# SQLite WAL Mode Optimization for UMS-STI

## Executive Summary
SQLite Write-Ahead Logging (WAL) mode is a critical performance optimization that enables concurrent read access while maintaining ACID compliance. For UMS-STI, this configuration supports 1000+ concurrent users with sub-100ms response times while maintaining data integrity for our user management system.

## Learning Objectives
After completing this guide, you will:
- Understand WAL mode benefits over traditional SQLite journaling
- Configure Laravel for optimal SQLite performance with WAL mode
- Implement performance pragmas for memory and I/O optimization
- Validate WAL mode operation and performance benchmarks
- Troubleshoot common SQLite configuration issues

## Prerequisite Knowledge
- Basic Laravel database configuration
- Understanding of database ACID properties
- Familiarity with Laravel migrations and Eloquent ORM
- Basic knowledge of database indexing concepts

## Architectural Overview

### Why SQLite for UMS-STI?
Based on **DECISION-006** from our decision log, SQLite was chosen over PostgreSQL/MySQL for:
- **Deployment Simplicity**: Single file database, no server management
- **Performance**: Modern SQLite with WAL mode handles 100K+ users efficiently
- **Backup Integration**: File-based backups work seamlessly with spatie/laravel-backup
- **Development Efficiency**: Same database for dev/test/production environments

### WAL Mode Architecture
```
Traditional SQLite (DELETE mode):
[Reader] ──X── [Database] ──X── [Writer]
         (blocked)      (blocked)

WAL Mode:
[Reader 1] ──✓── [Database] ──✓── [Writer]
[Reader 2] ──✓──     │      ──✓── [WAL File]
[Reader N] ──✓──     │      
                [Checkpoint Process]
```

## Core Concepts Deep Dive

### Write-Ahead Logging Explained
WAL mode changes how SQLite handles concurrent access:

1. **Writes go to WAL file**: New data is written to a separate WAL file
2. **Reads from database + WAL**: Readers see consistent view combining both
3. **Periodic checkpointing**: WAL data is merged back to main database
4. **Concurrent access**: Multiple readers can access while writer operates

### Performance Benefits
- **Concurrent Reads**: 1000+ simultaneous readers supported
- **Faster Writes**: No need to copy entire pages for small changes
- **Better Cache Utilization**: Reduced I/O operations
- **Crash Recovery**: WAL provides better recovery mechanisms

## Implementation Principles & Patterns

### Configuration Strategy
We'll implement a layered configuration approach:
1. **Laravel Database Config**: Primary connection settings
2. **Performance Pragmas**: Memory and I/O optimization
3. **Connection Validation**: Ensure WAL mode is active
4. **Monitoring Integration**: Performance metrics collection

### Security Considerations
- **File Permissions**: Ensure proper access controls on database files
- **WAL File Management**: Automatic cleanup and checkpoint management
- **Backup Coordination**: WAL-aware backup procedures

## Step-by-Step Implementation Guide

### Step 1: Update Laravel Database Configuration

Edit `config/database.php`:

```php
<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),
    
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'options' => [
                // Enable WAL mode immediately on connection
                PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READWRITE | PDO::SQLITE_OPEN_CREATE,
            ],
            // Custom options for UMS-STI optimization
            'pragmas' => [
                'journal_mode' => 'WAL',
                'synchronous' => 'NORMAL',
                'cache_size' => -64000,  // 64MB cache
                'temp_store' => 'MEMORY',
                'mmap_size' => 268435456, // 256MB memory mapping
                'foreign_keys' => 'ON',
            ],
        ],
    ],
];
```

### Step 2: Create SQLite Configuration Service Provider

Create `app/Providers/SqliteServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class SqliteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Configure SQLite when connection is established
        Event::listen(ConnectionEstablished::class, function ($event) {
            if ($event->connection->getDriverName() === 'sqlite') {
                $this->configureSqliteConnection($event->connection);
            }
        });
    }

    private function configureSqliteConnection($connection): void
    {
        $pragmas = config('database.connections.sqlite.pragmas', []);
        
        foreach ($pragmas as $pragma => $value) {
            $connection->statement("PRAGMA {$pragma} = {$value}");
        }
        
        // Validate WAL mode is active
        $result = $connection->selectOne('PRAGMA journal_mode');
        if (strtoupper($result->journal_mode) !== 'WAL') {
            Log::warning('SQLite WAL mode not activated', [
                'current_mode' => $result->journal_mode,
                'expected' => 'WAL'
            ]);
        }
        
        Log::info('SQLite connection configured', [
            'journal_mode' => $result->journal_mode,
            'pragmas_applied' => count($pragmas)
        ]);
    }
}
```

