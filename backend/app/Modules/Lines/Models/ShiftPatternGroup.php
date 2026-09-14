<?php

namespace App\Modules\Lines\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of concrete ShiftPattern rows, one per slot_type (day/afternoon/night) - a
 * Line picks one group, and every shift on that line uses that group's hours for each
 * shift type. E.g. "Pattern A": Day 08-16, Afternoon 16-00, Night 00-08 vs. "Pattern B":
 * Day 10-18, Afternoon 18-02, Night 02-10 - the shift type names stay the same
 * everywhere, only their hours vary by group.
 */
#[Fillable(['name', 'is_active'])]
class ShiftPatternGroup extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ShiftPattern, $this>
     */
    public function shiftPatterns(): HasMany
    {
        return $this->hasMany(ShiftPattern::class);
    }

    /**
     * @return HasMany<Line, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(Line::class);
    }
}
