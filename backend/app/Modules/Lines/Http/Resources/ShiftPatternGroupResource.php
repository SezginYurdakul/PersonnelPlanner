<?php

namespace App\Modules\Lines\Http\Resources;

use App\Modules\Lines\Models\ShiftPatternGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShiftPatternGroup */
class ShiftPatternGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'patterns' => ShiftPatternResource::collection($this->whenLoaded('shiftPatterns')),
        ];
    }
}
