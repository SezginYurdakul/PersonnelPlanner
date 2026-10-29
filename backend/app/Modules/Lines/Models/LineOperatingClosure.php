<?php

namespace App\Modules\Lines\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day-of-week + slot_type combination this line does NOT operate. Absence of a row
 * means open - this table only stores exceptions to the "runs every day, every shift"
 * default, so a new line needs zero setup (symmetric to EmployeeUnavailability).
 */
#[Fillable([
    'line_id',
    'day_of_week',
    'slot_type',
])]
class LineOperatingClosure extends Model
{
    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
