<?php

namespace App\Modules\TimeAttendance\Models;

use Database\Factories\TimeClockBreakFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['time_clock_entry_id', 'break_start', 'break_end'])]
class TimeClockBreak extends Model
{
    /** @use HasFactory<TimeClockBreakFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'break_start' => 'datetime',
            'break_end' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TimeClockEntry, $this>
     */
    public function timeClockEntry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class);
    }

    public function minutes(): int
    {
        return $this->break_start->diffInMinutes($this->break_end);
    }
}
