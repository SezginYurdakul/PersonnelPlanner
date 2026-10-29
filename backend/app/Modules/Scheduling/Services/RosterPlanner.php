<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\CompanySettings\Models\CompanySetting;
use App\Modules\Lines\Contracts\PayRateSurchargeRuleServiceContract;
use App\Modules\Lines\Models\PayRateSurchargeRule;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Scheduling\DTOs\RosterWeekPlanData;
use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\Exceptions\RosterPlannerUnavailableException;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\Rules\MinRestBetweenShiftsRule;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Models\EmployeeUnavailability;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Plans the week's shift leads and station operators with the roster planner service
 * (ProjectPlan.md §28): gathers both groups, their contracts, availability, approved leave,
 * station qualifications, the previous week and recent approved history, solves a multi-week
 * horizon starting at the week being generated, and keeps that first week. Leads and
 * operators are solved together but compared for fairness only within their own group, and
 * nobody covers for anybody: a station no qualified operator can take stays open for the
 * planner to fill by hand. Throws RosterPlannerUnavailableException when the service can't
 * answer - generate then refuses rather than staffing anyone some other way.
 */
final class RosterPlanner
{
    public const LEAD_ROLE = 'shift_lead';

    /**
     * What each fairness count counts; how much each matters is a company setting.
     *
     * @var array<int, array{key: string, types?: array<int, string>, weekdays?: array<int, int>, weight_setting: string}>
     */
    private const FAIRNESS_DIMENSIONS = [
        ['key' => 'night', 'types' => ['night'], 'weight_setting' => 'planner_night_weight'],
        ['key' => 'sunday', 'weekdays' => [7], 'weight_setting' => 'planner_sunday_weight'],
        ['key' => 'saturday', 'weekdays' => [6], 'weight_setting' => 'planner_saturday_weight'],
    ];

    public function __construct(
        private readonly RosterPlannerClient $client,
        private readonly PayRateSurchargeRuleServiceContract $surcharges,
    ) {}

    /**
     * @param  array<int, string>  $slotTypes
     * @param  Collection<int, array{date: string, slot_type: string, start: Carbon, end: Carbon}>  $leadShifts  every
     *                                                                                                           open shift-lead shift of the horizon (CompanySetting::planner_horizon_weeks from $weekStart)
     * @param  Collection<int, array{date: string, slot_type: string, line_id: int, role_id: int, start: Carbon, end: Carbon, min: int, max: int}>  $stationSlots  every
     *                                                                                                                                                             station of the horizon that runs, with its headcount
     *
     * @throws RosterPlannerUnavailableException when the planner can't answer
     */
    public function planWeek(Carbon $weekStart, array $slotTypes, Collection $leadShifts, Collection $stationSlots, string $mode = SuggestionRequestData::RANKING_FAIR): RosterWeekPlanData
    {

        $leadRoleCount = SchedulingRole::query()->where('attachment_type', 'shift_lead')->count();
        $leadShifts = $leadRoleCount > 0 ? $leadShifts : collect();
        $stationRoleIds = $stationSlots->pluck('role_id')->unique()->values();

        $settings = CompanySetting::current();
        $weeks = $settings->planner_horizon_weeks;
        $horizonEnd = $weekStart->copy()->addWeeks($weeks)->subDay();
        $relations = [
            'currentEmploymentTerm',
            'unavailabilities',
            'schedulingRoles',
            'roleExclusions',
            'leaveRequests' => fn ($q) => $q->where('status', 'approved')
                ->where('start_date', '<=', $horizonEnd->format('Y-m-d'))
                ->where('end_date', '>=', $weekStart->format('Y-m-d')),
        ];

        $leads = $leadShifts->isEmpty() ? collect() : Employee::query()
            ->where('is_active', true)
            ->where('is_shift_lead', true)
            ->with($relations)
            ->orderBy('id')
            ->get();

        $operators = $stationSlots->isEmpty() ? collect() : Employee::query()
            ->where('is_active', true)
            ->where('is_shift_lead', false)
            ->whereHas('schedulingRoles', fn ($q) => $q->whereIn('scheduling_roles.id', $stationRoleIds))
            ->with($relations)
            ->orderBy('id')
            ->get();

        if ($leads->isEmpty() && $operators->isEmpty()) {
            return RosterWeekPlanData::empty();
        }

        $employeeIds = $leads->concat($operators)->pluck('id');
        $previousWeek = $this->previousWeekAssignments($weekStart, $employeeIds);

        if ($mode === SuggestionRequestData::RANKING_COST) {
            $leadShifts = $this->withPaidHours($leadShifts);
            $stationSlots = $this->withPaidHours($stationSlots);
        }

        $problem = $this->buildProblem(
            $weekStart,
            $weeks,
            $slotTypes,
            $leads->isEmpty() ? collect() : $leadShifts,
            $leadRoleCount,
            $operators->isEmpty() ? collect() : $stationSlots,
            $leads,
            $operators,
            $this->history($weekStart, $employeeIds),
            $this->previousTypes($previousWeek),
            $this->previousShifts($previousWeek, $weekStart),
            $settings,
            $mode,
        );

        $started = microtime(true);
        $response = $this->client->plan($problem);

        Log::info('Leads and operators planned by the roster planner.', [
            'week_start' => $weekStart->format('Y-m-d'),
            'weeks' => $weeks,
            'leads' => $leads->count(),
            'operators' => $operators->count(),
            'seconds' => round(microtime(true) - $started, 1),
            'status' => $response['status'],
            'summary' => $response['summary'] ?? null,
        ]);

        return RosterWeekPlanData::fromPlannerResponse($response);
    }

