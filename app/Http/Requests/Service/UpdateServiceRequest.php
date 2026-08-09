<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'status' => ['sometimes', Rule::enum(ServiceStatus::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    /**
     * A partial update may send only one of the two dates, so compare the
     * incoming value against what is already stored rather than against a
     * field that is absent from this request.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $service = $this->route('service');

            if (! $service instanceof Service) {
                return;
            }

            $startsAt = $this->has('starts_at')
                ? $this->date('starts_at')
                : $service->starts_at;

            $endsAt = $this->has('ends_at')
                ? $this->date('ends_at')
                : $service->ends_at;

            if ($startsAt && $endsAt && $endsAt->lt($startsAt)) {
                $validator->errors()->add('ends_at', 'The end date must not be earlier than the start date.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.decimal' => 'The price may have at most two decimal places.',
        ];
    }
}
