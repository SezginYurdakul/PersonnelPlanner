<?php

namespace App\Modules\Leave\Http\Resources;

use App\Modules\Staff\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Leave\Models\LeaveRequest */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'leave_type' => new LeaveTypeResource($this->whenLoaded('leaveType')),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'approved_by' => $this->approved_by,
        ];
    }
}
