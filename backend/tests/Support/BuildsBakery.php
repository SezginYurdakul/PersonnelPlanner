<?php

namespace Tests\Support;

use App\Models\User;
use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Lines\Models\ShiftPatternGroup;
use App\Modules\Scheduling\Contracts\ScheduleSuggestionServiceContract;
use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\Services\EffectiveWorkedHoursCalculator;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmployeeUnavailability;
use App\Modules\Staff\Models\EmploymentTerm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A small bakery shaped like the real one: two lines (Line 2's shifts start two hours later),
 * two stations on Line 1, an allows_second_person machine on Line 2, a company-wide shift
 * lead role and a secondary task; six 40-hour leads (one day-only, one never on Mondays),
 * eight 40-hour operators and a 16-hour part-timer. The week runs Sunday to Friday with
 * three shifts and Saturday with only the night: 19 lead shifts and 57 station shifts
 * against 42 operator shifts, so at best 15 stations stay open.
 */
trait BuildsBakery
{
    /** @var array<string, Line> */
    protected array $lines = [];

    /** @var array<string, SchedulingRole> */
    protected array $roles = [];

    /** @var array<string, Employee> */
    protected array $people = [];

    protected function buildBakery(): void
    {
        CompanySetting::current()->update(['week_start_day' => CompanySetting::WEEK_START_SUNDAY]);

        $this->lines['l1'] = $this->line('L1', 'Line 1', ['night' => ['00:00', '08:00'], 'day' => ['08:00', '16:00'], 'afternoon' => ['16:00', '00:00']]);
        $this->lines['l2'] = $this->line('L2', 'Line 2', ['night' => ['02:00', '10:00'], 'day' => ['10:00', '18:00'], 'afternoon' => ['18:00', '02:00']]);

        $this->roles['lead'] = SchedulingRole::create(['name' => 'Shift lead', 'role_kind' => SchedulingRole::KIND_SECONDARY_TASK, 'attachment_type' => 'shift_lead', 'requires_coverage' => true, 'min_headcount' => 1, 'is_active' => true]);
        $this->roles['dough'] = $this->station($this->lines['l1'], 'Dough');
        $this->roles['oven'] = $this->station($this->lines['l1'], 'Oven');
        $this->roles['machine'] = $this->station($this->lines['l2'], 'Machine', allowsSecondPerson: true);
        $this->roles['silo'] = SchedulingRole::create(['name' => 'Silo watch', 'role_kind' => SchedulingRole::KIND_SECONDARY_TASK, 'attachment_type' => 'none', 'requires_coverage' => false, 'min_headcount' => 1, 'is_active' => true]);

        $dayOnly = [];
        foreach (range(1, 7) as $weekday) {
            $dayOnly[] = [$weekday, 'afternoon'];
            $dayOnly[] = [$weekday, 'night'];
        }

        foreach (['Erdal' => $dayOnly, 'Sander' => [[1, 'day'], [1, 'afternoon'], [1, 'night']], 'Winfred' => [], 'Marc' => [], 'Kars' => [], 'Mark' => []] as $name => $unavailable) {
            $this->people[$name] = $this->person($name, 40, lead: true, unavailable: $unavailable, roles: ['silo']);
        }

        $all = ['dough', 'oven', 'machine'];
        foreach (['Ali' => $all, 'David' => $all, 'Jaco' => ['dough', 'oven'], 'Kevin' => ['dough', 'oven'], 'Luka' => $all, 'Ruud' => ['oven', 'machine'], 'Sezgin' => $all, 'Karlos' => ['machine']] as $name => $stations) {
            $this->people[$name] = $this->person($name, 40, roles: [...$stations, 'silo']);
        }
        $this->people['Heinrick'] = $this->person('Heinrick', 16, roles: ['dough']);
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $hours  slot type => [start, end]
     */
    private function line(string $code, string $name, array $hours): Line
    {
        $group = ShiftPatternGroup::create(['name' => "{$name} hours", 'is_active' => true]);

        foreach ($hours as $slotType => [$start, $end]) {
            ShiftPattern::create([
                'shift_pattern_group_id' => $group->id,
                'slot_type' => $slotType,
                'name' => ucfirst($slotType),
                'start_time' => $start,
                'end_time' => $end,
                'crosses_midnight' => $end <= $start,
                'is_active' => true,
            ]);
        }

        return Line::create(['code' => $code, 'name' => $name, 'shift_pattern_group_id' => $group->id, 'is_active' => true]);
    }

    private function station(Line $line, string $name, bool $allowsSecondPerson = false): SchedulingRole
    {
        return SchedulingRole::create([
            'name' => "{$line->name} {$name}",
            'line_id' => $line->id,
            'role_kind' => SchedulingRole::KIND_STATION,
            'requires_coverage' => true,
            'min_headcount' => 1,
            'allows_second_person' => $allowsSecondPerson,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array{0: int, 1: string}>  $unavailable  [ISO weekday, slot type]
     * @param  array<int, string>  $roles  keys of $this->roles
     */
    private function person(string $name, int $hours, bool $lead = false, array $unavailable = [], array $roles = []): Employee
    {
        $employee = Employee::create([
            'first_name' => $name,
            'last_name' => 'Test',
            'employee_type' => 'vast',
            'pay_type' => 'hourly',
            'hourly_rate' => $lead ? 24 : 20,
            'contracted_hours_per_week' => $hours,
            'is_active' => true,
            'is_shift_lead' => $lead,
        ]);
        EmploymentTerm::create(['employee_id' => $employee->id, 'min_weekly_hours' => $hours, 'max_weekly_hours' => $hours, 'effective_from' => '2026-01-01']);

        foreach ($unavailable as [$weekday, $slotType]) {
            EmployeeUnavailability::create(['employee_id' => $employee->id, 'day_of_week' => $weekday, 'slot_type' => $slotType]);
        }

        $employee->schedulingRoles()->sync(collect($roles)->map(fn (string $key) => $this->roles[$key]->id));

        return $employee;
    }

    protected function generateWeek(string $weekStart, string $mode = SuggestionRequestData::RANKING_FAIR, ?int $scheduleId = null): Schedule
    {
        $exclusions = collect($this->lines)->flatMap(fn (Line $line) => [
            ['line_id' => $line->id, 'day_of_week' => 6, 'slot_type' => 'day'],
            ['line_id' => $line->id, 'day_of_week' => 6, 'slot_type' => 'afternoon'],
        ])->all();

        return app(ScheduleSuggestionServiceContract::class)->generate(new SuggestionRequestData(
            $weekStart,
            collect($this->lines)->pluck('id')->all(),
            ['day', 'afternoon', 'night'],
            null,
            $mode,
            User::factory()->create()->id,
            $scheduleId,
            $exclusions,
        ));
    }

    /**
     * Every hard rule, re-checked from the stored assignments alone: weekly hours, 11 hours of
     * rest between separate shifts, one station at a time, station qualification, recurring
     * unavailability, and no shift lead on a station.
     */
    protected function assertValidSchedule(Schedule $schedule): void
    {
        $assignments = ShiftAssignment::query()->where('schedule_id', $schedule->id)->with(['employee.currentEmploymentTerm', 'employee.unavailabilities', 'employee.schedulingRoles', 'role', 'shiftPattern'])->get();
        $calculator = app(EffectiveWorkedHoursCalculator::class);

        foreach ($assignments->groupBy('employee_id') as $own) {
            $employee = $own->first()->employee;
            $name = $employee->first_name;
            $max = (float) $employee->currentEmploymentTerm->max_weekly_hours;
            $this->assertLessThanOrEqual($max, $calculator->totalHours($own), "{$name} is over max hours");

            $stations = $own->filter(fn (ShiftAssignment $a) => $a->role->isStation());
            foreach ($stations as $a) {
                $this->assertFalse($employee->is_shift_lead, "lead {$name} was put on a station");
                $this->assertTrue($employee->schedulingRoles->contains('id', $a->role_id), "{$name} is not qualified for {$a->role->name}");
                $overlapping = $stations->filter(fn (ShiftAssignment $b) => $b->id !== $a->id && $b->work_date->equalTo($a->work_date) && $b->overlapsTimeRange($a->effectiveStart(), $a->effectiveEnd(), $a->effectiveCrossesMidnight()));
                $this->assertCount(0, $overlapping, "{$name} holds two stations at once on {$a->work_date->format('D')}");
            }

            foreach ($own as $a) {
                $this->assertFalse($employee->isUnavailable($a->work_date->dayOfWeekIso, $a->shiftPattern->slot_type), "{$name} works an unavailable {$a->shiftPattern->slot_type} on {$a->work_date->format('D')}");
            }

            $shifts = $this->mergedShifts($own);
            for ($i = 1; $i < $shifts->count(); $i++) {
                $rest = $shifts[$i - 1][1]->floatDiffInHours($shifts[$i][0]);
                $this->assertGreaterThanOrEqual(11, $rest, "{$name} rests only {$rest}h before {$shifts[$i][0]->format('D H:i')}");
            }
        }
    }

    /**
     * An employee's time ranges, with ranges that overlap (a lead shift plus a secondary task
     * in it) merged into one shift, in start order.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<int, array{0: Carbon, 1: Carbon}>
     */
    private function mergedShifts(Collection $assignments): Collection
    {
        $ranges = $assignments->map(function (ShiftAssignment $a) {
            $date = $a->work_date->format('Y-m-d');
            $start = Carbon::parse("{$date} {$a->effectiveStart()}");
            $end = Carbon::parse("{$date} {$a->effectiveEnd()}");

            return [$start, $a->effectiveCrossesMidnight() ? $end->addDay() : $end];
        })->sortBy(fn (array $r) => $r[0]->timestamp)->values();

        $merged = collect();
        foreach ($ranges as $range) {
            $last = $merged->pop();
            if ($last !== null && $range[0]->lt($last[1])) {
                $merged->push([$last[0], $last[1]->max($range[1])]);
            } else {
                if ($last !== null) {
                    $merged->push($last);
                }
                $merged->push($range);
            }
        }

        return $merged;
    }
}
