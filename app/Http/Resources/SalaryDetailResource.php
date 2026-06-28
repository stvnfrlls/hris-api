<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'basic_salary' => $this->basic_salary,
            'hourly_rate'  => $this->hourly_rate,
            'employee'     => [
                'id'            => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name'          => $this->employee->user->name,
            ],
        ];
    }
}
