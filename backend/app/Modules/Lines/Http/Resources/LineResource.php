<?php

namespace App\Modules\Lines\Http\Resources;

use App\Modules\Lines\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Line */
class LineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'shift_pattern_group_id' => $this->shift_pattern_group_id,
            'is_active' => $this->is_active,
        ];
    }
}
