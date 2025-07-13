# 1. Performance Monitoring Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Monitoring Stack Setup](#2-monitoring-stack-setup)
- [3. Application Performance Monitoring](#3-application-performance-monitoring)
- [4. Database Performance Monitoring](#4-database-performance-monitoring)
- [5. Frontend Performance Monitoring](#5-frontend-performance-monitoring)
- [6. Music Streaming Metrics](#6-music-streaming-metrics)
- [7. Alerting Systems](#7-alerting-systems)
- [8. Dashboard Configuration](#8-dashboard-configuration)
- [9. Best Practices](#9-best-practices)
- [10. Navigation](#10-navigation)

## 1. Overview

This guide provides comprehensive performance monitoring strategies for the Chinook music platform. Monitoring covers application performance, database optimization, frontend metrics, streaming quality, and user experience analytics with real-time alerting and detailed dashboards.

## 2. Monitoring Stack Setup

### 2.1 Core Monitoring Infrastructure

```yaml
# docker-compose.monitoring.yml
version: '3.8'

services:
  # Prometheus for metrics collection
  prometheus:
    image: prom/prometheus:latest
    container_name: chinook_prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/etc/prometheus/console_libraries'
      - '--web.console.templates=/etc/prometheus/consoles'
      - '--storage.tsdb.retention.time=30d'
      - '--web.enable-lifecycle'

  # Grafana for visualization
  grafana:
    image: grafana/grafana:latest
    container_name: chinook_grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    volumes:
      - grafana_data:/var/lib/grafana
      - ./monitoring/grafana/dashboards:/etc/grafana/provisioning/dashboards
      - ./monitoring/grafana/datasources:/etc/grafana/provisioning/datasources

  # Redis for caching metrics
  redis_metrics:
    image: redis:alpine
    container_name: chinook_redis_metrics
    ports:
      - "6380:6379"
    volumes:
      - redis_metrics_data:/data

  # Elasticsearch for log aggregation
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.11.0
    container_name: chinook_elasticsearch
    environment:
      - discovery.type=single-node
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
      - xpack.security.enabled=false
    ports:
      - "9200:9200"
    volumes:
      - elasticsearch_data:/usr/share/elasticsearch/data

  # Kibana for log visualization
  kibana:
    image: docker.elastic.co/kibana/kibana:8.11.0
    container_name: chinook_kibana
    ports:
      - "5601:5601"
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    depends_on:
      - elasticsearch

volumes:
  prometheus_data:
  grafana_data:
  redis_metrics_data:
  elasticsearch_data:
```

### 2.2 Laravel Performance Monitoring Package Installation

```bash
# Install Laravel monitoring packages
composer require spatie/laravel-prometheus
composer require spatie/laravel-ray
composer require barryvdh/laravel-debugbar --dev
composer require itsgoingd/clockwork --dev

# Install frontend monitoring
npm install @sentry/browser @sentry/tracing
npm install web-vitals
```

### 2.3 Configuration Files

```php
// config/prometheus.php
<?php

return [
    'namespace' => 'chinook',
    'default_metric_labels' => [
        'app' => env('APP_NAME', 'chinook'),
        'environment' => env('APP_ENV', 'production'),
        'version' => env('APP_VERSION', '1.0.0'),
    ],
    'storage_adapter' => env('PROMETHEUS_STORAGE_ADAPTER', 'redis'),
    'redis' => [
        'host' => env('PROMETHEUS_REDIS_HOST', '127.0.0.1'),
        'port' => env('PROMETHEUS_REDIS_PORT', 6380),
        'password' => env('PROMETHEUS_REDIS_PASSWORD'),
        'database' => env('PROMETHEUS_REDIS_DB', 0),
    ],
];
```

## 3. Application Performance Monitoring

### 3.1 Custom Metrics Collection

```php
// app/Services/MetricsService.php
<?php

namespace App\Services;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class MetricsService
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        $adapter = new Redis([
            'host' => config('prometheus.redis.host'),
            'port' => config('prometheus.redis.port'),
            'password' => config('prometheus.redis.password'),
            'database' => config('prometheus.redis.database'),
        ]);
        
        $this->registry = new CollectorRegistry($adapter);
    }

    public function incrementCounter(string $name, array $labels = [], string $help = ''): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            config('prometheus.namespace'),
            $name,
            $help ?: "Counter for {$name}",
            array_keys($labels)
        );
        
        $counter->incBy(1, array_values($labels));
    }

    public function recordHistogram(string $name, float $value, array $labels = [], string $help = ''): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            config('prometheus.namespace'),
            $name,
            $help ?: "Histogram for {$name}",
            array_keys($labels),
            [0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0]
        );
        
        $histogram->observe($value, array_values($labels));
    }

    public function setGauge(string $name, float $value, array $labels = [], string $help = ''): void
    {
        $gauge = $this->registry->getOrRegisterGauge(
            config('prometheus.namespace'),
            $name,
            $help ?: "Gauge for {$name}",
            array_keys($labels)
        );
        
        $gauge->set($value, array_values($labels));
    }

    public function getMetrics(): string
    {
        $renderer = new \Prometheus\RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }
}
```

### 3.2 Middleware for Request Monitoring

```php
// app/Http/Middleware/MetricsMiddleware.php
<?php

namespace App\Http\Middleware;

use App\Services\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetricsMiddleware
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $startTime;
        
        // Record request metrics
        $this->metrics->incrementCounter('http_requests_total', [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? 'unknown',
            'status_code' => $response->getStatusCode(),
        ]);
        
        $this->metrics->recordHistogram('http_request_duration_seconds', $duration, [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? 'unknown',
        ]);
        
        // Record memory usage
        $this->metrics->recordHistogram('http_request_memory_usage_bytes', memory_get_peak_usage(true), [
            'route' => $request->route()?->getName() ?? 'unknown',
        ]);
        
        return $response;
    }
}
```

### 3.3 Livewire Component Monitoring

```php
// app/Http/Livewire/Concerns/MonitorsPerformance.php
<?php

namespace App\Http\Livewire\Concerns;

use App\Services\MetricsService;

trait MonitorsPerformance
{
    protected function bootMonitorsPerformance(): void
    {
        $this->beforeRender(function () {
            $this->startTime = microtime(true);
        });
        
        $this->afterRender(function () {
            if (isset($this->startTime)) {
                $duration = microtime(true) - $this->startTime;
                
                app(MetricsService::class)->recordHistogram(
                    'livewire_component_render_duration_seconds',
                    $duration,
                    [
                        'component' => static::class,
                        'view' => $this->getView(),
                    ]
                );
            }
        });
    }

    public function trackAction(string $action, callable $callback)
    {
        $startTime = microtime(true);
        
        try {
            $result = $callback();
            
            app(MetricsService::class)->incrementCounter('livewire_actions_total', [
                'component' => static::class,
                'action' => $action,
                'status' => 'success',
            ]);
            
            return $result;
        } catch (\Exception $e) {
            app(MetricsService::class)->incrementCounter('livewire_actions_total', [
                'component' => static::class,
                'action' => $action,
                'status' => 'error',
            ]);
            
            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;
            
            app(MetricsService::class)->recordHistogram(
                'livewire_action_duration_seconds',
                $duration,
                [
                    'component' => static::class,
                    'action' => $action,
                ]
            );
        }
    }
}
```

## 4. Database Performance Monitoring

### 4.1 Query Performance Tracking

```php
// app/Providers/DatabaseMonitoringServiceProvider.php
<?php

namespace App\Providers;

use App\Services\MetricsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class DatabaseMonitoringServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        DB::listen(function (QueryExecuted $query) {
            $metrics = app(MetricsService::class);

            // Record query execution time
            $metrics->recordHistogram('database_query_duration_seconds', $query->time / 1000, [
                'connection' => $query->connectionName,
                'type' => $this->getQueryType($query->sql),
            ]);

            // Count queries by type
            $metrics->incrementCounter('database_queries_total', [
                'connection' => $query->connectionName,
                'type' => $this->getQueryType($query->sql),
            ]);

            // Track slow queries
            if ($query->time > 1000) { // Queries taking more than 1 second
                $metrics->incrementCounter('database_slow_queries_total', [
                    'connection' => $query->connectionName,
                    'type' => $this->getQueryType($query->sql),
                ]);

                // Log slow query details
                logger()->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }

    private function getQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (str_starts_with($sql, 'SELECT')) return 'select';
        if (str_starts_with($sql, 'INSERT')) return 'insert';
        if (str_starts_with($sql, 'UPDATE')) return 'update';
        if (str_starts_with($sql, 'DELETE')) return 'delete';
        if (str_starts_with($sql, 'CREATE')) return 'create';
        if (str_starts_with($sql, 'ALTER')) return 'alter';
        if (str_starts_with($sql, 'DROP')) return 'drop';

        return 'other';
    }
}
```

### 4.2 Connection Pool Monitoring

```php
// app/Console/Commands/MonitorDatabaseConnections.php
<?php

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorDatabaseConnections extends Command
{
    protected $signature = 'monitor:database-connections';
    protected $description = 'Monitor database connection pool status';

    public function handle(MetricsService $metrics): void
    {
        $connections = config('database.connections');

        foreach ($connections as $name => $config) {
            try {
                // Test connection
                $pdo = DB::connection($name)->getPdo();

                // Record connection status
                $metrics->setGauge('database_connection_status', 1, [
                    'connection' => $name,
                ]);

                // Get connection pool stats (if available)
                if (method_exists($pdo, 'getAttribute')) {
                    $serverInfo = $pdo->getAttribute(\PDO::ATTR_SERVER_INFO);

                    // Parse connection stats from server info
                    if (preg_match('/Threads: (\d+)/', $serverInfo, $matches)) {
                        $metrics->setGauge('database_active_connections', (int)$matches[1], [
                            'connection' => $name,
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $metrics->setGauge('database_connection_status', 0, [
                    'connection' => $name,
                ]);

                $this->error("Connection {$name} failed: " . $e->getMessage());
            }
        }
    }
}
```

### 4.3 Cache Performance Monitoring

```php
// app/Services/CacheMonitoringService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheMonitoringService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    public function trackCacheOperation(string $operation, string $key, callable $callback)
    {
        $startTime = microtime(true);
        $store = Cache::getDefaultDriver();

        try {
            $result = $callback();

            $this->metrics->incrementCounter('cache_operations_total', [
                'operation' => $operation,
                'store' => $store,
                'status' => 'success',
            ]);

            // Track cache hits/misses
            if ($operation === 'get') {
                $status = $result !== null ? 'hit' : 'miss';
                $this->metrics->incrementCounter('cache_requests_total', [
                    'store' => $store,
                    'status' => $status,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->metrics->incrementCounter('cache_operations_total', [
                'operation' => $operation,
                'store' => $store,
                'status' => 'error',
            ]);

            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;

            $this->metrics->recordHistogram('cache_operation_duration_seconds', $duration, [
                'operation' => $operation,
                'store' => $store,
            ]);
        }
    }

    public function getCacheStats(): array
    {
        $stats = [];

        // Redis cache stats
        if (Cache::getDefaultDriver() === 'redis') {
            $redis = Cache::store('redis')->getRedis();
            $info = $redis->info();

            $stats['redis'] = [
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            ];

            // Calculate hit ratio
            $hits = $stats['redis']['keyspace_hits'];
            $misses = $stats['redis']['keyspace_misses'];
            $total = $hits + $misses;

            $stats['redis']['hit_ratio'] = $total > 0 ? ($hits / $total) * 100 : 0;

            // Record metrics
            $this->metrics->setGauge('cache_memory_usage_bytes', $stats['redis']['used_memory']);
            $this->metrics->setGauge('cache_hit_ratio_percent', $stats['redis']['hit_ratio']);
            $this->metrics->setGauge('cache_connected_clients', $stats['redis']['connected_clients']);
        }

        return $stats;
    }
}
```

## 5. Frontend Performance Monitoring

### 5.1 Web Vitals Tracking

```javascript
// resources/js/performance-monitoring.js
import { getCLS, getFID, getFCP, getLCP, getTTFB } from 'web-vitals';

class PerformanceMonitor {
    constructor() {
        this.metrics = [];
        this.initWebVitals();
        this.initCustomMetrics();
    }

    initWebVitals() {
        // Core Web Vitals
        getCLS(this.sendMetric.bind(this));
        getFID(this.sendMetric.bind(this));
        getFCP(this.sendMetric.bind(this));
        getLCP(this.sendMetric.bind(this));
        getTTFB(this.sendMetric.bind(this));
    }

    initCustomMetrics() {
        // Livewire performance tracking
        document.addEventListener('livewire:init', () => {
            Livewire.on('component:update', (component, updateType) => {
                this.trackLivewireUpdate(component, updateType);
            });
        });

        // Navigation timing
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.trackNavigationTiming();
            }, 0);
        });

        // Resource timing
        this.trackResourceTiming();
    }

    sendMetric(metric) {
        const data = {
            name: metric.name,
            value: metric.value,
            delta: metric.delta,
            id: metric.id,
            timestamp: Date.now(),
            url: window.location.href,
            userAgent: navigator.userAgent,
        };

        // Send to backend
        fetch('/api/metrics/web-vitals', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(data),
        }).catch(console.error);

        // Store locally for debugging
        this.metrics.push(data);
    }

    trackLivewireUpdate(component, updateType) {
        const startTime = performance.now();

        // Track update duration
        requestAnimationFrame(() => {
            const duration = performance.now() - startTime;

            this.sendMetric({
                name: 'livewire_update_duration',
                value: duration,
                delta: duration,
                id: `${component.name}-${Date.now()}`,
                metadata: {
                    component: component.name,
                    updateType: updateType,
                }
            });
        });
    }

    trackNavigationTiming() {
        const navigation = performance.getEntriesByType('navigation')[0];

        if (navigation) {
            const metrics = {
                dns_lookup: navigation.domainLookupEnd - navigation.domainLookupStart,
                tcp_connect: navigation.connectEnd - navigation.connectStart,
                request_response: navigation.responseEnd - navigation.requestStart,
                dom_processing: navigation.domContentLoadedEventEnd - navigation.responseEnd,
                load_complete: navigation.loadEventEnd - navigation.loadEventStart,
            };

            Object.entries(metrics).forEach(([name, value]) => {
                this.sendMetric({
                    name: `navigation_${name}`,
                    value: value,
                    delta: value,
                    id: `nav-${name}-${Date.now()}`,
                });
            });
        }
    }

    trackResourceTiming() {
        const observer = new PerformanceObserver((list) => {
            list.getEntries().forEach((entry) => {
                if (entry.entryType === 'resource') {
                    this.sendMetric({
                        name: 'resource_load_duration',
                        value: entry.duration,
                        delta: entry.duration,
                        id: `resource-${Date.now()}`,
                        metadata: {
                            name: entry.name,
                            type: entry.initiatorType,
                            size: entry.transferSize,
                        }
                    });
                }
            });
        });

        observer.observe({ entryTypes: ['resource'] });
    }

    // Error tracking
    trackError(error, context = {}) {
        const errorData = {
            name: 'javascript_error',
            message: error.message,
            stack: error.stack,
            filename: error.filename,
            lineno: error.lineno,
            colno: error.colno,
            timestamp: Date.now(),
            url: window.location.href,
            context: context,
        };

        fetch('/api/metrics/errors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(errorData),
        }).catch(console.error);
    }
}

// Initialize performance monitoring
const performanceMonitor = new PerformanceMonitor();

// Global error handler
window.addEventListener('error', (event) => {
    performanceMonitor.trackError(event.error, {
        type: 'global_error',
        target: event.target?.tagName,
    });
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', (event) => {
    performanceMonitor.trackError(new Error(event.reason), {
        type: 'unhandled_promise_rejection',
    });
});

export default performanceMonitor;
```

### 5.2 Backend Metrics API

```php
// app/Http/Controllers/Api/MetricsController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MetricsController extends Controller
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    public function webVitals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'value' => 'required|numeric',
            'delta' => 'required|numeric',
            'id' => 'required|string',
            'timestamp' => 'required|integer',
            'url' => 'required|url',
            'userAgent' => 'required|string',
            'metadata' => 'array',
        ]);

        // Record web vital metric
        $this->metrics->recordHistogram(
            'frontend_web_vitals_' . strtolower($validated['name']),
            $validated['value'],
            [
                'page' => parse_url($validated['url'], PHP_URL_PATH),
                'metric' => $validated['name'],
            ]
        );

        // Store detailed data for analysis
        logger()->channel('performance')->info('Web Vital Recorded', $validated);

        return response()->json(['status' => 'recorded']);
    }

    public function errors(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'message' => 'required|string',
            'stack' => 'nullable|string',
            'filename' => 'nullable|string',
            'lineno' => 'nullable|integer',
            'colno' => 'nullable|integer',
            'timestamp' => 'required|integer',
            'url' => 'required|url',
            'context' => 'array',
        ]);

        // Record error metric
        $this->metrics->incrementCounter('frontend_errors_total', [
            'page' => parse_url($validated['url'], PHP_URL_PATH),
            'type' => $validated['context']['type'] ?? 'unknown',
        ]);

        // Log error for investigation
        logger()->channel('errors')->error('Frontend Error', $validated);

        return response()->json(['status' => 'recorded']);
    }

    public function export(): string
    {
        return $this->metrics->getMetrics();
    }
}
```

## 6. Music Streaming Metrics

### 6.1 Audio Streaming Performance

```php
// app/Services/StreamingMetricsService.php
<?php

namespace App\Services;

class StreamingMetricsService
{
    public function __construct(
        private MetricsService $metrics
    ) {}

    public function trackStreamStart(int $trackId, string $quality, string $format): void
    {
        $this->metrics->incrementCounter('audio_streams_started_total', [
            'quality' => $quality,
            'format' => $format,
        ]);

        $this->metrics->setGauge('active_streams_current', $this->getActiveStreamCount());
    }

    public function trackStreamEnd(int $trackId, float $duration, float $bufferTime): void
    {
        $this->metrics->incrementCounter('audio_streams_completed_total');

        $this->metrics->recordHistogram('audio_stream_duration_seconds', $duration);

        $this->metrics->recordHistogram('audio_buffer_time_seconds', $bufferTime);

        // Calculate buffer ratio
        $bufferRatio = $duration > 0 ? ($bufferTime / $duration) * 100 : 0;
        $this->metrics->recordHistogram('audio_buffer_ratio_percent', $bufferRatio);

        $this->metrics->setGauge('active_streams_current', $this->getActiveStreamCount());
    }

    public function trackStreamError(int $trackId, string $errorType, string $errorMessage): void
    {
        $this->metrics->incrementCounter('audio_stream_errors_total', [
            'error_type' => $errorType,
        ]);

        logger()->channel('streaming')->error('Stream Error', [
            'track_id' => $trackId,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
        ]);
    }

    public function trackBandwidthUsage(float $bytesTransferred, float $duration): void
    {
        $bandwidth = $duration > 0 ? $bytesTransferred / $duration : 0;

        $this->metrics->recordHistogram('audio_bandwidth_bytes_per_second', $bandwidth);
        $this->metrics->recordHistogram('audio_data_transferred_bytes', $bytesTransferred);
    }

    private function getActiveStreamCount(): int
    {
        // Implementation depends on your streaming architecture
        // This could query Redis, database, or streaming server
        return cache()->remember('active_streams_count', 30, function () {
            // Example implementation
            return \DB::table('active_streams')->count();
        });
    }
}
```

### 6.2 Frontend Streaming Metrics

```javascript
// resources/js/streaming-monitor.js
class StreamingMonitor {
    constructor() {
        this.activeStreams = new Map();
        this.initAudioEventListeners();
    }

    initAudioEventListeners() {
        document.addEventListener('audio-stream-start', (event) => {
            this.trackStreamStart(event.detail);
        });

        document.addEventListener('audio-stream-end', (event) => {
            this.trackStreamEnd(event.detail);
        });

        document.addEventListener('audio-stream-error', (event) => {
            this.trackStreamError(event.detail);
        });
    }

    trackStreamStart(streamData) {
        const { trackId, quality, format } = streamData;

        this.activeStreams.set(trackId, {
            startTime: Date.now(),
            quality: quality,
            format: format,
            bufferEvents: [],
            errorEvents: [],
        });

        // Send metric to backend
        this.sendMetric('stream_start', {
            track_id: trackId,
            quality: quality,
            format: format,
            timestamp: Date.now(),
        });
    }

    trackStreamEnd(streamData) {
        const { trackId } = streamData;
        const stream = this.activeStreams.get(trackId);

        if (stream) {
            const duration = (Date.now() - stream.startTime) / 1000;
            const totalBufferTime = stream.bufferEvents.reduce((total, event) => {
                return total + (event.endTime - event.startTime);
            }, 0) / 1000;

            this.sendMetric('stream_end', {
                track_id: trackId,
                duration: duration,
                buffer_time: totalBufferTime,
                buffer_events: stream.bufferEvents.length,
                error_events: stream.errorEvents.length,
                timestamp: Date.now(),
            });

            this.activeStreams.delete(trackId);
        }
    }

    trackStreamError(errorData) {
        const { trackId, errorType, errorMessage } = errorData;
        const stream = this.activeStreams.get(trackId);

        if (stream) {
            stream.errorEvents.push({
                type: errorType,
                message: errorMessage,
                timestamp: Date.now(),
            });
        }

        this.sendMetric('stream_error', {
            track_id: trackId,
            error_type: errorType,
            error_message: errorMessage,
            timestamp: Date.now(),
        });
    }

    trackBufferEvent(trackId, eventType) {
        const stream = this.activeStreams.get(trackId);

        if (stream) {
            if (eventType === 'start') {
                stream.currentBufferStart = Date.now();
            } else if (eventType === 'end' && stream.currentBufferStart) {
                stream.bufferEvents.push({
                    startTime: stream.currentBufferStart,
                    endTime: Date.now(),
                });
                delete stream.currentBufferStart;
            }
        }
    }

    sendMetric(type, data) {
        fetch('/api/metrics/streaming', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ type, data }),
        }).catch(console.error);
    }
}

// Initialize streaming monitor
const streamingMonitor = new StreamingMonitor();

export default streamingMonitor;
```

## 7. Alerting Systems

### 7.1 Alert Rules Configuration

```yaml
# monitoring/prometheus/alert-rules.yml
groups:
  - name: chinook_application_alerts
    rules:
      - alert: HighErrorRate
        expr: rate(chinook_http_requests_total{status_code=~"5.."}[5m]) > 0.1
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value }} errors per second"

      - alert: HighResponseTime
        expr: histogram_quantile(0.95, rate(chinook_http_request_duration_seconds_bucket[5m])) > 2
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High response time detected"
          description: "95th percentile response time is {{ $value }} seconds"

      - alert: DatabaseSlowQueries
        expr: rate(chinook_database_slow_queries_total[5m]) > 0.05
        for: 3m
        labels:
          severity: warning
        annotations:
          summary: "High rate of slow database queries"
          description: "Slow query rate is {{ $value }} queries per second"

  - name: chinook_streaming_alerts
    rules:
      - alert: HighStreamErrorRate
        expr: rate(chinook_audio_stream_errors_total[5m]) > 0.02
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High streaming error rate"
          description: "Stream error rate is {{ $value }} errors per second"

      - alert: HighBufferRatio
        expr: histogram_quantile(0.90, rate(chinook_audio_buffer_ratio_percent_bucket[10m])) > 20
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High buffer ratio detected"
          description: "90th percentile buffer ratio is {{ $value }}%"

  - name: chinook_infrastructure_alerts
    rules:
      - alert: DatabaseConnectionDown
        expr: chinook_database_connection_status == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Database connection is down"
          description: "Database connection {{ $labels.connection }} is not responding"

      - alert: HighMemoryUsage
        expr: (chinook_cache_memory_usage_bytes / (1024*1024*1024)) > 8
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High cache memory usage"
          description: "Cache memory usage is {{ $value }}GB"
```

### 7.2 Alert Manager Configuration

```yaml
# monitoring/alertmanager/alertmanager.yml
global:
  smtp_smarthost: 'localhost:587'
  smtp_from: 'alerts@chinook.local'

route:
  group_by: ['alertname']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 1h
  receiver: 'web.hook'
  routes:
    - match:
        severity: critical
      receiver: 'critical-alerts'
    - match:
        severity: warning
      receiver: 'warning-alerts'

receivers:
  - name: 'web.hook'
    webhook_configs:
      - url: 'http://chinook-app:8000/api/alerts/webhook'

  - name: 'critical-alerts'
    email_configs:
      - to: 'devops@chinook.local'
        subject: 'CRITICAL: {{ .GroupLabels.alertname }}'
        body: |
          {{ range .Alerts }}
          Alert: {{ .Annotations.summary }}
          Description: {{ .Annotations.description }}
          {{ end }}
    slack_configs:
      - api_url: 'YOUR_SLACK_WEBHOOK_URL'
        channel: '#alerts-critical'
        title: 'Critical Alert: {{ .GroupLabels.alertname }}'
        text: '{{ range .Alerts }}{{ .Annotations.summary }}{{ end }}'

  - name: 'warning-alerts'
    email_configs:
      - to: 'team@chinook.local'
        subject: 'WARNING: {{ .GroupLabels.alertname }}'
        body: |
          {{ range .Alerts }}
          Alert: {{ .Annotations.summary }}
          Description: {{ .Annotations.description }}
          {{ end }}
```

## 8. Dashboard Configuration

### 8.1 Grafana Dashboard JSON

```json
{
  "dashboard": {
    "id": null,
    "title": "Chinook Application Performance",
    "tags": ["chinook", "performance"],
    "timezone": "browser",
    "panels": [
      {
        "id": 1,
        "title": "Request Rate",
        "type": "stat",
        "targets": [
          {
            "expr": "rate(chinook_http_requests_total[5m])",
            "legendFormat": "Requests/sec"
          }
        ],
        "fieldConfig": {
          "defaults": {
            "unit": "reqps"
          }
        }
      },
      {
        "id": 2,
        "title": "Response Time",
        "type": "timeseries",
        "targets": [
          {
            "expr": "histogram_quantile(0.50, rate(chinook_http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "50th percentile"
          },
          {
            "expr": "histogram_quantile(0.95, rate(chinook_http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "95th percentile"
          },
          {
            "expr": "histogram_quantile(0.99, rate(chinook_http_request_duration_seconds_bucket[5m]))",
            "legendFormat": "99th percentile"
          }
        ]
      },
      {
        "id": 3,
        "title": "Error Rate",
        "type": "timeseries",
        "targets": [
          {
            "expr": "rate(chinook_http_requests_total{status_code=~\"4..\"}[5m])",
            "legendFormat": "4xx errors"
          },
          {
            "expr": "rate(chinook_http_requests_total{status_code=~\"5..\"}[5m])",
            "legendFormat": "5xx errors"
          }
        ]
      },
      {
        "id": 4,
        "title": "Database Query Performance",
        "type": "timeseries",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(chinook_database_query_duration_seconds_bucket[5m]))",
            "legendFormat": "95th percentile query time"
          },
          {
            "expr": "rate(chinook_database_slow_queries_total[5m])",
            "legendFormat": "Slow queries/sec"
          }
        ]
      },
      {
        "id": 5,
        "title": "Streaming Performance",
        "type": "timeseries",
        "targets": [
          {
            "expr": "chinook_active_streams_current",
            "legendFormat": "Active streams"
          },
          {
            "expr": "rate(chinook_audio_stream_errors_total[5m])",
            "legendFormat": "Stream errors/sec"
          }
        ]
      }
    ],
    "time": {
      "from": "now-1h",
      "to": "now"
    },
    "refresh": "5s"
  }
}
```

## 9. Best Practices

### 9.1 Monitoring Guidelines

1. **Metric Naming**: Use consistent naming conventions with appropriate prefixes
2. **Label Management**: Keep label cardinality low to avoid performance issues
3. **Retention Policies**: Set appropriate retention periods for different metric types
4. **Alert Fatigue**: Configure meaningful alerts with proper thresholds
5. **Dashboard Organization**: Group related metrics and use consistent visualizations

### 9.2 Performance Optimization

1. **Sampling**: Use sampling for high-volume metrics to reduce overhead
2. **Batching**: Batch metric submissions to reduce network overhead
3. **Caching**: Cache expensive metric calculations
4. **Async Processing**: Process metrics asynchronously when possible
5. **Resource Limits**: Set appropriate resource limits for monitoring infrastructure

### 9.3 Security Considerations

1. **Data Sanitization**: Sanitize sensitive data in logs and metrics
2. **Access Controls**: Implement proper access controls for monitoring dashboards
3. **Encryption**: Use encryption for metric transmission and storage
4. **Audit Logging**: Log access to monitoring systems
5. **Regular Updates**: Keep monitoring tools updated with security patches

## 10. Navigation

**← Previous** [Testing Approaches Guide](160-testing-approaches-guide.md)
**Next →** [API Testing Guide](180-api-testing-guide.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/frontend/170-performance-monitoring-guide.md on 2025-07-11

*This guide provides comprehensive performance monitoring strategies for the Chinook music platform. Continue with the API testing guide for detailed testing methodologies.*

[⬆️ Back to Top](#1-performance-monitoring-guide)
