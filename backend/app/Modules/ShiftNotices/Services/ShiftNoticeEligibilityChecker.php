<?php

namespace App\Modules\ShiftNotices\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Carbon;

/**
 * ProjectPlan.md §8d: a sick or late-arrival notice can only be submitted while the
 * referenced shift is at least X hours away, X being an admin-configurable Company
 * Setting. Backend-computed (not derived client-side from raw setting+shift-time) since
 * the same hour-math must exist authoritatively server-side regardless - maintaining it in
 * two places risks drift.
 */
final class ShiftNoticeEligibilityChecker
{
    /**
     * @return array{can_submit: bool, phone_number?: ?string}
     */
    public function checkEligibility(ShiftAssignment $assignment): array
    {
        $minHours = CompanySetting::current()->shift_notice_min_notice_hours;
        $shiftStart = $this->resolveShiftStart($assignment);

        if ($shiftStart === null || now()->diffInHours($shiftStart, false) < $minHours) {
            return [
                'can_submit' => false,
                'phone_number' => CompanySetting::current()->emergency_contact_phone,
            ];
        }

        return ['can_submit' => true];
    }

    private function resolveShiftStart(ShiftAssignment $assignment): ?Carbon
    {
        $start = $assignment->effectiveStart();

        if ($start === null) {
            return null;
        }

        return Carbon::parse($assignment->work_date->format('Y-m-d').' '.$start);
    }
}
