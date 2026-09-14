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
        Schema::table('lines', function (Blueprint $table) {
            $table->foreignId('shift_pattern_group_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        // Backfill: point every existing line at whichever single-pattern group its
        // current shift assignments most commonly use, so existing schedules keep
        // resolving to the same concrete shift_pattern rows they already reference. A
        // line with no assignments yet is left null - the admin picks a group for it
        // explicitly before scheduling.
        $lines = DB::table('lines')->get();

        foreach ($lines as $line) {
            $mostUsedGroupId = DB::table('shift_assignments')
                ->join('shift_patterns', 'shift_assignments.shift_pattern_id', '=', 'shift_patterns.id')
                ->where('shift_assignments.line_id', $line->id)
                ->whereNotNull('shift_patterns.shift_pattern_group_id')
                ->selectRaw('shift_patterns.shift_pattern_group_id, count(*) as cnt')
                ->groupBy('shift_patterns.shift_pattern_group_id')
                ->orderByDesc('cnt')
                ->value('shift_pattern_group_id');

            if ($mostUsedGroupId !== null) {
                DB::table('lines')->where('id', $line->id)->update(['shift_pattern_group_id' => $mostUsedGroupId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_pattern_group_id');
        });
    }
};
