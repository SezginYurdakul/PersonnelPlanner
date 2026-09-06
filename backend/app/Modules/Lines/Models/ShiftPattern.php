<?php

namespace App\Modules\Lines\Models;

use Database\Factories\ShiftPatternFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'start_time', 'end_time', 'crosses_midnight', 'is_active'])]
class ShiftPattern extends Model
{
    /** @use HasFactory<ShiftPatternFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
