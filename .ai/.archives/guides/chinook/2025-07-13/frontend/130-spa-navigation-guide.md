# SPA Navigation Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Livewire Navigate Setup](#livewire-navigate-setup)
- [Route Configuration](#route-configuration)
- [Navigation Patterns](#navigation-patterns)
- [State Management](#state-management)
- [Page Transitions](#page-transitions)
- [URL Synchronization](#url-synchronization)
- [History Management](#history-management)
- [Error Handling](#error-handling)
- [Performance Optimization](#performance-optimization)
- [SEO Considerations](#seo-considerations)
- [Testing Navigation](#testing-navigation)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide demonstrates how to implement Single Page Application (SPA) navigation in the Chinook application using Livewire Navigate. This approach provides seamless page transitions while maintaining the simplicity of server-side rendering and the benefits of Laravel's routing system.

## Livewire Navigate Setup

### Basic Configuration

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

### Layout Integration

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

## Route Configuration

### SPA-Friendly Routes

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
    Volt::route('/create', 'tracks.create')->name('create');
    Volt::route('/{track:slug}', 'tracks.show')->name('show');
    Volt::route('/{track:slug}/edit', 'tracks.edit')->name('edit');
});

// API Routes for AJAX requests
Route::prefix('api')->group(function () {
    Route::get('/search', [SearchController::class, 'search'])->name('api.search');
    Route::post('/tracks/{track}/play', [TrackController::class, 'play'])->name('api.tracks.play');
});
```

## Navigation Patterns

### Basic Navigation Component

```php
<?php
// resources/views/livewire/navigation.blade.php
use function Livewire\Volt\{state, computed};

state(['currentRoute' => fn() => request()->route()->getName()]);

$navigationItems = computed(function () {
    return [
        [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'home',
            'active' => str_starts_with($this->currentRoute, 'dashboard')
        ],
        [
            'name' => 'Artists',
            'route' => 'artists.index',
            'icon' => 'user-group',
            'active' => str_starts_with($this->currentRoute, 'artists.')
        ],
        [
            'name' => 'Albums',
            'route' => 'albums.index',
            'icon' => 'musical-note',
            'active' => str_starts_with($this->currentRoute, 'albums.')
        ],
        [
            'name' => 'Tracks',
            'route' => 'tracks.index',
            'icon' => 'play',
            'active' => str_starts_with($this->currentRoute, 'tracks.')
        ]
    ];
});
?>

<nav class="bg-white shadow-sm border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <flux:brand wire:navigate href="{{ route('dashboard') }}">
                    <x-logo class="h-8 w-auto" />
                    Chinook Music
                </flux:brand>
            </div>
            
            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                @foreach($this->navigationItems as $item)
                    <flux:button
                        wire:navigate
                        href="{{ route($item['route']) }}"
                        :variant="$item['active'] ? 'primary' : 'ghost'"
                        icon="{{ $item['icon'] }}"
                    >
                        {{ $item['name'] }}
                    </flux:button>
                @endforeach
            </div>
            
            <!-- Search -->
            <div class="flex items-center">
                <livewire:global-search />
            </div>
        </div>
    </div>
</nav>
```

### Programmatic Navigation

```php
<?php
// Artist listing with navigation actions
use function Livewire\Volt\{state, computed};
use App\Models\Artist;

state(['search' => '', 'selectedArtist' => null]);

$artists = computed(function () {
    return Artist::query()
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->with(['albums', 'categories'])
        ->paginate(20);
});

// Navigation methods
$viewArtist = function ($artistSlug) {
    return $this->redirect(route('artists.show', $artistSlug), navigate: true);
};

$editArtist = function ($artistSlug) {
    return $this->redirect(route('artists.edit', $artistSlug), navigate: true);
};

$createArtist = function () {
    return $this->redirect(route('artists.create'), navigate: true);
};

// Conditional navigation
$navigateToArtistOrCreate = function ($artistId = null) {
    if ($artistId) {
        $artist = Artist::find($artistId);
        return $this->redirect(route('artists.show', $artist->slug), navigate: true);
    } else {
        return $this->redirect(route('artists.create'), navigate: true);
    }
};
?>

<div class="space-y-6">
    <!-- Header with Actions -->
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Artists</flux:heading>
        
        <flux:button 
            wire:click="createArtist"
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
            <flux:card wire:key="artist-{{ $artist->id }}">
                <div class="p-6">
                    <flux:heading size="lg" class="mb-2">{{ $artist->name }}</flux:heading>
                    <flux:text variant="muted">{{ $artist->albums_count }} albums</flux:text>
                    
                    <div class="flex space-x-2 mt-4">
                        <flux:button 
                            wire:click="viewArtist('{{ $artist->slug }}')"
                            variant="primary"
                            size="sm"
                        >
                            View
                        </flux:button>
                        
                        <flux:button 
                            wire:click="editArtist('{{ $artist->slug }}')"
                            variant="ghost"
                            size="sm"
                        >
                            Edit
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>
    
    <!-- Pagination with Navigate -->
    <div class="flex justify-center">
        {{ $this->artists->links() }}
    </div>
</div>
```

## State Management

### URL State Synchronization

```php
<?php
// Track listing with URL-synced filters
use function Livewire\Volt\{state, computed};
use App\Models\Track;
use App\Enums\CategoryType;

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
        ->with(['album.artist', 'categories'])
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->when($this->genre, function ($query) {
            $query->whereHas('categories', function ($q) {
                $q->where('categories.id', $this->genre)
                  ->where('categories.type', CategoryType::GENRE);
            });
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate(25, ['*'], 'page', $this->page);
});

// Navigation with state preservation
$navigateToTrack = function ($trackSlug) {
    // Preserve current filters in session
    session(['track_filters' => [
        'search' => $this->search,
        'genre' => $this->genre,
        'sortBy' => $this->sortBy,
        'sortDirection' => $this->sortDirection
    ]]);
    
    return $this->redirect(route('tracks.show', $trackSlug), navigate: true);
};

// Restore filters when returning
$restoreFilters = function () {
    $filters = session('track_filters', []);
    
    $this->search = $filters['search'] ?? '';
    $this->genre = $filters['genre'] ?? null;
    $this->sortBy = $filters['sortBy'] ?? 'name';
    $this->sortDirection = $filters['sortDirection'] ?? 'asc';
};
?>
```

### Global State Management

```php
<?php
// Global application state
use function Livewire\Volt\{state, on};

state([
    'currentUser' => fn() => auth()->user(),
    'currentTrack' => null,
    'isPlaying' => false,
    'notifications' => []
]);

// Global event listeners
on([
    'user-updated' => function ($userData) {
        $this->currentUser = auth()->user()->fresh();
    },
    
    'track-changed' => function ($trackId) {
        $this->currentTrack = \App\Models\Track::find($trackId);
    },
    
    'notification-added' => function ($notification) {
        $this->notifications[] = $notification;
    }
]);

// Global actions
$logout = function () {
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();
    
    return $this->redirect(route('login'), navigate: true);
};
?>
```

## Page Transitions

### Custom Transition Effects

```css
/* resources/css/app.css */

/* Page transition animations */
[wire\\:navigate] {
    transition: all 0.3s ease-in-out;
}

/* Loading states */
.livewire-navigate-loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Custom progress bar */
.livewire-progress-bar {
    height: 3px;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    border-radius: 0 0 2px 2px;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
}

/* Fade transitions */
.page-enter {
    opacity: 0;
    transform: translateY(10px);
}

.page-enter-active {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.page-leave {
    opacity: 1;
    transform: translateY(0);
}

.page-leave-active {
    opacity: 0;
    transform: translateY(-10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}
```

### JavaScript Integration

```javascript
// resources/js/app.js
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Navigation event listeners
document.addEventListener('livewire:navigate', (event) => {
    // Show loading indicator
    document.body.classList.add('navigating');
    
    // Track navigation analytics
    if (window.gtag) {
        gtag('event', 'page_view', {
            page_title: event.detail.url,
            page_location: window.location.href
        });
    }
});

document.addEventListener('livewire:navigated', (event) => {
    // Hide loading indicator
    document.body.classList.remove('navigating');
    
    // Update page title
    if (event.detail.title) {
        document.title = event.detail.title;
    }
    
    // Scroll to top
    window.scrollTo(0, 0);
    
    // Re-initialize third-party libraries
    initializeThirdPartyLibraries();
});

// Handle navigation errors
document.addEventListener('livewire:navigate-error', (event) => {
    console.error('Navigation error:', event.detail);
    
    // Show error message
    showToast('Navigation failed. Please try again.', 'error');
});

function initializeThirdPartyLibraries() {
    // Re-initialize any third-party libraries that need it
    if (window.initializeCharts) {
        window.initializeCharts();
    }
    
    if (window.initializeTooltips) {
        window.initializeTooltips();
    }
}

function showToast(message, type = 'info') {
    Livewire.dispatch('toast', { message, type });
}

Livewire.start();
```

## URL Synchronization

### Deep Linking Support

```php
<?php
// Album detail page with deep linking
use function Livewire\Volt\{state, mount, computed};
use App\Models\Album;

state([
    'album' => null,
    'activeTab' => 'overview',
    'selectedTrack' => null
])->url(['activeTab' => 'tab', 'selectedTrack' => 'track']);

mount(function (Album $album, $tab = 'overview', $track = null) {
    $this->album = $album->load(['artist', 'tracks', 'categories']);
    $this->activeTab = $tab;
    
    if ($track) {
        $this->selectedTrack = $this->album->tracks->where('slug', $track)->first();
    }
});

$selectTrack = function ($trackSlug) {
    $this->selectedTrack = $this->album->tracks->where('slug', $trackSlug)->first();
    
    // Update URL without full page reload
    $this->dispatch('url-updated', [
        'tab' => $this->activeTab,
        'track' => $trackSlug
    ]);
};

$changeTab = function ($tab) {
    $this->activeTab = $tab;
    $this->selectedTrack = null;
};
?>

<div class="max-w-6xl mx-auto space-y-8">
    <!-- Album Header -->
    <div class="flex items-start space-x-8">
        <img 
            src="{{ $album->getFirstMediaUrl('cover') }}" 
            alt="{{ $album->title }}"
            class="w-64 h-64 rounded-lg shadow-lg"
        />
        
        <div class="flex-1">
            <flux:heading size="2xl">{{ $album->title }}</flux:heading>
            <flux:text size="lg" variant="muted">{{ $album->artist->name }}</flux:text>
            
            <!-- Share URL -->
            <div class="mt-4">
                <flux:button 
                    wire:click="copyUrl"
                    variant="ghost"
                    icon="share"
                    size="sm"
                >
                    Share Album
                </flux:button>
            </div>
        </div>
    </div>
    
    <!-- Tabs with URL sync -->
    <flux:tabs wire:model="activeTab">
        <flux:tab name="overview">Overview</flux:tab>
        <flux:tab name="tracks">Tracks</flux:tab>
        <flux:tab name="reviews">Reviews</flux:tab>
    </flux:tabs>
    
    <!-- Tab Content -->
    @if($activeTab === 'tracks')
        <div class="space-y-4">
            @foreach($album->tracks as $track)
                <flux:card 
                    wire:click="selectTrack('{{ $track->slug }}')"
                    class="p-4 cursor-pointer hover:shadow-md transition-shadow
                           {{ $selectedTrack?->id === $track->id ? 'ring-2 ring-blue-500' : '' }}"
                >
                    <div class="flex justify-between items-center">
                        <div>
                            <flux:heading size="sm">{{ $track->name }}</flux:heading>
                            <flux:text variant="muted">
                                {{ gmdate('i:s', $track->milliseconds / 1000) }}
                            </flux:text>
                        </div>
                        
                        <flux:button 
                            wire:click.stop="playTrack({{ $track->id }})"
                            variant="ghost"
                            icon="play"
                            size="sm"
                        />
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
```

## History Management

### Browser History Integration

```php
<?php
// Search component with history management
use function Livewire\Volt\{state, computed};

state([
    'query' => '',
    'filters' => [],
    'results' => [],
    'searchHistory' => fn() => session('search_history', [])
]);

$search = function () {
    if (empty($this->query)) return;

    // Add to search history
    $history = $this->searchHistory;
    array_unshift($history, [
        'query' => $this->query,
        'timestamp' => now()->toISOString(),
        'filters' => $this->filters
    ]);

    // Keep only last 10 searches
    $history = array_slice($history, 0, 10);
    session(['search_history' => $history]);

    // Perform search
    $this->results = $this->performSearch();

    // Update URL with search state
    $this->dispatch('update-url', [
        'q' => $this->query,
        'filters' => $this->filters
    ]);
};

$loadFromHistory = function ($index) {
    $historyItem = $this->searchHistory[$index] ?? null;

    if ($historyItem) {
        $this->query = $historyItem['query'];
        $this->filters = $historyItem['filters'];
        $this->search();
    }
};

$clearHistory = function () {
    session()->forget('search_history');
    $this->searchHistory = [];
};
?>

<div class="space-y-6">
    <!-- Search Input -->
    <div class="relative">
        <flux:input
            wire:model="query"
            wire:keydown.enter="search"
            placeholder="Search artists, albums, tracks..."
            icon="magnifying-glass"
        />

        <!-- Search History Dropdown -->
        @if(count($searchHistory) > 0)
            <flux:dropdown>
                <flux:button
                    variant="ghost"
                    icon="clock"
                    size="sm"
                    class="absolute right-2 top-2"
                />

                <flux:menu>
                    <flux:menu.item disabled>Recent Searches</flux:menu.item>
                    <flux:menu.separator />

                    @foreach($searchHistory as $index => $item)
                        <flux:menu.item wire:click="loadFromHistory({{ $index }})">
                            {{ $item['query'] }}
                            <flux:text variant="muted" size="sm">
                                {{ \Carbon\Carbon::parse($item['timestamp'])->diffForHumans() }}
                            </flux:text>
                        </flux:menu.item>
                    @endforeach

                    <flux:menu.separator />
                    <flux:menu.item wire:click="clearHistory" variant="danger">
                        Clear History
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <!-- Search Results -->
    @if(count($results) > 0)
        <div class="space-y-4">
            @foreach($results as $result)
                <flux:card class="p-4">
                    <flux:heading size="sm">{{ $result['title'] }}</flux:heading>
                    <flux:text variant="muted">{{ $result['type'] }}</flux:text>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
```

## Error Handling

### Navigation Error Recovery

```php
<?php
// Error handling component
use function Livewire\Volt\{state, on};

state([
    'hasError' => false,
    'errorMessage' => '',
    'lastWorkingUrl' => null
]);

on([
    'navigation-error' => function ($error, $url) {
        $this->hasError = true;
        $this->errorMessage = $error;
        $this->lastWorkingUrl = session('last_working_url', route('dashboard'));
    },

    'navigation-success' => function ($url) {
        $this->hasError = false;
        $this->errorMessage = '';
        session(['last_working_url' => $url]);
    }
]);

$retry = function () {
    $this->hasError = false;
    $this->errorMessage = '';

    // Attempt to navigate to the last working URL
    return $this->redirect($this->lastWorkingUrl, navigate: true);
};

$goHome = function () {
    $this->hasError = false;
    return $this->redirect(route('dashboard'), navigate: true);
};
?>

@if($hasError)
    <flux:modal wire:model="hasError" variant="danger">
        <flux:modal.header>
            <flux:heading size="lg">Navigation Error</flux:heading>
        </flux:modal.header>

        <div class="p-6">
            <flux:text>{{ $errorMessage }}</flux:text>
            <flux:text variant="muted" class="mt-2">
                We're sorry, but something went wrong while navigating to the requested page.
            </flux:text>
        </div>

        <flux:modal.footer>
            <flux:button wire:click="retry" variant="primary">
                Try Again
            </flux:button>
            <flux:button wire:click="goHome" variant="ghost">
                Go to Dashboard
            </flux:button>
        </flux:modal.footer>
    </flux:modal>
@endif
```

## Performance Optimization

### Preloading and Caching

```php
<?php
// Navigation with preloading
use function Livewire\Volt\{state, computed};

state(['preloadedRoutes' => []]);

$preloadRoute = function ($route) {
    if (!in_array($route, $this->preloadedRoutes)) {
        $this->preloadedRoutes[] = $route;

        // Dispatch preload event to JavaScript
        $this->dispatch('preload-route', route: $route);
    }
};

$navigationItems = computed(function () {
    return [
        ['name' => 'Artists', 'route' => 'artists.index'],
        ['name' => 'Albums', 'route' => 'albums.index'],
        ['name' => 'Tracks', 'route' => 'tracks.index']
    ];
});
?>

<nav class="space-y-2">
    @foreach($this->navigationItems as $item)
        <flux:button
            wire:navigate
            href="{{ route($item['route']) }}"
            wire:mouseenter="preloadRoute('{{ $item['route'] }}')"
            class="w-full justify-start"
        >
            {{ $item['name'] }}
        </flux:button>
    @endforeach
</nav>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('preload-route', (event) => {
        // Preload the route in the background
        fetch(event.route, {
            method: 'GET',
            headers: {
                'X-Livewire-Preload': 'true'
            }
        });
    });
});
</script>
```

## SEO Considerations

### Meta Tags and Social Sharing

```php
<?php
// SEO-friendly page component
use function Livewire\Volt\{state, mount, title, layout};
use App\Models\Artist;

state(['artist' => null]);

mount(function (Artist $artist) {
    $this->artist = $artist;
});

// Dynamic page title
title(fn() => $this->artist->name . ' - Chinook Music');

// Layout with meta tags
layout('layouts.app', [
    'meta' => [
        'description' => fn() => "Listen to {$this->artist->name} on Chinook Music. {$this->artist->biography}",
        'keywords' => fn() => implode(', ', $this->artist->categories->pluck('name')->toArray()),
        'og:title' => fn() => $this->artist->name,
        'og:description' => fn() => $this->artist->biography,
        'og:image' => fn() => $this->artist->getFirstMediaUrl('avatar'),
        'og:url' => fn() => route('artists.show', $this->artist->slug)
    ]
]);
?>

<div class="max-w-4xl mx-auto">
    <!-- Structured Data -->
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
            @foreach($artist->categories->where('type', 'genre') as $genre)
                "{{ $genre->name }}"{{ !$loop->last ? ',' : '' }}
            @endforeach
        ]
    }
    </script>

    <!-- Artist Content -->
    <flux:heading size="2xl">{{ $artist->name }}</flux:heading>
    <flux:text>{{ $artist->biography }}</flux:text>
</div>
```

## Testing Navigation

### Feature Tests

```php
// tests/Feature/NavigationTest.php
use Livewire\Volt\Volt;

it('navigates between pages correctly', function () {
    $artist = Artist::factory()->create();

    // Test navigation to artist page
    $this->get(route('artists.index'))
        ->assertOk()
        ->assertSee('Artists');

    // Test navigation to specific artist
    $this->get(route('artists.show', $artist->slug))
        ->assertOk()
        ->assertSee($artist->name);
});

it('preserves state during navigation', function () {
    Volt::test('artists.index')
        ->set('search', 'test artist')
        ->call('navigateToArtist', 'artist-slug')
        ->assertRedirect(route('artists.show', 'artist-slug'));
});

it('handles navigation errors gracefully', function () {
    $this->get(route('artists.show', 'non-existent-slug'))
        ->assertStatus(404);
});
```

## Best Practices

### Navigation Guidelines

1. **Consistent Patterns**: Use consistent navigation patterns throughout the application
2. **Loading States**: Provide visual feedback during navigation
3. **Error Handling**: Implement graceful error handling and recovery
4. **Performance**: Optimize navigation performance with preloading and caching
5. **Accessibility**: Ensure navigation is accessible to all users
6. **SEO**: Implement proper meta tags and structured data
7. **Testing**: Write comprehensive tests for navigation functionality

### Code Organization

1. **Route Grouping**: Organize routes logically with proper naming
2. **State Management**: Use URL synchronization for important state
3. **Component Reuse**: Create reusable navigation components
4. **Error Boundaries**: Implement error boundaries for navigation failures

## Navigation

**← Previous** [Flux/Flux-Pro Component Integration Guide](120-flux-component-integration-guide.md)
**Next →** [Accessibility and WCAG Compliance Guide](140-accessibility-wcag-guide.md)

---

*This guide provides comprehensive patterns for implementing SPA navigation with Livewire Navigate. Continue with the accessibility guide for WCAG 2.1 AA compliance strategies.*
