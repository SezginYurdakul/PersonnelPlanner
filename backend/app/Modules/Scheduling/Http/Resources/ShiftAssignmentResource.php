<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShiftAssignment */
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
                'slot_type' => $this->shiftPattern->slot_type,
                'start_time' => $this->shiftPattern->start_time,
                'end_time' => $this->shiftPattern->end_time,
                'crosses_midnight' => $this->shiftPattern->crosses_midnight,
            ] : null),
            'work_date' => $this->work_date?->toDateString(),
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'role_kind' => $this->role->role_kind,
                'attachment_type' => $this->role->attachment_type,
                // The ROLE's own line_id (e.g. null for Shift Lead/Vardiya Sefi or a
                // company-wide secondary task) - not this assignment's own line_id, which
                // is always a real, concrete line borrowed purely to resolve hours. Lets
                // the grid group these under their own cross-line row instead of nesting
                // them inside whichever line happened to be borrowed.
                'line_id' => $this->role->line_id,
            ] : null),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
        ];
    }
}
