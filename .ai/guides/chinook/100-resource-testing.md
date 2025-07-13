# 1. Chinook Resource Testing Guide

**Refactored from:** `.ai/guides/chinook/100-resource-testing.md` on 2025-07-11

## 1.1 Table of Contents

- [1.2 Overview](#12-overview)
- [1.3 Testing Framework Setup](#13-testing-framework-setup)
- [1.4 Model Testing](#14-model-testing)
- [1.5 API Resource Testing](#15-api-resource-testing)
- [1.6 Filament Resource Testing](#16-filament-resource-testing)

## 1.2 Overview

This comprehensive guide covers testing strategies for all Chinook resources, including models, API endpoints, Filament admin resources, and database operations using Pest PHP testing framework with Laravel 12 modern patterns and exclusive use of the aliziodev/laravel-taxonomy package.

### 1.2.1 Testing Philosophy

**Testing Principles:**

- **Test-Driven Development**: Write tests before implementation
- **Comprehensive Coverage**: Unit, feature, and integration tests
- **Performance Awareness**: Test query efficiency and response times
- **Security Focus**: Test authorization and data protection
- **Real-World Scenarios**: Test actual user workflows
- **Taxonomy Integration**: Test aliziodev/laravel-taxonomy relationships

### 1.2.2 Testing Stack

- **Framework**: Pest PHP with Laravel integration
- **Database**: SQLite in-memory for fast testing
- **Factories**: Model factories for test data generation
- **Mocking**: Mockery for external service testing
- **Coverage**: PHPUnit coverage reports
- **Taxonomy**: aliziodev/laravel-taxonomy package testing

## 1.3 Testing Framework Setup

### 1.3.1 Pest Configuration

```php
// tests/Pest.php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Term;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class,
    WithFaker::class
)->in('Feature', 'Unit');

// Global test helpers
function actingAsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');
    return $user;
}

function actingAsUser(): User
{
    return User::factory()->create();
}

function createChinookArtistWithAlbums(int $albumCount = 3): \App\Models\ChinookArtist
{
    $artist = \App\Models\ChinookArtist::factory()->create();
    \App\Models\ChinookAlbum::factory()->count($albumCount)->create(['artist_id' => $artist->id]);
    return $artist;
}

function createGenreTaxonomy(): Taxonomy
{
    return Taxonomy::factory()->create([
        'name' => 'Genres',
        'slug' => 'genres'
    ]);
}

function createGenreTerm(Taxonomy $taxonomy, string $name): Term
{
    return Term::factory()->create([
        'taxonomy_id' => $taxonomy->id,
        'name' => $name,
        'slug' => Str::slug($name)
    ]);
}
```

### 1.3.2 Database Configuration

```php
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_DRIVER" value="array"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="QUEUE_DRIVER" value="sync"/>
```

### 1.3.3 Test Factories

```php
// database/factories/ChinookArtistFactory.php
class ChinookArtistFactory extends Factory
{
    protected $model = ChinookArtist::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->name(),
            'public_id' => Str::ulid(),
            'slug' => fn(array $attributes) => Str::slug($attributes['name']),
            'biography' => $this->faker->paragraph(),
            'website' => $this->faker->url(),
            'is_active' => true,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
    
    public function withAlbums(int $count = 3): static
    {
        return $this->afterCreating(function (ChinookArtist $artist) use ($count) {
            ChinookAlbum::factory()->count($count)->create(['artist_id' => $artist->id]);
        });
    }

    public function withGenre(string $genreName): static
    {
        return $this->afterCreating(function (ChinookArtist $artist) use ($genreName) {
            $taxonomy = createGenreTaxonomy();
            $term = createGenreTerm($taxonomy, $genreName);
            $artist->attachTerm($term);
        });
    }
}

// database/factories/ChinookAlbumFactory.php
class ChinookAlbumFactory extends Factory
{
    protected $model = ChinookAlbum::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'artist_id' => ChinookArtist::factory(),
            'public_id' => Str::ulid(),
            'slug' => fn(array $attributes) => Str::slug($attributes['title']),
            'release_date' => $this->faker->date(),
            'is_active' => true,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
```

## 1.4 Model Testing

### 1.4.1 Model Relationships

```php
// tests/Unit/Models/ChinookArtistTest.php
describe('ChinookArtist Model', function () {
    test('has many albums', function () {
        $artist = ChinookArtist::factory()->create();
        $albums = ChinookAlbum::factory()->count(3)->create(['artist_id' => $artist->id]);
        
        expect($artist->albums)->toHaveCount(3);
        expect($artist->albums->first())->toBeInstanceOf(ChinookAlbum::class);
    });
    
    test('has many tracks through albums', function () {
        $artist = ChinookArtist::factory()->create();
        $album = ChinookAlbum::factory()->create(['artist_id' => $artist->id]);
        $tracks = ChinookTrack::factory()->count(5)->create(['album_id' => $album->id]);
        
        expect($artist->tracks)->toHaveCount(5);
        expect($artist->tracks->first())->toBeInstanceOf(ChinookTrack::class);
    });
    
    test('can have taxonomy terms', function () {
        $artist = ChinookArtist::factory()->create();
        $taxonomy = createGenreTaxonomy();
        $rockTerm = createGenreTerm($taxonomy, 'Rock');
        
        $artist->attachTerm($rockTerm);
        
        expect($artist->terms)->toHaveCount(1);
        expect($artist->terms->first()->name)->toBe('Rock');
    });

    test('can filter by taxonomy terms', function () {
        $rockArtist = ChinookArtist::factory()->withGenre('Rock')->create();
        $jazzArtist = ChinookArtist::factory()->withGenre('Jazz')->create();
        
        $rockArtists = ChinookArtist::whereHasTerm('Rock', 'genres')->get();
        
        expect($rockArtists)->toHaveCount(1);
        expect($rockArtists->first()->id)->toBe($rockArtist->id);
    });
});
```

### 1.4.2 Model Attributes and Casting

```php
describe('ChinookArtist Attributes', function () {
    test('casts attributes correctly', function () {
        $artist = ChinookArtist::factory()->create([
            'is_active' => true,
            'metadata' => ['country' => 'USA', 'formed_year' => 1960],
        ]);
        
        expect($artist->is_active)->toBeTrue();
        expect($artist->metadata)->toBeArray();
        expect($artist->metadata['country'])->toBe('USA');
    });
    
    test('generates slug from name', function () {
        $artist = ChinookArtist::factory()->create(['name' => 'The Beatles']);
        
        expect($artist->slug)->toBe('the-beatles');
    });
    
    test('generates public_id automatically', function () {
        $artist = ChinookArtist::factory()->create();
        
        expect($artist->public_id)->not->toBeNull();
        expect(strlen($artist->public_id))->toBe(26); // ULID length
    });

    test('uses Laravel 12 casts method', function () {
        $artist = new ChinookArtist();
        $casts = $artist->getCasts();
        
        expect($casts)->toHaveKey('is_active');
        expect($casts)->toHaveKey('created_at');
        expect($casts)->toHaveKey('updated_at');
        expect($casts)->toHaveKey('deleted_at');
    });
});
```

### 1.4.3 Model Scopes

```php
describe('ChinookArtist Scopes', function () {
    test('active scope returns only active artists', function () {
        ChinookArtist::factory()->create(['is_active' => true]);
        ChinookArtist::factory()->create(['is_active' => false]);
        
        $activeArtists = ChinookArtist::active()->get();
        
        expect($activeArtists)->toHaveCount(1);
        expect($activeArtists->first()->is_active)->toBeTrue();
    });
    
    test('with albums scope includes album count', function () {
        $artistWithAlbums = ChinookArtist::factory()->withAlbums(3)->create();
        $artistWithoutAlbums = ChinookArtist::factory()->create();
        
        $artists = ChinookArtist::withAlbums()->get();
        
        expect($artists)->toHaveCount(1);
        expect($artists->first()->id)->toBe($artistWithAlbums->id);
    });

    test('by genre scope filters by taxonomy terms', function () {
        $rockArtist = ChinookArtist::factory()->withGenre('Rock')->create();
        $jazzArtist = ChinookArtist::factory()->withGenre('Jazz')->create();
        
        $rockArtists = ChinookArtist::byGenre('Rock')->get();
        
        expect($rockArtists)->toHaveCount(1);
        expect($rockArtists->first()->id)->toBe($rockArtist->id);
    });
});
```

## 1.5 API Resource Testing

### 1.5.1 API Endpoint Tests

```php
// tests/Feature/Api/ChinookArtistApiTest.php
describe('ChinookArtist API', function () {
    test('can list artists', function () {
        $user = actingAsUser();
        $user->givePermissionTo('chinook.artists.view');

        ChinookArtist::factory()->count(5)->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/chinook/artists');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'name', 'slug', 'biography', 'albums_count', 'terms']
                     ],
                     'meta' => ['current_page', 'total', 'per_page']
                 ]);

        expect($response->json('data'))->toHaveCount(5);
    });

    test('can create artist with taxonomy terms', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('chinook.artists.create');

        $taxonomy = createGenreTaxonomy();
        $rockTerm = createGenreTerm($taxonomy, 'Rock');

        $artistData = [
            'name' => 'New Artist',
            'biography' => 'Artist biography',
            'website' => 'https://example.com',
            'terms' => [$rockTerm->id]
        ];

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/chinook/artists', $artistData);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'New Artist']);

        $this->assertDatabaseHas('chinook_artists', ['name' => 'New Artist']);

        $artist = ChinookArtist::where('name', 'New Artist')->first();
        expect($artist->terms)->toHaveCount(1);
        expect($artist->terms->first()->name)->toBe('Rock');
    });

    test('cannot create artist without permission', function () {
        $user = actingAsUser();

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/chinook/artists', ['name' => 'Test Artist']);

        $response->assertStatus(403);
    });
});
```

### 1.5.2 API Validation Tests

```php
describe('ChinookArtist API Validation', function () {
    test('requires name field', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('chinook.artists.create');

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/chinook/artists', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    });

    test('name must be unique', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('chinook.artists.create');

        ChinookArtist::factory()->create(['name' => 'Existing Artist']);

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/chinook/artists', ['name' => 'Existing Artist']);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    });

    test('website must be valid url', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('chinook.artists.create');

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/chinook/artists', [
                             'name' => 'Test Artist',
                             'website' => 'invalid-url'
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['website']);
    });

    test('terms must exist in taxonomy', function () {
        $user = actingAsAdmin();
        $user->givePermissionTo('chinook.artists.create');

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/chinook/artists', [
                             'name' => 'Test Artist',
                             'terms' => [999] // Non-existent term ID
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['terms.0']);
    });
});
```

### 1.5.3 API Resource Transformation

```php
describe('ChinookArtist API Resources', function () {
    test('transforms artist data correctly with taxonomy', function () {
        $artist = ChinookArtist::factory()->withAlbums(2)->withGenre('Rock')->create();
        $user = actingAsUser();
        $user->givePermissionTo('chinook.artists.view');

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson("/api/chinook/artists/{$artist->id}");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'id', 'name', 'slug', 'biography', 'website',
                         'albums_count', 'created_at', 'updated_at',
                         'terms' => [
                             '*' => ['id', 'name', 'slug', 'taxonomy']
                         ],
                         'albums' => [
                             '*' => ['id', 'title', 'slug', 'release_date']
                         ]
                     ]
                 ]);

        expect($response->json('data.albums_count'))->toBe(2);
        expect($response->json('data.terms'))->toHaveCount(1);
        expect($response->json('data.terms.0.name'))->toBe('Rock');
    });

    test('filters artists by taxonomy terms', function () {
        $rockArtist = ChinookArtist::factory()->withGenre('Rock')->create();
        $jazzArtist = ChinookArtist::factory()->withGenre('Jazz')->create();
        $user = actingAsUser();
        $user->givePermissionTo('chinook.artists.view');

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/chinook/artists?filter[genre]=Rock');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.id'))->toBe($rockArtist->id);
    });
});
```

## 1.6 Filament Resource Testing

### 1.6.1 Filament Resource CRUD Tests

```php
// tests/Feature/Filament/ChinookArtistResourceTest.php
use App\Filament\Resources\ChinookArtistResource;

describe('ChinookArtist Filament Resource', function () {
    test('can render index page', function () {
        $user = actingAsAdmin();
        ChinookArtist::factory()->count(10)->create();

        Livewire::actingAs($user)
                ->test(ChinookArtistResource\Pages\ListChinookArtists::class)
                ->assertSuccessful()
                ->assertCanSeeTableRecords(ChinookArtist::all());
    });

    test('can create artist with taxonomy terms', function () {
        $user = actingAsAdmin();
        $newData = ChinookArtist::factory()->make();
        $taxonomy = createGenreTaxonomy();
        $rockTerm = createGenreTerm($taxonomy, 'Rock');

        Livewire::actingAs($user)
                ->test(ChinookArtistResource\Pages\CreateChinookArtist::class)
                ->fillForm([
                    'name' => $newData->name,
                    'biography' => $newData->biography,
                    'website' => $newData->website,
                    'terms' => [$rockTerm->id]
                ])
                ->call('create')
                ->assertHasNoFormErrors();

        $this->assertDatabaseHas('chinook_artists', ['name' => $newData->name]);

        $artist = ChinookArtist::where('name', $newData->name)->first();
        expect($artist->terms)->toHaveCount(1);
    });

    test('can edit artist taxonomy terms', function () {
        $user = actingAsAdmin();
        $artist = ChinookArtist::factory()->withGenre('Rock')->create();
        $taxonomy = createGenreTaxonomy();
        $jazzTerm = createGenreTerm($taxonomy, 'Jazz');

        Livewire::actingAs($user)
                ->test(ChinookArtistResource\Pages\EditChinookArtist::class, ['record' => $artist->id])
                ->fillForm([
                    'name' => 'Updated Name',
                    'terms' => [$jazzTerm->id]
                ])
                ->call('save')
                ->assertHasNoFormErrors();

        $artist->refresh();
        expect($artist->name)->toBe('Updated Name');
        expect($artist->terms)->toHaveCount(1);
        expect($artist->terms->first()->name)->toBe('Jazz');
    });

    test('can delete artist', function () {
        $user = actingAsAdmin();
        $artist = ChinookArtist::factory()->create();

        Livewire::actingAs($user)
                ->test(ChinookArtistResource\Pages\ListChinookArtists::class)
                ->callTableAction('delete', $artist);

        $this->assertSoftDeleted('chinook_artists', ['id' => $artist->id]);
    });
});
```

---

**Next**: [Authentication Flow Guide](110-authentication-flow.md) | **Previous**: [Relationship Mapping Guide](090-relationship-mapping.md)

---

*This guide demonstrates comprehensive testing strategies for Chinook resources using Laravel 12 patterns and the aliziodev/laravel-taxonomy package.*

[⬆️ Back to Top](#1-chinook-resource-testing-guide)
