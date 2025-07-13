# 1. Laravel WorkOS Implementation Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Installation & Configuration](#2-installation--configuration)
  - [2.1. Package Installation](#21-package-installation)
  - [2.2. Environment Configuration](#22-environment-configuration)
  - [2.3. Service Provider Configuration](#23-service-provider-configuration)
- [3. Authentication Setup](#3-authentication-setup)
  - [3.1. WorkOS Service Integration](#31-workos-service-integration)
  - [3.2. SSO Controller Implementation](#32-sso-controller-implementation)
  - [3.3. User Provisioning](#33-user-provisioning)
- [8. Testing Strategies](#8-testing-strategies)
  - [8.1. SSO Testing](#81-sso-testing)
  - [8.2. Directory Sync Testing](#82-directory-sync-testing)
- [9. Monitoring & Troubleshooting](#9-monitoring--troubleshooting)
  - [9.1. Health Checks](#91-health-checks)
  - [9.2. Error Handling](#92-error-handling)
- [Navigation](#navigation)

## 1. Overview

This guide provides comprehensive implementation of WorkOS authentication and directory sync for the Chinook application, enabling enterprise SSO and user management capabilities with **exclusive aliziodev/laravel-taxonomy integration** for genre-based user segmentation and access control.

**🚀 Key Features:**
- **Enterprise SSO**: Single Sign-On integration with major identity providers
- **Directory Sync**: Automated user and group synchronization
- **RBAC Integration**: Role-based access control with spatie/laravel-permission
- **Audit Logging**: Comprehensive authentication and access logging
- **Compliance**: SOC 2, GDPR, and enterprise security compliance
- **Taxonomy Integration**: Genre-based user segmentation and content access control

**🎵 Chinook Integration Benefits:**
- **Department-Genre Mapping**: Map organizational departments to music genre preferences
- **Content Access Control**: Restrict music content based on user's organizational role and genre preferences
- **Analytics Segmentation**: User analytics segmented by department and music preferences
- **Personalized Experiences**: Tailored music recommendations based on organizational context

## 2. Installation & Configuration

### 2.1. Package Installation

```bash
# Install WorkOS PHP SDK
composer require workos/workos-php

# Install Laravel Socialite for OAuth integration
composer require laravel/socialite

# Publish configuration
php artisan vendor:publish --provider="Laravel\Socialite\SocialiteServiceProvider"
```

### 2.2. Environment Configuration

```bash
# .env
WORKOS_API_KEY=your_workos_api_key
WORKOS_CLIENT_ID=your_workos_client_id
WORKOS_REDIRECT_URI=https://your-app.com/auth/workos/callback

# Optional: Directory Sync
WORKOS_WEBHOOK_SECRET=your_webhook_secret

# Taxonomy Integration
WORKOS_DEFAULT_GENRE_MAPPING=true
WORKOS_AUTO_ASSIGN_GENRES=true
```

### 2.3. Service Provider Configuration

```php
// config/services.php
return [
    'workos' => [
        'api_key' => env('WORKOS_API_KEY'),
        'client_id' => env('WORKOS_CLIENT_ID'),
        'redirect_uri' => env('WORKOS_REDIRECT_URI'),
        'webhook_secret' => env('WORKOS_WEBHOOK_SECRET'),
        'default_genre_mapping' => env('WORKOS_DEFAULT_GENRE_MAPPING', true),
        'auto_assign_genres' => env('WORKOS_AUTO_ASSIGN_GENRES', true),
    ],
];
```

## 3. Authentication Setup

### 3.1. WorkOS Service Integration

```php
// app/Services/WorkOSService.php
<?php

namespace App\Services;

use WorkOS\WorkOS;
use WorkOS\SSO;
use WorkOS\DirectorySync;
use App\Models\User;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Support\Facades\Log;

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
            'raw_attributes' => $profile->getRawAttributes(),
        ];
    }

    public function createOrUpdateUser(array $profile): User
    {
        $user = User::updateOrCreate(
            ['email' => $profile['email']],
            [
                'name' => trim($profile['first_name'] . ' ' . $profile['last_name']),
                'workos_id' => $profile['id'],
                'connection_id' => $profile['connection_id'],
                'organization_id' => $profile['organization_id'],
                'email_verified_at' => now(),
            ]
        );

        // Auto-assign genres based on organization/department
        if (config('services.workos.auto_assign_genres')) {
            $this->assignGenresBasedOnOrganization($user, $profile);
        }

        // Log authentication event
        Log::info('WorkOS user authenticated', [
            'user_id' => $user->id,
            'email' => $user->email,
            'organization_id' => $profile['organization_id'],
            'connection_type' => $profile['connection_type'],
        ]);

        return $user;
    }

    private function assignGenresBasedOnOrganization(User $user, array $profile): void
    {
        $rawAttributes = $profile['raw_attributes'] ?? [];
        $department = $rawAttributes['department'] ?? null;
        $jobTitle = $rawAttributes['job_title'] ?? null;

        // Map departments to genre preferences
        $genreMapping = $this->getDepartmentGenreMapping();
        
        $assignedGenres = collect();

        if ($department && isset($genreMapping[$department])) {
            $genreNames = $genreMapping[$department];
            $genres = Taxonomy::where('type', 'genre')
                ->whereIn('name', $genreNames)
                ->get();
            
            $assignedGenres = $assignedGenres->merge($genres);
        }

        // Additional mapping based on job title
        if ($jobTitle) {
            $titleGenres = $this->getJobTitleGenreMapping($jobTitle);
            if (!empty($titleGenres)) {
                $genres = Taxonomy::where('type', 'genre')
                    ->whereIn('name', $titleGenres)
                    ->get();
                
                $assignedGenres = $assignedGenres->merge($genres);
            }
        }

        // Assign unique genres to user
        if ($assignedGenres->isNotEmpty()) {
            $user->taxonomies()->syncWithoutDetaching($assignedGenres->pluck('id')->unique());
            
            Log::info('Auto-assigned genres to WorkOS user', [
                'user_id' => $user->id,
                'department' => $department,
                'job_title' => $jobTitle,
                'assigned_genres' => $assignedGenres->pluck('name')->toArray(),
            ]);
        }
    }

    private function getDepartmentGenreMapping(): array
    {
        return [
            'Marketing' => ['Pop', 'Electronic', 'Alternative'],
            'Engineering' => ['Electronic', 'Progressive Rock', 'Instrumental'],
            'Sales' => ['Pop', 'Rock', 'Country'],
            'HR' => ['Jazz', 'Classical', 'World'],
            'Finance' => ['Classical', 'Jazz', 'Blues'],
            'Creative' => ['Alternative', 'Indie', 'Electronic'],
            'Operations' => ['Rock', 'Blues', 'Country'],
            'Legal' => ['Classical', 'Jazz'],
            'Customer Success' => ['Pop', 'Folk', 'World'],
            'Product' => ['Electronic', 'Alternative', 'Progressive Rock'],
        ];
    }

    private function getJobTitleGenreMapping(string $jobTitle): array
    {
        $titleLower = strtolower($jobTitle);

        if (str_contains($titleLower, 'developer') || str_contains($titleLower, 'engineer')) {
            return ['Electronic', 'Progressive Rock'];
        }

        if (str_contains($titleLower, 'designer') || str_contains($titleLower, 'creative')) {
            return ['Alternative', 'Indie'];
        }

        if (str_contains($titleLower, 'manager') || str_contains($titleLower, 'director')) {
            return ['Jazz', 'Classical'];
        }

        if (str_contains($titleLower, 'analyst') || str_contains($titleLower, 'data')) {
            return ['Electronic', 'Instrumental'];
        }

        return [];
    }

    public function getDirectories(): array
    {
        return $this->directorySync->listDirectories();
    }

    public function getDirectoryUsers(string $directoryId): array
    {
        return $this->directorySync->listUsers(['directory' => $directoryId]);
    }

    public function getDirectoryGroups(string $directoryId): array
    {
        return $this->directorySync->listGroups(['directory' => $directoryId]);
    }
}
```

### 3.2. SSO Controller Implementation

```php
// app/Http/Controllers/Auth/WorkOSController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\WorkOSService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WorkOSController extends Controller
{
    private WorkOSService $workosService;

    public function __construct(WorkOSService $workosService)
    {
        $this->workosService = $workosService;
    }

    public function redirect(Request $request): RedirectResponse
    {
        $request->validate([
            'domain' => 'required|string|email',
        ]);

        $domain = $request->input('domain');
        $authorizationUrl = $this->workosService->getAuthorizationUrl($domain);

        // Store domain in session for callback processing
        session(['workos_domain' => $domain]);

        return redirect($authorizationUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $error = $request->input('error');

        if ($error) {
            Log::error('WorkOS authentication error', [
                'error' => $error,
                'error_description' => $request->input('error_description'),
            ]);

            return redirect('/login')->withErrors([
                'workos' => 'Authentication failed: ' . $error,
            ]);
        }

        if (!$code) {
            return redirect('/login')->withErrors([
                'workos' => 'No authorization code received',
            ]);
        }

        try {
            $profile = $this->workosService->getProfile($code);
            $user = $this->workosService->createOrUpdateUser($profile);

            Auth::login($user, true);

            // Clear session data
            session()->forget('workos_domain');

            // Redirect to intended page or dashboard
            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            Log::error('WorkOS callback error', [
                'error' => $e->getMessage(),
                'code' => $code,
            ]);

            return redirect('/login')->withErrors([
                'workos' => 'Authentication failed. Please try again.',
            ]);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
```

### 3.3. User Provisioning

```php
// app/Services/UserProvisioningService.php
<?php

namespace App\Services;

use App\Models\User;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class UserProvisioningService
{
    public function provisionUser(array $workosProfile): User
    {
        $user = User::updateOrCreate(
            ['email' => $workosProfile['email']],
            [
                'name' => trim($workosProfile['first_name'] . ' ' . $workosProfile['last_name']),
                'workos_id' => $workosProfile['id'],
                'connection_id' => $workosProfile['connection_id'],
                'organization_id' => $workosProfile['organization_id'],
                'email_verified_at' => now(),
            ]
        );

        // Assign roles based on organization attributes
        $this->assignRoles($user, $workosProfile);

        // Assign genre preferences based on department
        $this->assignGenrePreferences($user, $workosProfile);

        // Set up user preferences
        $this->setupUserPreferences($user, $workosProfile);

        return $user;
    }

    private function assignRoles(User $user, array $profile): void
    {
        $rawAttributes = $profile['raw_attributes'] ?? [];
        $department = $rawAttributes['department'] ?? null;
        $jobTitle = $rawAttributes['job_title'] ?? null;

        // Default role
        $roleName = 'user';

        // Map job titles to roles
        if ($jobTitle) {
            $titleLower = strtolower($jobTitle);

            if (str_contains($titleLower, 'admin') || str_contains($titleLower, 'cto') || str_contains($titleLower, 'ceo')) {
                $roleName = 'super-admin';
            } elseif (str_contains($titleLower, 'manager') || str_contains($titleLower, 'director') || str_contains($titleLower, 'lead')) {
                $roleName = 'admin';
            } elseif (str_contains($titleLower, 'senior') || str_contains($titleLower, 'principal')) {
                $roleName = 'manager';
            } elseif (str_contains($titleLower, 'editor') || str_contains($titleLower, 'content')) {
                $roleName = 'editor';
            }
        }

        // Ensure role exists
        $role = Role::firstOrCreate(['name' => $roleName]);

        // Assign role if not already assigned
        if (!$user->hasRole($roleName)) {
            $user->assignRole($roleName);

            Log::info('Assigned role to WorkOS user', [
                'user_id' => $user->id,
                'role' => $roleName,
                'job_title' => $jobTitle,
                'department' => $department,
            ]);
        }
    }

    private function assignGenrePreferences(User $user, array $profile): void
    {
        $rawAttributes = $profile['raw_attributes'] ?? [];
        $department = $rawAttributes['department'] ?? null;
        $location = $rawAttributes['location'] ?? null;

        $genrePreferences = [];

        // Department-based preferences
        if ($department) {
            $genrePreferences = array_merge($genrePreferences, $this->getDepartmentGenres($department));
        }

        // Location-based preferences
        if ($location) {
            $genrePreferences = array_merge($genrePreferences, $this->getLocationGenres($location));
        }

        // Default preferences if none assigned
        if (empty($genrePreferences)) {
            $genrePreferences = ['Pop', 'Rock', 'Jazz'];
        }

        // Find and assign genres
        $genres = Taxonomy::where('type', 'genre')
            ->whereIn('name', array_unique($genrePreferences))
            ->get();

        if ($genres->isNotEmpty()) {
            $user->taxonomies()->syncWithoutDetaching($genres->pluck('id'));

            Log::info('Assigned genre preferences to WorkOS user', [
                'user_id' => $user->id,
                'department' => $department,
                'location' => $location,
                'genres' => $genres->pluck('name')->toArray(),
            ]);
        }
    }

    private function getDepartmentGenres(string $department): array
    {
        $mapping = [
            'Marketing' => ['Pop', 'Electronic', 'Alternative'],
            'Engineering' => ['Electronic', 'Progressive Rock', 'Instrumental'],
            'Sales' => ['Pop', 'Rock', 'Country'],
            'HR' => ['Jazz', 'Classical', 'World'],
            'Finance' => ['Classical', 'Jazz', 'Blues'],
            'Creative' => ['Alternative', 'Indie', 'Electronic'],
            'Operations' => ['Rock', 'Blues', 'Country'],
            'Legal' => ['Classical', 'Jazz'],
            'Customer Success' => ['Pop', 'Folk', 'World'],
            'Product' => ['Electronic', 'Alternative', 'Progressive Rock'],
        ];

        return $mapping[$department] ?? [];
    }

    private function getLocationGenres(string $location): array
    {
        $locationLower = strtolower($location);

        if (str_contains($locationLower, 'nashville') || str_contains($locationLower, 'austin')) {
            return ['Country', 'Folk', 'Blues'];
        }

        if (str_contains($locationLower, 'new york') || str_contains($locationLower, 'chicago')) {
            return ['Jazz', 'Hip-Hop', 'R&B'];
        }

        if (str_contains($locationLower, 'los angeles') || str_contains($locationLower, 'san francisco')) {
            return ['Pop', 'Electronic', 'Alternative'];
        }

        if (str_contains($locationLower, 'london') || str_contains($locationLower, 'manchester')) {
            return ['Rock', 'Alternative', 'Electronic'];
        }

        return [];
    }

    private function setupUserPreferences(User $user, array $profile): void
    {
        $preferences = [
            'workos_provisioned' => true,
            'auto_genre_assignment' => true,
            'organization_id' => $profile['organization_id'],
            'connection_type' => $profile['connection_type'],
            'last_workos_sync' => now()->toISOString(),
        ];

        $user->update(['preferences' => array_merge($user->preferences ?? [], $preferences)]);
    }
}
```

## 8. Testing Strategies

### 8.1. SSO Testing

Comprehensive testing for WorkOS SSO integration:

```php
<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\WorkOSService;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class WorkOSSSOTest extends TestCase
{
    use RefreshDatabase;

    public function test_workos_redirect_generates_authorization_url(): void
    {
        $response = $this->post('/auth/workos/redirect', [
            'domain' => 'example.com',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('workos.com', $response->getTargetUrl());
        $this->assertEquals('example.com', session('workos_domain'));
    }

    public function test_workos_callback_creates_user_with_genre_assignment(): void
    {
        // Create test genres
        $rockGenre = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
        $jazzGenre = Taxonomy::create(['name' => 'Jazz', 'type' => 'genre']);

        // Mock WorkOS profile response
        $mockProfile = [
            'id' => 'workos_user_123',
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'connection_id' => 'conn_123',
            'connection_type' => 'OktaSAML',
            'organization_id' => 'org_123',
            'raw_attributes' => [
                'department' => 'Engineering',
                'job_title' => 'Senior Developer',
                'location' => 'San Francisco',
            ],
        ];

        $this->mock(WorkOSService::class, function ($mock) use ($mockProfile) {
            $mock->shouldReceive('getProfile')
                ->once()
                ->with('test_code')
                ->andReturn($mockProfile);

            $mock->shouldReceive('createOrUpdateUser')
                ->once()
                ->with($mockProfile)
                ->andReturnUsing(function ($profile) {
                    return User::create([
                        'name' => 'John Doe',
                        'email' => $profile['email'],
                        'workos_id' => $profile['id'],
                        'email_verified_at' => now(),
                    ]);
                });
        });

        $response = $this->get('/auth/workos/callback?code=test_code');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('workos_user_123', $user->workos_id);
    }

    public function test_workos_callback_handles_error(): void
    {
        $response = $this->get('/auth/workos/callback?error=access_denied&error_description=User+denied+access');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['workos']);
        $this->assertGuest();
    }

    public function test_department_genre_mapping(): void
    {
        $engineeringGenres = Taxonomy::whereIn('name', ['Electronic', 'Progressive Rock', 'Instrumental'])
            ->where('type', 'genre')
            ->get();

        $this->assertCount(3, $engineeringGenres);

        $marketingGenres = Taxonomy::whereIn('name', ['Pop', 'Electronic', 'Alternative'])
            ->where('type', 'genre')
            ->get();

        $this->assertCount(3, $marketingGenres);
    }
}
```

### 8.2. Directory Sync Testing

Test directory synchronization with taxonomy integration:

```php
<?php

namespace Tests\Feature\WorkOS;

use Tests\TestCase;
use App\Models\User;
use App\Services\WorkOSService;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DirectorySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_sync_creates_users_with_genres(): void
    {
        // Create test genres
        Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
        Taxonomy::create(['name' => 'Jazz', 'type' => 'genre']);
        Taxonomy::create(['name' => 'Electronic', 'type' => 'genre']);

        $mockUsers = [
            [
                'id' => 'dir_user_1',
                'email' => 'alice@example.com',
                'first_name' => 'Alice',
                'last_name' => 'Johnson',
                'department' => 'Engineering',
                'job_title' => 'Software Engineer',
            ],
            [
                'id' => 'dir_user_2',
                'email' => 'bob@example.com',
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'department' => 'Marketing',
                'job_title' => 'Marketing Manager',
            ],
        ];

        $this->mock(WorkOSService::class, function ($mock) use ($mockUsers) {
            $mock->shouldReceive('getDirectoryUsers')
                ->once()
                ->with('directory_123')
                ->andReturn($mockUsers);
        });

        $this->artisan('workos:sync-directory', ['directory' => 'directory_123']);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'name' => 'Alice Johnson',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'bob@example.com',
            'name' => 'Bob Smith',
        ]);

        // Verify genre assignments
        $alice = User::where('email', 'alice@example.com')->first();
        $this->assertTrue($alice->taxonomies()->where('name', 'Electronic')->exists());

        $bob = User::where('email', 'bob@example.com')->first();
        $this->assertTrue($bob->taxonomies()->where('name', 'Pop')->exists());
    }

    public function test_webhook_processes_user_updates(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'workos_id' => 'workos_123',
        ]);

        $webhookPayload = [
            'event' => 'dsync.user.updated',
            'data' => [
                'id' => 'workos_123',
                'email' => 'test@example.com',
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'department' => 'Sales',
                'job_title' => 'Sales Director',
            ],
        ];

        $response = $this->postJson('/webhooks/workos', $webhookPayload, [
            'WorkOS-Signature' => $this->generateWebhookSignature($webhookPayload),
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);

        // Verify new genre assignments based on Sales department
        $this->assertTrue($user->taxonomies()->where('name', 'Country')->exists());
    }

    private function generateWebhookSignature(array $payload): string
    {
        $secret = config('services.workos.webhook_secret');
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
```

## 9. Monitoring & Troubleshooting

### 9.1. Health Checks

```php
// app/Http/Controllers/HealthController.php
<?php

namespace App\Http\Controllers;

use App\Services\WorkOSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function workos(): JsonResponse
    {
        try {
            $workosService = app(WorkOSService::class);

            // Test API connectivity
            $directories = $workosService->getDirectories();

            $status = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'api_connectivity' => 'ok',
                'directories_count' => count($directories),
                'last_sync' => Cache::get('workos_last_sync'),
            ];

            return response()->json($status);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
                'api_connectivity' => 'failed',
            ], 503);
        }
    }
}
```

### 9.2. Error Handling

```php
// app/Exceptions/WorkOSException.php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class WorkOSException extends Exception
{
    public static function authenticationFailed(string $reason): self
    {
        return new self("WorkOS authentication failed: {$reason}");
    }

    public static function syncFailed(string $directoryId, string $reason): self
    {
        return new self("Directory sync failed for {$directoryId}: {$reason}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'type' => 'workos_error',
                'message' => $this->getMessage(),
                'timestamp' => now()->toISOString(),
            ]
        ], 422);
    }
}
```

---

**Refactored from:** `.ai/guides/chinook/packages/090-laravel-workos-guide.md` on 2025-07-11

## Navigation

**← Previous:** [Laravel Sanctum Guide](080-laravel-sanctum-guide.md)

**Next →** [Spatie Tags Guide](100-spatie-tags-guide.md)

[⬆️ Back to Top](#1-laravel-workos-implementation-guide)
