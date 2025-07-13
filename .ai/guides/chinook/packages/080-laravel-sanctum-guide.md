# 1. Laravel Sanctum Implementation Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Installation & Setup](#2-installation--setup)
  - [2.1. Package Installation](#21-package-installation)
  - [2.2. API Installation](#22-api-installation)
  - [2.3. Configuration Setup](#23-configuration-setup)
- [3. API Token Authentication](#3-api-token-authentication)
  - [3.1. Token Generation](#31-token-generation)
  - [3.2. Token Management](#32-token-management)
  - [3.3. Token Abilities](#33-token-abilities)
- [5. Taxonomy-Aware Authentication](#5-taxonomy-aware-authentication)
  - [5.1. Genre-Based Permissions](#51-genre-based-permissions)
- [9. Testing Strategies](#9-testing-strategies)
  - [9.1. API Authentication Testing](#91-api-authentication-testing)
  - [9.2. Token Abilities Testing](#92-token-abilities-testing)
- [Navigation](#navigation)

## 1. Overview

Laravel Sanctum provides modern API authentication with token management, SPA integration, and comprehensive security features. This guide covers enterprise-level implementation with **exclusive aliziodev/laravel-taxonomy integration**, mobile app authentication, advanced security practices, and production deployment strategies for the Chinook music database system.

**🚀 Key Features:**
- **Multiple Authentication Types**: API tokens and SPA cookie authentication
- **Token Abilities**: Granular permission system for API access control
- **CSRF Protection**: Built-in CSRF protection for SPA applications
- **Rate Limiting**: Advanced rate limiting with custom throttling strategies
- **Security Monitoring**: Authentication attempt tracking and security logging
- **Mobile Integration**: Comprehensive mobile app authentication workflows
- **Taxonomy Integration**: Genre-based permissions and hierarchical access control

**🎵 Chinook Integration Benefits:**
- **Genre-Based Access Control**: Restrict API access based on music genre taxonomies
- **Hierarchical Permissions**: Parent-child genre relationships for permission inheritance
- **Content Filtering**: Automatic content filtering based on user's genre preferences
- **Artist/Album Scoping**: Token abilities scoped to specific artists or album categories

## 2. Installation & Setup

### 2.1. Package Installation

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

### 2.2. API Installation

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

// Chinook API routes with taxonomy integration
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('artists', \App\Http\Controllers\Api\ChinookArtistController::class);
    Route::apiResource('albums', \App\Http\Controllers\Api\ChinookAlbumController::class);
    Route::apiResource('tracks', \App\Http\Controllers\Api\ChinookTrackController::class);
    Route::apiResource('genres', \App\Http\Controllers\Api\TaxonomyController::class);
    
    // Genre-specific routes
    Route::get('genres/{genre}/tracks', [\App\Http\Controllers\Api\TaxonomyController::class, 'tracks']);
    Route::get('genres/{genre}/albums', [\App\Http\Controllers\Api\TaxonomyController::class, 'albums']);
    Route::get('genres/{genre}/artists', [\App\Http\Controllers\Api\TaxonomyController::class, 'artists']);
});
```

### 2.3. Configuration Setup

Configure Sanctum for Chinook with taxonomy support:

```php
// config/sanctum.php
<?php

return [
    /*
     * Sanctum will delete tokens that have been expired for this many minutes.
     * This keeps the database clean by removing old tokens.
     */
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', 525600), // 1 year

    /*
     * The stateful domains that should receive CSRF protection.
     */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : '',
        env('FRONTEND_URL') ? ','.parse_url(env('FRONTEND_URL'), PHP_URL_HOST) : ''
    ))),

    /*
     * Sanctum guards that should be used for authentication.
     */
    'guard' => ['web'],

    /*
     * The middleware that should be used for authentication.
     */
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],

    /*
     * Custom token abilities for Chinook taxonomy integration
     */
    'abilities' => [
        // Basic CRUD operations
        'tracks:read' => 'Read track information',
        'tracks:create' => 'Create new tracks',
        'tracks:update' => 'Update track information',
        'tracks:delete' => 'Delete tracks',
        
        'albums:read' => 'Read album information',
        'albums:create' => 'Create new albums',
        'albums:update' => 'Update album information',
        'albums:delete' => 'Delete albums',
        
        'artists:read' => 'Read artist information',
        'artists:create' => 'Create new artists',
        'artists:update' => 'Update artist information',
        'artists:delete' => 'Delete artists',
        
        // Taxonomy-specific abilities
        'genres:read' => 'Read genre taxonomies',
        'genres:manage' => 'Manage genre taxonomies',
        'genres:assign' => 'Assign genres to content',
        
        // Genre-scoped abilities
        'rock:access' => 'Access rock music content',
        'jazz:access' => 'Access jazz music content',
        'classical:access' => 'Access classical music content',
        'electronic:access' => 'Access electronic music content',
        
        // Administrative abilities
        'admin:full' => 'Full administrative access',
        'analytics:read' => 'Read analytics data',
        'reports:generate' => 'Generate reports',
    ],
];
```

## 3. API Token Authentication

### 3.1. Token Generation

Create tokens with taxonomy-aware abilities:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
            'preferred_genres' => 'sometimes|array',
            'preferred_genres.*' => 'integer|exists:taxonomies,id',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Generate token abilities based on user preferences and genre access
        $abilities = $this->generateTokenAbilities($user, $request->preferred_genres ?? []);

        $token = $user->createToken(
            $request->device_name,
            $abilities,
            now()->addYear()
        );

        // Store user's preferred genres for content filtering
        if ($request->has('preferred_genres')) {
            $user->preferredGenres()->sync($request->preferred_genres);
        }

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'preferred_genres' => $user->preferredGenres()->pluck('name')->toArray(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ];
        });

        return response()->json([
            'tokens' => $tokens,
        ]);
    }

    private function generateTokenAbilities(User $user, array $preferredGenres): array
    {
        $abilities = [
            'tracks:read',
            'albums:read',
            'artists:read',
            'genres:read',
        ];

        // Add role-based abilities
        if ($user->hasRole('admin')) {
            $abilities = array_merge($abilities, [
                'tracks:create', 'tracks:update', 'tracks:delete',
                'albums:create', 'albums:update', 'albums:delete',
                'artists:create', 'artists:update', 'artists:delete',
                'genres:manage',
                'admin:full',
                'analytics:read',
                'reports:generate',
            ]);
        } elseif ($user->hasRole('editor')) {
            $abilities = array_merge($abilities, [
                'tracks:create', 'tracks:update',
                'albums:create', 'albums:update',
                'artists:create', 'artists:update',
                'genres:assign',
            ]);
        }

        // Add genre-specific abilities based on preferences
        if (!empty($preferredGenres)) {
            $genres = Taxonomy::whereIn('id', $preferredGenres)
                ->where('type', 'genre')
                ->get();

            foreach ($genres as $genre) {
                $genreSlug = \Str::slug($genre->name);
                $abilities[] = "{$genreSlug}:access";
                
                // Add parent genre access if applicable
                if ($genre->parent) {
                    $parentSlug = \Str::slug($genre->parent->name);
                    $abilities[] = "{$parentSlug}:access";
                }
            }
        }

        return array_unique($abilities);
    }
}
```

### 3.2. Token Management

Advanced token management with taxonomy-aware scoping:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|array',
            'abilities.*' => 'string',
            'genre_restrictions' => 'sometimes|array',
            'genre_restrictions.*' => 'integer|exists:taxonomies,id',
            'expires_at' => 'sometimes|date|after:now',
        ]);

        // Validate abilities against allowed list
        $allowedAbilities = array_keys(config('sanctum.abilities', []));
        $requestedAbilities = $request->abilities;

        $invalidAbilities = array_diff($requestedAbilities, $allowedAbilities);
        if (!empty($invalidAbilities)) {
            return response()->json([
                'error' => 'Invalid abilities: ' . implode(', ', $invalidAbilities),
            ], 422);
        }

        // Create token with genre restrictions
        $token = $request->user()->createToken(
            $request->name,
            $requestedAbilities,
            $request->expires_at ? now()->parse($request->expires_at) : null
        );

        // Store genre restrictions in token metadata
        if ($request->has('genre_restrictions')) {
            $token->accessToken->update([
                'meta' => [
                    'genre_restrictions' => $request->genre_restrictions,
                    'created_by_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
        }

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $requestedAbilities,
            'genre_restrictions' => $request->genre_restrictions ?? [],
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ]);
    }

    public function revoke(Request $request, $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($tokenId);
        $token->delete();

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => "Successfully revoked {$count} tokens",
        ]);
    }

    public function updateAbilities(Request $request, $tokenId): JsonResponse
    {
        $request->validate([
            'abilities' => 'required|array',
            'abilities.*' => 'string',
        ]);

        $token = $request->user()->tokens()->findOrFail($tokenId);

        // Validate new abilities
        $allowedAbilities = array_keys(config('sanctum.abilities', []));
        $newAbilities = $request->abilities;

        $invalidAbilities = array_diff($newAbilities, $allowedAbilities);
        if (!empty($invalidAbilities)) {
            return response()->json([
                'error' => 'Invalid abilities: ' . implode(', ', $invalidAbilities),
            ], 422);
        }

        $token->update(['abilities' => $newAbilities]);

        return response()->json([
            'message' => 'Token abilities updated successfully',
            'abilities' => $newAbilities,
        ]);
    }
}
```

### 3.3. Token Abilities

Implement granular permissions with taxonomy integration:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class CheckTokenAbilities
{
    public function handle(Request $request, Closure $next, ...$abilities): Response
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Check basic abilities
        foreach ($abilities as $ability) {
            if (!$token->can($ability)) {
                return response()->json([
                    'error' => "Insufficient permissions. Required: {$ability}",
                ], 403);
            }
        }

        // Check genre-specific restrictions
        if ($this->hasGenreRestrictions($request, $token)) {
            if (!$this->checkGenreAccess($request, $token)) {
                return response()->json([
                    'error' => 'Access denied for this genre',
                ], 403);
            }
        }

        return $next($request);
    }

    private function hasGenreRestrictions(Request $request, $token): bool
    {
        // Check if the request involves genre-specific content
        $genreRoutes = ['tracks', 'albums', 'artists'];
        $currentRoute = $request->route()->getName();

        foreach ($genreRoutes as $route) {
            if (str_contains($currentRoute, $route)) {
                return true;
            }
        }

        return false;
    }

    private function checkGenreAccess(Request $request, $token): bool
    {
        $genreRestrictions = $token->meta['genre_restrictions'] ?? [];

        if (empty($genreRestrictions)) {
            return true; // No restrictions
        }

        // Get the content being accessed
        $content = $this->getContentFromRequest($request);

        if (!$content) {
            return true; // No specific content, allow access
        }

        // Check if content's genres are within allowed restrictions
        $contentGenres = $content->taxonomies()
            ->where('type', 'genre')
            ->pluck('id')
            ->toArray();

        // Allow access if any of the content's genres are in the allowed list
        return !empty(array_intersect($contentGenres, $genreRestrictions));
    }

    private function getContentFromRequest(Request $request)
    {
        $routeName = $request->route()->getName();
        $routeParameters = $request->route()->parameters();

        if (str_contains($routeName, 'tracks') && isset($routeParameters['track'])) {
            return \App\Models\ChinookTrack::find($routeParameters['track']);
        }

        if (str_contains($routeName, 'albums') && isset($routeParameters['album'])) {
            return \App\Models\ChinookAlbum::find($routeParameters['album']);
        }

        if (str_contains($routeName, 'artists') && isset($routeParameters['artist'])) {
            return \App\Models\ChinookArtist::find($routeParameters['artist']);
        }

        return null;
    }
}

// Usage in routes
Route::middleware(['auth:sanctum', 'abilities:tracks:read,rock:access'])
    ->get('/tracks/{track}', [ChinookTrackController::class, 'show']);

Route::middleware(['auth:sanctum', 'abilities:tracks:create,genres:assign'])
    ->post('/tracks', [ChinookTrackController::class, 'store']);
```

## 5. Taxonomy-Aware Authentication

### 5.1. Genre-Based Permissions

Implement fine-grained genre-based access control:

```php
<?php

namespace App\Services;

use App\Models\User;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Support\Collection;

class GenrePermissionService
{
    public function getUserAccessibleGenres(User $user): Collection
    {
        $token = $user->currentAccessToken();

        if (!$token) {
            return collect();
        }

        // Get genre restrictions from token metadata
        $genreRestrictions = $token->meta['genre_restrictions'] ?? [];

        if (empty($genreRestrictions)) {
            // No restrictions, return all genres
            return Taxonomy::where('type', 'genre')->get();
        }

        // Return only allowed genres and their children
        $allowedGenres = Taxonomy::whereIn('id', $genreRestrictions)
            ->where('type', 'genre')
            ->get();

        $expandedGenres = collect();

        foreach ($allowedGenres as $genre) {
            $expandedGenres->push($genre);
            $expandedGenres = $expandedGenres->merge($genre->descendants);
        }

        return $expandedGenres->unique('id');
    }

    public function canAccessGenre(User $user, Taxonomy $genre): bool
    {
        $accessibleGenres = $this->getUserAccessibleGenres($user);

        return $accessibleGenres->contains('id', $genre->id);
    }

    public function filterContentByGenreAccess($query, User $user)
    {
        $accessibleGenres = $this->getUserAccessibleGenres($user);

        if ($accessibleGenres->isEmpty()) {
            return $query->whereRaw('1 = 0'); // No access
        }

        $genreIds = $accessibleGenres->pluck('id')->toArray();

        return $query->whereHas('taxonomies', function ($q) use ($genreIds) {
            $q->whereIn('taxonomy_id', $genreIds)
              ->where('type', 'genre');
        });
    }

    public function getGenreBasedRecommendations(User $user, int $limit = 10): Collection
    {
        $accessibleGenres = $this->getUserAccessibleGenres($user);

        if ($accessibleGenres->isEmpty()) {
            return collect();
        }

        // Get user's preferred genres
        $preferredGenres = $user->preferredGenres()->pluck('id')->toArray();

        // Find similar genres within accessible scope
        $recommendations = collect();

        foreach ($preferredGenres as $preferredGenreId) {
            $preferredGenre = $accessibleGenres->firstWhere('id', $preferredGenreId);

            if ($preferredGenre) {
                // Add siblings and children
                if ($preferredGenre->parent) {
                    $siblings = $preferredGenre->parent->children()
                        ->whereIn('id', $accessibleGenres->pluck('id'))
                        ->where('id', '!=', $preferredGenreId)
                        ->get();

                    $recommendations = $recommendations->merge($siblings);
                }

                $children = $preferredGenre->children()
                    ->whereIn('id', $accessibleGenres->pluck('id'))
                    ->get();

                $recommendations = $recommendations->merge($children);
            }
        }

        return $recommendations->unique('id')->take($limit);
    }
}
```

## 9. Testing Strategies

### 9.1. API Authentication Testing

Comprehensive testing for taxonomy-aware authentication:

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChinookTrack;
use App\Models\ChinookAlbum;
use App\Models\ChinookArtist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_genre_preferences(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $rockGenre = Taxonomy::create([
            'name' => 'Rock',
            'type' => 'genre',
        ]);

        $jazzGenre = Taxonomy::create([
            'name' => 'Jazz',
            'type' => 'genre',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'Test Device',
            'preferred_genres' => [$rockGenre->id, $jazzGenre->id],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'abilities',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'preferred_genres',
                ],
            ]);

        $this->assertContains('rock:access', $response->json('abilities'));
        $this->assertContains('jazz:access', $response->json('abilities'));
        $this->assertEquals(['Rock', 'Jazz'], $response->json('user.preferred_genres'));
    }

    public function test_token_restricts_access_to_unauthorized_genres(): void
    {
        $user = User::factory()->create();

        $rockGenre = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
        $jazzGenre = Taxonomy::create(['name' => 'Jazz', 'type' => 'genre']);

        // Create token with only rock access
        $token = $user->createToken('test', ['tracks:read', 'rock:access']);
        $token->accessToken->update([
            'meta' => ['genre_restrictions' => [$rockGenre->id]],
        ]);

        // Create tracks with different genres
        $artist = ChinookArtist::factory()->create();
        $album = ChinookAlbum::factory()->create(['artist_id' => $artist->id]);

        $rockTrack = ChinookTrack::factory()->create(['album_id' => $album->id]);
        $rockTrack->taxonomies()->attach($rockGenre);

        $jazzTrack = ChinookTrack::factory()->create(['album_id' => $album->id]);
        $jazzTrack->taxonomies()->attach($jazzGenre);

        Sanctum::actingAs($user, ['tracks:read', 'rock:access'], 'sanctum');

        // Should be able to access rock track
        $response = $this->getJson("/api/tracks/{$rockTrack->id}");
        $response->assertOk();

        // Should be denied access to jazz track
        $response = $this->getJson("/api/tracks/{$jazzTrack->id}");
        $response->assertForbidden()
            ->assertJson(['error' => 'Access denied for this genre']);
    }

    public function test_hierarchical_genre_access(): void
    {
        $user = User::factory()->create();

        $rockGenre = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
        $hardRockGenre = Taxonomy::create([
            'name' => 'Hard Rock',
            'type' => 'genre',
            'parent_id' => $rockGenre->id,
        ]);

        // Create token with parent genre access
        $token = $user->createToken('test', ['tracks:read', 'rock:access']);
        $token->accessToken->update([
            'meta' => ['genre_restrictions' => [$rockGenre->id]],
        ]);

        $artist = ChinookArtist::factory()->create();
        $album = ChinookAlbum::factory()->create(['artist_id' => $artist->id]);

        $hardRockTrack = ChinookTrack::factory()->create(['album_id' => $album->id]);
        $hardRockTrack->taxonomies()->attach($hardRockGenre);

        Sanctum::actingAs($user, ['tracks:read', 'rock:access'], 'sanctum');

        // Should be able to access child genre content
        $response = $this->getJson("/api/tracks/{$hardRockTrack->id}");
        $response->assertOk();
    }

    public function test_token_abilities_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['tracks:read'], 'sanctum');

        $artist = ChinookArtist::factory()->create();

        // Should be denied access without proper abilities
        $response = $this->postJson('/api/artists', [
            'name' => 'New Artist',
            'bio' => 'Test bio',
        ]);

        $response->assertForbidden()
            ->assertJson(['error' => 'Insufficient permissions. Required: artists:create']);
    }
}
```

### 9.2. Token Abilities Testing

Test complex token ability scenarios:

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TokenAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_token_has_full_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Admin Device',
        ]);

        $abilities = $response->json('abilities');

        $this->assertContains('admin:full', $abilities);
        $this->assertContains('tracks:create', $abilities);
        $this->assertContains('tracks:update', $abilities);
        $this->assertContains('tracks:delete', $abilities);
        $this->assertContains('genres:manage', $abilities);
    }

    public function test_editor_token_has_limited_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Editor Device',
        ]);

        $abilities = $response->json('abilities');

        $this->assertContains('tracks:create', $abilities);
        $this->assertContains('tracks:update', $abilities);
        $this->assertNotContains('tracks:delete', $abilities);
        $this->assertNotContains('admin:full', $abilities);
        $this->assertContains('genres:assign', $abilities);
        $this->assertNotContains('genres:manage', $abilities);
    }

    public function test_token_abilities_can_be_updated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['tracks:read']);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->putJson("/api/tokens/{$token->accessToken->id}/abilities", [
            'abilities' => ['tracks:read', 'tracks:create', 'albums:read'],
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Token abilities updated successfully',
                'abilities' => ['tracks:read', 'tracks:create', 'albums:read'],
            ]);

        $this->assertEquals(
            ['tracks:read', 'tracks:create', 'albums:read'],
            $token->accessToken->fresh()->abilities
        );
    }

    public function test_invalid_abilities_are_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->postJson('/api/tokens', [
            'name' => 'Test Token',
            'abilities' => ['tracks:read', 'invalid:ability', 'another:invalid'],
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'error' => 'Invalid abilities: invalid:ability, another:invalid',
            ]);
    }
}
```

---

**Refactored from:** `.ai/guides/chinook/packages/080-laravel-sanctum-guide.md` on 2025-07-11

## Navigation

**← Previous:** [Laravel Fractal Guide](070-laravel-fractal-guide.md)

**Next →** [Laravel WorkOS Guide](090-laravel-workos-guide.md)

[⬆️ Back to Top](#1-laravel-sanctum-implementation-guide)
