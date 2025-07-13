<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Chinook Employees Seeder
 * 
 * Seeds the employees table with data from the original Chinook database.
 * Maintains original IDs for foreign key compatibility with customers.
 * Handles the self-referencing reports_to relationship properly.
 */
class ChinookEmployeesSeeder extends Seeder
{
    /**
     * The original Chinook employees data.
     */
    private array $chinookEmployees = [
        1 => [
            'last_name' => 'Adams',
            'first_name' => 'Andrew',
            'title' => 'General Manager',
            'reports_to' => null,
            'birth_date' => '1962-02-18 00:00:00',
            'hire_date' => '2002-08-14 00:00:00',
            'address' => '11120 Jasper Ave NW',
            'city' => 'Edmonton',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T5K 2N1',
            'phone' => '+1 (780) 428-9482',
            'fax' => '+1 (780) 428-3457',
            'email' => 'andrew@chinookcorp.com',
        ],
        2 => [
            'last_name' => 'Edwards',
            'first_name' => 'Nancy',
            'title' => 'Sales Manager',
            'reports_to' => 1,
            'birth_date' => '1958-12-08 00:00:00',
            'hire_date' => '2002-05-01 00:00:00',
            'address' => '825 8 Ave SW',
            'city' => 'Calgary',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T2P 2T3',
            'phone' => '+1 (403) 262-3443',
            'fax' => '+1 (403) 262-3322',
            'email' => 'nancy@chinookcorp.com',
        ],
        3 => [
            'last_name' => 'Peacock',
            'first_name' => 'Jane',
            'title' => 'Sales Support Agent',
            'reports_to' => 2,
            'birth_date' => '1973-08-29 00:00:00',
            'hire_date' => '2002-04-01 00:00:00',
            'address' => '1111 6 Ave SW',
            'city' => 'Calgary',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T2P 5M5',
            'phone' => '+1 (403) 262-3443',
            'fax' => '+1 (403) 262-6712',
            'email' => 'jane@chinookcorp.com',
        ],
        4 => [
            'last_name' => 'Park',
            'first_name' => 'Margaret',
            'title' => 'Sales Support Agent',
            'reports_to' => 2,
            'birth_date' => '1947-09-19 00:00:00',
            'hire_date' => '2003-05-03 00:00:00',
            'address' => '683 10 Street SW',
            'city' => 'Calgary',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T2P 5G3',
            'phone' => '+1 (403) 263-4423',
            'fax' => '+1 (403) 263-4289',
            'email' => 'margaret@chinookcorp.com',
        ],
        5 => [
            'last_name' => 'Johnson',
            'first_name' => 'Steve',
            'title' => 'Sales Support Agent',
            'reports_to' => 2,
            'birth_date' => '1965-03-03 00:00:00',
            'hire_date' => '2003-10-17 00:00:00',
            'address' => '7727B 41 Ave',
            'city' => 'Calgary',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T3B 1Y7',
            'phone' => '1 (780) 836-9987',
            'fax' => '1 (780) 836-9543',
            'email' => 'steve@chinookcorp.com',
        ],
        6 => [
            'last_name' => 'Mitchell',
            'first_name' => 'Michael',
            'title' => 'IT Manager',
            'reports_to' => 1,
            'birth_date' => '1973-07-01 00:00:00',
            'hire_date' => '2003-10-17 00:00:00',
            'address' => '5827 Bowness Road NW',
            'city' => 'Calgary',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T3B 0C5',
            'phone' => '+1 (403) 246-9887',
            'fax' => '+1 (403) 246-9899',
            'email' => 'michael@chinookcorp.com',
        ],
        7 => [
            'last_name' => 'King',
            'first_name' => 'Robert',
            'title' => 'IT Staff',
            'reports_to' => 6,
            'birth_date' => '1970-05-29 00:00:00',
            'hire_date' => '2004-01-02 00:00:00',
            'address' => '590 Columbia Boulevard West',
            'city' => 'Lethbridge',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T1K 5N8',
            'phone' => '+1 (403) 456-9986',
            'fax' => '+1 (403) 456-8485',
            'email' => 'robert@chinookcorp.com',
        ],
        8 => [
            'last_name' => 'Callahan',
            'first_name' => 'Laura',
            'title' => 'IT Staff',
            'reports_to' => 6,
            'birth_date' => '1968-01-09 00:00:00',
            'hire_date' => '2004-03-04 00:00:00',
            'address' => '923 7 ST NW',
            'city' => 'Lethbridge',
            'state' => 'AB',
            'country' => 'Canada',
            'postal_code' => 'T1H 1Y8',
            'phone' => '+1 (403) 467-3351',
            'fax' => '+1 (403) 467-8772',
            'email' => 'laura@chinookcorp.com',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Chinook Employees seeding...');
        
        DB::transaction(function () {
            $systemUser = $this->getSystemUser();
            $createdCount = 0;
            $skippedCount = 0;

            // Sort employees by hierarchy (managers first, then their reports)
            $sortedEmployees = $this->sortEmployeesByHierarchy();

            foreach ($sortedEmployees as $originalId => $employeeData) {
                try {
                    // Check if employee already exists
                    $existingEmployee = Employee::where('email', $employeeData['email'])->first();
                    
                    if ($existingEmployee) {
                        $this->command->warn("Employee '{$employeeData['email']}' already exists, skipping...");
                        $skippedCount++;
                        continue;
                    }

                    // Verify reports_to relationship if it exists
                    if ($employeeData['reports_to'] && !Employee::find($employeeData['reports_to'])) {
                        $this->command->warn("Manager ID {$employeeData['reports_to']} not found for employee '{$employeeData['email']}', skipping...");
                        $skippedCount++;
                        continue;
                    }

                    // Create the employee with original ID preserved for FK compatibility
                    $employee = Employee::create([
                        'id' => $originalId,
                        'first_name' => $employeeData['first_name'],
                        'last_name' => $employeeData['last_name'],
                        'title' => $employeeData['title'],
                        'reports_to' => $employeeData['reports_to'],
                        'birth_date' => Carbon::parse($employeeData['birth_date'])->format('Y-m-d'),
                        'hire_date' => Carbon::parse($employeeData['hire_date'])->format('Y-m-d'),
                        'address' => $employeeData['address'],
                        'city' => $employeeData['city'],
                        'state' => $employeeData['state'],
                        'country' => $employeeData['country'],
                        'postal_code' => $employeeData['postal_code'],
                        'phone' => $employeeData['phone'],
                        'fax' => $employeeData['fax'],
                        'email' => $employeeData['email'],
                        'department' => $this->getDepartment($employeeData['title']),
                        'salary' => null, // Not provided in original data
                        'commission_rate' => $this->getCommissionRate($employeeData['title']),
                        'is_active' => true,
                        'notes' => null,
                        'created_by' => $systemUser?->id,
                        'updated_by' => $systemUser?->id,
                    ]);

                    // Add appropriate tags
                    $tags = $this->getEmployeeTags($employeeData);
                    $employee->syncTags($tags);

                    $createdCount++;
                    $this->command->info("Created employee: {$employeeData['first_name']} {$employeeData['last_name']} (ID: {$originalId})");

                } catch (\Exception $e) {
                    $this->command->error("Failed to create employee '{$employeeData['email']}': " . $e->getMessage());
                    Log::error("ChinookEmployeesSeeder failed for employee: {$employeeData['email']}", [
                        'error' => $e->getMessage(),
                        'original_id' => $originalId,
                        'employee_data' => $employeeData,
                    ]);
                    throw $e;
                }
            }

            $this->command->info("Chinook Employees seeding completed: {$createdCount} created, {$skippedCount} skipped");
        });
    }

    /**
     * Sort employees by hierarchy to ensure managers are created before their reports.
     */
    private function sortEmployeesByHierarchy(): array
    {
        $sorted = [];
        $remaining = $this->chinookEmployees;
        
        // First, add employees with no manager (top level)
        foreach ($remaining as $id => $employee) {
            if ($employee['reports_to'] === null) {
                $sorted[$id] = $employee;
                unset($remaining[$id]);
            }
        }
        
        // Then add employees level by level
        while (!empty($remaining)) {
            $addedThisRound = [];
            
            foreach ($remaining as $id => $employee) {
                if (isset($sorted[$employee['reports_to']])) {
                    $addedThisRound[$id] = $employee;
                }
            }
            
            if (empty($addedThisRound)) {
                // If we can't add any more, add the remaining ones anyway
                $sorted = array_merge($sorted, $remaining);
                break;
            }
            
            $sorted = array_merge($sorted, $addedThisRound);
            foreach ($addedThisRound as $id => $employee) {
                unset($remaining[$id]);
            }
        }
        
        return $sorted;
    }

    /**
     * Get department based on title.
     */
    private function getDepartment(string $title): string
    {
        return match (true) {
            str_contains($title, 'Sales') => 'Sales',
            str_contains($title, 'IT') => 'Information Technology',
            str_contains($title, 'General Manager') => 'Management',
            default => 'General',
        };
    }

    /**
     * Get commission rate based on title.
     */
    private function getCommissionRate(string $title): ?float
    {
        return match (true) {
            str_contains($title, 'Sales') => 0.05, // 5% commission for sales staff
            default => null,
        };
    }

    /**
     * Get appropriate tags for employee.
     */
    private function getEmployeeTags(array $employeeData): array
    {
        $baseTags = ['employee', 'chinook'];
        
        $specificTags = [];
        
        if (str_contains($employeeData['title'], 'Manager')) {
            $specificTags[] = 'manager';
        }
        
        if (str_contains($employeeData['title'], 'Sales')) {
            $specificTags[] = 'sales';
        }
        
        if (str_contains($employeeData['title'], 'IT')) {
            $specificTags[] = 'it';
        }
        
        $specificTags[] = strtolower(str_replace(' ', '-', $employeeData['city']));
        $specificTags[] = strtolower($employeeData['country']);

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
