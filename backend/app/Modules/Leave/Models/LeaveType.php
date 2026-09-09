<?php

namespace App\Modules\Leave\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug', 'requires_approval'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    public const SLUG_VAKANTIE = 'vakantie';

    public const SLUG_ZIEK = 'ziek';

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
        ];
    }

    /**
     * The annual-leave type, used by the employee self-service leave request flow
     * (ProjectPlan.md §8c) to force the correct leave_type_id server-side.
     */
    public static function vakantie(): self
    {
        return static::query()->where('slug', self::SLUG_VAKANTIE)->firstOrFail();
    }
}
