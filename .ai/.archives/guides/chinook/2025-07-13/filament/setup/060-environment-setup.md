# 6. Environment Setup

## Table of Contents

- [Overview](#overview)
- [Development Environment](#development-environment)
  - [Local Development Setup](#local-development-setup)
  - [Docker Development](#docker-development)
  - [Development Tools](#development-tools)
- [Testing Environment](#testing-environment)
  - [Testing Configuration](#testing-configuration)
  - [Test Database Setup](#test-database-setup)
  - [Continuous Integration](#continuous-integration)
- [Staging Environment](#staging-environment)
  - [Staging Configuration](#staging-configuration)
  - [Deployment Pipeline](#deployment-pipeline)
- [Production Environment](#production-environment)
  - [Production Configuration](#production-configuration)
  - [Security Hardening](#security-hardening)
  - [Performance Optimization](#performance-optimization)
- [Environment Variables](#environment-variables)
  - [Core Configuration](#core-configuration)
  - [Database Configuration](#database-configuration)
  - [Security Configuration](#security-configuration)
- [Monitoring and Logging](#monitoring-and-logging)
  - [Application Monitoring](#application-monitoring)
  - [Error Tracking](#error-tracking)
  - [Performance Monitoring](#performance-monitoring)
- [Backup and Recovery](#backup-and-recovery)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive environment setup for the Chinook admin panel across development, testing, staging, and production environments. Each environment is configured with appropriate security, performance, and monitoring settings.

### Environment Strategy

- **Development**: Local development with debugging and hot reloading
- **Testing**: Automated testing with CI/CD integration
- **Staging**: Production-like environment for final testing
- **Production**: Optimized, secure, and monitored production deployment

## Development Environment

### Local Development Setup

#### Prerequisites

```bash
# System Requirements
- PHP 8.4+
- Composer 2.6+
- Node.js 18+
- npm/yarn/pnpm
- MySQL 8.0+ or PostgreSQL 14+
- Redis 6.0+ (optional, for caching)

# Development Tools
- Laravel Herd (recommended)
- TablePlus/Sequel Pro (database management)
- Postman/Insomnia (API testing)
- VS Code/PhpStorm (IDE)
```

#### Environment Configuration

Create `.env` file for development:

```bash
# Application
APP_NAME="Chinook Admin"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://chinook-admin.test

# Database
DB_CONNECTION=sqlite
DB_DATABASE=database/chinook_admin_dev.sqlite
DB_FOREIGN_KEYS=true

# Cache
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/chinook-admin
SESSION_DOMAIN=chinook-admin.test

# Queue
QUEUE_CONNECTION=redis

# Mail (for development)
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="admin@chinook-admin.test"
MAIL_FROM_NAME="${APP_NAME}"

# Filament
FILAMENT_FILESYSTEM_DISK=local

# Security (development)
BCRYPT_ROUNDS=10
HASH_VERIFY=true

# Debugging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Development Tools
TELESCOPE_ENABLED=true
DEBUGBAR_ENABLED=true
```

#### Installation Steps

```bash
# 1. Clone repository
git clone https://github.com/your-org/chinook-admin.git
cd chinook-admin

# 2. Install PHP dependencies
composer install

# 3. Install Node.js dependencies
npm install

# 4. Copy environment file
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Configure database
# Edit .env with your database credentials

# 7. Run migrations and seeders
php artisan migrate:fresh --seed

# 8. Create admin user
php artisan make:filament-user

# 9. Build assets
npm run dev

# 10. Start development server
php artisan serve
```

### Docker Development

#### Docker Compose Configuration

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile.dev
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
      - ./storage:/var/www/html/storage
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: chinook_admin_dev
      MYSQL_USER: chinook
      MYSQL_PASSWORD: secret
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"
      - "8025:8025"

volumes:
  mysql_data:
  redis_data:
```

#### Development Dockerfile

```dockerfile
# Dockerfile.dev
FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-interaction --no-plugins --no-scripts
RUN npm install

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage
RUN chown -R www-data:www-data /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

### Development Tools

#### Laravel Telescope

```bash
# Install Telescope for development
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

#### Laravel Debugbar

```bash
# Install Debugbar for development
composer require barryvdh/laravel-debugbar --dev
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"
```

#### IDE Helper

```bash
# Install IDE Helper for better code completion
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
php artisan ide-helper:meta
```

## Testing Environment

### Testing Configuration

#### Test Environment Variables

```bash
# .env.testing
APP_NAME="Chinook Admin Test"
APP_ENV=testing
APP_KEY=base64:your-test-app-key-here
APP_DEBUG=false
APP_URL=http://localhost

# Test Database
DB_CONNECTION=sqlite
DB_DATABASE=:memory:

# Cache
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

# Mail
MAIL_MAILER=array

# Disable external services
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false

# Testing
BCRYPT_ROUNDS=4
HASH_VERIFY=false
```

### Test Database Setup

#### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### Continuous Integration

#### GitHub Actions Workflow

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: chinook_admin_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        extensions: mbstring, dom, fileinfo, mysql, redis
        coverage: xdebug

    - name: Cache Composer packages
      id: composer-cache
      uses: actions/cache@v3
      with:
        path: vendor
        key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-php-

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Copy environment file
      run: cp .env.ci .env

    - name: Generate key
      run: php artisan key:generate

    - name: Directory Permissions
      run: chmod -R 777 storage bootstrap/cache

    - name: Run migrations
      run: php artisan migrate --force

    - name: Run tests
      run: php artisan test --coverage

    - name: Upload coverage reports
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
```

## Staging Environment

### Staging Configuration

#### Staging Environment Variables

```bash
# .env.staging
APP_NAME="Chinook Admin Staging"
APP_ENV=staging
APP_KEY=base64:your-staging-app-key-here
APP_DEBUG=false
APP_URL=https://staging.chinook-admin.com

# Database
DB_CONNECTION=mysql
DB_HOST=staging-db.internal
DB_PORT=3306
DB_DATABASE=chinook_admin_staging
DB_USERNAME=chinook_staging
DB_PASSWORD=secure-staging-password

# Cache
CACHE_STORE=redis
REDIS_HOST=staging-redis.internal
REDIS_PASSWORD=secure-redis-password
REDIS_PORT=6379

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/chinook-admin
SESSION_DOMAIN=staging.chinook-admin.com
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=staging-mail-user
MAIL_PASSWORD=staging-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="admin@staging.chinook-admin.com"
MAIL_FROM_NAME="${APP_NAME}"

# Security
BCRYPT_ROUNDS=12
HASH_VERIFY=true

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=info

# Monitoring
SENTRY_LARAVEL_DSN=https://your-sentry-dsn@sentry.io/project-id
```

### Deployment Pipeline

#### Staging Deployment Script

```bash
#!/bin/bash
# deploy-staging.sh

set -e

echo "🚀 Deploying to staging environment..."

# Pull latest code
git fetch origin
git reset --hard origin/develop

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
php artisan migrate --force

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart chinook-admin-worker
sudo service nginx reload

echo "✅ Staging deployment completed successfully!"
```

## Production Environment

### Production Configuration

#### Production Environment Variables

```bash
# .env.production
APP_NAME="Chinook Admin"
APP_ENV=production
APP_KEY=base64:your-production-app-key-here
APP_DEBUG=false
APP_URL=https://admin.chinook.com

# Database
DB_CONNECTION=mysql
DB_HOST=prod-db-cluster.internal
DB_PORT=3306
DB_DATABASE=chinook_admin_prod
DB_USERNAME=chinook_prod
DB_PASSWORD=ultra-secure-production-password

# Cache
CACHE_STORE=redis
REDIS_HOST=prod-redis-cluster.internal
REDIS_PASSWORD=ultra-secure-redis-password
REDIS_PORT=6379

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_PATH=/chinook-admin
SESSION_DOMAIN=admin.chinook.com
SESSION_SECURE_COOKIE=true

# Queue
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-aws-access-key
AWS_SECRET_ACCESS_KEY=your-aws-secret-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS="admin@chinook.com"
MAIL_FROM_NAME="${APP_NAME}"

# File Storage
FILESYSTEM_DISK=s3
AWS_BUCKET=chinook-admin-storage
AWS_USE_PATH_STYLE_ENDPOINT=false

# Security
BCRYPT_ROUNDS=15
HASH_VERIFY=true

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning

# Monitoring
SENTRY_LARAVEL_DSN=https://your-production-sentry-dsn@sentry.io/project-id

# Performance
OPCACHE_ENABLE=1
OPCACHE_MEMORY_CONSUMPTION=256
OPCACHE_MAX_ACCELERATED_FILES=20000
```

### Security Hardening

#### Production Security Checklist

- [ ] **SSL/TLS**: HTTPS with strong cipher suites
- [ ] **Firewall**: Restrict access to necessary ports only
- [ ] **Database**: Separate database server with restricted access
- [ ] **File Permissions**: Proper file and directory permissions
- [ ] **Environment Variables**: Secure storage of sensitive configuration
- [ ] **Error Handling**: No debug information in production
- [ ] **Logging**: Comprehensive security event logging
- [ ] **Monitoring**: Real-time security monitoring
- [ ] **Backup**: Automated, encrypted backups
- [ ] **Updates**: Regular security updates

### Performance Optimization

#### Production Optimizations

```bash
# Optimize Composer autoloader
composer install --no-dev --optimize-autoloader

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize images and assets
npm run production

# Enable OPcache
echo "opcache.enable=1" >> /etc/php/8.4/fpm/php.ini
echo "opcache.memory_consumption=256" >> /etc/php/8.4/fpm/php.ini
echo "opcache.max_accelerated_files=20000" >> /etc/php/8.4/fpm/php.ini

# Configure Redis for performance
echo "maxmemory 2gb" >> /etc/redis/redis.conf
echo "maxmemory-policy allkeys-lru" >> /etc/redis/redis.conf
```

## Environment Variables

### Core Configuration

```bash
# Application Identity
APP_NAME="Chinook Admin"
APP_ENV=production
APP_KEY=base64:generated-key-here
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://admin.chinook.com
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database
```

### Database Configuration

```bash
# Primary Database
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/chinook-admin/database/production.sqlite
DB_FOREIGN_KEYS=true

# SQLite WAL Mode Configuration (applied automatically)
# PRAGMA journal_mode = WAL
# PRAGMA synchronous = NORMAL
# PRAGMA cache_size = -64000
# PRAGMA temp_store = MEMORY
# PRAGMA mmap_size = 268435456
```

### Security Configuration

```bash
# Encryption
BCRYPT_ROUNDS=12
HASH_VERIFY=true

# Session Security
SESSION_DRIVER=redis
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_PATH=/chinook-admin
SESSION_DOMAIN=admin.chinook.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict

# CSRF Protection
CSRF_COOKIE=chinook_admin_csrf
CSRF_EXPIRE=120

# Rate Limiting
THROTTLE_REQUESTS=60
THROTTLE_DECAY_MINUTES=1
```

## Monitoring and Logging

### Application Monitoring

#### Sentry Configuration

```php
// config/sentry.php
return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('APP_ENV'),
    'sample_rate' => env('SENTRY_SAMPLE_RATE', 1.0),
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'send_default_pii' => false,
    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => true,
    ],
];
```

### Error Tracking

#### Custom Error Handler

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }

    public function render($request, Throwable $e): Response
    {
        // Log security-related errors
        if ($this->isSecurityException($e)) {
            Log::channel('security')->error('Security exception', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);
        }

        return parent::render($request, $e);
    }

    private function isSecurityException(Throwable $e): bool
    {
        return in_array(get_class($e), [
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
        ]);
    }
}
```

### Performance Monitoring

#### Laravel Pulse Configuration

```bash
# Install Laravel Pulse
composer require laravel/pulse

# Publish configuration
php artisan vendor:publish --provider="Laravel\Pulse\PulseServiceProvider"

# Run migrations
php artisan migrate
```

## Backup and Recovery

### Database Backup

```bash
#!/bin/bash
# backup-database.sh

BACKUP_DIR="/var/backups/chinook-admin"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="chinook_admin_prod"

# Create backup directory
mkdir -p $BACKUP_DIR

# Create SQLite database backup
sqlite3 $DB_PATH ".backup $BACKUP_DIR/db_backup_$DATE.sqlite"

# Compress backup
gzip $BACKUP_DIR/db_backup_$DATE.sql

# Upload to S3
aws s3 cp $BACKUP_DIR/db_backup_$DATE.sql.gz \
  s3://chinook-admin-backups/database/

# Clean up old local backups (keep 7 days)
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Database backup completed: db_backup_$DATE.sql.gz"
```

### Application Backup

```bash
#!/bin/bash
# backup-application.sh

BACKUP_DIR="/var/backups/chinook-admin"
DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/chinook-admin"

# Create backup directory
mkdir -p $BACKUP_DIR

# Create application backup (excluding vendor and node_modules)
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='storage/logs' \
  --exclude='storage/framework/cache' \
  --exclude='storage/framework/sessions' \
  --exclude='storage/framework/views' \
  -C $APP_DIR .

# Upload to S3
aws s3 cp $BACKUP_DIR/app_backup_$DATE.tar.gz \
  s3://chinook-admin-backups/application/

# Clean up old local backups (keep 3 days)
find $BACKUP_DIR -name "app_backup_*.tar.gz" -mtime +3 -delete

echo "Application backup completed: app_backup_$DATE.tar.gz"
```

## Best Practices

### Environment Management

1. **Separation**: Keep environments completely separate
2. **Configuration**: Use environment-specific configuration files
3. **Secrets**: Store sensitive data in secure secret management systems
4. **Versioning**: Version control environment configurations (excluding secrets)
5. **Documentation**: Document environment-specific procedures

### Security Best Practices

1. **Principle of Least Privilege**: Grant minimum necessary permissions
2. **Defense in Depth**: Implement multiple security layers
3. **Regular Updates**: Keep all components updated
4. **Monitoring**: Implement comprehensive monitoring and alerting
5. **Incident Response**: Have incident response procedures ready

### Performance Best Practices

1. **Caching**: Implement appropriate caching strategies
2. **Database Optimization**: Optimize queries and indexes
3. **Asset Optimization**: Minimize and compress assets
4. **CDN**: Use content delivery networks for static assets
5. **Monitoring**: Monitor performance metrics continuously

## Troubleshooting

### Common Issues

1. **Permission Errors**:

   ```bash
   # Fix storage permissions
   sudo chown -R www-data:www-data storage/
   sudo chmod -R 775 storage/
   ```

2. **Cache Issues**:

   ```bash
   # Clear all caches
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

3. **Database Connection Issues**:

   ```bash
   # Test database connection
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

4. **Queue Issues**:

   ```bash
   # Restart queue workers
   php artisan queue:restart

   # Check failed jobs
   php artisan queue:failed
   ```

### Environment-Specific Debugging

#### Development Debugging

```bash
# Enable debug mode
APP_DEBUG=true

# Enable query logging
DB_LOG_QUERIES=true

# Enable Telescope
TELESCOPE_ENABLED=true
```

#### Production Debugging

```bash
# Check application logs
tail -f storage/logs/laravel.log

# Check web server logs
tail -f /var/log/nginx/error.log

# Check system resources
htop
df -h
free -m
```

---

## Navigation

**← Previous:** [Security Configuration](050-security-configuration.md)

**Next →** [Resources Documentation](../resources/README.md)
