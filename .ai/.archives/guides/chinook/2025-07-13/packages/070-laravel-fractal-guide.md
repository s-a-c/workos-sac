# Laravel Fractal Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. Configuration Publishing](#12-configuration-publishing)
  - [1.3. Basic Setup](#13-basic-setup)
- [Transformer Creation](#transformer-creation)
  - [2.1. Basic Transformers](#21-basic-transformers)
  - [2.2. Advanced Transformers](#22-advanced-transformers)
  - [2.3. Nested Transformers](#23-nested-transformers)
- [Resource Management](#resource-management)
  - [3.1. Item Resources](#31-item-resources)
  - [3.2. Collection Resources](#32-collection-resources)
  - [3.3. Relationship Handling](#33-relationship-handling)
- [Pagination & Filtering](#pagination--filtering)
  - [4.1. Pagination Setup](#41-pagination-setup)
  - [4.2. Advanced Filtering](#42-advanced-filtering)
  - [4.3. Sorting & Ordering](#43-sorting--ordering)
- [API Response Formatting](#api-response-formatting)
  - [5.1. Response Structure](#51-response-structure)
  - [5.2. Custom Serializers](#52-custom-serializers)
- [Caching Integration](#caching-integration)
  - [6.1. Response Caching](#61-response-caching)
  - [6.2. Cache Invalidation](#62-cache-invalidation)
- [Performance Optimization](#performance-optimization)
  - [7.1. Eager Loading](#71-eager-loading)
  - [7.2. Lazy Loading Prevention](#72-lazy-loading-prevention)
- [Testing Strategies](#testing-strategies)
  - [8.1. Transformer Testing](#81-transformer-testing)
  - [8.2. API Response Testing](#82-api-response-testing)
- [Best Practices](#best-practices)
  - [9.1. Security Considerations](#91-security-considerations)
  - [9.2. Error Handling](#92-error-handling)
- [Navigation](#navigation)

## Overview

Laravel Fractal provides advanced API transformation layers with flexible resource management, relationship handling, and comprehensive pagination support. This guide covers enterprise-level implementation with caching integration, performance optimization, and modern API patterns.

**🚀 Key Features:**
- **Flexible Transformations**: Customizable data transformation with includes/excludes
- **Relationship Handling**: Efficient nested resource loading and transformation
- **Pagination Support**: Built-in pagination with metadata and navigation links
- **Caching Integration**: Response caching for improved performance
- **API Versioning**: Support for multiple API versions and backward compatibility
- **Performance Optimization**: Lazy loading and efficient data processing

## Installation & Setup

### 1.1. Package Installation

Install Laravel Fractal using Composer:

```bash
# Install Laravel Fractal
composer require spatie/laravel-fractal

# Publish configuration (optional)
php artisan vendor:publish --provider="Spatie\Fractal\FractalServiceProvider"
```

### 1.2. Configuration Publishing

Publish and configure the Fractal configuration file:

```bash
# Publish configuration
php artisan vendor:publish --tag=fractal-config
```

Configuration file (`config/fractal.php`):

```php
<?php

return [
    /*
     * The default serializer to be used when performing a transformation.
     */
    'default_serializer' => \League\Fractal\Serializer\DataArraySerializer::class,

    /*
     * The default paginator to be used when transforming paginated results.
     */
    'default_paginator' => \League\Fractal\Paginator\IlluminatePaginatorAdapter::class,

    /*
     * League\Fractal\Manager settings
     */
    'auto_includes' => [
        'request_key' => 'include',
    ],
];
```

### 1.3. Basic Setup

Create a base transformer class for consistent API responses:

```php
<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;

abstract class BaseTransformer extends TransformerAbstract
{
    /**
     * Common fields for all models
     */
    protected function getBaseFields($model): array
    {
        return [
            'id' => $model->public_id ?? $model->id,
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
        ];
    }

    /**
     * Include user stamps if available
     */
    protected function getUserStamps($model): array
    {
        $stamps = [];

        if ($model->created_by) {
            $stamps['created_by'] = [
                'id' => $model->createdBy->public_id,
                'name' => $model->createdBy->name,
            ];
        }

        if ($model->updated_by) {
            $stamps['updated_by'] = [
                'id' => $model->updatedBy->public_id,
                'name' => $model->updatedBy->name,
            ];
        }

        return $stamps;
    }
}
```

## Transformer Creation

### 2.1. Basic Transformers

Create transformers for core Chinook models:

```php
<?php

namespace App\Transformers;

use App\Models\Artist;

class ArtistTransformer extends BaseTransformer
{
    /**
     * Available includes
     */
    protected array $availableIncludes = [
        'albums',
        'categories',
        'media',
    ];

    /**
     * Default includes
     */
    protected array $defaultIncludes = [
        'categories',
    ];

    /**
     * Transform artist data
     */
    public function transform(Artist $artist): array
    {
        return array_merge($this->getBaseFields($artist), [
            'name' => $artist->name,
            'slug' => $artist->slug,
            'bio' => $artist->bio,
            'website' => $artist->website,
            'formed_year' => $artist->formed_year,
            'albums_count' => $artist->albums_count ?? $artist->albums()->count(),
        ], $this->getUserStamps($artist));
    }

    /**
     * Include albums
     */
    public function includeAlbums(Artist $artist)
    {
        return $this->collection($artist->albums, new AlbumTransformer);
    }

    /**
     * Include categories
     */
    public function includeCategories(Artist $artist)
    {
        return $this->collection($artist->categories, new CategoryTransformer);
    }

    /**
     * Include media
     */
    public function includeMedia(Artist $artist)
    {
        return $this->collection($artist->getMedia(), new MediaTransformer);
    }
}
```

### 2.2. Advanced Transformers

Album transformer with complex relationships:

```php
<?php

namespace App\Transformers;

use App\Models\Album;

class AlbumTransformer extends BaseTransformer
{
    protected array $availableIncludes = [
        'artist',
        'tracks',
        'categories',
        'media',
        'sales_stats',
    ];

    protected array $defaultIncludes = [
        'artist',
    ];

    public function transform(Album $album): array
    {
        return array_merge($this->getBaseFields($album), [
            'title' => $album->title,
            'slug' => $album->slug,
            'release_date' => $album->release_date?->toDateString(),
            'unit_price' => $album->unit_price,
            'tracks_count' => $album->tracks_count ?? $album->tracks()->count(),
            'total_duration' => $album->total_duration,
            'cover_art_url' => $album->getFirstMediaUrl('covers'),
        ], $this->getUserStamps($album));
    }

    public function includeArtist(Album $album)
    {
        return $this->item($album->artist, new ArtistTransformer);
    }

    public function includeTracks(Album $album)
    {
        return $this->collection($album->tracks, new TrackTransformer);
    }

    public function includeSalesStats(Album $album)
    {
        return $this->item($album, function (Album $album) {
            return [
                'total_sales' => $album->invoiceLines()->sum('quantity'),
                'revenue' => $album->invoiceLines()->sum('total'),
                'last_sale' => $album->invoiceLines()
                    ->latest('created_at')
                    ->first()?->created_at?->toISOString(),
            ];
        });
    }
}
```

### 2.3. Nested Transformers

Track transformer with deep nesting:

```php
<?php

namespace App\Transformers;

use App\Models\Track;

class TrackTransformer extends BaseTransformer
{
    protected array $availableIncludes = [
        'album',
        'album.artist',
        'media_type',
        'categories',
        'playlists',
        'invoice_lines',
    ];

    public function transform(Track $track): array
    {
        return array_merge($this->getBaseFields($track), [
            'name' => $track->name,
            'slug' => $track->slug,
            'composer' => $track->composer,
            'duration' => [
                'milliseconds' => $track->milliseconds,
                'formatted' => $track->formatted_duration,
            ],
            'file_size' => [
                'bytes' => $track->bytes,
                'formatted' => $track->formatted_file_size,
            ],
            'unit_price' => $track->unit_price,
            'track_number' => $track->track_number,
            'audio_url' => $track->getFirstMediaUrl('audio'),
        ], $this->getUserStamps($track));
    }

    public function includeAlbum(Track $track)
    {
        return $this->item($track->album, new AlbumTransformer);
    }

    public function includeMediaType(Track $track)
    {
        return $this->item($track->mediaType, new MediaTypeTransformer);
    }
}
```

## Resource Management

### 3.1. Item Resources

Transform single model instances:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Artist;
use App\Transformers\ArtistTransformer;
use Spatie\Fractal\Fractal;

class ArtistController extends Controller
{
    public function show(Artist $artist)
    {
        return Fractal::create()
            ->item($artist)
            ->transformWith(ArtistTransformer::class)
            ->includeFromRequest()
            ->respond();
    }

    public function update(Request $request, Artist $artist)
    {
        $artist->update($request->validated());

        return Fractal::create()
            ->item($artist->fresh())
            ->transformWith(ArtistTransformer::class)
            ->respond(200);
    }
}
```

### 3.2. Collection Resources

Transform collections with pagination:

```php
public function index(Request $request)
{
    $artists = Artist::query()
        ->withCount(['albums', 'tracks'])
        ->with(['categories'])
        ->when($request->search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })
        ->when($request->category, function ($query, $category) {
            $query->whereHas('categories', function ($q) use ($category) {
                $q->where('slug', $category);
            });
        })
        ->paginate($request->per_page ?? 15);

    return Fractal::create()
        ->collection($artists)
        ->transformWith(ArtistTransformer::class)
        ->paginateWith(new IlluminatePaginatorAdapter($artists))
        ->includeFromRequest()
        ->respond();
}
```

### 3.3. Relationship Handling

Handle complex nested relationships:

```php
public function albums(Artist $artist)
{
    $albums = $artist->albums()
        ->with(['tracks', 'categories'])
        ->withCount('tracks')
        ->paginate(10);

    return Fractal::create()
        ->collection($albums)
        ->transformWith(AlbumTransformer::class)
        ->paginateWith(new IlluminatePaginatorAdapter($albums))
        ->respond();
}

public function tracks(Artist $artist)
{
    $tracks = $artist->tracks()
        ->with(['album', 'mediaType', 'categories'])
        ->orderBy('album_id')
        ->orderBy('track_number')
        ->paginate(20);

    return Fractal::create()
        ->collection($tracks)
        ->transformWith(TrackTransformer::class)
        ->paginateWith(new IlluminatePaginatorAdapter($tracks))
        ->respond();
}
```

## Pagination & Filtering

### 4.1. Pagination Setup

Configure pagination with metadata:

```php
<?php

namespace App\Http\Controllers\Api;

use League\Fractal\Pagination\IlluminatePaginatorAdapter;

class BaseApiController extends Controller
{
    protected function paginatedResponse($query, $transformer, $perPage = 15)
    {
        $paginator = $query->paginate($perPage);

        return Fractal::create()
            ->collection($paginator)
            ->transformWith($transformer)
            ->paginateWith(new IlluminatePaginatorAdapter($paginator))
            ->addMeta([
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ])
            ->respond();
    }
}
```

### 4.2. Advanced Filtering

Implement complex filtering logic:

```php
public function index(Request $request)
{
    $query = Track::query()
        ->with(['album.artist', 'mediaType', 'categories'])
        ->withCount('invoiceLines');

    // Genre filtering
    if ($request->genre) {
        $query->whereHas('categories', function ($q) use ($request) {
            $q->where('type', CategoryType::GENRE)
              ->where('slug', $request->genre);
        });
    }

    // Price range filtering
    if ($request->min_price || $request->max_price) {
        $query->whereBetween('unit_price', [
            $request->min_price ?? 0,
            $request->max_price ?? 999.99
        ]);
    }

    // Duration filtering
    if ($request->min_duration || $request->max_duration) {
        $query->whereBetween('milliseconds', [
            ($request->min_duration ?? 0) * 1000,
            ($request->max_duration ?? 1800) * 1000
        ]);
    }

    // Artist filtering
    if ($request->artist) {
        $query->whereHas('album.artist', function ($q) use ($request) {
            $q->where('slug', $request->artist);
        });
    }

    return $this->paginatedResponse(
        $query,
        TrackTransformer::class,
        $request->per_page ?? 20
    );
}
```

### 4.3. Sorting & Ordering

Implement flexible sorting:

```php
public function index(Request $request)
{
    $query = Album::query()
        ->with(['artist', 'categories'])
        ->withCount(['tracks', 'invoiceLines']);

    // Dynamic sorting
    $sortField = $request->sort ?? 'title';
    $sortDirection = $request->direction ?? 'asc';

    switch ($sortField) {
        case 'artist':
            $query->join('artists', 'albums.artist_id', '=', 'artists.id')
                  ->orderBy('artists.name', $sortDirection)
                  ->select('albums.*');
            break;

        case 'release_date':
            $query->orderBy('release_date', $sortDirection);
            break;

        case 'tracks_count':
            $query->orderBy('tracks_count', $sortDirection);
            break;

        case 'popularity':
            $query->orderBy('invoice_lines_count', $sortDirection);
            break;

        default:
            $query->orderBy($sortField, $sortDirection);
    }

    return $this->paginatedResponse($query, AlbumTransformer::class);
}

# Install additional dependencies for enhanced features
composer require league/fractal

# Verify installation
php artisan fractal:check
```

**System Requirements:**

- PHP 8.1 or higher
- Laravel 9.0 or higher
- League Fractal 0.20 or higher

## API Response Formatting

Laravel Fractal provides flexible response formatting with customizable serializers and metadata handling.

### 5.1. Response Structure

Configure response structure and metadata:

```php
// app/Http/Controllers/Api/ArtistController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Transformers\ArtistTransformer;
use Illuminate\Http\Request;
use Spatie\Fractal\Fractal;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $artists = Artist::with(['albums', 'categories'])
            ->paginate(15);

        return Fractal::create()
            ->collection($artists->getCollection(), new ArtistTransformer())
            ->withResourceName('artists')
            ->addMeta([
                'pagination' => [
                    'total' => $artists->total(),
                    'count' => $artists->count(),
                    'per_page' => $artists->perPage(),
                    'current_page' => $artists->currentPage(),
                    'total_pages' => $artists->lastPage(),
                    'links' => [
                        'next' => $artists->nextPageUrl(),
                        'prev' => $artists->previousPageUrl(),
                    ],
                ],
                'api_version' => '1.0',
                'generated_at' => now()->toISOString(),
            ])
            ->respond();
    }

    public function show(Artist $artist)
    {
        return Fractal::create()
            ->item($artist, new ArtistTransformer())
            ->withResourceName('artist')
            ->parseIncludes(request('include', ''))
            ->addMeta([
                'api_version' => '1.0',
                'generated_at' => now()->toISOString(),
            ])
            ->respond();
    }
}
```

### 5.2. Custom Serializers

Create custom serializers for specific response formats:

```php
// app/Serializers/CustomArraySerializer.php
<?php

namespace App\Serializers;

use League\Fractal\Serializer\ArraySerializer;

class CustomArraySerializer extends ArraySerializer
{
    public function collection(?string $resourceKey, array $data): array
    {
        return [
            'data' => $data,
            'meta' => [
                'resource_type' => $resourceKey,
                'count' => count($data),
            ],
        ];
    }

    public function item(?string $resourceKey, array $data): array
    {
        return [
            'data' => $data,
            'meta' => [
                'resource_type' => $resourceKey,
            ],
        ];
    }
}
```

## Caching Integration

Implement response caching for improved performance and reduced database load.

### 6.1. Response Caching

Cache transformed responses:

```php
// app/Http/Controllers/Api/ArtistController.php
use Illuminate\Support\Facades\Cache;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'artists_' . md5($request->getQueryString());

        return Cache::remember($cacheKey, 3600, function () use ($request) {
            $artists = Artist::with(['albums', 'categories'])
                ->paginate(15);

            return Fractal::create()
                ->collection($artists->getCollection(), new ArtistTransformer())
                ->withResourceName('artists')
                ->parseIncludes($request->get('include', ''))
                ->respond();
        });
    }
}
```

### 6.2. Cache Invalidation

Implement cache invalidation strategies:

```php
// app/Models/Artist.php
use Illuminate\Support\Facades\Cache;

class Artist extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($artist) {
            Cache::tags(['artists'])->flush();
        });

        static::deleted(function ($artist) {
            Cache::tags(['artists'])->flush();
        });
    }
}
```

## Performance Optimization

Optimize Fractal transformations for high-performance applications.

### 7.1. Eager Loading

Optimize database queries with eager loading:

```php
// app/Transformers/ArtistTransformer.php
public function includeAlbums(Artist $artist)
{
    // Ensure albums are already loaded
    if (!$artist->relationLoaded('albums')) {
        $artist->load('albums.tracks');
    }

    return $this->collection($artist->albums, new AlbumTransformer());
}
```

### 7.2. Lazy Loading Prevention

Prevent N+1 queries:

```php
// app/Http/Controllers/Api/ArtistController.php
public function index(Request $request)
{
    $includes = explode(',', $request->get('include', ''));

    $query = Artist::query();

    if (in_array('albums', $includes)) {
        $query->with('albums');
    }

    if (in_array('albums.tracks', $includes)) {
        $query->with('albums.tracks');
    }

    $artists = $query->paginate(15);

    return Fractal::create()
        ->collection($artists->getCollection(), new ArtistTransformer())
        ->parseIncludes($request->get('include', ''))
        ->respond();
}
```

## Testing Strategies

Comprehensive testing approaches for Fractal transformations.

### 8.1. Transformer Testing

Test transformer output:

```php
// tests/Unit/Transformers/ArtistTransformerTest.php
<?php

namespace Tests\Unit\Transformers;

use App\Models\Artist;
use App\Transformers\ArtistTransformer;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArtistTransformerTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_transformation_structure()
    {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'country' => 'USA',
        ]);

        $transformer = new ArtistTransformer();
        $result = $transformer->transform($artist);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('public_id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('country', $result);
        $this->assertEquals('Test Artist', $result['name']);
        $this->assertEquals('USA', $result['country']);
    }
}
```

### 8.2. API Response Testing

Test complete API responses:

```php
// tests/Feature/Api/ArtistApiTest.php
<?php

namespace Tests\Feature\Api;

use App\Models\Artist;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArtistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_index_response_structure()
    {
        Artist::factory()->count(3)->create();

        $response = $this->getJson('/api/artists');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'public_id',
                        'name',
                        'country',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'pagination' => [
                        'total',
                        'count',
                        'per_page',
                        'current_page',
                        'total_pages',
                    ],
                ],
            ]);
    }
}
```

## Best Practices

Enterprise-level best practices for Laravel Fractal implementation.

### 9.1. Security Considerations

Implement security measures:

```php
// app/Http/Middleware/ApiRateLimit.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

class ApiRateLimit
{
    public function handle(Request $request, Closure $next, $maxAttempts = 60)
    {
        $key = $request->ip();

        if (app(RateLimiter::class)->tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => app(RateLimiter::class)->availableIn($key),
            ], 429);
        }

        app(RateLimiter::class)->hit($key);

        return $next($request);
    }
}
```

### 9.2. Error Handling

Implement comprehensive error handling:

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($request->expectsJson()) {
        return response()->json([
            'error' => [
                'message' => $exception->getMessage(),
                'type' => class_basename($exception),
                'code' => $exception->getCode(),
            ],
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ], $this->getStatusCode($exception));
    }

    return parent::render($request, $exception);
}
```

## Navigation

**Previous**: [Laravel Data Guide](060-laravel-data-guide.md) | **Next**: [Laravel Sanctum Guide](080-laravel-sanctum-guide.md) | **Index**: [Package Index](000-packages-index.md)

---

*This guide provides comprehensive Laravel Fractal implementation with enterprise-grade features, performance optimization, and modern API patterns for the Chinook music database application.*
