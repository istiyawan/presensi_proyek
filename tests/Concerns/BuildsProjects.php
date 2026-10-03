<?php

namespace Tests\Concerns;

use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait BuildsProjects
{
    /** Titik proyek acuan (Kedurus, Surabaya) */
    protected const LAT = -7.3154;

    protected const LNG = 112.6855;

    protected function makeProject(array $settings = [], array $shift = []): Project
    {
        $this->seed(RoleSeeder::class);

        $project = Project::create([
            'code' => 'P'.Str::random(5),
            'name' => 'Proyek Uji',
            'city' => 'Surabaya',
            'contract_start_date' => '2026-02-01',
            'timezone' => 'Asia/Jakarta',
        ]);
        $project->locations()->create([
            'name' => 'Kantor Proyek', 'latitude' => self::LAT, 'longitude' => self::LNG, 'radius_m' => 100,
        ]);
        $project->shifts()->create($shift + [
            'name' => 'Jam Kerja Standard', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'is_default' => true,
        ]);

        if ($settings) {
            app(SettingService::class)->setProject($project, $settings);
        }

        return $project;
    }

    protected function makeEmployee(Project $project, array $pivot = []): Employee
    {
        $user = User::create([
            'name' => 'Karyawan Uji',
            'username' => 'user'.Str::random(6),
            'password' => 'password',
        ]);
        $user->assignRole(User::ROLE_EMPLOYEE);

        $employee = Employee::create(['user_id' => $user->id, 'full_name' => 'Karyawan Uji']);
        $project->employees()->attach($employee->id, $pivot + [
            'shift_id' => $project->defaultShift()->id,
            'start_date' => '2026-01-01',
        ]);

        return $employee;
    }

    protected function attendancePayload(Project $project, array $overrides = []): array
    {
        return $overrides + [
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'accuracy' => 10,
            'is_mock' => 'false',
            'photo' => UploadedFile::fake()->image('selfie.jpg', 600, 800),
        ];
    }

    /** Koordinat ±$meters ke utara dari titik proyek. */
    protected function latNorth(int $meters): float
    {
        // 1° lintang = 2πR/360 dengan R yang sama seperti GeoService (6.371 km)
        return self::LAT + $meters / (6371000 * M_PI / 180);
    }
}
