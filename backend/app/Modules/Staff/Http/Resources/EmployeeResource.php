<?php

namespace App\Modules\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Staff\Models\Employee */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'employee_type' => $this->employee_type,
            'agency' => new AgencyResource($this->whenLoaded('agency')),
            'pay_type' => $this->pay_type,
            'hourly_rate' => $this->hourly_rate,
            'monthly_salary' => $this->monthly_salary,
            'contracted_hours_per_week' => $this->contracted_hours_per_week,
            'default_line_id' => $this->default_line_id,
            'employment_terms' => new EmploymentTermResource($this->whenLoaded('currentEmploymentTerm')),
            'has_account' => $this->hasLinkedAccount(),
            'user_id' => $this->user_id,
            'is_active' => $this->is_active,
        ];
    }
}
