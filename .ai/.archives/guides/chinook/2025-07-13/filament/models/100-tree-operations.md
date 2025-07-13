# Tree Operations Guide

## Table of Contents

- [Overview](#overview)
- [Adjacency List Pattern](#adjacency-list-pattern)
- [Closure Table Implementation](#closure-table-implementation)
- [Hybrid Tree Architecture](#hybrid-tree-architecture)
- [Tree Traversal Methods](#tree-traversal-methods)
- [Tree Modification Operations](#tree-modification-operations)
- [Performance Optimization](#performance-optimization)
- [Tree Validation](#tree-validation)
- [Testing Tree Operations](#testing-tree-operations)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive tree operations for Laravel 12 models in the Chinook application. The system implements a hybrid closure table + adjacency list architecture for optimal performance in both read and write operations.

**🚀 Key Features:**
- **Hybrid Architecture**: Combines adjacency list simplicity with closure table performance
- **Efficient Traversal**: Optimized tree traversal algorithms
- **Bulk Operations**: Mass tree modifications with transaction safety
- **Integrity Validation**: Comprehensive tree structure validation
- **WCAG 2.1 AA Compliance**: Accessible tree data presentation

## Adjacency List Pattern

### Basic Adjacency List Implementation

```php
<?php
// app/Traits/HasAdjacencyList.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait HasAdjacencyList
{
    /**
     * Parent relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Children relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Siblings relationship
     */
    public function siblings(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')
            ->where('id', '!=', $this->id)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Get all ancestors (recursive query)
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
     * Get all descendants (recursive)
     */
    public function descendants(): Collection
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }
        
        return $descendants;
    }

    /**
     * Get tree depth/level
     */
    public function getDepth(): int
    {
        return $this->ancestors()->count();
    }

    /**
     * Check if node is root
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if node is leaf
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Check if node has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Scope for root nodes
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for leaf nodes
     */
    public function scopeLeaves(Builder $query): Builder
    {
        return $query->whereDoesntHave('children');
    }

    /**
     * Scope for nodes at specific depth
     */
    public function scopeAtDepth(Builder $query, int $depth): Builder
    {
        if ($depth === 0) {
            return $query->roots();
        }

        // This requires a more complex query for deeper levels
        // Better handled by closure table for performance
        return $query->whereHas('ancestors', function ($q) use ($depth) {
            $q->havingRaw('COUNT(*) = ?', [$depth]);
        });
    }
}
```

## Closure Table Implementation

### Closure Table Model

```php
<?php
// app/Models/CategoryClosure.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryClosure extends Model
{
    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    protected function cast(): array
    {
        return [
            'depth' => 'integer',
        ];
    }

    public $timestamps = false;

    /**
     * Ancestor relationship
     */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'ancestor_id');
    }

    /**
     * Descendant relationship
     */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'descendant_id');
    }

    /**
     * Scope for direct relationships (depth = 1)
     */
    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('depth', 1);
    }

    /**
     * Scope for self-references (depth = 0)
     */
    public function scopeSelfReferences(Builder $query): Builder
    {
        return $query->where('depth', 0);
    }

    /**
     * Scope for specific depth
     */
    public function scopeAtDepth(Builder $query, int $depth): Builder
    {
        return $query->where('depth', $depth);
    }
}
```

### Closure Table Trait

```php
<?php
// app/Traits/HasClosureTable.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasClosureTable
{
    /**
     * Closure table relationships
     */
    public function ancestorClosures(): HasMany
    {
        return $this->hasMany($this->getClosureModel(), 'descendant_id');
    }

    public function descendantClosures(): HasMany
    {
        return $this->hasMany($this->getClosureModel(), 'ancestor_id');
    }

    /**
     * Get closure table model class
     */
    protected function getClosureModel(): string
    {
        return static::class . 'Closure';
    }

    /**
     * Get ancestors using closure table
     */
    public function getAncestorsViaClosure(): Collection
    {
        return static::whereIn('id', function ($query) {
            $query->select('ancestor_id')
                ->from($this->getClosureTable())
                ->where('descendant_id', $this->id)
                ->where('depth', '>', 0);
        })->orderBy('depth')->get();
    }

    /**
     * Get descendants using closure table
     */
    public function getDescendantsViaClosure(): Collection
    {
        return static::whereIn('id', function ($query) {
            $query->select('descendant_id')
                ->from($this->getClosureTable())
                ->where('ancestor_id', $this->id)
                ->where('depth', '>', 0);
        })->get();
    }

    /**
     * Get children using closure table
     */
    public function getChildrenViaClosure(): Collection
    {
        return static::whereIn('id', function ($query) {
            $query->select('descendant_id')
                ->from($this->getClosureTable())
                ->where('ancestor_id', $this->id)
                ->where('depth', 1);
        })->get();
    }

    /**
     * Get subtree (node + descendants)
     */
    public function getSubtree(): Collection
    {
        return static::whereIn('id', function ($query) {
            $query->select('descendant_id')
                ->from($this->getClosureTable())
                ->where('ancestor_id', $this->id);
        })->get();
    }

    /**
     * Get closure table name
     */
    protected function getClosureTable(): string
    {
        return $this->getTable() . '_closure';
    }

    /**
     * Scope for ancestors
     */
    public function scopeAncestorsOf(Builder $query, int $nodeId): Builder
    {
        return $query->whereIn('id', function ($subQuery) use ($nodeId) {
            $subQuery->select('ancestor_id')
                ->from($this->getClosureTable())
                ->where('descendant_id', $nodeId)
                ->where('depth', '>', 0);
        });
    }

    /**
     * Scope for descendants
     */
    public function scopeDescendantsOf(Builder $query, int $nodeId): Builder
    {
        return $query->whereIn('id', function ($subQuery) use ($nodeId) {
            $subQuery->select('descendant_id')
                ->from($this->getClosureTable())
                ->where('ancestor_id', $nodeId)
                ->where('depth', '>', 0);
        });
    }
}
```

## Hybrid Tree Architecture

### Hybrid Tree Manager

```php
<?php
// app/Services/HybridTreeManager.php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HybridTreeManager
{
    protected string $model;
    protected string $closureModel;

    public function __construct(string $model)
    {
        $this->model = $model;
        $this->closureModel = $model . 'Closure';
    }

    /**
     * Insert node using hybrid approach
     */
    public function insertNode(Model $node, ?Model $parent = null): void
    {
        DB::transaction(function () use ($node, $parent) {
            // 1. Update adjacency list (simple parent_id update)
            $node->parent_id = $parent?->id;
            $node->save();

            // 2. Update closure table for performance
            $this->insertClosureRecords($node, $parent);
        });
    }

    /**
     * Move node in tree
     */
    public function moveNode(Model $node, ?Model $newParent = null): void
    {
        if ($newParent && $this->wouldCreateCycle($node, $newParent)) {
            throw new InvalidArgumentException('Move would create a cycle');
        }

        DB::transaction(function () use ($node, $newParent) {
            // 1. Remove old closure records
            $this->removeClosureRecords($node);

            // 2. Update adjacency list
            $node->parent_id = $newParent?->id;
            $node->save();

            // 3. Insert new closure records
            $this->insertClosureRecords($node, $newParent);
        });
    }

    /**
     * Delete node and handle children
     */
    public function deleteNode(Model $node, bool $deleteChildren = false): void
    {
        DB::transaction(function () use ($node, $deleteChildren) {
            if ($deleteChildren) {
                // Delete entire subtree
                $descendants = $node->getDescendantsViaClosure();
                foreach ($descendants as $descendant) {
                    $this->removeClosureRecords($descendant);
                    $descendant->delete();
                }
            } else {
                // Move children to parent
                $children = $node->getChildrenViaClosure();
                foreach ($children as $child) {
                    $this->moveNode($child, $node->parent);
                }
            }

            // Remove node's closure records and delete
            $this->removeClosureRecords($node);
            $node->delete();
        });
    }

    /**
     * Insert closure table records for a node
     */
    protected function insertClosureRecords(Model $node, ?Model $parent = null): void
    {
        $closureTable = $this->getClosureTableName();

        // Self-reference
        DB::table($closureTable)->insert([
            'ancestor_id' => $node->id,
            'descendant_id' => $node->id,
            'depth' => 0,
        ]);

        if ($parent) {
            // Copy all ancestor relationships from parent
            DB::table($closureTable)->insertUsing(
                ['ancestor_id', 'descendant_id', 'depth'],
                DB::table($closureTable)
                    ->select('ancestor_id', DB::raw($node->id), DB::raw('depth + 1'))
                    ->where('descendant_id', $parent->id)
            );
        }
    }

    /**
     * Remove closure table records for a node
     */
    protected function removeClosureRecords(Model $node): void
    {
        $closureTable = $this->getClosureTableName();

        DB::table($closureTable)
            ->where('descendant_id', $node->id)
            ->delete();
    }

    /**
     * Check if move would create a cycle
     */
    protected function wouldCreateCycle(Model $node, Model $potentialParent): bool
    {
        return $potentialParent->getAncestorsViaClosure()
            ->pluck('id')
            ->contains($node->id);
    }

    /**
     * Get closure table name
     */
    protected function getClosureTableName(): string
    {
        return (new $this->model)->getTable() . '_closure';
    }

    /**
     * Rebuild entire closure table
     */
    public function rebuildClosureTable(): void
    {
        $closureTable = $this->getClosureTableName();

        DB::transaction(function () use ($closureTable) {
            // Clear existing records
            DB::table($closureTable)->truncate();

            // Rebuild from adjacency list
            $nodes = $this->model::all();

            foreach ($nodes as $node) {
                $this->insertClosureRecords($node, $node->parent);
            }
        });
    }
}
```

## Tree Traversal Methods

### Advanced Traversal Algorithms

```php
<?php
// app/Services/TreeTraversalService.php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TreeTraversalService
{
    /**
     * Depth-first traversal (pre-order)
     */
    public function depthFirstPreOrder(Model $root): Collection
    {
        $result = collect([$root]);

        foreach ($root->children as $child) {
            $result = $result->merge($this->depthFirstPreOrder($child));
        }

        return $result;
    }

    /**
     * Depth-first traversal (post-order)
     */
    public function depthFirstPostOrder(Model $root): Collection
    {
        $result = collect();

        foreach ($root->children as $child) {
            $result = $result->merge($this->depthFirstPostOrder($child));
        }

        $result->push($root);
        return $result;
    }

    /**
     * Breadth-first traversal
     */
    public function breadthFirst(Model $root): Collection
    {
        $result = collect();
        $queue = collect([$root]);

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $result->push($current);

            foreach ($current->children as $child) {
                $queue->push($child);
            }
        }

        return $result;
    }

    /**
     * Level-order traversal (grouped by level)
     */
    public function levelOrder(Model $root): array
    {
        $levels = [];
        $currentLevel = collect([$root]);
        $depth = 0;

        while ($currentLevel->isNotEmpty()) {
            $levels[$depth] = $currentLevel->toArray();
            $nextLevel = collect();

            foreach ($currentLevel as $node) {
                foreach ($node->children as $child) {
                    $nextLevel->push($child);
                }
            }

            $currentLevel = $nextLevel;
            $depth++;
        }

        return $levels;
    }

    /**
     * Find path between two nodes
     */
    public function findPath(Model $from, Model $to): ?Collection
    {
        // Find common ancestor
        $fromAncestors = $from->getAncestorsViaClosure()->pluck('id');
        $toAncestors = $to->getAncestorsViaClosure()->pluck('id');

        $commonAncestors = $fromAncestors->intersect($toAncestors);

        if ($commonAncestors->isEmpty()) {
            return null; // No path exists
        }

        // Find lowest common ancestor
        $lca = $this->findLowestCommonAncestor($from, $to);

        // Build path: from -> lca -> to
        $pathUp = $this->getPathToAncestor($from, $lca);
        $pathDown = $this->getPathFromAncestor($lca, $to);

        return $pathUp->merge($pathDown->slice(1)); // Remove duplicate LCA
    }

    /**
     * Find lowest common ancestor
     */
    public function findLowestCommonAncestor(Model $node1, Model $node2): ?Model
    {
        $ancestors1 = $node1->getAncestorsViaClosure()->keyBy('id');
        $ancestors2 = $node2->getAncestorsViaClosure()->keyBy('id');

        $commonAncestorIds = $ancestors1->keys()->intersect($ancestors2->keys());

        if ($commonAncestorIds->isEmpty()) {
            return null;
        }

        // Return the deepest common ancestor
        return $ancestors1->whereIn('id', $commonAncestorIds)
            ->sortByDesc('depth')
            ->first();
    }
}
```

## Tree Modification Operations

### Tree Modification Service

```php
<?php
// app/Services/TreeModificationService.php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TreeModificationService
{
    /**
     * Bulk insert tree structure
     */
    public function bulkInsertTree(array $treeData, string $modelClass, ?Model $parent = null): Collection
    {
        return DB::transaction(function () use ($treeData, $modelClass, $parent) {
            $created = collect();

            foreach ($treeData as $nodeData) {
                $node = $modelClass::create(array_merge(
                    $nodeData,
                    ['parent_id' => $parent?->id]
                ));

                $created->push($node);

                if (isset($nodeData['children'])) {
                    $children = $this->bulkInsertTree($nodeData['children'], $modelClass, $node);
                    $created = $created->merge($children);
                }
            }

            return $created;
        });
    }

    /**
     * Reorder siblings
     */
    public function reorderSiblings(array $orderedIds, ?int $parentId = null): void
    {
        DB::transaction(function () use ($orderedIds, $parentId) {
            foreach ($orderedIds as $index => $id) {
                DB::table('categories')
                    ->where('id', $id)
                    ->where('parent_id', $parentId)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    /**
     * Copy subtree to new location
     */
    public function copySubtree(Model $source, ?Model $newParent = null): Model
    {
        return DB::transaction(function () use ($source, $newParent) {
            // Create copy of root node
            $copy = $source->replicate();
            $copy->parent_id = $newParent?->id;
            $copy->save();

            // Recursively copy children
            foreach ($source->children as $child) {
                $this->copySubtree($child, $copy);
            }

            return $copy;
        });
    }

    /**
     * Merge two subtrees
     */
    public function mergeSubtrees(Model $source, Model $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Move all children of source to target
            foreach ($source->children as $child) {
                $child->update(['parent_id' => $target->id]);
            }

            // Update closure table
            app(HybridTreeManager::class)->rebuildClosureTable();

            // Delete source node
            $source->delete();
        });
    }

    /**
     * Split node (create new parent)
     */
    public function splitNode(Model $node, array $newParentData): Model
    {
        return DB::transaction(function () use ($node, $newParentData) {
            // Create new parent
            $newParent = $node::create(array_merge(
                $newParentData,
                ['parent_id' => $node->parent_id]
            ));

            // Move original node under new parent
            $node->update(['parent_id' => $newParent->id]);

            return $newParent;
        });
    }
}
```

## Performance Optimization

### Tree Performance Optimizer

```php
<?php
// app/Services/TreePerformanceOptimizer.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TreePerformanceOptimizer
{
    /**
     * Cache tree structure
     */
    public function cacheTreeStructure(string $modelClass, string $cacheKey = null): array
    {
        $cacheKey = $cacheKey ?? "tree_structure_{$modelClass}";

        return Cache::remember($cacheKey, 3600, function () use ($modelClass) {
            return $modelClass::with('children')
                ->whereNull('parent_id')
                ->get()
                ->map(function ($root) {
                    return $this->buildTreeNode($root);
                })
                ->toArray();
        });
    }

    /**
     * Build tree node recursively
     */
    protected function buildTreeNode($node): array
    {
        return [
            'id' => $node->id,
            'name' => $node->name,
            'parent_id' => $node->parent_id,
            'children' => $node->children->map(function ($child) {
                return $this->buildTreeNode($child);
            })->toArray(),
        ];
    }

    /**
     * Optimize tree queries with materialized path
     */
    public function addMaterializedPath(string $modelClass): void
    {
        $model = new $modelClass;
        $table = $model->getTable();

        // Add materialized path column if not exists
        if (!Schema::hasColumn($table, 'path')) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('path', 1000)->nullable()->index();
            });
        }

        // Update paths for all nodes
        $this->updateMaterializedPaths($modelClass);
    }

    /**
     * Update materialized paths
     */
    protected function updateMaterializedPaths(string $modelClass): void
    {
        $roots = $modelClass::whereNull('parent_id')->get();

        foreach ($roots as $root) {
            $this->updateNodePath($root, '/');
        }
    }

    /**
     * Update path for a node and its descendants
     */
    protected function updateNodePath($node, string $parentPath): void
    {
        $path = $parentPath . $node->id . '/';
        $node->update(['path' => $path]);

        foreach ($node->children as $child) {
            $this->updateNodePath($child, $path);
        }
    }

    /**
     * Add database indexes for tree operations
     */
    public function addTreeIndexes(string $table): void
    {
        DB::statement("CREATE INDEX IF NOT EXISTS idx_{$table}_parent_sort ON {$table} (parent_id, sort_order)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_{$table}_path ON {$table} (path)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_{$table}_depth ON {$table} (parent_id) WHERE parent_id IS NOT NULL");
    }
}
```

## Tree Validation

### Tree Integrity Validator

```php
<?php
// app/Services/TreeIntegrityValidator.php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TreeIntegrityValidator
{
    /**
     * Validate entire tree integrity
     */
    public function validateTree(string $modelClass): array
    {
        $errors = [];

        $errors = array_merge($errors, $this->checkCircularReferences($modelClass));
        $errors = array_merge($errors, $this->checkOrphanedNodes($modelClass));
        $errors = array_merge($errors, $this->checkClosureTableIntegrity($modelClass));
        $errors = array_merge($errors, $this->checkSortOrderIntegrity($modelClass));

        return $errors;
    }

    /**
     * Check for circular references
     */
    protected function checkCircularReferences(string $modelClass): array
    {
        $errors = [];
        $nodes = $modelClass::all();

        foreach ($nodes as $node) {
            if ($this->hasCircularReference($node)) {
                $errors[] = "Circular reference detected for node {$node->id}";
            }
        }

        return $errors;
    }

    /**
     * Check if node has circular reference
     */
    protected function hasCircularReference(Model $node): bool
    {
        $visited = collect();
        $current = $node;

        while ($current && $current->parent_id) {
            if ($visited->contains($current->id)) {
                return true;
            }

            $visited->push($current->id);
            $current = $current->parent;
        }

        return false;
    }

    /**
     * Check for orphaned nodes
     */
    protected function checkOrphanedNodes(string $modelClass): array
    {
        $errors = [];

        $orphans = $modelClass::whereNotNull('parent_id')
            ->whereDoesntHave('parent')
            ->get();

        foreach ($orphans as $orphan) {
            $errors[] = "Orphaned node detected: {$orphan->id} references non-existent parent {$orphan->parent_id}";
        }

        return $errors;
    }

    /**
     * Validate closure table integrity
     */
    protected function checkClosureTableIntegrity(string $modelClass): array
    {
        $errors = [];
        $model = new $modelClass;
        $closureTable = $model->getTable() . '_closure';

        // Check for missing self-references
        $missingSelfRefs = DB::table($model->getTable())
            ->leftJoin($closureTable, function ($join) {
                $join->on($model->getTable() . '.id', '=', $closureTable . '.ancestor_id')
                     ->on($model->getTable() . '.id', '=', $closureTable . '.descendant_id')
                     ->where($closureTable . '.depth', 0);
            })
            ->whereNull($closureTable . '.ancestor_id')
            ->pluck('id');

        foreach ($missingSelfRefs as $id) {
            $errors[] = "Missing self-reference in closure table for node {$id}";
        }

        return $errors;
    }

    /**
     * Check sort order integrity
     */
    protected function checkSortOrderIntegrity(string $modelClass): array
    {
        $errors = [];

        $parentGroups = $modelClass::selectRaw('parent_id, COUNT(*) as count, MAX(sort_order) as max_sort')
            ->groupBy('parent_id')
            ->get();

        foreach ($parentGroups as $group) {
            if ($group->max_sort >= $group->count) {
                // Check for gaps or duplicates
                $sortOrders = $modelClass::where('parent_id', $group->parent_id)
                    ->pluck('sort_order')
                    ->sort()
                    ->values();

                $expected = range(0, $group->count - 1);

                if ($sortOrders->toArray() !== $expected) {
                    $errors[] = "Sort order integrity issue for parent {$group->parent_id}";
                }
            }
        }

        return $errors;
    }

    /**
     * Auto-fix tree integrity issues
     */
    public function autoFixTree(string $modelClass): array
    {
        $fixed = [];

        // Fix orphaned nodes
        $orphans = $modelClass::whereNotNull('parent_id')
            ->whereDoesntHave('parent')
            ->get();

        foreach ($orphans as $orphan) {
            $orphan->update(['parent_id' => null]);
            $fixed[] = "Fixed orphaned node {$orphan->id}";
        }

        // Rebuild closure table
        app(HybridTreeManager::class)->rebuildClosureTable();
        $fixed[] = "Rebuilt closure table";

        // Fix sort orders
        $this->fixSortOrders($modelClass);
        $fixed[] = "Fixed sort orders";

        return $fixed;
    }

    /**
     * Fix sort order sequences
     */
    protected function fixSortOrders(string $modelClass): void
    {
        $parentGroups = $modelClass::selectRaw('parent_id')
            ->groupBy('parent_id')
            ->pluck('parent_id');

        foreach ($parentGroups as $parentId) {
            $siblings = $modelClass::where('parent_id', $parentId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($siblings as $index => $sibling) {
                $sibling->update(['sort_order' => $index]);
            }
        }
    }
}
```

## Testing Tree Operations

### Tree Operations Test Suite

```php
<?php
// tests/Feature/TreeOperationsTest.php

use App\Models\Category;
use App\Services\HybridTreeManager;
use App\Services\TreeIntegrityValidator;
use Tests\TestCase;

class TreeOperationsTest extends TestCase
{
    public function test_can_create_tree_structure(): void
    {
        $root = Category::factory()->create(['name' => 'Root']);
        $child1 = Category::factory()->create(['name' => 'Child 1', 'parent_id' => $root->id]);
        $child2 = Category::factory()->create(['name' => 'Child 2', 'parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['name' => 'Grandchild', 'parent_id' => $child1->id]);

        $this->assertEquals(2, $root->children->count());
        $this->assertEquals(1, $child1->children->count());
        $this->assertEquals(0, $child2->children->count());
        $this->assertTrue($grandchild->parent->is($child1));
    }

    public function test_can_move_node_in_tree(): void
    {
        $root = Category::factory()->create();
        $parent1 = Category::factory()->create(['parent_id' => $root->id]);
        $parent2 = Category::factory()->create(['parent_id' => $root->id]);
        $child = Category::factory()->create(['parent_id' => $parent1->id]);

        $treeManager = app(HybridTreeManager::class);
        $treeManager->moveNode($child, $parent2);

        $this->assertEquals($parent2->id, $child->fresh()->parent_id);
        $this->assertEquals(0, $parent1->fresh()->children->count());
        $this->assertEquals(1, $parent2->fresh()->children->count());
    }

    public function test_prevents_circular_references(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $treeManager = app(HybridTreeManager::class);

        $this->expectException(InvalidArgumentException::class);
        $treeManager->moveNode($parent, $child);
    }

    public function test_validates_tree_integrity(): void
    {
        $validator = app(TreeIntegrityValidator::class);

        // Create valid tree
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $errors = $validator->validateTree(Category::class);
        $this->assertEmpty($errors);

        // Create orphaned node
        $orphan = Category::factory()->create(['parent_id' => 999]);

        $errors = $validator->validateTree(Category::class);
        $this->assertNotEmpty($errors);
        $this->assertStringContains('Orphaned node', $errors[0]);
    }
}
```

## Best Practices

### Tree Operations Guidelines

1. **Transaction Safety**: Always wrap tree modifications in database transactions
2. **Integrity Validation**: Regularly validate tree structure integrity
3. **Performance Monitoring**: Monitor query performance for deep trees
4. **Caching Strategy**: Cache frequently accessed tree structures
5. **Batch Operations**: Use bulk operations for large tree modifications
6. **Index Optimization**: Maintain proper database indexes for tree queries

### Implementation Checklist

```php
<?php
// Tree operations implementation checklist

/*
✓ Implement adjacency list with parent_id column
✓ Create closure table for performance optimization
✓ Add HybridTreeManager for tree operations
✓ Implement tree traversal algorithms
✓ Create tree modification services
✓ Add performance optimization with caching
✓ Implement tree integrity validation
✓ Write comprehensive test suite
✓ Add database indexes for tree queries
✓ Set up monitoring for tree operations
✓ Document tree structure constraints
✓ Implement auto-fix for common issues
*/
```

## Navigation

**← Previous:** [Category Management Guide](090-category-management.md)
**Next →** [Performance Optimization Guide](110-performance-optimization.md)

**Related Guides:**
- [Hierarchical Models Guide](050-hierarchical-models.md) - Tree structure foundations
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Category Management Guide](090-category-management.md) - Category-specific tree operations

---

*This guide provides comprehensive tree operations for Laravel 12 models in the Chinook application. The hybrid architecture combines adjacency list simplicity with closure table performance for optimal tree management.*
