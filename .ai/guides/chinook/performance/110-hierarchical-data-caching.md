# 1. Hierarchical Data Caching

## Table of Contents

- [1. Overview](#1-overview)
- [2. Caching Architecture](#2-caching-architecture)
- [3. Taxonomy Hierarchy Caching](#3-taxonomy-hierarchy-caching)
- [4. Query Result Caching](#4-query-result-caching)
- [5. Cache Invalidation Strategies](#5-cache-invalidation-strategies)
- [6. Performance Optimization](#6-performance-optimization)
- [7. Monitoring and Metrics](#7-monitoring-and-metrics)
- [8. Best Practices](#8-best-practices)
- [9. Navigation](#9-navigation)

## 1. Overview

This guide provides advanced caching strategies for hierarchical data in the Chinook application using aliziodev/laravel-taxonomy. The focus is on optimizing taxonomy tree operations, relationship queries, and maintaining cache consistency.

**Caching Goals**:
- Sub-50ms response times for cached hierarchy queries
- 90%+ cache hit ratio for frequently accessed hierarchies
- Intelligent cache invalidation to maintain data consistency
- Memory-efficient caching for large taxonomy trees

**Key Caching Areas**:
- Taxonomy hierarchy trees and subtrees
- Ancestor/descendant relationship queries
- Taxonomy path calculations
- Aggregated taxonomy statistics

## 2. Caching Architecture

### 2.1 Multi-Layer Caching Strategy

```php
<?php

// Hierarchical caching service
class HierarchicalCacheService
{
    private const CACHE_TTL = [
        'hierarchy_tree' => 7200,      // 2 hours
        'taxonomy_path' => 3600,       // 1 hour
        'descendant_ids' => 1800,      // 30 minutes
        'taxonomy_stats' => 900,       // 15 minutes
    ];
    
    public function __construct(
        private CacheManager $cache,
        private TaxonomyRepository $taxonomyRepository
    ) {}
    
    // Cache entire taxonomy tree
    public function getTaxonomyTree(int $rootId = null): Collection
    {
        $cacheKey = $rootId ? "taxonomy:tree:{$rootId}" : "taxonomy:tree:all";
        
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL['hierarchy_tree'],
            fn() => $this->buildTaxonomyTree($rootId)
        );
    }
    
    // Cache taxonomy descendants with depth information
    public function getDescendantIds(int $taxonomyId, int $maxDepth = null): array
    {
        $cacheKey = $maxDepth 
            ? "taxonomy:{$taxonomyId}:descendants:depth:{$maxDepth}"
            : "taxonomy:{$taxonomyId}:descendants:all";
            
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL['descendant_ids'],
            fn() => $this->taxonomyRepository->getDescendantIds($taxonomyId, $maxDepth)
        );
    }
    
    // Cache taxonomy path from root
    public function getTaxonomyPath(int $taxonomyId): array
    {
        $cacheKey = "taxonomy:{$taxonomyId}:path";
        
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL['taxonomy_path'],
            fn() => $this->taxonomyRepository->getTaxonomyPath($taxonomyId)
        );
    }
    
    private function buildTaxonomyTree(int $rootId = null): Collection
    {
        if ($rootId) {
            $root = Taxonomy::find($rootId);
            return $root ? $root->descendants()->with('children')->get() : collect();
        }
        
        return Taxonomy::whereNull('parent_id')
                      ->with('descendants')
                      ->get();
    }
}
```

### 2.2 Cache Key Generation

```php
<?php

// Standardized cache key generation
class CacheKeyGenerator
{
    private const PREFIX = 'chinook';
    private const VERSION = 'v1';
    
    public static function taxonomyTree(int $rootId = null, array $filters = []): string
    {
        $base = self::PREFIX . ':' . self::VERSION . ':taxonomy:tree';
        
        if ($rootId) {
            $base .= ":{$rootId}";
        }
        
        if (!empty($filters)) {
            $base .= ':' . md5(serialize($filters));
        }
        
        return $base;
    }
    
    public static function taxonomyDescendants(int $taxonomyId, array $options = []): string
    {
        $base = self::PREFIX . ':' . self::VERSION . ":taxonomy:{$taxonomyId}:descendants";
        
        if (isset($options['depth'])) {
            $base .= ":depth:{$options['depth']}";
        }
        
        if (isset($options['type'])) {
            $base .= ":type:{$options['type']}";
        }
        
        return $base;
    }
    
    public static function taxonomyAncestors(int $taxonomyId): string
    {
        return self::PREFIX . ':' . self::VERSION . ":taxonomy:{$taxonomyId}:ancestors";
    }
    
    public static function taxonomyStats(int $taxonomyId, string $statType): string
    {
        return self::PREFIX . ':' . self::VERSION . ":taxonomy:{$taxonomyId}:stats:{$statType}";
    }
}
```

## 3. Taxonomy Hierarchy Caching

### 3.1 Tree Structure Caching

```php
<?php

// Optimized taxonomy tree caching
class TaxonomyTreeCache
{
    public function getOptimizedTree(int $rootId, array $options = []): array
    {
        $cacheKey = CacheKeyGenerator::taxonomyTree($rootId, $options);
        
        return Cache::remember($cacheKey, 3600, function () use ($rootId, $options) {
            return $this->buildOptimizedTree($rootId, $options);
        });
    }
    
    private function buildOptimizedTree(int $rootId, array $options): array
    {
        // Use closure table for efficient hierarchy queries
        $descendants = DB::table('taxonomy_closure as tc')
            ->join('taxonomies as t', 'tc.descendant_id', '=', 't.id')
            ->where('tc.ancestor_id', $rootId)
            ->select([
                't.id',
                't.name',
                't.type',
                't.parent_id',
                'tc.depth'
            ])
            ->orderBy('tc.depth')
            ->orderBy('t.name')
            ->get();
        
        return $this->buildTreeStructure($descendants->toArray());
    }
    
    private function buildTreeStructure(array $nodes): array
    {
        $tree = [];
        $lookup = [];
        
        foreach ($nodes as $node) {
            $lookup[$node->id] = $node;
            $lookup[$node->id]->children = [];
        }
        
        foreach ($nodes as $node) {
            if ($node->parent_id && isset($lookup[$node->parent_id])) {
                $lookup[$node->parent_id]->children[] = &$lookup[$node->id];
            } else {
                $tree[] = &$lookup[$node->id];
            }
        }
        
        return $tree;
    }
}
```

### 3.2 Intelligent Subtree Caching

```php
<?php

// Intelligent subtree caching
class SubtreeCacheManager
{
    private const MAX_SUBTREE_SIZE = 1000; // Maximum nodes to cache as subtree
    
    public function getSubtree(int $taxonomyId, int $maxDepth = 3): array
    {
        // Check if subtree is small enough to cache
        $nodeCount = $this->getSubtreeNodeCount($taxonomyId, $maxDepth);
        
        if ($nodeCount > self::MAX_SUBTREE_SIZE) {
            // For large subtrees, use pagination or lazy loading
            return $this->getLargeSubtree($taxonomyId, $maxDepth);
        }
        
        $cacheKey = "taxonomy:{$taxonomyId}:subtree:depth:{$maxDepth}";
        
        return Cache::remember($cacheKey, 1800, function () use ($taxonomyId, $maxDepth) {
            return $this->buildSubtree($taxonomyId, $maxDepth);
        });
    }
    
    private function getSubtreeNodeCount(int $taxonomyId, int $maxDepth): int
    {
        $cacheKey = "taxonomy:{$taxonomyId}:subtree:count:depth:{$maxDepth}";
        
        return Cache::remember($cacheKey, 3600, function () use ($taxonomyId, $maxDepth) {
            return DB::table('taxonomy_closure')
                    ->where('ancestor_id', $taxonomyId)
                    ->where('depth', '<=', $maxDepth)
                    ->count();
        });
    }
    
    private function buildSubtree(int $taxonomyId, int $maxDepth): array
    {
        $query = Taxonomy::whereHas('ancestors', function ($query) use ($taxonomyId) {
            $query->where('ancestor_id', $taxonomyId);
        });
        
        if ($maxDepth > 0) {
            $query->whereHas('ancestors', function ($query) use ($taxonomyId, $maxDepth) {
                $query->where('ancestor_id', $taxonomyId)
                      ->where('depth', '<=', $maxDepth);
            });
        }
        
        return $query->with(['children' => function ($query) {
            $query->orderBy('sort_order');
        }])->get()->toArray();
    }
    
    private function getLargeSubtree(int $taxonomyId, int $maxDepth): array
    {
        // For large subtrees, implement pagination or lazy loading
        return [
            'id' => $taxonomyId,
            'children' => 'lazy_load', // Placeholder for lazy loading
            'total_descendants' => $this->getSubtreeNodeCount($taxonomyId, $maxDepth),
            'max_depth' => $maxDepth
        ];
    }
}
```

## 4. Query Result Caching

### 4.1 Relationship Query Caching

```php
<?php

// Cache taxonomy relationships
class TaxonomyRelationshipCache
{
    public function getCachedAncestors(int $taxonomyId): Collection
    {
        $cacheKey = CacheKeyGenerator::taxonomyAncestors($taxonomyId);
        
        return Cache::remember($cacheKey, 3600, function () use ($taxonomyId) {
            $taxonomy = Taxonomy::find($taxonomyId);
            return $taxonomy ? $taxonomy->ancestors()->orderBy('depth')->get() : collect();
        });
    }
    
    public function getCachedDescendants(int $taxonomyId, array $options = []): Collection
    {
        $cacheKey = CacheKeyGenerator::taxonomyDescendants($taxonomyId, $options);
        
        return Cache::remember($cacheKey, 1800, function () use ($taxonomyId, $options) {
            $taxonomy = Taxonomy::find($taxonomyId);
            if (!$taxonomy) {
                return collect();
            }
            
            $query = $taxonomy->descendants();
            
            if (isset($options['depth'])) {
                $query->wherePivot('depth', '<=', $options['depth']);
            }
            
            if (isset($options['type'])) {
                $query->where('type', $options['type']);
            }
            
            return $query->get();
        });
    }
    
    public function getCachedSiblings(int $taxonomyId): Collection
    {
        $cacheKey = "taxonomy:{$taxonomyId}:siblings";
        
        return Cache::remember($cacheKey, 3600, function () use ($taxonomyId) {
            $taxonomy = Taxonomy::find($taxonomyId);
            if (!$taxonomy || !$taxonomy->parent_id) {
                return collect();
            }
            
            return Taxonomy::where('parent_id', $taxonomy->parent_id)
                          ->where('id', '!=', $taxonomyId)
                          ->orderBy('sort_order')
                          ->get();
        });
    }
}
```

## 5. Cache Invalidation Strategies

### 5.1 Event-Driven Invalidation

```php
<?php

// Taxonomy cache invalidation observer
class TaxonomyCacheObserver
{
    public function __construct(
        private HierarchicalCacheService $cacheService
    ) {}
    
    public function created(Taxonomy $taxonomy): void
    {
        $this->invalidateHierarchyCache($taxonomy);
        $this->invalidateParentCache($taxonomy);
    }
    
    public function updated(Taxonomy $taxonomy): void
    {
        $this->invalidateHierarchyCache($taxonomy);
        $this->invalidateParentCache($taxonomy);
        
        // If parent changed, invalidate old parent cache too
        if ($taxonomy->isDirty('parent_id')) {
            $oldParentId = $taxonomy->getOriginal('parent_id');
            if ($oldParentId) {
                $this->invalidateParentCache(Taxonomy::find($oldParentId));
            }
        }
    }
    
    public function deleted(Taxonomy $taxonomy): void
    {
        $this->invalidateHierarchyCache($taxonomy);
        $this->invalidateParentCache($taxonomy);
        $this->invalidateDescendantCache($taxonomy);
    }
    
    private function invalidateHierarchyCache(Taxonomy $taxonomy): void
    {
        // Invalidate taxonomy tree caches
        Cache::forget("taxonomy:tree:{$taxonomy->id}");
        Cache::forget("taxonomy:tree:all");
        
        // Invalidate ancestor/descendant caches
        Cache::forget("taxonomy:{$taxonomy->id}:ancestors");
        Cache::forget("taxonomy:{$taxonomy->id}:descendants:all");
        
        // Invalidate path cache
        Cache::forget("taxonomy:{$taxonomy->id}:path");
    }
    
    private function invalidateParentCache(?Taxonomy $taxonomy): void
    {
        if (!$taxonomy || !$taxonomy->parent_id) {
            return;
        }
        
        $parent = $taxonomy->parent;
        if ($parent) {
            Cache::forget("taxonomy:{$parent->id}:descendants:all");
            Cache::forget("taxonomy:tree:{$parent->id}");
            
            // Recursively invalidate ancestor caches
            $this->invalidateParentCache($parent);
        }
    }
    
    private function invalidateDescendantCache(Taxonomy $taxonomy): void
    {
        $descendants = $taxonomy->descendants()->pluck('id');
        
        foreach ($descendants as $descendantId) {
            Cache::forget("taxonomy:{$descendantId}:ancestors");
            Cache::forget("taxonomy:{$descendantId}:path");
        }
    }
}
```

## 6. Performance Optimization

### 6.1 Memory-Optimized Caching

```php
<?php

// Memory-optimized caching for large hierarchies
class MemoryOptimizedCache
{
    private const MAX_MEMORY_USAGE = 64 * 1024 * 1024; // 64MB

    public function getCachedHierarchy(int $taxonomyId): array
    {
        $memoryBefore = memory_get_usage();

        $hierarchy = $this->loadHierarchy($taxonomyId);

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        if ($memoryUsed > self::MAX_MEMORY_USAGE) {
            // Use compressed caching for large hierarchies
            return $this->getCompressedHierarchy($taxonomyId);
        }

        return $hierarchy;
    }

    private function getCompressedHierarchy(int $taxonomyId): array
    {
        $cacheKey = "taxonomy:{$taxonomyId}:hierarchy:compressed";

        return Cache::remember($cacheKey, 3600, function () use ($taxonomyId) {
            $hierarchy = $this->loadHierarchy($taxonomyId);

            // Compress hierarchy data
            $compressed = gzcompress(serialize($hierarchy), 9);

            return [
                'compressed' => true,
                'data' => base64_encode($compressed),
                'original_size' => strlen(serialize($hierarchy)),
                'compressed_size' => strlen($compressed)
            ];
        });
    }

    private function loadHierarchy(int $taxonomyId): array
    {
        return Taxonomy::find($taxonomyId)
                      ->descendants()
                      ->with('children')
                      ->get()
                      ->toArray();
    }
}
```

## 7. Monitoring and Metrics

### 7.1 Cache Performance Monitoring

```php
<?php

// Cache performance monitoring
class CacheMonitor
{
    public function getCacheMetrics(): array
    {
        return [
            'hit_ratio' => $this->calculateHitRatio(),
            'memory_usage' => $this->getMemoryUsage(),
            'key_count' => $this->getKeyCount(),
            'eviction_rate' => $this->getEvictionRate()
        ];
    }
    
    private function calculateHitRatio(): float
    {
        $hits = Cache::get('cache_hits', 0);
        $misses = Cache::get('cache_misses', 0);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
    
    public function trackCacheOperation(string $operation, string $key): void
    {
        $metric = "cache_{$operation}s";
        Cache::increment($metric);
        
        Log::debug('Cache Operation', [
            'operation' => $operation,
            'key' => $key,
            'timestamp' => now()
        ]);
    }
}
```

## 8. Best Practices

### 8.1 Caching Guidelines

- **Cache frequently accessed hierarchies** - 90% performance improvement
- **Use appropriate TTL values** - Balance freshness with performance
- **Implement intelligent invalidation** - Maintain data consistency
- **Monitor cache hit ratios** - Target 90%+ for optimal performance
- **Use compressed caching for large datasets** - Reduce memory usage

### 8.2 Performance Targets

- **Cache hit ratio**: > 90%
- **Hierarchy query response**: < 50ms (cached)
- **Memory usage**: < 512MB for typical operations
- **Cache invalidation time**: < 10ms

## 9. Navigation

**Previous ←** [Single Taxonomy System Optimization](100-single-taxonomy-optimization.md)  
**Next →** [Performance Index](000-performance-index.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/performance/110-hierarchical-data-caching.md on 2025-07-11

*This guide provides advanced caching strategies for hierarchical taxonomy data using aliziodev/laravel-taxonomy with Laravel 12 modern patterns.*

[⬆️ Back to Top](#1-hierarchical-data-caching)
