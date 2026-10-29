<?php

namespace App\Modules\Scheduling\Models;

use App\Models\User;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'week_start_date',
    'label',
    'note',
    'status',
    'created_by',
    'approved_by',
    'approved_at',
    'generated_scope',
    'was_approved_before',
    'version',
    'rejection_note',
])]
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_APPROVED = 'approved';

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'approved_at' => 'datetime',
            'generated_scope' => 'array',
            'was_approved_before' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * @return HasMany<ScheduleStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ScheduleStatusEvent::class)->latest('created_at');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isProposed(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    /**
     * A schedule that was ever approved (even if later unlocked back to draft) can never
     * again be regenerated via "Generate Suggestion" - only manual drag-and-drop editing.
     */
    public function canBeGenerated(): bool
    {
        return ! $this->was_approved_before;
    }

    /**
     * Only a draft schedule that has never been approved may be edited freely by an admin.
     * A proposed (submitted for review) or approved schedule is locked to admins; a
     * super_admin unlocks an approved one back to draft before it can be edited again.
     */
    public function isEditableByAdmin(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
