<?php

namespace App\Modules\ShiftNotices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\ShiftNotices\Contracts\ShiftNoticeServiceContract;
use App\Modules\ShiftNotices\DTOs\ShiftNoticeData;
use App\Modules\ShiftNotices\Http\Requests\StoreShiftNoticeRequest;
use App\Modules\ShiftNotices\Http\Resources\ShiftNoticeResource;
use App\Modules\ShiftNotices\Services\ShiftNoticeEligibilityChecker;
use App\Modules\Staff\Support\ResolvesAuthenticatedEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SelfServiceShiftNoticeController extends Controller
{
    use ResolvesAuthenticatedEmployee;

    public function __construct(
        private readonly ShiftNoticeServiceContract $notices,
        private readonly ShiftNoticeEligibilityChecker $eligibilityChecker,
    ) {
    }

    public function eligibility(Request $request): JsonResponse
    {
        $employee = $this->employeeForUser($request->user());

        $assignment = ShiftAssignment::query()
            ->where('id', $request->integer('shift_assignment_id'))
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        return response()->json($this->eligibilityChecker->checkEligibility($assignment));
    }

    public function store(StoreShiftNoticeRequest $request): ShiftNoticeResource
    {
        $employee = $this->employeeForUser($request->user());

        $assignment = ShiftAssignment::query()
            ->where('id', $request->integer('shift_assignment_id'))
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $eligibility = $this->eligibilityChecker->checkEligibility($assignment);

        if (! $eligibility['can_submit']) {
            throw ValidationException::withMessages([
                'shift_assignment_id' => __('shift_notices.outside_notice_window'),
            ]);
        }

        $notice = $this->notices->create(new ShiftNoticeData(
            employeeId: $employee->id,
            shiftAssignmentId: $assignment->id,
            type: $request->string('type')->toString(),
            delayMinutes: $request->input('delay_minutes'),
            note: $request->string('note')->toString() ?: null,
        ));

        $notice->load(['employee', 'shiftAssignment.line', 'shiftAssignment.shiftPattern']);

        return new ShiftNoticeResource($notice);
    }
}
