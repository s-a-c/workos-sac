# Laravel 12 Model Standards Documentation

This directory contains comprehensive documentation for implementing Laravel 12 models with modern patterns, required traits, and architectural preferences for the Chinook Filament admin panel.

## Documentation Structure

### Core Model Standards
1. **[Model Architecture](010-model-architecture.md)** - Laravel 12 modern patterns and conventions
2. **[Required Traits](020-required-traits.md)** - HasTags, HasSecondaryUniqueKey, HasSlug, HasTaxonomies
3. **[Casting Patterns](030-casting-patterns.md)** - Modern cast() method usage instead of $casts property
4. **[Relationship Patterns](040-relationship-patterns.md)** - Eloquent relationships and optimization

### Specialized Implementations
5. **[Hierarchical Models](050-hierarchical-models.md)** - Hybrid closure table + adjacency list architecture
6. **[Polymorphic Models](060-polymorphic-models.md)** - Categorizable trait and polymorphic relationships
7. **[User Stamps](070-user-stamps.md)** - Audit trail implementation with wildside/userstamps
8. **[Soft Deletes](080-soft-deletes.md)** - Safe deletion patterns and restoration

### Business Logic
9. **[Model Factories](090-model-factories.md)** - Laravel 12 factory patterns for testing
10. **[Model Observers](100-model-observers.md)** - Event handling and business logic
11. **[Model Policies](110-model-policies.md)** - Authorization and access control
12. **[Model Scopes](120-model-scopes.md)** - Query scopes and filtering

## Laravel 12 Modern Patterns

### Model Structure Template

All Chinook models follow this standardized structure:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use App\Traits\Categorizable;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use Wildside\Userstamps\Userstamps;

class ExampleModel extends Model implements HasMedia
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTags;
    use SoftDeletes;
    use Userstamps;
    use Categorizable;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     */
    protected $table = 'example_models';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'public_id',
        'slug',
        // Add other fillable attributes
    ];

    /**
     * Get the attributes that should be cast.
     * Using Laravel 12 cast() method instead of $casts property.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            // Add other casts
        ];
    }

    /**
     * Get the secondary key type for this model.
     */
    public function getSecondaryKeyType(): string
    {
        return 'ulid'; // or 'uuid', 'snowflake' based on requirements
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Define model relationships.
     */
    public function relatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    /**
     * Define query scopes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
    }

    /**
     * Define accessors and mutators.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Auto-generate slug if not provided
            if (empty($model->slug)) {
                $model->slug = \Illuminate\Support\Str::slug($model->name);
            }
        });
    }
}
```

## Required Traits Implementation

### HasSecondaryUniqueKey Trait

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
    protected static function bootHasSecondaryUniqueKey()
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
}
```

### HasSlug Trait

```php
<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the trait.
     */
    protected static function bootHasSlug()
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
}
```

### Categorizable Trait

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
        )->withTimestamps();
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
     * Attach categories to this model.
     */
    public function attachCategories(array $categoryIds): void
    {
        $this->categories()->attach($categoryIds);
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
    public function syncCategories(array $categoryIds): void
    {
        $this->categories()->sync($categoryIds);
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

## CategoryType Enum

```php
<?php

namespace App\Enums;

enum CategoryType: string
{
    case GENRE = 'genre';
    case MOOD = 'mood';
    case THEME = 'theme';
    case ERA = 'era';
    case INSTRUMENT = 'instrument';
    case LANGUAGE = 'language';
    case OCCASION = 'occasion';

    /**
     * Get all enum values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get enum options for forms.
     */
    public static function options(): array
    {
        return [
            self::GENRE->value => 'Genre',
            self::MOOD->value => 'Mood',
            self::THEME->value => 'Theme',
            self::ERA->value => 'Era',
            self::INSTRUMENT->value => 'Instrument',
            self::LANGUAGE->value => 'Language',
            self::OCCASION->value => 'Occasion',
        ];
    }

    /**
     * Get the display label for the enum.
     */
    public function label(): string
    {
        return match ($this) {
            self::GENRE => 'Genre',
            self::MOOD => 'Mood',
            self::THEME => 'Theme',
            self::ERA => 'Era',
            self::INSTRUMENT => 'Instrument',
            self::LANGUAGE => 'Language',
            self::OCCASION => 'Occasion',
        };
    }

