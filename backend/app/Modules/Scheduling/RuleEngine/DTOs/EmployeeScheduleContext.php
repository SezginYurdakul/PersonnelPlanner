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
     *   other assignments within the candidate slot's calendar week (Mon-Sun), needed by
     *   MaxWeeklyHoursRule to sum hours. Deliberately scoped to the calendar week only - see
     *   $adjacentAssignments for cross-week-boundary checks.
     * @param  Collection<int, ShiftAssignment>  $adjacentAssignments  the employee's
     *   assignments on the calendar day immediately before/after the candidate slot's day,
     *   regardless of which Schedule/week_start_date they belong to - needed by
     *   MinRestBetweenShiftsRule, since a Sunday-evening shift and a Monday-morning shift
     *   live in two different Schedule rows but must still be checked for rest-gap violations.
     */
    public function __construct(
        public Employee $employee,
        public Carbon $candidateStart,
        public Carbon $candidateEnd,
        public Collection $existingAssignmentsThisWeek,
        public float $maxWeeklyHours,
        public Collection $adjacentAssignments,
    ) {
    }
}
