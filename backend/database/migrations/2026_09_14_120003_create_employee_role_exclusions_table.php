<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deliberately separate from `employee_scheduling_roles` (qualification: "can perform
     * this role"). This table is a second, independent layer: "must never be assigned to
     * this role", even if otherwise qualified - e.g. a medical restriction or personal
     * exemption unrelated to skill.
     */
    public function up(): void
    {
        Schema::create('employee_role_exclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'scheduling_role_id'], 'employee_role_exclusion_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_role_exclusions');
    }
};
