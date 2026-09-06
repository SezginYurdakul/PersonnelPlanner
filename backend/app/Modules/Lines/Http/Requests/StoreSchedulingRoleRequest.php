<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\DTOs\SchedulingRoleData;
use App\Modules\Lines\Models\SchedulingRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSchedulingRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'line_id' => ['nullable', 'exists:lines,id'],
            'role_kind' => ['required', 'in:station,secondary_task'],
            'requires_coverage' => ['sometimes', 'boolean', 'prohibited_if:role_kind,secondary_task'],
            'attachment_type' => [
                'nullable',
                'in:station,line,none',
                'required_if:role_kind,secondary_task',
                'prohibited_if:role_kind,station',
            ],
            'attached_station_role_id' => [
                'nullable',
                'integer',
                'exists:scheduling_roles,id',
                'required_if:attachment_type,station',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * §17A.5a: attached_station_role_id must reference an actual station, and if that
     * station is line-specific, this secondary task's line_id must match it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stationId = $this->input('attached_station_role_id');

            if (! $stationId) {
                return;
            }

            $station = SchedulingRole::find($stationId);

            if (! $station || ! $station->isStation()) {
                $validator->errors()->add('attached_station_role_id', __('lines.must_reference_station'));

                return;
            }

            if ($station->line_id !== null && (int) $this->input('line_id') !== $station->line_id) {
                $validator->errors()->add('line_id', __('lines.line_must_match_station'));
            }
        });
    }

    public function toDto(): SchedulingRoleData
    {
        return new SchedulingRoleData(
            name: $this->string('name')->toString(),
            roleKind: $this->string('role_kind')->toString(),
            lineId: $this->input('line_id'),
            requiresCoverage: $this->has('requires_coverage') ? $this->boolean('requires_coverage') : null,
            attachmentType: $this->string('attachment_type')->toString() ?: null,
            attachedStationRoleId: $this->input('attached_station_role_id'),
            isActive: $this->boolean('is_active', true),
        );
    }
}
