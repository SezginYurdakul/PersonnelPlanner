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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained('lines')->restrictOnDelete();
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->date('work_date');
            $table->foreignId('role_id')->nullable()->constrained('scheduling_roles')->nullOnDelete();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->enum('status', ['proposed', 'confirmed'])->default('proposed');
            $table->enum('source', ['auto_suggested', 'manual'])->default('auto_suggested');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
            $table->index(['schedule_id', 'line_id', 'work_date']);
            $table->index(['schedule_id', 'line_id', 'role_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
