# 1. SPA Navigation Implementation Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Livewire Navigate Setup](#2-livewire-navigate-setup)
- [3. Route Configuration](#3-route-configuration)
- [4. Navigation Patterns](#4-navigation-patterns)
- [5. State Management](#5-state-management)
- [6. Page Transitions](#6-page-transitions)
- [7. URL Synchronization](#7-url-synchronization)
- [8. History Management](#8-history-management)
- [9. Error Handling](#9-error-handling)
- [10. Performance Optimization](#10-performance-optimization)
- [11. SEO Considerations](#11-seo-considerations)
- [12. Testing Navigation](#12-testing-navigation)
- [13. Best Practices](#13-best-practices)
- [14. Navigation](#14-navigation)

## 1. Overview

This guide demonstrates how to implement Single Page Application (SPA) navigation in the Chinook application using Livewire Navigate. This approach provides seamless page transitions while maintaining the simplicity of server-side rendering and the benefits of Laravel's routing system.

## 2. Livewire Navigate Setup

### 2.1 Basic Configuration

```php
// config/livewire.php
return [
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2563eb',
        'timeout' => 60000,
    ],
];
```

### 2.2 Layout Integration

```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ $title ?? 'Chinook Music' }}</title>
    
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div id="app" class="min-h-screen bg-gray-50">
        <!-- Navigation Component -->
        <livewire:navigation />
        
        <!-- Main Content -->
        <main>
            {{ $slot }}
        </main>
        
        <!-- Global Components -->
        <livewire:music-player />
        <livewire:toast-notifications />
    </div>
    
    @fluxScripts
</body>
</html>
```

## 3. Route Configuration

### 3.1 SPA-Friendly Routes

```php
// routes/web.php
use Livewire\Volt\Volt;

// Dashboard
Volt::route('/', 'dashboard.index')->name('dashboard');

// Artist Routes
Route::prefix('artists')->name('artists.')->group(function () {
    Volt::route('/', 'artists.index')->name('index');
    Volt::route('/create', 'artists.create')->name('create');
    Volt::route('/{artist:slug}', 'artists.show')->name('show');
    Volt::route('/{artist:slug}/edit', 'artists.edit')->name('edit');
});

// Album Routes
Route::prefix('albums')->name('albums.')->group(function () {
    Volt::route('/', 'albums.index')->name('index');
    Volt::route('/create', 'albums.create')->name('create');
    Volt::route('/{album:slug}', 'albums.show')->name('show');
    Volt::route('/{album:slug}/edit', 'albums.edit')->name('edit');
});

// Track Routes
Route::prefix('tracks')->name('tracks.')->group(function () {
    Volt::route('/', 'tracks.index')->name('index');
    Volt::route('/{track:slug}', 'tracks.show')->name('show');
});

// Taxonomy Browse Routes
Route::prefix('browse')->name('browse.')->group(function () {
    Volt::route('/taxonomies', 'browse.taxonomies')->name('taxonomies');
    Volt::route('/taxonomy/{taxonomy:slug}', 'browse.taxonomy')->name('taxonomy');
    Volt::route('/genre/{taxonomy:slug}', 'browse.genre')->name('genre');
});

// Playlist Routes
Route::prefix('playlists')->name('playlists.')->group(function () {
    Volt::route('/', 'playlists.index')->name('index');
    Volt::route('/create', 'playlists.create')->name('create');
    Volt::route('/{playlist:slug}', 'playlists.show')->name('show');
});
```

### 3.2 Route Model Binding

```php
// app/Providers/RouteServiceProvider.php
public function boot(): void
{
    Route::model('artist', Artist::class);
    Route::model('album', Album::class);
    Route::model('track', Track::class);
    Route::model('taxonomy', \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class);
    
    // Custom route binding for slugs
    Route::bind('artist', function ($value) {
        return Artist::where('slug', $value)->firstOrFail();
    });
    
    Route::bind('album', function ($value) {
        return Album::where('slug', $value)->firstOrFail();
    });
    
    Route::bind('taxonomy', function ($value) {
        return \Aliziodev\LaravelTaxonomy\Models\Taxonomy::where('slug', $value)->firstOrFail();
    });
}
```

## 4. Navigation Patterns

### 4.1 Basic Navigation Component

```php
<?php
// resources/views/livewire/navigation.blade.php
use function Livewire\Volt\{state, computed};
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state(['mobileMenuOpen' => false, 'searchQuery' => '']);

$popularGenres = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->withCount('tracks')
        ->orderByDesc('tracks_count')
        ->limit(6)
        ->get();
});

$search = function () {
    if ($this->searchQuery) {
        return $this->redirect(route('search', ['q' => $this->searchQuery]), navigate: true);
    }
};
?>

<nav class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <flux:link href="{{ route('dashboard') }}" wire:navigate class="flex items-center">
                    <flux:icon name="musical-note" size="lg" class="text-blue-600" />
                    <flux:heading size="lg" class="ml-2">Chinook</flux:heading>
                </flux:link>
            </div>
            
            <!-- Main Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <flux:link href="{{ route('artists.index') }}" wire:navigate>Artists</flux:link>
                <flux:link href="{{ route('albums.index') }}" wire:navigate>Albums</flux:link>
                <flux:link href="{{ route('tracks.index') }}" wire:navigate>Tracks</flux:link>
                <flux:link href="{{ route('browse.taxonomies') }}" wire:navigate>Browse</flux:link>
                <flux:link href="{{ route('playlists.index') }}" wire:navigate>Playlists</flux:link>
            </div>
            
            <!-- Search -->
            <div class="flex items-center">
                <form wire:submit="search" class="flex">
                    <flux:input
                        wire:model="searchQuery"
                        placeholder="Search..."
                        icon="magnifying-glass"
                        class="w-64"
                    />
                </form>
            </div>
        </div>
        
        <!-- Genre Quick Links -->
        <div class="hidden md:flex py-2 space-x-4 border-t">
            <flux:text variant="muted" class="text-sm">Popular Genres:</flux:text>
            @foreach($this->popularGenres as $genre)
                <flux:link
                    href="{{ route('browse.genre', $genre) }}"
                    wire:navigate
                    class="text-sm text-gray-600 hover:text-blue-600"
                >
                    {{ $genre->name }}
                </flux:link>
            @endforeach
        </div>
    </div>
</nav>
```

### 4.2 Artist Listing with Navigation

```php
<?php
// resources/views/livewire/artists/index.blade.php
use function Livewire\Volt\{state, computed};
use App\Models\Artist;

state(['search' => '', 'selectedArtist' => null]);

$artists = computed(function () {
    return Artist::query()
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->with(['albums', 'taxonomies'])
        ->paginate(20);
});

// Navigation methods
$viewArtist = function ($artistSlug) {
    return $this->redirect(route('artists.show', $artistSlug), navigate: true);
};

$editArtist = function ($artistSlug) {
    return $this->redirect(route('artists.edit', $artistSlug), navigate: true);
};
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Artists</flux:heading>
        <flux:button
            href="{{ route('artists.create') }}"
            wire:navigate
            variant="primary"
            icon="plus"
        >
            Add Artist
        </flux:button>
    </div>
    
    <!-- Search -->
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search artists..."
        icon="magnifying-glass"
    />
    
    <!-- Artist Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($this->artists as $artist)
            <flux:card class="p-6 cursor-pointer hover:shadow-lg transition-shadow"
                       wire:click="viewArtist('{{ $artist->slug }}')">
                <div class="flex items-center space-x-4">
                    <flux:avatar
                        :src="$artist->getFirstMediaUrl('avatar')"
                        :alt="$artist->name"
                        size="lg"
                    />
                    <div class="flex-1">
                        <flux:heading size="lg">{{ $artist->name }}</flux:heading>
                        <flux:text variant="muted">{{ $artist->albums_count }} albums</flux:text>
                        
                        <!-- Taxonomies -->
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach($artist->taxonomies->take(3) as $taxonomy)
                                <flux:badge variant="subtle" size="sm">{{ $taxonomy->name }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>
    
    <!-- Pagination -->
    {{ $this->artists->links() }}
</div>
```

## 5. State Management

### 5.1 URL-Synchronized Filters

```php
<?php
// Track listing with URL-synced filters using aliziodev/laravel-taxonomy
use function Livewire\Volt\{state, computed};
use App\Models\Track;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

// URL-synchronized state
state([
    'search' => '',
    'genre' => null,
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'page' => 1
])->url();

$tracks = computed(function () {
    return Track::query()
        ->with(['album.artist', 'taxonomies'])
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->when($this->genre, function ($query) {
            $query->whereHasTaxonomies(function ($q) {
                $q->where('taxonomies.id', $this->genre);
            });
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate(25, ['*'], 'page', $this->page);
});

$availableGenres = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->orderBy('name')
        ->get();
});

// Filter actions
$setGenre = function ($genreId) {
    $this->genre = $genreId;
    $this->page = 1; // Reset pagination
};

$clearFilters = function () {
    $this->search = '';
    $this->genre = null;
    $this->page = 1;
};
?>

<div class="space-y-6">
    <!-- Filters -->
    <div class="flex flex-wrap gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search tracks..."
            icon="magnifying-glass"
            class="flex-1 min-w-64"
        />

        <flux:select wire:model.live="genre" placeholder="All Genres" class="w-48">
            @foreach($this->availableGenres as $genreOption)
                <flux:option value="{{ $genreOption->id }}">{{ $genreOption->name }}</flux:option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="sortBy" class="w-32">
            <flux:option value="name">Name</flux:option>
            <flux:option value="created_at">Date</flux:option>
            <flux:option value="milliseconds">Duration</flux:option>
        </flux:select>

        @if($search || $genre)
            <flux:button wire:click="clearFilters" variant="ghost" icon="x-mark">
                Clear
            </flux:button>
        @endif
    </div>

    <!-- Track List -->
    <div class="space-y-2">
        @foreach($this->tracks as $track)
            <flux:card class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <flux:button variant="ghost" size="sm" icon="play" />
                        <div>
                            <flux:heading size="sm">{{ $track->name }}</flux:heading>
                            <flux:text variant="muted">
                                {{ $track->artist->name }} • {{ $track->album->title }}
                            </flux:text>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach($track->taxonomies->take(2) as $taxonomy)
                                <flux:badge variant="subtle" size="sm">{{ $taxonomy->name }}</flux:badge>
                            @endforeach
                        </div>
                        <flux:text variant="muted" class="text-sm">
                            {{ gmdate('i:s', $track->milliseconds / 1000) }}
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    {{ $this->tracks->links() }}
</div>
```

### 5.2 Complex State with Nested Navigation

```php
<?php
// Album detail page with tabbed navigation
use function Livewire\Volt\{state, computed, mount};
use App\Models\Album;

state([
    'album' => null,
    'activeTab' => 'overview',
    'selectedTrack' => null
])->url(['activeTab' => 'tab', 'selectedTrack' => 'track']);

mount(function (Album $album, $tab = 'overview', $track = null) {
    $this->album = $album->load(['artist', 'tracks', 'taxonomies']);
    $this->activeTab = $tab;

    if ($track) {
        $this->selectedTrack = $this->album->tracks->where('slug', $track)->first();
    }
});

$setActiveTab = function ($tab) {
    $this->activeTab = $tab;
    $this->selectedTrack = null;
};

$selectTrack = function ($trackSlug) {
    $this->selectedTrack = $this->album->tracks->where('slug', $trackSlug)->first();
    $this->activeTab = 'tracks';
};

$playAlbum = function () {
    $this->dispatch('play-album', albumId: $this->album->id);
};
?>

<div class="space-y-6">
    <!-- Album Header -->
    <div class="flex items-start space-x-6">
        <flux:avatar
            :src="$album->getFirstMediaUrl('cover')"
            :alt="$album->title"
            size="2xl"
        />

        <div class="flex-1">
            <flux:heading size="2xl">{{ $album->title }}</flux:heading>
            <flux:link
                href="{{ route('artists.show', $album->artist) }}"
                wire:navigate
                class="text-lg text-blue-600 hover:text-blue-800"
            >
                {{ $album->artist->name }}
            </flux:link>

            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($album->taxonomies as $taxonomy)
                    <flux:badge variant="subtle">{{ $taxonomy->name }}</flux:badge>
                @endforeach
            </div>
        </div>

        <flux:button wire:click="playAlbum" variant="primary" icon="play">
            Play Album
        </flux:button>
    </div>

    <!-- Navigation Tabs -->
    <flux:tabs wire:model.live="activeTab">
        <flux:tab name="overview">Overview</flux:tab>
        <flux:tab name="tracks">Tracks</flux:tab>
        <flux:tab name="reviews">Reviews</flux:tab>
        <flux:tab name="similar">Similar Albums</flux:tab>
    </flux:tabs>

    <!-- Tab Content -->
    <div class="mt-6">
        @if($activeTab === 'overview')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Album Info -->
                <div class="lg:col-span-2">
                    <flux:card class="p-6">
                        <flux:heading size="lg" class="mb-4">Album Information</flux:heading>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <flux:text>Release Date</flux:text>
                                <flux:text>{{ $album->release_date?->format('F j, Y') ?? 'Unknown' }}</flux:text>
                            </div>
                            <div class="flex justify-between">
                                <flux:text>Total Tracks</flux:text>
                                <flux:text>{{ $album->tracks->count() }}</flux:text>
                            </div>
                            <div class="flex justify-between">
                                <flux:text>Total Duration</flux:text>
                                <flux:text>{{ gmdate('H:i:s', $album->tracks->sum('milliseconds') / 1000) }}</flux:text>
                            </div>
                        </div>
                    </flux:card>
                </div>

                <!-- Sidebar -->
                <div>
                    <flux:card class="p-6">
                        <flux:heading size="lg" class="mb-4">Taxonomies</flux:heading>
                        <div class="space-y-2">
                            @foreach($album->taxonomies->groupBy('type') as $type => $taxonomies)
                                <div>
                                    <flux:subheading>{{ ucfirst($type) }}</flux:subheading>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($taxonomies as $taxonomy)
                                            <flux:badge variant="subtle" size="sm">{{ $taxonomy->name }}</flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                </div>
            </div>
        @elseif($activeTab === 'tracks')
            <!-- Track listing implementation -->
            <div class="space-y-2">
                @foreach($album->tracks as $index => $track)
                    <flux:card
                        class="p-4 cursor-pointer hover:bg-gray-50"
                        wire:click="selectTrack('{{ $track->slug }}')"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <flux:text variant="muted" class="w-8">{{ $index + 1 }}</flux:text>
                                <div>
                                    <flux:heading size="sm">{{ $track->name }}</flux:heading>
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach($track->taxonomies->take(2) as $taxonomy)
                                            <flux:badge variant="subtle" size="xs">{{ $taxonomy->name }}</flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <flux:text variant="muted">
                                {{ gmdate('i:s', $track->milliseconds / 1000) }}
                            </flux:text>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>
</div>
```

## 6. Page Transitions

### 6.1 Custom Transition Effects

```css
/* resources/css/app.css */
[wire\:navigate\.transition] {
    transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
}

[wire\:navigate\.transition].navigate-out {
    opacity: 0;
    transform: translateX(-10px);
}

[wire\:navigate\.transition].navigate-in {
    opacity: 1;
    transform: translateX(0);
}

/* Page-specific transitions */
.page-transition-slide {
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.page-transition-fade {
    transition: opacity 0.3s ease-in-out;
}
```

### 6.2 Loading States

```php
<?php
// Global loading component
use function Livewire\Volt\{state, on};

state(['isNavigating' => false, 'progress' => 0]);

on(['navigate:start' => fn() => $this->isNavigating = true]);
on(['navigate:end' => fn() => $this->isNavigating = false]);
on(['navigate:progress' => fn($progress) => $this->progress = $progress]);
?>

@if($isNavigating)
    <div class="fixed top-0 left-0 right-0 z-50">
        <div class="h-1 bg-blue-600 transition-all duration-300"
             style="width: {{ $progress }}%"></div>
    </div>
@endif
```

## 7. URL Synchronization

### 7.1 Advanced URL State Management

```php
<?php
// Search page with complex URL state
use function Livewire\Volt\{state, computed};

state([
    'query' => '',
    'type' => 'all', // all, artists, albums, tracks
    'taxonomies' => [],
    'sortBy' => 'relevance',
    'page' => 1
])->url([
    'query' => 'q',
    'type' => 't',
    'taxonomies' => 'tax',
    'sortBy' => 'sort',
    'page' => 'p'
]);

$results = computed(function () {
    if (!$this->query) return collect();

    $results = collect();

    if (in_array($this->type, ['all', 'artists'])) {
        $artists = \App\Models\Artist::search($this->query)
            ->when($this->taxonomies, function ($query) {
                $query->whereHasTaxonomies($this->taxonomies);
            })
            ->get();
        $results = $results->merge($artists->map(fn($item) => ['type' => 'artist', 'item' => $item]));
    }

    if (in_array($this->type, ['all', 'albums'])) {
        $albums = \App\Models\Album::search($this->query)
            ->when($this->taxonomies, function ($query) {
                $query->whereHasTaxonomies($this->taxonomies);
            })
            ->get();
        $results = $results->merge($albums->map(fn($item) => ['type' => 'album', 'item' => $item]));
    }

    return $results->take(50);
});
?>
```

## 8. History Management

### 8.1 Custom History Handling

```javascript
// resources/js/navigation.js
document.addEventListener('livewire:init', () => {
    // Custom back button handling
    window.addEventListener('popstate', (event) => {
        if (event.state && event.state.livewire) {
            // Handle Livewire navigation state
            console.log('Navigating back to:', event.state);
        }
    });

    // Track navigation for analytics
    Livewire.on('navigate', (event) => {
        // Track page view
        if (typeof gtag !== 'undefined') {
            gtag('config', 'GA_MEASUREMENT_ID', {
                page_title: event.title,
                page_location: event.url
            });
        }
    });
});
```

## 9. Error Handling

### 9.1 Navigation Error Boundaries

```php
<?php
// Error boundary component
use function Livewire\Volt\{state, on};

state(['hasError' => false, 'errorMessage' => '']);

on(['navigate:error' => function ($error) {
    $this->hasError = true;
    $this->errorMessage = $error['message'] ?? 'Navigation failed';
}]);

$retry = function () {
    $this->hasError = false;
    $this->errorMessage = '';
    // Retry last navigation
    $this->dispatch('navigate:retry');
};
?>

@if($hasError)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <flux:card class="p-6 max-w-md">
            <flux:heading size="lg" class="mb-4">Navigation Error</flux:heading>
            <flux:text class="mb-4">{{ $errorMessage }}</flux:text>

            <div class="flex space-x-2">
                <flux:button wire:click="retry" variant="primary">
                    Retry
                </flux:button>
                <flux:button wire:click="$set('hasError', false)" variant="ghost">
                    Dismiss
                </flux:button>
            </div>
        </flux:card>
    </div>
@endif
```

## 10. Performance Optimization

### 10.1 Route Preloading

```javascript
// resources/js/preloader.js
document.addEventListener('livewire:init', () => {
    // Preload routes on hover
    document.addEventListener('mouseover', (event) => {
        const link = event.target.closest('[wire\\:navigate]');
        if (link && link.href) {
            // Preload the route
            fetch(link.href, {
                method: 'HEAD',
                headers: {
                    'X-Livewire': 'true'
                }
            }).catch(() => {
                // Ignore preload errors
            });
        }
    });
});
```

### 10.2 Component Lazy Loading

```php
<?php
// Lazy loaded component
use function Livewire\Volt\{state, computed};

state(['isVisible' => false]);

$expensiveData = computed(function () {
    if (!$this->isVisible) {
        return null;
    }

    // Load expensive data only when visible
    return \App\Models\Track::with(['artist', 'album', 'taxonomies'])
        ->latest()
        ->limit(20)
        ->get();
})->persist(seconds: 300);
?>

<div x-data="{
    observer: null,
    init() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    $wire.set('isVisible', true);
                    this.observer.disconnect();
                }
            });
        });
        this.observer.observe(this.$el);
    }
}" class="min-h-96">
    @if($this->expensiveData)
        <!-- Render data -->
        @foreach($this->expensiveData as $track)
            <div>{{ $track->name }}</div>
        @endforeach
    @else
        <div class="flex items-center justify-center h-96">
            <flux:spinner size="lg" />
        </div>
    @endif
</div>
```

## 11. SEO Considerations

### 11.1 Dynamic Meta Tags

```php
<?php
// Artist detail page with SEO optimization
use function Livewire\Volt\{state, mount, computed};
use App\Models\Artist;

state(['artist' => null]);

mount(function (Artist $artist) {
    $this->artist = $artist->load(['albums', 'taxonomies']);
});

// Layout with meta tags
layout('layouts.app', [
    'meta' => [
        'description' => fn() => "Listen to {$this->artist->name} on Chinook Music. {$this->artist->biography}",
        'keywords' => fn() => implode(', ', $this->artist->taxonomies->pluck('name')->toArray()),
        'og:title' => fn() => $this->artist->name,
        'og:description' => fn() => $this->artist->biography,
        'og:image' => fn() => $this->artist->getFirstMediaUrl('avatar'),
        'og:url' => fn() => route('artists.show', $this->artist->slug)
    ]
]);
?>

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "MusicGroup",
    "name": "{{ $artist->name }}",
    "description": "{{ $artist->biography }}",
    "image": "{{ $artist->getFirstMediaUrl('avatar') }}",
    "url": "{{ route('artists.show', $artist->slug) }}",
    "foundingDate": "{{ $artist->formed_year }}",
    "genre": [
        @foreach($artist->taxonomies->where('type', 'genre') as $genre)
            "{{ $genre->name }}"{{ !$loop->last ? ',' : '' }}
        @endforeach
    ]
}
</script>
```

## 12. Testing Navigation

### 12.1 Navigation Testing

```php
<?php
// tests/Feature/NavigationTest.php
use App\Models\Artist;
use Livewire\Volt\Volt;

test('artist navigation works correctly', function () {
    $artist = Artist::factory()->create(['slug' => 'test-artist']);

    $this->get(route('artists.index'))
        ->assertOk()
        ->assertSee($artist->name);

    $this->get(route('artists.show', $artist))
        ->assertOk()
        ->assertSee($artist->name);
});

test('SPA navigation maintains state', function () {
    $artist = Artist::factory()->create();

    Volt::test('artists.index')
        ->set('search', 'test')
        ->call('viewArtist', $artist->slug)
        ->assertRedirect(route('artists.show', $artist));
});
?>
```

## 13. Best Practices

### 13.1 Navigation Guidelines

1. **Consistent Navigation**: Use wire:navigate consistently across the application
2. **URL State**: Sync important state with URL for bookmarkability
3. **Loading States**: Provide clear feedback during navigation
4. **Error Handling**: Implement proper error boundaries
5. **Performance**: Use preloading and lazy loading strategically
6. **SEO**: Include proper meta tags and structured data

### 13.2 Code Organization

1. **Route Grouping**: Organize routes logically with proper naming
2. **State Management**: Use URL synchronization for important state
3. **Component Reuse**: Create reusable navigation components
4. **Error Boundaries**: Implement error boundaries for navigation failures

## 14. Navigation

**← Previous** [Flux/Flux-Pro Component Integration Guide](120-flux-component-integration-guide.md)
**Next →** [Accessibility and WCAG Compliance Guide](140-accessibility-wcag-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/130-spa-navigation-guide.md on 2025-07-11

*This guide provides comprehensive patterns for implementing SPA navigation with Livewire Navigate. Continue with the accessibility guide for WCAG 2.1 AA compliance strategies.*

[⬆️ Back to Top](#1-spa-navigation-implementation-guide)
