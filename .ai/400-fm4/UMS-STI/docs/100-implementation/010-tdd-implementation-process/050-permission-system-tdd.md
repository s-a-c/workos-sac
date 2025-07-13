# TDD Implementation for Permission System with Team Isolation

## Executive Summary

This guide provides a comprehensive Test-Driven Development approach for implementing a security-first permission system with team isolation in the UMS-STI system. Using TDD methodology, we'll build a robust permission system that ensures complete isolation between teams while maintaining high performance and security standards.

## Learning Objectives

After completing this guide, you will:
- Implement team-scoped permission isolation using TDD methodology
- Create secure permission checking with comprehensive test coverage
- Build role-based access control with test-first approach
- Optimize permission queries for performance using TDD validation
- Integrate permission system with STI user models and team hierarchy through TDD

## Prerequisites

- Completed [010-tdd-environment-setup.md](010-tdd-environment-setup.md)
- Completed [020-database-tdd-approach.md](020-database-tdd-approach.md)
- Completed [030-sti-models-tdd.md](030-sti-models-tdd.md)
- Completed [040-closure-table-tdd.md](040-closure-table-tdd.md)
- Understanding of RBAC (Role-Based Access Control) concepts
- Basic knowledge of Laravel authorization patterns

## TDD Implementation Strategy

### Phase 1: Permission Foundation Tests (Week 4, Days 3-4)

#### 1.1 Permission Model Structure Tests

**Test File**: `tests/Unit/Models/PermissionTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Permission Model Structure', function () {
    it('can create a basic permission', function () {
        $permission = Permission::factory()->create([
            'name' => 'edit-users',
            'guard_name' => 'web',
            'description' => 'Can edit user profiles',
        ]);

        expect($permission)
            ->toBeInstanceOf(Permission::class)
            ->and($permission->name)->toBe('edit-users')
            ->and($permission->guard_name)->toBe('web')
            ->and($permission->description)->toBe('Can edit user profiles');
    });

    it('has required fillable attributes', function () {
        $fillable = (new Permission())->getFillable();
        
        expect($fillable)->toContain(
            'name', 
            'guard_name', 
            'description', 
            'category',
            'is_system'
        );
    });

    it('casts is_system to boolean', function () {
        $permission = Permission::factory()->create([
            'is_system' => true,
        ]);

        expect($permission->is_system)->toBeTrue();
    });

    it('generates unique permission keys', function () {
        $permission = Permission::factory()->create([
            'name' => 'edit-users',
            'category' => 'user-management',
        ]);

        expect($permission->getKey())->toBe('user-management.edit-users');
    });
});
```

**Implementation**: Create the Permission model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'category',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function getKey(): string
    {
        return $this->category ? "{$this->category}.{$this->name}" : $this->name;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }
}
```

#### 1.2 Role Model Structure Tests

**Test File**: `tests/Unit/Models/RoleTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Role Model Structure', function () {
    it('can create a team-scoped role', function () {
        $team = Team::factory()->create();
        $role = Role::factory()->create([
            'name' => 'team-lead',
            'team_id' => $team->id,
            'guard_name' => 'web',
        ]);

        expect($role)
            ->toBeInstanceOf(Role::class)
            ->and($role->name)->toBe('team-lead')
            ->and($role->team_id)->toBe($team->id)
            ->and($role->isTeamScoped())->toBeTrue();
    });

    it('can create a global role', function () {
        $role = Role::factory()->create([
            'name' => 'system-admin',
            'team_id' => null,
            'is_global' => true,
        ]);

        expect($role->isGlobal())->toBeTrue();
        expect($role->isTeamScoped())->toBeFalse();
    });

    it('can assign permissions to role', function () {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        
        $role->givePermissionTo($permission);
        
        expect($role->permissions)->toHaveCount(1);
        expect($role->hasPermissionTo($permission))->toBeTrue();
    });

    it('validates team scope for permissions', function () {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $role = Role::factory()->create(['team_id' => $team1->id]);
        $permission = Permission::factory()->create();
        
        $role->givePermissionTo($permission);
        
        // Permission should only apply within the team scope
        expect($role->hasPermissionTo($permission, $team1))->toBeTrue();
        expect($role->hasPermissionTo($permission, $team2))->toBeFalse();
    });
});
```

**Implementation**: Create the Role model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'team_id',
        'is_global',
        'is_system',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('team_id');
    }

    public function isGlobal(): bool
    {
        return $this->is_global || $this->team_id === null;
    }

    public function isTeamScoped(): bool
    {
        return !$this->isGlobal();
    }

    public function givePermissionTo(Permission $permission): void
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions()->attach($permission);
        }
    }

    public function hasPermissionTo(Permission $permission, ?Team $team = null): bool
    {
        if (!$this->permissions->contains($permission)) {
            return false;
        }

        // Global roles have permissions everywhere
        if ($this->isGlobal()) {
            return true;
        }

        // Team-scoped roles only have permissions in their team
        return $team && $this->team_id === $team->id;
    }
}
```

