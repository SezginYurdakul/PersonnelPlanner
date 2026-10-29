<?php

namespace App\Modules\Scheduling\RuleEngine\Rules;

use App\Modules\Scheduling\RuleEngine\Contracts\SchedulingRule;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;
use App\Modules\Scheduling\Services\EffectiveWorkedHoursCalculator;

/**
 * The only enabled rule for the MVP (ProjectPlan.md §16.2): an employee's total assigned
 * hours for the week - existing assignments plus the candidate slot - must not exceed their
 * max_weekly_hours (default 40, overridable per employee via employment_terms).
 *
 * Existing assignments are counted via EffectiveWorkedHoursCalculator, which merges
 * same-day overlapping assignments (a station shift plus one or more secondary tasks held
 * during that same physical shift) into a single interval - otherwise each role held during
 * one shift would count as a separate full shift's hours.
 */
final class MaxWeeklyHoursRule implements SchedulingRule
{
    public function __construct(private readonly EffectiveWorkedHoursCalculator $hoursCalculator) {}

    public function key(): string
    {
        return 'max_weekly_hours';
    }

    public function evaluate(EmployeeScheduleContext $context): RuleResult
    {
        $existingHours = $this->hoursCalculator->totalHours($context->existingAssignmentsThisWeek);

        $candidateHours = $this->hoursCalculator->marginalHoursForCandidate(
            $context->existingAssignmentsThisWeek,
            $context->candidateStart,
            $context->candidateEnd,
        );

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
}
