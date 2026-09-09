<?php

namespace App\Modules\TimeAttendance\DTOs;

final readonly class TimeClockEntryData
{
    public function __construct(
        public int $employeeId,
        public string $workDate,
        public string $clockIn,
        public string $clockOut,
        public int $breakMinutes,
        public ?int $shiftAssignmentId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'work_date' => $this->workDate,
            'clock_in' => $this->clockIn,
            'clock_out' => $this->clockOut,
            'break_minutes' => $this->breakMinutes,
            'shift_assignment_id' => $this->shiftAssignmentId,
        ];
    }
}
