<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case bindings
|--------------------------------------------------------------------------
|
| Feature tests hit the HTTP layer against a real MySQL schema, rolled back
| after each test. Unit tests that touch no database bind the base test case
| only, and opt into RefreshDatabase individually where they need it.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Rate limit counters, the token denylist and cached listings all live in
    // Redis, which a database rollback does not touch. Without this, state
    // leaks between tests and failures depend on execution order.
    ->beforeEach(fn () => Cache::flush())
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * An account that exists, with its plain password to hand.
 *
 * @return array{0: User, 1: string}
 */
function apiUser(string $password = 'password123'): array
{
    $user = User::factory()->create([
        'email' => 'tester@moneyflex.test',
        'password' => bcrypt($password),
    ]);

    return [$user, $password];
}

/**
 * The Authorization header for HTTP Basic, as a client would send it.
 *
 * @return array<string, string>
 */
function basicAuthHeader(User $user, string $password = 'password123'): array
{
    return ['Authorization' => 'Basic '.base64_encode($user->email.':'.$password)];
}