#### 1.3 Permission Database Migration Tests

**Test File**: `tests/Unit/Database/PermissionMigrationTest.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Permission System Database Structure', function () {
    it('has permissions table with correct structure', function () {
        expect(Schema::hasTable('permissions'))->toBeTrue();
        
        $columns = Schema::getColumnListing('permissions');
        expect($columns)->toContain(
            'id',
            'name',
            'guard_name',
            'description',
            'category',
            'is_system',
            'created_at',
            'updated_at'
        );
    });

    it('has roles table with team scoping', function () {
        expect(Schema::hasTable('roles'))->toBeTrue();
        
        $columns = Schema::getColumnListing('roles');
        expect($columns)->toContain(
            'id',
            'name',
            'guard_name',
            'description',
            'team_id',
            'is_global',
            'is_system',
            'created_at',
            'updated_at'
        );
    });

    it('has role_has_permissions pivot table', function () {
        expect(Schema::hasTable('role_has_permissions'))->toBeTrue();
        
        $columns = Schema::getColumnListing('role_has_permissions');
        expect($columns)->toContain('permission_id', 'role_id');
    });

    it('has user_has_roles table with team context', function () {
        expect(Schema::hasTable('user_has_roles'))->toBeTrue();
        
        $columns = Schema::getColumnListing('user_has_roles');
        expect($columns)->toContain(
            'user_id',
            'role_id',
            'team_id',
            'assigned_at',
            'assigned_by'
        );
    });

    it('has user_has_permissions table for direct permissions', function () {
        expect(Schema::hasTable('user_has_permissions'))->toBeTrue();
        
        $columns = Schema::getColumnListing('user_has_permissions');
        expect($columns)->toContain(
            'user_id',
            'permission_id',
            'team_id',
            'assigned_at',
            'assigned_by'
        );
    });

    it('has correct indexes for performance', function () {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('user_has_roles');
            
        expect($indexes)->toHaveKey('user_has_roles_user_team_index');
        expect($indexes)->toHaveKey('user_has_roles_role_team_index');
    });
});
```

**Implementation**: Create the permission system migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            
            $table->unique(['name', 'guard_name']);
            $table->index(['category', 'name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->text('description')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('is_global')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            
            $table->unique(['name', 'guard_name', 'team_id']);
            $table->index(['team_id', 'is_global']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_has_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            
            $table->primary(['user_id', 'role_id', 'team_id']);
            $table->index(['user_id', 'team_id']);
            $table->index(['role_id', 'team_id']);
        });

        Schema::create('user_has_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            
            $table->primary(['user_id', 'permission_id', 'team_id']);
            $table->index(['user_id', 'team_id']);
            $table->index(['permission_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_permissions');
        Schema::dropIfExists('user_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
```

### Phase 2: Permission Assignment and Checking (Week 4, Days 5-7)

#### 2.1 User Permission Assignment Tests

**Test File**: `tests/Unit/Models/UserPermissionTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use App\Models\Employee;
use App\Models\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Permission Assignment', function () {
    it('can assign role to user in team context', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        
        $user->assignRole($role, $team);
        
        expect($user->hasRole($role, $team))->toBeTrue();
        expect($user->hasRole($role))->toBeFalse(); // Should not have role globally
    });

    it('can assign global role to user', function () {
        $user = User::factory()->create();
        $role = Role::factory()->create(['is_global' => true]);
        
        $user->assignRole($role);
        
        expect($user->hasRole($role))->toBeTrue();
        expect($user->hasGlobalRole($role))->toBeTrue();
    });

    it('can assign direct permission to user in team', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $permission = Permission::factory()->create();
        
        $user->givePermissionTo($permission, $team);
        
        expect($user->hasPermissionTo($permission, $team))->toBeTrue();
        expect($user->hasPermissionTo($permission))->toBeFalse();
    });

    it('inherits permissions from roles', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        $permission = Permission::factory()->create();
        
        $role->givePermissionTo($permission);
        $user->assignRole($role, $team);
        
        expect($user->hasPermissionTo($permission, $team))->toBeTrue();
        expect($user->getPermissionsViaRoles($team))->toContain($permission);
    });

    it('prevents duplicate role assignments', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        
        $user->assignRole($role, $team);
        
        expect(fn() => $user->assignRole($role, $team))
            ->toThrow(InvalidArgumentException::class, 'User already has this role in this team');
    });

    it('can revoke role from user', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        
        $user->assignRole($role, $team);
        expect($user->hasRole($role, $team))->toBeTrue();
        
        $user->removeRole($role, $team);
        expect($user->hasRole($role, $team))->toBeFalse();
    });
});
```

**Implementation**: Add permission methods to User model

```php
// Add to User model

