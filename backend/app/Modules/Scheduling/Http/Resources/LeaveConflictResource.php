<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\DTOs\LeaveConflictData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveConflictData */
class LeaveConflictResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->assignment;

        return [
            'assignment_id' => $assignment->id,
            'employee' => [
                'id' => $assignment->employee->id,
                'first_name' => $assignment->employee->first_name,
                'last_name' => $assignment->employee->last_name,
            ],
            'role' => $assignment->role ? [
                'id' => $assignment->role->id,
                'name' => $assignment->role->name,
            ] : null,
            'line' => $assignment->line ? [
                'id' => $assignment->line->id,
                'name' => $assignment->line->name,
            ] : null,
            'work_date' => $assignment->work_date->toDateString(),
            'reason' => $this->reason,
        ];
    }
}
