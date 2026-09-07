<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Scheduling\Contracts\AlternativeCandidateServiceContract;
use App\Modules\Scheduling\DTOs\CandidateData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The on-demand "who else could go here?" lookup (ProjectPlan.md §12.2a last bullet) -
 * available for any slot, filled or unfilled. Reuses SlotCandidateRanker so the ranking
 * algorithm isn't duplicated against ScheduleSuggestionService.
 */
final class AlternativeCandidateService implements AlternativeCandidateServiceContract
{
    public function __construct(private readonly SlotCandidateRanker $ranker)
    {
    }

    public function forSlot(
        Schedule $schedule,
        int $lineId,
        ?int $shiftPatternId,
        Carbon $workDate,
        int $roleId,
        string $rankingMode,
    ): Collection {
        $role = SchedulingRole::query()->findOrFail($roleId);
        $shiftPattern = $shiftPatternId !== null ? ShiftPattern::query()->find($shiftPatternId) : null;

        [$shiftStart, $shiftEnd] = $this->shiftTimeRange($workDate, $shiftPattern);

        $fairScores = $this->currentWeekFairScores($schedule);

        return $this->ranker->rankAllForRole($role, $shiftStart, $shiftEnd, $rankingMode, $fairScores);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function shiftTimeRange(Carbon $workDate, ?ShiftPattern $shiftPattern): array
    {
        if ($shiftPattern === null) {
            return [$workDate->copy(), $workDate->copy()->addHours(8)];
        }

        $start = Carbon::parse($workDate->format('Y-m-d').' '.$shiftPattern->start_time);
        $end = Carbon::parse($workDate->format('Y-m-d').' '.$shiftPattern->end_time);

        if ($shiftPattern->crosses_midnight) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * Reconstructs the current per-employee hours-so-far tally for this schedule's week,
     * so an on-demand alternative-candidates lookup ranks fairly against what has actually
     * been assigned so far - not just what a single in-progress generate() pass tracked.
     *
     * @return Collection<int, float>
     */
    private function currentWeekFairScores(Schedule $schedule): Collection
    {
        return ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->with('shiftPattern')
            ->get()
            ->groupBy('employee_id')
            ->map(function (Collection $assignments) {
                return $assignments->sum(function (ShiftAssignment $assignment) {
                    $start = $assignment->effectiveStart();
                    $end = $assignment->effectiveEnd();

                    if ($start === null || $end === null) {
                        return 0.0;
                    }

                    $startsAt = Carbon::parse($start);
                    $endsAt = Carbon::parse($end);

                    if ($assignment->effectiveCrossesMidnight()) {
                        $endsAt->addDay();
                    }

                    return $startsAt->floatDiffInHours($endsAt);
                });
            });
    }
}
