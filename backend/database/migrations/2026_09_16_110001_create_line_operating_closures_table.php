<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Symmetric to employee_unavailabilities: stores only the exceptions to the default
     * "line runs every day, every shift" - a line with no rows here operates around the
     * clock. day_of_week is ISO (1=Monday..7=Sunday), independent of the company's
     * configurable week-start-day setting.
     */
    public function up(): void
    {
        Schema::create('line_operating_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->enum('slot_type', ['day', 'afternoon', 'night']);
            $table->timestamps();

            $table->unique(['line_id', 'day_of_week', 'slot_type'], 'line_operating_closure_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_operating_closures');
    }
};
