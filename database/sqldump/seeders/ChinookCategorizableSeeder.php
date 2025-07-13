<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\Traits\ChinookSeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Categorizable Seeder
 * 
 * Creates polymorphic category relationships for Chinook data by populating the
 * categorizables pivot table. Specifically handles track-to-genre-category relationships
 * by mapping original genre_id values from tracks to the corresponding Category records
 * with CategoryType::GENRE.
 * 
 * This seeder establishes the bridge between the original Chinook genre system and
 * the modern Laravel polymorphic category architecture.
 * 
 * Dependencies:
 * - ChinookTracksSeeder (tracks must exist)
 * - ChinookGenreCategorySeeder (genre categories must exist)
 * 
 * Data Flow:
 * 1. Extract genre_id from track metadata (stored during ChinookTracksSeeder)
 * 2. Map genre_id to corresponding Category with type=GENRE
 * 3. Create categorizable relationship: Track -> Category
 * 4. Set as primary genre relationship for each track
 */
class ChinookCategorizableSeeder extends Seeder
{
    use ChinookSeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startTime = microtime(true);
        $this->command->info('🏷️  Starting Chinook Categorizable relationships seeding...');
        
        // Check if we should skip seeding
        if ($this->shouldSkipSeeding('categorizables', 'Chinook Categorizable')) {
            return;
        }

        $this->executeInTransaction(function () {
            $systemUser = $this->getSystemUser();
            
            // Extract track-genre mappings from existing data
            $trackGenreMappings = $this->extractTrackGenreMappings();
            
            if (empty($trackGenreMappings)) {
                $this->command->warn('No track-genre mappings found. Ensure ChinookTracksSeeder has run.');
                return ['processed' => 0, 'skipped' => 0, 'errors' => 0];
            }
            
            // Validate foreign key relationships
            $validMappings = $this->validateTrackGenreMappings($trackGenreMappings);
            
            // Process in optimized batches
            $results = $this->processBatchesWithMemoryManagement(
                $validMappings,
                [$this, 'processCategorizableBatch'],
                'Categorizable relationships seeding',
                200 // batch size optimized for relationship data
            );
            
            // Update statistics
            $this->updateCategorizableStatistics();
            
            return $results;
        }, 'Chinook Categorizable relationships seeding');
        
