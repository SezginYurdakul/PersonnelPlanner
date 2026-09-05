<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\DTOs\EmployeeData;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'employee_type' => ['required', 'in:vast,uitzendkracht'],
            'agency_id' => [
                'nullable',
                'exists:agencies,id',
                'required_if:employee_type,uitzendkracht',
                'prohibited_if:employee_type,vast',
            ],
            'pay_type' => ['required', 'in:hourly,monthly'],
            'hourly_rate' => ['required_if:pay_type,hourly', 'nullable', 'numeric', 'min:0'],
            'monthly_salary' => ['required_if:pay_type,monthly', 'nullable', 'numeric', 'min:0'],
            'contracted_hours_per_week' => ['required_if:pay_type,monthly', 'nullable', 'numeric', 'min:0', 'max:168'],
            'default_line_id' => ['nullable', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * uitzendkracht is always billed hourly, regardless of what pay_type is submitted
     * (ProjectPlan.md §17A.3).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('employee_type') === 'uitzendkracht') {
            $this->merge(['pay_type' => 'hourly']);
        }
    }

    public function toDto(): EmployeeData
    {
        return new EmployeeData(
            firstName: $this->string('first_name')->toString(),
            lastName: $this->string('last_name')->toString(),
            employeeType: $this->string('employee_type')->toString(),
            payType: $this->string('pay_type')->toString(),
            phone: $this->string('phone')->toString() ?: null,
            email: $this->string('email')->toString() ?: null,
            agencyId: $this->input('agency_id'),
            hourlyRate: $this->has('hourly_rate') ? (float) $this->input('hourly_rate') : null,
            monthlySalary: $this->has('monthly_salary') ? (float) $this->input('monthly_salary') : null,
            contractedHoursPerWeek: $this->has('contracted_hours_per_week') ? (float) $this->input('contracted_hours_per_week') : null,
            defaultLineId: $this->input('default_line_id'),
            isActive: $this->boolean('is_active', true),
        );
    }
}
