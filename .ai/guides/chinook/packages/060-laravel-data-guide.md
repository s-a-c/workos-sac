# 1. Laravel Data Implementation Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Installation & Setup](#2-installation--setup)
  - [2.1. Package Installation](#21-package-installation)
  - [2.2. Configuration Publishing](#22-configuration-publishing)
  - [2.3. Basic Setup](#23-basic-setup)
- [3. Data Transfer Objects](#3-data-transfer-objects)
  - [3.1. Basic DTO Creation](#31-basic-dto-creation)
  - [3.2. Advanced DTO Features](#32-advanced-dto-features)
  - [3.3. Nested DTOs](#33-nested-dtos)
- [4. Validation & Transformation](#4-validation--transformation)
  - [4.1. Built-in Validation](#41-built-in-validation)
  - [4.2. Custom Validation Rules](#42-custom-validation-rules)
  - [4.3. Data Transformation](#43-data-transformation)
- [8. Taxonomy Integration](#8-taxonomy-integration)
  - [8.1. Taxonomy Data Objects](#81-taxonomy-data-objects)
  - [8.2. Chinook Taxonomy Examples](#82-chinook-taxonomy-examples)
  - [8.3. Advanced Taxonomy Patterns](#83-advanced-taxonomy-patterns)
- [9. Testing Strategies](#9-testing-strategies)
  - [9.1. Unit Testing Data Objects](#91-unit-testing-data-objects)
  - [9.2. Integration Testing](#92-integration-testing)
- [Navigation](#navigation)

## 1. Overview

Laravel Data provides type-safe data transfer objects with built-in validation, transformation, and serialization capabilities. This guide covers enterprise-level implementation with API integration, performance optimization, comprehensive testing strategies, and **exclusive integration with aliziodev/laravel-taxonomy** for the Chinook music database system.

**🚀 Key Features:**
- **Type Safety**: Full PHP type system integration with strict typing
- **Automatic Validation**: Built-in validation with custom rule support
- **API Resource Integration**: Seamless JSON API response generation
- **Data Transformation**: Flexible casting and transformation pipelines
- **Collection Support**: Efficient handling of data collections and arrays
- **Performance Optimization**: Caching and lazy loading capabilities
- **Taxonomy Integration**: Native support for aliziodev/laravel-taxonomy relationships

**🎵 Chinook Integration Benefits:**
- **Unified Data Layer**: Consistent DTOs across all Chinook entities (Artists, Albums, Tracks, Genres)
- **Taxonomy-Aware DTOs**: Built-in support for hierarchical genre classification
- **Performance Optimized**: Lazy loading for complex relationships and taxonomy trees
- **Type-Safe APIs**: Strongly typed API responses with taxonomy metadata

## 2. Installation & Setup

### 2.1. Package Installation

Install Laravel Data using Composer:

```bash
# Install Laravel Data
composer require spatie/laravel-data

# Publish configuration (optional)
php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag="data-config"

# Verify installation
php artisan data:check
```

**System Requirements:**

- PHP 8.1 or higher
- Laravel 9.0 or higher
- Composer 2.0 or higher

### 2.2. Configuration Publishing

Configure Laravel Data for your application:

```php
// config/data.php
return [
    /*
     * The package will use this format to transform and cast dates.
     * This can be overridden in specific data classes.
     */
    'date_format' => 'Y-m-d H:i:s',

    /*
     * Global transformers will take complex types and transform them into simple types.
     */
    'transformers' => [
        DateTimeInterface::class => \Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class,
        \Illuminate\Contracts\Support\Arrayable::class => \Spatie\LaravelData\Transformers\ArrayableTransformer::class,
        BackedEnum::class => \Spatie\LaravelData\Transformers\EnumTransformer::class,
    ],

    /*
     * Global casts will cast values into complex types when creating a data object from simple types.
     */
    'casts' => [
        DateTimeInterface::class => \Spatie\LaravelData\Casts\DateTimeInterfaceCast::class,
        BackedEnum::class => \Spatie\LaravelData\Casts\EnumCast::class,
    ],

    /*
     * Rule inferrers can be configured here. They will automatically add
     * validation rules to properties of a data object based upon
     * the type of the property.
     */
    'rule_inferrers' => [
        \Spatie\LaravelData\RuleInferrers\SometimesRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\NullableRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\RequiredRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\BuiltInTypesRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\AttributesRuleInferrer::class,
    ],

    /*
     * Normalizers return an array representation of the payload, or null if
     * it cannot normalize the payload. The normalizers below are used for
     * every data object, unless overridden in a specific data class.
     */
    'normalizers' => [
        \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
        \Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
        \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
        \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
        \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
        \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
    ],

    /*
     * Data objects can be wrapped into a key like 'data' when used as a resource,
     * this key can be set globally here for all data objects. You can pass
     * `null` if you want to disable wrapping.
     */
    'wrap' => null,

    /*
     * Adds a specific caster to the Symphony VarDumper component which hides some
     * properties from data objects and collections when being dumped by `dump` or `dd`.
     * Can be 'enabled', 'disabled' or 'development_only'.
     */
    'var_dumper_caster_mode' => 'development_only',
];
```

### 2.3. Basic Setup

Create your first Data object with taxonomy integration:

```php
// app/Data/ChinookArtistData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\DataCollection;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Carbon\Carbon;

class ChinookArtistData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $name,
        
        public ?string $bio = null,
        
        public ?int $formed_year = null,
        
        /** @var DataCollection<TaxonomyData> */
        public ?DataCollection $genres = null,
        
        public ?Carbon $created_at = null,
        
        public ?Carbon $updated_at = null,
    ) {}
    
    public static function fromModel($model): static
    {
        return new static(
            name: $model->name,
            bio: $model->bio,
            formed_year: $model->formed_year,
            genres: $model->taxonomies ? TaxonomyData::collection($model->taxonomies) : null,
            created_at: $model->created_at,
            updated_at: $model->updated_at,
        );
    }
}
```

**Basic Usage:**

```php
// Creating from array
$artistData = ChinookArtistData::from([
    'name' => 'Led Zeppelin',
    'bio' => 'English rock band formed in London in 1968',
    'formed_year' => 1968,
]);

// Creating from request
$artistData = ChinookArtistData::from($request);

// Creating from model with taxonomy
$artistData = ChinookArtistData::fromModel($artist);

// Converting to array
$array = $artistData->toArray();

// Converting to JSON
$json = $artistData->toJson();
```

## 3. Data Transfer Objects

### 3.1. Basic DTO Creation

Create comprehensive DTOs for Chinook entities:

```php
// app/Data/ChinookTrackData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\DataCollection;
use Carbon\Carbon;

class ChinookTrackData extends Data
{
    public function __construct(
        #[Required, StringType, Min(1), Max(200)]
        public string $name,
        
        #[Required, Numeric]
        public int $album_id,
        
        #[Required, Numeric]
        public int $media_type_id,
        
        #[Numeric, Min(0)]
        public ?int $milliseconds = null,
        
        #[Numeric, Min(0)]
        public ?int $bytes = null,
        
        #[Numeric, Min(0)]
        public ?float $unit_price = null,
        
        public ?string $composer = null,
        
        /** @var DataCollection<TaxonomyData> */
        public ?DataCollection $genres = null,
        
        public ?ChinookAlbumData $album = null,
        
        public ?Carbon $created_at = null,
        
        public ?Carbon $updated_at = null,
    ) {}
    
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:200'],
            'album_id' => ['required', 'integer', 'exists:chinook_albums,id'],
            'media_type_id' => ['required', 'integer', 'exists:chinook_media_types,id'],
            'milliseconds' => ['nullable', 'integer', 'min:0'],
            'bytes' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'composer' => ['nullable', 'string', 'max:220'],
        ];
    }
    
    public function getDurationFormatted(): string
    {
        if (!$this->milliseconds) {
            return 'Unknown';
        }
        
        $seconds = $this->milliseconds / 1000;
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
    
    public function getFileSizeFormatted(): string
    {
        if (!$this->bytes) {
            return 'Unknown';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->bytes;
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    public function getPriceFormatted(): string
    {
        return $this->unit_price ? '$' . number_format($this->unit_price, 2) : 'Free';
    }
    
    public function hasGenre(string $genreName): bool
    {
        return $this->genres?->contains(fn(TaxonomyData $genre) => $genre->name === $genreName) ?? false;
    }
}
```

### 3.2. Advanced DTO Features

Implement advanced DTO features with taxonomy relationships:

```php
// app/Data/ChinookAlbumData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Carbon\Carbon;

class ChinookAlbumData extends Data
{
    public function __construct(
        #[Required, StringType, Min(1)]
        public string $title,

        #[Required, Numeric]
        public int $artist_id,

        public ?int $release_year = null,

        #[Computed]
        public int $tracks_count,

        #[Computed]
        public int $total_duration_ms,

        /** @var DataCollection<ChinookTrackData> */
        public ?DataCollection $tracks = null,

        /** @var DataCollection<TaxonomyData> */
        public ?DataCollection $genres = null,

        public ?ChinookArtistData $artist = null,

        public ?Carbon $created_at = null,

        public ?Carbon $updated_at = null,
    ) {}

    public static function fromModel($model): static
    {
        return new static(
            title: $model->title,
            artist_id: $model->artist_id,
            release_year: $model->release_year,
            tracks_count: $model->tracks_count ?? $model->tracks?->count() ?? 0,
            total_duration_ms: $model->total_duration_ms ?? $model->tracks?->sum('milliseconds') ?? 0,
            tracks: $model->tracks ? ChinookTrackData::collection($model->tracks) : null,
            genres: $model->taxonomies ? TaxonomyData::collection($model->taxonomies) : null,
            artist: $model->artist ? ChinookArtistData::fromModel($model->artist) : null,
            created_at: $model->created_at,
            updated_at: $model->updated_at,
        );
    }

    #[Computed]
    public function tracksCount(): int
    {
        return $this->tracks?->count() ?? 0;
    }

    #[Computed]
    public function totalDurationMs(): int
    {
        return $this->tracks?->sum(fn(ChinookTrackData $track) => $track->milliseconds ?? 0) ?? 0;
    }

    public function getTotalDurationFormatted(): string
    {
        $totalSeconds = $this->total_duration_ms / 1000;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getAverageTrackDuration(): float
    {
        if ($this->tracks_count === 0) {
            return 0;
        }

        return $this->total_duration_ms / $this->tracks_count;
    }

    public function getGenreDistribution(): array
    {
        if (!$this->tracks) {
            return [];
        }

        $genreCounts = [];

        foreach ($this->tracks as $track) {
            if ($track->genres) {
                foreach ($track->genres as $genre) {
                    $genreCounts[$genre->name] = ($genreCounts[$genre->name] ?? 0) + 1;
                }
            }
        }

        arsort($genreCounts);
        return $genreCounts;
    }

    public function getPrimaryGenre(): ?TaxonomyData
    {
        $distribution = $this->getGenreDistribution();

        if (empty($distribution)) {
            return null;
        }

        $primaryGenreName = array_key_first($distribution);

        return $this->genres?->first(fn(TaxonomyData $genre) => $genre->name === $primaryGenreName);
    }

    public function hasMultipleGenres(): bool
    {
        return count($this->getGenreDistribution()) > 1;
    }
}
```

### 3.3. Nested DTOs

Create complex nested DTO structures with taxonomy support:

```php
// app/Data/TaxonomyData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Computed;

class TaxonomyData extends Data
{
    public function __construct(
        public int $id,

        #[Required, StringType, Min(1), Max(255)]
        public string $name,

        #[StringType]
        public ?string $description = null,

        public ?int $parent_id = null,

        public ?string $slug = null,

        public ?array $meta = null,

        #[Computed]
        public int $depth,

        #[Computed]
        public string $full_path,

        /** @var DataCollection<TaxonomyData> */
        public ?DataCollection $children = null,

        public ?TaxonomyData $parent = null,
    ) {}

    public static function fromModel($model): static
    {
        return new static(
            id: $model->id,
            name: $model->name,
            description: $model->description,
            parent_id: $model->parent_id,
            slug: $model->slug,
            meta: $model->meta,
            depth: $model->depth ?? 0,
            full_path: $model->full_path ?? $model->name,
            children: $model->children ? static::collection($model->children) : null,
            parent: $model->parent ? static::fromModel($model->parent) : null,
        );
    }

    #[Computed]
    public function depth(): int
    {
        $depth = 0;
        $current = $this->parent;

        while ($current) {
            $depth++;
            $current = $current->parent;
        }

        return $depth;
    }

    #[Computed]
    public function fullPath(): string
    {
        $path = [$this->name];
        $current = $this->parent;

        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isLeaf(): bool
    {
        return $this->children === null || $this->children->isEmpty();
    }

    public function hasChildren(): bool
    {
        return !$this->isLeaf();
    }

    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }

    public function getDescendants(): DataCollection
    {
        $descendants = collect();

        if ($this->children) {
            foreach ($this->children as $child) {
                $descendants->push($child);
                $descendants = $descendants->merge($child->getDescendants());
            }
        }

        return new DataCollection(TaxonomyData::class, $descendants->toArray());
    }

    public function isAncestorOf(TaxonomyData $taxonomy): bool
    {
        return in_array($this->id, $taxonomy->getAncestors()->pluck('id')->toArray());
    }

    public function isDescendantOf(TaxonomyData $taxonomy): bool
    {
        return $taxonomy->isAncestorOf($this);
    }
}
```

## 4. Validation & Transformation

### 4.1. Built-in Validation

Leverage built-in validation attributes with taxonomy constraints:

```php
// app/Data/ChinookPlaylistData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Carbon\Carbon;

class ChinookPlaylistData extends Data
{
    public function __construct(
        #[Required, StringType, Min(1), Max(120), Unique('chinook_playlists', 'name')]
        public string $name,

        #[StringType, Max(500)]
        public ?string $description = null,

        public bool $is_public = true,

        public ?int $user_id = null,

        /** @var DataCollection<ChinookTrackData> */
        public ?DataCollection $tracks = null,

        /** @var DataCollection<TaxonomyData> */
        public ?DataCollection $genres = null,

        public ?Carbon $created_at = null,

        public ?Carbon $updated_at = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120', 'unique:chinook_playlists,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['boolean'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function getTracksCount(): int
    {
        return $this->tracks?->count() ?? 0;
    }

    public function getTotalDuration(): int
    {
        return $this->tracks?->sum(fn(ChinookTrackData $track) => $track->milliseconds ?? 0) ?? 0;
    }

    public function getGenreDistribution(): array
    {
        if (!$this->tracks) {
            return [];
        }

        $genreCounts = [];

        foreach ($this->tracks as $track) {
            if ($track->genres) {
                foreach ($track->genres as $genre) {
                    $genreCounts[$genre->name] = ($genreCounts[$genre->name] ?? 0) + 1;
                }
            }
        }

        arsort($genreCounts);
        return $genreCounts;
    }

    public function getDominantGenres(int $limit = 3): array
    {
        $distribution = $this->getGenreDistribution();
        return array_slice($distribution, 0, $limit, true);
    }

    public function hasGenreDiversity(): bool
    {
        return count($this->getGenreDistribution()) >= 3;
    }
}
```

### 4.2. Custom Validation Rules

Create custom validation rules for taxonomy relationships:

```php
// app/Rules/ValidTaxonomyRule.php
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class ValidTaxonomyRule implements Rule
{
    private string $taxonomyType;
    private bool $allowChildren;

    public function __construct(string $taxonomyType = 'genre', bool $allowChildren = true)
    {
        $this->taxonomyType = $taxonomyType;
        $this->allowChildren = $allowChildren;
    }

    public function passes($attribute, $value): bool
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $taxonomyId) {
            $taxonomy = Taxonomy::find($taxonomyId);

            if (!$taxonomy) {
                return false;
            }

            // Check if taxonomy is of correct type
            if ($taxonomy->type !== $this->taxonomyType) {
                return false;
            }

            // Check if children are allowed
            if (!$this->allowChildren && $taxonomy->children()->exists()) {
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        $message = "The :attribute must contain valid {$this->taxonomyType} taxonomies.";

        if (!$this->allowChildren) {
            $message .= " Parent taxonomies are not allowed.";
        }

        return $message;
    }
}

// app/Data/ChinookGenreFilterData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use App\Rules\ValidTaxonomyRule;

class ChinookGenreFilterData extends Data
{
    public function __construct(
        #[Required, Rule(ValidTaxonomyRule::class, 'genre', true)]
        public array $genre_ids,

        public bool $include_children = true,

        public bool $exact_match = false,
    ) {}

    public static function rules(): array
    {
        return [
            'genre_ids' => ['required', 'array', new ValidTaxonomyRule('genre', true)],
            'genre_ids.*' => ['integer'],
            'include_children' => ['boolean'],
            'exact_match' => ['boolean'],
        ];
    }

    public function getExpandedGenreIds(): array
    {
        if (!$this->include_children) {
            return $this->genre_ids;
        }

        $expandedIds = $this->genre_ids;

        foreach ($this->genre_ids as $genreId) {
            $taxonomy = Taxonomy::find($genreId);
            if ($taxonomy) {
                $childIds = $taxonomy->descendants()->pluck('id')->toArray();
                $expandedIds = array_merge($expandedIds, $childIds);
            }
        }

        return array_unique($expandedIds);
    }
}
```

### 4.3. Data Transformation

Implement custom data transformation for taxonomy hierarchies:

```php
// app/Transformers/TaxonomyHierarchyTransformer.php
<?php

namespace App\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Transformers\Transformer;
use App\Data\TaxonomyData;

class TaxonomyHierarchyTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value): mixed
    {
        if ($value instanceof TaxonomyData) {
            return [
                'id' => $value->id,
                'name' => $value->name,
                'slug' => $value->slug,
                'depth' => $value->depth,
                'full_path' => $value->full_path,
                'is_root' => $value->isRoot(),
                'is_leaf' => $value->isLeaf(),
                'children_count' => $value->children?->count() ?? 0,
                'hierarchy' => [
                    'ancestors' => $value->getAncestors(),
                    'level' => $value->depth,
                    'position' => $value->parent_id ? 'child' : 'root',
                ],
            ];
        }

        return $value;
    }
}

// app/Casts/TaxonomyHierarchyCast.php
<?php

namespace App\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\DataProperty;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use App\Data\TaxonomyData;

class TaxonomyHierarchyCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $context): mixed
    {
        if (is_array($value) && isset($value['id'])) {
            $taxonomy = Taxonomy::with(['parent', 'children'])->find($value['id']);
            return $taxonomy ? TaxonomyData::fromModel($taxonomy) : null;
        }

        if (is_numeric($value)) {
            $taxonomy = Taxonomy::with(['parent', 'children'])->find($value);
            return $taxonomy ? TaxonomyData::fromModel($taxonomy) : null;
        }

        if ($value instanceof Taxonomy) {
            return TaxonomyData::fromModel($value);
        }

        return $value;
    }
}
```

## 8. Taxonomy Integration

### 8.1. Taxonomy Data Objects

Specialized Data objects for taxonomy operations:

```php
// app/Data/ChinookGenreAnalyticsData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Computed;

class ChinookGenreAnalyticsData extends Data
{
    public function __construct(
        public TaxonomyData $genre,

        #[Computed]
        public int $tracks_count,

        #[Computed]
        public int $albums_count,

        #[Computed]
        public int $artists_count,

        #[Computed]
        public float $average_track_duration,

        #[Computed]
        public float $total_revenue,

        /** @var DataCollection<TaxonomyData> */
        public ?DataCollection $related_genres = null,

        /** @var DataCollection<ChinookArtistData> */
        public ?DataCollection $top_artists = null,
    ) {}

    #[Computed]
    public function tracksCount(): int
    {
        return $this->genre->tracks()->count();
    }

    #[Computed]
    public function albumsCount(): int
    {
        return $this->genre->albums()->distinct()->count();
    }

    #[Computed]
    public function artistsCount(): int
    {
        return $this->genre->artists()->distinct()->count();
    }

    #[Computed]
    public function averageTrackDuration(): float
    {
        return $this->genre->tracks()->avg('milliseconds') ?? 0;
    }

    #[Computed]
    public function totalRevenue(): float
    {
        return $this->genre->tracks()->sum('unit_price') ?? 0;
    }

    public function getPopularityScore(): float
    {
        // Calculate popularity based on multiple factors
        $trackWeight = 0.4;
        $artistWeight = 0.3;
        $revenueWeight = 0.3;

        $maxTracks = 1000; // Normalize against maximum expected values
        $maxArtists = 100;
        $maxRevenue = 10000;

        $trackScore = min($this->tracks_count / $maxTracks, 1) * $trackWeight;
        $artistScore = min($this->artists_count / $maxArtists, 1) * $artistWeight;
        $revenueScore = min($this->total_revenue / $maxRevenue, 1) * $revenueWeight;

        return ($trackScore + $artistScore + $revenueScore) * 100;
    }

    public function getTrendDirection(): string
    {
        // This would typically compare with historical data
        // For now, we'll use a simple heuristic based on recent activity
        $recentTracks = $this->genre->tracks()
            ->where('created_at', '>=', now()->subMonths(3))
            ->count();

        $totalTracks = $this->tracks_count;

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
}
```

### 8.2. Chinook Taxonomy Examples

Real-world examples using Chinook data:

```php
// app/Data/ChinookMusicDiscoveryData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ChinookMusicDiscoveryData extends Data
{
    public function __construct(
        /** @var DataCollection<TaxonomyData> */
        public DataCollection $user_preferred_genres,

        /** @var DataCollection<ChinookTrackData> */
        public DataCollection $recommended_tracks,

        /** @var DataCollection<ChinookArtistData> */
        public DataCollection $similar_artists,

        /** @var DataCollection<TaxonomyData> */
        public DataCollection $genre_suggestions,

        public float $discovery_score,

        public array $recommendation_reasons,
    ) {}

    public static function generateForUser($user): static
    {
        // Get user's listening history and preferred genres
        $userGenres = $user->preferredGenres(); // Returns taxonomy collection

        // Find similar genres using taxonomy hierarchy
        $similarGenres = collect();
        foreach ($userGenres as $genre) {
            $siblings = $genre->parent?->children ?? collect();
            $similarGenres = $similarGenres->merge($siblings);
        }

        // Get recommendations based on taxonomy relationships
        $recommendedTracks = ChinookTrackData::collection(
            Track::whereHas('taxonomies', function ($query) use ($similarGenres) {
                $query->whereIn('taxonomy_id', $similarGenres->pluck('id'));
            })->limit(20)->get()
        );

        return new static(
            user_preferred_genres: TaxonomyData::collection($userGenres),
            recommended_tracks: $recommendedTracks,
            similar_artists: ChinookArtistData::collection(collect()), // Implementation details
            genre_suggestions: TaxonomyData::collection($similarGenres),
            discovery_score: 0.85, // Calculated based on diversity and relevance
            recommendation_reasons: [
                'Based on your love for Rock music',
                'Similar to artists you\'ve liked',
                'Popular in your preferred genres',
            ],
        );
    }

    public function getGenreDiversityScore(): float
    {
        $totalGenres = $this->user_preferred_genres->count();
        $uniqueParents = $this->user_preferred_genres
            ->filter(fn($genre) => $genre->parent_id !== null)
            ->groupBy('parent_id')
            ->count();

        return $totalGenres > 0 ? $uniqueParents / $totalGenres : 0;
    }

    public function getRecommendationStrength(): string
    {
        if ($this->discovery_score >= 0.8) {
            return 'strong';
        } elseif ($this->discovery_score >= 0.6) {
            return 'moderate';
        } else {
            return 'weak';
        }
    }
}
```

### 8.3. Advanced Taxonomy Patterns

Complex taxonomy operations with Data objects:

```php
// app/Data/ChinookTaxonomyMigrationData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ChinookTaxonomyMigrationData extends Data
{
    public function __construct(
        /** @var DataCollection<TaxonomyData> */
        public DataCollection $source_taxonomies,

        /** @var DataCollection<TaxonomyData> */
        public DataCollection $target_taxonomies,

        public array $mapping_rules,

        public array $conflict_resolutions,

        public int $affected_tracks_count,

        public int $affected_albums_count,

        public int $affected_artists_count,

        public bool $preserve_hierarchy,

        public bool $merge_duplicates,
    ) {}

    public function generateMigrationPlan(): array
    {
        $plan = [
            'steps' => [],
            'warnings' => [],
            'estimated_duration' => 0,
        ];

        // Analyze taxonomy relationships
        foreach ($this->source_taxonomies as $sourceTaxonomy) {
            $targetTaxonomy = $this->findTargetMapping($sourceTaxonomy);

            if (!$targetTaxonomy) {
                $plan['warnings'][] = "No mapping found for: {$sourceTaxonomy->name}";
                continue;
            }

            $plan['steps'][] = [
                'action' => 'migrate',
                'source' => $sourceTaxonomy->name,
                'target' => $targetTaxonomy->name,
                'affected_entities' => $this->getAffectedEntities($sourceTaxonomy),
            ];
        }

        // Estimate duration based on affected entities
        $totalEntities = $this->affected_tracks_count +
                        $this->affected_albums_count +
                        $this->affected_artists_count;

        $plan['estimated_duration'] = ceil($totalEntities / 1000) * 5; // 5 minutes per 1000 entities

        return $plan;
    }

    private function findTargetMapping(TaxonomyData $source): ?TaxonomyData
    {
        foreach ($this->mapping_rules as $rule) {
            if ($rule['source_id'] === $source->id) {
                return $this->target_taxonomies->first(fn($t) => $t->id === $rule['target_id']);
            }
        }

        return null;
    }

    private function getAffectedEntities(TaxonomyData $taxonomy): array
    {
        return [
            'tracks' => $taxonomy->tracks()->count(),
            'albums' => $taxonomy->albums()->count(),
            'artists' => $taxonomy->artists()->count(),
        ];
    }

    public function validateMigration(): array
    {
        $issues = [];

        // Check for circular dependencies
        foreach ($this->target_taxonomies as $taxonomy) {
            if ($this->hasCircularDependency($taxonomy)) {
                $issues[] = "Circular dependency detected in: {$taxonomy->name}";
            }
        }

        // Check for orphaned taxonomies
        foreach ($this->source_taxonomies as $taxonomy) {
            if (!$this->findTargetMapping($taxonomy)) {
                $issues[] = "Orphaned taxonomy: {$taxonomy->name}";
            }
        }

        return $issues;
    }

    private function hasCircularDependency(TaxonomyData $taxonomy, array $visited = []): bool
    {
        if (in_array($taxonomy->id, $visited)) {
            return true;
        }

        $visited[] = $taxonomy->id;

        if ($taxonomy->parent) {
            return $this->hasCircularDependency($taxonomy->parent, $visited);
        }

        return false;
    }
}
```

## 9. Testing Strategies

### 9.1. Unit Testing Data Objects

Comprehensive testing strategies for taxonomy-aware Data objects:

```php
<?php

namespace Tests\Unit\Data;

use Tests\TestCase;
use App\Data\ChinookTrackData;
use App\Data\TaxonomyData;
use Spatie\LaravelData\Exceptions\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChinookTrackDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_track_data_from_array(): void
    {
        $data = ChinookTrackData::from([
            'name' => 'Bohemian Rhapsody',
            'album_id' => 1,
            'media_type_id' => 1,
            'milliseconds' => 355000,
            'bytes' => 5600000,
            'unit_price' => 0.99,
            'composer' => 'Freddie Mercury',
        ]);

        $this->assertEquals('Bohemian Rhapsody', $data->name);
        $this->assertEquals(1, $data->album_id);
        $this->assertEquals(355000, $data->milliseconds);
        $this->assertEquals(0.99, $data->unit_price);
    }

    public function test_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        ChinookTrackData::from([
            'album_id' => 1,
            'media_type_id' => 1,
            // Missing required 'name' field
        ]);
    }

    public function test_formats_duration_correctly(): void
    {
        $data = ChinookTrackData::from([
            'name' => 'Test Track',
            'album_id' => 1,
            'media_type_id' => 1,
            'milliseconds' => 185000, // 3:05
        ]);

        $this->assertEquals('3:05', $data->getDurationFormatted());
    }

    public function test_formats_file_size_correctly(): void
    {
        $data = ChinookTrackData::from([
            'name' => 'Test Track',
            'album_id' => 1,
            'media_type_id' => 1,
            'bytes' => 5242880, // 5 MB
        ]);

        $this->assertEquals('5 MB', $data->getFileSizeFormatted());
    }

    public function test_checks_genre_membership(): void
    {
        $rockGenre = TaxonomyData::from([
            'id' => 1,
            'name' => 'Rock',
            'depth' => 0,
            'full_path' => 'Rock',
        ]);

        $data = ChinookTrackData::from([
            'name' => 'Test Track',
            'album_id' => 1,
            'media_type_id' => 1,
            'genres' => [$rockGenre],
        ]);

        $this->assertTrue($data->hasGenre('Rock'));
        $this->assertFalse($data->hasGenre('Jazz'));
    }
}
```

### 9.2. Integration Testing

Test Data objects in API contexts with taxonomy relationships:

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

    public function test_api_returns_track_data_with_taxonomy(): void
    {
        // Create test data
        $artist = ChinookArtist::factory()->create(['name' => 'Queen']);
        $album = ChinookAlbum::factory()->create([
            'title' => 'A Night at the Opera',
            'artist_id' => $artist->id,
        ]);

        $rockGenre = Taxonomy::create([
            'name' => 'Rock',
            'type' => 'genre',
        ]);

        $track = ChinookTrack::factory()->create([
            'name' => 'Bohemian Rhapsody',
            'album_id' => $album->id,
            'milliseconds' => 355000,
        ]);

        $track->taxonomies()->attach($rockGenre);

        $response = $this->getJson("/api/tracks/{$track->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'album_id',
                    'media_type_id',
                    'milliseconds',
                    'bytes',
                    'unit_price',
                    'composer',
                    'genres' => [
                        '*' => [
                            'id',
                            'name',
                            'depth',
                            'full_path',
                        ]
                    ],
                    'album' => [
                        'title',
                        'artist_id',
                    ],
                ]
            ])
            ->assertJsonPath('data.name', 'Bohemian Rhapsody')
            ->assertJsonPath('data.genres.0.name', 'Rock');
    }

    public function test_api_validates_track_creation_with_taxonomy(): void
    {
        $response = $this->postJson('/api/tracks', [
            'album_id' => 1,
            'media_type_id' => 1,
            'genre_ids' => [999], // Non-existent genre
            // Missing required 'name'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'genre_ids']);
    }

    public function test_api_filters_tracks_by_genre_hierarchy(): void
    {
        // Create genre hierarchy: Rock > Hard Rock > Heavy Metal
        $rock = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
        $hardRock = Taxonomy::create([
            'name' => 'Hard Rock',
            'type' => 'genre',
            'parent_id' => $rock->id,
        ]);
        $heavyMetal = Taxonomy::create([
            'name' => 'Heavy Metal',
            'type' => 'genre',
            'parent_id' => $hardRock->id,
        ]);

        // Create tracks with different genres
        $track1 = ChinookTrack::factory()->create(['name' => 'Rock Song']);
        $track1->taxonomies()->attach($rock);

        $track2 = ChinookTrack::factory()->create(['name' => 'Hard Rock Song']);
        $track2->taxonomies()->attach($hardRock);

        $track3 = ChinookTrack::factory()->create(['name' => 'Heavy Metal Song']);
        $track3->taxonomies()->attach($heavyMetal);

        // Test filtering by parent genre (should include children)
        $response = $this->getJson("/api/tracks?genre_ids[]={$rock->id}&include_children=true");

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // All three tracks should be included

        // Test filtering without children
        $response = $this->getJson("/api/tracks?genre_ids[]={$rock->id}&include_children=false");

        $response->assertOk()
            ->assertJsonCount(1, 'data'); // Only the direct Rock track
    }
}
```

## 10. Best Practices

### 10.1. Design Principles

Follow these principles when designing taxonomy-aware Data objects:

1. **Single Responsibility**: Each Data object should represent one concept with clear taxonomy relationships
2. **Immutability**: Prefer immutable Data objects for better predictability in taxonomy operations
3. **Type Safety**: Use strict typing and validation for taxonomy IDs and relationships
4. **Performance**: Consider caching and lazy loading for complex taxonomy hierarchies
5. **Consistency**: Maintain consistent taxonomy integration patterns across all Data objects

### 10.2. Naming Conventions

Consistent naming improves maintainability in taxonomy contexts:

```php
// Good: Clear, descriptive names with taxonomy context
class ChinookArtistGenreData extends Data { }
class ChinookAlbumTaxonomyData extends Data { }
class ChinookGenreAnalyticsData extends Data { }
class TaxonomyHierarchyData extends Data { }

// Avoid: Generic or unclear names
class DataObject extends Data { }
class GenreInfo extends Data { }
class TaxonomyStuff extends Data { }
```

### 10.3. Validation Strategy

Implement comprehensive validation for taxonomy relationships:

```php
class ChinookTrackData extends Data
{
    public function __construct(
        #[Required, Max(200)]
        public string $name,

        #[Required, Exists('chinook_albums', 'id')]
        public int $album_id,

        #[Sometimes, Rule(ValidTaxonomyRule::class, 'genre')]
        public ?array $genre_ids = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'album_id' => ['required', 'integer', 'exists:chinook_albums,id'],
            'genre_ids' => ['sometimes', 'array', new ValidTaxonomyRule('genre')],
            'genre_ids.*' => ['integer', 'exists:taxonomies,id'],
        ];
    }
}
```

## 11. Advanced Patterns

### 11.1. Polymorphic Data Objects

Handle different entity types with shared taxonomy relationships:

```php
abstract class ChinookTaxonomyAwareData extends Data
{
    abstract public function getTaxonomyType(): string;
    abstract public function getTaxonomyRelationships(): DataCollection;
}

class ChinookTrackTaxonomyData extends ChinookTaxonomyAwareData
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var DataCollection<TaxonomyData> */
        public DataCollection $genres,
    ) {}

    public function getTaxonomyType(): string
    {
        return 'track';
    }

    public function getTaxonomyRelationships(): DataCollection
    {
        return $this->genres;
    }
}

class ChinookArtistTaxonomyData extends ChinookTaxonomyAwareData
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var DataCollection<TaxonomyData> */
        public DataCollection $genres,
    ) {}

    public function getTaxonomyType(): string
    {
        return 'artist';
    }

    public function getTaxonomyRelationships(): DataCollection
    {
        return $this->genres;
    }
}
```

### 11.2. Data Pipelines

Create taxonomy-aware data processing pipelines:

```php
class TaxonomyDataPipeline
{
    private array $processors = [];

    public function addProcessor(callable $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }

    public function process(Data $data): Data
    {
        return array_reduce(
            $this->processors,
            fn($data, $processor) => $processor($data),
            $data
        );
    }
}

// Usage
$pipeline = (new TaxonomyDataPipeline())
    ->addProcessor(fn($data) => $data->withNormalizedGenres())
    ->addProcessor(fn($data) => $data->withHierarchicalValidation())
    ->addProcessor(fn($data) => $data->withEnrichedTaxonomyMetadata());

$processedData = $pipeline->process($rawTrackData);
```

### 11.3. Event-Driven Data Processing

Integrate with Laravel events for taxonomy operations:

```php
class ChinookTrackData extends Data
{
    public static function fromModel(ChinookTrack $track): self
    {
        $data = new self(
            name: $track->name,
            album_id: $track->album_id,
            genres: TaxonomyData::collection($track->taxonomies),
        );

        // Dispatch event for taxonomy analysis
        event(new TrackTaxonomyAnalyzed($data, $track));

        return $data;
    }
}

// Event listener
class UpdateGenreStatistics
{
    public function handle(TrackTaxonomyAnalyzed $event): void
    {
        // Update genre popularity statistics
        foreach ($event->data->genres as $genre) {
            $this->updateGenreMetrics($genre);
        }

        // Cache enriched taxonomy data
        Cache::put(
            "track.{$event->track->id}.taxonomy",
            $event->data->genres,
            3600
        );
    }
}
```

---

**Refactored from:** `.ai/guides/chinook/packages/060-laravel-data-guide.md` on 2025-07-11

## Navigation

**← Previous:** [Laravel Horizon Guide](050-laravel-horizon-guide.md)

**Next →** [Laravel Fractal Guide](070-laravel-fractal-guide.md)

[⬆️ Back to Top](#1-laravel-data-implementation-guide)
