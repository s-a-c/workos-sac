# Chinook Media Library Integration Guide

## Table of Contents

- [Overview](#overview)
- [Installation and Setup](#installation-and-setup)
  - [Package Installation](#package-installation)
  - [MinIO Configuration](#minio-configuration)
  - [Environment Configuration](#environment-configuration)
- [Model Integration](#model-integration)
  - [HasMedia Trait Implementation](#hasmedia-trait-implementation)
  - [Media Collections](#media-collections)
  - [Conversion Definitions](#conversion-definitions)
- [Category-Based Media Management](#category-based-media-management)
  - [Media Categorization](#media-categorization)
  - [Hierarchical Media Organization](#hierarchical-media-organization)
- [File Upload and Processing](#file-upload-and-processing)
  - [Upload Workflows](#upload-workflows)
  - [Background Processing](#background-processing)
  - [Progress Tracking](#progress-tracking)
- [Avatar Upload Workflows](#avatar-upload-workflows)
  - [User Avatar Management](#user-avatar-management)
  - [Artist Profile Photos](#artist-profile-photos)
  - [Real-time Upload Progress](#real-time-upload-progress)
  - [Avatar Validation and Security](#avatar-validation-and-security)
- [Performance Optimization](#performance-optimization)
  - [CDN Integration](#cdn-integration)
  - [Caching Strategies](#caching-strategies)
  - [Storage Optimization](#storage-optimization)
- [API Integration](#api-integration)
  - [Media API Endpoints](#media-api-endpoints)
  - [Authentication and Authorization](#authentication-and-authorization)
- [Testing and Validation](#testing-and-validation)
- [Troubleshooting](#troubleshooting)
- [Next Steps](#next-steps)
- [Navigation](#navigation)

## Overview

This guide provides comprehensive instructions for integrating `spatie/laravel-medialibrary` into the Chinook Laravel 12 implementation. The media library seamlessly integrates with existing features including hybrid hierarchical categories, the Categorizable trait, RBAC system, and modern Laravel 12 patterns.

**Modern Laravel 12 Features Supported:**

- **HasMedia Trait Integration**: Works alongside Categorizable, Userstamps, and RBAC traits
- **Hybrid Hierarchical Compatibility**: Media can be categorized using the existing CategoryType system
- **Multi-tier Storage**: Optimized storage strategy for music platform requirements
- **Queue Processing**: Background media conversion with progress tracking
- **Performance Optimization**: Caching, CDN integration, and efficient querying

## Installation and Setup

### Package Installation

Install the Spatie Media Library package and its dependencies:

```bash
# Install the media library package
composer require spatie/laravel-medialibrary

# Publish and run migrations
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"

# Publish config (optional for customization)
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"

# Run migrations
php artisan migrate
```

### MinIO Configuration

#### 6.2.2. MinIO Configuration for Laravel Herd Development

For local development with Laravel Herd, configure MinIO as an S3-compatible storage solution:

```bash
# Install MinIO using Homebrew
brew install minio/stable/minio

# Create MinIO data directory
mkdir -p ~/minio-data

# Start MinIO server
minio server ~/minio-data --console-address ":9001"
```

**Environment Configuration (.env):**

```env
# MinIO Configuration for Local Development
MEDIA_DISK=minio
MINIO_ENDPOINT=http://127.0.0.1:9000
MINIO_ACCESS_KEY=minioadmin
MINIO_SECRET_KEY=minioadmin
MINIO_BUCKET=chinook-media
MINIO_REGION=us-east-1

# Production Storage Configuration
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_MEDIA_BUCKET=chinook-media-prod
AWS_ARCHIVE_BUCKET=chinook-media-archive

# Cloudflare R2 for CDN
CLOUDFLARE_R2_ACCESS_KEY_ID=your_r2_key
CLOUDFLARE_R2_SECRET_ACCESS_KEY=your_r2_secret
CLOUDFLARE_R2_BUCKET=chinook-cdn
CLOUDFLARE_R2_ENDPOINT=https://your-account.r2.cloudflarestorage.com
```

**Filesystem Configuration (config/filesystems.php):**

```php
'disks' => [
    // Local development with MinIO
    'minio' => [
        'driver' => 's3',
        'key' => env('MINIO_ACCESS_KEY'),
        'secret' => env('MINIO_SECRET_KEY'),
        'region' => env('MINIO_REGION'),
        'bucket' => env('MINIO_BUCKET'),
        'endpoint' => env('MINIO_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'throw' => false,
    ],

    // Production: Primary storage for frequently accessed media
    'media_primary' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_MEDIA_BUCKET'),
        'url' => env('AWS_URL'),
        'throw' => false,
    ],

    // Archive storage for rarely accessed media
    'media_archive' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_ARCHIVE_BUCKET'),
        'storage_class' => 'GLACIER',
        'throw' => false,
    ],

    // CDN for global distribution
    'media_cdn' => [
        'driver' => 's3',
        'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
        'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
        'region' => 'auto',
        'bucket' => env('CLOUDFLARE_R2_BUCKET'),
        'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
        'use_path_style_endpoint' => true,
        'throw' => false,
    ],
],

// Set default disk based on environment
'default' => env('FILESYSTEM_DISK', env('APP_ENV') === 'local' ? 'minio' : 'media_primary'),
```

### Environment Configuration

#### 6.2.3. Media Library Configuration

**Media Library Config (config/media-library.php):**

```php
return [
    'disk_name' => env('MEDIA_DISK', env('APP_ENV') === 'local' ? 'minio' : 'media_primary'),
    
    'max_file_size' => 1024 * 1024 * 100, // 100MB for audio files
    
    'queue_name' => 'media-conversions',
    
    'queue_conversions_by_default' => true,
    
    'media_model' => App\Models\ChinookMedia::class,
    
    'remote' => [
        'extra_headers' => [
            'CacheControl' => 'max-age=604800', // 1 week
        ],
    ],
    
    'responsive_images' => [
        'width_calculator' => Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator::class,
        'use_tiny_placeholders' => true,
    ],
    
    'path_generator' => Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator::class,
    
    'url_generator' => Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator::class,
];
```

## 6.3. Integration Strategy

## Model Integration

### HasMedia Trait Implementation

#### 6.3.1. Selective HasMedia Trait Implementation

**Recommended Models for HasMedia Integration:**

```php
// ✅ Models that SHOULD have HasMedia trait
Artist    - Profile photos, band images, promotional materials, press kits
Album     - Cover art, liner notes, promotional images, digital booklets
Track     - Audio files, preview clips, waveform images, lyric sheets
Playlist  - Cover images, promotional graphics

// ❌ Models that should NOT have HasMedia trait
Customer  - Use separate User profile system for privacy/GDPR compliance
Employee  - Use separate HR system for employee data management
Invoice   - Use document management system for financial records
```

**Rationale for Selective Implementation:**
- **Performance**: Reduces database overhead for models that don't need media
- **Security**: Separates user-generated content from business-critical data
- **Compliance**: Maintains GDPR and privacy boundaries
- **Maintainability**: Cleaner separation of concerns

### 6.3.2. Database Migration Order

Execute migrations in this specific order to maintain referential integrity:

```bash
# 1. Existing Chinook migrations (already completed)
# 2. Media library migrations
php artisan migrate

# 3. Custom media enhancements
php artisan make:migration enhance_media_table_for_chinook
php artisan make:migration create_media_categories_table
```

### 6.3.3. Trait Integration Patterns

The `HasMedia` trait integrates seamlessly with existing Chinook traits:

```php
<?php

namespace App\Models;

use App\Traits\Categorizable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Artist extends Model implements HasMedia
{
    // Trait order matters for method resolution
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTags;
    use SoftDeletes;
    use Userstamps;              // User stamps for audit trail
    use HasRoles;                // RBAC permissions
    use HasPermissions;          // RBAC permissions
    use Categorizable;           // Category relationships
    use InteractsWithMedia;      // Media library functionality

    // Model implementation continues...
}
```

**Trait Compatibility Matrix:**

| Trait | HasMedia | Conflicts | Notes |
|-------|----------|-----------|-------|
| Categorizable | ✅ Compatible | None | Can categorize both models and media |
| Userstamps | ✅ Compatible | None | Tracks who uploaded/modified media |
| HasRoles/HasPermissions | ✅ Compatible | None | Controls media access permissions |
| SoftDeletes | ✅ Compatible | None | Soft delete media with models |
| HasTags | ✅ Compatible | None | Tag both models and media separately |

## 6.4. Media Types and Collections

### Media Collections

#### 6.4.1. Model-Specific Media Collections

#### 6.4.1.1. Artist Model Media Collections

```php
class Artist extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('band_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('promotional_materials')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);

        $this->addMediaCollection('press_kit')
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'text/plain']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('profile_photos', 'band_photos', 'promotional_materials');

        $this->addMediaConversion('avatar')
            ->width(150)
            ->height(150)
            ->optimize()
            ->performOnCollections('profile_photos');

        $this->addMediaConversion('hero')
            ->width(1920)
            ->height(1080)
            ->optimize()
            ->performOnCollections('band_photos', 'promotional_materials');

        $this->addMediaConversion('webp_thumb')
            ->width(300)
            ->height(300)
            ->format('webp')
            ->optimize()
            ->performOnCollections('profile_photos', 'band_photos');
    }
}
```

#### 6.4.1.2. Album Model Media Collections

```php
class Album extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_art')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('liner_notes')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);

        $this->addMediaCollection('promotional_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('digital_booklet')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(300)
            ->height(300)
            ->performOnCollections('cover_art', 'promotional_images');

        $this->addMediaConversion('large')
            ->width(1000)
            ->height(1000)
            ->performOnCollections('cover_art');

        $this->addMediaConversion('playlist_cover')
            ->width(500)
            ->height(500)
            ->performOnCollections('cover_art');
    }
}
```

#### 6.4.1.3. Track Model Media Collections

```php
class Track extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('audio_files')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/flac', 'audio/wav', 'audio/ogg'])
            ->singleFile();

        $this->addMediaCollection('preview_clips')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav']);

        $this->addMediaCollection('waveform_images')
            ->acceptsMimeTypes(['image/png', 'image/svg+xml'])
            ->singleFile();

        $this->addMediaCollection('lyric_sheets')
            ->acceptsMimeTypes(['application/pdf', 'text/plain', 'image/jpeg', 'image/png']);

        $this->addMediaCollection('sheet_music')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        // Audio conversions for different quality levels
        $this->addMediaConversion('preview')
            ->performOnCollections('audio_files')
            ->nonQueued(); // For immediate preview generation

        $this->addMediaConversion('compressed')
            ->performOnCollections('audio_files');

        // Waveform generation
        $this->addMediaConversion('waveform_thumb')
            ->width(800)
            ->height(200)
            ->performOnCollections('waveform_images');
    }
}
```

#### 6.4.1.4. Playlist Model Media Collections

```php
class Playlist extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('promotional_graphics')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(300)
            ->height(300)
            ->performOnCollections('cover_images', 'promotional_graphics');

        $this->addMediaConversion('large')
            ->width(800)
            ->height(800)
            ->performOnCollections('cover_images');
    }
}
```

### Conversion Definitions

#### 6.4.2. File Format Support Matrix

| Collection | JPEG | PNG | WebP | MP3 | FLAC | WAV | PDF | TXT |
|------------|------|-----|------|-----|------|-----|-----|-----|
| profile_photos | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| band_photos | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| cover_art | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| audio_files | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| preview_clips | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| press_kit | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| liner_notes | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| lyric_sheets | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |

## Category-Based Media Management

### Media Categorization

#### 6.4.3. Custom ChinookMedia Model with Categorizable Integration

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Categorizable;
use App\Enums\CategoryType;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\SoftDeletes;
use Wildside\Userstamps\Userstamps;

class ChinookMedia extends Media
{
    use Categorizable;
    use SoftDeletes;
    use Userstamps;

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'custom_properties' => 'array',
            'generated_conversions' => 'array',
            'deleted_at' => 'datetime',
        ]);
    }

    /**
     * Get media-specific category types.
     */
    public function getMediaCategoryTypes(): array
    {
        return [
            CategoryType::THEME,     // e.g., "Promotional", "Archival", "Live Performance"
            CategoryType::OCCASION,  // e.g., "Album Release", "Tour", "Interview"
            CategoryType::LANGUAGE,  // For international content
        ];
    }

    /**
     * Scope to filter media by category type.
     */
    public function scopeByMediaCategory(Builder $query, CategoryType $type): Builder
    {
        return $query->whereHas('categories', function ($categoryQuery) use ($type) {
            $categoryQuery->where('type', $type);
        });
    }

    /**
     * Get the storage tier for this media based on access patterns.
     */
    public function getStorageTier(): string
    {
        $accessCount = $this->getCustomProperty('access_count', 0);
        $daysSinceCreated = $this->created_at->diffInDays(now());

        return match(true) {
            $accessCount > 100 && $daysSinceCreated < 30 => 'hot',
            $accessCount > 10 && $daysSinceCreated < 90 => 'warm',
            $daysSinceCreated > 365 => 'archive',
            default => 'standard',
        };
    }
}
```

**Register Custom Media Model:**

```php
// In AppServiceProvider or MediaLibraryServiceProvider
public function boot(): void
{
    $this->app->bind(
        \Spatie\MediaLibrary\MediaCollections\Models\Media::class,
        \App\Models\ChinookMedia::class
    );
}
```

## 6.5. Technical Implementation

### Hierarchical Media Organization

#### 6.5.1. Multi-tier Storage Strategy

```php
// Dynamic storage selection service
class MediaStorageService
{
    private array $storageTiers = [
        'hot' => 'media_cdn',        // Frequently accessed (CDN)
        'warm' => 'media_primary',   // Regularly accessed (S3 Standard)
        'cold' => 'media_primary',   // Infrequently accessed (S3 IA)
        'archive' => 'media_archive', // Rarely accessed (Glacier)
    ];

    public function getDiskForMedia(string $collection, int $fileSize, array $metadata = []): string
    {
        // Local development always uses MinIO
        if (app()->environment('local')) {
            return 'minio';
        }

        return match($collection) {
            'audio_files' => $fileSize > 50 * 1024 * 1024 ? 'media_archive' : 'media_primary',
            'cover_art', 'profile_photos' => 'media_cdn', // Frequently accessed
            'press_kit', 'liner_notes' => 'media_archive', // Rarely accessed
            default => 'media_primary',
        };
    }

    public function shouldQueue(string $collection): bool
    {
        return in_array($collection, [
            'audio_files',           // Large files need background processing
            'promotional_materials',
            'digital_booklet',
        ]);
    }

    public function getLifecyclePolicy(string $collection): array
    {
        return match($collection) {
            'audio_files' => [
                'transition_to_ia' => 30,    // days
                'transition_to_glacier' => 90,
                'delete_after' => 2555,      // 7 years
            ],
            'cover_art', 'profile_photos' => [
                'transition_to_ia' => 90,
                'transition_to_glacier' => 365,
                'delete_after' => null,      // Keep indefinitely
            ],
            'press_kit', 'promotional_materials' => [
                'transition_to_ia' => 7,
                'transition_to_glacier' => 30,
                'delete_after' => 1095,      // 3 years
            ],
            default => [
                'transition_to_ia' => 30,
                'transition_to_glacier' => 90,
                'delete_after' => 365,
            ],
        };
    }
}
```

## File Upload and Processing

### Upload Workflows

#### 6.5.2. Media Conversion Pipeline

```php
// Custom conversion classes for audio processing
class AudioConversion
{
    public static function preview(): \Spatie\MediaLibrary\Conversions\Conversion
    {
        return \Spatie\MediaLibrary\Conversions\Conversion::create('preview')
            ->performOnCollections('audio_files')
            ->addFilter('-ss', '30')        // Start at 30 seconds
            ->addFilter('-t', '30')         // Duration 30 seconds
            ->addFilter('-b:a', '128k')     // Lower bitrate for previews
            ->format('mp3')
            ->nonQueued();                  // Generate immediately for UI
    }

    public static function waveform(): \Spatie\MediaLibrary\Conversions\Conversion
    {
        return \Spatie\MediaLibrary\Conversions\Conversion::create('waveform')
            ->performOnCollections('audio_files')
            ->addFilter('-filter_complex', 'showwavespic=s=1200x300:colors=0x3b82f6')
            ->format('png')
            ->nonQueued();
    }

    public static function compressed(): \Spatie\MediaLibrary\Conversions\Conversion
    {
        return \Spatie\MediaLibrary\Conversions\Conversion::create('compressed')
            ->performOnCollections('audio_files')
            ->addFilter('-b:a', '192k')     // Compressed quality
            ->format('mp3');
    }
}

// Queue job for media processing with progress tracking
class MediaConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour for large audio files
    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // Exponential backoff

    public function __construct(
        public ChinookMedia $media,
        public string $conversionName
    ) {}

    public function handle(): void
    {
        $this->updateProgress(0, 'Starting conversion...');

        try {
            $this->media->performConversions($this->conversionName);

            $this->media->setCustomProperty('is_processed', true);
            $this->media->setCustomProperty('processed_at', now());
            $this->media->save();

            $this->updateProgress(100, 'Conversion completed');

        } catch (Exception $e) {
            $this->media->setCustomProperty('processing_errors', [
                'error' => $e->getMessage(),
                'failed_at' => now(),
                'conversion' => $this->conversionName,
            ]);
            $this->media->save();

            $this->updateProgress(-1, 'Conversion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function updateProgress(int $percentage, string $message): void
    {
        Cache::put("media_conversion_{$this->media->id}", [
            'percentage' => $percentage,
            'message' => $message,
            'conversion' => $this->conversionName,
            'updated_at' => now(),
        ], 3600);
    }
}
```

### Background Processing

### Progress Tracking

#### 6.5.3. Performance Optimization

```php
// Media caching and optimization service
class MediaCacheService
{
    public function getCachedMediaUrl(ChinookMedia $media, string $conversion = ''): string
    {
        $cacheKey = "media_url_{$media->id}_{$conversion}";

        return Cache::remember($cacheKey, 3600, function () use ($media, $conversion) {
            return $conversion
                ? $media->getUrl($conversion)
                : $media->getUrl();
        });
    }

    public function preloadMediaForModel(Model $model): void
    {
        // Preload commonly accessed media conversions
        $model->load(['media' => function ($query) {
            $query->with('conversions');
        }]);

        // Cache URLs for immediate access
        foreach ($model->media as $media) {
            $this->getCachedMediaUrl($media, 'thumbnail');
            $this->getCachedMediaUrl($media, 'large');
        }
    }

    public function warmCDNCache(ChinookMedia $media): void
    {
        // Preload critical conversions to CDN
        $criticalConversions = ['thumbnail', 'large', 'webp_thumb'];

        foreach ($criticalConversions as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                // Make HTTP request to warm CDN cache
                Http::get($media->getUrl($conversion));
            }
        }
    }
}

// Optimized repository with media relationships
class ArtistRepository
{
    public function getArtistsWithMedia(array $filters = []): Collection
    {
        return Artist::query()
            ->with([
                'media' => function ($query) {
                    $query->where('collection_name', 'profile_photos')
                          ->latest()
                          ->limit(1);
                },
                'categories' => function ($query) {
                    $query->where('type', CategoryType::GENRE);
                }
            ])
            ->when($filters['has_media'] ?? false, function ($query) {
                $query->whereHas('media', function ($mediaQuery) {
                    $mediaQuery->where('collection_name', 'profile_photos');
                });
            })
            ->when($filters['category_ids'] ?? null, function ($query, $categoryIds) {
                $query->withCategories($categoryIds);
            })
            ->paginate(20);
    }

    public function getArtistWithCompleteMedia(int $artistId): ?Artist
    {
        return Artist::with([
            'media' => function ($query) {
                $query->orderBy('collection_name')
                      ->orderBy('order_column');
            },
            'albums.media' => function ($query) {
                $query->where('collection_name', 'cover_art');
            },
            'categories'
        ])->find($artistId);
    }
}
```

## 6.6. Architecture Trade-offs

### 6.6.1. Storage Cost Analysis

```php
// Cost calculation service for different storage strategies
class StorageCostCalculator
{
    private array $costs = [
        's3_standard' => [
            'storage_per_gb' => 0.023,    // $/GB/month
            'requests_per_1k' => 0.0004,  // $/1K requests
            'transfer_per_gb' => 0.09,    // $/GB transfer
        ],
        's3_glacier' => [
            'storage_per_gb' => 0.004,
            'requests_per_1k' => 0.05,
            'transfer_per_gb' => 0.09,
        ],
        'cloudflare_r2' => [
            'storage_per_gb' => 0.015,
            'requests_per_1k' => 0.0,     // Free requests
            'transfer_per_gb' => 0.0,     // Free egress
        ],
        'local_server' => [
            'storage_per_gb' => 0.10,     // Server costs amortized
            'requests_per_1k' => 0.0,
            'transfer_per_gb' => 0.0,
        ],
    ];

    public function calculateMonthlyCost(string $storage, int $totalGB, int $requests, int $transferGB): float
    {
        $config = $this->costs[$storage];

        return ($totalGB * $config['storage_per_gb']) +
               ($requests * $config['requests_per_1k'] / 1000) +
               ($transferGB * $config['transfer_per_gb']);
    }

    public function getOptimalStrategy(array $usage): array
    {
        // Example usage: ['total_gb' => 1000, 'monthly_requests' => 100000, 'transfer_gb' => 500]
        $strategies = [];

        foreach ($this->costs as $storage => $config) {
            $strategies[$storage] = $this->calculateMonthlyCost(
                $storage,
                $usage['total_gb'],
                $usage['monthly_requests'],
                $usage['transfer_gb']
            );
        }

        asort($strategies);
        return $strategies;
    }

    public function getRecommendedTierStrategy(): array
    {
        return [
            'hot_tier' => [
                'storage' => 'cloudflare_r2',
                'use_case' => 'Frequently accessed images (cover art, profile photos)',
                'cost_per_gb' => '$0.015/month',
                'benefits' => ['Free egress', 'Global CDN', 'Fast access'],
            ],
            'warm_tier' => [
                'storage' => 's3_standard',
                'use_case' => 'Regular access audio files and documents',
                'cost_per_gb' => '$0.023/month',
                'benefits' => ['High durability', 'Fast retrieval', 'Lifecycle policies'],
            ],
            'cold_tier' => [
                'storage' => 's3_glacier',
                'use_case' => 'Archive audio, old promotional materials',
                'cost_per_gb' => '$0.004/month',
                'benefits' => ['Very low cost', 'Long-term retention', 'Compliance'],
            ],
        ];
    }
}
```

**Cost Comparison Example (1TB storage, 100K requests/month, 500GB transfer):**

| Storage Strategy | Monthly Cost | Use Case |
|------------------|--------------|----------|
| Cloudflare R2 Only | ~$15 | Best for image-heavy platforms |
| S3 Standard Only | ~$140 | Simple but expensive |
| S3 + Glacier Hybrid | ~$90 | Balanced approach |
| Multi-tier Optimized | ~$35 | Recommended for music platforms |

### 6.6.2. Backup and Disaster Recovery

```php
// Multi-region backup strategy
class MediaBackupService
{
    public function createBackupStrategy(): array
    {
        return [
            'primary' => [
                'storage' => 's3',
                'region' => 'us-east-1',
                'replication' => 'cross-region',
                'versioning' => true,
                'lifecycle' => [
                    'current_version_transitions' => [
                        ['days' => 30, 'storage_class' => 'STANDARD_IA'],
                        ['days' => 90, 'storage_class' => 'GLACIER'],
                    ],
                    'noncurrent_version_transitions' => [
                        ['days' => 7, 'storage_class' => 'GLACIER'],
                    ],
                ],
            ],
            'backup' => [
                'storage' => 's3',
                'region' => 'eu-west-1',
                'purpose' => 'Cross-region disaster recovery',
                'sync_frequency' => 'daily',
            ],
            'archive' => [
                'storage' => 'glacier_deep_archive',
                'region' => 'us-west-2',
                'retention' => 'indefinite',
                'purpose' => 'Long-term compliance and legal hold',
            ],
        ];
    }

    public function scheduleBackups(): void
    {
        // Daily incremental backups
        Schedule::command('media:backup --incremental')
            ->daily()
            ->at('02:00')
            ->withoutOverlapping();

        // Weekly full backups
        Schedule::command('media:backup --full')
            ->weekly()
            ->sundays()
            ->at('01:00')
            ->withoutOverlapping();

        // Monthly archive to deep storage
        Schedule::command('media:archive --older-than=90days')
            ->monthly()
            ->at('00:00')
            ->withoutOverlapping();

        // Quarterly backup verification
        Schedule::command('media:verify-backups')
            ->quarterly()
            ->at('03:00');
    }
}
```

### 6.6.3. CDN Integration Strategy

```php
// CDN configuration for global distribution
class MediaCDNService
{
    private array $cdnEndpoints = [
        'images' => env('CDN_IMAGES_URL', 'https://images.chinook-cdn.com'),
        'audio' => env('CDN_AUDIO_URL', 'https://audio.chinook-cdn.com'),
        'documents' => env('CDN_DOCS_URL', 'https://docs.chinook-cdn.com'),
    ];

    public function getCDNUrl(ChinookMedia $media, string $conversion = ''): string
    {
        // Use local URLs in development
        if (app()->environment('local')) {
            return $conversion ? $media->getUrl($conversion) : $media->getUrl();
        }

        $type = $this->getMediaType($media);
        $baseUrl = $this->cdnEndpoints[$type];

        $path = $conversion
            ? $media->getPath($conversion)
            : $media->getPath();

        return "{$baseUrl}/{$path}";
    }

    private function getMediaType(ChinookMedia $media): string
    {
        return match(true) {
            str_starts_with($media->mime_type, 'image/') => 'images',
            str_starts_with($media->mime_type, 'audio/') => 'audio',
            str_starts_with($media->mime_type, 'application/') => 'documents',
            default => 'images',
        };
    }

    public function preloadCriticalMedia(Collection $models): void
    {
        // Preload critical media to CDN edge locations
        $criticalMedia = $models->flatMap(function ($model) {
            return $model->getMedia(['profile_photos', 'cover_art']);
        });

        foreach ($criticalMedia as $media) {
            $this->warmCDNCache($media);
        }
    }

    private function warmCDNCache(ChinookMedia $media): void
    {
        $criticalConversions = ['thumbnail', 'large'];

        foreach ($criticalConversions as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                // Make HEAD request to warm CDN cache without downloading
                Http::head($this->getCDNUrl($media, $conversion));
            }
        }
    }
}
```

## Avatar Upload Workflows

### User Avatar Management

#### 6.6.4. Avatar Upload Workflows

Comprehensive avatar upload handling with real-time progress, validation, and optimization specifically designed for user profiles and artist photos.

### Avatar Validation and Security

Comprehensive security measures for avatar uploads:

```php
// Avatar validation rules
public function avatarValidationRules(): array
{
    return [
        'avatar' => [
            'required',
            'image',
            'mimes:jpeg,png,webp',
            'max:5120', // 5MB
            'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
        ],
    ];
}

// Security scanning
public function scanUploadedFile($file): bool
{
    // Virus scanning, content validation, etc.
    return $this->securityService->scanFile($file);
}
```

### Artist Profile Photos

```php
<?php
// app/Services/AvatarUploadService.php

namespace App\Services;

use App\Models\User;
use App\Models\Artist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AvatarUploadService
{
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif'
    ];

    private int $maxFileSize = 10 * 1024 * 1024; // 10MB
    private array $requiredSizes = [
        'thumbnail' => ['width' => 64, 'height' => 64],
        'avatar' => ['width' => 150, 'height' => 150],
        'profile' => ['width' => 300, 'height' => 300],
        'hero' => ['width' => 800, 'height' => 600],
    ];

    public function uploadUserAvatar(User $user, UploadedFile $file, array $options = []): Media
    {
        // Validate file
        $this->validateAvatarFile($file);

        // Remove existing avatar if replace option is set
        if ($options['replace'] ?? true) {
            $this->removeExistingAvatar($user);
        }

        // Process and upload
        $media = $user->addMediaFromRequest('avatar')
            ->withCustomProperties([
                'uploaded_by' => auth()->id(),
                'upload_source' => $options['source'] ?? 'web',
                'original_name' => $file->getClientOriginalName(),
                'file_hash' => hash_file('sha256', $file->getPathname()),
                'upload_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'upload_session' => session()->getId(),
            ])
            ->usingName($options['name'] ?? 'User Avatar')
            ->usingFileName($this->generateAvatarFileName($user, $file))
            ->toMediaCollection('avatars', 'image_cdn');

        // Generate conversions immediately for avatars (small files)
        $this->generateAvatarConversions($media);

        // Update user avatar reference
        $user->update(['avatar_media_id' => $media->id]);

        // Clear user cache
        Cache::forget("user_avatar_{$user->id}");

        // Track upload analytics
        $this->trackAvatarUpload($user, $media, $options);

        return $media;
    }

    public function uploadArtistProfilePhoto(Artist $artist, UploadedFile $file, array $options = []): Media
    {
        $this->validateAvatarFile($file);

        // Artists can have multiple profile photos
        $media = $artist->addMediaFromRequest('profile_photo')
            ->withCustomProperties([
                'uploaded_by' => auth()->id(),
                'upload_source' => $options['source'] ?? 'web',
                'original_name' => $file->getClientOriginalName(),
                'file_hash' => hash_file('sha256', $file->getPathname()),
                'upload_ip' => request()->ip(),
                'photo_type' => $options['photo_type'] ?? 'profile',
                'is_primary' => $options['is_primary'] ?? false,
            ])
            ->usingName($options['name'] ?? 'Artist Profile Photo')
            ->usingFileName($this->generateArtistPhotoFileName($artist, $file, $options))
            ->toMediaCollection('profile_photos', 'image_cdn');

        // Set as primary if specified or if it's the first photo
        if ($options['is_primary'] ?? false || !$artist->getFirstMedia('profile_photos')) {
            $this->setPrimaryProfilePhoto($artist, $media);
        }

        $this->generateAvatarConversions($media);

        return $media;
    }

    private function validateAvatarFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException(
                'File size exceeds maximum allowed size of ' . ($this->maxFileSize / 1024 / 1024) . 'MB'
            );
        }

        // Check MIME type
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException(
                'Invalid file type. Allowed types: ' . implode(', ', $this->allowedMimeTypes)
            );
        }

        // Validate image dimensions
        $imageInfo = getimagesize($file->getPathname());
        if (!$imageInfo) {
            throw new \InvalidArgumentException('Invalid image file');
        }

        [$width, $height] = $imageInfo;

        // Minimum dimensions
        if ($width < 64 || $height < 64) {
            throw new \InvalidArgumentException('Image must be at least 64x64 pixels');
        }

        // Maximum dimensions
        if ($width > 4096 || $height > 4096) {
            throw new \InvalidArgumentException('Image dimensions too large (max 4096x4096)');
        }

        // Check for malicious content
        $this->scanForMaliciousContent($file);
    }

    private function generateAvatarConversions(Media $media): void
    {
        foreach ($this->requiredSizes as $conversion => $dimensions) {
            $media->addMediaConversion($conversion)
                ->width($dimensions['width'])
                ->height($dimensions['height'])
                ->sharpen(10)
                ->optimize()
                ->nonQueued(); // Process immediately for avatars
        }
    }

    private function generateAvatarFileName(User $user, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d-H-i-s');
        return "user-{$user->id}-avatar-{$timestamp}.{$extension}";
    }

    private function generateArtistPhotoFileName(Artist $artist, UploadedFile $file, array $options): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d-H-i-s');
        $type = $options['photo_type'] ?? 'profile';
        return "artist-{$artist->id}-{$type}-{$timestamp}.{$extension}";
    }

    private function removeExistingAvatar(User $user): void
    {
        $existingMedia = $user->getFirstMedia('avatars');
        if ($existingMedia) {
            $existingMedia->delete();
        }
    }

    private function setPrimaryProfilePhoto(Artist $artist, Media $media): void
    {
        // Remove primary flag from other photos
        $artist->media()
            ->where('collection_name', 'profile_photos')
            ->where('id', '!=', $media->id)
            ->update(['custom_properties->is_primary' => false]);

        // Set this photo as primary
        $media->setCustomProperty('is_primary', true);
        $media->save();

        // Update artist primary photo reference
        $artist->update(['primary_photo_media_id' => $media->id]);
    }

    private function scanForMaliciousContent(UploadedFile $file): void
    {
        // Check for embedded PHP code in image files
        $content = file_get_contents($file->getPathname());

        $maliciousPatterns = [
            '/<\?php/',
            '/<script/',
            '/eval\s*\(/',
            '/base64_decode/',
            '/exec\s*\(/',
            '/system\s*\(/',
            '/shell_exec/',
        ];

        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new \InvalidArgumentException('Potentially malicious content detected in file');
            }
        }
    }

    private function trackAvatarUpload(User $user, Media $media, array $options): void
    {
        // Track upload analytics
        \Log::info('Avatar uploaded', [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'file_size' => $media->size,
            'mime_type' => $media->mime_type,
            'source' => $options['source'] ?? 'web',
            'upload_time' => now(),
        ]);

        // Update user statistics
        Cache::increment("user_uploads_{$user->id}");
    }
}
```

#### Real-time Upload Progress

```php
<?php
// app/Http/Controllers/Api/AvatarUploadController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AvatarUploadService;
use App\Http\Requests\AvatarUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AvatarUploadController extends Controller
{
    public function __construct(
        private AvatarUploadService $avatarService
    ) {
        $this->middleware('auth:sanctum');
    }

    public function uploadUserAvatar(AvatarUploadRequest $request): JsonResponse
    {
        $user = auth()->user();
        $uploadId = Str::uuid();

        // Initialize progress tracking
        $this->initializeUploadProgress($uploadId);

        try {
            // Update progress: validation
            $this->updateUploadProgress($uploadId, 10, 'Validating file...');

            $file = $request->file('avatar');
            $options = [
                'source' => $request->input('source', 'web'),
                'replace' => $request->boolean('replace', true),
                'name' => $request->input('name', 'User Avatar'),
            ];

            // Update progress: processing
            $this->updateUploadProgress($uploadId, 30, 'Processing image...');

            $media = $this->avatarService->uploadUserAvatar($user, $file, $options);

            // Update progress: generating thumbnails
            $this->updateUploadProgress($uploadId, 70, 'Generating thumbnails...');

            // Wait for conversions to complete
            $this->waitForConversions($media);

            // Update progress: complete
            $this->updateUploadProgress($uploadId, 100, 'Upload complete!');

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'upload_id' => $uploadId,
                'data' => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'urls' => [
                        'original' => $media->getUrl(),
                        'thumbnail' => $media->getUrl('thumbnail'),
                        'avatar' => $media->getUrl('avatar'),
                        'profile' => $media->getUrl('profile'),
                    ],
                    'conversions' => $media->getGeneratedConversions()->toArray(),
                ],
            ], 201);

        } catch (\Exception $e) {
            $this->updateUploadProgress($uploadId, 0, 'Upload failed: ' . $e->getMessage(), true);

            return response()->json([
                'message' => 'Avatar upload failed',
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function uploadArtistPhoto(AvatarUploadRequest $request, int $artistId): JsonResponse
    {
        $artist = \App\Models\Artist::findOrFail($artistId);
        $this->authorize('update', $artist);

        $uploadId = Str::uuid();
        $this->initializeUploadProgress($uploadId);

        try {
            $this->updateUploadProgress($uploadId, 10, 'Validating file...');

            $file = $request->file('avatar');
            $options = [
                'source' => $request->input('source', 'web'),
                'photo_type' => $request->input('photo_type', 'profile'),
                'is_primary' => $request->boolean('is_primary', false),
                'name' => $request->input('name', 'Artist Profile Photo'),
            ];

            $this->updateUploadProgress($uploadId, 30, 'Processing image...');

            $media = $this->avatarService->uploadArtistProfilePhoto($artist, $file, $options);

            $this->updateUploadProgress($uploadId, 70, 'Generating thumbnails...');
            $this->waitForConversions($media);
            $this->updateUploadProgress($uploadId, 100, 'Upload complete!');

            return response()->json([
                'message' => 'Artist photo uploaded successfully',
                'upload_id' => $uploadId,
                'data' => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'is_primary' => $media->getCustomProperty('is_primary', false),
                    'urls' => [
                        'original' => $media->getUrl(),
                        'thumbnail' => $media->getUrl('thumbnail'),
                        'avatar' => $media->getUrl('avatar'),
                        'profile' => $media->getUrl('profile'),
                        'hero' => $media->getUrl('hero'),
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            $this->updateUploadProgress($uploadId, 0, 'Upload failed: ' . $e->getMessage(), true);

            return response()->json([
                'message' => 'Artist photo upload failed',
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function getUploadProgress(Request $request, string $uploadId): JsonResponse
    {
        $progress = Cache::get("upload_progress_{$uploadId}");

        if (!$progress) {
            return response()->json([
                'message' => 'Upload progress not found',
            ], 404);
        }

        return response()->json($progress);
    }

    public function deleteUserAvatar(Request $request): JsonResponse
    {
        $user = auth()->user();
        $media = $user->getFirstMedia('avatars');

        if (!$media) {
            return response()->json([
                'message' => 'No avatar found',
            ], 404);
        }

        $media->delete();
        $user->update(['avatar_media_id' => null]);
        Cache::forget("user_avatar_{$user->id}");

        return response()->json([
            'message' => 'Avatar deleted successfully',
        ]);
    }

    private function initializeUploadProgress(string $uploadId): void
    {
        Cache::put("upload_progress_{$uploadId}", [
            'upload_id' => $uploadId,
            'progress' => 0,
            'status' => 'starting',
            'message' => 'Initializing upload...',
            'error' => false,
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ], 3600); // 1 hour TTL
    }

    private function updateUploadProgress(string $uploadId, int $progress, string $message, bool $error = false): void
    {
        $data = Cache::get("upload_progress_{$uploadId}", []);

        $data['progress'] = $progress;
        $data['message'] = $message;
        $data['error'] = $error;
        $data['status'] = $error ? 'error' : ($progress === 100 ? 'complete' : 'processing');
        $data['updated_at'] = now()->toISOString();

        if ($progress === 100 && !$error) {
            $data['completed_at'] = now()->toISOString();
        }

        Cache::put("upload_progress_{$uploadId}", $data, 3600);

        // Broadcast progress update via WebSocket if available
        if (class_exists('\Pusher\Pusher')) {
            broadcast(new \App\Events\UploadProgressUpdated($uploadId, $data));
        }
    }

    private function waitForConversions(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): void
    {
        $maxWait = 30; // 30 seconds max wait
        $waited = 0;

        while ($waited < $maxWait) {
            $media->refresh();

            $requiredConversions = ['thumbnail', 'avatar', 'profile'];
            $completedConversions = $media->getGeneratedConversions()->keys()->toArray();

            if (count(array_intersect($requiredConversions, $completedConversions)) === count($requiredConversions)) {
                break;
            }

            sleep(1);
            $waited++;
        }
    }
}
```

#### Frontend Avatar Upload Component

```javascript
// resources/js/components/avatar-upload.js

class AvatarUpload {
    constructor(options = {}) {
        this.options = {
            maxFileSize: 10 * 1024 * 1024, // 10MB
            allowedTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            progressUpdateInterval: 500, // 500ms
            ...options
        };

        this.uploadId = null;
        this.progressInterval = null;
        this.setupEventListeners();
    }

    setupEventListeners() {
        // File input change
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-avatar-upload]')) {
                this.handleFileSelect(e);
            }
        });

        // Drag and drop
        document.addEventListener('dragover', (e) => {
            if (e.target.matches('[data-avatar-dropzone]')) {
                e.preventDefault();
                e.target.classList.add('drag-over');
            }
        });

        document.addEventListener('dragleave', (e) => {
            if (e.target.matches('[data-avatar-dropzone]')) {
                e.target.classList.remove('drag-over');
            }
        });

        document.addEventListener('drop', (e) => {
            if (e.target.matches('[data-avatar-dropzone]')) {
                e.preventDefault();
                e.target.classList.remove('drag-over');

                const files = Array.from(e.dataTransfer.files);
                if (files.length > 0) {
                    this.handleFileSelect({ target: { files } });
                }
            }
        });
    }

    async handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.showError(validation.error);
            return;
        }

        // Show preview
        this.showPreview(file);

        // Start upload
        try {
            await this.uploadFile(file);
        } catch (error) {
            this.showError(error.message);
        }
    }

    validateFile(file) {
        // Check file size
        if (file.size > this.options.maxFileSize) {
            return {
                valid: false,
                error: `File size exceeds ${this.options.maxFileSize / 1024 / 1024}MB limit`
            };
        }

        // Check file type
        if (!this.options.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                error: `Invalid file type. Allowed: ${this.options.allowedTypes.join(', ')}`
            };
        }

        return { valid: true };
    }

    showPreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.querySelector('[data-avatar-preview]');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }

    async uploadFile(file) {
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('source', 'web');
        formData.append('replace', 'true');

        // Show progress bar
        this.showProgressBar();

        try {
            const response = await fetch('/api/user/avatar', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                },
                body: formData,
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Upload failed');
            }

            this.uploadId = result.upload_id;
            this.startProgressTracking();

        } catch (error) {
            this.hideProgressBar();
            throw error;
        }
    }

    startProgressTracking() {
        this.progressInterval = setInterval(async () => {
            try {
                const response = await fetch(`/api/uploads/${this.uploadId}/progress`, {
                    headers: {
                        'Authorization': `Bearer ${this.getAuthToken()}`,
                    },
                });

                if (response.ok) {
                    const progress = await response.json();
                    this.updateProgress(progress);

                    if (progress.status === 'complete' || progress.status === 'error') {
                        this.stopProgressTracking();

                        if (progress.status === 'complete') {
                            this.onUploadComplete(progress);
                        } else {
                            this.showError(progress.message);
                        }
                    }
                }
            } catch (error) {
                console.error('Progress tracking error:', error);
            }
        }, this.options.progressUpdateInterval);
    }

    stopProgressTracking() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
    }

    updateProgress(progress) {
        const progressBar = document.querySelector('[data-progress-bar]');
        const progressText = document.querySelector('[data-progress-text]');

        if (progressBar) {
            progressBar.style.width = `${progress.progress}%`;
        }

        if (progressText) {
            progressText.textContent = progress.message;
        }
    }

    showProgressBar() {
        const progressContainer = document.querySelector('[data-progress-container]');
        if (progressContainer) {
            progressContainer.style.display = 'block';
        }
    }

    hideProgressBar() {
        const progressContainer = document.querySelector('[data-progress-container]');
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
    }

    onUploadComplete(progress) {
        this.hideProgressBar();

        // Update avatar display
        const avatarImg = document.querySelector('[data-current-avatar]');
        if (avatarImg && progress.data?.urls?.avatar) {
            avatarImg.src = progress.data.urls.avatar + '?t=' + Date.now(); // Cache bust
        }

        this.showSuccess('Avatar updated successfully!');
    }

    showError(message) {
        const errorContainer = document.querySelector('[data-error-message]');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';
        }
    }

    showSuccess(message) {
        const successContainer = document.querySelector('[data-success-message]');
        if (successContainer) {
            successContainer.textContent = message;
            successContainer.style.display = 'block';

            // Hide after 3 seconds
            setTimeout(() => {
                successContainer.style.display = 'none';
            }, 3000);
        }
    }

    getAuthToken() {
        return localStorage.getItem('auth_token') ||
               document.querySelector('meta[name="api-token"]')?.content;
    }
}

// Initialize avatar upload functionality
document.addEventListener('DOMContentLoaded', () => {
    new AvatarUpload();
});

export default AvatarUpload;
```

### 6.6.5. Image Optimization and Processing

Comprehensive image optimization strategies for the Chinook music platform, covering format selection, compression, responsive images, and performance optimization.

#### Advanced Image Processing Service

```php
<?php
// app/Services/ImageOptimizationService.php

namespace App\Services;

use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class ImageOptimizationService
{
    private ImageManager $imageManager;

    private array $formatSettings = [
        'jpeg' => [
            'quality' => 85,
            'progressive' => true,
            'use_cases' => ['photos', 'complex_images', 'gradients'],
        ],
        'webp' => [
            'quality' => 80,
            'lossless' => false,
            'use_cases' => ['modern_browsers', 'best_compression'],
        ],
        'png' => [
            'compression' => 9,
            'use_cases' => ['transparency', 'simple_graphics', 'logos'],
        ],
        'avif' => [
            'quality' => 75,
            'use_cases' => ['next_gen_browsers', 'maximum_compression'],
        ],
    ];

    private array $responsiveSizes = [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'small' => ['width' => 300, 'height' => 300],
        'medium' => ['width' => 600, 'height' => 600],
        'large' => ['width' => 1200, 'height' => 1200],
        'xlarge' => ['width' => 2400, 'height' => 2400],
    ];

    public function __construct()
    {
        $this->imageManager = new ImageManager(['driver' => 'gd']);
    }

    public function optimizeForWeb(Media $media, array $options = []): array
    {
        $originalPath = $media->getPath();
        $optimizedVersions = [];

        // Generate multiple formats for modern browsers
        $formats = $options['formats'] ?? ['webp', 'jpeg'];

        foreach ($formats as $format) {
            $optimizedVersions[$format] = $this->convertToFormat($media, $format, $options);
        }

        // Generate responsive sizes
        if ($options['responsive'] ?? true) {
            $optimizedVersions['responsive'] = $this->generateResponsiveSizes($media, $options);
        }

        // Generate progressive JPEG if original is JPEG
        if ($media->mime_type === 'image/jpeg') {
            $optimizedVersions['progressive'] = $this->createProgressiveJpeg($media);
        }

        return $optimizedVersions;
    }

    public function convertToFormat(Media $media, string $targetFormat, array $options = []): string
    {
        $cacheKey = "optimized_image_{$media->id}_{$targetFormat}_" . md5(serialize($options));

        return Cache::remember($cacheKey, 3600, function () use ($media, $targetFormat, $options) {
            $image = $this->imageManager->make($media->getPath());

            // Apply format-specific optimizations
            $this->applyFormatOptimizations($image, $targetFormat, $options);

            // Generate optimized filename
            $optimizedPath = $this->getOptimizedPath($media, $targetFormat);

            // Save optimized version
            $image->save($optimizedPath);

            // Upload to CDN if configured
            if ($options['upload_to_cdn'] ?? true) {
                $this->uploadToCDN($optimizedPath, $media, $targetFormat);
            }

            return $optimizedPath;
        });
    }

    public function generateResponsiveSizes(Media $media, array $options = []): array
    {
        $responsiveVersions = [];
        $sizes = $options['sizes'] ?? $this->responsiveSizes;

        foreach ($sizes as $sizeName => $dimensions) {
            $responsiveVersions[$sizeName] = $this->createResponsiveVersion(
                $media,
                $sizeName,
                $dimensions,
                $options
            );
        }

        return $responsiveVersions;
    }

    private function applyFormatOptimizations($image, string $format, array $options): void
    {
        $settings = $this->formatSettings[$format] ?? [];

        switch ($format) {
            case 'jpeg':
                $image->encode('jpg', $settings['quality']);
                if ($settings['progressive']) {
                    $image->interlace(true);
                }
                break;

            case 'webp':
                $image->encode('webp', $settings['quality']);
                break;

            case 'png':
                $image->encode('png', $settings['compression']);
                break;

            case 'avif':
                // Note: Requires imagemagick with AVIF support
                $image->encode('avif', $settings['quality']);
                break;
        }

        // Apply common optimizations
        $this->applyCommonOptimizations($image, $options);
    }

    private function applyCommonOptimizations($image, array $options): void
    {
        // Auto-orient based on EXIF data
        $image->orientate();

        // Apply sharpening if requested
        if ($options['sharpen'] ?? false) {
            $image->sharpen($options['sharpen_amount'] ?? 10);
        }

        // Apply contrast enhancement
        if ($options['enhance_contrast'] ?? false) {
            $image->contrast($options['contrast_amount'] ?? 5);
        }

        // Remove metadata to reduce file size
        if ($options['strip_metadata'] ?? true) {
            // This removes EXIF data, color profiles, etc.
            $image->limitColors(256); // For PNG optimization
        }
    }

    private function createResponsiveVersion(Media $media, string $sizeName, array $dimensions, array $options): string
    {
        $image = $this->imageManager->make($media->getPath());

        // Calculate optimal dimensions maintaining aspect ratio
        $optimalDimensions = $this->calculateOptimalDimensions(
            $image->width(),
            $image->height(),
            $dimensions['width'],
            $dimensions['height']
        );

        // Resize with high-quality resampling
        $image->resize(
            $optimalDimensions['width'],
            $optimalDimensions['height'],
            function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize(); // Prevent upscaling
            }
        );

        // Apply size-specific optimizations
        $this->applySizeSpecificOptimizations($image, $sizeName, $options);

        $responsivePath = $this->getResponsivePath($media, $sizeName);
        $image->save($responsivePath);

        return $responsivePath;
    }

    private function calculateOptimalDimensions(int $originalWidth, int $originalHeight, int $targetWidth, int $targetHeight): array
    {
        $aspectRatio = $originalWidth / $originalHeight;
        $targetAspectRatio = $targetWidth / $targetHeight;

        if ($aspectRatio > $targetAspectRatio) {
            // Original is wider
            $width = $targetWidth;
            $height = (int) ($targetWidth / $aspectRatio);
        } else {
            // Original is taller
            $height = $targetHeight;
            $width = (int) ($targetHeight * $aspectRatio);
        }

        return [
            'width' => min($width, $originalWidth),
            'height' => min($height, $originalHeight),
        ];
    }

    private function applySizeSpecificOptimizations($image, string $sizeName, array $options): void
    {
        switch ($sizeName) {
            case 'thumbnail':
                // More aggressive compression for thumbnails
                $image->sharpen(15);
                break;

            case 'small':
                $image->sharpen(10);
                break;

            case 'medium':
                $image->sharpen(5);
                break;

            case 'large':
            case 'xlarge':
                // Minimal sharpening for large images
                $image->sharpen(2);
                break;
        }
    }

    public function createProgressiveJpeg(Media $media): string
    {
        $image = $this->imageManager->make($media->getPath());
        $image->interlace(true);
        $image->encode('jpg', 85);

        $progressivePath = $this->getProgressivePath($media);
        $image->save($progressivePath);

        return $progressivePath;
    }

    public function generateImageSrcSet(Media $media, array $sizes = null): string
    {
        $sizes = $sizes ?? array_keys($this->responsiveSizes);
        $srcSetParts = [];

        foreach ($sizes as $sizeName) {
            if (isset($this->responsiveSizes[$sizeName])) {
                $width = $this->responsiveSizes[$sizeName]['width'];
                $url = $this->getResponsiveUrl($media, $sizeName);
                $srcSetParts[] = "{$url} {$width}w";
            }
        }

        return implode(', ', $srcSetParts);
    }

    public function getOptimalFormat(string $userAgent, array $supportedFormats = ['avif', 'webp', 'jpeg']): string
    {
        // Check for AVIF support (Chrome 85+, Firefox 93+)
        if (in_array('avif', $supportedFormats) && $this->supportsAvif($userAgent)) {
            return 'avif';
        }

        // Check for WebP support (most modern browsers)
        if (in_array('webp', $supportedFormats) && $this->supportsWebp($userAgent)) {
            return 'webp';
        }

        // Fallback to JPEG
        return 'jpeg';
    }

    private function supportsAvif(string $userAgent): bool
    {
        // Chrome 85+
        if (preg_match('/Chrome\/(\d+)/', $userAgent, $matches)) {
            return (int) $matches[1] >= 85;
        }

        // Firefox 93+
        if (preg_match('/Firefox\/(\d+)/', $userAgent, $matches)) {
            return (int) $matches[1] >= 93;
        }

        return false;
    }

    private function supportsWebp(string $userAgent): bool
    {
        return strpos($userAgent, 'Chrome') !== false ||
               strpos($userAgent, 'Firefox') !== false ||
               strpos($userAgent, 'Edge') !== false ||
               strpos($userAgent, 'Opera') !== false;
    }

    private function getOptimizedPath(Media $media, string $format): string
    {
        $directory = dirname($media->getPath());
        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        return "{$directory}/{$filename}_optimized.{$format}";
    }

    private function getResponsivePath(Media $media, string $sizeName): string
    {
        $directory = dirname($media->getPath());
        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
        return "{$directory}/{$filename}_{$sizeName}.{$extension}";
    }

    private function getProgressivePath(Media $media): string
    {
        $directory = dirname($media->getPath());
        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        return "{$directory}/{$filename}_progressive.jpg";
    }

    private function getResponsiveUrl(Media $media, string $sizeName): string
    {
        // This would integrate with your CDN service
        $baseUrl = config('media.cdn_url');
        $path = $this->getResponsivePath($media, $sizeName);
        return "{$baseUrl}/" . basename($path);
    }

    private function uploadToCDN(string $localPath, Media $media, string $format): void
    {
        // Upload optimized version to CDN
        $cdnPath = "optimized/{$media->id}/{$format}/" . basename($localPath);
        Storage::disk('image_cdn')->put($cdnPath, file_get_contents($localPath));
    }
}
```

#### Performance-Optimized Image Delivery

```php
<?php
// app/Http/Controllers/OptimizedImageController.php

namespace App\Http\Controllers;

use App\Services\ImageOptimizationService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class OptimizedImageController extends Controller
{
    public function __construct(
        private ImageOptimizationService $imageOptimizer
    ) {}

    public function serve(Request $request, Media $media): Response
    {
        // Determine optimal format based on browser support
        $userAgent = $request->userAgent();
        $optimalFormat = $this->imageOptimizer->getOptimalFormat($userAgent);

        // Get requested size or default to original
        $size = $request->query('size', 'original');
        $quality = $request->query('quality', 'auto');

        // Generate cache key
        $cacheKey = "optimized_image_{$media->id}_{$optimalFormat}_{$size}_{$quality}";

        // Check if optimized version exists in cache
        $optimizedPath = Cache::remember($cacheKey, 86400, function () use ($media, $optimalFormat, $size, $quality) {
            return $this->generateOptimizedImage($media, $optimalFormat, $size, $quality);
        });

        // Set appropriate headers
        $headers = [
            'Content-Type' => "image/{$optimalFormat}",
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Vary' => 'Accept',
            'X-Image-Format' => $optimalFormat,
            'X-Image-Size' => $size,
        ];

        // Add WebP/AVIF hint headers
        if ($optimalFormat === 'webp') {
            $headers['Content-Type'] = 'image/webp';
        } elseif ($optimalFormat === 'avif') {
            $headers['Content-Type'] = 'image/avif';
        }

        return response()->file($optimizedPath, $headers);
    }

    public function generateSrcSet(Request $request, Media $media): array
    {
        $userAgent = $request->userAgent();
        $optimalFormat = $this->imageOptimizer->getOptimalFormat($userAgent);

        $srcSet = $this->imageOptimizer->generateImageSrcSet($media);
        $sizes = $request->query('sizes', '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw');

        return [
            'srcset' => $srcSet,
            'sizes' => $sizes,
            'format' => $optimalFormat,
            'fallback' => $media->getUrl(),
        ];
    }

    private function generateOptimizedImage(Media $media, string $format, string $size, string $quality): string
    {
        $options = [
            'formats' => [$format],
            'quality' => $this->getQualityValue($quality),
            'responsive' => $size !== 'original',
            'sharpen' => true,
            'strip_metadata' => true,
        ];

        if ($size === 'original') {
            return $this->imageOptimizer->convertToFormat($media, $format, $options);
        } else {
            $responsiveVersions = $this->imageOptimizer->generateResponsiveSizes($media, $options);
            return $responsiveVersions[$size] ?? $responsiveVersions['medium'];
        }
    }

    private function getQualityValue(string $quality): int
    {
        return match($quality) {
            'low' => 60,
            'medium' => 75,
            'high' => 85,
            'ultra' => 95,
            'auto' => 80,
            default => 80,
        };
    }
}
```

#### Frontend Image Optimization Integration

```javascript
// resources/js/components/optimized-image.js

class OptimizedImage {
    constructor(element, options = {}) {
        this.element = element;
        this.options = {
            lazyLoad: true,
            responsiveSizes: true,
            formatDetection: true,
            qualityAdaptation: true,
            loadingPlaceholder: true,
            errorFallback: true,
            ...options
        };

        this.init();
    }

    init() {
        if (this.options.lazyLoad) {
            this.setupLazyLoading();
        }

        if (this.options.responsiveSizes) {
            this.setupResponsiveImages();
        }

        if (this.options.formatDetection) {
            this.setupFormatDetection();
        }

        if (this.options.loadingPlaceholder) {
            this.setupLoadingPlaceholder();
        }

        if (this.options.errorFallback) {
            this.setupErrorFallback();
        }
    }

    setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage();
                        observer.unobserve(this.element);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            observer.observe(this.element);
        } else {
            // Fallback for older browsers
            this.loadImage();
        }
    }

    setupResponsiveImages() {
        const mediaId = this.element.dataset.mediaId;
        if (!mediaId) return;

        // Generate responsive srcset
        fetch(`/api/media/${mediaId}/srcset`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        })
        .then(response => response.json())
        .then(data => {
            this.element.srcset = data.srcset;
            this.element.sizes = data.sizes;
        })
        .catch(error => {
            console.warn('Failed to load responsive images:', error);
        });
    }

    setupFormatDetection() {
        // Detect browser support for modern formats
        const supportsWebP = this.checkWebPSupport();
        const supportsAVIF = this.checkAVIFSupport();

        let format = 'jpeg'; // fallback

        if (supportsAVIF) {
            format = 'avif';
        } else if (supportsWebP) {
            format = 'webp';
        }

        // Update image URL with optimal format
        const originalSrc = this.element.dataset.originalSrc || this.element.src;
        if (originalSrc) {
            const url = new URL(originalSrc, window.location.origin);
            url.searchParams.set('format', format);
            this.element.dataset.optimizedSrc = url.toString();
        }
    }

    setupLoadingPlaceholder() {
        // Create a low-quality placeholder
        const placeholder = this.generatePlaceholder();
        this.element.style.backgroundImage = `url(${placeholder})`;
        this.element.style.backgroundSize = 'cover';
        this.element.style.backgroundPosition = 'center';

        // Remove placeholder when image loads
        this.element.addEventListener('load', () => {
            this.element.style.backgroundImage = '';
        });
    }

    setupErrorFallback() {
        this.element.addEventListener('error', () => {
            // Try fallback formats
            this.tryFallbackFormats();
        });
    }

    loadImage() {
        const src = this.element.dataset.optimizedSrc ||
                   this.element.dataset.src ||
                   this.element.src;

        if (src && src !== this.element.src) {
            // Add loading class
            this.element.classList.add('loading');

            // Preload image
            const img = new Image();
            img.onload = () => {
                this.element.src = src;
                this.element.classList.remove('loading');
                this.element.classList.add('loaded');

                // Trigger custom event
                this.element.dispatchEvent(new CustomEvent('imageLoaded', {
                    detail: { src, loadTime: performance.now() }
                }));
            };

            img.onerror = () => {
                this.element.classList.remove('loading');
                this.element.classList.add('error');
                this.tryFallbackFormats();
            };

            img.src = src;
        }
    }

    checkWebPSupport() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }

    checkAVIFSupport() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        try {
            return canvas.toDataURL('image/avif').indexOf('data:image/avif') === 0;
        } catch (e) {
            return false;
        }
    }

    generatePlaceholder() {
        // Generate a simple SVG placeholder
        const width = this.element.dataset.width || 300;
        const height = this.element.dataset.height || 200;

        const svg = `
            <svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#f0f0f0;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#e0e0e0;stop-opacity:1" />
                    </linearGradient>
                </defs>
                <rect width="100%" height="100%" fill="url(#grad)" />
                <text x="50%" y="50%" text-anchor="middle" dy=".3em"
                      font-family="Arial, sans-serif" font-size="14" fill="#999">
                    Loading...
                </text>
            </svg>
        `;

        return 'data:image/svg+xml;base64,' + btoa(svg);
    }

    tryFallbackFormats() {
        const fallbackFormats = ['jpeg', 'png'];
        const originalSrc = this.element.dataset.originalSrc || this.element.src;

        if (!originalSrc) return;

        for (const format of fallbackFormats) {
            const url = new URL(originalSrc, window.location.origin);
            url.searchParams.set('format', format);

            const img = new Image();
            img.onload = () => {
                this.element.src = url.toString();
                this.element.classList.remove('error');
                this.element.classList.add('loaded');
                break;
            };
            img.src = url.toString();
        }
    }

    // Quality adaptation based on connection speed
    adaptQuality() {
        if ('connection' in navigator) {
            const connection = navigator.connection;
            let quality = 'auto';

            switch (connection.effectiveType) {
                case 'slow-2g':
                case '2g':
                    quality = 'low';
                    break;
                case '3g':
                    quality = 'medium';
                    break;
                case '4g':
                    quality = 'high';
                    break;
            }

            // Update image URL with adaptive quality
            const src = this.element.dataset.optimizedSrc || this.element.src;
            if (src) {
                const url = new URL(src, window.location.origin);
                url.searchParams.set('quality', quality);
                this.element.dataset.optimizedSrc = url.toString();
            }
        }
    }
}

// Auto-initialize optimized images
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-optimized-image]').forEach(img => {
        new OptimizedImage(img);
    });
});

// Livewire integration
document.addEventListener('livewire:navigated', () => {
    document.querySelectorAll('[data-optimized-image]').forEach(img => {
        if (!img.optimizedImageInstance) {
            img.optimizedImageInstance = new OptimizedImage(img);
        }
    });
});

export default OptimizedImage;
```

#### Image Optimization Best Practices

**Format Selection Guidelines:**

1. **AVIF**: Best compression, use for modern browsers (Chrome 85+, Firefox 93+)
2. **WebP**: Good compression, wide browser support (95%+ of users)
3. **JPEG**: Universal fallback, use progressive encoding
4. **PNG**: Only for images requiring transparency

**Performance Optimization Checklist:**

- ✅ **Responsive Images**: Generate multiple sizes for different viewports
- ✅ **Format Detection**: Serve optimal format based on browser support
- ✅ **Lazy Loading**: Load images only when needed
- ✅ **Progressive Enhancement**: Start with low-quality placeholder
- ✅ **CDN Integration**: Serve optimized images from global CDN
- ✅ **Caching Strategy**: Implement aggressive caching with proper headers
- ✅ **Quality Adaptation**: Adjust quality based on connection speed
- ✅ **Metadata Stripping**: Remove unnecessary EXIF data

**Quality vs. File Size Optimization:**

| Use Case | JPEG Quality | WebP Quality | AVIF Quality | Notes |
|----------|--------------|--------------|--------------|-------|
| Thumbnails | 75-80 | 70-75 | 65-70 | Small size priority |
| Profile Photos | 80-85 | 75-80 | 70-75 | Balance quality/size |
| Album Covers | 85-90 | 80-85 | 75-80 | Quality important |
| Hero Images | 90-95 | 85-90 | 80-85 | Maximum quality |

## Performance Optimization

### CDN Integration

### Caching Strategies

### Storage Optimization

#### 6.6.6. Media Conversion Workflows

Comprehensive audio and media conversion workflows for the Chinook music platform, supporting multiple formats, quality levels, and real-time processing with progress tracking.

#### Advanced Audio Conversion Service

```php
<?php
// app/Services/AudioConversionService.php

namespace App\Services;

use App\Models\Track;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class AudioConversionService
{
    private array $qualityPresets = [
        'low' => [
            'bitrate' => '128k',
            'sample_rate' => '44100',
            'channels' => '2',
            'format' => 'mp3',
            'use_case' => 'Preview clips, mobile streaming',
        ],
        'medium' => [
            'bitrate' => '256k',
            'sample_rate' => '44100',
            'channels' => '2',
            'format' => 'mp3',
            'use_case' => 'Standard streaming quality',
        ],
        'high' => [
            'bitrate' => '320k',
            'sample_rate' => '44100',
            'channels' => '2',
            'format' => 'mp3',
            'use_case' => 'High-quality streaming',
        ],
        'lossless' => [
            'bitrate' => null,
            'sample_rate' => '44100',
            'channels' => '2',
            'format' => 'flac',
            'use_case' => 'Audiophile quality, archival',
        ],
    ];

    private array $formatSupport = [
        'input' => ['mp3', 'flac', 'wav', 'aac', 'm4a', 'ogg', 'wma'],
        'output' => ['mp3', 'flac', 'wav', 'aac', 'ogg'],
    ];

    public function convertTrack(Track $track, array $qualities = ['low', 'medium', 'high'], array $options = []): array
    {
        $originalMedia = $track->getFirstMedia('audio_files');
        if (!$originalMedia) {
            throw new \InvalidArgumentException('No audio file found for track');
        }

        $conversionResults = [];
        $conversionId = Str::uuid();

        // Initialize progress tracking
        $this->initializeConversionProgress($conversionId, count($qualities));

        foreach ($qualities as $index => $quality) {
            try {
                $this->updateConversionProgress($conversionId, $index, count($qualities), "Converting to {$quality} quality...");

                $convertedMedia = $this->convertToQuality($originalMedia, $quality, $options);
                $conversionResults[$quality] = $convertedMedia;

                // Generate waveform for visual representation
                if ($quality === 'medium' && ($options['generate_waveform'] ?? true)) {
                    $this->generateWaveform($convertedMedia, $options);
                }

            } catch (\Exception $e) {
                $conversionResults[$quality] = [
                    'error' => $e->getMessage(),
                    'status' => 'failed',
                ];

                \Log::error('Audio conversion failed', [
                    'track_id' => $track->id,
                    'quality' => $quality,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->updateConversionProgress($conversionId, count($qualities), count($qualities), 'Conversion completed');

        return [
            'conversion_id' => $conversionId,
            'results' => $conversionResults,
            'original_media' => $originalMedia,
        ];
    }

    public function convertToQuality(Media $originalMedia, string $quality, array $options = []): Media
    {
        $preset = $this->qualityPresets[$quality] ?? throw new \InvalidArgumentException("Unknown quality preset: {$quality}");

        // Generate output filename
        $outputPath = $this->generateOutputPath($originalMedia, $quality, $preset['format']);

        // Build FFmpeg command
        $command = $this->buildFFmpegCommand($originalMedia->getPath(), $outputPath, $preset, $options);

        // Execute conversion
        $result = Process::timeout(3600)->run($command); // 1 hour timeout

        if (!$result->successful()) {
            throw new \RuntimeException("FFmpeg conversion failed: " . $result->errorOutput());
        }

        // Create new media record for converted file
        $convertedMedia = $originalMedia->model
            ->addMedia($outputPath)
            ->withCustomProperties([
                'quality' => $quality,
                'original_media_id' => $originalMedia->id,
                'conversion_preset' => $preset,
                'file_size' => filesize($outputPath),
                'duration' => $this->getAudioDuration($outputPath),
                'bitrate' => $preset['bitrate'],
                'sample_rate' => $preset['sample_rate'],
                'channels' => $preset['channels'],
                'format' => $preset['format'],
                'converted_at' => now()->toISOString(),
            ])
            ->usingName($originalMedia->name . " ({$quality} quality)")
            ->usingFileName(basename($outputPath))
            ->toMediaCollection("audio_{$quality}", 'audio_cdn');

        // Clean up temporary file
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

        return $convertedMedia;
    }

    public function generateWaveform(Media $audioMedia, array $options = []): Media
    {
        $waveformPath = $this->generateWaveformPath($audioMedia);

        // FFmpeg command to generate waveform image
        $command = [
            'ffmpeg',
            '-i', $audioMedia->getPath(),
            '-filter_complex', 'showwavespic=s=1200x300:colors=0x3b82f6|0x1e40af',
            '-frames:v', '1',
            '-y', // Overwrite output file
            $waveformPath
        ];

        $result = Process::timeout(300)->run($command); // 5 minute timeout

        if (!$result->successful()) {
            throw new \RuntimeException("Waveform generation failed: " . $result->errorOutput());
        }

        // Create media record for waveform
        $waveformMedia = $audioMedia->model
            ->addMedia($waveformPath)
            ->withCustomProperties([
                'audio_media_id' => $audioMedia->id,
                'waveform_width' => 1200,
                'waveform_height' => 300,
                'generated_at' => now()->toISOString(),
            ])
            ->usingName($audioMedia->name . ' Waveform')
            ->usingFileName(basename($waveformPath))
            ->toMediaCollection('waveforms', 'image_cdn');

        // Clean up temporary file
        if (file_exists($waveformPath)) {
            unlink($waveformPath);
        }

        return $waveformMedia;
    }

    public function generatePreviewClip(Media $audioMedia, array $options = []): Media
    {
        $startTime = $options['start_time'] ?? 30; // Start at 30 seconds
        $duration = $options['duration'] ?? 30; // 30 second preview
        $quality = $options['quality'] ?? 'medium';

        $preset = $this->qualityPresets[$quality];
        $previewPath = $this->generatePreviewPath($audioMedia, $startTime, $duration);

        // FFmpeg command for preview clip
        $command = [
            'ffmpeg',
            '-i', $audioMedia->getPath(),
            '-ss', (string) $startTime,
            '-t', (string) $duration,
            '-b:a', $preset['bitrate'],
            '-ar', $preset['sample_rate'],
            '-ac', $preset['channels'],
            '-f', $preset['format'],
            '-y',
            $previewPath
        ];

        $result = Process::timeout(120)->run($command); // 2 minute timeout

        if (!$result->successful()) {
            throw new \RuntimeException("Preview generation failed: " . $result->errorOutput());
        }

        // Create media record for preview
        $previewMedia = $audioMedia->model
            ->addMedia($previewPath)
            ->withCustomProperties([
                'audio_media_id' => $audioMedia->id,
                'start_time' => $startTime,
                'duration' => $duration,
                'quality' => $quality,
                'is_preview' => true,
                'generated_at' => now()->toISOString(),
            ])
            ->usingName($audioMedia->name . ' Preview')
            ->usingFileName(basename($previewPath))
            ->toMediaCollection('previews', 'audio_cdn');

        // Clean up temporary file
        if (file_exists($previewPath)) {
            unlink($previewPath);
        }

        return $previewMedia;
    }

    private function buildFFmpegCommand(string $inputPath, string $outputPath, array $preset, array $options): array
    {
        $command = ['ffmpeg', '-i', $inputPath];

        // Audio codec and quality settings
        if ($preset['format'] === 'mp3') {
            $command[] = '-codec:a';
            $command[] = 'libmp3lame';
        } elseif ($preset['format'] === 'flac') {
            $command[] = '-codec:a';
            $command[] = 'flac';
        } elseif ($preset['format'] === 'aac') {
            $command[] = '-codec:a';
            $command[] = 'aac';
        }

        // Bitrate (not applicable for lossless)
        if ($preset['bitrate']) {
            $command[] = '-b:a';
            $command[] = $preset['bitrate'];
        }

        // Sample rate
        $command[] = '-ar';
        $command[] = $preset['sample_rate'];

        // Channels
        $command[] = '-ac';
        $command[] = $preset['channels'];

        // Additional options
        if ($options['normalize'] ?? false) {
            $command[] = '-filter:a';
            $command[] = 'loudnorm';
        }

        if ($options['fade_in'] ?? false) {
            $command[] = '-af';
            $command[] = 'afade=t=in:ss=0:d=2';
        }

        if ($options['fade_out'] ?? false) {
            $duration = $this->getAudioDuration($inputPath);
            $fadeStart = max(0, $duration - 2);
            $command[] = '-af';
            $command[] = "afade=t=out:st={$fadeStart}:d=2";
        }

        // Output format
        $command[] = '-f';
        $command[] = $preset['format'];

        // Overwrite output file
        $command[] = '-y';
        $command[] = $outputPath;

        return $command;
    }

    private function getAudioDuration(string $filePath): float
    {
        $command = [
            'ffprobe',
            '-v', 'quiet',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $filePath
        ];

        $result = Process::run($command);

        if ($result->successful()) {
            return (float) trim($result->output());
        }

        return 0.0;
    }

    private function generateOutputPath(Media $media, string $quality, string $format): string
    {
        $tempDir = storage_path('app/temp/conversions');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        return "{$tempDir}/{$filename}_{$quality}.{$format}";
    }

    private function generateWaveformPath(Media $media): string
    {
        $tempDir = storage_path('app/temp/waveforms');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        return "{$tempDir}/{$filename}_waveform.png";
    }

    private function generatePreviewPath(Media $media, int $startTime, int $duration): string
    {
        $tempDir = storage_path('app/temp/previews');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        return "{$tempDir}/{$filename}_preview_{$startTime}s_{$duration}s.mp3";
    }

    private function initializeConversionProgress(string $conversionId, int $totalSteps): void
    {
        Cache::put("conversion_progress_{$conversionId}", [
            'conversion_id' => $conversionId,
            'total_steps' => $totalSteps,
            'current_step' => 0,
            'progress_percentage' => 0,
            'status' => 'starting',
            'message' => 'Initializing conversion...',
            'started_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ], 3600); // 1 hour TTL
    }

    private function updateConversionProgress(string $conversionId, int $currentStep, int $totalSteps, string $message): void
    {
        $progressPercentage = ($currentStep / $totalSteps) * 100;

        $data = Cache::get("conversion_progress_{$conversionId}", []);
        $data['current_step'] = $currentStep;
        $data['progress_percentage'] = $progressPercentage;
        $data['message'] = $message;
        $data['status'] = $progressPercentage >= 100 ? 'completed' : 'processing';
        $data['updated_at'] = now()->toISOString();

        if ($progressPercentage >= 100) {
            $data['completed_at'] = now()->toISOString();
        }

        Cache::put("conversion_progress_{$conversionId}", $data, 3600);

        // Broadcast progress update if WebSocket is available
        if (class_exists('\Pusher\Pusher')) {
            broadcast(new \App\Events\ConversionProgressUpdated($conversionId, $data));
        }
    }
}
```

### 6.6.7. File Storage and Retrieval Patterns

Comprehensive file storage and retrieval patterns for the Chinook music platform, supporting multiple storage backends, intelligent tiering, and optimized access patterns.

#### Multi-Backend Storage Service

```php
<?php
// app/Services/FileStorageService.php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Carbon\Carbon;

class FileStorageService
{
    private array $storageConfig = [
        'hot_tier' => [
            'disk' => 'cloudflare_r2',
            'ttl_days' => 30,
            'use_cases' => ['recent_uploads', 'popular_content', 'user_avatars'],
            'cost_per_gb' => 0.015,
        ],
        'warm_tier' => [
            'disk' => 's3_standard',
            'ttl_days' => 365,
            'use_cases' => ['regular_access', 'album_covers', 'track_files'],
            'cost_per_gb' => 0.023,
        ],
        'cold_tier' => [
            'disk' => 's3_glacier',
            'ttl_days' => null, // Permanent
            'use_cases' => ['archive', 'backup', 'old_content'],
            'cost_per_gb' => 0.004,
        ],
    ];

    private array $accessPatterns = [
        'immediate' => ['user_avatars', 'thumbnails', 'previews'],
        'frequent' => ['album_covers', 'artist_photos', 'recent_tracks'],
        'occasional' => ['full_tracks', 'promotional_materials'],
        'rare' => ['archive_content', 'backup_files'],
    ];

    public function storeFile(string $filePath, string $collection, array $metadata = []): array
    {
        // Determine optimal storage tier based on collection and metadata
        $tier = $this->determineStorageTier($collection, $metadata);
        $disk = $this->storageConfig[$tier]['disk'];

        // Generate storage path with intelligent organization
        $storagePath = $this->generateStoragePath($filePath, $collection, $metadata);

        // Store file with appropriate settings
        $stored = Storage::disk($disk)->put($storagePath, file_get_contents($filePath), [
            'visibility' => $this->getVisibility($collection),
            'metadata' => array_merge($metadata, [
                'tier' => $tier,
                'collection' => $collection,
                'stored_at' => now()->toISOString(),
                'original_name' => basename($filePath),
            ]),
        ]);

        if (!$stored) {
            throw new \RuntimeException("Failed to store file to {$tier} tier");
        }

        // Create retrieval record for tracking
        $this->createRetrievalRecord($storagePath, $tier, $collection, $metadata);

        // Set up automatic tiering if applicable
        $this->scheduleAutomaticTiering($storagePath, $tier, $collection);

        return [
            'storage_path' => $storagePath,
            'tier' => $tier,
            'disk' => $disk,
            'url' => $this->generateAccessUrl($storagePath, $tier),
            'metadata' => $metadata,
        ];
    }

    public function retrieveFile(string $storagePath, array $options = []): array
    {
        // Track access for analytics and tiering decisions
        $this->trackFileAccess($storagePath);

        // Determine current storage location
        $location = $this->locateFile($storagePath);

        if (!$location) {
            throw new \RuntimeException("File not found: {$storagePath}");
        }

        // Check if file needs to be promoted to higher tier
        if ($this->shouldPromoteFile($storagePath, $location['tier'])) {
            $this->promoteFile($storagePath, $location['tier']);
            $location = $this->locateFile($storagePath); // Refresh location
        }

        // Generate appropriate access method
        $accessMethod = $options['access_method'] ?? $this->determineAccessMethod($location['tier']);

        return [
            'url' => $this->generateAccessUrl($storagePath, $location['tier'], $accessMethod),
            'tier' => $location['tier'],
            'disk' => $location['disk'],
            'expires_at' => $this->getUrlExpiration($location['tier']),
            'access_method' => $accessMethod,
            'metadata' => $location['metadata'],
        ];
    }

    public function moveToTier(string $storagePath, string $targetTier, array $options = []): bool
    {
        $currentLocation = $this->locateFile($storagePath);

        if (!$currentLocation) {
            throw new \RuntimeException("File not found for tier migration: {$storagePath}");
        }

        if ($currentLocation['tier'] === $targetTier) {
            return true; // Already in target tier
        }

        $sourceDisk = $currentLocation['disk'];
        $targetDisk = $this->storageConfig[$targetTier]['disk'];

        // Copy file to target tier
        $fileContent = Storage::disk($sourceDisk)->get($storagePath);
        $stored = Storage::disk($targetDisk)->put($storagePath, $fileContent, [
            'visibility' => Storage::disk($sourceDisk)->getVisibility($storagePath),
            'metadata' => array_merge($currentLocation['metadata'], [
                'tier' => $targetTier,
                'migrated_at' => now()->toISOString(),
                'previous_tier' => $currentLocation['tier'],
            ]),
        ]);

        if (!$stored) {
            throw new \RuntimeException("Failed to migrate file to {$targetTier} tier");
        }

        // Update retrieval record
        $this->updateRetrievalRecord($storagePath, $targetTier, $targetDisk);

        // Delete from source tier (unless it's a backup operation)
        if (!($options['keep_source'] ?? false)) {
            Storage::disk($sourceDisk)->delete($storagePath);
        }

        // Log migration
        \Log::info('File migrated between tiers', [
            'storage_path' => $storagePath,
            'from_tier' => $currentLocation['tier'],
            'to_tier' => $targetTier,
            'reason' => $options['reason'] ?? 'manual',
        ]);

        return true;
    }

    public function generateSignedUrl(string $storagePath, int $expirationMinutes = 60, array $options = []): string
    {
        $location = $this->locateFile($storagePath);

        if (!$location) {
            throw new \RuntimeException("File not found for signed URL generation: {$storagePath}");
        }

        $disk = Storage::disk($location['disk']);

        // Generate signed URL with appropriate expiration
        $expiration = now()->addMinutes($expirationMinutes);

        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($storagePath, $expiration, $options);
        }

        // Fallback for disks that don't support signed URLs
        return $this->generateProxyUrl($storagePath, $expiration, $options);
    }

    public function optimizeStorageCosts(): array
    {
        $optimizations = [];

        // Find files that can be moved to cheaper tiers
        $candidates = $this->findTieringCandidates();

        foreach ($candidates as $candidate) {
            try {
                $this->moveToTier(
                    $candidate['storage_path'],
                    $candidate['recommended_tier'],
                    ['reason' => 'cost_optimization']
                );

                $optimizations[] = [
                    'file' => $candidate['storage_path'],
                    'from_tier' => $candidate['current_tier'],
                    'to_tier' => $candidate['recommended_tier'],
                    'estimated_savings' => $candidate['estimated_savings'],
                ];
            } catch (\Exception $e) {
                \Log::warning('Failed to optimize file storage', [
                    'file' => $candidate['storage_path'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $optimizations;
    }

    private function determineStorageTier(string $collection, array $metadata): string
    {
        // Check if collection has specific tier requirements
        $collectionTier = $this->getCollectionTier($collection);
        if ($collectionTier) {
            return $collectionTier;
        }

        // Determine tier based on file characteristics
        $fileSize = $metadata['file_size'] ?? 0;
        $accessPattern = $this->getAccessPattern($collection);

        return match($accessPattern) {
            'immediate' => 'hot_tier',
            'frequent' => $fileSize > 50 * 1024 * 1024 ? 'warm_tier' : 'hot_tier', // 50MB threshold
            'occasional' => 'warm_tier',
            'rare' => 'cold_tier',
            default => 'warm_tier',
        };
    }

    private function generateStoragePath(string $filePath, string $collection, array $metadata): string
    {
        $date = now()->format('Y/m/d');
        $filename = basename($filePath);
        $hash = substr(hash('sha256', $filename . time()), 0, 8);

        // Organize by collection and date for efficient retrieval
        return "{$collection}/{$date}/{$hash}_{$filename}";
    }

    private function getVisibility(string $collection): string
    {
        $publicCollections = ['avatars', 'thumbnails', 'album_covers', 'artist_photos'];
        return in_array($collection, $publicCollections) ? 'public' : 'private';
    }

    private function createRetrievalRecord(string $storagePath, string $tier, string $collection, array $metadata): void
    {
        Cache::put("file_location_{$storagePath}", [
            'storage_path' => $storagePath,
            'tier' => $tier,
            'disk' => $this->storageConfig[$tier]['disk'],
            'collection' => $collection,
            'metadata' => $metadata,
            'created_at' => now()->toISOString(),
            'last_accessed' => now()->toISOString(),
            'access_count' => 0,
        ], 86400 * 30); // 30 days cache
    }

    private function locateFile(string $storagePath): ?array
    {
        // Check cache first
        $cached = Cache::get("file_location_{$storagePath}");
        if ($cached) {
            return $cached;
        }

        // Search across all tiers
        foreach ($this->storageConfig as $tier => $config) {
            if (Storage::disk($config['disk'])->exists($storagePath)) {
                $location = [
                    'storage_path' => $storagePath,
                    'tier' => $tier,
                    'disk' => $config['disk'],
                    'metadata' => $this->getFileMetadata($storagePath, $config['disk']),
                ];

                // Cache the location
                Cache::put("file_location_{$storagePath}", $location, 86400);

                return $location;
            }
        }

        return null;
    }

    private function trackFileAccess(string $storagePath): void
    {
        $key = "file_access_{$storagePath}";
        $accessData = Cache::get($key, [
            'access_count' => 0,
            'last_accessed' => null,
            'access_pattern' => [],
        ]);

        $accessData['access_count']++;
        $accessData['last_accessed'] = now()->toISOString();
        $accessData['access_pattern'][] = now()->timestamp;

        // Keep only last 100 access timestamps
        $accessData['access_pattern'] = array_slice($accessData['access_pattern'], -100);

        Cache::put($key, $accessData, 86400 * 7); // 7 days
    }

    private function shouldPromoteFile(string $storagePath, string $currentTier): bool
    {
        if ($currentTier === 'hot_tier') {
            return false; // Already in highest tier
        }

        $accessData = Cache::get("file_access_{$storagePath}", []);
        $accessCount = $accessData['access_count'] ?? 0;
        $lastAccessed = $accessData['last_accessed'] ? Carbon::parse($accessData['last_accessed']) : null;

        // Promote if frequently accessed recently
        if ($accessCount > 10 && $lastAccessed && $lastAccessed->diffInDays() < 7) {
            return true;
        }

        // Promote if access pattern shows increasing usage
        $accessPattern = $accessData['access_pattern'] ?? [];
        if (count($accessPattern) > 10) {
            $recentAccesses = array_filter($accessPattern, fn($timestamp) => $timestamp > (time() - 86400)); // Last 24 hours
            return count($recentAccesses) > 5;
        }

        return false;
    }

    private function generateAccessUrl(string $storagePath, string $tier, string $accessMethod = 'direct'): string
    {
        $disk = $this->storageConfig[$tier]['disk'];

        return match($accessMethod) {
            'direct' => Storage::disk($disk)->url($storagePath),
            'cdn' => $this->getCDNUrl($storagePath, $tier),
            'proxy' => route('file.proxy', ['path' => base64_encode($storagePath)]),
            default => Storage::disk($disk)->url($storagePath),
        };
    }

    private function getCDNUrl(string $storagePath, string $tier): string
    {
        $cdnConfig = [
            'hot_tier' => env('CDN_HOT_URL', 'https://hot.chinook-cdn.com'),
            'warm_tier' => env('CDN_WARM_URL', 'https://warm.chinook-cdn.com'),
            'cold_tier' => env('CDN_COLD_URL', 'https://cold.chinook-cdn.com'),
        ];

        return $cdnConfig[$tier] . '/' . $storagePath;
    }

    private function findTieringCandidates(): array
    {
        $candidates = [];

        // This would typically query a database of file access patterns
        // For now, we'll simulate with cache data
        $cacheKeys = Cache::getRedis()->keys('file_access_*');

        foreach ($cacheKeys as $key) {
            $storagePath = str_replace('file_access_', '', $key);
            $accessData = Cache::get($key, []);
            $location = $this->locateFile($storagePath);

            if (!$location) continue;

            $recommendation = $this->recommendTier($accessData, $location);

            if ($recommendation['tier'] !== $location['tier']) {
                $candidates[] = [
                    'storage_path' => $storagePath,
                    'current_tier' => $location['tier'],
                    'recommended_tier' => $recommendation['tier'],
                    'estimated_savings' => $recommendation['savings'],
                    'reason' => $recommendation['reason'],
                ];
            }
        }

        return $candidates;
    }

    private function recommendTier(array $accessData, array $location): array
    {
        $accessCount = $accessData['access_count'] ?? 0;
        $lastAccessed = $accessData['last_accessed'] ? Carbon::parse($accessData['last_accessed']) : null;
        $daysSinceAccess = $lastAccessed ? $lastAccessed->diffInDays() : 999;

        // Recommendation logic
        if ($accessCount > 50 && $daysSinceAccess < 7) {
            return ['tier' => 'hot_tier', 'savings' => 0, 'reason' => 'high_access'];
        } elseif ($accessCount > 10 && $daysSinceAccess < 30) {
            return ['tier' => 'warm_tier', 'savings' => 0.008, 'reason' => 'moderate_access'];
        } elseif ($daysSinceAccess > 90) {
            return ['tier' => 'cold_tier', 'savings' => 0.019, 'reason' => 'low_access'];
        }

        return ['tier' => $location['tier'], 'savings' => 0, 'reason' => 'no_change'];
    }
}
```

#### Storage Pattern Examples and Best Practices

**Intelligent File Organization:**

```php
<?php
// app/Http/Controllers/FileProxyController.php

namespace App\Http\Controllers;

use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class FileProxyController extends Controller
{
    public function __construct(
        private FileStorageService $storageService
    ) {}

    public function serve(Request $request, string $encodedPath): Response
    {
        $storagePath = base64_decode($encodedPath);

        // Validate access permissions
        $this->authorize('access-file', $storagePath);

        // Get file information
        try {
            $fileInfo = $this->storageService->retrieveFile($storagePath, [
                'access_method' => 'proxy',
            ]);
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }

        // Check cache for file content
        $cacheKey = "file_content_" . hash('sha256', $storagePath);
        $fileContent = Cache::get($cacheKey);

        if (!$fileContent) {
            $fileContent = Storage::disk($fileInfo['disk'])->get($storagePath);

            // Cache small files (< 1MB) for faster serving
            if (strlen($fileContent) < 1024 * 1024) {
                Cache::put($cacheKey, $fileContent, 3600); // 1 hour
            }
        }

        // Determine content type
        $contentType = $this->getContentType($storagePath, $fileInfo['metadata']);

        // Set appropriate headers
        $headers = [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($fileContent),
            'Cache-Control' => 'public, max-age=3600',
            'X-Storage-Tier' => $fileInfo['tier'],
        ];

        // Add content disposition for downloads
        if ($request->query('download')) {
            $filename = $fileInfo['metadata']['original_name'] ?? basename($storagePath);
            $headers['Content-Disposition'] = "attachment; filename=\"{$filename}\"";
        }

        return response($fileContent, 200, $headers);
    }

    public function generateDownloadLink(Request $request): array
    {
        $request->validate([
            'storage_path' => 'required|string',
            'expiration_minutes' => 'sometimes|integer|min:1|max:1440', // Max 24 hours
        ]);

        $storagePath = $request->input('storage_path');
        $expirationMinutes = $request->input('expiration_minutes', 60);

        try {
            $signedUrl = $this->storageService->generateSignedUrl(
                $storagePath,
                $expirationMinutes,
                ['download' => true]
            );

            return [
                'download_url' => $signedUrl,
                'expires_at' => now()->addMinutes($expirationMinutes)->toISOString(),
                'storage_path' => $storagePath,
            ];
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate download link',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function getContentType(string $storagePath, array $metadata): string
    {
        $extension = pathinfo($storagePath, PATHINFO_EXTENSION);

        return match(strtolower($extension)) {
            'mp3' => 'audio/mpeg',
            'flac' => 'audio/flac',
            'wav' => 'audio/wav',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => $metadata['mime_type'] ?? 'application/octet-stream',
        };
    }
}
```

**Storage Cost Optimization Strategies:**

```php
<?php
// app/Console/Commands/OptimizeStorageCosts.php

namespace App\Console\Commands;

use App\Services\FileStorageService;
use Illuminate\Console\Command;

class OptimizeStorageCosts extends Command
{
    protected $signature = 'storage:optimize-costs {--dry-run : Show what would be optimized without making changes}';
    protected $description = 'Optimize storage costs by moving files to appropriate tiers';

    public function handle(FileStorageService $storageService): int
    {
        $this->info('Starting storage cost optimization...');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get optimization recommendations
        $optimizations = $isDryRun
            ? $this->getOptimizationPreview($storageService)
            : $storageService->optimizeStorageCosts();

        if (empty($optimizations)) {
            $this->info('No storage optimizations needed.');
            return 0;
        }

        // Display results
        $this->table(
            ['File', 'From Tier', 'To Tier', 'Est. Monthly Savings'],
            array_map(function ($opt) {
                return [
                    substr($opt['file'], 0, 50) . '...',
                    $opt['from_tier'],
                    $opt['to_tier'],
                    '$' . number_format($opt['estimated_savings'], 4),
                ];
            }, $optimizations)
        );

        $totalSavings = array_sum(array_column($optimizations, 'estimated_savings'));
        $this->info("Total estimated monthly savings: $" . number_format($totalSavings, 2));

        if (!$isDryRun) {
            $this->info('Storage optimization completed successfully.');
        }

        return 0;
    }

    private function getOptimizationPreview(FileStorageService $storageService): array
    {
        // This would analyze files without moving them
        return $storageService->findTieringCandidates();
    }
}
```

**File Storage Best Practices:**

1. **Tier Selection Strategy:**
   - **Hot Tier**: User avatars, thumbnails, recent uploads (< 30 days)
   - **Warm Tier**: Album covers, track files, regular content (30-365 days)
   - **Cold Tier**: Archive content, backups, rarely accessed files (> 365 days)

2. **Access Pattern Optimization:**
   - Monitor file access patterns to optimize tier placement
   - Automatically promote frequently accessed files
   - Demote files that haven't been accessed recently

3. **Cost Management:**
   - Regular cost optimization runs (weekly/monthly)
   - Monitor storage costs per tier
   - Set up alerts for unusual storage growth

4. **Performance Considerations:**
   - Cache frequently accessed small files in memory
   - Use CDN for public content delivery
   - Implement intelligent prefetching for related files

5. **Security and Compliance:**
   - Use signed URLs for private content
   - Implement proper access controls
   - Regular security audits of storage permissions

**Storage Performance Metrics:**

| Metric | Hot Tier | Warm Tier | Cold Tier | Target |
|--------|----------|-----------|-----------|---------|
| Access Time | < 100ms | < 500ms | < 5s | 95th percentile |
| Availability | 99.99% | 99.9% | 99% | Monthly uptime |
| Cost per GB | $0.015 | $0.023 | $0.004 | Monthly storage |
| Retrieval Cost | Free | $0.0004/GB | $0.01/GB | Per request |

**File Organization Structure:**

```
storage/
├── avatars/
│   ├── 2024/01/15/
│   │   ├── a1b2c3d4_user-123-avatar.jpg
│   │   └── e5f6g7h8_user-456-avatar.png
│   └── 2024/01/16/
├── album_covers/
│   ├── 2024/01/15/
│   │   ├── i9j0k1l2_album-789-cover.jpg
│   │   └── m3n4o5p6_album-012-cover.webp
├── audio_files/
│   ├── 2024/01/15/
│   │   ├── q7r8s9t0_track-345-high.mp3
│   │   ├── u1v2w3x4_track-345-medium.mp3
│   │   └── y5z6a7b8_track-345-low.mp3
└── waveforms/
    ├── 2024/01/15/
    │   └── c9d0e1f2_track-345-waveform.png
```

## 6.7. Code Examples

### 6.7.1. Complete Model Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\Categorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Wildside\Userstamps\Userstamps;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Artist extends Model implements HasMedia
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTags;
    use SoftDeletes;
    use Userstamps;
    use HasRoles;
    use HasPermissions;
    use Categorizable;
    use InteractsWithMedia;

    protected $table = 'artists';

    protected $fillable = [
        'name',
        'biography',
        'formed_year',
        'social_links',
        'is_active',
        'public_id',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'formed_year' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return SecondaryKeyType::ULID;
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('public_id')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    // Media Collections
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('band_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('promotional_materials')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);

        $this->addMediaCollection('press_kit')
            ->acceptsMimeTypes(['application/pdf', 'application/msword', 'text/plain']);
    }

    // Media Conversions
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->optimize()
            ->performOnCollections('profile_photos', 'band_photos', 'promotional_materials');

        $this->addMediaConversion('avatar')
            ->width(150)
            ->height(150)
            ->optimize()
            ->performOnCollections('profile_photos');

        $this->addMediaConversion('hero')
            ->width(1920)
            ->height(1080)
            ->optimize()
            ->performOnCollections('band_photos', 'promotional_materials');

        $this->addMediaConversion('webp_thumb')
            ->width(300)
            ->height(300)
            ->format('webp')
            ->optimize()
            ->performOnCollections('profile_photos', 'band_photos');
    }

    // Relationships
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    // Media Helper Methods
    public function getProfilePhotoUrl(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('profile_photos');

        if (!$media) {
            return null;
        }

        return $conversion ? $media->getUrl($conversion) : $media->getUrl();
    }

    public function hasProfilePhoto(): bool
    {
        return $this->hasMedia('profile_photos');
    }

    public function getBandPhotosUrls(string $conversion = ''): array
    {
        return $this->getMedia('band_photos')
            ->map(fn(Media $media) => $conversion ? $media->getUrl($conversion) : $media->getUrl())
            ->toArray();
    }

    // Query Scopes
    public function scopeWithMedia(Builder $query, array $collections = []): Builder
    {
        $collections = empty($collections) ? ['profile_photos'] : $collections;

        return $query->with(['media' => function ($mediaQuery) use ($collections) {
            $mediaQuery->whereIn('collection_name', $collections);
        }]);
    }

    public function scopeHasProfilePhoto(Builder $query): Builder
    {
        return $query->whereHas('media', function ($mediaQuery) {
            $mediaQuery->where('collection_name', 'profile_photos');
        });
    }
}
```

### 6.7.2. Migration Files

```php
<?php

// Custom migration: Enhance media table for Chinook
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Add soft deletes for Chinook implementation
            $table->softDeletes();

            // Add user stamps for audit trail
            $table->userstamps();

            // Performance indexes
            $table->index(['model_type', 'model_id', 'collection_name'], 'media_model_collection_index');
            $table->index(['collection_name', 'created_at'], 'media_collection_date_index');
            $table->index(['mime_type', 'size'], 'media_type_size_index');
            $table->index(['created_at', 'deleted_at'], 'media_active_date_index');

            // Add constraints for file size limits
            $table->check('size <= 104857600'); // 100MB limit

            // Add metadata columns for common queries
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->json('processing_errors')->nullable();
            $table->integer('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropUserstamps();
            $table->dropIndex('media_model_collection_index');
            $table->dropIndex('media_collection_date_index');
            $table->dropIndex('media_type_size_index');
            $table->dropIndex('media_active_date_index');
            $table->dropColumn([
                'is_processed',
                'processed_at',
                'processing_errors',
                'access_count',
                'last_accessed_at'
            ]);
        });
    }
};
```

## API Integration

### Media API Endpoints

### Authentication and Authorization

#### 6.7.3. API Controller with RBAC Integration

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ChinookMedia;
use App\Http\Requests\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Services\MediaSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ArtistMediaController extends Controller
{
    public function __construct(
        private MediaSecurityService $securityService
    ) {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Artist::class, 'artist');
    }

    /**
     * Upload media for an artist.
     */
    public function store(MediaUploadRequest $request, Artist $artist): JsonResponse
    {
        $this->authorize('manage-artists');

        $collection = $request->validated('collection');
        $file = $request->file('media');

        // Security validation
        $securityErrors = $this->securityService->validateFile($file, $collection);
        if (!empty($securityErrors)) {
            return response()->json([
                'message' => 'File validation failed',
                'errors' => $securityErrors,
            ], 422);
        }

        try {
            $media = $artist->addMediaFromRequest('media')
                ->withCustomProperties([
                    'uploaded_by' => auth()->id(),
                    'upload_source' => 'api',
                    'original_name' => $file->getClientOriginalName(),
                    'file_hash' => hash_file('sha256', $file->getPathname()),
                    'upload_ip' => $request->ip(),
                ])
                ->toMediaCollection($collection);

            // Queue conversions for large files
            if ($this->shouldQueueConversions($collection, $file->getSize())) {
                dispatch(new \App\Jobs\MediaConversionJob($media, 'all'));
            }

            return response()->json([
                'message' => 'Media uploaded successfully',
                'data' => new MediaResource($media),
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Media upload failed', [
                'artist_id' => $artist->id,
                'collection' => $collection,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Failed to upload media',
                'error' => app()->environment('production') ? 'Internal server error' : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all media for an artist with filtering.
     */
    public function index(Request $request, Artist $artist): JsonResponse
    {
        $request->validate([
            'collection' => 'sometimes|string|in:profile_photos,band_photos,promotional_materials,press_kit',
            'conversion' => 'sometimes|string|in:thumb,avatar,hero,webp_thumb',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $mediaQuery = $artist->media()->latest();

        if ($collection = $request->query('collection')) {
            $mediaQuery->where('collection_name', $collection);
        }

        $media = $mediaQuery->paginate($request->query('per_page', 20));

        return response()->json([
            'data' => MediaResource::collection($media),
            'meta' => [
                'total' => $media->total(),
                'per_page' => $media->perPage(),
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
            ],
        ]);
    }

    /**
     * Delete specific media.
     */
    public function destroy(Artist $artist, ChinookMedia $media): JsonResponse
    {
        $this->authorize('manage-artists');

        if ($media->model_id !== $artist->id || $media->model_type !== Artist::class) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        // Log deletion for audit trail
        \Log::info('Media deleted', [
            'media_id' => $media->id,
            'artist_id' => $artist->id,
            'collection' => $media->collection_name,
            'deleted_by' => auth()->id(),
        ]);

        $media->delete();

        return response()->json(['message' => 'Media deleted successfully']);
    }

    /**
     * Get media conversion status.
     */
    public function conversionStatus(Artist $artist, ChinookMedia $media): JsonResponse
    {
        if ($media->model_id !== $artist->id || $media->model_type !== Artist::class) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        $conversions = $media->getGeneratedConversions();
        $isProcessing = !$media->getCustomProperty('is_processed', false);
        $progress = \Cache::get("media_conversion_{$media->id}", []);

        return response()->json([
            'is_processing' => $isProcessing,
            'conversions' => $conversions,
            'progress' => $progress,
            'errors' => $media->getCustomProperty('processing_errors', []),
        ]);
    }

    private function shouldQueueConversions(string $collection, int $fileSize): bool
    {
        return in_array($collection, ['promotional_materials', 'press_kit']) ||
               $fileSize > 5 * 1024 * 1024; // 5MB
    }
}

// Media Upload Request Validation
class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'media' => [
                'required',
                'file',
                'max:102400', // 100MB
                function ($attribute, $value, $fail) {
                    $collection = $this->input('collection');
                    $allowedMimes = $this->getAllowedMimesForCollection($collection);

                    if (!in_array($value->getMimeType(), $allowedMimes)) {
                        $fail("The {$attribute} must be a valid file type for {$collection}.");
                    }
                },
            ],
            'collection' => [
                'required',
                'string',
                'in:profile_photos,band_photos,promotional_materials,press_kit',
            ],
        ];
    }

    private function getAllowedMimesForCollection(string $collection): array
    {
        return match($collection) {
            'profile_photos', 'band_photos', 'promotional_materials' => [
                'image/jpeg', 'image/png', 'image/webp'
            ],
            'press_kit' => [
                'application/pdf', 'application/msword', 'text/plain'
            ],
            default => [],
        };
    }
}
```

## 6.8. Best Practices

## Testing and Validation

### 6.8.1. File Security and Validation

```php
// Comprehensive file validation service
class MediaSecurityService
{
    private array $allowedMimeTypes = [
        'images' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'audio' => ['audio/mpeg', 'audio/flac', 'audio/wav', 'audio/ogg'],
        'documents' => ['application/pdf', 'text/plain'],
    ];

    private array $dangerousExtensions = [
        'php', 'js', 'html', 'htm', 'exe', 'bat', 'cmd', 'scr', 'pif'
    ];

    public function validateFile(UploadedFile $file, string $collection): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > $this->getMaxSizeForCollection($collection)) {
            $errors[] = 'File size exceeds maximum allowed for this collection';
        }

        // Check MIME type
        if (!$this->isAllowedMimeType($file->getMimeType(), $collection)) {
            $errors[] = 'File type not allowed for this collection';
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, $this->dangerousExtensions)) {
            $errors[] = 'File extension not allowed for security reasons';
        }

        // Validate image files specifically
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $imageErrors = $this->validateImage($file);
            $errors = array_merge($errors, $imageErrors);
        }

        // Validate audio files
        if (str_starts_with($file->getMimeType(), 'audio/')) {
            $audioErrors = $this->validateAudio($file);
            $errors = array_merge($errors, $audioErrors);
        }

        return $errors;
    }

    private function validateImage(UploadedFile $file): array
    {
        $errors = [];

        try {
            $imageInfo = getimagesize($file->getPathname());

            if (!$imageInfo) {
                $errors[] = 'Invalid image file';
                return $errors;
            }

            [$width, $height] = $imageInfo;

            // Check dimensions
            if ($width > 5000 || $height > 5000) {
                $errors[] = 'Image dimensions too large (max 5000x5000)';
            }

            if ($width < 100 || $height < 100) {
                $errors[] = 'Image dimensions too small (min 100x100)';
            }

        } catch (\Exception $e) {
            $errors[] = 'Failed to validate image: ' . $e->getMessage();
        }

        return $errors;
    }

    private function validateAudio(UploadedFile $file): array
    {
        $errors = [];

        // Basic audio validation - could be enhanced with FFmpeg
        if ($file->getSize() > 100 * 1024 * 1024) { // 100MB
            $errors[] = 'Audio file too large (max 100MB)';
        }

        return $errors;
    }

    private function getMaxSizeForCollection(string $collection): int
    {
        return match($collection) {
            'audio_files' => 100 * 1024 * 1024,      // 100MB
            'profile_photos', 'cover_art' => 10 * 1024 * 1024,  // 10MB
            'press_kit', 'digital_booklet' => 50 * 1024 * 1024, // 50MB
            default => 10 * 1024 * 1024,             // 10MB
        };
    }

    private function isAllowedMimeType(string $mimeType, string $collection): bool
    {
        $allowedTypes = match($collection) {
            'profile_photos', 'band_photos', 'cover_art' => $this->allowedMimeTypes['images'],
            'audio_files', 'preview_clips' => $this->allowedMimeTypes['audio'],
            'press_kit', 'liner_notes' => $this->allowedMimeTypes['documents'],
            default => [],
        };

        return in_array($mimeType, $allowedTypes);
    }
}
```

### 6.8.2. Performance Monitoring

```php
// Media performance monitoring service
class MediaPerformanceMonitor
{
    public function trackUpload(ChinookMedia $media, float $uploadTime): void
    {
        $metrics = [
            'upload_time' => $uploadTime,
            'file_size' => $media->size,
            'mime_type' => $media->mime_type,
            'collection' => $media->collection_name,
            'disk' => $media->disk,
            'user_id' => auth()->id(),
            'timestamp' => now(),
        ];

        // Log to monitoring service
        \Log::channel('metrics')->info('media_upload', $metrics);

        // Store in cache for real-time dashboard
        $cacheKey = "upload_metrics_" . now()->format('Y-m-d-H');
        $existing = \Cache::get($cacheKey, []);
        $existing[] = $metrics;
        \Cache::put($cacheKey, $existing, 3600);
    }

    public function getPerformanceReport(string $period = '24h'): array
    {
        $cacheKey = "performance_report_{$period}";

        return \Cache::remember($cacheKey, 300, function () use ($period) {
            $since = match($period) {
                '1h' => now()->subHour(),
                '24h' => now()->subDay(),
                '7d' => now()->subWeek(),
                '30d' => now()->subMonth(),
                default => now()->subDay(),
            };

            return [
                'uploads' => [
                    'total' => ChinookMedia::where('created_at', '>=', $since)->count(),
                    'total_size' => ChinookMedia::where('created_at', '>=', $since)->sum('size'),
                    'by_collection' => ChinookMedia::where('created_at', '>=', $since)
                        ->groupBy('collection_name')
                        ->selectRaw('collection_name, COUNT(*) as count, SUM(size) as total_size')
                        ->get(),
                ],
                'storage' => [
                    'total_files' => ChinookMedia::count(),
                    'total_size' => ChinookMedia::sum('size'),
                    'by_disk' => ChinookMedia::groupBy('disk')
                        ->selectRaw('disk, COUNT(*) as count, SUM(size) as total_size')
                        ->get(),
                ],
                'conversions' => [
                    'pending' => ChinookMedia::where('is_processed', false)->count(),
                    'failed' => ChinookMedia::whereNotNull('processing_errors')->count(),
                ],
            ];
        });
    }
}
```

## Troubleshooting

### 6.8.3. Testing Strategies

```php
<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\User;
use App\Models\ChinookMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtistMediaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Artist $artist;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('minio');

        $this->user = User::factory()->create();
        $this->artist = Artist::factory()->create();

        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_upload_profile_photo(): void
    {
        $file = UploadedFile::fake()->image('profile.jpg', 800, 600);

        $response = $this->postJson("/api/artists/{$this->artist->id}/media", [
            'media' => $file,
            'collection' => 'profile_photos',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        'id',
                        'collection_name',
                        'file_name',
                        'mime_type',
                        'size',
                        'urls' => [
                            'original',
                            'thumb',
                            'avatar',
                        ],
                    ],
                ]);

        $this->assertDatabaseHas('media', [
            'model_type' => Artist::class,
            'model_id' => $this->artist->id,
            'collection_name' => 'profile_photos',
            'mime_type' => 'image/jpeg',
        ]);

        Storage::disk('minio')->assertExists(
            $this->artist->getFirstMedia('profile_photos')->getPath()
        );
    }

    /** @test */
    public function it_integrates_media_with_categories(): void
    {
        $rockCategory = Category::factory()->create([
            'name' => 'Rock',
            'type' => CategoryType::GENRE,
        ]);

        $this->artist->attachCategory($rockCategory);

        $file = UploadedFile::fake()->image('rock-band.jpg');
        $media = $this->artist->addMediaFromRequest('media')
            ->withCustomProperties(['category_context' => 'rock_performance'])
            ->toMediaCollection('band_photos');

        // Test that media can also be categorized
        $media->attachCategory($rockCategory);

        $this->assertTrue($media->hasCategoryType(CategoryType::GENRE));
        $this->assertTrue($this->artist->hasMedia('band_photos'));
        $this->assertTrue($this->artist->hasCategoryType(CategoryType::GENRE));
    }
}
```

## 6.9. Summary and Recommendations

### 6.9.1. Implementation Checklist

- ✅ **Install Package**: `composer require spatie/laravel-medialibrary`
- ✅ **Configure MinIO**: Set up local development environment with MinIO
- ✅ **Selective Integration**: Add `HasMedia` trait to Artist, Album, Track, Playlist models only
- ✅ **Custom Media Model**: Extend with `ChinookMedia` for Categorizable integration
- ✅ **Multi-tier Storage**: Configure S3, Glacier, and Cloudflare R2 for production
- ✅ **Security Validation**: Implement comprehensive file validation and security checks
- ✅ **Performance Optimization**: Set up caching, CDN integration, and monitoring
- ✅ **Testing**: Create comprehensive test suite for media functionality

### 6.9.2. Expected Performance Metrics

| Metric | Target | Notes |
|--------|--------|-------|
| Image Upload | < 5 seconds | Including thumbnail generation |
| Audio Upload | < 2 minutes | For files up to 100MB |
| Conversion Processing | < 30 seconds | Background queue processing |
| CDN Cache Hit Rate | > 95% | For frequently accessed media |
| Storage Cost | < $50/month | For 1TB with optimized strategy |
| Query Performance | < 100ms | Media-enabled model queries |

### 6.9.3. Key Benefits

1. **Seamless Integration**: Works perfectly with existing Categorizable trait and closure table structure
2. **Cost Optimization**: Multi-tier storage reduces costs by 60-80% compared to single-tier storage
3. **Developer Experience**: Laravel 12 patterns with comprehensive type hints and documentation
4. **Production Ready**: Includes security, monitoring, and disaster recovery considerations
5. **Scalable Architecture**: Supports growth from startup to enterprise scale

## Next Steps

After implementing the media library integration:

1. **Test Media Workflows**: Verify upload, conversion, and retrieval processes
2. **Configure CDN**: Set up CloudFlare or AWS CloudFront for media delivery
3. **Implement Monitoring**: Add media-specific monitoring and alerting
4. **Performance Testing**: Test with realistic file sizes and volumes
5. **Security Audit**: Review file validation and access controls
6. **Documentation**: Update API documentation with media endpoints

## Navigation

**← Previous:** [Advanced Features Guide](050-chinook-advanced-features-guide.md)

**Next →** [Hierarchy Comparison Guide](070-chinook-hierarchy-comparison-guide.md)

This comprehensive media library integration provides a robust, scalable solution that enhances the Chinook music platform while maintaining compatibility with all existing Laravel 12 features and architectural patterns.
