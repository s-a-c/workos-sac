# Laravel Horizon Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. Configuration Publishing](#12-configuration-publishing)
  - [1.3. Environment Setup](#13-environment-setup)
- [Dashboard Configuration](#dashboard-configuration)
  - [2.1. Basic Dashboard Setup](#21-basic-dashboard-setup)
  - [2.2. Authentication & Authorization](#22-authentication--authorization)
  - [2.3. Custom Dashboard Views](#23-custom-dashboard-views)
- [Worker Configuration](#worker-configuration)
  - [3.1. Queue Worker Settings](#31-queue-worker-settings)
  - [3.2. Supervisor Configuration](#32-supervisor-configuration)
  - [3.3. Auto-Scaling Setup](#33-auto-scaling-setup)
- [Enhanced Monitoring](#enhanced-monitoring)
  - [4.1. Horizon Watcher Integration](#41-horizon-watcher-integration)
  - [4.2. Custom Metrics Collection](#42-custom-metrics-collection)
  - [4.3. Alert Configuration](#43-alert-configuration)
- [Deployment Procedures](#deployment-procedures)
  - [5.1. Zero-Downtime Deployment](#51-zero-downtime-deployment)
  - [5.2. Blue-Green Deployment](#52-blue-green-deployment)
  - [5.3. Rollback Procedures](#53-rollback-procedures)
- [Performance Tuning](#performance-tuning)
  - [6.1. Queue Optimization](#61-queue-optimization)
  - [6.2. Memory Management](#62-memory-management)
  - [6.3. Scaling Strategies](#63-scaling-strategies)
- [Integration Strategies](#integration-strategies)
  - [7.1. Laravel Pulse Integration](#71-laravel-pulse-integration)
  - [7.2. Monitoring Stack](#72-monitoring-stack)
  - [7.3. Alerting Systems](#73-alerting-systems)
- [Best Practices](#best-practices)
  - [8.1. Production Configuration](#81-production-configuration)
  - [8.2. Security Considerations](#82-security-considerations)
  - [8.3. Maintenance Procedures](#83-maintenance-procedures)
- [Troubleshooting](#troubleshooting)
  - [9.1. Common Issues](#91-common-issues)
  - [9.2. Debug Commands](#92-debug-commands)
  - [9.3. Performance Issues](#93-performance-issues)
- [Navigation](#navigation)

## Overview

Laravel Horizon provides advanced queue monitoring with real-time dashboard, enhanced alerting, and comprehensive worker management. This guide covers enterprise-level implementation with Horizon Watcher integration, auto-scaling, and production deployment strategies.

**🚀 Key Features:**
- **Real-Time Monitoring**: Live queue status and worker performance metrics
- **Automatic Scaling**: Dynamic worker scaling based on queue depth and load
- **Failed Job Management**: Comprehensive failure tracking and retry strategies
- **Deployment Integration**: Seamless deployment with supervisor configuration
- **Advanced Alerting**: Custom notification channels and threshold monitoring
- **Performance Analytics**: Historical data and optimization insights

## Installation & Setup

### 1.1. Package Installation

Install Laravel Horizon and enhanced monitoring:

```bash
# Install Laravel Horizon
composer require laravel/horizon

# Install Horizon Watcher for enhanced monitoring
composer require spatie/laravel-horizon-watcher

# Publish Horizon assets and configuration
php artisan horizon:install

# Publish Horizon Watcher configuration
php artisan vendor:publish --provider="Spatie\HorizonWatcher\HorizonWatcherServiceProvider"

# Run migrations
php artisan migrate
```

**Verification Steps:**

```bash
# Verify installation
php artisan horizon:status

# Expected output:
# Horizon is inactive.
# (This is normal before starting workers)

# Check Horizon configuration
php artisan config:show horizon
```

### 1.2. Configuration Publishing

Configure Horizon for your environment:

```php
// config/horizon.php
return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),
    
    'middleware' => ['web', 'auth'],
    
    'waits' => [
        'redis:default' => 60,
    ],
    
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'failed' => 10080,
    ],
    
    'silenced' => [
        // Jobs to silence from failed job notifications
    ],
    
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    
    'fast_termination' => false,
    
    'memory_limit' => 64,
    
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],
    
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'high', 'low'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 2,
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 300,
                'memory' => 256,
                'nice' => 0,
            ],
            
            'supervisor-high-priority' => [
                'connection' => 'redis',
                'queue' => ['high'],
                'balance' => 'simple',
                'processes' => 5,
                'tries' => 3,
                'timeout' => 60,
                'memory' => 128,
                'nice' => -10,
            ],
            
            'supervisor-low-priority' => [
                'connection' => 'redis',
                'queue' => ['low'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'size',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries' => 1,
                'timeout' => 600,
                'memory' => 512,
                'nice' => 10,
            ],
        ],
        
        'staging' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'processes' => 2,
                'tries' => 2,
                'timeout' => 120,
                'memory' => 128,
            ],
        ],
        
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 1,
                'timeout' => 60,
                'memory' => 64,
            ],
        ],
    ],
];
```

### 1.3. Environment Setup

Configure environment variables for Horizon:

```bash
# .env configuration
HORIZON_DOMAIN=null
HORIZON_PATH=horizon
HORIZON_PREFIX=horizon:

# Redis configuration for queues
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Queue configuration
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# Horizon Watcher settings
HORIZON_WATCHER_ENABLED=true
HORIZON_WATCHER_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
HORIZON_WATCHER_NOTIFICATION_CHANNELS=slack,mail

# Performance settings
HORIZON_MEMORY_LIMIT=256
HORIZON_TIMEOUT=300
HORIZON_TRIES=3
```

## Dashboard Configuration

### 2.1. Basic Dashboard Setup

Configure the Horizon dashboard for monitoring:

```php
// app/Providers/HorizonServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');

        Horizon::night();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                'admin@example.com',
            ]) || $user->hasRole(['admin', 'developer', 'operations']);
        });
    }
}
```

### 2.2. Authentication & Authorization

Implement role-based access control:

```php
// Enhanced authorization with detailed permissions
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        // Environment-based access
        if (app()->environment('local')) {
            return true;
        }
        
        // Production access - strict role checking
        if (app()->environment('production')) {
            return $user->hasAnyRole(['admin', 'operations']);
        }
        
        // Staging access - broader permissions
        return $user->hasAnyRole(['admin', 'developer', 'operations', 'qa']);
    });
    
    // Additional gates for specific actions
    Gate::define('retryHorizonJobs', function ($user) {
        return $user->hasAnyRole(['admin', 'operations']);
    });
    
    Gate::define('pauseHorizonQueues', function ($user) {
        return $user->hasRole('admin');
    });
}
```

**Custom Middleware for Enhanced Security:**

```php
// app/Http/Middleware/HorizonAccess.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HorizonAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Log all Horizon access attempts
        Log::info('Horizon access attempt', [
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
        ]);
        
        // IP whitelist for production
        if (app()->environment('production')) {
            $allowedIps = config('horizon.allowed_ips', []);
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                abort(403, 'Access denied from this IP address');
            }
        }
        
        // Time-based access restrictions
        if (config('horizon.time_restrictions.enabled', false)) {
            $currentHour = now()->hour;
            $allowedHours = config('horizon.time_restrictions.hours', []);
            
            if (!empty($allowedHours) && !in_array($currentHour, $allowedHours)) {
                abort(403, 'Horizon access restricted during this time');
            }
        }
        
        return $next($request);
    }
}
```

### 2.3. Custom Dashboard Views

Create custom dashboard views for different teams:

```php
// app/Http/Controllers/HorizonDashboardController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;

class HorizonDashboardController extends Controller
{
    public function executive(JobRepository $jobs, MetricsRepository $metrics)
    {
        return view('horizon.executive', [
            'stats' => $this->getExecutiveStats($jobs, $metrics),
        ]);
    }
    
    public function operations(JobRepository $jobs, MetricsRepository $metrics)
    {
        return view('horizon.operations', [
            'stats' => $this->getOperationsStats($jobs, $metrics),
            'queues' => $this->getQueueStats($jobs),
        ]);
    }
    
    private function getExecutiveStats($jobs, $metrics): array
    {
        return [
            'jobs_processed_today' => $jobs->countRecentlyCompleted(),
            'average_processing_time' => $metrics->jobsPerMinute(),
            'failed_jobs_today' => $jobs->countRecentlyFailed(),
            'queue_health' => $this->calculateQueueHealth($jobs),
        ];
    }
    
    private function getOperationsStats($jobs, $metrics): array
    {
        return [
            'pending_jobs' => $jobs->getPending(),
            'completed_jobs' => $jobs->getCompleted(),
            'failed_jobs' => $jobs->getFailed(),
            'processing_jobs' => $jobs->getRunning(),
            'throughput' => $metrics->throughput(),
            'runtime' => $metrics->runtime(),
        ];
    }
    
    private function getQueueStats($jobs): array
    {
        $queues = ['default', 'high', 'low', 'emails', 'reports'];
        $stats = [];
        
        foreach ($queues as $queue) {
            $stats[$queue] = [
                'pending' => $jobs->getPending($queue)->count(),
                'completed' => $jobs->getCompleted($queue)->count(),
                'failed' => $jobs->getFailed($queue)->count(),
            ];
        }
        
        return $stats;
    }
    
    private function calculateQueueHealth($jobs): string
    {
        $failedCount = $jobs->countRecentlyFailed();
        $completedCount = $jobs->countRecentlyCompleted();
        
        if ($completedCount === 0) {
            return 'unknown';
        }
        
        $failureRate = $failedCount / ($failedCount + $completedCount);
        
        if ($failureRate < 0.01) {
            return 'excellent';
        } elseif ($failureRate < 0.05) {
            return 'good';
        } elseif ($failureRate < 0.1) {
            return 'warning';
        } else {
            return 'critical';
        }
    }
}
```

## Worker Configuration

### 3.1. Queue Worker Settings

Configure workers for optimal performance:

```php
// config/horizon.php - Advanced worker configuration
'environments' => [
    'production' => [
        // High-priority queue for critical jobs
        'supervisor-critical' => [
            'connection' => 'redis',
            'queue' => ['critical'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 5,
            'timeout' => 60,
            'memory' => 128,
            'nice' => -20, // Highest priority
            'sleep' => 1,
            'maxTime' => 0,
            'maxJobs' => 100,
        ],
        
        // Standard processing queue
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'emails'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 2,
            'maxProcesses' => 8,
            'balanceMaxShift' => 2,
            'balanceCooldown' => 5,
            'tries' => 3,
            'timeout' => 300,
            'memory' => 256,
            'nice' => 0,
            'sleep' => 3,
            'maxTime' => 3600, // 1 hour
            'maxJobs' => 1000,
        ],
        
        // Long-running jobs queue
        'supervisor-long-running' => [
            'connection' => 'redis',
            'queue' => ['reports', 'exports'],
            'balance' => 'simple',
            'processes' => 2,
            'tries' => 1,
            'timeout' => 1800, // 30 minutes
            'memory' => 512,
            'nice' => 10,
            'sleep' => 5,
            'maxTime' => 7200, // 2 hours
            'maxJobs' => 10,
        ],
        
        // Background processing queue
        'supervisor-background' => [
            'connection' => 'redis',
            'queue' => ['background', 'cleanup'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'size',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'tries' => 2,
            'timeout' => 600,
            'memory' => 256,
            'nice' => 15,
            'sleep' => 10,
            'maxTime' => 0,
            'maxJobs' => 500,
        ],
    ],
],
```

### 3.2. Supervisor Configuration

Configure Supervisor for production deployment:

```ini
; /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/horizon.log
stopwaitsecs=3600
user=www-data
numprocs=1
environment=LARAVEL_ENV="production"

; Optional: Separate supervisor for different environments
[program:horizon-staging]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/staging/artisan horizon
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/staging/storage/logs/horizon.log
stopwaitsecs=1800
user=www-data
numprocs=1
environment=LARAVEL_ENV="staging"
```

**Supervisor Management Commands:**

```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start/stop Horizon
sudo supervisorctl start horizon
sudo supervisorctl stop horizon
sudo supervisorctl restart horizon

# Check status
sudo supervisorctl status horizon

# View logs
sudo supervisorctl tail horizon
sudo supervisorctl tail -f horizon
```

### 3.3. Auto-Scaling Setup

Configure intelligent auto-scaling:

```php
// app/Services/HorizonAutoScaler.php
<?php

namespace App\Services;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

class HorizonAutoScaler
{
    private JobRepository $jobs;
    private SupervisorRepository $supervisors;
    
    public function __construct(JobRepository $jobs, SupervisorRepository $supervisors)
    {
        $this->jobs = $jobs;
        $this->supervisors = $supervisors;
    }
    
    public function scale(): void
    {
        $queueStats = $this->getQueueStatistics();
        
        foreach ($queueStats as $queue => $stats) {
            $this->scaleQueue($queue, $stats);
        }
    }
    
    private function getQueueStatistics(): array
    {
        $queues = ['default', 'high', 'low', 'emails', 'reports'];
        $stats = [];
        
        foreach ($queues as $queue) {
            $pending = $this->jobs->getPending($queue)->count();
            $processing = $this->jobs->getRunning($queue)->count();
            $avgWaitTime = $this->calculateAverageWaitTime($queue);
            
            $stats[$queue] = [
                'pending' => $pending,
                'processing' => $processing,
                'avg_wait_time' => $avgWaitTime,
                'load_factor' => $this->calculateLoadFactor($pending, $processing),
            ];
        }
        
        return $stats;
    }
    
    private function scaleQueue(string $queue, array $stats): void
    {
        $currentWorkers = $this->getCurrentWorkerCount($queue);
        $optimalWorkers = $this->calculateOptimalWorkers($stats);
        
        if ($optimalWorkers > $currentWorkers) {
            $this->scaleUp($queue, $optimalWorkers - $currentWorkers);
        } elseif ($optimalWorkers < $currentWorkers) {
            $this->scaleDown($queue, $currentWorkers - $optimalWorkers);
        }
    }
    
    private function calculateOptimalWorkers(array $stats): int
    {
        $loadFactor = $stats['load_factor'];
        $pending = $stats['pending'];
        $avgWaitTime = $stats['avg_wait_time'];
        
        // Scale up if high load or long wait times
        if ($loadFactor > 0.8 || $avgWaitTime > 300) {
            return min(10, ceil($pending / 10)); // Max 10 workers
        }
        
        // Scale down if low load
        if ($loadFactor < 0.2 && $avgWaitTime < 60) {
            return max(1, floor($pending / 20)); // Min 1 worker
        }
        
        // Maintain current level
        return $this->getCurrentWorkerCount('default');
    }
    
    private function calculateLoadFactor(int $pending, int $processing): float
    {
        $total = $pending + $processing;
        return $total > 0 ? $processing / $total : 0;
    }
    
    private function calculateAverageWaitTime(string $queue): int
    {
        // Implementation depends on your metrics collection
        // This is a simplified example
        return cache()->remember("queue_wait_time_{$queue}", 60, function () {
            return rand(30, 300); // Placeholder
        });
    }
    
    private function getCurrentWorkerCount(string $queue): int
    {
        $supervisors = $this->supervisors->all();
        $count = 0;
        
        foreach ($supervisors as $supervisor) {
            if (in_array($queue, $supervisor->options['queue'] ?? [])) {
                $count += $supervisor->processes;
            }
        }
        
        return $count;
    }
    
    private function scaleUp(string $queue, int $workers): void
    {
        logger()->info("Scaling up queue {$queue} by {$workers} workers");
        // Implementation for scaling up
    }
    
    private function scaleDown(string $queue, int $workers): void
    {
        logger()->info("Scaling down queue {$queue} by {$workers} workers");
        // Implementation for scaling down
    }
}
```

## Enhanced Monitoring

### 4.1. Horizon Watcher Integration

Configure Horizon Watcher for enhanced monitoring:

```php
// config/horizon-watcher.php
return [
    'enabled' => env('HORIZON_WATCHER_ENABLED', true),

    'horizon_master_timeout' => env('HORIZON_WATCHER_MASTER_TIMEOUT', 300),
    'horizon_worker_timeout' => env('HORIZON_WATCHER_WORKER_TIMEOUT', 60),

    'notifications' => [
        'channels' => explode(',', env('HORIZON_WATCHER_NOTIFICATION_CHANNELS', 'mail')),

        'mail' => [
            'to' => env('HORIZON_WATCHER_MAIL_TO', 'admin@example.com'),
            'subject' => env('HORIZON_WATCHER_MAIL_SUBJECT', 'Horizon Alert'),
        ],

        'slack' => [
            'webhook_url' => env('HORIZON_WATCHER_SLACK_WEBHOOK'),
            'channel' => env('HORIZON_WATCHER_SLACK_CHANNEL', '#alerts'),
            'username' => env('HORIZON_WATCHER_SLACK_USERNAME', 'Horizon Watcher'),
            'icon' => env('HORIZON_WATCHER_SLACK_ICON', ':warning:'),
        ],
    ],

    'monitors' => [
        'master_supervisor' => [
            'enabled' => true,
            'timeout' => 300, // 5 minutes
        ],

        'worker_processes' => [
            'enabled' => true,
            'timeout' => 60, // 1 minute
        ],

        'queue_size' => [
            'enabled' => true,
            'threshold' => 1000, // Alert if queue size exceeds 1000
            'queues' => ['default', 'high', 'emails'],
        ],

        'failed_jobs' => [
            'enabled' => true,
            'threshold' => 10, // Alert if more than 10 failed jobs in 5 minutes
            'window' => 300,
        ],

        'wait_time' => [
            'enabled' => true,
            'threshold' => 300, // Alert if wait time exceeds 5 minutes
        ],
    ],
];
```

### 4.2. Custom Metrics Collection

Implement comprehensive metrics collection:

```php
// app/Services/HorizonMetricsService.php
<?php

namespace App\Services;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Illuminate\Support\Facades\Redis;

class HorizonMetricsService
{
    private JobRepository $jobs;
    private MetricsRepository $metrics;
    private SupervisorRepository $supervisors;

    public function __construct(
        JobRepository $jobs,
        MetricsRepository $metrics,
        SupervisorRepository $supervisors
    ) {
        $this->jobs = $jobs;
        $this->metrics = $metrics;
        $this->supervisors = $supervisors;
    }

    public function collectMetrics(): array
    {
        return [
            'queue_metrics' => $this->getQueueMetrics(),
            'worker_metrics' => $this->getWorkerMetrics(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'health_metrics' => $this->getHealthMetrics(),
        ];
    }

    private function getQueueMetrics(): array
    {
        $queues = ['default', 'high', 'low', 'emails', 'reports'];
        $metrics = [];

        foreach ($queues as $queue) {
            $pending = Redis::llen("queues:{$queue}");
            $processing = $this->jobs->getRunning($queue)->count();
            $completed = $this->jobs->getCompleted($queue)->count();
            $failed = $this->jobs->getFailed($queue)->count();

            $metrics[$queue] = [
                'pending' => $pending,
                'processing' => $processing,
                'completed_last_hour' => $this->getCompletedLastHour($queue),
                'failed_last_hour' => $this->getFailedLastHour($queue),
                'average_wait_time' => $this->getAverageWaitTime($queue),
                'throughput' => $this->getThroughput($queue),
            ];
        }

        return $metrics;
    }

    private function getWorkerMetrics(): array
    {
        $supervisors = $this->supervisors->all();
        $metrics = [];

        foreach ($supervisors as $supervisor) {
            $metrics[$supervisor->name] = [
                'status' => $supervisor->status,
                'processes' => $supervisor->processes,
                'options' => $supervisor->options,
                'memory_usage' => $this->getWorkerMemoryUsage($supervisor->name),
                'cpu_usage' => $this->getWorkerCpuUsage($supervisor->name),
                'uptime' => $this->getWorkerUptime($supervisor->name),
            ];
        }

        return $metrics;
    }

    private function getPerformanceMetrics(): array
    {
        return [
            'jobs_per_minute' => $this->metrics->jobsPerMinute(),
            'average_runtime' => $this->getAverageRuntime(),
            'peak_throughput' => $this->getPeakThroughput(),
            'error_rate' => $this->getErrorRate(),
            'queue_efficiency' => $this->getQueueEfficiency(),
        ];
    }

    private function getHealthMetrics(): array
    {
        return [
            'horizon_status' => $this->getHorizonStatus(),
            'redis_connection' => $this->checkRedisConnection(),
            'supervisor_health' => $this->getSupervisorHealth(),
            'memory_usage' => $this->getSystemMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
        ];
    }

    private function getCompletedLastHour(string $queue): int
    {
        return cache()->remember("completed_last_hour_{$queue}", 300, function () use ($queue) {
            return $this->jobs->getCompleted($queue)
                ->where('completed_at', '>=', now()->subHour())
                ->count();
        });
    }

    private function getFailedLastHour(string $queue): int
    {
        return cache()->remember("failed_last_hour_{$queue}", 300, function () use ($queue) {
            return $this->jobs->getFailed($queue)
                ->where('failed_at', '>=', now()->subHour())
                ->count();
        });
    }

    private function getAverageWaitTime(string $queue): float
    {
        return cache()->remember("avg_wait_time_{$queue}", 60, function () use ($queue) {
            $jobs = $this->jobs->getCompleted($queue)
                ->where('completed_at', '>=', now()->subHour())
                ->take(100);

            if ($jobs->isEmpty()) {
                return 0;
            }

            $totalWaitTime = $jobs->sum(function ($job) {
                return $job->started_at->diffInSeconds($job->created_at);
            });

            return $totalWaitTime / $jobs->count();
        });
    }

    private function getThroughput(string $queue): float
    {
        return cache()->remember("throughput_{$queue}", 60, function () use ($queue) {
            $completed = $this->getCompletedLastHour($queue);
            return $completed / 60; // Jobs per minute
        });
    }

    private function getWorkerMemoryUsage(string $supervisorName): array
    {
        // Implementation depends on your monitoring setup
        return [
            'current' => 0,
            'peak' => 0,
            'limit' => 0,
        ];
    }

    private function getWorkerCpuUsage(string $supervisorName): float
    {
        // Implementation depends on your monitoring setup
        return 0.0;
    }

    private function getWorkerUptime(string $supervisorName): int
    {
        // Implementation depends on your monitoring setup
        return 0;
    }

    private function getAverageRuntime(): float
    {
        return cache()->remember('avg_runtime', 300, function () {
            $jobs = $this->jobs->getCompleted()
                ->where('completed_at', '>=', now()->subHour())
                ->take(1000);

            if ($jobs->isEmpty()) {
                return 0;
            }

            $totalRuntime = $jobs->sum(function ($job) {
                return $job->completed_at->diffInSeconds($job->started_at);
            });

            return $totalRuntime / $jobs->count();
        });
    }

    private function getPeakThroughput(): float
    {
        return cache()->remember('peak_throughput', 3600, function () {
            // Calculate peak throughput over the last 24 hours
            return 0.0; // Placeholder
        });
    }

    private function getErrorRate(): float
    {
        $completed = $this->jobs->countRecentlyCompleted();
        $failed = $this->jobs->countRecentlyFailed();
        $total = $completed + $failed;

        return $total > 0 ? ($failed / $total) * 100 : 0;
    }

    private function getQueueEfficiency(): float
    {
        // Calculate efficiency based on wait time vs processing time
        return 85.0; // Placeholder
    }

    private function getHorizonStatus(): string
    {
        try {
            $masters = $this->supervisors->all();
            return $masters->isNotEmpty() ? 'running' : 'stopped';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function checkRedisConnection(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getSupervisorHealth(): array
    {
        $supervisors = $this->supervisors->all();
        $health = [];

        foreach ($supervisors as $supervisor) {
            $health[$supervisor->name] = [
                'status' => $supervisor->status,
                'healthy' => $supervisor->status === 'running',
                'last_seen' => $supervisor->updated_at,
            ];
        }

        return $health;
    }

    private function getSystemMemoryUsage(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

        $totalMB = isset($total[1]) ? round($total[1] / 1024) : 0;
        $availableMB = isset($available[1]) ? round($available[1] / 1024) : 0;
        $usedMB = $totalMB - $availableMB;

        return [
            'total' => $totalMB,
            'used' => $usedMB,
            'available' => $availableMB,
            'percentage' => $totalMB > 0 ? round(($usedMB / $totalMB) * 100, 2) : 0,
        ];
    }

    private function getDiskUsage(): array
    {
        $bytes = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $bytes - $free;

        return [
            'total' => round($bytes / 1024 / 1024 / 1024, 2), // GB
            'used' => round($used / 1024 / 1024 / 1024, 2), // GB
            'free' => round($free / 1024 / 1024 / 1024, 2), // GB
            'percentage' => round(($used / $bytes) * 100, 2),
        ];
    }
}
```

### 4.3. Alert Configuration

Configure comprehensive alerting system:

```php
// app/Services/HorizonAlertService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Notification;
use App\Notifications\HorizonAlert;

class HorizonAlertService
{
    private array $thresholds;
    private HorizonMetricsService $metricsService;

    public function __construct(HorizonMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
        $this->thresholds = config('horizon-alerts.thresholds', []);
    }

    public function checkAlerts(): void
    {
        $metrics = $this->metricsService->collectMetrics();

        $this->checkQueueAlerts($metrics['queue_metrics']);
        $this->checkWorkerAlerts($metrics['worker_metrics']);
        $this->checkPerformanceAlerts($metrics['performance_metrics']);
        $this->checkHealthAlerts($metrics['health_metrics']);
    }

    private function checkQueueAlerts(array $queueMetrics): void
    {
        foreach ($queueMetrics as $queue => $metrics) {
            // Check queue size
            if ($metrics['pending'] > $this->thresholds['queue_size'][$queue] ?? 1000) {
                $this->sendAlert('queue_size_high', [
                    'queue' => $queue,
                    'pending' => $metrics['pending'],
                    'threshold' => $this->thresholds['queue_size'][$queue] ?? 1000,
                ]);
            }

            // Check wait time
            if ($metrics['average_wait_time'] > $this->thresholds['wait_time'] ?? 300) {
                $this->sendAlert('wait_time_high', [
                    'queue' => $queue,
                    'wait_time' => $metrics['average_wait_time'],
                    'threshold' => $this->thresholds['wait_time'] ?? 300,
                ]);
            }

            // Check failure rate
            $failureRate = $this->calculateFailureRate($metrics);
            if ($failureRate > $this->thresholds['failure_rate'] ?? 10) {
                $this->sendAlert('failure_rate_high', [
                    'queue' => $queue,
                    'failure_rate' => $failureRate,
                    'threshold' => $this->thresholds['failure_rate'] ?? 10,
                ]);
            }
        }
    }

    private function checkWorkerAlerts(array $workerMetrics): void
    {
        foreach ($workerMetrics as $supervisor => $metrics) {
            // Check worker status
            if ($metrics['status'] !== 'running') {
                $this->sendAlert('worker_down', [
                    'supervisor' => $supervisor,
                    'status' => $metrics['status'],
                ]);
            }

            // Check memory usage
            if (isset($metrics['memory_usage']['percentage']) &&
                $metrics['memory_usage']['percentage'] > $this->thresholds['memory_usage'] ?? 80) {
                $this->sendAlert('memory_usage_high', [
                    'supervisor' => $supervisor,
                    'memory_usage' => $metrics['memory_usage']['percentage'],
                    'threshold' => $this->thresholds['memory_usage'] ?? 80,
                ]);
            }
        }
    }

    private function checkPerformanceAlerts(array $performanceMetrics): void
    {
        // Check error rate
        if ($performanceMetrics['error_rate'] > $this->thresholds['error_rate'] ?? 5) {
            $this->sendAlert('error_rate_high', [
                'error_rate' => $performanceMetrics['error_rate'],
                'threshold' => $this->thresholds['error_rate'] ?? 5,
            ]);
        }

        // Check throughput
        if ($performanceMetrics['jobs_per_minute'] < $this->thresholds['min_throughput'] ?? 10) {
            $this->sendAlert('throughput_low', [
                'throughput' => $performanceMetrics['jobs_per_minute'],
                'threshold' => $this->thresholds['min_throughput'] ?? 10,
            ]);
        }
    }

    private function checkHealthAlerts(array $healthMetrics): void
    {
        // Check Horizon status
        if ($healthMetrics['horizon_status'] !== 'running') {
            $this->sendAlert('horizon_down', [
                'status' => $healthMetrics['horizon_status'],
            ]);
        }

        // Check Redis connection
        if (!$healthMetrics['redis_connection']) {
            $this->sendAlert('redis_connection_failed', []);
        }

        // Check system resources
        if ($healthMetrics['memory_usage']['percentage'] > $this->thresholds['system_memory'] ?? 90) {
            $this->sendAlert('system_memory_high', [
                'memory_usage' => $healthMetrics['memory_usage']['percentage'],
                'threshold' => $this->thresholds['system_memory'] ?? 90,
            ]);
        }

        if ($healthMetrics['disk_usage']['percentage'] > $this->thresholds['disk_usage'] ?? 85) {
            $this->sendAlert('disk_usage_high', [
                'disk_usage' => $healthMetrics['disk_usage']['percentage'],
                'threshold' => $this->thresholds['disk_usage'] ?? 85,
            ]);
        }
    }

    private function calculateFailureRate(array $metrics): float
    {
        $completed = $metrics['completed_last_hour'];
        $failed = $metrics['failed_last_hour'];
        $total = $completed + $failed;

        return $total > 0 ? ($failed / $total) * 100 : 0;
    }

    private function sendAlert(string $type, array $data): void
    {
        // Prevent alert spam
        $alertKey = "horizon_alert_{$type}_" . md5(serialize($data));
        if (cache()->has($alertKey)) {
            return;
        }

        // Cache alert for 15 minutes to prevent spam
        cache()->put($alertKey, true, 900);

        // Send notification
        $channels = config('horizon-alerts.channels', ['mail']);

        foreach ($channels as $channel) {
            Notification::route($channel, $this->getChannelAddress($channel))
                ->notify(new HorizonAlert($type, $data));
        }

        // Log alert
        logger()->warning("Horizon alert: {$type}", $data);
    }

    private function getChannelAddress(string $channel): string
    {
        return match ($channel) {
            'mail' => config('horizon-alerts.mail.to', 'admin@example.com'),
            'slack' => config('horizon-alerts.slack.webhook_url'),
            default => '',
        };
    }
}
```

## Deployment Procedures

### 5.1. Zero-Downtime Deployment

Implement zero-downtime deployment strategy:

```bash
#!/bin/bash
# scripts/deploy-horizon.sh

set -e

echo "Starting Horizon deployment..."

# Configuration
APP_PATH="/var/www/html"
BACKUP_PATH="/var/backups/horizon"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup
echo "Creating backup..."
mkdir -p $BACKUP_PATH
cp -r $APP_PATH $BACKUP_PATH/app_$TIMESTAMP

# Update application code
echo "Updating application code..."
cd $APP_PATH
git pull origin main

# Install dependencies
echo "Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Pause Horizon workers
echo "Pausing Horizon workers..."
php artisan horizon:pause

# Wait for current jobs to complete
echo "Waiting for jobs to complete..."
sleep 30

# Terminate Horizon
echo "Terminating Horizon..."
php artisan horizon:terminate

# Wait for graceful shutdown
sleep 10

# Restart Horizon via Supervisor
echo "Restarting Horizon..."
sudo supervisorctl restart horizon

# Wait for Horizon to start
sleep 15

# Resume processing
echo "Resuming Horizon..."
php artisan horizon:continue

# Verify deployment
echo "Verifying deployment..."
php artisan horizon:status

# Health check
echo "Running health check..."
curl -f http://localhost/horizon/api/stats || {
    echo "Health check failed, rolling back..."
    # Rollback logic here
    exit 1
}

echo "Deployment completed successfully!"

# Cleanup old backups (keep last 5)
find $BACKUP_PATH -type d -name "app_*" | sort -r | tail -n +6 | xargs rm -rf

echo "Cleanup completed."
```

### 5.2. Blue-Green Deployment

Implement blue-green deployment for Horizon:

```bash
#!/bin/bash
# scripts/blue-green-deploy.sh

set -e

# Configuration
BLUE_PATH="/var/www/blue"
GREEN_PATH="/var/www/green"
CURRENT_LINK="/var/www/current"
NGINX_CONFIG="/etc/nginx/sites-available/app"

# Determine current and target environments
if [ -L $CURRENT_LINK ]; then
    CURRENT_ENV=$(readlink $CURRENT_LINK)
    if [[ $CURRENT_ENV == *"blue"* ]]; then
        TARGET_ENV="green"
        TARGET_PATH=$GREEN_PATH
        CURRENT_ENV_NAME="blue"
    else
        TARGET_ENV="blue"
        TARGET_PATH=$BLUE_PATH
        CURRENT_ENV_NAME="green"
    fi
else
    TARGET_ENV="blue"
    TARGET_PATH=$BLUE_PATH
    CURRENT_ENV_NAME="none"
fi

echo "Current environment: $CURRENT_ENV_NAME"
echo "Target environment: $TARGET_ENV"

# Deploy to target environment
echo "Deploying to $TARGET_ENV environment..."
cd $TARGET_PATH

# Update code
git pull origin main
composer install --no-dev --optimize-autoloader

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Horizon in target environment
echo "Starting Horizon in $TARGET_ENV environment..."
php artisan horizon:terminate || true
sleep 5

# Update supervisor configuration for target environment
sudo tee /etc/supervisor/conf.d/horizon-$TARGET_ENV.conf << EOF
[program:horizon-$TARGET_ENV]
process_name=%(program_name)s_%(process_num)02d
command=php $TARGET_PATH/artisan horizon
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=$TARGET_PATH/storage/logs/horizon.log
stopwaitsecs=3600
user=www-data
numprocs=1
environment=LARAVEL_ENV="production"
EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon-$TARGET_ENV

# Wait for Horizon to start
sleep 15

# Health check on target environment
echo "Running health check on $TARGET_ENV..."
cd $TARGET_PATH
php artisan horizon:status

# Switch traffic to target environment
echo "Switching traffic to $TARGET_ENV..."
sudo rm -f $CURRENT_LINK
sudo ln -s $TARGET_PATH $CURRENT_LINK

# Update nginx configuration if needed
sudo nginx -t && sudo systemctl reload nginx

# Stop Horizon in old environment
if [ "$CURRENT_ENV_NAME" != "none" ]; then
    echo "Stopping Horizon in $CURRENT_ENV_NAME environment..."
    sudo supervisorctl stop horizon-$CURRENT_ENV_NAME || true
    sudo supervisorctl remove horizon-$CURRENT_ENV_NAME || true
fi

echo "Blue-green deployment completed successfully!"
echo "Active environment: $TARGET_ENV"
```

### 5.3. Rollback Procedures

Implement automated rollback procedures:

```bash
#!/bin/bash
# scripts/rollback-horizon.sh

set -e

echo "Starting Horizon rollback..."

# Configuration
BACKUP_PATH="/var/backups/horizon"
APP_PATH="/var/www/html"

# Find latest backup
LATEST_BACKUP=$(ls -t $BACKUP_PATH | head -n 1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "No backup found for rollback!"
    exit 1
fi

echo "Rolling back to: $LATEST_BACKUP"

# Stop Horizon
echo "Stopping Horizon..."
php artisan horizon:pause
php artisan horizon:terminate
sudo supervisorctl stop horizon

# Backup current state
echo "Backing up current state..."
cp -r $APP_PATH $BACKUP_PATH/rollback_$(date +%Y%m%d_%H%M%S)

# Restore from backup
echo "Restoring from backup..."
rm -rf $APP_PATH/*
cp -r $BACKUP_PATH/$LATEST_BACKUP/* $APP_PATH/

# Set permissions
sudo chown -R www-data:www-data $APP_PATH
sudo chmod -R 755 $APP_PATH

# Clear caches
echo "Clearing caches..."
cd $APP_PATH
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart Horizon
echo "Restarting Horizon..."
sudo supervisorctl start horizon

# Wait for startup
sleep 15

# Verify rollback
echo "Verifying rollback..."
php artisan horizon:status

echo "Rollback completed successfully!"
```

## Performance Tuning

Optimize Horizon performance for high-throughput applications.

### 6.1. Queue Optimization

Configure optimal queue settings:

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
            'timeout' => 300,
            'memory' => 512,
            'nice' => 0,
        ],
    ],
],
```

### 6.2. Memory Management

Implement memory optimization:

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
        'memory_limit' => '512M',
    ],
],
```

### 6.3. Scaling Strategies

Configure auto-scaling:

```php
// config/horizon.php
'waits' => [
    'redis:high' => 60,
    'redis:default' => 120,
    'redis:low' => 300,
],

'trim' => [
    'recent' => 60,
    'pending' => 60,
    'completed' => 60,
    'failed' => 10080,
],
```

## Integration Strategies

Integrate Horizon with monitoring and alerting systems.

### 7.1. Laravel Pulse Integration

Connect Horizon with Laravel Pulse:

```php
// config/pulse.php
'recorders' => [
    \Laravel\Pulse\Recorders\Queues::class => [
        'sample_rate' => 1,
        'ignore' => [
            // Jobs to ignore
        ],
    ],
],
```

### 7.2. Monitoring Stack

Integrate with monitoring tools:

```php
// app/Providers/HorizonServiceProvider.php
use Laravel\Horizon\Horizon;

public function boot()
{
    Horizon::routeSlackNotificationsTo('slack-webhook-url', '#alerts');
    Horizon::routeMailNotificationsTo('admin@example.com');
    Horizon::routeSmsNotificationsTo('15556667777');
}
```

### 7.3. Alerting Systems

Configure comprehensive alerting:

```php
// config/horizon.php
'notifications' => [
    'mail' => [
        'to' => ['admin@example.com'],
        'subject' => 'Horizon Alert',
    ],
    'slack' => [
        'webhook' => env('HORIZON_SLACK_WEBHOOK'),
        'channel' => '#alerts',
    ],
],
```

## Best Practices

Enterprise-level best practices for Horizon deployment.

### 8.1. Production Configuration

Optimize for production:

```php
// config/horizon.php
'use' => 'default',
'prefix' => env('HORIZON_PREFIX', 'horizon:'),
'middleware' => ['web', 'auth'],
'waits' => [
    'redis:high' => 60,
    'redis:default' => 120,
    'redis:low' => 300,
],
'trim' => [
    'recent' => 60,
    'pending' => 60,
    'completed' => 60,
    'failed' => 10080,
],
```

### 8.2. Security Considerations

Implement security measures:

```php
// app/Providers/HorizonServiceProvider.php
use Laravel\Horizon\Horizon;

public function boot()
{
    Horizon::auth(function ($request) {
        return auth()->check() &&
               auth()->user()->hasRole(['Super Admin', 'Admin']);
    });
}
```

### 8.3. Maintenance Procedures

Regular maintenance tasks:

```bash
#!/bin/bash
# horizon-maintenance.sh

# Clear failed jobs older than 7 days
php artisan horizon:clear --queue=failed --hours=168

# Restart workers
php artisan horizon:terminate

# Check status
php artisan horizon:status
```

## Troubleshooting

Common issues and solutions for Horizon deployment.

### 9.1. Common Issues

**Workers Not Starting:**

```bash
# Check supervisor status
sudo supervisorctl status horizon

# Restart supervisor
sudo supervisorctl restart horizon

# Check logs
tail -f /var/log/supervisor/horizon.log
```

### 9.2. Debug Commands

Useful debugging commands:

```bash
# Check Horizon status
php artisan horizon:status

# List failed jobs
php artisan horizon:failed

# Clear all jobs
php artisan horizon:clear

# Pause/Continue workers
php artisan horizon:pause
php artisan horizon:continue
```

### 9.3. Performance Issues

Diagnose performance problems:

```php
// Monitor queue metrics
use Laravel\Horizon\Contracts\MetricsRepository;

$metrics = app(MetricsRepository::class);
$throughput = $metrics->throughput();
$runtime = $metrics->runtime();
```

## Navigation

**← Previous:** [Laravel Octane with FrankenPHP Guide](040-laravel-octane-frankenphp-guide.md)

**Next →** [Laravel Data Guide](060-laravel-data-guide.md)
