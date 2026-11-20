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
 * ProjectPlan.md §11a.3a/§12.3b: a requires_coverage role with zero assignments for a shift
 * it is "scheduled to run" blocks approval outright (unresolvedBlockingSlots()). This applies
 * to both station roles AND secondary tasks marked requires_coverage=true (e.g. a mandatory
 * fault-response duty) - the latter is still only ever staffed from whoever already holds
 * that shift, never a new hire, but going unfilled blocks approval exactly like a mandatory
 * station would.
 *
 * unresolvedAdvisorySlots() is the non-mandatory ("Open") counterpart, computed the exact
 * same live way - the grid's `unfilled_slots` used to source these purely from the last
 * "Generate Suggestion" run's frozen snapshot (see ScheduleResource::reconciledUnfilledSlots()
 * for the blocking side of this same fix), which could only ever shrink, never grow: removing
 * the sole assignment for a non-mandatory secondary task made it disappear from the grid
 * entirely instead of reappearing as "Open".
 *
 * A `flexible`-attachment role (day-specific hours, no fixed shift pattern - ProjectPlan's
 * "Production Lead" style role) is excluded from both: it's staffed once per DAY, not once
 * per slot-type pattern, so looping it through `$patternsForLine` the same way a
 * station/secondary-task/shift-lead role is would multiply-count it (once per slot type in
 * scope) and falsely flag it unfilled up to 3x/day. Flexible roles were already deliberately
 * exempted from mandatory-coverage semantics entirely (assignFlexibleRoles() never pushes an
 * $unfilled entry either - "a soft suggestion, not a mandatory-coverage concept").
 *
 * "Scheduled to run" is defined as the line/shift/role scope persisted on
 * schedules.generated_scope from the planner's most recent "Generate Suggestion" call - a
 * schedule that was never generated (fully manual) has no scope, so this check is a no-op
 * for it.
 *
 * Re-run live (not just at generation time), since a manual edit after generation could
 * remove the only assignment for a role.
 */
final class MandatoryCoverageChecker
{
    /**
     * @return Collection<int, UnfilledSlotData>
     */
    public function unresolvedBlockingSlots(Schedule $schedule): Collection
    {
        return $this->unresolvedSlots($schedule, requiresCoverage: true);
    }

    /**
     * @return Collection<int, UnfilledSlotData>
     */
    public function unresolvedAdvisorySlots(Schedule $schedule): Collection
    {
        return $this->unresolvedSlots($schedule, requiresCoverage: false);
    }

