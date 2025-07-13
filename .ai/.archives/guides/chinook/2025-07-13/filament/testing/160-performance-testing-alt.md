# Performance Testing Guide

This guide covers comprehensive performance testing strategies for the Chinook Filament admin panel, including load
testing, stress testing, performance monitoring, and optimization validation.

## Table of Contents

- [Overview](#overview)
- [Load Testing Setup](#load-testing-setup)
- [Application Performance Testing](#application-performance-testing)
- [Database Performance Testing](#database-performance-testing)
- [Frontend Performance Testing](#frontend-performance-testing)
- [API Performance Testing](#api-performance-testing)
- [Memory and Resource Testing](#memory-and-resource-testing)
- [Stress Testing](#stress-testing)
- [Performance Monitoring](#performance-monitoring)

## Overview

Performance testing ensures the Chinook admin panel can handle expected load, performs efficiently under stress, and
maintains acceptable response times. This includes testing various performance aspects from database queries to user
interface responsiveness.

### Testing Objectives

- **Load Capacity**: Determine maximum concurrent user capacity
- **Response Times**: Validate acceptable response times under load
- **Resource Usage**: Monitor memory, CPU, and database performance
- **Scalability**: Test horizontal and vertical scaling capabilities
- **Bottleneck Identification**: Identify and resolve performance bottlenecks

## Load Testing Setup

### K6 Load Testing Configuration

```javascript
// tests/Performance/load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// Custom metrics
export let errorRate = new Rate('errors');

export let options = {
  stages: [
    { duration: '2m', target: 10 },   // Ramp up to 10 users
    { duration: '5m', target: 10 },   // Stay at 10 users
    { duration: '2m', target: 20 },   // Ramp up to 20 users
    { duration: '5m', target: 20 },   // Stay at 20 users
    { duration: '2m', target: 50 },   // Ramp up to 50 users
    { duration: '5m', target: 50 },   // Stay at 50 users
    { duration: '5m', target: 0 },    // Ramp down to 0 users
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // 95% of requests under 2s
    http_req_failed: ['rate<0.1'],     // Error rate under 10%
    errors: ['rate<0.1'],              // Custom error rate under 10%
  },
};

// Test data
const credentials = {
  email: 'admin@test.com',
  password: 'password123',
};

export function setup() {
  // Login and get session token
  let loginRes = http.post('http://localhost:8000/chinook-admin/login', credentials);
  
  check(loginRes, {
    'login successful': (r) => r.status === 302,
  });

  return {
    sessionCookie: loginRes.cookies['laravel_session'][0].value,
    csrfToken: loginRes.body.match(/name="_token" value="([^"]+)"/)[1],
  };
}

export default function(data) {
  let params = {
    cookies: {
      laravel_session: data.sessionCookie,
    },
    headers: {
      'X-CSRF-TOKEN': data.csrfToken,
    },
  };

  // Test different admin panel endpoints
  testArtistsList(params);
  testArtistDetail(params);
  testArtistCreate(params);
  testDashboard(params);
  
  sleep(1);
}

function testArtistsList(params) {
  let response = http.get('http://localhost:8000/chinook-admin/artists', params);
  
  let success = check(response, {
    'artists list status 200': (r) => r.status === 200,
    'artists list response time < 1s': (r) => r.timings.duration < 1000,
    'artists list contains table': (r) => r.body.includes('table'),
  });

  errorRate.add(!success);
}

function testArtistDetail(params) {
  // Get first artist ID from the list
  let listResponse = http.get('http://localhost:8000/chinook-admin/artists', params);
  let artistId = extractFirstArtistId(listResponse.body);
  
  if (artistId) {
    let response = http.get(`http://localhost:8000/chinook-admin/artists/${artistId}`, params);
    
    let success = check(response, {
      'artist detail status 200': (r) => r.status === 200,
      'artist detail response time < 1s': (r) => r.timings.duration < 1000,
    });

    errorRate.add(!success);
  }
}

function testArtistCreate(params) {
  let response = http.get('http://localhost:8000/chinook-admin/artists/create', params);
  
  let success = check(response, {
    'artist create form status 200': (r) => r.status === 200,
    'artist create form response time < 1s': (r) => r.timings.duration < 1000,
    'artist create form contains form': (r) => r.body.includes('form'),
  });

  errorRate.add(!success);
}

function testDashboard(params) {
  let response = http.get('http://localhost:8000/chinook-admin', params);
  
  let success = check(response, {
    'dashboard status 200': (r) => r.status === 200,
    'dashboard response time < 2s': (r) => r.timings.duration < 2000,
  });

  errorRate.add(!success);
}

function extractFirstArtistId(html) {
  let match = html.match(/\/artists\/(\d+)/);
  return match ? match[1] : null;
}
```

### Laravel Performance Testing

```php
// tests/Performance/ApplicationPerformanceTest.php
<?php

namespace Tests\Performance;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        $artists = Artist::factory()->count(100)->create();
        
        foreach ($artists as $artist) {
            $albums = Album::factory()->count(3)->for($artist)->create();
            
            foreach ($albums as $album) {
                Track::factory()->count(12)->for($album)->create();
            }
        }
    }

    public function test_artist_index_performance(): void
    {
        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $responseTime, 
            "Artist index took {$responseTime} seconds");
    }

    public function test_artist_index_with_pagination_performance(): void
    {
        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists?page=1&per_page=25');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(0.5, $responseTime, 
            "Paginated artist index took {$responseTime} seconds");
    }

    public function test_artist_search_performance(): void
    {
        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists?search=test');

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(0.8, $responseTime, 
            "Artist search took {$responseTime} seconds");
    }

    public function test_artist_detail_with_relationships_performance(): void
    {
        $artist = Artist::with('albums.tracks')->first();

        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->get("/chinook-admin/artists/{$artist->id}");

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(1.5, $responseTime, 
            "Artist detail with relationships took {$responseTime} seconds");
    }

    public function test_bulk_operations_performance(): void
    {
        $artists = Artist::take(50)->get();
        $artistIds = $artists->pluck('id')->toArray();

        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)
            ->post('/chinook-admin/artists/bulk-actions/activate', [
                'records' => $artistIds,
            ]);

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertRedirect();
        $this->assertLessThan(3.0, $responseTime, 
            "Bulk operation on 50 records took {$responseTime} seconds");
    }
}
```

## Application Performance Testing

### Memory Usage Testing

```php
public function test_memory_usage_within_limits(): void
{
    $initialMemory = memory_get_usage(true);

    // Perform memory-intensive operations
    $artists = Artist::with(['albums.tracks', 'categories'])->get();

    $peakMemory = memory_get_peak_usage(true);
    $memoryUsed = $peakMemory - $initialMemory;

    // Should not use more than 128MB for this operation
    $this->assertLessThan(128 * 1024 * 1024, $memoryUsed, 
        "Memory usage: " . ($memoryUsed / 1024 / 1024) . " MB");
}

public function test_memory_leak_detection(): void
{
    $initialMemory = memory_get_usage(true);

    // Perform operations that might cause memory leaks
    for ($i = 0; $i < 100; $i++) {
        $artist = Artist::factory()->create();
        $artist->delete();
        unset($artist);
    }

    // Force garbage collection
    gc_collect_cycles();

    $finalMemory = memory_get_usage(true);
    $memoryDifference = $finalMemory - $initialMemory;

    // Memory usage should not increase significantly
    $this->assertLessThan(10 * 1024 * 1024, $memoryDifference, 
        "Potential memory leak detected: " . ($memoryDifference / 1024 / 1024) . " MB");
}

public function test_large_dataset_memory_efficiency(): void
{
    $initialMemory = memory_get_usage(true);

    // Process large dataset efficiently
    Artist::chunk(100, function ($artists) {
        foreach ($artists as $artist) {
            // Process each artist
            $artist->touch();
        }
    });

    $peakMemory = memory_get_peak_usage(true);
    $memoryUsed = $peakMemory - $initialMemory;

    // Chunked processing should use minimal memory
    $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 
        "Chunked processing used too much memory: " . ($memoryUsed / 1024 / 1024) . " MB");
}
```

### CPU Performance Testing

```php
public function test_cpu_intensive_operations(): void
{
    $startTime = microtime(true);

    // CPU-intensive operation: complex search with multiple filters
    $results = Artist::where('is_active', true)
        ->whereHas('albums', function ($query) {
            $query->where('release_date', '>', '2020-01-01');
        })
        ->whereHas('categories', function ($query) {
            $query->where('type', 'GENRE');
        })
        ->with(['albums.tracks', 'categories'])
        ->get();

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    $this->assertLessThan(2.0, $executionTime, 
        "Complex query took {$executionTime} seconds");
}

public function test_concurrent_request_simulation(): void
{
    $processes = [];
    $startTime = microtime(true);

    // Simulate concurrent requests
    for ($i = 0; $i < 5; $i++) {
        $processes[] = function () {
            return $this->actingAs($this->adminUser)
                ->get('/chinook-admin/artists');
        };
    }

    // Execute all processes
    $responses = [];
    foreach ($processes as $process) {
        $responses[] = $process();
    }

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;

    // All requests should complete successfully
    foreach ($responses as $response) {
        $response->assertStatus(200);
    }

    $this->assertLessThan(5.0, $totalTime, 
        "5 concurrent requests took {$totalTime} seconds");
}
```

## Database Performance Testing

### Query Performance Testing

```php
public function test_database_query_performance(): void
{
    DB::enableQueryLog();

    $startTime = microtime(true);

    // Complex query with joins and aggregations
    $results = DB::table('artists')
        ->join('albums', 'artists.id', '=', 'albums.artist_id')
        ->join('tracks', 'albums.id', '=', 'tracks.album_id')
        ->select('artists.name', DB::raw('COUNT(tracks.id) as track_count'))
        ->groupBy('artists.id', 'artists.name')
        ->having('track_count', '>', 10)
        ->orderBy('track_count', 'desc')
        ->limit(20)
        ->get();

    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $this->assertLessThan(1.0, $queryTime, 
        "Complex database query took {$queryTime} seconds");
    $this->assertLessThanOrEqual(1, count($queries), 
        "Query should be executed in single statement");
}

public function test_n_plus_one_query_prevention(): void
{
    DB::enableQueryLog();

    // Load artists with albums (should use eager loading)
    $artists = Artist::with('albums')->limit(10)->get();

    foreach ($artists as $artist) {
        $albumCount = $artist->albums->count();
    }

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Should only execute 2 queries: one for artists, one for albums
    $this->assertLessThanOrEqual(2, count($queries), 
        "N+1 query problem detected: " . count($queries) . " queries executed");
}

public function test_database_connection_pool_performance(): void
{
    $startTime = microtime(true);

    // Test multiple database operations
    for ($i = 0; $i < 50; $i++) {
        Artist::where('is_active', true)->count();
    }

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;

    $this->assertLessThan(2.0, $totalTime, 
        "50 database operations took {$totalTime} seconds");
}
```

### Index Performance Testing

```php
public function test_index_effectiveness(): void
{
    // Test query with indexed column
    $startTime = microtime(true);
    
    $artist = Artist::where('public_id', Artist::first()->public_id)->first();
    
    $endTime = microtime(true);
    $indexedQueryTime = $endTime - $startTime;

    // Test query without index (simulate)
    $startTime = microtime(true);
    
    $artists = Artist::where('biography', 'LIKE', '%test%')->get();
    
    $endTime = microtime(true);
    $nonIndexedQueryTime = $endTime - $startTime;

    $this->assertLessThan(0.1, $indexedQueryTime, 
        "Indexed query took {$indexedQueryTime} seconds");
    
    // Non-indexed query should be slower but still reasonable
    $this->assertLessThan(1.0, $nonIndexedQueryTime, 
        "Non-indexed query took {$nonIndexedQueryTime} seconds");
}
```

## Frontend Performance Testing

### Page Load Performance

```php
public function test_page_load_performance(): void
{
    $pages = [
        '/chinook-admin' => 2.0,
        '/chinook-admin/artists' => 1.5,
        '/chinook-admin/albums' => 1.5,
        '/chinook-admin/tracks' => 2.0,
        '/chinook-admin/customers' => 1.5,
    ];

    foreach ($pages as $url => $maxTime) {
        $startTime = microtime(true);

        $response = $this->actingAs($this->adminUser)->get($url);

        $endTime = microtime(true);
        $loadTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan($maxTime, $loadTime, 
            "Page {$url} took {$loadTime} seconds (max: {$maxTime}s)");
    }
}

public function test_asset_loading_performance(): void
{
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $content = $response->getContent();

    // Check that CSS and JS assets are properly minified and compressed
    $this->assertStringNotContainsString('/* ', $content, 
        'CSS comments should be removed in production');
    
    // Check for asset versioning (cache busting)
    $this->assertMatchesRegularExpression('/\.css\?v=\w+/', $content, 
        'CSS assets should have version parameters');
    $this->assertMatchesRegularExpression('/\.js\?v=\w+/', $content, 
        'JS assets should have version parameters');
}
```

## API Performance Testing

### API Endpoint Performance

```php
public function test_api_endpoint_performance(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    
    Sanctum::actingAs($user);

    $endpoints = [
        'GET /api/v1/artists' => 1.0,
        'GET /api/v1/albums' => 1.0,
        'GET /api/v1/tracks' => 1.5,
        'POST /api/v1/artists' => 0.5,
    ];

    foreach ($endpoints as $endpoint => $maxTime) {
        [$method, $url] = explode(' ', $endpoint);

        $startTime = microtime(true);

        if ($method === 'GET') {
            $response = $this->getJson($url);
        } else {
            $response = $this->postJson($url, [
                'name' => 'Performance Test Artist',
                'country' => 'US',
            ]);
        }

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $this->assertTrue($response->status() < 400);
        $this->assertLessThan($maxTime, $responseTime, 
            "API {$endpoint} took {$responseTime} seconds (max: {$maxTime}s)");
    }
}

public function test_api_pagination_performance(): void
{
    Sanctum::actingAs($this->adminUser);

    $pageSizes = [10, 25, 50, 100];

    foreach ($pageSizes as $pageSize) {
        $startTime = microtime(true);

        $response = $this->getJson("/api/v1/artists?per_page={$pageSize}");

        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $responseTime, 
            "API pagination with {$pageSize} items took {$responseTime} seconds");
    }
}
```

## Memory and Resource Testing

### Resource Monitoring

```php
public function test_resource_usage_monitoring(): void
{
    $initialStats = [
        'memory' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true),
        'time' => microtime(true),
    ];

    // Perform resource-intensive operations
    $this->performResourceIntensiveOperations();

    $finalStats = [
        'memory' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true),
        'time' => microtime(true),
    ];

    $memoryIncrease = $finalStats['memory'] - $initialStats['memory'];
    $peakMemoryUsed = $finalStats['peak_memory'] - $initialStats['peak_memory'];
    $executionTime = $finalStats['time'] - $initialStats['time'];

    $this->assertLessThan(64 * 1024 * 1024, $memoryIncrease, 
        "Memory increase: " . ($memoryIncrease / 1024 / 1024) . " MB");
    $this->assertLessThan(128 * 1024 * 1024, $peakMemoryUsed, 
        "Peak memory used: " . ($peakMemoryUsed / 1024 / 1024) . " MB");
    $this->assertLessThan(5.0, $executionTime, 
        "Execution time: {$executionTime} seconds");
}

