# Performance Optimization Guide

## Table of Contents

- [Overview](#overview)
- [Livewire Performance Patterns](#livewire-performance-patterns)
- [Lazy Loading Strategies](#lazy-loading-strategies)
- [Caching Mechanisms](#caching-mechanisms)
- [Database Optimization](#database-optimization)
- [Asset Optimization](#asset-optimization)
- [Memory Management](#memory-management)
- [Network Optimization](#network-optimization)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide provides comprehensive strategies for optimizing performance in the Chinook application's frontend components. Performance optimization focuses on reducing load times, minimizing server requests, and creating smooth user interactions while maintaining the rich functionality of Livewire/Volt components.

## Livewire Performance Patterns

### Efficient Component Design

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
                    
                    <!-- Lazy load album count -->
                    <div wire:loading.remove wire:target="artists">
                        <flux:text size="sm">{{ $artist->albums->count() }} albums</flux:text>
                    </div>
                    
                    <div wire:loading wire:target="artists">
                        <flux:text size="sm" variant="muted">Loading...</flux:text>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>
    
    <!-- Efficient pagination -->
    <div class="flex justify-center">
        {{ $this->artists->links() }}
    </div>
</div>
```

### Component State Optimization

```php
<?php
// Optimized music player with minimal state
use function Livewire\Volt\{state, computed, on};

// Only essential state
state([
    'currentTrackId' => null,
    'isPlaying' => false,
    'position' => 0,
    'volume' => 0.8
]);

// Computed properties for derived data
$currentTrack = computed(function () {
    return $this->currentTrackId 
        ? \App\Models\Track::with(['album:id,title,artist_id', 'album.artist:id,name'])
            ->find($this->currentTrackId)
        : null;
})->persist(seconds: 60);

$playlistTracks = computed(function () {
    return session('current_playlist', []);
});

// Efficient event handling
on([
    'track-selected' => function ($trackId) {
        $this->currentTrackId = $trackId;
        $this->position = 0;
        $this->isPlaying = true;
        
        // Update session without re-rendering
        session(['last_played_track' => $trackId]);
    },
    
    'playlist-updated' => function ($tracks) {
        session(['current_playlist' => $tracks]);
        $this->dispatch('playlist-changed');
    }
]);

// Optimized actions
$togglePlay = function () {
    $this->isPlaying = !$this->isPlaying;
    
    // Dispatch to JavaScript for immediate UI feedback
    $this->dispatch('player-state-changed', [
        'isPlaying' => $this->isPlaying,
        'trackId' => $this->currentTrackId
    ]);
};

$updatePosition = action(function ($newPosition) {
    $this->position = $newPosition;
    
    // Save position periodically without re-rendering
    if ($newPosition % 10 === 0) { // Every 10 seconds
        session(['track_position' => $newPosition]);
    }
})->renderless();
?>

<div class="music-player" 
     x-data="musicPlayer()"
     x-on:player-state-changed.window="updatePlayerState($event.detail)">
    
    @if($this->currentTrack)
        <!-- Track info (cached) -->
        <div class="flex items-center space-x-4">
            <img 
                src="{{ $this->currentTrack->album->getFirstMediaUrl('cover', 'thumb') }}" 
                alt="{{ $this->currentTrack->album->title }}"
                class="w-12 h-12 rounded"
                loading="lazy"
            />
            
            <div>
                <flux:heading size="sm">{{ $this->currentTrack->name }}</flux:heading>
                <flux:text variant="muted">{{ $this->currentTrack->album->artist->name }}</flux:text>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="flex items-center space-x-4">
            <flux:button 
                wire:click="togglePlay"
                :icon="$isPlaying ? 'pause' : 'play'"
                variant="primary"
            />
            
            <!-- Position slider with JavaScript handling -->
            <input
                type="range"
                min="0"
                max="{{ $this->currentTrack->milliseconds / 1000 }}"
                x-model="position"
                x-on:input="updatePosition($event.target.value)"
                class="flex-1"
            />
        </div>
    @endif
</div>

<script>
function musicPlayer() {
    return {
        position: @entangle('position'),
        isPlaying: @entangle('isPlaying'),
        
        updatePlayerState(state) {
            this.isPlaying = state.isPlaying;
            // Handle immediate UI updates without server round-trip
        },
        
        updatePosition(newPosition) {
            this.position = newPosition;
            
            // Throttle server updates
            clearTimeout(this.positionTimeout);
            this.positionTimeout = setTimeout(() => {
                $wire.updatePosition(newPosition);
            }, 1000);
        }
    }
}
</script>
```

## Lazy Loading Strategies

### Component Lazy Loading

```php
<?php
// Lazy-loaded album details
use function Livewire\Volt\{state, mount, placeholder};
use App\Models\Album;

state(['album' => null, 'tracks' => null, 'reviews' => null]);

// Lazy loading placeholder
placeholder('<div class="animate-pulse bg-gray-200 h-64 rounded-lg"></div>');

mount(function (Album $album) {
    $this->album = $album->load(['artist:id,name', 'categories:id,name']);
    
    // Load tracks lazily
    $this->loadTracks();
});

$loadTracks = function () {
    if (!$this->tracks) {
        $this->tracks = $this->album->tracks()
            ->select(['id', 'name', 'track_number', 'milliseconds', 'unit_price'])
            ->orderBy('track_number')
            ->get();
    }
};

$loadReviews = function () {
    if (!$this->reviews) {
        $this->reviews = $this->album->reviews()
            ->with(['user:id,name'])
            ->latest()
            ->limit(10)
            ->get();
    }
};
?>

<div class="space-y-8">
    <!-- Album header (immediate) -->
    <div class="flex items-start space-x-8">
        <img 
            src="{{ $album->getFirstMediaUrl('cover') }}" 
            alt="{{ $album->title }}"
            class="w-64 h-64 rounded-lg shadow-lg"
            loading="lazy"
        />
        
        <div>
            <flux:heading size="2xl">{{ $album->title }}</flux:heading>
            <flux:text size="lg">{{ $album->artist->name }}</flux:text>
            <flux:text variant="muted">{{ $album->release_date->format('Y') }}</flux:text>
        </div>
    </div>
    
    <!-- Tabs with lazy loading -->
    <flux:tabs wire:model="activeTab">
        <flux:tab name="tracks">Tracks</flux:tab>
        <flux:tab name="reviews">Reviews</flux:tab>
    </flux:tabs>
    
    <!-- Lazy-loaded content -->
    @if($activeTab === 'tracks')
        <div wire:loading wire:target="loadTracks">
            <div class="space-y-3">
                @for($i = 0; $i < 5; $i++)
                    <div class="animate-pulse bg-gray-200 h-12 rounded"></div>
                @endfor
            </div>
        </div>
        
        <div wire:loading.remove wire:target="loadTracks">
            @if($tracks)
                <div class="space-y-3">
                    @foreach($tracks as $track)
                        <div class="flex justify-between items-center p-3 border rounded">
                            <div>
                                <flux:heading size="sm">{{ $track->name }}</flux:heading>
                                <flux:text variant="muted">
                                    {{ gmdate('i:s', $track->milliseconds / 1000) }}
                                </flux:text>
                            </div>
                            <flux:text>${{ number_format($track->unit_price, 2) }}</flux:text>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
    
    @if($activeTab === 'reviews')
        <div wire:init="loadReviews">
            <div wire:loading wire:target="loadReviews">
                <flux:text variant="muted">Loading reviews...</flux:text>
            </div>
            
            <div wire:loading.remove wire:target="loadReviews">
                @if($reviews && $reviews->count() > 0)
                    <div class="space-y-4">
                        @foreach($reviews as $review)
                            <flux:card class="p-4">
                                <flux:heading size="sm">{{ $review->user->name }}</flux:heading>
                                <flux:text>{{ $review->content }}</flux:text>
                            </flux:card>
                        @endforeach
                    </div>
                @else
                    <flux:text variant="muted">No reviews yet.</flux:text>
                @endif
            </div>
        </div>
    @endif
</div>
```

### Infinite Scrolling

```php
<?php
// Infinite scroll track listing
use function Livewire\Volt\{state, computed};
use App\Models\Track;

state([
    'tracks' => fn() => collect(),
    'page' => 1,
    'hasMore' => true,
    'loading' => false
]);

$loadMore = function () {
    if ($this->loading || !$this->hasMore) return;
    
    $this->loading = true;
    
    $newTracks = Track::with(['album:id,title,artist_id', 'album.artist:id,name'])
        ->select(['id', 'name', 'album_id', 'milliseconds', 'unit_price'])
        ->orderBy('name')
        ->paginate(20, ['*'], 'page', $this->page);
    
    $this->tracks = $this->tracks->concat($newTracks->items());
    $this->hasMore = $newTracks->hasMorePages();
    $this->page++;
    $this->loading = false;
};

mount(function () {
    $this->loadMore(); // Load initial batch
});
?>

<div class="space-y-4" 
     x-data="{ 
         init() {
             this.setupInfiniteScroll();
         },
         setupInfiniteScroll() {
             const observer = new IntersectionObserver((entries) => {
                 entries.forEach(entry => {
                     if (entry.isIntersecting && !@entangle('loading') && @entangle('hasMore')) {
                         $wire.loadMore();
                     }
                 });
             }, { threshold: 0.1 });
             
             observer.observe(this.$refs.loadTrigger);
         }
     }">
    
    <!-- Track list -->
    @foreach($tracks as $track)
        <flux:card wire:key="track-{{ $track->id }}" class="p-4">
            <div class="flex justify-between items-center">
                <div>
                    <flux:heading size="sm">{{ $track->name }}</flux:heading>
                    <flux:text variant="muted">{{ $track->album->artist->name }}</flux:text>
                </div>
                <flux:text>${{ number_format($track->unit_price, 2) }}</flux:text>
            </div>
        </flux:card>
    @endforeach
    
    <!-- Loading indicator -->
    <div wire:loading wire:target="loadMore" class="text-center py-4">
        <flux:text variant="muted">Loading more tracks...</flux:text>
    </div>
    
    <!-- Intersection trigger -->
    @if($hasMore)
        <div x-ref="loadTrigger" class="h-4"></div>
    @else
        <div class="text-center py-4">
            <flux:text variant="muted">No more tracks to load.</flux:text>
        </div>
    @endif
</div>
```

## Caching Mechanisms

### Multi-Level Caching Strategy

```php
<?php
// Advanced caching for dashboard analytics
use function Livewire\Volt\{state, computed, mount};

state(['dateRange' => '30', 'refreshing' => false]);

// Level 1: Request-level caching (computed properties)
$totalSales = computed(function () {
    return \Cache::remember(
        "sales_total_{$this->dateRange}_" . auth()->id(),
        now()->addMinutes(5),
        fn() => $this->calculateTotalSales()
    );
});

// Level 2: User-specific caching
$topArtists = computed(function () {
    return \Cache::remember(
        "top_artists_{$this->dateRange}",
        now()->addHours(1),
        fn() => $this->getTopArtists()
    );
})->persist(seconds: 1800); // 30 minutes

$refreshData = function () {
    $this->refreshing = true;

    // Clear specific cache keys
    \Cache::forget("sales_total_{$this->dateRange}_" . auth()->id());

    // Reset computed properties
    $this->resetComputedProperty('totalSales');
    $this->resetComputedProperty('topArtists');

    $this->refreshing = false;

    $this->dispatch('toast', message: 'Data refreshed successfully');
};
?>

<div class="space-y-6">
    <!-- Cache controls -->
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Analytics Dashboard</flux:heading>

        <div class="flex items-center space-x-4">
            <flux:select wire:model.live="dateRange">
                <flux:option value="7">Last 7 days</flux:option>
                <flux:option value="30">Last 30 days</flux:option>
                <flux:option value="90">Last 90 days</flux:option>
            </flux:select>

            <flux:button
                wire:click="refreshData"
                :disabled="$refreshing"
                variant="ghost"
                icon="arrow-path"
            >
                <span wire:loading.remove wire:target="refreshData">Refresh</span>
                <span wire:loading wire:target="refreshData">Refreshing...</span>
            </flux:button>
        </div>
    </div>

    <!-- Cached metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card class="p-6">
            <flux:heading size="lg">${{ number_format($this->totalSales, 2) }}</flux:heading>
            <flux:text variant="muted">Total Sales</flux:text>
        </flux:card>
    </div>
</div>
```

## Database Optimization

### Efficient Query Patterns

```php
<?php
// Optimized artist search with relationships
use function Livewire\Volt\{state, computed};
use App\Models\Artist;

state(['search' => '', 'filters' => []]);

$artists = computed(function () {
    return Artist::query()
        // Select only needed columns
        ->select([
            'artists.id',
            'artists.public_id',
            'artists.slug',
            'artists.name',
            'artists.country'
        ])
        // Efficient search
        ->when($this->search, function ($query) {
            $query->where('artists.name', 'like', "%{$this->search}%");
        })
        // Optimized eager loading
        ->with(['albums:id,artist_id,title'])
        // Add computed columns
        ->withCount(['albums', 'tracks'])
        // Efficient ordering
        ->orderBy('artists.name')
        // Pagination
        ->paginate(20);
})->persist(seconds: 300);
?>
```

## Asset Optimization

### Image Optimization

```php
<?php
// Optimized image handling
use function Livewire\Volt\{state, mount};
use App\Models\Album;

state(['album' => null, 'imageLoaded' => false]);

mount(function (Album $album) {
    $this->album = $album;
});

$getOptimizedImage = function ($size = 'medium') {
    if (!$this->album->hasMedia('cover')) {
        return '/images/default-album-cover.jpg';
    }

    return $this->album->getFirstMediaUrl('cover', $size);
};
?>

<div class="album-cover-container">
    <!-- Progressive image loading -->
    <div class="relative">
        <!-- Low-quality placeholder -->
        <img
            src="{{ $this->getOptimizedImage('thumb') }}"
            alt="{{ $album->title }} cover"
            class="w-full h-64 object-cover rounded-lg blur-sm transition-all duration-300"
            style="{{ $imageLoaded ? 'opacity: 0;' : 'opacity: 1;' }}"
        />

        <!-- High-quality image -->
        <img
            src="{{ $this->getOptimizedImage('large') }}"
            alt="{{ $album->title }} cover"
            class="absolute inset-0 w-full h-64 object-cover rounded-lg transition-all duration-300"
            style="{{ $imageLoaded ? 'opacity: 1;' : 'opacity: 0;' }}"
            loading="lazy"
            onload="$wire.set('imageLoaded', true)"
        />
    </div>
</div>
```

## Memory Management

### Component Cleanup

```php
<?php
// Memory-efficient component lifecycle
use function Livewire\Volt\{state, dehydrate, hydrate};

state(['largeDataset' => null, 'tempFiles' => []]);

hydrate(function () {
    // Restore only essential state
    $this->restoreEssentialState();
});

dehydrate(function () {
    // Clean up large objects before serialization
    $this->largeDataset = null;

    // Clean up temporary files
    foreach ($this->tempFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    $this->tempFiles = [];
});
?>
```

## Network Optimization

### CDN Configuration for Music Streaming

Optimize content delivery for the Chinook music platform with strategic CDN implementation.

```php
<?php
// config/filesystems.php - CDN configuration for music streaming

return [
    'disks' => [
        // Primary CDN for audio files
        'audio_cdn' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_AUDIO_BUCKET'),
            'url' => env('AWS_AUDIO_CDN_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'options' => [
                'CacheControl' => 'max-age=31536000, immutable', // 1 year cache
                'ContentType' => 'audio/mpeg',
            ],
        ],

        // Image CDN for album artwork and avatars
        'image_cdn' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_IMAGE_BUCKET'),
            'url' => env('AWS_IMAGE_CDN_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'options' => [
                'CacheControl' => 'max-age=2592000, public', // 30 days cache
            ],
        ],

        // Cloudflare R2 for global distribution
        'global_cdn' => [
            'driver' => 's3',
            'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
            'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('CLOUDFLARE_R2_BUCKET'),
            'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
            'url' => env('CLOUDFLARE_R2_PUBLIC_URL'),
            'use_path_style_endpoint' => false,
            'throw' => false,
            'options' => [
                'CacheControl' => 'max-age=31536000, public, immutable',
            ],
        ],
    ],
];
?>
```

### Bandwidth Management and Adaptive Streaming

Implement adaptive bitrate streaming for optimal user experience across different connection speeds.

```php
<?php
// app/Services/StreamingService.php

namespace App\Services;

use App\Models\Track;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class StreamingService
{
    private array $qualityLevels = [
        'low' => ['bitrate' => 128, 'format' => 'mp3'],
        'medium' => ['bitrate' => 256, 'format' => 'mp3'],
        'high' => ['bitrate' => 320, 'format' => 'mp3'],
        'lossless' => ['bitrate' => 1411, 'format' => 'flac'],
    ];

    public function getStreamingUrl(Track $track, Request $request): string
    {
        $quality = $this->determineOptimalQuality($request);
        $filename = $this->getQualityFilename($track, $quality);

        // Use CDN for audio delivery
        $disk = Storage::disk('audio_cdn');

        // Generate signed URL for secure streaming
        return $disk->temporaryUrl(
            $filename,
            now()->addHours(2), // 2-hour access
            [
                'ResponseContentType' => 'audio/mpeg',
                'ResponseContentDisposition' => 'inline',
                'ResponseCacheControl' => 'max-age=3600',
            ]
        );
    }

    private function determineOptimalQuality(Request $request): string
    {
        // Detect connection speed from user agent or previous analytics
        $userAgent = $request->userAgent();
        $connectionHint = $request->header('Save-Data');

        // Check user preferences
        $userQuality = auth()->user()?->preferred_audio_quality ?? 'medium';

        // Adaptive quality based on connection
        if ($connectionHint === 'on' || $this->isSlowConnection($request)) {
            return 'low';
        }

        if ($this->isMobileDevice($userAgent)) {
            return min($userQuality, 'medium');
        }

        return $userQuality;
    }

    private function isSlowConnection(Request $request): bool
    {
        // Check for slow connection indicators
        $effectiveType = $request->header('Network-Information-Effective-Type');
        return in_array($effectiveType, ['slow-2g', '2g', '3g']);
    }

    private function isMobileDevice(string $userAgent): bool
    {
        return preg_match('/Mobile|Android|iPhone|iPad/', $userAgent);
    }

    private function getQualityFilename(Track $track, string $quality): string
    {
        $config = $this->qualityLevels[$quality];
        return "tracks/{$track->public_id}/{$quality}.{$config['format']}";
    }

    public function preloadNextTracks(array $trackIds, string $quality = 'medium'): array
    {
        $preloadUrls = [];

        foreach (array_slice($trackIds, 0, 3) as $trackId) { // Preload next 3 tracks
            $track = Track::findBySecondaryKey($trackId);
            if ($track) {
                $preloadUrls[] = [
                    'track_id' => $track->public_id,
                    'url' => $this->getStreamingUrl($track, request()),
                    'quality' => $quality,
                ];
            }
        }

        return $preloadUrls;
    }
}
?>
```

### Progressive Loading and Buffering

Implement progressive loading strategies for smooth playback experience.

```javascript
// resources/js/components/audio-player.js

class AudioPlayer {
    constructor(trackData, options = {}) {
        this.track = trackData;
        this.audio = new Audio();
        this.bufferSize = options.bufferSize || 1024 * 1024; // 1MB buffer
        this.preloadBuffer = options.preloadBuffer || 3; // Preload 3 tracks
        this.qualityLevels = ['low', 'medium', 'high', 'lossless'];
        this.currentQuality = options.defaultQuality || 'medium';

        this.setupProgressiveLoading();
        this.setupAdaptiveQuality();
    }

    setupProgressiveLoading() {
        this.audio.preload = 'metadata'; // Load metadata first

        // Progressive loading based on user interaction
        this.audio.addEventListener('canplaythrough', () => {
            this.preloadNextTracks();
        });

        // Monitor buffer health
        this.audio.addEventListener('progress', () => {
            this.checkBufferHealth();
        });

        // Handle stalling
        this.audio.addEventListener('stalled', () => {
            this.handleStalling();
        });
    }

    setupAdaptiveQuality() {
        // Monitor connection quality
        if ('connection' in navigator) {
            navigator.connection.addEventListener('change', () => {
                this.adaptQuality();
            });
        }

        // Monitor playback performance
        this.audio.addEventListener('waiting', () => {
            this.bufferingEvents++;
            if (this.bufferingEvents > 3) {
                this.downgradeQuality();
            }
        });
    }

    async loadTrack(trackId, quality = null) {
        const targetQuality = quality || this.currentQuality;

        try {
            // Get streaming URL from backend
            const response = await fetch(`/api/tracks/${trackId}/stream`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    quality: targetQuality,
                    connection_info: this.getConnectionInfo(),
                }),
            });

            const data = await response.json();

            // Set audio source with range request support
            this.audio.src = data.streaming_url;

            // Record analytics
            this.recordStreamingEvent(trackId, targetQuality);

            return data;
        } catch (error) {
            console.error('Failed to load track:', error);
            // Fallback to lower quality
            if (targetQuality !== 'low') {
                return this.loadTrack(trackId, 'low');
            }
            throw error;
        }
    }

    checkBufferHealth() {
        if (this.audio.buffered.length > 0) {
            const bufferedEnd = this.audio.buffered.end(this.audio.buffered.length - 1);
            const currentTime = this.audio.currentTime;
            const bufferAhead = bufferedEnd - currentTime;

            // If buffer is low, preload more
            if (bufferAhead < 30) { // Less than 30 seconds buffered
                this.requestMoreBuffer();
            }
        }
    }

    async preloadNextTracks() {
        try {
            const response = await fetch('/api/player/preload', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    current_track: this.track.id,
                    quality: this.currentQuality,
                    count: this.preloadBuffer,
                }),
            });

            const preloadData = await response.json();

            // Create hidden audio elements for preloading
            preloadData.tracks.forEach(track => {
                const preloadAudio = new Audio();
                preloadAudio.preload = 'metadata';
                preloadAudio.src = track.url;

                // Store for quick access
                this.preloadedTracks = this.preloadedTracks || new Map();
                this.preloadedTracks.set(track.track_id, preloadAudio);
            });
        } catch (error) {
            console.error('Preload failed:', error);
        }
    }

    getConnectionInfo() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

        return {
            effective_type: connection?.effectiveType || 'unknown',
            downlink: connection?.downlink || null,
            rtt: connection?.rtt || null,
            save_data: connection?.saveData || false,
        };
    }

    adaptQuality() {
        const connection = navigator.connection;
        if (!connection) return;

        const { effectiveType, saveData } = connection;

        if (saveData || effectiveType === 'slow-2g' || effectiveType === '2g') {
            this.currentQuality = 'low';
        } else if (effectiveType === '3g') {
            this.currentQuality = 'medium';
        } else if (effectiveType === '4g') {
            this.currentQuality = 'high';
        }

        // Reload current track with new quality if playing
        if (!this.audio.paused) {
            const currentTime = this.audio.currentTime;
            this.loadTrack(this.track.id, this.currentQuality).then(() => {
                this.audio.currentTime = currentTime;
                this.audio.play();
            });
        }
    }

    recordStreamingEvent(trackId, quality) {
        // Record analytics for streaming optimization
        fetch('/api/analytics/streaming', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                track_id: trackId,
                quality: quality,
                connection_info: this.getConnectionInfo(),
                timestamp: new Date().toISOString(),
            }),
        }).catch(error => console.error('Analytics failed:', error));
    }
}

