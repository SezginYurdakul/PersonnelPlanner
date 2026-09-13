<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use App\Notifications\ScheduleChanged;

/**
 * Single place that dispatches ScheduleChanged (ProjectPlan.md §12.4/§20) - used from both
 * hook points (ScheduleService::approve() and each of ShiftAssignmentService's
 * create/update/move/delete) so the "only notify affected employees, only for an approved
 * schedule" rule lives in one place rather than being duplicated at each call site.
 */
final class NotificationDispatchService
{
    /**
     * Notifies every distinct employee across the schedule's assignments - fired once when
     * a schedule transitions to approved (ProjectPlan.md §20.1).
     */
    public function notifyScheduleApproved(Schedule $schedule): void
    {
        $schedule->loadMissing('assignments.employee');

        $employees = $schedule->assignments
            ->pluck('employee')
            ->filter()
            ->unique('id');

        foreach ($employees as $employee) {
            $this->notifyEmployee($employee, $schedule->week_start_date->toDateString());
        }
    }

    /**
     * Notifies only the one employee whose assignment changed, and only when that
     * assignment belongs to an already-approved schedule (ProjectPlan.md §20.1's "does not
     * fire for draft/proposed edits").
     */
    public function notifyAssignmentChanged(ShiftAssignment $assignment): void
    {
        if (! $assignment->schedule->isApproved()) {
            return;
        }

        $this->notifyEmployee($assignment->employee, $assignment->schedule->week_start_date->toDateString());
    }

    private function notifyEmployee(Employee $employee, string $weekStartDate): void
    {
        $employee->notify(new ScheduleChanged($weekStartDate));
    }
}
