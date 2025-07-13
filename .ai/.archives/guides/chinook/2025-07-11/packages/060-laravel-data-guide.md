# 1. Laravel Data Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/060-laravel-data-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Data Implementation Guide](#1-laravel-data-implementation-guide)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. Configuration Publishing](#122-configuration-publishing)
    - [1.2.3. Basic Setup](#123-basic-setup)
  - [1.3. Data Transfer Objects](#13-data-transfer-objects)
    - [1.3.1. Basic DTO Creation](#131-basic-dto-creation)
    - [1.3.2. Advanced DTO Features](#132-advanced-dto-features)
    - [1.3.3. Nested DTOs](#133-nested-dtos)
  - [1.4. Validation & Transformation](#14-validation--transformation)
    - [1.4.1. Built-in Validation](#141-built-in-validation)
    - [1.4.2. Custom Validation Rules](#142-custom-validation-rules)
    - [1.4.3. Data Transformation](#143-data-transformation)
  - [1.5. API Integration](#15-api-integration)
    - [1.5.1. API Resource Integration](#151-api-resource-integration)
    - [1.5.2. Request Handling](#152-request-handling)
    - [1.5.3. Response Formatting](#153-response-formatting)
  - [1.6. Collections & Arrays](#16-collections--arrays)
    - [1.6.1. Data Collections](#161-data-collections)
    - [1.6.2. Array Manipulation](#162-array-manipulation)
  - [1.7. Performance Optimization](#17-performance-optimization)
    - [1.7.1. Caching Strategies](#171-caching-strategies)
    - [1.7.2. Lazy Loading](#172-lazy-loading)
    - [1.7.3. Memory Optimization](#173-memory-optimization)
  - [1.8. Testing Strategies](#18-testing-strategies)
    - [1.8.1. Unit Testing Data Objects](#181-unit-testing-data-objects)
    - [1.8.2. Integration Testing](#182-integration-testing)
  - [1.9. Best Practices](#19-best-practices)
    - [1.9.1. Design Principles](#191-design-principles)
    - [1.9.2. Naming Conventions](#192-naming-conventions)
    - [1.9.3. Validation Strategy](#193-validation-strategy)
  - [1.10. Advanced Patterns](#110-advanced-patterns)
    - [1.10.1. Polymorphic Data Objects](#1101-polymorphic-data-objects)
    - [1.10.2. Data Pipelines](#1102-data-pipelines)
    - [1.10.3. Event-Driven Data Processing](#1103-event-driven-data-processing)
  - [1.11. Navigation](#111-navigation)

## 1.1. Overview

Laravel Data provides type-safe data transfer objects with built-in validation, transformation, and serialization capabilities. This guide covers enterprise-level implementation with API integration, performance optimization, and comprehensive testing strategies for the Chinook music store application.

**🚀 Key Features:**
- **Type Safety**: Full PHP type system integration with strict typing for Chinook data models
- **Automatic Validation**: Built-in validation with custom rule support for music catalog data
- **API Resource Integration**: Seamless JSON API response generation for Chinook endpoints
- **Data Transformation**: Flexible casting and transformation pipelines for music metadata
- **Collection Support**: Advanced collection handling for music catalogs and playlists
- **Performance Optimization**: Caching and lazy loading for large music datasets
- **Taxonomy Integration**: Type-safe handling of aliziodev/laravel-taxonomy data structures

**🎵 Chinook-Specific Benefits:**
- **Music Catalog DTOs**: Type-safe representation of tracks, albums, artists, and genres
- **Customer Data Handling**: Secure and validated customer information processing
- **Playlist Management**: Structured data objects for playlist creation and management
- **Invoice Processing**: Type-safe invoice and payment data handling
- **Search Results**: Optimized data structures for music discovery and filtering
- **Taxonomy Operations**: Seamless integration with taxonomy-based categorization

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Data for the Chinook application:

```bash
# Install Laravel Data
composer require spatie/laravel-data

# Publish configuration (optional)
php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag="data-config"

# Install additional packages for Chinook integration
composer require spatie/laravel-typescript-transformer # For TypeScript generation
composer require spatie/laravel-query-builder # For API filtering
```

**Verification Steps:**

```bash
# Verify installation
php artisan tinker
>>> use Spatie\LaravelData\Data;
>>> class TestData extends Data { public string $name; }
>>> TestData::from(['name' => 'test'])

# Expected output: TestData object with name property
```

### 1.2.2. Configuration Publishing

Configure Laravel Data for Chinook operations:

```php
// config/data.php
return [
    /*
     * The package will use this format when working with dates.
     */
    'date_format' => 'Y-m-d H:i:s',

    /*
     * Global transformers will take complex types and transform them into simple types.
     */
    'transformers' => [
        DateTimeInterface::class => \Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class,
        \Illuminate\Contracts\Support\Arrayable::class => \Spatie\LaravelData\Transformers\ArrayableTransformer::class,
        BackedEnum::class => Spatie\LaravelData\Transformers\EnumTransformer::class,
        
        // Chinook-specific transformers
        \App\Models\ChinookTrack::class => \App\Data\Transformers\ChinookTrackTransformer::class,
        \App\Models\ChinookAlbum::class => \App\Data\Transformers\ChinookAlbumTransformer::class,
        \App\Models\ChinookArtist::class => \App\Data\Transformers\ChinookArtistTransformer::class,
        \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class => \App\Data\Transformers\TaxonomyTransformer::class,
    ],

    /*
     * Global casts will cast values into complex types when creating a data object from simple types.
     */
    'casts' => [
        DateTimeInterface::class => Spatie\LaravelData\Casts\DateTimeInterfaceCast::class,
        BackedEnum::class => Spatie\LaravelData\Casts\EnumCast::class,
        
        // Chinook-specific casts
        'chinook_duration' => \App\Data\Casts\ChinookDurationCast::class,
        'chinook_price' => \App\Data\Casts\ChinookPriceCast::class,
        'chinook_file_size' => \App\Data\Casts\ChinookFileSizeCast::class,
    ],

    /*
     * Rule inferrers can be configured here. They will automatically add validation rules to properties of a data object based upon the type of the property.
     */
    'rule_inferrers' => [
        Spatie\LaravelData\RuleInferrers\SometimesRuleInferrer::class,
        Spatie\LaravelData\RuleInferrers\NullableRuleInferrer::class,
        Spatie\LaravelData\RuleInferrers\RequiredRuleInferrer::class,
        Spatie\LaravelData\RuleInferrers\BuiltInTypesRuleInferrer::class,
        Spatie\LaravelData\RuleInferrers\AttributesRuleInferrer::class,
        
        // Chinook-specific rule inferrers
        \App\Data\RuleInferrers\ChinookMusicFileRuleInferrer::class,
        \App\Data\RuleInferrers\ChinookCustomerRuleInferrer::class,
    ],

    /*
     * Normalizers return an array representation of the payload, or null if it cannot normalize the payload.
     */
    'normalizers' => [
        Spatie\LaravelData\Normalizers\ModelNormalizer::class,
        Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
        Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
        Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
        Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
        Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        
        // Chinook-specific normalizers
        \App\Data\Normalizers\ChinookModelNormalizer::class,
        \App\Data\Normalizers\TaxonomyNormalizer::class,
    ],

    /*
     * Data objects can be wrapped into a key like 'data' when used as a resource.
     */
    'wrap' => null,

    /*
     * Adds a specific caster to the Symphony VarDumper component which hides some properties from data objects and collections when dumping them.
     */
    'var_dumper_caster_mode' => 'development',
];
```

### 1.2.3. Basic Setup

Set up basic Chinook data structures:

```php
// app/Data/ChinookBaseData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;

abstract class ChinookBaseData extends Data
{
    #[Required, StringType]
    public string $public_id;

    #[WithCast('datetime')]
    public ?\DateTime $created_at;

    #[WithCast('datetime')]
    public ?\DateTime $updated_at;

    public function getRouteKey(): string
    {
        return $this->public_id;
    }

    public function getCacheKey(): string
    {
        return static::class . ':' . $this->public_id;
    }
}
```

**Environment Configuration:**

```bash
# .env additions for Chinook Data
CHINOOK_DATA_CACHE_TTL=3600
CHINOOK_DATA_VALIDATION_STRICT=true
CHINOOK_DATA_TRANSFORM_DATES=true
CHINOOK_DATA_INCLUDE_METADATA=false

# TypeScript generation settings
TYPESCRIPT_TRANSFORMER_OUTPUT_PATH=resources/js/types/chinook
TYPESCRIPT_TRANSFORMER_AUTO_DISCOVER_TYPES=true
```

## 1.3. Data Transfer Objects

### 1.3.1. Basic DTO Creation

Create basic DTOs for Chinook entities:

```php
// app/Data/ChinookTrackData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use App\Data\Casts\ChinookDurationCast;
use App\Data\Casts\ChinookPriceCast;
use App\Data\Casts\ChinookFileSizeCast;

class ChinookTrackData extends ChinookBaseData
{
    public function __construct(
        public string $public_id,

        #[Required, StringType]
        public string $name,

        #[Required]
        public ChinookAlbumData $album,

        #[Required]
        public ChinookGenreData $genre,

        public ?ChinookMediaTypeData $media_type,

        #[WithCast(ChinookDurationCast::class)]
        public ?int $milliseconds,

        #[WithCast(ChinookFileSizeCast::class)]
        public ?int $bytes,

        #[WithCast(ChinookPriceCast::class), Numeric, Min(0), Max(999.99)]
        public ?float $unit_price,

        public ?string $composer,

        /** @var TaxonomyData[] */
        public array $taxonomies = [],

        public ?\DateTime $created_at = null,
        public ?\DateTime $updated_at = null,
    ) {}

    public static function fromModel(\App\Models\ChinookTrack $track): self
    {
        return new self(
            public_id: $track->public_id,
            name: $track->name,
            album: ChinookAlbumData::fromModel($track->album),
            genre: ChinookGenreData::fromModel($track->genre),
            media_type: $track->mediaType ? ChinookMediaTypeData::fromModel($track->mediaType) : null,
            milliseconds: $track->milliseconds,
            bytes: $track->bytes,
            unit_price: $track->unit_price,
            composer: $track->composer,
            taxonomies: $track->taxonomies->map(fn($taxonomy) => TaxonomyData::fromModel($taxonomy))->toArray(),
            created_at: $track->created_at,
            updated_at: $track->updated_at,
        );
    }

    public function getDurationFormatted(): string
    {
        if (!$this->milliseconds) {
            return '0:00';
        }

        $seconds = intval($this->milliseconds / 1000);
        $minutes = intval($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->bytes;
        $unit = 0;

        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return round($bytes, 2) . ' ' . $units[$unit];
    }
}
```

```php
// app/Data/ChinookAlbumData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

class ChinookAlbumData extends ChinookBaseData
{
    public function __construct(
        public string $public_id,

        #[Required, StringType]
        public string $title,

        #[Required]
        public ChinookArtistData $artist,

        public ?string $artwork_url = null,
        public ?int $release_year = null,
        public ?int $track_count = null,

        /** @var TaxonomyData[] */
        public array $taxonomies = [],

        public ?\DateTime $created_at = null,
        public ?\DateTime $updated_at = null,
    ) {}

    public static function fromModel(\App\Models\ChinookAlbum $album): self
    {
        return new self(
            public_id: $album->public_id,
            title: $album->title,
            artist: ChinookArtistData::fromModel($album->artist),
            artwork_url: $album->artwork_url,
            release_year: $album->release_year,
            track_count: $album->tracks_count ?? $album->tracks->count(),
            taxonomies: $album->taxonomies->map(fn($taxonomy) => TaxonomyData::fromModel($taxonomy))->toArray(),
            created_at: $album->created_at,
            updated_at: $album->updated_at,
        );
    }
}
```

```php
// app/Data/ChinookArtistData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

class ChinookArtistData extends ChinookBaseData
{
    public function __construct(
        public string $public_id,

        #[Required, StringType]
        public string $name,

        public ?string $biography = null,
        public ?string $website_url = null,
        public ?int $album_count = null,
        public ?int $track_count = null,

        /** @var TaxonomyData[] */
        public array $taxonomies = [],

        public ?\DateTime $created_at = null,
        public ?\DateTime $updated_at = null,
    ) {}

    public static function fromModel(\App\Models\ChinookArtist $artist): self
    {
        return new self(
            public_id: $artist->public_id,
            name: $artist->name,
            biography: $artist->biography,
            website_url: $artist->website_url,
            album_count: $artist->albums_count ?? $artist->albums->count(),
            track_count: $artist->tracks_count,
            taxonomies: $artist->taxonomies->map(fn($taxonomy) => TaxonomyData::fromModel($taxonomy))->toArray(),
            created_at: $artist->created_at,
            updated_at: $artist->updated_at,
        );
    }
}
```

### 1.3.2. Advanced DTO Features

Implement advanced features for Chinook DTOs:

```php
// app/Data/ChinookCustomerData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Attributes\Computed;

class ChinookCustomerData extends ChinookBaseData
{
    public function __construct(
        public string $public_id,

        #[Required, StringType, Max(40)]
        public string $first_name,

        #[Required, StringType, Max(20)]
        public string $last_name,

        public ?string $company = null,

        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $postal_code = null,
        public ?string $phone = null,
        public ?string $fax = null,

        #[Required, Email]
        public string $email,

        #[Hidden] // Hide sensitive data from serialization
        public ?string $password_hash = null,

        public ?ChinookEmployeeData $support_rep = null,

        #[Computed] // Computed property
        public ?string $full_name = null,

        #[Computed]
        public ?string $display_location = null,

        public ?\DateTime $last_login_at = null,
        public ?\DateTime $created_at = null,
        public ?\DateTime $updated_at = null,
    ) {
        $this->full_name = trim($this->first_name . ' ' . $this->last_name);
        $this->display_location = $this->formatLocation();
    }

    public static function fromModel(\App\Models\ChinookCustomer $customer): self
    {
        return new self(
            public_id: $customer->public_id,
            first_name: $customer->first_name,
            last_name: $customer->last_name,
            company: $customer->company,
            address: $customer->address,
            city: $customer->city,
            state: $customer->state,
            country: $customer->country,
            postal_code: $customer->postal_code,
            phone: $customer->phone,
            fax: $customer->fax,
            email: $customer->email,
            password_hash: $customer->password,
            support_rep: $customer->supportRep ? ChinookEmployeeData::fromModel($customer->supportRep) : null,
            last_login_at: $customer->last_login_at,
            created_at: $customer->created_at,
            updated_at: $customer->updated_at,
        );
    }

    private function formatLocation(): ?string
    {
        $parts = array_filter([$this->city, $this->state, $this->country]);
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    public function getInitials(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }
}
```

### 1.3.3. Nested DTOs

Create complex nested DTOs for Chinook operations:

```php
// app/Data/ChinookPlaylistData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;

class ChinookPlaylistData extends ChinookBaseData
{
    public function __construct(
        public string $public_id,

        #[Required, StringType]
        public string $name,

        public ?string $description = null,

        public ?ChinookCustomerData $owner = null,

        #[DataCollectionOf(ChinookTrackData::class)]
        public DataCollection $tracks,

        public bool $is_public = false,
        public bool $is_collaborative = false,

        /** @var TaxonomyData[] */
        public array $taxonomies = [],

        public ?int $total_duration_ms = null,
        public ?int $track_count = null,

        public ?\DateTime $created_at = null,
        public ?\DateTime $updated_at = null,
    ) {}

    public static function fromModel(\App\Models\ChinookPlaylist $playlist): self
    {
        return new self(
            public_id: $playlist->public_id,
            name: $playlist->name,
            description: $playlist->description,
            owner: $playlist->customer ? ChinookCustomerData::fromModel($playlist->customer) : null,
            tracks: ChinookTrackData::collection($playlist->tracks),
            is_public: $playlist->is_public,
            is_collaborative: $playlist->is_collaborative,
            taxonomies: $playlist->taxonomies->map(fn($taxonomy) => TaxonomyData::fromModel($taxonomy))->toArray(),
            total_duration_ms: $playlist->tracks->sum('milliseconds'),
            track_count: $playlist->tracks->count(),
            created_at: $playlist->created_at,
            updated_at: $playlist->updated_at,
        );
    }

    public function getTotalDurationFormatted(): string
    {
        if (!$this->total_duration_ms) {
            return '0:00';
        }

        $seconds = intval($this->total_duration_ms / 1000);
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
```

```php
// app/Data/ChinookInvoiceData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\WithCast;
use App\Data\Casts\ChinookPriceCast;

class ChinookInvoiceData extends ChinookBaseData
{
    public function __construct(
        public string $public_id,

        #[Required]
        public ChinookCustomerData $customer,

        #[Required]
        public \DateTime $invoice_date,

        public ?string $billing_address = null,
        public ?string $billing_city = null,
        public ?string $billing_state = null,
        public ?string $billing_country = null,
        public ?string $billing_postal_code = null,

        #[WithCast(ChinookPriceCast::class), Required, Numeric, Min(0)]
        public float $total,

        #[DataCollectionOf(ChinookInvoiceLineData::class)]
        public DataCollection $invoice_lines,

        public ?string $payment_method = null,
        public ?string $payment_status = null,
        public ?\DateTime $paid_at = null,

        /** @var TaxonomyData[] */
        public array $taxonomies = [],

        public ?\DateTime $created_at = null,
        public ?\DateTime $updated_at = null,
    ) {}

    public static function fromModel(\App\Models\ChinookInvoice $invoice): self
    {
        return new self(
            public_id: $invoice->public_id,
            customer: ChinookCustomerData::fromModel($invoice->customer),
            invoice_date: $invoice->invoice_date,
            billing_address: $invoice->billing_address,
            billing_city: $invoice->billing_city,
            billing_state: $invoice->billing_state,
            billing_country: $invoice->billing_country,
            billing_postal_code: $invoice->billing_postal_code,
            total: $invoice->total,
            invoice_lines: ChinookInvoiceLineData::collection($invoice->invoiceLines),
            payment_method: $invoice->payment_method,
            payment_status: $invoice->payment_status,
            paid_at: $invoice->paid_at,
            taxonomies: $invoice->taxonomies->map(fn($taxonomy) => TaxonomyData::fromModel($taxonomy))->toArray(),
            created_at: $invoice->created_at,
            updated_at: $invoice->updated_at,
        );
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid' && $this->paid_at !== null;
    }

    public function getFormattedTotal(): string
    {
        return '$' . number_format($this->total, 2);
    }
}
```

## 1.4. Validation & Transformation

### 1.4.1. Built-in Validation

Implement comprehensive validation for Chinook data:

```php
// app/Data/Validation/ChinookTrackValidationData.php
<?php

namespace App\Data\Validation;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Exists;

class ChinookTrackValidationData extends Data
{
    public function __construct(
        #[Required, StringType, Max(200)]
        public string $name,

        #[Required, Exists('chinook_albums', 'public_id')]
        public string $album_id,

        #[Required, Exists('chinook_genres', 'public_id')]
        public string $genre_id,

        #[Exists('chinook_media_types', 'public_id')]
        public ?string $media_type_id = null,

        #[Numeric, Min(1), Max(86400000)] // Max 24 hours in milliseconds
        public ?int $milliseconds = null,

        #[Numeric, Min(1)]
        public ?int $bytes = null,

        #[Numeric, Min(0), Max(999.99)]
        public ?float $unit_price = null,

        #[StringType, Max(220)]
        public ?string $composer = null,

        #[In(['mp3', 'flac', 'wav', 'aac', 'm4a'])]
        public ?string $file_format = null,

        public array $taxonomy_ids = [],
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'album_id' => ['required', 'exists:chinook_albums,public_id'],
            'genre_id' => ['required', 'exists:chinook_genres,public_id'],
            'media_type_id' => ['nullable', 'exists:chinook_media_types,public_id'],
            'milliseconds' => ['nullable', 'numeric', 'min:1', 'max:86400000'],
            'bytes' => ['nullable', 'numeric', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'composer' => ['nullable', 'string', 'max:220'],
            'file_format' => ['nullable', 'in:mp3,flac,wav,aac,m4a'],
            'taxonomy_ids' => ['array'],
            'taxonomy_ids.*' => ['exists:taxonomies,id'],
        ];
    }
}
```

### 1.4.2. Custom Validation Rules

Create custom validation rules for Chinook-specific data:

```php
// app/Data/Rules/ChinookMusicFileRule.php
<?php

namespace App\Data\Rules;

use Illuminate\Contracts\Validation\Rule;

class ChinookMusicFileRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Validate music file extensions
        $allowedExtensions = ['mp3', 'flac', 'wav', 'aac', 'm4a', 'ogg'];
        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));

        return in_array($extension, $allowedExtensions);
    }

    public function message(): string
    {
        return 'The :attribute must be a valid music file (mp3, flac, wav, aac, m4a, ogg).';
    }
}
```

### 1.4.3. Data Transformation

Implement data transformation for Chinook operations:

```php
// app/Data/Transformers/ChinookTrackTransformer.php
<?php

namespace App\Data\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Transformers\Transformer;
use App\Models\ChinookTrack;

class ChinookTrackTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value): mixed
    {
        if (!$value instanceof ChinookTrack) {
            return $value;
        }

        return [
            'id' => $value->public_id,
            'name' => $value->name,
            'duration' => $this->formatDuration($value->milliseconds),
            'file_size' => $this->formatFileSize($value->bytes),
            'price' => $this->formatPrice($value->unit_price),
            'album' => [
                'id' => $value->album->public_id,
                'title' => $value->album->title,
                'artist' => $value->album->artist->name,
            ],
            'genre' => $value->genre->name,
            'taxonomies' => $value->taxonomies->pluck('name')->toArray(),
        ];
    }

    private function formatDuration(?int $milliseconds): ?string
    {
        if (!$milliseconds) return null;

        $seconds = intval($milliseconds / 1000);
        $minutes = intval($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    private function formatFileSize(?int $bytes): ?string
    {
        if (!$bytes) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    private function formatPrice(?float $price): ?string
    {
        return $price ? '$' . number_format($price, 2) : null;
    }
}
```

## 1.10. Advanced Patterns

### 1.10.1. Polymorphic Data Objects

Implement polymorphic data handling for Chinook:

```php
// app/Data/ChinookSearchResultData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;

abstract class ChinookSearchResultData extends Data
{
    public function __construct(
        #[Required]
        public string $type,

        #[Required]
        public string $public_id,

        #[Required]
        public string $title,

        public ?string $subtitle = null,
        public ?string $image_url = null,
        public ?float $relevance_score = null,
    ) {}

    abstract public function getDisplayUrl(): string;
}

class ChinookTrackSearchResultData extends ChinookSearchResultData
{
    public function __construct(
        public string $public_id,
        public string $title,
        public ?string $subtitle = null,
        public ?string $image_url = null,
        public ?float $relevance_score = null,
        public ?string $artist_name = null,
        public ?string $album_title = null,
        public ?int $duration_ms = null,
    ) {
        parent::__construct('track', $public_id, $title, $subtitle, $image_url, $relevance_score);
    }

    public function getDisplayUrl(): string
    {
        return route('chinook.tracks.show', $this->public_id);
    }
}

class ChinookAlbumSearchResultData extends ChinookSearchResultData
{
    public function __construct(
        public string $public_id,
        public string $title,
        public ?string $subtitle = null,
        public ?string $image_url = null,
        public ?float $relevance_score = null,
        public ?string $artist_name = null,
        public ?int $track_count = null,
        public ?int $release_year = null,
    ) {
        parent::__construct('album', $public_id, $title, $subtitle, $image_url, $relevance_score);
    }

    public function getDisplayUrl(): string
    {
        return route('chinook.albums.show', $this->public_id);
    }
}
```

## 1.11. Navigation

**← Previous:** [Laravel Horizon Guide](050-laravel-horizon-guide.md)

**Next →** [Laravel Sanctum Guide](070-laravel-sanctum-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Data implementation guide provides comprehensive type-safe data handling capabilities for the Chinook music store application, including:

- **Type-Safe DTOs**: Comprehensive data transfer objects for tracks, albums, artists, customers, and playlists
- **Advanced Validation**: Built-in and custom validation rules for music catalog data integrity
- **Nested Data Structures**: Complex nested DTOs for invoices, playlists, and search results
- **API Integration**: Seamless JSON API response generation with proper data transformation
- **Performance Optimization**: Caching strategies and lazy loading for large music datasets
- **Taxonomy Integration**: Type-safe handling of aliziodev/laravel-taxonomy data structures
- **Polymorphic Support**: Flexible data objects for search results and mixed content types

The implementation leverages Laravel Data's advanced capabilities while providing Chinook-specific optimizations for music catalog management, customer data handling, and business operations with complete type safety and validation.
