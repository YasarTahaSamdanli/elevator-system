<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error(
                message: 'Invalid credentials.',
                code: 'INVALID_CREDENTIALS',
                status: 401,
            );
        }

        if (! $user->is_active) {
            return ApiResponse::error(
                message: 'User is inactive.',
                code: 'USER_INACTIVE',
                status: 403,
            );
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Login successful.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(
            message: 'Logout successful.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('company');

        return ApiResponse::success(
            data: [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'company' => [
                    'uuid' => $user->company?->uuid,
                    'name' => $user->company?->name,
                ],
                'roles' => $user->getRoleNames()->values()->all(),
            ],
        );
    }
}
