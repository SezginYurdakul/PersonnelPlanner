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
        Schema::table('shift_patterns', function (Blueprint $table) {
            $table->foreignId('shift_pattern_group_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
            // The stable "kind" of shift this row represents (day/afternoon/night) -
            // shared across every group, so a Line's Day Shift always resolves to
            // whichever concrete row matches its group's day slot, regardless of that
            // row's actual hours.
            $table->string('slot_type')->nullable()->after('shift_pattern_group_id');

            $table->unique(['shift_pattern_group_id', 'slot_type']);
        });

        // Backfill: every existing shift pattern (Day/Afternoon/Night) already forms one
        // coherent set used together across lines - they become the slots of a single new
        // "Pattern A" group, not separate one-pattern groups each, so existing
        // schedules/assignments keep resolving to the same concrete shift_pattern rows.
        $patterns = DB::table('shift_patterns')->get();

        if ($patterns->isNotEmpty()) {
            $groupId = DB::table('shift_pattern_groups')->insertGetId([
                'name' => 'Pattern A',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($patterns as $pattern) {
                $slotType = match (true) {
                    str_contains(strtolower($pattern->name), 'night') => 'night',
                    str_contains(strtolower($pattern->name), 'afternoon') => 'afternoon',
                    default => 'day',
                };

                DB::table('shift_patterns')->where('id', $pattern->id)->update([
                    'shift_pattern_group_id' => $groupId,
                    'slot_type' => $slotType,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_patterns', function (Blueprint $table) {
            $table->dropUnique(['shift_pattern_group_id', 'slot_type']);
            $table->dropConstrainedForeignId('shift_pattern_group_id');
            $table->dropColumn('slot_type');
        });
    }
};
