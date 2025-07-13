# Laravel WorkOS Guide

## Overview

This guide provides comprehensive implementation of WorkOS authentication and directory sync for the Chinook application, enabling enterprise SSO and user management capabilities.

## Table of Contents

- [Overview](#overview)
- [Installation & Configuration](#installation--configuration)
- [Authentication Setup](#authentication-setup)
- [Directory Sync](#directory-sync)
- [User Management](#user-management)
- [RBAC Integration](#rbac-integration)
- [API Integration](#api-integration)
- [Testing Strategies](#testing-strategies)
- [Monitoring & Troubleshooting](#monitoring--troubleshooting)

## Installation & Configuration

### Package Installation

```bash
# Install WorkOS PHP SDK
composer require workos/workos-php

# Install Laravel Socialite for OAuth integration
composer require laravel/socialite

# Publish configuration
php artisan vendor:publish --provider="Laravel\Socialite\SocialiteServiceProvider"
```

### Environment Configuration

```bash
# .env
WORKOS_API_KEY=your_workos_api_key
WORKOS_CLIENT_ID=your_workos_client_id
WORKOS_REDIRECT_URI=https://your-app.com/auth/workos/callback

# Optional: Directory Sync
WORKOS_WEBHOOK_SECRET=your_webhook_secret
```

### Service Provider Configuration

```php
// config/services.php
return [
    'workos' => [
        'api_key' => env('WORKOS_API_KEY'),
        'client_id' => env('WORKOS_CLIENT_ID'),
        'redirect_uri' => env('WORKOS_REDIRECT_URI'),
        'webhook_secret' => env('WORKOS_WEBHOOK_SECRET'),
    ],
];
```

## Authentication Setup

### WorkOS Service Integration

```php
// app/Services/WorkOSService.php
<?php

namespace App\Services;

use WorkOS\WorkOS;
use WorkOS\SSO;
use WorkOS\DirectorySync;

class WorkOSService
{
    private WorkOS $workos;
    private SSO $sso;
    private DirectorySync $directorySync;

    public function __construct()
    {
        $this->workos = new WorkOS(config('services.workos.api_key'));
        $this->sso = $this->workos->sso;
        $this->directorySync = $this->workos->directorySync;
    }

    public function getAuthorizationUrl(string $domain): string
    {
        return $this->sso->getAuthorizationUrl([
            'domain' => $domain,
            'redirect_uri' => config('services.workos.redirect_uri'),
            'client_id' => config('services.workos.client_id'),
        ]);
    }

    public function getProfile(string $code): array
    {
        $profile = $this->sso->getProfile([
            'code' => $code,
            'client_id' => config('services.workos.client_id'),
        ]);

        return [
            'id' => $profile->getId(),
            'email' => $profile->getEmail(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'connection_id' => $profile->getConnectionId(),
            'connection_type' => $profile->getConnectionType(),
            'organization_id' => $profile->getOrganizationId(),
        ];
    }

    public function listDirectories(): array
    {
        return $this->directorySync->listDirectories();
    }

    public function getDirectoryUsers(string $directoryId): array
    {
        return $this->directorySync->listUsers([
            'directory' => $directoryId,
        ]);
    }
}
```

### Authentication Controller

```php
// app/Http/Controllers/Auth/WorkOSController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\WorkOSService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WorkOSController extends Controller
{
    public function __construct(
        private WorkOSService $workosService
    ) {}

    public function redirect(Request $request)
    {
        $domain = $request->input('domain');
        
        if (!$domain) {
            return redirect()->route('login')
                ->withErrors(['domain' => 'Domain is required for SSO login']);
        }

        $authUrl = $this->workosService->getAuthorizationUrl($domain);
        
        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        $code = $request->input('code');
        
        if (!$code) {
            return redirect()->route('login')
                ->withErrors(['error' => 'Authorization code not provided']);
        }

        try {
            $profile = $this->workosService->getProfile($code);
            $user = $this->findOrCreateUser($profile);
            
            Auth::login($user);
            
            return redirect()->intended('/admin');
            
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->withErrors(['error' => 'Authentication failed: ' . $e->getMessage()]);
        }
    }

    private function findOrCreateUser(array $profile): User
    {
        $user = User::where('email', $profile['email'])->first();

        if (!$user) {
            $user = User::create([
                'name' => trim($profile['first_name'] . ' ' . $profile['last_name']),
                'email' => $profile['email'],
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(32)), // Random password for SSO users
                'workos_id' => $profile['id'],
                'workos_connection_id' => $profile['connection_id'],
                'workos_organization_id' => $profile['organization_id'],
            ]);

            // Assign default role for new SSO users
            $user->assignRole('user');
        } else {
            // Update WorkOS information for existing users
            $user->update([
                'workos_id' => $profile['id'],
                'workos_connection_id' => $profile['connection_id'],
                'workos_organization_id' => $profile['organization_id'],
            ]);
        }

        return $user;
    }
}
```

## Directory Sync

### Webhook Handler

```php
// app/Http/Controllers/WorkOSWebhookController.php
<?php

namespace App\Http\Controllers;

use App\Services\WorkOSDirectorySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkOSWebhookController extends Controller
{
    public function __construct(
        private WorkOSDirectorySyncService $directorySyncService
    ) {}

    public function handle(Request $request)
    {
        $signature = $request->header('WorkOS-Signature');
        $payload = $request->getContent();

        if (!$this->verifyWebhookSignature($signature, $payload)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = json_decode($payload, true);

        try {
            $this->processEvent($event);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('WorkOS webhook processing failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    private function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $secret = config('services.workos.webhook_secret');
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($signature, $expectedSignature);
    }

    private function processEvent(array $event): void
    {
        match ($event['event']) {
            'dsync.user.created' => $this->directorySyncService->handleUserCreated($event['data']),
            'dsync.user.updated' => $this->directorySyncService->handleUserUpdated($event['data']),
            'dsync.user.deleted' => $this->directorySyncService->handleUserDeleted($event['data']),
            'dsync.group.created' => $this->directorySyncService->handleGroupCreated($event['data']),
            'dsync.group.updated' => $this->directorySyncService->handleGroupUpdated($event['data']),
            'dsync.group.deleted' => $this->directorySyncService->handleGroupDeleted($event['data']),
            default => Log::info('Unhandled WorkOS event', ['event' => $event['event']]),
        };
    }
}
```

### Directory Sync Service

```php
// app/Services/WorkOSDirectorySyncService.php
<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class WorkOSDirectorySyncService
{
    public function handleUserCreated(array $userData): void
    {
        $user = User::create([
            'name' => $userData['first_name'] . ' ' . $userData['last_name'],
            'email' => $userData['email'],
            'email_verified_at' => now(),
            'workos_directory_user_id' => $userData['id'],
            'workos_directory_id' => $userData['directory_id'],
        ]);

        // Assign default role
        $user->assignRole('user');

        Log::info('WorkOS user created', ['user_id' => $user->id, 'email' => $user->email]);
    }

    public function handleUserUpdated(array $userData): void
    {
        $user = User::where('workos_directory_user_id', $userData['id'])->first();

        if ($user) {
            $user->update([
                'name' => $userData['first_name'] . ' ' . $userData['last_name'],
                'email' => $userData['email'],
            ]);

            Log::info('WorkOS user updated', ['user_id' => $user->id, 'email' => $user->email]);
        }
    }

    public function handleUserDeleted(array $userData): void
    {
        $user = User::where('workos_directory_user_id', $userData['id'])->first();

        if ($user) {
            $user->delete();
            Log::info('WorkOS user deleted', ['user_id' => $user->id, 'email' => $user->email]);
        }
    }

    public function handleGroupCreated(array $groupData): void
    {
        // Create role if it doesn't exist
        $role = Role::firstOrCreate([
            'name' => strtolower(str_replace(' ', '-', $groupData['name'])),
            'guard_name' => 'web',
        ]);

        Log::info('WorkOS group created as role', ['role' => $role->name]);
    }

    public function handleGroupUpdated(array $groupData): void
    {
        // Handle group updates if needed
        Log::info('WorkOS group updated', ['group_id' => $groupData['id']]);
    }

    public function handleGroupDeleted(array $groupData): void
    {
        // Handle group deletion if needed
        Log::info('WorkOS group deleted', ['group_id' => $groupData['id']]);
    }
}
```

## User Management

### User Model Extensions

```php
// Add to User model
class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'workos_id',
        'workos_connection_id',
        'workos_organization_id',
        'workos_directory_user_id',
        'workos_directory_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isWorkOSUser(): bool
    {
        return !is_null($this->workos_id);
    }

    public function isDirectorySyncUser(): bool
    {
        return !is_null($this->workos_directory_user_id);
    }
}
```

### Migration

```php
// database/migrations/add_workos_fields_to_users_table.php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('workos_id')->nullable()->unique();
        $table->string('workos_connection_id')->nullable();
        $table->string('workos_organization_id')->nullable();
        $table->string('workos_directory_user_id')->nullable()->unique();
        $table->string('workos_directory_id')->nullable();
        
        $table->index(['workos_id']);
        $table->index(['workos_directory_user_id']);
    });
}
```

## RBAC Integration

### Role Mapping

```php
// config/workos.php
return [
    'role_mapping' => [
        'admin' => 'admin',
        'manager' => 'manager',
        'employee' => 'user',
        'contractor' => 'user',
    ],
    
    'default_role' => 'user',
];
```

## API Integration

### WorkOS API Client

```php
// app/Http/Controllers/Api/WorkOSController.php
class WorkOSController extends Controller
{
    public function organizations(Request $request)
    {
        $this->authorize('view-workos-data');
        
        $organizations = $this->workosService->listOrganizations();
        
        return response()->json($organizations);
    }

    public function connections(Request $request)
    {
        $this->authorize('view-workos-data');
        
        $connections = $this->workosService->listConnections();
        
        return response()->json($connections);
    }
}
```

## Testing Strategies

### Feature Tests

```php
// tests/Feature/WorkOSAuthTest.php
class WorkOSAuthTest extends TestCase
{
    /** @test */
    public function it_redirects_to_workos_for_authentication()
    {
        $response = $this->post('/auth/workos', ['domain' => 'example.com']);
        
        $response->assertRedirect();
        $this->assertStringContains('workos.com', $response->headers->get('Location'));
    }

    /** @test */
    public function it_creates_user_from_workos_profile()
    {
        $this->mock(WorkOSService::class)
            ->shouldReceive('getProfile')
            ->andReturn([
                'id' => 'workos_123',
                'email' => 'user@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'connection_id' => 'conn_123',
                'organization_id' => 'org_123',
            ]);

        $response = $this->get('/auth/workos/callback?code=auth_code');

        $response->assertRedirect('/admin');
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'workos_id' => 'workos_123',
        ]);
    }
}
```

## Monitoring & Troubleshooting

### Logging Configuration

```php
// config/logging.php
'channels' => [
    'workos' => [
        'driver' => 'daily',
        'path' => storage_path('logs/workos.log'),
        'level' => 'info',
        'days' => 14,
    ],
],
```

### Health Checks

```php
// app/Http/Controllers/HealthController.php
public function workos()
{
    try {
        $organizations = $this->workosService->listOrganizations();
        
        return response()->json([
            'status' => 'healthy',
            'organizations_count' => count($organizations),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 503);
    }
}
```

### Common Issues

1. **Invalid Signature**: Check webhook secret configuration
2. **User Creation Fails**: Verify required fields and validation rules
3. **Role Assignment Issues**: Ensure role mapping configuration is correct
4. **Connection Timeout**: Check API key and network connectivity

---

**Next**: [Spatie Laravel Query Builder Guide](101-laravel-query-builder-guide.md) | **Back**: [Spatie Tags Guide](090-spatie-tags-guide.md)

---

*This guide provides comprehensive WorkOS integration for enterprise authentication and user management.*
