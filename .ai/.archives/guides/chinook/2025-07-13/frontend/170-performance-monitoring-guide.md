# Performance Monitoring Guide

## Table of Contents

- [Overview](#overview)
- [Monitoring Stack Setup](#monitoring-stack-setup)
- [Application Performance Monitoring](#application-performance-monitoring)
- [Database Performance Monitoring](#database-performance-monitoring)
- [Frontend Performance Monitoring](#frontend-performance-monitoring)
- [Music Streaming Metrics](#music-streaming-metrics)
- [Alerting Systems](#alerting-systems)
- [Dashboard Configuration](#dashboard-configuration)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide provides comprehensive performance monitoring strategies for the Chinook music platform. Monitoring covers application performance, database optimization, frontend metrics, streaming quality, and user experience analytics with real-time alerting and detailed dashboards.

## Monitoring Stack Setup

### Core Monitoring Infrastructure

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

### Laravel Performance Monitoring Package Installation

```bash
# Install monitoring packages
composer require spatie/laravel-prometheus
composer require spatie/laravel-ray
composer require barryvdh/laravel-debugbar --dev
composer require itsgoingd/clockwork --dev

# Install frontend monitoring
npm install web-vitals
npm install @sentry/browser
npm install @sentry/tracing

# Publish configurations
php artisan vendor:publish --provider="Spatie\Prometheus\PrometheusServiceProvider"
php artisan vendor:publish --provider="Barryvdh\Debugbar\ServiceProvider"
```

## Application Performance Monitoring

### Custom Metrics Collection Service

```php
<?php
// app/Services/MetricsService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Spatie\Prometheus\Facades\Prometheus;

class MetricsService
{
    private string $metricsPrefix = 'chinook_';
    
    public function recordPlayEvent(array $data): void
    {
        // Record play event metrics
        Prometheus::addGauge('track_plays_total')
            ->value(1)
            ->labels([
                'track_id' => $data['track_id'],
                'quality' => $data['quality'] ?? 'medium',
                'device_type' => $data['device_type'] ?? 'web',
            ]);
            
        // Update streaming quality metrics
        $this->updateStreamingMetrics($data);
        
        // Cache popular tracks
        $this->updatePopularityCache($data['track_id']);
    }
    
    public function recordSearchEvent(string $query, int $resultsCount): void
    {
        Prometheus::addGauge('search_queries_total')
            ->value(1)
            ->labels([
                'has_results' => $resultsCount > 0 ? 'true' : 'false',
                'result_count_range' => $this->getResultCountRange($resultsCount),
            ]);
            
        // Track search performance
        $this->recordSearchPerformance($query, $resultsCount);
    }
    
    public function recordPageLoad(string $page, float $loadTime): void
    {
        Prometheus::addHistogram('page_load_duration_seconds')
            ->observe($loadTime)
            ->labels(['page' => $page]);
            
        // Alert on slow pages
        if ($loadTime > 2.0) {
            $this->alertSlowPage($page, $loadTime);
        }
    }
    
    public function recordDatabaseQuery(string $query, float $duration): void
    {
        Prometheus::addHistogram('database_query_duration_seconds')
            ->observe($duration)
            ->labels([
                'query_type' => $this->getQueryType($query),
                'slow' => $duration > 0.1 ? 'true' : 'false',
            ]);
    }
    
    public function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_load' => sys_getloadavg(),
            'disk_usage' => disk_free_space('/'),
            'active_connections' => $this->getActiveConnections(),
            'cache_hit_ratio' => $this->getCacheHitRatio(),
            'queue_size' => $this->getQueueSize(),
        ];
    }
    
    private function updateStreamingMetrics(array $data): void
    {
        $key = "streaming_metrics:{$data['quality']}:{$data['device_type']}";
        
        Redis::pipeline(function ($pipe) use ($key, $data) {
            $pipe->hincrby($key, 'total_plays', 1);
            $pipe->hincrby($key, 'total_duration', $data['duration'] ?? 0);
            $pipe->expire($key, 3600); // 1 hour TTL
        });
    }
    
    private function updatePopularityCache(string $trackId): void
    {
        $key = "popular_tracks";
        Redis::zincrby($key, 1, $trackId);
        Redis::expire($key, 86400); // 24 hours
    }
    
    private function getActiveConnections(): int
    {
        return DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
    }
    
    private function getCacheHitRatio(): float
    {
        $stats = Redis::info('stats');
        $hits = $stats['keyspace_hits'] ?? 0;
        $misses = $stats['keyspace_misses'] ?? 0;
        
        return $hits + $misses > 0 ? $hits / ($hits + $misses) : 0;
    }
    
    private function getQueueSize(): int
    {
        return Redis::llen('queues:default');
    }
    
    private function getResultCountRange(int $count): string
    {
        if ($count === 0) return '0';
        if ($count <= 10) return '1-10';
        if ($count <= 50) return '11-50';
        if ($count <= 100) return '51-100';
        return '100+';
    }
    
    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        if (str_starts_with($query, 'SELECT')) return 'select';
        if (str_starts_with($query, 'INSERT')) return 'insert';
        if (str_starts_with($query, 'UPDATE')) return 'update';
        if (str_starts_with($query, 'DELETE')) return 'delete';
        return 'other';
    }
    
    private function alertSlowPage(string $page, float $loadTime): void
    {
        // Send alert to monitoring system
        logger()->warning('Slow page detected', [
            'page' => $page,
            'load_time' => $loadTime,
            'threshold' => 2.0,
        ]);
    }
}
```

## Database Performance Monitoring

### Query Performance Tracking

```php
<?php
// app/Providers/DatabaseMonitoringServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MetricsService;

class DatabaseMonitoringServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Monitor all database queries
        DB::listen(function ($query) {
            $metrics = app(MetricsService::class);
            
            // Record query metrics
            $metrics->recordDatabaseQuery(
                $query->sql,
                $query->time / 1000 // Convert to seconds
            );
            
            // Log slow queries
            if ($query->time > 100) { // Slower than 100ms
                Log::warning('Slow database query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
            
            // Track N+1 queries
            $this->detectNPlusOneQueries($query);
        });
    }
    
    private function detectNPlusOneQueries($query): void
    {
        static $queryPatterns = [];
        
        $pattern = preg_replace('/\d+/', '?', $query->sql);
        $queryPatterns[$pattern] = ($queryPatterns[$pattern] ?? 0) + 1;
        
        // Alert if same pattern executed many times
        if ($queryPatterns[$pattern] > 10) {
            Log::warning('Potential N+1 query detected', [
                'pattern' => $pattern,
                'count' => $queryPatterns[$pattern],
                'sql' => $query->sql,
            ]);
        }
    }
}
```

## Frontend Performance Monitoring

### Web Vitals and User Experience Tracking

```javascript
// resources/js/monitoring/performance-monitor.js

import { getCLS, getFID, getFCP, getLCP, getTTFB } from 'web-vitals';

class PerformanceMonitor {
    constructor() {
        this.metrics = {};
        this.setupWebVitals();
        this.setupCustomMetrics();
        this.setupErrorTracking();
    }

    setupWebVitals() {
        // Core Web Vitals
        getCLS(this.sendMetric.bind(this, 'CLS'));
        getFID(this.sendMetric.bind(this, 'FID'));
        getFCP(this.sendMetric.bind(this, 'FCP'));
        getLCP(this.sendMetric.bind(this, 'LCP'));
        getTTFB(this.sendMetric.bind(this, 'TTFB'));
    }

    setupCustomMetrics() {
        // Track Livewire component load times
        document.addEventListener('livewire:load', () => {
            this.trackComponentLoad('livewire-app');
        });

        // Track navigation timing
        window.addEventListener('load', () => {
            this.trackNavigationTiming();
        });

        // Track audio player performance
        this.trackAudioPlayerMetrics();
    }

    trackComponentLoad(componentName) {
        const startTime = performance.now();
        
        // Wait for component to be fully rendered
        requestAnimationFrame(() => {
            const loadTime = performance.now() - startTime;
            
            this.sendMetric('component-load', {
                name: componentName,
                value: loadTime,
                rating: loadTime < 100 ? 'good' : loadTime < 300 ? 'needs-improvement' : 'poor'
            });
        });
    }

    trackNavigationTiming() {
        const navigation = performance.getEntriesByType('navigation')[0];
        
        if (navigation) {
            this.sendMetric('page-load', {
                value: navigation.loadEventEnd - navigation.fetchStart,
                dns: navigation.domainLookupEnd - navigation.domainLookupStart,
                tcp: navigation.connectEnd - navigation.connectStart,
                request: navigation.responseStart - navigation.requestStart,
                response: navigation.responseEnd - navigation.responseStart,
                dom: navigation.domContentLoadedEventEnd - navigation.responseEnd,
            });
        }
    }

    trackAudioPlayerMetrics() {
        // Monitor audio loading and playback
        document.addEventListener('audio-load-start', (event) => {
            const startTime = performance.now();
            
            event.target.addEventListener('canplaythrough', () => {
                const loadTime = performance.now() - startTime;
                
                this.sendMetric('audio-load', {
                    value: loadTime,
                    quality: event.detail.quality,
                    size: event.detail.fileSize,
                    rating: loadTime < 2000 ? 'good' : loadTime < 5000 ? 'needs-improvement' : 'poor'
                });
            }, { once: true });
        });

        // Track buffering events
        document.addEventListener('audio-buffering', (event) => {
            this.sendMetric('audio-buffering', {
                duration: event.detail.duration,
                position: event.detail.position,
                quality: event.detail.quality,
            });
        });
    }

    setupErrorTracking() {
        // JavaScript errors
        window.addEventListener('error', (event) => {
            this.sendMetric('js-error', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                stack: event.error?.stack,
            });
        });

        // Promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.sendMetric('promise-rejection', {
                reason: event.reason,
                stack: event.reason?.stack,
            });
        });

        // Livewire errors
        document.addEventListener('livewire:error', (event) => {
            this.sendMetric('livewire-error', {
                component: event.detail.component,
                message: event.detail.message,
                stack: event.detail.stack,
            });
        });
    }

    sendMetric(name, data) {
        // Send to backend analytics
        fetch('/api/metrics/frontend', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
                metric: name,
                data: data,
                timestamp: Date.now(),
                url: window.location.href,
                user_agent: navigator.userAgent,
                connection: this.getConnectionInfo(),
            }),
        }).catch(error => {
            console.error('Failed to send metric:', error);
        });
    }

    getConnectionInfo() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        
        return connection ? {
            effective_type: connection.effectiveType,
            downlink: connection.downlink,
            rtt: connection.rtt,
            save_data: connection.saveData,
        } : null;
    }
}

// Initialize performance monitoring
const performanceMonitor = new PerformanceMonitor();
window.performanceMonitor = performanceMonitor;

export default PerformanceMonitor;
```

## Music Streaming Metrics

### Streaming Quality and Performance Tracking

```php
<?php
// app/Services/StreamingMetricsService.php

namespace App\Services;

use App\Models\Analytics\PlayEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class StreamingMetricsService
{
    public function trackStreamingSession(array $data): void
    {
        // Record streaming session metrics
        $sessionKey = "streaming_session:{$data['session_id']}";

        Redis::hmset($sessionKey, [
            'track_id' => $data['track_id'],
            'user_id' => $data['user_id'],
            'quality' => $data['quality'],
            'start_time' => now()->timestamp,
            'device_type' => $data['device_type'],
            'connection_type' => $data['connection_type'] ?? 'unknown',
        ]);

        Redis::expire($sessionKey, 3600); // 1 hour TTL
    }

    public function recordBufferingEvent(array $data): void
    {
        $bufferingKey = "buffering_events:" . date('Y-m-d-H');

        Redis::lpush($bufferingKey, json_encode([
            'track_id' => $data['track_id'],
            'user_id' => $data['user_id'],
            'duration' => $data['duration'],
            'position' => $data['position'],
            'quality' => $data['quality'],
            'connection_type' => $data['connection_type'],
            'timestamp' => now()->timestamp,
        ]));

        Redis::expire($bufferingKey, 86400); // 24 hours

        // Alert on excessive buffering
        $bufferingCount = Redis::llen($bufferingKey);
        if ($bufferingCount > 100) { // More than 100 buffering events per hour
            $this->alertHighBuffering($bufferingCount);
        }
    }

    public function getStreamingQualityMetrics(): array
    {
        $metrics = [];
        $qualities = ['low', 'medium', 'high', 'lossless'];

        foreach ($qualities as $quality) {
            $key = "streaming_metrics:{$quality}:*";
            $keys = Redis::keys($key);

            $totalPlays = 0;
            $totalDuration = 0;

            foreach ($keys as $k) {
                $data = Redis::hgetall($k);
                $totalPlays += $data['total_plays'] ?? 0;
                $totalDuration += $data['total_duration'] ?? 0;
            }

            $metrics[$quality] = [
                'total_plays' => $totalPlays,
                'total_duration' => $totalDuration,
                'avg_duration' => $totalPlays > 0 ? $totalDuration / $totalPlays : 0,
            ];
        }

        return $metrics;
    }

    public function getPopularTracks(int $limit = 50): array
    {
        $trackIds = Redis::zrevrange('popular_tracks', 0, $limit - 1, 'WITHSCORES');
        $tracks = [];

        for ($i = 0; $i < count($trackIds); $i += 2) {
            $tracks[] = [
                'track_id' => $trackIds[$i],
                'play_count' => $trackIds[$i + 1],
            ];
        }

        return $tracks;
    }

    public function getStreamingHealthScore(): float
    {
        // Calculate overall streaming health based on multiple factors
        $bufferingRate = $this->getBufferingRate();
        $errorRate = $this->getStreamingErrorRate();
        $qualityDistribution = $this->getQualityDistribution();
        $avgLoadTime = $this->getAverageLoadTime();

        // Weighted scoring (0-100)
        $score = 100;
        $score -= ($bufferingRate * 30); // Buffering heavily impacts score
        $score -= ($errorRate * 40); // Errors are critical
        $score -= ($avgLoadTime > 3 ? 20 : 0); // Slow loading penalty
        $score += ($qualityDistribution['high'] + $qualityDistribution['lossless']) * 0.1; // Bonus for high quality

        return max(0, min(100, $score));
    }

    private function getBufferingRate(): float
    {
        $totalSessions = Redis::get('total_streaming_sessions:' . date('Y-m-d')) ?? 1;
        $bufferingEvents = Redis::llen('buffering_events:' . date('Y-m-d-H')) ?? 0;

        return $bufferingEvents / $totalSessions;
    }

    private function getStreamingErrorRate(): float
    {
        $totalAttempts = Redis::get('streaming_attempts:' . date('Y-m-d')) ?? 1;
        $errors = Redis::get('streaming_errors:' . date('Y-m-d')) ?? 0;

        return $errors / $totalAttempts;
    }

    private function getQualityDistribution(): array
    {
        $metrics = $this->getStreamingQualityMetrics();
        $total = array_sum(array_column($metrics, 'total_plays'));

        if ($total === 0) {
            return ['low' => 0, 'medium' => 0, 'high' => 0, 'lossless' => 0];
        }

        return [
            'low' => ($metrics['low']['total_plays'] / $total) * 100,
            'medium' => ($metrics['medium']['total_plays'] / $total) * 100,
            'high' => ($metrics['high']['total_plays'] / $total) * 100,
            'lossless' => ($metrics['lossless']['total_plays'] / $total) * 100,
        ];
    }

    private function getAverageLoadTime(): float
    {
        return Cache::remember('avg_audio_load_time', 300, function () {
            return PlayEvent::where('created_at', '>=', now()->subHour())
                ->avg('load_time_ms') / 1000 ?? 0;
        });
    }

    private function alertHighBuffering(int $count): void
    {
        logger()->warning('High buffering rate detected', [
            'buffering_events_per_hour' => $count,
            'threshold' => 100,
            'hour' => date('Y-m-d H:00'),
        ]);
    }
}
```

## Alerting Systems

### Comprehensive Alert Configuration

```php
<?php
// app/Services/AlertingService.php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertingService
{
    private array $alertChannels;
    private array $thresholds;

    public function __construct()
    {
        $this->alertChannels = config('monitoring.alert_channels');
        $this->thresholds = config('monitoring.thresholds');
    }

    public function checkPerformanceThresholds(): void
    {
        $metrics = app(MetricsService::class)->getSystemMetrics();

        // Check memory usage
        if ($metrics['memory_usage'] > $this->thresholds['memory_critical']) {
            $this->sendAlert('critical', 'High memory usage detected', [
                'current' => $this->formatBytes($metrics['memory_usage']),
                'threshold' => $this->formatBytes($this->thresholds['memory_critical']),
                'peak' => $this->formatBytes($metrics['memory_peak']),
            ]);
        }

        // Check CPU load
        $avgLoad = array_sum($metrics['cpu_load']) / count($metrics['cpu_load']);
        if ($avgLoad > $this->thresholds['cpu_critical']) {
            $this->sendAlert('critical', 'High CPU load detected', [
                'current' => round($avgLoad, 2),
                'threshold' => $this->thresholds['cpu_critical'],
                'load_1min' => $metrics['cpu_load'][0],
                'load_5min' => $metrics['cpu_load'][1],
                'load_15min' => $metrics['cpu_load'][2],
            ]);
        }

        // Check disk space
        $diskFree = $metrics['disk_usage'];
        $diskTotal = disk_total_space('/');
        $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        if ($diskUsagePercent > $this->thresholds['disk_critical']) {
            $this->sendAlert('critical', 'Low disk space detected', [
                'usage_percent' => round($diskUsagePercent, 2),
                'free_space' => $this->formatBytes($diskFree),
                'threshold' => $this->thresholds['disk_critical'] . '%',
            ]);
        }

        // Check cache hit ratio
        if ($metrics['cache_hit_ratio'] < $this->thresholds['cache_hit_ratio_warning']) {
            $this->sendAlert('warning', 'Low cache hit ratio detected', [
                'current' => round($metrics['cache_hit_ratio'] * 100, 2) . '%',
                'threshold' => $this->thresholds['cache_hit_ratio_warning'] * 100 . '%',
            ]);
        }

        // Check queue size
        if ($metrics['queue_size'] > $this->thresholds['queue_size_critical']) {
            $this->sendAlert('critical', 'Large queue size detected', [
                'current' => $metrics['queue_size'],
                'threshold' => $this->thresholds['queue_size_critical'],
            ]);
        }
    }

    public function checkStreamingHealth(): void
    {
        $streamingService = app(StreamingMetricsService::class);
        $healthScore = $streamingService->getStreamingHealthScore();

        if ($healthScore < $this->thresholds['streaming_health_critical']) {
            $this->sendAlert('critical', 'Streaming health score critical', [
                'score' => round($healthScore, 2),
                'threshold' => $this->thresholds['streaming_health_critical'],
                'metrics' => $streamingService->getStreamingQualityMetrics(),
            ]);
        } elseif ($healthScore < $this->thresholds['streaming_health_warning']) {
            $this->sendAlert('warning', 'Streaming health score low', [
                'score' => round($healthScore, 2),
                'threshold' => $this->thresholds['streaming_health_warning'],
            ]);
        }
    }

    public function sendAlert(string $severity, string $message, array $data = []): void
    {
        $alert = [
            'severity' => $severity,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'server' => gethostname(),
        ];

        // Log alert
        Log::channel('alerts')->{$severity}($message, $alert);

        // Send to configured channels
        foreach ($this->alertChannels as $channel => $config) {
            if ($config['enabled'] && $this->shouldSendToChannel($severity, $config)) {
                $this->sendToChannel($channel, $alert, $config);
            }
        }
    }

    private function sendToChannel(string $channel, array $alert, array $config): void
    {
        try {
            switch ($channel) {
                case 'slack':
                    $this->sendSlackAlert($alert, $config);
                    break;
                case 'email':
                    $this->sendEmailAlert($alert, $config);
                    break;
                case 'webhook':
                    $this->sendWebhookAlert($alert, $config);
                    break;
                case 'discord':
                    $this->sendDiscordAlert($alert, $config);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send alert to ' . $channel, [
                'error' => $e->getMessage(),
                'alert' => $alert,
            ]);
        }
    }

    private function sendSlackAlert(array $alert, array $config): void
    {
        $color = match($alert['severity']) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'good',
        };

        $payload = [
            'text' => "🚨 Chinook Alert: {$alert['message']}",
            'attachments' => [
                [
                    'color' => $color,
                    'fields' => [
                        [
                            'title' => 'Severity',
                            'value' => strtoupper($alert['severity']),
                            'short' => true,
                        ],
                        [
                            'title' => 'Environment',
                            'value' => $alert['environment'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Server',
                            'value' => $alert['server'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Timestamp',
                            'value' => $alert['timestamp'],
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];

        if (!empty($alert['data'])) {
            $payload['attachments'][0]['fields'][] = [
                'title' => 'Details',
                'value' => json_encode($alert['data'], JSON_PRETTY_PRINT),
                'short' => false,
            ];
        }

        Http::post($config['webhook_url'], $payload);
    }

    private function shouldSendToChannel(string $severity, array $config): bool
    {
        $severityLevels = ['info' => 1, 'warning' => 2, 'critical' => 3];
        $alertLevel = $severityLevels[$severity] ?? 1;
        $channelLevel = $severityLevels[$config['min_severity'] ?? 'info'] ?? 1;

        return $alertLevel >= $channelLevel;
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

## Dashboard Configuration

### Grafana Dashboard Setup

```json
{
  "dashboard": {
    "id": null,
    "title": "Chinook Music Platform - Performance Overview",
    "tags": ["chinook", "performance", "music"],
    "timezone": "browser",
    "panels": [
      {
        "id": 1,
        "title": "System Overview",
        "type": "stat",
        "targets": [
          {
            "expr": "up{job=\"chinook-app\"}",
            "legendFormat": "App Status"
          },
          {
            "expr": "process_resident_memory_bytes{job=\"chinook-app\"}",
            "legendFormat": "Memory Usage"
          }
        ],
        "gridPos": {"h": 8, "w": 12, "x": 0, "y": 0}
      },
      {
        "id": 2,
        "title": "Request Rate",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(http_requests_total{job=\"chinook-app\"}[5m])",
            "legendFormat": "{{method}} {{status}}"
          }
        ],
        "gridPos": {"h": 8, "w": 12, "x": 12, "y": 0}
      },
      {
        "id": 3,
        "title": "Response Times",
        "type": "graph",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket{job=\"chinook-app\"}[5m]))",
            "legendFormat": "95th percentile"
          },
          {
            "expr": "histogram_quantile(0.50, rate(http_request_duration_seconds_bucket{job=\"chinook-app\"}[5m]))",
            "legendFormat": "50th percentile"
          }
        ],
        "gridPos": {"h": 8, "w": 24, "x": 0, "y": 8}
      },
      {
        "id": 4,
        "title": "Database Performance",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(database_query_duration_seconds_sum{job=\"chinook-app\"}[5m]) / rate(database_query_duration_seconds_count{job=\"chinook-app\"}[5m])",
            "legendFormat": "Average Query Time"
          },
          {
            "expr": "rate(database_query_duration_seconds_count{job=\"chinook-app\", slow=\"true\"}[5m])",
            "legendFormat": "Slow Queries/sec"
          }
        ],
        "gridPos": {"h": 8, "w": 12, "x": 0, "y": 16}
      },
      {
        "id": 5,
        "title": "Streaming Metrics",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(track_plays_total{job=\"chinook-app\"}[5m])",
            "legendFormat": "Plays/sec - {{quality}}"
          },
          {
            "expr": "rate(audio_buffering_events_total{job=\"chinook-app\"}[5m])",
            "legendFormat": "Buffering Events/sec"
          }
        ],
        "gridPos": {"h": 8, "w": 12, "x": 12, "y": 16}
      },
      {
        "id": 6,
        "title": "Error Rates",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(http_requests_total{job=\"chinook-app\", status=~\"5..\"}[5m])",
            "legendFormat": "5xx Errors/sec"
          },
          {
            "expr": "rate(http_requests_total{job=\"chinook-app\", status=~\"4..\"}[5m])",
            "legendFormat": "4xx Errors/sec"
          }
        ],
        "gridPos": {"h": 8, "w": 24, "x": 0, "y": 24}
      }
    ],
    "time": {
      "from": "now-1h",
      "to": "now"
    },
    "refresh": "30s"
  }
}
```

### Prometheus Configuration

```yaml
# monitoring/prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - "chinook_alerts.yml"

alerting:
  alertmanagers:
    - static_configs:
        - targets:
          - alertmanager:9093

scrape_configs:
  - job_name: 'chinook-app'
    static_configs:
      - targets: ['app:8000']
    metrics_path: '/metrics'
    scrape_interval: 30s

  - job_name: 'chinook-database'
    static_configs:
      - targets: ['mysql-exporter:9104']
    scrape_interval: 30s

  - job_name: 'chinook-redis'
    static_configs:
      - targets: ['redis-exporter:9121']
    scrape_interval: 30s

  - job_name: 'chinook-nginx'
    static_configs:
      - targets: ['nginx-exporter:9113']
    scrape_interval: 30s
```

## Best Practices

### Performance Monitoring Checklist

1. **System Monitoring**
   - [ ] CPU, memory, and disk usage tracking
   - [ ] Network I/O monitoring
   - [ ] Process and service health checks
   - [ ] Log aggregation and analysis

2. **Application Monitoring**
   - [ ] Request/response time tracking
   - [ ] Error rate monitoring
   - [ ] Database query performance
   - [ ] Cache hit ratio monitoring

3. **User Experience Monitoring**
   - [ ] Core Web Vitals tracking
   - [ ] Page load time monitoring
   - [ ] Audio streaming quality metrics
   - [ ] Frontend error tracking

4. **Alerting Configuration**
   - [ ] Critical threshold alerts
   - [ ] Escalation procedures
   - [ ] Multiple notification channels
   - [ ] Alert fatigue prevention

5. **Dashboard Setup**
   - [ ] Real-time metrics visualization
   - [ ] Historical trend analysis
   - [ ] Custom business metrics
   - [ ] Mobile-friendly dashboards

### Monitoring Best Practices

1. **Metric Collection**
   - Use consistent naming conventions
   - Include relevant labels and dimensions
   - Avoid high-cardinality metrics
   - Implement proper sampling for high-volume events

2. **Alert Management**
   - Set meaningful thresholds based on SLAs
   - Implement alert suppression during maintenance
   - Use runbooks for common issues
   - Regular review and tuning of alerts

3. **Performance Optimization**
   - Monitor metrics collection overhead
   - Use efficient storage and retention policies
   - Implement metric aggregation where appropriate
   - Regular cleanup of old metrics data

4. **Security Considerations**
   - Secure metrics endpoints
   - Sanitize sensitive data in logs
   - Implement proper access controls
   - Regular security audits of monitoring infrastructure

## Navigation

**← Previous** [Testing Approaches Guide](160-testing-approaches-guide.md)
**Next →** [API Testing Guide](180-api-testing-guide.md)

---

*This guide provides comprehensive performance monitoring strategies for the Chinook music platform. Continue with the API testing guide for detailed testing methodologies.*
