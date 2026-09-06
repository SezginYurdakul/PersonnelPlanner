<?php

namespace App\Modules\Leave\DTOs;

use Illuminate\Support\Carbon;

final readonly class LeaveRequestData
{
    public function __construct(
        public int $employeeId,
        public int $leaveTypeId,
        public Carbon $startDate,
        public Carbon $endDate,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'leave_type_id' => $this->leaveTypeId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'reason' => $this->reason,
        ];
    }
}
