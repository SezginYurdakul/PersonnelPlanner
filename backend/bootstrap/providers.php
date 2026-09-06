<?php

use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Lines\LinesServiceProvider;
use App\Modules\Staff\StaffServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Module service providers
    AuthServiceProvider::class,
    LinesServiceProvider::class,
    StaffServiceProvider::class,
];
