<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Scheduling\DTOs\ShiftAssignmentData;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShiftAssignmentRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'line_id' => ['required', 'integer', 'exists:lines,id'],
            'shift_pattern_id' => ['nullable', 'integer', 'exists:shift_patterns,id'],
            'work_date' => ['required', 'date'],
            'role_id' => ['nullable', 'integer', 'exists:scheduling_roles,id'],
            'starts_at' => ['nullable', 'date_format:H:i', 'required_with:ends_at'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_with:starts_at'],
            'notes' => ['nullable', 'string'],
            'confirm_override' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * ProjectPlan.md §17A.8: if role_id refers to a line-specific role, its line_id must
     * match this assignment's own line_id.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roleId = $this->input('role_id');

            if (! $roleId) {
                return;
            }

            $role = SchedulingRole::find($roleId);

            if ($role && $role->line_id !== null && (int) $this->input('line_id') !== $role->line_id) {
                $validator->errors()->add('role_id', 'This role belongs to a different line than the assignment.');
            }
        });
    }

    public function toDto(): ShiftAssignmentData
    {
        return new ShiftAssignmentData(
            employeeId: (int) $this->input('employee_id'),
            lineId: (int) $this->input('line_id'),
            shiftPatternId: $this->input('shift_pattern_id'),
            workDate: $this->string('work_date')->toString(),
            roleId: $this->input('role_id'),
            startsAt: $this->input('starts_at'),
            endsAt: $this->input('ends_at'),
            status: ShiftAssignment::STATUS_PROPOSED,
            source: ShiftAssignment::SOURCE_MANUAL,
            notes: $this->input('notes'),
            confirmOverride: $this->boolean('confirm_override'),
        );
    }
}
