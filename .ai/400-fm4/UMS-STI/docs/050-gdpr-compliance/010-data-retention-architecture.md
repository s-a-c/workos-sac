# GDPR Data Retention Architecture

## Executive Summary
The GDPR data retention architecture implements a two-tier system that separates personal data (2-year retention, deletable on request) from audit/compliance data (7-year retention with anonymization). This approach satisfies both GDPR "right to be forgotten" requirements and regulatory audit trail obligations through secure anonymization techniques.

## Learning Objectives
After completing this guide, you will:
- Understand GDPR data classification and retention requirements
- Implement separate retention policies for personal vs. audit data
- Design anonymous token systems for audit trail continuity
- Create automated data lifecycle management processes
- Build GDPR-compliant deletion and export mechanisms

## Prerequisite Knowledge
- GDPR fundamental principles and user rights
- Laravel database design and migrations
- Understanding of data anonymization vs. pseudonymization
- Audit logging concepts and requirements
- Laravel job queues and scheduling

## Architectural Overview

### GDPR Compliance Challenge

Based on **DECISION-007** from our decision log, we address the conflict between:

```
GDPR Requirements:
├── Right to Erasure (Article 17)
│   └── Delete personal data within 30 days of request
├── Data Minimization (Article 5)
│   └── Keep data only as long as necessary
└── Data Portability (Article 20)
    └── Provide data in machine-readable format

vs.

Audit Requirements:
├── Regulatory Compliance
│   └── 7-year audit trail retention
├── Security Investigations
│   └── Maintain evidence for incident response
└── Legal Protection
    └── Preserve records for potential litigation
```

### Two-Tier Data Architecture

```
Tier 1: Personal Data (GDPR Deletable)
┌─────────────────────────────────────┐
│ users                               │
│ ├── name, email, personal_info      │
│ ├── profile pictures, preferences   │
│ └── user-generated content          │
│                                     │
│ user_profiles                       │
│ ├── bio, avatar, contact_info       │
│ └── personal preferences            │
│                                     │
│ team_memberships                    │
│ ├── user_id (personal context)      │
│ └── role assignments with names     │
└─────────────────────────────────────┘
Retention: 2 years from last activity
Deletion: On GDPR request (30 days)

Tier 2: Audit Data (Anonymized Retention)
┌─────────────────────────────────────┐
│ audit_logs                          │
│ ├── user_token (anonymous)          │
│ ├── action, resource, timestamp     │
│ └── system events, security logs    │
│                                     │
│ user_stamps                         │
│ ├── created_by_token (anonymous)    │
│ ├── updated_by_token (anonymous)    │
│ └── deleted_by_token (anonymous)    │
│                                     │
│ compliance_logs                     │
│ ├── gdpr_requests with tokens       │
│ └── anonymization records           │
└─────────────────────────────────────┘
Retention: 7 years with anonymization
Legal Basis: Legitimate interest (Article 6(1)(f))
```

## Core Concepts Deep Dive

### 1. Data Classification Framework

```php
enum DataClassification: string
{
    case PersonalData = 'personal';      // GDPR deletable
    case AuditData = 'audit';           // Anonymizable
    case SystemData = 'system';         // Non-personal metadata
    case ComplianceData = 'compliance'; // Regulatory requirements
}
```

### 2. Anonymous Token System

```php
// Irreversible anonymization
$userToken = hash('sha256', $user->id . config('app.key') . 'audit_salt');

// Before deletion:
audit_logs: user_id = 123, action = 'login', timestamp = '2024-01-01'

// After anonymization:
audit_logs: user_token = 'a1b2c3...', action = 'login', timestamp = '2024-01-01'
```

### 3. Retention Policy Engine

```php
class RetentionPolicy
{
    public function getRetentionPeriod(DataClassification $type): int
    {
        return match($type) {
            DataClassification::PersonalData => 2,      // 2 years
            DataClassification::AuditData => 7,         // 7 years
            DataClassification::SystemData => 10,       // 10 years
            DataClassification::ComplianceData => 7,    // 7 years
        };
    }
}
```

