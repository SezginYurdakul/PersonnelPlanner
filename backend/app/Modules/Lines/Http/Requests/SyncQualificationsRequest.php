<?php

namespace App\Modules\Lines\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    /**
     * @return array<int>
     */
    public function roleIds(): array
    {
        return $this->input('role_ids', []);
    }
}
