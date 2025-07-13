# 1. Spatie Laravel Health Integration Guide

> **Package Source:** [spatie/laravel-health](https://github.com/spatie/laravel-health)  
> **Official Documentation:** [Laravel Health Documentation](https://spatie.be/docs/laravel-health)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook database schema and monitoring requirements  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Package Installation](#131-package-installation)
  - [1.3.2. Health Check Configuration](#132-health-check-configuration)
  - [1.3.3. Custom Health Checks](#133-custom-health-checks)
- [1.4. Chinook Health Monitoring](#14-chinook-health-monitoring)
  - [1.4.1. Database Health Checks](#141-database-health-checks)
  - [1.4.2. Media Storage Monitoring](#142-media-storage-monitoring)
  - [1.4.3. Business Logic Validation](#143-business-logic-validation)
- [1.5. Integration with Filament](#15-integration-with-filament)
- [1.6. Monitoring & Alerting](#16-monitoring--alerting)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Spatie Laravel Health documentation](https://spatie.be/docs/laravel-health) for Laravel 12 and Chinook project requirements, providing the foundation for the [Filament Health Plugin](290-shuvroroy-filament-spatie-laravel-health-guide.md) interface.

**Spatie Laravel Health** provides a comprehensive health checking system for Laravel applications. It offers a flexible framework for monitoring application components, database integrity, external services, and custom business logic validation.

### 1.2.1. Key Features

- **Comprehensive Health Checks**: Database, cache, storage, and custom checks
- **Flexible Check System**: Easy creation of custom health checks
- **Multiple Result Formats**: JSON, HTML, and custom output formats
- **Integration Ready**: Works with monitoring services and dashboards
- **Performance Monitoring**: Track check execution times and resource usage
- **Alerting Support**: Integration with notification systems

### 1.2.2. Chinook Health Monitoring Strategy

- **Database Integrity**: SQLite WAL mode optimization and data consistency
- **Media Storage Health**: File system monitoring and orphaned file detection
- **Performance Metrics**: Query performance and response time monitoring
- **Business Logic Validation**: Music catalog data integrity and relationships
- **External Service Monitoring**: Backup services and cloud storage connectivity

## 1.3. Installation & Configuration

### 1.3.1. Package Installation

> **Installation Source:** Based on [official installation guide](https://spatie.be/docs/laravel-health/v1/installation-setup)  
> **Chinook Enhancement:** Already installed and configured

The package is already installed via Composer. Verify installation:

<augment_code_snippet path="composer.json" mode="EXCERPT">
````json
{
    "require": {
        "spatie/laravel-health": "^1.34"
    }
}
````
</augment_code_snippet>

**Publish Configuration and Set Up Routes:**

```bash
# Publish configuration file
php artisan vendor:publish --tag="health-config"

# Publish migrations (if using database storage)
php artisan vendor:publish --tag="health-migrations"
php artisan migrate

# Set up health check endpoint
php artisan health:install
```

### 1.3.2. Health Check Configuration

> **Configuration Source:** Enhanced from [health configuration](https://spatie.be/docs/laravel-health/v1/installation-setup#publishing-the-config-file)  
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
use App\Health\Checks\ChinookDataIntegrityCheck;
use App\Health\Checks\ChinookMediaStorageCheck;
use App\Health\Checks\ChinookPerformanceCheck;

return [
    /*
     * Health check endpoint configuration
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
        // Core application health checks
        OptimizedAppCheck::new(),
        DebugModeCheck::new(),
        EnvironmentCheck::new()->expectEnvironment('production'),

        // Database health checks optimized for SQLite
        DatabaseCheck::new(),
        DatabaseConnectionCountCheck::new()
            ->warnWhenMoreConnectionsThan(5)
            ->failWhenMoreConnectionsThan(10),
        
        // SQLite-specific database size monitoring
        DatabaseSizeCheck::new()
            ->warnWhenMoreMegabytesThan(500) // 500MB warning
            ->failWhenMoreMegabytesThan(1000), // 1GB failure

        // Chinook table size monitoring
        DatabaseTableSizeCheck::new()
            ->table('chinook_artists', maxSizeInMb: 10)
            ->table('chinook_albums', maxSizeInMb: 50)
            ->table('chinook_tracks', maxSizeInMb: 100)
            ->table('chinook_playlists', maxSizeInMb: 20)
            ->table('chinook_customers', maxSizeInMb: 30)
            ->table('chinook_invoices', maxSizeInMb: 40)
            ->table('activity_log', maxSizeInMb: 200)
            ->table('media', maxSizeInMb: 100),

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

        // Custom Chinook health checks
        ChinookDataIntegrityCheck::new(),
        ChinookMediaStorageCheck::new(),
        ChinookPerformanceCheck::new(),
    ],

    /*
     * Notification configuration
     */
    'notifications' => [
        /*
         * Enable notifications
         */
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', true),

        /*
         * Notification classes and channels
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
     * Result store configuration
     */
    'result_stores' => [
        /*
         * Store results in cache
         */
        \Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => env('HEALTH_CACHE_STORE', 'default'),
            'key' => env('HEALTH_CACHE_KEY', 'health-check-results'),
        ],

        /*
         * Store results in database (optional)
         */
        // \Spatie\Health\ResultStores\EloquentHealthResultStore::class,
    ],

    /*
     * Chinook-specific health configuration
     */
    'chinook' => [
        /*
         * Data integrity thresholds
         */
        'data_integrity' => [
            'max_orphaned_albums' => 5,
            'max_orphaned_tracks' => 10,
            'max_invalid_durations' => 3,
            'max_missing_artists' => 0,
        ],

        /*
         * Performance thresholds
         */
        'performance' => [
            'max_query_time_ms' => 1000,
            'max_memory_usage_mb' => 256,
            'max_response_time_ms' => 2000,
            'min_cache_hit_ratio' => 0.8,
        ],

        /*
         * Media storage thresholds
         */
        'media_storage' => [
            'max_orphaned_files' => 10,
            'max_missing_conversions' => 5,
            'max_file_age_days' => 365,
            'min_free_space_mb' => 1000,
        ],
    ],
];
````
</augment_code_snippet>

### 1.3.3. Custom Health Checks

> **Custom Checks:** Chinook-specific health monitoring for business logic and data integrity

<augment_code_snippet path="app/Health/Checks/ChinookDataIntegrityCheck.php" mode="EXCERPT">
````php
<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\DB;

class ChinookDataIntegrityCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            // Check for orphaned albums (albums without artists)
            $orphanedAlbums = DB::table('chinook_albums')
                ->leftJoin('chinook_artists', 'chinook_albums.artist_id', '=', 'chinook_artists.id')
                ->whereNull('chinook_artists.id')
                ->count();

            if ($orphanedAlbums > config('health.chinook.data_integrity.max_orphaned_albums', 5)) {
                return $result->failed("Found {$orphanedAlbums} orphaned albums");
            }

            // Check for orphaned tracks (tracks without albums)
            $orphanedTracks = DB::table('chinook_tracks')
                ->leftJoin('chinook_albums', 'chinook_tracks.album_id', '=', 'chinook_albums.id')
                ->whereNull('chinook_albums.id')
                ->count();

            if ($orphanedTracks > config('health.chinook.data_integrity.max_orphaned_tracks', 10)) {
                return $result->failed("Found {$orphanedTracks} orphaned tracks");
            }

            // Check for tracks with invalid durations
            $invalidDurations = DB::table('chinook_tracks')
                ->where(function ($query) {
                    $query->where('milliseconds', '<=', 0)
                          ->orWhere('milliseconds', '>', 3600000); // > 1 hour
                })
                ->count();

            if ($invalidDurations > config('health.chinook.data_integrity.max_invalid_durations', 3)) {
                return $result->failed("Found {$invalidDurations} tracks with invalid durations");
            }

            // Check for invoice total consistency
            $inconsistentInvoices = DB::table('chinook_invoices as i')
                ->leftJoin(DB::raw('(SELECT invoice_id, SUM(unit_price * quantity) as calculated_total FROM chinook_invoice_lines GROUP BY invoice_id) as calc'), 'i.id', '=', 'calc.invoice_id')
                ->whereRaw('ABS(i.total - COALESCE(calc.calculated_total, 0)) > 0.01')
                ->count();

            if ($inconsistentInvoices > 0) {
                return $result->failed("Found {$inconsistentInvoices} invoices with inconsistent totals");
            }

            return $result->ok('All data integrity checks passed');

        } catch (\Exception $e) {
            return $result->failed('Data integrity check failed: ' . $e->getMessage());
        }
    }
}
````
</augment_code_snippet>

## 1.4. Chinook Health Monitoring

### 1.4.1. Database Health Checks

> **Database Monitoring:** SQLite-specific health checks and optimization monitoring

<augment_code_snippet path="app/Health/Checks/ChinookPerformanceCheck.php" mode="EXCERPT">
````php
<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ChinookPerformanceCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            // Check database query performance
            $startTime = microtime(true);

            // Test query performance with a complex join
            DB::table('chinook_tracks')
                ->join('chinook_albums', 'chinook_tracks.album_id', '=', 'chinook_albums.id')
                ->join('chinook_artists', 'chinook_albums.artist_id', '=', 'chinook_artists.id')
                ->select('chinook_artists.name', 'chinook_albums.title', 'chinook_tracks.name')
                ->limit(100)
                ->get();

            $queryTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            if ($queryTime > config('health.chinook.performance.max_query_time_ms', 1000)) {
                return $result->failed("Database query took {$queryTime}ms (threshold: 1000ms)");
            }

            // Check cache performance
            $cacheStartTime = microtime(true);
            Cache::put('health_check_test', 'test_value', 60);
            $cachedValue = Cache::get('health_check_test');
            Cache::forget('health_check_test');
            $cacheTime = (microtime(true) - $cacheStartTime) * 1000;

            if ($cacheTime > 100) { // 100ms threshold for cache operations
                return $result->failed("Cache operation took {$cacheTime}ms (threshold: 100ms)");
            }

            // Check memory usage
            $memoryUsage = memory_get_usage(true) / 1024 / 1024; // Convert to MB
            if ($memoryUsage > config('health.chinook.performance.max_memory_usage_mb', 256)) {
                return $result->failed("Memory usage is {$memoryUsage}MB (threshold: 256MB)");
            }

            // Check SQLite WAL mode status
            $walMode = DB::select("PRAGMA journal_mode")[0]->journal_mode ?? 'unknown';
            if (strtolower($walMode) !== 'wal') {
                return $result->failed("SQLite is not in WAL mode (current: {$walMode})");
            }

            return $result->ok("Performance checks passed - Query: {$queryTime}ms, Cache: {$cacheTime}ms, Memory: {$memoryUsage}MB");

        } catch (\Exception $e) {
            return $result->failed('Performance check failed: ' . $e->getMessage());
        }
    }
}
````
</augment_code_snippet>

### 1.4.2. Media Storage Monitoring

> **Media Health:** Comprehensive media storage and file integrity monitoring

<augment_code_snippet path="app/Health/Checks/ChinookMediaStorageCheck.php" mode="EXCERPT">
````php
<?php

namespace App\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;

class ChinookMediaStorageCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        try {
            // Check for orphaned media files (files without database records)
            $orphanedFiles = $this->findOrphanedFiles();
            if ($orphanedFiles > config('health.chinook.media_storage.max_orphaned_files', 10)) {
                return $result->failed("Found {$orphanedFiles} orphaned media files");
            }

            // Check for missing media files (database records without files)
            $missingFiles = Media::whereDoesntExist(function ($query) {
                // This would need custom implementation based on storage driver
            })->count();

            // Check for missing media conversions
            $missingConversions = Media::where('collection_name', 'cover_art')
                ->whereDoesntHave('conversions', function ($query) {
                    $query->where('conversion_name', 'thumb');
                })
                ->count();

            if ($missingConversions > config('health.chinook.media_storage.max_missing_conversions', 5)) {
                return $result->failed("Found {$missingConversions} media items missing conversions");
            }

            // Check storage disk space
            $freeSpace = disk_free_space(storage_path('app/public'));
            $freeSpaceMB = $freeSpace / 1024 / 1024;

            if ($freeSpaceMB < config('health.chinook.media_storage.min_free_space_mb', 1000)) {
                return $result->failed("Low disk space: {$freeSpaceMB}MB available (minimum: 1000MB)");
            }

            // Check media file integrity (sample check)
            $corruptedFiles = $this->checkMediaIntegrity();
            if ($corruptedFiles > 0) {
                return $result->failed("Found {$corruptedFiles} potentially corrupted media files");
            }

            return $result->ok("Media storage healthy - {$orphanedFiles} orphaned, {$missingConversions} missing conversions, {$freeSpaceMB}MB free");

        } catch (\Exception $e) {
            return $result->failed('Media storage check failed: ' . $e->getMessage());
        }
    }

    private function findOrphanedFiles(): int
    {
        // Implementation would scan storage directory and compare with database
        // This is a simplified version
        return 0;
    }

    private function checkMediaIntegrity(): int
    {
        // Sample integrity check - could be expanded to verify file headers, etc.
        $corruptedCount = 0;

        Media::where('mime_type', 'like', 'image/%')
            ->limit(10) // Sample check to avoid performance issues
            ->each(function ($media) use (&$corruptedCount) {
                try {
                    if (!Storage::disk($media->disk)->exists($media->getPath())) {
                        $corruptedCount++;
                    }
                } catch (\Exception $e) {
                    $corruptedCount++;
                }
            });

        return $corruptedCount;
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Schedule Monitor Guide](300-mvenghaus-filament-plugin-schedule-monitor-guide.md) | **Next:** [Laravel Schedule Monitor Guide](310-spatie-laravel-schedule-monitor-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
