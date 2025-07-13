# Model Observers Guide

## Table of Contents

- [Overview](#overview)
- [Basic Observer Implementation](#basic-observer-implementation)
- [Advanced Observer Patterns](#advanced-observer-patterns)
- [Event-Driven Architecture](#event-driven-architecture)
- [Performance Considerations](#performance-considerations)
- [Testing Observers](#testing-observers)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive model observer patterns for Laravel 12 in the Chinook application. Observers provide a clean way to listen for model events and execute code when specific actions occur, enabling separation of concerns and maintainable event-driven architecture.

**🚀 Key Features:**
- **Event-Driven Architecture**: Clean separation of business logic
- **Automatic Registration**: Seamless integration with Laravel's event system
- **Performance Optimized**: Efficient event handling patterns
- **Testable Design**: Easy to test observer behavior
- **WCAG 2.1 AA Compliance**: Accessible event-driven features

## Basic Observer Implementation

### Artist Observer

```php
<?php
// app/Observers/ArtistObserver.php

namespace App\Observers;

use App\Models\Artist;
use App\Services\{SearchIndexService, CacheService, NotificationService};
use Illuminate\Support\Facades\Log;

class ArtistObserver
{
    public function __construct(
        private SearchIndexService $searchService,
        private CacheService $cacheService,
        private NotificationService $notificationService
    ) {}

    /**
     * Handle the Artist "creating" event
     */
    public function creating(Artist $artist): void
    {
        // Generate slug if not provided
        if (empty($artist->slug)) {
            $artist->slug = $artist->generateSlug($artist->name);
        }

        // Set default values
        if (is_null($artist->is_active)) {
            $artist->is_active = true;
        }

        // Validate business rules
        $this->validateArtistCreation($artist);
    }

    /**
     * Handle the Artist "created" event
     */
    public function created(Artist $artist): void
    {
        // Add to search index
        $this->searchService->indexArtist($artist);

        // Log creation
        Log::info('Artist created', [
            'artist_id' => $artist->id,
            'name' => $artist->name,
            'created_by' => $artist->created_by,
        ]);

        // Send notification to admins
        $this->notificationService->notifyAdmins('artist.created', $artist);

        // Clear relevant caches
        $this->cacheService->clearArtistCaches();
    }

    /**
     * Handle the Artist "updating" event
     */
    public function updating(Artist $artist): void
    {
        // Track changes for audit
        $artist->original_values = $artist->getOriginal();

        // Update slug if name changed
        if ($artist->isDirty('name')) {
            $artist->slug = $artist->generateSlug($artist->name);
        }

        // Validate business rules
        $this->validateArtistUpdate($artist);
    }

    /**
     * Handle the Artist "updated" event
     */
    public function updated(Artist $artist): void
    {
        // Update search index
        $this->searchService->updateArtist($artist);

        // Clear caches
        $this->cacheService->clearArtistCache($artist->id);

        // Log significant changes
        if ($artist->wasChanged(['name', 'is_active'])) {
            Log::info('Artist updated', [
                'artist_id' => $artist->id,
                'changes' => $artist->getChanges(),
                'updated_by' => $artist->updated_by,
            ]);
        }

        // Notify if artist was deactivated
        if ($artist->wasChanged('is_active') && !$artist->is_active) {
            $this->notificationService->notifyAdmins('artist.deactivated', $artist);
        }
    }

    /**
     * Handle the Artist "deleting" event
     */
    public function deleting(Artist $artist): void
    {
        // Check if artist can be deleted
        if ($artist->albums()->exists()) {
            throw new \Exception('Cannot delete artist with existing albums');
        }

        // Log deletion attempt
        Log::warning('Artist deletion attempted', [
            'artist_id' => $artist->id,
            'name' => $artist->name,
            'deleted_by' => auth()->id(),
        ]);
    }

    /**
     * Handle the Artist "deleted" event
     */
    public function deleted(Artist $artist): void
    {
        // Remove from search index
        $this->searchService->removeArtist($artist);

        // Clear caches
        $this->cacheService->clearArtistCache($artist->id);
        $this->cacheService->clearArtistCaches();

        // Log deletion
        Log::info('Artist deleted', [
            'artist_id' => $artist->id,
            'name' => $artist->name,
            'deleted_by' => $artist->deleted_by,
        ]);

        // Notify admins
        $this->notificationService->notifyAdmins('artist.deleted', $artist);
    }

    /**
     * Handle the Artist "restored" event
     */
    public function restored(Artist $artist): void
    {
        // Re-add to search index
        $this->searchService->indexArtist($artist);

        // Clear caches
        $this->cacheService->clearArtistCaches();

        // Log restoration
        Log::info('Artist restored', [
            'artist_id' => $artist->id,
            'name' => $artist->name,
            'restored_by' => auth()->id(),
        ]);
    }

    /**
     * Validate artist creation business rules
     */
    private function validateArtistCreation(Artist $artist): void
    {
        // Check for duplicate names
        if (Artist::where('name', $artist->name)->exists()) {
            throw new \Exception('Artist with this name already exists');
        }

        // Validate required fields
        if (empty($artist->name)) {
            throw new \Exception('Artist name is required');
        }
    }

    /**
     * Validate artist update business rules
     */
    private function validateArtistUpdate(Artist $artist): void
    {
        // Check for duplicate names (excluding current artist)
        if ($artist->isDirty('name')) {
            $exists = Artist::where('name', $artist->name)
                ->where('id', '!=', $artist->id)
                ->exists();
                
            if ($exists) {
                throw new \Exception('Artist with this name already exists');
            }
        }
    }
}
```

### Album Observer

```php
<?php
// app/Observers/AlbumObserver.php

namespace App\Observers;

use App\Models\Album;
use App\Services\{SearchIndexService, CacheService, MediaProcessingService};

class AlbumObserver
{
    public function __construct(
        private SearchIndexService $searchService,
        private CacheService $cacheService,
        private MediaProcessingService $mediaService
    ) {}

    /**
     * Handle the Album "created" event
     */
    public function created(Album $album): void
    {
        // Add to search index
        $this->searchService->indexAlbum($album);

        // Process album artwork if provided
        if ($album->artwork_path) {
            $this->mediaService->processAlbumArtwork($album);
        }

        // Update artist's album count cache
        $this->cacheService->clearArtistCache($album->artist_id);

        // Log creation
        Log::info('Album created', [
            'album_id' => $album->id,
            'title' => $album->title,
            'artist_id' => $album->artist_id,
        ]);
    }

    /**
     * Handle the Album "updated" event
     */
    public function updated(Album $album): void
    {
        // Update search index
        $this->searchService->updateAlbum($album);

        // Reprocess artwork if changed
        if ($album->wasChanged('artwork_path') && $album->artwork_path) {
            $this->mediaService->processAlbumArtwork($album);
        }

        // Clear relevant caches
        $this->cacheService->clearAlbumCache($album->id);
        
        if ($album->wasChanged('artist_id')) {
            $this->cacheService->clearArtistCache($album->getOriginal('artist_id'));
            $this->cacheService->clearArtistCache($album->artist_id);
        }
    }

    /**
     * Handle the Album "deleting" event
     */
    public function deleting(Album $album): void
    {
        // Soft delete all tracks
        $album->tracks()->delete();
    }

    /**
     * Handle the Album "deleted" event
     */
    public function deleted(Album $album): void
    {
        // Remove from search index
        $this->searchService->removeAlbum($album);

        // Clean up artwork files
        if ($album->artwork_path) {
            $this->mediaService->deleteAlbumArtwork($album);
        }

        // Clear caches
        $this->cacheService->clearAlbumCache($album->id);
        $this->cacheService->clearArtistCache($album->artist_id);
    }
}
```

## Advanced Observer Patterns

### Track Observer with Complex Logic

```php
<?php
// app/Observers/TrackObserver.php

namespace App\Observers;

use App\Models\Track;
use App\Services\{AudioProcessingService, LyricsAnalysisService, RecommendationService};
use App\Jobs\{ProcessAudioFile, AnalyzeLyrics, UpdateRecommendations};

class TrackObserver
{
    /**
     * Handle the Track "created" event
     */
    public function created(Track $track): void
    {
        // Queue audio processing
        if ($track->file_path) {
            ProcessAudioFile::dispatch($track);
        }

        // Queue lyrics analysis
        if ($track->lyrics) {
            AnalyzeLyrics::dispatch($track);
        }

        // Update album statistics
        $this->updateAlbumStatistics($track->album);

        // Queue recommendation updates
        UpdateRecommendations::dispatch($track->album->artist);
    }

    /**
     * Handle the Track "updated" event
     */
    public function updated(Track $track): void
    {
        // Reprocess audio if file changed
        if ($track->wasChanged('file_path') && $track->file_path) {
            ProcessAudioFile::dispatch($track);
        }

        // Reanalyze lyrics if changed
        if ($track->wasChanged('lyrics') && $track->lyrics) {
            AnalyzeLyrics::dispatch($track);
        }

        // Update album statistics if duration changed
        if ($track->wasChanged('duration_ms')) {
            $this->updateAlbumStatistics($track->album);
        }

        // Update search index
        $this->searchService->updateTrack($track);
    }

    /**
     * Handle the Track "deleted" event
     */
    public function deleted(Track $track): void
    {
        // Clean up audio files
        if ($track->file_path) {
            Storage::delete($track->file_path);
        }

        // Update album statistics
        $this->updateAlbumStatistics($track->album);

        // Remove from playlists
        $track->playlists()->detach();

        // Update recommendations
        UpdateRecommendations::dispatch($track->album->artist);
    }

    /**
     * Update album statistics
     */
    private function updateAlbumStatistics($album): void
    {
        if (!$album) return;

        $stats = $album->tracks()->selectRaw('
            COUNT(*) as track_count,
            SUM(duration_ms) as total_duration_ms,
            AVG(price) as average_price
        ')->first();

        $album->update([
            'track_count' => $stats->track_count,
            'total_duration_ms' => $stats->total_duration_ms,
            'average_price' => $stats->average_price,
        ]);
    }
}
```

## Event-Driven Architecture

### Observer Registration

```php
<?php
// app/Providers/EventServiceProvider.php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\{Artist, Album, Track, Playlist, User};
use App\Observers\{ArtistObserver, AlbumObserver, TrackObserver, PlaylistObserver, UserObserver};

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application
     */
    protected $listen = [
        // Custom events
        'App\Events\ArtistFeatured' => [
            'App\Listeners\UpdateArtistFeaturedStatus',
            'App\Listeners\NotifyArtistFeatured',
        ],
        
        'App\Events\AlbumReleased' => [
            'App\Listeners\UpdateReleaseStatus',
            'App\Listeners\NotifySubscribers',
            'App\Listeners\UpdateRecommendations',
        ],
    ];

    /**
     * Register any events for your application
     */
    public function boot(): void
    {
        // Register model observers
        Artist::observe(ArtistObserver::class);
        Album::observe(AlbumObserver::class);
        Track::observe(TrackObserver::class);
        Playlist::observe(PlaylistObserver::class);
        User::observe(UserObserver::class);
    }
}
```

### Custom Events and Listeners

```php
<?php
// app/Events/AlbumReleased.php

namespace App\Events;

use App\Models\Album;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlbumReleased
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Album $album
    ) {}
}

// app/Listeners/NotifySubscribers.php
namespace App\Listeners;

use App\Events\AlbumReleased;
use App\Services\NotificationService;

class NotifySubscribers
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(AlbumReleased $event): void
    {
        $this->notificationService->notifyArtistSubscribers(
            $event->album->artist,
            'New album released: ' . $event->album->title
        );
    }
}
```

## Performance Considerations

### Optimized Observer Patterns

```php
<?php
// app/Observers/OptimizedTrackObserver.php

class OptimizedTrackObserver
{
    /**
     * Handle the Track "created" event with batching
     */
    public function created(Track $track): void
    {
        // Batch operations to reduce database queries
        $this->batchUpdateOperations($track);
    }

    /**
     * Batch multiple operations for performance
     */
    private function batchUpdateOperations(Track $track): void
    {
        // Collect all operations
        $operations = collect([
            'search_index' => fn() => $this->searchService->indexTrack($track),
            'cache_clear' => fn() => $this->cacheService->clearTrackCaches($track),
            'stats_update' => fn() => $this->updateAlbumStatistics($track->album),
        ]);

        // Execute operations in optimal order
        $operations->each(fn($operation) => $operation());
    }

    /**
     * Conditional observer logic
     */
    public function updated(Track $track): void
    {
        // Only execute expensive operations if necessary
        if ($track->wasChanged(['name', 'lyrics', 'duration_ms'])) {
            $this->searchService->updateTrack($track);
        }

        if ($track->wasChanged('file_path')) {
            ProcessAudioFile::dispatch($track);
        }

        // Batch cache clearing
        $this->batchCacheClear($track);
    }

    /**
     * Efficient cache clearing
     */
    private function batchCacheClear(Track $track): void
    {
        $cacheKeys = [
            "track.{$track->id}",
            "album.{$track->album_id}.tracks",
            "artist.{$track->album->artist_id}.tracks",
        ];

        $this->cacheService->forgetMany($cacheKeys);
    }
}
```

### Async Observer Processing

```php
<?php
// app/Observers/AsyncArtistObserver.php

class AsyncArtistObserver
{
    /**
     * Handle events asynchronously for better performance
     */
    public function created(Artist $artist): void
    {
        // Immediate operations (synchronous)
        $this->handleImmediateOperations($artist);

        // Deferred operations (asynchronous)
        $this->queueDeferredOperations($artist);
    }

    private function handleImmediateOperations(Artist $artist): void
    {
        // Critical operations that must happen immediately
        Log::info('Artist created', ['artist_id' => $artist->id]);
    }

    private function queueDeferredOperations(Artist $artist): void
    {
        // Queue non-critical operations
        dispatch(function () use ($artist) {
            $this->searchService->indexArtist($artist);
            $this->cacheService->clearArtistCaches();
            $this->notificationService->notifyAdmins('artist.created', $artist);
        })->onQueue('low-priority');
    }
}
```

## Testing Observers

### Observer Testing Patterns

```php
<?php
// tests/Unit/Observers/ArtistObserverTest.php

use App\Models\Artist;
use App\Observers\ArtistObserver;
use App\Services\{SearchIndexService, CacheService};
use Tests\TestCase;
use Mockery;

class ArtistObserverTest extends TestCase
{
    private $searchService;
    private $cacheService;
    private $observer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->searchService = Mockery::mock(SearchIndexService::class);
        $this->cacheService = Mockery::mock(CacheService::class);
        
        $this->observer = new ArtistObserver(
            $this->searchService,
            $this->cacheService,
            app(NotificationService::class)
        );
    }

    public function test_creating_sets_slug_if_empty(): void
    {
        $artist = new Artist(['name' => 'Test Artist']);
        
        $this->observer->creating($artist);
        
        expect($artist->slug)->toBe('test-artist');
    }

    public function test_created_adds_to_search_index(): void
    {
        $artist = Artist::factory()->make();
        
        $this->searchService
            ->shouldReceive('indexArtist')
            ->once()
            ->with($artist);
            
        $this->cacheService
            ->shouldReceive('clearArtistCaches')
            ->once();
        
        $this->observer->created($artist);
    }

    public function test_updated_clears_cache(): void
    {
        $artist = Artist::factory()->create();
        $artist->name = 'Updated Name';
        
        $this->searchService
            ->shouldReceive('updateArtist')
            ->once()
            ->with($artist);
            
        $this->cacheService
            ->shouldReceive('clearArtistCache')
            ->once()
            ->with($artist->id);
        
        $this->observer->updated($artist);
    }

    public function test_deleting_prevents_deletion_with_albums(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->create(['artist_id' => $artist->id]);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete artist with existing albums');
        
        $this->observer->deleting($artist);
    }
}
```

### Integration Testing

```php
<?php
// tests/Feature/ObserverIntegrationTest.php

class ObserverIntegrationTest extends TestCase
{
    public function test_artist_creation_triggers_all_observers(): void
    {
        Event::fake();
        
        $artist = Artist::factory()->create();
        
        // Verify events were dispatched
        Event::assertDispatched('eloquent.created: ' . Artist::class);
        
        // Verify side effects
        expect(Cache::has("artist.{$artist->id}"))->toBeFalse();
        
        // Verify search index (if using real service)
        $this->assertDatabaseHas('search_index', [
            'indexable_type' => Artist::class,
            'indexable_id' => $artist->id,
        ]);
    }

    public function test_observer_performance(): void
    {
        $startTime = microtime(true);
        
        // Create multiple artists to test observer performance
        Artist::factory()->count(100)->create();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Assert reasonable performance (adjust threshold as needed)
        expect($executionTime)->toBeLessThan(5.0);
    }
}
```

## Best Practices

### Observer Guidelines

1. **Single Responsibility**: Each observer should handle one model's events
2. **Performance**: Use queues for expensive operations
3. **Error Handling**: Implement proper exception handling
4. **Testing**: Write comprehensive tests for observer behavior
5. **Logging**: Log important events for debugging and auditing
6. **Conditional Logic**: Only execute operations when necessary

### Observer Organization

```php
<?php
// app/Observers/BaseObserver.php

abstract class BaseObserver
{
    protected function logEvent(string $event, Model $model, array $context = []): void
    {
        Log::info("Model {$event}", array_merge([
            'model' => get_class($model),
            'id' => $model->getKey(),
            'user_id' => auth()->id(),
        ], $context));
    }

    protected function clearModelCaches(Model $model): void
    {
        $cacheKeys = [
            get_class($model) . ".{$model->getKey()}",
            get_class($model) . ".all",
        ];

        Cache::forget($cacheKeys);
    }

    protected function shouldExecute(string $environment = 'production'): bool
    {
        return app()->environment($environment);
    }
}
```

## Navigation

**← Previous:** [Model Factories Guide](090-model-factories.md)
**Next →** [Model Policies Guide](110-model-policies.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Advanced Features Guide](../../050-chinook-advanced-features-guide.md) - Advanced event patterns
- [Performance Optimization](../deployment/050-performance-optimization.md) - Performance best practices

---

*This guide provides comprehensive model observer patterns for Laravel 12 in the Chinook application. Each pattern includes performance optimization, testing strategies, and best practices for maintainable event-driven architecture.*
