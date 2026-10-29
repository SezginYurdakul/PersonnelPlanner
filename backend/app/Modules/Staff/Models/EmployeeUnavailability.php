<?php

namespace App\Modules\Staff\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day-of-week + slot_type combination this employee is NOT available for. Absence of a
 * row means available - this table only stores exceptions to the "available every day,
 * every shift" default, so a new employee needs zero setup.
 */
#[Fillable([
    'employee_id',
    'day_of_week',
    'slot_type',
])]
class EmployeeUnavailability extends Model
{
    public const DAY_MONDAY = 1;

    public const DAY_SUNDAY = 7;

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
