<?php

use App\Modules\Auth\AuthServiceProvider;
use App\Modules\CompanySettings\CompanySettingsServiceProvider;
use App\Modules\Dashboard\DashboardServiceProvider;
use App\Modules\Leave\LeaveServiceProvider;
use App\Modules\Lines\LinesServiceProvider;
use App\Modules\Scheduling\SchedulingServiceProvider;
use App\Modules\ShiftNotices\ShiftNoticesServiceProvider;
use App\Modules\Staff\StaffServiceProvider;
use App\Modules\TimeAttendance\TimeAttendanceServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Module service providers
    AuthServiceProvider::class,
    LinesServiceProvider::class,
    StaffServiceProvider::class,
    LeaveServiceProvider::class,
    DashboardServiceProvider::class,
    SchedulingServiceProvider::class,
    TimeAttendanceServiceProvider::class,
    CompanySettingsServiceProvider::class,
    ShiftNoticesServiceProvider::class,
];