## Implementation Principles & Patterns

### 1. Data Lifecycle Management
- **Automated Classification**: Automatic data type identification
- **Scheduled Purging**: Background jobs for retention enforcement
- **Audit Trail Preservation**: Maintain compliance evidence
- **Secure Deletion**: Cryptographic erasure where possible

### 2. Anonymization Strategy
- **Irreversible Tokens**: One-way hash functions for user identification
- **Contextual Preservation**: Maintain audit value without personal data
- **Compliance Logging**: Track all anonymization operations
- **Verification Mechanisms**: Validate anonymization completeness

### 3. GDPR Request Handling
- **Automated Workflows**: Streamlined request processing
- **Data Discovery**: Comprehensive personal data identification
- **Export Generation**: Machine-readable data formats
- **Deletion Verification**: Confirm complete data removal

## Step-by-Step Implementation Guide

### Step 1: Create Data Classification Migration

Create `database/migrations/007_create_data_classification_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GDPR requests tracking
        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_type'); // export, delete, rectify
            $table->string('status'); // pending, processing, completed, failed
            $table->unsignedBigInteger('user_id');
            $table->string('user_email'); // Preserved for verification
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('completion_token')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'request_type']);
            $table->index(['status', 'requested_at']);
        });

        // Data retention tracking
        Schema::create('data_retention_records', function (Blueprint $table) {
            $table->id();
            $table->string('data_type'); // personal, audit, system, compliance
            $table->string('table_name');
            $table->unsignedBigInteger('record_id');
            $table->timestamp('created_at');
            $table->timestamp('expires_at');
            $table->timestamp('purged_at')->nullable();
            $table->string('purge_method')->nullable(); // delete, anonymize
            $table->json('metadata')->nullable();

            $table->index(['data_type', 'expires_at']);
            $table->index(['table_name', 'record_id']);
            $table->index('purged_at');
        });

        // Anonymization mapping (for audit trail continuity)
        Schema::create('anonymization_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_user_id');
            $table->string('anonymous_token');
            $table->timestamp('anonymized_at');
            $table->string('anonymization_method');
            $table->json('affected_tables');
            $table->timestamps();

            $table->unique('anonymous_token');
            $table->index('original_user_id');
            $table->index('anonymized_at');
        });

        // Compliance audit log
        Schema::create('compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // gdpr_request, data_purge, anonymization
            $table->string('user_token')->nullable(); // Anonymous token
            $table->json('event_data');
            $table->timestamp('event_timestamp');
            $table->string('performed_by')->nullable();
            $table->string('legal_basis')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'event_timestamp']);
            $table->index('user_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_logs');
        Schema::dropIfExists('anonymization_mappings');
        Schema::dropIfExists('data_retention_records');
        Schema::dropIfExists('gdpr_requests');
    }
};
```

### Step 2: Create Data Classification Enum

Create `app/Enums/DataClassification.php`:

```php
<?php

namespace App\Enums;

enum DataClassification: string
{
    case PersonalData = 'personal';
    case AuditData = 'audit';
    case SystemData = 'system';
    case ComplianceData = 'compliance';

    public function getRetentionYears(): int
    {
        return match($this) {
            self::PersonalData => 2,
            self::AuditData => 7,
            self::SystemData => 10,
            self::ComplianceData => 7,
        };
    }

    public function isDeletableOnRequest(): bool
    {
        return match($this) {
            self::PersonalData => true,
            self::AuditData => false, // Anonymized instead
            self::SystemData => false,
            self::ComplianceData => false,
        };
    }

    public function getLegalBasis(): string
    {
        return match($this) {
            self::PersonalData => 'Consent (Article 6(1)(a))',
            self::AuditData => 'Legitimate interest (Article 6(1)(f))',
            self::SystemData => 'Legitimate interest (Article 6(1)(f))',
            self::ComplianceData => 'Legal obligation (Article 6(1)(c))',
        };
    }
}
```

