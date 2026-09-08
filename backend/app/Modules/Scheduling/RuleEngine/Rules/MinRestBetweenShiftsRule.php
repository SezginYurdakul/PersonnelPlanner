<?php

namespace App\Modules\Scheduling\RuleEngine\Rules;

use App\Modules\Scheduling\Models\ShiftAssignment;
use App\Modules\Scheduling\RuleEngine\Contracts\SchedulingRule;
use App\Modules\Scheduling\RuleEngine\DTOs\EmployeeScheduleContext;
use App\Modules\Scheduling\RuleEngine\ValueObjects\RuleResult;
use Illuminate\Support\Carbon;

/**
 * Enforces at least MIN_REST_HOURS of consecutive rest between the end of one assignment
 * and the start of the next, confirmed with the user as a flat requirement (not the Dutch
 * ATW's "reducible to 8 hours once per week" carve-out mentioned in ProjectPlan.md §16.3 -
 * that exception was deliberately not implemented per the user's explicit requirement).
 *
 * Only the single nearest assignment on each side of the candidate slot matters - anything
 * further away only makes the gap larger, never smaller. A full rest day (or more) between
 * two shifts is never flagged: worked examples (e.g. Monday 08:00-16:00 -> Wednesday
 * 08:00-16:00, a 40-hour gap) confirm plain hour-gap arithmetic between nearest-neighbor
 * assignments already clears the threshold by a wide margin whenever a full day separates
 * them, so no explicit "is there a full rest day in between" branch is needed here.
 */
final class MinRestBetweenShiftsRule implements SchedulingRule
{
    public const MIN_REST_HOURS = 11.0;

    public function key(): string
    {
        return 'min_rest_between_shifts';
    }

    public function evaluate(EmployeeScheduleContext $context): RuleResult
    {
        $ranges = $context->adjacentAssignments
            ->map(fn (ShiftAssignment $assignment) => $this->absoluteRange($assignment))
            ->filter();

        $preceding = $ranges
            ->filter(fn (array $range) => $range[1]->lte($context->candidateStart))
            ->sortByDesc(fn (array $range) => $range[1])
            ->first();

        if ($preceding !== null) {
            $gapBefore = $preceding[1]->floatDiffInHours($context->candidateStart);

            if ($gapBefore < self::MIN_REST_HOURS) {
                return RuleResult::violation(
                    $this->key(),
                    'Only '.round($gapBefore, 1).'h rest since previous shift',
                );
            }
        }

        $following = $ranges
            ->filter(fn (array $range) => $range[0]->gte($context->candidateEnd))
            ->sortBy(fn (array $range) => $range[0])
            ->first();

        if ($following !== null) {
            $gapAfter = $context->candidateEnd->floatDiffInHours($following[0]);

            if ($gapAfter < self::MIN_REST_HOURS) {
                return RuleResult::violation(
                    $this->key(),
                    'Only '.round($gapAfter, 1).'h rest before next shift',
                );
            }
        }

        return RuleResult::pass($this->key());
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function absoluteRange(ShiftAssignment $assignment): ?array
    {
        $start = $assignment->effectiveStart();
        $end = $assignment->effectiveEnd();

        if ($start === null || $end === null) {
            return null;
        }

        $startsAt = Carbon::parse($assignment->work_date->format('Y-m-d').' '.$start);
        $endsAt = Carbon::parse($assignment->work_date->format('Y-m-d').' '.$end);

        if ($assignment->effectiveCrossesMidnight()) {
            $endsAt->addDay();
        }

        return [$startsAt, $endsAt];
    }
}
