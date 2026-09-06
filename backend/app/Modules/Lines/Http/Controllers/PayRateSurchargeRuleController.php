<?php

namespace App\Modules\Lines\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Lines\Contracts\PayRateSurchargeRuleServiceContract;
use App\Modules\Lines\Http\Requests\StorePayRateSurchargeRuleRequest;
use App\Modules\Lines\Http\Requests\UpdatePayRateSurchargeRuleRequest;
use App\Modules\Lines\Http\Resources\PayRateSurchargeRuleResource;
use App\Modules\Lines\Models\PayRateSurchargeRule;

class PayRateSurchargeRuleController extends Controller
{
    public function __construct(
        private readonly PayRateSurchargeRuleServiceContract $rules,
    ) {}

    public function index()
    {
        return PayRateSurchargeRuleResource::collection($this->rules->list());
    }

    public function store(StorePayRateSurchargeRuleRequest $request): PayRateSurchargeRuleResource
    {
        return new PayRateSurchargeRuleResource($this->rules->create($request->toDto()));
    }

    public function show(PayRateSurchargeRule $payRateSurchargeRule): PayRateSurchargeRuleResource
    {
        return new PayRateSurchargeRuleResource($payRateSurchargeRule);
    }

    public function update(UpdatePayRateSurchargeRuleRequest $request, PayRateSurchargeRule $payRateSurchargeRule): PayRateSurchargeRuleResource
    {
        return new PayRateSurchargeRuleResource($this->rules->update($payRateSurchargeRule, $request->toDto()));
    }

    public function destroy(PayRateSurchargeRule $payRateSurchargeRule): PayRateSurchargeRuleResource
    {
        return new PayRateSurchargeRuleResource($this->rules->deactivate($payRateSurchargeRule));
    }
}
