# Unit Testing Guide

## Table of Contents

- [Overview](#overview)
- [Model Testing Strategies](#model-testing-strategies)
- [Trait Testing Patterns](#trait-testing-patterns)
- [Service Class Testing](#service-class-testing)
- [Enum Testing](#enum-testing)
- [Test Organization](#test-organization)
- [Best Practices](#best-practices)

## Overview

Unit testing in the Chinook application focuses on testing individual components in isolation. This guide covers comprehensive testing strategies for models, traits, services, and enums using Pest PHP framework with Laravel 12 modern patterns.

### Unit Testing Principles

- **Isolation**: Each test runs independently without external dependencies
- **Speed**: Fast execution with minimal setup and teardown
- **Clarity**: Clear, descriptive test names and expectations
- **Coverage**: Comprehensive testing of all code paths and edge cases

## Model Testing Strategies

### Artist Model Testing

```php
<?php

// tests/Unit/Models/ChinookArtistTest.php
use App\Models\ChinookArtist;
use App\Models\Album;
use App\Enums\CategoryType;
use App\Enums\SecondaryKeyType;

describe('Artist Model', function () {
    it('can be created with valid attributes', function () {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'biography' => 'Test biography',
            'country' => 'USA',
            'formed_year' => 2020,
            'is_active' => true,
        ]);

        expect($artist)
            ->name->toBe('Test Artist')
            ->biography->toBe('Test biography')
            ->country->toBe('USA')
            ->formed_year->toBe(2020)
            ->is_active->toBeTrue()
            ->toHaveValidTimestamps();
    });

    it('uses ULID as secondary key type', function () {
        $artist = new Artist();
        
        expect($artist->getSecondaryKeyType())
            ->toBe(SecondaryKeyType::ULID);
    });

    it('generates slug from public_id', function () {
        $artist = Artist::factory()->create();
        
        expect($artist->slug)
            ->not->toBeEmpty()
            ->toBeString();
    });

    it('uses slug as route key', function () {
        $artist = new Artist();
        
        expect($artist->getRouteKeyName())
            ->toBe('slug');
    });
});

describe('Artist Relationships', function () {
    it('has many albums', function () {
        $artist = Artist::factory()->create();
        $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);

        expect($artist->albums)
            ->toHaveCount(3)
            ->each->toBeInstanceOf(Album::class);
    });

    it('can have categories', function () {
        $artist = Artist::factory()->create();
        $category = Category::factory()->create(['type' => CategoryType::GENRE]);
        
        $artist->categories()->attach($category->id);

        expect($artist->categories)
            ->toHaveCount(1)
            ->first()->type->toBe(CategoryType::GENRE);
    });
});

describe('Artist Scopes', function () {
    beforeEach(function () {
        $this->activeArtist = Artist::factory()->create(['is_active' => true]);
        $this->inactiveArtist = Artist::factory()->create(['is_active' => false]);
    });

    it('can filter active artists', function () {
        $activeArtists = Artist::active()->get();

        expect($activeArtists)
            ->toHaveCount(1)
            ->first()->id->toBe($this->activeArtist->id);
    });

    it('can filter by country', function () {
        $usArtist = Artist::factory()->create(['country' => 'USA']);
        $ukArtist = Artist::factory()->create(['country' => 'UK']);

        $usArtists = Artist::byCountry('USA')->get();

        expect($usArtists)
            ->toHaveCount(1)
            ->first()->country->toBe('USA');
    });
});

describe('Artist Accessors', function () {
    it('formats social links as array', function () {
        $artist = Artist::factory()->create([
            'social_links' => ['twitter' => '@artist', 'instagram' => '@artist']
        ]);

        expect($artist->social_links)
            ->toBeArray()
            ->toHaveKey('twitter')
            ->toHaveKey('instagram');
    });

    it('calculates total albums count', function () {
        $artist = Artist::factory()->create();
        Album::factory()->count(5)->create(['artist_id' => $artist->id]);

        expect($artist->total_albums)->toBe(5);
    });
});
```

### Album Model Testing

```php
<?php

// tests/Unit/Models/ChinookAlbumTest.php
use App\Models\ChinookAlbum;
use App\Models\Artist;
use App\Models\Track;

describe('Album Model', function () {
    it('belongs to an artist', function () {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        expect($album->artist)
            ->toBeInstanceOf(Artist::class)
            ->id->toBe($artist->id);
    });

    it('has many tracks', function () {
        $album = Album::factory()->create();
        $tracks = Track::factory()->count(10)->create(['album_id' => $album->id]);

        expect($album->tracks)
            ->toHaveCount(10)
            ->each->toBeInstanceOf(Track::class);
    });

    it('calculates total duration from tracks', function () {
        $album = Album::factory()->create();
        Track::factory()->count(3)->create([
            'album_id' => $album->id,
            'milliseconds' => 180000 // 3 minutes each
        ]);

        expect($album->total_duration)->toBe(540000); // 9 minutes total
    });

    it('formats full title with artist name', function () {
        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $album = Album::factory()->create([
            'artist_id' => $artist->id,
            'title' => 'Test Album'
        ]);

        expect($album->full_title)->toBe('Test Artist - Test Album');
    });
});

describe('Album Validation', function () {
    it('requires title', function () {
        expect(fn() => Album::create(['artist_id' => 1]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('requires valid artist_id', function () {
        expect(fn() => Album::create([
            'title' => 'Test Album',
            'artist_id' => 999999
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
```

### Track Model Testing

```php
<?php

// tests/Unit/Models/ChinookTrackTest.php
use App\Models\ChinookTrack;
use App\Models\Album;
use App\Models\MediaType;

describe('Track Model', function () {
    it('uses Snowflake as secondary key type', function () {
        $track = new Track();
        
        expect($track->getSecondaryKeyType())
            ->toBe(SecondaryKeyType::SNOWFLAKE);
    });

    it('belongs to album and media type', function () {
        $album = Album::factory()->create();
        $mediaType = MediaType::factory()->create();
        $track = Track::factory()->create([
            'album_id' => $album->id,
            'media_type_id' => $mediaType->id
        ]);

        expect($track->album)->toBeInstanceOf(Album::class);
        expect($track->mediaType)->toBeInstanceOf(MediaType::class);
    });

    it('formats duration correctly', function () {
        $track = Track::factory()->create(['milliseconds' => 180000]); // 3 minutes

        expect($track->formatted_duration)->toBe('3:00');
    });

    it('calculates if track is long', function () {
        $shortTrack = Track::factory()->create(['milliseconds' => 120000]); // 2 minutes
        $longTrack = Track::factory()->create(['milliseconds' => 420000]); // 7 minutes

        expect($shortTrack->is_long)->toBeFalse();
        expect($longTrack->is_long)->toBeTrue();
    });
});

describe('Track Scopes', function () {
    it('can filter by duration range', function () {
        Track::factory()->create(['milliseconds' => 120000]); // 2 minutes
        Track::factory()->create(['milliseconds' => 180000]); // 3 minutes
        Track::factory()->create(['milliseconds' => 300000]); // 5 minutes

        $mediumTracks = Track::durationBetween(150000, 250000)->get();

        expect($mediumTracks)->toHaveCount(1);
    });

    it('can filter popular tracks', function () {
        $track1 = Track::factory()->create();
        $track2 = Track::factory()->create();
        
        // Simulate purchases
        InvoiceLine::factory()->count(10)->create(['track_id' => $track1->id]);
        InvoiceLine::factory()->count(5)->create(['track_id' => $track2->id]);

        $popularTracks = Track::popular(1)->get();

        expect($popularTracks)
            ->toHaveCount(1)
            ->first()->id->toBe($track1->id);
    });
});
```

## Trait Testing Patterns

### HasSecondaryUniqueKey Trait Testing

```php
<?php

// tests/Unit/Traits/HasSecondaryUniqueKeyTest.php
use App\Models\Artist;
use App\Models\Track;
use App\Models\Category;
use App\Enums\SecondaryKeyType;

describe('HasSecondaryUniqueKey Trait', function () {
    it('generates ULID for Artist models', function () {
        $artist = Artist::factory()->create();

        expect($artist->public_id)
            ->not->toBeEmpty()
            ->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/'); // ULID format
    });

    it('generates Snowflake for Track models', function () {
        $track = Track::factory()->create();

        expect($track->public_id)
            ->not->toBeEmpty()
            ->toBeNumeric();
    });

    it('generates UUID for Category models', function () {
        $category = Category::factory()->create();

        expect($category->public_id)
            ->not->toBeEmpty()
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/'); // UUID format
    });

    it('prevents duplicate public_id generation', function () {
        $artist1 = Artist::factory()->create();
        $artist2 = Artist::factory()->create();

        expect($artist1->public_id)->not->toBe($artist2->public_id);
    });

    it('finds models by public_id', function () {
        $artist = Artist::factory()->create();

        $foundArtist = Artist::findByPublicId($artist->public_id);

        expect($foundArtist->id)->toBe($artist->id);
    });
});
```

### HasSlug Trait Testing

```php
<?php

// tests/Unit/Traits/HasSlugTest.php
use App\Models\Artist;
use App\Models\Album;

describe('HasSlug Trait', function () {
    it('generates slug from public_id', function () {
        $artist = Artist::factory()->create();

        expect($artist->slug)
            ->not->toBeEmpty()
            ->toBeString()
            ->not->toContain(' '); // No spaces in slug
    });

    it('prevents slug updates after creation', function () {
        $artist = Artist::factory()->create();
        $originalSlug = $artist->slug;

        $artist->update(['name' => 'Updated Name']);

        expect($artist->fresh()->slug)->toBe($originalSlug);
    });

    it('handles slug conflicts with suffix', function () {
        $artist1 = Artist::factory()->create();

        // Force same public_id (for testing purposes)
        $artist2 = new Artist();
        $artist2->forceFill(['public_id' => $artist1->public_id]);
        $artist2->save();

        expect($artist2->slug)->toContain('-2');
    });

    it('uses slug for route model binding', function () {
        $artist = Artist::factory()->create();

        expect($artist->getRouteKeyName())->toBe('slug');
        expect($artist->getRouteKey())->toBe($artist->slug);
    });
});
```

### Categorizable Trait Testing

```php
<?php

// tests/Unit/Traits/CategorizableTest.php
use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Category;
use App\Enums\CategoryType;

describe('Categorizable Trait', function () {
    beforeEach(function () {
        $this->artist = Artist::factory()->create();
        $this->genreCategory = Category::factory()->create(['type' => CategoryType::GENRE]);
        $this->moodCategory = Category::factory()->create(['type' => CategoryType::MOOD]);
    });

    it('can attach categories to models', function () {
        $this->artist->categories()->attach($this->genreCategory->id);

        expect($this->artist->categories)
            ->toHaveCount(1)
            ->first()->id->toBe($this->genreCategory->id);
    });

    it('can filter categories by type', function () {
        $this->artist->categories()->attach([
            $this->genreCategory->id,
            $this->moodCategory->id
        ]);

        $genreCategories = $this->artist->categoriesByType(CategoryType::GENRE)->get();

        expect($genreCategories)
            ->toHaveCount(1)
            ->first()->type->toBe(CategoryType::GENRE);
    });

    it('can set primary category', function () {
        $this->artist->categories()->attach([
            $this->genreCategory->id => ['is_primary' => false],
            $this->moodCategory->id => ['is_primary' => false]
        ]);

        $this->artist->setPrimaryCategory($this->genreCategory);

        $primaryCategory = $this->artist->getPrimaryCategory(CategoryType::GENRE);
        expect($primaryCategory->id)->toBe($this->genreCategory->id);
    });

    it('provides category helper methods', function () {
        $this->artist->categories()->attach($this->genreCategory->id);

        expect($this->artist->hasCategoryType(CategoryType::GENRE))->toBeTrue();
        expect($this->artist->hasCategoryType(CategoryType::MOOD))->toBeFalse();

        $categoryNames = $this->artist->getCategoryNames();
        expect($categoryNames)->toContain($this->genreCategory->name);
    });

    it('can sync categories by type', function () {
        $newGenreCategory = Category::factory()->create(['type' => CategoryType::GENRE]);

        $this->artist->categories()->attach([
            $this->genreCategory->id,
            $this->moodCategory->id
        ]);

        $this->artist->syncCategoriesByType(CategoryType::GENRE, [$newGenreCategory->id]);

        $categories = $this->artist->fresh()->categories;
        expect($categories)
            ->toHaveCount(2) // One genre + one mood
            ->where('type', CategoryType::GENRE)->first()->id->toBe($newGenreCategory->id);
    });
});
```

## Service Class Testing

### Music Recommendation Service Testing

```php
<?php

// tests/Unit/Services/MusicRecommendationServiceTest.php
use App\Services\MusicRecommendationService;
use App\Models\User;
use App\Models\Track;
use App\Models\Category;
use App\Enums\CategoryType;

describe('Music Recommendation Service', function () {
    beforeEach(function () {
        $this->service = app(MusicRecommendationService::class);
        $this->user = User::factory()->create();

        // Create test categories and tracks
        $this->rockGenre = Category::factory()->create([
            'type' => CategoryType::GENRE,
            'name' => 'Rock'
        ]);

        $this->jazzGenre = Category::factory()->create([
            'type' => CategoryType::GENRE,
            'name' => 'Jazz'
        ]);

        $this->rockTracks = Track::factory()->count(10)->create();
        $this->jazzTracks = Track::factory()->count(5)->create();

        // Attach categories to tracks
        $this->rockTracks->each(fn($track) =>
            $track->categories()->attach($this->rockGenre->id)
        );

        $this->jazzTracks->each(fn($track) =>
            $track->categories()->attach($this->jazzGenre->id)
        );
    });

    it('recommends tracks based on user preferences', function () {
        // Simulate user listening to rock tracks
        $this->user->listenedTracks()->attach($this->rockTracks->take(3)->pluck('id'));

        $recommendations = $this->service->getRecommendations($this->user, 5);

        expect($recommendations)
            ->toHaveCount(5)
            ->each->toBeInstanceOf(Track::class);

        // Should recommend more rock tracks
        $rockRecommendations = $recommendations->filter(function ($track) {
            return $track->categories->contains('id', $this->rockGenre->id);
        });

        expect($rockRecommendations->count())->toBeGreaterThan(2);
    });

    it('excludes already listened tracks', function () {
        $listenedTrack = $this->rockTracks->first();
        $this->user->listenedTracks()->attach($listenedTrack->id);

        $recommendations = $this->service->getRecommendations($this->user, 10);

        expect($recommendations->pluck('id'))->not->toContain($listenedTrack->id);
    });

    it('handles users with no listening history', function () {
        $recommendations = $this->service->getRecommendations($this->user, 5);

        expect($recommendations)
            ->toHaveCount(5)
            ->each->toBeInstanceOf(Track::class);
    });

    it('caches recommendations for performance', function () {
        // First call
        $this->service->getRecommendations($this->user, 5);

        // Second call should be faster due to caching
        $startTime = microtime(true);
        $this->service->getRecommendations($this->user, 5);
        $executionTime = (microtime(true) - $startTime) * 1000;

        expect($executionTime)->toBeLessThan(10); // Should be very fast
    });
});
```

### Category Management Service Testing

```php
<?php

// tests/Unit/Services/CategoryManagementServiceTest.php
use App\Services\CategoryManagementService;
use App\Models\Category;
use App\Enums\CategoryType;

describe('Category Management Service', function () {
    beforeEach(function () {
        $this->service = app(CategoryManagementService::class);
    });

    it('creates hierarchical category structure', function () {
        $parentCategory = $this->service->createCategory([
            'name' => 'Rock',
            'type' => CategoryType::GENRE,
        ]);

        $childCategory = $this->service->createCategory([
            'name' => 'Hard Rock',
            'type' => CategoryType::GENRE,
            'parent_id' => $parentCategory->id,
        ]);

        expect($childCategory->parent_id)->toBe($parentCategory->id);
        expect($parentCategory->children)->toHaveCount(1);
    });

    it('validates category type consistency in hierarchy', function () {
        $genreCategory = Category::factory()->create(['type' => CategoryType::GENRE]);

        expect(fn() => $this->service->createCategory([
            'name' => 'Happy',
            'type' => CategoryType::MOOD,
            'parent_id' => $genreCategory->id,
        ]))->toThrow(\InvalidArgumentException::class);
    });

    it('moves categories between parents', function () {
        $parent1 = Category::factory()->create(['type' => CategoryType::GENRE]);
        $parent2 = Category::factory()->create(['type' => CategoryType::GENRE]);
        $child = Category::factory()->create([
            'type' => CategoryType::GENRE,
            'parent_id' => $parent1->id
        ]);

        $this->service->moveCategory($child, $parent2);

        expect($child->fresh()->parent_id)->toBe($parent2->id);
    });
});
```

## Enum Testing

### CategoryType Enum Testing

```php
<?php

// tests/Unit/Enums/CategoryTypeTest.php
use App\Enums\CategoryType;

describe('CategoryType Enum', function () {
    it('has all expected values', function () {
        $expectedValues = [
            'genre', 'mood', 'theme', 'era',
            'instrument', 'language', 'occasion'
        ];

        $actualValues = array_map(fn($case) => $case->value, CategoryType::cases());

        expect($actualValues)->toBe($expectedValues);
    });

    it('provides correct labels', function () {
        expect(CategoryType::GENRE->label())->toBe('Music Genre');
        expect(CategoryType::MOOD->label())->toBe('Mood & Emotion');
        expect(CategoryType::THEME->label())->toBe('Theme & Style');
        expect(CategoryType::ERA->label())->toBe('Time Period');
        expect(CategoryType::INSTRUMENT->label())->toBe('Instrument Focus');
        expect(CategoryType::LANGUAGE->label())->toBe('Language');
        expect(CategoryType::OCCASION->label())->toBe('Occasion & Event');
    });

    it('provides correct descriptions', function () {
        expect(CategoryType::GENRE->description())
            ->toContain('musical style')
            ->toContain('classification');

        expect(CategoryType::MOOD->description())
            ->toContain('emotional')
            ->toContain('feeling');
    });

    it('provides default categories for seeding', function () {
        $genreDefaults = CategoryType::GENRE->defaultCategories();

        expect($genreDefaults)
            ->toBeArray()
            ->toHaveKey('Rock')
            ->toHaveKey('Jazz');

        expect($genreDefaults['Rock'])->toContain('Hard Rock');
    });

    it('filters category types for specific models', function () {
        $artistTypes = CategoryType::forModel('App\Models\Artist');

        expect($artistTypes)->toContain(CategoryType::GENRE);
        expect($artistTypes)->toContain(CategoryType::ERA);
        expect($artistTypes)->not->toContain(CategoryType::OCCASION);
    });

    it('converts to array correctly', function () {
        $array = CategoryType::toArray();

        expect($array)
            ->toBeArray()
            ->toHaveCount(7)
            ->toContain('genre')
            ->toContain('mood');
    });
});
```

### SecondaryKeyType Enum Testing

```php
<?php

// tests/Unit/Enums/SecondaryKeyTypeTest.php
use App\Enums\SecondaryKeyType;

describe('SecondaryKeyType Enum', function () {
    it('has all expected values', function () {
        $expectedValues = ['ulid', 'uuid', 'snowflake'];
        $actualValues = array_map(fn($case) => $case->value, SecondaryKeyType::cases());

        expect($actualValues)->toBe($expectedValues);
    });

    it('generates valid ULID', function () {
        $ulid = SecondaryKeyType::ULID->generate();

        expect($ulid)
            ->toBeString()
            ->toHaveLength(26)
            ->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });

    it('generates valid UUID', function () {
        $uuid = SecondaryKeyType::UUID->generate();

        expect($uuid)
            ->toBeString()
            ->toHaveLength(36)
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('generates valid Snowflake', function () {
        $snowflake = SecondaryKeyType::SNOWFLAKE->generate();

        expect($snowflake)
            ->toBeNumeric()
            ->toBeGreaterThan(0);
    });

    it('provides correct descriptions', function () {
        expect(SecondaryKeyType::ULID->description())
            ->toContain('chronologically sortable')
            ->toContain('URL-friendly');

        expect(SecondaryKeyType::UUID->description())
            ->toContain('standards compliance')
            ->toContain('globally unique');

        expect(SecondaryKeyType::SNOWFLAKE->description())
            ->toContain('high performance')
            ->toContain('distributed systems');
    });
});
```

## Test Organization

### Directory Structure

```text
tests/Unit/
├── Models/
│   ├── ChinookArtistTest.php
│   ├── ChinookAlbumTest.php
│   ├── ChinookTrackTest.php
│   ├── ChinookCategoryTest.php
│   ├── ChinookCustomerTest.php
│   ├── ChinookEmployeeTest.php
│   ├── ChinookInvoiceTest.php
│   └── ChinookInvoiceLineTest.php
├── Traits/
│   ├── HasSecondaryUniqueKeyTest.php
│   ├── HasSlugTest.php
│   ├── CategorizableTest.php
│   ├── HasTagsTest.php
│   └── UserstampsTest.php
├── Services/
│   ├── MusicRecommendationServiceTest.php
│   ├── CategoryManagementServiceTest.php
│   └── PlaylistServiceTest.php
└── Enums/
    ├── CategoryTypeTest.php
    └── SecondaryKeyTypeTest.php
```

### Test Naming Conventions

- **Test Files**: `{ClassName}Test.php`
- **Test Methods**: Descriptive names using `it('should do something', function() {})`
- **Test Groups**: Use `describe()` blocks to group related tests
- **Setup Methods**: Use `beforeEach()` for common test setup

## Comprehensive Coverage Strategy

### Coverage Targets

- **Model Coverage**: 100% coverage of all model attributes, relationships, and methods
- **Trait Coverage**: 100% coverage of all trait functionality and edge cases
- **Service Coverage**: 95%+ coverage of all service class business logic
- **Enum Coverage**: Complete coverage of all enum values and methods

### Coverage Measurement

```bash
# Run tests with coverage reporting
php artisan test --coverage

# Generate HTML coverage report
php artisan test --coverage-html reports/coverage

# Enforce minimum coverage threshold
php artisan test --min=95
```

## Best Practices

### Test Writing Guidelines

1. **One Assertion Per Test**: Each test should verify one specific behavior
2. **Descriptive Names**: Test names should clearly describe what is being tested
3. **Arrange-Act-Assert**: Structure tests with clear setup, execution, and verification
4. **Test Edge Cases**: Include tests for boundary conditions and error scenarios
5. **Complete Coverage**: Ensure all code paths and edge cases are tested

### Performance Optimization

1. **Use Database Transactions**: For faster test execution and cleanup
2. **Factory Optimization**: Create minimal data needed for each test
3. **Mock External Dependencies**: Avoid real API calls or file system operations
4. **Parallel Testing**: Run tests in parallel when possible

### Maintenance Strategies

1. **Regular Refactoring**: Keep test code clean and maintainable
2. **Shared Helpers**: Extract common test logic into helper methods
3. **Documentation**: Document complex test scenarios and edge cases
4. **Coverage Monitoring**: Maintain high test coverage and monitor trends

---

**Navigation:**

- **Previous:** [Test Architecture Overview](010-test-architecture-overview.md)
- **Next:** [Feature Testing Guide](030-feature-testing-guide.md)
- **Up:** [Testing Documentation](000-testing-index.md)
