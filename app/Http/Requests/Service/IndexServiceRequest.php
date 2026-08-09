<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\Enums\ServiceStatus;
use App\Support\QueryOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexServiceRequest extends FormRequest
{
    public const SORTABLE = ['id', 'name', 'price', 'status', 'starts_at', 'ends_at', 'created_at', 'updated_at'];

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
            'status' => ['sometimes', Rule::enum(ServiceStatus::class)],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
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
