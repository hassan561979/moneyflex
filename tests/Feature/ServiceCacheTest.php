<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    [$user, $password] = apiUser();
    $this->withHeaders(basicAuthHeader($user, $password));
});

/**
 * The queries a request issues against one table.
 *
 * Counting every query would measure the credential lookup that authentication
 * performs on each request whatever the cache does. Narrowing to the services
 * table states exactly what the cache is meant to avoid.
 *
 * @return array<int, string>
 */
function queriesAgainst(string $table, Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    return array_values(array_filter(
        $queries,
        static fn (string $query): bool => str_contains($query, "`{$table}`"),
    ));
}

it('serves a repeated listing without touching the services table', function (): void {
    Service::factory()->count(3)->create();

    $miss = queriesAgainst('services', fn () => $this->getJson('/api/v1/services')->assertOk());
    $hit = queriesAgainst('services', fn () => $this->getJson('/api/v1/services')->assertOk());

    expect($miss)->not->toBeEmpty()
        ->and($hit)->toBeEmpty();
});

it('returns the same payload on a hit as on a miss', function (): void {
    Service::factory()->count(3)->create();

    $first = $this->getJson('/api/v1/services')->assertOk()->json();
    $second = $this->getJson('/api/v1/services')->assertOk()->json();

    expect($second)->toBe($first);
});

it('caches each set of filters separately', function (): void {
    Service::factory()->count(2)->active()->create();
    Service::factory()->cancelled()->create();

    $this->getJson('/api/v1/services?status=active')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/services?status=cancelled')->assertJsonCount(1, 'data');

    // Served from the cache the second time, and still correctly filtered.
    $this->getJson('/api/v1/services?status=active')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'active');
    $this->getJson('/api/v1/services?status=cancelled')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('caches each page separately', function (): void {
    Service::factory()->count(10)->create();

    $page1 = $this->getJson('/api/v1/services?per_page=5&page=1')->json('data.0.id');
    $page2 = $this->getJson('/api/v1/services?per_page=5&page=2')->json('data.0.id');

    expect($page1)->not->toBe($page2);
});

/*
|--------------------------------------------------------------------------
| Invalidation
|--------------------------------------------------------------------------
*/

it('shows a newly created service immediately', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->create();

    $this->getJson('/api/v1/services')->assertJsonCount(1, 'data');

    $this->postJson("/api/v1/customers/{$customer->id}/services", ['name' => 'Fresh', 'price' => '1.00'])
        ->assertCreated();

    $this->getJson('/api/v1/services')->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/customers/{$customer->id}/services")->assertJsonCount(2, 'data');
});

it('shows an updated service immediately', function (): void {
    $service = Service::factory()->create(['name' => 'Before']);

    $this->getJson('/api/v1/services')->assertJsonPath('data.0.name', 'Before');

    $this->patchJson("/api/v1/services/{$service->id}", ['name' => 'After'])->assertOk();

    $this->getJson('/api/v1/services')->assertJsonPath('data.0.name', 'After');
});

it('drops a deleted service immediately', function (): void {
    $service = Service::factory()->create();

    $this->getJson('/api/v1/services')->assertJsonCount(1, 'data');

    $this->deleteJson("/api/v1/services/{$service->id}")->assertNoContent();

    $this->getJson('/api/v1/services')->assertJsonCount(0, 'data');
});

it('empties a customer listing when the customer is deleted', function (): void {
    // The cascade is a mass update that fires no model events, so nothing
    // would invalidate this cache implicitly.
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(2)->create();

    $this->getJson("/api/v1/customers/{$customer->id}/services")->assertJsonCount(2, 'data');

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();

    $this->getJson('/api/v1/services')->assertJsonCount(0, 'data');
});

it('leaves another customer\'s cached listing alone', function (): void {
    $one = Customer::factory()->create();
    $two = Customer::factory()->create();
    Service::factory()->for($one)->create();
    Service::factory()->for($two)->create();

    $this->getJson("/api/v1/customers/{$one->id}/services")->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/customers/{$two->id}/services")->assertJsonCount(1, 'data');

    $this->postJson("/api/v1/customers/{$one->id}/services", ['name' => 'New', 'price' => '2.00'])
        ->assertCreated();

    $this->getJson("/api/v1/customers/{$one->id}/services")->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/customers/{$two->id}/services")->assertJsonCount(1, 'data');
});
