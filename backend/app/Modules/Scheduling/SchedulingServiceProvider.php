<?php

namespace App\Modules\Scheduling;

use App\Modules\Scheduling\Contracts\AlternativeCandidateServiceContract;
use App\Modules\Scheduling\Contracts\ScheduleServiceContract;
use App\Modules\Scheduling\Contracts\ScheduleSuggestionServiceContract;
use App\Modules\Scheduling\Contracts\ShiftAssignmentServiceContract;
use App\Modules\Scheduling\Services\AlternativeCandidateService;
use App\Modules\Scheduling\Services\ScheduleService;
use App\Modules\Scheduling\Services\ScheduleSuggestionService;
use App\Modules\Scheduling\Services\ShiftAssignmentService;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleServiceContract::class, ScheduleService::class);
        $this->app->bind(ShiftAssignmentServiceContract::class, ShiftAssignmentService::class);
        $this->app->bind(ScheduleSuggestionServiceContract::class, ScheduleSuggestionService::class);
        $this->app->bind(AlternativeCandidateServiceContract::class, AlternativeCandidateService::class);
    }
}
