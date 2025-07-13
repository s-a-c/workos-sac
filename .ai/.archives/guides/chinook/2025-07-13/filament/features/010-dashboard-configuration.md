# Dashboard Configuration Guide

This guide covers the complete setup and configuration of the main dashboard for the Chinook admin panel, including layout customization, widget organization, and performance optimization.

## Table of Contents

- [Overview](#overview)
- [Dashboard Page Setup](#dashboard-page-setup)
- [Widget Organization](#widget-organization)
- [Layout Configuration](#layout-configuration)
- [Performance Optimization](#performance-optimization)
- [Customization Options](#customization-options)
- [Access Control](#access-control)

## Overview

The Chinook admin panel dashboard provides a comprehensive overview of the music store's key metrics, including sales performance, popular tracks, customer analytics, and system health indicators.

### Dashboard Features

- **Real-time Analytics**: Live data updates for key performance indicators
- **Interactive Widgets**: Clickable charts and metrics with drill-down capabilities
- **Responsive Layout**: Optimized for desktop, tablet, and mobile viewing
- **Role-based Content**: Different widgets based on user permissions
- **Performance Monitoring**: System health and performance metrics

## Dashboard Page Setup

### Custom Dashboard Page

Create a custom dashboard page to replace the default Filament dashboard:

```php
<?php

namespace App\Filament\ChinookAdmin\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.chinook-admin.pages.dashboard';

    public function getColumns(): int | string | array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            // Account and system widgets
            AccountWidget::class,
            
            // Revenue and sales widgets
            \App\Filament\ChinookAdmin\Widgets\RevenueOverview::class,
            \App\Filament\ChinookAdmin\Widgets\SalesChart::class,
            \App\Filament\ChinookAdmin\Widgets\TopTracks::class,
            
            // Customer and music analytics
            \App\Filament\ChinookAdmin\Widgets\CustomerGrowth::class,
            \App\Filament\ChinookAdmin\Widgets\PopularArtists::class,
            \App\Filament\ChinookAdmin\Widgets\GenreDistribution::class,
            
            // System monitoring
            \App\Filament\ChinookAdmin\Widgets\SystemHealth::class,
            \App\Filament\ChinookAdmin\Widgets\RecentActivity::class,
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            \App\Filament\ChinookAdmin\Widgets\QuickStats::class,
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            FilamentInfoWidget::class,
        ];
    }
}
```

### Dashboard Blade Template

Create a custom dashboard view for enhanced layout control:

```blade
{{-- resources/views/filament/chinook-admin/pages/dashboard.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Welcome to Chinook Music Admin
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Manage your music store with comprehensive analytics and tools
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <x-filament::button
                        icon="heroicon-o-arrow-path"
                        wire:click="$refresh"
                        size="sm"
                    >
                        Refresh Data
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-filament::button
                tag="a"
                href="{{ route('filament.chinook-admin.resources.artists.create') }}"
                icon="heroicon-o-plus"
                color="primary"
                class="h-20 flex flex-col items-center justify-center"
            >
                <span class="text-sm font-medium">Add Artist</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('filament.chinook-admin.resources.albums.create') }}"
                icon="heroicon-o-plus"
                color="success"
                class="h-20 flex flex-col items-center justify-center"
            >
                <span class="text-sm font-medium">Add Album</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('filament.chinook-admin.resources.tracks.create') }}"
                icon="heroicon-o-plus"
                color="warning"
                class="h-20 flex flex-col items-center justify-center"
            >
                <span class="text-sm font-medium">Add Track</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ route('filament.chinook-admin.resources.customers.index') }}"
                icon="heroicon-o-users"
                color="info"
                class="h-20 flex flex-col items-center justify-center"
            >
                <span class="text-sm font-medium">View Customers</span>
            </x-filament::button>
        </div>

        {{-- Main Dashboard Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Primary Content Area --}}
            <div class="lg:col-span-2 space-y-6">
                @foreach ($this->getHeaderWidgets() as $widget)
                    @livewire($widget)
                @endforeach

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach (array_slice($this->getWidgets(), 0, 4) as $widget)
                        @livewire($widget)
                    @endforeach
                </div>
            </div>

            {{-- Sidebar Content --}}
            <div class="space-y-6">
                @foreach (array_slice($this->getWidgets(), 4) as $widget)
                    @livewire($widget)
                @endforeach
            </div>
        </div>

        {{-- Footer Widgets --}}
        <div class="grid grid-cols-1 gap-6">
            @foreach ($this->getFooterWidgets() as $widget)
                @livewire($widget)
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
```

## Widget Organization

### Widget Categories

Organize widgets into logical categories for better user experience:

#### Performance Metrics
- **Revenue Overview**: Total revenue, growth trends, and targets
- **Sales Chart**: Daily, weekly, and monthly sales visualization
- **Top Performing Items**: Best-selling tracks, albums, and artists

#### Customer Analytics
- **Customer Growth**: New customer acquisition and retention metrics
- **Geographic Distribution**: Customer locations and regional performance
- **Customer Lifetime Value**: Revenue per customer and engagement metrics

#### Music Analytics
- **Popular Artists**: Most played and purchased artists
- **Genre Distribution**: Music category performance and trends
- **Track Performance**: Play counts, purchase rates, and ratings

#### System Monitoring
- **System Health**: Database performance, response times, and uptime
- **Recent Activity**: Latest user actions and system events
- **Storage Usage**: File storage and database size monitoring

### Widget Grid Layout

Configure responsive grid layouts for optimal viewing:

```php
public function getColumns(): int | string | array
{
    return [
        'default' => 1,
        'sm' => 1,
        'md' => 2,
        'lg' => 3,
        'xl' => 4,
        '2xl' => 4,
    ];
}
```

## Layout Configuration

### Responsive Design

Implement responsive layouts that work across all device sizes:

```php
// Widget column spans
protected int | string | array $columnSpan = [
    'default' => 1,
    'sm' => 1,
    'md' => 2,
    'lg' => 1,
    'xl' => 1,
];

// Widget height configuration
protected string $height = '300px';

// Widget refresh interval
protected ?string $pollingInterval = '30s';
```

### Custom CSS Styling

Add custom styling for enhanced visual appeal:

```css
/* resources/css/filament/chinook-admin/dashboard.css */
.dashboard-widget {
    @apply bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700;
}

.dashboard-metric {
    @apply text-3xl font-bold text-gray-900 dark:text-white;
}

.dashboard-metric-label {
    @apply text-sm font-medium text-gray-600 dark:text-gray-400;
}

.dashboard-chart {
    @apply h-64 w-full;
}

.quick-action-button {
    @apply transition-all duration-200 hover:scale-105 hover:shadow-md;
}
```

## Performance Optimization

### Caching Strategy

Implement caching for expensive dashboard queries:

```php
use Illuminate\Support\Facades\Cache;

class RevenueOverview extends Widget
{
    protected function getData(): array
    {
        return Cache::remember('dashboard.revenue.overview', 300, function () {
            return [
                'total_revenue' => Invoice::sum('total'),
                'monthly_revenue' => Invoice::whereMonth('created_at', now()->month)->sum('total'),
                'growth_rate' => $this->calculateGrowthRate(),
            ];
        });
    }

    private function calculateGrowthRate(): float
    {
        $currentMonth = Invoice::whereMonth('created_at', now()->month)->sum('total');
        $previousMonth = Invoice::whereMonth('created_at', now()->subMonth()->month)->sum('total');
        
        return $previousMonth > 0 ? (($currentMonth - $previousMonth) / $previousMonth) * 100 : 0;
    }
}
```

### Lazy Loading

Implement lazy loading for non-critical widgets:

```php
protected bool $isLazy = true;

protected function getViewData(): array
{
    if ($this->isLazy && !$this->hasBeenLoaded) {
        return ['loading' => true];
    }

    return $this->getData();
}
```

### Database Query Optimization

Optimize database queries for dashboard performance:

```php
// Use efficient queries with proper indexing
$topTracks = Track::select('tracks.*')
    ->join('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
    ->selectRaw('SUM(invoice_lines.quantity) as total_sold')
    ->groupBy('tracks.id')
    ->orderByDesc('total_sold')
    ->limit(10)
    ->with(['album.artist', 'categories'])
    ->get();
```

## Customization Options

### Theme Configuration

Allow users to customize dashboard appearance:

```php
public function getTheme(): string
{
    return auth()->user()->dashboard_theme ?? 'default';
}

public function getWidgetOrder(): array
{
    return auth()->user()->widget_preferences ?? $this->getDefaultWidgetOrder();
}
```

### Widget Personalization

Enable users to show/hide specific widgets:

```php
public function getVisibleWidgets(): array
{
    $userPreferences = auth()->user()->widget_visibility ?? [];
    
    return array_filter($this->getWidgets(), function ($widget) use ($userPreferences) {
        $widgetName = class_basename($widget);
        return $userPreferences[$widgetName] ?? true;
    });
}
```

## Access Control

### Role-based Widget Display

Show different widgets based on user roles:

```php
public function getWidgets(): array
{
    $widgets = [];
    
    if (auth()->user()->can('view-analytics')) {
        $widgets[] = RevenueOverview::class;
        $widgets[] = SalesChart::class;
    }
    
    if (auth()->user()->can('view-customers')) {
        $widgets[] = CustomerGrowth::class;
    }
    
    if (auth()->user()->can('view-system-health')) {
        $widgets[] = SystemHealth::class;
    }
    
    return $widgets;
}
```

### Permission-based Content

Filter widget content based on user permissions:

```php
protected function getData(): array
{
    $data = [];
    
    if (auth()->user()->can('view-revenue')) {
        $data['revenue'] = $this->getRevenueData();
    }
    
    if (auth()->user()->can('view-customer-data')) {
        $data['customers'] = $this->getCustomerData();
    }
    
    return $data;
}
```

## Next Steps

1. **Implement Core Widgets** - Create the essential dashboard widgets for revenue, sales, and customer metrics
2. **Setup Caching** - Implement caching strategies for improved performance
3. **Configure Permissions** - Set up role-based access control for dashboard content
4. **Customize Layout** - Adapt the layout to match your specific business requirements
5. **Add Interactivity** - Implement click-through functionality and drill-down capabilities

## Related Documentation

- **[Widget Development](020-widget-development.md)** - Creating custom widgets for the dashboard
- **[Chart Integration](030-chart-integration.md)** - Adding charts and data visualization
- **[Real-time Updates](040-real-time-updates.md)** - Implementing live data updates
- **[Performance Optimization](../deployment/050-performance-optimization.md)** - Additional performance tuning strategies
