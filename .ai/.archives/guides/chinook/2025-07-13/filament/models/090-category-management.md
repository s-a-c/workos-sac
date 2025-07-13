# Taxonomy Management Guide

## Table of Contents

- [Overview](#overview)
- [Taxonomy System Architecture](#taxonomy-system-architecture)
- [Hierarchical Taxonomy System](#hierarchical-taxonomy-system)
- [Taxonomy Type Management](#taxonomy-type-management)
- [Bulk Taxonomy Operations](#bulk-taxonomy-operations)
- [Taxonomy Validation](#taxonomy-validation)
- [Performance Optimization](#performance-optimization)
- [Taxonomy Analytics](#taxonomy-analytics)
- [Testing Taxonomy Management](#testing-taxonomy-management)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive taxonomy management for Laravel 12 models in the Chinook application using the `aliziodev/laravel-taxonomy` package. The system provides hierarchical taxonomies with type classification, bulk operations, and advanced analytics capabilities.

**🚀 Key Features:**
- **Single Taxonomy System**: Uses only `aliziodev/laravel-taxonomy` package
- **Hierarchical Structure**: Tree-based taxonomy organization with parent-child relationships
- **Type Classification**: String-based taxonomy types for different classification systems
- **Bulk Operations**: Efficient mass taxonomy management
- **Analytics Integration**: Taxonomy usage statistics and insights
- **WCAG 2.1 AA Compliance**: Accessible taxonomy management interfaces

## Taxonomy System Architecture

### Taxonomy Model Integration

The Chinook application uses the `aliziodev/laravel-taxonomy` package exclusively for all categorization needs. This provides a standardized, well-maintained taxonomy system.

```php
<?php
// Using aliziodev/laravel-taxonomy package

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

use App\Traits\HasSlug;
use App\Traits\HasSecondaryUniqueKey;
use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

// The Taxonomy model is provided by the aliziodev/laravel-taxonomy package
// No custom Category model needed

### Taxonomy Model Features

The `Taxonomy` model from `aliziodev/laravel-taxonomy` provides:

- **Hierarchical Structure**: Built-in parent-child relationships
- **Type Classification**: String-based taxonomy types
- **Metadata Support**: JSON metadata field for additional data
- **Slug Generation**: Automatic URL-friendly slug creation
- **Active Status**: Enable/disable taxonomies
- **Timestamps**: Created and updated timestamps

### Basic Taxonomy Operations

```php
<?php
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

// Create a new taxonomy
$genreTaxonomy = Taxonomy::create([
    'name' => 'Rock',
    'type' => 'genre',
    'description' => 'Rock music genre',
    'is_active' => true,
]);

// Create hierarchical taxonomy
$subGenre = Taxonomy::create([
    'name' => 'Progressive Rock',
    'type' => 'genre',
    'parent_id' => $genreTaxonomy->id,
    'description' => 'Progressive rock subgenre',
    'is_active' => true,
]);

// Get parent and children
$parent = $subGenre->parent;
$children = $genreTaxonomy->children;

    /**
     * All descendants (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;
        
        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }
        
        return $ancestors;
    }

    /**
     * Polymorphic relationships
     */
    public function artists(): MorphToMany
    {
        return $this->morphedByMany(Artist::class, 'categorizable')
            ->withPivot(['category_type', 'sort_order', 'is_primary'])
            ->withTimestamps();
    }

    public function albums(): MorphToMany
    {
        return $this->morphedByMany(Album::class, 'categorizable')
            ->withPivot(['category_type', 'sort_order', 'is_primary'])
            ->withTimestamps();
    }

    public function tracks(): MorphToMany
    {
        return $this->morphedByMany(Track::class, 'categorizable')
            ->withPivot(['category_type', 'sort_order', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Scope for active categories
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category type
     */
    public function scopeOfType(Builder $query, CategoryType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for root categories (no parent)
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get category level in hierarchy
     */
    public function getLevel(): int
    {
        return $this->ancestors()->count();
    }

    /**
     * Get full category path
     */
    public function getPath(string $separator = ' > '): string
    {
        $path = $this->ancestors()->pluck('name')->toArray();
        $path[] = $this->name;
        
        return implode($separator, $path);
    }

    /**
     * Check if category has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if category is ancestor of another
     */
    public function isAncestorOf(Category $category): bool
    {
        return $category->ancestors()->contains('id', $this->id);
    }

    /**
     * Check if category is descendant of another
     */
    public function isDescendantOf(Category $category): bool
    {
        return $this->ancestors()->contains('id', $category->id);
    }
}
```

## Hierarchical Category System

### Tree Operations Manager

```php
<?php
// app/Services/CategoryTreeManager.php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryClosure;
use App\Enums\CategoryType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryTreeManager
{
    /**
     * Build category tree structure
     */
    public function buildTree(CategoryType $type = null): Collection
    {
        $query = Category::with(['children' => function ($q) {
            $q->orderBy('sort_order')->orderBy('name');
        }])->roots()->orderBy('sort_order')->orderBy('name');

        if ($type) {
            $query->ofType($type);
        }

        return $query->get()->map(function ($category) {
            return $this->buildCategoryNode($category);
        });
    }

    /**
     * Build individual category node with children
     */
    protected function buildCategoryNode(Category $category): array
    {
        return [
            'id' => $category->id,
            'public_id' => $category->public_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'type' => $category->type,
            'level' => $category->getLevel(),
            'path' => $category->getPath(),
            'is_active' => $category->is_active,
            'children_count' => $category->children->count(),
            'children' => $category->children->map(function ($child) {
                return $this->buildCategoryNode($child);
            }),
        ];
    }

    /**
     * Move category to new parent
     */
    public function moveCategory(Category $category, ?Category $newParent = null): bool
    {
        // Prevent circular references
        if ($newParent && $category->isAncestorOf($newParent)) {
            throw new InvalidArgumentException('Cannot move category to its own descendant');
        }

        return DB::transaction(function () use ($category, $newParent) {
            $category->update(['parent_id' => $newParent?->id]);
            $this->rebuildClosureTable();
            return true;
        });
    }

    /**
     * Rebuild closure table for performance
     */
    public function rebuildClosureTable(): void
    {
        DB::transaction(function () {
            // Clear existing closure records
            CategoryClosure::truncate();

            // Rebuild closure table
            $categories = Category::all();
            
            foreach ($categories as $category) {
                // Self-reference
                CategoryClosure::create([
                    'ancestor_id' => $category->id,
                    'descendant_id' => $category->id,
                    'depth' => 0,
                ]);

                // Ancestor relationships
                $ancestors = $category->ancestors();
                foreach ($ancestors as $depth => $ancestor) {
                    CategoryClosure::create([
                        'ancestor_id' => $ancestor->id,
                        'descendant_id' => $category->id,
                        'depth' => $depth + 1,
                    ]);
                }
            }
        });
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats(CategoryType $type = null): array
    {
        $query = Category::query();
        
        if ($type) {
            $query->ofType($type);
        }

        return [
            'total_categories' => $query->count(),
            'active_categories' => $query->active()->count(),
            'root_categories' => $query->roots()->count(),
            'max_depth' => $this->getMaxDepth($type),
            'categories_by_type' => $this->getCategoriesByType(),
        ];
    }

    /**
     * Get maximum category depth
     */
    protected function getMaxDepth(CategoryType $type = null): int
    {
        $query = CategoryClosure::query();
        
        if ($type) {
            $query->whereHas('descendant', function ($q) use ($type) {
                $q->ofType($type);
            });
        }

        return $query->max('depth') ?? 0;
    }

    /**
     * Get category count by type
     */
    protected function getCategoriesByType(): array
    {
        return Category::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }
}
```

## Category Type Management

### Category Type Service

```php
<?php
// app/Services/CategoryTypeService.php

namespace App\Services;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryTypeService
{
    /**
     * Get categories for specific type with hierarchy
     */
    public function getCategoriesForType(CategoryType $type): Collection
    {
        return Category::ofType($type)
            ->active()
            ->with(['parent', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create category hierarchy for type
     */
    public function createCategoryHierarchy(CategoryType $type, array $hierarchy): Collection
    {
        $created = collect();

        foreach ($hierarchy as $categoryData) {
            $category = $this->createCategoryWithChildren($type, $categoryData);
            $created->push($category);
        }

        return $created;
    }

    /**
     * Create category with children recursively
     */
    protected function createCategoryWithChildren(CategoryType $type, array $data, ?Category $parent = null): Category
    {
        $category = Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'parent_id' => $parent?->id,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? [],
        ]);

        if (isset($data['children'])) {
            foreach ($data['children'] as $childData) {
                $this->createCategoryWithChildren($type, $childData, $category);
            }
        }

        return $category;
    }

    /**
     * Get category type statistics
     */
    public function getTypeStatistics(): array
    {
        $stats = [];

        foreach (CategoryType::cases() as $type) {
            $categories = Category::ofType($type);
            
            $stats[$type->value] = [
                'label' => $type->label(),
                'description' => $type->description(),
                'total_count' => $categories->count(),
                'active_count' => $categories->active()->count(),
                'root_count' => $categories->roots()->count(),
                'usage_count' => $this->getCategoryUsageCount($type),
            ];
        }

        return $stats;
    }

    /**
     * Get usage count for category type
     */
    protected function getCategoryUsageCount(CategoryType $type): int
    {
        return DB::table('categorizables')
            ->where('category_type', $type->value)
            ->distinct('categorizable_id', 'categorizable_type')
            ->count();
    }

    /**
     * Validate category type assignment
     */
    public function validateTypeAssignment(Category $category, CategoryType $newType): array
    {
        $errors = [];

        // Check if category has existing assignments
        $hasAssignments = DB::table('categorizables')
            ->where('category_id', $category->id)
            ->exists();

        if ($hasAssignments && $category->type !== $newType) {
            $errors[] = 'Cannot change type of category with existing assignments';
        }

        // Check parent-child type consistency
        if ($category->parent && $category->parent->type !== $newType) {
            $errors[] = 'Category type must match parent category type';
        }

        if ($category->children()->where('type', '!=', $newType)->exists()) {
            $errors[] = 'Cannot change type when children have different types';
        }

        return $errors;
    }
}
```

## Bulk Category Operations

### Bulk Category Manager

```php
<?php
// app/Services/BulkCategoryManager.php

namespace App\Services;

use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkCategoryManager
{
    /**
     * Bulk create categories from array
     */
    public function bulkCreate(array $categoriesData): Collection
    {
        return DB::transaction(function () use ($categoriesData) {
            $created = collect();

            foreach ($categoriesData as $data) {
                $category = Category::create($data);
                $created->push($category);
            }

            return $created;
        });
    }

    /**
     * Bulk update categories
     */
    public function bulkUpdate(array $updates): int
    {
        return DB::transaction(function () use ($updates) {
            $updated = 0;

            foreach ($updates as $id => $data) {
                $updated += Category::where('id', $id)->update($data);
            }

            return $updated;
        });
    }

    /**
     * Bulk assign categories to models
     */
    public function bulkAssignToModels(array $categoryIds, array $modelData): void
    {
        DB::transaction(function () use ($categoryIds, $modelData) {
            $insertData = [];

            foreach ($modelData as $model) {
                foreach ($categoryIds as $categoryId) {
                    $insertData[] = [
                        'category_id' => $categoryId,
                        'categorizable_id' => $model['id'],
                        'categorizable_type' => $model['type'],
                        'category_type' => $model['category_type'],
                        'sort_order' => $model['sort_order'] ?? 0,
                        'is_primary' => $model['is_primary'] ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::table('categorizables')->insert($insertData);
        });
    }

    /**
     * Bulk remove category assignments
     */
    public function bulkRemoveAssignments(array $categoryIds, string $modelType = null): int
    {
        $query = DB::table('categorizables')
            ->whereIn('category_id', $categoryIds);

        if ($modelType) {
            $query->where('categorizable_type', $modelType);
        }

        return $query->delete();
    }

    /**
     * Merge categories (move all assignments from source to target)
     */
    public function mergeCategories(Category $source, Category $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Move all assignments to target category
            DB::table('categorizables')
                ->where('category_id', $source->id)
                ->update(['category_id' => $target->id]);

            // Move children to target category
            Category::where('parent_id', $source->id)
                ->update(['parent_id' => $target->id]);

            // Soft delete source category
            $source->delete();
        });
    }
}
```

## Category Validation

### Category Validation Service

```php
<?php
// app/Services/CategoryValidationService.php

namespace App\Services;

use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Validation\Rule;

class CategoryValidationService
{
    /**
     * Get validation rules for category creation
     */
    public function getCreationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get validation rules for category update
     */
    public function getUpdateRules(Category $category): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255',
                Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'parent_id' => ['nullable', 'exists:categories,id',
                function ($attribute, $value, $fail) use ($category) {
                    if ($value && $this->wouldCreateCircularReference($category, $value)) {
                        $fail('Cannot create circular reference in category hierarchy.');
                    }
                }],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Check for circular reference
     */
    protected function wouldCreateCircularReference(Category $category, int $parentId): bool
    {
        $potentialParent = Category::find($parentId);

        if (!$potentialParent) {
            return false;
        }

        return $category->isAncestorOf($potentialParent);
    }

    /**
     * Validate category assignment
     */
    public function validateAssignment(Category $category, string $modelType, CategoryType $assignmentType): array
    {
        $errors = [];

        // Check if category type matches assignment type
        if ($category->type !== $assignmentType) {
            $errors[] = "Category type {$category->type->value} does not match assignment type {$assignmentType->value}";
        }

        // Check if category is active
        if (!$category->is_active) {
            $errors[] = 'Cannot assign inactive category';
        }

        // Check model type compatibility
        if (!$this->isModelTypeCompatible($modelType, $assignmentType)) {
            $errors[] = "Model type {$modelType} is not compatible with category type {$assignmentType->value}";
        }

        return $errors;
    }

    /**
     * Check model type compatibility with category type
     */
    protected function isModelTypeCompatible(string $modelType, CategoryType $categoryType): bool
    {
        $compatibility = [
            'App\Models\Artist' => [CategoryType::GENRE, CategoryType::ERA, CategoryType::MOOD],
            'App\Models\Album' => [CategoryType::GENRE, CategoryType::ERA, CategoryType::MOOD, CategoryType::THEME],
            'App\Models\Track' => [CategoryType::GENRE, CategoryType::MOOD, CategoryType::THEME, CategoryType::INSTRUMENT, CategoryType::LANGUAGE, CategoryType::OCCASION],
        ];

        return in_array($categoryType, $compatibility[$modelType] ?? []);
    }
}
```

## Performance Optimization

### Category Performance Optimizer

```php
<?php
// app/Services/CategoryPerformanceOptimizer.php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryPerformanceOptimizer
{
    /**
     * Cache category tree for performance
     */
    public function getCachedCategoryTree(CategoryType $type = null): array
    {
        $cacheKey = 'category_tree_' . ($type?->value ?? 'all');

        return Cache::remember($cacheKey, 3600, function () use ($type) {
            return app(CategoryTreeManager::class)->buildTree($type);
        });
    }

    /**
     * Preload category relationships
     */
    public function preloadCategoryRelationships(Collection $models): Collection
    {
        return $models->load([
            'categories' => function ($query) {
                $query->select(['id', 'name', 'slug', 'type', 'parent_id'])
                      ->orderBy('pivot_sort_order');
            }
        ]);
    }

    /**
     * Optimize category queries with indexes
     */
    public function optimizeCategoryQueries(): void
    {
        // Add database indexes for better performance
        DB::statement('CREATE INDEX IF NOT EXISTS idx_categories_type_active ON categories (type, is_active)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_categories_parent_sort ON categories (parent_id, sort_order)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_categorizables_type ON categorizables (category_type, categorizable_type)');
    }

    /**
     * Clear category caches
     */
    public function clearCategoryCache(): void
    {
        $patterns = [
            'category_tree_*',
            'category_stats_*',
            'model_categories_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
```

## Category Analytics

### Category Analytics Service

```php
<?php
// app/Services/CategoryAnalyticsService.php

namespace App\Services;

use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Support\Facades\DB;

class CategoryAnalyticsService
{
    /**
     * Get category usage analytics
     */
    public function getCategoryUsageAnalytics(): array
    {
        return [
            'most_used_categories' => $this->getMostUsedCategories(),
            'least_used_categories' => $this->getLeastUsedCategories(),
            'usage_by_type' => $this->getUsageByType(),
            'assignment_trends' => $this->getAssignmentTrends(),
        ];
    }

    /**
     * Get most used categories
     */
    protected function getMostUsedCategories(int $limit = 10): array
    {
        return DB::table('categorizables')
            ->select('category_id', DB::raw('COUNT(*) as usage_count'))
            ->join('categories', 'categories.id', '=', 'categorizables.category_id')
            ->groupBy('category_id')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $category = Category::find($item->category_id);
                return [
                    'category' => $category->name,
                    'type' => $category->type->label(),
                    'usage_count' => $item->usage_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get assignment trends over time
     */
    protected function getAssignmentTrends(): array
    {
        return DB::table('categorizables')
            ->select(
                DB::raw('DATE(created_at) as date'),
                'category_type',
                DB::raw('COUNT(*) as assignments')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date', 'category_type')
            ->orderBy('date')
            ->get()
            ->groupBy('category_type')
            ->toArray();
    }
}
```

## Testing Category Management

### Category Management Tests

```php
<?php
// tests/Feature/CategoryManagementTest.php

use App\Models\Category;
use App\Models\Artist;
use App\Enums\CategoryType;
use App\Services\CategoryTreeManager;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    public function test_can_create_category_hierarchy(): void
    {
        $parent = Category::factory()->create([
            'name' => 'Rock',
            'type' => CategoryType::GENRE,
        ]);

        $child = Category::factory()->create([
            'name' => 'Progressive Rock',
            'type' => CategoryType::GENRE,
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains($child));
        $this->assertEquals(1, $child->getLevel());
        $this->assertEquals('Rock > Progressive Rock', $child->getPath());
    }

    public function test_can_move_category_in_hierarchy(): void
    {
        $oldParent = Category::factory()->create(['type' => CategoryType::GENRE]);
        $newParent = Category::factory()->create(['type' => CategoryType::GENRE]);
        $category = Category::factory()->create([
            'parent_id' => $oldParent->id,
            'type' => CategoryType::GENRE,
        ]);

        $treeManager = app(CategoryTreeManager::class);
        $treeManager->moveCategory($category, $newParent);

        $this->assertEquals($newParent->id, $category->fresh()->parent_id);
    }

    public function test_prevents_circular_references(): void
    {
        $parent = Category::factory()->create(['type' => CategoryType::GENRE]);
        $child = Category::factory()->create([
            'parent_id' => $parent->id,
            'type' => CategoryType::GENRE,
        ]);

        $treeManager = app(CategoryTreeManager::class);

        $this->expectException(InvalidArgumentException::class);
        $treeManager->moveCategory($parent, $child);
    }
}
```

## Best Practices

### Category Management Guidelines

1. **Hierarchy Design**: Keep category hierarchies shallow (max 4-5 levels)
2. **Type Consistency**: Ensure parent and child categories have the same type
3. **Performance**: Use closure tables for complex hierarchical queries
4. **Caching**: Cache frequently accessed category trees
5. **Validation**: Always validate category assignments before saving
6. **Analytics**: Monitor category usage to optimize structure

### Implementation Checklist

```php
<?php
// Category management implementation checklist

/*
✓ Create Category model with hierarchical relationships
✓ Implement CategoryType enum with all classification types
✓ Add CategoryClosure model for performance optimization
✓ Create CategoryTreeManager service for tree operations
✓ Implement bulk category operations
✓ Add comprehensive validation rules
✓ Set up performance optimization with caching
✓ Create analytics service for usage insights
✓ Write comprehensive tests for all operations
✓ Add database indexes for query performance
✓ Implement security policies for category access
✓ Document category type compatibility rules
*/
```

## Navigation

**← Previous:** [Secondary Keys Guide](080-secondary-keys.md)
**Next →** [Tree Operations Guide](100-tree-operations.md)

**Related Guides:**
- [Categorizable Trait Guide](060-categorizable-trait.md) - Polymorphic category relationships
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Hierarchical Models Guide](050-hierarchical-models.md) - Tree structure patterns

---

*This guide provides comprehensive category management for Laravel 12 models in the Chinook application. The system includes hierarchical organization, type management, and bulk operations with performance optimization and analytics capabilities.*
