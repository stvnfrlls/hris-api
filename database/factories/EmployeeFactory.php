<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
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
        $department = Department::factory()->create();
        $position   = Position::factory()->create(['department_id' => $department->id]);
        
        return [
            'user_id'         => User::factory(),
            'employee_code'   => 'EMP-' . $this->faker->unique()->numberBetween(100, 999),
            'department_id'   => $department->id,
            'position_id'     => $position->id,
            'hire_date'       => $this->faker->date(),
            'status'          => 'active',
        ];
    }
}
