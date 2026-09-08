<?php

namespace App\Modules\Scheduling\DTOs;

use App\Modules\Staff\Models\Employee;

/**
 * One ranked or excluded candidate for a slot. Shared shape between the suggestion
 * engine's internal ranking (ProjectPlan.md §12.2) and the public alternative-candidates
 * endpoint (§12.2a), so exclusion-reason phrasing only lives in one place.
 */
final readonly class CandidateData
{
    public function __construct(
        public Employee $employee,
        public ?float $score,
        public bool $qualified,
        public bool $ruleCompliant,
        public ?string $exclusionReason,
        public bool $matchesHomeShiftPattern = false,
        public bool $wouldBreakIsolatedRestDay = false,
    ) {
    }

    public function isEligible(): bool
    {
        return $this->qualified && $this->ruleCompliant;
    }
}
