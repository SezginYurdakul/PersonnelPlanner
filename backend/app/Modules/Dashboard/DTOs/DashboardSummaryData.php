<?php

namespace App\Modules\Dashboard\DTOs;

final readonly class DashboardSummaryData
{
    public function __construct(
        public int $activeEmployeeCount,
        public int $vastEmployeeCount,
        public int $uitzendkrachtEmployeeCount,
        public int $unlinkedAccountCount,
        public int $pendingLeaveRequestCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'active_employee_count' => $this->activeEmployeeCount,
            'vast_employee_count' => $this->vastEmployeeCount,
            'uitzendkracht_employee_count' => $this->uitzendkrachtEmployeeCount,
            'unlinked_account_count' => $this->unlinkedAccountCount,
            'pending_leave_request_count' => $this->pendingLeaveRequestCount,
        ];
    }
}
