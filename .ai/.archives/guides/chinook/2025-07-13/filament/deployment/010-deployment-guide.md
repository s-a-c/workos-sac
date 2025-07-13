# Deployment Guide

## Overview

This comprehensive deployment guide covers production deployment strategies for the Chinook Filament application,
including Docker containerization, cloud deployment, and performance optimization.

## Table of Contents

- [Overview](#overview)
- [Production Environment Setup](#production-environment-setup)
- [Docker Deployment](#docker-deployment)
- [Cloud Deployment Options](#cloud-deployment-options)
- [Database Configuration](#database-configuration)
- [Security Configuration](#security-configuration)
- [Performance Optimization](#performance-optimization)
- [Monitoring and Logging](#monitoring-and-logging)
- [Backup Strategies](#backup-strategies)
- [CI/CD Pipeline](#cicd-pipeline)

## Production Environment Setup

### Server Requirements

**Minimum Requirements:**

- PHP 8.3+
- Node.js 18+
- SQLite 3.35+ (with WAL mode support)
- Redis 6.0+
- Nginx 1.20+

**Recommended Production Setup:**

- 4 CPU cores
- 8GB RAM
- 100GB SSD storage
- Load balancer for high availability

### Environment Configuration

```bash
# .env.production
APP_NAME="Chinook Music Database"
APP_ENV=production
APP_KEY=base64:your-production-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/production.sqlite
DB_FOREIGN_KEYS=true

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls

# Logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Performance
OCTANE_SERVER=frankenphp
OCTANE_HTTPS=true
```

## Docker Deployment

### Dockerfile

```dockerfile
# Dockerfile
FROM dunglas/frankenphp:1-php8.3

# Install system dependencies
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

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app/storage \
    && chmod -R 755 /app/bootstrap/cache

# Install Node.js dependencies and build assets
RUN npm ci && npm run build

# Create SQLite database directory
RUN mkdir -p /app/database && \
    touch /app/database/production.sqlite && \
    chown www-data:www-data /app/database/production.sqlite

# Expose port
EXPOSE 80 443

# Start FrankenPHP
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

### Docker Compose

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./database:/app/database
      - ./storage/logs:/app/storage/logs
    environment:
      - APP_ENV=production
      - DB_DATABASE=/app/database/production.sqlite
    depends_on:
      - redis
    networks:
      - chinook-network

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - chinook-network

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - app
    networks:
      - chinook-network

volumes:
  redis_data:

networks:
  chinook-network:
    driver: bridge
```

## Cloud Deployment Options

### AWS Deployment

```bash
# AWS ECS Task Definition
{
  "family": "chinook-app",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "1024",
  "memory": "2048",
  "executionRoleArn": "arn:aws:iam::account:role/ecsTaskExecutionRole",
  "containerDefinitions": [
    {
      "name": "chinook-app",
      "image": "your-account.dkr.ecr.region.amazonaws.com/chinook:latest",
      "portMappings": [
        {
          "containerPort": 80,
          "protocol": "tcp"
        }
      ],
      "environment": [
        {
          "name": "APP_ENV",
          "value": "production"
        }
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/chinook-app",
          "awslogs-region": "us-east-1",
          "awslogs-stream-prefix": "ecs"
        }
      }
    }
  ]
}
```

### Laravel Vapor Deployment

```yaml
# vapor.yml
id: 12345
name: chinook-music-db
environments:
    production:
        memory: 1024
        cli-memory: 512
        runtime: php-8.3
        database: chinook-production
        cache: chinook-cache
        build:
            - 'composer install --no-dev --optimize-autoloader'
            - 'npm ci && npm run build'
            - 'php artisan config:cache'
            - 'php artisan route:cache'
            - 'php artisan view:cache'
```

## Database Configuration

### SQLite Production Setup

```bash
# SQLite optimization for production
sqlite3 /var/www/html/database/production.sqlite << 'EOF'
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA cache_size = 1000000;
PRAGMA foreign_keys = ON;
PRAGMA temp_store = MEMORY;
PRAGMA mmap_size = 268435456;
EOF
```

### Database Migration Strategy

```php
// database/migrations/production_optimizations.php
public function up()
{
    // Create indexes for performance
    DB::statement('CREATE INDEX IF NOT EXISTS idx_artists_active ON artists(is_active)');
    DB::statement('CREATE INDEX IF NOT EXISTS idx_albums_artist_release ON albums(artist_id, release_date)');
    DB::statement('CREATE INDEX IF NOT EXISTS idx_tracks_album ON tracks(album_id)');
    DB::statement('CREATE INDEX IF NOT EXISTS idx_categories_type_active ON categories(type, is_active)');
    DB::statement('CREATE INDEX IF NOT EXISTS idx_categorizables_poly ON categorizables(categorizable_type, categorizable_id)');
    
    // Optimize closure table
    DB::statement('CREATE INDEX IF NOT EXISTS idx_closure_ancestor ON category_closure(ancestor_id)');
    DB::statement('CREATE INDEX IF NOT EXISTS idx_closure_descendant ON category_closure(descendant_id)');
    DB::statement('CREATE INDEX IF NOT EXISTS idx_closure_depth ON category_closure(depth)');
}
```

## Security Configuration

### SSL/TLS Setup

```nginx
# nginx.conf
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    location / {
        proxy_pass http://app:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Application Security

```php
// config/security.php
return [
    'rate_limiting' => [
        'api' => '60,1', // 60 requests per minute
        'login' => '5,1', // 5 login attempts per minute
        'admin' => '120,1', // 120 admin requests per minute
    ],
    
    'csrf' => [
        'except' => [
            'api/*', // API routes use Sanctum
        ],
    ],
    
    'cors' => [
        'allowed_origins' => [
            'https://your-domain.com',
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
    ],
];
```

## Performance Optimization

### Caching Strategy

```php
// config/cache.php production settings
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],

// Cache optimization
'prefix' => env('CACHE_PREFIX', 'chinook'),
'serialize' => true,
'compress' => true,
```

### Queue Configuration

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],

// Horizon configuration for queue monitoring
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'high', 'low'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
        ],
    ],
],
```

## Monitoring and Logging

### Application Monitoring

```php
// config/logging.php
'channels' => [
    'production' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],
    
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'error'),
        'days' => 14,
    ],
    
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Chinook Monitor',
        'emoji' => ':boom:',
        'level' => 'critical',
    ],
],
```

### Health Checks

```php
// routes/web.php
Route::get('/health', function () {
    $checks = [
        'database' => DB::connection()->getPdo() !== null,
        'cache' => Cache::store('redis')->get('health_check') !== null,
        'queue' => Queue::size() < 1000, // Queue not backed up
        'storage' => Storage::disk('local')->exists('health_check.txt'),
    ];
    
    $healthy = array_reduce($checks, fn($carry, $check) => $carry && $check, true);
    
    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
    ], $healthy ? 200 : 503);
});
```

## Backup Strategies

### Automated Backup Script

```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"
DB_PATH="/var/www/html/database/production.sqlite"

# Create backup directory
mkdir -p $BACKUP_DIR

# SQLite backup with WAL checkpoint
sqlite3 $DB_PATH "PRAGMA wal_checkpoint(FULL);"
cp $DB_PATH "$BACKUP_DIR/chinook_$DATE.sqlite"

# Compress backup
gzip "$BACKUP_DIR/chinook_$DATE.sqlite"

# Upload to S3 (optional)
aws s3 cp "$BACKUP_DIR/chinook_$DATE.sqlite.gz" s3://your-backup-bucket/

# Clean old backups (keep 30 days)
find $BACKUP_DIR -name "chinook_*.sqlite.gz" -mtime +30 -delete

echo "Backup completed: chinook_$DATE.sqlite.gz"
```

## CI/CD Pipeline

### GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Run tests
      run: php artisan test
      
    - name: Build assets
      run: |
        npm ci
        npm run build
        
    - name: Deploy to production
      run: |
        # Your deployment script here
        ./deploy.sh
```

---

**Next**: [Monitoring Setup](090-monitoring-setup.md) | **Back**: [Deployment Index](000-deployment-index.md)

---

*This guide provides comprehensive production deployment strategies for enterprise-scale Chinook applications.*
