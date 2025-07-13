# Secondary Keys Guide

## Table of Contents

- [Overview](#overview)
- [HasSecondaryUniqueKey Trait](#hassecondaryuniquekey-trait)
- [Public ID Implementation](#public-id-implementation)
- [UUID Integration](#uuid-integration)
- [Slug Generation](#slug-generation)
- [Database Schema](#database-schema)
- [Query Optimization](#query-optimization)
- [Security Considerations](#security-considerations)
- [Testing Secondary Keys](#testing-secondary-keys)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of secondary unique keys for Laravel 12 models in the Chinook application. The HasSecondaryUniqueKey trait provides public-facing identifiers that are separate from primary keys, enhancing security and user experience.

**🚀 Key Features:**
- **Public ID System**: User-friendly identifiers separate from database IDs
- **UUID Support**: Universally unique identifiers for distributed systems
- **Slug Integration**: SEO-friendly URL identifiers
- **Security Enhancement**: Hide internal database structure
- **WCAG 2.1 AA Compliance**: Accessible identifier presentation

## HasSecondaryUniqueKey Trait

### Basic Trait Implementation

```php
<?php
// app/Traits/HasSecondaryUniqueKey.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasSecondaryUniqueKey
{
    /**
     * Boot the trait
     */
    protected static function bootHasSecondaryUniqueKey(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = $model->generatePublicId();
            }
        });
    }

    /**
     * Generate a unique public ID
     */
    protected function generatePublicId(): string
    {
        do {
            $publicId = $this->createPublicId();
        } while (static::where('public_id', $publicId)->exists());

        return $publicId;
    }

    /**
     * Create the public ID format
     */
    protected function createPublicId(): string
    {
        $prefix = $this->getPublicIdPrefix();
        $suffix = $this->generatePublicIdSuffix();
        
        return $prefix . '_' . $suffix;
    }

    /**
     * Get the public ID prefix based on model
     */
    protected function getPublicIdPrefix(): string
    {
        return strtolower(class_basename(static::class));
    }

    /**
     * Generate the public ID suffix
     */
    protected function generatePublicIdSuffix(): string
    {
        return Str::random(12);
    }

    /**
     * Find model by public ID
     */
    public static function findByPublicId(string $publicId): ?static
    {
        return static::where('public_id', $publicId)->first();
    }

    /**
     * Find model by public ID or fail
     */
    public static function findByPublicIdOrFail(string $publicId): static
    {
        return static::where('public_id', $publicId)->firstOrFail();
    }

    /**
     * Scope to find by public ID
     */
    public function scopeByPublicId(Builder $query, string $publicId): Builder
    {
        return $query->where('public_id', $publicId);
    }

    /**
     * Get route key name for model binding
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Get the route key value
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute('public_id');
    }

    /**
     * Resolve route binding
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        return $this->where($field ?? 'public_id', $value)->first();
    }
}
```

## Public ID Implementation

### Model Integration

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    use HasSecondaryUniqueKey, HasSlug, SoftDeletes;

    protected $fillable = [
        'name',
        'bio',
        'public_id',
        'slug',
    ];

    protected function cast(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Custom public ID prefix
     */
    protected function getPublicIdPrefix(): string
    {
        return 'art';
    }

    /**
     * Custom public ID suffix with timestamp
     */
    protected function generatePublicIdSuffix(): string
    {
        return now()->format('ymd') . Str::random(8);
    }

    /**
     * Get display identifier
     */
    public function getDisplayIdAttribute(): string
    {
        return $this->public_id ?? "#{$this->id}";
    }
}
```

### Advanced Public ID Patterns

```php
<?php
// Different public ID patterns for different models

class Album extends Model
{
    use HasSecondaryUniqueKey;

    /**
     * Album-specific public ID with year
     */
    protected function createPublicId(): string
    {
        $year = $this->release_date ? 
            $this->release_date->format('Y') : 
            now()->format('Y');
            
        return 'alb_' . $year . '_' . Str::random(8);
    }
}

class Track extends Model
{
    use HasSecondaryUniqueKey;

    /**
     * Track-specific public ID with duration hint
     */
    protected function createPublicId(): string
    {
        $duration = $this->duration_ms ? 
            substr(md5($this->duration_ms), 0, 4) : 
            Str::random(4);
            
        return 'trk_' . $duration . '_' . Str::random(6);
    }
}

class Playlist extends Model
{
    use HasSecondaryUniqueKey;

    /**
     * Playlist-specific public ID with user hint
     */
    protected function createPublicId(): string
    {
        $userHint = $this->user_id ? 
            substr(md5($this->user_id), 0, 3) : 
            'pub';
            
        return 'pls_' . $userHint . '_' . Str::random(8);
    }
}
```

## UUID Integration

### UUID Secondary Keys

```php
<?php
// app/Traits/HasUuidSecondaryKey.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasUuidSecondaryKey
{
    use HasSecondaryUniqueKey;

    /**
     * Generate UUID-based public ID
     */
    protected function generatePublicIdSuffix(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Create UUID public ID without prefix
     */
    protected function createPublicId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Validate UUID format
     */
    public function scopeValidUuid(Builder $query, string $uuid): Builder
    {
        if (!Str::isUuid($uuid)) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }
        
        return $query->where('public_id', $uuid);
    }
}
```

## Slug Generation

### Slug Integration with Secondary Keys

```php
<?php
// app/Traits/HasSlug.php (Enhanced)

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the slug trait
     */
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty($model->getSlugSourceAttribute()) && empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });
    }

    /**
     * Generate slug from public_id if available
     */
    protected function generateSlug(): string
    {
        $source = $this->getSlugSource();
        $baseSlug = Str::slug($source);
        
        return $this->ensureUniqueSlug($baseSlug);
    }

    /**
     * Get slug source - prefer public_id over name
     */
    protected function getSlugSource(): string
    {
        if (!empty($this->public_id)) {
            return $this->public_id;
        }
        
        return $this->getAttribute($this->getSlugSourceAttribute());
    }

    /**
     * Get the attribute to generate slug from
     */
    protected function getSlugSourceAttribute(): string
    {
        return 'name';
    }

    /**
     * Ensure slug uniqueness
     */
    protected function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Find by slug
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Scope by slug
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
```

## Database Schema

### Migration for Secondary Keys

```php
<?php
// database/migrations/add_secondary_keys_to_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Artists table
        Schema::table('artists', function (Blueprint $table) {
            $table->string('public_id', 50)->unique()->after('id');
            $table->string('slug')->unique()->after('public_id');
            $table->index(['public_id', 'slug']);
        });

        // Albums table
        Schema::table('albums', function (Blueprint $table) {
            $table->string('public_id', 50)->unique()->after('id');
            $table->string('slug')->unique()->after('public_id');
            $table->index(['public_id', 'slug']);
        });

        // Tracks table
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('public_id', 50)->unique()->after('id');
            $table->string('slug')->unique()->after('public_id');
            $table->index(['public_id', 'slug']);
        });

        // Add indexes for performance
        Schema::table('artists', function (Blueprint $table) {
            $table->index('public_id', 'idx_artists_public_id');
            $table->index('slug', 'idx_artists_slug');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropIndex('idx_artists_public_id');
            $table->dropIndex('idx_artists_slug');
            $table->dropColumn(['public_id', 'slug']);
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['public_id', 'slug']);
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['public_id', 'slug']);
        });
    }
};
```

## Query Optimization

### Efficient Secondary Key Queries

```php
<?php
// Optimized query patterns for secondary keys

