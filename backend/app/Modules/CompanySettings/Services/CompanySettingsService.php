<?php

namespace App\Modules\CompanySettings\Services;

use App\Modules\CompanySettings\Contracts\CompanySettingsServiceContract;
use App\Modules\CompanySettings\DTOs\CompanySettingsData;
use App\Modules\CompanySettings\Models\CompanySetting;

final class CompanySettingsService implements CompanySettingsServiceContract
{
    public function get(): CompanySetting
    {
        return CompanySetting::current();
    }

    public function update(CompanySettingsData $data): CompanySetting
    {
        $setting = CompanySetting::current();
        $setting->update($data->toArray());

        return $setting->refresh();
    }
}
