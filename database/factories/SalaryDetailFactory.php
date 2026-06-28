<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\SalaryDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryDetail>
 */
class SalaryDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id'  => Employee::factory(),
            'basic_salary' => $this->faker->randomElement([20000, 25000, 30000, 35000, 40000]),
        ];
    }
}
