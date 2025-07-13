# 1. Flux/Flux-Pro Component Integration Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Flux Free Components](#2-flux-free-components)
- [3. Flux Pro Components](#3-flux-pro-components)
- [4. Data Display Components](#4-data-display-components)
- [5. Navigation Components](#5-navigation-components)
- [6. Interactive Components](#6-interactive-components)
- [7. Best Practices](#7-best-practices)
- [8. Navigation](#8-navigation)

## 1. Overview

This guide demonstrates how to integrate Flux and Flux-Pro UI components with Livewire/Volt functional components in the Chinook application. Flux provides a comprehensive set of accessible, well-designed components that integrate seamlessly with Livewire's reactive system.

## 2. Flux Free Components

### 2.1 Essential UI Components

#### 2.1.1 Buttons and Actions

```php
<?php
// Artist management actions
use function Livewire\Volt\{state};

state(['selectedArtists' => [], 'showBulkActions' => false]);

$selectArtist = function ($artistId) {
    if (in_array($artistId, $this->selectedArtists)) {
        $this->selectedArtists = array_filter($this->selectedArtists, fn($id) => $id !== $artistId);
    } else {
        $this->selectedArtists[] = $artistId;
    }
    $this->showBulkActions = count($this->selectedArtists) > 0;
};

$bulkDelete = function () {
    $this->dispatch('confirm-bulk-delete', count: count($this->selectedArtists));
};
?>

<div class="space-y-4">
    <!-- Primary Actions -->
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Artists</flux:heading>

        <div class="flex space-x-2">
            <flux:button
                wire:click="$dispatch('open-import-modal')"
                variant="ghost"
                icon="arrow-up-tray"
            >
                Import
            </flux:button>

            <flux:button
                wire:navigate
                href="{{ route('artists.create') }}"
                variant="primary"
                icon="plus"
            >
                Add Artist
            </flux:button>
        </div>
    </div>

    <!-- Bulk Actions -->
    @if($showBulkActions)
        <flux:card variant="warning" class="p-4">
            <div class="flex justify-between items-center">
                <flux:text>{{ count($selectedArtists) }} artists selected</flux:text>

                <div class="flex space-x-2">
                    <flux:button
                        wire:click="bulkDelete"
                        variant="danger"
                        size="sm"
                        icon="trash"
                    >
                        Delete Selected
                    </flux:button>

                    <flux:button
                        wire:click="$set('selectedArtists', [])"
                        variant="ghost"
                        size="sm"
                    >
                        Clear Selection
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif
</div>
```

#### 2.1.2 Search and Filter Interface

```php
<?php
// Search and filter interface using aliziodev/laravel-taxonomy
use function Livewire\Volt\{state, computed};
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state([
    'search' => '',
    'genreFilter' => null,
    'countryFilter' => null,
    'activeFilter' => 'all'
]);

$genres = computed(function () {
    return Taxonomy::where('type', 'genre')
        ->orderBy('name')
        ->pluck('name', 'id');
});

$clearFilters = function () {
    $this->search = '';
    $this->genreFilter = null;
    $this->countryFilter = null;
    $this->activeFilter = 'all';
};
?>

<div class="space-y-4">
    <!-- Search Bar -->
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search artists, albums, tracks..."
        icon="magnifying-glass"
        size="lg"
    />

    <!-- Filter Tabs -->
    <flux:tabs wire:model.live="activeFilter">
        <flux:tab name="all">All</flux:tab>
        <flux:tab name="artists">Artists</flux:tab>
        <flux:tab name="albums">Albums</flux:tab>
        <flux:tab name="tracks">Tracks</flux:tab>
    </flux:tabs>

    <!-- Advanced Filters -->
    <div class="flex flex-wrap gap-4">
        <flux:select
            wire:model.live="genreFilter"
            placeholder="All Genres"
            class="w-48"
        >
            @foreach($this->genres as $id => $name)
                <flux:option value="{{ $id }}">{{ $name }}</flux:option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model.live="countryFilter"
            placeholder="All Countries"
            class="w-48"
        >
            <flux:option value="US">United States</flux:option>
            <flux:option value="UK">United Kingdom</flux:option>
            <flux:option value="CA">Canada</flux:option>
        </flux:select>

        @if($search || $genreFilter || $countryFilter)
            <flux:button
                wire:click="clearFilters"
                variant="ghost"
                icon="x-mark"
            >
                Clear Filters
            </flux:button>
        @endif
    </div>
</div>
```

### 2.2 Form Components

#### 2.2.1 Artist Creation Form

```php
<?php
// Artist creation form with taxonomy integration
use function Livewire\Volt\{state, form, rules};
use App\Livewire\Forms\ArtistForm;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

form(ArtistForm::class);

state(['selectedTaxonomies' => [], 'showAdvanced' => false]);

rules(['selectedTaxonomies' => 'array|max:10']);

$availableTaxonomies = computed(function () {
    return Taxonomy::whereIn('type', ['genre', 'style', 'mood'])
        ->orderBy('type')
        ->orderBy('name')
        ->get()
        ->groupBy('type');
});

$toggleTaxonomy = function ($taxonomyId) {
    if (in_array($taxonomyId, $this->selectedTaxonomies)) {
        $this->selectedTaxonomies = array_diff($this->selectedTaxonomies, [$taxonomyId]);
    } else {
        $this->selectedTaxonomies[] = $taxonomyId;
    }
};

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
?>

<form wire:submit="save" class="space-y-6">
    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-6">Create New Artist</flux:heading>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Artist Name</flux:label>
                    <flux:input wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>

                <flux:field>
                    <flux:label>Biography</flux:label>
                    <flux:textarea wire:model="form.biography" rows="4" />
                    <flux:error name="form.biography" />
                </flux:field>
            </div>

            <div class="space-y-4">
                @if($showAdvanced)
                    <flux:field>
                        <flux:label>Website</flux:label>
                        <flux:input wire:model="form.website" type="url" />
                        <flux:error name="form.website" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Taxonomies</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($artist->taxonomies as $taxonomy)
                                <flux:badge variant="subtle">{{ $taxonomy->name }}</flux:badge>
                            @endforeach
                        </div>
                    </flux:field>
                @endif
            </div>
        </div>
        <!-- Taxonomy Selection -->
        <flux:fieldset>
            <flux:legend>Taxonomies</flux:legend>

            @foreach($this->availableTaxonomies as $type => $taxonomies)
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

        <div class="flex justify-between">
            <flux:button
                type="button"
                wire:click="$toggle('showAdvanced')"
                variant="ghost"
            >
                {{ $showAdvanced ? 'Hide' : 'Show' }} Advanced Options
            </flux:button>

            <div class="flex space-x-2">
                <flux:button
                    type="button"
                    wire:navigate
                    href="{{ route('artists.index') }}"
                    variant="ghost"
                >
                    Cancel
                </flux:button>

                <flux:button type="submit" variant="primary">
                    Create Artist
                </flux:button>
            </div>
        </div>
    </flux:card>
</form>
```

## 3. Flux Pro Components

### 3.1 Advanced Data Visualization

#### 3.1.1 Revenue Dashboard

```php
<?php
// Revenue dashboard with taxonomy-based analytics
use function Livewire\Volt\{state, computed};
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state(['selectedPeriod' => '30', 'selectedGenre' => null]);

$revenueData = computed(function () {
    $days = (int) $this->selectedPeriod;

    return \DB::table('invoice_lines')
        ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
        ->join('tracks', 'invoice_lines.track_id', '=', 'tracks.id')
        ->when($this->selectedGenre, function ($query) {
            $query->whereHas('tracks.taxonomies', function ($q) {
                $q->where('taxonomies.id', $this->selectedGenre);
            });
        })
        ->where('invoices.invoice_date', '>=', now()->subDays($days))
        ->selectRaw('DATE(invoices.invoice_date) as date, SUM(invoice_lines.quantity * invoice_lines.unit_price) as revenue')
        ->groupBy('date')
        ->orderBy('date')
        ->get();
});

$topGenres = computed(function () {
    return \DB::table('invoice_lines')
        ->join('tracks', 'invoice_lines.track_id', '=', 'tracks.id')
        ->join('taxonomy_terms', function ($join) {
            $join->on('tracks.id', '=', 'taxonomy_terms.taxonomizable_id')
                 ->where('taxonomy_terms.taxonomizable_type', 'App\\Models\\Track');
        })
        ->join('taxonomies', 'taxonomy_terms.taxonomy_id', '=', 'taxonomies.id')
        ->where('taxonomies.type', 'genre')
        ->selectRaw('taxonomies.name, SUM(invoice_lines.quantity * invoice_lines.unit_price) as revenue')
        ->groupBy('taxonomies.id', 'taxonomies.name')
        ->orderByDesc('revenue')
        ->limit(10)
        ->get();
});
?>

<div class="space-y-6">
    <!-- Controls -->
    <div class="flex justify-between items-center">
        <flux:heading size="xl">Revenue Analytics</flux:heading>

        <div class="flex space-x-4">
            <flux:select wire:model.live="selectedPeriod" class="w-32">
                <flux:option value="7">7 days</flux:option>
                <flux:option value="30">30 days</flux:option>
                <flux:option value="90">90 days</flux:option>
                <flux:option value="365">1 year</flux:option>
            </flux:select>

            <flux:select wire:model.live="selectedGenre" placeholder="All Genres" class="w-48">
                @foreach(Taxonomy::where('type', 'genre')->orderBy('name')->get() as $genre)
                    <flux:option value="{{ $genre->id }}">{{ $genre->name }}</flux:option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Revenue Chart -->
    <flux:card class="p-6">
        <flux:chart
            type="line"
            :data="$this->revenueData"
            x-field="date"
            y-field="revenue"
            height="400"
        />
    </flux:card>

    <!-- Top Genres -->
    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-4">Top Genres by Revenue</flux:heading>
        <flux:chart
            type="bar"
            :data="$this->topGenres"
            x-field="name"
            y-field="revenue"
            height="300"
        />
    </flux:card>
</div>
```

### 3.2 Advanced Form Components

#### 3.2.1 Multi-Step Album Creation

```php
<?php
// Multi-step album creation with taxonomy management
use function Livewire\Volt\{state, computed};
use App\Models\Artist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

state([
    'currentStep' => 1,
    'albumData' => ['title' => '', 'artist_id' => null, 'release_date' => null],
    'selectedTaxonomies' => [],
    'tracks' => []
]);

$artists = computed(fn() => Artist::orderBy('name')->get());

$availableTaxonomies = computed(function () {
    return Taxonomy::whereIn('type', ['genre', 'style', 'mood'])
        ->orderBy('type')
        ->orderBy('name')
        ->get()
        ->groupBy('type');
});

$nextStep = function () {
    $this->validateCurrentStep();
    $this->currentStep++;
};

$previousStep = fn() => $this->currentStep--;

$validateCurrentStep = function () {
    $rules = match($this->currentStep) {
        1 => ['albumData.title' => 'required|min:3', 'albumData.artist_id' => 'required'],
        2 => ['selectedTaxonomies' => 'required|array|min:1'],
        3 => ['tracks' => 'required|array|min:1']
    };

    $this->validate($rules);
};
?>

<div class="max-w-4xl mx-auto">
    <!-- Progress Indicator -->
    <flux:progress value="{{ ($currentStep / 3) * 100 }}" class="mb-8" />

    <!-- Step Content -->
    @if($currentStep === 1)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">Album Information</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Album Title</flux:label>
                    <flux:input wire:model="albumData.title" />
                    <flux:error name="albumData.title" />
                </flux:field>

                <flux:field>
                    <flux:label>Artist</flux:label>
                    <flux:select wire:model="albumData.artist_id" placeholder="Select artist...">
                        @foreach($this->artists as $artist)
                            <flux:option value="{{ $artist->id }}">{{ $artist->name }}</flux:option>
                        @endforeach
                    </flux:select>
                    <flux:error name="albumData.artist_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Release Date</flux:label>
                    <flux:input wire:model="albumData.release_date" type="date" />
                </flux:field>
            </div>
        </flux:card>
    @elseif($currentStep === 2)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">Select Taxonomies</flux:heading>

            @foreach($this->availableTaxonomies as $type => $taxonomies)
                <div class="mb-6">
                    <flux:subheading class="mb-3">{{ ucfirst($type) }}</flux:subheading>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                        @foreach($taxonomies as $taxonomy)
                            <flux:checkbox
                                wire:model="selectedTaxonomies"
                                value="{{ $taxonomy->id }}"
                                label="{{ $taxonomy->name }}"
                            />
                        @endforeach
                    </div>
                </div>
            @endforeach

            <flux:error name="selectedTaxonomies" />
        </flux:card>
    @elseif($currentStep === 3)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">Add Tracks</flux:heading>

            <div class="space-y-4">
                @foreach($tracks as $index => $track)
                    <div class="flex items-center space-x-4 p-4 border rounded">
                        <flux:input
                            wire:model="tracks.{{ $index }}.title"
                            placeholder="Track title"
                            class="flex-1"
                        />
                        <flux:input
                            wire:model="tracks.{{ $index }}.duration"
                            placeholder="Duration (mm:ss)"
                            class="w-24"
                        />
                        <flux:button
                            wire:click="removeTrack({{ $index }})"
                            variant="danger"
                            size="sm"
                            icon="trash"
                        />
                    </div>
                @endforeach

                <flux:button
                    wire:click="addTrack"
                    variant="ghost"
                    icon="plus"
                    class="w-full"
                >
                    Add Track
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Navigation -->
    <div class="flex justify-between mt-6">
        <flux:button
            wire:click="previousStep"
            variant="ghost"
            :disabled="$currentStep === 1"
        >
            Previous
        </flux:button>

        @if($currentStep < 3)
            <flux:button wire:click="nextStep" variant="primary">
                Next
            </flux:button>
        @else
            <flux:button wire:click="save" variant="primary">
                Create Album
            </flux:button>
        @endif
    </div>
</div>
```

## 4. Data Display Components

### 4.1 Artist Detail View

```php
<?php
// Comprehensive artist detail view with taxonomy integration
use function Livewire\Volt\{state, computed, mount};
use App\Models\Artist;

state(['artist' => null, 'activeSection' => 'overview']);

mount(function (Artist $artist) {
    $this->artist = $artist->load([
        'albums.tracks',
        'taxonomies',
        'media'
    ]);
});

$albumStats = computed(function () {
    return [
        'total_albums' => $this->artist->albums->count(),
        'total_tracks' => $this->artist->albums->sum(fn($album) => $album->tracks->count()),
        'total_duration' => $this->artist->albums->flatMap->tracks->sum('milliseconds'),
        'latest_release' => $this->artist->albums->sortByDesc('release_date')->first()?->release_date
    ];
});

$genreDistribution = computed(function () {
    return $this->artist->taxonomies
        ->where('type', 'genre')
        ->map(function ($taxonomy) {
            $trackCount = $this->artist->albums
                ->flatMap->tracks
                ->filter(fn($track) => $track->taxonomies->contains($taxonomy))
                ->count();

            return [
                'name' => $taxonomy->name,
                'count' => $trackCount,
                'percentage' => $this->albumStats['total_tracks'] > 0
                    ? round(($trackCount / $this->albumStats['total_tracks']) * 100, 1)
                    : 0
            ];
        })
        ->sortByDesc('count');
});
?>

<div class="space-y-6">
    <!-- Header -->
    <flux:card class="p-6">
        <div class="flex items-start space-x-6">
            <flux:avatar
                :src="$artist->getFirstMediaUrl('avatar')"
                :alt="$artist->name"
                size="2xl"
            />

            <div class="flex-1">
                <flux:heading size="2xl">{{ $artist->name }}</flux:heading>
                <flux:text variant="muted" class="mt-2">
                    {{ $this->albumStats['total_albums'] }} albums •
                    {{ $this->albumStats['total_tracks'] }} tracks
                </flux:text>

                @if($artist->biography)
                    <flux:text class="mt-4">{{ $artist->biography }}</flux:text>
                @endif
            </div>

            <div class="flex space-x-2">
                <flux:button variant="primary" icon="play">
                    Play All
                </flux:button>
                <flux:button variant="ghost" icon="heart">
                    Follow
                </flux:button>
            </div>
        </div>
    </flux:card>

    <!-- Navigation Tabs -->
    <flux:tabs wire:model.live="activeSection">
        <flux:tab name="overview">Overview</flux:tab>
        <flux:tab name="albums">Albums</flux:tab>
        <flux:tab name="tracks">Popular Tracks</flux:tab>
        <flux:tab name="analytics">Analytics</flux:tab>
    </flux:tabs>

    <!-- Content Sections -->
    @if($activeSection === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Recent Albums -->
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">Recent Albums</flux:heading>
                    <div class="space-y-4">
                        @foreach($artist->albums->sortByDesc('release_date')->take(3) as $album)
                            <div class="flex items-center space-x-4">
                                <flux:avatar
                                    :src="$album->getFirstMediaUrl('cover')"
                                    :alt="$album->title"
                                    size="lg"
                                />
                                <div class="flex-1">
                                    <flux:heading size="sm">{{ $album->title }}</flux:heading>
                                    <flux:text variant="muted">{{ $album->release_date?->format('Y') }}</flux:text>
                                </div>
                                <flux:button variant="ghost" size="sm" icon="play" />
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Taxonomies -->
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">Taxonomies</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($artist->taxonomies->where('type', 'genre') as $genre)
                            <flux:badge variant="subtle">{{ $genre->name }}</flux:badge>
                        @endforeach
                    </div>
                </flux:card>

                <!-- Statistics -->
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">Statistics</flux:heading>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <flux:text>Total Albums</flux:text>
                            <flux:text>{{ $this->albumStats['total_albums'] }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text>Total Tracks</flux:text>
                            <flux:text>{{ $this->albumStats['total_tracks'] }}</flux:text>
                        </div>
                        <div class="flex justify-between">
                            <flux:text>Latest Release</flux:text>
                            <flux:text>{{ $this->albumStats['latest_release']?->format('Y') ?? 'N/A' }}</flux:text>
                        </div>
                    </div>
                </flux:card>
            </div>
        </div>
    @endif
</div>
```

## 5. Navigation Components

### 5.1 Responsive Navigation Menu

```php
<?php
// Main navigation with taxonomy-based filtering
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

$toggleMobileMenu = fn() => $this->mobileMenuOpen = !$this->mobileMenuOpen;
?>

<nav class="bg-white shadow-sm border-b">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo and Primary Nav -->
            <div class="flex items-center space-x-8">
                <flux:link href="{{ route('home') }}" class="flex items-center">
                    <flux:icon name="musical-note" size="lg" class="text-blue-600" />
                    <flux:heading size="lg" class="ml-2">Chinook</flux:heading>
                </flux:link>

                <div class="hidden md:flex space-x-6">
                    <flux:link href="{{ route('artists.index') }}" wire:navigate>Artists</flux:link>
                    <flux:link href="{{ route('albums.index') }}" wire:navigate>Albums</flux:link>
                    <flux:link href="{{ route('tracks.index') }}" wire:navigate>Tracks</flux:link>
                    <flux:link href="{{ route('playlists.index') }}" wire:navigate>Playlists</flux:link>
                </div>
            </div>

            <!-- Search and User Menu -->
            <div class="flex items-center space-x-4">
                <flux:input
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search..."
                    icon="magnifying-glass"
                    class="w-64 hidden md:block"
                />

                <flux:dropdown>
                    <flux:dropdown.trigger>
                        <flux:button variant="ghost" icon="user" />
                    </flux:dropdown.trigger>

                    <flux:dropdown.menu>
                        <flux:dropdown.item href="{{ route('profile') }}">Profile</flux:dropdown.item>
                        <flux:dropdown.item href="{{ route('settings') }}">Settings</flux:dropdown.item>
                        <flux:dropdown.divider />
                        <flux:dropdown.item wire:click="logout">Logout</flux:dropdown.item>
                    </flux:dropdown.menu>
                </flux:dropdown>

                <!-- Mobile menu button -->
                <flux:button
                    wire:click="toggleMobileMenu"
                    variant="ghost"
                    icon="bars-3"
                    class="md:hidden"
                />
            </div>
        </div>

        <!-- Genre Quick Links -->
        <div class="hidden md:flex py-2 space-x-4 border-t">
            <flux:text variant="muted" class="text-sm">Popular:</flux:text>
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

    <!-- Mobile Menu -->
    @if($mobileMenuOpen)
        <div class="md:hidden border-t bg-gray-50">
            <div class="px-4 py-2 space-y-2">
                <flux:link href="{{ route('artists.index') }}" wire:navigate class="block">Artists</flux:link>
                <flux:link href="{{ route('albums.index') }}" wire:navigate class="block">Albums</flux:link>
                <flux:link href="{{ route('tracks.index') }}" wire:navigate class="block">Tracks</flux:link>
                <flux:link href="{{ route('playlists.index') }}" wire:navigate class="block">Playlists</flux:link>
            </div>
        </div>
    @endif
</nav>
```

## 6. Interactive Components

### 6.1 Music Player Component

```php
<?php
// Advanced music player with playlist management
use function Livewire\Volt\{state, on};

state([
    'currentTrack' => null,
    'isPlaying' => false,
    'currentTime' => 0,
    'duration' => 0,
    'volume' => 75,
    'queue' => [],
    'shuffle' => false,
    'repeat' => 'none' // none, one, all
]);

// Player controls
$play = fn() => $this->isPlaying = true;
$pause = fn() => $this->isPlaying = false;
$togglePlay = fn() => $this->isPlaying = !$this->isPlaying;

$nextTrack = function () {
    $currentIndex = array_search($this->currentTrack['id'], array_column($this->queue, 'id'));
    if ($currentIndex !== false && $currentIndex < count($this->queue) - 1) {
        $this->currentTrack = $this->queue[$currentIndex + 1];
        $this->dispatch('track-changed')->to('browser');
    }
};

$previousTrack = function () {
    $currentIndex = array_search($this->currentTrack['id'], array_column($this->queue, 'id'));
    if ($currentIndex !== false && $currentIndex > 0) {
        $this->currentTrack = $this->queue[$currentIndex - 1];
        $this->dispatch('track-changed')->to('browser');
    }
};

// Listen for external play requests
on(['play-track' => function ($trackId, $queue = []) {
    $this->currentTrack = \App\Models\Track::with(['artist', 'album', 'taxonomies'])->find($trackId);
    $this->queue = $queue ?: [$this->currentTrack];
    $this->isPlaying = true;
    $this->dispatch('track-changed')->to('browser');
}]);
?>

<div class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg z-50">
    <div class="max-w-7xl mx-auto px-4 py-3">
        @if($currentTrack)
            <div class="flex items-center justify-between">
                <!-- Track Info -->
                <div class="flex items-center space-x-4 flex-1">
                    <flux:avatar
                        :src="$currentTrack->album->getFirstMediaUrl('cover')"
                        :alt="$currentTrack->album->title"
                        size="md"
                    />

                    <div class="min-w-0 flex-1">
                        <flux:heading size="sm" class="truncate">{{ $currentTrack->name }}</flux:heading>
                        <flux:text variant="muted" class="text-sm truncate">
                            {{ $currentTrack->artist->name }} • {{ $currentTrack->album->title }}
                        </flux:text>
                    </div>
                </div>

                <!-- Player Controls -->
                <div class="flex items-center space-x-4">
                    <flux:button
                        wire:click="previousTrack"
                        variant="ghost"
                        size="sm"
                        icon="backward"
                    />

                    <flux:button
                        wire:click="togglePlay"
                        :variant="$isPlaying ? 'primary' : 'ghost'"
                        :icon="$isPlaying ? 'pause' : 'play'"
                    />

                    <flux:button
                        wire:click="nextTrack"
                        variant="ghost"
                        size="sm"
                        icon="forward"
                    />
                </div>

                <!-- Volume and Options -->
                <div class="flex items-center space-x-4 flex-1 justify-end">
                    <div class="flex items-center space-x-2">
                        <flux:icon name="speaker-wave" size="sm" />
                        <flux:slider
                            wire:model.live="volume"
                            min="0"
                            max="100"
                            class="w-20"
                        />
                    </div>

                    <flux:button
                        wire:click="$toggle('shuffle')"
                        :variant="$shuffle ? 'primary' : 'ghost'"
                        size="sm"
                        icon="arrow-path-rounded-square"
                    />

                    <flux:dropdown>
                        <flux:dropdown.trigger>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                        </flux:dropdown.trigger>

                        <flux:dropdown.menu>
                            <flux:dropdown.item>Add to Playlist</flux:dropdown.item>
                            <flux:dropdown.item>View Album</flux:dropdown.item>
                            <flux:dropdown.item>View Artist</flux:dropdown.item>
                        </flux:dropdown.menu>
                    </flux:dropdown>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mt-2">
                <flux:progress
                    :value="$duration > 0 ? ($currentTime / $duration) * 100 : 0"
                    class="h-1"
                />
            </div>
        @endif
    </div>
</div>
```

## 7. Best Practices

### 7.1 Component Organization

1. **Single Responsibility**: Each component should have one clear purpose
2. **Consistent Styling**: Use Flux design tokens and variants consistently
3. **Accessibility First**: Leverage Flux's built-in accessibility features
4. **Performance**: Use computed properties and caching for expensive operations
5. **State Management**: Keep component state minimal and focused

### 7.2 Flux Integration Guidelines

1. **Use Flux Variants**: Leverage predefined variants instead of custom CSS
2. **Consistent Spacing**: Use Flux's spacing system for layouts
3. **Icon Usage**: Use Flux's icon system for consistency
4. **Form Validation**: Integrate with Laravel's validation system
5. **Event Handling**: Use Livewire events for component communication

### 7.3 Taxonomy Integration Best Practices

1. **Consistent Naming**: Use 'taxonomies' instead of 'categories' throughout
2. **Type Safety**: Validate taxonomy types in forms and filters
3. **Performance**: Eager load taxonomy relationships when needed
4. **User Experience**: Provide clear taxonomy selection interfaces
5. **Data Integrity**: Use proper validation for taxonomy assignments

## 8. Navigation

**← Previous** [Volt Functional Component Patterns Guide](110-volt-functional-patterns-guide.md)
**Next →** [SPA Navigation Guide](130-spa-navigation-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/120-flux-component-integration-guide.md on 2025-07-11

*This guide provides comprehensive patterns for integrating Flux components with Livewire/Volt. Continue with the SPA navigation guide for advanced routing patterns.*

[⬆️ Back to Top](#1-fluxflux-pro-component-integration-guide)
