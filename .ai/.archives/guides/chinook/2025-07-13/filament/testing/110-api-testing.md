# API Testing Guide

This guide covers comprehensive API endpoint testing for the Chinook admin panel, including REST API validation,
authentication, authorization, and integration testing.

## Table of Contents

- [Overview](#overview)
- [API Authentication Testing](#api-authentication-testing)
- [CRUD Endpoint Testing](#crud-endpoint-testing)
- [API Authorization Testing](#api-authorization-testing)
- [Request Validation Testing](#request-validation-testing)
- [Response Format Testing](#response-format-testing)
- [Error Handling Testing](#error-handling-testing)
- [Performance Testing](#performance-testing)
- [Integration Testing](#integration-testing)

## Overview

API testing ensures that all REST endpoints function correctly, maintain proper security, and provide consistent
responses. This includes testing authentication, authorization, validation, and error handling.

### Testing Objectives

- **Functionality**: Verify all API endpoints work correctly
- **Security**: Test authentication and authorization mechanisms
- **Validation**: Ensure proper request validation and error responses
- **Performance**: Validate API performance under load
- **Consistency**: Test response format and data consistency

## API Authentication Testing

### Token-Based Authentication

```php
<?php

namespace Tests\Feature\ChinookAdmin\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class APIAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/artists');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_valid_token_allows_api_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/artists');

        $response->assertStatus(200);
    }

    public function test_invalid_token_denies_access(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/v1/artists');

        $response->assertStatus(401);
    }

    public function test_expired_token_denies_access(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['*'], now()->subDay());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->getJson('/api/v1/artists');

        $response->assertStatus(401);
    }

    public function test_token_scopes_restrict_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        
        // Token with limited scope
        $token = $user->createToken('limited-token', ['read-only']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->getJson('/api/v1/artists');

        $response->assertStatus(200);

        // Should not allow write operations
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->postJson('/api/v1/artists', [
            'name' => 'Test Artist',
            'country' => 'US',
        ]);

        $response->assertStatus(403);
    }
}
```

### API Key Authentication

```php
public function test_api_key_authentication(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $apiKey = $user->createToken('api-key')->plainTextToken;

    $response = $this->withHeaders([
        'X-API-Key' => $apiKey,
    ])->getJson('/api/v1/artists');

    $response->assertStatus(200);
}

public function test_invalid_api_key_denies_access(): void
{
    $response = $this->withHeaders([
        'X-API-Key' => 'invalid-key',
    ])->getJson('/api/v1/artists');

    $response->assertStatus(401);
}
```

## CRUD Endpoint Testing

### GET Endpoints Testing

```php
it('returns artists list with proper structure', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artists = Artist::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/artists');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'public_id',
                    'name',
                    'country',
                    'formed_year',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
        ])
        ->assertJsonCount(3, 'data');
});

public function test_get_single_artist(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create([
        'name' => 'Test Artist',
        'country' => 'US',
    ]);

    $response = $this->getJson("/api/v1/artists/{$artist->public_id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'public_id' => $artist->public_id,
                'name' => 'Test Artist',
                'country' => 'US',
            ],
        ]);
}

public function test_get_nonexistent_artist_returns_404(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/artists/nonexistent-id');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Artist not found.',
        ]);
}
```

### POST Endpoints Testing

```php
public function test_create_artist_via_api(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artistData = [
        'name' => 'API Test Artist',
        'country' => 'US',
        'biography' => 'Test biography',
        'website' => 'https://example.com',
        'formed_year' => 2020,
        'is_active' => true,
    ];

    $response = $this->postJson('/api/v1/artists', $artistData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'public_id',
                'name',
                'country',
                'biography',
                'website',
                'formed_year',
                'is_active',
                'created_at',
                'updated_at',
            ],
        ]);

    $this->assertDatabaseHas('artists', [
        'name' => 'API Test Artist',
        'country' => 'US',
    ]);
}

public function test_create_artist_with_invalid_data(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $invalidData = [
        'name' => '', // Required field
        'country' => 'INVALID', // Invalid country code
        'website' => 'not-a-url',
        'formed_year' => 1800, // Too early
    ];

    $response = $this->postJson('/api/v1/artists', $invalidData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'name',
            'country',
            'website',
            'formed_year',
        ]);
}
```

### PUT/PATCH Endpoints Testing

```php
public function test_update_artist_via_api(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create([
        'name' => 'Original Name',
        'country' => 'US',
    ]);

    $updateData = [
        'name' => 'Updated Name',
        'country' => 'CA',
        'biography' => 'Updated biography',
    ];

    $response = $this->putJson("/api/v1/artists/{$artist->public_id}", $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'name' => 'Updated Name',
                'country' => 'CA',
                'biography' => 'Updated biography',
            ],
        ]);

    $this->assertDatabaseHas('artists', [
        'id' => $artist->id,
        'name' => 'Updated Name',
        'country' => 'CA',
    ]);
}

public function test_partial_update_artist_via_patch(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create([
        'name' => 'Original Name',
        'country' => 'US',
        'biography' => 'Original biography',
    ]);

    $patchData = [
        'name' => 'Patched Name',
    ];

    $response = $this->patchJson("/api/v1/artists/{$artist->public_id}", $patchData);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'name' => 'Patched Name',
                'country' => 'US', // Unchanged
                'biography' => 'Original biography', // Unchanged
            ],
        ]);
}
```

### DELETE Endpoints Testing

```php
public function test_delete_artist_via_api(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create();

    $response = $this->deleteJson("/api/v1/artists/{$artist->public_id}");

    $response->assertStatus(204);

    $this->assertSoftDeleted($artist);
}

public function test_delete_nonexistent_artist_returns_404(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/v1/artists/nonexistent-id');

    $response->assertStatus(404);
}

public function test_cannot_delete_artist_with_albums(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create();
    Album::factory()->for($artist)->create();

    $response = $this->deleteJson("/api/v1/artists/{$artist->public_id}");

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Cannot delete artist with existing albums.',
        ]);

    $this->assertNotSoftDeleted($artist);
}
```

## API Authorization Testing

### Role-Based API Access

```php
public function test_admin_can_access_all_api_endpoints(): void
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Sanctum::actingAs($admin);

    $endpoints = [
        ['GET', '/api/v1/artists'],
        ['GET', '/api/v1/albums'],
        ['GET', '/api/v1/tracks'],
        ['GET', '/api/v1/customers'],
        ['GET', '/api/v1/employees'],
    ];

    foreach ($endpoints as [$method, $endpoint]) {
        $response = $this->json($method, $endpoint);
        $this->assertEquals(200, $response->getStatusCode(), 
            "Admin should access {$method} {$endpoint}");
    }
}

public function test_editor_cannot_access_customer_api(): void
{
    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    Sanctum::actingAs($editor);

    $response = $this->getJson('/api/v1/customers');

    $response->assertStatus(403);
}

public function test_guest_can_only_read_content_api(): void
{
    $guest = User::factory()->create();
    $guest->assignRole('Guest');
    Sanctum::actingAs($guest);

    // Can read artists
    $response = $this->getJson('/api/v1/artists');
    $response->assertStatus(200);

    // Cannot create artists
    $response = $this->postJson('/api/v1/artists', [
        'name' => 'Test Artist',
        'country' => 'US',
    ]);
    $response->assertStatus(403);
}
```

### Permission-Based API Access

```php
public function test_api_respects_granular_permissions(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('view-artists');
    Sanctum::actingAs($user);

    // Can view artists
    $response = $this->getJson('/api/v1/artists');
    $response->assertStatus(200);

    // Cannot create artists (no create permission)
    $response = $this->postJson('/api/v1/artists', [
        'name' => 'Test Artist',
        'country' => 'US',
    ]);
    $response->assertStatus(403);

    // Cannot view albums (no album permission)
    $response = $this->getJson('/api/v1/albums');
    $response->assertStatus(403);
}
```

## Request Validation Testing

### Input Validation Testing

```php
public function test_api_validates_required_fields(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/artists', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'country']);
}

public function test_api_validates_field_formats(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $invalidData = [
        'name' => 'Valid Name',
        'country' => 'INVALID_CODE',
        'website' => 'not-a-url',
        'formed_year' => 'not-a-year',
        'social_links' => 'not-an-array',
    ];

    $response = $this->postJson('/api/v1/artists', $invalidData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'country',
            'website',
            'formed_year',
            'social_links',
        ]);
}

public function test_api_validates_unique_constraints(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $existingArtist = Artist::factory()->create(['name' => 'Existing Artist']);

    $response = $this->postJson('/api/v1/artists', [
        'name' => 'Existing Artist',
        'country' => 'US',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
}
```

### Complex Validation Testing

```php
public function test_api_validates_nested_data(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $invalidNestedData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'social_links' => [
            ['platform' => 'facebook', 'url' => 'invalid-url'],
            ['platform' => '', 'url' => 'https://example.com'],
            ['platform' => 'twitter', 'url' => ''],
        ],
    ];

    $response = $this->postJson('/api/v1/artists', $invalidNestedData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'social_links.0.url',
            'social_links.1.platform',
            'social_links.2.url',
        ]);
}

public function test_api_validates_conditional_fields(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    // Test that certain fields are required based on other field values
    $conditionalData = [
        'name' => 'Test Artist',
        'country' => 'US',
        'has_website' => true,
        // website should be required when has_website is true
    ];

    $response = $this->postJson('/api/v1/artists', $conditionalData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['website']);
}
```

## Response Format Testing

### JSON Structure Testing

```php
public function test_api_returns_consistent_json_structure(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create();

    $response = $this->getJson("/api/v1/artists/{$artist->public_id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'public_id',
                'name',
                'country',
                'biography',
                'website',
                'formed_year',
                'is_active',
                'social_links',
                'created_at',
                'updated_at',
                'albums_count',
                'categories' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                    ],
                ],
            ],
        ]);
}

public function test_api_pagination_structure(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    Artist::factory()->count(20)->create();

    $response = $this->getJson('/api/v1/artists?per_page=10');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
        ]);
}

public function test_api_error_response_structure(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/artists', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                'name' => [],
                'country' => [],
            ],
        ]);
}
```

### Data Transformation Testing

```php
public function test_api_transforms_data_correctly(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create([
        'created_at' => '2023-01-15 10:30:00',
        'updated_at' => '2023-01-16 15:45:00',
    ]);

    $response = $this->getJson("/api/v1/artists/{$artist->public_id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'created_at' => '2023-01-15T10:30:00.000000Z',
                'updated_at' => '2023-01-16T15:45:00.000000Z',
            ],
        ]);
}

public function test_api_includes_computed_fields(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    $artist = Artist::factory()->create();
    Album::factory()->count(3)->for($artist)->create();

    $response = $this->getJson("/api/v1/artists/{$artist->public_id}");

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'albums_count' => 3,
            ],
        ]);
}
```

## Error Handling Testing

### HTTP Error Status Testing

```php
public function test_api_returns_404_for_nonexistent_resource(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/v1/artists/99999');

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Artist not found',
            'error' => 'RESOURCE_NOT_FOUND'
        ]);
}

public function test_api_returns_422_for_validation_errors(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/v1/artists', [
        'name' => '', // Required field empty
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'message',
            'errors' => [
                'name'
            ]
        ]);
}

public function test_api_returns_403_for_unauthorized_access(): void
{
    $user = User::factory()->create();
    $user->assignRole('Guest'); // Role without API access

    $this->actingAs($user);

    $response = $this->getJson('/api/v1/artists');

    $response->assertStatus(403)
        ->assertJson([
            'message' => 'Insufficient permissions',
            'error' => 'FORBIDDEN'
        ]);
}
```

### Exception Handling Testing

```php
public function test_api_handles_database_connection_errors(): void
{
    // Simulate database connection failure
    DB::shouldReceive('connection')->andThrow(new \Exception('Database connection failed'));

    $this->actingAs($this->adminUser);

    $response = $this->getJson('/api/v1/artists');

    $response->assertStatus(500)
        ->assertJson([
            'message' => 'Internal server error',
            'error' => 'DATABASE_ERROR'
        ]);
}

public function test_api_handles_rate_limiting(): void
{
    $this->actingAs($this->adminUser);

    // Make multiple requests to trigger rate limiting
    for ($i = 0; $i < 61; $i++) {
        $response = $this->getJson('/api/v1/artists');
    }

    $response->assertStatus(429)
        ->assertJson([
            'message' => 'Too many requests',
            'error' => 'RATE_LIMIT_EXCEEDED'
        ]);
}

public function test_api_handles_malformed_json(): void
{
    $this->actingAs($this->adminUser);

    $response = $this->call('POST', '/api/v1/artists', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{"name": "Test Artist"'); // Malformed JSON

    $response->assertStatus(400)
        ->assertJson([
            'message' => 'Invalid JSON format',
            'error' => 'MALFORMED_JSON'
        ]);
}
```

### Error Response Consistency Testing

```php
public function test_error_responses_have_consistent_structure(): void
{
    $this->actingAs($this->adminUser);

    // Test various error scenarios
    $responses = [
        $this->getJson('/api/v1/artists/99999'), // 404
        $this->postJson('/api/v1/artists', []), // 422
        $this->deleteJson('/api/v1/artists/1'), // 403 (if no delete permission)
    ];

    foreach ($responses as $response) {
        $response->assertJsonStructure([
            'message',
            'error',
            'timestamp',
            'path'
        ]);
    }
}

public function test_error_logging_functionality(): void
{
    Log::fake();

    $this->actingAs($this->adminUser);

    // Trigger an error
    $this->getJson('/api/v1/artists/99999');

    Log::assertLogged('warning', function ($message, $context) {
        return str_contains($message, 'API resource not found') &&
               isset($context['resource_type']) &&
               $context['resource_type'] === 'Artist';
    });
}
```

## Integration Testing

### Third-Party Service Integration Testing

```php
public function test_spotify_api_integration(): void
{
    Http::fake([
        'api.spotify.com/*' => Http::response([
            'artists' => [
                'items' => [
                    [
                        'id' => 'spotify123',
                        'name' => 'Test Artist',
                        'external_urls' => ['spotify' => 'https://open.spotify.com/artist/spotify123']
                    ]
                ]
            ]
        ], 200)
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/v1/artists/sync-spotify', [
        'artist_name' => 'Test Artist'
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'spotify_id' => 'spotify123',
            'external_url' => 'https://open.spotify.com/artist/spotify123'
        ]);
}

public function test_musicbrainz_integration(): void
{
    Http::fake([
        'musicbrainz.org/*' => Http::response([
            'artists' => [
                [
                    'id' => 'mb123',
                    'name' => 'Test Artist',
                    'disambiguation' => 'Rock band from UK'
                ]
            ]
        ], 200)
    ]);

    $this->actingAs($this->adminUser);

    $response = $this->postJson('/api/v1/artists/enrich-metadata', [
        'artist_id' => 1
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('musicbrainz_id', 'mb123');
}
```

### Database Transaction Testing

```php
public function test_api_maintains_data_consistency(): void
{
    $this->actingAs($this->adminUser);

    DB::beginTransaction();

    try {
        // Create artist with albums in a transaction
        $response = $this->postJson('/api/v1/artists', [
            'name' => 'Test Artist',
            'albums' => [
                ['title' => 'Album 1'],
                ['title' => 'Album 2']
            ]
        ]);

        $response->assertStatus(201);

        $artist = Artist::where('name', 'Test Artist')->first();
        $this->assertCount(2, $artist->albums);

        DB::commit();
    } catch (\Exception $e) {
        DB::rollback();
        $this->fail('Transaction failed: ' . $e->getMessage());
    }
}

public function test_api_rollback_on_validation_failure(): void
{
    $this->actingAs($this->adminUser);

    $initialArtistCount = Artist::count();

    // Attempt to create artist with invalid album data
    $response = $this->postJson('/api/v1/artists', [
        'name' => 'Test Artist',
        'albums' => [
            ['title' => ''], // Invalid empty title
            ['title' => 'Valid Album']
        ]
    ]);

    $response->assertStatus(422);

    // Verify no artist was created due to rollback
    $this->assertEquals($initialArtistCount, Artist::count());
}
```

### Cross-Resource Integration Testing

```php
public function test_artist_album_track_relationship_integrity(): void
{
    $this->actingAs($this->adminUser);

    // Create artist
    $artistResponse = $this->postJson('/api/v1/artists', [
        'name' => 'Integration Test Artist'
    ]);

    $artistId = $artistResponse->json('data.id');

    // Create album for artist
    $albumResponse = $this->postJson('/api/v1/albums', [
        'title' => 'Integration Test Album',
        'artist_id' => $artistId
    ]);

    $albumId = $albumResponse->json('data.id');

    // Create track for album
    $trackResponse = $this->postJson('/api/v1/tracks', [
        'name' => 'Integration Test Track',
        'album_id' => $albumId
    ]);

    // Verify relationships
    $artistResponse = $this->getJson("/api/v1/artists/{$artistId}?include=albums.tracks");

    $artistResponse->assertStatus(200)
        ->assertJsonPath('data.albums.0.title', 'Integration Test Album')
        ->assertJsonPath('data.albums.0.tracks.0.name', 'Integration Test Track');
}

public function test_cascade_delete_behavior(): void
{
    $artist = Artist::factory()
        ->has(Album::factory()->has(Track::factory()->count(3))->count(2))
        ->create();

    $this->actingAs($this->adminUser);

    $response = $this->deleteJson("/api/v1/artists/{$artist->id}");

    $response->assertStatus(204);

    // Verify cascade soft delete
    $this->assertSoftDeleted($artist);

    foreach ($artist->albums as $album) {
        $this->assertSoftDeleted($album);

        foreach ($album->tracks as $track) {
            $this->assertSoftDeleted($track);
        }
    }
}
```

### API Versioning Testing

```php
public function test_api_version_compatibility(): void
{
    $this->actingAs($this->adminUser);

    // Test v1 API
    $v1Response = $this->getJson('/api/v1/artists', [
        'Accept' => 'application/vnd.chinook.v1+json'
    ]);

    $v1Response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'created_at']
            ]
        ]);

    // Test v2 API (if available)
    $v2Response = $this->getJson('/api/v2/artists', [
        'Accept' => 'application/vnd.chinook.v2+json'
    ]);

    if ($v2Response->status() === 200) {
        $v2Response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'created_at', 'updated_at']
            ]
        ]);
    }
}
```

## Performance Testing

### API Performance Testing

```php
public function test_api_response_time(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    Artist::factory()->count(100)->create();

    $startTime = microtime(true);

    $response = $this->getJson('/api/v1/artists');

    $endTime = microtime(true);
    $responseTime = $endTime - $startTime;

    $response->assertStatus(200);
    $this->assertLessThan(1.0, $responseTime, 
        "API response took {$responseTime} seconds");
}

public function test_api_handles_large_datasets(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    Sanctum::actingAs($user);

    Artist::factory()->count(1000)->create();

    $response = $this->getJson('/api/v1/artists?per_page=100');

    $response->assertStatus(200)
        ->assertJsonCount(100, 'data');
}
```

## Related Documentation

- **[RBAC Testing](100-rbac-testing.md)** - Role-based access control testing
- **[Authentication Testing](090-auth-testing.md)** - Authentication mechanisms
- **[Performance Testing](130-performance-testing.md)** - Load testing and optimization
- **[Security Testing](160-security-testing.md)** - API security validation

---

## Navigation

**← Previous:** [RBAC Testing](100-rbac-testing.md)

**Next →** [Database Testing](120-database-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
