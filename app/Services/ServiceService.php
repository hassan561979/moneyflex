<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\ServiceResource;
use App\Models\Customer;
use App\Models\Service;
use App\Support\CacheKeys;
use App\Support\QueryOptions;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Business logic for services. A service only ever exists under a customer,
 * so creation always takes the owner explicitly.
 *
 * Listings are read far more often than they change, so they are cached in
 * Redis and discarded the moment a write makes them wrong. Every write in the
 * application goes through this class, which is what makes that safe.
 */
class ServiceService
{
    /**
     * A cached listing, as the exact payload the endpoint returns.
     *
     * What is cached is the rendered array, not the paginator that produced
     * it: serialised framework objects are brittle, and storing the finished
     * representation also skips the serialisation work on a hit, not just the
     * database round trip.
     *
     * @return array<string, mixed>
     */
    public function cachedIndex(QueryOptions $options, ?int $customerId = null): array
    {
        $key = $customerId === null
            ? CacheKeys::serviceIndex($options)
            : CacheKeys::customerServiceIndex($customerId, $options);

        $tags = $customerId === null
            ? [CacheKeys::SERVICES_TAG]
            // Tagged with both, so a write for one customer can discard just
            // that customer's listings, and a broad change can discard all.
            : [CacheKeys::SERVICES_TAG, CacheKeys::customerServicesTag($customerId)];

        return $this->cache($tags)->remember(
            $key,
            CacheKeys::ttl(),
            function () use ($options, $customerId): array {
                $paginator = $this->paginate($options, $customerId);

                /** @var array<string, mixed> $payload */
                $payload = ServiceResource::collection($paginator)
                    ->response()
                    ->getData(true);

                return $payload;
            },
        );
    }

    /**
     * Services across every customer, straight from the database.
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
        $service = $customer->services()->create($attributes);

        $this->forget($customer->id);

        return $service;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Service $service, array $attributes): Service
    {
        $service->fill($attributes)->save();

        $this->forget($service->customer_id);

        return $service;
    }

    public function delete(Service $service): void
    {
        $service->delete();

        $this->forget($service->customer_id);
    }

    /**
     * Discard cached listings after a write.
     *
     * Both the customer's own listings and the global ones are affected by any
     * single service changing, so both tags are flushed.
     */
    public function forget(?int $customerId = null): void
    {
        $tags = [CacheKeys::SERVICES_TAG];

        if ($customerId !== null) {
            $tags[] = CacheKeys::customerServicesTag($customerId);
        }

        $this->cache($tags)->flush();
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function cache(array $tags): Repository
    {
        return Cache::tags($tags);
    }
}
