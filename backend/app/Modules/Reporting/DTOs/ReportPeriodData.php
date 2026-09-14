<?php

namespace App\Modules\Reporting\DTOs;

use Illuminate\Support\Carbon;

final readonly class ReportPeriodData
{
    public function __construct(
        public Carbon $startDate,
        public Carbon $endDate,
    ) {}
}
