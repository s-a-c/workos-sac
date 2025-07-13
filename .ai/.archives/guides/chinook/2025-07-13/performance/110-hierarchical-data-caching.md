# Hierarchical Data Caching Strategies

## Table of Contents

- [Overview](#overview)
- [Caching Architecture](#caching-architecture)
- [Category Hierarchy Caching](#category-hierarchy-caching)
- [Query Result Caching](#query-result-caching)
- [Cache Invalidation Strategies](#cache-invalidation-strategies)
- [Performance Optimization](#performance-optimization)
- [Monitoring and Metrics](#monitoring-and-metrics)

## Overview

This guide provides comprehensive caching strategies for hierarchical data in the Chinook database implementation. The hybrid hierarchical architecture (closure table + adjacency list) requires sophisticated caching to achieve optimal performance.

**Caching Goals**:
- Sub-50ms response times for cached hierarchy queries
- 90%+ cache hit ratio for frequently accessed hierarchies
- Intelligent cache invalidation to maintain data consistency
- Memory-efficient caching for large category trees

**Key Caching Areas**:
- Category hierarchy trees and subtrees
- Ancestor/descendant relationship queries
- Category path calculations
- Aggregated category statistics

## Caching Architecture

### Multi-Layer Caching Strategy

```php
// Hierarchical caching service
class HierarchicalCacheService
{
    private const CACHE_TTL = [
        'hierarchy_tree' => 7200,      // 2 hours
        'category_path' => 3600,       // 1 hour
        'descendant_ids' => 1800,      // 30 minutes
        'category_stats' => 900,       // 15 minutes
    ];
    
    public function __construct(
        private CacheManager $cache,
        private CategoryRepository $categoryRepository
    ) {}
    
    // Cache entire category tree
    public function getCategoryTree(int $rootId = null): Collection
    {
        $cacheKey = $rootId ? "category:tree:{$rootId}" : "category:tree:all";
        
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL['hierarchy_tree'],
            fn() => $this->buildCategoryTree($rootId)
        );
    }
    
    // Cache category descendants with depth information
    public function getDescendantIds(int $categoryId, int $maxDepth = null): array
    {
        $cacheKey = $maxDepth 
            ? "category:{$categoryId}:descendants:depth:{$maxDepth}"
            : "category:{$categoryId}:descendants:all";
            
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL['descendant_ids'],
            fn() => $this->categoryRepository->getDescendantIds($categoryId, $maxDepth)
        );
    }
    
    // Cache category path from root
    public function getCategoryPath(int $categoryId): array
    {
        $cacheKey = "category:{$categoryId}:path";
        
        return $this->cache->remember(
            $cacheKey,
            self::CACHE_TTL['category_path'],
            fn() => $this->categoryRepository->getCategoryPath($categoryId)
        );
    }
    
    private function buildCategoryTree(int $rootId = null): Collection
    {
        if ($rootId) {
            $root = Category::find($rootId);
            return $root ? $root->descendants()->with('children')->get() : collect();
        }
        
        return Category::whereNull('parent_id')
                      ->with('descendants')
                      ->get();
    }
}
```

### Cache Key Strategy

```php
// Standardized cache key generation
class CacheKeyGenerator
{
    private const PREFIX = 'chinook';
    private const VERSION = 'v1';
    
    public static function categoryTree(int $rootId = null, array $filters = []): string
    {
        $base = self::PREFIX . ':' . self::VERSION . ':category:tree';
        
        if ($rootId) {
            $base .= ":{$rootId}";
        }
        
        if (!empty($filters)) {
            $base .= ':' . md5(serialize($filters));
        }
        
        return $base;
    }
    
    public static function categoryDescendants(int $categoryId, array $options = []): string
    {
        $base = self::PREFIX . ':' . self::VERSION . ":category:{$categoryId}:descendants";
        
        if (isset($options['depth'])) {
            $base .= ":depth:{$options['depth']}";
        }
        
        if (isset($options['type'])) {
            $base .= ":type:{$options['type']->value}";
        }
        
        return $base;
    }
    
    public static function categoryAncestors(int $categoryId): string
    {
        return self::PREFIX . ':' . self::VERSION . ":category:{$categoryId}:ancestors";
    }
    
    public static function categoryStats(int $categoryId, string $statType): string
    {
        return self::PREFIX . ':' . self::VERSION . ":category:{$categoryId}:stats:{$statType}";
    }
}
```

## Category Hierarchy Caching

### Tree Structure Caching

```php
// Optimized category tree caching
class CategoryTreeCache
{
    public function getOptimizedTree(int $rootId, array $options = []): array
    {
        $cacheKey = CacheKeyGenerator::categoryTree($rootId, $options);
        
        return Cache::remember($cacheKey, 3600, function () use ($rootId, $options) {
            return $this->buildOptimizedTree($rootId, $options);
        });
    }
    
    private function buildOptimizedTree(int $rootId, array $options): array
    {
        // Use closure table for efficient hierarchy queries
        $descendants = DB::table('category_closure as cc')
            ->join('categories as c', 'cc.descendant_id', '=', 'c.id')
            ->where('cc.ancestor_id', $rootId)
            ->select([
                'c.id',
                'c.name',
                'c.parent_id',
                'c.type',
                'cc.depth',
                'c.sort_order'
            ])
            ->orderBy('cc.depth')
            ->orderBy('c.sort_order')
            ->get();
        
        return $this->buildTreeFromFlat($descendants->toArray());
    }
    
    private function buildTreeFromFlat(array $flatData): array
    {
        $tree = [];
        $lookup = [];
        
        // First pass: create lookup table
        foreach ($flatData as $item) {
            $lookup[$item->id] = [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'depth' => $item->depth,
                'children' => []
            ];
        }
        
        // Second pass: build tree structure
        foreach ($flatData as $item) {
            if ($item->parent_id && isset($lookup[$item->parent_id])) {
                $lookup[$item->parent_id]['children'][] = &$lookup[$item->id];
            } else {
                $tree[] = &$lookup[$item->id];
            }
        }
        
        return $tree;
    }
}
```

### Subtree Caching

```php
// Intelligent subtree caching
class SubtreeCacheManager
{
    private const MAX_SUBTREE_SIZE = 1000; // Maximum nodes to cache as subtree
    
    public function getSubtree(int $categoryId, int $maxDepth = 3): array
    {
        // Check if subtree is small enough to cache
        $nodeCount = $this->getSubtreeNodeCount($categoryId, $maxDepth);
        
        if ($nodeCount > self::MAX_SUBTREE_SIZE) {
            // For large subtrees, use pagination or lazy loading
            return $this->getLargeSubtree($categoryId, $maxDepth);
        }
        
        $cacheKey = "category:{$categoryId}:subtree:depth:{$maxDepth}";
        
        return Cache::remember($cacheKey, 1800, function () use ($categoryId, $maxDepth) {
            return $this->buildSubtree($categoryId, $maxDepth);
        });
    }
    
    private function getSubtreeNodeCount(int $categoryId, int $maxDepth): int
    {
        $cacheKey = "category:{$categoryId}:subtree:count:depth:{$maxDepth}";
        
        return Cache::remember($cacheKey, 3600, function () use ($categoryId, $maxDepth) {
            return DB::table('category_closure')
                    ->where('ancestor_id', $categoryId)
                    ->where('depth', '<=', $maxDepth)
                    ->count();
        });
    }
    
    private function buildSubtree(int $categoryId, int $maxDepth): array
    {
        $query = Category::whereHas('ancestors', function ($query) use ($categoryId) {
            $query->where('ancestor_id', $categoryId);
        });
        
        if ($maxDepth > 0) {
            $query->whereHas('ancestors', function ($query) use ($categoryId, $maxDepth) {
                $query->where('ancestor_id', $categoryId)
                      ->where('depth', '<=', $maxDepth);
            });
        }
        
        return $query->with(['children' => function ($query) {
            $query->orderBy('sort_order');
        }])->get()->toArray();
    }
    
    private function getLargeSubtree(int $categoryId, int $maxDepth): array
    {
        // For large subtrees, implement pagination or lazy loading
        return [
            'id' => $categoryId,
            'children' => 'lazy_load', // Placeholder for lazy loading
            'total_descendants' => $this->getSubtreeNodeCount($categoryId, $maxDepth),
            'max_depth' => $maxDepth
        ];
    }
}
```

## Query Result Caching

### Relationship Query Caching

```php
// Cache category relationships
class CategoryRelationshipCache
{
    public function getCachedAncestors(int $categoryId): Collection
    {
        $cacheKey = CacheKeyGenerator::categoryAncestors($categoryId);
        
        return Cache::remember($cacheKey, 3600, function () use ($categoryId) {
            $category = Category::find($categoryId);
            return $category ? $category->ancestors()->orderBy('depth')->get() : collect();
        });
    }
    
    public function getCachedDescendants(int $categoryId, array $options = []): Collection
    {
        $cacheKey = CacheKeyGenerator::categoryDescendants($categoryId, $options);
        
        return Cache::remember($cacheKey, 1800, function () use ($categoryId, $options) {
            $category = Category::find($categoryId);
            if (!$category) {
                return collect();
            }
            
            $query = $category->descendants();
            
            if (isset($options['depth'])) {
                $query->wherePivot('depth', '<=', $options['depth']);
            }
            
            if (isset($options['type'])) {
                $query->where('type', $options['type']);
            }
            
            return $query->get();
        });
    }
    
    public function getCachedSiblings(int $categoryId): Collection
    {
        $cacheKey = "category:{$categoryId}:siblings";
        
        return Cache::remember($cacheKey, 3600, function () use ($categoryId) {
            $category = Category::find($categoryId);
            if (!$category || !$category->parent_id) {
                return collect();
            }
            
            return Category::where('parent_id', $category->parent_id)
                          ->where('id', '!=', $categoryId)
                          ->orderBy('sort_order')
                          ->get();
        });
    }
}
```

### Aggregation Caching

```php
// Cache category statistics and aggregations
class CategoryStatsCache
{
    public function getCachedTrackCount(int $categoryId, bool $includeDescendants = false): int
    {
        $suffix = $includeDescendants ? 'with_descendants' : 'direct';
        $cacheKey = CacheKeyGenerator::categoryStats($categoryId, "track_count_{$suffix}");
        
        return Cache::remember($cacheKey, 900, function () use ($categoryId, $includeDescendants) {
            if ($includeDescendants) {
                return $this->getDescendantTrackCount($categoryId);
            }
            
            return DB::table('categorizables')
                    ->where('category_id', $categoryId)
                    ->where('categorizable_type', Track::class)
                    ->count();
        });
    }
    
    public function getCachedCategoryDepth(int $categoryId): int
    {
        $cacheKey = CacheKeyGenerator::categoryStats($categoryId, 'depth');
        
        return Cache::remember($cacheKey, 7200, function () use ($categoryId) {
            $maxDepth = DB::table('category_closure')
                         ->where('descendant_id', $categoryId)
                         ->max('depth');
                         
            return $maxDepth ?? 0;
        });
    }
    
    private function getDescendantTrackCount(int $categoryId): int
    {
        $descendantIds = DB::table('category_closure')
                          ->where('ancestor_id', $categoryId)
                          ->pluck('descendant_id');
        
        return DB::table('categorizables')
                ->whereIn('category_id', $descendantIds)
                ->where('categorizable_type', Track::class)
                ->count();
    }
}
```

## Cache Invalidation Strategies

### Event-Driven Invalidation

```php
// Category cache invalidation observer
class CategoryCacheObserver
{
    public function __construct(
        private HierarchicalCacheService $cacheService
    ) {}
    
    public function created(Category $category): void
    {
        $this->invalidateHierarchyCache($category);
        $this->invalidateParentCache($category);
    }
    
    public function updated(Category $category): void
    {
        $this->invalidateHierarchyCache($category);
        $this->invalidateParentCache($category);
        
        // If parent changed, invalidate old parent cache too
        if ($category->isDirty('parent_id')) {
            $oldParentId = $category->getOriginal('parent_id');
            if ($oldParentId) {
                $this->invalidateParentCache(Category::find($oldParentId));
            }
        }
    }
    
    public function deleted(Category $category): void
    {
        $this->invalidateHierarchyCache($category);
        $this->invalidateParentCache($category);
        $this->invalidateDescendantCache($category);
    }
    
    private function invalidateHierarchyCache(Category $category): void
    {
        // Invalidate category tree caches
        Cache::forget("category:tree:{$category->id}");
        Cache::forget("category:tree:all");
        
        // Invalidate ancestor/descendant caches
        Cache::forget("category:{$category->id}:ancestors");
        Cache::forget("category:{$category->id}:descendants:all");
        
        // Invalidate path cache
        Cache::forget("category:{$category->id}:path");
    }
    
    private function invalidateParentCache(?Category $category): void
    {
        if (!$category || !$category->parent_id) {
            return;
        }
        
        $parent = $category->parent;
        if ($parent) {
            Cache::forget("category:{$parent->id}:descendants:all");
            Cache::forget("category:tree:{$parent->id}");
            
            // Recursively invalidate ancestor caches
            $this->invalidateParentCache($parent);
        }
    }
    
    private function invalidateDescendantCache(Category $category): void
    {
        $descendants = $category->descendants()->pluck('id');
        
        foreach ($descendants as $descendantId) {
            Cache::forget("category:{$descendantId}:ancestors");
            Cache::forget("category:{$descendantId}:path");
        }
    }
}
```

### Smart Cache Warming

```php
// Proactive cache warming for frequently accessed hierarchies
class CacheWarmingService
{
    public function warmFrequentlyAccessedHierarchies(): void
    {
        $frequentCategories = $this->getFrequentlyAccessedCategories();

        foreach ($frequentCategories as $categoryId) {
            $this->warmCategoryHierarchy($categoryId);
        }
    }

    private function getFrequentlyAccessedCategories(): array
    {
        // Get categories accessed in the last 24 hours
        return DB::table('category_access_log')
                ->where('accessed_at', '>=', now()->subDay())
                ->groupBy('category_id')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(50)
                ->pluck('category_id')
                ->toArray();
    }

    private function warmCategoryHierarchy(int $categoryId): void
    {
        // Warm tree cache
        $this->cacheService->getCategoryTree($categoryId);

        // Warm descendant cache
        $this->cacheService->getDescendantIds($categoryId);

        // Warm ancestor cache
        $this->cacheService->getCategoryPath($categoryId);

        // Warm stats cache
        $this->statsCache->getCachedTrackCount($categoryId, true);
    }
}
```

## Performance Optimization

### Memory-Efficient Caching

```php
// Memory-optimized caching for large hierarchies
class MemoryOptimizedCache
{
    private const MAX_MEMORY_USAGE = 64 * 1024 * 1024; // 64MB

    public function getCachedHierarchy(int $categoryId): array
    {
        $memoryBefore = memory_get_usage();

        $hierarchy = $this->loadHierarchy($categoryId);

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        if ($memoryUsed > self::MAX_MEMORY_USAGE) {
            // Use compressed caching for large hierarchies
            return $this->getCompressedHierarchy($categoryId);
        }

        return $hierarchy;
    }

    private function getCompressedHierarchy(int $categoryId): array
    {
        $cacheKey = "category:{$categoryId}:hierarchy:compressed";

        return Cache::remember($cacheKey, 3600, function () use ($categoryId) {
            $hierarchy = $this->loadHierarchy($categoryId);

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

    public function decompressHierarchy(array $compressedData): array
    {
        if (!$compressedData['compressed']) {
            return $compressedData;
        }

        $compressed = base64_decode($compressedData['data']);
        $decompressed = gzuncompress($compressed);

        return unserialize($decompressed);
    }
}
```

### Cache Performance Monitoring

```php
// Monitor cache performance and hit rates
class CachePerformanceMonitor
{
    public function recordCacheHit(string $cacheKey, float $retrievalTime): void
    {
        $this->recordMetric('cache_hit', $cacheKey, $retrievalTime);
    }

    public function recordCacheMiss(string $cacheKey, float $generationTime): void
    {
        $this->recordMetric('cache_miss', $cacheKey, $generationTime);
    }

    public function getCacheStatistics(string $period = '24h'): array
    {
        $stats = DB::table('cache_metrics')
                  ->where('created_at', '>=', $this->getPeriodStart($period))
                  ->selectRaw('
                      cache_key_pattern,
                      COUNT(*) as total_requests,
                      SUM(CASE WHEN metric_type = "cache_hit" THEN 1 ELSE 0 END) as hits,
                      SUM(CASE WHEN metric_type = "cache_miss" THEN 1 ELSE 0 END) as misses,
                      AVG(CASE WHEN metric_type = "cache_hit" THEN value ELSE NULL END) as avg_hit_time,
                      AVG(CASE WHEN metric_type = "cache_miss" THEN value ELSE NULL END) as avg_miss_time
                  ')
                  ->groupBy('cache_key_pattern')
                  ->get();

        return $stats->map(function ($stat) {
            $hitRate = $stat->total_requests > 0
                ? ($stat->hits / $stat->total_requests) * 100
                : 0;

            return [
                'pattern' => $stat->cache_key_pattern,
                'hit_rate' => round($hitRate, 2),
                'total_requests' => $stat->total_requests,
                'avg_hit_time' => round($stat->avg_hit_time, 2),
                'avg_miss_time' => round($stat->avg_miss_time, 2),
                'performance_score' => $this->calculatePerformanceScore($stat)
            ];
        })->toArray();
    }

    private function recordMetric(string $type, string $cacheKey, float $value): void
    {
        DB::table('cache_metrics')->insert([
            'metric_type' => $type,
            'cache_key' => $cacheKey,
            'cache_key_pattern' => $this->extractKeyPattern($cacheKey),
            'value' => $value,
            'created_at' => now()
        ]);
    }

    private function extractKeyPattern(string $cacheKey): string
    {
        // Extract pattern by replacing IDs with placeholders
        return preg_replace('/:\d+/', ':*', $cacheKey);
    }

    private function calculatePerformanceScore(object $stat): float
    {
        $hitRate = $stat->total_requests > 0
            ? ($stat->hits / $stat->total_requests) * 100
            : 0;

        $speedScore = $stat->avg_hit_time > 0
            ? min(100, (10 / $stat->avg_hit_time) * 100)
            : 0;

        return round(($hitRate * 0.7) + ($speedScore * 0.3), 2);
    }
}
```

## Monitoring and Metrics

### Cache Health Dashboard

```php
// Real-time cache health monitoring
class CacheHealthMonitor
{
    public function getHealthMetrics(): array
    {
        return [
            'hit_rates' => $this->getHitRates(),
            'memory_usage' => $this->getMemoryUsage(),
            'response_times' => $this->getResponseTimes(),
            'invalidation_frequency' => $this->getInvalidationFrequency(),
            'recommendations' => $this->getOptimizationRecommendations()
        ];
    }

    private function getHitRates(): array
    {
        $monitor = new CachePerformanceMonitor();
        $stats = $monitor->getCacheStatistics('24h');

        return [
            'overall_hit_rate' => $this->calculateOverallHitRate($stats),
            'by_pattern' => array_column($stats, 'hit_rate', 'pattern'),
            'target_hit_rate' => 85.0,
            'status' => $this->getHitRateStatus($stats)
        ];
    }

    private function getMemoryUsage(): array
    {
        $redis = Cache::getRedis();
        $info = $redis->info('memory');

        return [
            'used_memory' => $info['used_memory'],
            'used_memory_human' => $info['used_memory_human'],
            'used_memory_peak' => $info['used_memory_peak'],
            'memory_fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 1.0,
            'status' => $this->getMemoryStatus($info)
        ];
    }

    private function getResponseTimes(): array
    {
        $avgTimes = DB::table('cache_metrics')
                     ->where('created_at', '>=', now()->subHours(24))
                     ->selectRaw('
                         cache_key_pattern,
                         AVG(CASE WHEN metric_type = "cache_hit" THEN value END) as avg_hit_time,
                         AVG(CASE WHEN metric_type = "cache_miss" THEN value END) as avg_miss_time
                     ')
                     ->groupBy('cache_key_pattern')
                     ->get();

        return [
            'average_hit_time' => $avgTimes->avg('avg_hit_time'),
            'average_miss_time' => $avgTimes->avg('avg_miss_time'),
            'by_pattern' => $avgTimes->toArray(),
            'target_hit_time' => 10.0, // 10ms target
            'status' => $this->getResponseTimeStatus($avgTimes)
        ];
    }

    private function getOptimizationRecommendations(): array
    {
        $recommendations = [];

        $hitRates = $this->getHitRates();
        if ($hitRates['overall_hit_rate'] < 80) {
            $recommendations[] = [
                'type' => 'hit_rate',
                'priority' => 'high',
                'message' => 'Cache hit rate is below 80%. Consider increasing TTL or warming frequently accessed data.'
            ];
        }

        $responseTimes = $this->getResponseTimes();
        if ($responseTimes['average_hit_time'] > 20) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'medium',
                'message' => 'Cache retrieval times are high. Consider optimizing cache storage or using compression.'
            ];
        }

        return $recommendations;
    }
}
```
