<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Scheduling\Contracts\ShiftAssignmentServiceContract;
use App\Modules\Scheduling\DTOs\MoveAssignmentData;
use App\Modules\Scheduling\DTOs\ShiftAssignmentData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\RuleEngine;
use App\Modules\Staff\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Owns the ProjectPlan.md §17A.8 application-layer checks (station overlap exclusivity,
 * role/line consistency, secondary-task-requires-station) and re-runs the RuleEngine on
 * every mutation so the violation panel is always current (§12.3).
 */
final class ShiftAssignmentService implements ShiftAssignmentServiceContract
{
    public function __construct(private readonly RuleEngine $ruleEngine)
    {
    }

    public function create(Schedule $schedule, ShiftAssignmentData $data): array
    {
        $employee = Employee::query()->findOrFail($data->employeeId);
        $role = $data->roleId !== null ? SchedulingRole::query()->findOrFail($data->roleId) : null;

        $this->assertRoleLineConsistency($data->lineId, $role);

        $start = $data->startsAt;
        $end = $data->endsAt;
        $crossesMidnight = false;

        if ($role?->isStation()) {
            $this->assertNoStationOverlap($employee, $data->workDate, $start, $end, $crossesMidnight, null);
        } elseif ($role?->isSecondaryTask()) {
            $this->assertSecondaryTaskHasStation($employee, $schedule, $data->workDate, $start, $end);
        }

        $assignment = ShiftAssignment::create([
            ...$data->toArray(),
            'schedule_id' => $schedule->id,
        ]);

        return $this->withRuleResults($assignment->refresh());
    }

    public function update(ShiftAssignment $assignment, ShiftAssignmentData $data): array
    {
        $employee = Employee::query()->findOrFail($data->employeeId);
        $role = $data->roleId !== null ? SchedulingRole::query()->findOrFail($data->roleId) : null;

        $this->assertRoleLineConsistency($data->lineId, $role);

        if ($role?->isStation()) {
            $this->assertNoStationOverlap(
                $employee,
                $data->workDate,
                $data->startsAt,
                $data->endsAt,
                false,
                $assignment->id,
            );
        } elseif ($role?->isSecondaryTask()) {
            $this->assertSecondaryTaskHasStation($employee, $assignment->schedule, $data->workDate, $data->startsAt, $data->endsAt);
        }

        $assignment->update($data->toArray());

        return $this->withRuleResults($assignment->refresh());
    }

    public function move(ShiftAssignment $assignment, MoveAssignmentData $data): array
    {
        $role = $assignment->role;

        $this->assertRoleLineConsistency($data->lineId, $role);

        if ($role?->isStation()) {
            $this->assertNoStationOverlap(
                $assignment->employee,
                Carbon::parse($data->workDate),
                $assignment->starts_at,
                $assignment->ends_at,
                $assignment->effectiveCrossesMidnight(),
                $assignment->id,
            );
        } elseif ($role?->isSecondaryTask()) {
            $this->assertSecondaryTaskHasStation(
                $assignment->employee,
                $assignment->schedule,
                Carbon::parse($data->workDate),
                $assignment->starts_at,
                $assignment->ends_at,
            );
        }

        $assignment->update([
            'line_id' => $data->lineId,
            'shift_pattern_id' => $data->shiftPatternId,
            'work_date' => $data->workDate,
            'source' => ShiftAssignment::SOURCE_MANUAL,
        ]);

        return $this->withRuleResults($assignment->refresh());
    }

    public function delete(ShiftAssignment $assignment): void
    {
        $assignment->delete();
    }

    /**
     * @return array{assignment: ShiftAssignment, rule_results: Collection<int, \App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult>}
     */
    private function withRuleResults(ShiftAssignment $assignment): array
    {
        $employee = $assignment->employee;
        $maxWeeklyHours = (float) ($employee->currentEmploymentTerm?->max_weekly_hours
            ?? config('scheduling_rules.default_max_weekly_hours'));

        $existingAssignments = ShiftAssignment::query()
            ->where('schedule_id', $assignment->schedule_id)
            ->where('employee_id', $employee->id)
            ->where('id', '!=', $assignment->id)
            ->with('shiftPattern')
            ->get();

        $start = Carbon::parse($assignment->work_date->format('Y-m-d').' '.$assignment->effectiveStart());
        $end = Carbon::parse($assignment->work_date->format('Y-m-d').' '.$assignment->effectiveEnd());
        if ($assignment->effectiveCrossesMidnight()) {
            $end->addDay();
        }

        $context = new EmployeeScheduleContext(
            employee: $employee,
            candidateStart: $start,
            candidateEnd: $end,
            existingAssignmentsThisWeek: $existingAssignments,
            maxWeeklyHours: $maxWeeklyHours,
        );

        return [
            'assignment' => $assignment,
            'rule_results' => $this->ruleEngine->run($context),
        ];
    }

    private function assertRoleLineConsistency(int $lineId, ?SchedulingRole $role): void
    {
        if ($role !== null && $role->line_id !== null && $role->line_id !== $lineId) {
            throw ValidationException::withMessages([
                'role_id' => 'This role belongs to a different line than the assignment.',
            ]);
        }
    }

    private function assertNoStationOverlap(
        Employee $employee,
        Carbon|string $workDate,
        ?string $start,
        ?string $end,
        bool $crossesMidnight,
        ?int $excludingAssignmentId,
    ): void {
        $workDate = $workDate instanceof Carbon ? $workDate->format('Y-m-d') : $workDate;

        $candidates = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate)
            ->when($excludingAssignmentId, fn ($q) => $q->where('id', '!=', $excludingAssignmentId))
            ->whereHas('role', fn ($q) => $q->where('role_kind', SchedulingRole::KIND_STATION))
            ->with(['shiftPattern', 'role'])
            ->get();

        $effectiveStart = $start;
        $effectiveEnd = $end;

        foreach ($candidates as $candidate) {
            if ($effectiveStart !== null && $effectiveEnd !== null
                && $candidate->overlapsTimeRange($effectiveStart, $effectiveEnd, $crossesMidnight)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This employee already holds an overlapping station assignment on this day.',
                ]);
            }
        }
    }

    private function assertSecondaryTaskHasStation(
        Employee $employee,
        Schedule $schedule,
        Carbon|string $workDate,
        ?string $start,
        ?string $end,
    ): void {
        $workDate = $workDate instanceof Carbon ? $workDate->format('Y-m-d') : $workDate;

        $stationAssignments = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate)
            ->whereHas('role', fn ($q) => $q->where('role_kind', SchedulingRole::KIND_STATION))
            ->with('shiftPattern')
            ->get();

        if ($start === null || $end === null) {
            if ($stationAssignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'role_id' => 'A secondary task requires an overlapping station assignment for the same employee and day.',
                ]);
            }

            return;
        }

        $hasOverlap = $stationAssignments->contains(
            fn (ShiftAssignment $station): bool => $station->overlapsTimeRange($start, $end, false)
        );

        if (! $hasOverlap) {
            throw ValidationException::withMessages([
                'role_id' => 'A secondary task requires an overlapping station assignment for the same employee and day.',
            ]);
        }
    }
}