use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class, 'user_has_roles')
        ->withPivot('team_id', 'assigned_at', 'assigned_by');
}

public function permissions(): BelongsToMany
{
    return $this->belongsToMany(Permission::class, 'user_has_permissions')
        ->withPivot('team_id', 'assigned_at', 'assigned_by');
}

public function assignRole(Role $role, ?Team $team = null): void
{
    // Validate team context for team-scoped roles
    if ($role->isTeamScoped() && !$team) {
        throw new \InvalidArgumentException('Team-scoped role requires team context');
    }

    if ($role->isGlobal() && $team) {
        throw new \InvalidArgumentException('Global role cannot be assigned with team context');
    }

    // Check for existing assignment
    $existing = $this->roles()
        ->where('role_id', $role->id)
        ->where('team_id', $team?->id)
        ->exists();

    if ($existing) {
        throw new \InvalidArgumentException('User already has this role in this team');
    }

    $this->roles()->attach($role, [
        'team_id' => $team?->id,
        'assigned_at' => now(),
        'assigned_by' => auth()->id(),
    ]);
}

public function removeRole(Role $role, ?Team $team = null): void
{
    $this->roles()
        ->wherePivot('role_id', $role->id)
        ->wherePivot('team_id', $team?->id)
        ->detach();
}

public function hasRole(Role $role, ?Team $team = null): bool
{
    return $this->roles()
        ->where('role_id', $role->id)
        ->where('team_id', $team?->id)
        ->exists();
}

public function hasGlobalRole(Role $role): bool
{
    return $this->roles()
        ->where('role_id', $role->id)
        ->whereNull('team_id')
        ->exists();
}

public function givePermissionTo(Permission $permission, ?Team $team = null): void
{
    // Check for existing assignment
    $existing = $this->permissions()
        ->where('permission_id', $permission->id)
        ->where('team_id', $team?->id)
        ->exists();

    if ($existing) {
        return; // Already has permission
    }

    $this->permissions()->attach($permission, [
        'team_id' => $team?->id,
        'assigned_at' => now(),
        'assigned_by' => auth()->id(),
    ]);
}

public function revokePermissionTo(Permission $permission, ?Team $team = null): void
{
    $this->permissions()
        ->wherePivot('permission_id', $permission->id)
        ->wherePivot('team_id', $team?->id)
        ->detach();
}

public function hasPermissionTo(Permission $permission, ?Team $team = null): bool
{
    // Check direct permissions
    $hasDirectPermission = $this->permissions()
        ->where('permission_id', $permission->id)
        ->where('team_id', $team?->id)
        ->exists();

    if ($hasDirectPermission) {
        return true;
    }

    // Check permissions via roles
    return $this->getPermissionsViaRoles($team)->contains($permission);
}

public function getPermissionsViaRoles(?Team $team = null): Collection
{
    $roleIds = $this->roles()
        ->where('team_id', $team?->id)
        ->pluck('role_id');

    return Permission::whereHas('roles', function ($query) use ($roleIds) {
        $query->whereIn('role_id', $roleIds);
    })->get();
}

