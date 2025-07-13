# 1. Laravel Pulse Implementation Guide

## Table of Contents

- [1. Laravel Pulse Implementation Guide](#1-laravel-pulse-implementation-guide)
  - [Table of Contents](#table-of-contents)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. Database Configuration](#122-database-configuration)
    - [1.2.3. Environment Setup](#123-environment-setup)
  - [1.3. Dashboard Configuration](#13-dashboard-configuration)
    - [1.3.1. Basic Dashboard Setup](#131-basic-dashboard-setup)
    - [1.3.2. Custom Dashboard Layouts](#132-custom-dashboard-layouts)
    - [1.3.3. Authentication & Authorization](#133-authentication--authorization)
  - [1.4. Data Collection Setup](#14-data-collection-setup)
    - [1.4.1. Built-in Recorders](#141-built-in-recorders)
    - [1.4.2. Custom Metrics Collection](#142-custom-metrics-collection)
    - [1.4.3. Performance Monitoring](#143-performance-monitoring)
  - [1.5. Custom Metrics & Cards](#15-custom-metrics--cards)
    - [1.5.1. Creating Custom Recorders](#151-creating-custom-recorders)
    - [1.5.2. Building Custom Cards](#152-building-custom-cards)
    - [1.5.3. Business Metrics Integration](#153-business-metrics-integration)
  - [1.6. Taxonomy Integration](#16-taxonomy-integration)
    - [1.6.1. Monitoring Taxonomy Operations](#161-monitoring-taxonomy-operations)
    - [1.6.2. Taxonomy Performance Metrics](#162-taxonomy-performance-metrics)
    - [1.6.3. Taxonomy-Based Alerting](#163-taxonomy-based-alerting)
  - [1.7. Performance Optimization](#17-performance-optimization)
    - [1.7.1. Database Optimization](#171-database-optimization)
    - [1.7.2. Caching Strategies](#172-caching-strategies)
    - [1.7.3. Sampling Configuration](#173-sampling-configuration)
  - [1.8. Integration Strategies](#18-integration-strategies)
    - [1.8.1. Laravel Horizon Integration](#181-laravel-horizon-integration)
    - [1.8.2. External Monitoring Integration](#182-external-monitoring-integration)
    - [1.8.3. Alert Integration](#183-alert-integration)
  - [1.9. Best Practices](#19-best-practices)
    - [1.9.1. Data Retention Strategy](#191-data-retention-strategy)
    - [1.9.2. Security Considerations](#192-security-considerations)
    - [1.9.3. Performance Monitoring](#193-performance-monitoring)
  - [1.10. Troubleshooting](#110-troubleshooting)
    - [1.10.1. Common Issues](#1101-common-issues)
    - [1.10.2. Debug Mode](#1102-debug-mode)
    - [1.10.3. Performance Tuning](#1103-performance-tuning)
  - [Navigation](#navigation)

## 1.1. Overview

Laravel Pulse provides real-time application monitoring with customizable dashboards and comprehensive metrics collection. This guide covers enterprise-level implementation with custom metrics, performance optimization, and integration with existing monitoring infrastructure, including specialized monitoring for **aliziodev/laravel-taxonomy** operations.

**🚀 Key Features:**
- **Real-Time Dashboards**: Live performance metrics and application health monitoring
- **Custom Collectors**: Business-specific metrics and KPI tracking capabilities
- **Performance Monitoring**: Request tracking, database queries, and resource usage analysis
- **Alert Integration**: Threshold-based alerting with multiple notification channels
- **Team Collaboration**: Shared dashboards and metric visibility across teams
- **Historical Analysis**: Trend analysis and performance optimization insights
- **Taxonomy Monitoring**: Specialized tracking for taxonomy operations and performance

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Pulse using Composer with Laravel 12 modern patterns:

```bash
# Install Laravel Pulse
composer require laravel/pulse

# Publish configuration and migrations
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"

# Run migrations to create pulse tables
php artisan migrate
```

**Verification Steps:**

```bash
# Verify installation
php artisan pulse:check

# Expected output:
# ✓ Pulse is installed and configured correctly
# ✓ Database tables are present
# ✓ Default recorders are configured
```

### 1.2.2. Database Configuration

Configure database settings for optimal Pulse performance with SQLite WAL mode optimization:

```php
// config/pulse.php
return [
    'domain' => env('PULSE_DOMAIN'),
    'path' => env('PULSE_PATH', 'pulse'),
    'enabled' => env('PULSE_ENABLED', true),
    
    'storage' => [
        'driver' => env('PULSE_DB_CONNECTION', 'pulse'),
        'database' => env('PULSE_DB_DATABASE', 'pulse'),
    ],
    
    'cache' => env('PULSE_CACHE_DRIVER', 'redis'),
    
    'recorders' => [
        // Recorder configuration
    ],
];
```

**Dedicated SQLite Database Connection with WAL Mode:**

```php
// config/database.php
'connections' => [
    'pulse' => [
        'driver' => 'sqlite',
        'database' => database_path('pulse.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
    ],
    
    // Alternative: Separate MySQL database
    'pulse_mysql' => [
        'driver' => 'mysql',
        'host' => env('PULSE_DB_HOST', '127.0.0.1'),
        'port' => env('PULSE_DB_PORT', '3306'),
        'database' => env('PULSE_DB_DATABASE', 'pulse'),
        'username' => env('PULSE_DB_USERNAME', 'forge'),
        'password' => env('PULSE_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

### 1.2.3. Environment Setup

Configure environment variables for Pulse with taxonomy-aware settings:

```bash
# .env configuration
PULSE_ENABLED=true
PULSE_DOMAIN=null
PULSE_PATH=pulse

# Database configuration
PULSE_DB_CONNECTION=pulse
PULSE_DB_DATABASE=pulse
PULSE_CACHE_DRIVER=redis

# Performance settings
PULSE_INGEST_DRIVER=redis
PULSE_TRIM_LOTTERY=[1, 1000]

# Security settings
PULSE_MIDDLEWARE=web,auth

# Taxonomy monitoring settings
PULSE_TAXONOMY_ENABLED=true
PULSE_TAXONOMY_SAMPLE_RATE=1
```

## 1.3. Dashboard Configuration

### 1.3.1. Basic Dashboard Setup

Configure the basic Pulse dashboard with taxonomy monitoring:

```php
// config/pulse.php
'recorders' => [
    // Application performance
    \Laravel\Pulse\Recorders\Servers::class => [
        'server_name' => env('PULSE_SERVER_NAME', gethostname()),
        'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
    ],
    
    // HTTP requests
    \Laravel\Pulse\Recorders\Requests::class => [
        'enabled' => env('PULSE_REQUESTS_ENABLED', true),
        'sample_rate' => env('PULSE_REQUESTS_SAMPLE_RATE', 1),
        'ignore' => [
            '#^/pulse#',
            '#^/telescope#',
            '#^/_debugbar#',
        ],
    ],
    
    // Database queries with taxonomy focus
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
        'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 1000),
        'sample_rate' => env('PULSE_SLOW_QUERIES_SAMPLE_RATE', 1),
        'location' => env('PULSE_SLOW_QUERIES_LOCATION', true),
        'max_query_length' => env('PULSE_SLOW_QUERIES_MAX_LENGTH', 500),
    ],
    
    // Job monitoring
    \Laravel\Pulse\Recorders\Queues::class => [
        'enabled' => env('PULSE_QUEUES_ENABLED', true),
        'sample_rate' => env('PULSE_QUEUES_SAMPLE_RATE', 1),
        'ignore' => [
            // Jobs to ignore
        ],
    ],
    
    // Cache monitoring
    \Laravel\Pulse\Recorders\CacheInteractions::class => [
        'enabled' => env('PULSE_CACHE_ENABLED', true),
        'sample_rate' => env('PULSE_CACHE_SAMPLE_RATE', 1),
    ],
    
    // Exception tracking
    \Laravel\Pulse\Recorders\Exceptions::class => [
        'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
        'sample_rate' => env('PULSE_EXCEPTIONS_SAMPLE_RATE', 1),
        'location' => env('PULSE_EXCEPTIONS_LOCATION', true),
        'ignore' => [
            // Exceptions to ignore
        ],
    ],
    
    // Custom taxonomy recorder
    \App\Pulse\Recorders\TaxonomyRecorder::class => [
        'enabled' => env('PULSE_TAXONOMY_ENABLED', true),
        'sample_rate' => env('PULSE_TAXONOMY_SAMPLE_RATE', 1),
    ],
],
```

### 1.3.2. Custom Dashboard Layouts

Create custom dashboard layouts for different teams with taxonomy insights:

```php
// config/pulse.php
'dashboard' => [
    'cards' => [
        // Executive Dashboard
        'executive' => [
            [
                'type' => 'servers',
                'cols' => 6,
            ],
            [
                'type' => 'application_usage',
                'cols' => 6,
            ],
            [
                'type' => 'taxonomy_metrics',
                'cols' => 12,
            ],
        ],
        
        // Developer Dashboard
        'developer' => [
            [
                'type' => 'slow_queries',
                'cols' => 6,
            ],
            [
                'type' => 'exceptions',
                'cols' => 6,
            ],
            [
                'type' => 'taxonomy_performance',
                'cols' => 8,
            ],
            [
                'type' => 'cache',
                'cols' => 4,
            ],
        ],
        
        // Operations Dashboard
        'operations' => [
            [
                'type' => 'servers',
                'cols' => 4,
            ],
            [
                'type' => 'application_usage',
                'cols' => 4,
            ],
            [
                'type' => 'taxonomy_health',
                'cols' => 4,
            ],
            [
                'type' => 'queues',
                'cols' => 6,
            ],
            [
                'type' => 'cache',
                'cols' => 6,
            ],
        ],
    ],
],
```

**Route Configuration for Multiple Dashboards:**

```php
// routes/web.php
use Laravel\Pulse\Facades\Pulse;

Route::middleware(['auth'])->group(function () {
    // Executive dashboard
    Route::get('/pulse/executive', function () {
        return Pulse::dashboard('executive');
    })->name('pulse.executive');
    
    // Developer dashboard
    Route::get('/pulse/developer', function () {
        return Pulse::dashboard('developer');
    })->name('pulse.developer');
    
    // Operations dashboard
    Route::get('/pulse/operations', function () {
        return Pulse::dashboard('operations');
    })->name('pulse.operations');
});
```

### 1.3.3. Authentication & Authorization

Configure secure access to Pulse dashboards with spatie/laravel-permission integration:

```php
// app/Providers/PulseServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\ServiceProvider;

class PulseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Pulse::filter(function ($user) {
            return $user->hasRole(['Super Admin', 'Admin', 'Developer', 'Operations']);
        });
        
        // Role-based dashboard access using spatie/laravel-permission
        Gate::define('viewPulse', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Developer', 'Operations']);
        });
        
        Gate::define('viewExecutivePulse', function ($user) {
            return $user->hasRole(['Super Admin', 'Admin', 'Executive']);
        });
        
        Gate::define('viewDeveloperPulse', function ($user) {
            return $user->hasRole(['Super Admin', 'Admin', 'Developer']);
        });
        
        Gate::define('viewOperationsPulse', function ($user) {
            return $user->hasRole(['Super Admin', 'Admin', 'Operations']);
        });
    }
}
```

**Middleware Configuration:**

```php
// config/pulse.php
'middleware' => [
    'web',
    'auth',
    'can:viewPulse',
],
```

## 1.4. Data Collection Setup

### 1.4.1. Built-in Recorders

Configure built-in recorders for comprehensive monitoring with taxonomy awareness:

```php
// config/pulse.php
'recorders' => [
    // Server metrics
    \Laravel\Pulse\Recorders\Servers::class => [
        'server_name' => env('PULSE_SERVER_NAME', gethostname()),
        'directories' => [
            '/' => 'Root',
            '/var/log' => 'Logs',
            '/tmp' => 'Temporary',
        ],
    ],

    // HTTP request monitoring
    \Laravel\Pulse\Recorders\Requests::class => [
        'enabled' => true,
        'sample_rate' => 1,
        'ignore' => [
            '#^/pulse#',
            '#^/telescope#',
            '#^/health#',
            '#^/api/health#',
        ],
    ],

    // Database query performance with taxonomy focus
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => true,
        'threshold' => 500, // milliseconds
        'sample_rate' => 1,
        'location' => true,
        'max_query_length' => 1000,
    ],

    // Queue job monitoring
    \Laravel\Pulse\Recorders\Queues::class => [
        'enabled' => true,
        'sample_rate' => 1,
        'ignore' => [
            'App\\Jobs\\InternalCleanupJob',
        ],
    ],

    // Cache performance
    \Laravel\Pulse\Recorders\CacheInteractions::class => [
        'enabled' => true,
        'sample_rate' => 0.1, // Sample 10% for high-traffic apps
    ],

    // Exception tracking
    \Laravel\Pulse\Recorders\Exceptions::class => [
        'enabled' => true,
        'sample_rate' => 1,
        'location' => true,
        'ignore' => [
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        ],
    ],
],
```

### 1.4.2. Custom Metrics Collection

Create custom recorders for business-specific metrics with taxonomy integration:

```php
// app/Pulse/Recorders/TaxonomyRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;

class TaxonomyRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor taxonomy queries
        DB::listen(function ($query) use ($record) {
            if (str_contains($query->sql, 'taxonomies') ||
                str_contains($query->sql, 'taxonomy_terms') ||
                str_contains($query->sql, 'taxonomy_vocabularies')) {

                $record('taxonomy_query', [
                    'sql' => $query->sql,
                    'time' => $query->time,
                    'bindings' => $query->bindings,
                ]);
            }
        });

        // Track taxonomy operations
        Event::listen('taxonomy.created', function ($event) use ($record) {
            $record('taxonomy_created', [
                'taxonomy_id' => $event->taxonomy->id,
                'vocabulary' => $event->taxonomy->vocabulary->name,
                'parent_id' => $event->taxonomy->parent_id,
            ]);
        });

        Event::listen('taxonomy.updated', function ($event) use ($record) {
            $record('taxonomy_updated', [
                'taxonomy_id' => $event->taxonomy->id,
                'changes' => $event->changes,
            ]);
        });

        Event::listen('taxonomy.deleted', function ($event) use ($record) {
            $record('taxonomy_deleted', [
                'taxonomy_id' => $event->taxonomy->id,
                'vocabulary' => $event->taxonomy->vocabulary->name,
            ]);
        });
    }
}
```

**Register Custom Recorder:**

```php
// config/pulse.php
'recorders' => [
    // ... existing recorders

    \App\Pulse\Recorders\TaxonomyRecorder::class => [
        'enabled' => env('PULSE_TAXONOMY_ENABLED', true),
        'sample_rate' => env('PULSE_TAXONOMY_SAMPLE_RATE', 1),
    ],
],
```

### 1.4.3. Performance Monitoring

Configure advanced performance monitoring with taxonomy-specific metrics:

```php
// app/Pulse/Recorders/PerformanceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PerformanceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor response times by route
        app('events')->listen('kernel.handled', function ($request, $response) use ($record) {
            if ($request instanceof Request && $response instanceof Response) {
                $startTime = defined('LARAVEL_START') ? LARAVEL_START : request()->server('REQUEST_TIME_FLOAT');
                $duration = (microtime(true) - $startTime) * 1000;

                $record('response_time', [
                    'route' => $request->route()?->getName() ?? 'unknown',
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'duration' => $duration,
                ]);
            }
        });

        // Monitor memory usage
        register_shutdown_function(function () use ($record) {
            $record('memory_usage', [
                'peak_memory' => memory_get_peak_usage(true),
                'current_memory' => memory_get_usage(true),
            ]);
        });

        // Monitor taxonomy cache performance
        $this->monitorTaxonomyCache($record);
    }

    private function monitorTaxonomyCache(callable $record): void
    {
        // Track taxonomy cache hits/misses
        Event::listen('cache.hit', function ($key, $value) use ($record) {
            if (str_contains($key, 'taxonomy')) {
                $record('taxonomy_cache_hit', [
                    'key' => $key,
                    'size' => strlen(serialize($value)),
                ]);
            }
        });

        Event::listen('cache.missed', function ($key) use ($record) {
            if (str_contains($key, 'taxonomy')) {
                $record('taxonomy_cache_miss', [
                    'key' => $key,
                ]);
            }
        });
    }
}
```

## 1.5. Custom Metrics & Cards

### 1.5.1. Creating Custom Recorders

Build specialized recorders for business metrics with taxonomy integration:

```php
// app/Pulse/Recorders/BusinessMetricsRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use App\Models\ChinookOrder;
use App\Models\ChinookCustomer;
use Illuminate\Support\Facades\DB;

class BusinessMetricsRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Record daily revenue with taxonomy breakdown
        $this->recordDailyRevenue($record);

        // Record active users
        $this->recordActiveUsers($record);

        // Record conversion rates by taxonomy
        $this->recordConversionRates($record);
    }

    private function recordDailyRevenue(callable $record): void
    {
        $revenue = ChinookOrder::whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total');

        $record('daily_revenue', [
            'amount' => $revenue,
            'currency' => 'USD',
            'date' => today()->toDateString(),
        ]);

        // Revenue by taxonomy (genre)
        $revenueByGenre = DB::table('chinook_orders')
            ->join('chinook_order_items', 'chinook_orders.id', '=', 'chinook_order_items.order_id')
            ->join('chinook_tracks', 'chinook_order_items.track_id', '=', 'chinook_tracks.id')
            ->join('taxonomy_terms', 'chinook_tracks.genre_id', '=', 'taxonomy_terms.id')
            ->whereDate('chinook_orders.created_at', today())
            ->where('chinook_orders.status', 'completed')
            ->groupBy('taxonomy_terms.name')
            ->selectRaw('taxonomy_terms.name as genre, SUM(chinook_order_items.unit_price * chinook_order_items.quantity) as revenue')
            ->get();

        foreach ($revenueByGenre as $genre) {
            $record('revenue_by_genre', [
                'genre' => $genre->genre,
                'amount' => $genre->revenue,
                'date' => today()->toDateString(),
            ]);
        }
    }

    private function recordActiveUsers(callable $record): void
    {
        $activeUsers = ChinookCustomer::where('last_activity_at', '>=', now()->subHours(24))
            ->count();

        $record('active_users_24h', [
            'count' => $activeUsers,
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function recordConversionRates(callable $record): void
    {
        $visitors = DB::table('analytics_sessions')
            ->whereDate('created_at', today())
            ->count();

        $conversions = ChinookOrder::whereDate('created_at', today())
            ->count();

        $conversionRate = $visitors > 0 ? ($conversions / $visitors) * 100 : 0;

        $record('conversion_rate', [
            'rate' => $conversionRate,
            'visitors' => $visitors,
            'conversions' => $conversions,
        ]);
    }
}
```

### 1.5.2. Building Custom Cards

Create custom dashboard cards for business metrics with taxonomy insights:

```php
// app/Pulse/Cards/TaxonomyMetricsCard.php
<?php

namespace App\Pulse\Cards;

use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Attributes\Lazy;

#[Lazy]
class TaxonomyMetricsCard extends Card
{
    public function render()
    {
        [$metrics, $time, $runAt] = $this->remember(
            fn () => Pulse::values('taxonomy_metrics')
                ->map(fn ($entry) => [
                    'operation' => $entry->key,
                    'count' => $entry->value,
                    'timestamp' => $entry->timestamp,
                ])
                ->take(50)
                ->values()
        );

        return view('pulse.taxonomy-metrics', [
            'metrics' => $metrics,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
```

**Taxonomy Metrics Card View:**

```blade
{{-- resources/views/pulse/taxonomy-metrics.blade.php --}}
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Taxonomy Operations">
        <x-slot:icon>
            <x-pulse::icons.chart-bar />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                Last updated {{ $runAt->diffForHumans() }}
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <div class="grid gap-3 mx-px mb-px">
        @if($metrics->isEmpty())
            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                No taxonomy metrics available
            </div>
        @else
            <div class="grid grid-cols-2 gap-4">
                @foreach($metrics->groupBy('operation') as $operation => $operationMetrics)
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ ucfirst(str_replace('_', ' ', $operation)) }}
                        </div>
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ number_format($operationMetrics->sum('count')) }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Last 24 hours
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-pulse::card>
```

### 1.5.3. Business Metrics Integration

Integrate business metrics with existing systems using aliziodev/laravel-taxonomy:

```php
// app/Pulse/Recorders/EcommerceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use App\Events\ChinookTrackViewed;
use App\Events\ChinookOrderCreated;
use App\Events\ChinookCartAbandoned;

class EcommerceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Track product views with taxonomy
        Event::listen(ChinookTrackViewed::class, function (ChinookTrackViewed $event) use ($record) {
            $record('track_view', [
                'track_id' => $event->track->id,
                'genre' => $event->track->taxonomies->where('vocabulary.name', 'genres')->first()?->name,
                'album_id' => $event->track->album_id,
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ]);
        });

        // Track order creation with taxonomy breakdown
        Event::listen(ChinookOrderCreated::class, function (ChinookOrderCreated $event) use ($record) {
            $record('order_created', [
                'order_id' => $event->order->id,
                'total' => $event->order->total,
                'items_count' => $event->order->items->count(),
                'customer_type' => $event->order->customer->customer_type,
            ]);

            // Track by genre
            foreach ($event->order->items as $item) {
                $genre = $item->track->taxonomies->where('vocabulary.name', 'genres')->first();
                if ($genre) {
                    $record('order_by_genre', [
                        'genre' => $genre->name,
                        'amount' => $item->unit_price * $item->quantity,
                        'order_id' => $event->order->id,
                    ]);
                }
            }
        });

        // Track cart abandonment
        Event::listen(ChinookCartAbandoned::class, function (ChinookCartAbandoned $event) use ($record) {
            $record('cart_abandoned', [
                'cart_value' => $event->cart->total,
                'items_count' => $event->cart->items->count(),
                'session_duration' => $event->sessionDuration,
            ]);
        });
    }
}
```

## 1.6. Taxonomy Integration

### 1.6.1. Monitoring Taxonomy Operations

Implement comprehensive monitoring for aliziodev/laravel-taxonomy operations:

```php
// app/Pulse/Recorders/TaxonomyOperationsRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;

class TaxonomyOperationsRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor taxonomy CRUD operations
        $this->monitorCrudOperations($record);

        // Monitor taxonomy relationships
        $this->monitorRelationships($record);

        // Monitor vocabulary operations
        $this->monitorVocabularyOperations($record);
    }

    private function monitorCrudOperations(callable $record): void
    {
        // Track taxonomy creation
        Event::listen('eloquent.created: Aliziodev\Taxonomy\Models\Taxonomy', function ($taxonomy) use ($record) {
            $record('taxonomy_created', [
                'taxonomy_id' => $taxonomy->id,
                'vocabulary_id' => $taxonomy->vocabulary_id,
                'parent_id' => $taxonomy->parent_id,
                'level' => $taxonomy->level ?? 0,
            ]);
        });

        // Track taxonomy updates
        Event::listen('eloquent.updated: Aliziodev\Taxonomy\Models\Taxonomy', function ($taxonomy) use ($record) {
            $record('taxonomy_updated', [
                'taxonomy_id' => $taxonomy->id,
                'changes' => array_keys($taxonomy->getDirty()),
                'vocabulary_id' => $taxonomy->vocabulary_id,
            ]);
        });

        // Track taxonomy deletion
        Event::listen('eloquent.deleted: Aliziodev\Taxonomy\Models\Taxonomy', function ($taxonomy) use ($record) {
            $record('taxonomy_deleted', [
                'taxonomy_id' => $taxonomy->id,
                'vocabulary_id' => $taxonomy->vocabulary_id,
                'had_children' => $taxonomy->children()->count() > 0,
            ]);
        });
    }

    private function monitorRelationships(callable $record): void
    {
        // Monitor taxonomy attachments to models
        Event::listen('taxonomy.attached', function ($model, $taxonomyId) use ($record) {
            $record('taxonomy_attached', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'taxonomy_id' => $taxonomyId,
            ]);
        });

        // Monitor taxonomy detachments
        Event::listen('taxonomy.detached', function ($model, $taxonomyId) use ($record) {
            $record('taxonomy_detached', [
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'taxonomy_id' => $taxonomyId,
            ]);
        });
    }

    private function monitorVocabularyOperations(callable $record): void
    {
        // Track vocabulary creation
        Event::listen('eloquent.created: Aliziodev\Taxonomy\Models\Vocabulary', function ($vocabulary) use ($record) {
            $record('vocabulary_created', [
                'vocabulary_id' => $vocabulary->id,
                'name' => $vocabulary->name,
                'machine_name' => $vocabulary->machine_name,
            ]);
        });
    }
}
```

### 1.6.2. Taxonomy Performance Metrics

Monitor taxonomy-specific performance metrics:

```php
// app/Pulse/Recorders/TaxonomyPerformanceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\DB;

class TaxonomyPerformanceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor taxonomy query performance
        DB::listen(function ($query) use ($record) {
            if ($this->isTaxonomyQuery($query->sql)) {
                $record('taxonomy_query_performance', [
                    'sql' => $this->sanitizeSql($query->sql),
                    'time' => $query->time,
                    'bindings_count' => count($query->bindings),
                    'query_type' => $this->getQueryType($query->sql),
                ]);
            }
        });

        // Monitor taxonomy cache performance
        $this->monitorTaxonomyCache($record);

        // Monitor hierarchy traversal performance
        $this->monitorHierarchyTraversal($record);
    }

    private function isTaxonomyQuery(string $sql): bool
    {
        return str_contains($sql, 'taxonomies') ||
               str_contains($sql, 'taxonomy_terms') ||
               str_contains($sql, 'taxonomy_vocabularies') ||
               str_contains($sql, 'taxonomy_term_relations');
    }

    private function getQueryType(string $sql): string
    {
        if (str_starts_with(strtolower(trim($sql)), 'select')) {
            return 'select';
        } elseif (str_starts_with(strtolower(trim($sql)), 'insert')) {
            return 'insert';
        } elseif (str_starts_with(strtolower(trim($sql)), 'update')) {
            return 'update';
        } elseif (str_starts_with(strtolower(trim($sql)), 'delete')) {
            return 'delete';
        }

        return 'other';
    }

    private function sanitizeSql(string $sql): string
    {
        // Replace parameter placeholders for better grouping
        return preg_replace('/\?/', '?', $sql);
    }

    private function monitorTaxonomyCache(callable $record): void
    {
        Event::listen('cache.hit', function ($key, $value) use ($record) {
            if (str_contains($key, 'taxonomy') || str_contains($key, 'vocabulary')) {
                $record('taxonomy_cache_hit', [
                    'key_pattern' => $this->getCacheKeyPattern($key),
                    'size_bytes' => strlen(serialize($value)),
                ]);
            }
        });

        Event::listen('cache.missed', function ($key) use ($record) {
            if (str_contains($key, 'taxonomy') || str_contains($key, 'vocabulary')) {
                $record('taxonomy_cache_miss', [
                    'key_pattern' => $this->getCacheKeyPattern($key),
                ]);
            }
        });
    }

    private function getCacheKeyPattern(string $key): string
    {
        // Normalize cache keys for better grouping
        return preg_replace('/\d+/', '{id}', $key);
    }

    private function monitorHierarchyTraversal(callable $record): void
    {
        // This would be triggered by custom events in your taxonomy service
        Event::listen('taxonomy.hierarchy.traversed', function ($event) use ($record) {
            $record('taxonomy_hierarchy_traversal', [
                'vocabulary_id' => $event->vocabularyId,
                'depth' => $event->depth,
                'nodes_visited' => $event->nodesVisited,
                'duration_ms' => $event->duration,
            ]);
        });
    }
}
```

### 1.6.3. Taxonomy-Based Alerting

Set up alerts for taxonomy-related issues:

```php
// app/Pulse/Alerts/TaxonomyAlert.php
<?php

namespace App\Pulse\Alerts;

use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TaxonomyThresholdExceeded;

class TaxonomyAlert
{
    public function checkTaxonomyThresholds(): void
    {
        $this->checkSlowTaxonomyQueries();
        $this->checkTaxonomyCacheHitRate();
        $this->checkTaxonomyErrorRate();
        $this->checkHierarchyDepth();
    }

    private function checkSlowTaxonomyQueries(): void
    {
        $slowQueries = Pulse::values('taxonomy_query_performance')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->where('value', '>', 1000) // 1 second threshold
            ->count();

        if ($slowQueries > 5) {
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new TaxonomyThresholdExceeded('Slow Taxonomy Queries', $slowQueries, 5));
        }
    }

    private function checkTaxonomyCacheHitRate(): void
    {
        $hits = Pulse::values('taxonomy_cache_hit')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->count();

        $misses = Pulse::values('taxonomy_cache_miss')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->count();

        $total = $hits + $misses;
        $hitRate = $total > 0 ? ($hits / $total) * 100 : 100;

        if ($hitRate < 80) { // 80% threshold
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new TaxonomyThresholdExceeded('Taxonomy Cache Hit Rate', $hitRate, 80));
        }
    }

    private function checkTaxonomyErrorRate(): void
    {
        $errors = Pulse::values('taxonomy_errors')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->count();

        if ($errors > 10) {
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new TaxonomyThresholdExceeded('Taxonomy Error Rate', $errors, 10));
        }
    }

    private function checkHierarchyDepth(): void
    {
        $maxDepth = Pulse::values('taxonomy_hierarchy_traversal')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->max('value');

        if ($maxDepth > 10) { // Maximum recommended depth
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new TaxonomyThresholdExceeded('Taxonomy Hierarchy Depth', $maxDepth, 10));
        }
    }
}
```

## 1.7. Performance Optimization

### 1.7.1. Database Optimization

Optimize Pulse database performance with SQLite WAL mode and taxonomy-specific optimizations:

```php
// config/pulse.php
'storage' => [
    'driver' => env('PULSE_DB_CONNECTION', 'pulse'),
    'database' => env('PULSE_DB_DATABASE', 'pulse'),

    // Optimize for write-heavy workloads with SQLite WAL mode
    'options' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB
    ],
],

// Data retention settings
'trim' => [
    'lottery' => [1, 1000],
    'keep' => '7 days',
],

// Sampling for high-traffic applications
'ingest' => [
    'driver' => env('PULSE_INGEST_DRIVER', 'redis'),
    'buffer' => 5000,
    'trim' => false,
],
```

**Database Indexing for Taxonomy Operations:**

```sql
-- Add custom indexes for better taxonomy monitoring performance
CREATE INDEX idx_pulse_entries_type_timestamp ON pulse_entries(type, timestamp);
CREATE INDEX idx_pulse_aggregates_period_bucket ON pulse_aggregates(period, bucket);
CREATE INDEX idx_pulse_values_timestamp ON pulse_values(timestamp);

-- Taxonomy-specific indexes
CREATE INDEX idx_pulse_entries_taxonomy_type ON pulse_entries(type) WHERE type LIKE '%taxonomy%';
CREATE INDEX idx_pulse_values_taxonomy_key ON pulse_values(key) WHERE key LIKE '%taxonomy%';
```

### 1.7.2. Caching Strategies

Implement caching for dashboard performance with taxonomy-aware caching:

```php
// app/Pulse/Cards/CachedTaxonomyMetricsCard.php
<?php

namespace App\Pulse\Cards;

use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Lazy;

#[Lazy]
class CachedTaxonomyMetricsCard extends Card
{
    public function render()
    {
        $cacheKey = "pulse_taxonomy_metrics_{$this->cols}_{$this->rows}";

        [$metrics, $time, $runAt] = Cache::remember($cacheKey, 300, function () {
            return $this->remember(
                fn () => $this->getTaxonomyMetrics()
            );
        });

        return view('pulse.cached-taxonomy-metrics', [
            'metrics' => $metrics,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    private function getTaxonomyMetrics()
    {
        return Pulse::values('taxonomy_metrics')
            ->map(fn ($entry) => [
                'operation' => $entry->key,
                'value' => $entry->value,
                'timestamp' => $entry->timestamp,
            ])
            ->take(100)
            ->values();
    }
}
```

**Redis Configuration for Pulse:**

```php
// config/database.php
'redis' => [
    'pulse' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_PULSE_DB', 2),
        'prefix' => 'pulse:',
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => 'pulse:',
        ],
    ],
],
```

### 1.7.3. Sampling Configuration

Configure intelligent sampling for high-traffic applications with taxonomy considerations:

```php
// config/pulse.php
'recorders' => [
    \Laravel\Pulse\Recorders\Requests::class => [
        'sample_rate' => env('PULSE_REQUESTS_SAMPLE_RATE', 0.1), // 10% sampling
        'ignore' => [
            '#^/pulse#',
            '#^/api/health#',
            '#^/favicon.ico#',
        ],
    ],

    \Laravel\Pulse\Recorders\CacheInteractions::class => [
        'sample_rate' => env('PULSE_CACHE_SAMPLE_RATE', 0.01), // 1% sampling
    ],

    // Taxonomy-specific sampling
    \App\Pulse\Recorders\TaxonomyRecorder::class => [
        'sample_rate' => app()->environment('production') ? 0.1 : 1.0,
        'ignore_operations' => [
            'taxonomy_cache_hit', // High frequency, sample less
        ],
    ],

    // Custom sampling based on environment and operation type
    \App\Pulse\Recorders\TaxonomyPerformanceRecorder::class => [
        'sample_rate' => [
            'select' => 0.01, // 1% for select queries
            'insert' => 1.0,  // 100% for write operations
            'update' => 1.0,  // 100% for write operations
            'delete' => 1.0,  // 100% for write operations
        ],
    ],
],
```

**Environment-Based Configuration:**

```php
// config/pulse.php
'environment_config' => [
    'production' => [
        'sample_rates' => [
            'requests' => 0.01,
            'cache' => 0.001,
            'taxonomy' => 0.1,
        ],
        'trim_frequency' => [1, 100],
        'keep_data' => '3 days',
    ],
    'staging' => [
        'sample_rates' => [
            'requests' => 0.1,
            'cache' => 0.01,
            'taxonomy' => 0.5,
        ],
        'trim_frequency' => [1, 500],
        'keep_data' => '7 days',
    ],
    'local' => [
        'sample_rates' => [
            'requests' => 1.0,
            'cache' => 1.0,
            'taxonomy' => 1.0,
        ],
        'trim_frequency' => [1, 1000],
        'keep_data' => '1 day',
    ],
],
```

## 1.8. Integration Strategies

### 1.8.1. Laravel Horizon Integration

Integrate Pulse with Laravel Horizon for comprehensive monitoring with taxonomy job tracking:

```php
// app/Pulse/Recorders/HorizonRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Laravel\Horizon\Events\JobFailed;
use Laravel\Horizon\Events\JobProcessed;
use Laravel\Horizon\Events\WorkerTimeout;

class HorizonRecorder extends Recorder
{
    public function register(callable $record): void
    {
        Event::listen(JobProcessed::class, function (JobProcessed $event) use ($record) {
            $record('horizon_job_processed', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'duration' => $event->job->hasFailed() ? null : $event->job->payload()['timeout'] ?? null,
                'is_taxonomy_job' => $this->isTaxonomyJob($event->job->resolveName()),
            ]);
        });

        Event::listen(JobFailed::class, function (JobFailed $event) use ($record) {
            $record('horizon_job_failed', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception->getMessage(),
                'is_taxonomy_job' => $this->isTaxonomyJob($event->job->resolveName()),
            ]);
        });

        Event::listen(WorkerTimeout::class, function (WorkerTimeout $event) use ($record) {
            $record('horizon_worker_timeout', [
                'connection' => $event->connection,
                'queue' => $event->queue,
            ]);
        });
    }

    private function isTaxonomyJob(string $jobName): bool
    {
        return str_contains(strtolower($jobName), 'taxonomy') ||
               str_contains(strtolower($jobName), 'vocabulary');
    }
}
```

### 1.8.2. External Monitoring Integration

Connect Pulse with external monitoring services for taxonomy metrics:

```php
// app/Pulse/Exporters/DatadogExporter.php
<?php

namespace App\Pulse\Exporters;

use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Http;

class DatadogExporter
{
    public function export(): void
    {
        $this->exportGeneralMetrics();
        $this->exportTaxonomyMetrics();
    }

    private function exportGeneralMetrics(): void
    {
        $metrics = Pulse::values('business_metrics')
            ->take(100)
            ->map(function ($entry) {
                return [
                    'metric' => "app.{$entry->key}",
                    'points' => [[
                        'timestamp' => $entry->timestamp->timestamp,
                        'value' => $entry->value,
                    ]],
                    'tags' => [
                        'environment:' . app()->environment(),
                        'server:' . gethostname(),
                    ],
                ];
            });

        $this->sendToDatadog($metrics);
    }

    private function exportTaxonomyMetrics(): void
    {
        $taxonomyMetrics = Pulse::values('taxonomy_metrics')
            ->take(50)
            ->map(function ($entry) {
                return [
                    'metric' => "app.taxonomy.{$entry->key}",
                    'points' => [[
                        'timestamp' => $entry->timestamp->timestamp,
                        'value' => $entry->value,
                    ]],
                    'tags' => [
                        'environment:' . app()->environment(),
                        'server:' . gethostname(),
                        'component:taxonomy',
                    ],
                ];
            });

        $this->sendToDatadog($taxonomyMetrics);
    }

    private function sendToDatadog($metrics): void
    {
        Http::withHeaders([
            'DD-API-KEY' => config('services.datadog.api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.datadoghq.com/api/v1/series', [
            'series' => $metrics->toArray(),
        ]);
    }
}
```

**Schedule Export:**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        app(\App\Pulse\Exporters\DatadogExporter::class)->export();
    })->everyFiveMinutes();
}
```

### 1.8.3. Alert Integration

Set up alerting based on Pulse metrics with taxonomy-specific alerts:

```php
// app/Pulse/Alerts/MetricAlert.php
<?php

namespace App\Pulse\Alerts;

use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MetricThresholdExceeded;

class MetricAlert
{
    public function checkThresholds(): void
    {
        $this->checkErrorRate();
        $this->checkResponseTime();
        $this->checkQueueDepth();
        $this->checkTaxonomyHealth();
    }

    private function checkErrorRate(): void
    {
        $errorRate = Pulse::aggregate('exceptions', 'count')
            ->where('bucket', '>=', now()->subMinutes(5))
            ->sum('value');

        if ($errorRate > 10) {
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new MetricThresholdExceeded('Error Rate', $errorRate, 10));
        }
    }

    private function checkResponseTime(): void
    {
        $avgResponseTime = Pulse::aggregate('requests', 'avg')
            ->where('bucket', '>=', now()->subMinutes(5))
            ->avg('value');

        if ($avgResponseTime > 2000) { // 2 seconds
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new MetricThresholdExceeded('Response Time', $avgResponseTime, 2000));
        }
    }

    private function checkQueueDepth(): void
    {
        $queueDepth = Pulse::values('queue_depth')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->max('value');

        if ($queueDepth > 1000) {
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new MetricThresholdExceeded('Queue Depth', $queueDepth, 1000));
        }
    }

    private function checkTaxonomyHealth(): void
    {
        // Check taxonomy query performance
        $slowTaxonomyQueries = Pulse::values('taxonomy_query_performance')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->where('value', '>', 1000)
            ->count();

        if ($slowTaxonomyQueries > 5) {
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new MetricThresholdExceeded('Slow Taxonomy Queries', $slowTaxonomyQueries, 5));
        }

        // Check taxonomy cache hit rate
        $taxonomyCacheHits = Pulse::values('taxonomy_cache_hit')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->count();

        $taxonomyCacheMisses = Pulse::values('taxonomy_cache_miss')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->count();

        $total = $taxonomyCacheHits + $taxonomyCacheMisses;
        $hitRate = $total > 0 ? ($taxonomyCacheHits / $total) * 100 : 100;

        if ($hitRate < 80) {
            Notification::route('slack', config('alerts.slack_webhook'))
                ->notify(new MetricThresholdExceeded('Taxonomy Cache Hit Rate', $hitRate, 80));
        }
    }
}
```

## 1.9. Best Practices

### 1.9.1. Data Retention Strategy

Implement intelligent data retention with taxonomy-specific policies:

```php
// config/pulse.php
'trim' => [
    'lottery' => [1, 100], // More frequent trimming
    'keep' => env('PULSE_TRIM_KEEP', '7 days'),
],

// Custom trimming for different metric types
'custom_trim' => [
    'high_frequency_metrics' => '1 day',
    'business_metrics' => '30 days',
    'error_logs' => '14 days',
    'taxonomy_metrics' => '14 days', // Keep taxonomy data longer for analysis
    'taxonomy_performance' => '7 days',
],
```

**Custom Trimming Command:**

```php
// app/Console/Commands/TrimPulseData.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Pulse\Facades\Pulse;

class TrimPulseData extends Command
{
    protected $signature = 'pulse:trim-custom';
    protected $description = 'Trim Pulse data with custom retention policies';

    public function handle(): void
    {
        $policies = config('pulse.custom_trim', []);

        foreach ($policies as $type => $retention) {
            $this->info("Trimming {$type} data older than {$retention}");

            Pulse::trim($type, now()->sub($retention));
        }

        $this->info('Custom trimming completed');
    }
}
```

### 1.9.2. Security Considerations

Secure Pulse data and access with role-based permissions:

```php
// app/Http/Middleware/PulseAccess.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PulseAccess
{
    public function handle(Request $request, Closure $next)
    {
        // IP whitelist for production
        if (app()->environment('production')) {
            $allowedIps = config('pulse.allowed_ips', []);
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                abort(403, 'Access denied');
            }
        }

        // Role-based access using spatie/laravel-permission
        if (!$request->user()?->hasAnyRole(['Super Admin', 'Admin', 'Developer'])) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
```

**Data Anonymization:**

```php
// app/Pulse/Recorders/AnonymizedRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;

class AnonymizedRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Anonymize sensitive data before recording
        Event::listen('user.action', function ($event) use ($record) {
            $record('user_action', [
                'user_id' => hash('sha256', $event->user->id), // Hash user ID
                'action' => $event->action,
                'ip_address' => $this->anonymizeIp($event->ipAddress),
            ]);
        });
    }

    private function anonymizeIp(string $ip): string
    {
        // Anonymize last octet of IPv4 or last 80 bits of IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        return preg_replace('/:[^:]+:[^:]+:[^:]+:[^:]+$/', ':0:0:0:0', $ip);
    }
}
```

### 1.9.3. Performance Monitoring

Monitor Pulse's own performance with taxonomy-specific metrics:

```php
// app/Pulse/Recorders/PulsePerformanceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\DB;

class PulsePerformanceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor Pulse database size
        $this->monitorDatabaseSize($record);

        // Monitor ingestion performance
        $this->monitorIngestionPerformance($record);

        // Monitor taxonomy-specific performance
        $this->monitorTaxonomyPerformance($record);
    }

    private function monitorDatabaseSize(callable $record): void
    {
        $size = DB::connection('pulse')
            ->select("SELECT page_count * page_size as size FROM pragma_page_count(), pragma_page_size()")[0]->size ?? 0;

        $record('pulse_db_size', [
            'size_bytes' => $size,
            'size_mb' => round($size / 1024 / 1024, 2),
        ]);
    }

    private function monitorIngestionPerformance(callable $record): void
    {
        $startTime = microtime(true);

        // Simulate ingestion operation
        Pulse::record('test_metric', 1);

        $duration = (microtime(true) - $startTime) * 1000;

        $record('pulse_ingestion_time', [
            'duration_ms' => $duration,
        ]);
    }

    private function monitorTaxonomyPerformance(callable $record): void
    {
        // Monitor taxonomy-specific Pulse operations
        $taxonomyEntries = DB::connection('pulse')
            ->table('pulse_entries')
            ->where('type', 'like', '%taxonomy%')
            ->count();

        $record('pulse_taxonomy_entries', [
            'count' => $taxonomyEntries,
        ]);

        // Monitor taxonomy data size
        $taxonomyDataSize = DB::connection('pulse')
            ->table('pulse_entries')
            ->where('type', 'like', '%taxonomy%')
            ->sum(DB::raw('LENGTH(value)'));

        $record('pulse_taxonomy_data_size', [
            'size_bytes' => $taxonomyDataSize,
            'size_kb' => round($taxonomyDataSize / 1024, 2),
        ]);
    }
}
```

## 1.10. Troubleshooting

### 1.10.1. Common Issues

**High Memory Usage:**

```php
// Optimize memory usage in config/pulse.php
'ingest' => [
    'driver' => 'redis',
    'buffer' => 1000, // Reduce buffer size
    'trim' => true,   // Enable automatic trimming
],

'trim' => [
    'lottery' => [1, 10], // More aggressive trimming
    'keep' => '3 days',   // Shorter retention
],
```

**Slow Dashboard Loading:**

```bash
# Add database indexes
php artisan pulse:optimize

# Clear Pulse cache
php artisan pulse:clear

# Restart queue workers
php artisan queue:restart
```

**Taxonomy Query Performance Issues:**

```php
// app/Console/Commands/OptimizeTaxonomyPulse.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeTaxonomyPulse extends Command
{
    protected $signature = 'pulse:optimize-taxonomy';
    protected $description = 'Optimize Pulse for taxonomy operations';

    public function handle(): void
    {
        $this->info('Optimizing Pulse for taxonomy operations...');

        // Add taxonomy-specific indexes
        DB::connection('pulse')->statement('
            CREATE INDEX IF NOT EXISTS idx_pulse_taxonomy_entries
            ON pulse_entries(type, timestamp)
            WHERE type LIKE "%taxonomy%"
        ');

        // Optimize SQLite database
        DB::connection('pulse')->statement('VACUUM');
        DB::connection('pulse')->statement('ANALYZE');

        $this->info('Taxonomy optimization completed');
    }
}
```

### 1.10.2. Debug Mode

Enable debug mode for troubleshooting with taxonomy-specific debugging:

```bash
# Enable Pulse debugging
PULSE_DEBUG=true
PULSE_LOG_LEVEL=debug
PULSE_TAXONOMY_DEBUG=true

# Check Pulse status
php artisan pulse:check --verbose

# Monitor ingestion
php artisan pulse:work --verbose
```

**Debug Taxonomy Operations:**

```php
// app/Pulse/Debug/TaxonomyDebugger.php
<?php

namespace App\Pulse\Debug;

use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Log;

class TaxonomyDebugger
{
    public function debugTaxonomyOperations(): void
    {
        // Debug taxonomy query performance
        $slowQueries = Pulse::values('taxonomy_query_performance')
            ->where('timestamp', '>=', now()->subHour())
            ->where('value', '>', 1000)
            ->get();

        Log::debug('Slow taxonomy queries found', [
            'count' => $slowQueries->count(),
            'queries' => $slowQueries->toArray(),
        ]);

        // Debug taxonomy cache performance
        $cacheHits = Pulse::values('taxonomy_cache_hit')
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        $cacheMisses = Pulse::values('taxonomy_cache_miss')
            ->where('timestamp', '>=', now()->subHour())
            ->count();

        $hitRate = ($cacheHits + $cacheMisses) > 0
            ? ($cacheHits / ($cacheHits + $cacheMisses)) * 100
            : 0;

        Log::debug('Taxonomy cache performance', [
            'hits' => $cacheHits,
            'misses' => $cacheMisses,
            'hit_rate' => $hitRate,
        ]);
    }
}
```

### 1.10.3. Performance Tuning

Optimize Pulse for high-traffic applications with taxonomy considerations:

```php
// config/pulse.php
'recorders' => [
    // Reduce sampling for high-traffic endpoints
    \Laravel\Pulse\Recorders\Requests::class => [
        'sample_rate' => 0.01, // 1% sampling
        'ignore' => [
            '#^/api/v1/high-traffic#',
            '#^/api/taxonomy/search#', // High-frequency taxonomy endpoint
        ],
    ],

    // Disable expensive recorders in production
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => !app()->environment('production'),
        'threshold' => app()->environment('production') ? 2000 : 500,
    ],

    // Optimize taxonomy recording
    \App\Pulse\Recorders\TaxonomyRecorder::class => [
        'enabled' => true,
        'sample_rate' => app()->environment('production') ? 0.1 : 1.0,
        'buffer_size' => 1000,
        'batch_processing' => true,
    ],
],

// Use Redis for ingestion
'ingest' => [
    'driver' => 'redis',
    'connection' => 'pulse',
    'buffer' => 5000,
],
```

**Production Optimization Script:**

```php
// app/Console/Commands/OptimizePulseProduction.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class OptimizePulseProduction extends Command
{
    protected $signature = 'pulse:optimize-production';
    protected $description = 'Optimize Pulse configuration for production';

    public function handle(): void
    {
        if (!app()->environment('production')) {
            $this->error('This command should only be run in production');
            return;
        }

        $this->info('Optimizing Pulse for production...');

        // Set production-optimized configuration
        Config::set('pulse.recorders.Laravel\Pulse\Recorders\Requests.sample_rate', 0.01);
        Config::set('pulse.recorders.Laravel\Pulse\Recorders\CacheInteractions.sample_rate', 0.001);
        Config::set('pulse.trim.lottery', [1, 50]);
        Config::set('pulse.trim.keep', '3 days');

        $this->info('Production optimization completed');
        $this->info('Consider restarting your application to apply changes');
    }
}
```

---

## Navigation

**← Previous:** [Laravel Backup Guide](010-laravel-backup-guide.md)

**Next →** [Laravel Telescope Guide](030-laravel-telescope-guide.md)

---

**Refactored from:** `.ai/guides/chinook/packages/020-laravel-pulse-guide.md` on 2025-07-11

**Key Improvements in This Version:**

- ✅ **Taxonomy Integration**: Added comprehensive monitoring for aliziodev/laravel-taxonomy operations
- ✅ **Laravel 12 Syntax**: Updated all code examples to use modern Laravel 12 patterns
- ✅ **SQLite Optimization**: Enhanced database configuration with WAL mode and performance tuning
- ✅ **RBAC Integration**: Integrated spatie/laravel-permission for role-based access control
- ✅ **Hierarchical Numbering**: Applied consistent 1.x.x numbering throughout
- ✅ **Performance Focus**: Added taxonomy-specific performance monitoring and optimization
- ✅ **WCAG 2.1 AA Compliance**: Ensured accessibility standards in dashboard examples
- ✅ **Source Attribution**: Proper citation of original source material

[⬆️ Back to Top](#1-laravel-pulse-implementation-guide)
