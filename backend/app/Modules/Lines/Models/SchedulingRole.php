<?php

namespace App\Modules\Lines\Models;

use App\Modules\Scheduling\Models\ShiftAssignment;
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
    'allows_second_person',
    'min_headcount',
    'fairness_weight',
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
            'allows_second_person' => 'boolean',
            'min_headcount' => 'integer',
            'fairness_weight' => 'float',
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

    /**
     * Employees who must never be assigned to this role - independent of, and layered on
     * top of, qualifiedEmployees() (see EmployeeRoleExclusion's docblock).
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function excludedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_role_exclusions');
    }

    public function isStation(): bool
    {
        return $this->role_kind === self::KIND_STATION;
    }

    public function isSecondaryTask(): bool
    {
        return $this->role_kind === self::KIND_SECONDARY_TASK;
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'role_id');
    }
}
