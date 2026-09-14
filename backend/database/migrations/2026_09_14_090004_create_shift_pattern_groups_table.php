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
        // A named set of concrete shift patterns (e.g. "Pattern A": Day 08-16, Afternoon
        // 16-00, Night 00-08 vs. "Pattern B": Day 10-18, Afternoon 18-02, Night 02-10) - a
        // Line picks one group, and every shift on that line uses that group's hours for
        // each shift type. The shift type names (Day/Afternoon/Night) stay the same
        // everywhere; only their hours vary by group.
        Schema::create('shift_pattern_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_pattern_groups');
    }
};
