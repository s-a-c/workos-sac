# 8. Testing Strategy

## 8.1. Testing Overview

This section outlines comprehensive testing strategies for the STI User model implementation, covering unit tests, feature tests, integration tests, and FilamentPHP v4-specific testing.

## 8.2. Unit Tests for STI Models

### 8.2.1. Base User Model Tests

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\StandardUser;
use App\States\User\ActiveState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_ulid_on_creation(): void
    {
        $user = StandardUser::factory()->create();
        
        $this->assertNotNull($user->ulid);
        $this->assertEquals(26, strlen($user->ulid));
        $this->assertTrue(User::isValidUlid($user->ulid));
    }

    /** @test */
    public function it_generates_slug_from_name(): void
    {
        $user = StandardUser::factory()->create(['name' => 'John Doe']);
        
        $this->assertEquals('john-doe', $user->slug);
    }

    /** @test */
    public function it_ensures_unique_slugs(): void
    {
        StandardUser::factory()->create(['name' => 'John Doe']);
        $user2 = StandardUser::factory()->create(['name' => 'John Doe']);
        
        $this->assertEquals('john-doe-1', $user2->slug);
    }

    /** @test */
    public function it_uses_ulid_for_route_key(): void
    {
        $user = StandardUser::factory()->create();
        
        $this->assertEquals('ulid', $user->getRouteKeyName());
        $this->assertEquals($user->ulid, $user->getRouteKey());
    }

    /** @test */
    public function it_scopes_active_users(): void
    {
        StandardUser::factory()->create(['is_active' => true]);
        StandardUser::factory()->create(['is_active' => false]);
        
        $activeUsers = StandardUser::active()->get();
        
        $this->assertCount(1, $activeUsers);
        $this->assertTrue($activeUsers->first()->is_active);
    }

    /** @test */
    public function it_scopes_users_by_role(): void
    {
        StandardUser::factory()->create(['role' => UserRole::User]);
        StandardUser::factory()->create(['role' => UserRole::User]);
        StandardUser::factory()->create(['role' => UserRole::Moderator]);
        
        $users = StandardUser::role(UserRole::User)->get();
        
        $this->assertCount(2, $users);
        $users->each(fn($user) => $this->assertEquals(UserRole::User, $user->role));
    }

    /** @test */
    public function it_checks_user_role(): void
    {
        $user = StandardUser::factory()->create(['role' => UserRole::User]);
        
        $this->assertTrue($user->hasRole(UserRole::User));
        $this->assertFalse($user->hasRole(UserRole::Admin));
    }

    /** @test */
    public function it_returns_display_name(): void
    {
        $user = StandardUser::factory()->create(['name' => 'John Doe']);
        $this->assertEquals('John Doe', $user->display_name);
        
        $userWithoutName = StandardUser::factory()->create(['name' => null, 'email' => 'john@example.com']);
        $this->assertEquals('john@example.com', $userWithoutName->display_name);
    }
}
```

### 8.2.2. STI Subclass Tests

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\StandardUser;
use App\Models\Guest;
use App\States\User\ActiveState;
use App\States\User\GuestState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_admin_with_correct_defaults(): void
    {
        $admin = Admin::factory()->create();
        
        $this->assertEquals('admin', $admin->type);
        $this->assertEquals(UserRole::Admin, $admin->role);
        $this->assertEquals(1, $admin->admin_level);
        $this->assertInstanceOf(ActiveState::class, $admin->state);
    }

    /** @test */
    public function it_manages_permissions(): void
    {
        $admin = Admin::factory()->create(['permissions' => ['manage_users']]);
        
        $this->assertTrue($admin->hasPermission('manage_users'));
        $this->assertFalse($admin->hasPermission('manage_system'));
        
        $admin->grantPermission('manage_system');
        $this->assertTrue($admin->hasPermission('manage_system'));
        
        $admin->revokePermission('manage_users');
        $this->assertFalse($admin->hasPermission('manage_users'));
    }

    /** @test */
    public function it_checks_admin_actions(): void
    {
        $admin = Admin::factory()->create(['permissions' => ['manage_users']]);
        
        $this->assertTrue($admin->canPerformAction('manage_users'));
        $this->assertTrue($admin->canPerformAction('view_admin_panel'));
        $this->assertFalse($admin->canPerformAction('invalid_action'));
    }

    /** @test */
    public function it_returns_dashboard_data(): void
    {
        $admin = Admin::factory()->create([
            'admin_level' => 3,
            'department' => 'IT',
            'permissions' => ['manage_users'],
        ]);
        
        $dashboardData = $admin->getDashboardData();
        
        $this->assertEquals(3, $dashboardData['admin_level']);
        $this->assertEquals('IT', $dashboardData['department']);
        $this->assertEquals(['manage_users'], $dashboardData['permissions']);
    }
}

class GuestTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_guest_with_correct_defaults(): void
    {
        $guest = Guest::factory()->create();
        
        $this->assertEquals('guest', $guest->type);
        $this->assertEquals(UserRole::Guest, $guest->role);
        $this->assertFalse($guest->is_active);
        $this->assertInstanceOf(GuestState::class, $guest->state);
        $this->assertNotNull($guest->expires_at);
    }

    /** @test */
    public function it_checks_expiration(): void
    {
        $activeGuest = Guest::factory()->create(['expires_at' => now()->addDays(1)]);
        $expiredGuest = Guest::factory()->create(['expires_at' => now()->subDays(1)]);
        
        $this->assertFalse($activeGuest->isExpired());
        $this->assertTrue($expiredGuest->isExpired());
    }

    /** @test */
    public function it_extends_session(): void
    {
        $guest = Guest::factory()->create(['expires_at' => now()->addDays(1)]);
        $originalExpiry = $guest->expires_at;
        
        $guest->extendSession(15);
        
        $this->assertTrue($guest->expires_at->greaterThan($originalExpiry));
    }

    /** @test */
    public function it_tracks_activity(): void
    {
        $guest = Guest::factory()->create();
        
        $guest->trackActivity('page_view', ['url' => '/products']);
        
        $this->assertNotEmpty($guest->tracking_data);
        $this->assertEquals('page_view', $guest->tracking_data[0]['action']);
        $this->assertEquals('/products', $guest->tracking_data[0]['data']['url']);
    }

    /** @test */
    public function it_converts_to_standard_user(): void
    {
        $guest = Guest::factory()->create([
            'tracking_data' => [['action' => 'page_view', 'data' => ['url' => '/products']]],
        ]);
        
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];
        
        $standardUser = $guest->convertToUser($userData);
        
        $this->assertInstanceOf(StandardUser::class, $standardUser);
        $this->assertEquals('John Doe', $standardUser->name);
        $this->assertEquals('john@example.com', $standardUser->email);
        $this->assertNotNull($standardUser->profile_data['guest_data']);
    }
}
```