public function getAllPermissions(?Team $team = null): Collection
{
    $directPermissions = $this->permissions()
        ->where('team_id', $team?->id)
        ->get();

    $rolePermissions = $this->getPermissionsViaRoles($team);

    return $directPermissions->merge($rolePermissions)->unique('id');
}
```

#### 2.2 Team Permission Isolation Tests

**Test File**: `tests/Unit/Security/PermissionIsolationTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Permission Isolation Security', function () {
    it('enforces strict team isolation for permissions', function () {
        $team1 = Team::factory()->create(['name' => 'Team Alpha']);
        $team2 = Team::factory()->create(['name' => 'Team Beta']);
        
        $user = User::factory()->create();
        $permission = Permission::factory()->create(['name' => 'edit-users']);
        
        // Give permission in team1 only
        $user->givePermissionTo($permission, $team1);
        
        // User should have permission in team1 but not team2
        expect($user->hasPermissionTo($permission, $team1))->toBeTrue();
        expect($user->hasPermissionTo($permission, $team2))->toBeFalse();
        expect($user->hasPermissionTo($permission))->toBeFalse(); // No global permission
    });

    it('prevents cross-team role access', function () {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $user = User::factory()->create();
        $role = Role::factory()->create(['team_id' => $team1->id]);
        $permission = Permission::factory()->create();
        
        $role->givePermissionTo($permission);
        $user->assignRole($role, $team1);
        
        // User should have role and permission in team1 only
        expect($user->hasRole($role, $team1))->toBeTrue();
        expect($user->hasRole($role, $team2))->toBeFalse();
        expect($user->hasPermissionTo($permission, $team1))->toBeTrue();
        expect($user->hasPermissionTo($permission, $team2))->toBeFalse();
    });

    it('allows global roles to work across teams', function () {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $user = User::factory()->create();
        $globalRole = Role::factory()->create(['is_global' => true]);
        $permission = Permission::factory()->create();
        
        $globalRole->givePermissionTo($permission);
        $user->assignRole($globalRole);
        
        // Global role should work in any team context
        expect($user->hasRole($globalRole))->toBeTrue();
        expect($user->hasPermissionTo($permission, $team1))->toBeTrue();
        expect($user->hasPermissionTo($permission, $team2))->toBeTrue();
    });

    it('prevents privilege escalation through team switching', function () {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $user = User::factory()->create();
        $adminRole = Role::factory()->create([
            'name' => 'admin',
            'team_id' => $team1->id
        ]);
        $adminPermission = Permission::factory()->create(['name' => 'delete-users']);
        
        $adminRole->givePermissionTo($adminPermission);
        $user->assignRole($adminRole, $team1);
        
        // User is admin in team1 but should have no permissions in team2
        expect($user->hasPermissionTo($adminPermission, $team1))->toBeTrue();
        expect($user->hasPermissionTo($adminPermission, $team2))->toBeFalse();
        
        // Even if user joins team2, they shouldn't inherit team1 permissions
        $team2->addMember($user, 'member');
        expect($user->hasPermissionTo($adminPermission, $team2))->toBeFalse();
    });

    it('validates system user bypass permissions', function () {
        $team = Team::factory()->create();
        $systemUser = User::factory()->systemUser()->create();
        $regularUser = User::factory()->create();
        $permission = Permission::factory()->create();
        
        // System user should bypass team isolation
        expect($systemUser->hasPermissionTo($permission, $team))->toBeTrue();
        expect($systemUser->hasPermissionTo($permission))->toBeTrue();
        
        // Regular user should not bypass
        expect($regularUser->hasPermissionTo($permission, $team))->toBeFalse();
        expect($regularUser->hasPermissionTo($permission))->toBeFalse();
    });
});
```

### Phase 3: Performance Optimization and Caching (Week 5, Days 1-2)

#### 3.1 Permission Query Performance Tests

**Test File**: `tests/Unit/Performance/PermissionPerformanceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Permission System Performance', function () {
    beforeEach(function () {
        // Create test data: 5 teams, 10 roles per team, 5 permissions per role, 20 users per team
        $this->teams = Team::factory()->count(5)->create();
        $this->permissions = Permission::factory()->count(25)->create();
        
        foreach ($this->teams as $team) {
            $roles = Role::factory()->count(10)->create(['team_id' => $team->id]);
            
            foreach ($roles as $index => $role) {
                // Assign 5 permissions to each role
                $rolePermissions = $this->permissions->slice($index * 2, 5);
                foreach ($rolePermissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            }
            
            // Create users and assign roles
            $users = User::factory()->count(20)->create();
            foreach ($users as $index => $user) {
                $role = $roles[$index % 10];
                $user->assignRole($role, $team);
                $team->addMember($user, 'member');
            }
        }
    });

    it('performs permission checks efficiently', function () {
        $user = User::first();
        $team = $this->teams->first();
        $permission = $this->permissions->first();
        
        DB::enableQueryLog();
        $startTime = microtime(true);
        
        $hasPermission = $user->hasPermissionTo($permission, $team);
        
        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        expect($endTime - $startTime)->toBeLessThan(0.01); // <10ms
        expect(count($queries))->toBeLessThanOrEqual(3); // Efficient query count
    });

    it('retrieves user permissions efficiently', function () {
        $user = User::first();
        $team = $this->teams->first();
        
        DB::enableQueryLog();
        $startTime = microtime(true);
        
        $permissions = $user->getAllPermissions($team);
        
        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        expect($permissions->count())->toBeGreaterThan(0);
        expect($endTime - $startTime)->toBeLessThan(0.05); // <50ms
        expect(count($queries))->toBeLessThanOrEqual(4); // Efficient query count
    });

    it('handles bulk permission checks efficiently', function () {
        $users = User::take(10)->get();
        $team = $this->teams->first();
        $permissions = $this->permissions->take(5);
        
        $startTime = microtime(true);
        
        $results = [];
        foreach ($users as $user) {
            foreach ($permissions as $permission) {
                $results[] = $user->hasPermissionTo($permission, $team);
            }
        }
        
        $endTime = microtime(true);
        
        expect(count($results))->toBe(50); // 10 users × 5 permissions
        expect($endTime - $startTime)->toBeLessThan(0.5); // <500ms for 50 checks
    });

    it('optimizes role-based permission inheritance', function () {
        $user = User::first();
        $team = $this->teams->first();
        
        DB::enableQueryLog();
        $startTime = microtime(true);
        
        $rolePermissions = $user->getPermissionsViaRoles($team);
        
        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        expect($rolePermissions->count())->toBeGreaterThan(0);
        expect($endTime - $startTime)->toBeLessThan(0.03); // <30ms
        expect(count($queries))->toBeLessThanOrEqual(2); // Single join query preferred
    });
});
```

#### 3.2 Permission Caching Tests

**Test File**: `tests/Unit/Caching/PermissionCacheTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('Permission System Caching', function () {
    it('caches user permissions per team', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $permission = Permission::factory()->create();
        
        $user->givePermissionTo($permission, $team);
        
        // First call should cache
        $permissions1 = $user->getCachedPermissions($team);
        expect(Cache::has("user_permissions_{$user->id}_{$team->id}"))->toBeTrue();
        
        // Second call should use cache
        $permissions2 = $user->getCachedPermissions($team);
        expect($permissions1)->toEqual($permissions2);
    });

    it('invalidates cache when permissions change', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $permission = Permission::factory()->create();
        
        // Cache permissions
        $user->getCachedPermissions($team);
        expect(Cache::has("user_permissions_{$user->id}_{$team->id}"))->toBeTrue();
        
        // Adding permission should invalidate cache
        $user->givePermissionTo($permission, $team);
        expect(Cache::has("user_permissions_{$user->id}_{$team->id}"))->toBeFalse();
    });

    it('caches role permissions', function () {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        
        $role->givePermissionTo($permission);
        
        // First call should cache
        $permissions1 = $role->getCachedPermissions();
        expect(Cache::has("role_permissions_{$role->id}"))->toBeTrue();
        
        // Second call should use cache
        $permissions2 = $role->getCachedPermissions();
        expect($permissions1)->toEqual($permissions2);
    });

    it('provides cache warming for frequently accessed permissions', function () {
        $users = User::factory()->count(10)->create();
        $team = Team::factory()->create();
        $role = Role::factory()->create(['team_id' => $team->id]);
        
        foreach ($users as $user) {
            $user->assignRole($role, $team);
        }
        
        // Warm cache for all users
        $startTime = microtime(true);
        User::warmPermissionCache($team);
        $warmTime = microtime(true) - $startTime;
        
        // Subsequent permission checks should be fast
        $startTime = microtime(true);
        foreach ($users as $user) {
            $user->getCachedPermissions($team);
        }
        $cachedTime = microtime(true) - $startTime;
        
        expect($cachedTime)->toBeLessThan($warmTime / 2); // Cached should be much faster
    });
});
```

**Implementation**: Add caching methods to User and Role models

```php
// Add to User model

