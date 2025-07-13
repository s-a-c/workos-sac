# Test Data Management Guide

This guide covers comprehensive test data creation and management strategies for the Chinook Filament admin panel,
including factories, seeders, and database state management.

## Table of Contents

- [Overview](#overview)
- [Factory Patterns](#factory-patterns)
- [Seeder Strategies](#seeder-strategies)
- [Database State Management](#database-state-management)
- [Test Data Isolation](#test-data-isolation)
- [Performance Optimization](#performance-optimization)
- [Data Cleanup Strategies](#data-cleanup-strategies)
- [Best Practices](#best-practices)

## Overview

Effective test data management is crucial for reliable, maintainable tests. This guide provides strategies for creating
realistic test data while maintaining test isolation and performance.

### Test Data Principles

- **Isolation**: Each test should have independent data
- **Realism**: Test data should reflect real-world scenarios
- **Performance**: Data creation should be fast and efficient
- **Maintainability**: Easy to update and extend test data
- **Consistency**: Predictable data patterns across tests

## Factory Patterns

### Enhanced Model Factories

```php
// database/factories/ArtistFactory.php
<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'country' => fake()->countryCode(),
            'biography' => fake()->paragraphs(3, true),
            'website' => fake()->url(),
            'formed_year' => fake()->numberBetween(1950, 2023),
            'is_active' => true,
            'social_links' => [
                ['platform' => 'facebook', 'url' => fake()->url()],
                ['platform' => 'twitter', 'url' => fake()->url()],
            ],
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withGenres(int $count = 2): static
    {
        return $this->afterCreating(function (Artist $artist) use ($count) {
            $genres = \App\Models\Category::factory()
                ->count($count)
                ->genre()
                ->create();
            
            $artist->attachCategories($genres->pluck('id')->toArray());
        });
    }

    public function withAlbums(int $count = 3): static
    {
        return $this->afterCreating(function (Artist $artist) use ($count) {
            \App\Models\Album::factory()
                ->count($count)
                ->for($artist)
                ->create();
        });
    }

    public function rock(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->randomElement([
                'The Rock Band', 'Metal Masters', 'Stone Crushers'
            ]),
            'formed_year' => fake()->numberBetween(1960, 1990),
        ]);
    }

    public function jazz(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->randomElement([
                'Jazz Ensemble', 'Blue Note Quartet', 'Smooth Jazz Collective'
            ]),
            'formed_year' => fake()->numberBetween(1920, 1980),
        ]);
    }
}
```

### Category Factory with Hierarchical Support

```php
// database/factories/CategoryFactory.php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(CategoryType::cases()),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'color' => fake()->hexColor(),
            'icon' => fake()->randomElement(['fas fa-music', 'fas fa-guitar', 'fas fa-drum']),
            'metadata' => [
                'popularity' => fake()->numberBetween(1, 100),
                'era' => fake()->randomElement(['60s', '70s', '80s', '90s', '2000s']),
            ],
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function genre(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CategoryType::GENRE,
            'name' => fake()->randomElement([
                'Rock', 'Jazz', 'Classical', 'Pop', 'Hip Hop', 'Electronic'
            ]),
        ]);
    }

    public function mood(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CategoryType::MOOD,
            'name' => fake()->randomElement([
                'Energetic', 'Relaxing', 'Melancholic', 'Upbeat', 'Aggressive'
            ]),
        ]);
    }

    public function withParent(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
            'path' => $parent->path . '/' . fake()->slug(),
        ]);
    }

    public function withChildren(int $count = 3): static
    {
        return $this->afterCreating(function (Category $category) use ($count) {
            Category::factory()
                ->count($count)
                ->withParent($category)
                ->create();
        });
    }
}
```

### Track Factory with Relationships

```php
// database/factories/TrackFactory.php
<?php

namespace Database\Factories;

use App\Models\Track;
use App\Models\Album;
use App\Models\MediaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        return [
            'album_id' => Album::factory(),
            'media_type_id' => MediaType::factory(),
            'name' => fake()->sentence(3),
            'composer' => fake()->name(),
            'milliseconds' => fake()->numberBetween(120000, 360000), // 2-6 minutes
            'bytes' => fake()->numberBetween(3000000, 8000000), // 3-8 MB
            'unit_price' => fake()->randomFloat(2, 0.99, 2.99),
            'track_number' => fake()->numberBetween(1, 15),
            'disc_number' => 1,
            'is_explicit' => fake()->boolean(20), // 20% chance
            'is_active' => true,
            'preview_url' => fake()->url(),
            'lyrics' => fake()->paragraphs(4, true),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function explicit(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_explicit' => true,
        ]);
    }

    public function withCategories(int $count = 2): static
    {
        return $this->afterCreating(function (Track $track) use ($count) {
            $categories = \App\Models\Category::factory()
                ->count($count)
                ->create();
            
            $track->attachCategories($categories->pluck('id')->toArray());
        });
    }
}
```

## Seeder Strategies

### Test-Specific Seeders

```php
// database/seeders/TestDataSeeder.php
<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create test users with roles
        $this->createTestUsers();
        
        // Create category hierarchy
        $this->createCategoryHierarchy();
        
        // Create music data
        $this->createMusicData();
        
        // Create customer data
        $this->createCustomerData();
    }

    private function createTestUsers(): void
    {
        $roles = ['Super Admin', 'Admin', 'Manager', 'Editor', 'Customer Service', 'User', 'Guest'];
        
        foreach ($roles as $role) {
            User::factory()
                ->withRole($role)
                ->create([
                    'email' => strtolower(str_replace(' ', '.', $role)) . '@test.com',
                    'name' => "Test {$role}",
                ]);
        }
    }

    private function createCategoryHierarchy(): void
    {
        // Create root genres
        $rock = Category::factory()->genre()->create(['name' => 'Rock']);
        $jazz = Category::factory()->genre()->create(['name' => 'Jazz']);
        
        // Create sub-genres
        Category::factory()->genre()->withParent($rock)->create(['name' => 'Hard Rock']);
        Category::factory()->genre()->withParent($rock)->create(['name' => 'Alternative Rock']);
        Category::factory()->genre()->withParent($jazz)->create(['name' => 'Smooth Jazz']);
        Category::factory()->genre()->withParent($jazz)->create(['name' => 'Bebop']);
        
        // Create moods
        Category::factory()->mood()->create(['name' => 'Energetic']);
        Category::factory()->mood()->create(['name' => 'Relaxing']);
    }

    private function createMusicData(): void
    {
        // Create artists with albums and tracks
        Artist::factory()
            ->count(10)
            ->withGenres(2)
            ->withAlbums(3)
            ->create()
            ->each(function ($artist) {
                $artist->albums->each(function ($album) {
                    Track::factory()
                        ->count(12)
                        ->for($album)
                        ->withCategories(2)
                        ->create();
                });
            });
    }

    private function createCustomerData(): void
    {
        // Create customers with invoices
        \App\Models\Customer::factory()
            ->count(50)
            ->create()
            ->each(function ($customer) {
                \App\Models\Invoice::factory()
                    ->count(rand(1, 5))
                    ->for($customer)
                    ->create();
            });
    }
}
```

### Minimal Test Seeder

```php
// database/seeders/MinimalTestSeeder.php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class MinimalTestSeeder extends Seeder
{
    public function run(): void
    {
        // Only create essential data for fast tests
        User::factory()->withRole('Admin')->create(['email' => 'admin@test.com']);
        User::factory()->withRole('Editor')->create(['email' => 'editor@test.com']);
        User::factory()->withRole('Guest')->create(['email' => 'guest@test.com']);
    }
}
```

## Database State Management

### Transaction-Based Testing

```php
// tests/Feature/DatabaseTransactionTest.php
<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseTransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fast_database_operations(): void
    {
        // This test uses transactions for speed
        $artist = \App\Models\Artist::factory()->create();
        
        expect($artist)->toBeInstanceOf(\App\Models\Artist::class);
        
        // Transaction will be rolled back automatically
    }
}
```

### Refresh Database Strategy

```php
// tests/Feature/RefreshDatabaseTest.php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed minimal required data
        $this->artisan('db:seed', ['--class' => 'MinimalTestSeeder']);
    }

    public function test_with_fresh_database(): void
    {
        // Database is completely fresh for each test
        $artist = \App\Models\Artist::factory()->create();
        
        expect(\App\Models\Artist::count())->toBe(1);
    }
}
```

## Test Data Isolation

### Test-Specific Data Creation

```php
// tests/Traits/CreatesTestData.php
<?php

namespace Tests\Traits;

use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Category;
use App\Models\User;

trait CreatesTestData
{
    protected function createCompleteArtist(): Artist
    {
        return Artist::factory()
            ->withGenres(2)
            ->withAlbums(2)
            ->create();
    }

    protected function createCategoryHierarchy(): array
    {
        $parent = Category::factory()->genre()->create(['name' => 'Rock']);
        $child = Category::factory()->genre()->withParent($parent)->create(['name' => 'Hard Rock']);
        
        return [$parent, $child];
    }

    protected function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        
        return $user;
    }

    protected function createMusicCollection(int $artistCount = 3): array
    {
        $artists = Artist::factory()->count($artistCount)->create();
        
        $artists->each(function ($artist) {
            Album::factory()
                ->count(2)
                ->for($artist)
                ->create()
                ->each(function ($album) {
                    Track::factory()
                        ->count(10)
                        ->for($album)
                        ->create();
                });
        });
        
        return $artists->toArray();
    }
}
```

## Performance Optimization

### Efficient Factory Usage

```php
// Efficient batch creation
public function test_efficient_data_creation(): void
{
    // Create multiple records efficiently
    $artists = Artist::factory()->count(100)->create();
    
    // Batch relationship creation
    $albums = Album::factory()
        ->count(300)
        ->sequence(fn ($sequence) => [
            'artist_id' => $artists->random()->id
        ])
        ->create();
    
    expect($artists)->toHaveCount(100);
    expect($albums)->toHaveCount(300);
}
```

### Memory Management

```php
// tests/Traits/MemoryManagement.php
<?php

namespace Tests\Traits;

trait MemoryManagement
{
    protected function clearModelCache(): void
    {
        // Clear Eloquent model cache
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        
        // Force garbage collection
        gc_collect_cycles();
    }

    protected function resetFactorySequences(): void
    {
        // Reset factory sequences to prevent memory leaks
        \Illuminate\Database\Eloquent\Factories\Factory::resetSequences();
    }
}
```

## Data Cleanup Strategies

### Automatic Cleanup

```php
// tests/TestCase.php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\MemoryManagement;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, MemoryManagement;

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->clearModelCache();
        $this->resetFactorySequences();
        
        parent::tearDown();
    }
}
```

### Manual Cleanup

```php
public function test_with_manual_cleanup(): void
{
    // Create test data
    $artists = Artist::factory()->count(10)->create();
    
    // Perform test operations
    expect($artists)->toHaveCount(10);
    
    // Manual cleanup if needed
    Artist::whereIn('id', $artists->pluck('id'))->delete();
    
    expect(Artist::count())->toBe(0);
}
```

## Best Practices

### Factory Design Patterns

1. **State Methods**: Use descriptive state methods for different scenarios
2. **Relationship Factories**: Create related data efficiently
3. **Realistic Data**: Generate data that reflects real-world usage
4. **Performance**: Optimize for test execution speed

### Seeder Strategies

1. **Minimal Seeders**: Create only essential data for most tests
2. **Comprehensive Seeders**: Use for integration tests requiring full data sets
3. **Environment-Specific**: Different seeders for different test environments
4. **Idempotent**: Seeders should be safe to run multiple times

### Test Data Principles

1. **Isolation**: Each test should have independent data
2. **Predictability**: Use consistent data patterns
3. **Maintainability**: Easy to update and extend
4. **Performance**: Fast creation and cleanup

## Related Documentation

- **[Test Environment Setup](020-test-environment-setup.md)** - Environment configuration
- **[Resource Testing](040-resource-testing.md)** - Testing Filament resources
- **[Performance Testing](120-performance-testing.md)** - Load testing strategies
- **[Database Testing](110-database-testing.md)** - Database operation testing

---

## Navigation

**← Previous:** [Test Environment Setup](020-test-environment-setup.md)

**Next →** [Continuous Integration](040-ci-integration.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
