<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Contracts\PushSubscriptionServiceContract;
use App\Modules\Notifications\DTOs\PushSubscriptionData;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Staff\Models\Employee;

final class PushSubscriptionService implements PushSubscriptionServiceContract
{
    public function register(Employee $employee, PushSubscriptionData $data): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data->endpoint],
            [
                'employee_id' => $employee->id,
                'p256dh_key' => $data->p256dhKey,
                'auth_key' => $data->authKey,
                'user_agent' => $data->userAgent,
                'is_active' => true,
            ],
        );
    }

    public function deactivate(PushSubscription $subscription): void
    {
        $subscription->update(['is_active' => false]);
    }
}
