<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Contracts\PushSubscriptionServiceContract;
use App\Modules\Notifications\Services\PushSubscriptionService;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushSubscriptionServiceContract::class, PushSubscriptionService::class);
    }
}
