<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Chinook Invoices Seeder
 * 
 * Seeds the invoices table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with invoice_lines.
 * Parses the SQL dump file directly to handle the dataset efficiently (~412 invoices).
 */
class ChinookInvoicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Invoices seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $sqlDumpPath = database_path('sqldump/chinook.sql');
            
            if (!file_exists($sqlDumpPath)) {
                throw new \Exception("Chinook SQL dump file not found at: {$sqlDumpPath}");
            }

            $invoicesData = $this->parseInvoicesFromSqlDump($sqlDumpPath);
            $this->seedInvoices($invoicesData, $systemUser);
        });
    }

    /**
     * Parse invoices data from the SQL dump file.
     */
    private function parseInvoicesFromSqlDump(string $filePath): array
    {
        $this->command->info('Parsing invoices data from SQL dump...');
        
        $content = file_get_contents($filePath);
        $invoices = [];
        
        // Find the invoices INSERT statement
        $pattern = '/INSERT INTO `invoices` \(`id`, `customer_id`, `invoice_date`, `billing_address`, `billing_city`, `billing_state`, `billing_country`, `billing_postal_code`, `total`\) VALUES\s*(.*?);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $valuesString = $matches[1];
            
            // Parse individual invoice records - handle complex cases with quotes and NULLs
            $lines = explode("\n", $valuesString);
            $currentRecord = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $currentRecord .= $line;
                
                // Check if this line completes a record (ends with ),)
                if (preg_match('/\),\s*$/', $line) || preg_match('/\);\s*$/', $line)) {
                    $this->parseInvoiceRecord($currentRecord, $invoices);
                    $currentRecord = '';
                }
            }
            
            // Handle the last record if it doesn't end with a comma
            if (!empty($currentRecord)) {
                $this->parseInvoiceRecord($currentRecord, $invoices);
            }
        }
        
        $this->command->info("Parsed " . count($invoices) . " invoices from SQL dump");
        return $invoices;
    }

    /**
     * Parse a single invoice record from the SQL string.
     */
    private function parseInvoiceRecord(string $recordString, array &$invoices): void
    {
        // Pattern to match invoice records with proper handling of quotes and NULLs
        $pattern = '/\((\d+),\s*(\d+),\s*\'([^\']*)\',\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*([\d.]+)\)/';
        
        if (preg_match($pattern, $recordString, $match)) {
            $id = (int) $match[1];
            $customerId = (int) $match[2];
            $invoiceDate = $match[3];
            $billingAddress = isset($match[4]) && $match[4] !== '' ? str_replace("''", "'", $match[4]) : null;
            $billingCity = isset($match[5]) && $match[5] !== '' ? str_replace("''", "'", $match[5]) : null;
            $billingState = isset($match[6]) && $match[6] !== '' ? str_replace("''", "'", $match[6]) : null;
            $billingCountry = isset($match[7]) && $match[7] !== '' ? str_replace("''", "'", $match[7]) : null;
            $billingPostalCode = isset($match[8]) && $match[8] !== '' ? str_replace("''", "'", $match[8]) : null;
            $total = (float) $match[9];
            
            $invoices[] = [
                'id' => $id,
                'customer_id' => $customerId,
                'invoice_date' => $invoiceDate,
                'billing_address' => $billingAddress,
                'billing_city' => $billingCity,
                'billing_state' => $billingState,
                'billing_country' => $billingCountry,
                'billing_postal_code' => $billingPostalCode,
                'total' => $total,
            ];
        }
    }

    /**
     * Seed the invoices with the parsed data.
     */
    private function seedInvoices(array $invoicesData, ?User $systemUser): void
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $batchSize = 50;
        $batch = [];

        foreach ($invoicesData as $invoiceData) {
            try {
                // Verify that the customer exists
                if (!Customer::find($invoiceData['customer_id'])) {
                    $this->command->warn("Customer ID {$invoiceData['customer_id']} not found for invoice {$invoiceData['id']}, skipping...");
                    $skippedCount++;
                    continue;
                }

                // Check if invoice already exists
                $existingInvoice = Invoice::find($invoiceData['id']);
                
                if ($existingInvoice) {
                    $this->command->warn("Invoice ID {$invoiceData['id']} already exists, skipping...");
                    $skippedCount++;
                    continue;
                }

                // Prepare invoice data for batch insert
                $batch[] = [
                    'id' => $invoiceData['id'],
                    'customer_id' => $invoiceData['customer_id'],
                    'invoice_date' => Carbon::parse($invoiceData['invoice_date'])->format('Y-m-d H:i:s'),
                    'billing_address' => $invoiceData['billing_address'],
                    'billing_city' => $invoiceData['billing_city'],
                    'billing_state' => $invoiceData['billing_state'],
                    'billing_country' => $invoiceData['billing_country'],
                    'billing_postal_code' => $invoiceData['billing_postal_code'],
                    'total' => $invoiceData['total'],
                    'status' => 'paid', // Assume all historical invoices are paid
                    'payment_method' => null,
                    'payment_date' => Carbon::parse($invoiceData['invoice_date'])->addDays(rand(1, 30))->format('Y-m-d H:i:s'),
                    'due_date' => Carbon::parse($invoiceData['invoice_date'])->addDays(30)->format('Y-m-d H:i:s'),
                    'notes' => null,
                    'metadata' => null,
                    'created_by' => $systemUser?->id,
                    'updated_by' => $systemUser?->id,
                    'created_at' => Carbon::parse($invoiceData['invoice_date'])->format('Y-m-d H:i:s'),
                    'updated_at' => Carbon::parse($invoiceData['invoice_date'])->format('Y-m-d H:i:s'),
                ];

                // Process batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $createdCount += count($batch);
                    $batch = [];
                    
                    // Progress indicator
                    if ($createdCount % 100 === 0) {
                        $this->command->info("Processed {$createdCount} invoices...");
                    }
                }

            } catch (\Exception $e) {
                $this->command->error("Failed to prepare invoice {$invoiceData['id']}: " . $e->getMessage());
                Log::error("ChinookInvoicesSeeder failed for invoice", [
                    'invoice_data' => $invoiceData,
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

        $this->command->info("Chinook Invoices seeding completed: {$createdCount} created, {$skippedCount} skipped, {$errorCount} errors");
        
        // Add tags to invoices
        $this->addInvoiceTags($invoicesData);
    }

    /**
     * Process a batch of invoices.
     */
    private function processBatch(array $batch): void
    {
        try {
            Invoice::insert($batch);
        } catch (\Exception $e) {
            $this->command->warn("Batch insert failed, trying individual inserts...");
            
            foreach ($batch as $invoiceData) {
                try {
                    Invoice::create($invoiceData);
                } catch (\Exception $individualError) {
                    $this->command->error("Failed to create invoice {$invoiceData['id']}: " . $individualError->getMessage());
                    Log::error("Individual invoice creation failed", [
                        'invoice_data' => $invoiceData,
                        'error' => $individualError->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Add tags to invoices based on their data.
     */
    private function addInvoiceTags(array $invoicesData): void
    {
        $this->command->info('Adding tags to invoices...');
        
        foreach ($invoicesData as $invoiceData) {
            $invoice = Invoice::find($invoiceData['id']);
            if (!$invoice) continue;

            $tags = $this->getInvoiceTags($invoiceData);
            $invoice->syncTags($tags);
        }
    }

    /**
     * Get appropriate tags for invoice.
     */
    private function getInvoiceTags(array $invoiceData): array
    {
        $baseTags = ['invoice', 'chinook', 'sales'];
        
        $specificTags = [];
        
        // Add year tag
        $year = Carbon::parse($invoiceData['invoice_date'])->year;
        $specificTags[] = "year-{$year}";
        
        // Add country tag
        if ($invoiceData['billing_country']) {
            $specificTags[] = strtolower(str_replace(' ', '-', $invoiceData['billing_country']));
        }
        
        // Add total range tags
        $total = $invoiceData['total'];
        if ($total < 5) {
            $specificTags[] = 'small-order';
        } elseif ($total < 15) {
            $specificTags[] = 'medium-order';
        } else {
            $specificTags[] = 'large-order';
        }

        return array_merge($baseTags, $specificTags);
    }

    /**
     * Get the system user for user stamps.
     */
    private function getSystemUser(): ?User
    {
        return User::where('email', 'system@chinook.com')->first();
    }
}
