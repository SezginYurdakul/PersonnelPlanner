<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Contracts\AuthServiceContract;
use App\Modules\Auth\DTOs\InviteUserData;
use App\Modules\Auth\DTOs\LoginCredentials;
use App\Modules\Staff\Contracts\EmployeeServiceContract;
use App\Modules\Staff\Models\Employee;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthService implements AuthServiceContract
{
    public function __construct(private readonly EmployeeServiceContract $employees) {}

    public function login(LoginCredentials $credentials): User
    {
        // An invited-but-not-yet-completed account has no password hash yet - Auth::attempt
        // would throw comparing against null rather than simply failing, so it's rejected
        // the same way a wrong-password attempt is, before it ever reaches Hash::check().
        $hasUsablePassword = User::query()
            ->where('email', $credentials->email)
            ->whereNotNull('password')
            ->exists();

        if (! $hasUsablePassword || ! Auth::guard('web')->attempt([
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

    public function inviteUser(InviteUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => null,
            'is_active' => false,
            'invitation_token' => Str::random(64),
            'invited_at' => now(),
        ]);
        $user->assignRole('user');

        if ($data->employeeId !== null) {
            $this->employees->linkUser(Employee::findOrFail($data->employeeId), $user);
        }

        $user->notify(new UserInvited($user->invitation_token));

        return $user;
    }

    public function completeInvitation(string $token, string $password, string $locale): User
    {
        $user = User::query()->where('invitation_token', $token)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => __('auth.invalid_invitation'),
            ]);
        }

        $user->update([
            'password' => $password,
            'locale' => $locale,
            'invitation_token' => null,
        ]);

        return $user;
    }

    public function activateUser(User $user): User
    {
        $user->update(['is_active' => true, 'activated_at' => now()]);

        return $user->refresh();
    }
}
