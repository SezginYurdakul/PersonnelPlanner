<?php

namespace App\Notifications;

use App\Modules\Staff\Models\Employee;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ProjectPlan.md §12.4/§20 - fired when an approved schedule affecting an employee is
 * created, edited, or has an assignment change. Constructed with a plain week-start-date
 * string (not a hydrated Schedule) per standard ShouldQueue serialization practice.
 */
class ScheduleChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $weekStartDate) {}

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
        $url = rtrim(config('app.frontend_url'), '/')."/me/schedule?week_start_date={$this->weekStartDate}";

        return (new MailMessage)
            ->subject(__('notifications.schedule_changed.subject', [], $locale))
            ->line(__('notifications.schedule_changed.line', ['week' => $this->weekStartDate], $locale))
            ->action(__('notifications.schedule_changed.action', [], $locale), $url);
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(Employee $notifiable): array
    {
        $locale = $notifiable->user?->locale ?? config('app.fallback_locale');

        return [
            'title' => __('notifications.schedule_changed.subject', [], $locale),
            'body' => __('notifications.schedule_changed.line', ['week' => $this->weekStartDate], $locale),
            'url' => "/me/schedule?week_start_date={$this->weekStartDate}",
        ];
    }
}
