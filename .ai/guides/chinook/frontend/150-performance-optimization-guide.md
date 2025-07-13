# 1. Performance Optimization Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Livewire Performance Patterns](#2-livewire-performance-patterns)
- [3. Lazy Loading Strategies](#3-lazy-loading-strategies)
- [4. Caching Mechanisms](#4-caching-mechanisms)
- [5. Database Optimization](#5-database-optimization)
- [6. Asset Optimization](#6-asset-optimization)
- [7. Memory Management](#7-memory-management)
- [8. Network Optimization](#8-network-optimization)
- [9. Best Practices](#9-best-practices)
- [10. Navigation](#10-navigation)

## 1. Overview

This guide provides comprehensive strategies for optimizing performance in the Chinook application's frontend components. Performance optimization focuses on reducing load times, minimizing server requests, and creating smooth user interactions while maintaining the rich functionality of Livewire/Volt components.

## 2. Livewire Performance Patterns

### 2.1 Efficient Component Design

```php
<?php
// Optimized artist listing component
use function Livewire\Volt\{state, computed, mount};
use App\Models\Artist;

// Minimal initial state
state([
    'search' => '',
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'perPage' => 20,
    'page' => 1
]);

// Cached computed properties
$artists = computed(function () {
    return Artist::query()
        ->select(['id', 'public_id', 'slug', 'name', 'country', 'is_active']) // Only needed columns
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->orderBy($this->sortBy, $this->sortDirection)
        ->with(['albums:id,artist_id,title']) // Eager load with specific columns
        ->paginate($this->perPage, ['*'], 'page', $this->page);
})->persist(seconds: 300); // Cache for 5 minutes

// Debounced search to reduce server requests
$updatedSearch = function () {
    $this->page = 1; // Reset pagination
    $this->resetPage(); // Clear cached results
};

// Optimized sorting
$sortBy = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
    $this->page = 1;
};

// Renderless actions for analytics
$trackView = action(function ($artistId) {
    // Log view event for analytics
    \Log::info('Artist viewed', [
        'viewable_type' => Artist::class,
        'viewable_id' => $artistId,
        'user_id' => auth()->id(),
        'ip_address' => request()->ip()
    ]);

    // Could implement analytics tracking here
    // Example: dispatch job for view analytics
    // \App\Jobs\ViewAnalytics::dispatch(Artist::class, $artistId, auth()->id());
})->renderless();
?>

<div class="space-y-6">
    <!-- Search with debouncing -->
    <flux:input 
        wire:model.live.debounce.500ms="search"
        placeholder="Search artists..."
        icon="magnifying-glass"
    />
    
    <!-- Optimized artist grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($this->artists as $artist)
            <flux:card 
                wire:key="artist-{{ $artist->id }}"
                wire:mouseenter="trackView({{ $artist->id }})"
                class="cursor-pointer hover:shadow-lg transition-shadow duration-200"
            >
                <div class="p-4">
                    <flux:heading size="lg">{{ $artist->name }}</flux:heading>
                    <flux:text variant="muted">{{ $artist->country }}</flux:text>
                    <flux:text variant="muted">{{ $artist->albums->count() }} albums</flux:text>
                </div>
            </flux:card>
        @endforeach
    </div>
    
    <!-- Pagination -->
    {{ $this->artists->links() }}
</div>
```

### 2.2 Computed Property Optimization

```php
<?php
// Advanced caching strategies for taxonomy-based queries
use function Livewire\Volt\{state, computed};
use App\Models\Track;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state(['selectedGenre' => null, 'selectedMood' => null]);

// Cache expensive taxonomy queries
$availableGenres = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->withCount('tracks')
        ->orderBy('tracks_count', 'desc')
        ->limit(20)
        ->get();
})->persist(seconds: 3600); // Cache for 1 hour

// Conditional loading based on user interaction
$tracks = computed(function () {
    if (!$this->selectedGenre && !$this->selectedMood) {
        return collect(); // Don't load anything initially
    }
    
    return Track::query()
        ->select(['id', 'name', 'milliseconds', 'album_id', 'artist_id'])
        ->with([
            'artist:id,name',
            'album:id,title',
            'taxonomies:id,name,type'
        ])
        ->when($this->selectedGenre, function ($query) {
            $query->whereHasTaxonomies(function ($q) {
                $q->where('taxonomies.id', $this->selectedGenre);
            });
        })
        ->when($this->selectedMood, function ($query) {
            $query->whereHasTaxonomies(function ($q) {
                $q->where('taxonomies.id', $this->selectedMood);
            });
        })
        ->limit(50)
        ->get();
})->persist(seconds: 600); // Cache for 10 minutes

// Optimized statistics computation
$genreStats = computed(function () {
    if (!$this->selectedGenre) return null;
    
    return cache()->remember(
        "genre-stats-{$this->selectedGenre}",
        now()->addHours(2),
        function () {
            $genre = Taxonomy::find($this->selectedGenre);
            
            return [
                'total_tracks' => $genre->tracks()->count(),
                'total_albums' => $genre->albums()->count(),
                'total_artists' => $genre->artists()->count(),
                'avg_duration' => $genre->tracks()->avg('milliseconds'),
                'total_duration' => $genre->tracks()->sum('milliseconds')
            ];
        }
    );
});
?>
```

### 2.3 Renderless Actions

```php
<?php
// Optimized background operations
use function Livewire\Volt\{state, action};

state(['playCount' => 0, 'isPlaying' => false]);

// Renderless action for play tracking
$trackPlay = action(function ($trackId) {
    // Increment play count in background
    \App\Models\Track::find($trackId)?->increment('play_count');
    
    // Queue analytics job
    \App\Jobs\TrackPlayAnalytics::dispatch($trackId, auth()->id());
    
    // Update local state
    $this->playCount++;
})->renderless();

// Renderless action for favorites
$toggleFavorite = action(function ($trackId) {
    $user = auth()->user();
    $track = \App\Models\Track::find($trackId);
    
    if ($user->favorites()->where('track_id', $trackId)->exists()) {
        $user->favorites()->detach($trackId);
        $this->dispatch('favorite-removed', trackId: $trackId);
    } else {
        $user->favorites()->attach($trackId);
        $this->dispatch('favorite-added', trackId: $trackId);
    }
})->renderless();

// Batch operations for better performance
$batchUpdatePlaylists = action(function ($trackIds, $playlistId) {
    \App\Models\Playlist::find($playlistId)
        ->tracks()
        ->syncWithoutDetaching($trackIds);
    
    $this->dispatch('playlist-updated', 
        playlistId: $playlistId, 
        trackCount: count($trackIds)
    );
})->renderless();
?>
```

## 3. Lazy Loading Strategies

### 3.1 Component Lazy Loading

```php
<?php
// Lazy loaded album details component
use function Livewire\Volt\{state, computed, mount, placeholder};
use App\Models\Album;

state(['album' => null, 'isLoaded' => false]);

// Lazy loading placeholder
placeholder('<div class="animate-pulse bg-gray-200 h-64 rounded-lg"></div>');

mount(function (Album $album) {
    $this->album = $album->load(['artist:id,name', 'taxonomies:id,name']);
    
    // Load tracks lazily
    $this->loadTracks();
});

$tracks = computed(function () {
    if (!$this->isLoaded) return collect();
    
    return $this->album->tracks()
        ->select(['id', 'name', 'milliseconds', 'track_number'])
        ->orderBy('track_number')
        ->get();
});

$loadTracks = function () {
    $this->isLoaded = true;
};
?>

<div class="space-y-6">
    <!-- Album Header -->
    <div class="flex items-start space-x-6">
        <img 
            src="{{ $album->getFirstMediaUrl('cover') }}"
            alt="{{ $album->title }}"
            class="w-48 h-48 rounded-lg shadow-lg"
            loading="lazy"
        />
        
        <div class="flex-1">
            <flux:heading size="2xl">{{ $album->title }}</flux:heading>
            <flux:text size="lg" class="text-gray-600">{{ $album->artist->name }}</flux:text>
            
            <!-- Taxonomies -->
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($album->taxonomies as $taxonomy)
                    <flux:badge variant="subtle">{{ $taxonomy->name }}</flux:badge>
                @endforeach
            </div>
        </div>
    </div>
    
    <!-- Lazy Loaded Tracks -->
    @if($isLoaded)
        <div class="space-y-2">
            <flux:heading size="lg">Tracks</flux:heading>
            @foreach($this->tracks as $track)
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded">
                    <div class="flex items-center space-x-3">
                        <span class="text-gray-500 w-6">{{ $track->track_number }}</span>
                        <span>{{ $track->name }}</span>
                    </div>
                    <span class="text-gray-500">
                        {{ gmdate('i:s', $track->milliseconds / 1000) }}
                    </span>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <flux:button wire:click="loadTracks" variant="primary">
                Load Tracks
            </flux:button>
        </div>
    @endif
</div>
```

### 3.2 Intersection Observer for Lazy Loading

```javascript
// resources/js/lazy-loading.js
document.addEventListener('livewire:init', () => {
    // Intersection Observer for lazy loading components
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const componentId = element.getAttribute('wire:id');

                // Trigger lazy loading
                if (element.hasAttribute('data-lazy-load')) {
                    Livewire.find(componentId).call('loadContent');
                    observer.unobserve(element);
                }
            }
        });
    }, {
        rootMargin: '100px' // Start loading 100px before element is visible
    });

    // Observe all lazy-loadable elements
    document.querySelectorAll('[data-lazy-load]').forEach(el => {
        observer.observe(el);
    });
});
```

## 4. Caching Mechanisms

### 4.1 Multi-Level Caching Strategy

```php
<?php
// Advanced caching for taxonomy-based content
use function Livewire\Volt\{state, computed};
use Illuminate\Support\Facades\Cache;

state(['selectedTaxonomies' => [], 'timeRange' => '30']);

// Level 1: Application Cache (Redis/Memcached)
$popularContent = computed(function () {
    $cacheKey = 'popular-content-' . md5(serialize($this->selectedTaxonomies)) . '-' . $this->timeRange;

    return Cache::remember($cacheKey, now()->addHours(2), function () {
        $query = \App\Models\Track::query()
            ->select(['id', 'name', 'play_count', 'artist_id', 'album_id'])
            ->with(['artist:id,name', 'album:id,title'])
            ->where('created_at', '>=', now()->subDays($this->timeRange))
            ->orderByDesc('play_count');

        if (!empty($this->selectedTaxonomies)) {
            $query->whereHasTaxonomies($this->selectedTaxonomies);
        }

        return $query->limit(20)->get();
    });
})->persist(seconds: 600); // Level 2: Component Cache

// Level 3: Database Query Cache
$taxonomyStats = computed(function () {
    return Cache::tags(['taxonomy-stats'])->remember(
        'taxonomy-stats-summary',
        now()->addDay(),
        function () {
            return \DB::table('taxonomy_terms')
                ->join('taxonomies', 'taxonomy_terms.taxonomy_id', '=', 'taxonomies.id')
                ->select([
                    'taxonomies.id',
                    'taxonomies.name',
                    'taxonomies.type',
                    \DB::raw('COUNT(*) as usage_count')
                ])
                ->groupBy('taxonomies.id', 'taxonomies.name', 'taxonomies.type')
                ->orderByDesc('usage_count')
                ->get();
        }
    );
});

// Cache invalidation strategy
$invalidateCache = function () {
    Cache::tags(['taxonomy-stats'])->flush();
    Cache::forget('popular-content-*');
    $this->resetComputedProperty('popularContent');
    $this->resetComputedProperty('taxonomyStats');
};
?>
```

### 4.2 Smart Cache Warming

```php
<?php
// Cache warming job for frequently accessed data
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class WarmTaxonomyCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Warm popular genres cache
        $popularGenres = Taxonomy::where('type', 'genre')
            ->withCount(['tracks', 'albums', 'artists'])
            ->orderByDesc('tracks_count')
            ->limit(50)
            ->get();

        Cache::put('popular-genres', $popularGenres, now()->addHours(6));

        // Warm genre statistics
        foreach ($popularGenres->take(10) as $genre) {
            $stats = [
                'total_tracks' => $genre->tracks_count,
                'total_albums' => $genre->albums_count,
                'total_artists' => $genre->artists_count,
                'avg_duration' => $genre->tracks()->avg('milliseconds'),
                'recent_tracks' => $genre->tracks()
                    ->with(['artist:id,name', 'album:id,title'])
                    ->latest()
                    ->limit(10)
                    ->get()
            ];

            Cache::put("genre-stats-{$genre->id}", $stats, now()->addHours(4));
        }

        // Warm search suggestions
        $searchSuggestions = [
            'artists' => \App\Models\Artist::select('name')
                ->orderByDesc('play_count')
                ->limit(100)
                ->pluck('name'),
            'albums' => \App\Models\Album::select('title')
                ->orderByDesc('play_count')
                ->limit(100)
                ->pluck('title'),
            'genres' => Taxonomy::where('type', 'genre')
                ->orderBy('name')
                ->pluck('name')
        ];

        Cache::put('search-suggestions', $searchSuggestions, now()->addDay());
    }
}
?>
```

## 5. Database Optimization

### 5.1 Efficient Query Patterns

```php
<?php
// Optimized database queries for taxonomy relationships
use function Livewire\Volt\{state, computed};

state(['filters' => []]);

// Efficient taxonomy filtering with proper indexing
$filteredTracks = computed(function () {
    $query = \App\Models\Track::query()
        ->select([
            'tracks.id',
            'tracks.name',
            'tracks.milliseconds',
            'tracks.album_id',
            'tracks.play_count'
        ])
        ->join('albums', 'tracks.album_id', '=', 'albums.id')
        ->join('artists', 'albums.artist_id', '=', 'artists.id')
        ->addSelect([
            'albums.title as album_title',
            'artists.name as artist_name'
        ]);

    // Efficient taxonomy filtering using EXISTS
    if (!empty($this->filters)) {
        $query->whereExists(function ($subquery) {
            $subquery->select(\DB::raw(1))
                ->from('taxonomy_terms')
                ->whereColumn('taxonomy_terms.taxonomizable_id', 'tracks.id')
                ->where('taxonomy_terms.taxonomizable_type', \App\Models\Track::class)
                ->whereIn('taxonomy_terms.taxonomy_id', $this->filters);
        });
    }

    return $query
        ->orderByDesc('tracks.play_count')
        ->limit(100)
        ->get();
});

// Optimized aggregation queries
$genrePopularity = computed(function () {
    return \DB::table('taxonomy_terms')
        ->join('taxonomies', 'taxonomy_terms.taxonomy_id', '=', 'taxonomies.id')
        ->join('tracks', function ($join) {
            $join->on('taxonomy_terms.taxonomizable_id', '=', 'tracks.id')
                 ->where('taxonomy_terms.taxonomizable_type', '=', \App\Models\Track::class);
        })
        ->where('taxonomies.type', 'genre')
        ->select([
            'taxonomies.id',
            'taxonomies.name',
            \DB::raw('COUNT(tracks.id) as track_count'),
            \DB::raw('SUM(tracks.play_count) as total_plays'),
            \DB::raw('AVG(tracks.milliseconds) as avg_duration')
        ])
        ->groupBy('taxonomies.id', 'taxonomies.name')
        ->orderByDesc('total_plays')
        ->limit(20)
        ->get();
});
?>
```

### 5.2 Database Indexing Strategy

```sql
-- Optimized indexes for taxonomy queries
-- Add these to your migration files

-- Index for taxonomy_terms table
CREATE INDEX idx_taxonomy_terms_taxonomizable ON taxonomy_terms(taxonomizable_type, taxonomizable_id);
CREATE INDEX idx_taxonomy_terms_taxonomy_id ON taxonomy_terms(taxonomy_id);
CREATE INDEX idx_taxonomy_terms_composite ON taxonomy_terms(taxonomy_id, taxonomizable_type, taxonomizable_id);

-- Index for taxonomies table
CREATE INDEX idx_taxonomies_type ON taxonomies(type);
CREATE INDEX idx_taxonomies_type_name ON taxonomies(type, name);

-- Index for tracks table
CREATE INDEX idx_tracks_play_count ON tracks(play_count DESC);
CREATE INDEX idx_tracks_album_id ON tracks(album_id);
CREATE INDEX idx_tracks_created_at ON tracks(created_at);

-- Composite indexes for common queries
CREATE INDEX idx_tracks_album_play_count ON tracks(album_id, play_count DESC);
CREATE INDEX idx_albums_artist_id ON albums(artist_id);

-- Full-text search indexes
ALTER TABLE artists ADD FULLTEXT(name);
ALTER TABLE albums ADD FULLTEXT(title);
ALTER TABLE tracks ADD FULLTEXT(name);
```

## 6. Asset Optimization

### 6.1 Image Optimization

```php
<?php
// Optimized image handling with responsive images
use function Livewire\Volt\{state, computed};

state(['album' => null]);

$optimizedImages = computed(function () {
    if (!$this->album) return null;

    $media = $this->album->getFirstMedia('cover');
    if (!$media) return null;

    return [
        'thumbnail' => $media->getUrl('thumbnail'), // 150x150
        'small' => $media->getUrl('small'),         // 300x300
        'medium' => $media->getUrl('medium'),       // 600x600
        'large' => $media->getUrl('large'),         // 1200x1200
        'webp_small' => $media->getUrl('webp_small'),
        'webp_medium' => $media->getUrl('webp_medium'),
    ];
});
?>

<!-- Responsive image with WebP support -->
<picture>
    <source
        srcset="{{ $this->optimizedImages['webp_small'] }} 300w,
                {{ $this->optimizedImages['webp_medium'] }} 600w"
        sizes="(max-width: 768px) 300px, 600px"
        type="image/webp"
    />
    <img
        src="{{ $this->optimizedImages['medium'] }}"
        srcset="{{ $this->optimizedImages['small'] }} 300w,
                {{ $this->optimizedImages['medium'] }} 600w"
        sizes="(max-width: 768px) 300px, 600px"
        alt="{{ $album->title }} cover"
        loading="lazy"
        class="w-full h-auto rounded-lg"
    />
</picture>
```

### 6.2 JavaScript and CSS Optimization

```javascript
// resources/js/performance.js
// Optimized JavaScript loading and execution

// Lazy load non-critical JavaScript
const loadNonCriticalJS = () => {
    // Load analytics only when needed
    if (window.location.pathname.includes('/analytics')) {
        import('./analytics.js').then(module => {
            module.initAnalytics();
        });
    }

    // Load music player only when needed
    if (document.querySelector('[data-music-player]')) {
        import('./music-player.js').then(module => {
            module.initMusicPlayer();
        });
    }
};

// Use Intersection Observer for performance monitoring
const performanceObserver = new PerformanceObserver((list) => {
    list.getEntries().forEach((entry) => {
        if (entry.entryType === 'navigation') {
            console.log('Page load time:', entry.loadEventEnd - entry.loadEventStart);
        }
    });
});

performanceObserver.observe({ entryTypes: ['navigation'] });

// Optimize Livewire requests
document.addEventListener('livewire:init', () => {
    // Batch multiple updates
    let updateQueue = [];
    let updateTimeout;

    const batchUpdates = (callback) => {
        updateQueue.push(callback);

        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(() => {
            updateQueue.forEach(cb => cb());
            updateQueue = [];
        }, 50); // Batch updates within 50ms
    };

    // Optimize scroll-based updates
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            // Trigger scroll-based Livewire updates
            Livewire.dispatch('scroll-update', {
                scrollY: window.scrollY,
                viewportHeight: window.innerHeight
            });
        }, 100);
    });
});

// Load non-critical resources after page load
window.addEventListener('load', () => {
    setTimeout(loadNonCriticalJS, 1000);
});
```

## 7. Memory Management

### 7.1 Component Memory Optimization

```php
<?php
// Memory-efficient component design
use function Livewire\Volt\{state, computed, dehydrate, hydrate};

state(['largeDataset' => null, 'processedData' => null]);

// Optimize memory usage during dehydration
dehydrate(function () {
    // Clear large datasets before storing component state
    if (isset($this->largeDataset) && count($this->largeDataset) > 1000) {
        unset($this->largeDataset);
    }

    // Clear processed data that can be regenerated
    unset($this->processedData);
});

// Restore data efficiently during hydration
hydrate(function () {
    // Only reload if needed
    if (!$this->largeDataset && $this->needsLargeDataset()) {
        $this->loadLargeDataset();
    }
});

$needsLargeDataset = function () {
    return request()->has('detailed_view');
};

$loadLargeDataset = function () {
    // Load data in chunks to manage memory
    $this->largeDataset = \App\Models\Track::query()
        ->with(['artist:id,name', 'album:id,title'])
        ->chunk(500, function ($tracks) {
            // Process in smaller chunks
            foreach ($tracks as $track) {
                // Process individual track
                yield $track;
            }
        });
};

// Memory-efficient data processing
$processData = computed(function () {
    if (!$this->largeDataset) return collect();

    // Use generators for memory efficiency
    return $this->processInChunks($this->largeDataset);
});

$processInChunks = function ($data) {
    foreach (array_chunk($data->toArray(), 100) as $chunk) {
        yield from array_map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'duration' => gmdate('i:s', $item['milliseconds'] / 1000)
            ];
        }, $chunk);
    }
};
?>
```

### 7.2 Garbage Collection Optimization

```php
<?php
// Optimize garbage collection for long-running components
use function Livewire\Volt\{state, mount, destroy};

state(['memoryUsage' => 0, 'gcCycles' => 0]);

mount(function () {
    // Track initial memory usage
    $this->memoryUsage = memory_get_usage(true);

    // Enable garbage collection optimization
    if (function_exists('gc_enable')) {
        gc_enable();
    }
});

destroy(function () {
    // Force garbage collection on component destruction
    if (function_exists('gc_collect_cycles')) {
        $this->gcCycles = gc_collect_cycles();
    }

    // Clear any remaining references
    $this->clearReferences();
});

$clearReferences = function () {
    // Clear large objects and arrays
    unset($this->largeDataset);
    unset($this->processedData);

    // Clear any cached computations
    $this->resetComputedProperties();
};

$monitorMemory = function () {
    $currentUsage = memory_get_usage(true);
    $peakUsage = memory_get_peak_usage(true);

    return [
        'current' => $this->formatBytes($currentUsage),
        'peak' => $this->formatBytes($peakUsage),
        'limit' => $this->formatBytes(ini_get('memory_limit'))
    ];
};

$formatBytes = function ($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
};
?>
```

## 8. Network Optimization

### 8.1 Request Batching and Debouncing

```php
<?php
// Optimized network requests
use function Livewire\Volt\{state, action};

state(['pendingUpdates' => [], 'batchTimeout' => null]);

// Batch multiple updates into single request
$batchUpdate = function ($type, $id, $data) {
    $this->pendingUpdates[] = [
        'type' => $type,
        'id' => $id,
        'data' => $data,
        'timestamp' => microtime(true)
    ];

    // Clear existing timeout
    if ($this->batchTimeout) {
        clearTimeout($this->batchTimeout);
    }

    // Set new timeout for batch processing
    $this->batchTimeout = setTimeout(function () {
        $this->processBatch();
    }, 100); // 100ms batch window
};

$processBatch = action(function () {
    if (empty($this->pendingUpdates)) return;

    // Group updates by type
    $grouped = collect($this->pendingUpdates)->groupBy('type');

    foreach ($grouped as $type => $updates) {
        switch ($type) {
            case 'play_count':
                $this->batchUpdatePlayCounts($updates);
                break;
            case 'favorites':
                $this->batchUpdateFavorites($updates);
                break;
            case 'playlist':
                $this->batchUpdatePlaylists($updates);
                break;
        }
    }

    // Clear pending updates
    $this->pendingUpdates = [];
    $this->batchTimeout = null;
})->renderless();

$batchUpdatePlayCounts = function ($updates) {
    $trackIds = collect($updates)->pluck('id')->unique();

    \App\Models\Track::whereIn('id', $trackIds)
        ->increment('play_count');

    // Dispatch single analytics event for all tracks
    \App\Jobs\BatchPlayAnalytics::dispatch($trackIds->toArray(), auth()->id());
};
?>
```

### 8.2 Connection-Aware Loading

```javascript
// resources/js/network-optimization.js
class NetworkOptimizer {
    constructor() {
        this.connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        this.isSlowConnection = this.checkConnectionSpeed();

        this.setupNetworkListeners();
        this.optimizeForConnection();
    }

    checkConnectionSpeed() {
        if (!this.connection) return false;

        // Consider 2G and slow-2g as slow connections
        return ['slow-2g', '2g'].includes(this.connection.effectiveType);
    }

    setupNetworkListeners() {
        if (this.connection) {
            this.connection.addEventListener('change', () => {
                this.isSlowConnection = this.checkConnectionSpeed();
                this.optimizeForConnection();
            });
        }

        // Monitor online/offline status
        window.addEventListener('online', () => {
            this.handleOnline();
        });

        window.addEventListener('offline', () => {
            this.handleOffline();
        });
    }

    optimizeForConnection() {
        if (this.isSlowConnection) {
            // Reduce image quality
            this.setImageQuality('low');

            // Increase debounce times
            this.setDebounceTime(1000);

            // Disable auto-refresh
            this.disableAutoRefresh();

            // Reduce pagination size
            this.setPaginationSize(10);
        } else {
            // Use high quality settings
            this.setImageQuality('high');
            this.setDebounceTime(300);
            this.enableAutoRefresh();
            this.setPaginationSize(20);
        }
    }

    setImageQuality(quality) {
        document.documentElement.setAttribute('data-image-quality', quality);
    }

    setDebounceTime(time) {
        Livewire.directive('model.live.debounce', (el, directive) => {
            directive.modifiers = [`${time}ms`];
        });
    }

    handleOnline() {
        // Sync any pending changes
        Livewire.dispatch('network-online');

        // Resume normal operations
        this.enableAutoRefresh();
    }

    handleOffline() {
        // Cache current state
        Livewire.dispatch('network-offline');

        // Disable network-dependent features
        this.disableAutoRefresh();
    }
}

// Initialize network optimizer
document.addEventListener('livewire:init', () => {
    new NetworkOptimizer();
});
```

## 9. Best Practices

### 9.1 Performance Guidelines

1. **Minimize State**: Keep component state as small as possible
2. **Use Computed Properties**: Cache expensive calculations with appropriate TTL
3. **Lazy Load**: Load content only when needed using intersection observers
4. **Batch Operations**: Group multiple updates into single requests
5. **Optimize Queries**: Use proper indexing and efficient query patterns
6. **Cache Strategically**: Implement multi-level caching for frequently accessed data
7. **Monitor Performance**: Track key metrics and optimize bottlenecks

### 9.2 Performance Checklist

- [ ] Component state is minimal and focused
- [ ] Expensive computations are cached with appropriate TTL
- [ ] Database queries use proper indexing
- [ ] Images are optimized and use responsive loading
- [ ] JavaScript is loaded lazily for non-critical features
- [ ] Network requests are batched and debounced
- [ ] Memory usage is monitored and optimized
- [ ] Performance metrics are tracked and analyzed

### 9.3 Monitoring and Metrics

```php
<?php
// Performance monitoring component
use function Livewire\Volt\{state, computed};

state(['metrics' => []]);

$performanceMetrics = computed(function () {
    return [
        'memory_usage' => [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ],
        'database_queries' => \DB::getQueryLog(),
        'cache_hits' => Cache::getHits(),
        'cache_misses' => Cache::getMisses(),
        'response_time' => microtime(true) - LARAVEL_START
    ];
});

$trackPerformance = function ($event, $data = []) {
    $this->metrics[] = [
        'event' => $event,
        'data' => $data,
        'timestamp' => microtime(true),
        'memory' => memory_get_usage(true)
    ];

    // Log performance data for analysis
    \Log::channel('performance')->info($event, $data);
};
?>
```

## 10. Navigation

**← Previous** [Accessibility and WCAG Compliance Guide](140-accessibility-wcag-guide.md)
**Next →** [Testing Approaches Guide](160-testing-approaches-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/150-performance-optimization-guide.md on 2025-07-11

*This guide provides comprehensive performance optimization strategies for Livewire/Volt components. Continue with the testing guide for comprehensive testing approaches.*

[⬆️ Back to Top](#1-performance-optimization-guide)
