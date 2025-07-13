<?php

declare(strict_types=1);

namespace Database\Seeders\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Chinook Seeder Helpers Trait
 * 
 * Provides common functionality for all Chinook seeders including:
 * - Progress tracking with memory management
 * - Comprehensive error handling
 * - Performance monitoring
 * - Batch processing utilities
 * - Transaction management
 */
trait ChinookSeederHelpers
{
    /**
     * Track seeding progress with memory management.
     */
    protected function trackProgress(string $operation, int $current, int $total, int $interval = 100): void
    {
        if ($current % $interval === 0 || $current === $total) {
            $percentage = round(($current / $total) * 100, 1);
            $memoryUsage = $this->formatBytes(memory_get_usage(true));
            $peakMemory = $this->formatBytes(memory_get_peak_usage(true));
            
            $this->command->info(
                "📊 {$operation}: {$current}/{$total} ({$percentage}%) | " .
                "Memory: {$memoryUsage} | Peak: {$peakMemory}"
            );
            
            // Force garbage collection on large datasets
            if ($current % 1000 === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Execute with comprehensive error handling and retry logic.
     */
    protected function executeWithRetry(callable $operation, string $description, int $maxRetries = 3): bool
    {
        $attempt = 1;
        
        while ($attempt <= $maxRetries) {
            try {
                $startTime = microtime(true);
                $result = $operation();
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                
                $this->command->info("✅ {$description} completed in {$duration}ms");
                return true;
                
            } catch (\Exception $e) {
                $this->command->warn("⚠️  Attempt {$attempt}/{$maxRetries} failed for {$description}: " . $e->getMessage());
                
                Log::warning("Seeder operation failed", [
                    'operation' => $description,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                if ($attempt === $maxRetries) {
                    $this->command->error("❌ {$description} failed after {$maxRetries} attempts");
                    Log::error("Seeder operation failed permanently", [
                        'operation' => $description,
                        'final_error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
                
                // Exponential backoff
                $delay = pow(2, $attempt - 1);
                $this->command->info("⏳ Retrying in {$delay} seconds...");
                sleep($delay);
                
                $attempt++;
            }
        }
        
        return false;
    }

    /**
     * Process data in optimized batches with memory management.
     */
    protected function processBatchesWithMemoryManagement(
        array $data,
        callable $processor,
        string $operation,
        int $batchSize = 100,
        int $memoryLimit = 128 * 1024 * 1024 // 128MB
    ): array {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'batches' => 0,
        ];
        
        $total = count($data);
        $chunks = array_chunk($data, $batchSize);
        
        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                // Check memory usage before processing
                $currentMemory = memory_get_usage(true);
                if ($currentMemory > $memoryLimit) {
                    $this->command->warn("🧠 Memory limit approaching, forcing garbage collection...");
                    gc_collect_cycles();
                    
                    $newMemory = memory_get_usage(true);
                    $freed = $this->formatBytes($currentMemory - $newMemory);
                    $this->command->info("🗑️  Freed {$freed} of memory");
                }
                
                $batchResult = $this->executeWithRetry(
                    fn() => $processor($chunk),
                    "Batch " . ($chunkIndex + 1) . " of " . count($chunks) . " for {$operation}"
                );
                
                if (is_array($batchResult)) {
                    $results['processed'] += $batchResult['processed'] ?? count($chunk);
                    $results['skipped'] += $batchResult['skipped'] ?? 0;
                    $results['errors'] += $batchResult['errors'] ?? 0;
                } else {
                    $results['processed'] += count($chunk);
                }
                
                $results['batches']++;
                
                // Progress tracking
                $this->trackProgress($operation, $results['processed'], $total, 50);
                
            } catch (\Exception $e) {
                $results['errors'] += count($chunk);
                $this->command->error("❌ Batch processing failed: " . $e->getMessage());
                
                Log::error("Batch processing failed", [
                    'operation' => $operation,
                    'chunk_index' => $chunkIndex,
                    'chunk_size' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Validate foreign key relationships before processing.
     */
    protected function validateForeignKeys(array $data, array $foreignKeyChecks): array
    {
        $validData = [];
        $invalidCount = 0;
        
        foreach ($data as $record) {
            $isValid = true;
            
            foreach ($foreignKeyChecks as $field => $config) {
                $value = $record[$field] ?? null;
                
                // Skip validation if field is nullable and value is null
                if ($value === null && ($config['nullable'] ?? false)) {
                    continue;
                }
                
                // Check if foreign key exists
                if ($value !== null) {
                    $exists = DB::table($config['table'])
                        ->where($config['column'] ?? 'id', $value)
                        ->exists();
                    
                    if (!$exists) {
                        $isValid = false;
                        $this->command->warn(
                            "⚠️  Invalid foreign key: {$field}={$value} not found in {$config['table']}"
                        );
                        break;
                    }
                }
            }
            
            if ($isValid) {
                $validData[] = $record;
            } else {
                $invalidCount++;
            }
        }
        
        if ($invalidCount > 0) {
            $this->command->warn("🔍 Filtered out {$invalidCount} records with invalid foreign keys");
        }
        
        return $validData;
    }

    /**
     * Create database transaction with proper error handling.
     */
    protected function executeInTransaction(callable $operation, string $description): bool
    {
        return $this->executeWithRetry(function () use ($operation, $description) {
            return DB::transaction(function () use ($operation, $description) {
                $this->command->info("🔄 Starting transaction: {$description}");
                $result = $operation();
                $this->command->info("✅ Transaction completed: {$description}");
                return $result;
            });
        }, "Transaction: {$description}");
    }

    /**
     * Format bytes into human readable format.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Log seeding statistics.
     */
    protected function logSeedingStats(string $seederName, array $stats, float $duration): void
    {
        $this->command->info("📈 {$seederName} Statistics:");
        $this->command->info("  • Processed: " . number_format($stats['processed'] ?? 0));
        $this->command->info("  • Skipped: " . number_format($stats['skipped'] ?? 0));
        $this->command->info("  • Errors: " . number_format($stats['errors'] ?? 0));
        $this->command->info("  • Duration: " . round($duration, 2) . "s");
        $this->command->info("  • Rate: " . round(($stats['processed'] ?? 0) / max($duration, 0.001), 2) . " records/sec");
        
        Log::info("Seeder completed", [
            'seeder' => $seederName,
            'stats' => $stats,
            'duration' => $duration,
            'memory_peak' => memory_get_peak_usage(true),
        ]);
    }

    /**
     * Check if seeding should be skipped based on existing data.
     */
    protected function shouldSkipSeeding(string $table, string $identifier = 'chinook'): bool
    {
        $count = DB::table($table)->count();
        
        if ($count > 0) {
            $skip = !$this->command->confirm(
                "Table '{$table}' already contains {$count} records. Continue with {$identifier} seeding?",
                false
            );
            
            if ($skip) {
                $this->command->info("⏭️  Skipping {$identifier} seeding for table '{$table}'");
                return true;
            }
        }
        
        return false;
    }

    /**
     * Cleanup and optimize after seeding.
     */
    protected function performPostSeedingOptimization(array $tables): void
    {
        $this->command->info("🔧 Performing post-seeding optimization...");
        
        foreach ($tables as $table) {
            try {
                // Analyze table for query optimization
                DB::statement("ANALYZE TABLE {$table}");
                $this->command->info("  • Analyzed table: {$table}");
            } catch (\Exception $e) {
                $this->command->warn("  • Failed to analyze table {$table}: " . $e->getMessage());
            }
        }
        
        // Force final garbage collection
        gc_collect_cycles();
        
        $finalMemory = $this->formatBytes(memory_get_usage(true));
        $peakMemory = $this->formatBytes(memory_get_peak_usage(true));
        
        $this->command->info("🧠 Final memory usage: {$finalMemory} (Peak: {$peakMemory})");
    }
}
