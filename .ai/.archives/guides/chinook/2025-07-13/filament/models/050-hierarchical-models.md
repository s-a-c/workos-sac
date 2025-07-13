# Hierarchical Models Guide

This guide covers the implementation of hybrid hierarchical data management using the combination of closure table and adjacency list patterns for optimal performance in the Chinook admin panel.

## Table of Contents

- [Overview](#overview)
- [Hybrid Architecture](#hybrid-architecture)
- [Category Model Implementation](#category-model-implementation)
- [Closure Table Management](#closure-table-management)
- [Query Optimization](#query-optimization)
- [Performance Considerations](#performance-considerations)
- [Maintenance Operations](#maintenance-operations)

## Overview

The Chinook admin panel uses a hybrid hierarchical architecture that combines the best aspects of adjacency lists and closure tables to provide optimal performance for both read and write operations.

### Architecture Benefits

- **Write Performance**: Adjacency list for fast category updates and modifications
- **Read Performance**: Closure table for complex hierarchical queries and analytics
- **Flexibility**: Runtime selection of optimal approach based on operation type
- **Scalability**: Efficient handling of both deep hierarchies and frequent updates

## Hybrid Architecture

### Database Schema

The hybrid approach uses two complementary table structures:

```sql
-- Adjacency List (taxonomy terms table)
CREATE TABLE categories (
    id INTEGER PRIMARY KEY,
    parent_id INTEGER REFERENCES categories(id),
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    depth INTEGER DEFAULT 0,
    path TEXT,
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    public_id TEXT UNIQUE NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    created_by INTEGER REFERENCES users(id),
    updated_by INTEGER REFERENCES users(id),
    created_at DATETIME,
    updated_at DATETIME,
    deleted_at DATETIME
);

-- Closure Table (category_closure table)
CREATE TABLE category_closure (
    ancestor_id INTEGER NOT NULL REFERENCES categories(id),
    descendant_id INTEGER NOT NULL REFERENCES categories(id),
    depth INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME,
    updated_at DATETIME,
    PRIMARY KEY (ancestor_id, descendant_id)
);
```

### Indexing Strategy

```sql
-- Adjacency list indexes
CREATE INDEX idx_categories_parent_id ON categories(parent_id);
CREATE INDEX idx_categories_type ON categories(type);
CREATE INDEX idx_categories_active ON categories(is_active);
CREATE INDEX idx_categories_sort_order ON categories(sort_order);
CREATE INDEX idx_categories_path ON categories(path);

-- Closure table indexes
CREATE INDEX idx_closure_ancestor ON category_closure(ancestor_id);
CREATE INDEX idx_closure_descendant ON category_closure(descendant_id);
CREATE INDEX idx_closure_depth ON category_closure(depth);
CREATE INDEX idx_closure_ancestor_depth ON category_closure(ancestor_id, depth);
```

## Category Model Implementation

### Base Category Model

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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Wildside\Userstamps\Userstamps;

class Category extends Model
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use SoftDeletes;
    use Userstamps;

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
            'depth' => 'integer',
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
     * Get the parent category (adjacency list).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories (adjacency list).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
    }

    /**
     * Get all ancestors using closure table.
     */
    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'descendant_id',
            'ancestor_id'
        )->where('category_closure.depth', '>', 0)
         ->orderBy('category_closure.depth', 'desc')
         ->withTimestamps();
    }

    /**
     * Get all descendants using closure table.
     */
    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'ancestor_id',
            'descendant_id'
        )->where('category_closure.depth', '>', 0)
         ->orderBy('category_closure.depth', 'asc')
         ->withTimestamps();
    }

    /**
     * Get immediate children using closure table.
     */
    public function immediateChildren(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'ancestor_id',
            'descendant_id'
        )->where('category_closure.depth', '=', 1)
         ->orderBy('sort_order')
         ->orderBy('name')
         ->withTimestamps();
    }

    /**
     * Get immediate parent using closure table.
     */
    public function immediateParent(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'descendant_id',
            'ancestor_id'
        )->where('category_closure.depth', '=', 1)
         ->withTimestamps();
    }
}
```

### Hierarchical Query Scopes

```php
class Category extends Model
{
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
     * Scope to get categories at a specific depth.
     */
    public function scopeAtDepth($query, int $depth)
    {
        return $query->where('depth', $depth);
    }

    /**
     * Scope to get leaf categories (no children).
     */
    public function scopeLeaves($query)
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Scope to get categories with children.
     */
    public function scopeWithChildren($query)
    {
        return $query->whereHas('children');
    }

