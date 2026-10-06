<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penandatangan laporan diganti menjadi Project Manager NINDYA - ITP, KSO.
 * Penandatangan lama dinonaktifkan (tidak dihapus) agar bisa diaktifkan lagi dari Pengaturan Proyek.
 */
return new class extends Migration
{
    private const SIGNATORY = [
        'label' => 'Dibuat',
        'name' => 'Ardhian Elia Patria',
        'title' => 'Project Manager',
        'organization' => 'NINDYA - ITP, KSO',
    ];

    public function up(): void
    {
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            DB::table('report_signatories')->where('project_id', $projectId)->update(['is_active' => false, 'updated_at' => now()]);

            $existing = DB::table('report_signatories')
                ->where('project_id', $projectId)
                ->where('name', self::SIGNATORY['name'])
                ->first();

            $values = self::SIGNATORY + ['employee_id' => null, 'sort_order' => 1, 'is_active' => true, 'updated_at' => now()];
            $existing
                ? DB::table('report_signatories')->where('id', $existing->id)->update($values)
                : DB::table('report_signatories')->insert($values + ['project_id' => $projectId, 'created_at' => now()]);
        }
    }

    public function down(): void
    {
        // Data penandatangan tidak dikembalikan otomatis; atur ulang dari Pengaturan Proyek.
    }
};
