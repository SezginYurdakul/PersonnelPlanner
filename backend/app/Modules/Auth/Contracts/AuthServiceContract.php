<?php

namespace App\Modules\Auth\Contracts;

use App\Models\User;
use App\Modules\Auth\DTOs\InviteUserData;
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

    /**
     * Admin action (ProjectPlan.md §8g): creates an inactive User (optionally linked to an
     * existing Employee) with a random invitation token, and queues the UserInvited email.
     */
    public function inviteUser(InviteUserData $data): User;

    /**
     * Public, token-only action: the invited person sets their own password and language
     * (ProjectPlan.md §10A.6a). The account stays inactive (`is_active` remains false) -
     * this only clears the invitation token, it is not the admin's separate activation step.
     *
     * @throws ValidationException if the token is invalid/already used.
     */
    public function completeInvitation(string $token, string $password, string $locale): User;

    /**
     * Admin action: the second, separate approval step - flips `is_active` to true.
     */
    public function activateUser(User $user): User;
}
