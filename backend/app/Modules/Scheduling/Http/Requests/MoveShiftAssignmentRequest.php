<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\DTOs\MoveAssignmentData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A drag-and-drop move (ProjectPlan.md §12.3a) deliberately excludes employee_id/role_id -
 * a move never changes who is assigned or which role they hold, only where/when.
 */
class MoveShiftAssignmentRequest extends FormRequest
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
            'line_id' => ['required', 'integer', 'exists:lines,id'],
            'shift_pattern_id' => ['nullable', 'integer', 'exists:shift_patterns,id'],
            'work_date' => ['required', 'date'],
            'confirm_override' => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(): MoveAssignmentData
    {
        return new MoveAssignmentData(
            lineId: (int) $this->input('line_id'),
            shiftPatternId: $this->input('shift_pattern_id'),
            workDate: $this->string('work_date')->toString(),
            confirmOverride: $this->boolean('confirm_override'),
        );
    }
}
