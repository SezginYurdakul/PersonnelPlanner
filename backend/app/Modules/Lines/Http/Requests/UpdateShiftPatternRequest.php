<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\DTOs\ShiftPatternData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftPatternRequest extends FormRequest
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
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): ShiftPatternData
    {
        $startTime = $this->string('start_time')->toString();
        $endTime = $this->string('end_time')->toString();

        return new ShiftPatternData(
            name: $this->string('name')->toString(),
            startTime: $startTime,
            endTime: $endTime,
            crossesMidnight: $endTime < $startTime,
            isActive: $this->boolean('is_active', true),
        );
    }
}
