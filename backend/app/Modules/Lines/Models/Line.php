<?php

namespace App\Modules\Lines\Models;

use App\Modules\Staff\Models\Employee;
use Database\Factories\LineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'shift_pattern_group_id', 'is_active'])]
class Line extends Model
{
    /** @use HasFactory<LineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SchedulingRole, $this>
     */
    public function schedulingRoles(): HasMany
    {
        return $this->hasMany(SchedulingRole::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function defaultEmployees(): HasMany
    {
        return $this->hasMany(Employee::class, 'default_line_id');
    }

    /**
     * Which set of concrete shift-pattern hours (Day/Afternoon/Night) this line uses
     * (ProjectPlan.md: "Day Shift" stays one name everywhere, but its hours vary by
     * group). Nullable - a newly created line has no group until the admin assigns one.
     *
     * @return BelongsTo<ShiftPatternGroup, $this>
     */
    public function shiftPatternGroup(): BelongsTo
    {
        return $this->belongsTo(ShiftPatternGroup::class);
    }
}
