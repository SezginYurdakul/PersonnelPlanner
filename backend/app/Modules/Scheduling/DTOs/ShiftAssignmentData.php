<?php

namespace App\Modules\Scheduling\DTOs;

final readonly class ShiftAssignmentData
{
    public function __construct(
        public int $employeeId,
        public int $lineId,
        public ?int $shiftPatternId,
        public string $workDate,
        public ?int $roleId,
        public ?string $startsAt,
        public ?string $endsAt,
        public string $status,
        public string $source,
        public ?string $notes,
        public bool $confirmOverride = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'line_id' => $this->lineId,
            'shift_pattern_id' => $this->shiftPatternId,
            'work_date' => $this->workDate,
            'role_id' => $this->roleId,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
        ];
    }
}
