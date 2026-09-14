<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\DTOs\ShiftPatternGroupData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftPatternGroupRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'slots' => ['required', 'array'],
            'slots.day.start_time' => ['required', 'date_format:H:i'],
            'slots.day.end_time' => ['required', 'date_format:H:i'],
            'slots.day.crosses_midnight' => ['required', 'boolean'],
            'slots.afternoon.start_time' => ['required', 'date_format:H:i'],
            'slots.afternoon.end_time' => ['required', 'date_format:H:i'],
            'slots.afternoon.crosses_midnight' => ['required', 'boolean'],
            'slots.night.start_time' => ['required', 'date_format:H:i'],
            'slots.night.end_time' => ['required', 'date_format:H:i'],
            'slots.night.crosses_midnight' => ['required', 'boolean'],
        ];
    }

    public function toDto(): ShiftPatternGroupData
    {
        return new ShiftPatternGroupData(
            name: $this->string('name')->toString(),
            slots: $this->input('slots'),
            isActive: $this->boolean('is_active', true),
        );
    }
}
