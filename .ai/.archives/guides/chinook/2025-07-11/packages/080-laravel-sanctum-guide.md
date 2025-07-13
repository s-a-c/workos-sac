# 1. Laravel Sanctum Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/080-laravel-sanctum-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Sanctum Implementation Guide](#1-laravel-sanctum-implementation-guide)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. API Installation](#122-api-installation)
    - [1.2.3. Configuration Setup](#123-configuration-setup)
  - [1.3. API Token Authentication](#13-api-token-authentication)
    - [1.3.1. Token Generation](#131-token-generation)
    - [1.3.2. Token Management](#132-token-management)
    - [1.3.3. Token Abilities](#133-token-abilities)
  - [1.4. SPA Authentication](#14-spa-authentication)
    - [1.4.1. CSRF Protection](#141-csrf-protection)
    - [1.4.2. Session-Based Auth](#142-session-based-auth)
    - [1.4.3. Frontend Integration](#143-frontend-integration)
  - [1.5. Mobile App Integration](#15-mobile-app-integration)
    - [1.5.1. Mobile Token Flow](#151-mobile-token-flow)
    - [1.5.2. Device Management](#152-device-management)
    - [1.5.3. Push Notifications](#153-push-notifications)
  - [1.6. Security Best Practices](#16-security-best-practices)
    - [1.6.1. Token Security](#161-token-security)
    - [1.6.2. Environment Security](#162-environment-security)
    - [1.6.3. Authentication Monitoring](#163-authentication-monitoring)
  - [1.7. Rate Limiting](#17-rate-limiting)
    - [1.7.1. Custom Rate Limiters](#171-custom-rate-limiters)
    - [1.7.2. Middleware Implementation](#172-middleware-implementation)
  - [1.8. Testing Strategies](#18-testing-strategies)
    - [1.8.1. API Authentication Testing](#181-api-authentication-testing)
    - [1.8.2. Token Abilities Testing](#182-token-abilities-testing)
  - [1.9. Advanced Features](#19-advanced-features)
    - [1.9.1. Custom Token Guards](#191-custom-token-guards)
    - [1.9.2. Token Analytics](#192-token-analytics)
  - [1.10. Troubleshooting](#110-troubleshooting)
    - [1.10.1. Common Issues](#1101-common-issues)
    - [1.10.2. Debug Commands](#1102-debug-commands)
    - [1.10.3. Performance Optimization](#1103-performance-optimization)
  - [1.11. Navigation](#111-navigation)

## 1.1. Overview

Laravel Sanctum provides modern API authentication with token management, SPA integration, and comprehensive security features. This guide covers enterprise-level implementation with mobile app integration, advanced security practices, and production deployment strategies for the Chinook music store application.

**🚀 Key Features:**
- **Multiple Authentication Types**: API tokens and SPA cookie authentication for Chinook customers
- **Token Abilities**: Granular permission system for music catalog and customer data access
- **CSRF Protection**: Built-in CSRF protection for Chinook SPA applications
- **Rate Limiting**: Advanced rate limiting with custom throttling for music streaming APIs
- **Security Monitoring**: Authentication attempt tracking and security logging for customer accounts
- **Mobile Integration**: Comprehensive mobile app authentication workflows for Chinook mobile apps
- **Taxonomy Integration**: Secure access control for aliziodev/laravel-taxonomy operations

**🎵 Chinook-Specific Benefits:**
- **Customer Authentication**: Secure customer login and registration for music purchases
- **API Access Control**: Granular permissions for music catalog, playlists, and customer data
- **Mobile Music Apps**: Token-based authentication for iOS and Android Chinook apps
- **Third-Party Integrations**: Secure API access for music streaming partners and services
- **Admin Panel Security**: Role-based access control for Chinook administrative functions
- **Music Streaming Security**: Secure token validation for audio file access and downloads

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Sanctum for the Chinook application:

```bash
# Install Laravel Sanctum
composer require laravel/sanctum

# Publish Sanctum configuration and migrations
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run migrations to create personal access tokens table
php artisan migrate

# Add Sanctum middleware to API routes
# This is automatically done in Laravel 11+
```

**Verification Steps:**

```bash
# Verify Sanctum installation
php artisan tinker
>>> use Laravel\Sanctum\Sanctum;
>>> Sanctum::$personalAccessTokenModel

# Expected output: Laravel\Sanctum\PersonalAccessToken
```

### 1.2.2. API Installation

Configure Sanctum for Chinook API authentication:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ],

    'api' => [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

// Add Sanctum middleware aliases for Chinook
protected $middlewareAliases = [
    // ... existing aliases
    'auth.sanctum' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
    'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
    
    // Chinook-specific middleware
    'chinook.customer' => \App\Http\Middleware\ChinookCustomerAuth::class,
    'chinook.admin' => \App\Http\Middleware\ChinookAdminAuth::class,
    'chinook.api.throttle' => \App\Http\Middleware\ChinookApiThrottle::class,
];
```

### 1.2.3. Configuration Setup

Configure Sanctum for optimal Chinook operations:

```php
// config/sanctum.php
return [
    /*
     * Sanctum will delete tokens that have been expired for this number of minutes.
     */
    'expiration' => env('SANCTUM_EXPIRATION', null),

    /*
     * Sanctum will delete tokens that have been expired for this number of minutes.
     */
    'token_expiration' => [
        'customer' => env('SANCTUM_CUSTOMER_TOKEN_EXPIRATION', 60 * 24 * 30), // 30 days
        'admin' => env('SANCTUM_ADMIN_TOKEN_EXPIRATION', 60 * 8), // 8 hours
        'mobile' => env('SANCTUM_MOBILE_TOKEN_EXPIRATION', 60 * 24 * 90), // 90 days
        'api' => env('SANCTUM_API_TOKEN_EXPIRATION', 60 * 24 * 7), // 7 days
    ],

    /*
     * The guard to use when authenticating users.
     */
    'guard' => ['web'],

    /*
     * Stateful domains for Chinook SPA applications.
     */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : '',
        env('FRONTEND_URL') ? ','.parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : ''
    ))),

    /*
     * Chinook-specific stateful domains
     */
    'chinook_domains' => [
        'web' => env('CHINOOK_WEB_DOMAIN', 'chinook-music.com'),
        'admin' => env('CHINOOK_ADMIN_DOMAIN', 'admin.chinook-music.com'),
        'api' => env('CHINOOK_API_DOMAIN', 'api.chinook-music.com'),
        'mobile' => env('CHINOOK_MOBILE_DOMAIN', 'mobile.chinook-music.com'),
    ],

    /*
     * Sanctum will prefix all database tables with this string.
     */
    'prefix' => env('SANCTUM_PREFIX', ''),

    /*
     * Chinook token abilities for granular access control
     */
    'abilities' => [
        // Customer abilities
        'customer:read' => 'Read customer profile information',
        'customer:update' => 'Update customer profile information',
        'customer:delete' => 'Delete customer account',
        
        // Music catalog abilities
        'music:read' => 'Read music catalog (tracks, albums, artists)',
        'music:stream' => 'Stream music tracks',
        'music:download' => 'Download music tracks',
        'music:purchase' => 'Purchase music tracks and albums',
        
        // Playlist abilities
        'playlist:read' => 'Read playlists',
        'playlist:create' => 'Create new playlists',
        'playlist:update' => 'Update existing playlists',
        'playlist:delete' => 'Delete playlists',
        'playlist:share' => 'Share playlists with other users',
        
        // Invoice abilities
        'invoice:read' => 'Read purchase history and invoices',
        'invoice:create' => 'Create new invoices',
        
        // Taxonomy abilities
        'taxonomy:read' => 'Read taxonomy data (genres, categories)',
        'taxonomy:manage' => 'Manage taxonomy data (admin only)',
        
        // Admin abilities
        'admin:read' => 'Read administrative data',
        'admin:manage' => 'Manage administrative functions',
        'admin:reports' => 'Access business reports and analytics',
        
        // API abilities
        'api:read' => 'Read-only API access',
        'api:write' => 'Read-write API access',
        'api:admin' => 'Administrative API access',
    ],

    /*
     * Default abilities for different user types
     */
    'default_abilities' => [
        'customer' => [
            'customer:read',
            'customer:update',
            'music:read',
            'music:stream',
            'playlist:read',
            'playlist:create',
            'playlist:update',
            'playlist:delete',
            'invoice:read',
            'taxonomy:read',
        ],
        'admin' => [
            '*', // All abilities
        ],
        'mobile' => [
            'customer:read',
            'customer:update',
            'music:read',
            'music:stream',
            'music:download',
            'playlist:read',
            'playlist:create',
            'playlist:update',
            'playlist:delete',
            'playlist:share',
            'invoice:read',
            'taxonomy:read',
        ],
        'api' => [
            'api:read',
            'music:read',
            'taxonomy:read',
        ],
    ],
];
```

**Environment Configuration:**

```bash
# .env additions for Chinook Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,chinook-music.com,admin.chinook-music.com
SANCTUM_CUSTOMER_TOKEN_EXPIRATION=43200  # 30 days in minutes
SANCTUM_ADMIN_TOKEN_EXPIRATION=480       # 8 hours in minutes
SANCTUM_MOBILE_TOKEN_EXPIRATION=129600   # 90 days in minutes
SANCTUM_API_TOKEN_EXPIRATION=10080       # 7 days in minutes

# Chinook domain configuration
CHINOOK_WEB_DOMAIN=chinook-music.com
CHINOOK_ADMIN_DOMAIN=admin.chinook-music.com
CHINOOK_API_DOMAIN=api.chinook-music.com
CHINOOK_MOBILE_DOMAIN=mobile.chinook-music.com

# Security settings
CHINOOK_TOKEN_ENCRYPTION=true
CHINOOK_RATE_LIMIT_ENABLED=true
CHINOOK_AUTH_LOGGING=true
```

## 1.3. API Token Authentication

### 1.3.1. Token Generation

Implement token generation for Chinook customers and applications:

```php
// app/Http/Controllers/Auth/ChinookTokenController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ChinookCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChinookTokenController extends Controller
{
    public function createCustomerToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
            'device_type' => 'required|in:web,mobile,desktop',
        ]);

        $customer = ChinookCustomer::where('email', $request->email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Determine abilities based on device type and customer role
        $abilities = $this->getCustomerAbilities($customer, $request->device_type);

        // Create token with appropriate expiration
        $token = $customer->createToken(
            name: $request->device_name,
            abilities: $abilities,
            expiresAt: $this->getTokenExpiration($request->device_type)
        );

        // Log token creation for security monitoring
        $this->logTokenCreation($customer, $request);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'abilities' => $abilities,
            'customer' => [
                'id' => $customer->public_id,
                'name' => $customer->first_name . ' ' . $customer->last_name,
                'email' => $customer->email,
            ],
        ]);
    }

    public function createApiToken(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'application_name' => 'required|string|max:255',
            'requested_abilities' => 'array',
            'requested_abilities.*' => 'string',
        ]);

        // Validate API credentials (implement your own validation logic)
        if (!$this->validateApiCredentials($request->api_key, $request->api_secret)) {
            throw ValidationException::withMessages([
                'api_key' => ['Invalid API credentials.'],
            ]);
        }

        // Create a system user for API access
        $apiUser = $this->getOrCreateApiUser($request->api_key);

        // Determine allowed abilities for this API key
        $allowedAbilities = $this->getApiAbilities($request->api_key);
        $requestedAbilities = $request->requested_abilities ?? ['api:read'];
        $abilities = array_intersect($requestedAbilities, $allowedAbilities);

        $token = $apiUser->createToken(
            name: $request->application_name,
            abilities: $abilities,
            expiresAt: now()->addDays(config('sanctum.token_expiration.api', 7))
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'abilities' => $abilities,
        ]);
    }

    public function revokeToken(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }

    public function revokeAllTokens(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'All tokens revoked successfully.',
        ]);
    }

    private function getCustomerAbilities(ChinookCustomer $customer, string $deviceType): array
    {
        $baseAbilities = config('sanctum.default_abilities.customer', []);

        // Add device-specific abilities
        if ($deviceType === 'mobile') {
            $baseAbilities = array_merge($baseAbilities, [
                'music:download',
                'playlist:share',
            ]);
        }

        // Add role-based abilities if customer has admin role
        if ($customer->hasRole('Admin')) {
            $baseAbilities = array_merge($baseAbilities, [
                'admin:read',
                'admin:manage',
                'taxonomy:manage',
            ]);
        }

        return $baseAbilities;
    }

    private function getTokenExpiration(string $deviceType): ?\DateTime
    {
        $minutes = match($deviceType) {
            'mobile' => config('sanctum.token_expiration.mobile', 129600),
            'web' => config('sanctum.token_expiration.customer', 43200),
            'desktop' => config('sanctum.token_expiration.customer', 43200),
            default => config('sanctum.token_expiration.customer', 43200),
        };

        return now()->addMinutes($minutes);
    }

    private function logTokenCreation(ChinookCustomer $customer, Request $request): void
    {
        logger()->info('Chinook customer token created', [
            'customer_id' => $customer->public_id,
            'device_name' => $request->device_name,
            'device_type' => $request->device_type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function validateApiCredentials(string $apiKey, string $apiSecret): bool
    {
        // Implement your API credential validation logic
        // This could check against a database of registered API clients
        return true; // Placeholder
    }

    private function getOrCreateApiUser(string $apiKey)
    {
        // Implement logic to get or create a system user for API access
        // This could be a special user type or a separate model
        return ChinookCustomer::firstOrCreate(['email' => "api+{$apiKey}@chinook-music.com"]);
    }

    private function getApiAbilities(string $apiKey): array
    {
        // Implement logic to determine abilities for this API key
        return config('sanctum.default_abilities.api', ['api:read']);
    }
}
```

### 1.3.2. Token Management

Implement comprehensive token management for Chinook:

```php
// app/Http/Controllers/Auth/ChinookTokenManagementController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ChinookTokenManagementController extends Controller
{
    public function listTokens(Request $request)
    {
        $tokens = $request->user()->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $request->user()->currentAccessToken()->id,
            ];
        });

        return response()->json([
            'tokens' => $tokens,
            'total' => $tokens->count(),
        ]);
    }

    public function updateToken(Request $request, $tokenId)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string',
        ]);

        $token = $request->user()->tokens()->findOrFail($tokenId);

        if ($request->has('name')) {
            $token->name = $request->name;
        }

        if ($request->has('abilities')) {
            // Validate that user can assign these abilities
            $allowedAbilities = $this->getAllowedAbilities($request->user());
            $requestedAbilities = $request->abilities;

            if (!empty(array_diff($requestedAbilities, $allowedAbilities))) {
                return response()->json([
                    'error' => 'Some requested abilities are not allowed.',
                ], 403);
            }

            $token->abilities = $requestedAbilities;
        }

        $token->save();

        return response()->json([
            'message' => 'Token updated successfully.',
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
            ],
        ]);
    }

    public function revokeToken(Request $request, $tokenId)
    {
        $token = $request->user()->tokens()->findOrFail($tokenId);

        // Prevent revoking current token
        if ($token->id === $request->user()->currentAccessToken()->id) {
            return response()->json([
                'error' => 'Cannot revoke the current token.',
            ], 400);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }

    public function refreshToken(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();

        // Create new token with same abilities and device name
        $newToken = $request->user()->createToken(
            name: $currentToken->name,
            abilities: $currentToken->abilities,
            expiresAt: $this->getTokenExpiration($currentToken->name)
        );

        // Revoke old token
        $currentToken->delete();

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $newToken->accessToken->expires_at,
            'abilities' => $newToken->accessToken->abilities,
        ]);
    }

    private function getAllowedAbilities($user): array
    {
        if ($user->hasRole('Admin')) {
            return array_keys(config('sanctum.abilities', []));
        }

        return config('sanctum.default_abilities.customer', []);
    }

    private function getTokenExpiration(string $tokenName): ?\DateTime
    {
        // Determine expiration based on token name/type
        if (str_contains(strtolower($tokenName), 'mobile')) {
            return now()->addMinutes(config('sanctum.token_expiration.mobile', 129600));
        }

        return now()->addMinutes(config('sanctum.token_expiration.customer', 43200));
    }
}
```

### 1.3.3. Token Abilities

Implement granular token abilities for Chinook operations:

```php
// app/Http/Middleware/ChinookTokenAbilities.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

class ChinookTokenAbilities extends CheckAbilities
{
    public function handle(Request $request, Closure $next, ...$abilities)
    {
        // Check if user is authenticated via Sanctum
        if (!$request->user() || !$request->user()->currentAccessToken()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $token = $request->user()->currentAccessToken();

        // Check for wildcard ability (admin users)
        if (in_array('*', $token->abilities)) {
            return $next($request);
        }

        // Check specific abilities
        foreach ($abilities as $ability) {
            if (!$token->can($ability)) {
                return response()->json([
                    'error' => 'Insufficient permissions',
                    'required_ability' => $ability,
                    'current_abilities' => $token->abilities,
                ], 403);
            }
        }

        return $next($request);
    }
}
```

**Route Protection with Abilities:**

```php
// routes/api.php
use App\Http\Controllers\Api\ChinookMusicController;
use App\Http\Controllers\Api\ChinookPlaylistController;
use App\Http\Controllers\Api\ChinookCustomerController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Music catalog routes with read permissions
    Route::middleware(['ability:music:read'])->group(function () {
        Route::get('/tracks', [ChinookMusicController::class, 'tracks']);
        Route::get('/albums', [ChinookMusicController::class, 'albums']);
        Route::get('/artists', [ChinookMusicController::class, 'artists']);
        Route::get('/genres', [ChinookMusicController::class, 'genres']);
    });

    // Music streaming routes
    Route::middleware(['ability:music:stream'])->group(function () {
        Route::get('/tracks/{track}/stream', [ChinookMusicController::class, 'stream']);
    });

    // Music download routes (mobile apps)
    Route::middleware(['ability:music:download'])->group(function () {
        Route::get('/tracks/{track}/download', [ChinookMusicController::class, 'download']);
    });

    // Playlist management routes
    Route::middleware(['ability:playlist:read'])->group(function () {
        Route::get('/playlists', [ChinookPlaylistController::class, 'index']);
        Route::get('/playlists/{playlist}', [ChinookPlaylistController::class, 'show']);
    });

    Route::middleware(['ability:playlist:create'])->group(function () {
        Route::post('/playlists', [ChinookPlaylistController::class, 'store']);
    });

    Route::middleware(['ability:playlist:update'])->group(function () {
        Route::put('/playlists/{playlist}', [ChinookPlaylistController::class, 'update']);
        Route::post('/playlists/{playlist}/tracks', [ChinookPlaylistController::class, 'addTrack']);
        Route::delete('/playlists/{playlist}/tracks/{track}', [ChinookPlaylistController::class, 'removeTrack']);
    });

    Route::middleware(['ability:playlist:delete'])->group(function () {
        Route::delete('/playlists/{playlist}', [ChinookPlaylistController::class, 'destroy']);
    });

    // Customer profile routes
    Route::middleware(['ability:customer:read'])->group(function () {
        Route::get('/profile', [ChinookCustomerController::class, 'profile']);
        Route::get('/purchase-history', [ChinookCustomerController::class, 'purchaseHistory']);
    });

    Route::middleware(['ability:customer:update'])->group(function () {
        Route::put('/profile', [ChinookCustomerController::class, 'updateProfile']);
    });

    // Admin routes
    Route::middleware(['ability:admin:read'])->prefix('admin')->group(function () {
        Route::get('/customers', [ChinookCustomerController::class, 'adminIndex']);
        Route::get('/analytics', [ChinookAnalyticsController::class, 'index']);
    });

    Route::middleware(['ability:admin:manage'])->prefix('admin')->group(function () {
        Route::post('/customers/{customer}/suspend', [ChinookCustomerController::class, 'suspend']);
        Route::post('/customers/{customer}/activate', [ChinookCustomerController::class, 'activate']);
    });

    // Taxonomy management routes
    Route::middleware(['ability:taxonomy:read'])->group(function () {
        Route::get('/taxonomies', [ChinookTaxonomyController::class, 'index']);
        Route::get('/taxonomies/{taxonomy}', [ChinookTaxonomyController::class, 'show']);
    });

    Route::middleware(['ability:taxonomy:manage'])->group(function () {
        Route::post('/taxonomies', [ChinookTaxonomyController::class, 'store']);
        Route::put('/taxonomies/{taxonomy}', [ChinookTaxonomyController::class, 'update']);
        Route::delete('/taxonomies/{taxonomy}', [ChinookTaxonomyController::class, 'destroy']);
    });
});
```

## 1.4. SPA Authentication

### 1.4.1. CSRF Protection

Configure CSRF protection for Chinook SPA applications:

```php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'https://chinook-music.com',
        'https://admin.chinook-music.com',
    ],

    'allowed_origins_patterns' => [
        '#^https://.*\.chinook-music\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
```

**Frontend CSRF Setup:**

```javascript
// Frontend JavaScript for Chinook SPA
import axios from 'axios';

// Configure axios for Chinook API
const chinookApi = axios.create({
    baseURL: process.env.REACT_APP_API_URL || 'https://api.chinook-music.com',
    withCredentials: true,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

// CSRF token setup
let csrfToken = null;

const getCsrfToken = async () => {
    if (!csrfToken) {
        await chinookApi.get('/sanctum/csrf-cookie');
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }
    return csrfToken;
};

// Request interceptor to include CSRF token
chinookApi.interceptors.request.use(async (config) => {
    if (['post', 'put', 'patch', 'delete'].includes(config.method)) {
        const token = await getCsrfToken();
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
        }
    }
    return config;
});

// Response interceptor for error handling
chinookApi.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            // CSRF token mismatch - refresh and retry
            csrfToken = null;
            return getCsrfToken().then(() => chinookApi.request(error.config));
        }
        return Promise.reject(error);
    }
);

export default chinookApi;
```

### 1.4.2. Session-Based Auth

Implement session-based authentication for Chinook SPA:

```php
// app/Http/Controllers/Auth/ChinookSpaAuthController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ChinookCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChinookSpaAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $customer = ChinookCustomer::where('email', $request->email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Auth::login($customer, $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Logged in successfully',
            'customer' => [
                'id' => $customer->public_id,
                'name' => $customer->first_name . ' ' . $customer->last_name,
                'email' => $customer->email,
                'roles' => $customer->roles->pluck('name'),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function user(Request $request)
    {
        $customer = $request->user();

        if (!$customer) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'customer' => [
                'id' => $customer->public_id,
                'name' => $customer->first_name . ' ' . $customer->last_name,
                'email' => $customer->email,
                'roles' => $customer->roles->pluck('name'),
                'permissions' => $customer->getAllPermissions()->pluck('name'),
            ],
        ]);
    }
}
```

## 1.10. Troubleshooting

### 1.10.1. Common Issues

**Token Authentication Issues:**

```bash
# Check if Sanctum middleware is properly configured
php artisan route:list --middleware=auth:sanctum

# Verify token exists and is valid
php artisan tinker
>>> use Laravel\Sanctum\PersonalAccessToken;
>>> PersonalAccessToken::findToken('your-token-here')

# Check token abilities
>>> $token = PersonalAccessToken::findToken('your-token-here');
>>> $token->abilities
```

**CSRF Issues in SPA:**

```javascript
// Debug CSRF token issues
console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]')?.content);

// Check if CSRF cookie is set
console.log('Cookies:', document.cookie);

// Verify CORS configuration
fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    .then(response => console.log('CSRF Response:', response.status));
```

### 1.10.2. Debug Commands

Debug Sanctum configuration and tokens:

```bash
# Check Sanctum configuration
php artisan config:show sanctum

# List all active tokens
php artisan tinker
>>> Laravel\Sanctum\PersonalAccessToken::all()

# Check token expiration
>>> Laravel\Sanctum\PersonalAccessToken::where('expires_at', '<', now())->count()

# Clean up expired tokens
php artisan sanctum:prune-expired

# Monitor token usage
php artisan tinker
>>> Laravel\Sanctum\PersonalAccessToken::whereNotNull('last_used_at')->orderBy('last_used_at', 'desc')->take(10)->get()
```

## 1.11. Navigation

**← Previous:** [Laravel Fractal Guide](070-laravel-fractal-guide.md)

**Next →** [Laravel Filament Guide](090-laravel-filament-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Sanctum implementation guide provides comprehensive authentication and authorization capabilities for the Chinook music store application, including:

- **Multi-Platform Authentication**: Token-based authentication for web, mobile, and API access
- **Granular Permissions**: Detailed ability system for music catalog, customer data, and administrative functions
- **SPA Integration**: Seamless authentication for Chinook single-page applications with CSRF protection
- **Mobile App Support**: Comprehensive token management for iOS and Android Chinook applications
- **Security Best Practices**: Advanced security measures, rate limiting, and authentication monitoring
- **Taxonomy Integration**: Secure access control for aliziodev/laravel-taxonomy operations
- **Production Ready**: Enterprise-level security features with comprehensive error handling and debugging tools

The implementation leverages Laravel Sanctum's advanced capabilities while providing Chinook-specific optimizations for music streaming security, customer data protection, and business operations with complete authentication and authorization coverage.
