# STI Models TDD Implementation for UMS-STI

## Executive Summary

This guide demonstrates how to implement Single Table Inheritance (STI) models using comprehensive Test-Driven Development for UMS-STI. It covers testing user type behaviors, state management, polymorphic relationships, and business logic validation through a rigorous TDD approach that ensures 95% test coverage and robust architecture.

## Learning Objectives

After completing this guide, you will:
- Implement STI models using test-first development
- Create comprehensive tests for user type behaviors and state transitions
- Validate polymorphic relationships through TDD
- Test business logic and type-specific functionality
- Ensure performance and security requirements through testing

## Prerequisites

- Completed [01-tdd-environment-setup.md](01-tdd-environment-setup.md)
- Completed [02-database-tdd-approach.md](02-database-tdd-approach.md)
- Understanding of Laravel STI patterns and tightenco/parental package
- Knowledge of state management with spatie/laravel-model-states

## TDD Strategy for STI Models

### 1. Base User Model TDD

#### Step 1: Write Base User Tests

Create `tests/Unit/Models/User/BaseUserTest.php`:

```php
<?php

namespace Tests\Unit\Models\User;

use Tests\TestCase;
use App\Models\User;
use App\Models\StandardUser;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\SystemUser;
use App\Enums\UserType;
use App\Enums\UserState;
use Illuminate\Support\Str;

class BaseUserTest extends TestCase
{
    /** @test */
    public function it_generates_ulid_on_creation()
    {
        $user = User::factory()->make();
        
        $this->assertNotNull($user->ulid);
        $this->assertTrue(Str::isUlid($user->ulid));
    }

    /** @test */
    public function it_has_default_state_active()
    {
        $user = User::factory()->make();
        
        $this->assertEquals(UserState::Active, $user->state);
    }

    /** @test */
    public function it_casts_type_to_enum()
    {
        $user = User::factory()->make(['type' => 'standard']);
        
        $this->assertInstanceOf(UserType::class, $user->type);
        $this->assertEquals(UserType::Standard, $user->type);
    }

    /** @test */
    public function it_casts_state_to_enum()
    {
        $user = User::factory()->make(['state' => 'inactive']);
        
        $this->assertInstanceOf(UserState::class, $user->state);
        $this->assertEquals(UserState::Inactive, $user->state);
    }

    /** @test */
    public function it_has_user_stamps_tracking()
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);
        
        $user = User::factory()->create();
        
        $this->assertEquals($creator->id, $user->created_by);
        $this->assertNotNull($user->created_at);
    }

    /** @test */
    public function it_returns_correct_sti_instance_based_on_type()
    {
        $standardUser = User::factory()->create(['type' => 'standard']);
        $admin = User::factory()->create(['type' => 'admin']);
        $guest = User::factory()->create(['type' => 'guest']);
        $system = User::factory()->create(['type' => 'system']);
        
        $retrievedStandard = User::find($standardUser->id);
        $retrievedAdmin = User::find($admin->id);
        $retrievedGuest = User::find($guest->id);
        $retrievedSystem = User::find($system->id);
        
        $this->assertInstanceOf(StandardUser::class, $retrievedStandard);
        $this->assertInstanceOf(Admin::class, $retrievedAdmin);
        $this->assertInstanceOf(Guest::class, $retrievedGuest);
        $this->assertInstanceOf(SystemUser::class, $retrievedSystem);
    }
}
```

#### Step 2: Implement Base User Model

Create the base User model that makes tests pass:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tightenco\Parental\HasParent;
use Spatie\ModelStates\HasStates;
use Wildside\Userstamps\Userstamps;
use App\Enums\UserType;
use App\Enums\UserState;
use App\States\User\UserStateTransition;
use Illuminate\Support\Str;

abstract class User extends Authenticatable
{
    use HasFactory, Notifiable, HasParent, HasStates, Userstamps;

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'state',
        'ulid',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'type' => UserType::class,
        'state' => UserState::class,
        'settings' => 'array',
    ];

    protected $attributes = [
        'state' => UserState::Active,
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (empty($user->ulid)) {
                $user->ulid = (string) Str::ulid();
            }
        });
    }

    // STI Child Types
    public function getChildTypes(): array
    {
        return [
            'standard' => StandardUser::class,
            'admin' => Admin::class,
            'guest' => Guest::class,
            'system' => SystemUser::class,
        ];
    }

    // State Management
    protected $states = [
        'state' => UserStateTransition::class,
    ];

    // Abstract methods to be implemented by child classes
    abstract public function getPermissions(): array;
    abstract public function canAccessAdminPanel(): bool;
    abstract public function getMaxSessionDuration(): int;
}
```

### 2. User Type Implementation TDD

#### Step 1: Write StandardUser Tests

Create `tests/Unit/Models/User/StandardUserTest.php`:

```php
<?php

