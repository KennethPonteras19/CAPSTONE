<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Database\Seeders\AdminAccountSeeder;
use Database\Seeders\CustomerAccountSeeder;
use Database\Seeders\StaffAccountSeeder;
use Database\Seeders\SuperAdminSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Seed core accounts for multi-role authentication
        $this->call([
            AdminAccountSeeder::class,
            CustomerAccountSeeder::class,
            StaffAccountSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
