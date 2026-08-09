<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Enums\CustomerStatus;
use App\Support\QueryOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerRequest extends FormRequest
{
    /**
     * Columns a client is allowed to sort by. Anything else is rejected
     * before it can reach the query builder.
     */
    public const SORTABLE = ['id', 'name', 'email', 'status', 'created_at', 'updated_at'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(CustomerStatus::class)],
            'sort' => ['sometimes', 'string', Rule::in($this->sortableValues())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function options(): QueryOptions
    {
        return QueryOptions::fromArray($this->validated());
    }

    /**
     * Both ascending and descending forms of every sortable column.
     *
     * @return array<int, string>
     */
    private function sortableValues(): array
    {
        return array_merge(
            self::SORTABLE,
            array_map(static fn (string $column): string => '-'.$column, self::SORTABLE),
        );
    }
}
