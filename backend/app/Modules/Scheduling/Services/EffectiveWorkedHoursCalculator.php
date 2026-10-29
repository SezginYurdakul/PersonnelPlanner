<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A secondary task (Tiger Pap prep, a fault-fix duty, etc.) is picked up DURING the same
 * shift as an employee's station work or another secondary task - not as separate extra
 * hours. Left uncorrected, MaxWeeklyHoursRule and EmployeeFairnessScoreService would each
 * count a station assignment and its accompanying secondary tasks as independent full
 * shifts (e.g. 4 assignments in one day -> 32 "hours" instead of the 8 actually worked).
 *
 * This is the single place that turns a raw ShiftAssignment collection into the hours an
 * employee actually worked: assignments on the same calendar day whose effective time
 * ranges overlap are merged into one interval before their duration is counted, so
 * multiple roles held during one physical shift contribute that shift's hours exactly once.
 */
final class EffectiveWorkedHoursCalculator
{
    /**
     * Total effective hours across the given assignments, after merging same-day
     * overlapping ones.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     */
    public function totalHours(Collection $assignments): float
    {
        return $this->mergedRangesByDay($assignments)
            ->sum(fn (Collection $ranges) => $ranges->sum(
                fn (array $range) => $range[0]->floatDiffInHours($range[1])
            ));
    }

    /**
     * Same total, but grouped per calendar day - EmployeeFairnessScoreService needs each
     * day's own hours separately, since the shift-type/day-type weighting is applied
     * per-day, not to a pre-summed weekly total.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<string, float> work_date (Y-m-d) => effective hours that day
     */
    public function totalHoursByDay(Collection $assignments): Collection
    {
        return $this->mergedRangesByDay($assignments)
            ->map(fn (Collection $ranges) => $ranges->sum(
                fn (array $range) => $range[0]->floatDiffInHours($range[1])
            ));
    }

    /**
     * The hours a not-yet-created candidate slot [candidateStart, candidateEnd] would ADD
     * on top of the given existing assignments - 0 if it falls entirely inside a range
     * already covered (same physical shift, e.g. a second secondary task alongside a
     * station assignment already counted), otherwise only its non-overlapping portion.
     * This is what MaxWeeklyHoursRule needs: existing-week hours plus whatever marginal
     * hours the candidate itself contributes, never double-counting a shared shift.
     *
     * @param  Collection<int, ShiftAssignment>  $existingAssignments
     */
    public function marginalHoursForCandidate(
        Collection $existingAssignments,
        Carbon $candidateStart,
        Carbon $candidateEnd,
    ): float {
        $day = $candidateStart->format('Y-m-d');
        $sameDayRanges = $this->mergedRangesByDay($existingAssignments)->get($day, collect());

        // Collection::push() mutates in place - computing "without" from the same
        // reference after pushing onto it would silently include the candidate in both
        // totals and always yield a zero difference, so "without" must be summed first.
        $withoutCandidate = $sameDayRanges->sum(fn (array $r) => $r[0]->floatDiffInHours($r[1]));
        $withCandidate = $this->mergeRanges($sameDayRanges->push([$candidateStart, $candidateEnd]));
        $withCandidateTotal = $withCandidate->sum(fn (array $r) => $r[0]->floatDiffInHours($r[1]));

        return max(0.0, $withCandidateTotal - $withoutCandidate);
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<string, Collection<int, array{0: Carbon, 1: Carbon}>> work_date
     *                                                                          (Y-m-d) => merged, non-overlapping [start, end] ranges
     */
    private function mergedRangesByDay(Collection $assignments): Collection
    {
        return $assignments
            ->map(fn (ShiftAssignment $assignment) => $this->absoluteRange($assignment))
            ->filter()
            ->groupBy(fn (array $entry) => $entry['day'])
            ->map(fn (Collection $entries) => $this->mergeRanges(
                $entries->map(fn (array $entry) => $entry['range'])
            ));
    }

    /**
     * @return ?array{day: string, range: array{0: Carbon, 1: Carbon}}
     */
    private function absoluteRange(ShiftAssignment $assignment): ?array
    {
        $start = $assignment->effectiveStart();
        $end = $assignment->effectiveEnd();

        if ($start === null || $end === null) {
            return null;
        }

        $day = $assignment->work_date->format('Y-m-d');
        $startsAt = Carbon::parse($day.' '.$start);
        $endsAt = Carbon::parse($day.' '.$end);

        if ($assignment->effectiveCrossesMidnight()) {
            $endsAt->addDay();
        }

        return ['day' => $day, 'range' => [$startsAt, $endsAt]];
    }

    /**
     * Sweep-line merge of possibly-overlapping [start, end] ranges into the minimal set of
     * non-overlapping ranges that cover the same total time - standard interval-union.
     *
     * @param  Collection<int, array{0: Carbon, 1: Carbon}>  $ranges
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function mergeRanges(Collection $ranges): Collection
    {
        $sorted = $ranges->sortBy(fn (array $range) => $range[0]->timestamp)->values();

        $merged = collect();

        foreach ($sorted as $range) {
            $last = $merged->last();

            if ($last !== null && $range[0]->lte($last[1])) {
                $merged->pop();
                $merged->push([$last[0], $last[1]->gte($range[1]) ? $last[1] : $range[1]]);

                continue;
            }

            $merged->push($range);
        }

        return $merged;
    }
}
