<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Engineering',
                'code' => 'ENG',
                'description' => 'Software and systems development',
                'positions' => [
                    ['name' => 'Backend Developer',  'employment_type' => 'full_time'],
                    ['name' => 'Frontend Developer', 'employment_type' => 'full_time'],
                    ['name' => 'QA Engineer',        'employment_type' => 'full_time'],
                ],
            ],
            [
                'name' => 'Human Resources',
                'code' => 'HR',
                'description' => 'People and culture management',
                'positions' => [
                    ['name' => 'HR Manager',    'employment_type' => 'full_time'],
                    ['name' => 'HR Associate',  'employment_type' => 'full_time'],
                ],
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
                'description' => 'Financial operations and accounting',
                'positions' => [
                    ['name' => 'Accountant',       'employment_type' => 'full_time'],
                    ['name' => 'Finance Analyst',  'employment_type' => 'full_time'],
                ],
            ],
        ];

        foreach ($departments as $dept) {
            $positions = $dept['positions'];
            unset($dept['positions']);

            $department = Department::firstOrCreate(['code' => $dept['code']], $dept);

            foreach ($positions as $pos) {
                Position::firstOrCreate(
                    ['department_id' => $department->id, 'name' => $pos['name']],
                    $pos
                );
            }
        }
    }
}
