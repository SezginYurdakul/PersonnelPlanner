<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Scheduling\Contracts\AlternativeCandidateServiceContract;
use App\Modules\Scheduling\DTOs\CandidateData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The on-demand "who else could go here?" lookup (ProjectPlan.md §12.2a last bullet) -
 * available for any slot, filled or unfilled. Reuses SlotCandidateRanker so the ranking
 * algorithm isn't duplicated against ScheduleSuggestionService.
 */
final class AlternativeCandidateService implements AlternativeCandidateServiceContract
{
    public function __construct(
        private readonly SlotCandidateRanker $ranker,
        private readonly EmployeeFairnessScoreService $fairnessScores,
        private readonly EffectiveWorkedHoursCalculator $hoursCalculator,
    ) {}

    public function forSlot(
        Schedule $schedule,
        int $lineId,
        ?int $shiftPatternId,
        Carbon $workDate,
        int $roleId,
        string $rankingMode,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): Collection {
        $role = SchedulingRole::query()->findOrFail($roleId);
        $shiftPattern = $shiftPatternId !== null ? ShiftPattern::query()->find($shiftPatternId) : null;

        [$shiftStart, $shiftEnd] = $this->shiftTimeRange($workDate, $shiftPattern, $startsAt, $endsAt);

        $assignmentsByEmployeeId = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->with('shiftPattern')
            ->get()
            ->groupBy('employee_id');

        $fairScores = $this->currentWeekFairScores($assignmentsByEmployeeId);

        $ranked = $this->ranker->rankAllForRole($role, $shiftStart, $shiftEnd, $rankingMode, $fairScores, $shiftPattern?->slot_type);

        // Whoever already holds this exact (line, shift, role, day) slot is never a
        // meaningful "who else could go here?" answer - "Replace" shouldn't offer swapping
        // someone for themselves, and "Add as extra" shouldn't be able to double-book the
        // same person onto the same slot a second time.
        $alreadyAssignedEmployeeIds = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('line_id', $lineId)
            ->where('shift_pattern_id', $shiftPatternId)
            ->where('work_date', $workDate->format('Y-m-d'))
            ->where('role_id', $roleId)
            ->pluck('employee_id');

        return $ranked
            ->reject(fn (CandidateData $candidate) => $alreadyAssignedEmployeeIds->contains($candidate->employee->id))
            ->map(fn (CandidateData $candidate) => $candidate->withWeekLoad(
                $this->hoursCalculator->totalHours($assignmentsByEmployeeId->get($candidate->employee->id, collect())),
                $this->contractHours($candidate->employee),
            ))
            ->values();
    }

    /**
     * The weekly hours their current employment term sets, as the week summary shows them.
     *
     * @return array{min: ?float, max: float}|null
     */
    private function contractHours(Employee $employee): ?array
    {
        $term = $employee->currentEmploymentTerm;

        return $term === null ? null : [
            'min' => $term->min_weekly_hours === null ? null : (float) $term->min_weekly_hours,
            'max' => (float) $term->max_weekly_hours,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function shiftTimeRange(Carbon $workDate, ?ShiftPattern $shiftPattern, ?string $startsAt, ?string $endsAt): array
    {
        if ($shiftPattern === null) {
            // A flexible/day-specific-hours role has no pattern to fall back on, so without
            // $startsAt/$endsAt (the hours the admin has actually typed into the assignment
            // form, or an existing assignment's stored hours) this used to always guess
            // midnight-to-08:00. That fake window would collide with a DIFFERENT day's real
            // flexible-hours assignment for the same employee via MinRestBetweenShiftsRule -
            // e.g. a genuine 00:00-16:00 assignment yesterday made every candidate look like
            // an 8h-rest violation today, hiding an otherwise perfectly valid candidate.
            if ($startsAt !== null && $endsAt !== null) {
                $start = Carbon::parse($workDate->format('Y-m-d').' '.$startsAt);
                $end = Carbon::parse($workDate->format('Y-m-d').' '.$endsAt);

                if ($end->lessThanOrEqualTo($start)) {
                    $end->addDay();
                }

                return [$start, $end];
            }

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
     * The last 8 approved weeks' weighted hours per employee (ProjectPlan's confirmed
     * cross-week fairness design), plus this schedule's own current weighted hours-so-far -
     * so an on-demand alternative-candidates lookup ranks fairly against recent history AND
     * what has actually been assigned so far this week, not just a single in-progress
     * generate() pass.
     *
     * @param  Collection<int, Collection<int, ShiftAssignment>>  $assignmentsByEmployeeId  this schedule's
     * @return Collection<int, float>
     */
    private function currentWeekFairScores(Collection $assignmentsByEmployeeId): Collection
    {
        $companySetting = CompanySetting::current();
        $recentScores = $this->fairnessScores->recentScoresByEmployee();

        $thisWeekScores = $assignmentsByEmployeeId
            ->map(fn (Collection $assignments) => $this->fairnessScores->weightedHoursFor($assignments, $companySetting));

        return $recentScores->keys()
            ->merge($thisWeekScores->keys())
            ->unique()
            ->mapWithKeys(fn ($employeeId) => [
                $employeeId => (float) $recentScores->get($employeeId, 0.0) + (float) $thisWeekScores->get($employeeId, 0.0),
            ]);
    }
}
