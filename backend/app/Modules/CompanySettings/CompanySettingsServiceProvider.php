<?php

namespace App\Modules\CompanySettings;

use App\Modules\CompanySettings\Contracts\CompanySettingsServiceContract;
use App\Modules\CompanySettings\Services\CompanySettingsService;
use Illuminate\Support\ServiceProvider;

class CompanySettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanySettingsServiceContract::class, CompanySettingsService::class);
    }
}
