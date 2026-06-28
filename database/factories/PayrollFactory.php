<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basicSalary     = 25000;
        $grossPay        = 12500;
        $totalDeductions = 1000;

        $period = PayrollPeriod::firstOrCreate(
            [
                'month'       => now()->month,
                'year'        => now()->year,
                'period_type' => 'first_half',
            ],
            [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date'   => now()->startOfMonth()->addDays(14)->toDateString(),
                'status'     => 'draft',
            ]
        );

        return [
            'employee_id'       => Employee::factory(),
            'payroll_period_id' => $period->id,
            'basic_salary'      => $basicSalary,
            'days_worked'       => 13,
            'days_absent'       => 0,
            'late_minutes'      => 0,
            'gross_pay'         => $grossPay,
            'total_deductions'  => $totalDeductions,
            'net_pay'           => $grossPay - $totalDeductions,
            'status'            => 'draft',
        ];
    }
}
