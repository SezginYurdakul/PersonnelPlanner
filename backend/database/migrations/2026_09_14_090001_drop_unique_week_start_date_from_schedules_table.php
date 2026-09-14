<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Multiple draft/proposed schedules may now coexist for the same week (the
            // admin drafts alternative scenarios before picking one) - the constraint
            // enforcing "at most one approved schedule per week" instead lives in
            // ScheduleService::approve() (application-layer, since a partial unique index
            // on status='approved' isn't portable across the DB drivers this project might
            // run under).
            $table->dropUnique(['week_start_date']);
            $table->index('week_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['week_start_date']);
            $table->unique('week_start_date');
        });
    }
};