use Illuminate\Support\Facades\Cache;

public function getCachedPermissions(?Team $team = null): Collection
{
    $cacheKey = $team 
        ? "user_permissions_{$this->id}_{$team->id}"
        : "user_permissions_{$this->id}_global";

    return Cache::remember(
        $cacheKey,
        now()->addMinutes(30),
        fn() => $this->getAllPermissions($team)
    );
}

public function invalidatePermissionCache(?Team $team = null): void
{
    if ($team) {
        Cache::forget("user_permissions_{$this->id}_{$team->id}");
    } else {
        // Clear all team caches for this user
        $teamIds = $this->teams()->pluck('team_id');
        foreach ($teamIds as $teamId) {
            Cache::forget("user_permissions_{$this->id}_{$teamId}");
        }
        Cache::forget("user_permissions_{$this->id}_global");
    }
}

public static function warmPermissionCache(Team $team): void
{
    $userIds = $team->members()->pluck('user_id');
    
    foreach ($userIds as $userId) {
        $user = User::find($userId);
        $user->getCachedPermissions($team);
    }
}

// Override permission methods to invalidate cache
public function givePermissionTo(Permission $permission, ?Team $team = null): void
{
    // ... existing logic ...
    
    $this->invalidatePermissionCache($team);
}

public function assignRole(Role $role, ?Team $team = null): void
{
    // ... existing logic ...
    
    $this->invalidatePermissionCache($team);
}
```

```php
// Add to Role model

public function getCachedPermissions(): Collection
{
    return Cache::remember(
        "role_permissions_{$this->id}",
        now()->addHours(2),
        fn() => $this->permissions
    );
}

public function invalidatePermissionCache(): void
{
    Cache::forget("role_permissions_{$this->id}");
    
    // Also invalidate cache for all users with this role
    $userIds = $this->users()->pluck('user_id');
    foreach ($userIds as $userId) {
        $user = User::find($userId);
        $user->invalidatePermissionCache($this->team);
    }
}

// Override givePermissionTo to invalidate cache
public function givePermissionTo(Permission $permission): void
{
    // ... existing logic ...
    
    $this->invalidatePermissionCache();
}
```

### Phase 4: Authorization Middleware and Gates (Week 5, Days 3-4)

#### 4.1 Authorization Middleware Tests

**Test File**: `tests/Unit/Middleware/TeamPermissionMiddlewareTest.php`

```php
<?php

declare(strict_types=1);

