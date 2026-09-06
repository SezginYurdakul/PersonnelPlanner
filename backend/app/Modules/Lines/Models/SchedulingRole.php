<?php

namespace App\Modules\Lines\Models;

use App\Modules\Staff\Models\Employee;
use Database\Factories\SchedulingRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'line_id',
    'role_kind',
    'requires_coverage',
    'attachment_type',
    'attached_station_role_id',
    'is_active',
])]
class SchedulingRole extends Model
{
    /** @use HasFactory<SchedulingRoleFactory> */
    use HasFactory;

    public const KIND_STATION = 'station';

    public const KIND_SECONDARY_TASK = 'secondary_task';

    protected function casts(): array
    {
        return [
            'requires_coverage' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * The specific station this secondary task rides on, when attachment_type = station
     * (ProjectPlan.md §11a.2).
     *
     * @return BelongsTo<SchedulingRole, $this>
     */
    public function attachedStation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'attached_station_role_id');
    }

    /**
     * @return HasMany<SchedulingRole, $this>
     */
    public function attachedSecondaryTasks(): HasMany
    {
        return $this->hasMany(self::class, 'attached_station_role_id');
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function qualifiedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_scheduling_roles');
    }

    public function isStation(): bool
    {
        return $this->role_kind === self::KIND_STATION;
    }

    public function isSecondaryTask(): bool
    {
        return $this->role_kind === self::KIND_SECONDARY_TASK;
    }
}
