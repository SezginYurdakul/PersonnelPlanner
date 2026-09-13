<?php

namespace App\Modules\Auth\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'visibility_scope' => $this->visibility_scope,
            'is_active' => $this->is_active,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'invitation_pending' => $this->invitation_token !== null,
            'roles' => $this->getRoleNames(),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
            ] : null),
        ];
    }
}
