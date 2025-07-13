# Security Testing Guide

This guide covers comprehensive security testing strategies for the Chinook Filament admin panel, including authentication, authorization, input validation, and vulnerability assessment.

## Table of Contents

- [Overview](#overview)
- [Authentication Testing](#authentication-testing)
- [Authorization Testing](#authorization-testing)
- [Input Validation Testing](#input-validation-testing)
- [CSRF Protection Testing](#csrf-protection-testing)
- [Input Validation Testing](#input-validation-testing)
- [XSS Prevention Testing](#xss-prevention-testing)
- [Best Practices](#best-practices)

## Overview

Security testing ensures the Chinook admin panel is protected against common vulnerabilities and maintains proper access controls.

### Testing Objectives

- **Authentication**: Verify secure login and session management
- **Authorization**: Test role-based access controls
- **Input Validation**: Ensure proper sanitization and validation
- **Vulnerability Prevention**: Test against common attack vectors

## Authentication Testing

### Login Security Testing

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Test invalid password
        $response = $this->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_rate_limiting(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        // Attempt multiple failed logins
        for ($i = 0; $i < 6; $i++) {
            $this->post('/admin/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword'
            ]);
        }

        // Next attempt should be rate limited
        $response = $this->post('/admin/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_session_timeout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Simulate session timeout by manipulating session
        session()->put('last_activity', now()->subMinutes(121)->timestamp);

        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }
}
```

### Password Security Testing

```php
public function test_password_complexity_requirements(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    // Test weak password
    $response = $this->put('/admin/profile', [
        'current_password' => 'password',
        'password' => '123',
        'password_confirmation' => '123'
    ]);

    $response->assertSessionHasErrors(['password']);
}

public function test_password_history_prevention(): void
{
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword123')
    ]);
    $this->actingAs($user);

    // Try to reuse old password
    $response = $this->put('/admin/profile', [
        'current_password' => 'oldpassword123',
        'password' => 'oldpassword123',
        'password_confirmation' => 'oldpassword123'
    ]);

    $response->assertSessionHasErrors(['password']);
}
```

## Authorization Testing

### Role-Based Access Control Testing

```php
public function test_admin_access_restrictions(): void
{
    $adminUser = User::factory()->create();
    $adminUser->assignRole('Super Admin');

    $editorUser = User::factory()->create();
    $editorUser->assignRole('Editor');

    $guestUser = User::factory()->create();
    $guestUser->assignRole('Guest');

    // Test admin access
    $this->actingAs($adminUser);
    $response = $this->get('/admin/users');
    $response->assertSuccessful();

    // Test editor access (should be forbidden)
    $this->actingAs($editorUser);
    $response = $this->get('/admin/users');
    $response->assertForbidden();

    // Test guest access (should be forbidden)
    $this->actingAs($guestUser);
    $response = $this->get('/admin/users');
    $response->assertForbidden();
}

public function test_resource_level_permissions(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $user->givePermissionTo('view artists');
    $user->revokePermissionTo('delete artists');

    $this->actingAs($user);

    // Should be able to view
    $response = $this->get('/admin/artists');
    $response->assertSuccessful();

    // Should not be able to delete
    $artist = \App\Models\Artist::factory()->create();
    $response = $this->delete("/admin/artists/{$artist->id}");
    $response->assertForbidden();
}
```

## Input Validation Testing

### SQL Injection Prevention

```php
public function test_sql_injection_prevention(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    // Test SQL injection in search
    $maliciousInput = "'; DROP TABLE artists; --";
    
    $response = $this->get('/admin/artists?search=' . urlencode($maliciousInput));
    
    $response->assertSuccessful();
    
    // Verify table still exists
    $this->assertTrue(\Schema::hasTable('artists'));
}

public function test_parameterized_queries(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    // Test with potentially dangerous input
    $response = $this->post('/admin/artists', [
        'name' => "'; DELETE FROM artists WHERE '1'='1",
        'biography' => 'Test biography'
    ]);

    // Should either succeed with escaped input or fail validation
    $this->assertTrue(
        $response->isSuccessful() || $response->isRedirect()
    );
}
```

## CSRF Protection Testing

### CSRF Token Validation

```php
public function test_csrf_protection_on_forms(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    // Test POST without CSRF token
    $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/admin/artists', [
            'name' => 'Test Artist'
        ]);

    // Should fail due to missing CSRF token
    $response->assertStatus(419); // CSRF token mismatch
}

public function test_csrf_token_regeneration(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $initialToken = csrf_token();
    
    // Perform action that should regenerate token
    $this->post('/admin/logout');
    
    $newToken = csrf_token();
    
    $this->assertNotEquals($initialToken, $newToken);
}
```

## XSS Prevention Testing

### Output Escaping Testing

```php
public function test_xss_prevention_in_output(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    $maliciousScript = '<script>alert("XSS")</script>';
    
    $artist = \App\Models\Artist::factory()->create([
        'name' => $maliciousScript,
        'biography' => $maliciousScript
    ]);

    $response = $this->get("/admin/artists/{$artist->id}");
    
    // Script should be escaped, not executed
    $response->assertDontSee($maliciousScript, false); // false = don't escape
    $response->assertSee(htmlspecialchars($maliciousScript), false);
}

public function test_user_input_sanitization(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    $response = $this->post('/admin/artists', [
        'name' => '<script>alert("XSS")</script>Test Artist',
        'biography' => 'Test <b>biography</b> with <script>alert("XSS")</script> tags'
    ]);

    if ($response->isSuccessful() || $response->isRedirect()) {
        $artist = \App\Models\Artist::latest()->first();
        
        // Verify dangerous scripts are removed/escaped
        $this->assertStringNotContainsString('<script>', $artist->name);
        $this->assertStringNotContainsString('<script>', $artist->biography);
    }
}
```

## File Upload Security Testing

### File Upload Validation

```php
public function test_file_upload_type_validation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    // Test malicious file upload
    $maliciousFile = \Illuminate\Http\UploadedFile::fake()->create('malicious.php', 100);
    
    $response = $this->post('/admin/artists', [
        'name' => 'Test Artist',
        'avatar' => $maliciousFile
    ]);

    $response->assertSessionHasErrors(['avatar']);
}

public function test_file_size_limits(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');
    $this->actingAs($user);

    // Test oversized file
    $largeFile = \Illuminate\Http\UploadedFile::fake()->create('large.jpg', 10000); // 10MB
    
    $response = $this->post('/admin/artists', [
        'name' => 'Test Artist',
        'avatar' => $largeFile
    ]);

    $response->assertSessionHasErrors(['avatar']);
}
```

## Session Security Testing

### Session Hijacking Prevention

```php
public function test_session_regeneration_on_login(): void
{
    $user = User::factory()->create([
        'password' => Hash::make('password123')
    ]);

    $initialSessionId = session()->getId();
    
    $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password123'
    ]);

    $newSessionId = session()->getId();
    
    $this->assertNotEquals($initialSessionId, $newSessionId);
}

