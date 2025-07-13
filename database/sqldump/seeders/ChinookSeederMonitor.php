<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/**
 * Chinook Seeder Monitor
 * 
 * Provides comprehensive monitoring, validation, and reporting for Chinook seeding operations.
 * Includes data integrity checks, performance metrics, and detailed reporting.
 */
class ChinookSeederMonitor
{
    private array $metrics = [];
    private array $errors = [];
    private array $warnings = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Start monitoring a seeding operation.
     */
    public function startOperation(string $operation): void
    {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'status' => 'running',
        ];
        
        Log::info("Seeding operation started", ['operation' => $operation]);
    }

    /**
     * Complete monitoring a seeding operation.
     */
    public function completeOperation(string $operation, array $stats = []): void
    {
        if (!isset($this->metrics[$operation])) {
            $this->addWarning("Operation '{$operation}' was not properly started");
            return;
        }

        $this->metrics[$operation]['end_time'] = microtime(true);
        $this->metrics[$operation]['memory_end'] = memory_get_usage(true);
        $this->metrics[$operation]['duration'] = 
            $this->metrics[$operation]['end_time'] - $this->metrics[$operation]['start_time'];
        $this->metrics[$operation]['memory_used'] = 
            $this->metrics[$operation]['memory_end'] - $this->metrics[$operation]['memory_start'];
        $this->metrics[$operation]['stats'] = $stats;
        $this->metrics[$operation]['status'] = 'completed';

        Log::info("Seeding operation completed", [
            'operation' => $operation,
            'duration' => $this->metrics[$operation]['duration'],
            'stats' => $stats,
        ]);
    }

    /**
     * Mark an operation as failed.
     */
    public function failOperation(string $operation, string $error): void
    {
        if (isset($this->metrics[$operation])) {
            $this->metrics[$operation]['status'] = 'failed';
            $this->metrics[$operation]['error'] = $error;
        }
        
        $this->addError("Operation '{$operation}' failed: {$error}");
    }

    /**
     * Add an error to the monitoring log.
     */
    public function addError(string $error): void
    {
        $this->errors[] = [
            'timestamp' => now(),
            'message' => $error,
        ];
        
        Log::error("Seeding error", ['error' => $error]);
    }

    /**
     * Add a warning to the monitoring log.
     */
    public function addWarning(string $warning): void
    {
        $this->warnings[] = [
            'timestamp' => now(),
            'message' => $warning,
        ];
        
        Log::warning("Seeding warning", ['warning' => $warning]);
    }

    /**
     * Validate data integrity after seeding.
     */
    public function validateDataIntegrity(): array
    {
        $validationResults = [];

        // Check foreign key constraints
        $validationResults['foreign_keys'] = $this->validateForeignKeys();
        
        // Check data consistency
        $validationResults['data_consistency'] = $this->validateDataConsistency();
        
        // Check for orphaned records
        $validationResults['orphaned_records'] = $this->checkOrphanedRecords();
        
        // Validate category relationships
        $validationResults['category_relationships'] = $this->validateCategoryRelationships();

        return $validationResults;
    }

    /**
     * Validate foreign key constraints.
     */
    private function validateForeignKeys(): array
    {
        $results = [];
        
        $foreignKeyChecks = [
            'albums' => [
                'artist_id' => 'artists',
            ],
            'tracks' => [
                'album_id' => 'albums',
                'media_type_id' => 'media_types',
            ],
            'customers' => [
                'support_rep_id' => 'employees',
            ],
            'invoices' => [
                'customer_id' => 'customers',
            ],
            'invoice_lines' => [
                'invoice_id' => 'invoices',
                'track_id' => 'tracks',
            ],
            'playlist_track' => [
                'playlist_id' => 'playlists',
                'track_id' => 'tracks',
            ],
        ];

        foreach ($foreignKeyChecks as $table => $foreignKeys) {
            foreach ($foreignKeys as $column => $referencedTable) {
                $invalidCount = DB::table($table)
                    ->leftJoin($referencedTable, "{$table}.{$column}", '=', "{$referencedTable}.id")
                    ->whereNotNull("{$table}.{$column}")
                    ->whereNull("{$referencedTable}.id")
                    ->count();

                $results["{$table}.{$column}"] = [
                    'valid' => $invalidCount === 0,
                    'invalid_count' => $invalidCount,
                ];

                if ($invalidCount > 0) {
                    $this->addError("Foreign key constraint violation: {$table}.{$column} has {$invalidCount} invalid references");
                }
            }
        }

        return $results;
    }

    /**
     * Validate data consistency.
     */
    private function validateDataConsistency(): array
    {
        $results = [];

        // Check invoice totals match invoice lines
        $invoiceDiscrepancies = DB::select("
            SELECT i.id, i.total as invoice_total, 
                   COALESCE(SUM(il.unit_price * il.quantity), 0) as calculated_total
            FROM invoices i
            LEFT JOIN invoice_lines il ON i.id = il.invoice_id
            GROUP BY i.id, i.total
            HAVING ABS(i.total - COALESCE(SUM(il.unit_price * il.quantity), 0)) > 0.01
        ");

        $results['invoice_totals'] = [
            'valid' => empty($invoiceDiscrepancies),
            'discrepancies' => count($invoiceDiscrepancies),
        ];

        // Check playlist track counts
        $playlistDiscrepancies = DB::select("
            SELECT p.id, p.total_tracks as recorded_count,
                   COUNT(pt.track_id) as actual_count
            FROM playlists p
            LEFT JOIN playlist_track pt ON p.id = pt.playlist_id
            GROUP BY p.id, p.total_tracks
            HAVING p.total_tracks != COUNT(pt.track_id)
        ");

        $results['playlist_counts'] = [
            'valid' => empty($playlistDiscrepancies),
            'discrepancies' => count($playlistDiscrepancies),
        ];

        return $results;
    }

    /**
     * Check for orphaned records.
     */
    private function checkOrphanedRecords(): array
    {
        $results = [];

        // Check for albums without artists
        $orphanedAlbums = DB::table('albums')
            ->leftJoin('artists', 'albums.artist_id', '=', 'artists.id')
            ->whereNull('artists.id')
            ->count();

        $results['orphaned_albums'] = $orphanedAlbums;

        // Check for tracks without albums
        $orphanedTracks = DB::table('tracks')
            ->leftJoin('albums', 'tracks.album_id', '=', 'albums.id')
            ->whereNotNull('tracks.album_id')
            ->whereNull('albums.id')
            ->count();

        $results['orphaned_tracks'] = $orphanedTracks;

        return $results;
    }

    /**
     * Validate category relationships.
     */
    private function validateCategoryRelationships(): array
    {
        $results = [];

        // Check genre categories exist
        $genreCategories = DB::table('categories')
            ->where('type', 'genre')
            ->count();

        $results['genre_categories'] = $genreCategories;

        // Check track-category relationships
        $trackCategoryRelations = DB::table('categorizables')
            ->where('categorizable_type', 'App\\Models\\Track')
            ->count();

        $results['track_category_relations'] = $trackCategoryRelations;

        return $results;
    }

    /**
     * Generate a comprehensive report.
     */
    public function generateReport(): array
    {
        $totalDuration = microtime(true) - $this->startTime;
        
        $report = [
            'summary' => [
                'total_duration' => round($totalDuration, 2),
                'operations_count' => count($this->metrics),
                'errors_count' => count($this->errors),
                'warnings_count' => count($this->warnings),
                'peak_memory' => memory_get_peak_usage(true),
                'final_memory' => memory_get_usage(true),
            ],
            'operations' => $this->metrics,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'validation' => $this->validateDataIntegrity(),
        ];

        return $report;
    }

    /**
     * Save report to file.
     */
    public function saveReport(string $filename = null): string
    {
        $filename = $filename ?: 'chinook_seeding_report_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path("logs/{$filename}");
        
        $report = $this->generateReport();
        File::put($filepath, json_encode($report, JSON_PRETTY_PRINT));
        
        return $filepath;
    }

    /**
     * Display a summary report.
     */
    public function displaySummary(): void
    {
        $report = $this->generateReport();
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🎵 CHINOOK SEEDING REPORT\n";
        echo str_repeat('=', 60) . "\n";
        
        echo "📊 Summary:\n";
        echo "  • Total Duration: " . $report['summary']['total_duration'] . "s\n";
        echo "  • Operations: " . $report['summary']['operations_count'] . "\n";
        echo "  • Errors: " . $report['summary']['errors_count'] . "\n";
        echo "  • Warnings: " . $report['summary']['warnings_count'] . "\n";
        echo "  • Peak Memory: " . $this->formatBytes($report['summary']['peak_memory']) . "\n";
        
        if (!empty($this->errors)) {
            echo "\n❌ Errors:\n";
            foreach ($this->errors as $error) {
                echo "  • " . $error['message'] . "\n";
            }
        }
        
        if (!empty($this->warnings)) {
            echo "\n⚠️  Warnings:\n";
            foreach ($this->warnings as $warning) {
                echo "  • " . $warning['message'] . "\n";
            }
        }
        
        echo "\n📈 Operations:\n";
        foreach ($this->metrics as $operation => $metrics) {
            $status = $metrics['status'] === 'completed' ? '✅' : 
                     ($metrics['status'] === 'failed' ? '❌' : '⏳');
            $duration = isset($metrics['duration']) ? round($metrics['duration'], 2) . 's' : 'N/A';
            echo "  {$status} {$operation}: {$duration}\n";
        }
        
        echo "\n" . str_repeat('=', 60) . "\n";
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
