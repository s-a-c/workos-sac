# Model Factories Guide

## Table of Contents

- [Overview](#overview)
- [Basic Factory Implementation](#basic-factory-implementation)
- [Advanced Factory Patterns](#advanced-factory-patterns)
- [Relationship Factories](#relationship-factories)
- [State Management](#state-management)
- [Seeding Strategies](#seeding-strategies)
- [Testing with Factories](#testing-with-factories)
- [Performance Optimization](#performance-optimization)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive model factory patterns for Laravel 12 in the Chinook application. Factories provide a clean way to generate test data and seed databases with realistic content for development and testing environments.

**🚀 Key Features:**
- **Laravel 12 Modern Syntax**: Latest factory patterns and features
- **Realistic Data Generation**: Contextually appropriate test data
- **Relationship Management**: Complex relationship creation
- **State Variations**: Different model states for testing scenarios
- **Performance Optimized**: Efficient bulk data generation

## Basic Factory Implementation

### Artist Factory

```php
<?php
// database/factories/ArtistFactory.php

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
            'name' => $this->faker->unique()->name(),
            'bio' => $this->faker->paragraphs(3, true),
            'formed_date' => $this->faker->dateTimeBetween('-50 years', '-1 year'),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'website' => $this->faker->optional(0.7)->url(),
            'social_links' => [
                'spotify' => $this->faker->optional(0.8)->url(),
                'apple_music' => $this->faker->optional(0.6)->url(),
                'youtube' => $this->faker->optional(0.9)->url(),
                'instagram' => $this->faker->optional(0.7)->userName(),
                'twitter' => $this->faker->optional(0.6)->userName(),
            ],
            'metadata' => [
                'genre_primary' => $this->faker->randomElement(['Rock', 'Pop', 'Jazz', 'Classical', 'Electronic']),
                'country_origin' => $this->faker->countryCode(),
                'record_label' => $this->faker->optional(0.8)->company(),
            ],
            'created_by' => User::factory(),
            'updated_by' => function (array $attributes) {
                return $attributes['created_by'];
            },
        ];
    }

    /**
     * Indicate that the artist is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'bio' => $attributes['bio'] . ' [INACTIVE]',
        ]);
    }

    /**
     * Indicate that the artist is a solo performer
     */
    public function solo(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'type' => 'solo',
                'instruments' => $this->faker->randomElements(['vocals', 'guitar', 'piano', 'drums'], 2),
            ]),
        ]);
    }

    /**
     * Indicate that the artist is a band
     */
    public function band(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->words(2, true) . ' ' . $this->faker->randomElement(['Band', 'Group', 'Collective']),
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'type' => 'band',
                'member_count' => $this->faker->numberBetween(2, 8),
            ]),
        ]);
    }

    /**
     * Create artist with albums
     */
    public function withAlbums(int $count = 3): static
    {
        return $this->has(
            \App\Models\Album::factory()->count($count),
            'albums'
        );
    }
}
```

### Album Factory

```php
<?php
// database/factories/AlbumFactory.php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlbumFactory extends Factory
{
    protected $model = Album::class;

    public function definition(): array
    {
        $releaseDate = $this->faker->dateTimeBetween('-20 years', 'now');
        
        return [
            'title' => $this->faker->words(rand(1, 4), true),
            'artist_id' => Artist::factory(),
            'release_date' => $releaseDate,
            'description' => $this->faker->optional(0.8)->paragraphs(2, true),
            'total_duration_ms' => $this->faker->numberBetween(1800000, 4800000), // 30-80 minutes
            'track_count' => $this->faker->numberBetween(8, 15),
            'is_compilation' => $this->faker->boolean(10), // 10% chance
            'is_explicit' => $this->faker->boolean(20), // 20% chance
            'metadata' => [
                'producer' => $this->faker->optional(0.9)->name(),
                'studio' => $this->faker->optional(0.7)->company() . ' Studios',
                'label' => $this->faker->optional(0.8)->company() . ' Records',
                'catalog_number' => $this->faker->optional(0.6)->bothify('??-####'),
            ],
            'created_by' => User::factory(),
            'updated_by' => function (array $attributes) {
                return $attributes['created_by'];
            },
        ];
    }

    /**
     * Create a compilation album
     */
    public function compilation(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Best of ' . $this->faker->words(2, true),
            'is_compilation' => true,
            'track_count' => $this->faker->numberBetween(15, 25),
            'description' => 'A compilation of the greatest hits and fan favorites.',
        ]);
    }

    /**
     * Create a live album
     */
    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $attributes['title'] . ' (Live)',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'recording_type' => 'live',
                'venue' => $this->faker->company() . ' ' . $this->faker->randomElement(['Arena', 'Theater', 'Hall']),
                'recording_date' => $this->faker->dateTimeBetween($attributes['release_date'], 'now'),
            ]),
        ]);
    }

    /**
     * Create album with tracks
     */
    public function withTracks(int $count = null): static
    {
        $trackCount = $count ?? $this->faker->numberBetween(8, 15);
        
        return $this->has(
            \App\Models\Track::factory()->count($trackCount)->sequence(
                fn ($sequence) => ['track_number' => $sequence->index + 1]
            ),
            'tracks'
        );
    }
}
```

## Advanced Factory Patterns

### Track Factory with Complex Logic

```php
<?php
// database/factories/TrackFactory.php

class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        $durationMs = $this->faker->numberBetween(120000, 480000); // 2-8 minutes
        
        return [
            'name' => $this->faker->words(rand(1, 5), true),
            'album_id' => Album::factory(),
            'media_type_id' => 1, // Default to audio
            'composer' => $this->faker->optional(0.7)->name(),
            'track_number' => 1,
            'duration_ms' => $durationMs,
            'file_size_bytes' => $this->calculateFileSize($durationMs),
            'price' => $this->faker->randomFloat(2, 0.99, 2.99),
            'is_explicit' => $this->faker->boolean(15),
            'is_featured' => $this->faker->boolean(5),
            'lyrics' => $this->faker->optional(0.6)->paragraphs(4, true),
            'metadata' => [
                'bpm' => $this->faker->optional(0.8)->numberBetween(60, 180),
                'key' => $this->faker->optional(0.7)->randomElement(['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']),
                'energy_level' => $this->faker->optional(0.9)->numberBetween(1, 10),
            ],
            'created_by' => User::factory(),
        ];
    }

    /**
     * Calculate realistic file size based on duration
     */
    private function calculateFileSize(int $durationMs): int
    {
        // Approximate file size for 320kbps MP3
        $durationSeconds = $durationMs / 1000;
        $bytesPerSecond = 40000; // ~320kbps
        return (int) ($durationSeconds * $bytesPerSecond);
    }

    /**
     * Create a single/hit track
     */
    public function hit(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'price' => 1.29,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'chart_position' => $this->faker->numberBetween(1, 40),
                'play_count' => $this->faker->numberBetween(1000000, 50000000),
            ]),
        ]);
    }

    /**
     * Create an instrumental track
     */
    public function instrumental(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $attributes['name'] . ' (Instrumental)',
            'lyrics' => null,
            'composer' => $this->faker->name(),
        ]);
    }
}
```

## Relationship Factories

### Complex Relationship Creation

```php
<?php
// database/factories/PlaylistFactory.php

class PlaylistFactory extends Factory
{
    protected $model = Playlist::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(rand(2, 4), true),
            'description' => $this->faker->optional(0.8)->sentence(),
            'is_public' => $this->faker->boolean(70),
            'is_collaborative' => $this->faker->boolean(20),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create playlist with tracks
     */
    public function withTracks(int $count = 20): static
    {
        return $this->afterCreating(function (Playlist $playlist) use ($count) {
            $tracks = Track::factory()->count($count)->create();
            
            $tracks->each(function ($track, $index) use ($playlist) {
                $playlist->tracks()->attach($track->id, [
                    'position' => $index + 1,
                    'added_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
                    'added_by' => $playlist->created_by,
                ]);
            });
        });
    }

    /**
     * Create a genre-specific playlist
     */
    public function forGenre(string $genre): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $genre . ' ' . $this->faker->randomElement(['Hits', 'Classics', 'Mix', 'Collection']),
            'description' => "A curated collection of the best {$genre} music.",
        ])->afterCreating(function (Playlist $playlist) use ($genre) {
            // Create tracks with specific genre
            $tracks = Track::factory()
                ->count(15)
                ->state(['metadata' => ['genre' => $genre]])
                ->create();
                
            $playlist->tracks()->attach($tracks->pluck('id'));
        });
    }
}
```

## State Management

### Factory States for Different Scenarios

```php
<?php
// Enhanced factory states

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ];
    }

    /**
     * Admin user state
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => 'admin@chinook.app',
        ])->afterCreating(function (User $user) {
            $user->assignRole('admin');
        });
    }

    /**
     * Customer user state
     */
    public function customer(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('customer');
            
            // Create customer profile
            Customer::factory()->create([
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        });
    }

    /**
     * User with purchase history
     */
    public function withPurchases(int $invoiceCount = 5): static
    {
        return $this->customer()->afterCreating(function (User $user) use ($invoiceCount) {
            $customer = $user->customer;
            
            Invoice::factory()
                ->count($invoiceCount)
                ->withItems()
                ->create(['customer_id' => $customer->id]);
        });
    }
}
```

## Seeding Strategies

### Comprehensive Database Seeding

```php
<?php
// database/seeders/ChinookSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Artist, Album, Track, User, Customer, Playlist};

class ChinookSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::factory()->admin()->create();

        // Create sample artists with albums and tracks
        $this->createMusicCatalog();
        
        // Create customers with purchase history
        $this->createCustomers();
        
        // Create playlists
        $this->createPlaylists();
    }

    private function createMusicCatalog(): void
    {
        // Rock artists
        Artist::factory()
            ->count(5)
            ->band()
            ->state(['metadata' => ['genre_primary' => 'Rock']])
            ->withAlbums(3)
            ->create()
            ->each(function ($artist) {
                $artist->albums->each(function ($album) {
                    Track::factory()
                        ->count(rand(10, 14))
                        ->sequence(fn ($sequence) => ['track_number' => $sequence->index + 1])
                        ->create(['album_id' => $album->id]);
                });
            });

        // Pop artists
        Artist::factory()
            ->count(3)
            ->solo()
            ->state(['metadata' => ['genre_primary' => 'Pop']])
            ->withAlbums(2)
            ->create()
            ->each(function ($artist) {
                $artist->albums->each(function ($album) {
                    Track::factory()
                        ->count(rand(8, 12))
                        ->sequence(fn ($sequence) => ['track_number' => $sequence->index + 1])
                        ->create(['album_id' => $album->id]);
                });
            });
    }

    private function createCustomers(): void
    {
        User::factory()
            ->count(50)
            ->withPurchases(rand(1, 10))
            ->create();
    }

    private function createPlaylists(): void
    {
        // Public playlists
        Playlist::factory()
            ->count(10)
            ->state(['is_public' => true])
            ->withTracks(25)
            ->create();

        // Genre-specific playlists
        $genres = ['Rock', 'Pop', 'Jazz', 'Classical'];
        foreach ($genres as $genre) {
            Playlist::factory()
                ->forGenre($genre)
                ->create();
        }
    }
}
```

## Testing with Factories

### Factory Testing Patterns

```php
<?php
// tests/Feature/FactoryTest.php

use App\Models\{Artist, Album, Track};
use Tests\TestCase;

class FactoryTest extends TestCase
{
    public function test_artist_factory_creates_valid_artist(): void
    {
        $artist = Artist::factory()->create();

        expect($artist->name)->toBeString();
        expect($artist->bio)->toBeString();
        expect($artist->is_active)->toBeBool();
        expect($artist->created_by)->toBeInt();
    }

    public function test_artist_factory_states_work(): void
    {
        $soloArtist = Artist::factory()->solo()->create();
        $bandArtist = Artist::factory()->band()->create();

        expect($soloArtist->metadata['type'])->toBe('solo');
        expect($bandArtist->metadata['type'])->toBe('band');
        expect($bandArtist->metadata['member_count'])->toBeGreaterThan(1);
    }

    public function test_album_with_tracks_factory(): void
    {
        $album = Album::factory()->withTracks(12)->create();

        expect($album->tracks)->toHaveCount(12);
        expect($album->tracks->first()->track_number)->toBe(1);
        expect($album->tracks->last()->track_number)->toBe(12);
    }

    public function test_playlist_with_tracks_factory(): void
    {
        $playlist = Playlist::factory()->withTracks(20)->create();

        expect($playlist->tracks)->toHaveCount(20);
        expect($playlist->tracks->first()->pivot->position)->toBe(1);
    }
}
```

## Performance Optimization

### Efficient Factory Usage

```php
<?php
// Optimized factory patterns for large datasets

class OptimizedFactorySeeder extends Seeder
{
    public function run(): void
    {
        // Disable model events for performance
        Artist::unsetEventDispatcher();
        Album::unsetEventDispatcher();
        Track::unsetEventDispatcher();

        // Use raw inserts for large datasets
        $this->createLargeDataset();

        // Re-enable events
        Artist::setEventDispatcher(app('events'));
        Album::setEventDispatcher(app('events'));
        Track::setEventDispatcher(app('events'));
    }

    private function createLargeDataset(): void
    {
        // Create artists in batches
        $artists = Artist::factory()->count(100)->make()->toArray();
        Artist::insert($artists);

        // Get created artist IDs
        $artistIds = Artist::pluck('id')->toArray();

        // Create albums in batches
        $albums = [];
        foreach ($artistIds as $artistId) {
            $albumCount = rand(1, 5);
            for ($i = 0; $i < $albumCount; $i++) {
                $albums[] = Album::factory()->make(['artist_id' => $artistId])->toArray();
            }
        }
        Album::insert($albums);

        // Create tracks efficiently
        $this->createTracksInBatches();
    }

    private function createTracksInBatches(): void
    {
        $albumIds = Album::pluck('id')->toArray();
        
        foreach (array_chunk($albumIds, 50) as $albumChunk) {
            $tracks = [];
            
            foreach ($albumChunk as $albumId) {
                $trackCount = rand(8, 15);
                for ($i = 1; $i <= $trackCount; $i++) {
                    $tracks[] = Track::factory()->make([
                        'album_id' => $albumId,
                        'track_number' => $i,
                    ])->toArray();
                }
            }
            
            Track::insert($tracks);
        }
    }
}
```

## Best Practices

### Factory Guidelines

1. **Realistic Data**: Generate contextually appropriate test data
2. **Relationships**: Use factory relationships for complex data structures
3. **States**: Create meaningful states for different testing scenarios
4. **Performance**: Use batch operations for large datasets
5. **Consistency**: Maintain consistent factory patterns across models
6. **Testing**: Write tests to verify factory behavior

### Factory Maintenance

```php
<?php
// Factory validation and maintenance

class FactoryValidator
{
    /**
     * Validate all factories produce valid models
     */
    public function validateFactories(): array
    {
        $results = [];
        $factories = [
            Artist::class => ArtistFactory::class,
            Album::class => AlbumFactory::class,
            Track::class => TrackFactory::class,
        ];

        foreach ($factories as $model => $factory) {
            try {
                $instance = $model::factory()->create();
                $results[$model] = [
                    'status' => 'success',
                    'id' => $instance->id,
                ];
                $instance->delete();
            } catch (\Exception $e) {
                $results[$model] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
```

## Navigation

**← Previous:** [Soft Deletes Guide](080-soft-deletes.md)
**Next →** [Model Observers Guide](100-model-observers.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Relationship Patterns Guide](040-relationship-patterns.md) - Model relationships
- [Testing Strategies](../../testing/000-testing-index.md) - Comprehensive testing approaches

---

*This guide provides comprehensive model factory patterns for Laravel 12 in the Chinook application. Each pattern includes realistic data generation, relationship management, and performance optimization strategies for effective testing and development.*
