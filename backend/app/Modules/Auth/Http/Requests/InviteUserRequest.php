<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\DTOs\InviteUserData;
use Illuminate\Foundation\Http\FormRequest;

class InviteUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'unique:users,email'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function toDto(): InviteUserData
    {
        return new InviteUserData(
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            employeeId: $this->input('employee_id'),
        );
    }
}
