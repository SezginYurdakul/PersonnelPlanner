<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Contracts\AuthServiceContract;
use App\Modules\Auth\DTOs\LoginCredentials;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AuthService implements AuthServiceContract
{
    public function login(LoginCredentials $credentials): User
    {
        if (! Auth::guard('web')->attempt([
            'email' => $credentials->email,
            'password' => $credentials->password,
        ])) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('auth.inactive'),
            ]);
        }

        request()->session()->regenerate();

        return $user;
    }

    public function logout(User $user): void
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
