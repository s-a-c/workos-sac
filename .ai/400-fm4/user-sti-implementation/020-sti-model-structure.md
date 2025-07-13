# 2. STI Model Structure

## 2.1. Base User Model Implementation

### 2.1.1. Abstract Base User Class

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\States\UserState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\LaravelData\WithData;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStatus\HasStatuses;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Symfony\Component\Uid\Ulid;
use Tightenco\Parental\HasParent;

abstract class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasParent;
    use HasSlug;
    use HasStates;
    use HasStatuses;
    use Notifiable;
    use WithData;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'ulid',
        'slug',
        'role',
        'is_active',
        'last_login_at',
        'active_team_id',
        'profile_data',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'profile_data' => 'array',
        ];
    }

    /**
     * State configuration for FSM.
     */
    protected $states = [
        'state' => UserState::class,
    ];

    /**
     * Boot method for model events.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (User $user) {
            if (empty($user->ulid)) {
                $user->ulid = (string) Ulid::generate();
            }
        });
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Scope query to active users only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query by role.
     */
    public function scopeRole($query, UserRole $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Active team relationship.
     */
    public function activeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'active_team_id');
    }

    /**
     * All teams the user belongs to.
     */
    public function teams(): MorphToMany
    {
        return $this->morphedByMany(Team::class, 'teamable')
            ->withPivot(['role', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Switch to a different active team.
     */
    public function switchToTeam(Team $team): bool
    {
        // Verify user is a member of the team
        if (!$team->hasMember($this)) {
            return false;
        }

        $this->update(['active_team_id' => $team->id]);

        // Store in session for persistence
        session(['active_team_id' => $team->id]);

        return true;
    }

    /**
     * Clear active team selection.
     */
    public function clearActiveTeam(): void
    {
        $this->update(['active_team_id' => null]);
        session()->forget('active_team_id');
    }

    /**
     * Get user's teams with their roles.
     */
    public function getTeamsWithRoles(): Collection
    {
        return $this->teams()->get()->map(function ($team) {
            return [
                'team' => $team,
                'role' => $team->pivot->role,
                'is_active' => $team->pivot->is_active,
                'joined_at' => $team->pivot->joined_at,
            ];
        });
    }

    /**
     * Check if user is a system user (bypasses all permissions).
     */
    public function isSystemUser(): bool
    {
        return $this instanceof SystemUser;
    }

    /**
     * Get user's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? $this->email;
    }
}
```

## 2.2. STI Subclass Implementations

### 2.2.1. Standard User Class

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use App\States\User\ActiveState;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StandardUser extends User
{
    use HasFactory;

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected $keyType = 'int';

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'type' => 'standard_user',
        'role' => UserRole::User,
    ];

    /**
     * Boot method for model-specific logic.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (StandardUser $user) {
            $user->state = ActiveState::class;
        });
    }

    /**
     * Get user's profile preferences.
     */
    public function getProfilePreferences(): array
    {
        return $this->profile_data['preferences'] ?? [];
    }

    /**
     * Update profile preferences.
     */
    public function updateProfilePreferences(array $preferences): void
    {
        $profileData = $this->profile_data ?? [];
        $profileData['preferences'] = $preferences;
        $this->update(['profile_data' => $profileData]);
    }

    /**
     * Check if user can perform action.
     */
    public function canPerformAction(string $action): bool
    {
        $allowedActions = [
            'view_profile',
            'edit_profile',
            'change_password',
            'view_dashboard',
        ];

        return in_array($action, $allowedActions);
    }
}
```

### 2.2.2. Admin User Class

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use App\States\User\ActiveState;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends User
{
    use HasFactory;

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'type' => 'admin',
        'role' => UserRole::Admin,
    ];

    /**
     * Additional fillable attributes for admin.
     */
    protected $fillable = [
        'admin_level',
        'permissions',
        'department',
    ];

    /**
     * Additional casts for admin-specific fields.
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'permissions' => 'array',
            'admin_level' => 'integer',
        ]);
    }

    /**
     * Boot method for admin-specific logic.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (Admin $admin) {
            $admin->state = ActiveState::class;
            $admin->admin_level = $admin->admin_level ?? 1;
        });
    }

    /**
     * Check if admin has specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Grant permission to admin.
     */
    public function grantPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->update(['permissions' => $permissions]);
        }
    }

    /**
     * Revoke permission from admin.
     */
    public function revokePermission(string $permission): void
    {
        $permissions = array_filter(
            $this->permissions ?? [],
            fn($p) => $p !== $permission
        );
        $this->update(['permissions' => array_values($permissions)]);
    }

    /**
     * Check if admin can perform action.
     */
    public function canPerformAction(string $action): bool
    {
        $adminActions = [
            'manage_users',
            'view_admin_panel',
            'manage_settings',
            'view_analytics',
            'manage_content',
        ];

        return $this->hasPermission($action) || in_array($action, $adminActions);
    }

    /**
     * Get admin dashboard data.
     */
    public function getDashboardData(): array
    {
        return [
            'admin_level' => $this->admin_level,
            'permissions' => $this->permissions,
            'department' => $this->department,
            'last_login' => $this->last_login_at,
        ];
    }
}
```

### 2.2.3. Guest User Class

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use App\States\User\GuestState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Guest extends User
{
    use HasFactory;

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'type' => 'guest',
        'role' => UserRole::Guest,
        'is_active' => false,
    ];

    /**
     * Additional fillable attributes for guest.
     */
    protected $fillable = [
        'session_id',
        'expires_at',
        'conversion_data',
        'tracking_data',
    ];

    /**
     * Additional casts for guest-specific fields.
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'expires_at' => 'datetime',
            'conversion_data' => 'array',
            'tracking_data' => 'array',
        ]);
    }

    /**
     * Boot method for guest-specific logic.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (Guest $guest) {
            $guest->state = GuestState::class;
            $guest->expires_at = $guest->expires_at ?? Carbon::now()->addDays(30);
        });
    }

    /**
     * Check if guest session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Extend guest session.
     */
    public function extendSession(int $days = 30): void
    {
        $this->update([
            'expires_at' => Carbon::now()->addDays($days)
        ]);
    }

    /**
     * Convert guest to standard user.
     */
    public function convertToUser(array $userData): StandardUser
    {
        $standardUser = StandardUser::create(array_merge([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => $userData['password'],
            'profile_data' => $this->conversion_data,
        ], $userData));

        // Transfer any relevant data
        if ($this->tracking_data) {
            $standardUser->update([
                'profile_data' => array_merge(
                    $standardUser->profile_data ?? [],
                    ['guest_data' => $this->tracking_data]
                )
            ]);
        }

        return $standardUser;
    }

    /**
     * Track guest activity.
     */
    public function trackActivity(string $action, array $data = []): void
    {
        $trackingData = $this->tracking_data ?? [];
        $trackingData[] = [
            'action' => $action,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];
        
        $this->update(['tracking_data' => $trackingData]);
    }

    /**
     * Scope to non-expired guests.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to expired guests.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }
}
```

### 2.2.4. System User Class

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use App\States\User\ActiveState;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemUser extends User
{
    use HasFactory;

    /**
     * Default values for attributes.
     */
    protected $attributes = [
        'type' => 'system_user',
        'role' => UserRole::SuperAdmin,
        'is_active' => true,
    ];

    /**
     * Boot method for system user-specific logic.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SystemUser $user) {
            $user->state = ActiveState::class;
            $user->email_verified_at = now();
        });
    }

    /**
     * System users bypass all permission checks.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return true;
    }

    /**
     * System users bypass all role checks.
     */
    public function hasRole($roles, string $guard = null): bool
    {
        return true;
    }

    /**
     * System users bypass team permission checks.
     */
    public function hasTeamPermission(string $permission, Team $team): bool
    {
        return true;
    }

    /**
     * System users can perform any action.
     */
    public function canPerformAction(string $action): bool
    {
        return true;
    }

    /**
     * System users have access to all teams.
     */
    public function hasAccessToTeam(Team $team): bool
    {
        return true;
    }

    /**
     * Get system user capabilities.
     */
    public function getSystemCapabilities(): array
    {
        return [
            'unrestricted_access' => true,
            'bypass_permissions' => true,
            'bypass_team_restrictions' => true,
            'system_maintenance' => true,
            'emergency_access' => true,
            'automated_processes' => true,
        ];
    }

    /**
     * Check if this is an automated system process.
     */
    public function isAutomatedProcess(): bool
    {
        return str_contains($this->name, 'System') ||
               str_contains($this->email, 'system@') ||
               str_contains($this->email, 'noreply@');
    }

    /**
     * Log system user activity with enhanced context.
     */
    public function logSystemActivity(string $action, array $context = []): void
    {
        activity()
            ->performedOn($this)
            ->withProperties(array_merge($context, [
                'system_user' => true,
                'bypass_permissions' => true,
                'automated' => $this->isAutomatedProcess(),
            ]))
            ->log("System: {$action}");
    }
}
```

---

**Next**: [State Management](030-state-management.md) - Implementation of Finite State Machine for user states.
