<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id'   => Department::factory(),
            'name'            => $this->faker->jobTitle(),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time', 'contractual']),
            'is_active'       => true,
        ];
    }
}
