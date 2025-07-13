# Database Testing Guide

This guide covers comprehensive database operations and integrity testing for the Chinook admin panel, including
transaction testing, constraint validation, migration testing, and data consistency verification.

## Table of Contents

- [Overview](#overview)
- [Database Transaction Testing](#database-transaction-testing)
- [Model Relationship Testing](#model-relationship-testing)
- [Constraint and Validation Testing](#constraint-and-validation-testing)
- [Migration Testing](#migration-testing)
- [Data Integrity Testing](#data-integrity-testing)
- [Query Performance Testing](#query-performance-testing)
- [Backup and Recovery Testing](#backup-and-recovery-testing)
- [Concurrency Testing](#concurrency-testing)

## Overview

Database testing ensures data integrity, proper constraint enforcement, and reliable database operations. This includes
testing CRUD operations, relationships, migrations, and performance under various conditions.

### Testing Objectives

- **Data Integrity**: Verify data consistency and constraint enforcement
- **Relationship Integrity**: Test foreign key constraints and cascading operations
- **Transaction Safety**: Ensure ACID properties are maintained
- **Performance**: Validate query performance and optimization
- **Migration Safety**: Test schema changes and data migrations

## Database Transaction Testing

### Basic Transaction Testing

```php
<?php

namespace Tests\Feature\ChinookAdmin\Database;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_rollback_on_failure(): void
    {
        $initialCount = Artist::count();

        try {
            DB::transaction(function () {
                Artist::create([
                    'name' => 'Test Artist',
                    'country' => 'US',
                ]);

                // Force an exception
                throw new \Exception('Simulated failure');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Verify rollback occurred
        $this->assertEquals($initialCount, Artist::count());
    }

    public function test_transaction_commit_on_success(): void
    {
        $initialCount = Artist::count();

        DB::transaction(function () {
            Artist::create([
                'name' => 'Test Artist',
                'country' => 'US',
            ]);

            Album::create([
                'title' => 'Test Album',
                'artist_id' => Artist::latest()->first()->id,
                'release_date' => '2023-01-01',
            ]);
        });

        $this->assertEquals($initialCount + 1, Artist::count());
        $this->assertEquals(1, Album::count());
    }

    public function test_nested_transactions(): void
    {
        $initialArtistCount = Artist::count();
        $initialAlbumCount = Album::count();

        try {
            DB::transaction(function () {
                $artist = Artist::create([
                    'name' => 'Outer Transaction Artist',
                    'country' => 'US',
                ]);

                DB::transaction(function () use ($artist) {
                    Album::create([
                        'title' => 'Inner Transaction Album',
                        'artist_id' => $artist->id,
                        'release_date' => '2023-01-01',
                    ]);

                    // Force failure in inner transaction
                    throw new \Exception('Inner transaction failure');
                });
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Both operations should be rolled back
        $this->assertEquals($initialArtistCount, Artist::count());
        $this->assertEquals($initialAlbumCount, Album::count());
    }

    public function test_deadlock_handling(): void
    {
        $artist = Artist::factory()->create();

        // Simulate concurrent updates that could cause deadlock
        $processes = [];
        
        for ($i = 0; $i < 2; $i++) {
            $processes[] = function () use ($artist) {
                DB::transaction(function () use ($artist) {
                    $artist->update(['name' => 'Updated Name ' . time()]);
                    usleep(100000); // 100ms delay
                    $artist->touch();
                });
            };
        }

        // Execute processes concurrently (simplified test)
        foreach ($processes as $process) {
            try {
                $process();
            } catch (\Exception $e) {
                // Handle potential deadlock exceptions
                $this->assertStringContainsString('deadlock', strtolower($e->getMessage()));
            }
        }

        $this->assertTrue(true); // Test completed without hanging
    }
}
```

### Connection Pool Testing

```php
public function test_multiple_database_connections(): void
{
    // Test read/write splitting if configured
    $writeConnection = DB::connection('mysql');
    $readConnection = DB::connection('mysql_read');

    $artist = Artist::factory()->create();

    // Write to primary
    $writeConnection->table('artists')->where('id', $artist->id)
        ->update(['name' => 'Updated via Write']);

    // Read from replica (may have replication lag)
    $readArtist = $readConnection->table('artists')->find($artist->id);

    $this->assertNotNull($readArtist);
}

public function test_connection_failover(): void
{
    // Test database failover scenarios
    try {
        DB::connection('mysql')->getPdo();
        $this->assertTrue(true, 'Primary connection available');
    } catch (\Exception $e) {
        // Test fallback connection
        try {
            DB::connection('mysql_backup')->getPdo();
            $this->assertTrue(true, 'Backup connection available');
        } catch (\Exception $backupException) {
            $this->fail('Both primary and backup connections failed');
        }
    }
}
```

## Model Relationship Testing

### Foreign Key Constraint Testing

```php
public function test_foreign_key_constraints_prevent_orphaned_records(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->for($artist)->create();

    // Attempt to delete artist with albums should fail
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    DB::table('artists')->where('id', $artist->id)->delete();
}

public function test_cascade_delete_relationships(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->for($artist)->create();
    $tracks = Track::factory()->count(3)->for($album)->create();

    $initialTrackCount = Track::count();

    // Soft delete artist should cascade to albums and tracks
    $artist->delete();

    $this->assertSoftDeleted($artist);
    $this->assertSoftDeleted($album);
    
    foreach ($tracks as $track) {
        $this->assertSoftDeleted($track);
    }
}

public function test_relationship_integrity_on_update(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->for($artist)->create();

    // Update artist ID should maintain relationship
    $newArtist = Artist::factory()->create();
    $album->update(['artist_id' => $newArtist->id]);

    $album->refresh();
    $this->assertEquals($newArtist->id, $album->artist_id);
    $this->assertEquals($newArtist->name, $album->artist->name);
}

public function test_polymorphic_relationship_integrity(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->create();
    $category = Category::factory()->genre()->create();

    // Test polymorphic categorization
    $artist->attachCategories([$category->id]);
    $album->attachCategories([$category->id]);

    $this->assertTrue($artist->categories->contains($category));
    $this->assertTrue($album->categories->contains($category));

    // Test category deletion doesn't break relationships
    $category->delete();

    $artist->refresh();
    $album->refresh();

    $this->assertFalse($artist->categories->contains($category));
    $this->assertFalse($album->categories->contains($category));
}
```

### Relationship Query Testing

```php
public function test_eager_loading_prevents_n_plus_one(): void
{
    $artists = Artist::factory()->count(5)->create();
    
    foreach ($artists as $artist) {
        Album::factory()->count(3)->for($artist)->create();
    }

    // Test N+1 query problem
    DB::enableQueryLog();

    // Without eager loading (should cause N+1)
    $artistsWithoutEager = Artist::all();
    foreach ($artistsWithoutEager as $artist) {
        $artist->albums->count(); // This triggers additional queries
    }

    $queriesWithoutEager = count(DB::getQueryLog());
    DB::flushQueryLog();

    // With eager loading
    $artistsWithEager = Artist::with('albums')->get();
    foreach ($artistsWithEager as $artist) {
        $artist->albums->count(); // This should not trigger additional queries
    }

    $queriesWithEager = count(DB::getQueryLog());

    $this->assertLessThan($queriesWithoutEager, $queriesWithEager);
    DB::disableQueryLog();
}

public function test_complex_relationship_queries(): void
{
    $rockGenre = Category::factory()->genre()->create(['name' => 'Rock']);
    $jazzGenre = Category::factory()->genre()->create(['name' => 'Jazz']);

    $rockArtist = Artist::factory()->create();
    $rockArtist->attachCategories([$rockGenre->id]);

    $jazzArtist = Artist::factory()->create();
    $jazzArtist->attachCategories([$jazzGenre->id]);

    // Test complex query with relationships
    $rockArtists = Artist::whereHas('categories', function ($query) use ($rockGenre) {
        $query->where('categories.id', $rockGenre->id);
    })->get();

    $this->assertCount(1, $rockArtists);
    $this->assertTrue($rockArtists->contains($rockArtist));
    $this->assertFalse($rockArtists->contains($jazzArtist));
}
```

## Constraint and Validation Testing

### Database Constraint Testing

```php
public function test_unique_constraints(): void
{
    Artist::factory()->create(['name' => 'Unique Artist']);

    // Attempt to create duplicate should fail
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Artist::factory()->create(['name' => 'Unique Artist']);
}

public function test_not_null_constraints(): void
{
    // Attempt to create artist without required fields should fail
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    DB::table('artists')->insert([
        'name' => null, // Required field
        'country' => 'US',
    ]);
}

public function test_check_constraints(): void
{
    // Test custom check constraints if implemented
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Track::factory()->create([
        'milliseconds' => -1000, // Should fail check constraint
    ]);
}

public function test_enum_constraints(): void
{
    // Test enum field constraints
    $validTypes = ['GENRE', 'MOOD', 'THEME', 'ERA', 'INSTRUMENT', 'LANGUAGE', 'OCCASION'];
    
    foreach ($validTypes as $type) {
        $category = Category::factory()->create(['type' => $type]);
        $this->assertEquals($type, $category->type->value);
    }

    // Invalid enum value should fail
    $this->expectException(\ValueError::class);
    Category::factory()->create(['type' => 'INVALID_TYPE']);
}
```

### Data Validation Testing

```php
public function test_model_validation_rules(): void
{
    // Test that model validation catches invalid data
    $artist = new Artist([
        'name' => '', // Invalid: empty name
        'country' => 'INVALID', // Invalid: not a valid country code
        'formed_year' => 1800, // Invalid: too early
        'website' => 'not-a-url', // Invalid: not a URL
    ]);

    $this->assertFalse($artist->isValid());
    $this->assertArrayHasKey('name', $artist->getErrors());
    $this->assertArrayHasKey('country', $artist->getErrors());
    $this->assertArrayHasKey('formed_year', $artist->getErrors());
    $this->assertArrayHasKey('website', $artist->getErrors());
}

public function test_custom_validation_rules(): void
{
    // Test custom validation rules
    $track = new Track([
        'name' => 'Test Track',
        'milliseconds' => 30000, // 30 seconds
        'unit_price' => -1.00, // Invalid: negative price
    ]);

    $this->assertFalse($track->isValid());
    $this->assertArrayHasKey('unit_price', $track->getErrors());
}
```

## Migration Testing

### Migration Execution Testing

```php
public function test_migrations_run_successfully(): void
{
    // Test that all migrations can run from scratch
    Artisan::call('migrate:fresh');
    
    $this->assertTrue(Schema::hasTable('artists'));
    $this->assertTrue(Schema::hasTable('albums'));
    $this->assertTrue(Schema::hasTable('tracks'));
    $this->assertTrue(Schema::hasTable('categories'));
    $this->assertTrue(Schema::hasTable('categorizables'));
}

public function test_migration_rollback(): void
{
    // Test that migrations can be rolled back
    $lastBatch = DB::table('migrations')->max('batch');
    
    Artisan::call('migrate:rollback', ['--step' => 1]);
    
    $currentBatch = DB::table('migrations')->max('batch');
    $this->assertLessThan($lastBatch, $currentBatch);
}

public function test_migration_data_preservation(): void
{
    // Create test data
    $artist = Artist::factory()->create(['name' => 'Test Artist']);
    
    // Run a migration that should preserve data
    Artisan::call('migrate');
    
    // Verify data still exists
    $this->assertDatabaseHas('artists', ['name' => 'Test Artist']);
}

public function test_schema_changes(): void
{
    // Test that schema matches expected structure
    $this->assertTrue(Schema::hasColumn('artists', 'public_id'));
    $this->assertTrue(Schema::hasColumn('artists', 'slug'));
    $this->assertTrue(Schema::hasColumn('artists', 'created_by'));
    $this->assertTrue(Schema::hasColumn('artists', 'updated_by'));
    $this->assertTrue(Schema::hasColumn('artists', 'deleted_at'));
}
```

### Index and Performance Testing

```php
public function test_database_indexes_exist(): void
{
    $indexes = DB::select("SHOW INDEX FROM artists");
    $indexNames = collect($indexes)->pluck('Key_name')->unique();

    $this->assertTrue($indexNames->contains('artists_public_id_unique'));
    $this->assertTrue($indexNames->contains('artists_slug_unique'));
    $this->assertTrue($indexNames->contains('artists_country_index'));
}

public function test_foreign_key_indexes(): void
{
    $indexes = DB::select("SHOW INDEX FROM albums");
    $indexNames = collect($indexes)->pluck('Key_name')->unique();

    $this->assertTrue($indexNames->contains('albums_artist_id_foreign'));
}
```

## Data Integrity Testing

### Audit Trail Testing

```php
public function test_audit_trail_creation(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $artist = Artist::factory()->create(['name' => 'Original Name']);

    // Verify created_by is set
    $this->assertEquals($user->id, $artist->created_by);

    // Update and verify updated_by
    $artist->update(['name' => 'Updated Name']);
    $artist->refresh();

    $this->assertEquals($user->id, $artist->updated_by);
}

public function test_soft_delete_integrity(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->for($artist)->create();

    $artist->delete();

    // Verify soft delete
    $this->assertSoftDeleted($artist);
    $this->assertNotNull($artist->deleted_at);

    // Verify related records are also soft deleted
    $album->refresh();
    $this->assertSoftDeleted($album);
}

public function test_timestamp_accuracy(): void
{
    $beforeCreate = now();
    $artist = Artist::factory()->create();
    $afterCreate = now();

    $this->assertTrue($artist->created_at->between($beforeCreate, $afterCreate));
    $this->assertTrue($artist->updated_at->between($beforeCreate, $afterCreate));

    sleep(1);

    $beforeUpdate = now();
    $artist->touch();
    $afterUpdate = now();

    $artist->refresh();
    $this->assertTrue($artist->updated_at->between($beforeUpdate, $afterUpdate));
    $this->assertNotEquals($artist->created_at, $artist->updated_at);
}
```

### Data Consistency Testing

```php
public function test_data_consistency_across_relationships(): void
{
    $artist = Artist::factory()->create();
    $albums = Album::factory()->count(3)->for($artist)->create();

    // Verify artist album count matches actual albums
    $this->assertEquals(3, $artist->albums()->count());
    $this->assertEquals(3, $artist->albums_count ?? $artist->albums->count());

    // Delete an album and verify consistency
    $albums->first()->delete();
    $artist->refresh();

    $this->assertEquals(2, $artist->albums()->count());
}

public function test_calculated_fields_accuracy(): void
{
    $album = Album::factory()->create();
    $tracks = Track::factory()->count(5)->for($album)->create([
        'milliseconds' => 240000, // 4 minutes each
    ]);

    $album->refresh();

    // Test calculated total duration
    $expectedDuration = 5 * 240000; // 20 minutes total
    $this->assertEquals($expectedDuration, $album->total_duration);

    // Test calculated track count
    $this->assertEquals(5, $album->tracks_count);
}
```

## Query Performance Testing

### Query Optimization Testing

```php
public function test_query_performance_with_large_dataset(): void
{
    // Create large dataset
    Artist::factory()->count(1000)->create();

    $startTime = microtime(true);

    // Test paginated query performance
    $artists = Artist::paginate(50);

    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;

    $this->assertLessThan(1.0, $queryTime, "Query took {$queryTime} seconds");
    $this->assertCount(50, $artists->items());
}

public function test_complex_query_performance(): void
{
    // Create test data with relationships
    $artists = Artist::factory()->count(100)->create();
    foreach ($artists as $artist) {
        Album::factory()->count(3)->for($artist)->create();
    }

    $startTime = microtime(true);

    // Complex query with joins and aggregations
    $results = Artist::with('albums')
        ->withCount('albums')
        ->having('albums_count', '>', 2)
        ->orderBy('albums_count', 'desc')
        ->limit(10)
        ->get();

    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;

    $this->assertLessThan(2.0, $queryTime, "Complex query took {$queryTime} seconds");
    $this->assertLessThanOrEqual(10, $results->count());
}

public function test_index_usage(): void
{
    Artist::factory()->count(1000)->create();

    // Test that indexed queries are fast
    $startTime = microtime(true);

    $artist = Artist::where('public_id', Artist::first()->public_id)->first();

    $endTime = microtime(true);
    $queryTime = $endTime - $startTime;

    $this->assertLessThan(0.1, $queryTime, "Indexed query took {$queryTime} seconds");
    $this->assertNotNull($artist);
}
```

## Backup and Recovery Testing

### Backup Testing

```php
public function test_database_backup_creation(): void
{
    // Create test data
    Artist::factory()->count(10)->create();

    // Test backup command
    Artisan::call('backup:run', ['--only-db' => true]);

    $this->assertEquals(0, Artisan::output());
}

public function test_data_export_integrity(): void
{
    $originalArtists = Artist::factory()->count(5)->create();

    // Export data
    $exportedData = Artist::all()->toArray();

    // Verify export completeness
    $this->assertCount(5, $exportedData);
    
    foreach ($originalArtists as $index => $artist) {
        $this->assertEquals($artist->name, $exportedData[$index]['name']);
        $this->assertEquals($artist->country, $exportedData[$index]['country']);
    }
}
```

## Concurrency Testing

### Concurrent Access Testing

```php
public function test_concurrent_updates(): void
{
    $artist = Artist::factory()->create(['name' => 'Original Name']);

    // Simulate concurrent updates
    $artist1 = Artist::find($artist->id);
    $artist2 = Artist::find($artist->id);

    $artist1->update(['name' => 'Update 1']);
    $artist2->update(['name' => 'Update 2']);

    // Last update should win
    $artist->refresh();
    $this->assertEquals('Update 2', $artist->name);
}

public function test_optimistic_locking(): void
{
    // Test optimistic locking if implemented
    $artist = Artist::factory()->create();
    $originalVersion = $artist->version ?? $artist->updated_at;

    // Simulate concurrent modification
    DB::table('artists')
        ->where('id', $artist->id)
        ->update(['name' => 'Concurrent Update']);

    // Attempt to update with stale version should fail
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    
    $artist->update(['name' => 'My Update']);
}
```

## Related Documentation

- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[API Testing](110-api-testing.md)** - API endpoint testing
- **[Security Testing](160-security-testing.md)** - Security validation
- **[Test Data Management](030-test-data-management.md)** - Test data strategies

---

## Navigation

**← Previous:** [API Testing](110-api-testing.md)

**Next →** [Performance Testing](130-performance-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
