# Filament Monitoring Setup Guide

## Overview

This guide covers comprehensive monitoring setup for the Chinook Filament admin panel, including application performance monitoring, error tracking, health checks, and alerting systems.

## Table of Contents

- [Overview](#overview)
- [Application Performance Monitoring](#application-performance-monitoring)
- [Error Tracking & Logging](#error-tracking--logging)
- [Health Checks & Uptime Monitoring](#health-checks--uptime-monitoring)
- [Database Monitoring](#database-monitoring)
- [Queue Monitoring](#queue-monitoring)
- [Security Monitoring](#security-monitoring)
- [Alerting & Notifications](#alerting--notifications)
- [Dashboard Setup](#dashboard-setup)
- [Troubleshooting](#troubleshooting)

## Application Performance Monitoring

### Laravel Pulse Integration

```php
<?php
// config/pulse.php

return [
    'domain' => env('PULSE_DOMAIN'),
    'path' => env('PULSE_PATH', 'pulse'),
    'enabled' => env('PULSE_ENABLED', true),
    
    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),
        'database' => [
            'connection' => env('PULSE_DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],
    
    'cache' => env('PULSE_CACHE_DRIVER', 'redis'),
    
    'recorders' => [
        Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_INTERACTIONS_ENABLED', true),
            'sample_rate' => env('PULSE_CACHE_INTERACTIONS_SAMPLE_RATE', 1),
        ],
        
        Recorders\Exceptions::class => [
            'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
            'sample_rate' => env('PULSE_EXCEPTIONS_SAMPLE_RATE', 1),
            'capture_stacktrace' => true,
        ],
        
        Recorders\Queues::class => [
            'enabled' => env('PULSE_QUEUES_ENABLED', true),
            'sample_rate' => env('PULSE_QUEUES_SAMPLE_RATE', 1),
        ],
        
        Recorders\SlowQueries::class => [
            'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_QUERIES_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 1000),
        ],
        
        Recorders\SlowRequests::class => [
            'enabled' => env('PULSE_SLOW_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_REQUESTS_THRESHOLD', 1000),
        ],
        
        Recorders\UserRequests::class => [
            'enabled' => env('PULSE_USER_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_USER_REQUESTS_SAMPLE_RATE', 1),
        ],
    ],
];
```

### Custom Filament Metrics

```php
<?php
// app/Filament/Widgets/AdminMetricsWidget.php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\{DB, Cache};

class AdminMetricsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static bool $isLazy = false;
    
    protected function getStats(): array
    {
        return [
            Stat::make('Active Users', $this->getActiveUsers())
                ->description('Users active in last 24h')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart($this->getUserActivityChart()),
                
            Stat::make('Response Time', $this->getAverageResponseTime())
                ->description('Average response time')
                ->descriptionIcon('heroicon-m-clock')
                ->color($this->getResponseTimeColor())
                ->chart($this->getResponseTimeChart()),
                
            Stat::make('Error Rate', $this->getErrorRate())
                ->description('Errors in last hour')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($this->getErrorRateColor())
                ->chart($this->getErrorChart()),
                
            Stat::make('Queue Jobs', $this->getQueueJobsCount())
                ->description('Pending queue jobs')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($this->getQueueColor())
                ->chart($this->getQueueChart()),
        ];
    }
    
    private function getActiveUsers(): int
    {
        return Cache::remember('active_users_24h', 300, function () {
            return DB::table('sessions')
                ->where('last_activity', '>=', now()->subDay()->timestamp)
                ->distinct('user_id')
                ->whereNotNull('user_id')
                ->count();
        });
    }
    
    private function getAverageResponseTime(): string
    {
        $avgTime = Cache::remember('avg_response_time', 60, function () {
            return DB::table('pulse_entries')
                ->where('type', 'slow_request')
                ->where('timestamp', '>=', now()->subHour())
                ->avg('value') ?? 0;
        });
        
        return number_format($avgTime, 0) . 'ms';
    }
    
    private function getErrorRate(): string
    {
        $errors = Cache::remember('error_count_1h', 60, function () {
            return DB::table('pulse_entries')
                ->where('type', 'exception')
                ->where('timestamp', '>=', now()->subHour())
                ->count();
        });
        
        return $errors . ' errors';
    }
    
    private function getQueueJobsCount(): int
    {
        return Cache::remember('queue_jobs_count', 30, function () {
            return DB::table('jobs')->count() + 
                   DB::table('failed_jobs')->count();
        });
    }
    
    private function getResponseTimeColor(): string
    {
        $avgTime = (float) str_replace('ms', '', $this->getAverageResponseTime());
        
        return match (true) {
            $avgTime < 200 => 'success',
            $avgTime < 500 => 'warning',
            default => 'danger',
        };
    }
    
    private function getErrorRateColor(): string
    {
        $errors = (int) str_replace(' errors', '', $this->getErrorRate());
        
        return match (true) {
            $errors === 0 => 'success',
            $errors < 5 => 'warning',
            default => 'danger',
        };
    }
    
    private function getQueueColor(): string
    {
        $jobs = $this->getQueueJobsCount();
        
        return match (true) {
            $jobs < 10 => 'success',
            $jobs < 50 => 'warning',
            default => 'danger',
        };
    }
    
    private function getUserActivityChart(): array
    {
        return Cache::remember('user_activity_chart', 300, function () {
            $data = [];
            for ($i = 23; $i >= 0; $i--) {
                $hour = now()->subHours($i);
                $count = DB::table('sessions')
                    ->where('last_activity', '>=', $hour->timestamp)
                    ->where('last_activity', '<', $hour->addHour()->timestamp)
                    ->distinct('user_id')
                    ->whereNotNull('user_id')
                    ->count();
                $data[] = $count;
            }
            return $data;
        });
    }
    
    private function getResponseTimeChart(): array
    {
        return Cache::remember('response_time_chart', 300, function () {
            $data = [];
            for ($i = 11; $i >= 0; $i--) {
                $time = now()->subMinutes($i * 5);
                $avgTime = DB::table('pulse_entries')
                    ->where('type', 'slow_request')
                    ->where('timestamp', '>=', $time->timestamp)
                    ->where('timestamp', '<', $time->addMinutes(5)->timestamp)
                    ->avg('value') ?? 0;
                $data[] = (int) $avgTime;
            }
            return $data;
        });
    }
    
    private function getErrorChart(): array
    {
        return Cache::remember('error_chart', 300, function () {
            $data = [];
            for ($i = 11; $i >= 0; $i--) {
                $time = now()->subMinutes($i * 5);
                $count = DB::table('pulse_entries')
                    ->where('type', 'exception')
                    ->where('timestamp', '>=', $time->timestamp)
                    ->where('timestamp', '<', $time->addMinutes(5)->timestamp)
                    ->count();
                $data[] = $count;
            }
            return $data;
        });
    }
    
    private function getQueueChart(): array
    {
        return Cache::remember('queue_chart', 300, function () {
            $data = [];
            for ($i = 11; $i >= 0; $i--) {
                $time = now()->subMinutes($i * 5);
                $count = DB::table('pulse_entries')
                    ->where('type', 'queue')
                    ->where('timestamp', '>=', $time->timestamp)
                    ->where('timestamp', '<', $time->addMinutes(5)->timestamp)
                    ->count();
                $data[] = $count;
            }
            return $data;
        });
    }
}
```

## Error Tracking & Logging

### Sentry Integration

```php
<?php
// config/sentry.php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
    
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => true,
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES', false),
        'sql_bindings' => env('SENTRY_TRACE_SQL_BINDINGS', false),
        'queue_info' => true,
        'command_info' => true,
    ],
    
    'tracing' => [
        'enabled' => env('SENTRY_TRACES_ENABLED', false),
        'sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => true,
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES', false),
        'redis_commands' => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS', false),
    ],
    
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),
    
    'profiles' => [
        'sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),
    ],
];
```

### Custom Error Handler

```php
<?php
// app/Exceptions/Handler.php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sentry\Laravel\Integration;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Integration::captureUnhandledException($e);
        });
    }
    
    public function report(Throwable $exception): void
    {
        // Custom logging for Filament-specific errors
        if ($this->isFilamentError($exception)) {
            Log::channel('filament')->error('Filament Error', [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'user_id' => auth()->id(),
                'url' => request()->url(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ]);
        }
        
        parent::report($exception);
    }
    
    private function isFilamentError(Throwable $exception): bool
    {
        return str_contains($exception->getFile(), 'filament') ||
               str_contains($exception->getFile(), 'Filament') ||
               str_contains(request()->path(), 'admin');
    }
}
```

## Health Checks & Uptime Monitoring

### Laravel Health Check

```php
<?php
// config/health.php

use Spatie\Health\Checks;

return [
    'oh_dear_endpoint' => [
        'enabled' => env('OH_DEAR_HEALTH_CHECK_ENABLED', false),
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),
        'url' => env('OH_DEAR_HEALTH_CHECK_URL', '/oh-dear-health-check-results'),
    ],

    'result_stores' => [
        Spatie\Health\ResultStores\EloquentHealthResultStore::class => [
            'keep_history_for_days' => 5,
        ],
        
        Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => null,
        ],
        
        Spatie\Health\ResultStores\JsonFileHealthResultStore::class => [
            'disk' => 'local',
            'path' => 'health.json',
        ],
    ],

    'checks' => [
        Checks\OptimizedAppCheck::class,
        Checks\DebugModeCheck::class,
        Checks\EnvironmentCheck::class => [
            'expected_environment' => env('EXPECTED_ENV', 'production'),
        ],
        Checks\DatabaseCheck::class,
        Checks\DatabaseConnectionCountCheck::class => [
            'connection_name' => env('DB_CONNECTION', 'mysql'),
        ],
        Checks\DatabaseSizeCheck::class => [
            'connection_name' => env('DB_CONNECTION', 'mysql'),
            'max_size_mb' => 1000,
        ],
        Checks\DatabaseTableSizeCheck::class => [
            'connection_name' => env('DB_CONNECTION', 'mysql'),
            'table' => 'tracks',
            'max_size_mb' => 100,
        ],
        Checks\RedisCheck::class,
        Checks\RedisMemoryUsageCheck::class => [
            'connection_name' => 'default',
            'max_memory_usage_in_mb' => 100,
        ],
        Checks\CacheCheck::class,
        Checks\QueueCheck::class => [
            'connection' => env('QUEUE_CONNECTION', 'redis'),
        ],
        Checks\ScheduleCheck::class,
        Checks\UsedDiskSpaceCheck::class => [
            'disk_names' => ['local'],
            'warning_threshold' => 70,
            'error_threshold' => 90,
        ],
        Checks\CpuLoadCheck::class => [
            'warning_threshold' => 2.0,
            'error_threshold' => 3.0,
        ],
        Checks\MemoryUsageCheck::class => [
            'warning_threshold' => 80,
            'error_threshold' => 90,
        ],
    ],

    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', true),
        
        'notifications' => [
            Spatie\Health\Notifications\CheckFailedNotification::class => [
                'mail' => ['admin@chinook.local'],
                'slack' => [env('HEALTH_SLACK_WEBHOOK_URL')],
            ],
        ],
    ],
];
```

### Custom Health Checks

```php
<?php
// app/Health/FilamentHealthCheck.php

namespace App\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\{DB, Cache};

class FilamentHealthCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();
        
        try {
            // Check if Filament admin is accessible
            $adminAccessible = $this->checkAdminAccess();
            
            // Check database connectivity for Filament
            $dbConnected = $this->checkDatabaseConnection();
            
            // Check cache functionality
            $cacheWorking = $this->checkCacheConnection();
            
            // Check queue processing
            $queueWorking = $this->checkQueueProcessing();
            
            if ($adminAccessible && $dbConnected && $cacheWorking && $queueWorking) {
                return $result->ok('Filament admin panel is healthy');
            }
            
            $issues = [];
            if (!$adminAccessible) $issues[] = 'Admin panel not accessible';
            if (!$dbConnected) $issues[] = 'Database connection failed';
            if (!$cacheWorking) $issues[] = 'Cache not working';
            if (!$queueWorking) $issues[] = 'Queue processing issues';
            
            return $result->failed('Filament health issues: ' . implode(', ', $issues));
            
        } catch (\Exception $e) {
            return $result->failed('Health check failed: ' . $e->getMessage());
        }
    }
    
    private function checkAdminAccess(): bool
    {
        try {
            $response = \Http::timeout(5)->get(url('/admin'));
            return $response->status() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return DB::connection()->getDatabaseName() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkCacheConnection(): bool
    {
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 60);
            $value = Cache::get($key);
            Cache::forget($key);
            return $value === 'test';
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkQueueProcessing(): bool
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $pendingJobs = DB::table('jobs')->count();
            
            // Consider healthy if failed jobs < 10 and pending jobs < 100
            return $failedJobs < 10 && $pendingJobs < 100;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

## Database Monitoring

### Query Performance Monitoring

```php
<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Database\Events\QueryExecuted;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('production')) {
            DB::listen(function (QueryExecuted $query) {
                if ($query->time > 1000) { // Log queries taking more than 1 second
                    Log::channel('slow_queries')->warning('Slow Query Detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time,
                        'connection' => $query->connectionName,
                        'url' => request()->url(),
                        'user_id' => auth()->id(),
                    ]);
                }
            });
        }
    }
}
```

### Database Metrics Collection

```php
<?php
// app/Console/Commands/CollectDatabaseMetrics.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Cache};

class CollectDatabaseMetrics extends Command
{
    protected $signature = 'metrics:database';
    protected $description = 'Collect database performance metrics';

    public function handle(): void
    {
        $metrics = [
            'connections' => $this->getConnectionCount(),
            'slow_queries' => $this->getSlowQueryCount(),
            'table_sizes' => $this->getTableSizes(),
            'index_usage' => $this->getIndexUsage(),
            'lock_waits' => $this->getLockWaits(),
        ];
        
        Cache::put('database_metrics', $metrics, 300);
        
        $this->info('Database metrics collected successfully');
    }
    
    private function getConnectionCount(): int
    {
        return DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
    }
    
    private function getSlowQueryCount(): int
    {
        return DB::select("SHOW STATUS LIKE 'Slow_queries'")[0]->Value ?? 0;
    }
    
    private function getTableSizes(): array
    {
        $tables = ['artists', 'albums', 'tracks', 'customers', 'invoices'];
        $sizes = [];
        
        foreach ($tables as $table) {
            $result = DB::select("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ", [$table]);
            
            $sizes[$table] = $result[0]->size_mb ?? 0;
        }
        
        return $sizes;
    }
    
    private function getIndexUsage(): array
    {
        $usage = DB::select("
            SELECT 
                table_name,
                index_name,
                cardinality
            FROM information_schema.STATISTICS 
            WHERE table_schema = DATABASE()
            ORDER BY cardinality DESC
            LIMIT 10
        ");
        
        return collect($usage)->toArray();
    }
    
    private function getLockWaits(): int
    {
        return DB::select("SHOW STATUS LIKE 'Table_locks_waited'")[0]->Value ?? 0;
    }
}
```

## Queue Monitoring

### Queue Health Monitoring

```php
<?php
// app/Console/Commands/MonitorQueues.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Cache, Log};

class MonitorQueues extends Command
{
    protected $signature = 'monitor:queues';
    protected $description = 'Monitor queue health and performance';

    public function handle(): void
    {
        $metrics = [
            'pending_jobs' => $this->getPendingJobs(),
            'failed_jobs' => $this->getFailedJobs(),
            'processing_time' => $this->getAverageProcessingTime(),
            'queue_sizes' => $this->getQueueSizes(),
        ];
        
        Cache::put('queue_metrics', $metrics, 300);
        
        // Alert if queues are backing up
        if ($metrics['pending_jobs'] > 100) {
            Log::warning('Queue backup detected', $metrics);
        }
        
        if ($metrics['failed_jobs'] > 10) {
            Log::error('High failed job count', $metrics);
        }
        
        $this->info('Queue monitoring completed');
    }
    
    private function getPendingJobs(): int
    {
        return DB::table('jobs')->count();
    }
    
    private function getFailedJobs(): int
    {
        return DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHour())
            ->count();
    }
    
    private function getAverageProcessingTime(): float
    {
        return Cache::remember('avg_job_processing_time', 300, function () {
            return DB::table('pulse_entries')
                ->where('type', 'queue')
                ->where('timestamp', '>=', now()->subHour())
                ->avg('value') ?? 0;
        });
    }
    
    private function getQueueSizes(): array
    {
        $queues = ['default', 'high', 'low', 'emails'];
        $sizes = [];
        
        foreach ($queues as $queue) {
            $sizes[$queue] = DB::table('jobs')
                ->where('queue', $queue)
                ->count();
        }
        
        return $sizes;
    }
}
```

## Security Monitoring

### Security Event Logging

```php
<?php
// app/Listeners/SecurityEventListener.php

namespace App\Listeners;

use Illuminate\Auth\Events\{Login, Logout, Failed, Lockout};
use Illuminate\Support\Facades\Log;

class SecurityEventListener
{
    public function handleLogin(Login $event): void
    {
        Log::channel('security')->info('User login', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }
    
    public function handleLogout(Logout $event): void
    {
        Log::channel('security')->info('User logout', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ]);
    }
    
    public function handleFailed(Failed $event): void
    {
        Log::channel('security')->warning('Login failed', [
            'email' => $event->credentials['email'] ?? 'unknown',
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }
    
    public function handleLockout(Lockout $event): void
    {
        Log::channel('security')->error('Account lockout', [
            'email' => $event->request->input('email'),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }
}
```

## Alerting & Notifications

### Alert Configuration

```php
<?php
// config/alerts.php

return [
    'channels' => [
        'slack' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => env('SLACK_CHANNEL', '#alerts'),
            'username' => 'Chinook Monitor',
            'icon_emoji' => ':warning:',
        ],
        
        'email' => [
            'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
            'from' => env('MAIL_FROM_ADDRESS'),
        ],
        
        'discord' => [
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        ],
    ],
    
    'thresholds' => [
        'response_time' => [
            'warning' => 500, // ms
            'critical' => 1000, // ms
        ],
        
        'error_rate' => [
            'warning' => 5, // errors per hour
            'critical' => 20, // errors per hour
        ],
        
        'queue_size' => [
            'warning' => 50, // pending jobs
            'critical' => 100, // pending jobs
        ],
        
        'disk_usage' => [
            'warning' => 80, // percentage
            'critical' => 90, // percentage
        ],
    ],
];
```

## Dashboard Setup

### Monitoring Dashboard Widget

```php
<?php
// app/Filament/Widgets/MonitoringDashboard.php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class MonitoringDashboard extends Widget
{
    protected static string $view = 'filament.widgets.monitoring-dashboard';
    protected static ?int $sort = 2;
    
    protected function getViewData(): array
    {
        return [
            'metrics' => Cache::get('database_metrics', []),
            'queue_metrics' => Cache::get('queue_metrics', []),
            'health_status' => $this->getHealthStatus(),
            'alerts' => $this->getRecentAlerts(),
        ];
    }
    
    private function getHealthStatus(): array
    {
        return [
            'database' => 'healthy',
            'cache' => 'healthy',
            'queue' => 'healthy',
            'storage' => 'healthy',
        ];
    }
    
    private function getRecentAlerts(): array
    {
        return Cache::get('recent_alerts', []);
    }
}
```

## Troubleshooting

### Common Monitoring Issues

1. **High Memory Usage**
   - Check for memory leaks in long-running processes
   - Monitor queue worker memory consumption
   - Review cache usage patterns

2. **Slow Database Queries**
   - Enable query logging
   - Analyze slow query log
   - Check index usage

3. **Queue Backup**
   - Monitor queue worker processes
   - Check for failed jobs
   - Review job processing times

4. **High Error Rates**
   - Check application logs
   - Monitor exception tracking
   - Review error patterns

### Performance Optimization

```bash
# Monitor system resources
htop
iotop
nethogs

# Database monitoring
mysql -e "SHOW PROCESSLIST;"
mysql -e "SHOW STATUS LIKE 'Slow_queries';"

# Queue monitoring
php artisan queue:monitor
php artisan queue:failed

# Cache monitoring
redis-cli info memory
redis-cli monitor
```

---

**Next Steps:**
- [Logging Configuration](100-logging-configuration.md)
- [Backup Strategy](110-backup-strategy.md)
- [Maintenance Procedures](120-maintenance-procedures.md)
