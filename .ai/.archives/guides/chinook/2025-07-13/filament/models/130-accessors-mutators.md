# Accessors and Mutators Guide

## Table of Contents

- [Overview](#overview)
- [Laravel 12 Accessor Patterns](#laravel-12-accessor-patterns)
- [Laravel 12 Mutator Patterns](#laravel-12-mutator-patterns)
- [Attribute Casting Integration](#attribute-casting-integration)
- [Computed Properties](#computed-properties)
- [Data Transformation](#data-transformation)
- [Performance Considerations](#performance-considerations)
- [Testing Accessors and Mutators](#testing-accessors-and-mutators)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive accessor and mutator patterns for Laravel 12 models in the Chinook application. The focus is on modern Laravel 12 syntax, data transformation, computed properties, and performance optimization for attribute manipulation.

**🚀 Key Features:**
- **Laravel 12 Modern Syntax**: Using new accessor/mutator patterns
- **Data Transformation**: Automatic data formatting and processing
- **Computed Properties**: Dynamic attribute calculation
- **Performance Optimized**: Efficient attribute handling
- **WCAG 2.1 AA Compliance**: Accessible data presentation

## Laravel 12 Accessor Patterns

### Modern Accessor Implementation

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Artist extends Model
{
    /**
     * Get the artist's display name
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['stage_name'] ?? $attributes['name'] ?? 'Unknown Artist'
        );
    }

    /**
     * Get the artist's formatted bio
     */
    protected function formattedBio(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['bio'] ? Str::limit(strip_tags($attributes['bio']), 200) : null
        );
    }

    /**
     * Get the artist's full URL
     */
    protected function fullUrl(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                url("/artists/{$attributes['slug']}")
        );
    }

    /**
     * Get the artist's avatar URL with fallback
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if ($attributes['avatar_path']) {
                    return asset("storage/{$attributes['avatar_path']}");
                }
                
                // Generate avatar from name
                $name = urlencode($attributes['name'] ?? 'Artist');
                return "https://ui-avatars.com/api/?name={$name}&size=200&background=1976d2&color=fff";
            }
        );
    }

    /**
     * Get the artist's social media links
     */
    protected function socialLinks(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $metadata = json_decode($attributes['metadata'] ?? '{}', true);
                $social = $metadata['social'] ?? [];
                
                return collect($social)->map(function ($url, $platform) {
                    return [
                        'platform' => $platform,
                        'url' => $url,
                        'icon' => $this->getSocialIcon($platform),
                    ];
                })->values()->toArray();
            }
        );
    }

    /**
     * Get the artist's popularity score
     */
    protected function popularityScore(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Calculate based on albums, tracks, and engagement
                $albumCount = $this->albums()->count();
                $trackCount = $this->tracks()->count();
                $viewCount = $attributes['view_count'] ?? 0;
                
                return ($albumCount * 10) + ($trackCount * 2) + ($viewCount * 0.1);
            }
        );
    }

    /**
     * Get formatted creation date
     */
    protected function createdAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $this->created_at?->format('M j, Y')
        );
    }

    /**
     * Get time since creation
     */
    protected function timeSinceCreation(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $this->created_at?->diffForHumans()
        );
    }

    /**
     * Helper method for social icons
     */
    protected function getSocialIcon(string $platform): string
    {
        $icons = [
            'twitter' => 'fab fa-twitter',
            'facebook' => 'fab fa-facebook',
            'instagram' => 'fab fa-instagram',
            'youtube' => 'fab fa-youtube',
            'spotify' => 'fab fa-spotify',
            'website' => 'fas fa-globe',
        ];

        return $icons[$platform] ?? 'fas fa-link';
    }
}
```

### Album Accessors

```php
<?php
// app/Models/Album.php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Album extends Model
{
    /**
     * Get the album's display title
     */
    protected function displayTitle(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['title'] ?? 'Untitled Album'
        );
    }

    /**
     * Get formatted release date
     */
    protected function releaseDateFormatted(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!$attributes['release_date']) {
                    return 'Release date unknown';
                }
                
                return Carbon::parse($attributes['release_date'])->format('F j, Y');
            }
        );
    }

    /**
     * Get release year
     */
    protected function releaseYear(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['release_date'] ? 
                    Carbon::parse($attributes['release_date'])->year : 
                    null
        );
    }

    /**
     * Get album duration from tracks
     */
    protected function totalDuration(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $totalMs = $this->tracks()->sum('duration_ms');
                return $this->formatDuration($totalMs);
            }
        );
    }

    /**
     * Get track count
     */
    protected function trackCount(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $this->tracks()->count()
        );
    }

    /**
     * Get album cover URL with fallback
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if ($attributes['cover_path']) {
                    return asset("storage/{$attributes['cover_path']}");
                }
                
                // Generate placeholder
                $title = urlencode($attributes['title'] ?? 'Album');
                return "https://via.placeholder.com/300x300/1976d2/ffffff?text={$title}";
            }
        );
    }

    /**
     * Get album rating display
     */
    protected function ratingDisplay(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $rating = $attributes['average_rating'] ?? 0;
                $stars = str_repeat('★', floor($rating)) . str_repeat('☆', 5 - floor($rating));
                return "{$stars} ({$rating}/5)";
            }
        );
    }

    /**
     * Helper method to format duration
     */
    protected function formatDuration(int $milliseconds): string
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
```

## Laravel 12 Mutator Patterns

### Modern Mutator Implementation

```php
<?php
// app/Models/Artist.php (continued)

