<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per (schedule, employee, version) once that employee has confirmed they saw
     * a correction to an already-approved, already-published schedule (ScheduleCorrected
     * notification - see Schedule.version and ScheduleService::approve()). `version` is
     * part of the row, not just a filter, so a later correction naturally needs a fresh
     * acknowledgment - an old ack for version 1 never counts for version 2. The unique
     * constraint makes acknowledging twice for the same version a no-op, not an error.
     */
    public function up(): void
    {
        Schema::create('schedule_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(['schedule_id', 'employee_id', 'version'], 'schedule_acknowledgment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_acknowledgments');
    }
};
