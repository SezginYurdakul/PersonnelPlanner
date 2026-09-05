<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Contracts\AuthServiceContract;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Resources\AuthUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceContract $authService,
    ) {}

    public function login(LoginRequest $request): AuthUserResource
    {
        $user = $this->authService->login($request->credentials());

        return new AuthUserResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => __('auth.logged_out')]);
    }

    public function me(Request $request): AuthUserResource
    {
        return new AuthUserResource($request->user());
    }
}
