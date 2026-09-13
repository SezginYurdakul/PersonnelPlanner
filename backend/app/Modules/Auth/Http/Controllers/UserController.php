<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Auth\Http\Requests\UpdateVisibilityScopeRequest;
use App\Modules\Auth\Http\Resources\AuthUserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users, optionally filtered to those not yet linked to an employee
     * (ProjectPlan.md §11.2a) - powers the "link account" picker on the employee
     * detail screen.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->boolean('unlinked')) {
            $query->doesntHave('employee');
        }

        // Completed the set-password step but not yet approved by an admin
        // (ProjectPlan.md §8g's two-step activation) - powers the Staff page's
        // "pending activation" section.
        if ($request->boolean('pending_activation')) {
            $query->where('is_active', false)
                ->whereNull('invitation_token')
                ->whereNotNull('invited_at');
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return AuthUserResource::collection($query->orderBy('name')->limit(20)->get());
    }

    public function updateVisibilityScope(UpdateVisibilityScopeRequest $request, User $user): AuthUserResource
    {
        $user->update(['visibility_scope' => $request->string('visibility_scope')->toString()]);

        return new AuthUserResource($user->refresh());
    }
}
