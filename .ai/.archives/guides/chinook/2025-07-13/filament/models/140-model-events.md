# Model Events Guide

## Table of Contents

- [Overview](#overview)
- [Laravel 12 Event Patterns](#laravel-12-event-patterns)
- [Model Observers](#model-observers)
- [Event Listeners](#event-listeners)
- [Custom Model Events](#custom-model-events)
- [Event-Driven Architecture](#event-driven-architecture)
- [Performance Considerations](#performance-considerations)
- [Testing Model Events](#testing-model-events)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive model event handling for Laravel 12 models in the Chinook application. The system provides event-driven architecture, model observers, custom events, and performance optimization for reactive model behavior.

**🚀 Key Features:**
- **Event-Driven Architecture**: Reactive model behavior with events
- **Model Observers**: Centralized event handling logic
- **Custom Events**: Domain-specific event broadcasting
- **Performance Optimized**: Efficient event handling and queuing
- **WCAG 2.1 AA Compliance**: Accessible event-driven interfaces

## Laravel 12 Event Patterns

### Basic Model Events

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Artist extends Model
{
    /**
     * Boot the model and register event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Artist $artist) {
            // Generate public_id if not set
            if (empty($artist->public_id)) {
                $artist->public_id = $artist->generatePublicId();
            }

            // Generate slug if not set
            if (empty($artist->slug)) {
                $artist->slug = $artist->generateSlug();
            }

            Log::info('Artist being created', ['name' => $artist->name]);
        });

        static::created(function (Artist $artist) {
            // Clear relevant caches
            Cache::tags(['artists', 'popular'])->flush();
            
            // Dispatch custom event
            event(new \App\Events\ArtistCreated($artist));
            
            Log::info('Artist created successfully', [
                'id' => $artist->id,
                'public_id' => $artist->public_id
            ]);
        });

        static::updating(function (Artist $artist) {
            // Track what's being updated
            $dirty = $artist->getDirty();
            
            if (isset($dirty['name'])) {
                Log::info('Artist name being updated', [
                    'id' => $artist->id,
                    'old_name' => $artist->getOriginal('name'),
                    'new_name' => $dirty['name']
                ]);
            }
        });

        static::updated(function (Artist $artist) {
            // Clear model-specific cache
            Cache::forget("artist_{$artist->id}");
            Cache::tags(['artists'])->flush();
            
            // Dispatch update event
            event(new \App\Events\ArtistUpdated($artist, $artist->getChanges()));
        });

        static::deleting(function (Artist $artist) {
            // Prevent deletion if artist has albums
            if ($artist->albums()->exists()) {
                throw new \Exception('Cannot delete artist with existing albums');
            }
            
            Log::warning('Artist being deleted', [
                'id' => $artist->id,
                'name' => $artist->name
            ]);
        });

        static::deleted(function (Artist $artist) {
            // Clean up related data
            $artist->categories()->detach();
            $artist->tags()->detach();
            
            // Clear caches
            Cache::forget("artist_{$artist->id}");
            Cache::tags(['artists', 'popular'])->flush();
            
            // Dispatch deletion event
            event(new \App\Events\ArtistDeleted($artist));
            
            Log::info('Artist deleted', ['id' => $artist->id]);
        });

        static::restored(function (Artist $artist) {
            // Clear caches when restored from soft delete
            Cache::tags(['artists'])->flush();
            
            event(new \App\Events\ArtistRestored($artist));
            
            Log::info('Artist restored', [
                'id' => $artist->id,
                'name' => $artist->name
            ]);
        });
    }
}
```

### Album Model Events

```php
<?php
// app/Models/Album.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Jobs\ProcessAlbumArtwork;
use App\Jobs\UpdateArtistStatistics;

class Album extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Album $album) {
            // Set default values
            if (empty($album->public_id)) {
                $album->public_id = $album->generatePublicId();
            }

            // Validate release date
            if ($album->release_date && $album->release_date->isFuture()) {
                $album->status = 'upcoming';
            }
        });

        static::created(function (Album $album) {
            // Queue artwork processing if cover uploaded
            if ($album->cover_path) {
                ProcessAlbumArtwork::dispatch($album);
            }

            // Update artist statistics
            UpdateArtistStatistics::dispatch($album->artist);

            // Clear relevant caches
            Cache::tags(['albums', 'recent'])->flush();
            
            event(new \App\Events\AlbumCreated($album));
        });

        static::updated(function (Album $album) {
            $changes = $album->getChanges();

            // Handle cover image changes
            if (isset($changes['cover_path'])) {
                ProcessAlbumArtwork::dispatch($album);
            }

            // Handle release date changes
            if (isset($changes['release_date'])) {
                $album->updateStatus();
            }

            // Update artist statistics if artist changed
            if (isset($changes['artist_id'])) {
                $oldArtistId = $album->getOriginal('artist_id');
                if ($oldArtistId) {
                    UpdateArtistStatistics::dispatch(Artist::find($oldArtistId));
                }
                UpdateArtistStatistics::dispatch($album->artist);
            }

            Cache::forget("album_{$album->id}");
            Cache::tags(['albums'])->flush();
            
            event(new \App\Events\AlbumUpdated($album, $changes));
        });

        static::deleting(function (Album $album) {
            // Archive tracks instead of deleting
            $album->tracks()->update(['status' => 'archived']);
        });

        static::deleted(function (Album $album) {
            // Update artist statistics
            UpdateArtistStatistics::dispatch($album->artist);
            
            // Clean up files
            if ($album->cover_path) {
                Storage::delete($album->cover_path);
            }

            Cache::forget("album_{$album->id}");
            Cache::tags(['albums', 'recent'])->flush();
            
            event(new \App\Events\AlbumDeleted($album));
        });
    }

    /**
     * Update album status based on release date
     */
    protected function updateStatus(): void
    {
        if ($this->release_date) {
            $this->status = $this->release_date->isFuture() ? 'upcoming' : 'released';
            $this->save();
        }
    }
}
```

## Model Observers

### Artist Observer

```php
<?php
// app/Observers/ArtistObserver.php

namespace App\Observers;

use App\Models\Artist;
use App\Services\SearchIndexService;
use App\Services\CacheService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;

class ArtistObserver
{
    protected SearchIndexService $searchService;
    protected CacheService $cacheService;
    protected AuditService $auditService;

    public function __construct(
        SearchIndexService $searchService,
        CacheService $cacheService,
        AuditService $auditService
    ) {
        $this->searchService = $searchService;
        $this->cacheService = $cacheService;
        $this->auditService = $auditService;
    }

    /**
     * Handle the Artist "creating" event
     */
    public function creating(Artist $artist): void
    {
        // Validate business rules
        $this->validateArtistData($artist);
        
        // Set default metadata
        if (empty($artist->metadata)) {
            $artist->metadata = $this->getDefaultMetadata();
        }

        // Log creation attempt
        $this->auditService->logModelEvent('artist.creating', $artist);
    }

    /**
     * Handle the Artist "created" event
     */
    public function created(Artist $artist): void
    {
        // Add to search index
        $this->searchService->indexArtist($artist);
        
        // Clear relevant caches
        $this->cacheService->clearArtistCaches();
        
        // Send notifications
        $this->sendCreationNotifications($artist);
        
        // Log successful creation
        $this->auditService->logModelEvent('artist.created', $artist);
    }

    /**
     * Handle the Artist "updating" event
     */
    public function updating(Artist $artist): void
    {
        // Track significant changes
        $this->trackSignificantChanges($artist);
        
        // Validate updates
        $this->validateArtistUpdates($artist);
        
        // Log update attempt
        $this->auditService->logModelEvent('artist.updating', $artist, [
            'changes' => $artist->getDirty()
        ]);
    }

    /**
     * Handle the Artist "updated" event
     */
    public function updated(Artist $artist): void
    {
        // Update search index
        $this->searchService->updateArtistIndex($artist);
        
        // Clear caches
        $this->cacheService->clearArtistCache($artist->id);
        
        // Handle specific field updates
        $this->handleFieldUpdates($artist);
        
        // Log successful update
        $this->auditService->logModelEvent('artist.updated', $artist, [
            'changes' => $artist->getChanges()
        ]);
    }

    /**
     * Handle the Artist "deleting" event
     */
    public function deleting(Artist $artist): void
    {
        // Check business constraints
        if ($artist->albums()->exists()) {
            throw new \Exception('Cannot delete artist with existing albums');
        }
        
        // Archive related data
        $this->archiveRelatedData($artist);
        
        // Log deletion attempt
        $this->auditService->logModelEvent('artist.deleting', $artist);
    }

    /**
     * Handle the Artist "deleted" event
     */
    public function deleted(Artist $artist): void
    {
        // Remove from search index
        $this->searchService->removeArtistFromIndex($artist->id);
        
        // Clear all related caches
        $this->cacheService->clearArtistCache($artist->id);
        $this->cacheService->clearArtistCaches();
        
        // Clean up files
        $this->cleanupArtistFiles($artist);
        
        // Log successful deletion
        $this->auditService->logModelEvent('artist.deleted', $artist);
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
        
        // Restore related data
        $this->restoreRelatedData($artist);
        
        // Log restoration
        $this->auditService->logModelEvent('artist.restored', $artist);
    }

    /**
     * Validate artist data
     */
    protected function validateArtistData(Artist $artist): void
    {
        if (empty($artist->name)) {
            throw new \InvalidArgumentException('Artist name is required');
        }

        if (strlen($artist->name) > 255) {
            throw new \InvalidArgumentException('Artist name too long');
        }
    }

    /**
     * Get default metadata for new artists
     */
    protected function getDefaultMetadata(): array
    {
        return [
            'social' => [],
            'preferences' => [
                'public_profile' => true,
                'show_stats' => false,
            ],
            'stats' => [
                'view_count' => 0,
                'play_count' => 0,
            ],
        ];
    }

    /**
     * Track significant changes
     */
    protected function trackSignificantChanges(Artist $artist): void
    {
        $significantFields = ['name', 'bio', 'is_active', 'is_featured'];
        $changes = array_intersect_key($artist->getDirty(), array_flip($significantFields));
        
        if (!empty($changes)) {
            Log::info('Significant artist changes detected', [
                'artist_id' => $artist->id,
                'changes' => $changes
            ]);
        }
    }

    /**
     * Handle specific field updates
     */
    protected function handleFieldUpdates(Artist $artist): void
    {
        $changes = $artist->getChanges();

        // Handle name changes
        if (isset($changes['name'])) {
            $this->handleNameChange($artist, $changes['name']);
        }

        // Handle status changes
        if (isset($changes['is_active'])) {
            $this->handleStatusChange($artist, $changes['is_active']);
        }
    }

    /**
     * Handle artist name changes
     */
    protected function handleNameChange(Artist $artist, string $newName): void
    {
        // Update related slugs if needed
        if (empty($artist->slug) || $artist->slug === Str::slug($artist->getOriginal('name'))) {
            $artist->slug = $artist->generateSlug();
            $artist->save();
        }
    }

    /**
     * Handle artist status changes
     */
    protected function handleStatusChange(Artist $artist, bool $isActive): void
    {
        if (!$isActive) {
            // Hide albums when artist is deactivated
            $artist->albums()->update(['is_active' => false]);
        }
    }

    /**
     * Send creation notifications
     */
    protected function sendCreationNotifications(Artist $artist): void
    {
        // Notify administrators
        // This would typically dispatch notification jobs
    }

    /**
     * Archive related data before deletion
     */
    protected function archiveRelatedData(Artist $artist): void
    {
        // Archive categories, tags, etc.
        $artist->categories()->detach();
        $artist->tags()->detach();
    }

    /**
     * Clean up artist files
     */
    protected function cleanupArtistFiles(Artist $artist): void
    {
        if ($artist->avatar_path) {
            Storage::delete($artist->avatar_path);
        }
    }

    /**
     * Restore related data after restoration
     */
    protected function restoreRelatedData(Artist $artist): void
    {
        // Restore any archived relationships if needed
    }
}
```

## Event Listeners

### Custom Event Listeners

```php
<?php
// app/Listeners/ArtistEventListener.php

namespace App\Listeners;

use App\Events\ArtistCreated;
use App\Events\ArtistUpdated;
use App\Events\ArtistDeleted;
use App\Services\NotificationService;
use App\Services\AnalyticsService;
use App\Services\RecommendationService;

class ArtistEventListener
{
    protected NotificationService $notificationService;
    protected AnalyticsService $analyticsService;
    protected RecommendationService $recommendationService;

    public function __construct(
        NotificationService $notificationService,
        AnalyticsService $analyticsService,
        RecommendationService $recommendationService
    ) {
        $this->notificationService = $notificationService;
        $this->analyticsService = $analyticsService;
        $this->recommendationService = $recommendationService;
    }

    /**
     * Handle artist created event
     */
    public function handleArtistCreated(ArtistCreated $event): void
    {
        $artist = $event->artist;

        // Send welcome notification
        $this->notificationService->sendWelcomeNotification($artist);

        // Track analytics
        $this->analyticsService->trackEvent('artist.created', [
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'created_at' => $artist->created_at,
        ]);

        // Update recommendation engine
        $this->recommendationService->addNewArtist($artist);
    }

    /**
     * Handle artist updated event
     */
    public function handleArtistUpdated(ArtistUpdated $event): void
    {
        $artist = $event->artist;
        $changes = $event->changes;

        // Track significant changes
        if (isset($changes['name']) || isset($changes['bio'])) {
            $this->analyticsService->trackEvent('artist.profile_updated', [
                'artist_id' => $artist->id,
                'changes' => array_keys($changes),
            ]);
        }

        // Update recommendations if genre changed
        if (isset($changes['primary_genre_id'])) {
            $this->recommendationService->updateArtistProfile($artist);
        }

        // Send notifications for status changes
        if (isset($changes['is_active'])) {
            $this->handleStatusChange($artist, $changes['is_active']);
        }
    }

    /**
     * Handle artist deleted event
     */
    public function handleArtistDeleted(ArtistDeleted $event): void
    {
        $artist = $event->artist;

        // Track deletion
        $this->analyticsService->trackEvent('artist.deleted', [
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'deleted_at' => now(),
        ]);

        // Remove from recommendations
        $this->recommendationService->removeArtist($artist->id);

        // Send admin notification
        $this->notificationService->notifyAdmins('artist_deleted', [
            'artist' => $artist->name,
            'id' => $artist->id,
        ]);
    }

    /**
     * Handle artist status changes
     */
    protected function handleStatusChange(Artist $artist, bool $isActive): void
    {
        if ($isActive) {
            $this->notificationService->sendActivationNotification($artist);
        } else {
            $this->notificationService->sendDeactivationNotification($artist);
        }
    }
}
```

## Custom Model Events

### Custom Event Classes

```php
<?php
// app/Events/ArtistCreated.php

namespace App\Events;

use App\Models\Artist;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArtistCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Artist $artist;

    public function __construct(Artist $artist)
    {
        $this->artist = $artist;
    }

    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('artists'),
            new PrivateChannel('admin.artists'),
        ];
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->artist->id,
            'public_id' => $this->artist->public_id,
            'name' => $this->artist->name,
            'slug' => $this->artist->slug,
            'created_at' => $this->artist->created_at,
        ];
    }

    /**
     * Get the broadcast event name
     */
    public function broadcastAs(): string
    {
        return 'artist.created';
    }
}
```

```php
<?php
// app/Events/AlbumReleased.php

namespace App\Events;

use App\Models\Album;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlbumReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Album $album;

    public function __construct(Album $album)
    {
        $this->album = $album;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('albums'),
            new Channel("artist.{$this->album->artist->public_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'album' => [
                'id' => $this->album->id,
                'public_id' => $this->album->public_id,
                'title' => $this->album->title,
                'artist' => $this->album->artist->name,
                'release_date' => $this->album->release_date,
                'cover_url' => $this->album->cover_url,
            ],
        ];
    }

    public function broadcastAs(): string
    {
        return 'album.released';
    }
}
```

## Event-Driven Architecture

### Event Service Provider

```php
<?php
// app/Providers/EventServiceProvider.php

namespace App\Providers;

use App\Events\ArtistCreated;
use App\Events\ArtistUpdated;
use App\Events\ArtistDeleted;
use App\Events\AlbumReleased;
use App\Listeners\ArtistEventListener;
use App\Listeners\AlbumEventListener;
use App\Observers\ArtistObserver;
use App\Observers\AlbumObserver;
use App\Observers\CategoryObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application
     */
    protected $listen = [
        ArtistCreated::class => [
            ArtistEventListener::class . '@handleArtistCreated',
        ],
        ArtistUpdated::class => [
            ArtistEventListener::class . '@handleArtistUpdated',
        ],
        ArtistDeleted::class => [
            ArtistEventListener::class . '@handleArtistDeleted',
        ],
        AlbumReleased::class => [
            AlbumEventListener::class . '@handleAlbumReleased',
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
        Category::observe(CategoryObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
```

## Performance Considerations

### Event Performance Optimization

```php
<?php
// app/Services/EventOptimizationService.php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

class EventOptimizationService
{
    /**
     * Queue heavy event processing
     */
    public function queueEventProcessing(string $eventClass, array $data): void
    {
        Queue::push(new ProcessEventJob($eventClass, $data));
    }

    /**
     * Batch event processing
     */
    public function batchEvents(array $events): void
    {
        $batches = array_chunk($events, 100);

        foreach ($batches as $batch) {
            Queue::push(new ProcessEventBatchJob($batch));
        }
    }

    /**
     * Debounce frequent events
     */
    public function debounceEvent(string $key, callable $callback, int $delay = 5): void
    {
        $cacheKey = "debounce_{$key}";

        if (!Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, $delay);

            // Schedule the callback
            dispatch(function () use ($callback) {
                $callback();
            })->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Throttle event processing
     */
    public function throttleEvent(string $key, callable $callback, int $maxPerMinute = 60): bool
    {
        $cacheKey = "throttle_{$key}";
        $current = Cache::get($cacheKey, 0);

        if ($current >= $maxPerMinute) {
            return false;
        }

        Cache::put($cacheKey, $current + 1, 60);
        $callback();

        return true;
    }
}
```

## Testing Model Events

### Event Testing Suite

```php
<?php
// tests/Feature/ModelEventsTest.php

use App\Models\Artist;
use App\Events\ArtistCreated;
use App\Events\ArtistUpdated;
use App\Events\ArtistDeleted;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ModelEventsTest extends TestCase
{
    public function test_artist_created_event_is_dispatched(): void
    {
        Event::fake();

        $artist = Artist::factory()->create();

        Event::assertDispatched(ArtistCreated::class, function ($event) use ($artist) {
            return $event->artist->id === $artist->id;
        });
    }

    public function test_artist_updated_event_is_dispatched(): void
    {
        Event::fake();

        $artist = Artist::factory()->create();
        $artist->update(['name' => 'Updated Name']);

        Event::assertDispatched(ArtistUpdated::class, function ($event) use ($artist) {
            return $event->artist->id === $artist->id &&
                   isset($event->changes['name']);
        });
    }

    public function test_artist_deleted_event_is_dispatched(): void
    {
        Event::fake();

        $artist = Artist::factory()->create();
        $artist->delete();

        Event::assertDispatched(ArtistDeleted::class, function ($event) use ($artist) {
            return $event->artist->id === $artist->id;
        });
    }

    public function test_observer_prevents_deletion_with_albums(): void
    {
        $artist = Artist::factory()->create();
        Album::factory()->create(['artist_id' => $artist->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete artist with existing albums');

        $artist->delete();
    }

    public function test_observer_generates_public_id_on_creation(): void
    {
        $artist = Artist::factory()->make(['public_id' => null]);
        $artist->save();

        $this->assertNotNull($artist->public_id);
        $this->assertStringStartsWith('art_', $artist->public_id);
    }

    public function test_cache_is_cleared_on_model_changes(): void
    {
        Cache::put('artist_1', 'cached_data');

        $artist = Artist::factory()->create(['id' => 1]);
        $artist->update(['name' => 'New Name']);

        $this->assertNull(Cache::get('artist_1'));
    }
}
```

## Best Practices

### Model Event Guidelines

1. **Performance**: Queue heavy event processing to avoid blocking requests
2. **Separation**: Use observers for model-specific logic, listeners for cross-cutting concerns
3. **Testing**: Always test event dispatching and handling
4. **Logging**: Log important model events for audit trails
5. **Error Handling**: Implement proper error handling in event listeners
6. **Debouncing**: Use debouncing for frequently triggered events

### Implementation Checklist

```php
<?php
// Model events implementation checklist

/*
✓ Register model observers for centralized event handling
✓ Create custom events for domain-specific actions
✓ Implement event listeners for cross-cutting concerns
✓ Queue heavy event processing to avoid blocking
✓ Add comprehensive event testing
✓ Implement proper error handling in events
✓ Use caching strategies with event-driven invalidation
✓ Log important events for audit trails
✓ Implement event debouncing for performance
✓ Set up event broadcasting for real-time updates
✓ Document event flows and dependencies
✓ Monitor event processing performance
*/
```

## Navigation

**← Previous:** [Accessors and Mutators Guide](130-accessors-mutators.md)
**Next →** [Custom Methods Guide](150-custom-methods.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Model Observers Guide](100-model-observers.md) - Observer patterns
- [Real-time Updates](../features/040-real-time-updates.md) - Real-time event handling

---

*This guide provides comprehensive model event handling for Laravel 12 models in the Chinook application. The system includes event-driven architecture, model observers, custom events, and performance optimization for reactive model behavior.*
