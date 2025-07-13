# Single Table Inheritance (STI) Architecture Explained

## Executive Summary
Single Table Inheritance (STI) is a design pattern that stores multiple related classes in a single database table, using a discriminator column to identify the specific type. In UMS-STI, we use STI to manage four user types (Standard, Admin, Guest, SystemUser) in one `users` table while maintaining type-specific behaviors and relationships.

## Learning Objectives
After completing this guide, you will:
- Understand STI principles and when to use them vs. alternatives
- Implement Laravel STI using the tightenco/parental package
- Design type-specific behaviors while sharing common functionality
- Handle STI queries efficiently with proper indexing
- Integrate STI with polymorphic relationships for extended data

## Prerequisite Knowledge
- Laravel Eloquent ORM basics
- PHP object-oriented programming (inheritance, abstract classes)
- Database table design principles
- Basic understanding of Laravel migrations

## Architectural Overview

### STI vs. Alternative Patterns

Based on **DECISION-001** from our decision log, we chose **Hybrid STI + Polymorphic** approach:

```
Alternative 1: Separate Tables (Table Per Type)
users_standard    users_admin    users_guest    users_system
├── id           ├── id         ├── id         ├── id
├── name         ├── name       ├── name       ├── name
├── email        ├── email      ├── email      ├── email
└── ...          └── ...        └── ...        └── ...

❌ Problems: Complex joins, duplicate schema, relationship complexity

Alternative 2: Pure STI (All data in one table)
users
├── id
├── type (discriminator)
├── name
├── email
├── admin_level (null for non-admins)
├── guest_session_id (null for non-guests)
└── system_permissions (null for non-system)

❌ Problems: Sparse columns, data integrity issues

✅ Our Choice: Hybrid STI + Polymorphic
users (shared data)          user_profiles (type-specific data)
├── id                      ├── id
├── type (discriminator)    ├── user_id
├── name                    ├── user_type
├── email                   ├── profile_data (JSON)
├── created_at              └── ...
└── ...
```

### UMS-STI User Type Hierarchy

```
                    User (Abstract)
                         │
        ┌────────────────┼────────────────┐
        │                │                │
   StandardUser      AdminUser        GuestUser
        │                │                │
   ┌────────┐      ┌─────────┐      ┌─────────┐
   │Profile │      │Profile  │      │Profile  │
   │Settings│      │Permissions│     │Session  │
   │Teams   │      │Audit    │      │Tracking │
   └────────┘      └─────────┘      └─────────┘
                                          
                    SystemUser
                         │
                   ┌─────────┐
                   │Bypass   │
                   │Audit    │
                   │Emergency│
                   └─────────┘
```

## Core Concepts Deep Dive

### 1. Discriminator Column Strategy
The `type` column determines which class to instantiate:

```php
// Database record
['id' => 1, 'type' => 'admin', 'name' => 'John Doe', 'email' => 'john@example.com']

// Laravel instantiates
$user = Admin::find(1); // Returns Admin instance, not User
```

### 2. Shared vs. Type-Specific Behavior
```php
// Shared behavior (in base User class)
$user->getName();           // All users have names
$user->getEmail();          // All users have emails
$user->joinTeam($team);     // All users can join teams

// Type-specific behavior
$admin->manageUsers();      // Only admins can manage users
$guest->convertToUser();    // Only guests can convert
$system->bypassPermissions(); // Only system users bypass
```

### 3. Query Scoping
STI automatically scopes queries by type:

```php
// These queries are automatically scoped
Admin::all();           // SELECT * FROM users WHERE type = 'admin'
StandardUser::count();  // SELECT COUNT(*) FROM users WHERE type = 'standard'
Guest::active();        // SELECT * FROM users WHERE type = 'guest' AND status = 'active'
```

## Implementation Principles & Patterns

### 1. Abstract Base Class Pattern
```php
abstract class User extends Authenticatable
{
    // Shared functionality for all user types
    // Cannot be instantiated directly
}
```

### 2. Type-Specific Concrete Classes
```php
class Admin extends User
{
    // Admin-specific behavior
    // Can be instantiated and queried
}
```

### 3. Polymorphic Relationships for Extended Data
```php
// Base user data in users table
// Extended data in user_profiles table via polymorphic relationship
$admin->profile; // Returns AdminProfile instance
$guest->profile; // Returns GuestProfile instance
```

## Step-by-Step Implementation Guide

### Step 1: Install tightenco/parental Package

