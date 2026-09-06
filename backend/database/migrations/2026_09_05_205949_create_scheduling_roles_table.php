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
        Schema::create('scheduling_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('line_id')->nullable()->constrained('lines')->cascadeOnDelete();
            $table->enum('role_kind', ['station', 'secondary_task']);
            $table->boolean('requires_coverage')->default(true);
            $table->enum('attachment_type', ['station', 'line', 'none'])->nullable();
            $table->foreignId('attached_station_role_id')->nullable()->constrained('scheduling_roles')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('line_id');
            $table->index(['role_kind', 'requires_coverage']);
            $table->index('attached_station_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduling_roles');
    }
};
