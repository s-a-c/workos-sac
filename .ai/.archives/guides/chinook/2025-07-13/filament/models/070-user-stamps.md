# User Stamps Guide

## Table of Contents

- [Overview](#overview)
- [HasUserStamps Trait](#hasuserstamps-trait)
- [Implementation Patterns](#implementation-patterns)
- [Advanced User Stamping](#advanced-user-stamping)
- [Audit Trail Integration](#audit-trail-integration)
- [Performance Considerations](#performance-considerations)
- [Security and Privacy](#security-and-privacy)
- [Testing User Stamps](#testing-user-stamps)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive user stamping patterns for Laravel 12 models in the Chinook application. User stamps track who created, updated, and deleted records, providing essential audit trails and accountability throughout the application.

**🚀 Key Features:**
- **Automatic User Tracking**: Seamless integration with authentication
- **Audit Trail Support**: Complete history of record modifications
- **Soft Delete Integration**: Track who deleted records
- **Performance Optimized**: Efficient querying and relationship loading
- **WCAG 2.1 AA Compliance**: Accessible user information presentation

## HasUserStamps Trait

### Basic User Stamps Implementation

```php
<?php
// app/Traits/HasUserStamps.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasUserStamps
{
    /**
     * Boot the trait
     */
    protected static function bootHasUserStamps(): void
    {
        static::creating(function (Model $model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function (Model $model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        // Handle soft deletes if the model uses SoftDeletes
        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::deleting(function (Model $model) {
                if (Auth::check() && $model->usesSoftDeletes()) {
                    $model->deleted_by = Auth::id();
                    $model->save();
                }
            });
        }
    }

    /**
     * Get the user who created this record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this record
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Check if model uses soft deletes
     */
    protected function usesSoftDeletes(): bool
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($this));
    }

    /**
     * Scope to filter by creator
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to filter by updater
     */
    public function scopeUpdatedBy($query, $userId)
    {
        return $query->where('updated_by', $userId);
    }

    /**
     * Scope to filter by current user's creations
     */
    public function scopeCreatedByCurrentUser($query)
    {
        return $query->where('created_by', Auth::id());
    }
}
```

### Migration for User Stamps

```php
<?php
// database/migrations/add_user_stamps_to_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'artists', 'albums', 'tracks', 'playlists', 
            'categories', 'invoices', 'customers'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('created_by')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();
                      
                $table->foreignId('updated_by')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();
                      
                $table->foreignId('deleted_by')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'artists', 'albums', 'tracks', 'playlists', 
            'categories', 'invoices', 'customers'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $table->dropForeign(['deleted_by']);
                $table->dropColumn(['created_by', 'updated_by', 'deleted_by']);
            });
        }
    }
};
```

## Implementation Patterns

### Model Implementation

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

    protected function casts(): array
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
     * Get formatted creator information
     */
    public function getCreatorInfoAttribute(): array
    {
        return [
            'name' => $this->creator?->name ?? 'System',
            'email' => $this->creator?->email ?? 'system@chinook.app',
            'created_at' => $this->created_at?->format('M j, Y g:i A'),
        ];
    }

    /**
     * Get formatted updater information
     */
    public function getUpdaterInfoAttribute(): array
    {
        return [
            'name' => $this->updater?->name ?? 'System',
            'email' => $this->updater?->email ?? 'system@chinook.app',
            'updated_at' => $this->updated_at?->format('M j, Y g:i A'),
        ];
    }
}
```

### Advanced User Stamp Tracking

```php
<?php
// app/Models/Track.php

class Track extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug, HasUserStamps;

    /**
     * Track additional user actions
     */
    protected static function bootTrack(): void
    {
        static::created(function (Track $track) {
            $track->logUserAction('created');
        });

        static::updated(function (Track $track) {
            if ($track->wasChanged('is_published')) {
                $action = $track->is_published ? 'published' : 'unpublished';
                $track->logUserAction($action);
            }
        });
    }

    /**
     * Log user actions for this track
     */
    public function logUserAction(string $action): void
    {
        UserAction::create([
            'user_id' => auth()->id(),
            'actionable_type' => static::class,
            'actionable_id' => $this->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }

    /**
     * Get all user actions for this track
     */
    public function userActions(): MorphMany
    {
        return $this->morphMany(UserAction::class, 'actionable')
            ->orderBy('performed_at', 'desc');
    }

    /**
     * Get the user who published this track
     */
    public function publisher(): ?User
    {
        return $this->userActions()
            ->where('action', 'published')
            ->with('user')
            ->first()
            ?->user;
    }
}
```

## Advanced User Stamping

### User Action Logging Model

```php
<?php
// app/Models/UserAction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserAction extends Model
{
    protected $fillable = [
        'user_id',
        'actionable_type',
        'actionable_id',
        'action',
        'ip_address',
        'user_agent',
        'metadata',
        'performed_at',
    ];

    protected function cast(): array
    {
        return [
            'metadata' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the actionable model
     */
    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for specific actions
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('performed_at', [$startDate, $endDate]);
    }
}
```

### Enhanced User Stamps with Metadata

```php
<?php
// app/Traits/HasEnhancedUserStamps.php

trait HasEnhancedUserStamps
{
    use HasUserStamps;

    /**
     * Boot enhanced user stamps
     */
    protected static function bootHasEnhancedUserStamps(): void
    {
        static::creating(function (Model $model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
                $model->created_ip = request()->ip();
                $model->created_user_agent = request()->userAgent();
            }
        });

        static::updating(function (Model $model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
                $model->updated_ip = request()->ip();
                $model->updated_user_agent = request()->userAgent();
            }
        });
    }

    /**
     * Get creation metadata
     */
    public function getCreationMetadataAttribute(): array
    {
        return [
            'user' => $this->creator?->name ?? 'System',
            'ip_address' => $this->created_ip ?? 'Unknown',
            'user_agent' => $this->created_user_agent ?? 'Unknown',
            'timestamp' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Get last update metadata
     */
    public function getUpdateMetadataAttribute(): array
    {
        return [
            'user' => $this->updater?->name ?? 'System',
            'ip_address' => $this->updated_ip ?? 'Unknown',
            'user_agent' => $this->updated_user_agent ?? 'Unknown',
            'timestamp' => $this->updated_at?->toISOString(),
        ];
    }
}
```

## Audit Trail Integration

### Comprehensive Audit System

```php
<?php
// app/Models/AuditLog.php

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected function cast(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get changed fields
     */
    public function getChangedFieldsAttribute(): array
    {
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];
        
        return array_keys(array_merge($oldValues, $newValues));
    }
}

// app/Traits/HasAuditTrail.php
trait HasAuditTrail
{
    use HasUserStamps;

    protected static function bootHasAuditTrail(): void
    {
        static::created(function (Model $model) {
            $model->createAuditLog('created', [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $model->createAuditLog('updated', $model->getOriginal(), $model->getAttributes());
        });

        static::deleted(function (Model $model) {
            $model->createAuditLog('deleted', $model->getAttributes(), []);
        });
    }

    /**
     * Create audit log entry
     */
    protected function createAuditLog(string $event, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => static::class,
            'auditable_id' => $this->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }

    /**
     * Get audit logs for this model
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')
            ->orderBy('performed_at', 'desc');
    }
}
```

## Performance Considerations

### Optimized User Stamp Queries

```php
<?php
// Efficient user stamp loading

class UserStampService
{
    /**
     * Load user stamps efficiently for a collection
     */
    public function loadUserStampsForCollection(Collection $models): Collection
    {
        $userIds = $models->flatMap(function ($model) {
            return array_filter([
                $model->created_by,
                $model->updated_by,
                $model->deleted_by,
            ]);
        })->unique();

        $users = User::whereIn('id', $userIds)
            ->select(['id', 'name', 'email'])
            ->get()
            ->keyBy('id');

        return $models->map(function ($model) use ($users) {
            if ($model->created_by) {
                $model->setRelation('creator', $users->get($model->created_by));
            }
            if ($model->updated_by) {
                $model->setRelation('updater', $users->get($model->updated_by));
            }
            if ($model->deleted_by) {
                $model->setRelation('deleter', $users->get($model->deleted_by));
            }
            return $model;
        });
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(User $user, string $period = '30 days'): array
    {
        $startDate = now()->sub($period);

        return [
            'created_count' => $this->getCreatedCount($user, $startDate),
            'updated_count' => $this->getUpdatedCount($user, $startDate),
            'deleted_count' => $this->getDeletedCount($user, $startDate),
            'most_active_models' => $this->getMostActiveModels($user, $startDate),
        ];
    }

    private function getCreatedCount(User $user, $startDate): int
    {
        return UserAction::byUser($user->id)
            ->forAction('created')
            ->where('performed_at', '>=', $startDate)
            ->count();
    }
}
```

## Security and Privacy

### Secure User Stamp Implementation

```php
<?php
// app/Traits/SecureUserStamps.php

trait SecureUserStamps
{
    use HasUserStamps;

    /**
     * Get sanitized creator information
     */
    public function getCreatorDisplayAttribute(): array
    {
        $creator = $this->creator;
        
        if (!$creator) {
            return ['name' => 'System', 'initials' => 'SYS'];
        }

        // Only show full info if user has permission
        if (auth()->user()?->can('view-user-details')) {
            return [
                'name' => $creator->name,
                'email' => $creator->email,
                'initials' => $this->getInitials($creator->name),
            ];
        }

        // Show limited info for privacy
        return [
            'name' => $creator->first_name ?? 'User',
            'initials' => $this->getInitials($creator->name),
        ];
    }

    /**
     * Get user initials
     */
    private function getInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        
        return substr($initials, 0, 2);
    }

    /**
     * Check if current user can view user stamps
     */
    public function canViewUserStamps(): bool
    {
        return auth()->user()?->can('view-user-stamps') ?? false;
    }
}
```

## Testing User Stamps

### Comprehensive User Stamp Testing

```php
<?php
// tests/Unit/Traits/HasUserStampsTest.php

use App\Models\Artist;
use App\Models\User;
use Tests\TestCase;

class HasUserStampsTest extends TestCase
{
    public function test_user_stamps_are_set_on_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $artist = Artist::factory()->create(['name' => 'Test Artist']);

        expect($artist->created_by)->toBe($user->id);
        expect($artist->updated_by)->toBe($user->id);
        expect($artist->creator)->toBeInstanceOf(User::class);
        expect($artist->updater)->toBeInstanceOf(User::class);
    }

    public function test_updated_by_is_set_on_update(): void
    {
        $creator = User::factory()->create();
        $updater = User::factory()->create();

        $this->actingAs($creator);
        $artist = Artist::factory()->create(['name' => 'Test Artist']);

        $this->actingAs($updater);
        $artist->update(['name' => 'Updated Artist']);

        expect($artist->created_by)->toBe($creator->id);
        expect($artist->updated_by)->toBe($updater->id);
    }

    public function test_deleted_by_is_set_on_soft_delete(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $artist = Artist::factory()->create(['name' => 'Test Artist']);
        $artist->delete();

        expect($artist->deleted_by)->toBe($user->id);
        expect($artist->deleter)->toBeInstanceOf(User::class);
    }

    public function test_scopes_work_correctly(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $artist1 = Artist::factory()->create(['name' => 'Artist 1']);

        $this->actingAs($user2);
        $artist2 = Artist::factory()->create(['name' => 'Artist 2']);

        $user1Artists = Artist::createdBy($user1->id)->get();
        $user2Artists = Artist::createdBy($user2->id)->get();

        expect($user1Artists)->toHaveCount(1);
        expect($user2Artists)->toHaveCount(1);
        expect($user1Artists->first()->id)->toBe($artist1->id);
        expect($user2Artists->first()->id)->toBe($artist2->id);
    }
}
```

## Best Practices

### User Stamp Guidelines

1. **Consistent Implementation**: Use the trait across all models that need tracking
2. **Performance**: Eager load user relationships when needed
3. **Privacy**: Respect user privacy when displaying user information
4. **Security**: Validate user permissions before showing detailed user data
5. **Testing**: Write comprehensive tests for user stamp functionality
6. **Audit Trail**: Consider implementing full audit trails for sensitive data

### Migration Best Practices

```php
<?php
// Best practices for user stamp migrations

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            // Add user stamp columns with proper constraints
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete();
                  
            $table->foreignId('updated_by')
                  ->nullable()
                  ->after('created_by')
                  ->constrained('users')
                  ->nullOnDelete();
                  
            $table->foreignId('deleted_by')
                  ->nullable()
                  ->after('updated_by')
                  ->constrained('users')
                  ->nullOnDelete();

            // Add indexes for performance
            $table->index(['created_by', 'created_at']);
            $table->index(['updated_by', 'updated_at']);
        });
    }
};
```

## Navigation

**← Previous:** [Polymorphic Models Guide](060-polymorphic-models.md)
**Next →** [Soft Deletes Guide](080-soft-deletes.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Required Traits Guide](020-required-traits.md) - Essential model traits
- [Validation Rules Guide](040-validation-rules.md) - Data validation strategies

---

*This guide provides comprehensive user stamping patterns for Laravel 12 models in the Chinook application. Each pattern includes security considerations, performance optimization, and testing strategies to ensure robust user tracking throughout the application.*
