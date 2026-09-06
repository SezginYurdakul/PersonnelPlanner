<?php

namespace App\Modules\Lines\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Lines\Contracts\LineServiceContract;
use App\Modules\Lines\Http\Requests\StoreLineRequest;
use App\Modules\Lines\Http\Requests\UpdateLineRequest;
use App\Modules\Lines\Http\Resources\LineResource;
use App\Modules\Lines\Models\Line;

class LineController extends Controller
{
    public function __construct(
        private readonly LineServiceContract $lines,
    ) {}

    public function index()
    {
        return LineResource::collection($this->lines->list());
    }

    public function store(StoreLineRequest $request): LineResource
    {
        return new LineResource($this->lines->create($request->toDto()));
    }

    public function show(Line $line): LineResource
    {
        return new LineResource($line);
    }

    public function update(UpdateLineRequest $request, Line $line): LineResource
    {
        return new LineResource($this->lines->update($line, $request->toDto()));
    }

    public function destroy(Line $line): LineResource
    {
        return new LineResource($this->lines->deactivate($line));
    }
}
