<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Scheduling\Contracts\ScheduleSuggestionServiceContract;
use App\Modules\Scheduling\Contracts\ShiftAssignmentServiceContract;
use App\Modules\Scheduling\DTOs\RosterWeekPlanData;
use App\Modules\Scheduling\DTOs\ShiftAssignmentData;
use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\Exceptions\RosterPlannerUnavailableException;
use App\Modules\Scheduling\Exceptions\ScheduleNotRegenerableException;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a draft week (ProjectPlan.md §12.2, §27.1, §28.9). The roster planner - a CP-SAT
 * solver over several weeks - decides every shift lead and station operator; this service
 * asks it, places its first week, and adds what the planner doesn't decide: flexible-hours
 * roles copied from last week, and secondary tasks spread over whoever works each shift.
 * What it leaves open is reported live by MandatoryCoverageChecker (ScheduleResource), with
 * alternatives from AlternativeCandidateService, for the planner to fill by hand.
 */
final class ScheduleSuggestionService implements ScheduleSuggestionServiceContract
{
    public function __construct(
        private readonly ShiftAssignmentServiceContract $shiftAssignmentService,
        private readonly EmployeeFairnessScoreService $fairnessScores,
        private readonly RoleRotationService $roleRotation,
        private readonly RosterPlanner $rosterPlanner,
    ) {}

