# 1. CI/CD Integration Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. CI/CD Strategy](#2-cicd-strategy)
- [3. GitHub Actions Workflows](#3-github-actions-workflows)
- [4. Testing Pipeline](#4-testing-pipeline)
- [5. Build and Deployment](#5-build-and-deployment)
- [6. Environment Management](#6-environment-management)
- [7. Security and Secrets](#7-security-and-secrets)
- [8. Best Practices](#8-best-practices)
- [9. Rollback Strategies](#9-rollback-strategies)
- [10. Navigation](#10-navigation)

## 1. Overview

This guide provides comprehensive CI/CD integration workflows and deployment strategies specifically designed for the Chinook music platform. It covers automated testing, building, deployment, and monitoring processes that ensure reliable and efficient software delivery.

## 2. CI/CD Strategy

### 2.1 Deployment Pipeline Overview

```mermaid
graph TD
    subgraph "Source Control"
        A[GitHub Repository] --> B[Feature Branch]
        B --> C[Pull Request]
        C --> D[Code Review]
    end
    
    subgraph "CI Pipeline"
        D --> E[Automated Tests]
        E --> F[Code Quality Checks]
        F --> G[Security Scans]
        G --> H[Build Assets]
    end
    
    subgraph "CD Pipeline"
        H --> I[Deploy to Staging]
        I --> J[Integration Tests]
        J --> K[Performance Tests]
        K --> L[Deploy to Production]
    end
    
    subgraph "Monitoring"
        L --> M[Health Checks]
        M --> N[Performance Monitoring]
        N --> O[Error Tracking]
    end
    
    style A fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    style B fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    style C fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    style D fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    style E fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    style F fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    style G fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    style H fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    style I fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    style J fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    style K fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    style L fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    style M fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
    style N fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
    style O fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
```

### 2.2 Branching Strategy

- **main**: Production-ready code
- **develop**: Integration branch for features
- **feature/***: Individual feature development
- **hotfix/***: Critical production fixes
- **release/***: Release preparation branches

## 3. GitHub Actions Workflows

### 3.1 Main CI/CD Workflow

```yaml
# .github/workflows/ci-cd.yml
name: Chinook CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]
  release:
    types: [ published ]

env:
  PHP_VERSION: '8.3'
  NODE_VERSION: '20'
  COMPOSER_CACHE_DIR: ~/.composer/cache

jobs:
  # Code Quality and Testing
  test:
    name: Run Tests
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: chinook_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:alpine
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          coverage: xdebug

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Cache Composer dependencies
        uses: actions/cache@v3
        with:
          path: ${{ env.COMPOSER_CACHE_DIR }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install Composer dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Install NPM dependencies
        run: npm ci

      - name: Copy environment file
        run: cp .env.ci .env

      - name: Generate application key
        run: php artisan key:generate

      - name: Create SQLite database
        run: touch database/database.sqlite

      - name: Run database migrations
        run: php artisan migrate --force

      - name: Seed test data
        run: php artisan db:seed --class=TestSeeder

      - name: Build frontend assets
        run: npm run build

      - name: Run PHP tests
        run: php artisan test --coverage --min=80

      - name: Run JavaScript tests
        run: npm test

      - name: Run Pest tests
        run: ./vendor/bin/pest --coverage --min=80

      - name: Upload coverage reports
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
          flags: unittests
          name: codecov-umbrella

  # Code Quality Checks
  quality:
    name: Code Quality
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv

      - name: Install Composer dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Run PHP CS Fixer
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff

      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse

      - name: Run Psalm
        run: ./vendor/bin/psalm

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'

      - name: Install NPM dependencies
        run: npm ci

      - name: Run ESLint
        run: npm run lint

      - name: Run Prettier
        run: npm run format:check

  # Security Scanning
  security:
    name: Security Scan
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Run Composer security audit
        run: composer audit

      - name: Run NPM security audit
        run: npm audit --audit-level=moderate

      - name: Run Snyk security scan
        uses: snyk/actions/php@master
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}

      - name: Run OWASP ZAP security scan
        uses: zaproxy/action-baseline@v0.7.0
        with:
          target: 'http://localhost:8000'
```

## 4. Testing Pipeline

### 4.1 Automated Testing Strategy

```yaml
# .github/workflows/testing.yml
name: Comprehensive Testing

on:
  pull_request:
    branches: [ main, develop ]

jobs:
  unit-tests:
    name: Unit Tests
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          coverage: xdebug

      - name: Install dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Run unit tests
        run: php artisan test --testsuite=Unit --coverage-clover=coverage.xml

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml

  feature-tests:
    name: Feature Tests
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: chinook_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv

      - name: Install dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Copy environment file
        run: cp .env.ci .env

      - name: Generate application key
        run: php artisan key:generate

      - name: Run migrations
        run: php artisan migrate --force

      - name: Run feature tests
        run: php artisan test --testsuite=Feature

  browser-tests:
    name: Browser Tests
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install dependencies
        run: |
          composer install --no-progress --prefer-dist --optimize-autoloader
          npm ci

      - name: Build assets
        run: npm run build

      - name: Start Chrome
        run: ./vendor/laravel/dusk/bin/chromedriver-linux &

      - name: Run Dusk tests
        run: php artisan dusk

      - name: Upload Dusk screenshots
        uses: actions/upload-artifact@v3
        if: failure()
        with:
          name: dusk-screenshots
          path: tests/Browser/screenshots

  performance-tests:
    name: Performance Tests
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install Artillery
        run: npm install -g artillery

      - name: Setup test environment
        run: |
          docker-compose -f docker-compose.test.yml up -d
          sleep 30

      - name: Run performance tests
        run: artillery run tests/Performance/load-test.yml

      - name: Generate performance report
        run: artillery report --output performance-report.html

      - name: Upload performance report
        uses: actions/upload-artifact@v3
        with:
          name: performance-report
          path: performance-report.html
```

### 4.2 Test Environment Configuration

```bash
# .env.ci
APP_NAME="Chinook Test"
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite
DB_DATABASE=:memory:

BROADCAST_DRIVER=log
CACHE_DRIVER=array
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=array

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=array

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

## 5. Build and Deployment

### 5.1 Production Deployment Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  release:
    types: [ published ]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build-and-push:
    name: Build and Push Docker Image
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Log in to Container Registry
        uses: docker/login-action@v2
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v4
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=ref,event=branch
            type=ref,event=pr
            type=semver,pattern={{version}}
            type=semver,pattern={{major}}.{{minor}}

      - name: Build and push Docker image
        uses: docker/build-push-action@v4
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}

  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: build-and-push
    environment: production

    steps:
      - name: Deploy to production server
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/chinook
            docker-compose pull
            docker-compose up -d
            docker-compose exec -T app php artisan migrate --force
            docker-compose exec -T app php artisan config:cache
            docker-compose exec -T app php artisan route:cache
            docker-compose exec -T app php artisan view:cache
            docker-compose exec -T app php artisan queue:restart

  health-check:
    name: Health Check
    runs-on: ubuntu-latest
    needs: deploy

    steps:
      - name: Check application health
        run: |
          for i in {1..10}; do
            if curl -f ${{ secrets.PRODUCTION_URL }}/health; then
              echo "Application is healthy"
              exit 0
            fi
            echo "Attempt $i failed, retrying in 30 seconds..."
            sleep 30
          done
          echo "Health check failed"
          exit 1

      - name: Run smoke tests
        run: |
          curl -f ${{ secrets.PRODUCTION_URL }}/api/health
          curl -f ${{ secrets.PRODUCTION_URL }}/api/artists?limit=1
```

### 5.2 Dockerfile Configuration

```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy package files
COPY package.json package-lock.json ./

# Install Node dependencies
RUN npm ci --only=production

# Copy application code
COPY . .

# Build frontend assets
RUN npm run build

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port
EXPOSE 9000

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### 5.3 Docker Compose Configuration

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: chinook-app:latest
    container_name: chinook_app
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./storage:/var/www/storage
      - ./bootstrap/cache:/var/www/bootstrap/cache
    networks:
      - chinook

  nginx:
    image: nginx:alpine
    container_name: chinook_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./public:/var/www/public
      - ./docker/nginx:/etc/nginx/conf.d
      - ./docker/ssl:/etc/ssl/certs
    depends_on:
      - app
    networks:
      - chinook

  mysql:
    image: mysql:8.0
    container_name: chinook_mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_USER: ${DB_USERNAME}
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"
    networks:
      - chinook

  redis:
    image: redis:alpine
    container_name: chinook_redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    networks:
      - chinook

  queue:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: chinook_queue
    restart: unless-stopped
    command: php artisan queue:work --sleep=3 --tries=3
    volumes:
      - ./storage:/var/www/storage
    depends_on:
      - mysql
      - redis
    networks:
      - chinook

  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: chinook_scheduler
    restart: unless-stopped
    command: php artisan schedule:work
    volumes:
      - ./storage:/var/www/storage
    depends_on:
      - mysql
      - redis
    networks:
      - chinook

volumes:
  mysql_data:
  redis_data:

networks:
  chinook:
    driver: bridge
```

## 6. Environment Management

### 6.1 Environment-Specific Configurations

```yaml
# .github/workflows/deploy-staging.yml
name: Deploy to Staging

on:
  push:
    branches: [ develop ]

jobs:
  deploy-staging:
    name: Deploy to Staging Environment
    runs-on: ubuntu-latest
    environment: staging

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install NPM dependencies
        run: npm ci

      - name: Build assets
        run: npm run build

      - name: Deploy to staging
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: ${{ secrets.STAGING_USER }}
          key: ${{ secrets.STAGING_SSH_KEY }}
          script: |
            cd /var/www/chinook-staging
            git pull origin develop
            composer install --no-dev --optimize-autoloader
            npm ci
            npm run build
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart

      - name: Run integration tests
        run: |
          sleep 30
          curl -f ${{ secrets.STAGING_URL }}/health
          npm run test:integration -- --baseUrl=${{ secrets.STAGING_URL }}
```

## 7. Security and Secrets

### 7.1 Secrets Management

```yaml
# GitHub Secrets Configuration
# Repository Settings > Secrets and variables > Actions

# Production Environment
PRODUCTION_HOST: "prod.chinook.com"
PRODUCTION_USER: "deploy"
PRODUCTION_SSH_KEY: "-----BEGIN OPENSSH PRIVATE KEY-----..."
PRODUCTION_URL: "https://chinook.com"

# Staging Environment
STAGING_HOST: "staging.chinook.com"
STAGING_USER: "deploy"
STAGING_SSH_KEY: "-----BEGIN OPENSSH PRIVATE KEY-----..."
STAGING_URL: "https://staging.chinook.com"

# Database
DB_PASSWORD: "secure_database_password"
DB_USERNAME: "chinook_user"

# External Services
SNYK_TOKEN: "snyk_security_token"
CODECOV_TOKEN: "codecov_upload_token"
SENTRY_DSN: "sentry_error_tracking_dsn"

# Container Registry
GHCR_TOKEN: "github_container_registry_token"
```

### 7.2 Security Scanning Integration

```yaml
# .github/workflows/security.yml
name: Security Scanning

on:
  schedule:
    - cron: '0 2 * * *' # Daily at 2 AM
  push:
    branches: [ main ]

jobs:
  dependency-scan:
    name: Dependency Security Scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Run Snyk to check for vulnerabilities
        uses: snyk/actions/php@master
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}
        with:
          args: --severity-threshold=high

      - name: Upload Snyk results to GitHub Code Scanning
        uses: github/codeql-action/upload-sarif@v2
        with:
          sarif_file: snyk.sarif

  code-scan:
    name: Code Security Scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Initialize CodeQL
        uses: github/codeql-action/init@v2
        with:
          languages: php, javascript

      - name: Perform CodeQL Analysis
        uses: github/codeql-action/analyze@v2

  container-scan:
    name: Container Security Scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Build Docker image
        run: docker build -t chinook-app:latest .

      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: 'chinook-app:latest'
          format: 'sarif'
          output: 'trivy-results.sarif'

      - name: Upload Trivy scan results
        uses: github/codeql-action/upload-sarif@v2
        with:
          sarif_file: 'trivy-results.sarif'
```

## 8. Best Practices

### 8.1 CI/CD Guidelines

1. **Fast Feedback**: Keep build times under 10 minutes
2. **Fail Fast**: Run fastest tests first
3. **Parallel Execution**: Run independent jobs in parallel
4. **Caching**: Cache dependencies and build artifacts
5. **Security**: Scan for vulnerabilities at every stage

### 8.2 Deployment Strategies

1. **Blue-Green Deployment**: Zero-downtime deployments
2. **Rolling Updates**: Gradual rollout to minimize risk
3. **Feature Flags**: Control feature rollout independently
4. **Database Migrations**: Run migrations safely with rollback plans
5. **Health Checks**: Verify application health after deployment

### 8.3 Monitoring and Alerting

```yaml
# .github/workflows/monitoring.yml
name: Post-Deployment Monitoring

on:
  workflow_run:
    workflows: ["Deploy to Production"]
    types:
      - completed

jobs:
  health-monitoring:
    name: Health Check Monitoring
    runs-on: ubuntu-latest
    if: ${{ github.event.workflow_run.conclusion == 'success' }}

    steps:
      - name: Wait for deployment stabilization
        run: sleep 120

      - name: Check application health
        run: |
          response=$(curl -s -o /dev/null -w "%{http_code}" ${{ secrets.PRODUCTION_URL }}/health)
          if [ $response -ne 200 ]; then
            echo "Health check failed with status: $response"
            exit 1
          fi

      - name: Check API endpoints
        run: |
          endpoints=("/api/artists" "/api/albums" "/api/tracks")
          for endpoint in "${endpoints[@]}"; do
            response=$(curl -s -o /dev/null -w "%{http_code}" ${{ secrets.PRODUCTION_URL }}$endpoint)
            if [ $response -ne 200 ]; then
              echo "API endpoint $endpoint failed with status: $response"
              exit 1
            fi
          done

      - name: Send Slack notification on failure
        if: failure()
        uses: 8398a7/action-slack@v3
        with:
          status: failure
          channel: '#alerts'
          webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

## 9. Rollback Strategies

### 9.1 Automated Rollback

```yaml
# .github/workflows/rollback.yml
name: Emergency Rollback

on:
  workflow_dispatch:
    inputs:
      version:
        description: 'Version to rollback to'
        required: true
        type: string

jobs:
  rollback:
    name: Rollback to Previous Version
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Rollback application
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/chinook

            # Stop current services
            docker-compose down

            # Checkout previous version
            git checkout ${{ github.event.inputs.version }}

            # Rollback database if needed
            php artisan migrate:rollback --step=1

            # Restart services
            docker-compose up -d

            # Verify rollback
            sleep 30
            curl -f http://localhost/health

      - name: Verify rollback
        run: |
          sleep 60
          response=$(curl -s -o /dev/null -w "%{http_code}" ${{ secrets.PRODUCTION_URL }}/health)
          if [ $response -ne 200 ]; then
            echo "Rollback verification failed"
            exit 1
          fi

      - name: Notify team of rollback
        uses: 8398a7/action-slack@v3
        with:
          status: custom
          custom_payload: |
            {
              "text": "🚨 Emergency rollback completed",
              "attachments": [
                {
                  "color": "warning",
                  "fields": [
                    {
                      "title": "Rolled back to",
                      "value": "${{ github.event.inputs.version }}",
                      "short": true
                    },
                    {
                      "title": "Triggered by",
                      "value": "${{ github.actor }}",
                      "short": true
                    }
                  ]
                }
              ]
            }
          webhook_url: ${{ secrets.SLACK_WEBHOOK }}
```

### 9.2 Database Rollback Strategy

```php
<?php
// app/Console/Commands/SafeRollback.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SafeRollback extends Command
{
    protected $signature = 'rollback:safe {--steps=1 : Number of migration steps to rollback}';
    protected $description = 'Safely rollback database migrations with backup';

    public function handle(): int
    {
        $steps = $this->option('steps');

        // Create backup before rollback
        $this->info('Creating database backup...');
        $backupFile = storage_path('backups/pre-rollback-' . now()->format('Y-m-d-H-i-s') . '.sql');

        $this->call('backup:database', ['--path' => $backupFile]);

        // Confirm rollback
        if (!$this->confirm("Are you sure you want to rollback {$steps} migration(s)?")) {
            $this->info('Rollback cancelled.');
            return 0;
        }

        try {
            // Perform rollback
            $this->info("Rolling back {$steps} migration(s)...");
            $this->call('migrate:rollback', ['--step' => $steps]);

            $this->info('Rollback completed successfully.');
            $this->info("Backup saved to: {$backupFile}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Rollback failed: ' . $e->getMessage());
            $this->info('Restoring from backup...');

            // Restore from backup
            $this->call('backup:restore', ['--path' => $backupFile]);

            return 1;
        }
    }
}
```

## 10. Navigation

**← Previous** [API Testing Guide](180-api-testing-guide.md)
**Next →** [Media Library Enhancement Guide](200-media-library-enhancement-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/190-cicd-integration-guide.md on 2025-07-11

*This guide provides comprehensive CI/CD integration workflows for the Chinook music platform. Continue with the media library enhancement guide for detailed media handling workflows.*

[⬆️ Back to Top](#1-cicd-integration-guide)
