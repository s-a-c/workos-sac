# 1. Laravel Fractal Implementation Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Installation & Setup](#2-installation--setup)
  - [2.1. Package Installation](#21-package-installation)
  - [2.2. Configuration Publishing](#22-configuration-publishing)
  - [2.3. Basic Setup](#23-basic-setup)
- [3. Transformer Creation](#3-transformer-creation)
  - [3.1. Basic Transformers](#31-basic-transformers)
  - [3.2. Advanced Transformers](#32-advanced-transformers)
  - [3.3. Nested Transformers](#33-nested-transformers)
- [5. Taxonomy Integration](#5-taxonomy-integration)
  - [5.1. Taxonomy Transformers](#51-taxonomy-transformers)
  - [5.2. Hierarchical Data Handling](#52-hierarchical-data-handling)
- [Navigation](#navigation)

## 1. Overview

Laravel Fractal provides advanced API transformation layers with flexible resource management, relationship handling, and comprehensive pagination support. This guide covers enterprise-level implementation with **exclusive aliziodev/laravel-taxonomy integration**, caching optimization, and modern API patterns for the Chinook music database system.

**🚀 Key Features:**
- **Flexible Transformations**: Customizable data transformation with includes/excludes
- **Relationship Handling**: Efficient nested resource loading and transformation
- **Pagination Support**: Built-in pagination with metadata and navigation links
- **Caching Integration**: Response caching for improved performance
- **API Versioning**: Support for multiple API versions and backward compatibility
- **Performance Optimization**: Lazy loading and efficient data processing
- **Taxonomy Integration**: Native support for aliziodev/laravel-taxonomy hierarchies

**🎵 Chinook Integration Benefits:**
- **Hierarchical Genre Transformations**: Efficient handling of genre taxonomies with parent-child relationships
- **Unified API Responses**: Consistent transformation patterns across Artists, Albums, Tracks, and Genres
- **Performance Optimized**: Eager loading strategies for taxonomy relationships
- **Flexible Includes**: Dynamic inclusion of taxonomy data based on API consumer needs

## 2. Installation & Setup

### 2.1. Package Installation

Install Laravel Fractal using Composer:

```bash
# Install Laravel Fractal
composer require spatie/laravel-fractal

# Publish configuration (optional)
php artisan vendor:publish --provider="Spatie\Fractal\FractalServiceProvider"
```

### 2.2. Configuration Publishing

Publish and configure the Fractal configuration file:

```bash
# Publish configuration
php artisan vendor:publish --tag=fractal-config
```

Configuration file (`config/fractal.php`):

```php
<?php

return [
    /*
     * The default serializer to be used when performing a transformation.
     */
    'default_serializer' => \League\Fractal\Serializer\DataArraySerializer::class,

    /*
     * The default paginator to be used when transforming paginated results.
     */
    'default_paginator' => \League\Fractal\Paginator\IlluminatePaginatorAdapter::class,

    /*
     * League\Fractal\Manager settings
     */
    'auto_includes' => [
        'request_key' => 'include',
    ],
];
```

### 2.3. Basic Setup

Create a base transformer class for consistent API responses with taxonomy support:

```php
<?php

namespace App\Transformers;

use League\Fractal\TransformerAbstract;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

abstract class BaseTransformer extends TransformerAbstract
{
    /**
     * Common fields for all models
     */
    protected function getBaseFields($model): array
    {
        return [
            'id' => $model->public_id ?? $model->id,
            'created_at' => $model->created_at?->toISOString(),
            'updated_at' => $model->updated_at?->toISOString(),
        ];
    }

    /**
     * Include user stamps if available
     */
    protected function getUserStamps($model): array
    {
        $stamps = [];

        if ($model->created_by) {
            $stamps['created_by'] = [
                'id' => $model->createdBy->public_id,
                'name' => $model->createdBy->name,
            ];
        }

        if ($model->updated_by) {
            $stamps['updated_by'] = [
                'id' => $model->updatedBy->public_id,
                'name' => $model->updatedBy->name,
            ];
        }

        return $stamps;
    }

    /**
     * Transform taxonomy relationships
     */
    protected function transformTaxonomies($taxonomies): array
    {
        if (!$taxonomies || $taxonomies->isEmpty()) {
            return [];
        }

        return $taxonomies->map(function (Taxonomy $taxonomy) {
            return [
                'id' => $taxonomy->id,
                'name' => $taxonomy->name,
                'slug' => $taxonomy->slug,
                'type' => $taxonomy->type,
                'description' => $taxonomy->description,
                'parent_id' => $taxonomy->parent_id,
                'depth' => $taxonomy->depth ?? 0,
                'full_path' => $this->getTaxonomyPath($taxonomy),
                'meta' => $taxonomy->meta,
            ];
        })->toArray();
    }

    /**
     * Get full taxonomy path
     */
    protected function getTaxonomyPath(Taxonomy $taxonomy): string
    {
        $path = [$taxonomy->name];
        $current = $taxonomy->parent;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Transform taxonomy hierarchy
     */
    protected function transformTaxonomyHierarchy(Taxonomy $taxonomy): array
    {
        return [
            'id' => $taxonomy->id,
            'name' => $taxonomy->name,
            'slug' => $taxonomy->slug,
            'type' => $taxonomy->type,
            'description' => $taxonomy->description,
            'parent_id' => $taxonomy->parent_id,
            'depth' => $taxonomy->depth ?? 0,
            'full_path' => $this->getTaxonomyPath($taxonomy),
            'is_root' => $taxonomy->parent_id === null,
            'is_leaf' => !$taxonomy->children()->exists(),
            'children_count' => $taxonomy->children()->count(),
            'meta' => $taxonomy->meta,
        ];
    }
}
```

## 3. Transformer Creation

### 3.1. Basic Transformers

Create transformers for core Chinook models with taxonomy integration:

```php
<?php

namespace App\Transformers;

use App\Models\ChinookArtist;

class ChinookArtistTransformer extends BaseTransformer
{
    /**
     * Available includes
     */
    protected array $availableIncludes = [
        'albums',
        'genres',
        'tracks',
        'media',
    ];

    /**
     * Default includes
     */
    protected array $defaultIncludes = [
        'genres',
    ];

    /**
     * Transform artist data
     */
    public function transform(ChinookArtist $artist): array
    {
        return array_merge($this->getBaseFields($artist), [
            'name' => $artist->name,
            'slug' => $artist->slug,
            'bio' => $artist->bio,
            'website' => $artist->website,
            'formed_year' => $artist->formed_year,
            'albums_count' => $artist->albums_count ?? $artist->albums()->count(),
            'tracks_count' => $artist->tracks_count ?? $artist->tracks()->count(),
            'genres_count' => $artist->taxonomies()->where('type', 'genre')->count(),
        ], $this->getUserStamps($artist));
    }

    /**
     * Include albums
     */
    public function includeAlbums(ChinookArtist $artist)
    {
        return $this->collection($artist->albums, new ChinookAlbumTransformer);
    }

    /**
     * Include genres (taxonomies)
     */
    public function includeGenres(ChinookArtist $artist)
    {
        $genres = $artist->taxonomies()->where('type', 'genre')->get();
        return $this->collection($genres, new TaxonomyTransformer);
    }

    /**
     * Include tracks
     */
    public function includeTracks(ChinookArtist $artist)
    {
        return $this->collection($artist->tracks, new ChinookTrackTransformer);
    }

    /**
     * Include media
     */
    public function includeMedia(ChinookArtist $artist)
    {
        return $this->collection($artist->media, new MediaTransformer);
    }
}
```

### 3.2. Advanced Transformers

Create advanced transformers with complex taxonomy relationships:

```php
<?php

namespace App\Transformers;

use App\Models\ChinookTrack;

class ChinookTrackTransformer extends BaseTransformer
{
    /**
     * Available includes
     */
    protected array $availableIncludes = [
        'album',
        'artist',
        'genres',
        'media_type',
        'playlists',
        'invoice_lines',
    ];

    /**
     * Default includes
     */
    protected array $defaultIncludes = [
        'genres',
    ];

    /**
     * Transform track data
     */
    public function transform(ChinookTrack $track): array
    {
        return array_merge($this->getBaseFields($track), [
            'name' => $track->name,
            'composer' => $track->composer,
            'milliseconds' => $track->milliseconds,
            'bytes' => $track->bytes,
            'unit_price' => $track->unit_price,
            'duration_formatted' => $this->formatDuration($track->milliseconds),
            'file_size_formatted' => $this->formatFileSize($track->bytes),
            'price_formatted' => $this->formatPrice($track->unit_price),
            'album_id' => $track->album_id,
            'media_type_id' => $track->media_type_id,
            'genre_primary' => $this->getPrimaryGenre($track),
            'genre_count' => $track->taxonomies()->where('type', 'genre')->count(),
        ], $this->getUserStamps($track));
    }

    /**
     * Include album
     */
    public function includeAlbum(ChinookTrack $track)
    {
        return $this->item($track->album, new ChinookAlbumTransformer);
    }

    /**
     * Include artist
     */
    public function includeArtist(ChinookTrack $track)
    {
        return $this->item($track->album->artist, new ChinookArtistTransformer);
    }

    /**
     * Include genres (taxonomies)
     */
    public function includeGenres(ChinookTrack $track)
    {
        $genres = $track->taxonomies()->where('type', 'genre')->get();
        return $this->collection($genres, new TaxonomyTransformer);
    }

    /**
     * Include media type
     */
    public function includeMediaType(ChinookTrack $track)
    {
        return $this->item($track->mediaType, new MediaTypeTransformer);
    }

    /**
     * Include playlists
     */
    public function includePlaylists(ChinookTrack $track)
    {
        return $this->collection($track->playlists, new PlaylistTransformer);
    }

    /**
     * Include invoice lines
     */
    public function includeInvoiceLines(ChinookTrack $track)
    {
        return $this->collection($track->invoiceLines, new InvoiceLineTransformer);
    }

    /**
     * Format duration in milliseconds to human readable format
     */
    private function formatDuration(?int $milliseconds): ?string
    {
        if (!$milliseconds) {
            return null;
        }

        $seconds = $milliseconds / 1000;
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Format file size in bytes to human readable format
     */
    private function formatFileSize(?int $bytes): ?string
    {
        if (!$bytes) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Format price
     */
    private function formatPrice(?float $price): ?string
    {
        return $price ? '$' . number_format($price, 2) : null;
    }

    /**
     * Get primary genre for the track
     */
    private function getPrimaryGenre(ChinookTrack $track): ?array
    {
        $primaryGenre = $track->taxonomies()
            ->where('type', 'genre')
            ->orderBy('pivot_created_at', 'asc')
            ->first();

        return $primaryGenre ? [
            'id' => $primaryGenre->id,
            'name' => $primaryGenre->name,
            'slug' => $primaryGenre->slug,
        ] : null;
    }
}
```

### 3.3. Nested Transformers

Create specialized taxonomy transformers for hierarchical data:

```php
<?php

namespace App\Transformers;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class TaxonomyTransformer extends BaseTransformer
{
    /**
     * Available includes
     */
    protected array $availableIncludes = [
        'parent',
        'children',
        'ancestors',
        'descendants',
        'tracks',
        'albums',
        'artists',
        'statistics',
    ];

    /**
     * Default includes
     */
    protected array $defaultIncludes = [];

    /**
     * Transform taxonomy data
     */
    public function transform(Taxonomy $taxonomy): array
    {
        return [
            'id' => $taxonomy->id,
            'name' => $taxonomy->name,
            'slug' => $taxonomy->slug,
            'type' => $taxonomy->type,
            'description' => $taxonomy->description,
            'parent_id' => $taxonomy->parent_id,
            'depth' => $taxonomy->depth ?? 0,
            'full_path' => $this->getTaxonomyPath($taxonomy),
            'is_root' => $taxonomy->parent_id === null,
            'is_leaf' => !$taxonomy->children()->exists(),
            'children_count' => $taxonomy->children()->count(),
            'meta' => $taxonomy->meta,
            'created_at' => $taxonomy->created_at?->toISOString(),
            'updated_at' => $taxonomy->updated_at?->toISOString(),
        ];
    }

    /**
     * Include parent taxonomy
     */
    public function includeParent(Taxonomy $taxonomy)
    {
        if ($taxonomy->parent) {
            return $this->item($taxonomy->parent, new TaxonomyTransformer);
        }

        return $this->null();
    }

    /**
     * Include children taxonomies
     */
    public function includeChildren(Taxonomy $taxonomy)
    {
        return $this->collection($taxonomy->children, new TaxonomyTransformer);
    }

    /**
     * Include ancestors
     */
    public function includeAncestors(Taxonomy $taxonomy)
    {
        $ancestors = collect();
        $current = $taxonomy->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $this->collection($ancestors->reverse(), new TaxonomyTransformer);
    }

    /**
     * Include descendants
     */
    public function includeDescendants(Taxonomy $taxonomy)
    {
        $descendants = $taxonomy->descendants()->get();
        return $this->collection($descendants, new TaxonomyTransformer);
    }

    /**
     * Include related tracks
     */
    public function includeTracks(Taxonomy $taxonomy)
    {
        $tracks = $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->get();

        return $this->collection($tracks, new ChinookTrackTransformer);
    }

    /**
     * Include related albums
     */
    public function includeAlbums(Taxonomy $taxonomy)
    {
        $albums = $taxonomy->morphedByMany(
            \App\Models\ChinookAlbum::class,
            'taxonomizable'
        )->get();

        return $this->collection($albums, new ChinookAlbumTransformer);
    }

    /**
     * Include related artists
     */
    public function includeArtists(Taxonomy $taxonomy)
    {
        $artists = $taxonomy->morphedByMany(
            \App\Models\ChinookArtist::class,
            'taxonomizable'
        )->get();

        return $this->collection($artists, new ChinookArtistTransformer);
    }

    /**
     * Include taxonomy statistics
     */
    public function includeStatistics(Taxonomy $taxonomy)
    {
        return $this->item($taxonomy, new TaxonomyStatisticsTransformer);
    }
}
```

## 5. Taxonomy Integration

### 5.1. Taxonomy Transformers

Specialized transformers for taxonomy statistics and analytics:

```php
<?php

namespace App\Transformers;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class TaxonomyStatisticsTransformer extends BaseTransformer
{
    /**
     * Transform taxonomy statistics
     */
    public function transform(Taxonomy $taxonomy): array
    {
        return [
            'id' => $taxonomy->id,
            'name' => $taxonomy->name,
            'type' => $taxonomy->type,
            'statistics' => [
                'tracks_count' => $this->getTracksCount($taxonomy),
                'albums_count' => $this->getAlbumsCount($taxonomy),
                'artists_count' => $this->getArtistsCount($taxonomy),
                'total_duration_ms' => $this->getTotalDuration($taxonomy),
                'average_track_duration_ms' => $this->getAverageTrackDuration($taxonomy),
                'total_revenue' => $this->getTotalRevenue($taxonomy),
                'popularity_score' => $this->getPopularityScore($taxonomy),
                'trend_direction' => $this->getTrendDirection($taxonomy),
            ],
            'hierarchy_stats' => [
                'depth' => $taxonomy->depth ?? 0,
                'children_count' => $taxonomy->children()->count(),
                'descendants_count' => $taxonomy->descendants()->count(),
                'siblings_count' => $this->getSiblingsCount($taxonomy),
            ],
            'temporal_stats' => [
                'created_at' => $taxonomy->created_at?->toISOString(),
                'first_track_date' => $this->getFirstTrackDate($taxonomy),
                'latest_track_date' => $this->getLatestTrackDate($taxonomy),
                'activity_period_days' => $this->getActivityPeriodDays($taxonomy),
            ],
        ];
    }

    private function getTracksCount(Taxonomy $taxonomy): int
    {
        return $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->count();
    }

    private function getAlbumsCount(Taxonomy $taxonomy): int
    {
        return $taxonomy->morphedByMany(
            \App\Models\ChinookAlbum::class,
            'taxonomizable'
        )->count();
    }

    private function getArtistsCount(Taxonomy $taxonomy): int
    {
        return $taxonomy->morphedByMany(
            \App\Models\ChinookArtist::class,
            'taxonomizable'
        )->count();
    }

    private function getTotalDuration(Taxonomy $taxonomy): int
    {
        return $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->sum('milliseconds') ?? 0;
    }

    private function getAverageTrackDuration(Taxonomy $taxonomy): float
    {
        return $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->avg('milliseconds') ?? 0;
    }

    private function getTotalRevenue(Taxonomy $taxonomy): float
    {
        return $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->sum('unit_price') ?? 0;
    }

    private function getPopularityScore(Taxonomy $taxonomy): float
    {
        $tracksCount = $this->getTracksCount($taxonomy);
        $artistsCount = $this->getArtistsCount($taxonomy);
        $revenue = $this->getTotalRevenue($taxonomy);

        // Normalize scores (adjust these values based on your data)
        $trackScore = min($tracksCount / 100, 1) * 0.4;
        $artistScore = min($artistsCount / 50, 1) * 0.3;
        $revenueScore = min($revenue / 1000, 1) * 0.3;

        return ($trackScore + $artistScore + $revenueScore) * 100;
    }

    private function getTrendDirection(Taxonomy $taxonomy): string
    {
        $recentTracks = $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->where('created_at', '>=', now()->subMonths(3))->count();

        $totalTracks = $this->getTracksCount($taxonomy);

        if ($totalTracks === 0) {
            return 'stable';
        }

        $recentPercentage = ($recentTracks / $totalTracks) * 100;

        if ($recentPercentage > 25) {
            return 'rising';
        } elseif ($recentPercentage < 5) {
            return 'declining';
        }

        return 'stable';
    }

    private function getSiblingsCount(Taxonomy $taxonomy): int
    {
        if (!$taxonomy->parent_id) {
            return Taxonomy::whereNull('parent_id')
                ->where('id', '!=', $taxonomy->id)
                ->where('type', $taxonomy->type)
                ->count();
        }

        return $taxonomy->parent->children()
            ->where('id', '!=', $taxonomy->id)
            ->count();
    }

    private function getFirstTrackDate(Taxonomy $taxonomy): ?string
    {
        $firstTrack = $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->orderBy('created_at', 'asc')->first();

        return $firstTrack?->created_at?->toISOString();
    }

    private function getLatestTrackDate(Taxonomy $taxonomy): ?string
    {
        $latestTrack = $taxonomy->morphedByMany(
            \App\Models\ChinookTrack::class,
            'taxonomizable'
        )->orderBy('created_at', 'desc')->first();

        return $latestTrack?->created_at?->toISOString();
    }

    private function getActivityPeriodDays(Taxonomy $taxonomy): ?int
    {
        $firstDate = $this->getFirstTrackDate($taxonomy);
        $latestDate = $this->getLatestTrackDate($taxonomy);

        if (!$firstDate || !$latestDate) {
            return null;
        }

        return \Carbon\Carbon::parse($firstDate)
            ->diffInDays(\Carbon\Carbon::parse($latestDate));
    }
}
```

### 5.2. Hierarchical Data Handling

Handle complex taxonomy hierarchies in API responses:

```php
<?php

namespace App\Transformers;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class TaxonomyHierarchyTransformer extends BaseTransformer
{
    /**
     * Available includes
     */
    protected array $availableIncludes = [
        'full_tree',
        'breadcrumb',
        'related_genres',
    ];

    /**
     * Transform taxonomy hierarchy
     */
    public function transform(Taxonomy $taxonomy): array
    {
        return [
            'id' => $taxonomy->id,
            'name' => $taxonomy->name,
            'slug' => $taxonomy->slug,
            'type' => $taxonomy->type,
            'description' => $taxonomy->description,
            'hierarchy' => [
                'level' => $taxonomy->depth ?? 0,
                'position' => $this->getPositionInLevel($taxonomy),
                'is_root' => $taxonomy->parent_id === null,
                'is_leaf' => !$taxonomy->children()->exists(),
                'path' => $this->getTaxonomyPath($taxonomy),
                'path_ids' => $this->getTaxonomyPathIds($taxonomy),
            ],
            'relationships' => [
                'parent_id' => $taxonomy->parent_id,
                'children_count' => $taxonomy->children()->count(),
                'descendants_count' => $taxonomy->descendants()->count(),
                'siblings_count' => $this->getSiblingsCount($taxonomy),
            ],
            'meta' => $taxonomy->meta,
        ];
    }

    /**
     * Include full taxonomy tree
     */
    public function includeFullTree(Taxonomy $taxonomy)
    {
        $root = $this->findRoot($taxonomy);
        return $this->item($root, new TaxonomyTreeTransformer);
    }

    /**
     * Include breadcrumb navigation
     */
    public function includeBreadcrumb(Taxonomy $taxonomy)
    {
        $breadcrumb = [];
        $current = $taxonomy;

        while ($current) {
            array_unshift($breadcrumb, [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
                'url' => "/api/taxonomies/{$current->id}",
            ]);
            $current = $current->parent;
        }

        return $this->primitive($breadcrumb);
    }

    /**
     * Include related genres (siblings and cousins)
     */
    public function includeRelatedGenres(Taxonomy $taxonomy)
    {
        $related = collect();

        // Add siblings
        if ($taxonomy->parent) {
            $siblings = $taxonomy->parent->children()
                ->where('id', '!=', $taxonomy->id)
                ->get();
            $related = $related->merge($siblings);
        }

        // Add cousins (children of parent's siblings)
        if ($taxonomy->parent && $taxonomy->parent->parent) {
            $uncles = $taxonomy->parent->parent->children()
                ->where('id', '!=', $taxonomy->parent->id)
                ->get();

            foreach ($uncles as $uncle) {
                $cousins = $uncle->children;
                $related = $related->merge($cousins);
            }
        }

        return $this->collection($related->unique('id'), new TaxonomyTransformer);
    }

    private function getPositionInLevel(Taxonomy $taxonomy): int
    {
        if (!$taxonomy->parent_id) {
            return Taxonomy::whereNull('parent_id')
                ->where('type', $taxonomy->type)
                ->where('id', '<=', $taxonomy->id)
                ->count();
        }

        return $taxonomy->parent->children()
            ->where('id', '<=', $taxonomy->id)
            ->count();
    }

    private function getTaxonomyPathIds(Taxonomy $taxonomy): array
    {
        $pathIds = [$taxonomy->id];
        $current = $taxonomy->parent;

        while ($current) {
            array_unshift($pathIds, $current->id);
            $current = $current->parent;
        }

        return $pathIds;
    }

    private function getSiblingsCount(Taxonomy $taxonomy): int
    {
        if (!$taxonomy->parent_id) {
            return Taxonomy::whereNull('parent_id')
                ->where('id', '!=', $taxonomy->id)
                ->where('type', $taxonomy->type)
                ->count();
        }

        return $taxonomy->parent->children()
            ->where('id', '!=', $taxonomy->id)
            ->count();
    }

    private function findRoot(Taxonomy $taxonomy): Taxonomy
    {
        $current = $taxonomy;

        while ($current->parent) {
            $current = $current->parent;
        }

        return $current;
    }
}
```

## 10. Testing Strategies

### 10.1. Transformer Testing

Comprehensive testing strategies for taxonomy-aware transformers:

```php
<?php

namespace Tests\Unit\Transformers;

use Tests\TestCase;
use App\Transformers\ChinookTrackTransformer;
use App\Models\ChinookTrack;
use App\Models\ChinookAlbum;
use App\Models\ChinookArtist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

class ChinookTrackTransformerTest extends TestCase
{
    use RefreshDatabase;

    private Manager $fractal;
    private ChinookTrackTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fractal = new Manager();
        $this->transformer = new ChinookTrackTransformer();
    }

    public function test_transforms_track_basic_data(): void
    {
        $artist = ChinookArtist::factory()->create(['name' => 'Queen']);
        $album = ChinookAlbum::factory()->create([
            'title' => 'A Night at the Opera',
            'artist_id' => $artist->id,
        ]);

        $track = ChinookTrack::factory()->create([
            'name' => 'Bohemian Rhapsody',
            'album_id' => $album->id,
            'milliseconds' => 355000,
            'bytes' => 5600000,
            'unit_price' => 0.99,
            'composer' => 'Freddie Mercury',
        ]);

        $resource = new Item($track, $this->transformer);
        $result = $this->fractal->createData($resource)->toArray();

        $this->assertEquals('Bohemian Rhapsody', $result['data']['name']);
        $this->assertEquals('Freddie Mercury', $result['data']['composer']);
        $this->assertEquals(355000, $result['data']['milliseconds']);
        $this->assertEquals('5:55', $result['data']['duration_formatted']);
        $this->assertEquals('5.34 MB', $result['data']['file_size_formatted']);
        $this->assertEquals('$0.99', $result['data']['price_formatted']);
    }

    public function test_includes_taxonomy_relationships(): void
    {
        $track = ChinookTrack::factory()->create();

        $rockGenre = Taxonomy::create([
            'name' => 'Rock',
            'type' => 'genre',
        ]);

        $progressiveRock = Taxonomy::create([
            'name' => 'Progressive Rock',
            'type' => 'genre',
            'parent_id' => $rockGenre->id,
        ]);

        $track->taxonomies()->attach([$rockGenre->id, $progressiveRock->id]);

        $this->fractal->parseIncludes('genres');
        $resource = new Item($track, $this->transformer);
        $result = $this->fractal->createData($resource)->toArray();

        $this->assertArrayHasKey('genres', $result['data']);
        $this->assertCount(2, $result['data']['genres']['data']);

        $genreNames = collect($result['data']['genres']['data'])->pluck('name')->toArray();
        $this->assertContains('Rock', $genreNames);
        $this->assertContains('Progressive Rock', $genreNames);
    }

    public function test_handles_nested_includes(): void
    {
        $artist = ChinookArtist::factory()->create();
        $album = ChinookAlbum::factory()->create(['artist_id' => $artist->id]);
        $track = ChinookTrack::factory()->create(['album_id' => $album->id]);

        $this->fractal->parseIncludes('album.artist');
        $resource = new Item($track, $this->transformer);
        $result = $this->fractal->createData($resource)->toArray();

        $this->assertArrayHasKey('album', $result['data']);
        $this->assertArrayHasKey('artist', $result['data']['album']['data']);
        $this->assertEquals($artist->name, $result['data']['album']['data']['artist']['data']['name']);
    }
}
```

### 10.2. API Response Testing

Test complete API responses with taxonomy integration:

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ChinookTrack;
use App\Models\ChinookAlbum;
use App\Models\ChinookArtist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChinookTrackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_track_with_taxonomy_data(): void
    {
        $artist = ChinookArtist::factory()->create(['name' => 'Led Zeppelin']);
        $album = ChinookAlbum::factory()->create([
            'title' => 'Led Zeppelin IV',
            'artist_id' => $artist->id,
        ]);

        $rockGenre = Taxonomy::create([
            'name' => 'Rock',
            'type' => 'genre',
        ]);

        $hardRock = Taxonomy::create([
            'name' => 'Hard Rock',
            'type' => 'genre',
            'parent_id' => $rockGenre->id,
        ]);

        $track = ChinookTrack::factory()->create([
            'name' => 'Stairway to Heaven',
            'album_id' => $album->id,
            'milliseconds' => 482000,
        ]);

        $track->taxonomies()->attach([$rockGenre->id, $hardRock->id]);

        $response = $this->getJson("/api/tracks/{$track->id}?include=genres,album.artist");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'milliseconds',
                    'duration_formatted',
                    'genre_primary',
                    'genres' => [
                        'data' => [
                            '*' => [
                                'id',
                                'name',
                                'type',
                                'full_path',
                                'depth',
                            ]
                        ]
                    ],
                    'album' => [
                        'data' => [
                            'title',
                            'artist' => [
                                'data' => [
                                    'name',
                                ]
                            ]
                        ]
                    ]
                ]
            ])
            ->assertJsonPath('data.name', 'Stairway to Heaven')
            ->assertJsonPath('data.album.data.title', 'Led Zeppelin IV')
            ->assertJsonPath('data.album.data.artist.data.name', 'Led Zeppelin');

        // Verify taxonomy hierarchy
        $genres = $response->json('data.genres.data');
        $this->assertCount(2, $genres);

        $hardRockGenre = collect($genres)->firstWhere('name', 'Hard Rock');
        $this->assertEquals('Rock > Hard Rock', $hardRockGenre['full_path']);
        $this->assertEquals(1, $hardRockGenre['depth']);
    }

    public function test_api_filters_tracks_by_taxonomy_hierarchy(): void
    {
        // Create genre hierarchy
        $rock = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
        $hardRock = Taxonomy::create([
            'name' => 'Hard Rock',
            'type' => 'genre',
            'parent_id' => $rock->id,
        ]);
        $metal = Taxonomy::create([
            'name' => 'Heavy Metal',
            'type' => 'genre',
            'parent_id' => $hardRock->id,
        ]);

        // Create tracks with different genres
        $track1 = ChinookTrack::factory()->create(['name' => 'Rock Song']);
        $track1->taxonomies()->attach($rock);

        $track2 = ChinookTrack::factory()->create(['name' => 'Hard Rock Song']);
        $track2->taxonomies()->attach($hardRock);

        $track3 = ChinookTrack::factory()->create(['name' => 'Metal Song']);
        $track3->taxonomies()->attach($metal);

        // Test filtering by parent genre (should include children)
        $response = $this->getJson("/api/tracks?filter[genre_ids][]={$rock->id}&filter[include_children]=true");

        $response->assertOk();
        $tracks = $response->json('data');
        $this->assertCount(3, $tracks); // All three tracks should be included

        // Test filtering without children
        $response = $this->getJson("/api/tracks?filter[genre_ids][]={$rock->id}&filter[include_children]=false");

        $response->assertOk();
        $tracks = $response->json('data');
        $this->assertCount(1, $tracks); // Only the direct Rock track
    }
}
```

## 11. Best Practices

### 11.1. Security Considerations

Implement security best practices for taxonomy-aware APIs:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Transformers\ChinookTrackTransformer;
use App\Models\ChinookTrack;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Fractal\Fractal;

class ChinookTrackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Validate and sanitize includes
        $allowedIncludes = ['album', 'artist', 'genres', 'media_type'];
        $includes = $this->validateIncludes($request->get('include', ''), $allowedIncludes);

        // Validate taxonomy filters
        $genreIds = $this->validateTaxonomyIds($request->get('filter.genre_ids', []));

        $query = ChinookTrack::query();

        // Apply taxonomy filters securely
        if (!empty($genreIds)) {
            $query->whereHas('taxonomies', function ($q) use ($genreIds) {
                $q->whereIn('taxonomy_id', $genreIds)
                  ->where('type', 'genre'); // Ensure only genre taxonomies
            });
        }

        $tracks = $query->paginate(15);

        return Fractal::collection($tracks, new ChinookTrackTransformer)
            ->parseIncludes($includes)
            ->respond();
    }

    public function show(Request $request, ChinookTrack $track): JsonResponse
    {
        $allowedIncludes = ['album', 'artist', 'genres', 'media_type', 'playlists'];
        $includes = $this->validateIncludes($request->get('include', ''), $allowedIncludes);

        return Fractal::item($track, new ChinookTrackTransformer)
            ->parseIncludes($includes)
            ->respond();
    }

    private function validateIncludes(string $includes, array $allowed): string
    {
        $requestedIncludes = explode(',', $includes);
        $validIncludes = [];

        foreach ($requestedIncludes as $include) {
            $include = trim($include);

            // Handle nested includes (e.g., 'album.artist')
            $basePath = explode('.', $include)[0];

            if (in_array($basePath, $allowed)) {
                $validIncludes[] = $include;
            }
        }

        return implode(',', $validIncludes);
    }

    private function validateTaxonomyIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }
}
```

### 11.2. Error Handling

Implement comprehensive error handling for taxonomy operations:

```php
<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class TaxonomyTransformationException extends Exception
{
    public static function invalidHierarchy(string $taxonomyName): self
    {
        return new self("Invalid taxonomy hierarchy detected for: {$taxonomyName}");
    }

    public static function circularReference(string $taxonomyName): self
    {
        return new self("Circular reference detected in taxonomy: {$taxonomyName}");
    }

    public static function missingParent(int $parentId): self
    {
        return new self("Parent taxonomy not found: {$parentId}");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'type' => 'taxonomy_transformation_error',
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ]
        ], 422);
    }
}

// Usage in transformer
class TaxonomyTransformer extends BaseTransformer
{
    public function transform(Taxonomy $taxonomy): array
    {
        try {
            // Validate taxonomy hierarchy
            $this->validateHierarchy($taxonomy);

            return $this->transformTaxonomyHierarchy($taxonomy);
        } catch (Exception $e) {
            throw TaxonomyTransformationException::invalidHierarchy($taxonomy->name);
        }
    }

    private function validateHierarchy(Taxonomy $taxonomy): void
    {
        // Check for circular references
        $visited = [];
        $current = $taxonomy;

        while ($current && $current->parent_id) {
            if (in_array($current->id, $visited)) {
                throw TaxonomyTransformationException::circularReference($taxonomy->name);
            }

            $visited[] = $current->id;
            $current = $current->parent;

            if (!$current && $current->parent_id) {
                throw TaxonomyTransformationException::missingParent($current->parent_id);
            }
        }
    }
}
```

---

**Refactored from:** `.ai/guides/chinook/packages/070-laravel-fractal-guide.md` on 2025-07-11

## Navigation

**← Previous:** [Laravel Data Guide](060-laravel-data-guide.md)

**Next →** [Laravel Sanctum Guide](080-laravel-sanctum-guide.md)

[⬆️ Back to Top](#1-laravel-fractal-implementation-guide)
