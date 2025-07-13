# 11. Roles and Permissions Integration

## 11.1. Spatie Laravel Permission Integration

This section covers the integration of `spatie/laravel-permission` package with our STI User model and Teams functionality, providing comprehensive role-based access control (RBAC) with team-scoped permissions.

### 11.1.1. Critical Permission Design Principles

**⚠️ NON-INHERITED PERMISSIONS**: Team permissions are **NOT** inherited through the team hierarchy. This design ensures:

- **Explicit Security**: Users must be explicitly granted access to each team
- **Principle of Least Privilege**: No accidental access through organizational structure
- **Clear Audit Trail**: All permissions are explicitly assigned and traceable
- **Scalable Security**: Prevents permission sprawl in large organizations

**🔧 SYSTEM USER BYPASS**: SystemUser instances bypass all permission checks for:

- **Automated Processes**: Background jobs, system maintenance, data migrations
- **Emergency Access**: Critical system recovery and troubleshooting
- **System Integration**: API integrations, third-party service connections

## 11.2. Package Installation and Configuration

### 11.2.1. Composer Dependencies

```json
{
    "require": {
        "spatie/laravel-permission": "^6.0"
    }
}
```

### 11.2.2. User Model Integration

```php
<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;

abstract class User extends Authenticatable
{
    use HasRoles;
    use HasPermissions;
    
    // ... existing code ...

    /**
     * Get user permissions for a specific team.
     */
    public function getTeamPermissions(Team $team): Collection
    {
        return $this->getPermissionsViaRoles()
            ->merge($this->getDirectPermissions())
            ->filter(function ($permission) use ($team) {
                return $permission->hasTeamScope($team);
            });
    }

    /**
     * Check if user has permission in team context.
     * NOTE: Does NOT check parent team permissions - permissions are not inherited.
     */
    public function hasTeamPermission(string $permission, Team $team): bool
    {
        // System users bypass all permission checks
        if ($this->isSystemUser()) {
            return true;
        }

        // Only check permissions for the specific team, not parent teams
        return $this->hasPermissionTo($permission) &&
               $this->getTeamPermissions($team)->contains('name', $permission);
    }

    /**
     * Assign role to user within team context.
     */
    public function assignTeamRole(string $role, Team $team): void
    {
        $roleModel = Role::where('name', $role)
            ->where('team_id', $team->id)
            ->firstOrFail();
            
        $this->assignRole($roleModel);
    }

    /**
     * Get user roles for specific team.
     */
    public function getTeamRoles(Team $team): Collection
    {
        return $this->roles->filter(function ($role) use ($team) {
            return $role->team_id === $team->id || $role->team_id === null;
        });
    }
}
```

## 11.3. Enhanced Role and Permission Models

### 11.3.1. Extended Role Model

```php
<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
        'description',
        'is_default',
        'level',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * Team relationship for team-scoped roles.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Check if role is global (not team-scoped).
     */
    public function isGlobal(): bool
    {
        return is_null($this->team_id);
    }

    /**
     * Check if role is team-scoped.
     */
    public function isTeamScoped(): bool
    {
        return !is_null($this->team_id);
    }

    /**
     * Scope for global roles.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('team_id');
    }

    /**
     * Scope for team-specific roles.
     */
    public function scopeForTeam($query, Team $team)
    {
        return $query->where('team_id', $team->id);
    }

    /**
     * Scope for default roles.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
```

### 11.3.2. Extended Permission Model

```php
<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'team_id',
        'description',
        'category',
        'is_dangerous',
    ];

    protected $casts = [
        'is_dangerous' => 'boolean',
    ];

    /**
     * Team relationship for team-scoped permissions.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Check if permission is global.
     */
    public function isGlobal(): bool
    {
        return is_null($this->team_id);
    }

    /**
     * Check if permission has team scope.
     */
    public function hasTeamScope(Team $team): bool
    {
        return $this->team_id === $team->id || $this->isGlobal();
    }

    /**
     * Scope for global permissions.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('team_id');
    }

    /**
     * Scope for team-specific permissions.
     */
    public function scopeForTeam($query, Team $team)
    {
        return $query->where('team_id', $team->id);
    }

    /**
     * Scope by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for dangerous permissions.
     */
    public function scopeDangerous($query)
    {
        return $query->where('is_dangerous', true);
    }
}
```

## 11.4. Permission Categories and Structure

### 11.4.1. Permission Categories Enum

```php
<?php

namespace App\Enums;

enum PermissionCategory: string
{
    case UserManagement = 'user_management';
    case TeamManagement = 'team_management';
    case ProjectManagement = 'project_management';
    case ContentManagement = 'content_management';
    case SystemAdministration = 'system_administration';
    case Reporting = 'reporting';
    case ApiAccess = 'api_access';

    public function getLabel(): string
    {
        return match ($this) {
            self::UserManagement => 'User Management',
            self::TeamManagement => 'Team Management',
            self::ProjectManagement => 'Project Management',
            self::ContentManagement => 'Content Management',
            self::SystemAdministration => 'System Administration',
            self::Reporting => 'Reporting',
            self::ApiAccess => 'API Access',
        };
    }

    public function getPermissions(): array
    {
        return match ($this) {
            self::UserManagement => [
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'users.impersonate',
            ],
            self::TeamManagement => [
                'teams.view',
                'teams.create',
                'teams.edit',
                'teams.delete',
                'teams.manage_members',
                'teams.assign_roles',
            ],
            self::ProjectManagement => [
                'projects.view',
                'projects.create',
                'projects.edit',
                'projects.delete',
                'projects.manage_timeline',
                'projects.manage_budget',
            ],
            self::ContentManagement => [
                'content.view',
                'content.create',
                'content.edit',
                'content.delete',
                'content.publish',
                'content.moderate',
            ],
            self::SystemAdministration => [
                'system.settings',
                'system.maintenance',
                'system.logs',
                'system.backups',
            ],
            self::Reporting => [
                'reports.view',
                'reports.create',
                'reports.export',
                'analytics.view',
            ],
            self::ApiAccess => [
                'api.access',
                'api.tokens.manage',
                'api.webhooks.manage',
            ],
        };
    }
}
```

