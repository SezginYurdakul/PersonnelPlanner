<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Scheduling\Models\EmployeeFairnessScore;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Owns the cumulative, cross-week fairness score (ProjectPlan's confirmed design): each
 * day's EFFECTIVE hours (station + any secondary tasks held during that same physical
 * shift, merged via EffectiveWorkedHoursCalculator so they aren't double-counted) are
 * weighted by shift-type (day/afternoon/night) x day-type (weekday/Saturday/Sunday),
 * multiplied together - a Sunday night shift is the heaviest combination. Written once
 * per employee per Schedule, only at approve() time, so regenerating a draft/proposed
 * schedule never inflates the score.
 */
final class EmployeeFairnessScoreService
{
    /** How many recent approved weeks feed the suggestion engine's fairness ranking. */
    private const LOOKBACK_WEEKS = 8;

    public function __construct(private readonly EffectiveWorkedHoursCalculator $hoursCalculator) {}

    /**
     * Recomputes and stores every employee's weighted-hours contribution for this
     * (now-approved) schedule - replaces any prior row for this schedule, so re-approving
     * after an unlock+manual-edit cycle reflects the edited assignments, not the original
     * ones.
     */
    public function recordForApprovedSchedule(Schedule $schedule): void
    {
        $companySetting = CompanySetting::current();

        $assignmentsByEmployee = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->with('shiftPattern')
            ->get()
            ->groupBy('employee_id');

        foreach ($assignmentsByEmployee as $employeeId => $assignments) {
            $weightedHours = $this->weightedHoursFor($assignments, $companySetting);

            EmployeeFairnessScore::updateOrCreate(
                ['employee_id' => $employeeId, 'schedule_id' => $schedule->id],
                ['week_start_date' => $schedule->week_start_date, 'weighted_hours' => $weightedHours],
            );
        }
    }

    /**
     * Removes this schedule's fairness rows - called when an approved week is unlocked back
     * to draft, since a week that isn't currently approved shouldn't count toward other
     * weeks' fairness ranking until it's (re-)approved.
     */
    public function removeForUnlockedSchedule(Schedule $schedule): void
    {
        EmployeeFairnessScore::where('schedule_id', $schedule->id)->delete();
    }

    /**
     * The last `LOOKBACK_WEEKS` approved weeks' weighted hours, summed per employee - what
     * the suggestion engine's "fair" ranking mode seeds its running tally with, on top of
     * (added to) whatever this in-progress generation pass assigns.
     *
     * @return Collection<int, float> employee_id => cumulative weighted hours
     */
    public function recentScoresByEmployee(): Collection
    {
        $cutoff = CompanySetting::current()
            ->startOfWeekFor(now())
            ->subWeeks(self::LOOKBACK_WEEKS);

        return EmployeeFairnessScore::query()
            ->where('week_start_date', '>=', $cutoff->toDateString())
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $rows) => (float) $rows->sum('weighted_hours'));
    }

    /**
     * Weighted hours across a (possibly multi-day) set of assignments for ONE employee -
     * shared by AlternativeCandidateService (the on-demand "who else could go here?"
     * lookup) so the weighting formula only lives in one place. Each calendar day's
     * effective hours (station + secondary tasks held during that same shift, merged so
     * they count once) are weighted by that day's shift-type and day-type before summing,
     * since the two multipliers are inherently per-day.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     */
    public function weightedHoursFor(Collection $assignments, CompanySetting $companySetting): float
    {
        $hoursByDay = $this->hoursCalculator->totalHoursByDay($assignments);
        $slotTypeByDay = $this->slotTypeByDay($assignments);

        return $hoursByDay->sum(function (float $hours, string $day) use ($slotTypeByDay, $companySetting) {
            $slotType = $slotTypeByDay->get($day, 'day');
            $isoDayOfWeek = Carbon::parse($day)->dayOfWeekIso;

            return $hours * $companySetting->shiftTypeWeight($slotType) * $companySetting->dayTypeWeight($isoDayOfWeek);
        });
    }

    /**
     * Which slot_type represents each calendar day, for weighting purposes - the day's
     * station assignment's shift_pattern (secondary tasks are usually pattern-less, so a
     * station's pattern is the reliable source; falls back to any assignment that does
     * carry one if there's no station that day, e.g. a shift-lead-only day).
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<string, string> work_date (Y-m-d) => slot_type
     */
    private function slotTypeByDay(Collection $assignments): Collection
    {
        return $assignments
            ->groupBy(fn (ShiftAssignment $a) => $a->work_date->format('Y-m-d'))
            ->map(function (Collection $dayAssignments) {
                $withPattern = $dayAssignments->first(fn (ShiftAssignment $a) => $a->shiftPattern !== null);

                return $withPattern?->shiftPattern?->slot_type ?? 'day';
            });
    }
}
