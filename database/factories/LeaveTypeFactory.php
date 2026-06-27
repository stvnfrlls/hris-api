<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'         => $this->faker->unique()->randomElement([
                'Vacation Leave',
                'Sick Leave',
                'Emergency Leave',
                'Maternity Leave',
                'Paternity Leave',
            ]),
            'code'         => strtoupper($this->faker->unique()->lexify('??')),
            'days_allowed' => $this->faker->randomElement([5, 7, 10, 15]),
            'is_active'    => true,
        ];
    }
}
