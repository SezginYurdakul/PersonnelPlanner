<?php

namespace App\Modules\Scheduling\Contracts;

use App\Modules\Scheduling\DTOs\CandidateData;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface AlternativeCandidateServiceContract
{
    /**
     * The on-demand "who else could go here?" lookup (ProjectPlan.md §12.2a last bullet),
     * available for any slot - filled or unfilled.
     *
     * $startsAt/$endsAt (H:i) only matter for a flexible/day-specific-hours role
     * ($shiftPatternId null) - they're the real hours (typed into the assignment form, or an
     * existing assignment's stored hours) to rank candidates against, instead of an arbitrary
     * fallback window.
     *
     * @return Collection<int, CandidateData>
     */
    public function forSlot(
        Schedule $schedule,
        int $lineId,
        ?int $shiftPatternId,
        Carbon $workDate,
        int $roleId,
        string $rankingMode,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): Collection;
}
