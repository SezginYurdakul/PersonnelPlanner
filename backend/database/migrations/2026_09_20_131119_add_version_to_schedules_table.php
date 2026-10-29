<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How many times this schedule has been approved, starting at 1 - bumped by
     * ScheduleService::approve() only when the schedule was ALREADY approved before (a
     * correction, via the unlock -> manually fix -> resubmit -> approve cycle), never on
     * the first approval. Drives which notification fires (ScheduleChanged vs the new
     * ScheduleCorrected, which asks for an acknowledgment) and which version an
     * employee's schedule_acknowledgments row is for.
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('was_approved_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
