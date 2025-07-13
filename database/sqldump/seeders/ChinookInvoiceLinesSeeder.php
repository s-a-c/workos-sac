<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InvoiceLine;
use App\Models\Invoice;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Invoice Lines Seeder
 * 
 * Seeds the invoice_lines table with data from the original Chinook database.
 * Maintains original IDs and handles the large dataset efficiently (~2,240 invoice lines).
 * Parses the SQL dump file directly to handle the dataset efficiently.
 */
class ChinookInvoiceLinesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Invoice Lines seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $sqlDumpPath = database_path('sqldump/chinook.sql');
            
            if (!file_exists($sqlDumpPath)) {
                throw new \Exception("Chinook SQL dump file not found at: {$sqlDumpPath}");
            }

            $invoiceLinesData = $this->parseInvoiceLinesFromSqlDump($sqlDumpPath);
            $this->seedInvoiceLines($invoiceLinesData, $systemUser);
        });
    }

    /**
     * Parse invoice lines data from the SQL dump file.
     */
    private function parseInvoiceLinesFromSqlDump(string $filePath): array
    {
        $this->command->info('Parsing invoice lines data from SQL dump...');
        
        $content = file_get_contents($filePath);
        $invoiceLines = [];
        
        // Find the invoice_lines INSERT statement
        $pattern = '/INSERT INTO `invoice_lines` \(`id`, `invoice_id`, `track_id`, `unit_price`, `quantity`\) VALUES\s*(.*?);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $valuesString = $matches[1];
            
            // Parse individual invoice line records
            $pattern = '/\((\d+),\s*(\d+),\s*(\d+),\s*([\d.]+),\s*(\d+)\)/';
            
            if (preg_match_all($pattern, $valuesString, $lineMatches, PREG_SET_ORDER)) {
                foreach ($lineMatches as $match) {
                    $id = (int) $match[1];
                    $invoiceId = (int) $match[2];
                    $trackId = (int) $match[3];
                    $unitPrice = (float) $match[4];
                    $quantity = (int) $match[5];
                    
                    $invoiceLines[] = [
                        'id' => $id,
                        'invoice_id' => $invoiceId,
                        'track_id' => $trackId,
                        'unit_price' => $unitPrice,
                        'quantity' => $quantity,
                    ];
                }
            }
        }
        
        $this->command->info("Parsed " . count($invoiceLines) . " invoice lines from SQL dump");
        return $invoiceLines;
    }

    /**
     * Seed the invoice lines with the parsed data.
     */
    private function seedInvoiceLines(array $invoiceLinesData, ?User $systemUser): void
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $batchSize = 200; // Larger batch for invoice lines due to volume
        $batch = [];

        foreach ($invoiceLinesData as $invoiceLineData) {
            try {
                // Verify foreign key relationships
                if (!Invoice::find($invoiceLineData['invoice_id'])) {
                    $this->command->warn("Invoice ID {$invoiceLineData['invoice_id']} not found for invoice line {$invoiceLineData['id']}, skipping...");
                    $skippedCount++;
                    continue;
                }

                if (!Track::find($invoiceLineData['track_id'])) {
                    $this->command->warn("Track ID {$invoiceLineData['track_id']} not found for invoice line {$invoiceLineData['id']}, skipping...");
                    $skippedCount++;
                    continue;
                }

                // Check if invoice line already exists
                $existingInvoiceLine = InvoiceLine::find($invoiceLineData['id']);
                
                if ($existingInvoiceLine) {
                    $skippedCount++;
                    continue;
                }

                // Prepare invoice line data for batch insert
                $batch[] = [
                    'id' => $invoiceLineData['id'],
                    'invoice_id' => $invoiceLineData['invoice_id'],
                    'track_id' => $invoiceLineData['track_id'],
                    'unit_price' => $invoiceLineData['unit_price'],
                    'quantity' => $invoiceLineData['quantity'],
                    'discount' => 0.00, // No discount in original data
                    'line_total' => $invoiceLineData['unit_price'] * $invoiceLineData['quantity'],
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
                    if ($createdCount % 500 === 0) {
                        $this->command->info("Processed {$createdCount} invoice lines...");
                    }
                }

            } catch (\Exception $e) {
                $this->command->error("Failed to prepare invoice line {$invoiceLineData['id']}: " . $e->getMessage());
                Log::error("ChinookInvoiceLinesSeeder failed for invoice line", [
                    'invoice_line_data' => $invoiceLineData,
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

        $this->command->info("Chinook Invoice Lines seeding completed: {$createdCount} created, {$skippedCount} skipped, {$errorCount} errors");
    }

    /**
     * Process a batch of invoice lines.
     */
    private function processBatch(array $batch): void
    {
        try {
            InvoiceLine::insert($batch);
        } catch (\Exception $e) {
            $this->command->warn("Batch insert failed, trying individual inserts...");
            
            foreach ($batch as $invoiceLineData) {
                try {
                    InvoiceLine::create($invoiceLineData);
                } catch (\Exception $individualError) {
                    $this->command->error("Failed to create invoice line {$invoiceLineData['id']}: " . $individualError->getMessage());
                    Log::error("Individual invoice line creation failed", [
                        'invoice_line_data' => $invoiceLineData,
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
