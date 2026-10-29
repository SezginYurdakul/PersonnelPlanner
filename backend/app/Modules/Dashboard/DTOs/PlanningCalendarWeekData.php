<?php

namespace App\Modules\Dashboard\DTOs;

final readonly class PlanningCalendarWeekData
{
    /**
     * @param  array<int, array{id: int, label: ?string, status: string}>  $schedules  every
     *                                                                                 draft/proposed/approved schedule for this week - empty if none exist yet
     *                                                                                 (ProjectPlan.md's multi-scenario support means this can hold more than one).
     */
    public function __construct(
        public string $weekStartDate,
        public int $isoWeek,
        public array $schedules,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'week_start_date' => $this->weekStartDate,
            'iso_week' => $this->isoWeek,
            'schedules' => $this->schedules,
        ];
    }
}
