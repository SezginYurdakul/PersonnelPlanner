<?php

namespace App\Modules\Scheduling\RuleEngine;

use App\Modules\Scheduling\RuleEngine\Contracts\SchedulingRule;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Runs every enabled rule from config/scheduling_rules.php against a context and collects
 * the results. Adding a new rule requires only a new rule class + a config entry - this
 * class never references a specific rule class by name (ProjectPlan.md §16.5).
 */
final class RuleEngine
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return Collection<int, RuleResult>
     */
    public function run(EmployeeScheduleContext $context): Collection
    {
        $rules = collect(config('scheduling_rules.rules', []));

        return $rules
            ->filter(fn (array $settings): bool => (bool) ($settings['enabled'] ?? false))
            ->keys()
            ->map(function (string $ruleClass) use ($context): RuleResult {
                /** @var SchedulingRule $rule */
                $rule = $this->container->make($ruleClass);

                return $rule->evaluate($context);
            })
            ->values();
    }

    /**
     * @param  Collection<int, RuleResult>  $results
     */
    public function hasViolations(Collection $results): bool
    {
        return $results->contains(fn (RuleResult $result): bool => $result->isViolation());
    }
}
