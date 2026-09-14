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
            // Lets the admin tell apart multiple draft/proposed scenarios for the same
            // week (e.g. "Plan A" vs "Cost-focused option") - null falls back to an
            // ordinal ("Draft 1", "Draft 2") in the frontend.
            $table->string('label')->nullable()->after('week_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
