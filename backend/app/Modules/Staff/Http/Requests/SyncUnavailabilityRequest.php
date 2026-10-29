<?php

namespace App\Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncUnavailabilityRequest extends FormRequest
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
            'unavailable_slots' => ['present', 'array'],
            'unavailable_slots.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'unavailable_slots.*.slot_type' => ['required', 'string', 'in:day,afternoon,night'],
        ];
    }

    /**
     * @return array<int, array{day_of_week: int, slot_type: string}>
     */
    public function unavailableSlots(): array
    {
        return $this->input('unavailable_slots', []);
    }
}
