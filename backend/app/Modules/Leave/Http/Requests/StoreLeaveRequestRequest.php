<?php

namespace App\Modules\Leave\Http\Requests;

use App\Modules\Leave\DTOs\LeaveRequestData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreLeaveRequestRequest extends FormRequest
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
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ];
    }

    public function toDto(): LeaveRequestData
    {
        return new LeaveRequestData(
            employeeId: $this->integer('employee_id'),
            leaveTypeId: $this->integer('leave_type_id'),
            startDate: Carbon::parse($this->string('start_date')->toString()),
            endDate: Carbon::parse($this->string('end_date')->toString()),
            reason: $this->string('reason')->toString() ?: null,
        );
    }
}
