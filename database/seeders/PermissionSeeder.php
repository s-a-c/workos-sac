<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Artist permissions
            'artists.view', 'artists.create', 'artists.update', 'artists.delete',
            // Album permissions
            'albums.view', 'albums.create', 'albums.update', 'albums.delete',
            // Track permissions
            'tracks.view', 'tracks.create', 'tracks.update', 'tracks.delete',
            // Category permissions
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            // Customer permissions
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            // System permissions
            'system.admin', 'system.reports', 'system.backup',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
    }
}
