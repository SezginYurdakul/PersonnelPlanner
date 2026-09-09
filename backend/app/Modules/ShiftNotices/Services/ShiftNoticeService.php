<?php

namespace App\Modules\ShiftNotices\Services;

use App\Models\User;
use App\Modules\ShiftNotices\Contracts\ShiftNoticeServiceContract;
use App\Modules\ShiftNotices\DTOs\ShiftNoticeData;
use App\Modules\ShiftNotices\Models\ShiftNotice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ShiftNoticeService implements ShiftNoticeServiceContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ShiftNotice>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = ShiftNotice::query()->with(['employee', 'shiftAssignment.line', 'shiftAssignment.shiftPattern']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Submitted (actionable) notices surface first, matching LeaveRequestService's
        // pending-first ordering convention.
        return $query
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate();
    }

    public function create(ShiftNoticeData $data): ShiftNotice
    {
        return ShiftNotice::create($data->toArray())->refresh();
    }

    public function acknowledge(ShiftNotice $notice, User $acknowledger): ShiftNotice
    {
        $notice->update([
            'status' => ShiftNotice::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => $acknowledger->id,
            'acknowledged_at' => now(),
        ]);

        return $notice->refresh();
    }
}
