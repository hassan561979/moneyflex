<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // A known customer, so the documentation examples and the Postman
        // collection always have a stable record to point at.
        $showcase = Customer::query()->firstOrCreate(
            ['email' => 'acme@moneyflex.test'],
            [
                'name' => 'Acme Holdings',
                'phone' => '+971500000001',
                'address' => '1 Sheikh Zayed Road, Dubai',
                'status' => CustomerStatus::Active,
            ],
        );

        if ($showcase->services()->doesntExist()) {
            Service::factory()
                ->for($showcase)
                ->active()
                ->createMany([
                    ['name' => 'Current Account', 'price' => 0.00],
                    ['name' => 'Payment Gateway', 'price' => 249.99],
                    ['name' => 'Wealth Advisory', 'price' => 1500.00],
                ]);
        }

        // Plus a spread of random customers with between one and five
        // services each, to exercise pagination and filtering.
        Customer::factory()
            ->count(24)
            ->create()
            ->each(function (Customer $customer): void {
                Service::factory()
                    ->for($customer)
                    ->count(fake()->numberBetween(1, 5))
                    ->create();
            });
    }
}
