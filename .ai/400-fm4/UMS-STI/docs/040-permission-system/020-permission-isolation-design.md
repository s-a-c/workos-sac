# Permission Isolation Design and Implementation

## Executive Summary
Permission isolation is a security-first design principle where team permissions are explicitly assigned and never inherited through organizational hierarchy. This approach prevents accidental privilege escalation and ensures that access to child teams must be explicitly granted, even if a user has administrative access to parent teams.

## Learning Objectives
After completing this guide, you will:
- Understand the security rationale behind permission isolation
- Implement explicit permission assignment without inheritance
- Design team-scoped permission systems using Spatie Laravel Permission
- Create validation mechanisms to ensure isolation is maintained
- Build audit trails for permission changes and access attempts

## Prerequisite Knowledge
- Laravel authorization concepts (gates, policies, middleware)
- Spatie Laravel Permission package basics
- Understanding of role-based access control (RBAC)
- Team hierarchy concepts from previous tasks
- Database relationship design

## Architectural Overview

### Permission Isolation Principle

Based on **DECISION-003** from our decision log, we implement **explicit-only** permission assignment:

```
❌ Traditional Inherited Permissions:
Organization: Acme Corp (User has Admin role)
├── Engineering Dept (User inherits Admin access)
    ├── Backend Project (User inherits Admin access)
        └── API Squad (User inherits Admin access)

✅ UMS-STI Isolated Permissions:
Organization: Acme Corp (User has Admin role)
├── Engineering Dept (User has NO access - must be explicitly granted)
    ├── Backend Project (User has NO access - must be explicitly granted)
        └── API Squad (User has NO access - must be explicitly granted)
```

### Security Benefits

1. **Zero Trust Model**: No implicit access through hierarchy
2. **Principle of Least Privilege**: Users only get explicitly granted access
3. **Audit Clarity**: Every permission is explicitly tracked
4. **Reduced Attack Surface**: Compromised parent access doesn't cascade
5. **Compliance Ready**: Clear permission trails for regulatory requirements

## Core Concepts Deep Dive

### 1. Team-Scoped Permissions

Each team maintains its own isolated permission scope:

```php
// User permissions are scoped to specific teams
$user->assignRole('admin', $teamA);  // Admin only in Team A
$user->assignRole('member', $teamB); // Member only in Team B

// User has NO access to Team C (even if admin in Team A)
$user->hasRole('admin', $teamC); // Returns false
```

### 2. Explicit Access Validation

Every access check validates explicit team membership:

```php
public function userHasAccess(User $user, Team $team): bool
{
    // SystemUser bypass (only exception)
    if ($user->isSystemUser()) {
        return true;
    }

    // Explicit team membership required
    return $team->members()->where('user_id', $user->id)->exists();
}
```

### 3. Permission Assignment Patterns

```php
// ✅ Correct: Explicit assignment
$user->assignRole('admin', $engineeringDept);
$user->assignRole('member', $backendProject);

// ❌ Incorrect: Assuming inheritance
// User with admin in engineering does NOT automatically get access to backend project
```

## Implementation Principles & Patterns

### 1. Guard-Based Isolation
Each team acts as a separate permission guard:

```php
// Team-specific permission checks
Gate::define('manage-team-users', function (User $user, Team $team) {
    return $user->hasRole(['admin', 'executive'], $team);
});
```

### 2. Middleware Protection
Custom middleware ensures team context is validated:

```php
class TeamAccessMiddleware
{
    public function handle($request, Closure $next, $permission = null)
    {
        $team = $request->route('team');
        $user = $request->user();
        
        if (!$this->userHasTeamAccess($user, $team, $permission)) {
            abort(403, 'Access denied to this team');
        }
        
        return $next($request);
    }
}
```

### 3. Audit Trail Integration
Every permission check and assignment is logged:

```php
// Log access attempts
activity()
    ->performedOn($team)
    ->causedBy($user)
    ->withProperties(['permission' => $permission, 'granted' => $hasAccess])
    ->log($hasAccess ? 'team_access_granted' : 'team_access_denied');
```

## Step-by-Step Implementation Guide

### Step 1: Configure Team-Scoped Permissions

Update `config/permission.php`:

