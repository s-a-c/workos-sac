# Laravel Pulse Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. Database Configuration](#12-database-configuration)
  - [1.3. Environment Setup](#13-environment-setup)
- [Dashboard Configuration](#dashboard-configuration)
  - [2.1. Basic Dashboard Setup](#21-basic-dashboard-setup)
  - [2.2. Custom Dashboard Layouts](#22-custom-dashboard-layouts)
  - [2.3. Authentication & Authorization](#23-authentication--authorization)
- [Data Collection Setup](#data-collection-setup)
  - [3.1. Built-in Recorders](#31-built-in-recorders)
  - [3.2. Custom Metrics Collection](#32-custom-metrics-collection)
  - [3.3. Performance Monitoring](#33-performance-monitoring)
- [Custom Metrics & Cards](#custom-metrics--cards)
  - [4.1. Creating Custom Recorders](#41-creating-custom-recorders)
  - [4.2. Building Custom Cards](#42-building-custom-cards)
  - [4.3. Business Metrics Integration](#43-business-metrics-integration)
- [Performance Optimization](#performance-optimization)
  - [5.1. Database Optimization](#51-database-optimization)
  - [5.2. Caching Strategies](#52-caching-strategies)
  - [5.3. Sampling Configuration](#53-sampling-configuration)
- [Integration Strategies](#integration-strategies)
  - [6.1. Laravel Horizon Integration](#61-laravel-horizon-integration)
  - [6.2. External Monitoring Integration](#62-external-monitoring-integration)
  - [6.3. Alert Integration](#63-alert-integration)
- [Best Practices](#best-practices)
  - [7.1. Data Retention Strategy](#71-data-retention-strategy)
  - [7.2. Security Considerations](#72-security-considerations)
  - [7.3. Performance Monitoring](#73-performance-monitoring)
- [Troubleshooting](#troubleshooting)
  - [8.1. Common Issues](#81-common-issues)
  - [8.2. Debug Mode](#82-debug-mode)
  - [8.3. Performance Tuning](#83-performance-tuning)
- [Navigation](#navigation)

## Overview

Laravel Pulse provides real-time application monitoring with customizable dashboards and comprehensive metrics collection. This guide covers enterprise-level implementation with custom metrics, performance optimization, and integration with existing monitoring infrastructure.

**🚀 Key Features:**
- **Real-Time Dashboards**: Live performance metrics and application health monitoring
- **Custom Collectors**: Business-specific metrics and KPI tracking capabilities
- **Performance Monitoring**: Request tracking, database queries, and resource usage analysis
- **Alert Integration**: Threshold-based alerting with multiple notification channels
- **Team Collaboration**: Shared dashboards and metric visibility across teams
- **Historical Analysis**: Trend analysis and performance optimization insights

## Installation & Setup

### 1.1. Package Installation

Install Laravel Pulse using Composer:

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

### 1.2. Database Configuration

Configure database settings for optimal Pulse performance:

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

**Dedicated Database Connection:**

```php
// config/database.php
'connections' => [
    'pulse' => [
        'driver' => 'sqlite',
        'database' => database_path('pulse.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
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

### 1.3. Environment Setup

Configure environment variables for Pulse:

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
```

## Dashboard Configuration

### 2.1. Basic Dashboard Setup

Configure the basic Pulse dashboard:

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
    
    // Database queries
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
],
```

### 2.2. Custom Dashboard Layouts

Create custom dashboard layouts for different teams:

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
                'type' => 'slow_requests',
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
                'type' => 'cache',
                'cols' => 4,
            ],
            [
                'type' => 'queues',
                'cols' => 8,
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

### 2.3. Authentication & Authorization

Configure secure access to Pulse dashboards:

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
            return $user->hasRole(['admin', 'developer', 'operations']);
        });
        
        // Role-based dashboard access
        Gate::define('viewPulse', function ($user) {
            return $user->hasAnyRole(['admin', 'developer', 'operations']);
        });
        
        Gate::define('viewExecutivePulse', function ($user) {
            return $user->hasRole(['admin', 'executive']);
        });
        
        Gate::define('viewDeveloperPulse', function ($user) {
            return $user->hasRole(['admin', 'developer']);
        });
        
        Gate::define('viewOperationsPulse', function ($user) {
            return $user->hasRole(['admin', 'operations']);
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

## Data Collection Setup

### 3.1. Built-in Recorders

Configure built-in recorders for comprehensive monitoring:

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
    
    // Database query performance
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

### 3.2. Custom Metrics Collection

Create custom recorders for business-specific metrics:

```php
// app/Pulse/Recorders/UserActivityRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\Event;
use App\Events\UserLoggedIn;
use App\Events\UserRegistered;
use App\Events\PurchaseCompleted;

class UserActivityRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Track user logins
        Event::listen(UserLoggedIn::class, function (UserLoggedIn $event) use ($record) {
            $record('user_login', [
                'user_id' => $event->user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
        
        // Track user registrations
        Event::listen(UserRegistered::class, function (UserRegistered $event) use ($record) {
            $record('user_registration', [
                'user_id' => $event->user->id,
                'source' => $event->source ?? 'direct',
            ]);
        });
        
        // Track purchases
        Event::listen(PurchaseCompleted::class, function (PurchaseCompleted $event) use ($record) {
            $record('purchase_completed', [
                'user_id' => $event->purchase->user_id,
                'amount' => $event->purchase->total,
                'currency' => $event->purchase->currency,
                'items_count' => $event->purchase->items->count(),
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
    
    \App\Pulse\Recorders\UserActivityRecorder::class => [
        'enabled' => env('PULSE_USER_ACTIVITY_ENABLED', true),
        'sample_rate' => env('PULSE_USER_ACTIVITY_SAMPLE_RATE', 1),
    ],
],
```

### 3.3. Performance Monitoring

Configure advanced performance monitoring:

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
    }
}
```

## Custom Metrics & Cards

### 4.1. Creating Custom Recorders

Build specialized recorders for business metrics:

```php
// app/Pulse/Recorders/BusinessMetricsRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessMetricsRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Record daily revenue
        $this->recordDailyRevenue($record);

        // Record active users
        $this->recordActiveUsers($record);

        // Record conversion rates
        $this->recordConversionRates($record);
    }

    private function recordDailyRevenue(callable $record): void
    {
        $revenue = Order::whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total');

        $record('daily_revenue', [
            'amount' => $revenue,
            'currency' => 'USD',
            'date' => today()->toDateString(),
        ]);
    }

    private function recordActiveUsers(callable $record): void
    {
        $activeUsers = User::where('last_activity_at', '>=', now()->subHours(24))
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

        $conversions = Order::whereDate('created_at', today())
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

### 4.2. Building Custom Cards

Create custom dashboard cards for business metrics:

```php
// app/Pulse/Cards/RevenueCard.php
<?php

namespace App\Pulse\Cards;

use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Livewire\Attributes\Lazy;

#[Lazy]
class RevenueCard extends Card
{
    public function render()
    {
        [$revenue, $time, $runAt] = $this->remember(
            fn () => Pulse::values('daily_revenue')
                ->map(fn ($entry) => [
                    'amount' => $entry->value,
                    'date' => $entry->key,
                ])
                ->take(30)
                ->values()
        );

        return view('pulse.revenue', [
            'revenue' => $revenue,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }
}
```

**Revenue Card View:**

```blade
{{-- resources/views/pulse/revenue.blade.php --}}
<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header name="Daily Revenue">
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
                <canvas id="revenue-chart" class="w-full h-full"></canvas>
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
            const ctx = document.getElementById('revenue-chart').getContext('2d');
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

### 4.3. Business Metrics Integration

Integrate business metrics with existing systems:

```php
// app/Pulse/Recorders/EcommerceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use App\Events\OrderCreated;
use App\Events\ProductViewed;
use App\Events\CartAbandoned;

class EcommerceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Track product views
        Event::listen(ProductViewed::class, function (ProductViewed $event) use ($record) {
            $record('product_view', [
                'product_id' => $event->product->id,
                'category' => $event->product->category->name,
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ]);
        });

        // Track order creation
        Event::listen(OrderCreated::class, function (OrderCreated $event) use ($record) {
            $record('order_created', [
                'order_id' => $event->order->id,
                'total' => $event->order->total,
                'items_count' => $event->order->items->count(),
                'customer_type' => $event->order->user->customer_type,
            ]);
        });

        // Track cart abandonment
        Event::listen(CartAbandoned::class, function (CartAbandoned $event) use ($record) {
            $record('cart_abandoned', [
                'cart_value' => $event->cart->total,
                'items_count' => $event->cart->items->count(),
                'session_duration' => $event->sessionDuration,
            ]);
        });
    }
}
```

## Performance Optimization

### 5.1. Database Optimization

Optimize Pulse database performance:

```php
// config/pulse.php
'storage' => [
    'driver' => env('PULSE_DB_CONNECTION', 'pulse'),
    'database' => env('PULSE_DB_DATABASE', 'pulse'),

    // Optimize for write-heavy workloads
    'options' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
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

**Database Indexing:**

```sql
-- Add custom indexes for better performance
CREATE INDEX idx_pulse_entries_type_timestamp ON pulse_entries(type, timestamp);
CREATE INDEX idx_pulse_aggregates_period_bucket ON pulse_aggregates(period, bucket);
CREATE INDEX idx_pulse_values_timestamp ON pulse_values(timestamp);
```

### 5.2. Caching Strategies

Implement caching for dashboard performance:

```php
// app/Pulse/Cards/CachedMetricsCard.php
<?php

namespace App\Pulse\Cards;

use Laravel\Pulse\Livewire\Card;
use Laravel\Pulse\Facades\Pulse;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Lazy;

#[Lazy]
class CachedMetricsCard extends Card
{
    public function render()
    {
        $cacheKey = "pulse_metrics_{$this->cols}_{$this->rows}";

        [$metrics, $time, $runAt] = Cache::remember($cacheKey, 300, function () {
            return $this->remember(
                fn () => $this->getMetrics()
            );
        });

        return view('pulse.cached-metrics', [
            'metrics' => $metrics,
            'time' => $time,
            'runAt' => $runAt,
        ]);
    }

    private function getMetrics()
    {
        return Pulse::values('business_metrics')
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

### 5.3. Sampling Configuration

Configure intelligent sampling for high-traffic applications:

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

    // Custom sampling based on environment
    \App\Pulse\Recorders\UserActivityRecorder::class => [
        'sample_rate' => app()->environment('production') ? 0.1 : 1.0,
    ],
],
```

## Integration Strategies

### 6.1. Laravel Horizon Integration

Integrate Pulse with Laravel Horizon for comprehensive monitoring:

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
            ]);
        });

        Event::listen(JobFailed::class, function (JobFailed $event) use ($record) {
            $record('horizon_job_failed', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception->getMessage(),
            ]);
        });

        Event::listen(WorkerTimeout::class, function (WorkerTimeout $event) use ($record) {
            $record('horizon_worker_timeout', [
                'connection' => $event->connection,
                'queue' => $event->queue,
            ]);
        });
    }
}
```

### 6.2. External Monitoring Integration

Connect Pulse with external monitoring services:

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

### 6.3. Alert Integration

Set up alerting based on Pulse metrics:

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
}
```

## Best Practices

### 7.1. Data Retention Strategy

Implement intelligent data retention:

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
],
```

### 7.2. Security Considerations

Secure Pulse data and access:

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
            if (!in_array($request->ip(), $allowedIps)) {
                abort(403, 'Access denied');
            }
        }

        // Role-based access
        if (!$request->user()?->hasRole(['admin', 'developer'])) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}
```

### 7.3. Performance Monitoring

Monitor Pulse's own performance:

```php
// app/Pulse/Recorders/PulsePerformanceRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;

class PulsePerformanceRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Monitor Pulse database size
        $this->monitorDatabaseSize($record);

        // Monitor ingestion performance
        $this->monitorIngestionPerformance($record);
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
}
```

## Troubleshooting

### 8.1. Common Issues

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

### 8.2. Debug Mode

Enable debug mode for troubleshooting:

```bash
# Enable Pulse debugging
PULSE_DEBUG=true
PULSE_LOG_LEVEL=debug

# Check Pulse status
php artisan pulse:check --verbose

# Monitor ingestion
php artisan pulse:work --verbose
```

### 8.3. Performance Tuning

Optimize Pulse for high-traffic applications:

```php
// config/pulse.php
'recorders' => [
    // Reduce sampling for high-traffic endpoints
    \Laravel\Pulse\Recorders\Requests::class => [
        'sample_rate' => 0.01, // 1% sampling
        'ignore' => [
            '#^/api/v1/high-traffic#',
        ],
    ],

    // Disable expensive recorders in production
    \Laravel\Pulse\Recorders\SlowQueries::class => [
        'enabled' => !app()->environment('production'),
    ],
],

// Use Redis for ingestion
'ingest' => [
    'driver' => 'redis',
    'connection' => 'pulse',
    'buffer' => 5000,
],
```

---

## Navigation

**← Previous:** [Laravel Backup Guide](010-laravel-backup-guide.md)

**Next →** [Laravel Telescope Guide](030-laravel-telescope-guide.md)
