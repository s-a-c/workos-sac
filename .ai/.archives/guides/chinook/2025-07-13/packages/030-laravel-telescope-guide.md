# Laravel Telescope Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. Configuration Publishing](#12-configuration-publishing)
  - [1.3. Environment Configuration](#13-environment-configuration)
- [Authorization & Security](#authorization--security)
  - [2.1. Gate-Based Authorization](#21-gate-based-authorization)
  - [2.2. Environment-Specific Access](#22-environment-specific-access)
  - [2.3. IP Whitelisting](#23-ip-whitelisting)
- [Data Collection Configuration](#data-collection-configuration)
  - [3.1. Watcher Configuration](#31-watcher-configuration)
  - [3.2. Filtering & Sampling](#32-filtering--sampling)
  - [3.3. Performance Impact Mitigation](#33-performance-impact-mitigation)
- [Data Pruning & Storage Management](#data-pruning--storage-management)
  - [4.1. Automated Pruning](#41-automated-pruning)
  - [4.2. Storage Optimization](#42-storage-optimization)
  - [4.3. Custom Retention Policies](#43-custom-retention-policies)
- [Debugging Workflows](#debugging-workflows)
  - [5.1. Request Debugging](#51-request-debugging)
  - [5.2. Database Query Analysis](#52-database-query-analysis)
  - [5.3. Exception Analysis](#53-exception-analysis)
- [Production Considerations](#production-considerations)
  - [6.1. Performance Impact Assessment](#61-performance-impact-assessment)
  - [6.2. Security Hardening](#62-security-hardening)
  - [6.3. Data Sanitization](#63-data-sanitization)
- [Integration Strategies](#integration-strategies)
  - [7.1. Laravel Pulse Integration](#71-laravel-pulse-integration)
  - [7.2. External Monitoring Integration](#72-external-monitoring-integration)
- [Best Practices](#best-practices)
  - [8.1. Development Workflow](#81-development-workflow)
  - [8.2. Team Collaboration](#82-team-collaboration)
  - [8.3. Performance Monitoring](#83-performance-monitoring)
- [Troubleshooting](#troubleshooting)
  - [9.1. Common Issues](#91-common-issues)
  - [9.2. Debug Mode](#92-debug-mode)
  - [9.3. Data Recovery](#93-data-recovery)
- [Navigation](#navigation)

## Overview

Laravel Telescope provides comprehensive debugging and application inspection capabilities with detailed request tracking, database query monitoring, and performance analysis. This guide covers enterprise-level implementation with security considerations, performance optimization, and production deployment strategies.

**🚀 Key Features:**
- **Request Inspection**: Detailed request/response analysis and debugging capabilities
- **Database Query Monitoring**: Query performance analysis and optimization insights
- **Job & Queue Tracking**: Background job monitoring and failure analysis
- **Mail & Notification Tracking**: Communication debugging and delivery verification
- **Security Monitoring**: Authentication attempts and security event tracking
- **Exception Tracking**: Comprehensive error monitoring and stack trace analysis

## Installation & Setup

### 1.1. Package Installation

Install Laravel Telescope using Composer:

```bash
# Install Telescope
composer require laravel/telescope

# Install Telescope (development only)
composer require laravel/telescope --dev

# Publish Telescope assets and configuration
php artisan telescope:install

# Run migrations to create telescope tables
php artisan migrate
```

**Verification Steps:**

```bash
# Verify installation
php artisan telescope:status

# Expected output:
# ✓ Telescope is installed and configured
# ✓ Database tables are present
# ✓ Watchers are configured
```

### 1.2. Configuration Publishing

Configure Telescope settings for your environment:

```php
// config/telescope.php
return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    'driver' => env('TELESCOPE_DRIVER', 'database'),
    
    'storage' => [
        'database' => [
            'connection' => env('TELESCOPE_DB_CONNECTION', null),
            'chunk' => 1000,
        ],
    ],
    
    'queue' => [
        'connection' => env('TELESCOPE_QUEUE_CONNECTION', null),
        'queue' => env('TELESCOPE_QUEUE', null),
    ],
    
    'watchers' => [
        // Watcher configuration
    ],
];
```

### 1.3. Environment Configuration

Configure environment variables for different environments:

```bash
# .env configuration
TELESCOPE_ENABLED=true
TELESCOPE_DOMAIN=null
TELESCOPE_PATH=telescope

# Database configuration
TELESCOPE_DB_CONNECTION=telescope
TELESCOPE_DRIVER=database

# Queue configuration (for async processing)
TELESCOPE_QUEUE_CONNECTION=redis
TELESCOPE_QUEUE=telescope

# Security settings
TELESCOPE_MIDDLEWARE=web,auth
```

**Environment-Specific Configuration:**

```bash
# Development (.env.local)
TELESCOPE_ENABLED=true
TELESCOPE_WATCHERS_CACHE=true
TELESCOPE_WATCHERS_COMMANDS=true
TELESCOPE_WATCHERS_DUMPS=true

# Staging (.env.staging)
TELESCOPE_ENABLED=true
TELESCOPE_WATCHERS_CACHE=false
TELESCOPE_WATCHERS_COMMANDS=false
TELESCOPE_WATCHERS_DUMPS=false

# Production (.env.production)
TELESCOPE_ENABLED=false  # Disable in production by default
```

## Authorization & Security

### 2.1. Gate-Based Authorization

Configure secure access to Telescope:

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

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return in_array($user->email, [
                'admin@example.com',
                'developer@example.com',
            ]) || $user->hasRole(['admin', 'developer']);
        });
    }
}
```

**Role-Based Access Control:**

```php
// Enhanced authorization with roles
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        // Environment-based access
        if ($this->app->environment('local')) {
            return true;
        }
        
        // Production access - strict role checking
        if ($this->app->environment('production')) {
            return $user->hasRole('super-admin');
        }
        
        // Staging access - developer and admin roles
        return $user->hasAnyRole(['admin', 'developer', 'qa']);
    });
}
```

### 2.2. Environment-Specific Access

Configure different access levels per environment:

```php
// config/telescope.php
'enabled' => env('TELESCOPE_ENABLED', function () {
    return app()->environment(['local', 'staging']);
}),

'middleware' => [
    'web',
    app()->environment('production') ? 'auth:admin' : 'auth',
],
```

### 2.3. IP Whitelisting

Implement IP-based access control:

```php
// app/Http/Middleware/TelescopeAccess.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TelescopeAccess
{
    public function handle(Request $request, Closure $next)
    {
        // IP whitelist for production
        if (app()->environment('production')) {
            $allowedIps = [
                '192.168.1.100',  // Office IP
                '10.0.0.0/8',     // VPN range
            ];
            
            if (!$this->isIpAllowed($request->ip(), $allowedIps)) {
                abort(403, 'Access denied from this IP address');
            }
        }
        
        // Time-based access restrictions
        if (app()->environment('production')) {
            $currentHour = now()->hour;
            if ($currentHour < 8 || $currentHour > 18) {
                abort(403, 'Telescope access restricted outside business hours');
            }
        }
        
        return $next($request);
    }
    
    private function isIpAllowed(string $ip, array $allowedIps): bool
    {
        foreach ($allowedIps as $allowedIp) {
            if (str_contains($allowedIp, '/')) {
                // CIDR notation
                if ($this->ipInRange($ip, $allowedIp)) {
                    return true;
                }
            } else {
                // Exact IP match
                if ($ip === $allowedIp) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
}
```

## Data Collection Configuration

### 3.1. Watcher Configuration

Configure watchers for comprehensive monitoring:

```php
// config/telescope.php
'watchers' => [
    // Cache interactions
    Watchers\CacheWatcher::class => [
        'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
    ],

    // Artisan commands
    Watchers\CommandWatcher::class => [
        'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
        'ignore' => [
            'telescope:prune',
            'schedule:run',
            'queue:work',
        ],
    ],

    // Database queries
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'ignore_packages' => true,
        'slow' => 100, // milliseconds
    ],

    // Events
    Watchers\EventWatcher::class => [
        'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
        'ignore' => [
            'Illuminate\Auth\Events\*',
            'Illuminate\Cache\Events\*',
            'Illuminate\Foundation\Events\LocaleUpdated',
        ],
    ],

    // Exceptions
    Watchers\ExceptionWatcher::class => [
        'enabled' => env('TELESCOPE_EXCEPTION_WATCHER', true),
    ],

    // Jobs
    Watchers\JobWatcher::class => [
        'enabled' => env('TELESCOPE_JOB_WATCHER', true),
    ],

    // Logs
    Watchers\LogWatcher::class => [
        'enabled' => env('TELESCOPE_LOG_WATCHER', true),
        'level' => 'error',
    ],

    // Mail
    Watchers\MailWatcher::class => [
        'enabled' => env('TELESCOPE_MAIL_WATCHER', true),
    ],

    // Models
    Watchers\ModelWatcher::class => [
        'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
        'events' => ['eloquent.*'],
        'hydrations' => true,
    ],

    // Notifications
    Watchers\NotificationWatcher::class => [
        'enabled' => env('TELESCOPE_NOTIFICATION_WATCHER', true),
    ],

    // Redis
    Watchers\RedisWatcher::class => [
        'enabled' => env('TELESCOPE_REDIS_WATCHER', true),
    ],

    // HTTP requests
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => 64,
    ],

    // Scheduled tasks
    Watchers\ScheduleWatcher::class => [
        'enabled' => env('TELESCOPE_SCHEDULE_WATCHER', true),
    ],

    // Views
    Watchers\ViewWatcher::class => [
        'enabled' => env('TELESCOPE_VIEW_WATCHER', true),
    ],
],
```

### 3.2. Filtering & Sampling

Implement intelligent filtering to reduce data volume:

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    Telescope::filter(function (IncomingEntry $entry) {
        // Always record in local environment
        if ($this->app->environment('local')) {
            return true;
        }
        
        // Production filtering - only important entries
        if ($this->app->environment('production')) {
            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isSlowQuery() ||
                   $entry->hasMonitoredTag();
        }
        
        // Staging - moderate filtering
        return $entry->isReportableException() ||
               $entry->isFailedRequest() ||
               $entry->isFailedJob() ||
               $entry->isSlowQuery() ||
               $entry->type === 'request' && $entry->content['response_status'] >= 400;
    });
    
    // Tag important entries for monitoring
    Telescope::tag(function (IncomingEntry $entry) {
        $tags = [];
        
        if ($entry->type === 'request') {
            $tags[] = 'status:' . $entry->content['response_status'];
            
            if ($entry->content['response_status'] >= 500) {
                $tags[] = 'critical';
            }
            
            if ($entry->content['duration'] > 2000) {
                $tags[] = 'slow';
            }
        }
        
        if ($entry->type === 'query' && $entry->content['time'] > 1000) {
            $tags[] = 'slow-query';
        }
        
        if ($entry->type === 'exception') {
            $tags[] = 'error';
            $tags[] = 'exception:' . class_basename($entry->content['class']);
        }
        
        return $tags;
    });
}
```

### 3.3. Performance Impact Mitigation

Minimize Telescope's performance impact:

```php
// config/telescope.php
'queue' => [
    'connection' => 'redis',
    'queue' => 'telescope',
],

// Use async processing for heavy watchers
'watchers' => [
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'slow' => 500, // Only log slow queries in production
    ],
    
    Watchers\ModelWatcher::class => [
        'enabled' => env('TELESCOPE_MODEL_WATCHER', false), // Disable in production
    ],
    
    Watchers\ViewWatcher::class => [
        'enabled' => env('TELESCOPE_VIEW_WATCHER', false), // Disable in production
    ],
],
```

**Conditional Watcher Loading:**

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    if ($this->app->environment('local')) {
        // Enable all watchers in local
        Telescope::night();
    } elseif ($this->app->environment('staging')) {
        // Limited watchers in staging
        $this->enableStagingWatchers();
    } else {
        // Minimal watchers in production
        $this->enableProductionWatchers();
    }
}

private function enableStagingWatchers(): void
{
    config([
        'telescope.watchers' => array_filter(config('telescope.watchers'), function ($config, $watcher) {
            $allowedWatchers = [
                'Laravel\Telescope\Watchers\ExceptionWatcher',
                'Laravel\Telescope\Watchers\QueryWatcher',
                'Laravel\Telescope\Watchers\RequestWatcher',
                'Laravel\Telescope\Watchers\JobWatcher',
            ];
            
            return in_array($watcher, $allowedWatchers);
        }, ARRAY_FILTER_USE_BOTH),
    ]);
}

private function enableProductionWatchers(): void
{
    config([
        'telescope.watchers' => [
            'Laravel\Telescope\Watchers\ExceptionWatcher' => ['enabled' => true],
            'Laravel\Telescope\Watchers\QueryWatcher' => [
                'enabled' => true,
                'slow' => 1000, // Only very slow queries
            ],
        ],
    ]);
}
```

## Data Pruning & Storage Management

### 4.1. Automated Pruning

Configure automated data pruning to manage storage:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Daily pruning - keep last 7 days
    $schedule->command('telescope:prune --hours=168')
        ->daily()
        ->at('02:00');

    // Environment-specific pruning
    if (app()->environment('production')) {
        // More aggressive pruning in production
        $schedule->command('telescope:prune --hours=24')
            ->daily()
            ->at('03:00');
    } elseif (app()->environment('staging')) {
        // Moderate pruning in staging
        $schedule->command('telescope:prune --hours=72')
            ->daily()
            ->at('02:30');
    }
}
```

**Custom Pruning Command:**

```php
// app/Console/Commands/TelescopePruneCustom.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

class TelescopePruneCustom extends Command
{
    protected $signature = 'telescope:prune-custom
                           {--exceptions=24 : Hours to keep exception entries}
                           {--queries=12 : Hours to keep query entries}
                           {--requests=6 : Hours to keep request entries}';

    protected $description = 'Prune Telescope entries with custom retention per type';

    public function handle(): int
    {
        $repository = app(DatabaseEntriesRepository::class);

        $this->pruneByType('exception', $this->option('exceptions'), $repository);
        $this->pruneByType('query', $this->option('queries'), $repository);
        $this->pruneByType('request', $this->option('requests'), $repository);

        $this->info('Custom Telescope pruning completed');

        return 0;
    }

    private function pruneByType(string $type, int $hours, $repository): void
    {
        $cutoff = now()->subHours($hours);

        $deleted = $repository->prune($type, $cutoff);

        $this->line("Pruned {$deleted} {$type} entries older than {$hours} hours");
    }
}
```

### 4.2. Storage Optimization

Optimize Telescope database performance:

```php
// database/migrations/add_telescope_indexes.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telescope_entries', function (Blueprint $table) {
            $table->index(['type', 'created_at']);
            $table->index(['family_hash', 'created_at']);
            $table->index(['should_display_on_index', 'created_at']);
        });

        Schema::table('telescope_entries_tags', function (Blueprint $table) {
            $table->index(['tag', 'entry_uuid']);
        });

        Schema::table('telescope_monitoring', function (Blueprint $table) {
            $table->index(['tag', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('telescope_entries', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropIndex(['family_hash', 'created_at']);
            $table->dropIndex(['should_display_on_index', 'created_at']);
        });

        Schema::table('telescope_entries_tags', function (Blueprint $table) {
            $table->dropIndex(['tag', 'entry_uuid']);
        });

        Schema::table('telescope_monitoring', function (Blueprint $table) {
            $table->dropIndex(['tag', 'created_at']);
        });
    }
};
```

**Database Configuration Optimization:**

```php
// config/database.php - Telescope connection
'telescope' => [
    'driver' => 'sqlite',
    'database' => database_path('telescope.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => false, // Disable for performance
    'options' => [
        PDO::ATTR_TIMEOUT => 30,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
    ],
],
```

### 4.3. Custom Retention Policies

Implement sophisticated retention policies:

```php
// app/Services/TelescopeRetentionService.php
<?php

namespace App\Services;

use Laravel\Telescope\Storage\DatabaseEntriesRepository;
use Illuminate\Support\Facades\DB;

class TelescopeRetentionService
{
    private array $retentionPolicies = [
        'exception' => [
            'critical' => '30 days',
            'error' => '14 days',
            'warning' => '7 days',
            'default' => '3 days',
        ],
        'query' => [
            'slow-query' => '14 days',
            'default' => '3 days',
        ],
        'request' => [
            'critical' => '7 days',
            'slow' => '3 days',
            'default' => '1 day',
        ],
        'job' => [
            'failed' => '30 days',
            'default' => '7 days',
        ],
    ];

    public function applyRetentionPolicies(): void
    {
        foreach ($this->retentionPolicies as $type => $policies) {
            $this->applyTypeRetention($type, $policies);
        }
    }

    private function applyTypeRetention(string $type, array $policies): void
    {
        foreach ($policies as $tag => $retention) {
            if ($tag === 'default') {
                $this->pruneDefault($type, $retention);
            } else {
                $this->pruneTagged($type, $tag, $retention);
            }
        }
    }

    private function pruneTagged(string $type, string $tag, string $retention): void
    {
        $cutoff = now()->sub($retention);

        $entryIds = DB::table('telescope_entries_tags')
            ->join('telescope_entries', 'telescope_entries_tags.entry_uuid', '=', 'telescope_entries.uuid')
            ->where('telescope_entries.type', $type)
            ->where('telescope_entries_tags.tag', $tag)
            ->where('telescope_entries.created_at', '<', $cutoff)
            ->pluck('telescope_entries.id');

        if ($entryIds->isNotEmpty()) {
            DB::table('telescope_entries')->whereIn('id', $entryIds)->delete();
            DB::table('telescope_entries_tags')->whereIn('entry_uuid',
                DB::table('telescope_entries')->whereIn('id', $entryIds)->pluck('uuid')
            )->delete();
        }
    }

    private function pruneDefault(string $type, string $retention): void
    {
        $cutoff = now()->sub($retention);

        DB::table('telescope_entries')
            ->where('type', $type)
            ->where('created_at', '<', $cutoff)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('telescope_entries_tags')
                    ->whereColumn('telescope_entries_tags.entry_uuid', 'telescope_entries.uuid');
            })
            ->delete();
    }
}
```

## Debugging Workflows

### 5.1. Request Debugging

Use Telescope for comprehensive request debugging:

```php
// app/Http/Middleware/TelescopeDebugger.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

class TelescopeDebugger
{
    public function handle(Request $request, Closure $next)
    {
        // Tag requests for easier filtering
        if ($request->has('debug')) {
            Telescope::tag(['debug-session']);
        }

        // Tag API requests
        if ($request->is('api/*')) {
            Telescope::tag(['api-request']);
        }

        // Tag slow requests
        $startTime = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000;

        if ($duration > 1000) {
            Telescope::tag(['slow-request']);
        }

        return $response;
    }
}
```

### 5.2. Database Query Analysis

Analyze and optimize database queries:

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    // Monitor specific query patterns
    Telescope::filter(function (IncomingEntry $entry) {
        if ($entry->type === 'query') {
            // Flag N+1 queries
            if ($this->isNPlusOneQuery($entry)) {
                $entry->tags(['n-plus-one']);
            }

            // Flag queries without indexes
            if ($this->isUnindexedQuery($entry)) {
                $entry->tags(['unindexed']);
            }

            // Flag duplicate queries
            if ($this->isDuplicateQuery($entry)) {
                $entry->tags(['duplicate']);
            }
        }

        return true;
    });
}

private function isNPlusOneQuery(IncomingEntry $entry): bool
{
    // Simple heuristic: same query pattern executed multiple times
    $sql = $entry->content['sql'];
    $bindings = $entry->content['bindings'];

    // Check if this query pattern has been executed recently
    return cache()->remember(
        'telescope_query_' . md5($sql),
        60,
        function () use ($sql) {
            return 0;
        }
    ) > 5; // More than 5 times in a minute
}

private function isUnindexedQuery(IncomingEntry $entry): bool
{
    $sql = strtolower($entry->content['sql']);

    // Look for table scans or missing WHERE clauses
    return str_contains($sql, 'select * from') &&
           !str_contains($sql, 'where') &&
           !str_contains($sql, 'limit');
}

private function isDuplicateQuery(IncomingEntry $entry): bool
{
    $queryHash = md5($entry->content['sql'] . serialize($entry->content['bindings']));

    return cache()->remember(
        'telescope_duplicate_' . $queryHash,
        60,
        function () {
            return false;
        }
    );
}
```

### 5.3. Exception Analysis

Comprehensive exception tracking and analysis:

```php
// app/Exceptions/Handler.php
public function report(Throwable $exception): void
{
    // Add context to Telescope exception entries
    if (app()->bound('telescope')) {
        Telescope::recordException($exception, [
            'user_id' => auth()->id(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'input' => request()->except(['password', 'password_confirmation']),
        ]);
    }

    parent::report($exception);
}
```

## Production Considerations

### 6.1. Performance Impact Assessment

Monitor Telescope's impact on application performance:

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    if (app()->environment('production')) {
        // Minimal configuration for production
        config([
            'telescope.enabled' => env('TELESCOPE_ENABLED', false),
            'telescope.watchers' => [
                'Laravel\Telescope\Watchers\ExceptionWatcher' => [
                    'enabled' => true,
                ],
                'Laravel\Telescope\Watchers\QueryWatcher' => [
                    'enabled' => true,
                    'slow' => 2000, // Only very slow queries
                ],
            ],
        ]);

        // Use queue for async processing
        Telescope::queue([
            'Laravel\Telescope\Watchers\QueryWatcher',
            'Laravel\Telescope\Watchers\ExceptionWatcher',
        ]);
    }
}
```

### 6.2. Security Hardening

Implement additional security measures for production:

```php
// app/Http/Middleware/TelescopeSecurityMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelescopeSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log all Telescope access attempts
        Log::info('Telescope access attempt', [
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
        ]);

        // Rate limiting
        if ($this->isRateLimited($request)) {
            abort(429, 'Too many requests');
        }

        // Session timeout for Telescope
        if ($this->isSessionExpired($request)) {
            auth()->logout();
            return redirect()->route('login');
        }

        return $next($request);
    }

    private function isRateLimited(Request $request): bool
    {
        $key = 'telescope_access_' . $request->ip();
        $attempts = cache()->get($key, 0);

        if ($attempts > 10) { // 10 requests per minute
            return true;
        }

        cache()->put($key, $attempts + 1, 60);
        return false;
    }

    private function isSessionExpired(Request $request): bool
    {
        $lastActivity = session('telescope_last_activity', now());
        $timeout = config('telescope.session_timeout', 30); // 30 minutes

        if (now()->diffInMinutes($lastActivity) > $timeout) {
            return true;
        }

        session(['telescope_last_activity' => now()]);
        return false;
    }
}
```

### 6.3. Data Sanitization

Ensure sensitive data is not stored in Telescope:

```php
// app/Providers/TelescopeServiceProvider.php
protected function hideSensitiveRequestDetails(): void
{
    Telescope::hideRequestParameters([
        '_token',
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'api_key',
        'secret',
        'credit_card_number',
        'cvv',
        'ssn',
        'social_security_number',
    ]);

    Telescope::hideRequestHeaders([
        'authorization',
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'x-api-key',
        'x-auth-token',
    ]);

    // Custom data sanitization
    Telescope::filter(function (IncomingEntry $entry) {
        if ($entry->type === 'request') {
            $entry->content = $this->sanitizeRequestContent($entry->content);
        }

        if ($entry->type === 'query') {
            $entry->content = $this->sanitizeQueryContent($entry->content);
        }

        return true;
    });
}

private function sanitizeRequestContent(array $content): array
{
    // Remove sensitive data from request payload
    if (isset($content['payload'])) {
        $content['payload'] = $this->recursiveSanitize($content['payload']);
    }

    return $content;
}

private function sanitizeQueryContent(array $content): array
{
    // Sanitize SQL query bindings
    if (isset($content['bindings'])) {
        $content['bindings'] = array_map(function ($binding) {
            if (is_string($binding) && $this->isSensitiveData($binding)) {
                return '[REDACTED]';
            }
            return $binding;
        }, $content['bindings']);
    }

    return $content;
}

private function recursiveSanitize(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = $this->recursiveSanitize($value);
        } elseif ($this->isSensitiveField($key) || $this->isSensitiveData($value)) {
            $data[$key] = '[REDACTED]';
        }
    }

    return $data;
}

private function isSensitiveField(string $field): bool
{
    $sensitiveFields = [
        'password', 'secret', 'token', 'key', 'credit_card',
        'ssn', 'social_security', 'bank_account', 'routing_number'
    ];

    foreach ($sensitiveFields as $sensitive) {
        if (str_contains(strtolower($field), $sensitive)) {
            return true;
        }
    }

    return false;
}

private function isSensitiveData($value): bool
{
    if (!is_string($value)) {
        return false;
    }

    // Check for patterns that might be sensitive
    $patterns = [
        '/^\d{4}-\d{4}-\d{4}-\d{4}$/', // Credit card pattern
        '/^\d{3}-\d{2}-\d{4}$/',       // SSN pattern
        '/^[A-Za-z0-9+\/]{40,}={0,2}$/', // Base64 encoded data
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }

    return false;
}
```

## Integration Strategies

### 7.1. Laravel Pulse Integration

Combine Telescope with Laravel Pulse for comprehensive monitoring:

```php
// app/Pulse/Recorders/TelescopeRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

class TelescopeRecorder extends Recorder
{
    public function register(callable $record): void
    {
        Telescope::filter(function (IncomingEntry $entry) use ($record) {
            // Record Telescope metrics in Pulse
            $this->recordTelescopeMetrics($entry, $record);

            return true;
        });
    }

    private function recordTelescopeMetrics(IncomingEntry $entry, callable $record): void
    {
        switch ($entry->type) {
            case 'exception':
                $record('telescope_exceptions', [
                    'class' => $entry->content['class'],
                    'message' => substr($entry->content['message'], 0, 100),
                ]);
                break;

            case 'query':
                if ($entry->content['time'] > 1000) {
                    $record('telescope_slow_queries', [
                        'time' => $entry->content['time'],
                        'connection' => $entry->content['connection'],
                    ]);
                }
                break;

            case 'request':
                if ($entry->content['response_status'] >= 500) {
                    $record('telescope_server_errors', [
                        'status' => $entry->content['response_status'],
                        'method' => $entry->content['method'],
                        'uri' => $entry->content['uri'],
                    ]);
                }
                break;
        }
    }
}
```

### 7.2. External Monitoring Integration

Export Telescope data to external monitoring services:

```php
// app/Services/TelescopeExportService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Laravel\Telescope\Storage\DatabaseEntriesRepository;

class TelescopeExportService
{
    public function exportToDatadog(): void
    {
        $repository = app(DatabaseEntriesRepository::class);

        // Export exception metrics
        $exceptions = $repository->get('exception', [
            'limit' => 100,
            'before' => now()->subMinutes(5),
        ]);

        $metrics = $exceptions->map(function ($entry) {
            return [
                'metric' => 'telescope.exceptions',
                'points' => [[
                    'timestamp' => $entry->created_at->timestamp,
                    'value' => 1,
                ]],
                'tags' => [
                    'environment:' . app()->environment(),
                    'exception:' . class_basename($entry->content['class']),
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

        // Get recent critical errors
        $criticalErrors = $repository->get('exception', [
            'limit' => 10,
            'before' => now()->subMinutes(5),
            'tags' => ['critical'],
        ]);

        if ($criticalErrors->isNotEmpty()) {
            $message = "🚨 Critical errors detected in the last 5 minutes:\n\n";

            foreach ($criticalErrors as $error) {
                $message .= "• {$error->content['class']}: {$error->content['message']}\n";
            }

            Http::post(config('services.slack.webhook_url'), [
                'text' => $message,
                'channel' => '#alerts',
                'username' => 'Telescope Monitor',
                'icon_emoji' => ':telescope:',
            ]);
        }
    }
}
```

## Best Practices

### 8.1. Development Workflow

Optimize Telescope for development productivity:

```php
// config/telescope.php - Development configuration
'watchers' => [
    // Enable all watchers in development
    Watchers\CacheWatcher::class => ['enabled' => true],
    Watchers\CommandWatcher::class => ['enabled' => true],
    Watchers\DumpWatcher::class => ['enabled' => true],
    Watchers\EventWatcher::class => ['enabled' => true],
    Watchers\ExceptionWatcher::class => ['enabled' => true],
    Watchers\JobWatcher::class => ['enabled' => true],
    Watchers\LogWatcher::class => ['enabled' => true],
    Watchers\MailWatcher::class => ['enabled' => true],
    Watchers\ModelWatcher::class => ['enabled' => true],
    Watchers\NotificationWatcher::class => ['enabled' => true],
    Watchers\QueryWatcher::class => [
        'enabled' => true,
        'slow' => 50, // Lower threshold for development
    ],
    Watchers\RedisWatcher::class => ['enabled' => true],
    Watchers\RequestWatcher::class => ['enabled' => true],
    Watchers\ScheduleWatcher::class => ['enabled' => true],
    Watchers\ViewWatcher::class => ['enabled' => true],
],
```

### 8.2. Team Collaboration

Configure Telescope for team development:

```php
// app/Providers/TelescopeServiceProvider.php
public function register(): void
{
    // Tag entries by developer
    Telescope::tag(function (IncomingEntry $entry) {
        $tags = [];

        if (auth()->check()) {
            $tags[] = 'user:' . auth()->user()->name;
            $tags[] = 'team:' . auth()->user()->team;
        }

        // Tag by feature branch
        if ($branch = $this->getCurrentBranch()) {
            $tags[] = 'branch:' . $branch;
        }

        return $tags;
    });
}

private function getCurrentBranch(): ?string
{
    if (file_exists(base_path('.git/HEAD'))) {
        $head = file_get_contents(base_path('.git/HEAD'));
        if (preg_match('/ref: refs\/heads\/(.+)/', $head, $matches)) {
            return $matches[1];
        }
    }

    return null;
}
```

### 8.3. Performance Monitoring

Monitor Telescope's own performance:

```php
// app/Console/Commands/TelescopeHealthCheck.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TelescopeHealthCheck extends Command
{
    protected $signature = 'telescope:health';
    protected $description = 'Check Telescope health and performance';

    public function handle(): int
    {
        $this->info('Telescope Health Check');
        $this->line('========================');

        // Check database size
        $this->checkDatabaseSize();

        // Check entry counts
        $this->checkEntryCounts();

        // Check performance impact
        $this->checkPerformanceImpact();

        return 0;
    }

    private function checkDatabaseSize(): void
    {
        $size = DB::connection('telescope')
            ->select("SELECT page_count * page_size as size FROM pragma_page_count(), pragma_page_size()")[0]->size ?? 0;

        $sizeMB = round($size / 1024 / 1024, 2);

        $this->line("Database size: {$sizeMB} MB");

        if ($sizeMB > 1000) {
            $this->warn("⚠️  Database size is large. Consider more aggressive pruning.");
        } else {
            $this->info("✓ Database size is acceptable");
        }
    }

    private function checkEntryCounts(): void
    {
        $counts = DB::connection('telescope')
            ->table('telescope_entries')
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $this->line("\nEntry counts by type:");
        foreach ($counts as $count) {
            $this->line("  {$count->type}: {$count->count}");
        }
    }

    private function checkPerformanceImpact(): void
    {
        $avgQueryTime = DB::connection('telescope')
            ->table('telescope_entries')
            ->where('type', 'query')
            ->where('created_at', '>=', now()->subHour())
            ->avg(DB::raw("json_extract(content, '$.time')"));

        $this->line("\nPerformance metrics:");
        $this->line("  Average query time (last hour): " . round($avgQueryTime, 2) . "ms");

        if ($avgQueryTime > 100) {
            $this->warn("⚠️  Average query time is high. Consider optimizing queries.");
        } else {
            $this->info("✓ Query performance is good");
        }
    }
}
```

## Troubleshooting

### 9.1. Common Issues

**Memory Issues:**

```bash
# Increase memory limit for Telescope operations
php -d memory_limit=512M artisan telescope:prune

# Check memory usage
php artisan telescope:health
```

**Database Lock Issues:**

```php
// config/database.php - Telescope connection
'telescope' => [
    'driver' => 'sqlite',
    'database' => database_path('telescope.sqlite'),
    'options' => [
        'journal_mode' => 'WAL',
        'busy_timeout' => 30000, // 30 seconds
    ],
],
```

**Performance Issues:**

```bash
# Disable expensive watchers
TELESCOPE_MODEL_WATCHER=false
TELESCOPE_VIEW_WATCHER=false
TELESCOPE_CACHE_WATCHER=false

# Increase pruning frequency
php artisan telescope:prune --hours=24
```

### 9.2. Debug Mode

Enable debug mode for troubleshooting:

```bash
# Enable Telescope debugging
TELESCOPE_DEBUG=true
LOG_LEVEL=debug

# Check Telescope status
php artisan telescope:status --verbose

# Monitor Telescope performance
php artisan telescope:health
```

### 9.3. Data Recovery

Recover from Telescope data corruption:

```bash
# Backup current data
cp database/telescope.sqlite database/telescope.sqlite.backup

# Rebuild Telescope tables
php artisan telescope:clear
php artisan migrate:fresh --path=vendor/laravel/telescope/database/migrations

# Restore from backup if needed
cp database/telescope.sqlite.backup database/telescope.sqlite
```

---

## Navigation

**← Previous:** [Laravel Pulse Guide](020-laravel-pulse-guide.md)

**Next →** [Laravel Octane with FrankenPHP Guide](040-laravel-octane-frankenphp-guide.md)
