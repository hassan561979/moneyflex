<?php

declare(strict_types=1);

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Service;

beforeEach(function (): void {
    [$user, $password] = apiUser();
    $this->withHeaders(basicAuthHeader($user, $password));
});

/*
|--------------------------------------------------------------------------
| View all services
|--------------------------------------------------------------------------
*/

it('lists every service', function (): void {
    Service::factory()->count(3)->create();

    $this->getJson('/api/v1/services')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'customer_id', 'name', 'description', 'price', 'status', 'starts_at', 'ends_at']],
            'links',
            'meta',
        ]);
});

it('filters services by status', function (): void {
    Service::factory()->count(2)->active()->create();
    Service::factory()->cancelled()->create();

    $this->getJson('/api/v1/services?status=cancelled')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('filters services by customer', function (): void {
    $mine = Customer::factory()->create();
    Service::factory()->for($mine)->count(2)->create();
    Service::factory()->count(3)->create();

    $this->getJson("/api/v1/services?customer_id={$mine->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('searches services by name and description', function (): void {
    Service::factory()->create(['name' => 'Escrow Account']);
    Service::factory()->create(['name' => 'Payment Gateway']);

    $this->getJson('/api/v1/services?search=Escrow')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Escrow Account');
});

it('sorts services by price', function (): void {
    Service::factory()->create(['name' => 'Cheap', 'price' => '10.00']);
    Service::factory()->create(['name' => 'Dear', 'price' => '900.00']);

    expect($this->getJson('/api/v1/services?sort=-price')->json('data.0.name'))->toBe('Dear')
        ->and($this->getJson('/api/v1/services?sort=price')->json('data.0.name'))->toBe('Cheap');
});

it('refuses to sort services by a column outside the whitelist', function (): void {
    $this->getJson('/api/v1/services?sort=customer_id')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
});

/*
|--------------------------------------------------------------------------
| View the services of one customer
|--------------------------------------------------------------------------
*/

it('lists only the services of the given customer', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(2)->create();
    Service::factory()->count(3)->create();

    $response = $this->getJson("/api/v1/customers/{$customer->id}/services")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    foreach ($response->json('data') as $service) {
        expect($service['customer_id'])->toBe($customer->id);
    }
});

it('answers 404 when listing services of a customer that does not exist', function (): void {
    $this->getJson('/api/v1/customers/999999/services')->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Create a service for a customer
|--------------------------------------------------------------------------
*/

it('creates a service for a customer', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$customer->id}/services", [
        'name' => 'Payment Gateway',
        'description' => 'Card acquiring',
        'price' => '249.99',
        'starts_at' => '2026-01-01',
        'ends_at' => '2027-01-01',
    ])
        ->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.price', '249.99')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('services', ['name' => 'Payment Gateway', 'customer_id' => $customer->id]);
});

it('ignores a customer_id in the body and trusts the url', function (): void {
    // Otherwise a caller could attach a service to somebody else's account.
    $owner = Customer::factory()->create();
    $victim = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$owner->id}/services", [
        'name' => 'Injected',
        'price' => '5.00',
        'customer_id' => $victim->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.customer_id', $owner->id);

    expect(Service::where('customer_id', $victim->id)->count())->toBe(0);
});

it('requires a name and a price', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$customer->id}/services", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'price']);
});

it('rejects a price with more than two decimal places', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$customer->id}/services", ['name' => 'S', 'price' => '10.999'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price');
});

it('rejects a negative price', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$customer->id}/services", ['name' => 'S', 'price' => '-1.00'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price');
});

it('rejects an end date before the start date', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$customer->id}/services", [
        'name' => 'S',
        'price' => '10.00',
        'starts_at' => '2026-05-01',
        'ends_at' => '2026-01-01',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');
});

it('keeps the exact price given, without float rounding', function (): void {
    $customer = Customer::factory()->create();

    $this->postJson("/api/v1/customers/{$customer->id}/services", ['name' => 'S', 'price' => '0.10'])
        ->assertCreated()
        ->assertJsonPath('data.price', '0.10');

    $this->postJson("/api/v1/customers/{$customer->id}/services", ['name' => 'T', 'price' => '19.99'])
        ->assertCreated()
        ->assertJsonPath('data.price', '19.99');
});

/*
|--------------------------------------------------------------------------
| View, update and delete a service
|--------------------------------------------------------------------------
*/

it('shows a service with its customer', function (): void {
    $service = Service::factory()->create();

    $this->getJson("/api/v1/services/{$service->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $service->id)
        ->assertJsonPath('data.customer.id', $service->customer_id);
});

it('answers 404 for a service that does not exist', function (): void {
    $this->getJson('/api/v1/services/999999')->assertNotFound();
});

it('updates a service', function (): void {
    $service = Service::factory()->active()->create(['price' => '10.00']);

    $this->putJson("/api/v1/services/{$service->id}", ['price' => '299.00', 'status' => 'suspended'])
        ->assertOk()
        ->assertJsonPath('data.price', '299.00')
        ->assertJsonPath('data.status', 'suspended');

    expect($service->fresh()->status)->toBe(ServiceStatus::Suspended);
});

it('compares a partial date update against what is already stored', function (): void {
    // Only ends_at is sent, so the rule has to reach for the stored starts_at
    // rather than a field absent from the request.
    $service = Service::factory()->create(['starts_at' => '2026-05-01', 'ends_at' => '2026-06-01']);

    $this->patchJson("/api/v1/services/{$service->id}", ['ends_at' => '2026-01-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');

    $this->patchJson("/api/v1/services/{$service->id}", ['ends_at' => '2026-09-01'])
        ->assertOk();
});

it('soft deletes a service', function (): void {
    $service = Service::factory()->create();

    $this->deleteJson("/api/v1/services/{$service->id}")->assertNoContent();

    $this->assertSoftDeleted('services', ['id' => $service->id]);
    $this->getJson("/api/v1/services/{$service->id}")->assertNotFound();
});

it('leaves the customer intact when a service is deleted', function (): void {
    $service = Service::factory()->create();

    $this->deleteJson("/api/v1/services/{$service->id}")->assertNoContent();

    $this->assertDatabaseHas('customers', ['id' => $service->customer_id, 'deleted_at' => null]);
});
