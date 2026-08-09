<?php

declare(strict_types=1);

use App\Support\QueryOptions;

it('defaults to the newest records first', function (): void {
    $options = QueryOptions::fromArray([]);

    expect($options->sortColumn)->toBe('created_at')
        ->and($options->sortDirection)->toBe('desc')
        ->and($options->perPage)->toBe(15)
        ->and($options->search)->toBeNull()
        ->and($options->status)->toBeNull();
});

it('reads a leading minus as descending order', function (): void {
    expect(QueryOptions::fromArray(['sort' => '-name'])->sortDirection)->toBe('desc')
        ->and(QueryOptions::fromArray(['sort' => '-name'])->sortColumn)->toBe('name')
        ->and(QueryOptions::fromArray(['sort' => 'name'])->sortDirection)->toBe('asc')
        ->and(QueryOptions::fromArray(['sort' => 'name'])->sortColumn)->toBe('name');
});

it('caps the page size however large the request', function (): void {
    // The form request rejects anything over the maximum, so this is the
    // second line of defence for any caller that bypasses it.
    expect(QueryOptions::fromArray(['per_page' => 10_000])->perPage)->toBe(100)
        ->and(QueryOptions::fromArray(['per_page' => 20])->perPage)->toBe(20);
});

it('gives identical options the same fingerprint', function (): void {
    $one = QueryOptions::fromArray(['search' => 'acme', 'status' => 'active', 'sort' => '-name']);
    $two = QueryOptions::fromArray(['search' => 'acme', 'status' => 'active', 'sort' => '-name']);

    expect($one->fingerprint())->toBe($two->fingerprint());
});

it('gives differing options different fingerprints', function (): void {
    // A collision here would serve one caller's filtered listing to another.
    $fingerprints = collect([
        [],
        ['search' => 'acme'],
        ['search' => 'other'],
        ['status' => 'active'],
        ['status' => 'inactive'],
        ['sort' => 'name'],
        ['sort' => '-name'],
        ['per_page' => 50],
    ])->map(fn (array $input): string => QueryOptions::fromArray($input)->fingerprint());

    expect($fingerprints->unique())->toHaveCount($fingerprints->count());
});
