<?php

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Contracts\LeaveRequestServiceContract;
use App\Modules\Leave\DTOs\LeaveRequestData;
use App\Modules\Leave\Http\Requests\StoreSelfServiceLeaveRequestRequest;
use App\Modules\Leave\Http\Resources\LeaveRequestResource;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\AnnualLeaveNoticeChecker;
use App\Modules\Staff\Support\ResolvesAuthenticatedEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SelfServiceLeaveRequestController extends Controller
{
    use ResolvesAuthenticatedEmployee;

    public function __construct(
        private readonly LeaveRequestServiceContract $leaveRequests,
        private readonly AnnualLeaveNoticeChecker $noticeChecker,
    ) {
    }

    public function index(Request $request)
    {
        $employee = $this->employeeForUser($request->user());

        $requests = $this->leaveRequests->list(['employee_id' => $employee->id]);

        return LeaveRequestResource::collection($requests);
    }

    public function store(StoreSelfServiceLeaveRequestRequest $request): LeaveRequestResource
    {
        $employee = $this->employeeForUser($request->user());

        $startDate = Carbon::parse($request->string('start_date')->toString());

        $this->noticeChecker->assertSufficientNotice($startDate);

        $leaveRequest = $this->leaveRequests->create(new LeaveRequestData(
            employeeId: $employee->id,
            leaveTypeId: LeaveType::vakantie()->id,
            startDate: $startDate,
            endDate: Carbon::parse($request->string('end_date')->toString()),
            reason: $request->string('reason')->toString() ?: null,
        ));

        $leaveRequest->load(['employee', 'leaveType']);

        return new LeaveRequestResource($leaveRequest);
    }
}