namespace Tests\Unit\Models\User;

use Tests\TestCase;
use App\Models\StandardUser;
use App\Models\Team;
use App\Enums\UserType;

class StandardUserTest extends TestCase
{
    /** @test */
    public function it_has_correct_type()
    {
        $user = StandardUser::factory()->make();
        
        $this->assertEquals(UserType::Standard, $user->type);
        $this->assertEquals('standard', $user->type->value);
    }

    /** @test */
    public function it_cannot_access_admin_panel()
    {
        $user = StandardUser::factory()->create();
        
        $this->assertFalse($user->canAccessAdminPanel());
    }

    /** @test */
    public function it_has_24_hour_session_duration()
    {
        $user = StandardUser::factory()->create();
        
        $this->assertEquals(24 * 60 * 60, $user->getMaxSessionDuration()); // 24 hours in seconds
    }

    /** @test */
    public function it_has_basic_permissions()
    {
        $user = StandardUser::factory()->create();
        
        $permissions = $user->getPermissions();
        
        $this->assertContains('profile.view', $permissions);
        $this->assertContains('profile.edit', $permissions);
        $this->assertContains('teams.view', $permissions);
        $this->assertNotContains('users.manage', $permissions);
    }

    /** @test */
    public function it_can_join_teams()
    {
        $user = StandardUser::factory()->create();
        $team = Team::factory()->create();
        
        $result = $user->joinTeam($team, 'member');
        
        $this->assertTrue($result);
        $this->assertTrue($user->teams->contains($team));
    }

    /** @test */
    public function it_can_switch_active_team()
    {
        $user = StandardUser::factory()->create();
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $user->joinTeam($team1, 'member');
        $user->joinTeam($team2, 'member');
        
        $user->setActiveTeam($team2);
        
        $this->assertTrue($user->activeTeam->is($team2));
    }

    /** @test */
    public function it_validates_profile_update_permissions()
    {
        $user = StandardUser::factory()->create();
        
        $this->assertTrue($user->canUpdateProfile());
        $this->assertTrue($user->canUpdateField('name'));
        $this->assertTrue($user->canUpdateField('bio'));
        $this->assertFalse($user->canUpdateField('email')); // Requires verification
    }
}
```

#### Step 2: Implement StandardUser Model

```php
<?php

namespace App\Models;

use App\Enums\UserType;

class StandardUser extends User
{
    protected $attributes = [
        'type' => UserType::Standard,
    ];

    public function getPermissions(): array
    {
        return [
            'profile.view',
            'profile.edit',
            'teams.view',
            'teams.join',
            'reports.basic',
        ];
    }

    public function canAccessAdminPanel(): bool
    {
        return false;
    }

    public function getMaxSessionDuration(): int
    {
        return 24 * 60 * 60; // 24 hours
    }

    public function joinTeam(Team $team, string $role = 'member'): bool
    {
        if ($team->allowsSelfRegistration() || $this->hasInvitation($team)) {
            $team->addMember($this, $role);
            return true;
        }
        
        return false;
    }

    public function setActiveTeam(Team $team): void
    {
        if ($this->teams->contains($team)) {
            $this->update(['active_team_id' => $team->id]);
        }
    }

    public function canUpdateProfile(): bool
    {
        return true;
    }

    public function canUpdateField(string $field): bool
    {
        $allowedFields = ['name', 'bio', 'avatar', 'settings'];
        return in_array($field, $allowedFields);
    }

    private function hasInvitation(Team $team): bool
    {
        return $this->invitations()
            ->where('team_id', $team->id)
            ->where('status', 'pending')
            ->exists();
    }
}
```

#### Step 3: Write Admin User Tests

Create `tests/Unit/Models/User/AdminUserTest.php`:

```php
<?php

namespace Tests\Unit\Models\User;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\StandardUser;
use App\Models\Team;
use App\Enums\UserType;

class AdminUserTest extends TestCase
{
    /** @test */
    public function it_has_correct_type()
    {
        $admin = Admin::factory()->make();
        
        $this->assertEquals(UserType::Admin, $admin->type);
        $this->assertEquals('admin', $admin->type->value);
    }

