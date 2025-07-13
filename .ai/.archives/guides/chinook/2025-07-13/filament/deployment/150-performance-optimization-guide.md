# Filament Performance Optimization Guide

## Table of Contents

- [Overview](#overview)
- [Database Optimization](#database-optimization)
- [Caching Strategies](#caching-strategies)
- [Asset Optimization](#asset-optimization)
- [Query Optimization](#query-optimization)
- [Memory Management](#memory-management)
- [Monitoring & Profiling](#monitoring--profiling)
- [Production Checklist](#production-checklist)
- [Navigation](#navigation)

## Overview

This comprehensive guide covers performance optimization strategies for Filament admin panels in production
environments. These optimizations are specifically tailored for the Chinook music database system and can significantly
improve response times, reduce server load, and enhance user experience.

**Performance Goals:**

- **Page Load Times**: < 200ms for dashboard, < 500ms for resource pages
- **Database Queries**: < 50 queries per page load
- **Memory Usage**: < 128MB per request
- **Cache Hit Ratio**: > 95% for frequently accessed data

## Database Optimization

### Index Optimization

Create strategic indexes for Filament resource queries:

```sql
-- Indexes for common Filament operations
CREATE INDEX idx_tracks_search ON tracks(name, album_id, created_at);
CREATE INDEX idx_albums_artist_date ON albums(artist_id, release_date);
CREATE INDEX idx_categories_type_active ON categories(type, is_active, sort_order);
CREATE INDEX idx_users_role_active ON users(created_at) WHERE deleted_at IS NULL;

-- Composite indexes for filtering
CREATE INDEX idx_tracks_filter ON tracks(media_type_id, unit_price, created_at);
CREATE INDEX idx_invoices_customer_date ON invoices(customer_id, invoice_date, status);

-- Full-text search indexes
ALTER TABLE tracks ADD FULLTEXT(name);
ALTER TABLE albums ADD FULLTEXT(title);
ALTER TABLE artists ADD FULLTEXT(name);
```

### Query Optimization for Resources

Optimize Filament resource queries:

```php
<?php

namespace App\Filament\Resources;

use App\Models\Track;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrackResource extends Resource
{
    protected static ?string $model = Track::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['album.artist', 'mediaType']) // Eager load relationships
            ->select([
                'tracks.*',
                'albums.title as album_title',
                'artists.name as artist_name'
            ])
            ->join('albums', 'tracks.album_id', '=', 'albums.id')
            ->join('artists', 'albums.artist_id', '=', 'artists.id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
                TextColumn::make('album_title')
                    ->label('Album')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
                TextColumn::make('artist_name')
                    ->label('Artist')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->money('USD')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginate([10, 25, 50, 100]);
    }
}
```

### Database Connection Optimization

Configure optimized database connections:

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_TIMEOUT => 30,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]) : [],
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 10,
        'connect_timeout' => 10.0,
        'wait_timeout' => 3.0,
        'heartbeat' => -1,
        'max_idle_time' => 60.0,
    ],
],
```

## Caching Strategies

### Redis Configuration

Configure Redis for optimal Filament performance:

```php
// config/cache.php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_write_timeout' => 60,
        'context' => [
            'auth' => [env('REDIS_PASSWORD'), env('REDIS_USERNAME', 'default')],
        ],
    ],
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

### Model Caching

Implement model-level caching:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Track extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($track) {
            Cache::tags(['tracks', "track:{$track->id}"])->flush();
        });

        static::deleted(function ($track) {
            Cache::tags(['tracks', "track:{$track->id}"])->flush();
        });
    }

    public function getPopularTracksAttribute()
    {
        return Cache::tags(['tracks', 'popular'])
            ->remember('popular_tracks', 3600, function () {
                return static::withCount('invoiceLines')
                    ->orderBy('invoice_lines_count', 'desc')
                    ->limit(10)
                    ->get();
            });
    }

    public function album()
    {
        return $this->belongsTo(Album::class);
    }

    public function getCachedAlbum()
    {
        return Cache::tags(['albums', "album:{$this->album_id}"])
            ->remember("album:{$this->album_id}", 1800, function () {
                return $this->album()->with('artist')->first();
            });
    }
}
```

### Filament Widget Caching

Cache expensive widget calculations:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Track;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SalesOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Tracks', $this->getTotalTracks())
                ->description('Total tracks in database')
                ->descriptionIcon('heroicon-m-musical-note')
                ->color('success'),

            Stat::make('Monthly Revenue', $this->getMonthlyRevenue())
                ->description('Revenue this month')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Active Customers', $this->getActiveCustomers())
                ->description('Customers with purchases this month')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }

    private function getTotalTracks(): string
    {
        return Cache::remember('stats.total_tracks', 3600, function () {
            return number_format(Track::count());
        });
    }

    private function getMonthlyRevenue(): string
    {
        return Cache::remember('stats.monthly_revenue', 1800, function () {
            $revenue = Invoice::whereMonth('invoice_date', now()->month)
                ->whereYear('invoice_date', now()->year)
                ->sum('total');
            
            return '$' . number_format($revenue, 2);
        });
    }

    private function getActiveCustomers(): string
    {
        return Cache::remember('stats.active_customers', 1800, function () {
            return number_format(
                Invoice::whereMonth('invoice_date', now()->month)
                    ->whereYear('invoice_date', now()->year)
                    ->distinct('customer_id')
                    ->count('customer_id')
            );
        });
    }
}
```

## Asset Optimization

### Vite Configuration

Optimize asset building with Vite:

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
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['alpinejs'],
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
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});
```

### CSS Optimization

Optimize Filament theme CSS:

```css
/* resources/css/filament/admin/theme.css */
@import '/vendor/filament/filament/resources/css/theme.css';

