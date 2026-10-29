<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Http\Resources\PendingAcknowledgmentResource;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Services\ScheduleAcknowledgmentService;
use App\Modules\Staff\Support\ResolvesAuthenticatedEmployee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SelfServiceScheduleAcknowledgmentController extends Controller
{
    use ResolvesAuthenticatedEmployee;

    public function __construct(private readonly ScheduleAcknowledgmentService $acknowledgments) {}

    public function index(Request $request)
    {
        $employee = $this->employeeForUser($request->user());

        return PendingAcknowledgmentResource::collection($this->acknowledgments->pendingFor($employee));
    }

    public function store(Request $request, Schedule $schedule): Response
    {
        $employee = $this->employeeForUser($request->user());

        $this->acknowledgments->acknowledge($schedule, $employee);

        return response()->noContent();
    }
}
