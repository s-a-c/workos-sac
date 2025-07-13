# 1. Chinook Database Models Guide

## 1.1. Overview

This guide provides comprehensive instructions for creating modern Laravel 12 Eloquent models for the Chinook database
schema using a **single taxonomy system** approach. The Chinook database represents a digital music store with artists, albums, tracks, customers, employees, and
sales data, enhanced with the aliziodev/laravel-taxonomy package for unified categorization.

All models include modern Laravel 12 features:

- **Modern Casting**: Using `casts()` method instead of `$casts` property
- **Single Taxonomy System**: aliziodev/laravel-taxonomy for unified categorization
- **Timestamps**: `created_at` and `updated_at` columns
- **Soft Deletes**: Safe deletion with `deleted_at` column
- **User Stamps**: Track who created/updated records
- **Tags**: Spatie tags for additional metadata
- **Secondary Unique Keys**: Public-facing identifiers using `public_id`
- **Slugs**: URL-friendly identifiers generated from `public_id`

## 1.2. Database Schema Overview

The Chinook database consists of interconnected tables with modern Laravel 12 enhancements and single taxonomy system:

- **Core Music Data**: `artists`, `albums`, `tracks`, `media_types`
- **Single Taxonomy System**: aliziodev/laravel-taxonomy package tables (`taxonomies`, `taxonomables`)
- **Genre Compatibility**: `chinook_genres` (preserved for data export/import compatibility)
- **Customer Management**: `customers`, `employees`
- **Sales System**: `invoices`, `invoice_lines`
- **Playlist System**: `playlists`, `playlist_track`

## 1.3. Required Packages

Ensure these packages are installed for full functionality:

```bash
# Single taxonomy system (CRITICAL for categorization)
composer require aliziodev/laravel-taxonomy

# Core Laravel features
composer require spatie/laravel-sluggable
composer require glhd/bits

# User stamps (track who created/updated)
composer require wildside/userstamps

# Role-based access control (CRITICAL for enterprise features)
composer require spatie/laravel-permission

# Activity logging (optional but recommended)
composer require spatie/laravel-activitylog
```

## 1.4. Package Installation and Configuration

### 1.4.1. Spatie Laravel Permission Setup

```bash
# Install the package
composer require spatie/laravel-permission

# Publish the migration and config file
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Run the migrations (creates roles, permissions, and pivot tables)
php artisan migrate

# Clear cache after installation
php artisan permission:cache-reset
```

### 1.4.2. Configuration File Customization

Update `config/permission.php` for Chinook-specific settings:

```php
<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    // Enable teams for multi-tenant Chinook instances
    'teams' => false,

    // Use cache for better performance
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
```

### 1.4.3. User Model Configuration

Update your `User` model to include permission traits:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasRoles;
    use HasPermissions;

    // Your existing User model code...
}

```

## 1.5. Single Taxonomy System Integration

### 1.5.1. Taxonomy Package Setup

Install and configure the aliziodev/laravel-taxonomy package:

```bash
# Install the taxonomy package
composer require aliziodev/laravel-taxonomy

# Publish configuration and migrations (preferred method)
php artisan taxonomy:install

# Alternative: Manual publishing
# php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider" --tag="taxonomy-config"
# php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider" --tag="taxonomy-migrations"

# Run migrations
php artisan migrate
```

**Source:** [aliziodev/laravel-taxonomy GitHub Repository](https://github.com/aliziodev/laravel-taxonomy) - Installation Documentation

### 1.5.2. Taxonomy Types Configuration

The single taxonomy system uses the `aliziodev/laravel-taxonomy` package configuration for taxonomy types. All categorization is handled through the unified taxonomy system.

#### 1.5.2.1. Supported Taxonomy Types

The Chinook implementation supports the following taxonomy types through the single taxonomy system:

- **Genre**: Musical genres (Rock, Pop, Jazz, etc.)
- **Mood**: Emotional categorization (Energetic, Calm, Upbeat, etc.)
- **Theme**: Lyrical themes (Love, Adventure, Social, etc.)
- **Era**: Time periods (1960s, 1970s, Modern, etc.)
- **Instrument**: Primary instruments (Guitar, Piano, Drums, etc.)
- **Language**: Track languages (English, Spanish, French, etc.)
- **Occasion**: Listening contexts (Workout, Study, Party, etc.)
- **Tempo**: Speed classifications (Fast, Medium, Slow, etc.)

#### 1.5.2.2. Taxonomy Configuration

```php
<?php
// Configuration is handled by aliziodev/laravel-taxonomy package
// No custom enums needed - taxonomy types are stored in the database

// Example taxonomy creation
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

$genreTaxonomy = Taxonomy::create([
    'name' => 'Rock',
    'type' => 'genre',
    'description' => 'Rock music genre',
    'is_active' => true,
]);

$moodTaxonomy = Taxonomy::create([
    'name' => 'Energetic',
    'type' => 'mood',
    'description' => 'High energy music',
    'is_active' => true,
]);
```

## 1.6. Model Creation Commands

### 1.6.1. Generate All Models

```bash
# Core music models (using single taxonomy system)
php artisan make:model ChinookArtist
php artisan make:model ChinookAlbum
php artisan make:model ChinookTrack
php artisan make:model ChinookGenre     # PRESERVED: For compatibility and data export
php artisan make:model ChinookMediaType

# Customer and employee models
php artisan make:model ChinookCustomer
php artisan make:model ChinookEmployee

# Sales models
php artisan make:model ChinookInvoice
php artisan make:model ChinookInvoiceLine

# Playlist models
php artisan make:model ChinookPlaylist
```

## 1.7. Model Implementations

### 1.7.1. Single Taxonomy System (Using aliziodev/laravel-taxonomy Package)

The Chinook implementation uses **exclusively** the `aliziodev/laravel-taxonomy` package for all categorization needs. This provides a unified, standardized approach to taxonomy management across all models.

#### 1.7.1.1. Taxonomy Integration

All Chinook models that require categorization use the `HasTaxonomies` trait:

```php
<?php
// Example: ChinookTrack with taxonomy support

namespace App\Models;

use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class ChinookTrack extends Model
{
    use HasTaxonomies;  // Single taxonomy system only

    /**
     * Configure supported taxonomy types for this model
     */
    public function getTaxonomyTypes(): array
    {
        return ['genre', 'mood', 'theme', 'instrument', 'era', 'language', 'occasion', 'tempo'];
    }
}

```

#### 1.7.1.2. Taxonomy Usage Examples

**Basic Taxonomy Operations:**

```php
// Attach taxonomies to a track
$track = ChinookTrack::find(1);

// Attach genre taxonomy
$genreTaxonomy = Taxonomy::where('name', 'Rock')->where('type', 'genre')->first();
$track->attachTaxonomy($genreTaxonomy->id);

// Attach multiple taxonomies
$track->attachTaxonomies([
    $genreTaxonomy->id,
    $moodTaxonomy->id,
    $eraTaxonomy->id
]);

