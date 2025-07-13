# Test Environment Setup Guide

This guide covers the complete setup and configuration of testing environments for the Chinook Filament 4 admin panel,
including database configuration, authentication setup, and testing utilities.

## Table of Contents

- [Overview](#overview)
- [Prerequisites](#prerequisites)
- [Database Configuration](#database-configuration)
- [Authentication Setup](#authentication-setup)
- [Testing Dependencies](#testing-dependencies)
- [Environment Configuration](#environment-configuration)
- [Test Data Setup](#test-data-setup)
- [Performance Optimization](#performance-optimization)
- [Troubleshooting](#troubleshooting)

## Overview

The Chinook admin panel testing environment requires specific configuration to ensure reliable, fast, and isolated
testing. This guide provides step-by-step instructions for setting up a comprehensive testing environment that supports
all testing types.

### Testing Environment Goals

- **Isolation**: Tests run in complete isolation from development/production data
- **Performance**: Fast test execution with optimized database configuration
- **Reliability**: Consistent test results across different environments
- **Completeness**: Support for all testing types (unit, feature, integration, browser)
- **RBAC Integration**: Proper role and permission testing setup

## Prerequisites

### Required Software

```bash
# PHP 8.4+ with required extensions
php -v
php -m | grep -E "(sqlite|pdo_sqlite|mbstring|openssl|tokenizer|xml|ctype|json)"

# Composer for dependency management
composer --version

# Node.js for frontend asset compilation (if needed)
node --version
npm --version
```

### Laravel Installation

```bash
# Ensure Laravel 12+ is installed
php artisan --version

# Verify Filament 4 installation
composer show filament/filament
```

## Database Configuration

### SQLite Testing Database

Configure SQLite for optimal testing performance:

```php
// config/database.php
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
    'journal_mode' => 'WAL',
    'synchronous' => 'NORMAL',
    'cache_size' => 10000,
    'temp_store' => 'MEMORY',
],
```

### Environment Configuration

```bash
# .env.testing
APP_ENV=testing
APP_DEBUG=true
APP_KEY=base64:your-testing-key-here

# Database
DB_CONNECTION=testing
DB_DATABASE=:memory:

# Cache
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync

# Mail
MAIL_MAILER=array

# Disable external services
TELESCOPE_ENABLED=false
PULSE_ENABLED=false
```

### Database Migrations for Testing

```php
// tests/TestCase.php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations
        $this->artisan('migrate:fresh');
        
        // Seed essential data
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'TestUserSeeder']);
    }
}
```

## Authentication Setup

### Test User Creation

```php
// database/seeders/TestUserSeeder.php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create test users for each role
        $roles = ['Super Admin', 'Admin', 'Manager', 'Editor', 'Customer Service', 'User', 'Guest'];
        
        foreach ($roles as $roleName) {
            $user = User::factory()->create([
                'email' => strtolower(str_replace(' ', '.', $roleName)) . '@test.com',
                'name' => "Test {$roleName}",
                'email_verified_at' => now(),
            ]);
            
            $role = Role::findByName($roleName);
            $user->assignRole($role);
        }
    }
}
```

### Authentication Helper Traits

```php
// tests/Traits/AuthenticationHelpers.php
<?php

namespace Tests\Traits;

use App\Models\User;
use Spatie\Permission\Models\Role;

trait AuthenticationHelpers
{
    protected function actingAsAdmin(): self
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        return $this->actingAs($admin);
    }
    
    protected function actingAsEditor(): self
    {
        $editor = User::factory()->create();
        $editor->assignRole('Editor');
        
        return $this->actingAs($editor);
    }
    
    protected function actingAsGuest(): self
    {
        $guest = User::factory()->create();
        $guest->assignRole('Guest');
        
        return $this->actingAs($guest);
    }
    
    protected function actingAsRole(string $roleName): self
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        
        return $this->actingAs($user);
    }
}
```

## Testing Dependencies

### Pest PHP Installation

```bash
# Install Pest and plugins
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
composer require --dev pestphp/pest-plugin-livewire
composer require --dev pestphp/pest-plugin-faker
composer require --dev pestphp/pest-plugin-type-coverage

# Initialize Pest
./vendor/bin/pest --init
```

### Filament Testing Utilities

```bash
# Install Filament testing utilities
composer require --dev filament/testing
```

### Browser Testing Setup

```bash
# Install Laravel Dusk for browser testing
composer require --dev laravel/dusk

# Install Dusk
php artisan dusk:install

# Install Chrome driver
php artisan dusk:chrome-driver
```

## Environment Configuration

### Pest Configuration

```php
// tests/Pest.php
<?php

use Tests\TestCase;
use Tests\Traits\AuthenticationHelpers;

uses(TestCase::class, AuthenticationHelpers::class)->in('Feature');
uses(TestCase::class)->in('Unit');

// Global functions for testing
function createAdmin(): \App\Models\User
{
    $admin = \App\Models\User::factory()->create();
    $admin->assignRole('Admin');
    return $admin;
}

function createEditor(): \App\Models\User
{
    $editor = \App\Models\User::factory()->create();
    $editor->assignRole('Editor');
    return $editor;
}

function createGuest(): \App\Models\User
{
    $guest = \App\Models\User::factory()->create();
    $guest->assignRole('Guest');
    return $guest;
}
```

### PHPUnit Configuration

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
        <env name="DB_CONNECTION" value="testing"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

## Test Data Setup

### Factory Configuration

```php
// database/factories/UserFactory.php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
    
    public function withRole(string $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            $user->assignRole($role);
        });
    }
}
```

## Performance Optimization

### Database Optimization

```php
// config/database.php - Testing optimizations
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
    
    // Performance optimizations
    'options' => [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
    
    // SQLite-specific optimizations
    'pragma' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => 10000,
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB
    ],
],
```

### Parallel Testing

```bash
# Run tests in parallel
./vendor/bin/pest --parallel

# Configure parallel processes
./vendor/bin/pest --parallel --processes=4
```

## Troubleshooting

### Common Issues

#### Database Connection Issues

```bash
# Check SQLite extension
php -m | grep sqlite

# Verify database configuration
php artisan config:show database.connections.testing
```

#### Permission Issues

```bash
# Clear and cache permissions
php artisan permission:cache-reset
php artisan config:clear
php artisan cache:clear
```

#### Memory Issues

```bash
# Increase memory limit for testing
php -d memory_limit=512M vendor/bin/pest
```

### Performance Issues

#### Slow Tests

- Use database transactions instead of migrations
- Optimize factory relationships
- Use `RefreshDatabase` trait efficiently
- Consider using `DatabaseTransactions` for faster tests

#### Memory Leaks

- Clear model instances between tests
- Reset static properties
- Use `gc_collect_cycles()` for memory cleanup

## Related Documentation

- **[Testing Strategy](010-testing-strategy.md)** - Overall testing approach
- **[Test Data Management](030-test-data-management.md)** - Factory and seeder patterns
- **[Resource Testing](040-resource-testing.md)** - Filament resource testing
- **[Performance Testing](120-performance-testing.md)** - Load testing and optimization

---

## Navigation

**← Previous:** [Testing Strategy](010-testing-strategy.md)

**Next →** [Test Data Management](030-test-data-management.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
