<?php

namespace App\Modules\Lines\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Lines\Contracts\ShiftPatternServiceContract;
use App\Modules\Lines\Http\Requests\StoreShiftPatternRequest;
use App\Modules\Lines\Http\Requests\UpdateShiftPatternRequest;
use App\Modules\Lines\Http\Resources\ShiftPatternResource;
use App\Modules\Lines\Models\ShiftPattern;

class ShiftPatternController extends Controller
{
    public function __construct(
        private readonly ShiftPatternServiceContract $shiftPatterns,
    ) {}

    public function index()
    {
        return ShiftPatternResource::collection($this->shiftPatterns->list());
    }

    public function store(StoreShiftPatternRequest $request): ShiftPatternResource
    {
        return new ShiftPatternResource($this->shiftPatterns->create($request->toDto()));
    }

    public function show(ShiftPattern $shiftPattern): ShiftPatternResource
    {
        return new ShiftPatternResource($shiftPattern);
    }

    public function update(UpdateShiftPatternRequest $request, ShiftPattern $shiftPattern): ShiftPatternResource
    {
        return new ShiftPatternResource($this->shiftPatterns->update($shiftPattern, $request->toDto()));
    }

    public function destroy(ShiftPattern $shiftPattern): ShiftPatternResource
    {
        return new ShiftPatternResource($this->shiftPatterns->deactivate($shiftPattern));
    }
}
