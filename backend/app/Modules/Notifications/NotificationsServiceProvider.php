<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Contracts\PushSubscriptionServiceContract;
use App\Modules\Notifications\Services\BlockedMailDomainFilter;
use App\Modules\Notifications\Services\PushSubscriptionService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushSubscriptionServiceContract::class, PushSubscriptionService::class);
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, BlockedMailDomainFilter::class);
    }
}
