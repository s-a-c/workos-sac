# Test Data Management

## Table of Contents

- [Overview](#overview)
- [Factory Definitions](#factory-definitions)
- [Seeder Strategies](#seeder-strategies)
- [Test Database State Management](#test-database-state-management)
- [Data Cleanup Strategies](#data-cleanup-strategies)
- [Hierarchical Data Factories](#hierarchical-data-factories)
- [Polymorphic Data Factories](#polymorphic-data-factories)
- [Best Practices](#best-practices)

## Overview

Effective test data management is crucial for reliable and maintainable tests. This guide covers comprehensive strategies for creating, managing, and cleaning up test data in the Chinook application using Laravel 12 modern patterns and Pest PHP framework.

### Test Data Management Principles

- **Isolation**: Each test should have clean, isolated data
- **Consistency**: Predictable and reliable test data generation
- **Performance**: Efficient data creation and cleanup
- **Realism**: Test data that reflects real-world scenarios

## Factory Definitions

### ChinookArtist Factory with Laravel 12 Modern Syntax

```php
<?php

// database/factories/ChinookArtistFactory.php
namespace Database\Factories;

use App\Models\ChinookArtist;
use App\Enums\SecondaryKeyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChinookArtist>
 */
class ChinookArtistFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ChinookArtist::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company() . ' Band',
            'biography' => $this->faker->paragraphs(3, true),
            'website' => $this->faker->url(),
            'social_links' => [
                'twitter' => '@' . $this->faker->userName(),
                'instagram' => '@' . $this->faker->userName(),
                'facebook' => $this->faker->url(),
            ],
            'country' => $this->faker->country(),
            'formed_year' => $this->faker->numberBetween(1950, 2023),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Indicate that the artist is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the artist is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create an artist from a specific country.
     */
    public function fromCountry(string $country): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => $country,
        ]);
    }

    /**
     * Create a legendary artist (formed before 1980).
     */
    public function legendary(): static
    {
        return $this->state(fn (array $attributes) => [
            'formed_year' => $this->faker->numberBetween(1950, 1979),
            'biography' => 'A legendary artist that shaped the music industry. ' . $this->faker->paragraphs(2, true),
        ]);
    }

    /**
     * Create an artist with albums.
     */
    public function withAlbums(int $count = 3): static
    {
        return $this->afterCreating(function (Artist $artist) use ($count) {
            Album::factory()->count($count)->create([
                'artist_id' => $artist->id,
            ]);
        });
    }

    /**
     * Create an artist with categories.
     */
    public function withCategories(array $categoryTypes = []): static
    {
        return $this->afterCreating(function (Artist $artist) use ($categoryTypes) {
            if (empty($categoryTypes)) {
                $categoryTypes = [CategoryType::GENRE, CategoryType::ERA];
            }

            foreach ($categoryTypes as $type) {
                $category = Category::factory()->create(['type' => $type]);
                $artist->categories()->attach($category->id);
            }
        });
    }
}
```

### Album Factory with Relationships

```php
<?php

// database/factories/AlbumFactory.php
namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlbumFactory extends Factory
{
    protected $model = Album::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->words(rand(1, 4), true),
            'artist_id' => Artist::factory(),
            'release_date' => $this->faker->dateTimeBetween('-50 years', 'now'),
            'label' => $this->faker->company() . ' Records',
            'catalog_number' => strtoupper($this->faker->bothify('??-####')),
            'description' => $this->faker->paragraphs(2, true),
            'cover_image_url' => $this->faker->imageUrl(400, 400, 'music'),
            'total_tracks' => $this->faker->numberBetween(8, 20),
            'total_duration_ms' => $this->faker->numberBetween(1800000, 4800000), // 30-80 minutes
            'is_compilation' => $this->faker->boolean(10), // 10% chance
            'is_explicit' => $this->faker->boolean(20), // 20% chance
            'is_active' => $this->faker->boolean(90), // 90% chance
        ];
    }

    /**
     * Create a compilation album.
     */
    public function compilation(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_compilation' => true,
            'title' => 'Greatest Hits - ' . $this->faker->words(2, true),
        ]);
    }

    /**
     * Create an album with tracks.
     */
    public function withTracks(int $count = null): static
    {
        return $this->afterCreating(function (Album $album) use ($count) {
            $trackCount = $count ?? $album->total_tracks ?? $this->faker->numberBetween(8, 15);
            
            Track::factory()->count($trackCount)->create([
                'album_id' => $album->id,
            ]);

            // Update album duration based on actual tracks
            $totalDuration = $album->tracks()->sum('milliseconds');
            $album->update(['total_duration_ms' => $totalDuration]);
        });
    }

    /**
     * Create a classic album (released before 1990).
     */
    public function classic(): static
    {
        return $this->state(fn (array $attributes) => [
            'release_date' => $this->faker->dateTimeBetween('-50 years', '-33 years'),
        ]);
    }
}
```

### Track Factory with Complex Relationships

```php
<?php

// database/factories/TrackFactory.php
namespace Database\Factories;

use App\Models\Track;
use App\Models\Album;
use App\Models\MediaType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        $duration = $this->faker->numberBetween(120000, 480000); // 2-8 minutes
        
        return [
            'name' => $this->faker->words(rand(1, 5), true),
            'album_id' => Album::factory(),
            'media_type_id' => MediaType::factory(),
            'composer' => $this->faker->optional(0.7)->name(), // 70% have composer
            'milliseconds' => $duration,
            'bytes' => $this->calculateFileSize($duration),
            'unit_price' => $this->faker->randomElement([0.99, 1.29, 1.49]),
            'track_number' => $this->faker->numberBetween(1, 20),
            'disc_number' => $this->faker->numberBetween(1, 2),
            'is_explicit' => $this->faker->boolean(15), // 15% chance
            'is_active' => $this->faker->boolean(95), // 95% chance
            'preview_url' => $this->faker->optional(0.8)->url(),
            'lyrics' => $this->faker->optional(0.6)->paragraphs(4, true),
        ];
    }

    /**
     * Calculate approximate file size based on duration.
     */
    private function calculateFileSize(int $milliseconds): int
    {
        // Approximate MP3 file size: 1MB per minute at 128kbps
        $minutes = $milliseconds / 60000;
        return (int) ($minutes * 1024 * 1024);
    }

    /**
     * Create a short track (under 3 minutes).
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'milliseconds' => $this->faker->numberBetween(120000, 180000),
        ]);
    }

    /**
     * Create a long track (over 6 minutes).
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'milliseconds' => $this->faker->numberBetween(360000, 720000),
        ]);
    }

    /**
     * Create an instrumental track.
     */
    public function instrumental(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $attributes['name'] . ' (Instrumental)',
            'lyrics' => null,
        ]);
    }

    /**
     * Create a track with categories.
     */
    public function withCategories(array $categoryTypes = []): static
    {
        return $this->afterCreating(function (Track $track) use ($categoryTypes) {
            if (empty($categoryTypes)) {
                $categoryTypes = [CategoryType::GENRE, CategoryType::MOOD];
            }

            foreach ($categoryTypes as $type) {
                $category = Category::factory()->create(['type' => $type]);
                $track->categories()->attach($category->id, [
                    'is_primary' => $type === CategoryType::GENRE,
                    'sort_order' => array_search($type, $categoryTypes) + 1,
                ]);
            }
        });
    }
}
```

## Seeder Strategies

### Hierarchical Category Seeder

```php
<?php

// database/seeders/CategorySeeder.php
namespace Database\Seeders;

use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedGenreCategories();
        $this->seedMoodCategories();
        $this->seedEraCategories();
    }

    /**
     * Seed genre categories with hierarchy.
     */
    private function seedGenreCategories(): void
    {
        $genreHierarchy = [
            'Rock' => ['Hard Rock', 'Soft Rock', 'Progressive Rock'],
            'Electronic' => ['House', 'Techno', 'Ambient'],
            'Jazz' => ['Smooth Jazz', 'Bebop', 'Fusion'],
            'Classical' => ['Baroque', 'Romantic', 'Modern'],
        ];

        foreach ($genreHierarchy as $parentName => $children) {
            $parent = Category::factory()->create([
                'name' => $parentName,
                'type' => CategoryType::GENRE,
                'description' => "The {$parentName} music genre",
                'is_active' => true,
            ]);

            foreach ($children as $childName) {
                $child = Category::factory()->create([
                    'name' => $childName,
                    'type' => CategoryType::GENRE,
                    'description' => "A subgenre of {$parentName}",
                    'is_active' => true,
                ]);

                $child->makeChildOf($parent);
            }
        }
    }

    /**
     * Seed mood categories.
     */
    private function seedMoodCategories(): void
    {
        $moods = [
            'Energetic' => 'High energy and motivational music',
            'Relaxing' => 'Calm and peaceful music',
            'Melancholic' => 'Sad and nostalgic music',
            'Upbeat' => 'Happy and cheerful music',
        ];

        foreach ($moods as $name => $description) {
            Category::factory()->create([
                'name' => $name,
                'type' => CategoryType::MOOD,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Seed era categories.
     */
    private function seedEraCategories(): void
    {
        $eras = [
            '1960s' => 'Music from the 1960s',
            '1970s' => 'Music from the 1970s',
            '1980s' => 'Music from the 1980s',
            '1990s' => 'Music from the 1990s',
            '2000s' => 'Music from the 2000s',
        ];

        foreach ($eras as $name => $description) {
            Category::factory()->create([
                'name' => $name,
                'type' => CategoryType::ERA,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }
}
```

### Test-Specific Seeder

```php
<?php

// database/seeders/TestSeeder.php
namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Seed data specifically for testing scenarios.
     */
    public function run(): void
    {
        $this->seedTestCategories();
        $this->seedTestMusicData();
    }

    /**
     * Seed minimal categories for testing.
     */
    private function seedTestCategories(): void
    {
        $categoryTypes = [
            CategoryType::GENRE => 'Rock',
            CategoryType::MOOD => 'Energetic',
            CategoryType::THEME => 'Workout',
            CategoryType::ERA => '1970s',
        ];

        foreach ($categoryTypes as $type => $name) {
            Category::factory()->create([
                'name' => $name,
                'type' => $type,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Seed predictable music data for testing.
     */
    private function seedTestMusicData(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'country' => 'USA',
            'formed_year' => 1970,
            'is_active' => true,
        ]);

        $album = Album::factory()->create([
            'title' => 'Test Album',
            'artist_id' => $artist->id,
            'release_date' => '1975-01-01',
            'total_tracks' => 10,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Track::factory()->create([
                'name' => "Test Track {$i}",
                'album_id' => $album->id,
                'track_number' => $i,
                'milliseconds' => 180000, // 3 minutes
                'unit_price' => 0.99,
                'is_active' => true,
            ]);
        }
    }
}
```

## Test Database State Management

### Database Configuration for Testing

```php
<?php

// config/database.php - Testing configuration
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
    'journal_mode' => 'WAL',
    'synchronous' => 'NORMAL',
    'cache_size' => 10000,
    'temp_store' => 'MEMORY',
],
```

### Pest Configuration for Database Management

```php
<?php

// tests/Pest.php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

// Use RefreshDatabase for feature tests that need clean state
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

// Use DatabaseTransactions for unit tests for better performance
uses(Tests\TestCase::class, DatabaseTransactions::class)->in('Unit');

// Custom database setup for integration tests
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Integration');

// Helper function for creating test data
function createTestMusicHierarchy(): array
{
    $artist = Artist::factory()->create(['name' => 'Test Artist']);
    $album = Album::factory()->create(['artist_id' => $artist->id, 'title' => 'Test Album']);
    $tracks = Track::factory()->count(5)->create(['album_id' => $album->id]);

    return compact('artist', 'album', 'tracks');
}

function createTestCategories(): array
{
    return [
        'genre' => Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Rock']),
        'mood' => Category::factory()->create(['type' => CategoryType::MOOD, 'name' => 'Energetic']),
        'era' => Category::factory()->create(['type' => CategoryType::ERA, 'name' => '1970s']),
    ];
}
```

### Custom Test Case for Complex Scenarios

```php
<?php

// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Setup method for tests requiring hierarchical data.
     */
    protected function setUpHierarchicalData(): void
    {
        // Seed basic categories
        $this->artisan('db:seed', ['--class' => 'CategorySeeder']);
    }

    /**
     * Setup method for tests requiring complete music data.
     */
    protected function setUpMusicData(): void
    {
        $this->artisan('db:seed', ['--class' => 'TestSeeder']);
    }

    /**
     * Create a user with specific roles for testing.
     */
    protected function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    /**
     * Assert that a model has specific categories.
     */
    protected function assertHasCategories($model, array $expectedCategories): void
    {
        $actualCategories = $model->categories->pluck('name')->toArray();

        foreach ($expectedCategories as $expected) {
            $this->assertContains($expected, $actualCategories);
        }
    }

    /**
     * Assert hierarchical relationship.
     */
    protected function assertIsChildOf($child, $parent): void
    {
        $this->assertTrue($child->ancestors->contains('id', $parent->id));
    }
}
```

## Data Cleanup Strategies

### Automatic Cleanup with Traits

```php
<?php

// tests/Traits/CleansUpTestData.php
namespace Tests\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait CleansUpTestData
{
    /**
     * Clean up uploaded files after test.
     */
    protected function tearDown(): void
    {
        $this->cleanupUploadedFiles();
        $this->cleanupCacheData();

        parent::tearDown();
    }

    /**
     * Clean up any uploaded test files.
     */
    protected function cleanupUploadedFiles(): void
    {
        $testDirectories = [
            'public/test-uploads',
            'public/artist-images',
            'public/album-covers',
        ];

        foreach ($testDirectories as $directory) {
            if (Storage::exists($directory)) {
                Storage::deleteDirectory($directory);
            }
        }
    }

    /**
     * Clean up cache data.
     */
    protected function cleanupCacheData(): void
    {
        cache()->flush();
    }

    /**
     * Reset auto-increment values for consistent testing.
     */
    protected function resetAutoIncrements(): void
    {
        $tables = ['artists', 'albums', 'tracks', 'categories'];

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
        }
    }
}
```

### Memory Management for Large Tests

```php
<?php

// tests/Traits/ManagesMemory.php
namespace Tests\Traits;

trait ManagesMemory
{
    /**
     * Monitor memory usage during test.
     */
    protected function assertMemoryUsage(int $maxMegabytes = 50): void
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;

        $this->assertLessThan(
            $maxMegabytes,
            $memoryUsage,
            "Memory usage ({$memoryUsage}MB) exceeded limit ({$maxMegabytes}MB)"
        );
    }

    /**
     * Force garbage collection and memory cleanup.
     */
    protected function cleanupMemory(): void
    {
        // Clear any large collections
        if (isset($this->largeDataSets)) {
            foreach ($this->largeDataSets as $dataSet) {
                unset($dataSet);
            }
        }

        // Force garbage collection
        gc_collect_cycles();
    }
}
```

## Hierarchical Data Factories

### Category Factory with Hierarchy Support

```php
<?php

// database/factories/CategoryFactory.php
namespace Database\Factories;

use App\Models\Category;
use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(CategoryType::cases()),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => $this->faker->boolean(90),
            'color' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement(['music', 'guitar', 'piano', 'microphone']),
            'metadata' => [
                'created_by' => 'factory',
                'test_data' => true,
            ],
        ];
    }

    /**
     * Create a category with children.
     */
    public function withChildren(int $count = 3): static
    {
        return $this->afterCreating(function (Category $category) use ($count) {
            $children = Category::factory()->count($count)->create([
                'type' => $category->type, // Same type as parent
            ]);

            foreach ($children as $child) {
                $child->makeChildOf($category);
            }
        });
    }

    /**
     * Create a deep hierarchy.
     */
    public function deepHierarchy(int $depth = 3): static
    {
        return $this->afterCreating(function (Category $root) use ($depth) {
            $current = $root;

            for ($i = 1; $i < $depth; $i++) {
                $child = Category::factory()->create([
                    'type' => $root->type,
                    'name' => $root->name . " Level {$i}",
                ]);

                $child->makeChildOf($current);
                $current = $child;
            }
        });
    }
}
```

## Polymorphic Data Factories

### Categorizable Factory Trait

```php
<?php

// database/factories/Traits/HasCategories.php
namespace Database\Factories\Traits;

use App\Models\Category;
use App\Enums\CategoryType;

trait HasCategories
{
    /**
     * Add categories to the model after creation.
     */
    public function withRandomCategories(int $count = 2): static
    {
        return $this->afterCreating(function ($model) use ($count) {
            $validTypes = CategoryType::forModel(get_class($model));
            $categories = Category::factory()->count($count)->create([
                'type' => $this->faker->randomElement($validTypes),
            ]);

            foreach ($categories as $index => $category) {
                $model->categories()->attach($category->id, [
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                    'metadata' => ['attached_by' => 'factory'],
                ]);
            }
        });
    }

    /**
     * Add specific category types.
     */
    public function withCategoryTypes(array $types): static
    {
        return $this->afterCreating(function ($model) use ($types) {
            foreach ($types as $index => $type) {
                $category = Category::factory()->create(['type' => $type]);

                $model->categories()->attach($category->id, [
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);
            }
        });
    }
}
```

## Best Practices

### Factory Design Guidelines

1. **Realistic Data**: Generate data that resembles real-world scenarios
2. **Relationships**: Use factory relationships instead of hardcoded IDs
3. **State Methods**: Create specific states for different test scenarios
4. **Performance**: Avoid creating unnecessary related models
5. **Consistency**: Use consistent naming and structure across factories

### Seeder Best Practices

1. **Environment Specific**: Different seeders for different environments
2. **Idempotent**: Seeders should be safe to run multiple times
3. **Hierarchical**: Build hierarchies in correct order
4. **Validation**: Validate data before insertion

### Database Management

1. **Isolation**: Each test should have clean database state
2. **Performance**: Use appropriate cleanup strategies
3. **Memory**: Monitor and manage memory usage
4. **Transactions**: Use database transactions for unit tests

---

**Navigation:**

- **Previous:** [Integration Testing Guide](040-integration-testing-guide.md)
- **Next:** [RBAC Testing Guide](060-rbac-testing-guide.md)
- **Up:** [Testing Documentation](000-testing-index.md)
