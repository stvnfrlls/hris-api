<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id'    => Employee::factory(),
            'leave_type_id'  => LeaveType::factory(),
            'start_date'     => fn() => now()->addDays(2)->toDateString(),
            'end_date'       => fn() => now()->addDays(4)->toDateString(),
            'days_requested' => 3,
            'reason'         => $this->faker->sentence(),
            'status'         => 'pending',
            'reviewed_by'    => null,
            'remarks'        => null,
            'reviewed_at'    => null,
        ];
    }
}
