<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Contracts\ScheduleServiceContract;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Exceptions\ScheduleCannotBeApprovedException;
use App\Modules\Scheduling\Http\Resources\ScheduleResource;
use App\Modules\Scheduling\Http\Resources\UnfilledSlotResource;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleServiceContract $schedules)
    {
    }

    public function index()
    {
        return ScheduleResource::collection($this->schedules->list());
    }

    public function show(Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->find($schedule));
    }

    public function store(Request $request): ScheduleResource
    {
        $data = new ScheduleData(
            weekStartDate: $request->string('week_start_date')->toString(),
            createdBy: $request->user()->id,
        );

        return new ScheduleResource($this->schedules->create($data));
    }

    public function destroy(Schedule $schedule): Response
    {
        $this->schedules->delete($schedule);

        return response()->noContent();
    }

    public function approve(Request $request, Schedule $schedule): ScheduleResource|JsonResponse
    {
        try {
            $schedule = $this->schedules->approve($schedule, $request->user());
        } catch (ScheduleCannotBeApprovedException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'blocking_slots' => UnfilledSlotResource::collection($e->blockingSlots),
            ], 422);
        }

        return new ScheduleResource($schedule);
    }
}
