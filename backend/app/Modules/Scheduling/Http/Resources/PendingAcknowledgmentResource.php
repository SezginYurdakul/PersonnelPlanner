<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Schedule */
class PendingAcknowledgmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'schedule_id' => $this->id,
            'week_start_date' => $this->week_start_date->toDateString(),
            'version' => $this->version,
        ];
    }
}
