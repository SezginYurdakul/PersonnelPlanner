<?php

namespace App\Modules\Leave\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Leave\Models\LeaveType */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'requires_approval' => $this->requires_approval,
        ];
    }
}
