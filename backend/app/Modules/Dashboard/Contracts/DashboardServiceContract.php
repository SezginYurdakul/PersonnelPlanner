<?php

namespace App\Modules\Dashboard\Contracts;

use App\Modules\Dashboard\DTOs\DashboardSummaryData;
use App\Modules\Dashboard\DTOs\PlanningCalendarWeekData;

interface DashboardServiceContract
{
    public function summary(): DashboardSummaryData;

    /**
     * One row per calendar week in [weeksBefore, weeksAfter] around the current week,
     * each carrying every schedule that exists for it - powers the dashboard's planning
     * calendar widget (ProjectPlan.md's multi-scenario support means a week can hold more
     * than one draft/proposed plan, so this is a list per week, not a single status).
     *
     * @return array<int, PlanningCalendarWeekData>
     */
    public function planningCalendar(int $weeksBefore, int $weeksAfter): array;
}
