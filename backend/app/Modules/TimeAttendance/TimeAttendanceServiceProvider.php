<?php

namespace App\Modules\TimeAttendance;

use App\Modules\TimeAttendance\Contracts\TimeClockEntryServiceContract;
use App\Modules\TimeAttendance\Contracts\TimeClockImportServiceContract;
use App\Modules\TimeAttendance\Services\TimeClockEntryService;
use App\Modules\TimeAttendance\Services\TimeClockImportService;
use Illuminate\Support\ServiceProvider;

class TimeAttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TimeClockEntryServiceContract::class, TimeClockEntryService::class);
        $this->app->bind(TimeClockImportServiceContract::class, TimeClockImportService::class);
    }
}
