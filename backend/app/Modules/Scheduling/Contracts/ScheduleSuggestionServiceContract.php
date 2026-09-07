<?php

namespace App\Modules\Scheduling\Contracts;

use App\Modules\Scheduling\DTOs\SuggestionRequestData;
use App\Modules\Scheduling\DTOs\SuggestionResultData;

interface ScheduleSuggestionServiceContract
{
    public function generate(SuggestionRequestData $request): SuggestionResultData;
}
