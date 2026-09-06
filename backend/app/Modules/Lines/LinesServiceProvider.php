<?php

namespace App\Modules\Lines;

use App\Modules\Lines\Contracts\LineServiceContract;
use App\Modules\Lines\Contracts\PayRateSurchargeRuleServiceContract;
use App\Modules\Lines\Contracts\SchedulingRoleServiceContract;
use App\Modules\Lines\Contracts\ShiftPatternServiceContract;
use App\Modules\Lines\Services\LineService;
use App\Modules\Lines\Services\PayRateSurchargeRuleService;
use App\Modules\Lines\Services\SchedulingRoleService;
use App\Modules\Lines\Services\ShiftPatternService;
use Illuminate\Support\ServiceProvider;

class LinesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LineServiceContract::class, LineService::class);
        $this->app->bind(SchedulingRoleServiceContract::class, SchedulingRoleService::class);
        $this->app->bind(ShiftPatternServiceContract::class, ShiftPatternService::class);
        $this->app->bind(PayRateSurchargeRuleServiceContract::class, PayRateSurchargeRuleService::class);
    }
}
