<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\DTOs\SchedulingRoleData;
use App\Modules\Lines\Models\SchedulingRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSchedulingRoleRequest extends FormRequest
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
            // A secondary task can require coverage too (e.g. a mandatory fault-response
            // duty) - it's staffed from whoever already holds this shift, never a new hire,
            // but going unfilled still blocks approval just like a mandatory station.
            'requires_coverage' => ['sometimes', 'boolean'],
            'attachment_type' => [
                'nullable',
                'in:station,line,none,shift_lead,flexible',
                'required_if:role_kind,secondary_task',
                'prohibited_if:role_kind,station',
            ],
            'attached_station_role_id' => [
                'nullable',
                'integer',
                'exists:scheduling_roles,id',
                'required_if:attachment_type,station',
            ],
            'allows_second_person' => ['sometimes', 'boolean'],
            'min_headcount' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'fairness_weight' => ['sometimes', 'numeric', 'min:0.1', 'max:9.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stationId = $this->input('attached_station_role_id');

            if (! $stationId) {
                return;
            }

            $role = $this->route('scheduling_role');

            if ($role && (int) $stationId === $role->id) {
                $validator->errors()->add('attached_station_role_id', __('lines.cannot_attach_to_self'));

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
            allowsSecondPerson: $this->has('allows_second_person') ? $this->boolean('allows_second_person') : null,
            minHeadcount: $this->has('min_headcount') ? $this->integer('min_headcount') : null,
            isActive: $this->boolean('is_active', true),
            fairnessWeight: $this->has('fairness_weight') ? (float) $this->input('fairness_weight') : 1.0,
        );
    }
}
