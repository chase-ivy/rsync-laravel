<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run with:  php artisan db:seed
     * Your real data comes from the import (php artisan rsync:import-legacy), not from here.
     */
    public function run(): void
    {
        // Demo accounts only on your own computer, never on a live server.
        if (app()->environment('local', 'testing')) {
            $this->call(DemoAccountsSeeder::class);
        } else {
            $this->command?->warn('Not a local environment: demo accounts were NOT created.');
        }
    }
}