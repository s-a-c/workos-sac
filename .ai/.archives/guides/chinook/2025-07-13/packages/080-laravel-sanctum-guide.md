# Laravel Sanctum Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. API Installation](#12-api-installation)
  - [1.3. Configuration Setup](#13-configuration-setup)
- [API Token Authentication](#api-token-authentication)
  - [2.1. Token Generation](#21-token-generation)
  - [2.2. Token Management](#22-token-management)
  - [2.3. Token Abilities](#23-token-abilities)
- [SPA Authentication](#spa-authentication)
  - [3.1. CSRF Protection](#31-csrf-protection)
  - [3.2. Session-Based Auth](#32-session-based-auth)
  - [3.3. Frontend Integration](#33-frontend-integration)
- [Mobile App Integration](#mobile-app-integration)
  - [4.1. Mobile Token Flow](#41-mobile-token-flow)
  - [4.2. Device Management](#42-device-management)
  - [4.3. Push Notifications](#43-push-notifications)
- [Security Best Practices](#security-best-practices)
  - [5.1. Token Security](#51-token-security)
  - [5.2. Environment Security](#52-environment-security)
- [Rate Limiting](#rate-limiting)
  - [6.1. Custom Rate Limiters](#61-custom-rate-limiters)
  - [6.2. Middleware Implementation](#62-middleware-implementation)
- [Testing Strategies](#testing-strategies)
  - [7.1. API Authentication Testing](#71-api-authentication-testing)
  - [7.2. Token Abilities Testing](#72-token-abilities-testing)
- [Advanced Features](#advanced-features)
  - [8.1. Custom Token Guards](#81-custom-token-guards)
  - [8.2. Token Analytics](#82-token-analytics)
- [Troubleshooting](#troubleshooting)
  - [9.1. Common Issues](#91-common-issues)
  - [9.2. Debug Commands](#92-debug-commands)
  - [9.3. Performance Optimization](#93-performance-optimization)
- [Navigation](#navigation)

## Overview

Laravel Sanctum provides modern API authentication with token management, SPA integration, and comprehensive security features. This guide covers enterprise-level implementation with mobile app integration, advanced security practices, and production deployment strategies.

**🚀 Key Features:**
- **Multiple Authentication Types**: API tokens and SPA cookie authentication
- **Token Abilities**: Granular permission system for API access control
- **CSRF Protection**: Built-in CSRF protection for SPA applications
- **Rate Limiting**: Advanced rate limiting with custom throttling strategies
- **Security Monitoring**: Authentication attempt tracking and security logging
- **Mobile Integration**: Comprehensive mobile app authentication workflows

## Installation & Setup

### 1.1. Package Installation

Install Laravel Sanctum using Composer:

```bash
# Install Laravel Sanctum
composer require laravel/sanctum

# Publish Sanctum configuration
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run migrations
php artisan migrate

# Verify installation
php artisan sanctum:check
```

**System Requirements:**

- PHP 8.1 or higher
- Laravel 9.0 or higher
- Redis (recommended for session storage)

### 1.2. API Installation

Use the modern API installation command:

```bash
# Install API scaffolding with Sanctum
php artisan install:api

# This command will:
# - Install Laravel Sanctum
# - Publish the configuration
# - Add Sanctum middleware to API routes
# - Create API route files
# - Set up proper CORS configuration
```

**What `install:api` sets up:**

```php
// routes/api.php (created/updated)
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Additional API routes...
```

### 1.3. Configuration Setup

Configure Sanctum for your application:

```php
// config/sanctum.php
return [
    /*
     * Sanctum will only authenticate requests that are made to a first-party
     * domain using Sanctum's cookie authentication middleware.
     */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    /*
     * Sanctum guards which authenticate via HTTP Bearer tokens.
     */
    'guard' => ['web'],

    /*
     * Expiration minutes for issued tokens.
     */
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null),

    /*
     * Token prefix for issued tokens.
     */
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
     * Sanctum middleware for SPA authentication.
     */
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
```

**Environment Configuration:**

```bash
# .env configuration
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,your-frontend-domain.com
SANCTUM_TOKEN_EXPIRATION=525600  # 1 year in minutes
SANCTUM_TOKEN_PREFIX=your-app

# Session configuration for SPA
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# CORS configuration
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://your-frontend-domain.com
CORS_ALLOWED_HEADERS=*
CORS_ALLOWED_METHODS=*
CORS_SUPPORTS_CREDENTIALS=true
```

## API Token Authentication

### 2.1. Token Generation

Implement comprehensive token generation:

```php
// app/Http/Controllers/Auth/TokenController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => 'string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Default abilities based on user role
        $abilities = $request->get('abilities', $this->getDefaultAbilities($user));

        // Create token with abilities
        $token = $user->createToken(
            $request->device_name,
            $abilities,
            now()->addYear() // Token expiration
        );

        // Log token creation
        $this->logTokenCreation($user, $request);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'abilities' => $abilities,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Create new token with same abilities
        $newToken = $user->createToken(
            $currentToken->name,
            $currentToken->abilities,
            now()->addYear()
        );

        // Revoke old token
        $currentToken->delete();

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $newToken->accessToken->expires_at?->toISOString(),
            'abilities' => $newToken->accessToken->abilities,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($request->has('token_id')) {
            // Revoke specific token
            $user->tokens()->where('id', $request->token_id)->delete();
        } else {
            // Revoke current token
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'All tokens revoked successfully',
        ]);
    }

    private function getDefaultAbilities(User $user): array
    {
        return match ($user->role) {
            'admin' => ['*'],
            'manager' => ['read', 'write', 'delete'],
            'editor' => ['read', 'write'],
            'user' => ['read'],
            default => ['read'],
        };
    }

    private function logTokenCreation(User $user, Request $request): void
    {
        logger()->info('API token created', [
            'user_id' => $user->id,
            'device_name' => $request->device_name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
```

### 2.2. Token Management

Implement comprehensive token management:

```php
// app/Http/Controllers/Auth/TokenManagementController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TokenManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $user->tokens()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'tokens' => $tokens->map(function ($token) use ($request) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->toISOString(),
                    'created_at' => $token->created_at->toISOString(),
                    'expires_at' => $token->expires_at?->toISOString(),
                    'is_current' => $token->id === $request->user()->currentAccessToken()->id,
                ];
            }),
        ]);
    }

    public function show(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->findOrFail($tokenId);

        return response()->json([
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'usage_stats' => $this->getTokenUsageStats($token),
            ],
        ]);
    }

    public function update(Request $request, string $tokenId): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string',
            'expires_at' => 'sometimes|date|after:now',
        ]);

        $user = $request->user();
        $token = $user->tokens()->findOrFail($tokenId);

        if ($request->has('name')) {
            $token->name = $request->name;
        }

        if ($request->has('abilities')) {
            $token->abilities = $request->abilities;
        }

        if ($request->has('expires_at')) {
            $token->expires_at = $request->expires_at;
        }

        $token->save();

        return response()->json([
            'message' => 'Token updated successfully',
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'expires_at' => $token->expires_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->findOrFail($tokenId);

        // Prevent deletion of current token
        if ($token->id === $request->user()->currentAccessToken()->id) {
            return response()->json([
                'message' => 'Cannot delete the current token',
            ], 400);
        }

        $token->delete();

        return response()->json([
            'message' => 'Token deleted successfully',
        ]);
    }

    private function getTokenUsageStats($token): array
    {
        // This would require additional logging/tracking
        return [
            'total_requests' => 0, // Implement based on your logging
            'last_ip' => null,     // Implement based on your logging
            'last_user_agent' => null, // Implement based on your logging
        ];
    }
}
```

### 2.3. Token Abilities

Implement granular token abilities:

```php
// app/Http/Middleware/CheckTokenAbilities.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Exceptions\MissingAbilityException;

class CheckTokenAbilities
{
    public function handle(Request $request, Closure $next, ...$abilities)
    {
        if (!$request->user() || !$request->user()->currentAccessToken()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $token = $request->user()->currentAccessToken();

        // Check if token has all required abilities
        foreach ($abilities as $ability) {
            if (!$token->can($ability)) {
                throw new MissingAbilityException($ability);
            }
        }

        return $next($request);
    }
}

// app/Enums/TokenAbility.php
<?php

namespace App\Enums;

enum TokenAbility: string
{
    case READ_USERS = 'users:read';
    case WRITE_USERS = 'users:write';
    case DELETE_USERS = 'users:delete';
    
    case READ_PRODUCTS = 'products:read';
    case WRITE_PRODUCTS = 'products:write';
    case DELETE_PRODUCTS = 'products:delete';
    
    case READ_ORDERS = 'orders:read';
    case WRITE_ORDERS = 'orders:write';
    case DELETE_ORDERS = 'orders:delete';
    
    case ADMIN_ACCESS = 'admin:*';
    case FULL_ACCESS = '*';

    public static function getAbilitiesForRole(string $role): array
    {
        return match ($role) {
            'admin' => [self::FULL_ACCESS->value],
            'manager' => [
                self::READ_USERS->value,
                self::WRITE_USERS->value,
                self::READ_PRODUCTS->value,
                self::WRITE_PRODUCTS->value,
                self::READ_ORDERS->value,
                self::WRITE_ORDERS->value,
            ],
            'editor' => [
                self::READ_USERS->value,
                self::READ_PRODUCTS->value,
                self::WRITE_PRODUCTS->value,
                self::READ_ORDERS->value,
            ],
            'user' => [
                self::READ_PRODUCTS->value,
                self::READ_ORDERS->value,
            ],
            default => [self::READ_PRODUCTS->value],
        };
    }
}

// Usage in routes
Route::middleware(['auth:sanctum', 'abilities:users:read,users:write'])
    ->get('/api/users', [UserController::class, 'index']);

Route::middleware(['auth:sanctum', 'abilities:products:write'])
    ->post('/api/products', [ProductController::class, 'store']);
```

## SPA Authentication

### 3.1. CSRF Protection

Configure CSRF protection for SPA applications:

```php
// app/Http/Middleware/VerifyCsrfToken.php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        'api/*', // API routes are protected by Sanctum tokens
    ];

    /**
     * Determine if the session and input CSRF tokens match.
     */
    protected function tokensMatch($request)
    {
        // For SPA applications, check both session and header tokens
        $token = $this->getTokenFromRequest($request);

        return is_string($request->session()->token()) &&
               is_string($token) &&
               hash_equals($request->session()->token(), $token);
    }

    /**
     * Get the CSRF token from the request.
     */
    protected function getTokenFromRequest($request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (!$token && $header = $request->header('X-XSRF-TOKEN')) {
            $token = $this->encrypter->decrypt($header, static::serialized());
        }

        return $token;
    }
}

// routes/web.php - CSRF cookie route
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['message' => 'CSRF cookie set']);
})->middleware('web');
```

### 3.2. Session-Based Auth

Implement session-based authentication for SPAs:

```php
// app/Http/Controllers/Auth/SpaAuthController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SpaAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
                'permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ] : null,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'Registration successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }
}
```

### 3.3. Frontend Integration

Frontend integration examples for different frameworks:

```javascript
// Vue.js/Nuxt.js Integration
// plugins/auth.js
export default class AuthService {
  constructor(axios) {
    this.axios = axios;
  }

  async getCsrfCookie() {
    await this.axios.get('/sanctum/csrf-cookie');
  }

  async login(credentials) {
    await this.getCsrfCookie();

    const response = await this.axios.post('/api/auth/login', credentials);
    return response.data;
  }

  async logout() {
    await this.axios.post('/api/auth/logout');
  }

  async getUser() {
    const response = await this.axios.get('/api/auth/user');
    return response.data.user;
  }

  async register(userData) {
    await this.getCsrfCookie();

    const response = await this.axios.post('/api/auth/register', userData);
    return response.data;
  }
}

// React/Next.js Integration
// hooks/useAuth.js
import { useState, useEffect, createContext, useContext } from 'react';
import axios from 'axios';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Configure axios defaults
  axios.defaults.baseURL = process.env.NEXT_PUBLIC_API_URL;
  axios.defaults.withCredentials = true;

  const getCsrfCookie = async () => {
    await axios.get('/sanctum/csrf-cookie');
  };

  const login = async (credentials) => {
    await getCsrfCookie();

    const response = await axios.post('/api/auth/login', credentials);
    setUser(response.data.user);
    return response.data;
  };

  const logout = async () => {
    await axios.post('/api/auth/logout');
    setUser(null);
  };

  const register = async (userData) => {
    await getCsrfCookie();

    const response = await axios.post('/api/auth/register', userData);
    setUser(response.data.user);
    return response.data;
  };

  const getUser = async () => {
    try {
      const response = await axios.get('/api/auth/user');
      setUser(response.data.user);
    } catch (error) {
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getUser();
  }, []);

  return (
    <AuthContext.Provider value={{
      user,
      login,
      logout,
      register,
      loading,
    }}>
      {children}
    </AuthContext.Provider>
  );
};
```

## Mobile App Integration

### 4.1. Mobile Token Flow

Implement mobile-specific authentication flow:

```php
// app/Http/Controllers/Auth/MobileAuthController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
            'device_id' => 'required|string|max:255',
            'device_type' => 'required|in:ios,android',
            'app_version' => 'required|string',
            'os_version' => 'required|string',
            'push_token' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Register or update device
        $device = $this->registerDevice($user, $request);

        // Create token with mobile-specific abilities
        $abilities = $this->getMobileAbilities($user);
        $token = $user->createToken(
            $request->device_name,
            $abilities,
            now()->addMonths(6) // 6-month expiration for mobile
        );

        // Store device association with token
        $token->accessToken->update([
            'device_id' => $device->id,
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'role' => $user->role,
            ],
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
            ],
            'app_config' => $this->getAppConfig($user),
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $device = Device::find($currentToken->device_id);

        // Create new token
        $newToken = $user->createToken(
            $currentToken->name,
            $currentToken->abilities,
            now()->addMonths(6)
        );

        // Associate with same device
        $newToken->accessToken->update([
            'device_id' => $device?->id,
        ]);

        // Revoke old token
        $currentToken->delete();

        return response()->json([
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $newToken->accessToken->expires_at?->toISOString(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        // Update device status
        if ($token->device_id) {
            Device::where('id', $token->device_id)->update([
                'last_logout_at' => now(),
                'push_token' => null, // Clear push token on logout
            ]);
        }

        // Revoke token
        $token->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    private function registerDevice(User $user, Request $request): Device
    {
        return Device::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $request->device_id,
            ],
            [
                'name' => $request->device_name,
                'type' => $request->device_type,
                'app_version' => $request->app_version,
                'os_version' => $request->os_version,
                'push_token' => $request->push_token,
                'last_login_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );
    }

    private function getMobileAbilities(User $user): array
    {
        $baseAbilities = [
            'mobile:read',
            'mobile:write',
            'profile:read',
            'profile:write',
            'orders:read',
            'notifications:read',
        ];

        if ($user->hasRole('premium')) {
            $baseAbilities[] = 'premium:access';
        }

        return $baseAbilities;
    }

    private function getAppConfig(User $user): array
    {
        return [
            'features' => [
                'push_notifications' => true,
                'biometric_auth' => true,
                'offline_mode' => $user->hasRole('premium'),
                'dark_mode' => true,
            ],
            'limits' => [
                'max_offline_days' => $user->hasRole('premium') ? 30 : 7,
                'max_downloads' => $user->hasRole('premium') ? 1000 : 100,
            ],
            'endpoints' => [
                'api_base' => config('app.api_url'),
                'websocket' => config('app.websocket_url'),
                'cdn' => config('app.cdn_url'),
            ],
        ];
    }
}
```

### 4.2. Device Management

Implement comprehensive device management:

```php
// app/Models/Device.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'name',
        'type',
        'app_version',
        'os_version',
        'push_token',
        'last_login_at',
        'last_logout_at',
        'ip_address',
        'user_agent',
        'is_trusted',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'last_logout_at' => 'datetime',
            'is_trusted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class, 'device_id');
    }

    public function isActive(): bool
    {
        return $this->tokens()->where('expires_at', '>', now())->exists();
    }

    public function getLastActivityAttribute(): ?string
    {
        $lastToken = $this->tokens()
            ->orderBy('last_used_at', 'desc')
            ->first();

        return $lastToken?->last_used_at?->diffForHumans();
    }

    public function scopeActive($query)
    {
        return $query->whereHas('tokens', function ($q) {
            $q->where('expires_at', '>', now());
        });
    }

    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }
}

// app/Http/Controllers/DeviceController.php
<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $devices = $user->devices()
            ->with('tokens')
            ->orderBy('last_login_at', 'desc')
            ->get();

        return response()->json([
            'devices' => $devices->map(function ($device) {
                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'type' => $device->type,
                    'app_version' => $device->app_version,
                    'os_version' => $device->os_version,
                    'last_login_at' => $device->last_login_at?->toISOString(),
                    'last_activity' => $device->last_activity,
                    'is_active' => $device->isActive(),
                    'is_trusted' => $device->is_trusted,
                    'is_current' => $device->device_id === $this->getCurrentDeviceId($request),
                ];
            }),
        ]);
    }

    public function trust(Request $request, Device $device): JsonResponse
    {
        $this->authorize('update', $device);

        $device->update(['is_trusted' => true]);

        return response()->json([
            'message' => 'Device marked as trusted',
        ]);
    }

    public function revoke(Request $request, Device $device): JsonResponse
    {
        $this->authorize('delete', $device);

        // Don't allow revoking current device
        if ($device->device_id === $this->getCurrentDeviceId($request)) {
            return response()->json([
                'message' => 'Cannot revoke current device',
            ], 400);
        }

        // Revoke all tokens for this device
        $device->tokens()->delete();

        // Update device status
        $device->update([
            'last_logout_at' => now(),
            'push_token' => null,
        ]);

        return response()->json([
            'message' => 'Device access revoked',
        ]);
    }

    private function getCurrentDeviceId(Request $request): ?string
    {
        $token = $request->user()->currentAccessToken();
        $device = Device::find($token->device_id);

        return $device?->device_id;
    }
}
```

### 4.3. Push Notifications

Integrate push notifications with device management:

```php
// app/Services/PushNotificationService.php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Device;
use Illuminate\Support\Facades\Http;

class PushNotificationService
{
    public function sendToUser(User $user, array $notification): array
    {
        $devices = $user->devices()
            ->whereNotNull('push_token')
            ->where('is_trusted', true)
            ->get();

        $results = [];

        foreach ($devices as $device) {
            $result = $this->sendToDevice($device, $notification);
            $results[] = [
                'device_id' => $device->id,
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        return $results;
    }

    public function sendToDevice(Device $device, array $notification): array
    {
        try {
            $payload = $this->buildPayload($device, $notification);

            $response = match ($device->type) {
                'ios' => $this->sendApnNotification($device->push_token, $payload),
                'android' => $this->sendFcmNotification($device->push_token, $payload),
                default => ['success' => false, 'message' => 'Unsupported device type'],
            };

            // Log notification attempt
            logger()->info('Push notification sent', [
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'success' => $response['success'],
            ]);

            return $response;
        } catch (\Exception $e) {
            logger()->error('Push notification failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function sendApnNotification(string $token, array $payload): array
    {
        // Apple Push Notification service implementation
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getApnJwt(),
            'Content-Type' => 'application/json',
        ])->post("https://api.push.apple.com/3/device/{$token}", $payload);

        return [
            'success' => $response->successful(),
            'message' => $response->successful() ? 'Sent' : $response->body(),
        ];
    }

    private function sendFcmNotification(string $token, array $payload): array
    {
        // Firebase Cloud Messaging implementation
        $response = Http::withHeaders([
            'Authorization' => 'key=' . config('services.fcm.server_key'),
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'to' => $token,
            'notification' => $payload['notification'],
            'data' => $payload['data'] ?? [],
        ]);

        return [
            'success' => $response->successful(),
            'message' => $response->successful() ? 'Sent' : $response->body(),
        ];
    }

    private function buildPayload(Device $device, array $notification): array
    {
        return match ($device->type) {
            'ios' => [
                'aps' => [
                    'alert' => [
                        'title' => $notification['title'],
                        'body' => $notification['body'],
                    ],
                    'badge' => $notification['badge'] ?? 1,
                    'sound' => $notification['sound'] ?? 'default',
                ],
                'data' => $notification['data'] ?? [],
            ],
            'android' => [
                'notification' => [
                    'title' => $notification['title'],
                    'body' => $notification['body'],
                    'icon' => $notification['icon'] ?? 'default',
                    'sound' => $notification['sound'] ?? 'default',
                ],
                'data' => $notification['data'] ?? [],
            ],
            default => [],
        };
    }

    private function getApnJwt(): string
    {
        // Generate JWT for Apple Push Notifications
        // Implementation depends on your JWT library
        return 'your-apn-jwt-token';
    }
}
```

## Security Best Practices

Implement comprehensive security measures for production Sanctum deployments.

### 5.1. Token Security

Secure token generation and storage:

```php
// app/Http/Controllers/Api/AuthController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke existing tokens for security
        $user->tokens()->where('name', $request->device_name)->delete();

        $token = $user->createToken($request->device_name, [
            'read',
            'write',
        ], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'abilities' => $token->accessToken->abilities,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
```

### 5.2. Environment Security

Secure environment configuration:

```env
# .env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,::1,your-domain.com
SESSION_DOMAIN=.your-domain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
```

## Rate Limiting

Implement advanced rate limiting for API protection.

### 6.1. Custom Rate Limiters

Configure custom rate limiting:

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('login', function (Request $request) {
        return [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perMinute(3)->by($request->input('email')),
        ];
    });

    RateLimiter::for('sensitive', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()->id);
    });
}
```

### 6.2. Middleware Implementation

Apply rate limiting middleware:

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('artists', ArtistController::class);
    Route::apiResource('albums', AlbumController::class);
});

Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
```

## Testing Strategies

Comprehensive testing approaches for Sanctum authentication.

### 7.1. API Authentication Testing

Test API token authentication:

```php
// tests/Feature/Api/AuthenticationTest.php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
                'abilities',
            ]);
    }

    public function test_authenticated_user_can_access_protected_routes()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['read']);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }
}
```

### 7.2. Token Abilities Testing

Test token abilities and permissions:

```php
// tests/Feature/Api/TokenAbilitiesTest.php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Artist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_read_ability_can_view_resources()
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        Sanctum::actingAs($user, ['read']);

        $response = $this->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200);
    }

    public function test_user_without_write_ability_cannot_create_resources()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['read']);

        $response = $this->postJson('/api/artists', [
            'name' => 'Test Artist',
            'country' => 'USA',
        ]);

        $response->assertStatus(403);
    }
}
```

## Advanced Features

Advanced Sanctum features for enterprise applications.

### 8.1. Custom Token Guards

Implement custom token guards:

```php
// app/Guards/CustomSanctumGuard.php
<?php

namespace App\Guards;

use Laravel\Sanctum\Guard;
use Illuminate\Http\Request;

class CustomSanctumGuard extends Guard
{
    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (!$token) {
            return null;
        }

        // Custom token validation logic
        $model = $this->provider->getModel();
        $accessToken = $model::findToken($token);

        if (!$accessToken) {
            return null;
        }

        // Additional security checks
        if ($this->isTokenExpired($accessToken)) {
            return null;
        }

        if ($this->isTokenSuspicious($accessToken)) {
            $this->logSuspiciousActivity($accessToken);
            return null;
        }

        return $this->user = $accessToken->tokenable;
    }

    protected function isTokenExpired($accessToken): bool
    {
        return $accessToken->expires_at &&
               $accessToken->expires_at->isPast();
    }

    protected function isTokenSuspicious($accessToken): bool
    {
        // Implement suspicious activity detection
        return false;
    }
}
```

### 8.2. Token Analytics

Implement token usage analytics:

```php
// app/Models/PersonalAccessToken.php
<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
        'usage_count',
        'ip_address',
        'user_agent',
    ];

    /**
     * Laravel 12 modern cast() method
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_count' => 'integer',
        ];
    }

    public function incrementUsage(string $ipAddress, string $userAgent): void
    {
        $this->update([
            'last_used_at' => now(),
            'usage_count' => $this->usage_count + 1,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
```

## Troubleshooting

Common issues and solutions for Sanctum implementation.

### 9.1. Common Issues

**CORS Configuration Issues:**

```php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:3000', 'https://your-domain.com'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

**Session Configuration:**

```php
// config/session.php
'same_site' => env('SESSION_SAME_SITE', 'lax'),
'secure' => env('SESSION_SECURE_COOKIE', false),
'domain' => env('SESSION_DOMAIN', null),
```

### 9.2. Debug Commands

Useful debugging commands:

```bash
# Clear all tokens
php artisan sanctum:prune-expired

# Check Sanctum configuration
php artisan config:show sanctum

# Debug CORS issues
php artisan route:list --name=sanctum
```

### 9.3. Performance Optimization

Optimize Sanctum performance:

```php
// config/sanctum.php
return [
    'expiration' => 525600, // 1 year in minutes
    'token_retrieval_method' => 'header', // or 'query'
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

## Navigation

**← Previous:** [Laravel Fractal Guide](070-laravel-fractal-guide.md)

**Next →** [Package Implementation Index](000-packages-index.md)
