<?php

namespace App\Modules\Dashboard\DTOs;

final readonly class DashboardSummaryData
{
    /**
     * @param  array<int, array{id: int, label: ?string, status: string}>  $currentWeekSchedules  every
     *                                                                                            draft/proposed/approved schedule for the current week (ProjectPlan.md's
     *                                                                                            multi-scenario support) - empty if none exist yet.
     * @param  array<int, array{employee_name: string, start_date: string, end_date: string}>  $onLeaveThisWeek
     * @param  array<int, array{employee_name: string, type: string, work_date: ?string}>  $pendingShiftNotices
     */
    public function __construct(
        public int $activeEmployeeCount,
        public int $vastEmployeeCount,
        public int $uitzendkrachtEmployeeCount,
        public int $unlinkedAccountCount,
        public int $pendingActivationCount,
        public int $pendingLeaveRequestCount,
        public int $pendingShiftNoticeCount,
        public int $unfilledMandatoryStationCount,
        public array $currentWeekSchedules,
        public array $onLeaveThisWeek,
        public array $pendingShiftNotices,
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
            'pending_activation_count' => $this->pendingActivationCount,
            'pending_leave_request_count' => $this->pendingLeaveRequestCount,
            'pending_shift_notice_count' => $this->pendingShiftNoticeCount,
            'unfilled_mandatory_station_count' => $this->unfilledMandatoryStationCount,
            'current_week_schedules' => $this->currentWeekSchedules,
            'on_leave_this_week' => $this->onLeaveThisWeek,
            'pending_shift_notices' => $this->pendingShiftNotices,
        ];
    }
}