    /** @test */
    public function it_can_access_admin_panel()
    {
        $admin = Admin::factory()->create();
        
        $this->assertTrue($admin->canAccessAdminPanel());
    }

    /** @test */
    public function it_has_8_hour_session_duration()
    {
        $admin = Admin::factory()->create();
        
        $this->assertEquals(8 * 60 * 60, $admin->getMaxSessionDuration()); // 8 hours in seconds
    }

    /** @test */
    public function it_has_elevated_permissions()
    {
        $admin = Admin::factory()->create();
        
        $permissions = $admin->getPermissions();
        
        $this->assertContains('users.manage', $permissions);
        $this->assertContains('teams.manage', $permissions);
        $this->assertContains('permissions.assign', $permissions);
        $this->assertContains('reports.advanced', $permissions);
    }

    /** @test */
    public function it_can_manage_users()
    {
        $admin = Admin::factory()->create();
        $user = StandardUser::factory()->create();
        
        $this->assertTrue($admin->canManageUser($user));
        $this->assertTrue($admin->canSuspendUser($user));
        $this->assertTrue($admin->canActivateUser($user));
    }

    /** @test */
    public function it_can_create_teams()
    {
        $admin = Admin::factory()->create();
        
        $team = $admin->createTeam([
            'name' => 'New Team',
            'type' => 'department',
        ]);
        
        $this->assertInstanceOf(Team::class, $team);
        $this->assertEquals('New Team', $team->name);
        $this->assertEquals($admin->id, $team->created_by);
    }

    /** @test */
    public function it_can_assign_permissions()
    {
        $admin = Admin::factory()->create();
        $user = StandardUser::factory()->create();
        $team = Team::factory()->create();
        
        $result = $admin->assignUserToTeam($user, $team, 'leader');
        
        $this->assertTrue($result);
        $this->assertTrue($user->teams->contains($team));
        $this->assertEquals('leader', $user->getRoleInTeam($team));
    }

    /** @test */
    public function it_can_impersonate_users()
    {
        $admin = Admin::factory()->create();
        $user = StandardUser::factory()->create();
        
        $this->assertTrue($admin->canImpersonate($user));
        
        $impersonationToken = $admin->startImpersonation($user);
        
        $this->assertNotNull($impersonationToken);
        $this->assertDatabaseHas('impersonation_logs', [
            'admin_id' => $admin->id,
            'user_id' => $user->id,
            'token' => $impersonationToken,
        ]);
    }
}
```

### 3. State Management TDD

#### Step 1: Write State Transition Tests

Create `tests/Unit/Models/User/UserStateTest.php`:

```php
<?php

namespace Tests\Unit\Models\User;

use Tests\TestCase;
use App\Models\StandardUser;
use App\Enums\UserState;
use App\States\User\Active;
use App\States\User\Inactive;
use App\States\User\Suspended;
use App\States\User\Pending;
use Spatie\ModelStates\Exceptions\InvalidConfig;

class UserStateTest extends TestCase
{
    /** @test */
    public function it_starts_in_active_state()
    {
        $user = StandardUser::factory()->create();
        
        $this->assertEquals(UserState::Active, $user->state);
        $this->assertInstanceOf(Active::class, $user->state);
    }

    /** @test */
    public function it_can_transition_from_active_to_inactive()
    {
        $user = StandardUser::factory()->create(['state' => UserState::Active]);
        
        $user->state->transitionTo(Inactive::class);
        
        $this->assertEquals(UserState::Inactive, $user->fresh()->state);
    }

    /** @test */
    public function it_can_transition_from_active_to_suspended()
    {
        $user = StandardUser::factory()->create(['state' => UserState::Active]);
        
        $user->state->transitionTo(Suspended::class);
        
        $this->assertEquals(UserState::Suspended, $user->fresh()->state);
    }

    /** @test */
    public function it_cannot_transition_from_suspended_to_inactive()
    {
        $user = StandardUser::factory()->create(['state' => UserState::Suspended]);
        
        $this->expectException(InvalidConfig::class);
        
        $user->state->transitionTo(Inactive::class);
    }

