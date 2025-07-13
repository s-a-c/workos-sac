# Model Policies Guide

## Table of Contents

- [Overview](#overview)
- [Basic Policy Implementation](#basic-policy-implementation)
- [Advanced Authorization Patterns](#advanced-authorization-patterns)
- [Role-Based Access Control](#role-based-access-control)
- [Resource-Specific Policies](#resource-specific-policies)
- [Policy Testing](#policy-testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive model policy patterns for Laravel 12 in the Chinook application. Policies provide a clean way to organize authorization logic around models, ensuring proper access control and security throughout the application.

**🚀 Key Features:**
- **Granular Permissions**: Fine-grained access control
- **Role-Based Authorization**: Integration with spatie/laravel-permission
- **Resource Protection**: Secure model operations
- **Hierarchical Access**: Complex permission structures
- **WCAG 2.1 AA Compliance**: Accessible authorization feedback

## Basic Policy Implementation

### Artist Policy

```php
<?php
// app/Policies/ArtistPolicy.php

namespace App\Policies;

use App\Models\{Artist, User};
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ArtistPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any artists
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-artists');
    }

    /**
     * Determine whether the user can view the artist
     */
    public function view(User $user, Artist $artist): bool
    {
        // Public artists can be viewed by anyone
        if ($artist->is_public) {
            return true;
        }

        // Check specific permission
        if ($user->hasPermissionTo('view-artists')) {
            return true;
        }

        // Users can view their own created artists
        return $artist->created_by === $user->id;
    }

    /**
     * Determine whether the user can create artists
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-artists');
    }

    /**
     * Determine whether the user can update the artist
     */
    public function update(User $user, Artist $artist): Response
    {
        // Super admins can update anything
        if ($user->hasRole('super-admin')) {
            return Response::allow();
        }

        // Admins can update any artist
        if ($user->hasRole('admin') && $user->hasPermissionTo('update-artists')) {
            return Response::allow();
        }

        // Users can update their own artists
        if ($artist->created_by === $user->id && $user->hasPermissionTo('update-own-artists')) {
            return Response::allow();
        }

        // Managers can update artists in their organization
        if ($user->hasRole('manager') && $this->sameOrganization($user, $artist)) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to update this artist.');
    }

    /**
     * Determine whether the user can delete the artist
     */
    public function delete(User $user, Artist $artist): Response
    {
        // Prevent deletion if artist has albums
        if ($artist->albums()->exists()) {
            return Response::deny('Cannot delete artist with existing albums.');
        }

        // Super admins can delete anything
        if ($user->hasRole('super-admin')) {
            return Response::allow();
        }

        // Admins can delete artists
        if ($user->hasRole('admin') && $user->hasPermissionTo('delete-artists')) {
            return Response::allow();
        }

        // Users can delete their own artists (if no albums)
        if ($artist->created_by === $user->id && $user->hasPermissionTo('delete-own-artists')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to delete this artist.');
    }

    /**
     * Determine whether the user can restore the artist
     */
    public function restore(User $user, Artist $artist): bool
    {
        return $user->hasRole(['super-admin', 'admin']) || 
               $artist->deleted_by === $user->id;
    }

    /**
     * Determine whether the user can permanently delete the artist
     */
    public function forceDelete(User $user, Artist $artist): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can feature the artist
     */
    public function feature(User $user, Artist $artist): bool
    {
        return $user->hasPermissionTo('feature-artists');
    }

    /**
     * Determine whether the user can publish the artist
     */
    public function publish(User $user, Artist $artist): bool
    {
        // Only published artists can be made public
        if (!$artist->is_published) {
            return false;
        }

        return $user->hasPermissionTo('publish-artists') ||
               ($artist->created_by === $user->id && $user->hasPermissionTo('publish-own-artists'));
    }

    /**
     * Check if user and artist are in the same organization
     */
    private function sameOrganization(User $user, Artist $artist): bool
    {
        // Implement organization logic based on your requirements
        return $user->organization_id === $artist->creator?->organization_id;
    }
}
```

### Album Policy

```php
<?php
// app/Policies/AlbumPolicy.php

namespace App\Policies;

use App\Models\{Album, User};
use Illuminate\Auth\Access\Response;

class AlbumPolicy
{
    /**
     * Determine whether the user can view any albums
     */
    public function viewAny(User $user): bool
    {
        return true; // Albums are generally viewable
    }

    /**
     * Determine whether the user can view the album
     */
    public function view(User $user, Album $album): bool
    {
        // Check if user can view the artist
        if (!$user->can('view', $album->artist)) {
            return false;
        }

        // Public albums can be viewed
        if ($album->is_public) {
            return true;
        }

        // Check album-specific permissions
        return $user->hasPermissionTo('view-albums') ||
               $album->created_by === $user->id;
    }

    /**
     * Determine whether the user can create albums
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-albums');
    }

    /**
     * Determine whether the user can update the album
     */
    public function update(User $user, Album $album): Response
    {
        // Must be able to view the album first
        if (!$user->can('view', $album)) {
            return Response::deny('You cannot access this album.');
        }

        // Check if user can update the parent artist
        if (!$user->can('update', $album->artist)) {
            return Response::deny('You do not have permission to update albums for this artist.');
        }

        // Standard update permissions
        if ($user->hasRole(['super-admin', 'admin']) || 
            ($album->created_by === $user->id && $user->hasPermissionTo('update-own-albums'))) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to update this album.');
    }

    /**
     * Determine whether the user can delete the album
     */
    public function delete(User $user, Album $album): Response
    {
        // Check for active purchases
        $recentPurchases = $album->tracks()
            ->join('invoice_lines', 'tracks.id', '=', 'invoice_lines.track_id')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.created_at', '>', now()->subDays(30))
            ->exists();

        if ($recentPurchases) {
            return Response::deny('Cannot delete album with recent purchases.');
        }

        // Standard delete permissions
        if ($user->hasRole(['super-admin', 'admin']) ||
            ($album->created_by === $user->id && $user->hasPermissionTo('delete-own-albums'))) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to delete this album.');
    }

    /**
     * Determine whether the user can add tracks to the album
     */
    public function addTracks(User $user, Album $album): bool
    {
        return $user->can('update', $album) && 
               $user->hasPermissionTo('manage-tracks');
    }

    /**
     * Determine whether the user can reorder tracks in the album
     */
    public function reorderTracks(User $user, Album $album): bool
    {
        return $user->can('update', $album);
    }
}
```

## Advanced Authorization Patterns

### Track Policy with Complex Logic

```php
<?php
// app/Policies/TrackPolicy.php

namespace App\Policies;

use App\Models\{Track, User};
use Illuminate\Auth\Access\Response;

class TrackPolicy
{
    /**
     * Determine whether the user can purchase the track
     */
    public function purchase(User $user, Track $track): Response
    {
        // Track must be published and available
        if (!$track->is_published || !$track->is_available) {
            return Response::deny('This track is not available for purchase.');
        }

        // User must have a valid payment method
        if (!$user->hasValidPaymentMethod()) {
            return Response::deny('Please add a valid payment method to purchase tracks.');
        }

        // Check if user already owns this track
        if ($user->ownedTracks()->where('track_id', $track->id)->exists()) {
            return Response::deny('You already own this track.');
        }

        // Check age restrictions for explicit content
        if ($track->is_explicit && !$user->canAccessExplicitContent()) {
            return Response::deny('You must be 18 or older to purchase explicit content.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can stream the track
     */
    public function stream(User $user, Track $track): Response
    {
        // Free tracks can be streamed by anyone
        if ($track->is_free) {
            return Response::allow();
        }

        // Check if user owns the track
        if ($user->ownedTracks()->where('track_id', $track->id)->exists()) {
            return Response::allow();
        }

        // Check subscription status
        if ($user->hasActiveSubscription()) {
            return Response::allow();
        }

        // Allow preview for non-subscribers
        if ($track->has_preview) {
            return Response::allow('Preview only - subscribe for full access.');
        }

        return Response::deny('Purchase the track or subscribe to stream.');
    }

    /**
     * Determine whether the user can download the track
     */
    public function download(User $user, Track $track): Response
    {
        // Must own the track to download
        if (!$user->ownedTracks()->where('track_id', $track->id)->exists()) {
            return Response::deny('You must purchase this track to download it.');
        }

        // Check download limits
        $downloadCount = $user->trackDownloads()
            ->where('track_id', $track->id)
            ->where('created_at', '>', now()->subDays(30))
            ->count();

        if ($downloadCount >= config('music.max_downloads_per_month', 5)) {
            return Response::deny('Monthly download limit reached for this track.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can add track to playlist
     */
    public function addToPlaylist(User $user, Track $track): bool
    {
        // Must be able to access the track
        return $user->can('view', $track);
    }

    /**
     * Determine whether the user can rate the track
     */
    public function rate(User $user, Track $track): Response
    {
        // Must have listened to at least 30 seconds
        $listenHistory = $user->listenHistory()
            ->where('track_id', $track->id)
            ->where('duration_listened', '>=', 30000) // 30 seconds in ms
            ->exists();

        if (!$listenHistory) {
            return Response::deny('You must listen to at least 30 seconds to rate this track.');
        }

        return Response::allow();
    }
}
```

## Role-Based Access Control

### Hierarchical Role Policy

```php
<?php
// app/Policies/BasePolicy.php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Role hierarchy for permission inheritance
     */
    protected array $roleHierarchy = [
        'super-admin' => ['admin', 'manager', 'editor', 'customer-service', 'user', 'guest'],
        'admin' => ['manager', 'editor', 'customer-service', 'user', 'guest'],
        'manager' => ['editor', 'customer-service', 'user', 'guest'],
        'editor' => ['customer-service', 'user', 'guest'],
        'customer-service' => ['user', 'guest'],
        'user' => ['guest'],
        'guest' => [],
    ];

    /**
     * Check if user has role or higher in hierarchy
     */
    protected function hasRoleOrHigher(User $user, string $role): bool
    {
        $userRoles = $user->getRoleNames()->toArray();
        
        foreach ($userRoles as $userRole) {
            if ($userRole === $role) {
                return true;
            }
            
            if (isset($this->roleHierarchy[$userRole]) && 
                in_array($role, $this->roleHierarchy[$userRole])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if user can perform action based on ownership and permissions
     */
    protected function canPerformAction(
        User $user, 
        $model, 
        string $permission, 
        string $ownPermission = null
    ): bool {
        // Super admin can do anything
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Check general permission
        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        // Check ownership permission if provided
        if ($ownPermission && 
            $model->created_by === $user->id && 
            $user->hasPermissionTo($ownPermission)) {
            return true;
        }

        return false;
    }

    /**
     * Check business hours restrictions
     */
    protected function isWithinBusinessHours(): bool
    {
        $now = now();
        $businessStart = $now->copy()->setTime(9, 0); // 9 AM
        $businessEnd = $now->copy()->setTime(17, 0);   // 5 PM
        
        return $now->between($businessStart, $businessEnd) && 
               $now->isWeekday();
    }

    /**
     * Check if action requires approval
     */
    protected function requiresApproval(User $user, string $action): bool
    {
        $approvalRequired = [
            'delete-artist' => ['editor', 'customer-service'],
            'publish-album' => ['editor'],
            'feature-track' => ['editor', 'customer-service'],
        ];

        $userRoles = $user->getRoleNames()->toArray();
        
        return isset($approvalRequired[$action]) && 
               !empty(array_intersect($userRoles, $approvalRequired[$action]));
    }
}
```

## Resource-Specific Policies

### Playlist Policy with Collaboration

```php
<?php
// app/Policies/PlaylistPolicy.php

namespace App\Policies;

use App\Models\{Playlist, User};
use Illuminate\Auth\Access\Response;

class PlaylistPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view the playlist
     */
    public function view(User $user, Playlist $playlist): bool
    {
        // Public playlists can be viewed by anyone
        if ($playlist->is_public) {
            return true;
        }

        // Owner can always view
        if ($playlist->created_by === $user->id) {
            return true;
        }

        // Collaborators can view
        if ($playlist->collaborators()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Admins can view any playlist
        return $user->hasRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can update the playlist
     */
    public function update(User $user, Playlist $playlist): Response
    {
        // Owner can always update
        if ($playlist->created_by === $user->id) {
            return Response::allow();
        }

        // Check if user is a collaborator with edit permissions
        $collaboration = $playlist->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if ($collaboration && $collaboration->pivot->can_edit) {
            return Response::allow();
        }

        // Admins can update any playlist
        if ($user->hasRole(['super-admin', 'admin'])) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to edit this playlist.');
    }

    /**
     * Determine whether the user can add tracks to the playlist
     */
    public function addTracks(User $user, Playlist $playlist): Response
    {
        // Check basic update permission
        if (!$user->can('update', $playlist)) {
            return Response::deny('You cannot edit this playlist.');
        }

        // Check playlist limits
        if ($playlist->tracks()->count() >= config('music.max_playlist_tracks', 1000)) {
            return Response::deny('Playlist has reached maximum track limit.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can share the playlist
     */
    public function share(User $user, Playlist $playlist): Response
    {
        // Must be able to view the playlist
        if (!$user->can('view', $playlist)) {
            return Response::deny('You cannot access this playlist.');
        }

        // Private playlists can only be shared by owner
        if (!$playlist->is_public && $playlist->created_by !== $user->id) {
            return Response::deny('Only the owner can share private playlists.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can collaborate on the playlist
     */
    public function collaborate(User $user, Playlist $playlist): bool
    {
        return $playlist->is_collaborative && 
               $user->can('view', $playlist);
    }
}
```

## Policy Testing

### Comprehensive Policy Testing

```php
<?php
// tests/Unit/Policies/ArtistPolicyTest.php

use App\Models\{Artist, User};
use App\Policies\ArtistPolicy;
use Tests\TestCase;

class ArtistPolicyTest extends TestCase
{
    private ArtistPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ArtistPolicy();
    }

    public function test_super_admin_can_do_anything(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $artist = Artist::factory()->create();

        expect($this->policy->view($user, $artist))->toBeTrue();
        expect($this->policy->update($user, $artist)->allowed())->toBeTrue();
        expect($this->policy->delete($user, $artist)->allowed())->toBeTrue();
        expect($this->policy->forceDelete($user, $artist))->toBeTrue();
    }

    public function test_user_can_view_own_artist(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['created_by' => $user->id]);

        expect($this->policy->view($user, $artist))->toBeTrue();
    }

    public function test_user_cannot_view_others_private_artist(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create([
            'is_public' => false,
            'created_by' => User::factory()->create()->id,
        ]);

        expect($this->policy->view($user, $artist))->toBeFalse();
    }

    public function test_cannot_delete_artist_with_albums(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo('delete-artists');
        
        $artist = Artist::factory()->create();
        Album::factory()->create(['artist_id' => $artist->id]);

        $response = $this->policy->delete($user, $artist);
        
        expect($response->denied())->toBeTrue();
        expect($response->message())->toContain('Cannot delete artist with existing albums');
    }

    public function test_manager_can_update_same_organization_artist(): void
    {
        $organization = Organization::factory()->create();
        
        $manager = User::factory()->create(['organization_id' => $organization->id]);
        $manager->assignRole('manager');
        
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $artist = Artist::factory()->create(['created_by' => $creator->id]);

        expect($this->policy->update($manager, $artist)->allowed())->toBeTrue();
    }
}
```

### Policy Integration Testing

```php
<?php
// tests/Feature/PolicyIntegrationTest.php

class PolicyIntegrationTest extends TestCase
{
    public function test_artist_policy_integration_with_gates(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user);

        // Test using Gate facade
        expect(Gate::allows('view', $artist))->toBeTrue();
        expect(Gate::allows('update', $artist))->toBeFalse(); // No permission

        // Give permission and test again
        $user->givePermissionTo('update-own-artists');
        expect(Gate::allows('update', $artist))->toBeTrue();
    }

    public function test_policy_with_middleware(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $this->actingAs($user);

        // Test route protection
        $response = $this->get(route('artists.edit', $artist));
        $response->assertStatus(403);

        // Give permission and test again
        $user->givePermissionTo('update-artists');
        $response = $this->get(route('artists.edit', $artist));
        $response->assertStatus(200);
    }
}
```

## Best Practices

### Policy Guidelines

1. **Granular Permissions**: Create specific permissions for different actions
2. **Clear Responses**: Use Response objects with descriptive messages
3. **Performance**: Avoid N+1 queries in policy methods
4. **Testing**: Write comprehensive tests for all policy scenarios
5. **Documentation**: Document complex authorization logic
6. **Consistency**: Use consistent naming and patterns across policies

### Policy Organization

```php
<?php
// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application
     */
    protected $policies = [
        Artist::class => ArtistPolicy::class,
        Album::class => AlbumPolicy::class,
        Track::class => TrackPolicy::class,
        Playlist::class => PlaylistPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define custom gates
        Gate::define('access-admin-panel', function (User $user) {
            return $user->hasRole(['super-admin', 'admin', 'manager']);
        });

        Gate::define('manage-featured-content', function (User $user) {
            return $user->hasPermissionTo('manage-featured-content');
        });

        // Before hook for super admin
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });
    }
}
```

## Navigation

**← Previous:** [Model Observers Guide](100-model-observers.md)
**Next →** [Model Scopes Guide](120-model-scopes.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [RBAC Integration](../setup/030-rbac-integration.md) - Role-based access control
- [Security Configuration](../setup/050-security-configuration.md) - Application security

---

*This guide provides comprehensive model policy patterns for Laravel 12 in the Chinook application. Each pattern includes role-based access control, security considerations, and testing strategies for robust authorization throughout the application.*
