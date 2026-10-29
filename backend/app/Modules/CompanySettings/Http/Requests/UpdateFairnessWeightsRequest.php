<?php

namespace App\Modules\CompanySettings\Http\Requests;

use App\Modules\CompanySettings\DTOs\FairnessWeightsData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFairnessWeightsRequest extends FormRequest
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
            'fairness_night_weight' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'fairness_afternoon_weight' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'fairness_day_weight' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'fairness_sunday_weight' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'fairness_saturday_weight' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'fairness_weekday_weight' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'role_rotation_enabled' => ['required', 'boolean'],
        ];
    }

    public function toDto(): FairnessWeightsData
    {
        return new FairnessWeightsData(
            fairnessNightWeight: (float) $this->input('fairness_night_weight'),
            fairnessAfternoonWeight: (float) $this->input('fairness_afternoon_weight'),
            fairnessDayWeight: (float) $this->input('fairness_day_weight'),
            fairnessSundayWeight: (float) $this->input('fairness_sunday_weight'),
            fairnessSaturdayWeight: (float) $this->input('fairness_saturday_weight'),
            fairnessWeekdayWeight: (float) $this->input('fairness_weekday_weight'),
            roleRotationEnabled: $this->boolean('role_rotation_enabled'),
        );
    }
}
