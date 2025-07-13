---
owner: "[SECURITY_LEAD]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft"
version: "1.0.0"
target_audience: "Junior developers with 6 months-2 years experience"
framework_version: "Laravel 12.x"
security_framework: "OWASP Top 10"
compliance: "GDPR"
---

# Security Implementation Guide
## [PROJECT_NAME]

**Estimated Reading Time:** 35 minutes

## Overview

This guide provides comprehensive security implementation procedures for [PROJECT_NAME] using Laravel 12.x and FilamentPHP v4. It covers authentication, authorization, data protection, and compliance with OWASP Top 10 and GDPR requirements.

### Security Objectives
- **Defense in Depth**: Multiple layers of security controls
- **OWASP Compliance**: Address all OWASP Top 10 vulnerabilities
- **GDPR Compliance**: Implement privacy by design and data protection
- **Zero Trust**: Never trust, always verify approach
- **Continuous Monitoring**: Real-time security monitoring and alerting

### Security Principles
- **Least Privilege**: Users get minimum necessary permissions
- **Fail Secure**: System fails to a secure state
- **Defense in Depth**: Multiple security layers
- **Security by Design**: Security built into architecture
- **Continuous Validation**: Regular security assessments

## Authentication Security

### Laravel Sanctum Implementation

#### Secure Token Management
```php
<?php
// config/sanctum.php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    'expiration' => 60, // 1 hour token expiration

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
```

#### Multi-Factor Authentication
```php
<?php
// app/Models/User.php

use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_enabled',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
    ];

    public function enableTwoFactorAuth(): void
    {
        $this->forceFill([
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, function () {
                return RecoveryCode::generate();
            })->all())),
            'two_factor_enabled' => true,
        ])->save();
    }

    public function disableTwoFactorAuth(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_enabled' => false,
        ])->save();
    }
}
```

#### Secure Session Management
```php
<?php
// config/session.php

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours
    'expire_on_close' => true,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100], // 2% chance of garbage collection
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true), // HTTPS only
    'http_only' => true, // Prevent XSS
    'same_site' => 'strict', // CSRF protection
    'partitioned' => false,
];
```

### Password Security

#### Strong Password Policy
```php
<?php
// app/Rules/StrongPassword.php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < 12) {
            $fail('Password must be at least 12 characters long.');
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('Password must contain at least one uppercase letter.');
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('Password must contain at least one lowercase letter.');
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('Password must contain at least one number.');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Password must contain at least one special character.');
        }

        // Check against common passwords
        if ($this->isCommonPassword($value)) {
            $fail('Password is too common. Please choose a more secure password.');
        }
    }

    private function isCommonPassword(string $password): bool
    {
        $commonPasswords = [
            'password123', '123456789', 'qwerty123',
            'admin123', 'welcome123', 'password1',
        ];

        return in_array(strtolower($password), $commonPasswords);
    }
}
```

#### Password Hashing Configuration
```php
<?php
// config/hashing.php

return [
    'driver' => 'bcrypt',
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12), // Increased from default 10
    ],
    'argon' => [
        'memory' => 65536, // 64 MB
        'threads' => 1,
        'time' => 4,
    ],
];
```

## Authorization Security

### Role-Based Access Control (RBAC)

#### Permission System Implementation
```php
<?php
// app/Models/User.php

use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Log permission checks for audit
        activity()
            ->performedOn($this)
            ->withProperties([
                'permission' => $permission,
                'granted' => parent::hasPermissionTo($permission, $guardName),
                'ip_address' => request()->ip(),
            ])
            ->log('permission_check');

        return parent::hasPermissionTo($permission, $guardName);
    }

    public function assignRole(...$roles): self
    {
        $result = parent::assignRole(...$roles);

        // Log role assignments
        activity()
            ->performedOn($this)
            ->withProperties(['roles' => $roles])
            ->log('role_assigned');

        return $result;
    }
}
```

#### Policy-Based Authorization
```php
<?php
// app/Policies/UserPolicy.php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-users');
    }

    public function view(User $user, User $model): bool
    {
        // Users can view their own profile or have view-users permission
        return $user->id === $model->id || $user->hasPermissionTo('view-users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-users');
    }

    public function update(User $user, User $model): bool
    {
        // Users can update their own profile or have edit-users permission
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermissionTo('edit-users');
    }

    public function delete(User $user, User $model): bool
    {
        // Prevent self-deletion and require delete permission
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermissionTo('delete-users');
    }

    public function forceDelete(User $user, User $model): bool
    {
        // Only super admins can force delete
        return $user->hasRole('super-admin');
    }
}
```

### API Security

#### Rate Limiting Implementation
```php
<?php
// app/Http/Kernel.php

protected $middlewareGroups = [
    'api' => [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\SecurityHeaders::class,
    ],
];

protected $middlewareAliases = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'throttle.login' => \App\Http\Middleware\LoginThrottle::class,
];
```

