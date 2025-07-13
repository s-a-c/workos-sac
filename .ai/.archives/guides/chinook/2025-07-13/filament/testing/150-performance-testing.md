# Performance Testing Guide

This guide covers comprehensive performance testing strategies for the Chinook Filament admin panel, including load testing, stress testing, and performance optimization validation.

## Table of Contents

- [Overview](#overview)
- [Load Testing](#load-testing)
- [Stress Testing](#stress-testing)
- [Database Performance Testing](#database-performance-testing)
- [Frontend Performance Testing](#frontend-performance-testing)
- [API Performance Testing](#api-performance-testing)
- [Memory Usage Testing](#memory-usage-testing)
- [Best Practices](#best-practices)

## Overview

Performance testing ensures the Chinook admin panel maintains optimal performance under various load conditions and usage patterns.

### Testing Objectives

- **Response Time**: Validate acceptable response times under load
- **Throughput**: Measure system capacity and concurrent user handling
- **Resource Usage**: Monitor CPU, memory, and database performance
- **Scalability**: Test system behavior as load increases

## Load Testing

### Basic Load Testing Setup

```php
<?php

namespace Tests\Performance;

use App\Models\User;
use App\Models\Artist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Super Admin');
        
        // Create test data
        Artist::factory()->count(1000)->create();
    }

    public function test_artist_index_page_load_time(): void
    {
        $this->actingAs($this->adminUser);
        
        $startTime = microtime(true);
        
        $response = $this->get(route('filament.chinook-admin.resources.artists.index'));
        
        $endTime = microtime(true);
        $loadTime = $endTime - $startTime;
        
        $response->assertSuccessful();
        $this->assertLessThan(2.0, $loadTime, 'Page load time exceeded 2 seconds');
    }

    public function test_concurrent_user_simulation(): void
    {
        $users = User::factory()->count(10)->create();
        $responses = [];
        $startTime = microtime(true);
        
        foreach ($users as $user) {
            $user->assignRole('Editor');
            
            $response = $this->actingAs($user)
                ->get(route('filament.chinook-admin.resources.artists.index'));
            
            $responses[] = $response->status();
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // All requests should succeed
        foreach ($responses as $status) {
            $this->assertEquals(200, $status);
        }
        
        // Average response time should be reasonable
        $averageTime = $totalTime / count($users);
        $this->assertLessThan(1.0, $averageTime, 'Average response time too high');
    }
}
```

### Livewire Component Performance

```php
public function test_livewire_component_performance(): void
{
    $this->actingAs($this->adminUser);
    
    $startTime = microtime(true);
    
    Livewire::test(\App\Filament\Resources\ArtistResource\Pages\ListArtists::class)
        ->assertSuccessful();
    
    $endTime = microtime(true);
    $renderTime = $endTime - $startTime;
    
    $this->assertLessThan(1.0, $renderTime, 'Livewire component render time too high');
}

public function test_table_pagination_performance(): void
{
    $this->actingAs($this->adminUser);
    
    $startTime = microtime(true);
    
    Livewire::test(\App\Filament\Resources\ArtistResource\Pages\ListArtists::class)
        ->set('tableRecordsPerPage', 100)
        ->assertSuccessful();
    
    $endTime = microtime(true);
    $paginationTime = $endTime - $startTime;
    
    $this->assertLessThan(1.5, $paginationTime, 'Pagination performance too slow');
}
```

## Stress Testing

### High Volume Data Testing

```php
public function test_large_dataset_handling(): void
{
    // Create large dataset
    Artist::factory()->count(10000)->create();
    
    $this->actingAs($this->adminUser);
    
    $startTime = microtime(true);
    
    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));
    
    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;
    
    $response->assertSuccessful();
    $this->assertLessThan(3.0, $loadTime, 'Large dataset handling too slow');
}

public function test_memory_usage_under_load(): void
{
    $initialMemory = memory_get_usage(true);
    
    // Simulate heavy operations
    for ($i = 0; $i < 100; $i++) {
        Artist::factory()->create();
    }
    
    $peakMemory = memory_get_peak_usage(true);
    $memoryIncrease = $peakMemory - $initialMemory;
    
    // Memory increase should be reasonable (adjust threshold as needed)
    $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory usage too high'); // 50MB
}
```

## Database Performance Testing

### Query Performance Testing

```php
public function test_database_query_performance(): void
{
    Artist::factory()->count(5000)->create();
    
    \DB::enableQueryLog();
    $startTime = microtime(true);
    
    // Test complex query
    $artists = Artist::with(['albums.tracks'])
        ->where('name', 'like', 'A%')
        ->orderBy('name')
        ->paginate(50);
    
    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;
    $queryCount = count(\DB::getQueryLog());
    
    \DB::disableQueryLog();
    
    $this->assertLessThan(0.5, $queryTime, 'Database query too slow');
    $this->assertLessThan(10, $queryCount, 'Too many database queries (N+1 problem?)');
}

public function test_index_effectiveness(): void
{
    Artist::factory()->count(10000)->create();
    
    $startTime = microtime(true);
    
    // Query that should use index
    $result = Artist::where('name', 'Test Artist 1')->first();
    
    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;
    
    $this->assertLessThan(0.01, $queryTime, 'Indexed query too slow');
}
```

### Connection Pool Testing

```php
public function test_database_connection_handling(): void
{
    $connections = [];
    
    // Simulate multiple concurrent connections
    for ($i = 0; $i < 20; $i++) {
        $connections[] = \DB::connection();
    }
    
    // All connections should be valid
    foreach ($connections as $connection) {
        $this->assertTrue($connection->getPdo() !== null);
    }
    
    // Test query execution on multiple connections
    $startTime = microtime(true);
    
    foreach ($connections as $connection) {
        $connection->select('SELECT 1');
    }
    
    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    
    $this->assertLessThan(1.0, $totalTime, 'Connection handling too slow');
}
```

## Frontend Performance Testing

### Asset Loading Performance

```php
public function test_asset_loading_performance(): void
{
    $this->actingAs($this->adminUser);
    
    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));
    
    $content = $response->getContent();
    
    // Check for asset optimization
    $this->assertStringContains('app.css', $content);
    $this->assertStringContains('app.js', $content);
    
    // Verify assets are minified (basic check)
    $this->assertStringNotContains('/* Development Mode */', $content);
}

public function test_page_size_optimization(): void
{
    $this->actingAs($this->adminUser);
    
    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));
    
    $contentLength = strlen($response->getContent());
    
    // Page size should be reasonable (adjust threshold as needed)
    $this->assertLessThan(500 * 1024, $contentLength, 'Page size too large'); // 500KB
}
```

## API Performance Testing

### API Response Time Testing

```php
public function test_api_response_times(): void
{
    $this->actingAs($this->adminUser);
    
    $endpoints = [
        '/api/v1/artists',
        '/api/v1/albums',
        '/api/v1/tracks',
    ];
    
    foreach ($endpoints as $endpoint) {
        $startTime = microtime(true);
        
        $response = $this->getJson($endpoint);
        
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;
        
        $response->assertSuccessful();
        $this->assertLessThan(1.0, $responseTime, "API endpoint {$endpoint} too slow");
    }
}

public function test_api_throughput(): void
{
    $this->actingAs($this->adminUser);
    
    $requestCount = 50;
    $startTime = microtime(true);
    
    for ($i = 0; $i < $requestCount; $i++) {
        $response = $this->getJson('/api/v1/artists');
        $response->assertSuccessful();
    }
    
    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    $throughput = $requestCount / $totalTime;
    
    $this->assertGreaterThan(10, $throughput, 'API throughput too low'); // 10 requests/second
}
```

## Memory Usage Testing

### Memory Leak Detection

```php
public function test_memory_leak_detection(): void
{
    $initialMemory = memory_get_usage(true);
    
    // Perform operations that might cause memory leaks
    for ($i = 0; $i < 1000; $i++) {
        $artist = Artist::factory()->make();
        unset($artist);
    }
    
    // Force garbage collection
    gc_collect_cycles();
    
    $finalMemory = memory_get_usage(true);
    $memoryIncrease = $finalMemory - $initialMemory;
    
    // Memory increase should be minimal after garbage collection
    $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Potential memory leak detected'); // 10MB
}

public function test_large_collection_memory_usage(): void
{
    $initialMemory = memory_get_usage(true);
    
    // Create large collection
    $artists = Artist::factory()->count(1000)->create();
    $collection = Artist::all();
    
    $peakMemory = memory_get_peak_usage(true);
    $memoryUsage = $peakMemory - $initialMemory;
    
    // Memory usage should be reasonable for collection size
    $this->assertLessThan(100 * 1024 * 1024, $memoryUsage, 'Collection memory usage too high'); // 100MB
    
    unset($collection);
    gc_collect_cycles();
}
```

## Best Practices

### Performance Testing Strategy

1. **Baseline Establishment**: Establish performance baselines for comparison
2. **Realistic Data**: Use production-like data volumes for testing
3. **Environment Consistency**: Test in environments similar to production
4. **Continuous Monitoring**: Integrate performance tests into CI/CD pipeline

### Optimization Guidelines

1. **Database Optimization**: Use proper indexing and query optimization
2. **Caching Strategy**: Implement appropriate caching mechanisms
3. **Asset Optimization**: Minify and compress frontend assets
4. **Memory Management**: Monitor and optimize memory usage patterns

### Monitoring and Alerting

1. **Performance Metrics**: Track key performance indicators
2. **Threshold Alerts**: Set up alerts for performance degradation
3. **Regular Audits**: Conduct regular performance audits
4. **Capacity Planning**: Plan for future growth and scaling needs

---

## Related Documentation

- **[Database Testing](110-database-testing.md)** - Database performance and integrity testing
- **[Browser Testing](140-browser-testing.md)** - End-to-end performance testing
- **[Security Testing](160-security-testing.md)** - Security performance considerations

---

## Navigation

**← Previous:** [Database Testing](110-database-testing.md)

**Next →** [Browser Testing](140-browser-testing.md)
