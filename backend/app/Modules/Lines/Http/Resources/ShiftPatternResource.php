<?php

namespace App\Modules\Lines\Http\Resources;

use App\Modules\Lines\Models\ShiftPattern;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShiftPattern */
class ShiftPatternResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_pattern_group_id' => $this->shift_pattern_group_id,
            'slot_type' => $this->slot_type,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'crosses_midnight' => $this->crosses_midnight,
            'is_active' => $this->is_active,
        ];
    }
}