// Get taxonomies by type
$genres = $track->taxonomies()->where('type', 'genre')->get();
$moods = $track->taxonomies()->where('type', 'mood')->get();

// Query tracks by taxonomy
$rockTracks = ChinookTrack::whereHasTaxonomies(['Rock'])->get();
```

#### 1.7.1.3. Taxonomy Benefits

**Single System Advantages:**
- **Unified API**: Consistent methods across all models
- **Package Maintenance**: Well-maintained `aliziodev/laravel-taxonomy` package
- **Performance**: Optimized queries without dual system overhead
- **Simplicity**: One categorization approach to learn and maintain
- **Standardization**: Industry-standard taxonomy patterns

**Supported Taxonomy Types:**
- **Genre**: Musical genres (Rock, Pop, Jazz, etc.)
- **Mood**: Emotional categorization (Energetic, Calm, Upbeat, etc.)
- **Theme**: Lyrical themes (Love, Adventure, Social, etc.)
- **Era**: Time periods (1960s, 1970s, Modern, etc.)
- **Instrument**: Primary instruments (Guitar, Piano, Drums, etc.)
- **Language**: Track languages (English, Spanish, French, etc.)
- **Occasion**: Listening contexts (Workout, Study, Party, etc.)
- **Tempo**: Speed classifications (Fast, Medium, Slow, etc.)

---

#### 1.7.1.4. Advanced Taxonomy Usage

**Music Discovery System:**

```php
// Find energetic rock tracks for workout playlists
$rockTaxonomy = Taxonomy::where('name', 'Rock')->where('type', 'genre')->first();
$energeticTaxonomy = Taxonomy::where('name', 'Energetic')->where('type', 'mood')->first();

$workoutTracks = ChinookTrack::whereHasTaxonomies([$rockTaxonomy->id, $energeticTaxonomy->id])->get();

// Find jazz albums from the 1960s
$jazzTaxonomy = Taxonomy::where('name', 'Jazz')->where('type', 'genre')->first();
$sixtiesTaxonomy = Taxonomy::where('name', '1960s')->where('type', 'era')->first();

$vintageJazz = ChinookAlbum::whereHasTaxonomies([$jazzTaxonomy->id, $sixtiesTaxonomy->id])->get();
```

**Taxonomy Management Dashboard:**

```php
// Get comprehensive taxonomy breakdown for an artist
$artist = ChinookArtist::with('taxonomies')->find(1);

$taxonomyBreakdown = [
    'genres' => $artist->taxonomies()->where('type', 'genre')->pluck('name')->toArray(),
    'moods' => $artist->taxonomies()->where('type', 'mood')->pluck('name')->toArray(),
    'instruments' => $artist->taxonomies()->where('type', 'instrument')->pluck('name')->toArray(),
    'eras' => $artist->taxonomies()->where('type', 'era')->pluck('name')->toArray(),
];

// Bulk taxonomy assignment for new releases
$newAlbum = ChinookAlbum::create([...]);

$taxonomies = [
    Taxonomy::where('name', 'Alternative Rock')->where('type', 'genre')->first(),
    Taxonomy::where('name', 'Melancholic')->where('type', 'mood')->first(),
    Taxonomy::where('name', '2020s')->where('type', 'era')->first(),
];

$newAlbum->attachTaxonomies($taxonomies->pluck('id')->toArray());
```

**Advanced Filtering and Search:**

```php
// Complex taxonomy-based search
$searchResults = ChinookTrack::query()
    ->when($genreFilter, function ($q) use ($genreFilter) {
        return $q->whereHasTaxonomies([$genreFilter]);
    })
    ->when($moodFilters, function ($q) use ($moodFilters) {
        return $q->whereHas('taxonomies', function ($subQ) use ($moodFilters) {
            $subQ->whereIn('taxonomies.id', $moodFilters)
                 ->where('taxonomies.type', 'mood');
        });
    })
    ->when($excludeExplicit, function ($q) {
        return $q->where('is_explicit', false);
    })
    ->with(['taxonomies', 'album.artist'])
    ->paginate(20);

// Taxonomy-based recommendations
$userPreferences = $user->favoriteTaxonomies; // Custom relationship
$recommendedTracks = ChinookTrack::whereHas('taxonomies', function ($q) use ($userPreferences) {
    $q->whereIn('taxonomies.id', $userPreferences->pluck('id'));
})->inRandomOrder()->limit(50)->get();
```

**Taxonomy Analytics:**

```php
// Generate taxonomy usage statistics
$taxonomyStats = Taxonomy::withCount(['taxonomables'])
    ->get()
    ->groupBy('type')
    ->map(function ($taxonomies) {
        return $taxonomies->map(function ($taxonomy) {
            return [
                'name' => $taxonomy->name,
                'total_usage' => $taxonomy->taxonomables_count,
                'type' => $taxonomy->type,
            ];
        })->sortByDesc('total_usage');
    });
```

---

## 1.8. Chinook Model Implementations

### 1.8.1. ChinookArtist Model (Single Taxonomy System)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Wildside\Userstamps\Userstamps;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookArtist extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;
    use HasRoles;
    use HasPermissions;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_artists';

    /**
     * Get the secondary key type for this model.
     * Using ULID for artists - good balance of readability and performance.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'biography',
        'website',
        'social_links',
        'country',
        'formed_year',
        'is_active',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'formed_year' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the albums for the artist.
     */
    public function albums(): HasMany
    {
        return $this->hasMany(ChinookAlbum::class);
    }

    /**
     * Get all tracks for this artist through albums.
     */
    public function tracks(): HasMany
    {
        return $this->hasManyThrough(ChinookTrack::class, ChinookAlbum::class);
    }

    /**
     * Configure supported taxonomy types for this model
     */
    public function getTaxonomyTypes(): array
    {
        return ['genre', 'era', 'instrument', 'mood', 'theme', 'language', 'occasion'];
    }

    /**
     * Get genres (taxonomies of type 'genre').
     */
    public function genres()
    {
        return $this->taxonomies()->where('type', 'genre');
    }

    /**
     * Get eras (taxonomies of type 'era').
     */
    public function eras()
    {
        return $this->taxonomies()->where('type', 'era');
    }

    /**
     * Get instruments (taxonomies of type 'instrument').
     */
    public function instruments()
    {
        return $this->taxonomies()->where('type', 'instrument');
    }

    /**
     * Scope to find artists by genre.
     */
    public function scopeByGenre(Builder $query, string $genreName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($genreName) {
            $q->where('type', 'genre')
              ->where('name', 'like', "%{$genreName}%");
        });
    }

    /**
     * Scope to find artists by era.
     */
    public function scopeByEra(Builder $query, string $eraName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($eraName) {
            $q->where('type', 'era')
              ->where('name', 'like', "%{$eraName}%");
        });
    }

    /**
     * Scope to find published artists (those with albums).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereHas('albums');
    }

    /**
     * Scope to find active artists.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Attach a taxonomy to this artist.
     */
    public function attachTaxonomyByName(string $taxonomyName, string $type): void
    {
        $taxonomy = \Aliziodev\LaravelTaxonomy\Models\Taxonomy::where('name', $taxonomyName)
            ->where('type', $type)
            ->first();

        if ($taxonomy) {
            $this->attachTaxonomy($taxonomy->id);
        }
    }

    /**
     * Sync taxonomies by type.
     */
    public function syncTaxonomiesByType(string $type, array $taxonomyIds): void
    {
        $existingOtherTypes = $this->taxonomies()
            ->where('type', '!=', $type)
            ->pluck('taxonomies.id')
            ->toArray();

        $this->syncTaxonomies(array_merge($existingOtherTypes, $taxonomyIds));
    }

    /**
     * Get taxonomies grouped by type.
     */
    public function getTaxonomiesByType(): array
    {
        return $this->taxonomies()
            ->get()
            ->groupBy('type')
            ->map(function ($taxonomies) {
                return $taxonomies->pluck('name', 'id');
            })
            ->toArray();
    }

    /**
     * Get the artist's display name with album count.
     */
    public function getDisplayNameAttribute(): string
    {
        $albumCount = $this->albums()->count();
        return "{$this->name} ({$albumCount} albums)";
    }

    /**
     * Get the primary genre for this artist.
     */
    public function getPrimaryGenreAttribute()
    {
        return $this->genres()->first();
    }

    /**
     * Get the artist's years active.
     */
    public function getYearsActiveAttribute(): string
    {
        if (!$this->formed_year) {
            return 'Unknown';
        }

        $endYear = $this->is_active ? 'Present' : 'Unknown';
        return "{$this->formed_year} - {$endYear}";
    }
}

```

