<?php

namespace App\Modules\ShiftNotices;

use App\Modules\ShiftNotices\Contracts\ShiftNoticeServiceContract;
use App\Modules\ShiftNotices\Services\ShiftNoticeService;
use Illuminate\Support\ServiceProvider;

class ShiftNoticesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShiftNoticeServiceContract::class, ShiftNoticeService::class);
    }
}
