<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Dashboard\Contracts\DashboardServiceContract;
use App\Modules\Dashboard\DTOs\DashboardSummaryData;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Staff\Models\Employee;

final class DashboardService implements DashboardServiceContract
{
    public function summary(): DashboardSummaryData
    {
        $activeEmployees = Employee::query()->where('is_active', true);

        return new DashboardSummaryData(
            activeEmployeeCount: (clone $activeEmployees)->count(),
            vastEmployeeCount: (clone $activeEmployees)->where('employee_type', 'vast')->count(),
            uitzendkrachtEmployeeCount: (clone $activeEmployees)->where('employee_type', 'uitzendkracht')->count(),
            unlinkedAccountCount: (clone $activeEmployees)->whereNull('user_id')->count(),
            pendingLeaveRequestCount: LeaveRequest::query()->where('status', LeaveRequest::STATUS_PENDING)->count(),
        );
    }
}