```php
<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id', // Enable team-scoped permissions
    ],

    // Enable team-based permissions
    'teams' => true,
    
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
```

### Step 2: Create Team Permission Policy

Create `app/Policies/TeamPermissionPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPermissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        // SystemUser bypass
        if ($user->isSystemUser()) {
            return true;
        }

        // Explicit team membership required
        return $this->hasExplicitAccess($user, $team);
    }

    /**
     * Determine if user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        if ($user->isSystemUser()) {
            return true;
        }

        return $this->hasExplicitAccess($user, $team) && 
               $user->hasAnyRole(['admin', 'executive'], $team);
    }

    /**
     * Determine if user can manage team settings.
     */
    public function manageSettings(User $user, Team $team): bool
    {
        if ($user->isSystemUser()) {
            return true;
        }

        return $this->hasExplicitAccess($user, $team) && 
               $user->hasRole('executive', $team);
    }

    /**
     * Determine if user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        if ($user->isSystemUser()) {
            return true;
        }

        return $this->hasExplicitAccess($user, $team) && 
               $user->hasRole('executive', $team);
    }

    /**
     * Check explicit team access (no inheritance).
     */
    private function hasExplicitAccess(User $user, Team $team): bool
    {
        return $team->members()->where('user_id', $user->id)->exists();
    }
}
```

### Step 3: Create Permission Isolation Service

Create `app/Services/PermissionIsolationService.php`:

