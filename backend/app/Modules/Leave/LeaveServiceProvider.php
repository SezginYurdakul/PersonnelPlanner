<?php

namespace App\Modules\Leave;

use App\Modules\Leave\Contracts\LeaveRequestServiceContract;
use App\Modules\Leave\Contracts\LeaveTypeServiceContract;
use App\Modules\Leave\Services\LeaveRequestService;
use App\Modules\Leave\Services\LeaveTypeService;
use Illuminate\Support\ServiceProvider;

class LeaveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeaveTypeServiceContract::class, LeaveTypeService::class);
        $this->app->bind(LeaveRequestServiceContract::class, LeaveRequestService::class);
    }
}
