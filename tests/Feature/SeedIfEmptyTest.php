<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Boot time seeding
|--------------------------------------------------------------------------
|
| The container runs this on start so a fresh checkout comes up with the
| account the documentation quotes. Both halves matter: it must seed an empty
| database, and it must leave a populated one alone, or every restart would
| add another batch of demonstration data.
|
*/

it('seeds an empty database', function (): void {
    expect(User::query()->count())->toBe(0);

    $this->artisan('db:seed-if-empty')
        ->expectsOutputToContain('Empty database, seeding.')
        ->assertSuccessful();

    expect(User::query()->count())->toBeGreaterThan(0)
        ->and(Customer::query()->count())->toBeGreaterThan(0);
});

it('creates the account the documentation quotes', function (): void {
    $this->artisan('db:seed-if-empty')->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'email' => config('moneyflex.api_user.email'),
    ]);
});

it('leaves a populated database untouched', function (): void {
    $this->artisan('db:seed-if-empty')->assertSuccessful();

    $users = User::query()->count();
    $customers = Customer::query()->count();

    $this->artisan('db:seed-if-empty')
        ->expectsOutputToContain('The database already holds data. Nothing to seed.')
        ->assertSuccessful();

    expect(User::query()->count())->toBe($users)
        ->and(Customer::query()->count())->toBe($customers);
});

it('leaves the seeded credentials working', function (): void {
    $this->artisan('db:seed-if-empty')->assertSuccessful();

    $this->withBasicAuth(
        (string) config('moneyflex.api_user.email'),
        (string) config('moneyflex.api_user.password'),
    )->getJson('/api/v1/customers')->assertOk();
});
