<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\DTOs\UnfilledSlotData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UnfilledSlotData */
class UnfilledSlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'line' => $this->line ? [
                'id' => $this->line->id,
                'name' => $this->line->name,
                'code' => $this->line->code,
            ] : null,
            'shift_pattern' => $this->shiftPattern ? [
                'id' => $this->shiftPattern->id,
                'name' => $this->shiftPattern->name,
                'slot_type' => $this->shiftPattern->slot_type,
                'start_time' => $this->shiftPattern->start_time,
                'end_time' => $this->shiftPattern->end_time,
            ] : null,
            'work_date' => $this->workDate->toDateString(),
            'role' => [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'role_kind' => $this->role->role_kind,
            ],
            'blocking' => $this->blocking,
            'missing_count' => $this->missingCount,
            'alternatives' => CandidateResource::collection($this->alternatives),
            'reason' => $this->reason,
        ];
    }
}
