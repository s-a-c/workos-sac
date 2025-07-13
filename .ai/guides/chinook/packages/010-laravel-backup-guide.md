# 1. Laravel Backup Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/010-laravel-backup-guide.md` on 2025-07-11

## 1.1 Table of Contents

- [1.2 Overview](#12-overview)
- [1.3 Installation & Setup](#13-installation--setup)
- [1.4 Storage Configuration](#14-storage-configuration)
- [1.5 Backup Configuration](#15-backup-configuration)
- [1.6 Automated Scheduling](#16-automated-scheduling)
- [1.7 Notification Setup](#17-notification-setup)
- [1.8 Monitoring & Health Checks](#18-monitoring--health-checks)
- [1.9 Restoration Procedures](#19-restoration-procedures)

## 1.2 Overview

The `spatie/laravel-backup` package provides a comprehensive backup solution for Laravel applications, supporting multiple storage destinations, automated scheduling, and robust notification systems. This guide covers enterprise-level implementation with production-ready configurations for the Chinook system using Laravel 12 modern patterns and aliziodev/laravel-taxonomy integration.

**🚀 Key Features:**
- **Multi-Destination Backups**: Simultaneous backup to multiple storage providers
- **Automated Scheduling**: Integration with Laravel's task scheduler
- **Comprehensive Monitoring**: Health checks and notification systems
- **Flexible Configuration**: Environment-specific backup strategies
- **Encryption Support**: Secure backup encryption for sensitive data
- **Restoration Tools**: Built-in restoration and verification commands
- **Taxonomy Data Protection**: Specialized backup handling for aliziodev/laravel-taxonomy data

## 1.3 Installation & Setup

### 1.3.1 Package Installation

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

### 1.3.2 Configuration Publishing

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
        'name' => env('APP_NAME', 'chinook-backup'),
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

### 1.3.3 Environment Configuration

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

## 1.4 Storage Configuration

### 1.4.1 Local Storage Setup

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

### 1.4.2 AWS S3 Configuration

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

### 1.4.3 Multi-Destination Strategy

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

## 1.5 Backup Configuration

### 1.5.1 Basic Backup Settings with Taxonomy Support

Configure core backup settings for the Chinook application:

```php
// config/backup.php
'backup' => [
    'name' => env('BACKUP_NAME', 'chinook-'.config('app.name')),
    
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
                // Exclude large media files (backup separately)
                base_path('storage/app/media/videos'),
                base_path('public/uploads/large'),
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
        'filename_prefix' => 'chinook-',
        'disks' => [
            'backup-s3',
        ],
    ],
],
```

### 1.5.2 Database Backup with Taxonomy Tables

Configure database-specific backup settings including taxonomy data:

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
    
    // Custom database dump command for taxonomy preservation
    'database_dump_command_timeout' => 60 * 5, // 5 minutes for large taxonomy datasets
],
```

**Taxonomy-Specific Backup Verification:**
```php
// app/Console/Commands/VerifyTaxonomyBackup.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Term;

class VerifyTaxonomyBackup extends Command
{
    protected $signature = 'backup:verify-taxonomy';
    protected $description = 'Verify taxonomy data integrity in backups';

    public function handle(): int
    {
        $this->info('Verifying taxonomy data for backup...');
        
        $taxonomyCount = Taxonomy::count();
        $termCount = Term::count();
        $termableCount = DB::table('termables')->count();
        
        $this->table(['Component', 'Count'], [
            ['Taxonomies', $taxonomyCount],
            ['Terms', $termCount],
            ['Term Relationships', $termableCount],
        ]);
        
        // Verify critical taxonomies exist
        $criticalTaxonomies = ['Genres', 'Moods', 'Themes'];
        foreach ($criticalTaxonomies as $taxonomyName) {
            $taxonomy = Taxonomy::where('name', $taxonomyName)->first();
            if ($taxonomy) {
                $this->line("✓ {$taxonomyName} taxonomy found with {$taxonomy->terms()->count()} terms");
            } else {
                $this->error("✗ {$taxonomyName} taxonomy missing!");
                return 1;
            }
        }
        
        $this->info('Taxonomy data verification completed successfully!');
        return 0;
    }
}
```

## 1.6 Automated Scheduling

### 1.6.1 Laravel Task Scheduler with Taxonomy Considerations

Configure automated backups using Laravel's task scheduler:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Daily full backup at 2 AM (includes taxonomy data)
    $schedule->command('backup:run')
        ->daily()
        ->at('02:00')
        ->environments(['production', 'staging'])
        ->before(function () {
            // Pre-backup taxonomy verification
            Artisan::call('backup:verify-taxonomy');
        });

    // Weekly backup cleanup (keep last 4 weeks)
    $schedule->command('backup:clean')
        ->weekly()
        ->sundays()
        ->at('03:00')
        ->environments(['production', 'staging']);

    // Daily backup monitoring with taxonomy health check
    $schedule->command('backup:monitor')
        ->daily()
        ->at('08:00')
        ->environments(['production', 'staging'])
        ->after(function () {
            // Post-backup taxonomy integrity check
            Artisan::call('backup:verify-taxonomy');
        });
}
```

### 1.6.2 Advanced Scheduling with Taxonomy Optimization

```php
// Different backup frequencies for taxonomy-heavy operations
$schedule->command('backup:run --only-db')
    ->hourly()  // Database backup every hour (includes taxonomy changes)
    ->between('8:00', '18:00')  // Only during business hours
    ->environments(['production'])
    ->when(function () {
        // Only backup if taxonomy data has changed
        return Cache::get('taxonomy_modified', false);
    });

$schedule->command('backup:run --only-files')
    ->daily()   // File backup once daily
    ->at('01:00')
    ->environments(['production']);
```

## 1.7 Notification Setup

### 1.7.1 Email Notifications with Taxonomy Context

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
        'to' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@chinook.com'),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'backup@chinook.com'),
            'name' => env('MAIL_FROM_NAME', 'Chinook Backup System'),
        ],
    ],
],
```

**Custom Email Template with Taxonomy Information:**
```php
// resources/views/emails/backup-notification.blade.php
@component('mail::message')
# Chinook Backup {{ $event }}

**Application:** {{ config('app.name') }}
**Environment:** {{ app()->environment() }}
**Time:** {{ now()->format('Y-m-d H:i:s T') }}

@if($event === 'successful')
**Backup Details:**
- **Size:** {{ $backupDestination->newestBackup()->size() }}
- **Destination:** {{ $backupDestination->diskName() }}
- **Taxonomies:** {{ \Aliziodev\LaravelTaxonomy\Models\Taxonomy::count() }} taxonomies
- **Terms:** {{ \Aliziodev\LaravelTaxonomy\Models\Term::count() }} terms
- **Relationships:** {{ DB::table('termables')->count() }} term relationships
@endif

@if($event === 'failed')
@component('mail::panel')
**Error Details:**
{{ $exception->getMessage() }}

**Taxonomy Status:**
- Taxonomies: {{ \Aliziodev\LaravelTaxonomy\Models\Taxonomy::count() }}
- Terms: {{ \Aliziodev\LaravelTaxonomy\Models\Term::count() }}
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }} Backup System
@endcomponent
```

### 1.7.2 Slack Integration with Taxonomy Alerts

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
        'channel' => env('BACKUP_SLACK_CHANNEL', '#chinook-backups'),
        'username' => env('BACKUP_SLACK_USERNAME', 'Chinook Backup Bot'),
        'icon' => env('BACKUP_SLACK_ICON', ':floppy_disk:'),
    ],
],
```

## 1.8 Monitoring & Health Checks

### 1.8.1 Backup Monitoring with Taxonomy Validation

Use the built-in monitoring command with taxonomy-specific checks:

```bash
# Manual health check
php artisan backup:monitor

# Expected output for healthy backups:
# ✓ The chinook-application backup is considered healthy.
# ✓ The newest backup was made on 2025-01-15 02:00:00 which is 6 hours ago.
# ✓ The backup is 45.2 MB which is within the expected size range.
```

**Custom Health Check for Taxonomy Data:**
```php
// app/HealthChecks/TaxonomyBackupIntegrityCheck.php
<?php

namespace App\HealthChecks;

use Spatie\Backup\Tasks\Monitor\HealthCheck;
use Spatie\Backup\BackupDestination\BackupDestination;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Term;

class TaxonomyBackupIntegrityCheck extends HealthCheck
{
    public function checkHealth(BackupDestination $backupDestination): void
    {
        $newestBackup = $backupDestination->newestBackup();

        if (!$newestBackup->exists()) {
            $this->fail('Backup file does not exist');
            return;
        }

        // Verify taxonomy data is present in current database
        $taxonomyCount = Taxonomy::count();
        $termCount = Term::count();

        if ($taxonomyCount === 0) {
            $this->fail('No taxonomies found in database - backup may be incomplete');
            return;
        }

        if ($termCount === 0) {
            $this->fail('No terms found in database - backup may be incomplete');
            return;
        }

        // Verify backup size is reasonable for taxonomy data
        $expectedMinSize = ($taxonomyCount + $termCount) * 1024; // Rough estimate
        if ($newestBackup->size() < $expectedMinSize) {
            $this->fail("Backup size ({$newestBackup->size()} bytes) seems too small for taxonomy data");
            return;
        }

        $this->pass("Backup contains {$taxonomyCount} taxonomies and {$termCount} terms");
    }
}
```

### 1.8.2 Integration with Laravel Pulse

Monitor backup performance with Laravel Pulse:

```php
// app/Providers/AppServiceProvider.php
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\BackupHasFailed;
use Laravel\Pulse\Facades\Pulse;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

public function boot(): void
{
    Event::listen(BackupWasSuccessful::class, function (BackupWasSuccessful $event) {
        Pulse::record('backup_successful', [
            'destination' => $event->backupDestination->diskName(),
            'size' => $event->backupDestination->newestBackup()->size(),
            'taxonomies' => Taxonomy::count(),
            'terms' => Term::count(),
        ]);
    });

    Event::listen(BackupHasFailed::class, function (BackupHasFailed $event) {
        Pulse::record('backup_failed', [
            'destination' => $event->backupDestination?->diskName() ?? 'unknown',
            'error' => $event->exception->getMessage(),
            'taxonomy_count' => Taxonomy::count(),
        ]);
    });
}
```

## 1.9 Restoration Procedures

### 1.9.1 Database Restoration with Taxonomy Preservation

Restore database from backup with taxonomy data integrity:

```bash
# List available backups
php artisan backup:list

# Download specific backup (if using cloud storage)
php artisan backup:restore --backup-name="chinook-2025-01-15-02-00-00.zip" --disk=backup-s3

# Extract and restore database with taxonomy verification
unzip storage/app/backups/chinook-2025-01-15-02-00-00.zip
sqlite3 database/database.sqlite < db-dumps/sqlite-database.sql

# Verify taxonomy data after restoration
php artisan backup:verify-taxonomy
```

**Automated Restoration Script with Taxonomy Validation:**
```bash
#!/bin/bash
# scripts/restore-chinook-backup.sh

BACKUP_NAME=$1
RESTORE_PATH="/tmp/chinook-backup-restore"

if [ -z "$BACKUP_NAME" ]; then
    echo "Usage: $0 <backup-name>"
    exit 1
fi

echo "Restoring Chinook backup: $BACKUP_NAME"

# Create temporary restore directory
mkdir -p $RESTORE_PATH
cd $RESTORE_PATH

# Download backup
php artisan backup:restore --backup-name="$BACKUP_NAME" --disk=backup-s3

# Extract backup
unzip "$BACKUP_NAME"

# Backup current database
cp /path/to/chinook/database/database.sqlite /path/to/chinook/database/database.sqlite.backup

# Restore database
echo "Restoring database with taxonomy data..."
cd /path/to/chinook
sqlite3 database/database.sqlite < $RESTORE_PATH/db-dumps/sqlite-database.sql

# Verify taxonomy data integrity
echo "Verifying taxonomy data..."
php artisan backup:verify-taxonomy

if [ $? -eq 0 ]; then
    echo "✓ Taxonomy data verification successful"
else
    echo "✗ Taxonomy data verification failed - restoring backup"
    cp database/database.sqlite.backup database/database.sqlite
    exit 1
fi

# Restore critical files
echo "Restoring critical files..."
cp -r $RESTORE_PATH/storage/app/uploads/* storage/app/uploads/
cp -r $RESTORE_PATH/public/uploads/* public/uploads/

# Cleanup
rm -rf $RESTORE_PATH
rm database/database.sqlite.backup

echo "Chinook backup restoration completed successfully!"
```

### 1.9.2 Taxonomy-Specific Restoration Commands

```php
// app/Console/Commands/RestoreTaxonomyData.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Term;

class RestoreTaxonomyData extends Command
{
    protected $signature = 'backup:restore-taxonomy
                           {backup : Backup filename}
                           {--verify : Verify data after restoration}';

    protected $description = 'Restore taxonomy data from backup with verification';

    public function handle(): int
    {
        $backupName = $this->argument('backup');
        $verify = $this->option('verify');

        $this->info("Restoring taxonomy data from backup: {$backupName}");

        // Store current counts for comparison
        $originalTaxonomies = Taxonomy::count();
        $originalTerms = Term::count();

        try {
            // Perform restoration logic here
            $this->restoreTaxonomyFromBackup($backupName);

            // Verify restoration if requested
            if ($verify) {
                $newTaxonomies = Taxonomy::count();
                $newTerms = Term::count();

                $this->table(['Metric', 'Before', 'After'], [
                    ['Taxonomies', $originalTaxonomies, $newTaxonomies],
                    ['Terms', $originalTerms, $newTerms],
                ]);

                if ($newTaxonomies > 0 && $newTerms > 0) {
                    $this->info('✓ Taxonomy restoration verified successfully');
                } else {
                    $this->error('✗ Taxonomy restoration verification failed');
                    return 1;
                }
            }

            $this->info('Taxonomy data restoration completed!');
            return 0;

        } catch (\Exception $e) {
            $this->error("Restoration failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function restoreTaxonomyFromBackup(string $backupName): void
    {
        // Implementation for extracting and restoring taxonomy data
        // This would involve extracting the backup, parsing the SQL dump,
        // and selectively restoring taxonomy-related tables
    }
}
```

---

**Next**: [Laravel Pulse Guide](020-laravel-pulse-guide.md) | **Previous**: [Package Index](000-packages-index.md)

---

*This guide demonstrates comprehensive backup strategies for the Chinook system using Laravel 12, spatie/laravel-backup, and specialized handling for aliziodev/laravel-taxonomy data.*

[⬆️ Back to Top](#1-laravel-backup-implementation-guide)
