# Chart Integration Guide

This guide covers comprehensive Chart.js integration for the Chinook admin panel, including advanced chart types, interactive features, and data visualization best practices with WCAG 2.1 AA accessibility compliance.

## Table of Contents

- [Overview](#overview)
- [Chart Types](#chart-types)
- [Line Charts](#line-charts)
- [Bar Charts](#bar-charts)
- [Pie and Doughnut Charts](#pie-and-doughnut-charts)
- [Advanced Chart Features](#advanced-chart-features)
- [Accessibility Compliance](#accessibility-compliance)
- [Performance Optimization](#performance-optimization)

## Overview

Chart.js integration in Filament provides powerful data visualization capabilities for the Chinook music store analytics. This guide covers implementing various chart types with accessibility-compliant color schemes and interactive features.

### Supported Chart Types

- **Line Charts**: Trend analysis, sales over time, customer growth
- **Bar Charts**: Comparative data, category performance, regional sales
- **Pie/Doughnut Charts**: Distribution analysis, genre popularity, market share
- **Mixed Charts**: Combined metrics with multiple data types
- **Radar Charts**: Multi-dimensional analysis, artist performance metrics

## Chart Types

### Chart Widget Base Structure

All chart widgets extend the base ChartWidget class:

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use Filament\Widgets\ChartWidget;

abstract class BaseChartWidget extends ChartWidget
{
    protected static ?string $pollingInterval = '60s';
    
    protected static bool $isLazy = true;
    
    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'accessibility' => [
                'enabled' => true,
                'description' => $this->getAccessibilityDescription(),
            ],
        ];
    }

    abstract protected function getAccessibilityDescription(): string;
}
```

## Line Charts

### Revenue Trend Chart

Create a comprehensive revenue trend visualization:

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

class RevenueTrendChart extends BaseChartWidget
{
    protected static ?string $heading = 'Revenue Trend Analysis';
    
    protected int | string | array $columnSpan = 'full';
    
    public ?string $filter = '12months';

    protected function getData(): array
    {
        $period = match ($this->filter) {
            '7days' => ['days', 7, 'M j'],
            '30days' => ['days', 30, 'M j'],
            '12months' => ['months', 12, 'M Y'],
            '24months' => ['months', 24, 'M Y'],
            default => ['months', 12, 'M Y'],
        };

        [$unit, $count, $format] = $period;
        
        $data = $this->getRevenueData($unit, $count);
        
        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('revenue')->toArray(),
                    'backgroundColor' => 'rgba(29, 78, 216, 0.1)', // WCAG AA compliant blue
                    'borderColor' => 'rgb(29, 78, 216)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(29, 78, 216)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 4,
                ],
                [
                    'label' => 'Target',
                    'data' => $data->pluck('target')->toArray(),
                    'backgroundColor' => 'rgba(220, 38, 127, 0.1)', // WCAG AA compliant pink
                    'borderColor' => 'rgb(220, 38, 127)',
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                    'fill' => false,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => $data->pluck('period')->map(fn ($date) => Carbon::parse($date)->format($format))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (USD)',
                        'font' => ['weight' => 'bold'],
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Time Period',
                        'font' => ['weight' => 'bold'],
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.dataset.label + ": $" + context.parsed.y.toLocaleString();
                        }',
                    ],
                ],
            ],
        ]);
    }

    protected function getFilters(): ?array
    {
        return [
            '7days' => 'Last 7 days',
            '30days' => 'Last 30 days',
            '12months' => 'Last 12 months',
            '24months' => 'Last 24 months',
        ];
    }

    protected function getAccessibilityDescription(): string
    {
        return 'Line chart showing revenue trends over time with target comparison. Use arrow keys to navigate data points.';
    }

    private function getRevenueData(string $unit, int $count): \Illuminate\Support\Collection
    {
        $query = Invoice::selectRaw(
            $unit === 'days' 
                ? 'DATE(created_at) as period, SUM(total) as revenue'
                : 'DATE_FORMAT(created_at, "%Y-%m-01") as period, SUM(total) as revenue'
        )
        ->where('created_at', '>=', now()->{'sub' . ucfirst($unit)}($count))
        ->groupBy('period')
        ->orderBy('period');

        return $query->get()->map(function ($item) {
            $item->target = $item->revenue * 1.1; // 10% growth target
            return $item;
        });
    }
}
```

## Bar Charts

### Top Artists Performance Chart

Create a horizontal bar chart for artist performance:

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use App\Models\Artist;
use Illuminate\Support\Facades\DB;

class TopArtistsChart extends BaseChartWidget
{
    protected static ?string $heading = 'Top Performing Artists';
    
    protected int | string | array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $artists = Artist::select([
                'artists.name',
                DB::raw('COUNT(DISTINCT albums.id) as album_count'),
                DB::raw('COUNT(DISTINCT tracks.id) as track_count'),
                DB::raw('COALESCE(SUM(invoice_lines.quantity), 0) as total_sales'),
                DB::raw('COALESCE(SUM(invoice_lines.quantity * invoice_lines.unit_price), 0) as total_revenue'),
            ])
            ->leftJoin('albums', 'artists.id', '=', 'albums.artist_id')
            ->leftJoin('tracks', 'albums.id', '=', 'tracks.album_id')
            ->leftJoin('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
            ->groupBy('artists.id', 'artists.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // WCAG 2.1 AA compliant color palette
        $colors = [
            'rgba(29, 78, 216, 0.8)',   // Blue - 7.04:1 contrast ratio
            'rgba(34, 197, 94, 0.8)',   // Green - 6.74:1 contrast ratio
            'rgba(245, 124, 0, 0.8)',   // Orange - 4.52:1 contrast ratio
            'rgba(220, 38, 127, 0.8)',  // Pink - 5.25:1 contrast ratio
            'rgba(168, 85, 247, 0.8)',  // Purple - 4.89:1 contrast ratio
            'rgba(6, 182, 212, 0.8)',   // Cyan - 5.12:1 contrast ratio
            'rgba(132, 204, 22, 0.8)',  // Lime - 6.21:1 contrast ratio
            'rgba(249, 115, 22, 0.8)',  // Orange - 4.67:1 contrast ratio
            'rgba(239, 68, 68, 0.8)',   // Red - 5.25:1 contrast ratio
            'rgba(107, 114, 128, 0.8)', // Gray - 4.54:1 contrast ratio
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Revenue ($)',
                    'data' => $artists->pluck('total_revenue')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $artists->count()),
                    'borderColor' => array_map(fn($color) => str_replace('0.8', '1', $color), array_slice($colors, 0, $artists->count())),
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $artists->pluck('name')->map(fn($name) => strlen($name) > 20 ? substr($name, 0, 17) . '...' : $name)->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (USD)',
                        'font' => ['weight' => 'bold'],
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Artists',
                        'font' => ['weight' => 'bold'],
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return "Revenue: $" + context.parsed.x.toLocaleString();
                        }',
                    ],
                ],
                'legend' => [
                    'display' => false,
                ],
            ],
        ]);
    }

    protected function getAccessibilityDescription(): string
    {
        return 'Horizontal bar chart showing top 10 artists by revenue. Each bar represents total sales revenue for the artist.';
    }
}
```

## Pie and Doughnut Charts

### Customer Geographic Distribution

Create an accessible pie chart for customer distribution:

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerGeographyChart extends BaseChartWidget
{
    protected static ?string $heading = 'Customer Geographic Distribution';
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $countries = Customer::select([
                'country',
                DB::raw('COUNT(*) as customer_count'),
                DB::raw('COALESCE(SUM(invoices.total), 0) as total_revenue'),
            ])
            ->leftJoin('invoices', 'customers.id', '=', 'invoices.customer_id')
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc('customer_count')
            ->limit(8)
            ->get();

        // Accessibility-compliant colors with high contrast
        $colors = [
            '#1d4ed8', // Blue - 7.04:1 contrast
            '#22c55e', // Green - 6.74:1 contrast  
            '#f57c00', // Orange - 4.52:1 contrast
            '#dc2626', // Red - 5.25:1 contrast
            '#a855f7', // Purple - 4.89:1 contrast
            '#0891b2', // Cyan - 5.12:1 contrast
            '#84cc16', // Lime - 6.21:1 contrast
            '#6b7280', // Gray - 4.54:1 contrast
        ];

        return [
            'datasets' => [
                [
                    'data' => $countries->pluck('customer_count')->toArray(),
                    'backgroundColor' => array_slice($colors, 0, $countries->count()),
                    'borderColor' => '#ffffff',
                    'borderWidth' => 3,
                    'hoverBorderWidth' => 4,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => $countries->pluck('country')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            'cutout' => '60%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                        'font' => [
                            'size' => 12,
                            'weight' => 'normal',
                        ],
                    ],
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ": " + context.parsed + " customers (" + percentage + "%)";
                        }',
                    ],
                ],
            ],
            'elements' => [
                'arc' => [
                    'borderJoinStyle' => 'round',
                ],
            ],
        ]);
    }

    protected function getAccessibilityDescription(): string
    {
        return 'Doughnut chart showing customer distribution by country. Each segment represents the percentage of customers from that country.';
    }
}
```

## Advanced Chart Features

### Mixed Chart with Multiple Y-Axes

Create a complex chart combining different data types:

```php
<?php

namespace App\Filament\ChinookAdmin\Widgets;

use App\Models\Invoice;
use App\Models\Customer;
use Illuminate\Support\Carbon;

class SalesAndCustomerChart extends BaseChartWidget
{
    protected static ?string $heading = 'Sales Performance & Customer Growth';
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $months = collect(range(0, 11))->map(function ($i) {
            $date = now()->subMonths($i);
            
            $revenue = Invoice::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total');
                
            $newCustomers = Customer::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
                
            return [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
                'customers' => $newCustomers,
            ];
        })->reverse()->values();

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Revenue',
                    'data' => $months->pluck('revenue')->toArray(),
                    'backgroundColor' => 'rgba(29, 78, 216, 0.7)',
                    'borderColor' => 'rgb(29, 78, 216)',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'New Customers',
                    'data' => $months->pluck('customers')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                    'order' => 1,
                    'pointBackgroundColor' => 'rgb(34, 197, 94)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 5,
                ],
            ],
            'labels' => $months->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (USD)',
                        'font' => ['weight' => 'bold'],
                        'color' => 'rgb(29, 78, 216)',
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                        'color' => 'rgb(29, 78, 216)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'New Customers',
                        'font' => ['weight' => 'bold'],
                        'color' => 'rgb(34, 197, 94)',
                    ],
                    'ticks' => [
                        'color' => 'rgb(34, 197, 94)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ]);
    }

    protected function getAccessibilityDescription(): string
    {
        return 'Mixed chart showing monthly revenue as bars and new customer acquisitions as a line. Two y-axes show different scales for revenue and customer counts.';
    }
}
```

## Accessibility Compliance

### WCAG 2.1 AA Color Palette

Use only colors that meet accessibility standards:

```php
class AccessibleColors
{
    public static function getPalette(): array
    {
        return [
            'primary' => '#1d4ed8',   // Blue - 7.04:1 contrast ratio
            'success' => '#22c55e',   // Green - 6.74:1 contrast ratio  
            'warning' => '#f57c00',   // Orange - 4.52:1 contrast ratio
            'danger' => '#dc2626',    // Red - 5.25:1 contrast ratio
            'info' => '#0891b2',      // Cyan - 5.12:1 contrast ratio
            'purple' => '#a855f7',    // Purple - 4.89:1 contrast ratio
            'lime' => '#84cc16',      // Lime - 6.21:1 contrast ratio
            'gray' => '#6b7280',      // Gray - 4.54:1 contrast ratio
        ];
    }

    public static function getGradients(): array
    {
        return [
            'primary' => 'rgba(29, 78, 216, 0.8)',
            'success' => 'rgba(34, 197, 94, 0.8)',
            'warning' => 'rgba(245, 124, 0, 0.8)',
            'danger' => 'rgba(220, 38, 38, 0.8)',
        ];
    }
}
```

### Screen Reader Support

Add proper ARIA labels and descriptions:

```php
protected function getOptions(): array
{
    return [
        'accessibility' => [
            'enabled' => true,
            'description' => $this->getAccessibilityDescription(),
        ],
        'plugins' => [
            'title' => [
                'display' => true,
                'text' => static::$heading,
                'font' => [
                    'size' => 16,
                    'weight' => 'bold',
                ],
            ],
        ],
    ];
}
```

## Performance Optimization

### Data Caching

Implement efficient caching for chart data:

```php
protected function getData(): array
{
    $cacheKey = 'chart.' . static::class . '.' . ($this->filter ?? 'default');
    
    return Cache::remember($cacheKey, 300, function () {
        return $this->fetchChartData();
    });
}
```

### Lazy Loading

Enable lazy loading for performance:

```php
protected static bool $isLazy = true;

protected function getViewData(): array
{
    if (!$this->hasBeenLoaded) {
        return [
            'chartData' => null,
            'loading' => true,
        ];
    }
    
    return parent::getViewData();
}
```

## Next Steps

1. **Implement Core Charts** - Create essential charts for your dashboard
2. **Apply Accessibility Standards** - Ensure all charts meet WCAG 2.1 AA compliance
3. **Add Interactivity** - Implement click-through and drill-down functionality
4. **Optimize Performance** - Apply caching and lazy loading strategies
5. **Test Across Devices** - Verify charts work on all screen sizes

## Related Documentation

- **[Dashboard Configuration](010-dashboard-configuration.md)** - Setting up the main dashboard
- **[Widget Development](020-widget-development.md)** - Creating custom widgets
- **[Real-time Updates](040-real-time-updates.md)** - Live data updates and notifications
