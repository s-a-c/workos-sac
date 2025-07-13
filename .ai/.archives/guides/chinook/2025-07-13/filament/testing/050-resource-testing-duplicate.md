# Resource Testing Guide

This guide covers comprehensive testing strategies for Filament resources in the Chinook admin panel, including CRUD operations, form validation, table functionality, and relationship management.

## Table of Contents

- [Overview](#overview)
- [Basic Resource Testing](#basic-resource-testing)
- [Form Testing](#form-testing)
- [Table Testing](#table-testing)
- [Relationship Testing](#relationship-testing)
- [Permission Testing](#permission-testing)
- [Validation Testing](#validation-testing)
- [Best Practices](#best-practices)

## Overview

Resource testing ensures that all Filament resources function correctly, maintain proper security, and provide consistent user experiences. This includes testing CRUD operations, form validation, table functionality, and access control.

### Testing Objectives

- **Functionality**: Test all CRUD operations work correctly
- **Security**: Verify proper access control and permissions
- **Validation**: Ensure form validation rules are enforced
- **Performance**: Test resource performance under load

## Basic Resource Testing

### Resource Access Testing

```php
<?php

namespace Tests\Feature\Filament\Resources;

use App\Filament\Resources\ArtistResource;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArtistResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $editorUser;
    protected User $guestUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Super Admin');
        
        $this->editorUser = User::factory()->create();
        $this->editorUser->assignRole('Editor');
        
        $this->guestUser = User::factory()->create();
        $this->guestUser->assignRole('Guest');
    }

    public function test_admin_can_access_artist_resource(): void
    {
        $this->actingAs($this->adminUser);
        
        $response = $this->get(ArtistResource::getUrl('index'));
        
        $response->assertSuccessful();
    }

    public function test_editor_can_access_artist_resource(): void
    {
        $this->actingAs($this->editorUser);
        
        $response = $this->get(ArtistResource::getUrl('index'));
        
        $response->assertSuccessful();
    }

    public function test_guest_cannot_access_artist_resource(): void
    {
        $this->actingAs($this->guestUser);
        
        $response = $this->get(ArtistResource::getUrl('index'));
        
        $response->assertForbidden();
    }
}
```

### CRUD Operations Testing

```php
public function test_can_create_artist(): void
{
    $this->actingAs($this->adminUser);
    
    $artistData = [
        'name' => 'Test Artist',
        'biography' => 'Test biography',
        'country' => 'US',
        'website' => 'https://testartist.com',
    ];
    
    Livewire::test(ArtistResource\Pages\CreateArtist::class)
        ->fillForm($artistData)
        ->call('create')
        ->assertHasNoFormErrors();
    
    $this->assertDatabaseHas('artists', [
        'name' => 'Test Artist',
        'biography' => 'Test biography',
    ]);
}

public function test_can_edit_artist(): void
{
    $artist = Artist::factory()->create([
        'name' => 'Original Name'
    ]);
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\EditArtist::class, [
        'record' => $artist->getRouteKey(),
    ])
        ->fillForm([
            'name' => 'Updated Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
    
    $this->assertDatabaseHas('artists', [
        'id' => $artist->id,
        'name' => 'Updated Name',
    ]);
}

public function test_can_delete_artist(): void
{
    $artist = Artist::factory()->create();
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->callTableAction('delete', $artist);
    
    $this->assertSoftDeleted($artist);
}
```

## Form Testing

### Form Validation Testing

```php
public function test_artist_name_is_required(): void
{
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\CreateArtist::class)
        ->fillForm([
            'name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
}

public function test_artist_name_must_be_unique(): void
{
    $existingArtist = Artist::factory()->create(['name' => 'Existing Artist']);
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\CreateArtist::class)
        ->fillForm([
            'name' => 'Existing Artist',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
}

public function test_website_must_be_valid_url(): void
{
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\CreateArtist::class)
        ->fillForm([
            'name' => 'Test Artist',
            'website' => 'invalid-url',
        ])
        ->call('create')
        ->assertHasFormErrors(['website' => 'url']);
}
```

## Table Testing

### Table Display Testing

```php
public function test_artists_table_displays_correctly(): void
{
    $artists = Artist::factory()->count(3)->create();
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->assertCanSeeTableRecords($artists);
}

public function test_artists_table_search_works(): void
{
    $artist1 = Artist::factory()->create(['name' => 'Rock Artist']);
    $artist2 = Artist::factory()->create(['name' => 'Jazz Artist']);
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->searchTable('Rock')
        ->assertCanSeeTableRecords([$artist1])
        ->assertCanNotSeeTableRecords([$artist2]);
}

public function test_artists_table_sorting_works(): void
{
    $artistA = Artist::factory()->create(['name' => 'A Artist']);
    $artistZ = Artist::factory()->create(['name' => 'Z Artist']);
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->sortTable('name')
        ->assertCanSeeTableRecords([$artistA, $artistZ], inOrder: true)
        ->sortTable('name', 'desc')
        ->assertCanSeeTableRecords([$artistZ, $artistA], inOrder: true);
}
```

## Relationship Testing

### Relationship Display Testing

```php
public function test_artist_albums_relationship_displays(): void
{
    $artist = Artist::factory()
        ->has(Album::factory()->count(3))
        ->create();
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ViewArtist::class, [
        'record' => $artist->getRouteKey(),
    ])
        ->assertSee($artist->albums->first()->title);
}

public function test_can_attach_album_to_artist(): void
{
    $artist = Artist::factory()->create();
    $album = Album::factory()->create(['artist_id' => null]);
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\RelationManagers\AlbumsRelationManager::class, [
        'ownerRecord' => $artist,
        'pageClass' => ArtistResource\Pages\EditArtist::class,
    ])
        ->callTableAction('attach', null, [
            'recordId' => $album->id,
        ]);
    
    $this->assertDatabaseHas('albums', [
        'id' => $album->id,
        'artist_id' => $artist->id,
    ]);
}
```

## Permission Testing

### Role-Based Access Testing

```php
public function test_editor_cannot_delete_artists(): void
{
    $artist = Artist::factory()->create();
    
    $this->actingAs($this->editorUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->assertTableActionHidden('delete', $artist);
}

public function test_admin_can_delete_artists(): void
{
    $artist = Artist::factory()->create();
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->assertTableActionVisible('delete', $artist);
}
```

## Validation Testing

### Business Rule Testing

```php
public function test_cannot_delete_artist_with_albums(): void
{
    $artist = Artist::factory()
        ->has(Album::factory()->count(2))
        ->create();
    
    $this->actingAs($this->adminUser);
    
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->callTableAction('delete', $artist)
        ->assertNotified('Cannot delete artist with existing albums');
    
    $this->assertNotSoftDeleted($artist);
}
```

## Best Practices

### Testing Organization

1. **Group Related Tests**: Organize tests by functionality (CRUD, validation, permissions)
2. **Use Factories**: Leverage model factories for consistent test data
3. **Test Edge Cases**: Include boundary conditions and error scenarios
4. **Mock External Services**: Use HTTP fakes for external API calls

### Performance Considerations

1. **Database Transactions**: Use RefreshDatabase for clean test state
2. **Minimal Data**: Create only necessary test data
3. **Parallel Testing**: Structure tests for parallel execution

---

## Related Documentation

- **[Form Testing](060-form-testing.md)** - Detailed form testing strategies
- **[Table Testing](070-table-testing.md)** - Comprehensive table testing
- **[RBAC Testing](100-rbac-testing.md)** - Role-based access control testing
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization

---

## Navigation

**← Previous:** [CI Integration](040-ci-integration.md)

**Next →** [Form Testing](060-form-testing.md)
