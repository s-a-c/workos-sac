# Laravel Octane with FrankenPHP Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. FrankenPHP Server Setup](#12-frankenphp-server-setup)
  - [1.3. Environment Configuration](#13-environment-configuration)
- [Server Configuration](#server-configuration)
  - [2.1. Basic Server Settings](#21-basic-server-settings)
  - [2.2. Performance Optimization](#22-performance-optimization)
  - [2.3. SSL/TLS Configuration](#23-ssltls-configuration)
- [Memory Management](#memory-management)
  - [3.1. Memory Leak Prevention](#31-memory-leak-prevention)
  - [3.2. Resource Optimization](#32-resource-optimization)
  - [3.3. Garbage Collection Tuning](#33-garbage-collection-tuning)
- [Production Deployment](#production-deployment)
  - [4.1. Docker Configuration](#41-docker-configuration)
  - [4.2. Load Balancing](#42-load-balancing)
  - [4.3. Scaling Strategies](#43-scaling-strategies)
- [Monitoring & Troubleshooting](#monitoring--troubleshooting)
  - [5.1. Performance Monitoring](#51-performance-monitoring)
  - [5.2. Health Checks](#52-health-checks)
  - [5.3. Debugging Tools](#53-debugging-tools)
- [Integration Strategies](#integration-strategies)
  - [6.1. Laravel Pulse Integration](#61-laravel-pulse-integration)
  - [6.2. External Monitoring Integration](#62-external-monitoring-integration)
- [Best Practices](#best-practices)
  - [7.1. Development Workflow](#71-development-workflow)
  - [7.2. Production Optimization](#72-production-optimization)
  - [7.3. Security Hardening](#73-security-hardening)
- [Performance Benchmarks](#performance-benchmarks)
  - [8.1. Benchmark Results](#81-benchmark-results)
  - [8.2. Load Testing](#82-load-testing)
- [Navigation](#navigation)

## Overview

Laravel Octane with FrankenPHP provides ultra-high performance application serving with advanced memory management, HTTP/2 & HTTP/3 support, and built-in HTTPS capabilities. This guide covers enterprise-level implementation with production deployment strategies and comprehensive performance optimization.

**🚀 Key Features:**
- **Ultra-High Performance**: 10x+ performance improvement over traditional PHP-FPM
- **HTTP/2 & HTTP/3 Support**: Modern protocol support for enhanced performance
- **Built-in HTTPS**: Automatic SSL/TLS certificate management with Let's Encrypt
- **Memory Efficiency**: Advanced memory management and leak prevention
- **Hot Reloading**: Development-friendly automatic code reloading
- **Production Scaling**: Horizontal scaling and load balancing capabilities

## Installation & Setup

### 1.1. Package Installation

Install Laravel Octane with FrankenPHP:

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

### 1.2. FrankenPHP Server Setup

Configure FrankenPHP for optimal performance:

```bash
# Create FrankenPHP configuration directory
sudo mkdir -p /etc/frankenphp
sudo mkdir -p /var/log/frankenphp

# Create Caddyfile for FrankenPHP
sudo tee /etc/frankenphp/Caddyfile << 'EOF'
{
    # Global options
    auto_https off
    admin off
    
    # Logging
    log {
        output file /var/log/frankenphp/access.log
        format json
    }
    
    # Error handling
    handle_errors {
        respond "Server Error: {http.error.status_code}" {http.error.status_code}
    }
}

# Laravel application
:80 {
    # Document root
    root * /var/www/html/public
    
    # Enable compression
    encode gzip zstd
    
    # PHP handler
    php_server {
        # Worker configuration
        num_threads 4
        
        # Memory limits
        memory_limit 256M
        max_execution_time 30
        
        # Error reporting
        display_errors off
        log_errors on
        error_log /var/log/frankenphp/php_errors.log
    }
    
    # Static file handling
    file_server {
        hide .htaccess
        hide .env
    }
    
    # Security headers
    header {
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
    }
}
EOF
```

### 1.3. Environment Configuration

Configure environment variables for Octane:

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
OCTANE_MEMORY_LIMIT=256M
OCTANE_GC_ENABLED=true
OCTANE_GC_THRESHOLD=50
```

## Server Configuration

### 2.1. Basic Server Settings

Configure Octane for your application:

```php
// config/octane.php
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
    ],
    
    'cache' => [
        'tables' => [
            'users',
            'posts',
            'categories',
        ],
    ],
    
    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeStored::class,
        ],
        
        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
        ],
        
        RequestHandled::class => [
            FlushTemporaryContainerInstances::class,
            DisconnectFromDatabases::class,
            CollectGarbage::class,
        ],
        
        RequestTerminated::class => [
            FlushSessionState::class,
            FlushAuthenticationState::class,
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
        ],
        
        WorkerStopping::class => [
            //
        ],
    ],
];
```

### 2.2. Performance Optimization

Optimize FrankenPHP for maximum performance:

```php
// config/octane.php - Performance optimizations
'frankenphp' => [
    'admin' => [
        'listen' => env('FRANKENPHP_ADMIN_LISTEN', '127.0.0.1:2019'),
    ],
    
    'server' => [
        'num_threads' => env('FRANKENPHP_NUM_THREADS', 4),
        'max_requests_per_worker' => env('FRANKENPHP_MAX_REQUESTS', 1000),
        'worker_timeout' => env('FRANKENPHP_WORKER_TIMEOUT', 30),
        'request_timeout' => env('FRANKENPHP_REQUEST_TIMEOUT', 30),
    ],
    
    'php' => [
        'memory_limit' => env('FRANKENPHP_MEMORY_LIMIT', '256M'),
        'max_execution_time' => env('FRANKENPHP_MAX_EXECUTION_TIME', 30),
        'max_input_time' => env('FRANKENPHP_MAX_INPUT_TIME', 60),
        'post_max_size' => env('FRANKENPHP_POST_MAX_SIZE', '100M'),
        'upload_max_filesize' => env('FRANKENPHP_UPLOAD_MAX_FILESIZE', '100M'),
    ],
    
    'opcache' => [
        'enable' => true,
        'memory_consumption' => 256,
        'interned_strings_buffer' => 16,
        'max_accelerated_files' => 20000,
        'validate_timestamps' => false, // Disable in production
        'revalidate_freq' => 0,
        'save_comments' => false,
        'enable_file_override' => true,
    ],
],
```

**System-Level Optimizations:**

```bash
# /etc/sysctl.d/99-frankenphp.conf
# Network optimizations
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
net.ipv4.tcp_max_tw_buckets = 1440000

# Memory optimizations
vm.swappiness = 1
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5

# File descriptor limits
fs.file-max = 2097152

# Apply settings
sudo sysctl -p /etc/sysctl.d/99-frankenphp.conf
```

### 2.3. SSL/TLS Configuration

Configure HTTPS with automatic certificate management:

```bash
# Caddyfile with HTTPS
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

# Production site with HTTPS
example.com {
    # Enable HTTP/2 and HTTP/3
    protocols h1 h2 h3
    
    # Document root
    root * /var/www/html/public
    
    # Security headers
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Frame-Options DENY
        X-Content-Type-Options nosniff
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
        Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
    }
    
    # Compression
    encode gzip zstd
    
    # PHP handler with Octane
    php_server {
        num_threads 8
        memory_limit 512M
        max_execution_time 60
    }
    
    # Static file caching
    @static {
        file
        path *.css *.js *.ico *.png *.jpg *.jpeg *.gif *.svg *.woff *.woff2
    }
    header @static Cache-Control "public, max-age=31536000, immutable"
    
    # API rate limiting
    @api {
        path /api/*
    }
    rate_limit @api {
        zone api_zone 10m
        key {remote_host}
        events 100
        window 1m
    }
    
    # Logging
    log {
        output file /var/log/frankenphp/access.log {
            roll_size 100mb
            roll_keep 5
            roll_keep_for 720h
        }
        format json
    }
}
```

## Memory Management

### 3.1. Memory Leak Prevention

Implement comprehensive memory leak prevention:

```php
// app/Octane/Listeners/FlushApplicationState.php
<?php

namespace App\Octane\Listeners;

use Laravel\Octane\Contracts\OperationTerminated;

class FlushApplicationState
{
    public function handle(OperationTerminated $event): void
    {
        // Clear application state
        $this->flushAuthenticationState();
        $this->flushSessionState();
        $this->flushCacheState();
        $this->flushDatabaseConnections();
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
        cache()->tags(['user-data', 'temporary'])->flush();
    }
    
    private function flushDatabaseConnections(): void
    {
        foreach (DB::getConnections() as $connection) {
            $connection->disconnect();
        }
        
        DB::purge();
    }
    
    private function flushTemporaryFiles(): void
    {
        // Clean up temporary files
        $tempDir = storage_path('app/temp');
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    unlink($file);
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

### 3.2. Resource Optimization

Optimize resource usage for long-running processes:

```php
// app/Octane/Listeners/OptimizeResources.php
<?php

namespace App\Octane\Listeners;

use Laravel\Octane\Events\RequestReceived;

class OptimizeResources
{
    private int $requestCount = 0;
    private float $memoryThreshold = 0.8; // 80% of memory limit
    
    public function handle(RequestReceived $event): void
    {
        $this->requestCount++;
        
        // Monitor memory usage
        $this->monitorMemoryUsage();
        
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
            logger()->warning('High memory usage detected', [
                'memory_usage' => $memoryUsage,
                'memory_limit' => $memoryLimit,
                'memory_percent' => $memoryPercent,
                'request_count' => $this->requestCount,
            ]);
            
            $this->performEmergencyCleanup();
        }
    }
    
    private function performPeriodicCleanup(): void
    {
        // Clear expired cache entries
        cache()->store('octane')->flush();
        
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
        
        // Disconnect from databases
        DB::purge();
        
        // Clear compiled views
        if (app()->bound('view')) {
            app('view')->flushFinderCache();
        }
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
                logger()->info('Garbage collection performed', [
                    'cycles_collected' => $collected,
                    'memory_before' => memory_get_usage(true),
                    'memory_after' => memory_get_usage(true),
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
        $logPath = storage_path('logs');
        $files = glob($logPath . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - 86400 * 7) { // 7 days old
                unlink($file);
            }
        }
    }
    
    private function resetStaticVariables(): void
    {
        // Reset any static caches in your application
        // This is application-specific
    }
}
```

### 3.3. Garbage Collection Tuning

Fine-tune garbage collection for optimal performance:

```php
// config/octane.php - Garbage collection settings
'garbage_collection' => [
    'enabled' => env('OCTANE_GC_ENABLED', true),
    'threshold' => env('OCTANE_GC_THRESHOLD', 50), // Requests between GC
    'memory_threshold' => env('OCTANE_GC_MEMORY_THRESHOLD', 0.7), // 70% memory usage
    'force_collection' => env('OCTANE_GC_FORCE', false),
],
```

**PHP Configuration for Garbage Collection:**

```ini
; php.ini optimizations for Octane
; Garbage Collection
zend.enable_gc = On
gc.threshold = 10001

; Memory settings
memory_limit = 512M
max_execution_time = 60
max_input_time = 60

; OPcache settings
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.save_comments = 0
opcache.enable_file_override = 1

; JIT settings (PHP 8.0+)
opcache.jit_buffer_size = 100M
opcache.jit = tracing

; Session settings
session.gc_probability = 1
session.gc_divisor = 1000
session.gc_maxlifetime = 1440
```

## Production Deployment

### 4.1. Docker Configuration

Deploy FrankenPHP with Docker for production:

```dockerfile
# Dockerfile
FROM dunglas/frankenphp:latest

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache
RUN chmod -R 775 /app/storage /app/bootstrap/cache

# Copy FrankenPHP configuration
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose ports
EXPOSE 80 443 2019

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

**Docker Compose Configuration:**

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
      - "443:443"
      - "2019:2019"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=8
      - OCTANE_MAX_REQUESTS=1000
    volumes:
      - ./storage:/app/storage
      - ./bootstrap/cache:/app/bootstrap/cache
      - caddy_data:/data
      - caddy_config:/config
    networks:
      - app-network
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
    networks:
      - app-network
    restart: unless-stopped

  database:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
      MYSQL_USER: laravel
      MYSQL_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - app-network
    restart: unless-stopped

volumes:
  caddy_data:
  caddy_config:
  redis_data:
  mysql_data:

networks:
  app-network:
    driver: bridge
```

### 4.2. Load Balancing

Configure load balancing for high availability:

```bash
# nginx.conf for load balancing
upstream frankenphp_backend {
    least_conn;
    server app1:80 max_fails=3 fail_timeout=30s;
    server app2:80 max_fails=3 fail_timeout=30s;
    server app3:80 max_fails=3 fail_timeout=30s;
    keepalive 32;
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

    # SSL configuration
    ssl_certificate /etc/ssl/certs/example.com.crt;
    ssl_certificate_key /etc/ssl/private/example.com.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=general:10m rate=1r/s;

    # API endpoints
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        proxy_pass http://frankenphp_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 5s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # General requests
    location / {
        limit_req zone=general burst=5 nodelay;
        proxy_pass http://frankenphp_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 5s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }

    # Health check
    location /health {
        access_log off;
        proxy_pass http://frankenphp_backend;
        proxy_set_header Host $host;
    }

    # Static files
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        proxy_pass http://frankenphp_backend;
    }
}
```

### 4.3. Scaling Strategies

Implement horizontal and vertical scaling:

```yaml
# kubernetes/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-frankenphp
  labels:
    app: laravel-frankenphp
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel-frankenphp
  template:
    metadata:
      labels:
        app: laravel-frankenphp
    spec:
      containers:
      - name: frankenphp
        image: your-registry/laravel-frankenphp:latest
        ports:
        - containerPort: 80
        - containerPort: 443
        env:
        - name: APP_ENV
          value: "production"
        - name: OCTANE_WORKERS
          value: "8"
        - name: OCTANE_MAX_REQUESTS
          value: "1000"
        resources:
          requests:
            memory: "1Gi"
            cpu: "500m"
          limits:
            memory: "2Gi"
            cpu: "1000m"
        livenessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
        volumeMounts:
        - name: storage
          mountPath: /app/storage
      volumes:
      - name: storage
        persistentVolumeClaim:
          claimName: laravel-storage

---
apiVersion: v1
kind: Service
metadata:
  name: laravel-frankenphp-service
spec:
  selector:
    app: laravel-frankenphp
  ports:
  - name: http
    port: 80
    targetPort: 80
  - name: https
    port: 443
    targetPort: 443
  type: LoadBalancer

---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-frankenphp-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-frankenphp
  minReplicas: 3
  maxReplicas: 10
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
```

## Monitoring & Troubleshooting

### 5.1. Performance Monitoring

Monitor FrankenPHP performance with comprehensive metrics:

```php
// app/Http/Middleware/OctaneMonitoring.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OctaneMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $this->logMetrics([
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'worker_id' => getmypid(),
            'timestamp' => now()->toISOString(),
        ]);

        return $response;
    }

    private function logMetrics(array $metrics): void
    {
        // Log to structured format for analysis
        Log::channel('octane')->info('Request metrics', $metrics);

        // Send to monitoring service
        if (config('octane.monitoring.enabled')) {
            $this->sendToMonitoring($metrics);
        }

        // Check for performance issues
        $this->checkPerformanceThresholds($metrics);
    }

    private function sendToMonitoring(array $metrics): void
    {
        // Send to external monitoring service
        Http::async()->post(config('octane.monitoring.endpoint'), $metrics);
    }

    private function checkPerformanceThresholds(array $metrics): void
    {
        // Alert on slow requests
        if ($metrics['duration_ms'] > 2000) {
            Log::warning('Slow request detected', $metrics);
        }

        // Alert on high memory usage
        if ($metrics['peak_memory'] > 500 * 1024 * 1024) { // 500MB
            Log::warning('High memory usage detected', $metrics);
        }
    }
}
```

### 5.2. Health Checks

Implement comprehensive health checks:

```php
// app/Http/Controllers/HealthController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'memory' => $this->checkMemory(),
            'octane' => $this->checkOctane(),
        ];

        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'worker_id' => getmypid(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === 'test'
                ? ['status' => 'ok', 'message' => 'Cache working correctly']
                : ['status' => 'error', 'message' => 'Cache read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $testFile = storage_path('app/health_check.txt');
            file_put_contents($testFile, 'test');
            $content = file_get_contents($testFile);
            unlink($testFile);

            return $content === 'test'
                ? ['status' => 'ok', 'message' => 'Storage read/write successful']
                : ['status' => 'error', 'message' => 'Storage read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

        $status = $memoryPercent < 80 ? 'ok' : ($memoryPercent < 90 ? 'warning' : 'error');

        return [
            'status' => $status,
            'message' => sprintf('Memory usage: %.1f%%', $memoryPercent),
            'usage_bytes' => $memoryUsage,
            'limit_bytes' => $memoryLimit,
        ];
    }

    private function checkOctane(): array
    {
        if (!app()->bound('octane')) {
            return ['status' => 'error', 'message' => 'Octane not available'];
        }

        return [
            'status' => 'ok',
            'message' => 'Octane running',
            'server' => config('octane.server'),
            'workers' => config('octane.workers'),
        ];
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
}
```

### 5.3. Debugging Tools

Advanced debugging tools for FrankenPHP:

```bash
#!/bin/bash
# scripts/octane-debug.sh

echo "=== FrankenPHP Debug Information ==="
echo "Date: $(date)"
echo "Server: $(hostname)"
echo ""

echo "=== Process Information ==="
ps aux | grep frankenphp | grep -v grep
echo ""

echo "=== Memory Usage ==="
free -h
echo ""

echo "=== Disk Usage ==="
df -h
echo ""

echo "=== Network Connections ==="
netstat -tulpn | grep :80
netstat -tulpn | grep :443
echo ""

echo "=== FrankenPHP Logs (last 50 lines) ==="
tail -n 50 /var/log/frankenphp/access.log
echo ""

echo "=== PHP Error Logs (last 20 lines) ==="
tail -n 20 /var/log/frankenphp/php_errors.log
echo ""

echo "=== System Load ==="
uptime
echo ""

echo "=== PHP Configuration ==="
php -i | grep -E "(memory_limit|max_execution_time|opcache)"
echo ""

echo "=== Octane Status ==="
cd /var/www/html && php artisan octane:status
```

## Integration Strategies

### 6.1. Laravel Pulse Integration

Monitor FrankenPHP with Laravel Pulse:

```php
// app/Pulse/Recorders/FrankenPHPRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;

class FrankenPHPRecorder extends Recorder
{
    public function register(callable $record): void
    {
        // Record worker metrics
        $this->recordWorkerMetrics($record);

        // Record memory usage
        $this->recordMemoryMetrics($record);

        // Record request metrics
        $this->recordRequestMetrics($record);
    }

    private function recordWorkerMetrics(callable $record): void
    {
        $record('frankenphp_worker', [
            'worker_id' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ]);
    }

    private function recordMemoryMetrics(callable $record): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        $record('frankenphp_memory', [
            'usage_bytes' => $memoryUsage,
            'usage_percent' => ($memoryUsage / $memoryLimit) * 100,
            'limit_bytes' => $memoryLimit,
        ]);
    }

    private function recordRequestMetrics(callable $record): void
    {
        if (app()->bound('octane.request_start_time')) {
            $duration = (microtime(true) - app('octane.request_start_time')) * 1000;

            $record('frankenphp_request_duration', [
                'duration_ms' => $duration,
                'method' => request()->method(),
                'status' => response()->getStatusCode(),
            ]);
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
}
```

### 6.2. External Monitoring Integration

Connect with external monitoring services:

```php
// app/Services/FrankenPHPMonitoringService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FrankenPHPMonitoringService
{
    public function sendMetricsToDatadog(): void
    {
        $metrics = $this->collectMetrics();

        Http::withHeaders([
            'DD-API-KEY' => config('services.datadog.api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.datadoghq.com/api/v1/series', [
            'series' => $metrics,
        ]);
    }

    private function collectMetrics(): array
    {
        return [
            [
                'metric' => 'frankenphp.memory.usage',
                'points' => [[
                    'timestamp' => time(),
                    'value' => memory_get_usage(true),
                ]],
                'tags' => [
                    'environment:' . app()->environment(),
                    'worker:' . getmypid(),
                ],
            ],
            [
                'metric' => 'frankenphp.memory.peak',
                'points' => [[
                    'timestamp' => time(),
                    'value' => memory_get_peak_usage(true),
                ]],
                'tags' => [
                    'environment:' . app()->environment(),
                    'worker:' . getmypid(),
                ],
            ],
        ];
    }
}
```

## Best Practices

### 7.1. Development Workflow

Optimize development with FrankenPHP:

```bash
# Development start script
#!/bin/bash
# scripts/dev-start.sh

echo "Starting Laravel Octane with FrankenPHP for development..."

# Set development environment
export APP_ENV=local
export APP_DEBUG=true
export OCTANE_WATCH=true
export OCTANE_WORKERS=1

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Start Octane with file watching
php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=8000 \
    --workers=1 \
    --max-requests=100 \
    --watch
```

### 7.2. Production Optimization

Production-ready configuration:

```bash
# Production deployment script
#!/bin/bash
# scripts/deploy.sh

echo "Deploying Laravel Octane with FrankenPHP to production..."

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize Composer autoloader
composer install --no-dev --optimize-autoloader

# Set production environment
export APP_ENV=production
export APP_DEBUG=false
export OCTANE_WATCH=false
export OCTANE_WORKERS=8
export OCTANE_MAX_REQUESTS=1000

# Start Octane
php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=8000 \
    --workers=8 \
    --max-requests=1000
```

### 7.3. Security Hardening

Secure FrankenPHP for production:

```bash
# Security configuration
# /etc/frankenphp/security.conf

# Disable server tokens
server_tokens off;

# Hide PHP version
expose_php = Off

# Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

# File upload restrictions
file_uploads = On
upload_max_filesize = 10M
max_file_uploads = 20

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

# Prevent information disclosure
display_errors = Off
display_startup_errors = Off
log_errors = On
```

## Performance Benchmarks

### 8.1. Benchmark Results

Typical performance improvements with FrankenPHP:

```bash
# Benchmark comparison script
#!/bin/bash
# scripts/benchmark.sh

echo "=== Performance Benchmark: FrankenPHP vs PHP-FPM ==="

echo "Testing simple JSON API endpoint..."

echo "PHP-FPM Results:"
ab -n 10000 -c 100 http://localhost:8080/api/test

echo "FrankenPHP Results:"
ab -n 10000 -c 100 http://localhost:8000/api/test

echo "Expected improvements:"
echo "- Requests per second: 10x+ improvement"
echo "- Memory usage: 50% reduction"
echo "- Response time: 80% reduction"
```

**Typical Results:**

| Metric | PHP-FPM | FrankenPHP | Improvement |
|--------|---------|------------|-------------|
| Requests/sec | 500 | 5,000+ | 10x+ |
| Memory Usage | 512MB | 256MB | 50% |
| Response Time | 200ms | 40ms | 80% |
| CPU Usage | 80% | 40% | 50% |

### 8.2. Load Testing

Comprehensive load testing setup:

```bash
# Load testing with k6
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

export default function() {
    let response = http.get('http://localhost:8000/api/test');

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(1);
}
```

---

## Navigation

**← Previous:** [Laravel Telescope Guide](030-laravel-telescope-guide.md)

**Next →** [Laravel Horizon Guide](050-laravel-horizon-guide.md)
