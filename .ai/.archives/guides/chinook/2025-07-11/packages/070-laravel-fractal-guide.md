# 1. Laravel Fractal Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/070-laravel-fractal-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Fractal Implementation Guide](#1-laravel-fractal-implementation-guide)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. Configuration Publishing](#122-configuration-publishing)
    - [1.2.3. Basic Setup](#123-basic-setup)
  - [1.3. Transformer Creation](#13-transformer-creation)
    - [1.3.1. Basic Transformers](#131-basic-transformers)
    - [1.3.2. Advanced Transformers](#132-advanced-transformers)
    - [1.3.3. Nested Transformers](#133-nested-transformers)
  - [1.4. Resource Management](#14-resource-management)
    - [1.4.1. Item Resources](#141-item-resources)
    - [1.4.2. Collection Resources](#142-collection-resources)
    - [1.4.3. Relationship Handling](#143-relationship-handling)
  - [1.5. Pagination & Filtering](#15-pagination--filtering)
    - [1.5.1. Pagination Setup](#151-pagination-setup)
    - [1.5.2. Advanced Filtering](#152-advanced-filtering)
    - [1.5.3. Sorting & Ordering](#153-sorting--ordering)
  - [1.6. API Response Formatting](#16-api-response-formatting)
    - [1.6.1. Response Structure](#161-response-structure)
    - [1.6.2. Custom Serializers](#162-custom-serializers)
  - [1.7. Caching Integration](#17-caching-integration)
    - [1.7.1. Response Caching](#171-response-caching)
    - [1.7.2. Cache Invalidation](#172-cache-invalidation)
  - [1.8. Performance Optimization](#18-performance-optimization)
    - [1.8.1. Eager Loading](#181-eager-loading)
    - [1.8.2. Lazy Loading Prevention](#182-lazy-loading-prevention)
  - [1.9. Testing Strategies](#19-testing-strategies)
    - [1.9.1. Transformer Testing](#191-transformer-testing)
    - [1.9.2. API Response Testing](#192-api-response-testing)
  - [1.10. Best Practices](#110-best-practices)
    - [1.10.1. Security Considerations](#1101-security-considerations)
    - [1.10.2. Error Handling](#1102-error-handling)
  - [1.11. Navigation](#111-navigation)

## 1.1. Overview

Laravel Fractal provides advanced API transformation layers with flexible resource management, relationship handling, and comprehensive pagination support. This guide covers enterprise-level implementation with caching integration, performance optimization, and modern API patterns for the Chinook music store application.

**🚀 Key Features:**
- **Flexible Transformations**: Customizable data transformation with includes/excludes for Chinook APIs
- **Relationship Handling**: Efficient nested resource loading for music catalog relationships
- **Pagination Support**: Built-in pagination with metadata for large music collections
- **Caching Integration**: Response caching for improved music catalog performance
- **API Versioning**: Support for multiple API versions and backward compatibility
- **Performance Optimization**: Lazy loading and efficient data processing for music operations
- **Taxonomy Integration**: Advanced transformation support for aliziodev/laravel-taxonomy structures

**🎵 Chinook-Specific Benefits:**
- **Music Catalog APIs**: Structured transformation of tracks, albums, artists, and genres
- **Customer Data APIs**: Secure and consistent customer information presentation
- **Playlist Management**: Complex nested transformations for playlist and track relationships
- **Search Results**: Flexible transformation for mixed search result types
- **Invoice Processing**: Comprehensive invoice and payment data transformation
- **Analytics APIs**: Structured data presentation for business intelligence

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Fractal for the Chinook application:

```bash
# Install Laravel Fractal
composer require spatie/fractal

# Install additional packages for enhanced functionality
composer require spatie/laravel-query-builder # For advanced filtering
composer require spatie/laravel-json-api-paginate # For JSON API pagination
```

**Verification Steps:**

```bash
# Verify installation
php artisan tinker
>>> use Spatie\Fractal\Fractal;
>>> $fractal = new Fractal();
>>> $fractal->getManager()

# Expected output: League\Fractal\Manager instance
```

### 1.2.2. Configuration Publishing

Configure Fractal for Chinook operations:

```php
// config/fractal.php
return [
    /*
     * The default serializer to be used when transforming data.
     */
    'default_serializer' => \League\Fractal\Serializer\JsonApiSerializer::class,

    /*
     * The default paginator to be used when transforming paginated data.
     */
    'default_paginator' => \League\Fractal\Pagination\IlluminatePaginatorAdapter::class,

    /*
     * League\Fractal\Manager settings
     */
    'auto_includes' => [
        'enabled' => true,
        'request_key' => 'include',
    ],

    'auto_excludes' => [
        'enabled' => true,
        'request_key' => 'exclude',
    ],

    /*
     * Chinook-specific transformer settings
     */
    'chinook' => [
        'cache_transformations' => env('CHINOOK_CACHE_TRANSFORMATIONS', true),
        'cache_ttl' => env('CHINOOK_TRANSFORMATION_CACHE_TTL', 3600),
        'include_metadata' => env('CHINOOK_INCLUDE_METADATA', true),
        'taxonomy_depth' => env('CHINOOK_TAXONOMY_DEPTH', 3),
    ],

    /*
     * Available includes for Chinook entities
     */
    'available_includes' => [
        'tracks' => ['album', 'artist', 'genre', 'media_type', 'taxonomies', 'playlists'],
        'albums' => ['artist', 'tracks', 'genre', 'taxonomies'],
        'artists' => ['albums', 'tracks', 'taxonomies'],
        'customers' => ['invoices', 'playlists', 'support_rep'],
        'playlists' => ['tracks', 'owner', 'taxonomies'],
        'invoices' => ['customer', 'invoice_lines', 'tracks'],
    ],

    /*
     * Default includes for Chinook entities
     */
    'default_includes' => [
        'tracks' => ['album.artist', 'genre'],
        'albums' => ['artist'],
        'artists' => [],
        'customers' => [],
        'playlists' => ['owner'],
        'invoices' => ['customer'],
    ],
];
```

### 1.2.3. Basic Setup

Set up basic Chinook transformer structure:

```php
// app/Transformers/ChinookBaseTransformer.php
<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;
use Illuminate\Support\Facades\Cache;

abstract class ChinookBaseTransformer extends TransformerAbstract
{
    protected int $cacheTime = 3600; // 1 hour default cache
    protected bool $enableCaching = true;

    protected function getCacheKey($data): string
    {
        $class = class_basename(static::class);
        $id = is_object($data) ? $data->getKey() : $data['id'] ?? 'unknown';
        return "chinook_transform:{$class}:{$id}";
    }

    protected function cacheTransformation($data, callable $transformer)
    {
        if (!$this->enableCaching) {
            return $transformer();
        }

        $cacheKey = $this->getCacheKey($data);
        
        return Cache::remember($cacheKey, $this->cacheTime, $transformer);
    }

    protected function formatTimestamp(?\DateTime $timestamp): ?string
    {
        return $timestamp?->format('Y-m-d\TH:i:s\Z');
    }

    protected function formatPrice(?float $price): ?string
    {
        return $price !== null ? '$' . number_format($price, 2) : null;
    }

    protected function formatDuration(?int $milliseconds): ?string
    {
        if (!$milliseconds) {
            return null;
        }
        
        $seconds = intval($milliseconds / 1000);
        $minutes = intval($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    protected function formatFileSize(?int $bytes): ?string
    {
        if (!$bytes) {
            return null;
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 2) . ' ' . $units[$unit];
    }

    protected function transformTaxonomies($taxonomies): array
    {
        if (!$taxonomies) {
            return [];
        }

        return $taxonomies->map(function ($taxonomy) {
            return [
                'id' => $taxonomy->id,
                'name' => $taxonomy->name,
                'slug' => $taxonomy->slug,
                'type' => $taxonomy->type,
                'parent_id' => $taxonomy->parent_id,
            ];
        })->toArray();
    }
}
```

**Environment Configuration:**

```bash
# .env additions for Chinook Fractal
CHINOOK_CACHE_TRANSFORMATIONS=true
CHINOOK_TRANSFORMATION_CACHE_TTL=3600
CHINOOK_INCLUDE_METADATA=true
CHINOOK_TAXONOMY_DEPTH=3

# API response settings
CHINOOK_API_VERSION=v1
CHINOOK_API_PAGINATION_LIMIT=50
CHINOOK_API_MAX_INCLUDES=10
```

## 1.3. Transformer Creation

### 1.3.1. Basic Transformers

Create basic transformers for Chinook entities:

```php
// app/Transformers/ChinookTrackTransformer.php
<?php

namespace App\Transformers;

use App\Models\ChinookTrack;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;

class ChinookTrackTransformer extends ChinookBaseTransformer
{
    protected array $availableIncludes = [
        'album',
        'artist',
        'genre',
        'media_type',
        'taxonomies',
        'playlists',
        'invoice_lines'
    ];

    protected array $defaultIncludes = [
        'album',
        'genre'
    ];

    public function transform(ChinookTrack $track): array
    {
        return $this->cacheTransformation($track, function () use ($track) {
            return [
                'id' => $track->public_id,
                'name' => $track->name,
                'composer' => $track->composer,
                'duration' => $this->formatDuration($track->milliseconds),
                'duration_ms' => $track->milliseconds,
                'file_size' => $this->formatFileSize($track->bytes),
                'file_size_bytes' => $track->bytes,
                'unit_price' => $this->formatPrice($track->unit_price),
                'unit_price_raw' => $track->unit_price,
                'created_at' => $this->formatTimestamp($track->created_at),
                'updated_at' => $this->formatTimestamp($track->updated_at),
                'links' => [
                    'self' => route('api.chinook.tracks.show', $track->public_id),
                    'download' => route('api.chinook.tracks.download', $track->public_id),
                    'stream' => route('api.chinook.tracks.stream', $track->public_id),
                ],
            ];
        });
    }

    public function includeAlbum(ChinookTrack $track): Item
    {
        if (!$track->album) {
            return $this->null();
        }

        return $this->item($track->album, new ChinookAlbumTransformer(), 'albums');
    }

    public function includeArtist(ChinookTrack $track): Item
    {
        if (!$track->album?->artist) {
            return $this->null();
        }

        return $this->item($track->album->artist, new ChinookArtistTransformer(), 'artists');
    }

    public function includeGenre(ChinookTrack $track): Item
    {
        if (!$track->genre) {
            return $this->null();
        }

        return $this->item($track->genre, new ChinookGenreTransformer(), 'genres');
    }

    public function includeMediaType(ChinookTrack $track): Item
    {
        if (!$track->mediaType) {
            return $this->null();
        }

        return $this->item($track->mediaType, new ChinookMediaTypeTransformer(), 'media_types');
    }

    public function includeTaxonomies(ChinookTrack $track): Collection
    {
        return $this->collection($track->taxonomies, new TaxonomyTransformer(), 'taxonomies');
    }

    public function includePlaylists(ChinookTrack $track): Collection
    {
        return $this->collection($track->playlists, new ChinookPlaylistTransformer(), 'playlists');
    }

    public function includeInvoiceLines(ChinookTrack $track): Collection
    {
        return $this->collection($track->invoiceLines, new ChinookInvoiceLineTransformer(), 'invoice_lines');
    }
}
```

```php
// app/Transformers/ChinookAlbumTransformer.php
<?php

namespace App\Transformers;

use App\Models\ChinookAlbum;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;

class ChinookAlbumTransformer extends ChinookBaseTransformer
{
    protected array $availableIncludes = [
        'artist',
        'tracks',
        'taxonomies'
    ];

    protected array $defaultIncludes = [
        'artist'
    ];

    public function transform(ChinookAlbum $album): array
    {
        return $this->cacheTransformation($album, function () use ($album) {
            return [
                'id' => $album->public_id,
                'title' => $album->title,
                'artwork_url' => $album->artwork_url,
                'release_year' => $album->release_year,
                'track_count' => $album->tracks_count ?? $album->tracks->count(),
                'total_duration' => $this->formatDuration($album->tracks->sum('milliseconds')),
                'total_duration_ms' => $album->tracks->sum('milliseconds'),
                'created_at' => $this->formatTimestamp($album->created_at),
                'updated_at' => $this->formatTimestamp($album->updated_at),
                'links' => [
                    'self' => route('api.chinook.albums.show', $album->public_id),
                    'tracks' => route('api.chinook.albums.tracks', $album->public_id),
                ],
            ];
        });
    }

    public function includeArtist(ChinookAlbum $album): Item
    {
        if (!$album->artist) {
            return $this->null();
        }

        return $this->item($album->artist, new ChinookArtistTransformer(), 'artists');
    }

    public function includeTracks(ChinookAlbum $album): Collection
    {
        return $this->collection($album->tracks, new ChinookTrackTransformer(), 'tracks');
    }

    public function includeTaxonomies(ChinookAlbum $album): Collection
    {
        return $this->collection($album->taxonomies, new TaxonomyTransformer(), 'taxonomies');
    }
}
```

### 1.3.2. Advanced Transformers

Implement advanced transformation features for Chinook:

```php
// app/Transformers/ChinookCustomerTransformer.php
<?php

namespace App\Transformers;

use App\Models\ChinookCustomer;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;

class ChinookCustomerTransformer extends ChinookBaseTransformer
{
    protected array $availableIncludes = [
        'invoices',
        'playlists',
        'support_rep',
        'purchase_history',
        'favorite_genres'
    ];

    protected array $defaultIncludes = [];

    // Sensitive fields that should be excluded from public APIs
    protected array $sensitiveFields = [
        'email',
        'phone',
        'fax',
        'address',
        'postal_code'
    ];

    public function transform(ChinookCustomer $customer): array
    {
        $data = $this->cacheTransformation($customer, function () use ($customer) {
            return [
                'id' => $customer->public_id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'full_name' => trim($customer->first_name . ' ' . $customer->last_name),
                'company' => $customer->company,
                'city' => $customer->city,
                'state' => $customer->state,
                'country' => $customer->country,
                'display_location' => $this->formatLocation($customer),
                'total_purchases' => $customer->invoices->sum('total'),
                'total_purchases_formatted' => $this->formatPrice($customer->invoices->sum('total')),
                'invoice_count' => $customer->invoices->count(),
                'playlist_count' => $customer->playlists->count(),
                'last_purchase_date' => $this->formatTimestamp($customer->invoices->max('invoice_date')),
                'created_at' => $this->formatTimestamp($customer->created_at),
                'updated_at' => $this->formatTimestamp($customer->updated_at),
                'links' => [
                    'self' => route('api.chinook.customers.show', $customer->public_id),
                    'invoices' => route('api.chinook.customers.invoices', $customer->public_id),
                    'playlists' => route('api.chinook.customers.playlists', $customer->public_id),
                ],
            ];
        });

        // Include sensitive fields only for authenticated requests
        if ($this->shouldIncludeSensitiveData()) {
            $data = array_merge($data, [
                'email' => $customer->email,
                'phone' => $customer->phone,
                'fax' => $customer->fax,
                'address' => $customer->address,
                'postal_code' => $customer->postal_code,
            ]);
        }

        return $data;
    }

    public function includeInvoices(ChinookCustomer $customer): Collection
    {
        return $this->collection($customer->invoices, new ChinookInvoiceTransformer(), 'invoices');
    }

    public function includePlaylists(ChinookCustomer $customer): Collection
    {
        return $this->collection($customer->playlists, new ChinookPlaylistTransformer(), 'playlists');
    }

    public function includeSupportRep(ChinookCustomer $customer): Item
    {
        if (!$customer->supportRep) {
            return $this->null();
        }

        return $this->item($customer->supportRep, new ChinookEmployeeTransformer(), 'employees');
    }

    public function includePurchaseHistory(ChinookCustomer $customer): Collection
    {
        $recentInvoices = $customer->invoices()
            ->with(['invoiceLines.track.album.artist', 'invoiceLines.track.genre'])
            ->orderBy('invoice_date', 'desc')
            ->limit(10)
            ->get();

        return $this->collection($recentInvoices, new ChinookInvoiceTransformer(), 'invoices');
    }

    public function includeFavoriteGenres(ChinookCustomer $customer): Collection
    {
        $favoriteGenres = $customer->invoices()
            ->join('chinook_invoice_lines', 'chinook_invoices.id', '=', 'chinook_invoice_lines.invoice_id')
            ->join('chinook_tracks', 'chinook_invoice_lines.track_id', '=', 'chinook_tracks.id')
            ->join('chinook_genres', 'chinook_tracks.genre_id', '=', 'chinook_genres.id')
            ->selectRaw('chinook_genres.*, COUNT(*) as purchase_count')
            ->groupBy('chinook_genres.id')
            ->orderBy('purchase_count', 'desc')
            ->limit(5)
            ->get();

        return $this->collection($favoriteGenres, new ChinookGenreTransformer(), 'genres');
    }

    private function formatLocation(ChinookCustomer $customer): ?string
    {
        $parts = array_filter([$customer->city, $customer->state, $customer->country]);
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    private function shouldIncludeSensitiveData(): bool
    {
        // Check if the current user is authenticated and authorized
        return auth()->check() && (
            auth()->user()->can('view-customer-details') ||
            auth()->id() === request()->route('customer')?->id
        );
    }
}
```

### 1.3.3. Nested Transformers

Create complex nested transformers for Chinook operations:

```php
// app/Transformers/ChinookPlaylistTransformer.php
<?php

namespace App\Transformers;

use App\Models\ChinookPlaylist;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;

class ChinookPlaylistTransformer extends ChinookBaseTransformer
{
    protected array $availableIncludes = [
        'owner',
        'tracks',
        'tracks.album',
        'tracks.artist',
        'tracks.genre',
        'taxonomies',
        'collaborators'
    ];

    protected array $defaultIncludes = [
        'owner'
    ];

    public function transform(ChinookPlaylist $playlist): array
    {
        return $this->cacheTransformation($playlist, function () use ($playlist) {
            return [
                'id' => $playlist->public_id,
                'name' => $playlist->name,
                'description' => $playlist->description,
                'is_public' => $playlist->is_public,
                'is_collaborative' => $playlist->is_collaborative,
                'track_count' => $playlist->tracks->count(),
                'total_duration' => $this->formatDuration($playlist->tracks->sum('milliseconds')),
                'total_duration_ms' => $playlist->tracks->sum('milliseconds'),
                'total_size' => $this->formatFileSize($playlist->tracks->sum('bytes')),
                'total_size_bytes' => $playlist->tracks->sum('bytes'),
                'genre_diversity' => $this->calculateGenreDiversity($playlist),
                'created_at' => $this->formatTimestamp($playlist->created_at),
                'updated_at' => $this->formatTimestamp($playlist->updated_at),
                'links' => [
                    'self' => route('api.chinook.playlists.show', $playlist->public_id),
                    'tracks' => route('api.chinook.playlists.tracks', $playlist->public_id),
                    'export' => route('api.chinook.playlists.export', $playlist->public_id),
                ],
            ];
        });
    }

    public function includeOwner(ChinookPlaylist $playlist): Item
    {
        if (!$playlist->customer) {
            return $this->null();
        }

        return $this->item($playlist->customer, new ChinookCustomerTransformer(), 'customers');
    }

    public function includeTracks(ChinookPlaylist $playlist): Collection
    {
        return $this->collection($playlist->tracks, new ChinookTrackTransformer(), 'tracks');
    }

    public function includeTaxonomies(ChinookPlaylist $playlist): Collection
    {
        return $this->collection($playlist->taxonomies, new TaxonomyTransformer(), 'taxonomies');
    }

    public function includeCollaborators(ChinookPlaylist $playlist): Collection
    {
        if (!$playlist->is_collaborative) {
            return $this->collection([], new ChinookCustomerTransformer(), 'customers');
        }

        return $this->collection($playlist->collaborators, new ChinookCustomerTransformer(), 'customers');
    }

    private function calculateGenreDiversity(ChinookPlaylist $playlist): array
    {
        $genres = $playlist->tracks->pluck('genre.name')->filter()->countBy();
        $totalTracks = $playlist->tracks->count();

        return [
            'unique_genres' => $genres->count(),
            'genre_distribution' => $genres->map(function ($count) use ($totalTracks) {
                return [
                    'count' => $count,
                    'percentage' => $totalTracks > 0 ? round(($count / $totalTracks) * 100, 2) : 0,
                ];
            })->toArray(),
        ];
    }
}
```

## 1.4. Resource Management

### 1.4.1. Item Resources

Implement item resource handling for Chinook:

```php
// app/Http/Controllers/Api/ChinookTrackController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChinookTrack;
use App\Transformers\ChinookTrackTransformer;
use Spatie\Fractal\Fractal;
use Illuminate\Http\Request;

class ChinookTrackController extends Controller
{
    public function show(Request $request, string $trackId)
    {
        $track = ChinookTrack::where('public_id', $trackId)
            ->with($this->getEagerLoads($request))
            ->firstOrFail();

        return Fractal::create()
            ->item($track)
            ->transformWith(new ChinookTrackTransformer())
            ->parseIncludes($request->get('include', ''))
            ->parseExcludes($request->get('exclude', ''))
            ->withResourceName('tracks')
            ->respond();
    }

    public function index(Request $request)
    {
        $query = ChinookTrack::query()
            ->with($this->getEagerLoads($request));

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorting
        $this->applySorting($query, $request);

        $tracks = $query->paginate($request->get('per_page', 15));

        return Fractal::create()
            ->collection($tracks->getCollection())
            ->transformWith(new ChinookTrackTransformer())
            ->parseIncludes($request->get('include', ''))
            ->parseExcludes($request->get('exclude', ''))
            ->withResourceName('tracks')
            ->paginateWith(new \League\Fractal\Pagination\IlluminatePaginatorAdapter($tracks))
            ->respond();
    }

    private function getEagerLoads(Request $request): array
    {
        $includes = explode(',', $request->get('include', ''));
        $eagerLoads = [];

        foreach ($includes as $include) {
            $include = trim($include);

            switch ($include) {
                case 'album':
                    $eagerLoads[] = 'album';
                    break;
                case 'artist':
                case 'album.artist':
                    $eagerLoads[] = 'album.artist';
                    break;
                case 'genre':
                    $eagerLoads[] = 'genre';
                    break;
                case 'media_type':
                    $eagerLoads[] = 'mediaType';
                    break;
                case 'taxonomies':
                    $eagerLoads[] = 'taxonomies';
                    break;
                case 'playlists':
                    $eagerLoads[] = 'playlists';
                    break;
            }
        }

        return array_unique($eagerLoads);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->has('genre')) {
            $query->whereHas('genre', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->get('genre') . '%');
            });
        }

        if ($request->has('artist')) {
            $query->whereHas('album.artist', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->get('artist') . '%');
            });
        }

        if ($request->has('album')) {
            $query->whereHas('album', function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->get('album') . '%');
            });
        }

        if ($request->has('min_price')) {
            $query->where('unit_price', '>=', $request->get('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('unit_price', '<=', $request->get('max_price'));
        }

        if ($request->has('taxonomy')) {
            $query->whereHas('taxonomies', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->get('taxonomy') . '%');
            });
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');

        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortDirection);
                break;
            case 'price':
                $query->orderBy('unit_price', $sortDirection);
                break;
            case 'duration':
                $query->orderBy('milliseconds', $sortDirection);
                break;
            case 'album':
                $query->join('chinook_albums', 'chinook_tracks.album_id', '=', 'chinook_albums.id')
                      ->orderBy('chinook_albums.title', $sortDirection);
                break;
            case 'artist':
                $query->join('chinook_albums', 'chinook_tracks.album_id', '=', 'chinook_albums.id')
                      ->join('chinook_artists', 'chinook_albums.artist_id', '=', 'chinook_artists.id')
                      ->orderBy('chinook_artists.name', $sortDirection);
                break;
            default:
                $query->orderBy('name', 'asc');
        }
    }
}
```

### 1.4.2. Collection Resources

Implement collection resource handling with advanced features:

```php
// app/Http/Controllers/Api/ChinookSearchController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChinookTrack;
use App\Models\ChinookAlbum;
use App\Models\ChinookArtist;
use App\Transformers\ChinookTrackTransformer;
use App\Transformers\ChinookAlbumTransformer;
use App\Transformers\ChinookArtistTransformer;
use Spatie\Fractal\Fractal;
use Illuminate\Http\Request;

class ChinookSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $type = $request->get('type', 'all'); // all, tracks, albums, artists
        $limit = min($request->get('limit', 20), 100);

        $results = [];

        if ($type === 'all' || $type === 'tracks') {
            $tracks = $this->searchTracks($query, $limit);
            $results['tracks'] = Fractal::create()
                ->collection($tracks)
                ->transformWith(new ChinookTrackTransformer())
                ->parseIncludes($request->get('include', 'album.artist,genre'))
                ->withResourceName('tracks')
                ->toArray();
        }

        if ($type === 'all' || $type === 'albums') {
            $albums = $this->searchAlbums($query, $limit);
            $results['albums'] = Fractal::create()
                ->collection($albums)
                ->transformWith(new ChinookAlbumTransformer())
                ->parseIncludes($request->get('include', 'artist'))
                ->withResourceName('albums')
                ->toArray();
        }

        if ($type === 'all' || $type === 'artists') {
            $artists = $this->searchArtists($query, $limit);
            $results['artists'] = Fractal::create()
                ->collection($artists)
                ->transformWith(new ChinookArtistTransformer())
                ->parseIncludes($request->get('include', ''))
                ->withResourceName('artists')
                ->toArray();
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'query' => $query,
                'type' => $type,
                'total_results' => array_sum(array_map(function($result) {
                    return count($result['data'] ?? []);
                }, $results)),
            ],
        ]);
    }

    private function searchTracks(string $query, int $limit)
    {
        return ChinookTrack::where('name', 'like', "%{$query}%")
            ->orWhere('composer', 'like', "%{$query}%")
            ->orWhereHas('album', function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%");
            })
            ->orWhereHas('album.artist', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orWhereHas('genre', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orWhereHas('taxonomies', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with(['album.artist', 'genre', 'taxonomies'])
            ->limit($limit)
            ->get();
    }

    private function searchAlbums(string $query, int $limit)
    {
        return ChinookAlbum::where('title', 'like', "%{$query}%")
            ->orWhereHas('artist', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orWhereHas('taxonomies', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with(['artist', 'taxonomies'])
            ->limit($limit)
            ->get();
    }

    private function searchArtists(string $query, int $limit)
    {
        return ChinookArtist::where('name', 'like', "%{$query}%")
            ->orWhereHas('taxonomies', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with(['taxonomies'])
            ->limit($limit)
            ->get();
    }
}
```

## 1.10. Best Practices

### 1.10.1. Security Considerations

Implement security best practices for Chinook transformers:

```php
// app/Transformers/Concerns/ChinookSecurityTrait.php
<?php

namespace App\Transformers\Concerns;

trait ChinookSecurityTrait
{
    protected function sanitizeOutput(array $data): array
    {
        // Remove null values to reduce response size
        $data = array_filter($data, function ($value) {
            return $value !== null;
        });

        // Sanitize string values
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $data;
    }

    protected function checkPermissions(string $permission): bool
    {
        return auth()->check() && auth()->user()->can($permission);
    }

    protected function filterSensitiveData(array $data, array $sensitiveFields): array
    {
        if (!$this->checkPermissions('view-sensitive-data')) {
            foreach ($sensitiveFields as $field) {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
```

### 1.10.2. Error Handling

Implement comprehensive error handling:

```php
// app/Http/Controllers/Api/ChinookApiController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class ChinookApiController extends Controller
{
    protected function handleApiException(\Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'error' => [
                    'type' => 'not_found',
                    'message' => 'The requested Chinook resource was not found.',
                    'code' => 'CHINOOK_RESOURCE_NOT_FOUND',
                ],
            ], 404);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'error' => [
                    'type' => 'not_found',
                    'message' => 'The requested endpoint was not found.',
                    'code' => 'CHINOOK_ENDPOINT_NOT_FOUND',
                ],
            ], 404);
        }

        // Log the error for debugging
        logger()->error('Chinook API Error', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request' => request()->all(),
        ]);

        return response()->json([
            'error' => [
                'type' => 'internal_error',
                'message' => 'An internal error occurred while processing your request.',
                'code' => 'CHINOOK_INTERNAL_ERROR',
            ],
        ], 500);
    }
}
```

## 1.11. Navigation

**← Previous:** [Laravel Data Guide](060-laravel-data-guide.md)

**Next →** [Laravel Sanctum Guide](080-laravel-sanctum-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Fractal implementation guide provides comprehensive API transformation capabilities for the Chinook music store application, including:

- **Advanced Transformers**: Comprehensive transformation layers for tracks, albums, artists, customers, and playlists
- **Flexible Resource Management**: Efficient handling of item and collection resources with relationship loading
- **Security Integration**: Role-based data filtering and sensitive information protection
- **Performance Optimization**: Caching strategies, eager loading, and efficient data processing
- **Taxonomy Integration**: Advanced transformation support for aliziodev/laravel-taxonomy structures
- **Search Capabilities**: Multi-entity search with flexible result transformation
- **API Best Practices**: Comprehensive error handling, security considerations, and response formatting

The implementation leverages Laravel Fractal's advanced capabilities while providing Chinook-specific optimizations for music catalog APIs, customer data management, and business intelligence operations with complete security and performance considerations.
