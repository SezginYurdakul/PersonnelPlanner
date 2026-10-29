<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Contracts\ScheduleServiceContract;
use App\Modules\Scheduling\DTOs\ScheduleData;
use App\Modules\Scheduling\Exceptions\ScheduleCannotBeApprovedException;
use App\Modules\Scheduling\Exceptions\ScheduleHasLeaveConflictsException;
use App\Modules\Scheduling\Http\Requests\RejectScheduleRequest;
use App\Modules\Scheduling\Http\Resources\LeaveConflictResource;
use App\Modules\Scheduling\Http\Resources\ScheduleAcknowledgmentStatusResource;
use App\Modules\Scheduling\Http\Resources\ScheduleResource;
use App\Modules\Scheduling\Http\Resources\UnfilledSlotResource;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Services\ScheduleAcknowledgmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleServiceContract $schedules,
        private readonly ScheduleAcknowledgmentService $acknowledgments,
    ) {}

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
            label: $request->filled('label') ? $request->string('label')->toString() : null,
            note: $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return new ScheduleResource($this->schedules->create($data));
    }

    public function update(Request $request, Schedule $schedule): ScheduleResource
    {
        $schedule = $this->schedules->updateNote(
            $schedule,
            $request->filled('label') ? $request->string('label')->toString() : null,
            $request->filled('note') ? $request->string('note')->toString() : null,
        );

        return new ScheduleResource($schedule);
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
        } catch (ScheduleHasLeaveConflictsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'leave_conflicts' => LeaveConflictResource::collection($e->conflicts),
            ], 422);
        }

        return new ScheduleResource($schedule);
    }

    public function submit(Request $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->submitForReview($schedule, $request->user()));
    }

    public function reject(RejectScheduleRequest $request, Schedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->reject($schedule, $request->user(), $request->string('note')->toString()));
    }

    public function unlock(Request $request, Schedule $schedule): ScheduleResource
    {
        $note = $request->filled('note') ? $request->string('note')->toString() : null;

        return new ScheduleResource($this->schedules->unlock($schedule, $request->user(), $note));
    }

    /**
     * Per-assignee acknowledgment status for this schedule's current version - so an admin
     * can see who has confirmed they saw a correction and who hasn't (user-confirmed
     * requirement). Meaningful at any version, not just corrections - for a schedule still
     * on version 1 this simply shows nobody needed to acknowledge anything yet.
     */
    public function acknowledgments(Schedule $schedule)
    {
        return ScheduleAcknowledgmentStatusResource::collection($this->acknowledgments->statusFor($schedule));
    }
}
