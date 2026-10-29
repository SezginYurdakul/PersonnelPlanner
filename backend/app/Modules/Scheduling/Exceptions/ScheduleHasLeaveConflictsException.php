<?php

namespace App\Modules\Scheduling\Exceptions;

use App\Modules\Scheduling\DTOs\LeaveConflictData;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Mirrors ScheduleCannotBeApprovedException's "hard block, not a warning" precedent
 * (§12.3b), for a different reason: at least one ShiftAssignment's employee now has
 * approved leave or a sick notice covering that work_date. The admin resolves this the same
 * way as any other approval block - reassign someone else and retry, or reject the schedule
 * (the rejection note can cite this conflict).
 */
final class ScheduleHasLeaveConflictsException extends RuntimeException
{
    /**
     * @param  Collection<int, LeaveConflictData>  $conflicts
     */
    public function __construct(public readonly Collection $conflicts)
    {
        parent::__construct('This schedule assigns employees who are on approved leave or reported sick, and cannot be approved.');
    }
}
