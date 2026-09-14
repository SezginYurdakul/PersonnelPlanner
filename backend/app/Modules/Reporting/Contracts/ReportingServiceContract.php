<?php

namespace App\Modules\Reporting\Contracts;

use App\Modules\Reporting\DTOs\ReportData;
use App\Modules\Reporting\DTOs\ReportPeriodData;

interface ReportingServiceContract
{
    /**
     * Builds the headcount and cost-breakdown report for a date range (ProjectPlan.md
     * §14.2), drawn only from approved schedules' assignments.
     */
    public function generate(ReportPeriodData $period): ReportData;
}
