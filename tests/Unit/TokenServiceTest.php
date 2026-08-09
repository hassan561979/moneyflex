<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\TokenService;
use Illuminate\Cache\RedisStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    $this->tokens = app(TokenService::class);
    $this->user = User::factory()->create();
});

it('issues a token carrying the expected claims', function (): void {
    $issued = $this->tokens->issue($this->user);

    expect($issued)->toHaveKeys(['access_token', 'token_type', 'expires_in'])
        ->and($issued['token_type'])->toBe('Bearer')
        ->and($issued['expires_in'])->toBe(config('jwt.ttl') * 60);

    $claims = $this->tokens->decode($issued['access_token']);

    expect($claims)->toHaveKeys(['iss', 'sub', 'iat', 'nbf', 'exp', 'jti'])
        ->and($claims['sub'])->toBe((string) $this->user->id);
});

it('gives every token its own identifier', function (): void {
    // Shared identifiers would mean revoking one session revoked them all.
    $first = $this->tokens->decode($this->tokens->issue($this->user)['access_token']);
    $second = $this->tokens->decode($this->tokens->issue($this->user)['access_token']);

    expect($first['jti'])->not->toBe($second['jti']);
});

it('resolves the user behind a token', function (): void {
    $token = $this->tokens->issue($this->user)['access_token'];

    expect($this->tokens->userFromToken($token)?->id)->toBe($this->user->id);
});

it('refuses a token signed with another key', function (): void {
    $token = $this->tokens->issue($this->user)['access_token'];

    config()->set('jwt.secret', 'a-completely-different-secret');

    expect($this->tokens->decode($token))->toBeNull();
});

it('refuses a token whose payload has been edited', function (): void {
    $token = $this->tokens->issue($this->user)['access_token'];
    [$header, , $signature] = explode('.', $token);
    $forgedPayload = rtrim(strtr(base64_encode((string) json_encode(['sub' => '999'])), '+/', '-_'), '=');

    expect($this->tokens->decode("{$header}.{$forgedPayload}.{$signature}"))->toBeNull();
});

it('refuses an expired token', function (): void {
    config()->set('jwt.ttl', -10);
    $token = $this->tokens->issue($this->user)['access_token'];

    expect($this->tokens->decode($token))->toBeNull();
});

it('refuses a malformed token', function (): void {
    expect($this->tokens->decode('not-a-token'))->toBeNull()
        ->and($this->tokens->decode(''))->toBeNull()
        ->and($this->tokens->decode('a.b.c'))->toBeNull();
});

it('refuses a revoked token', function (): void {
    $token = $this->tokens->issue($this->user)['access_token'];

    expect($this->tokens->decode($token))->not->toBeNull();

    $this->tokens->revoke($token);

    expect($this->tokens->decode($token))->toBeNull();
});

it('holds a revoked token only until it would have expired', function (): void {
    // A denylist that never forgets would grow without bound. Expiry is
    // enforced by the cache server against real time, so the assertion is on
    // the lifetime recorded with the key rather than on a travelled clock.
    $token = $this->tokens->issue($this->user)['access_token'];
    $claims = $this->tokens->decode($token);
    $this->tokens->revoke($token);

    $store = Cache::getStore();
    $lifetime = $store->connection()->ttl($store->getPrefix().'jwt:revoked:'.$claims['jti']);
    $tokenLifetime = (int) config('jwt.ttl') * 60;

    expect($lifetime)->toBeGreaterThan(0)
        ->and($lifetime)->toBeLessThanOrEqual($tokenLifetime);
})->skip(
    fn (): bool => ! Cache::getStore() instanceof RedisStore,
    'Only the Redis store records a lifetime that can be inspected.',
);

it('rotates a token on refresh and retires the original', function (): void {
    $original = $this->tokens->issue($this->user)['access_token'];

    $refreshed = $this->tokens->refresh($original);

    expect($refreshed)->not->toBeNull()
        ->and($this->tokens->decode($refreshed['access_token']))->not->toBeNull()
        ->and($this->tokens->decode($original))->toBeNull();
});

it('refuses to refresh once the refresh window has passed', function (): void {
    $token = $this->tokens->issue($this->user)['access_token'];

    $this->travelTo(now()->addMinutes((int) config('jwt.refresh_ttl') + 10));

    expect($this->tokens->refresh($token))->toBeNull();
});

it('refuses to refresh a token belonging to a deleted account', function (): void {
    $token = $this->tokens->issue($this->user)['access_token'];

    $this->user->delete();

    expect($this->tokens->refresh($token))->toBeNull()
        ->and($this->tokens->userFromToken($token))->toBeNull();
});

it('ignores a revoke call for a token it cannot read', function (): void {
    // Revoking rubbish should be a no-op rather than an error.
    expect(fn () => $this->tokens->revoke('not-a-token'))->not->toThrow(Throwable::class);
});

it('refuses to work without a signing key', function (): void {
    // Falling back to an empty secret would sign every token identically.
    config()->set('jwt.secret', '');

    expect(fn () => $this->tokens->issue($this->user))
        ->toThrow(RuntimeException::class, 'No JWT secret configured. Set JWT_SECRET or APP_KEY.');
});
