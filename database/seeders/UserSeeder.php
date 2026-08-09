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
            ['email' => (string) config('moneyflex.api_user.email')],
            [
                'name' => 'MoneyFlex API',
                'password' => Hash::make((string) config('moneyflex.api_user.password')),
                'email_verified_at' => now(),
            ],
        );
    }
}
