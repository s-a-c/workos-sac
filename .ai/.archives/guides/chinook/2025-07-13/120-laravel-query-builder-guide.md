# Laravel Query Builder Integration Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Installation & Configuration](#2-installation--configuration)
- [3. Basic Query Building](#3-basic-query-building)
- [4. Advanced Filtering](#4-advanced-filtering)
- [5. Sorting & Pagination](#5-sorting--pagination)
- [6. Relationship Queries](#6-relationship-queries)
- [7. Custom Filters](#7-custom-filters)
- [8. Performance Optimization](#8-performance-optimization)

## 1. Overview

This guide demonstrates the integration of Spatie's Laravel Query Builder package with the Chinook database implementation, providing powerful API query capabilities with filtering, sorting, and relationship inclusion using Laravel 12 modern patterns.

### 1.1. Package Features

**Core Features:**
- **Dynamic Filtering**: URL-based filtering with type safety
- **Flexible Sorting**: Multi-column sorting with direction control
- **Relationship Inclusion**: Eager loading via URL parameters
- **Field Selection**: Sparse fieldsets for optimized responses
- **Pagination Integration**: Seamless pagination support
- **Security**: Built-in protection against unauthorized queries

### 1.2. Chinook Integration Benefits

- **API Flexibility**: Rich querying capabilities for frontend applications
- **Performance**: Optimized database queries with eager loading
- **Developer Experience**: Intuitive URL-based query syntax
- **Type Safety**: Laravel 12 casting and validation integration
- **RBAC Integration**: Permission-based query restrictions

## 2. Installation & Configuration

### 2.1. Package Installation

```bash
composer require spatie/laravel-query-builder
```

### 2.2. Configuration Publishing

```bash
php artisan vendor:publish --provider="Spatie\QueryBuilder\QueryBuilderServiceProvider" --tag="config"
```

### 2.3. Configuration Setup

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

### 2.4. Base Query Builder Setup

```php
// app/Http/Controllers/Api/BaseController.php
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

## 3. Basic Query Building

### 3.1. Artist Controller Implementation

```php
// app/Http/Controllers/Api/ArtistController.php
class ArtistController extends BaseController
{
    public function index(): JsonResponse
    {
        $artists = $this->buildQuery(Artist::class)
            ->paginate(request('per_page', 15));
        
        return response()->json([
            'data' => ArtistResource::collection($artists->items()),
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
            'categories',
            'tags',
        ];
    }
    
    protected function getAllowedFilters(): array
    {
        return [
            'name',
            'is_active',
            AllowedFilter::exact('id'),
            AllowedFilter::partial('bio'),
            AllowedFilter::scope('active'),
            AllowedFilter::scope('with_albums'),
            AllowedFilter::callback('created_after', function ($query, $value) {
                $query->where('created_at', '>=', $value);
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
            'artists.id',
            'artists.name',
            'artists.slug',
            'artists.bio',
            'artists.website',
            'artists.is_active',
            'albums.id',
            'albums.title',
            'albums.release_date',
        ];
    }
}
```

### 3.2. Basic Query Examples

```bash
# Get all artists
GET /api/artists

# Get artists with albums included
GET /api/artists?include=albums

# Filter active artists
GET /api/artists?filter[is_active]=1

# Search artists by name
GET /api/artists?filter[name]=Beatles

# Sort by name ascending
GET /api/artists?sort=name

# Sort by creation date descending
GET /api/artists?sort=-created_at

# Select specific fields
GET /api/artists?fields[artists]=id,name,slug

# Combine multiple parameters
GET /api/artists?include=albums&filter[is_active]=1&sort=name&fields[artists]=id,name
```

## 4. Advanced Filtering

### 4.1. Custom Filter Types

```php
// app/Http/Controllers/Api/AlbumController.php
class AlbumController extends BaseController
{
    protected function getAllowedFilters(): array
    {
        return [
            'title',
            'genre',
            AllowedFilter::exact('artist_id'),
            AllowedFilter::partial('title', 'title'),
            AllowedFilter::scope('released_after'),
            AllowedFilter::scope('released_before'),
            AllowedFilter::scope('by_genre'),
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
        ];
    }
}

// Model scopes for filtering
class Album extends Model
{
    public function scopeReleasedAfter($query, $date)
    {
        return $query->where('release_date', '>=', $date);
    }
    
    public function scopeReleasedBefore($query, $date)
    {
        return $query->where('release_date', '<=', $date);
    }
    
    public function scopeByGenre($query, $genre)
    {
        return $query->where('genre', $genre);
    }
}
```

### 4.2. Relationship Filtering

```php
// Filter artists by album count
AllowedFilter::callback('min_albums', function ($query, $value) {
    $query->has('albums', '>=', $value);
}),

// Filter artists by category
AllowedFilter::callback('category', function ($query, $value) {
    $query->whereHas('categories', function ($q) use ($value) {
        $q->where('slug', $value);
    });
}),

// Filter by date range
AllowedFilter::callback('date_range', function ($query, $value) {
    [$start, $end] = explode(',', $value);
    $query->whereBetween('created_at', [$start, $end]);
}),
```

### 4.3. Complex Filter Examples

```bash
# Albums released after 2020
GET /api/albums?filter[released_after]=2020-01-01

# Albums in price range $10-$20
GET /api/albums?filter[price_range]=10,20

# Artists with at least 3 albums
GET /api/artists?filter[min_albums]=3

# Albums by genre with tracks included
GET /api/albums?filter[by_genre]=Rock&include=tracks

# Artists in specific category
GET /api/artists?filter[category]=rock-legends
```

## 5. Sorting & Pagination

### 5.1. Advanced Sorting

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
    ];
}
```

### 5.2. Sorting Examples

```bash
# Sort by name ascending
GET /api/artists?sort=name

# Sort by creation date descending
GET /api/artists?sort=-created_at

# Multiple sort criteria
GET /api/artists?sort=albums_count,-created_at

# Custom popularity sort
GET /api/artists?sort=popularity
```

### 5.3. Pagination Configuration

```php
public function index(): JsonResponse
{
    $perPage = min(request('per_page', 15), 100); // Max 100 items
    
    $artists = $this->buildQuery(Artist::class)
        ->paginate($perPage);
    
    return response()->json([
        'data' => ArtistResource::collection($artists->items()),
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

## 6. Relationship Queries

### 6.1. Nested Includes

```php
protected function getAllowedIncludes(): array
{
    return [
        'albums',
        'albums.tracks',
        'albums.tracks.mediaType',
        'albums.tracks.categories',
        'categories',
        'categories.parent',
        'tags',
    ];
}
```

### 6.2. Conditional Includes

```php
AllowedInclude::callback('popular_albums', function ($query) {
    $query->with(['albums' => function ($q) {
        $q->where('sales_count', '>', 1000)
          ->orderBy('sales_count', 'desc')
          ->limit(5);
    }]);
}),

AllowedInclude::callback('recent_tracks', function ($query) {
    $query->with(['albums.tracks' => function ($q) {
        $q->where('created_at', '>=', now()->subMonths(6))
          ->orderBy('created_at', 'desc');
    }]);
}),
```

### 6.3. Include Examples

```bash
# Include albums with tracks
GET /api/artists?include=albums.tracks

# Include categories and tags
GET /api/artists?include=categories,tags

# Include nested relationships
GET /api/artists?include=albums.tracks.mediaType

# Conditional includes
GET /api/artists?include=popular_albums,recent_tracks
```

## 7. Custom Filters

### 7.1. Geographic Filters

```php
// Custom filter for location-based queries
AllowedFilter::callback('near_location', function ($query, $value) {
    [$lat, $lng, $radius] = explode(',', $value);
    
    $query->selectRaw("
        *, (
            6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(latitude))
            )
        ) AS distance
    ", [$lat, $lng, $lat])
    ->having('distance', '<', $radius)
    ->orderBy('distance');
}),
```

### 7.2. Date Range Filters

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

### 7.3. Search Filters

```php
// Full-text search filter
AllowedFilter::callback('search', function ($query, $value) {
    $query->where(function ($q) use ($value) {
        $q->where('name', 'LIKE', "%{$value}%")
          ->orWhere('bio', 'LIKE', "%{$value}%")
          ->orWhereHas('albums', function ($albumQuery) use ($value) {
              $albumQuery->where('title', 'LIKE', "%{$value}%");
          });
    });
}),

// Tag-based search
AllowedFilter::callback('tags', function ($query, $value) {
    $tags = explode(',', $value);
    
    $query->whereHas('tags', function ($q) use ($tags) {
        $q->whereIn('name', $tags);
    }, '>=', count($tags)); // Must have all tags
}),
```

## 8. Performance Optimization

### 8.1. Query Optimization

```php
// Optimize with select and counts
public function index(): JsonResponse
{
    $artists = QueryBuilder::for(Artist::class)
        ->select(['id', 'name', 'slug', 'bio', 'is_active'])
        ->withCount(['albums', 'tracks'])
        ->allowedIncludes($this->getAllowedIncludes())
        ->allowedFilters($this->getAllowedFilters())
        ->allowedSorts($this->getAllowedSorts())
        ->paginate(request('per_page', 15));
    
    return ArtistResource::collection($artists);
}
```

### 8.2. Caching Strategies

```php
// Cache expensive queries
public function index(): JsonResponse
{
    $cacheKey = 'artists:' . md5(request()->getQueryString());
    
    $artists = Cache::remember($cacheKey, 300, function () {
        return $this->buildQuery(Artist::class)
            ->paginate(request('per_page', 15));
    });
    
    return ArtistResource::collection($artists);
}

// Cache relationship counts
class Artist extends Model
{
    protected $withCount = ['albums'];
    
    public function getAlbumsCountAttribute($value)
    {
        return Cache::remember(
            "artist.{$this->id}.albums_count",
            3600,
            fn() => $this->albums()->count()
        );
    }
}
```

### 8.3. Database Indexing

```php
// Migration for query optimization
Schema::table('artists', function (Blueprint $table) {
    $table->index(['is_active', 'created_at']);
    $table->index(['name', 'is_active']);
    $table->fullText(['name', 'bio']); // For search queries
});

Schema::table('albums', function (Blueprint $table) {
    $table->index(['artist_id', 'release_date']);
    $table->index(['genre', 'is_active']);
});
```

### 8.4. Performance Monitoring

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
            ]);
        }
        
        return $response;
    }
}
```

---

**Next Steps:**
- [Frontend Integration](frontend/180-api-testing-guide.md) - Frontend API integration patterns
- [Performance Testing](testing/090-performance-testing-guide.md) - Query performance testing

**Related Documentation:**
- [Model Implementation](010-chinook-models-guide.md) - Model structure and relationships
- [Advanced Features](050-chinook-advanced-features-guide.md) - Advanced query patterns
