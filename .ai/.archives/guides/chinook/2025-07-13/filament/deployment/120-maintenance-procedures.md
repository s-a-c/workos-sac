# Filament Maintenance Procedures Guide

## Overview

This guide covers comprehensive maintenance procedures for the Chinook Filament admin panel, including routine maintenance tasks, system updates, performance optimization, and troubleshooting procedures.

## Table of Contents

- [Overview](#overview)
- [Routine Maintenance Schedule](#routine-maintenance-schedule)
- [System Updates](#system-updates)
- [Database Maintenance](#database-maintenance)
- [Cache Management](#cache-management)
- [Log Management](#log-management)
- [Performance Optimization](#performance-optimization)
- [Security Maintenance](#security-maintenance)
- [Monitoring & Health Checks](#monitoring--health-checks)
- [Emergency Procedures](#emergency-procedures)
- [Troubleshooting](#troubleshooting)

## Routine Maintenance Schedule

### Daily Maintenance Tasks

```bash
#!/bin/bash
# scripts/daily-maintenance.sh

set -euo pipefail

echo "Starting daily maintenance tasks..."

# Clear expired cache entries
php artisan cache:prune-stale-tags
php artisan view:clear

# Clean up temporary files
find /tmp -name "laravel_*" -mtime +1 -delete
find storage/framework/cache -name "*.php" -mtime +1 -delete

# Optimize database
php artisan model:prune

# Update search indexes
php artisan scout:import "App\Models\Track"
php artisan scout:import "App\Models\Album"
php artisan scout:import "App\Models\Artist"

# Generate sitemap
php artisan sitemap:generate

# Check queue health
php artisan queue:monitor

# Verify backup integrity
php artisan backup:verify --type=database

echo "Daily maintenance completed successfully"
```

### Weekly Maintenance Tasks

```bash
#!/bin/bash
# scripts/weekly-maintenance.sh

set -euo pipefail

echo "Starting weekly maintenance tasks..."

# Full cache clear and rebuild
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Database optimization
php artisan db:optimize
php artisan db:analyze-tables

# Clean up old logs
php artisan logs:cleanup --days=30

# Update composer dependencies (security updates only)
composer audit
composer update --with-dependencies --prefer-stable

# Clear and rebuild search indexes
php artisan scout:flush "App\Models\Track"
php artisan scout:import "App\Models\Track"

# Generate fresh API documentation
php artisan l5-swagger:generate

# Run security scan
php artisan security:scan

echo "Weekly maintenance completed successfully"
```

### Monthly Maintenance Tasks

```bash
#!/bin/bash
# scripts/monthly-maintenance.sh

set -euo pipefail

echo "Starting monthly maintenance tasks..."

# Full system backup
php artisan backup:run --only-db
php artisan backup:run --only-files

# Database maintenance
php artisan db:vacuum
php artisan db:reindex

# Clean up old sessions
php artisan session:gc

# Update all dependencies
composer update --prefer-stable
npm update

# Rebuild all caches
php artisan optimize:clear
php artisan optimize

# Security audit
php artisan security:audit
npm audit

# Performance analysis
php artisan performance:analyze

# Generate monthly reports
php artisan reports:generate --type=monthly

echo "Monthly maintenance completed successfully"
```

## System Updates

### Laravel Framework Updates

```php
<?php
// app/Console/Commands/UpdateLaravel.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, Log};
use Illuminate\Support\Process\Process;

class UpdateLaravel extends Command
{
    protected $signature = 'system:update-laravel {--dry-run : Show what would be updated}';
    protected $description = 'Update Laravel framework and related packages';

    public function handle(): void
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Checking for Laravel updates...');

        // Check current version
        $currentVersion = app()->version();
        $this->line("Current Laravel version: {$currentVersion}");

        // Check for updates
        $updates = $this->checkForUpdates();

        if (empty($updates)) {
            $this->info('No updates available');
            return;
        }

        $this->table(['Package', 'Current', 'Available'], $updates);

        if ($isDryRun) {
            $this->info('Dry run completed. Use --no-dry-run to apply updates.');
            return;
        }

        if (!$this->confirm('Do you want to proceed with the updates?')) {
            $this->info('Update cancelled');
            return;
        }

        $this->performUpdate();
    }

    private function checkForUpdates(): array
    {
        $process = Process::start('composer outdated --direct --format=json');
        $result = $process->wait();

        if (!$result->successful()) {
            $this->error('Failed to check for updates');
            return [];
        }

        $data = json_decode($result->output(), true);
        $updates = [];

        foreach ($data['installed'] ?? [] as $package) {
            if (str_starts_with($package['name'], 'laravel/') ||
                str_starts_with($package['name'], 'filament/')) {
                $updates[] = [
                    $package['name'],
                    $package['version'],
                    $package['latest'] ?? 'N/A'
                ];
            }
        }

        return $updates;
    }

    private function performUpdate(): void
    {
        $this->info('Creating backup before update...');
        Artisan::call('backup:run');

        $this->info('Updating packages...');

        $commands = [
            'composer update laravel/framework --with-dependencies',
            'composer update filament/filament --with-dependencies',
            'php artisan migrate --force',
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
            'npm update',
            'npm run build',
        ];

        foreach ($commands as $command) {
            $this->line("Running: {$command}");

            $process = Process::start($command);
            $result = $process->wait();

            if (!$result->successful()) {
                $this->error("Command failed: {$command}");
                $this->error($result->errorOutput());

                Log::error('Update command failed', [
                    'command' => $command,
                    'error' => $result->errorOutput()
                ]);

                return;
            }
        }

        $this->info('Update completed successfully');
        Log::info('Laravel update completed successfully');
    }
}
```

### Package Updates

```bash
#!/bin/bash
# scripts/update-packages.sh

set -euo pipefail

echo "Checking for package updates..."

# Backup before updates
php artisan backup:run --only-db

# Check for security updates
composer audit

# Update security-related packages only
composer update --with-dependencies \
    spatie/laravel-permission \
    laravel/sanctum \
    filament/filament \
    --prefer-stable

# Update Node.js packages
npm audit fix
npm update

# Clear caches after updates
php artisan optimize:clear
php artisan optimize

# Run tests to verify updates
php artisan test --testsuite=Feature

echo "Package updates completed successfully"
```

## Database Maintenance

### Database Optimization

```php
<?php
// app/Console/Commands/OptimizeDatabase.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Log};

class OptimizeDatabase extends Command
{
    protected $signature = 'db:optimize {--analyze : Run ANALYZE TABLE}';
    protected $description = 'Optimize database tables and indexes';

    public function handle(): void
    {
        $this->info('Starting database optimization...');

        $tables = $this->getDatabaseTables();

        foreach ($tables as $table) {
            $this->optimizeTable($table);

            if ($this->option('analyze')) {
                $this->analyzeTable($table);
            }
        }

        $this->info('Database optimization completed');
    }

    private function getDatabaseTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $tableColumn = 'Tables_in_' . config('database.connections.mysql.database');

        return array_map(fn($table) => $table->$tableColumn, $tables);
    }

    private function optimizeTable(string $table): void
    {
        $this->line("Optimizing table: {$table}");

        try {
            DB::statement("OPTIMIZE TABLE `{$table}`");
            $this->info("✓ Optimized {$table}");
        } catch (\Exception $e) {
            $this->error("✗ Failed to optimize {$table}: " . $e->getMessage());
            Log::error('Table optimization failed', [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function analyzeTable(string $table): void
    {
        $this->line("Analyzing table: {$table}");

        try {
            DB::statement("ANALYZE TABLE `{$table}`");
            $this->info("✓ Analyzed {$table}");
        } catch (\Exception $e) {
            $this->error("✗ Failed to analyze {$table}: " . $e->getMessage());
            Log::error('Table analysis failed', [
                'table' => $table,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### Index Maintenance

```sql
-- Database index maintenance queries

-- Check for unused indexes
SELECT
    s.table_name,
    s.index_name,
    s.cardinality,
    s.sub_part,
    s.packed,
    s.nullable,
    s.index_type
FROM information_schema.statistics s
LEFT JOIN information_schema.index_statistics i
    ON s.table_schema = i.table_schema
    AND s.table_name = i.table_name
    AND s.index_name = i.index_name
WHERE s.table_schema = DATABASE()
    AND i.index_name IS NULL
    AND s.index_name != 'PRIMARY';

-- Check for duplicate indexes
SELECT
    table_name,
    GROUP_CONCAT(index_name) as duplicate_indexes,
    GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
GROUP BY table_name, GROUP_CONCAT(column_name ORDER BY seq_in_index)
HAVING COUNT(*) > 1;

-- Check index cardinality
SELECT
    table_name,
    index_name,
    cardinality,
    ROUND(cardinality / (SELECT table_rows FROM information_schema.tables
                        WHERE table_schema = DATABASE()
                        AND table_name = s.table_name) * 100, 2) as selectivity_percent
FROM information_schema.statistics s
WHERE table_schema = DATABASE()
    AND cardinality > 0
ORDER BY selectivity_percent DESC;
```

## Cache Management

### Cache Optimization

```php
<?php
// app/Console/Commands/OptimizeCache.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Redis, Log};

class OptimizeCache extends Command
{
    protected $signature = 'cache:optimize {--clear : Clear all caches first}';
    protected $description = 'Optimize application caches';

    public function handle(): void
    {
        if ($this->option('clear')) {
            $this->clearAllCaches();
        }

        $this->optimizeConfigCache();
        $this->optimizeRouteCache();
        $this->optimizeViewCache();
        $this->optimizeEventCache();
        $this->optimizeRedisCache();

        $this->info('Cache optimization completed');
    }

    private function clearAllCaches(): void
    {
        $this->info('Clearing all caches...');

        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        $this->call('event:clear');
    }

    private function optimizeConfigCache(): void
    {
        $this->line('Optimizing configuration cache...');
        $this->call('config:cache');
    }

    private function optimizeRouteCache(): void
    {
        $this->line('Optimizing route cache...');
        $this->call('route:cache');
    }

    private function optimizeViewCache(): void
    {
        $this->line('Optimizing view cache...');
        $this->call('view:cache');
    }

    private function optimizeEventCache(): void
    {
        $this->line('Optimizing event cache...');
        $this->call('event:cache');
    }

    private function optimizeRedisCache(): void
    {
        $this->line('Optimizing Redis cache...');

        try {
            // Get Redis memory usage
            $info = Redis::info('memory');
            $memoryUsage = $info['used_memory_human'] ?? 'Unknown';

            $this->line("Redis memory usage: {$memoryUsage}");

            // Clean up expired keys
            Redis::eval("
                local keys = redis.call('keys', ARGV[1])
                local deleted = 0
                for i=1,#keys do
                    if redis.call('ttl', keys[i]) == -1 then
                        redis.call('del', keys[i])
                        deleted = deleted + 1
                    end
                end
                return deleted
            ", 0, 'laravel_cache:*');

            $this->info('Redis cache optimized');

        } catch (\Exception $e) {
            $this->error('Redis optimization failed: ' . $e->getMessage());
            Log::error('Redis cache optimization failed', ['error' => $e->getMessage()]);
        }
    }
}
```

## Log Management

### Log Rotation and Cleanup

```php
<?php
// app/Console/Commands/ManageLogs.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ManageLogs extends Command
{
    protected $signature = 'logs:manage {--cleanup : Clean up old logs} {--rotate : Rotate current logs}';
    protected $description = 'Manage application logs';

    public function handle(): void
    {
        if ($this->option('cleanup')) {
            $this->cleanupOldLogs();
        }

        if ($this->option('rotate')) {
            $this->rotateLogs();
        }

        $this->showLogStatistics();
    }

    private function cleanupOldLogs(): void
    {
        $this->info('Cleaning up old logs...');

        $logPath = storage_path('logs');
        $retentionPolicies = [
            'laravel*.log' => 30,      // 30 days
            'filament*.log' => 30,     // 30 days
            'performance*.log' => 7,   // 7 days
            'security*.log' => 365,    // 1 year
            'audit*.log' => 2555,      // 7 years
        ];

        foreach ($retentionPolicies as $pattern => $retentionDays) {
            $this->cleanupLogsByPattern($logPath, $pattern, $retentionDays);
        }
    }

    private function cleanupLogsByPattern(string $logPath, string $pattern, int $retentionDays): void
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $files = File::glob($logPath . '/' . $pattern);
        $deletedCount = 0;

        foreach ($files as $file) {
            $fileDate = Carbon::createFromTimestamp(File::lastModified($file));

            if ($fileDate->lt($cutoffDate)) {
                File::delete($file);
                $deletedCount++;
            }
        }

        $this->line("Cleaned up {$deletedCount} files matching {$pattern}");
    }

    private function rotateLogs(): void
    {
        $this->info('Rotating current logs...');

        $logPath = storage_path('logs');
        $currentLogs = [
            'laravel.log',
            'filament.log',
            'performance.log',
            'security.log',
        ];

        foreach ($currentLogs as $logFile) {
            $fullPath = $logPath . '/' . $logFile;

            if (File::exists($fullPath) && File::size($fullPath) > 0) {
                $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
                $rotatedName = pathinfo($logFile, PATHINFO_FILENAME) . "_{$timestamp}.log";

                File::move($fullPath, $logPath . '/' . $rotatedName);
                File::put($fullPath, ''); // Create empty log file

                $this->line("Rotated {$logFile} to {$rotatedName}");
            }
        }
    }

    private function showLogStatistics(): void
    {
        $this->info('Log statistics:');

        $logPath = storage_path('logs');
        $files = File::allFiles($logPath);

        $totalSize = 0;
        $fileCount = 0;

        foreach ($files as $file) {
            $totalSize += $file->getSize();
            $fileCount++;
        }

        $this->table(['Metric', 'Value'], [
            ['Total files', $fileCount],
            ['Total size', $this->formatBytes($totalSize)],
            ['Average file size', $fileCount > 0 ? $this->formatBytes($totalSize / $fileCount) : '0 B'],
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
```

## Performance Optimization

### Regular Performance Maintenance

Implement routine performance optimization procedures:

```bash
#!/bin/bash
# scripts/performance-optimization.sh

echo "Starting performance optimization..."

# Clear and warm up caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize

# Database optimization
php artisan db:optimize

# Queue optimization
php artisan queue:restart

echo "Performance optimization completed."
```

### Database Performance Tuning

Regular database maintenance for optimal performance:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeDatabase extends Command
{
    protected $signature = 'db:optimize';

    public function handle(): void
    {
        $this->info('Optimizing database...');

        // Analyze tables
        $this->analyzeAllTables();

        // Update statistics
        $this->updateTableStatistics();

        // Optimize tables
        $this->optimizeAllTables();

        $this->info('Database optimization completed.');
    }

    private function analyzeAllTables(): void
    {
        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName = array_values((array) $table)[0];
            DB::statement("ANALYZE TABLE {$tableName}");
        }
    }
}
```

## Security Maintenance

### Security Updates and Patches

Regular security maintenance procedures:

```bash
#!/bin/bash
# scripts/security-maintenance.sh

echo "Starting security maintenance..."

# Update system packages
sudo apt update && sudo apt upgrade -y

# Update PHP dependencies
composer update --with-dependencies

# Update Node.js dependencies
npm audit fix

# Check for security vulnerabilities
composer audit
npm audit

# Update SSL certificates
sudo certbot renew --quiet

echo "Security maintenance completed."
```

### Security Monitoring

Implement continuous security monitoring:

```php
<?php

namespace App\Console\Commands;

class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    public function handle(): void
    {
        $this->checkFilePermissions();
        $this->checkSuspiciousActivity();
        $this->checkFailedLogins();
        $this->checkSystemIntegrity();
    }

    private function checkFilePermissions(): void
    {
        $criticalFiles = [
            '.env',
            'config/',
            'storage/',
        ];

        foreach ($criticalFiles as $file) {
            $permissions = substr(sprintf('%o', fileperms($file)), -4);

            if ($permissions !== '0644' && $permissions !== '0755') {
                $this->warn("Suspicious permissions on {$file}: {$permissions}");
            }
        }
    }
}
```

## Monitoring & Health Checks

### Automated Health Monitoring

Implement comprehensive health check procedures:

```php
<?php

namespace App\Console\Commands;

class HealthCheck extends Command
{
    protected $signature = 'health:check';

    public function handle(): void
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            'mail' => $this->checkMail(),
        ];

        $this->displayHealthStatus($checks);

        if (in_array(false, $checks)) {
            $this->error('Health check failed!');
            exit(1);
        }

        $this->info('All health checks passed.');
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            $this->error("Database check failed: {$e->getMessage()}");
            return false;
        }
    }
}
```

### Performance Monitoring

Monitor system performance metrics:

```php
<?php

class PerformanceMonitor
{
    public function collectMetrics(): array
    {
        return [
            'memory_usage' => memory_get_peak_usage(true),
            'cpu_load' => sys_getloadavg(),
            'disk_usage' => $this->getDiskUsage(),
            'response_time' => $this->getAverageResponseTime(),
            'database_connections' => $this->getDatabaseConnections(),
        ];
    }

    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');

        return [
            'total' => $total,
            'free' => $free,
            'used' => $total - $free,
            'percentage' => round((($total - $free) / $total) * 100, 2),
        ];
    }
}
```

## Emergency Procedures

### Emergency Response Plan

Procedures for handling critical system failures:

```bash
#!/bin/bash
# scripts/emergency-response.sh

EMERGENCY_TYPE=$1

case $EMERGENCY_TYPE in
    "database-down")
        echo "Database emergency detected..."
        # Switch to read-only mode
        php artisan down --message="Database maintenance in progress"
        # Attempt database recovery
        systemctl restart mysql
        # Verify database connectivity
        php artisan health:check
        ;;
    "high-load")
        echo "High load emergency detected..."
        # Enable maintenance mode
        php artisan down --allow=127.0.0.1
        # Scale up resources
        docker-compose up --scale app=3
        # Clear caches
        php artisan cache:clear
        ;;
    "security-breach")
        echo "Security breach detected..."
        # Immediate lockdown
        php artisan down
        # Change all passwords
        php artisan auth:reset-all
        # Enable audit logging
        php artisan audit:enable
        ;;
esac
```

### Disaster Recovery

Implement disaster recovery procedures:

```php
<?php

namespace App\Console\Commands;

class DisasterRecovery extends Command
{
    protected $signature = 'disaster:recover {--backup-file=}';

    public function handle(): void
    {
        $this->info('Starting disaster recovery...');

        // Stop all services
        $this->stopServices();

        // Restore from backup
        $this->restoreFromBackup();

        // Verify system integrity
        $this->verifySystemIntegrity();

        // Restart services
        $this->startServices();

        $this->info('Disaster recovery completed.');
    }
}
```

## Troubleshooting

### Common Issues and Solutions

#### Application Performance Issues

```bash
# Check system resources
top
htop
iotop

# Check PHP processes
ps aux | grep php

# Check database performance
mysql -e "SHOW PROCESSLIST;"
mysql -e "SHOW ENGINE INNODB STATUS;"

# Check logs for errors
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/error.log
```

#### Database Connection Issues

```bash
# Test database connectivity
mysql -h localhost -u username -p

# Check database status
systemctl status mysql

# Check database logs
tail -f /var/log/mysql/error.log

# Restart database service
sudo systemctl restart mysql
```

#### Cache Issues

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Check Redis status
redis-cli ping

# Restart Redis
sudo systemctl restart redis
```

### Diagnostic Tools

```php
<?php

namespace App\Console\Commands;

class SystemDiagnostics extends Command
{
    protected $signature = 'system:diagnose';

    public function handle(): void
    {
        $this->info('Running system diagnostics...');

        $this->checkSystemResources();
        $this->checkApplicationHealth();
        $this->checkDatabaseHealth();
        $this->checkCacheHealth();
        $this->checkQueueHealth();

        $this->info('Diagnostics completed.');
    }

    private function checkSystemResources(): void
    {
        $load = sys_getloadavg();
        $memory = memory_get_peak_usage(true);

        $this->table(['Resource', 'Value'], [
            ['CPU Load (1min)', $load[0]],
            ['CPU Load (5min)', $load[1]],
            ['CPU Load (15min)', $load[2]],
            ['Memory Usage', $this->formatBytes($memory)],
        ]);
    }
}
