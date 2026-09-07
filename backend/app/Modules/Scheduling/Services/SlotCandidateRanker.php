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
     * @return Collection<int, CandidateData>
     */
    public function rankPool(
        Collection $pool,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        string $rankingMode,
        Collection $fairScoresByEmployeeId,
    ): Collection {
        $ranked = $pool->map(fn (Employee $employee) => $this->evaluateCandidate(
            $employee,
            qualified: true,
            shiftStart: $shiftStart,
            shiftEnd: $shiftEnd,
            rankingMode: $rankingMode,
            fairScoresByEmployeeId: $fairScoresByEmployeeId,
        ));

        return $this->sortCandidates($ranked, $rankingMode, $fairScoresByEmployeeId);
    }

    /**
     * Builds the full ranked-and-annotated candidate list for a slot across the entire
     * active workforce, including employees outside the "automatic" pool (unqualified or
     * rule-violating), each tagged with why they were excluded (ProjectPlan.md §12.2a) -
     * used for the alternative-candidates lookup and the unfilled-slot advisory list.
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
     */
    private function evaluateCandidate(
        Employee $employee,
        bool $qualified,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        string $rankingMode,
        Collection $fairScoresByEmployeeId,
        ?string $roleName = null,
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

        return new CandidateData(
            employee: $employee,
            score: $score,
            qualified: true,
            ruleCompliant: true,
            exclusionReason: null,
        );
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

        return new EmployeeScheduleContext(
            employee: $employee,
            candidateStart: $shiftStart,
            candidateEnd: $shiftEnd,
            existingAssignmentsThisWeek: $existingAssignments,
            maxWeeklyHours: $maxWeeklyHours,
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

                return $fairA <=> $fairB;
            }

            return $a->score <=> $b->score;
        })->values();

        return $sortedEligible->merge($excluded->values());
    }
}
