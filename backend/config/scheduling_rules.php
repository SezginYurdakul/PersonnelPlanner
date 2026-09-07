<?php

use App\Modules\Scheduling\RuleEngine\Rules\ConsecutiveNightShiftsRule;
use App\Modules\Scheduling\RuleEngine\Rules\MaxWeeklyHoursRule;
use App\Modules\Scheduling\RuleEngine\Rules\MinRestBetweenShiftsRule;
use App\Modules\Scheduling\RuleEngine\Rules\WeeklyMandatoryRestDayRule;

return [
    /*
     * Each entry's class is resolved via the container by RuleEngine (ProjectPlan.md §16.5) -
     * adding a new rule requires only a new class here, no changes to RuleEngine itself.
     */
    'rules' => [
        MaxWeeklyHoursRule::class => ['enabled' => true],
        MinRestBetweenShiftsRule::class => ['enabled' => false],
        ConsecutiveNightShiftsRule::class => ['enabled' => false],
        WeeklyMandatoryRestDayRule::class => ['enabled' => false],
    ],

    'default_max_weekly_hours' => 40,
];
