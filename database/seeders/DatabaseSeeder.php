<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data pengembangan. Untuk server klien gunakan `php artisan app:install`.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole(User::ROLE_SUPER_ADMIN);

        $this->call(KedurusProjectSeeder::class);
        $this->call(ProjectAdminSeeder::class);
        Artisan::call('holidays:import', ['year' => 2026]);

        if (app()->environment('local')) {
            $this->call(DemoAttendanceSeeder::class);
        }
    }
}