### Step 3: Register the Service Provider

Add to `bootstrap/providers.php` (Laravel 12.x pattern):

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\SqliteServiceProvider::class,
];
```

**Note**: Laravel 12.x moved service provider registration from `config/app.php` to `bootstrap/providers.php` for improved performance and clarity.

### Step 4: Create Database Connection Test

Create `app/Console/Commands/TestDatabaseConnection.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDatabaseConnection extends Command
{
    protected $signature = 'db:test-connection';
    protected $description = 'Test SQLite WAL configuration and performance';

    public function handle(): int
    {
        $this->info('Testing SQLite WAL Configuration...');
        
        try {
            // Test basic connection
            $this->testBasicConnection();
            
            // Validate WAL mode
            $this->validateWalMode();
            
            // Test performance pragmas
            $this->testPerformancePragmas();
            
            // Test concurrent access
            $this->testConcurrentAccess();
            
            $this->info('✅ All SQLite tests passed!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ SQLite test failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function testBasicConnection(): void
    {
        $result = DB::selectOne('SELECT 1 as test');
        if ($result->test !== 1) {
            throw new \Exception('Basic connection test failed');
        }
        $this->line('✓ Basic connection working');
    }

    private function validateWalMode(): void
    {
        $result = DB::selectOne('PRAGMA journal_mode');
        if (strtoupper($result->journal_mode) !== 'WAL') {
            throw new \Exception("WAL mode not active. Current: {$result->journal_mode}");
        }
        $this->line('✓ WAL mode active');
    }

    private function testPerformancePragmas(): void
    {
        $pragmas = [
            'cache_size' => -64000,
            'temp_store' => 2, // MEMORY
            'synchronous' => 1, // NORMAL
            'mmap_size' => 268435456,
        ];

        foreach ($pragmas as $pragma => $expected) {
            $result = DB::selectOne("PRAGMA {$pragma}");
            $actual = $result->{$pragma};
            
            if ($actual != $expected) {
                throw new \Exception("Pragma {$pragma} mismatch. Expected: {$expected}, Actual: {$actual}");
            }
        }
        $this->line('✓ Performance pragmas configured');
    }

    private function testConcurrentAccess(): void
    {
        // Create test table
        DB::statement('CREATE TEMPORARY TABLE wal_test (id INTEGER, value TEXT)');
        
        // Test concurrent reads (simulated)
        for ($i = 0; $i < 10; $i++) {
            DB::insert('INSERT INTO wal_test (id, value) VALUES (?, ?)', [$i, "test_{$i}"]);
        }
        
        $count = DB::selectOne('SELECT COUNT(*) as count FROM wal_test')->count;
        if ($count !== 10) {
            throw new \Exception("Concurrent access test failed. Expected 10 records, got {$count}");
        }
        
        $this->line('✓ Concurrent access working');
    }
}
```

### Step 5: Create SQLite Optimization Migration

Create `database/migrations/001_optimize_sqlite_configuration.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Apply SQLite optimizations via migration
        $pragmas = [
            'journal_mode = WAL',
            'synchronous = NORMAL',
            'cache_size = -64000',
            'temp_store = MEMORY',
            'mmap_size = 268435456',
            'foreign_keys = ON',
        ];

        foreach ($pragmas as $pragma) {
            DB::statement("PRAGMA {$pragma}");
        }
    }

    public function down(): void
    {
        // Reset to default SQLite settings
        DB::statement('PRAGMA journal_mode = DELETE');
        DB::statement('PRAGMA synchronous = FULL');
        DB::statement('PRAGMA cache_size = -2000');
        DB::statement('PRAGMA temp_store = DEFAULT');
        DB::statement('PRAGMA mmap_size = 0');
    }
};
```

### Step 6: Environment Configuration

Update `.env` file:

```env
# Database Configuration (Laravel 12.x)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
DB_FOREIGN_KEYS=true

# SQLite Performance Settings
SQLITE_CACHE_SIZE=64000
SQLITE_MMAP_SIZE=268435456
SQLITE_WAL_AUTOCHECKPOINT=1000
```

## Code Examples & Snippets

### WAL Checkpoint Management

Create a scheduled command for WAL maintenance:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SqliteCheckpoint extends Command
{
    protected $signature = 'sqlite:checkpoint {--mode=PASSIVE}';
    protected $description = 'Perform SQLite WAL checkpoint';

    public function handle(): int
    {
        $mode = $this->option('mode');
        
        $result = DB::selectOne("PRAGMA wal_checkpoint({$mode})");
        
        $this->info("WAL Checkpoint completed:");
        $this->line("Mode: {$mode}");
        $this->line("Busy: {$result->busy}");
        $this->line("Log: {$result->log}");
        $this->line("Checkpointed: {$result->checkpointed}");
        
        return 0;
    }
}
```

### Performance Monitoring

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SqliteMonitoringService
{
    public function getPerformanceMetrics(): array
    {
        return [
            'journal_mode' => $this->getJournalMode(),
            'wal_size' => $this->getWalSize(),
            'cache_stats' => $this->getCacheStats(),
            'connection_count' => $this->getConnectionCount(),
        ];
    }

    private function getJournalMode(): string
    {
        return DB::selectOne('PRAGMA journal_mode')->journal_mode;
    }

    private function getWalSize(): int
    {
        $dbPath = config('database.connections.sqlite.database');
        $walPath = $dbPath . '-wal';
        
        return file_exists($walPath) ? filesize($walPath) : 0;
    }

    private function getCacheStats(): array
    {
        $cacheSize = DB::selectOne('PRAGMA cache_size')->cache_size;
        $pageCount = DB::selectOne('PRAGMA page_count')->page_count;
        
        return [
            'cache_size' => $cacheSize,
            'page_count' => $pageCount,
            'cache_utilization' => $pageCount > 0 ? ($cacheSize / $pageCount) * 100 : 0,
        ];
    }

    private function getConnectionCount(): int
    {
        // This is a simplified metric - in production you'd track this differently
        return 1; // SQLite doesn't have built-in connection counting
    }
}
```

## Testing & Validation

### Unit Test for SQLite Configuration

Create `tests/Unit/Database/SqliteConfigurationTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqliteConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wal_mode_is_active(): void
    {
        $result = DB::selectOne('PRAGMA journal_mode');
        $this->assertEquals('wal', strtolower($result->journal_mode));
    }

    public function test_performance_pragmas_are_set(): void
    {
        $pragmas = [
            'cache_size' => -64000,
            'temp_store' => 2,
            'synchronous' => 1,
        ];

        foreach ($pragmas as $pragma => $expected) {
            $result = DB::selectOne("PRAGMA {$pragma}");
            $this->assertEquals($expected, $result->{$pragma}, "Pragma {$pragma} not set correctly");
        }
    }

    public function test_foreign_keys_are_enabled(): void
    {
        $result = DB::selectOne('PRAGMA foreign_keys');
        $this->assertEquals(1, $result->foreign_keys);
    }

    public function test_sqlite_version_compatibility(): void
    {
        $result = DB::selectOne('SELECT sqlite_version() as version');
        $version = $result->version;

        // Ensure SQLite version supports WAL mode (3.7.0+)
        $this->assertGreaterThanOrEqual('3.7.0', $version, 'SQLite version must support WAL mode');
    }
}
```

### Performance Benchmark Test (Laravel 12.x with Pest)

Create `tests/Performance/SqlitePerformanceTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('concurrent read performance meets requirements', function () {
    // Create test data
    DB::statement('CREATE TEMPORARY TABLE perf_test (id INTEGER PRIMARY KEY, data TEXT)');

    for ($i = 0; $i < 1000; $i++) {
        DB::insert('INSERT INTO perf_test (data) VALUES (?)', ["test_data_{$i}"]);
    }

    // Measure read performance
    $startTime = microtime(true);

    for ($i = 0; $i < 100; $i++) {
        DB::select('SELECT * FROM perf_test WHERE id = ?', [rand(1, 1000)]);
    }

    $endTime = microtime(true);
    $averageTime = (($endTime - $startTime) / 100) * 1000; // Convert to milliseconds

    // Should be well under 10ms per query
    expect($averageTime)->toBeLessThan(10, 'SQLite queries should be under 10ms average');
});

