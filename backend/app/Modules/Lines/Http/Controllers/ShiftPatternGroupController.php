<?php

namespace App\Modules\Lines\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Lines\Contracts\ShiftPatternGroupServiceContract;
use App\Modules\Lines\Http\Requests\StoreShiftPatternGroupRequest;
use App\Modules\Lines\Http\Requests\UpdateShiftPatternGroupRequest;
use App\Modules\Lines\Http\Resources\ShiftPatternGroupResource;
use App\Modules\Lines\Models\ShiftPatternGroup;

class ShiftPatternGroupController extends Controller
{
    public function __construct(private readonly ShiftPatternGroupServiceContract $shiftPatternGroups) {}

    public function index()
    {
        return ShiftPatternGroupResource::collection($this->shiftPatternGroups->list());
    }

    public function store(StoreShiftPatternGroupRequest $request): ShiftPatternGroupResource
    {
        return new ShiftPatternGroupResource($this->shiftPatternGroups->create($request->toDto()));
    }

    public function show(ShiftPatternGroup $shiftPatternGroup): ShiftPatternGroupResource
    {
        return new ShiftPatternGroupResource($shiftPatternGroup->load('shiftPatterns'));
    }

    public function update(UpdateShiftPatternGroupRequest $request, ShiftPatternGroup $shiftPatternGroup): ShiftPatternGroupResource
    {
        return new ShiftPatternGroupResource($this->shiftPatternGroups->update($shiftPatternGroup, $request->toDto()));
    }

    public function destroy(ShiftPatternGroup $shiftPatternGroup): ShiftPatternGroupResource
    {
        return new ShiftPatternGroupResource($this->shiftPatternGroups->deactivate($shiftPatternGroup));
    }
}