class SecondaryKeyQueryOptimizer
{
    /**
     * Batch find by public IDs
     */
    public static function findManyByPublicIds(string $modelClass, array $publicIds): Collection
    {
        return $modelClass::whereIn('public_id', $publicIds)
            ->get()
            ->keyBy('public_id');
    }

    /**
     * Cached public ID lookup
     */
    public static function findByPublicIdCached(string $modelClass, string $publicId): ?Model
    {
        $cacheKey = "model_public_id_{$modelClass}_{$publicId}";

        return cache()->remember($cacheKey, 3600, function () use ($modelClass, $publicId) {
            return $modelClass::findByPublicId($publicId);
        });
    }

    /**
     * Bulk slug generation
     */
    public static function generateSlugsInBatch(Collection $models): void
    {
        $models->each(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateSlug();
                $model->save();
            }
        });
    }
}
```

### Database Indexing Strategy

```php
<?php
// Advanced indexing for secondary keys

// In migration
Schema::table('artists', function (Blueprint $table) {
    // Composite index for common queries
    $table->index(['public_id', 'is_active'], 'idx_artists_public_active');

    // Partial index for active records only (PostgreSQL)
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('CREATE INDEX idx_artists_active_public ON artists (public_id) WHERE is_active = true');
    }

    // Full-text search index for slugs
    $table->fullText(['slug', 'name'], 'idx_artists_search');
});
```

## Security Considerations

### Secure Secondary Key Implementation

```php
<?php
// app/Traits/SecureSecondaryKey.php

namespace App\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait SecureSecondaryKey
{
    use HasSecondaryUniqueKey;