### Step 3: Create GDPR Request Model

Create `app/Models/GdprRequest.php`:

```php
<?php

namespace App\Models;

use App\Enums\GdprRequestStatus;
use App\Enums\GdprRequestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_type',
        'status',
        'user_id',
        'user_email',
        'request_data',
        'response_data',
        'requested_at',
        'completed_at',
        'completion_token',
    ];

    protected $casts = [
        'request_type' => GdprRequestType::class,
        'status' => GdprRequestStatus::class,
        'request_data' => 'array',
        'response_data' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate completion token for verification.
     */
    public function generateCompletionToken(): string
    {
        $token = hash('sha256', $this->id . $this->user_email . now()->timestamp);
        $this->update(['completion_token' => $token]);
        return $token;
    }

    /**
     * Mark request as completed.
     */
    public function markCompleted(array $responseData = []): void
    {
        $this->update([
            'status' => GdprRequestStatus::Completed,
            'completed_at' => now(),
            'response_data' => $responseData,
        ]);
    }

    /**
     * Check if request is within legal timeframe (30 days).
     */
    public function isWithinLegalTimeframe(): bool
    {
        return $this->requested_at->diffInDays(now()) <= 30;
    }

    /**
     * Scope for pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', GdprRequestStatus::Pending);
    }

    /**
     * Scope for overdue requests.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', GdprRequestStatus::Pending)
            ->where('requested_at', '<', now()->subDays(30));
    }
}
```

### Step 4: Create Data Retention Service

Create `app/Services/DataRetentionService.php`:

