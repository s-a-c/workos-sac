# Global Search Implementation Guide

This guide covers implementing comprehensive global search functionality across all Chinook admin panel resources, including cross-resource search, advanced filtering, and performance optimization.

## Table of Contents

- [Overview](#overview)
- [Basic Global Search Setup](#basic-global-search-setup)
- [Resource-Specific Configuration](#resource-specific-configuration)
- [Advanced Search Features](#advanced-search-features)
- [Search Result Customization](#search-result-customization)
- [Performance Optimization](#performance-optimization)
- [Search Analytics](#search-analytics)

## Overview

Global search in the Chinook admin panel enables administrators to quickly find artists, albums, tracks, customers, and other entities across all resources from a single search interface.

### Search Capabilities

- **Cross-Resource Search**: Search across all resources simultaneously
- **Intelligent Ranking**: Results ranked by relevance and user permissions
- **Quick Actions**: Direct actions from search results
- **Search History**: Track and suggest previous searches
- **Advanced Filters**: Filter by resource type, date ranges, and categories

## Basic Global Search Setup

### Panel Configuration

Enable global search in the panel provider:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;

class ChinookAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('chinook-admin')
            ->path('chinook-admin')
            // ... other configuration
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->globalSearchDebounce('500ms');
    }
}
```

### Global Search Service

Create a dedicated service for search functionality:

```php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class GlobalSearchService
{
    protected array $searchableResources = [
        \App\Filament\ChinookAdmin\Resources\ArtistResource::class,
        \App\Filament\ChinookAdmin\Resources\AlbumResource::class,
        \App\Filament\ChinookAdmin\Resources\TrackResource::class,
        \App\Filament\ChinookAdmin\Resources\CustomerResource::class,
        \App\Filament\ChinookAdmin\Resources\PlaylistResource::class,
    ];

    public function search(string $query, ?string $resourceType = null, int $limit = 50): Collection
    {
        $cacheKey = 'global_search.' . md5($query . $resourceType . $limit);
        
        return Cache::remember($cacheKey, 300, function () use ($query, $resourceType, $limit) {
            $results = collect();
            
            foreach ($this->getSearchableResources($resourceType) as $resource) {
                if (auth()->user()->can('view', $resource::getModel())) {
                    $resourceResults = $this->searchResource($resource, $query, $limit);
                    $results = $results->merge($resourceResults);
                }
            }
            
            return $results->sortByDesc('relevance_score')->take($limit);
        });
    }

    protected function searchResource(string $resource, string $query, int $limit): Collection
    {
        $model = $resource::getModel();
        $searchableAttributes = $resource::getGloballySearchableAttributes();
        
        $queryBuilder = $model::query();
        
        // Apply global search query
        $queryBuilder = $resource::getGlobalSearchEloquentQuery($queryBuilder);
        
        // Build search conditions
        $queryBuilder->where(function (Builder $builder) use ($searchableAttributes, $query) {
            foreach ($searchableAttributes as $attribute) {
                if (str_contains($attribute, '.')) {
                    // Handle relationship attributes
                    $this->addRelationshipSearch($builder, $attribute, $query);
                } else {
                    // Handle direct attributes
                    $builder->orWhere($attribute, 'LIKE', "%{$query}%");
                }
            }
        });
        
        return $queryBuilder->limit($limit)->get()->map(function ($record) use ($resource, $query) {
            return [
                'record' => $record,
                'resource' => $resource,
                'title' => $resource::getGlobalSearchResultTitle($record),
                'details' => $resource::getGlobalSearchResultDetails($record),
                'url' => $resource::getGlobalSearchResultUrl($record),
                'relevance_score' => $this->calculateRelevanceScore($record, $query),
            ];
        });
    }

    protected function addRelationshipSearch(Builder $builder, string $attribute, string $query): void
    {
        [$relation, $column] = explode('.', $attribute, 2);
        
        $builder->orWhereHas($relation, function (Builder $relationBuilder) use ($column, $query) {
            if (str_contains($column, '.')) {
                $this->addRelationshipSearch($relationBuilder, $column, $query);
            } else {
                $relationBuilder->where($column, 'LIKE', "%{$query}%");
            }
        });
    }

    protected function calculateRelevanceScore($record, string $query): float
    {
        $score = 0;
        
        // Exact matches get highest score
        if (stripos($record->name ?? '', $query) === 0) {
            $score += 100;
        } elseif (stripos($record->name ?? '', $query) !== false) {
            $score += 50;
        }
        
        // Recent records get bonus points
        if ($record->created_at && $record->created_at->isAfter(now()->subDays(30))) {
            $score += 10;
        }
        
        // Popular records get bonus points (if applicable)
        if (method_exists($record, 'getPopularityScore')) {
            $score += $record->getPopularityScore();
        }
        
        return $score;
    }

    protected function getSearchableResources(?string $resourceType): array
    {
        if ($resourceType) {
            return array_filter($this->searchableResources, function ($resource) use ($resourceType) {
                return $resource::getModelLabel() === $resourceType;
            });
        }
        
        return $this->searchableResources;
    }
}
```

## Resource-Specific Configuration

### Artist Resource Search Configuration

Configure comprehensive search for the Artist resource:

```php
<?php

namespace App\Filament\ChinookAdmin\Resources;

use App\Models\Artist;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['albums', 'categories'])
            ->withCount(['albums', 'tracks']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'biography',
            'country',
            'albums.title',
            'albums.tracks.name',
            'categories.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [
            'Type' => 'Artist',
            'Country' => $record->country,
        ];

        if ($record->albums_count > 0) {
            $details['Albums'] = $record->albums_count;
        }

        if ($record->tracks_count > 0) {
            $details['Tracks'] = $record->tracks_count;
        }

        if ($record->categories->isNotEmpty()) {
            $details['Genres'] = $record->categories
                ->where('type', 'genre')
                ->pluck('name')
                ->join(', ');
        }

        return $details;
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            'view' => [
                'label' => 'View Artist',
                'url' => static::getUrl('view', ['record' => $record]),
                'icon' => 'heroicon-o-eye',
            ],
            'edit' => [
                'label' => 'Edit Artist',
                'url' => static::getUrl('edit', ['record' => $record]),
                'icon' => 'heroicon-o-pencil',
                'visible' => auth()->user()->can('update', $record),
            ],
        ];
    }
}
```

### Track Resource Search Configuration

Configure detailed search for tracks with album and artist information:

```php
<?php

namespace App\Filament\ChinookAdmin\Resources;

use App\Models\Track;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrackResource extends Resource
{
    protected static ?string $model = Track::class;

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['album.artist', 'mediaType', 'categories'])
            ->withCount(['invoiceLines']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'composer',
            'album.title',
            'album.artist.name',
            'categories.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [
            'Type' => 'Track',
            'Artist' => $record->album->artist->name ?? 'Unknown',
            'Album' => $record->album->title ?? 'Unknown',
        ];

        if ($record->composer) {
            $details['Composer'] = $record->composer;
        }

        if ($record->milliseconds) {
            $minutes = floor($record->milliseconds / 60000);
            $seconds = floor(($record->milliseconds % 60000) / 1000);
            $details['Duration'] = sprintf('%d:%02d', $minutes, $seconds);
        }

        if ($record->unit_price) {
            $details['Price'] = '$' . number_format($record->unit_price, 2);
        }

        if ($record->invoice_lines_count > 0) {
            $details['Sales'] = $record->invoice_lines_count . ' purchases';
        }

        return $details;
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }
}
```

## Advanced Search Features

### Search Filters Component

Create a component for advanced search filtering:

```php
<?php

