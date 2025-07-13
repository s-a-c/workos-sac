# Model Scopes Guide

## Table of Contents

- [Overview](#overview)
- [Basic Query Scopes](#basic-query-scopes)
- [Advanced Scope Patterns](#advanced-scope-patterns)
- [Dynamic Scopes](#dynamic-scopes)
- [Global Scopes](#global-scopes)
- [Scope Composition](#scope-composition)
- [Performance Optimization](#performance-optimization)
- [Testing Scopes](#testing-scopes)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive model scope patterns for Laravel 12 in the Chinook application. Scopes provide a clean way to encapsulate common query logic, making code more readable, reusable, and maintainable.

**🚀 Key Features:**
- **Query Encapsulation**: Reusable query logic
- **Chainable Methods**: Fluent query building
- **Performance Optimized**: Efficient database queries
- **Type Safety**: Proper return type declarations
- **WCAG 2.1 AA Compliance**: Accessible data filtering

## Basic Query Scopes

### Artist Model Scopes

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Artist extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasUserStamps;

    /**
     * Scope to filter active artists
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter inactive artists
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to filter featured artists
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter artists by genre
     */
    public function scopeByGenre(Builder $query, string $genre): Builder
    {
        return $query->whereJsonContains('metadata->genre_primary', $genre);
    }

    /**
     * Scope to filter artists by country
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->whereJsonContains('metadata->country_origin', $country);
    }

    /**
     * Scope to search artists by name
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('bio', 'LIKE', "%{$term}%");
    }

    /**
     * Scope to filter artists with albums
     */
    public function scopeWithAlbums(Builder $query): Builder
    {
        return $query->has('albums');
    }

    /**
     * Scope to filter artists without albums
     */
    public function scopeWithoutAlbums(Builder $query): Builder
    {
        return $query->doesntHave('albums');
    }

    /**
     * Scope to order by popularity (album count)
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->withCount('albums')
                    ->orderBy('albums_count', 'desc');
    }

    /**
     * Scope to filter recently created artists
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter artists created by user
     */
    public function scopeCreatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }
}
```

### Album Model Scopes

```php
<?php
// app/Models/Album.php

class Album extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasUserStamps;

    /**
     * Scope to filter published albums
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
                    ->where('release_date', '<=', now());
    }

    /**
     * Scope to filter upcoming releases
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('is_published', true)
                    ->where('release_date', '>', now());
    }

    /**
     * Scope to filter albums by release year
     */
    public function scopeByYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('release_date', $year);
    }

    /**
     * Scope to filter albums by decade
     */
    public function scopeByDecade(Builder $query, int $decade): Builder
    {
        $startYear = $decade;
        $endYear = $decade + 9;
        
        return $query->whereBetween(
            DB::raw('YEAR(release_date)'), 
            [$startYear, $endYear]
        );
    }

    /**
     * Scope to filter compilation albums
     */
    public function scopeCompilations(Builder $query): Builder
    {
        return $query->where('is_compilation', true);
    }

    /**
     * Scope to filter studio albums
     */
    public function scopeStudioAlbums(Builder $query): Builder
    {
        return $query->where('is_compilation', false)
                    ->whereJsonDoesntContain('metadata->recording_type', 'live');
    }

    /**
     * Scope to filter live albums
     */
    public function scopeLiveAlbums(Builder $query): Builder
    {
        return $query->whereJsonContains('metadata->recording_type', 'live');
    }

    /**
     * Scope to filter albums with minimum track count
     */
    public function scopeWithMinTracks(Builder $query, int $minTracks): Builder
    {
        return $query->where('track_count', '>=', $minTracks);
    }

    /**
     * Scope to filter albums by duration range
     */
    public function scopeByDuration(Builder $query, int $minMs, int $maxMs): Builder
    {
        return $query->whereBetween('total_duration_ms', [$minMs, $maxMs]);
    }

    /**
     * Scope to order by release date
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('release_date', 'desc');
    }

    /**
     * Scope to order by oldest first
     */
    public function scopeOldest(Builder $query): Builder
    {
        return $query->orderBy('release_date', 'asc');
    }
}
```

## Advanced Scope Patterns

### Track Model with Complex Scopes

```php
<?php
// app/Models/Track.php

class Track extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasUserStamps;

    /**
     * Scope to filter tracks available for purchase
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_published', true)
                    ->where('is_available', true)
                    ->whereNotNull('file_path');
    }

    /**
     * Scope to filter explicit content
     */
    public function scopeExplicit(Builder $query): Builder
    {
        return $query->where('is_explicit', true);
    }

    /**
     * Scope to filter clean content
     */
    public function scopeClean(Builder $query): Builder
    {
        return $query->where('is_explicit', false);
    }

    /**
     * Scope to filter tracks by price range
     */
    public function scopeByPriceRange(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope to filter free tracks
     */
    public function scopeFree(Builder $query): Builder
    {
        return $query->where('price', 0);
    }

    /**
     * Scope to filter tracks by duration
     */
    public function scopeByDuration(Builder $query, int $minSeconds, int $maxSeconds): Builder
    {
        $minMs = $minSeconds * 1000;
        $maxMs = $maxSeconds * 1000;
        
        return $query->whereBetween('duration_ms', [$minMs, $maxMs]);
    }

    /**
     * Scope to filter short tracks (under 3 minutes)
     */
    public function scopeShort(Builder $query): Builder
    {
        return $query->where('duration_ms', '<', 180000); // 3 minutes
    }

    /**
     * Scope to filter long tracks (over 6 minutes)
     */
    public function scopeLong(Builder $query): Builder
    {
        return $query->where('duration_ms', '>', 360000); // 6 minutes
    }

    /**
     * Scope to filter tracks with lyrics
     */
    public function scopeWithLyrics(Builder $query): Builder
    {
        return $query->whereNotNull('lyrics')
                    ->where('lyrics', '!=', '');
    }

    /**
     * Scope to filter instrumental tracks
     */
    public function scopeInstrumental(Builder $query): Builder
    {
        return $query->whereNull('lyrics')
                    ->orWhere('lyrics', '');
    }

    /**
     * Scope to filter tracks by BPM range
     */
    public function scopeByBpm(Builder $query, int $minBpm, int $maxBpm): Builder
    {
        return $query->whereJsonBetween('metadata->bpm', [$minBpm, $maxBpm]);
    }

    /**
     * Scope to filter tracks by energy level
     */
    public function scopeByEnergyLevel(Builder $query, int $level): Builder
    {
        return $query->whereJsonContains('metadata->energy_level', $level);
    }

    /**
     * Scope to filter high energy tracks
     */
    public function scopeHighEnergy(Builder $query): Builder
    {
        return $query->whereJson('metadata->energy_level', '>=', 7);
    }

    /**
     * Scope to filter tracks by musical key
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->whereJsonContains('metadata->key', $key);
    }

    /**
     * Scope to filter featured tracks
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to order by popularity (play count)
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc(
            DB::raw('COALESCE(JSON_EXTRACT(metadata, "$.play_count"), 0)')
        );
    }
}
```

## Dynamic Scopes

### Playlist Model with Dynamic Filtering

```php
<?php
// app/Models/Playlist.php

class Playlist extends Model
{
    use HasFactory, SoftDeletes, HasUserStamps;

    /**
     * Scope to filter public playlists
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to filter collaborative playlists
     */
    public function scopeCollaborative(Builder $query): Builder
    {
        return $query->where('is_collaborative', true);
    }

    /**
     * Scope to filter playlists by user access
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('is_public', true)
              ->orWhere('created_by', $user->id)
              ->orWhereHas('collaborators', function ($collaboratorQuery) use ($user) {
                  $collaboratorQuery->where('user_id', $user->id);
              });
        });
    }

    /**
     * Scope to filter playlists by genre
     */
    public function scopeByGenre(Builder $query, string $genre): Builder
    {
        return $query->whereHas('tracks.album.artist', function ($artistQuery) use ($genre) {
            $artistQuery->whereJsonContains('metadata->genre_primary', $genre);
        });
    }

    /**
     * Scope to filter playlists by mood
     */
    public function scopeByMood(Builder $query, string $mood): Builder
    {
        return $query->whereHas('tags', function ($tagQuery) use ($mood) {
            $tagQuery->where('name', $mood)
                    ->where('type', 'mood');
        });
    }

    /**
     * Scope to filter playlists with minimum track count
     */
    public function scopeWithMinTracks(Builder $query, int $minTracks): Builder
    {
        return $query->has('tracks', '>=', $minTracks);
    }

    /**
     * Scope to filter recently updated playlists
     */
    public function scopeRecentlyUpdated(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to search playlists
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%")
              ->orWhereHas('tracks', function ($trackQuery) use ($term) {
                  $trackQuery->where('name', 'LIKE', "%{$term}%");
              });
        });
    }

    /**
     * Scope to filter playlists by duration range
     */
    public function scopeByDuration(Builder $query, int $minMinutes, int $maxMinutes): Builder
    {
        $minMs = $minMinutes * 60 * 1000;
        $maxMs = $maxMinutes * 60 * 1000;
        
        return $query->whereHas('tracks', function ($trackQuery) use ($minMs, $maxMs) {
            $trackQuery->selectRaw('SUM(duration_ms) as total_duration')
                      ->havingBetween('total_duration', [$minMs, $maxMs]);
        });
    }
}
```

## Global Scopes

### Tenant Scope for Multi-Tenancy

```php
<?php
// app/Scopes/TenantScope.php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    }
}

// app/Scopes/PublishedScope.php
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('is_published', true);
    }
}

