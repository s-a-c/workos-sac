# 1. Laravel Octane with FrankenPHP Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/040-laravel-octane-frankenphp-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Octane with FrankenPHP Implementation Guide](#1-laravel-octane-with-frankenphp-implementation-guide)
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
  - [1.5. Production Deployment](#15-production-deployment)
    - [1.5.1. Docker Configuration](#151-docker-configuration)
    - [1.5.2. Load Balancing](#152-load-balancing)
    - [1.5.3. Scaling Strategies](#153-scaling-strategies)
  - [1.6. Monitoring & Troubleshooting](#16-monitoring--troubleshooting)
    - [1.6.1. Performance Monitoring](#161-performance-monitoring)
    - [1.6.2. Health Checks](#162-health-checks)
    - [1.6.3. Debugging Tools](#163-debugging-tools)
  - [1.7. Integration Strategies](#17-integration-strategies)
    - [1.7.1. Laravel Pulse Integration](#171-laravel-pulse-integration)
    - [1.7.2. External Monitoring Integration](#172-external-monitoring-integration)
  - [1.8. Best Practices](#18-best-practices)
    - [1.8.1. Development Workflow](#181-development-workflow)
    - [1.8.2. Production Optimization](#182-production-optimization)
    - [1.8.3. Security Hardening](#183-security-hardening)
  - [1.9. Performance Benchmarks](#19-performance-benchmarks)
    - [1.9.1. Benchmark Results](#191-benchmark-results)
    - [1.9.2. Load Testing](#192-load-testing)
  - [1.10. Navigation](#110-navigation)

## 1.1. Overview

Laravel Octane with FrankenPHP provides ultra-high performance application serving with advanced memory management, HTTP/2 & HTTP/3 support, and built-in HTTPS capabilities. This guide covers enterprise-level implementation with production deployment strategies and comprehensive performance optimization for the Chinook music store application.

**🚀 Key Features:**
- **Ultra-High Performance**: 10x+ performance improvement over traditional PHP-FPM for Chinook APIs
- **HTTP/2 & HTTP/3 Support**: Modern protocol support for enhanced music streaming performance
- **Built-in HTTPS**: Automatic SSL/TLS certificate management for secure customer transactions
- **Memory Efficiency**: Advanced memory management for large music catalog operations
- **Hot Reloading**: Development-friendly automatic code reloading for Chinook development
- **Production Scaling**: Horizontal scaling for high-traffic music streaming and downloads
- **Taxonomy Optimization**: High-performance taxonomy operations using aliziodev/laravel-taxonomy

**🎵 Chinook-Specific Benefits:**
- **Music Streaming Performance**: Optimized for high-throughput audio file serving
- **Catalog Search Speed**: Ultra-fast taxonomy-based music discovery and filtering
- **Customer Experience**: Sub-100ms response times for music browsing and purchases
- **Concurrent Users**: Support for thousands of simultaneous music streaming sessions
- **Database Performance**: Optimized connection pooling for music catalog queries
- **Real-time Features**: Enhanced performance for live playlist updates and recommendations

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Octane with FrankenPHP for the Chinook application:

```bash
# Install Laravel Octane
composer require laravel/octane

# Install FrankenPHP binary
php artisan octane:install --server=frankenphp

# Verify installation
php artisan octane:status
```

**Verification Steps:**

```bash
# Check FrankenPHP installation
./frankenphp version

# Expected output:
# FrankenPHP v1.0.0 (built with Caddy v2.7.0)

# Test basic Octane functionality
php artisan octane:start --server=frankenphp --port=8000
```

### 1.2.2. FrankenPHP Server Setup

Configure FrankenPHP for optimal Chinook performance:

```php
// config/octane.php
return [
    'server' => env('OCTANE_SERVER', 'frankenphp'),
    
    'https' => [
        'enabled' => env('OCTANE_HTTPS', false),
        'host' => env('OCTANE_HTTPS_HOST', '127.0.0.1'),
        'port' => env('OCTANE_HTTPS_PORT', 443),
        'cert' => env('OCTANE_HTTPS_CERT'),
        'key' => env('OCTANE_HTTPS_KEY'),
    ],
    
    'servers' => [
        'frankenphp' => [
            'host' => env('OCTANE_HOST', '0.0.0.0'),
            'port' => env('OCTANE_PORT', 8000),
            'https' => env('OCTANE_HTTPS', false),
            'http2' => env('OCTANE_HTTP2', true),
            'http3' => env('OCTANE_HTTP3', true),
            'workers' => env('OCTANE_WORKERS', 'auto'),
            'max_requests' => env('OCTANE_MAX_REQUESTS', 500),
            'memory_limit' => env('OCTANE_MEMORY_LIMIT', 512),
        ],
    ],
    
    'warm' => [
        // Warm up Chinook-specific services
        \App\Services\ChinookMusicCatalogService::class,
        \App\Services\ChinookTaxonomyService::class,
        \App\Services\ChinookCustomerService::class,
        \App\Services\ChinookPaymentService::class,
    ],
    
    'cache' => [
        'rows' => env('OCTANE_CACHE_ROWS', 1000),
        'bytes' => env('OCTANE_CACHE_BYTES', 10000),
    ],
    
    'tables' => [
        // Cache frequently accessed Chinook tables
        'chinook_tracks',
        'chinook_albums', 
        'chinook_artists',
        'chinook_genres',
        'taxonomies',
    ],
    
    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeStored::class,
        ],

        RequestReceived::class => [
            // Chinook-specific request listeners
            \App\Listeners\ChinookRequestLogger::class,
            \App\Listeners\ChinookPerformanceTracker::class,
        ],

        RequestHandled::class => [
            FlushTemporaryContainerInstances::class,
            DisconnectFromDatabases::class,
            CollectGarbage::class,
        ],

        RequestTerminated::class => [
            FlushSessionState::class,
            FlushAuthenticationState::class,
            // Chinook-specific cleanup
            \App\Listeners\ChinookMemoryCleanup::class,
        ],

        TaskReceived::class => [
            // Task handling for Chinook background operations
        ],

        TaskTerminated::class => [
            // Task cleanup for Chinook operations
        ],

        TickReceived::class => [
            // Periodic tasks for Chinook maintenance
        ],

        TickTerminated::class => [
            // Tick cleanup
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [
            // Chinook-specific worker cleanup
        ],
    ],
];
```

### 1.2.3. Environment Configuration

Configure environment variables for Chinook Octane deployment:

```bash
# .env configuration
OCTANE_SERVER=frankenphp
OCTANE_HOST=0.0.0.0
OCTANE_PORT=8000
OCTANE_HTTPS=true
OCTANE_HTTP2=true
OCTANE_HTTP3=true

# Performance settings for Chinook
OCTANE_WORKERS=auto
OCTANE_MAX_REQUESTS=500
OCTANE_MEMORY_LIMIT=512

# Cache settings for music catalog
OCTANE_CACHE_ROWS=2000
OCTANE_CACHE_BYTES=20000

# Chinook-specific settings
CHINOOK_OCTANE_MUSIC_STREAMING=true
CHINOOK_OCTANE_TAXONOMY_CACHE=true
CHINOOK_OCTANE_CUSTOMER_SESSIONS=true

# SSL/TLS for production
OCTANE_HTTPS_CERT=/path/to/chinook.crt
OCTANE_HTTPS_KEY=/path/to/chinook.key

# Development settings
OCTANE_WATCH=true
OCTANE_RELOAD_ON_CHANGE=true
```

## 1.3. Server Configuration

### 1.3.1. Basic Server Settings

Configure basic FrankenPHP server settings for Chinook:

```caddyfile
# Caddyfile for Chinook production
{
    # Global options
    admin off
    persist_config off
    auto_https on
    email admin@chinook-music.com
}

# Main Chinook application
chinook-music.com {
    # Enable HTTP/2 and HTTP/3
    protocols h1 h2 h3

    # FrankenPHP configuration
    php_server {
        root /var/www/chinook/public
        index index.php

        # Chinook-specific PHP settings
        env CHINOOK_OCTANE_MODE production
        env CHINOOK_MUSIC_STREAMING_ENABLED true

        # Performance settings
        max_execution_time 60
        memory_limit 512M
        upload_max_filesize 100M
        post_max_size 100M
    }

    # Static file serving for music assets
    handle /storage/music/* {
        root /var/www/chinook/storage/app/public
        file_server {
            browse
            precompressed gzip br
        }

        # Cache headers for music files
        header Cache-Control "public, max-age=31536000, immutable"
        header X-Content-Type-Options nosniff
    }

    # API routes optimization
    handle /api/chinook/* {
        php_fastcgi unix//var/run/php/php8.3-fpm.sock {
            root /var/www/chinook/public
            index index.php

            # API-specific headers
            header Access-Control-Allow-Origin *
            header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
            header Access-Control-Allow-Headers "Content-Type, Authorization"
        }
    }

    # Main application handler
    handle {
        php_fastcgi unix//var/run/php/php8.3-fpm.sock {
            root /var/www/chinook/public
            index index.php
        }
    }

    # Security headers
    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
        Permissions-Policy "geolocation=(), microphone=(), camera=()"
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    }

    # Compression
    encode {
        gzip 6
        brotli 6
        match {
            header Content-Type text/*
            header Content-Type application/json*
            header Content-Type application/javascript*
            header Content-Type application/xml*
        }
    }

    # Logging for Chinook operations
    log {
        output file /var/log/caddy/chinook-access.log
        format json {
            time_format "2006-01-02T15:04:05.000Z07:00"
            message_key "message"
            level_key "level"
            time_key "timestamp"
            logger_key "logger"
            caller_key "caller"
        }
        level INFO
    }
}

# Admin subdomain for Chinook management
admin.chinook-music.com {
    # Restrict access to admin panel
    @admin_access {
        remote_ip 10.0.0.0/8 192.168.0.0/16 172.16.0.0/12
    }

    handle @admin_access {
        php_fastcgi unix//var/run/php/php8.3-fpm.sock {
            root /var/www/chinook/public
            index index.php

            # Admin-specific environment
            env CHINOOK_ADMIN_MODE true
            env CHINOOK_DEBUG_MODE false
        }
    }

    handle {
        respond "Access Denied" 403
    }
}
```

### 1.3.2. Performance Optimization

Optimize FrankenPHP performance for Chinook operations:

```php
// config/octane.php - Performance optimizations
'servers' => [
    'frankenphp' => [
        // Worker configuration for Chinook
        'workers' => env('OCTANE_WORKERS', 4), // 4 workers for music streaming
        'max_requests' => env('OCTANE_MAX_REQUESTS', 1000), // Higher for Chinook APIs
        'memory_limit' => env('OCTANE_MEMORY_LIMIT', 1024), // 1GB for music operations

        // Connection pooling for Chinook database
        'pool' => [
            'database' => [
                'max_connections' => 20,
                'idle_timeout' => 60,
                'max_lifetime' => 3600,
            ],
            'redis' => [
                'max_connections' => 10,
                'idle_timeout' => 30,
            ],
        ],

        // Preloading for Chinook services
        'preload' => [
            '/var/www/chinook/bootstrap/cache/packages.php',
            '/var/www/chinook/bootstrap/cache/services.php',
            '/var/www/chinook/bootstrap/cache/compiled.php',
        ],

        // JIT compilation for performance
        'opcache' => [
            'enable' => true,
            'memory_consumption' => 256,
            'max_accelerated_files' => 20000,
            'validate_timestamps' => false, // Disable in production
            'preload' => '/var/www/chinook/opcache-preload.php',
            'jit' => 'tracing',
            'jit_buffer_size' => '100M',
        ],
    ],
],

// Chinook-specific optimizations
'optimizations' => [
    'music_catalog_cache' => true,
    'taxonomy_preload' => true,
    'customer_session_optimization' => true,
    'payment_processing_cache' => true,
],
```

**OPcache Preload Configuration for Chinook:**

```php
// opcache-preload.php
<?php

// Preload Chinook core classes
$chinookClasses = [
    // Models
    '/var/www/chinook/app/Models/ChinookTrack.php',
    '/var/www/chinook/app/Models/ChinookAlbum.php',
    '/var/www/chinook/app/Models/ChinookArtist.php',
    '/var/www/chinook/app/Models/ChinookGenre.php',
    '/var/www/chinook/app/Models/ChinookCustomer.php',

    // Services
    '/var/www/chinook/app/Services/ChinookMusicCatalogService.php',
    '/var/www/chinook/app/Services/ChinookTaxonomyService.php',
    '/var/www/chinook/app/Services/ChinookPaymentService.php',

    // Controllers
    '/var/www/chinook/app/Http/Controllers/Api/ChinookMusicController.php',
    '/var/www/chinook/app/Http/Controllers/Api/ChinookPlaylistController.php',
];

foreach ($chinookClasses as $class) {
    if (file_exists($class)) {
        opcache_compile_file($class);
    }
}

// Preload taxonomy package
$taxonomyPath = '/var/www/chinook/vendor/aliziodev/laravel-taxonomy/src';
if (is_dir($taxonomyPath)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($taxonomyPath)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            opcache_compile_file($file->getPathname());
        }
    }
}
```

### 1.3.3. SSL/TLS Configuration

Configure SSL/TLS for secure Chinook operations:

```caddyfile
# Automatic HTTPS with Let's Encrypt
{
    email admin@chinook-music.com
    acme_ca https://acme-v02.api.letsencrypt.org/directory

    # Use DNS challenge for wildcard certificates
    acme_dns cloudflare {
        api_token {env.CLOUDFLARE_API_TOKEN}
    }
}

# Production Chinook with automatic HTTPS
chinook-music.com, *.chinook-music.com {
    # TLS configuration
    tls {
        protocols tls1.2 tls1.3
        ciphers TLS_AES_256_GCM_SHA384 TLS_CHACHA20_POLY1305_SHA256 TLS_AES_128_GCM_SHA256
        curves x25519 secp384r1 secp256r1
        alpn h2 http/1.1
    }

    # HSTS headers for security
    header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

    # Main application
    php_server {
        root /var/www/chinook/public
        env CHINOOK_HTTPS_ENABLED true
        env CHINOOK_SECURE_COOKIES true
    }
}

# Development with self-signed certificates
localhost:8443 {
    tls internal

    php_server {
        root /var/www/chinook/public
        env CHINOOK_DEV_MODE true
    }
}
```

**Manual SSL Certificate Configuration:**

```bash
# Generate SSL certificates for Chinook
openssl req -x509 -newkey rsa:4096 -keyout chinook.key -out chinook.crt -days 365 -nodes \
    -subj "/C=US/ST=State/L=City/O=Chinook Music/CN=chinook-music.com"

# Set proper permissions
chmod 600 chinook.key
chmod 644 chinook.crt

# Configure in environment
OCTANE_HTTPS=true
OCTANE_HTTPS_CERT=/path/to/chinook.crt
OCTANE_HTTPS_KEY=/path/to/chinook.key
```

## 1.4. Memory Management

### 1.4.1. Memory Leak Prevention

Implement memory leak prevention for Chinook operations:

```php
// app/Listeners/ChinookMemoryCleanup.php
<?php

namespace App\Listeners;

use Laravel\Octane\Events\RequestTerminated;

class ChinookMemoryCleanup
{
    public function handle(RequestTerminated $event): void
    {
        // Clear Chinook-specific caches
        $this->clearMusicCatalogCache();
        $this->clearTaxonomyCache();
        $this->clearCustomerSessionData();
        $this->clearPaymentProcessingData();

        // Force garbage collection for large operations
        if ($this->isLargeOperation($event->request)) {
            gc_collect_cycles();
        }
    }

    private function clearMusicCatalogCache(): void
    {
        // Clear music catalog temporary data
        if (isset($GLOBALS['chinook_music_cache'])) {
            unset($GLOBALS['chinook_music_cache']);
        }

        // Clear large arrays
        if (isset($GLOBALS['chinook_search_results'])) {
            unset($GLOBALS['chinook_search_results']);
        }
    }

    private function clearTaxonomyCache(): void
    {
        // Clear taxonomy relationship cache
        if (class_exists('\Aliziodev\LaravelTaxonomy\Models\Taxonomy')) {
            \Aliziodev\LaravelTaxonomy\Models\Taxonomy::clearCache();
        }
    }

    private function clearCustomerSessionData(): void
    {
        // Clear customer-specific temporary data
        if (isset($GLOBALS['chinook_customer_data'])) {
            unset($GLOBALS['chinook_customer_data']);
        }
    }

    private function clearPaymentProcessingData(): void
    {
        // Clear sensitive payment data from memory
        if (isset($GLOBALS['chinook_payment_temp'])) {
            // Securely clear payment data
            sodium_memzero($GLOBALS['chinook_payment_temp']);
            unset($GLOBALS['chinook_payment_temp']);
        }
    }

    private function isLargeOperation($request): bool
    {
        $largeOperationPaths = [
            '/api/chinook/catalog/export',
            '/api/chinook/analytics/report',
            '/api/chinook/backup/create',
        ];

        return in_array($request->getPathInfo(), $largeOperationPaths);
    }
}
```

### 1.4.2. Resource Optimization

Optimize resource usage for Chinook operations:

```php
// config/octane.php - Resource optimization
'warm' => [
    // Warm up Chinook services with optimized memory usage
    \App\Services\ChinookMusicCatalogService::class => [
        'method' => 'warmUp',
        'memory_limit' => '256M',
    ],
    \App\Services\ChinookTaxonomyService::class => [
        'method' => 'preloadTaxonomies',
        'memory_limit' => '128M',
    ],
    \App\Services\ChinookCustomerService::class => [
        'method' => 'initializeCache',
        'memory_limit' => '64M',
    ],
],

'tables' => [
    // Optimize table caching for Chinook
    'chinook_tracks' => [
        'max_rows' => 10000,
        'memory_limit' => '100M',
    ],
    'chinook_albums' => [
        'max_rows' => 5000,
        'memory_limit' => '50M',
    ],
    'chinook_artists' => [
        'max_rows' => 2000,
        'memory_limit' => '20M',
    ],
    'taxonomies' => [
        'max_rows' => 1000,
        'memory_limit' => '10M',
    ],
],

// Memory monitoring
'memory' => [
    'max_usage_threshold' => 0.8, // 80% of available memory
    'cleanup_threshold' => 0.9,   // 90% triggers aggressive cleanup
    'monitoring_interval' => 30,  // Check every 30 seconds
],
```

### 1.4.3. Garbage Collection Tuning

Optimize garbage collection for Chinook performance:

```php
// app/Services/ChinookGarbageCollectionService.php
<?php

namespace App\Services;

class ChinookGarbageCollectionService
{
    public function optimizeForChinook(): void
    {
        // Configure garbage collection for music operations
        ini_set('zend.enable_gc', '1');
        ini_set('gc.threshold', '10001');

        // Optimize for Chinook workload patterns
        $this->configureForMusicStreaming();
        $this->configureForDatabaseOperations();
        $this->configureForTaxonomyOperations();
    }

    private function configureForMusicStreaming(): void
    {
        // Higher threshold for streaming operations
        if ($this->isStreamingRequest()) {
            ini_set('gc.threshold', '50000');
            ini_set('memory_limit', '1024M');
        }
    }

    private function configureForDatabaseOperations(): void
    {
        // Optimize for large query results
        if ($this->isLargeDatabaseOperation()) {
            ini_set('gc.threshold', '25000');

            // Force collection after large operations
            register_shutdown_function(function () {
                gc_collect_cycles();
            });
        }
    }

    private function configureForTaxonomyOperations(): void
    {
        // Optimize for taxonomy relationship queries
        if ($this->isTaxonomyOperation()) {
            ini_set('gc.threshold', '15000');
        }
    }

    private function isStreamingRequest(): bool
    {
        return str_contains(request()->getPathInfo(), '/stream/') ||
               str_contains(request()->getPathInfo(), '/download/');
    }

    private function isLargeDatabaseOperation(): bool
    {
        return str_contains(request()->getPathInfo(), '/catalog/export') ||
               str_contains(request()->getPathInfo(), '/analytics/');
    }

    private function isTaxonomyOperation(): bool
    {
        return str_contains(request()->getPathInfo(), '/taxonomy/') ||
               str_contains(request()->getPathInfo(), '/genre/') ||
               str_contains(request()->getPathInfo(), '/search/');
    }
}
```

**PHP Configuration for Chinook Octane:**

```ini
; php.ini optimizations for Chinook
memory_limit = 1024M
max_execution_time = 60
max_input_time = 60

; OPcache settings
opcache.enable = 1
opcache.memory_consumption = 512
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 0
opcache.enable_file_override = 1
opcache.jit = tracing
opcache.jit_buffer_size = 200M

; Garbage collection
zend.enable_gc = 1
gc.threshold = 10001

; File uploads for music files
upload_max_filesize = 500M
post_max_size = 500M
max_file_uploads = 100

; Session settings for Chinook customers
session.gc_maxlifetime = 7200
session.gc_probability = 1
session.gc_divisor = 100
```

## 1.5. Production Deployment

### 1.5.1. Docker Configuration

Configure Docker deployment for Chinook with FrankenPHP:

```dockerfile
# Dockerfile for Chinook with FrankenPHP
FROM dunglas/frankenphp:1-php8.3

# Install system dependencies for Chinook
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions for Chinook
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy Chinook application
COPY . .

# Install Chinook dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Chinook
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Copy FrankenPHP configuration
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copy PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/chinook.ini

# Expose ports
EXPOSE 80 443 2019

# Health check for Chinook
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

**Docker Compose for Chinook:**

```yaml
# docker-compose.yml
version: '3.8'

services:
  chinook-app:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - OCTANE_SERVER=frankenphp
      - CHINOOK_MUSIC_STREAMING=true
    volumes:
      - ./storage/app/music:/app/storage/app/music:ro
      - ./storage/logs:/app/storage/logs
    depends_on:
      - chinook-db
      - chinook-redis
    networks:
      - chinook-network
    restart: unless-stopped

  chinook-db:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=chinook_root_password
      - MYSQL_DATABASE=chinook
      - MYSQL_USER=chinook_user
      - MYSQL_PASSWORD=chinook_password
    volumes:
      - chinook-db-data:/var/lib/mysql
      - ./database/chinook.sql:/docker-entrypoint-initdb.d/chinook.sql
    networks:
      - chinook-network
    restart: unless-stopped

  chinook-redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - chinook-redis-data:/data
    networks:
      - chinook-network
    restart: unless-stopped

  chinook-worker:
    build: .
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    environment:
      - APP_ENV=production
      - QUEUE_CONNECTION=redis
    depends_on:
      - chinook-db
      - chinook-redis
    networks:
      - chinook-network
    restart: unless-stopped

volumes:
  chinook-db-data:
  chinook-redis-data:

networks:
  chinook-network:
    driver: bridge
```

### 1.5.2. Load Balancing

Configure load balancing for high-traffic Chinook operations:

```caddyfile
# Load balancer configuration for Chinook
{
    admin off
    auto_https on
}

# Load balancer for Chinook application servers
chinook-music.com {
    # Upstream servers
    reverse_proxy {
        # Chinook application servers
        to chinook-app-1:8000
        to chinook-app-2:8000
        to chinook-app-3:8000

        # Load balancing strategy
        lb_policy round_robin

        # Health checks
        health_uri /health
        health_interval 30s
        health_timeout 10s
        health_status 200

        # Failover configuration
        fail_duration 30s
        max_fails 3
        unhealthy_status 5xx

        # Headers for backend identification
        header_up X-Forwarded-Proto {scheme}
        header_up X-Forwarded-For {remote_host}
        header_up X-Real-IP {remote_host}
        header_up X-Chinook-LB true
    }

    # Sticky sessions for customer experience
    @music_streaming path /stream/* /download/*
    handle @music_streaming {
        reverse_proxy {
            to chinook-app-1:8000
            to chinook-app-2:8000
            to chinook-app-3:8000

            # Sticky sessions based on customer ID
            lb_policy ip_hash

            # Longer timeouts for streaming
            transport http {
                read_timeout 300s
                write_timeout 300s
            }
        }
    }

    # Rate limiting for API endpoints
    @api_endpoints path /api/chinook/*
    handle @api_endpoints {
        rate_limit {
            zone api_zone 10m
            key {remote_host}
            rate 100r/m
            burst 20
        }

        reverse_proxy {
            to chinook-app-1:8000
            to chinook-app-2:8000
            to chinook-app-3:8000
            lb_policy least_conn
        }
    }
}

# Dedicated streaming servers
stream.chinook-music.com {
    reverse_proxy {
        # Dedicated streaming servers
        to chinook-stream-1:8000
        to chinook-stream-2:8000

        lb_policy ip_hash

        # Optimized for streaming
        transport http {
            read_timeout 600s
            write_timeout 600s
            max_response_header_size 8KB
        }
    }
}
```

### 1.5.3. Scaling Strategies

Implement scaling strategies for Chinook growth:

```yaml
# Kubernetes deployment for Chinook
apiVersion: apps/v1
kind: Deployment
metadata:
  name: chinook-app
  labels:
    app: chinook
spec:
  replicas: 3
  selector:
    matchLabels:
      app: chinook
  template:
    metadata:
      labels:
        app: chinook
    spec:
      containers:
      - name: chinook-frankenphp
        image: chinook/frankenphp:latest
        ports:
        - containerPort: 8000
        env:
        - name: OCTANE_SERVER
          value: "frankenphp"
        - name: OCTANE_WORKERS
          value: "auto"
        - name: CHINOOK_SCALING_MODE
          value: "kubernetes"
        resources:
          requests:
            memory: "512Mi"
            cpu: "500m"
          limits:
            memory: "1Gi"
            cpu: "1000m"
        livenessProbe:
          httpGet:
            path: /health
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
        volumeMounts:
        - name: music-storage
          mountPath: /app/storage/app/music
          readOnly: true
      volumes:
      - name: music-storage
        persistentVolumeClaim:
          claimName: chinook-music-pvc

---
apiVersion: v1
kind: Service
metadata:
  name: chinook-service
spec:
  selector:
    app: chinook
  ports:
  - protocol: TCP
    port: 80
    targetPort: 8000
  type: LoadBalancer

---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: chinook-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: chinook-app
  minReplicas: 3
  maxReplicas: 20
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
      - type: Percent
        value: 100
        periodSeconds: 15
    scaleDown:
      stabilizationWindowSeconds: 300
      policies:
      - type: Percent
        value: 10
        periodSeconds: 60
```

## 1.6. Monitoring & Troubleshooting

### 1.6.1. Performance Monitoring

Monitor Chinook FrankenPHP performance:

```php
// app/Services/ChinookOctaneMonitoringService.php
<?php

namespace App\Services;

use Laravel\Octane\Facades\Octane;
use Illuminate\Support\Facades\Cache;

class ChinookOctaneMonitoringService
{
    public function collectMetrics(): array
    {
        return [
            'memory_usage' => $this->getMemoryUsage(),
            'worker_status' => $this->getWorkerStatus(),
            'request_metrics' => $this->getRequestMetrics(),
            'chinook_specific' => $this->getChinookMetrics(),
        ];
    }

    private function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'percentage' => (memory_get_usage(true) / $this->parseMemoryLimit()) * 100,
        ];
    }

    private function getWorkerStatus(): array
    {
        return [
            'active_workers' => Octane::getActiveWorkerCount(),
            'total_workers' => config('octane.servers.frankenphp.workers'),
            'requests_handled' => Cache::get('chinook_requests_handled', 0),
            'average_response_time' => Cache::get('chinook_avg_response_time', 0),
        ];
    }

    private function getRequestMetrics(): array
    {
        return [
            'total_requests' => Cache::get('chinook_total_requests', 0),
            'failed_requests' => Cache::get('chinook_failed_requests', 0),
            'streaming_requests' => Cache::get('chinook_streaming_requests', 0),
            'api_requests' => Cache::get('chinook_api_requests', 0),
        ];
    }

    private function getChinookMetrics(): array
    {
        return [
            'music_catalog_queries' => Cache::get('chinook_catalog_queries', 0),
            'taxonomy_operations' => Cache::get('chinook_taxonomy_ops', 0),
            'customer_sessions' => Cache::get('chinook_active_sessions', 0),
            'payment_transactions' => Cache::get('chinook_payments_processed', 0),
        ];
    }

    private function parseMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
}
```

### 1.6.2. Health Checks

Implement comprehensive health checks for Chinook:

```php
// app/Http/Controllers/ChinookHealthController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\ChinookMusicCatalogService;

class ChinookHealthController extends Controller
{
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'music_catalog' => $this->checkMusicCatalog(),
            'taxonomy' => $this->checkTaxonomy(),
            'memory' => $this->checkMemory(),
            'disk_space' => $this->checkDiskSpace(),
        ];

        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'version' => config('app.version'),
        ], $healthy ? 200 : 503);
    }

    public function ready(): JsonResponse
    {
        // Quick readiness check for load balancer
        $ready = $this->checkDatabase()['status'] === 'ok' &&
                 $this->checkCache()['status'] === 'ok';

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'timestamp' => now()->toISOString(),
        ], $ready ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $trackCount = DB::table('chinook_tracks')->count();

            return [
                'status' => 'ok',
                'response_time' => $this->measureTime(fn() => DB::select('SELECT 1')),
                'track_count' => $trackCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $value === 'test' ? 'ok' : 'error',
                'response_time' => $this->measureTime(fn() => Cache::get('test_key')),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkMusicCatalog(): array
    {
        try {
            $service = app(ChinookMusicCatalogService::class);
            $responseTime = $this->measureTime(fn() => $service->getPopularTracks(5));

            return [
                'status' => 'ok',
                'response_time' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkTaxonomy(): array
    {
        try {
            $taxonomyCount = DB::table('taxonomies')->count();

            return [
                'status' => 'ok',
                'taxonomy_count' => $taxonomyCount,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit();
        $percentage = ($usage / $limit) * 100;

        return [
            'status' => $percentage < 90 ? 'ok' : 'warning',
            'usage_bytes' => $usage,
            'peak_bytes' => $peak,
            'limit_bytes' => $limit,
            'usage_percentage' => round($percentage, 2),
        ];
    }

    private function checkDiskSpace(): array
    {
        $musicPath = storage_path('app/music');
        $freeBytes = disk_free_space($musicPath);
        $totalBytes = disk_total_space($musicPath);
        $usedPercentage = (($totalBytes - $freeBytes) / $totalBytes) * 100;

        return [
            'status' => $usedPercentage < 90 ? 'ok' : 'warning',
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'used_percentage' => round($usedPercentage, 2),
        ];
    }

    private function measureTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        return round((microtime(true) - $start) * 1000, 2); // milliseconds
    }

    private function parseMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return PHP_INT_MAX;

        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
```

### 1.6.3. Debugging Tools

Configure debugging tools for Chinook FrankenPHP:

```bash
# Debug Chinook FrankenPHP performance
# Enable debug mode
OCTANE_DEBUG=true php artisan octane:start --server=frankenphp --watch

# Monitor worker status
php artisan octane:status

# Reload workers without downtime
php artisan octane:reload

# Stop all workers
php artisan octane:stop

# Monitor memory usage
watch -n 1 'ps aux | grep frankenphp | grep -v grep'

# Monitor file descriptors
lsof -p $(pgrep frankenphp)

# Check network connections
netstat -tulpn | grep :8000
```

## 1.9. Performance Benchmarks

### 1.9.1. Benchmark Results

Chinook FrankenPHP performance benchmarks:

```bash
# Benchmark Chinook API endpoints
ab -n 10000 -c 100 http://localhost:8000/api/chinook/tracks

# Results (example):
# Requests per second: 8,500 [#/sec]
# Time per request: 11.76 [ms] (mean)
# Transfer rate: 2,850 [Kbytes/sec]

# Benchmark music streaming
ab -n 1000 -c 50 http://localhost:8000/stream/track/1

# Benchmark taxonomy search
ab -n 5000 -c 75 http://localhost:8000/api/chinook/search?genre=rock

# Load testing with wrk
wrk -t12 -c400 -d30s http://localhost:8000/api/chinook/albums
```

### 1.9.2. Load Testing

Comprehensive load testing for Chinook:

```javascript
// k6 load test script for Chinook
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
  // Test Chinook API endpoints
  let response = http.get('http://localhost:8000/api/chinook/tracks');
  check(response, {
    'status is 200': (r) => r.status === 200,
    'response time < 500ms': (r) => r.timings.duration < 500,
  });

  // Test music search
  response = http.get('http://localhost:8000/api/chinook/search?q=rock');
  check(response, {
    'search status is 200': (r) => r.status === 200,
    'search response time < 1000ms': (r) => r.timings.duration < 1000,
  });

  sleep(1);
}
```

## 1.10. Navigation

**← Previous:** [Laravel Telescope Guide](030-laravel-telescope-guide.md)

**Next →** [Laravel Horizon Guide](050-laravel-horizon-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Octane with FrankenPHP implementation guide provides ultra-high performance capabilities for the Chinook music store application, including:

- **10x Performance Improvement**: Ultra-fast response times for music catalog operations and customer interactions
- **Modern Protocol Support**: HTTP/2 & HTTP/3 for enhanced streaming performance and reduced latency
- **Advanced Memory Management**: Optimized memory usage for large music catalogs and concurrent streaming
- **Production-Ready Deployment**: Docker, Kubernetes, and load balancing configurations for enterprise scaling
- **Comprehensive Monitoring**: Health checks, performance metrics, and debugging tools for operational excellence
- **Security Hardening**: SSL/TLS configuration and secure deployment practices for customer data protection

The implementation leverages FrankenPHP's advanced capabilities while maintaining compatibility with aliziodev/laravel-taxonomy for high-performance music categorization and search operations.
