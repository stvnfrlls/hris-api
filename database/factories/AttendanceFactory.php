<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date'        => fake()->unique()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
            'clock_in'    => '08:00:00',
            'clock_out'   => '17:00:00',
            'status'      => 'present',
            'remarks'     => null,
        ];
    }
}
