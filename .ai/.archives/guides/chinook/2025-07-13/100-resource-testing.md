# Chinook Resource Testing Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Testing Framework Setup](#2-testing-framework-setup)
- [3. Model Testing](#3-model-testing)
- [4. API Resource Testing](#4-api-resource-testing)
- [5. Filament Resource Testing](#5-filament-resource-testing)
- [6. Database Testing](#6-database-testing)
- [7. Performance Testing](#7-performance-testing)
- [8. Security Testing](#8-security-testing)

## 1. Overview

This comprehensive guide covers testing strategies for all Chinook resources, including models, API endpoints, Filament
admin resources, and database operations using Pest PHP testing framework with Laravel 12 modern patterns.

### 1.1. Testing Philosophy

**Testing Principles:**

- **Test-Driven Development**: Write tests before implementation
- **Comprehensive Coverage**: Unit, feature, and integration tests
- **Performance Awareness**: Test query efficiency and response times
- **Security Focus**: Test authorization and data protection
- **Real-World Scenarios**: Test actual user workflows

### 1.2. Testing Stack

- **Framework**: Pest PHP with Laravel integration
- **Database**: SQLite in-memory for fast testing
- **Factories**: Model factories for test data generation
- **Mocking**: Mockery for external service testing
- **Coverage**: PHPUnit coverage reports

## 2. Testing Framework Setup

### 2.1. Pest Configuration

```php
// tests/Pest.php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    WithFaker::class
)->in('Feature', 'Unit');

// Global test helpers
function actingAsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function actingAsUser(): User
{
    return User::factory()->create();
}

function createArtistWithAlbums(int $albumCount = 3): \App\Models\Artist
{
    $artist = \App\Models\Artist::factory()->create();
    \App\Models\Album::factory()->count($albumCount)->create(['artist_id' => $artist->id]);
    return $artist;
}
```

### 2.2. Database Configuration

```php
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="QUEUE_DRIVER" value="sync"/>
```

### 2.3. Test Factories

```php
// database/factories/ArtistFactory.php
class ArtistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->name(),
            'public_id' => Str::ulid(),
            'slug' => fn(array $attributes) => Str::slug($attributes['name']),
            'bio' => $this->faker->paragraph(),
            'website' => $this->faker->url(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
    
    public function withAlbums(int $count = 3): static
    {
        return $this->afterCreating(function (Artist $artist) use ($count) {
            Album::factory()->count($count)->create(['artist_id' => $artist->id]);
        });
    }
}

// database/factories/AlbumFactory.php
class AlbumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'artist_id' => Artist::factory(),
            'public_id' => Str::ulid(),
            'slug' => fn(array $attributes) => Str::slug($attributes['title']),
            'release_date' => $this->faker->date(),
            'genre' => $this->faker->randomElement(['Rock', 'Pop', 'Jazz', 'Classical']),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
```

## 3. Model Testing

### 3.1. Model Relationships

```php
// tests/Unit/Models/ArtistTest.php
describe('Artist Model', function () {
    test('has many albums', function () {
        $artist = Artist::factory()->create();
        $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);
        
        expect($artist->albums)->toHaveCount(3);
        expect($artist->albums->first())->toBeInstanceOf(Album::class);
    });
    
    test('has many tracks through albums', function () {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);
        $tracks = Track::factory()->count(5)->create(['album_id' => $album->id]);
        
        expect($artist->tracks)->toHaveCount(5);
        expect($artist->tracks->first())->toBeInstanceOf(Track::class);
    });
    
    test('can have categories', function () {
        $artist = Artist::factory()->create();
        $category = Category::factory()->create(['type' => CategoryType::GENRE]);
        
        $artist->categories()->attach($category);
        
        expect($artist->categories)->toHaveCount(1);
        expect($artist->categories->first()->type)->toBe(CategoryType::GENRE);
    });
});
```

### 3.2. Model Attributes and Casting

```php
describe('Artist Attributes', function () {
    test('casts attributes correctly', function () {
        $artist = Artist::factory()->create([
            'is_active' => true,
            'metadata' => ['genre' => 'Rock', 'country' => 'USA'],
        ]);
        
        expect($artist->is_active)->toBeTrue();
        expect($artist->metadata)->toBeArray();
        expect($artist->metadata['genre'])->toBe('Rock');
    });
    
    test('generates slug from name', function () {
        $artist = Artist::factory()->create(['name' => 'The Beatles']);
        
        expect($artist->slug)->toBe('the-beatles');
    });
    
    test('generates public_id automatically', function () {
        $artist = Artist::factory()->create();
        
        expect($artist->public_id)->not->toBeNull();
        expect(strlen($artist->public_id))->toBe(26); // ULID length
    });
});
```

### 3.3. Model Scopes

```php
describe('Artist Scopes', function () {
    test('active scope returns only active artists', function () {
        Artist::factory()->create(['is_active' => true]);
        Artist::factory()->create(['is_active' => false]);
        
        $activeArtists = Artist::active()->get();
        
        expect($activeArtists)->toHaveCount(1);
        expect($activeArtists->first()->is_active)->toBeTrue();
    });
    
    test('with albums scope includes album count', function () {
        $artistWithAlbums = Artist::factory()->withAlbums(3)->create();
        $artistWithoutAlbums = Artist::factory()->create();
        
        $artists = Artist::withAlbums()->get();
        
        expect($artists)->toHaveCount(1);
        expect($artists->first()->id)->toBe($artistWithAlbums->id);
    });
});
```

## 4. API Resource Testing

### 4.1. API Endpoint Tests

```php
// tests/Feature/Api/ArtistApiTest.php
describe('Artist API', function () {
    test('can list artists', function () {
        $user = actingAsUser();
        $user->givePermissionTo('music-catalog.artists.view');
        
        Artist::factory()->count(5)->create();
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/artists');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'name', 'slug', 'bio', 'albums_count']
                     ],
                     'meta' => ['current_page', 'total', 'per_page']
                 ]);
        
        expect($response->json('data'))->toHaveCount(5);
    });
    
    test('can create artist with valid data', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('music-catalog.artists.create');
        
        $artistData = [
            'name' => 'New Artist',
            'bio' => 'Artist biography',
            'website' => 'https://example.com',
        ];
        
        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/artists', $artistData);
        
        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'New Artist']);
        
        $this->assertDatabaseHas('artists', ['name' => 'New Artist']);
    });
    
    test('cannot create artist without permission', function () {
        $user = actingAsUser();
        
        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/artists', ['name' => 'Test Artist']);
        
        $response->assertStatus(403);
    });
});
```

### 4.2. API Validation Tests

```php
describe('Artist API Validation', function () {
    test('requires name field', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('music-catalog.artists.create');
        
        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/artists', []);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    });
    
    test('name must be unique', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('music-catalog.artists.create');
        
        Artist::factory()->create(['name' => 'Existing Artist']);
        
        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/artists', ['name' => 'Existing Artist']);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    });
    
    test('website must be valid url', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('music-catalog.artists.create');
        
        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/artists', [
                             'name' => 'Test Artist',
                             'website' => 'invalid-url'
                         ]);
        
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['website']);
    });
});
```

### 4.3. API Resource Transformation

```php
describe('Artist API Resources', function () {
    test('transforms artist data correctly', function () {
        $artist = Artist::factory()->withAlbums(2)->create();
        $user = actingAsUser();
        $user->givePermissionTo('music-catalog.artists.view');
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson("/api/artists/{$artist->id}");
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'name', 'slug', 'bio', 'website',
                         'albums_count', 'created_at', 'updated_at',
                         'albums' => [
                             '*' => ['id', 'title', 'slug', 'release_date']
                         ]
                     ]
                 ]);
        
        expect($response->json('data.albums_count'))->toBe(2);
    });
});
```

## 5. Filament Resource Testing

### 5.1. Filament Resource CRUD Tests

```php
// tests/Feature/Filament/ArtistResourceTest.php
use App\Filament\Resources\ArtistResource;

describe('Artist Filament Resource', function () {
    test('can render index page', function () {
        $user = actingAsAdmin();
        Artist::factory()->count(10)->create();
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\ListArtists::class)
                ->assertSuccessful()
                ->assertCanSeeTableRecords(Artist::all());
    });
    
    test('can create artist', function () {
        $user = actingAsAdmin();
        $newData = Artist::factory()->make();
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\CreateArtist::class)
                ->fillForm([
                    'name' => $newData->name,
                    'bio' => $newData->bio,
                    'website' => $newData->website,
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        
        $this->assertDatabaseHas('artists', ['name' => $newData->name]);
    });
    
    test('can edit artist', function () {
        $user = actingAsAdmin();
        $artist = Artist::factory()->create();
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\EditArtist::class, ['record' => $artist->id])
                ->fillForm(['name' => 'Updated Name'])
                ->call('save')
                ->assertHasNoFormErrors();
        
        expect($artist->fresh()->name)->toBe('Updated Name');
    });
    
    test('can delete artist', function () {
        $user = actingAsAdmin();
        $artist = Artist::factory()->create();
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\ListArtists::class)
                ->callTableAction('delete', $artist);
        
        $this->assertSoftDeleted('artists', ['id' => $artist->id]);
    });
});
```

### 5.2. Filament Form Validation

```php
describe('Artist Filament Form Validation', function () {
    test('validates required fields', function () {
        $user = actingAsAdmin();
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\CreateArtist::class)
                ->fillForm(['name' => ''])
                ->call('create')
                ->assertHasFormErrors(['name' => 'required']);
    });
    
    test('validates unique name', function () {
        $user = actingAsAdmin();
        $existingArtist = Artist::factory()->create();
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\CreateArtist::class)
                ->fillForm(['name' => $existingArtist->name])
                ->call('create')
                ->assertHasFormErrors(['name' => 'unique']);
    });
});
```

### 5.3. Filament Table Features

```php
describe('Artist Filament Table', function () {
    test('can search artists', function () {
        $user = actingAsAdmin();
        $artist1 = Artist::factory()->create(['name' => 'The Beatles']);
        $artist2 = Artist::factory()->create(['name' => 'Rolling Stones']);
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\ListArtists::class)
                ->searchTable('Beatles')
                ->assertCanSeeTableRecords([$artist1])
                ->assertCanNotSeeTableRecords([$artist2]);
    });
    
    test('can filter artists by status', function () {
        $user = actingAsAdmin();
        $activeArtist = Artist::factory()->create(['is_active' => true]);
        $inactiveArtist = Artist::factory()->create(['is_active' => false]);
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\ListArtists::class)
                ->filterTable('is_active', true)
                ->assertCanSeeTableRecords([$activeArtist])
                ->assertCanNotSeeTableRecords([$inactiveArtist]);
    });
    
    test('can sort artists by name', function () {
        $user = actingAsAdmin();
        $artistA = Artist::factory()->create(['name' => 'A Artist']);
        $artistZ = Artist::factory()->create(['name' => 'Z Artist']);
        
        Livewire::actingAs($user)
                ->test(ArtistResource\Pages\ListArtists::class)
                ->sortTable('name')
                ->assertCanSeeTableRecords([$artistA, $artistZ], inOrder: true);
    });
});
```

## 6. Database Testing

### 6.1. Migration Tests

```php
describe('Artist Migration', function () {
    test('creates artists table with correct structure', function () {
        $columns = Schema::getColumnListing('artists');
        
        expect($columns)->toContain([
            'id', 'name', 'slug', 'public_id', 'bio', 'website',
            'is_active', 'metadata', 'created_by', 'updated_by',
            'created_at', 'updated_at', 'deleted_at'
        ]);
    });
    
    test('has correct indexes', function () {
        $indexes = collect(DB::select("PRAGMA index_list('artists')"))
                    ->pluck('name')->toArray();
        
        expect($indexes)->toContain([
            'artists_slug_index',
            'artists_public_id_index',
            'artists_name_index'
        ]);
    });
});
```

### 6.2. Query Performance Tests

```php
describe('Artist Query Performance', function () {
    test('eager loading prevents n+1 queries', function () {
        Artist::factory()->count(10)->create();
        Album::factory()->count(30)->create();
        
        DB::enableQueryLog();
        
        $artists = Artist::with('albums')->get();
        $artists->each(fn($artist) => $artist->albums->count());
        
        $queryCount = count(DB::getQueryLog());
        expect($queryCount)->toBeLessThanOrEqual(2); // 1 for artists, 1 for albums
    });
    
    test('scoped queries use indexes', function () {
        Artist::factory()->count(100)->create();
        
        $startTime = microtime(true);
        Artist::active()->get();
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        expect($executionTime)->toBeLessThan(50); // Should complete in under 50ms
    });
});
```

## 7. Performance Testing

### 7.1. Response Time Tests

```php
describe('API Performance', function () {
    test('artist list endpoint responds quickly', function () {
        $user = actingAsUser();
        $user->givePermissionTo('music-catalog.artists.view');
        Artist::factory()->count(100)->create();
        
        $startTime = microtime(true);
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/artists');
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        expect($responseTime)->toBeLessThan(500); // Under 500ms
    });
    
    test('pagination limits memory usage', function () {
        $user = actingAsUser();
        $user->givePermissionTo('music-catalog.artists.view');
        Artist::factory()->count(1000)->create();
        
        $memoryBefore = memory_get_usage();
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/artists?per_page=50');
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB
        
        $response->assertStatus(200);
        expect($memoryUsed)->toBeLessThan(10); // Under 10MB
    });
});
```

## 8. Security Testing

### 8.1. Authorization Tests

```php
describe('Artist Security', function () {
    test('unauthorized user cannot access artists', function () {
        Artist::factory()->count(5)->create();
        
        $response = $this->getJson('/api/artists');
        
        $response->assertStatus(401);
    });
    
    test('user without permission cannot create artist', function () {
        $user = actingAsUser();
        
        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/artists', ['name' => 'Test Artist']);
        
        $response->assertStatus(403);
    });
    
    test('user can only update own artists', function () {
        $user1 = actingAsUser();
        $user2 = actingAsUser();
        $user1->givePermissionTo('music-catalog.artists.update-own');
        
        $artist = Artist::factory()->create(['created_by' => $user2->id]);
        
        $response = $this->actingAs($user1, 'sanctum')
                         ->putJson("/api/artists/{$artist->id}", ['name' => 'Updated']);
        
        $response->assertStatus(403);
    });
});
```

### 8.2. Data Protection Tests

```php
describe('Artist Data Protection', function () {
    test('soft deleted artists are not visible in api', function () {
        $user = actingAsUser();
        $user->givePermissionTo('music-catalog.artists.view');
        
        $artist = Artist::factory()->create();
        $artist->delete();
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/artists');
        
        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(0);
    });
    
    test('sensitive data is not exposed in api', function () {
        $user = actingAsUser();
        $user->givePermissionTo('music-catalog.artists.view');
        
        $artist = Artist::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
                         ->getJson("/api/artists/{$artist->id}");
        
        $response->assertStatus(200)
                 ->assertJsonMissing(['created_by', 'updated_by']);
    });
});
```

---

**Next Steps:**

- [Performance Testing Guide](testing/090-performance-testing-guide.md) - Advanced performance testing
- [Security Testing Guide](filament/testing/160-security-testing.md) - Security testing patterns
- [CI/CD Integration](filament/deployment/130-cicd-pipeline.md) - Automated testing in CI/CD

**Related Documentation:**

- [Model Implementation](010-chinook-models-guide.md) - Model structure and relationships
- [API Documentation](packages/080-laravel-sanctum-guide.md) - API endpoint specifications
- [Filament Resources](filament/resources/000-resources-index.md) - Admin panel resource implementation
