<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PushSubscription */
class PushSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_agent' => $this->user_agent,
            'is_active' => $this->is_active,
        ];
    }
}