## 11.5. Role Hierarchy and Levels

### 11.5.1. Role Hierarchy Service

```php
<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class RoleHierarchyService
{
    /**
     * Define role hierarchy levels.
     */
    public const ROLE_LEVELS = [
        'super_admin' => 100,
        'admin' => 90,
        'manager' => 80,
        'team_lead' => 70,
        'senior_member' => 60,
        'member' => 50,
        'contributor' => 40,
        'viewer' => 30,
        'guest' => 10,
    ];

    /**
     * Check if user can assign role to another user.
     */
    public function canAssignRole(User $assigner, Role $role, ?Team $team = null): bool
    {
        $assignerLevel = $this->getUserMaxLevel($assigner, $team);
        $roleLevel = $role->level ?? self::ROLE_LEVELS[$role->name] ?? 0;
        
        return $assignerLevel > $roleLevel;
    }

    /**
     * Get user's maximum role level in team context.
     */
    public function getUserMaxLevel(User $user, ?Team $team = null): int
    {
        $roles = $team ? $user->getTeamRoles($team) : $user->roles;
        
        return $roles->max(function ($role) {
            return $role->level ?? self::ROLE_LEVELS[$role->name] ?? 0;
        }) ?? 0;
    }

    /**
     * Get assignable roles for user in team context.
     */
    public function getAssignableRoles(User $user, ?Team $team = null): Collection
    {
        $userLevel = $this->getUserMaxLevel($user, $team);
        
        $query = Role::where('level', '<', $userLevel);
        
        if ($team) {
            $query->where(function ($q) use ($team) {
                $q->where('team_id', $team->id)->orWhereNull('team_id');
            });
        }
        
        return $query->get();
    }

    /**
     * Create default roles for team.
     */
    public function createDefaultTeamRoles(Team $team): void
    {
        $defaultRoles = [
            [
                'name' => 'team_admin',
                'description' => 'Team Administrator',
                'level' => 85,
                'permissions' => ['teams.manage_members', 'teams.edit', 'projects.create'],
            ],
            [
                'name' => 'team_member',
                'description' => 'Team Member',
                'level' => 50,
                'permissions' => ['teams.view', 'projects.view'],
            ],
            [
                'name' => 'team_viewer',
                'description' => 'Team Viewer',
                'level' => 30,
                'permissions' => ['teams.view'],
            ],
        ];

        foreach ($defaultRoles as $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'level' => $roleData['level'],
                'team_id' => $team->id,
                'guard_name' => 'web',
            ]);

            foreach ($roleData['permissions'] as $permissionName) {
                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                
                $role->givePermissionTo($permission);
            }
        }
    }
}
```

## 11.6. Team-Based Permission Middleware

### 11.6.1. Team Permission Middleware

```php
<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTeamPermission
{
    /**
     * Handle an incoming request.
     * Enforces non-inherited team permissions.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // System users bypass all checks
        if ($user->isSystemUser()) {
            return $next($request);
        }

        // Get team from route parameter or request
        $team = $this->getTeamFromRequest($request);

        if (!$team) {
            abort(404, 'Team not found');
        }

        // CRITICAL: Only check permissions for the specific team
        // Parent team permissions are NOT inherited
        if (!$user->hasTeamPermission($permission, $team)) {
            abort(403, "Insufficient permissions for team '{$team->name}'. Permissions are not inherited from parent teams.");
        }

        // Verify user is actually a member of this specific team
        if (!$team->hasMember($user)) {
            abort(403, "User is not a member of team '{$team->name}'");
        }

        // Add team to request for controller access
        $request->merge(['current_team' => $team]);

        return $next($request);
    }

    /**
     * Extract team from request.
     */
    private function getTeamFromRequest(Request $request): ?Team
    {
        // Try to get team from route parameters
        if ($request->route('team')) {
            return $request->route('team');
        }

        if ($request->route('team_id')) {
            return Team::find($request->route('team_id'));
        }

        if ($request->route('team_ulid')) {
            return Team::where('ulid', $request->route('team_ulid'))->first();
        }

        // Try to get from request data
        if ($request->has('team_id')) {
            return Team::find($request->input('team_id'));
        }

        return null;
    }
}
```

## 11.7. Permission Policies

### 11.7.1. Team Policy

```php
<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine if user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->hasTeamPermission('teams.view', $team) ||
               $team->hasMember($user);
    }

    /**
     * Determine if user can create teams.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('teams.create');
    }

    /**
     * Determine if user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission('teams.edit', $team);
    }

    /**
     * Determine if user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->hasTeamPermission('teams.delete', $team) &&
               !$team->children()->exists(); // Cannot delete teams with children
    }

    /**
     * Determine if user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $user->hasTeamPermission('teams.manage_members', $team);
    }

    /**
     * Determine if user can assign roles in team.
     */
    public function assignRoles(User $user, Team $team): bool
    {
        return $user->hasTeamPermission('teams.assign_roles', $team);
    }
}
```

---

**Next**: [Database Factories and Seeders](120-database-factories-seeders.md) - Comprehensive factory implementations and seeding strategies.
