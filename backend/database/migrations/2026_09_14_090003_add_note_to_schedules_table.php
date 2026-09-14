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
            // A longer free-text explanation of why this particular draft/scenario was
            // saved (e.g. "cost-optimized, but leaves Lijn 3 short on Fridays") -
            // separate from `label`, which is the short title shown in list views.
            $table->text('note')->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
