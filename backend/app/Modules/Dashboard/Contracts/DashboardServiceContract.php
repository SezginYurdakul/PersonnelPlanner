<?php

namespace App\Modules\Dashboard\Contracts;

use App\Modules\Dashboard\DTOs\DashboardSummaryData;

interface DashboardServiceContract
{
    public function summary(): DashboardSummaryData;
}