```bash
# Laravel 12.x compatible version
composer require tightenco/parental:^2.0

# Verify compatibility
composer show tightenco/parental
```

### Step 2: Create Base User Migration

Create `database/migrations/001_create_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // STI discriminator column
            $table->string('type')->index();
            
            // Shared user data
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('slug')->unique();
            
            // State management
            $table->string('state')->default('pending');
            
            // Active team tracking
            $table->unsignedBigInteger('active_team_id')->nullable();
            
            // User stamps (wildside/userstamps)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['type', 'state']);
            $table->index(['type', 'active_team_id']);
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### Step 3: Create Abstract Base User Model

Create `app/Models/User.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserState;
use App\Enums\UserType;
use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Laravel 12.x API authentication
use Spatie\ModelStates\HasStates;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Tightenco\Parental\HasParent;
use Wildside\Userstamps\Userstamps;

abstract class User extends Authenticatable
{
    use HasApiTokens; // Laravel 12.x API authentication
    use HasFactory;
    use HasParent;
    use HasRoles;
    use HasSlug;
    use HasStates;
    use HasUlid;
    use Notifiable;
    use SoftDeletes;
    use Userstamps;

    protected $fillable = [
        'name',
        'email',
        'password',
        'ulid',
        'slug',
        'state',
        'active_team_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'state' => UserState::class,
    ];

    // Abstract methods that must be implemented by child classes
    abstract public function getTypeAttribute(): UserType;
    abstract public function getPermissionLevel(): int;
    abstract public function canAccessAdminPanel(): bool;

    /**
     * Get the slug options for the model.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name', 'type'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugOnUpdate()
            ->slugsShouldBeNoLongerThan(100);
    }

    /**
     * Polymorphic relationship to user profiles.
     */
    public function profile(): MorphOne
    {
        return $this->morphOne(UserProfile::class, 'user');
    }

