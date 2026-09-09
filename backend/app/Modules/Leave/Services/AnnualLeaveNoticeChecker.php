<?php

namespace App\Modules\Leave\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * ProjectPlan.md §8c: an annual-leave (Vakantie) self-service request must be submitted at
 * least N days before the requested start date, N being an admin-configurable Company
 * Setting. Authoritative, server-side - the frontend pre-checks the same threshold for UX,
 * but this is what actually enforces it.
 */
final class AnnualLeaveNoticeChecker
{
    public function assertSufficientNotice(Carbon $startDate): void
    {
        $minDays = CompanySetting::current()->annual_leave_min_notice_days;

        if (now()->startOfDay()->diffInDays($startDate->copy()->startOfDay(), false) < $minDays) {
            throw ValidationException::withMessages([
                'start_date' => __('leave.insufficient_notice', ['days' => $minDays]),
            ]);
        }
    }
}
