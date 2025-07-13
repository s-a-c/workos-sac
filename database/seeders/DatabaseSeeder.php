<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Contracts\Permission;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WorldSeeder::class,
            // PermissionSeeder::class,
            // RoleSeeder::class,
            UserSeeder::class,
            // ChinookDatabaseSeeder::class,
        ]);
    }
}
