<?php

namespace App\Modules\TimeAttendance\Http\Requests;

use App\Modules\TimeAttendance\DTOs\ColumnMappingData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommitImportRequest extends FormRequest
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
            'import_token' => ['required', 'string'],
            'shape' => ['required', 'in:simple,detailed'],
            'column_mapping' => ['required', 'array'],
            'column_mapping.employee_identifier' => ['required', 'string'],
            'column_mapping.work_date' => ['required', 'string'],
            'column_mapping.clock_in' => [Rule::requiredIf($this->input('shape') === 'simple'), 'nullable', 'string'],
            'column_mapping.clock_out' => [Rule::requiredIf($this->input('shape') === 'simple'), 'nullable', 'string'],
            'column_mapping.break_minutes' => ['nullable', 'string'],
            'column_mapping.event_time' => [Rule::requiredIf($this->input('shape') === 'detailed'), 'nullable', 'string'],
            'column_mapping.event_type' => [Rule::requiredIf($this->input('shape') === 'detailed'), 'nullable', 'string'],
        ];
    }

    public function toMappingDto(): ColumnMappingData
    {
        return new ColumnMappingData(
            employeeIdentifier: $this->input('column_mapping.employee_identifier'),
            workDate: $this->input('column_mapping.work_date'),
            clockIn: $this->input('column_mapping.clock_in'),
            clockOut: $this->input('column_mapping.clock_out'),
            breakMinutes: $this->input('column_mapping.break_minutes'),
            eventTime: $this->input('column_mapping.event_time'),
            eventType: $this->input('column_mapping.event_type'),
        );
    }
}