```php
<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\LogActivity;

class PermissionIsolationService
{
    /**
     * Assign role to user for specific team only.
     */
    public function assignTeamRole(User $user, Team $team, string $role): bool
    {
        DB::transaction(function () use ($user, $team, $role) {
            // Ensure user is team member first
            if (!$team->members()->where('user_id', $user->id)->exists()) {
                $team->addMember($user);
            }

            // Assign role scoped to this team
            $user->assignRole($role, $team);

            // Log the assignment
            activity()
                ->performedOn($team)
                ->causedBy(auth()->user())
                ->withProperties([
                    'target_user_id' => $user->id,
                    'role' => $role,
                    'team_id' => $team->id,
                ])
                ->log('team_role_assigned');
        });

        return true;
    }

    /**
     * Remove role from user for specific team.
     */
    public function removeTeamRole(User $user, Team $team, string $role): bool
    {
        DB::transaction(function () use ($user, $team, $role) {
            // Remove role scoped to this team
            $user->removeRole($role, $team);

            // Log the removal
            activity()
                ->performedOn($team)
                ->causedBy(auth()->user())
                ->withProperties([
                    'target_user_id' => $user->id,
                    'role' => $role,
                    'team_id' => $team->id,
                ])
                ->log('team_role_removed');
        });

        return true;
    }

    /**
     * Validate that user has explicit access to team.
     */
    public function validateExplicitAccess(User $user, Team $team): bool
    {
        // SystemUser bypass
        if ($user->isSystemUser()) {
            $this->logSystemUserBypass($user, $team);
            return true;
        }

        // Check explicit membership
        $hasAccess = $team->members()->where('user_id', $user->id)->exists();

        // Log access attempt
        activity()
            ->performedOn($team)
            ->causedBy($user)
            ->withProperties([
                'access_granted' => $hasAccess,
                'check_type' => 'explicit_access_validation',
            ])
            ->log($hasAccess ? 'team_access_granted' : 'team_access_denied');

        return $hasAccess;
    }

    /**
     * Check if user would have access through inheritance (should always be false).
     */
    public function auditInheritanceViolation(User $user, Team $team): array
    {
        $violations = [];

        // Check if user has access to any parent teams
        $ancestors = $team->ancestors();
        
        foreach ($ancestors as $ancestor) {
            if ($ancestor->members()->where('user_id', $user->id)->exists()) {
                $violations[] = [
                    'parent_team_id' => $ancestor->id,
                    'parent_team_name' => $ancestor->name,
                    'user_roles' => $user->getRoleNames($ancestor)->toArray(),
                    'violation_type' => 'potential_inheritance_access',
                ];
            }
        }

        if (!empty($violations)) {
            activity()
                ->performedOn($team)
                ->causedBy($user)
                ->withProperties([
                    'violations' => $violations,
                    'audit_type' => 'inheritance_violation_check',
                ])
                ->log('permission_inheritance_audit');
        }

        return $violations;
    }

    /**
     * Get user's explicit permissions for a team.
     */
    public function getUserTeamPermissions(User $user, Team $team): array
    {
        if (!$this->validateExplicitAccess($user, $team)) {
            return [];
        }

        return [
            'roles' => $user->getRoleNames($team)->toArray(),
            'permissions' => $user->getPermissionsViaRoles($team)->pluck('name')->toArray(),
            'team_id' => $team->id,
            'team_name' => $team->name,
            'access_type' => $user->isSystemUser() ? 'system_bypass' : 'explicit',
        ];
    }

    /**
     * Bulk assign permissions while maintaining isolation.
     */
    public function bulkAssignTeamRoles(array $userTeamRoles): array
    {
        $results = [];

        DB::transaction(function () use ($userTeamRoles, &$results) {
            foreach ($userTeamRoles as $assignment) {
                $user = User::find($assignment['user_id']);
                $team = Team::find($assignment['team_id']);
                $role = $assignment['role'];

                if ($user && $team) {
                    $success = $this->assignTeamRole($user, $team, $role);
                    $results[] = [
                        'user_id' => $user->id,
                        'team_id' => $team->id,
                        'role' => $role,
                        'success' => $success,
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Log SystemUser bypass for audit purposes.
     */
    private function logSystemUserBypass(User $user, Team $team): void
    {
        activity()
            ->performedOn($team)
            ->causedBy($user)
            ->withProperties([
                'bypass_type' => 'system_user',
                'user_type' => get_class($user),
            ])
            ->log('system_user_bypass');
    }

    /**
     * Generate permission isolation report.
     */
    public function generateIsolationReport(): array
    {
        return [
            'total_teams' => Team::count(),
            'total_users' => User::count(),
            'total_team_memberships' => DB::table('team_user')->count(),
            'users_with_multiple_teams' => User::has('teams', '>', 1)->count(),
            'teams_with_no_members' => Team::doesntHave('members')->count(),
            'system_users' => User::where('type', 'system')->count(),
            'inheritance_violations' => $this->detectInheritanceViolations(),
        ];
    }

    /**
     * Detect any potential inheritance violations.
     */
    private function detectInheritanceViolations(): int
    {
        // This should always return 0 if isolation is working correctly
        $violations = 0;

        $teams = Team::with(['members', 'ancestors'])->get();
        
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                $ancestors = $team->ancestors();
                foreach ($ancestors as $ancestor) {
                    if ($ancestor->members()->where('user_id', $member->id)->exists()) {
                        // This is not a violation - user has explicit access to both
                        continue;
                    }
                    
                    // Check if user somehow has permissions without explicit membership
                    if ($member->hasAnyRole(['admin', 'executive', 'member'], $ancestor)) {
                        $violations++;
                    }
                }
            }
        }

        return $violations;
    }
}
```

### Step 4: Create Team Access Middleware

Create `app/Http/Middleware/TeamAccessMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Services\PermissionIsolationService;
use Closure;
use Illuminate\Http\Request;

class TeamAccessMiddleware
{
    private PermissionIsolationService $permissionService;

    public function __construct(PermissionIsolationService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = null): mixed
    {
        $user = $request->user();
        $team = $this->getTeamFromRequest($request);

        if (!$user || !$team) {
            abort(404, 'Team not found');
        }

        // Validate explicit access
        if (!$this->permissionService->validateExplicitAccess($user, $team)) {
            abort(403, 'Access denied: No explicit access to this team');
        }

        // Check specific permission if provided
        if ($permission && !$user->can($permission, $team)) {
            abort(403, "Access denied: Missing permission '{$permission}' for this team");
        }

        // Add team to request for controllers
        $request->merge(['current_team' => $team]);

        return $next($request);
    }

    /**
     * Extract team from request parameters.
     */
    private function getTeamFromRequest(Request $request)
    {
        // Try route parameter first
        if ($request->route('team')) {
            return $request->route('team');
        }

        // Try team_id parameter
        if ($request->has('team_id')) {
            return Team::find($request->get('team_id'));
        }

        // Try team slug
        if ($request->has('team_slug')) {
            return Team::where('slug', $request->get('team_slug'))->first();
        }

        return null;
    }
}
```

