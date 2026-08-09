<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\BasicCredentials;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * HTTP Basic Authentication, as required of every endpoint.
 *
 * Written by hand rather than using the framework's auth.basic middleware so
 * the challenge carries a JSON body and a realm of our choosing, and so no
 * session is ever started for a stateless API.
 */
class AuthenticateWithBasicAuth
{
    public function __construct(private readonly BasicCredentials $credentials) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = $this->credentials->resolve($request);

        if ($user === null) {
            return response()->json(
                ['message' => 'Unauthenticated.'],
                Response::HTTP_UNAUTHORIZED,
                // This header is what tells a client which scheme to use and
                // what makes a browser prompt for credentials.
                ['WWW-Authenticate' => 'Basic realm="'.config('app.name').' API", charset="UTF-8"'],
            );
        }

        Auth::setUser($user);

        return $next($request);
    }
}
