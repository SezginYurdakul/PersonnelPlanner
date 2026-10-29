<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per employee per approved week's worth of weighted hours - written once,
     * when that week's Schedule is approved (never for draft/proposed regeneration
     * attempts, so re-running "Generate Suggestion" doesn't inflate the score). The
     * suggestion engine's fairness ranking sums recent rows (last N weeks) per employee
     * instead of only the in-progress generation pass's own tally.
     */
    public function up(): void
    {
        Schema::create('employee_fairness_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->decimal('weighted_hours', 8, 2);
            $table->timestamps();

            $table->unique(['employee_id', 'schedule_id'], 'employee_fairness_scores_unique');
            $table->index(['employee_id', 'week_start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_fairness_scores');
    }
};
