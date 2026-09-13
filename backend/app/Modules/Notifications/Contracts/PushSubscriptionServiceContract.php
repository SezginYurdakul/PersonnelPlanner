<?php

namespace App\Modules\Notifications\Contracts;

use App\Modules\Notifications\DTOs\PushSubscriptionData;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Staff\Models\Employee;

interface PushSubscriptionServiceContract
{
    /**
     * Registers a subscription for the given employee (ProjectPlan.md §20.2a) - upserts
     * by `endpoint` so re-registering the same browser/device doesn't create duplicates.
     */
    public function register(Employee $employee, PushSubscriptionData $data): PushSubscription;

    /**
     * Deactivates a subscription rather than deleting it (ProjectPlan.md §17.11) - called
     * both from an explicit unregister request and lazily by WebPushChannel on a confirmed
     * 410 Gone response.
     */
    public function deactivate(PushSubscription $subscription): void;
}
