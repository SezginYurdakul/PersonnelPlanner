<?php

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Contracts\LeaveRequestServiceContract;
use App\Modules\Leave\Http\Requests\StoreLeaveRequestRequest;
use App\Modules\Leave\Http\Requests\UpdateLeaveRequestRequest;
use App\Modules\Leave\Http\Resources\LeaveRequestResource;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestServiceContract $leaveRequests,
    ) {}

    public function index(Request $request)
    {
        $leaveRequests = $this->leaveRequests->list($request->only(['status', 'leave_type_id', 'employee_id']));

        return LeaveRequestResource::collection($leaveRequests);
    }

    public function store(StoreLeaveRequestRequest $request): LeaveRequestResource
    {
        $leaveRequest = $this->leaveRequests->create($request->toDto());
        $leaveRequest->load(['employee', 'leaveType']);

        return new LeaveRequestResource($leaveRequest);
    }

    public function show(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $leaveRequest->load(['employee', 'leaveType']);

        return new LeaveRequestResource($leaveRequest);
    }

    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $leaveRequest = $this->leaveRequests->update($leaveRequest, $request->toDto());
        $leaveRequest->load(['employee', 'leaveType']);

        return new LeaveRequestResource($leaveRequest);
    }

    public function destroy(LeaveRequest $leaveRequest): Response
    {
        $this->leaveRequests->delete($leaveRequest);

        return response()->noContent();
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $leaveRequest = $this->leaveRequests->approve($leaveRequest, $request->user());
        $leaveRequest->load(['employee', 'leaveType']);

        return new LeaveRequestResource($leaveRequest);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $leaveRequest = $this->leaveRequests->reject($leaveRequest, $request->user());
        $leaveRequest->load(['employee', 'leaveType']);

        return new LeaveRequestResource($leaveRequest);
    }
}
