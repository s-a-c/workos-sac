# Panel Configuration Guide

This guide covers the complete setup of the `chinook-admin` Filament 4 panel with service provider registration, middleware configuration, and panel-specific settings.

## Table of Contents

- [Service Provider Creation](#service-provider-creation)
- [Panel Configuration Details](#panel-configuration-details)
- [Directory Structure](#directory-structure)
- [Service Provider Registration](#service-provider-registration)
- [Navigation Groups](#navigation-groups)
- [Plugin Integration](#plugin-integration)
- [Environment Configuration](#environment-configuration)
- [Next Steps](#next-steps)
- [Related Documentation](#related-documentation)

## Service Provider Creation

### Generate the Panel Provider

```bash
# Create the dedicated panel provider
php artisan make:filament-panel chinook-admin
```

This creates `app/Providers/Filament/ChinookAdminPanelProvider.php`.

### Complete Panel Provider Implementation

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ChinookAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('chinook-admin')
            ->path('chinook-admin')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->brandName('Chinook Music Admin')
            ->brandLogo(asset('images/chinook-logo.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/favicon.ico'))
            ->discoverResources(
                in: app_path('Filament/ChinookAdmin/Resources'),
                for: 'App\\Filament\\ChinookAdmin\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/ChinookAdmin/Pages'),
                for: 'App\\Filament\\ChinookAdmin\\Pages'
            )
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(
                in: app_path('Filament/ChinookAdmin/Widgets'),
                for: 'App\\Filament\\ChinookAdmin\\Widgets'
            )
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->discoverClusters(
                in: app_path('Filament/ChinookAdmin/Clusters'),
                for: 'App\\Filament\\ChinookAdmin\\Clusters'
            )
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
            ->databaseTransactions()
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->sidebarWidth('20rem')
            ->collapsedSidebarWidth('4rem')
            ->spa()
            ->strictAuthorization()
            ->subNavigationPosition(SubNavigationPosition::Top)
            ->topNavigation(false)
            ->unsavedChangesAlerts()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->navigationGroups([
                'Music Management',
                'Customer Management',
                'Administration',
                'Analytics & Reports',
                'System',
            ])
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
                \Filament\SpatieLaravelMediaLibraryPlugin\SpatieLaravelMediaLibraryPlugin::make(),
                \Filament\SpatieLaravelTagsPlugin\SpatieLaravelTagsPlugin::make(),
                \Filament\SpatieLaravelActivitylogPlugin\SpatieLaravelActivitylogPlugin::make(),
            ]);
    }
}
```

## Panel Configuration Details

### Core Panel Settings

```php
// Panel identification and routing
->id('chinook-admin')                    // Unique panel identifier
->path('chinook-admin')                  // URL path prefix
->login()                                // Enable login page
->registration()                         // Enable user registration
->passwordReset()                        // Enable password reset
->emailVerification()                    // Enable email verification
->profile()                              // Enable user profile management
```

### Branding Configuration

```php
// Visual branding
->brandName('Chinook Music Admin')       // Panel title
->brandLogo(asset('images/chinook-logo.svg'))  // Logo image
->brandLogoHeight('2rem')                // Logo height
->favicon(asset('images/favicon.ico'))   // Browser favicon
```

### Color Scheme

```php
// Modern color palette with accessibility compliance
->colors([
    'primary' => Color::Blue,            // Primary actions and highlights
    'gray' => Color::Slate,              // Neutral elements
    'success' => Color::Green,           // Success states
    'warning' => Color::Amber,           // Warning states  
    'danger' => Color::Red,              // Error states
    'info' => Color::Sky,                // Informational elements
])
```

### Layout Configuration

```php
// Responsive layout settings
->maxContentWidth(MaxWidth::Full)        // Full width content area
->sidebarCollapsibleOnDesktop()          // Collapsible sidebar
->sidebarFullyCollapsibleOnDesktop()     // Fully collapsible option
->sidebarWidth('20rem')                  // Expanded sidebar width
->collapsedSidebarWidth('4rem')          // Collapsed sidebar width
->subNavigationPosition(SubNavigationPosition::Top)  // Sub-nav position
->topNavigation(false)                   // Disable top navigation
```

### Performance Features

```php
// Performance optimizations
->spa()                                  // Single Page Application mode
->databaseTransactions()                 // Automatic DB transactions
->unsavedChangesAlerts()                 // Warn about unsaved changes
->strictAuthorization()                  // Enforce authorization checks
```

### Global Search Configuration

```php
// Enhanced search functionality
->globalSearchKeyBindings(['command+k', 'ctrl+k'])  // Keyboard shortcuts
->globalSearchFieldKeyBindingSuffix()               // Show shortcut hint
```

## Directory Structure

The panel uses a dedicated directory structure:

```
app/
├── Filament/
│   └── ChinookAdmin/
│       ├── Resources/
│       │   ├── ArtistResource.php
│       │   ├── AlbumResource.php
│       │   ├── TrackResource.php
│       │   ├── CategoryResource.php
│       │   ├── CustomerResource.php
│       │   ├── EmployeeResource.php
│       │   ├── InvoiceResource.php
│       │   ├── PlaylistResource.php
│       │   └── MediaTypeResource.php
│       ├── Pages/
│       │   ├── Dashboard.php
│       │   ├── SalesAnalytics.php
│       │   ├── EmployeeHierarchy.php
│       │   └── MusicDiscovery.php
│       ├── Widgets/
│       │   ├── SalesOverview.php
│       │   ├── TopTracks.php
│       │   ├── CustomerGrowth.php
│       │   └── RevenueChart.php
│       └── Clusters/
│           ├── MusicManagement.php
│           └── CustomerManagement.php
```

## Service Provider Registration

### Register in Application

Add to `config/app.php`:

```php
'providers' => [
    // Other providers...
    App\Providers\Filament\ChinookAdminPanelProvider::class,
],
```

### Bootstrap Configuration

The provider is automatically discovered by Laravel 12, but you can manually register it in `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\ChinookAdminPanelProvider::class,
];
```

## Navigation Groups

Organize resources into logical groups:

```php
->navigationGroups([
    'Music Management',      // Artists, Albums, Tracks, Categories, Playlists
    'Customer Management',   // Customers, Invoices, Invoice Lines
    'Administration',        // Employees, Users, Roles & Permissions
    'Analytics & Reports',   // Dashboards, Analytics, Reports
    'System',               // Settings, Logs, Media Library, Import/Export
])
```

## Plugin Integration

Essential plugins for the Chinook admin panel:

```php
->plugins([
    // RBAC and security
    \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
    
    // Media management
    \Filament\SpatieLaravelMediaLibraryPlugin\SpatieLaravelMediaLibraryPlugin::make(),
    
    // Tagging system
    \Filament\SpatieLaravelTagsPlugin\SpatieLaravelTagsPlugin::make(),
    
    // Activity logging
    \Filament\SpatieLaravelActivitylogPlugin\SpatieLaravelActivitylogPlugin::make(),
])
```

## Environment Configuration

### Development Settings

```php
// In development environment
if (app()->environment('local')) {
    $panel->registration()           // Allow registration
          ->emailVerification(false) // Skip email verification
          ->viteTheme('resources/css/filament/chinook-admin/theme.css');
}
```

### Production Settings

```php
// In production environment
if (app()->environment('production')) {
    $panel->registration(false)      // Disable registration
          ->emailVerification()      // Require email verification
          ->spa(false);              // Disable SPA for better SEO
}
```

## Next Steps

1. **Configure Authentication** - Set up user authentication and session management
2. **Implement RBAC** - Configure role-based access control with spatie/laravel-permission
3. **Setup Navigation** - Configure menu structure and access control
4. **Apply Security** - Implement security middleware and access patterns
5. **Create Resources** - Build Filament resources for all Chinook entities

## Related Documentation

- **[Authentication Setup](020-authentication-setup.md)** - User authentication configuration
- **[RBAC Integration](030-rbac-integration.md)** - Role-based access control
- **[Navigation Configuration](040-navigation-configuration.md)** - Menu and navigation setup
- **[Security Configuration](050-security-configuration.md)** - Security and middleware setup