#### Custom Rate Limiting
```php
<?php
// app/Http/Middleware/LoginThrottle.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LoginThrottle
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            
            // Log suspicious activity
            activity()
                ->withProperties([
                    'ip' => $request->ip(),
                    'email' => $request->input('email'),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('login_rate_limit_exceeded');

            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
            ], 429);
        }

        return $next($request);
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }
}
```

## Data Protection Security

### Input Validation and Sanitization

#### Form Request Validation
```php
<?php
// app/Http/Requests/CreateUserRequest.php

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s]+$/', // Only letters and spaces
            ],
            'email' => [
                'required',
                'email:rfc,dns', // Strict email validation
                'unique:users,email',
                'max:255',
            ],
            'password' => [
                'required',
                'confirmed',
                new StrongPassword(),
            ],
            'role' => [
                'required',
                'string',
                'exists:roles,name',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags($this->name), // Remove HTML tags
            'email' => strtolower(trim($this->email)), // Normalize email
        ]);
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Name can only contain letters and spaces.',
            'email.email' => 'Please provide a valid email address.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
```

#### XSS Prevention
```php
<?php
// app/Http/Middleware/XssProtection.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class XssProtection
{
    public function handle(Request $request, Closure $next): mixed
    {
        $input = $request->all();
        
        array_walk_recursive($input, function (&$input) {
            $input = strip_tags($input);
        });
        
        $request->merge($input);
        
        return $next($request);
    }
}
```

### SQL Injection Prevention

#### Parameterized Queries
```php
<?php
// Good: Using Eloquent ORM (automatically parameterized)
$users = User::where('email', $email)->get();

// Good: Using Query Builder with bindings
$users = DB::table('users')
    ->where('email', '=', $email)
    ->get();

// Good: Raw queries with parameter binding
$users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);

// BAD: Never do this (vulnerable to SQL injection)
// $users = DB::select("SELECT * FROM users WHERE email = '$email'");
```

#### Database Security Configuration
```php
<?php
// config/database.php

'sqlite' => [
    'driver' => 'sqlite',
    'database' => database_path('database.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'options' => [
        PDO::ATTR_TIMEOUT => 60,
        PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
],
```

### Encryption and Data Security

#### Sensitive Data Encryption
```php
<?php
// app/Models/User.php

class User extends Authenticatable
{
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        // Encrypt sensitive fields
        'phone' => 'encrypted',
        'address' => 'encrypted',
        'social_security_number' => 'encrypted',
    ];

    // Custom encryption for highly sensitive data
    public function setSocialSecurityNumberAttribute($value): void
    {
        $this->attributes['social_security_number'] = encrypt($value);
    }

    public function getSocialSecurityNumberAttribute($value): ?string
    {
        return $value ? decrypt($value) : null;
    }
}
```

#### File Upload Security
```php
<?php
// app/Http/Controllers/FileUploadController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:2048', // 2MB max
                'mimes:jpg,jpeg,png,pdf,doc,docx', // Allowed file types
            ],
        ]);

        $file = $request->file('file');
        
        // Generate secure filename
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        
        // Store in private disk
        $path = $file->storeAs('uploads', $filename, 'private');
        
        // Scan for malware (if antivirus service available)
        $this->scanForMalware($path);
        
        return response()->json([
            'message' => 'File uploaded successfully',
            'path' => $path,
        ]);
    }

    private function scanForMalware(string $path): void
    {
        // Implement malware scanning logic
        // This could integrate with ClamAV or similar service
    }
}
```

## Security Headers and HTTPS

### Security Headers Middleware
```php
<?php
// app/Http/Middleware/SecurityHeaders.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // XSS Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Strict Transport Security (HTTPS only)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $response->headers->set('Content-Security-Policy', 
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none';"
        );
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy
        $response->headers->set('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=()'
        );

        return $response;
    }
}
```

## Security Monitoring and Logging

### Security Event Logging
```php
<?php
// app/Listeners/SecurityEventListener.php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

class SecurityEventListener
{
    public function handleLogin(Login $event): void
    {
        activity()
            ->performedOn($event->user)
            ->withProperties([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
            ])
            ->log('user_login');
    }

    public function handleFailedLogin(Failed $event): void
    {
        activity()
            ->withProperties([
                'email' => $event->credentials['email'] ?? 'unknown',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('login_failed');
        
        // Alert on multiple failed attempts
        $this->checkForBruteForceAttack($event->credentials['email'] ?? '');
    }

    public function handleLogout(Logout $event): void
    {
        activity()
            ->performedOn($event->user)
            ->withProperties([
                'ip_address' => request()->ip(),
                'session_duration' => $this->calculateSessionDuration($event->user),
            ])
            ->log('user_logout');
    }

    private function checkForBruteForceAttack(string $email): void
    {
        $recentFailures = Activity::where('description', 'login_failed')
            ->where('properties->email', $email)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recentFailures >= 5) {
            // Send security alert
            $this->sendSecurityAlert('Potential brute force attack detected', [
                'email' => $email,
                'ip_address' => request()->ip(),
                'failure_count' => $recentFailures,
            ]);
        }
    }
}
```