    /** @test */
    public function it_logs_state_transitions()
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin);
        
        $user = StandardUser::factory()->create(['state' => UserState::Active]);
        
        $user->state->transitionTo(Suspended::class);
        
        $this->assertDatabaseHas('state_transitions', [
            'model_type' => StandardUser::class,
            'model_id' => $user->id,
            'from' => 'active',
            'to' => 'suspended',
            'created_by' => $admin->id,
        ]);
    }

    /** @test */
    public function it_automatically_transitions_to_inactive_after_90_days()
    {
        $user = StandardUser::factory()->create([
            'state' => UserState::Active,
            'last_login_at' => now()->subDays(91),
        ]);
        
        // Simulate automated state check
        $user->checkInactivityTransition();
        
        $this->assertEquals(UserState::Inactive, $user->fresh()->state);
    }

    /** @test */
    public function active_users_can_login()
    {
        $user = StandardUser::factory()->create(['state' => UserState::Active]);
        
        $this->assertTrue($user->canLogin());
    }

    /** @test */
    public function suspended_users_cannot_login()
    {
        $user = StandardUser::factory()->create(['state' => UserState::Suspended]);
        
        $this->assertFalse($user->canLogin());
    }

    /** @test */
    public function inactive_users_can_be_reactivated()
    {
        $user = StandardUser::factory()->create(['state' => UserState::Inactive]);
        
        $user->state->transitionTo(Active::class);
        
        $this->assertEquals(UserState::Active, $user->fresh()->state);
        $this->assertTrue($user->canLogin());
    }
}
```

### 4. Polymorphic Relationships TDD

#### Step 1: Write Profile Relationship Tests

Create `tests/Unit/Models/User/UserProfileTest.php`:

```php
<?php

namespace Tests\Unit\Models\User;

use Tests\TestCase;
use App\Models\StandardUser;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\UserProfile;
use App\Models\StandardUserProfile;
use App\Models\AdminProfile;
use App\Models\GuestProfile;

class UserProfileTest extends TestCase
{
    /** @test */
    public function standard_user_has_standard_profile()
    {
        $user = StandardUser::factory()->create();
        
        $profile = $user->profile;
        
        $this->assertInstanceOf(StandardUserProfile::class, $profile);
        $this->assertEquals($user->id, $profile->user_id);
    }

    /** @test */
    public function admin_has_admin_profile()
    {
        $admin = Admin::factory()->create();
        
        $profile = $admin->profile;
        
        $this->assertInstanceOf(AdminProfile::class, $profile);
        $this->assertEquals($admin->id, $profile->user_id);
    }

    /** @test */
    public function guest_has_guest_profile()
    {
        $guest = Guest::factory()->create();
        
        $profile = $guest->profile;
        
        $this->assertInstanceOf(GuestProfile::class, $profile);
        $this->assertEquals($guest->id, $profile->user_id);
    }

    /** @test */
    public function profile_is_created_automatically()
    {
        $user = StandardUser::factory()->create();
        
        $this->assertNotNull($user->profile);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'user_type' => StandardUser::class,
        ]);
    }

    /** @test */
    public function profile_is_deleted_with_user()
    {
        $user = StandardUser::factory()->create();
        $profileId = $user->profile->id;
        
        $user->delete();
        
        $this->assertDatabaseMissing('user_profiles', ['id' => $profileId]);
    }

    /** @test */
    public function standard_profile_has_basic_fields()
    {
        $user = StandardUser::factory()->create();
        $profile = $user->profile;
        
        $profile->update([
            'bio' => 'Test bio',
            'avatar_url' => 'https://example.com/avatar.jpg',
            'preferences' => ['theme' => 'dark'],
        ]);
        
        $this->assertEquals('Test bio', $profile->bio);
        $this->assertEquals('https://example.com/avatar.jpg', $profile->avatar_url);
        $this->assertEquals(['theme' => 'dark'], $profile->preferences);
    }

    /** @test */
    public function admin_profile_has_additional_fields()
    {
        $admin = Admin::factory()->create();
        $profile = $admin->profile;
        
        $profile->update([
            'department' => 'IT',
            'access_level' => 'senior',
            'emergency_contact' => 'john@example.com',
        ]);
        
        $this->assertEquals('IT', $profile->department);
        $this->assertEquals('senior', $profile->access_level);
        $this->assertEquals('john@example.com', $profile->emergency_contact);
    }
}
```

### 5. Performance and Security Testing

#### Step 1: Write Performance Tests for STI

Create `tests/Performance/StiModelPerformanceTest.php`:

```php
<?php

namespace Tests\Performance;

use Tests\Performance\PerformanceTestCase;
use App\Models\StandardUser;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\User;

