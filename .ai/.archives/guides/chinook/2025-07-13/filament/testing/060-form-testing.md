# Form Testing Guide

This guide covers comprehensive form validation and submission testing for Filament resources in the Chinook admin
panel, including field validation, custom components, and user interaction testing.

## Table of Contents

- [Overview](#overview)
- [Form Validation Testing](#form-validation-testing)
- [Component Testing](#component-testing)
- [Field Interaction Testing](#field-interaction-testing)
- [Custom Component Testing](#custom-component-testing)
- [File Upload Testing](#file-upload-testing)
- [Relationship Field Testing](#relationship-field-testing)
- [Performance Testing](#performance-testing)

## Overview

Form testing ensures that all form components work correctly, validate input properly, and provide appropriate user
feedback. This includes testing field validation, component interactions, and form submission workflows.

### Testing Objectives

- **Validation**: Verify all validation rules work correctly
- **User Experience**: Test form interactions and feedback
- **Security**: Ensure proper input sanitization and validation
- **Performance**: Validate form performance with large datasets
- **Accessibility**: Test form accessibility compliance

## Form Validation Testing

### Basic Validation Testing

```php
<?php

namespace Tests\Feature\ChinookAdmin\Forms;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistFormTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');
    }

    it('requires artist name', function () {
        $this->actingAs($this->adminUser)
            ->post('/chinook-admin/artists', [
                'name' => '',
                'country' => 'US',
            ])
            ->assertSessionHasErrors(['name']);
    });

    it('validates artist name uniqueness', function () {
        Artist::factory()->create(['name' => 'Existing Artist']);

        $this->actingAs($this->adminUser)
            ->post('/chinook-admin/artists', [
                'name' => 'Existing Artist',
                'country' => 'US',
            ])
            ->assertSessionHasErrors(['name']);
    });

    public function test_artist_website_must_be_valid_url(): void
    {
        $this->actingAs($this->adminUser)
            ->post('/chinook-admin/artists', [
                'name' => 'Test Artist',
                'website' => 'invalid-url',
                'country' => 'US',
            ])
            ->assertSessionHasErrors(['website']);
    }

    public function test_artist_formed_year_must_be_valid_range(): void
    {
        $this->actingAs($this->adminUser)
            ->post('/chinook-admin/artists', [
                'name' => 'Test Artist',
                'formed_year' => 1800, // Too early
                'country' => 'US',
            ])
            ->assertSessionHasErrors(['formed_year']);

        $this->actingAs($this->adminUser)
            ->post('/chinook-admin/artists', [
                'name' => 'Test Artist 2',
                'formed_year' => 2050, // Too late
                'country' => 'US',
            ])
            ->assertSessionHasErrors(['formed_year']);
    }
}
```

### Complex Validation Testing

```php
public function test_social_links_validation(): void
{
    $invalidSocialLinks = [
        'name' => 'Test Artist',
        'social_links' => [
            ['platform' => 'facebook', 'url' => 'invalid-url'],
            ['platform' => '', 'url' => 'https://example.com'],
            ['platform' => 'twitter', 'url' => ''],
        ],
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $invalidSocialLinks)
        ->assertSessionHasErrors([
            'social_links.0.url',
            'social_links.1.platform',
            'social_links.2.url',
        ]);
}

public function test_biography_length_validation(): void
{
    $longBiography = str_repeat('a', 5001); // Exceeds 5000 character limit

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', [
            'name' => 'Test Artist',
            'biography' => $longBiography,
            'country' => 'US',
        ])
        ->assertSessionHasErrors(['biography']);
}
```

## Component Testing

### Select Component Testing

```php
public function test_country_select_component(): void
{
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists/create');

    $response->assertStatus(200)
        ->assertSee('name="country"')
        ->assertSee('option value="US"')
        ->assertSee('option value="CA"')
        ->assertSee('option value="GB"');
}

public function test_category_multi_select(): void
{
    $genres = \App\Models\Category::factory()->count(3)->genre()->create();

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists/create');

    $response->assertStatus(200);
    
    foreach ($genres as $genre) {
        $response->assertSee($genre->name);
    }
}
```

### Repeater Component Testing

```php
public function test_social_links_repeater(): void
{
    $artistData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'social_links' => [
            ['platform' => 'facebook', 'url' => 'https://facebook.com/artist'],
            ['platform' => 'twitter', 'url' => 'https://twitter.com/artist'],
            ['platform' => 'instagram', 'url' => 'https://instagram.com/artist'],
        ],
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertRedirect();

    $artist = Artist::where('name', 'Test Artist')->first();
    $this->assertCount(3, $artist->social_links);
    $this->assertEquals('facebook', $artist->social_links[0]['platform']);
}

public function test_repeater_minimum_items(): void
{
    $artistData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'social_links' => [], // Empty array when minimum is required
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertSessionHasErrors(['social_links']);
}
```

## Field Interaction Testing

### Conditional Field Testing

```php
public function test_conditional_fields_display(): void
{
    // Test that certain fields appear based on other field values
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/tracks/create');

    $response->assertStatus(200)
        ->assertSee('name="is_explicit"');

    // Test conditional field behavior with JavaScript (if applicable)
    // This would require browser testing with Dusk
}

public function test_dependent_field_updates(): void
{
    // Test that selecting an album updates available tracks
    $artist = Artist::factory()->create();
    $album = Album::factory()->for($artist)->create();

    $response = $this->actingAs($this->adminUser)
        ->get("/chinook-admin/tracks/create?album_id={$album->id}");

    $response->assertStatus(200)
        ->assertSee($album->title);
}
```

### Field State Testing

```php
public function test_field_state_persistence(): void
{
    // Test that field values persist after validation errors
    $invalidData = [
        'name' => '', // Invalid
        'country' => 'US',
        'biography' => 'Valid biography',
        'website' => 'https://example.com',
    ];

    $response = $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $invalidData);

    $response->assertSessionHasErrors(['name'])
        ->assertSessionHasInput('country', 'US')
        ->assertSessionHasInput('biography', 'Valid biography')
        ->assertSessionHasInput('website', 'https://example.com');
}
```

## Custom Component Testing

### Custom Field Component Testing

```php
public function test_custom_slug_field(): void
{
    $artistData = [
        'name' => 'Test Artist Name',
        'country' => 'US',
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertRedirect();

    $artist = Artist::where('name', 'Test Artist Name')->first();
    $this->assertNotNull($artist->slug);
    $this->assertEquals('test-artist-name', $artist->slug);
}

public function test_custom_public_id_generation(): void
{
    $artistData = [
        'name' => 'Test Artist',
        'country' => 'US',
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertRedirect();

    $artist = Artist::where('name', 'Test Artist')->first();
    $this->assertNotNull($artist->public_id);
    $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $artist->public_id); // ULID format
}
```

### Rich Text Editor Testing

```php
public function test_rich_text_editor_content(): void
{
    $richTextContent = '<p>This is <strong>bold</strong> text with <em>emphasis</em>.</p>';

    $artistData = [
        'name' => 'Test Artist',
        'biography' => $richTextContent,
        'country' => 'US',
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertRedirect();

    $artist = Artist::where('name', 'Test Artist')->first();
    $this->assertEquals($richTextContent, $artist->biography);
}

public function test_rich_text_editor_sanitization(): void
{
    $maliciousContent = '<script>alert("XSS")</script><p>Safe content</p>';

    $artistData = [
        'name' => 'Test Artist',
        'biography' => $maliciousContent,
        'country' => 'US',
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertRedirect();

    $artist = Artist::where('name', 'Test Artist')->first();
    $this->assertStringNotContainsString('<script>', $artist->biography);
    $this->assertStringContainsString('<p>Safe content</p>', $artist->biography);
}
```

## File Upload Testing

### Image Upload Testing

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

public function test_profile_image_upload(): void
{
    Storage::fake('public');
    
    $file = UploadedFile::fake()->image('artist.jpg', 800, 600);

    $artistData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'profile_image' => $file,
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertRedirect();

    $artist = Artist::where('name', 'Test Artist')->first();
    $this->assertTrue($artist->hasMedia('profile_images'));
    
    $media = $artist->getFirstMedia('profile_images');
    $this->assertEquals('artist.jpg', $media->name);
}

public function test_invalid_file_type_rejection(): void
{
    Storage::fake('public');
    
    $file = UploadedFile::fake()->create('document.pdf', 1000);

    $artistData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'profile_image' => $file,
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertSessionHasErrors(['profile_image']);
}

public function test_file_size_limit(): void
{
    Storage::fake('public');
    
    $file = UploadedFile::fake()->image('large.jpg')->size(10000); // 10MB

    $artistData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'profile_image' => $file,
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', $artistData)
        ->assertSessionHasErrors(['profile_image']);
}
```

## Relationship Field Testing

### BelongsTo Relationship Testing

```php
public function test_album_artist_relationship_field(): void
{
    $artist = Artist::factory()->create();

    $albumData = [
        'title' => 'Test Album',
        'artist_id' => $artist->id,
        'release_date' => '2023-01-01',
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/albums', $albumData)
        ->assertRedirect();

    $album = Album::where('title', 'Test Album')->first();
    $this->assertEquals($artist->id, $album->artist_id);
}

public function test_invalid_relationship_id(): void
{
    $albumData = [
        'title' => 'Test Album',
        'artist_id' => 99999, // Non-existent artist
        'release_date' => '2023-01-01',
    ];

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/albums', $albumData)
        ->assertSessionHasErrors(['artist_id']);
}
```

### ManyToMany Relationship Testing

```php
public function test_track_categories_relationship(): void
{
    $track = Track::factory()->create();
    $categories = Category::factory()->count(3)->create();

    $trackData = [
        'name' => $track->name,
        'album_id' => $track->album_id,
        'categories' => $categories->pluck('id')->toArray(),
    ];

    $this->actingAs($this->adminUser)
        ->put("/chinook-admin/tracks/{$track->id}", $trackData)
        ->assertRedirect();

    $track->refresh();
    $this->assertCount(3, $track->categories);
}
```

## Performance Testing

### Form Performance Testing

```php
public function test_form_loads_quickly_with_large_datasets(): void
{
    // Create large dataset
    Artist::factory()->count(1000)->create();
    Category::factory()->count(500)->create();

    $startTime = microtime(true);

    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/tracks/create');

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(2.0, $loadTime, "Form took {$loadTime} seconds to load");
}

public function test_form_submission_performance(): void
{
    $trackData = [
        'name' => 'Performance Test Track',
        'album_id' => Album::factory()->create()->id,
        'media_type_id' => MediaType::factory()->create()->id,
        'milliseconds' => 240000,
        'unit_price' => 1.99,
    ];

    $startTime = microtime(true);

    $this->actingAs($this->adminUser)
        ->post('/chinook-admin/tracks', $trackData)
        ->assertRedirect();

    $endTime = microtime(true);
    $submitTime = $endTime - $startTime;

    $this->assertLessThan(1.0, $submitTime, "Form submission took {$submitTime} seconds");
}
```

## Related Documentation

- **[Resource Testing](050-resource-testing.md)** - Overall resource testing strategies
- **[Table Testing](070-table-testing.md)** - Table functionality testing
- **[Validation Testing](../../testing/020-unit-testing-guide.md)** - Unit testing for validation rules
- **[Browser Testing](140-browser-testing.md)** - End-to-end form testing

---

## Navigation

**← Previous:** [Resource Testing](050-resource-testing.md)

**Next →** [Table Testing](070-table-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