### Intrusion Detection
```php
<?php
// app/Services/IntrusionDetectionService.php

namespace App\Services;

class IntrusionDetectionService
{
    public function detectSuspiciousActivity(): void
    {
        $this->detectMultipleFailedLogins();
        $this->detectUnusualAccessPatterns();
        $this->detectPrivilegeEscalation();
        $this->detectDataExfiltration();
    }

    private function detectMultipleFailedLogins(): void
    {
        $suspiciousIPs = Activity::where('description', 'login_failed')
            ->where('created_at', '>', now()->subHour())
            ->groupBy('properties->ip_address')
            ->havingRaw('COUNT(*) > 10')
            ->pluck('properties->ip_address');

        foreach ($suspiciousIPs as $ip) {
            $this->blockIP($ip);
            $this->sendSecurityAlert('IP blocked due to excessive failed logins', ['ip' => $ip]);
        }
    }

    private function detectPrivilegeEscalation(): void
    {
        $recentRoleChanges = Activity::where('description', 'role_assigned')
            ->where('created_at', '>', now()->subDay())
            ->get();

        foreach ($recentRoleChanges as $change) {
            if (in_array('admin', $change->properties['roles'] ?? [])) {
                $this->sendSecurityAlert('Admin role assigned', [
                    'user_id' => $change->subject_id,
                    'assigned_by' => $change->causer_id,
                ]);
            }
        }
    }

    private function blockIP(string $ip): void
    {
        // Implement IP blocking logic
        // This could integrate with firewall or load balancer
    }
}
```

## GDPR Security Compliance

### Data Protection Impact Assessment
```php
<?php
// app/Services/DataProtectionService.php

namespace App\Services;

class DataProtectionService
{
    public function conductDPIA(array $processingActivity): array
    {
        return [
            'activity_description' => $processingActivity['description'],
            'data_types' => $this->classifyDataTypes($processingActivity['data']),
            'risk_assessment' => $this->assessRisks($processingActivity),
            'mitigation_measures' => $this->getMitigationMeasures($processingActivity),
            'compliance_status' => $this->checkCompliance($processingActivity),
        ];
    }

    private function classifyDataTypes(array $data): array
    {
        $classification = [];
        
        foreach ($data as $field => $value) {
            $classification[$field] = match($field) {
                'email', 'name' => 'personal_data',
                'phone', 'address' => 'contact_data',
                'ip_address', 'user_agent' => 'technical_data',
                'health_info', 'medical_records' => 'special_category_data',
                default => 'general_data'
            };
        }
        
        return $classification;
    }

    private function assessRisks(array $activity): array
    {
        $risks = [];
        
        // Assess data breach risk
        if ($this->containsSensitiveData($activity['data'])) {
            $risks[] = [
                'type' => 'data_breach',
                'likelihood' => 'medium',
                'impact' => 'high',
                'mitigation' => 'encryption_at_rest_and_transit',
            ];
        }
        
        return $risks;
    }
}
```

## Security Testing and Validation

### Automated Security Testing
```php
<?php
// tests/Feature/Security/SecurityTest.php

class SecurityTest extends TestCase
{
    /** @test */
    public function it_prevents_sql_injection_attacks()
    {
        $maliciousInput = "'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/users/search', [
            'query' => $maliciousInput,
        ]);
        
        // Should not cause database error
        $response->assertStatus(422); // Validation error expected
        
        // Verify users table still exists
        $this->assertDatabaseHas('users', []);
    }

    /** @test */
    public function it_prevents_xss_attacks()
    {
        $user = User::factory()->create();
        $maliciousScript = '<script>alert("XSS")</script>';
        
        $response = $this->actingAs($user)
            ->post('/profile/update', [
                'name' => $maliciousScript,
            ]);
        
        $user->refresh();
        
        // Script should be stripped
        $this->assertStringNotContainsString('<script>', $user->name);
        $this->assertStringNotContainsString('alert', $user->name);
    }

    /** @test */
    public function it_enforces_rate_limiting()
    {
        $user = User::factory()->create();
        
        // Make multiple requests quickly
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/user/profile');
        }
        
        // Should be rate limited
        $response->assertStatus(429);
    }
}
```

---

**Security Implementation Guide Version**: 1.0.0  
**Security Framework**: OWASP Top 10 + GDPR Compliance  
**Framework**: Laravel 12.x with FilamentPHP v4  
**Created**: [YYYY-MM-DD]  
**Last Updated**: [YYYY-MM-DD]  
**Next Review**: [YYYY-MM-DD]  
**Security Owner**: [SECURITY_LEAD]
