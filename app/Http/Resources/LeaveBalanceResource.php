<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'year'           => $this->year,
            'total_days'     => $this->total_days,
            'used_days'      => $this->used_days,
            'remaining_days' => $this->remaining_days,
            'leave_type'     => [
                'id'   => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
            ],
        ];
    }
}