### Step 5: Register Middleware and Policies

Update `bootstrap/app.php` (Laravel 12.x pattern):

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'team.access' => \App\Http\Middleware\TeamAccessMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

Update `app/Providers/AuthServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\Team;
use App\Policies\TeamPermissionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Team::class => TeamPermissionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Define team-specific gates
        Gate::define('access-team', function ($user, $team) {
            return app(PermissionIsolationService::class)->validateExplicitAccess($user, $team);
        });

        Gate::define('manage-team-members', function ($user, $team) {
            return $user->can('manageMembers', $team);
        });

        Gate::define('manage-team-settings', function ($user, $team) {
            return $user->can('manageSettings', $team);
        });
    }
}
```

## Testing & Validation

### Feature Test for Permission Isolation (Laravel 12.x with Pest)

Create `tests/Feature/Permissions/PermissionIsolationTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\Organization;
use App\Models\Department;
use App\Models\StandardUser;
use App\Models\SystemUser;
use App\Services\PermissionIsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PermissionIsolationService::class);
});

test('parent team access does not grant child access', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create(['parent_id' => $org->id]);
    $admin = Admin::factory()->create();

    // Grant admin access to organization
    $this->service->assignTeamRole($admin, $org, 'admin');

    // Verify admin has access to organization
    expect($this->service->validateExplicitAccess($admin, $org))->toBeTrue();

    // Verify admin does NOT have access to department
    expect($this->service->validateExplicitAccess($admin, $dept))->toBeFalse();
});

test('explicit access required for each team', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create(['parent_id' => $org->id]);
    $user = StandardUser::factory()->create();

    // User has no access initially
    expect($this->service->validateExplicitAccess($user, $org))->toBeFalse();
    expect($this->service->validateExplicitAccess($user, $dept))->toBeFalse();

    // Grant access to organization only
    $this->service->assignTeamRole($user, $org, 'member');

    expect($this->service->validateExplicitAccess($user, $org))->toBeTrue();
    expect($this->service->validateExplicitAccess($user, $dept))->toBeFalse();

    // Grant access to department explicitly
    $this->service->assignTeamRole($user, $dept, 'member');

    expect($this->service->validateExplicitAccess($user, $org))->toBeTrue();
    expect($this->service->validateExplicitAccess($user, $dept))->toBeTrue();
});

test('system user bypasses all restrictions', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create(['parent_id' => $org->id]);
    $systemUser = SystemUser::factory()->create();

    // SystemUser should have access to all teams without explicit assignment
    expect($this->service->validateExplicitAccess($systemUser, $org))->toBeTrue();
    expect($this->service->validateExplicitAccess($systemUser, $dept))->toBeTrue();
});

test('role assignment is team scoped', function () {
    $teamA = Organization::factory()->create(['name' => 'Team A']);
    $teamB = Organization::factory()->create(['name' => 'Team B']);
    $user = StandardUser::factory()->create();

    // Assign admin role to Team A only
    $this->service->assignTeamRole($user, $teamA, 'admin');

    // User should be admin in Team A but not Team B
    expect($user->hasRole('admin', $teamA))->toBeTrue();
    expect($user->hasRole('admin', $teamB))->toBeFalse();

    // User should have no access to Team B
    expect($this->service->validateExplicitAccess($user, $teamB))->toBeFalse();
});

test('inheritance violation detection works', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create(['parent_id' => $org->id]);
    $user = StandardUser::factory()->create();

    // Grant access to both teams explicitly
    $this->service->assignTeamRole($user, $org, 'admin');
    $this->service->assignTeamRole($user, $dept, 'member');

    // This should NOT be flagged as violation (both are explicit)
    $violations = $this->service->auditInheritanceViolation($user, $dept);
    expect($violations)->toBeEmpty();
});

test('bulk role assignment maintains isolation', function () {
    $teams = Organization::factory()->count(3)->create();
    $user = StandardUser::factory()->create();

    $assignments = $teams->map(fn($team) => [
        'user_id' => $user->id,
        'team_id' => $team->id,
        'role' => 'member',
    ])->toArray();

    $results = $this->service->bulkAssignTeamRoles($assignments);

    // Verify all assignments succeeded
    expect($results)->toHaveCount(3);
    expect(collect($results)->every(fn($result) => $result['success']))->toBeTrue();

    // Verify user has explicit access to all teams
    foreach ($teams as $team) {
        expect($this->service->validateExplicitAccess($user, $team))->toBeTrue();
    }
});

test('permission isolation report is accurate', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create(['parent_id' => $org->id]);
    $users = StandardUser::factory()->count(5)->create();

    // Assign users to teams
    foreach ($users as $user) {
        $this->service->assignTeamRole($user, $org, 'member');
    }

    $report = $this->service->generateIsolationReport();

    expect($report)->toHaveKey('total_teams');
    expect($report)->toHaveKey('total_users');
    expect($report)->toHaveKey('inheritance_violations');
    expect($report['inheritance_violations'])->toBe(0); // Should always be 0
});
```

