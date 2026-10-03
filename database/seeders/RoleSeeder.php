<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([User::ROLE_SUPER_ADMIN, User::ROLE_PROJECT_ADMIN, User::ROLE_TEAM_LEADER, User::ROLE_EMPLOYEE] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
