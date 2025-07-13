# 1. Chinook Database Models Guide

> **Refactored from:** `.ai/guides/chinook/010-chinook-models-guide.md` on 2025-07-11  
> **Focus:** Single taxonomy system using aliziodev/laravel-taxonomy package exclusively

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
  - [1.2.1. Modern Laravel 12 Features](#121-modern-laravel-12-features)
  - [1.2.2. Database Schema Overview](#122-database-schema-overview)
  - [1.2.3. Required Packages](#123-required-packages)
- [1.3. Model Architecture](#13-model-architecture)
  - [1.3.1. Base Model Traits](#131-base-model-traits)
  - [1.3.2. Taxonomy Integration](#132-taxonomy-integration)
  - [1.3.3. Performance Considerations](#133-performance-considerations)
- [1.4. Core Music Models](#14-core-music-models)
  - [1.4.1. ChinookArtist Model](#141-chinookartist-model)
  - [1.4.2. ChinookAlbum Model](#142-chinookalbum-model)
  - [1.4.3. ChinookTrack Model](#143-chinooktrack-model)
  - [1.4.4. ChinookMediaType Model](#144-chinookmediatype-model)
- [2. Customer & Employee Models](#2-customer--employee-models)
  - [2.1. ChinookCustomer Model](#21-chinookcustomer-model)
  - [2.2. ChinookEmployee Model](#22-chinookemployee-model)
- [3. Sales Models](#3-sales-models)
  - [3.1. ChinookInvoice Model](#31-chinookinvoice-model)
  - [3.2. ChinookInvoiceLine Model](#32-chinookinvoiceline-model)
- [4. Playlist Models](#4-playlist-models)
  - [4.1. ChinookPlaylist Model](#41-chinookplaylist-model)
- [5. Taxonomy Models](#5-taxonomy-models)
  - [5.1. ChinookGenre Model](#51-chinookgenre-model)
- [6. Model Relationships Summary](#6-model-relationships-summary)
  - [6.1. Core Music Relationships](#61-core-music-relationships)
  - [6.2. Taxonomy Relationships](#62-taxonomy-relationships)
  - [6.3. RBAC Relationships](#63-rbac-relationships)
- [7. Testing & Validation](#7-testing--validation)
  - [7.1. Model Testing](#71-model-testing)
  - [7.2. Relationship Testing](#72-relationship-testing)

## 1.2. Overview

This guide provides comprehensive instructions for creating modern Laravel 12 Eloquent models for the Chinook database schema using a **single taxonomy system** approach. The Chinook database represents a digital music store with artists, albums, tracks, customers, employees, and sales data, enhanced with the aliziodev/laravel-taxonomy package for unified categorization.

### 1.2.1. Modern Laravel 12 Features

All models include modern Laravel 12 features:

- **Modern Casting**: Using `casts()` method instead of `$casts` property
- **Single Taxonomy System**: aliziodev/laravel-taxonomy for unified categorization
- **Timestamps**: `created_at` and `updated_at` columns
- **Soft Deletes**: Safe deletion with `deleted_at` column
- **User Stamps**: Track who created/updated records
- **Taxonomies**: Single taxonomy system for all categorization needs
- **Secondary Unique Keys**: Public-facing identifiers using `public_id`
- **Slugs**: URL-friendly identifiers generated from `public_id`

### 1.2.2. Database Schema Overview

The Chinook database consists of interconnected tables with modern Laravel 12 enhancements and single taxonomy system:

- **Core Music Data**: `artists`, `albums`, `tracks`, `media_types`
- **Single Taxonomy System**: aliziodev/laravel-taxonomy package tables (`taxonomies`, `taxonomy_terms`, `taxables`)
- **Genre Compatibility**: `chinook_genres` (preserved for data export/import compatibility)
- **Customer Management**: `customers`, `employees`
- **Sales System**: `invoices`, `invoice_lines`
- **Playlist System**: `playlists`, `playlist_track`

### 1.2.3. Required Packages

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

# Media library integration
composer require spatie/laravel-media-library
```

## 1.3. Model Architecture

### 1.3.1. Base Model Traits

All Chinook models implement these essential traits:

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Wildside\Userstamps\Userstamps;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use App\Traits\HasSecondaryUniqueKey;

abstract class ChinookBaseModel extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Userstamps;
    use HasTaxonomies;
    use HasSlug;
    use HasSecondaryUniqueKey;

    /**
     * Modern Laravel 12 casting using casts() method
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
     * Configure slug generation from public_id
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Configure secondary unique key generation
     */
    public function getSecondaryUniqueKeyOptions(): array
    {
        return [
            'field' => 'public_id',
            'type' => 'ulid', // or 'uuid', 'snowflake'
        ];
    }
}
```

### 1.3.2. Taxonomy Integration

The single taxonomy system provides unified categorization across all models:

```php
<?php

namespace App\Models\Chinook;

class ChinookTrack extends ChinookBaseModel
{
    // Taxonomy relationships are automatically available via HasTaxonomies trait
    
    /**
     * Get all taxonomy terms for this track
     */
    public function getTaxonomyTermsAttribute()
    {
        return $this->taxonomies()->with('terms')->get();
    }

    /**
     * Assign taxonomy terms to this track
     */
    public function assignTaxonomyTerms(array $termIds): void
    {
        $this->taxonomies()->sync($termIds);
    }

    /**
     * Get tracks by taxonomy term
     */
    public function scopeWithTaxonomyTerm($query, string $termSlug)
    {
        return $query->whereHas('taxonomies.terms', function ($q) use ($termSlug) {
            $q->where('slug', $termSlug);
        });
    }
}
```

### 1.3.3. Performance Considerations

**Eager Loading Strategy**:

```php
// Efficient loading of taxonomy relationships
$tracks = ChinookTrack::with([
    'taxonomies.terms',
    'album.artist',
    'mediaType'
])->get();

// Optimized taxonomy filtering
$genreTracks = ChinookTrack::withTaxonomyTerm('rock')
    ->with('taxonomies.terms')
    ->paginate(20);
```

**Caching Strategy**:

```php
// Cache taxonomy hierarchies for performance
$taxonomyHierarchy = Cache::remember('taxonomy_hierarchy', 3600, function () {
    return Taxonomy::with('terms.children')->get();
});
```

## 1.4. Core Music Models

### 1.4.1. ChinookArtist Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookArtist extends ChinookBaseModel
{
    protected $table = 'chinook_artists';

    protected $fillable = [
        'name',
        'public_id',
        'slug',
        'bio',
        'website',
        'social_links',
        'country',
        'formed_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'social_links' => 'array',
            'formed_year' => 'integer',
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Artist has many albums
     */
    public function albums(): HasMany
    {
        return $this->hasMany(ChinookAlbum::class, 'artist_id');
    }

    /**
     * Artist has many tracks through albums
     */
    public function tracks()
    {
        return $this->hasManyThrough(ChinookTrack::class, ChinookAlbum::class, 'artist_id', 'album_id');
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

### 1.4.2. ChinookAlbum Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookAlbum extends ChinookBaseModel
{
    protected $table = 'chinook_albums';

    protected $fillable = [
        'title',
        'artist_id',
        'public_id',
        'slug',
        'release_date',
        'label',
        'catalog_number',
        'total_tracks',
        'duration_seconds',
        'description',
        'is_compilation',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'release_date' => 'date',
            'total_tracks' => 'integer',
            'duration_seconds' => 'integer',
            'is_compilation' => 'boolean',
        ]);
    }

    /**
     * Album belongs to an artist
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ChinookArtist::class, 'artist_id');
    }

    /**
     * Album has many tracks
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(ChinookTrack::class, 'album_id');
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

### 1.4.3. ChinookTrack Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookTrack extends ChinookBaseModel
{
    protected $table = 'chinook_tracks';

    protected $fillable = [
        'name',
        'album_id',
        'media_type_id',
        'public_id',
        'slug',
        'composer',
        'milliseconds',
        'bytes',
        'unit_price',
        'track_number',
        'disc_number',
        'lyrics',
        'isrc',
        'explicit_content',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'milliseconds' => 'integer',
            'bytes' => 'integer',
            'unit_price' => 'decimal:2',
            'track_number' => 'integer',
            'disc_number' => 'integer',
            'explicit_content' => 'boolean',
        ]);
    }

    /**
     * Track belongs to an album
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(ChinookAlbum::class, 'album_id');
    }

    /**
     * Track belongs to a media type
     */
    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(ChinookMediaType::class, 'media_type_id');
    }

    /**
     * Track belongs to many playlists
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(ChinookPlaylist::class, 'chinook_playlist_track', 'track_id', 'playlist_id')
                    ->withPivot('position')
                    ->withTimestamps();
    }

    /**
     * Track has many invoice lines
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(ChinookInvoiceLine::class, 'track_id');
    }

    /**
     * Get artist through album relationship
     */
    public function artist()
    {
        return $this->hasOneThrough(ChinookArtist::class, ChinookAlbum::class, 'id', 'id', 'album_id', 'artist_id');
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalSeconds = floor($this->milliseconds / 1000);
        $minutes = floor($totalSeconds / 60);
        $seconds = $totalSeconds % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        if ($this->bytes < 1024) {
            return $this->bytes . ' B';
        } elseif ($this->bytes < 1048576) {
            return round($this->bytes / 1024, 2) . ' KB';
        } else {
            return round($this->bytes / 1048576, 2) . ' MB';
        }
    }

    /**
     * Scope for tracks with specific taxonomy terms
     */
    public function scopeWithTaxonomyTerm($query, string $termSlug)
    {
        return $query->whereHas('taxonomies.terms', function ($q) use ($termSlug) {
            $q->where('slug', $termSlug);
        });
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

### 1.4.4. ChinookMediaType Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookMediaType extends ChinookBaseModel
{
    protected $table = 'chinook_media_types';

    protected $fillable = [
        'name',
        'public_id',
        'slug',
        'mime_type',
        'file_extension',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Media type has many tracks
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(ChinookTrack::class, 'media_type_id');
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

## 2. Customer & Employee Models

### 2.1. ChinookCustomer Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookCustomer extends ChinookBaseModel
{
    protected $table = 'chinook_customers';

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
        'date_of_birth',
        'preferred_language',
        'marketing_consent',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'date_of_birth' => 'date',
            'marketing_consent' => 'boolean',
        ]);
    }

    /**
     * Customer belongs to a support representative (employee)
     */
    public function supportRep(): BelongsTo
    {
        return $this->belongsTo(ChinookEmployee::class, 'support_rep_id');
    }

    /**
     * Customer has many invoices
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(ChinookInvoice::class, 'customer_id');
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

### 2.2. ChinookEmployee Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookEmployee extends ChinookBaseModel
{
    protected $table = 'chinook_employees';

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
        'department',
        'salary',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'salary' => 'decimal:2',
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Employee reports to another employee (manager)
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(ChinookEmployee::class, 'reports_to');
    }

    /**
     * Employee has many subordinates
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(ChinookEmployee::class, 'reports_to');
    }

    /**
     * Employee supports many customers
     */
    public function customers(): HasMany
    {
        return $this->hasMany(ChinookCustomer::class, 'support_rep_id');
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

## 3. Sales Models

### 3.1. ChinookInvoice Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinookInvoice extends ChinookBaseModel
{
    protected $table = 'chinook_invoices';

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
        'payment_method',
        'payment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'invoice_date' => 'datetime',
            'total' => 'decimal:2',
        ]);
    }

    /**
     * Invoice belongs to a customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(ChinookCustomer::class, 'customer_id');
    }

    /**
     * Invoice has many invoice lines
     */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(ChinookInvoiceLine::class, 'invoice_id');
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

### 3.2. ChinookInvoiceLine Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinookInvoiceLine extends ChinookBaseModel
{
    protected $table = 'chinook_invoice_lines';

    protected $fillable = [
        'invoice_id',
        'track_id',
        'unit_price',
        'quantity',
        'public_id',
        'slug',
        'discount_percentage',
        'line_total',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'discount_percentage' => 'decimal:2',
            'line_total' => 'decimal:2',
        ]);
    }

    /**
     * Invoice line belongs to an invoice
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ChinookInvoice::class, 'invoice_id');
    }

    /**
     * Invoice line belongs to a track
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(ChinookTrack::class, 'track_id');
    }

    /**
     * Calculate line total automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($invoiceLine) {
            $subtotal = $invoiceLine->unit_price * $invoiceLine->quantity;
            $discount = $subtotal * ($invoiceLine->discount_percentage / 100);
            $invoiceLine->line_total = $subtotal - $discount;
        });
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

## 4. Playlist Models

### 4.1. ChinookPlaylist Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChinookPlaylist extends ChinookBaseModel
{
    protected $table = 'chinook_playlists';

    protected $fillable = [
        'name',
        'public_id',
        'slug',
        'description',
        'is_public',
        'total_duration',
        'track_count',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_public' => 'boolean',
            'total_duration' => 'integer',
            'track_count' => 'integer',
        ]);
    }

    /**
     * Playlist belongs to many tracks
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(ChinookTrack::class, 'chinook_playlist_track', 'playlist_id', 'track_id')
                    ->withPivot('position')
                    ->withTimestamps()
                    ->orderBy('pivot_position');
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        $totalSeconds = floor($this->total_duration / 1000);
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

## 5. Taxonomy Models

### 5.1. ChinookGenre Model

```php
<?php

namespace App\Models\Chinook;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChinookGenre Model - Preserved for compatibility
 *
 * This model is maintained for data export/import compatibility
 * with the original Chinook database structure. For new implementations,
 * use the aliziodev/laravel-taxonomy package exclusively.
 */
class ChinookGenre extends ChinookBaseModel
{
    protected $table = 'chinook_genres';

    protected $fillable = [
        'name',
        'public_id',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
        ]);
    }

    /**
     * Genre has many tracks (legacy relationship)
     *
     * @deprecated Use taxonomy relationships instead
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(ChinookTrack::class, 'genre_id');
    }

    /**
     * Get corresponding taxonomy term
     */
    public function getTaxonomyTermAttribute()
    {
        return \Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm::where('name', $this->name)->first();
    }

    /**
     * Get route key name for URL generation
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

## 6. Model Relationships Summary

### 6.1. Core Music Relationships

- **Artists → Albums**: One-to-many relationship
- **Albums → Tracks**: One-to-many relationship
- **Tracks → Media Types**: Many-to-one relationship
- **Tracks → Playlists**: Many-to-many through pivot table

### 6.2. Taxonomy Relationships

All models can have taxonomy relationships through the `HasTaxonomies` trait:

```php
// Assign taxonomy terms to any model
$track->taxonomies()->attach($taxonomyTermIds);

// Query models by taxonomy
$rockTracks = ChinookTrack::withTaxonomyTerm('rock')->get();

// Get all taxonomy terms for a model
$trackTaxonomies = $track->taxonomies()->with('terms')->get();
```

### 6.3. RBAC Relationships

- **Users → Roles**: Many-to-many with spatie/laravel-permission
- **Roles → Permissions**: Many-to-many with granular permission system
- **Model Policies**: Resource-based authorization with hierarchical inheritance

## 7. Testing & Validation

### 7.1. Model Testing

```php
<?php

use App\Models\Chinook\ChinookTrack;
use App\Models\Chinook\ChinookAlbum;
use App\Models\Chinook\ChinookArtist;

describe('ChinookTrack Model', function () {
    it('can create a track with taxonomy terms', function () {
        $track = ChinookTrack::factory()->create();

        $taxonomyTerm = \Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm::factory()->create([
            'name' => 'Rock'
        ]);

        $track->taxonomies()->attach($taxonomyTerm->id);

        expect($track->taxonomies)->toHaveCount(1);
        expect($track->taxonomies->first()->name)->toBe('Rock');
    });

    it('can query tracks by taxonomy term', function () {
        $rockTrack = ChinookTrack::factory()->create();
        $jazzTrack = ChinookTrack::factory()->create();

        $rockTerm = \Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm::factory()->create([
            'name' => 'Rock',
            'slug' => 'rock'
        ]);

        $rockTrack->taxonomies()->attach($rockTerm->id);

        $results = ChinookTrack::withTaxonomyTerm('rock')->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($rockTrack->id);
    });
});
```

### 7.2. Relationship Testing

```php
describe('Model Relationships', function () {
    it('maintains proper artist-album-track hierarchy', function () {
        $artist = ChinookArtist::factory()->create();
        $album = ChinookAlbum::factory()->create(['artist_id' => $artist->id]);
        $track = ChinookTrack::factory()->create(['album_id' => $album->id]);

        expect($track->album->id)->toBe($album->id);
        expect($track->album->artist->id)->toBe($artist->id);
        expect($artist->albums)->toHaveCount(1);
        expect($album->tracks)->toHaveCount(1);
    });
});
```

---

## Navigation

**Previous:** [000-chinook-index.md](000-chinook-index.md) | **Index:** [Table of Contents](#11-table-of-contents) | **Next:** [020-chinook-migrations-guide.md](020-chinook-migrations-guide.md)

---

**Documentation Standards**: This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns.

[⬆️ Back to Top](#1-chinook-database-models-guide)
