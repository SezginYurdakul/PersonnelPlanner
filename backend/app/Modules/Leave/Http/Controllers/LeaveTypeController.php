<?php

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Contracts\LeaveTypeServiceContract;
use App\Modules\Leave\Http\Requests\StoreLeaveTypeRequest;
use App\Modules\Leave\Http\Resources\LeaveTypeResource;

class LeaveTypeController extends Controller
{
    public function __construct(
        private readonly LeaveTypeServiceContract $leaveTypes,
    ) {}

    public function index()
    {
        return LeaveTypeResource::collection($this->leaveTypes->list());
    }

    public function store(StoreLeaveTypeRequest $request): LeaveTypeResource
    {
        return new LeaveTypeResource($this->leaveTypes->create($request->toDto()));
    }
}