    /**
     * Generate cryptographically secure public ID
     */
    protected function generatePublicIdSuffix(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Validate public ID format
     */
    public static function isValidPublicId(string $publicId): bool
    {
        $pattern = '/^[a-z]{3}_[a-f0-9]{16}$/';
        return preg_match($pattern, $publicId) === 1;
    }

    /**
     * Rate-limited public ID lookup
     */
    public static function findByPublicIdRateLimited(string $publicId, string $clientIp): ?static
    {
        $key = "public_id_lookup_{$clientIp}";
        $attempts = cache()->get($key, 0);

        if ($attempts >= 100) { // 100 requests per hour
            throw new TooManyRequestsException('Rate limit exceeded for public ID lookups');
        }

        cache()->put($key, $attempts + 1, 3600);

        return static::findByPublicId($publicId);
    }

    /**
     * Obfuscated public ID for logs
     */
    public function getObfuscatedPublicId(): string
    {
        $publicId = $this->public_id;
        if (strlen($publicId) <= 8) {
            return $publicId;
        }

        return substr($publicId, 0, 4) . '***' . substr($publicId, -4);
    }
}
```

### Access Control for Secondary Keys

```php
<?php
// app/Policies/SecondaryKeyPolicy.php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SecondaryKeyPolicy
{
    /**
     * Determine if user can view public ID
     */
    public function viewPublicId(User $user, Model $model): bool
    {
        // Public IDs are generally viewable, but check model-specific rules
        return $this->canView($user, $model);
    }

    /**
     * Determine if user can regenerate public ID
     */
    public function regeneratePublicId(User $user, Model $model): bool
    {
        return $user->can('update', $model) &&
               $user->hasPermissionTo('regenerate-public-ids');
    }

    /**
     * Determine if user can view slug
     */
    public function viewSlug(User $user, Model $model): bool
    {
        return true; // Slugs are generally public
    }

    /**
     * Determine if user can update slug
     */
    public function updateSlug(User $user, Model $model): bool
    {
        return $user->can('update', $model);
    }
}
```

## Testing Secondary Keys

### Comprehensive Test Suite

```php
<?php
// tests/Feature/SecondaryKeyTest.php

use App\Models\Artist;
use Tests\TestCase;

class SecondaryKeyTest extends TestCase
{
    public function test_generates_public_id_on_creation(): void
    {
        $artist = Artist::factory()->create();

        $this->assertNotNull($artist->public_id);
        $this->assertStringStartsWith('art_', $artist->public_id);
        $this->assertEquals(16, strlen(explode('_', $artist->public_id)[1]));
    }

    public function test_public_id_is_unique(): void
    {
        $artists = Artist::factory()->count(100)->create();
        $publicIds = $artists->pluck('public_id')->toArray();

        $this->assertEquals(100, count(array_unique($publicIds)));
    }

    public function test_can_find_by_public_id(): void
    {
        $artist = Artist::factory()->create();

        $found = Artist::findByPublicId($artist->public_id);

        $this->assertTrue($found->is($artist));
    }

    public function test_generates_slug_from_name(): void
    {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);

        $this->assertEquals('the-beatles', $artist->slug);
    }

    public function test_slug_uniqueness(): void
    {
        Artist::factory()->create(['name' => 'The Beatles']);
        $duplicate = Artist::factory()->create(['name' => 'The Beatles']);

        $this->assertEquals('the-beatles-1', $duplicate->slug);
    }

    public function test_route_model_binding_uses_public_id(): void
    {
        $artist = Artist::factory()->create();

        $response = $this->get("/artists/{$artist->public_id}");

        $response->assertOk();
        $response->assertViewHas('artist', $artist);
    }

    public function test_public_id_validation(): void
    {
        $this->assertTrue(Artist::isValidPublicId('art_1234567890abcdef'));
        $this->assertFalse(Artist::isValidPublicId('invalid_id'));
        $this->assertFalse(Artist::isValidPublicId('art_short'));
    }
}
```

## Best Practices

### Secondary Key Guidelines

1. **Consistency**: Use consistent prefixes across similar models
2. **Security**: Generate cryptographically secure identifiers
3. **Performance**: Add proper database indexes for secondary keys
4. **Validation**: Always validate secondary key formats
5. **Caching**: Cache frequently accessed secondary key lookups
6. **Logging**: Use obfuscated IDs in logs for security

### Implementation Checklist

```php
<?php
// Secondary key implementation checklist

/*
✓ Add public_id column with unique constraint
✓ Add slug column with unique constraint
✓ Implement HasSecondaryUniqueKey trait
✓ Implement HasSlug trait
✓ Add database indexes for performance
✓ Configure route model binding
✓ Add validation rules
✓ Write comprehensive tests
✓ Implement caching strategy
✓ Add security policies
✓ Document public ID format
✓ Set up monitoring for uniqueness violations
*/
```

### Performance Optimization Tips

```php
<?php
// Performance tips for secondary keys

// 1. Use database indexes
Schema::table('models', function (Blueprint $table) {
    $table->index('public_id');
    $table->index('slug');
    $table->index(['public_id', 'is_active']); // Composite index
});

// 2. Cache frequent lookups
public static function findByPublicIdCached(string $publicId): ?static
{
    return cache()->remember("model_public_id_{$publicId}", 3600, function () use ($publicId) {
        return static::findByPublicId($publicId);
    });
}

// 3. Batch operations
public static function findManyByPublicIds(array $publicIds): Collection
{
    return static::whereIn('public_id', $publicIds)->get()->keyBy('public_id');
}

// 4. Eager load relationships
public function scopeWithRelationsForPublicView(Builder $query): Builder
{
    return $query->with(['categories', 'tags', 'media']);
}
```

## Navigation

**← Previous:** [Categorizable Trait Guide](060-categorizable-trait.md)
**Next →** [Category Management Guide](090-category-management.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Required Traits Guide](020-required-traits.md) - Essential trait implementations
- [Casting Patterns Guide](030-casting-patterns.md) - Modern casting techniques

---

*This guide provides comprehensive secondary key implementation for Laravel 12 models in the Chinook application. The system includes public IDs, UUIDs, and slug generation with performance optimization and security considerations.*
