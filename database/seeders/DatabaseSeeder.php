<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\CacheKeys;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CustomerSeeder::class,
        ]);

        // Seeding writes straight to the database, bypassing the service layer
        // that would normally invalidate. Without this, the API would keep
        // serving listings built from the previous data set.
        Cache::tags([CacheKeys::SERVICES_TAG])->flush();
    }
}
