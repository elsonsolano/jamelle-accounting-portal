<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Admin', 'Accountant', 'Viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manageUsers = Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web'])
            ->givePermissionTo($manageUsers);
    }
}