### 1.8.2. ChinookAlbum Model (Single Taxonomy System)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Wildside\Userstamps\Userstamps;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookAlbum extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;
    use HasRoles;
    use HasPermissions;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_albums';

    /**
     * Get the secondary key type for this model.
     * Using ULID for albums - good for chronological ordering and URL-friendly.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'artist_id',
        'release_date',
        'label',
        'catalog_number',
        'description',
        'cover_image_url',
        'total_tracks',
        'total_duration_ms',
        'is_compilation',
        'is_explicit',
        'is_active',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'total_tracks' => 'integer',
            'total_duration_ms' => 'integer',
            'is_compilation' => 'boolean',
            'is_explicit' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the artist that owns the album.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ChinookArtist::class);
    }

    /**
     * Get the tracks for the album.
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(ChinookTrack::class);
    }

    /**
     * Configure supported taxonomy types for this model
     */
    public function getTaxonomyTypes(): array
    {
        return ['genre', 'mood', 'theme', 'era', 'language', 'occasion'];
    }

    /**
     * Get genres (taxonomies of type 'genre').
     */
    public function genres()
    {
        return $this->taxonomies()->where('type', 'genre');
    }

    /**
     * Get moods (taxonomies of type 'mood').
     */
    public function moods()
    {
        return $this->taxonomies()->where('type', 'mood');
    }

    /**
     * Get themes (taxonomies of type 'theme').
     */
    public function themes()
    {
        return $this->taxonomies()->where('type', 'theme');
    }

    /**
     * Get eras (taxonomies of type 'era').
     */
    public function eras()
    {
        return $this->taxonomies()->where('type', 'era');
    }

    /**
     * Get languages (taxonomies of type 'language').
     */
    public function languages()
    {
        return $this->taxonomies()->where('type', 'language');
    }

    /**
     * Scope to find albums by genre.
     */
    public function scopeByGenre(Builder $query, string $genreName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($genreName) {
            $q->where('type', 'genre')
              ->where('name', 'like', "%{$genreName}%");
        });
    }

    /**
     * Scope to find albums by mood.
     */
    public function scopeByMood(Builder $query, string $moodName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($moodName) {
            $q->where('type', 'mood')
              ->where('name', 'like', "%{$moodName}%");
        });
    }

    /**
     * Scope to find albums by era.
     */
    public function scopeByEra(Builder $query, string $eraName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($eraName) {
            $q->where('type', 'era')
              ->where('name', 'like', "%{$eraName}%");
        });
    }

    /**
     * Scope to find albums with tracks.
     */
    public function scopeWithTracks(Builder $query): Builder
    {
        return $query->whereHas('tracks');
    }

    /**
     * Scope to find active albums.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Sync taxonomies by type.
     */
    public function syncTaxonomiesByType(string $type, array $taxonomyIds): void
    {
        $existingOtherTypes = $this->taxonomies()
            ->where('type', '!=', $type)
            ->pluck('taxonomies.id')
            ->toArray();

        $this->syncTaxonomies(array_merge($existingOtherTypes, $taxonomyIds));
    }

    /**
     * Get the album's full title with artist name.
     */
    public function getFullTitleAttribute(): string
    {
        return "{$this->artist->name} - {$this->title}";
    }

    /**
     * Get the total duration of all tracks in the album.
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->tracks()->sum('milliseconds');
    }

    /**
     * Get the primary genre for this album.
     */
    public function getPrimaryGenreAttribute()
    {
        return $this->genres()->first();
    }

    /**
     * Get the formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalSeconds = intval($this->total_duration / 1000);
        $hours = intval($totalSeconds / 3600);
        $minutes = intval(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}

```

### 1.7.5. Track Model (Updated with Polymorphic Categories and RBAC)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Wildside\Userstamps\Userstamps;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookTrack extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;
    use HasRoles;
    use HasPermissions;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_tracks';

    /**
     * Get the secondary key type for this model.
     * Using Snowflake for tracks - high performance for large datasets.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::SNOWFLAKE;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'album_id',
        'media_type_id',
        'composer',
        'milliseconds',
        'bytes',
        'unit_price',
        'track_number',
        'disc_number',
        'is_explicit',
        'is_active',
        'preview_url',
        'lyrics',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'milliseconds' => 'integer',
            'bytes' => 'integer',
            'track_number' => 'integer',
            'disc_number' => 'integer',
            'is_explicit' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the album that owns the track.
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(ChinookAlbum::class);
    }

    /**
     * Get the media type that owns the track.
     */
    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(ChinookMediaType::class);
    }

    /**
     * Get the artist through the album relationship.
     */
    public function artist(): BelongsTo
    {
        return $this->album()->artist();
    }

    /**
     * Get the invoice lines for the track.
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(ChinookInvoiceLine::class);
    }

    /**
     * Get the playlists that contain this track.
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(ChinookPlaylist::class, 'chinook_playlist_track')
            ->withTimestamps()
            ->withPivot(['position', 'added_by']);
    }

    /**
     * Configure supported taxonomy types for this model
     */
    public function getTaxonomyTypes(): array
    {
        return ['genre', 'mood', 'theme', 'instrument', 'language', 'occasion', 'tempo'];
    }

    /**
     * Get genres (taxonomies of type 'genre').
     */
    public function genres()
    {
        return $this->taxonomies()->where('type', 'genre');
    }

    /**
     * Get moods (taxonomies of type 'mood').
     */
    public function moods()
    {
        return $this->taxonomies()->where('type', 'mood');
    }

    /**
     * Get themes (taxonomies of type 'theme').
     */
    public function themes()
    {
        return $this->taxonomies()->where('type', 'theme');
    }

    /**
     * Get instruments (taxonomies of type 'instrument').
     */
    public function instruments()
    {
        return $this->taxonomies()->where('type', 'instrument');
    }

    /**
     * Get languages (taxonomies of type 'language').
     */
    public function languages()
    {
        return $this->taxonomies()->where('type', 'language');
    }

    /**
     * Get occasions (taxonomies of type 'occasion').
     */
    public function occasions()
    {
        return $this->taxonomies()->where('type', 'occasion');
    }

    /**
     * Scope to find tracks by genre.
     */
    public function scopeByGenre(Builder $query, string $genreName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($genreName) {
            $q->where('type', 'genre')
              ->where('name', 'like', "%{$genreName}%");
        });
    }

    /**
     * Scope to find tracks by mood.
     */
    public function scopeByMood(Builder $query, string $moodName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($moodName) {
            $q->where('type', 'mood')
              ->where('name', 'like', "%{$moodName}%");
        });
    }

    /**
     * Scope to find tracks by theme.
     */
    public function scopeByTheme(Builder $query, string $themeName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($themeName) {
            $q->where('type', 'theme')
              ->where('name', 'like', "%{$themeName}%");
        });
    }

    /**
     * Scope to find tracks by instrument.
     */
    public function scopeByInstrument(Builder $query, string $instrumentName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($instrumentName) {
            $q->where('type', 'instrument')
              ->where('name', 'like', "%{$instrumentName}%");
        });
    }

    /**
     * Scope to find tracks by language.
     */
    public function scopeByLanguage(Builder $query, string $languageName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($languageName) {
            $q->where('type', 'language')
              ->where('name', 'like', "%{$languageName}%");
        });
    }

    /**
     * Scope to find tracks by occasion.
     */
    public function scopeByOccasion(Builder $query, string $occasionName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($occasionName) {
            $q->where('type', 'occasion')
              ->where('name', 'like', "%{$occasionName}%");
        });
    }

    /**
     * Scope to find tracks by duration range.
     */
    public function scopeByDuration(Builder $query, int $minMs, int $maxMs): Builder
    {
        return $query->whereBetween('milliseconds', [$minMs, $maxMs]);
    }

    /**
     * Scope to find popular tracks (frequently purchased).
     */
    public function scopePopular(Builder $query, int $minPurchases = 10): Builder
    {
        return $query->whereHas('invoiceLines', function ($q) use ($minPurchases) {
            $q->havingRaw('COUNT(*) >= ?', [$minPurchases]);
        });
    }

    /**
     * Scope to find active tracks.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Sync taxonomies by type.
     */
    public function syncTaxonomiesByType(string $type, array $taxonomyIds): void
    {
        $existingOtherTypes = $this->taxonomies()
            ->where('type', '!=', $type)
            ->pluck('taxonomies.id')
            ->toArray();

        $this->syncTaxonomies(array_merge($existingOtherTypes, $taxonomyIds));
    }

    /**
     * Get the track's duration in human-readable format.
     */
    public function getDurationAttribute(): string
    {
        $seconds = intval($this->milliseconds / 1000);
        $minutes = intval($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Get the track's file size in human-readable format.
     */
    public function getFileSizeAttribute(): string
    {
        if (!$this->bytes) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->bytes;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the primary genre for this track.
     */
    public function getPrimaryGenreAttribute()
    {
        return $this->genres()->first();
    }

    /**
     * Get the track's full name with artist and album.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->artist->name} - {$this->album->title} - {$this->name}";
    }
}

```

### 1.7.6. Playlist Model (Updated with Polymorphic Categories and RBAC)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Wildside\Userstamps\Userstamps;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookPlaylist extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;
    use HasRoles;
    use HasPermissions;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_playlists';

    /**
     * Get the secondary key type for this model.
     * Using ULID for playlists - user-friendly for sharing.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'is_public',
        'is_collaborative',
        'cover_image_url',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_collaborative' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the tracks that belong to this playlist.
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(ChinookTrack::class, 'chinook_playlist_track')
            ->withTimestamps()
            ->withPivot(['position', 'added_by'])
            ->orderBy('pivot_position');
    }

    /**
     * Configure supported taxonomy types for this model
     */
    public function getTaxonomyTypes(): array
    {
        return ['mood', 'theme', 'occasion'];
    }

    /**
     * Get moods (taxonomies of type 'mood').
     */
    public function moods()
    {
        return $this->taxonomies()->where('type', 'mood');
    }

    /**
     * Get themes (taxonomies of type 'theme').
     */
    public function themes()
    {
        return $this->taxonomies()->where('type', 'theme');
    }

    /**
     * Get occasions (taxonomies of type 'occasion').
     */
    public function occasions()
    {
        return $this->taxonomies()->where('type', 'occasion');
    }

    /**
     * Scope to find public playlists.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to find playlists with tracks.
     */
    public function scopeWithTracks(Builder $query): Builder
    {
        return $query->whereHas('tracks');
    }

    /**
     * Scope to find playlists by mood.
     */
    public function scopeByMood(Builder $query, string $moodName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($moodName) {
            $q->where('type', 'mood')
              ->where('name', 'like', "%{$moodName}%");
        });
    }

    /**
     * Scope to find playlists by theme.
     */
    public function scopeByTheme(Builder $query, string $themeName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($themeName) {
            $q->where('type', 'theme')
              ->where('name', 'like', "%{$themeName}%");
        });
    }

    /**
     * Scope to find playlists by occasion.
     */
    public function scopeByOccasion(Builder $query, string $occasionName): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($occasionName) {
            $q->where('type', 'occasion')
              ->where('name', 'like', "%{$occasionName}%");
        });
    }

    /**
     * Add a track to the playlist.
     */
    public function addTrack(Track $track, ?int $position = null, ?int $addedBy = null): void
    {
        $position = $position ?? ($this->tracks()->max('pivot_position') + 1);

        $this->tracks()->attach($track->id, [
            'position' => $position,
            'added_by' => $addedBy ?? auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get the playlist's track count.
     */
    public function getTrackCountAttribute(): int
    {
        return $this->tracks()->count();
    }

    /**
     * Get the playlist's total duration.
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->tracks()->sum('milliseconds');
    }

    /**
     * Get the playlist's formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalSeconds = intval($this->total_duration / 1000);
        $hours = intval($totalSeconds / 3600);
        $minutes = intval(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}

```

### 1.7.7. Customer Model (Updated with Polymorphic Categories and RBAC)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookCustomer extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_customers';

    /**
     * Get the secondary key type for this model.
     * Using ULID for customers - good balance for customer management.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'company',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'fax',
        'email',
        'support_rep_id',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the support representative for the customer.
     */
    public function supportRep(): BelongsTo
    {
        return $this->belongsTo(ChinookEmployee::class, 'support_rep_id');
    }

    /**
     * Get the invoices for the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(ChinookInvoice::class);
    }

    /**
     * Scope to find customers by country.
     */
    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope to find customers with recent purchases.
     */
    public function scopeRecentCustomers($query, int $days = 30)
    {
        return $query->whereHas('invoices', function ($q) use ($days) {
            $q->where('invoice_date', '>=', now()->subDays($days));
        });
    }

    /**
     * Get the customer's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the customer's total spent amount.
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->invoices()->sum('total');
    }

    /**
     * Get the customer's invoice count.
     */
    public function getInvoiceCountAttribute(): int
    {
        return $this->invoices()->count();
    }
}

```

## 1.6. Key Model Features

### 1.6.1. Modern Laravel Features

All Chinook models now include:

- **Timestamps**: Full `created_at` and `updated_at` support
- **Soft Deletes**: Safe deletion with `deleted_at` column
- **User Stamps**: Track who created/updated records with `created_by` and `updated_by`
- **Tags**: Spatie tags for flexible categorization
- **Secondary Unique Keys**: Public-facing identifiers using configurable types
- **Slugs**: URL-friendly identifiers generated from `public_id`

### 1.6.2. Secondary Key Type Strategy

Each model uses an appropriate secondary key type:

- **Artists, Albums, Customers**: ULID (balanced performance and readability)
- **Tracks**: Snowflake (high performance for large datasets)
- **Genres, MediaTypes**: UUID (standards compliance for reference data)
- **Employees, Invoices**: ULID (good for business records)
- **Playlists**: ULID (user-friendly for playlist sharing)

### 1.6.3. Fillable Attributes

Each model defines comprehensive `$fillable` arrays including:

- Original Chinook fields
- Modern Laravel fields (`public_id`, `slug`)
- Audit trail support

### 1.6.4. Type Casting

Models use the `casts()` method for:

- Decimal prices with proper precision
- DateTime fields for timestamps
- Integer values for counts and durations
- Boolean flags where applicable

### 1.6.5. Relationship Methods

All models include comprehensive relationship methods:

- `belongsTo()` for foreign key relationships
- `hasMany()` for one-to-many relationships
- `hasManyThrough()` for indirect relationships
- `belongsToMany()` for many-to-many relationships

### 1.5.7. Employee Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookEmployee extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_employees';

    /**
     * Get the secondary key type for this model.
     * Using ULID for employees - good for HR management.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'last_name',
        'first_name',
        'title',
        'reports_to',
        'birth_date',
        'hire_date',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'fax',
        'email',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the manager that this employee reports to.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(ChinookEmployee::class, 'reports_to');
    }

    /**
     * Get the employees that report to this employee.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(ChinookEmployee::class, 'reports_to');
    }

    /**
     * Get the customers assigned to this employee.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(ChinookCustomer::class, 'support_rep_id');
    }

    /**
     * Scope to find managers (employees with subordinates).
     */
    public function scopeManagers($query)
    {
        return $query->whereHas('subordinates');
    }

    /**
     * Scope to find employees by title.
     */
    public function scopeByTitle($query, string $title)
    {
        return $query->where('title', 'like', "%{$title}%");
    }

    /**
     * Get the employee's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the employee's years of service.
     */
    public function getYearsOfServiceAttribute(): int
    {
        return $this->hire_date ? $this->hire_date->diffInYears(now()) : 0;
    }

    /**
     * Get the employee's customer count.
     */
    public function getCustomerCountAttribute(): int
    {
        return $this->customers()->count();
    }

    /**
     * Check if this employee is a manager.
     */
    public function getIsManagerAttribute(): bool
    {
        return $this->subordinates()->exists();
    }
}
```

### 1.5.8. Invoice Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookInvoice extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_invoices';

    /**
     * Get the secondary key type for this model.
     * Using ULID for invoices - good for business records and chronological ordering.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'invoice_date',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_postal_code',
        'total',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'datetime',
            'total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the customer that owns the invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(ChinookCustomer::class);
    }

    /**
     * Get the invoice lines for the invoice.
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(ChinookInvoiceLine::class);
    }

    /**
     * Scope to find invoices by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    /**
     * Scope to find invoices above a certain amount.
     */
    public function scopeAboveAmount($query, float $amount)
    {
        return $query->where('total', '>', $amount);
    }

    /**
     * Get the invoice's line count.
     */
    public function getLineCountAttribute(): int
    {
        return $this->invoiceLines()->count();
    }

    /**
     * Get the invoice's formatted total.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total, 2);
    }

    /**
     * Calculate the invoice total from line items.
     */
    public function calculateTotal(): float
    {
        return $this->invoiceLines()->sum(\DB::raw('unit_price * quantity'));
    }
}
```

### 1.5.9. InvoiceLine Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookInvoiceLine extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_invoice_lines';

    /**
     * Get the secondary key type for this model.
     * Using Snowflake for invoice lines - high performance for transaction data.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::SNOWFLAKE;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'invoice_id',
        'track_id',
        'unit_price',
        'quantity',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the invoice that owns the invoice line.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ChinookInvoice::class);
    }

    /**
     * Get the track that owns the invoice line.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(ChinookTrack::class);
    }

    /**
     * Calculate the line total (unit_price * quantity).
     */
    public function getLineTotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * Get the formatted line total.
     */
    public function getFormattedLineTotalAttribute(): string
    {
        return '$' . number_format($this->line_total, 2);
    }

    /**
     * Scope to find lines above a certain amount.
     */
    public function scopeAboveAmount($query, float $amount)
    {
        return $query->whereRaw('unit_price * quantity > ?', [$amount]);
    }
}
```

### 1.5.10. Playlist Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookPlaylist extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_playlists';

    /**
     * Get the secondary key type for this model.
     * Using ULID for playlists - user-friendly for sharing.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'is_public',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the tracks that belong to this playlist.
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(ChinookTrack::class, 'chinook_playlist_track')
            ->withTimestamps()
            ->withPivot(['position', 'added_by']);
    }

    /**
     * Scope to find public playlists.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to find playlists with tracks.
     */
    public function scopeWithTracks($query)
    {
        return $query->whereHas('tracks');
    }

    /**
     * Get the playlist's track count.
     */
    public function getTrackCountAttribute(): int
    {
        return $this->tracks()->count();
    }

    /**
     * Get the playlist's total duration.
     */
    public function getTotalDurationAttribute(): int
    {
        return $this->tracks()->sum('milliseconds');
    }

    /**
     * Get the playlist's formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalSeconds = intval($this->total_duration / 1000);
        $hours = intval($totalSeconds / 3600);
        $minutes = intval(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
```

### 1.5.11. Analytics Models

The Chinook platform includes comprehensive analytics models to track user behavior, search patterns, and content
engagement. These models follow Laravel 12 modern patterns and integrate seamlessly with the existing RBAC and
categorization systems.

#### 1.5.11.1. PlayEvent Model

The `PlayEvent` model tracks when users play tracks, providing essential data for music recommendation algorithms and
usage analytics.

```php
<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use Carbon\Carbon;

class PlayEvent extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_play_events';

    /**
     * Get the secondary key type for this model.
     * Using Snowflake for high-performance analytics.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::SNOWFLAKE;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'track_id',
        'user_id',
        'customer_id',
        'played_at',
        'duration_listened',
        'completion_percentage',
        'source_type',
        'source_id',
        'device_type',
        'ip_address',
        'user_agent',
        'session_id',
        'playlist_id',
        'album_id',
        'artist_id',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'duration_listened' => 'integer',
            'completion_percentage' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function track(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Track::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Playlist::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Album::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Artist::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeCompleted($query, float $threshold = 0.8)
    {
        return $query->where('completion_percentage', '>=', $threshold);
    }

    public function scopeSkipped($query, float $threshold = 0.3)
    {
        return $query->where('completion_percentage', '<', $threshold);
    }

    public function scopeByDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('played_at', [$start, $end]);
    }

    public function scopeByDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    public function scopePopularTracks($query, int $limit = 50)
    {
        return $query->select('track_id')
            ->selectRaw('COUNT(*) as play_count')
            ->selectRaw('AVG(completion_percentage) as avg_completion')
            ->groupBy('track_id')
            ->orderByDesc('play_count')
            ->limit($limit);
    }

    // Accessors & Mutators
    public function getIsCompletedAttribute(): bool
    {
        return $this->completion_percentage >= 0.8;
    }

    public function getIsSkippedAttribute(): bool
    {
        return $this->completion_percentage < 0.3;
    }

    public function getFormattedDurationAttribute(): string
    {
        $seconds = intval($this->duration_listened / 1000);
        $minutes = intval($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    // Helper Methods
    public static function recordPlay(
        int $trackId,
        int $userId,
        int $durationListened,
        array $context = []
    ): self {
        $track = \App\Models\Track::findOrFail($trackId);
        $completionPercentage = $track->milliseconds > 0
            ? ($durationListened / $track->milliseconds) * 100
            : 0;

        return self::create([
            'track_id' => $trackId,
            'user_id' => $userId,
            'customer_id' => $context['customer_id'] ?? null,
            'played_at' => now(),
            'duration_listened' => $durationListened,
            'completion_percentage' => min($completionPercentage, 100),
            'source_type' => $context['source_type'] ?? 'direct',
            'source_id' => $context['source_id'] ?? null,
            'device_type' => $context['device_type'] ?? 'web',
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'user_agent' => $context['user_agent'] ?? request()->userAgent(),
            'session_id' => $context['session_id'] ?? session()->getId(),
            'playlist_id' => $context['playlist_id'] ?? null,
            'album_id' => $track->album_id,
            'artist_id' => $track->artist_id,
        ]);
    }
}
```

#### 1.5.11.2. SearchEvent Model

The `SearchEvent` model tracks user search queries, providing insights into user intent and content discovery patterns.

```php
<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use Carbon\Carbon;

class SearchEvent extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_search_events';

    /**
     * Get the secondary key type for this model.
     * Using Snowflake for high-performance analytics.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::SNOWFLAKE;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'query',
        'normalized_query',
        'user_id',
        'customer_id',
        'searched_at',
        'results_count',
        'clicked_result_id',
        'clicked_result_type',
        'clicked_position',
        'search_type',
        'filters_applied',
        'sort_order',
        'device_type',
        'ip_address',
        'user_agent',
        'session_id',
        'response_time_ms',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'searched_at' => 'datetime',
            'results_count' => 'integer',
            'clicked_position' => 'integer',
            'filters_applied' => 'array',
            'response_time_ms' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    // Scopes
    public function scopeWithResults($query, int $minResults = 1)
    {
        return $query->where('results_count', '>=', $minResults);
    }

    public function scopeWithoutResults($query)
    {
        return $query->where('results_count', 0);
    }

    public function scopeWithClicks($query)
    {
        return $query->whereNotNull('clicked_result_id');
    }

    public function scopeByDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('searched_at', [$start, $end]);
    }

    public function scopeBySearchType($query, string $searchType)
    {
        return $query->where('search_type', $searchType);
    }

    public function scopePopularQueries($query, int $limit = 50)
    {
        return $query->select('normalized_query')
            ->selectRaw('COUNT(*) as search_count')
            ->selectRaw('AVG(results_count) as avg_results')
            ->selectRaw('COUNT(clicked_result_id) as click_count')
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit($limit);
    }

    // Accessors & Mutators
    public function getHasResultsAttribute(): bool
    {
        return $this->results_count > 0;
    }

    public function getHasClickAttribute(): bool
    {
        return !is_null($this->clicked_result_id);
    }

    public function getClickThroughRateAttribute(): float
    {
        return $this->has_results && $this->has_click ? 1.0 : 0.0;
    }

    // Helper Methods
    public static function recordSearch(
        string $query,
        int $resultsCount,
        array $context = []
    ): self {
        return self::create([
            'query' => $query,
            'normalized_query' => self::normalizeQuery($query),
            'user_id' => $context['user_id'] ?? auth()->id(),
            'customer_id' => $context['customer_id'] ?? null,
            'searched_at' => now(),
            'results_count' => $resultsCount,
            'search_type' => $context['search_type'] ?? 'general',
            'filters_applied' => $context['filters'] ?? [],
            'sort_order' => $context['sort_order'] ?? 'relevance',
            'device_type' => $context['device_type'] ?? 'web',
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'user_agent' => $context['user_agent'] ?? request()->userAgent(),
            'session_id' => $context['session_id'] ?? session()->getId(),
            'response_time_ms' => $context['response_time_ms'] ?? null,
        ]);
    }

    public static function normalizeQuery(string $query): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $query)));
    }
}
```

#### 1.5.11.3. ViewEvent Model

The `ViewEvent` model tracks when users view content pages, providing insights into content engagement and user
navigation patterns through polymorphic relationships.

```php
<?php

declare(strict_types=1);

namespace App\Models\Analytics;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use Carbon\Carbon;

class ViewEvent extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;
    use SoftDeletes;
    use Userstamps;

    /**
     * The table associated with the model.
     */
    protected $table = 'chinook_view_events';

    /**
     * Get the secondary key type for this model.
     * Using Snowflake for high-performance analytics.
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::SNOWFLAKE;
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'viewable_type',
        'viewable_id',
        'user_id',
        'customer_id',
        'viewed_at',
        'view_duration_seconds',
        'page_url',
        'referrer_url',
        'device_type',
        'browser_type',
        'ip_address',
        'user_agent',
        'session_id',
        'is_unique_view',
        'scroll_percentage',
        'interactions_count',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'view_duration_seconds' => 'integer',
            'is_unique_view' => 'boolean',
            'scroll_percentage' => 'decimal:2',
            'interactions_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->startSlugSuffixFrom(2);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // Relationships
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    // Scopes
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('viewable_type', $modelType);
    }

    public function scopeUniqueViews($query)
    {
        return $query->where('is_unique_view', true);
    }

    public function scopeEngaged($query, int $minDuration = 30)
    {
        return $query->where('view_duration_seconds', '>=', $minDuration);
    }

    public function scopeByDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('viewed_at', [$start, $end]);
    }

    public function scopeByDevice($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    public function scopePopularContent($query, string $modelType = null, int $limit = 50)
    {
        $query = $query->select('viewable_type', 'viewable_id')
            ->selectRaw('COUNT(*) as view_count')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_viewers')
            ->selectRaw('AVG(view_duration_seconds) as avg_duration')
            ->groupBy('viewable_type', 'viewable_id');

        if ($modelType) {
            $query->where('viewable_type', $modelType);
        }

        return $query->orderByDesc('view_count')->limit($limit);
    }

    // Accessors & Mutators
    public function getIsEngagedAttribute(): bool
    {
        return $this->view_duration_seconds >= 30;
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = intval($this->view_duration_seconds / 60);
        $seconds = $this->view_duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    // Helper Methods
    public static function recordView(
        Model $viewable,
        array $context = []
    ): self {
        $userId = $context['user_id'] ?? auth()->id();

        // Check if this is a unique view (first view by this user for this content)
        $isUniqueView = !self::where('viewable_type', get_class($viewable))
            ->where('viewable_id', $viewable->id)
            ->where('user_id', $userId)
            ->exists();

        return self::create([
            'viewable_type' => get_class($viewable),
            'viewable_id' => $viewable->id,
            'user_id' => $userId,
            'customer_id' => $context['customer_id'] ?? null,
            'viewed_at' => now(),
            'page_url' => $context['page_url'] ?? request()->url(),
            'referrer_url' => $context['referrer_url'] ?? request()->header('referer'),
            'device_type' => $context['device_type'] ?? 'web',
            'browser_type' => $context['browser_type'] ?? null,
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'user_agent' => $context['user_agent'] ?? request()->userAgent(),
            'session_id' => $context['session_id'] ?? session()->getId(),
            'is_unique_view' => $isUniqueView,
        ]);
    }

    public function updateEngagement(
        int $durationSeconds,
        float $scrollPercentage = null,
        int $interactionsCount = null
    ): void {
        $this->update([
            'view_duration_seconds' => $durationSeconds,
            'scroll_percentage' => $scrollPercentage,
            'interactions_count' => $interactionsCount,
        ]);
    }
}
```

#### 1.5.11.4. Analytics Models Usage Examples

**Recording Play Events:**

```php
// Record a track play
$playEvent = PlayEvent::recordPlay(
    trackId: $track->id,
    userId: auth()->id(),
    durationListened: 180000, // 3 minutes in milliseconds
    context: [
        'source_type' => 'playlist',
        'source_id' => $playlist->id,
        'device_type' => 'mobile',
        'playlist_id' => $playlist->id,
    ]
);

// Query popular tracks
$popularTracks = PlayEvent::popularTracks(50)
    ->with('track.artist')
    ->get();
```

**Recording Search Events:**

```php
// Record a search
$searchEvent = SearchEvent::recordSearch(
    query: 'rock music 2023',
    resultsCount: 42,
    context: [
        'search_type' => 'tracks',
        'filters' => ['genre' => 'rock', 'year' => 2023],
        'sort_order' => 'popularity',
        'response_time_ms' => 150,
    ]
);

// Analyze search patterns
$popularQueries = SearchEvent::popularQueries(20)
    ->having('search_count', '>', 10)
    ->get();
```

**Recording View Events:**

```php
// Record an artist page view
$viewEvent = ViewEvent::recordView(
    viewable: $artist,
    context: [
        'device_type' => 'desktop',
        'browser_type' => 'chrome',
    ]
);

// Update engagement metrics
$viewEvent->updateEngagement(
    durationSeconds: 120,
    scrollPercentage: 75.5,
    interactionsCount: 3
);

// Get popular content
$popularArtists = ViewEvent::popularContent(\App\Models\Artist::class, 10)
    ->with('viewable')
    ->get();
```

## 1.8. Role-Based Access Control Integration

### 1.8.1. Permission System Overview

The Chinook system implements a comprehensive role-based access control system with these key roles:

**Role Hierarchy:**

1. **Super Admin**: Complete system control
2. **Admin**: Full business operations
3. **Manager**: Department management
4. **Editor**: Content management
5. **Customer Service**: Customer support
6. **User**: Standard customer
7. **Guest**: Public access

### 1.8.2. Model-Level Authorization Examples

```php
// Check permissions in controllers
class ArtistController extends Controller
{
    public function index()
    {
        $this->authorize('view-artists');
        return Artist::with('categories')->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create-artists');

        $artist = Artist::create($request->validated());
        $artist->syncTaxonomiesByType('genre', $request->genre_ids);

        return $artist;
    }

    public function update(Request $request, Artist $artist)
    {
        $this->authorize('edit-artists');

        $artist->update($request->validated());

        return $artist;
    }
}
```

### 1.8.3. Blade Template Authorization

```blade
@can('view-artists')
    <a href="{{ route('artists.index') }}">View Artists</a>
@endcan

@role('admin|manager')
    <a href="{{ route('artists.create') }}">Create Artist</a>
@endrole

@hasrole('super-admin')
    <a href="{{ route('admin.users') }}">Manage Users</a>
@endhasrole

@cannot('edit-artists')
    <p>You don't have permission to edit artists.</p>
@endcannot
```

## 1.9. Taxonomy Usage Examples

### 1.9.1. Taxonomy Assignment and Retrieval

```php
// Create taxonomies using aliziodev/laravel-taxonomy
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

$rockGenre = Taxonomy::create([
    'name' => 'Rock',
    'type' => 'genre',
    'description' => 'Rock music genre',
]);

$energeticMood = Taxonomy::create([
    'name' => 'Energetic',
    'type' => 'mood',
    'description' => 'High energy music',
]);

// Assign taxonomies to models
$artist = Artist::first();
$artist->attachTaxonomies([$rockGenre->id, $energeticMood->id]);

// Or use helper methods
$album = Album::first();
$album->syncTaxonomiesByType('genre', [$rockGenre->id]);
$album->syncTaxonomiesByType('mood', [$energeticMood->id]);

// Retrieve by taxonomy type
$rockArtists = Artist::byGenre('Rock')->get();
$energeticTracks = Track::byMood('Energetic')->get();
$workoutPlaylists = Playlist::byTheme('Workout')->get();
```

### 1.9.2. Hierarchical Taxonomy Management

```php
// Create taxonomy hierarchy using aliziodev/laravel-taxonomy
$rock = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
$hardRock = Taxonomy::create(['name' => 'Hard Rock', 'type' => 'genre', 'parent_id' => $rock->id]);
$heavyMetal = Taxonomy::create(['name' => 'Heavy Metal', 'type' => 'genre', 'parent_id' => $hardRock->id]);

// Navigate hierarchy
$ancestors = $heavyMetal->ancestors(); // [Rock, Hard Rock]
$descendants = $rock->descendants(); // [Hard Rock, Heavy Metal]
$siblings = $hardRock->siblings(); // Other children of Rock

// Get full hierarchical name
echo $heavyMetal->full_name; // "Rock > Hard Rock > Heavy Metal"
```

### 1.9.3. Advanced Taxonomy Queries

```php
// Find tracks with multiple taxonomy types
$tracks = Track::whereHas('taxonomies', function ($q) {
    $q->where('type', 'genre')->where('name', 'Rock');
})->whereHas('taxonomies', function ($q) {
    $q->where('type', 'mood')->where('name', 'Energetic');
})->get();

// Get taxonomy statistics
$genreStats = Taxonomy::where('type', 'genre')
    ->withCount(['taxonomables'])
    ->get();

// Find popular taxonomies
$popularGenres = Taxonomy::where('type', 'genre')
    ->whereHas('taxonomables', function ($q) {
        $q->whereHasMorph('taxonomable', [Track::class], function ($trackQuery) {
            $trackQuery->popular(50); // Tracks with 50+ purchases
        });
    })
    })->get();
```

## 1.10. Model Usage Examples with Modern Features

### 1.10.1. Basic Queries with Categories

```php
// Get all artists with their categories
$artists = Artist::with('categories')->get();

// Find artist by slug (URL-friendly)
$artist = Artist::where('slug', 'artist-slug')->first();

// Find artist by public_id (API-friendly)
$artist = Artist::findBySecondaryKey('01ARZ3NDEKTSV4RRFFQ69G5FAV');

// Get tracks with categories and album information
$tracks = Track::with(['album.artist', 'categories', 'mediaType'])->get();

// Get customer with their invoices and preferences
$customer = Customer::with(['invoices.invoiceLines.track', 'categories'])->first();
```

### 1.7.2. Using Tags and Scopes

```php
// Tag an artist with genres
$artist = Artist::first();
$artist->attachTag('rock');
$artist->attachTag('classic-rock');

// Find artists with specific tags
$rockArtists = Artist::withAllTags(['rock'])->get();

// Use scopes for business logic
$popularGenres = Genre::popular(50)->get();
$recentCustomers = Customer::recentCustomers(30)->get();
$publishedArtists = Artist::published()->get();
```

### 1.7.3. Complex Relationships and Calculations

```php
// Get all tracks by a specific artist with duration info
$artist = Artist::first();
$tracks = $artist->tracks()->with('genre')->get();
$totalDuration = $tracks->sum('milliseconds');

// Get employee hierarchy with user stamps
$manager = Employee::with(['subordinates.subordinates', 'createdBy', 'updatedBy'])->first();

// Get playlist with track details and total duration
$playlist = Playlist::with(['tracks.album.artist', 'tracks.genre'])->first();
echo "Playlist duration: {$playlist->formatted_duration}";

// Customer analytics
$customer = Customer::first();
echo "Customer: {$customer->full_name}";
echo "Total spent: \${$customer->total_spent}";
echo "Invoice count: {$customer->invoice_count}";
```

### 1.7.4. Working with Secondary Keys and Slugs

```php
// Create models with automatic public_id and slug generation
$artist = Artist::create(['name' => 'The Beatles']);
echo "Public ID: {$artist->public_id}"; // ULID generated
echo "Slug: {$artist->slug}"; // Generated from public_id

// Route model binding using slugs
Route::get('/artists/{artist}', function (Artist $artist) {
    return $artist; // Automatically resolved by slug
});

// API endpoints using public_id
Route::get('/api/artists/{public_id}', function ($publicId) {
    $artist = Artist::findBySecondaryKeyOrFail($publicId);
    return $artist;
});
```

### 1.7.5. Soft Deletes and User Stamps

```php
// Soft delete with user tracking
$artist = Artist::first();
$artist->delete(); // Sets deleted_at and deleted_by

// Restore soft deleted records
$artist->restore(); // Clears deleted_at

// Query including soft deleted
$allArtists = Artist::withTrashed()->get();
$onlyDeleted = Artist::onlyTrashed()->get();

// Check who created/updated records
echo "Created by: {$artist->createdBy->name}";
echo "Updated by: {$artist->updatedBy->name}";
```

### 1.7.6. Advanced Querying

```php
// Find tracks by duration range
$shortTracks = Track::byDuration(0, 180000)->get(); // Under 3 minutes
$longTracks = Track::byDuration(360000, PHP_INT_MAX)->get(); // Over 6 minutes

// Popular tracks with purchase data
$popularTracks = Track::popular(10)->with('album.artist')->get();

// Customer analytics by country
$usCustomers = Customer::byCountry('USA')
    ->recentCustomers(90)
    ->with('invoices')
    ->get();

// Invoice reporting
$highValueInvoices = Invoice::aboveAmount(50.00)
    ->byDateRange(now()->subMonth(), now())
    ->with('customer')
    ->get();
```

## 1.8. Migration Requirements

Before using these models, ensure your migrations include:

```php
// Required columns for all models
$table->id();
$table->string('public_id')->unique()->index(); // Secondary unique key
$table->string('slug')->unique()->index(); // URL-friendly identifier
$table->timestamps(); // created_at, updated_at
$table->softDeletes(); // deleted_at
$table->userstamps(); // created_by, updated_by, deleted_by

// For models with taxonomies (handled by aliziodev/laravel-taxonomy package)
// Run: php artisan taxonomy:install
// Or manually: php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider"
```

## 1.9. Next Steps

After creating these models, you should:

1. **Create Migrations**: See [Chinook Migrations Guide](020-chinook-migrations-guide.md)
2. **Create Factories**: See [Chinook Factories Guide](030-chinook-factories-guide.md)
3. **Create Seeders**: See [Chinook Seeders Guide](040-chinook-seeders-guide.md)
4. **Install Taxonomy System**:
   `php artisan taxonomy:install`
5. **Configure User Stamps**: Ensure your User model is properly configured for user stamps

## Related Documentation

### Single Taxonomy System Implementation

- **[Aliziodev Laravel Taxonomy Guide](packages/110-aliziodev-laravel-taxonomy-guide.md)** - ✅ **Greenfield** Complete single taxonomy system implementation
- **[Direct Taxonomy Mapping Testing](testing/100-genre-preservation-testing.md)** - Comprehensive testing strategy for direct mapping approach
- **[Comprehensive Data Access Guide](130-comprehensive-data-access-guide.md)** - CLI + web + API data access facilities

### Model Implementation

- **[Model Architecture Guide](filament/models/010-model-architecture.md)** - Comprehensive model patterns with single taxonomy system
- **[Taxonomy Integration Guide](filament/models/060-categorizable-trait.md)** - HasTaxonomies trait implementation patterns
- **[Hierarchy Comparison Guide](070-chinook-hierarchy-comparison-guide.md)** - Hybrid architecture patterns and performance analysis

---

## Navigation

**Next →** [Chinook Migrations Guide](020-chinook-migrations-guide.md)