    /**
     * The planner's problem JSON (ProjectPlan.md §28.5), from already-loaded data only. Lead
     * shifts carry the role "shift_lead", stations "station:<role id>"; each person holds the
     * roles they may work - every lead the lead role, every operator the stations they're
     * qualified for and not excluded from.
     *
     * @param  array<int, string>  $slotTypes
     * @param  Collection<int, array{date: string, slot_type: string, start: Carbon, end: Carbon}>  $leadShifts
     * @param  Collection<int, array{date: string, slot_type: string, line_id: int, role_id: int, start: Carbon, end: Carbon, min: int, max: int}>  $stationSlots
     * @param  Collection<int, Employee>  $leads  with currentEmploymentTerm, unavailabilities and approved leaveRequests loaded
     * @param  Collection<int, Employee>  $operators  same, plus schedulingRoles and roleExclusions
     * @param  Collection<int, array{hours: float, counts: array<string, int>}>  $history  by employee id
     * @param  Collection<int, string>  $previousTypes  employee_id => last week's shift type
     * @param  Collection<int, Collection<int, array{0: Carbon, 1: Carbon}>>  $previousShifts  employee_id => shifts just before the horizon
     * @param  CompanySetting  $settings  the planner_* business settings
     * @return array<string, mixed>
     */
    public function buildProblem(
        Carbon $weekStart,
        int $weeks,
        array $slotTypes,
        Collection $leadShifts,
        int $leadsPerShift,
        Collection $stationSlots,
        Collection $leads,
        Collection $operators,
        Collection $history,
        Collection $previousTypes,
        Collection $previousShifts,
        CompanySetting $settings,
        string $mode = SuggestionRequestData::RANKING_FAIR,
    ): array {
        $slotTypes = array_values($slotTypes);
        $horizonDates = collect(range(0, $weeks * 7 - 1))->map(fn (int $offset) => $weekStart->copy()->addDays($offset)->format('Y-m-d'));
        $defaultMaxWeeklyHours = (float) config('scheduling_rules.default_max_weekly_hours', 40);
        $stationRoleIds = $stationSlots->pluck('role_id')->unique();

        $person = fn (Employee $employee, string $group, array $roles) => [
            'id' => $employee->id,
            'name' => trim("{$employee->first_name} {$employee->last_name}"),
            'group' => $group,
            'roles' => $roles,
            'min_hours' => (float) ($employee->currentEmploymentTerm?->min_weekly_hours ?? 0),
            'max_hours' => (float) ($employee->currentEmploymentTerm?->max_weekly_hours ?? $defaultMaxWeeklyHours),
            'hourly_cost' => round($employee->effectiveHourlyRate(), 2),
            'unavailable' => $employee->unavailabilities
                ->filter(fn (EmployeeUnavailability $u) => in_array($u->slot_type, $slotTypes, true))
                ->map(fn (EmployeeUnavailability $u) => ['weekday' => $u->day_of_week, 'type' => $u->slot_type])
                ->values()->all(),
            'absences' => $horizonDates
                ->filter(fn (string $date) => $employee->leaveRequests->contains(fn ($leave) => $leave->coversDate(Carbon::parse($date))))
                ->map(fn (string $date) => ['date' => $date])
                ->values()->all(),
            'previous_type' => in_array($previousTypes->get($employee->id), $slotTypes, true) ? $previousTypes->get($employee->id) : null,
            'previous_shifts' => $previousShifts->get($employee->id, collect())
                ->map(fn (array $range) => ['start' => $range[0]->format('Y-m-d\TH:i'), 'end' => $range[1]->format('Y-m-d\TH:i')])
                ->values()->all(),
            // An object, not a list, even when empty: the planner reads counts by key.
            'history' => $history->get($employee->id, ['hours' => 0, 'counts' => (object) []]),
        ];

        $shift = fn (string $id, array $slot, ?string $role, int $min, int $max) => [
            'id' => $id,
            'date' => $slot['date'],
            'type' => $slot['slot_type'],
            'role' => $role,
            'start' => $slot['start']->format('Y-m-d\TH:i'),
            'end' => $slot['end']->format('Y-m-d\TH:i'),
            'min' => $min,
            'max' => $max,
            'paid_hours' => $slot['paid_hours'] ?? null,
        ];

        return [
            'horizon_start' => $weekStart->format('Y-m-d'),
            'weeks' => $weeks,
            'shift_types' => $slotTypes,
            'rotation' => $settings->planner_rotation_enabled ? array_values(array_intersect(config('planner.rotation'), $slotTypes)) : [],
            'rules' => [
                'min_rest_hours' => MinRestBetweenShiftsRule::MIN_REST_HOURS,
                'max_shifts_per_day' => 1,
                'weekend_policy' => $settings->planner_weekend_policy,
                'weekend_weekdays' => config('planner.weekend_weekdays'),
                'mode' => $mode,
            ],
            'fairness' => [
                'dimensions' => $this->fairnessDimensions($slotTypes, $settings),
                'rotation_break_weight' => $settings->planner_rotation_break_weight,
            ],
            'shifts' => $leadShifts
                ->map(fn (array $slot) => $shift(
                    "lead|{$slot['date']}|{$slot['slot_type']}",
                    $slot,
                    self::LEAD_ROLE,
                    $leadsPerShift,
                    max($leadsPerShift, $settings->planner_max_leads_per_shift),
                ))
                ->concat($stationSlots->map(fn (array $slot) => $shift(
                    "station|{$slot['date']}|{$slot['slot_type']}|{$slot['line_id']}|{$slot['role_id']}",
                    $slot,
                    "station:{$slot['role_id']}",
                    $slot['min'],
                    $slot['max'],
                )))
                ->values()->all(),
            'employees' => $leads
                ->map(fn (Employee $lead) => $person($lead, 'leads', [self::LEAD_ROLE]))
                ->concat($operators->map(fn (Employee $operator) => $person(
                    $operator,
                    'operators',
                    $operator->schedulingRoles
                        ->pluck('id')
                        ->filter(fn (int $roleId) => $stationRoleIds->contains($roleId) && ! $operator->isExcludedFromRole($roleId))
                        ->map(fn (int $roleId) => "station:{$roleId}")
                        ->values()->all(),
                )))
                ->values()->all(),
            'solver' => ['stage_seconds' => config('planner.stage_seconds')],
        ];
    }

