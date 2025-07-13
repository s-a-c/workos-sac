# Livewire/Volt Integration Guide

## Overview

This guide provides comprehensive coverage of Livewire/Volt integration patterns for the Chinook application, focusing on functional components, real-time features, and seamless integration with Filament admin panels.

## Table of Contents

- [Overview](#overview)
- [Functional Component Architecture](#functional-component-architecture)
- [Volt Integration Patterns](#volt-integration-patterns)
- [Real-time Features](#real-time-features)
- [State Management](#state-management)
- [Component Communication](#component-communication)
- [Performance Optimization](#performance-optimization)
- [Testing Strategies](#testing-strategies)
- [Best Practices](#best-practices)

## Functional Component Architecture

### Volt Functional Components

Volt enables functional programming patterns in Livewire, providing cleaner and more maintainable code:

```php
<?php
// resources/views/livewire/music/artist-browser.blade.php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Artist;
use App\Models\Category;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public array $selectedCategories = [];
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    public function with(): array
    {
        return [
            'artists' => $this->getArtists(),
            'categories' => $this->getCategories(),
        ];
    }

    public function getArtists()
    {
        return Artist::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->selectedCategories, fn($q) => 
                $q->whereHas('categories', fn($q) => 
                    $q->whereIn('categories.id', $this->selectedCategories)
                )
            )
            ->orderBy($this->sortBy, $this->sortDirection)
            ->with(['categories', 'albums'])
            ->paginate(12);
    }

    public function getCategories()
    {
        return Category::where('type', 'genre')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategories(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }
}; ?>

<div class="artist-browser">
    <!-- Search and Filters -->
    <div class="filters mb-6">
        <div class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-64">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search artists..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    aria-label="Search artists"
                />
            </div>
            
            <div class="min-w-48">
                <select 
                    wire:model.live="selectedCategories"
                    multiple
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    aria-label="Filter by categories"
                >
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Sort Controls -->
    <div class="sort-controls mb-4">
        <div class="flex gap-2">
            <button 
                wire:click="sortBy('name')"
                class="px-3 py-1 text-sm border rounded {{ $sortBy === 'name' ? 'bg-blue-100 border-blue-500' : 'border-gray-300' }}"
                aria-pressed="{{ $sortBy === 'name' ? 'true' : 'false' }}"
            >
                Name
                @if($sortBy === 'name')
                    <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                @endif
            </button>
            
            <button 
                wire:click="sortBy('created_at')"
                class="px-3 py-1 text-sm border rounded {{ $sortBy === 'created_at' ? 'bg-blue-100 border-blue-500' : 'border-gray-300' }}"
                aria-pressed="{{ $sortBy === 'created_at' ? 'true' : 'false' }}"
            >
                Date Added
                @if($sortBy === 'created_at')
                    <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                @endif
            </button>
        </div>
    </div>

    <!-- Artist Grid -->
    <div class="artist-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach($artists as $artist)
            <livewire:music.artist-card :artist="$artist" :key="$artist->id" />
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $artists->links() }}
    </div>
</div>
```

### Component Composition

```php
<?php
// resources/views/livewire/music/artist-card.blade.php

use Livewire\Volt\Component;
use App\Models\Artist;

new class extends Component {
    public Artist $artist;

    public function mount(Artist $artist): void
    {
        $this->artist = $artist->load(['categories', 'albums']);
    }

    public function toggleFavorite(): void
    {
        $user = auth()->user();
        
        if ($user->favoriteArtists()->where('artist_id', $this->artist->id)->exists()) {
            $user->favoriteArtists()->detach($this->artist->id);
            $this->dispatch('artist-unfavorited', artistId: $this->artist->id);
        } else {
            $user->favoriteArtists()->attach($this->artist->id);
            $this->dispatch('artist-favorited', artistId: $this->artist->id);
        }
    }

    public function isFavorite(): bool
    {
        return auth()->user()?->favoriteArtists()
            ->where('artist_id', $this->artist->id)
            ->exists() ?? false;
    }
}; ?>

<div class="artist-card bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
    <!-- Artist Image -->
    <div class="aspect-square bg-gray-200 relative">
        @if($artist->image_url)
            <img 
                src="{{ $artist->image_url }}" 
                alt="{{ $artist->name }}"
                class="w-full h-full object-cover"
                loading="lazy"
            />
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-400">
                <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/>
                </svg>
            </div>
        @endif
        
        <!-- Favorite Button -->
        @auth
            <button 
                wire:click="toggleFavorite"
                class="absolute top-2 right-2 p-2 rounded-full bg-white/80 hover:bg-white transition-colors"
                aria-label="{{ $this->isFavorite() ? 'Remove from favorites' : 'Add to favorites' }}"
            >
                <svg class="w-5 h-5 {{ $this->isFavorite() ? 'text-red-500' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/>
                </svg>
            </button>
        @endauth
    </div>

    <!-- Artist Info -->
    <div class="p-4">
        <h3 class="font-semibold text-lg text-gray-900 mb-2">
            <a href="{{ route('artists.show', $artist->slug) }}" class="hover:text-blue-600 transition-colors">
                {{ $artist->name }}
            </a>
        </h3>
        
        <!-- Categories -->
        @if($artist->categories->isNotEmpty())
            <div class="flex flex-wrap gap-1 mb-2">
                @foreach($artist->categories->take(3) as $category)
                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                        {{ $category->name }}
                    </span>
                @endforeach
                @if($artist->categories->count() > 3)
                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                        +{{ $artist->categories->count() - 3 }} more
                    </span>
                @endif
            </div>
        @endif
        
        <!-- Album Count -->
        <p class="text-sm text-gray-600">
            {{ $artist->albums->count() }} {{ Str::plural('album', $artist->albums->count()) }}
        </p>
    </div>
</div>
```

## Volt Integration Patterns

### State Sharing Between Components

```php
<?php
// resources/views/livewire/music/playlist-manager.blade.php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\Playlist;
use App\Models\Track;

new class extends Component {
    public ?Playlist $currentPlaylist = null;
    public array $selectedTracks = [];
    public bool $isPlaying = false;

    #[On('track-selected')]
    public function addTrack(int $trackId): void
    {
        if (!in_array($trackId, $this->selectedTracks)) {
            $this->selectedTracks[] = $trackId;
            $this->dispatch('playlist-updated', tracks: $this->selectedTracks);
        }
    }

    #[On('track-deselected')]
    public function removeTrack(int $trackId): void
    {
        $this->selectedTracks = array_filter(
            $this->selectedTracks, 
            fn($id) => $id !== $trackId
        );
        $this->dispatch('playlist-updated', tracks: $this->selectedTracks);
    }

    #[On('playlist-play')]
    public function playPlaylist(): void
    {
        $this->isPlaying = true;
        $this->dispatch('audio-player-start', tracks: $this->selectedTracks);
    }

    #[On('playlist-pause')]
    public function pausePlaylist(): void
    {
        $this->isPlaying = false;
        $this->dispatch('audio-player-pause');
    }

    public function savePlaylist(string $name): void
    {
        $playlist = Playlist::create([
            'name' => $name,
            'user_id' => auth()->id(),
            'is_public' => false,
        ]);

        $tracks = Track::whereIn('id', $this->selectedTracks)->get();
        $playlist->tracks()->attach($tracks->pluck('id'));

        $this->currentPlaylist = $playlist;
        $this->dispatch('playlist-saved', playlistId: $playlist->id);
    }
}; ?>
```

### Real-time Updates with Broadcasting

```php
<?php
// resources/views/livewire/admin/live-analytics.blade.php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use App\Models\Track;
use App\Models\Album;
use App\Models\Artist;

new class extends Component {
    public array $realtimeStats = [
        'tracks_played' => 0,
        'new_users' => 0,
        'revenue' => 0,
        'active_sessions' => 0,
    ];

    public function mount(): void
    {
        $this->loadInitialStats();
    }

    #[On('echo:analytics,StatsUpdated')]
    public function updateStats(array $data): void
    {
        $this->realtimeStats = array_merge($this->realtimeStats, $data);
    }

    #[On('echo:analytics,TrackPlayed')]
    public function trackPlayed(array $data): void
    {
        $this->realtimeStats['tracks_played']++;
        $this->dispatch('track-played-animation', trackId: $data['track_id']);
    }

    #[On('echo:analytics,UserRegistered')]
    public function userRegistered(): void
    {
        $this->realtimeStats['new_users']++;
    }

    #[On('echo:analytics,PurchaseCompleted')]
    public function purchaseCompleted(array $data): void
    {
        $this->realtimeStats['revenue'] += $data['amount'];
    }

    private function loadInitialStats(): void
    {
        $this->realtimeStats = [
            'tracks_played' => cache()->get('stats.tracks_played_today', 0),
            'new_users' => cache()->get('stats.new_users_today', 0),
            'revenue' => cache()->get('stats.revenue_today', 0),
            'active_sessions' => cache()->get('stats.active_sessions', 0),
        ];
    }
}; ?>

<div class="live-analytics grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Tracks Played -->
    <div class="stat-card bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Tracks Played</p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ number_format($realtimeStats['tracks_played']) }}
                </p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
                <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M18 3a1 1 0 00-1.196-.98l-10 2A1 1 0 006 5v9.114A4.369 4.369 0 005 14c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V7.82l8-1.6v5.894A4.37 4.37 0 0015 12c-1.657 0-3 .895-3 2s1.343 2 3 2 3-.895 3-2V3z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- New Users -->
    <div class="stat-card bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">New Users</p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ number_format($realtimeStats['new_users']) }}
                </p>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Revenue -->
    <div class="stat-card bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Revenue</p>
                <p class="text-3xl font-bold text-gray-900">
                    ${{ number_format($realtimeStats['revenue'], 2) }}
                </p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
                <svg class="w-6 h-6 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Active Sessions -->
    <div class="stat-card bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Active Sessions</p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ number_format($realtimeStats['active_sessions']) }}
                </p>
            </div>
            <div class="p-3 bg-purple-100 rounded-full">
                <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>
</div>
```

## Real-time Features

### Live Updates with Broadcasting

```php
<?php
// resources/views/livewire/live-music-dashboard.blade.php

use function Livewire\Volt\{state, on, mount};

state(['nowPlaying' => null, 'listeners' => 0, 'queue' => []]);

mount(function () {
    $this->nowPlaying = Cache::get('now_playing');
    $this->listeners = Cache::get('listener_count', 0);
    $this->queue = Cache::get('music_queue', []);
});

on([
    'track-changed' => function ($track) {
        $this->nowPlaying = $track;
        $this->dispatch('track-updated', $track);
    },
    'listener-joined' => function () {
        $this->listeners++;
    },
    'listener-left' => function () {
        $this->listeners = max(0, $this->listeners - 1);
    },
    'queue-updated' => function ($queue) {
        $this->queue = $queue;
    }
]);

?>

<div class="live-dashboard">
    <div class="now-playing-section">
        <h2>Now Playing</h2>
        @if($nowPlaying)
            <div class="track-info">
                <h3>{{ $nowPlaying['name'] }}</h3>
                <p>{{ $nowPlaying['artist'] }} - {{ $nowPlaying['album'] }}</p>
                <div class="progress-bar">
                    <div class="progress" style="width: {{ $nowPlaying['progress'] }}%"></div>
                </div>
            </div>
        @else
            <p>No track currently playing</p>
        @endif
    </div>

    <div class="listeners-section">
        <h3>Live Listeners: {{ $listeners }}</h3>
    </div>

    <div class="queue-section">
        <h3>Up Next</h3>
        <ul>
            @foreach($queue as $track)
                <li>{{ $track['name'] }} - {{ $track['artist'] }}</li>
            @endforeach
        </ul>
    </div>
</div>
```

### WebSocket Integration

```php
<?php
// resources/views/livewire/real-time-notifications.blade.php

use function Livewire\Volt\{state, on, mount};

state(['notifications' => [], 'unreadCount' => 0]);

mount(function () {
    $this->notifications = auth()->user()->notifications()->latest()->take(10)->get();
    $this->unreadCount = auth()->user()->unreadNotifications()->count();
});

on([
    'notification-received' => function ($notification) {
        array_unshift($this->notifications, $notification);
        $this->unreadCount++;
        $this->dispatch('show-toast', [
            'message' => $notification['message'],
            'type' => $notification['type']
        ]);
    },
    'notification-read' => function ($notificationId) {
        $this->notifications = collect($this->notifications)
            ->map(function ($notification) use ($notificationId) {
                if ($notification['id'] === $notificationId) {
                    $notification['read_at'] = now();
                }
                return $notification;
            })->toArray();
        $this->unreadCount = max(0, $this->unreadCount - 1);
    }
]);

?>

<div class="notifications-dropdown" x-data="{ open: false }">
    <button @click="open = !open" class="notification-trigger">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-5 5v-5zM4 19h10a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        @if($unreadCount > 0)
            <span class="notification-badge">{{ $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" @click.away="open = false" class="notifications-panel">
        <div class="notifications-header">
            <h3>Notifications</h3>
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead" class="mark-all-read">
                    Mark all as read
                </button>
            @endif
        </div>

        <div class="notifications-list">
            @forelse($notifications as $notification)
                <div class="notification-item {{ $notification['read_at'] ? '' : 'unread' }}"
                     wire:click="markAsRead('{{ $notification['id'] }}')">
                    <div class="notification-content">
                        <p>{{ $notification['message'] }}</p>
                        <span class="notification-time">
                            {{ \Carbon\Carbon::parse($notification['created_at'])->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="no-notifications">No notifications</p>
            @endforelse
        </div>
    </div>
</div>
```

## State Management

### Global State with Livewire

```php
<?php
// app/Livewire/Concerns/ManagesGlobalState.php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;

trait ManagesGlobalState
{
    public function getGlobalState($key, $default = null)
    {
        return Cache::get("global_state.{$key}", $default);
    }

    public function setGlobalState($key, $value, $ttl = 3600)
    {
        Cache::put("global_state.{$key}", $value, $ttl);
        $this->dispatch('global-state-updated', ['key' => $key, 'value' => $value]);
    }

    public function syncGlobalState($keys = [])
    {
        $state = [];
        foreach ($keys as $key) {
            $state[$key] = $this->getGlobalState($key);
        }
        return $state;
    }
}
```

### Persistent State Management

```php
<?php
// resources/views/livewire/music-player-state.blade.php

use function Livewire\Volt\{state, computed, on, mount};
use App\Livewire\Concerns\ManagesGlobalState;

new class extends \Livewire\Volt\Component {
    use ManagesGlobalState;

    public $currentTrack = null;
    public $isPlaying = false;
    public $volume = 50;
    public $position = 0;
    public $playlist = [];
    public $shuffle = false;
    public $repeat = 'none'; // none, one, all

    public function mount()
    {
        $this->loadPlayerState();
    }

    public function loadPlayerState()
    {
        $state = $this->syncGlobalState([
            'current_track', 'is_playing', 'volume',
            'position', 'playlist', 'shuffle', 'repeat'
        ]);

        $this->currentTrack = $state['current_track'];
        $this->isPlaying = $state['is_playing'] ?? false;
        $this->volume = $state['volume'] ?? 50;
        $this->position = $state['position'] ?? 0;
        $this->playlist = $state['playlist'] ?? [];
        $this->shuffle = $state['shuffle'] ?? false;
        $this->repeat = $state['repeat'] ?? 'none';
    }

    public function savePlayerState()
    {
        $this->setGlobalState('current_track', $this->currentTrack);
        $this->setGlobalState('is_playing', $this->isPlaying);
        $this->setGlobalState('volume', $this->volume);
        $this->setGlobalState('position', $this->position);
        $this->setGlobalState('playlist', $this->playlist);
        $this->setGlobalState('shuffle', $this->shuffle);
        $this->setGlobalState('repeat', $this->repeat);
    }

    public function playTrack($trackId)
    {
        $track = \App\Models\Track::find($trackId);
        if ($track) {
            $this->currentTrack = $track->toArray();
            $this->isPlaying = true;
            $this->position = 0;
            $this->savePlayerState();
            $this->dispatch('track-changed', $this->currentTrack);
        }
    }

    public function togglePlayPause()
    {
        $this->isPlaying = !$this->isPlaying;
        $this->savePlayerState();
        $this->dispatch('playback-toggled', $this->isPlaying);
    }

    public function updateVolume($volume)
    {
        $this->volume = max(0, min(100, $volume));
        $this->savePlayerState();
        $this->dispatch('volume-changed', $this->volume);
    }

    public function updatePosition($position)
    {
        $this->position = $position;
        $this->savePlayerState();
    }
}; ?>

<div class="music-player-controls">
    <div class="track-info">
        @if($currentTrack)
            <img src="{{ $currentTrack['album_artwork'] ?? '/images/default-album.png' }}"
                 alt="Album artwork" class="album-art">
            <div class="track-details">
                <h4>{{ $currentTrack['name'] }}</h4>
                <p>{{ $currentTrack['artist_name'] }} - {{ $currentTrack['album_title'] }}</p>
            </div>
        @endif
    </div>

    <div class="playback-controls">
        <button wire:click="previousTrack" class="control-btn">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8.445 14.832A1 1 0 0010 14v-2.798l5.445 3.63A1 1 0 0017 14V6a1 1 0 00-1.555-.832L10 8.798V6a1 1 0 00-1.555-.832l-6 4a1 1 0 000 1.664l6 4z"/>
            </svg>
        </button>

        <button wire:click="togglePlayPause" class="play-pause-btn">
            @if($isPlaying)
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5 4a1 1 0 011 1v10a1 1 0 01-2 0V5a1 1 0 011-1zM14 4a1 1 0 011 1v10a1 1 0 01-2 0V5a1 1 0 011-1z"/>
                </svg>
            @else
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
            @endif
        </button>

        <button wire:click="nextTrack" class="control-btn">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4.555 5.168A1 1 0 003 6v8a1 1 0 001.555.832L10 11.202V14a1 1 0 001.555.832l6-4a1 1 0 000-1.664l-6-4A1 1 0 0010 6v2.798l-5.445-3.63z"/>
            </svg>
        </button>
    </div>

    <div class="volume-control">
        <input type="range"
               min="0"
               max="100"
               wire:model.live="volume"
               wire:change="updateVolume($event.target.value)"
               class="volume-slider">
        <span class="volume-display">{{ $volume }}%</span>
    </div>
</div>
```

## Component Communication

### Parent-Child Communication

```php
<?php
// resources/views/livewire/album-browser.blade.php

use function Livewire\Volt\{state, on, mount};

state(['selectedAlbum' => null, 'albums' => [], 'tracks' => []]);

mount(function () {
    $this->albums = \App\Models\Album::with('artist')->get();
});

on([
    'album-selected' => function ($albumId) {
        $this->selectedAlbum = \App\Models\Album::with(['tracks', 'artist'])->find($albumId);
        $this->tracks = $this->selectedAlbum ? $this->selectedAlbum->tracks : [];
        $this->dispatch('tracks-updated', $this->tracks);
    },
    'track-played' => function ($trackId) {
        $this->dispatch('add-to-history', $trackId);
    }
]);

?>

<div class="album-browser">
    <div class="albums-grid">
        @foreach($albums as $album)
            <div class="album-card"
                 wire:click="$dispatch('album-selected', {{ $album->id }})"
                 @class(['selected' => $selectedAlbum && $selectedAlbum->id === $album->id])>
                <img src="{{ $album->artwork_url }}" alt="{{ $album->title }}">
                <h3>{{ $album->title }}</h3>
                <p>{{ $album->artist->name }}</p>
            </div>
        @endforeach
    </div>

    @if($selectedAlbum)
        <div class="album-details">
            <livewire:track-list :album-id="$selectedAlbum->id" />
        </div>
    @endif
</div>
```

### Sibling Component Communication

```php
<?php
// resources/views/livewire/track-list.blade.php

use function Livewire\Volt\{state, on, mount};

state(['albumId' => null, 'tracks' => [], 'currentTrack' => null]);

mount(function () {
    if ($this->albumId) {
        $this->loadTracks();
    }
});

on([
    'tracks-updated' => function ($tracks) {
        $this->tracks = $tracks;
    },
    'track-changed' => function ($track) {
        $this->currentTrack = $track;
    }
]);

function loadTracks()
{
    $album = \App\Models\Album::with('tracks')->find($this->albumId);
    $this->tracks = $album ? $album->tracks : [];
}

function playTrack($trackId)
{
    $track = collect($this->tracks)->firstWhere('id', $trackId);
    if ($track) {
        $this->dispatch('track-played', $trackId);
        $this->dispatch('player-play-track', $track);
    }
}

?>

<div class="track-list">
    <h3>Tracks</h3>
    <div class="tracks">
        @foreach($tracks as $track)
            <div class="track-item"
                 @class(['playing' => $currentTrack && $currentTrack['id'] === $track['id']])>
                <button wire:click="playTrack({{ $track['id'] }})" class="play-btn">
                    @if($currentTrack && $currentTrack['id'] === $track['id'])
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 4a1 1 0 011 1v10a1 1 0 01-2 0V5a1 1 0 011-1zM14 4a1 1 0 011 1v10a1 1 0 01-2 0V5a1 1 0 011-1z"/>
                        </svg>
                    @else
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8 5v10l8-5-8-5z"/>
                        </svg>
                    @endif
                </button>

                <div class="track-info">
                    <span class="track-number">{{ $track['track_number'] }}</span>
                    <span class="track-name">{{ $track['name'] }}</span>
                    <span class="track-duration">{{ $track['formatted_duration'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

### Event Bus Pattern

```php
<?php
// resources/views/livewire/event-bus.blade.php

use function Livewire\Volt\{state, on, mount};

state(['events' => [], 'subscribers' => []]);

on([
    'register-subscriber' => function ($componentId, $events) {
        $this->subscribers[$componentId] = $events;
    },
    'unregister-subscriber' => function ($componentId) {
        unset($this->subscribers[$componentId]);
    },
    'broadcast-event' => function ($eventName, $data = []) {
        $this->events[] = [
            'name' => $eventName,
            'data' => $data,
            'timestamp' => now()->toISOString()
        ];

        // Notify all subscribers
        foreach ($this->subscribers as $componentId => $subscribedEvents) {
            if (in_array($eventName, $subscribedEvents)) {
                $this->dispatch("event-{$eventName}", $data)->to($componentId);
            }
        }
    }
]);

?>

<div class="event-bus" style="display: none;">
    <!-- Hidden component that manages global event communication -->
    <div class="debug-panel" x-data="{ show: false }" x-show="show">
        <h4>Event Bus Debug</h4>
        <div class="events-log">
            @foreach(array_slice($events, -10) as $event)
                <div class="event-entry">
                    <strong>{{ $event['name'] }}</strong>
                    <span>{{ $event['timestamp'] }}</span>
                    <pre>{{ json_encode($event['data'], JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endforeach
        </div>
    </div>
</div>
```

## Performance Optimization

### Lazy Loading and Deferred Components

```php
<?php
// resources/views/livewire/music/album-details.blade.php

use Livewire\Volt\Component;
use Livewire\Attributes\Lazy;
use App\Models\Album;

#[Lazy]
new class extends Component {
    public Album $album;
    public bool $showFullDescription = false;

    public function mount(Album $album): void
    {
        $this->album = $album;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="animate-pulse">
            <div class="h-64 bg-gray-200 rounded-lg mb-4"></div>
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
        </div>
        HTML;
    }

    public function loadTracks(): void
    {
        $this->album->load(['tracks' => fn($q) => $q->orderBy('track_number')]);
    }

    public function toggleDescription(): void
    {
        $this->showFullDescription = !$this->showFullDescription;
    }
}; ?>
```

### Efficient State Management

```php
<?php
// resources/views/livewire/music/search-interface.blade.php

use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use App\Services\SearchService;

new class extends Component {
    public string $query = '';
    public array $filters = [];
    public int $page = 1;

    #[Computed]
    public function searchResults()
    {
        if (strlen($this->query) < 3) {
            return collect();
        }

        return cache()->remember(
            "search.{$this->query}." . md5(serialize($this->filters)) . ".{$this->page}",
            now()->addMinutes(5),
            fn() => app(SearchService::class)->search($this->query, $this->filters, $this->page)
        );
    }

    public function updatedQuery(): void
    {
        $this->page = 1;
        $this->clearCache();
    }

    public function updatedFilters(): void
    {
        $this->page = 1;
        $this->clearCache();
    }

    private function clearCache(): void
    {
        cache()->forget("search.{$this->query}." . md5(serialize($this->filters)) . ".{$this->page}");
    }
}; ?>
```

## Testing Strategies

### Component Testing

```php
// tests/Feature/Livewire/ArtistBrowserTest.php

use Livewire\Volt\Volt;
use App\Models\Artist;
use App\Models\Category;

class ArtistBrowserTest extends TestCase
{
    /** @test */
    public function it_filters_artists_by_search_term()
    {
        $artist1 = Artist::factory()->create(['name' => 'The Beatles']);
        $artist2 = Artist::factory()->create(['name' => 'Led Zeppelin']);

        Volt::test('music.artist-browser')
            ->set('search', 'Beatles')
            ->assertSee('The Beatles')
            ->assertDontSee('Led Zeppelin');
    }

    /** @test */
    public function it_filters_artists_by_category()
    {
        $rockCategory = Category::factory()->create(['name' => 'Rock', 'type' => 'genre']);
        $jazzCategory = Category::factory()->create(['name' => 'Jazz', 'type' => 'genre']);
        
        $rockArtist = Artist::factory()->create(['name' => 'Rock Band']);
        $jazzArtist = Artist::factory()->create(['name' => 'Jazz Ensemble']);
        
        $rockArtist->categories()->attach($rockCategory);
        $jazzArtist->categories()->attach($jazzCategory);

        Volt::test('music.artist-browser')
            ->set('selectedCategories', [$rockCategory->id])
            ->assertSee('Rock Band')
            ->assertDontSee('Jazz Ensemble');
    }

    /** @test */
    public function it_sorts_artists_correctly()
    {
        Artist::factory()->create(['name' => 'Zebra', 'created_at' => now()->subDay()]);
        Artist::factory()->create(['name' => 'Alpha', 'created_at' => now()]);

        Volt::test('music.artist-browser')
            ->call('sortBy', 'name')
            ->assertSeeInOrder(['Alpha', 'Zebra']);
    }
}
```

## Best Practices

### Component Organization

1. **Single Responsibility**: Each component should have one clear purpose
2. **Functional Approach**: Prefer Volt functional components over class-based
3. **State Management**: Keep state minimal and focused
4. **Event Communication**: Use events for component communication
5. **Performance**: Implement lazy loading and caching where appropriate

### Accessibility Considerations

```php
// Accessible form components
<div class="form-group">
    <label for="search-input" class="sr-only">Search artists</label>
    <input 
        id="search-input"
        type="text" 
        wire:model.live.debounce.300ms="search"
        placeholder="Search artists..."
        aria-describedby="search-help"
        class="form-input"
    />
    <div id="search-help" class="sr-only">
        Search for artists by name. Results will update as you type.
    </div>
</div>

<!-- Accessible button states -->
<button 
    wire:click="toggleFavorite"
    aria-pressed="{{ $this->isFavorite() ? 'true' : 'false' }}"
    aria-label="{{ $this->isFavorite() ? 'Remove from favorites' : 'Add to favorites' }}"
    class="favorite-btn {{ $this->isFavorite() ? 'active' : '' }}"
>
    <span aria-hidden="true">♥</span>
</button>
```

### Security Best Practices

```php
// Input validation and sanitization
public function updatedSearch(): void
{
    $this->search = strip_tags($this->search);
    $this->resetPage();
}

// Authorization checks
public function deletePlaylist(int $playlistId): void
{
    $playlist = Playlist::findOrFail($playlistId);
    
    $this->authorize('delete', $playlist);
    
    $playlist->delete();
    $this->dispatch('playlist-deleted');
}

// Rate limiting
#[RateLimit(10, 60)] // 10 requests per minute
public function savePlaylist(string $name): void
{
    // Implementation
}
```

---

**Next**: [Performance Monitoring](170-performance-monitoring-guide.md) | **Back**: [Testing Approaches](160-testing-approaches-guide.md)

---

*This guide provides comprehensive patterns for building modern, accessible, and performant Livewire/Volt applications.*
