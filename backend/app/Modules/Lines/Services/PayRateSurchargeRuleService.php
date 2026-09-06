<?php

namespace App\Modules\Lines\Services;

use App\Modules\Lines\Contracts\PayRateSurchargeRuleServiceContract;
use App\Modules\Lines\DTOs\PayRateSurchargeRuleData;
use App\Modules\Lines\Models\PayRateSurchargeRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class PayRateSurchargeRuleService implements PayRateSurchargeRuleServiceContract
{
    /**
     * @return Collection<int, PayRateSurchargeRule>
     */
    public function list(): Collection
    {
        return PayRateSurchargeRule::query()->orderBy('name')->get();
    }

    public function create(PayRateSurchargeRuleData $data): PayRateSurchargeRule
    {
        return PayRateSurchargeRule::create($data->toArray());
    }

    public function update(PayRateSurchargeRule $rule, PayRateSurchargeRuleData $data): PayRateSurchargeRule
    {
        $rule->update($data->toArray());

        return $rule->refresh();
    }

    public function deactivate(PayRateSurchargeRule $rule): PayRateSurchargeRule
    {
        $rule->update(['is_active' => false]);

        return $rule->refresh();
    }

    public function computeShiftCost(float $baseHourlyRate, Carbon $shiftStart, Carbon $shiftEnd): float
    {
        $rules = PayRateSurchargeRule::query()->where('is_active', true)->get();

        // Build the set of minute-offsets (relative to shift start) where any rule's
        // window begins or ends within the shift - these are the only points where the
        // "highest applicable surcharge" can change, so they define our segment boundaries.
        $totalMinutes = $shiftStart->diffInMinutes($shiftEnd);
        $boundaries = [0, $totalMinutes];

        foreach ($rules as $rule) {
            // ruleOccurrencesWithinShift() already clamps occurrences to [$shiftStart, $shiftEnd],
            // so these offsets are always within [0, $totalMinutes].
            foreach ($this->ruleOccurrencesWithinShift($rule, $shiftStart, $shiftEnd) as [$occStart, $occEnd]) {
                $boundaries[] = $shiftStart->diffInMinutes($occStart);
                $boundaries[] = $shiftStart->diffInMinutes($occEnd);
            }
        }

        $boundaries = array_values(array_unique($boundaries));
        sort($boundaries);

        $totalCost = 0.0;

        for ($i = 0; $i < count($boundaries) - 1; $i++) {
            $segmentStartOffset = $boundaries[$i];
            $segmentEndOffset = $boundaries[$i + 1];
            $segmentMinutes = $segmentEndOffset - $segmentStartOffset;

            if ($segmentMinutes <= 0) {
                continue;
            }

            $segmentMidpoint = $shiftStart->copy()->addMinutes((int) (($segmentStartOffset + $segmentEndOffset) / 2));
            $highestSurcharge = $this->highestSurchargeAt($rules, $segmentMidpoint);

            $segmentHours = $segmentMinutes / 60;
            $totalCost += $segmentHours * $baseHourlyRate * (1 + $highestSurcharge / 100);
        }

        return round($totalCost, 2);
    }

    /**
     * @param  Collection<int, PayRateSurchargeRule>  $rules
     */
    private function highestSurchargeAt(Collection $rules, Carbon $instant): float
    {
        $highest = 0.0;

        foreach ($rules as $rule) {
            if ($this->ruleAppliesAt($rule, $instant)) {
                $highest = max($highest, (float) $rule->surcharge_percentage);
            }
        }

        return $highest;
    }

    private function ruleAppliesAt(PayRateSurchargeRule $rule, Carbon $instant): bool
    {
        $isoDay = $instant->dayOfWeekIso; // 1 (Mon) - 7 (Sun)
        $time = $instant->format('H:i:s');
        $start = Carbon::parse($rule->start_time)->format('H:i:s');
        $end = Carbon::parse($rule->end_time)->format('H:i:s');

        if (! $rule->crosses_midnight) {
            $withinWindow = $time >= $start && $time < $end;

            return $withinWindow && in_array($isoDay, $rule->days_of_week, true);
        }

        // A crossing-midnight window (e.g. 18:00-06:00) is "active" either from start_time
        // to midnight on a matching day, or from midnight to end_time on the day *after*
        // a matching day.
        if ($time >= $start) {
            return in_array($isoDay, $rule->days_of_week, true);
        }

        if ($time < $end) {
            $previousIsoDay = $isoDay === 1 ? 7 : $isoDay - 1;

            return in_array($previousIsoDay, $rule->days_of_week, true);
        }

        return false;
    }

    /**
     * Placeholder for potential future multi-occurrence expansion; a shift within a
     * single day only needs the rule's own boundaries checked once per day it spans.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function ruleOccurrencesWithinShift(PayRateSurchargeRule $rule, Carbon $shiftStart, Carbon $shiftEnd): array
    {
        $occurrences = [];
        $cursor = $shiftStart->copy()->startOfDay();

        while ($cursor->lte($shiftEnd)) {
            $start = $cursor->copy()->setTimeFromTimeString($rule->start_time);
            $end = $cursor->copy()->setTimeFromTimeString($rule->end_time);

            if ($rule->crosses_midnight) {
                $end->addDay();
            }

            if ($start->lt($shiftEnd) && $end->gt($shiftStart)) {
                $occurrences[] = [$start->max($shiftStart), $end->min($shiftEnd)];
            }

            $cursor->addDay();
        }

        return $occurrences;
    }
}
