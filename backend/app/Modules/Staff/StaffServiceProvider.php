<?php

namespace App\Modules\Staff;

use App\Modules\Staff\Contracts\AgencyServiceContract;
use App\Modules\Staff\Contracts\EmployeeServiceContract;
use App\Modules\Staff\Services\AgencyService;
use App\Modules\Staff\Services\EmployeeService;
use Illuminate\Support\ServiceProvider;

class StaffServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AgencyServiceContract::class, AgencyService::class);
        $this->app->bind(EmployeeServiceContract::class, EmployeeService::class);
    }
}
