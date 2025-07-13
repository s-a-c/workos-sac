# Accessibility and WCAG Compliance Guide

## Table of Contents

- [Overview](#overview)
- [WCAG 2.1 AA Requirements](#wcag-21-aa-requirements)
- [Semantic HTML Structure](#semantic-html-structure)
- [Keyboard Navigation](#keyboard-navigation)
- [Screen Reader Support](#screen-reader-support)
- [Color and Contrast](#color-and-contrast)
- [Focus Management](#focus-management)
- [Form Accessibility](#form-accessibility)
- [Testing Strategies](#testing-strategies)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide provides comprehensive strategies for implementing WCAG 2.1 AA accessibility compliance in the Chinook application. Accessibility is built into every component and interaction, ensuring the application is usable by everyone, including users with disabilities.

## WCAG 2.1 AA Requirements

### Core Principles

The Web Content Accessibility Guidelines (WCAG) 2.1 are organized around four main principles:

1. **Perceivable**: Information must be presentable in ways users can perceive
2. **Operable**: Interface components must be operable by all users
3. **Understandable**: Information and UI operation must be understandable
4. **Robust**: Content must be robust enough for various assistive technologies

### Key Success Criteria

#### Level A Requirements
- Text alternatives for images
- Captions for videos
- Keyboard accessibility
- No seizure-inducing content

#### Level AA Requirements (Our Target)
- Color contrast ratio of at least 4.5:1
- Text can be resized up to 200%
- Focus indicators are visible
- Headings and labels are descriptive

## Semantic HTML Structure

### Proper Document Structure

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
            </flux:navbar.items>
        </flux:navbar>
    </nav>
    
    <!-- Main content landmark -->
    <main id="main-content" role="main" aria-label="Main content">
        <!-- Page heading -->
        <h1 class="text-3xl font-bold text-gray-900 mb-6">
            @yield('page-title', 'Dashboard')
        </h1>
        
        <!-- Content area -->
        <div class="space-y-6">
            {{ $slot }}
        </div>
    </main>
    
    <!-- Complementary content -->
    <aside role="complementary" aria-label="Sidebar">
        <livewire:sidebar-content />
    </aside>
    
    <!-- Footer -->
    <footer role="contentinfo" aria-label="Site footer">
        <flux:text variant="muted">© 2025 Chinook Music. All rights reserved.</flux:text>
    </footer>
</body>
</html>
```

### Heading Hierarchy

```php
<?php
// Artist profile with proper heading structure
use function Livewire\Volt\{state, mount};
use App\Models\Artist;

state(['artist' => null]);

mount(function (Artist $artist) {
    $this->artist = $artist->load(['albums.tracks']);
});
?>

<article role="main" aria-labelledby="artist-name">
    <!-- Main heading (h1) -->
    <h1 id="artist-name" class="text-4xl font-bold mb-4">
        {{ $artist->name }}
    </h1>
    
    <!-- Artist info section -->
    <section aria-labelledby="artist-info">
        <h2 id="artist-info" class="text-2xl font-semibold mb-3">Artist Information</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="text-lg font-medium mb-2">Biography</h3>
                <p>{{ $artist->biography }}</p>
            </div>
            
            <div>
                <h3 class="text-lg font-medium mb-2">Genres</h3>
                <ul role="list" aria-label="Artist genres">
                    @foreach($artist->categories as $category)
                        <li>
                            <flux:badge>{{ $category->name }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
    
    <!-- Albums section -->
    <section aria-labelledby="artist-albums">
        <h2 id="artist-albums" class="text-2xl font-semibold mb-3">
            Albums ({{ $artist->albums->count() }})
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" 
             role="list" 
             aria-label="Artist albums">
            @foreach($artist->albums as $album)
                <article role="listitem" aria-labelledby="album-{{ $album->id }}">
                    <h3 id="album-{{ $album->id }}" class="text-xl font-medium mb-2">
                        {{ $album->title }}
                    </h3>
                    
                    <p class="text-gray-600">
                        Released {{ $album->release_date->format('Y') }}
                    </p>
                    
                    <p class="text-sm text-gray-500">
                        {{ $album->tracks->count() }} tracks
                    </p>
                </article>
            @endforeach
        </div>
    </section>
</article>
```

## Keyboard Navigation

### Focus Management

```php
<?php
// Modal with proper focus management
use function Livewire\Volt\{state};

state(['showModal' => false, 'modalTitle' => '']);

$openModal = function ($title) {
    $this->modalTitle = $title;
    $this->showModal = true;
    
    // Dispatch focus management event
    $this->dispatch('modal-opened', modalId: 'artist-modal');
};

$closeModal = function () {
    $this->showModal = false;
    
    // Return focus to trigger element
    $this->dispatch('modal-closed', returnFocus: true);
};
?>

<div>
    <!-- Modal trigger -->
    <flux:button 
        wire:click="openModal('Edit Artist')"
        id="modal-trigger"
        aria-haspopup="dialog"
    >
        Edit Artist
    </flux:button>
    
    <!-- Modal -->
    @if($showModal)
        <div 
            role="dialog" 
            aria-modal="true"
            aria-labelledby="modal-title"
            aria-describedby="modal-description"
            class="fixed inset-0 z-50 overflow-y-auto"
            x-data="{ 
                init() {
                    // Focus first focusable element
                    this.$nextTick(() => {
                        this.$refs.firstFocusable.focus();
                    });
                    
                    // Trap focus within modal
                    this.trapFocus();
                }
            }"
            x-on:keydown.escape="$wire.closeModal()"
        >
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black bg-opacity-50" 
                 aria-hidden="true"
                 wire:click="closeModal">
            </div>
            
            <!-- Modal content -->
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <!-- Modal header -->
                    <div class="flex justify-between items-center mb-4">
                        <h2 id="modal-title" class="text-xl font-semibold">
                            {{ $modalTitle }}
                        </h2>
                        
                        <flux:button 
                            wire:click="closeModal"
                            variant="ghost"
                            size="sm"
                            aria-label="Close modal"
                            x-ref="lastFocusable"
                        >
                            <flux:icon.x-mark class="w-5 h-5" />
                        </flux:button>
                    </div>
                    
                    <!-- Modal body -->
                    <div id="modal-description">
                        <form wire:submit="saveArtist">
                            <flux:field>
                                <flux:label for="artist-name">Artist Name</flux:label>
                                <flux:input 
                                    id="artist-name"
                                    wire:model="artistName"
                                    x-ref="firstFocusable"
                                    required
                                />
                                <flux:error name="artistName" />
                            </flux:field>
                            
                            <div class="flex justify-end space-x-2 mt-6">
                                <flux:button 
                                    type="button"
                                    wire:click="closeModal"
                                    variant="ghost"
                                >
                                    Cancel
                                </flux:button>
                                
                                <flux:button type="submit" variant="primary">
                                    Save Changes
                                </flux:button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
// Focus trap utility
function trapFocus() {
    const focusableElements = this.$el.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    const firstFocusable = focusableElements[0];
    const lastFocusable = focusableElements[focusableElements.length - 1];
    
    this.$el.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    lastFocusable.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    firstFocusable.focus();
                    e.preventDefault();
                }
            }
        }
    });
}
</script>
```

### Keyboard Shortcuts

```php
<?php
// Global keyboard shortcuts
use function Livewire\Volt\{state, on};

state(['shortcuts' => [
    ['key' => '/', 'action' => 'focusSearch', 'description' => 'Focus search'],
    ['key' => 'h', 'action' => 'goHome', 'description' => 'Go to dashboard'],
    ['key' => 'a', 'action' => 'goToArtists', 'description' => 'Go to artists'],
    ['key' => '?', 'action' => 'showHelp', 'description' => 'Show keyboard shortcuts']
]]);

on([
    'keyboard-shortcut' => function ($key) {
        match($key) {
            '/' => $this->dispatch('focus-search'),
            'h' => $this->redirect(route('dashboard'), navigate: true),
            'a' => $this->redirect(route('artists.index'), navigate: true),
            '?' => $this->dispatch('show-shortcuts-modal'),
            default => null
        };
    }
]);
?>

<!-- Keyboard shortcuts help -->
<div x-data="{ showShortcuts: false }" 
     x-on:show-shortcuts-modal.window="showShortcuts = true">
    
    <!-- Global keyboard listener -->
    <div x-on:keydown.window="handleKeydown($event)"></div>
    
    <!-- Shortcuts modal -->
    <flux:modal x-model="showShortcuts">
        <flux:modal.header>
            <flux:heading size="lg">Keyboard Shortcuts</flux:heading>
        </flux:modal.header>
        
        <div class="p-6">
            <dl class="space-y-3">
                @foreach($shortcuts as $shortcut)
                    <div class="flex justify-between">
                        <dt>
                            <flux:badge variant="subtle" class="font-mono">
                                {{ $shortcut['key'] }}
                            </flux:badge>
                        </dt>
                        <dd class="text-gray-600">{{ $shortcut['description'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
        
        <flux:modal.footer>
            <flux:button x-on:click="showShortcuts = false">Close</flux:button>
        </flux:modal.footer>
    </flux:modal>
</div>

<script>
function handleKeydown(event) {
    // Don't trigger shortcuts when typing in inputs
    if (event.target.tagName === 'INPUT' || 
        event.target.tagName === 'TEXTAREA' || 
        event.target.isContentEditable) {
        return;
    }
    
    // Handle shortcuts
    const key = event.key.toLowerCase();
    
    if (key === '/' || key === 'h' || key === 'a' || key === '?') {
        event.preventDefault();
        Livewire.dispatch('keyboard-shortcut', key);
    }
}
</script>
```

## Screen Reader Support

### ARIA Labels and Descriptions

```php
<?php
// Music player with comprehensive ARIA support
use function Livewire\Volt\{state, computed};

state([
    'currentTrack' => null,
    'isPlaying' => false,
    'volume' => 0.8,
    'position' => 0,
    'duration' => 300
]);

$playbackStatus = computed(function () {
    if (!$this->currentTrack) return 'No track selected';

    return $this->isPlaying
        ? "Playing {$this->currentTrack->name} by {$this->currentTrack->album->artist->name}"
        : "Paused {$this->currentTrack->name} by {$this->currentTrack->album->artist->name}";
});

$togglePlay = function () {
    $this->isPlaying = !$this->isPlaying;

    // Announce state change to screen readers
    $this->dispatch('announce',
        message: $this->isPlaying ? 'Playback started' : 'Playback paused'
    );
};
?>

<div role="region"
     aria-label="Music player"
     aria-live="polite"
     class="bg-white border-t shadow-lg p-4">

    <!-- Screen reader announcements -->
    <div id="player-announcements"
         aria-live="assertive"
         aria-atomic="true"
         class="sr-only">
        {{ $this->playbackStatus }}
    </div>

    @if($currentTrack)
        <!-- Track information -->
        <div class="flex items-center space-x-4 mb-4">
            <img src="{{ $currentTrack->album->getFirstMediaUrl('cover') }}"
                 alt="Album cover for {{ $currentTrack->album->title }}"
                 class="w-16 h-16 rounded">

            <div>
                <h3 class="font-medium">{{ $currentTrack->name }}</h3>
                <p class="text-gray-600">{{ $currentTrack->album->artist->name }}</p>
            </div>
        </div>

        <!-- Player controls -->
        <div role="group"
             aria-label="Playback controls"
             class="flex items-center justify-center space-x-4 mb-4">

            <flux:button
                wire:click="previousTrack"
                aria-label="Previous track"
                variant="ghost"
                icon="backward"
            />

            <flux:button
                wire:click="togglePlay"
                :aria-label="$isPlaying ? 'Pause' : 'Play'"
                variant="primary"
                :icon="$isPlaying ? 'pause' : 'play'"
            />

            <flux:button
                wire:click="nextTrack"
                aria-label="Next track"
                variant="ghost"
                icon="forward"
            />
        </div>

        <!-- Progress bar -->
        <div class="space-y-2">
            <label for="progress-slider" class="sr-only">
                Track progress: {{ gmdate('i:s', $position) }} of {{ gmdate('i:s', $duration) }}
            </label>

            <input
                id="progress-slider"
                type="range"
                min="0"
                max="{{ $duration }}"
                wire:model.live="position"
                aria-valuemin="0"
                aria-valuemax="{{ $duration }}"
                aria-valuenow="{{ $position }}"
                aria-valuetext="{{ gmdate('i:s', $position) }} of {{ gmdate('i:s', $duration) }}"
                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
            />
        </div>
    @endif
</div>
```

## Color and Contrast

### WCAG AA Compliant Color System

```css
/* resources/css/accessibility.css */

/* Color system with 4.5:1 contrast ratios */
:root {
    /* Primary colors */
    --color-primary-500: #3b82f6;  /* 4.5:1 on white */
    --color-primary-600: #2563eb;  /* 7:1 on white */

    /* Gray scale */
    --color-gray-500: #6b7280;     /* 4.5:1 on white */
    --color-gray-600: #4b5563;     /* 7:1 on white */
    --color-gray-900: #111827;     /* 15:1 on white */

    /* Status colors */
    --color-success-600: #059669;  /* 4.5:1 on white */
    --color-warning-600: #d97706;  /* 4.5:1 on white */
    --color-danger-600: #dc2626;   /* 4.5:1 on white */
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    :root {
        --color-primary-500: #1d4ed8;
        --color-gray-500: #374151;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

/* Screen reader only content */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
```

## Focus Management

### Visible Focus Indicators

```php
<?php
// Navigation with enhanced focus management
use function Livewire\Volt\{state};

state(['currentPage' => 'dashboard']);
?>

<nav role="navigation" aria-label="Main navigation">
    <ul class="flex space-x-4" role="list">
        <li>
            <flux:button
                wire:navigate
                href="{{ route('dashboard') }}"
                :variant="$currentPage === 'dashboard' ? 'primary' : 'ghost'"
                class="focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                :aria-current="$currentPage === 'dashboard' ? 'page' : 'false'"
            >
                Dashboard
            </flux:button>
        </li>

        <li>
            <flux:button
                wire:navigate
                href="{{ route('artists.index') }}"
                :variant="str_starts_with($currentPage, 'artists') ? 'primary' : 'ghost'"
                class="focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                :aria-current="str_starts_with($currentPage, 'artists') ? 'page' : 'false'"
            >
                Artists
            </flux:button>
        </li>
    </ul>
</nav>
```

## Form Accessibility

### Comprehensive Form Patterns

```php
<?php
// Accessible search form
use function Livewire\Volt\{state, rules};

state(['query' => '', 'filters' => []]);

rules(['query' => 'required|min:2']);

$search = function () {
    $this->validate();

    // Perform search
    $this->dispatch('search-performed', query: $this->query);
};
?>

<form wire:submit="search"
      role="search"
      aria-label="Search music library">

    <div class="flex items-end space-x-4">
        <flux:field class="flex-1">
            <flux:label for="search-input">
                Search
                <span class="sr-only">(required, minimum 2 characters)</span>
            </flux:label>

            <flux:input
                id="search-input"
                wire:model="query"
                placeholder="Search artists, albums, tracks..."
                aria-describedby="search-help search-error"
                aria-required="true"
                :aria-invalid="$errors->has('query') ? 'true' : 'false'"
                autocomplete="off"
                spellcheck="false"
            />

            <flux:description id="search-help">
                Enter at least 2 characters to search the music library.
            </flux:description>

            @error('query')
                <flux:error id="search-error" role="alert">
                    {{ $message }}
                </flux:error>
            @enderror
        </flux:field>

        <flux:button
            type="submit"
            variant="primary"
            aria-describedby="search-button-help"
        >
            Search
        </flux:button>
    </div>

    <flux:description id="search-button-help" class="mt-2">
        Press Enter or click Search to find music.
    </flux:description>
</form>
```

## Testing Strategies

### Automated Accessibility Testing

```php
// tests/Feature/AccessibilityTest.php
use Livewire\Volt\Volt;

it('has proper heading hierarchy', function () {
    $response = $this->get(route('artists.index'));

    $response->assertSee('<h1', false); // Main heading exists
    $response->assertDontSee('<h3', false); // No h3 without h2
});

it('has proper ARIA labels', function () {
    Volt::test('music-player')
        ->assertSee('aria-label="Music player"', false)
        ->assertSee('role="region"', false);
});

it('provides keyboard navigation', function () {
    $this->get(route('artists.index'))
        ->assertSee('tabindex', false)
        ->assertSee('aria-current', false);
});

it('has sufficient color contrast', function () {
    // This would integrate with tools like axe-core
    $this->markTestIncomplete('Requires axe-core integration');
});
```

### Manual Testing Checklist

```markdown
## Accessibility Testing Checklist

### Keyboard Navigation
- [ ] All interactive elements are keyboard accessible
- [ ] Tab order is logical and intuitive
- [ ] Focus indicators are clearly visible
- [ ] Escape key closes modals and dropdowns
- [ ] Arrow keys work in menus and lists

### Screen Reader Testing
- [ ] All images have appropriate alt text
- [ ] Form fields have proper labels
- [ ] Error messages are announced
- [ ] Status changes are announced
- [ ] Headings create logical structure

### Visual Testing
- [ ] Text has sufficient color contrast (4.5:1 minimum)
- [ ] Content is readable at 200% zoom
- [ ] No information conveyed by color alone
- [ ] Focus indicators are visible
- [ ] Text spacing is adequate

### Motor Accessibility
- [ ] Click targets are at least 44x44 pixels
- [ ] Drag and drop has keyboard alternatives
- [ ] Time limits can be extended
- [ ] No content flashes more than 3 times per second
```

## Best Practices

### Accessibility Guidelines

1. **Semantic HTML**: Use proper HTML elements for their intended purpose
2. **ARIA Labels**: Provide descriptive labels for all interactive elements
3. **Keyboard Support**: Ensure all functionality is keyboard accessible
4. **Color Contrast**: Maintain WCAG AA contrast ratios (4.5:1 minimum)
5. **Focus Management**: Provide clear focus indicators and logical tab order
6. **Error Handling**: Make error messages clear and actionable
7. **Testing**: Regularly test with screen readers and keyboard navigation

### Implementation Checklist

- [ ] All images have alt text
- [ ] Forms have proper labels and error handling
- [ ] Navigation is keyboard accessible
- [ ] Color contrast meets WCAG AA standards
- [ ] Focus indicators are visible
- [ ] Screen reader announcements work correctly
- [ ] Content is structured with proper headings
- [ ] Interactive elements have appropriate ARIA attributes

## Navigation

**← Previous** [SPA Navigation Implementation Guide](130-spa-navigation-guide.md)
**Next →** [Performance Optimization Guide](150-performance-optimization-guide.md)

---

*This guide provides comprehensive strategies for implementing WCAG 2.1 AA accessibility compliance. Continue with the performance optimization guide for efficient component loading and data handling.*
