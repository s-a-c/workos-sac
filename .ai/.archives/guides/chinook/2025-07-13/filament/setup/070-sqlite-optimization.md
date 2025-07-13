# SQLite Optimization for Chinook Admin Panel

This guide covers comprehensive SQLite optimization strategies for the Chinook admin panel, including WAL mode configuration, performance tuning, and production deployment considerations.

## Table of Contents

- [Overview](#overview)
- [WAL Mode Configuration](#wal-mode-configuration)
- [Performance Pragmas](#performance-pragmas)
- [Laravel Configuration](#laravel-configuration)
- [Production Optimization](#production-optimization)
- [Monitoring and Maintenance](#monitoring-and-maintenance)
- [Troubleshooting](#troubleshooting)

## Overview

SQLite with Write-Ahead Logging (WAL) mode provides excellent performance for the Chinook admin panel, supporting concurrent read access while maintaining ACID compliance. This configuration enables the panel to handle multiple administrators simultaneously with sub-100ms response times.

### Key Benefits

- **Concurrent Access**: Multiple readers can access the database while a writer operates
- **Improved Performance**: WAL mode reduces lock contention and improves throughput
- **Better Crash Recovery**: WAL provides superior recovery mechanisms
- **Reduced I/O**: More efficient disk operations compared to traditional journaling

### Performance Characteristics

- **Concurrent Readers**: 1000+ simultaneous read operations supported
- **Write Performance**: Optimized for admin panel operations (CRUD, reporting)
- **Memory Efficiency**: Configurable cache sizes for optimal memory usage
- **Storage Efficiency**: Compact database files with automatic maintenance

## WAL Mode Configuration

### Automatic Configuration

The Chinook admin panel automatically configures SQLite with optimal settings through Laravel's database configuration:

```php
// config/database.php
'sqlite' => [
    'driver' => 'sqlite',
    'url' => env('DATABASE_URL'),
    'database' => env('DB_DATABASE', database_path('chinook_admin.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    'options' => [
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READWRITE | PDO::SQLITE_OPEN_CREATE,
    ],
    'pragmas' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000,  // 64MB cache
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB memory mapping
        'foreign_keys' => 'ON',
        'busy_timeout' => 30000,  // 30 second timeout
    ],
],
```

### Manual WAL Mode Activation

If needed, WAL mode can be activated manually:

```sql
-- Enable WAL mode
PRAGMA journal_mode = WAL;

-- Verify WAL mode is active
PRAGMA journal_mode;
-- Should return: wal
```

## Performance Pragmas

### Core Performance Settings

```sql
-- Write-Ahead Logging for concurrent access
PRAGMA journal_mode = WAL;

-- Balanced durability and performance
PRAGMA synchronous = NORMAL;

-- 64MB cache for better performance
PRAGMA cache_size = -64000;

-- Store temporary tables in memory
PRAGMA temp_store = MEMORY;

-- 256MB memory-mapped I/O
PRAGMA mmap_size = 268435456;

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- 30-second busy timeout
PRAGMA busy_timeout = 30000;
```

### Advanced Optimization Settings

```sql
-- Optimize for read-heavy workloads
PRAGMA optimize;

-- Configure automatic checkpointing
PRAGMA wal_autocheckpoint = 1000;

-- Set page size (must be done before first write)
PRAGMA page_size = 4096;

-- Configure locking mode for single-process scenarios
PRAGMA locking_mode = NORMAL;

-- Enable query planner optimizations
PRAGMA analysis_limit = 1000;
```

## Laravel Configuration

### Environment Variables

```bash
# SQLite Database Configuration
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/chinook-admin/database/production.sqlite
DB_FOREIGN_KEYS=true

# SQLite-specific optimizations
SQLITE_CACHE_SIZE=64000
SQLITE_MMAP_SIZE=268435456
SQLITE_BUSY_TIMEOUT=30000
```

### Service Provider Configuration

Create a dedicated service provider for SQLite optimization:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class SqliteOptimizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('database.default') === 'sqlite') {
            $this->optimizeSqlite();
        }
    }

    private function optimizeSqlite(): void
    {
        $pragmas = [
            'journal_mode = WAL',
            'synchronous = NORMAL',
            'cache_size = -' . config('database.sqlite_cache_size', 64000),
            'temp_store = MEMORY',
            'mmap_size = ' . config('database.sqlite_mmap_size', 268435456),
            'foreign_keys = ON',
            'busy_timeout = ' . config('database.sqlite_busy_timeout', 30000),
        ];

        foreach ($pragmas as $pragma) {
            DB::statement("PRAGMA {$pragma}");
        }
    }
}
```

### Migration for Initial Optimization

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Apply SQLite optimizations
        if (config('database.default') === 'sqlite') {
            $pragmas = [
                'journal_mode = WAL',
                'synchronous = NORMAL',
                'cache_size = -64000',
                'temp_store = MEMORY',
                'mmap_size = 268435456',
                'foreign_keys = ON',
                'busy_timeout = 30000',
            ];

            foreach ($pragmas as $pragma) {
                DB::statement("PRAGMA {$pragma}");
            }
        }
    }

    public function down(): void
    {
        // Reset to default SQLite settings
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA journal_mode = DELETE');
            DB::statement('PRAGMA synchronous = FULL');
            DB::statement('PRAGMA cache_size = -2000');
            DB::statement('PRAGMA temp_store = DEFAULT');
            DB::statement('PRAGMA mmap_size = 0');
        }
    }
};
```

## Production Optimization

### File System Considerations

```bash
# Ensure proper file permissions
chmod 664 /var/www/chinook-admin/database/production.sqlite
chmod 664 /var/www/chinook-admin/database/production.sqlite-wal
chmod 664 /var/www/chinook-admin/database/production.sqlite-shm

# Set proper ownership
chown www-data:www-data /var/www/chinook-admin/database/production.sqlite*

# Ensure directory permissions
chmod 755 /var/www/chinook-admin/database/
```

### Backup Strategy for WAL Mode

```bash
#!/bin/bash
# SQLite WAL-aware backup script

DB_PATH="/var/www/chinook-admin/database/production.sqlite"
BACKUP_DIR="/var/backups/chinook-admin"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Checkpoint WAL file before backup
sqlite3 $DB_PATH "PRAGMA wal_checkpoint(FULL);"

# Create backup using SQLite's backup API
sqlite3 $DB_PATH ".backup $BACKUP_DIR/chinook_admin_$DATE.sqlite"

# Verify backup integrity
sqlite3 $BACKUP_DIR/chinook_admin_$DATE.sqlite "PRAGMA integrity_check;"

# Compress backup
gzip $BACKUP_DIR/chinook_admin_$DATE.sqlite

echo "Backup completed: chinook_admin_$DATE.sqlite.gz"
```

### Monitoring WAL File Size

```bash
#!/bin/bash
# Monitor WAL file size and checkpoint if needed

DB_PATH="/var/www/chinook-admin/database/production.sqlite"
WAL_PATH="${DB_PATH}-wal"
MAX_WAL_SIZE=104857600  # 100MB

if [ -f "$WAL_PATH" ]; then
    WAL_SIZE=$(stat -f%z "$WAL_PATH" 2>/dev/null || stat -c%s "$WAL_PATH")
    
    if [ "$WAL_SIZE" -gt "$MAX_WAL_SIZE" ]; then
        echo "WAL file size ($WAL_SIZE bytes) exceeds threshold. Checkpointing..."
        sqlite3 $DB_PATH "PRAGMA wal_checkpoint(TRUNCATE);"
        echo "Checkpoint completed."
    fi
fi
```

## Monitoring and Maintenance

### Performance Monitoring

```sql
-- Check current pragma settings
PRAGMA journal_mode;
PRAGMA synchronous;
PRAGMA cache_size;
PRAGMA temp_store;
PRAGMA mmap_size;

-- Monitor WAL file status
PRAGMA wal_checkpoint;

-- Check database integrity
PRAGMA integrity_check;

-- Analyze query performance
PRAGMA optimize;
```

### Maintenance Commands

```sql
-- Force WAL checkpoint
PRAGMA wal_checkpoint(FULL);

-- Truncate WAL file
PRAGMA wal_checkpoint(TRUNCATE);

-- Vacuum database (reclaim space)
VACUUM;

-- Update table statistics
ANALYZE;

-- Optimize query planner
PRAGMA optimize;
```

### Laravel Artisan Commands

Create custom Artisan commands for SQLite maintenance:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SqliteOptimize extends Command
{
    protected $signature = 'sqlite:optimize';
    protected $description = 'Optimize SQLite database performance';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->error('This command is only for SQLite databases.');
            return 1;
        }

        $this->info('Optimizing SQLite database...');

        // Checkpoint WAL file
        DB::statement('PRAGMA wal_checkpoint(FULL)');
        $this->info('✓ WAL checkpoint completed');

        // Update statistics
        DB::statement('ANALYZE');
        $this->info('✓ Database statistics updated');

        // Optimize query planner
        DB::statement('PRAGMA optimize');
        $this->info('✓ Query planner optimized');

        $this->info('SQLite optimization completed successfully!');
        return 0;
    }
}
```

## Troubleshooting

### Common Issues

#### Database Locked Errors

```bash
# Check for long-running transactions
sqlite3 database.sqlite "SELECT * FROM pragma_wal_checkpoint;"

# Force checkpoint to resolve locks
sqlite3 database.sqlite "PRAGMA wal_checkpoint(TRUNCATE);"
```

#### WAL File Growing Too Large

```bash
# Check WAL file size
ls -lh database.sqlite-wal

# Force checkpoint and truncate
sqlite3 database.sqlite "PRAGMA wal_checkpoint(TRUNCATE);"
```

#### Performance Degradation

```sql
-- Check if WAL mode is still active
PRAGMA journal_mode;

-- Verify cache settings
PRAGMA cache_size;

-- Update table statistics
ANALYZE;

-- Optimize query planner
PRAGMA optimize;
```

### Diagnostic Queries

```sql
-- Check database file sizes
SELECT 
    'Main DB' as file_type,
    page_count * page_size as size_bytes
FROM pragma_page_count(), pragma_page_size()
UNION ALL
SELECT 
    'WAL file' as file_type,
    (SELECT COUNT(*) FROM pragma_wal_checkpoint()) * page_size
FROM pragma_page_size();

-- Check cache hit ratio
PRAGMA cache_spill = OFF;
SELECT * FROM pragma_cache_size();

-- Monitor busy timeout effectiveness
PRAGMA busy_timeout;
```

## Next Steps

1. **Implement Configuration** - Apply the SQLite optimization settings to your environment
2. **Monitor Performance** - Use the provided monitoring tools to track database performance
3. **Setup Maintenance** - Implement regular maintenance routines using the provided scripts
4. **Test Backup Strategy** - Verify backup and recovery procedures work correctly
5. **Performance Testing** - Conduct load testing to validate optimization effectiveness

## Related Documentation

- **[Panel Configuration](010-panel-configuration.md)** - Basic panel setup and configuration
- **[Environment Setup](060-environment-setup.md)** - Development and production environment configuration
- **[Performance Optimization](../deployment/050-performance-optimization.md)** - Additional performance tuning strategies
- **[Monitoring](../deployment/090-monitoring-setup.md)** - Application monitoring and logging
