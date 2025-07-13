<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chinook Customers Seeder
 * 
 * Seeds the customers table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with invoices.
 * Parses the SQL dump file directly to handle the dataset efficiently.
 */
class ChinookCustomersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Customers seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $sqlDumpPath = database_path('sqldump/chinook.sql');
            
            if (!file_exists($sqlDumpPath)) {
                throw new \Exception("Chinook SQL dump file not found at: {$sqlDumpPath}");
            }

            $customersData = $this->parseCustomersFromSqlDump($sqlDumpPath);
            $this->seedCustomers($customersData, $systemUser);
        });
    }

    /**
     * Parse customers data from the SQL dump file.
     */
    private function parseCustomersFromSqlDump(string $filePath): array
    {
        $this->command->info('Parsing customers data from SQL dump...');
        
        $content = file_get_contents($filePath);
        $customers = [];
        
        // Find the customers INSERT statement
        $pattern = '/INSERT INTO `customers` \(`id`, `first_name`, `last_name`, `company`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `fax`, `email`, `support_rep_id`\) VALUES\s*(.*?);/s';
        
        if (preg_match($pattern, $content, $matches)) {
            $valuesString = $matches[1];
            
            // Parse individual customer records - handle complex cases with quotes and NULLs
            $lines = explode("\n", $valuesString);
            $currentRecord = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $currentRecord .= $line;
                
                // Check if this line completes a record (ends with ),)
                if (preg_match('/\),\s*$/', $line) || preg_match('/\);\s*$/', $line)) {
                    $this->parseCustomerRecord($currentRecord, $customers);
                    $currentRecord = '';
                }
            }
            
            // Handle the last record if it doesn't end with a comma
            if (!empty($currentRecord)) {
                $this->parseCustomerRecord($currentRecord, $customers);
            }
        }
        
        $this->command->info("Parsed " . count($customers) . " customers from SQL dump");
        return $customers;
    }

    /**
     * Parse a single customer record from the SQL string.
     */
    private function parseCustomerRecord(string $recordString, array &$customers): void
    {
        // Pattern to match customer records with proper handling of quotes and NULLs
        $pattern = '/\((\d+),\s*\'([^\']*(?:\'\'[^\']*)*)\',\s*\'([^\']*(?:\'\'[^\']*)*)\',\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*(?:\'([^\']*(?:\'\'[^\']*)*)\'\s*|NULL\s*),\s*\'([^\']*(?:\'\'[^\']*)*)\',\s*(\d+|NULL)\)/';
        
        if (preg_match($pattern, $recordString, $match)) {
            $id = (int) $match[1];
            $firstName = str_replace("''", "'", $match[2]);
            $lastName = str_replace("''", "'", $match[3]);
            $company = isset($match[4]) && $match[4] !== '' ? str_replace("''", "'", $match[4]) : null;
            $address = isset($match[5]) && $match[5] !== '' ? str_replace("''", "'", $match[5]) : null;
            $city = isset($match[6]) && $match[6] !== '' ? str_replace("''", "'", $match[6]) : null;
            $state = isset($match[7]) && $match[7] !== '' ? str_replace("''", "'", $match[7]) : null;
            $country = isset($match[8]) && $match[8] !== '' ? str_replace("''", "'", $match[8]) : null;
            $postalCode = isset($match[9]) && $match[9] !== '' ? str_replace("''", "'", $match[9]) : null;
            $phone = isset($match[10]) && $match[10] !== '' ? str_replace("''", "'", $match[10]) : null;
            $fax = isset($match[11]) && $match[11] !== '' ? str_replace("''", "'", $match[11]) : null;
            $email = str_replace("''", "'", $match[12]);
            $supportRepId = $match[13] === 'NULL' ? null : (int) $match[13];
            
            $customers[] = [
                'id' => $id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company' => $company,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'postal_code' => $postalCode,
                'phone' => $phone,
                'fax' => $fax,
                'email' => $email,
                'support_rep_id' => $supportRepId,
            ];
        }
    }

    /**
     * Seed the customers with the parsed data.
     */
    private function seedCustomers(array $customersData, ?User $systemUser): void
    {
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $batchSize = 25;
        $batch = [];

        foreach ($customersData as $customerData) {
            try {
                // Verify that the support rep exists if specified
                if ($customerData['support_rep_id'] && !Employee::find($customerData['support_rep_id'])) {
                    $this->command->warn("Support Rep ID {$customerData['support_rep_id']} not found for customer '{$customerData['email']}', skipping...");
                    $skippedCount++;
                    continue;
                }

                // Check if customer already exists
                $existingCustomer = Customer::where('email', $customerData['email'])->first();
                
                if ($existingCustomer) {
                    $this->command->warn("Customer '{$customerData['email']}' already exists, skipping...");
                    $skippedCount++;
                    continue;
                }

                // Prepare customer data for batch insert
                $batch[] = [
                    'id' => $customerData['id'],
                    'first_name' => $customerData['first_name'],
                    'last_name' => $customerData['last_name'],
                    'company' => $customerData['company'],
                    'address' => $customerData['address'],
                    'city' => $customerData['city'],
                    'state' => $customerData['state'],
                    'country' => $customerData['country'],
                    'postal_code' => $customerData['postal_code'],
                    'phone' => $customerData['phone'],
                    'fax' => $customerData['fax'],
                    'email' => $customerData['email'],
                    'support_rep_id' => $customerData['support_rep_id'],
                    'customer_type' => $this->getCustomerType($customerData),
                    'credit_limit' => null,
                    'preferred_language' => $this->getPreferredLanguage($customerData['country']),
                    'marketing_opt_in' => true,
                    'is_active' => true,
                    'notes' => null,
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
                    $this->command->info("Processed {$createdCount} customers...");
                }

            } catch (\Exception $e) {
                $this->command->error("Failed to prepare customer '{$customerData['email']}': " . $e->getMessage());
                Log::error("ChinookCustomersSeeder failed for customer", [
                    'customer_data' => $customerData,
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

        $this->command->info("Chinook Customers seeding completed: {$createdCount} created, {$skippedCount} skipped, {$errorCount} errors");
        
        // Add tags to customers
        $this->addCustomerTags($customersData);
    }

    /**
     * Process a batch of customers.
     */
    private function processBatch(array $batch): void
    {
        try {
            Customer::insert($batch);
        } catch (\Exception $e) {
            $this->command->warn("Batch insert failed, trying individual inserts...");
            
            foreach ($batch as $customerData) {
                try {
                    Customer::create($customerData);
                } catch (\Exception $individualError) {
                    $this->command->error("Failed to create customer '{$customerData['email']}': " . $individualError->getMessage());
                    Log::error("Individual customer creation failed", [
                        'customer_data' => $customerData,
                        'error' => $individualError->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Add tags to customers based on their data.
     */
    private function addCustomerTags(array $customersData): void
    {
        $this->command->info('Adding tags to customers...');
        
        foreach ($customersData as $customerData) {
            $customer = Customer::find($customerData['id']);
            if (!$customer) continue;

            $tags = $this->getCustomerTags($customerData);
            $customer->syncTags($tags);
        }
    }

    /**
     * Determine customer type based on company field.
     */
    private function getCustomerType(array $customerData): string
    {
        return $customerData['company'] ? 'business' : 'individual';
    }

    /**
     * Get preferred language based on country.
     */
    private function getPreferredLanguage(string $country): string
    {
        return match ($country) {
            'Brazil' => 'Portuguese',
            'Germany', 'Austria' => 'German',
            'France' => 'French',
            'Spain' => 'Spanish',
            'Italy' => 'Italian',
            'Norway', 'Denmark', 'Sweden', 'Finland' => 'English', // Nordic countries often use English
            'Czech Republic', 'Poland', 'Hungary' => 'English', // Eastern European countries
            default => 'English',
        };
    }

    /**
     * Get appropriate tags for customer.
     */
    private function getCustomerTags(array $customerData): array
    {
        $baseTags = ['customer', 'chinook'];
        
        $specificTags = [];
        
        if ($customerData['company']) {
            $specificTags[] = 'business';
        } else {
            $specificTags[] = 'individual';
        }
        
        if ($customerData['country']) {
            $specificTags[] = strtolower(str_replace(' ', '-', $customerData['country']));
        }
        
        if ($customerData['city']) {
            $specificTags[] = strtolower(str_replace(' ', '-', $customerData['city']));
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