class Artist extends Model
{
    /**
     * Set the artist's name with automatic slug generation
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: function (string $value) {
                return [
                    'name' => trim($value),
                    'slug' => $this->slug ?? Str::slug($value),
                ];
            }
        );
    }

    /**
     * Set and sanitize bio content
     */
    protected function bio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                if (!$value) {
                    return null;
                }
                
                // Sanitize HTML and limit length
                $cleaned = strip_tags($value, '<p><br><strong><em><ul><ol><li>');
                return Str::limit($cleaned, 2000);
            }
        );
    }

    /**
     * Set email with validation and normalization
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: fn (?string $value) => $value ? strtolower(trim($value)) : null
        );
    }

    /**
     * Set metadata with validation
     */
    protected function metadata(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value, true) : [],
            set: function (array $value) {
                // Validate and sanitize metadata
                $sanitized = $this->sanitizeMetadata($value);
                return json_encode($sanitized);
            }
        );
    }

    /**
     * Set social media links with validation
     */
    protected function socialMedia(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $metadata = json_decode($attributes['metadata'] ?? '{}', true);
                return $metadata['social'] ?? [];
            },
            set: function (array $value) {
                $metadata = json_decode($this->attributes['metadata'] ?? '{}', true);
                $metadata['social'] = $this->validateSocialLinks($value);
                
                return [
                    'metadata' => json_encode($metadata)
                ];
            }
        );
    }

    /**
     * Sanitize metadata array
     */
    protected function sanitizeMetadata(array $metadata): array
    {
        $allowed = ['social', 'preferences', 'stats', 'settings'];
        
        return collect($metadata)
            ->only($allowed)
            ->map(function ($value, $key) {
                return match($key) {
                    'social' => $this->validateSocialLinks($value),
                    'preferences' => $this->validatePreferences($value),
                    'stats' => $this->validateStats($value),
                    'settings' => $this->validateSettings($value),
                    default => $value
                };
            })
            ->toArray();
    }

    /**
     * Validate social media links
     */
    protected function validateSocialLinks(array $links): array
    {
        $validPlatforms = ['twitter', 'facebook', 'instagram', 'youtube', 'spotify', 'website'];
        
        return collect($links)
            ->only($validPlatforms)
            ->filter(fn ($url) => filter_var($url, FILTER_VALIDATE_URL))
            ->toArray();
    }

    /**
     * Validate preferences
     */
    protected function validatePreferences(array $preferences): array
    {
        return collect($preferences)
            ->only(['theme', 'language', 'notifications'])
            ->toArray();
    }

    /**
     * Validate stats
     */
    protected function validateStats(array $stats): array
    {
        return collect($stats)
            ->only(['view_count', 'play_count', 'like_count'])
            ->map(fn ($value) => max(0, (int) $value))
            ->toArray();
    }

    /**
     * Validate settings
     */
    protected function validateSettings(array $settings): array
    {
        return collect($settings)
            ->only(['public_profile', 'show_stats', 'allow_messages'])
            ->map(fn ($value) => (bool) $value)
            ->toArray();
    }
}
```

## Attribute Casting Integration

### Combined Casting and Accessors

```php
<?php
// app/Models/Track.php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Track extends Model
{
    protected function cast(): array
    {
        return [
            'duration_ms' => 'integer',
            'track_number' => 'integer',
            'is_explicit' => 'boolean',
            'metadata' => 'array',
            'release_date' => 'datetime',
        ];
    }

    /**
     * Get formatted duration
     */
    protected function durationFormatted(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $ms = $attributes['duration_ms'] ?? 0;
                $seconds = floor($ms / 1000);
                $minutes = floor($seconds / 60);
                $seconds = $seconds % 60;

                return sprintf('%d:%02d', $minutes, $seconds);
            }
        );
    }

    /**
     * Get track position display
     */
    protected function positionDisplay(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $trackNumber = $attributes['track_number'] ?? 0;
                $discNumber = $attributes['disc_number'] ?? 1;

                if ($discNumber > 1) {
                    return "{$discNumber}-{$trackNumber}";
                }

                return (string) $trackNumber;
            }
        );
    }

    /**
     * Get explicit content indicator
     */
    protected function explicitIndicator(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) =>
                $attributes['is_explicit'] ? '🅴' : ''
        );
    }

    /**
     * Set duration with multiple format support
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $attributes['duration_ms'],
            set: function (mixed $value) {
                if (is_numeric($value)) {
                    return ['duration_ms' => (int) $value];
                }

                if (is_string($value) && preg_match('/^(\d+):(\d{2})$/', $value, $matches)) {
                    $minutes = (int) $matches[1];
                    $seconds = (int) $matches[2];
                    return ['duration_ms' => ($minutes * 60 + $seconds) * 1000];
                }

                return ['duration_ms' => 0];
            }
        );
    }
}
```

## Computed Properties

### Dynamic Attribute Calculation

```php
<?php
// app/Models/Album.php (continued)

class Album extends Model
{
    /**
     * Get album statistics
     */
    protected function statistics(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return [
                    'track_count' => $this->tracks()->count(),
                    'total_duration' => $this->tracks()->sum('duration_ms'),
                    'average_track_length' => $this->tracks()->avg('duration_ms'),
                    'explicit_tracks' => $this->tracks()->where('is_explicit', true)->count(),
                    'play_count' => $this->tracks()->sum('play_count'),
                ];
            }
        );
    }

    /**
     * Get album completeness score
     */
    protected function completenessScore(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $score = 0;
                $maxScore = 100;

                // Basic info (40 points)
                if ($attributes['title']) $score += 10;
                if ($attributes['description']) $score += 10;
                if ($attributes['release_date']) $score += 10;
                if ($attributes['cover_path']) $score += 10;

                // Tracks (30 points)
                $trackCount = $this->tracks()->count();
                if ($trackCount > 0) $score += 15;
                if ($trackCount >= 8) $score += 15;

                // Categories (20 points)
                $categoryCount = $this->categories()->count();
                if ($categoryCount > 0) $score += 10;
                if ($categoryCount >= 3) $score += 10;

                // Metadata (10 points)
                $metadata = json_decode($attributes['metadata'] ?? '{}', true);
                if (!empty($metadata['producer'])) $score += 5;
                if (!empty($metadata['studio'])) $score += 5;

                return min($score, $maxScore);
            }
        );
    }

    /**
     * Get recommendation score
     */
    protected function recommendationScore(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $rating = $attributes['average_rating'] ?? 0;
                $playCount = $attributes['play_count'] ?? 0;
                $recentPlays = $this->getRecentPlayCount();
                $completeness = $this->completeness_score;

                // Weighted scoring
                return ($rating * 0.4) +
                       (min($playCount / 1000, 5) * 0.3) +
                       (min($recentPlays / 100, 5) * 0.2) +
                       (($completeness / 100) * 5 * 0.1);
            }
        );
    }

    /**
     * Get genre distribution
     */
    protected function genreDistribution(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $this->tracks()
                    ->with(['categories' => function ($q) {
                        $q->where('type', 'genre');
                    }])
                    ->get()
                    ->flatMap(fn ($track) => $track->categories)
                    ->groupBy('name')
                    ->map(fn ($genres) => $genres->count())
                    ->sortDesc()
                    ->toArray();
            }
        );
    }

    /**
     * Helper method for recent play count
     */
    protected function getRecentPlayCount(): int
    {
        // This would typically query a plays table
        return 0; // Placeholder
    }
}
```

## Data Transformation

### Advanced Data Processing

```php
<?php
// app/Models/Category.php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    /**
     * Get category breadcrumb
     */
    protected function breadcrumb(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $breadcrumb = [];
                $current = $this;

                while ($current) {
                    array_unshift($breadcrumb, [
                        'id' => $current->id,
                        'name' => $current->name,
                        'slug' => $current->slug,
                        'url' => url("/categories/{$current->slug}"),
                    ]);
                    $current = $current->parent;
                }

                return $breadcrumb;
            }
        );
    }

    /**
     * Get category tree structure
     */
    protected function treeStructure(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'slug' => $this->slug,
                    'level' => $this->getLevel(),
                    'has_children' => $this->children()->exists(),
                    'children_count' => $this->children()->count(),
                    'total_descendants' => $this->descendants()->count(),
                    'path' => $this->getPath(),
                ];
            }
        );
    }

    /**
     * Get usage statistics
     */
    protected function usageStats(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $stats = [];

                // Count usage across different model types
                $modelTypes = ['App\Models\Artist', 'App\Models\Album', 'App\Models\Track'];

                foreach ($modelTypes as $modelType) {
                    $count = DB::table('categorizables')
                        ->where('category_id', $this->id)
                        ->where('categorizable_type', $modelType)
                        ->count();

                    $stats[class_basename($modelType)] = $count;
                }

                $stats['total'] = array_sum($stats);
                return $stats;
            }
        );
    }

    /**
     * Set name with automatic processing
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => $value,
            set: function (string $value) {
                $processed = trim($value);

                // Auto-generate slug if not set
                $slug = $this->slug ?? Str::slug($processed);

                // Ensure uniqueness
                $originalSlug = $slug;
                $counter = 1;

                while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                return [
                    'name' => $processed,
                    'slug' => $slug,
                ];
            }
        );
    }

    /**
     * Set description with processing
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value,
            set: function (?string $value) {
                if (!$value) {
                    return null;
                }

                // Clean and limit description
                $cleaned = strip_tags($value);
                $limited = Str::limit($cleaned, 500);

                return trim($limited);
            }
        );
    }
}
```

## Performance Considerations

### Optimized Accessor/Mutator Patterns

```php
<?php
// app/Traits/OptimizedAttributes.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait OptimizedAttributes
{
    /**
     * Cache expensive computed properties
     */
    protected function getCachedAttribute(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $cacheKey = "model_attr_{$this->getTable()}_{$this->id}_{$key}";

        return cache()->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Invalidate attribute cache
     */
    protected function invalidateAttributeCache(string $key): void
    {
        $cacheKey = "model_attr_{$this->getTable()}_{$this->id}_{$key}";
        cache()->forget($cacheKey);
    }

    /**
     * Batch invalidate attribute caches
     */
    protected function invalidateAllAttributeCaches(): void
    {
        $pattern = "model_attr_{$this->getTable()}_{$this->id}_*";

        // This would depend on your cache driver
        // For Redis: Redis::eval("return redis.call('del', unpack(redis.call('keys', ARGV[1])))", 0, $pattern);
    }

    /**
     * Lazy load expensive attributes
     */
    protected function lazyAttribute(string $key, callable $callback): mixed
    {
        if (!isset($this->lazyAttributes[$key])) {
            $this->lazyAttributes[$key] = $callback();
        }

        return $this->lazyAttributes[$key];
    }
}
```

## Testing Accessors and Mutators

### Comprehensive Test Suite

```php
<?php
// tests/Feature/AccessorsMutatorsTest.php

