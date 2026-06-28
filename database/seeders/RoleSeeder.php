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
            // departments
            'view departments',
            'create departments',
            'update departments',
            'delete departments',
            // positions
            'view positions',
            'create positions',
            'update positions',
            'delete positions',
            // leave
            'view leave requests',
            'create leave requests',
            'review leave requests',
            'manage leave types',
            // payroll
            'view payroll',
            'manage payroll',
            'manage salary',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $hr = Role::firstOrCreate(['name' => 'hr']);
        $employee = Role::firstOrCreate(['name' => 'employee']);

        // admin gets everything
        $admin->syncPermissions($permissions);

        // hr can manage employees, attendance, departments, and positions
        // but cannot delete departments or positions
        $hr->syncPermissions([
            'view employees',
            'create employees',
            'update employees',
            'view attendance',
            'manage attendance',
            'view departments',
            'create departments',
            'update departments',
            'view positions',
            'create positions',
            'update positions',
            'view leave requests',
            'review leave requests',
            'view payroll',
            'manage salary',
        ]);

        // employee can only view their own data and clock in/out
        $employee->syncPermissions([
            'view attendance',
            'clock in out',
            'view departments',
            'view positions',
            'view leave requests',
            'create leave requests',
            'view payroll',
        ]);
    }
}