// Usage in models
class Track extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new PublishedScope);
    }
}
```

## Scope Composition

### Chainable Scope Patterns

```php
<?php
// Complex scope combinations

class MusicDiscoveryService
{
    /**
     * Get recommended tracks for user
     */
    public function getRecommendedTracks(User $user): Collection
    {
        $userGenres = $this->getUserPreferredGenres($user);
        $userEnergyLevel = $this->getUserEnergyPreference($user);
        
        return Track::available()
            ->clean() // Assume user prefers clean content
            ->byEnergyLevel($userEnergyLevel)
            ->whereHas('album.artist', function ($query) use ($userGenres) {
                $query->whereIn('metadata->genre_primary', $userGenres);
            })
            ->whereNotIn('id', $user->recentlyPlayedTracks()->pluck('id'))
            ->popular()
            ->limit(50)
            ->get();
    }

    /**
     * Get workout playlist tracks
     */
    public function getWorkoutTracks(): Collection
    {
        return Track::available()
            ->highEnergy()
            ->byBpm(120, 180) // Good workout BPM range
            ->byDuration(180, 300) // 3-5 minutes
            ->popular()
            ->limit(30)
            ->get();
    }

    /**
     * Get chill/relaxation tracks
     */
    public function getChillTracks(): Collection
    {
        return Track::available()
            ->whereJson('metadata->energy_level', '<=', 4)
            ->byBpm(60, 100) // Slower tempo
            ->whereHas('tags', function ($query) {
                $query->whereIn('name', ['chill', 'ambient', 'relaxing']);
            })
            ->orderBy('created_at', 'desc')
            ->limit(25)
            ->get();
    }
}
```

## Performance Optimization

### Efficient Scope Implementation

```php
<?php
// Optimized scopes for performance

