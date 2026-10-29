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
        Schema::table('employment_terms', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_weekly_hours')->nullable()->after('max_weekly_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employment_terms', function (Blueprint $table) {
            $table->dropColumn('min_weekly_hours');
        });
    }
};
