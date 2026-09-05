<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default admin account for local development.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@personnelplanner.test'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'visibility_scope' => 'company',
                'locale' => 'en',
                'is_active' => true,
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
