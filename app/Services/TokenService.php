<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use UnexpectedValueException;

/**
 * Issues and verifies the API's JWTs.
 *
 * Tokens are stateless, so a logout cannot delete them. Revoked identifiers
 * are instead held in the cache until the moment the token would have expired
 * anyway, which bounds the storage to the token lifetime.
 */
class TokenService
{
    private const DENYLIST_PREFIX = 'jwt:revoked:';

    /**
     * @return array{access_token: string, token_type: string, expires_in: int}
     */
    public function issue(User $user): array
    {
        $issuedAt = now()->getTimestamp();
        $ttlSeconds = $this->ttlMinutes() * 60;

        $payload = [
            'iss' => (string) config('jwt.issuer'),
            'sub' => (string) $user->getAuthIdentifier(),
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $issuedAt + $ttlSeconds,
            // A unique id per token, so a single token can be revoked without
            // affecting the user's other sessions.
            'jti' => (string) Str::uuid(),
        ];

        return [
            'access_token' => JWT::encode($payload, $this->secret(), $this->algorithm()),
            'token_type' => 'Bearer',
            'expires_in' => $ttlSeconds,
        ];
    }

    /**
     * Decode and validate a token, or return null when it cannot be trusted.
     *
     * @return array<string, mixed>|null
     */
    public function decode(string $token): ?array
    {
        JWT::$leeway = (int) config('jwt.leeway');

        try {
            /** @var array<string, mixed> $claims */
            $claims = (array) JWT::decode($token, new Key($this->secret(), $this->algorithm()));
        } catch (ExpiredException|UnexpectedValueException|DomainException) {
            // Signature failure, malformed token, wrong algorithm, expired:
            // none of these are distinguishable to a client on purpose.
            return null;
        }

        if ($this->isRevoked($claims)) {
            return null;
        }

        return $claims;
    }

    public function userFromToken(string $token): ?User
    {
        $claims = $this->decode($token);

        if ($claims === null || ! isset($claims['sub'])) {
            return null;
        }

        // whereKey rather than find: given an array, find would return a
        // collection, which is not what a single subject claim ever means.
        return User::query()->whereKey($claims['sub'])->first();
    }

    /**
     * Revoke a single token until its own expiry.
     */
    public function revoke(string $token): void
    {
        $claims = $this->decode($token);

        if ($claims === null || ! isset($claims['jti'], $claims['exp'])) {
            return;
        }

        $secondsRemaining = max(1, (int) $claims['exp'] - now()->getTimestamp());

        Cache::put(self::DENYLIST_PREFIX.$claims['jti'], true, $secondsRemaining);
    }

    /**
     * Exchange a valid token for a fresh one and retire the old identifier, so
     * a refreshed token cannot be replayed.
     *
     * @return array{access_token: string, token_type: string, expires_in: int}|null
     */
    public function refresh(string $token): ?array
    {
        $claims = $this->decode($token);

        if ($claims === null || ! isset($claims['sub'], $claims['iat'])) {
            return null;
        }

        // Refreshing forever would make the lifetime meaningless; the original
        // issue time caps how long a chain of refreshes may continue.
        $refreshWindow = (int) config('jwt.refresh_ttl') * 60;

        if (now()->getTimestamp() - (int) $claims['iat'] > $refreshWindow) {
            return null;
        }

        $user = User::query()->whereKey($claims['sub'])->first();

        if ($user === null) {
            return null;
        }

        $this->revoke($token);

        return $this->issue($user);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function isRevoked(array $claims): bool
    {
        if (! isset($claims['jti'])) {
            return false;
        }

        return Cache::has(self::DENYLIST_PREFIX.$claims['jti']);
    }

    private function ttlMinutes(): int
    {
        return (int) config('jwt.ttl');
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new \RuntimeException('No JWT secret configured. Set JWT_SECRET or APP_KEY.');
        }

        return $secret;
    }

    private function algorithm(): string
    {
        return (string) config('jwt.algorithm');
    }
}
