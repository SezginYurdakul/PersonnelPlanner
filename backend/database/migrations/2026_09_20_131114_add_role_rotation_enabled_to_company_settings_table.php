<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admin-configurable toggle for the role-specific rotation signal (ProjectPlan.md §12.6
     * item 4) - defaults to true (rotation on) since that is the behavior the fairness
     * review this field came out of was asking for; a factory that deliberately wants an
     * employee to specialize in one role rather than rotate can turn it off per the same
     * super_admin-gated fairness-weights endpoint the other scoring knobs use.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('role_rotation_enabled')->default(true)->after('fairness_weekday_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('role_rotation_enabled');
        });
    }
};
