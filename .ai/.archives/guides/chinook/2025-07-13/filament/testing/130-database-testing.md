# Database Testing Guide

This guide covers comprehensive database testing strategies for the Chinook application, including migration testing, model relationships, data integrity, and performance validation.

## Table of Contents

- [Overview](#overview)
- [Migration Testing](#migration-testing)
- [Model Relationship Testing](#model-relationship-testing)
- [Data Integrity Testing](#data-integrity-testing)
- [Query Performance Testing](#query-performance-testing)
- [Transaction Testing](#transaction-testing)
- [Constraint Testing](#constraint-testing)
- [Best Practices](#best-practices)

## Overview

Database testing ensures data integrity, relationship consistency, and optimal performance across all database operations in the Chinook application.

### Testing Objectives

- **Data Integrity**: Verify constraints and validation rules
- **Relationships**: Test model associations and cascading operations
- **Performance**: Validate query efficiency and optimization
- **Transactions**: Ensure ACID compliance and rollback behavior

## Migration Testing

### Migration Execution Testing

```php
<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_artists_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('artists'));
        
        $this->assertTrue(Schema::hasColumns('artists', [
            'id', 'public_id', 'name', 'biography', 'country',
            'website', 'created_by', 'updated_by', 'created_at',
            'updated_at', 'deleted_at'
        ]));
    }

    public function test_albums_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('albums'));
        
        $this->assertTrue(Schema::hasColumns('albums', [
            'id', 'public_id', 'title', 'artist_id', 'release_year',
            'genre', 'created_by', 'updated_by', 'created_at',
            'updated_at', 'deleted_at'
        ]));
    }

    public function test_tracks_table_structure(): void
    {
        $this->assertTrue(Schema::hasTable('tracks'));
        
        $this->assertTrue(Schema::hasColumns('tracks', [
            'id', 'public_id', 'name', 'album_id', 'media_type_id',
            'genre_id', 'composer', 'milliseconds', 'bytes',
            'unit_price', 'created_by', 'updated_by', 'created_at',
            'updated_at', 'deleted_at'
        ]));
    }

    public function test_foreign_key_constraints(): void
    {
        // Test albums.artist_id foreign key
        $this->assertTrue(Schema::hasColumn('albums', 'artist_id'));
        
        // Test tracks.album_id foreign key
        $this->assertTrue(Schema::hasColumn('tracks', 'album_id'));
        
        // Test tracks.media_type_id foreign key
        $this->assertTrue(Schema::hasColumn('tracks', 'media_type_id'));
    }
}
```

## Model Relationship Testing

### One-to-Many Relationships

```php
<?php

namespace Tests\Feature\Database;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_has_many_albums(): void
    {
        $artist = Artist::factory()->create();
        $albums = Album::factory()->count(3)->create([
            'artist_id' => $artist->id
        ]);

        $this->assertCount(3, $artist->albums);
        $this->assertTrue($artist->albums->contains($albums->first()));
    }

    public function test_album_belongs_to_artist(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create([
            'artist_id' => $artist->id
        ]);

        $this->assertEquals($artist->id, $album->artist->id);
        $this->assertEquals($artist->name, $album->artist->name);
    }

    public function test_album_has_many_tracks(): void
    {
        $album = Album::factory()->create();
        $tracks = Track::factory()->count(5)->create([
            'album_id' => $album->id
        ]);

        $this->assertCount(5, $album->tracks);
        $this->assertTrue($album->tracks->contains($tracks->first()));
    }

    public function test_track_belongs_to_album(): void
    {
        $album = Album::factory()->create();
        $track = Track::factory()->create([
            'album_id' => $album->id
        ]);

        $this->assertEquals($album->id, $track->album->id);
        $this->assertEquals($album->title, $track->album->title);
    }
}
```

### Polymorphic Relationships

```php
public function test_categorizable_polymorphic_relationship(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->create();
    $category = Category::factory()->create(['name' => 'Rock']);

    // Test artist categorization
    $artist->categories()->attach($category);
    $this->assertTrue($artist->categories->contains($category));

    // Test album categorization
    $album->categories()->attach($category);
    $this->assertTrue($album->categories->contains($category));

    // Test category items
    $this->assertTrue($category->artists->contains($artist));
    $this->assertTrue($category->albums->contains($album));
}

public function test_taggable_polymorphic_relationship(): void
{
    $artist = Artist::factory()->create();
    $tag = Tag::factory()->create(['name' => 'popular']);

    $artist->attachTag($tag);

    $this->assertTrue($artist->tags->contains($tag));
    $this->assertEquals(1, $artist->tags()->count());
}
```

## Data Integrity Testing

### Constraint Validation

```php
public function test_unique_constraints(): void
{
    $artist = Artist::factory()->create(['name' => 'Unique Artist']);

    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Artist::factory()->create(['name' => 'Unique Artist']);
}

public function test_not_null_constraints(): void
{
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Artist::create([
        'name' => null, // Should fail due to NOT NULL constraint
    ]);
}

public function test_foreign_key_constraints(): void
{
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Album::create([
        'title' => 'Test Album',
        'artist_id' => 99999, // Non-existent artist ID
    ]);
}
```

### Soft Delete Behavior

```php
public function test_soft_delete_behavior(): void
{
    $artist = Artist::factory()->create();
    $artistId = $artist->id;

    $artist->delete();

    // Verify soft deletion
    $this->assertSoftDeleted($artist);
    $this->assertDatabaseHas('artists', [
        'id' => $artistId,
    ]);
    $this->assertDatabaseMissing('artists', [
        'id' => $artistId,
        'deleted_at' => null,
    ]);
}

public function test_cascade_soft_delete(): void
{
    $artist = Artist::factory()
        ->has(Album::factory()->has(Track::factory()->count(3))->count(2))
        ->create();

    $artist->delete();

    // Verify cascade soft deletion
    $this->assertSoftDeleted($artist);
    
    foreach ($artist->albums as $album) {
        $this->assertSoftDeleted($album);
        
        foreach ($album->tracks as $track) {
            $this->assertSoftDeleted($track);
        }
    }
}
```

## Query Performance Testing

### N+1 Query Prevention

```php
public function test_eager_loading_prevents_n_plus_one(): void
{
    Artist::factory()
        ->has(Album::factory()->count(5))
        ->count(10)
        ->create();

    // Test without eager loading (should have many queries)
    \DB::enableQueryLog();
    $artists = Artist::all();
    foreach ($artists as $artist) {
        $artist->albums->count(); // This triggers N+1
    }
    $queriesWithoutEagerLoading = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    // Test with eager loading (should have fewer queries)
    \DB::enableQueryLog();
    $artists = Artist::with('albums')->get();
    foreach ($artists as $artist) {
        $artist->albums->count(); // This doesn't trigger additional queries
    }
    $queriesWithEagerLoading = count(\DB::getQueryLog());
    \DB::disableQueryLog();

    $this->assertLessThan($queriesWithoutEagerLoading, $queriesWithEagerLoading);
}

public function test_query_optimization_with_indexes(): void
{
    // Create test data
    Artist::factory()->count(1000)->create();

    $startTime = microtime(true);
    
    // Query that should use index
    Artist::where('name', 'like', 'Test%')->get();
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    // Assert query executes within reasonable time (adjust threshold as needed)
    $this->assertLessThan(0.1, $executionTime, 'Query took too long, check indexes');
}
```

## Transaction Testing

### Transaction Rollback Testing

```php
public function test_transaction_rollback_on_failure(): void
{
    $initialArtistCount = Artist::count();

    try {
        \DB::transaction(function () {
            Artist::create(['name' => 'Test Artist']);
            
            // Force an exception
            throw new \Exception('Simulated failure');
        });
    } catch (\Exception $e) {
        // Expected exception
    }

    // Verify rollback occurred
    $this->assertEquals($initialArtistCount, Artist::count());
}

public function test_nested_transaction_behavior(): void
{
    $initialCount = Artist::count();

    \DB::transaction(function () use ($initialCount) {
        Artist::create(['name' => 'Outer Transaction Artist']);
        
        try {
            \DB::transaction(function () {
                Artist::create(['name' => 'Inner Transaction Artist']);
                throw new \Exception('Inner transaction failure');
            });
        } catch (\Exception $e) {
            // Handle inner transaction failure
        }
        
        // Outer transaction should still be valid
        Artist::create(['name' => 'Another Outer Artist']);
    });

    // Verify only outer transaction artists were created
    $this->assertEquals($initialCount + 2, Artist::count());
}
```

## Constraint Testing

### Database Constraint Validation

```php
public function test_check_constraints(): void
{
    $this->expectException(\Illuminate\Database\QueryException::class);
    
    Track::create([
        'name' => 'Test Track',
        'album_id' => Album::factory()->create()->id,
        'unit_price' => -1.00, // Should fail check constraint
    ]);
}

public function test_enum_constraints(): void
{
    $customer = Customer::factory()->create();
    
    // Valid status
    $customer->update(['status' => 'active']);
    $this->assertEquals('active', $customer->fresh()->status);
    
    // Invalid status should fail
    $this->expectException(\Illuminate\Database\QueryException::class);
    $customer->update(['status' => 'invalid_status']);
}
```

## Best Practices

### Testing Database Performance

1. **Use Realistic Data Volumes**: Test with data sizes similar to production
2. **Monitor Query Counts**: Use query logging to detect N+1 problems
3. **Test Index Effectiveness**: Verify queries use appropriate indexes
4. **Validate Constraint Performance**: Ensure constraints don't slow down operations

### Data Consistency Testing

1. **Test Concurrent Operations**: Verify behavior under concurrent access
2. **Validate Referential Integrity**: Test foreign key relationships
3. **Check Cascade Behavior**: Verify cascade updates and deletes
4. **Test Transaction Boundaries**: Ensure proper transaction scope

---

## Related Documentation

- **[API Testing](110-api-testing.md)** - API endpoint testing strategies
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[RBAC Testing](100-rbac-testing.md)** - Role-based access control testing

---

## Navigation

**← Previous:** [API Testing](110-api-testing.md)

**Next →** [Performance Testing](130-performance-testing.md)
