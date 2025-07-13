# 1. Laravel WorkOS Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/090-laravel-workos-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel WorkOS Implementation Guide](#1-laravel-workos-implementation-guide)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. Configuration Publishing](#122-configuration-publishing)
    - [1.2.3. Environment Setup](#123-environment-setup)
  - [1.3. Authentication Integration](#13-authentication-integration)
    - [1.3.1. SSO Configuration](#131-sso-configuration)
    - [1.3.2. OAuth Flow Implementation](#132-oauth-flow-implementation)
    - [1.3.3. User Provisioning](#133-user-provisioning)
  - [1.4. Directory Sync](#14-directory-sync)
    - [1.4.1. Webhook Configuration](#141-webhook-configuration)
    - [1.4.2. User Synchronization](#142-user-synchronization)
    - [1.4.3. Group Management](#143-group-management)
  - [1.5. RBAC Integration](#15-rbac-integration)
    - [1.5.1. Role Mapping](#151-role-mapping)
    - [1.5.2. Permission Synchronization](#152-permission-synchronization)
    - [1.5.3. Dynamic Role Assignment](#153-dynamic-role-assignment)
  - [1.6. API Integration](#16-api-integration)
    - [1.6.1. WorkOS API Client](#161-workos-api-client)
    - [1.6.2. Organization Management](#162-organization-management)
    - [1.6.3. Audit Logs](#163-audit-logs)
  - [1.7. Security & Compliance](#17-security--compliance)
    - [1.7.1. Security Best Practices](#171-security-best-practices)
    - [1.7.2. Compliance Features](#172-compliance-features)
    - [1.7.3. Data Protection](#173-data-protection)
  - [1.8. Testing Strategies](#18-testing-strategies)
    - [1.8.1. Authentication Testing](#181-authentication-testing)
    - [1.8.2. Directory Sync Testing](#182-directory-sync-testing)
  - [1.9. Monitoring & Analytics](#19-monitoring--analytics)
    - [1.9.1. Authentication Monitoring](#191-authentication-monitoring)
    - [1.9.2. Performance Tracking](#192-performance-tracking)
  - [1.10. Troubleshooting](#110-troubleshooting)
    - [1.10.1. Common Issues](#1101-common-issues)
    - [1.10.2. Debug Tools](#1102-debug-tools)
    - [1.10.3. Error Handling](#1103-error-handling)
  - [1.11. Navigation](#111-navigation)

## 1.1. Overview

Laravel WorkOS provides enterprise-grade authentication with SSO, directory sync, and comprehensive user management capabilities. This guide covers enterprise-level implementation with RBAC integration, security compliance, and production deployment strategies for the Chinook music store application.

**🚀 Key Features:**
- **Enterprise SSO**: Single Sign-On integration with major identity providers for Chinook enterprise customers
- **Directory Synchronization**: Automated user and group sync from corporate directories
- **RBAC Integration**: Seamless integration with spatie/laravel-permission for role-based access control
- **Multi-Tenant Support**: Organization-based access control for Chinook business customers
- **Audit Logging**: Comprehensive authentication and access logging for compliance
- **Security Compliance**: SOC 2, GDPR, and enterprise security standards compliance
- **Taxonomy Integration**: Enterprise-level access control for aliziodev/laravel-taxonomy operations

**🎵 Chinook-Specific Benefits:**
- **Enterprise Customer Access**: SSO integration for corporate Chinook music licensing customers
- **Team Management**: Automated provisioning for music production teams and record labels
- **License Management**: Role-based access to different music licensing tiers and catalogs
- **Compliance Tracking**: Audit trails for music licensing and usage compliance
- **Multi-Organization Support**: Separate access controls for different music industry clients
- **Admin Panel Integration**: Enterprise-grade authentication for Chinook administrative functions

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel WorkOS for the Chinook application:

```bash
# Install WorkOS PHP SDK
composer require workos/workos-php

# Install Laravel Socialite for OAuth integration
composer require laravel/socialite

# Install additional packages for Chinook integration
composer require spatie/laravel-permission # For RBAC
composer require spatie/laravel-activitylog # For audit logging
```

**Verification Steps:**

```bash
# Verify WorkOS installation
php artisan tinker
>>> use WorkOS\WorkOS;
>>> $workos = new WorkOS('your-api-key');
>>> $workos->getApiKey()

# Expected output: Your WorkOS API key
```

### 1.2.2. Configuration Publishing

Configure WorkOS for Chinook operations:

```php
// config/workos.php
return [
    /*
     * WorkOS API configuration
     */
    'api_key' => env('WORKOS_API_KEY'),
    'client_id' => env('WORKOS_CLIENT_ID'),
    'redirect_uri' => env('WORKOS_REDIRECT_URI'),
    'webhook_secret' => env('WORKOS_WEBHOOK_SECRET'),

    /*
     * Chinook-specific WorkOS configuration
     */
    'chinook' => [
        'default_organization' => env('CHINOOK_DEFAULT_ORG'),
        'admin_organization' => env('CHINOOK_ADMIN_ORG'),
        'enable_directory_sync' => env('CHINOOK_DIRECTORY_SYNC', true),
        'auto_provision_users' => env('CHINOOK_AUTO_PROVISION', true),
        'default_role' => env('CHINOOK_DEFAULT_ROLE', 'Customer Service'),
    ],

    /*
     * SSO provider configuration for Chinook enterprise customers
     */
    'sso_providers' => [
        'google' => [
            'enabled' => env('WORKOS_GOOGLE_SSO', true),
            'domains' => explode(',', env('WORKOS_GOOGLE_DOMAINS', '')),
        ],
        'microsoft' => [
            'enabled' => env('WORKOS_MICROSOFT_SSO', true),
            'domains' => explode(',', env('WORKOS_MICROSOFT_DOMAINS', '')),
        ],
        'okta' => [
            'enabled' => env('WORKOS_OKTA_SSO', true),
            'domains' => explode(',', env('WORKOS_OKTA_DOMAINS', '')),
        ],
        'saml' => [
            'enabled' => env('WORKOS_SAML_SSO', true),
            'default_connection' => env('WORKOS_SAML_CONNECTION'),
        ],
    ],

    /*
     * Directory sync configuration
     */
    'directory_sync' => [
        'enabled' => env('WORKOS_DIRECTORY_SYNC', true),
        'auto_create_users' => env('WORKOS_AUTO_CREATE_USERS', true),
        'auto_update_users' => env('WORKOS_AUTO_UPDATE_USERS', true),
        'auto_deactivate_users' => env('WORKOS_AUTO_DEACTIVATE_USERS', true),
        'sync_groups' => env('WORKOS_SYNC_GROUPS', true),
        'group_role_mapping' => [
            'Chinook Admins' => 'Admin',
            'Music Managers' => 'Manager',
            'Content Editors' => 'Editor',
            'Customer Support' => 'Customer Service',
            'API Users' => 'User',
        ],
    ],

    /*
     * Role mapping for Chinook organizations
     */
    'role_mapping' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'editor' => 'Editor',
        'customer_service' => 'Customer Service',
        'user' => 'User',
        'guest' => 'Guest',
    ],

    /*
     * Audit logging configuration
     */
    'audit_logging' => [
        'enabled' => env('WORKOS_AUDIT_LOGGING', true),
        'log_authentication' => true,
        'log_authorization' => true,
        'log_directory_sync' => true,
        'log_role_changes' => true,
        'retention_days' => env('WORKOS_AUDIT_RETENTION', 90),
    ],

    /*
     * Security settings
     */
    'security' => [
        'enforce_mfa' => env('WORKOS_ENFORCE_MFA', false),
        'session_timeout' => env('WORKOS_SESSION_TIMEOUT', 480), // 8 hours
        'max_concurrent_sessions' => env('WORKOS_MAX_SESSIONS', 3),
        'ip_whitelist' => explode(',', env('WORKOS_IP_WHITELIST', '')),
    ],
];
```

### 1.2.3. Environment Setup

Configure environment variables for Chinook WorkOS:

```bash
# .env configuration
WORKOS_API_KEY=sk_test_your_workos_api_key
WORKOS_CLIENT_ID=client_your_workos_client_id
WORKOS_REDIRECT_URI=https://chinook-music.com/auth/workos/callback
WORKOS_WEBHOOK_SECRET=your_webhook_secret

# Chinook organization settings
CHINOOK_DEFAULT_ORG=org_chinook_music_default
CHINOOK_ADMIN_ORG=org_chinook_music_admin
CHINOOK_DIRECTORY_SYNC=true
CHINOOK_AUTO_PROVISION=true
CHINOOK_DEFAULT_ROLE="Customer Service"

# SSO provider configuration
WORKOS_GOOGLE_SSO=true
WORKOS_GOOGLE_DOMAINS=chinook-music.com,chinookmusic.com
WORKOS_MICROSOFT_SSO=true
WORKOS_MICROSOFT_DOMAINS=chinook-music.com
WORKOS_OKTA_SSO=true
WORKOS_SAML_SSO=true

# Directory sync settings
WORKOS_DIRECTORY_SYNC=true
WORKOS_AUTO_CREATE_USERS=true
WORKOS_AUTO_UPDATE_USERS=true
WORKOS_AUTO_DEACTIVATE_USERS=true
WORKOS_SYNC_GROUPS=true

# Security settings
WORKOS_ENFORCE_MFA=false
WORKOS_SESSION_TIMEOUT=480
WORKOS_MAX_SESSIONS=3
WORKOS_AUDIT_LOGGING=true
WORKOS_AUDIT_RETENTION=90

# Performance settings
WORKOS_CACHE_TTL=3600
WORKOS_RATE_LIMIT=100
WORKOS_TIMEOUT=30
```

**Service Provider Registration:**

```php
// config/services.php
'workos' => [
    'api_key' => env('WORKOS_API_KEY'),
    'client_id' => env('WORKOS_CLIENT_ID'),
    'redirect' => env('WORKOS_REDIRECT_URI'),
    'webhook_secret' => env('WORKOS_WEBHOOK_SECRET'),
],

// Socialite configuration for WorkOS
'workos_sso' => [
    'client_id' => env('WORKOS_CLIENT_ID'),
    'client_secret' => env('WORKOS_API_KEY'),
    'redirect' => env('WORKOS_REDIRECT_URI'),
],
```

## 1.3. Authentication Integration

### 1.3.1. SSO Configuration

Configure SSO for Chinook enterprise customers:

```php
// app/Services/ChinookWorkOSService.php
<?php

namespace App\Services;

use WorkOS\WorkOS;
use WorkOS\SSO;
use WorkOS\DirectorySync;
use WorkOS\Organizations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChinookWorkOSService
{
    private WorkOS $workos;
    private SSO $sso;
    private DirectorySync $directorySync;
    private Organizations $organizations;

    public function __construct()
    {
        $this->workos = new WorkOS(config('workos.api_key'));
        $this->sso = $this->workos->sso;
        $this->directorySync = $this->workos->directorySync;
        $this->organizations = $this->workos->organizations;
    }

    public function getAuthorizationUrl(string $domain, ?string $organization = null): string
    {
        $params = [
            'client_id' => config('workos.client_id'),
            'redirect_uri' => config('workos.redirect_uri'),
            'response_type' => 'code',
            'state' => $this->generateState(),
        ];

        // Add domain hint for Chinook enterprise customers
        if ($domain) {
            $params['domain_hint'] = $domain;
        }

        // Add organization for multi-tenant Chinook setup
        if ($organization) {
            $params['organization'] => $organization;
        }

        return $this->sso->getAuthorizationUrl($params);
    }

    public function authenticateUser(string $code): array
    {
        try {
            $profile = $this->sso->getProfile([
                'code' => $code,
                'client_id' => config('workos.client_id'),
            ]);

            // Log authentication attempt for Chinook audit
            Log::info('Chinook WorkOS authentication attempt', [
                'user_id' => $profile->getId(),
                'email' => $profile->getEmail(),
                'organization_id' => $profile->getOrganizationId(),
                'connection_type' => $profile->getConnectionType(),
            ]);

            return [
                'success' => true,
                'profile' => $profile,
                'user_data' => $this->extractUserData($profile),
            ];
        } catch (\Exception $e) {
            Log::error('Chinook WorkOS authentication failed', [
                'error' => $e->getMessage(),
                'code' => $code,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getOrganizations(): array
    {
        $cacheKey = 'chinook_workos_organizations';

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $organizations = $this->organizations->listOrganizations();

                return array_map(function ($org) {
                    return [
                        'id' => $org->getId(),
                        'name' => $org->getName(),
                        'domains' => $org->getDomains(),
                        'allow_profiles_outside_organization' => $org->getAllowProfilesOutsideOrganization(),
                    ];
                }, $organizations->getData());
            } catch (\Exception $e) {
                Log::error('Failed to fetch Chinook WorkOS organizations', [
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    private function extractUserData($profile): array
    {
        return [
            'workos_id' => $profile->getId(),
            'email' => $profile->getEmail(),
            'first_name' => $profile->getFirstName(),
            'last_name' => $profile->getLastName(),
            'organization_id' => $profile->getOrganizationId(),
            'connection_id' => $profile->getConnectionId(),
            'connection_type' => $profile->getConnectionType(),
            'raw_attributes' => $profile->getRawAttributes(),
        ];
    }

    private function generateState(): string
    {
        $state = bin2hex(random_bytes(16));
        Cache::put("workos_state_{$state}", true, 600); // 10 minutes
        return $state;
    }

    public function validateState(string $state): bool
    {
        $isValid = Cache::has("workos_state_{$state}");
        if ($isValid) {
            Cache::forget("workos_state_{$state}");
        }
        return $isValid;
    }
}
```

### 1.3.2. OAuth Flow Implementation

Implement OAuth flow for Chinook authentication:

```php
// app/Http/Controllers/Auth/ChinookWorkOSController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ChinookWorkOSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChinookWorkOSController extends Controller
{
    public function __construct(
        private ChinookWorkOSService $workosService
    ) {}

    public function redirect(Request $request)
    {
        $domain = $request->get('domain');
        $organization = $request->get('organization');

        // Validate domain for Chinook enterprise customers
        if ($domain && !$this->isAllowedDomain($domain)) {
            return redirect()->route('login')->withErrors([
                'domain' => 'Domain not authorized for Chinook enterprise access.',
            ]);
        }

        $authUrl = $this->workosService->getAuthorizationUrl($domain, $organization);

        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');

        // Validate state parameter
        if (!$state || !$this->workosService->validateState($state)) {
            return redirect()->route('login')->withErrors([
                'auth' => 'Invalid authentication state.',
            ]);
        }

        $authResult = $this->workosService->authenticateUser($code);

        if (!$authResult['success']) {
            return redirect()->route('login')->withErrors([
                'auth' => 'Authentication failed: ' . $authResult['error'],
            ]);
        }

        $userData = $authResult['user_data'];
        $user = $this->findOrCreateUser($userData);

        if (!$user) {
            return redirect()->route('login')->withErrors([
                'auth' => 'User provisioning failed.',
            ]);
        }

        // Update user's last login and WorkOS data
        $this->updateUserWorkOSData($user, $userData);

        Auth::login($user);

        // Redirect to intended page or Chinook dashboard
        return redirect()->intended(route('chinook.dashboard'));
    }

    public function organizations()
    {
        $organizations = $this->workosService->getOrganizations();

        return response()->json([
            'organizations' => $organizations,
        ]);
    }

    private function isAllowedDomain(string $domain): bool
    {
        $allowedDomains = array_merge(
            config('workos.sso_providers.google.domains', []),
            config('workos.sso_providers.microsoft.domains', []),
            config('workos.sso_providers.okta.domains', [])
        );

        return in_array($domain, $allowedDomains);
    }

    private function findOrCreateUser(array $userData): ?User
    {
        return DB::transaction(function () use ($userData) {
            $user = User::where('email', $userData['email'])->first();

            if (!$user && config('workos.chinook.auto_provision_users')) {
                $user = $this->createUser($userData);
            }

            return $user;
        });
    }

    private function createUser(array $userData): User
    {
        $user = User::create([
            'name' => trim($userData['first_name'] . ' ' . $userData['last_name']),
            'email' => $userData['email'],
            'email_verified_at' => now(),
            'workos_id' => $userData['workos_id'],
            'workos_organization_id' => $userData['organization_id'],
            'workos_connection_id' => $userData['connection_id'],
            'workos_connection_type' => $userData['connection_type'],
        ]);

        // Assign default role for Chinook users
        $defaultRole = config('workos.chinook.default_role', 'Customer Service');
        $user->assignRole($defaultRole);

        // Log user creation for Chinook audit
        activity()
            ->causedBy($user)
            ->log('Chinook user created via WorkOS SSO');

        return $user;
    }

    private function updateUserWorkOSData(User $user, array $userData): void
    {
        $user->update([
            'workos_id' => $userData['workos_id'],
            'workos_organization_id' => $userData['organization_id'],
            'workos_connection_id' => $userData['connection_id'],
            'workos_connection_type' => $userData['connection_type'],
            'last_login_at' => now(),
        ]);

        // Update user roles based on organization
        $this->syncUserRoles($user, $userData);
    }

    private function syncUserRoles(User $user, array $userData): void
    {
        // Implement role synchronization based on WorkOS organization
        $organizationId = $userData['organization_id'];

        // Map organization to roles for Chinook
        $roleMapping = [
            config('workos.chinook.admin_organization') => 'Admin',
            config('workos.chinook.default_organization') => 'Customer Service',
        ];

        $role = $roleMapping[$organizationId] ?? config('workos.chinook.default_role');

        if ($role && !$user->hasRole($role)) {
            $user->syncRoles([$role]);

            activity()
                ->causedBy($user)
                ->withProperties(['role' => $role, 'organization' => $organizationId])
                ->log('Chinook user role synchronized via WorkOS');
        }
    }
}
```

### 1.3.3. User Provisioning

Implement automated user provisioning for Chinook:

```php
// app/Services/ChinookUserProvisioningService.php
<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChinookUserProvisioningService
{
    public function provisionUser(array $workosData): ?User
    {
        return DB::transaction(function () use ($workosData) {
            try {
                $user = $this->createOrUpdateUser($workosData);
                $this->assignUserRoles($user, $workosData);
                $this->assignTaxonomyPermissions($user, $workosData);
                $this->logProvisioningActivity($user, $workosData);

                return $user;
            } catch (\Exception $e) {
                Log::error('Chinook user provisioning failed', [
                    'workos_data' => $workosData,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    private function createOrUpdateUser(array $workosData): User
    {
        return User::updateOrCreate(
            ['email' => $workosData['email']],
            [
                'name' => trim($workosData['first_name'] . ' ' . $workosData['last_name']),
                'email_verified_at' => now(),
                'workos_id' => $workosData['workos_id'],
                'workos_organization_id' => $workosData['organization_id'],
                'workos_connection_id' => $workosData['connection_id'],
                'workos_connection_type' => $workosData['connection_type'],
            ]
        );
    }

    private function assignUserRoles(User $user, array $workosData): void
    {
        $organizationId = $workosData['organization_id'];
        $role = $this->determineUserRole($organizationId, $workosData);

        if ($role && Role::where('name', $role)->exists()) {
            $user->syncRoles([$role]);
        }
    }

    private function assignTaxonomyPermissions(User $user, array $workosData): void
    {
        // Assign taxonomy permissions based on role
        if ($user->hasRole(['Admin', 'Manager'])) {
            $user->givePermissionTo([
                'taxonomy:read',
                'taxonomy:create',
                'taxonomy:update',
                'taxonomy:delete',
            ]);
        } elseif ($user->hasRole(['Editor', 'Customer Service'])) {
            $user->givePermissionTo([
                'taxonomy:read',
                'taxonomy:create',
                'taxonomy:update',
            ]);
        } else {
            $user->givePermissionTo(['taxonomy:read']);
        }
    }

    private function determineUserRole(string $organizationId, array $workosData): string
    {
        // Map WorkOS organization to Chinook roles
        $orgRoleMapping = config('workos.directory_sync.group_role_mapping', []);

        // Check if user belongs to specific groups
        $groups = $workosData['raw_attributes']['groups'] ?? [];

        foreach ($groups as $group) {
            if (isset($orgRoleMapping[$group])) {
                return $orgRoleMapping[$group];
            }
        }

        // Default role based on organization
        if ($organizationId === config('workos.chinook.admin_organization')) {
            return 'Admin';
        }

        return config('workos.chinook.default_role', 'Customer Service');
    }

    private function logProvisioningActivity(User $user, array $workosData): void
    {
        activity()
            ->causedBy($user)
            ->withProperties([
                'workos_organization' => $workosData['organization_id'],
                'connection_type' => $workosData['connection_type'],
                'roles' => $user->roles->pluck('name')->toArray(),
            ])
            ->log('Chinook user provisioned via WorkOS');
    }
}
```

## 1.10. Troubleshooting

### 1.10.1. Common Issues

**SSO Authentication Issues:**

```bash
# Check WorkOS configuration
php artisan config:show workos

# Verify API connectivity
php artisan tinker
>>> $workos = new \WorkOS\WorkOS(config('workos.api_key'));
>>> $workos->organizations->listOrganizations();

# Check user provisioning
>>> App\Models\User::where('workos_id', 'user_123')->first()
```

**Directory Sync Issues:**

```bash
# Check webhook configuration
php artisan route:list | grep workos

# Verify webhook secret
php artisan tinker
>>> config('workos.webhook_secret')

# Check directory sync logs
tail -f storage/logs/laravel.log | grep "WorkOS Directory Sync"
```

### 1.10.2. Debug Tools

Debug WorkOS integration:

```php
// app/Console/Commands/ChinookWorkOSDebug.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ChinookWorkOSService;

class ChinookWorkOSDebug extends Command
{
    protected $signature = 'chinook:workos-debug {--test-auth} {--list-orgs} {--check-users}';
    protected $description = 'Debug Chinook WorkOS integration';

    public function handle(ChinookWorkOSService $workosService): void
    {
        if ($this->option('test-auth')) {
            $this->testAuthentication($workosService);
        }

        if ($this->option('list-orgs')) {
            $this->listOrganizations($workosService);
        }

        if ($this->option('check-users')) {
            $this->checkUsers();
        }
    }

    private function testAuthentication(ChinookWorkOSService $workosService): void
    {
        $this->info('Testing WorkOS authentication...');

        try {
            $authUrl = $workosService->getAuthorizationUrl('chinook-music.com');
            $this->info("Auth URL generated: {$authUrl}");
        } catch (\Exception $e) {
            $this->error("Authentication test failed: {$e->getMessage()}");
        }
    }

    private function listOrganizations(ChinookWorkOSService $workosService): void
    {
        $this->info('Fetching WorkOS organizations...');

        $organizations = $workosService->getOrganizations();

        if (empty($organizations)) {
            $this->warn('No organizations found');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Domains'],
            array_map(function ($org) {
                return [
                    $org['id'],
                    $org['name'],
                    implode(', ', $org['domains']),
                ];
            }, $organizations)
        );
    }

    private function checkUsers(): void
    {
        $this->info('Checking WorkOS users...');

        $users = \App\Models\User::whereNotNull('workos_id')->get();

        $this->table(
            ['ID', 'Name', 'Email', 'WorkOS ID', 'Organization', 'Roles'],
            $users->map(function ($user) {
                return [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->workos_id,
                    $user->workos_organization_id,
                    $user->roles->pluck('name')->implode(', '),
                ];
            })->toArray()
        );
    }
}
```

## 1.11. Navigation

**← Previous:** [Laravel Sanctum Guide](080-laravel-sanctum-guide.md)

**Next →** [Laravel Filament Guide](100-laravel-filament-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel WorkOS implementation guide provides enterprise-grade authentication and user management capabilities for the Chinook music store application, including:

- **Enterprise SSO Integration**: Seamless single sign-on for corporate Chinook customers with major identity providers
- **Automated User Provisioning**: Dynamic user creation and role assignment based on organizational structure
- **RBAC Integration**: Complete integration with spatie/laravel-permission for hierarchical role management
- **Directory Synchronization**: Real-time user and group sync from corporate directories
- **Multi-Tenant Support**: Organization-based access control for different music industry clients
- **Audit & Compliance**: Comprehensive logging and tracking for enterprise security requirements
- **Taxonomy Integration**: Enterprise-level access control for aliziodev/laravel-taxonomy operations

The implementation leverages WorkOS's enterprise capabilities while providing Chinook-specific optimizations for music industry workflows, licensing management, and corporate customer access with complete security and compliance coverage.