    /**
     * The planner is asked before the draft is touched: when it can't answer, this throws
     * RosterPlannerUnavailableException and an existing draft keeps its assignments.
     *
     * @throws RosterPlannerUnavailableException
     */
    public function generate(SuggestionRequestData $request): Schedule
    {
        $weekStart = Carbon::parse($request->weekStartDate)->startOfDay();
        $existing = $request->scheduleId !== null ? $this->regenerableSchedule($request->scheduleId) : null;
        $companySetting = CompanySetting::current();

        $lines = Line::query()->whereIn('id', $request->lineIds)->get();
        $lineSlotExclusions = $this->buildLineSlotExclusionSet($request->lineSlotExclusions);

        // Every ShiftPattern row across the selected lines' own groups, for the selected
        // slot types - keyed by "lineId|slotType" so each role's line resolves to the
        // correct concrete hours (ProjectPlan.md: "Day Shift" stays one name everywhere, but
        // its hours vary by the line's shift_pattern_group).
        $patternsByGroupAndSlot = ShiftPattern::query()
            ->whereIn('shift_pattern_group_id', $lines->pluck('shift_pattern_group_id')->filter()->unique())
            ->whereIn('slot_type', $request->slotTypes)
            ->get()
            ->groupBy('shift_pattern_group_id');

        $shiftPatternsByLineAndSlot = collect();
        foreach ($lines as $line) {
            if ($line->shift_pattern_group_id === null) {
                continue;
            }

            foreach ($patternsByGroupAndSlot->get($line->shift_pattern_group_id, collect()) as $pattern) {
                $shiftPatternsByLineAndSlot->put("{$line->id}|{$pattern->slot_type}", $pattern);
            }
        }

        $stationRolesQuery = SchedulingRole::query()
            ->where('role_kind', SchedulingRole::KIND_STATION)
            ->whereIn('line_id', $lines->pluck('id'));
        // 'shift_lead' and 'flexible' secondary tasks have their own steps
        // (placePlannedShiftLeads()/assignFlexibleRoles()) - excluded here so they are never
        // also run through the generic per-slot secondary-task step, which would treat them
        // as an ordinary "staff from whoever's already present" task.
        $secondaryTaskRolesQuery = SchedulingRole::query()
            ->where('role_kind', SchedulingRole::KIND_SECONDARY_TASK)
            ->whereNotIn('attachment_type', ['shift_lead', 'flexible']);

        if ($request->roleIds !== null) {
            $stationRolesQuery->whereIn('id', $request->roleIds);
            $secondaryTaskRolesQuery->whereIn('id', $request->roleIds);
        }

        $stationRoles = $stationRolesQuery->get();
        $secondaryTaskRoles = $secondaryTaskRolesQuery->get();
        $days = collect(range(0, 6))->map(fn (int $offset) => $weekStart->copy()->addDays($offset));

        $horizonWeeks = $companySetting->planner_horizon_weeks;
        $plan = $this->rosterPlanner->planWeek(
            $weekStart,
            $request->slotTypes,
            $this->openShiftLeadShifts($weekStart, $horizonWeeks, $request->slotTypes, $lines, $shiftPatternsByLineAndSlot, $lineSlotExclusions),
            $this->openStationSlots($weekStart, $horizonWeeks, $request->slotTypes, $stationRoles, $shiftPatternsByLineAndSlot, $lineSlotExclusions),
            $request->rankingMode,
        );

        $schedule = $this->prepareDraft($request, $existing);

        // Recent weighted hours (last 8 approved weeks, plus this run's placements) and role
        // rotation only order the alternatives offered for an open slot and spread secondary
        // tasks - the planner keeps its own history.
        $fairScores = $this->fairnessScores->recentScoresByEmployee();
        $lastAssignedByEmployeeAndRole = $companySetting->role_rotation_enabled
            ? $this->roleRotation->lastAssignedDatesByEmployeeAndRole()
            : null;

        foreach ($days as $day) {
            $this->assignFlexibleRoles($schedule, $day, $lines);

            $presentBySlot = $this->placePlannedShiftLeads(
                $schedule,
                $day,
                $plan,
                $request->slotTypes,
                $shiftPatternsByLineAndSlot,
                $lines,
                $lineSlotExclusions,
                $lastAssignedByEmployeeAndRole,
            );

            foreach ($request->slotTypes as $slotType) {
                // Everyone working this slot - leads, then station holders, then
                // secondary-task holders as they're added - whom secondary tasks can ride.
                $presentThisSlot = $presentBySlot->get($slotType, collect());

                foreach ($this->stationsRunning($stationRoles, $day, $slotType, $shiftPatternsByLineAndSlot, $lineSlotExclusions) as ['role' => $role, 'shiftPattern' => $shiftPattern]) {
                    $operators = Employee::query()->whereIn('id', $plan->operatorsFor($day->format('Y-m-d'), $slotType, $role->line_id, $role->id))->get();

                    foreach ($operators as $operator) {
                        $this->placeStationAssignment($schedule, $operator, $role, $shiftPattern, $day, $slotType, $companySetting, $fairScores, $lastAssignedByEmployeeAndRole, $presentThisSlot);
                    }
                }

                // Reset every slot: purely a within-this-shift tiebreak so back-to-back
                // secondary tasks in the same shift spread across different people.
                $secondaryTaskCountThisSlot = collect();

                foreach ($secondaryTaskRoles as $task) {
                    $this->assignSecondaryTask(
                        $schedule,
                        $task,
                        $day,
                        $slotType,
                        $shiftPatternsByLineAndSlot,
                        $presentThisSlot,
                        $fairScores,
                        $secondaryTaskCountThisSlot,
                        $lineSlotExclusions,
                        $lastAssignedByEmployeeAndRole,
                        $companySetting,
                    );
                }
            }
        }

        return $schedule->fresh(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role']);
    }

    /**
     * @return Collection<string, true> keyed "lineId|isoDayOfWeek|slotType"
     */
    private function buildLineSlotExclusionSet(?array $lineSlotExclusions): Collection
    {
        return collect($lineSlotExclusions ?? [])
            ->mapWithKeys(fn (array $e) => ["{$e['line_id']}|{$e['day_of_week']}|{$e['slot_type']}" => true]);
    }

    /**
     * @param  Collection<string, true>  $exclusionSet
     */
    private function isLineSlotExcluded(Collection $exclusionSet, ?int $lineId, int $isoDayOfWeek, string $slotType): bool
    {
        return $lineId !== null && $exclusionSet->has("{$lineId}|{$isoDayOfWeek}|{$slotType}");
    }

    /**
     * The draft to regenerate, checked before anything is planned or touched: it must still
     * be editable, and never have been approved (§17.7's was_approved_before - an approved
     * week is only ever corrected by hand). Since a week may have more than one draft
     * (several scenarios), which one to regenerate is never guessed from week_start_date -
     * the caller says so via scheduleId, or generate starts a new draft.
     */
    private function regenerableSchedule(int $scheduleId): Schedule
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);

        if (! $schedule->isEditableByAdmin()) {
            throw new ScheduleNotRegenerableException('This schedule is locked and cannot be regenerated in its current status.');
        }

        if (! $schedule->canBeGenerated()) {
            throw new ScheduleNotRegenerableException('This schedule was previously approved; it can only be edited manually, not regenerated.');
        }

