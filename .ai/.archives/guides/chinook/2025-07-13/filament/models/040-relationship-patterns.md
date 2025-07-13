# Relationship Patterns Guide

## Table of Contents

- [Overview](#overview)
- [Basic Relationship Types](#basic-relationship-types)
- [Advanced Relationship Patterns](#advanced-relationship-patterns)
- [Polymorphic Relationships](#polymorphic-relationships)
- [Many-to-Many Relationships](#many-to-many-relationships)
- [Hierarchical Relationships](#hierarchical-relationships)
- [Performance Optimization](#performance-optimization)
- [Relationship Constraints](#relationship-constraints)
- [Testing Relationships](#testing-relationships)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive relationship patterns for Laravel 12 models in the Chinook application. Proper relationship design is crucial for data integrity, query performance, and maintainable code architecture.

**🚀 Key Features:**
- **Modern Laravel 12 Patterns**: Latest relationship syntax and features
- **Performance Optimized**: Efficient querying and eager loading strategies
- **Type Safety**: Proper return type declarations and IDE support
- **Polymorphic Design**: Flexible relationship patterns for complex data structures
- **WCAG 2.1 AA Compliance**: Accessible relationship data presentation

## Basic Relationship Types

### One-to-One Relationships

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Artist extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug;

    /**
     * Get the artist's profile information
     */
    public function profile(): HasOne
    {
        return $this->hasOne(ArtistProfile::class);
    }

    /**
     * Get the artist's primary contact
     */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)
            ->where('is_primary', true)
            ->latest();
    }
}

// app/Models/ArtistProfile.php
class ArtistProfile extends Model
{
    use HasFactory, HasUserStamps;

    protected function cast(): array
    {
        return [
            'bio' => 'string',
            'website' => 'string',
            'social_links' => 'array',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * Get the artist that owns this profile
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
```

### One-to-Many Relationships

```php
<?php
// app/Models/Album.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Album extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug;

    /**
     * Get all tracks for this album
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)
            ->orderBy('track_number');
    }

    /**
     * Get published tracks only
     */
    public function publishedTracks(): HasMany
    {
        return $this->hasMany(Track::class)
            ->where('is_published', true)
            ->orderBy('track_number');
    }

    /**
     * Get the artist that owns this album
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Get the genre for this album
     */
    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }
}

// app/Models/Track.php
class Track extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug;

    /**
     * Get the album that owns this track
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * Get the media type for this track
     */
    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }
}
```

## Advanced Relationship Patterns

### Has-Many-Through Relationships

```php
<?php
// app/Models/Artist.php

class Artist extends Model
{
    /**
     * Get all tracks through albums
     */
    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(
            Track::class,
            Album::class,
            'artist_id', // Foreign key on albums table
            'album_id',  // Foreign key on tracks table
            'id',        // Local key on artists table
            'id'         // Local key on albums table
        );
    }

    /**
     * Get all customers who purchased this artist's tracks
     */
    public function customers(): HasManyThrough
    {
        return $this->hasManyThrough(
            Customer::class,
            InvoiceLine::class,
            'track_id',
            'customer_id',
            'id',
            'invoice_id'
        )->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
         ->join('tracks', 'invoice_lines.track_id', '=', 'tracks.id')
         ->join('albums', 'tracks.album_id', '=', 'albums.id')
         ->where('albums.artist_id', $this->id);
    }
}
```

### Conditional Relationships

```php
<?php
// app/Models/Customer.php

class Customer extends Model
{
    /**
     * Get recent invoices (last 30 days)
     */
    public function recentInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->where('invoice_date', '>=', now()->subDays(30))
            ->orderBy('invoice_date', 'desc');
    }

    /**
     * Get unpaid invoices
     */
    public function unpaidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->whereNull('paid_at')
            ->where('due_date', '>=', now());
    }

    /**
     * Get overdue invoices
     */
    public function overdueInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->whereNull('paid_at')
            ->where('due_date', '<', now());
    }
}
```

## Polymorphic Relationships

### Basic Polymorphic Relationships

```php
<?php
// app/Models/Comment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    use HasFactory, HasUserStamps;

    protected function cast(): array
    {
        return [
            'content' => 'string',
            'is_approved' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the commentable model (Artist, Album, Track, etc.)
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

// Add to Artist, Album, Track models
trait HasComments
{
    /**
     * Get all comments for this model
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get approved comments only
     */
    public function approvedComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc');
    }
}
```

### Many-to-Many Polymorphic Relationships

```php
<?php
// app/Models/Tag.php

class Tag extends Model
{
    use HasFactory, HasSlug;

    /**
     * Get all taggable models
     */
    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class);
    }

    /**
     * Get all artists with this tag
     */
    public function artists(): MorphToMany
    {
        return $this->morphedByMany(Artist::class, 'taggable');
    }

    /**
     * Get all albums with this tag
     */
    public function albums(): MorphToMany
    {
        return $this->morphedByMany(Album::class, 'taggable');
    }

    /**
     * Get all tracks with this tag
     */
    public function tracks(): MorphToMany
    {
        return $this->morphedByMany(Track::class, 'taggable');
    }
}

