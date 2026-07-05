<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $throttleKey = self::throttleKey($request, $credentials['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            return ApiResponse::error(
                message: 'Too many login attempts. Please try again later.',
                code: 'TOO_MANY_ATTEMPTS',
                details: ['retry_after' => RateLimiter::availableIn($throttleKey)],
                status: 429,
            );
        }

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            return ApiResponse::error(
                message: 'Invalid credentials.',
                code: 'INVALID_CREDENTIALS',
                status: 401,
            );
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            return ApiResponse::error(
                message: 'User is inactive.',
                code: 'USER_INACTIVE',
                status: 403,
            );
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Login successful.',
        );
    }

    private static function throttleKey(Request $request, string $email): string
    {
        return Str::lower($email).'|'.$request->ip();
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
