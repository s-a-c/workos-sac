# Polymorphic Models Guide

## Table of Contents

- [Overview](#overview)
- [Basic Polymorphic Relationships](#basic-polymorphic-relationships)
- [Many-to-Many Polymorphic](#many-to-many-polymorphic)
- [Polymorphic Traits](#polymorphic-traits)
- [Advanced Polymorphic Patterns](#advanced-polymorphic-patterns)
- [Categorizable Implementation](#categorizable-implementation)
- [Performance Optimization](#performance-optimization)
- [Type Safety](#type-safety)
- [Testing Polymorphic Models](#testing-polymorphic-models)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive polymorphic relationship patterns for Laravel 12 models in the Chinook application. Polymorphic relationships provide flexibility for models that can belong to multiple other model types, enabling clean and maintainable code architecture.

**🚀 Key Features:**
- **Flexible Associations**: Models that can belong to multiple parent types
- **Type Safety**: Proper type checking and validation
- **Performance Optimized**: Efficient querying strategies
- **Categorizable System**: Advanced category management with polymorphic relationships
- **WCAG 2.1 AA Compliance**: Accessible polymorphic data presentation

## Basic Polymorphic Relationships

### One-to-Many Polymorphic (morphMany/morphTo)

```php
<?php
// app/Models/Image.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    use HasFactory, HasUserStamps;

    protected function cast(): array
    {
        return [
            'filename' => 'string',
            'alt_text' => 'string',
            'file_size' => 'integer',
            'mime_type' => 'string',
            'width' => 'integer',
            'height' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Get the imageable model (Artist, Album, Track, etc.)
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the full URL for this image
     */
    public function url(): string
    {
        return Storage::url($this->filename);
    }

    /**
     * Get responsive image URLs
     */
    public function responsiveUrls(): array
    {
        return [
            'thumbnail' => $this->getResizedUrl(150, 150),
            'medium' => $this->getResizedUrl(400, 400),
            'large' => $this->getResizedUrl(800, 800),
            'original' => $this->url(),
        ];
    }
}

// app/Traits/HasImages.php
trait HasImages
{
    /**
     * Get all images for this model
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')
            ->orderBy('is_featured', 'desc')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get the featured image
     */
    public function featuredImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('is_featured', true);
    }

    /**
     * Add an image to this model
     */
    public function addImage(string $filename, array $attributes = []): Image
    {
        return $this->images()->create(array_merge([
            'filename' => $filename,
            'created_by' => auth()->id(),
        ], $attributes));
    }
}
```

### Applying the HasImages Trait

```php
<?php
// app/Models/Artist.php

class Artist extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasImages;

    // Artist-specific image methods
    public function profileImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('type', 'profile');
    }

    public function bannerImage(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('type', 'banner');
    }
}

// app/Models/Album.php
class Album extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasImages;

    // Album-specific image methods
    public function coverArt(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('type', 'cover');
    }

    public function backCover(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable')
            ->where('type', 'back_cover');
    }
}
```

## Many-to-Many Polymorphic

### Tagging System Implementation

```php
<?php
// app/Models/Tag.php

class Tag extends Model
{
    use HasFactory, HasSlug;

    protected function cast(): array
    {
        return [
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'color' => 'string',
            'is_featured' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Get all artists with this tag
     */
    public function artists(): MorphToMany
    {
        return $this->morphedByMany(Artist::class, 'taggable')
            ->withTimestamps()
            ->withPivot(['tagged_by', 'confidence_score']);
    }

    /**
     * Get all albums with this tag
     */
    public function albums(): MorphToMany
    {
        return $this->morphedByMany(Album::class, 'taggable')
            ->withTimestamps()
            ->withPivot(['tagged_by', 'confidence_score']);
    }

    /**
     * Get all tracks with this tag
     */
    public function tracks(): MorphToMany
    {
        return $this->morphedByMany(Track::class, 'taggable')
            ->withTimestamps()
            ->withPivot(['tagged_by', 'confidence_score']);
    }

    /**
     * Get all taggable models
     */
    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}

// app/Traits/HasTags.php
trait HasTags
{
    /**
     * Get all tags for this model
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')
            ->withTimestamps()
            ->withPivot(['tagged_by', 'confidence_score']);
    }

    /**
     * Sync tags with confidence scores
     */
    public function syncTagsWithScores(array $tagsWithScores): void
    {
        $syncData = [];
        
        foreach ($tagsWithScores as $tagData) {
            $tag = is_array($tagData) ? $tagData['tag'] : $tagData;
            $score = is_array($tagData) ? $tagData['score'] : 1.0;
            
            $tagModel = is_string($tag) 
                ? Tag::firstOrCreate(['name' => $tag])
                : $tag;
                
            $syncData[$tagModel->id] = [
                'tagged_by' => auth()->id(),
                'confidence_score' => $score,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->tags()->sync($syncData);
    }

    /**
     * Add a single tag
     */
    public function addTag(string|Tag $tag, float $confidenceScore = 1.0): void
    {
        $tagModel = is_string($tag) 
            ? Tag::firstOrCreate(['name' => $tag])
            : $tag;

        $this->tags()->attach($tagModel->id, [
            'tagged_by' => auth()->id(),
            'confidence_score' => $confidenceScore,
        ]);

        $tagModel->incrementUsage();
    }

    /**
     * Remove a tag
     */
    public function removeTag(string|Tag $tag): void
    {
        $tagModel = is_string($tag) 
            ? Tag::where('name', $tag)->first()
            : $tag;

        if ($tagModel) {
            $this->tags()->detach($tagModel->id);
        }
    }

    /**
     * Get tags by confidence score
     */
    public function highConfidenceTags(float $threshold = 0.8): Collection
    {
        return $this->tags()
            ->wherePivot('confidence_score', '>=', $threshold)
            ->get();
    }
}
```

## Polymorphic Traits

### Comments System

```php
<?php
// app/Models/Comment.php

class Comment extends Model
{
    use HasFactory, HasUserStamps, SoftDeletes;

    protected function cast(): array
    {
        return [
            'content' => 'string',
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
            'rating' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the commentable model
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created this comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get the parent comment
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Scope for approved comments
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for featured comments
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}

// app/Traits/HasComments.php
trait HasComments
{
    /**
     * Get all comments for this model
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get approved comments only
     */
    public function approvedComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->approved()
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get featured comments
     */
    public function featuredComments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')
            ->featured()
            ->approved()
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Add a comment to this model
     */
    public function addComment(string $content, int $rating = null): Comment
    {
        return $this->comments()->create([
            'content' => $content,
            'rating' => $rating,
            'user_id' => auth()->id(),
            'is_approved' => auth()->user()->hasRole('admin'),
        ]);
    }

    /**
     * Get average rating from comments
     */
    public function averageRating(): float
    {
        return $this->comments()
            ->approved()
            ->whereNotNull('rating')
            ->avg('rating') ?? 0.0;
    }
}
```

## Advanced Polymorphic Patterns

### Activity Logging System

```php
<?php
// app/Models/Activity.php

class Activity extends Model
{
    use HasFactory;

    protected function cast(): array
    {
        return [
            'action' => 'string',
            'description' => 'string',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the subject of the activity
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the causer of the activity
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for specific actions
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific subject type
     */
    public function scopeForSubjectType($query, string $subjectType)
    {
        return $query->where('subject_type', $subjectType);
    }
}

// app/Traits/LogsActivity.php
trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });

        static::updated(function ($model) {
            $model->logActivity('updated');
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }

    /**
     * Get all activities for this model
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Log an activity for this model
     */
    public function logActivity(string $action, array $properties = []): Activity
    {
        return Activity::create([
            'action' => $action,
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'causer_type' => auth()->check() ? get_class(auth()->user()) : null,
            'causer_id' => auth()->id(),
            'properties' => $properties,
            'description' => $this->getActivityDescription($action),
        ]);
    }

    /**
     * Get activity description
     */
    protected function getActivityDescription(string $action): string
    {
        $modelName = class_basename($this);
        $identifier = $this->name ?? $this->title ?? $this->id;

        return match($action) {
            'created' => "{$modelName} '{$identifier}' was created",
            'updated' => "{$modelName} '{$identifier}' was updated",
            'deleted' => "{$modelName} '{$identifier}' was deleted",
            default => "{$modelName} '{$identifier}' {$action}",
        };
    }
}
```

## Categorizable Implementation

### Advanced Category System

```php
<?php
// app/Models/Categorizable.php

class Categorizable extends Model
{
    protected $table = 'categorizables';

    protected function cast(): array
    {
        return [
            'category_type' => CategoryType::class,
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the categorizable model
     */
    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

// app/Enums/CategoryType.php
enum CategoryType: string
{
    case GENRE = 'genre';
    case MOOD = 'mood';
    case THEME = 'theme';
    case ERA = 'era';
    case INSTRUMENT = 'instrument';
    case LANGUAGE = 'language';
    case OCCASION = 'occasion';

    public function label(): string
    {
        return match($this) {
            self::GENRE => 'Genre',
            self::MOOD => 'Mood',
            self::THEME => 'Theme',
            self::ERA => 'Era',
            self::INSTRUMENT => 'Instrument',
            self::LANGUAGE => 'Language',
            self::OCCASION => 'Occasion',
        };
    }
}

// app/Traits/Categorizable.php
trait Categorizable
{
    /**
     * Get all categories for this model
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->withPivot(['category_type', 'is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderBy('categorizables.sort_order');
    }

    /**
     * Get categories by type
     */
    public function categoriesByType(CategoryType $type): Collection
    {
        return $this->categories()
            ->wherePivot('category_type', $type->value)
            ->get();
    }

    /**
     * Get primary category for a type
     */
    public function primaryCategory(CategoryType $type): ?Category
    {
        return $this->categories()
            ->wherePivot('category_type', $type->value)
            ->wherePivot('is_primary', true)
            ->first();
    }

    /**
     * Assign category with type
     */
    public function assignCategory(
        Category $category, 
        CategoryType $type, 
        bool $isPrimary = false,
        int $sortOrder = 0
    ): void {
        // If setting as primary, unset other primary categories of same type
        if ($isPrimary) {
            $this->categories()
                ->wherePivot('category_type', $type->value)
                ->updateExistingPivot($category->id, ['is_primary' => false]);
        }

        $this->categories()->attach($category->id, [
            'category_type' => $type->value,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Sync categories by type
     */
    public function syncCategoriesByType(CategoryType $type, array $categoryIds): void
    {
        $existingIds = $this->categories()
            ->wherePivot('category_type', $type->value)
            ->pluck('categories.id')
            ->toArray();

        // Detach categories not in new list
        $toDetach = array_diff($existingIds, $categoryIds);
        foreach ($toDetach as $categoryId) {
            $this->categories()->detach($categoryId);
        }

        // Attach new categories
        $toAttach = array_diff($categoryIds, $existingIds);
        foreach ($toAttach as $index => $categoryId) {
            $this->assignCategory(
                Category::find($categoryId),
                $type,
                $index === 0, // First one is primary
                $index
            );
        }
    }
}
```

## Performance Optimization

### Efficient Polymorphic Queries

```php
<?php
// Optimized polymorphic queries

class ImageService
{
    /**
     * Get images for multiple models efficiently
     */
    public function getImagesForModels(Collection $models): Collection
    {
        $modelsByType = $models->groupBy(fn($model) => get_class($model));
        $images = collect();

        foreach ($modelsByType as $type => $typeModels) {
            $ids = $typeModels->pluck('id');
            
            $typeImages = Image::where('imageable_type', $type)
                ->whereIn('imageable_id', $ids)
                ->get()
                ->groupBy('imageable_id');

            $images = $images->merge($typeImages);
        }

        return $images;
    }

    /**
     * Eager load polymorphic relationships
     */
    public function loadImagesForCollection(Collection $models): Collection
    {
        $images = $this->getImagesForModels($models);

        return $models->map(function ($model) use ($images) {
            $modelImages = $images->get($model->id, collect());
            $model->setRelation('images', $modelImages);
            return $model;
        });
    }
}
```

## Type Safety

### Polymorphic Type Constraints

```php
<?php
// Type-safe polymorphic relationships

abstract class PolymorphicModel extends Model
{
    /**
     * Get allowed polymorphic types
     */
    abstract protected function getAllowedMorphTypes(): array;

    /**
     * Validate polymorphic type
     */
    protected function validateMorphType(string $type): void
    {
        if (!in_array($type, $this->getAllowedMorphTypes())) {
            throw new InvalidArgumentException(
                "Invalid morph type: {$type}. Allowed types: " . 
                implode(', ', $this->getAllowedMorphTypes())
            );
        }
    }

    /**
     * Override morph to method with validation
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = null)
    {
        $relation = parent::morphTo($name, $type, $id, $ownerKey);
        
        // Add constraint to only allow specific types
        return $relation->whereIn(
            $relation->getMorphType(),
            $this->getAllowedMorphTypes()
        );
    }
}

// Example implementation
class Comment extends PolymorphicModel
{
    protected function getAllowedMorphTypes(): array
    {
        return [
            Artist::class,
            Album::class,
            Track::class,
        ];
    }
}
```

## Testing Polymorphic Models

### Comprehensive Polymorphic Testing

```php
<?php
// tests/Unit/Models/PolymorphicTest.php

use App\Models\Artist;
use App\Models\Album;
use App\Models\Image;
use App\Models\Tag;
use Tests\TestCase;

class PolymorphicTest extends TestCase
{
    public function test_artist_can_have_images(): void
    {
        $artist = Artist::factory()->create();
        $image = Image::factory()->create([
            'imageable_type' => Artist::class,
            'imageable_id' => $artist->id,
        ]);

        expect($artist->images)->toHaveCount(1);
        expect($artist->images->first())->toBeInstanceOf(Image::class);
        expect($image->imageable)->toBeInstanceOf(Artist::class);
    }

    public function test_multiple_models_can_share_tags(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create();
        $tag = Tag::factory()->create(['name' => 'rock']);

        $artist->tags()->attach($tag);
        $album->tags()->attach($tag);

        expect($tag->artists)->toHaveCount(1);
        expect($tag->albums)->toHaveCount(1);
        expect($artist->tags->first()->name)->toBe('rock');
        expect($album->tags->first()->name)->toBe('rock');
    }

    public function test_categorizable_trait_works_correctly(): void
    {
        $artist = Artist::factory()->create();
        $genre = Category::factory()->create(['type' => 'genre']);
        
        $artist->assignCategory($genre, CategoryType::GENRE, true);

        expect($artist->primaryCategory(CategoryType::GENRE))->toBe($genre);
        expect($artist->categoriesByType(CategoryType::GENRE))->toHaveCount(1);
    }
}
```

## Best Practices

### Polymorphic Guidelines

1. **Type Safety**: Always validate polymorphic types
2. **Performance**: Use eager loading for polymorphic relationships
3. **Naming**: Use descriptive names for polymorphic methods
4. **Constraints**: Implement database-level constraints where possible
5. **Testing**: Write comprehensive tests for all polymorphic scenarios
6. **Documentation**: Document allowed polymorphic types clearly

### Security Considerations

```php
<?php
// Secure polymorphic access

trait SecurePolymorphic
{
    /**
     * Check if user can access polymorphic model
     */
    protected function canAccessPolymorphicModel(Model $model): bool
    {
        return match(get_class($model)) {
            Artist::class => $this->canAccessArtist($model),
            Album::class => $this->canAccessAlbum($model),
            Track::class => $this->canAccessTrack($model),
            default => false,
        };
    }

    /**
     * Filter polymorphic results by access permissions
     */
    public function filterByAccess(Collection $models): Collection
    {
        return $models->filter(fn($model) => $this->canAccessPolymorphicModel($model));
    }
}
```

## Navigation

**← Previous:** [Relationship Patterns Guide](040-relationship-patterns.md)
**Next →** [User Stamps Guide](070-user-stamps.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Relationship Patterns Guide](040-relationship-patterns.md) - Basic relationship patterns
- [Hierarchical Models Guide](050-hierarchical-models.md) - Tree structure patterns

---

*This guide provides comprehensive polymorphic relationship patterns for Laravel 12 models in the Chinook application. Each pattern includes type safety, performance optimization, and security considerations to ensure robust and maintainable polymorphic implementations.*
