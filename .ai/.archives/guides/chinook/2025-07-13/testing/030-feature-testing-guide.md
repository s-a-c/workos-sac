# Feature Testing Guide

## Table of Contents

- [Overview](#overview)
- [API Endpoint Testing](#api-endpoint-testing)
- [Web Route Testing](#web-route-testing)
- [Filament Admin Panel Testing](#filament-admin-panel-testing)
- [Livewire Component Testing](#livewire-component-testing)
- [Authentication & Authorization Testing](#authentication--authorization-testing)
- [Test Organization](#test-organization)
- [Best Practices](#best-practices)

## Overview

Feature testing validates application functionality from the user's perspective, testing complete workflows and user interactions. This guide covers comprehensive feature testing strategies for the Chinook application using Pest PHP framework with Laravel 12 modern patterns.

### Feature Testing Principles

- **User-Centric**: Test from the user's perspective and workflows
- **End-to-End**: Test complete features including all layers
- **Realistic Data**: Use realistic test data and scenarios
- **State Management**: Proper database state setup and cleanup

## API Endpoint Testing

### Artist API Testing

```php
<?php

// tests/Feature/Api/ArtistApiTest.php
use App\Models\Artist;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Artist API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    });

    describe('GET /api/artists', function () {
        it('returns paginated list of artists', function () {
            Artist::factory()->count(15)->create();

            $response = $this->getJson('/api/artists');

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'public_id',
                            'name',
                            'slug',
                            'country',
                            'is_active',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'links',
                    'meta'
                ]);

            expect($response->json('data'))->toHaveCount(10); // Default pagination
        });

        it('filters artists by country', function () {
            Artist::factory()->create(['country' => 'USA']);
            Artist::factory()->create(['country' => 'UK']);

            $response = $this->getJson('/api/artists?country=USA');

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(1);
            expect($response->json('data.0.country'))->toBe('USA');
        });

        it('searches artists by name', function () {
            Artist::factory()->create(['name' => 'The Beatles']);
            Artist::factory()->create(['name' => 'Led Zeppelin']);

            $response = $this->getJson('/api/artists?search=Beatles');

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(1);
            expect($response->json('data.0.name'))->toBe('The Beatles');
        });
    });

    describe('POST /api/artists', function () {
        it('creates new artist with valid data', function () {
            $artistData = [
                'name' => 'New Artist',
                'biography' => 'Artist biography',
                'country' => 'USA',
                'formed_year' => 2020,
                'is_active' => true
            ];

            $response = $this->postJson('/api/artists', $artistData);

            $response->assertCreated()
                ->assertJsonFragment($artistData);

            $this->assertDatabaseHas('artists', $artistData);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/artists', []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['name']);
        });

        it('validates unique name constraint', function () {
            $existingArtist = Artist::factory()->create(['name' => 'Existing Artist']);

            $response = $this->postJson('/api/artists', [
                'name' => 'Existing Artist',
                'country' => 'USA'
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['name']);
        });
    });

    describe('GET /api/artists/{artist}', function () {
        it('returns artist details with relationships', function () {
            $artist = Artist::factory()->create();
            $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);

            $response = $this->getJson("/api/artists/{$artist->slug}");

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'name',
                        'albums' => [
                            '*' => ['id', 'title', 'release_date']
                        ]
                    ]
                ]);

            expect($response->json('data.albums'))->toHaveCount(3);
        });

        it('returns 404 for non-existent artist', function () {
            $response = $this->getJson('/api/artists/non-existent-slug');

            $response->assertNotFound();
        });
    });

    describe('PUT /api/artists/{artist}', function () {
        it('updates artist with valid data', function () {
            $artist = Artist::factory()->create();
            $updateData = ['name' => 'Updated Name'];

            $response = $this->putJson("/api/artists/{$artist->slug}", $updateData);

            $response->assertOk()
                ->assertJsonFragment($updateData);

            expect($artist->fresh()->name)->toBe('Updated Name');
        });

        it('prevents updating slug', function () {
            $artist = Artist::factory()->create();
            $originalSlug = $artist->slug;

            $response = $this->putJson("/api/artists/{$artist->slug}", [
                'name' => 'New Name',
                'slug' => 'new-slug'
            ]);

            $response->assertOk();
            expect($artist->fresh()->slug)->toBe($originalSlug);
        });
    });

    describe('DELETE /api/artists/{artist}', function () {
        it('soft deletes artist', function () {
            $artist = Artist::factory()->create();

            $response = $this->deleteJson("/api/artists/{$artist->slug}");

            $response->assertNoContent();
            $this->assertSoftDeleted('artists', ['id' => $artist->id]);
        });

        it('prevents deletion of artist with albums', function () {
            $artist = Artist::factory()->create();
            Album::factory()->create(['artist_id' => $artist->id]);

            $response = $this->deleteJson("/api/artists/{$artist->slug}");

            $response->assertUnprocessable()
                ->assertJsonFragment(['message' => 'Cannot delete artist with existing albums']);
        });
    });
});
```

### Track API Testing

```php
<?php

// tests/Feature/Api/TrackApiTest.php
use App\Models\Track;
use App\Models\Album;
use App\Models\Category;
use App\Enums\CategoryType;

describe('Track API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    });

    describe('GET /api/tracks', function () {
        it('returns tracks with album and artist information', function () {
            $artist = Artist::factory()->create();
            $album = Album::factory()->create(['artist_id' => $artist->id]);
            Track::factory()->count(5)->create(['album_id' => $album->id]);

            $response = $this->getJson('/api/tracks');

            $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'album' => ['id', 'title', 'artist' => ['id', 'name']],
                            'formatted_duration',
                            'unit_price'
                        ]
                    ]
                ]);
        });

        it('filters tracks by genre category', function () {
            $rockGenre = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Rock']);
            $jazzGenre = Category::factory()->create(['type' => CategoryType::GENRE, 'name' => 'Jazz']);
            
            $rockTrack = Track::factory()->create();
            $jazzTrack = Track::factory()->create();
            
            $rockTrack->categories()->attach($rockGenre->id);
            $jazzTrack->categories()->attach($jazzGenre->id);

            $response = $this->getJson("/api/tracks?genre={$rockGenre->slug}");

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(1);
        });

        it('filters tracks by duration range', function () {
            Track::factory()->create(['milliseconds' => 120000]); // 2 minutes
            Track::factory()->create(['milliseconds' => 180000]); // 3 minutes
            Track::factory()->create(['milliseconds' => 300000]); // 5 minutes

            $response = $this->getJson('/api/tracks?min_duration=150&max_duration=250');

            $response->assertOk();
            expect($response->json('data'))->toHaveCount(1);
        });
    });

    describe('POST /api/tracks', function () {
        it('creates track with categories', function () {
            $album = Album::factory()->create();
            $genre = Category::factory()->create(['type' => CategoryType::GENRE]);
            $mood = Category::factory()->create(['type' => CategoryType::MOOD]);

            $trackData = [
                'name' => 'New Track',
                'album_id' => $album->id,
                'milliseconds' => 180000,
                'unit_price' => 0.99,
                'categories' => [$genre->id, $mood->id]
            ];

            $response = $this->postJson('/api/tracks', $trackData);

            $response->assertCreated();
            
            $track = Track::where('name', 'New Track')->first();
            expect($track->categories)->toHaveCount(2);
        });
    });
});
```

## Web Route Testing

### Artist Web Routes Testing

```php
<?php

// tests/Feature/Web/ArtistWebTest.php
use App\Models\Artist;
use App\Models\User;

describe('Artist Web Routes', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('GET /artists', function () {
        it('displays artists index page', function () {
            Artist::factory()->count(5)->create();

            $response = $this->get('/artists');

            $response->assertOk()
                ->assertViewIs('artists.index')
                ->assertViewHas('artists');
        });

        it('filters artists by search query', function () {
            Artist::factory()->create(['name' => 'The Beatles']);
            Artist::factory()->create(['name' => 'Led Zeppelin']);

            $response = $this->get('/artists?search=Beatles');

            $response->assertOk()
                ->assertSee('The Beatles')
                ->assertDontSee('Led Zeppelin');
        });
    });

    describe('GET /artists/{artist}', function () {
        it('displays artist detail page', function () {
            $artist = Artist::factory()->create(['name' => 'Test Artist']);
            $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);

            $response = $this->get("/artists/{$artist->slug}");

            $response->assertOk()
                ->assertViewIs('artists.show')
                ->assertViewHas('artist')
                ->assertSee('Test Artist')
                ->assertSee($albums->first()->title);
        });

        it('returns 404 for non-existent artist', function () {
            $response = $this->get('/artists/non-existent');

            $response->assertNotFound();
        });
    });

    describe('Artist Management Routes', function () {
        beforeEach(function () {
            $this->actingAs($this->user);
        });

        it('displays create artist form for authenticated users', function () {
            $response = $this->get('/artists/create');

            $response->assertOk()
                ->assertViewIs('artists.create')
                ->assertSee('Create Artist');
        });

        it('creates artist via form submission', function () {
            $artistData = [
                'name' => 'New Artist',
                'biography' => 'Artist biography',
                'country' => 'USA',
                'formed_year' => 2020
            ];

            $response = $this->post('/artists', $artistData);

            $response->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseHas('artists', $artistData);
        });

        it('validates form data', function () {
            $response = $this->post('/artists', []);

            $response->assertSessionHasErrors(['name']);
        });

        it('displays edit form', function () {
            $artist = Artist::factory()->create();

            $response = $this->get("/artists/{$artist->slug}/edit");

            $response->assertOk()
                ->assertViewIs('artists.edit')
                ->assertViewHas('artist')
                ->assertSee($artist->name);
        });

        it('updates artist via form submission', function () {
            $artist = Artist::factory()->create();
            $updateData = ['name' => 'Updated Name'];

            $response = $this->put("/artists/{$artist->slug}", $updateData);

            $response->assertRedirect()
                ->assertSessionHas('success');

            expect($artist->fresh()->name)->toBe('Updated Name');
        });
    });
});
```

### Playlist Web Routes Testing

```php
<?php

// tests/Feature/Web/PlaylistWebTest.php
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;

describe('Playlist Web Routes', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    describe('GET /playlists', function () {
        it('displays user playlists', function () {
            $userPlaylist = Playlist::factory()->create(['user_id' => $this->user->id]);
            $otherPlaylist = Playlist::factory()->create();

            $response = $this->get('/playlists');

            $response->assertOk()
                ->assertViewIs('playlists.index')
                ->assertSee($userPlaylist->name)
                ->assertDontSee($otherPlaylist->name);
        });
    });

    describe('POST /playlists', function () {
        it('creates new playlist', function () {
            $playlistData = [
                'name' => 'My Playlist',
                'description' => 'Test playlist'
            ];

            $response = $this->post('/playlists', $playlistData);

            $response->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDatabaseHas('playlists', [
                'name' => 'My Playlist',
                'user_id' => $this->user->id
            ]);
        });
    });

    describe('POST /playlists/{playlist}/tracks', function () {
        it('adds track to playlist', function () {
            $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
            $track = Track::factory()->create();

            $response = $this->post("/playlists/{$playlist->slug}/tracks", [
                'track_id' => $track->id
            ]);

            $response->assertRedirect()
                ->assertSessionHas('success');

            expect($playlist->tracks)->toContain($track);
        });

        it('prevents adding duplicate tracks', function () {
            $playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
            $track = Track::factory()->create();

            $playlist->tracks()->attach($track->id);

            $response = $this->post("/playlists/{$playlist->slug}/tracks", [
                'track_id' => $track->id
            ]);

            $response->assertSessionHasErrors(['track_id']);
        });
    });
});
```

## Filament Admin Panel Testing

### Artist Resource Testing

```php
<?php

// tests/Feature/Filament/ArtistResourceTest.php
use App\Filament\Resources\ArtistResource;
use App\Models\Artist;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\BulkAction;

describe('Artist Resource', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    });

    describe('List Page', function () {
        it('displays artists list', function () {
            Artist::factory()->count(10)->create();

            $response = $this->get(ArtistResource::getUrl('index'));

            $response->assertOk()
                ->assertSee('Artists')
                ->assertSeeLivewire('filament.resources.artists.pages.list-artists');
        });

        it('filters artists by country', function () {
            $usArtist = Artist::factory()->create(['country' => 'USA']);
            $ukArtist = Artist::factory()->create(['country' => 'UK']);

            Livewire::test(ArtistResource\Pages\ListArtists::class)
                ->filterTable('country', 'USA')
                ->assertCanSeeTableRecords([$usArtist])
                ->assertCanNotSeeTableRecords([$ukArtist]);
        });

        it('searches artists by name', function () {
            $beatles = Artist::factory()->create(['name' => 'The Beatles']);
            $zeppelin = Artist::factory()->create(['name' => 'Led Zeppelin']);

            Livewire::test(ArtistResource\Pages\ListArtists::class)
                ->searchTable('Beatles')
                ->assertCanSeeTableRecords([$beatles])
                ->assertCanNotSeeTableRecords([$zeppelin]);
        });

        it('bulk deletes artists', function () {
            $artists = Artist::factory()->count(3)->create();

            Livewire::test(ArtistResource\Pages\ListArtists::class)
                ->selectTableRecords($artists)
                ->callTableBulkAction('delete')
                ->assertHasNoTableActionErrors();

            foreach ($artists as $artist) {
                $this->assertSoftDeleted('artists', ['id' => $artist->id]);
            }
        });
    });

    describe('Create Page', function () {
        it('creates new artist', function () {
            $artistData = [
                'name' => 'New Artist',
                'biography' => 'Artist biography',
                'country' => 'USA',
                'formed_year' => 2020,
                'is_active' => true
            ];

            Livewire::test(ArtistResource\Pages\CreateArtist::class)
                ->fillForm($artistData)
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('artists', $artistData);
        });

        it('validates required fields', function () {
            Livewire::test(ArtistResource\Pages\CreateArtist::class)
                ->fillForm([])
                ->call('create')
                ->assertHasFormErrors(['name']);
        });

        it('validates unique name', function () {
            $existingArtist = Artist::factory()->create(['name' => 'Existing Artist']);

            Livewire::test(ArtistResource\Pages\CreateArtist::class)
                ->fillForm(['name' => 'Existing Artist'])
                ->call('create')
                ->assertHasFormErrors(['name']);
        });
    });

    describe('Edit Page', function () {
        it('updates artist', function () {
            $artist = Artist::factory()->create();

            Livewire::test(ArtistResource\Pages\EditArtist::class, ['record' => $artist->id])
                ->fillForm(['name' => 'Updated Name'])
                ->call('save')
                ->assertHasNoFormErrors();

            expect($artist->fresh()->name)->toBe('Updated Name');
        });

        it('deletes artist', function () {
            $artist = Artist::factory()->create();

            Livewire::test(ArtistResource\Pages\EditArtist::class, ['record' => $artist->id])
                ->callAction(DeleteAction::class)
                ->assertHasNoActionErrors();

            $this->assertSoftDeleted('artists', ['id' => $artist->id]);
        });
    });
});
```

### Category Resource Testing

```php
<?php

// tests/Feature/Filament/CategoryResourceTest.php
use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Enums\CategoryType;

describe('Category Resource', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    });

    describe('Hierarchical Management', function () {
        it('creates parent category', function () {
            $categoryData = [
                'name' => 'Rock',
                'type' => CategoryType::GENRE,
                'description' => 'Rock music genre'
            ];

            Livewire::test(CategoryResource\Pages\CreateCategory::class)
                ->fillForm($categoryData)
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('categories', $categoryData);
        });

        it('creates child category', function () {
            $parentCategory = Category::factory()->create(['type' => CategoryType::GENRE]);

            $childData = [
                'name' => 'Hard Rock',
                'type' => CategoryType::GENRE,
                'parent_id' => $parentCategory->id
            ];

            Livewire::test(CategoryResource\Pages\CreateCategory::class)
                ->fillForm($childData)
                ->call('create')
                ->assertHasNoFormErrors();

            $child = Category::where('name', 'Hard Rock')->first();
            expect($child->parent_id)->toBe($parentCategory->id);
        });

        it('validates type consistency in hierarchy', function () {
            $genreParent = Category::factory()->create(['type' => CategoryType::GENRE]);

            Livewire::test(CategoryResource\Pages\CreateCategory::class)
                ->fillForm([
                    'name' => 'Happy',
                    'type' => CategoryType::MOOD,
                    'parent_id' => $genreParent->id
                ])
                ->call('create')
                ->assertHasFormErrors(['type']);
        });
    });

    describe('Tree View', function () {
        it('displays hierarchical tree structure', function () {
            $parent = Category::factory()->create(['name' => 'Rock', 'type' => CategoryType::GENRE]);
            $child = Category::factory()->create([
                'name' => 'Hard Rock',
                'type' => CategoryType::GENRE,
                'parent_id' => $parent->id
            ]);

            Livewire::test(CategoryResource\Pages\ListCategories::class)
                ->assertCanSeeTableRecords([$parent, $child])
                ->assertTableColumnStateSet('name', 'Rock', $parent)
                ->assertTableColumnStateSet('name', '— Hard Rock', $child); // Indented child
        });
    });
});
```

## Livewire Component Testing

### Track Player Component Testing

```php
<?php

// tests/Feature/Livewire/TrackPlayerTest.php
use App\Livewire\TrackPlayer;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

describe('Track Player Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->track = Track::factory()->create(['name' => 'Test Track']);
    });

    it('renders track information', function () {
        Livewire::test(TrackPlayer::class, ['track' => $this->track])
            ->assertSee('Test Track')
            ->assertSee($this->track->album->title)
            ->assertSee($this->track->album->artist->name)
            ->assertSee($this->track->formatted_duration);
    });

    it('can play and pause track', function () {
        Livewire::test(TrackPlayer::class, ['track' => $this->track])
            ->assertSet('isPlaying', false)
            ->call('play')
            ->assertSet('isPlaying', true)
            ->assertDispatched('track-started', ['trackId' => $this->track->id])
            ->call('pause')
            ->assertSet('isPlaying', false)
            ->assertDispatched('track-paused');
    });

    it('updates progress when seeking', function () {
        Livewire::test(TrackPlayer::class, ['track' => $this->track])
            ->call('seek', 50)
            ->assertSet('progress', 50)
            ->assertDispatched('track-seeked', ['position' => 50]);
    });

    it('handles volume changes', function () {
        Livewire::test(TrackPlayer::class, ['track' => $this->track])
            ->set('volume', 75)
            ->assertSet('volume', 75)
            ->assertDispatched('volume-changed', ['volume' => 75]);
    });

    it('toggles repeat mode', function () {
        Livewire::test(TrackPlayer::class, ['track' => $this->track])
            ->assertSet('repeatMode', 'none')
            ->call('toggleRepeat')
            ->assertSet('repeatMode', 'track')
            ->call('toggleRepeat')
            ->assertSet('repeatMode', 'none');
    });

    it('adds track to favorites', function () {
        Livewire::test(TrackPlayer::class, ['track' => $this->track])
            ->call('toggleFavorite')
            ->assertDispatched('track-favorited', ['trackId' => $this->track->id]);

        expect($this->user->favoriteTracks)->toContain($this->track);
    });
});
```

### Playlist Manager Component Testing

```php
<?php

// tests/Feature/Livewire/PlaylistManagerTest.php
use App\Livewire\PlaylistManager;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;

describe('Playlist Manager Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->playlist = Playlist::factory()->create(['user_id' => $this->user->id]);
    });

    it('displays user playlists', function () {
        $otherPlaylist = Playlist::factory()->create();

        Livewire::test(PlaylistManager::class)
            ->assertSee($this->playlist->name)
            ->assertDontSee($otherPlaylist->name);
    });

    it('creates new playlist', function () {
        Livewire::test(PlaylistManager::class)
            ->set('newPlaylistName', 'My New Playlist')
            ->call('createPlaylist')
            ->assertHasNoErrors()
            ->assertDispatched('playlist-created');

        $this->assertDatabaseHas('playlists', [
            'name' => 'My New Playlist',
            'user_id' => $this->user->id
        ]);
    });

    it('validates playlist name', function () {
        Livewire::test(PlaylistManager::class)
            ->set('newPlaylistName', '')
            ->call('createPlaylist')
            ->assertHasErrors(['newPlaylistName']);
    });

    it('adds track to playlist', function () {
        $track = Track::factory()->create();

        Livewire::test(PlaylistManager::class)
            ->call('addTrackToPlaylist', $this->playlist->id, $track->id)
            ->assertHasNoErrors()
            ->assertDispatched('track-added-to-playlist');

        expect($this->playlist->tracks)->toContain($track);
    });

    it('removes track from playlist', function () {
        $track = Track::factory()->create();
        $this->playlist->tracks()->attach($track->id);

        Livewire::test(PlaylistManager::class)
            ->call('removeTrackFromPlaylist', $this->playlist->id, $track->id)
            ->assertHasNoErrors()
            ->assertDispatched('track-removed-from-playlist');

        expect($this->playlist->fresh()->tracks)->not->toContain($track);
    });

    it('reorders tracks in playlist', function () {
        $tracks = Track::factory()->count(3)->create();
        $this->playlist->tracks()->attach($tracks->pluck('id')->toArray());

        $newOrder = [$tracks[2]->id, $tracks[0]->id, $tracks[1]->id];

        Livewire::test(PlaylistManager::class)
            ->call('reorderTracks', $this->playlist->id, $newOrder)
            ->assertHasNoErrors()
            ->assertDispatched('playlist-reordered');

        $orderedTracks = $this->playlist->fresh()->tracks()->orderBy('pivot_position')->get();
        expect($orderedTracks->first()->id)->toBe($tracks[2]->id);
    });
});
```

### Search Component Testing

```php
<?php

// tests/Feature/Livewire/SearchComponentTest.php
use App\Livewire\SearchComponent;
use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;

describe('Search Component', function () {
    beforeEach(function () {
        $this->artist = Artist::factory()->create(['name' => 'The Beatles']);
        $this->album = Album::factory()->create(['title' => 'Abbey Road']);
        $this->track = Track::factory()->create(['name' => 'Come Together']);
    });

    it('searches across all content types', function () {
        Livewire::test(SearchComponent::class)
            ->set('query', 'Beatles')
            ->call('search')
            ->assertSee('The Beatles')
            ->assertSee('Artists')
            ->assertViewHas('results');
    });

    it('filters results by content type', function () {
        Livewire::test(SearchComponent::class)
            ->set('query', 'Abbey')
            ->set('filter', 'albums')
            ->call('search')
            ->assertSee('Abbey Road')
            ->assertDontSee('The Beatles');
    });

    it('handles empty search queries', function () {
        Livewire::test(SearchComponent::class)
            ->set('query', '')
            ->call('search')
            ->assertSee('Enter a search term');
    });

    it('provides search suggestions', function () {
        Livewire::test(SearchComponent::class)
            ->set('query', 'Beat')
            ->call('getSuggestions')
            ->assertJsonFragment(['The Beatles']);
    });

    it('tracks search analytics', function () {
        Livewire::test(SearchComponent::class)
            ->set('query', 'Beatles')
            ->call('search')
            ->assertDispatched('search-performed', [
                'query' => 'Beatles',
                'results_count' => 1
            ]);
    });
});
```

## Authentication & Authorization Testing

### Role-Based Access Control Testing

```php
<?php

// tests/Feature/Auth/RoleBasedAccessTest.php
use App\Models\User;
use App\Models\Artist;
use Spatie\Permission\Models\Role;

describe('Role-Based Access Control', function () {
    beforeEach(function () {
        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'editor']);
        Role::create(['name' => 'user']);

        $this->admin = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->user = User::factory()->create();

        $this->admin->assignRole('admin');
        $this->editor->assignRole('editor');
        $this->user->assignRole('user');
    });

    describe('Admin Access', function () {
        it('allows admin to access all resources', function () {
            $this->actingAs($this->admin);

            $response = $this->get('/admin');
            $response->assertOk();

            $response = $this->get('/admin/artists');
            $response->assertOk();

            $response = $this->delete('/admin/artists/1');
            $response->assertStatus(302); // Redirect after action
        });
    });

    describe('Editor Access', function () {
        it('allows editor to manage content but not users', function () {
            $this->actingAs($this->editor);

            $response = $this->get('/admin/artists');
            $response->assertOk();

            $response = $this->post('/admin/artists', [
                'name' => 'New Artist',
                'country' => 'USA'
            ]);
            $response->assertStatus(302);

            $response = $this->get('/admin/users');
            $response->assertForbidden();
        });
    });

    describe('User Access', function () {
        it('restricts user access to admin panel', function () {
            $this->actingAs($this->user);

            $response = $this->get('/admin');
            $response->assertForbidden();
        });

        it('allows user access to public content', function () {
            $this->actingAs($this->user);

            $response = $this->get('/artists');
            $response->assertOk();

            $response = $this->get('/playlists');
            $response->assertOk();
        });
    });

    describe('Guest Access', function () {
        it('redirects guests to login for protected routes', function () {
            $response = $this->get('/admin');
            $response->assertRedirect('/login');

            $response = $this->get('/playlists');
            $response->assertRedirect('/login');
        });

        it('allows guest access to public content', function () {
            $response = $this->get('/artists');
            $response->assertOk();

            $response = $this->get('/tracks');
            $response->assertOk();
        });
    });
});
```

### API Authentication Testing

```php
<?php

// tests/Feature/Auth/ApiAuthenticationTest.php
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('API Authentication', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('Sanctum Token Authentication', function () {
        it('allows access with valid token', function () {
            Sanctum::actingAs($this->user);

            $response = $this->getJson('/api/artists');
            $response->assertOk();
        });

        it('denies access without token', function () {
            $response = $this->getJson('/api/artists');
            $response->assertUnauthorized();
        });

        it('denies access with invalid token', function () {
            $this->withHeaders(['Authorization' => 'Bearer invalid-token']);

            $response = $this->getJson('/api/artists');
            $response->assertUnauthorized();
        });
    });

    describe('Token Abilities', function () {
        it('respects token abilities', function () {
            $token = $this->user->createToken('test-token', ['read']);

            $this->withHeaders(['Authorization' => "Bearer {$token->plainTextToken}"]);

            $response = $this->getJson('/api/artists');
            $response->assertOk();

            $response = $this->postJson('/api/artists', ['name' => 'New Artist']);
            $response->assertForbidden();
        });
    });
});
```

## Test Organization

### Directory Structure

```text
tests/Feature/
├── Api/
│   ├── ArtistApiTest.php
│   ├── AlbumApiTest.php
│   ├── TrackApiTest.php
│   └── PlaylistApiTest.php
├── Web/
│   ├── ArtistWebTest.php
│   ├── AlbumWebTest.php
│   ├── PlaylistWebTest.php
│   └── SearchWebTest.php
├── Filament/
│   ├── ArtistResourceTest.php
│   ├── CategoryResourceTest.php
│   ├── UserResourceTest.php
│   └── DashboardTest.php
├── Livewire/
│   ├── TrackPlayerTest.php
│   ├── PlaylistManagerTest.php
│   ├── SearchComponentTest.php
│   └── CategorySelectorTest.php
└── Auth/
    ├── RoleBasedAccessTest.php
    ├── ApiAuthenticationTest.php
    └── PermissionTest.php
```

### Test Naming Conventions

- **Test Files**: `{FeatureName}Test.php`
- **Test Methods**: Descriptive names using `it('should do something', function() {})`
- **Test Groups**: Use `describe()` blocks to group related tests
- **Setup Methods**: Use `beforeEach()` for common test setup

## Best Practices

### Feature Test Guidelines

1. **Test User Workflows**: Focus on complete user journeys and workflows
2. **Use Realistic Data**: Create realistic test scenarios with proper relationships
3. **Test Edge Cases**: Include tests for error conditions and boundary cases
4. **Verify Side Effects**: Test that actions produce expected side effects

### Performance Considerations

1. **Database Optimization**: Use `RefreshDatabase` trait for clean state
2. **Factory Efficiency**: Create only the data needed for each test
3. **Parallel Testing**: Structure tests to run in parallel when possible
4. **Resource Cleanup**: Ensure proper cleanup of test resources

### Maintenance Strategies

1. **DRY Principle**: Extract common test logic into helper methods
2. **Page Objects**: Use page object pattern for complex UI testing
3. **Test Data Builders**: Create fluent test data builders for complex scenarios
4. **Regular Refactoring**: Keep test code clean and maintainable

---

**Navigation:**

- **Previous:** [Unit Testing Guide](020-unit-testing-guide.md)
- **Next:** [Integration Testing Guide](040-integration-testing-guide.md)
- **Up:** [Testing Documentation](000-testing-index.md)
