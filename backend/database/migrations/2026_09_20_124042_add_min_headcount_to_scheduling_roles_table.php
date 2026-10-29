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
        Schema::table('scheduling_roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_headcount')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduling_roles', function (Blueprint $table) {
            $table->dropColumn('min_headcount');
        });
    }
};
