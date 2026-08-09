<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The listing options shared by every index endpoint, carried as one object
 * instead of a bag of loose arguments.
 */
final readonly class QueryOptions
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public string $sortColumn = 'created_at',
        public string $sortDirection = 'desc',
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * Build from already validated query parameters.
     *
     * The sort parameter follows the "-column" convention for descending
     * order. The column is validated against a whitelist by the form request,
     * so it can never reach the query builder unchecked.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromArray(array $validated): self
    {
        $sort = (string) ($validated['sort'] ?? '-created_at');
        $descending = str_starts_with($sort, '-');

        return new self(
            search: isset($validated['search']) ? (string) $validated['search'] : null,
            status: isset($validated['status']) ? (string) $validated['status'] : null,
            sortColumn: ltrim($sort, '-'),
            sortDirection: $descending ? 'desc' : 'asc',
            perPage: min((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applySort(Builder $query): Builder
    {
        return $query->orderBy($this->sortColumn, $this->sortDirection)
            // A stable tie breaker keeps pagination deterministic when the
            // sorted column holds duplicates.
            ->orderBy('id', $this->sortDirection);
    }

    /**
     * A stable representation used to build cache keys in phase 5.
     */
    public function fingerprint(): string
    {
        return md5(serialize([
            $this->search,
            $this->status,
            $this->sortColumn,
            $this->sortDirection,
            $this->perPage,
            request()->integer('page', 1),
        ]));
    }
}
