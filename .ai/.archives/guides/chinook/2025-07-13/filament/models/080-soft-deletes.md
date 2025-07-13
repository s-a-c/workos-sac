# Soft Deletes Guide

## Table of Contents

- [Overview](#overview)
- [Basic Soft Delete Implementation](#basic-soft-delete-implementation)
- [Advanced Soft Delete Patterns](#advanced-soft-delete-patterns)
- [Cascade Soft Deletes](#cascade-soft-deletes)
- [Restoration Strategies](#restoration-strategies)
- [Performance Optimization](#performance-optimization)
- [Security Considerations](#security-considerations)
- [Testing Soft Deletes](#testing-soft-deletes)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive soft delete patterns for Laravel 12 models in the Chinook application. Soft deletes provide data safety by marking records as deleted without actually removing them from the database, enabling data recovery and maintaining referential integrity.

**🚀 Key Features:**
- **Data Safety**: Records are marked as deleted, not physically removed
- **Restoration Capability**: Deleted records can be easily restored
- **Audit Trail Integration**: Track who deleted records and when
- **Cascade Management**: Handle related record deletions appropriately
- **WCAG 2.1 AA Compliance**: Accessible soft delete status presentation

## Basic Soft Delete Implementation

### Model Setup with Soft Deletes

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserStamps;

class Artist extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasUserStamps;

    protected function cast(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'bio' => 'string',
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected $fillable = [
        'name',
        'bio',
        'is_active',
    ];

    /**
     * Check if the artist is soft deleted
     */
    public function isSoftDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    /**
     * Get soft delete status for display
     */
    public function getSoftDeleteStatusAttribute(): array
    {
        if ($this->isSoftDeleted()) {
            return [
                'status' => 'deleted',
                'deleted_at' => $this->deleted_at->format('M j, Y g:i A'),
                'deleted_by' => $this->deleter?->name ?? 'System',
                'can_restore' => auth()->user()?->can('restore', $this) ?? false,
            ];
        }

        return [
            'status' => 'active',
            'can_delete' => auth()->user()?->can('delete', $this) ?? false,
        ];
    }
}
```

### Migration for Soft Deletes

```php
<?php
// database/migrations/add_soft_deletes_to_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'artists', 'albums', 'tracks', 'playlists', 
            'categories', 'customers'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
                
                // Add index for performance
                $table->index(['deleted_at']);
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'artists', 'albums', 'tracks', 'playlists', 
            'categories', 'customers'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
```

## Advanced Soft Delete Patterns

### Custom Soft Delete Behavior

```php
<?php
// app/Traits/EnhancedSoftDeletes.php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;

trait EnhancedSoftDeletes
{
    use SoftDeletes;

    /**
     * Boot the enhanced soft deletes trait
     */
    protected static function bootEnhancedSoftDeletes(): void
    {
        static::deleting(function ($model) {
            // Log the deletion
            if (auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->save();
            }

            // Trigger custom deletion logic
            $model->onSoftDelete();
        });

        static::restoring(function ($model) {
            // Clear deleted_by when restoring
            $model->deleted_by = null;
            
            // Trigger custom restoration logic
            $model->onRestore();
        });
    }

    /**
     * Custom logic when soft deleting
     */
    protected function onSoftDelete(): void
    {
        // Override in models for custom behavior
    }

    /**
     * Custom logic when restoring
     */
    protected function onRestore(): void
    {
        // Override in models for custom behavior
    }

    /**
     * Force delete with confirmation
     */
    public function forceDeleteWithConfirmation(string $confirmation): bool
    {
        if ($confirmation !== $this->getForceDeleteConfirmation()) {
            throw new \InvalidArgumentException('Invalid confirmation string');
        }

        return $this->forceDelete();
    }

    /**
     * Get confirmation string for force delete
     */
    protected function getForceDeleteConfirmation(): string
    {
        return "DELETE-{$this->getKey()}";
    }

    /**
     * Scope for only soft deleted records
     */
    public function scopeOnlyTrashed($query)
    {
        return $query->whereNotNull($this->getDeletedAtColumn());
    }

    /**
     * Scope for records deleted by specific user
     */
    public function scopeDeletedBy($query, int $userId)
    {
        return $query->onlyTrashed()->where('deleted_by', $userId);
    }

    /**
     * Scope for recently deleted records
     */
    public function scopeRecentlyDeleted($query, int $days = 30)
    {
        return $query->onlyTrashed()
            ->where('deleted_at', '>=', now()->subDays($days));
    }
}
```

### Model-Specific Soft Delete Logic

```php
<?php
// app/Models/Album.php

class Album extends Model
{
    use HasFactory, EnhancedSoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasUserStamps;

    /**
     * Custom soft delete behavior for albums
     */
    protected function onSoftDelete(): void
    {
        // Soft delete all tracks in this album
        $this->tracks()->delete();

        // Remove from active playlists (but keep in deleted playlists)
        $this->tracks()->each(function ($track) {
            $track->playlists()->wherePivot('is_active', true)->detach();
        });

        // Log album deletion
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->log('Album soft deleted with all tracks');
    }

    /**
     * Custom restore behavior for albums
     */
    protected function onRestore(): void
    {
        // Restore all tracks that were deleted with this album
        $this->tracks()->onlyTrashed()
            ->where('deleted_at', $this->deleted_at)
            ->restore();

        // Log album restoration
        activity()
            ->performedOn($this)
            ->causedBy(auth()->user())
            ->log('Album restored with all tracks');
    }

    /**
     * Get tracks including soft deleted ones
     */
    public function allTracks(): HasMany
    {
        return $this->hasMany(Track::class)->withTrashed();
    }

    /**
     * Check if album can be safely deleted
     */
    public function canBeSafelyDeleted(): array
    {
        $issues = [];

        // Check for active purchases
        $activePurchases = $this->tracks()
            ->join('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.paid_at', '>', now()->subDays(30))
            ->count();

        if ($activePurchases > 0) {
            $issues[] = "Album has {$activePurchases} recent purchases";
        }

        // Check for active playlists
        $activePlaylistCount = $this->tracks()
            ->join('playlist_tracks', 'tracks.id', '=', 'playlist_tracks.track_id')
            ->join('playlists', 'playlist_tracks.playlist_id', '=', 'playlists.id')
            ->whereNull('playlists.deleted_at')
            ->distinct('playlists.id')
            ->count();

        if ($activePlaylistCount > 0) {
            $issues[] = "Tracks are in {$activePlaylistCount} active playlists";
        }

        return [
            'can_delete' => empty($issues),
            'issues' => $issues,
            'recommendation' => empty($issues) 
                ? 'Safe to delete' 
                : 'Consider archiving instead of deleting',
        ];
    }
}
```

## Cascade Soft Deletes

### Implementing Cascade Soft Deletes

```php
<?php
// app/Traits/CascadeSoftDeletes.php

trait CascadeSoftDeletes
{
    /**
     * Boot cascade soft deletes
     */
    protected static function bootCascadeSoftDeletes(): void
    {
        static::deleting(function ($model) {
            $model->cascadeSoftDelete();
        });

        static::restoring(function ($model) {
            $model->cascadeRestore();
        });
    }

    /**
     * Get relationships to cascade soft delete
     */
    protected function getCascadeDeleteRelations(): array
    {
        return [];
    }

    /**
     * Get relationships to cascade restore
     */
    protected function getCascadeRestoreRelations(): array
    {
        return $this->getCascadeDeleteRelations();
    }

    /**
     * Cascade soft delete to related models
     */
    protected function cascadeSoftDelete(): void
    {
        foreach ($this->getCascadeDeleteRelations() as $relation) {
            if (method_exists($this, $relation)) {
                $this->$relation()->delete();
            }
        }
    }

    /**
     * Cascade restore to related models
     */
    protected function cascadeRestore(): void
    {
        foreach ($this->getCascadeRestoreRelations() as $relation) {
            if (method_exists($this, $relation)) {
                $this->$relation()->onlyTrashed()
                    ->where('deleted_at', $this->deleted_at)
                    ->restore();
            }
        }
    }
}

// app/Models/Artist.php
class Artist extends Model
{
    use HasFactory, EnhancedSoftDeletes, CascadeSoftDeletes, HasUserStamps;

    /**
     * Define cascade relationships
     */
    protected function getCascadeDeleteRelations(): array
    {
        return ['albums']; // When artist is deleted, delete all albums
    }
}
```

## Restoration Strategies

### Bulk Restoration Operations

```php
<?php
// app/Services/SoftDeleteService.php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SoftDeleteService
{
    /**
     * Bulk restore models
     */
    public function bulkRestore(string $modelClass, array $ids): array
    {
        $restored = [];
        $failed = [];

        foreach ($ids as $id) {
            try {
                $model = $modelClass::onlyTrashed()->findOrFail($id);
                
                if ($this->canRestore($model)) {
                    $model->restore();
                    $restored[] = $id;
                } else {
                    $failed[] = ['id' => $id, 'reason' => 'Permission denied'];
                }
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        return [
            'restored' => $restored,
            'failed' => $failed,
            'summary' => [
                'total' => count($ids),
                'restored_count' => count($restored),
                'failed_count' => count($failed),
            ],
        ];
    }

    /**
     * Check if model can be restored
     */
    protected function canRestore(Model $model): bool
    {
        return auth()->user()?->can('restore', $model) ?? false;
    }

    /**
     * Get restoration candidates
     */
    public function getRestorationCandidates(string $modelClass, int $days = 30): Collection
    {
        return $modelClass::onlyTrashed()
            ->where('deleted_at', '>=', now()->subDays($days))
            ->with(['creator', 'deleter'])
            ->get();
    }

    /**
     * Auto-restore based on criteria
     */
    public function autoRestore(string $modelClass, array $criteria): int
    {
        $query = $modelClass::onlyTrashed();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        $models = $query->get();
        $restoredCount = 0;

        foreach ($models as $model) {
            if ($this->canRestore($model)) {
                $model->restore();
                $restoredCount++;
            }
        }

        return $restoredCount;
    }
}
```

### Scheduled Cleanup

```php
<?php
// app/Console/Commands/CleanupSoftDeletes.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupSoftDeletes extends Command
{
    protected $signature = 'cleanup:soft-deletes 
                           {--model= : Specific model to clean}
                           {--days=90 : Days to keep soft deleted records}
                           {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up old soft deleted records';

    public function handle(): int
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        $model = $this->option('model');

        $models = $model ? [$model] : [
            \App\Models\Artist::class,
            \App\Models\Album::class,
            \App\Models\Track::class,
            \App\Models\Playlist::class,
        ];

        foreach ($models as $modelClass) {
            $this->cleanupModel($modelClass, $days, $dryRun);
        }

        return 0;
    }

    protected function cleanupModel(string $modelClass, int $days, bool $dryRun): void
    {
        $cutoffDate = now()->subDays($days);
        
        $query = $modelClass::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate);

        $count = $query->count();
        
        if ($count === 0) {
            $this->info("No old soft deleted records found for {$modelClass}");
            return;
        }

        if ($dryRun) {
            $this->warn("Would permanently delete {$count} records from {$modelClass}");
            return;
        }

        if ($this->confirm("Permanently delete {$count} records from {$modelClass}?")) {
            $deleted = $query->forceDelete();
            $this->info("Permanently deleted {$deleted} records from {$modelClass}");
        }
    }
}
```

## Performance Optimization

### Optimized Soft Delete Queries

```php
<?php
// Efficient soft delete queries

class OptimizedSoftDeleteQueries
{
    /**
     * Get active records with optimized query
     */
    public static function getActiveRecords(string $modelClass, array $with = []): Collection
    {
        return $modelClass::whereNull('deleted_at')
            ->with($with)
            ->get();
    }

    /**
     * Count records by status efficiently
     */
    public static function getStatusCounts(string $modelClass): array
    {
        $results = $modelClass::selectRaw('
            COUNT(*) as total,
            COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) as active,
            COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) as deleted
        ')->first();

        return [
            'total' => $results->total,
            'active' => $results->active,
            'deleted' => $results->deleted,
        ];
    }

    /**
     * Batch soft delete for performance
     */
    public static function batchSoftDelete(string $modelClass, array $ids, int $batchSize = 100): void
    {
        $chunks = array_chunk($ids, $batchSize);

        foreach ($chunks as $chunk) {
            $modelClass::whereIn('id', $chunk)
                ->update([
                    'deleted_at' => now(),
                    'deleted_by' => auth()->id(),
                ]);
        }
    }
}
```

## Security Considerations

### Secure Soft Delete Operations

```php
<?php
// app/Policies/SoftDeletePolicy.php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

trait SoftDeletePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can soft delete the model
     */
    public function delete(User $user, Model $model): bool
    {
        // Admin can delete anything
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can delete their own content
        if ($model->created_by === $user->id) {
            return true;
        }

        // Managers can delete in their department
        if ($user->hasRole('manager') && $this->sameOrganization($user, $model)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can restore the model
     */
    public function restore(User $user, Model $model): bool
    {
        // Only admins and the deleter can restore
        return $user->hasRole('admin') || $model->deleted_by === $user->id;
    }

    /**
     * Determine if user can force delete the model
     */
    public function forceDelete(User $user, Model $model): bool
    {
        // Only super admins can force delete
        return $user->hasRole('super-admin');
    }

    /**
     * Check if user and model are in same organization
     */
    protected function sameOrganization(User $user, Model $model): bool
    {
        // Implement organization logic
        return true;
    }
}
```

## Testing Soft Deletes

### Comprehensive Soft Delete Testing

```php
<?php
// tests/Unit/Models/SoftDeleteTest.php

use App\Models\Artist;
use App\Models\Album;
use App\Models\User;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    public function test_model_can_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $artist = Artist::factory()->create();
        $artist->delete();

        expect($artist->isSoftDeleted())->toBeTrue();
        expect($artist->deleted_by)->toBe($user->id);
        expect(Artist::count())->toBe(0);
        expect(Artist::withTrashed()->count())->toBe(1);
    }

    public function test_soft_deleted_model_can_be_restored(): void
    {
        $artist = Artist::factory()->create();
        $artist->delete();
        
        $artist->restore();

        expect($artist->isSoftDeleted())->toBeFalse();
        expect($artist->deleted_by)->toBeNull();
        expect(Artist::count())->toBe(1);
    }

    public function test_cascade_soft_delete_works(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        $artist->delete();

        expect($artist->isSoftDeleted())->toBeTrue();
        expect($album->fresh()->isSoftDeleted())->toBeTrue();
    }

    public function test_cascade_restore_works(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create(['artist_id' => $artist->id]);

        $artist->delete();
        $artist->restore();

        expect($artist->isSoftDeleted())->toBeFalse();
        expect($album->fresh()->isSoftDeleted())->toBeFalse();
    }

    public function test_scopes_work_correctly(): void
    {
        $activeArtist = Artist::factory()->create();
        $deletedArtist = Artist::factory()->create();
        $deletedArtist->delete();

        expect(Artist::count())->toBe(1);
        expect(Artist::onlyTrashed()->count())->toBe(1);
        expect(Artist::withTrashed()->count())->toBe(2);
    }
}
```

## Best Practices

### Soft Delete Guidelines

1. **Use Consistently**: Apply soft deletes to all user-facing models
2. **Index Performance**: Add indexes on deleted_at columns
3. **Cascade Carefully**: Consider the impact of cascade operations
4. **Regular Cleanup**: Implement scheduled cleanup of old soft deleted records
5. **Security**: Implement proper authorization for delete/restore operations
6. **Testing**: Write comprehensive tests for all soft delete scenarios

### Monitoring and Alerting

```php
<?php
// app/Services/SoftDeleteMonitoringService.php

class SoftDeleteMonitoringService
{
    /**
     * Get soft delete statistics
     */
    public function getStatistics(): array
    {
        $models = [
            'artists' => Artist::class,
            'albums' => Album::class,
            'tracks' => Track::class,
            'playlists' => Playlist::class,
        ];

        $stats = [];

        foreach ($models as $name => $class) {
            $stats[$name] = [
                'total' => $class::withTrashed()->count(),
                'active' => $class::count(),
                'deleted' => $class::onlyTrashed()->count(),
                'recently_deleted' => $class::onlyTrashed()
                    ->where('deleted_at', '>=', now()->subDays(7))
                    ->count(),
            ];
        }

        return $stats;
    }

    /**
     * Alert on unusual deletion patterns
     */
    public function checkForUnusualActivity(): array
    {
        $alerts = [];

        // Check for mass deletions
        $recentDeletions = Artist::onlyTrashed()
            ->where('deleted_at', '>=', now()->subHour())
            ->count();

        if ($recentDeletions > 10) {
            $alerts[] = "High deletion rate: {$recentDeletions} artists deleted in the last hour";
        }

        return $alerts;
    }
}
```

## Navigation

**← Previous:** [User Stamps Guide](070-user-stamps.md)
**Next →** [Model Factories Guide](090-model-factories.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [User Stamps Guide](070-user-stamps.md) - User tracking patterns
- [Required Traits Guide](020-required-traits.md) - Essential model traits

---

*This guide provides comprehensive soft delete patterns for Laravel 12 models in the Chinook application. Each pattern includes performance optimization, security considerations, and testing strategies to ensure robust data safety throughout the application.*
