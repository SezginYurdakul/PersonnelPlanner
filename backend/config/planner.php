<?php

/*
 * Technical settings of the roster planner (ProjectPlan.md §28). Its business settings -
 * weekend policy, fairness weights, rotation on/off, the lead cap per shift and the weeks
 * each solve looks ahead - are company settings (planner_*), edited by a super admin.
 */
return [
    /*
     * Base URL of the roster planner service, e.g. http://planner:8000 (set by
     * docker-compose.yml). Empty means generate refuses: it has no other way to plan.
     */
    'url' => env('PLANNER_URL'),

    /*
     * Seconds to wait for a plan. Generate runs on the queue (ProjectPlan.md §28.11): keep
     * this above the stage limits' total below and under GenerateScheduleSuggestion's 300 s
     * timeout.
     */
    'timeout' => (int) env('PLANNER_TIMEOUT', 240),

    /*
     * Approved weeks counted as history, so fairness carries across generate calls.
     */
    'history_weeks' => 8,

    /*
     * The weekly rotation cycle: next week's shift type follows this week's in this order
     * (when the planner_rotation_enabled setting is on).
     */
    'rotation' => ['day', 'afternoon', 'night'],

    /*
     * ISO weekdays the weekend policy applies to.
     */
    'weekend_weekdays' => [6, 7],

    /*
     * Per-stage solver limits for a generate call (leads and operators solved together,
     * 8 weeks), 190 seconds at most - 220 in cost mode, which adds the cost stage. A stage
     * that proves its optimum stops early, so today's 6 leads and 13 operators take about
     * 130: coverage, hours and single types prove within seconds, rotation, fairness and
     * weekends use their full time. single_type gets double the service default because
     * everything after it builds on it - cut short (3 seconds) it stopped at dozens of mixed
     * weeks where none are needed; it proves 0 in about 7. A 45-second budget gave the same
     * coverage, hours and mixed weeks with about 30% more rotation breaks.
     */
    'stage_seconds' => [
        'coverage' => 10,
        'single_type' => 20,
        'mixed_share' => 10,
        'contract' => 10,
        'over_max' => 10,
        'short_share' => 10,
        'rotation' => 30,
        'fairness' => 60,
        'weekend' => 30,
        'cost' => 30,
    ],
];
