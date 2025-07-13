# Filament Logging Configuration Guide

## Overview

This guide covers comprehensive logging configuration for the Chinook Filament admin panel, including structured logging, log rotation, centralized logging, and log analysis.

## Table of Contents

- [Overview](#overview)
- [Laravel Logging Configuration](#laravel-logging-configuration)
- [Structured Logging](#structured-logging)
- [Log Channels & Drivers](#log-channels--drivers)
- [Log Rotation & Retention](#log-rotation--retention)
- [Centralized Logging](#centralized-logging)
- [Security Logging](#security-logging)
- [Performance Logging](#performance-logging)
- [Error Tracking](#error-tracking)
- [Log Analysis](#log-analysis)
- [Troubleshooting](#troubleshooting)

## Laravel Logging Configuration

### Enhanced Logging Configuration

```php
<?php
// config/logging.php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],
    
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'slack'],
            'ignore_exceptions' => false,
        ],
        
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],
        
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],
        
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Chinook Logger',
            'emoji' => ':boom:',
            'level' => env('LOG_SLACK_LEVEL', 'critical'),
        ],
        
        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],
        
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],
        
        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],
        
        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],
        
        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],
        
        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
        
        // Custom channels for Filament
        'filament' => [
            'driver' => 'daily',
            'path' => storage_path('logs/filament.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
            'replace_placeholders' => true,
        ],
        
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'days' => 90,
            'replace_placeholders' => true,
        ],
        
        'performance' => [
            'driver' => 'daily',
            'path' => storage_path('logs/performance.log'),
            'level' => 'info',
            'days' => 7,
            'replace_placeholders' => true,
        ],
        
        'slow_queries' => [
            'driver' => 'daily',
            'path' => storage_path('logs/slow-queries.log'),
            'level' => 'warning',
            'days' => 14,
            'replace_placeholders' => true,
        ],
        
        'audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/audit.log'),
            'level' => 'info',
            'days' => 365,
            'replace_placeholders' => true,
        ],
        
        'api' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => 'info',
            'days' => 30,
            'replace_placeholders' => true,
        ],
    ],
];
```

## Structured Logging

### Custom Log Formatter

```php
<?php
// app/Logging/JsonFormatter.php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends BaseJsonFormatter
{
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalize($record);
        
        // Add application context
        $normalized['app'] = [
            'name' => config('app.name'),
            'env' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
        ];
        
        // Add request context if available
        if (app()->bound('request')) {
            $request = app('request');
            $normalized['request'] = [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
            ];
        }
        
        // Add server context
        $normalized['server'] = [
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
        
        return $this->toJson($normalized, true) . "\n";
    }
}
```

### Structured Logging Service

```php
<?php
// app/Services/StructuredLogger.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StructuredLogger
{
    public function logUserAction(string $action, array $context = []): void
    {
        Log::channel('audit')->info('User Action', [
            'action' => $action,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'trace_id' => $this->generateTraceId(),
        ]);
    }
    
    public function logFilamentAction(string $resource, string $action, $record = null, array $context = []): void
    {
        Log::channel('filament')->info('Filament Action', [
            'resource' => $resource,
            'action' => $action,
            'record_id' => $record?->id ?? null,
            'record_type' => $record ? get_class($record) : null,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'trace_id' => $this->generateTraceId(),
        ]);
    }
    
    public function logPerformanceMetric(string $operation, float $duration, array $context = []): void
    {
        Log::channel('performance')->info('Performance Metric', [
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'trace_id' => $this->generateTraceId(),
        ]);
    }
    
    public function logSecurityEvent(string $event, string $level = 'warning', array $context = []): void
    {
        Log::channel('security')->log($level, 'Security Event', [
            'event' => $event,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'trace_id' => $this->generateTraceId(),
        ]);
    }
    
    public function logApiRequest(string $endpoint, int $statusCode, float $duration, array $context = []): void
    {
        Log::channel('api')->info('API Request', [
            'endpoint' => $endpoint,
            'method' => request()->method(),
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
            'context' => $context,
            'trace_id' => $this->generateTraceId(),
        ]);
    }
    
    private function generateTraceId(): string
    {
        return Str::uuid()->toString();
    }
}
```

## Log Channels & Drivers

### Custom Log Channel Configuration

```php
<?php
// app/Providers/LogServiceProvider.php

namespace App\Providers;

use App\Logging\JsonFormatter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
    
    public function boot(): void
    {
        Log::extend('json', function ($app, $config) {
            $handler = new StreamHandler($config['path'], Logger::toMonologLevel($config['level']));
            $handler->setFormatter(new JsonFormatter());
            
            return new Logger('json', [$handler]);
        });
        
        Log::extend('elasticsearch', function ($app, $config) {
            return new Logger('elasticsearch', [
                new \Monolog\Handler\ElasticsearchHandler(
                    $app->make('elasticsearch'),
                    [
                        'index' => $config['index'] ?? 'laravel-logs',
                        'type' => $config['type'] ?? 'log',
                    ]
                )
            ]);
        });
    }
}
```

### Database Logging Channel

```php
<?php
// app/Logging/DatabaseHandler.php

namespace App\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Illuminate\Support\Facades\DB;

class DatabaseHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        DB::table('logs')->insert([
            'level' => $record->level->getName(),
            'message' => $record->message,
            'context' => json_encode($record->context),
            'extra' => json_encode($record->extra),
            'formatted' => $record->formatted,
            'created_at' => $record->datetime,
        ]);
    }
}
```

## Log Rotation & Retention

### Log Rotation Configuration

```bash
# /etc/logrotate.d/chinook-laravel
/var/www/chinook/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    postrotate
        /usr/bin/supervisorctl restart chinook-worker:*
    endscript
}

# Security logs - longer retention
/var/www/chinook/storage/logs/security.log {
    daily
    missingok
    rotate 365
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}

# Audit logs - longest retention
/var/www/chinook/storage/logs/audit.log {
    daily
    missingok
    rotate 2555  # 7 years
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
```

### Automated Log Cleanup

```php
<?php
// app/Console/Commands/CleanupLogs.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{File, Storage};
use Carbon\Carbon;

class CleanupLogs extends Command
{
    protected $signature = 'logs:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up old log files based on retention policies';

    public function handle(): void
    {
        $isDryRun = $this->option('dry-run');
        $logPath = storage_path('logs');
        
        $retentionPolicies = [
            'laravel*.log' => 30,      // 30 days
            'filament*.log' => 30,     // 30 days
            'performance*.log' => 7,   // 7 days
            'slow-queries*.log' => 14, // 14 days
            'security*.log' => 365,    // 1 year
            'audit*.log' => 2555,      // 7 years
            'api*.log' => 30,          // 30 days
        ];
        
        foreach ($retentionPolicies as $pattern => $retentionDays) {
            $this->cleanupLogsByPattern($logPath, $pattern, $retentionDays, $isDryRun);
        }
        
        $this->info('Log cleanup completed');
    }
    
    private function cleanupLogsByPattern(string $logPath, string $pattern, int $retentionDays, bool $isDryRun): void
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $files = File::glob($logPath . '/' . $pattern);
        
        foreach ($files as $file) {
            $fileDate = Carbon::createFromTimestamp(File::lastModified($file));
            
            if ($fileDate->lt($cutoffDate)) {
                if ($isDryRun) {
                    $this->line("Would delete: {$file} (modified: {$fileDate->format('Y-m-d H:i:s')})");
                } else {
                    File::delete($file);
                    $this->line("Deleted: {$file}");
                }
            }
        }
    }
}
```

## Centralized Logging

### ELK Stack Integration

```php
<?php
// config/elasticsearch.php

return [
    'default' => env('ELASTICSEARCH_CONNECTION', 'default'),
    
    'connections' => [
        'default' => [
            'hosts' => [
                [
                    'host' => env('ELASTICSEARCH_HOST', 'localhost'),
                    'port' => env('ELASTICSEARCH_PORT', 9200),
                    'scheme' => env('ELASTICSEARCH_SCHEME', 'http'),
                    'user' => env('ELASTICSEARCH_USER'),
                    'pass' => env('ELASTICSEARCH_PASS'),
                ],
            ],
            'retries' => 2,
            'handler' => \Elasticsearch\ClientBuilder::defaultHandler(),
        ],
    ],
    
    'indices' => [
        'logs' => [
            'index' => env('ELASTICSEARCH_LOG_INDEX', 'chinook-logs'),
            'type' => '_doc',
        ],
    ],
];
```

### Fluentd Configuration

```ruby
# /etc/td-agent/td-agent.conf

<source>
  @type tail
  path /var/www/chinook/storage/logs/*.log
  pos_file /var/log/td-agent/chinook.log.pos
  tag chinook.logs
  format json
  time_key timestamp
  time_format %Y-%m-%dT%H:%M:%S.%L%z
</source>

<filter chinook.logs>
  @type record_transformer
  <record>
    hostname "#{Socket.gethostname}"
    environment "#{ENV['APP_ENV']}"
    application "chinook"
  </record>
</filter>

<match chinook.logs>
  @type elasticsearch
  host elasticsearch.local
  port 9200
  index_name chinook-logs
  type_name _doc
  logstash_format true
  logstash_prefix chinook
  flush_interval 10s
</match>
```

## Security Logging

### Security Event Middleware

```php
<?php
// app/Http/Middleware/SecurityLoggingMiddleware.php

namespace App\Http\Middleware;

use App\Services\StructuredLogger;
use Closure;
use Illuminate\Http\Request;

class SecurityLoggingMiddleware
{
    public function __construct(
        private StructuredLogger $logger
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // Log suspicious activity
        if ($this->isSuspiciousRequest($request)) {
            $this->logger->logSecurityEvent('suspicious_request', 'warning', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);
        }
        
        // Log admin panel access
        if ($request->is('admin*')) {
            $this->logger->logSecurityEvent('admin_access', 'info', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }
        
        return $next($request);
    }
    
    private function isSuspiciousRequest(Request $request): bool
    {
        $suspiciousPatterns = [
            '/\.\.\//',           // Directory traversal
            '/union.*select/i',   // SQL injection
            '/<script/i',         // XSS
            '/eval\(/i',          // Code injection
            '/base64_decode/i',   // Encoded payloads
        ];
        
        $content = $request->getContent();
        $queryString = $request->getQueryString();
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content) || preg_match($pattern, $queryString)) {
                return true;
            }
        }
        
        return false;
    }
}
```

## Performance Logging

### Performance Monitoring Middleware

```php
<?php
// app/Http/Middleware/PerformanceLoggingMiddleware.php

namespace App\Http\Middleware;

use App\Services\StructuredLogger;
use Closure;
use Illuminate\Http\Request;

class PerformanceLoggingMiddleware
{
    public function __construct(
        private StructuredLogger $logger
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        // Log slow requests
        if ($duration > 1.0) { // Requests taking more than 1 second
            $this->logger->logPerformanceMetric('slow_request', $duration, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'memory_used' => $memoryUsed,
                'query_count' => $this->getQueryCount(),
            ]);
        }
        
        return $response;
    }
    
    private function getQueryCount(): int
    {
        return collect(\DB::getQueryLog())->count();
    }
}
```

## Error Tracking

### Enhanced Error Logging

```php
<?php
// app/Exceptions/Handler.php (additional methods)

namespace App\Exceptions;

use App\Services\StructuredLogger;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    public function __construct(
        private StructuredLogger $logger
    ) {}

    public function report(Throwable $exception): void
    {
        if ($this->shouldReport($exception)) {
            $this->logger->logSecurityEvent('exception_occurred', 'error', [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'previous' => $exception->getPrevious()?->getMessage(),
            ]);
        }
        
        parent::report($exception);
    }
}
```

## Log Analysis

### Log Analysis Commands

```php
<?php
// app/Console/Commands/AnalyzeLogs.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeLogs extends Command
{
    protected $signature = 'logs:analyze {--type=all : Type of analysis (errors, performance, security, all)}';
    protected $description = 'Analyze log files for patterns and insights';

    public function handle(): void
    {
        $type = $this->option('type');
        
        match ($type) {
            'errors' => $this->analyzeErrors(),
            'performance' => $this->analyzePerformance(),
            'security' => $this->analyzeSecurity(),
            'all' => $this->analyzeAll(),
            default => $this->error('Invalid analysis type'),
        };
    }
    
    private function analyzeErrors(): void
    {
        $this->info('Analyzing error logs...');
        
        $logFiles = File::glob(storage_path('logs/laravel*.log'));
        $errorCounts = [];
        
        foreach ($logFiles as $file) {
            $content = File::get($file);
            preg_match_all('/\[(\d{4}-\d{2}-\d{2}[^\]]+)\] local\.ERROR: ([^\n]+)/', $content, $matches);
            
            foreach ($matches[2] as $error) {
                $errorCounts[$error] = ($errorCounts[$error] ?? 0) + 1;
            }
        }
        
        arsort($errorCounts);
        
        $this->table(['Error Message', 'Count'], 
            array_slice(array_map(fn($error, $count) => [$error, $count], 
            array_keys($errorCounts), $errorCounts), 0, 10)
        );
    }
    
    private function analyzePerformance(): void
    {
        $this->info('Analyzing performance logs...');
        
        $logFiles = File::glob(storage_path('logs/performance*.log'));
        $slowOperations = [];
        
        foreach ($logFiles as $file) {
            $content = File::get($file);
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $data = json_decode($line, true);
                if ($data && isset($data['duration_ms']) && $data['duration_ms'] > 1000) {
                    $slowOperations[] = [
                        'operation' => $data['operation'] ?? 'unknown',
                        'duration' => $data['duration_ms'],
                        'timestamp' => $data['timestamp'] ?? 'unknown',
                    ];
                }
            }
        }
        
        usort($slowOperations, fn($a, $b) => $b['duration'] <=> $a['duration']);
        
        $this->table(['Operation', 'Duration (ms)', 'Timestamp'], 
            array_slice($slowOperations, 0, 10)
        );
    }
    
    private function analyzeSecurity(): void
    {
        $this->info('Analyzing security logs...');
        
        $logFiles = File::glob(storage_path('logs/security*.log'));
        $securityEvents = [];
        
        foreach ($logFiles as $file) {
            $content = File::get($file);
            $lines = explode("\n", $content);
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $data = json_decode($line, true);
                if ($data && isset($data['event'])) {
                    $securityEvents[$data['event']] = ($securityEvents[$data['event']] ?? 0) + 1;
                }
            }
        }
        
        arsort($securityEvents);
        
        $this->table(['Security Event', 'Count'], 
            array_map(fn($event, $count) => [$event, $count], 
            array_keys($securityEvents), $securityEvents)
        );
    }
    
    private function analyzeAll(): void
    {
        $this->analyzeErrors();
        $this->line('');
        $this->analyzePerformance();
        $this->line('');
        $this->analyzeSecurity();
    }
}
```

## Troubleshooting

### Common Logging Issues

1. **Log Files Not Rotating**
   - Check logrotate configuration
   - Verify file permissions
   - Test logrotate manually: `sudo logrotate -d /etc/logrotate.d/chinook-laravel`

2. **High Disk Usage**
   - Monitor log file sizes: `du -sh storage/logs/*`
   - Implement log cleanup: `php artisan logs:cleanup`
   - Adjust retention policies

3. **Missing Log Entries**
   - Check log level configuration
   - Verify channel configuration
   - Test logging: `php artisan tinker` → `Log::info('test')`

4. **Performance Impact**
   - Use asynchronous logging for high-volume logs
   - Implement log sampling for performance logs
   - Consider using separate log servers

### Log Monitoring Commands

```bash
# Monitor log files in real-time
tail -f storage/logs/laravel.log

# Search for specific errors
grep -r "ERROR" storage/logs/

# Count log entries by level
grep -c "ERROR\|WARNING\|INFO" storage/logs/laravel.log

# Monitor log file sizes
watch -n 5 'du -sh storage/logs/*'

# Check log rotation status
sudo logrotate -d /etc/logrotate.d/chinook-laravel
```

---

**Next Steps:**
- [Backup Strategy](110-backup-strategy.md)
- [Maintenance Procedures](120-maintenance-procedures.md)
- [CI/CD Pipeline](130-cicd-pipeline.md)
