<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Lines\Contracts\PayRateSurchargeRuleServiceContract;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Scheduling\DTOs\CandidateData;
use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\RuleEngine;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Internal collaborator shared by ScheduleSuggestionService (needs winners during
 * generation) and AlternativeCandidateService (needs the full ranked+annotated list on
 * demand), so the ranking algorithm and exclusion-reason phrasing only live in one place
 * (ProjectPlan.md §12.2, §12.2a). Not exposed via a Contract - an internal detail of the
 * Scheduling module's services.
 */
final class SlotCandidateRanker
{
    /** Average weeks per month, used only to rank monthly-salary employees by an
     *  effective hourly cost for `cost` mode (ProjectPlan.md §11.2 - "for ranking
     *  purposes only, not a payroll calculation"). */
    private const WEEKS_PER_MONTH = 52 / 12;

    public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly PayRateSurchargeRuleServiceContract $payRateSurchargeRuleService,
    ) {
    }

    /**
     * Ranks a pre-built candidate pool for a station slot (used internally during
     * suggestion generation, where the pool is already qualification-filtered per
     * ProjectPlan.md §12.2 step 2).
     *
     * @param  Collection<int, Employee>  $pool
     * @param  Collection<int, float>  $fairScoresByEmployeeId  running hours-so-far tally,
     *   keyed by employee id, maintained by the caller across a whole generation pass.
     * @param  Collection<int, int>  $homeShiftPatternsByEmployeeId  each employee's first-
     *   assigned shift_pattern_id this generation run (soft shift-consistency preference).
     * @param  Collection<int, Collection<int, Carbon>>  $restDaysByEmployeeId  each
     *   employee's rest days so far this generation run (soft consecutive-rest-day
     *   preference).
     * @return Collection<int, CandidateData>
     */
    public function rankPool(
        Collection $pool,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        string $rankingMode,
        Collection $fairScoresByEmployeeId,
        int $shiftPatternId,
        Collection $homeShiftPatternsByEmployeeId,
        Collection $restDaysByEmployeeId,
    ): Collection {
        $ranked = $pool->map(fn (Employee $employee) => $this->evaluateCandidate(
            $employee,
            qualified: true,
            shiftStart: $shiftStart,
            shiftEnd: $shiftEnd,
            rankingMode: $rankingMode,
            fairScoresByEmployeeId: $fairScoresByEmployeeId,
            shiftPatternId: $shiftPatternId,
            homeShiftPatternsByEmployeeId: $homeShiftPatternsByEmployeeId,
            restDaysByEmployeeId: $restDaysByEmployeeId,
        ));

        return $this->sortCandidates($ranked, $rankingMode, $fairScoresByEmployeeId);
    }

    /**
     * Builds the full ranked-and-annotated candidate list for a slot across the entire
     * active workforce, including employees outside the "automatic" pool (unqualified or
     * rule-violating), each tagged with why they were excluded (ProjectPlan.md §12.2a) -
     * used for the alternative-candidates lookup and the unfilled-slot advisory list.
     *
     * Deliberately does not accept the shift-consistency/rest-day preference inputs that
     * `rankPool()` does - this is a manual-assignment-support tool (§12.2a: picking from it
     * is always a manual, deliberate action), not the automatic generation path those soft
     * preferences are scoped to.
     *
     * @param  Collection<int, float>  $fairScoresByEmployeeId
     * @return Collection<int, CandidateData>
     */
    public function rankAllForRole(
        SchedulingRole $role,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        string $rankingMode,
        Collection $fairScoresByEmployeeId,
    ): Collection {
        $qualifiedEmployeeIds = $role->qualifiedEmployees()->pluck('employees.id')->all();

        $ranked = Employee::query()->where('is_active', true)->get()
            ->map(fn (Employee $employee) => $this->evaluateCandidate(
                $employee,
                qualified: in_array($employee->id, $qualifiedEmployeeIds, true),
                shiftStart: $shiftStart,
                shiftEnd: $shiftEnd,
                rankingMode: $rankingMode,
                fairScoresByEmployeeId: $fairScoresByEmployeeId,
                roleName: $role->name,
            ));

        return $this->sortCandidates($ranked, $rankingMode, $fairScoresByEmployeeId);
    }

    /**
     * Monthly salary -> effective hourly cost, for ranking purposes only (ProjectPlan.md
     * §11.2). Agency/hourly employees use their stored hourly_rate directly.
     */
    public function resolveBaseHourlyRate(Employee $employee): float
    {
        if ($employee->pay_type === 'monthly') {
            $monthlyHours = (float) $employee->contracted_hours_per_week * self::WEEKS_PER_MONTH;

            return $monthlyHours > 0 ? (float) $employee->monthly_salary / $monthlyHours : 0.0;
        }

        return (float) $employee->hourly_rate;
    }

    /**
     * @param  Collection<int, float>  $fairScoresByEmployeeId
     * @param  ?Collection<int, int>  $homeShiftPatternsByEmployeeId
     * @param  ?Collection<int, Collection<int, Carbon>>  $restDaysByEmployeeId
     */
    private function evaluateCandidate(
        Employee $employee,
        bool $qualified,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        string $rankingMode,
        Collection $fairScoresByEmployeeId,
        ?string $roleName = null,
        ?int $shiftPatternId = null,
        ?Collection $homeShiftPatternsByEmployeeId = null,
        ?Collection $restDaysByEmployeeId = null,
    ): CandidateData {
        if (! $qualified) {
            return new CandidateData(
                employee: $employee,
                score: null,
                qualified: false,
                ruleCompliant: true,
                exclusionReason: $roleName !== null ? "Not qualified for {$roleName}" : 'Not qualified for this role',
            );
        }

        $ruleResults = $this->ruleEngine->run($this->contextFor($employee, $shiftStart, $shiftEnd));
        $violation = $ruleResults->first(fn ($result) => $result->isViolation());

        if ($violation !== null) {
            return new CandidateData(
                employee: $employee,
                score: null,
                qualified: true,
                ruleCompliant: false,
                exclusionReason: $violation->message,
            );
        }

        $fairScore = (float) $fairScoresByEmployeeId->get($employee->id, 0.0);
        $score = $rankingMode === SuggestionRequestData::RANKING_COST
            ? $this->payRateSurchargeRuleService->computeShiftCost(
                $this->resolveBaseHourlyRate($employee),
                $shiftStart,
                $shiftEnd,
            )
            : $fairScore;

        // No home pattern recorded yet means this would be the employee's first assignment
        // this run - treated as neutral (not a mismatch), never penalized.
        $homePattern = $homeShiftPatternsByEmployeeId?->get($employee->id);
        $matchesHomeShiftPattern = $homePattern === null || $homePattern === $shiftPatternId;

        $wouldBreakIsolatedRestDay = $this->wouldBreakIsolatedRestDay(
            $employee,
            $shiftStart,
            $restDaysByEmployeeId,
        );

        return new CandidateData(
            employee: $employee,
            score: $score,
            qualified: true,
            ruleCompliant: true,
            exclusionReason: null,
            matchesHomeShiftPattern: $matchesHomeShiftPattern,
            wouldBreakIsolatedRestDay: $wouldBreakIsolatedRestDay,
        );
    }

    /**
     * ProjectPlan.md-confirmed best-effort heuristic (§12.2, consecutive-rest-days
     * preference): flags a candidate whose immediately preceding calendar day was their
     * only rest day so far this run - assigning them today would turn a day that could
     * still become part of a consecutive pair into an isolated single day off. This only
     * ever looks backward at already-decided days (no lookahead), consistent with this
     * codebase's greedy, single-pass suggestion generator (ProjectPlan.md §5.2/§9.3).
     *
     * @param  ?Collection<int, Collection<int, Carbon>>  $restDaysByEmployeeId
     */
    private function wouldBreakIsolatedRestDay(
        Employee $employee,
        Carbon $candidateStart,
        ?Collection $restDaysByEmployeeId,
    ): bool {
        if ($restDaysByEmployeeId === null) {
            return false;
        }

        /** @var ?Collection<int, Carbon> $restDays */
        $restDays = $restDaysByEmployeeId->get($employee->id);

        if ($restDays === null || $restDays->isEmpty()) {
            return false;
        }

        $previousDay = $candidateStart->copy()->startOfDay()->subDay();
        $hadRestYesterday = $restDays->contains(fn (Carbon $day) => $day->isSameDay($previousDay));

        if (! $hadRestYesterday) {
            return false;
        }

        $dayBeforeThat = $previousDay->copy()->subDay();
        $alreadyHadConsecutivePair = $restDays->contains(fn (Carbon $day) => $day->isSameDay($dayBeforeThat));

        // Yesterday was a rest day; if the day before that was ALSO a rest day, the
        // consecutive pair already happened and today's assignment doesn't break anything.
        return ! $alreadyHadConsecutivePair;
    }

    private function contextFor(Employee $employee, Carbon $shiftStart, Carbon $shiftEnd): EmployeeScheduleContext
    {
        $maxWeeklyHours = (float) ($employee->currentEmploymentTerm?->max_weekly_hours
            ?? config('scheduling_rules.default_max_weekly_hours'));

        $existingAssignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$shiftStart->copy()->startOfWeek(), $shiftStart->copy()->endOfWeek()])
            ->with('shiftPattern')
            ->get();

        // Deliberately independent of schedule_id/week_start_date - MinRestBetweenShiftsRule
        // must catch a rest-gap violation even when the two shifts fall in different weeks'
        // Schedule rows (e.g. Sunday-evening in last week's schedule vs. Monday-morning in
        // this week's).
        $adjacentAssignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                $shiftStart->copy()->subDay()->startOfDay(),
                $shiftEnd->copy()->addDay()->endOfDay(),
            ])
            ->with('shiftPattern')
            ->get();

        return new EmployeeScheduleContext(
            employee: $employee,
            candidateStart: $shiftStart,
            candidateEnd: $shiftEnd,
            existingAssignmentsThisWeek: $existingAssignments,
            maxWeeklyHours: $maxWeeklyHours,
            adjacentAssignments: $adjacentAssignments,
        );
    }

    /**
     * @param  Collection<int, CandidateData>  $candidates
     * @param  Collection<int, float>  $fairScoresByEmployeeId
     * @return Collection<int, CandidateData>
     */
    private function sortCandidates(Collection $candidates, string $rankingMode, Collection $fairScoresByEmployeeId): Collection
    {
        [$eligible, $excluded] = $candidates->partition(fn (CandidateData $c) => $c->isEligible());

        $sortedEligible = $eligible->sort(function (CandidateData $a, CandidateData $b) use ($rankingMode, $fairScoresByEmployeeId) {
            if ($rankingMode === SuggestionRequestData::RANKING_COST) {
                $costCompare = $a->score <=> $b->score;
                if ($costCompare !== 0) {
                    return $costCompare;
                }

                $fairA = (float) $fairScoresByEmployeeId->get($a->employee->id, 0.0);
                $fairB = (float) $fairScoresByEmployeeId->get($b->employee->id, 0.0);

                $fairCompare = $fairA <=> $fairB;
                if ($fairCompare !== 0) {
                    return $fairCompare;
                }
            } else {
                $scoreCompare = $a->score <=> $b->score;
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }
            }

            // Tiebreak 1 (soft shift-consistency preference, ProjectPlan.md-confirmed):
            // prefer whoever matches their "home" shift pattern for this run. Never
            // overrides fairness/cost - only decides among otherwise-tied candidates.
            $homeMatchCompare = ($b->matchesHomeShiftPattern <=> $a->matchesHomeShiftPattern);
            if ($homeMatchCompare !== 0) {
                return $homeMatchCompare;
            }

            // Tiebreak 2 (soft consecutive-rest-day preference): prefer whoever assigning
            // today would NOT turn into an isolated single day off. Weakest tier - only
            // fires among candidates already tied on everything else above.
            return $a->wouldBreakIsolatedRestDay <=> $b->wouldBreakIsolatedRestDay;
        })->values();

        return $sortedEligible->merge($excluded->values());
    }
}
