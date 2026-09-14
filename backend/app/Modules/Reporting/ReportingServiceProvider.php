<?php

namespace App\Modules\Reporting;

use App\Modules\Reporting\Contracts\ReportingServiceContract;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportingServiceContract::class, ReportingService::class);
    }
}