// Export for use in Livewire components
window.AudioPlayer = AudioPlayer;
```

## Best Practices

### Performance Checklist

1. **Component Design**
   - [ ] Minimize initial state
   - [ ] Use computed properties for derived data
   - [ ] Implement proper caching strategies
   - [ ] Use renderless actions for non-UI operations

2. **Database Optimization**
   - [ ] Select only needed columns
   - [ ] Use eager loading appropriately
   - [ ] Implement proper indexing
   - [ ] Use database-level aggregations

3. **Caching Strategy**
   - [ ] Implement multi-level caching
   - [ ] Use cache tags for invalidation
   - [ ] Cache expensive computations
   - [ ] Implement session-based caching

4. **Asset Optimization**
   - [ ] Optimize images with multiple sizes
   - [ ] Implement lazy loading
   - [ ] Use progressive image loading
   - [ ] Minimize JavaScript bundle size

5. **Memory Management**
   - [ ] Clean up large objects in dehydrate
   - [ ] Process large datasets in chunks
   - [ ] Remove temporary files
   - [ ] Force garbage collection when needed

6. **Network Optimization**
   - [ ] Configure CDN for audio and image delivery
   - [ ] Implement adaptive bitrate streaming
   - [ ] Use progressive loading for media content
   - [ ] Monitor and optimize buffer health
   - [ ] Implement connection-aware quality adjustment

## Navigation

**← Previous** [Accessibility and WCAG Compliance Guide](140-accessibility-wcag-guide.md)
**Next →** [Testing Approaches Guide](160-testing-approaches-guide.md)

---

*This guide provides comprehensive performance optimization strategies for Livewire/Volt components. Continue with the testing guide for comprehensive testing approaches.*
