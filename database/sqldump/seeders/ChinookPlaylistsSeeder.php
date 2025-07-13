<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Playlists Seeder
 * 
 * Seeds the playlists table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with playlist_track.
 */
class ChinookPlaylistsSeeder extends Seeder
{
    /**
     * The original Chinook playlists data.
     */
    private array $chinookPlaylists = [
        1 => 'Music',
        2 => 'Movies',
        3 => 'TV Shows',
        4 => 'Audiobooks',
        5 => '90\'s Music',
        6 => 'Audiobooks',
        7 => 'Movies',
        8 => 'Music',
        9 => 'Music Videos',
        10 => 'TV Shows',
        11 => 'Brazilian Music',
        12 => 'Classical',
        13 => 'Classical 101 - Deep Cuts',
        14 => 'Classical 101 - Next Steps',
        15 => 'Classical 101 - The Basics',
        16 => 'Grunge',
        17 => 'Heavy Metal Classic',
        18 => 'On-The-Go 1',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Playlists seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $createdCount = 0;
            $skippedCount = 0;

            foreach ($this->chinookPlaylists as $originalId => $playlistName) {
                try {
                    // Check if playlist already exists
                    $existingPlaylist = Playlist::where('name', $playlistName)
                        ->where('id', $originalId)
                        ->first();
                    
                    if ($existingPlaylist) {
                        $this->command->warn("Playlist '{$playlistName}' (ID: {$originalId}) already exists, skipping...");
                        $skippedCount++;
                        continue;
                    }

                    // Create the playlist with original ID preserved for FK compatibility
                    $playlist = Playlist::create([
                        'id' => $originalId,
                        'name' => $playlistName,
                        'description' => $this->getPlaylistDescription($playlistName),
                        'is_public' => true, // Assume all Chinook playlists are public
                        'is_collaborative' => false,
                        'cover_image_url' => null,
                        'total_tracks' => 0, // Will be updated after tracks are added
                        'total_duration_ms' => 0, // Will be updated after tracks are added
                        'created_by' => $systemUser?->id,
                        'updated_by' => $systemUser?->id,
                    ]);

                    // Add appropriate tags
                    $tags = $this->getPlaylistTags($playlistName);
                    $playlist->syncTags($tags);

                    $createdCount++;
                    $this->command->info("Created playlist: {$playlistName} (ID: {$originalId})");

                } catch (\Exception $e) {
                    $this->command->error("Failed to create playlist '{$playlistName}': " . $e->getMessage());
                    Log::error("ChinookPlaylistsSeeder failed for playlist: {$playlistName}", [
                        'error' => $e->getMessage(),
                        'original_id' => $originalId,
                    ]);
                    throw $e;
                }
            }

            $this->command->info("Chinook Playlists seeding completed: {$createdCount} created, {$skippedCount} skipped");
        });
    }

    /**
     * Get the system user for user stamps.
     */
    private function getSystemUser(): ?User
    {
        return User::where('email', 'system@chinook.com')->first();
    }

    /**
     * Get appropriate description for playlist.
     */
    private function getPlaylistDescription(string $playlistName): string
    {
        return match ($playlistName) {
            'Music' => 'General music collection',
            'Movies' => 'Soundtracks and music from movies',
            'TV Shows' => 'Music and soundtracks from television shows',
            'Audiobooks' => 'Collection of audiobook content',
            '90\'s Music' => 'Popular music from the 1990s decade',
            'Music Videos' => 'Collection of music videos',
            'Brazilian Music' => 'Music from Brazilian artists and genres',
            'Classical' => 'Classical music collection',
            'Classical 101 - Deep Cuts' => 'Advanced classical music selections for enthusiasts',
            'Classical 101 - Next Steps' => 'Intermediate classical music for developing listeners',
            'Classical 101 - The Basics' => 'Essential classical music for beginners',
            'Grunge' => 'Grunge rock music from the alternative rock movement',
            'Heavy Metal Classic' => 'Classic heavy metal tracks and artists',
            'On-The-Go 1' => 'Portable music collection for mobile listening',
            default => "Curated playlist: {$playlistName}",
        };
    }

    /**
     * Get appropriate tags for playlist.
     */
    private function getPlaylistTags(string $playlistName): array
    {
        $baseTags = ['playlist', 'chinook'];
        
        $specificTags = match ($playlistName) {
            'Music' => ['general', 'music'],
            'Movies' => ['movies', 'soundtrack', 'media'],
            'TV Shows' => ['tv', 'television', 'soundtrack', 'media'],
            'Audiobooks' => ['audiobooks', 'spoken-word', 'media'],
            '90\'s Music' => ['90s', 'nineties', 'decade', 'retro'],
            'Music Videos' => ['music-videos', 'video', 'media'],
            'Brazilian Music' => ['brazilian', 'brazil', 'latin', 'world'],
            'Classical' => ['classical', 'orchestral'],
            'Classical 101 - Deep Cuts' => ['classical', 'advanced', 'deep-cuts'],
            'Classical 101 - Next Steps' => ['classical', 'intermediate', 'educational'],
            'Classical 101 - The Basics' => ['classical', 'beginner', 'basics', 'educational'],
            'Grunge' => ['grunge', 'alternative', 'rock', '90s'],
            'Heavy Metal Classic' => ['heavy-metal', 'metal', 'classic', 'rock'],
            'On-The-Go 1' => ['portable', 'mobile', 'travel'],
            default => [strtolower(str_replace([' ', '\'', '-'], ['', '', ''], $playlistName))],
        };

        return array_merge($baseTags, $specificTags);
    }
}
