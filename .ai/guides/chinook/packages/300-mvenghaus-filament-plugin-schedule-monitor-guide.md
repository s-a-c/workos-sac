# 1. Mvenghaus Filament Plugin Schedule Monitor Integration Guide

> **Package Source:** [mvenghaus/filament-plugin-schedule-monitor](https://github.com/mvenghaus/filament-plugin-schedule-monitor)  
> **Official Documentation:** [Filament Schedule Monitor Documentation](https://github.com/mvenghaus/filament-plugin-schedule-monitor/blob/main/README.md)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook scheduled task monitoring and management  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Plugin Registration](#131-plugin-registration)
  - [1.3.2. Schedule Monitor Configuration](#132-schedule-monitor-configuration)
- [1.4. Chinook Scheduled Tasks](#14-chinook-scheduled-tasks)
  - [1.4.1. Database Maintenance Tasks](#141-database-maintenance-tasks)
  - [1.4.2. Media Processing Tasks](#142-media-processing-tasks)
  - [1.4.3. Backup Automation](#143-backup-automation)
- [1.5. Monitoring Dashboard](#15-monitoring-dashboard)
- [1.6. Alerting & Notifications](#16-alerting--notifications)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Filament Schedule Monitor documentation](https://github.com/mvenghaus/filament-plugin-schedule-monitor/blob/main/README.md) for Laravel 12 and Chinook project requirements, integrating with the existing [spatie/laravel-schedule-monitor](310-spatie-laravel-schedule-monitor-guide.md) foundation.

**Mvenghaus Filament Plugin Schedule Monitor** provides a comprehensive Filament admin interface for monitoring Laravel scheduled tasks. It offers real-time task status visualization, execution history, and failure alerting capabilities.

### 1.2.1. Key Features

- **Real-time Task Monitoring**: Live status of all scheduled tasks
- **Execution History**: Detailed logs of task runs and outcomes
- **Failure Detection**: Automatic detection and alerting of failed tasks
- **Performance Metrics**: Task execution time and resource usage tracking
- **Manual Task Execution**: Trigger scheduled tasks manually from the interface
- **Task Management**: Enable/disable tasks and modify schedules

### 1.2.2. Integration with Spatie Schedule Monitor

> **Foundation Package:** Built on [spatie/laravel-schedule-monitor](310-spatie-laravel-schedule-monitor-guide.md) with enhanced UI

**Package Relationship:**
- **Spatie Laravel Schedule Monitor**: Provides core schedule monitoring functionality
- **Mvenghaus Schedule Monitor Plugin**: Provides Filament admin interface and management
- **Combined Workflow**: Automated task monitoring with comprehensive dashboard

## 1.3. Installation & Configuration

### 1.3.1. Plugin Registration

> **Configuration Source:** Based on [official installation guide](https://github.com/mvenghaus/filament-plugin-schedule-monitor#installation)  
> **Chinook Enhancement:** Already configured in AdminPanelProvider

The plugin is already registered in the admin panel. Verify configuration:

<augment_code_snippet path="app/Providers/Filament/AdminPanelProvider.php" mode="EXCERPT">
````php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Mvenghaus\FilamentPluginScheduleMonitor\FilamentPluginScheduleMonitorPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ... existing configuration ...
            
            // Filament Schedule Monitor Plugin
            ->plugin(
                FilamentPluginScheduleMonitorPlugin::make()
                    ->navigationGroup('System Monitoring')
                    ->navigationSort(40)
                    ->navigationIcon('heroicon-o-clock')
                    ->navigationLabel('Scheduled Tasks')
                    ->enableNavigation(true)
                    ->enableManualRun(true) // Allow manual task execution
                    ->enableTaskToggle(true) // Allow enabling/disabling tasks
            );
    }
}
````
</augment_code_snippet>

### 1.3.2. Schedule Monitor Configuration

> **Configuration Source:** Enhanced from [spatie/laravel-schedule-monitor configuration](310-spatie-laravel-schedule-monitor-guide.md)  
> **Chinook Modifications:** Optimized for Chinook-specific scheduled tasks

<augment_code_snippet path="config/schedule-monitor.php" mode="EXCERPT">
````php
<?php
// Configuration enhanced from: https://github.com/spatie/laravel-schedule-monitor/blob/main/config/schedule-monitor.php
// Chinook modifications: Enhanced for Chinook scheduled task monitoring
// Laravel 12 updates: Modern syntax and framework patterns

return [
    /*
     * Schedule monitor model
     */
    'models' => [
        'monitored_scheduled_task' => Spatie\ScheduleMonitor\Models\MonitoredScheduledTask::class,
    ],

    /*
     * Database table name
     */
    'table_name' => 'monitored_scheduled_tasks',

    /*
     * Date format for task scheduling
     */
    'date_format' => 'Y-m-d H:i:s',

    /*
     * Monitor configuration
     */
    'monitor' => [
        /*
         * Enable monitoring for all scheduled tasks
         */
        'enabled' => env('SCHEDULE_MONITOR_ENABLED', true),

        /*
         * Store output of scheduled tasks
         */
        'store_output_in_db' => true,

        /*
         * Maximum output length to store
         */
        'max_output_length' => 10000,

        /*
         * Delete old task runs after this many days
         */
        'delete_after_days' => 30,
    ],

    /*
     * Notification configuration
     */
    'notifications' => [
        /*
         * Enable notifications for task failures
         */
        'enabled' => env('SCHEDULE_MONITOR_NOTIFICATIONS_ENABLED', true),

        /*
         * Notification channels
         */
        'notifications' => [
            \Spatie\ScheduleMonitor\Notifications\ScheduledTaskFailed::class => [
                'mail', 'slack'
            ],
            \Spatie\ScheduleMonitor\Notifications\ScheduledTaskFinished::class => [
                // 'mail' // Uncomment to get notified of successful tasks
            ],
        ],

        /*
         * Notifiable configuration
         */
        'notifiable' => \Spatie\ScheduleMonitor\Notifications\Notifiable::class,

        /*
         * Mail configuration
         */
        'mail' => [
            'to' => env('SCHEDULE_MONITOR_MAIL_TO', 'admin@chinook.local'),
            'subject' => 'Chinook Scheduled Task Alert',
        ],

        /*
         * Slack configuration
         */
        'slack' => [
            'webhook_url' => env('SCHEDULE_MONITOR_SLACK_WEBHOOK_URL'),
            'channel' => env('SCHEDULE_MONITOR_SLACK_CHANNEL', '#alerts'),
            'username' => 'Chinook Task Monitor',
            'icon' => ':alarm_clock:',
        ],
    ],

    /*
     * Chinook-specific configuration
     */
    'chinook' => [
        /*
         * Task categories for organization
         */
        'task_categories' => [
            'database_maintenance' => 'Database Maintenance',
            'media_processing' => 'Media Processing',
            'backup_operations' => 'Backup Operations',
            'data_cleanup' => 'Data Cleanup',
            'analytics_processing' => 'Analytics Processing',
            'notification_sending' => 'Notification Sending',
        ],

        /*
         * Critical tasks that require immediate attention on failure
         */
        'critical_tasks' => [
            'backup:run',
            'health:check',
            'queue:work',
            'chinook:database-maintenance',
        ],

        /*
         * Performance monitoring
         */
        'performance_monitoring' => [
            'track_memory_usage' => true,
            'track_execution_time' => true,
            'alert_on_long_running_tasks' => true,
            'max_execution_time_minutes' => 60,
        ],

        /*
         * Task retry configuration
         */
        'retry_configuration' => [
            'enable_auto_retry' => true,
            'max_retry_attempts' => 3,
            'retry_delay_minutes' => 5,
            'exponential_backoff' => true,
        ],
    ],
];
````
</augment_code_snippet>

## 1.4. Chinook Scheduled Tasks

### 1.4.1. Database Maintenance Tasks

> **Database Maintenance:** Automated SQLite optimization and cleanup tasks

<augment_code_snippet path="app/Console/Commands/ChinookDatabaseMaintenance.php" mode="EXCERPT">
````php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;

class ChinookDatabaseMaintenance extends Command
{
    protected $signature = 'chinook:database-maintenance';
    protected $description = 'Perform routine database maintenance for Chinook SQLite database';

    public function handle(): int
    {
        $this->info('Starting Chinook database maintenance...');

        try {
            // SQLite VACUUM operation for space reclamation
            $this->info('Running VACUUM operation...');
            DB::statement('VACUUM');

            // SQLite ANALYZE operation for query optimization
            $this->info('Running ANALYZE operation...');
            DB::statement('ANALYZE');

            // WAL checkpoint to merge WAL file back to main database
            $this->info('Running WAL checkpoint...');
            DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');

            // Clean up old activity logs
            $this->info('Cleaning up old activity logs...');
            $deletedLogs = DB::table('activity_log')
                ->where('created_at', '<', now()->subDays(90))
                ->delete();
            $this->info("Deleted {$deletedLogs} old activity log entries");

            // Clean up old media conversions
            $this->info('Cleaning up orphaned media conversions...');
            $deletedConversions = DB::table('media')
                ->whereNull('model_id')
                ->where('created_at', '<', now()->subDays(30))
                ->delete();
            $this->info("Deleted {$deletedConversions} orphaned media entries");

            // Update database statistics
            $this->updateDatabaseStatistics();

            $this->info('Database maintenance completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Database maintenance failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateDatabaseStatistics(): void
    {
        $stats = [
            'artists_count' => DB::table('chinook_artists')->count(),
            'albums_count' => DB::table('chinook_albums')->count(),
            'tracks_count' => DB::table('chinook_tracks')->count(),
            'database_size_mb' => $this->getDatabaseSizeMB(),
            'last_maintenance' => now(),
        ];

        // Store statistics for monitoring
        cache()->put('chinook_database_stats', $stats, now()->addDay());
        
        $this->info('Database statistics updated');
    }

    private function getDatabaseSizeMB(): float
    {
        $dbPath = database_path('database.sqlite');
        return file_exists($dbPath) ? round(filesize($dbPath) / 1024 / 1024, 2) : 0;
    }
}
````
</augment_code_snippet>

### 1.4.2. Media Processing Tasks

> **Media Processing:** Automated media optimization and cleanup tasks

<augment_code_snippet path="app/Console/Commands/ChinookMediaProcessing.php" mode="EXCERPT">
````php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;

class ChinookMediaProcessing extends Command
{
    protected $signature = 'chinook:media-processing';
    protected $description = 'Process and optimize Chinook media files';

    public function handle(): int
    {
        $this->info('Starting Chinook media processing...');

        try {
            // Process pending media conversions
            $this->processPendingConversions();

            // Clean up orphaned media files
            $this->cleanupOrphanedFiles();

            // Generate missing thumbnails
            $this->generateMissingThumbnails();

            // Optimize storage usage
            $this->optimizeStorageUsage();

            $this->info('Media processing completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Media processing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function processPendingConversions(): void
    {
        $this->info('Processing pending media conversions...');
        
        $pendingMedia = Media::whereDoesntHave('conversions')
            ->where('collection_name', 'cover_art')
            ->limit(50)
            ->get();

        foreach ($pendingMedia as $media) {
            try {
                $media->performConversions();
                $this->info("Processed conversions for media ID: {$media->id}");
            } catch (\Exception $e) {
                $this->warn("Failed to process media ID {$media->id}: {$e->getMessage()}");
            }
        }
    }

    private function cleanupOrphanedFiles(): void
    {
        $this->info('Cleaning up orphaned media files...');
        
        $orphanedMedia = Media::whereNull('model_id')
            ->where('created_at', '<', now()->subDays(7))
            ->get();

        foreach ($orphanedMedia as $media) {
            try {
                $media->delete();
                $this->info("Deleted orphaned media: {$media->file_name}");
            } catch (\Exception $e) {
                $this->warn("Failed to delete media {$media->id}: {$e->getMessage()}");
            }
        }
    }

    private function generateMissingThumbnails(): void
    {
        $this->info('Generating missing thumbnails...');
        
        $mediaWithoutThumbs = Media::where('mime_type', 'like', 'image/%')
            ->whereDoesntHave('conversions', function ($query) {
                $query->where('conversion_name', 'thumb');
            })
            ->limit(20)
            ->get();

        foreach ($mediaWithoutThumbs as $media) {
            try {
                $media->performConversions('thumb');
                $this->info("Generated thumbnail for: {$media->file_name}");
            } catch (\Exception $e) {
                $this->warn("Failed to generate thumbnail for {$media->id}: {$e->getMessage()}");
            }
        }
    }

    private function optimizeStorageUsage(): void
    {
        $this->info('Optimizing storage usage...');
        
        // Calculate and cache storage statistics
        $totalSize = Media::sum('size');
        $mediaCount = Media::count();
        
        cache()->put('chinook_media_stats', [
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'media_count' => $mediaCount,
            'last_processed' => now(),
        ], now()->addDay());
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Filament Spotlight Guide](260-pxlrbt-filament-spotlight-guide.md) | **Next:** [Laravel Health Guide](320-spatie-laravel-health-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
