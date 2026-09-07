<?php

namespace App\Modules\Scheduling\DTOs;

use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Support\Collection;

/**
 * The composite response for POST /schedules/suggest - the schedule plus its unfilled
 * slots in one payload, so the grid is immediately actionable without a second round-trip
 * (ProjectPlan.md §12.5's "under a few seconds" acceptance criterion).
 */
final readonly class SuggestionResultData
{
    /**
     * @param  Collection<int, UnfilledSlotData>  $unfilledSlots
     */
    public function __construct(
        public Schedule $schedule,
        public Collection $unfilledSlots,
    ) {
    }
}
