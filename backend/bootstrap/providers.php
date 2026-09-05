<?php

use App\Modules\Auth\AuthServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Module service providers
    AuthServiceProvider::class,
];
