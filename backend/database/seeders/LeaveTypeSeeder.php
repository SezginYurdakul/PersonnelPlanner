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
        LeaveType::firstOrCreate(
            ['name' => 'Annual Leave - Vakantie'],
            ['requires_approval' => true],
        );

        LeaveType::firstOrCreate(
            ['name' => 'Sick Leave - Ziek'],
            ['requires_approval' => true],
        );

        LeaveType::firstOrCreate(
            ['name' => 'Short Excuse'],
            ['requires_approval' => false],
        );
    }
}
