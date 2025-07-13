# Required Traits Implementation Guide

This guide covers the implementation and usage of all required traits for Chinook models, including HasTags, HasSecondaryUniqueKey, HasSlug, Categorizable, and other essential traits for enterprise functionality.

## Table of Contents

- [Overview](#overview)
- [HasSecondaryUniqueKey Trait](#hassecondaryuniquekey-trait)
- [HasSlug Trait](#hasslug-trait)
- [Categorizable Trait](#categorizable-trait)
- [User Stamps Integration](#user-stamps-integration)
- [Soft Deletes Pattern](#soft-deletes-pattern)
- [Media Library Integration](#media-library-integration)

## Overview

The Chinook admin panel requires specific traits on all models to ensure consistency, functionality, and enterprise-grade features. These traits provide secondary unique keys, URL-friendly slugs, categorization, audit trails, and media management.

### Required Traits List

1. **HasSecondaryUniqueKey** - Public-facing identifiers (ULID/UUID/Snowflake)
2. **HasSlug** - URL-friendly identifiers generated from public_id
3. **HasTaxonomies** - Single taxonomy system via aliziodev/laravel-taxonomy
4. **HasTags** - Spatie tags for flexible metadata
5. **Userstamps** - Audit trails with created_by/updated_by
6. **SoftDeletes** - Safe deletion with recovery capability
7. **InteractsWithMedia** - File and media management

## HasSecondaryUniqueKey Trait

### Trait Implementation

```php
<?php

namespace App\Traits;

use Glhd\Bits\Snowflake;
use Illuminate\Support\Str;

trait HasSecondaryUniqueKey
{
    /**
     * Boot the trait.
     */
    protected static function bootHasSecondaryUniqueKey(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = $model->generateSecondaryKey();
            }
        });
    }

    /**
     * Generate a secondary key based on the model's preference.
     */
    public function generateSecondaryKey(): string
    {
        $type = $this->getSecondaryKeyType();

        return match ($type) {
            'uuid' => (string) Str::uuid(),
            'ulid' => (string) Str::ulid(),
            'snowflake' => (string) app(Snowflake::class)->id(),
            default => (string) Str::ulid(),
        };
    }

    /**
     * Get the secondary key type for this model.
     * Override this method in your model to specify the key type.
     */
    public function getSecondaryKeyType(): string
    {
        return 'ulid'; // Default to ULID
    }

    /**
     * Find a model by its secondary key.
     */
    public static function findByPublicId(string $publicId): ?static
    {
        return static::where('public_id', $publicId)->first();
    }

    /**
     * Find a model by its secondary key or fail.
     */
    public static function findByPublicIdOrFail(string $publicId): static
    {
        return static::where('public_id', $publicId)->firstOrFail();
    }

    /**
     * Get the route key for API endpoints.
     */
    public function getRouteKey(): string
    {
        return $this->public_id;
    }

    /**
     * Scope to find by public ID.
     */
    public function scopeByPublicId($query, string $publicId)
    {
        return $query->where('public_id', $publicId);
    }
}
```

### Model Usage

```php
class Artist extends Model
{
    use HasSecondaryUniqueKey;

    /**
     * Configure secondary key type for artists.
     */
    public function getSecondaryKeyType(): string
    {
        return 'ulid'; // Artists use ULID
    }
}

class Track extends Model
{
    use HasSecondaryUniqueKey;

    /**
     * Configure secondary key type for tracks.
     */
    public function getSecondaryKeyType(): string
    {
        return 'snowflake'; // Tracks use Snowflake for ordering
    }
}
```

## HasSlug Trait

### Trait Implementation

```php
<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the trait.
     */
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty($model->getSlugSourceAttribute()) && empty($model->slug)) {
                $model->slug = $model->generateSlug();
            }
        });
    }

    /**
     * Generate a slug for the model.
     */
    public function generateSlug(): string
    {
        $source = $this->getSlugSource();
        $slug = Str::slug($source);
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Get the source attribute for slug generation.
     * Override this method to use a different attribute.
     */
    protected function getSlugSource(): string
    {
        return $this->public_id ?? $this->name ?? $this->title ?? 'item';
    }

    /**
     * Get the slug source attribute name.
     */
    protected function getSlugSourceAttribute(): string
    {
        return 'public_id'; // Default to public_id as per preferences
    }

    /**
     * Find a model by its slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * Find a model by its slug or fail.
     */
    public static function findBySlugOrFail(string $slug): static
    {
        return static::where('slug', $slug)->firstOrFail();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Scope to find by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
```

### Custom Slug Sources

```php
class Album extends Model
{
    use HasSlug;

    /**
     * Use album title for slug generation.
     */
    protected function getSlugSource(): string
    {
        return $this->title ?? $this->public_id;
    }

    /**
     * Update slug when title changes.
     */
    protected function getSlugSourceAttribute(): string
    {
        return 'title';
    }
}
```

## Categorizable Trait

### Trait Implementation

```php
<?php

namespace App\Traits;

use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait Categorizable
{
    /**
     * Get all categories for this model.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(
            Category::class,
            'categorizable',
            'categorizables'
        )->withTimestamps()
         ->withPivot(['metadata', 'sort_order', 'is_primary']);
    }

    /**
     * Get categories of a specific type.
     */
    public function categoriesByType(CategoryType $type): MorphToMany
    {
        return $this->categories()->where('type', $type->value);
    }

    /**
     * Get genres (GENRE type categories).
     */
    public function genres(): MorphToMany
    {
        return $this->categoriesByType(CategoryType::GENRE);
    }

    /**
     * Get moods (MOOD type categories).
     */
    public function moods(): MorphToMany
    {
        return $this->categoriesByType(CategoryType::MOOD);
    }

    /**
     * Get themes (THEME type categories).
     */
    public function themes(): MorphToMany
    {
        return $this->categoriesByType(CategoryType::THEME);
    }

    /**
     * Get eras (ERA type categories).
     */
    public function eras(): MorphToMany
    {
        return $this->categoriesByType(CategoryType::ERA);
    }

    /**
     * Attach categories to this model.
     */
    public function attachCategories(array $categoryIds, array $metadata = []): void
    {
        $attachData = [];
        foreach ($categoryIds as $categoryId) {
            $attachData[$categoryId] = [
                'metadata' => json_encode($metadata[$categoryId] ?? []),
                'sort_order' => $metadata[$categoryId]['sort_order'] ?? 0,
                'is_primary' => $metadata[$categoryId]['is_primary'] ?? false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];
        }
        
        $this->categories()->attach($attachData);
    }

    /**
     * Detach categories from this model.
     */
    public function detachCategories(array $categoryIds = []): void
    {
        if (empty($categoryIds)) {
            $this->categories()->detach();
        } else {
            $this->categories()->detach($categoryIds);
        }
    }

    /**
     * Sync categories for this model.
     */
    public function syncCategories(array $categoryIds, array $metadata = []): void
    {
        $syncData = [];
        foreach ($categoryIds as $categoryId) {
            $syncData[$categoryId] = [
                'metadata' => json_encode($metadata[$categoryId] ?? []),
                'sort_order' => $metadata[$categoryId]['sort_order'] ?? 0,
                'is_primary' => $metadata[$categoryId]['is_primary'] ?? false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];
        }
        
        $this->categories()->sync($syncData);
    }

    /**
     * Check if the model has a specific category.
     */
    public function hasCategory(int $categoryId): bool
    {
        return $this->categories()->where('category_id', $categoryId)->exists();
    }

    /**
     * Check if the model has any categories of a specific type.
     */
    public function hasCategoryType(CategoryType $type): bool
    {
        return $this->categoriesByType($type)->exists();
    }

    /**
     * Get the primary category for a specific type.
     */
    public function getPrimaryCategory(CategoryType $type): ?Category
    {
        return $this->categoriesByType($type)
                    ->wherePivot('is_primary', true)
                    ->first();
    }

    /**
     * Scope to filter by category.
     */
    public function scopeWithCategory($query, int $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('category_id', $categoryId);
        });
    }

    /**
     * Scope to filter by category type.
     */
    public function scopeWithCategoryType($query, CategoryType $type)
    {
        return $query->whereHas('categories', function ($q) use ($type) {
            $q->where('type', $type->value);
        });
    }

    /**
     * Scope to filter by multiple categories (AND logic).
     */
    public function scopeWithAllCategories($query, array $categoryIds)
    {
        foreach ($categoryIds as $categoryId) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }
        return $query;
    }

    /**
     * Scope to filter by any of the given categories (OR logic).
     */
    public function scopeWithAnyCategory($query, array $categoryIds)
    {
        return $query->whereHas('categories', function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds);
        });
    }
}
```

### Usage Examples

```php
// Attach categories with metadata
$track->attachCategories([1, 2, 3], [
    1 => ['is_primary' => true, 'sort_order' => 1],
    2 => ['is_primary' => false, 'sort_order' => 2],
    3 => ['is_primary' => false, 'sort_order' => 3],
]);

// Get primary genre
$primaryGenre = $track->getPrimaryCategory(CategoryType::GENRE);

// Filter tracks by genre
$rockTracks = Track::withCategoryType(CategoryType::GENRE)
                  ->whereHas('categories', function ($q) {
                      $q->where('name', 'Rock');
                  })->get();
```

## User Stamps Integration

### Configuration

```php
// config/userstamps.php
return [
    'users_model' => App\Models\User::class,
    'users_table' => 'users',
    'created_by_column' => 'created_by',
    'updated_by_column' => 'updated_by',
    'deleted_by_column' => 'deleted_by',
];
```

### Model Usage

```php
class Artist extends Model
{
    use Userstamps;

    /**
     * Get the user who created this artist.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this artist.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter by creator.
     */
    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }
}
```

## Soft Deletes Pattern

### Enhanced Soft Deletes

```php
class Album extends Model
{
    use SoftDeletes;
    use Userstamps;

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope to include only active records.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to include only non-deleted records.
     */
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope to include only deleted records.
     */
    public function scopeOnlyDeleted($query)
    {
        return $query->whereNotNull('deleted_at');
    }

    /**
     * Restore the model and mark as active.
     */
    public function restore(): bool
    {
        $this->is_active = true;
        return parent::restore();
    }

    /**
     * Soft delete the model and mark as inactive.
     */
    public function delete(): bool
    {
        $this->is_active = false;
        $this->save();
        return parent::delete();
    }
}
```

## Media Library Integration

### Media Collections Setup

```php
class Artist extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_images')
              ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
              ->singleFile();

        $this->addMediaCollection('gallery')
              ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('documents')
              ->acceptsMimeTypes(['application/pdf', 'text/plain']);
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
              ->width(300)
              ->height(300)
              ->sharpen(10)
              ->performOnCollections('profile_images', 'gallery');

        $this->addMediaConversion('preview')
              ->width(800)
              ->height(600)
              ->quality(90)
              ->performOnCollections('gallery');
    }

    /**
     * Get the profile image URL.
     */
    public function getProfileImageUrl(): ?string
    {
        return $this->getFirstMediaUrl('profile_images', 'thumb');
    }
}
```

### Media Usage Examples

```php
// Add profile image
$artist->addMediaFromRequest('profile_image')
       ->toMediaCollection('profile_images');

// Add multiple gallery images
foreach ($request->file('gallery_images') as $file) {
    $artist->addMedia($file)
           ->toMediaCollection('gallery');
}

// Get media URLs
$profileImage = $artist->getProfileImageUrl();
$galleryImages = $artist->getMedia('gallery')->map(function ($media) {
    return $media->getUrl('preview');
});
```

## Next Steps

1. **Implement All Traits** - Create and test all required traits
2. **Configure Packages** - Setup Spatie packages and Userstamps
3. **Test Integration** - Verify all traits work together correctly
4. **Create Factories** - Update model factories to work with traits
5. **Document Usage** - Create comprehensive usage examples

## Related Documentation

- **[Model Architecture](010-model-architecture.md)** - Overall model structure and patterns
- **[Casting Patterns](030-casting-patterns.md)** - Modern casting techniques
- **[Hierarchical Models](050-hierarchical-models.md)** - Category and hierarchical data management
