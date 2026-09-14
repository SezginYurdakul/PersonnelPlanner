<?php

namespace App\Modules\Reporting\DTOs;

use Illuminate\Support\Collection;

final readonly class ReportData
{
    /**
     * @param  Collection<int, HeadcountRowData>  $headcount
     * @param  Collection<int, CostBreakdownRowData>  $costBreakdown
     */
    public function __construct(
        public ReportPeriodData $period,
        public Collection $headcount,
        public Collection $costBreakdown,
    ) {}
}
