<?php

namespace Tests\Feature;

use App\Modules\CompanySettings\Models\CompanySetting;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The app week (CompanySetting::week_start_day) is seven days, never eight - the weekly
 * hours check once ran Sunday to Sunday and counted the next week's first shift.
 */
class CompanyWeekBoundaryTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function weeks(): array
    {
        return [
            'sunday start, mid-week' => [CompanySetting::WEEK_START_SUNDAY, '2026-11-06 16:00', '2026-11-01 00:00:00', '2026-11-07 23:59:59'],
            'sunday start, on the sunday' => [CompanySetting::WEEK_START_SUNDAY, '2026-11-08 00:00', '2026-11-08 00:00:00', '2026-11-14 23:59:59'],
            'monday start, on the sunday' => [CompanySetting::WEEK_START_MONDAY, '2026-11-08 08:00', '2026-11-02 00:00:00', '2026-11-08 23:59:59'],
        ];
    }

    #[DataProvider('weeks')]
    public function test_week_runs_seven_days_from_the_configured_start(string $weekStartDay, string $moment, string $expectedStart, string $expectedEnd): void
    {
        $setting = new CompanySetting(['week_start_day' => $weekStartDay]);
        $date = Carbon::parse($moment);

        $this->assertSame($expectedStart, $setting->startOfWeekFor($date)->format('Y-m-d H:i:s'));
        $this->assertSame($expectedEnd, $setting->endOfWeekFor($date)->format('Y-m-d H:i:s'));
    }
}
