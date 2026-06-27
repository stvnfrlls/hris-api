<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->word() . ' Department',
            'code'        => strtoupper($this->faker->unique()->lexify('???')),
            'description' => $this->faker->sentence(),
            'is_active'   => true,
        ];
    }
}
