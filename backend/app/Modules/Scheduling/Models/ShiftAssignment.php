<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Staff\Models\Employee;
use Database\Factories\ShiftAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'schedule_id',
    'employee_id',
    'line_id',
    'shift_pattern_id',
    'work_date',
    'role_id',
    'starts_at',
    'ends_at',
    'status',
    'source',
    'notes',
])]
class ShiftAssignment extends Model
{
    /** @use HasFactory<ShiftAssignmentFactory> */
    use HasFactory;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const SOURCE_AUTO_SUGGESTED = 'auto_suggested';

    public const SOURCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
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

    /**
     * @return BelongsTo<Line, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * @return BelongsTo<ShiftPattern, $this>
     */
    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    /**
     * @return BelongsTo<SchedulingRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(SchedulingRole::class, 'role_id');
    }

    /**
     * The actual start time this assignment covers - its own starts_at override if set,
     * otherwise the linked shift pattern's full start time (ProjectPlan.md §17.8).
     */
    public function effectiveStart(): ?string
    {
        return $this->starts_at ?? $this->shiftPattern?->start_time;
    }

    /**
     * The actual end time this assignment covers - its own ends_at override if set,
     * otherwise the linked shift pattern's full end time (ProjectPlan.md §17.8).
     */
    public function effectiveEnd(): ?string
    {
        return $this->ends_at ?? $this->shiftPattern?->end_time;
    }

    public function effectiveCrossesMidnight(): bool
    {
        if ($this->starts_at !== null && $this->ends_at !== null) {
            return $this->ends_at < $this->starts_at;
        }

        return (bool) $this->shiftPattern?->crosses_midnight;
    }

    public function isStationAssignment(): bool
    {
        return (bool) $this->role?->isStation();
    }

    public function isSecondaryTaskAssignment(): bool
    {
        return (bool) $this->role?->isSecondaryTask();
    }

    /**
     * Pure interval-overlap check against this assignment's effective time range
     * (ProjectPlan.md §17A.8 - overlap depends on each row's *effective* range, not a
     * simple column comparison, since starts_at/ends_at may be null). Compares minute
     * offsets rather than raw time strings, since stored values (H:i:s) and incoming
     * request values (H:i) are not guaranteed to share a string format.
     */
    public function overlapsTimeRange(string $start, string $end, bool $crossesMidnight): bool
    {
        $ownStart = $this->effectiveStart();
        $ownEnd = $this->effectiveEnd();

        if ($ownStart === null || $ownEnd === null) {
            return false;
        }

        $ownCrosses = $this->effectiveCrossesMidnight();

        $ownStartMinutes = $this->toMinutes($ownStart);
        $ownEndMinutes = $this->toMinutes($ownEnd);
        $otherStartMinutes = $this->toMinutes($start);
        $otherEndMinutes = $this->toMinutes($end);

        $ownRanges = $ownCrosses
            ? [[$ownStartMinutes, 1440], [0, $ownEndMinutes]]
            : [[$ownStartMinutes, $ownEndMinutes]];

        $otherRanges = $crossesMidnight
            ? [[$otherStartMinutes, 1440], [0, $otherEndMinutes]]
            : [[$otherStartMinutes, $otherEndMinutes]];

        foreach ($ownRanges as [$aStart, $aEnd]) {
            foreach ($otherRanges as [$bStart, $bEnd]) {
                if ($aStart < $bEnd && $bStart < $aEnd) {
                    return true;
                }
            }
        }

        return false;
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
