<?php

namespace App\Modules\Lines\Models;

use Database\Factories\ShiftPatternFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shift_pattern_group_id', 'slot_type', 'name', 'start_time', 'end_time', 'crosses_midnight', 'is_active'])]
class ShiftPattern extends Model
{
    /** @use HasFactory<ShiftPatternFactory> */
    use HasFactory;

    public const SLOT_DAY = 'day';

    public const SLOT_AFTERNOON = 'afternoon';

    public const SLOT_NIGHT = 'night';

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ShiftPatternGroup, $this>
     */
    public function shiftPatternGroup(): BelongsTo
    {
        return $this->belongsTo(ShiftPatternGroup::class);
    }
}
