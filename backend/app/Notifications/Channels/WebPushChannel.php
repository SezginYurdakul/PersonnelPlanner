<?php

namespace App\Notifications\Channels;

use App\Jobs\SendWebPushNotification;
use App\Modules\Staff\Models\Employee;
use Illuminate\Notifications\Notification;

/**
 * Custom Web Push delivery channel (ProjectPlan.md §20.2). Rather than sending inline,
 * this dispatches one SendWebPushNotification job per active subscription - each gets its
 * own queued job with its own retry/backoff, entirely independent of this notification's
 * other channels (§20.3: "a failed send on either channel must be logged and retryable
 * independently"). Dispatching itself can't fail the way a send can, so there's nothing
 * here to catch - the actual send and its failure handling live in the job.
 */
class WebPushChannel
{
    public function send(Employee $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $subscriptions = $notifiable->pushSubscriptions()->where('is_active', true)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode($notification->toWebPush($notifiable));

        foreach ($subscriptions as $subscription) {
            SendWebPushNotification::dispatch($subscription->id, $payload);
        }
    }
}
