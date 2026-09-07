<?php

namespace App\Modules\Scheduling\RuleEngine\Rules;

use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\Contracts\SchedulingRule;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;
use Illuminate\Support\Carbon;

/**
 * The only enabled rule for the MVP (ProjectPlan.md §16.2): an employee's total assigned
 * hours for the week - existing assignments plus the candidate slot - must not exceed their
 * max_weekly_hours (default 40, overridable per employee via employment_terms).
 */
final class MaxWeeklyHoursRule implements SchedulingRule
{
    public function key(): string
    {
        return 'max_weekly_hours';
    }

    public function evaluate(EmployeeScheduleContext $context): RuleResult
    {
        $existingHours = $context->existingAssignmentsThisWeek
            ->sum(fn (ShiftAssignment $assignment): float => $this->hoursFor(
                $assignment->effectiveStart(),
                $assignment->effectiveEnd(),
                $assignment->effectiveCrossesMidnight(),
            ));

        $candidateHours = $context->candidateStart->floatDiffInHours($context->candidateEnd);

        $totalHours = $existingHours + $candidateHours;

        if ($totalHours <= $context->maxWeeklyHours) {
            return RuleResult::pass($this->key());
        }

        $overBy = round($totalHours - $context->maxWeeklyHours, 1);

        return RuleResult::violation(
            $this->key(),
            "Would exceed weekly hours by {$overBy}",
        );
    }

    private function hoursFor(?string $start, ?string $end, bool $crossesMidnight): float
    {
        if ($start === null || $end === null) {
            return 0.0;
        }

        $startsAt = Carbon::parse($start);
        $endsAt = Carbon::parse($end);

        if ($crossesMidnight) {
            $endsAt->addDay();
        }

        return $startsAt->floatDiffInHours($endsAt);
    }
}