public function test_concurrent_session_handling(): void
{
    $user = User::factory()->create();
    
    // Simulate multiple sessions for same user
    $session1 = $this->actingAs($user);
    $session2 = $this->actingAs($user);
    
    // Both sessions should be valid initially
    $response1 = $session1->get('/admin');
    $response2 = $session2->get('/admin');
    
    $response1->assertSuccessful();
    $response2->assertSuccessful();
}
```

## Best Practices

### Security Testing Strategy

1. **Regular Security Audits**: Conduct periodic security assessments
2. **Automated Testing**: Integrate security tests into CI/CD pipeline
3. **Penetration Testing**: Perform regular penetration testing
4. **Vulnerability Scanning**: Use automated vulnerability scanners

### Common Vulnerabilities to Test

1. **OWASP Top 10**: Test against common web vulnerabilities
2. **Authentication Bypass**: Test for authentication weaknesses
3. **Privilege Escalation**: Verify proper access controls
4. **Data Exposure**: Test for information disclosure

### Security Monitoring

1. **Audit Logging**: Log security-relevant events
2. **Intrusion Detection**: Monitor for suspicious activities
3. **Error Handling**: Ensure errors don't expose sensitive information
4. **Security Headers**: Verify proper security headers are set

---

## Related Documentation

- **[RBAC Testing](100-rbac-testing.md)** - Role-based access control testing
- **[Authentication Testing](090-auth-testing.md)** - Authentication mechanisms
- **[API Testing](110-api-testing.md)** - API security testing

---

## Navigation

**← Previous:** [Accessibility Testing](150-accessibility-testing.md)

**Next →** [Security Testing](160-security-testing.md)
