<?php

namespace App\Modules\Lines\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Lines\Models\SchedulingRole */
class SchedulingRoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'line' => new LineResource($this->whenLoaded('line')),
            'role_kind' => $this->role_kind,
            'requires_coverage' => $this->requires_coverage,
            'attachment_type' => $this->attachment_type,
            'attached_station' => new self($this->whenLoaded('attachedStation')),
            'is_active' => $this->is_active,
        ];
    }
}
