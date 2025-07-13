# 13. Best Practices and Patterns

## 13.1. Laravel Best Practices Implementation with Teams and Permissions

### 13.1.1. Enhanced Model Organization with Teams

```php
<?php

namespace App\Models;

use App\Traits\HasUlid;
use App\Traits\HasUserStates;
use App\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Base User Model following Laravel conventions
 * 
 * @property string $ulid
 * @property string $slug
 * @property UserRole $role
 * @property UserStatus $status
 * @property bool $is_active
 * @property UserState $state
 */
abstract class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use HasUlid;
    use HasUserStates;
    use LogsActivity;

    // Explicit property declarations for better IDE support
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'profile_data',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'profile_data' => 'array',
    ];

    // Use constants for magic strings
    public const TYPE_STANDARD = 'standard_user';
    public const TYPE_ADMIN = 'admin';
    public const TYPE_GUEST = 'guest';

    // Explicit scope methods
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole(Builder $query, UserRole $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // Business logic methods
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function isType(string $type): bool
    {
        return $this->type === $type;
    }
}
```

### 13.1.2. Enhanced Service Layer with Teams and Permissions

```php
<?php

namespace App\Services;

use App\Data\UserData;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Create user based on role.
     */
    public function createUser(UserData $userData): User
    {
        return DB::transaction(function () use ($userData) {
            $userClass = $this->getUserClassForRole($userData->role);
            
            $user = $userClass::create([
                'name' => $userData->name,
                'email' => $userData->email,
                'password' => Hash::make($userData->password ?? str()->random(16)),
                'role' => $userData->role,
                'is_active' => $userData->is_active,
                'profile_data' => $userData->profile?->toArray(),
            ]);

            // Set initial state
            $user->state = $this->getInitialStateForRole($userData->role);
            $user->save();

            // Log activity
            activity()
                ->performedOn($user)
                ->log('User created');

            return $user;
        });
    }

    /**
     * Convert guest to standard user.
     */
    public function convertGuestToUser(Guest $guest, array $userData): StandardUser
    {
        return DB::transaction(function () use ($guest, $userData) {
            $standardUser = $guest->convertToUser($userData);
            
            // Transfer any relevant data
            $this->transferGuestData($guest, $standardUser);
            
            // Mark guest as converted
            $guest->setStatus('converted', 'Converted to standard user');
            
            return $standardUser;
        });
    }

    /**
     * Get appropriate user class for role.
     */
    private function getUserClassForRole(UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin, UserRole::SuperAdmin => Admin::class,
            UserRole::Guest => Guest::class,
            default => StandardUser::class,
        };
    }

    /**
     * Get initial state for role.
     */
    private function getInitialStateForRole(UserRole $role): string
    {
        return match ($role) {
            UserRole::Guest => GuestState::class,
            default => PendingState::class,
        };
    }

    /**
     * Transfer guest data to standard user.
     */
    private function transferGuestData(Guest $guest, StandardUser $user): void
    {
        if ($guest->tracking_data) {
            $profileData = $user->profile_data ?? [];
            $profileData['guest_tracking'] = $guest->tracking_data;
            $user->update(['profile_data' => $profileData]);
        }
    }
}
```

### 13.1.3. Team Management Service Pattern

```php
<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Services\RoleHierarchyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeamManagementService
{
    public function __construct(
        private RoleHierarchyService $roleHierarchy
    ) {}

    /**
     * Create team with proper authorization and setup.
     */
    public function createTeam(array $data, User $creator): Team
    {
        Gate::authorize('create', Team::class);

        return DB::transaction(function () use ($data, $creator) {
            $team = Team::create($data);

            // Add creator as team admin
            $team->addMember($creator, 'admin');

            // Create default team roles
            $this->roleHierarchy->createDefaultTeamRoles($team);

            // Log activity
            activity()
                ->performedOn($team)
                ->causedBy($creator)
                ->log('Team created');

            return $team;
        });
    }

    /**
     * Add member to team with role validation.
     */
    public function addTeamMember(Team $team, User $user, string $role, User $assigner): void
    {
        Gate::authorize('manageMembers', $team);

        // Validate role assignment permissions
        $roleModel = $team->roles()->where('name', $role)->first();
        if ($roleModel && !$this->roleHierarchy->canAssignRole($assigner, $roleModel, $team)) {
            throw new \InvalidArgumentException('Insufficient permissions to assign this role');
        }

        DB::transaction(function () use ($team, $user, $role, $assigner) {
            $team->addMember($user, $role);

            // Assign team-specific role
            if ($roleModel = $team->roles()->where('name', $role)->first()) {
                $user->assignRole($roleModel);
            }

            activity()
                ->performedOn($team)
                ->causedBy($assigner)
                ->withProperties(['user_id' => $user->id, 'role' => $role])
                ->log('Member added to team');
        });
    }
}
```

