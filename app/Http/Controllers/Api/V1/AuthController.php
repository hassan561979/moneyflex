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
use OpenApi\Attributes as OA;

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
    #[OA\Post(
        path: '/auth/login',
        operationId: 'login',
        summary: 'Exchange credentials for a bearer token',
        description: 'Open endpoint. Rate limited to five attempts per minute per account and address.',
        security: [],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'api@moneyflex.test'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'A signed token.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                    new OA\Property(property: 'expires_in', type: 'integer', example: 3600, description: 'Seconds until the token expires.'),
                ]),
            ),
            new OA\Response(
                response: 401,
                description: 'The credentials were not accepted. The response is identical whether or not the account exists.',
                content: new OA\JsonContent(ref: '#/components/schemas/Error'),
            ),
            new OA\Response(
                response: 429,
                description: 'Too many attempts. A Retry-After header states when to try again.',
                content: new OA\JsonContent(ref: '#/components/schemas/Error'),
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
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
    #[OA\Get(
        path: '/auth/me',
        operationId: 'me',
        summary: 'The authenticated account',
        description: 'Accepts either scheme, so it doubles as a way to check that credentials work.',
        security: [['basicAuth' => []], ['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The account behind the credentials.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'MoneyFlex API'),
                        new OA\Property(property: 'email', type: 'string', example: 'api@moneyflex.test'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
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
    #[OA\Post(
        path: '/auth/refresh',
        operationId: 'refresh',
        summary: 'Exchange a token for a fresh one',
        description: 'The presented token is revoked in the same step, so it cannot be replayed. Refreshing is only possible within the refresh window measured from the original issue time.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A new token.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'access_token', type: 'string'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                    new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                ]),
            ),
            new OA\Response(response: 400, description: 'No bearer token was presented.', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
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
    #[OA\Post(
        path: '/auth/logout',
        operationId: 'logout',
        summary: 'Revoke the presented token',
        description: 'The token is held on a denylist until the moment it would have expired on its own.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 204, description: 'Revoked. No content is returned.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();

        if ($token !== null) {
            $this->tokens->revoke($token);
        }

        return response()->noContent();
    }
}
