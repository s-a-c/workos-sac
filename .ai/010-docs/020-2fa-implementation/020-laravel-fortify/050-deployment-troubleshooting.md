# 5.0 Laravel Fortify 2FA Implementation - Deployment & Troubleshooting

**Document Version**: 2.0  
**Last Updated**: 2025-07-01  
**Target Audience**: Junior Developers  
**Estimated Reading Time**: 20 minutes

## 5.1 Executive Summary

This document provides comprehensive deployment procedures and troubleshooting guidance for the unified Laravel Fortify 2FA implementation. It includes step-by-step deployment instructions, common issue resolution, performance optimization, and maintenance procedures for production environments with Laravel 12.19.3, PHP 8.4, Filament 4.0.0-beta11, and existing Google2FA Laravel package integration.

### 5.1.1 Deployment Overview

| Phase | Activity | Duration | Risk Level | Rollback Time |
|-------|----------|----------|------------|---------------|
| **Pre-Deployment** | Validation & Backup | 10 minutes | 🟢 Low | N/A |
| **Package Installation** | Fortify & Dependencies | 15 minutes | 🟡 Medium | 5 minutes |
| **Database Migration** | Schema & Data Migration | 20 minutes | 🔴 High | 10 minutes |
| **System Activation** | Switch to Fortify Auth | 10 minutes | 🔴 High | 5 minutes |
| **Post-Deployment** | Validation & Monitoring | 15 minutes | 🟢 Low | N/A |

## 5.2 Pre-Deployment Preparation

### 5.2.1 Environment Validation

**System Requirements Check**:

```bash
# Verify Laravel version compatibility
php artisan --version
# Expected: Laravel Framework 12.19.3

# Check PHP version
php --version
# Expected: PHP 8.4.x

# Verify database connectivity
php artisan tinker
>>> DB::connection()->getPdo()
>>> exit

# Check current user count with 2FA (if any existing)
php artisan tinker
>>> User::whereNotNull('app_authentication_secret')->count()
>>> exit

# Verify existing Google2FA package
composer show pragmarx/google2fa-laravel
# Expected: 2.3.0
```

**Backup Procedures**:

```bash
# Create comprehensive backup
BACKUP_DIR="backups/fortify-migration-$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

# Backup database
cp database/database.sqlite $BACKUP_DIR/database.sqlite.backup

# Backup configuration files
cp -r config/ $BACKUP_DIR/config/
cp .env $BACKUP_DIR/.env.backup

# Backup current User model
cp app/Models/User.php $BACKUP_DIR/User.php.backup

# Backup Filament AdminPanelProvider
cp app/Providers/Filament/AdminPanelProvider.php $BACKUP_DIR/AdminPanelProvider.php.backup

echo "Backup created in: $BACKUP_DIR"
```

### 5.2.2 Pre-Deployment Testing

**Validation Commands**:

```bash
# Run existing tests to ensure system stability
php artisan test

# Check for any pending migrations
php artisan migrate:status

# Verify Composer dependencies
composer validate
composer check-platform-reqs

# Test current authentication flow
php artisan tinker
>>> $user = User::first()
>>> $user->canAccessPanel(\Filament\Facades\Filament::getDefaultPanel())
>>> exit
```

## 5.3 Step-by-Step Deployment Process

### 5.3.1 Phase 1: Package Installation

**Install Required Packages**:

```bash
# Install Laravel Fortify
composer require laravel/fortify "^1.25"

# Install Laravel Sanctum (required dependency)
composer require laravel/sanctum "^4.0"

# Verify installation
composer show laravel/fortify laravel/sanctum

# Install Fortify and Sanctum
composer require laravel/fortify "^1.27"
php artisan install:api  # Installs and configures Sanctum
php artisan fortify:install  # Publishes Fortify resources
```

**Update Environment Configuration**:

