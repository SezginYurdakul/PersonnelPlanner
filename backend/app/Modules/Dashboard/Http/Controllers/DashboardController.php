<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Contracts\DashboardServiceContract;
use App\Modules\Dashboard\Http\Resources\DashboardSummaryResource;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceContract $dashboard,
    ) {}

    public function summary(): DashboardSummaryResource
    {
        return new DashboardSummaryResource($this->dashboard->summary());
    }
}
