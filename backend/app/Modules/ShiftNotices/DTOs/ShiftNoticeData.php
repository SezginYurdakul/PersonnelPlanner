<?php

namespace App\Modules\ShiftNotices\DTOs;

final readonly class ShiftNoticeData
{
    public function __construct(
        public int $employeeId,
        public int $shiftAssignmentId,
        public string $type,
        public ?int $delayMinutes,
        public ?string $note,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'shift_assignment_id' => $this->shiftAssignmentId,
            'type' => $this->type,
            'delay_minutes' => $this->delayMinutes,
            'note' => $this->note,
        ];
    }
}
