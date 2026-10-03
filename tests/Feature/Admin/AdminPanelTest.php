<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    private function admin(string $role = User::ROLE_SUPER_ADMIN): User
    {
        $user = User::create(['name' => 'Admin Uji', 'username' => 'admin'.$role, 'password' => 'password']);
        $user->assignRole($role);

        return $user;
    }

    public function test_login_page_and_web_login(): void
    {
        $this->makeProject();
        $this->admin();

        $this->get('/login')->assertOk()->assertSee('Selamat datang');
        $this->post('/login', ['username' => 'adminsuper_admin', 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertOk();
    }

    public function test_employee_account_cannot_open_web_admin(): void
    {
        $employee = $this->makeEmployee($this->makeProject());

        $this->post('/login', ['username' => $employee->user->username, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_all_pages_render_for_super_admin(): void
    {
        $project = $this->makeProject();
        $this->makeEmployee($project, ['is_team_leader' => true]);
        $this->actingAs($this->admin());

        foreach (['/', '/attendances', '/approvals', '/employees', '/shifts', '/locations', '/holidays', '/settings/project',
            '/settings/project?tab=signatories', '/positions', '/projects', '/users', '/settings/app'] as $url) {
            $this->get($url)->assertOk();
        }

        foreach (['/attendances/data', '/employees/data', '/approvals/leaves', '/approvals/offsite', '/users/data'] as $url) {
            $this->getJson($url.'?draw=1&start=0&length=10')->assertOk()->assertJsonStructure(['data', 'recordsTotal']);
        }
    }

    public function test_create_employee_with_account_and_assignment(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        $this->postJson('/employees', [
            'full_name' => 'Rina Kartika', 'title_suffix' => 'S.T.', 'position_id' => 'Ahli K3',
            'username' => 'rina.kartika', 'password' => 'rahasia123',
            'shift_id' => $project->defaultShift()->id, 'start_date' => '2026-10-01', 'is_team_leader' => '1',
        ])->assertOk();

        $employee = Employee::where('full_name', 'Rina Kartika')->sole();
        $this->assertSame('Ahli K3', $employee->position->name);
        $this->assertTrue($employee->user->hasRole(User::ROLE_TEAM_LEADER));
        $this->assertTrue($project->employees()->whereKey($employee->id)->exists());
    }

    public function test_manual_correction_handles_checkout_after_midnight(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'project_id' => $project->id, 'work_date' => '2026-10-02',
            'check_in_at' => '2026-10-02 08:00:00', 'status' => 'hadir', 'flags' => ['missing_checkout'],
        ]);
        $this->actingAs($this->admin());

        $this->putJson("/attendances/{$attendance->id}", [
            'status' => 'hadir', 'check_in_time' => '08:00', 'check_out_time' => '01:00', 'note' => 'Lupa check-out, pulang jam 1 pagi',
        ])->assertOk();

        $attendance->refresh();
        $this->assertSame('2026-10-03 01:00:00', $attendance->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame(17 * 60, $attendance->duration_minutes);
        $this->assertNull($attendance->flags);
        $this->assertTrue($attendance->is_manual);
    }

    public function test_manual_attendance_note_is_optional_but_correction_requires_it(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        $this->actingAs($this->admin());

        $this->postJson('/attendances', [
            'employee_id' => $employee->id, 'work_date' => '2026-10-02',
            'status' => 'hadir', 'check_in_time' => '08:00', 'check_out_time' => '17:00',
        ])->assertOk();

        $attendance = Attendance::where('employee_id', $employee->id)->sole();
        $this->assertNull($attendance->note);
        $this->assertTrue($attendance->is_manual);

        $this->putJson("/attendances/{$attendance->id}", ['status' => 'izin'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
    }

    public function test_correction_can_edit_date_coordinates_and_photo(): void
    {
        Storage::fake('local');
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        Storage::disk('local')->put('attendances/old_in.jpg', 'x');
        $attendance = Attendance::create([
            'employee_id' => $employee->id, 'project_id' => $project->id, 'work_date' => '2026-10-02',
            'check_in_at' => '2026-10-02 08:00:00', 'check_out_at' => '2026-10-02 17:00:00', 'status' => 'hadir',
            'check_in_lat' => self::LAT, 'check_in_lng' => self::LNG, 'check_in_mode' => 'onsite',
            'check_in_photo' => 'attendances/old_in.jpg',
        ]);
        $this->actingAs($this->admin());

        $this->post("/attendances/{$attendance->id}", [
            '_method' => 'PUT', 'work_date' => '2026-10-01', 'status' => 'hadir',
            'check_in_time' => '08:00', 'check_out_time' => '17:00',
            'check_in_lat' => -7.3254, 'check_in_lng' => self::LNG, // ±1,1 km dari titik proyek
            'check_out_lat' => self::LAT, 'check_out_lng' => self::LNG,
            'check_in_photo' => UploadedFile::fake()->image('baru.jpg', 600, 800),
            'note' => 'Salah tanggal & lokasi',
        ], ['Accept' => 'application/json'])->assertOk();

        $attendance->refresh();
        $this->assertSame('2026-10-01', $attendance->work_date->toDateString());
        $this->assertSame('2026-10-01 08:00:00', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame('offsite', $attendance->check_in_mode);
        $this->assertGreaterThan(1000, $attendance->check_in_distance_m);
        $this->assertSame('onsite', $attendance->check_out_mode);
        $this->assertNotSame('attendances/old_in.jpg', $attendance->check_in_photo);
        Storage::disk('local')->assertExists([$attendance->check_in_photo, $attendance->check_in_thumb]);
        Storage::disk('local')->assertMissing('attendances/old_in.jpg');
    }

    public function test_correction_rejects_date_already_used_by_another_attendance(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        $base = ['employee_id' => $employee->id, 'project_id' => $project->id, 'status' => 'alpha'];
        Attendance::create($base + ['work_date' => '2026-10-01']);
        $attendance = Attendance::create($base + ['work_date' => '2026-10-02']);
        $this->actingAs($this->admin());

        $this->putJson("/attendances/{$attendance->id}", ['work_date' => '2026-10-01', 'status' => 'izin', 'note' => 'Pindah tanggal'])
            ->assertStatus(422);
        $this->assertSame('2026-10-02', $attendance->refresh()->work_date->toDateString());
    }

    public function test_project_admin_is_limited_to_assigned_projects(): void
    {
        $mine = $this->makeProject();
        $other = $this->makeProject();
        $admin = $this->admin(User::ROLE_PROJECT_ADMIN);
        $admin->managedProjects()->attach($mine->id);
        $this->actingAs($admin);

        $this->get('/employees')->assertOk();
        $this->post("/projects/{$other->id}/switch")->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/settings/app')->assertForbidden();
    }

    public function test_super_admin_creates_admin_users(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        // Payload sama seperti FormData dari modal "Tambah Admin"
        $this->postJson('/users', [
            'name' => 'Admin Proyek', 'username' => 'admin.proyek', 'email' => '', 'password' => 'Rahasia#123',
            'role' => User::ROLE_PROJECT_ADMIN, 'project_ids' => [(string) $project->id], 'is_active' => '1',
        ])->assertOk();
        $this->postJson('/users', [
            'name' => 'Admin Pusat', 'username' => 'admin.pusat', 'password' => 'Rahasia#123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_active' => '1',
        ])->assertOk();

        $created = User::where('username', 'admin.proyek')->firstOrFail();
        $this->assertTrue($created->hasRole(User::ROLE_PROJECT_ADMIN));
        $this->assertSame([$project->id], $created->managedProjects()->pluck('projects.id')->all());
        $this->assertTrue(User::where('username', 'admin.pusat')->firstOrFail()->hasRole(User::ROLE_SUPER_ADMIN));

        // Tabel memanggil projects.map() → harus array JSON, bukan string
        $rows = collect($this->getJson('/users/data?draw=1&start=0&length=10')->assertOk()->json('data'))->keyBy('username');
        $this->assertSame([$project->code], $rows['admin.proyek']['projects']);
        $this->assertSame([], $rows['admin.pusat']['projects']);
    }

    public function test_single_project_mode_allows_only_one_project(): void
    {
        $this->seed(RoleSeeder::class); // belum ada proyek → makeProject() tidak dipakai
        $this->actingAs($this->admin());
        $pendingAdmin = $this->admin(User::ROLE_PROJECT_ADMIN); // dibuat sebelum proyek ada
        $payload = fn (string $code) => ['code' => $code, 'name' => "Proyek {$code}", 'timezone' => 'Asia/Jakarta', 'is_active' => '1'];

        $this->get('/projects')->assertOk()->assertSee('Proyek Baru');
        $this->postJson('/projects', $payload('AAA'))->assertOk();
        $this->assertSame(['AAA'], $pendingAdmin->managedProjects()->pluck('code')->all());

        $this->get('/projects')->assertOk()->assertSee('Profil Proyek')->assertDontSee('Proyek Baru');
        $this->get('/')->assertOk()->assertDontSee('Ganti proyek');
        $this->postJson('/projects', $payload('BBB'))->assertStatus(422);
        $this->assertSame(1, Project::count());

        // Admin proyek baru otomatis mengelola satu-satunya proyek, tanpa memilih proyek
        $this->postJson('/users', [
            'name' => 'Admin Dua', 'username' => 'admin.dua', 'password' => 'Rahasia#123', 'role' => User::ROLE_PROJECT_ADMIN,
        ])->assertOk();
        $this->assertSame(['AAA'], User::where('username', 'admin.dua')->first()->managedProjects()->pluck('code')->all());
    }

    public function test_multi_project_mode_can_be_enabled(): void
    {
        config(['presensi.multi_project' => true]);
        $this->makeProject();
        $this->actingAs($this->admin());

        $this->get('/projects')->assertOk()->assertSee('Daftar Proyek')->assertSee('Proyek Baru');
        $this->postJson('/projects', ['code' => 'BBB', 'name' => 'Proyek Kedua', 'timezone' => 'Asia/Jakarta'])->assertOk();
        $this->get('/')->assertOk()->assertSee('Ganti proyek');
    }

    public function test_team_leader_can_monitor_but_not_manage(): void
    {
        $project = $this->makeProject();
        $leader = $this->makeEmployee($project, ['is_team_leader' => true]);
        $leader->user->assignRole(User::ROLE_TEAM_LEADER);
        $this->actingAs($leader->user);

        $this->get('/')->assertOk();
        $this->get('/attendances')->assertOk();
        $this->get('/approvals')->assertOk();
        $this->get('/employees')->assertForbidden();
        $this->get('/settings/project')->assertForbidden();
    }

    public function test_project_settings_are_saved_with_proper_types(): void
    {
        $project = $this->makeProject();
        $this->actingAs($this->admin());

        $this->putJson('/settings/project/location', [
            'allow_offsite' => '1', 'offsite_scope' => 'all', 'offsite_requires_approval' => '0', 'offsite_requires_note' => '1',
            'max_gps_accuracy_m' => '40', 'block_mock_location' => '1', 'require_checkout_in_location' => '0', 'max_work_hours' => '24',
        ])->assertOk();

        $s = app(SettingService::class)->project($project);
        $this->assertTrue($s['allow_offsite']);
        $this->assertFalse($s['require_checkout_in_location']);
        $this->assertSame(24, $s['max_work_hours']);
    }
}
