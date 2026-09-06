<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\DTOs\PayRateSurchargeRuleData;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePayRateSurchargeRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'surcharge_percentage' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): PayRateSurchargeRuleData
    {
        $startTime = $this->string('start_time')->toString();
        $endTime = $this->string('end_time')->toString();

        return new PayRateSurchargeRuleData(
            name: $this->string('name')->toString(),
            daysOfWeek: $this->input('days_of_week'),
            startTime: $startTime,
            endTime: $endTime,
            crossesMidnight: $endTime < $startTime,
            surchargePercentage: (float) $this->input('surcharge_percentage'),
            isActive: $this->boolean('is_active', true),
        );
    }
}
