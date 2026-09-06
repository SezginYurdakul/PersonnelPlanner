<?php

namespace App\Modules\Leave\Contracts;

use App\Models\User;
use App\Modules\Leave\DTOs\LeaveRequestData;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeaveRequestServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, LeaveRequest>
     */
    public function list(array $filters): LengthAwarePaginator;

    public function create(LeaveRequestData $data): LeaveRequest;

    public function update(LeaveRequest $leaveRequest, LeaveRequestData $data): LeaveRequest;

    public function delete(LeaveRequest $leaveRequest): void;

    public function approve(LeaveRequest $leaveRequest, User $approver): LeaveRequest;

    public function reject(LeaveRequest $leaveRequest, User $approver): LeaveRequest;
}
