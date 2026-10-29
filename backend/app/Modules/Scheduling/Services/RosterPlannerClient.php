<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Exceptions\RosterPlannerUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the roster planner service (ProjectPlan.md §28.5). Any failure - not
 * configured, unreachable, too slow, or rejecting the problem - is logged and thrown as
 * RosterPlannerUnavailableException with the reason, for generate to report.
 */
final class RosterPlannerClient
{
    public function isEnabled(): bool
    {
        return filled(config('planner.url'));
    }

    /**
     * @param  array<string, mixed>  $problem
     * @return array<string, mixed> the plan
     *
     * @throws RosterPlannerUnavailableException
     */
    public function plan(array $problem): array
    {
        if (! $this->isEnabled()) {
            throw new RosterPlannerUnavailableException('the planner service is not configured (PLANNER_URL)');
        }

        try {
            $response = Http::timeout(config('planner.timeout'))
                ->acceptJson()
                ->post(rtrim(config('planner.url'), '/').'/plan', $problem);
        } catch (ConnectionException $exception) {
            Log::warning('Roster planner unreachable.', ['error' => $exception->getMessage()]);

            throw new RosterPlannerUnavailableException('the planner service could not be reached or did not answer in time');
        }

        $plan = $response->json();

        if (! $response->successful() || ! is_array($plan) || ! in_array($plan['status'] ?? null, ['ok', 'partial'], true)) {
            $message = is_array($plan) ? ($plan['message'] ?? null) : null;
            Log::warning('Roster planner returned no usable plan.', ['http_status' => $response->status(), 'message' => $message]);

            throw new RosterPlannerUnavailableException("the planner service answered with an error ({$response->status()}".($message !== null ? ": {$message}" : '').')');
        }

        return $plan;
    }
}
