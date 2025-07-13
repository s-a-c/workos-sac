# Continuous Integration Testing Guide

This guide covers comprehensive CI/CD testing pipeline integration for the Chinook Filament admin panel, including
automated testing, quality gates, and deployment validation.

## Table of Contents

- [Overview](#overview)
- [GitHub Actions Setup](#github-actions-setup)
- [Testing Pipeline](#testing-pipeline)
- [Quality Gates](#quality-gates)
- [Performance Testing](#performance-testing)
- [Security Testing](#security-testing)
- [Deployment Testing](#deployment-testing)
- [Monitoring and Alerts](#monitoring-and-alerts)

## Overview

Continuous Integration ensures code quality and reliability through automated testing pipelines. This guide provides
comprehensive CI/CD setup for the Chinook admin panel with multiple testing stages and quality gates.

### CI/CD Objectives

- **Automated Testing**: Run comprehensive test suites on every commit
- **Quality Assurance**: Enforce code quality standards and coverage requirements
- **Security Validation**: Automated security scanning and vulnerability detection
- **Performance Monitoring**: Track performance regressions and optimization opportunities
- **Deployment Safety**: Validate deployments before production release

## GitHub Actions Setup

### Main Testing Workflow

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
    
    strategy:
      matrix:
        php-version: [8.4]
        dependency-version: [prefer-stable]
        
    name: PHP ${{ matrix.php-version }} - ${{ matrix.dependency-version }}
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
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
      run: composer install --prefer-dist --no-interaction --no-progress
      
    - name: Copy environment file
      run: cp .env.example .env.testing
      
    - name: Generate application key
      run: php artisan key:generate --env=testing
      
    - name: Create SQLite database
      run: touch database/database.sqlite
      
    - name: Run migrations
      run: php artisan migrate --env=testing --force
      
    - name: Seed test data
      run: php artisan db:seed --class=TestDataSeeder --env=testing
      
    - name: Run tests with coverage
      run: ./vendor/bin/pest --coverage --min=80 --coverage-clover=coverage.xml
      
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        flags: unittests
        name: codecov-umbrella
```

### Code Quality Workflow

```yaml
# .github/workflows/code-quality.yml
name: Code Quality

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  code-quality:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.4
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-interaction
      
    - name: Run PHP CS Fixer
      run: ./vendor/bin/pint --test
      
    - name: Run PHPStan
      run: ./vendor/bin/phpstan analyse --memory-limit=2G
      
    - name: Run Rector (dry-run)
      run: ./vendor/bin/rector process --dry-run
      
    - name: Run Security Checker
      run: ./vendor/bin/security-checker security:check composer.lock
```

### Browser Testing Workflow

```yaml
# .github/workflows/browser-tests.yml
name: Browser Tests

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  dusk:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.4
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-interaction
      
    - name: Copy environment file
      run: cp .env.dusk.local .env
      
    - name: Generate application key
      run: php artisan key:generate
      
    - name: Create SQLite database
      run: touch database/database.sqlite
      
    - name: Run migrations and seed
      run: |
        php artisan migrate --force
        php artisan db:seed --class=TestDataSeeder
        
    - name: Install Chrome Driver
      run: php artisan dusk:chrome-driver
      
    - name: Start Chrome Driver
      run: ./vendor/laravel/dusk/bin/chromedriver-linux &
      
    - name: Run Laravel Server
      run: php artisan serve &
      
    - name: Run Dusk Tests
      run: php artisan dusk
      
    - name: Upload Screenshots
      uses: actions/upload-artifact@v3
      if: failure()
      with:
        name: screenshots
        path: tests/Browser/screenshots
```

## Testing Pipeline

### Multi-Stage Testing

```yaml
# .github/workflows/comprehensive-testing.yml
name: Comprehensive Testing

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  unit-tests:
    name: Unit Tests
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          coverage: xdebug
      - name: Install dependencies
        run: composer install
      - name: Run unit tests
        run: ./vendor/bin/pest --group=unit --coverage --min=90

  feature-tests:
    name: Feature Tests
    runs-on: ubuntu-latest
    needs: unit-tests
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
      - name: Install dependencies
        run: composer install
      - name: Setup database
        run: |
          touch database/database.sqlite
          php artisan migrate --env=testing
          php artisan db:seed --class=TestDataSeeder --env=testing
      - name: Run feature tests
        run: ./vendor/bin/pest --group=feature

  integration-tests:
    name: Integration Tests
    runs-on: ubuntu-latest
    needs: feature-tests
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
      - name: Install dependencies
        run: composer install
      - name: Setup database
        run: |
          touch database/database.sqlite
          php artisan migrate --env=testing
          php artisan db:seed --class=TestDataSeeder --env=testing
      - name: Run integration tests
        run: ./vendor/bin/pest --group=integration

  performance-tests:
    name: Performance Tests
    runs-on: ubuntu-latest
    needs: integration-tests
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
      - name: Install dependencies
        run: composer install
      - name: Setup database
        run: |
          touch database/database.sqlite
          php artisan migrate --env=testing
          php artisan db:seed --class=TestDataSeeder --env=testing
      - name: Run performance tests
        run: ./vendor/bin/pest --group=performance
```

## Quality Gates

### Coverage Requirements

```yaml
# Quality gate configuration
- name: Check test coverage
  run: |
    ./vendor/bin/pest --coverage --min=80
    if [ $? -ne 0 ]; then
      echo "Test coverage below 80% threshold"
      exit 1
    fi
```

### Code Quality Checks

```yaml
- name: Code quality gate
  run: |
    # PHP CS Fixer
    ./vendor/bin/pint --test
    
    # PHPStan
    ./vendor/bin/phpstan analyse --error-format=github
    
    # Security check
    ./vendor/bin/security-checker security:check
    
    # Rector check
    ./vendor/bin/rector process --dry-run
```

### Performance Benchmarks

```yaml
- name: Performance benchmarks
  run: |
    # Run performance tests and check thresholds
    ./vendor/bin/pest --group=performance --log-junit=performance.xml
    
    # Check response time thresholds
    php artisan performance:check --max-response-time=200ms
```

## Performance Testing

### Load Testing Integration

```yaml
# .github/workflows/load-testing.yml
name: Load Testing

on:
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM
  workflow_dispatch:

jobs:
  load-test:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Setup application
      run: |
        composer install
        cp .env.example .env
        php artisan key:generate
        touch database/database.sqlite
        php artisan migrate --force
        php artisan db:seed --class=TestDataSeeder
        
    - name: Start application
      run: php artisan serve &
      
    - name: Install k6
      run: |
        sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
        echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
        sudo apt-get update
        sudo apt-get install k6
        
    - name: Run load tests
      run: k6 run tests/Performance/load-test.js
      
    - name: Upload results
      uses: actions/upload-artifact@v3
      with:
        name: load-test-results
        path: load-test-results.json
```

### Performance Monitoring

```javascript
// tests/Performance/load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  stages: [
    { duration: '2m', target: 10 }, // Ramp up
    { duration: '5m', target: 10 }, // Stay at 10 users
    { duration: '2m', target: 20 }, // Ramp up to 20 users
    { duration: '5m', target: 20 }, // Stay at 20 users
    { duration: '2m', target: 0 },  // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
    http_req_failed: ['rate<0.1'],    // Error rate under 10%
  },
};

export default function () {
  // Test admin panel endpoints
  let response = http.get('http://localhost:8000/chinook-admin/artists');
  check(response, {
    'status is 200': (r) => r.status === 200,
    'response time < 500ms': (r) => r.timings.duration < 500,
  });
  
  sleep(1);
}
```

## Security Testing

### Security Scanning

```yaml
# .github/workflows/security.yml
name: Security Scanning

on:
  push:
    branches: [ main, develop ]
  schedule:
    - cron: '0 0 * * 0'  # Weekly

jobs:
  security:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Run Trivy vulnerability scanner
      uses: aquasecurity/trivy-action@master
      with:
        scan-type: 'fs'
        scan-ref: '.'
        format: 'sarif'
        output: 'trivy-results.sarif'
        
    - name: Upload Trivy scan results
      uses: github/codeql-action/upload-sarif@v2
      with:
        sarif_file: 'trivy-results.sarif'
        
    - name: Run Composer security check
      run: |
        composer install
        ./vendor/bin/security-checker security:check composer.lock
```

## Deployment Testing

### Staging Deployment

```yaml
# .github/workflows/deploy-staging.yml
name: Deploy to Staging

on:
  push:
    branches: [ develop ]

jobs:
  deploy-staging:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/develop'
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Run full test suite
      run: |
        composer install
        ./vendor/bin/pest --coverage --min=80
        
    - name: Deploy to staging
      run: |
        # Deployment script here
        echo "Deploying to staging environment"
        
    - name: Run smoke tests
      run: |
        # Smoke tests against staging
        curl -f https://staging.chinook-admin.com/health || exit 1
        
    - name: Notify team
      uses: 8398a7/action-slack@v3
      with:
        status: ${{ job.status }}
        channel: '#deployments'
      env:
        SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### Production Deployment

```yaml
# .github/workflows/deploy-production.yml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy-production:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    environment: production
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4
      
    - name: Run comprehensive tests
      run: |
        composer install
        ./vendor/bin/pest --coverage --min=85
        ./vendor/bin/pest --group=performance
        
    - name: Security scan
      run: ./vendor/bin/security-checker security:check
      
    - name: Deploy to production
      run: |
        # Production deployment script
        echo "Deploying to production environment"
        
    - name: Run health checks
      run: |
        # Production health checks
        curl -f https://chinook-admin.com/health || exit 1
        
    - name: Monitor deployment
      run: |
        # Monitor for 5 minutes after deployment
        sleep 300
        curl -f https://chinook-admin.com/health || exit 1
```

## Monitoring and Alerts

### Test Result Notifications

```yaml
- name: Notify on test failure
  if: failure()
  uses: 8398a7/action-slack@v3
  with:
    status: failure
    channel: '#testing'
    text: 'Test suite failed on ${{ github.ref }}'
  env:
    SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### Performance Alerts

```yaml
- name: Performance regression alert
  if: steps.performance-test.outputs.regression == 'true'
  uses: 8398a7/action-slack@v3
  with:
    status: warning
    channel: '#performance'
    text: 'Performance regression detected in ${{ github.ref }}'
  env:
    SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

## Related Documentation

- **[Testing Strategy](010-testing-strategy.md)** - Overall testing approach
- **[Test Environment Setup](020-test-environment-setup.md)** - Environment configuration
- **[Performance Testing](120-performance-testing.md)** - Load testing strategies
- **[Security Testing](150-security-testing.md)** - Security validation

---

## Navigation

**← Previous:** [Test Data Management](030-test-data-management.md)

**Next →** [Resource Testing](040-resource-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
