<?php

namespace App\Modules\Scheduling\RuleEngine\DTOs;

use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything a SchedulingRule needs to evaluate a candidate slot, without touching the
 * database itself - keeps rules pure, testable functions of their input (ProjectPlan.md §16).
 */
final readonly class EmployeeScheduleContext
{
    /**
     * @param  Collection<int, ShiftAssignment>  $existingAssignmentsThisWeek  the employee's
     *   other assignments in the same schedule, needed by MaxWeeklyHoursRule to sum hours and
     *   by future rest-day/night-shift rules to inspect adjacency.
     */
    public function __construct(
        public Employee $employee,
        public Carbon $candidateStart,
        public Carbon $candidateEnd,
        public Collection $existingAssignmentsThisWeek,
        public float $maxWeeklyHours,
    ) {
    }
}
