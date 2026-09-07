<?php

namespace App\Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Scheduling\DTOs\CandidateData */
class CandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee' => [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
            ],
            'score' => $this->score,
            'qualified' => $this->qualified,
            'rule_compliant' => $this->ruleCompliant,
            'exclusion_reason' => $this->exclusionReason,
        ];
    }
}
