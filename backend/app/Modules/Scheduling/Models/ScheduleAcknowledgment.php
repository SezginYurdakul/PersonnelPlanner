<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's confirmation that they saw a specific version of a corrected, already-
 * approved schedule (ScheduleAcknowledgmentService owns reading/writing these). Absence of
 * a row for the schedule's current version means "not yet acknowledged" - there is no
 * separate pending/acknowledged status column, the row's existence IS the acknowledgment.
 */
#[Fillable([
    'schedule_id',
    'employee_id',
    'version',
    'acknowledged_at',
])]
class ScheduleAcknowledgment extends Model
{
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'acknowledged_at' => 'datetime',
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
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
