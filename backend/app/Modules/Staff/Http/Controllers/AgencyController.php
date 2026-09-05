<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Contracts\AgencyServiceContract;
use App\Modules\Staff\Http\Requests\StoreAgencyRequest;
use App\Modules\Staff\Http\Requests\UpdateAgencyRequest;
use App\Modules\Staff\Http\Resources\AgencyResource;
use App\Modules\Staff\Models\Agency;
use Illuminate\Http\Response;

class AgencyController extends Controller
{
    public function __construct(
        private readonly AgencyServiceContract $agencies,
    ) {}

    public function index()
    {
        return AgencyResource::collection($this->agencies->list());
    }

    public function store(StoreAgencyRequest $request): AgencyResource
    {
        return new AgencyResource($this->agencies->create($request->toDto()));
    }

    public function show(Agency $agency): AgencyResource
    {
        return new AgencyResource($agency);
    }

    public function update(UpdateAgencyRequest $request, Agency $agency): AgencyResource
    {
        return new AgencyResource($this->agencies->update($agency, $request->toDto()));
    }

    public function destroy(Agency $agency): Response
    {
        $this->agencies->delete($agency);

        return response()->noContent();
    }
}
