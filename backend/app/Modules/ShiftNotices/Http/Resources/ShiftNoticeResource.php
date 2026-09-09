<?php

namespace App\Modules\ShiftNotices\Http\Resources;

use App\Modules\Staff\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\ShiftNotices\Models\ShiftNotice */
class ShiftNoticeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'shift_assignment' => $this->whenLoaded('shiftAssignment', fn () => $this->shiftAssignment ? [
                'id' => $this->shiftAssignment->id,
                'work_date' => $this->shiftAssignment->work_date?->toDateString(),
                'line' => $this->shiftAssignment->relationLoaded('line') && $this->shiftAssignment->line
                    ? ['id' => $this->shiftAssignment->line->id, 'name' => $this->shiftAssignment->line->name]
                    : null,
                'shift_pattern' => $this->shiftAssignment->relationLoaded('shiftPattern') && $this->shiftAssignment->shiftPattern
                    ? ['id' => $this->shiftAssignment->shiftPattern->id, 'name' => $this->shiftAssignment->shiftPattern->name]
                    : null,
            ] : null),
            'type' => $this->type,
            'delay_minutes' => $this->delay_minutes,
            'note' => $this->note,
            'status' => $this->status,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
        ];
    }
}
