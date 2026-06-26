<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // employees
            'view employees',
            'create employees',
            'update employees',
            'delete employees',
            // attendance
            'view attendance',
            'manage attendance',
            'clock in out',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $hr = Role::firstOrCreate(['name' => 'hr']);
        $employee = Role::firstOrCreate(['name' => 'employee']);

        // admin gets everything
        $admin->syncPermissions($permissions);

        // hr can manage employees and attendance
        $hr->syncPermissions([
            'view employees',
            'create employees',
            'update employees',
            'view attendance',
            'manage attendance',
        ]);

        // employee can only view their own data and clock in/out
        $employee->syncPermissions([
            'view attendance',
            'clock in out',
        ]);
    }
}
