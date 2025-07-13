<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Albums Seeder
 * 
 * Seeds the albums table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with tracks.
 * Parses the SQL dump file directly to handle the large dataset efficiently.
 */
class ChinookAlbumsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Albums seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $sqlDumpPath = database_path('sqldump/chinook.sql');
            
            if (!file_exists($sqlDumpPath)) {
                throw new \Exception("Chinook SQL dump file not found at: {$sqlDumpPath}");
            }

            $albumsData = $this->parseAlbumsFromSqlDump($sqlDumpPath);
            $this->seedAlbums($albumsData, $systemUser);
        });
    }

    /**
     * Parse albums data from the SQL dump file.
     */
    private function parseAlbumsFromSqlDump(string $filePath): array
    {
        $this->command->info('Parsing albums data from SQL dump...');
        
        $content = file_get_contents($filePath);
        $albums = [];
        
        // Find the albums INSERT statement
        $pattern = '/INSERT INTO `albums` \(`id`, `title`, `artist_id`\) VALUES\s*(.*?);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $valuesString = $matches[1];
            
            // Parse individual album records
            $pattern = '/\((\d+),\s*\'([^\']*(?:\'\'[^\']*)*)\',\s*(\d+)\)/';
            
            if (preg_match_all($pattern, $valuesString, $albumMatches, PREG_SET_ORDER)) {
                foreach ($albumMatches as $match) {
                    $id = (int) $match[1];
                    $title = str_replace("''", "'", $match[2]); // Handle escaped quotes
                    $artistId = (int) $match[3];
                    
                    $albums[] = [
                        'id' => $id,
                        'title' => $title,
                        'artist_id' => $artistId,
                    ];
                }
            }
        }
        
        $this->command->info("Parsed " . count($albums) . " albums from SQL dump");
        return $albums;
    }

    /**
     * Seed the albums with the parsed data.
     */
    private function seedAlbums(array $albumsData, ?User $systemUser): void
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $batchSize = 50;
        $batch = [];

        foreach ($albumsData as $albumData) {
            try {
                // Verify that the artist exists
                $artist = Artist::find($albumData['artist_id']);
                if (!$artist) {
                    $this->command->warn("Artist ID {$albumData['artist_id']} not found for album '{$albumData['title']}', skipping...");
                    $skippedCount++;
                    continue;
                }

                // Check if album already exists
                $existingAlbum = Album::where('title', $albumData['title'])
                    ->where('artist_id', $albumData['artist_id'])
                    ->first();
                
                if ($existingAlbum) {
                    $this->command->warn("Album '{$albumData['title']}' by artist ID {$albumData['artist_id']} already exists, skipping...");
                    $skippedCount++;
                    continue;
                }

                // Prepare album data for batch insert
                $batch[] = [
                    'id' => $albumData['id'],
                    'title' => $albumData['title'],
                    'artist_id' => $albumData['artist_id'],
                    'description' => null,
                    'release_date' => null,
                    'total_tracks' => null,
                    'duration_ms' => null,
                    'cover_image_url' => null,
                    'metadata' => null,
                    'created_by' => $systemUser?->id,
                    'updated_by' => $systemUser?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Process batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $createdCount += count($batch);
                    $batch = [];
                    
                    // Progress indicator
                    $this->command->info("Processed {$createdCount} albums...");
                }

            } catch (\Exception $e) {
                $this->command->error("Failed to prepare album '{$albumData['title']}': " . $e->getMessage());
                Log::error("ChinookAlbumsSeeder failed for album", [
                    'album_data' => $albumData,
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
                
                // Continue processing other albums
                continue;
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch);
            $createdCount += count($batch);
        }

        $this->command->info("Chinook Albums seeding completed: {$createdCount} created, {$skippedCount} skipped, {$errorCount} errors");
    }

    /**
     * Process a batch of albums.
     */
    private function processBatch(array $batch): void
    {
        try {
            Album::insert($batch);
        } catch (\Exception $e) {
            // If batch insert fails, try individual inserts to identify problematic records
            $this->command->warn("Batch insert failed, trying individual inserts...");
            
            foreach ($batch as $albumData) {
                try {
                    Album::create($albumData);
                } catch (\Exception $individualError) {
                    $this->command->error("Failed to create album '{$albumData['title']}': " . $individualError->getMessage());
                    Log::error("Individual album creation failed", [
                        'album_data' => $albumData,
                        'error' => $individualError->getMessage(),
                    ]);
                }
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
