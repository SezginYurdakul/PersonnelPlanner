<?php

namespace App\Modules\CompanySettings\Models;

use Database\Factories\CompanySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'annual_leave_min_notice_days',
    'shift_notice_min_notice_hours',
    'emergency_contact_phone',
])]
class CompanySetting extends Model
{
    /** @use HasFactory<CompanySettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'annual_leave_min_notice_days' => 'integer',
            'shift_notice_min_notice_hours' => 'integer',
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
        ]);
    }
}
