# RBAC Testing Guide

This guide covers comprehensive Role-Based Access Control (RBAC) testing for the Chinook Filament admin panel using
spatie/laravel-permission, including role hierarchy, permission management, and access control validation.

## Table of Contents

- [Overview](#overview)
- [Role Hierarchy Testing](#role-hierarchy-testing)
- [Permission Testing](#permission-testing)
- [Resource Access Control](#resource-access-control)
- [Dynamic Permission Testing](#dynamic-permission-testing)
- [Role Assignment Testing](#role-assignment-testing)
- [Permission Inheritance Testing](#permission-inheritance-testing)
- [Performance Testing](#performance-testing)
- [Integration Testing](#integration-testing)

## Overview

RBAC testing ensures that the hierarchical role system works correctly, permissions are properly enforced, and users can
only access resources and perform actions they are authorized for.

### Testing Objectives

- **Role Hierarchy**: Verify proper role inheritance and hierarchy
- **Permission Enforcement**: Test granular permission controls
- **Access Control**: Validate resource and action access restrictions
- **Security**: Ensure no unauthorized access or privilege escalation
- **Performance**: Test RBAC performance with large user bases

## Role Hierarchy Testing

### Basic Role Testing

```php
<?php

namespace Tests\Feature\ChinookAdmin\RBAC;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create role hierarchy
        $this->createRoleHierarchy();
        $this->createPermissions();
    }

    private function createRoleHierarchy(): void
    {
        Role::create(['name' => 'Super Admin']);
        Role::create(['name' => 'Admin']);
        Role::create(['name' => 'Manager']);
        Role::create(['name' => 'Editor']);
        Role::create(['name' => 'Customer Service']);
        Role::create(['name' => 'User']);
        Role::create(['name' => 'Guest']);
    }

    private function createPermissions(): void
    {
        $permissions = [
            // Artist permissions
            'view-artists', 'create-artists', 'edit-artists', 'delete-artists',
            // Album permissions
            'view-albums', 'create-albums', 'edit-albums', 'delete-albums',
            // Track permissions
            'view-tracks', 'create-tracks', 'edit-tracks', 'delete-tracks',
            // Customer permissions
            'view-customers', 'create-customers', 'edit-customers', 'delete-customers',
            // Employee permissions
            'view-employees', 'create-employees', 'edit-employees', 'delete-employees',
            // System permissions
            'manage-users', 'manage-roles', 'manage-permissions', 'view-analytics',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $allPermissions = Permission::all();
        
        foreach ($allPermissions as $permission) {
            $this->assertTrue(
                $superAdmin->hasPermissionTo($permission->name),
                "Super Admin should have {$permission->name} permission"
            );
        }
    }

    public function test_admin_has_most_permissions_except_system(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Admin should have content management permissions
        $this->assertTrue($admin->hasPermissionTo('view-artists'));
        $this->assertTrue($admin->hasPermissionTo('create-artists'));
        $this->assertTrue($admin->hasPermissionTo('edit-artists'));
        $this->assertTrue($admin->hasPermissionTo('delete-artists'));

        // Admin should NOT have system management permissions
        $this->assertFalse($admin->hasPermissionTo('manage-users'));
        $this->assertFalse($admin->hasPermissionTo('manage-roles'));
        $this->assertFalse($admin->hasPermissionTo('manage-permissions'));
    }

    public function test_manager_has_limited_permissions(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('Manager');

        // Manager can view and edit but not delete
        $this->assertTrue($manager->hasPermissionTo('view-artists'));
        $this->assertTrue($manager->hasPermissionTo('edit-artists'));
        $this->assertFalse($manager->hasPermissionTo('delete-artists'));

        // Manager cannot manage employees
        $this->assertFalse($manager->hasPermissionTo('create-employees'));
        $this->assertFalse($manager->hasPermissionTo('delete-employees'));
    }

    public function test_editor_has_content_permissions_only(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('Editor');

        // Editor can manage content
        $this->assertTrue($editor->hasPermissionTo('view-artists'));
        $this->assertTrue($editor->hasPermissionTo('create-artists'));
        $this->assertTrue($editor->hasPermissionTo('edit-artists'));

        // Editor cannot delete or manage customers
        $this->assertFalse($editor->hasPermissionTo('delete-artists'));
        $this->assertFalse($editor->hasPermissionTo('view-customers'));
    }

    public function test_guest_has_minimal_permissions(): void
    {
        $guest = User::factory()->create();
        $guest->assignRole('Guest');

        // Guest can only view basic content
        $this->assertTrue($guest->hasPermissionTo('view-artists'));
        $this->assertTrue($guest->hasPermissionTo('view-albums'));

        // Guest cannot create, edit, or delete
        $this->assertFalse($guest->hasPermissionTo('create-artists'));
        $this->assertFalse($guest->hasPermissionTo('edit-artists'));
        $this->assertFalse($guest->hasPermissionTo('delete-artists'));
    }
}
```

### Role Assignment Testing

```php
public function test_user_can_have_single_role(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $this->assertTrue($user->hasRole('Editor'));
    $this->assertFalse($user->hasRole('Admin'));
    $this->assertCount(1, $user->roles);
}

public function test_user_can_have_multiple_roles(): void
{
    $user = User::factory()->create();
    $user->assignRole(['Editor', 'Customer Service']);

    $this->assertTrue($user->hasRole('Editor'));
    $this->assertTrue($user->hasRole('Customer Service'));
    $this->assertCount(2, $user->roles);
}

public function test_role_can_be_removed_from_user(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $this->assertTrue($user->hasRole('Editor'));

    $user->removeRole('Editor');

    $this->assertFalse($user->hasRole('Editor'));
    $this->assertCount(0, $user->roles);
}

public function test_user_roles_can_be_synced(): void
{
    $user = User::factory()->create();
    $user->assignRole(['Editor', 'Customer Service']);

    $this->assertCount(2, $user->roles);

    $user->syncRoles(['Manager']);

    $this->assertTrue($user->hasRole('Manager'));
    $this->assertFalse($user->hasRole('Editor'));
    $this->assertFalse($user->hasRole('Customer Service'));
    $this->assertCount(1, $user->roles);
}
```

## Permission Testing

### Direct Permission Testing

```php
public function test_user_can_have_direct_permissions(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('view-artists');

    $this->assertTrue($user->hasPermissionTo('view-artists'));
    $this->assertFalse($user->hasPermissionTo('edit-artists'));
}

public function test_user_can_have_multiple_direct_permissions(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view-artists', 'edit-artists']);

    $this->assertTrue($user->hasPermissionTo('view-artists'));
    $this->assertTrue($user->hasPermissionTo('edit-artists'));
    $this->assertFalse($user->hasPermissionTo('delete-artists'));
}

public function test_permission_can_be_revoked_from_user(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('view-artists');

    $this->assertTrue($user->hasPermissionTo('view-artists'));

    $user->revokePermissionTo('view-artists');

    $this->assertFalse($user->hasPermissionTo('view-artists'));
}

public function test_user_permissions_can_be_synced(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo(['view-artists', 'edit-artists']);

    $this->assertTrue($user->hasPermissionTo('view-artists'));
    $this->assertTrue($user->hasPermissionTo('edit-artists'));

    $user->syncPermissions(['view-albums']);

    $this->assertFalse($user->hasPermissionTo('view-artists'));
    $this->assertFalse($user->hasPermissionTo('edit-artists'));
    $this->assertTrue($user->hasPermissionTo('view-albums'));
}
```

### Role Permission Testing

```php
public function test_role_can_have_permissions(): void
{
    $role = Role::findByName('Editor');
    $role->givePermissionTo(['view-artists', 'create-artists', 'edit-artists']);

    $this->assertTrue($role->hasPermissionTo('view-artists'));
    $this->assertTrue($role->hasPermissionTo('create-artists'));
    $this->assertTrue($role->hasPermissionTo('edit-artists'));
    $this->assertFalse($role->hasPermissionTo('delete-artists'));
}

public function test_user_inherits_role_permissions(): void
{
    $role = Role::findByName('Editor');
    $role->givePermissionTo(['view-artists', 'create-artists', 'edit-artists']);

    $user = User::factory()->create();
    $user->assignRole('Editor');

    $this->assertTrue($user->hasPermissionTo('view-artists'));
    $this->assertTrue($user->hasPermissionTo('create-artists'));
    $this->assertTrue($user->hasPermissionTo('edit-artists'));
    $this->assertFalse($user->hasPermissionTo('delete-artists'));
}

public function test_user_has_combined_role_and_direct_permissions(): void
{
    $role = Role::findByName('Editor');
    $role->givePermissionTo(['view-artists', 'edit-artists']);

    $user = User::factory()->create();
    $user->assignRole('Editor');
    $user->givePermissionTo('delete-artists');

    $this->assertTrue($user->hasPermissionTo('view-artists')); // From role
    $this->assertTrue($user->hasPermissionTo('edit-artists')); // From role
    $this->assertTrue($user->hasPermissionTo('delete-artists')); // Direct permission
}
```

## Resource Access Control

### Filament Resource Access Testing

```php
public function test_admin_can_access_all_resources(): void
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $resources = [
        '/chinook-admin/artists',
        '/chinook-admin/albums',
        '/chinook-admin/tracks',
        '/chinook-admin/customers',
        '/chinook-admin/employees',
    ];

    foreach ($resources as $resource) {
        $response = $this->actingAs($admin)->get($resource);
        $this->assertEquals(200, $response->getStatusCode(), 
            "Admin should access {$resource}");
    }
}

public function test_editor_cannot_access_customer_management(): void
{
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $response = $this->actingAs($editor)
        ->get('/chinook-admin/customers');

    $response->assertStatus(403);
}

public function test_customer_service_can_access_customers_only(): void
{
    $customerService = User::factory()->create();
    $customerService->assignRole('Customer Service');

    // Can access customers
    $response = $this->actingAs($customerService)
        ->get('/chinook-admin/customers');
    $response->assertStatus(200);

    // Cannot access artists
    $response = $this->actingAs($customerService)
        ->get('/chinook-admin/artists');
    $response->assertStatus(403);
}

public function test_guest_can_only_view_content(): void
{
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    // Can view artists
    $response = $this->actingAs($guest)
        ->get('/chinook-admin/artists');
    $response->assertStatus(200);

    // Cannot create artists
    $response = $this->actingAs($guest)
        ->get('/chinook-admin/artists/create');
    $response->assertStatus(403);

    // Cannot edit artists
    $artist = Artist::factory()->create();
    $response = $this->actingAs($guest)
        ->get("/chinook-admin/artists/{$artist->id}/edit");
    $response->assertStatus(403);
}
```

### Action-Level Access Control

```php
public function test_role_based_action_access(): void
{
    $artist = Artist::factory()->create();

    // Admin can perform all actions
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post("/chinook-admin/artists/{$artist->id}/actions/activate")
        ->assertRedirect();

    // Editor can activate but not delete
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $this->actingAs($editor)
        ->post("/chinook-admin/artists/{$artist->id}/actions/activate")
        ->assertRedirect();

    $this->actingAs($editor)
        ->post("/chinook-admin/artists/{$artist->id}/actions/delete")
        ->assertStatus(403);

    // Guest cannot perform any actions
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    $this->actingAs($guest)
        ->post("/chinook-admin/artists/{$artist->id}/actions/activate")
        ->assertStatus(403);
}

public function test_bulk_action_permissions(): void
{
    $artists = Artist::factory()->count(3)->create();
    $artistIds = $artists->pluck('id')->toArray();

    // Admin can perform bulk actions
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post('/chinook-admin/artists/bulk-actions/activate', [
            'records' => $artistIds,
        ])
        ->assertRedirect();

    // Guest cannot perform bulk actions
    $guest = User::factory()->create();
    $guest->assignRole('Guest');

    $this->actingAs($guest)
        ->post('/chinook-admin/artists/bulk-actions/activate', [
            'records' => $artistIds,
        ])
        ->assertStatus(403);
}
```

## Dynamic Permission Testing

### Ownership-Based Permissions

```php
public function test_user_can_edit_own_content(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $ownArtist = Artist::factory()->create(['created_by' => $user->id]);
    $otherArtist = Artist::factory()->create(['created_by' => User::factory()->create()->id]);

    // Can edit own content
    $response = $this->actingAs($user)
        ->get("/chinook-admin/artists/{$ownArtist->id}/edit");
    $response->assertStatus(200);

    // Cannot edit other's content (if ownership policy is enforced)
    $response = $this->actingAs($user)
        ->get("/chinook-admin/artists/{$otherArtist->id}/edit");
    $response->assertStatus(403);
}

public function test_manager_can_edit_team_content(): void
{
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $teamMember = User::factory()->create();
    $teamMember->assignRole('Editor');
    $teamMember->update(['manager_id' => $manager->id]);

    $teamArtist = Artist::factory()->create(['created_by' => $teamMember->id]);

    // Manager can edit team member's content
    $response = $this->actingAs($manager)
        ->get("/chinook-admin/artists/{$teamArtist->id}/edit");
    $response->assertStatus(200);
}
```

### Conditional Permissions

```php
public function test_time_based_permissions(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    // Create artist during business hours (mock)
    Carbon::setTestNow(Carbon::create(2023, 1, 1, 10, 0, 0)); // 10 AM

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => 'Business Hours Artist',
            'country' => 'US',
        ]);
    $response->assertRedirect();

    // Try to create artist outside business hours (mock)
    Carbon::setTestNow(Carbon::create(2023, 1, 1, 22, 0, 0)); // 10 PM

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => 'After Hours Artist',
            'country' => 'US',
        ]);
    $response->assertStatus(403);

    Carbon::setTestNow(); // Reset
}

public function test_resource_state_based_permissions(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $activeArtist = Artist::factory()->create(['is_active' => true]);
    $inactiveArtist = Artist::factory()->create(['is_active' => false]);

    // Can edit active artists
    $response = $this->actingAs($user)
        ->get("/chinook-admin/artists/{$activeArtist->id}/edit");
    $response->assertStatus(200);

    // Cannot edit inactive artists (if policy enforces this)
    $response = $this->actingAs($user)
        ->get("/chinook-admin/artists/{$inactiveArtist->id}/edit");
    $response->assertStatus(403);
}
```

## Permission Inheritance Testing

### Hierarchical Permission Testing

```php
public function test_permission_inheritance_hierarchy(): void
{
    // Set up role hierarchy with permissions
    $superAdmin = Role::findByName('Super Admin');
    $admin = Role::findByName('Admin');
    $manager = Role::findByName('Manager');
    $editor = Role::findByName('Editor');

    // Super Admin has all permissions
    $superAdmin->givePermissionTo(Permission::all());

    // Admin inherits most permissions except system management
    $adminPermissions = Permission::where('name', 'not like', 'manage-%')->get();
    $admin->givePermissionTo($adminPermissions);

    // Manager has subset of admin permissions
    $managerPermissions = Permission::where('name', 'like', 'view-%')
        ->orWhere('name', 'like', 'edit-%')
        ->get();
    $manager->givePermissionTo($managerPermissions);

    // Editor has content permissions only
    $editorPermissions = Permission::where('name', 'like', '%-artists')
        ->orWhere('name', 'like', '%-albums')
        ->orWhere('name', 'like', '%-tracks')
        ->get();
    $editor->givePermissionTo($editorPermissions);

    // Test inheritance
    $superAdminUser = User::factory()->create();
    $superAdminUser->assignRole('Super Admin');

    $adminUser = User::factory()->create();
    $adminUser->assignRole('Admin');

    $managerUser = User::factory()->create();
    $managerUser->assignRole('Manager');

    $editorUser = User::factory()->create();
    $editorUser->assignRole('Editor');

    // Super Admin can do everything
    $this->assertTrue($superAdminUser->hasPermissionTo('manage-users'));
    $this->assertTrue($superAdminUser->hasPermissionTo('delete-artists'));

    // Admin cannot manage users but can delete artists
    $this->assertFalse($adminUser->hasPermissionTo('manage-users'));
    $this->assertTrue($adminUser->hasPermissionTo('delete-artists'));

    // Manager can view and edit but not delete
    $this->assertTrue($managerUser->hasPermissionTo('view-artists'));
    $this->assertTrue($managerUser->hasPermissionTo('edit-artists'));
    $this->assertFalse($managerUser->hasPermissionTo('delete-artists'));

    // Editor has limited content permissions
    $this->assertTrue($editorUser->hasPermissionTo('view-artists'));
    $this->assertFalse($editorUser->hasPermissionTo('view-customers'));
}
```

## Performance Testing

### RBAC Performance Testing

```php
public function test_permission_check_performance(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Give role many permissions
    $permissions = Permission::factory()->count(100)->create();
    $user->roles->first()->givePermissionTo($permissions);

    $startTime = microtime(true);

    // Check multiple permissions
    for ($i = 0; $i < 50; $i++) {
        $user->hasPermissionTo($permissions->random()->name);
    }

    $endTime = microtime(true);
    $checkTime = $endTime - $startTime;

    $this->assertLessThan(1.0, $checkTime, 
        "Permission checks took {$checkTime} seconds");
}

public function test_role_assignment_performance(): void
{
    $users = User::factory()->count(100)->create();
    $role = Role::findByName('Editor');

    $startTime = microtime(true);

    foreach ($users as $user) {
        $user->assignRole($role);
    }

    $endTime = microtime(true);
    $assignmentTime = $endTime - $startTime;

    $this->assertLessThan(2.0, $assignmentTime, 
        "Role assignments took {$assignmentTime} seconds");
}

public function test_bulk_permission_operations(): void
{
    $users = User::factory()->count(50)->create();
    $permissions = Permission::take(10)->get();

    $startTime = microtime(true);

    foreach ($users as $user) {
        $user->givePermissionTo($permissions);
    }

    $endTime = microtime(true);
    $bulkTime = $endTime - $startTime;

    $this->assertLessThan(3.0, $bulkTime, 
        "Bulk permission operations took {$bulkTime} seconds");
}
```

## Integration Testing

### Middleware Integration Testing

```php
public function test_rbac_middleware_integration(): void
{
    $user = User::factory()->create();
    $user->assignRole('Guest');

    // Test that middleware properly blocks access
    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists/create');

    $response->assertStatus(403);

    // Give permission and test access
    $user->givePermissionTo('create-artists');

    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists/create');

    $response->assertStatus(200);
}

public function test_policy_integration(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $artist = Artist::factory()->create(['created_by' => $user->id]);

    // Test policy allows access to own content
    $this->assertTrue($user->can('update', $artist));

    $otherArtist = Artist::factory()->create();

    // Test policy denies access to other's content
    $this->assertFalse($user->can('update', $otherArtist));
}
```

## Related Documentation

- **[Authentication Testing](090-auth-testing.md)** - Authentication and session testing
- **[Security Testing](160-security-testing.md)** - Security vulnerability testing
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[API Testing](110-api-testing.md)** - API endpoint authorization testing

---

## Navigation

**← Previous:** [Authentication Testing](090-auth-testing.md)

**Next →** [API Testing](110-api-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
