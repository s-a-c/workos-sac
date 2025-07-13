# Flux/Flux-Pro Component Integration Guide

## Table of Contents

- [Overview](#overview)
- [Flux Free Components](#flux-free-components)
- [Flux Pro Components](#flux-pro-components)
- [Form Components](#form-components)
- [Data Display Components](#data-display-components)
- [Navigation Components](#navigation-components)
- [Layout Components](#layout-components)
- [Interactive Components](#interactive-components)
- [Advanced Patterns](#advanced-patterns)
- [Accessibility Integration](#accessibility-integration)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide demonstrates how to integrate Flux and Flux-Pro UI components with Livewire/Volt functional components in the Chinook application. Flux provides a comprehensive set of accessible, well-designed components that integrate seamlessly with Livewire's reactive system.

## Flux Free Components

### Essential UI Components

#### Buttons and Actions

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

#### Input Components

```php
<?php
// Search and filter interface
use function Livewire\Volt\{state, computed};
use App\Models\Category;
use App\Enums\CategoryType;

state([
    'search' => '',
    'genreFilter' => null,
    'countryFilter' => null,
    'activeFilter' => 'all'
]);

$genres = computed(function () {
    return Category::where('type', CategoryType::GENRE)
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

<flux:card class="p-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search Input -->
        <flux:field>
            <flux:label>Search Artists</flux:label>
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name or biography..."
                icon="magnifying-glass"
                icon:trailing="x-mark"
                wire:click:trailing="$set('search', '')"
            />
        </flux:field>

        <!-- Genre Filter -->
        <flux:field>
            <flux:label>Genre</flux:label>
            <flux:select
                wire:model.live="genreFilter"
                placeholder="All genres..."
            >
                @foreach($this->genres as $id => $name)
                    <flux:option value="{{ $id }}">{{ $name }}</flux:option>
                @endforeach
            </flux:select>
        </flux:field>

        <!-- Country Filter -->
        <flux:field>
            <flux:label>Country</flux:label>
            <flux:input
                wire:model.live="countryFilter"
                placeholder="Filter by country..."
                icon="globe-alt"
            />
        </flux:field>

        <!-- Status Filter -->
        <flux:field>
            <flux:label>Status</flux:label>
            <flux:radio.group wire:model.live="activeFilter">
                <flux:radio value="all" label="All Artists" />
                <flux:radio value="active" label="Active Only" />
                <flux:radio value="inactive" label="Inactive Only" />
            </flux:radio.group>
        </flux:field>
    </div>

    <!-- Clear Filters -->
    <div class="mt-4 flex justify-end">
        <flux:button
            wire:click="clearFilters"
            variant="ghost"
            size="sm"
            icon="x-mark"
        >
            Clear Filters
        </flux:button>
    </div>
</flux:card>
```

#### Modal Components

```php
<?php
// Artist detail modal
use function Livewire\Volt\{state, mount};
use App\Models\Artist;

state(['artist' => null, 'showModal' => false, 'activeTab' => 'overview']);

mount(function (Artist $artist) {
    $this->artist = $artist;
});

$openModal = fn() => $this->showModal = true;
$closeModal = fn() => $this->showModal = false;
?>

<flux:modal wire:model="showModal" variant="flyout">
    <flux:modal.header>
        <div class="flex items-center space-x-4">
            <flux:avatar
                :src="$artist->getFirstMediaUrl('avatar')"
                :alt="$artist->name"
                size="lg"
            />
            <div>
                <flux:heading size="lg">{{ $artist->name }}</flux:heading>
                <flux:text variant="muted">{{ $artist->country }}</flux:text>
            </div>
        </div>
    </flux:modal.header>

    <!-- Tabs Navigation -->
    <flux:tabs wire:model="activeTab">
        <flux:tab name="overview" icon="information-circle">Overview</flux:tab>
        <flux:tab name="albums" icon="musical-note">Albums</flux:tab>
        <flux:tab name="analytics" icon="chart-bar">Analytics</flux:tab>
    </flux:tabs>

    <!-- Tab Content -->
    <div class="p-6">
        @if($activeTab === 'overview')
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Biography</flux:label>
                    <flux:text>{{ $artist->biography }}</flux:text>
                </flux:field>

                <flux:field>
                    <flux:label>Genres</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($artist->categories as $category)
                            <flux:badge variant="subtle">{{ $category->name }}</flux:badge>
                        @endforeach
                    </div>
                </flux:field>
            </div>
        @endif

        @if($activeTab === 'albums')
            <div class="space-y-3">
                @foreach($artist->albums as $album)
                    <flux:card class="p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <flux:heading size="sm">{{ $album->title }}</flux:heading>
                                <flux:text variant="muted">{{ $album->release_date->format('Y') }}</flux:text>
                            </div>
                            <flux:badge>{{ $album->tracks_count }} tracks</flux:badge>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal.footer>
        <flux:button
            wire:navigate
            href="{{ route('artists.edit', $artist->slug) }}"
            variant="primary"
        >
            Edit Artist
        </flux:button>
        <flux:button wire:click="closeModal" variant="ghost">Close</flux:button>
    </flux:modal.footer>
</flux:modal>
```

## Flux Pro Components

### Advanced UI Components

#### Data Tables with Sorting and Filtering

```php
<?php
// Advanced track listing with Flux Pro table
use function Livewire\Volt\{state, computed};
use App\Models\Track;

state([
    'search' => '',
    'sortBy' => 'name',
    'sortDirection' => 'asc',
    'selectedTracks' => [],
    'perPage' => 25
]);

$tracks = computed(function () {
    return Track::query()
        ->with(['album.artist', 'mediaType'])
        ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate($this->perPage);
});

$sortBy = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};

$toggleTrackSelection = function ($trackId) {
    if (in_array($trackId, $this->selectedTracks)) {
        $this->selectedTracks = array_filter($this->selectedTracks, fn($id) => $id !== $trackId);
    } else {
        $this->selectedTracks[] = $trackId;
    }
};
?>

<div class="space-y-6">
    <!-- Search and Controls -->
    <div class="flex justify-between items-center">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search tracks..."
            icon="magnifying-glass"
            class="w-96"
        />

        <div class="flex space-x-2">
            <flux:select wire:model.live="perPage" size="sm">
                <flux:option value="10">10 per page</flux:option>
                <flux:option value="25">25 per page</flux:option>
                <flux:option value="50">50 per page</flux:option>
            </flux:select>
        </div>
    </div>

    <!-- Data Table -->
    <flux:table>
        <flux:columns>
            <flux:column>
                <flux:checkbox
                    wire:model="selectAll"
                    wire:click="toggleSelectAll"
                />
            </flux:column>

            <flux:column
                sortable
                wire:click="sortBy('name')"
                :sorted="$sortBy === 'name' ? $sortDirection : null"
            >
                Track Name
            </flux:column>

            <flux:column
                sortable
                wire:click="sortBy('album.title')"
                :sorted="$sortBy === 'album.title' ? $sortDirection : null"
            >
                Album
            </flux:column>

            <flux:column>Artist</flux:column>
            <flux:column>Duration</flux:column>
            <flux:column>Actions</flux:column>
        </flux:columns>

        <flux:rows>
            @foreach($this->tracks as $track)
                <flux:row wire:key="track-{{ $track->id }}">
                    <flux:cell>
                        <flux:checkbox
                            wire:click="toggleTrackSelection({{ $track->id }})"
                            :checked="in_array($track->id, $selectedTracks)"
                        />
                    </flux:cell>

                    <flux:cell>
                        <div class="flex items-center space-x-3">
                            <flux:button
                                wire:click="playTrack({{ $track->id }})"
                                variant="ghost"
                                size="sm"
                                icon="play"
                            />
                            <div>
                                <flux:heading size="sm">{{ $track->name }}</flux:heading>
                                @if($track->is_explicit)
                                    <flux:badge variant="warning" size="sm">Explicit</flux:badge>
                                @endif
                            </div>
                        </div>
                    </flux:cell>

                    <flux:cell>
                        <flux:text>{{ $track->album->title }}</flux:text>
                    </flux:cell>

                    <flux:cell>
                        <flux:text>{{ $track->album->artist->name }}</flux:text>
                    </flux:cell>

                    <flux:cell>
                        <flux:text variant="muted">
                            {{ gmdate('i:s', $track->milliseconds / 1000) }}
                        </flux:text>
                    </flux:cell>

                    <flux:cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />

                            <flux:menu>
                                <flux:menu.item
                                    wire:click="addToPlaylist({{ $track->id }})"
                                    icon="plus"
                                >
                                    Add to Playlist
                                </flux:menu.item>

                                <flux:menu.item
                                    wire:navigate
                                    href="{{ route('tracks.edit', $track->slug) }}"
                                    icon="pencil"
                                >
                                    Edit Track
                                </flux:menu.item>

                                <flux:menu.separator />

                                <flux:menu.item
                                    wire:click="deleteTrack({{ $track->id }})"
                                    icon="trash"
                                    variant="danger"
                                >
                                    Delete
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:cell>
                </flux:row>
            @endforeach
        </flux:rows>
    </flux:table>

    <!-- Pagination -->
    <div class="flex justify-between items-center">
        <flux:text variant="muted">
            Showing {{ $this->tracks->firstItem() }} to {{ $this->tracks->lastItem() }}
            of {{ $this->tracks->total() }} tracks
        </flux:text>

        {{ $this->tracks->links() }}
    </div>
</div>
```

#### Charts and Analytics

```php
<?php
// Sales analytics dashboard with Flux Pro charts
use function Livewire\Volt\{state, computed};
use App\Models\Invoice;

state([
    'dateRange' => '30',
    'chartType' => 'line'
]);

$salesData = computed(function () {
    $days = (int) $this->dateRange;
    $startDate = now()->subDays($days);

    return Invoice::where('created_at', '>=', $startDate)
        ->selectRaw('DATE(created_at) as date, SUM(total) as total, COUNT(*) as count')
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->map(function ($item) {
            return [
                'date' => $item->date,
                'sales' => (float) $item->total,
                'orders' => $item->count
            ];
        });
});

$topGenres = computed(function () {
    return \DB::table('invoice_lines')
        ->join('tracks', 'invoice_lines.track_id', '=', 'tracks.id')
        ->join('categorizables', function ($join) {
            $join->on('tracks.id', '=', 'categorizables.categorizable_id')
                 ->where('categorizables.categorizable_type', 'App\\Models\\Track');
        })
        ->join('categories', 'categorizables.category_id', '=', 'categories.id')
        ->where('categories.type', 'genre')
        ->selectRaw('categories.name, SUM(invoice_lines.quantity * invoice_lines.unit_price) as revenue')
        ->groupBy('categories.id', 'categories.name')
        ->orderByDesc('revenue')
        ->limit(10)
        ->get();
});
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Sales Chart -->
    <flux:card class="p-6">
        <div class="flex justify-between items-center mb-6">
            <flux:heading size="lg">Sales Overview</flux:heading>

            <div class="flex space-x-2">
                <flux:select wire:model.live="dateRange" size="sm">
                    <flux:option value="7">Last 7 days</flux:option>
                    <flux:option value="30">Last 30 days</flux:option>
                    <flux:option value="90">Last 90 days</flux:option>
                </flux:select>

                <flux:select wire:model.live="chartType" size="sm">
                    <flux:option value="line">Line Chart</flux:option>
                    <flux:option value="bar">Bar Chart</flux:option>
                    <flux:option value="area">Area Chart</flux:option>
                </flux:select>
            </div>
        </div>

        <flux:chart
            :type="$chartType"
            :data="$this->salesData"
            x-axis="date"
            y-axis="sales"
            height="300"
            :options="[
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => true],
                    'tooltip' => ['enabled' => true]
                ]
            ]"
        />
    </flux:card>

    <!-- Genre Revenue Chart -->
    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-6">Top Genres by Revenue</flux:heading>

        <flux:chart
            type="doughnut"
            :data="$this->topGenres"
            x-axis="name"
            y-axis="revenue"
            height="300"
            :options="[
                'responsive' => true,
                'plugins' => [
                    'legend' => ['position' => 'bottom']
                ]
            ]"
        />
    </flux:card>
</div>
```

#### Calendar and Date Picker

```php
<?php
// Event scheduling with Flux Pro calendar
use function Livewire\Volt\{state, computed};
use App\Models\Event;

state([
    'selectedDate' => null,
    'showEventModal' => false,
    'eventTitle' => '',
    'eventDescription' => ''
]);

$events = computed(function () {
    return Event::whereMonth('event_date', now()->month)
        ->whereYear('event_date', now()->year)
        ->get()
        ->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->event_date->format('Y-m-d'),
                'type' => $event->type
            ];
        });
});

$selectDate = function ($date) {
    $this->selectedDate = $date;
    $this->showEventModal = true;
};

$createEvent = function () {
    Event::create([
        'title' => $this->eventTitle,
        'description' => $this->eventDescription,
        'event_date' => $this->selectedDate,
        'created_by' => auth()->id()
    ]);

    $this->reset(['eventTitle', 'eventDescription', 'showEventModal']);
    $this->dispatch('event-created');
};
?>

<div class="space-y-6">
    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-6">Event Calendar</flux:heading>

        <flux:calendar
            wire:model="selectedDate"
            wire:click:date="selectDate"
            :events="$this->events"
            :options="[
                'selectable' => true,
                'editable' => true,
                'eventDisplay' => 'block'
            ]"
        />
    </flux:card>

    <!-- Event Creation Modal -->
    <flux:modal wire:model="showEventModal">
        <flux:modal.header>
            <flux:heading size="lg">Create Event</flux:heading>
        </flux:modal.header>

        <form wire:submit="createEvent">
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Event Title</flux:label>
                    <flux:input wire:model="eventTitle" required />
                    <flux:error name="eventTitle" />
                </flux:field>

                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="eventDescription" rows="3" />
                    <flux:error name="eventDescription" />
                </flux:field>

                <flux:field>
                    <flux:label>Date</flux:label>
                    <flux:date-picker wire:model="selectedDate" />
                    <flux:error name="selectedDate" />
                </flux:field>
            </div>

            <flux:modal.footer>
                <flux:button type="submit" variant="primary">Create Event</flux:button>
                <flux:button wire:click="$set('showEventModal', false)" variant="ghost">Cancel</flux:button>
            </flux:modal.footer>
        </form>
    </flux:modal>
</div>
```

## Form Components

### Advanced Form Patterns

#### Multi-Step Form with Validation

```php
<?php
// Album creation with multi-step form
use function Livewire\Volt\{state, rules, form};
use App\Livewire\Forms\AlbumForm;
use App\Models\Artist;

form(AlbumForm::class);

state([
    'currentStep' => 1,
    'totalSteps' => 4,
    'artists' => fn() => Artist::orderBy('name')->get()
]);

// Step-specific validation
rules(fn() => match($this->currentStep) {
    1 => [
        'form.title' => 'required|min:3|max:255',
        'form.artist_id' => 'required|exists:artists,id'
    ],
    2 => [
        'form.release_date' => 'required|date',
        'form.label' => 'required|max:255',
        'form.catalog_number' => 'nullable|max:100'
    ],
    3 => [
        'form.cover_image' => 'nullable|image|max:2048',
        'form.description' => 'nullable|max:1000'
    ],
    4 => [
        'form.tracks' => 'required|array|min:1',
        'form.tracks.*.name' => 'required|max:255',
        'form.tracks.*.duration' => 'required|integer|min:1'
    ],
    default => []
});

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

$addTrack = function () {
    $this->form->tracks[] = ['name' => '', 'duration' => 0];
};

$removeTrack = function ($index) {
    unset($this->form->tracks[$index]);
    $this->form->tracks = array_values($this->form->tracks);
};

$submit = function () {
    $this->validate();

    $album = $this->form->store();

    $this->dispatch('album-created', albumId: $album->id);
    return $this->redirect(route('albums.show', $album->slug), navigate: true);
};
?>

<flux:card class="max-w-4xl mx-auto">
    <!-- Progress Indicator -->
    <div class="p-6 border-b">
        <flux:heading size="xl" class="mb-4">Create New Album</flux:heading>

        <div class="flex items-center space-x-4">
            @for($i = 1; $i <= $totalSteps; $i++)
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full
                        {{ $i <= $currentStep ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                        {{ $i }}
                    </div>
                    @if($i < $totalSteps)
                        <div class="w-12 h-1 mx-2
                            {{ $i < $currentStep ? 'bg-blue-600' : 'bg-gray-200' }}">
                        </div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <form wire:submit="submit">
        <div class="p-6">
            <!-- Step 1: Basic Information -->
            @if($currentStep === 1)
                <div class="space-y-6">
                    <flux:heading size="lg">Basic Information</flux:heading>

                    <flux:field>
                        <flux:label>Album Title</flux:label>
                        <flux:input
                            wire:model="form.title"
                            placeholder="Enter album title..."
                            required
                        />
                        <flux:error name="form.title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Artist</flux:label>
                        <flux:select
                            wire:model="form.artist_id"
                            placeholder="Select an artist..."
                            required
                        >
                            @foreach($artists as $artist)
                                <flux:option value="{{ $artist->id }}">{{ $artist->name }}</flux:option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.artist_id" />
                    </flux:field>
                </div>
            @endif

            <!-- Step 2: Release Details -->
            @if($currentStep === 2)
                <div class="space-y-6">
                    <flux:heading size="lg">Release Details</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <flux:field>
                            <flux:label>Release Date</flux:label>
                            <flux:date-picker wire:model="form.release_date" />
                            <flux:error name="form.release_date" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Record Label</flux:label>
                            <flux:input wire:model="form.label" />
                            <flux:error name="form.label" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Catalog Number</flux:label>
                        <flux:input wire:model="form.catalog_number" />
                        <flux:error name="form.catalog_number" />
                    </flux:field>
                </div>
            @endif

            <!-- Step 3: Media and Description -->
            @if($currentStep === 3)
                <div class="space-y-6">
                    <flux:heading size="lg">Media and Description</flux:heading>

                    <flux:field>
                        <flux:label>Cover Image</flux:label>
                        <flux:input
                            type="file"
                            wire:model="form.cover_image"
                            accept="image/*"
                        />
                        <flux:error name="form.cover_image" />
                        <flux:description>Upload album cover art (max 2MB)</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Description</flux:label>
                        <flux:textarea
                            wire:model="form.description"
                            rows="4"
                            placeholder="Describe the album..."
                        />
                        <flux:error name="form.description" />
                    </flux:field>
                </div>
            @endif

            <!-- Step 4: Track Listing -->
            @if($currentStep === 4)
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <flux:heading size="lg">Track Listing</flux:heading>
                        <flux:button
                            wire:click="addTrack"
                            variant="ghost"
                            icon="plus"
                            type="button"
                        >
                            Add Track
                        </flux:button>
                    </div>

                    <div class="space-y-4">
                        @foreach($form->tracks ?? [] as $index => $track)
                            <div class="flex items-end space-x-4 p-4 border rounded-lg">
                                <div class="flex-1">
                                    <flux:field>
                                        <flux:label>Track {{ $index + 1 }} Name</flux:label>
                                        <flux:input
                                            wire:model="form.tracks.{{ $index }}.name"
                                            placeholder="Track name..."
                                        />
                                        <flux:error name="form.tracks.{{ $index }}.name" />
                                    </flux:field>
                                </div>

                                <div class="w-32">
                                    <flux:field>
                                        <flux:label>Duration (seconds)</flux:label>
                                        <flux:input
                                            type="number"
                                            wire:model="form.tracks.{{ $index }}.duration"
                                            min="1"
                                        />
                                        <flux:error name="form.tracks.{{ $index }}.duration" />
                                    </flux:field>
                                </div>

                                <flux:button
                                    wire:click="removeTrack({{ $index }})"
                                    variant="danger"
                                    size="sm"
                                    icon="trash"
                                    type="button"
                                />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Form Navigation -->
        <div class="flex justify-between items-center p-6 border-t bg-gray-50">
            <flux:button
                wire:click="previousStep"
                variant="ghost"
                :disabled="$currentStep === 1"
                type="button"
            >
                Previous
            </flux:button>

            <div class="flex space-x-2">
                @if($currentStep < $totalSteps)
                    <flux:button
                        wire:click="nextStep"
                        variant="primary"
                        type="button"
                    >
                        Next Step
                    </flux:button>
                @else
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Create Album</span>
                        <span wire:loading>Creating...</span>
                    </flux:button>
                @endif
            </div>
        </div>
    </form>
</flux:card>
```

## Data Display Components

### Advanced Data Visualization

#### Artist Profile with Rich Media

```php
<?php
// Comprehensive artist profile display
use function Livewire\Volt\{state, computed, mount};
use App\Models\Artist;

state(['artist' => null, 'activeSection' => 'overview']);

mount(function (Artist $artist) {
    $this->artist = $artist->load([
        'albums.tracks',
        'categories',
        'media'
    ]);
});

$albumStats = computed(function () {
    return [
        'total_albums' => $this->artist->albums->count(),
        'total_tracks' => $this->artist->albums->sum(fn($album) => $album->tracks->count()),
        'total_duration' => $this->artist->albums->sum('total_duration_ms'),
        'latest_release' => $this->artist->albums->sortByDesc('release_date')->first()
    ];
});
?>

<div class="max-w-6xl mx-auto space-y-8">
    <!-- Artist Header -->
    <flux:card class="p-8">
        <div class="flex items-start space-x-8">
            <flux:avatar
                :src="$artist->getFirstMediaUrl('avatar')"
                :alt="$artist->name"
                size="2xl"
                class="flex-shrink-0"
            />

            <div class="flex-1">
                <div class="flex justify-between items-start">
                    <div>
                        <flux:heading size="2xl" class="mb-2">{{ $artist->name }}</flux:heading>
                        <flux:text variant="muted" size="lg">{{ $artist->country }}</flux:text>

                        <div class="flex items-center space-x-4 mt-4">
                            <flux:badge
                                :variant="$artist->is_active ? 'success' : 'warning'"
                                size="lg"
                            >
                                {{ $artist->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>

                            @if($artist->formed_year)
                                <flux:text variant="muted">
                                    Formed {{ $artist->formed_year }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="flex space-x-2">
                        <flux:button
                            wire:click="playArtist"
                            variant="primary"
                            icon="play"
                        >
                            Play All
                        </flux:button>

                        <flux:button
                            wire:click="followArtist"
                            variant="ghost"
                            icon="heart"
                        >
                            Follow
                        </flux:button>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-4 gap-6 mt-6 pt-6 border-t">
                    <div class="text-center">
                        <flux:heading size="lg">{{ $this->albumStats['total_albums'] }}</flux:heading>
                        <flux:text variant="muted">Albums</flux:text>
                    </div>
                    <div class="text-center">
                        <flux:heading size="lg">{{ $this->albumStats['total_tracks'] }}</flux:heading>
                        <flux:text variant="muted">Tracks</flux:text>
                    </div>
                    <div class="text-center">
                        <flux:heading size="lg">
                            {{ gmdate('H:i:s', $this->albumStats['total_duration'] / 1000) }}
                        </flux:heading>
                        <flux:text variant="muted">Total Duration</flux:text>
                    </div>
                    <div class="text-center">
                        <flux:heading size="lg">
                            {{ $this->albumStats['latest_release']?->release_date->format('Y') ?? 'N/A' }}
                        </flux:heading>
                        <flux:text variant="muted">Latest Release</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Content Sections -->
    <flux:tabs wire:model="activeSection">
        <flux:tab name="overview" icon="information-circle">Overview</flux:tab>
        <flux:tab name="discography" icon="musical-note">Discography</flux:tab>
        <flux:tab name="media" icon="photo">Media</flux:tab>
        <flux:tab name="analytics" icon="chart-bar">Analytics</flux:tab>
    </flux:tabs>

    <!-- Tab Content -->
    @if($activeSection === 'overview')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Biography -->
            <div class="lg:col-span-2">
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">Biography</flux:heading>
                    <flux:text class="leading-relaxed">{{ $artist->biography }}</flux:text>
                </flux:card>
            </div>

            <!-- Sidebar Info -->
            <div class="space-y-6">
                <!-- Genres -->
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">Genres</flux:heading>
                    <div class="flex flex-wrap gap-2">
                        @foreach($artist->categories->where('type', 'genre') as $genre)
                            <flux:badge variant="subtle">{{ $genre->name }}</flux:badge>
                        @endforeach
                    </div>
                </flux:card>

                <!-- External Links -->
                @if($artist->website || $artist->social_links)
                    <flux:card class="p-6">
                        <flux:heading size="lg" class="mb-4">Links</flux:heading>
                        <div class="space-y-3">
                            @if($artist->website)
                                <flux:button
                                    href="{{ $artist->website }}"
                                    target="_blank"
                                    variant="ghost"
                                    icon="globe-alt"
                                    class="w-full justify-start"
                                >
                                    Official Website
                                </flux:button>
                            @endif

                            @if($artist->social_links)
                                @foreach($artist->social_links as $platform => $url)
                                    <flux:button
                                        href="{{ $url }}"
                                        target="_blank"
                                        variant="ghost"
                                        class="w-full justify-start"
                                    >
                                        {{ ucfirst($platform) }}
                                    </flux:button>
                                @endforeach
                            @endif
                        </div>
                    </flux:card>
                @endif
            </div>
        </div>
    @endif

    @if($activeSection === 'discography')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($artist->albums as $album)
                <flux:card class="overflow-hidden">
                    @if($album->getFirstMediaUrl('cover'))
                        <img
                            src="{{ $album->getFirstMediaUrl('cover') }}"
                            alt="{{ $album->title }}"
                            class="w-full h-48 object-cover"
                        />
                    @endif

                    <div class="p-4">
                        <flux:heading size="lg" class="mb-2">{{ $album->title }}</flux:heading>
                        <flux:text variant="muted">{{ $album->release_date->format('Y') }}</flux:text>

                        <div class="flex justify-between items-center mt-4">
                            <flux:badge>{{ $album->tracks->count() }} tracks</flux:badge>

                            <flux:button
                                wire:navigate
                                href="{{ route('albums.show', $album->slug) }}"
                                variant="ghost"
                                size="sm"
                            >
                                View Album
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
```

## Navigation Components

### Responsive Navigation System

```php
<?php
// Main application navigation
use function Livewire\Volt\{state, computed};

state(['isMobileMenuOpen' => false, 'currentSection' => 'dashboard']);

$navigationItems = computed(function () {
    return [
        [
            'name' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'home',
            'current' => request()->routeIs('dashboard')
        ],
        [
            'name' => 'Artists',
            'route' => 'artists.index',
            'icon' => 'user-group',
            'current' => request()->routeIs('artists.*')
        ],
        [
            'name' => 'Albums',
            'route' => 'albums.index',
            'icon' => 'musical-note',
            'current' => request()->routeIs('albums.*')
        ],
        [
            'name' => 'Tracks',
            'route' => 'tracks.index',
            'icon' => 'play',
            'current' => request()->routeIs('tracks.*')
        ],
        [
            'name' => 'Playlists',
            'route' => 'playlists.index',
            'icon' => 'queue-list',
            'current' => request()->routeIs('playlists.*')
        ]
    ];
});

$toggleMobileMenu = fn() => $this->isMobileMenuOpen = !$this->isMobileMenuOpen;
?>

<flux:navbar class="border-b">
    <!-- Brand -->
    <flux:navbar.brand>
        <flux:brand href="{{ route('dashboard') }}" wire:navigate>
            <x-logo class="h-8 w-auto" />
            Chinook Music
        </flux:brand>
    </flux:navbar.brand>

    <!-- Desktop Navigation -->
    <flux:navbar.items class="hidden md:flex">
        @foreach($this->navigationItems as $item)
            <flux:navbar.item
                wire:navigate
                href="{{ route($item['route']) }}"
                :current="$item['current']"
                icon="{{ $item['icon'] }}"
            >
                {{ $item['name'] }}
            </flux:navbar.item>
        @endforeach
    </flux:navbar.items>

    <!-- Search -->
    <div class="flex-1 max-w-lg mx-8">
        <flux:input
            placeholder="Search artists, albums, tracks..."
            icon="magnifying-glass"
            wire:model.live.debounce.300ms="globalSearch"
        />
    </div>

    <!-- User Menu -->
    <flux:dropdown>
        <flux:profile
            avatar="{{ auth()->user()->getFirstMediaUrl('avatar') }}"
            name="{{ auth()->user()->name }}"
        />

        <flux:menu>
            <flux:menu.item
                wire:navigate
                href="{{ route('profile.edit') }}"
                icon="user"
            >
                Profile
            </flux:menu.item>

            <flux:menu.item
                wire:navigate
                href="{{ route('settings') }}"
                icon="cog-6-tooth"
            >
                Settings
            </flux:menu.item>

            <flux:menu.separator />

            <flux:menu.item
                wire:click="logout"
                icon="arrow-right-on-rectangle"
            >
                Sign Out
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>

    <!-- Mobile Menu Button -->
    <flux:button
        wire:click="toggleMobileMenu"
        variant="ghost"
        icon="bars-3"
        class="md:hidden"
    />
</flux:navbar>

<!-- Mobile Menu -->
@if($isMobileMenuOpen)
    <div class="md:hidden border-b bg-white">
        <flux:navlist>
            @foreach($this->navigationItems as $item)
                <flux:navlist.item
                    wire:navigate
                    href="{{ route($item['route']) }}"
                    :current="$item['current']"
                    icon="{{ $item['icon'] }}"
                >
                    {{ $item['name'] }}
                </flux:navlist.item>
            @endforeach
        </flux:navlist>
    </div>
@endif
```

## Layout Components

### Application Shell

```php
<?php
// Main application layout with Flux components
use function Livewire\Volt\{state};

state(['sidebarOpen' => true, 'notifications' => []]);

$toggleSidebar = fn() => $this->sidebarOpen = !$this->sidebarOpen;
?>

<div class="min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <flux:sidebar :open="$sidebarOpen" class="w-64">
        <flux:sidebar.header>
            <flux:brand>
                <x-logo class="h-8 w-auto" />
                Chinook Music
            </flux:brand>
        </flux:sidebar.header>

        <flux:navlist>
            <flux:navlist.group heading="Music">
                <flux:navlist.item href="{{ route('dashboard') }}" icon="home">
                    Dashboard
                </flux:navlist.item>
                <flux:navlist.item href="{{ route('artists.index') }}" icon="user-group">
                    Artists
                </flux:navlist.item>
                <flux:navlist.item href="{{ route('albums.index') }}" icon="musical-note">
                    Albums
                </flux:navlist.item>
                <flux:navlist.item href="{{ route('tracks.index') }}" icon="play">
                    Tracks
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Management">
                <flux:navlist.item href="{{ route('customers.index') }}" icon="users">
                    Customers
                </flux:navlist.item>
                <flux:navlist.item href="{{ route('invoices.index') }}" icon="document-text">
                    Sales
                </flux:navlist.item>
                <flux:navlist.item href="{{ route('playlists.index') }}" icon="queue-list">
                    Playlists
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>
    </flux:sidebar>

    <!-- Main Content -->
    <div class="{{ $sidebarOpen ? 'ml-64' : 'ml-0' }} transition-all duration-300">
        <!-- Header -->
        <flux:header class="border-b bg-white">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center space-x-4">
                    <flux:button
                        wire:click="toggleSidebar"
                        variant="ghost"
                        icon="bars-3"
                    />

                    <flux:breadcrumbs>
                        <flux:breadcrumbs.item href="{{ route('dashboard') }}">
                            Home
                        </flux:breadcrumbs.item>
                        @yield('breadcrumbs')
                    </flux:breadcrumbs>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <flux:dropdown>
                        <flux:button variant="ghost" icon="bell">
                            @if(count($notifications) > 0)
                                <flux:badge variant="danger" size="sm" class="absolute -top-1 -right-1">
                                    {{ count($notifications) }}
                                </flux:badge>
                            @endif
                        </flux:button>

                        <flux:menu>
                            @forelse($notifications as $notification)
                                <flux:menu.item>{{ $notification['message'] }}</flux:menu.item>
                            @empty
                                <flux:menu.item disabled>No notifications</flux:menu.item>
                            @endforelse
                        </flux:menu>
                    </flux:dropdown>

                    <!-- User Menu -->
                    <flux:profile
                        avatar="{{ auth()->user()->getFirstMediaUrl('avatar') }}"
                        name="{{ auth()->user()->name }}"
                    />
                </div>
            </div>
        </flux:header>

        <!-- Page Content -->
        <main class="p-6">
            {{ $slot }}
        </main>
    </div>
</div>
```

## Interactive Components

### Real-time Music Player

```php
<?php
// Global music player component
use function Livewire\Volt\{state, on};

state([
    'currentTrack' => null,
    'isPlaying' => false,
    'volume' => 0.8,
    'position' => 0,
    'duration' => 0,
    'isShuffled' => false,
    'repeatMode' => 'none' // none, one, all
]);

on([
    'play-track' => function ($trackId) {
        $this->currentTrack = \App\Models\Track::find($trackId);
        $this->isPlaying = true;
        $this->position = 0;
    },

    'pause-track' => fn() => $this->isPlaying = false,
    'resume-track' => fn() => $this->isPlaying = true
]);

$togglePlay = function () {
    $this->isPlaying = !$this->isPlaying;
    $this->dispatch($this->isPlaying ? 'resume-track' : 'pause-track');
};

$nextTrack = function () {
    // Logic to play next track
    $this->dispatch('next-track');
};

$previousTrack = function () {
    // Logic to play previous track
    $this->dispatch('previous-track');
};

$toggleShuffle = fn() => $this->isShuffled = !$this->isShuffled;

$cycleRepeat = function () {
    $this->repeatMode = match($this->repeatMode) {
        'none' => 'one',
        'one' => 'all',
        'all' => 'none'
    };
};
?>

@if($currentTrack)
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg z-50">
        <div class="flex items-center justify-between p-4">
            <!-- Track Info -->
            <div class="flex items-center space-x-4 flex-1">
                @if($currentTrack->album->getFirstMediaUrl('cover'))
                    <img
                        src="{{ $currentTrack->album->getFirstMediaUrl('cover') }}"
                        alt="{{ $currentTrack->album->title }}"
                        class="w-12 h-12 rounded"
                    />
                @endif

                <div class="min-w-0">
                    <flux:heading size="sm" class="truncate">
                        {{ $currentTrack->name }}
                    </flux:heading>
                    <flux:text variant="muted" class="truncate">
                        {{ $currentTrack->album->artist->name }}
                    </flux:text>
                </div>
            </div>

            <!-- Player Controls -->
            <div class="flex items-center space-x-4">
                <flux:button
                    wire:click="toggleShuffle"
                    :variant="$isShuffled ? 'primary' : 'ghost'"
                    icon="arrow-path-rounded-square"
                    size="sm"
                />

                <flux:button
                    wire:click="previousTrack"
                    variant="ghost"
                    icon="backward"
                    size="sm"
                />

                <flux:button
                    wire:click="togglePlay"
                    variant="primary"
                    :icon="$isPlaying ? 'pause' : 'play'"
                />

                <flux:button
                    wire:click="nextTrack"
                    variant="ghost"
                    icon="forward"
                    size="sm"
                />

                <flux:button
                    wire:click="cycleRepeat"
                    :variant="$repeatMode !== 'none' ? 'primary' : 'ghost'"
                    icon="arrow-path"
                    size="sm"
                />
            </div>

            <!-- Volume Control -->
            <div class="flex items-center space-x-2 flex-1 justify-end">
                <flux:icon.speaker-wave class="w-4 h-4" />
                <input
                    type="range"
                    min="0"
                    max="1"
                    step="0.1"
                    wire:model.live="volume"
                    class="w-24"
                />
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="px-4 pb-2">
            <input
                type="range"
                min="0"
                :max="$duration"
                wire:model.live="position"
                class="w-full h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            />
        </div>
    </div>
@endif
```

## Advanced Patterns

### Component Communication

```php
<?php
// Parent-child component communication
use function Livewire\Volt\{state, on};

state(['selectedItems' => [], 'bulkAction' => null]);

// Listen for child component events
on([
    'item-selected' => function ($itemId, $selected) {
        if ($selected) {
            $this->selectedItems[] = $itemId;
        } else {
            $this->selectedItems = array_filter(
                $this->selectedItems,
                fn($id) => $id !== $itemId
            );
        }
    },

    'bulk-action-completed' => function ($action, $count) {
        $this->selectedItems = [];
        $this->dispatch('toast',
            message: "{$action} completed for {$count} items",
            type: 'success'
        );
    }
]);

$executeBulkAction = function () {
    if ($this->bulkAction && count($this->selectedItems) > 0) {
        $this->dispatch('execute-bulk-action',
            action: $this->bulkAction,
            items: $this->selectedItems
        );
    }
};
?>
```

## Accessibility Integration

### WCAG 2.1 AA Compliance

```php
<?php
// Accessible form component
use function Livewire\Volt\{state, rules};

state(['formData' => []]);

rules([
    'formData.name' => 'required|min:3',
    'formData.email' => 'required|email'
]);
?>

<form wire:submit="submit" role="form" aria-labelledby="form-title">
    <flux:heading id="form-title" size="lg">Contact Information</flux:heading>

    <flux:field>
        <flux:label for="name">
            Full Name
            <span aria-label="required" class="text-red-500">*</span>
        </flux:label>
        <flux:input
            id="name"
            wire:model="formData.name"
            aria-describedby="name-error name-help"
            aria-required="true"
            :aria-invalid="$errors->has('formData.name') ? 'true' : 'false'"
        />
        <flux:description id="name-help">
            Enter your full legal name
        </flux:description>
        <flux:error id="name-error" name="formData.name" />
    </flux:field>

    <flux:button
        type="submit"
        aria-describedby="submit-help"
    >
        Submit Form
    </flux:button>
    <flux:description id="submit-help">
        Press Enter or click to submit the form
    </flux:description>
</form>
```

## Best Practices

### Performance Optimization

1. **Lazy Loading**: Use computed properties for expensive operations
2. **Debouncing**: Implement debouncing for search inputs
3. **Caching**: Cache frequently accessed data
4. **Pagination**: Implement efficient pagination for large datasets

### Accessibility Guidelines

1. **Semantic HTML**: Use proper heading hierarchy and landmarks
2. **ARIA Labels**: Provide descriptive labels for interactive elements
3. **Keyboard Navigation**: Ensure all functionality is keyboard accessible
4. **Color Contrast**: Maintain minimum 4.5:1 contrast ratios

### Code Organization

1. **Component Composition**: Build complex UIs through composition
2. **Consistent Naming**: Follow Laravel and Flux conventions
3. **Error Handling**: Implement comprehensive error handling
4. **Documentation**: Add inline comments for complex logic

## Navigation

**← Previous** [Volt Functional Component Patterns Guide](110-volt-functional-patterns-guide.md)
**Next →** [SPA Navigation Implementation Guide](130-spa-navigation-guide.md)

---

*This guide demonstrates comprehensive integration patterns for Flux and Flux-Pro components with Livewire/Volt. Continue with the SPA navigation guide for seamless page transitions.*
