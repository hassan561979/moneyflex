<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\TokenService;

/*
|--------------------------------------------------------------------------
| The requirement: every endpoint is protected
|--------------------------------------------------------------------------
*/

/**
 * Enumerated rather than sampled: an endpoint added later without protection
 * should fail this test, not slip through because nobody thought to cover it.
 *
 * @return array<int, array{0: string, 1: string}>
 */
dataset('protected endpoints', [
    ['get', '/api/v1/customers'],
    ['post', '/api/v1/customers'],
    ['get', '/api/v1/customers/1'],
    ['put', '/api/v1/customers/1'],
    ['patch', '/api/v1/customers/1'],
    ['delete', '/api/v1/customers/1'],
    ['get', '/api/v1/customers/1/services'],
    ['post', '/api/v1/customers/1/services'],
    ['get', '/api/v1/services'],
    ['get', '/api/v1/services/1'],
    ['put', '/api/v1/services/1'],
    ['patch', '/api/v1/services/1'],
    ['delete', '/api/v1/services/1'],
    ['get', '/api/v1/auth/me'],
    ['post', '/api/v1/auth/refresh'],
    ['post', '/api/v1/auth/logout'],
]);

it('rejects anonymous requests', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized();
})->with('protected endpoints');

it('challenges with a basic authentication header', function (): void {
    $this->getJson('/api/v1/customers')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate')
        ->assertJsonPath('message', 'Unauthenticated.');
});

/*
|--------------------------------------------------------------------------
| Basic authentication
|--------------------------------------------------------------------------
*/

it('accepts correct basic credentials', function (): void {
    [$user, $password] = apiUser();

    $this->withHeaders(basicAuthHeader($user, $password))
        ->getJson('/api/v1/customers')
        ->assertOk();
});

it('rejects a wrong password', function (): void {
    [$user] = apiUser();

    $this->withHeaders(basicAuthHeader($user, 'not-the-password'))
        ->getJson('/api/v1/customers')
        ->assertUnauthorized();
});

it('rejects an account that does not exist', function (): void {
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('ghost@nowhere.test:password123')])
        ->getJson('/api/v1/customers')
        ->assertUnauthorized();
});

it('allows writes with basic credentials', function (): void {
    [$user, $password] = apiUser();

    $this->withHeaders(basicAuthHeader($user, $password))
        ->postJson('/api/v1/customers', ['name' => 'Basic Co', 'email' => 'basic@moneyflex.test'])
        ->assertCreated();
});

/*
|--------------------------------------------------------------------------
| Tokens
|--------------------------------------------------------------------------
*/

it('exchanges credentials for a token', function (): void {
    [$user, $password] = apiUser();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
        ->assertJsonPath('token_type', 'Bearer');
});

it('refuses to issue a token for bad credentials', function (): void {
    [$user] = apiUser();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertUnauthorized();
});

it('gives the same answer whether or not the account exists', function (): void {
    [$user] = apiUser();

    $existing = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);
    $absent = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@nowhere.test', 'password' => 'wrong']);

    expect($absent->status())->toBe($existing->status())
        ->and($absent->json('message'))->toBe($existing->json('message'));
});

it('accepts a bearer token', function (): void {
    [$user] = apiUser();
    $token = app(TokenService::class)->issue($user)['access_token'];

    $this->withToken($token)->getJson('/api/v1/customers')->assertOk();
    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('rejects a tampered token', function (): void {
    [$user] = apiUser();
    $token = app(TokenService::class)->issue($user)['access_token'];
    $tampered = substr($token, 0, (int) strrpos($token, '.')).'.forged-signature';

    $this->withToken($tampered)->getJson('/api/v1/customers')->assertUnauthorized();
});

it('rejects a token signed with the none algorithm', function (): void {
    // The classic JWT bypass: a forged header claiming the token needs no
    // signature at all.
    $encode = fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');
    $forged = $encode(['typ' => 'JWT', 'alg' => 'none']).'.'.$encode(['sub' => '1', 'exp' => time() + 3600]).'.';

    $this->withToken($forged)->getJson('/api/v1/customers')->assertUnauthorized();
});

it('rejects an expired token', function (): void {
    [$user] = apiUser();

    config()->set('jwt.ttl', -1);
    $expired = app(TokenService::class)->issue($user)['access_token'];

    $this->withToken($expired)->getJson('/api/v1/customers')->assertUnauthorized();
});

it('rejects a token whose account has since been deleted', function (): void {
    $user = User::factory()->create();
    $token = app(TokenService::class)->issue($user)['access_token'];

    $user->delete();

    $this->withToken($token)->getJson('/api/v1/customers')->assertUnauthorized();
});

it('retires the old token when refreshing', function (): void {
    [$user] = apiUser();
    $original = app(TokenService::class)->issue($user)['access_token'];

    $fresh = $this->withToken($original)->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->json('access_token');

    $this->withToken($fresh)->getJson('/api/v1/customers')->assertOk();
    $this->withToken($original)->getJson('/api/v1/customers')->assertUnauthorized();
});

it('revokes a token on logout', function (): void {
    [$user] = apiUser();
    $token = app(TokenService::class)->issue($user)['access_token'];

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->withToken($token)->getJson('/api/v1/customers')->assertUnauthorized();
});

it('rate limits repeated failed logins', function (): void {
    [$user] = apiUser();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('leaves no customer data reachable without credentials', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->create();

    $this->getJson('/api/v1/customers')->assertUnauthorized();
    $this->getJson("/api/v1/customers/{$customer->id}")->assertUnauthorized();
    $this->getJson('/api/v1/services')->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Token endpoints reached with basic credentials
|--------------------------------------------------------------------------
*/

it('asks for a bearer token when refreshing with basic credentials', function (): void {
    // Authentication succeeds, but there is no token to exchange.
    [$user, $password] = apiUser();

    $this->withHeaders(basicAuthHeader($user, $password))
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(400)
        ->assertJsonPath('message', 'A bearer token is required to refresh.');
});

it('accepts a logout with basic credentials as a no-op', function (): void {
    [$user, $password] = apiUser();

    $this->withHeaders(basicAuthHeader($user, $password))
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();
});

it('refuses to refresh a token it cannot read', function (): void {
    // A single Authorization header carries one scheme, so an unreadable
    // bearer token is rejected by authentication before refresh is reached.
    apiUser();

    $this->withToken('rubbish')
        ->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized();
});