// HasTags trait (used by Artist, Album, Track)
trait HasTags
{
    /**
     * Get all tags for this model
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withTimestamps();
    }

    /**
     * Sync tags for this model
     */
    public function syncTags(array $tags): void
    {
        $tagIds = collect($tags)->map(function ($tag) {
            return is_string($tag) 
                ? Tag::firstOrCreate(['name' => $tag])->id 
                : $tag;
        });

        $this->tags()->sync($tagIds);
    }
}
```

## Many-to-Many Relationships

### Pivot Table Relationships

```php
<?php
// app/Models/Playlist.php

class Playlist extends Model
{
    use HasFactory, SoftDeletes, HasUserStamps;

    /**
     * Get all tracks in this playlist
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'playlist_tracks')
            ->withPivot(['position', 'added_at', 'added_by'])
            ->withTimestamps()
            ->orderBy('playlist_tracks.position');
    }

    /**
     * Add track to playlist
     */
    public function addTrack(Track $track, int $position = null): void
    {
        $position = $position ?? ($this->tracks()->max('position') + 1);
        
        $this->tracks()->attach($track->id, [
            'position' => $position,
            'added_at' => now(),
            'added_by' => auth()->id(),
        ]);
    }

    /**
     * Reorder tracks in playlist
     */
    public function reorderTracks(array $trackOrder): void
    {
        foreach ($trackOrder as $position => $trackId) {
            $this->tracks()->updateExistingPivot($trackId, [
                'position' => $position + 1
            ]);
        }
    }
}

// app/Models/Track.php
class Track extends Model
{
    /**
     * Get all playlists containing this track
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_tracks')
            ->withPivot(['position', 'added_at', 'added_by'])
            ->withTimestamps();
    }
}
```

## Hierarchical Relationships

### Self-Referencing Relationships

```php
<?php
// app/Models/Category.php

class Category extends Model
{
    use HasFactory, SoftDeletes, HasSlug, HasUserStamps;

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get all child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order');
    }

    /**
     * Get all descendants recursively
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the root category
     */
    public function root(): Category
    {
        return $this->ancestors()->first() ?? $this;
    }

    /**
     * Check if this category is a descendant of another
     */
    public function isDescendantOf(Category $category): bool
    {
        return $this->ancestors()->contains('id', $category->id);
    }
}
```

## Performance Optimization

### Eager Loading Strategies

```php
<?php
// Efficient relationship loading

class ArtistController extends Controller
{
    public function index()
    {
        // Eager load related data to prevent N+1 queries
        $artists = Artist::with([
            'albums' => function ($query) {
                $query->select(['id', 'artist_id', 'title', 'release_date'])
                      ->orderBy('release_date', 'desc');
            },
            'albums.tracks' => function ($query) {
                $query->select(['id', 'album_id', 'name', 'duration_ms']);
            },
            'profile',
            'tags'
        ])->paginate(20);

        return view('artists.index', compact('artists'));
    }

