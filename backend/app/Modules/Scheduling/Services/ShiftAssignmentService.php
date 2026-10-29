<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Notifications\Services\NotificationDispatchService;
use App\Modules\Scheduling\Contracts\ShiftAssignmentServiceContract;
use App\Modules\Scheduling\DTOs\MoveAssignmentData;
use App\Modules\Scheduling\DTOs\ShiftAssignmentData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\RuleEngine;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;
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
    public function __construct(
        private readonly RuleEngine $ruleEngine,
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function create(Schedule $schedule, ShiftAssignmentData $data): array
    {
        $this->assertScheduleIsEditable($schedule);

        $employee = Employee::query()->findOrFail($data->employeeId);
        $role = $data->roleId !== null ? SchedulingRole::query()->findOrFail($data->roleId) : null;

        $this->assertRoleLineConsistency($data->lineId, $role);

        $start = $data->startsAt;
        $end = $data->endsAt;
        $crossesMidnight = false;

        if ($role?->isStation()) {
            $this->assertNoStationOverlap($employee, $data->workDate, $start, $end, $crossesMidnight, null);
        } elseif ($role?->attachment_type === 'flexible') {
            $this->assertNoFlexibleOverlap($employee, $data->workDate, $start, $end, null);
        } elseif ($role?->isSecondaryTask() && ! in_array($role->attachment_type, ['line', 'shift_lead', 'flexible'], true)) {
            $this->assertSecondaryTaskHasStation($employee, $schedule, $data->workDate, $start, $end);
        }

        $assignment = ShiftAssignment::create([
            ...$data->toArray(),
            'schedule_id' => $schedule->id,
        ]);

        $this->notifications->notifyAssignmentChanged($assignment);

        return $this->withRuleResults($assignment->refresh());
    }

    public function update(ShiftAssignment $assignment, ShiftAssignmentData $data): array
    {
        $this->assertScheduleIsEditable($assignment->schedule);

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
        } elseif ($role?->attachment_type === 'flexible') {
            $this->assertNoFlexibleOverlap($employee, $data->workDate, $data->startsAt, $data->endsAt, $assignment->id);
        } elseif ($role?->isSecondaryTask() && ! in_array($role->attachment_type, ['line', 'shift_lead', 'flexible'], true)) {
            $this->assertSecondaryTaskHasStation($employee, $assignment->schedule, $data->workDate, $data->startsAt, $data->endsAt);
        }

        $assignment->update($data->toArray());
        $this->notifications->notifyAssignmentChanged($assignment);

        return $this->withRuleResults($assignment->refresh());
    }

    public function move(ShiftAssignment $assignment, MoveAssignmentData $data): array
    {
        $this->assertScheduleIsEditable($assignment->schedule);

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
        } elseif ($role?->attachment_type === 'flexible') {
            $this->assertNoFlexibleOverlap(
                $assignment->employee,
                Carbon::parse($data->workDate),
                $assignment->starts_at,
                $assignment->ends_at,
                $assignment->id,
            );
        } elseif ($role?->isSecondaryTask() && ! in_array($role->attachment_type, ['line', 'shift_lead', 'flexible'], true)) {
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
        $this->notifications->notifyAssignmentChanged($assignment);

        return $this->withRuleResults($assignment->refresh());
    }

    public function delete(ShiftAssignment $assignment): void
    {
        $this->assertScheduleIsEditable($assignment->schedule);

        // Capture employee/approved-state before the row is removed - both are gone from
        // the model afterward (ProjectPlan.md §8h's dispatch-hook note on delete()).
        $this->notifications->notifyAssignmentChanged($assignment);

        $assignment->delete();
    }

    /**
     * Manual drag-and-drop editing is only ever allowed on a draft schedule - locked while
     * `proposed` (awaiting super_admin review) or `approved` (a super_admin must unlock it
     * back to draft first, per the approved-plan review policy).
     */
    private function assertScheduleIsEditable(Schedule $schedule): void
    {
        if (! $schedule->isEditableByAdmin()) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule is locked and cannot be edited in its current status.',
            ]);
        }
    }

    /**
     * @return array{assignment: ShiftAssignment, rule_results: Collection<int, RuleResult>}
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

        // Deliberately independent of schedule_id - MinRestBetweenShiftsRule must catch a
        // rest-gap violation even when the adjacent shift lives in a different week's
        // Schedule row.
        $adjacentAssignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('id', '!=', $assignment->id)
            ->whereBetween('work_date', [
                $start->copy()->subDay()->startOfDay(),
                $end->copy()->addDay()->endOfDay(),
            ])
            ->with('shiftPattern')
            ->get();

        $context = new EmployeeScheduleContext(
            employee: $employee,
            candidateStart: $start,
            candidateEnd: $end,
            existingAssignmentsThisWeek: $existingAssignments,
            maxWeeklyHours: $maxWeeklyHours,
            adjacentAssignments: $adjacentAssignments,
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

    /**
     * A flexible-hours role (e.g. Production Lead) works arbitrary, day-specific hours with
     * no fixed shift pattern - its only overlap concern is against ANOTHER flexible
     * assignment for the same employee/day (two conflicting free time ranges), never
     * against a station: a Production Lead's flexible hours are allowed to coexist with a
     * station shift on the same day without conflict, per the confirmed design.
     */
    private function assertNoFlexibleOverlap(
        Employee $employee,
        Carbon|string $workDate,
        ?string $start,
        ?string $end,
        ?int $excludingAssignmentId,
    ): void {
        $workDate = $workDate instanceof Carbon ? $workDate->format('Y-m-d') : $workDate;

        $candidates = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate)
            ->when($excludingAssignmentId, fn ($q) => $q->where('id', '!=', $excludingAssignmentId))
            ->whereHas('role', fn ($q) => $q->where('attachment_type', 'flexible'))
            ->with(['shiftPattern', 'role'])
            ->get();

        foreach ($candidates as $candidate) {
            if ($start !== null && $end !== null && $candidate->overlapsTimeRange($start, $end, false)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'This employee already holds an overlapping flexible-hours assignment on this day.',
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
            // A Shift Lead placement (attachment_type=shift_lead) counts as "present this
            // shift" the same way a station assignment does - a shift lead is never given a
            // station, so requiring one here would make them permanently ineligible for
            // every other line-independent/line-attached secondary task (e.g. "Silo
            // Takibi") despite decision #6 confirming they should be a normal candidate for
            // those once placed.
            ->whereHas('role', fn ($q) => $q->where('role_kind', SchedulingRole::KIND_STATION)
                ->orWhere('attachment_type', 'shift_lead'))
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
