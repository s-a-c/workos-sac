# 2.2 Laravel Package Ecosystem Integration

## 2.2.1 Executive Summary

This document provides comprehensive guidance for integrating the Laravel package ecosystem within the UMS-STI system. The implementation focuses on Spatie packages, dependency management best practices, and strategic package selection to support Single Table Inheritance, team hierarchy management, permission systems, and GDPR compliance requirements.

## 2.2.2 Learning Objectives

After completing this guide, you will:
- Understand the complete package ecosystem for UMS-STI
- Implement Spatie packages with optimal configuration
- Manage package dependencies and version compatibility
- Configure packages for Laravel 12.x compatibility
- Establish package integration testing strategies

## 2.2.3 Prerequisite Knowledge

- **Laravel 12.x** framework fundamentals
- **Composer** dependency management
- **PHP 8.4+** features and compatibility
- Basic understanding of package architecture
- Familiarity with Laravel service providers

## 2.2.4 Core Package Ecosystem

### 2.2.4.1 Spatie Package Suite

The UMS-STI system leverages several Spatie packages for core functionality:

```bash
# Permission and Role Management
composer require spatie/laravel-permission:^6.0

# Model State Management
composer require spatie/laravel-model-states:^2.0

# Event Sourcing Foundation
composer require spatie/laravel-event-sourcing:^7.0

# Data Transfer Objects
composer require spatie/laravel-data:^4.0

# Query Builder Enhancements
composer require spatie/laravel-query-builder:^6.0

# Activity Logging
composer require spatie/laravel-activitylog:^4.0
```

### 2.2.4.2 Additional Core Packages

```bash
# Unique ID Generation (Snowflake-style)
composer require glhd/bits:^1.0

# Testing Framework
composer require pestphp/pest:^3.0 --dev
composer require pestphp/pest-plugin-laravel:^3.0 --dev

# Code Quality
composer require larastan/larastan:^2.0 --dev
composer require friendsofphp/php-cs-fixer:^3.0 --dev
```

#### 2.2.4.3 UUID/ULID Support Analysis

**Why not ramsey/uuid:^4.7 and robinvdvleuten/ulid:^5.0?**

Laravel 12.x includes `symfony/uid` (v7.3.0) as a core dependency, which provides comprehensive UUID and ULID support:

**Symfony/UID Capabilities:**
- **UUID v1, v3, v4, v6, v7, v8** generation and validation
- **ULID** (Universally Unique Lexicographically Sortable Identifier) support
- **Time-based ordering** with ULID for better database performance
- **Binary optimization** for storage efficiency
- **Laravel integration** via built-in support

**Comparison with Standalone Packages:**

| Feature | symfony/uid | ramsey/uuid | robinvdvleuten/ulid |
|---------|-------------|-------------|-------------------|
| UUID Support | ✅ All versions | ✅ All versions | ❌ |
| ULID Support | ✅ Native | ❌ | ✅ Native |
| Laravel Integration | ✅ Built-in | ⚠️ Manual | ⚠️ Manual |
| Binary Storage | ✅ Optimized | ⚠️ Manual | ⚠️ Manual |
| Memory Footprint | ✅ Smaller | ❌ Larger | ✅ Smaller |
| Maintenance | ✅ Symfony team | ✅ Active | ⚠️ Limited |

**Recommendation:**
Use `symfony/uid` (already included) for all UUID/ULID needs. The `glhd/bits` package provides Snowflake-style IDs for specific use cases requiring Twitter-like distributed ID generation.

#### 2.2.4.4 Practical Usage Examples

**Using UUIDs with Symfony/UID:**

```php
// In a Laravel model
use Symfony\Component\Uid\Uuid;

class User extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Uuid::v4()->toRfc4122();
            }
        });
    }
}
```

**Using ULIDs with Symfony/UID:**

```php
// In a Laravel model for time-ordered IDs
use Symfony\Component\Uid\Ulid;

class Event extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Ulid::generate();
            }
        });
    }
}
```

**Migration Examples:**

```php
// UUID primary key migration
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->timestamps();
});

// ULID primary key migration (better for time-based ordering)
Schema::create('events', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('type');
    $table->timestamps();
});
```

## 2.2.5 Package Configuration

### 2.2.5.1 Spatie Laravel Permission

```php
// config/permission.php
<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,

    'teams' => true, // Enable team-scoped permissions
    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
```

### 2.2.5.2 Spatie Laravel Event Sourcing

```php
// config/event-sourcing.php
<?php

return [
    'stored_event_model' => \Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent::class,

    'stored_event_repository' => \Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository::class,

    'aggregate_repository' => \Spatie\EventSourcing\AggregateRoots\AggregateRepository::class,

    'snapshot_repository' => \Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository::class,

    'stored_snapshots' => [
        'table' => 'stored_snapshots',
    ],

    'stored_events' => [
        'table' => 'stored_events',
        'connection' => 'event_store', // Separate database connection
    ],

    'projectors' => [
        // Auto-discover projectors in app/Projectors
    ],

    'reactors' => [
        // Auto-discover reactors in app/Reactors
    ],

    'replay' => [
        'chunk_size' => 1000,
    ],

    'queue' => env('EVENT_SOURCING_QUEUE_CONNECTION', 'sync'),
];
```

### 2.2.5.3 Spatie Laravel Model States

```php
// config/model-states.php
<?php

return [
    'default_state_field' => 'state',

    'state_machines' => [
        // Auto-discover state machines
    ],
];
```

## 2.2.6 Service Provider Registration

### 2.2.6.1 Laravel 12.x Provider Registration

```php
// bootstrap/providers.php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\EventSourcingServiceProvider::class,
    App\Providers\PermissionServiceProvider::class,
    App\Providers\StateManagementServiceProvider::class,
];
```