        $duration = microtime(true) - $startTime;
        $this->logSeedingStats('ChinookCategorizableSeeder', [
            'processed' => DB::table('categorizables')
                ->where('categorizable_type', Track::class)
                ->count(),
            'skipped' => 0,
            'errors' => 0,
        ], $duration);
    }

    /**
     * Extract track-genre mappings from existing track metadata.
     * 
     * The ChinookTracksSeeder stores the original genre_id in the track's metadata
     * field as JSON: {"original_genre_id": 1}
     */
    private function extractTrackGenreMappings(): array
    {
        $this->command->info('📊 Extracting track-genre mappings from track metadata...');
        
        $mappings = [];
        
        // Query tracks with metadata containing original_genre_id
        $tracks = DB::table('tracks')
            ->select('id', 'metadata')
            ->whereNotNull('metadata')
            ->get();
        
        foreach ($tracks as $track) {
            $metadata = json_decode($track->metadata, true);
            
            if (isset($metadata['original_genre_id']) && $metadata['original_genre_id']) {
                $mappings[] = [
                    'track_id' => $track->id,
                    'original_genre_id' => $metadata['original_genre_id'],
                ];
            }
        }
        
        $this->command->info("📈 Found " . count($mappings) . " track-genre mappings");
        return $mappings;
    }

    /**
     * Validate track-genre mappings against existing data.
     */
    private function validateTrackGenreMappings(array $mappings): array
    {
        $this->command->info('🔍 Validating track-genre mappings...');
        
        // Get all valid track IDs
        $validTrackIds = DB::table('tracks')->pluck('id')->toArray();
        $validTrackIds = array_flip($validTrackIds); // For O(1) lookup
        
        // Get all valid genre category IDs (original genre IDs preserved as category IDs)
        $validGenreCategories = DB::table('categories')
            ->where('type', CategoryType::GENRE->value)
            ->pluck('id')
            ->toArray();
        $validGenreCategories = array_flip($validGenreCategories); // For O(1) lookup
        
        $validMappings = [];
        $invalidCount = 0;
        
        foreach ($mappings as $mapping) {
            $isValid = true;
            
            // Validate track exists
            if (!isset($validTrackIds[$mapping['track_id']])) {
                $this->command->warn("⚠️  Track ID {$mapping['track_id']} not found");
                $isValid = false;
            }
            
            // Validate genre category exists
            if (!isset($validGenreCategories[$mapping['original_genre_id']])) {
                $this->command->warn("⚠️  Genre category ID {$mapping['original_genre_id']} not found");
                $isValid = false;
            }
            
            if ($isValid) {
                $validMappings[] = $mapping;
            } else {
                $invalidCount++;
            }
        }
        
        if ($invalidCount > 0) {
            $this->command->warn("🔍 Filtered out {$invalidCount} invalid mappings");
        }
        
        $this->command->info("✅ Validated " . count($validMappings) . " track-genre mappings");
        return $validMappings;
    }

    /**
     * Process a batch of categorizable relationships.
     */
    public function processCategorizableBatch(array $batch): array
    {
        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $systemUser = $this->getSystemUser();
        
        $categorizableData = [];
        
        foreach ($batch as $mapping) {
            try {
                // Check if relationship already exists
                $existingRelationship = DB::table('categorizables')
                    ->where('category_id', $mapping['original_genre_id'])
                    ->where('categorizable_type', Track::class)
                    ->where('categorizable_id', $mapping['track_id'])
                    ->exists();
                
                if ($existingRelationship) {
                    $skipped++;
                    continue;
                }
                
                // Prepare categorizable data
                $categorizableData[] = [
                    'category_id' => $mapping['original_genre_id'],
                    'categorizable_type' => Track::class,
                    'categorizable_id' => $mapping['track_id'],
                    'sort_order' => 1, // Primary genre gets sort order 1
                    'metadata' => json_encode([
                        'is_primary' => true,
                        'source' => 'chinook_import',
                        'confidence' => 1.0,
                        'original_genre_id' => $mapping['original_genre_id'],
                    ]),
                    'created_by' => $systemUser?->id,
                    'updated_by' => $systemUser?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                $processed++;
                
            } catch (\Exception $e) {
                $errors++;
                $this->command->error("Failed to prepare categorizable for track {$mapping['track_id']}: " . $e->getMessage());
                Log::error("ChinookCategorizableSeeder failed for mapping", [
                    'mapping' => $mapping,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Batch insert categorizable relationships
        if (!empty($categorizableData)) {
            try {
                DB::table('categorizables')->insert($categorizableData);
            } catch (\Exception $e) {
                $this->command->error("Batch insert failed: " . $e->getMessage());
                
                // Try individual inserts
                foreach ($categorizableData as $data) {
                    try {
                        DB::table('categorizables')->insert($data);
                    } catch (\Exception $individualError) {
                        $errors++;
                        $this->command->error("Individual insert failed for track {$data['categorizable_id']}: " . $individualError->getMessage());
                    }
                }
            }
        }
        
        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Update categorizable statistics and metadata.
     */
    private function updateCategorizableStatistics(): void
    {
        $this->command->info('📊 Updating categorizable statistics...');
        
        try {
            // Update category usage counts
            $categoryUsage = DB::table('categorizables')
                ->select('category_id', DB::raw('COUNT(*) as usage_count'))
                ->where('categorizable_type', Track::class)
                ->groupBy('category_id')
                ->get();
            
            foreach ($categoryUsage as $usage) {
                DB::table('categories')
                    ->where('id', $usage->category_id)
                    ->update([
                        'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.usage_count', {$usage->usage_count})"),
                        'updated_at' => now(),
                    ]);
            }
            
            $this->command->info("✅ Updated usage counts for " . count($categoryUsage) . " categories");
            
        } catch (\Exception $e) {
            $this->command->warn("⚠️  Failed to update category statistics: " . $e->getMessage());
        }
    }

    /**
     * Get the system user for user stamps.
     */
    private function getSystemUser(): ?User
    {
        return User::where('email', 'system@chinook.com')->first();
    }

    /**
     * Validate the integrity of created relationships.
     */
    public function validateRelationshipIntegrity(): array
    {
        $this->command->info('🔍 Validating relationship integrity...');
        
        $results = [
            'total_relationships' => 0,
            'tracks_with_genres' => 0,
            'tracks_without_genres' => 0,
            'orphaned_relationships' => 0,
            'duplicate_relationships' => 0,
            'genre_categories_used' => 0,
        ];
        
        // Count total relationships
        $results['total_relationships'] = DB::table('categorizables')
            ->where('categorizable_type', Track::class)
            ->count();
        
        // Count tracks with genre relationships
        $results['tracks_with_genres'] = DB::table('tracks')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('categorizables')
                    ->whereColumn('categorizables.categorizable_id', 'tracks.id')
                    ->where('categorizables.categorizable_type', Track::class);
            })
            ->count();
        
        // Count tracks without genre relationships
        $results['tracks_without_genres'] = DB::table('tracks')->count() - $results['tracks_with_genres'];
        
        // Check for orphaned relationships
        $results['orphaned_relationships'] = DB::table('categorizables')
            ->leftJoin('tracks', function ($join) {
                $join->on('categorizables.categorizable_id', '=', 'tracks.id')
                    ->where('categorizables.categorizable_type', Track::class);
            })
            ->leftJoin('categories', 'categorizables.category_id', '=', 'categories.id')
            ->where('categorizables.categorizable_type', Track::class)
            ->where(function ($query) {
                $query->whereNull('tracks.id')
                    ->orWhereNull('categories.id');
            })
            ->count();
        
        // Check for duplicate relationships
        $results['duplicate_relationships'] = DB::table('categorizables')
            ->select('category_id', 'categorizable_type', 'categorizable_id', DB::raw('COUNT(*) as count'))
            ->where('categorizable_type', Track::class)
            ->groupBy('category_id', 'categorizable_type', 'categorizable_id')
            ->having('count', '>', 1)
            ->count();
        
        // Count genre categories used
        $results['genre_categories_used'] = DB::table('categorizables')
            ->join('categories', 'categorizables.category_id', '=', 'categories.id')
            ->where('categorizables.categorizable_type', Track::class)
            ->where('categories.type', CategoryType::GENRE->value)
            ->distinct('categorizables.category_id')
            ->count();
        
        return $results;
    }
}
