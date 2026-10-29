<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-role task-difficulty weight (ProjectPlan.md §12.6 item 7) - defaults to 1.00 (no
     * effect) so this is opt-in per role from the Roles admin screen, not a new global
     * toggle: a station's weighted hours are multiplied by it directly, and a secondary
     * task's marginal fairness credit (otherwise zero - it "rides" an existing shift) is
     * (weight - 1.00) times that shift's weighted hours, so a harder/less-liked task can be
     * made to count as extra load without inventing a second scoring dimension.
     */
    public function up(): void
    {
        Schema::table('scheduling_roles', function (Blueprint $table) {
            $table->decimal('fairness_weight', 4, 2)->default(1.00)->after('min_headcount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduling_roles', function (Blueprint $table) {
            $table->dropColumn('fairness_weight');
        });
    }
};
