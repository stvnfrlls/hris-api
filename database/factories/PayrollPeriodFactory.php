<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodType = $this->faker->randomElement(['first_half', 'second_half']);
        $month      = $this->faker->numberBetween(1, 12);
        $year       = now()->year;

        $startDate = $periodType === 'first_half'
            ? "{$year}-{$month}-01"
            : "{$year}-{$month}-16";

        $endDate = $periodType === 'first_half'
            ? "{$year}-{$month}-15"
            : date('Y-m-t', strtotime("{$year}-{$month}-01"));

        return [
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'period_type' => $periodType,
            'month'       => $month,
            'year'        => $year,
            'status'      => 'draft',
        ];
    }
}
