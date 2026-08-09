<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every cache key and tag the application uses, in one place.
 *
 * Keys built from string literals scattered across the codebase are the usual
 * reason a cache goes stale: the writer and the reader drift apart. Naming
 * them here means an invalidation can never miss a key that a read still uses.
 */
final class CacheKeys
{
    /**
     * Tag covering every cached service listing, whichever customer it is for.
     */
    public const SERVICES_TAG = 'services';

    /**
     * A listing of all services, identified by the filters that produced it.
     */
    public static function serviceIndex(QueryOptions $options): string
    {
        return 'services:index:'.$options->fingerprint();
    }

    /**
     * A listing of one customer's services.
     */
    public static function customerServiceIndex(int $customerId, QueryOptions $options): string
    {
        return 'customer:'.$customerId.':services:'.$options->fingerprint();
    }

    /**
     * Tag scoping one customer's cached listings, so a write for that customer
     * does not have to discard every other customer's cache.
     */
    public static function customerServicesTag(int $customerId): string
    {
        return 'customer:'.$customerId.':services';
    }

    /**
     * Time to live for cached listings, in seconds.
     */
    public static function ttl(): int
    {
        return (int) config('cache.ttl');
    }
}
