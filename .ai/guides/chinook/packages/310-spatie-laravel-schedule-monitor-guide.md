# 1. Spatie Laravel Schedule Monitor Integration Guide

> **Package Source:** [spatie/laravel-schedule-monitor](https://github.com/spatie/laravel-schedule-monitor)  
> **Official Documentation:** [Laravel Schedule Monitor Documentation](https://spatie.be/docs/laravel-schedule-monitor)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook scheduled task monitoring and automation  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Package Installation](#131-package-installation)
  - [1.3.2. Schedule Monitor Configuration](#132-schedule-monitor-configuration)
  - [1.3.3. Task Registration](#133-task-registration)
- [1.4. Chinook Scheduled Tasks](#14-chinook-scheduled-tasks)
  - [1.4.1. Database Maintenance Scheduling](#141-database-maintenance-scheduling)
  - [1.4.2. Media Processing Automation](#142-media-processing-automation)
  - [1.4.3. Backup Scheduling](#143-backup-scheduling)
- [1.5. Monitoring & Alerting](#15-monitoring--alerting)
- [1.6. Integration with Filament](#16-integration-with-filament)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Spatie Laravel Schedule Monitor documentation](https://spatie.be/docs/laravel-schedule-monitor) for Laravel 12 and Chinook project requirements, providing the foundation for the [Filament Schedule Monitor Plugin](300-mvenghaus-filament-plugin-schedule-monitor-guide.md) interface.

**Spatie Laravel Schedule Monitor** provides comprehensive monitoring for Laravel scheduled tasks. It tracks task execution, detects failures, monitors performance, and provides alerting capabilities for critical scheduled operations.

### 1.2.1. Key Features

- **Task Execution Monitoring**: Track success/failure of all scheduled tasks
- **Performance Metrics**: Monitor execution time and resource usage
- **Failure Detection**: Automatic detection and alerting of failed tasks
- **Historical Data**: Maintain execution history and trends
- **Flexible Alerting**: Integration with multiple notification channels
- **Custom Task Support**: Monitor any scheduled command or job

### 1.2.2. Chinook Scheduling Strategy

- **Database Maintenance**: Automated SQLite optimization and cleanup
- **Media Processing**: Scheduled media conversion and optimization
- **Backup Operations**: Regular database and file backups
- **Data Analytics**: Scheduled report generation and data processing
- **Health Monitoring**: Regular health check execution and reporting

## 1.3. Installation & Configuration

### 1.3.1. Package Installation

> **Installation Source:** Based on [official installation guide](https://spatie.be/docs/laravel-schedule-monitor/v3/installation-setup)  
> **Chinook Enhancement:** Already installed and configured

The package is already installed via Composer. Verify installation:

<augment_code_snippet path="composer.json" mode="EXCERPT">
````json
{
    "require": {
        "spatie/laravel-schedule-monitor": "^3.10"
    }
}
````
</augment_code_snippet>

**Publish Configuration and Migrations:**

```bash
# Publish configuration file
php artisan vendor:publish --tag="schedule-monitor-config"

# Publish and run migrations
php artisan vendor:publish --tag="schedule-monitor-migrations"
php artisan migrate

# Install the schedule monitor
php artisan schedule-monitor:install
```

### 1.3.2. Schedule Monitor Configuration

> **Configuration Source:** Enhanced from [schedule monitor configuration](https://spatie.be/docs/laravel-schedule-monitor/v3/installation-setup#publishing-the-config-file)  
> **Chinook Modifications:** Optimized for Chinook scheduled task requirements

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
         * Notification classes and channels
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
            'health_monitoring' => 'Health Monitoring',
        ],

        /*
         * Critical tasks that require immediate attention on failure
         */
        'critical_tasks' => [
            'backup:run',
            'health:check',
            'chinook:database-maintenance',
            'chinook:media-processing',
        ],

        /*
         * Performance monitoring thresholds
         */
        'performance_monitoring' => [
            'track_memory_usage' => true,
            'track_execution_time' => true,
            'alert_on_long_running_tasks' => true,
            'max_execution_time_minutes' => 60,
            'memory_threshold_mb' => 256,
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

        /*
         * Monitoring intervals
         */
        'monitoring_intervals' => [
            'heartbeat_check_minutes' => 2,
            'cleanup_old_records_days' => 30,
            'performance_analysis_hours' => 24,
        ],
    ],
];
````
</augment_code_snippet>

### 1.3.3. Task Registration

> **Task Registration:** Configure scheduled tasks with monitoring in the Laravel scheduler

<augment_code_snippet path="app/Console/Kernel.php" mode="EXCERPT">
````php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Database maintenance - Daily at 2 AM
        $schedule->command('chinook:database-maintenance')
            ->daily()
            ->at('02:00')
            ->monitorName('chinook-database-maintenance')
            ->graceTimeInMinutes(10)
            ->emailOutputOnFailure('admin@chinook.local')
            ->appendOutputTo(storage_path('logs/database-maintenance.log'));

        // Media processing - Every 4 hours
        $schedule->command('chinook:media-processing')
            ->cron('0 */4 * * *')
            ->monitorName('chinook-media-processing')
            ->graceTimeInMinutes(30)
            ->emailOutputOnFailure('admin@chinook.local')
            ->appendOutputTo(storage_path('logs/media-processing.log'));

        // Backup operations - Daily at 1 AM
        $schedule->command('backup:run')
            ->daily()
            ->at('01:00')
            ->monitorName('chinook-backup')
            ->graceTimeInMinutes(60)
            ->emailOutputOnFailure('admin@chinook.local')
            ->appendOutputTo(storage_path('logs/backup.log'));

        // Health checks - Every 5 minutes
        $schedule->command('health:check')
            ->everyFiveMinutes()
            ->monitorName('chinook-health-check')
            ->graceTimeInMinutes(2)
            ->withoutOverlapping()
            ->runInBackground();

        // Activity log cleanup - Weekly on Sunday at 3 AM
        $schedule->command('activitylog:clean')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->monitorName('chinook-activity-cleanup')
            ->graceTimeInMinutes(15);

        // Media library cleanup - Weekly on Saturday at 4 AM
        $schedule->command('media-library:clean')
            ->weekly()
            ->saturdays()
            ->at('04:00')
            ->monitorName('chinook-media-cleanup')
            ->graceTimeInMinutes(30);

        // Queue monitoring - Every minute
        $schedule->command('queue:monitor default --max=100')
            ->everyMinute()
            ->monitorName('chinook-queue-monitor')
            ->graceTimeInMinutes(1)
            ->withoutOverlapping();

        // Schedule monitor cleanup - Daily at 5 AM
        $schedule->command('schedule-monitor:clean')
            ->daily()
            ->at('05:00')
            ->monitorName('schedule-monitor-cleanup')
            ->graceTimeInMinutes(5);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Laravel Health Guide](320-spatie-laravel-health-guide.md) | **Next:** [Laravel Settings Guide](180-spatie-laravel-settings-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
