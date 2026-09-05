<?php

namespace App\Modules\Auth;

use App\Modules\Auth\Contracts\AuthServiceContract;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceContract::class, AuthService::class);
    }
}
