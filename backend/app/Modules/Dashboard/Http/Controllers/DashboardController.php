<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Contracts\DashboardServiceContract;
use App\Modules\Dashboard\Http\Resources\DashboardSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceContract $dashboard,
    ) {}

    public function summary(): DashboardSummaryResource
    {
        return new DashboardSummaryResource($this->dashboard->summary());
    }

    public function planningCalendar(Request $request): JsonResponse
    {
        $weeksBefore = min(52, max(0, $request->integer('weeks_before', 4)));
        $weeksAfter = min(52, max(0, $request->integer('weeks_after', 4)));

        $weeks = $this->dashboard->planningCalendar($weeksBefore, $weeksAfter);

        return response()->json([
            'data' => array_map(fn ($week) => $week->toArray(), $weeks),
        ]);
    }
}
