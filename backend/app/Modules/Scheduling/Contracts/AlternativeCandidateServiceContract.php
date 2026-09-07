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
     * @return Collection<int, CandidateData>
     */
    public function forSlot(
        Schedule $schedule,
        int $lineId,
        ?int $shiftPatternId,
        Carbon $workDate,
        int $roleId,
        string $rankingMode,
    ): Collection;
}
