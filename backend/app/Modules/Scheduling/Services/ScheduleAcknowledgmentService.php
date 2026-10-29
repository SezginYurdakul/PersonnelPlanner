<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ScheduleAcknowledgment;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Owns the "did this employee see this correction" tracking (user-confirmed requirement):
 * a schedule's acknowledgment is tied to its current `version` (Schedule.version, bumped
 * only by ScheduleService::approve() when correcting an already-published week), so an old
 * acknowledgment from a prior version never silently counts for a newer one.
 */
final class ScheduleAcknowledgmentService
{
    /**
     * Idempotent - acknowledging the same version twice is a no-op, not an error (the
     * table's unique constraint on schedule_id+employee_id+version is what makes this safe
     * to call from a UI action without a prior existence check).
     */
    public function acknowledge(Schedule $schedule, Employee $employee): void
    {
        ScheduleAcknowledgment::firstOrCreate(
            ['schedule_id' => $schedule->id, 'employee_id' => $employee->id, 'version' => $schedule->version],
            ['acknowledged_at' => now()],
        );
    }

    /**
     * Every approved, corrected (version > 1) schedule this employee is assigned to, whose
     * CURRENT version they have not yet acknowledged - what the employee-facing dashboard
     * banner is built from. A schedule still on its first version never appears here (its
     * first approval used the plain ScheduleChanged notice, no acknowledgment expected).
     *
     * @return Collection<int, Schedule>
     */
    public function pendingFor(Employee $employee): Collection
    {
        $schedules = Schedule::query()
            ->where('status', Schedule::STATUS_APPROVED)
            ->where('version', '>', 1)
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
            ->get();

        if ($schedules->isEmpty()) {
            return $schedules;
        }

        $acknowledgedVersionsBySchedule = ScheduleAcknowledgment::query()
            ->where('employee_id', $employee->id)
            ->whereIn('schedule_id', $schedules->pluck('id'))
            ->pluck('version', 'schedule_id');

        return $schedules
            ->filter(fn (Schedule $schedule) => (int) $acknowledgedVersionsBySchedule->get($schedule->id, 0) < $schedule->version)
            ->values();
    }

    /**
     * Per-assignee acknowledgment status for THIS schedule's current version - what the
     * admin-facing "who has seen this" view is built from. Same employee set as
     * NotificationDispatchService::notifyScheduleCorrected() (every distinct employee
     * across the schedule's assignments), so the list an admin sees matches exactly who was
     * notified.
     *
     * @return Collection<int, array{employee: Employee, acknowledged: bool, acknowledged_at: ?Carbon}>
     */
    public function statusFor(Schedule $schedule): Collection
    {
        $schedule->loadMissing('assignments.employee');

        $employees = $schedule->assignments
            ->pluck('employee')
            ->filter()
            ->unique('id');

        $acknowledgments = ScheduleAcknowledgment::query()
            ->where('schedule_id', $schedule->id)
            ->where('version', $schedule->version)
            ->get()
            ->keyBy('employee_id');

        return $employees
            ->map(function (Employee $employee) use ($acknowledgments) {
                $acknowledgment = $acknowledgments->get($employee->id);

                return [
                    'employee' => $employee,
                    'acknowledged' => $acknowledgment !== null,
                    'acknowledged_at' => $acknowledgment?->acknowledged_at,
                ];
            })
            ->values();
    }
}
