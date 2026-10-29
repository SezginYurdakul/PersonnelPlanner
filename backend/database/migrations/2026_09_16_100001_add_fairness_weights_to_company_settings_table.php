<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Multiplicative weights applied to an assignment's raw hours when computing its
     * contribution to an employee's cumulative fairness score (ProjectPlan's confirmed
     * two-dimensional weighting: shift-type x day-type, multiplied together - a Sunday
     * night shift ends up the heaviest-weighted combination). All default to 1.0 (no
     * weighting) so this is a no-op until a super_admin deliberately changes it.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('fairness_night_weight', 4, 2)->default(1.00)->after('week_start_day');
            $table->decimal('fairness_afternoon_weight', 4, 2)->default(1.00)->after('fairness_night_weight');
            $table->decimal('fairness_day_weight', 4, 2)->default(1.00)->after('fairness_afternoon_weight');
            $table->decimal('fairness_sunday_weight', 4, 2)->default(1.00)->after('fairness_day_weight');
            $table->decimal('fairness_saturday_weight', 4, 2)->default(1.00)->after('fairness_sunday_weight');
            $table->decimal('fairness_weekday_weight', 4, 2)->default(1.00)->after('fairness_saturday_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'fairness_night_weight',
                'fairness_afternoon_weight',
                'fairness_day_weight',
                'fairness_sunday_weight',
                'fairness_saturday_weight',
                'fairness_weekday_weight',
            ]);
        });
    }
};
