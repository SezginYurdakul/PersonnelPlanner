<?php

namespace App\Modules\Staff\Support;

use App\Models\User;
use App\Modules\Staff\Models\Employee;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Shared by every self-service ("me/*") controller (ProjectPlan.md §8b) - resolves the
 * Employee record linked to the authenticated User, so the resolution logic and its
 * unlinked-account failure mode live in exactly one place rather than being copy-pasted
 * across controllers.
 */
trait ResolvesAuthenticatedEmployee
{
    protected function employeeForUser(User $user): Employee
    {
        $employee = $user->employee;

        if ($employee === null) {
            throw new AuthorizationException('No employee record is linked to this account.');
        }

        return $employee;
    }
}
