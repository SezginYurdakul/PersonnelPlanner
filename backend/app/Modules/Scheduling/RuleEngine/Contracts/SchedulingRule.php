<?php

namespace App\Modules\Scheduling\RuleEngine\Contracts;

use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;

interface SchedulingRule
{
    /**
     * Stable identifier matching this rule's entry in config/scheduling_rules.php, so
     * RuleEngine can resolve/enable rules without hard-coding class names (ProjectPlan.md §16.5).
     */
    public function key(): string;

    public function evaluate(EmployeeScheduleContext $context): RuleResult;
}
