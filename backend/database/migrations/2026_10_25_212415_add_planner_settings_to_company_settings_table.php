<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The roster planner's business settings (ProjectPlan.md §28), super_admin-editable:
     * weekend policy, the weights of the fairness spreads (nights, Sundays, Saturdays,
     * rotation breaks), whether the weekly rotation applies, the most leads a shift may get,
     * and how many weeks each solve looks ahead. Defaults are the values the planner was
     * tuned and verified with.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('planner_weekend_policy', 20)->default('fair');
            $table->unsignedTinyInteger('planner_night_weight')->default(1);
            $table->unsignedTinyInteger('planner_sunday_weight')->default(3);
            $table->unsignedTinyInteger('planner_saturday_weight')->default(3);
            $table->unsignedTinyInteger('planner_rotation_break_weight')->default(3);
            $table->boolean('planner_rotation_enabled')->default(true);
            $table->unsignedTinyInteger('planner_max_leads_per_shift')->default(2);
            $table->unsignedTinyInteger('planner_horizon_weeks')->default(8);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'planner_weekend_policy',
                'planner_night_weight',
                'planner_sunday_weight',
                'planner_saturday_weight',
                'planner_rotation_break_weight',
                'planner_rotation_enabled',
                'planner_max_leads_per_shift',
                'planner_horizon_weeks',
            ]);
        });
    }
};