@config 'tailwind.config.js';

/* Optimize critical CSS */
@layer base {
    html {
        font-feature-settings: 'rlig' 1, 'calt' 1;
    }
}

/* Reduce unused CSS */
@layer utilities {
    .text-balance {
        text-wrap: balance;
    }
}

/* Optimize animations */
@layer components {
    .fi-btn {
        transition: all 0.15s ease-in-out;
    }
    
    .fi-ta-table {
        contain: layout style paint;
    }
}
```

### Image Optimization

Implement responsive image handling:

```php
<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;

class ArtistResource extends Resource
{
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('avatar')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '1:1',
                        '4:3',
                        '16:9',
                    ])
                    ->optimize('webp')
                    ->resize(800, 600)
                    ->directory('artists/avatars')
                    ->visibility('public'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(url('/images/placeholder-avatar.webp')),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ]);
    }
}
```

## Query Optimization

### Eager Loading Strategies

Implement efficient eager loading:

```php
<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;

class AlbumResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'artist:id,name',
                'tracks' => function ($query) {
                    $query->select('id', 'album_id', 'name', 'unit_price')
                          ->orderBy('track_number');
                },
                'categories:id,name,type',
            ])
            ->withCount(['tracks', 'categories'])
            ->withSum('tracks', 'unit_price');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('artist.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tracks_count')
                    ->label('Tracks')
                    ->sortable(),
                TextColumn::make('tracks_sum_unit_price')
                    ->label('Total Value')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('artist_id')
                    ->relationship('artist', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
```

### Database Query Monitoring

Monitor and optimize queries:

```php
<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('local', 'staging')) {
            DB::listen(function (QueryExecuted $query) {
                if ($query->time > 100) { // Log slow queries (>100ms)
                    Log::warning('Slow Query Detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                    ]);
                }
            });
        }
    }
}
```

## Memory Management

### PHP Configuration

Optimize PHP settings for Filament:

```ini
; php.ini optimizations
memory_limit = 256M
max_execution_time = 60
max_input_vars = 3000
post_max_size = 32M
upload_max_filesize = 32M