```bash
# Add to .env file
cat >> .env << 'EOF'

# Laravel Fortify Configuration
FORTIFY_GUARD=web
FORTIFY_PASSWORDS=users
FORTIFY_USERNAME=email
FORTIFY_HOME=/dashboard

# Two-Factor Authentication Settings
TWO_FACTOR_AUTH_ENABLED=true
TWO_FACTOR_RECOVERY_CODES=8
TWO_FACTOR_CONFIRM_PASSWORD_TIMEOUT=10800

# Application 2FA Settings
APP_2FA_ISSUER="${APP_NAME}"
APP_2FA_DIGITS=6
APP_2FA_PERIOD=30
APP_2FA_ALGORITHM=sha1
EOF
```

### 5.3.2 Phase 2: Database Migration

**Execute Migration Sequence**:

```bash
# Generate migration files (if not already created)
php artisan make:migration add_fortify_two_factor_fields_to_users_table --table=users
php artisan make:migration migrate_filament_to_fortify_2fa_data
php artisan make:migration add_fortify_2fa_indexes_to_users_table --table=users

# Execute migrations step by step
php artisan migrate --step

# Verify migration success
php artisan migrate:status

# Validate data migration manually
php artisan tinker
>>> User::whereNotNull('two_factor_secret')->count()
>>> exit
```

**Data Migration Verification**:

```bash
# Check migration results
php artisan tinker
>>> $filamentUsers = User::whereNotNull('app_authentication_secret')->count()
>>> $fortifyUsers = User::whereNotNull('two_factor_secret')->count()
>>> echo "Filament users: $filamentUsers, Fortify users: $fortifyUsers"
>>> exit
```

### 5.3.3 Phase 3: Service Provider Implementation

**Create and Register FortifyServiceProvider**:

```bash
# Generate service provider
php artisan make:provider FortifyServiceProvider

# Create Fortify action classes
mkdir -p app/Actions/Fortify

# Register service provider in bootstrap/providers.php
# (Manual step - add App\Providers\FortifyServiceProvider::class)

# Clear configuration cache
php artisan config:clear
php artisan config:cache
```

### 5.3.4 Phase 4: User Model and Filament Integration

**Update User Model**:

```bash
# Backup current User model
cp app/Models/User.php app/Models/User.php.backup

# Update User model with Fortify integration
# (Manual step - implement TwoFactorAuthenticatable trait)

# Create custom Filament middleware
php artisan make:middleware FortifyAuthenticateForFilament

# Update AdminPanelProvider
# (Manual step - configure for Fortify authentication)
```

### 5.3.5 Phase 5: UI Components Deployment

**Deploy Volt + Flux Components**:

```bash
# Create component directories
mkdir -p resources/views/livewire/auth/two-factor

# Deploy authentication components
# (Manual step - create Volt components)

# Clear view cache
php artisan view:clear

# Test component rendering
php artisan tinker
>>> view('livewire.auth.login')->render()
>>> exit
```

## 5.4 Post-Deployment Validation

### 5.4.1 System Validation Tests

**Authentication Flow Testing**:

```bash
# Test login functionality
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test 2FA setup endpoint
curl -X GET http://localhost:8000/two-factor/setup \
  -H "Authorization: Bearer {token}"

# Test admin panel access
curl -X GET http://localhost:8000/admin \
  -H "Authorization: Bearer {token}"
```

**Database Integrity Validation**:

```bash
# Run comprehensive validation
php artisan test --filter=TwoFactorAuthentication

# Check user model functionality manually
php artisan tinker
>>> $user = User::first()
>>> $user->hasEnabledTwoFactorAuthentication()
>>> exit

# Verify performance
php artisan tinker
>>> $start = microtime(true)
>>> User::where('two_factor_confirmed_at', '!=', null)->count()
>>> echo (microtime(true) - $start) * 1000 . "ms"
>>> exit
```

### 5.4.2 Security Validation

**Security Checklist**:

```bash
# Verify encryption
php artisan tinker
>>> $user = User::whereNotNull('two_factor_secret')->first()
>>> $secret = decrypt($user->two_factor_secret)
>>> echo strlen($secret) // Should be 32 characters
>>> exit

# Test rate limiting
for i in {1..10}; do
  curl -X POST http://localhost:8000/login \
    -H "Content-Type: application/json" \
    -d '{"email":"invalid@example.com","password":"wrong"}'
done

# Verify CSRF protection
curl -X POST http://localhost:8000/user/two-factor-authentication
# Should return 419 (CSRF token mismatch)
```

