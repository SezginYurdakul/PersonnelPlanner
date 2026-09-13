<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('invitation_token')->nullable()->unique()->after('password');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
            $table->timestamp('activated_at')->nullable()->after('invited_at');
        });

        // An invited-but-not-yet-completed user has no password yet and hasn't picked a
        // language (that happens on the complete-invitation screen, ProjectPlan.md §8g) -
        // both columns must accept a temporary null/empty state until then. `locale` is
        // backed by a Postgres CHECK constraint (no native enum type), which requires raw
        // SQL to relax - the doctrine/dbal package this project doesn't install is what
        // Schema::change() needs to do it generically.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN locale DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitation_token', 'invited_at', 'activated_at']);
            $table->string('password')->nullable(false)->change();
        });

        DB::statement('ALTER TABLE users ALTER COLUMN locale SET NOT NULL');
    }
};
