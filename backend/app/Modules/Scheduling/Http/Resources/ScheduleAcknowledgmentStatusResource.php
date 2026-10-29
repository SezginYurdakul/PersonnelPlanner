<?php

namespace App\Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array shape ScheduleAcknowledgmentService::statusFor() returns
 * (`{employee, acknowledged, acknowledged_at}`) - not an Eloquent model, so fields are read
 * via array access rather than property access.
 */
class ScheduleAcknowledgmentStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $employee = $this['employee'];

        return [
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
            ],
            'acknowledged' => $this['acknowledged'],
            'acknowledged_at' => $this['acknowledged_at']?->toIso8601String(),
        ];
    }
}