    /**
     * Get the description for the enum.
     */
    public function description(): string
    {
        return match ($this) {
            self::GENRE => 'Musical genre classification (Rock, Pop, Jazz, etc.)',
            self::MOOD => 'Emotional mood or feeling (Happy, Sad, Energetic, etc.)',
            self::THEME => 'Thematic content (Love, Adventure, Politics, etc.)',
            self::ERA => 'Time period or era (60s, 80s, Modern, etc.)',
            self::INSTRUMENT => 'Primary instrument focus (Guitar, Piano, Vocals, etc.)',
            self::LANGUAGE => 'Language of the content (English, Spanish, French, etc.)',
            self::OCCASION => 'Suitable occasion (Party, Workout, Relaxation, etc.)',
        };
    }

    /**
     * Get the color for the enum (for UI display).
     */
    public function color(): string
    {
        return match ($this) {
            self::GENRE => '#1976d2',      // Blue
            self::MOOD => '#388e3c',       // Green
            self::THEME => '#f57c00',      // Orange
            self::ERA => '#7b1fa2',        // Purple
            self::INSTRUMENT => '#d32f2f',  // Red
            self::LANGUAGE => '#0288d1',   // Light Blue
            self::OCCASION => '#c2185b',   // Pink
        };
    }

    /**
     * Get the icon for the enum (Heroicons).
     */
    public function icon(): string
    {
        return match ($this) {
            self::GENRE => 'heroicon-o-musical-note',
            self::MOOD => 'heroicon-o-face-smile',
            self::THEME => 'heroicon-o-light-bulb',
            self::ERA => 'heroicon-o-clock',
            self::INSTRUMENT => 'heroicon-o-microphone',
            self::LANGUAGE => 'heroicon-o-language',
            self::OCCASION => 'heroicon-o-calendar-days',
        };
    }
}
```

## Hybrid Hierarchical Architecture

### Category Model with Closure Table

```php
<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Wildside\Userstamps\Userstamps;

class Category extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use SoftDeletes;
    use Userstamps;
    use HasRecursiveRelationships;

    protected $table = 'categories';

    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'type',
        'color',
        'icon',
        'is_active',
        'sort_order',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the secondary key type for this model.
     */
    public function getSecondaryKeyType(): string
    {
        return 'uuid'; // UUID for reference data
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all ancestors using closure table.
     */
    public function ancestors()
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'descendant_id',
            'ancestor_id'
        )->where('category_closure.depth', '>', 0)
         ->orderBy('category_closure.depth', 'desc');
    }

    /**
     * Get all descendants using closure table.
     */
    public function descendants()
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'ancestor_id',
            'descendant_id'
        )->where('category_closure.depth', '>', 0)
         ->orderBy('category_closure.depth', 'asc');
    }

    /**
     * Scope to get root categories.
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, CategoryType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full path of the category.
     */
    public function getFullPathAttribute(): string
    {
        $ancestors = $this->ancestors()->pluck('name')->reverse();
        $ancestors->push($this->name);
        return $ancestors->implode(' > ');
    }

    /**
     * Boot the model to maintain closure table.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($category) {
            $category->rebuildClosureTable();
        });

        static::updated(function ($category) {
            if ($category->isDirty('parent_id')) {
                $category->rebuildClosureTable();
            }
        });

        static::deleted(function ($category) {
            $category->removeFromClosureTable();
        });
    }

    /**
     * Rebuild the closure table for this category.
     */
    public function rebuildClosureTable(): void
    {
        // Implementation for maintaining closure table
        // This would include logic to update the category_closure table
    }

    /**
     * Remove this category from the closure table.
     */
    public function removeFromClosureTable(): void
    {
        // Implementation for removing from closure table
    }
}
```

## Next Steps

1. **Implement Required Traits** - Create and test all required traits
2. **Setup Model Factories** - Create comprehensive factory definitions
3. **Configure Model Observers** - Implement business logic and event handling
4. **Create Model Policies** - Setup authorization and access control
5. **Test Model Functionality** - Comprehensive testing of all model features
6. **Document Usage Examples** - Create practical usage examples and patterns

## Related Documentation

- **[Chinook Models Guide](../../010-chinook-models-guide.md)** - Original model implementations
- **[Setup Documentation](../setup/)** - Panel configuration and authentication
- **[Resources Documentation](../resources/)** - Resource implementation using these models
- **[Testing Documentation](../testing/)** - Model testing strategies
