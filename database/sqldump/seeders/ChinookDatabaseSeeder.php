<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Traits\ChinookSeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Chinook SQL Dump Seeder
 *
 * Master seeder that orchestrates the complete Chinook database seeding from SQL dump.
 * Handles proper dependency order and provides comprehensive error handling and progress tracking.
 *
 * Seeding Order:
 * 1. Independent tables (no foreign keys)
 * 2. Genre conversion to categories
 * 3. First level dependencies
 * 4. Second level dependencies
 * 5. Junction/relationship tables
 */
class ChinookDatabaseSeeder extends Seeder
{
    use ChinookSeederHelpers;

    private ChinookSeederMonitor $monitor;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->monitor = new ChinookSeederMonitor();
        $this->command->info('🎵 Starting Complete Chinook SQL Dump Seeding Process...');

        try {
            $this->monitor->startOperation('Complete Chinook Seeding');

            $this->executeInTransaction(function () {
                $this->seedIndependentTables();
                $this->seedGenreConversion();
                $this->seedFirstLevelDependencies();
                $this->seedSecondLevelDependencies();
                $this->seedJunctionTables();
            }, 'Complete Chinook SQL Dump Seeding');

            $this->monitor->completeOperation('Complete Chinook Seeding');
            $this->command->info("🎉 Chinook SQL Dump seeding completed successfully!");

            // Generate and display comprehensive report
            $this->monitor->displaySummary();
            $reportPath = $this->monitor->saveReport();
            $this->command->info("📄 Detailed report saved to: {$reportPath}");

            // Perform post-seeding optimization
            $this->performPostSeedingOptimization([
                'artists', 'albums', 'tracks', 'categories', 'media_types',
                'employees', 'customers', 'invoices', 'invoice_lines',
                'playlists', 'playlist_track', 'categorizables'
            ]);

        } catch (\Exception $e) {
            $this->monitor->failOperation('Complete Chinook Seeding', $e->getMessage());
            $this->command->error('❌ Chinook SQL Dump seeding failed: ' . $e->getMessage());

            // Still generate report for debugging
            $this->monitor->displaySummary();
            $reportPath = $this->monitor->saveReport();
            $this->command->error("📄 Error report saved to: {$reportPath}");

            throw $e;
        }
    }

    /**
     * Seed independent tables (no foreign key dependencies).
     */
    private function seedIndependentTables(): void
    {
        $this->monitor->startOperation('Phase 1: Independent Tables');
        $this->command->info('📊 Phase 1: Seeding Independent Tables...');

        $this->call([
            ChinookArtistsSeeder::class,
            ChinookMediaTypesSeeder::class,
            ChinookEmployeesSeeder::class,
            ChinookPlaylistsSeeder::class,
        ]);

        $this->monitor->completeOperation('Phase 1: Independent Tables');
        $this->command->info('✅ Phase 1 completed: Independent tables seeded');
    }

    /**
     * Seed genre conversion to categories.
     */
    private function seedGenreConversion(): void
    {
        $this->command->info('🏷️  Phase 2: Converting Genres to Categories...');

        $this->call([
            ChinookGenreCategorySeeder::class,
        ]);

        $this->command->info('✅ Phase 2 completed: Genres converted to categories');
    }

    /**
     * Seed first level dependencies.
     */
    private function seedFirstLevelDependencies(): void
    {
        $this->command->info('🔗 Phase 3: Seeding First Level Dependencies...');

        $this->call([
            ChinookAlbumsSeeder::class,      // depends on artists
            ChinookCustomersSeeder::class,   // depends on employees
        ]);

        $this->command->info('✅ Phase 3 completed: First level dependencies seeded');
    }

    /**
     * Seed second level dependencies.
     */
    private function seedSecondLevelDependencies(): void
    {
        $this->command->info('🎵 Phase 4: Seeding Second Level Dependencies...');

        $this->call([
            ChinookTracksSeeder::class,      // depends on albums, media_types, categories
            ChinookInvoicesSeeder::class,    // depends on customers
        ]);

        $this->command->info('✅ Phase 4 completed: Second level dependencies seeded');
    }

    /**
     * Seed junction and relationship tables.
     */
    private function seedJunctionTables(): void
    {
        $this->monitor->startOperation('Phase 5: Junction Tables');
        $this->command->info('🔄 Phase 5: Seeding Junction Tables...');

        $this->call([
            ChinookInvoiceLinesSeeder::class,    // depends on invoices, tracks
            ChinookPlaylistTrackSeeder::class,   // depends on playlists, tracks
            ChinookCategorizableSeeder::class,   // depends on tracks, categories
        ]);

        $this->monitor->completeOperation('Phase 5: Junction Tables');
        $this->command->info('✅ Phase 5 completed: Junction tables seeded');
    }

    /**
     * Display a summary of the seeding process.
     */
    private function displaySeedingSummary(): void
    {
        $this->command->info('');
        $this->command->info('📈 Seeding Summary:');
        $this->command->info('==================');

        try {
            $counts = [
                'Artists' => DB::table('artists')->count(),
                'Albums' => DB::table('albums')->count(),
                'Tracks' => DB::table('tracks')->count(),
                'Categories (Genres)' => DB::table('categories')->where('type', 'genre')->count(),
                'Media Types' => DB::table('media_types')->count(),
                'Employees' => DB::table('employees')->count(),
                'Customers' => DB::table('customers')->count(),
                'Invoices' => DB::table('invoices')->count(),
                'Invoice Lines' => DB::table('invoice_lines')->count(),
                'Playlists' => DB::table('playlists')->count(),
                'Playlist-Track Relations' => DB::table('playlist_track')->count(),
                'Track-Category Relations' => DB::table('categorizables')
                    ->where('categorizable_type', 'App\\Models\\Track')->count(),
            ];

            foreach ($counts as $table => $count) {
                $this->command->info("  • {$table}: " . number_format($count) . " records");
            }

            $this->command->info('');
            $this->command->info('🎯 Key Features Implemented:');
            $this->command->info('  • Genre → Category polymorphic conversion');
            $this->command->info('  • Foreign key integrity maintained');
            $this->command->info('  • Original IDs preserved for compatibility');
            $this->command->info('  • Comprehensive error handling');
            $this->command->info('  • Batch processing for performance');
            $this->command->info('  • Progress tracking and logging');

        } catch (\Exception $e) {
            $this->command->warn('Could not generate complete summary: ' . $e->getMessage());
        }
    }
}
