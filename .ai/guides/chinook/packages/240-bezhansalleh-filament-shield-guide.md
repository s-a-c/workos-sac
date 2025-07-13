# 1. Bezhansalleh Filament Shield Integration Guide

> **Package Source:** [bezhansalleh/filament-shield](https://github.com/bezhanSalleh/filament-shield)  
> **Official Documentation:** [Filament Shield Documentation](https://filamentphp.com/plugins/bezhansalleh-shield)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook RBAC hierarchy and entity prefixing  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Plugin Registration](#131-plugin-registration)
  - [1.3.2. Shield Configuration](#132-shield-configuration)
  - [1.3.3. Permission Generation](#133-permission-generation)
- [1.4. Chinook RBAC Integration](#14-chinook-rbac-integration)
  - [1.4.1. Role Hierarchy Setup](#141-role-hierarchy-setup)
  - [1.4.2. Resource Permissions](#142-resource-permissions)
  - [1.4.3. Custom Permissions](#143-custom-permissions)
- [1.5. Admin Panel Integration](#15-admin-panel-integration)
- [1.6. Security Best Practices](#16-security-best-practices)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Filament Shield documentation](https://filamentphp.com/plugins/bezhansalleh-shield) for Laravel 12 and Chinook project requirements, integrating with the existing [spatie/laravel-permission](140-spatie-permission-guide.md) RBAC system.

**Filament Shield** provides a comprehensive role and permission management interface for Filament admin panels. It automatically generates permissions for Filament resources and provides an intuitive UI for managing user access control.

### 1.2.1. Key Features

- **Automatic Permission Generation**: Creates permissions for all Filament resources
- **Role Management Interface**: Visual role and permission assignment
- **Resource-Level Security**: Granular control over resource access
- **Policy Integration**: Works with Laravel authorization policies
- **Multi-Panel Support**: Supports multiple Filament panels
- **Hierarchical Roles**: Supports role inheritance and hierarchies

### 1.2.2. Integration with Spatie Permission

> **RBAC Foundation:** Built on [spatie/laravel-permission](140-spatie-permission-guide.md) with Chinook role hierarchy

**Chinook Role Hierarchy:**
1. **Super Admin** - Full system access
2. **Admin** - Administrative access to most features
3. **Manager** - Content management and user oversight
4. **Editor** - Content creation and editing
5. **Customer Service** - Customer interaction and support
6. **User** - Basic authenticated user access
7. **Guest** - Limited public access

## 1.3. Installation & Configuration

### 1.3.1. Plugin Registration

> **Configuration Source:** Based on [official installation guide](https://filamentphp.com/plugins/bezhansalleh-shield/installation)  
> **Chinook Enhancement:** Already configured in AdminPanelProvider

The plugin is already registered in the admin panel. Verify configuration:

<augment_code_snippet path="app/Providers/Filament/AdminPanelProvider.php" mode="EXCERPT">
````php
<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ... existing configuration ...
            
            // Filament Shield Plugin
            ->plugin(
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ])
            );
    }
}
````
</augment_code_snippet>

### 1.3.2. Shield Configuration

> **Configuration Source:** Adapted from [shield configuration](https://github.com/bezhanSalleh/filament-shield/blob/main/config/filament-shield.php)  
> **Chinook Modifications:** Enhanced for Chinook entity prefixing and role structure

<augment_code_snippet path="config/filament-shield.php" mode="EXCERPT">
````php
<?php
// Configuration adapted from: https://github.com/bezhanSalleh/filament-shield/blob/main/config/filament-shield.php
// Chinook modifications: Enhanced for Chinook entity prefixing and RBAC hierarchy
// Laravel 12 updates: Modern syntax and framework patterns

return [
    /*
     * Shield resource configuration
     */
    'shield_resource' => [
        'should_register_navigation' => true,
        'slug' => 'shield/roles',
        'navigation_sort' => -1,
        'navigation_badge' => true,
        'navigation_group' => 'Security Management',
        'is_globally_searchable' => false,
        'show_model_path' => true,
        'is_scoped_to_tenant' => true,
    ],

    /*
     * Permission prefixes for Chinook resources
     */
    'permission_prefixes' => [
        'resource' => [
            'view',
            'view_any',
            'create',
            'update',
            'restore',
            'restore_any',
            'replicate',
            'reorder',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
        ],

        'page' => 'page',
        'widget' => 'widget',
    ],

    /*
     * Entities configuration for Chinook models
     */
    'entities' => [
        'pages' => true,
        'widgets' => true,
        'resources' => true,
        'custom_permissions' => true,
    ],

    /*
     * Generator configuration
     */
    'generator' => [
        'option' => 'policies_and_permissions', // Generate both policies and permissions
        'policy_directory' => 'Policies',
    ],

    /*
     * Exclude specific models from permission generation
     */
    'exclude' => [
        'enabled' => true,
        'pages' => [
            'Dashboard',
        ],
        'widgets' => [
            'AccountWidget',
            'FilamentInfoWidget',
        ],
        'resources' => [],
    ],

    /*
     * Discovery configuration for auto-detection
     */
    'discovery' => [
        'discover_all_resources' => true,
        'discover_all_widgets' => true,
        'discover_all_pages' => true,
    ],

    /*
     * Register permissions for Chinook-specific features
     */
    'register_role_policy' => [
        'enabled' => true,
    ],

    /*
     * Chinook-specific configuration
     */
    'chinook' => [
        'enable_entity_prefixing' => true,
        'role_hierarchy_validation' => true,
        'auto_assign_permissions' => true,
        'enable_audit_logging' => true,
    ],
];
````
</augment_code_snippet>

### 1.3.3. Permission Generation

> **Permission Generation:** Based on [shield commands](https://filamentphp.com/plugins/bezhansalleh-shield/installation#generating-permissions)  
> **Chinook Enhancement:** Generate permissions for all Chinook resources

**Generate Shield Resources and Permissions:**

```bash
# Install Shield resources
php artisan shield:install

# Generate permissions for all resources
php artisan shield:generate --all

# Generate specific permissions for Chinook resources
php artisan shield:generate --resource=ChinookArtistResource
php artisan shield:generate --resource=ChinookAlbumResource
php artisan shield:generate --resource=ChinookTrackResource

# Generate custom permissions
php artisan shield:generate --option=custom
```

**Verify Generated Permissions:**

```bash
# List all generated permissions
php artisan permission:show

# Check role assignments
php artisan shield:super-admin --user=admin@example.com
```

## 1.4. Chinook RBAC Integration

### 1.4.1. Role Hierarchy Setup

> **Role Structure:** Based on [Chinook RBAC hierarchy](140-spatie-permission-guide.md#role-hierarchy)
> **Shield Integration:** Enhanced with Filament Shield management interface

<augment_code_snippet path="database/seeders/ChinookRolePermissionSeeder.php" mode="EXCERPT">
````php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ChinookRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create Chinook role hierarchy
        $roles = [
            'Super Admin' => 'Full system access and administration',
            'Admin' => 'Administrative access to most features',
            'Manager' => 'Content management and user oversight',
            'Editor' => 'Content creation and editing',
            'Customer Service' => 'Customer interaction and support',
            'User' => 'Basic authenticated user access',
            'Guest' => 'Limited public access',
        ];

        foreach ($roles as $roleName => $description) {
            Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => $description]
            );
        }

        // Generate Shield permissions for Chinook resources
        $this->generateChinookResourcePermissions();

        // Assign permissions to roles
        $this->assignRolePermissions();
    }

    private function generateChinookResourcePermissions(): void
    {
        $resources = [
            'ChinookArtistResource',
            'ChinookAlbumResource',
            'ChinookTrackResource',
            'ChinookPlaylistResource',
            'ChinookCustomerResource',
            'ChinookInvoiceResource',
        ];

        $actions = [
            'view', 'view_any', 'create', 'update',
            'delete', 'delete_any', 'restore', 'restore_any',
            'force_delete', 'force_delete_any', 'replicate'
        ];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action}_{$resource}",
                    'guard_name' => 'web'
                ]);
            }
        }
    }

    private function assignRolePermissions(): void
    {
        // Super Admin gets all permissions
        $superAdmin = Role::findByName('Super Admin');
        $superAdmin->givePermissionTo(Permission::all());

        // Admin gets most permissions except force delete
        $admin = Role::findByName('Admin');
        $adminPermissions = Permission::where('name', 'not like', '%force_delete%')->get();
        $admin->givePermissionTo($adminPermissions);

        // Manager gets content management permissions
        $manager = Role::findByName('Manager');
        $managerPermissions = Permission::whereIn('name', [
            'view_ChinookArtistResource', 'view_any_ChinookArtistResource',
            'create_ChinookArtistResource', 'update_ChinookArtistResource',
            'view_ChinookAlbumResource', 'view_any_ChinookAlbumResource',
            'create_ChinookAlbumResource', 'update_ChinookAlbumResource',
            'view_ChinookTrackResource', 'view_any_ChinookTrackResource',
            'create_ChinookTrackResource', 'update_ChinookTrackResource',
        ])->get();
        $manager->givePermissionTo($managerPermissions);

        // Editor gets content creation permissions
        $editor = Role::findByName('Editor');
        $editorPermissions = Permission::whereIn('name', [
            'view_ChinookArtistResource', 'view_any_ChinookArtistResource',
            'view_ChinookAlbumResource', 'view_any_ChinookAlbumResource',
            'view_ChinookTrackResource', 'view_any_ChinookTrackResource',
            'create_ChinookTrackResource', 'update_ChinookTrackResource',
        ])->get();
        $editor->givePermissionTo($editorPermissions);
    }
}
````
</augment_code_snippet>

### 1.4.2. Resource Permissions

> **Resource Security:** Automatic permission enforcement for Chinook resources

<augment_code_snippet path="app/Filament/Admin/Resources/ChinookArtistResource.php" mode="EXCERPT">
````php
<?php

namespace App\Filament\Admin\Resources;

use App\Models\ChinookArtist;
use Filament\Resources\Resource;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class ChinookArtistResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = ChinookArtist::class;
    protected static ?string $navigationIcon = 'heroicon-o-microphone';
    protected static ?string $navigationGroup = 'Music Catalog';

    /**
     * Get the permissions for this resource
     */
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'restore',
            'restore_any',
            'replicate',
            'reorder',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
        ];
    }

    /**
     * Check if user can access this resource
     */
    public static function canAccess(): bool
    {
        return auth()->user()->can('view_any_ChinookArtistResource');
    }

    /**
     * Check if user can view any records
     */
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_ChinookArtistResource');
    }

    /**
     * Check if user can create records
     */
    public static function canCreate(): bool
    {
        return auth()->user()->can('create_ChinookArtistResource');
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Media Library Plugin Guide](250-filament-spatie-media-library-plugin-guide.md) | **Next:** [Activity Log Guide](270-rmsramos-activitylog-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
