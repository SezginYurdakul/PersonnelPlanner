<?php

namespace App\Jobs;

use App\Modules\Notifications\Contracts\PushSubscriptionServiceContract;
use App\Modules\Notifications\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * One push send to one subscription, queued independently of the notification's other
 * channels (ProjectPlan.md §20.3: "a failed send on either channel must be logged and
 * retryable independently"). Splitting this out of WebPushChannel means a transient
 * failure here (a network blip, the push service returning 5xx) gets Laravel's normal
 * queued-job retry/backoff, without that retry ever touching the mail side of the same
 * notification - they no longer share a job at all.
 */
class SendWebPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Transient failures (network errors, 5xx) get a few attempts with backoff. A
     * permanently invalid subscription (410 Gone) is detected and deactivated on the
     * first attempt below, not retried.
     */
    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        private readonly int $pushSubscriptionId,
        private readonly string $payload,
    ) {}

    public function handle(PushSubscriptionServiceContract $subscriptions): void
    {
        $subscription = PushSubscription::query()->find($this->pushSubscriptionId);

        if ($subscription === null || ! $subscription->is_active) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.subject'),
                'publicKey' => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ]);

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => [
                    'p256dh' => $subscription->p256dh_key,
                    'auth' => $subscription->auth_key,
                ],
            ]),
            $this->payload,
        );

        if ($report->isSuccess()) {
            return;
        }

        if ($report->isSubscriptionExpired()) {
            $subscriptions->deactivate($subscription);

            return;
        }

        // Any other failure (network error, 5xx, malformed key) is treated as transient -
        // throwing here (rather than calling fail()) is what makes the queue's normal
        // tries/backoff retry this job, landing in failed_jobs only after all attempts
        // are exhausted.
        throw new \RuntimeException("Web push send failed for subscription #{$subscription->id}: {$report->getReason()}");
    }
}
