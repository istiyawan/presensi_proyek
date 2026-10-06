<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Project;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Data awal dari dokumen acuan "ABSENSI BULAN 1.pdf".
 * Password awal semua karyawan: lihat DEFAULT_PASSWORD — wajib diganti.
 */
class KedurusProjectSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Presensi#2026';

    /** username => [nama, gelar depan, gelar belakang, jabatan, team leader?] */
    private const EMPLOYEES = [
        'edi.santoso' => ['Edi Santoso', null, 'S.T., M.T.', 'Team Leader', true],
        'puguh.sarwono' => ['Puguh Sarwono', null, null, 'Water Resources Engineer', false],
        'danang.trisno' => ['Danang Ady Trisno', null, 'S.T.', 'Ahli Hidrologi', false],
        'bambang.rianto' => ['Bambang Rianto', null, 'S.T.', 'Ahli Hidromekanikal', false],
        'edy.jayanto' => ['Edy Jayanto', null, 'S.T.', 'Ahli Geologi/Geoteknik', false],
        'helmy.wijaya' => ['Helmy Mukti Wijaya', null, 'S.T.', 'Ahli Geodesi', false],
        'nabilah.ismiradiana' => ['Nabilah Ismiradiana', null, null, 'CAD Operator 1', false],
        'agus.irawan' => ['Agus Irawan', null, null, 'Juru Ukur 1', false],
        'budiarto' => ['Budiarto', null, null, 'Administrasi dan Keuangan', false],
    ];

    public function run(SettingService $settings): void
    {
        $settings->setApp([
            'company_name' => 'Nindya - ITP KSO',
            'app_name' => 'Presensi Proyek',
        ]);

        $project = Project::updateOrCreate(['code' => 'KDR'], [
            'name' => 'Nindya - ITP KSO Kedurus',
            'city' => 'Surabaya',
            // Tanggal kontrak belum diketahui → sementara awal bulan; Bulan ke-1 = 25/02/2026 – 26/03/2026
            'contract_start_date' => '2026-02-01',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $settings->setProject($project, ['report_city' => 'Surabaya']);

        $project->locations()->updateOrCreate(['name' => 'Kantor Proyek Kedurus'], [
            'latitude' => -7.3154,
            'longitude' => 112.6855,
            'radius_m' => 100,
            'is_active' => true,
        ]);

        $shift = $project->shifts()->updateOrCreate(['name' => 'Jam Kerja Standard'], [
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_enabled' => false,
            'late_tolerance_min' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);

        foreach (self::EMPLOYEES as $username => [$name, $prefix, $suffix, $positionName, $isLeader]) {
            $user = User::firstOrCreate(['username' => $username], [
                'name' => $name,
                'password' => self::DEFAULT_PASSWORD,
            ]);
            $user->assignRole($isLeader ? [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER] : [User::ROLE_EMPLOYEE]);

            $employee = Employee::updateOrCreate(['user_id' => $user->id], [
                'full_name' => $name,
                'title_prefix' => $prefix,
                'title_suffix' => $suffix,
                'position_id' => Position::firstOrCreate(['name' => $positionName])->id,
                'is_active' => true,
            ]);

            $project->employees()->syncWithoutDetaching([$employee->id => [
                'shift_id' => $shift->id,
                'start_date' => '2026-02-25',
                'is_team_leader' => $isLeader,
            ]]);
        }

        $project->signatories()->updateOrCreate(['label' => 'Dibuat'], [
            'employee_id' => null,
            'name' => 'Ardhian Elia Patria',
            'title' => 'Project Manager',
            'organization' => 'NINDYA - ITP, KSO',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
