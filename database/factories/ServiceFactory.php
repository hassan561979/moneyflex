<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-1 year', '+1 month');

        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->randomElement([
                'Current Account',
                'Savings Account',
                'Personal Loan',
                'Credit Card',
                'Wealth Advisory',
                'Payment Gateway',
                'Payroll Transfer',
                'FX Settlement',
            ]),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 5000),
            'status' => fake()->randomElement(ServiceStatus::cases()),
            'starts_at' => $startsAt,
            'ends_at' => fake()->boolean(70)
                ? fake()->dateTimeBetween($startsAt, '+2 years')
                : null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Suspended]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => ServiceStatus::Cancelled]);
    }

    /**
     * An open-ended service with no agreed end date.
     */
    public function ongoing(): static
    {
        return $this->state(fn (): array => ['ends_at' => null]);
    }
}
