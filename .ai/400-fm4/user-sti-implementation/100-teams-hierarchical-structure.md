# 10. Teams and Hierarchical Structure

## 10.1. Self-Referential Polymorphic STI Overview

The Team model implements a self-referential polymorphic Single Table Inheritance pattern, allowing for complex organizational hierarchies with different team types while maintaining efficient database queries and relationships.

### 10.1.1. Critical Design Principle: Non-Inherited Permissions

**⚠️ IMPORTANT**: Team permissions and roles are **NOT** inherited through the team hierarchy. This is a deliberate design decision with the following implications:

- **Explicit Permission Assignment**: Users must be explicitly granted roles/permissions for each team they need access to
- **Isolated Team Scopes**: Each team maintains its own isolated permission scope
- **No Cascading Access**: Parent team permissions do not automatically grant access to child teams
- **Security by Design**: Prevents accidental privilege escalation through organizational hierarchy

**Example Scenario**:
```
Organization: Acme Corp (User has Admin role)
├── Engineering Dept (User has NO access - must be explicitly granted)
    ├── Backend Project (User has NO access - must be explicitly granted)
        └── API Squad (User has NO access - must be explicitly granted)
```

This design ensures maximum security and explicit access control at every organizational level.

## 10.2. Team Model Structure

### 10.2.1. Base Team Model

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use App\Enums\TeamStatus;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Tightenco\Parental\HasParent;

