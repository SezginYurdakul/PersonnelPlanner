<?php

namespace App\Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One day in an employee's own weekly schedule view (ProjectPlan.md §12.3a/§8f) - a
 * narrow, day-by-day shape distinct from the admin's dense ScheduleResource, since the
 * PWA list is read-only and mobile-first rather than a drag-and-drop grid.
 *
 * @mixin \App\Modules\Scheduling\Models\ShiftAssignment
 */
class EmployeeScheduleDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'work_date' => $this->work_date?->toDateString(),
            'employee' => [
                'id' => $this->employee_id,
                'first_name' => $this->employee?->first_name,
                'last_name' => $this->employee?->last_name,
            ],
            'line' => $this->line ? [
                'id' => $this->line->id,
                'name' => $this->line->name,
            ] : null,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ] : null,
            'starts_at' => $this->effectiveStart(),
            'ends_at' => $this->effectiveEnd(),
            'crosses_midnight' => $this->effectiveCrossesMidnight(),
        ];
    }
}
