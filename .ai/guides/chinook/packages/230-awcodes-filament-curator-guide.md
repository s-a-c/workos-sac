# 1. Awcodes Filament Curator Integration Guide

> **Package Source:** [awcodes/filament-curator](https://github.com/awcodes/filament-curator)  
> **Official Documentation:** [Filament Curator Documentation](https://filamentphp.com/plugins/awcodes-curator)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook database schema and entity prefixing  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Package Installation](#131-package-installation)
  - [1.3.2. Configuration Setup](#132-configuration-setup)
  - [1.3.3. Storage Configuration](#133-storage-configuration)
- [1.4. Chinook Model Integration](#14-chinook-model-integration)
  - [1.4.1. ChinookArtist Media Collections](#141-chinookartist-media-collections)
  - [1.4.2. ChinookAlbum Media Collections](#142-chinookalbum-media-collections)
  - [1.4.3. ChinookTrack Media Collections](#143-chinooktrack-media-collections)
- [1.5. Filament Admin Panel Integration](#15-filament-admin-panel-integration)
- [1.6. Performance Optimization](#16-performance-optimization)
- [1.7. Security & Access Control](#17-security--access-control)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Filament Curator documentation](https://filamentphp.com/plugins/awcodes-curator) for Laravel 12 and Chinook project requirements, including SQLite WAL journal mode optimizations and hierarchical role-based access control.

**Filament Curator** provides a comprehensive media management solution with advanced features for organizing, processing, and serving media files. It integrates seamlessly with the existing `spatie/laravel-medialibrary` package while adding enhanced UI components and workflow management.

### 1.2.1. Key Features

- **Advanced Media Browser**: Intuitive file browser with search, filtering, and bulk operations
- **Image Processing**: Automatic image optimization and conversion capabilities
- **Storage Integration**: Support for local, S3, and other cloud storage providers
- **Responsive Design**: Mobile-friendly interface with drag-and-drop functionality
- **Permission Integration**: Works with `spatie/laravel-permission` for access control
- **Performance Optimized**: Efficient loading with pagination and lazy loading

### 1.2.2. Chinook Integration Benefits

- **Album Artwork Management**: Streamlined cover art upload and organization
- **Artist Media Library**: Comprehensive photo and promotional material management
- **Track Audio Files**: Enhanced audio file management with metadata preservation
- **Bulk Operations**: Efficient management of large media collections
- **SQLite Optimization**: Configured for optimal performance with SQLite WAL mode

## 1.3. Installation & Configuration

### 1.3.1. Package Installation

> **Installation Source:** Based on [official installation guide](https://github.com/awcodes/filament-curator#installation)  
> **Chinook Modifications:** Enhanced for Laravel 12 and SQLite compatibility

The package is already installed via Composer. Verify installation:

<augment_code_snippet path="composer.json" mode="EXCERPT">
````json
{
    "require": {
        "awcodes/filament-curator": "^3.7"
    }
}
````
</augment_code_snippet>

**Publish Configuration and Migrations:**

```bash
# Publish configuration file
php artisan vendor:publish --tag="curator-config"

# Publish and run migrations
php artisan vendor:publish --tag="curator-migrations"
php artisan migrate

# Publish assets (optional)
php artisan vendor:publish --tag="curator-assets"
```

### 1.3.2. Configuration Setup

> **Configuration Source:** Adapted from [curator configuration](https://github.com/awcodes/filament-curator/blob/main/config/curator.php)  
> **Chinook Modifications:** Enhanced for SQLite performance and Chinook entity integration

<augment_code_snippet path="config/curator.php" mode="EXCERPT">
````php
<?php
// Configuration adapted from: https://github.com/awcodes/filament-curator/blob/main/config/curator.php
// Chinook modifications: Enhanced for SQLite WAL mode and entity prefixing
// Laravel 12 updates: Modern syntax and framework patterns

return [
    /*
     * Disk configuration for media storage
     * Optimized for Chinook project requirements
     */
    'disk' => env('CURATOR_DISK', 'public'),
    
    /*
     * Directory structure for organized media storage
     */
    'directory' => env('CURATOR_DIRECTORY', 'media'),
    
    /*
     * File size limits (in KB)
     * Optimized for music industry media requirements
     */
    'max_size' => env('CURATOR_MAX_SIZE', 10240), // 10MB default
    
    /*
     * Accepted file types for Chinook media
     */
    'accepted_file_types' => [
        // Images for album artwork and artist photos
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        
        // Audio files for tracks and previews
        'audio/mpeg',
        'audio/flac',
        'audio/wav',
        'audio/ogg',
        
        // Documents for liner notes and press materials
        'application/pdf',
        'text/plain',
    ],
    
    /*
     * Image processing configuration
     */
    'image_crop_aspect_ratio' => env('CURATOR_CROP_ASPECT_RATIO', null),
    'image_resize_mode' => env('CURATOR_RESIZE_MODE', null),
    'image_resize_target_width' => env('CURATOR_RESIZE_TARGET_WIDTH', null),
    'image_resize_target_height' => env('CURATOR_RESIZE_TARGET_HEIGHT', null),
    
    /*
     * Performance optimization for SQLite
     */
    'pagination_page_size' => env('CURATOR_PAGINATION_SIZE', 20),
    'enable_lazy_loading' => env('CURATOR_LAZY_LOADING', true),
    
    /*
     * Chinook-specific configuration
     */
    'chinook' => [
        'album_artwork_max_size' => 5120, // 5MB for album covers
        'artist_photo_max_size' => 3072,  // 3MB for artist photos
        'track_audio_max_size' => 51200,  // 50MB for audio files
        'enable_metadata_extraction' => true,
        'auto_generate_thumbnails' => true,
    ],
];
````
</augment_code_snippet>

### 1.3.3. Storage Configuration

> **Storage Source:** Based on [Laravel filesystem configuration](https://laravel.com/docs/filesystem)  
> **Chinook Optimization:** Enhanced for media-specific storage requirements

<augment_code_snippet path="config/filesystems.php" mode="EXCERPT">
````php
// Enhanced storage configuration for Chinook media management
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
        'throw' => false,
    ],
    
    // Dedicated disk for Chinook media
    'chinook_media' => [
        'driver' => 'local',
        'root' => storage_path('app/chinook/media'),
        'url' => env('APP_URL').'/storage/chinook/media',
        'visibility' => 'public',
        'throw' => false,
    ],
    
    // S3 configuration for production (optional)
    'chinook_s3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
    ],
],
````
</augment_code_snippet>

## 1.4. Chinook Model Integration

### 1.4.1. ChinookArtist Media Collections

> **Model Integration:** Enhanced from [spatie/laravel-medialibrary documentation](https://spatie.be/docs/laravel-medialibrary)  
> **Chinook Adaptation:** Specialized for artist media management workflows

<augment_code_snippet path="app/Models/ChinookArtist.php" mode="EXCERPT">
````php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookArtist extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, HasTaxonomies;

    protected $table = 'chinook_artists';

    protected $fillable = [
        'name',
        'biography',
        'website',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Register media collections for artist content
     * Optimized for music industry requirements
     */
    public function registerMediaCollections(): void
    {
        // Primary artist profile photo
        $this->addMediaCollection('profile_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('chinook_media');

        // Artist gallery/band photos
        $this->addMediaCollection('gallery_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('chinook_media');

        // Promotional materials
        $this->addMediaCollection('promotional_materials')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->useDisk('chinook_media');

        // Press kit documents
        $this->addMediaCollection('press_kit')
            ->acceptsMimeTypes(['application/pdf', 'text/plain'])
            ->useDisk('chinook_media');
    }

    /**
     * Define media conversions for different use cases
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('profile_photos', 'gallery_photos');

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->quality(85)
            ->performOnCollections('profile_photos', 'gallery_photos', 'promotional_materials');
    }
}
````
</augment_code_snippet>

### 1.4.2. ChinookAlbum Media Collections

> **Album Media Integration:** Specialized for album artwork and promotional content management

<augment_code_snippet path="app/Models/ChinookAlbum.php" mode="EXCERPT">
````php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChinookAlbum extends Model implements HasMedia
{
    use InteractsWithMedia, HasTaxonomies;

    protected $table = 'chinook_albums';

    /**
     * Register media collections for album content
     */
    public function registerMediaCollections(): void
    {
        // Album cover artwork
        $this->addMediaCollection('cover_art')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('chinook_media');

        // Digital booklet/liner notes
        $this->addMediaCollection('digital_booklet')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->useDisk('chinook_media');

        // Promotional images for the album
        $this->addMediaCollection('promotional_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('chinook_media');
    }

    /**
     * Define media conversions for album artwork
     */
    public function registerMediaConversions(Media $media = null): void
    {
        // Standard album cover sizes
        $this->addMediaConversion('small')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->performOnCollections('cover_art');

        $this->addMediaConversion('medium')
            ->width(300)
            ->height(300)
            ->quality(90)
            ->performOnCollections('cover_art');

        $this->addMediaConversion('large')
            ->width(600)
            ->height(600)
            ->quality(95)
            ->performOnCollections('cover_art');
    }
}
````
</augment_code_snippet>

### 1.4.3. ChinookTrack Media Collections

> **Track Media Integration:** Optimized for audio files and track-specific content

<augment_code_snippet path="app/Models/ChinookTrack.php" mode="EXCERPT">
````php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChinookTrack extends Model implements HasMedia
{
    use InteractsWithMedia, HasTaxonomies;

    protected $table = 'chinook_tracks';

    /**
     * Register media collections for track content
     */
    public function registerMediaCollections(): void
    {
        // Primary audio file
        $this->addMediaCollection('audio_files')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/flac', 'audio/wav', 'audio/ogg'])
            ->singleFile()
            ->useDisk('chinook_media');

        // Preview/sample clips
        $this->addMediaCollection('preview_clips')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav'])
            ->useDisk('chinook_media');

        // Waveform visualizations
        $this->addMediaCollection('waveforms')
            ->acceptsMimeTypes(['image/png', 'image/svg+xml'])
            ->singleFile()
            ->useDisk('chinook_media');

        // Lyric sheets and sheet music
        $this->addMediaCollection('sheet_music')
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->useDisk('chinook_media');
    }

    /**
     * Audio-specific media conversions
     */
    public function registerMediaConversions(Media $media = null): void
    {
        // Generate preview clips for full audio files
        $this->addMediaConversion('preview')
            ->performOnCollections('audio_files')
            ->nonQueued(); // Process immediately for previews
    }
}
````
</augment_code_snippet>

## 1.5. Filament Admin Panel Integration

> **Panel Integration:** Based on [Filament Curator panel setup](https://filamentphp.com/plugins/awcodes-curator#panel-integration)
> **Chinook Enhancement:** Integrated with existing admin panel configuration

<augment_code_snippet path="app/Providers/Filament/AdminPanelProvider.php" mode="EXCERPT">
````php
<?php

namespace App\Providers\Filament;

use Awcodes\Curator\CuratorPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ... existing configuration ...

            // Add Curator plugin with Chinook-specific configuration
            ->plugin(
                CuratorPlugin::make()
                    ->label('Media Library')
                    ->pluralLabel('Media Library')
                    ->navigationIcon('heroicon-o-photo')
                    ->navigationGroup('Content Management')
                    ->navigationSort(10)
                    ->defaultListView('grid') // Better for media browsing
                    ->resource(\Awcodes\Curator\Resources\MediaResource::class)
            );
    }
}
````
</augment_code_snippet>

## 1.6. Performance Optimization

> **Performance Source:** Based on [Laravel media optimization best practices](https://spatie.be/docs/laravel-medialibrary/v11/optimizing-images)
> **SQLite Enhancement:** Optimized for SQLite WAL journal mode performance

### 1.6.1. SQLite Configuration

<augment_code_snippet path="config/database.php" mode="EXCERPT">
````php
// SQLite configuration optimized for media operations
'sqlite' => [
    'driver' => 'sqlite',
    'url' => env('DATABASE_URL'),
    'database' => env('DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    'journal_mode' => 'WAL', // Optimized for concurrent reads
    'synchronous' => 'NORMAL', // Balance between safety and performance
    'cache_size' => 10000, // Increased cache for media operations
    'temp_store' => 'MEMORY', // Use memory for temporary storage
],
````
</augment_code_snippet>

### 1.6.2. Queue Configuration for Media Processing

<augment_code_snippet path="config/queue.php" mode="EXCERPT">
````php
// Queue configuration for media processing
'connections' => [
    'media_processing' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'media',
        'retry_after' => 300, // 5 minutes for large files
        'after_commit' => false,
    ],
],
````
</augment_code_snippet>

## 1.7. Security & Access Control

> **Security Integration:** Enhanced with [spatie/laravel-permission](140-spatie-permission-guide.md) RBAC system
> **Chinook RBAC:** Integrated with established role hierarchy

### 1.7.1. Permission-Based Media Access

<augment_code_snippet path="app/Policies/MediaPolicy.php" mode="EXCERPT">
````php
<?php

namespace App\Policies;

use App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view media
     */
    public function view(User $user, Media $media): bool
    {
        return $user->hasAnyPermission([
            'media.view',
            'media.view.own'
        ]);
    }

    /**
     * Determine if user can create media
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.create');
    }

    /**
     * Determine if user can update media
     */
    public function update(User $user, Media $media): bool
    {
        if ($user->hasPermissionTo('media.update')) {
            return true;
        }

        // Allow users to update their own uploads
        return $user->hasPermissionTo('media.update.own')
            && $media->getCustomProperty('uploaded_by') === $user->id;
    }

    /**
     * Determine if user can delete media
     */
    public function delete(User $user, Media $media): bool
    {
        if ($user->hasPermissionTo('media.delete')) {
            return true;
        }

        return $user->hasPermissionTo('media.delete.own')
            && $media->getCustomProperty('uploaded_by') === $user->id;
    }
}
````
</augment_code_snippet>

### 1.7.2. Role-Based Media Permissions

> **Permission Structure:** Aligned with [Chinook RBAC hierarchy](140-spatie-permission-guide.md#role-hierarchy)

**Media Permission Seeder:**

<augment_code_snippet path="database/seeders/MediaPermissionSeeder.php" mode="EXCERPT">
````php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MediaPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create media permissions
        $permissions = [
            'media.view',
            'media.view.own',
            'media.create',
            'media.update',
            'media.update.own',
            'media.delete',
            'media.delete.own',
            'media.manage.collections',
            'media.manage.conversions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $superAdmin = Role::findByName('Super Admin');
        $superAdmin->givePermissionTo($permissions);

        $admin = Role::findByName('Admin');
        $admin->givePermissionTo([
            'media.view', 'media.create', 'media.update',
            'media.delete', 'media.manage.collections'
        ]);

        $manager = Role::findByName('Manager');
        $manager->givePermissionTo([
            'media.view', 'media.create', 'media.update.own', 'media.delete.own'
        ]);

        $editor = Role::findByName('Editor');
        $editor->givePermissionTo([
            'media.view', 'media.create', 'media.update.own'
        ]);
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Next:** [Filament Shield Guide](240-bezhansalleh-filament-shield-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