        return $schedule;
    }

    /**
     * Clears the previous auto-suggested assignments of the draft being regenerated (manual
     * ones stay) or creates a new draft - only once the planner has answered.
     */
    private function prepareDraft(SuggestionRequestData $request, ?Schedule $existing): Schedule
    {
        $scope = [
            'line_ids' => $request->lineIds,
            'slot_types' => $request->slotTypes,
            'role_ids' => $request->roleIds,
            // So MandatoryCoverageChecker (re-run at approve time, and by ScheduleResource
            // for the grid's "Unfilled" display) can tell a deliberately-excluded
            // line/day/slot apart from a genuinely unstaffed one - otherwise a day the admin
            // unchecked here (e.g. "don't generate Fri/Sat for Lijn 1") comes back as a
            // mandatory-coverage gap it never should have been.
            'line_slot_exclusions' => $request->lineSlotExclusions,
        ];

        if ($existing !== null) {
            $existing->assignments()->where('source', ShiftAssignment::SOURCE_AUTO_SUGGESTED)->delete();
            $existing->update(['generated_scope' => $scope]);

            return $existing;
        }

        return Schedule::create([
            'week_start_date' => $request->weekStartDate,
            'status' => Schedule::STATUS_DRAFT,
            'created_by' => $request->requestedBy,
            'generated_scope' => $scope,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function shiftTimeRange(Carbon $day, ShiftPattern $shiftPattern): array
    {
        $start = Carbon::parse($day->format('Y-m-d').' '.$shiftPattern->start_time);
        $end = Carbon::parse($day->format('Y-m-d').' '.$shiftPattern->end_time);

        if ($shiftPattern->crosses_midnight) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * The station roles that run on this day and slot type, with their line's pattern: the
     * line has a pattern for the slot type and isn't closed or excluded that day.
     *
     * @param  Collection<int, SchedulingRole>  $stationRoles
     * @param  Collection<string, ShiftPattern>  $shiftPatternsByLineAndSlot
     * @param  Collection<string, true>  $lineSlotExclusions
     * @return Collection<int, array{role: SchedulingRole, shiftPattern: ShiftPattern}>
     */
    private function stationsRunning(Collection $stationRoles, Carbon $day, string $slotType, Collection $shiftPatternsByLineAndSlot, Collection $lineSlotExclusions): Collection
    {
        return $stationRoles
            ->map(fn (SchedulingRole $role) => ['role' => $role, 'shiftPattern' => $shiftPatternsByLineAndSlot->get("{$role->line_id}|{$slotType}")])
            ->filter(fn (array $entry) => $entry['shiftPattern'] !== null && ($entry['role']->line === null || (
                ! $entry['role']->line->isClosedOn($day->dayOfWeekIso, $slotType)
                && ! $this->isLineSlotExcluded($lineSlotExclusions, $entry['role']->line_id, $day->dayOfWeekIso, $slotType)
            )))
            ->values();
    }

    /**
     * Second pass, per ProjectPlan.md §11a.3c: line-priority first (candidate pool
     * restricted to whoever already holds ANY assignment this shift, per the task's
     * attachment mode - never pulls in someone new), then the SAME cumulative fairness
     * score station assignment uses (the last 8 approved weeks plus this run's
     * placements) - a secondary task never adds marginal hours (it rides the shift the winner
     * is already working), but distributing it by fairness still matters: it's extra
     * responsibility/load, so it shouldn't always land on the same person.
     *
     * @param  Collection<int, array{employee: Employee, role: SchedulingRole, line_id?: ?int}>  $stationAssignmentsThisSlot  station
     *                                                                                                                        winners this slot, PLUS (appended by this method as it runs) any secondary-task
     *                                                                                                                        winners already handed out this same slot - so a 'station' task matches only real
     *                                                                                                                        station roles (task/station ids never collide) while a 'line' task also sees
     *                                                                                                                        earlier secondary-task winners on that line as already "present" this shift.
     * @param  Collection<string, ShiftPattern>  $shiftPatternsByLineAndSlot  keyed by
     *                                                                        "lineId|slotType" - a secondary task's effective line (its own line_id, or the
     *                                                                        line of whichever station it's attached to) determines which concrete pattern row
     *                                                                        applies, since the same slot type has different hours on different lines.
     * @param  Collection<int, float>  $fairScores  employee_id => cumulative weighted hours
     *                                              (same running tally station assignment updates) - used purely to break ties fairly,
     *                                              never mutated here since a secondary task adds no marginal hours of its own.
     * @param  Collection<int, int>  $secondaryTaskCountThisSlot  employee_id => how many
     *                                                            secondary tasks they've already picked up THIS slot - reset per slot by the caller, so
     *                                                            back-to-back tasks in one shift spread across people instead of always landing on
     *                                                            whoever's fairScore is lowest.
     * @param  Collection<string, true>  $lineSlotExclusions
     * @param  ?Collection<string, Carbon>  $lastAssignedByEmployeeAndRole  keyed
     *                                                                      "employeeId|roleId" => most recent approved work_date (ProjectPlan.md §12.6 item 4) -
     *                                                                      null when CompanySetting::role_rotation_enabled is off.
     * @param  ?CompanySetting  $companySetting  ProjectPlan.md §12.6 item 7 - when given, a
     *                                           task with a non-default fairness_weight earns its holder a marginal fairness credit
     *                                           ((weight - 1.0) x this shift's weighted hours) even though a secondary task
     *                                           otherwise adds zero marginal hours (it rides a shift already counted via the
     *                                           station assignment).
     */
    private function assignSecondaryTask(
        Schedule $schedule,
        SchedulingRole $task,
        Carbon $day,
        string $slotType,
        Collection $shiftPatternsByLineAndSlot,
        Collection $stationAssignmentsThisSlot,
        Collection $fairScores,
        Collection $secondaryTaskCountThisSlot,
        Collection $lineSlotExclusions,
        ?Collection $lastAssignedByEmployeeAndRole = null,
        ?CompanySetting $companySetting = null,
    ): void {
        $candidatePool = match ($task->attachment_type) {
            'station' => $stationAssignmentsThisSlot
                ->filter(fn (array $a) => $a['role']->id === $task->attached_station_role_id)
                ->pluck('employee'),
            // Everyone already holding ANY assignment THIS SLOT on this line - a station,
            // or another secondary task already handed out this same slot (e.g. a shift
            // lead's own oversight duty already makes them "present" this shift, so
            // they're eligible for a further line-attached task too). Deliberately scoped
            // to $stationAssignmentsThisSlot (which this method also appends its own
            // winners to, below) rather than the whole day's $assignedToday - someone
            // working a DIFFERENT slot today (e.g. the day shift) isn't present during THIS
            // slot (e.g. the night shift) and must not be pulled in here. Secondary tasks
            // are never staffed by pulling in someone new - only by spreading them across
            // people already working this slot.
            'line' => $stationAssignmentsThisSlot
                ->filter(fn (array $a) => $a['role']->line_id === $task->line_id)
                ->pluck('employee'),
            default => $stationAssignmentsThisSlot->pluck('employee'),
        };

        // $stationAssignmentsThisSlot's first entry may itself be a line-independent Shift
        // Lead placement (line_id=null) - skip past any such entries to find the first one
        // that actually anchors to a line, rather than always taking literally the first
        // element (which would incorrectly resolve to null whenever a shift lead was placed
        // before this task runs).
        $firstLineAnchoredAssignment = $stationAssignmentsThisSlot->first(fn (array $a) => $a['role']->line_id !== null);
        $effectiveLineId = $task->line_id ?? $firstLineAnchoredAssignment['role']->line_id ?? null;
        $shiftPattern = $effectiveLineId !== null
            ? $shiftPatternsByLineAndSlot->get("{$effectiveLineId}|{$slotType}")
            : null;

        if ($shiftPattern === null) {
            return;
        }

        $effectiveLine = $effectiveLineId !== null ? Line::find($effectiveLineId) : null;

        if ($effectiveLine !== null && (
            $effectiveLine->isClosedOn($day->dayOfWeekIso, $slotType)
            || $this->isLineSlotExcluded($lineSlotExclusions, $effectiveLineId, $day->dayOfWeekIso, $slotType)
        )) {
            // This line doesn't operate this day/shift at all - not an unfilled slot,
            // simply nothing to schedule (mirrors the station-role check above).
            return;
        }

        // This exact task/day/shift may already be filled: regenerating a draft keeps its
        // manual assignments, so someone may have been given this task by hand. Without this
        // check the task would get a second, redundant holder - a real double-booking for a
        // task with no multi-person concept at all (unlike a station's min_headcount).
        $alreadyFilled = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('role_id', $task->id)
            ->where('work_date', $day->format('Y-m-d'))
            ->when(
                $task->line_id === null,
                // A line-independent task takes its holder's own line's hours (below), so the
                // same task this slot may sit on any line's pattern - match the slot type.
                fn ($q) => $q->whereHas('shiftPattern', fn ($p) => $p->where('slot_type', $slotType)),
                fn ($q) => $q->where('shift_pattern_id', $shiftPattern->id),
            )
            ->exists();

        if ($alreadyFilled) {
            return;
        }

        $qualifiedEmployeeIds = $task->qualifiedEmployees()->pluck('employees.id')->all();
        $excludedEmployeeIds = $task->excludedEmployees()->pluck('employees.id')->all();
        $unavailableEmployeeIds = Employee::query()
            ->whereHas('unavailabilities', fn ($q) => $q->where('day_of_week', $day->dayOfWeekIso)->where('slot_type', $slotType))
            ->pluck('id')
            ->all();

        $qualifiedPool = $candidatePool->filter(
            fn (Employee $e) => in_array($e->id, $qualifiedEmployeeIds, true)
                && ! in_array($e->id, $excludedEmployeeIds, true)
                && ! in_array($e->id, $unavailableEmployeeIds, true)
        );

        if ($qualifiedPool->isEmpty()) {
            return;
        }

        $winner = $qualifiedPool->sort(function (Employee $a, Employee $b) use ($secondaryTaskCountThisSlot, $fairScores, $lastAssignedByEmployeeAndRole, $task, $day) {
            $countCompare = $secondaryTaskCountThisSlot->get($a->id, 0) <=> $secondaryTaskCountThisSlot->get($b->id, 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            // Role-specific rotation signal (ProjectPlan.md §12.6 item 4) - same cascade
            // position as the station-ranking cascade in SlotCandidateRanker::sortCandidates():
            // ahead of fairScores, never treating "never done this task" (null) as a tie
            // with "just did it". Only engages when the caller passed a map (rotation
            // enabled).
            if ($lastAssignedByEmployeeAndRole !== null) {
                $lastA = $lastAssignedByEmployeeAndRole->get("{$a->id}|{$task->id}");
                $lastB = $lastAssignedByEmployeeAndRole->get("{$b->id}|{$task->id}");
                $daysSinceA = $lastA !== null ? (int) $day->diffInDays($lastA) : PHP_INT_MAX;
                $daysSinceB = $lastB !== null ? (int) $day->diffInDays($lastB) : PHP_INT_MAX;

                $rotationCompare = $daysSinceB <=> $daysSinceA;
                if ($rotationCompare !== 0) {
                    return $rotationCompare;
                }
            }

            return (float) $fairScores->get($a->id, 0.0) <=> (float) $fairScores->get($b->id, 0.0);
        })->first();

        // A line-independent task (e.g. silo watch) runs during its holder's own shift, so it
        // takes the hours of the line the winner works this slot - a Line 1 lead (00:00-08:00)
        // given Line 2's night (02:00-10:00) would otherwise be booked for ten hours that day.
        if ($task->line_id === null) {
            $winnerLineId = $stationAssignmentsThisSlot
                ->filter(fn (array $a) => $a['employee']->id === $winner->id)
                ->map(fn (array $a) => $a['line_id'] ?? $a['role']->line_id)
                ->first(fn (?int $lineId) => $lineId !== null && $shiftPatternsByLineAndSlot->has("{$lineId}|{$slotType}"));

            if ($winnerLineId !== null) {
                $effectiveLineId = $winnerLineId;
                $shiftPattern = $shiftPatternsByLineAndSlot->get("{$winnerLineId}|{$slotType}");
            }
        }

        $this->shiftAssignmentService->create($schedule, new ShiftAssignmentData(
            employeeId: $winner->id,
            lineId: $effectiveLineId,
            shiftPatternId: $shiftPattern->id,
            workDate: $day->format('Y-m-d'),
            roleId: $task->id,
            startsAt: null,
            endsAt: null,
            status: ShiftAssignment::STATUS_PROPOSED,
            source: ShiftAssignment::SOURCE_AUTO_SUGGESTED,
            notes: null,
        ));

        $secondaryTaskCountThisSlot->put($winner->id, $secondaryTaskCountThisSlot->get($winner->id, 0) + 1);
        $lastAssignedByEmployeeAndRole?->put("{$winner->id}|{$task->id}", $day->copy());

        // §12.6 item 7: a secondary task normally adds zero marginal hours (its own shift's
        // hours already counted via the station assignment the winner holds) - a
        // non-default fairness_weight is the one deliberate exception, crediting only the
        // DELTA beyond that baseline so a harder/less-liked task tangibly counts as extra
        // load without double-counting the underlying shift.
        if ($companySetting !== null && $task->fairness_weight !== 1.0) {
            [$taskShiftStart, $taskShiftEnd] = $this->shiftTimeRange($day, $shiftPattern);
            $hours = $taskShiftStart->floatDiffInHours($taskShiftEnd);
            $marginalCredit = $hours
                * $companySetting->shiftTypeWeight($slotType)
                * $companySetting->dayTypeWeight($day->dayOfWeekIso)
                * ($task->fairness_weight - 1.0);
            $fairScores->put($winner->id, (float) $fairScores->get($winner->id, 0.0) + $marginalCredit);
        }

        // So a later secondary task processed in this same slot (e.g. a second
        // line-attached task after this one) sees this winner as already "present" this
        // shift too - mirrors how station winners are appended by the caller.
        $stationAssignmentsThisSlot->push(['employee' => $winner, 'role' => $task, 'line_id' => $effectiveLineId]);
    }

    /**
     * The in-scope line a company-wide shift lead borrows hours from on this day and slot
     * type: the first one that runs it (not closed, not excluded, has a pattern for it).
     * Null means no line runs that slot type that day, so it needs no lead.
     *
     * @param  Collection<int, Line>  $lines
     * @param  Collection<string, ShiftPattern>  $shiftPatternsByLineAndSlot
     * @param  Collection<string, true>  $lineSlotExclusions
     */
    private function shiftLeadLine(Carbon $day, string $slotType, Collection $lines, Collection $shiftPatternsByLineAndSlot, Collection $lineSlotExclusions): ?Line
    {
        return $lines->first(fn (Line $line) => ! $line->isClosedOn($day->dayOfWeekIso, $slotType)
            && ! $this->isLineSlotExcluded($lineSlotExclusions, $line->id, $day->dayOfWeekIso, $slotType)
            && $shiftPatternsByLineAndSlot->has("{$line->id}|{$slotType}"));
    }

    /**
     * Every shift-lead shift of the roster planner's horizon (ProjectPlan.md §28), starting
     * at this week: this week's lines, slot types and exclusions, repeated week after week -
     * the lookahead assumes the scope stays as it is.
     *
     * @param  array<int, string>  $slotTypes
     * @param  Collection<int, Line>  $lines
     * @param  Collection<string, ShiftPattern>  $shiftPatternsByLineAndSlot
     * @param  Collection<string, true>  $lineSlotExclusions
     * @return Collection<int, array{date: string, slot_type: string, start: Carbon, end: Carbon}>
     */
    private function openShiftLeadShifts(Carbon $weekStart, int $weeks, array $slotTypes, Collection $lines, Collection $shiftPatternsByLineAndSlot, Collection $lineSlotExclusions): Collection
    {
        $shifts = collect();

        for ($offset = 0; $offset < $weeks * 7; $offset++) {
            $day = $weekStart->copy()->addDays($offset);

            foreach ($slotTypes as $slotType) {
                $line = $this->shiftLeadLine($day, $slotType, $lines, $shiftPatternsByLineAndSlot, $lineSlotExclusions);

                if ($line === null) {
                    continue;
                }

                [$start, $end] = $this->shiftTimeRange($day, $shiftPatternsByLineAndSlot->get("{$line->id}|{$slotType}"));
                $shifts->push(['date' => $day->format('Y-m-d'), 'slot_type' => $slotType, 'start' => $start, 'end' => $end]);
            }
        }

        return $shifts;
    }

    /**
     * Every station of the roster planner's horizon (ProjectPlan.md §28) that runs
     * (stationsRunning(), day by day), repeated over the lookahead weeks with this week's
     * scope - with its line's hours and its headcount: min_headcount people, one more on an
     * allows_second_person station (who only ever fills someone's hours).
     *
     * @param  array<int, string>  $slotTypes
     * @param  Collection<int, SchedulingRole>  $stationRoles
     * @param  Collection<string, ShiftPattern>  $shiftPatternsByLineAndSlot
     * @param  Collection<string, true>  $lineSlotExclusions
     * @return Collection<int, array{date: string, slot_type: string, line_id: int, role_id: int, start: Carbon, end: Carbon, min: int, max: int}>
     */
    private function openStationSlots(Carbon $weekStart, int $weeks, array $slotTypes, Collection $stationRoles, Collection $shiftPatternsByLineAndSlot, Collection $lineSlotExclusions): Collection
    {
        $slots = collect();

        for ($offset = 0; $offset < $weeks * 7; $offset++) {
            $day = $weekStart->copy()->addDays($offset);

            foreach ($slotTypes as $slotType) {
                foreach ($this->stationsRunning($stationRoles, $day, $slotType, $shiftPatternsByLineAndSlot, $lineSlotExclusions) as ['role' => $role, 'shiftPattern' => $shiftPattern]) {
                    [$start, $end] = $this->shiftTimeRange($day, $shiftPattern);
                    $min = max(1, $role->min_headcount);
                    $slots->push([
                        'date' => $day->format('Y-m-d'),
                        'slot_type' => $slotType,
                        'line_id' => $role->line_id,
                        'role_id' => $role->id,
                        'start' => $start,
                        'end' => $end,
                        'min' => $min,
                        'max' => $role->allows_second_person ? $min + 1 : $min,
                    ]);
                }
            }
        }

        return $slots;
    }

    /**
     * Creates one planned station assignment and records it where the rest of the run looks:
     * the fairness tally and role rotation (which order alternatives and secondary tasks),
     * and who is present this slot (whom secondary tasks can ride).
     *
     * @param  Collection<int, float>  $fairScores
     * @param  ?Collection<string, Carbon>  $lastAssignedByEmployeeAndRole
     * @param  Collection<int, array{employee: Employee, role: SchedulingRole, line_id?: ?int}>  $stationAssignmentsThisSlot
     */
    private function placeStationAssignment(
        Schedule $schedule,
        Employee $employee,
        SchedulingRole $role,
        ShiftPattern $shiftPattern,
        Carbon $day,
        string $slotType,
        CompanySetting $companySetting,
        Collection $fairScores,
        ?Collection $lastAssignedByEmployeeAndRole,
        Collection $stationAssignmentsThisSlot,
    ): void {
        $this->shiftAssignmentService->create($schedule, new ShiftAssignmentData(
            employeeId: $employee->id,
            lineId: $role->line_id,
            shiftPatternId: $shiftPattern->id,
            workDate: $day->format('Y-m-d'),
            roleId: $role->id,
            startsAt: null,
            endsAt: null,
            status: ShiftAssignment::STATUS_PROPOSED,
            source: ShiftAssignment::SOURCE_AUTO_SUGGESTED,
            notes: null,
        ));

        [$shiftStart, $shiftEnd] = $this->shiftTimeRange($day, $shiftPattern);
        // §12.6 item 7: $role->fairness_weight (default 1.0 = no-op) lets a harder/less-liked
        // role's hours count as proportionally more load toward its holder's fairness score,
        // not just its actual duration.
        $weightedHours = $shiftStart->floatDiffInHours($shiftEnd) * $companySetting->shiftTypeWeight($slotType) * $companySetting->dayTypeWeight($day->dayOfWeekIso) * $role->fairness_weight;
        $fairScores->put($employee->id, (float) $fairScores->get($employee->id, 0.0) + $weightedHours);

        $lastAssignedByEmployeeAndRole?->put("{$employee->id}|{$role->id}", $day->copy());

        $stationAssignmentsThisSlot->push(['employee' => $employee, 'role' => $role, 'line_id' => $role->line_id]);
    }

    /**
     * Places the shift leads the roster planner planned for this day (ProjectPlan.md §28),
     * on the first in-scope line running each slot type (company-wide leads borrow its
     * hours). The n-th lead of a shift takes the n-th shift-lead role; a lead beyond the
     * roles is that shift's second lead on the first role. Returns who is present per slot
     * type, for the station and secondary-task steps that follow.
     *
     * @param  array<int, string>  $slotTypes
     * @param  Collection<string, ShiftPattern>  $shiftPatternsByLineAndSlot
     * @param  Collection<int, Line>  $lines
     * @param  Collection<string, true>  $lineSlotExclusions
     * @param  ?Collection<string, Carbon>  $lastAssignedByEmployeeAndRole
     * @return Collection<string, Collection<int, array{employee: Employee, role: SchedulingRole, line_id?: ?int}>>
     */
    private function placePlannedShiftLeads(
        Schedule $schedule,
        Carbon $day,
        RosterWeekPlanData $plan,
        array $slotTypes,
        Collection $shiftPatternsByLineAndSlot,
        Collection $lines,
        Collection $lineSlotExclusions,
        ?Collection $lastAssignedByEmployeeAndRole,
    ): Collection {
        $result = collect($slotTypes)->mapWithKeys(fn (string $s) => [$s => collect()]);
        $roles = SchedulingRole::query()->where('attachment_type', 'shift_lead')->orderBy('id')->get();

        foreach ($slotTypes as $slotType) {
            $line = $this->shiftLeadLine($day, $slotType, $lines, $shiftPatternsByLineAndSlot, $lineSlotExclusions);

            if ($line === null || $roles->isEmpty()) {
                continue;
            }

            $shiftPattern = $shiftPatternsByLineAndSlot->get("{$line->id}|{$slotType}");
            $leadIds = $plan->leadsFor($day->format('Y-m-d'), $slotType)->values();
            $leads = Employee::query()->whereIn('id', $leadIds)->get()->keyBy('id');

            foreach ($leadIds as $position => $leadId) {
                $lead = $leads->get($leadId);

                if ($lead === null) {
                    continue;
                }

                $role = $roles->get($position) ?? $roles->first();

                $this->shiftAssignmentService->create($schedule, new ShiftAssignmentData(
                    employeeId: $lead->id,
                    lineId: $line->id,
                    shiftPatternId: $shiftPattern->id,
                    workDate: $day->format('Y-m-d'),
                    roleId: $role->id,
                    startsAt: null,
                    endsAt: null,
                    status: ShiftAssignment::STATUS_PROPOSED,
                    source: ShiftAssignment::SOURCE_AUTO_SUGGESTED,
                    notes: null,
                ));

                $lastAssignedByEmployeeAndRole?->put("{$lead->id}|{$role->id}", $day->copy());

                $result->get($slotType)->push(['employee' => $lead, 'role' => $role, 'line_id' => $line->id]);
            }
        }

        return $result;
    }

    /**
     * A flexible-hours role (e.g. Production Lead) has no fixed ShiftPattern and no
     * standard slotType - its hours are day-specific and admin-set, never generated from
     * scratch. This step only ever COPIES last week's same-weekday placement for this role
     * as a draft suggestion the admin can edit or leave as-is; a day with no record last
     * week is left entirely unfilled (never invented), matching the confirmed "boş
     * bırakılır" design - a soft suggestion, not a mandatory-coverage concept.
     *
     * @param  Collection<int, Line>  $lines
     */
    private function assignFlexibleRoles(Schedule $schedule, Carbon $day, Collection $lines): void
    {
        $flexibleRoles = SchedulingRole::query()
            ->where('attachment_type', 'flexible')
            ->get();

        if ($flexibleRoles->isEmpty()) {
            return;
        }

        $lastWeekDate = $day->copy()->subDays(7)->format('Y-m-d');

        foreach ($flexibleRoles as $role) {
            $alreadyAssigned = ShiftAssignment::query()
                ->where('schedule_id', $schedule->id)
                ->where('role_id', $role->id)
                ->where('work_date', $day->format('Y-m-d'))
                ->exists();

            if ($alreadyAssigned) {
                continue;
            }

            $lastWeekAssignment = ShiftAssignment::query()
                ->where('role_id', $role->id)
                ->where('work_date', $lastWeekDate)
                ->whereHas('schedule', fn ($q) => $q->whereIn('status', ['approved', 'proposed']))
                ->first();

            if ($lastWeekAssignment === null) {
                // No record last week to copy - left unfilled, never invented.
                continue;
            }

            // The line itself is nominal/borrowed (the role definition is line-independent,
            // per the confirmed Shift Lead precedent) - still must reference a real,
            // currently in-scope line, so fall back to any available one if last week's
            // line has since gone out of scope.
            $lineId = $lines->firstWhere('id', $lastWeekAssignment->line_id)?->id ?? $lines->first()?->id;

            if ($lineId === null) {
                continue;
            }

            $this->shiftAssignmentService->create($schedule, new ShiftAssignmentData(
                employeeId: $lastWeekAssignment->employee_id,
                lineId: $lineId,
                shiftPatternId: null,
                workDate: $day->format('Y-m-d'),
                roleId: $role->id,
                startsAt: $lastWeekAssignment->starts_at,
                endsAt: $lastWeekAssignment->ends_at,
                status: ShiftAssignment::STATUS_PROPOSED,
                source: ShiftAssignment::SOURCE_AUTO_SUGGESTED,
                notes: null,
            ));
        }
    }
}
