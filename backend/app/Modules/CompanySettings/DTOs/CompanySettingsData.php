<?php

namespace App\Modules\CompanySettings\DTOs;

final readonly class CompanySettingsData
{
    public function __construct(
        public int $annualLeaveMinNoticeDays,
        public int $shiftNoticeMinNoticeHours,
        public ?string $emergencyContactPhone,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'annual_leave_min_notice_days' => $this->annualLeaveMinNoticeDays,
            'shift_notice_min_notice_hours' => $this->shiftNoticeMinNoticeHours,
            'emergency_contact_phone' => $this->emergencyContactPhone,
        ];
    }
}
