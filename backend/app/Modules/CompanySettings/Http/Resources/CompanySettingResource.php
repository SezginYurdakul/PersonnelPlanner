<?php

namespace App\Modules\CompanySettings\Http\Resources;

use App\Modules\CompanySettings\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanySetting */
class CompanySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'annual_leave_min_notice_days' => $this->annual_leave_min_notice_days,
            'shift_notice_min_notice_hours' => $this->shift_notice_min_notice_hours,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'week_start_day' => $this->week_start_day,
            'fairness_night_weight' => $this->fairness_night_weight,
            'fairness_afternoon_weight' => $this->fairness_afternoon_weight,
            'fairness_day_weight' => $this->fairness_day_weight,
            'fairness_sunday_weight' => $this->fairness_sunday_weight,
            'fairness_saturday_weight' => $this->fairness_saturday_weight,
            'fairness_weekday_weight' => $this->fairness_weekday_weight,
            'role_rotation_enabled' => $this->role_rotation_enabled,
            'planner_weekend_policy' => $this->planner_weekend_policy,
            'planner_night_weight' => $this->planner_night_weight,
            'planner_sunday_weight' => $this->planner_sunday_weight,
            'planner_saturday_weight' => $this->planner_saturday_weight,
            'planner_rotation_break_weight' => $this->planner_rotation_break_weight,
            'planner_rotation_enabled' => $this->planner_rotation_enabled,
            'planner_max_leads_per_shift' => $this->planner_max_leads_per_shift,
            'planner_horizon_weeks' => $this->planner_horizon_weeks,
        ];
    }
}
