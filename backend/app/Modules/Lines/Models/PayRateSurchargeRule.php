<?php

namespace App\Modules\Lines\Models;

use Database\Factories\PayRateSurchargeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'days_of_week',
    'start_time',
    'end_time',
    'crosses_midnight',
    'surcharge_percentage',
    'is_active',
])]
class PayRateSurchargeRule extends Model
{
    /** @use HasFactory<PayRateSurchargeRuleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'crosses_midnight' => 'boolean',
            'surcharge_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
