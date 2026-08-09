<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Service;

beforeEach(function (): void {
    [$user, $password] = apiUser();
    $this->withHeaders(basicAuthHeader($user, $password));
});

/*
|--------------------------------------------------------------------------
| View all customers
|--------------------------------------------------------------------------
*/

it('lists customers with pagination', function (): void {
    Customer::factory()->count(3)->create();

    $this->getJson('/api/v1/customers')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'email', 'phone', 'address', 'status', 'created_at', 'updated_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('counts each customer\'s services without loading them', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(4)->create();

    $this->getJson('/api/v1/customers')
        ->assertOk()
        ->assertJsonPath('data.0.services_count', 4);
});

it('paginates at the requested size', function (): void {
    Customer::factory()->count(12)->create();

    $this->getJson('/api/v1/customers?per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('meta.last_page', 3);
});

it('searches by name, email and phone', function (): void {
    Customer::factory()->create(['name' => 'Findable Industries', 'email' => 'a@moneyflex.test']);
    Customer::factory()->create(['name' => 'Other Co', 'email' => 'b@moneyflex.test']);

    $this->getJson('/api/v1/customers?search=Findable')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Findable Industries');
});

it('filters by status', function (): void {
    Customer::factory()->count(2)->active()->create();
    Customer::factory()->inactive()->create();

    $this->getJson('/api/v1/customers?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'inactive');
});

it('sorts ascending and descending', function (): void {
    Customer::factory()->create(['name' => 'Alpha']);
    Customer::factory()->create(['name' => 'Zulu']);

    expect($this->getJson('/api/v1/customers?sort=name')->json('data.0.name'))->toBe('Alpha')
        ->and($this->getJson('/api/v1/customers?sort=-name')->json('data.0.name'))->toBe('Zulu');
});

it('refuses to sort by a column outside the whitelist', function (): void {
    // Anything else would let a caller order by, and so probe, arbitrary
    // columns such as the password hash.
    $this->getJson('/api/v1/customers?sort=password')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
});

it('caps the page size', function (): void {
    $this->getJson('/api/v1/customers?per_page=5000')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('hides soft deleted customers from the listing', function (): void {
    Customer::factory()->count(2)->create();
    Customer::factory()->create()->delete();

    $this->getJson('/api/v1/customers')->assertOk()->assertJsonCount(2, 'data');
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

it('creates a customer', function (): void {
    $response = $this->postJson('/api/v1/customers', [
        'name' => 'Acme Holdings',
        'email' => 'acme@moneyflex.test',
        'phone' => '+971500000001',
        'address' => '1 Sheikh Zayed Road',
    ]);

    $response->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.name', 'Acme Holdings')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('customers', ['email' => 'acme@moneyflex.test']);
});

it('normalises the email to lower case', function (): void {
    $this->postJson('/api/v1/customers', ['name' => 'Acme', 'email' => 'ACME@Example.COM'])
        ->assertCreated()
        ->assertJsonPath('data.email', 'acme@example.com');
});

it('requires a name and an email', function (): void {
    $this->postJson('/api/v1/customers', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email']);
});

it('rejects a malformed email', function (): void {
    $this->postJson('/api/v1/customers', ['name' => 'Acme', 'email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects a duplicate email', function (): void {
    Customer::factory()->create(['email' => 'taken@moneyflex.test']);

    $this->postJson('/api/v1/customers', ['name' => 'Acme', 'email' => 'taken@moneyflex.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects an unknown status', function (): void {
    $this->postJson('/api/v1/customers', ['name' => 'Acme', 'email' => 'a@moneyflex.test', 'status' => 'wizard'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

/*
|--------------------------------------------------------------------------
| View one
|--------------------------------------------------------------------------
*/

it('shows a customer', function (): void {
    $customer = Customer::factory()->create();

    $this->getJson("/api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.email', $customer->email);
});

it('answers 404 for a customer that does not exist', function (): void {
    $this->getJson('/api/v1/customers/999999')
        ->assertNotFound()
        ->assertJsonPath('message', 'The requested resource was not found.');
});

it('answers 404 for a soft deleted customer', function (): void {
    $customer = Customer::factory()->create();
    $customer->delete();

    $this->getJson("/api/v1/customers/{$customer->id}")->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

it('updates a customer', function (): void {
    $customer = Customer::factory()->active()->create(['name' => 'Before']);

    $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'After', 'status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After')
        ->assertJsonPath('data.status', 'inactive');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Inactive);
});

it('accepts a partial update', function (): void {
    $customer = Customer::factory()->create(['name' => 'Keep Me']);

    $this->patchJson("/api/v1/customers/{$customer->id}", ['phone' => '+971500000002'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Keep Me')
        ->assertJsonPath('data.phone', '+971500000002');
});

it('lets a customer keep its own email', function (): void {
    $customer = Customer::factory()->create(['email' => 'mine@moneyflex.test']);

    $this->patchJson("/api/v1/customers/{$customer->id}", ['email' => 'mine@moneyflex.test'])
        ->assertOk();
});

it('refuses an email already held by another customer', function (): void {
    Customer::factory()->create(['email' => 'theirs@moneyflex.test']);
    $customer = Customer::factory()->create();

    $this->patchJson("/api/v1/customers/{$customer->id}", ['email' => 'theirs@moneyflex.test'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

it('soft deletes a customer', function (): void {
    $customer = Customer::factory()->create();

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

it('hides the services of a deleted customer', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(3)->create();

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();

    expect(Service::where('customer_id', $customer->id)->count())->toBe(0)
        ->and(Service::onlyTrashed()->where('customer_id', $customer->id)->count())->toBe(3);
});

it('answers 404 when deleting twice', function (): void {
    $customer = Customer::factory()->create();

    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNoContent();
    $this->deleteJson("/api/v1/customers/{$customer->id}")->assertNotFound();
});