## 5.5 Common Issues and Troubleshooting

### 5.5.1 Installation Issues

**Issue**: Composer dependency conflicts
**Symptoms**: Package installation fails with version conflicts
**Solution**:
```bash
# Clear Composer cache
composer clear-cache

# Update Composer to latest version
composer self-update

# Install with specific version constraints
composer require laravel/fortify "^1.25" --with-all-dependencies

# If conflicts persist, check platform requirements
composer check-platform-reqs
```

**Issue**: Configuration files not published
**Symptoms**: config/fortify.php missing
**Solution**:
```bash
# Force republish Fortify configuration
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider" --force

# Verify files exist
ls -la config/fortify.php config/sanctum.php

# Clear configuration cache
php artisan config:clear
```

### 5.5.2 Migration Issues

**Issue**: Migration fails with foreign key constraints
**Symptoms**: Database migration errors
**Solution**:
```bash
# For SQLite, disable foreign key checks temporarily
php artisan tinker
>>> DB::statement('PRAGMA foreign_keys=OFF;')
>>> Artisan::call('migrate')
>>> DB::statement('PRAGMA foreign_keys=ON;')
>>> exit
```

**Issue**: Data migration incomplete
**Symptoms**: Users missing Fortify 2FA data
**Solution**:
```bash
# Run manual data migration
php artisan tinker
>>> User::whereNotNull('app_authentication_secret')
    ->whereNull('two_factor_secret')
    ->chunk(100, function ($users) {
        foreach ($users as $user) {
            $user->migrateToFortify2FA();
        }
    });
>>> exit

# Validate migration manually
php artisan tinker
>>> User::whereNotNull('two_factor_secret')->count()
>>> exit
```

### 5.5.3 Authentication Issues

**Issue**: Admin panel not accessible
**Symptoms**: Redirect loop or 403 errors
**Solution**:
```bash
# Check middleware registration
php artisan route:list | grep admin

# Verify user 2FA status
php artisan tinker
>>> $user = User::find(1)
>>> $user->hasEnabledTwoFactorAuthentication()
>>> $user->canAccessPanel(\Filament\Facades\Filament::getDefaultPanel())
>>> exit

# Clear route cache
php artisan route:clear
php artisan route:cache
```

**Issue**: 2FA QR code not displaying
**Symptoms**: Blank QR code or errors
**Solution**:
```bash
# Verify Google2FA package
composer show pragmarx/google2fa

# Check secret encryption
php artisan tinker
>>> $user = User::whereNotNull('two_factor_secret')->first()
>>> $secret = decrypt($user->two_factor_secret)
>>> echo strlen($secret) // Should be 32
>>> exit

# Test QR code generation
php artisan tinker
>>> $user = User::whereNotNull('two_factor_secret')->first()
>>> echo $user->twoFactorQrCodeSvg()
>>> exit
```

### 5.5.4 UI Component Issues

**Issue**: Volt components not rendering
**Symptoms**: Blank pages or component errors
**Solution**:
```bash
# Clear view cache
php artisan view:clear

# Verify Volt installation
composer show livewire/volt

# Check component syntax
php artisan tinker
>>> view('livewire.auth.login')->render()
>>> exit

# Restart development server
php artisan serve --host=0.0.0.0 --port=8000
```

**Issue**: Flux components not styled
**Symptoms**: Unstyled form elements
**Solution**:
```bash
# Verify Flux installation
composer show livewire/flux livewire/flux-pro

# Check if Flux CSS is included
grep -r "flux" resources/css/
grep -r "flux" resources/js/

# Rebuild assets
npm run build
```

## 5.6 Performance Optimization

### 5.6.1 Database Optimization

**Index Optimization**:

```bash
# Verify indexes are created
php artisan tinker
>>> Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('users')
>>> exit

# Test query performance
php artisan tinker
>>> $start = microtime(true)
>>> User::where('two_factor_confirmed_at', '!=', null)->count()
>>> echo (microtime(true) - $start) * 1000 . "ms"
>>> exit
```

