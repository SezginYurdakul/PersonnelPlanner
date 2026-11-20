<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A qualified station the employee prefers (ProjectPlan.md §11a.3): the roster planner
     * keeps them on it, or at least on its line, and only moves them elsewhere when a higher
     * rule needs it (§28.2 tier 5). Never a restriction - the qualification row itself is.
     */
    public function up(): void
    {
        Schema::table('employee_scheduling_roles', function (Blueprint $table) {
            $table->boolean('is_preferred')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_scheduling_roles', function (Blueprint $table) {
            $table->dropColumn('is_preferred');
        });
    }
};
