# Table Testing Guide

This guide covers comprehensive testing strategies for Filament table functionality in the Chinook admin panel,
including data display, filtering, searching, sorting, and bulk operations.

## Table of Contents

- [Overview](#overview)
- [Basic Table Testing](#basic-table-testing)
- [Data Display Testing](#data-display-testing)
- [Filtering and Search Testing](#filtering-and-search-testing)
- [Sorting and Pagination Testing](#sorting-and-pagination-testing)
- [Column Configuration Testing](#column-configuration-testing)
- [Bulk Operations Testing](#bulk-operations-testing)
- [Performance Testing](#performance-testing)
- [Accessibility Testing](#accessibility-testing)

## Overview

Table testing ensures that Filament tables display data correctly, provide effective filtering and search capabilities,
and maintain good performance with large datasets. This includes testing all table features and user interactions.

### Testing Objectives

- **Data Accuracy**: Verify correct data display and formatting
- **Functionality**: Test filtering, searching, and sorting capabilities
- **Performance**: Validate table performance with large datasets
- **User Experience**: Test pagination, bulk operations, and interactions
- **Accessibility**: Ensure WCAG 2.1 AA compliance for table elements

## Basic Table Testing

### Table Structure Testing

```php
<?php

namespace Tests\Feature\ChinookAdmin\Tables;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistTableTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');
    }

    public function test_table_displays_correct_columns(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'country' => 'US',
            'formed_year' => 2020,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists');

        $response->assertStatus(200)
            ->assertSee($artist->name)
            ->assertSee($artist->country)
            ->assertSee('2020')
            ->assertSee('Active');
    }

    public function test_table_shows_empty_state(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists');

        $response->assertStatus(200)
            ->assertSee('No artists found');
    }

    public function test_table_respects_per_page_setting(): void
    {
        Artist::factory()->count(30)->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists?per_page=10');

        $response->assertStatus(200);
        
        // Should show pagination controls
        $response->assertSee('Next')
            ->assertSee('Previous');
    }
}
```

### Column Display Testing

```php
public function test_column_formatting(): void
{
    $artist = Artist::factory()->create([
        'name' => 'Test Artist',
        'website' => 'https://example.com',
        'formed_year' => 2020,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200)
        ->assertSee($artist->name)
        ->assertSee('https://example.com')
        ->assertSee('2020')
        ->assertSee('Active'); // Boolean formatted as text
}

public function test_relationship_column_display(): void
{
    $artist = Artist::factory()->create(['name' => 'Test Artist']);
    $album = Album::factory()->for($artist)->create(['title' => 'Test Album']);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/albums');

    $response->assertStatus(200)
        ->assertSee('Test Album')
        ->assertSee('Test Artist'); // Artist name displayed in album table
}

public function test_custom_column_content(): void
{
    $artist = Artist::factory()->create();
    $albums = Album::factory()->count(3)->for($artist)->create();

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200)
        ->assertSee('3 albums'); // Custom albums count column
}
```

## Data Display Testing

### Data Integrity Testing

```php
public function test_soft_deleted_records_hidden_by_default(): void
{
    $activeArtist = Artist::factory()->create(['name' => 'Active Artist']);
    $deletedArtist = Artist::factory()->create(['name' => 'Deleted Artist']);
    $deletedArtist->delete();

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200)
        ->assertSee('Active Artist')
        ->assertDontSee('Deleted Artist');
}

public function test_inactive_records_display_correctly(): void
{
    $activeArtist = Artist::factory()->create([
        'name' => 'Active Artist',
        'is_active' => true,
    ]);
    
    $inactiveArtist = Artist::factory()->create([
        'name' => 'Inactive Artist',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200)
        ->assertSee('Active Artist')
        ->assertSee('Inactive Artist')
        ->assertSee('Active')
        ->assertSee('Inactive');
}

public function test_date_formatting(): void
{
    $artist = Artist::factory()->create([
        'created_at' => '2023-01-15 10:30:00',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200)
        ->assertSee('Jan 15, 2023'); // Formatted date
}
```

### Badge and Status Testing

```php
public function test_status_badges_display(): void
{
    $activeArtist = Artist::factory()->create(['is_active' => true]);
    $inactiveArtist = Artist::factory()->create(['is_active' => false]);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200)
        ->assertSee('badge-success') // Active badge class
        ->assertSee('badge-danger'); // Inactive badge class
}

public function test_category_badges(): void
{
    $artist = Artist::factory()->create();
    $genres = Category::factory()->count(2)->genre()->create();
    $artist->attachCategories($genres->pluck('id')->toArray());

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $response->assertStatus(200);
    
    foreach ($genres as $genre) {
        $response->assertSee($genre->name);
    }
}
```

## Filtering and Search Testing

### Global Search Testing

```php
public function test_global_search_functionality(): void
{
    $artist1 = Artist::factory()->create(['name' => 'Searchable Artist']);
    $artist2 = Artist::factory()->create(['name' => 'Another Band']);
    $artist3 = Artist::factory()->create(['name' => 'Different Group']);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?search=Searchable');

    $response->assertStatus(200)
        ->assertSee('Searchable Artist')
        ->assertDontSee('Another Band')
        ->assertDontSee('Different Group');
}

public function test_search_across_multiple_fields(): void
{
    $artist1 = Artist::factory()->create([
        'name' => 'Test Artist',
        'country' => 'US',
    ]);
    
    $artist2 = Artist::factory()->create([
        'name' => 'Another Artist',
        'country' => 'Canada',
    ]);

    // Search by name
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?search=Test');

    $response->assertStatus(200)
        ->assertSee('Test Artist')
        ->assertDontSee('Another Artist');

    // Search by country
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?search=Canada');

    $response->assertStatus(200)
        ->assertSee('Another Artist')
        ->assertDontSee('Test Artist');
}
```

### Filter Testing

```php
public function test_status_filter(): void
{
    $activeArtist = Artist::factory()->create(['is_active' => true]);
    $inactiveArtist = Artist::factory()->create(['is_active' => false]);

    // Filter for active only
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?filter[is_active]=1');

    $response->assertStatus(200)
        ->assertSee($activeArtist->name)
        ->assertDontSee($inactiveArtist->name);

    // Filter for inactive only
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?filter[is_active]=0');

    $response->assertStatus(200)
        ->assertSee($inactiveArtist->name)
        ->assertDontSee($activeArtist->name);
}

public function test_country_filter(): void
{
    $usArtist = Artist::factory()->create(['country' => 'US']);
    $caArtist = Artist::factory()->create(['country' => 'CA']);
    $gbArtist = Artist::factory()->create(['country' => 'GB']);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?filter[country]=US');

    $response->assertStatus(200)
        ->assertSee($usArtist->name)
        ->assertDontSee($caArtist->name)
        ->assertDontSee($gbArtist->name);
}

public function test_date_range_filter(): void
{
    $oldArtist = Artist::factory()->create(['formed_year' => 1980]);
    $newArtist = Artist::factory()->create(['formed_year' => 2020]);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?filter[formed_year_from]=2000');

    $response->assertStatus(200)
        ->assertSee($newArtist->name)
        ->assertDontSee($oldArtist->name);
}
```

### Advanced Filter Testing

```php
public function test_category_filter(): void
{
    $rockGenre = Category::factory()->genre()->create(['name' => 'Rock']);
    $jazzGenre = Category::factory()->genre()->create(['name' => 'Jazz']);
    
    $rockArtist = Artist::factory()->create();
    $rockArtist->attachCategories([$rockGenre->id]);
    
    $jazzArtist = Artist::factory()->create();
    $jazzArtist->attachCategories([$jazzGenre->id]);

    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/artists?filter[categories]={$rockGenre->id}");

    $response->assertStatus(200)
        ->assertSee($rockArtist->name)
        ->assertDontSee($jazzArtist->name);
}

public function test_multiple_filters_combination(): void
{
    $usRockArtist = Artist::factory()->create(['country' => 'US', 'is_active' => true]);
    $usRockGenre = Category::factory()->genre()->create(['name' => 'Rock']);
    $usRockArtist->attachCategories([$usRockGenre->id]);
    
    $caJazzArtist = Artist::factory()->create(['country' => 'CA', 'is_active' => true]);
    $jazzGenre = Category::factory()->genre()->create(['name' => 'Jazz']);
    $caJazzArtist->attachCategories([$jazzGenre->id]);

    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/artists?filter[country]=US&filter[categories]={$usRockGenre->id}&filter[is_active]=1");

    $response->assertStatus(200)
        ->assertSee($usRockArtist->name)
        ->assertDontSee($caJazzArtist->name);
}
```

## Sorting and Pagination Testing

### Sorting Testing

```php
public function test_name_sorting(): void
{
    Artist::factory()->create(['name' => 'Zebra Band']);
    Artist::factory()->create(['name' => 'Alpha Group']);
    Artist::factory()->create(['name' => 'Beta Artists']);

    // Test ascending sort
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?sort=name&direction=asc');

    $content = $response->getContent();
    $alphaPos = strpos($content, 'Alpha Group');
    $betaPos = strpos($content, 'Beta Artists');
    $zebraPos = strpos($content, 'Zebra Band');

    $this->assertTrue($alphaPos < $betaPos);
    $this->assertTrue($betaPos < $zebraPos);

    // Test descending sort
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?sort=name&direction=desc');

    $content = $response->getContent();
    $alphaPos = strpos($content, 'Alpha Group');
    $betaPos = strpos($content, 'Beta Artists');
    $zebraPos = strpos($content, 'Zebra Band');

    $this->assertTrue($zebraPos < $betaPos);
    $this->assertTrue($betaPos < $alphaPos);
}

public function test_date_sorting(): void
{
    Artist::factory()->create(['formed_year' => 2020, 'name' => 'New Band']);
    Artist::factory()->create(['formed_year' => 1980, 'name' => 'Old Band']);
    Artist::factory()->create(['formed_year' => 2000, 'name' => 'Middle Band']);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?sort=formed_year&direction=asc');

    $content = $response->getContent();
    $oldPos = strpos($content, 'Old Band');
    $middlePos = strpos($content, 'Middle Band');
    $newPos = strpos($content, 'New Band');

    $this->assertTrue($oldPos < $middlePos);
    $this->assertTrue($middlePos < $newPos);
}
```

### Pagination Testing

```php
public function test_pagination_functionality(): void
{
    Artist::factory()->count(25)->create();

    // Test first page
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?page=1&per_page=10');

    $response->assertStatus(200)
        ->assertSee('Next')
        ->assertDontSee('Previous');

    // Test middle page
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?page=2&per_page=10');

    $response->assertStatus(200)
        ->assertSee('Next')
        ->assertSee('Previous');

    // Test last page
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?page=3&per_page=10');

    $response->assertStatus(200)
        ->assertDontSee('Next')
        ->assertSee('Previous');
}

public function test_per_page_options(): void
{
    Artist::factory()->count(50)->create();

    $perPageOptions = [10, 25, 50];

    foreach ($perPageOptions as $perPage) {
        $response = $this->actingAs($this->adminUser)
            ->get("/chinook-admin/artists?per_page={$perPage}");

        $response->assertStatus(200);
        
        if ($perPage < 50) {
            $response->assertSee('Next');
        }
    }
}
```

## Column Configuration Testing

### Column Visibility Testing

```php
public function test_column_visibility_configuration(): void
{
    $this->actingAs($this->adminUser);

    // Test default visible columns
    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    $response->assertSee('Name')
        ->assertSee('Albums Count')
        ->assertSee('Created At')
        ->assertDontSee('Updated At'); // Hidden by default
}

public function test_column_toggle_functionality(): void
{
    $this->actingAs($this->adminUser);

    // Test column toggle via table settings
    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->call('toggleColumn', 'updated_at')
        ->assertSee('Updated At');
}
```

### Column Formatting Testing

```php
public function test_date_column_formatting(): void
{
    $artist = Artist::factory()->create([
        'created_at' => '2024-01-15 10:30:00'
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test date formatting
    $response->assertSee('Jan 15, 2024');
}

public function test_currency_column_formatting(): void
{
    $track = Track::factory()->create([
        'unit_price' => 1.99
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.tracks.index'));

    // Test currency formatting
    $response->assertSee('$1.99');
}

public function test_badge_column_status(): void
{
    $customer = Customer::factory()->create([
        'status' => 'active'
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.customers.index'));

    // Test badge color and text
    $response->assertSee('Active')
        ->assertSee('bg-success'); // Badge styling
}
```

### Custom Column Testing

```php
public function test_custom_calculated_columns(): void
{
    $album = Album::factory()
        ->has(Track::factory()->count(10))
        ->create();

    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.albums.index'));

    // Test calculated tracks count column
    $response->assertSee('10 tracks');
}

public function test_relationship_column_display(): void
{
    $artist = Artist::factory()->create(['name' => 'Test Artist']);
    $album = Album::factory()->create([
        'artist_id' => $artist->id,
        'title' => 'Test Album'
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.albums.index'));

    // Test artist relationship column
    $response->assertSee('Test Artist');
}
```

## Bulk Operations Testing

### Basic Bulk Actions Testing

```php
public function test_bulk_delete_functionality(): void
{
    $artists = Artist::factory()->count(3)->create();

    $this->actingAs($this->adminUser);

    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->set('selectedTableRecords', $artists->pluck('id')->toArray())
        ->callTableBulkAction('delete')
        ->assertSuccessful();

    // Verify soft deletion
    foreach ($artists as $artist) {
        $this->assertSoftDeleted($artist);
    }
}

public function test_bulk_restore_functionality(): void
{
    $artists = Artist::factory()->count(3)->create();

    // Soft delete the artists first
    foreach ($artists as $artist) {
        $artist->delete();
    }

    $this->actingAs($this->adminUser);

    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->set('selectedTableRecords', $artists->pluck('id')->toArray())
        ->callTableBulkAction('restore')
        ->assertSuccessful();

    // Verify restoration
    foreach ($artists as $artist) {
        $this->assertDatabaseHas('artists', [
            'id' => $artist->id,
            'deleted_at' => null
        ]);
    }
}
```

### Custom Bulk Actions Testing

```php
public function test_bulk_category_assignment(): void
{
    $artists = Artist::factory()->count(3)->create();
    $category = Category::factory()->create(['name' => 'Rock']);

    $this->actingAs($this->adminUser);

    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->set('selectedTableRecords', $artists->pluck('id')->toArray())
        ->callTableBulkAction('assignCategory', [
            'category_id' => $category->id
        ])
        ->assertSuccessful();

    // Verify category assignment
    foreach ($artists as $artist) {
        $this->assertTrue($artist->fresh()->categories->contains($category));
    }
}

public function test_bulk_export_functionality(): void
{
    $artists = Artist::factory()->count(5)->create();

    $this->actingAs($this->adminUser);

    $response = Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->set('selectedTableRecords', $artists->pluck('id')->toArray())
        ->callTableBulkAction('export')
        ->assertSuccessful();

    // Verify export file creation
    Storage::disk('exports')->assertExists('artists-export-' . now()->format('Y-m-d') . '.csv');
}
```

### Bulk Action Permissions Testing

```php
public function test_bulk_delete_requires_permission(): void
{
    $user = User::factory()->create();
    $user->assignRole('Editor'); // Role without delete permission

    $artists = Artist::factory()->count(2)->create();

    $this->actingAs($user);

    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->set('selectedTableRecords', $artists->pluck('id')->toArray())
        ->assertTableBulkActionHidden('delete');
}

public function test_bulk_action_confirmation(): void
{
    $artists = Artist::factory()->count(3)->create();

    $this->actingAs($this->adminUser);

    Livewire::test(ArtistResource\Pages\ListArtists::class)
        ->set('selectedTableRecords', $artists->pluck('id')->toArray())
        ->callTableBulkAction('delete')
        ->assertSee('Are you sure you want to delete these records?');
}
```

## Accessibility Testing

### WCAG 2.1 AA Compliance Testing

```php
public function test_table_semantic_structure(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test proper table structure
    $response->assertSee('<table', false)
        ->assertSee('<thead', false)
        ->assertSee('<tbody', false)
        ->assertSee('<th scope="col"', false);
}

public function test_table_headers_accessibility(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test header accessibility attributes
    $response->assertSee('role="columnheader"', false)
        ->assertSee('aria-sort', false);
}

public function test_table_row_accessibility(): void
{
    $artist = Artist::factory()->create(['name' => 'Test Artist']);

    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test row accessibility
    $response->assertSee('role="row"', false)
        ->assertSee('aria-label', false);
}
```

### Keyboard Navigation Testing

```php
public function test_table_keyboard_navigation(): void
{
    $this->actingAs($this->adminUser);

    // Test with browser testing for keyboard interactions
    $this->browse(function (Browser $browser) {
        $browser->visit(route('filament.chinook-admin.resources.artists.index'))
            ->press('Tab') // Navigate to first focusable element
            ->assertFocused('[data-testid="table-search"]')
            ->press('Tab')
            ->assertFocused('[data-testid="table-filter"]');
    });
}

public function test_table_screen_reader_support(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test screen reader attributes
    $response->assertSee('aria-label="Artists table"', false)
        ->assertSee('aria-describedby="table-description"', false)
        ->assertSee('role="table"', false);
}
```

### Color Contrast and Visual Testing

```php
public function test_table_color_contrast(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test high contrast mode compatibility
    $response->assertSee('contrast-more:bg-gray-900', false)
        ->assertSee('contrast-more:text-white', false);
}

public function test_table_responsive_design(): void
{
    $this->actingAs($this->adminUser);

    // Test mobile viewport
    $this->browse(function (Browser $browser) {
        $browser->resize(375, 667) // Mobile viewport
            ->visit(route('filament.chinook-admin.resources.artists.index'))
            ->assertVisible('[data-testid="mobile-table-view"]')
            ->assertHidden('[data-testid="desktop-table-view"]');
    });
}

public function test_table_focus_indicators(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->get(route('filament.chinook-admin.resources.artists.index'));

    // Test focus ring visibility
    $response->assertSee('focus:ring-2', false)
        ->assertSee('focus:ring-primary-500', false)
        ->assertSee('focus:outline-none', false);
}
```

## Performance Testing

### Large Dataset Testing

```php
public function test_table_performance_with_large_dataset(): void
{
    Artist::factory()->count(1000)->create();

    $startTime = microtime(true);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(2.0, $loadTime, "Table took {$loadTime} seconds to load");
}

public function test_search_performance(): void
{
    Artist::factory()->count(500)->create();

    $startTime = microtime(true);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?search=test');

    $endTime = microtime(true);
    $searchTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(1.0, $searchTime, "Search took {$searchTime} seconds");
}

public function test_filter_performance(): void
{
    Artist::factory()->count(500)->create();

    $startTime = microtime(true);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists?filter[is_active]=1');

    $endTime = microtime(true);
    $filterTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(1.0, $filterTime, "Filter took {$filterTime} seconds");
}
```

## Related Documentation

- **[Form Testing](060-form-testing.md)** - Form validation and component testing
- **[Action Testing](080-action-testing.md)** - Custom actions and bulk operations
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[Browser Testing](140-browser-testing.md)** - End-to-end table interaction testing

---

## Navigation

**← Previous:** [Form Testing](060-form-testing.md)

**Next →** [Action Testing](080-action-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
