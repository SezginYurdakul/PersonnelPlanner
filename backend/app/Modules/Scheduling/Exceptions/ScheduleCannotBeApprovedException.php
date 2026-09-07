<?php

namespace App\Modules\Scheduling\Exceptions;

use App\Modules\Scheduling\DTOs\UnfilledSlotData;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * ProjectPlan.md §12.3b: a requires_coverage station with zero assignments for a shift it
 * is scheduled to run blocks approval outright - the one hard block in this system, unlike
 * every other rule violation (§16.4), which is only ever a warning.
 */
final class ScheduleCannotBeApprovedException extends RuntimeException
{
    /**
     * @param  Collection<int, UnfilledSlotData>  $blockingSlots
     */
    public function __construct(public readonly Collection $blockingSlots)
    {
        parent::__construct('This schedule has unfilled mandatory-coverage stations and cannot be approved.');
    }
}
