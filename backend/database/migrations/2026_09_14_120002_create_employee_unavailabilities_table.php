<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores only the exceptions to the default "available every day, every shift" - an
     * employee with no rows here is fully available. `day_of_week` is ISO (1=Monday..
     * 7=Sunday), matching Carbon::dayOfWeekIso, so it stays independent of the company's
     * configurable week-start-day setting.
     */
    public function up(): void
    {
        Schema::create('employee_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->enum('slot_type', ['day', 'afternoon', 'night']);
            $table->timestamps();

            $table->unique(['employee_id', 'day_of_week', 'slot_type'], 'employee_unavailability_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_unavailabilities');
    }
};