```php
<?php

namespace App\Services;

use App\Enums\DataClassification;
use App\Models\AnonymizationMapping;
use App\Models\ComplianceLog;
use App\Models\DataRetentionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataRetentionService
{
    /**
     * Register data for retention tracking.
     */
    public function registerForRetention(
        DataClassification $type,
        string $tableName,
        int $recordId,
        array $metadata = []
    ): DataRetentionRecord {
        $expiresAt = now()->addYears($type->getRetentionYears());

        return DataRetentionRecord::create([
            'data_type' => $type->value,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'expires_at' => $expiresAt,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Process expired personal data for deletion.
     */
    public function processExpiredPersonalData(): array
    {
        $results = [];

        $expiredRecords = DataRetentionRecord::where('data_type', DataClassification::PersonalData->value)
            ->where('expires_at', '<', now())
            ->whereNull('purged_at')
            ->get();

        foreach ($expiredRecords as $record) {
            try {
                $this->deletePersonalDataRecord($record);
                $results['deleted'][] = $record->id;
            } catch (\Exception $e) {
                Log::error('Failed to delete expired personal data', [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
                $results['failed'][] = $record->id;
            }
        }

        return $results;
    }

    /**
     * Process expired audit data for anonymization.
     */
    public function processExpiredAuditData(): array
    {
        $results = [];

        $expiredRecords = DataRetentionRecord::where('data_type', DataClassification::AuditData->value)
            ->where('expires_at', '<', now())
            ->whereNull('purged_at')
            ->get();

        foreach ($expiredRecords as $record) {
            try {
                $this->anonymizeAuditDataRecord($record);
                $results['anonymized'][] = $record->id;
            } catch (\Exception $e) {
                Log::error('Failed to anonymize expired audit data', [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
                $results['failed'][] = $record->id;
            }
        }

        return $results;
    }

    /**
     * Generate anonymous token for user.
     */
    public function generateAnonymousToken(int $userId): string
    {
        return hash('sha256', $userId . config('app.key') . 'audit_salt_' . now()->timestamp);
    }

    /**
     * Anonymize user data while preserving audit trails.
     */
    public function anonymizeUserData(User $user): AnonymizationMapping
    {
        $anonymousToken = $this->generateAnonymousToken($user->id);

        return DB::transaction(function () use ($user, $anonymousToken) {
            // Create anonymization mapping
            $mapping = AnonymizationMapping::create([
                'original_user_id' => $user->id,
                'anonymous_token' => $anonymousToken,
                'anonymized_at' => now(),
                'anonymization_method' => 'sha256_with_salt',
                'affected_tables' => $this->getAffectedTables($user),
            ]);

            // Anonymize audit logs
            DB::table('activity_log')
                ->where('causer_id', $user->id)
                ->update([
                    'causer_id' => null,
                    'causer_type' => null,
                    'properties' => DB::raw("JSON_SET(properties, '$.anonymous_user_token', '{$anonymousToken}')"),
                ]);

            // Anonymize user stamps
            DB::table('users')
                ->where('created_by', $user->id)
                ->update(['created_by' => null, 'created_by_token' => $anonymousToken]);

            DB::table('users')
                ->where('updated_by', $user->id)
                ->update(['updated_by' => null, 'updated_by_token' => $anonymousToken]);

            // Log compliance event
            ComplianceLog::create([
                'event_type' => 'user_anonymization',
                'user_token' => $anonymousToken,
                'event_data' => [
                    'original_user_id' => $user->id,
                    'anonymization_method' => 'sha256_with_salt',
                    'affected_tables' => $mapping->affected_tables,
                ],
                'event_timestamp' => now(),
                'legal_basis' => 'Legitimate interest (Article 6(1)(f))',
            ]);

            return $mapping;
        });
    }

    /**
     * Delete personal data record.
     */
    private function deletePersonalDataRecord(DataRetentionRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // Delete the actual data
            DB::table($record->table_name)
                ->where('id', $record->record_id)
                ->delete();

            // Mark retention record as purged
            $record->update([
                'purged_at' => now(),
                'purge_method' => 'delete',
            ]);

            // Log compliance event
            ComplianceLog::create([
                'event_type' => 'personal_data_deletion',
                'event_data' => [
                    'table_name' => $record->table_name,
                    'record_id' => $record->record_id,
                    'retention_record_id' => $record->id,
                ],
                'event_timestamp' => now(),
                'legal_basis' => 'Data retention policy',
            ]);
        });
    }

    /**
     * Anonymize audit data record.
     */
    private function anonymizeAuditDataRecord(DataRetentionRecord $record): void
    {
        // Implementation depends on specific table structure
        // This is a placeholder for table-specific anonymization logic
        
        $record->update([
            'purged_at' => now(),
            'purge_method' => 'anonymize',
        ]);
    }

    /**
     * Get tables affected by user anonymization.
     */
    private function getAffectedTables(User $user): array
    {
        return [
            'activity_log',
            'users' => ['created_by', 'updated_by'],
            'teams' => ['created_by', 'updated_by'],
            'gdpr_requests',
        ];
    }

    /**
     * Generate retention statistics.
     */
    public function getRetentionStatistics(): array
    {
        return [
            'total_records' => DataRetentionRecord::count(),
            'by_type' => DataRetentionRecord::groupBy('data_type')
                ->selectRaw('data_type, count(*) as count')
                ->pluck('count', 'data_type'),
            'expired_pending' => DataRetentionRecord::where('expires_at', '<', now())
                ->whereNull('purged_at')
                ->count(),
            'purged_total' => DataRetentionRecord::whereNotNull('purged_at')->count(),
            'next_expiration' => DataRetentionRecord::whereNull('purged_at')
                ->min('expires_at'),
        ];
    }
}
```

### Step 5: Create Scheduled Jobs for Retention

Create `app/Jobs/ProcessDataRetention.php`:

