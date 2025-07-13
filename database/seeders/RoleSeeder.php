<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin - all permissions
        $superAdmin = Role::create(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - most permissions
        $admin = Role::create(['name' => 'Admin']);
        $admin->givePermissionTo(Permission::where('name', 'not like', 'system.%')->get());

        // User - basic access
        $user = Role::create(['name' => 'User']);
        $user->givePermissionTo(['artists.view', 'albums.view', 'tracks.view']);
    }
}
