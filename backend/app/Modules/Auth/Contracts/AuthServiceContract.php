<?php

namespace App\Modules\Auth\Contracts;

use App\Models\User;
use App\Modules\Auth\DTOs\LoginCredentials;
use Illuminate\Validation\ValidationException;

interface AuthServiceContract
{
    /**
     * Authenticate a user for the SPA session.
     *
     * @throws ValidationException if the credentials are invalid or the account is inactive.
     */
    public function login(LoginCredentials $credentials): User;

    public function logout(User $user): void;
}
