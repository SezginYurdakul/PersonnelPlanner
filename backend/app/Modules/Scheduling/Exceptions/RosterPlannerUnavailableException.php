<?php

namespace App\Modules\Scheduling\Exceptions;

use RuntimeException;

/**
 * The roster planner (ProjectPlan.md §28) gave no usable answer - not configured,
 * unreachable, too slow, or rejecting the problem - so no schedule was generated. Generate
 * asks the planner before touching the draft, so nothing has changed when this is thrown.
 */
final class RosterPlannerUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The roster planner is not available, so no schedule was generated: {$reason}. Try again in a minute; if it keeps failing, the planner service needs checking.");
    }
}