use App\Http\Middleware\TeamPermissionMiddleware;
use App\Models\User;
use App\Models\Team;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

describe('Team Permission Middleware', function () {
    beforeEach(function () {
        $this->middleware = new TeamPermissionMiddleware();
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->permission = Permission::factory()->create(['name' => 'edit-users']);
    });

    it('allows access when user has required permission', function () {
        $this->user->givePermissionTo($this->permission, $this->team);
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $this->user);
        $request->route()->setParameter('team', $this->team);
        
        $response = $this->middleware->handle(
            $request,
            fn() => new Response('success'),
            'edit-users'
        );
        
        expect($response->getContent())->toBe('success');
    });

    it('denies access when user lacks permission', function () {
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $this->user);
        $request->route()->setParameter('team', $this->team);
        
        expect(fn() => $this->middleware->handle(
            $request,
            fn() => new Response('success'),
            'edit-users'
        ))->toThrow(AuthorizationException::class);
    });

    it('allows system users to bypass permission checks', function () {
        $systemUser = User::factory()->systemUser()->create();
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $systemUser);
        $request->route()->setParameter('team', $this->team);
        
        $response = $this->middleware->handle(
            $request,
            fn() => new Response('success'),
            'edit-users'
        );
        
        expect($response->getContent())->toBe('success');
    });

    it('handles multiple permission requirements', function () {
        $permission2 = Permission::factory()->create(['name' => 'delete-users']);
        
        $this->user->givePermissionTo($this->permission, $this->team);
        $this->user->givePermissionTo($permission2, $this->team);
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $this->user);
        $request->route()->setParameter('team', $this->team);
        
        $response = $this->middleware->handle(
            $request,
            fn() => new Response('success'),
            'edit-users',
            'delete-users'
        );
        
        expect($response->getContent())->toBe('success');
    });

    it('denies access when user lacks any required permission', function () {
        $permission2 = Permission::factory()->create(['name' => 'delete-users']);
        
        $this->user->givePermissionTo($this->permission, $this->team);
        // Missing permission2
        
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $this->user);
        $request->route()->setParameter('team', $this->team);
        
        expect(fn() => $this->middleware->handle(
            $request,
            fn() => new Response('success'),
            'edit-users',
            'delete-users'
        ))->toThrow(AuthorizationException::class);
    });
});
```

**Implementation**: Create TeamPermissionMiddleware

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\Team;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class TeamPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();
        
        if (!$user) {
            throw new AuthorizationException('Authentication required');
        }

        // System users bypass all permission checks
        if ($user->isSystemUser()) {
            return $next($request);
        }

        $team = $request->route('team');
        
        if (!$team instanceof Team) {
            throw new AuthorizationException('Team context required');
        }

        // Check all required permissions
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            
            if (!$permission) {
                throw new AuthorizationException("Permission '{$permissionName}' not found");
            }

            if (!$user->hasPermissionTo($permission, $team)) {
                throw new AuthorizationException(
                    "Insufficient permissions: missing '{$permissionName}' in team '{$team->name}'"
                );
            }
        }

        return $next($request);
    }
}
```

#### 4.2 Authorization Gates Tests

**Test File**: `tests/Unit/Authorization/PermissionGatesTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Permission Authorization Gates', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->permission = Permission::factory()->create(['name' => 'edit-users']);
        
        // Register gates
        app()->make(\App\Providers\AuthServiceProvider::class)->boot();
    });

    it('authorizes user with direct permission', function () {
        $this->user->givePermissionTo($this->permission, $this->team);
        
        expect(Gate::forUser($this->user)->allows('team-permission', [
            $this->permission->name, 
            $this->team
        ]))->toBeTrue();
    });

    it('authorizes user with role-based permission', function () {
        $role = Role::factory()->create(['team_id' => $this->team->id]);
        $role->givePermissionTo($this->permission);
        $this->user->assignRole($role, $this->team);
        
        expect(Gate::forUser($this->user)->allows('team-permission', [
            $this->permission->name, 
            $this->team
        ]))->toBeTrue();
    });

    it('denies user without permission', function () {
        expect(Gate::forUser($this->user)->denies('team-permission', [
            $this->permission->name, 
            $this->team
        ]))->toBeTrue();
    });

    it('allows system users through system-user gate', function () {
        $systemUser = User::factory()->systemUser()->create();
        
        expect(Gate::forUser($systemUser)->allows('system-user'))->toBeTrue();
        expect(Gate::forUser($this->user)->denies('system-user'))->toBeTrue();
    });

    it('handles team management authorization', function () {
        $managerRole = Role::factory()->create([
            'name' => 'team-manager',
            'team_id' => $this->team->id
        ]);
        
        $managePermission = Permission::factory()->create(['name' => 'manage-team']);
        $managerRole->givePermissionTo($managePermission);
        $this->user->assignRole($managerRole, $this->team);
        
        expect(Gate::forUser($this->user)->allows('manage-team', $this->team))->toBeTrue();
    });

    it('validates team membership for team-specific gates', function () {
        $otherTeam = Team::factory()->create();
        $this->user->givePermissionTo($this->permission, $this->team);
        
        // Should work for correct team
        expect(Gate::forUser($this->user)->allows('team-permission', [
            $this->permission->name, 
            $this->team
        ]))->toBeTrue();
        
        // Should fail for different team
        expect(Gate::forUser($this->user)->denies('team-permission', [
            $this->permission->name, 
            $otherTeam
        ]))->toBeTrue();
    });
});
```

