<?php

namespace App\Modules\TimeAttendance\Http\Resources;

use App\Modules\Staff\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\TimeAttendance\Models\TimeClockEntry */
class TimeClockEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'shift_assignment_id' => $this->shift_assignment_id,
            'work_date' => $this->work_date?->toDateString(),
            'clock_in' => $this->clock_in?->toIso8601String(),
            'clock_out' => $this->clock_out?->toIso8601String(),
            'break_minutes' => $this->break_minutes,
            'worked_minutes' => $this->workedMinutes(),
            'source' => $this->source,
            'breaks' => $this->whenLoaded('breaks', fn () => $this->breaks->map(fn ($b) => [
                'id' => $b->id,
                'break_start' => $b->break_start->toIso8601String(),
                'break_end' => $b->break_end->toIso8601String(),
            ])),
        ];
    }
}
