<?php

namespace App\Modules\Leave\Services;

use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * User-confirmed rule: an annual-leave (Vakantie) request can never be CREATED for a date
 * range that overlaps a week whose schedule is already APPROVED and published - by the time
 * a week is live, a new annual-leave request for it represents a process failure elsewhere
 * (annual leave already has its own advance-notice requirement, AnnualLeaveNoticeChecker),
 * not a legitimate same-week scenario. Sick leave and every other leave type are
 * deliberately NOT covered by this check - those can always be requested, and whether to
 * approve one that overlaps a published week is the admin's own judgment call (handled by
 * ScheduleService::approve()'s LeaveConflictChecker block instead, at approval time).
 */
final class AnnualLeaveScheduleConflictChecker
{
    public function assertNoApprovedScheduleConflict(Carbon $startDate, Carbon $endDate): void
    {
        $conflict = Schedule::query()
            ->where('status', Schedule::STATUS_APPROVED)
            ->get(['week_start_date'])
            ->contains(function (Schedule $schedule) use ($startDate, $endDate) {
                $weekEnd = $schedule->week_start_date->copy()->addDays(6);

                return $schedule->week_start_date->lte($endDate) && $weekEnd->gte($startDate);
            });

        if ($conflict) {
            throw ValidationException::withMessages([
                'start_date' => __('leave.conflicts_with_approved_schedule'),
            ]);
        }
    }
}
