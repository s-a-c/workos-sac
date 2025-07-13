# Performance Optimization Guide

This guide covers comprehensive performance optimization strategies for the Chinook admin panel in production, including SQLite optimization, caching strategies, and application-level performance tuning.

## Table of Contents

- [Overview](#overview)
- [SQLite Performance Optimization](#sqlite-performance-optimization)
- [Application Caching](#application-caching)
- [PHP Performance Tuning](#php-performance-tuning)
- [Frontend Optimization](#frontend-optimization)
- [Query Optimization](#query-optimization)
- [Monitoring and Profiling](#monitoring-and-profiling)

## Overview

The Chinook admin panel is optimized for high performance with SQLite as the primary database. This guide covers all aspects of performance optimization to ensure sub-second response times and efficient resource utilization.

### Performance Goals

- **Page Load Time**: < 500ms for dashboard and resource pages
- **Database Queries**: < 50ms average query time
- **Memory Usage**: < 256MB per request
- **Concurrent Users**: Support 100+ concurrent admin users
- **Uptime**: 99.9% availability with proper optimization

## SQLite Performance Optimization

### WAL Mode Configuration

```sql
-- Enable Write-Ahead Logging for optimal performance
PRAGMA journal_mode = WAL;

-- Optimize synchronization for performance vs durability balance
PRAGMA synchronous = NORMAL;

-- Set large cache size (64MB)
PRAGMA cache_size = -64000;

-- Store temporary tables in memory
PRAGMA temp_store = MEMORY;

-- Enable memory-mapped I/O (256MB)
PRAGMA mmap_size = 268435456;

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Set busy timeout for concurrent access
PRAGMA busy_timeout = 30000;

-- Configure automatic checkpointing
PRAGMA wal_autocheckpoint = 1000;

-- Optimize query planner
PRAGMA optimize;
```

### Database Maintenance Scripts

```bash
#!/bin/bash
# /usr/local/bin/sqlite-optimize.sh

DB_PATH="/var/www/chinook-admin/database/production.sqlite"

# Function to optimize database
optimize_database() {
    echo "Starting SQLite optimization..."
    
    # Checkpoint WAL file
    sqlite3 "$DB_PATH" "PRAGMA wal_checkpoint(FULL);"
    echo "✓ WAL checkpoint completed"
    
    # Update table statistics
    sqlite3 "$DB_PATH" "ANALYZE;"
    echo "✓ Table statistics updated"
    
    # Optimize query planner
    sqlite3 "$DB_PATH" "PRAGMA optimize;"
    echo "✓ Query planner optimized"
    
    # Vacuum if needed (only if fragmentation > 25%)
    FRAGMENTATION=$(sqlite3 "$DB_PATH" "PRAGMA freelist_count;" | head -1)
    PAGE_COUNT=$(sqlite3 "$DB_PATH" "PRAGMA page_count;" | head -1)
    
    if [ "$PAGE_COUNT" -gt 0 ]; then
        FRAG_PERCENT=$((FRAGMENTATION * 100 / PAGE_COUNT))
        if [ "$FRAG_PERCENT" -gt 25 ]; then
            echo "Database fragmentation: ${FRAG_PERCENT}%, running VACUUM..."
            sqlite3 "$DB_PATH" "VACUUM;"
            echo "✓ Database vacuumed"
        else
            echo "Database fragmentation: ${FRAG_PERCENT}%, VACUUM not needed"
        fi
    fi
    
    echo "SQLite optimization completed successfully"
}

# Run optimization
optimize_database

# Schedule this script to run daily via cron:
# 0 2 * * * /usr/local/bin/sqlite-optimize.sh >> /var/log/sqlite-optimize.log 2>&1
```

### Index Optimization

```sql
-- Create optimized indexes for common queries

-- Artists table indexes
CREATE INDEX IF NOT EXISTS idx_artists_name ON artists(name);
CREATE INDEX IF NOT EXISTS idx_artists_country ON artists(country);
CREATE INDEX IF NOT EXISTS idx_artists_active ON artists(is_active);
CREATE INDEX IF NOT EXISTS idx_artists_created ON artists(created_at);
CREATE INDEX IF NOT EXISTS idx_artists_public_id ON artists(public_id);

-- Albums table indexes
CREATE INDEX IF NOT EXISTS idx_albums_title ON albums(title);
CREATE INDEX IF NOT EXISTS idx_albums_artist ON albums(artist_id);
CREATE INDEX IF NOT EXISTS idx_albums_year ON albums(release_year);
CREATE INDEX IF NOT EXISTS idx_albums_active ON albums(is_active);

-- Tracks table indexes
CREATE INDEX IF NOT EXISTS idx_tracks_name ON tracks(name);
CREATE INDEX IF NOT EXISTS idx_tracks_album ON tracks(album_id);
CREATE INDEX IF NOT EXISTS idx_tracks_composer ON tracks(composer);
CREATE INDEX IF NOT EXISTS idx_tracks_price ON tracks(unit_price);

-- Composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_albums_artist_year ON albums(artist_id, release_year);
CREATE INDEX IF NOT EXISTS idx_tracks_album_number ON tracks(album_id, track_number);

-- Full-text search indexes (FTS5)
CREATE VIRTUAL TABLE IF NOT EXISTS artists_fts USING fts5(
    name, biography, country,
    content='artists',
    content_rowid='id'
);

CREATE VIRTUAL TABLE IF NOT EXISTS tracks_fts USING fts5(
    name, composer,
    content='tracks',
    content_rowid='id'
);
```

## Application Caching

### Redis Configuration

```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

'prefix' => env('CACHE_PREFIX', 'chinook_admin'),

// config/database.php - Redis connections
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'chinook_admin:'),
    ],
    
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_write_timeout' => 60,
        'context' => [
            'tcp' => [
                'tcp_keepalive' => 1,
            ],
        ],
    ],
    
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
    
    'session' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '2'),
    ],
],
```

### Model Caching Strategy

```php
<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

class Artist extends Model
{
    /**
     * Cache popular artists for dashboard.
     */
    public static function getPopularArtistsCached(int $limit = 10): Collection
    {
        return Cache::remember(
            'artists.popular.' . $limit,
            3600, // 1 hour
            fn() => static::select([
                'artists.*',
                \DB::raw('COUNT(DISTINCT albums.id) as albums_count'),
                \DB::raw('COUNT(DISTINCT tracks.id) as tracks_count'),
                \DB::raw('COALESCE(SUM(invoice_lines.quantity), 0) as total_sales')
            ])
            ->leftJoin('albums', 'artists.id', '=', 'albums.artist_id')
            ->leftJoin('tracks', 'albums.id', '=', 'tracks.album_id')
            ->leftJoin('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
            ->where('artists.is_active', true)
            ->groupBy('artists.id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get()
        );
    }

    /**
     * Cache artist statistics.
     */
    public function getCachedStatistics(): array
    {
        return Cache::remember(
            "artist.{$this->id}.statistics",
            1800, // 30 minutes
            fn() => [
                'albums_count' => $this->albums()->count(),
                'tracks_count' => $this->tracks()->count(),
                'total_sales' => $this->tracks()
                    ->join('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
                    ->sum('invoice_lines.quantity'),
                'total_revenue' => $this->tracks()
                    ->join('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
                    ->sum(\DB::raw('invoice_lines.quantity * invoice_lines.unit_price')),
            ]
        );
    }

    /**
     * Clear cache when model is updated.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function ($artist) {
            Cache::forget("artist.{$artist->id}.statistics");
            Cache::tags(['artists'])->flush();
        });

        static::deleted(function ($artist) {
            Cache::forget("artist.{$artist->id}.statistics");
            Cache::tags(['artists'])->flush();
        });
    }
}
```

### Widget Caching

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use Illuminate\Support\Facades\Cache;

class RevenueOverviewWidget extends Widget
{
    protected static ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        return Cache::remember(
            'widget.revenue.overview.' . auth()->id(),
            300, // 5 minutes
            function () {
                $totalRevenue = Invoice::sum('total');
                $monthlyRevenue = Invoice::whereMonth('created_at', now()->month)->sum('total');
                $previousMonthRevenue = Invoice::whereMonth('created_at', now()->subMonth()->month)->sum('total');
                
                $growthRate = $previousMonthRevenue > 0 
                    ? (($monthlyRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100 
                    : 0;

                return [
                    'total_revenue' => $totalRevenue,
                    'monthly_revenue' => $monthlyRevenue,
                    'growth_rate' => $growthRate,
                    'chart_data' => $this->getChartData(),
                ];
            }
        );
    }

    private function getChartData(): array
    {
        return Cache::remember(
            'widget.revenue.chart.' . auth()->id(),
            600, // 10 minutes
            fn() => Invoice::selectRaw('DATE(created_at) as date, SUM(total) as revenue')
                ->whereMonth('created_at', now()->month)
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('revenue', 'date')
                ->toArray()
        );
    }
}
```

## PHP Performance Tuning

### OPcache Configuration

```ini
; /etc/php/8.4/fpm/conf.d/10-opcache.ini

; Enable OPcache
opcache.enable=1
opcache.enable_cli=1

; Memory settings
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000

; Performance settings
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=0
opcache.fast_shutdown=1

; Advanced settings
opcache.huge_code_pages=1
opcache.file_cache=/tmp/opcache
opcache.file_cache_only=0
opcache.file_cache_consistency_checks=1

; JIT compilation (PHP 8.0+)
opcache.jit_buffer_size=128M
opcache.jit=tracing
```

### APCu Configuration

```ini
; /etc/php/8.4/fpm/conf.d/20-apcu.ini

; Enable APCu
apc.enabled=1
apc.enable_cli=1

; Memory settings
apc.shm_size=128M
apc.ttl=7200
apc.gc_ttl=3600

; Performance settings
apc.mmap_file_mask=/tmp/apc.XXXXXX
apc.slam_defense=1
apc.use_request_time=1
```

### Laravel Optimization Commands

```bash
#!/bin/bash
# /usr/local/bin/laravel-optimize.sh

cd /var/www/chinook-admin

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize Composer autoloader
composer dump-autoload --optimize --no-dev

# Clear and warm up OPcache
php artisan opcache:clear
php artisan opcache:compile --force

echo "Laravel optimization completed"
```

## Frontend Optimization

### Asset Compilation

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/chinook-admin/theme.css',
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['alpinejs', '@alpinejs/focus'],
                    filament: ['@filament/forms', '@filament/tables'],
                },
            },
        },
        chunkSizeWarningLimit: 1000,
        sourcemap: false,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
    },
});
```

### Image Optimization

```php
// config/media-library.php
'image_optimizers' => [
    Spatie\ImageOptimizer\Optimizers\Jpegoptim::class => [
        '-m85', // Quality
        '--strip-all',
        '--all-progressive',
    ],
    Spatie\ImageOptimizer\Optimizers\Pngquant::class => [
        '--force',
        '--quality=65-80',
    ],
    Spatie\ImageOptimizer\Optimizers\Optipng::class => [
        '-i0',
        '-o2',
        '-quiet',
    ],
    Spatie\ImageOptimizer\Optimizers\Svgo::class => [
        '--disable=cleanupIDs',
    ],
],

'image_conversions' => [
    'thumb' => [
        'width' => 300,
        'height' => 300,
        'quality' => 85,
        'format' => 'webp',
    ],
    'medium' => [
        'width' => 800,
        'height' => 600,
        'quality' => 90,
        'format' => 'webp',
    ],
],
```

## Query Optimization

### Eloquent Query Optimization

```php
<?php

namespace App\Http\Controllers;

class ArtistController extends Controller
{
    /**
     * Optimized artist listing with eager loading.
     */
    public function index(): Response
    {
        $artists = Artist::select([
                'id',
                'name',
                'country',
                'is_active',
                'created_at'
            ])
            ->with([
                'albums:id,artist_id,title',
                'albums.tracks:id,album_id,name'
            ])
            ->withCount(['albums', 'tracks'])
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(25);

        return response($artists);
    }

    /**
     * Optimized artist details with statistics.
     */
    public function show(Artist $artist): Response
    {
        $artist->load([
            'albums' => function ($query) {
                $query->select(['id', 'artist_id', 'title', 'release_year'])
                      ->with(['tracks:id,album_id,name,unit_price'])
                      ->orderBy('release_year', 'desc');
            },
            'categories:id,name,type',
        ]);

        $statistics = $artist->getCachedStatistics();

        return response([
            'artist' => $artist,
            'statistics' => $statistics,
        ]);
    }
}
```

### Database Query Monitoring

```php
<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class QueryMonitoringServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('production')) {
            DB::listen(function (QueryExecuted $query) {
                // Log slow queries (> 100ms)
                if ($query->time > 100) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }
    }
}
```

## Monitoring and Profiling

### Performance Monitoring

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        // Log performance metrics for slow requests
        if ($executionTime > 500) { // Log requests > 500ms
            Log::info('Performance metrics', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => round($executionTime, 2) . 'ms',
                'memory_usage' => round($memoryUsage, 2) . 'MB',
                'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            ]);
        }

        return $response;
    }
}
```

### Health Check Endpoint

```php
<?php

namespace App\Http\Controllers;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'memory' => $this->checkMemory(),
        ];

        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $time = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'response_time' => round($time, 2) . 'ms',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            $time = (microtime(true) - $start) * 1000;

            return [
                'status' => $value === 'ok' ? 'ok' : 'error',
                'response_time' => round($time, 2) . 'ms',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
```

## Next Steps

1. **Implement Caching** - Set up Redis and application-level caching
2. **Optimize Database** - Apply SQLite optimizations and indexing
3. **Configure Monitoring** - Set up performance monitoring and alerting
4. **Load Testing** - Test performance under realistic load conditions
5. **Continuous Optimization** - Monitor and optimize based on real usage patterns

## Related Documentation

- **[Production Environment](010-production-environment.md)** - Production setup and configuration
- **[Caching Strategy](080-caching-strategy.md)** - Detailed caching implementation
- **[Monitoring Setup](090-monitoring-setup.md)** - Application monitoring and alerting
- **[Database Optimization](060-database-optimization.md)** - Advanced database tuning
