# 1. Chinook Database Relationship Mapping Guide

**Refactored from:** `.ai/guides/chinook/090-relationship-mapping.md` on 2025-07-11

## 1.1 Table of Contents

- [1.2 Overview](#12-overview)
- [1.3 Core Entity Relationships](#13-core-entity-relationships)
- [1.4 Taxonomy System Relationships](#14-taxonomy-system-relationships)
- [1.5 Hierarchical Relationships](#15-hierarchical-relationships)
- [1.6 RBAC Relationships](#16-rbac-relationships)
- [1.7 Implementation Examples](#17-implementation-examples)
- [1.8 Performance Considerations](#18-performance-considerations)
- [1.9 Testing Relationships](#19-testing-relationships)

## 1.2 Overview

This guide provides comprehensive mapping of all relationships in the Chinook database implementation, including traditional foreign key relationships, polymorphic associations using the aliziodev/laravel-taxonomy package, and hierarchical structures using Laravel 12 modern patterns.

### 1.2.1 Relationship Types

**Core Relationship Types:**

- **One-to-Many**: Artist → Albums, Album → Tracks
- **Many-to-Many**: Playlists ↔ Tracks, Users ↔ Roles
- **Polymorphic**: Taxonomies ↔ All Models (via aliziodev/laravel-taxonomy)
- **Hierarchical**: Employee → Manager
- **Self-Referencing**: Employee hierarchy

### 1.2.2 Modern Laravel 12 Features

- **Attribute Casting**: Using `casts()` method for type safety
- **Relationship Caching**: Optimized query performance
- **Eager Loading**: Preventing N+1 query problems
- **Relationship Constraints**: Database-level integrity
- **Taxonomy Integration**: Single system via aliziodev/laravel-taxonomy

## 1.3 Core Entity Relationships

### 1.3.1 Music Catalog Relationships

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

// ChinookArtist Model
class ChinookArtist extends Model
{
    use HasTaxonomies;

    protected $table = 'chinook_artists';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function albums(): HasMany
    {
        return $this->hasMany(ChinookAlbum::class, 'artist_id');
    }
    
    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(ChinookTrack::class, ChinookAlbum::class, 'artist_id', 'album_id');
    }
}

// ChinookAlbum Model  
class ChinookAlbum extends Model
{
    use HasTaxonomies;

    protected $table = 'chinook_albums';

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(ChinookArtist::class, 'artist_id');
    }
    
    public function tracks(): HasMany
    {
        return $this->hasMany(ChinookTrack::class, 'album_id');
    }
}

// ChinookTrack Model
class ChinookTrack extends Model
{
    use HasTaxonomies;

    protected $table = 'chinook_tracks';

    protected function casts(): array
    {
        return [
            'milliseconds' => 'integer',
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(ChinookAlbum::class, 'album_id');
    }
    
    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(ChinookMediaType::class, 'media_type_id');
    }
    
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(ChinookPlaylist::class, 'chinook_playlist_tracks', 'track_id', 'playlist_id')
                    ->withPivot(['created_at', 'updated_at']);
    }
}
```

### 1.3.2 Customer & Sales Relationships

```php
// ChinookCustomer Model
class ChinookCustomer extends Model
{
    use HasTaxonomies;

    protected $table = 'chinook_customers';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ChinookInvoice::class, 'customer_id');
    }
    
    public function supportEmployee(): BelongsTo
    {
        return $this->belongsTo(ChinookEmployee::class, 'support_rep_id');
    }
}

// ChinookInvoice Model
class ChinookInvoice extends Model
{
    protected $table = 'chinook_invoices';

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ChinookCustomer::class, 'customer_id');
    }
    
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(ChinookInvoiceLine::class, 'invoice_id');
    }
    
    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(ChinookTrack::class, ChinookInvoiceLine::class, 'invoice_id', 'id', 'id', 'track_id');
    }
}
```

## 1.4 Taxonomy System Relationships

### 1.4.1 aliziodev/laravel-taxonomy Integration

The Chinook system uses the aliziodev/laravel-taxonomy package exclusively for all categorization needs, eliminating the need for custom Category models.

```php
// All Chinook models use HasTaxonomies trait
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookArtist extends Model
{
    use HasTaxonomies;

    // Taxonomy relationships are automatically available:
    // $artist->taxonomies() - Get all taxonomies
    // $artist->terms() - Get all terms
    // $artist->attachTerm($term) - Attach a term
    // $artist->detachTerm($term) - Detach a term
}
```

### 1.4.2 Genre Preservation Strategy

```php
// Genre mapping using taxonomy system
class GenreService
{
    public function mapGenreToTaxonomy(string $genreName): void
    {
        $genreTaxonomy = Taxonomy::firstOrCreate([
            'name' => 'Genres',
            'slug' => 'genres'
        ]);

        $term = Term::firstOrCreate([
            'taxonomy_id' => $genreTaxonomy->id,
            'name' => $genreName,
            'slug' => Str::slug($genreName)
        ]);

        // Bridge layer for existing genre data
        $this->migrateExistingGenreData($term);
    }

