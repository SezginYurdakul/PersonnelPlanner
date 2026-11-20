<?php

namespace App\Jobs;

use App\Modules\Scheduling\Contracts\ScheduleSuggestionServiceContract;
use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\Exceptions\RosterPlannerUnavailableException;
use App\Modules\Scheduling\Exceptions\ScheduleNotRegenerableException;
use App\Modules\Scheduling\Services\ScheduleSuggestionRuns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * One generate run (ProjectPlan.md §28.11). The roster planner can take minutes - longer
 * than Cloudflare keeps a request open (100 s) - so the request only queues this job and the
 * page polls ScheduleSuggestionRuns for the outcome.
 */
class GenerateScheduleSuggestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Never retried behind the admin's back: a failed run is reported and they press
     * Generate again.
     */
    public int $tries = 1;

    /**
     * The planner's own limit (config('planner.timeout'), 260 s) plus building the problem
     * and placing the answer. The queue's retry_after must stay above this.
     */
    public int $timeout = 300;

    public function __construct(
        private readonly string $runId,
        private readonly SuggestionRequestData $request,
    ) {}

    public function handle(ScheduleSuggestionServiceContract $suggestions, ScheduleSuggestionRuns $runs): void
    {
        $runs->markRunning($this->runId);

        try {
            $schedule = $suggestions->generate($this->request);
        } catch (RosterPlannerUnavailableException|ScheduleNotRegenerableException $e) {
            // Nothing was generated or changed (both are thrown before the draft is touched).
            $runs->markFailed($this->runId, $e->getMessage());

            return;
        }

        $runs->markCompleted($this->runId, $schedule->id);
    }

    public function failed(?Throwable $exception): void
    {
        app(ScheduleSuggestionRuns::class)->markFailed($this->runId, 'The schedule could not be generated. Please try again.');
    }
}
