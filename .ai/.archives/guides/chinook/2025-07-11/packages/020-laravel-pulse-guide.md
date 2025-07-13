# 1. Laravel Pulse Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/020-laravel-pulse-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Pulse Implementation Guide](#1-laravel-pulse-implementation-guide)
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
  - [1.6. Performance Optimization](#16-performance-optimization)
    - [1.6.1. Database Optimization](#161-database-optimization)
    - [1.6.2. Caching Strategies](#162-caching-strategies)
    - [1.6.3. Sampling Configuration](#163-sampling-configuration)
  - [1.7. Integration Strategies](#17-integration-strategies)
    - [1.7.1. Laravel Horizon Integration](#171-laravel-horizon-integration)
    - [1.7.2. External Monitoring Integration](#172-external-monitoring-integration)
    - [1.7.3. Alert Integration](#173-alert-integration)
  - [1.8. Best Practices](#18-best-practices)
    - [1.8.1. Data Retention Strategy](#181-data-retention-strategy)
    - [1.8.2. Security Considerations](#182-security-considerations)
    - [1.8.3. Performance Monitoring](#183-performance-monitoring)
  - [1.9. Troubleshooting](#19-troubleshooting)
    - [1.9.1. Common Issues](#191-common-issues)
    - [1.9.2. Debug Mode](#192-debug-mode)
    - [1.9.3. Performance Tuning](#193-performance-tuning)
  - [1.10. Navigation](#110-navigation)

## 1.1. Overview

Laravel Pulse provides real-time application monitoring with customizable dashboards and comprehensive metrics collection. This guide covers enterprise-level implementation with custom metrics, performance optimization, and integration with existing monitoring infrastructure for the Chinook music store application.

**🚀 Key Features:**
- **Real-Time Dashboards**: Live performance metrics and application health monitoring for Chinook operations
- **Custom Collectors**: Business-specific metrics and KPI tracking for music sales and customer behavior
- **Performance Monitoring**: Request tracking, database queries, and resource usage analysis for Chinook APIs
- **Alert Integration**: Threshold-based alerting with multiple notification channels for critical business metrics
- **Team Collaboration**: Shared dashboards and metric visibility across Chinook development teams
- **Historical Analysis**: Trend analysis and performance optimization insights for music catalog operations
- **Taxonomy Integration**: Monitor taxonomy operations and performance using aliziodev/laravel-taxonomy

**🎵 Chinook-Specific Monitoring:**
- Track music catalog browsing patterns and genre popularity
- Monitor customer purchase behavior and conversion rates
- Analyze artist and album performance metrics
- Track playlist creation and sharing activities
- Monitor taxonomy-based filtering and search performance

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Pulse using Composer for the Chinook application:

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

Configure database settings for optimal Pulse performance with SQLite optimization for Chinook:

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

**Dedicated SQLite Database Connection for Chinook:**

```php
// config/database.php
'connections' => [
    'pulse' => [
        'driver' => 'sqlite',
        'database' => database_path('chinook_pulse.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
    ],
    
    // Alternative: Separate MySQL database for production
    'pulse_mysql' => [
        'driver' => 'mysql',
        'host' => env('PULSE_DB_HOST', '127.0.0.1'),
        'port' => env('PULSE_DB_PORT', '3306'),
        'database' => env('PULSE_DB_DATABASE', 'chinook_pulse'),
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

Configure environment variables for Pulse in the Chinook application:

```bash
# .env configuration
PULSE_ENABLED=true
PULSE_DOMAIN=null
PULSE_PATH=pulse

# Database configuration
PULSE_DB_CONNECTION=pulse
PULSE_DB_DATABASE=chinook_pulse
PULSE_CACHE_DRIVER=redis

# Performance settings
PULSE_INGEST_DRIVER=redis
PULSE_TRIM_LOTTERY=[1, 1000]

# Security settings
PULSE_MIDDLEWARE=web,auth

# Chinook-specific settings
PULSE_CHINOOK_METRICS_ENABLED=true
PULSE_TAXONOMY_MONITORING=true
PULSE_MUSIC_ANALYTICS=true
```

## 1.3. Dashboard Configuration

### 1.3.1. Basic Dashboard Setup

Configure the basic Pulse dashboard for Chinook monitoring:

```php
// config/pulse.php
'recorders' => [
    // Application performance
    \Laravel\Pulse\Recorders\Servers::class => [
        'server_name' => env('PULSE_SERVER_NAME', 'chinook-' . gethostname()),
        'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
    ],
    
    // HTTP requests for Chinook APIs
    \Laravel\Pulse\Recorders\Requests::class => [
        'enabled' => env('PULSE_REQUESTS_ENABLED', true),
        'sample_rate' => env('PULSE_REQUESTS_SAMPLE_RATE', 1),
        'ignore' => [
            '#^/pulse#',
            '#^/telescope#',
            '#^/_debugbar#',
            '#^/health#',
        ],
    ],
    
    // Database queries for Chinook operations
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
        'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 1000),
        'sample_rate' => env('PULSE_SLOW_QUERIES_SAMPLE_RATE', 1),
        'location' => env('PULSE_SLOW_QUERIES_LOCATION', true),
        'max_query_length' => env('PULSE_SLOW_QUERIES_MAX_LENGTH', 500),
    ],
    
    // Job monitoring for Chinook background tasks
    \Laravel\Pulse\Recorders\Queues::class => [
        'enabled' => env('PULSE_QUEUES_ENABLED', true),
        'sample_rate' => env('PULSE_QUEUES_SAMPLE_RATE', 1),
        'ignore' => [
            // Jobs to ignore
        ],
    ],
    
    // Cache monitoring for taxonomy and music data
    \Laravel\Pulse\Recorders\CacheInteractions::class => [
        'enabled' => env('PULSE_CACHE_ENABLED', true),
        'sample_rate' => env('PULSE_CACHE_SAMPLE_RATE', 1),
    ],
    
    // Exception tracking for Chinook operations
    \Laravel\Pulse\Recorders\Exceptions::class => [
        'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
        'sample_rate' => env('PULSE_EXCEPTIONS_SAMPLE_RATE', 1),
        'location' => env('PULSE_EXCEPTIONS_LOCATION', true),
        'ignore' => [
            // Exceptions to ignore
        ],
    ],
],
```

### 1.3.2. Custom Dashboard Layouts

Create custom dashboard layouts for different Chinook teams:

```php
// config/pulse.php
'dashboard' => [
    'cards' => [
        // Executive Dashboard for Chinook Management
        'executive' => [
            [
                'type' => 'servers',
                'cols' => 6,
            ],
            [
                'type' => 'chinook_sales_overview',
                'cols' => 6,
            ],
            [
                'type' => 'slow_requests',
                'cols' => 12,
            ],
        ],

        // Developer Dashboard for Chinook Development Team
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
                'type' => 'cache',
                'cols' => 4,
            ],
            [
                'type' => 'queues',
                'cols' => 8,
            ],
        ],

        // Operations Dashboard for Chinook Infrastructure
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
                'type' => 'slow_requests',
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

        // Music Analytics Dashboard for Chinook Business Intelligence
        'music_analytics' => [
            [
                'type' => 'chinook_genre_popularity',
                'cols' => 6,
            ],
            [
                'type' => 'chinook_artist_performance',
                'cols' => 6,
            ],
            [
                'type' => 'chinook_customer_activity',
                'cols' => 12,
            ],
        ],
    ],
],
```

**Route Configuration for Multiple Chinook Dashboards:**

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

    // Music analytics dashboard
    Route::get('/pulse/music-analytics', function () {
        return Pulse::dashboard('music_analytics');
    })->name('pulse.music-analytics');
});
```

### 1.3.3. Authentication & Authorization

Configure secure access to Pulse dashboards using spatie/laravel-permission:

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
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager', 'Editor']);
        });

        // Role-based dashboard access for Chinook
        Gate::define('viewPulse', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager', 'Editor']);
        });

        Gate::define('viewExecutivePulse', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin']);
        });

        Gate::define('viewDeveloperPulse', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
        });

        Gate::define('viewOperationsPulse', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
        });

        Gate::define('viewMusicAnalyticsPulse', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager', 'Editor']);
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

Configure built-in recorders for comprehensive Chinook monitoring:

```php
// config/pulse.php
'recorders' => [
    // Server metrics for Chinook infrastructure
    \Laravel\Pulse\Recorders\Servers::class => [
        'server_name' => env('PULSE_SERVER_NAME', 'chinook-' . gethostname()),
        'directories' => [
            '/' => 'Root',
            '/var/log' => 'Logs',
            '/tmp' => 'Temporary',
            '/storage/app/chinook' => 'Chinook Data',
        ],
    ],

    // HTTP request monitoring for Chinook APIs
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

    // Database query performance for Chinook operations
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => true,
        'threshold' => 500, // milliseconds
        'sample_rate' => 1,
        'location' => true,
        'max_query_length' => 1000,
    ],

    // Queue job monitoring for Chinook background tasks
    \Laravel\Pulse\Recorders\Queues::class => [
        'enabled' => true,
        'sample_rate' => 1,
        'ignore' => [
            'App\\Jobs\\ChinookInternalCleanupJob',
        ],
    ],

    // Cache performance for taxonomy and music data
    \Laravel\Pulse\Recorders\CacheInteractions::class => [
        'enabled' => true,
        'sample_rate' => 0.1, // Sample 10% for high-traffic apps
    ],

    // Exception tracking for Chinook operations
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

Create custom recorders for Chinook business-specific metrics:

```php
// app/Pulse/Recorders/ChinookActivityRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\Event;
use App\Events\ChinookCustomerLoggedIn;
use App\Events\ChinookCustomerRegistered;
use App\Events\ChinookPurchaseCompleted;
use App\Events\ChinookPlaylistCreated;

class ChinookActivityRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Track customer logins
        Event::listen(ChinookCustomerLoggedIn::class, function (ChinookCustomerLoggedIn $event) use ($record) {
            $record('chinook_customer_login', [
                'customer_id' => $event->customer->public_id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'country' => $event->customer->country,
            ]);
        });

        // Track customer registrations
        Event::listen(ChinookCustomerRegistered::class, function (ChinookCustomerRegistered $event) use ($record) {
            $record('chinook_customer_registration', [
                'customer_id' => $event->customer->public_id,
                'source' => $event->source ?? 'direct',
                'country' => $event->customer->country,
            ]);
        });

        // Track music purchases
        Event::listen(ChinookPurchaseCompleted::class, function (ChinookPurchaseCompleted $event) use ($record) {
            $record('chinook_purchase_completed', [
                'customer_id' => $event->invoice->customer->public_id,
                'total' => $event->invoice->total,
                'tracks_count' => $event->invoice->invoiceLines->count(),
                'genres' => $event->invoice->invoiceLines->pluck('track.genre.name')->unique()->values(),
            ]);
        });

        // Track playlist creation
        Event::listen(ChinookPlaylistCreated::class, function (ChinookPlaylistCreated $event) use ($record) {
            $record('chinook_playlist_created', [
                'customer_id' => $event->playlist->customer->public_id,
                'playlist_id' => $event->playlist->public_id,
                'tracks_count' => $event->playlist->tracks->count(),
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

    \App\Pulse\Recorders\ChinookActivityRecorder::class => [
        'enabled' => env('PULSE_CHINOOK_ACTIVITY_ENABLED', true),
        'sample_rate' => env('PULSE_CHINOOK_ACTIVITY_SAMPLE_RATE', 1),
    ],
],
```

### 1.4.3. Performance Monitoring

Configure advanced performance monitoring for Chinook operations:

```php
// app/Pulse/Recorders/ChinookPerformanceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChinookPerformanceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor response times by route for Chinook APIs
        app('events')->listen('kernel.handled', function ($request, $response) use ($record) {
            if ($request instanceof Request && $response instanceof Response) {
                $startTime = defined('LARAVEL_START') ? LARAVEL_START : request()->server('REQUEST_TIME_FLOAT');
                $duration = (microtime(true) - $startTime) * 1000;

                $record('chinook_response_time', [
                    'route' => $request->route()?->getName() ?? 'unknown',
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'duration' => $duration,
                    'is_api' => str_starts_with($request->path(), 'api/'),
                ]);
            }
        });

        // Monitor memory usage for Chinook operations
        register_shutdown_function(function () use ($record) {
            $record('chinook_memory_usage', [
                'peak_memory' => memory_get_peak_usage(true),
                'current_memory' => memory_get_usage(true),
            ]);
        });

        // Monitor taxonomy operations performance
        Event::listen('taxonomy.queried', function ($event) use ($record) {
            $record('chinook_taxonomy_performance', [
                'operation' => $event->operation,
                'duration' => $event->duration,
                'results_count' => $event->resultsCount,
            ]);
        });
    }
}
```

## 1.5. Custom Metrics & Cards

### 1.5.1. Creating Custom Recorders

Build specialized recorders for Chinook business metrics:

```php
// app/Pulse/Recorders/ChinookBusinessMetricsRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use App\Models\ChinookInvoice;
use App\Models\ChinookCustomer;
use App\Models\ChinookTrack;
use Illuminate\Support\Facades\DB;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

class ChinookBusinessMetricsRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Record daily revenue
        $this->recordDailyRevenue($record);

        // Record active customers
        $this->recordActiveCustomers($record);

        // Record genre popularity
        $this->recordGenrePopularity($record);

        // Record taxonomy usage
        $this->recordTaxonomyUsage($record);
    }

    private function recordDailyRevenue(callable $record): void
    {
        $revenue = ChinookInvoice::whereDate('invoice_date', today())
            ->sum('total');

        $record('chinook_daily_revenue', [
            'amount' => $revenue,
            'currency' => 'USD',
            'date' => today()->toDateString(),
        ]);
    }

    private function recordActiveCustomers(callable $record): void
    {
        $activeCustomers = ChinookCustomer::where('last_activity_at', '>=', now()->subHours(24))
            ->count();

        $record('chinook_active_customers_24h', [
            'count' => $activeCustomers,
            'timestamp' => now()->toISOString(),
        ]);
    }

    private function recordGenrePopularity(callable $record): void
    {
        $genreStats = DB::table('chinook_invoice_lines')
            ->join('chinook_tracks', 'chinook_invoice_lines.track_id', '=', 'chinook_tracks.id')
            ->join('chinook_genres', 'chinook_tracks.genre_id', '=', 'chinook_genres.id')
            ->whereDate('chinook_invoice_lines.created_at', today())
            ->groupBy('chinook_genres.name')
            ->selectRaw('chinook_genres.name, COUNT(*) as sales_count, SUM(chinook_invoice_lines.unit_price * chinook_invoice_lines.quantity) as revenue')
            ->get();

        foreach ($genreStats as $stat) {
            $record('chinook_genre_performance', [
                'genre' => $stat->name,
                'sales_count' => $stat->sales_count,
                'revenue' => $stat->revenue,
            ]);
        }
    }

    private function recordTaxonomyUsage(callable $record): void
    {
        $taxonomyUsage = Taxonomy::withCount('taggables')
            ->where('type', 'music_genre')
            ->get();

        foreach ($taxonomyUsage as $taxonomy) {
            $record('chinook_taxonomy_usage', [
                'taxonomy_name' => $taxonomy->name,
                'usage_count' => $taxonomy->taggables_count,
                'taxonomy_type' => $taxonomy->type,
            ]);
        }
    }
}
```

### 1.5.2. Building Custom Cards

Create custom dashboard cards for Chinook business metrics:

```php
// app/Pulse/Cards/ChinookRevenueCard.php
<?php

namespace App\Pulse\Cards;

use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Attributes\Lazy;

#[Lazy]
class ChinookRevenueCard extends Card
{
    public function render()
    {
        [$revenue, $time, $runAt] = $this->remember(
            fn () => Pulse::values('chinook_daily_revenue')
                ->map(fn ($entry) => [
                    'amount' => $entry->value,
                    'date' => $entry->key,
                ])
                ->take(30)
                ->values()
        );

        return view('pulse.chinook-revenue', [
            'revenue' => $revenue,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
```

**Chinook Revenue Card View:**

```blade
{{-- resources/views/pulse/chinook-revenue.blade.php --}}
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Chinook Daily Revenue">
        <x-slot:icon>
            <x-pulse::icons.currency-dollar />
        </x-slot:icon>
        <x-slot:actions>
            <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                Last updated {{ $runAt->diffForHumans() }}
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <div class="grid gap-3 mx-px mb-px">
        @if($revenue->isEmpty())
            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                No revenue data available
            </div>
        @else
            <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                ${{ number_format($revenue->first()['amount'], 2) }}
            </div>

            <div class="h-32">
                <canvas id="chinook-revenue-chart" class="w-full h-full"></canvas>
            </div>

            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <div class="text-gray-500 dark:text-gray-400">Today</div>
                    <div class="font-semibold">${{ number_format($revenue->first()['amount'] ?? 0, 2) }}</div>
                </div>
                <div>
                    <div class="text-gray-500 dark:text-gray-400">Yesterday</div>
                    <div class="font-semibold">${{ number_format($revenue->skip(1)->first()['amount'] ?? 0, 2) }}</div>
                </div>
                <div>
                    <div class="text-gray-500 dark:text-gray-400">7-day avg</div>
                    <div class="font-semibold">${{ number_format($revenue->take(7)->avg('amount'), 2) }}</div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('chinook-revenue-chart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($revenue->pluck('date')->reverse()),
                    datasets: [{
                        label: 'Revenue',
                        data: @json($revenue->pluck('amount')->reverse()),
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</x-pulse::card>
```

### 1.5.3. Business Metrics Integration

Integrate Chinook business metrics with existing systems using Laravel 12 patterns:

```php
// app/Pulse/Recorders/ChinookMusicAnalyticsRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use App\Events\ChinookTrackPlayed;
use App\Events\ChinookAlbumViewed;
use App\Events\ChinookArtistFollowed;
use App\Events\ChinookGenreFiltered;
use Illuminate\Support\Facades\Event;

class ChinookMusicAnalyticsRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Track music plays
        Event::listen(ChinookTrackPlayed::class, function (ChinookTrackPlayed $event) use ($record) {
            $record('chinook_track_play', [
                'track_id' => $event->track->public_id,
                'album_id' => $event->track->album->public_id,
                'artist_id' => $event->track->album->artist->public_id,
                'genre_id' => $event->track->genre->public_id,
                'customer_id' => auth()->id(),
                'session_id' => session()->getId(),
            ]);
        });

        // Track album views
        Event::listen(ChinookAlbumViewed::class, function (ChinookAlbumViewed $event) use ($record) {
            $record('chinook_album_view', [
                'album_id' => $event->album->public_id,
                'artist_id' => $event->album->artist->public_id,
                'customer_id' => auth()->id(),
                'source' => $event->source ?? 'direct',
            ]);
        });

        // Track artist follows
        Event::listen(ChinookArtistFollowed::class, function (ChinookArtistFollowed $event) use ($record) {
            $record('chinook_artist_follow', [
                'artist_id' => $event->artist->public_id,
                'customer_id' => $event->customer->public_id,
                'follow_type' => $event->followType,
            ]);
        });

        // Track genre filtering with taxonomy integration
        Event::listen(ChinookGenreFiltered::class, function (ChinookGenreFiltered $event) use ($record) {
            $record('chinook_genre_filter', [
                'taxonomy_id' => $event->taxonomy->id,
                'taxonomy_name' => $event->taxonomy->name,
                'filter_type' => $event->filterType,
                'results_count' => $event->resultsCount,
                'customer_id' => auth()->id(),
            ]);
        });
    }
}
```

## 1.6. Performance Optimization

### 1.6.1. Database Optimization

Optimize Pulse database performance for Chinook with SQLite WAL mode:

```php
// config/pulse.php
'storage' => [
    'driver' => env('PULSE_DB_CONNECTION', 'pulse'),
    'database' => env('PULSE_DB_DATABASE', 'chinook_pulse'),

    // Optimize for write-heavy workloads with SQLite WAL mode
    'options' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB
    ],
],

// Data retention settings for Chinook
'trim' => [
    'lottery' => [1, 1000],
    'keep' => '7 days',
],

// Sampling for high-traffic Chinook operations
'ingest' => [
    'driver' => env('PULSE_INGEST_DRIVER', 'redis'),
    'buffer' => 5000,
    'trim' => false,
],
```

**Database Indexing for Chinook Metrics:**

```sql
-- Add custom indexes for better Chinook performance
CREATE INDEX idx_pulse_entries_chinook_type_timestamp ON pulse_entries(type, timestamp) WHERE type LIKE 'chinook_%';
CREATE INDEX idx_pulse_aggregates_chinook_period_bucket ON pulse_aggregates(period, bucket) WHERE type LIKE 'chinook_%';
CREATE INDEX idx_pulse_values_chinook_timestamp ON pulse_values(timestamp) WHERE key LIKE 'chinook_%';
```

### 1.6.2. Caching Strategies

Implement caching for Chinook dashboard performance:

```php
// app/Pulse/Cards/CachedChinookMetricsCard.php
<?php

namespace App\Pulse\Cards;

use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Lazy;

#[Lazy]
class CachedChinookMetricsCard extends Card
{
    public function render()
    {
        $cacheKey = "chinook_pulse_metrics_{$this->cols}_{$this->rows}";

        [$metrics, $time, $runAt] = Cache::remember($cacheKey, 300, function () {
            return $this->remember(
                fn () => $this->getChinookMetrics()
            );
        });

        return view('pulse.cached-chinook-metrics', [
            'metrics' => $metrics,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    private function getChinookMetrics()
    {
        return Pulse::values('chinook_business_metrics')
            ->map(fn ($entry) => [
                'name' => $entry->key,
                'value' => $entry->value,
                'timestamp' => $entry->timestamp,
            ])
            ->take(100)
            ->values();
    }
}
```

### 1.6.3. Sampling Configuration

Configure intelligent sampling for high-traffic Chinook operations:

```php
// config/pulse.php
'recorders' => [
    \Laravel\Pulse\Recorders\Requests::class => [
        'sample_rate' => env('PULSE_REQUESTS_SAMPLE_RATE', 0.1), // 10% sampling
        'ignore' => [
            '#^/pulse#',
            '#^/api/health#',
            '#^/favicon.ico#',
            '#^/api/chinook/tracks/popular#', // High-traffic endpoint
        ],
    ],

    \Laravel\Pulse\Recorders\CacheInteractions::class => [
        'sample_rate' => env('PULSE_CACHE_SAMPLE_RATE', 0.01), // 1% sampling
    ],

    // Custom sampling based on environment for Chinook
    \App\Pulse\Recorders\ChinookActivityRecorder::class => [
        'sample_rate' => app()->environment('production') ? 0.1 : 1.0,
    ],

    \App\Pulse\Recorders\ChinookMusicAnalyticsRecorder::class => [
        'sample_rate' => app()->environment('production') ? 0.05 : 1.0, // 5% in production
    ],
],
```

## 1.7. Integration Strategies

### 1.7.1. Laravel Horizon Integration

Integrate Pulse with Laravel Horizon for comprehensive Chinook job monitoring:

```php
// app/Pulse/Recorders/ChinookHorizonRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Laravel\Horizon\Events\JobFailed;
use Laravel\Horizon\Events\JobProcessed;
use Laravel\Horizon\Events\WorkerTimeout;
use Illuminate\Support\Facades\Event;

class ChinookHorizonRecorder extends Recorder
{
    public function register(callable $record): void
    {
        Event::listen(JobProcessed::class, function (JobProcessed $event) use ($record) {
            $record('chinook_horizon_job_processed', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'duration' => $event->job->hasFailed() ? null : $event->job->payload()['timeout'] ?? null,
                'is_chinook_job' => str_contains($event->job->resolveName(), 'Chinook'),
            ]);
        });

        Event::listen(JobFailed::class, function (JobFailed $event) use ($record) {
            $record('chinook_horizon_job_failed', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception->getMessage(),
                'is_chinook_job' => str_contains($event->job->resolveName(), 'Chinook'),
            ]);
        });

        Event::listen(WorkerTimeout::class, function (WorkerTimeout $event) use ($record) {
            $record('chinook_horizon_worker_timeout', [
                'connection' => $event->connection,
                'queue' => $event->queue,
            ]);
        });
    }
}
```

### 1.7.2. External Monitoring Integration

Connect Pulse with external monitoring services for Chinook:

```php
// app/Pulse/Exporters/ChinookDatadogExporter.php
<?php

namespace App\Pulse\Exporters;

use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Http;

class ChinookDatadogExporter
{
    public function export(): void
    {
        $metrics = Pulse::values('chinook_business_metrics')
            ->take(100)
            ->map(function ($entry) {
                return [
                    'metric' => "chinook.{$entry->key}",
                    'points' => [[
                        'timestamp' => $entry->timestamp->timestamp,
                        'value' => $entry->value,
                    ]],
                    'tags' => [
                        'environment:' . app()->environment(),
                        'server:' . gethostname(),
                        'application:chinook',
                    ],
                ];
            });

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
        app(\App\Pulse\Exporters\ChinookDatadogExporter::class)->export();
    })->everyFiveMinutes();
}
```

### 1.7.3. Alert Integration

Set up alerting based on Chinook Pulse metrics:

```php
// app/Pulse/Alerts/ChinookMetricAlert.php
<?php

namespace App\Pulse\Alerts;

use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ChinookMetricThresholdExceeded;

class ChinookMetricAlert
{
    public function checkThresholds(): void
    {
        $this->checkErrorRate();
        $this->checkResponseTime();
        $this->checkSalesVolume();
        $this->checkCustomerActivity();
    }

    private function checkErrorRate(): void
    {
        $errorRate = Pulse::aggregate('exceptions', 'count')
            ->where('bucket', '>=', now()->subMinutes(5))
            ->sum('value');

        if ($errorRate > 10) {
            Notification::route('slack', config('chinook.alerts.slack_webhook'))
                ->notify(new ChinookMetricThresholdExceeded('Error Rate', $errorRate, 10));
        }
    }

    private function checkResponseTime(): void
    {
        $avgResponseTime = Pulse::aggregate('chinook_response_time', 'avg')
            ->where('bucket', '>=', now()->subMinutes(5))
            ->avg('value');

        if ($avgResponseTime > 2000) { // 2 seconds
            Notification::route('slack', config('chinook.alerts.slack_webhook'))
                ->notify(new ChinookMetricThresholdExceeded('Response Time', $avgResponseTime, 2000));
        }
    }

    private function checkSalesVolume(): void
    {
        $dailySales = Pulse::values('chinook_daily_revenue')
            ->where('timestamp', '>=', today())
            ->sum('value');

        $expectedMinimum = config('chinook.alerts.minimum_daily_sales', 1000);

        if (now()->hour >= 20 && $dailySales < $expectedMinimum) {
            Notification::route('slack', config('chinook.alerts.slack_webhook'))
                ->notify(new ChinookMetricThresholdExceeded('Daily Sales', $dailySales, $expectedMinimum));
        }
    }

    private function checkCustomerActivity(): void
    {
        $activeCustomers = Pulse::values('chinook_active_customers_24h')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->max('value');

        $minimumActive = config('chinook.alerts.minimum_active_customers', 50);

        if ($activeCustomers < $minimumActive) {
            Notification::route('slack', config('chinook.alerts.slack_webhook'))
                ->notify(new ChinookMetricThresholdExceeded('Active Customers', $activeCustomers, $minimumActive));
        }
    }
}
```

## 1.8. Best Practices

### 1.8.1. Data Retention Strategy

Implement intelligent data retention for Chinook metrics:

```php
// config/pulse.php
'trim' => [
    'lottery' => [1, 100], // More frequent trimming
    'keep' => env('PULSE_TRIM_KEEP', '7 days'),
],

// Custom trimming for different Chinook metric types
'custom_trim' => [
    'chinook_high_frequency_metrics' => '1 day',
    'chinook_business_metrics' => '30 days',
    'chinook_error_logs' => '14 days',
    'chinook_customer_activity' => '90 days',
],
```

### 1.8.2. Security Considerations

Secure Pulse data and access for Chinook:

```php
// app/Http/Middleware/ChinookPulseAccess.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ChinookPulseAccess
{
    public function handle(Request $request, Closure $next)
    {
        // IP whitelist for production Chinook environment
        if (app()->environment('production')) {
            $allowedIps = config('chinook.pulse.allowed_ips', []);
            if (!in_array($request->ip(), $allowedIps)) {
                abort(403, 'Access denied to Chinook monitoring');
            }
        }

        // Role-based access using spatie/laravel-permission
        if (!$request->user()?->hasAnyRole(['Super Admin', 'Admin', 'Manager'])) {
            abort(403, 'Insufficient permissions for Chinook monitoring');
        }

        return $next($request);
    }
}
```

### 1.8.3. Performance Monitoring

Monitor Pulse's own performance for Chinook:

```php
// app/Pulse/Recorders/ChinookPulsePerformanceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\DB;

class ChinookPulsePerformanceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor Chinook Pulse database size
        $this->monitorDatabaseSize($record);

        // Monitor ingestion performance
        $this->monitorIngestionPerformance($record);
    }

    private function monitorDatabaseSize(callable $record): void
    {
        $size = DB::connection('pulse')
            ->select("SELECT page_count * page_size as size FROM pragma_page_count(), pragma_page_size()")[0]->size ?? 0;

        $record('chinook_pulse_db_size', [
            'size_bytes' => $size,
            'size_mb' => round($size / 1024 / 1024, 2),
        ]);
    }

    private function monitorIngestionPerformance(callable $record): void
    {
        $startTime = microtime(true);

        // Simulate ingestion operation
        Pulse::record('chinook_test_metric', 1);

        $duration = (microtime(true) - $startTime) * 1000;

        $record('chinook_pulse_ingestion_time', [
            'duration_ms' => $duration,
        ]);
    }
}
```

## 1.9. Troubleshooting

### 1.9.1. Common Issues

**High Memory Usage in Chinook Environment:**

```php
// Optimize memory usage in config/pulse.php
'ingest' => [
    'driver' => 'redis',
    'buffer' => 1000, // Reduce buffer size
    'trim' => true,   // Enable automatic trimming
],

'trim' => [
    'lottery' => [1, 10], // More aggressive trimming
    'keep' => '3 days',   // Shorter retention for Chinook
],
```

**Slow Dashboard Loading for Chinook Metrics:**

```bash
# Add database indexes for Chinook
php artisan pulse:optimize

# Clear Chinook Pulse cache
php artisan pulse:clear

# Restart queue workers
php artisan queue:restart
```

### 1.9.2. Debug Mode

Enable debug mode for troubleshooting Chinook Pulse issues:

```bash
# Enable Chinook Pulse debugging
PULSE_DEBUG=true
PULSE_LOG_LEVEL=debug
CHINOOK_PULSE_VERBOSE=true

# Check Chinook Pulse status
php artisan pulse:check --verbose

# Monitor Chinook ingestion
php artisan pulse:work --verbose
```

### 1.9.3. Performance Tuning

Optimize Pulse for high-traffic Chinook applications:

```php
// config/pulse.php
'recorders' => [
    // Reduce sampling for high-traffic Chinook endpoints
    \Laravel\Pulse\Recorders\Requests::class => [
        'sample_rate' => 0.01, // 1% sampling
        'ignore' => [
            '#^/api/v1/chinook/tracks/popular#',
            '#^/api/v1/chinook/search#',
        ],
    ],

    // Disable expensive recorders in Chinook production
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => !app()->environment('production'),
    ],
],

// Use Redis for Chinook ingestion
'ingest' => [
    'driver' => 'redis',
    'connection' => 'chinook_pulse',
    'buffer' => 5000,
],
```

## 1.10. Navigation

**← Previous:** [Laravel Backup Guide](010-laravel-backup-guide.md)

**Next →** [Laravel Telescope Guide](030-laravel-telescope-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Pulse implementation guide provides comprehensive monitoring capabilities for the Chinook music store application, including:

- **Real-time Performance Monitoring**: Track API response times, database queries, and system resources
- **Business Intelligence**: Monitor sales metrics, customer activity, and music catalog performance
- **Taxonomy Integration**: Leverage aliziodev/laravel-taxonomy for genre and category monitoring
- **Role-based Access**: Secure dashboards using spatie/laravel-permission with hierarchical roles
- **SQLite Optimization**: WAL mode configuration for optimal performance with the Chinook database
- **Custom Analytics**: Track music plays, album views, playlist creation, and customer behavior
- **Alert Integration**: Proactive monitoring with threshold-based notifications for critical metrics

The implementation follows Laravel 12 modern patterns and integrates seamlessly with the Chinook application's existing architecture, providing actionable insights for business operations and technical performance optimization.
