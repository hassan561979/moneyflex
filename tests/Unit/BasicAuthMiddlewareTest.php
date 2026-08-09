<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateWithBasicAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/*
|--------------------------------------------------------------------------
| The basic only middleware
|--------------------------------------------------------------------------
|
| Routes use the combined middleware, which accepts Basic or a bearer token.
| This one enforces Basic alone and is registered as auth.basic.api, so a
| deployment that wants to refuse tokens can switch by changing the alias in
| routes/api.php. It is exercised directly here.
|
*/

uses(RefreshDatabase::class);

/**
 * Run the middleware over a request and return whatever comes back.
 */
function runBasicAuth(?string $email, ?string $password): SymfonyResponse
{
    // Supplied as a server variable rather than set on the header bag after
    // the fact, which is how PHP-FPM presents it and the only form the
    // request decodes into credentials.
    $server = $email === null
        ? []
        : ['HTTP_AUTHORIZATION' => 'Basic '.base64_encode($email.':'.$password)];

    $request = Request::create('/api/v1/customers', 'GET', server: $server);

    return app(AuthenticateWithBasicAuth::class)->handle(
        $request,
        fn (): Response => new Response('reached the route', 200),
    );
}

it('lets a correct credential through', function (): void {
    User::factory()->create(['email' => 'gate@moneyflex.test', 'password' => bcrypt('password123')]);

    $response = runBasicAuth('gate@moneyflex.test', 'password123');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('reached the route')
        ->and(auth()->user()?->email)->toBe('gate@moneyflex.test');
});

it('refuses a wrong password', function (): void {
    User::factory()->create(['email' => 'gate@moneyflex.test', 'password' => bcrypt('password123')]);

    expect(runBasicAuth('gate@moneyflex.test', 'wrong')->getStatusCode())->toBe(401);
});

it('refuses an unknown account', function (): void {
    expect(runBasicAuth('ghost@nowhere.test', 'password123')->getStatusCode())->toBe(401);
});

it('refuses a request with no credentials at all', function (): void {
    expect(runBasicAuth(null, null)->getStatusCode())->toBe(401);
});

it('refuses an empty password', function (): void {
    User::factory()->create(['email' => 'gate@moneyflex.test', 'password' => bcrypt('password123')]);

    expect(runBasicAuth('gate@moneyflex.test', '')->getStatusCode())->toBe(401);
});

it('challenges with the scheme and realm', function (): void {
    $response = runBasicAuth(null, null);

    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('Basic')
        ->toContain('realm=');
});

it('matches the email case insensitively', function (): void {
    User::factory()->create(['email' => 'gate@moneyflex.test', 'password' => bcrypt('password123')]);

    expect(runBasicAuth('GATE@MoneyFlex.TEST', 'password123')->getStatusCode())->toBe(200);
});

it('refuses a bearer token, which is the point of this middleware', function (): void {
    $request = Request::create('/api/v1/customers', 'GET', server: [
        'HTTP_AUTHORIZATION' => 'Bearer some.jwt.token',
    ]);

    $response = app(AuthenticateWithBasicAuth::class)->handle(
        $request,
        fn (): Response => new Response('reached the route', 200),
    );

    expect($response->getStatusCode())->toBe(401);
});
