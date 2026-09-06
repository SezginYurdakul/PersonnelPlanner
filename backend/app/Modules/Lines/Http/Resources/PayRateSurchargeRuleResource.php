<?php

namespace App\Modules\Lines\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Lines\Models\PayRateSurchargeRule */
class PayRateSurchargeRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'days_of_week' => $this->days_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'crosses_midnight' => $this->crosses_midnight,
            'surcharge_percentage' => $this->surcharge_percentage,
            'is_active' => $this->is_active,
        ];
    }
}
