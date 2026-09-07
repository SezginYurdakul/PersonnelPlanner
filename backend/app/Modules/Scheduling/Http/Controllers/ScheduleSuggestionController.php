<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Contracts\ScheduleSuggestionServiceContract;
use App\Modules\Scheduling\Http\Requests\GenerateSuggestionRequest;
use App\Modules\Scheduling\Http\Resources\ScheduleResource;
use App\Modules\Scheduling\Http\Resources\UnfilledSlotResource;

class ScheduleSuggestionController extends Controller
{
    public function __construct(private readonly ScheduleSuggestionServiceContract $suggestions)
    {
    }

    public function store(GenerateSuggestionRequest $request)
    {
        $result = $this->suggestions->generate($request->toDto());

        return response()->json([
            'schedule' => new ScheduleResource($result->schedule),
            'unfilled_slots' => UnfilledSlotResource::collection($result->unfilledSlots),
        ]);
    }
}
