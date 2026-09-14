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
        );
    }
}