use App\Models\Artist;
use App\Models\Album;
use Tests\TestCase;

class AccessorsMutatorsTest extends TestCase
{
    public function test_artist_display_name_accessor(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'John Doe',
            'stage_name' => 'Johnny D'
        ]);

        $this->assertEquals('Johnny D', $artist->display_name);

        $artistWithoutStageName = Artist::factory()->create([
            'name' => 'Jane Doe',
            'stage_name' => null
        ]);

        $this->assertEquals('Jane Doe', $artistWithoutStageName->display_name);
    }

    public function test_artist_name_mutator(): void
    {
        $artist = new Artist();
        $artist->name = '  The Beatles  ';

        $this->assertEquals('The Beatles', $artist->name);
        $this->assertEquals('the-beatles', $artist->slug);
    }

    public function test_album_duration_accessor(): void
    {
        $album = Album::factory()->create();

        // Create tracks with known durations
        $album->tracks()->create(['duration_ms' => 180000]); // 3:00
        $album->tracks()->create(['duration_ms' => 240000]); // 4:00

        $this->assertEquals('7:00', $album->total_duration);
    }

    public function test_track_duration_mutator(): void
    {
        $track = new Track();

        // Test with milliseconds
        $track->duration = 180000;
        $this->assertEquals(180000, $track->duration_ms);

        // Test with MM:SS format
        $track->duration = '3:30';
        $this->assertEquals(210000, $track->duration_ms);
    }

    public function test_category_breadcrumb_accessor(): void
    {
        $root = Category::factory()->create(['name' => 'Music']);
        $child = Category::factory()->create([
            'name' => 'Rock',
            'parent_id' => $root->id
        ]);
        $grandchild = Category::factory()->create([
            'name' => 'Progressive Rock',
            'parent_id' => $child->id
        ]);

        $breadcrumb = $grandchild->breadcrumb;

        $this->assertCount(3, $breadcrumb);
        $this->assertEquals('Music', $breadcrumb[0]['name']);
        $this->assertEquals('Rock', $breadcrumb[1]['name']);
        $this->assertEquals('Progressive Rock', $breadcrumb[2]['name']);
    }

    public function test_metadata_mutator_sanitization(): void
    {
        $artist = new Artist();
        $artist->metadata = [
            'social' => [
                'twitter' => 'https://twitter.com/artist',
                'invalid' => 'not-a-url',
                'facebook' => 'https://facebook.com/artist'
            ],
            'stats' => [
                'view_count' => '100',
                'play_count' => -50, // Should be normalized to 0
            ],
            'forbidden_key' => 'should be removed'
        ];

        $metadata = json_decode($artist->attributes['metadata'], true);

        $this->assertArrayHasKey('social', $metadata);
        $this->assertArrayHasKey('stats', $metadata);
        $this->assertArrayNotHasKey('forbidden_key', $metadata);
        $this->assertArrayNotHasKey('invalid', $metadata['social']);
        $this->assertEquals(0, $metadata['stats']['play_count']);
    }
}
```

## Best Practices

### Accessor and Mutator Guidelines

1. **Performance**: Cache expensive computed properties
2. **Validation**: Always validate and sanitize mutator inputs
3. **Consistency**: Use consistent naming patterns for accessors
4. **Documentation**: Document complex accessor/mutator logic
5. **Testing**: Write comprehensive tests for all accessors and mutators
6. **Security**: Sanitize user input in mutators

### Implementation Checklist

```php
<?php
// Accessors and mutators implementation checklist

/*
✓ Use Laravel 12 modern Attribute syntax
✓ Implement computed properties for dynamic data
✓ Add data validation and sanitization in mutators
✓ Cache expensive accessor calculations
✓ Create formatted display accessors for UI
✓ Implement URL and media accessors with fallbacks
✓ Add comprehensive test coverage
✓ Document complex attribute logic
✓ Optimize performance with caching
✓ Validate and sanitize all mutator inputs
✓ Use consistent naming conventions
✓ Implement proper error handling
*/
```

## Navigation

**← Previous:** [Scopes and Filters Guide](120-scopes-filters.md)
**Next →** [Model Events Guide](140-model-events.md)

**Related Guides:**
- [Casting Patterns Guide](030-casting-patterns.md) - Modern casting techniques
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Chart Integration](../features/030-chart-integration.md) - Advanced data processing

---

*This guide provides comprehensive accessor and mutator patterns for Laravel 12 models in the Chinook application. The system includes modern Laravel 12 syntax, data transformation, computed properties, and performance optimization for attribute manipulation.*