abstract class Team extends Model
{
    use HasFactory;
    use HasParent;
    use HasSlug;
    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'ulid',
        'slug',
        'type',
        'status',
        'parent_id',
        'settings',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'type' => TeamType::class,
        'status' => TeamStatus::class,
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    // Team type constants
    public const TYPE_ORGANIZATION = 'organization';
    public const TYPE_DEPARTMENT = 'department';
    public const TYPE_PROJECT = 'project';
    public const TYPE_SQUAD = 'squad';

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name', 'type'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugOnUpdate()
            ->slugsShouldBeNoLongerThan(100)
            ->usingSeparator('-');
    }

    /**
     * Parent team relationship (self-referential).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Child teams relationship (self-referential).
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * All descendants (recursive).
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * All ancestors (recursive).
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }
        
        return $ancestors;
    }

    /**
     * Team members (polymorphic many-to-many).
     */
    public function members(): MorphToMany
    {
        return $this->morphToMany(User::class, 'teamable')
            ->withPivot(['role', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Active team members.
     */
    public function activeMembers(): MorphToMany
    {
        return $this->members()->wherePivot('is_active', true);
    }

    /**
     * Team leaders/managers.
     */
    public function leaders(): MorphToMany
    {
        return $this->members()
            ->wherePivot('role', 'leader')
            ->wherePivot('is_active', true);
    }

    /**
     * Check if user is a member of this team.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Check if user has access to this team (explicit membership only).
     * NOTE: Does NOT check parent team memberships - permissions are not inherited.
     */
    public function userHasAccess(User $user): bool
    {
        // System users bypass all restrictions
        if ($user->isSystemUser()) {
            return true;
        }

        // Only explicit team membership grants access
        return $this->hasMember($user);
    }

    /**
     * Add a member to the team.
     */
    public function addMember(User $user, string $role = 'member'): void
    {
        if (!$this->hasMember($user)) {
            $this->members()->attach($user->id, [
                'role' => $role,
                'joined_at' => now(),
                'is_active' => true,
            ]);
        }
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    /**
     * Update member role.
     */
    public function updateMemberRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Get team hierarchy level.
     */
    public function getLevel(): int
    {
        return $this->ancestors()->count();
    }

    /**
     * Get root team (top-level parent).
     */
    public function getRoot(): ?self
    {
        $ancestors = $this->ancestors();
        return $ancestors->isEmpty() ? $this : $ancestors->last();
    }

    /**
     * Check if team is root (has no parent).
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if team is leaf (has no children).
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Scope for active teams.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for root teams.
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope by team type.
     */
    public function scopeOfType($query, TeamType $type)
    {
        return $query->where('type', $type);
    }
}
```

## 10.3. Team Type Implementations

### 10.3.1. Organization Team

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Team
{
    use HasFactory;

    protected $attributes = [
        'type' => TeamType::Organization,
    ];

    /**
     * Boot method for organization-specific logic.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (Organization $organization) {
            // Organizations are always root level
            $organization->parent_id = null;
        });
    }

    /**
     * Get organization departments.
     */
    public function departments()
    {
        return $this->children()->where('type', TeamType::Department);
    }

    /**
     * Get all projects within this organization.
     */
    public function allProjects()
    {
        return Project::whereHas('ancestors', function ($query) {
            $query->where('id', $this->id);
        });
    }

    /**
     * Get organization settings.
     */
    public function getOrganizationSettings(): array
    {
        return array_merge([
            'allow_public_projects' => false,
            'require_approval_for_members' => true,
            'max_departments' => null,
        ], $this->settings ?? []);
    }
}
```

### 10.3.2. Department Team

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Team
{
    use HasFactory;

    protected $attributes = [
        'type' => TeamType::Department,
    ];

    /**
     * Boot method for department-specific logic.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (Department $department) {
            // Departments must have an organization parent
            if (!$department->parent_id) {
                throw new \InvalidArgumentException('Department must belong to an organization');
            }
        });
    }

    /**
     * Get parent organization.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    /**
     * Get department projects.
     */
    public function projects()
    {
        return $this->children()->where('type', TeamType::Project);
    }

    /**
     * Get department budget information.
     */
    public function getBudgetInfo(): array
    {
        return $this->metadata['budget'] ?? [
            'allocated' => 0,
            'spent' => 0,
            'currency' => 'USD',
        ];
    }
}
```

### 10.3.3. Project Team

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Team
{
    use HasFactory;

    protected $attributes = [
        'type' => TeamType::Project,
    ];

    protected $casts = [
        'type' => TeamType::class,
        'status' => TeamStatus::class,
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $fillable = [
        'name',
        'description',
        'ulid',
        'slug',
        'type',
        'status',
        'parent_id',
        'settings',
        'metadata',
        'is_active',
        'start_date',
        'end_date',
        'budget',
        'priority',
    ];

    /**
     * Get project squads.
     */
    public function squads()
    {
        return $this->children()->where('type', TeamType::Squad);
    }

    /**
     * Get project manager.
     */
    public function manager()
    {
        return $this->members()
            ->wherePivot('role', 'manager')
            ->wherePivot('is_active', true)
            ->first();
    }

    /**
     * Check if project is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== TeamStatus::Completed;
    }

    /**
     * Get project progress percentage.
     */
    public function getProgressPercentage(): int
    {
        return $this->metadata['progress'] ?? 0;
    }

    /**
     * Update project progress.
     */
    public function updateProgress(int $percentage): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['progress'] = max(0, min(100, $percentage));
        $this->update(['metadata' => $metadata]);
    }
}
```

### 10.3.4. Squad Team

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Squad extends Team
{
    use HasFactory;

    protected $attributes = [
        'type' => TeamType::Squad,
    ];

    /**
     * Get parent project.
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'parent_id');
    }

    /**
     * Get squad lead.
     */
    public function lead()
    {
        return $this->members()
            ->wherePivot('role', 'lead')
            ->wherePivot('is_active', true)
            ->first();
    }

    /**
     * Get squad capacity information.
     */
    public function getCapacityInfo(): array
    {
        return [
            'current_members' => $this->activeMembers()->count(),
            'max_capacity' => $this->settings['max_capacity'] ?? 8,
            'utilization' => $this->calculateUtilization(),
        ];
    }

    /**
     * Calculate squad utilization percentage.
     */
    private function calculateUtilization(): int
    {
        $maxCapacity = $this->settings['max_capacity'] ?? 8;
        $currentMembers = $this->activeMembers()->count();
        
        return $maxCapacity > 0 ? round(($currentMembers / $maxCapacity) * 100) : 0;
    }
}
```

## 10.4. Active Team Context Management

### 10.4.1. Active Team Service

```php
<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class ActiveTeamService
{
    /**
     * Set user's active team with validation.
     */
    public function setActiveTeam(User $user, Team $team): bool
    {
        // System users can access any team
        if ($user->isSystemUser()) {
            return $this->updateActiveTeam($user, $team);
        }

        // Validate user has access to the team
        if (!$team->userHasAccess($user)) {
            throw new \InvalidArgumentException('User does not have access to this team');
        }

        return $this->updateActiveTeam($user, $team);
    }

    /**
     * Update user's active team and session.
     */
    private function updateActiveTeam(User $user, Team $team): bool
    {
        $user->update(['active_team_id' => $team->id]);
        Session::put('active_team_id', $team->id);

        // Log team switch
        activity()
            ->performedOn($team)
            ->causedBy($user)
            ->log('Switched to active team');

        return true;
    }

    /**
     * Clear active team selection.
     */
    public function clearActiveTeam(User $user): void
    {
        $user->update(['active_team_id' => null]);
        Session::forget('active_team_id');
    }

    /**
     * Get user's available teams for switching.
     */
    public function getAvailableTeams(User $user): Collection
    {
        // System users can access all teams
        if ($user->isSystemUser()) {
            return Team::active()->get();
        }

        // Regular users only see teams they're members of
        return $user->teams()->wherePivot('is_active', true)->get();
    }

    /**
     * Restore active team from session on login.
     */
    public function restoreActiveTeamFromSession(User $user): void
    {
        $sessionTeamId = Session::get('active_team_id');

        if ($sessionTeamId && $user->active_team_id !== $sessionTeamId) {
            $team = Team::find($sessionTeamId);

            if ($team && ($user->isSystemUser() || $team->userHasAccess($user))) {
                $user->update(['active_team_id' => $team->id]);
            } else {
                // Clear invalid session data
                Session::forget('active_team_id');
            }
        }
    }
}
```

---

**Next**: [Roles and Permissions Integration](110-roles-permissions-integration.md) - Spatie Laravel Permission integration with team-based scoping.
