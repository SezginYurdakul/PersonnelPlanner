<?php

namespace App\Modules\CompanySettings\Http\Requests;

use App\Modules\CompanySettings\DTOs\PlannerSettingsData;
use App\Modules\CompanySettings\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlannerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'planner_weekend_policy' => ['required', 'string', 'in:'.CompanySetting::PLANNER_WEEKEND_FAIR.','.CompanySetting::PLANNER_WEEKEND_SINGLE_STAFFED],
            'planner_night_weight' => ['required', 'integer', 'min:0', 'max:10'],
            'planner_sunday_weight' => ['required', 'integer', 'min:0', 'max:10'],
            'planner_saturday_weight' => ['required', 'integer', 'min:0', 'max:10'],
            'planner_rotation_break_weight' => ['required', 'integer', 'min:0', 'max:10'],
            'planner_rotation_enabled' => ['required', 'boolean'],
            'planner_max_leads_per_shift' => ['required', 'integer', 'min:1', 'max:5'],
            // Each generate solves this many weeks; more lookahead means a larger, slower model.
            'planner_horizon_weeks' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function toDto(): PlannerSettingsData
    {
        return new PlannerSettingsData(
            weekendPolicy: $this->string('planner_weekend_policy')->toString(),
            nightWeight: $this->integer('planner_night_weight'),
            sundayWeight: $this->integer('planner_sunday_weight'),
            saturdayWeight: $this->integer('planner_saturday_weight'),
            rotationBreakWeight: $this->integer('planner_rotation_break_weight'),
            rotationEnabled: $this->boolean('planner_rotation_enabled'),
            maxLeadsPerShift: $this->integer('planner_max_leads_per_shift'),
            horizonWeeks: $this->integer('planner_horizon_weeks'),
        );
    }
}