namespace App\Filament\ChinookAdmin\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;

class GlobalSearchFilters extends Component
{
    protected string $view = 'filament.chinook-admin.components.global-search-filters';

    public static function make(): static
    {
        return app(static::class);
    }

    public function getSchema(): array
    {
        return [
            Select::make('resource_type')
                ->label('Resource Type')
                ->options([
                    'artists' => 'Artists',
                    'albums' => 'Albums', 
                    'tracks' => 'Tracks',
                    'customers' => 'Customers',
                    'playlists' => 'Playlists',
                ])
                ->placeholder('All Resources'),

            Select::make('category')
                ->label('Category')
                ->relationship('categories', 'name')
                ->searchable()
                ->placeholder('Any Category'),

            DatePicker::make('created_after')
                ->label('Created After')
                ->placeholder('Any Date'),

            DatePicker::make('created_before')
                ->label('Created Before')
                ->placeholder('Any Date'),

            TextInput::make('country')
                ->label('Country')
                ->placeholder('Any Country'),
        ];
    }
}
```

### Search History Tracking

Track and suggest previous searches:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    protected $fillable = [
        'user_id',
        'query',
        'resource_type',
        'results_count',
        'clicked_result',
    ];

    protected function casts(): array
    {
        return [
            'clicked_result' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recordSearch(string $query, ?string $resourceType, int $resultsCount): void
    {
        static::create([
            'user_id' => auth()->id(),
            'query' => $query,
            'resource_type' => $resourceType,
            'results_count' => $resultsCount,
        ]);
    }

    public static function getPopularSearches(int $limit = 10): \Illuminate\Support\Collection
    {
        return static::select('query')
            ->selectRaw('COUNT(*) as search_count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('query');
    }

    public static function getUserRecentSearches(int $limit = 5): \Illuminate\Support\Collection
    {
        return static::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('query');
    }
}
```

## Search Result Customization

### Custom Search Results View

Create a custom view for enhanced search results:

