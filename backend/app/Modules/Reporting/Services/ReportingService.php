<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Lines\Contracts\PayRateSurchargeRuleServiceContract;
use App\Modules\Reporting\Contracts\ReportingServiceContract;
use App\Modules\Reporting\DTOs\CostBreakdownRowData;
use App\Modules\Reporting\DTOs\HeadcountRowData;
use App\Modules\Reporting\DTOs\ReportData;
use App\Modules\Reporting\DTOs\ReportPeriodData;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use App\Modules\TimeAttendance\Models\TimeClockEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the headcount and cost reports (ProjectPlan.md §14) from approved schedules
 * only - a report for a past week must reflect what actually happened, not leftover
 * draft/proposed data (§14.3).
 */
final class ReportingService implements ReportingServiceContract
{
    public function __construct(private readonly PayRateSurchargeRuleServiceContract $payRateSurchargeRuleService) {}

    public function generate(ReportPeriodData $period): ReportData
    {
        $assignments = ShiftAssignment::query()
            ->whereHas('schedule', fn ($q) => $q->where('status', 'approved'))
            ->whereBetween('work_date', [$period->startDate, $period->endDate])
            ->with(['employee.agency', 'line', 'shiftPattern'])
            ->get();

        // One TimeClockEntry query for the whole period, keyed by "employeeId|workDate" -
        // avoids an N+1 lookup per assignment when checking for actual attendance data.
        $timeClockEntries = TimeClockEntry::query()
            ->whereBetween('work_date', [$period->startDate, $period->endDate])
            ->get()
            ->keyBy(fn (TimeClockEntry $entry) => $entry->employee_id.'|'.$entry->work_date->toDateString());

        return new ReportData(
            period: $period,
            headcount: $this->buildHeadcount($assignments),
            costBreakdown: $this->buildCostBreakdown($assignments, $timeClockEntries),
        );
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @return Collection<int, HeadcountRowData>
     */
    private function buildHeadcount(Collection $assignments): Collection
    {
        return $assignments
            ->groupBy(fn (ShiftAssignment $a) => $a->work_date->toDateString().'|'.$a->line_id.'|'.$a->shift_pattern_id)
            ->map(function (Collection $group) {
                /** @var ShiftAssignment $first */
                $first = $group->first();

                return new HeadcountRowData(
                    workDate: $first->work_date->toDateString(),
                    lineId: $first->line_id,
                    lineName: $first->line->name,
                    shiftPatternId: $first->shift_pattern_id,
                    shiftPatternName: $first->shiftPattern?->name ?? '—',
                    headcount: $group->unique('employee_id')->count(),
                );
            })
            ->values()
            ->sortBy([['workDate', 'asc'], ['lineName', 'asc'], ['shiftPatternName', 'asc']])
            ->values();
    }

    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @param  Collection<string, TimeClockEntry>  $timeClockEntries
     * @return Collection<int, CostBreakdownRowData>
     */
    private function buildCostBreakdown(Collection $assignments, Collection $timeClockEntries): Collection
    {
        $hoursAndCostByGroup = $assignments
            ->groupBy(function (ShiftAssignment $a) {
                $employee = $a->employee;

                return $employee->employee_type === 'uitzendkracht'
                    ? 'uitzendkracht|'.($employee->agency?->name ?? '—')
                    : 'vast';
            })
            ->map(function (Collection $group) use ($timeClockEntries) {
                $totalHours = 0.0;
                $totalCost = 0.0;

                foreach ($group as $assignment) {
                    [$hours, $cost] = $this->resolveHoursAndCost($assignment, $timeClockEntries);
                    $totalHours += $hours;
                    $totalCost += $cost;
                }

                return ['hours' => $totalHours, 'cost' => $totalCost];
            });

        return $hoursAndCostByGroup
            ->map(function (array $totals, string $key) {
                [$employeeType, $agencyName] = str_contains($key, '|') ? explode('|', $key, 2) : [$key, null];

                return new CostBreakdownRowData(
                    employeeType: $employeeType,
                    agencyName: $agencyName,
                    totalHours: round($totals['hours'], 2),
                    totalCost: round($totals['cost'], 2),
                );
            })
            ->values()
            ->sortBy([['employeeType', 'asc'], ['agencyName', 'asc']])
            ->values();
    }

    /**
     * Actual clocked-and-break-adjusted hours where a TimeClockEntry exists for this
     * employee/date, falling back to the planned shift_assignment duration otherwise
     * (ProjectPlan.md §14.2) - cost uses the same source, via PayRateSurchargeRuleService
     * so surcharges apply to whichever span (actual or planned) is used.
     *
     * @param  Collection<string, TimeClockEntry>  $timeClockEntries
     * @return array{0: float, 1: float}
     */
    private function resolveHoursAndCost(ShiftAssignment $assignment, Collection $timeClockEntries): array
    {
        /** @var Employee $employee */
        $employee = $assignment->employee;
        $baseHourlyRate = $employee->effectiveHourlyRate();
        $workDate = $assignment->work_date->toDateString();
        $entry = $timeClockEntries->get($assignment->employee_id.'|'.$workDate);

        if ($entry !== null) {
            $hours = $entry->workedMinutes() / 60;
            $cost = $this->payRateSurchargeRuleService->computeShiftCost($baseHourlyRate, $entry->clock_in, $entry->clock_out);

            return [$hours, $cost];
        }

        $start = $assignment->effectiveStart();
        $end = $assignment->effectiveEnd();

        if ($start === null || $end === null) {
            return [0.0, 0.0];
        }

        $shiftStart = Carbon::parse($workDate.' '.$start);
        $shiftEnd = Carbon::parse($workDate.' '.$end);
        if ($assignment->effectiveCrossesMidnight()) {
            $shiftEnd->addDay();
        }

        $hours = $shiftStart->diffInMinutes($shiftEnd) / 60;
        $cost = $this->payRateSurchargeRuleService->computeShiftCost($baseHourlyRate, $shiftStart, $shiftEnd);

        return [$hours, $cost];
    }
}
