<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Scheduling\Contracts\ScheduleSuggestionServiceContract;
use App\Modules\Scheduling\Contracts\ShiftAssignmentServiceContract;
use App\Modules\Scheduling\DTOs\CandidateData;
use App\Modules\Scheduling\DTOs\ShiftAssignmentData;
use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\DTOs\SuggestionResultData;
use App\Modules\Scheduling\DTOs\UnfilledSlotData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The centerpiece of Phase 5 (ProjectPlan.md §12.2, §12.2a, §11a.3c): builds a rule-aware
 * draft schedule across the planner's selected lines/shifts/roles for a week, ranking
 * station candidates by fairness or cost and secondary-task candidates by a fixed
 * line-priority-then-fairness rule, and surfaces whatever it could not staff.
 */
final class ScheduleSuggestionService implements ScheduleSuggestionServiceContract
{
    public function __construct(
        private readonly SlotCandidateRanker $ranker,
        private readonly ShiftAssignmentServiceContract $shiftAssignmentService,
    ) {
    }

    public function generate(SuggestionRequestData $request): SuggestionResultData
    {
        $weekStart = Carbon::parse($request->weekStartDate)->startOfDay();
        $schedule = $this->findOrCreateDraftSchedule($request);

        $lines = Line::query()->whereIn('id', $request->lineIds)->get();
        $shiftPatterns = ShiftPattern::query()->whereIn('id', $request->shiftPatternIds)->get();

        $stationRolesQuery = SchedulingRole::query()
            ->where('role_kind', SchedulingRole::KIND_STATION)
            ->whereIn('line_id', $lines->pluck('id'));
        $secondaryTaskRolesQuery = SchedulingRole::query()->where('role_kind', SchedulingRole::KIND_SECONDARY_TASK);

        if ($request->roleIds !== null) {
            $stationRolesQuery->whereIn('id', $request->roleIds);
            $secondaryTaskRolesQuery->whereIn('id', $request->roleIds);
        }

        $stationRoles = $stationRolesQuery->get();
        $secondaryTaskRoles = $secondaryTaskRolesQuery->get();

        $days = collect(range(0, 6))->map(fn (int $offset) => $weekStart->copy()->addDays($offset));

        $fairScores = collect();
        $secondaryTaskCounts = collect();
        $unfilled = collect();

        // Soft shift-consistency preference (ProjectPlan.md-confirmed): the shift_pattern_id
        // an employee is first assigned to this run becomes their "home" pattern - set once,
        // never overwritten, in-memory only for this generation pass.
        $homeShiftPatterns = collect();

        // Soft consecutive-rest-day preference bookkeeping: every employee who has appeared
        // in at least one role's candidate pool this run (so someone never in scope for any
        // role isn't wrongly counted as "resting"), and each employee's rest days recorded
        // so far as the day-by-day loop below progresses.
        $poolEmployeeIds = collect();
        $restDaysByEmployee = collect();

        foreach ($days as $day) {
            $assignedToday = collect();

            foreach ($shiftPatterns as $shiftPattern) {
                [$shiftStart, $shiftEnd] = $this->shiftTimeRange($day, $shiftPattern);

                $stationAssignmentsThisSlot = collect();

                foreach ($stationRoles as $role) {
                    $pool = $this->buildCandidatePool($day, $role);
                    $poolEmployeeIds = $poolEmployeeIds->merge($pool->pluck('id'));

                    $ranked = $this->ranker->rankPool(
                        $pool,
                        $shiftStart,
                        $shiftEnd,
                        $request->rankingMode,
                        $fairScores,
                        $shiftPattern->id,
                        $homeShiftPatterns,
                        $restDaysByEmployee,
                    );

                    $winner = $ranked->first(fn (CandidateData $c) => $c->isEligible());

                    if ($winner === null) {
                        $alternatives = $this->ranker->rankAllForRole($role, $shiftStart, $shiftEnd, $request->rankingMode, $fairScores);

                        $unfilled->push(new UnfilledSlotData(
                            line: $role->line,
                            shiftPattern: $shiftPattern,
                            workDate: $day->copy(),
                            role: $role,
                            blocking: (bool) $role->requires_coverage,
                            alternatives: $alternatives,
                        ));

                        continue;
                    }

                    $this->shiftAssignmentService->create($schedule, new ShiftAssignmentData(
                        employeeId: $winner->employee->id,
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

                    $hours = $shiftStart->floatDiffInHours($shiftEnd);
                    $fairScores->put($winner->employee->id, (float) $fairScores->get($winner->employee->id, 0.0) + $hours);

                    if (! $homeShiftPatterns->has($winner->employee->id)) {
                        $homeShiftPatterns->put($winner->employee->id, $shiftPattern->id);
                    }

                    $assignedToday->put($winner->employee->id, true);
                    $stationAssignmentsThisSlot->push(['employee' => $winner->employee, 'role' => $role]);
                }

                foreach ($secondaryTaskRoles as $task) {
                    $this->assignSecondaryTask(
                        $schedule,
                        $task,
                        $day,
                        $shiftPattern,
                        $shiftStart,
                        $shiftEnd,
                        $stationAssignmentsThisSlot,
                        $secondaryTaskCounts,
                        $unfilled,
                        $assignedToday,
                    );
                }
            }

            foreach ($poolEmployeeIds->unique() as $employeeId) {
                if ($assignedToday->has($employeeId)) {
                    continue;
                }

                if (! $restDaysByEmployee->has($employeeId)) {
                    $restDaysByEmployee->put($employeeId, collect());
                }

                $restDaysByEmployee->get($employeeId)->push($day->copy());
            }
        }

        return new SuggestionResultData($schedule->fresh(['assignments.employee', 'assignments.line', 'assignments.shiftPattern', 'assignments.role']), $unfilled);
    }

    private function findOrCreateDraftSchedule(SuggestionRequestData $request): Schedule
    {
        $scope = [
            'line_ids' => $request->lineIds,
            'shift_pattern_ids' => $request->shiftPatternIds,
            'role_ids' => $request->roleIds,
        ];

        $schedule = Schedule::query()->where('week_start_date', $request->weekStartDate)->first();

        if ($schedule !== null) {
            if ($schedule->status === Schedule::STATUS_APPROVED) {
                throw new RuntimeException('Cannot regenerate an already-approved schedule; edit it manually instead.');
            }

            $schedule->assignments()->where('source', ShiftAssignment::SOURCE_AUTO_SUGGESTED)->delete();
            $schedule->update(['generated_scope' => $scope]);

            return $schedule;
        }

        return Schedule::create([
            'week_start_date' => $request->weekStartDate,
            'status' => Schedule::STATUS_DRAFT,
            'created_by' => $request->requestedBy,
            'generated_scope' => $scope,
        ]);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function buildCandidatePool(Carbon $day, SchedulingRole $role): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->whereDoesntHave('leaveRequests', function ($q) use ($day) {
                $q->where('status', 'approved')
                    ->where('start_date', '<=', $day->format('Y-m-d'))
                    ->where('end_date', '>=', $day->format('Y-m-d'));
            })
            ->whereHas('schedulingRoles', fn ($q) => $q->where('scheduling_roles.id', $role->id))
            ->get();
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
     * Second pass, per ProjectPlan.md §11a.3c: line-priority first (candidate pool
     * restricted to whoever already holds a station this slot, per the task's attachment
     * mode), then fairness (separate counter from station hours, count-based) - regardless
     * of the schedule's ranking_mode.
     *
     * @param  Collection<int, array{employee: Employee, role: SchedulingRole}>  $stationAssignmentsThisSlot
     * @param  Collection<int, float>  $secondaryTaskCounts
     * @param  Collection<int, UnfilledSlotData>  $unfilled
     * @param  Collection<int, bool>  $assignedToday  employee_id => true for anyone already
     *   given any assignment today - a secondary task also counts as "worked today" for the
     *   consecutive-rest-day bookkeeping in generate().
     */
    private function assignSecondaryTask(
        Schedule $schedule,
        SchedulingRole $task,
        Carbon $day,
        ShiftPattern $shiftPattern,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        Collection $stationAssignmentsThisSlot,
        Collection $secondaryTaskCounts,
        Collection $unfilled,
        Collection $assignedToday,
    ): void {
        $candidatePool = match ($task->attachment_type) {
            'station' => $stationAssignmentsThisSlot
                ->filter(fn (array $a) => $a['role']->id === $task->attached_station_role_id)
                ->pluck('employee'),
            'line' => $stationAssignmentsThisSlot
                ->filter(fn (array $a) => $a['role']->line_id === $task->line_id)
                ->pluck('employee'),
            default => $stationAssignmentsThisSlot->pluck('employee'),
        };

        $qualifiedEmployeeIds = $task->qualifiedEmployees()->pluck('employees.id')->all();
        $qualifiedPool = $candidatePool->filter(fn (Employee $e) => in_array($e->id, $qualifiedEmployeeIds, true));

        if ($qualifiedPool->isEmpty()) {
            $unfilled->push(new UnfilledSlotData(
                line: $task->line ?? $stationAssignmentsThisSlot->first()['role']->line ?? null,
                shiftPattern: $shiftPattern,
                workDate: $day->copy(),
                role: $task,
                blocking: false,
                alternatives: collect(),
            ));

            return;
        }

        $winner = $qualifiedPool->sortBy(fn (Employee $e) => $secondaryTaskCounts->get($e->id, 0))->first();

        $this->shiftAssignmentService->create($schedule, new ShiftAssignmentData(
            employeeId: $winner->id,
            lineId: $task->line_id ?? $stationAssignmentsThisSlot->first()['role']->line_id,
            shiftPatternId: $shiftPattern->id,
            workDate: $day->format('Y-m-d'),
            roleId: $task->id,
            startsAt: null,
            endsAt: null,
            status: ShiftAssignment::STATUS_PROPOSED,
            source: ShiftAssignment::SOURCE_AUTO_SUGGESTED,
            notes: null,
        ));

        $secondaryTaskCounts->put($winner->id, (float) $secondaryTaskCounts->get($winner->id, 0) + 1);
        $assignedToday->put($winner->id, true);
    }
}
