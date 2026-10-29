<?php

namespace App\Modules\Scheduling\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail of every review-workflow transition a Schedule goes through -
 * submit for review, approve, reject (with note), unlock. Never updated or deleted;
 * append-only history alongside the schedule's own current-state columns.
 */
#[Fillable([
    'schedule_id',
    'event',
    'user_id',
    'note',
    'created_at',
])]
class ScheduleStatusEvent extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_SUBMITTED = 'submitted';

    public const EVENT_APPROVED = 'approved';

    public const EVENT_REJECTED = 'rejected';

    public const EVENT_UNLOCKED = 'unlocked';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