    /**
     * Adds each shift's paid hours - its hours with the surcharge rules applied, the same
     * calculation cost ranking and reporting use - for cost mode. Without active rules paid
     * hours equal real hours, which the planner assumes anyway.
     *
     * @param  Collection<int, array<string, mixed>>  $slots  with start and end
     * @return Collection<int, array<string, mixed>>
     */
    private function withPaidHours(Collection $slots): Collection
    {
        $rules = PayRateSurchargeRule::query()->where('is_active', true)->get();

        if ($rules->isEmpty()) {
            return $slots;
        }

        $paidHours = [];

        return $slots->map(function (array $slot) use ($rules, &$paidHours) {
            $key = $slot['start']->toDateTimeString().'|'.$slot['end']->toDateTimeString();
            $slot['paid_hours'] = $paidHours[$key] ??= $this->surcharges->computeShiftCost(1.0, $slot['start'], $slot['end'], $rules);

            return $slot;
        });
    }

    /**
     * The fairness dimensions with their weights from the settings, limited to the slot
     * types in scope - a dimension left with no slot type to count is dropped.
     *
     * @param  array<int, string>  $slotTypes
     * @return array<int, array<string, mixed>>
     */
    private function fairnessDimensions(array $slotTypes, CompanySetting $settings): array
    {
        return collect(self::FAIRNESS_DIMENSIONS)
            ->map(function (array $dimension) use ($slotTypes, $settings) {
                $dimension['weight'] = (int) $settings->{$dimension['weight_setting']};
                unset($dimension['weight_setting']);

                if (isset($dimension['types'])) {
                    $dimension['types'] = array_values(array_intersect($dimension['types'], $slotTypes));
                }

                return $dimension;
            })
            ->reject(fn (array $dimension) => isset($dimension['types']) && $dimension['types'] === [])
            ->values()
            ->all();
    }

