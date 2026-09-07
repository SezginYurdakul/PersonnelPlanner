<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Lines\Models\Line;
use App\Modules\Lines\Models\SchedulingRole;
use App\Modules\Lines\Models\ShiftPattern;
use App\Modules\Scheduling\DTOs\UnfilledSlotData;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Models\ShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * ProjectPlan.md §11a.3a/§12.3b: a requires_coverage station role with zero assignments for
 * a shift it is "scheduled to run" blocks approval outright. "Scheduled to run" is defined
 * as the line/shift/role scope persisted on schedules.generated_scope from the planner's
 * most recent "Generate Suggestion" call - a schedule that was never generated (fully
 * manual) has no scope, so this check is a no-op for it.
 *
 * Re-run at approve time (not just generation time), since a manual edit after generation
 * could remove the only assignment for a mandatory station.
 */
final class MandatoryCoverageChecker
{
    /**
     * @return Collection<int, UnfilledSlotData>
     */
    public function unresolvedBlockingSlots(Schedule $schedule): Collection
    {
        $scope = $schedule->generated_scope;

        if ($scope === null) {
            return collect();
        }

        $lineIds = $scope['line_ids'] ?? [];
        $shiftPatternIds = $scope['shift_pattern_ids'] ?? [];
        $roleIds = $scope['role_ids'] ?? null;

        $mandatoryStations = SchedulingRole::query()
            ->where('role_kind', SchedulingRole::KIND_STATION)
            ->where('requires_coverage', true)
            ->when($lineIds !== [], fn ($q) => $q->whereIn('line_id', $lineIds))
            ->when($roleIds !== null, fn ($q) => $q->whereIn('id', $roleIds))
            ->get();

        if ($mandatoryStations->isEmpty()) {
            return collect();
        }

        $shiftPatterns = ShiftPattern::query()->whereIn('id', $shiftPatternIds)->get();
        $days = $this->weekDays($schedule->week_start_date);

        $blocking = collect();

        foreach ($mandatoryStations as $role) {
            $line = $role->line;

            foreach ($days as $day) {
                foreach ($shiftPatterns as $shiftPattern) {
                    $hasAssignment = ShiftAssignment::query()
                        ->where('schedule_id', $schedule->id)
                        ->where('role_id', $role->id)
                        ->where('work_date', $day->format('Y-m-d'))
                        ->where('shift_pattern_id', $shiftPattern->id)
                        ->exists();

                    if (! $hasAssignment) {
                        $blocking->push(new UnfilledSlotData(
                            line: $line,
                            shiftPattern: $shiftPattern,
                            workDate: $day->copy(),
                            role: $role,
                            blocking: true,
                            alternatives: collect(),
                        ));
                    }
                }
            }
        }

        return $blocking;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function weekDays(Carbon $weekStartDate): Collection
    {
        return collect(range(0, 6))->map(fn (int $offset) => $weekStartDate->copy()->addDays($offset));
    }
}
