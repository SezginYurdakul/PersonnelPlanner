<?php

namespace App\Notifications;

use App\Modules\Staff\Models\Employee;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Distinct from ScheduleChanged: fired only when an ALREADY-approved, already-published
 * schedule is corrected (unlocked, manually fixed, resubmitted, re-approved) and bumps its
 * version - never on a schedule's first approval, which still gets the plain ScheduleChanged
 * notice. Links to the employee dashboard, where the pending-acknowledgment prompt lives
 * (ScheduleAcknowledgmentService) - the recipient is expected to actively confirm they saw
 * this, not just be informed.
 */
class ScheduleCorrected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $weekStartDate,
        private readonly int $version,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', WebPushChannel::class];
    }

    public function toMail(Employee $notifiable): MailMessage
    {
        $locale = $notifiable->user?->locale ?? config('app.fallback_locale');
        $url = rtrim(config('app.frontend_url'), '/').'/me/dashboard';

        return (new MailMessage)
            ->subject(__('notifications.schedule_corrected.subject', [], $locale))
            ->line(__('notifications.schedule_corrected.line', ['week' => $this->weekStartDate, 'version' => $this->version], $locale))
            ->action(__('notifications.schedule_corrected.action', [], $locale), $url);
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(Employee $notifiable): array
    {
        $locale = $notifiable->user?->locale ?? config('app.fallback_locale');

        return [
            'title' => __('notifications.schedule_corrected.subject', [], $locale),
            'body' => __('notifications.schedule_corrected.line', ['week' => $this->weekStartDate, 'version' => $this->version], $locale),
            'url' => '/me/dashboard',
        ];
    }
}