test('wal checkpoint performance', function () {
    $startTime = microtime(true);

    // Perform WAL checkpoint
    DB::statement('PRAGMA wal_checkpoint(PASSIVE)');

    $endTime = microtime(true);
    $checkpointTime = ($endTime - $startTime) * 1000;

    // Checkpoint should complete quickly
    expect($checkpointTime)->toBeLessThan(100, 'WAL checkpoint should complete under 100ms');
});
```

## Common Pitfalls & Troubleshooting

### Issue 1: WAL Mode Not Activating
**Symptoms**: `PRAGMA journal_mode` returns 'delete' instead of 'wal'
**Causes**: 
- File permissions issues
- SQLite version too old
- Database file on network drive

**Solutions**:
```bash
# Check SQLite version (need 3.7.0+)
sqlite3 --version

# Check file permissions
ls -la database/database.sqlite*

# Fix permissions
chmod 664 database/database.sqlite
chown www-data:www-data database/database.sqlite
```

### Issue 2: WAL File Growing Too Large
**Symptoms**: `.sqlite-wal` file becomes very large
**Cause**: Checkpoints not running frequently enough

**Solution**: Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('sqlite:checkpoint')->hourly();
}
```

### Issue 3: "Database is locked" Errors
**Symptoms**: Intermittent database lock errors
**Causes**: 
- Long-running transactions
- Improper connection handling

