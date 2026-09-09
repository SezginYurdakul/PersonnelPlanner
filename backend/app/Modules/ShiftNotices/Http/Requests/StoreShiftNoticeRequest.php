<?php

namespace App\Modules\ShiftNotices\Http\Requests;

use App\Modules\ShiftNotices\Models\ShiftNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Employee self-service sick/late-arrival submission (ProjectPlan.md §8d) - does NOT
 * accept employee_id from the client, mirroring StoreSelfServiceLeaveRequestRequest. The
 * controller forces employee_id server-side and re-runs the eligibility check
 * authoritatively before creating.
 */
class StoreShiftNoticeRequest extends FormRequest
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
            'shift_assignment_id' => ['required', 'integer', 'exists:shift_assignments,id'],
            'type' => ['required', Rule::in([ShiftNotice::TYPE_SICK, ShiftNotice::TYPE_LATE])],
            'delay_minutes' => [Rule::requiredIf($this->input('type') === ShiftNotice::TYPE_LATE), 'nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ];
    }
}
