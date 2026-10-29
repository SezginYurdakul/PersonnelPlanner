<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Staff\Models\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per employee per approved week - the weighted-hours contribution of that week,
 * written once when the week's Schedule is approved (never for draft/proposed regeneration,
 * so re-running "Generate Suggestion" doesn't inflate the score). The suggestion engine's
 * fairness ranking sums recent rows (last N weeks) per employee instead of relying only on
 * the in-progress generation pass's own in-memory tally.
 */
#[Fillable([
    'employee_id',
    'schedule_id',
    'week_start_date',
    'weighted_hours',
])]
class EmployeeFairnessScore extends Model
{
    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'weighted_hours' => 'float',
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
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
