<?php

namespace Database\Seeders;

use App\Modules\Leave\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Seed the leave types from ProjectPlan.md §13.3.
     */
    public function run(): void
    {
        LeaveType::updateOrCreate(
            ['name' => 'Annual Leave - Vakantie'],
            ['slug' => LeaveType::SLUG_VAKANTIE, 'requires_approval' => true],
        );

        LeaveType::updateOrCreate(
            ['name' => 'Sick Leave - Ziek'],
            ['slug' => LeaveType::SLUG_ZIEK, 'requires_approval' => true],
        );

        LeaveType::firstOrCreate(
            ['name' => 'Short Excuse'],
            ['requires_approval' => false],
        );
    }
}