    /**
     * Active team relationship.
     */
    public function activeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'active_team_id');
    }

    /**
     * Team memberships (many-to-many polymorphic).
     */
    public function teams(): MorphToMany
    {
        return $this->morphToMany(Team::class, 'teamable')
            ->withPivot(['role', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Active team memberships only.
     */
    public function activeTeams(): MorphToMany
    {
        return $this->teams()->wherePivot('is_active', true);
    }

    /**
     * Switch user's active team.
     */
    public function switchToTeam(Team $team): bool
    {
        if (!$this->teams()->where('teams.id', $team->id)->exists()) {
            return false;
        }

        $this->update(['active_team_id' => $team->id]);
        return true;
    }

    /**
     * Check if user is a specific type.
     */
    public function isType(UserType $type): bool
    {
        return $this->getTypeAttribute() === $type;
    }

    /**
     * Check if user is system user (for bypass logic).
     */
    public function isSystemUser(): bool
    {
        return $this instanceof SystemUser;
    }

    /**
     * Get user's display name with type.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->getTypeAttribute()->value})";
    }

    /**
     * Scope to filter by user type.
     */
    public function scopeOfType($query, UserType $type)
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope to filter by state.
     */
    public function scopeInState($query, UserState $state)
    {
        return $query->where('state', $state->value);
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('state', UserState::Active);
    }
}
```

### Step 4: Create User Type Enum

Create `app/Enums/UserType.php`:

```php
<?php

namespace App\Enums;

enum UserType: string
{
    case Standard = 'standard';
    case Admin = 'admin';
    case Guest = 'guest';
    case System = 'system';

    public function getLabel(): string
    {
        return match($this) {
            self::Standard => 'Standard User',
            self::Admin => 'Administrator',
            self::Guest => 'Guest User',
            self::System => 'System User',
        };
    }

    public function getPermissionLevel(): int
    {
        return match($this) {
            self::Guest => 1,
            self::Standard => 2,
            self::Admin => 3,
            self::System => 4,
        };
    }

    public function canAccessAdminPanel(): bool
    {
        return match($this) {
            self::Admin, self::System => true,
            default => false,
        };
    }
}
```

### Step 5: Create Concrete User Type Classes

Create `app/Models/StandardUser.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserType;

class StandardUser extends User
{
    protected $attributes = [
        'type' => 'standard',
    ];

    public function getTypeAttribute(): UserType
    {
        return UserType::Standard;
    }

    public function getPermissionLevel(): int
    {
        return UserType::Standard->getPermissionLevel();
    }

    public function canAccessAdminPanel(): bool
    {
        return false;
    }

    /**
     * Standard user specific methods.
     */
    public function canCreateTeams(): bool
    {
        return true;
    }

    public function canInviteUsers(): bool
    {
        return $this->teams()->wherePivot('role', 'leader')->exists();
    }
}
```

Create `app/Models/Admin.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserType;

class Admin extends User
{
    protected $attributes = [
        'type' => 'admin',
    ];

    public function getTypeAttribute(): UserType
    {
        return UserType::Admin;
    }

    public function getPermissionLevel(): int
    {
        return UserType::Admin->getPermissionLevel();
    }

    public function canAccessAdminPanel(): bool
    {
        return true;
    }

    /**
     * Admin specific methods.
     */
    public function canManageAllUsers(): bool
    {
        return true;
    }

    public function canManageAllTeams(): bool
    {
        return true;
    }

    public function canViewAuditLogs(): bool
    {
        return true;
    }

    public function canManageSystemSettings(): bool
    {
        return true;
    }
}
```

Create `app/Models/Guest.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserType;

class Guest extends User
{
    protected $attributes = [
        'type' => 'guest',
    ];

    public function getTypeAttribute(): UserType
    {
        return UserType::Guest;
    }

    public function getPermissionLevel(): int
    {
        return UserType::Guest->getPermissionLevel();
    }

    public function canAccessAdminPanel(): bool
    {
        return false;
    }

    /**
     * Guest specific methods.
     */
    public function convertToStandardUser(array $additionalData = []): StandardUser
    {
        // Create new standard user with guest data
        $standardUser = StandardUser::create(array_merge([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'state' => $this->state,
        ], $additionalData));

        // Transfer any relevant data
        if ($this->profile) {
            $this->profile->update([
                'user_id' => $standardUser->id,
                'user_type' => StandardUser::class,
            ]);
        }

        // Soft delete the guest user
        $this->delete();

        return $standardUser;
    }

    public function getSessionDuration(): int
    {
        return 24; // 24 hours for guest sessions
    }

    public function canJoinTeams(): bool
    {
        return false; // Guests cannot join teams
    }
}
```

Create `app/Models/SystemUser.php`:

```php
<?php

namespace App\Models;

use App\Enums\UserType;

class SystemUser extends User
{
    protected $attributes = [
        'type' => 'system',
    ];

    public function getTypeAttribute(): UserType
    {
        return UserType::System;
    }

    public function getPermissionLevel(): int
    {
        return UserType::System->getPermissionLevel();
    }

    public function canAccessAdminPanel(): bool
    {
        return true;
    }

    /**
     * System user specific methods.
     */
    public function bypassAllPermissions(): bool
    {
        return true;
    }

    public function canPerformEmergencyActions(): bool
    {
        return true;
    }

    public function canAccessAllTeams(): bool
    {
        return true;
    }

    public function canViewAllAuditLogs(): bool
    {
        return true;
    }

    /**
     * Override team access check for system users.
     */
    public function canAccessTeam(Team $team): bool
    {
        return true; // System users can access any team
    }
}
```

## Testing & Validation

### Unit Test for STI Functionality (Laravel 12.x with Pest)

Create `tests/Unit/Models/UserStiTest.php`:

```php
<?php

use App\Enums\UserType;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user types are correctly instantiated', function () {
    $standard = StandardUser::factory()->create();
    $admin = Admin::factory()->create();
    $guest = Guest::factory()->create();
    $system = SystemUser::factory()->create();

    expect($standard)->toBeInstanceOf(StandardUser::class);
    expect($admin)->toBeInstanceOf(Admin::class);
    expect($guest)->toBeInstanceOf(Guest::class);
    expect($system)->toBeInstanceOf(SystemUser::class);
});

test('sti queries are scoped correctly', function () {
    StandardUser::factory()->count(3)->create();
    Admin::factory()->count(2)->create();
    Guest::factory()->count(1)->create();

    expect(StandardUser::count())->toBe(3);
    expect(Admin::count())->toBe(2);
    expect(Guest::count())->toBe(1);
    expect(User::count())->toBe(6); // All users via base class
});

