<?php

namespace App\Modules\CompanySettings\DTOs;

/**
 * Super_admin-only roster planner settings (ProjectPlan.md §28) - the business knobs of the
 * CP-SAT solver, kept as their own DTO/endpoint like the fairness weights.
 */
final readonly class PlannerSettingsData
{
    public function __construct(
        public string $weekendPolicy,
        public int $nightWeight,
        public int $sundayWeight,
        public int $saturdayWeight,
        public int $rotationBreakWeight,
        public bool $rotationEnabled,
        public int $maxLeadsPerShift,
        public int $horizonWeeks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'planner_weekend_policy' => $this->weekendPolicy,
            'planner_night_weight' => $this->nightWeight,
            'planner_sunday_weight' => $this->sundayWeight,
            'planner_saturday_weight' => $this->saturdayWeight,
            'planner_rotation_break_weight' => $this->rotationBreakWeight,
            'planner_rotation_enabled' => $this->rotationEnabled,
            'planner_max_leads_per_shift' => $this->maxLeadsPerShift,
            'planner_horizon_weeks' => $this->horizonWeeks,
        ];
    }
}
