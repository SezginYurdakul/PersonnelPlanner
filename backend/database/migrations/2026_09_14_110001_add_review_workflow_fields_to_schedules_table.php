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
            // Permanent, never-cleared marker: once true, this schedule may never again be
            // regenerated via "Generate Suggestion" - even after an unlock sends it back to
            // draft for manual editing. Set the first time approve() succeeds.
            $table->boolean('was_approved_before')->default(false)->after('generated_scope');
            $table->text('rejection_note')->nullable()->after('was_approved_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['was_approved_before', 'rejection_note']);
        });
    }
};
