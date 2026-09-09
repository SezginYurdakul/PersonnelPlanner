<?php

namespace App\Modules\ShiftNotices\Models;

use App\Models\User;
use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Database\Factories\ShiftNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'shift_assignment_id',
    'type',
    'delay_minutes',
    'note',
    'status',
    'acknowledged_by',
    'acknowledged_at',
])]
class ShiftNotice extends Model
{
    /** @use HasFactory<ShiftNoticeFactory> */
    use HasFactory;

    public const TYPE_SICK = 'sick';

    public const TYPE_LATE = 'late';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    protected function casts(): array
    {
        return [
            'delay_minutes' => 'integer',
            'acknowledged_at' => 'datetime',
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
     * @return BelongsTo<ShiftAssignment, $this>
     */
    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isAcknowledged(): bool
    {
        return $this->status === self::STATUS_ACKNOWLEDGED;
    }
}