test('type specific behaviors work correctly', function () {
    $admin = Admin::factory()->create();
    $standard = StandardUser::factory()->create();
    $guest = Guest::factory()->create();
    $system = SystemUser::factory()->create();

    expect($admin->canAccessAdminPanel())->toBeTrue();
    expect($standard->canAccessAdminPanel())->toBeFalse();
    expect($guest->canAccessAdminPanel())->toBeFalse();
    expect($system->canAccessAdminPanel())->toBeTrue();

    expect($system->bypassAllPermissions())->toBeTrue();
    expect($admin->canManageAllUsers())->toBeTrue();
    expect($guest->canJoinTeams())->toBeFalse();
});

test('guest conversion to standard user works', function () {
    $guest = Guest::factory()->create([
        'name' => 'Test Guest',
        'email' => 'guest@example.com',
    ]);

    $standardUser = $guest->convertToStandardUser();

    expect($standardUser)->toBeInstanceOf(StandardUser::class);
    expect($standardUser->name)->toBe('Test Guest');
    expect($standardUser->email)->toBe('guest@example.com');

    $this->assertSoftDeleted($guest);
});

test('user type enum integration', function () {
    $admin = Admin::factory()->create();

    expect($admin->getTypeAttribute())->toBe(UserType::Admin);
    expect($admin->getPermissionLevel())->toBe(3);
    expect($admin->isType(UserType::Admin))->toBeTrue();
    expect($admin->isType(UserType::Standard))->toBeFalse();
});
```

## Common Pitfalls & Troubleshooting

### Issue 1: Wrong Class Instantiation
**Problem**: `User::find(1)` returns base User instead of specific type
**Solution**: Always query through specific type classes or ensure discriminator is set

### Issue 2: Missing Type Attribute
**Problem**: STI not working, all records show as base class
**Solution**: Ensure `type` column is properly set in migrations and model attributes

### Issue 3: Query Performance Issues
**Problem**: Slow queries on large user tables
**Solution**: Add proper indexes on `type` column and commonly queried combinations

## Integration Points

### Connection to Other UMS-STI Components
- **Team Hierarchy (Task 3.0)**: Users have polymorphic relationships with teams
- **Permission System (Task 4.0)**: Type-specific permission behaviors
- **GDPR Compliance (Task 5.0)**: Type-specific data retention rules
- **FilamentPHP Interface (Task 6.0)**: Type-aware admin forms

## Further Reading & Resources

### Package Documentation
- [tightenco/parental Documentation](https://github.com/tightenco/parental)
- [Laravel Eloquent Inheritance](https://laravel.com/docs/eloquent)

### Design Patterns
- [Single Table Inheritance Pattern](https://martinfowler.com/eaaCatalog/singleTableInheritance.html)
- [Polymorphic Relationships in Laravel](https://laravel.com/docs/eloquent-relationships#polymorphic-relationships)

## References and Citations

### Primary Sources
- [Laravel 12.x Eloquent ORM](https://laravel.com/docs/12.x/eloquent)
- [Laravel 12.x Authentication](https://laravel.com/docs/12.x/authentication)
- [tightenco/parental Documentation](https://github.com/tightenco/parental)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)

### Secondary Sources
- [Single Table Inheritance Pattern](https://martinfowler.com/eaaCatalog/singleTableInheritance.html)
- [Laravel Model States by Spatie](https://spatie.be/docs/laravel-model-states)
- [Laravel Permission by Spatie](https://spatie.be/docs/laravel-permission)
- [Polymorphic Relationships in Laravel](https://laravel.com/docs/12.x/eloquent-relationships#polymorphic-relationships)

### Related UMS-STI Documentation
- [User Type Implementations](02-user-type-implementations.md) - Next implementation step
- [Polymorphic Relationships](03-polymorphic-relationships.md) - Extended user data patterns
- [State Management Patterns](04-state-management-patterns.md) - User state workflows
- [Permission Isolation Design](../04-permission-system/02-permission-isolation-design.md) - Security integration
- [Unit Testing Strategies](../08-testing-suite/01-unit-testing-strategies.md) - STI testing patterns
- [PRD Requirements](../../prd-UMS-STI.md) - User type specifications (REQ-003)
- [Decision Log](../../decision-log-UMS-STI.md) - STI architectural decisions (DECISION-001)

### Laravel 12.x Compatibility Notes
- Enhanced API authentication with Laravel Sanctum integration
- Improved factory patterns for STI models
- Updated testing utilities with Pest PHP
- Enhanced enum support for user types and states
- Optimized polymorphic relationship handling

---

**Next Steps**: Proceed to [User Type Implementations](02-user-type-implementations.md) to dive deeper into each user type's specific behaviors and relationships.
