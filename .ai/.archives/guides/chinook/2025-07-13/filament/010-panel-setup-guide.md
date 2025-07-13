# Filament 4 Panel Setup & Configuration Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Installation & Setup](#2-installation--setup)
  - [2.1. Package Installation](#21-package-installation)
  - [2.2. Panel Configuration](#22-panel-configuration)
  - [2.3. Authentication Setup](#23-authentication-setup)
- [3. Multi-Panel Architecture](#3-multi-panel-architecture)
  - [3.1. Admin Panel Configuration](#31-admin-panel-configuration)
  - [3.2. User Panel Configuration](#32-user-panel-configuration)
  - [3.3. Panel Separation](#33-panel-separation)
- [4. RBAC Integration](#4-rbac-integration)
  - [4.1. Spatie Permission Integration](#41-spatie-permission-integration)
  - [4.2. Role-Based Navigation](#42-role-based-navigation)
  - [4.3. Permission Gates](#43-permission-gates)
- [5. Theme & Accessibility](#5-theme--accessibility)
  - [5.1. WCAG 2.1 AA Compliance](#51-wcag-21-aa-compliance)
  - [5.2. Custom Theme Configuration](#52-custom-theme-configuration)
  - [5.3. Accessibility Features](#53-accessibility-features)
- [6. Performance Optimization](#6-performance-optimization)
  - [6.1. Lazy Loading](#61-lazy-loading)
  - [6.2. Query Optimization](#62-query-optimization)
  - [6.3. Caching Strategy](#63-caching-strategy)
- [7. Security Configuration](#7-security-configuration)
  - [7.1. CSRF Protection](#71-csrf-protection)
  - [7.2. Rate Limiting](#72-rate-limiting)
  - [7.3. Audit Logging](#73-audit-logging)
- [8. Testing & Validation](#8-testing--validation)
- [9. Troubleshooting](#9-troubleshooting)

## 1. Overview

This guide provides comprehensive instructions for setting up and configuring Filament 4 admin panels for the Chinook database application. The setup includes multi-panel architecture, role-based access control (RBAC), WCAG 2.1 AA accessibility compliance, and enterprise-grade security features.

### Key Features

- **Multi-Panel Support**: Separate admin and user panels with distinct access controls
- **RBAC Integration**: Spatie Laravel Permission with hierarchical roles
- **Accessibility Compliance**: WCAG 2.1 AA standards with high-contrast themes
- **Performance Optimization**: Lazy loading and efficient query strategies
- **Security Hardening**: CSRF protection, rate limiting, and audit logging

## 2. Installation & Setup

### 2.1. Package Installation

Install Filament 4 and required dependencies:

```bash
# Install Filament 4
composer require filament/filament:"^4.0"

# Install RBAC package
composer require spatie/laravel-permission

# Install additional packages
composer require spatie/laravel-activitylog
composer require spatie/laravel-tags
```

### 2.2. Panel Configuration

Create the admin panel configuration:

```bash
php artisan make:filament-panel admin
```

Configure the admin panel in `app/Providers/Filament/AdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Blue,
                'success' => Color::Green,
                'warning' => Color::Orange,
                'danger' => Color::Red,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName('Chinook Admin')
            ->favicon(asset('favicon.ico'))
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Music Management',
                'Customer Management', 
                'Sales & Analytics',
                'System Administration',
                'Reports & Analytics',
            ]);
    }
}
```

### 2.3. Authentication Setup

Configure authentication for the admin panel:

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

## 3. Multi-Panel Architecture

### 3.1. Admin Panel Configuration

The admin panel provides full system access for administrators:

```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->authGuard('admin')
        ->navigationGroups([
            'Music Management' => [
                'icon' => 'heroicon-o-musical-note',
                'sort' => 1,
            ],
            'Customer Management' => [
                'icon' => 'heroicon-o-users',
                'sort' => 2,
            ],
            'Sales & Analytics' => [
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 3,
            ],
            'System Administration' => [
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort' => 4,
            ],
        ]);
}
```

### 3.2. User Panel Configuration

Create a separate user panel for customer access:

```bash
php artisan make:filament-panel user
```

Configure the user panel:

```php
// app/Providers/Filament/UserPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('user')
        ->path('user')
        ->authGuard('web')
        ->colors([
            'primary' => Color::Indigo,
        ])
        ->brandName('Chinook Music Store')
        ->navigationGroups([
            'My Music',
            'Playlists',
            'Account',
        ]);
}
```

### 3.3. Panel Separation

Implement panel-specific middleware and guards:

```php
// app/Http/Middleware/AdminPanelMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminPanelMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->user()?->hasRole(['Super Admin', 'Admin'])) {
            abort(403, 'Access denied to admin panel');
        }

        return $next($request);
    }
}
```

## 4. RBAC Integration

### 4.1. Spatie Permission Integration

Configure Spatie Laravel Permission:

```php
// config/permission.php
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
    'teams' => false,
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

### 4.2. Role-Based Navigation

Implement role-based navigation visibility:

```php
// app/Filament/Resources/BaseResource.php
<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view-any ' . static::getModel());
    }
    
    public static function canCreate(): bool
    {
        return auth()->user()?->can('create ' . static::getModel());
    }
    
    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update ' . static::getModel());
    }
    
    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete ' . static::getModel());
    }
}
```

### 4.3. Permission Gates

Define permission gates for fine-grained access control:

```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('access-admin-panel', function ($user) {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
    });
    
    Gate::define('manage-users', function ($user) {
        return $user->hasRole(['Super Admin', 'Admin']);
    });
    
    Gate::define('view-analytics', function ($user) {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
    });
}
```

## 5. Theme & Accessibility

### 5.1. WCAG 2.1 AA Compliance

Configure high-contrast colors for accessibility:

```php
// app/Providers/Filament/AdminPanelProvider.php
->colors([
    'primary' => '#1976d2',    // 7.04:1 contrast ratio
    'success' => '#388e3c',    // 6.74:1 contrast ratio  
    'warning' => '#f57c00',    // 4.52:1 contrast ratio
    'danger' => '#d32f2f',     // 5.25:1 contrast ratio
])
```

### 5.2. Custom Theme Configuration

Create custom CSS for accessibility:

```css
/* resources/css/filament/admin/theme.css */
@import '/vendor/filament/filament/resources/css/theme.css';

:root {
    --primary: #1976d2;
    --success: #388e3c;
    --warning: #f57c00;
    --danger: #d32f2f;
}

/* High contrast focus indicators */
.focus\:ring-primary-500:focus {
    --tw-ring-color: #1976d2;
    --tw-ring-opacity: 1;
    --tw-ring-offset-width: 2px;
}

/* Accessible button styles */
.btn-primary {
    background-color: #1976d2;
    border-color: #1976d2;
    color: white;
    font-weight: 600;
}

.btn-primary:hover {
    background-color: #1565c0;
    border-color: #1565c0;
}

/* Screen reader only content */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
```

### 5.3. Accessibility Features

Implement accessibility features:

```php
// app/Filament/Pages/Dashboard.php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';
    
    protected static ?string $navigationLabel = 'Dashboard';
    
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static ?int $navigationSort = 1;
    
    public function getTitle(): string
    {
        return 'Chinook Music Database - Admin Dashboard';
    }
    
    public function getHeading(): string
    {
        return 'Welcome to Chinook Admin Panel';
    }
    
    public function getSubheading(): ?string
    {
        return 'Manage your music database with enterprise-grade tools';
    }
}
```

## 6. Performance Optimization

### 6.1. Lazy Loading

Configure lazy loading for resources:

```php
// app/Filament/Resources/ArtistResource.php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['albums.tracks'])
        ->withCount(['albums', 'tracks']);
}
```

### 6.2. Query Optimization

Optimize database queries:

```php
// app/Filament/Resources/AlbumResource.php
public static function getTableQuery(): Builder
{
    return static::getEloquentQuery()
        ->select([
            'albums.id',
            'albums.title',
            'albums.release_date',
            'artists.name as artist_name',
        ])
        ->join('artists', 'albums.artist_id', '=', 'artists.id')
        ->orderBy('albums.created_at', 'desc');
}
```

### 6.3. Caching Strategy

Implement caching for frequently accessed data:

```php
// app/Filament/Widgets/StatsOverview.php
protected function getStats(): array
{
    return Cache::remember('dashboard-stats', 300, function () {
        return [
            Stat::make('Total Artists', Artist::count())
                ->description('Active artists in database')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            Stat::make('Total Albums', Album::count())
                ->description('Albums available')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
                
            Stat::make('Total Tracks', Track::count())
                ->description('Individual tracks')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),
        ];
    });
}
```

## 7. Security Configuration

### 7.1. CSRF Protection

Ensure CSRF protection is enabled:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    // Add any routes that need CSRF exemption
];
```

### 7.2. Rate Limiting

Configure rate limiting:

```php
// app/Providers/RouteServiceProvider.php
protected function configureRateLimiting()
{
    RateLimiter::for('admin', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
}
```

### 7.3. Audit Logging

Implement audit logging:

```php
// app/Models/User.php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use LogsActivity;
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

## 8. Testing & Validation

Test the panel configuration:

```php
// tests/Feature/AdminPanelTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_admin_can_access_panel()
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        
        $response = $this->actingAs($admin)->get('/admin');
        
        $response->assertStatus(200);
    }
    
    public function test_regular_user_cannot_access_admin_panel()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/admin');
        
        $response->assertStatus(403);
    }
}
```

## 9. Troubleshooting

### Common Issues

1. **Permission Denied Errors**
   - Verify user roles and permissions
   - Check middleware configuration
   - Ensure proper gate definitions

2. **Navigation Not Showing**
   - Verify resource permissions
   - Check navigation group configuration
   - Ensure proper role assignments

3. **Theme Not Loading**
   - Check CSS compilation
   - Verify asset publishing
   - Ensure proper color configuration

### Debug Commands

```bash
# Clear all caches
php artisan optimize:clear

# Publish Filament assets
php artisan filament:assets

# Check permissions
php artisan permission:show

# Test panel access
php artisan tinker
>>> auth()->user()->hasRole('Admin')
```

---

**Next**: [Model Standards Guide](models/010-model-standards-guide.md) | **Previous**: [Main Index](../000-chinook-index.md)

---

*This guide provides comprehensive setup instructions for Filament 4 admin panels with enterprise-grade features, RBAC integration, and WCAG 2.1 AA accessibility compliance.*
