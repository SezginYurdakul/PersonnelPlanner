<?php

namespace App\Modules\CompanySettings\Http\Requests;

use App\Modules\CompanySettings\DTOs\CompanySettingsData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
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
            'annual_leave_min_notice_days' => ['required', 'integer', 'min:0'],
            'shift_notice_min_notice_hours' => ['required', 'integer', 'min:0'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function toDto(): CompanySettingsData
    {
        return new CompanySettingsData(
            annualLeaveMinNoticeDays: $this->integer('annual_leave_min_notice_days'),
            shiftNoticeMinNoticeHours: $this->integer('shift_notice_min_notice_hours'),
            emergencyContactPhone: $this->string('emergency_contact_phone')->toString() ?: null,
        );
    }
}
