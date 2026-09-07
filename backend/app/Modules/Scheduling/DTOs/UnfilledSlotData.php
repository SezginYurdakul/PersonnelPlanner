<?php

namespace App\Modules\Scheduling\DTOs;

use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A day x line x shift x role slot the suggestion engine (or a manual check) could not
 * staff. `blocking` distinguishes a mandatory-coverage gap (§11a.3a, blocks approval per
 * §12.3b) from an advisory one (surfaced, but never blocks).
 */
final readonly class UnfilledSlotData
{
    /**
     * @param  Collection<int, CandidateData>  $alternatives
     */
    public function __construct(
        public Line $line,
        public ?ShiftPattern $shiftPattern,
        public Carbon $workDate,
        public SchedulingRole $role,
        public bool $blocking,
        public Collection $alternatives,
    ) {
    }
}
