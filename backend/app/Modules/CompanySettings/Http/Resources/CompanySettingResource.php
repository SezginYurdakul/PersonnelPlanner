<?php

namespace App\Modules\CompanySettings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\CompanySettings\Models\CompanySetting */
class CompanySettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'annual_leave_min_notice_days' => $this->annual_leave_min_notice_days,
            'shift_notice_min_notice_hours' => $this->shift_notice_min_notice_hours,
            'emergency_contact_phone' => $this->emergency_contact_phone,
        ];
    }
}
