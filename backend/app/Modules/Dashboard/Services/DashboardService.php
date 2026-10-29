<?php

namespace App\Modules\Dashboard\Services;

use App\Models\User;
use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Dashboard\Contracts\DashboardServiceContract;
use App\Modules\Dashboard\DTOs\DashboardSummaryData;
use App\Modules\Dashboard\DTOs\PlanningCalendarWeekData;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Services\MandatoryCoverageChecker;
use App\Modules\ShiftNotices\Models\ShiftNotice;
use App\Modules\Staff\Models\Employee;

final class DashboardService implements DashboardServiceContract
{
    public function __construct(private readonly MandatoryCoverageChecker $coverageChecker) {}

    public function summary(): DashboardSummaryData
    {
        $activeEmployees = Employee::query()->where('is_active', true);
        $companySetting = CompanySetting::current();
        $weekStart = $companySetting->startOfWeekFor(now());
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $currentWeekSchedules = Schedule::query()
            ->where('week_start_date', $weekStart->toDateString())
            ->get(['id', 'label', 'status']);

        $unfilledMandatoryStationCount = $currentWeekSchedules
            ->sum(fn (Schedule $schedule) => $this->coverageChecker->unresolvedBlockingSlots($schedule)->count());

        $onLeaveThisWeek = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $weekEnd)
            ->where('end_date', '>=', $weekStart)
            ->with('employee')
            ->get();

        $pendingShiftNotices = ShiftNotice::query()
            ->where('status', ShiftNotice::STATUS_SUBMITTED)
            ->with(['employee', 'shiftAssignment'])
            ->latest()
            ->get();

        return new DashboardSummaryData(
            activeEmployeeCount: (clone $activeEmployees)->count(),
            vastEmployeeCount: (clone $activeEmployees)->where('employee_type', 'vast')->count(),
            uitzendkrachtEmployeeCount: (clone $activeEmployees)->where('employee_type', 'uitzendkracht')->count(),
            unlinkedAccountCount: (clone $activeEmployees)->whereNull('user_id')->count(),
            pendingActivationCount: User::query()
                ->where('is_active', false)
                ->whereNull('invitation_token')
                ->whereNotNull('invited_at')
                ->count(),
            pendingLeaveRequestCount: LeaveRequest::query()->where('status', LeaveRequest::STATUS_PENDING)->count(),
            pendingShiftNoticeCount: $pendingShiftNotices->count(),
            unfilledMandatoryStationCount: $unfilledMandatoryStationCount,
            currentWeekSchedules: $currentWeekSchedules->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'label' => $schedule->label,
                'status' => $schedule->status,
            ])->all(),
            onLeaveThisWeek: $onLeaveThisWeek->map(fn (LeaveRequest $leaveRequest) => [
                'employee_name' => trim("{$leaveRequest->employee->first_name} {$leaveRequest->employee->last_name}"),
                'start_date' => $leaveRequest->start_date->toDateString(),
                'end_date' => $leaveRequest->end_date->toDateString(),
            ])->all(),
            pendingShiftNotices: $pendingShiftNotices->map(fn (ShiftNotice $notice) => [
                'employee_name' => trim("{$notice->employee->first_name} {$notice->employee->last_name}"),
                'type' => $notice->type,
                'work_date' => $notice->shiftAssignment?->work_date?->toDateString(),
            ])->all(),
        );
    }

    public function planningCalendar(int $weeksBefore, int $weeksAfter): array
    {
        $currentWeekStart = CompanySetting::current()->startOfWeekFor(now());
        $rangeStart = $currentWeekStart->copy()->subWeeks($weeksBefore);
        $rangeEnd = $currentWeekStart->copy()->addWeeks($weeksAfter)->addDays(6)->endOfDay();

        $schedulesByWeek = Schedule::query()
            ->whereBetween('week_start_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get(['id', 'week_start_date', 'label', 'status'])
            ->groupBy(fn (Schedule $schedule) => $schedule->week_start_date->toDateString());

        $weeks = [];
        for ($offset = -$weeksBefore; $offset <= $weeksAfter; $offset++) {
            $weekStart = $currentWeekStart->copy()->addWeeks($offset);
            $weekStartDate = $weekStart->toDateString();

            $weeks[] = new PlanningCalendarWeekData(
                weekStartDate: $weekStartDate,
                isoWeek: $weekStart->copy()->addDays(3)->isoWeek(),
                schedules: $schedulesByWeek->get($weekStartDate, collect())
                    ->map(fn (Schedule $schedule) => [
                        'id' => $schedule->id,
                        'label' => $schedule->label,
                        'status' => $schedule->status,
                    ])
                    ->all(),
            );
        }

        return $weeks;
    }
}
