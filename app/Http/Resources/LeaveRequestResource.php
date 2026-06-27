<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'start_date'     => $this->start_date->toDateString(),
            'end_date'       => $this->end_date->toDateString(),
            'days_requested' => $this->days_requested,
            'reason'         => $this->reason,
            'status'         => $this->status,
            'remarks'        => $this->remarks,
            'reviewed_at'    => $this->reviewed_at?->toDateTimeString(),
            'leave_type'     => [
                'id'   => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
            ],
            'employee'       => [
                'id'            => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name'          => $this->employee->user->name,
            ],
            'reviewed_by'    => $this->reviewer?->name,
            'created_at'     => $this->created_at->toDateTimeString(),
        ];
    }
}