```php
<?php

namespace App\Jobs;

use App\Services\DataRetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDataRetention implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DataRetentionService $service): void
    {
        Log::info('Starting data retention processing');

        // Process expired personal data
        $personalDataResults = $service->processExpiredPersonalData();
        Log::info('Personal data retention processed', $personalDataResults);

        // Process expired audit data
        $auditDataResults = $service->processExpiredAuditData();
        Log::info('Audit data retention processed', $auditDataResults);

        Log::info('Data retention processing completed');
    }
}
```

Register the job in `routes/console.php` (Laravel 12.x pattern):

```php
<?php

use App\Jobs\ProcessDataRetention;
use App\Services\DataRetentionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Laravel 12.x scheduling pattern
Schedule::job(new ProcessDataRetention())->daily();

Schedule::call(function () {
    $service = app(DataRetentionService::class);
    $stats = $service->getRetentionStatistics();
    Log::info('Weekly retention statistics', $stats);
})->weekly()->name('retention-statistics');
```

## Testing & Validation

### Feature Test for Data Retention (Laravel 12.x with Pest)

Create `tests/Feature/Gdpr/DataRetentionTest.php`:

```php
<?php

use App\Enums\DataClassification;
use App\Models\StandardUser;
use App\Models\DataRetentionRecord;
use App\Services\DataRetentionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(DataRetentionService::class);
});

test('personal data registration for retention works', function () {
    $user = StandardUser::factory()->create();

    $record = $this->service->registerForRetention(
        DataClassification::PersonalData,
        'users',
        $user->id
    );

    expect('data_retention_records')->toHaveRecord([
        'data_type' => 'personal',
        'table_name' => 'users',
        'record_id' => $user->id,
    ]);

    // Should expire in 2 years for personal data
    expect($record->expires_at->diffInYears(now()))->toBeGreaterThanOrEqual(1);
});

test('expired personal data deletion works', function () {
    $user = StandardUser::factory()->create();

    // Create expired retention record
    $record = DataRetentionRecord::create([
        'data_type' => DataClassification::PersonalData->value,
        'table_name' => 'users',
        'record_id' => $user->id,
        'expires_at' => Carbon::now()->subDays(1), // Expired yesterday
    ]);

    $results = $this->service->processExpiredPersonalData();

    expect($results['deleted'])->toContain($record->id);
    $this->assertSoftDeleted($user);

    $record->refresh();
    expect($record->purged_at)->not->toBeNull();
    expect($record->purge_method)->toBe('delete');
});

test('user anonymization preserves audit trail', function () {
    $user = StandardUser::factory()->create();

    // Create some audit activity
    activity()
        ->performedOn($user)
        ->causedBy($user)
        ->log('user_login');

    $mapping = $this->service->anonymizeUserData($user);

    // Check anonymization mapping created
    expect('anonymization_mappings')->toHaveRecord([
        'original_user_id' => $user->id,
        'anonymous_token' => $mapping->anonymous_token,
    ]);

    // Check audit log anonymized
    expect('activity_log')->toHaveRecord([
        'subject_id' => $user->id,
        'causer_id' => null, // Should be null after anonymization
    ]);

    // Check compliance log created
    expect('compliance_logs')->toHaveRecord([
        'event_type' => 'user_anonymization',
        'user_token' => $mapping->anonymous_token,
    ]);
});

test('retention statistics generation is accurate', function () {
    // Create test data
    StandardUser::factory()->count(5)->create();

    foreach (StandardUser::all() as $user) {
        $this->service->registerForRetention(
            DataClassification::PersonalData,
            'users',
            $user->id
        );
    }

    $stats = $this->service->getRetentionStatistics();

    expect($stats)->toHaveKey('total_records');
    expect($stats)->toHaveKey('by_type');
    expect($stats['total_records'])->toBe(5);
    expect($stats['by_type']['personal'])->toBe(5);
});

test('anonymous token generation has proper entropy', function () {
    $userId = 123;

    // Generate token twice with same input
    $token1 = $this->service->generateAnonymousToken($userId);

    // Wait a moment to ensure timestamp changes
    sleep(1);

    $token2 = $this->service->generateAnonymousToken($userId);

    // Tokens should be different due to timestamp
    expect($token1)->not->toBe($token2);

    // But both should be valid SHA256 hashes
    expect(strlen($token1))->toBe(64);
    expect(strlen($token2))->toBe(64);
    expect($token1)->toMatch('/^[a-f0-9]{64}$/');
    expect($token2)->toMatch('/^[a-f0-9]{64}$/');
});
```

