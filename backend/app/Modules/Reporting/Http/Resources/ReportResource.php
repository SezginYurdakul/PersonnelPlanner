<?php

namespace App\Modules\Reporting\Http\Resources;

use App\Modules\Reporting\DTOs\CostBreakdownRowData;
use App\Modules\Reporting\DTOs\HeadcountRowData;
use App\Modules\Reporting\DTOs\ReportData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportData */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period' => [
                'start_date' => $this->period->startDate->toDateString(),
                'end_date' => $this->period->endDate->toDateString(),
            ],
            'headcount' => $this->headcount->map(fn (HeadcountRowData $row) => [
                'work_date' => $row->workDate,
                'line_id' => $row->lineId,
                'line_name' => $row->lineName,
                'shift_pattern_id' => $row->shiftPatternId,
                'shift_pattern_name' => $row->shiftPatternName,
                'headcount' => $row->headcount,
            ])->values(),
            'cost_breakdown' => $this->costBreakdown->map(fn (CostBreakdownRowData $row) => [
                'employee_type' => $row->employeeType,
                'agency_name' => $row->agencyName,
                'total_hours' => $row->totalHours,
                'total_cost' => $row->totalCost,
            ])->values(),
        ];
    }
}
