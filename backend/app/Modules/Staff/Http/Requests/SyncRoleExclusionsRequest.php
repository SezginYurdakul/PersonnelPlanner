<?php

namespace App\Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRoleExclusionsRequest extends FormRequest
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
            'scheduling_role_ids' => ['present', 'array'],
            'scheduling_role_ids.*' => ['integer', 'exists:scheduling_roles,id'],
        ];
    }

    /**
     * @return array<int>
     */
    public function schedulingRoleIds(): array
    {
        return $this->input('scheduling_role_ids', []);
    }
}
