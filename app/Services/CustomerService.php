<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Support\QueryOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Every write to a customer passes through here, so the controllers stay thin
 * and the caching added in phase 5 has a single place to hook into.
 */
class CustomerService
{
    /**
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginate(QueryOptions $options): LengthAwarePaginator
    {
        $query = Customer::query()
            ->search($options->search)
            ->status($options->status)
            // Cheap aggregate instead of loading every service of every row.
            ->withCount('services');

        return $options->applySort($query)
            ->paginate($options->perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Customer
    {
        return Customer::query()->withCount('services')->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Customer
    {
        $customer = Customer::query()->create($attributes);

        return $customer->loadCount('services');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Customer $customer, array $attributes): Customer
    {
        $customer->fill($attributes)->save();

        return $customer->loadCount('services');
    }

    /**
     * Soft deletes the customer. The model cascades the deletion onto its
     * services, and the transaction keeps the two consistent.
     */
    public function delete(Customer $customer): void
    {
        DB::transaction(static function () use ($customer): void {
            $customer->delete();
        });
    }
}
