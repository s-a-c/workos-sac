# 1. Livewire/Volt Integration Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Functional Component Architecture](#2-functional-component-architecture)
- [3. Volt Integration Patterns](#3-volt-integration-patterns)
- [4. Real-time Features](#4-real-time-features)
- [5. State Management](#5-state-management)
- [6. Component Communication](#6-component-communication)
- [7. Performance Optimization](#7-performance-optimization)
- [8. Testing Strategies](#8-testing-strategies)
- [9. Best Practices](#9-best-practices)
- [10. Navigation](#10-navigation)

## 1. Overview

This guide provides comprehensive coverage of Livewire/Volt integration patterns for the Chinook application, focusing on functional components, real-time features, and seamless integration with Filament admin panels using the aliziodev/laravel-taxonomy system.

## 2. Functional Component Architecture

### 2.1 Volt Functional Components

Volt enables functional programming patterns in Livewire, providing cleaner and more maintainable code:

```php
<?php
// resources/views/livewire/music/artist-browser.blade.php
use function Livewire\Volt\{state, computed, with, mount};
use Livewire\WithPagination;
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

// Component state
state([
    'search' => '',
    'selectedTaxonomies' => [],
    'sortBy' => 'name',
    'sortDirection' => 'asc'
]);

// Computed properties for data
$artists = computed(function () {
    return Artist::query()
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->when($this->selectedTaxonomies, function ($query) {
            $query->whereHasTaxonomies($this->selectedTaxonomies);
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->with(['taxonomies', 'albums'])
        ->paginate(12);
});

$availableTaxonomies = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->orderBy('name')
        ->get();
});

// Lifecycle hooks
mount(function () {
    // Initialize component
});

// Actions
$updatedSearch = function () {
    $this->resetPage();
};

$updatedSelectedTaxonomies = function () {
    $this->resetPage();
};

$sortBy = function (string $field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};
?>

<div class="artist-browser">
    <!-- Search and Filters -->
    <div class="filters mb-6">
        <div class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-64">
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search artists..."
                    icon="magnifying-glass"
                />
            </div>
            
            <div class="min-w-48">
                <flux:select 
                    wire:model.live="selectedTaxonomies"
                    multiple
                    placeholder="Filter by genres..."
                >
                    @foreach($this->availableTaxonomies as $taxonomy)
                        <flux:option value="{{ $taxonomy->id }}">{{ $taxonomy->name }}</flux:option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Artist Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($this->artists as $artist)
            <livewire:music.artist-card :artist="$artist" :key="$artist->id" />
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $this->artists->links() }}
    </div>
</div>
```

### 2.2 Artist Card Component

```php
<?php
// resources/views/livewire/music/artist-card.blade.php
use function Livewire\Volt\{state, mount};
use App\Models\Artist;

state(['artist' => null, 'isFavorite' => false]);

mount(function (Artist $artist) {
    $this->artist = $artist->load(['taxonomies', 'albums']);
    $this->isFavorite = auth()->user()?->favorites()->where('artist_id', $artist->id)->exists() ?? false;
});

$toggleFavorite = function () {
    $user = auth()->user();
    if (!$user) return;

    if ($this->isFavorite) {
        $user->favorites()->where('artist_id', $this->artist->id)->delete();
        $this->isFavorite = false;
        $this->dispatch('favorite-removed', artistId: $this->artist->id);
    } else {
        $user->favorites()->create(['artist_id' => $this->artist->id]);
        $this->isFavorite = true;
        $this->dispatch('favorite-added', artistId: $this->artist->id);
    }
};

$viewArtist = function () {
    return $this->redirect(route('artists.show', $this->artist), navigate: true);
};
?>

<flux:card class="artist-card hover:shadow-lg transition-shadow duration-200">
    <div class="relative">
        <!-- Artist Image -->
        <div class="aspect-square overflow-hidden rounded-t-lg">
            <img 
                src="{{ $artist->getFirstMediaUrl('avatar') ?: '/default-artist.jpg' }}"
                alt="{{ $artist->name }}"
                class="w-full h-full object-cover hover:scale-105 transition-transform duration-200 cursor-pointer"
                wire:click="viewArtist"
                loading="lazy"
            />
        </div>
        
        <!-- Favorite Button -->
        <flux:button
            wire:click="toggleFavorite"
            variant="{{ $isFavorite ? 'primary' : 'ghost' }}"
            size="sm"
            icon="{{ $isFavorite ? 'heart-solid' : 'heart' }}"
            class="absolute top-2 right-2"
            aria-label="{{ $isFavorite ? 'Remove from favorites' : 'Add to favorites' }}"
        />
    </div>
    
    <div class="p-4">
        <!-- Artist Name -->
        <h3 class="font-semibold text-lg mb-2">
            <button 
                wire:click="viewArtist"
                class="hover:text-blue-600 transition-colors text-left w-full"
            >
                {{ $artist->name }}
            </button>
        </h3>
        
        <!-- Taxonomies -->
        @if($artist->taxonomies->isNotEmpty())
            <div class="flex flex-wrap gap-1 mb-2">
                @foreach($artist->taxonomies->take(3) as $taxonomy)
                    <flux:badge variant="subtle" size="sm">
                        {{ $taxonomy->name }}
                    </flux:badge>
                @endforeach
                @if($artist->taxonomies->count() > 3)
                    <flux:badge variant="outline" size="sm">
                        +{{ $artist->taxonomies->count() - 3 }} more
                    </flux:badge>
                @endif
            </div>
        @endif
        
        <!-- Album Count -->
        <flux:text variant="muted" class="text-sm">
            {{ $artist->albums->count() }} {{ Str::plural('album', $artist->albums->count()) }}
        </flux:text>
    </div>
</flux:card>
```

## 3. Volt Integration Patterns

### 3.1 Advanced State Management

```php
<?php
// Complex state management with URL synchronization
use function Livewire\Volt\{state, computed};

// URL-synchronized state
state([
    'filters' => [
        'search' => '',
        'taxonomies' => [],
        'country' => null,
        'decade' => null
    ],
    'view' => 'grid', // grid, list, table
    'sortBy' => 'name',
    'sortDirection' => 'asc'
])->url();

// Computed properties with caching
$filteredArtists = computed(function () {
    return Artist::query()
        ->when($this->filters['search'], function ($query) {
            $query->where('name', 'like', "%{$this->filters['search']}%")
                  ->orWhere('biography', 'like', "%{$this->filters['search']}%");
        })
        ->when($this->filters['taxonomies'], function ($query) {
            $query->whereHasTaxonomies($this->filters['taxonomies']);
        })
        ->when($this->filters['country'], function ($query) {
            $query->where('country', $this->filters['country']);
        })
        ->when($this->filters['decade'], function ($query) {
            $query->whereBetween('formed_year', [
                $this->filters['decade'],
                $this->filters['decade'] + 9
            ]);
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->with(['taxonomies', 'albums'])
        ->paginate(20);
})->persist(seconds: 300);

// Filter actions
$updateFilter = function ($key, $value) {
    $this->filters[$key] = $value;
    $this->resetPage();
};

$clearFilters = function () {
    $this->filters = [
        'search' => '',
        'taxonomies' => [],
        'country' => null,
        'decade' => null
    ];
    $this->resetPage();
};

$toggleView = function ($view) {
    $this->view = $view;
};
?>
```

### 3.2 Form Integration with Validation

```php
<?php
// Advanced form handling with taxonomy selection
use function Livewire\Volt\{state, form, rules};
use App\Livewire\Forms\ArtistForm;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

// Use dedicated form class
form(ArtistForm::class);

// Additional component state
state([
    'selectedTaxonomies' => [],
    'step' => 1,
    'totalSteps' => 3
]);

// Validation rules
rules([
    'selectedTaxonomies' => 'array|max:10',
    'selectedTaxonomies.*' => 'exists:taxonomies,id'
]);

// Available taxonomies grouped by type
$taxonomyGroups = computed(function () {
    return Taxonomy::whereIn('type', ['genre', 'style', 'mood'])
        ->orderBy('type')
        ->orderBy('name')
        ->get()
        ->groupBy('type');
});

// Step navigation
$nextStep = function () {
    $this->validateCurrentStep();
    if ($this->step < $this->totalSteps) {
        $this->step++;
    }
};

$previousStep = function () {
    if ($this->step > 1) {
        $this->step--;
    }
};

$validateCurrentStep = function () {
    $rules = match($this->step) {
        1 => ['form.name' => 'required|min:2', 'form.country' => 'required'],
        2 => ['selectedTaxonomies' => 'required|array|min:1'],
        3 => ['form.biography' => 'required|min:50']
    };

    $this->validate($rules);
};

// Form submission
$save = function () {
    $this->validate();
    $this->form->validate();

    $artist = $this->form->store();

    // Sync taxonomies
    if (!empty($this->selectedTaxonomies)) {
        $artist->syncTaxonomies($this->selectedTaxonomies);
    }

    session()->flash('message', 'Artist created successfully');
    return $this->redirect(route('artists.show', $artist), navigate: true);
};

// Taxonomy management
$toggleTaxonomy = function ($taxonomyId) {
    if (in_array($taxonomyId, $this->selectedTaxonomies)) {
        $this->selectedTaxonomies = array_diff($this->selectedTaxonomies, [$taxonomyId]);
    } else {
        $this->selectedTaxonomies[] = $taxonomyId;
    }
};
?>

<div class="max-w-2xl mx-auto">
    <!-- Progress Indicator -->
    <div class="mb-8">
        <flux:progress value="{{ ($step / $totalSteps) * 100 }}" />
        <div class="flex justify-between mt-2 text-sm text-gray-600">
            <span>Step {{ $step }} of {{ $totalSteps }}</span>
            <span>{{ round(($step / $totalSteps) * 100) }}% Complete</span>
        </div>
    </div>

    <form wire:submit="save">
        @if($step === 1)
            <!-- Basic Information -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-6">Basic Information</flux:heading>

                <div class="space-y-4">
                    <flux:field>
                        <flux:label>Artist Name</flux:label>
                        <flux:input wire:model="form.name" />
                        <flux:error name="form.name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Country</flux:label>
                        <flux:select wire:model="form.country" placeholder="Select country...">
                            <flux:option value="US">United States</flux:option>
                            <flux:option value="UK">United Kingdom</flux:option>
                            <flux:option value="CA">Canada</flux:option>
                            <flux:option value="AU">Australia</flux:option>
                        </flux:select>
                        <flux:error name="form.country" />
                    </flux:field>
                </div>
            </flux:card>

        @elseif($step === 2)
            <!-- Taxonomy Selection -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-6">Select Taxonomies</flux:heading>

                @foreach($this->taxonomyGroups as $type => $taxonomies)
                    <div class="mb-6">
                        <flux:subheading class="mb-3">{{ ucfirst($type) }}</flux:subheading>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($taxonomies as $taxonomy)
                                <flux:badge
                                    wire:click="toggleTaxonomy({{ $taxonomy->id }})"
                                    :variant="in_array($taxonomy->id, $selectedTaxonomies) ? 'primary' : 'subtle'"
                                    class="cursor-pointer text-center"
                                >
                                    {{ $taxonomy->name }}
                                </flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <flux:error name="selectedTaxonomies" />
            </flux:card>

        @elseif($step === 3)
            <!-- Biography -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-6">Artist Biography</flux:heading>

                <flux:field>
                    <flux:label>Biography</flux:label>
                    <flux:textarea
                        wire:model="form.biography"
                        rows="6"
                        placeholder="Tell us about this artist..."
                    />
                    <flux:error name="form.biography" />
                </flux:field>
            </flux:card>
        @endif

        <!-- Navigation Buttons -->
        <div class="flex justify-between mt-6">
            <flux:button
                type="button"
                wire:click="previousStep"
                variant="ghost"
                :disabled="$step === 1"
            >
                Previous
            </flux:button>

            @if($step < $totalSteps)
                <flux:button
                    type="button"
                    wire:click="nextStep"
                    variant="primary"
                >
                    Next
                </flux:button>
            @else
                <flux:button type="submit" variant="primary">
                    Create Artist
                </flux:button>
            @endif
        </div>
    </form>
</div>
```

## 4. Real-time Features

### 4.1 Live Search with Debouncing

```php
<?php
// Real-time search component
use function Livewire\Volt\{state, computed};

state(['query' => '', 'isSearching' => false]);

$searchResults = computed(function () {
    if (strlen($this->query) < 2) {
        return collect();
    }

    $this->isSearching = true;

    $results = collect();

    // Search artists
    $artists = Artist::search($this->query)
        ->with(['taxonomies'])
        ->take(5)
        ->get()
        ->map(fn($artist) => [
            'type' => 'artist',
            'id' => $artist->id,
            'name' => $artist->name,
            'url' => route('artists.show', $artist),
            'taxonomies' => $artist->taxonomies->pluck('name')->take(2)
        ]);

    // Search albums
    $albums = Album::search($this->query)
        ->with(['artist', 'taxonomies'])
        ->take(5)
        ->get()
        ->map(fn($album) => [
            'type' => 'album',
            'id' => $album->id,
            'name' => $album->title,
            'subtitle' => $album->artist->name,
            'url' => route('albums.show', $album),
            'taxonomies' => $album->taxonomies->pluck('name')->take(2)
        ]);

    $this->isSearching = false;

    return $results->merge($artists)->merge($albums);
});

$selectResult = function ($type, $id) {
    $route = match($type) {
        'artist' => route('artists.show', $id),
        'album' => route('albums.show', $id),
        default => '/'
    };

    return $this->redirect($route, navigate: true);
};
?>

<div class="relative">
    <flux:input
        wire:model.live.debounce.300ms="query"
        placeholder="Search artists, albums..."
        icon="magnifying-glass"
        class="w-full"
    />

    @if($query && $this->searchResults->count() > 0)
        <div class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-b-lg shadow-lg z-50 max-h-96 overflow-y-auto">
            @foreach($this->searchResults as $result)
                <div
                    wire:click="selectResult('{{ $result['type'] }}', {{ $result['id'] }})"
                    class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium">{{ $result['name'] }}</div>
                            @if(isset($result['subtitle']))
                                <div class="text-sm text-gray-600">{{ $result['subtitle'] }}</div>
                            @endif
                            @if($result['taxonomies']->count() > 0)
                                <div class="flex gap-1 mt-1">
                                    @foreach($result['taxonomies'] as $taxonomy)
                                        <flux:badge variant="subtle" size="xs">{{ $taxonomy }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <flux:badge variant="outline" size="sm">
                            {{ ucfirst($result['type']) }}
                        </flux:badge>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($query && $this->searchResults->count() === 0 && !$isSearching)
        <div class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-b-lg shadow-lg z-50 p-4 text-center text-gray-500">
            No results found for "{{ $query }}"
        </div>
    @endif

    @if($isSearching)
        <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
            <flux:spinner size="sm" />
        </div>
    @endif
</div>
```

### 4.2 Real-time Notifications

```php
<?php
// Real-time notification system
use function Livewire\Volt\{state, on};

state(['notifications' => []]);

// Listen for various events
on([
    'artist-created' => function ($artistId) {
        $this->addNotification('success', 'Artist created successfully!');
    },
    'favorite-added' => function ($artistId) {
        $artist = Artist::find($artistId);
        $this->addNotification('info', "Added {$artist->name} to favorites");
    },
    'playlist-updated' => function ($playlistId, $trackCount) {
        $this->addNotification('success', "Added {$trackCount} tracks to playlist");
    }
]);

$addNotification = function ($type, $message) {
    $this->notifications[] = [
        'id' => uniqid(),
        'type' => $type,
        'message' => $message,
        'timestamp' => now()
    ];

    // Auto-remove after 5 seconds
    $this->dispatch('auto-remove-notification', delay: 5000);
};

$removeNotification = function ($notificationId) {
    $this->notifications = array_filter(
        $this->notifications,
        fn($notification) => $notification['id'] !== $notificationId
    );
};
?>

<div class="fixed top-4 right-4 z-50 space-y-2">
    @foreach($notifications as $notification)
        <div
            wire:key="notification-{{ $notification['id'] }}"
            class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 max-w-sm"
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-full"
        >
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    @if($notification['type'] === 'success')
                        <flux:icon name="check-circle" class="text-green-500" />
                    @elseif($notification['type'] === 'error')
                        <flux:icon name="x-circle" class="text-red-500" />
                    @else
                        <flux:icon name="information-circle" class="text-blue-500" />
                    @endif
                </div>

                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-900">
                        {{ $notification['message'] }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $notification['timestamp']->diffForHumans() }}
                    </p>
                </div>

                <flux:button
                    wire:click="removeNotification('{{ $notification['id'] }}')"
                    variant="ghost"
                    size="sm"
                    icon="x-mark"
                    class="ml-2"
                />
            </div>
        </div>
    @endforeach
</div>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('auto-remove-notification', (event) => {
        setTimeout(() => {
            // Auto-remove oldest notification
            const notifications = @json($notifications);
            if (notifications.length > 0) {
                @this.call('removeNotification', notifications[0].id);
            }
        }, event.delay);
    });
});
</script>
```

## 5. State Management

### 5.1 Complex State with Persistence

```php
<?php
// Advanced state management with local storage persistence
use function Livewire\Volt\{state, computed, mount, dehydrate, hydrate};

state([
    'preferences' => [
        'theme' => 'light',
        'view_mode' => 'grid',
        'items_per_page' => 20,
        'auto_play' => false
    ],
    'filters' => [
        'taxonomies' => [],
        'date_range' => null,
        'sort_by' => 'name'
    ],
    'session' => [
        'last_viewed' => [],
        'search_history' => []
    ]
]);

// Persist state to localStorage
dehydrate(function () {
    $this->dispatch('save-to-storage', [
        'preferences' => $this->preferences,
        'session' => $this->session
    ]);
});

// Restore state from localStorage
hydrate(function () {
    $this->dispatch('load-from-storage');
});

mount(function () {
    // Initialize with default preferences
    $this->loadUserPreferences();
});

$loadUserPreferences = function () {
    $user = auth()->user();
    if ($user && $user->preferences) {
        $this->preferences = array_merge($this->preferences, $user->preferences);
    }
};

$updatePreference = function ($key, $value) {
    $this->preferences[$key] = $value;

    // Save to user profile if authenticated
    if (auth()->check()) {
        auth()->user()->update(['preferences' => $this->preferences]);
    }

    $this->dispatch('preference-updated', key: $key, value: $value);
};

$addToHistory = function ($type, $item) {
    if ($type === 'search') {
        array_unshift($this->session['search_history'], $item);
        $this->session['search_history'] = array_slice($this->session['search_history'], 0, 10);
    } elseif ($type === 'view') {
        array_unshift($this->session['last_viewed'], $item);
        $this->session['last_viewed'] = array_slice($this->session['last_viewed'], 0, 20);
    }
};
?>

<script>
document.addEventListener('livewire:init', () => {
    // Save state to localStorage
    Livewire.on('save-to-storage', (data) => {
        localStorage.setItem('chinook_state', JSON.stringify(data));
    });

    // Load state from localStorage
    Livewire.on('load-from-storage', () => {
        const saved = localStorage.getItem('chinook_state');
        if (saved) {
            const data = JSON.parse(saved);
            @this.set('preferences', data.preferences || {});
            @this.set('session', data.session || {});
        }
    });

    // Handle preference updates
    Livewire.on('preference-updated', (event) => {
        console.log(`Preference ${event.key} updated to:`, event.value);

        // Apply theme changes immediately
        if (event.key === 'theme') {
            document.documentElement.setAttribute('data-theme', event.value);
        }
    });
});
</script>
```

## 6. Component Communication

### 6.1 Event-Driven Architecture

```php
<?php
// Parent component managing multiple child components
use function Livewire\Volt\{state, on};

state([
    'selectedArtists' => [],
    'playingTrack' => null,
    'currentPlaylist' => null
]);

// Listen for events from child components
on([
    'artist-selected' => function ($artistId) {
        if (!in_array($artistId, $this->selectedArtists)) {
            $this->selectedArtists[] = $artistId;
        }
        $this->dispatch('selection-updated', count: count($this->selectedArtists));
    },

    'artist-deselected' => function ($artistId) {
        $this->selectedArtists = array_filter($this->selectedArtists, fn($id) => $id !== $artistId);
        $this->dispatch('selection-updated', count: count($this->selectedArtists));
    },

    'track-play-requested' => function ($trackId) {
        $this->playingTrack = $trackId;
        $this->dispatch('play-track', trackId: $trackId)->to('music-player');
    },

    'playlist-created' => function ($playlistId) {
        $this->currentPlaylist = $playlistId;
        $this->dispatch('playlist-updated', playlistId: $playlistId);
    }
]);

// Bulk operations
$createPlaylistFromSelection = function () {
    if (empty($this->selectedArtists)) return;

    $playlist = auth()->user()->playlists()->create([
        'name' => 'New Playlist - ' . now()->format('M j, Y'),
        'description' => 'Created from selected artists'
    ]);

    // Add top tracks from selected artists
    foreach ($this->selectedArtists as $artistId) {
        $tracks = Artist::find($artistId)
            ->tracks()
            ->orderByDesc('play_count')
            ->limit(5)
            ->get();

        $playlist->tracks()->attach($tracks->pluck('id'));
    }

    $this->dispatch('playlist-created', playlistId: $playlist->id);
    $this->selectedArtists = [];
};

$clearSelection = function () {
    $this->selectedArtists = [];
    $this->dispatch('selection-cleared');
};
?>

<div class="space-y-6">
    <!-- Selection Summary -->
    @if(count($selectedArtists) > 0)
        <flux:card class="p-4 bg-blue-50 border-blue-200">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text class="font-medium">
                        {{ count($selectedArtists) }} artists selected
                    </flux:text>
                </div>

                <div class="flex space-x-2">
                    <flux:button
                        wire:click="createPlaylistFromSelection"
                        variant="primary"
                        size="sm"
                    >
                        Create Playlist
                    </flux:button>

                    <flux:button
                        wire:click="clearSelection"
                        variant="ghost"
                        size="sm"
                    >
                        Clear
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Artist Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($artists as $artist)
            <livewire:music.selectable-artist-card
                :artist="$artist"
                :selected="in_array($artist->id, $selectedArtists)"
                :key="$artist->id"
            />
        @endforeach
    </div>
</div>
```

## 7. Performance Optimization

### 7.1 Lazy Loading and Caching

```php
<?php
// Performance-optimized component with lazy loading
use function Livewire\Volt\{state, computed, placeholder};

state(['isLoaded' => false, 'page' => 1]);

// Lazy loading placeholder
placeholder(view('components.skeleton-loader'));

// Cached expensive computation
$expensiveData = computed(function () {
    if (!$this->isLoaded) return collect();

    return cache()->remember(
        "expensive-data-{$this->page}",
        now()->addMinutes(30),
        function () {
            return Artist::with(['taxonomies', 'albums.tracks'])
                ->withCount(['albums', 'tracks'])
                ->orderByDesc('tracks_count')
                ->paginate(20, ['*'], 'page', $this->page);
        }
    );
});

$loadData = function () {
    $this->isLoaded = true;
};

// Intersection observer for automatic loading
$setVisible = function () {
    if (!$this->isLoaded) {
        $this->loadData();
    }
};
?>

<div
    x-data="{
        observer: null,
        init() {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        $wire.setVisible();
                        this.observer.disconnect();
                    }
                });
            });
            this.observer.observe(this.$el);
        }
    }"
    class="min-h-96"
>
    @if($this->expensiveData->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($this->expensiveData as $artist)
                <livewire:music.artist-card :artist="$artist" :key="$artist->id" />
            @endforeach
        </div>

        {{ $this->expensiveData->links() }}
    @elseif($isLoaded)
        <div class="text-center py-12">
            <flux:text variant="muted">No artists found</flux:text>
        </div>
    @else
        <div class="flex items-center justify-center py-12">
            <flux:button wire:click="loadData" variant="primary">
                Load Artists
            </flux:button>
        </div>
    @endif
</div>
```

## 8. Testing Strategies

### 8.1 Volt Component Testing

```php
<?php
// tests/Feature/Livewire/ArtistBrowserTest.php
use Livewire\Volt\Volt;
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class ArtistBrowserTest extends TestCase
{
    /** @test */
    public function it_filters_artists_by_search_term()
    {
        Artist::factory()->create(['name' => 'The Beatles']);
        Artist::factory()->create(['name' => 'Led Zeppelin']);

        Volt::test('music.artist-browser')
            ->set('search', 'Beatles')
            ->assertSee('The Beatles')
            ->assertDontSee('Led Zeppelin');
    }

    /** @test */
    public function it_filters_artists_by_taxonomy()
    {
        $rockTaxonomy = Taxonomy::factory()->create(['name' => 'Rock', 'type' => 'genre']);
        $jazzTaxonomy = Taxonomy::factory()->create(['name' => 'Jazz', 'type' => 'genre']);

        $rockArtist = Artist::factory()->create(['name' => 'Rock Band']);
        $jazzArtist = Artist::factory()->create(['name' => 'Jazz Ensemble']);

        $rockArtist->syncTaxonomies([$rockTaxonomy->id]);
        $jazzArtist->syncTaxonomies([$jazzTaxonomy->id]);

        Volt::test('music.artist-browser')
            ->set('selectedTaxonomies', [$rockTaxonomy->id])
            ->assertSee('Rock Band')
            ->assertDontSee('Jazz Ensemble');
    }

    /** @test */
    public function it_sorts_artists_correctly()
    {
        Artist::factory()->create(['name' => 'Zebra']);
        Artist::factory()->create(['name' => 'Alpha']);

        Volt::test('music.artist-browser')
            ->call('sortBy', 'name')
            ->assertSeeInOrder(['Alpha', 'Zebra']);
    }

    /** @test */
    public function it_handles_pagination()
    {
        Artist::factory()->count(25)->create();

        $component = Volt::test('music.artist-browser');

        $component->assertSee('1')
              ->assertSee('2')
              ->call('gotoPage', 2)
              ->assertSet('page', 2);
    }
}
?>
```

## 9. Best Practices

### 9.1 Component Organization

1. **Single Responsibility**: Each component should have one clear purpose
2. **Computed Properties**: Use for expensive calculations and data transformations
3. **State Management**: Keep state minimal and focused on the component's purpose
4. **Event Communication**: Use events for loose coupling between components
5. **Performance**: Implement lazy loading and caching for expensive operations

### 9.2 Taxonomy Integration Guidelines

1. **Consistent Naming**: Always use 'taxonomies' instead of 'categories'
2. **Type Safety**: Validate taxonomy types in forms and filters
3. **Performance**: Eager load taxonomy relationships when needed
4. **User Experience**: Provide clear taxonomy selection interfaces
5. **Data Integrity**: Use proper validation for taxonomy assignments

### 9.3 Code Quality Standards

1. **Type Hints**: Use Laravel 12 type hints and return types
2. **Validation**: Implement comprehensive validation rules
3. **Error Handling**: Provide meaningful error messages
4. **Documentation**: Add inline comments for complex logic
5. **Testing**: Write comprehensive tests for all component functionality

## 10. Navigation

**← Previous** [Performance Optimization Guide](150-performance-optimization-guide.md)
**Next →** [Testing Approaches Guide](160-testing-approaches-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/160-livewire-volt-integration-guide.md on 2025-07-11

*This guide provides comprehensive patterns for building modern, accessible, and performant Livewire/Volt applications with aliziodev/laravel-taxonomy integration.*

[⬆️ Back to Top](#1-livewirevolt-integration-guide)