## 8.3. State Management Tests

### 8.3.1. State Transition Tests

```php
<?php

namespace Tests\Unit\States;

use App\Models\StandardUser;
use App\States\User\ActiveState;
use App\States\User\InactiveState;
use App\States\User\PendingState;
use App\States\User\SuspendedState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_starts_in_pending_state(): void
    {
        $user = StandardUser::factory()->create(['state' => PendingState::class]);
        
        $this->assertInstanceOf(PendingState::class, $user->state);
        $this->assertEquals('pending', $user->state::$name);
    }

    /** @test */
    public function it_transitions_from_pending_to_active(): void
    {
        $user = StandardUser::factory()->create(['state' => PendingState::class]);
        
        $this->assertTrue($user->state->canTransitionTo(ActiveState::class));
        
        $user->state = new ActiveState($user);
        $user->save();
        
        $this->assertInstanceOf(ActiveState::class, $user->fresh()->state);
    }

    /** @test */
    public function it_prevents_invalid_transitions(): void
    {
        $user = StandardUser::factory()->create(['state' => SuspendedState::class]);
        
        $this->assertFalse($user->state->canTransitionTo(PendingState::class));
    }

    /** @test */
    public function it_provides_state_information(): void
    {
        $user = StandardUser::factory()->create(['state' => ActiveState::class]);
        
        $this->assertEquals('Active', $user->state->getDisplayName());
        $this->assertEquals('success', $user->state->getColor());
        $this->assertTrue($user->state->canLogin());
        $this->assertContains('login', $user->state->getAllowedActions());
    }
}
```

## 8.4. Feature Tests

### 8.4.1. User Registration and Authentication

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\StandardUser;
use App\States\User\PendingState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_registers_new_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        
        $response->assertRedirect('/dashboard');
        
        $user = StandardUser::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertInstanceOf(PendingState::class, $user->state);
        $this->assertNotNull($user->ulid);
        $this->assertNotNull($user->slug);
    }

    /** @test */
    public function it_prevents_duplicate_email_registration(): void
    {
        StandardUser::factory()->create(['email' => 'john@example.com']);
        
        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        
        $response->assertSessionHasErrors('email');
    }
}
```

### 8.4.2. API Tests

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\StandardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_user_data_via_api(): void
    {
        $user = StandardUser::factory()->create();
        Sanctum::actingAs($user);
        
        $response = $this->getJson("/api/users/{$user->ulid}");
        
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                    'slug',
                    'is_active',
                    'created_at',
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->ulid,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
    }

    /** @test */
    public function it_creates_user_via_api(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin);
        
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'user',
        ];
        
        $response = $this->postJson('/api/users', $userData);
        
        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ]
            ]);
        
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'type' => 'standard_user',
        ]);
    }

    /** @test */
    public function it_prevents_unauthorized_user_creation(): void
    {
        $user = StandardUser::factory()->create();
        Sanctum::actingAs($user);
        
        $response = $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);
        
        $response->assertForbidden();
    }
}
```

## 8.5. Integration Tests

### 8.5.1. STI Query Tests

```php
<?php

namespace Tests\Integration;

use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StiQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_queries_all_user_types(): void
    {
        StandardUser::factory()->count(3)->create();
        Admin::factory()->count(2)->create();
        Guest::factory()->count(1)->create();
        
        $allUsers = User::all();
        $this->assertCount(6, $allUsers);
        
        $standardUsers = StandardUser::all();
        $this->assertCount(3, $standardUsers);
        
        $admins = Admin::all();
        $this->assertCount(2, $admins);
        
        $guests = Guest::all();
        $this->assertCount(1, $guests);
    }

    /** @test */
    public function it_maintains_type_integrity_in_queries(): void
    {
        StandardUser::factory()->create();
        Admin::factory()->create();
        
        $standardUsers = StandardUser::all();
        $admins = Admin::all();
        
        $standardUsers->each(function ($user) {
            $this->assertInstanceOf(StandardUser::class, $user);
            $this->assertEquals('standard_user', $user->type);
        });
        
        $admins->each(function ($user) {
            $this->assertInstanceOf(Admin::class, $user);
            $this->assertEquals('admin', $user->type);
        });
    }

    /** @test */
    public function it_handles_polymorphic_relationships(): void
    {
        $user = StandardUser::factory()->create();
        $user->setStatus('verified', 'Email verified');
        
        $this->assertCount(1, $user->statuses);
        $this->assertEquals('verified', $user->latestStatus()->name);
    }
}
```

---

**Next**: [FilamentPHP v4 Integration](090-filament-integration.md) - Admin panel integration for STI models.
