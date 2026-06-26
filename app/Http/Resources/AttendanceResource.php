<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'date'        => $this->date->toDateString(),
            'clock_in'    => $this->clock_in?->format('H:i'),
            'clock_out'   => $this->clock_out?->format('H:i'),
            'status'      => $this->status,
            'remarks'     => $this->remarks,
            'employee'    => [
                'id'            => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name'          => $this->employee->user->name,
            ],
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
