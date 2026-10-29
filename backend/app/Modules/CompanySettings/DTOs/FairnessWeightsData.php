<?php

namespace App\Modules\CompanySettings\DTOs;

/**
 * Super_admin-only fairness scoring weights: they weight the recent hours that order the
 * alternatives offered for an open slot and spread secondary tasks, and role rotation breaks
 * ties in who gets a secondary task. The roster planner has its own planner_* settings.
 * Kept as a separate DTO/endpoint so route middleware can gate it to super_admin.
 */
final readonly class FairnessWeightsData
{
    public function __construct(
        public float $fairnessNightWeight,
        public float $fairnessAfternoonWeight,
        public float $fairnessDayWeight,
        public float $fairnessSundayWeight,
        public float $fairnessSaturdayWeight,
        public float $fairnessWeekdayWeight,
        public bool $roleRotationEnabled,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fairness_night_weight' => $this->fairnessNightWeight,
            'fairness_afternoon_weight' => $this->fairnessAfternoonWeight,
            'fairness_day_weight' => $this->fairnessDayWeight,
            'fairness_sunday_weight' => $this->fairnessSundayWeight,
            'fairness_saturday_weight' => $this->fairnessSaturdayWeight,
            'fairness_weekday_weight' => $this->fairnessWeekdayWeight,
            'role_rotation_enabled' => $this->roleRotationEnabled,
        ];
    }
}