    /**
     * Everyone's assignments in the week right before this one (the latest schedule for it,
     * whatever its status - planning runs week by week, so last week's draft is what this
     * week's rotation follows).
     *
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int, ShiftAssignment>
     */
    private function previousWeekAssignments(Carbon $weekStart, Collection $employeeIds): Collection
    {
        $previousSchedule = Schedule::query()
            ->whereDate('week_start_date', $weekStart->copy()->subWeek()->format('Y-m-d'))
            ->orderByDesc('id')
            ->first();

        if ($previousSchedule === null) {
            return collect();
        }

        return ShiftAssignment::query()
            ->where('schedule_id', $previousSchedule->id)
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('shift_pattern_id')
            ->with('shiftPattern')
            ->get();
    }

    /**
     * Each person's most-worked shift type last week; a tie leaves them without one.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<int, string>
     */
    private function previousTypes(Collection $assignments): Collection
    {
        return $assignments->groupBy('employee_id')->map(function (Collection $own) {
            $counts = $own->unique(fn (ShiftAssignment $a) => $a->work_date->format('Y-m-d'))
                ->countBy(fn (ShiftAssignment $a) => $a->shiftPattern->slot_type)
                ->sortDesc();

            return $counts->count() > 1 && $counts->values()[0] === $counts->values()[1] ? null : $counts->keys()->first();
        })->filter();
    }

    /**
     * Last week's shifts on its final two days, so the minimum rest holds across the
     * week boundary.
     *
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<int, Collection<int, array{0: Carbon, 1: Carbon}>>
     */
    private function previousShifts(Collection $assignments, Carbon $weekStart): Collection
    {
        $from = $weekStart->copy()->subDays(2);

        return $assignments
            ->filter(fn (ShiftAssignment $a) => $a->work_date->greaterThanOrEqualTo($from))
            ->groupBy('employee_id')
            ->map(fn (Collection $own) => $own->map(fn (ShiftAssignment $a) => self::timeRange($a))->values());
    }

    /**
     * Shift counts per fairness dimension, and hours, over the recent approved weeks - one
     * per worked shift (a second station or a secondary task in the same shift isn't
     * counted again).
     *
     * @param  Collection<int, int>  $employeeIds
     * @return Collection<int, array{hours: float, counts: array<string, int>}>
     */
    private function history(Carbon $weekStart, Collection $employeeIds): Collection
    {
        $approvedScheduleIds = Schedule::query()
            ->where('status', Schedule::STATUS_APPROVED)
            ->whereDate('week_start_date', '>=', $weekStart->copy()->subWeeks((int) config('planner.history_weeks'))->format('Y-m-d'))
            ->whereDate('week_start_date', '<', $weekStart->format('Y-m-d'))
            ->pluck('id');

        $dimensions = collect(self::FAIRNESS_DIMENSIONS);

        return ShiftAssignment::query()
            ->whereIn('schedule_id', $approvedScheduleIds)
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('shift_pattern_id')
            ->with('shiftPattern')
            ->get()
            ->groupBy('employee_id')
            ->map(function (Collection $own) use ($dimensions) {
                $shifts = $own->unique(fn (ShiftAssignment $a) => $a->work_date->format('Y-m-d').'|'.$a->shiftPattern->slot_type);

                return [
                    'hours' => round($shifts->sum(function (ShiftAssignment $a) {
                        [$start, $end] = self::timeRange($a);

                        return $start->floatDiffInHours($end);
                    }), 2),
                    'counts' => $dimensions->mapWithKeys(fn (array $dimension) => [
                        $dimension['key'] => $shifts->filter(
                            fn (ShiftAssignment $a) => (! isset($dimension['types']) || in_array($a->shiftPattern->slot_type, $dimension['types'], true))
                                && (! isset($dimension['weekdays']) || in_array($a->work_date->dayOfWeekIso, $dimension['weekdays'], true))
                        )->count(),
                    ])->all(),
                ];
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function timeRange(ShiftAssignment $assignment): array
    {
        $date = $assignment->work_date->format('Y-m-d');
        $start = Carbon::parse("{$date} {$assignment->effectiveStart()}");
        $end = Carbon::parse("{$date} {$assignment->effectiveEnd()}");

        if ($assignment->effectiveCrossesMidnight()) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
