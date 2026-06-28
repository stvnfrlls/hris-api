<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'basic_salary'     => $this->basic_salary,
            'days_worked'      => $this->days_worked,
            'days_absent'      => $this->days_absent,
            'late_minutes'     => $this->late_minutes,
            'gross_pay'        => $this->gross_pay,
            'total_deductions' => $this->total_deductions,
            'net_pay'          => $this->net_pay,
            'status'           => $this->status,
            'period'           => [
                'id'          => $this->period->id,
                'start_date'  => $this->period->start_date->toDateString(),
                'end_date'    => $this->period->end_date->toDateString(),
                'period_type' => $this->period->period_type,
                'month'       => $this->period->month,
                'year'        => $this->period->year,
            ],
            'employee'         => [
                'id'            => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name'          => $this->employee->user->name,
            ],
            'deductions'       => $this->whenLoaded(
                'deductions',
                fn() =>
                $this->deductions->map(fn($d) => [
                    'name'   => $d->name,
                    'type'   => $d->type,
                    'amount' => $d->amount,
                ])
            ),
        ];
    }
}
