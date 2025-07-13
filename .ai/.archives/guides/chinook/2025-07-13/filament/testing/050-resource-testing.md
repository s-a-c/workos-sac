# Resource Testing Guide

This guide covers comprehensive testing strategies for Filament resources in the Chinook admin panel, including CRUD
operations, authorization, validation, and advanced features.

## Table of Contents

- [Overview](#overview)
- [Test Structure](#test-structure)
- [CRUD Operation Testing](#crud-operation-testing)
- [Related Documentation](#related-documentation)
- [Navigation](#navigation)

## Overview

Resource testing ensures that all Filament resources function correctly, maintain proper security, and provide the
expected user experience. This includes testing all CRUD operations, form validation, table functionality, and access
control.

### Testing Objectives

- **Functionality**: Verify all CRUD operations work correctly
- **Security**: Ensure proper authorization and access control
- **Validation**: Test form validation rules and error handling
- **Performance**: Validate resource performance under load
- **User Experience**: Test filtering, searching, and sorting

## Test Structure

### Base Resource Test Class

Create a base class for common resource testing functionality:

```php
<?php

namespace Tests\Feature\ChinookAdmin\Resources;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ResourceTestCase extends TestCase
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

    /**
     * Get the resource URL path.
     */
    abstract protected function getResourcePath(): string;

    /**
     * Get valid data for creating a record.
     */
    abstract protected function getValidCreateData(): array;

    /**
     * Get valid data for updating a record.
     */
    abstract protected function getValidUpdateData(): array;

    /**
     * Get invalid data for testing validation.
     */
    abstract protected function getInvalidData(): array;

    /**
     * Test that admin can access the resource index.
     */
    public function test_admin_can_access_index(): void
    {
        $this->actingAs($this->adminUser)
            ->get("/chinook-admin/{$this->getResourcePath()}")
            ->assertStatus(200);
    }

    /**
     * Test that unauthorized users cannot access the resource.
     */
    public function test_unauthorized_user_cannot_access_resource(): void
    {
        $this->actingAs($this->guestUser)
            ->get("/chinook-admin/{$this->getResourcePath()}")
            ->assertStatus(403);
    }
}
```

### Artist Resource Test Example

```php
<?php

namespace Tests\Feature\ChinookAdmin\Resources;

use App\Models\Artist;
use App\Models\Album;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ArtistResourceTest extends ResourceTestCase
{
    protected function getResourcePath(): string
    {
        return 'artists';
    }

    protected function getValidCreateData(): array
    {
        return [
            'name' => 'Test Artist',
            'country' => 'US',
            'biography' => 'Test biography',
            'website' => 'https://example.com',
            'formed_year' => 2020,
            'is_active' => true,
            'social_links' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com/artist'],
                ['platform' => 'twitter', 'url' => 'https://twitter.com/artist'],
            ],
        ];
    }

    protected function getValidUpdateData(): array
    {
        return [
            'name' => 'Updated Artist Name',
            'country' => 'CA',
            'biography' => 'Updated biography',
        ];
    }

    protected function getInvalidData(): array
    {
        return [
            'name' => '', // Required field
            'website' => 'invalid-url',
            'formed_year' => 1800, // Too early
            'social_links' => [
                ['platform' => '', 'url' => 'https://example.com'], // Missing platform
            ],
        ];
    }
}
```

## CRUD Operation Testing

### Create Operation Testing

```php
class ArtistResourceTest extends ResourceTestCase
{
    public function test_admin_can_create_artist(): void
    {
        $artistData = $this->getValidCreateData();

        $this->actingAs($this->adminUser)
            ->post("/chinook-admin/{$this->getResourcePath()}", $artistData)
            ->assertRedirect();

        $this->assertDatabaseHas('artists', [
            'name' => 'Test Artist',
            'country' => 'US',
        ]);

        $artist = Artist::where('name', 'Test Artist')->first();
        $this->assertNotNull($artist->public_id);
        $this->assertNotNull($artist->slug);
        $this->assertEquals($this->adminUser->id, $artist->created_by);
    }

    public function test_editor_can_create_artist(): void
    {
        $artistData = $this->getValidCreateData();

        $this->actingAs($this->editorUser)
            ->post("/chinook-admin/{$this->getResourcePath()}", $artistData)
            ->assertRedirect();

        $this->assertDatabaseHas('artists', [
            'name' => 'Test Artist',
        ]);
    }

    public function test_guest_cannot_create_artist(): void
    {
        $artistData = $this->getValidCreateData();

        $this->actingAs($this->guestUser)
            ->post("/chinook-admin/{$this->getResourcePath()}", $artistData)
            ->assertStatus(403);

        $this->assertDatabaseMissing('artists', [
            'name' => 'Test Artist',
        ]);
    }

    public function test_create_artist_with_media(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('artist.jpg');

        $artistData = array_merge($this->getValidCreateData(), [
            'profile_image' => $file,
        ]);

        $this->actingAs($this->adminUser)
            ->post("/chinook-admin/{$this->getResourcePath()}", $artistData)
            ->assertRedirect();

        $artist = Artist::where('name', 'Test Artist')->first();
        $this->assertTrue($artist->hasMedia('profile_images'));
    }
}
```

### Read Operation Testing

```php
public function test_admin_can_view_artist_details(): void
{
    $artist = Artist::factory()->create();

    $this->actingAs($this->adminUser)
        ->get("/chinook-admin/{$this->getResourcePath()}/{$artist->id}")
        ->assertStatus(200)
        ->assertSee($artist->name)
        ->assertSee($artist->country);
}

public function test_artist_index_displays_correct_data(): void
{
    $artists = Artist::factory()->count(3)->create();

    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/{$this->getResourcePath()}");

    $response->assertStatus(200);
    
    foreach ($artists as $artist) {
        $response->assertSee($artist->name);
    }
}

public function test_artist_index_pagination(): void
{
    Artist::factory()->count(30)->create();

    $this->actingAs($this->adminUser)
        ->get("/chinook-admin/{$this->getResourcePath()}")
        ->assertStatus(200)
        ->assertSee('Next')
        ->assertSee('Previous');
}
```

### Update Operation Testing

```php
public function test_admin_can_update_artist(): void
{
    $artist = Artist::factory()->create();
    $updateData = $this->getValidUpdateData();

    $this->actingAs($this->adminUser)
        ->put("/chinook-admin/{$this->getResourcePath()}/{$artist->id}", $updateData)
        ->assertRedirect();

    $this->assertDatabaseHas('artists', [
        'id' => $artist->id,
        'name' => 'Updated Artist Name',
        'country' => 'CA',
    ]);

    $artist->refresh();
    $this->assertEquals($this->adminUser->id, $artist->updated_by);
}

public function test_editor_can_update_artist(): void
{
    $artist = Artist::factory()->create();
    $updateData = $this->getValidUpdateData();

    $this->actingAs($this->editorUser)
        ->put("/chinook-admin/{$this->getResourcePath()}/{$artist->id}", $updateData)
        ->assertRedirect();

    $this->assertDatabaseHas('artists', [
        'id' => $artist->id,
        'name' => 'Updated Artist Name',
    ]);
}

public function test_guest_cannot_update_artist(): void
{
    $artist = Artist::factory()->create();
    $updateData = $this->getValidUpdateData();

    $this->actingAs($this->guestUser)
        ->put("/chinook-admin/{$this->getResourcePath()}/{$artist->id}", $updateData)
        ->assertStatus(403);

    $this->assertDatabaseHas('artists', [
        'id' => $artist->id,
        'name' => $artist->name, // Original name unchanged
    ]);
}
```

## Related Documentation

- **[Testing Strategy](010-testing-strategy.md)** - Overall testing approach
- **[Form Testing](060-form-testing.md)** - Detailed form validation testing
- **[Authorization Testing](090-auth-testing.md)** - Permission and access control testing
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization

---

## Navigation

**← Previous:** [Continuous Integration](040-ci-integration.md)

**Next →** [Form Testing](060-form-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
