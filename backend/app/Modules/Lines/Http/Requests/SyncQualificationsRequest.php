<?php

namespace App\Modules\Lines\Http\Requests;

use App\Modules\Lines\Models\SchedulingRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncQualificationsRequest extends FormRequest
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
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'exists:scheduling_roles,id'],
            // Left out, the preferred stations stay as they are.
            'preferred_role_ids' => ['sometimes', 'array'],
            'preferred_role_ids.*' => [
                'integer',
                'in_array:role_ids.*',
                Rule::exists('scheduling_roles', 'id')->where('role_kind', SchedulingRole::KIND_STATION),
            ],
        ];
    }

    /**
     * @return array<int>
     */
    public function roleIds(): array
    {
        return $this->input('role_ids', []);
    }

    /**
     * @return ?array<int>
     */
    public function preferredRoleIds(): ?array
    {
        return $this->input('preferred_role_ids');
    }
}
