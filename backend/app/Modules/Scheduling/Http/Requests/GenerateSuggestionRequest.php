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
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer', 'exists:lines,id'],
            'shift_pattern_ids' => ['required', 'array', 'min:1'],
            'shift_pattern_ids.*' => ['integer', 'exists:shift_patterns,id'],
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
            shiftPatternIds: $this->input('shift_pattern_ids', []),
            roleIds: $this->input('role_ids'),
            rankingMode: $this->string('ranking_mode')->toString(),
            requestedBy: $this->user()->id,
        );
    }
}
