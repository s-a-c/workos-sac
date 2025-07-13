# 1. Laravel Telescope Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/030-laravel-telescope-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Telescope Implementation Guide](#1-laravel-telescope-implementation-guide)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. Configuration Publishing](#122-configuration-publishing)
    - [1.2.3. Environment Configuration](#123-environment-configuration)
  - [1.3. Authorization & Security](#13-authorization--security)
    - [1.3.1. Gate-Based Authorization](#131-gate-based-authorization)
    - [1.3.2. Environment-Specific Access](#132-environment-specific-access)
    - [1.3.3. IP Whitelisting](#133-ip-whitelisting)
  - [1.4. Data Collection Configuration](#14-data-collection-configuration)
    - [1.4.1. Watcher Configuration](#141-watcher-configuration)
    - [1.4.2. Filtering & Sampling](#142-filtering--sampling)
    - [1.4.3. Performance Impact Mitigation](#143-performance-impact-mitigation)
  - [1.5. Data Pruning & Storage Management](#15-data-pruning--storage-management)
    - [1.5.1. Automated Pruning](#151-automated-pruning)
    - [1.5.2. Storage Optimization](#152-storage-optimization)
    - [1.5.3. Custom Retention Policies](#153-custom-retention-policies)
  - [1.6. Debugging Workflows](#16-debugging-workflows)
    - [1.6.1. Request Debugging](#161-request-debugging)
    - [1.6.2. Database Query Analysis](#162-database-query-analysis)
    - [1.6.3. Exception Analysis](#163-exception-analysis)
  - [1.7. Production Considerations](#17-production-considerations)
    - [1.7.1. Performance Impact Assessment](#171-performance-impact-assessment)
    - [1.7.2. Security Hardening](#172-security-hardening)
    - [1.7.3. Data Sanitization](#173-data-sanitization)
  - [1.8. Integration Strategies](#18-integration-strategies)
    - [1.8.1. Laravel Pulse Integration](#181-laravel-pulse-integration)
    - [1.8.2. External Monitoring Integration](#182-external-monitoring-integration)
  - [1.9. Best Practices](#19-best-practices)
    - [1.9.1. Development Workflow](#191-development-workflow)
    - [1.9.2. Team Collaboration](#192-team-collaboration)
    - [1.9.3. Performance Monitoring](#193-performance-monitoring)
  - [1.10. Troubleshooting](#110-troubleshooting)
    - [1.10.1. Common Issues](#1101-common-issues)
    - [1.10.2. Debug Mode](#1102-debug-mode)
    - [1.10.3. Data Recovery](#1103-data-recovery)
  - [1.11. Navigation](#111-navigation)

## 1.1. Overview

Laravel Telescope provides comprehensive debugging and application inspection capabilities with detailed request tracking, database query monitoring, and performance analysis. This guide covers enterprise-level implementation with security considerations, performance optimization, and production deployment strategies for the Chinook music store application.

**🚀 Key Features:**
- **Request Inspection**: Detailed request/response analysis and debugging capabilities for Chinook APIs
- **Database Query Monitoring**: Query performance analysis and optimization insights for music catalog operations
- **Job & Queue Tracking**: Background job monitoring and failure analysis for Chinook processing tasks
- **Exception Tracking**: Comprehensive error monitoring and debugging for customer-facing operations
- **Cache Monitoring**: Cache performance analysis for taxonomy and music data optimization
- **Mail Tracking**: Email debugging for customer notifications and marketing campaigns
- **Event Monitoring**: Real-time event tracking for business logic and user interactions
- **Taxonomy Integration**: Monitor taxonomy operations and performance using aliziodev/laravel-taxonomy

**🎵 Chinook-Specific Debugging:**
- Track music catalog API performance and database queries
- Monitor customer authentication and purchase workflows
- Analyze playlist creation and sharing operations
- Debug taxonomy-based filtering and search functionality
- Monitor invoice generation and payment processing
- Track artist and album metadata operations

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Telescope for the Chinook application:

```bash
# Install Laravel Telescope
composer require laravel/telescope

# Publish Telescope assets and configuration
php artisan telescope:install

# Run migrations to create telescope tables
php artisan migrate
```

**Verification Steps:**

```bash
# Verify Telescope installation
php artisan telescope:status

# Expected output:
# ✓ Telescope is installed and configured
# ✓ Database tables are present
# ✓ Watchers are configured
```

### 1.2.2. Configuration Publishing

Configure Telescope for optimal Chinook debugging:

```php
// config/telescope.php
return [
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    
    'storage' => [
        'database' => [
            'connection' => env('TELESCOPE_DB_CONNECTION', 'telescope'),
            'chunk' => 1000,
        ],
    ],
    
    'enabled' => env('TELESCOPE_ENABLED', true),
    
    'middleware' => [
        'web',
        'auth',
        Authorize::class,
    ],
    
    'only_paths' => [
        // Monitor specific Chinook paths
        'api/chinook/*',
        'chinook/*',
        'admin/chinook/*',
    ],
    
    'ignore_paths' => [
        'telescope*',
        'vendor/telescope*',
        'nova-api*',
    ],
    
    'ignore_commands' => [
        'telescope:prune',
        'telescope:clear',
    ],
    
    'watchers' => [
        // Watcher configuration
    ],
];
```

**Dedicated Database Connection for Chinook Telescope:**

```php
// config/database.php
'connections' => [
    'telescope' => [
        'driver' => 'sqlite',
        'database' => database_path('chinook_telescope.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
    ],
    
    // Alternative: MySQL for production Chinook environment
    'telescope_mysql' => [
        'driver' => 'mysql',
        'host' => env('TELESCOPE_DB_HOST', '127.0.0.1'),
        'port' => env('TELESCOPE_DB_PORT', '3306'),
        'database' => env('TELESCOPE_DB_DATABASE', 'chinook_telescope'),
        'username' => env('TELESCOPE_DB_USERNAME', 'forge'),
        'password' => env('TELESCOPE_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

### 1.2.3. Environment Configuration

Configure environment variables for Chinook Telescope:

```bash
# .env configuration
TELESCOPE_ENABLED=true
TELESCOPE_DOMAIN=null
TELESCOPE_PATH=telescope

# Database configuration
TELESCOPE_DB_CONNECTION=telescope
TELESCOPE_DB_DATABASE=chinook_telescope
TELESCOPE_DRIVER=database

# Performance settings
TELESCOPE_QUEUE_WATCHER=true
TELESCOPE_CACHE_WATCHER=true
TELESCOPE_MAIL_WATCHER=true

# Security settings
TELESCOPE_MIDDLEWARE=web,auth,can:viewTelescope

# Chinook-specific settings
TELESCOPE_CHINOOK_MONITORING=true
TELESCOPE_TAXONOMY_DEBUGGING=true
TELESCOPE_MUSIC_API_TRACKING=true
```

## 1.3. Authorization & Security

### 1.3.1. Gate-Based Authorization

Configure secure access to Telescope using spatie/laravel-permission:

```php
// app/Providers/TelescopeServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(function ($request) {
            return Gate::check('viewTelescope', [$request->user()]);
        });
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
        });
    }
}
```

### 1.3.2. Environment-Specific Access

Configure environment-specific access controls for Chinook:

```php
// app/Http/Middleware/ChinookTelescopeAccess.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ChinookTelescopeAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Disable in production unless explicitly enabled
        if (app()->environment('production') && !config('telescope.enabled')) {
            abort(404);
        }

        // Role-based access for Chinook team members
        if (!$request->user()?->hasAnyRole(['Super Admin', 'Admin', 'Manager'])) {
            abort(403, 'Insufficient permissions for Chinook debugging tools');
        }

        // Additional security checks for sensitive Chinook data
        if ($this->containsSensitiveChinookData($request)) {
            if (!$request->user()?->hasRole('Super Admin')) {
                abort(403, 'Super Admin access required for sensitive Chinook data');
            }
        }

        return $next($request);
    }

    private function containsSensitiveChinookData(Request $request): bool
    {
        $sensitivePatterns = [
            '/telescope/requests.*customer.*payment',
            '/telescope/requests.*invoice.*total',
            '/telescope/queries.*customer_payment_methods',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $request->getPathInfo())) {
                return true;
            }
        }

        return false;
    }
}
```

### 1.3.3. IP Whitelisting

Implement IP whitelisting for production Chinook environment:

```php
// config/telescope.php
'middleware' => [
    'web',
    'auth',
    \App\Http\Middleware\ChinookTelescopeAccess::class,
    function ($request, $next) {
        if (app()->environment('production')) {
            $allowedIps = config('chinook.telescope.allowed_ips', []);
            if (!in_array($request->ip(), $allowedIps)) {
                abort(403, 'IP not whitelisted for Chinook Telescope access');
            }
        }
        return $next($request);
    },
],
```

## 1.4. Data Collection Configuration

### 1.4.1. Watcher Configuration

Configure watchers for comprehensive Chinook monitoring:

```php
// config/telescope.php
'watchers' => [
    // Request monitoring for Chinook APIs
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
    ],

    // Database query monitoring for Chinook operations
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'ignore_packages' => true,
        'slow' => env('TELESCOPE_SLOW_QUERY_THRESHOLD', 100),
    ],

    // Job monitoring for Chinook background tasks
    Watchers\JobWatcher::class => [
        'enabled' => env('TELESCOPE_JOB_WATCHER', true),
    ],

    // Exception tracking for Chinook errors
    Watchers\ExceptionWatcher::class => [
        'enabled' => env('TELESCOPE_EXCEPTION_WATCHER', true),
    ],

    // Cache monitoring for taxonomy and music data
    Watchers\CacheWatcher::class => [
        'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
    ],

    // Mail tracking for customer communications
    Watchers\MailWatcher::class => [
        'enabled' => env('TELESCOPE_MAIL_WATCHER', true),
    ],

    // Event monitoring for Chinook business logic
    Watchers\EventWatcher::class => [
        'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
        'ignore' => [
            // Events to ignore
        ],
    ],

    // Model monitoring for Chinook entities
    Watchers\ModelWatcher::class => [
        'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
        'hydrations' => true,
    ],

    // Redis monitoring for Chinook caching
    Watchers\RedisWatcher::class => [
        'enabled' => env('TELESCOPE_REDIS_WATCHER', true),
    ],

    // Schedule monitoring for Chinook automated tasks
    Watchers\ScheduleWatcher::class => [
        'enabled' => env('TELESCOPE_SCHEDULE_WATCHER', true),
    ],
],
```

### 1.4.2. Filtering & Sampling

Configure intelligent filtering and sampling for Chinook operations:

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    Telescope::filter(function (IncomingEntry $entry) {
        // Always record in local environment
        if ($this->app->environment('local')) {
            return true;
        }

        // Production filtering for Chinook
        if ($this->app->environment('production')) {
            return $this->shouldRecordInProduction($entry);
        }

        return $entry->isReportableException() ||
               $entry->isFailedRequest() ||
               $entry->isFailedJob() ||
               $entry->hasMonitoredTag();
    });

    // Tag important Chinook operations
    Telescope::tag(function (IncomingEntry $entry) {
        $tags = [];

        // Tag Chinook-specific operations
        if ($entry->type === 'request') {
            if (str_contains($entry->content['uri'], '/api/chinook/')) {
                $tags[] = 'chinook-api';
            }
            if (str_contains($entry->content['uri'], '/chinook/purchase')) {
                $tags[] = 'chinook-purchase';
            }
            if (str_contains($entry->content['uri'], '/chinook/playlist')) {
                $tags[] = 'chinook-playlist';
            }
        }

        // Tag taxonomy operations
        if ($entry->type === 'query') {
            if (str_contains($entry->content['sql'], 'taxonomies')) {
                $tags[] = 'taxonomy-query';
            }
        }

        // Tag Chinook models
        if ($entry->type === 'model' && str_contains($entry->content['model'], 'Chinook')) {
            $tags[] = 'chinook-model';
        }

        return $tags;
    });
}

private function shouldRecordInProduction(IncomingEntry $entry): bool
{
    // Always record exceptions and failures
    if ($entry->isReportableException() || $entry->isFailedRequest() || $entry->isFailedJob()) {
        return true;
    }

    // Record slow queries for Chinook optimization
    if ($entry->type === 'query' && $entry->content['time'] > 100) {
        return true;
    }

    // Record Chinook API requests with sampling
    if ($entry->type === 'request' && str_contains($entry->content['uri'], '/api/chinook/')) {
        return rand(1, 100) <= 10; // 10% sampling
    }

    // Record purchase-related operations
    if ($entry->hasMonitoredTag() && in_array('chinook-purchase', $entry->tags)) {
        return true;
    }

    return false;
}
```

### 1.4.3. Performance Impact Mitigation

Optimize Telescope performance for high-traffic Chinook operations:

```php
// config/telescope.php
'watchers' => [
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => 32, // Reduced for Chinook production
        'ignore_http_methods' => ['OPTIONS'],
        'ignore_status_codes' => [301, 302, 304],
    ],

    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'ignore_packages' => true,
        'slow' => 50, // Lower threshold for Chinook optimization
        'location' => false, // Disable in production for performance
    ],

    Watchers\CacheWatcher::class => [
        'enabled' => env('TELESCOPE_CACHE_WATCHER', false), // Disabled in production
    ],

    Watchers\ModelWatcher::class => [
        'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
        'hydrations' => false, // Disabled for Chinook performance
    ],
],

// Batch processing for Chinook
'storage' => [
    'database' => [
        'connection' => env('TELESCOPE_DB_CONNECTION', 'telescope'),
        'chunk' => 500, // Smaller chunks for Chinook
    ],
],
```

## 1.5. Data Pruning & Storage Management

### 1.5.1. Automated Pruning

Configure automated pruning for Chinook Telescope data:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Daily pruning for Chinook Telescope data
    $schedule->command('telescope:prune --hours=48')
        ->daily()
        ->at('02:00')
        ->description('Prune Chinook Telescope data older than 48 hours');

    // Weekly deep pruning for production
    if (app()->environment('production')) {
        $schedule->command('telescope:prune --hours=24')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->description('Weekly deep prune of Chinook Telescope data');
    }

    // Clear Telescope cache
    $schedule->command('telescope:clear')
        ->weekly()
        ->description('Clear Chinook Telescope cache');
}
```

**Manual Pruning Commands:**

```bash
# Prune Chinook Telescope data older than 24 hours
php artisan telescope:prune --hours=24

# Prune specific entry types
php artisan telescope:prune --type=request --hours=12
php artisan telescope:prune --type=query --hours=6

# Clear all Telescope data
php artisan telescope:clear
```

### 1.5.2. Storage Optimization

Optimize storage for Chinook Telescope data:

```php
// config/telescope.php
'storage' => [
    'database' => [
        'connection' => env('TELESCOPE_DB_CONNECTION', 'telescope'),
        'chunk' => 500,
        'prune' => [
            'hours' => env('TELESCOPE_PRUNE_HOURS', 48),
            'lottery' => [1, 100], // 1% chance to prune on each request
        ],
    ],
],

// Custom storage driver for Chinook
'drivers' => [
    'chinook_optimized' => [
        'driver' => 'database',
        'connection' => 'telescope',
        'chunk' => 250,
        'compress' => true,
        'encrypt_sensitive' => true,
    ],
],
```

**Database Optimization for Chinook:**

```sql
-- Add indexes for better Chinook Telescope performance
CREATE INDEX idx_telescope_entries_chinook_type_created ON telescope_entries(type, created_at) WHERE content LIKE '%chinook%';
CREATE INDEX idx_telescope_entries_tags ON telescope_entries USING GIN(tags) WHERE tags IS NOT NULL;
CREATE INDEX idx_telescope_entries_batch_id ON telescope_entries(batch_id) WHERE batch_id IS NOT NULL;
```

### 1.5.3. Custom Retention Policies

Implement custom retention policies for different Chinook data types:

```php
// app/Console/Commands/ChinookTelescopePrune.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

class ChinookTelescopePrune extends Command
{
    protected $signature = 'chinook:telescope-prune';
    protected $description = 'Prune Chinook Telescope data with custom retention policies';

    public function handle(): void
    {
        $repository = app(DatabaseEntriesRepository::class);

        // Different retention periods for different Chinook data types
        $retentionPolicies = [
            'request' => now()->subHours(24), // Keep requests for 24 hours
            'query' => now()->subHours(12),   // Keep queries for 12 hours
            'exception' => now()->subDays(7), // Keep exceptions for 7 days
            'job' => now()->subDays(3),       // Keep jobs for 3 days
            'mail' => now()->subDays(30),     // Keep mail for 30 days
        ];

        foreach ($retentionPolicies as $type => $cutoff) {
            $deleted = $repository->prune($type, $cutoff);
            $this->info("Pruned {$deleted} {$type} entries older than {$cutoff->diffForHumans()}");
        }

        // Special handling for Chinook purchase data (keep longer)
        $purchaseEntries = $repository->prune('request', now()->subDays(30), function ($query) {
            return $query->where('content->uri', 'like', '%/chinook/purchase%');
        });

        $this->info("Pruned {$purchaseEntries} Chinook purchase entries older than 30 days");
    }
}
```

## 1.6. Debugging Workflows

### 1.6.1. Request Debugging

Debug Chinook API requests and customer interactions:

```php
// Example: Debugging Chinook customer purchase flow
// Navigate to: /telescope/requests

// Filter by Chinook purchase requests
// URL pattern: /api/chinook/purchase/*
// Status codes: 400, 422, 500 (errors)
// Tags: chinook-purchase

// Common debugging scenarios:
// 1. Failed payment processing
// 2. Invalid customer data
// 3. Inventory availability issues
// 4. Pricing calculation errors
```

**Custom Telescope Tags for Chinook Debugging:**

```php
// app/Providers/TelescopeServiceProvider.php
Telescope::tag(function (IncomingEntry $entry) {
    $tags = [];

    if ($entry->type === 'request') {
        $uri = $entry->content['uri'] ?? '';

        // Tag Chinook API endpoints
        if (str_contains($uri, '/api/chinook/')) {
            $tags[] = 'chinook-api';

            // Specific endpoint tags
            if (str_contains($uri, '/tracks')) $tags[] = 'chinook-tracks';
            if (str_contains($uri, '/albums')) $tags[] = 'chinook-albums';
            if (str_contains($uri, '/artists')) $tags[] = 'chinook-artists';
            if (str_contains($uri, '/playlists')) $tags[] = 'chinook-playlists';
            if (str_contains($uri, '/purchase')) $tags[] = 'chinook-purchase';
            if (str_contains($uri, '/search')) $tags[] = 'chinook-search';
        }

        // Tag customer operations
        if (str_contains($uri, '/customer')) {
            $tags[] = 'chinook-customer';
        }

        // Tag taxonomy operations
        if (str_contains($uri, '/taxonomy') || str_contains($uri, '/genre')) {
            $tags[] = 'chinook-taxonomy';
        }
    }

    return $tags;
});
```

### 1.6.2. Database Query Analysis

Analyze database queries for Chinook performance optimization:

```php
// Example: Analyzing slow Chinook queries
// Navigate to: /telescope/queries

// Common Chinook query optimization targets:
// 1. Music catalog searches with taxonomy filters
// 2. Customer purchase history queries
// 3. Artist/album relationship queries
// 4. Playlist track associations

// Query patterns to monitor:
// - SELECT * FROM chinook_tracks WHERE genre_id IN (...)
// - Complex JOINs between tracks, albums, artists
// - Taxonomy relationship queries
// - Customer invoice aggregations
```

**Custom Query Monitoring for Chinook:**

```php
// app/Providers/TelescopeServiceProvider.php
Telescope::filter(function (IncomingEntry $entry) {
    if ($entry->type === 'query') {
        $sql = $entry->content['sql'] ?? '';

        // Always monitor Chinook-related queries
        if (str_contains($sql, 'chinook_')) {
            return true;
        }

        // Monitor taxonomy queries
        if (str_contains($sql, 'taxonomies') || str_contains($sql, 'taggables')) {
            return true;
        }

        // Monitor slow queries (>100ms)
        if (($entry->content['time'] ?? 0) > 100) {
            return true;
        }
    }

    return false;
});
```

### 1.6.3. Exception Analysis

Analyze exceptions and errors in Chinook operations:

```php
// Example: Debugging Chinook exceptions
// Navigate to: /telescope/exceptions

// Common Chinook exception patterns:
// 1. Payment processing failures
// 2. Invalid music file access
// 3. Taxonomy relationship errors
// 4. Customer authentication issues
// 5. Inventory management exceptions

// Exception monitoring configuration
Telescope::filter(function (IncomingEntry $entry) {
    if ($entry->type === 'exception') {
        $exception = $entry->content['class'] ?? '';

        // Always monitor Chinook-specific exceptions
        $chinookExceptions = [
            'App\\Exceptions\\ChinookPaymentException',
            'App\\Exceptions\\ChinookInventoryException',
            'App\\Exceptions\\ChinookTaxonomyException',
            'App\\Exceptions\\ChinookCustomerException',
        ];

        if (in_array($exception, $chinookExceptions)) {
            return true;
        }

        // Monitor validation exceptions for Chinook forms
        if ($exception === 'Illuminate\\Validation\\ValidationException') {
            $message = $entry->content['message'] ?? '';
            if (str_contains($message, 'chinook') || str_contains($message, 'customer')) {
                return true;
            }
        }
    }

    return $entry->isReportableException();
});
```

## 1.7. Production Considerations

### 1.7.1. Performance Impact Assessment

Assess and minimize Telescope's impact on Chinook production performance:

```php
// config/telescope.php - Production configuration
return [
    'enabled' => env('TELESCOPE_ENABLED', false), // Disabled by default in production

    'watchers' => [
        // Minimal watchers for production Chinook monitoring
        Watchers\ExceptionWatcher::class => [
            'enabled' => true, // Always monitor exceptions
        ],

        Watchers\QueryWatcher::class => [
            'enabled' => true,
            'slow' => 200, // Only very slow queries
            'location' => false,
        ],

        Watchers\RequestWatcher::class => [
            'enabled' => false, // Disabled in production
        ],

        Watchers\JobWatcher::class => [
            'enabled' => true, // Monitor failed jobs
        ],

        // Disable resource-intensive watchers
        Watchers\CacheWatcher::class => ['enabled' => false],
        Watchers\ModelWatcher::class => ['enabled' => false],
        Watchers\RedisWatcher::class => ['enabled' => false],
    ],

    // Aggressive pruning for production
    'storage' => [
        'database' => [
            'chunk' => 100,
            'prune' => [
                'hours' => 12, // Keep data for 12 hours only
                'lottery' => [1, 10], // 10% chance to prune
            ],
        ],
    ],
];
```

### 1.7.2. Security Hardening

Implement security hardening for production Chinook Telescope:

```php
// app/Http/Middleware/SecureChinookTelescope.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecureChinookTelescope
{
    public function handle(Request $request, Closure $next)
    {
        // Disable in production unless explicitly enabled
        if (app()->environment('production') && !config('telescope.enabled')) {
            abort(404);
        }

        // IP whitelist check
        $allowedIps = config('chinook.telescope.allowed_ips', []);
        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
            abort(403, 'IP not authorized for Chinook Telescope access');
        }

        // Time-based access restrictions
        $allowedHours = config('chinook.telescope.allowed_hours', []);
        if (!empty($allowedHours) && !in_array(now()->hour, $allowedHours)) {
            abort(403, 'Telescope access not allowed at this time');
        }

        // Rate limiting for Telescope access
        if ($this->isRateLimited($request)) {
            abort(429, 'Too many Telescope requests');
        }

        return $next($request);
    }

    private function isRateLimited(Request $request): bool
    {
        $key = 'telescope_access:' . $request->ip();
        $attempts = cache()->get($key, 0);

        if ($attempts >= 60) { // 60 requests per hour
            return true;
        }

        cache()->put($key, $attempts + 1, now()->addHour());
        return false;
    }
}
```

### 1.7.3. Data Sanitization

Implement data sanitization for sensitive Chinook information:

```php
// app/Providers/TelescopeServiceProvider.php
protected function hideSensitiveRequestDetails(): void
{
    // Hide sensitive Chinook customer data
    Telescope::hideRequestParameters([
        '_token',
        'password',
        'password_confirmation',
        'credit_card_number',
        'cvv',
        'customer_payment_method',
        'billing_address',
    ]);

    Telescope::hideRequestHeaders([
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'authorization',
        'x-api-key',
    ]);

    // Custom sanitization for Chinook data
    Telescope::filter(function (IncomingEntry $entry) {
        if ($entry->type === 'request') {
            $content = $entry->content;

            // Sanitize customer payment information
            if (isset($content['payload'])) {
                $content['payload'] = $this->sanitizePaymentData($content['payload']);
            }

            // Sanitize customer personal information
            if (isset($content['session'])) {
                $content['session'] = $this->sanitizeCustomerData($content['session']);
            }

            $entry->content = $content;
        }

        return true;
    });
}

private function sanitizePaymentData(array $data): array
{
    $sensitiveFields = ['credit_card_number', 'cvv', 'billing_address'];

    foreach ($sensitiveFields as $field) {
        if (isset($data[$field])) {
            $data[$field] = '[REDACTED]';
        }
    }

    return $data;
}

private function sanitizeCustomerData(array $data): array
{
    $sensitiveFields = ['email', 'phone', 'address'];

    foreach ($sensitiveFields as $field) {
        if (isset($data[$field])) {
            $data[$field] = '[REDACTED]';
        }
    }

    return $data;
}
```

## 1.8. Integration Strategies

### 1.8.1. Laravel Pulse Integration

Integrate Telescope with Laravel Pulse for comprehensive Chinook monitoring:

```php
// app/Pulse/Recorders/ChinookTelescopeRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;

class ChinookTelescopeRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Record Telescope metrics in Pulse
        Telescope::filter(function (IncomingEntry $entry) use ($record) {
            // Record exception counts
            if ($entry->type === 'exception') {
                $record('chinook_telescope_exceptions', [
                    'exception_class' => $entry->content['class'] ?? 'unknown',
                    'is_chinook_related' => $this->isChinookRelated($entry),
                ]);
            }

            // Record slow query counts
            if ($entry->type === 'query' && ($entry->content['time'] ?? 0) > 100) {
                $record('chinook_telescope_slow_queries', [
                    'duration' => $entry->content['time'],
                    'is_chinook_table' => str_contains($entry->content['sql'] ?? '', 'chinook_'),
                ]);
            }

            // Record failed job counts
            if ($entry->type === 'job' && $entry->content['status'] === 'failed') {
                $record('chinook_telescope_failed_jobs', [
                    'job_class' => $entry->content['name'] ?? 'unknown',
                    'is_chinook_job' => str_contains($entry->content['name'] ?? '', 'Chinook'),
                ]);
            }

            return true;
        });
    }

    private function isChinookRelated(IncomingEntry $entry): bool
    {
        $content = json_encode($entry->content);
        return str_contains($content, 'chinook') ||
               str_contains($content, 'Chinook') ||
               str_contains($content, 'customer') ||
               str_contains($content, 'taxonomy');
    }
}
```

### 1.8.2. External Monitoring Integration

Connect Telescope with external monitoring services:

```php
// app/Services/ChinookTelescopeExporter.php
<?php

namespace App\Services;

use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Illuminate\Support\Facades\Http;

class ChinookTelescopeExporter
{
    public function exportToDatadog(): void
    {
        $repository = app(DatabaseEntriesRepository::class);

        // Export exception metrics
        $exceptions = $repository->get('exception', [
            'limit' => 100,
            'before' => now()->timestamp,
        ]);

        $metrics = $exceptions->map(function ($entry) {
            return [
                'metric' => 'chinook.telescope.exceptions',
                'points' => [[
                    'timestamp' => $entry->created_at->timestamp,
                    'value' => 1,
                ]],
                'tags' => [
                    'environment:' . app()->environment(),
                    'exception:' . ($entry->content['class'] ?? 'unknown'),
                    'chinook_related:' . ($this->isChinookRelated($entry) ? 'true' : 'false'),
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

    public function exportToSlack(): void
    {
        $repository = app(DatabaseEntriesRepository::class);

        // Get recent critical exceptions
        $criticalExceptions = $repository->get('exception', [
            'limit' => 10,
            'before' => now()->timestamp,
        ])->filter(function ($entry) {
            return $this->isCriticalException($entry);
        });

        if ($criticalExceptions->isNotEmpty()) {
            $message = "🚨 Critical Chinook exceptions detected:\n\n";

            foreach ($criticalExceptions as $exception) {
                $message .= "• {$exception->content['class']}: {$exception->content['message']}\n";
                $message .= "  Time: {$exception->created_at->diffForHumans()}\n\n";
            }

            Http::post(config('chinook.slack.webhook_url'), [
                'text' => $message,
                'channel' => '#chinook-alerts',
                'username' => 'Chinook Telescope',
            ]);
        }
    }

    private function isChinookRelated($entry): bool
    {
        $content = json_encode($entry->content);
        return str_contains($content, 'chinook') || str_contains($content, 'Chinook');
    }

    private function isCriticalException($entry): bool
    {
        $criticalExceptions = [
            'App\\Exceptions\\ChinookPaymentException',
            'App\\Exceptions\\ChinookInventoryException',
            'Illuminate\\Database\\QueryException',
        ];

        return in_array($entry->content['class'] ?? '', $criticalExceptions);
    }
}
```

## 1.9. Best Practices

### 1.9.1. Development Workflow

Establish effective development workflows with Chinook Telescope:

```php
// Development best practices for Chinook team

// 1. Use tags for organized debugging
Telescope::tag(function (IncomingEntry $entry) {
    $tags = [];

    // Feature-based tagging
    if ($entry->type === 'request') {
        $uri = $entry->content['uri'] ?? '';

        if (str_contains($uri, '/feature/music-discovery')) {
            $tags[] = 'feature-music-discovery';
        }
        if (str_contains($uri, '/feature/playlist-management')) {
            $tags[] = 'feature-playlist-management';
        }
        if (str_contains($uri, '/feature/customer-portal')) {
            $tags[] = 'feature-customer-portal';
        }
    }

    // Developer-specific tagging
    if (auth()->check()) {
        $tags[] = 'dev-' . auth()->user()->name;
    }

    return $tags;
});

// 2. Custom debugging helpers
if (app()->environment('local')) {
    // Helper function for Chinook debugging
    function chinook_debug($data, $tag = 'chinook-debug') {
        Telescope::recordDebug([
            'data' => $data,
            'timestamp' => now(),
            'user' => auth()->user()?->name ?? 'guest',
        ], [$tag]);
    }
}
```

### 1.9.2. Team Collaboration

Enable effective team collaboration with Chinook Telescope:

```php
// Team collaboration features

// 1. Shared debugging sessions
Route::get('/telescope/shared/{session}', function ($session) {
    return view('telescope.shared', [
        'session' => $session,
        'entries' => Telescope::getEntriesByTag("session-{$session}"),
    ]);
})->middleware(['auth', 'can:viewTelescope']);

// 2. Team-specific filters
Telescope::filter(function (IncomingEntry $entry) {
    // Tag entries by team member
    if (auth()->check()) {
        $entry->tags[] = 'team-member-' . auth()->id();
    }

    return true;
});
```

### 1.9.3. Performance Monitoring

Monitor Telescope's own performance impact on Chinook:

```php
// app/Console/Commands/ChinookTelescopeHealth.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

class ChinookTelescopeHealth extends Command
{
    protected $signature = 'chinook:telescope-health';
    protected $description = 'Check Chinook Telescope health and performance';

    public function handle(): void
    {
        $repository = app(DatabaseEntriesRepository::class);

        // Check database size
        $entryCount = $repository->count();
        $this->info("Total Telescope entries: {$entryCount}");

        // Check recent activity
        $recentEntries = $repository->get(null, [
            'limit' => 100,
            'before' => now()->timestamp,
        ]);

        $this->info("Recent entries (last 100): {$recentEntries->count()}");

        // Check for performance issues
        $slowQueries = $recentEntries->where('type', 'query')
            ->where('content.time', '>', 1000);

        if ($slowQueries->isNotEmpty()) {
            $this->warn("Found {$slowQueries->count()} slow queries (>1s)");
        }

        // Check storage usage
        $dbSize = $this->getDatabaseSize();
        $this->info("Telescope database size: {$dbSize} MB");

        if ($dbSize > 500) {
            $this->warn("Telescope database is large (>{$dbSize}MB). Consider pruning.");
        }
    }

    private function getDatabaseSize(): float
    {
        $connection = config('telescope.storage.database.connection');
        $database = config("database.connections.{$connection}.database");

        if (file_exists($database)) {
            return round(filesize($database) / 1024 / 1024, 2);
        }

        return 0;
    }
}
```

## 1.10. Troubleshooting

### 1.10.1. Common Issues

**High Memory Usage:**

```bash
# Reduce Telescope memory usage
php artisan telescope:prune --hours=6
php artisan telescope:clear

# Optimize configuration
# Disable resource-intensive watchers in production
```

**Slow Dashboard Loading:**

```php
// config/telescope.php
'storage' => [
    'database' => [
        'chunk' => 100, // Reduce chunk size
    ],
],

'watchers' => [
    Watchers\ModelWatcher::class => [
        'enabled' => false, // Disable in production
    ],
],
```

### 1.10.2. Debug Mode

Enable debug mode for Telescope troubleshooting:

```bash
# Enable Telescope debugging
TELESCOPE_ENABLED=true
APP_DEBUG=true

# Check Telescope status
php artisan telescope:status

# Monitor Telescope performance
php artisan chinook:telescope-health
```

### 1.10.3. Data Recovery

Recover lost or corrupted Telescope data:

```bash
# Backup Telescope database
cp database/chinook_telescope.sqlite database/chinook_telescope_backup.sqlite

# Restore from backup
cp database/chinook_telescope_backup.sqlite database/chinook_telescope.sqlite

# Rebuild Telescope tables
php artisan telescope:install --force
php artisan migrate:refresh --path=vendor/laravel/telescope/database/migrations
```

## 1.11. Navigation

**← Previous:** [Laravel Pulse Guide](020-laravel-pulse-guide.md)

**Next →** [Laravel Horizon Guide](040-laravel-horizon-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Telescope implementation guide provides comprehensive debugging and monitoring capabilities for the Chinook music store application, including:

- **Advanced Request Debugging**: Track API performance, customer interactions, and purchase workflows
- **Database Query Optimization**: Monitor and optimize music catalog queries and taxonomy operations
- **Exception Monitoring**: Comprehensive error tracking for customer-facing operations and business logic
- **Security Hardening**: Production-ready security measures with data sanitization and access controls
- **Team Collaboration**: Shared debugging sessions and team-specific filtering for development workflows
- **Performance Optimization**: Minimal production impact with intelligent sampling and pruning strategies
- **Integration Capabilities**: Seamless integration with Laravel Pulse and external monitoring services

The implementation follows Laravel 12 modern patterns and integrates with spatie/laravel-permission for role-based access, providing powerful debugging capabilities while maintaining optimal performance for the Chinook music store operations.
