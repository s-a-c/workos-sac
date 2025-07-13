<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ChinookSeederMonitor;
use Database\Seeders\ChinookSqlDumpSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test Chinook Seeders Command
 *
 * Comprehensive testing command for Chinook SQL dump seeders.
 * Validates data integrity, performance, and proper functionality.
 */
class TestChinookSeeders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'chinook:test-seeders
                           {--fresh : Run with fresh migration}
                           {--validate-only : Only run validation without seeding}
                           {--performance : Include performance benchmarks}';

    /**
     * The console command description.
     */
    protected $description = 'Test and validate Chinook SQL dump seeders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Chinook Seeders Testing Suite');
        $this->info('================================');

        try {
            if ($this->option('validate-only')) {
                return $this->runValidationOnly();
            }

            if ($this->option('fresh')) {
                $this->runFreshMigration();
            }

            $this->runSeedingTests();
            $this->runValidationTests();

            if ($this->option('performance')) {
                $this->runPerformanceTests();
            }

            $this->info('✅ All tests passed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Run validation tests only.
     */
    private function runValidationOnly(): int
    {
        $this->info('🔍 Running validation tests only...');

        if (!$this->hasData()) {
            $this->error('No Chinook data found. Run seeding first.');
            return Command::FAILURE;
        }

        $this->runValidationTests();
        $this->info('✅ Validation tests completed!');
        return Command::SUCCESS;
    }

    /**
     * Run fresh migration.
     */
    private function runFreshMigration(): void
    {
        $this->info('🔄 Running fresh migration...');

        if (!$this->confirm('This will destroy all existing data. Continue?')) {
            $this->error('Operation cancelled.');
            exit(Command::FAILURE);
        }

        Artisan::call('migrate:fresh', [], $this->getOutput());
        $this->info('✅ Fresh migration completed');
    }

    /**
     * Run seeding tests.
     */
    private function runSeedingTests(): void
    {
        $this->info('🌱 Running seeding tests...');

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Run the seeder
        Artisan::call('db:seed', [
            '--class' => ChinookSqlDumpSeeder::class,
        ], $this->getOutput());

        $duration = round(microtime(true) - $startTime, 2);
        $memoryUsed = $this->formatBytes(memory_get_peak_usage(true) - $startMemory);

        $this->info("✅ Seeding completed in {$duration}s using {$memoryUsed}");
    }

    /**
     * Run validation tests.
     */
    private function runValidationTests(): void
    {
        $this->info('🔍 Running validation tests...');

        $monitor = new ChinookSeederMonitor();
        $validation = $monitor->validateDataIntegrity();

        // Test data counts
        $this->testDataCounts();

        // Test foreign key integrity
        $this->testForeignKeyIntegrity($validation['foreign_keys']);

        // Test data consistency
        $this->testDataConsistency($validation['data_consistency']);

        // Test category relationships
        $this->testCategoryRelationships($validation['category_relationships']);

        // Test categorizable relationships integrity
        $this->testCategorizableIntegrity();

        // Test orphaned records
        $this->testOrphanedRecords($validation['orphaned_records']);

        $this->info('✅ All validation tests passed');
    }

    /**
     * Test expected data counts.
     */
    private function testDataCounts(): void
    {
        $this->info('📊 Testing data counts...');

        $expectedCounts = [
            'artists' => 275,
            'albums' => 347,
            'tracks' => 3483,
            'media_types' => 5,
            'employees' => 8,
            'customers' => 59,
            'invoices' => 412,
            'invoice_lines' => 2240,
            'playlists' => 18,
        ];

        foreach ($expectedCounts as $table => $expectedCount) {
            $actualCount = DB::table($table)->count();

            if ($actualCount !== $expectedCount) {
                throw new \Exception(
                    "Data count mismatch for {$table}: expected {$expectedCount}, got {$actualCount}"
                );
            }

            $this->line("  ✅ {$table}: {$actualCount} records");
        }

        // Test genre categories
        $genreCategories = DB::table('categories')->where('type', 'genre')->count();
        if ($genreCategories !== 25) {
            throw new \Exception("Expected 25 genre categories, got {$genreCategories}");
        }
        $this->line("  ✅ categories (genres): {$genreCategories} records");

        // Test playlist-track relationships
        $playlistTracks = DB::table('playlist_track')->count();
        if ($playlistTracks < 8000) { // Allow some variance
            throw new \Exception("Expected ~8715 playlist-track relationships, got {$playlistTracks}");
        }
        $this->line("  ✅ playlist_track: {$playlistTracks} relationships");

        // Test categorizable relationships (track-genre)
        $categorizableRelationships = DB::table('categorizables')
            ->where('categorizable_type', 'App\\Models\\Track')
            ->count();
        if ($categorizableRelationships < 3000) { // Allow some variance
            throw new \Exception("Expected ~3483 track-category relationships, got {$categorizableRelationships}");
        }
        $this->line("  ✅ categorizables (tracks): {$categorizableRelationships} relationships");
    }

    /**
     * Test foreign key integrity.
     */
    private function testForeignKeyIntegrity(array $foreignKeyResults): void
    {
        $this->info('🔗 Testing foreign key integrity...');

        foreach ($foreignKeyResults as $relationship => $result) {
            if (!$result['valid']) {
                throw new \Exception(
                    "Foreign key integrity violation: {$relationship} has {$result['invalid_count']} invalid references"
                );
            }
            $this->line("  ✅ {$relationship}: valid");
        }
    }

    /**
     * Test data consistency.
     */
    private function testDataConsistency(array $consistencyResults): void
    {
        $this->info('📋 Testing data consistency...');

        if (!$consistencyResults['invoice_totals']['valid']) {
            throw new \Exception(
                "Invoice total inconsistencies found: {$consistencyResults['invoice_totals']['discrepancies']} discrepancies"
            );
        }
        $this->line('  ✅ Invoice totals: consistent');

        if (!$consistencyResults['playlist_counts']['valid']) {
            throw new \Exception(
                "Playlist count inconsistencies found: {$consistencyResults['playlist_counts']['discrepancies']} discrepancies"
            );
        }
        $this->line('  ✅ Playlist counts: consistent');
    }

    /**
     * Test category relationships.
     */
    private function testCategoryRelationships(array $categoryResults): void
    {
        $this->info('🏷️  Testing category relationships...');

        if ($categoryResults['genre_categories'] !== 25) {
            throw new \Exception(
                "Expected 25 genre categories, found {$categoryResults['genre_categories']}"
            );
        }
        $this->line('  ✅ Genre categories: 25 found');

        if ($categoryResults['track_category_relations'] < 3000) {
            throw new \Exception(
                "Expected ~3483 track-category relationships, found {$categoryResults['track_category_relations']}"
            );
        }
        $this->line("  ✅ Track-category relationships: {$categoryResults['track_category_relations']} found");
    }

    /**
     * Test categorizable relationships integrity.
     */
    private function testCategorizableIntegrity(): void
    {
        $this->info('🔗 Testing categorizable relationships integrity...');

        // Test for orphaned categorizable relationships
        $orphanedCategorizables = DB::table('categorizables')
            ->leftJoin('tracks', function ($join) {
                $join->on('categorizables.categorizable_id', '=', 'tracks.id')
                    ->where('categorizables.categorizable_type', 'App\\Models\\Track');
            })
            ->leftJoin('categories', 'categorizables.category_id', '=', 'categories.id')
            ->where('categorizables.categorizable_type', 'App\\Models\\Track')
            ->where(function ($query) {
                $query->whereNull('tracks.id')
                    ->orWhereNull('categories.id');
            })
            ->count();

        if ($orphanedCategorizables > 0) {
            throw new \Exception("Found {$orphanedCategorizables} orphaned categorizable relationships");
        }
        $this->line('  ✅ No orphaned categorizable relationships');

        // Test for duplicate relationships
        $duplicateCategorizables = DB::table('categorizables')
            ->select('category_id', 'categorizable_type', 'categorizable_id', DB::raw('COUNT(*) as count'))
            ->where('categorizable_type', 'App\\Models\\Track')
            ->groupBy('category_id', 'categorizable_type', 'categorizable_id')
            ->having('count', '>', 1)
            ->count();

        if ($duplicateCategorizables > 0) {
            throw new \Exception("Found {$duplicateCategorizables} duplicate categorizable relationships");
        }
        $this->line('  ✅ No duplicate categorizable relationships');

        // Test that all tracks have genre relationships
        $tracksWithoutGenres = DB::table('tracks')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('categorizables')
                    ->join('categories', 'categorizables.category_id', '=', 'categories.id')
                    ->whereColumn('categorizables.categorizable_id', 'tracks.id')
                    ->where('categorizables.categorizable_type', 'App\\Models\\Track')
                    ->where('categories.type', 'genre');
            })
            ->count();

        if ($tracksWithoutGenres > 100) { // Allow some tracks without genres
            throw new \Exception("Found {$tracksWithoutGenres} tracks without genre relationships (expected < 100)");
        }
        $this->line("  ✅ Tracks without genres: {$tracksWithoutGenres} (acceptable)");
    }

    /**
     * Test for orphaned records.
     */
    private function testOrphanedRecords(array $orphanedResults): void
    {
        $this->info('🔍 Testing for orphaned records...');

        if ($orphanedResults['orphaned_albums'] > 0) {
            throw new \Exception(
                "Found {$orphanedResults['orphaned_albums']} orphaned albums"
            );
        }
        $this->line('  ✅ No orphaned albums');

        if ($orphanedResults['orphaned_tracks'] > 0) {
            throw new \Exception(
                "Found {$orphanedResults['orphaned_tracks']} orphaned tracks"
            );
        }
        $this->line('  ✅ No orphaned tracks');
    }

    /**
     * Run performance tests.
     */
    private function runPerformanceTests(): void
    {
        $this->info('⚡ Running performance tests...');

        // Test query performance
        $this->testQueryPerformance();

        // Test memory usage
        $this->testMemoryUsage();

        $this->info('✅ Performance tests completed');
    }

    /**
     * Test query performance.
     */
    private function testQueryPerformance(): void
    {
        $queries = [
            'Artists count' => 'SELECT COUNT(*) FROM artists',
            'Albums with artists' => 'SELECT COUNT(*) FROM albums a JOIN artists ar ON a.artist_id = ar.id',
            'Tracks with categories' => 'SELECT COUNT(*) FROM tracks t JOIN categorizables c ON t.id = c.categorizable_id WHERE c.categorizable_type = "App\\\\Models\\\\Track"',
            'Invoice totals' => 'SELECT SUM(total) FROM invoices',
            'Complex join' => 'SELECT COUNT(*) FROM tracks t JOIN albums a ON t.album_id = a.id JOIN artists ar ON a.artist_id = ar.id',
        ];

        foreach ($queries as $name => $sql) {
            $startTime = microtime(true);
            DB::select($sql);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->line("  ⚡ {$name}: {$duration}ms");

            if ($duration > 1000) { // Warn if query takes more than 1 second
                $this->warn("    ⚠️  Query is slow (>{$duration}ms)");
            }
        }
    }

    /**
     * Test memory usage.
     */
    private function testMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->line('  🧠 Current memory: ' . $this->formatBytes($memoryUsage));
        $this->line('  🧠 Peak memory: ' . $this->formatBytes($peakMemory));

        if ($peakMemory > 512 * 1024 * 1024) { // Warn if peak memory > 512MB
            $this->warn('    ⚠️  High memory usage detected');
        }
    }

    /**
     * Check if Chinook data exists.
     */
    private function hasData(): bool
    {
        return Schema::hasTable('artists') && DB::table('artists')->count() > 0;
    }

    /**
     * Format bytes into human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