```blade
{{-- resources/views/filament/chinook-admin/components/global-search-results.blade.php --}}
<div class="fi-global-search-results">
    @if($results->isNotEmpty())
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($results->groupBy('resource') as $resourceClass => $resourceResults)
                <div class="py-3">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">
                        {{ $resourceClass::getModelLabel() }}
                        <span class="text-gray-500 dark:text-gray-400">({{ $resourceResults->count() }})</span>
                    </h3>
                    
                    <div class="space-y-2">
                        @foreach($resourceResults as $result)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            @if(method_exists($result['resource'], 'getNavigationIcon'))
                                                <x-heroicon-o-musical-note class="w-5 h-5 text-gray-400" />
                                            @endif
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                {{ $result['title'] }}
                                            </p>
                                            
                                            @if($result['details'])
                                                <div class="flex flex-wrap gap-2 mt-1">
                                                    @foreach($result['details'] as $key => $value)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                            {{ $key }}: {{ $value }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    @if(method_exists($result['resource'], 'getGlobalSearchResultActions'))
                                        @foreach($result['resource']::getGlobalSearchResultActions($result['record']) as $action)
                                            @if($action['visible'] ?? true)
                                                <x-filament::button
                                                    :href="$action['url']"
                                                    :icon="$action['icon']"
                                                    size="sm"
                                                    color="gray"
                                                    :tooltip="$action['label']"
                                                >
                                                    {{ $action['label'] }}
                                                </x-filament::button>
                                            @endif
                                        @endforeach
                                    @else
                                        <x-filament::button
                                            :href="$result['url']"
                                            icon="heroicon-o-eye"
                                            size="sm"
                                            color="primary"
                                        >
                                            View
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <x-heroicon-o-magnifying-glass class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No results found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Try adjusting your search terms or filters.
            </p>
        </div>
    @endif
</div>
```

## Performance Optimization

### Search Indexing

Optimize database queries with proper indexing:

```php
// In your migration files
Schema::table('artists', function (Blueprint $table) {
    $table->index(['name']);
    $table->index(['country']);
    $table->fullText(['name', 'biography']);
});

Schema::table('albums', function (Blueprint $table) {
    $table->index(['title', 'artist_id']);
    $table->fullText(['title', 'description']);
});

Schema::table('tracks', function (Blueprint $table) {
    $table->index(['name', 'album_id']);
    $table->index(['composer']);
    $table->fullText(['name', 'composer']);
});
```

### Search Result Caching

Implement intelligent caching for search results:

```php
class CachedGlobalSearchService extends GlobalSearchService
{
    protected function getCacheKey(string $query, ?string $resourceType, int $limit): string
    {
        return sprintf(
            'global_search.%s.%s.%d.%s',
            md5($query),
            $resourceType ?? 'all',
            $limit,
            auth()->user()->roles->pluck('name')->sort()->implode('.')
        );
    }

    public function search(string $query, ?string $resourceType = null, int $limit = 50): Collection
    {
        $cacheKey = $this->getCacheKey($query, $resourceType, $limit);
        
        return Cache::remember($cacheKey, 600, function () use ($query, $resourceType, $limit) {
            return parent::search($query, $resourceType, $limit);
        });
    }

    public function clearSearchCache(?string $pattern = null): void
    {
        if ($pattern) {
            Cache::flush(); // In production, use more specific cache clearing
        } else {
            Cache::tags(['global_search'])->flush();
        }
    }
}
```

## Search Analytics

### Search Analytics Widget

Track search usage and performance:

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use App\Models\SearchHistory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SearchAnalyticsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalSearches = SearchHistory::count();
        $todaySearches = SearchHistory::whereDate('created_at', today())->count();
        $avgResultsCount = SearchHistory::avg('results_count');
        $popularQuery = SearchHistory::getPopularSearches(1)->first();

        return [
            Stat::make('Total Searches', number_format($totalSearches))
                ->description('All-time search queries')
                ->descriptionIcon('heroicon-m-magnifying-glass')
                ->color('primary'),

            Stat::make('Today\'s Searches', number_format($todaySearches))
                ->description('Search queries today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),

            Stat::make('Avg Results', number_format($avgResultsCount, 1))
                ->description('Average results per search')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Popular Query', $popularQuery ?? 'N/A')
                ->description('Most searched term')
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning'),
        ];
    }
}
```

## Next Steps

1. **Configure Resources** - Set up global search for all your resources
2. **Optimize Performance** - Implement caching and database indexing
3. **Add Analytics** - Track search usage and popular queries
4. **Enhance UX** - Add search suggestions and filters
5. **Test Thoroughly** - Verify search works across all resources and permissions

## Related Documentation

- **[Resource Documentation](../resources/)** - Configuring individual resources
- **[Performance Optimization](../deployment/050-performance-optimization.md)** - Additional performance tuning
- **[Security Configuration](../setup/050-security-configuration.md)** - Securing search functionality
