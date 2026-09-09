<?php

namespace App\Modules\CompanySettings\Contracts;

use App\Modules\CompanySettings\DTOs\CompanySettingsData;
use App\Modules\CompanySettings\Models\CompanySetting;

interface CompanySettingsServiceContract
{
    public function get(): CompanySetting;

    public function update(CompanySettingsData $data): CompanySetting;
}
