<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin invites a new account (ProjectPlan.md §8g) - carries the tokenized
 * link to the frontend's set-password screen. Queued since the invite HTTP response
 * shouldn't wait on mail delivery.
 */
class UserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $invitationToken) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/complete-invitation?token='.$this->invitationToken;

        return (new MailMessage)
            ->subject(__('notifications.user_invited.subject'))
            ->line(__('notifications.user_invited.line'))
            ->action(__('notifications.user_invited.action'), $url);
    }
}