**Solutions**:
```php
// Use database transactions properly
DB::transaction(function () {
    // Keep transactions short
});

// Set busy timeout
DB::statement('PRAGMA busy_timeout = 30000'); // 30 seconds
```

## Best Practices & Optimization

### 1. WAL File Management
- Monitor WAL file size regularly
- Schedule periodic checkpoints
- Consider `PRAGMA wal_autocheckpoint` for automatic management

### 2. Backup Considerations
```php
// WAL-aware backup
public function backupDatabase(): void
{
    // Checkpoint before backup
    DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
    
    // Now backup the main database file
    copy(database_path('database.sqlite'), $backupPath);
}
```

### 3. Performance Monitoring
```php
// Add to monitoring dashboard
public function getDatabaseMetrics(): array
{
    return [
        'wal_size' => filesize(database_path('database.sqlite-wal')),
        'db_size' => filesize(database_path('database.sqlite')),
        'cache_hit_ratio' => $this->calculateCacheHitRatio(),
    ];
}
```

## Integration Points

### Connection to Other UMS-STI Components
- **User Models (Task 2.0)**: Optimized for STI queries with proper indexing
- **Team Hierarchy (Task 3.0)**: Closure table queries benefit from WAL concurrent reads
- **Permission Caching (Task 4.0)**: Reduced database load through Redis caching
- **GDPR Compliance (Task 5.0)**: WAL-aware backup procedures

### Laravel Integration
- Works seamlessly with Eloquent ORM
- Compatible with Laravel's database testing features
- Integrates with Laravel's queue system for background jobs

## Further Reading & Resources

### Official Documentation
- [SQLite WAL Mode Documentation](https://www.sqlite.org/wal.html)
- [Laravel Database Configuration](https://laravel.com/docs/database)
- [SQLite Performance Tuning](https://www.sqlite.org/optoverview.html)

### Advanced Topics
- SQLite FTS5 for full-text search
- SQLite R-Tree for spatial indexing
- Custom SQLite functions in Laravel
- Database sharding strategies with SQLite

### Performance Resources
- [SQLite Benchmarking Tools](https://www.sqlite.org/speed.html)
- [WAL Mode Performance Analysis](https://www.sqlite.org/walformat.html)
- [Laravel Database Performance](https://laravel.com/docs/database#database-performance)

## References and Citations

### Primary Sources
- [Laravel 12.x Database Configuration](https://laravel.com/docs/12.x/database)
- [Laravel 12.x Service Providers](https://laravel.com/docs/12.x/providers)
- [SQLite WAL Mode Documentation](https://www.sqlite.org/wal.html)
- [SQLite PRAGMA Statements](https://www.sqlite.org/pragma.html)

### Secondary Sources
- [SQLite Performance Tuning Guide](https://www.sqlite.org/optoverview.html)
- [Laravel Performance Best Practices](https://laravel.com/docs/12.x/deployment#optimization)
- [Database Performance Optimization](https://use-the-index-luke.com/)

### Related UMS-STI Documentation
- [Laravel Package Ecosystem](02-laravel-package-ecosystem.md) - Next implementation step
- [Migration Strategy STI](03-migration-strategy-sti.md) - Database schema design
- [Unit Testing Strategies](../08-testing-suite/01-unit-testing-strategies.md) - Testing patterns
- [PRD Requirements](../../prd-UMS-STI.md) - Business requirements context
- [Decision Log](../../decision-log-UMS-STI.md) - Architectural decisions (DECISION-006)

### Laravel 12.x Compatibility Notes
- Service provider registration moved from `config/app.php` to `bootstrap/providers.php`
- Enhanced database configuration options for SQLite optimization
- Improved testing utilities with Pest PHP integration
- Updated migration patterns for database optimization

---

**Next Steps**: Proceed to [Laravel Package Ecosystem](02-laravel-package-ecosystem.md) to install and configure the required packages for UMS-STI.