    /**
     * @return Collection<int, UnfilledSlotData>
     */
    private function unresolvedSlots(Schedule $schedule, bool $requiresCoverage): Collection
    {
        $scope = $schedule->generated_scope;

        if ($scope === null) {
            return collect();
        }

        $lineIds = $scope['line_ids'] ?? [];
        $slotTypes = $scope['slot_types'] ?? [];
        $roleIds = $scope['role_ids'] ?? null;

        // The same "line_id|isoDayOfWeek|slotType" exclusion set ScheduleSuggestionService
        // itself checks - a day/line/slot the admin deliberately unchecked before generating
        // was never "scheduled to run" in the first place, so it must not come back as a
        // coverage gap (only present on `generated_scope` for schedules generated after this
        // field was added - older schedules have no exclusions recorded and are treated as
        // if none were made, same as any other missing scope field).
        $exclusionSet = collect($scope['line_slot_exclusions'] ?? [])
            ->mapWithKeys(fn (array $e) => ["{$e['line_id']}|{$e['day_of_week']}|{$e['slot_type']}" => true]);
        // The same for one station or task on a shift ("*": the whole day).
        $roleExclusionSet = collect($scope['role_slot_exclusions'] ?? [])
            ->mapWithKeys(fn (array $e) => ["{$e['role_id']}|{$e['day_of_week']}|".($e['slot_type'] ?? '*') => true]);

        $roles = SchedulingRole::query()
            ->where('requires_coverage', $requiresCoverage)
            // A plain station has no attachment_type at all (NULL) - SQL's != never matches
            // NULL either, so `where('attachment_type', '!=', 'flexible')` alone silently
            // dropped every ordinary station role (exactly the bug this comment now guards
            // against a second time: same NULL pitfall as line_id below, different column).
            ->where(fn ($q) => $q->where('attachment_type', '!=', 'flexible')->orWhereNull('attachment_type'))
            // A line-independent role (line_id IS NULL) must always stay in scope
            // regardless of which lines were generated - SQL's IN never matches NULL, so
            // whereIn('line_id', $lineIds) alone would silently drop every line-independent
            // role (e.g. a company-wide Shift Lead requirement) the moment any line is
            // selected, which is always in practice.
            ->when($lineIds !== [], fn ($q) => $q->where(
                fn ($q2) => $q2->whereIn('line_id', $lineIds)->orWhereNull('line_id')
            ))
            ->when($roleIds !== null, fn ($q) => $q->whereIn('id', $roleIds))
            ->with('line')
            ->get();

        if ($roles->isEmpty()) {
            return collect();
        }

        // A line-independent role (e.g. a company-wide Shift Lead requirement) has no line
        // of its own to resolve hours from - it borrows the shift_pattern_group of any one
        // in-scope line instead, exactly like ScheduleSuggestionService::assignShiftLeads()
        // does when placing it.
        $inScopeLines = $lineIds !== []
            ? Line::query()->whereIn('id', $lineIds)->with('operatingClosures')->get()
            : collect();
        $fallbackGroupId = $inScopeLines->whereNotNull('shift_pattern_group_id')->first()?->shift_pattern_group_id;

        // Keyed by "lineId|slotType" - the same resolution ScheduleSuggestionService uses,
        // since a role's line determines which concrete pattern row (and therefore hours)
        // applies (ProjectPlan.md: hours vary by the line's shift_pattern_group, the slot
        // type name does not).
        $shiftPatternsByLineAndSlot = ShiftPattern::query()
            ->whereIn(
                'shift_pattern_group_id',
                $roles->pluck('line.shift_pattern_group_id')->filter()->push($fallbackGroupId)->filter()->unique(),
            )
            ->whereIn('slot_type', $slotTypes)
            ->get()
            ->groupBy('shift_pattern_group_id');

        $days = $this->weekDays($schedule->week_start_date);

        $unresolved = collect();

        foreach ($roles as $role) {
            $line = $role->line;
            $groupId = $line?->shift_pattern_group_id ?? $fallbackGroupId;
            $patternsForLine = $groupId !== null ? $shiftPatternsByLineAndSlot->get($groupId, collect()) : collect();

            // Secondary tasks (and shift lead) keep the existing "at least 1" requirement -
            // min_headcount only applies to stations, where it's a real per-shift
            // headcount, not "at least present".
            $requiredCount = $role->isStation() ? max(1, $role->min_headcount) : 1;

            foreach ($days as $day) {
                foreach ($patternsForLine as $shiftPattern) {
                    $isoDay = $day->dayOfWeekIso;
                    $slotType = $shiftPattern->slot_type;

                    // Mirrors generate()'s own "nothing to schedule here" skip: a
                    // permanently closed line/day (Line::isClosedOn()) or a day the admin
                    // excluded just for this run was never staffed by design, so it's not a
                    // gap. A line-independent role (no single $line of its own) only skips
                    // once EVERY in-scope line is closed/excluded for this day/slot - same
                    // as generate() only needing a lead where some line has an open slot to
                    // borrow (ScheduleSuggestionService::shiftLeadLine()).
                    $scheduledToRun = $line !== null
                        ? $this->isLineOpenForSlot($line, $isoDay, $slotType, $exclusionSet)
                        : $inScopeLines->contains(fn (Line $l) => $this->isLineOpenForSlot($l, $isoDay, $slotType, $exclusionSet));

                    if (! $scheduledToRun || $roleExclusionSet->has("{$role->id}|{$isoDay}|{$slotType}") || $roleExclusionSet->has("{$role->id}|{$isoDay}|*")) {
                        continue;
                    }

                    // Every role reaching this point (station, ordinary secondary task, or
                    // shift lead - flexible was excluded above) is created with a real,
                    // concrete shift_pattern_id (ScheduleSuggestionService always sets one),
                    // so a line-SPECIFIC role's presence check must be pattern-matched -
                    // otherwise a role filled on, say, the day shift would wrongly read as
                    // "covered" for the afternoon/night shifts of that same day too.
                    //
                    // A line-INDEPENDENT role (line_id null - Silo Takibi, Vardiya Sefi) has
                    // no such single pattern: ScheduleSuggestionService::assignSecondaryTask()
                    // resolves its concrete shift_pattern_id dynamically, from whichever line
                    // the first already-present station assignment that slot happens to
                    // anchor to - never the fixed $fallbackGroupId this method uses purely to
                    // enumerate "one slot per day/slotType" above. Matching on the fallback's
                    // own pattern_id here would only ever recognize an assignment that
                    // happened to land on that one line's pattern, and flag a duplicate
                    // "unfilled" slot_type for every day it was satisfied via a different
                    // line's pattern instead (e.g. Line 2's 10:00-18:00 "day" pattern while
                    // this loop's $shiftPattern is Lijn 1's 08:00-16:00 "day" pattern) - a
                    // real assignment, phantom-flagged as missing. Matching by slot_type
                    // alone for these roles accepts any in-scope line's pattern as fulfilling
                    // the single daily requirement, which is what "line-independent" means.
                    $actualCount = ShiftAssignment::query()
                        ->where('schedule_id', $schedule->id)
                        ->where('role_id', $role->id)
                        ->where('work_date', $day->format('Y-m-d'))
                        ->when(
                            $role->line_id === null,
                            fn ($q) => $q->whereHas('shiftPattern', fn ($q2) => $q2->where('slot_type', $slotType)),
                            fn ($q) => $q->where('shift_pattern_id', $shiftPattern->id),
                        )
                        ->count();

                    if ($actualCount < $requiredCount) {
                        $unresolved->push(new UnfilledSlotData(
                            line: $line,
                            shiftPattern: $shiftPattern,
                            workDate: $day->copy(),
                            role: $role,
                            blocking: $requiresCoverage,
                            alternatives: collect(),
                            missingCount: $requiredCount - $actualCount,
                        ));
                    }
                }
            }
        }

        return $unresolved;
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function weekDays(Carbon $weekStartDate): Collection
    {
        return collect(range(0, 6))->map(fn (int $offset) => $weekStartDate->copy()->addDays($offset));
    }

    /**
     * @param  Collection<string, true>  $exclusionSet
     */
    private function isLineOpenForSlot(Line $line, int $isoDayOfWeek, string $slotType, Collection $exclusionSet): bool
    {
        if ($line->isClosedOn($isoDayOfWeek, $slotType)) {
            return false;
        }

        return ! $exclusionSet->has("{$line->id}|{$isoDayOfWeek}|{$slotType}");
    }
}
