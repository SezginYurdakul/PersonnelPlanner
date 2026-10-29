<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Scheduling\DTOs\LeaveConflictData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\ShiftNotices\Models\ShiftNotice;
use Illuminate\Support\Collection;

/**
 * Catches the gap between a schedule being generated and it being approved: nothing today
 * automatically removes or flags a ShiftAssignment when the employee's leave gets approved,
 * or they report sick, *after* the draft/proposed schedule already placed them on that day
 * (user-confirmed finding - ShiftAssignmentService/LeaveRequestService never touch each
 * other). This checker is the one place that reconciles the two at the one moment it
 * matters most: right before a super_admin approves the week and it goes live to the
 * employee. Scoped to approved leave and SICK notices only - a "late" notice means the
 * employee is still coming in, just later, so it's not a conflict with the assignment
 * existing at all.
 */
final class LeaveConflictChecker
{
    /**
     * @return Collection<int, LeaveConflictData>
     */
    public function conflictingAssignments(Schedule $schedule): Collection
    {
        $assignments = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->with(['employee', 'line', 'role'])
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $approvedLeaveByEmployee = LeaveRequest::query()
            ->whereIn('employee_id', $assignments->pluck('employee_id')->unique())
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->get(['employee_id', 'start_date', 'end_date'])
            ->groupBy('employee_id');

        $sickAssignmentIds = ShiftNotice::query()
            ->whereIn('shift_assignment_id', $assignments->pluck('id'))
            ->where('type', ShiftNotice::TYPE_SICK)
            ->pluck('shift_assignment_id')
            ->all();

        return $assignments
            ->map(function (ShiftAssignment $assignment) use ($approvedLeaveByEmployee, $sickAssignmentIds) {
                if (in_array($assignment->id, $sickAssignmentIds, true)) {
                    return new LeaveConflictData($assignment, LeaveConflictData::REASON_SICK_NOTICE);
                }

                $onApprovedLeave = $approvedLeaveByEmployee->get($assignment->employee_id, collect())
                    ->contains(fn (LeaveRequest $leave) => $leave->start_date->lte($assignment->work_date)
                        && $leave->end_date->gte($assignment->work_date));

                if ($onApprovedLeave) {
                    return new LeaveConflictData($assignment, LeaveConflictData::REASON_APPROVED_LEAVE);
                }

                return null;
            })
            ->filter()
            ->values();
    }
}