## Common Pitfalls & Troubleshooting

### Issue 1: Accidental Permission Inheritance
**Problem**: Developers assume parent team access grants child access
**Solution**: Always validate explicit access and use audit tools

### Issue 2: SystemUser Bypass Not Logged
**Problem**: SystemUser access not properly audited
**Solution**: Ensure all bypass operations are logged for compliance

### Issue 3: Performance Issues with Permission Checks
**Problem**: Too many database queries for permission validation
**Solution**: Implement caching layer and optimize queries

## Integration Points

### Connection to Other UMS-STI Components
- **User Models (Task 2.0)**: SystemUser bypass mechanism
- **Team Hierarchy (Task 3.0)**: Explicit access validation across hierarchy
- **GDPR Compliance (Task 5.0)**: Permission audit trails
- **FilamentPHP Interface (Task 6.0)**: Team-scoped admin access
- **API Layer (Task 7.0)**: API endpoint protection

## Further Reading & Resources

### Security Principles
- [Principle of Least Privilege](https://en.wikipedia.org/wiki/Principle_of_least_privilege)
- [Zero Trust Security Model](https://www.nist.gov/publications/zero-trust-architecture)

### Laravel Authorization
- [Laravel Authorization Documentation](https://laravel.com/docs/authorization)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)

## References and Citations

### Primary Sources
- [Laravel 12.x Authorization](https://laravel.com/docs/12.x/authorization)
- [Laravel 12.x Middleware](https://laravel.com/docs/12.x/middleware)
- [Spatie Laravel Permission v6](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Laravel 12.x Configuration](https://laravel.com/docs/12.x/configuration)

### Secondary Sources
- [Principle of Least Privilege](https://en.wikipedia.org/wiki/Principle_of_least_privilege)
- [Zero Trust Security Model](https://www.nist.gov/publications/zero-trust-architecture)
- [Role-Based Access Control (RBAC)](https://csrc.nist.gov/projects/role-based-access-control)
- [Laravel Security Best Practices](https://laravel.com/docs/12.x/security)

### Related UMS-STI Documentation
- [SystemUser Bypass Security](03-systemuser-bypass-security.md) - Next implementation step
- [Redis Caching Strategy](04-redis-caching-strategy.md) - Performance optimization
- [Permission Service Patterns](05-permission-service-patterns.md) - Service layer design
- [STI Architecture Explained](../02-user-models/01-sti-architecture-explained.md) - User type integration
- [Team Hierarchy](../03-team-hierarchy/01-closure-table-theory.md) - Team structure context
- [Unit Testing Strategies](../08-testing-suite/01-unit-testing-strategies.md) - Testing patterns
- [PRD Requirements](../../prd-UMS-STI.md) - Permission specifications (REQ-022, REQ-023, REQ-024)
- [Decision Log](../../decision-log-UMS-STI.md) - Permission isolation rationale (DECISION-003)

### Laravel 12.x Compatibility Notes
- Middleware registration moved to `bootstrap/app.php` configuration
- Enhanced authorization patterns with improved gate definitions
- Updated testing utilities with Pest PHP integration
- Improved service provider patterns for permission management
- Enhanced policy registration and management

---

**Next Steps**: Proceed to [SystemUser Bypass Security](03-systemuser-bypass-security.md) to implement the secure bypass mechanism for system maintenance operations.
