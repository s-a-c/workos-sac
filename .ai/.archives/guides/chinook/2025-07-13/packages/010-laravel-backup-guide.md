# Laravel Backup Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. Configuration Publishing](#12-configuration-publishing)
  - [1.3. Environment Configuration](#13-environment-configuration)
- [Storage Configuration](#storage-configuration)
  - [2.1. Local Storage Setup](#21-local-storage-setup)
  - [2.2. AWS S3 Configuration](#22-aws-s3-configuration)
  - [2.3. Google Cloud Storage](#23-google-cloud-storage)
  - [2.4. Multi-Destination Strategy](#24-multi-destination-strategy)
- [Backup Configuration](#backup-configuration)
  - [3.1. Basic Backup Settings](#31-basic-backup-settings)
  - [3.2. Database Backup Options](#32-database-backup-options)
  - [3.3. File Backup Configuration](#33-file-backup-configuration)
  - [3.4. Exclusion Patterns](#34-exclusion-patterns)
- [Automated Scheduling](#automated-scheduling)
  - [4.1. Laravel Task Scheduler](#41-laravel-task-scheduler)
  - [4.2. Cron Configuration](#42-cron-configuration)
  - [4.3. Backup Frequency Strategies](#43-backup-frequency-strategies)
- [Notification Setup](#notification-setup)
  - [5.1. Email Notifications](#51-email-notifications)
  - [5.2. Slack Integration](#52-slack-integration)
  - [5.3. Custom Notification Channels](#53-custom-notification-channels)
- [Monitoring & Health Checks](#monitoring--health-checks)
  - [6.1. Backup Monitoring Command](#61-backup-monitoring-command)
  - [6.2. Health Check Configuration](#62-health-check-configuration)
  - [6.3. Integration with Laravel Pulse](#63-integration-with-laravel-pulse)
- [Restoration Procedures](#restoration-procedures)
  - [7.1. Database Restoration](#71-database-restoration)
  - [7.2. File Restoration](#72-file-restoration)
  - [7.3. Verification Procedures](#73-verification-procedures)
- [Best Practices](#best-practices)
  - [8.1. Security Considerations](#81-security-considerations)
  - [8.2. Performance Optimization](#82-performance-optimization)
  - [8.3. Monitoring Integration](#83-monitoring-integration)
- [Troubleshooting](#troubleshooting)
  - [9.1. Common Issues](#91-common-issues)
  - [9.2. Debug Mode](#92-debug-mode)
  - [9.3. Recovery Scenarios](#93-recovery-scenarios)
- [Navigation](#navigation)

## Overview

The `spatie/laravel-backup` package provides a comprehensive backup solution for Laravel applications, supporting multiple storage destinations, automated scheduling, and robust notification systems. This guide covers enterprise-level implementation with production-ready configurations.

**🚀 Key Features:**
- **Multi-Destination Backups**: Simultaneous backup to multiple storage providers
- **Automated Scheduling**: Integration with Laravel's task scheduler
- **Comprehensive Monitoring**: Health checks and notification systems
- **Flexible Configuration**: Environment-specific backup strategies
- **Encryption Support**: Secure backup encryption for sensitive data
- **Restoration Tools**: Built-in restoration and verification commands

## Installation & Setup

### 1.1. Package Installation

Install the Laravel Backup package using Composer:

```bash
# Install the package
composer require spatie/laravel-backup

# Install additional dependencies for cloud storage
composer require league/flysystem-aws-s3-v3  # For AWS S3
composer require league/flysystem-google-cloud-storage  # For Google Cloud
```

**Verification Steps:**
```bash
# Verify installation
php artisan list backup

# Expected output should show backup commands:
# backup:clean, backup:list, backup:monitor, backup:run
```

### 1.2. Configuration Publishing

Publish the configuration file to customize backup settings:

```bash
# Publish configuration
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"

# This creates: config/backup.php
```

**Configuration File Structure:**
```php
// config/backup.php
return [
    'backup' => [
        'name' => env('APP_NAME', 'laravel-backup'),
        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                ],
            ],
            'databases' => [
                'sqlite',
            ],
        ],
        'database_dump_compressor' => null,
        'destination' => [
            'filename_prefix' => '',
            'disks' => [
                'local',
            ],
        ],
    ],
    // ... additional configuration
];
```

### 1.3. Environment Configuration

Configure environment variables for backup settings:

```bash
# .env configuration
BACKUP_DISK=local
BACKUP_NAME="${APP_NAME}-backup"
BACKUP_ENCRYPTION_PASSWORD=your-secure-encryption-key

# AWS S3 Configuration (if using S3)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-backup-bucket

# Google Cloud Configuration (if using GCS)
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_CLOUD_KEY_FILE=path/to/service-account.json
GOOGLE_CLOUD_STORAGE_BUCKET=your-backup-bucket
```

## Storage Configuration

### 2.1. Local Storage Setup

Configure local storage for development and testing:

```php
// config/filesystems.php
'disks' => [
    'backup-local' => [
        'driver' => 'local',
        'root' => storage_path('app/backups'),
        'url' => env('APP_URL').'/storage/backups',
        'visibility' => 'private',
        'permissions' => [
            'file' => [
                'public' => 0644,
                'private' => 0600,
            ],
            'dir' => [
                'public' => 0755,
                'private' => 0700,
            ],
        ],
    ],
],
```

**Directory Permissions:**
```bash
# Ensure proper permissions
sudo mkdir -p storage/app/backups
sudo chown -R www-data:www-data storage/app/backups
sudo chmod -R 755 storage/app/backups
```

### 2.2. AWS S3 Configuration

Configure AWS S3 for production backups:

```php
// config/filesystems.php
'disks' => [
    'backup-s3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => false,
        'options' => [
            'ServerSideEncryption' => 'AES256',
            'StorageClass' => 'STANDARD_IA', // Cost-effective for backups
        ],
    ],
],
```

**S3 Bucket Policy Example:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::ACCOUNT-ID:user/backup-user"
            },
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::your-backup-bucket/*"
        },
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::ACCOUNT-ID:user/backup-user"
            },
            "Action": "s3:ListBucket",
            "Resource": "arn:aws:s3:::your-backup-bucket"
        }
    ]
}
```

### 2.3. Google Cloud Storage

Configure Google Cloud Storage for enterprise backups:

```php
// config/filesystems.php
'disks' => [
    'backup-gcs' => [
        'driver' => 'gcs',
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'key_file' => env('GOOGLE_CLOUD_KEY_FILE'),
        'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
        'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', ''),
        'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
        'visibility' => 'private',
        'metadata' => [
            'cacheControl' => 'public,max-age=86400',
        ],
    ],
],
```

**Service Account Permissions:**
```json
{
    "type": "service_account",
    "project_id": "your-project-id",
    "private_key_id": "key-id",
    "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
    "client_email": "backup-service@your-project.iam.gserviceaccount.com",
    "client_id": "client-id",
    "auth_uri": "https://accounts.google.com/o/oauth2/auth",
    "token_uri": "https://oauth2.googleapis.com/token"
}
```

### 2.4. Multi-Destination Strategy

Configure multiple backup destinations for redundancy:

```php
// config/backup.php
'backup' => [
    'destination' => [
        'disks' => [
            'backup-local',    // Local storage for quick access
            'backup-s3',       // AWS S3 for primary cloud backup
            'backup-gcs',      // Google Cloud for secondary backup
        ],
    ],
],
```

**Environment-Specific Configuration:**
```php
// config/backup.php
'backup' => [
    'destination' => [
        'disks' => env('APP_ENV') === 'production' 
            ? ['backup-s3', 'backup-gcs']  // Production: Cloud only
            : ['backup-local'],             // Development: Local only
    ],
],
```

## Backup Configuration

### 3.1. Basic Backup Settings

Configure core backup settings for your application:

```php
// config/backup.php
'backup' => [
    'name' => env('BACKUP_NAME', config('app.name')),
    
    'source' => [
        'files' => [
            'include' => [
                base_path(),
            ],
            'exclude' => [
                base_path('vendor'),
                base_path('node_modules'),
                base_path('.git'),
                base_path('storage/logs'),
                base_path('storage/framework/cache'),
                base_path('storage/framework/sessions'),
                base_path('storage/framework/views'),
            ],
            'follow_links' => false,
            'ignore_unreadable_directories' => false,
            'relative_path' => null,
        ],
        
        'databases' => [
            'sqlite',
        ],
    ],
    
    'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,
    
    'destination' => [
        'filename_prefix' => '',
        'disks' => [
            'backup-s3',
        ],
    ],
],
```

### 3.2. Database Backup Options

Configure database-specific backup settings:

```php
// config/backup.php
'backup' => [
    'source' => [
        'databases' => [
            'sqlite',
        ],
    ],
    
    'database_dump_compressor' => Spatie\DbDumper\Compressors\GzipCompressor::class,
    
    'database_dump_file_extension' => '',
],
```

**Multiple Database Support:**
```php
// For multiple databases
'databases' => [
    'sqlite',      // Primary database
    'mysql_logs',  // Separate logging database
    'redis',       // Redis cache/sessions
],
```

### 3.3. File Backup Configuration

Configure which files to include and exclude:

```php
// config/backup.php
'source' => [
    'files' => [
        'include' => [
            base_path(),
        ],
        'exclude' => [
            // Framework files
            base_path('vendor'),
            base_path('node_modules'),
            base_path('.git'),
            
            // Cache and temporary files
            base_path('storage/logs'),
            base_path('storage/framework/cache'),
            base_path('storage/framework/sessions'),
            base_path('storage/framework/views'),
            base_path('storage/framework/testing'),
            
            // Development files
            base_path('.env.example'),
            base_path('phpunit.xml'),
            base_path('webpack.mix.js'),
            
            // Large media files (backup separately)
            base_path('storage/app/media/videos'),
            base_path('public/uploads/large'),
        ],
        'follow_links' => false,
        'ignore_unreadable_directories' => true,
    ],
],
```

### 3.4. Exclusion Patterns

Use glob patterns for flexible file exclusion:

```php
// config/backup.php
'exclude' => [
    base_path('storage/logs/*.log'),
    base_path('storage/framework/cache/*'),
    base_path('storage/app/temp/*'),
    base_path('public/uploads/temp/*'),
    base_path('**/*.tmp'),
    base_path('**/*.cache'),
    base_path('**/thumbs.db'),
    base_path('**/.DS_Store'),
],
```

## Automated Scheduling

### 4.1. Laravel Task Scheduler

Configure automated backups using Laravel's task scheduler:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Daily full backup at 2 AM
    $schedule->command('backup:run')
        ->daily()
        ->at('02:00')
        ->environments(['production', 'staging']);
    
    // Weekly backup cleanup (keep last 4 weeks)
    $schedule->command('backup:clean')
        ->weekly()
        ->sundays()
        ->at('03:00')
        ->environments(['production', 'staging']);
    
    // Daily backup monitoring
    $schedule->command('backup:monitor')
        ->daily()
        ->at('08:00')
        ->environments(['production', 'staging']);
}
```

**Advanced Scheduling Options:**
```php
// Different backup frequencies
$schedule->command('backup:run --only-db')
    ->hourly()  // Database backup every hour
    ->between('8:00', '18:00')  // Only during business hours
    ->environments(['production']);

$schedule->command('backup:run --only-files')
    ->daily()   // File backup once daily
    ->at('01:00')
    ->environments(['production']);
```

### 4.2. Cron Configuration

Ensure Laravel's scheduler is running via cron:

```bash
# Add to crontab (crontab -e)
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

**Systemd Service Alternative:**
```ini
# /etc/systemd/system/laravel-scheduler.service
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

### 4.3. Backup Frequency Strategies

Design backup strategies based on business requirements:

```php
// config/backup.php - Environment-specific strategies
'backup' => [
    'name' => env('BACKUP_NAME', config('app.name')),
    
    // Production: Frequent, comprehensive backups
    'production' => [
        'database_frequency' => 'hourly',
        'files_frequency' => 'daily',
        'retention_days' => 30,
        'destinations' => ['s3', 'gcs'],
    ],
    
    // Staging: Less frequent, shorter retention
    'staging' => [
        'database_frequency' => 'daily',
        'files_frequency' => 'weekly',
        'retention_days' => 7,
        'destinations' => ['s3'],
    ],
    
    // Development: Minimal backups
    'local' => [
        'database_frequency' => 'weekly',
        'files_frequency' => 'never',
        'retention_days' => 3,
        'destinations' => ['local'],
    ],
],
```

## Notification Setup

### 5.1. Email Notifications

Configure email notifications for backup events:

```php
// config/backup.php
'notifications' => [
    'notifications' => [
        \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class => ['mail'],
        \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
        \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class => ['mail'],
        \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class => ['mail'],
        \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class => [],
        \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class => [],
    ],

    'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

    'mail' => [
        'to' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'backup@example.com'),
            'name' => env('MAIL_FROM_NAME', 'Laravel Backup'),
        ],
    ],
],
```

**Custom Email Template:**
```php
// Create: resources/views/emails/backup-notification.blade.php
@component('mail::message')
# Backup {{ $event }}

**Application:** {{ config('app.name') }}
**Environment:** {{ app()->environment() }}
**Time:** {{ now()->format('Y-m-d H:i:s T') }}

@if($event === 'failed')
@component('mail::panel')
**Error Details:**
{{ $exception->getMessage() }}
@endcomponent
@endif

@if($event === 'successful')
**Backup Details:**
- **Size:** {{ $backupDestination->newestBackup()->size() }}
- **Destination:** {{ $backupDestination->diskName() }}
- **Files:** {{ $backupDestination->newestBackup()->exists() ? 'Yes' : 'No' }}
@endif

Thanks,<br>
{{ config('app.name') }} Backup System
@endcomponent
```

### 5.2. Slack Integration

Configure Slack notifications for team collaboration:

```bash
# Install Slack notification channel
composer require laravel/slack-notification-channel
```

```php
// config/backup.php
'notifications' => [
    'notifications' => [
        \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class => ['mail', 'slack'],
        \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['slack'],
        \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class => ['slack'],
    ],

    'slack' => [
        'webhook_url' => env('BACKUP_SLACK_WEBHOOK_URL'),
        'channel' => env('BACKUP_SLACK_CHANNEL', '#backups'),
        'username' => env('BACKUP_SLACK_USERNAME', 'Laravel Backup'),
        'icon' => env('BACKUP_SLACK_ICON', ':floppy_disk:'),
    ],
],
```

**Environment Configuration:**
```bash
# .env
BACKUP_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
BACKUP_SLACK_CHANNEL=#infrastructure
BACKUP_SLACK_USERNAME="Backup Bot"
BACKUP_SLACK_ICON=":shield:"
```

### 5.3. Custom Notification Channels

Create custom notification channels for specialized alerting:

```php
// app/Notifications/Channels/TeamsChannel.php
<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

class TeamsChannel
{
    public function send($notifiable, Notification $notification): void
    {
        $message = $notification->toTeams($notifiable);

        // Send to Microsoft Teams webhook
        Http::post(config('services.teams.webhook_url'), [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => $message['color'] ?? '0076D7',
            'summary' => $message['title'],
            'sections' => [
                [
                    'activityTitle' => $message['title'],
                    'activitySubtitle' => $message['subtitle'] ?? '',
                    'text' => $message['text'],
                    'facts' => $message['facts'] ?? [],
                ],
            ],
        ]);
    }
}
```

**Register Custom Channel:**
```php
// app/Providers/AppServiceProvider.php
use App\Notifications\Channels\TeamsChannel;

public function boot(): void
{
    Notification::extend('teams', function ($app) {
        return new TeamsChannel();
    });
}
```

## Monitoring & Health Checks

### 6.1. Backup Monitoring Command

Use the built-in monitoring command to check backup health:

```bash
# Manual health check
php artisan backup:monitor

# Expected output for healthy backups:
# ✓ The application name backup is considered healthy.
# ✓ The newest backup was made on 2024-01-15 02:00:00 which is 6 hours ago.
# ✓ The backup is 45.2 MB which is within the expected size range.
```

**Automated Monitoring Schedule:**
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('backup:monitor')
        ->daily()
        ->at('08:00')
        ->emailOutputOnFailure('admin@example.com');
}
```

### 6.2. Health Check Configuration

Configure health check parameters:

```php
// config/backup.php
'monitor_backups' => [
    [
        'name' => env('BACKUP_NAME', config('app.name')),
        'disks' => ['backup-s3'],
        'health_checks' => [
            \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
            \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
        ],
    ],
],
```

**Custom Health Checks:**
```php
// app/HealthChecks/BackupIntegrityCheck.php
<?php

namespace App\HealthChecks;

use Spatie\Backup\Tasks\Monitor\HealthCheck;
use Spatie\Backup\BackupDestination\BackupDestination;

class BackupIntegrityCheck extends HealthCheck
{
    public function checkHealth(BackupDestination $backupDestination): void
    {
        $newestBackup = $backupDestination->newestBackup();

        if (!$newestBackup->exists()) {
            $this->fail('Backup file does not exist');
            return;
        }

        // Verify backup can be read
        try {
            $size = $newestBackup->size();
            if ($size < 1024) { // Less than 1KB is suspicious
                $this->fail("Backup file is suspiciously small: {$size} bytes");
                return;
            }
        } catch (\Exception $e) {
            $this->fail("Cannot read backup file: {$e->getMessage()}");
            return;
        }

        $this->pass();
    }
}
```

### 6.3. Integration with Laravel Pulse

Monitor backup performance with Laravel Pulse:

```php
// app/Providers/AppServiceProvider.php
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\BackupHasFailed;
use Laravel\Pulse\Facades\Pulse;

public function boot(): void
{
    Event::listen(BackupWasSuccessful::class, function (BackupWasSuccessful $event) {
        Pulse::record('backup_successful', [
            'destination' => $event->backupDestination->diskName(),
            'size' => $event->backupDestination->newestBackup()->size(),
        ]);
    });

    Event::listen(BackupHasFailed::class, function (BackupHasFailed $event) {
        Pulse::record('backup_failed', [
            'destination' => $event->backupDestination?->diskName() ?? 'unknown',
            'error' => $event->exception->getMessage(),
        ]);
    });
}
```

## Restoration Procedures

### 7.1. Database Restoration

Restore database from backup:

```bash
# List available backups
php artisan backup:list

# Download specific backup (if using cloud storage)
php artisan backup:restore --backup-name="2024-01-15-02-00-00.zip" --disk=backup-s3

# Extract and restore database
unzip storage/app/backups/2024-01-15-02-00-00.zip
sqlite3 database/database.sqlite < db-dumps/sqlite-database.sql
```

**Automated Restoration Script:**
```bash
#!/bin/bash
# scripts/restore-backup.sh

BACKUP_NAME=$1
RESTORE_PATH="/tmp/backup-restore"

if [ -z "$BACKUP_NAME" ]; then
    echo "Usage: $0 <backup-name>"
    exit 1
fi

echo "Restoring backup: $BACKUP_NAME"

# Create temporary restore directory
mkdir -p $RESTORE_PATH
cd $RESTORE_PATH

# Download backup
php artisan backup:restore --backup-name="$BACKUP_NAME" --disk=backup-s3

# Extract backup
unzip "$BACKUP_NAME"

# Restore database
echo "Restoring database..."
cp db-dumps/sqlite-database.sql /path/to/your/project/database/
cd /path/to/your/project
php artisan migrate:fresh --force
sqlite3 database/database.sqlite < database/sqlite-database.sql

# Restore files (selective)
echo "Restoring critical files..."
cp -r $RESTORE_PATH/storage/app/uploads/* storage/app/uploads/
cp -r $RESTORE_PATH/public/uploads/* public/uploads/

# Cleanup
rm -rf $RESTORE_PATH

echo "Backup restoration completed!"
```

### 7.2. File Restoration

Selective file restoration procedures:

```php
// app/Console/Commands/RestoreFiles.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RestoreFiles extends Command
{
    protected $signature = 'backup:restore-files
                           {backup : Backup filename}
                           {--path= : Specific path to restore}
                           {--disk=backup-s3 : Source disk}';

    protected $description = 'Restore specific files from backup';

    public function handle(): int
    {
        $backupName = $this->argument('backup');
        $specificPath = $this->option('path');
        $disk = $this->option('disk');

        $this->info("Restoring files from backup: {$backupName}");

        // Download backup to temporary location
        $tempPath = storage_path('app/temp/restore');
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        // Extract backup
        $backupPath = Storage::disk($disk)->path($backupName);
        $zip = new \ZipArchive();

        if ($zip->open($backupPath) === TRUE) {
            if ($specificPath) {
                // Extract specific path only
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (str_starts_with($filename, $specificPath)) {
                        $zip->extractTo($tempPath, $filename);
                        $this->line("Extracted: {$filename}");
                    }
                }
            } else {
                // Extract all files
                $zip->extractTo($tempPath);
                $this->info("Extracted all files to temporary location");
            }
            $zip->close();
        } else {
            $this->error("Failed to open backup file");
            return 1;
        }

        $this->info("File restoration completed!");
        $this->line("Files available in: {$tempPath}");

        return 0;
    }
}
```

### 7.3. Verification Procedures

Verify backup integrity before restoration:

```php
// app/Console/Commands/VerifyBackup.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerifyBackup extends Command
{
    protected $signature = 'backup:verify {backup : Backup filename} {--disk=backup-s3}';
    protected $description = 'Verify backup integrity and contents';

    public function handle(): int
    {
        $backupName = $this->argument('backup');
        $disk = $this->option('disk');

        $this->info("Verifying backup: {$backupName}");

        // Check if backup exists
        if (!Storage::disk($disk)->exists($backupName)) {
            $this->error("Backup file not found: {$backupName}");
            return 1;
        }

        // Check file size
        $size = Storage::disk($disk)->size($backupName);
        $this->line("Backup size: " . $this->formatBytes($size));

        // Verify ZIP integrity
        $tempPath = storage_path('app/temp/verify');
        Storage::disk($disk)->copy($backupName, 'temp/verify.zip');

        $zip = new \ZipArchive();
        $result = $zip->open(storage_path('app/temp/verify.zip'), \ZipArchive::CHECKCONS);

        if ($result === TRUE) {
            $this->info("✓ ZIP file integrity verified");
            $this->line("Files in backup: " . $zip->numFiles);

            // List contents
            $this->line("\nBackup contents:");
            for ($i = 0; $i < min($zip->numFiles, 10); $i++) {
                $this->line("  " . $zip->getNameIndex($i));
            }

            if ($zip->numFiles > 10) {
                $this->line("  ... and " . ($zip->numFiles - 10) . " more files");
            }

            $zip->close();
        } else {
            $this->error("✗ ZIP file is corrupted or invalid");
            return 1;
        }

        // Cleanup
        unlink(storage_path('app/temp/verify.zip'));

        $this->info("Backup verification completed successfully!");
        return 0;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
```

## Best Practices

### 8.1. Security Considerations

**Encryption Best Practices:**
```php
// config/backup.php
'backup' => [
    'password' => env('BACKUP_ENCRYPTION_PASSWORD'),
    'encryption' => 'default', // Use strong encryption
],
```

**Access Control:**
```bash
# Restrict backup file permissions
chmod 600 storage/app/backups/*

# Use dedicated backup user
sudo useradd -r -s /bin/false backup-user
sudo chown backup-user:backup-user storage/app/backups
```

**Environment Variables Security:**
```bash
# Use strong encryption passwords
BACKUP_ENCRYPTION_PASSWORD=$(openssl rand -base64 32)

# Rotate credentials regularly
AWS_ACCESS_KEY_ID=AKIA...  # Rotate every 90 days
AWS_SECRET_ACCESS_KEY=...  # Use IAM roles when possible
```

### 8.2. Performance Optimization

**Compression Settings:**
```php
// config/backup.php
'backup' => [
    'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,
    'database_dump_file_extension' => '',
],
```

**Parallel Processing:**
```bash
# Use multiple workers for large backups
php artisan backup:run --only-files &
php artisan backup:run --only-db &
wait
```

**Storage Optimization:**
```php
// Lifecycle policies for cloud storage
'backup' => [
    'destination' => [
        'disks' => ['backup-s3'],
    ],
],

// AWS S3 lifecycle policy (apply via AWS Console or CLI)
{
    "Rules": [
        {
            "Status": "Enabled",
            "Transitions": [
                {
                    "Days": 30,
                    "StorageClass": "STANDARD_IA"
                },
                {
                    "Days": 90,
                    "StorageClass": "GLACIER"
                }
            ]
        }
    ]
}
```

### 8.3. Monitoring Integration

**Laravel Pulse Integration:**
```php
// Create custom Pulse recorder
// app/Pulse/Recorders/BackupRecorder.php
<?php

namespace App\Pulse\Recorders;

use Laravel\Pulse\Recorders\Recorder;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\BackupHasFailed;

class BackupRecorder extends Recorder
{
    public function register(callable $record): void
    {
        Event::listen(BackupWasSuccessful::class, function (BackupWasSuccessful $event) use ($record) {
            $record('backup_success', [
                'destination' => $event->backupDestination->diskName(),
                'size' => $event->backupDestination->newestBackup()->size(),
                'duration' => $event->backupDestination->newestBackup()->date()->diffInSeconds(now()),
            ]);
        });

        Event::listen(BackupHasFailed::class, function (BackupHasFailed $event) use ($record) {
            $record('backup_failure', [
                'destination' => $event->backupDestination?->diskName() ?? 'unknown',
                'error' => $event->exception->getMessage(),
            ]);
        });
    }
}
```

## Troubleshooting

### 9.1. Common Issues

**Permission Errors:**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 755 storage/

# Fix backup directory permissions
sudo mkdir -p storage/app/backups
sudo chown -R www-data:www-data storage/app/backups
sudo chmod -R 755 storage/app/backups
```

**Memory Issues:**
```php
// config/backup.php - Increase memory for large backups
ini_set('memory_limit', '512M');

// Or use streaming for large files
'backup' => [
    'source' => [
        'files' => [
            'exclude' => [
                base_path('storage/app/large-files/*'),
            ],
        ],
    ],
],
```

**Cloud Storage Connection Issues:**
```bash
# Test AWS S3 connection
aws s3 ls s3://your-backup-bucket --profile backup-user

# Test Google Cloud Storage
gsutil ls gs://your-backup-bucket
```

### 9.2. Debug Mode

Enable debug mode for troubleshooting:

```bash
# Run backup with verbose output
php artisan backup:run --verbose

# Check backup status
php artisan backup:list --verbose

# Monitor with detailed output
php artisan backup:monitor --verbose
```

**Log Analysis:**
```bash
# Check Laravel logs for backup errors
tail -f storage/logs/laravel.log | grep -i backup

# Check system logs
sudo tail -f /var/log/syslog | grep -i backup
```

### 9.3. Recovery Scenarios

**Partial Backup Failure:**
```bash
# Retry failed backup
php artisan backup:run --only-db
php artisan backup:run --only-files

# Check what was backed up
php artisan backup:list
```

**Corrupted Backup Recovery:**
```bash
# Verify backup integrity
php artisan backup:verify latest-backup.zip

# Restore from previous backup
php artisan backup:list
php artisan backup:restore --backup-name="previous-backup.zip"
```

---

## Navigation

**← Previous:** [Laravel Package Implementation Guides](000-packages-index.md)

**Next →** [Laravel Pulse Guide](020-laravel-pulse-guide.md)
