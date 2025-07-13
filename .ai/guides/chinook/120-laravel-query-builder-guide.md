# 1. Laravel Query Builder Integration Guide

**Refactored from:** `.ai/guides/chinook/120-laravel-query-builder-guide.md` on 2025-07-11

## 1.1 Table of Contents

- [1.2 Overview](#12-overview)
- [1.3 Installation & Configuration](#13-installation--configuration)
- [1.4 Basic Query Building](#14-basic-query-building)
- [1.5 Advanced Filtering](#15-advanced-filtering)
- [1.6 Sorting & Pagination](#16-sorting--pagination)
- [1.7 Relationship Queries](#17-relationship-queries)
- [1.8 Custom Filters](#18-custom-filters)
- [1.9 Performance Optimization](#19-performance-optimization)

## 1.2 Overview

This guide demonstrates the integration of Spatie's Laravel Query Builder package with the Chinook database implementation, providing powerful API query capabilities with filtering, sorting, and relationship inclusion using Laravel 12 modern patterns and exclusive integration with the aliziodev/laravel-taxonomy package.

### 1.2.1 Package Features

**Core Features:**
- **Dynamic Filtering**: URL-based filtering with type safety
- **Flexible Sorting**: Multi-column sorting with direction control
- **Relationship Inclusion**: Eager loading via URL parameters
- **Field Selection**: Sparse fieldsets for optimized responses
- **Pagination Integration**: Seamless pagination support
- **Security**: Built-in protection against unauthorized queries
- **Taxonomy Integration**: Native support for aliziodev/laravel-taxonomy filtering

### 1.2.2 Chinook Integration Benefits

- **API Flexibility**: Rich querying capabilities for frontend applications
- **Performance**: Optimized database queries with eager loading
- **Developer Experience**: Intuitive URL-based query syntax
- **Type Safety**: Laravel 12 casting and validation integration
- **RBAC Integration**: Permission-based query restrictions
- **Taxonomy Filtering**: Seamless taxonomy-based content filtering

## 1.3 Installation & Configuration

### 1.3.1 Package Installation

```bash
composer require spatie/laravel-query-builder
```

### 1.3.2 Configuration Publishing

```bash
php artisan vendor:publish --provider="Spatie\QueryBuilder\QueryBuilderServiceProvider" --tag="config"
```

### 1.3.3 Configuration Setup

```php
// config/query-builder.php
return [
    'parameters' => [
        'include' => 'include',
        'filter' => 'filter',
        'sort' => 'sort',
        'fields' => 'fields',
        'append' => 'append',
    ],
    
    'count_suffix' => 'Count',
    'exists_suffix' => 'Exists',
    
    'disable_invalid_filter_query_exception' => false,
    'disable_invalid_includes_query_exception' => false,
];
```

### 1.3.4 Base Query Builder Setup

```php
// app/Http/Controllers/Api/BaseController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseController extends Controller
{
    protected function buildQuery(string $model): QueryBuilder
    {
        return QueryBuilder::for($model)
            ->allowedIncludes($this->getAllowedIncludes())
            ->allowedFilters($this->getAllowedFilters())
            ->allowedSorts($this->getAllowedSorts())
            ->allowedFields($this->getAllowedFields());
    }
    
    abstract protected function getAllowedIncludes(): array;
    abstract protected function getAllowedFilters(): array;
    abstract protected function getAllowedSorts(): array;
    abstract protected function getAllowedFields(): array;
}
```

## 1.4 Basic Query Building

### 1.4.1 ChinookArtist Controller Implementation

```php
// app/Http/Controllers/Api/ChinookArtistController.php
<?php

namespace App\Http\Controllers\Api;

use App\Models\ChinookArtist;
use App\Http\Resources\ChinookArtistResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Illuminate\Http\JsonResponse;

class ChinookArtistController extends BaseController
{
    public function index(): JsonResponse
    {
        $artists = $this->buildQuery(ChinookArtist::class)
            ->paginate(request('per_page', 15));
        
        return response()->json([
            'data' => ChinookArtistResource::collection($artists->items()),
            'meta' => [
                'current_page' => $artists->currentPage(),
                'total' => $artists->total(),
                'per_page' => $artists->perPage(),
                'last_page' => $artists->lastPage(),
            ]
        ]);
    }
    
    protected function getAllowedIncludes(): array
    {
        return [
            'albums',
            'albums.tracks',
            'terms',
            'terms.taxonomy',
        ];
    }
    
    protected function getAllowedFilters(): array
    {
        return [
            'name',
            'is_active',
            AllowedFilter::exact('id'),
            AllowedFilter::partial('biography'),
            AllowedFilter::scope('active'),
            AllowedFilter::scope('with_albums'),
            AllowedFilter::callback('created_after', function ($query, $value) {
                $query->where('created_at', '>=', $value);
            }),
            AllowedFilter::callback('genre', function ($query, $value) {
                $query->whereHasTerm($value, 'genres');
            }),
            AllowedFilter::callback('taxonomy', function ($query, $value) {
                [$taxonomyName, $termName] = explode(':', $value);
                $query->whereHasTerm($termName, $taxonomyName);
            }),
        ];
    }
    
    protected function getAllowedSorts(): array
    {
        return [
            'name',
            'created_at',
            'updated_at',
            AllowedSort::field('albums_count', 'albums_count'),
        ];
    }
    
    protected function getAllowedFields(): array
    {
        return [
            'chinook_artists.id',
            'chinook_artists.name',
            'chinook_artists.slug',
            'chinook_artists.biography',
            'chinook_artists.website',
            'chinook_artists.is_active',
            'chinook_albums.id',
            'chinook_albums.title',
            'chinook_albums.release_date',
        ];
    }
}
```

### 1.4.2 Basic Query Examples

```bash
# Get all artists
GET /api/chinook/artists

# Get artists with albums included
GET /api/chinook/artists?include=albums

# Filter active artists
GET /api/chinook/artists?filter[is_active]=1

# Search artists by name
GET /api/chinook/artists?filter[name]=Beatles

# Filter by genre using taxonomy
GET /api/chinook/artists?filter[genre]=Rock

# Filter by specific taxonomy term
GET /api/chinook/artists?filter[taxonomy]=genres:Rock

# Sort by name ascending
GET /api/chinook/artists?sort=name

# Sort by creation date descending
GET /api/chinook/artists?sort=-created_at

# Select specific fields
GET /api/chinook/artists?fields[chinook_artists]=id,name,slug

# Combine multiple parameters with taxonomy
GET /api/chinook/artists?include=albums,terms&filter[is_active]=1&filter[genre]=Rock&sort=name
```

## 1.5 Advanced Filtering

### 1.5.1 Custom Filter Types with Taxonomy

```php
// app/Http/Controllers/Api/ChinookAlbumController.php
class ChinookAlbumController extends BaseController
{
    protected function getAllowedFilters(): array
    {
        return [
            'title',
            AllowedFilter::exact('artist_id'),
            AllowedFilter::partial('title', 'title'),
            AllowedFilter::scope('released_after'),
            AllowedFilter::scope('released_before'),
            AllowedFilter::callback('price_range', function ($query, $value) {
                [$min, $max] = explode(',', $value);
                $query->whereBetween('price', [$min, $max]);
            }),
            AllowedFilter::callback('has_tracks', function ($query, $value) {
                if ($value) {
                    $query->has('tracks');
                } else {
                    $query->doesntHave('tracks');
                }
            }),
            // Taxonomy-based filters
            AllowedFilter::callback('genre', function ($query, $value) {
                $query->whereHasTerm($value, 'genres');
            }),
            AllowedFilter::callback('album_type', function ($query, $value) {
                $query->whereHasTerm($value, 'album-types');
            }),
            AllowedFilter::callback('multiple_genres', function ($query, $value) {
                $genres = explode(',', $value);
                $query->whereHasTerms($genres, 'genres');
            }),
        ];
    }
}

// Model scopes for filtering
class ChinookAlbum extends Model
{
    use HasTaxonomies;

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

    public function scopeReleasedAfter($query, $date)
    {
        return $query->where('release_date', '>=', $date);
    }
    
    public function scopeReleasedBefore($query, $date)
    {
        return $query->where('release_date', '<=', $date);
    }
}
```

### 1.5.2 Relationship Filtering with Taxonomy

```php
// Filter artists by album count
AllowedFilter::callback('min_albums', function ($query, $value) {
    $query->has('albums', '>=', $value);
}),

// Filter artists by taxonomy terms
AllowedFilter::callback('has_genre', function ($query, $value) {
    $query->whereHasTerm($value, 'genres');
}),

// Filter by multiple taxonomy criteria
AllowedFilter::callback('taxonomy_filter', function ($query, $value) {
    $filters = explode('|', $value);
    foreach ($filters as $filter) {
        [$taxonomy, $term] = explode(':', $filter);
        $query->whereHasTerm($term, $taxonomy);
    }
}),

// Filter by date range
AllowedFilter::callback('date_range', function ($query, $value) {
    [$start, $end] = explode(',', $value);
    $query->whereBetween('created_at', [$start, $end]);
}),
```

### 1.5.3 Complex Filter Examples

```bash
# Albums released after 2020
GET /api/chinook/albums?filter[released_after]=2020-01-01

# Albums in price range $10-$20
GET /api/chinook/albums?filter[price_range]=10,20

# Artists with at least 3 albums
GET /api/chinook/artists?filter[min_albums]=3

# Albums by genre with tracks included
GET /api/chinook/albums?filter[genre]=Rock&include=tracks

# Artists with specific genre
GET /api/chinook/artists?filter[has_genre]=rock

# Multiple genre filter
GET /api/chinook/albums?filter[multiple_genres]=Rock,Alternative

# Complex taxonomy filter
GET /api/chinook/artists?filter[taxonomy_filter]=genres:Rock|album-types:Studio
```

## 1.6 Sorting & Pagination

### 1.6.1 Advanced Sorting with Taxonomy

```php
protected function getAllowedSorts(): array
{
    return [
        'name',
        'created_at',
        'updated_at',
        AllowedSort::field('albums_count', 'albums_count'),
        AllowedSort::field('latest_album', 'latest_album_date'),
        AllowedSort::callback('popularity', function ($query, $direction) {
            $query->orderBy('play_count', $direction)
                  ->orderBy('download_count', $direction);
        }),
        AllowedSort::callback('genre_popularity', function ($query, $direction) {
            $query->withCount(['terms as genre_count' => function ($q) {
                $q->whereHas('taxonomy', function ($taxonomy) {
                    $taxonomy->where('name', 'Genres');
                });
            }])->orderBy('genre_count', $direction);
        }),
    ];
}
```

### 1.6.2 Sorting Examples

```bash
# Sort by name ascending
GET /api/chinook/artists?sort=name

# Sort by creation date descending
GET /api/chinook/artists?sort=-created_at

# Multiple sort criteria
GET /api/chinook/artists?sort=albums_count,-created_at

# Custom popularity sort
GET /api/chinook/artists?sort=popularity

# Sort by genre diversity
GET /api/chinook/artists?sort=-genre_popularity
```

### 1.6.3 Pagination Configuration

```php
public function index(): JsonResponse
{
    $perPage = min(request('per_page', 15), 100); // Max 100 items

    $artists = $this->buildQuery(ChinookArtist::class)
        ->paginate($perPage);

    return response()->json([
        'data' => ChinookArtistResource::collection($artists->items()),
        'meta' => [
            'current_page' => $artists->currentPage(),
            'total' => $artists->total(),
            'per_page' => $artists->perPage(),
            'last_page' => $artists->lastPage(),
            'from' => $artists->firstItem(),
            'to' => $artists->lastItem(),
        ],
        'links' => [
            'first' => $artists->url(1),
            'last' => $artists->url($artists->lastPage()),
            'prev' => $artists->previousPageUrl(),
            'next' => $artists->nextPageUrl(),
        ]
    ]);
}
```

## 1.7 Relationship Queries

### 1.7.1 Nested Includes with Taxonomy

```php
protected function getAllowedIncludes(): array
{
    return [
        'albums',
        'albums.tracks',
        'albums.tracks.mediaType',
        'terms',
        'terms.taxonomy',
        'albums.terms',
        'albums.tracks.terms',
    ];
}
```

### 1.7.2 Conditional Includes with Taxonomy

```php
AllowedInclude::callback('popular_albums', function ($query) {
    $query->with(['albums' => function ($q) {
        $q->where('sales_count', '>', 1000)
          ->orderBy('sales_count', 'desc')
          ->limit(5);
    }]);
}),

AllowedInclude::callback('rock_albums', function ($query) {
    $query->with(['albums' => function ($q) {
        $q->whereHasTerm('Rock', 'genres')
          ->orderBy('release_date', 'desc');
    }]);
}),

AllowedInclude::callback('recent_tracks', function ($query) {
    $query->with(['albums.tracks' => function ($q) {
        $q->where('created_at', '>=', now()->subMonths(6))
          ->orderBy('created_at', 'desc');
    }]);
}),

AllowedInclude::callback('genre_terms', function ($query) {
    $query->with(['terms' => function ($q) {
        $q->whereHas('taxonomy', function ($taxonomy) {
            $taxonomy->where('name', 'Genres');
        });
    }]);
}),
```

### 1.7.3 Include Examples

```bash
# Include albums with tracks
GET /api/chinook/artists?include=albums.tracks

# Include taxonomy terms
GET /api/chinook/artists?include=terms,terms.taxonomy

# Include nested relationships with taxonomy
GET /api/chinook/artists?include=albums.tracks.terms

# Conditional includes
GET /api/chinook/artists?include=popular_albums,rock_albums

# Genre-specific includes
GET /api/chinook/artists?include=genre_terms
```

## 1.8 Custom Filters

### 1.8.1 Taxonomy-Based Search Filters

```php
// Full-text search filter with taxonomy
AllowedFilter::callback('search', function ($query, $value) {
    $query->where(function ($q) use ($value) {
        $q->where('name', 'LIKE', "%{$value}%")
          ->orWhere('biography', 'LIKE', "%{$value}%")
          ->orWhereHas('albums', function ($albumQuery) use ($value) {
              $albumQuery->where('title', 'LIKE', "%{$value}%");
          })
          ->orWhereHas('terms', function ($termQuery) use ($value) {
              $termQuery->where('name', 'LIKE', "%{$value}%");
          });
    });
}),

// Taxonomy-based search
AllowedFilter::callback('taxonomy_search', function ($query, $value) {
    [$taxonomyName, $searchTerm] = explode(':', $value);

    $query->whereHas('terms', function ($q) use ($taxonomyName, $searchTerm) {
        $q->where('name', 'LIKE', "%{$searchTerm}%")
          ->whereHas('taxonomy', function ($taxonomy) use ($taxonomyName) {
              $taxonomy->where('name', $taxonomyName);
          });
    });
}),

// Multiple taxonomy filter
AllowedFilter::callback('taxonomies', function ($query, $value) {
    $taxonomyFilters = explode(',', $value);

    foreach ($taxonomyFilters as $filter) {
        [$taxonomyName, $termName] = explode(':', $filter);
        $query->whereHasTerm($termName, $taxonomyName);
    }
}),
```

### 1.8.2 Date Range Filters

```php
// Flexible date range filtering
AllowedFilter::callback('date_range', function ($query, $value) {
    $ranges = explode(',', $value);

    if (count($ranges) === 1) {
        // Single date - filter by day
        $query->whereDate('created_at', $ranges[0]);
    } elseif (count($ranges) === 2) {
        // Date range
        $query->whereBetween('created_at', $ranges);
    }
}),

// Relative date filters
AllowedFilter::callback('created_within', function ($query, $value) {
    $date = match($value) {
        'today' => now()->startOfDay(),
        'week' => now()->subWeek(),
        'month' => now()->subMonth(),
        'year' => now()->subYear(),
        default => now()->sub($value),
    };

    $query->where('created_at', '>=', $date);
}),
```

### 1.8.3 Advanced Filter Examples

```bash
# Full-text search including taxonomy terms
GET /api/chinook/artists?filter[search]=rock

# Search within specific taxonomy
GET /api/chinook/artists?filter[taxonomy_search]=genres:alternative

# Multiple taxonomy filters
GET /api/chinook/artists?filter[taxonomies]=genres:Rock,album-types:Studio

# Date range filtering
GET /api/chinook/artists?filter[date_range]=2020-01-01,2023-12-31

# Relative date filtering
GET /api/chinook/artists?filter[created_within]=month
```

## 1.9 Performance Optimization

### 1.9.1 Query Optimization with Taxonomy

```php
// Optimize with select and counts
public function index(): JsonResponse
{
    $artists = QueryBuilder::for(ChinookArtist::class)
        ->select(['id', 'name', 'slug', 'biography', 'is_active'])
        ->withCount(['albums', 'tracks', 'terms'])
        ->allowedIncludes($this->getAllowedIncludes())
        ->allowedFilters($this->getAllowedFilters())
        ->allowedSorts($this->getAllowedSorts())
        ->paginate(request('per_page', 15));

    return ChinookArtistResource::collection($artists);
}
```

### 1.9.2 Caching Strategies

```php
// Cache expensive queries with taxonomy
public function index(): JsonResponse
{
    $cacheKey = 'chinook_artists:' . md5(request()->getQueryString());

    $artists = Cache::remember($cacheKey, 300, function () {
        return $this->buildQuery(ChinookArtist::class)
            ->paginate(request('per_page', 15));
    });

    return ChinookArtistResource::collection($artists);
}

// Cache taxonomy relationship counts
class ChinookArtist extends Model
{
    use HasTaxonomies;

    protected $withCount = ['albums', 'terms'];

    public function getGenreCountAttribute(): int
    {
        return Cache::remember(
            "artist.{$this->id}.genre_count",
            3600,
            fn() => $this->getTermsByTaxonomy('Genres')->count()
        );
    }
}
```

### 1.9.3 Database Indexing

```php
// Migration for query optimization
Schema::table('chinook_artists', function (Blueprint $table) {
    $table->index(['is_active', 'created_at']);
    $table->index(['name', 'is_active']);
    $table->fullText(['name', 'biography']); // For search queries
});

Schema::table('chinook_albums', function (Blueprint $table) {
    $table->index(['artist_id', 'release_date']);
    $table->index(['is_active']);
});

// Taxonomy-specific indexes
Schema::table('termables', function (Blueprint $table) {
    $table->index(['termable_type', 'termable_id']);
    $table->index(['term_id', 'termable_type']);
});

Schema::table('terms', function (Blueprint $table) {
    $table->index(['taxonomy_id', 'name']);
    $table->index(['slug']);
});
```

### 1.9.4 Performance Monitoring

```php
// Query performance middleware
class QueryPerformanceMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        DB::enableQueryLog();

        $response = $next($request);

        $endTime = microtime(true);
        $queryCount = count(DB::getQueryLog());
        $executionTime = ($endTime - $startTime) * 1000;

        if ($executionTime > 1000 || $queryCount > 10) {
            Log::warning('Slow query detected', [
                'url' => $request->fullUrl(),
                'execution_time' => $executionTime,
                'query_count' => $queryCount,
                'taxonomy_filters' => $request->get('filter', []),
            ]);
        }

        return $response;
    }
}
```

---

**Next**: [Comprehensive Data Access Guide](130-comprehensive-data-access-guide.md) | **Previous**: [Authentication Flow Guide](110-authentication-flow.md)

---

*This guide demonstrates powerful query building capabilities for the Chinook system using Laravel 12, Spatie Query Builder, and the aliziodev/laravel-taxonomy package for advanced filtering.*

[⬆️ Back to Top](#1-laravel-query-builder-integration-guide)