    /**
     * Scope to get subtree of a category.
     */
    public function scopeSubtreeOf($query, int $categoryId)
    {
        return $query->whereHas('ancestors', function ($q) use ($categoryId) {
            $q->where('ancestor_id', $categoryId);
        })->orWhere('id', $categoryId);
    }
}
```

## Closure Table Management

### Closure Table Operations

```php
class Category extends Model
{
    /**
     * Boot the model to maintain closure table.
     */
    protected static function boot(): void
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
        // Remove existing closure entries for this category
        \DB::table('category_closure')
           ->where('descendant_id', $this->id)
           ->delete();

        // Add self-reference (depth 0)
        \DB::table('category_closure')->insert([
            'ancestor_id' => $this->id,
            'descendant_id' => $this->id,
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add ancestors if parent exists
        if ($this->parent_id) {
            $ancestorEntries = \DB::table('category_closure')
                ->where('descendant_id', $this->parent_id)
                ->get();

            foreach ($ancestorEntries as $entry) {
                \DB::table('category_closure')->insert([
                    'ancestor_id' => $entry->ancestor_id,
                    'descendant_id' => $this->id,
                    'depth' => $entry->depth + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Update depth and path in adjacency list
        $this->updateDepthAndPath();

        // Rebuild closure table for all descendants
        $this->rebuildDescendantClosures();
    }

    /**
     * Remove this category from the closure table.
     */
    public function removeFromClosureTable(): void
    {
        \DB::table('category_closure')
           ->where('ancestor_id', $this->id)
           ->orWhere('descendant_id', $this->id)
           ->delete();
    }

    /**
     * Update depth and materialized path.
     */
    protected function updateDepthAndPath(): void
    {
        if ($this->parent_id) {
            $parent = static::find($this->parent_id);
            $this->depth = $parent->depth + 1;
            $this->path = $parent->path . '/' . $this->id;
        } else {
            $this->depth = 0;
            $this->path = (string) $this->id;
        }

        $this->saveQuietly(); // Avoid triggering events
    }

    /**
     * Rebuild closure table for all descendants.
     */
    protected function rebuildDescendantClosures(): void
    {
        $children = $this->children()->get();
        
        foreach ($children as $child) {
            $child->rebuildClosureTable();
        }
    }
}
```

### Bulk Operations

```php
class CategoryService
{
    /**
     * Rebuild entire closure table.
     */
    public function rebuildEntireClosureTable(): void
    {
        \DB::transaction(function () {
            // Clear existing closure table
            \DB::table('category_closure')->truncate();

            // Process categories level by level
            $this->processLevel(null, 0);
        });
    }

    /**
     * Process categories at a specific level.
     */
    protected function processLevel(?int $parentId, int $depth): void
    {
        $categories = Category::where('parent_id', $parentId)->get();

        foreach ($categories as $category) {
            // Update depth and path
            $category->depth = $depth;
            $category->path = $parentId ? 
                Category::find($parentId)->path . '/' . $category->id : 
                (string) $category->id;
            $category->saveQuietly();

            // Add closure entries
            $this->addClosureEntries($category);

            // Process children
            $this->processLevel($category->id, $depth + 1);
        }
    }

    /**
     * Add closure table entries for a category.
     */
    protected function addClosureEntries(Category $category): void
    {
        // Self-reference
        \DB::table('category_closure')->insert([
            'ancestor_id' => $category->id,
            'descendant_id' => $category->id,
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ancestor references
        if ($category->parent_id) {
            $ancestors = \DB::table('category_closure')
                ->where('descendant_id', $category->parent_id)
                ->get();

            foreach ($ancestors as $ancestor) {
                \DB::table('category_closure')->insert([
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $category->id,
                    'depth' => $ancestor->depth + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
```

## Query Optimization

### Efficient Hierarchical Queries

```php
class Category extends Model
{
    /**
     * Get the full tree structure efficiently.
     */
    public static function getTree(CategoryType $type = null): Collection
    {
        $query = static::with(['children' => function ($q) {
            $q->orderBy('sort_order')->orderBy('name');
        }])->whereNull('parent_id')
          ->orderBy('sort_order')
          ->orderBy('name');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Get breadcrumb path efficiently.
     */
    public function getBreadcrumbs(): Collection
    {
        return $this->ancestors()
                    ->orderBy('category_closure.depth')
                    ->get()
                    ->push($this);
    }

    /**
     * Get category hierarchy with counts.
     */
    public static function getHierarchyWithCounts(CategoryType $type): Collection
    {
        return static::select([
                'categories.*',
                \DB::raw('COUNT(DISTINCT categorizables.categorizable_id) as usage_count')
            ])
            ->leftJoin('categorizables', 'categories.id', '=', 'categorizables.category_id')
            ->where('type', $type)
            ->groupBy('categories.id')
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get popular categories by usage.
     */
    public static function getPopularCategories(CategoryType $type, int $limit = 10): Collection
    {
        return static::select([
                'categories.*',
                \DB::raw('COUNT(categorizables.categorizable_id) as usage_count')
            ])
            ->join('categorizables', 'categories.id', '=', 'categorizables.category_id')
            ->where('type', $type)
            ->where('is_active', true)
            ->groupBy('categories.id')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();
    }
}
```

## Performance Considerations

### Caching Strategies

```php
class Category extends Model
{
    /**
     * Cache key for category tree.
     */
    public static function getTreeCacheKey(CategoryType $type = null): string
    {
        return 'category_tree' . ($type ? "_{$type->value}" : '');
    }

    /**
     * Get cached tree structure.
     */
    public static function getCachedTree(CategoryType $type = null): Collection
    {
        $cacheKey = static::getTreeCacheKey($type);
        
        return Cache::remember($cacheKey, 3600, function () use ($type) {
            return static::getTree($type);
        });
    }

    /**
     * Clear tree cache.
     */
    public static function clearTreeCache(): void
    {
        foreach (CategoryType::cases() as $type) {
            Cache::forget(static::getTreeCacheKey($type));
        }
        Cache::forget(static::getTreeCacheKey());
    }

    /**
     * Boot method to clear cache on changes.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function () {
            static::clearTreeCache();
        });

        static::deleted(function () {
            static::clearTreeCache();
        });
    }
}
```

### SQLite Optimization

```php
class CategoryRepository
{
    /**
     * Get optimized category query for SQLite.
     */
    public function getOptimizedQuery(): Builder
    {
        return Category::query()
            ->select([
                'id',
                'parent_id',
                'name',
                'type',
                'depth',
                'path',
                'is_active',
                'sort_order'
            ])
            ->where('is_active', true)
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Get descendants with single query.
     */
    public function getDescendants(int $categoryId): Collection
    {
        return Category::select('categories.*')
            ->join('category_closure', 'categories.id', '=', 'category_closure.descendant_id')
            ->where('category_closure.ancestor_id', $categoryId)
            ->where('category_closure.depth', '>', 0)
            ->orderBy('category_closure.depth')
            ->orderBy('categories.sort_order')
            ->get();
    }
}
```

## Maintenance Operations

### Artisan Commands

```php
<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Console\Command;

class RebuildCategoryClosureTable extends Command
{
    protected $signature = 'category:rebuild-closure';
    protected $description = 'Rebuild the category closure table';

    public function handle(CategoryService $categoryService): int
    {
        $this->info('Rebuilding category closure table...');
        
        $categoryService->rebuildEntireClosureTable();
        
        $this->info('Category closure table rebuilt successfully!');
        
        return 0;
    }
}

class ValidateCategoryHierarchy extends Command
{
    protected $signature = 'category:validate';
    protected $description = 'Validate category hierarchy integrity';

    public function handle(): int
    {
        $this->info('Validating category hierarchy...');
        
        $issues = $this->findHierarchyIssues();
        
        if ($issues->isEmpty()) {
            $this->info('No hierarchy issues found!');
        } else {
            $this->error('Found ' . $issues->count() . ' hierarchy issues:');
            foreach ($issues as $issue) {
                $this->line("- {$issue}");
            }
        }
        
        return $issues->isEmpty() ? 0 : 1;
    }

    protected function findHierarchyIssues(): Collection
    {
        $issues = collect();
        
        // Check for orphaned categories
        $orphaned = Category::whereNotNull('parent_id')
            ->whereDoesntHave('parent')
            ->get();
            
        foreach ($orphaned as $category) {
            $issues->push("Category {$category->id} has invalid parent_id {$category->parent_id}");
        }
        
        // Check for circular references
        // Implementation for circular reference detection
        
        return $issues;
    }
}
```

## Next Steps

1. **Implement Category Model** - Create the complete Category model with hybrid architecture
2. **Setup Closure Table** - Create and populate the closure table
3. **Create Service Classes** - Implement CategoryService for complex operations
4. **Add Artisan Commands** - Create maintenance and validation commands
5. **Test Performance** - Benchmark queries and optimize as needed
6. **Document Usage** - Create practical examples and best practices

## Related Documentation

- **[Model Architecture](010-model-architecture.md)** - Overall model structure and patterns
- **[Required Traits](020-required-traits.md)** - Trait implementations for categories
- **[Polymorphic Models](060-polymorphic-models.md)** - Categorizable trait usage
