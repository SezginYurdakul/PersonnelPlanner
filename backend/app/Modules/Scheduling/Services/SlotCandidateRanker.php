<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
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
 * Ranks and annotates who could take a slot by hand (ProjectPlan.md §12.2a): the
 * alternatives generate attaches to an open slot, and the alternative-candidates lookup.
 * Shared so the ranking and exclusion-reason phrasing only live in one place. Not exposed
 * via a Contract - an internal detail of the Scheduling module's services.
 */
final class SlotCandidateRanker
{
    public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly PayRateSurchargeRuleServiceContract $payRateSurchargeRuleService,
    ) {}

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
        ?string $slotType = null,
    ): Collection {
        $qualifiedEmployeeIds = $role->qualifiedEmployees()->pluck('employees.id')->all();

        $ranked = Employee::query()->where('is_active', true)->with(['unavailabilities', 'roleExclusions'])->get()
            ->map(fn (Employee $employee) => $this->evaluateCandidate(
                $employee,
                qualified: in_array($employee->id, $qualifiedEmployeeIds, true),
                shiftStart: $shiftStart,
                shiftEnd: $shiftEnd,
                rankingMode: $rankingMode,
                fairScoresByEmployeeId: $fairScoresByEmployeeId,
                roleName: $role->name,
                schedulingRoleId: $role->id,
                slotType: $slotType,
            ));

        return $this->sortCandidates($ranked, $rankingMode, $fairScoresByEmployeeId);
    }

    /**
     * Monthly salary -> effective hourly cost, for ranking purposes only (ProjectPlan.md
     * §11.2). Agency/hourly employees use their stored hourly_rate directly. Delegates to
     * Employee::effectiveHourlyRate() - the same conversion the reporting module's cost
     * breakdown uses (§14.2), kept in one place.
     */
    public function resolveBaseHourlyRate(Employee $employee): float
    {
        return $employee->effectiveHourlyRate();
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
        ?int $schedulingRoleId = null,
        ?string $slotType = null,
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

        if ($schedulingRoleId !== null && $employee->isExcludedFromRole($schedulingRoleId)) {
            return new CandidateData(
                employee: $employee,
                score: null,
                qualified: true,
                ruleCompliant: true,
                exclusionReason: $roleName !== null ? "Excluded from {$roleName}" : 'Excluded from this role',
                violatesEmployeeConstraint: true,
            );
        }

        if ($slotType !== null && $employee->isUnavailable($shiftStart->dayOfWeekIso, $slotType)) {
            return new CandidateData(
                employee: $employee,
                score: null,
                qualified: true,
                ruleCompliant: true,
                exclusionReason: 'Marked unavailable for this day/shift',
                violatesEmployeeConstraint: true,
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

        $score = $rankingMode === SuggestionRequestData::RANKING_COST
            ? $this->payRateSurchargeRuleService->computeShiftCost($this->resolveBaseHourlyRate($employee), $shiftStart, $shiftEnd)
            : (float) $fairScoresByEmployeeId->get($employee->id, 0.0);

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

        // The app's own week boundary (CompanySetting.week_start_day) drives this, NOT
        // Carbon's hardcoded Monday-start default - otherwise a Sunday-starting schedule
        // week would get split into two disjoint Carbon "weeks" (e.g. Sun 20th alone, then
        // Mon 21st-Sun 27th as a separate week), silently resetting the weekly-hours tally
        // to zero on day 2 and letting MaxWeeklyHoursRule never actually trigger. The end
        // must come from endOfWeekFor() as well: endOfWeek(weekStartsOnCarbonDay()) ran a
        // day into the next week, so a lead already at 40 hours whose next week's draft
        // starts with a Sunday shift was reported as 8 hours over for a shift they already
        // work.
        $companySetting = CompanySetting::current();
        $existingAssignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [
                $companySetting->startOfWeekFor($shiftStart),
                $companySetting->endOfWeekFor($shiftStart),
            ])
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
     * Eligible candidates first, by cost (cost mode, fairness breaking ties) or by recent
     * weighted hours (fair mode), then employee_id so the same data always ranks the same
     * way (ProjectPlan.md §12.6 item 3); the excluded ones after, in their incoming order.
     *
     * @param  Collection<int, CandidateData>  $candidates
     * @param  Collection<int, float>  $fairScoresByEmployeeId
     * @return Collection<int, CandidateData>
     */
    private function sortCandidates(Collection $candidates, string $rankingMode, Collection $fairScoresByEmployeeId): Collection
    {
        [$eligible, $excluded] = $candidates->partition(fn (CandidateData $c) => $c->isEligible());

        $sortedEligible = $eligible->sort(function (CandidateData $a, CandidateData $b) use ($rankingMode, $fairScoresByEmployeeId) {
            $scoreCompare = $a->score <=> $b->score;
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            if ($rankingMode === SuggestionRequestData::RANKING_COST) {
                $fairCompare = (float) $fairScoresByEmployeeId->get($a->employee->id, 0.0) <=> (float) $fairScoresByEmployeeId->get($b->employee->id, 0.0);
                if ($fairCompare !== 0) {
                    return $fairCompare;
                }
            }

            return $a->employee->id <=> $b->employee->id;
        })->values();

        return $sortedEligible->merge($excluded->values());
    }
}
