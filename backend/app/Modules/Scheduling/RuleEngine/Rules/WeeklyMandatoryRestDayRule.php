<?php

namespace App\Modules\Scheduling\RuleEngine\Rules;

use App\Modules\Scheduling\RuleEngine\Contracts\SchedulingRule;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;

/**
 * Skeleton for a future Dutch ATW rule (ProjectPlan.md §16.3): at least one full rest day
 * (or an equivalent rest block) per week. Registered disabled in config/scheduling_rules.php,
 * so RuleEngine never calls evaluate() in normal operation.
 */
final class WeeklyMandatoryRestDayRule implements SchedulingRule
{
    public function key(): string
    {
        return 'weekly_mandatory_rest_day';
    }

    public function evaluate(EmployeeScheduleContext $context): RuleResult
    {
        throw new \LogicException('Not implemented — see ProjectPlan.md §16.3');
    }
}
