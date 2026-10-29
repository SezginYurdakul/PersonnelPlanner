<?php

namespace App\Modules\Scheduling\Contracts;

use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\Models\Schedule;

interface ScheduleSuggestionServiceContract
{
    public function generate(SuggestionRequestData $request): Schedule;
}
