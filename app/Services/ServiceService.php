<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Service;
use App\Support\QueryOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Business logic for services. A service only ever exists under a customer,
 * so creation always takes the owner explicitly.
 */
class ServiceService
{
    /**
     * Services across every customer.
     *
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginate(QueryOptions $options, ?int $customerId = null): LengthAwarePaginator
    {
        $query = Service::query()
            ->search($options->search)
            ->status($options->status)
            ->forCustomer($customerId);

        return $options->applySort($query)
            ->paginate($options->perPage)
            ->withQueryString();
    }

    /**
     * Services belonging to one customer.
     *
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginateForCustomer(Customer $customer, QueryOptions $options): LengthAwarePaginator
    {
        return $this->paginate($options, $customer->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createForCustomer(Customer $customer, array $attributes): Service
    {
        // Writing through the relation guarantees the foreign key matches the
        // customer from the route, whatever the request body contained.
        return $customer->services()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Service $service, array $attributes): Service
    {
        $service->fill($attributes)->save();

        return $service;
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }
}
