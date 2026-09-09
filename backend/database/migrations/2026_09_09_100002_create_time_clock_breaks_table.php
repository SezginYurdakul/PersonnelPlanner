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
        Schema::create('time_clock_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_clock_entry_id')->constrained()->cascadeOnDelete();
            $table->dateTime('break_start');
            $table->dateTime('break_end');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_clock_breaks');
    }
};
