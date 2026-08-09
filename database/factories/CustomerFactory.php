<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+9715########'),
            'address' => fake()->address(),
            'status' => fake()->randomElement(CustomerStatus::cases()),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => CustomerStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => CustomerStatus::Inactive]);
    }
}
