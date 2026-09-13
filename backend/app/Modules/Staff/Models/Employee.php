<?php

namespace App\Modules\Staff\Models;

use App\Models\User;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\TimeAttendance\Models\TimeClockEntry;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'user_id',
    'first_name',
    'last_name',
    'phone',
    'email',
    'employee_type',
    'agency_id',
    'pay_type',
    'hourly_rate',
    'monthly_salary',
    'contracted_hours_per_week',
    'default_line_id',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'contracted_hours_per_week' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Line, $this>
     */
    public function defaultLine(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'default_line_id');
    }

    /**
     * The scheduling roles (stations/secondary tasks) this employee is qualified for
     * (ProjectPlan.md §11a.3).
     *
     * @return BelongsToMany<SchedulingRole, $this>
     */
    public function schedulingRoles(): BelongsToMany
    {
        return $this->belongsToMany(SchedulingRole::class, 'employee_scheduling_roles');
    }

    /**
     * @return HasMany<EmploymentTerm, $this>
     */
    public function employmentTerms(): HasMany
    {
        return $this->hasMany(EmploymentTerm::class);
    }

    /**
     * @return HasOne<EmploymentTerm, $this>
     */
    public function currentEmploymentTerm(): HasOne
    {
        return $this->hasOne(EmploymentTerm::class)
            ->whereNull('effective_to')
            ->latestOfMany('effective_from');
    }

    public function hasLinkedAccount(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<TimeClockEntry, $this>
     */
    public function timeClockEntries(): HasMany
    {
        return $this->hasMany(TimeClockEntry::class);
    }

    /**
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Email must reach this Employee's own address (ProjectPlan.md §20) regardless of
     * whether a linked User/login exists at all - unlike User, Employee has no `email`
     * column override needed since Notifiable's default already reads `->email`, but this
     * makes the routing explicit and independent of any User relationship.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }
}
