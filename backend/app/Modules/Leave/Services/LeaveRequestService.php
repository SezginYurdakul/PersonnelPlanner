<?php

namespace App\Modules\Leave\Services;

use App\Models\User;
use App\Modules\Leave\Contracts\LeaveRequestServiceContract;
use App\Modules\Leave\DTOs\LeaveRequestData;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class LeaveRequestService implements LeaveRequestServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, LeaveRequest>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = LeaveRequest::query()->with(['employee', 'leaveType']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['leave_type_id'])) {
            $query->where('leave_type_id', $filters['leave_type_id']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Pending requests are the actionable ones - surface them first (ProjectPlan.md §10A.4).
        return $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('start_date')
            ->paginate();
    }

    public function create(LeaveRequestData $data): LeaveRequest
    {
        return LeaveRequest::create($data->toArray())->refresh();
    }

    public function update(LeaveRequest $leaveRequest, LeaveRequestData $data): LeaveRequest
    {
        $leaveRequest->update($data->toArray());

        return $leaveRequest->refresh();
    }

    public function delete(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->delete();
    }

    public function approve(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'approved_by' => $approver->id,
        ]);

        return $leaveRequest->refresh();
    }

    public function reject(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'approved_by' => $approver->id,
        ]);

        return $leaveRequest->refresh();
    }
}
