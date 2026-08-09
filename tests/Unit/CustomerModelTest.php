<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts status to an enum', function (): void {
    $customer = Customer::factory()->active()->create();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

it('defaults a new customer to active', function (): void {
    // The column default alone leaves the in memory model without a status
    // until it is reloaded, which used to break serialisation.
    $customer = Customer::query()->create(['name' => 'Acme', 'email' => 'acme@moneyflex.test']);

    expect($customer->status)->toBe(CustomerStatus::Active);
});

it('relates a customer to its services in both directions', function (): void {
    $customer = Customer::factory()->create();
    $service = Service::factory()->for($customer)->create();

    expect($customer->services)->toHaveCount(1)
        ->and($service->customer->id)->toBe($customer->id);
});

it('cascades a soft delete onto the services', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(3)->create();

    $customer->delete();

    expect(Service::where('customer_id', $customer->id)->count())->toBe(0)
        ->and(Service::onlyTrashed()->where('customer_id', $customer->id)->count())->toBe(3);
});

it('brings the services back when the customer is restored', function (): void {
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(2)->create();

    $customer->delete();
    $customer->restore();

    expect(Service::where('customer_id', $customer->id)->count())->toBe(2);
});

it('removes the services for good when the customer is force deleted', function (): void {
    // Here the database cascade does the work, not the model.
    $customer = Customer::factory()->create();
    Service::factory()->for($customer)->count(2)->create();

    $customer->forceDelete();

    expect(Service::withTrashed()->where('customer_id', $customer->id)->count())->toBe(0);
});

it('keeps money exact', function (): void {
    $service = Service::factory()->create(['price' => '19.99']);

    expect($service->fresh()->price)->toBeString()->toBe('19.99');
});

it('casts service status to an enum and knows what is billable', function (): void {
    $active = Service::factory()->active()->create();
    $cancelled = Service::factory()->cancelled()->create();

    expect($active->fresh()->status)->toBe(ServiceStatus::Active)
        ->and($active->status->isBillable())->toBeTrue()
        ->and($cancelled->status->isBillable())->toBeFalse();
});

it('filters through its scopes', function (): void {
    Customer::factory()->count(2)->active()->create();
    Customer::factory()->inactive()->create(['name' => 'Dormant Ltd']);

    expect(Customer::query()->status(CustomerStatus::Inactive)->count())->toBe(1)
        ->and(Customer::query()->search('Dormant')->count())->toBe(1)
        ->and(Customer::query()->search(null)->count())->toBe(3);
});
