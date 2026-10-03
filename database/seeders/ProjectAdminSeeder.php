<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun admin proyek contoh (hanya mengelola proyek KDR) untuk pengembangan & QA.
 */
class ProjectAdminSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('code', 'KDR')->firstOrFail();

        $user = User::firstOrCreate(['username' => 'admin.kdr'], [
            'name' => 'Admin Proyek Kedurus',
            'password' => 'password',
        ]);
        $user->assignRole(User::ROLE_PROJECT_ADMIN);
        $user->managedProjects()->syncWithoutDetaching([$project->id]);
    }
}