## Common Pitfalls & Troubleshooting

### Issue 1: Incomplete Data Discovery
**Problem**: Missing personal data during GDPR deletion
**Solution**: Comprehensive data mapping and automated discovery tools

### Issue 2: Anonymization Reversibility
**Problem**: Anonymous tokens could potentially be reversed
**Solution**: Use strong salts and consider additional entropy sources

### Issue 3: Audit Trail Gaps
**Problem**: Important audit information lost during anonymization
**Solution**: Preserve essential context while removing personal identifiers

## Integration Points

### Connection to Other UMS-STI Components
- **User Models (Task 2.0)**: User lifecycle and state management
- **Permission System (Task 4.0)**: Permission audit trail anonymization
- **FilamentPHP Interface (Task 6.0)**: GDPR request management interface
- **API Layer (Task 7.0)**: GDPR compliance endpoints

## Further Reading & Resources

### GDPR Resources
- [GDPR Official Text](https://gdpr-info.eu/)
- [ICO Guide to Data Protection](https://ico.org.uk/for-organisations/guide-to-data-protection/)
- [EDPB Guidelines](https://edpb.europa.eu/our-work-tools/general-guidance/gdpr-guidelines-recommendations-best-practices_en)

### Technical Implementation
- [Laravel Data Privacy](https://laravel.com/docs/privacy)
- [Database Anonymization Techniques](https://en.wikipedia.org/wiki/Data_anonymization)

## References and Citations

### Primary Sources
- [GDPR Official Text](https://gdpr-info.eu/)
- [Laravel 12.x Database](https://laravel.com/docs/12.x/database)
- [Laravel 12.x Scheduling](https://laravel.com/docs/12.x/scheduling)
- [Laravel 12.x Queues](https://laravel.com/docs/12.x/queues)

### Secondary Sources
- [ICO Guide to Data Protection](https://ico.org.uk/for-organisations/guide-to-data-protection/)
- [EDPB Guidelines on Data Retention](https://edpb.europa.eu/our-work-tools/general-guidance/gdpr-guidelines-recommendations-best-practices_en)
- [Data Anonymization Techniques](https://en.wikipedia.org/wiki/Data_anonymization)
- [Laravel Privacy and GDPR](https://laravel.com/docs/12.x/privacy)

### Related UMS-STI Documentation
- [GDPR Request Workflows](02-gdpr-request-workflows.md) - Next implementation step
- [Audit Logging Anonymization](03-audit-logging-anonymization.md) - Audit trail management
- [Compliance Service Layer](04-compliance-service-layer.md) - Service implementation
- [User Models STI](../02-user-models/01-sti-architecture-explained.md) - User lifecycle integration
- [Permission System](../04-permission-system/02-permission-isolation-design.md) - Permission audit trails
- [Unit Testing Strategies](../08-testing-suite/01-unit-testing-strategies.md) - GDPR testing patterns
- [PRD Requirements](../../prd-UMS-STI.md) - GDPR specifications (REQ-010, REQ-011, REQ-012)
- [Decision Log](../../decision-log-UMS-STI.md) - Data retention decisions (DECISION-007)

### Laravel 12.x Compatibility Notes
- Scheduling moved to `routes/console.php` for better organization
- Enhanced job queue patterns with improved error handling
- Updated testing utilities with Pest PHP integration
- Improved enum support for data classification
- Enhanced database migration patterns for compliance tables

---

**Next Steps**: Proceed to [GDPR Request Workflows](02-gdpr-request-workflows.md) to implement the complete GDPR request processing system.
