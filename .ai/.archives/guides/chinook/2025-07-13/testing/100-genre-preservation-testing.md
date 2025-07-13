# Direct Taxonomy Mapping Testing Guide

## Table of Contents

- [Overview](#overview)
- [Direct Taxonomy Mapping Testing](#direct-taxonomy-mapping-testing)
- [Taxonomy Integration Testing](#taxonomy-integration-testing)
- [Data Integrity Testing](#data-integrity-testing)
- [Performance Testing](#performance-testing)
- [Data Integrity Testing](#data-integrity-testing)

## Overview

This guide provides comprehensive testing strategies for the **direct taxonomy mapping approach** in the Chinook database implementation. The direct mapping strategy uses the `aliziodev/laravel-taxonomy` package as the single categorization system with exact mapping from original chinook.sql genre data.

**Testing Framework**: All examples use Pest PHP with describe/it blocks following modern Laravel 12 patterns.

**Key Testing Areas**:

- Direct genre-to-taxonomy mapping without enhancement
- Single taxonomy system implementation validation
- Data integrity verification with original chinook.sql format
- Performance optimization assessment
- Comprehensive test coverage with Pest framework

## Direct Taxonomy Mapping Testing

### Core Direct Mapping Tests

```php
<?php

use App\Models\ChinookTrack;
use App\Models\ChinookAlbum;
use App\Models\ChinookArtist;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Direct Taxonomy Mapping Strategy', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seedDirectTaxonomyMapping();
    });

    describe('Direct Genre Mapping Validation', function () {
        it('creates exactly 25 genre taxonomies from chinook.sql data', function () {
            // Verify all 25 original genres mapped to taxonomies
            expect(Taxonomy::where('type', 'genre')->count())->toBe(25);

            // Verify specific genres from chinook.sql exist as taxonomies
            expect(Taxonomy::where('type', 'genre')->where('name', 'Rock')->exists())->toBeTrue();
            expect(Taxonomy::where('type', 'genre')->where('name', 'Jazz')->exists())->toBeTrue();
            expect(Taxonomy::where('type', 'genre')->where('name', 'Metal')->exists())->toBeTrue();
            expect(Taxonomy::where('type', 'genre')->where('name', 'Alternative & Punk')->exists())->toBeTrue();
        });

        it('maintains original genre IDs in taxonomy metadata', function () {
            $rockTaxonomy = Taxonomy::where('type', 'genre')
                ->where('name', 'Rock')
                ->first();

            // Verify original ID preserved in metadata
            expect($rockTaxonomy->meta['original_id'])->toBe(1);
            expect($rockTaxonomy->meta['source'])->toBe('direct_mapping');
            expect($rockTaxonomy->meta['chinook_sql_origin'])->toBeTrue();
        });

        it('validates direct mapping completeness', function () {
            // All 25 original genres should be mapped
            $expectedGenres = [
                1 => 'Rock', 2 => 'Jazz', 3 => 'Metal', 4 => 'Alternative & Punk',
                5 => 'Rock And Roll', 6 => 'Blues', 7 => 'Latin', 8 => 'Reggae',
                9 => 'Pop', 10 => 'Soundtrack', 11 => 'Bossa Nova', 12 => 'Easy Listening',
                13 => 'Heavy Metal', 14 => 'R&B/Soul', 15 => 'Electronica/Dance',
                16 => 'World', 17 => 'Hip Hop/Rap', 18 => 'Science Fiction',
                19 => 'TV Shows', 20 => 'Sci Fi & Fantasy', 21 => 'Drama',
                22 => 'Comedy', 23 => 'Alternative', 24 => 'Classical', 25 => 'Opera'
            ];

            foreach ($expectedGenres as $originalId => $name) {
                $taxonomy = Taxonomy::where('type', 'genre')
                    ->where('name', $name)
                    ->first();

                expect($taxonomy)->not->toBeNull();
                expect($taxonomy->meta['original_id'])->toBe($originalId);
            }
        });
    });

    describe('Taxonomy Integration with Models', function () {
        it('attaches taxonomies to tracks directly', function () {
            // Get rock taxonomy from direct mapping
            $rockTaxonomy = Taxonomy::where('type', 'genre')
                ->where('name', 'Rock')
                ->first();

            // Create track and attach taxonomy
            $track = ChinookTrack::factory()->create();
            $track->attachTaxonomy($rockTaxonomy->id);

            // Verify direct attachment
            expect($track->taxonomies()->count())->toBe(1);
            expect($track->taxonomies()->first()->id)->toBe($rockTaxonomy->id);
        });

        it('supports multiple taxonomy types on same model', function () {
            // Get taxonomies of different types
            $rockTaxonomy = Taxonomy::where('type', 'genre')
                ->where('name', 'Rock')
                ->first();

            $moodTaxonomy = Taxonomy::factory()->create([
                'type' => 'mood',
                'name' => 'Energetic'
            ]);

            // Attach both to same track
            $track = ChinookTrack::factory()->create();
            $track->attachTaxonomy($rockTaxonomy->id);
            $track->attachTaxonomy($moodTaxonomy->id);

            // Verify both types attached
            expect($track->taxonomies()->count())->toBe(2);
            expect($track->taxonomiesByType('genre')->first()->name)->toBe('Rock');
            expect($track->taxonomiesByType('mood')->first()->name)->toBe('Energetic');
        });

        it('preserves genre metadata in taxonomies', function () {
            $this->artisan('chinook:import-genres-to-taxonomy');

            $genre = Genre::first();
            $taxonomy = Taxonomy::where('type', 'genre')
                               ->where('meta->chinook_genre_id', $genre->id)
                               ->first();

            expect($taxonomy->meta)->toHaveKey('chinook_genre_id');
            expect($taxonomy->meta)->toHaveKey('original_name');
            expect($taxonomy->meta['chinook_genre_id'])->toBe($genre->id);
            expect($taxonomy->meta['original_name'])->toBe($genre->name);
        });
    });

    private function seedOriginalGenres(): void
    {
        // Seed the original 25 genres from chinook.sql
        $originalGenres = [
            'Rock', 'Jazz', 'Metal', 'Alternative & Punk', 'Rock And Roll',
            'Blues', 'Latin', 'Reggae', 'Pop', 'Soundtrack',
            'Bossa Nova', 'Easy Listening', 'Heavy Metal', 'R&B/Soul',
            'Electronica/Dance', 'World', 'Hip Hop/Rap', 'Science Fiction',
            'TV Shows', 'Sci Fi & Fantasy', 'Drama', 'Comedy',
            'Alternative', 'Classical', 'Opera'
        ];

        foreach ($originalGenres as $index => $genreName) {
            Genre::create([
                'id' => $index + 1,
                'name' => $genreName
            ]);
        }
    }
});
```

## Backward Compatibility Testing

### Legacy Code Compatibility Tests

```php
describe('Backward Compatibility', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seedOriginalGenres();
    });

    describe('Existing Track-Genre Relationships', function () {
        it('maintains existing track-genre foreign key relationships', function () {
            $genre = Genre::first();
            $track = Track::factory()->create(['genre_id' => $genre->id]);
            
            // Verify relationship works as before
            expect($track->genre)->not->toBeNull();
            expect($track->genre->id)->toBe($genre->id);
            expect($track->genre_id)->toBe($genre->id);
        });

        it('supports legacy genre queries', function () {
            $rockGenre = Genre::where('name', 'Rock')->first();
            Track::factory()->count(5)->create(['genre_id' => $rockGenre->id]);
            
            // Legacy query patterns should still work
            $rockTracks = Track::where('genre_id', $rockGenre->id)->get();
            expect($rockTracks)->toHaveCount(5);
            
            $tracksWithGenre = Track::with('genre')->get();
            expect($tracksWithGenre->first()->genre)->not->toBeNull();
        });

        it('preserves genre-based filtering and sorting', function () {
            $genres = Genre::take(3)->get();
            foreach ($genres as $genre) {
                Track::factory()->count(2)->create(['genre_id' => $genre->id]);
            }
            
            // Test genre-based filtering
            $firstGenreTracks = Track::whereHas('genre', function ($query) use ($genres) {
                $query->where('name', $genres->first()->name);
            })->get();
            
            expect($firstGenreTracks)->toHaveCount(2);
            
            // Test genre-based sorting
            $sortedTracks = Track::join('genres', 'tracks.genre_id', '=', 'genres.id')
                                ->orderBy('genres.name')
                                ->select('tracks.*')
                                ->get();
            
            expect($sortedTracks)->toHaveCount(6);
        });
    });

    describe('API Compatibility', function () {
        it('maintains genre API endpoints', function () {
            $response = $this->getJson('/api/genres');
            
            $response->assertStatus(200)
                    ->assertJsonStructure([
                        'data' => [
                            '*' => ['id', 'name']
                        ]
                    ]);
        });

        it('supports legacy genre-based track filtering', function () {
            $genre = Genre::first();
            Track::factory()->count(3)->create(['genre_id' => $genre->id]);
            
            $response = $this->getJson("/api/tracks?genre_id={$genre->id}");
            
            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(3);
        });
    });
});
```

## Migration Testing

### Genre-to-Taxonomy Import Tests

```php
describe('Genre Import Testing', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seedOriginalGenres();
        $this->createTestTracks();
    });

    describe('Import Process Validation', function () {
        it('imports genres to taxonomy system without data loss', function () {
            $originalGenreCount = Genre::count();
            $originalTrackCount = ChinookTrack::count();

            // Run import
            $this->artisan('chinook:import-genres-to-taxonomy');

            // Verify no data loss
            expect(Genre::count())->toBe($originalGenreCount);
            expect(ChinookTrack::count())->toBe($originalTrackCount);
            expect(Taxonomy::where('type', 'genre')->count())->toBe($originalGenreCount);
        });

        it('creates taxonomy relationships during import', function () {
            ChinookTrack::factory()->count(5)->create();

            $this->artisan('chinook:import-genres-to-taxonomy');

            // Verify taxonomy relationships can be created
            $track = ChinookTrack::first();
            $genreTaxonomy = Taxonomy::where('type', 'genre')->first();

            $track->attachTaxonomy($genreTaxonomy->id, ['source' => 'chinook_import']);

            expect($track->taxonomies)->toHaveCount(1);
            expect($track->taxonomies->first()->type)->toBe('genre');
        });

        it('validates import rollback capability', function () {
            $this->artisan('chinook:import-genres-to-taxonomy');

            // Verify import completed
            expect(Taxonomy::where('type', 'genre')->count())->toBeGreaterThan(0);

            // Test rollback
            $this->artisan('chinook:rollback-genre-import');

            // Verify rollback (taxonomies removed, genres preserved)
            expect(Taxonomy::where('type', 'genre')->count())->toBe(0);
            expect(Genre::count())->toBeGreaterThan(0);
        });
    });

    private function createTestTracks(): void
    {
        $genres = Genre::take(5)->get();
        foreach ($genres as $genre) {
            ChinookTrack::factory()->count(3)->create();
        }
    }
});
```

## Taxonomy Integration Testing

### Single Taxonomy System Tests

```php
<?php

use App\Models\ChinookTrack;
use App\Models\ChinookGenre;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Single Taxonomy System Integration', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seedOriginalGenres();
        $this->artisan('chinook:import-genres-to-taxonomy');
    });

    describe('Track Taxonomy Relationships', function () {
        it('supports taxonomy relationships for tracks', function () {
            $track = ChinookTrack::factory()->create();

            // Create genre taxonomy
            $genreTaxonomy = Taxonomy::create([
                'name' => 'Rock',
                'type' => 'genre',
                'slug' => 'rock'
            ]);

            // Attach taxonomy with metadata
            $track->attachTaxonomy($genreTaxonomy->id, [
                'is_primary' => true,
                'source' => 'chinook_import'
            ]);

            // Verify taxonomy relationship
            expect($track->taxonomies)->toHaveCount(1);
            expect($track->taxonomies->first()->name)->toBe('Rock');
            expect($track->taxonomies->first()->type)->toBe('genre');
        });

        it('supports multiple taxonomy types', function () {
            $track = ChinookTrack::factory()->create();

            $genreTaxonomy = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
            $moodTaxonomy = Taxonomy::create(['name' => 'Energetic', 'type' => 'mood']);
            $themeTaxonomy = Taxonomy::create(['name' => 'Adventure', 'type' => 'theme']);

            // Attach multiple taxonomies
            $track->attachTaxonomy($genreTaxonomy->id, ['is_primary' => true]);
            $track->attachTaxonomy($moodTaxonomy->id);
            $track->attachTaxonomy($themeTaxonomy->id);

            // Verify all taxonomies attached
            expect($track->taxonomies)->toHaveCount(3);

            // Test type-specific queries
            $genreTaxonomies = $track->taxonomies()->where('type', 'genre')->get();
            $moodTaxonomies = $track->taxonomies()->where('type', 'mood')->get();
            $themeTaxonomies = $track->taxonomies()->where('type', 'theme')->get();

            expect($genreTaxonomies)->toHaveCount(1);
            expect($moodTaxonomies)->toHaveCount(1);
            expect($themeTaxonomies)->toHaveCount(1);
        });

        it('supports primary genre identification', function () {
            $track = ChinookTrack::factory()->create();

            $rockTaxonomy = Taxonomy::create(['name' => 'Rock', 'type' => 'genre']);
            $jazzTaxonomy = Taxonomy::create(['name' => 'Jazz', 'type' => 'genre']);

            // Attach multiple genres with primary designation
            $track->attachTaxonomy($rockTaxonomy->id, ['is_primary' => true]);
            $track->attachTaxonomy($jazzTaxonomy->id, ['is_primary' => false]);

            // Test primary genre method
            $primaryGenre = $track->primaryGenre();

            expect($primaryGenre)->not->toBeNull();
            expect($primaryGenre->name)->toBe('Rock');
        });
    });

    describe('Taxonomy Hierarchy Support', function () {
        it('creates hierarchical genre taxonomies', function () {
            $musicRoot = Taxonomy::create([
                'name' => 'Music',
                'type' => 'genre',
                'slug' => 'music'
            ]);

            $rockTaxonomy = Taxonomy::create([
                'name' => 'Rock',
                'type' => 'genre',
                'slug' => 'rock',
                'parent_id' => $musicRoot->id
            ]);

            $hardRockTaxonomy = Taxonomy::create([
                'name' => 'Hard Rock',
                'type' => 'genre',
                'slug' => 'hard-rock',
                'parent_id' => $rockTaxonomy->id
            ]);

            // Verify hierarchy
            expect($rockTaxonomy->parent_id)->toBe($musicRoot->id);
            expect($hardRockTaxonomy->parent_id)->toBe($rockTaxonomy->id);

            // Test hierarchical queries
            $rockChildren = $rockTaxonomy->children()->get();
            expect($rockChildren)->toHaveCount(1);
            expect($rockChildren->first()->name)->toBe('Hard Rock');
        });

        it('supports taxonomy tree traversal', function () {
            // Create taxonomy tree
            $music = Taxonomy::create(['name' => 'Music', 'type' => 'genre']);
            $rock = Taxonomy::create(['name' => 'Rock', 'type' => 'genre', 'parent_id' => $music->id]);
            $hardRock = Taxonomy::create(['name' => 'Hard Rock', 'type' => 'genre', 'parent_id' => $rock->id]);
            $metal = Taxonomy::create(['name' => 'Metal', 'type' => 'genre', 'parent_id' => $rock->id]);

            // Test tree traversal methods
            $musicDescendants = $music->descendants()->get();
            $rockAncestors = $hardRock->ancestors()->get();

            expect($musicDescendants)->toHaveCount(3); // Rock, Hard Rock, Metal
            expect($rockAncestors)->toHaveCount(2); // Music, Rock
        });
    });
});
```

## Performance Testing

### Genre Preservation Performance Tests

```php
describe('Performance Impact Testing', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seedOriginalGenres();
        $this->createLargeDataset();
    });

    describe('Query Performance Comparison', function () {
        it('measures genre-only query performance', function () {
            $startTime = microtime(true);

            $rockTracks = Track::whereHas('genre', function ($query) {
                $query->where('name', 'Rock');
            })->get();

            $genreQueryTime = (microtime(true) - $startTime) * 1000;

            expect($genreQueryTime)->toBeLessThan(100); // Under 100ms
            expect($rockTracks->count())->toBeGreaterThan(0);
        });

        it('measures taxonomy system query performance', function () {
            $this->artisan('chinook:import-genres-to-taxonomy');

            $startTime = microtime(true);

            $rockTracks = ChinookTrack::whereHas('taxonomies', function ($query) {
                $query->where('type', 'genre')
                      ->where('name', 'Rock');
            })->get();

            $taxonomyQueryTime = (microtime(true) - $startTime) * 1000;

            expect($taxonomyQueryTime)->toBeLessThan(120); // Optimized single system
            expect($rockTracks->count())->toBeGreaterThan(0);
        });

        it('compares memory usage between approaches', function () {
            $memoryBefore = memory_get_usage();

            // Load tracks with genres
            $tracksWithGenres = Track::with('genre')->take(1000)->get();

            $genreMemoryUsage = memory_get_usage() - $memoryBefore;

            // Reset and test with categories
            unset($tracksWithGenres);
            gc_collect_cycles();

            $this->artisan('chinook:migrate-genres-to-categories');

            $memoryBefore = memory_get_usage();

            $tracksWithCategories = Track::with(['categories' => function ($query) {
                $query->where('type', CategoryType::GENRE);
            }])->take(1000)->get();

            $categoryMemoryUsage = memory_get_usage() - $memoryBefore;

            // Category approach should not use significantly more memory
            expect($categoryMemoryUsage)->toBeLessThan($genreMemoryUsage * 1.5);
        });
    });

    private function createLargeDataset(): void
    {
        $genres = Genre::all();

        foreach ($genres as $genre) {
            Track::factory()->count(100)->create(['genre_id' => $genre->id]);
        }
    }
});
```

## Data Integrity Testing

### Comprehensive Data Integrity Tests

```php
describe('Data Integrity Validation', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seedOriginalGenres();
    });

    describe('Referential Integrity', function () {
        it('maintains foreign key constraints', function () {
            $genre = Genre::first();
            $track = Track::factory()->create(['genre_id' => $genre->id]);

            // Attempt to delete genre with tracks should fail
            expect(fn() => $genre->delete())
                ->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('validates category-genre mapping integrity', function () {
            $this->artisan('chinook:migrate-genres-to-categories');

            $genre = Genre::first();
            $category = Category::where('type', CategoryType::GENRE)
                               ->where('metadata->genre_id', $genre->id)
                               ->first();

            expect($category)->not->toBeNull();
            expect($category->metadata['genre_id'])->toBe($genre->id);
        });

        it('prevents orphaned category relationships', function () {
            $this->artisan('chinook:migrate-genres-to-categories');

            $track = Track::factory()->create(['genre_id' => Genre::first()->id]);
            $category = $track->categories()->where('type', CategoryType::GENRE)->first();

            // Delete category should clean up relationships
            $category->delete();

            expect($track->fresh()->categories()->where('type', CategoryType::GENRE)->count())->toBe(0);
        });
    });

    describe('Data Consistency Validation', function () {
        it('validates genre-category name consistency', function () {
            $this->artisan('chinook:migrate-genres-to-categories');

            $genres = Genre::all();

            foreach ($genres as $genre) {
                $category = Category::where('type', CategoryType::GENRE)
                                  ->where('metadata->genre_id', $genre->id)
                                  ->first();

                expect($category->name)->toBe($genre->name);
            }
        });

        it('validates unique constraints', function () {
            // Test genre name uniqueness
            expect(fn() => Genre::create(['name' => Genre::first()->name]))
                ->toThrow(\Illuminate\Database\QueryException::class);

            // Test category name uniqueness within type
            $this->artisan('chinook:migrate-genres-to-categories');

            $existingCategory = Category::where('type', CategoryType::GENRE)->first();

            expect(fn() => Category::create([
                'type' => CategoryType::GENRE,
                'name' => $existingCategory->name
            ]))->toThrow(\Illuminate\Database\QueryException::class);
        });
    });
});
```
