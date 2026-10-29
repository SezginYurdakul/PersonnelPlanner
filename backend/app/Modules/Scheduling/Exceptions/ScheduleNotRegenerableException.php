<?php

namespace App\Modules\Scheduling\Exceptions;

use RuntimeException;

/**
 * Generate was asked to refill a draft it may not touch - locked in review, or approved
 * before and now only editable by hand. Thrown before the planner is asked or anything is
 * changed.
 */
final class ScheduleNotRegenerableException extends RuntimeException {}
