# Authentication Testing Guide

This guide covers comprehensive authentication and authorization testing for the Chinook Filament admin panel, including
login/logout functionality, session management, and access control.

## Table of Contents

- [Overview](#overview)
- [Login and Logout Testing](#login-and-logout-testing)
- [Session Management Testing](#session-management-testing)
- [Password Security Testing](#password-security-testing)
- [Multi-Factor Authentication Testing](#multi-factor-authentication-testing)
- [Access Control Testing](#access-control-testing)
- [Security Testing](#security-testing)
- [Performance Testing](#performance-testing)
- [Integration Testing](#integration-testing)

## Overview

Authentication testing ensures that the Filament admin panel properly authenticates users, manages sessions securely,
and enforces access controls. This includes testing login flows, session handling, and security measures.

### Testing Objectives

- **Authentication**: Verify login/logout functionality works correctly
- **Session Security**: Test session management and timeout handling
- **Access Control**: Ensure proper authorization enforcement
- **Security**: Validate security measures and attack prevention
- **User Experience**: Test authentication flows and error handling

## Login and Logout Testing

### Basic Authentication Testing

```php
<?php

namespace Tests\Feature\ChinookAdmin\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('Admin');

        $response = $this->post('/chinook-admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/chinook-admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/chinook-admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/chinook-admin/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post('/chinook-admin/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/chinook-admin/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->post('/chinook-admin/login', []);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }
}
```

### Email Validation Testing

```php
public function test_login_validates_email_format(): void
{
    $response = $this->post('/chinook-admin/login', [
        'email' => 'invalid-email',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
}

public function test_login_is_case_insensitive_for_email(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    $response = $this->post('/chinook-admin/login', [
        'email' => 'ADMIN@TEST.COM',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/chinook-admin');
    $this->assertAuthenticatedAs($user);
}
```

### Logout Testing

```php
public function test_authenticated_user_can_logout(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    $response = $this->post('/chinook-admin/logout');

    $response->assertRedirect('/chinook-admin/login');
    $this->assertGuest();
}

public function test_logout_invalidates_session(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);
    
    // Get session ID before logout
    $sessionId = session()->getId();

    $this->post('/chinook-admin/logout');

    // Attempt to access protected route with old session
    $this->withSession(['_token' => $sessionId])
        ->get('/chinook-admin')
        ->assertRedirect('/chinook-admin/login');
}

public function test_guest_cannot_logout(): void
{
    $response = $this->post('/chinook-admin/logout');

    $response->assertRedirect('/chinook-admin/login');
}
```

## Session Management Testing

### Session Timeout Testing

```php
public function test_session_expires_after_inactivity(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    // Simulate session timeout by manipulating session timestamp
    session(['last_activity' => now()->subMinutes(121)->timestamp]);

    $response = $this->get('/chinook-admin');

    $response->assertRedirect('/chinook-admin/login');
    $this->assertGuest();
}

public function test_session_extends_on_activity(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    $initialTime = now()->timestamp;
    session(['last_activity' => $initialTime]);

    // Make a request to extend session
    $this->get('/chinook-admin/artists');

    $this->assertTrue(session('last_activity') > $initialTime);
}

public function test_remember_me_functionality(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    $response = $this->post('/chinook-admin/login', [
        'email' => 'admin@test.com',
        'password' => 'password123',
        'remember' => true,
    ]);

    $response->assertRedirect('/chinook-admin');
    
    // Check that remember token is set
    $user->refresh();
    $this->assertNotNull($user->remember_token);
    
    // Check that remember cookie is set
    $response->assertCookie('remember_web_' . sha1(config('app.key')));
}
```

### Concurrent Session Testing

```php
public function test_multiple_sessions_for_same_user(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    // First session
    $response1 = $this->post('/chinook-admin/login', [
        'email' => 'admin@test.com',
        'password' => 'password123',
    ]);

    $response1->assertRedirect('/chinook-admin');

    // Second session (different browser/device)
    $response2 = $this->withSession([])
        ->post('/chinook-admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

    $response2->assertRedirect('/chinook-admin');
    
    // Both sessions should be valid
    $this->actingAs($user)
        ->get('/chinook-admin')
        ->assertStatus(200);
}

public function test_session_invalidation_on_password_change(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    // Change password
    $user->update(['password' => Hash::make('newpassword')]);

    // Session should be invalidated
    $response = $this->get('/chinook-admin');

    $response->assertRedirect('/chinook-admin/login');
    $this->assertGuest();
}
```

## Password Security Testing

### Password Validation Testing

```php
public function test_password_meets_minimum_requirements(): void
{
    $response = $this->post('/chinook-admin/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '123', // Too short
        'password_confirmation' => '123',
    ]);

    $response->assertSessionHasErrors(['password']);
}

public function test_password_confirmation_required(): void
{
    $response = $this->post('/chinook-admin/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different',
    ]);

    $response->assertSessionHasErrors(['password']);
}

public function test_password_reset_functionality(): void
{
    $user = User::factory()->create(['email' => 'test@example.com']);

    // Request password reset
    $response = $this->post('/chinook-admin/forgot-password', [
        'email' => 'test@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    // Verify reset token is created
    $this->assertDatabaseHas('password_reset_tokens', [
        'email' => 'test@example.com',
    ]);
}
```

### Password Security Testing

```php
public function test_password_is_hashed(): void
{
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $this->assertNotEquals('password123', $user->password);
    $this->assertTrue(Hash::check('password123', $user->password));
}

public function test_old_password_required_for_change(): void
{
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->put('/chinook-admin/profile/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertSessionHasErrors(['current_password']);
}

public function test_password_change_success(): void
{
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->put('/chinook-admin/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertRedirect();
    
    $user->refresh();
    $this->assertTrue(Hash::check('newpassword123', $user->password));
}
```

## Multi-Factor Authentication Testing

### 2FA Setup Testing

```php
public function test_user_can_enable_two_factor_authentication(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->post('/chinook-admin/profile/two-factor-authentication');

    $response->assertRedirect();
    
    $user->refresh();
    $this->assertNotNull($user->two_factor_secret);
    $this->assertNotNull($user->two_factor_recovery_codes);
}

public function test_user_can_disable_two_factor_authentication(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(['code1', 'code2']),
    ])->save();

    $response = $this->actingAs($user)
        ->delete('/chinook-admin/profile/two-factor-authentication');

    $response->assertRedirect();
    
    $user->refresh();
    $this->assertNull($user->two_factor_secret);
    $this->assertNull($user->two_factor_recovery_codes);
}

public function test_two_factor_authentication_required_after_login(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');
    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
    ])->save();

    $response = $this->post('/chinook-admin/login', [
        'email' => 'admin@test.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/chinook-admin/two-factor-challenge');
}
```

### 2FA Challenge Testing

```php
public function test_valid_two_factor_code_allows_access(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    $google2fa = app('pragmarx.google2fa');
    $secret = $google2fa->generateSecretKey();
    $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

    $validCode = $google2fa->getCurrentOtp($secret);

    $response = $this->actingAs($user)
        ->withSession(['login.id' => $user->id])
        ->post('/chinook-admin/two-factor-challenge', [
            'code' => $validCode,
        ]);

    $response->assertRedirect('/chinook-admin');
}

public function test_invalid_two_factor_code_denies_access(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $user->forceFill(['two_factor_secret' => encrypt('secret')])->save();

    $response = $this->actingAs($user)
        ->withSession(['login.id' => $user->id])
        ->post('/chinook-admin/two-factor-challenge', [
            'code' => '000000',
        ]);

    $response->assertRedirect('/chinook-admin/two-factor-challenge');
    $response->assertSessionHasErrors(['code']);
}

public function test_recovery_code_allows_access(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $recoveryCodes = ['recovery-code-1', 'recovery-code-2'];
    $user->forceFill([
        'two_factor_recovery_codes' => encrypt($recoveryCodes),
    ])->save();

    $response = $this->actingAs($user)
        ->withSession(['login.id' => $user->id])
        ->post('/chinook-admin/two-factor-challenge', [
            'recovery_code' => 'recovery-code-1',
        ]);

    $response->assertRedirect('/chinook-admin');
    
    // Recovery code should be consumed
    $user->refresh();
    $remainingCodes = decrypt($user->two_factor_recovery_codes);
    $this->assertNotContains('recovery-code-1', $remainingCodes);
}
```

## Access Control Testing

### Route Protection Testing

```php
public function test_guest_cannot_access_admin_panel(): void
{
    $response = $this->get('/chinook-admin');

    $response->assertRedirect('/chinook-admin/login');
}

public function test_authenticated_user_without_role_cannot_access(): void
{
    $user = User::factory()->create();
    // No role assigned

    $response = $this->actingAs($user)
        ->get('/chinook-admin');

    $response->assertStatus(403);
}

public function test_user_with_admin_role_can_access(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->get('/chinook-admin');

    $response->assertStatus(200);
}

public function test_middleware_protects_all_admin_routes(): void
{
    $protectedRoutes = [
        '/chinook-admin/artists',
        '/chinook-admin/albums',
        '/chinook-admin/tracks',
        '/chinook-admin/customers',
        '/chinook-admin/employees',
    ];

    foreach ($protectedRoutes as $route) {
        $response = $this->get($route);
        $response->assertRedirect('/chinook-admin/login');
    }
}
```

### Permission-Based Access Testing

```php
public function test_user_needs_specific_permission_for_resource(): void
{
    $user = User::factory()->create();
    $user->assignRole('Guest'); // Role without artist permissions

    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists');

    $response->assertStatus(403);
}

public function test_user_with_permission_can_access_resource(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('view-artists');

    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200);
}
```

## Security Testing

### CSRF Protection Testing

```php
public function test_csrf_protection_on_login(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/chinook-admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

    // Should fail without CSRF token
    $response->assertStatus(419);
}

public function test_csrf_token_required_for_forms(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/chinook-admin/artists', [
            'name' => 'Test Artist',
            'country' => 'US',
        ]);

    $response->assertStatus(419);
}
```

### Rate Limiting Testing

```php
public function test_login_rate_limiting(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);

    // Attempt multiple failed logins
    for ($i = 0; $i < 6; $i++) {
        $this->post('/chinook-admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);
    }

    // Next attempt should be rate limited
    $response = $this->post('/chinook-admin/login', [
        'email' => 'admin@test.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(429); // Too Many Requests
}

public function test_successful_login_resets_rate_limit(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    // Attempt multiple failed logins
    for ($i = 0; $i < 4; $i++) {
        $this->post('/chinook-admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);
    }

    // Successful login should reset counter
    $response = $this->post('/chinook-admin/login', [
        'email' => 'admin@test.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/chinook-admin');
}
```

## Performance Testing

### Authentication Performance Testing

```php
public function test_login_performance(): void
{
    $user = User::factory()->create([
        'email' => 'admin@test.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    $startTime = microtime(true);

    $response = $this->post('/chinook-admin/login', [
        'email' => 'admin@test.com',
        'password' => 'password123',
    ]);

    $endTime = microtime(true);
    $loginTime = $endTime - $startTime;

    $response->assertRedirect('/chinook-admin');
    $this->assertLessThan(1.0, $loginTime, "Login took {$loginTime} seconds");
}

public function test_session_check_performance(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    $startTime = microtime(true);

    $response = $this->get('/chinook-admin');

    $endTime = microtime(true);
    $checkTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(0.5, $checkTime, "Session check took {$checkTime} seconds");
}
```

## Integration Testing

### Third-Party Authentication Testing

```php
public function test_oauth_login_integration(): void
{
    // Mock OAuth provider response
    $this->mock(\Laravel\Socialite\Contracts\Factory::class, function ($mock) {
        $mock->shouldReceive('driver->user')->andReturn((object) [
            'id' => '12345',
            'email' => 'user@example.com',
            'name' => 'Test User',
        ]);
    });

    $response = $this->get('/chinook-admin/auth/google/callback?code=test-code');

    $response->assertRedirect('/chinook-admin');
    $this->assertDatabaseHas('users', [
        'email' => 'user@example.com',
        'name' => 'Test User',
    ]);
}
```

## Related Documentation

- **[RBAC Testing](100-rbac-testing.md)** - Role-based access control testing
- **[Security Testing](160-security-testing.md)** - Security vulnerability testing
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[Browser Testing](140-browser-testing.md)** - End-to-end authentication testing

---

## Navigation

**← Previous:** [Action Testing](080-action-testing.md)

**Next →** [RBAC Testing](100-rbac-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
