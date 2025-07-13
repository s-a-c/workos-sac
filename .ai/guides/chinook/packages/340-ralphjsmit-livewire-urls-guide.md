# 1. RalphJSmit Livewire URLs Integration Guide

> **Package Source:** [ralphjsmit/livewire-urls](https://github.com/ralphjsmit/livewire-urls)  
> **Official Documentation:** [Livewire URLs Documentation](https://github.com/ralphjsmit/livewire-urls/blob/main/README.md)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook Livewire components and URL state management  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Package Installation](#131-package-installation)
  - [1.3.2. URL Configuration](#132-url-configuration)
- [1.4. Chinook Livewire Integration](#14-chinook-livewire-integration)
  - [1.4.1. Music Catalog URL State](#141-music-catalog-url-state)
  - [1.4.2. Search & Filter URLs](#142-search--filter-urls)
  - [1.4.3. Playlist Management URLs](#143-playlist-management-urls)
- [1.5. Advanced URL Patterns](#15-advanced-url-patterns)
- [1.6. Performance & SEO](#16-performance--seo)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [RalphJSmit Livewire URLs documentation](https://github.com/ralphjsmit/livewire-urls/blob/main/README.md) for Laravel 12 and Chinook project requirements, focusing on URL state management for Livewire components in music catalog applications.

**RalphJSmit Livewire URLs** provides powerful URL state management for Livewire components. It enables deep linking, bookmarkable states, and SEO-friendly URLs while maintaining the reactive nature of Livewire applications.

### 1.2.1. Key Features

- **URL State Synchronization**: Automatic synchronization between component state and URLs
- **Deep Linking Support**: Bookmarkable and shareable component states
- **SEO-Friendly URLs**: Clean, readable URLs for better search engine optimization
- **Browser History**: Proper browser back/forward button support
- **Query Parameter Management**: Flexible query parameter handling
- **Route Model Binding**: Integration with Laravel route model binding

### 1.2.2. Chinook URL Management Benefits

- **Music Catalog Navigation**: Bookmarkable artist, album, and track views
- **Search State Persistence**: Shareable search results and filter states
- **Playlist Deep Linking**: Direct links to specific playlist configurations
- **Admin Panel URLs**: Stateful admin panel navigation and filtering
- **API Integration**: URL-based API endpoint generation

## 1.3. Installation & Configuration

### 1.3.1. Package Installation

> **Installation Source:** Based on [official installation guide](https://github.com/ralphjsmit/livewire-urls#installation)  
> **Chinook Enhancement:** Already installed and configured

The package is already installed via Composer. Verify installation:

<augment_code_snippet path="composer.json" mode="EXCERPT">
````json
{
    "require": {
        "ralphjsmit/livewire-urls": "^1.5"
    }
}
````
</augment_code_snippet>

**Publish Configuration:**

```bash
# Publish Livewire URLs configuration
php artisan vendor:publish --tag="livewire-urls-config"

# Publish Livewire URLs views (optional)
php artisan vendor:publish --tag="livewire-urls-views"
```

### 1.3.2. URL Configuration

> **Configuration Source:** Enhanced from [livewire-urls configuration](https://github.com/ralphjsmit/livewire-urls/blob/main/config/livewire-urls.php)  
> **Chinook Modifications:** Optimized for Chinook music catalog URL patterns

<augment_code_snippet path="config/livewire-urls.php" mode="EXCERPT">
````php
<?php
// Configuration adapted from: https://github.com/ralphjsmit/livewire-urls/blob/main/config/livewire-urls.php
// Chinook modifications: Enhanced for music catalog URL patterns and SEO optimization
// Laravel 12 updates: Modern syntax and framework patterns

return [
    /*
     * URL generation configuration
     */
    'url_generation' => [
        /*
         * Enable automatic URL generation
         */
        'enabled' => env('LIVEWIRE_URLS_ENABLED', true),

        /*
         * URL encoding for special characters
         */
        'encode_urls' => true,

        /*
         * URL parameter separator
         */
        'parameter_separator' => '&',

        /*
         * Array parameter format
         */
        'array_format' => 'brackets', // artist[]=1&artist[]=2
    ],

    /*
     * Query parameter configuration
     */
    'query_parameters' => [
        /*
         * Allowed query parameters
         */
        'allowed' => [
            'search',
            'genre',
            'artist',
            'album',
            'year',
            'sort',
            'filter',
            'page',
            'per_page',
            'view',
        ],

        /*
         * Parameter aliases for cleaner URLs
         */
        'aliases' => [
            'q' => 'search',
            'g' => 'genre',
            'a' => 'artist',
            'al' => 'album',
            'y' => 'year',
            's' => 'sort',
            'f' => 'filter',
            'p' => 'page',
            'pp' => 'per_page',
            'v' => 'view',
        ],

        /*
         * Default values (excluded from URL when default)
         */
        'defaults' => [
            'sort' => 'name',
            'view' => 'grid',
            'per_page' => 20,
            'page' => 1,
        ],
    ],

    /*
     * SEO configuration
     */
    'seo' => [
        /*
         * Generate meta tags based on URL state
         */
        'auto_meta_tags' => true,

        /*
         * Canonical URL generation
         */
        'canonical_urls' => true,

        /*
         * Open Graph tags
         */
        'open_graph' => true,

        /*
         * Twitter Card tags
         */
        'twitter_cards' => true,
    ],

    /*
     * Chinook-specific configuration
     */
    'chinook' => [
        /*
         * Music catalog URL patterns
         */
        'url_patterns' => [
            'artist_detail' => '/artists/{artist:slug}',
            'album_detail' => '/albums/{album:slug}',
            'track_detail' => '/tracks/{track:slug}',
            'genre_listing' => '/genres/{genre:slug}',
            'playlist_detail' => '/playlists/{playlist:slug}',
        ],

        /*
         * Search URL configuration
         */
        'search_urls' => [
            'base_path' => '/search',
            'result_path' => '/search/results',
            'advanced_path' => '/search/advanced',
        ],

        /*
         * Filter URL patterns
         */
        'filter_patterns' => [
            'by_genre' => 'genre',
            'by_artist' => 'artist',
            'by_year' => 'year',
            'by_duration' => 'duration',
            'by_rating' => 'rating',
        ],

        /*
         * Pagination configuration
         */
        'pagination' => [
            'page_parameter' => 'page',
            'per_page_parameter' => 'per_page',
            'per_page_options' => [10, 20, 50, 100],
            'default_per_page' => 20,
        ],

        /*
         * State persistence
         */
        'state_persistence' => [
            'session_key' => 'chinook_livewire_state',
            'cookie_name' => 'chinook_preferences',
            'cookie_lifetime' => 30, // days
        ],
    ],

    /*
     * Performance configuration
     */
    'performance' => [
        /*
         * Cache URL generation
         */
        'cache_urls' => true,

        /*
         * Cache TTL in seconds
         */
        'cache_ttl' => 3600,

        /*
         * Debounce URL updates
         */
        'debounce_ms' => 300,

        /*
         * Lazy loading for URL updates
         */
        'lazy_loading' => true,
    ],
];
````
</augment_code_snippet>

## 1.4. Chinook Livewire Integration

### 1.4.1. Music Catalog URL State

> **Catalog URLs:** URL state management for music catalog browsing and navigation

<augment_code_snippet path="app/Livewire/MusicCatalog.php" mode="EXCERPT">
````php
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use RalphJSmit\LivewireUrls\Concerns\HasUrls;
use App\Models\ChinookArtist;
use App\Models\ChinookAlbum;
use App\Models\ChinookTrack;

class MusicCatalog extends Component
{
    use WithPagination, HasUrls;

    // URL-tracked properties
    public string $search = '';
    public array $selectedGenres = [];
    public ?int $selectedArtist = null;
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';
    public string $viewMode = 'grid';
    public int $perPage = 20;

    /**
     * Define URL tracking for component properties
     */
    protected function urlProperties(): array
    {
        return [
            'search' => ['as' => 'q', 'default' => ''],
            'selectedGenres' => ['as' => 'genres', 'default' => []],
            'selectedArtist' => ['as' => 'artist', 'default' => null],
            'sortBy' => ['as' => 'sort', 'default' => 'name'],
            'sortDirection' => ['as' => 'dir', 'default' => 'asc'],
            'viewMode' => ['as' => 'view', 'default' => 'grid'],
            'perPage' => ['as' => 'per_page', 'default' => 20],
        ];
    }

    /**
     * Update search and sync with URL
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->updateUrl();
    }

    /**
     * Update genre filter and sync with URL
     */
    public function updatedSelectedGenres(): void
    {
        $this->resetPage();
        $this->updateUrl();
    }

    /**
     * Update artist filter and sync with URL
     */
    public function updatedSelectedArtist(): void
    {
        $this->resetPage();
        $this->updateUrl();
    }

    /**
     * Update sorting and sync with URL
     */
    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
        $this->updateUrl();
    }

    /**
     * Change view mode and sync with URL
     */
    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->updateUrl();
    }

    /**
     * Get filtered and sorted results
     */
    public function getResultsProperty()
    {
        $query = ChinookTrack::query()
            ->with(['album.artist', 'taxonomies'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhereHas('album', function ($albumQuery) {
                          $albumQuery->where('title', 'like', "%{$this->search}%");
                      })
                      ->orWhereHas('album.artist', function ($artistQuery) {
                          $artistQuery->where('name', 'like', "%{$this->search}%");
                      });
                });
            })
            ->when($this->selectedGenres, function ($query) {
                $query->whereHas('taxonomies', function ($taxonomyQuery) {
                    $taxonomyQuery->whereIn('id', $this->selectedGenres);
                });
            })
            ->when($this->selectedArtist, function ($query) {
                $query->whereHas('album', function ($albumQuery) {
                    $albumQuery->where('artist_id', $this->selectedArtist);
                });
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    /**
     * Generate SEO meta tags based on current state
     */
    public function getMetaTagsProperty(): array
    {
        $title = 'Music Catalog';
        $description = 'Browse our comprehensive music catalog';

        if ($this->search) {
            $title = "Search results for '{$this->search}' - Music Catalog";
            $description = "Find music tracks, albums, and artists matching '{$this->search}'";
        }

        if ($this->selectedArtist) {
            $artist = ChinookArtist::find($this->selectedArtist);
            if ($artist) {
                $title = "{$artist->name} - Music Catalog";
                $description = "Browse music by {$artist->name}";
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $this->getCurrentUrl(),
        ];
    }

    public function render()
    {
        return view('livewire.music-catalog', [
            'results' => $this->results,
            'metaTags' => $this->metaTags,
        ]);
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [LaravelJutsu Zap Guide](330-laraveljutsu-zap-guide.md) | **Next:** [Quality Assurance Phase](../quality-assurance/000-qa-index.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
