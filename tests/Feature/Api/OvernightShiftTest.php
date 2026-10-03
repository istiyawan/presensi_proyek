<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Services\DailyCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsProjects;
use Tests\TestCase;

/**
 * Skenario: 2 Okt masuk 08:00 → pulang 3 Okt 01:00; 3 Okt masuk 08:00 → pulang 17:00.
 * Tanpa hitungan lembur — durasi dicatat apa adanya.
 */
class OvernightShiftTest extends TestCase
{
    use BuildsProjects, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function attend(string $type, $project, array $extra = [])
    {
        return $this->post("/api/v1/attendance/{$type}", $this->attendancePayload($project, $extra), ['Accept' => 'application/json']);
    }

    public function test_checkout_after_midnight_closes_previous_day(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        Sanctum::actingAs($employee->user);

        $this->travelTo('2026-10-02 08:00:00');
        $this->attend('check-in', $project)->assertCreated()->assertJsonPath('data.work_date', '2026-10-02');

        // 00:30 — jam tutup hari: shift 2 Okt masih berjalan, belum boleh dianggap lupa check-out
        $this->travelTo('2026-10-03 00:30:00');
        app(DailyCloseService::class)->close($project, CarbonImmutable::parse('2026-10-02'));
        $this->assertNotContains('missing_checkout', Attendance::sole()->flags ?? []);

        // Aplikasi harus tetap menawarkan check-out untuk shift 2 Okt
        $this->getJson("/api/v1/attendance/today?project_id={$project->id}")
            ->assertJsonPath('data.attendance', null)
            ->assertJsonPath('data.open_attendance.work_date', '2026-10-02');

        $this->travelTo('2026-10-03 01:00:00');
        $this->attend('check-out', $project)
            ->assertOk()
            ->assertJsonPath('data.work_date', '2026-10-02')
            ->assertJsonPath('data.duration_minutes', 17 * 60)
            ->assertJsonPath('data.check_out.next_day', true);

        $this->travelTo('2026-10-03 08:00:00');
        $this->attend('check-in', $project)->assertCreated()->assertJsonPath('data.work_date', '2026-10-03');

        $this->travelTo('2026-10-03 17:00:00');
        $this->attend('check-out', $project)
            ->assertOk()
            ->assertJsonPath('data.work_date', '2026-10-03')
            ->assertJsonPath('data.duration_minutes', 9 * 60)
            ->assertJsonPath('data.check_out.next_day', false);

        $this->assertSame(2, Attendance::count());
        $this->assertSame(['hadir', 'hadir'], Attendance::orderBy('work_date')->pluck('status')->all());
    }

    public function test_check_in_while_previous_shift_open_is_blocked_unless_abandoned(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        Sanctum::actingAs($employee->user);

        $this->travelTo('2026-10-02 14:00:00');
        $this->attend('check-in', $project)->assertCreated();

        // 3 Okt 06:00 (16 jam kemudian) — masih di jendela check-out shift 2 Okt
        $this->travelTo('2026-10-03 06:00:00');
        $this->attend('check-in', $project)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'open_previous_shift')
            ->assertJsonPath('data.work_date', '2026-10-02');

        // Karyawan memilih "lupa check-out, mulai presensi baru"
        $this->attend('check-in', $project, ['abandon_previous' => 'true'])
            ->assertCreated()
            ->assertJsonPath('data.work_date', '2026-10-03');

        $previous = Attendance::whereDate('work_date', '2026-10-02')->sole();
        $this->assertNull($previous->check_out_at);
        $this->assertContains('missing_checkout', $previous->flags);
    }

    public function test_missing_checkout_flagged_once_window_passes(): void
    {
        $project = $this->makeProject();
        $employee = $this->makeEmployee($project);
        Sanctum::actingAs($employee->user);

        $this->travelTo('2026-10-02 08:00:00');
        $this->attend('check-in', $project)->assertCreated();

        // Jendela default 20 jam → 3 Okt 04:00
        $this->travelTo('2026-10-03 04:30:00');
        $this->artisan('attendance:flag-missing-checkout')->assertSuccessful();
        $this->assertContains('missing_checkout', Attendance::sole()->flags);

        // Check-out setelah jendela lewat ditolak (perlu koreksi admin)
        $this->attend('check-out', $project)->assertStatus(409)->assertJsonPath('reason', 'no_checkin');
    }

    public function test_checkout_window_is_configurable(): void
    {
        $project = $this->makeProject(['max_work_hours' => 24]);
        Sanctum::actingAs($this->makeEmployee($project)->user);

        $this->travelTo('2026-10-02 08:00:00');
        $this->attend('check-in', $project)->assertCreated();

        $this->travelTo('2026-10-03 06:00:00'); // 22 jam
        $this->attend('check-out', $project)->assertOk()->assertJsonPath('data.duration_minutes', 22 * 60);
    }
}