; OPcache settings
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
```

### Memory-Efficient Resource Loading

Implement memory-efficient data loading:

```php
<?php

namespace App\Filament\Resources;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class TrackResource extends Resource
{
    protected static ?int $defaultPaginationPageOption = 25;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'tracks.id',
                'tracks.name',
                'tracks.unit_price',
                'tracks.milliseconds',
                'tracks.album_id',
                'tracks.media_type_id',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                // Other columns...
            ])
            ->paginate([10, 25, 50]) // Limit max pagination
            ->deferLoading();
    }
}
```

## Monitoring & Profiling

### Performance Monitoring

Implement comprehensive monitoring:

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
        $memoryUsage = $endMemory - $startMemory;

        if ($executionTime > 500 || $memoryUsage > 50 * 1024 * 1024) { // 500ms or 50MB
            Log::info('Performance Alert', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => round($executionTime, 2) . 'ms',
                'memory_usage' => $this->formatBytes($memoryUsage),
                'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
            ]);
        }

        return $response;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
```

### Laravel Telescope Integration

Configure Telescope for production monitoring:

```php
// config/telescope.php
'watchers' => [
    Watchers\CacheWatcher::class => [
        'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
    ],
    Watchers\CommandWatcher::class => [
        'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
        'ignore' => ['schedule:run'],
    ],
    Watchers\DumpWatcher::class => [
        'enabled' => env('TELESCOPE_DUMP_WATCHER', true),
        'always' => env('TELESCOPE_DUMP_WATCHER_ALWAYS', false),
    ],
    Watchers\EventWatcher::class => [
        'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
        'ignore' => [],
    ],
    Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
    Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
    Watchers\LogWatcher::class => [
        'enabled' => env('TELESCOPE_LOG_WATCHER', true),
        'level' => 'error',
    ],
    Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
    Watchers\ModelWatcher::class => [
        'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
        'hydrations' => true,
    ],
    Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'ignore_packages' => true,
        'ignore_paths' => [],
        'slow' => 100, // Log queries slower than 100ms
    ],
    Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
    ],
    Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
    Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
],
```

## Production Checklist

### Pre-Deployment Optimization

```bash
#!/bin/bash
# deployment-optimization.sh

echo "Starting Filament optimization..."

# Clear and optimize caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:cache-components

# Optimize Composer autoloader
composer install --optimize-autoloader --no-dev

# Build optimized assets
npm run build

# Optimize database
php artisan db:show --counts
php artisan model:prune

# Generate sitemap and search indexes
php artisan sitemap:generate
php artisan scout:import "App\Models\Track"

echo "Optimization complete!"
```

### Environment Configuration

```env
# Production environment variables
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database optimization
DB_CONNECTION=mysql
DB_SLOW_QUERY_LOG=true
DB_SLOW_QUERY_TIME=2

# Cache configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Performance settings
OCTANE_SERVER=frankenphp
OCTANE_HTTPS=true
OCTANE_HOST=0.0.0.0
OCTANE_PORT=443

# Monitoring
TELESCOPE_ENABLED=true
TELESCOPE_QUERY_WATCHER=true
TELESCOPE_CACHE_WATCHER=true
```

### Performance Benchmarks

Target performance metrics:

| Metric           | Target    | Monitoring          |
|------------------|-----------|---------------------|
| Dashboard Load   | < 200ms   | Laravel Telescope   |
| Resource Index   | < 500ms   | Custom Middleware   |
| Form Submission  | < 300ms   | Application Logs    |
| Search Results   | < 400ms   | Query Monitoring    |
| Memory Usage     | < 128MB   | PHP Memory Profiler |
| Cache Hit Ratio  | > 95%     | Redis Monitoring    |
| Database Queries | < 50/page | Query Counter       |

---

## Navigation

**← Previous:** [Docker Deployment Guide](140-docker-deployment.md)

**Next →** [Scaling Strategies Guide](160-scaling-strategies.md)

**↑ Back to:** [Deployment Index](000-deployment-index.md)
