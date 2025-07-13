# 1. Shuvroroy Filament Spatie Laravel Health Integration Guide

> **Package Source:** [shuvroroy/filament-spatie-laravel-health](https://github.com/shuvroroy/filament-spatie-laravel-health)  
> **Official Documentation:** [Filament Health Plugin Documentation](https://github.com/shuvroroy/filament-spatie-laravel-health/blob/main/README.md)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook database schema and monitoring requirements  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Plugin Registration](#131-plugin-registration)
  - [1.3.2. Health Check Configuration](#132-health-check-configuration)
- [1.4. Chinook Health Checks](#14-chinook-health-checks)
  - [1.4.1. Database Health Monitoring](#141-database-health-monitoring)
  - [1.4.2. Media Storage Health](#142-media-storage-health)
  - [1.4.3. Custom Chinook Checks](#143-custom-chinook-checks)
- [1.5. Monitoring Dashboard](#15-monitoring-dashboard)
- [1.6. Alerting & Notifications](#16-alerting--notifications)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Filament Health Plugin documentation](https://github.com/shuvroroy/filament-spatie-laravel-health/blob/main/README.md) for Laravel 12 and Chinook project requirements, integrating with the existing [spatie/laravel-health](320-spatie-laravel-health-guide.md) foundation.

**Shuvroroy Filament Spatie Laravel Health** provides a comprehensive Filament admin interface for monitoring application health checks. It offers real-time health status visualization, detailed check results, and alerting capabilities.

### 1.2.1. Key Features

- **Real-time Health Dashboard**: Live monitoring of all health checks
- **Visual Status Indicators**: Color-coded health status with detailed information
- **Historical Health Data**: Track health trends over time
- **Alert Management**: Configurable notifications for health check failures
- **Custom Check Support**: Easy integration of custom health checks
- **Performance Metrics**: Monitor application performance indicators

### 1.2.2. Integration with Spatie Health

> **Foundation Package:** Built on [spatie/laravel-health](320-spatie-laravel-health-guide.md) with enhanced UI

**Package Relationship:**
- **Spatie Laravel Health**: Provides core health checking functionality
- **Shuvroroy Health Plugin**: Provides Filament admin interface and visualization
- **Combined Workflow**: Automated health monitoring with comprehensive dashboard

## 1.3. Installation & Configuration

### 1.3.1. Plugin Registration

> **Configuration Source:** Based on [official installation guide](https://github.com/shuvroroy/filament-spatie-laravel-health#installation)  
> **Chinook Enhancement:** Already configured in AdminPanelProvider

The plugin is already registered in the admin panel. Verify configuration:

<augment_code_snippet path="app/Providers/Filament/AdminPanelProvider.php" mode="EXCERPT">
````php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelHealth\FilamentSpatieLaravelHealthPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ... existing configuration ...
            
            // Filament Spatie Laravel Health Plugin
            ->plugin(
                FilamentSpatieLaravelHealthPlugin::make()
                    ->navigationGroup('System Monitoring')
                    ->navigationSort(10)
                    ->navigationIcon('heroicon-o-heart')
                    ->navigationLabel('Health Checks')
                    ->usingPage(\ShuvroRoy\FilamentSpatieLaravelHealth\Pages\HealthCheckResults::class)
            );
    }
}
````
</augment_code_snippet>

### 1.3.2. Health Check Configuration

> **Configuration Source:** Enhanced from [spatie/laravel-health configuration](320-spatie-laravel-health-guide.md)  
> **Chinook Modifications:** Optimized for Chinook-specific monitoring requirements

<augment_code_snippet path="config/health.php" mode="EXCERPT">
````php
<?php
// Configuration enhanced from: https://github.com/spatie/laravel-health/blob/main/config/health.php
// Chinook modifications: Enhanced for Chinook entity monitoring and SQLite optimization
// Laravel 12 updates: Modern syntax and framework patterns

use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DatabaseConnectionCountCheck;
use Spatie\Health\Checks\Checks\DatabaseSizeCheck;
use Spatie\Health\Checks\Checks\DatabaseTableSizeCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

return [
    /*
     * Health check configuration
     */
    'oh_dear_endpoint' => [
        'enabled' => env('OH_DEAR_HEALTH_CHECK_ENABLED', false),
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),
        'url' => '/oh-dear-health-check-results',
    ],

    /*
     * Health checks to run
     */
    'checks' => [
        // Core application checks
        OptimizedAppCheck::new(),
        DebugModeCheck::new(),
        EnvironmentCheck::new()->expectEnvironment('production'),

        // Database checks optimized for SQLite
        DatabaseCheck::new(),
        DatabaseConnectionCountCheck::new()
            ->warnWhenMoreConnectionsThan(5)
            ->failWhenMoreConnectionsThan(10),
        DatabaseSizeCheck::new()
            ->warnWhenMoreMegabytesThan(500) // 500MB warning for SQLite
            ->failWhenMoreMegabytesThan(1000), // 1GB failure threshold

        // Chinook-specific table size monitoring
        DatabaseTableSizeCheck::new()
            ->table('chinook_artists', maxSizeInMb: 10)
            ->table('chinook_albums', maxSizeInMb: 50)
            ->table('chinook_tracks', maxSizeInMb: 100)
            ->table('activity_log', maxSizeInMb: 200),

        // Cache and queue monitoring
        CacheCheck::new(),
        QueueCheck::new()->onQueue('default'),
        
        // Background job monitoring
        HorizonCheck::new(),
        ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2),

        // Storage monitoring
        UsedDiskSpaceCheck::new()
            ->warnWhenUsedSpaceIsAbovePercentage(70)
            ->failWhenUsedSpaceIsAbovePercentage(90)
            ->checkFilesystem('local'),

        // Media storage monitoring
        UsedDiskSpaceCheck::new()
            ->warnWhenUsedSpaceIsAbovePercentage(80)
            ->failWhenUsedSpaceIsAbovePercentage(95)
            ->checkFilesystem('chinook_media')
            ->name('Media Storage'),
    ],

    /*
     * Notification configuration
     */
    'notifications' => [
        /*
         * Notifications will only get sent if this option is set to `true`.
         */
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', true),

        /*
         * Notification channels to use
         */
        'notifications' => [
            \Spatie\Health\Notifications\CheckFailedNotification::class => [
                'mail', 'slack'
            ],
        ],

        /*
         * Notifiable configuration
         */
        'notifiable' => \Spatie\Health\Notifications\Notifiable::class,

        /*
         * Mail notification settings
         */
        'mail' => [
            'to' => env('HEALTH_MAIL_TO', 'admin@chinook.local'),
            'subject' => 'Chinook Health Check Failed',
        ],

        /*
         * Slack notification settings
         */
        'slack' => [
            'webhook_url' => env('HEALTH_SLACK_WEBHOOK_URL'),
            'channel' => env('HEALTH_SLACK_CHANNEL', '#alerts'),
            'username' => 'Chinook Health Monitor',
            'icon' => ':warning:',
        ],
    ],

    /*
     * Chinook-specific health configuration
     */
    'chinook' => [
        /*
         * Enable Chinook-specific health checks
         */
        'enable_custom_checks' => true,

        /*
         * Media library health thresholds
         */
        'media_thresholds' => [
            'max_file_size_mb' => 50,
            'max_collection_size_mb' => 500,
            'orphaned_files_threshold' => 10,
        ],

        /*
         * Performance monitoring thresholds
         */
        'performance_thresholds' => [
            'max_query_time_ms' => 1000,
            'max_memory_usage_mb' => 256,
            'max_response_time_ms' => 2000,
        ],

        /*
         * Business logic health checks
         */
        'business_checks' => [
            'check_artist_album_consistency' => true,
            'check_track_duration_validity' => true,
            'check_invoice_totals_accuracy' => true,
        ],
    ],
];
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Activity Log Guide](270-rmsramos-activitylog-guide.md) | **Next:** [Filament Backup Guide](280-shuvroroy-filament-spatie-laravel-backup-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