private function performResourceIntensiveOperations(): void
{
    // Create and process large datasets
    $artists = Artist::factory()->count(500)->create();
    
    // Perform complex operations
    foreach ($artists->chunk(50) as $chunk) {
        $chunk->each(function ($artist) {
            $artist->update(['name' => $artist->name . ' (Updated)']);
        });
    }
}
```

## Stress Testing

### High Load Stress Testing

```php
public function test_high_concurrent_user_simulation(): void
{
    $users = User::factory()->count(20)->create();
    foreach ($users as $user) {
        $user->assignRole('Admin');
    }

    $startTime = microtime(true);
    $responses = [];

    // Simulate 20 concurrent users
    foreach ($users as $user) {
        $responses[] = $this->actingAs($user)
            ->get('/chinook-admin/artists');
    }

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;

    // All requests should succeed
    foreach ($responses as $response) {
        $response->assertStatus(200);
    }

    $this->assertLessThan(10.0, $totalTime, 
        "20 concurrent requests took {$totalTime} seconds");
}

public function test_database_connection_stress(): void
{
    $startTime = microtime(true);

    // Stress test database connections
    $promises = [];
    for ($i = 0; $i < 100; $i++) {
        $promises[] = function () {
            return Artist::count();
        };
    }

    // Execute all database operations
    $results = [];
    foreach ($promises as $promise) {
        $results[] = $promise();
    }

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;

    $this->assertCount(100, $results);
    $this->assertLessThan(5.0, $totalTime, 
        "100 database operations took {$totalTime} seconds");
}
```

## Performance Monitoring

### Performance Metrics Collection

```php
public function test_performance_metrics_collection(): void
{
    // Enable performance monitoring
    $metrics = [];

    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);

    // Perform monitored operations
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);

    $metrics['response_time'] = $endTime - $startTime;
    $metrics['memory_used'] = $endMemory - $startMemory;
    $metrics['status_code'] = $response->getStatusCode();

    // Log metrics for monitoring
    Log::info('Performance Metrics', $metrics);

    $this->assertLessThan(1.0, $metrics['response_time']);
    $this->assertLessThan(10 * 1024 * 1024, $metrics['memory_used']);
    $this->assertEquals(200, $metrics['status_code']);
}
```

## Related Documentation

- **[Database Testing](120-database-testing.md)** - Database operations and integrity testing
- **[API Testing](110-api-testing.md)** - API endpoint testing
- **[Browser Testing](140-browser-testing.md)** - End-to-end performance testing
- **[Security Testing](160-security-testing.md)** - Security performance validation

---

## Navigation

**← Previous:** [Database Testing](120-database-testing.md)

**Next →** [Browser Testing](140-browser-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
