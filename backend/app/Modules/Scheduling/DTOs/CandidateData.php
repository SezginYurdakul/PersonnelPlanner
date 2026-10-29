<?php

namespace App\Modules\Scheduling\DTOs;

use App\Modules\Staff\Models\Employee;

/**
 * One ranked or excluded candidate for a slot - the alternatives attached to an open slot
 * and the alternative-candidates endpoint (ProjectPlan.md §12.2a), so exclusion-reason
 * phrasing only lives in one place.
 */
final readonly class CandidateData
{
    public function __construct(
        public Employee $employee,
        public ?float $score,
        public bool $qualified,
        public bool $ruleCompliant,
        public ?string $exclusionReason,
        /**
         * True when the employee marked themself unavailable for this day/slot, or is
         * excluded from this specific role - both are employee-level constraints (distinct
         * from `qualified`, which is about skill) that block automatic generation but can
         * still be manually overridden with admin confirmation.
         */
        public bool $violatesEmployeeConstraint = false,
    ) {}

    public function isEligible(): bool
    {
        return $this->qualified && $this->ruleCompliant && ! $this->violatesEmployeeConstraint;
    }
}
