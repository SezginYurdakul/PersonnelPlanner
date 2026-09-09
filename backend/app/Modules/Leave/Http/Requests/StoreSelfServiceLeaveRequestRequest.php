<?php

namespace App\Modules\Leave\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Employee self-service annual leave submission (ProjectPlan.md §8c) - deliberately does
 * NOT accept employee_id or leave_type_id from the client. The controller forces both
 * server-side (the authenticated employee, and the Vakantie leave type) - the trust
 * boundary for "whose leave is this" is the authenticated session, never the request body.
 */
class StoreSelfServiceLeaveRequestRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