**Query Optimization**:

```sql
-- Optimize common 2FA queries
EXPLAIN QUERY PLAN 
SELECT * FROM users 
WHERE two_factor_confirmed_at IS NOT NULL;

-- Verify index usage
EXPLAIN QUERY PLAN 
SELECT * FROM users 
WHERE email = 'test@example.com' 
AND two_factor_confirmed_at IS NOT NULL;
```

### 5.6.2 Caching Configuration

**Optimize Configuration Caching**:

```bash
# Cache configuration for production
php artisan config:cache

# Cache routes for production
php artisan route:cache

# Cache views for production
php artisan view:cache

# Optimize Composer autoloader
composer install --optimize-autoloader --no-dev
```

## 5.7 Monitoring and Maintenance

### 5.7.1 Health Monitoring

**Create Health Check Command**:

```bash
# Run application tests for health check
php artisan test

# Check application status
php artisan route:list | grep fortify

# Monitor key metrics
php artisan tinker
>>> echo "Total users: " . User::count()
>>> echo "Users with 2FA: " . User::whereNotNull('two_factor_confirmed_at')->count()
>>> echo "Recent logins: " . User::where('updated_at', '>', now()->subDay())->count()
>>> exit
```

### 5.7.2 Backup and Recovery

**Automated Backup Script**:

```bash
#!/bin/bash
# backup-fortify-system.sh

BACKUP_DIR="backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

# Database backup
cp database/database.sqlite $BACKUP_DIR/

# Configuration backup
tar -czf $BACKUP_DIR/config.tar.gz config/ .env

# User data export
php artisan tinker --execute="
User::whereNotNull('two_factor_confirmed_at')
    ->get(['id', 'email', 'two_factor_confirmed_at'])
    ->toJson()
" > $BACKUP_DIR/2fa_users.json

echo "Backup completed: $BACKUP_DIR"
```

### 5.7.3 Security Maintenance

**Regular Security Checks**:

```bash
# Check for failed login attempts
php artisan tinker
>>> DB::table('failed_jobs')->where('created_at', '>', now()->subDay())->count()
>>> exit

# Verify 2FA secret rotation
php artisan tinker
>>> User::whereNotNull('two_factor_confirmed_at')
    ->where('two_factor_confirmed_at', '<', now()->subMonths(6))
    ->count()
>>> exit

# Monitor recovery code usage
grep "recovery code" storage/logs/laravel.log | tail -10
```

## 5.8 Rollback Procedures

### 5.8.1 Emergency Rollback

**Quick Rollback Script**:

```bash
#!/bin/bash
# emergency-rollback.sh

echo "Starting emergency rollback..."

# Restore database backup
cp backups/latest/database.sqlite.backup database/database.sqlite

# Restore configuration
cp backups/latest/.env.backup .env
cp -r backups/latest/config/ config/

# Restore User model
cp backups/latest/User.php.backup app/Models/User.php

# Restore AdminPanelProvider
cp backups/latest/AdminPanelProvider.php.backup app/Providers/Filament/AdminPanelProvider.php

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Emergency rollback completed"
```

### 5.8.2 Controlled Rollback

**Step-by-Step Rollback**:

```bash
# Rollback migrations manually
php artisan migrate:rollback --step=3

# Remove Fortify packages
composer remove laravel/fortify laravel/sanctum

# Restore original configuration
git checkout HEAD~1 -- config/auth.php
git checkout HEAD~1 -- app/Providers/Filament/AdminPanelProvider.php

# Clear all caches
php artisan optimize:clear

# Verify system functionality
php artisan test
```

---

**Navigation Footer**

← [Previous: UI Components & Testing](040-ui-components-testing.md) | [Next: Implementation Overview →](../README.md)

---

**Document Information**
- **File Path**: `.ai/010-docs/020-2fa-implementation/020-laravel-fortify/050-deployment-troubleshooting.md`
- **Document ID**: LF-2FA-005-CONSOLIDATED
- **Version**: 2.0
- **Compliance**: WCAG AA, Junior Developer Guidelines
