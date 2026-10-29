<?php

namespace App\Modules\CompanySettings\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'annual_leave_min_notice_days',
    'shift_notice_min_notice_hours',
    'emergency_contact_phone',
    'week_start_day',
    'fairness_night_weight',
    'fairness_afternoon_weight',
    'fairness_day_weight',
    'fairness_sunday_weight',
    'fairness_saturday_weight',
    'fairness_weekday_weight',
    'role_rotation_enabled',
    'planner_weekend_policy',
    'planner_night_weight',
    'planner_sunday_weight',
    'planner_saturday_weight',
    'planner_rotation_break_weight',
    'planner_rotation_enabled',
    'planner_max_leads_per_shift',
    'planner_horizon_weeks',
])]
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    public const WEEK_START_MONDAY = 'monday';

    public const WEEK_START_SUNDAY = 'sunday';

    /** Roster planner weekend policy (ProjectPlan.md §28.4): even weekends first, extra leads allowed. */
    public const PLANNER_WEEKEND_FAIR = 'fair';

    /** Roster planner weekend policy: weekend shifts at their minimum staffing first, fairness after. */
    public const PLANNER_WEEKEND_SINGLE_STAFFED = 'single_staffed';

    protected function casts(): array
    {
        return [
            'annual_leave_min_notice_days' => 'integer',
            'shift_notice_min_notice_hours' => 'integer',
            'fairness_night_weight' => 'float',
            'fairness_afternoon_weight' => 'float',
            'fairness_day_weight' => 'float',
            'fairness_sunday_weight' => 'float',
            'fairness_saturday_weight' => 'float',
            'fairness_weekday_weight' => 'float',
            'role_rotation_enabled' => 'boolean',
            'planner_night_weight' => 'integer',
            'planner_sunday_weight' => 'integer',
            'planner_saturday_weight' => 'integer',
            'planner_rotation_break_weight' => 'integer',
            'planner_rotation_enabled' => 'boolean',
            'planner_max_leads_per_shift' => 'integer',
            'planner_horizon_weeks' => 'integer',
        ];
    }

    /**
     * Singleton accessor - the only place this table is ever queried from. Guarantees a
     * row always exists with the seeded/default values, without a DB-level unique trick.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'annual_leave_min_notice_days' => 14,
            'shift_notice_min_notice_hours' => 2,
            'emergency_contact_phone' => null,
            'week_start_day' => self::WEEK_START_MONDAY,
            'fairness_night_weight' => 1.00,
            'fairness_afternoon_weight' => 1.00,
            'fairness_day_weight' => 1.00,
            'fairness_sunday_weight' => 1.00,
            'fairness_saturday_weight' => 1.00,
            'fairness_weekday_weight' => 1.00,
            'role_rotation_enabled' => true,
            'planner_weekend_policy' => self::PLANNER_WEEKEND_FAIR,
            'planner_night_weight' => 1,
            'planner_sunday_weight' => 3,
            'planner_saturday_weight' => 3,
            'planner_rotation_break_weight' => 3,
            'planner_rotation_enabled' => true,
            'planner_max_leads_per_shift' => 2,
            'planner_horizon_weeks' => 8,
        ]);
    }

    /**
     * The shift-type weight (day/afternoon/night) for a fairness score - one half of the
     * two confirmed independent multipliers (the other is dayTypeWeight()).
     */
    public function shiftTypeWeight(string $slotType): float
    {
        return match ($slotType) {
            'night' => $this->fairness_night_weight,
            'afternoon' => $this->fairness_afternoon_weight,
            default => $this->fairness_day_weight,
        };
    }

    /**
     * The day-type weight (weekday/Saturday/Sunday) for a fairness score, keyed by Carbon's
     * ISO day-of-week (1=Monday..7=Sunday) so it's independent of week_start_day.
     */
    public function dayTypeWeight(int $isoDayOfWeek): float
    {
        return match ($isoDayOfWeek) {
            7 => $this->fairness_sunday_weight,
            6 => $this->fairness_saturday_weight,
            default => $this->fairness_weekday_weight,
        };
    }

    /**
     * The Carbon day-of-week constant (Carbon::SUNDAY/MONDAY) matching this setting -
     * the single conversion point every startOfWeek() call in the app should go through.
     */
    public function weekStartsOnCarbonDay(): int
    {
        return $this->week_start_day === self::WEEK_START_SUNDAY ? Carbon::SUNDAY : Carbon::MONDAY;
    }

    /**
     * @template T of Carbon|CarbonImmutable
     *
     * @param  T  $date
     * @return T
     */
    public function startOfWeekFor(Carbon|CarbonImmutable $date): Carbon|CarbonImmutable
    {
        return $date->copy()->startOfWeek($this->weekStartsOnCarbonDay());
    }

    /**
     * The last moment of the app week containing $date. Never call endOfWeek() with
     * weekStartsOnCarbonDay(): Carbon's endOfWeek() takes the day the week ENDS on, so that
     * reaches a day into the next week (a Sunday-start week would run Sunday to Sunday).
     *
     * @template T of Carbon|CarbonImmutable
     *
     * @param  T  $date
     * @return T
     */
    public function endOfWeekFor(Carbon|CarbonImmutable $date): Carbon|CarbonImmutable
    {
        return $this->startOfWeekFor($date)->addDays(6)->endOfDay();
    }
}
