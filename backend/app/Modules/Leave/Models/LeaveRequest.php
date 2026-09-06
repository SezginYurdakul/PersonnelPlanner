<?php

namespace App\Modules\Leave\Models;

use App\Models\User;
use App\Modules\Staff\Models\Employee;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'status', 'reason', 'approved_by'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Whether this approved leave covers the given date (ProjectPlan.md §13.3/§12.2 -
     * used by the suggestion engine to exclude an employee from a day's candidate pool).
     */
    public function coversDate(\DateTimeInterface $date): bool
    {
        return $this->isApproved()
            && $this->start_date->lte($date)
            && $this->end_date->gte($date);
    }
}
