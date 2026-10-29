<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Lines\Models\ShiftPatternGroup;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\Services\EmployeeFairnessScoreService;
use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Realistic (not pixel-exact) 8-week historical schedule, weeks 31-39 (2026-07-26 through
 * 2026-09-26), reflecting the shape of the real paper rooster: which employees work which
 * line/station, the range of shift start times actually observed (0/2/6/7/8/10/16/18/19),
 * a weekly rest-day pattern, and periodic Vakantie/Ziek leave. Cell-for-cell values are NOT
 * a transcription of the photographed roster - they're generated to be structurally
 * realistic and immediately usable for testing, per the confirmed approach.
 *
 * Every week is created as a single already-`approved` Schedule per line's roster week (one
 * Schedule per week covering all three lines' assignments, matching how the paper rooster
 * itself groups Leiding+Lijn1+Lijn2 on one sheet and Lijn3 on another but represents one
 * production week).
 */
class BakeryXHistoricalRosterSeeder extends Seeder
{
    private const WEEK_START_DATES = [
        '2026-07-26', // Week 31
        '2026-08-02', // Week 32
        '2026-08-09', // Week 33
        '2026-08-16', // Week 34
        '2026-08-23', // Week 35
        '2026-08-30', // Week 36
        '2026-09-06', // Week 37
        '2026-09-13', // Week 38
        '2026-09-20', // Week 39
    ];

    /** Start times actually observed on the paper rooster (Begintijd column values). */
    private const START_TIMES = ['06:00', '07:00', '08:00', '10:00', '16:00', '18:00', '19:00'];

    private const NIGHT_START_TIMES = ['00:00', '02:00'];

    private User $systemActor;

    /** @var Collection<string, ShiftPattern> Pattern A's rows keyed by slot_type. */
    private Collection $patternsBySlotType;

    private EmployeeFairnessScoreService $fairnessScores;

    public function run(): void
    {
        $this->fairnessScores = app(EmployeeFairnessScoreService::class);

        // The real paper rooster lays weeks out Zondag-first (confirmed) - the company-wide
        // week-start-day setting is aligned to match so the dashboard/planning calendar
        // reads consistently against this historical data.
        CompanySetting::current()->update(['week_start_day' => CompanySetting::WEEK_START_SUNDAY]);

        $this->createShiftPatternGroups();

        $this->systemActor = User::query()->where('email', 'menno@bakeryx.test')->firstOrFail();
        $patternAGroup = ShiftPatternGroup::where('name', 'Pattern A')->firstOrFail();
        $this->patternsBySlotType = ShiftPattern::query()
            ->where('shift_pattern_group_id', $patternAGroup->id)
            ->get()
            ->keyBy('slot_type');

        $lijn1 = Line::where('code', 'LIJN1')->firstOrFail();
        $lijn2 = Line::where('code', 'LIJN2')->firstOrFail();
        $lijn3 = Line::where('code', 'LIJN3')->firstOrFail();

        // Every line defaults to Pattern A - the historical shift start times generated
        // below (deriveShiftTimes) are independent, manual overrides per assignment, but
        // the line itself still needs a concrete group assigned (Company Settings/Lines
        // pages read from this).
        foreach ([$lijn1, $lijn2, $lijn3] as $line) {
            $line->update(['shift_pattern_group_id' => $patternAGroup->id]);
        }

        $lijn1Roles = SchedulingRole::where('line_id', $lijn1->id)->where('role_kind', SchedulingRole::KIND_STATION)->get();
        $lijn1Tasks = SchedulingRole::where('line_id', $lijn1->id)->where('role_kind', SchedulingRole::KIND_SECONDARY_TASK)->get();
        $lijn2Roles = SchedulingRole::where('line_id', $lijn2->id)->where('role_kind', SchedulingRole::KIND_STATION)->get();
        $lijn3Roles = SchedulingRole::where('line_id', $lijn3->id)->where('role_kind', SchedulingRole::KIND_STATION)->get();
        $leidingTasks = SchedulingRole::whereNull('line_id')->get();

        $leidingEmployees = Employee::where('last_name', 'Leiding')->orderBy('first_name')->get();
        $lijn1Employees = Employee::where('last_name', 'Lijn1')->orderBy('first_name')->get();
        $lijn2Employees = Employee::where('last_name', 'Lijn2')->orderBy('first_name')->get();
        $lijn3Employees = Employee::where('default_line_id', $lijn3->id)->orderBy('first_name')->get();

        foreach (self::WEEK_START_DATES as $weekIndex => $weekStartDate) {
            $schedule = Schedule::create([
                'week_start_date' => $weekStartDate,
                'label' => 'Week '.Carbon::parse($weekStartDate)->addDays(3)->isoWeek(),
                'status' => Schedule::STATUS_APPROVED,
                'created_by' => $this->systemActor->id,
                'approved_by' => $this->systemActor->id,
                'approved_at' => Carbon::parse($weekStartDate)->addDay(),
                'was_approved_before' => true,
            ]);

            $this->scheduleGroup($schedule, $weekStartDate, $weekIndex, $leidingEmployees, $leidingTasks, $lijn1->id);
            $this->scheduleGroup($schedule, $weekStartDate, $weekIndex, $lijn1Employees, $lijn1Roles, $lijn1->id, secondaryTasks: $lijn1Tasks);
            $this->scheduleGroup($schedule, $weekStartDate, $weekIndex, $lijn2Employees, $lijn2Roles, $lijn2->id);
            $this->scheduleGroup($schedule, $weekStartDate, $weekIndex, $lijn3Employees, $lijn3Roles, $lijn3->id);

            // Backfills the cumulative fairness score for this historical week - it was
            // written as already-`approved` directly, bypassing ScheduleService::approve()
            // (which is where this normally gets triggered), so the suggestion engine's
            // fairness ranking has real history to work with immediately.
            $this->fairnessScores->recordForApprovedSchedule($schedule);
        }
    }

