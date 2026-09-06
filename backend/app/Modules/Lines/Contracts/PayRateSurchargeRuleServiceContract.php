<?php

namespace App\Modules\Lines\Contracts;

use App\Modules\Lines\DTOs\PayRateSurchargeRuleData;
use App\Modules\Lines\Models\PayRateSurchargeRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

interface PayRateSurchargeRuleServiceContract
{
    /**
     * @return Collection<int, PayRateSurchargeRule>
     */
    public function list(): Collection;

    public function create(PayRateSurchargeRuleData $data): PayRateSurchargeRule;

    public function update(PayRateSurchargeRule $rule, PayRateSurchargeRuleData $data): PayRateSurchargeRule;

    public function deactivate(PayRateSurchargeRule $rule): PayRateSurchargeRule;

    /**
     * Computes the effective cost of a shift instance given a base hourly rate,
     * by splitting the shift into segments against all active surcharge rules and
     * applying the single highest overlapping surcharge percentage to each segment
     * (ProjectPlan.md §11.2c - surcharges do not stack).
     */
    public function computeShiftCost(float $baseHourlyRate, Carbon $shiftStart, Carbon $shiftEnd): float;
}
