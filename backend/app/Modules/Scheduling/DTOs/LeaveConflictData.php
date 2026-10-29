<?php

namespace App\Modules\Scheduling\DTOs;

use App\Modules\Scheduling\Models\ShiftAssignment;

/**
 * One ShiftAssignment whose employee has approved leave or a sick ShiftNotice covering its
 * work_date - surfaced by LeaveConflictChecker so a draft/proposed schedule that was
 * generated *before* the leave/illness was recorded can be caught before approval
 * (user-confirmed workflow: the admin manually reassigns someone else, or rejects the
 * schedule citing this conflict).
 */
final readonly class LeaveConflictData
{
    public const REASON_APPROVED_LEAVE = 'approved_leave';

    public const REASON_SICK_NOTICE = 'sick_notice';

    public function __construct(
        public ShiftAssignment $assignment,
        public string $reason,
    ) {}
}