## 13.2. Design Patterns Implementation

### 13.2.1. Enhanced Factory Pattern for User and Team Creation

```php
<?php

namespace App\Factories;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\User;

class UserFactory
{
    /**
     * Create user instance based on type.
     */
    public static function create(string $type, array $attributes = []): User
    {
        return match ($type) {
            User::TYPE_ADMIN => Admin::create($attributes),
            User::TYPE_GUEST => Guest::create($attributes),
            User::TYPE_STANDARD => StandardUser::create($attributes),
            default => throw new \InvalidArgumentException("Unknown user type: {$type}"),
        };
    }

    /**
     * Create user instance based on role.
     */
    public static function createByRole(UserRole $role, array $attributes = []): User
    {
        $attributes['role'] = $role;
        
        return match ($role) {
            UserRole::Admin, UserRole::SuperAdmin => Admin::create($attributes),
            UserRole::Guest => Guest::create($attributes),
            default => StandardUser::create($attributes),
        };
    }
}
```

### 13.2.2. Observer Pattern for User and Team Events

```php
<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\UserCreatedNotification;
use App\Notifications\UserStatusChangedNotification;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Send welcome notification
        $user->notify(new UserCreatedNotification());
        
        // Log activity
        activity()
            ->performedOn($user)
            ->log('User account created');
        
        // Clear relevant caches
        $this->clearUserCaches($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Check for status changes
        if ($user->wasChanged('is_active')) {
            $user->notify(new UserStatusChangedNotification(
                $user->is_active ? 'activated' : 'deactivated'
            ));
        }
        
        // Clear caches
        $this->clearUserCaches($user);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Log deletion
        activity()
            ->performedOn($user)
            ->log('User account deleted');
        
        // Clear caches
        $this->clearUserCaches($user);
    }

    /**
     * Clear user-related caches.
     */
    private function clearUserCaches(User $user): void
    {
        Cache::forget("user:ulid:{$user->ulid}");
        Cache::forget("user:slug:{$user->slug}");
        Cache::forget("user:email:{$user->email}");
        Cache::tags(['users', 'user-stats'])->flush();
    }
}
```

### 13.2.3. Repository Pattern (Optional)

```php
<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findByUlid(string $ulid): ?User;
    public function findBySlug(string $slug): ?User;
    public function findByEmail(string $email): ?User;
    public function getActiveUsers(int $perPage = 15): LengthAwarePaginator;
    public function getUsersByRole(string $role): Collection;
    public function searchUsers(string $query): Collection;
}

class UserRepository implements UserRepositoryInterface
{
    public function findByUlid(string $ulid): ?User
    {
        return User::byUlid($ulid)->first();
    }

    public function findBySlug(string $slug): ?User
    {
        return User::bySlug($slug)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getActiveUsers(int $perPage = 15): LengthAwarePaginator
    {
        return User::active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUsersByRole(string $role): Collection
    {
        return User::where('role', $role)->get();
    }

    public function searchUsers(string $query): Collection
    {
        return User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(50)
            ->get();
    }
}
```

## 13.3. Security Best Practices with Teams and Permissions

