<?php

namespace App\Modules\CompanySettings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanySettings\Contracts\CompanySettingsServiceContract;
use App\Modules\CompanySettings\Http\Requests\UpdateCompanySettingsRequest;
use App\Modules\CompanySettings\Http\Requests\UpdateFairnessWeightsRequest;
use App\Modules\CompanySettings\Http\Requests\UpdatePlannerSettingsRequest;
use App\Modules\CompanySettings\Http\Resources\CompanySettingResource;

class CompanySettingsController extends Controller
{
    public function __construct(private readonly CompanySettingsServiceContract $settings) {}

    public function show(): CompanySettingResource
    {
        return new CompanySettingResource($this->settings->get());
    }

    public function update(UpdateCompanySettingsRequest $request): CompanySettingResource
    {
        return new CompanySettingResource($this->settings->update($request->toDto()));
    }

    /**
     * Super_admin only (route-gated) - the fairness scoring weights are a distinct, more
     * sensitive knob than the rest of company settings.
     */
    public function updateFairnessWeights(UpdateFairnessWeightsRequest $request): CompanySettingResource
    {
        return new CompanySettingResource($this->settings->updateFairnessWeights($request->toDto()));
    }

    /**
     * Super_admin only (route-gated) - the roster planner's business settings
     * (ProjectPlan.md §28).
     */
    public function updatePlannerSettings(UpdatePlannerSettingsRequest $request): CompanySettingResource
    {
        return new CompanySettingResource($this->settings->updatePlannerSettings($request->toDto()));
    }

    /**
     * Read-only alias for the employee-facing `me/*` route group - employees need these
     * values client-side for the notice-window pre-check UX (ProjectPlan.md §8c/§8d), but
     * the authoritative check always happens server-side per-request.
     */
    public function showForEmployee(): CompanySettingResource
    {
        return $this->show();
    }
}