    /**
     * Guarantees both of the system's two shift-pattern types exist: "Pattern A" (the
     * default every line uses - 08-16/16-00/00-08) and "Pattern B", a second real option
     * with different concrete hours per slot_type, per the confirmed "two shift-pattern
     * types" requirement. Lines keep using Pattern A by default; Pattern B exists as a
     * selectable alternative.
     */
    private function createShiftPatternGroups(): void
    {
        $this->createShiftPatternGroup('Pattern A', [
            ['slot_type' => 'day', 'start_time' => '08:00', 'end_time' => '16:00', 'crosses_midnight' => false],
            ['slot_type' => 'afternoon', 'start_time' => '16:00', 'end_time' => '00:00', 'crosses_midnight' => true],
            ['slot_type' => 'night', 'start_time' => '00:00', 'end_time' => '08:00', 'crosses_midnight' => false],
        ]);

        $this->createShiftPatternGroup('Pattern B', [
            ['slot_type' => 'day', 'start_time' => '06:00', 'end_time' => '14:00', 'crosses_midnight' => false],
            ['slot_type' => 'afternoon', 'start_time' => '14:00', 'end_time' => '22:00', 'crosses_midnight' => false],
            ['slot_type' => 'night', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true],
        ]);
    }

    /**
     * @param  array<int, array{slot_type: string, start_time: string, end_time: string, crosses_midnight: bool}>  $slots
     */
    private function createShiftPatternGroup(string $name, array $slots): ShiftPatternGroup
    {
        $group = ShiftPatternGroup::updateOrCreate(['name' => $name], ['is_active' => true]);

        foreach ($slots as $slot) {
            ShiftPattern::updateOrCreate(
                ['shift_pattern_group_id' => $group->id, 'slot_type' => $slot['slot_type']],
                [
                    'name' => ucfirst($slot['slot_type']).' Shift',
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'crosses_midnight' => $slot['crosses_midnight'],
                    'is_active' => true,
                ],
            );
        }

        return $group;
    }