### 13.3.1. Enhanced Authorization Policies with Team Context

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::SuperAdmin);
    }

    /**
     * Determine if the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can view other users
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::SuperAdmin);
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::SuperAdmin);
    }

    /**
     * Determine if the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can update other users, but not super admins
        if ($user->hasRole(UserRole::Admin)) {
            return !$model->hasRole(UserRole::SuperAdmin);
        }

        // Super admins can update anyone
        return $user->hasRole(UserRole::SuperAdmin);
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Only super admins can delete users
        return $user->hasRole(UserRole::SuperAdmin);
    }
}
```

### 13.3.2. Input Validation and Sanitization

```php
<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Rules\ValidUlid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email,' . $this->route('user')->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', new Enum(UserRole::class)],
            'profile_data.first_name' => ['nullable', 'string', 'max:100'],
            'profile_data.last_name' => ['nullable', 'string', 'max:100'],
            'profile_data.phone' => ['nullable', 'string', 'regex:/^[\+]?[1-9][\d]{0,15}$/'],
            'profile_data.bio' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sanitize input
        $this->merge([
            'name' => strip_tags($this->name),
            'email' => strtolower(trim($this->email)),
        ]);

        // Sanitize profile data
        if ($this->has('profile_data')) {
            $profileData = $this->profile_data;
            foreach (['first_name', 'last_name', 'bio'] as $field) {
                if (isset($profileData[$field])) {
                    $profileData[$field] = strip_tags($profileData[$field]);
                }
            }
            $this->merge(['profile_data' => $profileData]);
        }
    }
}
```

## 13.4. Performance Optimization

### 13.4.1. Query Optimization with Teams

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class OptimizedUserService
{
    /**
     * Get users with optimized queries.
     */
    public function getUsersWithProfiles(): Collection
    {
        return User::select(['id', 'ulid', 'name', 'email', 'type', 'role', 'is_active'])
            ->with(['statuses' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->active()
            ->get();
    }

    /**
     * Cached user lookup.
     */
    public function findUserCached(string $ulid): ?User
    {
        return Cache::remember(
            "user:ulid:{$ulid}",
            now()->addHours(1),
            fn() => User::byUlid($ulid)->first()
        );
    }

    /**
     * Batch user lookup.
     */
    public function findUsersBatch(array $ulids): Collection
    {
        return User::whereIn('ulid', $ulids)
            ->get()
            ->keyBy('ulid');
    }

    /**
     * Efficient user statistics.
     */
    public function getUserStatistics(): array
    {
        return Cache::remember('user:statistics', now()->addMinutes(30), function () {
            return [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'by_type' => User::groupBy('type')->selectRaw('type, count(*) as count')->pluck('count', 'type'),
                'by_role' => User::groupBy('role')->selectRaw('role, count(*) as count')->pluck('count', 'role'),
            ];
        });
    }
}
```

## 13.5. Error Handling and Logging

### 13.5.1. Custom Exceptions

```php
<?php

namespace App\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    public function __construct(string $identifier, string $type = 'ID')
    {
        parent::__construct("User not found with {$type}: {$identifier}");
    }
}

class InvalidUserTypeException extends Exception
{
    public function __construct(string $type)
    {
        parent::__construct("Invalid user type: {$type}");
    }
}

class UserStateTransitionException extends Exception
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Cannot transition user state from {$from} to {$to}");
    }
}
```

### 13.5.2. Comprehensive Logging

```php
<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LogsUserActivity
{
    /**
     * Log user activity with context.
     */
    protected function logUserActivity(string $action, array $context = []): void
    {
        Log::info("User activity: {$action}", [
            'user_id' => $this->id,
            'user_ulid' => $this->ulid,
            'user_type' => $this->type,
            'user_role' => $this->role->value,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'context' => $context,
        ]);
    }

    /**
     * Log security events.
     */
    protected function logSecurityEvent(string $event, array $context = []): void
    {
        Log::warning("Security event: {$event}", [
            'user_id' => $this->id,
            'user_ulid' => $this->ulid,
            'user_email' => $this->email,
            'ip_address' => request()->ip(),
            'context' => $context,
        ]);
    }
}
```

---

This completes the comprehensive documentation for implementing a User model using Single Table Inheritance (STI) pattern with modern Laravel and PHP features, integrated with FilamentPHP v4, Teams functionality with self-referential polymorphic STI, and spatie/laravel-permission for robust role-based access control. The documentation covers all aspects from architecture to implementation, testing, and best practices for enterprise-grade applications.
