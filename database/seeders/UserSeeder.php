<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // The API account used in the documentation, the request collection
        // and the Swagger UI. Credentials are configurable so a deployment is
        // never obliged to keep the demo defaults.
        User::query()->updateOrCreate(
            ['email' => (string) env('API_USER_EMAIL', 'api@moneyflex.test')],
            [
                'name' => 'MoneyFlex API',
                'password' => Hash::make((string) env('API_USER_PASSWORD', 'password123')),
                'email_verified_at' => now(),
            ],
        );
    }
}