    /**
     * Builds one week's worth of assignments for one group of employees against one
     * line's roles - each employee gets ~5-6 working days (one rotating rest day, plus an
     * occasional second day off or a full week of Vakantie/Ziek leave) at a start time drawn
     * from the real observed range, with hours derived from that start time.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, SchedulingRole>  $stationRoles
     * @param  ?Collection<int, SchedulingRole>  $secondaryTasks
     */
    private function scheduleGroup(
        Schedule $schedule,
        string $weekStartDate,
        int $weekIndex,
        Collection $employees,
        Collection $stationRoles,
        int $lineId,
        ?Collection $secondaryTasks = null,
    ): void {
        if ($stationRoles->isEmpty() || $employees->isEmpty()) {
            return;
        }

        $weekStart = Carbon::parse($weekStartDate);

        foreach ($employees as $employeeIndex => $employee) {
            // ~1 in 6 employee-weeks is a full week of leave (rotates through the roster so
            // it's never the same people every week) - mirrors the "Vakantie"/"ziek" blocks
            // seen recurring on the real rooster.
            $leaveRoll = ($employeeIndex + $weekIndex) % 6;

            if ($leaveRoll === 0) {
                $this->createWeekLeave($employee, $weekStart, LeaveType::SLUG_VAKANTIE);

                continue;
            }

            if ($leaveRoll === 1 && $weekIndex % 3 === 0) {
                $this->createWeekLeave($employee, $weekStart, LeaveType::SLUG_ZIEK);

                continue;
            }

            // One rotating rest day per employee per week (keeps every day covered across
            // the workforce) - Sunday (offset 0) is the week's first column in this roster.
            $restDayOffset = ($employeeIndex + $weekIndex) % 7;
            $role = $stationRoles[$employeeIndex % $stationRoles->count()];

            foreach (range(0, 6) as $dayOffset) {
                if ($dayOffset === $restDayOffset) {
                    continue;
                }

                $workDate = $weekStart->copy()->addDays($dayOffset);
                $startTime = $this->pickStartTime($employeeIndex, $dayOffset, $weekIndex);
                [$startsAt, $endsAt] = $this->deriveShiftTimes($startTime);
                $shiftPattern = $this->patternsBySlotType->get($this->slotTypeForStartTime($startTime));

                ShiftAssignment::create([
                    'schedule_id' => $schedule->id,
                    'employee_id' => $employee->id,
                    'line_id' => $lineId,
                    'shift_pattern_id' => $shiftPattern->id,
                    'work_date' => $workDate->toDateString(),
                    'role_id' => $role->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => ShiftAssignment::STATUS_CONFIRMED,
                    'source' => ShiftAssignment::SOURCE_MANUAL,
                    'notes' => null,
                ]);

                // A secondary task rides alongside roughly one in four station shifts on
                // this line, attached to whoever is already working that day.
                if ($secondaryTasks !== null && $secondaryTasks->isNotEmpty() && $dayOffset % 4 === 0) {
                    $task = $secondaryTasks[$employeeIndex % $secondaryTasks->count()];

                    ShiftAssignment::create([
                        'schedule_id' => $schedule->id,
                        'employee_id' => $employee->id,
                        'line_id' => $lineId,
                        'shift_pattern_id' => null,
                        'work_date' => $workDate->toDateString(),
                        'role_id' => $task->id,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => ShiftAssignment::STATUS_CONFIRMED,
                        'source' => ShiftAssignment::SOURCE_MANUAL,
                        'notes' => null,
                    ]);
                }
            }
        }
    }

    private function pickStartTime(int $employeeIndex, int $dayOffset, int $weekIndex): string
    {
        // Occasionally draws a night-shift start (00:00/02:00), matching the rare "0,00"/
        // "2,00" entries seen on the rooster; otherwise rotates through the observed
        // daytime/evening start times so the week isn't monotonous.
        $roll = ($employeeIndex * 3 + $dayOffset + $weekIndex) % 9;

        if ($roll === 0) {
            return self::NIGHT_START_TIMES[$employeeIndex % 2];
        }

        return self::START_TIMES[($employeeIndex + $dayOffset) % count(self::START_TIMES)];
    }

    /**
     * Maps an observed start time to Pattern A's slot_type boundaries (day 08-16, afternoon
     * 16-00, night 00-08) - so the ShiftAssignment's shift_pattern_id genuinely matches its
     * starts_at, which the fairness-weighting calculation (day/afternoon/night weight) reads
     * from shift_pattern.slot_type.
     */
    private function slotTypeForStartTime(string $startTime): string
    {
        $hour = (int) explode(':', $startTime)[0];

        return match (true) {
            $hour >= 0 && $hour < 8 => 'night',
            $hour >= 8 && $hour < 16 => 'day',
            default => 'afternoon',
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function deriveShiftTimes(string $startTime): array
    {
        $start = Carbon::parse($startTime);
        $end = $start->copy()->addHours(8);

        // `starts_at`/`ends_at` are plain `time` columns (no date component) - an 8-hour
        // shift crossing midnight (e.g. 22:00 -> 06:00) is represented correctly this way,
        // the same as any other shift_pattern row in this system.
        return [$start->format('H:i:s'), $end->format('H:i:s')];
    }

    private function createWeekLeave(Employee $employee, Carbon $weekStart, string $slug): void
    {
        $leaveType = LeaveType::query()->where('slug', $slug)->firstOrFail();

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekStart->copy()->addDays(6)->toDateString(),
            'status' => LeaveRequest::STATUS_APPROVED,
            'reason' => null,
            'approved_by' => $this->systemActor->id,
        ]);
    }
}
