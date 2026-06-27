<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Vacation Leave',           'code' => 'VL',  'days_allowed' => 15],
            ['name' => 'Sick Leave',               'code' => 'SL',  'days_allowed' => 10],
            ['name' => 'Emergency Leave',          'code' => 'EL',  'days_allowed' => 5],
            ['name' => 'Maternity Leave',          'code' => 'ML',  'days_allowed' => 105],
            ['name' => 'Paternity Leave',          'code' => 'PL',  'days_allowed' => 7],
        ];

        foreach ($types as $type) {
            LeaveType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name'         => $type['name'],
                    'days_allowed' => $type['days_allowed'],
                ]
            );
        }
    }
}