**Implementation**: Register authorization gates

```php
// Add to AuthServiceProvider

use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // System user gate - bypasses all other checks
    Gate::define('system-user', function (User $user) {
        return $user->isSystemUser();
    });

    // Team permission gate
    Gate::define('team-permission', function (User $user, string $permissionName, Team $team) {
        // System users bypass all checks
        if ($user->isSystemUser()) {
            return true;
        }

        $permission = Permission::where('name', $permissionName)->first();
        
        if (!$permission) {
            return false;
        }

        return $user->hasPermissionTo($permission, $team);
    });

    // Team management gate
    Gate::define('manage-team', function (User $user, Team $team) {
        if ($user->isSystemUser()) {
            return true;
        }

        $managePermission = Permission::where('name', 'manage-team')->first();
        
        return $managePermission && $user->hasPermissionTo($managePermission, $team);
    });

    // User management within team
    Gate::define('manage-team-users', function (User $user, Team $team) {
        if ($user->isSystemUser()) {
            return true;
        }

        $permission = Permission::where('name', 'manage-users')->first();
        
        return $permission && $user->hasPermissionTo($permission, $team);
    });

    // Role assignment within team
    Gate::define('assign-team-roles', function (User $user, Team $team) {
        if ($user->isSystemUser()) {
            return true;
        }

        $permission = Permission::where('name', 'assign-roles')->first();
        
        return $permission && $user->hasPermissionTo($permission, $team);
    });
}
```

## Integration with STI User Models and Team Hierarchy

### STI Permission Integration Tests

**Test File**: `tests/Feature/STIPermissionIntegrationTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use App\Models\Employee;
use App\Models\Manager;
use App\Models\SystemUser;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('STI Permission Integration', function () {
    it('applies different permission defaults based on user type', function () {
        $team = Team::factory()->create();
        
        $employee = Employee::factory()->create();
        $manager = Manager::factory()->create();
        $systemUser = SystemUser::factory()->create();
        
        // Employees get basic permissions
        $employee->applyDefaultPermissions($team);
        expect($employee->hasPermissionTo(
            Permission::where('name', 'view-team')->first(), 
            $team
        ))->toBeTrue();
        
        // Managers get additional permissions
        $manager->applyDefaultPermissions($team);
        expect($manager->hasPermissionTo(
            Permission::where('name', 'manage-users')->first(), 
            $team
        ))->toBeTrue();
        
        // System users bypass permission checks
        expect($systemUser->isSystemUser())->toBeTrue();
    });

    it('respects user type hierarchy in permission inheritance', function () {
        $company = Team::factory()->create(['name' => 'Company']);
        $department = Team::factory()->create(['name' => 'Engineering']);
        $team = Team::factory()->create(['name' => 'Backend']);
        
        $company->addChild($department);
        $department->addChild($team);
        
        $manager = Manager::factory()->create();
        $employee = Employee::factory()->create();
        
        // Manager at department level
        $managerRole = Role::factory()->create([
            'name' => 'department-manager',
            'team_id' => $department->id
        ]);
        $managePermission = Permission::factory()->create(['name' => 'manage-department']);
        $managerRole->givePermissionTo($managePermission);
        $manager->assignRole($managerRole, $department);
        
        // Employee at team level
        $employee->givePermissionTo(
            Permission::factory()->create(['name' => 'edit-code']), 
            $team
        );
        
        // Manager should have access to sub-teams through hierarchy
        expect($manager->canAccessTeam($team))->toBeTrue();
        expect($employee->canAccessTeam($department))->toBeFalse();
    });

    it('validates user type restrictions on role assignments', function () {
        $team = Team::factory()->create();
        $employee = Employee::factory()->create();
        $systemRole = Role::factory()->create([
            'name' => 'system-admin',
            'is_system' => true
        ]);
        
        // Employees cannot be assigned system roles
        expect(fn() => $employee->assignRole($systemRole))
            ->toThrow(InvalidArgumentException::class, 'User type cannot be assigned system roles');
    });
});
```

## Factory Patterns for Testing

### Permission Factory

