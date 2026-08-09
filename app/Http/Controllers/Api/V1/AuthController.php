<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Token endpoints. Basic Authentication alone satisfies the brief; these exist
 * so a client can trade credentials for a short lived bearer token instead of
 * sending a password on every call.
 */
class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip();

        // Credential stuffing protection: five attempts per minute per
        // account and address.
        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            return response()->json([
                'message' => 'Too many login attempts. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => RateLimiter::availableIn($key),
            ]);
        }

        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($key, decaySeconds: 60);

            // Deliberately identical whether the account exists or not.
            return response()->json([
                'message' => 'These credentials do not match our records.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        RateLimiter::clear($key);

        return response()->json($this->tokens->issue($user));
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user?->getAuthIdentifier(),
                'name' => $user?->name,
                'email' => $user?->email,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     *
     * Exchanges a valid token for a fresh one and retires the old identifier.
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json([
                'message' => 'A bearer token is required to refresh.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $refreshed = $this->tokens->refresh($token);

        if ($refreshed === null) {
            return response()->json([
                'message' => 'The token can no longer be refreshed.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($refreshed);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revokes the presented token until it would have expired on its own.
     */
    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            $this->tokens->revoke($token);
        }

        return response()->noContent();
    }
}
