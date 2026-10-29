<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateScheduleSuggestion;
use App\Modules\Scheduling\Http\Requests\GenerateSuggestionRequest;
use App\Modules\Scheduling\Http\Resources\ScheduleResource;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Services\ScheduleSuggestionRuns;
use Illuminate\Http\JsonResponse;

class ScheduleSuggestionController extends Controller
{
    public function __construct(private readonly ScheduleSuggestionRuns $runs) {}

    /**
     * Queues the run and answers at once (ProjectPlan.md §28.11); the page polls show().
     */
    public function store(GenerateSuggestionRequest $request): JsonResponse
    {
        $runId = $this->runs->create();

        GenerateScheduleSuggestion::dispatch($runId, $request->toDto());

        return response()->json(['run' => $this->runPayload($runId)], 202);
    }

    public function show(string $run): JsonResponse
    {
        if ($this->runs->find($run) === null) {
            return response()->json(['message' => 'This generate run is unknown or has expired.'], 404);
        }

        return response()->json(['run' => $this->runPayload($run)]);
    }

    /**
     * Once completed it carries the regenerated schedule. ScheduleResource recomputes
     * `unfilled_slots` live (MandatoryCoverageChecker) on every render, so generate()'s own
     * in-memory unfilled list is never returned - it would go stale the moment anything is
     * edited.
     *
     * @return array<string, mixed>
     */
    private function runPayload(string $runId): array
    {
        $run = $this->runs->find($runId);
        $schedule = $run['status'] === ScheduleSuggestionRuns::STATUS_COMPLETED
            ? Schedule::query()->with(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role'])->find($run['schedule_id'])
            : null;

        return [
            'id' => $runId,
            'status' => $run['status'],
            'message' => $run['message'],
            'schedule' => $schedule !== null ? new ScheduleResource($schedule) : null,
        ];
    }
}
