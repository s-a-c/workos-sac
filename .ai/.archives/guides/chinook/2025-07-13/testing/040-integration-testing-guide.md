# Integration Testing Guide

## Table of Contents

- [Overview](#overview)
- [Database Relationship Testing](#database-relationship-testing)
- [Hierarchical Data Testing](#hierarchical-data-testing)
- [External Service Integration](#external-service-integration)
- [End-to-End Workflow Testing](#end-to-end-workflow-testing)
- [Performance Testing](#performance-testing)
- [Test Organization](#test-organization)
- [Best Practices](#best-practices)

## Overview

Integration testing validates the interaction between different components of the Chinook application, ensuring that complex workflows, database relationships, and external integrations work correctly together. This guide covers comprehensive integration testing strategies using Pest PHP framework with Laravel 12 modern patterns.

### Integration Testing Principles

- **System Interactions**: Test how different components work together
- **Real Scenarios**: Use realistic data and workflows
- **Performance Validation**: Ensure acceptable performance under load
- **Data Integrity**: Verify data consistency across operations

## Database Relationship Testing

### Polymorphic Relationship Testing

```php
<?php

// tests/Integration/Database/PolymorphicRelationshipTest.php
use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Category;
use App\Enums\CategoryType;

describe('Polymorphic Category Relationships', function () {
    beforeEach(function () {
        $this->genreCategory = Category::factory()->create(['type' => CategoryType::GENRE]);
        $this->moodCategory = Category::factory()->create(['type' => CategoryType::MOOD]);
        $this->themeCategory = Category::factory()->create(['type' => CategoryType::THEME]);
    });

    describe('Artist Categorization', function () {
        it('attaches multiple category types to artist', function () {
            $artist = Artist::factory()->create();
            
            $artist->categories()->attach([
                $this->genreCategory->id => ['is_primary' => true, 'sort_order' => 1],
                $this->moodCategory->id => ['is_primary' => false, 'sort_order' => 2]
            ]);

            expect($artist->categories)->toHaveCount(2);
            expect($artist->categoriesByType(CategoryType::GENRE))->toHaveCount(1);
            expect($artist->getPrimaryCategory(CategoryType::GENRE)->id)->toBe($this->genreCategory->id);
        });

        it('maintains category relationships across model updates', function () {
            $artist = Artist::factory()->create();
            $artist->categories()->attach($this->genreCategory->id);

            $artist->update(['name' => 'Updated Artist Name']);

            expect($artist->fresh()->categories)->toHaveCount(1);
            expect($artist->categories->first()->id)->toBe($this->genreCategory->id);
        });
    });

    describe('Cross-Model Category Queries', function () {
        it('finds all models with specific category', function () {
            $artist = Artist::factory()->create();
            $album = Album::factory()->create();
            $track = Track::factory()->create();

            $artist->categories()->attach($this->genreCategory->id);
            $album->categories()->attach($this->genreCategory->id);
            $track->categories()->attach($this->genreCategory->id);

            // Test polymorphic reverse relationship
            $categorizedModels = $this->genreCategory->categorizable;
            
            expect($categorizedModels)->toHaveCount(3);
        });

        it('performs complex category-based queries', function () {
            $rockArtist = Artist::factory()->create();
            $jazzArtist = Artist::factory()->create();
            
            $rockCategory = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Rock']);
            $jazzCategory = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Jazz']);

            $rockArtist->categories()->attach($rockCategory->id);
            $jazzArtist->categories()->attach($jazzCategory->id);

            // Find artists with specific genre
            $rockArtists = Artist::whereHas('categories', function ($query) use ($rockCategory) {
                $query->where('categories.id', $rockCategory->id);
            })->get();

            expect($rockArtists)->toHaveCount(1);
            expect($rockArtists->first()->id)->toBe($rockArtist->id);
        });
    });

    describe('Category Type Validation', function () {
        it('validates category types for specific models', function () {
            $artist = Artist::factory()->create();
            $occasionCategory = Category::factory()->create(['type' => CategoryType::OCCASION]);

            // Artists should not accept OCCASION categories
            $validTypes = CategoryType::forModel(Artist::class);
            
            expect($validTypes)->not->toContain(CategoryType::OCCASION);
            expect($validTypes)->toContain(CategoryType::GENRE);
        });
    });
});
```

### Complex Relationship Testing

```php
<?php

// tests/Integration/Database/ComplexRelationshipTest.php
use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;

describe('Complex Database Relationships', function () {
    describe('Music Hierarchy Relationships', function () {
        it('maintains referential integrity across music hierarchy', function () {
            $artist = Artist::factory()->create();
            $album = Album::factory()->create(['artist_id' => $artist->id]);
            $tracks = Track::factory()->count(10)->create(['album_id' => $album->id]);

            // Test cascade relationships
            expect($artist->albums)->toHaveCount(1);
            expect($album->tracks)->toHaveCount(10);
            expect($tracks->first()->album->artist->id)->toBe($artist->id);
        });

        it('prevents orphaned records with foreign key constraints', function () {
            $artist = Artist::factory()->create();
            $album = Album::factory()->create(['artist_id' => $artist->id]);

            // Should not be able to delete artist with albums
            expect(fn() => $artist->forceDelete())
                ->toThrow(\Illuminate\Database\QueryException::class);
        });

        it('handles soft deletes properly', function () {
            $artist = Artist::factory()->create();
            $album = Album::factory()->create(['artist_id' => $artist->id]);

            $artist->delete(); // Soft delete

            expect($artist->trashed())->toBeTrue();
            expect($album->fresh()->artist)->toBeNull(); // Should handle soft deleted parent
        });
    });

    describe('Sales Relationship Chain', function () {
        it('maintains complete sales chain integrity', function () {
            $customer = Customer::factory()->create();
            $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);
            $track = Track::factory()->create();
            $invoiceLine = InvoiceLine::factory()->create([
                'invoice_id' => $invoice->id,
                'track_id' => $track->id,
                'quantity' => 2,
                'unit_price' => 0.99
            ]);

            // Test complete relationship chain
            expect($customer->invoices)->toHaveCount(1);
            expect($invoice->invoiceLines)->toHaveCount(1);
            expect($invoiceLine->track->id)->toBe($track->id);
            expect($invoiceLine->invoice->customer->id)->toBe($customer->id);
        });

        it('calculates totals correctly across relationships', function () {
            $customer = Customer::factory()->create();
            $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);
            
            InvoiceLine::factory()->create([
                'invoice_id' => $invoice->id,
                'quantity' => 2,
                'unit_price' => 0.99
            ]);
            
            InvoiceLine::factory()->create([
                'invoice_id' => $invoice->id,
                'quantity' => 1,
                'unit_price' => 1.29
            ]);

            $calculatedTotal = $invoice->calculateTotal();
            expect($calculatedTotal)->toBe(3.27); // (2 * 0.99) + (1 * 1.29)
        });
    });
});
```

## Hierarchical Data Testing

### Closure Table Testing

```php
<?php

// tests/Integration/Database/ClosureTableTest.php
use App\Models\Category;
use App\Enums\CategoryType;

describe('Closure Table Hierarchical Data', function () {
    describe('Hierarchy Creation', function () {
        it('creates proper closure table entries for hierarchy', function () {
            $root = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Music']);
            $rock = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Rock']);
            $hardRock = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Hard Rock']);

            // Build hierarchy: Music > Rock > Hard Rock
            $rock->makeChildOf($root);
            $hardRock->makeChildOf($rock);

            // Verify closure table entries
            expect($root->descendants)->toHaveCount(2); // Rock and Hard Rock
            expect($rock->ancestors)->toHaveCount(1); // Music
            expect($hardRock->ancestors)->toHaveCount(2); // Music and Rock
        });

        it('maintains depth information correctly', function () {
            $root = Category::factory()->create(['type' => CategoryType::GENRE]);
            $level1 = Category::factory()->create(['type' => CategoryType::GENRE]);
            $level2 = Category::factory()->create(['type' => CategoryType::GENRE]);

            $level1->makeChildOf($root);
            $level2->makeChildOf($level1);

            $rootAncestors = $root->ancestors()->get();
            $level1Ancestors = $level1->ancestors()->get();
            $level2Ancestors = $level2->ancestors()->get();

            expect($rootAncestors)->toHaveCount(0);
            expect($level1Ancestors)->toHaveCount(1);
            expect($level2Ancestors)->toHaveCount(2);

            // Check depth values
            expect($level1Ancestors->where('id', $root->id)->first()->pivot->depth)->toBe(1);
            expect($level2Ancestors->where('id', $root->id)->first()->pivot->depth)->toBe(2);
            expect($level2Ancestors->where('id', $level1->id)->first()->pivot->depth)->toBe(1);
        });
    });

    describe('Hierarchy Queries', function () {
        beforeEach(function () {
            // Create test hierarchy
            $this->music = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Music']);
            $this->rock = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Rock']);
            $this->metal = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Metal']);
            $this->hardRock = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Hard Rock']);
            $this->heavyMetal = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Heavy Metal']);

            $this->rock->makeChildOf($this->music);
            $this->metal->makeChildOf($this->music);
            $this->hardRock->makeChildOf($this->rock);
            $this->heavyMetal->makeChildOf($this->metal);
        });

        it('retrieves all descendants efficiently', function () {
            $descendants = $this->music->descendants()->get();

            expect($descendants)->toHaveCount(4);
            expect($descendants->pluck('name')->toArray())
                ->toContain('Rock', 'Metal', 'Hard Rock', 'Heavy Metal');
        });

        it('retrieves descendants at specific depth', function () {
            $directChildren = $this->music->descendants()->wherePivot('depth', 1)->get();
            $grandChildren = $this->music->descendants()->wherePivot('depth', 2)->get();

            expect($directChildren)->toHaveCount(2); // Rock, Metal
            expect($grandChildren)->toHaveCount(2); // Hard Rock, Heavy Metal
        });

        it('finds common ancestors', function () {
            $commonAncestors = $this->hardRock->ancestors()
                ->whereIn('categories.id', $this->heavyMetal->ancestors()->pluck('id'))
                ->get();

            expect($commonAncestors)->toHaveCount(1);
            expect($commonAncestors->first()->name)->toBe('Music');
        });

        it('calculates subtree sizes', function () {
            $musicSubtreeSize = $this->music->descendants()->count() + 1; // +1 for self
            $rockSubtreeSize = $this->rock->descendants()->count() + 1;

            expect($musicSubtreeSize)->toBe(5); // Music + 4 descendants
            expect($rockSubtreeSize)->toBe(2); // Rock + Hard Rock
        });
    });

    describe('Hierarchy Modifications', function () {
        it('moves subtrees correctly', function () {
            $music = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Music']);
            $rock = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Rock']);
            $alternative = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Alternative']);
            $grunge = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Grunge']);

            // Initial hierarchy: Music > Rock, Alternative > Grunge
            $rock->makeChildOf($music);
            $grunge->makeChildOf($alternative);

            // Move Grunge under Rock
            $grunge->makeChildOf($rock);

            // Verify new structure
            expect($grunge->ancestors)->toHaveCount(2); // Rock, Music
            expect($alternative->descendants)->toHaveCount(0);
            expect($rock->descendants)->toHaveCount(1); // Grunge
        });

        it('prevents circular references', function () {
            $parent = Category::factory()->create(['type' => CategoryType::GENRE]);
            $child = Category::factory()->create(['type' => CategoryType::GENRE]);

            $child->makeChildOf($parent);

            // Attempt to make parent a child of child (circular reference)
            $result = $parent->makeChildOf($child);

            expect($result)->toBeFalse();
            expect($parent->ancestors)->toHaveCount(0);
        });

        it('handles deletion of nodes with children', function () {
            $root = Category::factory()->create(['type' => CategoryType::GENRE]);
            $middle = Category::factory()->create(['type' => CategoryType::GENRE]);
            $leaf = Category::factory()->create(['type' => CategoryType::GENRE]);

            $middle->makeChildOf($root);
            $leaf->makeChildOf($middle);

            // Delete middle node - leaf should become direct child of root
            $middle->delete();

            expect($leaf->fresh()->ancestors)->toHaveCount(1);
            expect($leaf->ancestors->first()->id)->toBe($root->id);
        });
    });

    describe('Performance Testing', function () {
        it('performs hierarchy queries efficiently with large datasets', function () {
            // Create a large hierarchy
            $root = Category::factory()->create(['type' => CategoryType::GENRE]);
            $children = Category::factory()->count(100)->create(['type' => CategoryType::GENRE]);

            foreach ($children as $child) {
                $child->makeChildOf($root);
            }

            $startTime = microtime(true);
            $descendants = $root->descendants()->get();
            $queryTime = (microtime(true) - $startTime) * 1000;

            expect($descendants)->toHaveCount(100);
            expect($queryTime)->toBeLessThan(100); // Should complete in under 100ms
        });
    });
});
```

## External Service Integration

### Music Metadata Service Testing

```php
<?php

// tests/Integration/Services/MusicMetadataServiceTest.php
use App\Services\MusicMetadataService;
use App\Models\Track;
use App\Models\Album;
use Illuminate\Support\Facades\Http;

describe('Music Metadata Service Integration', function () {
    beforeEach(function () {
        $this->service = app(MusicMetadataService::class);
    });

    describe('External API Integration', function () {
        it('fetches track metadata from external service', function () {
            Http::fake([
                'musicbrainz.org/*' => Http::response([
                    'recordings' => [
                        [
                            'title' => 'Come Together',
                            'artist-credit' => [['artist' => ['name' => 'The Beatles']]],
                            'length' => 259000,
                            'releases' => [
                                ['title' => 'Abbey Road', 'date' => '1969-09-26']
                            ]
                        ]
                    ]
                ], 200)
            ]);

            $metadata = $this->service->fetchTrackMetadata('Come Together', 'The Beatles');

            expect($metadata)
                ->toHaveKey('title')
                ->toHaveKey('artist')
                ->toHaveKey('duration')
                ->toHaveKey('album');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'musicbrainz.org');
            });
        });

        it('handles API failures gracefully', function () {
            Http::fake([
                'musicbrainz.org/*' => Http::response([], 500)
            ]);

            $metadata = $this->service->fetchTrackMetadata('Unknown Track', 'Unknown Artist');

            expect($metadata)->toBeNull();
        });

        it('caches API responses', function () {
            Http::fake([
                'musicbrainz.org/*' => Http::response(['recordings' => []], 200)
            ]);

            // First call
            $this->service->fetchTrackMetadata('Test Track', 'Test Artist');

            // Second call should use cache
            $this->service->fetchTrackMetadata('Test Track', 'Test Artist');

            Http::assertSentCount(1); // Only one actual API call
        });
    });

    describe('Data Enrichment', function () {
        it('enriches existing tracks with metadata', function () {
            $track = Track::factory()->create([
                'name' => 'Come Together',
                'milliseconds' => null
            ]);

            Http::fake([
                'musicbrainz.org/*' => Http::response([
                    'recordings' => [
                        [
                            'title' => 'Come Together',
                            'length' => 259000,
                            'artist-credit' => [['artist' => ['name' => 'The Beatles']]]
                        ]
                    ]
                ], 200)
            ]);

            $enriched = $this->service->enrichTrack($track);

            expect($enriched->milliseconds)->toBe(259000);
        });

        it('handles batch enrichment efficiently', function () {
            $tracks = Track::factory()->count(10)->create();

            Http::fake([
                'musicbrainz.org/*' => Http::response(['recordings' => []], 200)
            ]);

            $startTime = microtime(true);
            $this->service->enrichTracks($tracks);
            $executionTime = (microtime(true) - $startTime) * 1000;

            expect($executionTime)->toBeLessThan(5000); // Should complete in under 5 seconds
        });
    });
});
```

### File Storage Integration Testing

```php
<?php

// tests/Integration/Services/FileStorageServiceTest.php
use App\Services\FileStorageService;
use App\Models\Artist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

describe('File Storage Service Integration', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->service = app(FileStorageService::class);
    });

    describe('Image Upload and Processing', function () {
        it('uploads and processes artist images', function () {
            $artist = Artist::factory()->create();
            $image = UploadedFile::fake()->image('artist.jpg', 800, 600);

            $result = $this->service->uploadArtistImage($artist, $image);

            expect($result)
                ->toHaveKey('original_path')
                ->toHaveKey('thumbnail_path')
                ->toHaveKey('medium_path');

            Storage::disk('public')->assertExists($result['original_path']);
            Storage::disk('public')->assertExists($result['thumbnail_path']);
            Storage::disk('public')->assertExists($result['medium_path']);
        });

        it('validates image dimensions and file types', function () {
            $artist = Artist::factory()->create();
            $invalidFile = UploadedFile::fake()->create('document.pdf', 1000);

            expect(fn() => $this->service->uploadArtistImage($artist, $invalidFile))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('generates multiple image sizes', function () {
            $artist = Artist::factory()->create();
            $image = UploadedFile::fake()->image('artist.jpg', 1200, 800);

            $result = $this->service->uploadArtistImage($artist, $image);

            // Verify different sizes were created
            $originalSize = Storage::disk('public')->size($result['original_path']);
            $thumbnailSize = Storage::disk('public')->size($result['thumbnail_path']);
            $mediumSize = Storage::disk('public')->size($result['medium_path']);

            expect($thumbnailSize)->toBeLessThan($mediumSize);
            expect($mediumSize)->toBeLessThan($originalSize);
        });
    });

    describe('Audio File Processing', function () {
        it('processes audio files and extracts metadata', function () {
            $track = Track::factory()->create();
            $audioFile = UploadedFile::fake()->create('track.mp3', 5000, 'audio/mpeg');

            $result = $this->service->processAudioFile($track, $audioFile);

            expect($result)
                ->toHaveKey('file_path')
                ->toHaveKey('duration')
                ->toHaveKey('bitrate')
                ->toHaveKey('file_size');

            Storage::disk('public')->assertExists($result['file_path']);
        });
    });
});
```

## End-to-End Workflow Testing

### Complete Music Purchase Workflow

```php
<?php

// tests/Integration/Workflows/MusicPurchaseWorkflowTest.php
use App\Models\Customer;
use App\Models\Track;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\PurchaseService;

describe('Music Purchase Workflow', function () {
    beforeEach(function () {
        $this->customer = Customer::factory()->create();
        $this->tracks = Track::factory()->count(3)->create(['unit_price' => 0.99]);
        $this->purchaseService = app(PurchaseService::class);
    });

    it('completes full purchase workflow', function () {
        // Step 1: Add tracks to cart
        $cart = $this->purchaseService->createCart($this->customer);
        foreach ($this->tracks as $track) {
            $this->purchaseService->addToCart($cart, $track, 1);
        }

        expect($cart->items)->toHaveCount(3);

        // Step 2: Calculate totals
        $total = $this->purchaseService->calculateTotal($cart);
        expect($total)->toBe(2.97); // 3 * 0.99

        // Step 3: Process payment
        $invoice = $this->purchaseService->processPayment($cart, [
            'payment_method' => 'credit_card',
            'billing_address' => '123 Main St',
            'billing_city' => 'Anytown',
            'billing_country' => 'USA'
        ]);

        expect($invoice)->toBeInstanceOf(Invoice::class);
        expect($invoice->total)->toBe(2.97);
        expect($invoice->invoiceLines)->toHaveCount(3);

        // Step 4: Verify database state
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'total' => 2.97
        ]);

        foreach ($this->tracks as $track) {
            $this->assertDatabaseHas('invoice_lines', [
                'invoice_id' => $invoice->id,
                'track_id' => $track->id,
                'quantity' => 1,
                'unit_price' => 0.99
            ]);
        }

        // Step 5: Verify customer access to purchased tracks
        expect($this->customer->purchasedTracks)->toHaveCount(3);
    });

    it('handles payment failures gracefully', function () {
        $cart = $this->purchaseService->createCart($this->customer);
        $this->purchaseService->addToCart($cart, $this->tracks->first(), 1);

        // Simulate payment failure
        expect(fn() => $this->purchaseService->processPayment($cart, [
            'payment_method' => 'invalid_card'
        ]))->toThrow(\App\Exceptions\PaymentFailedException::class);

        // Verify no invoice was created
        expect($this->customer->invoices)->toHaveCount(0);
    });
});
```

### Playlist Creation and Sharing Workflow

```php
<?php

// tests/Integration/Workflows/PlaylistWorkflowTest.php
use App\Models\User;
use App\Models\Track;
use App\Models\Playlist;
use App\Services\PlaylistService;

describe('Playlist Creation and Sharing Workflow', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->friend = User::factory()->create();
        $this->tracks = Track::factory()->count(10)->create();
        $this->playlistService = app(PlaylistService::class);
    });

    it('completes playlist creation and sharing workflow', function () {
        // Step 1: Create playlist
        $playlist = $this->playlistService->createPlaylist($this->user, [
            'name' => 'My Awesome Playlist',
            'description' => 'Collection of great tracks'
        ]);

        expect($playlist->user_id)->toBe($this->user->id);

        // Step 2: Add tracks to playlist
        $selectedTracks = $this->tracks->take(5);
        foreach ($selectedTracks as $index => $track) {
            $this->playlistService->addTrack($playlist, $track, $index + 1);
        }

        expect($playlist->tracks)->toHaveCount(5);

        // Step 3: Reorder tracks
        $newOrder = $selectedTracks->reverse()->pluck('id')->toArray();
        $this->playlistService->reorderTracks($playlist, $newOrder);

        $orderedTracks = $playlist->fresh()->tracks()->orderBy('pivot_position')->get();
        expect($orderedTracks->first()->id)->toBe($selectedTracks->last()->id);

        // Step 4: Share playlist
        $this->playlistService->sharePlaylist($playlist, $this->friend);

        expect($this->friend->sharedPlaylists)->toContain($playlist);

        // Step 5: Verify permissions
        expect($this->playlistService->canView($this->friend, $playlist))->toBeTrue();
        expect($this->playlistService->canEdit($this->friend, $playlist))->toBeFalse();
    });
});
```

## Performance Testing

### Database Query Performance

```php
<?php

// tests/Integration/Performance/DatabasePerformanceTest.php
use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;
use App\Models\Category;

describe('Database Query Performance', function () {
    beforeEach(function () {
        // Create large dataset
        $this->artists = Artist::factory()->count(100)->create();
        $this->albums = Album::factory()->count(500)->create();
        $this->tracks = Track::factory()->count(5000)->create();
        $this->categories = Category::factory()->count(50)->create();
    });

    it('performs complex queries efficiently', function () {
        $startTime = microtime(true);

        // Complex query with multiple joins and filters
        $results = Track::with(['album.artist', 'categories'])
            ->whereHas('album.artist', function ($query) {
                $query->where('country', 'USA');
            })
            ->whereHas('categories', function ($query) {
                $query->where('type', CategoryType::GENRE);
            })
            ->limit(100)
            ->get();

        $queryTime = (microtime(true) - $startTime) * 1000;

        expect($results)->toHaveCount(100);
        expect($queryTime)->toBeLessThan(500); // Should complete in under 500ms
    });

    it('handles pagination efficiently', function () {
        $startTime = microtime(true);

        $paginated = Track::with(['album', 'categories'])
            ->paginate(50);

        $queryTime = (microtime(true) - $startTime) * 1000;

        expect($paginated->items())->toHaveCount(50);
        expect($queryTime)->toBeLessThan(200); // Should complete in under 200ms
    });

    it('performs aggregation queries efficiently', function () {
        $startTime = microtime(true);

        $stats = [
            'total_tracks' => Track::count(),
            'total_albums' => Album::count(),
            'total_artists' => Artist::count(),
            'avg_track_duration' => Track::avg('milliseconds'),
            'total_revenue' => InvoiceLine::sum(\DB::raw('unit_price * quantity'))
        ];

        $queryTime = (microtime(true) - $startTime) * 1000;

        expect($stats['total_tracks'])->toBe(5000);
        expect($queryTime)->toBeLessThan(100); // Should complete in under 100ms
    });
});
```

### Memory Usage Testing

```php
<?php

// tests/Integration/Performance/MemoryUsageTest.php

describe('Memory Usage Performance', function () {
    it('handles large result sets without excessive memory usage', function () {
        $startMemory = memory_get_usage();

        // Process large dataset in chunks
        Track::chunk(1000, function ($tracks) {
            foreach ($tracks as $track) {
                // Simulate processing
                $track->formatted_duration;
            }
        });

        $endMemory = memory_get_usage();
        $memoryUsed = $endMemory - $startMemory;

        expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // Less than 50MB
    });

    it('properly releases memory after operations', function () {
        $baselineMemory = memory_get_usage();

        // Perform memory-intensive operation
        $tracks = Track::with(['album', 'categories'])->limit(1000)->get();
        $tracks = null; // Release reference

        gc_collect_cycles(); // Force garbage collection

        $finalMemory = memory_get_usage();
        $memoryDifference = $finalMemory - $baselineMemory;

        expect($memoryDifference)->toBeLessThan(10 * 1024 * 1024); // Less than 10MB difference
    });
});
```

## Test Organization

### Directory Structure

```text
tests/Integration/
├── Database/
│   ├── PolymorphicRelationshipTest.php
│   ├── ComplexRelationshipTest.php
│   ├── ClosureTableTest.php
│   └── DataIntegrityTest.php
├── Services/
│   ├── MusicMetadataServiceTest.php
│   ├── FileStorageServiceTest.php
│   └── RecommendationServiceTest.php
├── Workflows/
│   ├── MusicPurchaseWorkflowTest.php
│   ├── PlaylistWorkflowTest.php
│   └── UserRegistrationWorkflowTest.php
└── Performance/
    ├── DatabasePerformanceTest.php
    ├── MemoryUsageTest.php
    └── ConcurrencyTest.php
```

## Best Practices

### Integration Test Guidelines

1. **Test Real Interactions**: Focus on actual system component interactions
2. **Use Realistic Data**: Create realistic test scenarios with proper data volumes
3. **Performance Benchmarks**: Set and monitor performance expectations
4. **Error Scenarios**: Test failure modes and recovery mechanisms

### Performance Testing Standards

1. **Response Time Targets**: Set specific performance targets for different operations
2. **Memory Usage Limits**: Monitor and limit memory consumption
3. **Concurrency Testing**: Test system behavior under concurrent load
4. **Regression Prevention**: Prevent performance regressions with automated tests

---

**Navigation:**

- **Previous:** [Feature Testing Guide](030-feature-testing-guide.md)
- **Next:** [Test Data Management](050-test-data-management.md)
- **Up:** [Testing Documentation](000-testing-index.md)
