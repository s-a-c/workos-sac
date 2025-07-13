# Scopes and Filters Guide

## Table of Contents

- [Overview](#overview)
- [Query Scopes](#query-scopes)
- [Dynamic Scopes](#dynamic-scopes)
- [Filter Classes](#filter-classes)
- [Advanced Filtering](#advanced-filtering)
- [Search Functionality](#search-functionality)
- [Performance Optimization](#performance-optimization)
- [Testing Scopes and Filters](#testing-scopes-and-filters)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive query scopes and filtering patterns for Laravel 12 models in the Chinook application. The system provides reusable query logic, dynamic filtering capabilities, and advanced search functionality with performance optimization.

**🚀 Key Features:**
- **Reusable Query Logic**: Encapsulated query patterns in scopes
- **Dynamic Filtering**: Flexible filter system for complex queries
- **Search Integration**: Full-text search with relevance scoring
- **Performance Optimized**: Efficient filtering with proper indexing
- **WCAG 2.1 AA Compliance**: Accessible filtering interfaces

## Query Scopes

### Basic Query Scopes

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Enums\CategoryType;

class Artist extends Model
{
    /**
     * Scope for active artists
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured artists
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
            ->where('is_active', true);
    }

    /**
     * Scope for artists with albums
     */
    public function scopeWithAlbums(Builder $query): Builder
    {
        return $query->whereHas('albums');
    }

    /**
     * Scope for artists by genre
     */
    public function scopeByGenre(Builder $query, string $genre): Builder
    {
        return $query->whereHas('categories', function ($q) use ($genre) {
            $q->where('type', CategoryType::GENRE)
              ->where('name', $genre);
        });
    }

    /**
     * Scope for artists created in date range
     */
    public function scopeCreatedBetween(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for popular artists (with album count)
     */
    public function scopePopular(Builder $query, int $minAlbums = 3): Builder
    {
        return $query->withCount('albums')
            ->having('albums_count', '>=', $minAlbums)
            ->orderByDesc('albums_count');
    }

    /**
     * Scope for artists with recent activity
     */
    public function scopeRecentlyActive(Builder $query, int $days = 30): Builder
    {
        return $query->where(function ($q) use ($days) {
            $q->where('updated_at', '>=', now()->subDays($days))
              ->orWhereHas('albums', function ($albumQuery) use ($days) {
                  $albumQuery->where('created_at', '>=', now()->subDays($days));
              });
        });
    }

    /**
     * Scope for search by name
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'LIKE', "%{$term}%")
            ->orWhere('bio', 'LIKE', "%{$term}%");
    }

    /**
     * Scope for ordering by relevance
     */
    public function scopeOrderByRelevance(Builder $query, string $term): Builder
    {
        return $query->orderByRaw("
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN name LIKE ? THEN 2
                WHEN bio LIKE ? THEN 3
                ELSE 4
            END
        ", [
            "{$term}%",
            "%{$term}%",
            "%{$term}%"
        ]);
    }
}
```

### Advanced Query Scopes

```php
<?php
// app/Models/Album.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Album extends Model
{
    /**
     * Scope for albums by release year
     */
    public function scopeByYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('release_date', $year);
    }

    /**
     * Scope for albums by decade
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
     * Scope for albums with minimum duration
     */
    public function scopeMinDuration(Builder $query, int $minutes): Builder
    {
        return $query->whereHas('tracks', function ($q) use ($minutes) {
            $q->selectRaw('SUM(duration_ms) as total_duration')
              ->groupBy('album_id')
              ->havingRaw('SUM(duration_ms) >= ?', [$minutes * 60 * 1000]);
        });
    }

    /**
     * Scope for albums with track count
     */
    public function scopeWithTrackCount(Builder $query, int $min = null, int $max = null): Builder
    {
        $query->withCount('tracks');
        
        if ($min !== null) {
            $query->having('tracks_count', '>=', $min);
        }
        
        if ($max !== null) {
            $query->having('tracks_count', '<=', $max);
        }
        
        return $query;
    }

    /**
     * Scope for albums by rating range
     */
    public function scopeByRating(Builder $query, float $minRating, float $maxRating = null): Builder
    {
        $query->where('average_rating', '>=', $minRating);
        
        if ($maxRating !== null) {
            $query->where('average_rating', '<=', $maxRating);
        }
        
        return $query;
    }

    /**
     * Scope for albums with specific categories
     */
    public function scopeWithCategories(Builder $query, array $categoryIds): Builder
    {
        return $query->whereHas('categories', function ($q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        });
    }

    /**
     * Scope for albums excluding categories
     */
    public function scopeWithoutCategories(Builder $query, array $categoryIds): Builder
    {
        return $query->whereDoesntHave('categories', function ($q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        });
    }
}
```

## Dynamic Scopes

### Dynamic Scope Builder

```php
<?php
// app/Services/DynamicScopeBuilder.php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DynamicScopeBuilder
{
    protected Builder $query;
    protected array $filters = [];

    public function __construct(string $modelClass)
    {
        $this->query = $modelClass::query();
    }

    /**
     * Add filter condition
     */
    public function addFilter(string $field, mixed $value, string $operator = '='): self
    {
        if ($value !== null && $value !== '') {
            $this->filters[] = [$field, $operator, $value];
            $this->query->where($field, $operator, $value);
        }

        return $this;
    }

    /**
     * Add date range filter
     */
    public function addDateRange(string $field, ?string $startDate, ?string $endDate): self
    {
        if ($startDate && $endDate) {
            $this->query->whereBetween($field, [$startDate, $endDate]);
        } elseif ($startDate) {
            $this->query->where($field, '>=', $startDate);
        } elseif ($endDate) {
            $this->query->where($field, '<=', $endDate);
        }

        return $this;
    }

    /**
     * Add search filter
     */
    public function addSearch(array $fields, string $term): self
    {
        if (!empty($term)) {
            $this->query->where(function ($q) use ($fields, $term) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$term}%");
                }
            });
        }

        return $this;
    }

    /**
     * Add relationship filter
     */
    public function addRelationshipFilter(string $relation, string $field, mixed $value): self
    {
        if ($value !== null && $value !== '') {
            $this->query->whereHas($relation, function ($q) use ($field, $value) {
                $q->where($field, $value);
            });
        }

        return $this;
    }

    /**
     * Add category filter
     */
    public function addCategoryFilter(array $categoryIds): self
    {
        if (!empty($categoryIds)) {
            $this->query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        return $this;
    }

    /**
     * Add sorting
     */
    public function addSort(string $field, string $direction = 'asc'): self
    {
        $this->query->orderBy($field, $direction);
        return $this;
    }

    /**
     * Get the built query
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get applied filters
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Execute query with pagination
     */
    public function paginate(int $perPage = 15): mixed
    {
        return $this->query->paginate($perPage);
    }

    /**
     * Execute query and get results
     */
    public function get(): mixed
    {
        return $this->query->get();
    }
}
```

## Filter Classes

### Base Filter Class

```php
<?php
// app/Filters/BaseFilter.php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class BaseFilter
{
    protected Request $request;
    protected Builder $query;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply filters to query
     */
    public function apply(Builder $query): Builder
    {
        $this->query = $query;

        foreach ($this->getFilters() as $filter => $value) {
            if ($this->hasFilter($filter) && method_exists($this, $filter)) {
                $this->$filter($value);
            }
        }

        return $this->query;
    }

    /**
     * Get filters from request
     */
    protected function getFilters(): array
    {
        return $this->request->all();
    }

    /**
     * Check if filter exists and has value
     */
    protected function hasFilter(string $filter): bool
    {
        $value = $this->request->get($filter);
        return $value !== null && $value !== '';
    }

    /**
     * Get filter value
     */
    protected function getFilterValue(string $filter): mixed
    {
        return $this->request->get($filter);
    }
}
```

### Artist Filter Implementation

```php
<?php
// app/Filters/ArtistFilter.php

namespace App\Filters;

class ArtistFilter extends BaseFilter
{
    /**
     * Filter by active status
     */
    public function active(bool $active): void
    {
        $this->query->where('is_active', $active);
    }

    /**
     * Filter by featured status
     */
    public function featured(bool $featured): void
    {
        if ($featured) {
            $this->query->where('is_featured', true);
        }
    }

    /**
     * Filter by search term
     */
    public function search(string $term): void
    {
        $this->query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('bio', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Filter by genre
     */
    public function genre(string $genre): void
    {
        $this->query->whereHas('categories', function ($q) use ($genre) {
            $q->where('type', 'genre')
              ->where('name', $genre);
        });
    }

    /**
     * Filter by creation date range
     */
    public function created_from(string $date): void
    {
        $this->query->where('created_at', '>=', $date);
    }

    public function created_to(string $date): void
    {
        $this->query->where('created_at', '<=', $date);
    }

    /**
     * Filter by minimum album count
     */
    public function min_albums(int $count): void
    {
        $this->query->withCount('albums')
            ->having('albums_count', '>=', $count);
    }

    /**
     * Sort by specified field
     */
    public function sort(string $field): void
    {
        $direction = $this->getFilterValue('direction') ?: 'asc';
        
        $allowedFields = ['name', 'created_at', 'updated_at', 'albums_count'];
        
        if (in_array($field, $allowedFields)) {
            if ($field === 'albums_count') {
                $this->query->withCount('albums')->orderBy('albums_count', $direction);
            } else {
                $this->query->orderBy($field, $direction);
            }
        }
    }
}
```

## Advanced Filtering

### Complex Filter Combinations

```php
<?php
// app/Services/AdvancedFilterService.php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdvancedFilterService
{
    /**
     * Apply complex filter combinations
     */
    public function applyComplexFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $filterGroup) {
            $query->where(function ($q) use ($filterGroup) {
                $this->applyFilterGroup($q, $filterGroup);
            });
        }

        return $query;
    }

    /**
     * Apply filter group with AND/OR logic
     */
    protected function applyFilterGroup(Builder $query, array $filterGroup): void
    {
        $logic = $filterGroup['logic'] ?? 'and';
        $conditions = $filterGroup['conditions'] ?? [];

        foreach ($conditions as $index => $condition) {
            $method = $index === 0 ? 'where' : ($logic === 'or' ? 'orWhere' : 'where');

            $this->applyCondition($query, $condition, $method);
        }
    }

    /**
     * Apply individual condition
     */
    protected function applyCondition(Builder $query, array $condition, string $method): void
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        switch ($operator) {
            case 'equals':
                $query->$method($field, '=', $value);
                break;

            case 'not_equals':
                $query->$method($field, '!=', $value);
                break;

            case 'contains':
                $query->$method($field, 'LIKE', "%{$value}%");
                break;

            case 'starts_with':
                $query->$method($field, 'LIKE', "{$value}%");
                break;

            case 'ends_with':
                $query->$method($field, 'LIKE', "%{$value}");
                break;

            case 'in':
                $query->$method($field, 'IN', (array) $value);
                break;

            case 'not_in':
                $query->$method($field, 'NOT IN', (array) $value);
                break;

            case 'between':
                $query->$method($field, 'BETWEEN', $value);
                break;

            case 'greater_than':
                $query->$method($field, '>', $value);
                break;

            case 'less_than':
                $query->$method($field, '<', $value);
                break;

            case 'is_null':
                $query->$method($field, 'IS', null);
                break;

            case 'is_not_null':
                $query->$method($field, 'IS NOT', null);
                break;
        }
    }

    /**
     * Apply relationship filters
     */
    public function applyRelationshipFilters(Builder $query, array $relationshipFilters): Builder
    {
        foreach ($relationshipFilters as $relation => $filters) {
            $query->whereHas($relation, function ($q) use ($filters) {
                $this->applyComplexFilters($q, $filters);
            });
        }

        return $query;
    }

    /**
     * Apply aggregation filters
     */
    public function applyAggregationFilters(Builder $query, array $aggregationFilters): Builder
    {
        foreach ($aggregationFilters as $filter) {
            $relation = $filter['relation'];
            $aggregation = $filter['aggregation']; // count, sum, avg, min, max
            $field = $filter['field'] ?? '*';
            $operator = $filter['operator'];
            $value = $filter['value'];

            $query->whereHas($relation, function ($q) use ($aggregation, $field, $operator, $value) {
                $q->selectRaw("{$aggregation}({$field}) as agg_value")
                  ->groupBy($q->getModel()->getKeyName())
                  ->havingRaw("agg_value {$operator} ?", [$value]);
            });
        }

        return $query;
    }
}
```

## Search Functionality

### Full-Text Search Implementation

```php
<?php
// app/Services/SearchService.php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SearchService
{
    /**
     * Perform full-text search across multiple models
     */
    public function globalSearch(string $term, array $models = []): array
    {
        $results = [];

        $defaultModels = [
            'artists' => Artist::class,
            'albums' => Album::class,
            'tracks' => Track::class,
            'categories' => Category::class,
        ];

        $searchModels = empty($models) ? $defaultModels : $models;

        foreach ($searchModels as $key => $modelClass) {
            $results[$key] = $this->searchModel($modelClass, $term);
        }

        return $results;
    }

    /**
     * Search within a specific model
     */
    public function searchModel(string $modelClass, string $term): Collection
    {
        $searchFields = $this->getSearchFields($modelClass);

        return $modelClass::where(function ($query) use ($searchFields, $term) {
            foreach ($searchFields as $field) {
                $query->orWhere($field, 'LIKE', "%{$term}%");
            }
        })
        ->orderByRaw($this->getRelevanceOrder($searchFields, $term))
        ->limit(50)
        ->get();
    }

    /**
     * Get searchable fields for model
     */
    protected function getSearchFields(string $modelClass): array
    {
        $searchFields = [
            Artist::class => ['name', 'bio'],
            Album::class => ['title', 'description'],
            Track::class => ['name', 'lyrics'],
            Category::class => ['name', 'description'],
        ];

        return $searchFields[$modelClass] ?? ['name'];
    }

    /**
     * Generate relevance ordering SQL
     */
    protected function getRelevanceOrder(array $fields, string $term): string
    {
        $cases = [];
        $priority = 1;

        foreach ($fields as $field) {
            $cases[] = "WHEN {$field} LIKE '{$term}%' THEN {$priority}";
            $priority++;
            $cases[] = "WHEN {$field} LIKE '%{$term}%' THEN {$priority}";
            $priority++;
        }

        $cases[] = "ELSE {$priority}";

        return "CASE " . implode(' ', $cases) . " END";
    }

    /**
     * Advanced search with filters
     */
    public function advancedSearch(array $criteria): Collection
    {
        $modelClass = $criteria['model'] ?? Artist::class;
        $term = $criteria['term'] ?? '';
        $filters = $criteria['filters'] ?? [];
        $sort = $criteria['sort'] ?? [];

        $query = $modelClass::query();

        // Apply search term
        if (!empty($term)) {
            $searchFields = $this->getSearchFields($modelClass);
            $query->where(function ($q) use ($searchFields, $term) {
                foreach ($searchFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$term}%");
                }
            });
        }

        // Apply filters
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        // Apply sorting
        if (!empty($sort)) {
            foreach ($sort as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        } elseif (!empty($term)) {
            // Default relevance sorting
            $searchFields = $this->getSearchFields($modelClass);
            $query->orderByRaw($this->getRelevanceOrder($searchFields, $term));
        }

        return $query->get();
    }

    /**
     * Search with autocomplete suggestions
     */
    public function autocomplete(string $term, string $modelClass, int $limit = 10): array
    {
        $searchFields = $this->getSearchFields($modelClass);
        $primaryField = $searchFields[0] ?? 'name';

        return $modelClass::where($primaryField, 'LIKE', "{$term}%")
            ->orderBy($primaryField)
            ->limit($limit)
            ->pluck($primaryField)
            ->toArray();
    }

    /**
     * Get search statistics
     */
    public function getSearchStats(string $term): array
    {
        $stats = [];

        $models = [
            'artists' => Artist::class,
            'albums' => Album::class,
            'tracks' => Track::class,
            'categories' => Category::class,
        ];

        foreach ($models as $key => $modelClass) {
            $searchFields = $this->getSearchFields($modelClass);

            $count = $modelClass::where(function ($query) use ($searchFields, $term) {
                foreach ($searchFields as $field) {
                    $query->orWhere($field, 'LIKE', "%{$term}%");
                }
            })->count();

            $stats[$key] = $count;
        }

        return $stats;
    }
}
```

## Performance Optimization

### Filter Performance Optimizer

```php
<?php
// app/Services/FilterPerformanceOptimizer.php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class FilterPerformanceOptimizer
{
    /**
     * Cache filter results
     */
    public function cacheFilterResults(string $cacheKey, Builder $query, int $ttl = 3600): mixed
    {
        return Cache::remember($cacheKey, $ttl, function () use ($query) {
            return $query->get();
        });
    }

    /**
     * Generate cache key for filters
     */
    public function generateCacheKey(string $model, array $filters): string
    {
        $filterString = http_build_query($filters);
        return "filter_results_{$model}_" . md5($filterString);
    }

    /**
     * Optimize filter query with indexes
     */
    public function optimizeFilterQuery(Builder $query, array $filters): Builder
    {
        // Reorder filters to use indexes efficiently
        $indexedFilters = $this->getIndexedFilters($query->getModel());

        foreach ($indexedFilters as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
                unset($filters[$filter]);
            }
        }

        // Apply remaining filters
        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        return $query;
    }

    /**
     * Get indexed fields for model
     */
    protected function getIndexedFilters(string $model): array
    {
        $indexedFields = [
            Artist::class => ['is_active', 'is_featured', 'created_at'],
            Album::class => ['artist_id', 'is_active', 'release_date'],
            Track::class => ['album_id', 'is_active', 'duration_ms'],
            Category::class => ['type', 'is_active', 'parent_id'],
        ];

        return $indexedFields[$model] ?? [];
    }

    /**
     * Batch process filters for large datasets
     */
    public function batchProcessFilters(Builder $query, array $filters, int $batchSize = 1000): \Generator
    {
        $query = $this->optimizeFilterQuery($query, $filters);

        $query->chunk($batchSize, function ($results) {
            yield $results;
        });
    }
}
```

## Testing Scopes and Filters

### Comprehensive Test Suite

```php
<?php
// tests/Feature/ScopesAndFiltersTest.php

use App\Models\Artist;
use App\Models\Album;
use App\Filters\ArtistFilter;
use App\Services\SearchService;
use Tests\TestCase;

class ScopesAndFiltersTest extends TestCase
{
    public function test_active_scope(): void
    {
        Artist::factory()->create(['is_active' => true]);
        Artist::factory()->create(['is_active' => false]);

        $activeArtists = Artist::active()->get();

        $this->assertCount(1, $activeArtists);
        $this->assertTrue($activeArtists->first()->is_active);
    }

    public function test_search_scope(): void
    {
        Artist::factory()->create(['name' => 'The Beatles']);
        Artist::factory()->create(['name' => 'Led Zeppelin']);
        Artist::factory()->create(['bio' => 'Beatles tribute band']);

        $results = Artist::search('Beatles')->get();

        $this->assertCount(2, $results);
    }

    public function test_artist_filter(): void
    {
        $request = new \Illuminate\Http\Request([
            'active' => true,
            'search' => 'Rock',
            'min_albums' => 2
        ]);

        $filter = new ArtistFilter($request);
        $query = $filter->apply(Artist::query());

        $this->assertStringContains('is_active', $query->toSql());
        $this->assertStringContains('LIKE', $query->toSql());
    }

    public function test_search_service(): void
    {
        Artist::factory()->create(['name' => 'Rock Artist']);
        Album::factory()->create(['title' => 'Rock Album']);

        $searchService = app(SearchService::class);
        $results = $searchService->globalSearch('Rock');

        $this->assertArrayHasKey('artists', $results);
        $this->assertArrayHasKey('albums', $results);
        $this->assertCount(1, $results['artists']);
        $this->assertCount(1, $results['albums']);
    }

    public function test_autocomplete_search(): void
    {
        Artist::factory()->create(['name' => 'The Beatles']);
        Artist::factory()->create(['name' => 'The Rolling Stones']);
        Artist::factory()->create(['name' => 'Led Zeppelin']);

        $searchService = app(SearchService::class);
        $suggestions = $searchService->autocomplete('The', Artist::class, 5);

        $this->assertCount(2, $suggestions);
        $this->assertContains('The Beatles', $suggestions);
        $this->assertContains('The Rolling Stones', $suggestions);
    }
}
```

## Best Practices

### Scopes and Filters Guidelines

1. **Reusable Logic**: Encapsulate common query patterns in scopes
2. **Performance**: Use indexes for frequently filtered fields
3. **Caching**: Cache expensive filter results
4. **Validation**: Validate filter inputs to prevent SQL injection
5. **Testing**: Write comprehensive tests for all scopes and filters
6. **Documentation**: Document complex filter logic clearly

### Implementation Checklist

```php
<?php
// Scopes and filters implementation checklist

/*
✓ Create reusable query scopes for common patterns
✓ Implement dynamic scope builder for flexible filtering
✓ Create filter classes for complex filter logic
✓ Add full-text search functionality
✓ Implement autocomplete search features
✓ Optimize filter performance with caching
✓ Add database indexes for filtered fields
✓ Write comprehensive test suite
✓ Document filter usage and examples
✓ Set up performance monitoring for filters
✓ Implement filter validation and sanitization
✓ Create user-friendly filter interfaces
*/
```

## Navigation

**← Previous:** [Performance Optimization Guide](110-performance-optimization.md)
**Next →** [Accessors and Mutators Guide](130-accessors-mutators.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Query Optimization](110-performance-optimization.md) - Performance considerations
- [Global Search](../features/090-global-search.md) - Advanced search patterns

---

*This guide provides comprehensive query scopes and filtering patterns for Laravel 12 models in the Chinook application. The system includes reusable query logic, dynamic filtering capabilities, and advanced search functionality with performance optimization.*
