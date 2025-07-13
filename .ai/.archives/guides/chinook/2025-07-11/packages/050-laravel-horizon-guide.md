# 1. Laravel Horizon Implementation Guide

**Refactored from:** `.ai/guides/chinook/packages/050-laravel-horizon-guide.md` on 2025-07-11

## Table of Contents

- [1. Laravel Horizon Implementation Guide](#1-laravel-horizon-implementation-guide)
  - [1.1. Overview](#11-overview)
  - [1.2. Installation & Setup](#12-installation--setup)
    - [1.2.1. Package Installation](#121-package-installation)
    - [1.2.2. Configuration Publishing](#122-configuration-publishing)
    - [1.2.3. Environment Setup](#123-environment-setup)
  - [1.3. Dashboard Configuration](#13-dashboard-configuration)
    - [1.3.1. Basic Dashboard Setup](#131-basic-dashboard-setup)
    - [1.3.2. Authentication & Authorization](#132-authentication--authorization)
    - [1.3.3. Custom Dashboard Views](#133-custom-dashboard-views)
  - [1.4. Worker Configuration](#14-worker-configuration)
    - [1.4.1. Queue Worker Settings](#141-queue-worker-settings)
    - [1.4.2. Supervisor Configuration](#142-supervisor-configuration)
    - [1.4.3. Auto-Scaling Setup](#143-auto-scaling-setup)
  - [1.5. Enhanced Monitoring](#15-enhanced-monitoring)
    - [1.5.1. Horizon Watcher Integration](#151-horizon-watcher-integration)
    - [1.5.2. Custom Metrics Collection](#152-custom-metrics-collection)
    - [1.5.3. Alert Configuration](#153-alert-configuration)
  - [1.6. Deployment Procedures](#16-deployment-procedures)
    - [1.6.1. Zero-Downtime Deployment](#161-zero-downtime-deployment)
    - [1.6.2. Blue-Green Deployment](#162-blue-green-deployment)
    - [1.6.3. Rollback Procedures](#163-rollback-procedures)
  - [1.7. Performance Tuning](#17-performance-tuning)
    - [1.7.1. Queue Optimization](#171-queue-optimization)
    - [1.7.2. Memory Management](#172-memory-management)
    - [1.7.3. Scaling Strategies](#173-scaling-strategies)
  - [1.8. Integration Strategies](#18-integration-strategies)
    - [1.8.1. Laravel Pulse Integration](#181-laravel-pulse-integration)
    - [1.8.2. Monitoring Stack](#182-monitoring-stack)
    - [1.8.3. Alerting Systems](#183-alerting-systems)
  - [1.9. Best Practices](#19-best-practices)
    - [1.9.1. Production Configuration](#191-production-configuration)
    - [1.9.2. Security Considerations](#192-security-considerations)
    - [1.9.3. Maintenance Procedures](#193-maintenance-procedures)
  - [1.10. Troubleshooting](#110-troubleshooting)
    - [1.10.1. Common Issues](#1101-common-issues)
    - [1.10.2. Debug Commands](#1102-debug-commands)
    - [1.10.3. Performance Issues](#1103-performance-issues)
  - [1.11. Navigation](#111-navigation)

## 1.1. Overview

Laravel Horizon provides advanced queue monitoring with real-time dashboard, enhanced alerting, and comprehensive worker management. This guide covers enterprise-level implementation with Horizon Watcher integration, auto-scaling, and production deployment strategies for the Chinook music store application.

**🚀 Key Features:**
- **Real-Time Monitoring**: Live queue status and worker performance metrics for Chinook operations
- **Automatic Scaling**: Dynamic worker scaling based on music processing and customer activity load
- **Enhanced Alerting**: Proactive notifications for failed jobs and performance issues
- **Comprehensive Dashboard**: Visual insights into Chinook background job processing
- **Production Deployment**: Zero-downtime deployment strategies for continuous music service
- **Performance Optimization**: Advanced queue tuning for high-throughput music operations
- **Taxonomy Integration**: Monitor taxonomy-related background jobs using aliziodev/laravel-taxonomy

**🎵 Chinook-Specific Queue Operations:**
- **Music File Processing**: Audio encoding, metadata extraction, and thumbnail generation
- **Customer Notifications**: Email campaigns, purchase confirmations, and playlist updates
- **Analytics Processing**: Sales reports, customer behavior analysis, and recommendation engines
- **Taxonomy Management**: Genre classification, tag processing, and search index updates
- **Payment Processing**: Invoice generation, payment verification, and subscription management
- **Data Synchronization**: Catalog updates, inventory management, and backup operations

## 1.2. Installation & Setup

### 1.2.1. Package Installation

Install Laravel Horizon for the Chinook application:

```bash
# Install Laravel Horizon
composer require laravel/horizon

# Publish Horizon assets and configuration
php artisan horizon:install

# Publish Horizon configuration
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"

# Run migrations for Horizon tables
php artisan migrate
```

**Verification Steps:**

```bash
# Verify Horizon installation
php artisan horizon:status

# Expected output:
# Horizon is inactive.

# Start Horizon
php artisan horizon

# Check Horizon dashboard
# Navigate to: http://your-app.com/horizon
```

### 1.2.2. Configuration Publishing

Configure Horizon for optimal Chinook queue management:

```php
// config/horizon.php
<?php

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),
    
    'middleware' => [
        'web',
        'auth',
        \App\Http\Middleware\ChinookHorizonAccess::class,
    ],
    
    'waits' => [
        'redis:default' => 60,
        'redis:chinook_music' => 120,
        'redis:chinook_notifications' => 30,
        'redis:chinook_analytics' => 300,
    ],
    
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    
    'silenced' => [
        // Silenced job classes for Chinook
    ],
    
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    
    'fast_termination' => false,
    
    'memory_limit' => 512,
    
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
            // Chinook music processing queue
            'chinook-music-supervisor' => [
                'connection' => 'redis',
                'queue' => ['chinook_music', 'chinook_audio_processing'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 8,
                'maxTime' => 0,
                'maxJobs' => 1000,
                'memory' => 512,
                'tries' => 3,
                'timeout' => 300,
                'nice' => 0,
            ],
            
            // Chinook customer notifications
            'chinook-notifications-supervisor' => [
                'connection' => 'redis',
                'queue' => ['chinook_notifications', 'chinook_emails'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'size',
                'maxProcesses' => 4,
                'maxTime' => 0,
                'maxJobs' => 500,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 120,
                'nice' => 0,
            ],
            
            // Chinook analytics and reporting
            'chinook-analytics-supervisor' => [
                'connection' => 'redis',
                'queue' => ['chinook_analytics', 'chinook_reports'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 2,
                'maxTime' => 0,
                'maxJobs' => 100,
                'memory' => 1024,
                'tries' => 2,
                'timeout' => 600,
                'nice' => 10,
            ],
            
            // Chinook taxonomy processing
            'chinook-taxonomy-supervisor' => [
                'connection' => 'redis',
                'queue' => ['chinook_taxonomy', 'chinook_search_index'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'size',
                'maxProcesses' => 3,
                'maxTime' => 0,
                'maxJobs' => 200,
                'memory' => 256,
                'tries' => 3,
                'timeout' => 180,
                'nice' => 5,
            ],
        ],
        
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'chinook_music', 'chinook_notifications'],
                'balance' => 'simple',
                'maxProcesses' => 3,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 1,
                'timeout' => 60,
                'nice' => 0,
            ],
        ],
    ],
];
```

### 1.2.3. Environment Setup

Configure environment variables for Chinook Horizon:

```bash
# .env configuration
HORIZON_DOMAIN=null
HORIZON_PATH=horizon
HORIZON_PREFIX=chinook_horizon:

# Redis configuration for Chinook queues
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Queue configuration
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# Chinook-specific queue settings
CHINOOK_MUSIC_QUEUE_WORKERS=8
CHINOOK_NOTIFICATION_QUEUE_WORKERS=4
CHINOOK_ANALYTICS_QUEUE_WORKERS=2
CHINOOK_TAXONOMY_QUEUE_WORKERS=3

# Performance settings
HORIZON_MEMORY_LIMIT=512
HORIZON_TIMEOUT=300
HORIZON_TRIES=3

# Monitoring settings
HORIZON_METRICS_ENABLED=true
HORIZON_ALERTS_ENABLED=true
HORIZON_SLACK_WEBHOOK=https://hooks.slack.com/services/your/webhook/url
```

**Redis Configuration for Chinook:**

```php
// config/database.php - Redis connections
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],

    // Chinook-specific Redis connections
    'chinook_music' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => 2,
        'options' => [
            'prefix' => 'chinook_music:',
        ],
    ],

    'chinook_notifications' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => 3,
        'options' => [
            'prefix' => 'chinook_notifications:',
        ],
    ],

    'chinook_analytics' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => 4,
        'options' => [
            'prefix' => 'chinook_analytics:',
        ],
    ],
],
```

## 1.3. Dashboard Configuration

### 1.3.1. Basic Dashboard Setup

Configure the Horizon dashboard for Chinook monitoring:

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

        // Configure Horizon for Chinook
        Horizon::routeSlackNotificationsTo(
            config('chinook.horizon.slack_webhook'),
            '#chinook-alerts'
        );

        // Custom dashboard configuration
        $this->configureChinookDashboard();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return $user->hasAnyRole(['Super Admin', 'Admin', 'Manager']);
        });
    }

    private function configureChinookDashboard(): void
    {
        // Custom dashboard metrics for Chinook
        Horizon::night();

        // Tag jobs for better organization
        Horizon::tag(function ($job) {
            $tags = [];

            // Tag Chinook-specific jobs
            if (str_contains(get_class($job), 'Chinook')) {
                $tags[] = 'chinook';
            }

            // Tag by job type
            if (str_contains(get_class($job), 'Music')) {
                $tags[] = 'music-processing';
            }

            if (str_contains(get_class($job), 'Notification')) {
                $tags[] = 'notifications';
            }

            if (str_contains(get_class($job), 'Analytics')) {
                $tags[] = 'analytics';
            }

            if (str_contains(get_class($job), 'Taxonomy')) {
                $tags[] = 'taxonomy';
            }

            // Tag by customer if available
            if (method_exists($job, 'getCustomerId')) {
                $tags[] = 'customer:' . $job->getCustomerId();
            }

            return $tags;
        });
    }
}
```

### 1.3.2. Authentication & Authorization

Configure secure access to Horizon using spatie/laravel-permission:

```php
// app/Http/Middleware/ChinookHorizonAccess.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ChinookHorizonAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Disable in production unless explicitly enabled
        if (app()->environment('production') && !config('horizon.enabled', false)) {
            abort(404);
        }

        // Role-based access for Chinook team members
        if (!$request->user()?->hasAnyRole(['Super Admin', 'Admin', 'Manager'])) {
            abort(403, 'Insufficient permissions for Chinook queue monitoring');
        }

        // IP whitelist for production
        if (app()->environment('production')) {
            $allowedIps = config('chinook.horizon.allowed_ips', []);
            if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
                abort(403, 'IP not authorized for Chinook Horizon access');
            }
        }

        // Time-based access restrictions
        $allowedHours = config('chinook.horizon.allowed_hours', []);
        if (!empty($allowedHours) && !in_array(now()->hour, $allowedHours)) {
            abort(403, 'Horizon access not allowed at this time');
        }

        return $next($request);
    }
}
```

### 1.3.3. Custom Dashboard Views

Create custom dashboard views for Chinook operations:

```php
// app/Http/Controllers/ChinookHorizonController.php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

class ChinookHorizonController extends Controller
{
    public function musicProcessingDashboard(
        JobRepository $jobs,
        MetricsRepository $metrics,
        WorkloadRepository $workload
    ) {
        return view('chinook.horizon.music-processing', [
            'musicJobs' => $jobs->getJobs(['music-processing'], 'pending'),
            'processingMetrics' => $metrics->jobMetricsFor('music-processing'),
            'workload' => $workload->get(),
        ]);
    }

    public function customerNotificationsDashboard(
        JobRepository $jobs,
        MetricsRepository $metrics
    ) {
        return view('chinook.horizon.notifications', [
            'notificationJobs' => $jobs->getJobs(['notifications'], 'pending'),
            'emailMetrics' => $metrics->jobMetricsFor('notifications'),
            'failedNotifications' => $jobs->getJobs(['notifications'], 'failed'),
        ]);
    }

    public function analyticsDashboard(
        JobRepository $jobs,
        MetricsRepository $metrics
    ) {
        return view('chinook.horizon.analytics', [
            'analyticsJobs' => $jobs->getJobs(['analytics'], 'pending'),
            'reportMetrics' => $metrics->jobMetricsFor('analytics'),
            'completedReports' => $jobs->getJobs(['analytics'], 'completed'),
        ]);
    }

    public function taxonomyDashboard(
        JobRepository $jobs,
        MetricsRepository $metrics
    ) {
        return view('chinook.horizon.taxonomy', [
            'taxonomyJobs' => $jobs->getJobs(['taxonomy'], 'pending'),
            'indexingMetrics' => $metrics->jobMetricsFor('taxonomy'),
            'searchIndexStatus' => $this->getSearchIndexStatus(),
        ]);
    }

    private function getSearchIndexStatus(): array
    {
        // Get search index status for Chinook taxonomy
        return [
            'total_taxonomies' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::count(),
            'indexed_taxonomies' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::whereNotNull('search_index_updated_at')->count(),
            'pending_indexing' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::whereNull('search_index_updated_at')->count(),
        ];
    }
}
```

**Custom Dashboard Routes:**

```php
// routes/web.php
Route::middleware(['auth', 'can:viewHorizon'])->prefix('horizon/chinook')->group(function () {
    Route::get('/music-processing', [ChinookHorizonController::class, 'musicProcessingDashboard'])
        ->name('horizon.chinook.music-processing');

    Route::get('/notifications', [ChinookHorizonController::class, 'customerNotificationsDashboard'])
        ->name('horizon.chinook.notifications');

    Route::get('/analytics', [ChinookHorizonController::class, 'analyticsDashboard'])
        ->name('horizon.chinook.analytics');

    Route::get('/taxonomy', [ChinookHorizonController::class, 'taxonomyDashboard'])
        ->name('horizon.chinook.taxonomy');
});
```

## 1.4. Worker Configuration

### 1.4.1. Queue Worker Settings

Configure queue workers for optimal Chinook performance:

```php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

        // Chinook music processing queue
        'chinook_music' => [
            'driver' => 'redis',
            'connection' => 'chinook_music',
            'queue' => 'chinook_music',
            'retry_after' => 300, // 5 minutes for music processing
            'block_for' => 5,
            'after_commit' => false,
        ],

        // Chinook notifications queue
        'chinook_notifications' => [
            'driver' => 'redis',
            'connection' => 'chinook_notifications',
            'queue' => 'chinook_notifications',
            'retry_after' => 120, // 2 minutes for notifications
            'block_for' => 2,
            'after_commit' => true, // Wait for DB commit
        ],

        // Chinook analytics queue
        'chinook_analytics' => [
            'driver' => 'redis',
            'connection' => 'chinook_analytics',
            'queue' => 'chinook_analytics',
            'retry_after' => 600, // 10 minutes for analytics
            'block_for' => 10,
            'after_commit' => false,
        ],

        // Chinook taxonomy queue
        'chinook_taxonomy' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'chinook_taxonomy',
            'retry_after' => 180, // 3 minutes for taxonomy operations
            'block_for' => 3,
            'after_commit' => true,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

**Chinook Job Classes:**

```php
// app/Jobs/Chinook/ProcessMusicFileJob.php
<?php

namespace App\Jobs\Chinook;

use App\Models\ChinookTrack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessMusicFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $maxExceptions = 2;

    public function __construct(
        public ChinookTrack $track,
        public string $filePath
    ) {
        $this->onQueue('chinook_music');
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->track->id),
        ];
    }

    public function handle(): void
    {
        // Process music file: encoding, metadata extraction, etc.
        $this->extractMetadata();
        $this->generateThumbnail();
        $this->updateSearchIndex();
    }

    public function tags(): array
    {
        return [
            'music-processing',
            'chinook',
            'track:' . $this->track->id,
        ];
    }

    private function extractMetadata(): void
    {
        // Extract metadata from music file
    }

    private function generateThumbnail(): void
    {
        // Generate album artwork thumbnail
    }

    private function updateSearchIndex(): void
    {
        // Update search index for the track
    }
}
```

### 1.4.2. Supervisor Configuration

Configure Supervisor for production Chinook deployment:

```ini
; /etc/supervisor/conf.d/chinook-horizon.conf
[program:chinook-horizon]
process_name=%(program_name)s
command=php /var/www/chinook/artisan horizon
directory=/var/www/chinook
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/chinook/storage/logs/horizon.log
stopwaitsecs=3600
environment=LARAVEL_ENV="production"

[program:chinook-music-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/chinook/artisan queue:work redis --queue=chinook_music --sleep=3 --tries=3 --max-time=3600 --memory=512
directory=/var/www/chinook
autostart=true
autorestart=true
numprocs=4
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/chinook/storage/logs/music-worker.log
stopwaitsecs=3600

[program:chinook-notifications-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/chinook/artisan queue:work redis --queue=chinook_notifications --sleep=3 --tries=3 --max-time=3600 --memory=256
directory=/var/www/chinook
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/chinook/storage/logs/notifications-worker.log
stopwaitsecs=3600

[program:chinook-analytics-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/chinook/artisan queue:work redis --queue=chinook_analytics --sleep=5 --tries=2 --max-time=7200 --memory=1024
directory=/var/www/chinook
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/chinook/storage/logs/analytics-worker.log
stopwaitsecs=7200

[program:chinook-taxonomy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/chinook/artisan queue:work redis --queue=chinook_taxonomy --sleep=3 --tries=3 --max-time=3600 --memory=256
directory=/var/www/chinook
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/chinook/storage/logs/taxonomy-worker.log
stopwaitsecs=3600

[group:chinook-workers]
programs=chinook-horizon,chinook-music-worker,chinook-notifications-worker,chinook-analytics-worker,chinook-taxonomy-worker
priority=999
```

**Supervisor Management Commands:**

```bash
# Reload Supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start Chinook workers
sudo supervisorctl start chinook-workers:*

# Stop Chinook workers
sudo supervisorctl stop chinook-workers:*

# Restart Chinook workers
sudo supervisorctl restart chinook-workers:*

# Check status
sudo supervisorctl status chinook-workers:*

# Monitor logs
sudo tail -f /var/www/chinook/storage/logs/horizon.log
sudo tail -f /var/www/chinook/storage/logs/music-worker.log
```

### 1.4.3. Auto-Scaling Setup

Configure auto-scaling for Chinook queue workers:

```php
// app/Console/Commands/ChinookHorizonAutoScale.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class ChinookHorizonAutoScale extends Command
{
    protected $signature = 'chinook:horizon-autoscale';
    protected $description = 'Auto-scale Chinook Horizon workers based on queue depth';

    public function handle(
        WorkloadRepository $workload,
        MasterSupervisorRepository $masters
    ): void {
        $workloads = $workload->get();

        foreach ($workloads as $queue => $load) {
            $this->scaleQueue($queue, $load, $masters);
        }
    }

    private function scaleQueue(string $queue, array $load, MasterSupervisorRepository $masters): void
    {
        $currentWorkers = $this->getCurrentWorkers($queue, $masters);
        $optimalWorkers = $this->calculateOptimalWorkers($queue, $load);

        if ($optimalWorkers > $currentWorkers) {
            $this->scaleUp($queue, $optimalWorkers - $currentWorkers);
        } elseif ($optimalWorkers < $currentWorkers) {
            $this->scaleDown($queue, $currentWorkers - $optimalWorkers);
        }
    }

    private function calculateOptimalWorkers(string $queue, array $load): int
    {
        $pending = $load['length'] ?? 0;
        $wait = $load['wait'] ?? 0;

        // Chinook-specific scaling logic
        return match($queue) {
            'chinook_music' => min(max(ceil($pending / 10), 2), 8), // 2-8 workers
            'chinook_notifications' => min(max(ceil($pending / 20), 1), 4), // 1-4 workers
            'chinook_analytics' => min(max(ceil($pending / 5), 1), 2), // 1-2 workers
            'chinook_taxonomy' => min(max(ceil($pending / 15), 1), 3), // 1-3 workers
            default => min(max(ceil($pending / 10), 1), 3),
        };
    }

    private function getCurrentWorkers(string $queue, MasterSupervisorRepository $masters): int
    {
        $count = 0;
        foreach ($masters->all() as $master) {
            foreach ($master->supervisors as $supervisor) {
                if (in_array($queue, $supervisor->options['queue'] ?? [])) {
                    $count += $supervisor->processes->count();
                }
            }
        }
        return $count;
    }

    private function scaleUp(string $queue, int $workers): void
    {
        $this->info("Scaling up {$queue} by {$workers} workers");
        // Implementation for scaling up workers
    }

    private function scaleDown(string $queue, int $workers): void
    {
        $this->info("Scaling down {$queue} by {$workers} workers");
        // Implementation for scaling down workers
    }
}
```

## 1.5. Enhanced Monitoring

### 1.5.1. Horizon Watcher Integration

Integrate Horizon with monitoring systems for Chinook:

```php
// app/Services/ChinookHorizonMonitoringService.php
<?php

namespace App\Services;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Illuminate\Support\Facades\Cache;

class ChinookHorizonMonitoringService
{
    public function __construct(
        private JobRepository $jobs,
        private MetricsRepository $metrics,
        private WorkloadRepository $workload
    ) {}

    public function collectChinookMetrics(): array
    {
        return [
            'queue_metrics' => $this->getQueueMetrics(),
            'job_metrics' => $this->getJobMetrics(),
            'worker_metrics' => $this->getWorkerMetrics(),
            'chinook_specific' => $this->getChinookSpecificMetrics(),
        ];
    }

    private function getQueueMetrics(): array
    {
        $workloads = $this->workload->get();

        return [
            'chinook_music' => [
                'pending' => $workloads['chinook_music']['length'] ?? 0,
                'wait_time' => $workloads['chinook_music']['wait'] ?? 0,
            ],
            'chinook_notifications' => [
                'pending' => $workloads['chinook_notifications']['length'] ?? 0,
                'wait_time' => $workloads['chinook_notifications']['wait'] ?? 0,
            ],
            'chinook_analytics' => [
                'pending' => $workloads['chinook_analytics']['length'] ?? 0,
                'wait_time' => $workloads['chinook_analytics']['wait'] ?? 0,
            ],
            'chinook_taxonomy' => [
                'pending' => $workloads['chinook_taxonomy']['length'] ?? 0,
                'wait_time' => $workloads['chinook_taxonomy']['wait'] ?? 0,
            ],
        ];
    }

    private function getJobMetrics(): array
    {
        return [
            'music_processing' => $this->metrics->jobMetricsFor('music-processing'),
            'notifications' => $this->metrics->jobMetricsFor('notifications'),
            'analytics' => $this->metrics->jobMetricsFor('analytics'),
            'taxonomy' => $this->metrics->jobMetricsFor('taxonomy'),
        ];
    }

    private function getWorkerMetrics(): array
    {
        return [
            'total_workers' => $this->getTotalWorkers(),
            'active_workers' => $this->getActiveWorkers(),
            'memory_usage' => $this->getMemoryUsage(),
        ];
    }

    private function getChinookSpecificMetrics(): array
    {
        return [
            'music_files_processed_today' => Cache::get('chinook_music_processed_today', 0),
            'notifications_sent_today' => Cache::get('chinook_notifications_sent_today', 0),
            'analytics_reports_generated' => Cache::get('chinook_reports_generated_today', 0),
            'taxonomy_operations_today' => Cache::get('chinook_taxonomy_ops_today', 0),
        ];
    }

    private function getTotalWorkers(): int
    {
        // Implementation to get total worker count
        return 0;
    }

    private function getActiveWorkers(): int
    {
        // Implementation to get active worker count
        return 0;
    }

    private function getMemoryUsage(): array
    {
        // Implementation to get memory usage statistics
        return [];
    }
}
```

### 1.5.2. Custom Metrics Collection

Implement custom metrics collection for Chinook operations:

```php
// app/Listeners/ChinookHorizonMetricsListener.php
<?php

namespace App\Listeners;

use Laravel\Horizon\Events\JobProcessed;
use Laravel\Horizon\Events\JobFailed;
use Laravel\Horizon\Events\WorkerStopping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChinookHorizonMetricsListener
{
    public function handleJobProcessed(JobProcessed $event): void
    {
        $job = $event->job;
        $tags = $job->tags();

        // Track Chinook-specific job completions
        if (in_array('chinook', $tags)) {
            $this->incrementChinookMetric($job, 'completed');
        }

        // Track by job type
        if (in_array('music-processing', $tags)) {
            Cache::increment('chinook_music_processed_today');
        }

        if (in_array('notifications', $tags)) {
            Cache::increment('chinook_notifications_sent_today');
        }

        if (in_array('analytics', $tags)) {
            Cache::increment('chinook_reports_generated_today');
        }

        if (in_array('taxonomy', $tags)) {
            Cache::increment('chinook_taxonomy_ops_today');
        }
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $job = $event->job;
        $exception = $event->exception;
        $tags = $job->tags();

        // Track Chinook-specific job failures
        if (in_array('chinook', $tags)) {
            $this->incrementChinookMetric($job, 'failed');

            // Log critical failures
            if (in_array('music-processing', $tags)) {
                Log::error('Chinook music processing job failed', [
                    'job' => get_class($job),
                    'exception' => $exception->getMessage(),
                    'tags' => $tags,
                ]);
            }
        }
    }

    public function handleWorkerStopping(WorkerStopping $event): void
    {
        // Track worker lifecycle for Chinook monitoring
        Log::info('Chinook worker stopping', [
            'status' => $event->status,
            'worker_name' => $event->workerName ?? 'unknown',
        ]);
    }

    private function incrementChinookMetric($job, string $status): void
    {
        $jobClass = get_class($job);
        $key = "chinook_job_{$status}:" . class_basename($jobClass);

        Cache::increment($key);
        Cache::increment("chinook_total_{$status}");
    }
}
```

### 1.5.3. Alert Configuration

Configure comprehensive alerting for Chinook Horizon:

```php
// app/Services/ChinookHorizonAlertService.php
<?php

namespace App\Services;

use Laravel\Horizon\Contracts\WorkloadRepository;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ChinookHorizonAlert;

class ChinookHorizonAlertService
{
    public function __construct(
        private WorkloadRepository $workload
    ) {}

    public function checkAlerts(): void
    {
        $this->checkQueueDepth();
        $this->checkWorkerHealth();
        $this->checkJobFailures();
        $this->checkMemoryUsage();
    }

    private function checkQueueDepth(): void
    {
        $workloads = $this->workload->get();

        foreach ($workloads as $queue => $load) {
            $pending = $load['length'] ?? 0;
            $threshold = $this->getQueueThreshold($queue);

            if ($pending > $threshold) {
                $this->sendAlert("Queue {$queue} has {$pending} pending jobs (threshold: {$threshold})");
            }
        }
    }

    private function checkWorkerHealth(): void
    {
        // Check if workers are responsive
        $inactiveWorkers = $this->getInactiveWorkers();

        if (!empty($inactiveWorkers)) {
            $this->sendAlert("Inactive workers detected: " . implode(', ', $inactiveWorkers));
        }
    }

    private function checkJobFailures(): void
    {
        $failureRate = $this->getJobFailureRate();

        if ($failureRate > 0.1) { // 10% failure rate threshold
            $this->sendAlert("High job failure rate detected: " . ($failureRate * 100) . "%");
        }
    }

    private function checkMemoryUsage(): void
    {
        $memoryUsage = $this->getWorkerMemoryUsage();

        foreach ($memoryUsage as $worker => $usage) {
            if ($usage > 0.9) { // 90% memory usage threshold
                $this->sendAlert("High memory usage on worker {$worker}: " . ($usage * 100) . "%");
            }
        }
    }

    private function getQueueThreshold(string $queue): int
    {
        return match($queue) {
            'chinook_music' => 100,
            'chinook_notifications' => 200,
            'chinook_analytics' => 50,
            'chinook_taxonomy' => 75,
            default => 50,
        };
    }

    private function sendAlert(string $message): void
    {
        Notification::route('slack', config('chinook.horizon.slack_webhook'))
            ->notify(new ChinookHorizonAlert($message));
    }

    private function getInactiveWorkers(): array
    {
        // Implementation to detect inactive workers
        return [];
    }

    private function getJobFailureRate(): float
    {
        // Implementation to calculate job failure rate
        return 0.0;
    }

    private function getWorkerMemoryUsage(): array
    {
        // Implementation to get worker memory usage
        return [];
    }
}
```

## 1.10. Troubleshooting

### 1.10.1. Common Issues

**Workers Not Processing Jobs:**

```bash
# Check Horizon status
php artisan horizon:status

# Check Redis connection
php artisan tinker
>>> Redis::ping()

# Check queue configuration
php artisan queue:monitor redis:chinook_music,redis:chinook_notifications

# Restart Horizon
php artisan horizon:terminate
php artisan horizon
```

**High Memory Usage:**

```bash
# Monitor worker memory usage
ps aux | grep "queue:work" | grep -v grep

# Restart workers with lower memory limits
php artisan horizon:terminate
php artisan horizon

# Check for memory leaks in jobs
php artisan horizon:clear
```

### 1.10.2. Debug Commands

Debug Chinook Horizon issues:

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Monitor specific queue
php artisan queue:monitor redis:chinook_music --max=100

# Check Horizon metrics
php artisan horizon:snapshot

# Pause/Continue queues
php artisan horizon:pause
php artisan horizon:continue
```

### 1.10.3. Performance Issues

Optimize Chinook Horizon performance:

```php
// Optimize job serialization
class OptimizedChinookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $trackId // Use ID instead of model
    ) {}

    public function handle(): void
    {
        $track = ChinookTrack::find($this->trackId);
        // Process track
    }
}

// Batch processing for efficiency
Bus::batch([
    new ProcessMusicFileJob($track1->id),
    new ProcessMusicFileJob($track2->id),
    new ProcessMusicFileJob($track3->id),
])->then(function (Batch $batch) {
    // All jobs completed successfully
})->catch(function (Batch $batch, Throwable $e) {
    // First batch job failure detected
})->finally(function (Batch $batch) {
    // The batch has finished executing
})->dispatch();
```

## 1.11. Navigation

**← Previous:** [Laravel Octane FrankenPHP Guide](040-laravel-octane-frankenphp-guide.md)

**Next →** [Laravel Data Guide](060-laravel-data-guide.md)

---

**🎵 Chinook Music Store Implementation**

This Laravel Horizon implementation guide provides comprehensive queue management and monitoring capabilities for the Chinook music store application, including:

- **Advanced Queue Management**: Specialized queues for music processing, customer notifications, analytics, and taxonomy operations
- **Real-Time Monitoring**: Live dashboard with Chinook-specific metrics and performance insights
- **Auto-Scaling Workers**: Dynamic worker scaling based on queue depth and processing requirements
- **Production Deployment**: Supervisor configuration and zero-downtime deployment strategies
- **Enhanced Alerting**: Proactive monitoring with Slack integration for critical queue issues
- **Performance Optimization**: Memory management and job batching for high-throughput operations
- **Taxonomy Integration**: Specialized queue handling for aliziodev/laravel-taxonomy operations

The implementation leverages Laravel Horizon's advanced capabilities while providing Chinook-specific optimizations for music catalog processing, customer engagement, and business intelligence operations.
