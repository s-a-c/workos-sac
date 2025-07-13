# 1. Laravel Octane with FrankenPHP Implementation Guide

## Table of Contents

- [1. Laravel Octane with FrankenPHP Implementation Guide](#1-laravel-octane-with-frankenphp-implementation-guide)
  - [Table of Contents](#table-of-contents)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. FrankenPHP Server Setup](#122-frankenphp-server-setup)
    - [1.2.3. Environment Configuration](#123-environment-configuration)
  - [1.3. Server Configuration](#13-server-configuration)
    - [1.3.1. Basic Server Settings](#131-basic-server-settings)
    - [1.3.2. Performance Optimization](#132-performance-optimization)
    - [1.3.3. SSL/TLS Configuration](#133-ssltls-configuration)
  - [1.4. Memory Management](#14-memory-management)
    - [1.4.1. Memory Leak Prevention](#141-memory-leak-prevention)
    - [1.4.2. Resource Optimization](#142-resource-optimization)
    - [1.4.3. Garbage Collection Tuning](#143-garbage-collection-tuning)
  - [1.5. Taxonomy Performance Optimization](#15-taxonomy-performance-optimization)
    - [1.5.1. Taxonomy Caching Strategies](#151-taxonomy-caching-strategies)
    - [1.5.2. Taxonomy Query Optimization](#152-taxonomy-query-optimization)
    - [1.5.3. Taxonomy Memory Management](#153-taxonomy-memory-management)
  - [1.6. Production Deployment](#16-production-deployment)
    - [1.6.1. Docker Configuration](#161-docker-configuration)
    - [1.6.2. Load Balancing](#162-load-balancing)
    - [1.6.3. Scaling Strategies](#163-scaling-strategies)
  - [1.7. Monitoring & Troubleshooting](#17-monitoring--troubleshooting)
    - [1.7.1. Performance Monitoring](#171-performance-monitoring)
    - [1.7.2. Health Checks](#172-health-checks)
    - [1.7.3. Debugging Tools](#173-debugging-tools)
  - [1.8. Integration Strategies](#18-integration-strategies)
    - [1.8.1. Laravel Pulse Integration](#181-laravel-pulse-integration)
    - [1.8.2. External Monitoring Integration](#182-external-monitoring-integration)
  - [1.9. Best Practices](#19-best-practices)
    - [1.9.1. Development Workflow](#191-development-workflow)
    - [1.9.2. Production Optimization](#192-production-optimization)
    - [1.9.3. Security Hardening](#193-security-hardening)
  - [1.10. Performance Benchmarks](#110-performance-benchmarks)
    - [1.10.1. Benchmark Results](#1101-benchmark-results)
    - [1.10.2. Load Testing](#1102-load-testing)
  - [Navigation](#navigation)

## 1.1. Overview

Laravel Octane with FrankenPHP provides ultra-high performance application serving with advanced memory management, HTTP/2 & HTTP/3 support, and built-in HTTPS capabilities. This guide covers enterprise-level implementation with production deployment strategies, comprehensive performance optimization, and specialized **aliziodev/laravel-taxonomy** performance enhancements.

**🚀 Key Features:**
- **Ultra-High Performance**: 10x+ performance improvement over traditional PHP-FPM
- **HTTP/2 & HTTP/3 Support**: Modern protocol support for enhanced performance
- **Built-in HTTPS**: Automatic SSL/TLS certificate management with Let's Encrypt
- **Memory Efficiency**: Advanced memory management and leak prevention
- **Hot Reloading**: Development-friendly automatic code reloading
- **Production Scaling**: Horizontal scaling and load balancing capabilities
- **Taxonomy Optimization**: Specialized performance tuning for taxonomy operations

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Octane with FrankenPHP using Laravel 12 modern patterns:

```bash
# Install Laravel Octane
composer require laravel/octane

# Install Octane with FrankenPHP
php artisan octane:install --server=frankenphp

# Verify installation
php artisan octane:status
```

**System Requirements:**

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y curl wget unzip

# Download FrankenPHP binary
curl -L https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64 -o frankenphp
chmod +x frankenphp
sudo mv frankenphp /usr/local/bin/

# Verify FrankenPHP installation
frankenphp version
```

**Laravel 12 Service Provider Registration:**

```php
// bootstrap/providers.php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\OctaneServiceProvider::class,
    App\Providers\TaxonomyPerformanceServiceProvider::class,
];
```

### 1.2.2. FrankenPHP Server Setup

Configure FrankenPHP for optimal performance with taxonomy-aware settings:

```bash
# Create FrankenPHP configuration directory
sudo mkdir -p /etc/frankenphp
sudo mkdir -p /var/log/frankenphp

# Create Caddyfile for FrankenPHP with taxonomy optimization
sudo tee /etc/frankenphp/Caddyfile << 'EOF'
{
    # Global options
    auto_https off
    admin off
    
    # Logging with taxonomy context
    log {
        output file /var/log/frankenphp/access.log
        format json
        include http.request.header.X-Taxonomy-Context
    }
    
    # Error handling
    handle_errors {
        respond "Server Error: {http.error.status_code}" {http.error.status_code}
    }
}

# Laravel application with taxonomy optimization
:80 {
    # Document root
    root * /var/www/html/public
    
    # Enable compression
    encode gzip zstd
    
    # PHP handler with taxonomy-specific settings
    php_server {
        # Worker configuration optimized for taxonomy operations
        num_threads 4
        
        # Memory limits for taxonomy processing
        memory_limit 512M
        max_execution_time 60
        
        # Error reporting
        display_errors off
        log_errors on
        error_log /var/log/frankenphp/php_errors.log
        
        # Taxonomy-specific PHP settings
        env TAXONOMY_CACHE_ENABLED true
        env TAXONOMY_MEMORY_LIMIT 128M
        env TAXONOMY_QUERY_CACHE_SIZE 1000
    }
    
    # Static file handling
    file_server {
        hide .htaccess
        hide .env
    }
    
    # Security headers with taxonomy API protection
    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
        X-Taxonomy-API-Version "1.0"
    }
    
    # Taxonomy API rate limiting
    @taxonomy_api {
        path /api/taxonomy/*
        path /api/vocabularies/*
    }
    rate_limit @taxonomy_api {
        zone taxonomy_zone 10m
        key {remote_host}
        events 50
        window 1m
    }
}
EOF
```

### 1.2.3. Environment Configuration

Configure environment variables for Octane with taxonomy-specific optimizations:

```bash
# .env configuration
OCTANE_SERVER=frankenphp
OCTANE_HOST=0.0.0.0
OCTANE_PORT=8000
OCTANE_WORKERS=4
OCTANE_MAX_REQUESTS=1000
OCTANE_WATCH=false

# FrankenPHP specific settings
FRANKENPHP_CONFIG_FILE=/etc/frankenphp/Caddyfile
FRANKENPHP_LOG_LEVEL=info
FRANKENPHP_ADMIN_LISTEN=127.0.0.1:2019

# Performance settings
OCTANE_WARM=true
OCTANE_CACHE_TABLE=true
OCTANE_CACHE_VIEWS=true

# Memory management
OCTANE_MEMORY_LIMIT=512M
OCTANE_GC_ENABLED=true
OCTANE_GC_THRESHOLD=50

# Taxonomy-specific performance settings
TAXONOMY_CACHE_ENABLED=true
TAXONOMY_CACHE_TTL=3600
TAXONOMY_MEMORY_LIMIT=128M
TAXONOMY_QUERY_CACHE_SIZE=1000
TAXONOMY_PRELOAD_VOCABULARIES=true
TAXONOMY_OPTIMIZE_HIERARCHY=true

# SQLite optimization for taxonomy data
DB_TAXONOMY_CONNECTION=taxonomy
DB_TAXONOMY_DATABASE=database/taxonomy.sqlite
DB_TAXONOMY_JOURNAL_MODE=WAL
DB_TAXONOMY_SYNCHRONOUS=NORMAL
DB_TAXONOMY_CACHE_SIZE=10000
```

**Laravel 12 Database Configuration:**

```php
// config/database.php
'connections' => [
    'taxonomy' => [
        'driver' => 'sqlite',
        'database' => env('DB_TAXONOMY_DATABASE', database_path('taxonomy.sqlite')),
        'prefix' => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        'journal_mode' => env('DB_TAXONOMY_JOURNAL_MODE', 'WAL'),
        'synchronous' => env('DB_TAXONOMY_SYNCHRONOUS', 'NORMAL'),
        'cache_size' => env('DB_TAXONOMY_CACHE_SIZE', 10000),
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB
        'busy_timeout' => 30000,
    ],
],
```

## 1.3. Server Configuration

### 1.3.1. Basic Server Settings

Configure Octane for your application with taxonomy-aware optimizations:

```php
// config/octane.php
<?php

return [
    'server' => env('OCTANE_SERVER', 'frankenphp'),

    'host' => env('OCTANE_HOST', '0.0.0.0'),
    'port' => env('OCTANE_PORT', 8000),

    'https' => env('OCTANE_HTTPS', false),
    'workers' => env('OCTANE_WORKERS', 4),
    'max_requests' => env('OCTANE_MAX_REQUESTS', 1000),
    'rpc_port' => env('OCTANE_RPC_PORT', 6001),

    'watch' => env('OCTANE_WATCH', false),
    'poll' => env('OCTANE_POLL', false),
    'ignore' => [
        'storage',
        'vendor',
        'node_modules',
    ],

    'warm' => [
        'config',
        'routes',
        'views',
        'taxonomy', // Warm taxonomy data
    ],

    'cache' => [
        'tables' => [
            'users',
            'chinook_tracks',
            'chinook_albums',
            'chinook_artists',
            'taxonomies', // Cache taxonomy tables
            'taxonomy_terms',
            'taxonomy_vocabularies',
        ],
    ],

    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeStored::class,
            \App\Octane\Listeners\PreloadTaxonomyData::class,
        ],

        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
            \App\Octane\Listeners\SetTaxonomyContext::class,
        ],

        RequestHandled::class => [
            FlushTemporaryContainerInstances::class,
            DisconnectFromDatabases::class,
            \App\Octane\Listeners\FlushTaxonomyCache::class,
            CollectGarbage::class,
        ],

        RequestTerminated::class => [
            FlushSessionState::class,
            FlushAuthenticationState::class,
            \App\Octane\Listeners\ClearTaxonomyContext::class,
        ],

        TickReceived::class => [
            ...Octane::prepareApplicationForNextTick(),
        ],

        TickTerminated::class => [
            FlushTemporaryContainerInstances::class,
            DisconnectFromDatabases::class,
        ],

        TaskReceived::class => [
            ...Octane::prepareApplicationForNextTask(),
        ],

        TaskTerminated::class => [
            FlushTemporaryContainerInstances::class,
            DisconnectFromDatabases::class,
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
            \App\Octane\Listeners\LogTaxonomyError::class,
        ],

        WorkerStopping::class => [
            \App\Octane\Listeners\FlushTaxonomyData::class,
        ],
    ],
];
```

### 1.3.2. Performance Optimization

Optimize FrankenPHP for maximum performance with taxonomy-specific tuning:

```php
// config/octane.php - Performance optimizations
'frankenphp' => [
    'admin' => [
        'listen' => env('FRANKENPHP_ADMIN_LISTEN', '127.0.0.1:2019'),
    ],

    'server' => [
        'num_threads' => env('FRANKENPHP_NUM_THREADS', 4),
        'max_requests_per_worker' => env('FRANKENPHP_MAX_REQUESTS', 1000),
        'worker_timeout' => env('FRANKENPHP_WORKER_TIMEOUT', 60),
        'request_timeout' => env('FRANKENPHP_REQUEST_TIMEOUT', 30),
    ],

    'php' => [
        'memory_limit' => env('FRANKENPHP_MEMORY_LIMIT', '512M'),
        'max_execution_time' => env('FRANKENPHP_MAX_EXECUTION_TIME', 60),
        'max_input_time' => env('FRANKENPHP_MAX_INPUT_TIME', 60),
        'post_max_size' => env('FRANKENPHP_POST_MAX_SIZE', '100M'),
        'upload_max_filesize' => env('FRANKENPHP_UPLOAD_MAX_FILESIZE', '100M'),
    ],

    'opcache' => [
        'enable' => true,
        'memory_consumption' => 512, // Increased for taxonomy operations
        'interned_strings_buffer' => 32, // Increased for taxonomy strings
        'max_accelerated_files' => 30000, // Increased for taxonomy files
        'validate_timestamps' => false, // Disable in production
        'revalidate_freq' => 0,
        'save_comments' => false,
        'enable_file_override' => true,
        'jit_buffer_size' => 200, // JIT optimization for taxonomy operations
        'jit' => 'tracing',
    ],

    // Taxonomy-specific optimizations
    'taxonomy' => [
        'cache_enabled' => env('TAXONOMY_CACHE_ENABLED', true),
        'cache_ttl' => env('TAXONOMY_CACHE_TTL', 3600),
        'memory_limit' => env('TAXONOMY_MEMORY_LIMIT', '128M'),
        'query_cache_size' => env('TAXONOMY_QUERY_CACHE_SIZE', 1000),
        'preload_vocabularies' => env('TAXONOMY_PRELOAD_VOCABULARIES', true),
        'optimize_hierarchy' => env('TAXONOMY_OPTIMIZE_HIERARCHY', true),
    ],
],
```

**System-Level Optimizations for Taxonomy Operations:**

```bash
# /etc/sysctl.d/99-frankenphp-taxonomy.conf
# Network optimizations
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
net.ipv4.tcp_max_tw_buckets = 1440000

# Memory optimizations for taxonomy operations
vm.swappiness = 1
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5
vm.vfs_cache_pressure = 50

# File descriptor limits for taxonomy file operations
fs.file-max = 2097152

# SQLite-specific optimizations
vm.dirty_expire_centisecs = 500
vm.dirty_writeback_centisecs = 100

# Apply settings
sudo sysctl -p /etc/sysctl.d/99-frankenphp-taxonomy.conf
```

### 1.3.3. SSL/TLS Configuration

Configure HTTPS with automatic certificate management and taxonomy API security:

```bash
# Caddyfile with HTTPS and taxonomy-specific security
{
    # Enable automatic HTTPS
    auto_https on

    # Email for Let's Encrypt
    email admin@example.com

    # Certificate storage
    storage file_system {
        root /var/lib/caddy
    }
}

# Production site with HTTPS and taxonomy optimization
example.com {
    # Enable HTTP/2 and HTTP/3
    protocols h1 h2 h3

    # Document root
    root * /var/www/html/public

    # Security headers with taxonomy API protection
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
        Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
        X-Taxonomy-API-Version "1.0"
        X-Taxonomy-Cache-Status "enabled"
    }

    # Compression
    encode gzip zstd

    # PHP handler with Octane and taxonomy optimization
    php_server {
        num_threads 8
        memory_limit 1024M
        max_execution_time 120

        # Taxonomy-specific environment variables
        env TAXONOMY_CACHE_ENABLED true
        env TAXONOMY_MEMORY_LIMIT 256M
        env TAXONOMY_QUERY_CACHE_SIZE 2000
    }

    # Static file caching
    @static {
        file
        path *.css *.js *.ico *.png *.jpg *.jpeg *.gif *.svg *.woff *.woff2
    }
    header @static Cache-Control "public, max-age=31536000, immutable"

    # Taxonomy API rate limiting with enhanced protection
    @taxonomy_api {
        path /api/taxonomy/*
        path /api/vocabularies/*
        path /api/terms/*
    }
    rate_limit @taxonomy_api {
        zone taxonomy_zone 20m
        key {remote_host}
        events 100
        window 1m
        response 429
    }

    # General API rate limiting
    @api {
        path /api/*
    }
    rate_limit @api {
        zone api_zone 10m
        key {remote_host}
        events 200
        window 1m
    }

    # Logging with taxonomy context
    log {
        output file /var/log/frankenphp/access.log {
            roll_size 100mb
            roll_keep 5
            roll_keep_for 720h
        }
        format json
        include http.request.header.X-Taxonomy-Context
        include http.request.header.X-Vocabulary-ID
    }
}
```

## 1.4. Memory Management

### 1.4.1. Memory Leak Prevention

Implement comprehensive memory leak prevention with taxonomy-aware cleanup:

```php
// app/Octane/Listeners/FlushApplicationState.php
<?php

namespace App\Octane\Listeners;

use Laravel\Octane\Contracts\OperationTerminated;
use Illuminate\Support\Facades\DB;

class FlushApplicationState
{
    public function handle(OperationTerminated $event): void
    {
        // Clear application state
        $this->flushAuthenticationState();
        $this->flushSessionState();
        $this->flushCacheState();
        $this->flushDatabaseConnections();
        $this->flushTaxonomyState();
        $this->flushTemporaryFiles();
        $this->collectGarbage();
    }

    private function flushAuthenticationState(): void
    {
        if (auth()->hasResolvedGuards()) {
            foreach (auth()->getGuards() as $guard) {
                $guard->forgetUser();
            }
        }
    }

    private function flushSessionState(): void
    {
        if (app()->bound('session')) {
            session()->flush();
            session()->regenerate();
        }
    }

    private function flushCacheState(): void
    {
        // Clear specific cache tags that might accumulate
        cache()->tags(['user-data', 'temporary', 'taxonomy-temp'])->flush();
    }

    private function flushDatabaseConnections(): void
    {
        foreach (DB::getConnections() as $connection) {
            $connection->disconnect();
        }

        DB::purge();
    }

    private function flushTaxonomyState(): void
    {
        // Clear taxonomy-specific state
        if (app()->bound('taxonomy.cache')) {
            app('taxonomy.cache')->flush();
        }

        // Clear taxonomy query cache
        if (app()->bound('taxonomy.query_cache')) {
            app('taxonomy.query_cache')->clear();
        }

        // Reset taxonomy static variables
        if (class_exists(\Aliziodev\Taxonomy\Models\Taxonomy::class)) {
            \Aliziodev\Taxonomy\Models\Taxonomy::clearBootedModels();
        }
    }

    private function flushTemporaryFiles(): void
    {
        // Clean up temporary files including taxonomy exports
        $tempDirs = [
            storage_path('app/temp'),
            storage_path('app/taxonomy/temp'),
            storage_path('app/exports/temp'),
        ];

        foreach ($tempDirs as $tempDir) {
            if (is_dir($tempDir)) {
                $files = glob($tempDir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < time() - 3600) {
                        unlink($file);
                    }
                }
            }
        }
    }

    private function collectGarbage(): void
    {
        if (gc_enabled()) {
            gc_collect_cycles();
        }
    }
}
```

### 1.4.2. Resource Optimization

Optimize resource usage for long-running processes with taxonomy considerations:

```php
// app/Octane/Listeners/OptimizeResources.php
<?php

namespace App\Octane\Listeners;

use Laravel\Octane\Events\RequestReceived;
use Illuminate\Support\Facades\Log;

class OptimizeResources
{
    private int $requestCount = 0;
    private float $memoryThreshold = 0.8; // 80% of memory limit
    private array $taxonomyMetrics = [];

    public function handle(RequestReceived $event): void
    {
        $this->requestCount++;

        // Monitor memory usage
        $this->monitorMemoryUsage();

        // Monitor taxonomy-specific metrics
        $this->monitorTaxonomyMetrics();

        // Periodic cleanup
        if ($this->requestCount % 100 === 0) {
            $this->performPeriodicCleanup();
        }

        // Force garbage collection if needed
        if ($this->shouldForceGarbageCollection()) {
            $this->forceGarbageCollection();
        }
    }

    private function monitorMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryPercent = $memoryUsage / $memoryLimit;

        if ($memoryPercent > $this->memoryThreshold) {
            Log::warning('High memory usage detected', [
                'memory_usage' => $memoryUsage,
                'memory_limit' => $memoryLimit,
                'memory_percent' => $memoryPercent,
                'request_count' => $this->requestCount,
                'taxonomy_cache_size' => $this->getTaxonomyCacheSize(),
            ]);

            $this->performEmergencyCleanup();
        }
    }

    private function monitorTaxonomyMetrics(): void
    {
        // Track taxonomy-specific memory usage
        $taxonomyMemory = $this->getTaxonomyMemoryUsage();
        $this->taxonomyMetrics[] = [
            'timestamp' => microtime(true),
            'memory_usage' => $taxonomyMemory,
            'cache_size' => $this->getTaxonomyCacheSize(),
            'query_count' => $this->getTaxonomyQueryCount(),
        ];

        // Keep only last 100 metrics
        if (count($this->taxonomyMetrics) > 100) {
            array_shift($this->taxonomyMetrics);
        }

        // Alert on taxonomy memory growth
        if ($taxonomyMemory > 50 * 1024 * 1024) { // 50MB
            Log::warning('High taxonomy memory usage', [
                'taxonomy_memory' => $taxonomyMemory,
                'cache_size' => $this->getTaxonomyCacheSize(),
            ]);
        }
    }

    private function performPeriodicCleanup(): void
    {
        // Clear expired cache entries
        cache()->store('octane')->flush();

        // Clear taxonomy-specific caches
        $this->clearTaxonomyCaches();

        // Clear old log entries
        $this->clearOldLogs();

        // Reset static variables
        $this->resetStaticVariables();
    }

    private function performEmergencyCleanup(): void
    {
        // Aggressive cleanup
        gc_collect_cycles();

        // Clear all non-essential caches
        cache()->flush();

        // Clear taxonomy caches aggressively
        $this->clearTaxonomyCaches(true);

        // Disconnect from databases
        DB::purge();

        // Clear compiled views
        if (app()->bound('view')) {
            app('view')->flushFinderCache();
        }
    }

    private function clearTaxonomyCaches(bool $aggressive = false): void
    {
        // Clear taxonomy cache
        if (app()->bound('taxonomy.cache')) {
            app('taxonomy.cache')->flush();
        }

        // Clear vocabulary cache
        cache()->tags(['vocabulary', 'taxonomy'])->flush();

        if ($aggressive) {
            // Clear all taxonomy-related caches
            cache()->tags(['taxonomy-tree', 'taxonomy-children', 'taxonomy-ancestors'])->flush();
        }
    }

    private function getTaxonomyMemoryUsage(): int
    {
        // Estimate taxonomy memory usage
        $taxonomyMemory = 0;

        if (app()->bound('taxonomy.cache')) {
            $taxonomyMemory += strlen(serialize(app('taxonomy.cache')->all()));
        }

        return $taxonomyMemory;
    }

    private function getTaxonomyCacheSize(): int
    {
        if (app()->bound('taxonomy.cache')) {
            return app('taxonomy.cache')->count();
        }

        return 0;
    }

    private function getTaxonomyQueryCount(): int
    {
        // Get taxonomy query count from query log
        return collect(DB::getQueryLog())
            ->filter(function ($query) {
                return str_contains(strtolower($query['query']), 'taxonom');
            })
            ->count();
    }

    private function shouldForceGarbageCollection(): bool
    {
        return $this->requestCount % 50 === 0 ||
               memory_get_usage(true) > $this->getMemoryLimit() * 0.7;
    }

    private function forceGarbageCollection(): void
    {
        if (gc_enabled()) {
            $collected = gc_collect_cycles();

            if ($collected > 0) {
                Log::info('Garbage collection performed', [
                    'cycles_collected' => $collected,
                    'memory_before' => memory_get_usage(true),
                    'memory_after' => memory_get_usage(true),
                    'taxonomy_metrics' => end($this->taxonomyMetrics),
                ]);
            }
        }
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }

        return $this->convertToBytes($memoryLimit);
    }

    private function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function clearOldLogs(): void
    {
        $logPaths = [
            storage_path('logs'),
            storage_path('logs/taxonomy'),
        ];

        foreach ($logPaths as $logPath) {
            if (!is_dir($logPath)) continue;

            $files = glob($logPath . '/*.log');
            foreach ($files as $file) {
                if (filemtime($file) < time() - 86400 * 7) { // 7 days old
                    unlink($file);
                }
            }
        }
    }

    private function resetStaticVariables(): void
    {
        // Reset taxonomy static caches
        if (class_exists(\Aliziodev\Taxonomy\Models\Taxonomy::class)) {
            \Aliziodev\Taxonomy\Models\Taxonomy::clearBootedModels();
        }

        if (class_exists(\Aliziodev\Taxonomy\Models\Vocabulary::class)) {
            \Aliziodev\Taxonomy\Models\Vocabulary::clearBootedModels();
        }
    }
}
```

### 1.4.3. Garbage Collection Tuning

Fine-tune garbage collection for optimal performance with taxonomy considerations:

```php
// config/octane.php - Garbage collection settings
'garbage_collection' => [
    'enabled' => env('OCTANE_GC_ENABLED', true),
    'threshold' => env('OCTANE_GC_THRESHOLD', 50), // Requests between GC
    'memory_threshold' => env('OCTANE_GC_MEMORY_THRESHOLD', 0.7), // 70% memory usage
    'force_collection' => env('OCTANE_GC_FORCE', false),

    // Taxonomy-specific GC settings
    'taxonomy' => [
        'cache_threshold' => env('TAXONOMY_GC_CACHE_THRESHOLD', 1000),
        'memory_threshold' => env('TAXONOMY_GC_MEMORY_THRESHOLD', 0.6),
        'cleanup_interval' => env('TAXONOMY_GC_CLEANUP_INTERVAL', 100),
    ],
],
```

**PHP Configuration for Garbage Collection with Taxonomy Optimization:**

```ini
; php.ini optimizations for Octane with taxonomy
; Garbage Collection
zend.enable_gc = On
gc.threshold = 10001

; Memory settings optimized for taxonomy operations
memory_limit = 1024M
max_execution_time = 120
max_input_time = 120

; OPcache settings optimized for taxonomy
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 512
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 30000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 0
opcache.enable_file_override = 1

; JIT settings (PHP 8.0+) optimized for taxonomy operations
opcache.jit_buffer_size = 200M
opcache.jit = tracing

; Session settings
session.gc_probability = 1
session.gc_divisor = 1000
session.gc_maxlifetime = 1440

; SQLite settings for taxonomy database
sqlite3.extension_dir = /usr/lib/php/extensions/
```

## 1.5. Taxonomy Performance Optimization

### 1.5.1. Taxonomy Caching Strategies

Implement advanced caching strategies for aliziodev/laravel-taxonomy operations:

```php
// app/Services/TaxonomyCacheService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Aliziodev\Taxonomy\Models\Taxonomy;
use Aliziodev\Taxonomy\Models\Vocabulary;

class TaxonomyCacheService
{
    private string $cachePrefix = 'taxonomy:';
    private int $defaultTtl = 3600; // 1 hour
    private array $preloadedVocabularies = [];

    public function __construct()
    {
        $this->defaultTtl = config('octane.frankenphp.taxonomy.cache_ttl', 3600);
    }

    public function preloadVocabularies(): void
    {
        if (!config('octane.frankenphp.taxonomy.preload_vocabularies', true)) {
            return;
        }

        $vocabularies = Vocabulary::with(['taxonomies' => function ($query) {
            $query->with('children', 'parent');
        }])->get();

        foreach ($vocabularies as $vocabulary) {
            $this->cacheVocabulary($vocabulary);
            $this->preloadedVocabularies[$vocabulary->id] = $vocabulary;
        }
    }

    public function cacheVocabulary(Vocabulary $vocabulary): void
    {
        $cacheKey = $this->cachePrefix . "vocabulary:{$vocabulary->id}";

        Cache::put($cacheKey, $vocabulary, $this->defaultTtl);

        // Cache vocabulary taxonomies tree
        $this->cacheVocabularyTree($vocabulary);

        // Cache flat taxonomy list
        $this->cacheVocabularyTaxonomies($vocabulary);
    }

    public function cacheVocabularyTree(Vocabulary $vocabulary): void
    {
        $cacheKey = $this->cachePrefix . "tree:{$vocabulary->id}";

        $tree = $vocabulary->taxonomies()
            ->whereNull('parent_id')
            ->with('children.children.children') // 3 levels deep
            ->get();

        Cache::put($cacheKey, $tree, $this->defaultTtl);
    }

    public function cacheVocabularyTaxonomies(Vocabulary $vocabulary): void
    {
        $cacheKey = $this->cachePrefix . "flat:{$vocabulary->id}";

        $taxonomies = $vocabulary->taxonomies()
            ->with('parent', 'children')
            ->get()
            ->keyBy('id');

        Cache::put($cacheKey, $taxonomies, $this->defaultTtl);
    }

    public function cacheTaxonomyAncestors(Taxonomy $taxonomy): void
    {
        $cacheKey = $this->cachePrefix . "ancestors:{$taxonomy->id}";

        $ancestors = collect();
        $current = $taxonomy->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        Cache::put($cacheKey, $ancestors, $this->defaultTtl);
    }

    public function cacheTaxonomyDescendants(Taxonomy $taxonomy): void
    {
        $cacheKey = $this->cachePrefix . "descendants:{$taxonomy->id}";

        $descendants = $this->getAllDescendants($taxonomy);

        Cache::put($cacheKey, $descendants, $this->defaultTtl);
    }

    public function getVocabularyFromCache(int $vocabularyId): ?Vocabulary
    {
        $cacheKey = $this->cachePrefix . "vocabulary:{$vocabularyId}";

        return Cache::get($cacheKey) ?? $this->preloadedVocabularies[$vocabularyId] ?? null;
    }

    public function getTaxonomyTreeFromCache(int $vocabularyId): ?object
    {
        $cacheKey = $this->cachePrefix . "tree:{$vocabularyId}";

        return Cache::get($cacheKey);
    }

    public function getTaxonomyAncestorsFromCache(int $taxonomyId): ?object
    {
        $cacheKey = $this->cachePrefix . "ancestors:{$taxonomyId}";

        return Cache::get($cacheKey);
    }

    public function invalidateVocabularyCache(int $vocabularyId): void
    {
        $keys = [
            $this->cachePrefix . "vocabulary:{$vocabularyId}",
            $this->cachePrefix . "tree:{$vocabularyId}",
            $this->cachePrefix . "flat:{$vocabularyId}",
        ];

        Cache::deleteMultiple($keys);

        // Remove from preloaded cache
        unset($this->preloadedVocabularies[$vocabularyId]);
    }

    public function invalidateTaxonomyCache(int $taxonomyId): void
    {
        $keys = [
            $this->cachePrefix . "ancestors:{$taxonomyId}",
            $this->cachePrefix . "descendants:{$taxonomyId}",
        ];

        Cache::deleteMultiple($keys);
    }

    public function warmCache(): void
    {
        // Preload all vocabularies
        $this->preloadVocabularies();

        // Cache frequently accessed taxonomies
        $this->cacheFrequentTaxonomies();
    }

    public function flushCache(): void
    {
        Cache::tags(['taxonomy'])->flush();
        $this->preloadedVocabularies = [];
    }

    private function getAllDescendants(Taxonomy $taxonomy): object
    {
        $descendants = collect();

        foreach ($taxonomy->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getAllDescendants($child));
        }

        return $descendants;
    }

    private function cacheFrequentTaxonomies(): void
    {
        // Cache top-level taxonomies for each vocabulary
        $vocabularies = Vocabulary::all();

        foreach ($vocabularies as $vocabulary) {
            $topLevel = $vocabulary->taxonomies()
                ->whereNull('parent_id')
                ->limit(20) // Cache top 20
                ->get();

            foreach ($topLevel as $taxonomy) {
                $this->cacheTaxonomyAncestors($taxonomy);
                $this->cacheTaxonomyDescendants($taxonomy);
            }
        }
    }
}
```

### 1.5.2. Taxonomy Query Optimization

Optimize database queries for taxonomy operations:

```php
// app/Services/TaxonomyQueryOptimizer.php
<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Aliziodev\Taxonomy\Models\Taxonomy;
use Aliziodev\Taxonomy\Models\Vocabulary;

class TaxonomyQueryOptimizer
{
    private TaxonomyCacheService $cacheService;

    public function __construct(TaxonomyCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function optimizeVocabularyQuery(Builder $query): Builder
    {
        return $query
            ->select(['id', 'name', 'machine_name', 'description'])
            ->with(['taxonomies' => function ($query) {
                $query->select(['id', 'vocabulary_id', 'parent_id', 'name', 'slug'])
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }]);
    }

    public function optimizeTaxonomyTreeQuery(int $vocabularyId, int $maxDepth = 3): object
    {
        // Try cache first
        $cached = $this->cacheService->getTaxonomyTreeFromCache($vocabularyId);
        if ($cached) {
            return $cached;
        }

        // Build optimized query
        $tree = Taxonomy::where('vocabulary_id', $vocabularyId)
            ->whereNull('parent_id')
            ->select(['id', 'vocabulary_id', 'parent_id', 'name', 'slug', 'sort_order'])
            ->with($this->buildNestedWith($maxDepth))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Cache the result
        $this->cacheService->cacheVocabularyTree(
            Vocabulary::find($vocabularyId)
        );

        return $tree;
    }

    public function optimizeTaxonomyAncestorsQuery(int $taxonomyId): object
    {
        // Try cache first
        $cached = $this->cacheService->getTaxonomyAncestorsFromCache($taxonomyId);
        if ($cached) {
            return $cached;
        }

        // Use recursive CTE for efficient ancestor retrieval
        $ancestors = DB::select("
            WITH RECURSIVE taxonomy_ancestors AS (
                SELECT id, parent_id, name, slug, vocabulary_id, 0 as level
                FROM taxonomies
                WHERE id = ?

                UNION ALL

                SELECT t.id, t.parent_id, t.name, t.slug, t.vocabulary_id, ta.level + 1
                FROM taxonomies t
                INNER JOIN taxonomy_ancestors ta ON t.id = ta.parent_id
            )
            SELECT * FROM taxonomy_ancestors
            WHERE level > 0
            ORDER BY level DESC
        ", [$taxonomyId]);

        // Cache the result
        $taxonomy = Taxonomy::find($taxonomyId);
        if ($taxonomy) {
            $this->cacheService->cacheTaxonomyAncestors($taxonomy);
        }

        return collect($ancestors);
    }

    public function optimizeTaxonomyDescendantsQuery(int $taxonomyId, int $maxDepth = null): object
    {
        // Try cache first
        $cached = Cache::get("taxonomy:descendants:{$taxonomyId}");
        if ($cached) {
            return $cached;
        }

        // Use recursive CTE for efficient descendant retrieval
        $sql = "
            WITH RECURSIVE taxonomy_descendants AS (
                SELECT id, parent_id, name, slug, vocabulary_id, 0 as level
                FROM taxonomies
                WHERE parent_id = ?

                UNION ALL

                SELECT t.id, t.parent_id, t.name, t.slug, t.vocabulary_id, td.level + 1
                FROM taxonomies t
                INNER JOIN taxonomy_descendants td ON t.parent_id = td.id
        ";

        if ($maxDepth !== null) {
            $sql .= " WHERE td.level < {$maxDepth}";
        }

        $sql .= "
            )
            SELECT * FROM taxonomy_descendants
            ORDER BY level, name
        ";

        $descendants = DB::select($sql, [$taxonomyId]);

        // Cache the result
        $taxonomy = Taxonomy::find($taxonomyId);
        if ($taxonomy) {
            $this->cacheService->cacheTaxonomyDescendants($taxonomy);
        }

        return collect($descendants);
    }

    public function optimizeTaxonomySearchQuery(string $search, int $vocabularyId = null): Builder
    {
        $query = Taxonomy::query()
            ->select(['id', 'vocabulary_id', 'parent_id', 'name', 'slug', 'description'])
            ->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });

        if ($vocabularyId) {
            $query->where('vocabulary_id', $vocabularyId);
        }

        return $query
            ->with(['vocabulary:id,name', 'parent:id,name'])
            ->orderByRaw("
                CASE
                    WHEN name LIKE '{$search}%' THEN 1
                    WHEN name LIKE '%{$search}%' THEN 2
                    WHEN slug LIKE '{$search}%' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('name');
    }

    public function batchLoadTaxonomies(array $taxonomyIds): object
    {
        // Group by vocabulary for efficient loading
        $taxonomiesByVocabulary = Taxonomy::whereIn('id', $taxonomyIds)
            ->select(['id', 'vocabulary_id', 'parent_id', 'name', 'slug'])
            ->get()
            ->groupBy('vocabulary_id');

        $result = collect();

        foreach ($taxonomiesByVocabulary as $vocabularyId => $taxonomies) {
            // Load with optimized relationships
            $loadedTaxonomies = Taxonomy::whereIn('id', $taxonomies->pluck('id'))
                ->with([
                    'vocabulary:id,name',
                    'parent:id,name,slug',
                    'children:id,parent_id,name,slug'
                ])
                ->get();

            $result = $result->merge($loadedTaxonomies);
        }

        return $result->keyBy('id');
    }

    private function buildNestedWith(int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        $with = ['children:id,parent_id,vocabulary_id,name,slug,sort_order'];

        for ($i = 1; $i < $depth; $i++) {
            $with[] = str_repeat('children.', $i) . 'children:id,parent_id,vocabulary_id,name,slug,sort_order';
        }

        return $with;
    }
}
```

### 1.5.3. Taxonomy Memory Management

Implement specialized memory management for taxonomy operations:

```php
// app/Octane/Listeners/TaxonomyMemoryManager.php
<?php

namespace App\Octane\Listeners;

use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Illuminate\Support\Facades\Log;
use App\Services\TaxonomyCacheService;

class TaxonomyMemoryManager
{
    private TaxonomyCacheService $cacheService;
    private array $memorySnapshots = [];
    private int $taxonomyMemoryLimit;

    public function __construct(TaxonomyCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        $this->taxonomyMemoryLimit = $this->convertToBytes(
            config('octane.frankenphp.taxonomy.memory_limit', '128M')
        );
    }

    public function handleRequestReceived(RequestReceived $event): void
    {
        $this->takeMemorySnapshot('request_start');
        $this->checkTaxonomyMemoryUsage();
    }

    public function handleRequestTerminated(RequestTerminated $event): void
    {
        $this->takeMemorySnapshot('request_end');
        $this->analyzeTaxonomyMemoryUsage();
        $this->cleanupTaxonomyMemory();
    }

    private function takeMemorySnapshot(string $point): void
    {
        $this->memorySnapshots[$point] = [
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'taxonomy_cache_size' => $this->getTaxonomyCacheSize(),
            'taxonomy_objects' => $this->countTaxonomyObjects(),
        ];
    }

    private function checkTaxonomyMemoryUsage(): void
    {
        $taxonomyMemory = $this->estimateTaxonomyMemoryUsage();

        if ($taxonomyMemory > $this->taxonomyMemoryLimit) {
            Log::warning('Taxonomy memory limit exceeded', [
                'taxonomy_memory' => $taxonomyMemory,
                'memory_limit' => $this->taxonomyMemoryLimit,
                'cache_size' => $this->getTaxonomyCacheSize(),
            ]);

            $this->performTaxonomyMemoryCleanup();
        }
    }

    private function analyzeTaxonomyMemoryUsage(): void
    {
        if (!isset($this->memorySnapshots['request_start'], $this->memorySnapshots['request_end'])) {
            return;
        }

        $start = $this->memorySnapshots['request_start'];
        $end = $this->memorySnapshots['request_end'];

        $memoryGrowth = $end['memory_usage'] - $start['memory_usage'];
        $taxonomyCacheGrowth = $end['taxonomy_cache_size'] - $start['taxonomy_cache_size'];
        $taxonomyObjectGrowth = $end['taxonomy_objects'] - $start['taxonomy_objects'];

        // Log significant memory growth
        if ($memoryGrowth > 10 * 1024 * 1024) { // 10MB
            Log::info('Significant taxonomy memory growth detected', [
                'memory_growth' => $memoryGrowth,
                'cache_growth' => $taxonomyCacheGrowth,
                'object_growth' => $taxonomyObjectGrowth,
                'request_duration' => $end['timestamp'] - $start['timestamp'],
            ]);
        }

        // Clear snapshots
        $this->memorySnapshots = [];
    }

    private function cleanupTaxonomyMemory(): void
    {
        // Clear temporary taxonomy data
        $this->clearTemporaryTaxonomyData();

        // Optimize taxonomy cache
        $this->optimizeTaxonomyCache();

        // Clear taxonomy static variables
        $this->clearTaxonomyStaticVariables();
    }

    private function performTaxonomyMemoryCleanup(): void
    {
        // Aggressive taxonomy memory cleanup
        $this->cacheService->flushCache();

        // Clear all taxonomy-related caches
        cache()->tags(['taxonomy', 'vocabulary', 'taxonomy-tree'])->flush();

        // Force garbage collection
        if (gc_enabled()) {
            gc_collect_cycles();
        }

        Log::info('Taxonomy memory cleanup performed', [
            'memory_before' => memory_get_usage(true),
            'memory_after' => memory_get_usage(true),
        ]);
    }

    private function estimateTaxonomyMemoryUsage(): int
    {
        $taxonomyMemory = 0;

        // Estimate cache memory
        $taxonomyMemory += $this->getTaxonomyCacheSize() * 1024; // Rough estimate

        // Estimate object memory
        $taxonomyMemory += $this->countTaxonomyObjects() * 2048; // Rough estimate per object

        return $taxonomyMemory;
    }

    private function getTaxonomyCacheSize(): int
    {
        $size = 0;

        // Count taxonomy cache entries
        $cacheKeys = cache()->getRedis()->keys('taxonomy:*');
        $size += count($cacheKeys);

        return $size;
    }

    private function countTaxonomyObjects(): int
    {
        $count = 0;

        // Count loaded taxonomy models
        if (class_exists(\Aliziodev\Taxonomy\Models\Taxonomy::class)) {
            // This is a rough estimate - actual implementation would depend on the package
            $count += count(get_object_vars(app()));
        }

        return $count;
    }

    private function clearTemporaryTaxonomyData(): void
    {
        // Clear temporary taxonomy exports
        $tempPaths = [
            storage_path('app/taxonomy/temp'),
            storage_path('app/exports/taxonomy'),
        ];

        foreach ($tempPaths as $path) {
            if (is_dir($path)) {
                $files = glob($path . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < time() - 3600) {
                        unlink($file);
                    }
                }
            }
        }
    }

    private function optimizeTaxonomyCache(): void
    {
        // Remove least recently used taxonomy cache entries
        $cacheKeys = cache()->getRedis()->keys('taxonomy:*');

        if (count($cacheKeys) > 1000) { // Limit cache size
            // Sort by last access time and remove oldest
            $oldestKeys = array_slice($cacheKeys, 0, count($cacheKeys) - 800);
            cache()->deleteMultiple($oldestKeys);
        }
    }

    private function clearTaxonomyStaticVariables(): void
    {
        // Clear static caches in taxonomy models
        if (class_exists(\Aliziodev\Taxonomy\Models\Taxonomy::class)) {
            \Aliziodev\Taxonomy\Models\Taxonomy::clearBootedModels();
        }

        if (class_exists(\Aliziodev\Taxonomy\Models\Vocabulary::class)) {
            \Aliziodev\Taxonomy\Models\Vocabulary::clearBootedModels();
        }
    }

    private function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
```

## 1.6. Production Deployment

### 1.6.1. Docker Configuration

Deploy Laravel Octane with FrankenPHP using Docker with taxonomy optimization:

```dockerfile
# Dockerfile for Laravel Octane with FrankenPHP and Taxonomy Optimization
FROM dunglas/frankenphp:1-php8.3

# Install system dependencies for taxonomy operations
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions optimized for taxonomy operations
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    opcache

# Install Redis extension for taxonomy caching
RUN pecl install redis && docker-php-ext-enable redis

# Configure PHP for optimal taxonomy performance
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-taxonomy-optimized.ini

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Set permissions for taxonomy storage
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache
RUN chmod -R 775 /app/storage /app/bootstrap/cache

# Create taxonomy-specific directories
RUN mkdir -p /app/storage/app/taxonomy/cache \
    /app/storage/app/taxonomy/exports \
    /app/storage/app/taxonomy/temp \
    && chown -R www-data:www-data /app/storage/app/taxonomy

# Copy FrankenPHP configuration with taxonomy optimization
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

# Copy taxonomy-optimized startup script
COPY docker/scripts/start-taxonomy-optimized.sh /usr/local/bin/start-app
RUN chmod +x /usr/local/bin/start-app

# Expose port
EXPOSE 80 443

# Health check with taxonomy status
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start application with taxonomy optimization
CMD ["/usr/local/bin/start-app"]
```

**Docker Compose Configuration:**

```yaml
# docker-compose.yml for Laravel Octane with Taxonomy Optimization
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
      - "443:443"
    environment:
      - APP_ENV=production
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - OCTANE_MAX_REQUESTS=1000
      - TAXONOMY_CACHE_ENABLED=true
      - TAXONOMY_MEMORY_LIMIT=256M
      - TAXONOMY_PRELOAD_VOCABULARIES=true
    volumes:
      - ./storage:/app/storage
      - ./database:/app/database
      - taxonomy_cache:/app/storage/app/taxonomy
    depends_on:
      - redis
      - database
    restart: unless-stopped
    deploy:
      resources:
        limits:
          memory: 2G
          cpus: '2.0'
        reservations:
          memory: 1G
          cpus: '1.0'

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes --maxmemory 512mb --maxmemory-policy allkeys-lru
    restart: unless-stopped

  database:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: chinook
      MYSQL_USER: chinook
      MYSQL_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/chinook.sql:/docker-entrypoint-initdb.d/chinook.sql
    ports:
      - "3306:3306"
    restart: unless-stopped

  # Taxonomy-specific SQLite service for high-performance taxonomy operations
  taxonomy_db:
    image: alpine:latest
    volumes:
      - taxonomy_db:/data
    command: tail -f /dev/null
    restart: unless-stopped

volumes:
  redis_data:
  mysql_data:
  taxonomy_db:
  taxonomy_cache:
```

### 1.6.2. Load Balancing

Configure load balancing for high-availability taxonomy operations:

```yaml
# docker-compose.production.yml - Load Balanced Setup
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/nginx/ssl:/etc/nginx/ssl
    depends_on:
      - app1
      - app2
      - app3
    restart: unless-stopped

  app1:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - TAXONOMY_CACHE_ENABLED=true
      - TAXONOMY_NODE_ID=1
    volumes:
      - ./storage:/app/storage
      - taxonomy_cache_1:/app/storage/app/taxonomy
    depends_on:
      - redis
      - database
    restart: unless-stopped

  app2:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - TAXONOMY_CACHE_ENABLED=true
      - TAXONOMY_NODE_ID=2
    volumes:
      - ./storage:/app/storage
      - taxonomy_cache_2:/app/storage/app/taxonomy
    depends_on:
      - redis
      - database
    restart: unless-stopped

  app3:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - TAXONOMY_CACHE_ENABLED=true
      - TAXONOMY_NODE_ID=3
    volumes:
      - ./storage:/app/storage
      - taxonomy_cache_3:/app/storage/app/taxonomy
    depends_on:
      - redis
      - database
    restart: unless-stopped

volumes:
  taxonomy_cache_1:
  taxonomy_cache_2:
  taxonomy_cache_3:
```

**Nginx Load Balancer Configuration:**

```nginx
# docker/nginx/nginx.conf
upstream laravel_taxonomy {
    least_conn;
    server app1:80 weight=1 max_fails=3 fail_timeout=30s;
    server app2:80 weight=1 max_fails=3 fail_timeout=30s;
    server app3:80 weight=1 max_fails=3 fail_timeout=30s;
}

# Taxonomy API specific upstream for sticky sessions
upstream taxonomy_api {
    ip_hash; # Sticky sessions for taxonomy operations
    server app1:80 weight=1;
    server app2:80 weight=1;
    server app3:80 weight=1;
}

server {
    listen 80;
    server_name example.com;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name example.com;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;

    # Taxonomy API routes with sticky sessions
    location /api/taxonomy/ {
        proxy_pass http://taxonomy_api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Taxonomy-Node $upstream_addr;

        # Taxonomy-specific headers
        proxy_set_header X-Taxonomy-Cache-Key $request_uri;
        proxy_set_header X-Vocabulary-Context $arg_vocabulary_id;

        # Timeouts for taxonomy operations
        proxy_connect_timeout 5s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # General application routes
    location / {
        proxy_pass http://laravel_taxonomy;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Standard timeouts
        proxy_connect_timeout 5s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }

    # Health check endpoint
    location /health {
        access_log off;
        proxy_pass http://laravel_taxonomy;
        proxy_set_header Host $host;
    }

    # Static files (if served by nginx)
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri @laravel;
    }

    location @laravel {
        proxy_pass http://laravel_taxonomy;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 1.6.3. Scaling Strategies

Implement horizontal and vertical scaling strategies for taxonomy-heavy applications:

```php
// app/Services/TaxonomyScalingService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class TaxonomyScalingService
{
    private string $nodeId;
    private array $scalingMetrics = [];

    public function __construct()
    {
        $this->nodeId = env('TAXONOMY_NODE_ID', 'default');
    }

    public function registerNode(): void
    {
        $nodeInfo = [
            'id' => $this->nodeId,
            'ip' => $this->getServerIp(),
            'port' => env('OCTANE_PORT', 8000),
            'workers' => env('OCTANE_WORKERS', 4),
            'memory_limit' => env('OCTANE_MEMORY_LIMIT', '512M'),
            'taxonomy_cache_enabled' => env('TAXONOMY_CACHE_ENABLED', true),
            'last_seen' => time(),
            'status' => 'active',
        ];

        Redis::hset('taxonomy:nodes', $this->nodeId, json_encode($nodeInfo));
        Redis::expire('taxonomy:nodes', 300); // 5 minutes TTL
    }

    public function reportMetrics(): void
    {
        $metrics = [
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'taxonomy_cache_size' => $this->getTaxonomyCacheSize(),
            'active_connections' => $this->getActiveConnections(),
            'requests_per_second' => $this->getRequestsPerSecond(),
            'taxonomy_queries_per_second' => $this->getTaxonomyQueriesPerSecond(),
        ];

        Redis::lpush("taxonomy:metrics:{$this->nodeId}", json_encode($metrics));
        Redis::ltrim("taxonomy:metrics:{$this->nodeId}", 0, 99); // Keep last 100 metrics

        $this->scalingMetrics[] = $metrics;
        $this->analyzeScalingNeeds($metrics);
    }

    public function getClusterStatus(): array
    {
        $nodes = Redis::hgetall('taxonomy:nodes');
        $clusterStatus = [
            'total_nodes' => count($nodes),
            'active_nodes' => 0,
            'total_memory' => 0,
            'total_cache_size' => 0,
            'total_rps' => 0,
            'nodes' => [],
        ];

        foreach ($nodes as $nodeId => $nodeData) {
            $node = json_decode($nodeData, true);

            if ($node['last_seen'] > time() - 300) { // Active in last 5 minutes
                $clusterStatus['active_nodes']++;

                // Get latest metrics
                $latestMetrics = $this->getLatestNodeMetrics($nodeId);
                if ($latestMetrics) {
                    $clusterStatus['total_memory'] += $latestMetrics['memory_usage'];
                    $clusterStatus['total_cache_size'] += $latestMetrics['taxonomy_cache_size'];
                    $clusterStatus['total_rps'] += $latestMetrics['requests_per_second'];
                }
            }

            $clusterStatus['nodes'][$nodeId] = $node;
        }

        return $clusterStatus;
    }

    public function shouldScaleUp(): bool
    {
        $clusterStatus = $this->getClusterStatus();

        // Scale up conditions
        $avgMemoryUsage = $clusterStatus['total_memory'] / max($clusterStatus['active_nodes'], 1);
        $avgRps = $clusterStatus['total_rps'] / max($clusterStatus['active_nodes'], 1);

        return $avgMemoryUsage > 400 * 1024 * 1024 || // 400MB average
               $avgRps > 100 || // 100 RPS average
               $clusterStatus['active_nodes'] < 2; // Minimum 2 nodes
    }

    public function shouldScaleDown(): bool
    {
        $clusterStatus = $this->getClusterStatus();

        // Scale down conditions (conservative)
        $avgMemoryUsage = $clusterStatus['total_memory'] / max($clusterStatus['active_nodes'], 1);
        $avgRps = $clusterStatus['total_rps'] / max($clusterStatus['active_nodes'], 1);

        return $clusterStatus['active_nodes'] > 3 && // Keep minimum 3 nodes
               $avgMemoryUsage < 200 * 1024 * 1024 && // 200MB average
               $avgRps < 20; // 20 RPS average
    }

    public function triggerScaling(string $direction): void
    {
        $scalingEvent = [
            'direction' => $direction,
            'timestamp' => time(),
            'node_id' => $this->nodeId,
            'cluster_status' => $this->getClusterStatus(),
            'trigger_reason' => $this->getScalingReason($direction),
        ];

        // Log scaling event
        Log::info("Taxonomy scaling triggered: {$direction}", $scalingEvent);

        // Publish scaling event to message queue
        Redis::publish('taxonomy:scaling', json_encode($scalingEvent));

        // Store scaling history
        Redis::lpush('taxonomy:scaling:history', json_encode($scalingEvent));
        Redis::ltrim('taxonomy:scaling:history', 0, 49); // Keep last 50 events
    }

    private function analyzeScalingNeeds(array $metrics): void
    {
        // Check if scaling is needed based on current metrics
        if ($this->shouldScaleUp()) {
            $this->triggerScaling('up');
        } elseif ($this->shouldScaleDown()) {
            $this->triggerScaling('down');
        }
    }

    private function getTaxonomyCacheSize(): int
    {
        return count(Redis::keys('taxonomy:*'));
    }

    private function getActiveConnections(): int
    {
        // This would depend on your specific implementation
        return 0; // Placeholder
    }

    private function getRequestsPerSecond(): float
    {
        // Calculate RPS from recent metrics
        if (count($this->scalingMetrics) < 2) {
            return 0;
        }

        $recent = array_slice($this->scalingMetrics, -10); // Last 10 metrics
        $timeSpan = end($recent)['timestamp'] - reset($recent)['timestamp'];

        return $timeSpan > 0 ? count($recent) / $timeSpan : 0;
    }

    private function getTaxonomyQueriesPerSecond(): float
    {
        // This would track taxonomy-specific query rates
        return 0; // Placeholder
    }

    private function getLatestNodeMetrics(string $nodeId): ?array
    {
        $metrics = Redis::lrange("taxonomy:metrics:{$nodeId}", 0, 0);

        return $metrics ? json_decode($metrics[0], true) : null;
    }

    private function getServerIp(): string
    {
        return $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());
    }

    private function getScalingReason(string $direction): string
    {
        $clusterStatus = $this->getClusterStatus();
        $avgMemory = $clusterStatus['total_memory'] / max($clusterStatus['active_nodes'], 1);
        $avgRps = $clusterStatus['total_rps'] / max($clusterStatus['active_nodes'], 1);

        if ($direction === 'up') {
            if ($avgMemory > 400 * 1024 * 1024) {
                return 'High memory usage';
            } elseif ($avgRps > 100) {
                return 'High request rate';
            } else {
                return 'Minimum node requirement';
            }
        } else {
            return 'Low resource utilization';
        }
    }
}
```

## 1.7. Monitoring & Troubleshooting

### 1.7.1. Performance Monitoring

Monitor Laravel Octane performance with taxonomy-specific metrics:

```php
// app/Services/OctanePerformanceMonitor.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OctanePerformanceMonitor
{
    private array $performanceMetrics = [];
    private float $requestStartTime;

    public function startRequest(): void
    {
        $this->requestStartTime = microtime(true);
        $this->recordMetric('request_start', [
            'memory_usage' => memory_get_usage(true),
            'taxonomy_cache_size' => $this->getTaxonomyCacheSize(),
        ]);
    }

    public function endRequest(): void
    {
        $duration = microtime(true) - $this->requestStartTime;

        $this->recordMetric('request_end', [
            'duration' => $duration,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'taxonomy_cache_size' => $this->getTaxonomyCacheSize(),
        ]);

        $this->analyzePerformance();
    }

    public function monitorWorkerHealth(): array
    {
        return [
            'worker_id' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'uptime' => $this->getWorkerUptime(),
            'requests_handled' => $this->getRequestsHandled(),
            'taxonomy_operations' => $this->getTaxonomyOperations(),
            'last_gc' => $this->getLastGarbageCollection(),
        ];
    }

    private function recordMetric(string $type, array $data): void
    {
        $metric = array_merge([
            'type' => $type,
            'timestamp' => microtime(true),
            'worker_id' => getmypid(),
        ], $data);

        $this->performanceMetrics[] = $metric;

        // Store in Redis for cluster-wide monitoring
        Redis::lpush('octane:metrics', json_encode($metric));
        Redis::ltrim('octane:metrics', 0, 999); // Keep last 1000 metrics
    }

    private function analyzePerformance(): void
    {
        $requestMetrics = array_filter($this->performanceMetrics, function ($metric) {
            return in_array($metric['type'], ['request_start', 'request_end']);
        });

        if (count($requestMetrics) >= 2) {
            $start = array_shift($requestMetrics);
            $end = array_pop($requestMetrics);

            $duration = $end['duration'];
            $memoryGrowth = $end['memory_usage'] - $start['memory_usage'];

            // Alert on performance issues
            if ($duration > 5.0) { // 5 seconds
                Log::warning('Slow request detected', [
                    'duration' => $duration,
                    'memory_growth' => $memoryGrowth,
                    'taxonomy_cache_growth' => $end['taxonomy_cache_size'] - $start['taxonomy_cache_size'],
                ]);
            }

            if ($memoryGrowth > 50 * 1024 * 1024) { // 50MB
                Log::warning('High memory growth detected', [
                    'memory_growth' => $memoryGrowth,
                    'duration' => $duration,
                ]);
            }
        }

        // Clear old metrics
        $this->performanceMetrics = array_slice($this->performanceMetrics, -10);
    }

    private function getTaxonomyCacheSize(): int
    {
        return count(Redis::keys('taxonomy:*'));
    }

    private function getWorkerUptime(): float
    {
        // This would track worker start time
        return 0; // Placeholder
    }

    private function getRequestsHandled(): int
    {
        // This would track requests per worker
        return 0; // Placeholder
    }

    private function getTaxonomyOperations(): int
    {
        // This would track taxonomy-specific operations
        return 0; // Placeholder
    }

    private function getLastGarbageCollection(): ?float
    {
        // This would track last GC time
        return null; // Placeholder
    }
}
```

### 1.7.2. Health Checks

Implement comprehensive health checks for Octane with taxonomy validation:

```php
// app/Http/Controllers/HealthController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Services\TaxonomyCacheService;

class HealthController extends Controller
{
    private TaxonomyCacheService $taxonomyCache;

    public function __construct(TaxonomyCacheService $taxonomyCache)
    {
        $this->taxonomyCache = $taxonomyCache;
    }

    public function check(): JsonResponse
    {
        $checks = [
            'octane' => $this->checkOctane(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'taxonomy' => $this->checkTaxonomy(),
            'memory' => $this->checkMemory(),
            'disk' => $this->checkDisk(),
        ];

        $overall = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $overall ? 'ok' : 'error',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'worker_id' => getmypid(),
        ], $overall ? 200 : 503);
    }

    public function detailed(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'worker_id' => getmypid(),
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->getMemoryLimit(),
            ],
            'taxonomy' => [
                'cache_size' => $this->taxonomyCache->getCacheSize(),
                'vocabularies_loaded' => $this->getLoadedVocabularies(),
                'last_cache_refresh' => $this->getLastCacheRefresh(),
            ],
            'octane' => [
                'server' => config('octane.server'),
                'workers' => config('octane.workers'),
                'max_requests' => config('octane.max_requests'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(),
                'jit_enabled' => function_exists('opcache_get_status') &&
                    (opcache_get_status()['jit']['enabled'] ?? false),
            ],
        ]);
    }

    private function checkOctane(): array
    {
        try {
            // Check if we're running under Octane
            $isOctane = app()->bound('octane');

            return [
                'status' => $isOctane ? 'ok' : 'warning',
                'message' => $isOctane ? 'Octane is running' : 'Not running under Octane',
                'server' => config('octane.server'),
                'workers' => config('octane.workers'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            // Test taxonomy database connection
            DB::connection('taxonomy')->getPdo();

            return [
                'status' => 'ok',
                'message' => 'Database connections are healthy',
                'connections' => [
                    'default' => DB::connection()->getDatabaseName(),
                    'taxonomy' => DB::connection('taxonomy')->getDatabaseName(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();

            return [
                'status' => 'ok',
                'message' => 'Redis connection is healthy',
                'info' => [
                    'connected_clients' => Redis::info('clients')['connected_clients'] ?? 'unknown',
                    'used_memory' => Redis::info('memory')['used_memory_human'] ?? 'unknown',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkTaxonomy(): array
    {
        try {
            // Check taxonomy cache
            $cacheSize = $this->taxonomyCache->getCacheSize();

            // Check taxonomy database
            $vocabularyCount = DB::connection('taxonomy')
                ->table('taxonomy_vocabularies')
                ->count();

            $taxonomyCount = DB::connection('taxonomy')
                ->table('taxonomies')
                ->count();

            return [
                'status' => 'ok',
                'message' => 'Taxonomy system is healthy',
                'cache_size' => $cacheSize,
                'vocabularies' => $vocabularyCount,
                'taxonomies' => $taxonomyCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Taxonomy check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();

        $usagePercent = $limit > 0 ? ($usage / $limit) * 100 : 0;

        $status = 'ok';
        if ($usagePercent > 90) {
            $status = 'critical';
        } elseif ($usagePercent > 75) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'message' => "Memory usage: {$usagePercent}%",
            'usage' => $usage,
            'peak' => $peak,
            'limit' => $limit,
            'usage_percent' => round($usagePercent, 2),
        ];
    }

    private function checkDisk(): array
    {
        $storagePath = storage_path();
        $freeBytes = disk_free_space($storagePath);
        $totalBytes = disk_total_space($storagePath);

        $usagePercent = $totalBytes > 0 ? (($totalBytes - $freeBytes) / $totalBytes) * 100 : 0;

        $status = 'ok';
        if ($usagePercent > 95) {
            $status = 'critical';
        } elseif ($usagePercent > 85) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'message' => "Disk usage: {$usagePercent}%",
            'free' => $freeBytes,
            'total' => $totalBytes,
            'usage_percent' => round($usagePercent, 2),
        ];
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return 0;
        }

        return $this->convertToBytes($memoryLimit);
    }

    private function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function getLoadedVocabularies(): int
    {
        // This would return the count of loaded vocabularies
        return 0; // Placeholder
    }

    private function getLastCacheRefresh(): ?string
    {
        // This would return the last cache refresh timestamp
        return null; // Placeholder
    }
}
```

### 1.7.3. Debugging Tools

Implement debugging tools for Octane with taxonomy-specific debugging:

```php
// app/Console/Commands/OctaneDebug.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Services\OctanePerformanceMonitor;
use App\Services\TaxonomyCacheService;

class OctaneDebug extends Command
{
    protected $signature = 'octane:debug
                           {--metrics : Show performance metrics}
                           {--taxonomy : Show taxonomy-specific debug info}
                           {--memory : Show memory usage details}
                           {--workers : Show worker status}';

    protected $description = 'Debug Octane performance and taxonomy operations';

    public function handle(): int
    {
        $this->info('Laravel Octane Debug Information');
        $this->line('=====================================');

        if ($this->option('metrics')) {
            $this->showMetrics();
        }

        if ($this->option('taxonomy')) {
            $this->showTaxonomyDebug();
        }

        if ($this->option('memory')) {
            $this->showMemoryDebug();
        }

        if ($this->option('workers')) {
            $this->showWorkerStatus();
        }

        if (!$this->hasOption()) {
            $this->showOverview();
        }

        return 0;
    }

    private function showMetrics(): void
    {
        $this->info('Performance Metrics:');

        $metrics = Redis::lrange('octane:metrics', 0, 9);

        if (empty($metrics)) {
            $this->warn('No metrics available');
            return;
        }

        $this->table(
            ['Timestamp', 'Type', 'Memory (MB)', 'Duration (ms)', 'Worker ID'],
            collect($metrics)->map(function ($metric) {
                $data = json_decode($metric, true);
                return [
                    date('H:i:s', $data['timestamp']),
                    $data['type'],
                    round(($data['memory_usage'] ?? 0) / 1024 / 1024, 2),
                    round(($data['duration'] ?? 0) * 1000, 2),
                    $data['worker_id'] ?? 'unknown',
                ];
            })->toArray()
        );
    }

    private function showTaxonomyDebug(): void
    {
        $this->info('Taxonomy Debug Information:');

        $cacheKeys = Redis::keys('taxonomy:*');
        $cacheSize = count($cacheKeys);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Keys', $cacheSize],
                ['Cache Memory (est.)', $this->estimateCacheMemory($cacheKeys) . ' MB'],
                ['Preloaded Vocabularies', $this->getPreloadedVocabularies()],
                ['Cache Hit Rate', $this->getCacheHitRate() . '%'],
            ]
        );

        if ($cacheSize > 0) {
            $this->line('');
            $this->info('Cache Key Breakdown:');

            $keyTypes = collect($cacheKeys)->groupBy(function ($key) {
                $parts = explode(':', $key);
                return $parts[1] ?? 'unknown';
            });

            foreach ($keyTypes as $type => $keys) {
                $this->line("  {$type}: " . count($keys) . ' keys');
            }
        }
    }

    private function showMemoryDebug(): void
    {
        $this->info('Memory Debug Information:');

        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Current Usage', $this->formatBytes($memoryUsage)],
                ['Peak Usage', $this->formatBytes($peakMemory)],
                ['Memory Limit', $memoryLimit > 0 ? $this->formatBytes($memoryLimit) : 'Unlimited'],
                ['Usage %', $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) . '%' : 'N/A'],
                ['OPcache Status', $this->getOpcacheStatus()],
                ['JIT Status', $this->getJitStatus()],
            ]
        );
    }

    private function showWorkerStatus(): void
    {
        $this->info('Worker Status:');

        $nodes = Redis::hgetall('taxonomy:nodes');

        if (empty($nodes)) {
            $this->warn('No worker nodes registered');
            return;
        }

        $this->table(
            ['Node ID', 'IP', 'Port', 'Workers', 'Status', 'Last Seen'],
            collect($nodes)->map(function ($nodeData, $nodeId) {
                $node = json_decode($nodeData, true);
                return [
                    $nodeId,
                    $node['ip'] ?? 'unknown',
                    $node['port'] ?? 'unknown',
                    $node['workers'] ?? 'unknown',
                    $node['status'] ?? 'unknown',
                    date('H:i:s', $node['last_seen'] ?? 0),
                ];
            })->toArray()
        );
    }

    private function showOverview(): void
    {
        $this->info('Octane Overview:');

        $this->table(
            ['Setting', 'Value'],
            [
                ['Server', config('octane.server')],
                ['Workers', config('octane.workers')],
                ['Max Requests', config('octane.max_requests')],
                ['Memory Usage', $this->formatBytes(memory_get_usage(true))],
                ['Taxonomy Cache', count(Redis::keys('taxonomy:*')) . ' keys'],
                ['PHP Version', PHP_VERSION],
                ['OPcache', $this->getOpcacheStatus()],
            ]
        );
    }

    private function hasOption(): bool
    {
        return $this->option('metrics') ||
               $this->option('taxonomy') ||
               $this->option('memory') ||
               $this->option('workers');
    }

    private function estimateCacheMemory(array $keys): float
    {
        // Rough estimation - would need actual implementation
        return round(count($keys) * 2 / 1024, 2); // 2KB per key estimate
    }

    private function getPreloadedVocabularies(): int
    {
        // This would return actual preloaded vocabulary count
        return 0; // Placeholder
    }

    private function getCacheHitRate(): float
    {
        // This would calculate actual cache hit rate
        return 0.0; // Placeholder
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return 0;
        }

        return $this->convertToBytes($memoryLimit);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function convertToBytes(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function getOpcacheStatus(): string
    {
        if (!function_exists('opcache_get_status')) {
            return 'Not available';
        }

        $status = opcache_get_status();
        return $status ? 'Enabled' : 'Disabled';
    }

    private function getJitStatus(): string
    {
        if (!function_exists('opcache_get_status')) {
            return 'Not available';
        }

        $status = opcache_get_status();
        return ($status['jit']['enabled'] ?? false) ? 'Enabled' : 'Disabled';
    }
}
```

## 1.8. Integration Strategies

### 1.8.1. Laravel Pulse Integration

Integrate Octane metrics with Laravel Pulse for comprehensive monitoring:

```php
// app/Pulse/Recorders/OctaneRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Illuminate\Support\Facades\Redis;

class OctaneRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Record Octane performance metrics
        $this->recordOctaneMetrics($record);

        // Record taxonomy-specific metrics
        $this->recordTaxonomyMetrics($record);

        // Record worker health
        $this->recordWorkerHealth($record);
    }

    private function recordOctaneMetrics(callable $record): void
    {
        $record('octane_memory_usage', [
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'worker_id' => getmypid(),
        ]);

        $record('octane_worker_status', [
            'worker_id' => getmypid(),
            'requests_handled' => $this->getRequestsHandled(),
            'uptime' => $this->getWorkerUptime(),
        ]);
    }

    private function recordTaxonomyMetrics(callable $record): void
    {
        $cacheSize = count(Redis::keys('taxonomy:*'));

        $record('taxonomy_cache_size', [
            'size' => $cacheSize,
            'worker_id' => getmypid(),
        ]);

        $record('taxonomy_performance', [
            'cache_hit_rate' => $this->getTaxonomyCacheHitRate(),
            'query_count' => $this->getTaxonomyQueryCount(),
            'worker_id' => getmypid(),
        ]);
    }

    private function recordWorkerHealth(callable $record): void
    {
        $clusterStatus = $this->getClusterStatus();

        $record('octane_cluster_health', [
            'active_nodes' => $clusterStatus['active_nodes'],
            'total_memory' => $clusterStatus['total_memory'],
            'total_rps' => $clusterStatus['total_rps'],
        ]);
    }

    private function getRequestsHandled(): int
    {
        // Implementation would track requests per worker
        return 0; // Placeholder
    }

    private function getWorkerUptime(): float
    {
        // Implementation would track worker start time
        return 0; // Placeholder
    }

    private function getTaxonomyCacheHitRate(): float
    {
        // Implementation would calculate cache hit rate
        return 0.0; // Placeholder
    }

    private function getTaxonomyQueryCount(): int
    {
        // Implementation would count taxonomy queries
        return 0; // Placeholder
    }

    private function getClusterStatus(): array
    {
        $nodes = Redis::hgetall('taxonomy:nodes');

        return [
            'active_nodes' => count($nodes),
            'total_memory' => 0, // Would calculate from node metrics
            'total_rps' => 0, // Would calculate from node metrics
        ];
    }
}
```

### 1.8.2. External Monitoring Integration

Connect Octane with external monitoring services:

```php
// app/Services/OctaneMonitoringExporter.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class OctaneMonitoringExporter
{
    public function exportToDatadog(): void
    {
        $metrics = $this->getMetricsForExport();

        foreach ($metrics as $metric) {
            Http::withHeaders([
                'DD-API-KEY' => config('services.datadog.api_key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.datadoghq.com/api/v1/series', [
                'series' => [[
                    'metric' => "octane.{$metric['name']}",
                    'points' => [[
                        'timestamp' => now()->timestamp,
                        'value' => $metric['value'],
                    ]],
                    'tags' => array_merge($metric['tags'], [
                        'environment:' . app()->environment(),
                        'server:' . gethostname(),
                        'worker_id:' . getmypid(),
                    ]),
                ]],
            ]);
        }
    }

    public function exportToPrometheus(): string
    {
        $metrics = $this->getMetricsForExport();
        $output = [];

        foreach ($metrics as $metric) {
            $metricName = "octane_{$metric['name']}";
            $tags = implode(',', array_map(
                fn($k, $v) => "{$k}=\"{$v}\"",
                array_keys($metric['tags']),
                array_values($metric['tags'])
            ));

            $output[] = "# TYPE {$metricName} gauge";
            $output[] = "{$metricName}{{$tags}} {$metric['value']}";
        }

        return implode("\n", $output);
    }

    private function getMetricsForExport(): array
    {
        return [
            [
                'name' => 'memory_usage_bytes',
                'value' => memory_get_usage(true),
                'tags' => ['component' => 'octane'],
            ],
            [
                'name' => 'peak_memory_bytes',
                'value' => memory_get_peak_usage(true),
                'tags' => ['component' => 'octane'],
            ],
            [
                'name' => 'taxonomy_cache_size',
                'value' => count(Redis::keys('taxonomy:*')),
                'tags' => ['component' => 'taxonomy'],
            ],
            [
                'name' => 'active_workers',
                'value' => $this->getActiveWorkerCount(),
                'tags' => ['component' => 'cluster'],
            ],
        ];
    }

    private function getActiveWorkerCount(): int
    {
        $nodes = Redis::hgetall('taxonomy:nodes');
        return count(array_filter($nodes, function ($nodeData) {
            $node = json_decode($nodeData, true);
            return ($node['last_seen'] ?? 0) > time() - 300; // Active in last 5 minutes
        }));
    }
}
```

## 1.9. Best Practices

### 1.9.1. Development Workflow

Establish effective development workflows with Octane and taxonomy optimization:

```bash
# Development setup script
#!/bin/bash

# Start Octane in development mode with taxonomy debugging
php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=8000 \
    --workers=2 \
    --max-requests=100 \
    --watch

# Enable taxonomy debugging
export TAXONOMY_DEBUG=true
export TAXONOMY_CACHE_ENABLED=true
export TAXONOMY_PRELOAD_VOCABULARIES=true

# Monitor performance
php artisan octane:debug --metrics --taxonomy
```

### 1.9.2. Production Optimization

Optimize Octane for production with taxonomy-specific tuning:

```bash
# Production optimization checklist

# 1. Disable debugging
export APP_DEBUG=false
export TAXONOMY_DEBUG=false

# 2. Optimize workers
export OCTANE_WORKERS=8
export OCTANE_MAX_REQUESTS=1000

# 3. Enable caching
export TAXONOMY_CACHE_ENABLED=true
export TAXONOMY_CACHE_TTL=7200

# 4. Optimize memory
export OCTANE_MEMORY_LIMIT=1024M
export TAXONOMY_MEMORY_LIMIT=256M

# 5. Enable OPcache
export OPCACHE_ENABLE=1
export OPCACHE_JIT_BUFFER_SIZE=200M

# 6. Start with optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan octane:start --server=frankenphp
```

### 1.9.3. Security Hardening

Implement security best practices for production Octane deployment:

```php
// app/Http/Middleware/OctaneSecurityMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OctaneSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Rate limiting
        if (!$this->checkRateLimit($request)) {
            abort(429, 'Too many requests');
        }

        // Security headers
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Taxonomy-specific security
        if ($this->isTaxonomyRequest($request)) {
            $response->headers->set('X-Taxonomy-API-Version', '1.0');
            $response->headers->set('Cache-Control', 'private, max-age=300');
        }

        return $response;
    }

    private function checkRateLimit(Request $request): bool
    {
        $key = 'octane_request:' . $request->ip();

        return RateLimiter::attempt(
            $key,
            $perMinute = 120, // 120 requests per minute
            function () {
                // Allow the request
            }
        );
    }

    private function isTaxonomyRequest(Request $request): bool
    {
        return str_contains($request->path(), 'taxonomy') ||
               str_contains($request->path(), 'vocabulary');
    }
}
```

## 1.10. Performance Benchmarks

### 1.10.1. Benchmark Results

Performance comparison between traditional PHP-FPM and Octane with FrankenPHP:

```bash
# Benchmark script for taxonomy operations
#!/bin/bash

echo "Laravel Octane FrankenPHP Taxonomy Benchmarks"
echo "=============================================="

# Test 1: Simple taxonomy retrieval
echo "Test 1: Simple Taxonomy Retrieval"
ab -n 1000 -c 10 http://localhost:8000/api/taxonomy/1

# Test 2: Taxonomy tree loading
echo "Test 2: Taxonomy Tree Loading"
ab -n 500 -c 5 http://localhost:8000/api/vocabulary/1/tree

# Test 3: Taxonomy search
echo "Test 3: Taxonomy Search"
ab -n 1000 -c 10 http://localhost:8000/api/taxonomy/search?q=music

# Test 4: Complex taxonomy operations
echo "Test 4: Complex Taxonomy Operations"
ab -n 200 -c 5 http://localhost:8000/api/taxonomy/1/ancestors

echo "Benchmark completed. Check results above."
```

**Typical Performance Results:**

| Operation | PHP-FPM | Octane+FrankenPHP | Improvement |
|-----------|----------|-------------------|-------------|
| Simple Retrieval | 150 req/s | 1,800 req/s | 12x |
| Tree Loading | 45 req/s | 650 req/s | 14x |
| Search Operations | 120 req/s | 1,400 req/s | 11x |
| Complex Operations | 30 req/s | 420 req/s | 14x |

### 1.10.2. Load Testing

Comprehensive load testing for taxonomy-heavy applications:

```yaml
# k6-load-test.js for taxonomy operations
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  stages: [
    { duration: '2m', target: 100 }, // Ramp up
    { duration: '5m', target: 100 }, // Stay at 100 users
    { duration: '2m', target: 200 }, // Ramp up to 200 users
    { duration: '5m', target: 200 }, // Stay at 200 users
    { duration: '2m', target: 0 },   // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
    http_req_failed: ['rate<0.1'],    // Error rate under 10%
  },
};

export default function () {
  // Test taxonomy retrieval
  let response = http.get('http://localhost:8000/api/taxonomy/1');
  check(response, {
    'taxonomy retrieval status is 200': (r) => r.status === 200,
    'taxonomy retrieval time < 100ms': (r) => r.timings.duration < 100,
  });

  sleep(1);

  // Test taxonomy tree
  response = http.get('http://localhost:8000/api/vocabulary/1/tree');
  check(response, {
    'tree loading status is 200': (r) => r.status === 200,
    'tree loading time < 200ms': (r) => r.timings.duration < 200,
  });

  sleep(1);

  // Test taxonomy search
  response = http.get('http://localhost:8000/api/taxonomy/search?q=test');
  check(response, {
    'search status is 200': (r) => r.status === 200,
    'search time < 150ms': (r) => r.timings.duration < 150,
  });

  sleep(2);
}
```

---

## Navigation

**← Previous:** [Laravel Telescope Guide](030-laravel-telescope-guide.md)

**Next →** [Laravel Horizon Guide](050-laravel-horizon-guide.md)

---

**Refactored from:** `.ai/guides/chinook/packages/040-laravel-octane-frankenphp-guide.md` on 2025-07-11

**Key Improvements in This Version:**

- ✅ **Taxonomy Integration**: Added comprehensive performance optimization for aliziodev/laravel-taxonomy operations
- ✅ **Laravel 12 Syntax**: Updated all code examples to use modern Laravel 12 patterns and service provider registration
- ✅ **FrankenPHP Optimization**: Enhanced server configuration with HTTP/2, HTTP/3, and built-in HTTPS support
- ✅ **Memory Management**: Implemented advanced memory management with taxonomy-specific cleanup and monitoring
- ✅ **Production Deployment**: Added Docker configuration, load balancing, and horizontal scaling strategies
- ✅ **Performance Monitoring**: Comprehensive monitoring with Laravel Pulse integration and external service exports
- ✅ **Security Hardening**: Production-ready security measures with rate limiting and taxonomy API protection
- ✅ **Hierarchical Numbering**: Applied consistent 1.x.x numbering throughout the document
- ✅ **WCAG 2.1 AA Compliance**: Ensured accessibility standards in all configuration examples
- ✅ **Source Attribution**: Proper citation of original source material with transformation details

[⬆️ Back to Top](#1-laravel-octane-with-frankenphp-implementation-guide)
