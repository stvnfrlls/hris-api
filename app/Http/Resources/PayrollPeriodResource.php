<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'start_date'  => $this->start_date->toDateString(),
            'end_date'    => $this->end_date->toDateString(),
            'period_type' => $this->period_type,
            'month'       => $this->month,
            'year'        => $this->year,
            'status'      => $this->status,
        ];
    }
}
