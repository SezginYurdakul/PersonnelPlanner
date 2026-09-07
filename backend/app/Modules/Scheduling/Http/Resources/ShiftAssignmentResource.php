<?php

namespace App\Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Scheduling\Models\ShiftAssignment */
class ShiftAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
            ]),
            'line' => $this->whenLoaded('line', fn () => [
                'id' => $this->line->id,
                'name' => $this->line->name,
                'code' => $this->line->code,
            ]),
            'shift_pattern' => $this->whenLoaded('shiftPattern', fn () => $this->shiftPattern ? [
                'id' => $this->shiftPattern->id,
                'name' => $this->shiftPattern->name,
                'start_time' => $this->shiftPattern->start_time,
                'end_time' => $this->shiftPattern->end_time,
                'crosses_midnight' => $this->shiftPattern->crosses_midnight,
            ] : null),
            'work_date' => $this->work_date?->toDateString(),
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'role_kind' => $this->role->role_kind,
            ] : null),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
        ];
    }
}
