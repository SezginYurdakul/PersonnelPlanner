<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use Illuminate\Foundation\Http\FormRequest;

class GenerateSuggestionRequest extends FormRequest
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
            'week_start_date' => ['required', 'date'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer', 'exists:lines,id'],
            'slot_types' => ['required', 'array', 'min:1'],
            'slot_types.*' => ['string', 'in:day,afternoon,night'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:scheduling_roles,id'],
            'ranking_mode' => ['required', 'in:fair,cost'],
            'line_slot_exclusions' => ['nullable', 'array'],
            'line_slot_exclusions.*.line_id' => ['required', 'integer', 'in:'.implode(',', $this->input('line_ids', []))],
            'line_slot_exclusions.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'line_slot_exclusions.*.slot_type' => ['required', 'string', 'in:day,afternoon,night'],
            'role_slot_exclusions' => ['nullable', 'array'],
            'role_slot_exclusions.*.role_id' => ['required', 'integer', 'exists:scheduling_roles,id'],
            'role_slot_exclusions.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'role_slot_exclusions.*.slot_type' => ['nullable', 'string', 'in:day,afternoon,night'],
        ];
    }

    public function toDto(): SuggestionRequestData
    {
        return new SuggestionRequestData(
            weekStartDate: $this->string('week_start_date')->toString(),
            lineIds: $this->input('line_ids', []),
            slotTypes: $this->input('slot_types', []),
            roleIds: $this->input('role_ids'),
            rankingMode: $this->string('ranking_mode')->toString(),
            requestedBy: $this->user()->id,
            scheduleId: $this->input('schedule_id'),
            lineSlotExclusions: $this->input('line_slot_exclusions'),
            roleSlotExclusions: $this->input('role_slot_exclusions'),
        );
    }
}
