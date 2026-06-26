<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'employee_code'   => $this->employee_code,
            'department'      => $this->department,
            'position'        => $this->position,
            'employment_type' => $this->employment_type,
            'hire_date'       => $this->hire_date->toDateString(),
            'status'          => $this->status,
            'user'            => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ],
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
