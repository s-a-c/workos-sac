# 1. Volt Functional Component Patterns Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Component Structure](#2-component-structure)
- [3. State Management Patterns](#3-state-management-patterns)
- [4. Action Patterns](#4-action-patterns)
- [5. Lifecycle Hooks](#5-lifecycle-hooks)
- [6. Form Handling](#6-form-handling)
- [7. Event Communication](#7-event-communication)
- [8. Computed Properties](#8-computed-properties)
- [9. URL State Synchronization](#9-url-state-synchronization)
- [10. Component Composition](#10-component-composition)
- [11. Performance Optimization](#11-performance-optimization)
- [12. Testing Patterns](#12-testing-patterns)
- [13. Best Practices](#13-best-practices)
- [14. Navigation](#14-navigation)

## 1. Overview

This guide provides comprehensive patterns for implementing Livewire/Volt functional components in the Chinook application. Volt's functional API offers a clean, concise way to build interactive components while maintaining the full power of Livewire's reactive system.

## 2. Component Structure

### 2.1 Basic Volt Component Pattern

```php
<?php
// resources/views/livewire/artists/artist-card.blade.php
use function Livewire\Volt\{state, computed, mount};
use App\Models\Artist;

// Component state
state([
    'artist' => fn() => null,
    'showDetails' => false,
    'isPlaying' => false
]);

// Mount lifecycle
mount(function (Artist $artist) {
    $this->artist = $artist;
});

// Computed properties
$albumCount = computed(function () {
    return $this->artist->albums()->count();
});

$totalTracks = computed(function () {
    return $this->artist->albums()->withCount('tracks')->get()->sum('tracks_count');
});

// Actions
$toggleDetails = fn() => $this->showDetails = !$this->showDetails;

$playArtist = function () {
    $this->isPlaying = true;
    $this->dispatch('play-artist', artistId: $this->artist->id);
};

$navigateToArtist = function () {
    return $this->redirect(route('artists.show', $this->artist->slug), navigate: true);
};
?>

<flux:card class="artist-card" wire:key="artist-{{ $artist->id }}">
    <div class="flex items-center space-x-4">
        <flux:avatar
            :src="$artist->getFirstMediaUrl('avatar')"
            :alt="$artist->name"
            size="lg"
        />
        
        <div class="flex-1">
            <flux:heading 
                size="lg" 
                wire:click="navigateToArtist"
                class="cursor-pointer hover:text-blue-600"
            >
                {{ $artist->name }}
            </flux:heading>
            
            <flux:text variant="muted">
                {{ $this->albumCount }} albums • {{ $this->totalTracks }} tracks
            </flux:text>
        </div>
        
        <div class="flex space-x-2">
            <flux:button 
                wire:click="playArtist"
                :variant="$isPlaying ? 'primary' : 'ghost'"
                icon="{{ $isPlaying ? 'pause' : 'play' }}"
                size="sm"
            />
            
            <flux:button 
                wire:click="toggleDetails"
                icon="{{ $showDetails ? 'chevron-up' : 'chevron-down' }}"
                variant="ghost"
                size="sm"
            />
        </div>
    </div>
    
    @if($showDetails)
        <div class="mt-4 pt-4 border-t" wire:transition>
            <flux:text>{{ $artist->biography }}</flux:text>
            
            <div class="mt-2 flex flex-wrap gap-1">
                @foreach($artist->taxonomies as $taxonomy)
                    <flux:badge variant="subtle">{{ $taxonomy->name }}</flux:badge>
                @endforeach
            </div>
        </div>
    @endif
</flux:card>
```

## 3. State Management Patterns

### 3.1 Advanced State with Filtering

```php
<?php
// Artist listing with filters using aliziodev/laravel-taxonomy
use function Livewire\Volt\{state, computed};
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

// State with URL synchronization
state([
    'search' => '',
    'genre' => null,
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'perPage' => 12
])->url();

// Computed artists with taxonomy filtering
$artists = computed(function () {
    return Artist::query()
        ->when($this->search, function ($query) {
            $query->where('name', 'like', "%{$this->search}%")
                  ->orWhere('biography', 'like', "%{$this->search}%");
        })
        ->when($this->genre, function ($query) {
            $query->whereHasTaxonomies(function ($q) {
                $q->where('taxonomies.id', $this->genre);
            });
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->with(['taxonomies', 'albums'])
        ->paginate($this->perPage);
});

// Filter actions
$clearFilters = function () {
    $this->search = '';
    $this->genre = null;
    $this->sortBy = 'name';
    $this->sortDirection = 'asc';
};

$setGenre = function ($genreId) {
    $this->genre = $genreId;
    $this->resetPage();
};

// Available genres from taxonomy system
$availableGenres = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->orderBy('name')
        ->get();
});
?>

<div class="space-y-6">
    <!-- Search and Filters -->
    <div class="flex flex-wrap gap-4">
        <flux:input 
            wire:model.live.debounce.300ms="search"
            placeholder="Search artists..."
            icon="magnifying-glass"
            class="flex-1 min-w-64"
        />
        
        <flux:select 
            wire:model.live="genre"
            placeholder="All Genres"
            class="w-48"
        >
            @foreach($this->availableGenres as $genreOption)
                <flux:option value="{{ $genreOption->id }}">
                    {{ $genreOption->name }}
                </flux:option>
            @endforeach
        </flux:select>
        
        <flux:button 
            wire:click="clearFilters"
            variant="ghost"
            icon="x-mark"
        >
            Clear
        </flux:button>
    </div>
    
    <!-- Results -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($this->artists as $artist)
            <livewire:artists.artist-card :artist="$artist" :key="$artist->id" />
        @endforeach
    </div>
    
    <!-- Pagination -->
    {{ $this->artists->links() }}
</div>
```

### 3.2 Complex State Management

```php
<?php
// Multi-step form with state persistence
use function Livewire\Volt\{state, computed, mount};
use App\Models\Album;
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state([
    'currentStep' => 1,
    'albumData' => [
        'title' => '',
        'artist_id' => null,
        'release_date' => null,
        'description' => ''
    ],
    'selectedTaxonomies' => [],
    'tracks' => []
]);

// Step validation
$validateCurrentStep = function () {
    $rules = match($this->currentStep) {
        1 => ['albumData.title' => 'required|min:3', 'albumData.artist_id' => 'required|exists:artists,id'],
        2 => ['selectedTaxonomies' => 'required|array|min:1'],
        3 => ['tracks' => 'required|array|min:1', 'tracks.*.title' => 'required|min:2']
    };
    
    $this->validate($rules);
};

// Navigation actions
$nextStep = function () {
    $this->validateCurrentStep();
    $this->currentStep++;
};

$previousStep = fn() => $this->currentStep--;

// Available taxonomies for selection
$availableTaxonomies = computed(function () {
    return Taxonomy::whereIn('type', ['genre', 'mood', 'style'])
        ->orderBy('type')
        ->orderBy('name')
        ->get()
        ->groupBy('type');
});
?>
```

## 4. Action Patterns

### 4.1 Simple Actions

```php
<?php
// Basic action patterns
use function Livewire\Volt\{state};

state(['count' => 0, 'message' => '']);

// Simple increment action
$increment = fn() => $this->count++;

// Action with parameters
$setMessage = fn($text) => $this->message = $text;

// Action with validation
$updateCount = function ($value) {
    $this->validate(['value' => 'required|integer|min:0'], ['value' => $value]);
    $this->count = $value;
};

// Action with side effects
$resetWithNotification = function () {
    $this->count = 0;
    $this->message = '';
    $this->dispatch('count-reset');
    session()->flash('message', 'Counter has been reset');
};
?>
```

### 4.2 Complex Actions with Database Operations

```php
<?php
// Album management actions
use function Livewire\Volt\{state, mount};
use App\Models\Album;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state(['album' => null, 'isEditing' => false]);

mount(function (Album $album) {
    $this->album = $album;
});

// Toggle edit mode
$toggleEdit = function () {
    $this->isEditing = !$this->isEditing;
    if (!$this->isEditing) {
        $this->album->refresh(); // Reset changes
    }
};

// Save album with taxonomy relationships
$saveAlbum = function () {
    $this->validate([
        'album.title' => 'required|min:3',
        'album.release_date' => 'nullable|date'
    ]);

    $this->album->save();

    // Sync taxonomies if provided
    if (isset($this->selectedTaxonomies)) {
        $this->album->syncTaxonomies($this->selectedTaxonomies);
    }

    $this->isEditing = false;
    $this->dispatch('album-updated', albumId: $this->album->id);
    session()->flash('message', 'Album updated successfully');
};

// Delete with confirmation
$deleteAlbum = function () {
    $this->album->delete();
    $this->dispatch('album-deleted', albumId: $this->album->id);
    return $this->redirect(route('albums.index'), navigate: true);
};
?>
```

## 5. Lifecycle Hooks

### 5.1 Mount and Initialization

```php
<?php
// Component initialization patterns
use function Livewire\Volt\{state, mount, boot, hydrate, dehydrate};
use App\Models\Playlist;

state(['playlist' => null, 'tracks' => [], 'isLoaded' => false]);

// Mount - runs once when component is first loaded
mount(function (?Playlist $playlist = null) {
    if ($playlist) {
        $this->playlist = $playlist;
        $this->loadTracks();
    }
});

// Boot - runs on every request
boot(function () {
    // Set default timezone or other per-request setup
    $this->timezone = auth()->user()?->timezone ?? 'UTC';
});

// Hydrate - runs when component state is restored from session
hydrate(function () {
    // Refresh model relationships that might be stale
    if ($this->playlist) {
        $this->playlist->load(['tracks.taxonomies', 'user']);
    }
});

// Dehydrate - runs before component state is stored
dehydrate(function () {
    // Clean up sensitive data before storage
    unset($this->temporaryData);
});

$loadTracks = function () {
    if ($this->playlist) {
        $this->tracks = $this->playlist->tracks()
            ->with(['artist', 'album', 'taxonomies'])
            ->orderBy('pivot.position')
            ->get();
        $this->isLoaded = true;
    }
};
?>
```

## 6. Form Handling

### 6.1 Advanced Form Patterns

```php
<?php
// Track creation form with taxonomy selection
use function Livewire\Volt\{state, form, rules};
use App\Livewire\Forms\TrackForm;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

// Use dedicated form class
form(TrackForm::class);

state(['selectedTaxonomies' => []]);

// Validation rules for taxonomy selection
rules(['selectedTaxonomies' => 'array|max:5']);

// Available taxonomies grouped by type
$taxonomyGroups = computed(function () {
    return Taxonomy::whereIn('type', ['genre', 'mood', 'style'])
        ->orderBy('type')
        ->orderBy('name')
        ->get()
        ->groupBy('type');
});

// Form submission
$save = function () {
    $this->validate();
    $this->form->validate();

    $track = $this->form->store();

    // Attach selected taxonomies
    if (!empty($this->selectedTaxonomies)) {
        $track->syncTaxonomies($this->selectedTaxonomies);
    }

    $this->dispatch('track-created', trackId: $track->id);
    return $this->redirect(route('tracks.show', $track), navigate: true);
};

// Toggle taxonomy selection
$toggleTaxonomy = function ($taxonomyId) {
    if (in_array($taxonomyId, $this->selectedTaxonomies)) {
        $this->selectedTaxonomies = array_diff($this->selectedTaxonomies, [$taxonomyId]);
    } else {
        $this->selectedTaxonomies[] = $taxonomyId;
    }
};
?>

<form wire:submit="save" class="space-y-6">
    <!-- Basic track information -->
    <flux:field>
        <flux:label>Track Title</flux:label>
        <flux:input wire:model="form.title" />
        <flux:error name="form.title" />
    </flux:field>

    <!-- Taxonomy selection -->
    <flux:fieldset>
        <flux:legend>Taxonomies</flux:legend>

        @foreach($this->taxonomyGroups as $type => $taxonomies)
            <div class="mb-4">
                <flux:subheading>{{ ucfirst($type) }}</flux:subheading>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach($taxonomies as $taxonomy)
                        <flux:badge
                            wire:click="toggleTaxonomy({{ $taxonomy->id }})"
                            :variant="in_array($taxonomy->id, $selectedTaxonomies) ? 'primary' : 'subtle'"
                            class="cursor-pointer"
                        >
                            {{ $taxonomy->name }}
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @endforeach

        <flux:error name="selectedTaxonomies" />
    </flux:fieldset>

    <flux:button type="submit" variant="primary">Create Track</flux:button>
</form>
```

## 7. Event Communication

### 7.1 Component Events

```php
<?php
// Event dispatching and listening patterns
use function Livewire\Volt\{state, on};

state(['notifications' => []]);

// Dispatch events to other components
$notifySuccess = function ($message) {
    $this->dispatch('notification', type: 'success', message: $message);
};

$notifyError = function ($message) {
    $this->dispatch('notification', type: 'error', message: $message);
};

// Listen for events from other components
on(['notification' => function ($type, $message) {
    $this->notifications[] = [
        'id' => uniqid(),
        'type' => $type,
        'message' => $message,
        'timestamp' => now()
    ];
}]);

// Remove notification
$removeNotification = function ($notificationId) {
    $this->notifications = array_filter(
        $this->notifications,
        fn($n) => $n['id'] !== $notificationId
    );
};
?>
```

### 7.2 Browser Events

```php
<?php
// Browser event integration
use function Livewire\Volt\{state, on};

state(['isOnline' => true, 'lastSync' => null]);

// Listen for browser events
on(['online' => fn() => $this->isOnline = true]);
on(['offline' => fn() => $this->isOnline = false]);

// Dispatch browser events
$syncData = function () {
    // Perform sync operation
    $this->lastSync = now();

    // Notify browser
    $this->dispatch('data-synced')->to('browser');
};

// Custom browser event
$playTrack = function ($trackId) {
    $this->dispatch('play-track', trackId: $trackId)->to('browser');
};
?>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('play-track', (event) => {
        // Handle track playback in browser
        console.log('Playing track:', event.trackId);
    });

    // Listen for browser online/offline events
    window.addEventListener('online', () => {
        Livewire.dispatch('online');
    });

    window.addEventListener('offline', () => {
        Livewire.dispatch('offline');
    });
});
</script>
```

## 8. Computed Properties

### 8.1 Basic Computed Properties

```php
<?php
// Computed property patterns
use function Livewire\Volt\{state, computed};
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state(['artistId' => null]);

// Simple computed property
$artist = computed(function () {
    return Artist::find($this->artistId);
});

// Computed with relationships
$artistWithData = computed(function () {
    return Artist::with(['albums.tracks', 'taxonomies'])
        ->find($this->artistId);
});

// Expensive computation with caching
$popularTracks = computed(function () {
    return $this->artist?->tracks()
        ->withCount('plays')
        ->orderBy('plays_count', 'desc')
        ->limit(10)
        ->get();
})->persist(seconds: 3600);

// Expensive computation with longer cache
$genreStatistics = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->withCount(['tracks', 'albums', 'artists'])
        ->get()
        ->map(function ($genre) {
            return [
                'name' => $genre->name,
                'tracks' => $genre->tracks_count,
                'albums' => $genre->albums_count,
                'artists' => $genre->artists_count,
                'percentage' => ($genre->tracks_count / $this->totalTracks) * 100
            ];
        });
})->persist(seconds: 7200); // 2 hours
?>
```

## 9. URL State Synchronization

### 9.1 Advanced URL Patterns

```php
<?php
// Search interface with URL state
use function Livewire\Volt\{state};

// URL synchronized state with aliases
state(['query' => ''])->url(as: 'q', history: true, keep: true);
state(['filters' => []])->url(as: 'f', history: false);
state(['page' => 1])->url(as: 'p', history: true, keep: false);

// Complex URL state with custom encoding
state(['selectedTaxonomies' => []])->url(
    as: 'taxonomies',
    history: true,
    keep: true,
    encode: fn($value) => implode(',', $value),
    decode: fn($value) => $value ? explode(',', $value) : []
);
?>
```

## 10. Component Composition

### 10.1 Nested Components

```php
<?php
// Parent component managing child components
use function Livewire\Volt\{state, on};

state(['selectedArtists' => [], 'playlistId' => null]);

// Handle child component events
on(['artist-selected' => function ($artistId) {
    if (!in_array($artistId, $this->selectedArtists)) {
        $this->selectedArtists[] = $artistId;
    }
}]);

on(['artist-deselected' => function ($artistId) {
    $this->selectedArtists = array_diff($this->selectedArtists, [$artistId]);
}]);

$createPlaylist = function () {
    $playlist = auth()->user()->playlists()->create([
        'name' => 'New Playlist',
        'description' => 'Created from selected artists'
    ]);

    // Add tracks from selected artists
    foreach ($this->selectedArtists as $artistId) {
        $tracks = \App\Models\Artist::find($artistId)->tracks()->limit(5)->get();
        $playlist->tracks()->attach($tracks->pluck('id'));
    }

    $this->playlistId = $playlist->id;
    $this->dispatch('playlist-created', playlistId: $playlist->id);
};
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <flux:heading>Select Artists</flux:heading>
        <flux:button
            wire:click="createPlaylist"
            :disabled="empty($selectedArtists)"
            variant="primary"
        >
            Create Playlist ({{ count($selectedArtists) }})
        </flux:button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($artists as $artist)
            <livewire:artists.selectable-card
                :artist="$artist"
                :selected="in_array($artist->id, $selectedArtists)"
                :key="$artist->id"
            />
        @endforeach
    </div>
</div>
```

## 11. Performance Optimization

### 11.1 Lazy Loading and Caching

```php
<?php
// Performance optimization patterns
use function Livewire\Volt\{state, computed};

state(['isVisible' => false]);

// Lazy loaded data
$expensiveData = computed(function () {
    if (!$this->isVisible) {
        return null;
    }

    // Only load when component is visible
    return \App\Models\Track::with(['artist', 'album', 'taxonomies'])
        ->where('created_at', '>', now()->subDays(7))
        ->orderBy('plays_count', 'desc')
        ->limit(50)
        ->get();
})->persist(seconds: 1800);

// Intersection observer for visibility
$setVisible = fn() => $this->isVisible = true;
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
    @if($this->expensiveData)
        <!-- Render expensive data -->
        @foreach($this->expensiveData as $track)
            <div>{{ $track->title }}</div>
        @endforeach
    @else
        <div class="flex items-center justify-center h-96">
            <flux:spinner size="lg" />
        </div>
    @endif
</div>
```

## 12. Testing Patterns

### 12.1 Component Testing

```php
<?php
// tests/Feature/Livewire/ArtistCardTest.php
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Livewire\Volt\Volt;

test('artist card displays basic information', function () {
    $artist = Artist::factory()->create(['name' => 'Test Artist']);

    Volt::test('artists.artist-card', ['artist' => $artist])
        ->assertSee('Test Artist')
        ->assertSet('showDetails', false);
});

test('artist card toggles details', function () {
    $artist = Artist::factory()->create();

    Volt::test('artists.artist-card', ['artist' => $artist])
        ->call('toggleDetails')
        ->assertSet('showDetails', true)
        ->call('toggleDetails')
        ->assertSet('showDetails', false);
});

test('artist card displays taxonomies when details shown', function () {
    $artist = Artist::factory()->create();
    $taxonomy = Taxonomy::factory()->create(['name' => 'Rock', 'type' => 'genre']);
    $artist->syncTaxonomies([$taxonomy->id]);

    Volt::test('artists.artist-card', ['artist' => $artist])
        ->call('toggleDetails')
        ->assertSee('Rock');
});
?>
```

## 13. Best Practices

### 13.1 Code Organization

1. **Single Responsibility**: Each component should have one clear purpose
2. **Computed Properties**: Use for expensive calculations and data transformations
3. **State Management**: Keep state minimal and focused
4. **Event Communication**: Use events for loose coupling between components
5. **URL Synchronization**: Sync important state with URL for bookmarkability
6. **Documentation**: Add inline comments for complex logic
7. **Modularity**: Break large components into smaller, focused ones

### 13.2 Security Considerations

1. **Input Validation**: Always validate user input with Laravel validation rules
2. **Authorization**: Check permissions before executing actions
3. **Locked Properties**: Protect sensitive data from client modification
4. **CSRF Protection**: Leverage Laravel's built-in CSRF protection
5. **SQL Injection**: Use Eloquent ORM and parameter binding
6. **XSS Prevention**: Escape output and use Blade's automatic escaping

### 13.3 Performance Guidelines

1. **Lazy Loading**: Load data only when needed
2. **Computed Caching**: Cache expensive calculations
3. **Database Optimization**: Use eager loading and efficient queries
4. **State Management**: Minimize reactive state updates
5. **Event Optimization**: Use renderless actions for non-UI operations

## 14. Navigation

**← Previous** [Frontend Architecture Overview](100-frontend-architecture-overview.md)
**Next →** [Flux/Flux-Pro Component Integration Guide](120-flux-component-integration-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/110-volt-functional-patterns-guide.md on 2025-07-11

*This guide provides comprehensive patterns for implementing Volt functional components. Continue with the Flux integration guide for UI component patterns.*

[⬆️ Back to Top](#1-volt-functional-component-patterns-guide)
