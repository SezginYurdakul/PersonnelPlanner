<?php

namespace App\Modules\Scheduling\RuleEngine\Rules;

use App\Modules\Scheduling\RuleEngine\Contracts\SchedulingRule;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;

/**
 * Skeleton for a future Dutch ATW rule (ProjectPlan.md §16.3): at least 11 consecutive hours
 * of rest between shifts (reducible to 8 hours once per week). Registered disabled in
 * config/scheduling_rules.php, so RuleEngine never calls evaluate() in normal operation.
 */
final class MinRestBetweenShiftsRule implements SchedulingRule
{
    public function key(): string
    {
        return 'min_rest_between_shifts';
    }

    public function evaluate(EmployeeScheduleContext $context): RuleResult
    {
        throw new \LogicException('Not implemented — see ProjectPlan.md §16.3');
    }
}
