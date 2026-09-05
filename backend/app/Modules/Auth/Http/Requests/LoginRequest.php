<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\DTOs\LoginCredentials;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function credentials(): LoginCredentials
    {
        return new LoginCredentials(
            email: $this->string('email')->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
