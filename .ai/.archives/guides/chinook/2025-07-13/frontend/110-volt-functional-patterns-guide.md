# Volt Functional Component Patterns Guide

## Table of Contents

- [Overview](#overview)
- [Component Structure](#component-structure)
- [State Management Patterns](#state-management-patterns)
- [Action Patterns](#action-patterns)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Form Handling](#form-handling)
- [Event Communication](#event-communication)
- [Computed Properties](#computed-properties)
- [URL State Synchronization](#url-state-synchronization)
- [Component Composition](#component-composition)
- [Performance Optimization](#performance-optimization)
- [Testing Patterns](#testing-patterns)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide provides comprehensive patterns for implementing Livewire/Volt functional components in the Chinook application. Volt's functional API offers a clean, concise way to build interactive components while maintaining the full power of Livewire's reactive system.

## Component Structure

### Basic Volt Component Pattern

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
                @foreach($artist->categories as $category)
                    <flux:badge variant="subtle">{{ $category->name }}</flux:badge>
                @endforeach
            </div>
        </div>
    @endif
</flux:card>
```

## State Management Patterns

### Reactive State with URL Synchronization

```php
<?php
// Artist listing with filters
use function Livewire\Volt\{state, computed};
use App\Models\Artist;
use App\Enums\CategoryType;

// State with URL synchronization
state([
    'search' => '',
    'genre' => null,
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'perPage' => 20
])->url();

// Computed filtered results
$artists = computed(function () {
    return Artist::query()
        ->when($this->search, function ($query) {
            $query->where('name', 'like', "%{$this->search}%")
                  ->orWhere('biography', 'like', "%{$this->search}%");
        })
        ->when($this->genre, function ($query) {
            $query->whereHas('categories', function ($q) {
                $q->where('categories.id', $this->genre)
                  ->where('categories.type', CategoryType::GENRE);
            });
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->with(['categories', 'albums'])
        ->paginate($this->perPage);
});

// Filter actions
$clearFilters = function () {
    $this->search = '';
    $this->genre = null;
    $this->sortBy = 'name';
    $this->sortDirection = 'asc';
};

$setSortBy = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};
?>
```

### Locked and Reactive Properties

```php
<?php
// Secure component with locked properties
use function Livewire\Volt\{state};

// Locked properties (cannot be modified from frontend)
state(['userId', 'permissions'])->locked();

// Reactive properties (automatically update from parent)
state(['currentTrack', 'playlistId'])->reactive();

// Modelable properties (two-way binding with parent)
state(['volume', 'isShuffled'])->modelable();
?>
```

## Action Patterns

### CRUD Operations

```php
<?php
// Album management actions
use function Livewire\Volt\{state, action};
use App\Models\Album;
use App\Livewire\Forms\AlbumForm;

state(['albums' => fn() => collect(), 'selectedAlbum' => null]);

// Create action
$createAlbum = function () {
    $this->dispatch('open-album-modal', mode: 'create');
};

// Edit action
$editAlbum = function (Album $album) {
    $this->selectedAlbum = $album;
    $this->dispatch('open-album-modal', mode: 'edit', albumId: $album->id);
};

// Delete action with confirmation
$deleteAlbum = function (Album $album) {
    $this->dispatch('confirm-deletion', [
        'title' => 'Delete Album',
        'message' => "Are you sure you want to delete '{$album->title}'?",
        'action' => 'performDelete',
        'params' => ['albumId' => $album->id]
    ]);
};

// Protected delete action
$performDelete = protect(function ($albumId) {
    $album = Album::findOrFail($albumId);
    $album->delete();
    
    $this->dispatch('album-deleted', albumId: $albumId);
    $this->dispatch('toast', message: 'Album deleted successfully', type: 'success');
});

// Bulk operations
$bulkDelete = function (array $albumIds) {
    Album::whereIn('id', $albumIds)->delete();
    $this->dispatch('albums-bulk-deleted', count: count($albumIds));
};
?>
```

### Renderless Actions

```php
<?php
// Analytics tracking actions
use function Livewire\Volt\{action};

// Renderless action for tracking (no UI update needed)
$trackPlay = action(function ($trackId) {
    // Log play event to Laravel's default log
    \Log::info('Track played', [
        'track_id' => $trackId,
        'user_id' => auth()->id(),
        'played_at' => now()
    ]);

    // Could implement analytics tracking here
    // Example: dispatch job for analytics processing
    // \App\Jobs\TrackPlayAnalytics::dispatch($trackId, auth()->id());
})->renderless();

$trackSearch = action(function ($query) {
    // Log search event
    \Log::info('Search performed', [
        'query' => $query,
        'user_id' => auth()->id(),
        'searched_at' => now()
    ]);
})->renderless();
?>
```

## Lifecycle Hooks

### Component Lifecycle Management

```php
<?php
// Music player component with lifecycle hooks
use function Livewire\Volt\{state, mount, hydrate, dehydrate, updating, updated};

state(['currentTrack', 'position', 'isPlaying' => false]);

// Mount: Initialize component
mount(function ($trackId = null) {
    if ($trackId) {
        $this->currentTrack = \App\Models\Track::find($trackId);
    }
    
    // Load user preferences
    $this->loadUserPreferences();
});

// Hydrate: Restore state from session
hydrate(function () {
    // Restore player state from session
    $this->restorePlayerState();
});

// Dehydrate: Save state to session
dehydrate(function () {
    // Save current state
    $this->savePlayerState();
});

// Property-specific lifecycle hooks
updating(['position' => function ($value) {
    // Validate position before update
    if ($value < 0 || $value > $this->currentTrack?->duration) {
        return false;
    }
}]);

updated(['currentTrack' => function ($value) {
    // Track change analytics
    $this->trackPlay($value?->id);
    
    // Update browser title
    $this->dispatch('update-title', title: $value?->name ?? 'Music Player');
}]);

// Helper methods
$loadUserPreferences = protect(function () {
    $preferences = auth()->user()->preferences ?? [];
    $this->volume = $preferences['volume'] ?? 0.8;
    $this->isShuffled = $preferences['shuffle'] ?? false;
});

$savePlayerState = protect(function () {
    session(['player_state' => [
        'track_id' => $this->currentTrack?->id,
        'position' => $this->position,
        'is_playing' => $this->isPlaying
    ]]);
});
?>
```

## Form Handling

### Advanced Form Patterns

```php
<?php
// Multi-step album creation form
use function Livewire\Volt\{state, rules, form};
use App\Livewire\Forms\AlbumForm;

form(AlbumForm::class);

state([
    'currentStep' => 1,
    'totalSteps' => 3,
    'canProceed' => false
]);

// Validation rules per step
rules(fn() => match($this->currentStep) {
    1 => ['form.title' => 'required|min:3', 'form.artist_id' => 'required|exists:artists,id'],
    2 => ['form.release_date' => 'required|date', 'form.label' => 'required'],
    3 => ['form.tracks' => 'required|array|min:1'],
    default => []
});

// Step navigation
$nextStep = function () {
    $this->validate();
    
    if ($this->currentStep < $this->totalSteps) {
        $this->currentStep++;
    }
};

$previousStep = function () {
    if ($this->currentStep > 1) {
        $this->currentStep--;
    }
};

// Form submission
$submit = function () {
    $this->validate();
    
    $album = $this->form->store();
    
    $this->dispatch('album-created', albumId: $album->id);
    return $this->redirect(route('albums.show', $album->slug), navigate: true);
};

// Auto-save draft
$saveDraft = action(function () {
    $this->form->saveDraft();
    $this->dispatch('toast', message: 'Draft saved', type: 'info');
})->renderless();
?>
```

## Event Communication

### Component Event Patterns

```php
<?php
// Playlist manager with event communication
use function Livewire\Volt\{state, on};

state(['playlist', 'tracks' => collect()]);

// Event listeners
on([
    'track-added' => function ($trackId) {
        $track = \App\Models\Track::find($trackId);
        $this->tracks->push($track);
        $this->updatePlaylistDuration();
    },
    
    'track-removed' => function ($trackId) {
        $this->tracks = $this->tracks->reject(fn($track) => $track->id === $trackId);
        $this->updatePlaylistDuration();
    },
    
    'playlist-reordered' => function ($trackIds) {
        $this->reorderTracks($trackIds);
    }
]);

// Dynamic event listeners based on playlist
on(fn() => [
    "playlist-{$this->playlist->id}-updated" => function ($data) {
        $this->playlist->refresh();
        $this->tracks = $this->playlist->tracks;
    }
]);

$updatePlaylistDuration = protect(function () {
    $totalDuration = $this->tracks->sum('duration_ms');
    $this->playlist->update(['total_duration_ms' => $totalDuration]);
});
?>
```

## Computed Properties

### Cached Computations

```php
<?php
// Dashboard with cached computed properties
use function Livewire\Volt\{computed};

// Cached for request lifecycle
$totalArtists = computed(function () {
    return \App\Models\Artist::count();
});

// Persisted in cache for 1 hour
$popularTracks = computed(function () {
    return \App\Models\Track::orderBy('play_count', 'desc')
        ->limit(10)
        ->with(['album.artist'])
        ->get();
})->persist(seconds: 3600);

// Expensive computation with longer cache
$genreStatistics = computed(function () {
    return \App\Models\Category::where('type', 'genre')
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

## URL State Synchronization

### Advanced URL Patterns

```php
<?php
// Search interface with URL state
use function Livewire\Volt\{state};

// URL synchronized state with aliases
state(['query' => ''])->url(as: 'q', history: true, keep: true);
state(['filters' => []])->url(as: 'f', history: false);
state(['page' => 1])->url(as: 'p', history: true, keep: false);

// Complex URL state management
$updateUrl = function () {
    $this->dispatch('url-updated', [
        'query' => $this->query,
        'filters' => $this->filters,
        'page' => $this->page
    ]);
};
?>
```

## Component Composition

### Nested Component Patterns

```php
<?php
// Parent component managing child state
use function Livewire\Volt\{state};

state([
    'selectedTracks' => [],
    'playlistMode' => false,
    'bulkActions' => false
]);

// Child component communication
$handleTrackSelection = function ($trackId, $selected) {
    if ($selected) {
        $this->selectedTracks[] = $trackId;
    } else {
        $this->selectedTracks = array_filter(
            $this->selectedTracks, 
            fn($id) => $id !== $trackId
        );
    }
    
    $this->bulkActions = count($this->selectedTracks) > 0;
};

$clearSelection = function () {
    $this->selectedTracks = [];
    $this->bulkActions = false;
    $this->dispatch('clear-track-selection');
};
?>
```

## Performance Optimization

### Efficient Data Loading

```php
<?php
// Optimized data loading patterns
use function Livewire\Volt\{state, computed};

// Lazy loading with pagination
$tracks = computed(function () {
    return \App\Models\Track::query()
        ->with(['album:id,title,artist_id', 'album.artist:id,name'])
        ->select(['id', 'name', 'album_id', 'duration_ms', 'play_count'])
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate($this->perPage);
});

// Chunked processing for large datasets
$processLargeDataset = function () {
    \App\Models\Track::chunk(1000, function ($tracks) {
        foreach ($tracks as $track) {
            // Process each track
            $this->processTrack($track);
        }
    });
};
?>
```

## Testing Patterns

### Component Testing Examples

```php
// tests/Feature/Livewire/ArtistCardTest.php
use Livewire\Volt\Volt;
use App\Models\Artist;

it('displays artist information correctly', function () {
    $artist = Artist::factory()->create(['name' => 'Test Artist']);

    Volt::test('artists.artist-card', ['artist' => $artist])
        ->assertSee('Test Artist')
        ->assertSee('albums')
        ->assertSee('tracks');
});

it('toggles details when button is clicked', function () {
    $artist = Artist::factory()->create();

    Volt::test('artists.artist-card', ['artist' => $artist])
        ->assertDontSee($artist->biography)
        ->call('toggleDetails')
        ->assertSee($artist->biography);
});

it('handles play action correctly', function () {
    $artist = Artist::factory()->create();

    Volt::test('artists.artist-card', ['artist' => $artist])
        ->call('playArtist')
        ->assertSet('isPlaying', true)
        ->assertDispatched('play-artist', artistId: $artist->id);
});

it('validates form input properly', function () {
    Volt::test('albums.create-form')
        ->set('form.title', '')
        ->call('submit')
        ->assertHasErrors(['form.title']);
});
```

### Integration Testing

```php
// tests/Feature/Livewire/PlaylistManagerTest.php
it('manages playlist tracks correctly', function () {
    $playlist = Playlist::factory()->create();
    $track = Track::factory()->create();

    Volt::test('playlists.manager', ['playlist' => $playlist])
        ->dispatch('track-added', $track->id)
        ->assertSee($track->name)
        ->dispatch('track-removed', $track->id)
        ->assertDontSee($track->name);
});
```

## Best Practices

### Code Organization

1. **Single Responsibility**: Each component should have one clear purpose
2. **Consistent Naming**: Use descriptive names for state and actions
3. **Type Safety**: Leverage Laravel 12 type hints and validation
4. **Error Handling**: Implement proper error handling and user feedback
5. **Performance**: Use computed properties and caching strategically
6. **Documentation**: Add inline comments for complex logic
7. **Modularity**: Break large components into smaller, focused ones

### Security Considerations

1. **Input Validation**: Always validate user input with Laravel validation rules
2. **Authorization**: Check permissions before executing actions
3. **Locked Properties**: Protect sensitive data from client modification
4. **CSRF Protection**: Leverage Laravel's built-in CSRF protection
5. **SQL Injection**: Use Eloquent ORM and parameter binding
6. **XSS Prevention**: Escape output and use Blade's automatic escaping

### Performance Guidelines

1. **Lazy Loading**: Load data only when needed
2. **Computed Caching**: Cache expensive calculations
3. **Database Optimization**: Use eager loading and efficient queries
4. **State Management**: Minimize reactive state updates
5. **Event Optimization**: Use renderless actions for non-UI operations

## Navigation

**← Previous** [Frontend Architecture Overview](100-frontend-architecture-overview.md)  
**Next →** [Flux/Flux-Pro Component Integration Guide](120-flux-component-integration-guide.md)

---

*This guide provides comprehensive patterns for implementing Volt functional components. Continue with the Flux integration guide for UI component patterns.*
