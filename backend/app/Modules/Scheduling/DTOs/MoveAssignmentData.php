<?php

namespace App\Modules\Scheduling\DTOs;

/**
 * A drag-and-drop move (ProjectPlan.md §12.3a) only ever changes line/shift-pattern/work_date
 * for an existing assignment - never who is assigned or which role they hold.
 */
final readonly class MoveAssignmentData
{
    public function __construct(
        public int $lineId,
        public ?int $shiftPatternId,
        public string $workDate,
        public bool $confirmOverride = false,
    ) {
    }
}
