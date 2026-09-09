<?php

namespace App\Modules\TimeAttendance\Models;

use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Staff\Models\Employee;
use Database\Factories\TimeClockEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'shift_assignment_id',
    'work_date',
    'clock_in',
    'clock_out',
    'break_minutes',
    'source',
])]
class TimeClockEntry extends Model
{
    /** @use HasFactory<TimeClockEntryFactory> */
    use HasFactory;

    public const SOURCE_IMPORT_SIMPLE = 'import_simple';

    public const SOURCE_IMPORT_DETAILED = 'import_detailed';

    public const SOURCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'break_minutes' => 'integer',
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
     * @return HasMany<TimeClockBreak, $this>
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(TimeClockBreak::class);
    }

    /**
     * Total clocked span minus total break time, in minutes (ProjectPlan.md §13a.2) - the
     * "actual worked minutes" figure a future Phase 7 cost report will consume. Not wired
     * into any cost calculation in this phase.
     */
    public function workedMinutes(): int
    {
        $spanMinutes = $this->clock_in->diffInMinutes($this->clock_out);

        return max(0, $spanMinutes - $this->break_minutes);
    }
}
