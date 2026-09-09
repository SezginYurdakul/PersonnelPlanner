<?php

namespace Database\Seeders;

use App\Modules\CompanySettings\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Ensure the singleton company_settings row exists with sensible defaults.
     */
    public function run(): void
    {
        CompanySetting::current();
    }
}
