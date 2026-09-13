<?php

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\DTOs\PushSubscriptionData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Matches the shape of the browser's `PushSubscription.toJSON()` (ProjectPlan.md §18.4a).
 */
class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ];
    }

    public function toDto(): PushSubscriptionData
    {
        return new PushSubscriptionData(
            endpoint: $this->string('endpoint')->toString(),
            p256dhKey: $this->string('keys.p256dh')->toString(),
            authKey: $this->string('keys.auth')->toString(),
            userAgent: $this->userAgent(),
        );
    }
}
