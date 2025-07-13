# 1. Accessibility and WCAG Compliance Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. WCAG 2.1 AA Requirements](#2-wcag-21-aa-requirements)
- [3. Semantic HTML Structure](#3-semantic-html-structure)
- [4. Keyboard Navigation](#4-keyboard-navigation)
- [5. Screen Reader Support](#5-screen-reader-support)
- [6. Color and Contrast](#6-color-and-contrast)
- [7. Form Accessibility](#7-form-accessibility)
- [8. Testing Strategies](#8-testing-strategies)
- [9. Best Practices](#9-best-practices)
- [10. Navigation](#10-navigation)

## 1. Overview

This guide provides comprehensive strategies for implementing WCAG 2.1 AA accessibility compliance in the Chinook application. Accessibility is built into every component and interaction, ensuring the application is usable by everyone, including users with disabilities.

## 2. WCAG 2.1 AA Requirements

### 2.1 Core Principles

The Web Content Accessibility Guidelines (WCAG) 2.1 are organized around four main principles:

1. **Perceivable**: Information must be presentable in ways users can perceive
2. **Operable**: Interface components must be operable by all users
3. **Understandable**: Information and UI operation must be understandable
4. **Robust**: Content must be robust enough for various assistive technologies

### 2.2 Key Success Criteria

#### 2.2.1 Level A Requirements
- Text alternatives for images
- Captions for videos
- Keyboard accessibility
- No seizure-inducing content

#### 2.2.2 Level AA Requirements (Our Target)
- Color contrast ratio of at least 4.5:1
- Text can be resized up to 200%
- Focus indicators are visible
- Headings and labels are descriptive

## 3. Semantic HTML Structure

### 3.1 Proper Document Structure

```php
<?php
// Accessible page layout
use function Livewire\Volt\{state, computed};

state(['currentPage' => 'artists']);

$pageTitle = computed(function () {
    return match($this->currentPage) {
        'artists' => 'Artists - Chinook Music',
        'albums' => 'Albums - Chinook Music',
        'tracks' => 'Tracks - Chinook Music',
        default => 'Chinook Music'
    };
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $this->pageTitle }}</title>
</head>
<body>
    <!-- Skip to main content link -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 
                                   bg-blue-600 text-white px-4 py-2 rounded z-50">
        Skip to main content
    </a>
    
    <!-- Main navigation landmark -->
    <nav role="navigation" aria-label="Main navigation">
        <flux:navbar>
            <flux:navbar.brand>
                <flux:brand href="{{ route('dashboard') }}">
                    <img src="/logo.svg" alt="Chinook Music Logo" class="h-8 w-auto">
                    Chinook Music
                </flux:brand>
            </flux:navbar.brand>
            
            <flux:navbar.items>
                <flux:navbar.item 
                    href="{{ route('artists.index') }}"
                    aria-current="{{ request()->routeIs('artists.*') ? 'page' : 'false' }}"
                >
                    Artists
                </flux:navbar.item>
                
                <flux:navbar.item 
                    href="{{ route('albums.index') }}"
                    aria-current="{{ request()->routeIs('albums.*') ? 'page' : 'false' }}"
                >
                    Albums
                </flux:navbar.item>
                
                <flux:navbar.item 
                    href="{{ route('tracks.index') }}"
                    aria-current="{{ request()->routeIs('tracks.*') ? 'page' : 'false' }}"
                >
                    Tracks
                </flux:navbar.item>
                
                <flux:navbar.item 
                    href="{{ route('browse.taxonomies') }}"
                    aria-current="{{ request()->routeIs('browse.*') ? 'page' : 'false' }}"
                >
                    Browse
                </flux:navbar.item>
            </flux:navbar.items>
        </flux:navbar>
    </nav>
    
    <!-- Main content landmark -->
    <main id="main-content" role="main" aria-label="Main content">
        {{ $slot }}
    </main>
    
    <!-- Footer landmark -->
    <footer role="contentinfo" aria-label="Site footer">
        <div class="bg-gray-800 text-white p-6">
            <div class="max-w-7xl mx-auto">
                <p>&copy; 2024 Chinook Music. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
```

### 3.2 Accessible Artist Card Component

```php
<?php
// Accessible artist card with proper semantics
use function Livewire\Volt\{state, mount};
use App\Models\Artist;

state(['artist' => null]);

mount(function (Artist $artist) {
    $this->artist = $artist->load(['albums', 'taxonomies']);
});

$viewArtist = function () {
    return $this->redirect(route('artists.show', $this->artist), navigate: true);
};
?>

<article 
    class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow"
    role="article"
    aria-labelledby="artist-{{ $artist->id }}-name"
    aria-describedby="artist-{{ $artist->id }}-info"
>
    <!-- Artist Image -->
    <div class="mb-4">
        <img 
            src="{{ $artist->getFirstMediaUrl('avatar') ?: '/default-artist.jpg' }}"
            alt="{{ $artist->name }} profile picture"
            class="w-full h-48 object-cover rounded-lg"
            loading="lazy"
        />
    </div>
    
    <!-- Artist Information -->
    <div class="space-y-3">
        <div>
            <h2 id="artist-{{ $artist->id }}-name" class="text-xl font-semibold">
                <button 
                    wire:click="viewArtist"
                    class="text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    aria-describedby="artist-{{ $artist->id }}-info"
                >
                    {{ $artist->name }}
                </button>
            </h2>
            
            <div id="artist-{{ $artist->id }}-info" class="text-gray-600">
                <p>{{ $artist->albums->count() }} albums</p>
                @if($artist->biography)
                    <p class="mt-2 text-sm line-clamp-3">{{ $artist->biography }}</p>
                @endif
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-medium mb-2">Taxonomies</h3>
            <ul role="list" aria-label="Artist taxonomies">
                @foreach($artist->taxonomies as $taxonomy)
                    <li>
                        <flux:badge>{{ $taxonomy->name }}</flux:badge>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</article>
```

## 4. Keyboard Navigation

### 4.1 Focus Management

```php
<?php
// Modal with proper focus management
use function Livewire\Volt\{state};

state(['showModal' => false, 'previousFocus' => null]);

$openModal = function () {
    $this->showModal = true;
    $this->dispatch('modal-opened');
};

$closeModal = function () {
    $this->showModal = false;
    $this->dispatch('modal-closed');
};
?>

<div>
    <flux:button 
        wire:click="openModal"
        id="open-modal-button"
        aria-haspopup="dialog"
    >
        Open Artist Details
    </flux:button>
    
    @if($showModal)
        <div 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
            aria-describedby="modal-description"
            x-data="{ 
                init() {
                    this.previousFocus = document.activeElement;
                    this.$nextTick(() => {
                        this.$refs.modalTitle.focus();
                    });
                }
            }"
            x-on:keydown.escape="$wire.closeModal()"
        >
            <div 
                class="bg-white rounded-lg p-6 max-w-md w-full mx-4"
                x-on:click.stop
            >
                <h2 
                    id="modal-title" 
                    class="text-xl font-semibold mb-4"
                    tabindex="-1"
                    x-ref="modalTitle"
                >
                    Artist Information
                </h2>
                
                <div id="modal-description" class="mb-6">
                    <p>Detailed information about the selected artist.</p>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <flux:button 
                        wire:click="closeModal"
                        variant="ghost"
                        x-on:click="previousFocus?.focus()"
                    >
                        Cancel
                    </flux:button>
                    <flux:button variant="primary">
                        Save
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('livewire:init', () => {
    Livewire.on('modal-opened', () => {
        // Trap focus within modal
        const modal = document.querySelector('[role="dialog"]');
        const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement.focus();
                        e.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement.focus();
                        e.preventDefault();
                    }
                }
            }
        });
    });
});
</script>
```

### 4.2 Keyboard Shortcuts

```javascript
// resources/js/keyboard-shortcuts.js
document.addEventListener('livewire:init', () => {
    // Global keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        // Skip if user is typing in an input
        if (e.target.matches('input, textarea, select')) return;

        // Alt + key combinations for navigation
        if (e.altKey) {
            switch (e.key) {
                case 'h':
                    e.preventDefault();
                    window.location.href = '/';
                    break;
                case 'a':
                    e.preventDefault();
                    window.location.href = '/artists';
                    break;
                case 's':
                    e.preventDefault();
                    document.querySelector('[aria-label="Search"]')?.focus();
                    break;
            }
        }

        // Escape key to close modals/dropdowns
        if (e.key === 'Escape') {
            Livewire.dispatch('close-all-modals');
        }
    });
});
```

## 5. Screen Reader Support

### 5.1 ARIA Labels and Descriptions

```php
<?php
// Music player with comprehensive ARIA support
use function Livewire\Volt\{state, computed};

state([
    'isPlaying' => false,
    'currentTrack' => null,
    'volume' => 75,
    'currentTime' => 0,
    'duration' => 0
]);

$togglePlay = fn() => $this->isPlaying = !$this->isPlaying;

$formatTime = function ($seconds) {
    return gmdate('i:s', $seconds);
};
?>

<div
    class="bg-white border-t shadow-lg p-4"
    role="region"
    aria-label="Music player"
    aria-live="polite"
>
    @if($currentTrack)
        <!-- Track Information -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-4">
                <img
                    src="{{ $currentTrack->album->getFirstMediaUrl('cover') }}"
                    alt="Album cover for {{ $currentTrack->album->title }}"
                    class="w-12 h-12 rounded"
                />
                <div>
                    <h3 class="font-medium">{{ $currentTrack->name }}</h3>
                    <p class="text-sm text-gray-600">
                        {{ $currentTrack->artist->name }} • {{ $currentTrack->album->title }}
                    </p>
                </div>
            </div>

            <!-- Playback Controls -->
            <div class="flex items-center space-x-2" role="group" aria-label="Playback controls">
                <flux:button
                    wire:click="previousTrack"
                    variant="ghost"
                    size="sm"
                    aria-label="Previous track"
                >
                    <flux:icon name="backward" />
                </flux:button>

                <flux:button
                    wire:click="togglePlay"
                    variant="primary"
                    aria-label="{{ $isPlaying ? 'Pause' : 'Play' }} {{ $currentTrack->name }}"
                    aria-pressed="{{ $isPlaying ? 'true' : 'false' }}"
                >
                    <flux:icon name="{{ $isPlaying ? 'pause' : 'play' }}" />
                </flux:button>

                <flux:button
                    wire:click="nextTrack"
                    variant="ghost"
                    size="sm"
                    aria-label="Next track"
                >
                    <flux:icon name="forward" />
                </flux:button>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
            <div class="flex items-center space-x-2 text-sm text-gray-600 mb-1">
                <span aria-label="Current time">{{ $this->formatTime($currentTime) }}</span>
                <span aria-hidden="true">/</span>
                <span aria-label="Total duration">{{ $this->formatTime($duration) }}</span>
            </div>

            <div
                class="relative"
                role="slider"
                aria-label="Track progress"
                aria-valuemin="0"
                aria-valuemax="{{ $duration }}"
                aria-valuenow="{{ $currentTime }}"
                aria-valuetext="{{ $this->formatTime($currentTime) }} of {{ $this->formatTime($duration) }}"
                tabindex="0"
            >
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div
                        class="bg-blue-600 h-2 rounded-full transition-all"
                        style="width: {{ $duration > 0 ? ($currentTime / $duration) * 100 : 0 }}%"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Volume Control -->
        <div class="flex items-center space-x-2">
            <flux:icon name="speaker-wave" class="text-gray-600" />
            <div
                class="flex-1"
                role="slider"
                aria-label="Volume"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $volume }}"
                aria-valuetext="{{ $volume }} percent"
                tabindex="0"
            >
                <input
                    type="range"
                    wire:model.live="volume"
                    min="0"
                    max="100"
                    class="w-full"
                    aria-label="Volume control"
                />
            </div>
            <span class="text-sm text-gray-600 w-8">{{ $volume }}%</span>
        </div>
    @else
        <div class="text-center text-gray-500 py-8">
            <p>No track selected</p>
        </div>
    @endif
</div>
```

### 5.2 Live Regions for Dynamic Content

```php
<?php
// Search results with live announcements
use function Livewire\Volt\{state, computed};

state(['query' => '', 'isSearching' => false]);

$results = computed(function () {
    if (!$this->query) return collect();

    $this->isSearching = true;

    $results = collect();
    $results = $results->merge(\App\Models\Artist::search($this->query)->take(5)->get());
    $results = $results->merge(\App\Models\Album::search($this->query)->take(5)->get());
    $results = $results->merge(\App\Models\Track::search($this->query)->take(5)->get());

    $this->isSearching = false;

    return $results;
});

$announceResults = computed(function () {
    $count = $this->results->count();
    if ($count === 0) {
        return "No results found for '{$this->query}'";
    }
    return "{$count} results found for '{$this->query}'";
});
?>

<div class="relative">
    <!-- Search Input -->
    <flux:input
        wire:model.live.debounce.300ms="query"
        placeholder="Search artists, albums, tracks..."
        aria-label="Search"
        aria-describedby="search-help search-results-status"
        role="searchbox"
        aria-expanded="{{ !empty($this->query) ? 'true' : 'false' }}"
        aria-owns="search-results"
    />

    <div id="search-help" class="sr-only">
        Type to search for artists, albums, or tracks. Results will appear below.
    </div>

    <!-- Live region for search status -->
    <div
        id="search-results-status"
        aria-live="polite"
        aria-atomic="true"
        class="sr-only"
    >
        @if($isSearching)
            Searching...
        @elseif($query)
            {{ $this->announceResults }}
        @endif
    </div>

    <!-- Search Results -->
    @if($query)
        <div
            id="search-results"
            class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-b-lg shadow-lg z-10 max-h-96 overflow-y-auto"
            role="listbox"
            aria-label="Search results"
        >
            @if($this->results->count() > 0)
                @foreach($this->results as $result)
                    <div
                        class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                        role="option"
                        tabindex="0"
                        aria-label="{{ class_basename($result) }}: {{ $result->name ?? $result->title }}"
                    >
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <flux:badge variant="subtle">
                                    {{ class_basename($result) }}
                                </flux:badge>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">
                                    {{ $result->name ?? $result->title }}
                                </p>
                                @if($result instanceof \App\Models\Track)
                                    <p class="text-sm text-gray-600 truncate">
                                        {{ $result->artist->name }} • {{ $result->album->title }}
                                    </p>
                                @elseif($result instanceof \App\Models\Album)
                                    <p class="text-sm text-gray-600 truncate">
                                        {{ $result->artist->name }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="p-4 text-center text-gray-500">
                    <p>No results found for "{{ $query }}"</p>
                    <p class="text-sm mt-1">Try different keywords or check your spelling</p>
                </div>
            @endif
        </div>
    @endif
</div>
```

## 6. Color and Contrast

### 6.1 WCAG Compliant Color Palette

```css
/* resources/css/accessibility.css */
:root {
    /* Primary Colors - 4.5:1 contrast ratio minimum */
    --color-primary-50: #eff6ff;
    --color-primary-500: #3b82f6;  /* 4.5:1 on white */
    --color-primary-600: #2563eb;  /* 7:1 on white */
    --color-primary-700: #1d4ed8;  /* 10:1 on white */

    /* Success Colors */
    --color-success-500: #10b981;  /* 4.5:1 on white */
    --color-success-600: #059669;  /* 7:1 on white */

    /* Warning Colors */
    --color-warning-500: #f59e0b;  /* 4.5:1 on white */
    --color-warning-600: #d97706;  /* 7:1 on white */

    /* Error Colors */
    --color-error-500: #ef4444;    /* 4.5:1 on white */
    --color-error-600: #dc2626;    /* 7:1 on white */

    /* Text Colors */
    --color-text-primary: #111827;    /* 16:1 on white */
    --color-text-secondary: #6b7280;  /* 4.5:1 on white */
    --color-text-muted: #9ca3af;      /* 3:1 on white - use carefully */
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    :root {
        --color-primary-500: #1d4ed8;
        --color-text-secondary: #374151;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

### 6.2 Focus Indicators

```css
/* Focus styles that meet WCAG requirements */
.focus-visible {
    outline: 2px solid var(--color-primary-600);
    outline-offset: 2px;
    border-radius: 4px;
}

/* Custom focus styles for different components */
.btn:focus-visible {
    outline: 2px solid var(--color-primary-600);
    outline-offset: 2px;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.input:focus-visible {
    border-color: var(--color-primary-600);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

/* Skip link styles */
.skip-link {
    position: absolute;
    top: -40px;
    left: 6px;
    background: var(--color-primary-600);
    color: white;
    padding: 8px;
    text-decoration: none;
    border-radius: 4px;
    z-index: 1000;
}

.skip-link:focus {
    top: 6px;
}
```

## 7. Form Accessibility

### 7.1 Accessible Form Components

```php
<?php
// Accessible artist creation form
use function Livewire\Volt\{state, form, rules};
use App\Livewire\Forms\ArtistForm;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

form(ArtistForm::class);

state(['selectedTaxonomies' => []]);

rules(['selectedTaxonomies' => 'array|max:10']);

$availableTaxonomies = computed(function () {
    return Taxonomy::whereIn('type', ['genre', 'style', 'mood'])
        ->orderBy('type')
        ->orderBy('name')
        ->get()
        ->groupBy('type');
});

$save = function () {
    $this->validate();
    $this->form->validate();

    $artist = $this->form->store();

    if (!empty($this->selectedTaxonomies)) {
        $artist->syncTaxonomies($this->selectedTaxonomies);
    }

    session()->flash('message', 'Artist created successfully');
    return $this->redirect(route('artists.show', $artist), navigate: true);
};
?>

<form wire:submit="save" novalidate>
    <fieldset>
        <legend class="text-lg font-medium mb-4">Artist Information</legend>

        <!-- Artist Name -->
        <div class="mb-4">
            <label for="artist-name" class="block text-sm font-medium text-gray-700 mb-1">
                Artist Name
                <span class="text-red-500" aria-label="required">*</span>
            </label>
            <input
                id="artist-name"
                type="text"
                wire:model="form.name"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                aria-required="true"
                aria-invalid="{{ $errors->has('form.name') ? 'true' : 'false' }}"
                aria-describedby="{{ $errors->has('form.name') ? 'artist-name-error' : 'artist-name-help' }}"
            />

            <div id="artist-name-help" class="text-sm text-gray-600 mt-1">
                Enter the full name of the artist or band
            </div>

            @error('form.name')
                <div id="artist-name-error" class="text-sm text-red-600 mt-1" role="alert">
                    <span class="font-medium">Error:</span> {{ $message }}
                </div>
            @enderror
        </div>

        <!-- Biography -->
        <div class="mb-4">
            <label for="artist-biography" class="block text-sm font-medium text-gray-700 mb-1">
                Biography
            </label>
            <textarea
                id="artist-biography"
                wire:model="form.biography"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                aria-describedby="artist-biography-help"
                maxlength="1000"
            ></textarea>

            <div id="artist-biography-help" class="text-sm text-gray-600 mt-1">
                Optional biography or description (maximum 1000 characters)
            </div>
        </div>
    </fieldset>

    <!-- Taxonomy Selection -->
    <fieldset class="mb-6">
        <legend class="text-lg font-medium mb-4">Taxonomies</legend>

        @foreach($this->availableTaxonomies as $type => $taxonomies)
            <div class="mb-4">
                <div class="text-sm font-medium text-gray-700 mb-2">{{ ucfirst($type) }}</div>
                <div
                    role="group"
                    aria-labelledby="taxonomy-{{ $type }}-label"
                    class="grid grid-cols-2 md:grid-cols-3 gap-2"
                >
                    @foreach($taxonomies as $taxonomy)
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="selectedTaxonomies"
                                value="{{ $taxonomy->id }}"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-2"
                                aria-describedby="taxonomy-help"
                            />
                            <span class="text-sm">{{ $taxonomy->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div id="taxonomy-help" class="text-sm text-gray-600">
            Select taxonomies that best describe this artist (maximum 10)
        </div>

        @error('selectedTaxonomies')
            <div class="text-sm text-red-600 mt-1" role="alert">
                <span class="font-medium">Error:</span> {{ $message }}
            </div>
        @enderror
    </fieldset>

    <!-- Form Actions -->
    <div class="flex justify-end space-x-3">
        <button
            type="button"
            onclick="history.back()"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            Cancel
        </button>

        <button
            type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            aria-describedby="submit-help"
        >
            Create Artist
        </button>
    </div>

    <div id="submit-help" class="sr-only">
        Creates a new artist with the provided information
    </div>
</form>
```

## 8. Testing Strategies

### 8.1 Automated Accessibility Testing

```javascript
// tests/accessibility.test.js
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Accessibility Tests', () => {
    test('homepage should not have accessibility violations', async ({ page }) => {
        await page.goto('/');

        const accessibilityScanResults = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
            .analyze();

        expect(accessibilityScanResults.violations).toEqual([]);
    });

    test('artist listing should be keyboard navigable', async ({ page }) => {
        await page.goto('/artists');

        // Test keyboard navigation
        await page.keyboard.press('Tab');
        await expect(page.locator(':focus')).toBeVisible();

        // Test skip link
        await page.keyboard.press('Tab');
        await expect(page.locator('.skip-link:focus')).toBeVisible();
    });

    test('forms should have proper labels and error handling', async ({ page }) => {
        await page.goto('/artists/create');

        // Submit empty form to trigger validation
        await page.click('button[type="submit"]');

        // Check for error messages
        const errorMessages = page.locator('[role="alert"]');
        await expect(errorMessages).toHaveCount(1);

        // Check ARIA attributes
        const nameInput = page.locator('#artist-name');
        await expect(nameInput).toHaveAttribute('aria-invalid', 'true');
        await expect(nameInput).toHaveAttribute('aria-describedby');
    });
});
```

### 8.2 Manual Testing Checklist

```php
<?php
// Accessibility testing component
use function Livewire\Volt\{state};

state(['testResults' => []]);

$runAccessibilityChecks = function () {
    $this->testResults = [
        'keyboard_navigation' => [
            'name' => 'Keyboard Navigation',
            'tests' => [
                'All interactive elements are focusable',
                'Focus indicators are visible',
                'Tab order is logical',
                'No keyboard traps exist'
            ]
        ],
        'screen_reader' => [
            'name' => 'Screen Reader Support',
            'tests' => [
                'All images have alt text',
                'Form labels are properly associated',
                'ARIA landmarks are present',
                'Live regions announce changes'
            ]
        ],
        'color_contrast' => [
            'name' => 'Color and Contrast',
            'tests' => [
                'Text contrast ratio ≥ 4.5:1',
                'Large text contrast ratio ≥ 3:1',
                'Color is not the only indicator',
                'Focus indicators are visible'
            ]
        ]
    ];
};
?>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Accessibility Testing Checklist</h2>

    <button
        wire:click="runAccessibilityChecks"
        class="mb-6 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
    >
        Load Testing Checklist
    </button>

    @if($testResults)
        @foreach($testResults as $category => $data)
            <div class="mb-6">
                <h3 class="text-lg font-medium mb-3">{{ $data['name'] }}</h3>
                <ul class="space-y-2">
                    @foreach($data['tests'] as $test)
                        <li class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                id="test-{{ $loop->parent->index }}-{{ $loop->index }}"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label
                                for="test-{{ $loop->parent->index }}-{{ $loop->index }}"
                                class="text-sm"
                            >
                                {{ $test }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    @endif
</div>
```

## 9. Best Practices

### 9.1 Accessibility Guidelines

1. **Semantic HTML**: Use proper HTML elements for their intended purpose
2. **ARIA Labels**: Provide descriptive labels for all interactive elements
3. **Keyboard Navigation**: Ensure all functionality is keyboard accessible
4. **Color Contrast**: Maintain minimum 4.5:1 contrast ratio for normal text
5. **Focus Management**: Provide clear focus indicators and logical tab order
6. **Error Handling**: Provide clear, descriptive error messages
7. **Live Regions**: Announce dynamic content changes to screen readers

### 9.2 Testing Checklist

- [ ] All images have appropriate alt text
- [ ] Forms have proper labels and error handling
- [ ] Keyboard navigation works throughout the application
- [ ] Color contrast meets WCAG AA standards
- [ ] Focus indicators are visible and clear
- [ ] ARIA landmarks are properly implemented
- [ ] Content is structured with proper headings
- [ ] Interactive elements have appropriate ARIA attributes

## 10. Navigation

**← Previous** [SPA Navigation Implementation Guide](130-spa-navigation-guide.md)
**Next →** [Performance Optimization Guide](150-performance-optimization-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/140-accessibility-wcag-guide.md on 2025-07-11

*This guide provides comprehensive strategies for implementing WCAG 2.1 AA accessibility compliance. Continue with the performance optimization guide for efficient component loading and data handling.*

[⬆️ Back to Top](#1-accessibility-and-wcag-compliance-guide)