    private function migrateExistingGenreData(Term $term): void
    {
        // Migration logic for preserving existing genre relationships
        // while transitioning to single taxonomy system
    }
}
```

### 1.4.3 Taxonomy Query Examples

```php
// Get all artists in a specific genre
$rockArtists = ChinookArtist::whereHasTerm('Rock', 'genres')->get();

// Get tracks with multiple taxonomy filters
$tracks = ChinookTrack::whereHasTerms(['Rock', 'Alternative'], 'genres')
    ->whereHasTerm('Studio Album', 'album-types')
    ->get();

// Eager load taxonomy relationships
$albums = ChinookAlbum::with(['terms.taxonomy'])->get();
```

## 1.5 Hierarchical Relationships

### 1.5.1 Employee Hierarchy

```php
// ChinookEmployee Model
class ChinookEmployee extends Model
{
    protected $table = 'chinook_employees';

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(ChinookEmployee::class, 'reports_to');
    }
    
    public function subordinates(): HasMany
    {
        return $this->hasMany(ChinookEmployee::class, 'reports_to');
    }
    
    public function customers(): HasMany
    {
        return $this->hasMany(ChinookCustomer::class, 'support_rep_id');
    }
}
```

## 1.6 RBAC Relationships

### 1.6.1 User-Role-Permission System (Spatie Permission)

```php
use Spatie\Permission\Traits\HasRoles;

// User Model
class User extends Model
{
    use HasRoles;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Spatie Permission provides:
    // $user->roles() - Get user roles
    // $user->permissions() - Get user permissions
    // $user->hasRole('admin') - Check role
    // $user->can('edit posts') - Check permission
}

// Role Model (provided by Spatie Permission)
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Usage examples:
// Role::create(['name' => 'Super Admin']);
// Permission::create(['name' => 'manage chinook artists']);
// $user->assignRole('Super Admin');
```

## 1.7 Implementation Examples

### 1.7.1 Eager Loading Strategies

```php
// Optimized queries with taxonomy relationships
$artists = ChinookArtist::with([
    'albums.tracks.mediaType',
    'terms.taxonomy' => function ($query) {
        $query->where('name', 'Genres');
    }
])->get();

// Conditional eager loading with taxonomy filters
$tracks = ChinookTrack::with([
    'album.artist',
    'terms' => function ($query) use ($taxonomyName) {
        if ($taxonomyName) {
            $query->whereHas('taxonomy', function ($q) use ($taxonomyName) {
                $q->where('name', $taxonomyName);
            });
        }
    }
])->paginate(50);
```

### 1.7.2 Relationship Constraints

```php
// Database constraints
Schema::table('chinook_albums', function (Blueprint $table) {
    $table->foreign('artist_id')->references('id')->on('chinook_artists')
          ->onDelete('cascade');
});

