<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Playlist Track Seeder
 * 
 * Seeds the playlist_track junction table with data from the original Chinook database.
 * Handles the many-to-many relationship between playlists and tracks.
 * Parses the SQL dump file directly to handle the large dataset efficiently (~8,715 relationships).
 */
class ChinookPlaylistTrackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Playlist Track relationships seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $sqlDumpPath = database_path('sqldump/chinook.sql');
            
            if (!file_exists($sqlDumpPath)) {
                throw new \Exception("Chinook SQL dump file not found at: {$sqlDumpPath}");
            }

            $playlistTrackData = $this->parsePlaylistTrackFromSqlDump($sqlDumpPath);
            $this->seedPlaylistTracks($playlistTrackData, $systemUser);
        });
    }

    /**
     * Parse playlist track data from the SQL dump file.
     */
    private function parsePlaylistTrackFromSqlDump(string $filePath): array
    {
        $this->command->info('Parsing playlist track data from SQL dump...');
        
        $content = file_get_contents($filePath);
        $playlistTracks = [];
        
        // Find the playlist_track INSERT statement
        $pattern = '/INSERT INTO `playlist_track` \(`id`, `track_id`\) VALUES\s*(.*?);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $valuesString = $matches[1];
            
            // Parse individual playlist track records
            $pattern = '/\((\d+),\s*(\d+)\)/';
            
            if (preg_match_all($pattern, $valuesString, $trackMatches, PREG_SET_ORDER)) {
                foreach ($trackMatches as $match) {
                    $playlistId = (int) $match[1];
                    $trackId = (int) $match[2];
                    
                    $playlistTracks[] = [
                        'playlist_id' => $playlistId,
                        'track_id' => $trackId,
                    ];
                }
            }
        }
        
        $this->command->info("Parsed " . count($playlistTracks) . " playlist-track relationships from SQL dump");
        return $playlistTracks;
    }

    /**
     * Seed the playlist tracks with the parsed data.
     */
    private function seedPlaylistTracks(array $playlistTrackData, ?User $systemUser): void
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $batchSize = 500; // Large batch for junction table
        $batch = [];
        $playlistTrackCounts = []; // Track count per playlist for updating totals

        foreach ($playlistTrackData as $relationshipData) {
            try {
                // Verify foreign key relationships
                if (!Playlist::find($relationshipData['playlist_id'])) {
                    $this->command->warn("Playlist ID {$relationshipData['playlist_id']} not found, skipping relationship...");
                    $skippedCount++;
                    continue;
                }

                if (!Track::find($relationshipData['track_id'])) {
                    $this->command->warn("Track ID {$relationshipData['track_id']} not found, skipping relationship...");
                    $skippedCount++;
                    continue;
                }

                // Check if relationship already exists
                $existingRelationship = DB::table('playlist_track')
                    ->where('playlist_id', $relationshipData['playlist_id'])
                    ->where('track_id', $relationshipData['track_id'])
                    ->exists();
                
                if ($existingRelationship) {
                    $skippedCount++;
                    continue;
                }

                // Count tracks per playlist for later total updates
                if (!isset($playlistTrackCounts[$relationshipData['playlist_id']])) {
                    $playlistTrackCounts[$relationshipData['playlist_id']] = 0;
                }
                $playlistTrackCounts[$relationshipData['playlist_id']]++;

                // Prepare relationship data for batch insert
                $batch[] = [
                    'playlist_id' => $relationshipData['playlist_id'],
                    'track_id' => $relationshipData['track_id'],
                    'sort_order' => $playlistTrackCounts[$relationshipData['playlist_id']], // Use count as sort order
                    'added_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Process batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $createdCount += count($batch);
                    $batch = [];
                    
                    // Progress indicator
                    if ($createdCount % 1000 === 0) {
                        $this->command->info("Processed {$createdCount} playlist-track relationships...");
                    }
                }

            } catch (\Exception $e) {
                $this->command->error("Failed to prepare playlist-track relationship: " . $e->getMessage());
                Log::error("ChinookPlaylistTrackSeeder failed for relationship", [
                    'relationship_data' => $relationshipData,
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
                continue;
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch);
            $createdCount += count($batch);
        }

        $this->command->info("Chinook Playlist Track relationships seeding completed: {$createdCount} created, {$skippedCount} skipped, {$errorCount} errors");
        
        // Update playlist totals
        $this->updatePlaylistTotals($playlistTrackCounts);
    }

    /**
     * Process a batch of playlist track relationships.
     */
    private function processBatch(array $batch): void
    {
        try {
            DB::table('playlist_track')->insert($batch);
        } catch (\Exception $e) {
            $this->command->warn("Batch insert failed, trying individual inserts...");
            
            foreach ($batch as $relationshipData) {
                try {
                    DB::table('playlist_track')->insert($relationshipData);
                } catch (\Exception $individualError) {
                    $this->command->error("Failed to create playlist-track relationship: " . $individualError->getMessage());
                    Log::error("Individual playlist-track relationship creation failed", [
                        'relationship_data' => $relationshipData,
                        'error' => $individualError->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Update playlist totals based on track counts.
     */
    private function updatePlaylistTotals(array $playlistTrackCounts): void
    {
        $this->command->info('Updating playlist totals...');
        
        foreach ($playlistTrackCounts as $playlistId => $trackCount) {
            try {
                $playlist = Playlist::find($playlistId);
                if (!$playlist) continue;

                // Calculate total duration from tracks
                $totalDuration = DB::table('playlist_track')
                    ->join('tracks', 'playlist_track.track_id', '=', 'tracks.id')
                    ->where('playlist_track.playlist_id', $playlistId)
                    ->sum('tracks.milliseconds');

                // Update playlist totals
                $playlist->update([
                    'total_tracks' => $trackCount,
                    'total_duration_ms' => $totalDuration,
                ]);

                $this->command->info("Updated playlist '{$playlist->name}': {$trackCount} tracks, " . 
                    round($totalDuration / 60000, 2) . " minutes");

            } catch (\Exception $e) {
                $this->command->error("Failed to update playlist totals for playlist ID {$playlistId}: " . $e->getMessage());
                Log::error("Failed to update playlist totals", [
                    'playlist_id' => $playlistId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get the system user for user stamps.
     */
    private function getSystemUser(): ?User
    {
        return User::where('email', 'system@chinook.com')->first();
    }
}
