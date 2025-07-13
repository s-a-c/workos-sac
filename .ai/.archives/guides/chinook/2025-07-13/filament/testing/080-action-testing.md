# Action Testing Guide

This guide covers comprehensive testing strategies for Filament custom actions and bulk operations in the Chinook admin
panel, including single record actions, bulk operations, and custom workflows.

## Table of Contents

- [Overview](#overview)
- [Single Record Actions](#single-record-actions)
- [Bulk Operations Testing](#bulk-operations-testing)
- [Custom Action Testing](#custom-action-testing)
- [Action Authorization Testing](#action-authorization-testing)
- [Action Validation Testing](#action-validation-testing)
- [Modal and Form Actions](#modal-and-form-actions)
- [Performance Testing](#performance-testing)
- [Error Handling Testing](#error-handling-testing)

## Overview

Action testing ensures that all custom actions and bulk operations work correctly, maintain proper authorization, and
provide appropriate user feedback. This includes testing action execution, validation, and error handling.

### Testing Objectives

- **Functionality**: Verify all actions execute correctly
- **Authorization**: Ensure proper permission checks
- **Validation**: Test action input validation and constraints
- **Performance**: Validate action performance with large datasets
- **User Experience**: Test action feedback and error handling

## Single Record Actions

### Basic Action Testing

```php
<?php

namespace Tests\Feature\ChinookAdmin\Actions;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $editorUser;
    protected User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');
        
        $this->editorUser = User::factory()->create();
        $this->editorUser->assignRole('Editor');
        
        $this->guestUser = User::factory()->create();
        $this->guestUser->assignRole('Guest');
    }

    public function test_activate_artist_action(): void
    {
        $artist = Artist::factory()->create(['is_active' => false]);

        $this->actingAs($this->adminUser)
            ->post("/chinook-admin/artists/{$artist->id}/actions/activate")
            ->assertRedirect()
            ->assertSessionHas('success');

        $artist->refresh();
        $this->assertTrue($artist->is_active);
    }

    public function test_deactivate_artist_action(): void
    {
        $artist = Artist::factory()->create(['is_active' => true]);

        $this->actingAs($this->adminUser)
            ->post("/chinook-admin/artists/{$artist->id}/actions/deactivate")
            ->assertRedirect()
            ->assertSessionHas('success');

        $artist->refresh();
        $this->assertFalse($artist->is_active);
    }

    public function test_duplicate_artist_action(): void
    {
        $originalArtist = Artist::factory()->create([
            'name' => 'Original Artist',
            'country' => 'US',
            'biography' => 'Original biography',
        ]);

        $this->actingAs($this->adminUser)
            ->post("/chinook-admin/artists/{$originalArtist->id}/actions/duplicate")
            ->assertRedirect()
            ->assertSessionHas('success');

        $duplicatedArtist = Artist::where('name', 'Original Artist (Copy)')->first();
        $this->assertNotNull($duplicatedArtist);
        $this->assertEquals($originalArtist->country, $duplicatedArtist->country);
        $this->assertEquals($originalArtist->biography, $duplicatedArtist->biography);
        $this->assertNotEquals($originalArtist->id, $duplicatedArtist->id);
    }
}
```

### Action State Testing

```php
public function test_action_availability_based_on_state(): void
{
    $activeArtist = Artist::factory()->create(['is_active' => true]);
    $inactiveArtist = Artist::factory()->create(['is_active' => false]);

    // Active artist should show deactivate action
    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/artists/{$activeArtist->id}");

    $response->assertStatus(200)
        ->assertSee('Deactivate')
        ->assertDontSee('Activate');

    // Inactive artist should show activate action
    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/artists/{$inactiveArtist->id}");

    $response->assertStatus(200)
        ->assertSee('Activate')
        ->assertDontSee('Deactivate');
}

public function test_action_with_confirmation(): void
{
    $artist = Artist::factory()->create();

    // Test that delete action requires confirmation
    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/artists/{$artist->id}");

    $response->assertStatus(200)
        ->assertSee('data-confirm="Are you sure you want to delete this artist?"');
}
```

## Bulk Operations Testing

### Bulk Action Testing

```php
public function test_bulk_activate_artists(): void
{
    $artists = Artist::factory()->count(3)->create(['is_active' => false]);
    $artistIds = $artists->pluck('id')->toArray();

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/activate', [
            'records' => $artistIds,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    foreach ($artists as $artist) {
        $artist->refresh();
        $this->assertTrue($artist->is_active);
    }
}

public function test_bulk_deactivate_artists(): void
{
    $artists = Artist::factory()->count(3)->create(['is_active' => true]);
    $artistIds = $artists->pluck('id')->toArray();

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/deactivate', [
            'records' => $artistIds,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    foreach ($artists as $artist) {
        $artist->refresh();
        $this->assertFalse($artist->is_active);
    }
}

public function test_bulk_delete_artists(): void
{
    $artists = Artist::factory()->count(3)->create();
    $artistIds = $artists->pluck('id')->toArray();

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/delete', [
            'records' => $artistIds,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    foreach ($artists as $artist) {
        $this->assertSoftDeleted($artist);
    }
}

public function test_bulk_assign_categories(): void
{
    $artists = Artist::factory()->count(3)->create();
    $category = Category::factory()->genre()->create();
    $artistIds = $artists->pluck('id')->toArray();

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/assign-categories', [
            'records' => $artistIds,
            'categories' => [$category->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    foreach ($artists as $artist) {
        $artist->refresh();
        $this->assertTrue($artist->categories->contains($category));
    }
}
```

### Bulk Operation Validation

```php
public function test_bulk_action_requires_selection(): void
{
    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/activate', [
            'records' => [],
        ])
        ->assertSessionHasErrors(['records']);
}

public function test_bulk_action_with_invalid_records(): void
{
    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/activate', [
            'records' => [99999], // Non-existent ID
        ])
        ->assertSessionHasErrors();
}

public function test_bulk_action_partial_failure_handling(): void
{
    $validArtist = Artist::factory()->create(['is_active' => false]);
    $artistWithAlbums = Artist::factory()->create(['is_active' => false]);
    
    // Create albums for one artist to prevent deletion
    Album::factory()->count(2)->for($artistWithAlbums)->create();

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/delete', [
            'records' => [$validArtist->id, $artistWithAlbums->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('warning'); // Partial success message

    $this->assertSoftDeleted($validArtist);
    $this->assertNotSoftDeleted($artistWithAlbums);
}
```

## Custom Action Testing

### Complex Action Testing

```php
public function test_export_artist_data_action(): void
{
    $artists = Artist::factory()->count(5)->create();

    $response = $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/actions/export', [
            'format' => 'csv',
            'include_albums' => true,
        ]);

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv')
        ->assertHeader('Content-Disposition', 'attachment; filename="artists.csv"');

    $content = $response->getContent();
    $this->assertStringContainsString('Name,Country,Albums Count', $content);
}

public function test_import_artist_data_action(): void
{
    Storage::fake('local');
    
    $csvContent = "name,country,formed_year\nTest Artist 1,US,2020\nTest Artist 2,CA,2021";
    $file = UploadedFile::fake()->createWithContent('artists.csv', $csvContent);

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/actions/import', [
            'file' => $file,
            'update_existing' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('artists', ['name' => 'Test Artist 1', 'country' => 'US']);
    $this->assertDatabaseHas('artists', ['name' => 'Test Artist 2', 'country' => 'CA']);
}

public function test_sync_artist_metadata_action(): void
{
    $artist = Artist::factory()->create([
        'name' => 'Test Artist',
        'website' => 'https://example.com',
    ]);

    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/sync-metadata")
        ->assertRedirect()
        ->assertSessionHas('success');

    $artist->refresh();
    // Verify that metadata was updated (mock external API response)
    $this->assertNotNull($artist->metadata);
}
```

### Action with External Services

```php
public function test_send_newsletter_action(): void
{
    Mail::fake();
    
    $artists = Artist::factory()->count(3)->create();
    $artistIds = $artists->pluck('id')->toArray();

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/send-newsletter', [
            'records' => $artistIds,
            'subject' => 'Test Newsletter',
            'message' => 'Test message content',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertQueued(ArtistNewsletterMail::class, 3);
}

public function test_social_media_sync_action(): void
{
    Http::fake([
        'api.facebook.com/*' => Http::response(['success' => true]),
        'api.twitter.com/*' => Http::response(['success' => true]),
    ]);

    $artist = Artist::factory()->create([
        'social_links' => [
            ['platform' => 'facebook', 'url' => 'https://facebook.com/artist'],
            ['platform' => 'twitter', 'url' => 'https://twitter.com/artist'],
        ],
    ]);

    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/sync-social-media")
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.facebook.com');
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.twitter.com');
    });
}
```

## Action Authorization Testing

### Permission-Based Action Testing

```php
public function test_admin_can_perform_all_actions(): void
{
    $artist = Artist::factory()->create();

    $actions = ['activate', 'deactivate', 'duplicate', 'delete'];

    foreach ($actions as $action) {
        $response = $this->actingAs($this->adminUser)
            ->post("/chinook-admin/artists/{$artist->id}/actions/{$action}");

        $this->assertNotEquals(403, $response->getStatusCode(), 
            "Admin should be able to perform {$action} action");
    }
}

public function test_editor_action_restrictions(): void
{
    $artist = Artist::factory()->create();

    // Editor can activate/deactivate
    $this->actingAs($this->editorUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/activate")
        ->assertRedirect();

    // Editor cannot delete
    $this->actingAs($this->editorUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/delete")
        ->assertStatus(403);
}

public function test_guest_cannot_perform_actions(): void
{
    $artist = Artist::factory()->create();

    $actions = ['activate', 'deactivate', 'duplicate', 'delete'];

    foreach ($actions as $action) {
        $this->actingAs($this->guestUser)
            ->post("/chinook-admin/artists/{$artist->id}/actions/{$action}")
            ->assertStatus(403);
    }
}

public function test_ownership_based_action_access(): void
{
    $user1 = User::factory()->create();
    $user1->assignRole('Editor');
    
    $user2 = User::factory()->create();
    $user2->assignRole('Editor');

    $artist = Artist::factory()->create(['created_by' => $user1->id]);

    // Creator can perform action
    $this->actingAs($user1)
        ->post("/chinook-admin/artists/{$artist->id}/actions/duplicate")
        ->assertRedirect();

    // Other user cannot perform action
    $this->actingAs($user2)
        ->post("/chinook-admin/artists/{$artist->id}/actions/duplicate")
        ->assertStatus(403);
}
```

## Action Validation Testing

### Input Validation Testing

```php
public function test_action_form_validation(): void
{
    $artist = Artist::factory()->create();

    // Test required fields
    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/send-email", [
            'subject' => '',
            'message' => '',
        ])
        ->assertSessionHasErrors(['subject', 'message']);

    // Test valid input
    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/send-email", [
            'subject' => 'Test Subject',
            'message' => 'Test message content',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

public function test_bulk_action_validation(): void
{
    $artists = Artist::factory()->count(3)->create();
    $category = Category::factory()->genre()->create();

    // Test missing category selection
    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/assign-categories', [
            'records' => $artists->pluck('id')->toArray(),
            'categories' => [],
        ])
        ->assertSessionHasErrors(['categories']);

    // Test invalid category ID
    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/assign-categories', [
            'records' => $artists->pluck('id')->toArray(),
            'categories' => [99999],
        ])
        ->assertSessionHasErrors(['categories']);
}
```

## Modal and Form Actions

### Modal Action Testing

```php
public function test_modal_action_display(): void
{
    $artist = Artist::factory()->create();

    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/artists/{$artist->id}/actions/send-email/form");

    $response->assertStatus(200)
        ->assertSee('Send Email')
        ->assertSee('name="subject"')
        ->assertSee('name="message"')
        ->assertSee('type="submit"');
}

public function test_modal_action_submission(): void
{
    Mail::fake();
    
    $artist = Artist::factory()->create();

    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/send-email", [
            'subject' => 'Test Email',
            'message' => 'Test message content',
            'send_copy' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertQueued(ArtistEmailMail::class);
}
```

## Performance Testing

### Action Performance Testing

```php
public function test_bulk_action_performance(): void
{
    $artists = Artist::factory()->count(100)->create(['is_active' => false]);
    $artistIds = $artists->pluck('id')->toArray();

    $startTime = microtime(true);

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/bulk-actions/activate', [
            'records' => $artistIds,
        ])
        ->assertRedirect();

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    $this->assertLessThan(5.0, $executionTime, 
        "Bulk action took {$executionTime} seconds for 100 records");
}

public function test_export_action_performance(): void
{
    Artist::factory()->count(1000)->create();

    $startTime = microtime(true);

    $response = $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists/actions/export', [
            'format' => 'csv',
        ]);

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(10.0, $executionTime, 
        "Export took {$executionTime} seconds for 1000 records");
}
```

## Error Handling Testing

### Action Error Testing

```php
public function test_action_handles_database_errors(): void
{
    $artist = Artist::factory()->create();
    
    // Simulate database error by creating constraint violation
    Album::factory()->for($artist)->create();

    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/delete")
        ->assertRedirect()
        ->assertSessionHas('error', 'Cannot delete artist with existing albums');

    $this->assertNotSoftDeleted($artist);
}

public function test_action_handles_external_service_errors(): void
{
    Http::fake([
        'api.external-service.com/*' => Http::response([], 500),
    ]);

    $artist = Artist::factory()->create();

    $this->actingAs($this->adminUser)
        ->post("/chinook-admin/artists/{$artist->id}/actions/sync-external-data")
        ->assertRedirect()
        ->assertSessionHas('error', 'External service unavailable');
}
```

## Related Documentation

- **[Table Testing](070-table-testing.md)** - Table functionality testing
- **[Authorization Testing](090-auth-testing.md)** - Permission and access control testing
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[Browser Testing](140-browser-testing.md)** - End-to-end action testing

---

## Navigation

**← Previous:** [Table Testing](070-table-testing.md)

**Next →** [Authentication Testing](090-auth-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
