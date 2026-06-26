<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'employee_code'   => 'EMP-' . $this->faker->unique()->numberBetween(100, 999),
            'department'      => $this->faker->randomElement(['Engineering', 'HR', 'Finance', 'Operations']),
            'position'        => $this->faker->jobTitle(),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time', 'contractual']),
            'hire_date'       => $this->faker->date(),
            'status'          => 'active',
        ];
    }
}
