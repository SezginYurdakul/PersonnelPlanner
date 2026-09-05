<?php

use App\Modules\Auth\AuthServiceProvider;
use App\Modules\Staff\StaffServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Module service providers
    AuthServiceProvider::class,
    StaffServiceProvider::class,
];
