<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Seeds only when there is nothing to lose.
 *
 * The container runs this on boot so a fresh checkout comes up with the
 * account the documentation quotes. Running the seeder unconditionally would
 * add another batch of demonstration customers on every restart, so this backs
 * off the moment the database holds anything.
 */
class SeedIfEmpty extends Command
{
    protected $signature = 'db:seed-if-empty';

    protected $description = 'Seed the database, but only when it is empty';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('The database already holds data. Nothing to seed.');

            return self::SUCCESS;
        }

        $this->info('Empty database, seeding.');

        $this->call('db:seed', ['--force' => true]);

        return self::SUCCESS;
    }
}
