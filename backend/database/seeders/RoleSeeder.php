<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the top-level roles from ProjectPlan.md §6/§15.2:
     * - admin: full scheduling access, but cannot approve/reject/unlock a schedule
     * - super_admin: everything admin can do, plus the sole authority to approve a
     *   submitted plan, reject it back to the admin, or unlock an already-approved plan
     * - user: employee-facing, read-only schedule access scoped by visibility_scope
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }
}
