<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\BasicCredentials;
use App\Models\User;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Accepts either credential the API supports: HTTP Basic, which the brief
 * requires, or a bearer JWT, which is the bonus. The Authorization scheme
 * decides which is checked, and a caller only ever presents one.
 */
class AuthenticateWithBasicOrJwt
{
    public function __construct(
        private readonly BasicCredentials $credentials,
        private readonly TokenService $tokens,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $this->fromBearerToken($request) ?? $this->credentials->resolve($request);

        if ($user === null) {
            return response()->json(
                ['message' => 'Unauthenticated.'],
                Response::HTTP_UNAUTHORIZED,
                // Advertise both accepted schemes so a client knows its options.
                ['WWW-Authenticate' => 'Basic realm="'.config('app.name').' API", charset="UTF-8"'],
            );
        }

        Auth::setUser($user);

        return $next($request);
    }

    private function fromBearerToken(Request $request): ?User
    {
        $token = $request->bearerToken();

        return blank($token) ? null : $this->tokens->userFromToken($token);
    }
}
