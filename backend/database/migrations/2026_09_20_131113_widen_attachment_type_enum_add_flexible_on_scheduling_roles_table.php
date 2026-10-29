<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres enforces Laravel's enum() column via a CHECK constraint, not a native enum
     * type - widening the allowed values (adding 'flexible') means dropping and recreating
     * that constraint directly, same as the earlier migration that added 'shift_lead'.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE scheduling_roles DROP CONSTRAINT scheduling_roles_attachment_type_check');
        DB::statement(
            'ALTER TABLE scheduling_roles ADD CONSTRAINT scheduling_roles_attachment_type_check '.
            "CHECK (attachment_type IN ('station', 'line', 'none', 'shift_lead', 'flexible'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE scheduling_roles DROP CONSTRAINT scheduling_roles_attachment_type_check');
        DB::statement(
            'ALTER TABLE scheduling_roles ADD CONSTRAINT scheduling_roles_attachment_type_check '.
            "CHECK (attachment_type IN ('station', 'line', 'none', 'shift_lead'))"
        );
    }
};
