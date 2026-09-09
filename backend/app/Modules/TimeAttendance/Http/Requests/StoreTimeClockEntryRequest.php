<?php

namespace App\Modules\TimeAttendance\Http\Requests;

use App\Modules\TimeAttendance\DTOs\TimeClockEntryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreTimeClockEntryRequest extends FormRequest
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
            'work_date' => ['required', 'date'],
            'clock_in' => ['required', 'date'],
            'clock_out' => ['required', 'date', 'after:clock_in'],
            'break_minutes' => ['required', 'integer', 'min:0'],
            'shift_assignment_id' => ['nullable', 'integer', 'exists:shift_assignments,id'],
        ];
    }

    /**
     * ProjectPlan.md §17A.9b: break_minutes must not exceed the total clock-in-to-clock-out
     * span - a cross-field check that can't be expressed as a single-field rule.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clockIn = $this->input('clock_in');
            $clockOut = $this->input('clock_out');
            $breakMinutes = $this->input('break_minutes');

            if (! $clockIn || ! $clockOut || $breakMinutes === null) {
                return;
            }

            $spanMinutes = Carbon::parse($clockIn)->diffInMinutes(Carbon::parse($clockOut));

            if ((int) $breakMinutes > $spanMinutes) {
                $validator->errors()->add('break_minutes', 'Break minutes cannot exceed the clock-in to clock-out span.');
            }
        });
    }

    public function toDto(): TimeClockEntryData
    {
        return new TimeClockEntryData(
            employeeId: $this->integer('employee_id'),
            workDate: $this->string('work_date')->toString(),
            clockIn: $this->string('clock_in')->toString(),
            clockOut: $this->string('clock_out')->toString(),
            breakMinutes: $this->integer('break_minutes'),
            shiftAssignmentId: $this->input('shift_assignment_id'),
        );
    }
}
