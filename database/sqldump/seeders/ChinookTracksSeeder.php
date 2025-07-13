<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Track;
use App\Models\Album;
use App\Models\MediaType;
use App\Models\Category;
use App\Models\User;
use App\Enums\CategoryType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Tracks Seeder
 *
 * Seeds the tracks table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with invoice_lines and playlist_track.
 * Handles the genre_id to category relationship conversion.
 * Parses the SQL dump file directly to handle the large dataset efficiently (~3,483 tracks).
 */
class ChinookTracksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Tracks seeding...');

        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $sqlDumpPath = database_path('sqldump/chinook.sql');

            if (!file_exists($sqlDumpPath)) {
                throw new \Exception("Chinook SQL dump file not found at: {$sqlDumpPath}");
            }

            $tracksData = $this->parseTracksFromSqlDump($sqlDumpPath);
            $this->seedTracks($tracksData, $systemUser);
        });
    }

    /**
     * Parse tracks data from the SQL dump file.
     */
    private function parseTracksFromSqlDump(string $filePath): array
    {
        $this->command->info('Parsing tracks data from SQL dump...');

        $content = file_get_contents($filePath);
        $tracks = [];

        // Find the tracks INSERT statement
        $pattern = '/INSERT INTO `tracks` \(`id`, `name`, `album_id`, `media_type_id`, `genre_id`, `composer`, `milliseconds`, `bytes`, `unit_price`\) VALUES\s*(.*?);/s';

        if (preg_match($pattern, $content, $matches)) {
            $valuesString = $matches[1];

            // Parse individual track records - handle complex cases with quotes and NULLs
            $lines = explode("\n", $valuesString);
            $currentRecord = '';

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $currentRecord .= $line;

                // Check if this line completes a record (ends with ),)
                if (preg_match('/\),\s*$/', $line) || preg_match('/\);\s*$/', $line)) {
                    $this->parseTrackRecord($currentRecord, $tracks);
                    $currentRecord = '';
                }
            }

            // Handle the last record if it doesn't end with a comma
            if (!empty($currentRecord)) {
                $this->parseTrackRecord($currentRecord, $tracks);
            }
        }

        $this->command->info("Parsed " . count($tracks) . " tracks from SQL dump");
        return $tracks;
    }

    /**
     * Parse a single track record from the SQL string.
     */
    private function parseTrackRecord(string $recordString, array &$tracks): void
    {
        // Pattern to match track records with proper handling of quotes and NULLs
        $pattern = '/\((\d+),\s*\'([^\']*(?:\'\'[^\']*)*)\',\s*(\d+|NULL),\s*(\d+),\s*(\d+|NULL),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(\d+),\s*(\d+|NULL),\s*([\d.]+)\)/';

        if (preg_match($pattern, $recordString, $match)) {
            $id = (int) $match[1];
            $name = str_replace("''", "'", $match[2]); // Handle escaped quotes
            $albumId = $match[3] === 'NULL' ? null : (int) $match[3];
            $mediaTypeId = (int) $match[4];
            $genreId = $match[5] === 'NULL' ? null : (int) $match[5];
            $composer = isset($match[6]) && $match[6] !== '' ? str_replace("''", "'", $match[6]) : null;
            $milliseconds = (int) $match[7];
            $bytes = $match[8] === 'NULL' ? null : (int) $match[8];
            $unitPrice = (float) $match[9];

            $tracks[] = [
                'id' => $id,
                'name' => $name,
                'album_id' => $albumId,
                'media_type_id' => $mediaTypeId,
                'genre_id' => $genreId, // Will be converted to category relationship
                'composer' => $composer,
                'milliseconds' => $milliseconds,
                'bytes' => $bytes,
                'unit_price' => $unitPrice,
            ];
        }
    }

    /**
     * Seed the tracks with the parsed data.
     */
    private function seedTracks(array $tracksData, ?User $systemUser): void
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $batchSize = 100; // Larger batch for tracks due to volume
        $batch = [];

        foreach ($tracksData as $trackData) {
            try {
                // Verify foreign key relationships
                if ($trackData['album_id'] && !Album::find($trackData['album_id'])) {
                    $this->command->warn("Album ID {$trackData['album_id']} not found for track '{$trackData['name']}', skipping...");
                    $skippedCount++;
                    continue;
                }

                if (!MediaType::find($trackData['media_type_id'])) {
                    $this->command->warn("Media Type ID {$trackData['media_type_id']} not found for track '{$trackData['name']}', skipping...");
                    $skippedCount++;
                    continue;
                }

                // Check if track already exists
                $existingTrack = Track::where('name', $trackData['name'])
                    ->where('album_id', $trackData['album_id'])
                    ->first();

                if ($existingTrack) {
                    $skippedCount++;
                    continue;
                }

                // Prepare track data for batch insert
                $batch[] = [
                    'id' => $trackData['id'],
                    'name' => $trackData['name'],
                    'album_id' => $trackData['album_id'],
                    'media_type_id' => $trackData['media_type_id'],
                    'composer' => $trackData['composer'],
                    'milliseconds' => $trackData['milliseconds'],
                    'bytes' => $trackData['bytes'],
                    'unit_price' => $trackData['unit_price'],
                    'track_number' => null,
                    'disc_number' => 1,
                    'lyrics' => null,
                    'metadata' => json_encode(['original_genre_id' => $trackData['genre_id']]),
                    'created_by' => $systemUser?->id,
                    'updated_by' => $systemUser?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Process batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch, $tracksData, $createdCount);
                    $createdCount += count($batch);
                    $batch = [];

                    // Progress indicator
                    if ($createdCount % 500 === 0) {
                        $this->command->info("Processed {$createdCount} tracks...");
                    }
                }

            } catch (\Exception $e) {
                $this->command->error("Failed to prepare track '{$trackData['name']}': " . $e->getMessage());
                Log::error("ChinookTracksSeeder failed for track", [
                    'track_data' => $trackData,
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
                continue;
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $this->processBatch($batch, $tracksData, $createdCount);
            $createdCount += count($batch);
        }

        $this->command->info("Chinook Tracks seeding completed: {$createdCount} created, {$skippedCount} skipped, {$errorCount} errors");

        // Note: Genre-to-category relationships are now handled by ChinookCategorizableSeeder
        $this->command->info("💡 Genre-category relationships will be created by ChinookCategorizableSeeder");
    }

    /**
     * Process a batch of tracks.
     */
    private function processBatch(array $batch, array $tracksData, int $currentCount): void
    {
        try {
            Track::insert($batch);
        } catch (\Exception $e) {
            $this->command->warn("Batch insert failed at position {$currentCount}, trying individual inserts...");

            foreach ($batch as $trackData) {
                try {
                    Track::create($trackData);
                } catch (\Exception $individualError) {
                    $this->command->error("Failed to create track '{$trackData['name']}': " . $individualError->getMessage());
                    Log::error("Individual track creation failed", [
                        'track_data' => $trackData,
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
