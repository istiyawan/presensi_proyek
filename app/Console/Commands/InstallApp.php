<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:install
    {--company= : Nama perusahaan/KSO}
    {--app-name= : Nama aplikasi}
    {--username=admin : Username super admin}
    {--email= : Email super admin}
    {--password= : Password super admin (min. 8 karakter)}')]
#[Description('Inisialisasi server klien baru: migrasi, role, profil perusahaan, super admin')]
class InstallApp extends Command
{
    public function handle(SettingService $settings): int
    {
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $company = $this->option('company') ?: $this->ask('Nama perusahaan/KSO');
        $appName = $this->option('app-name') ?: $this->ask('Nama aplikasi', 'Presensi Proyek');
        $username = $this->option('username');
        $email = $this->option('email') ?: $this->ask('Email super admin (opsional)');
        $password = $this->option('password') ?: $this->secret('Password super admin');

        if (strlen((string) $password) < 8) {
            $this->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $settings->setApp(['company_name' => $company, 'app_name' => $appName]);

        $admin = User::updateOrCreate(['username' => $username], [
            'name' => 'Super Admin',
            'email' => $email ?: null,
            'password' => $password,
            'is_active' => true,
        ]);
        $admin->syncRoles([User::ROLE_SUPER_ADMIN]);

        $this->info("Instalasi selesai. Login web admin dengan username \"{$username}\".");
        $this->line('Langkah berikut: buat proyek, titik lokasi, shift, dan karyawan di web admin.');

        return self::SUCCESS;
    }
}