// Model constraints with taxonomy cleanup
class ChinookAlbum extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($album) {
            // Clean up tracks and taxonomy relationships
            $album->tracks()->delete();
            $album->detachAllTerms();
        });
    }
}
```

## 1.8 Performance Considerations

### 1.8.1 Query Optimization

```php
// Use select to limit columns
$artists = ChinookArtist::select(['id', 'name', 'public_id'])
    ->with(['albums:id,artist_id,title'])
    ->get();

// Use exists for conditional queries with taxonomy
$artistsWithGenres = ChinookArtist::whereHas('terms', function ($query) {
    $query->whereHas('taxonomy', function ($q) {
        $q->where('name', 'Genres');
    });
})->get();

// Use counts for statistics
$artists = ChinookArtist::withCount(['albums', 'tracks', 'terms'])->get();
```

### 1.8.2 Caching Strategies

```php
// Relationship caching with taxonomy
class ChinookArtist extends Model
{
    public function getPopularAlbumsAttribute()
    {
        return Cache::remember(
            "artist.{$this->id}.popular_albums",
            3600,
            fn() => $this->albums()->orderBy('sales_count', 'desc')->limit(5)->get()
        );
    }

    public function getGenresAttribute()
    {
        return Cache::remember(
            "artist.{$this->id}.genres",
            1800,
            fn() => $this->getTermsByTaxonomy('Genres')
        );
    }
}
```

## 1.9 Testing Relationships

### 1.9.1 Relationship Tests

```php
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Term;

// Test relationship existence
test('artist has many albums', function () {
    $artist = ChinookArtist::factory()->create();
    $albums = ChinookAlbum::factory()->count(3)->create(['artist_id' => $artist->id]);

    expect($artist->albums)->toHaveCount(3);
    expect($artist->albums->first())->toBeInstanceOf(ChinookAlbum::class);
});

// Test taxonomy relationships
test('track can have taxonomy terms', function () {
    $track = ChinookTrack::factory()->create();

    $genreTaxonomy = Taxonomy::factory()->create(['name' => 'Genres']);
    $rockTerm = Term::factory()->create([
        'taxonomy_id' => $genreTaxonomy->id,
        'name' => 'Rock'
    ]);

    $track->attachTerm($rockTerm);

    expect($track->terms)->toHaveCount(1);
    expect($track->terms->first()->name)->toBe('Rock');
});
```

### 1.9.2 Performance Testing

```php
// Test N+1 query prevention with taxonomy
test('eager loading prevents n+1 queries with taxonomy', function () {
    ChinookArtist::factory()->count(10)->create();
    ChinookAlbum::factory()->count(30)->create();

    DB::enableQueryLog();

    $artists = ChinookArtist::with(['albums', 'terms.taxonomy'])->get();
    $artists->each(function ($artist) {
        $artist->albums->count();
        $artist->terms->count();
    });

    $queryCount = count(DB::getQueryLog());
    expect($queryCount)->toBeLessThanOrEqual(4); // artists, albums, terms, taxonomies
});
```

### 1.9.3 Taxonomy Integration Tests

```php
test('genre preservation strategy works correctly', function () {
    $artist = ChinookArtist::factory()->create();

    // Create genre taxonomy and term
    $genreTaxonomy = Taxonomy::create(['name' => 'Genres', 'slug' => 'genres']);
    $rockTerm = Term::create([
        'taxonomy_id' => $genreTaxonomy->id,
        'name' => 'Rock',
        'slug' => 'rock'
    ]);

    // Attach genre to artist
    $artist->attachTerm($rockTerm);

    // Verify relationship
    expect($artist->getTermsByTaxonomy('Genres'))->toHaveCount(1);
    expect($artist->getTermsByTaxonomy('Genres')->first()->name)->toBe('Rock');
});
```

---

**Next**: [Resource Testing Guide](100-resource-testing.md) | **Previous**: [Visual Documentation Guide](080-visual-documentation-guide.md)

---

*This guide demonstrates modern Laravel 12 relationship patterns with exclusive use of the aliziodev/laravel-taxonomy package for all categorization needs.*

[⬆️ Back to Top](#1-chinook-database-relationship-mapping-guide)