class Artist extends Model
{
    /**
     * Efficient scope with proper indexing
     */
    public function scopeActiveWithAlbums(Builder $query): Builder
    {
        return $query->where('is_active', true)
                    ->whereExists(function ($subQuery) {
                        $subQuery->select(DB::raw(1))
                                ->from('albums')
                                ->whereColumn('albums.artist_id', 'artists.id')
                                ->where('albums.is_published', true);
                    });
    }

    /**
     * Scope with eager loading
     */
    public function scopeWithRelatedData(Builder $query): Builder
    {
        return $query->with([
            'albums' => function ($albumQuery) {
                $albumQuery->select(['id', 'artist_id', 'title', 'release_date'])
                          ->published()
                          ->latest();
            },
            'albums.tracks' => function ($trackQuery) {
                $trackQuery->select(['id', 'album_id', 'name', 'duration_ms'])
                          ->available();
            }
        ]);
    }

    /**
     * Scope with counting optimization
     */
    public function scopeWithCounts(Builder $query): Builder
    {
        return $query->withCount([
            'albums',
            'albums as published_albums_count' => function ($albumQuery) {
                $albumQuery->published();
            },
            'tracks' => function ($trackQuery) {
                $trackQuery->join('albums', 'tracks.album_id', '=', 'albums.id')
                          ->where('albums.artist_id', '=', DB::raw('artists.id'));
            }
        ]);
    }
}
```

## Testing Scopes

### Comprehensive Scope Testing

```php
<?php
// tests/Unit/Scopes/ArtistScopeTest.php