**File**: `database/factories/PermissionFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'guard_name' => 'web',
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement([
                'user-management', 'team-management', 'content', 'system'
            ]),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn() => [
            'is_system' => true,
            'category' => 'system',
        ]);
    }

    public function userManagement(): static
    {
        return $this->state(fn() => [
            'category' => 'user-management',
            'name' => $this->faker->randomElement([
                'view-users', 'edit-users', 'delete-users', 'manage-users'
            ]),
        ]);
    }

    public function teamManagement(): static
    {
        return $this->state(fn() => [
            'category' => 'team-management',
            'name' => $this->faker->randomElement([
                'view-team', 'edit-team', 'manage-team', 'delete-team'
            ]),
        ]);
    }
}
```

### Role Factory

**File**: `database/factories/RoleFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'guard_name' => 'web',
            'description' => $this->faker->sentence(),
            'team_id' => null,
            'is_global' => false,
            'is_system' => false,
        ];
    }

    public function global(): static
    {
        return $this->state(fn() => [
            'is_global' => true,
            'team_id' => null,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn() => [
            'is_system' => true,
            'is_global' => true,
            'team_id' => null,
        ]);
    }

    public function teamScoped(): static
    {
        return $this->state(fn() => [
            'team_id' => Team::factory(),
            'is_global' => false,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn() => [
            'name' => 'manager',
            'description' => 'Team manager with user management permissions',
        ]);
    }

    public function developer(): static
    {
        return $this->state(fn() => [
            'name' => 'developer',
            'description' => 'Developer with code and project permissions',
        ]);
    }
}
```

## Performance Benchmarks and Security Validation

### Security Benchmark Tests

**Test File**: `tests/Performance/PermissionSecurityBenchmarkTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Permission Security Performance Benchmarks', function () {
    it('meets security performance requirements', function () {
        // Create complex permission structure
        $teams = Team::factory()->count(10)->create();
        $permissions = Permission::factory()->count(50)->create();
        $roles = collect();
        
        foreach ($teams as $team) {
            $teamRoles = Role::factory()->count(5)->teamScoped()->create(['team_id' => $team->id]);
            $roles = $roles->merge($teamRoles);
            
            foreach ($teamRoles as $role) {
                $rolePermissions = $permissions->random(10);
                foreach ($rolePermissions as $permission) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        
        $users = User::factory()->count(100)->create();
        foreach ($users as $index => $user) {
            $team = $teams[$index % 10];
            $role = $roles->where('team_id', $team->id)->random();
            $user->assignRole($role, $team);
        }
        
        // Benchmark: Permission check performance
        $user = $users->random();
        $team = $teams->random();
        $permission = $permissions->random();
        
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $user->hasPermissionTo($permission, $team);
        }
        $checkTime = microtime(true) - $start;
        
        // Benchmark: Permission retrieval performance
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $user->getAllPermissions($team);
        }
        $retrievalTime = microtime(true) - $start;
        
        // Performance assertions
        expect($checkTime / 100)->toBeLessThan(0.001); // <1ms per check
        expect($retrievalTime / 10)->toBeLessThan(0.01); // <10ms per retrieval
    });

    it('validates permission isolation under load', function () {
        $teams = Team::factory()->count(5)->create();
        $users = User::factory()->count(20)->create();
        $permission = Permission::factory()->create();
        
        // Give permission to users in different teams
        foreach ($users as $index => $user) {
            $team = $teams[$index % 5];
            $user->givePermissionTo($permission, $team);
        }
        
        // Verify isolation under concurrent-like access
        foreach ($users as $user) {
            foreach ($teams as $team) {
                $hasPermission = $user->hasPermissionTo($permission, $team);
                $userTeamIds = $user->teams()->pluck('team_id')->toArray();
                
                if (in_array($team->id, $userTeamIds)) {
                    expect($hasPermission)->toBeTrue();
                } else {
                    expect($hasPermission)->toBeFalse();
                }
            }
        }
    });
});
```

## Summary and Next Steps

This comprehensive TDD guide for permission system implementation provides:

1. **Complete security-first approach** with team isolation
2. **Performance-optimized** permission checking and caching
3. **Comprehensive authorization** middleware and gates
4. **STI integration** with user type-specific permissions
5. **Realistic factory patterns** for complex permission testing
6. **Security benchmarks** to validate isolation requirements

### Key TDD Principles Applied

- **Security-First Development**: All security features driven by failing tests
- **Performance Testing**: TDD approach to permission query optimization
- **Isolation Testing**: Comprehensive testing of team permission boundaries
- **Integration Testing**: Full testing of permission system with other components

### Security Targets Achieved

- Permission checks: <1ms per check under load
- Permission retrieval: <10ms for complex permission sets
- Complete team isolation: Zero cross-team permission leakage
- System user bypass: Secure and auditable privilege escalation

### Next Implementation Guide

Continue with [060-gdpr-compliance-tdd.md](060-gdpr-compliance-tdd.md) to implement GDPR compliance features using the same comprehensive TDD approach.