class StiModelPerformanceTest extends PerformanceTestCase
{
    /** @test */
    public function sti_instantiation_is_performant()
    {
        // Create mixed user types
        StandardUser::factory()->count(100)->create();
        Admin::factory()->count(20)->create();
        Guest::factory()->count(50)->create();
        
        $this->measurePerformance('sti_instantiation', function () {
            User::all(); // Should instantiate correct STI types
        });
        
        // STI instantiation should be under 100ms for 170 users
        $this->assertLessThan(100, $this->performanceMetrics['sti_instantiation']['execution_time_ms']);
    }

    /** @test */
    public function type_specific_queries_are_optimized()
    {
        StandardUser::factory()->count(1000)->create();
        Admin::factory()->count(50)->create();
        
        $this->measurePerformance('type_query', function () {
            StandardUser::where('state', 'active')->count();
        });
        
        // Type-specific queries should use indexes
        $this->assertLessThan(50, $this->performanceMetrics['type_query']['execution_time_ms']);
    }

    /** @test */
    public function polymorphic_profile_loading_is_efficient()
    {
        $users = StandardUser::factory()->count(100)->create();
        
        $this->measurePerformance('profile_loading', function () use ($users) {
            User::with('profile')->whereIn('id', $users->pluck('id'))->get();
        });
        
        // Eager loading profiles should be efficient
        $this->assertLessThan(100, $this->performanceMetrics['profile_loading']['execution_time_ms']);
    }

    /** @test */
    public function state_transitions_are_fast()
    {
        $users = StandardUser::factory()->count(50)->create();
        
        $this->measurePerformance('state_transitions', function () use ($users) {
            foreach ($users as $user) {
                $user->state->transitionTo(\App\States\User\Inactive::class);
            }
        });
        
        // State transitions should be under 200ms for 50 users
        $this->assertLessThan(200, $this->performanceMetrics['state_transitions']['execution_time_ms']);
    }
}
```

## TDD Workflow for STI Models

### 1. Red-Green-Refactor for Each User Type

```bash
# 1. RED: Write failing test for StandardUser
./vendor/bin/pest tests/Unit/Models/User/StandardUserTest.php::test_it_has_correct_type
# Test fails - StandardUser class doesn't exist

# 2. GREEN: Create minimal StandardUser class
# Implement just enough to make test pass

# 3. REFACTOR: Add business logic and optimize
# Implement full StandardUser functionality

# 4. REPEAT: For Admin, Guest, SystemUser
```

### 2. State Management TDD Cycle

```bash
# 1. Write state transition tests
./vendor/bin/pest tests/Unit/Models/User/UserStateTest.php
# Tests fail - state classes don't exist

# 2. Implement state classes and transitions
# Create Active, Inactive, Suspended, Pending states

# 3. Test automated transitions
./vendor/bin/pest tests/Unit/Models/User/UserStateTest.php::test_it_automatically_transitions_to_inactive_after_90_days
```

### 3. Performance Validation

```bash
# Run performance tests after each implementation
./vendor/bin/pest tests/Performance/StiModelPerformanceTest.php

# Monitor for performance regressions
composer run test-performance
```

## STI Testing Best Practices

### 1. Test Organization
- Separate test files for each user type
- Base tests for common functionality
- State-specific test suites
- Performance tests for STI patterns

### 2. Factory Patterns
- Type-specific factory states
- Realistic test data generation
- Profile relationship factories
- State transition factories

### 3. Assertion Patterns
- Type verification assertions
- Behavior validation assertions
- Permission checking assertions
- State transition assertions

## Success Criteria

- [ ] All user types implemented with TDD
- [ ] State management fully tested and functional
- [ ] Polymorphic relationships working correctly
- [ ] Performance requirements met (<100ms for STI operations)
- [ ] Security boundaries properly tested
- [ ] 95% test coverage for all STI models
- [ ] Business logic validated through comprehensive tests

## Next Steps

After completing STI models TDD:

1. **Validate Implementation**: Run all STI tests and ensure they pass
2. **Performance Baseline**: Confirm STI performance meets requirements
3. **Move to Hierarchy**: Follow [04-closure-table-tdd.md](04-closure-table-tdd.md)
4. **Integration Testing**: Test STI models with other system components

---

**Next Guide**: [04-closure-table-tdd.md](04-closure-table-tdd.md) - TDD for team hierarchy implementation  
**Estimated Time**: 8-10 hours for complete STI TDD implementation  
**Prerequisites**: Database TDD completed, understanding of STI patterns and state management