use App\Models\Artist;
use Tests\TestCase;

class ArtistScopeTest extends TestCase
{
    public function test_active_scope_filters_correctly(): void
    {
        Artist::factory()->create(['is_active' => true]);
        Artist::factory()->create(['is_active' => false]);

        $activeArtists = Artist::active()->get();

        expect($activeArtists)->toHaveCount(1);
        expect($activeArtists->first()->is_active)->toBeTrue();
    }

    public function test_by_genre_scope_filters_correctly(): void
    {
        Artist::factory()->create([
            'metadata' => ['genre_primary' => 'Rock']
        ]);
        Artist::factory()->create([
            'metadata' => ['genre_primary' => 'Pop']
        ]);

        $rockArtists = Artist::byGenre('Rock')->get();

        expect($rockArtists)->toHaveCount(1);
        expect($rockArtists->first()->metadata['genre_primary'])->toBe('Rock');
    }

    public function test_search_scope_searches_name_and_bio(): void
    {
        Artist::factory()->create([
            'name' => 'The Beatles',
            'bio' => 'Famous rock band'
        ]);
        Artist::factory()->create([
            'name' => 'Elvis Presley',
            'bio' => 'King of rock and roll'
        ]);

        $searchResults = Artist::search('rock')->get();

        expect($searchResults)->toHaveCount(2);
    }

    public function test_scope_chaining_works(): void
    {
        Artist::factory()->create([
            'is_active' => true,
            'is_featured' => true,
            'metadata' => ['genre_primary' => 'Rock']
        ]);
        Artist::factory()->create([
            'is_active' => false,
            'is_featured' => true,
            'metadata' => ['genre_primary' => 'Rock']
        ]);

        $results = Artist::active()
                        ->featured()
                        ->byGenre('Rock')
                        ->get();

        expect($results)->toHaveCount(1);
    }
}
```

## Best Practices

### Scope Guidelines

1. **Descriptive Names**: Use clear, descriptive scope names
2. **Single Responsibility**: Each scope should have one clear purpose
3. **Chainability**: Design scopes to work well together
4. **Performance**: Consider database performance and indexing
5. **Testing**: Write tests for all scopes
6. **Documentation**: Document complex scope logic

### Scope Organization

```php
<?php
// Organized scope structure

class Track extends Model
{
    // Status scopes
    public function scopePublished(Builder $query): Builder { /* ... */ }
    public function scopeAvailable(Builder $query): Builder { /* ... */ }
    public function scopeFeatured(Builder $query): Builder { /* ... */ }

    // Content scopes
    public function scopeExplicit(Builder $query): Builder { /* ... */ }
    public function scopeWithLyrics(Builder $query): Builder { /* ... */ }
    public function scopeInstrumental(Builder $query): Builder { /* ... */ }

    // Filtering scopes
    public function scopeByGenre(Builder $query, string $genre): Builder { /* ... */ }
    public function scopeByDuration(Builder $query, int $min, int $max): Builder { /* ... */ }
    public function scopeByPrice(Builder $query, float $min, float $max): Builder { /* ... */ }

    // Ordering scopes
    public function scopePopular(Builder $query): Builder { /* ... */ }
    public function scopeLatest(Builder $query): Builder { /* ... */ }
    public function scopeRandom(Builder $query): Builder { /* ... */ }

    // Search scopes
    public function scopeSearch(Builder $query, string $term): Builder { /* ... */ }
    public function scopeAdvancedSearch(Builder $query, array $criteria): Builder { /* ... */ }
}
```

## Navigation

**← Previous:** [Model Policies Guide](110-model-policies.md)
**Next →** [Validation Rules Guide](040-validation-rules.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Relationship Patterns Guide](040-relationship-patterns.md) - Model relationships
- [Database Optimization](../deployment/060-database-optimization.md) - Database performance

---

*This guide provides comprehensive model scope patterns for Laravel 12 in the Chinook application. Each pattern includes performance optimization, testing strategies, and best practices for maintainable and efficient query logic.*
