<?php

namespace App\Modules\TimeAttendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TimeAttendance\Contracts\TimeClockEntryServiceContract;
use App\Modules\TimeAttendance\Http\Requests\StoreTimeClockEntryRequest;
use App\Modules\TimeAttendance\Http\Requests\UpdateTimeClockEntryRequest;
use App\Modules\TimeAttendance\Http\Resources\TimeClockEntryResource;
use App\Modules\TimeAttendance\Models\TimeClockEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TimeClockEntryController extends Controller
{
    public function __construct(private readonly TimeClockEntryServiceContract $entries)
    {
    }

    public function index(Request $request)
    {
        $entries = $this->entries->list($request->only(['employee_id', 'date_from', 'date_to']));

        return TimeClockEntryResource::collection($entries);
    }

    public function store(StoreTimeClockEntryRequest $request): TimeClockEntryResource
    {
        $entry = $this->entries->create($request->toDto());
        $entry->load(['employee', 'breaks']);

        return new TimeClockEntryResource($entry);
    }

    public function update(UpdateTimeClockEntryRequest $request, TimeClockEntry $timeClockEntry): TimeClockEntryResource
    {
        $entry = $this->entries->update($timeClockEntry, $request->toDto());
        $entry->load(['employee', 'breaks']);

        return new TimeClockEntryResource($entry);
    }

    public function destroy(TimeClockEntry $timeClockEntry): Response
    {
        $this->entries->delete($timeClockEntry);

        return response()->noContent();
    }
}