    public function show(Artist $artist)
    {
        // Load specific relationships for detail view
        $artist->load([
            'albums.tracks.mediaType',
            'profile',
            'tags',
            'comments.user'
        ]);

        return view('artists.show', compact('artist'));
    }
}
```

### Relationship Counting

```php
<?php
// Efficient counting without loading relationships

class Album extends Model
{
    /**
     * Get albums with track counts
     */
    public static function withTrackCounts()
    {
        return static::withCount([
            'tracks',
            'tracks as published_tracks_count' => function ($query) {
                $query->where('is_published', true);
            }
        ]);
    }

    /**
     * Scope for popular albums
     */
    public function scopePopular($query, int $minTracks = 5)
    {
        return $query->withCount('tracks')
            ->having('tracks_count', '>=', $minTracks);
    }
}
```

## Relationship Constraints

### Database-Level Constraints

```php
<?php
// Migration with proper foreign key constraints

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('album_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('media_type_id')
                  ->constrained()
                  ->onDelete('restrict');
            $table->integer('track_number');
            $table->integer('duration_ms');
            $table->decimal('price', 8, 2);
            $table->timestamps();

            // Ensure unique track numbers per album
            $table->unique(['album_id', 'track_number']);
        });
    }
};
```

## Testing Relationships

### Comprehensive Relationship Testing

```php
<?php
// tests/Unit/Models/ArtistTest.php

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use Tests\TestCase;

class ArtistTest extends TestCase
{
    public function test_artist_has_many_albums(): void
    {
        $artist = Artist::factory()->create();
        $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);

        expect($artist->albums)->toHaveCount(3);
        expect($artist->albums->first())->toBeInstanceOf(Album::class);
    }

    public function test_artist_has_many_tracks_through_albums(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $tracks = Track::factory()->count(5)->create(['album_id' => $album->id]);

        expect($artist->tracks)->toHaveCount(5);
        expect($artist->tracks->first())->toBeInstanceOf(Track::class);
    }

    public function test_artist_can_have_tags(): void
    {
        $artist = Artist::factory()->create();
        $artist->syncTags(['rock', 'alternative']);

        expect($artist->tags)->toHaveCount(2);
        expect($artist->tags->pluck('name'))->toContain('rock', 'alternative');
    }
}
```

## Best Practices

### Relationship Guidelines

1. **Use Type Hints**: Always specify return types for relationship methods
2. **Eager Loading**: Use `with()` to prevent N+1 query problems
3. **Naming Conventions**: Use descriptive relationship method names
4. **Constraints**: Implement proper foreign key constraints
5. **Performance**: Consider relationship counting vs. loading
6. **Testing**: Write comprehensive tests for all relationships

### Security Considerations

```php
<?php
// Secure relationship access

class Album extends Model
{
    /**
     * Get tracks that the current user can access
     */
    public function accessibleTracks(): HasMany
    {
        return $this->hasMany(Track::class)
            ->where(function ($query) {
                if (auth()->guest()) {
                    $query->where('is_public', true);
                } elseif (!auth()->user()->hasRole('admin')) {
                    $query->where(function ($q) {
                        $q->where('is_public', true)
                          ->orWhere('created_by', auth()->id());
                    });
                }
            });
    }
}
```

## Navigation

**← Previous:** [Casting Patterns Guide](030-casting-patterns.md)
**Next →** [Validation Rules Guide](040-validation-rules.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Hierarchical Models Guide](050-hierarchical-models.md) - Tree structure patterns
- [Polymorphic Models Guide](060-polymorphic-models.md) - Advanced polymorphic patterns

---

*This guide provides comprehensive relationship patterns for Laravel 12 models in the Chinook application. Each pattern includes performance considerations, security aspects, and testing strategies to ensure robust data relationships throughout the application.*