### 2.2.6.2 Custom Service Providers

```php
// app/Providers/EventSourcingServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;

class EventSourcingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register event sourcing services
    }

    public function boot(): void
    {
        // Auto-discover projectors and reactors
        Projectionist::addProjectors([
            \App\Projectors\UserProjector::class,
            \App\Projectors\TeamProjector::class,
        ]);

        Projectionist::addReactors([
            \App\Reactors\NotificationReactor::class,
            \App\Reactors\AuditLogReactor::class,
        ]);
    }
}
```

## 2.2.7 Package Integration Testing

### 2.2.7.1 Permission Package Testing

```php
// tests/Feature/PermissionIntegrationTest.php
<?php

use App\Models\User\StandardUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('integrates spatie permission package correctly', function () {
    $user = StandardUser::factory()->create();
    $role = Role::create(['name' => 'admin']);
    $permission = Permission::create(['name' => 'edit users']);

    $role->givePermissionTo($permission);
    $user->assignRole($role);

    expect($user->hasPermissionTo('edit users'))->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
});
```

### 2.2.7.2 Event Sourcing Integration Testing

```php
// tests/Feature/EventSourcingIntegrationTest.php
<?php

use App\Events\UserRegistered;
use App\Models\User\StandardUser;
use Spatie\EventSourcing\Facades\Projectionist;

it('integrates event sourcing package correctly', function () {
    $userId = 'user-123';

    event(new UserRegistered($userId, 'John Doe', 'john@example.com'));

    Projectionist::replay();

    $user = StandardUser::where('public_id', $userId)->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('John Doe');
});
```

## 2.2.8 Dependency Management

### 2.2.8.1 Version Compatibility Matrix

| Package | Version | Laravel 12.x | PHP 8.4+ | Notes |
|---------|---------|--------------|----------|-------|
| spatie/laravel-permission | ^6.20 | ✅ | ✅ | Team-scoped permissions |
| spatie/laravel-model-states | ^2.11 | ✅ | ✅ | Enhanced enum support |
| spatie/laravel-model-status | ^1.18 | ✅ | ✅ | Status tracking |
| spatie/laravel-data | ^4.15 | ✅ | ✅ | Type-safe DTOs |
| spatie/laravel-sluggable | ^3.7 | ✅ | ✅ | URL-friendly slugs |
| symfony/uid | ^7.3.0 | ✅ | ✅ | UUID/ULID (included with Laravel) |
| glhd/bits | * | ✅ | ✅ | Snowflake-style IDs |
| filament/filament | ^4.0 | ✅ | ✅ | Admin panel |
| livewire/flux | ^2.1 | ✅ | ✅ | UI components |
| pestphp/pest | ^3.8 | ✅ | ✅ | Primary testing framework |

### 2.2.8.2 Composer Configuration

```json
{
    "require": {
        "php": "^8.4",
        "laravel/framework": "^12.0",
        "filament/filament": "^4.0",
        "spatie/laravel-permission": "^6.20",
        "spatie/laravel-model-states": "^2.11",
        "spatie/laravel-model-status": "^1.18",
        "spatie/laravel-data": "^4.15",
        "spatie/laravel-sluggable": "^3.7",
        "glhd/bits": "*",
        "livewire/flux": "^2.1",
        "livewire/flux-pro": "^2.2",
        "livewire/volt": "^1.7.0"
    },
    "require-dev": {
        "pestphp/pest": "^3.8",
        "pestphp/pest-plugin-laravel": "^3.2",
        "larastan/larastan": "^3.4",
        "laravel/pint": "^1.22",
        "spatie/laravel-ray": "^1.40"
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "ergebnis/composer-normalize": true,
            "infection/extension-installer": true,
            "php-http/discovery": true
        }
    }
}
```

**Note**: This configuration reflects the current project setup. UUID/ULID functionality is provided by `symfony/uid` (included with Laravel 12.x), eliminating the need for separate `ramsey/uuid` and `robinvdvleuten/ulid` packages.

## 2.2.9 Performance Optimization

### 2.2.9.1 Package Caching

```bash
# Optimize package autoloading
composer dump-autoload --optimize

# Cache package discovery
php artisan package:discover --ansi

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache
```

### 2.2.9.2 Memory Management

```php
// config/app.php - Memory optimization
'providers' => [
    // Load only required providers in production
    App\Providers\AppServiceProvider::class,
    App\Providers\EventSourcingServiceProvider::class,
    App\Providers\PermissionServiceProvider::class,
],
```

## 2.2.10 Troubleshooting

### 2.2.10.1 Common Issues

**Package Discovery Issues**:
```bash
composer dump-autoload
php artisan package:discover --ansi
php artisan clear-compiled
```

**Permission Cache Issues**:
```bash
php artisan permission:cache-reset
php artisan cache:clear
```

**Event Sourcing Connection Issues**:
```bash
php artisan migrate --database=event_store
php artisan event-sourcing:clear-cache
```

## 2.2.11 Next Steps

This package ecosystem integration provides the foundation for:

1. **Migration Strategy** - STI-optimized database schema design
2. **Indexing Performance** - Strategic indexing for package data
3. **Database Testing** - Validation of package integrations
4. **User Models** - STI implementation with package support

## 2.2.12 References

- [Spatie Laravel Permission Documentation](https://spatie.be/docs/laravel-permission)
- [Spatie Laravel Event Sourcing Documentation](https://spatie.be/docs/laravel-event-sourcing)
- [Laravel 12.x Package Development](https://laravel.com/docs/12.x/packages)
- [Composer Documentation](https://getcomposer.org/doc/)
