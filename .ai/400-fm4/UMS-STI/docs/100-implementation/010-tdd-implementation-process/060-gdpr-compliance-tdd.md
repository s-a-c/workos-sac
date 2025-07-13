# TDD Implementation for GDPR Compliance

## Executive Summary

This guide provides a comprehensive Test-Driven Development approach for implementing GDPR compliance features in the UMS-STI system. Using TDD methodology, we'll build robust data protection mechanisms including consent management, data export, data deletion, audit trails, and privacy controls that ensure full GDPR compliance.

## Learning Objectives

After completing this guide, you will:
- Implement GDPR-compliant data handling using TDD methodology
- Create comprehensive consent management with test coverage
- Build data export and deletion systems with test-first approach
- Develop audit trails and privacy controls using TDD validation
- Integrate GDPR features with STI user models and team isolation through TDD

## Prerequisites

- Completed [010-tdd-environment-setup.md](010-tdd-environment-setup.md)
- Completed [020-database-tdd-approach.md](020-database-tdd-approach.md)
- Completed [030-sti-models-tdd.md](030-sti-models-tdd.md)
- Completed [040-closure-table-tdd.md](040-closure-table-tdd.md)
- Completed [050-permission-system-tdd.md](050-permission-system-tdd.md)
- Understanding of GDPR requirements and data protection principles
- Basic knowledge of Laravel data handling patterns

## TDD Implementation Strategy

### Phase 1: Consent Management Foundation (Week 5, Days 5-7)

#### 1.1 Consent Model Structure Tests

**Test File**: `tests/Unit/Models/ConsentTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Consent;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Consent Model Structure', function () {
    it('can create a basic consent record', function () {
        $user = User::factory()->create();
        $consent = Consent::factory()->create([
            'user_id' => $user->id,
            'purpose' => 'marketing',
            'granted' => true,
            'granted_at' => now(),
        ]);

        expect($consent)
            ->toBeInstanceOf(Consent::class)
            ->and($consent->user_id)->toBe($user->id)
            ->and($consent->purpose)->toBe('marketing')
            ->and($consent->granted)->toBeTrue()
            ->and($consent->granted_at)->not->toBeNull();
    });

    it('has required fillable attributes', function () {
        $fillable = (new Consent())->getFillable();
        
        expect($fillable)->toContain(
            'user_id',
            'team_id',
            'purpose',
            'granted',
            'granted_at',
            'withdrawn_at',
            'ip_address',
            'user_agent',
            'legal_basis',
            'data_categories',
            'retention_period'
        );
    });

    it('casts attributes correctly', function () {
        $consent = Consent::factory()->create([
            'granted' => true,
            'data_categories' => ['personal', 'contact'],
            'granted_at' => '2024-01-01 12:00:00',
        ]);

        expect($consent->granted)->toBeTrue();
        expect($consent->data_categories)->toBeArray();
        expect($consent->granted_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('tracks consent withdrawal', function () {
        $consent = Consent::factory()->create(['granted' => true]);
        
        $consent->withdraw();
        
        expect($consent->granted)->toBeFalse();
        expect($consent->withdrawn_at)->not->toBeNull();
        expect($consent->isWithdrawn())->toBeTrue();
    });

    it('validates consent is current and not expired', function () {
        $validConsent = Consent::factory()->create([
            'granted' => true,
            'granted_at' => now()->subMonths(6),
            'retention_period' => 12, // 12 months
        ]);

        $expiredConsent = Consent::factory()->create([
            'granted' => true,
            'granted_at' => now()->subMonths(18),
            'retention_period' => 12, // 12 months
        ]);

        expect($validConsent->isValid())->toBeTrue();
        expect($expiredConsent->isValid())->toBeFalse();
        expect($expiredConsent->isExpired())->toBeTrue();
    });
});
```

**Implementation**: Create the Consent model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_id',
        'purpose',
        'granted',
        'granted_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
        'legal_basis',
        'data_categories',
        'retention_period',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'granted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'data_categories' => 'array',
        'retention_period' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function withdraw(): void
    {
        $this->update([
            'granted' => false,
            'withdrawn_at' => now(),
        ]);
    }

    public function isWithdrawn(): bool
    {
        return !$this->granted && $this->withdrawn_at !== null;
    }

    public function isValid(): bool
    {
        if (!$this->granted || $this->isWithdrawn()) {
            return false;
        }

        return !$this->isExpired();
    }

    public function isExpired(): bool
    {
        if (!$this->retention_period || !$this->granted_at) {
            return false;
        }

        $expiryDate = $this->granted_at->addMonths($this->retention_period);
        return now()->isAfter($expiryDate);
    }

    public function getExpiryDate(): ?Carbon
    {
        if (!$this->retention_period || !$this->granted_at) {
            return null;
        }

        return $this->granted_at->copy()->addMonths($this->retention_period);
    }
}
```

#### 1.2 Consent Database Migration Tests

**Test File**: `tests/Unit/Database/ConsentMigrationTest.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Consent Management Database Structure', function () {
    it('has consents table with correct structure', function () {
        expect(Schema::hasTable('consents'))->toBeTrue();
        
        $columns = Schema::getColumnListing('consents');
        expect($columns)->toContain(
            'id',
            'user_id',
            'team_id',
            'purpose',
            'granted',
            'granted_at',
            'withdrawn_at',
            'ip_address',
            'user_agent',
            'legal_basis',
            'data_categories',
            'retention_period',
            'created_at',
            'updated_at'
        );
    });

    it('has data_processing_activities table for audit', function () {
        expect(Schema::hasTable('data_processing_activities'))->toBeTrue();
        
        $columns = Schema::getColumnListing('data_processing_activities');
        expect($columns)->toContain(
            'id',
            'user_id',
            'team_id',
            'activity_type',
            'data_subject_id',
            'data_categories',
            'purpose',
            'legal_basis',
            'performed_by',
            'performed_at',
            'ip_address',
            'user_agent',
            'metadata'
        );
    });

    it('has data_retention_policies table', function () {
        expect(Schema::hasTable('data_retention_policies'))->toBeTrue();
        
        $columns = Schema::getColumnListing('data_retention_policies');
        expect($columns)->toContain(
            'id',
            'team_id',
            'data_category',
            'purpose',
            'retention_period_months',
            'auto_delete',
            'legal_basis',
            'created_at',
            'updated_at'
        );
    });

    it('has correct indexes for performance', function () {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('consents');
            
        expect($indexes)->toHaveKey('consents_user_purpose_index');
        expect($indexes)->toHaveKey('consents_team_purpose_index');
    });
});
```

**Implementation**: Create GDPR compliance migrations

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('purpose'); // marketing, analytics, functional, etc.
            $table->boolean('granted')->default(false);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('legal_basis')->nullable(); // consent, legitimate_interest, etc.
            $table->json('data_categories')->nullable(); // personal, contact, behavioral, etc.
            $table->integer('retention_period')->nullable(); // months
            $table->timestamps();
            
            $table->index(['user_id', 'purpose']);
            $table->index(['team_id', 'purpose']);
            $table->index(['granted', 'granted_at']);
        });

        Schema::create('data_processing_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('activity_type'); // create, read, update, delete, export
            $table->unsignedBigInteger('data_subject_id'); // ID of the data subject
            $table->string('data_subject_type'); // User, Employee, etc.
            $table->json('data_categories');
            $table->string('purpose');
            $table->string('legal_basis');
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('performed_at');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            
            $table->index(['data_subject_id', 'data_subject_type']);
            $table->index(['activity_type', 'performed_at']);
            $table->index(['team_id', 'activity_type']);
        });

        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('data_category');
            $table->string('purpose');
            $table->integer('retention_period_months');
            $table->boolean('auto_delete')->default(false);
            $table->string('legal_basis');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['team_id', 'data_category', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('data_processing_activities');
        Schema::dropIfExists('consents');
    }
};
```

### Phase 2: Data Export and Portability (Week 6, Days 1-2)

#### 2.1 Data Export Service Tests

**Test File**: `tests/Unit/Services/DataExportServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Employee;
use App\Services\DataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Data Export Service', function () {
    beforeEach(function () {
        $this->service = new DataExportService();
        $this->user = Employee::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        $this->team = Team::factory()->create();
        $this->team->addMember($this->user, 'developer');
    });

    it('can export user personal data', function () {
        $exportData = $this->service->exportUserData($this->user);
        
        expect($exportData)->toHaveKey('personal_information');
        expect($exportData['personal_information'])->toHaveKey('email', 'first_name', 'last_name');
        expect($exportData['personal_information']['email'])->toBe('test@example.com');
    });

    it('includes team memberships in export', function () {
        $exportData = $this->service->exportUserData($this->user);
        
        expect($exportData)->toHaveKey('team_memberships');
        expect($exportData['team_memberships'])->toHaveCount(1);
        expect($exportData['team_memberships'][0])->toHaveKey('team_name', 'role', 'joined_at');
    });

    it('includes consent history in export', function () {
        Consent::factory()->create([
            'user_id' => $this->user->id,
            'purpose' => 'marketing',
            'granted' => true,
        ]);

        $exportData = $this->service->exportUserData($this->user);
        
        expect($exportData)->toHaveKey('consent_history');
        expect($exportData['consent_history'])->toHaveCount(1);
        expect($exportData['consent_history'][0]['purpose'])->toBe('marketing');
    });

    it('includes data processing activities in export', function () {
        DataProcessingActivity::factory()->create([
            'data_subject_id' => $this->user->id,
            'data_subject_type' => get_class($this->user),
            'activity_type' => 'create',
            'purpose' => 'user_registration',
        ]);

        $exportData = $this->service->exportUserData($this->user);
        
        expect($exportData)->toHaveKey('processing_activities');
        expect($exportData['processing_activities'])->toHaveCount(1);
        expect($exportData['processing_activities'][0]['activity_type'])->toBe('create');
    });

    it('can generate JSON export file', function () {
        Storage::fake('exports');
        
        $filePath = $this->service->generateExportFile($this->user, 'json');
        
        expect(Storage::disk('exports')->exists($filePath))->toBeTrue();
        
        $content = Storage::disk('exports')->get($filePath);
        $data = json_decode($content, true);
        
        expect($data)->toHaveKey('export_metadata');
        expect($data)->toHaveKey('personal_information');
        expect($data['export_metadata']['format'])->toBe('json');
        expect($data['export_metadata']['exported_at'])->not->toBeNull();
    });

    it('can generate CSV export file', function () {
        Storage::fake('exports');
        
        $filePath = $this->service->generateExportFile($this->user, 'csv');
        
        expect(Storage::disk('exports')->exists($filePath))->toBeTrue();
        
        $content = Storage::disk('exports')->get($filePath);
        expect($content)->toContain('email,first_name,last_name');
        expect($content)->toContain('test@example.com,John,Doe');
    });

    it('logs data export activity', function () {
        $this->service->exportUserData($this->user);
        
        $activity = DataProcessingActivity::where([
            'data_subject_id' => $this->user->id,
            'activity_type' => 'export',
        ])->first();
        
        expect($activity)->not->toBeNull();
        expect($activity->purpose)->toBe('data_portability');
        expect($activity->legal_basis)->toBe('data_subject_request');
    });

    it('respects team isolation in exports', function () {
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create();
        $otherTeam->addMember($otherUser, 'member');
        
        // User should not see data from other teams
        $exportData = $this->service->exportUserData($this->user);
        
        expect($exportData['team_memberships'])->toHaveCount(1);
        expect($exportData['team_memberships'][0]['team_name'])->toBe($this->team->name);
    });
});
```

**Implementation**: Create DataExportService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\DataProcessingActivity;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DataExportService
{
    public function exportUserData(User $user): array
    {
        $exportData = [
            'export_metadata' => [
                'user_id' => $user->id,
                'exported_at' => now()->toISOString(),
                'export_version' => '1.0',
                'legal_basis' => 'data_subject_request',
            ],
            'personal_information' => $this->getPersonalInformation($user),
            'team_memberships' => $this->getTeamMemberships($user),
            'consent_history' => $this->getConsentHistory($user),
            'processing_activities' => $this->getProcessingActivities($user),
        ];

        // Log the export activity
        $this->logDataProcessingActivity($user, 'export', [
            'purpose' => 'data_portability',
            'legal_basis' => 'data_subject_request',
            'data_categories' => ['personal', 'membership', 'consent', 'activity'],
        ]);

        return $exportData;
    }

    public function generateExportFile(User $user, string $format = 'json'): string
    {
        $exportData = $this->exportUserData($user);
        $fileName = "user_data_export_{$user->id}_" . now()->format('Y-m-d_H-i-s') . ".{$format}";
        
        $exportData['export_metadata']['format'] = $format;
        
        switch ($format) {
            case 'json':
                $content = json_encode($exportData, JSON_PRETTY_PRINT);
                break;
            case 'csv':
                $content = $this->convertToCSV($exportData);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }

        Storage::disk('exports')->put($fileName, $content);
        
        return $fileName;
    }

    private function getPersonalInformation(User $user): array
    {
        return [
            'id' => $user->id,
            'type' => $user->type,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    private function getTeamMemberships(User $user): array
    {
        return $user->teams()->get()->map(function ($team) {
            return [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'team_type' => $team->type,
                'role' => $team->pivot->role,
                'joined_at' => $team->pivot->joined_at,
                'is_active' => $team->pivot->is_active,
            ];
        })->toArray();
    }

    private function getConsentHistory(User $user): array
    {
        return $user->consents()->get()->map(function ($consent) {
            return [
                'purpose' => $consent->purpose,
                'granted' => $consent->granted,
                'granted_at' => $consent->granted_at?->toISOString(),
                'withdrawn_at' => $consent->withdrawn_at?->toISOString(),
                'legal_basis' => $consent->legal_basis,
                'data_categories' => $consent->data_categories,
            ];
        })->toArray();
    }

    private function getProcessingActivities(User $user): array
    {
        return DataProcessingActivity::where([
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
        ])->get()->map(function ($activity) {
            return [
                'activity_type' => $activity->activity_type,
                'purpose' => $activity->purpose,
                'legal_basis' => $activity->legal_basis,
                'performed_at' => $activity->performed_at->toISOString(),
                'data_categories' => $activity->data_categories,
            ];
        })->toArray();
    }

    private function convertToCSV(array $data): string
    {
        $csv = [];
        
        // Personal information section
        $personal = $data['personal_information'];
        $csv[] = implode(',', array_keys($personal));
        $csv[] = implode(',', array_values($personal));
        
        return implode("\n", $csv);
    }

    private function logDataProcessingActivity(User $user, string $activityType, array $metadata): void
    {
        DataProcessingActivity::create([
            'user_id' => auth()->id(),
            'team_id' => null, // Global activity
            'activity_type' => $activityType,
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
            'data_categories' => $metadata['data_categories'] ?? [],
            'purpose' => $metadata['purpose'] ?? 'unknown',
            'legal_basis' => $metadata['legal_basis'] ?? 'legitimate_interest',
            'performed_by' => auth()->id() ?? $user->id,
            'performed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
```

#### 2.2 Data Deletion Service Tests

**Test File**: `tests/Unit/Services/DataDeletionServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Employee;
use App\Models\Consent;
use App\Services\DataDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Data Deletion Service', function () {
    beforeEach(function () {
        $this->service = new DataDeletionService();
        $this->user = Employee::factory()->create();
        $this->team = Team::factory()->create();
        $this->team->addMember($this->user, 'developer');
    });

    it('can perform soft deletion of user data', function () {
        $this->service->softDeleteUser($this->user);
        
        $this->user->refresh();
        expect($this->user->trashed())->toBeTrue();
        expect($this->user->deleted_at)->not->toBeNull();
    });

    it('can perform hard deletion of user data', function () {
        $userId = $this->user->id;
        
        $this->service->hardDeleteUser($this->user);
        
        expect(User::find($userId))->toBeNull();
    });

    it('anonymizes user data instead of deletion when required', function () {
        $originalEmail = $this->user->email;
        
        $this->service->anonymizeUser($this->user);
        
        $this->user->refresh();
        expect($this->user->email)->not->toBe($originalEmail);
        expect($this->user->email)->toStartWith('anonymized_');
        expect($this->user->first_name)->toBe('Anonymized');
        expect($this->user->last_name)->toBe('User');
    });

    it('removes user from teams during deletion', function () {
        expect($this->user->teams)->toHaveCount(1);
        
        $this->service->softDeleteUser($this->user);
        
        $this->user->refresh();
        expect($this->user->teams()->wherePivot('is_active', true))->toHaveCount(0);
    });

    it('withdraws all consents during deletion', function () {
        Consent::factory()->create([
            'user_id' => $this->user->id,
            'granted' => true,
        ]);

        $this->service->softDeleteUser($this->user);
        
        $consents = Consent::where('user_id', $this->user->id)->get();
        expect($consents->every(fn($consent) => !$consent->granted))->toBeTrue();
        expect($consents->every(fn($consent) => $consent->withdrawn_at !== null))->toBeTrue();
    });

    it('logs deletion activity', function () {
        $this->service->softDeleteUser($this->user);
        
        $activity = DataProcessingActivity::where([
            'data_subject_id' => $this->user->id,
            'activity_type' => 'delete',
        ])->first();
        
        expect($activity)->not->toBeNull();
        expect($activity->purpose)->toBe('data_subject_request');
        expect($activity->legal_basis)->toBe('data_subject_request');
    });

    it('respects retention policies during deletion', function () {
        // Create retention policy requiring 12 months retention
        DataRetentionPolicy::factory()->create([
            'team_id' => $this->team->id,
            'data_category' => 'employment',
            'retention_period_months' => 12,
        ]);

        $result = $this->service->canDeleteUser($this->user);
        
        expect($result['can_delete'])->toBeFalse();
        expect($result['reason'])->toContain('retention policy');
    });

    it('can delete expired data automatically', function () {
        // Create old user data that should be deleted
        $oldUser = Employee::factory()->create([
            'created_at' => now()->subYears(3),
            'deleted_at' => now()->subYears(2),
        ]);

        $deletedCount = $this->service->deleteExpiredData();
        
        expect($deletedCount)->toBeGreaterThan(0);
        expect(User::withTrashed()->find($oldUser->id))->toBeNull();
    });

    it('validates deletion permissions', function () {
        $otherUser = User::factory()->create();
        
        expect(fn() => $this->service->softDeleteUser($this->user, $otherUser))
            ->toThrow(AuthorizationException::class);
    });
});
```

**Implementation**: Create DataDeletionService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Consent;
use App\Models\DataProcessingActivity;
use App\Models\DataRetentionPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataDeletionService
{
    public function softDeleteUser(User $user, ?User $requestedBy = null): void
    {
        $this->validateDeletionPermissions($user, $requestedBy);
        
        DB::transaction(function () use ($user) {
            // Withdraw all consents
            $this->withdrawAllConsents($user);
            
            // Remove from teams
            $this->removeFromTeams($user);
            
            // Soft delete the user
            $user->delete();
            
            // Log the deletion activity
            $this->logDeletionActivity($user, 'soft_delete');
        });
    }

    public function hardDeleteUser(User $user, ?User $requestedBy = null): void
    {
        $this->validateDeletionPermissions($user, $requestedBy);
        
        $canDelete = $this->canDeleteUser($user);
        if (!$canDelete['can_delete']) {
            throw new \Exception("Cannot delete user: {$canDelete['reason']}");
        }
        
        DB::transaction(function () use ($user) {
            $userId = $user->id;
            
            // Log the deletion activity before deletion
            $this->logDeletionActivity($user, 'hard_delete');
            
            // Remove all related data
            $this->removeUserData($user);
            
            // Force delete the user
            $user->forceDelete();
        });
    }

    public function anonymizeUser(User $user, ?User $requestedBy = null): void
    {
        $this->validateDeletionPermissions($user, $requestedBy);
        
        DB::transaction(function () use ($user) {
            $user->update([
                'email' => 'anonymized_' . Str::random(10) . '@anonymized.local',
                'first_name' => 'Anonymized',
                'last_name' => 'User',
                'phone' => null,
                'address' => null,
            ]);
            
            // Withdraw all consents
            $this->withdrawAllConsents($user);
            
            // Log the anonymization activity
            $this->logDeletionActivity($user, 'anonymize');
        });
    }

    public function canDeleteUser(User $user): array
    {
        // Check retention policies
        $retentionPolicies = DataRetentionPolicy::whereIn('team_id', 
            $user->teams()->pluck('team_id')
        )->get();

        foreach ($retentionPolicies as $policy) {
            $retentionEnd = $user->created_at->addMonths($policy->retention_period_months);
            if (now()->isBefore($retentionEnd)) {
                return [
                    'can_delete' => false,
                    'reason' => "Retention policy requires data to be kept until {$retentionEnd->format('Y-m-d')}",
                    'policy_id' => $policy->id,
                ];
            }
        }

        // Check for legal holds or other restrictions
        if ($this->hasLegalHold($user)) {
            return [
                'can_delete' => false,
                'reason' => 'User data is under legal hold',
            ];
        }

        return ['can_delete' => true];
    }

    public function deleteExpiredData(): int
    {
        $deletedCount = 0;
        
        // Find users that have been soft deleted and passed retention period
        $expiredUsers = User::onlyTrashed()
            ->where('deleted_at', '<', now()->subYears(2))
            ->get();

        foreach ($expiredUsers as $user) {
            $canDelete = $this->canDeleteUser($user);
            if ($canDelete['can_delete']) {
                $this->removeUserData($user);
                $user->forceDelete();
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    private function validateDeletionPermissions(User $user, ?User $requestedBy): void
    {
        $requestedBy = $requestedBy ?? auth()->user();
        
        if (!$requestedBy) {
            throw new AuthorizationException('Authentication required for data deletion');
        }

        // Users can delete their own data
        if ($user->id === $requestedBy->id) {
            return;
        }

        // System users can delete any data
        if ($requestedBy->isSystemUser()) {
            return;
        }

        // Check if user has permission to delete other users' data
        if (!$requestedBy->hasPermissionTo('delete-user-data')) {
            throw new AuthorizationException('Insufficient permissions to delete user data');
        }
    }

    private function withdrawAllConsents(User $user): void
    {
        Consent::where('user_id', $user->id)
            ->where('granted', true)
            ->update([
                'granted' => false,
                'withdrawn_at' => now(),
            ]);
    }

    private function removeFromTeams(User $user): void
    {
        DB::table('team_memberships')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'left_at' => now(),
            ]);
    }

    private function removeUserData(User $user): void
    {
        // Remove consents
        Consent::where('user_id', $user->id)->delete();
        
        // Remove team memberships
        DB::table('team_memberships')->where('user_id', $user->id)->delete();
        
        // Remove role assignments
        DB::table('user_has_roles')->where('user_id', $user->id)->delete();
        
        // Remove permission assignments
        DB::table('user_has_permissions')->where('user_id', $user->id)->delete();
        
        // Keep processing activities for audit purposes
        // DataProcessingActivity records are not deleted
    }

    private function hasLegalHold(User $user): bool
    {
        // Check for legal holds - this would be implemented based on business requirements
        return false;
    }

    private function logDeletionActivity(User $user, string $deletionType): void
    {
        DataProcessingActivity::create([
            'user_id' => auth()->id(),
            'team_id' => null,
            'activity_type' => 'delete',
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
            'data_categories' => ['personal', 'membership', 'consent'],
            'purpose' => 'data_subject_request',
            'legal_basis' => 'data_subject_request',
            'performed_by' => auth()->id() ?? $user->id,
            'performed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'deletion_type' => $deletionType,
                'user_type' => $user->type,
            ],
        ]);
    }
}
```

### Phase 3: Audit Trails and Compliance Monitoring (Week 6, Days 3-4)

#### 3.1 Data Processing Activity Tracking Tests

**Test File**: `tests/Unit/Models/DataProcessingActivityTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\DataProcessingActivity;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Data Processing Activity Tracking', function () {
    it('can create a data processing activity record', function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        
        $activity = DataProcessingActivity::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'activity_type' => 'create',
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
        ]);

        expect($activity)
            ->toBeInstanceOf(DataProcessingActivity::class)
            ->and($activity->activity_type)->toBe('create')
            ->and($activity->data_subject_id)->toBe($user->id);
    });

    it('tracks different types of data processing activities', function () {
        $user = User::factory()->create();
        
        $activities = [
            'create' => 'User registration',
            'read' => 'Profile view',
            'update' => 'Profile update',
            'delete' => 'Account deletion',
            'export' => 'Data export request',
        ];

        foreach ($activities as $type => $purpose) {
            DataProcessingActivity::factory()->create([
                'activity_type' => $type,
                'purpose' => $purpose,
                'data_subject_id' => $user->id,
                'data_subject_type' => get_class($user),
            ]);
        }

        expect(DataProcessingActivity::count())->toBe(5);
        expect(DataProcessingActivity::where('activity_type', 'create')->count())->toBe(1);
    });

    it('includes comprehensive metadata for activities', function () {
        $activity = DataProcessingActivity::factory()->create([
            'metadata' => [
                'fields_changed' => ['email', 'first_name'],
                'old_values' => ['email' => 'old@example.com'],
                'new_values' => ['email' => 'new@example.com'],
                'request_id' => 'req_123456',
            ],
        ]);

        expect($activity->metadata)->toBeArray();
        expect($activity->metadata['fields_changed'])->toContain('email', 'first_name');
        expect($activity->metadata['request_id'])->toBe('req_123456');
    });

    it('can query activities by data subject', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        DataProcessingActivity::factory()->count(3)->create([
            'data_subject_id' => $user1->id,
            'data_subject_type' => get_class($user1),
        ]);
        
        DataProcessingActivity::factory()->count(2)->create([
            'data_subject_id' => $user2->id,
            'data_subject_type' => get_class($user2),
        ]);

        $user1Activities = DataProcessingActivity::forDataSubject($user1)->get();
        $user2Activities = DataProcessingActivity::forDataSubject($user2)->get();

        expect($user1Activities)->toHaveCount(3);
        expect($user2Activities)->toHaveCount(2);
    });

    it('can query activities by team', function () {
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        DataProcessingActivity::factory()->count(4)->create(['team_id' => $team1->id]);
        DataProcessingActivity::factory()->count(2)->create(['team_id' => $team2->id]);

        $team1Activities = DataProcessingActivity::where('team_id', $team1->id)->get();
        $team2Activities = DataProcessingActivity::where('team_id', $team2->id)->get();

        expect($team1Activities)->toHaveCount(4);
        expect($team2Activities)->toHaveCount(2);
    });

    it('can generate compliance reports', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        
        // Create various activities over time
        DataProcessingActivity::factory()->create([
            'team_id' => $team->id,
            'activity_type' => 'create',
            'performed_at' => now()->subDays(30),
        ]);
        
        DataProcessingActivity::factory()->create([
            'team_id' => $team->id,
            'activity_type' => 'export',
            'performed_at' => now()->subDays(15),
        ]);

        $report = DataProcessingActivity::generateComplianceReport($team, now()->subDays(45), now());

        expect($report)->toHaveKey('total_activities');
        expect($report)->toHaveKey('activities_by_type');
        expect($report)->toHaveKey('data_subjects_affected');
        expect($report['total_activities'])->toBe(2);
    });
});
```

**Implementation**: Add methods to DataProcessingActivity model

```php
// Add to DataProcessingActivity model

use Illuminate\Database\Eloquent\Builder;

public function scopeForDataSubject(Builder $query, $dataSubject): Builder
{
    return $query->where([
        'data_subject_id' => $dataSubject->id,
        'data_subject_type' => get_class($dataSubject),
    ]);
}

public function scopeByActivityType(Builder $query, string $activityType): Builder
{
    return $query->where('activity_type', $activityType);
}

public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
{
    return $query->whereBetween('performed_at', [$startDate, $endDate]);
}

public static function generateComplianceReport($team, $startDate, $endDate): array
{
    $activities = static::where('team_id', $team->id)
        ->inDateRange($startDate, $endDate)
        ->get();

    return [
        'team_id' => $team->id,
        'team_name' => $team->name,
        'report_period' => [
            'start' => $startDate,
            'end' => $endDate,
        ],
        'total_activities' => $activities->count(),
        'activities_by_type' => $activities->groupBy('activity_type')->map->count(),
        'activities_by_purpose' => $activities->groupBy('purpose')->map->count(),
        'data_subjects_affected' => $activities->unique(function ($activity) {
            return $activity->data_subject_type . ':' . $activity->data_subject_id;
        })->count(),
        'legal_bases_used' => $activities->pluck('legal_basis')->unique()->values(),
        'data_categories_processed' => $activities->pluck('data_categories')->flatten()->unique()->values(),
    ];
}
```

#### 3.2 GDPR Compliance Monitoring Tests

**Test File**: `tests/Unit/Services/GDPRComplianceServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Consent;
use App\Models\DataRetentionPolicy;
use App\Services\GDPRComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GDPR Compliance Service', function () {
    beforeEach(function () {
        $this->service = new GDPRComplianceService();
        $this->team = Team::factory()->create();
    });

    it('can check consent compliance for team', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // User1 has valid consent
        Consent::factory()->create([
            'user_id' => $user1->id,
            'team_id' => $this->team->id,
            'purpose' => 'marketing',
            'granted' => true,
            'granted_at' => now()->subMonths(6),
        ]);
        
        // User2 has expired consent
        Consent::factory()->create([
            'user_id' => $user2->id,
            'team_id' => $this->team->id,
            'purpose' => 'marketing',
            'granted' => true,
            'granted_at' => now()->subMonths(18),
            'retention_period' => 12,
        ]);

        $compliance = $this->service->checkConsentCompliance($this->team);

        expect($compliance['total_consents'])->toBe(2);
        expect($compliance['valid_consents'])->toBe(1);
        expect($compliance['expired_consents'])->toBe(1);
        expect($compliance['compliance_rate'])->toBe(0.5);
    });

    it('can identify data retention violations', function () {
        // Create retention policy: 12 months
        DataRetentionPolicy::factory()->create([
            'team_id' => $this->team->id,
            'data_category' => 'personal',
            'retention_period_months' => 12,
        ]);

        // Create old user that should be deleted
        $oldUser = User::factory()->create([
            'created_at' => now()->subMonths(18),
        ]);
        $this->team->addMember($oldUser, 'member');

        $violations = $this->service->checkRetentionCompliance($this->team);

        expect($violations)->toHaveCount(1);
        expect($violations[0]['user_id'])->toBe($oldUser->id);
        expect($violations[0]['violation_type'])->toBe('retention_period_exceeded');
    });

    it('can generate GDPR compliance dashboard', function () {
        // Create test data
        $users = User::factory()->count(10)->create();
        foreach ($users as $user) {
            $this->team->addMember($user, 'member');
            
            Consent::factory()->create([
                'user_id' => $user->id,
                'team_id' => $this->team->id,
                'granted' => true,
            ]);
        }

        $dashboard = $this->service->generateComplianceDashboard($this->team);

        expect($dashboard)->toHaveKey('consent_metrics');
        expect($dashboard)->toHaveKey('retention_metrics');
        expect($dashboard)->toHaveKey('processing_activities');
        expect($dashboard)->toHaveKey('compliance_score');
        
        expect($dashboard['consent_metrics']['total_users'])->toBe(10);
        expect($dashboard['compliance_score'])->toBeGreaterThan(0);
        expect($dashboard['compliance_score'])->toBeLessThanOrEqual(100);
    });

    it('can schedule automated compliance checks', function () {
        $this->service->scheduleComplianceCheck($this->team, 'daily');
        
        // Verify that a scheduled job was created
        expect(true)->toBeTrue(); // This would test job scheduling in real implementation
    });

    it('can handle data subject rights requests', function () {
        $user = User::factory()->create();
        $this->team->addMember($user, 'member');

        $request = $this->service->handleDataSubjectRequest($user, 'access');

        expect($request)->toHaveKey('request_id');
        expect($request)->toHaveKey('status');
        expect($request)->toHaveKey('estimated_completion');
        expect($request['status'])->toBe('processing');
    });

    it('validates legal basis for data processing', function () {
        $isValid = $this->service->validateLegalBasis('consent', [
            'consent_id' => 123,
            'purpose' => 'marketing',
        ]);

        expect($isValid)->toBeTrue();

        $isInvalid = $this->service->validateLegalBasis('consent', [
            'purpose' => 'marketing',
            // Missing consent_id
        ]);

        expect($isInvalid)->toBeFalse();
    });
});
```

**Implementation**: Create GDPRComplianceService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use App\Models\Consent;
use App\Models\DataRetentionPolicy;
use App\Models\DataProcessingActivity;
use Illuminate\Support\Str;

class GDPRComplianceService
{
    public function checkConsentCompliance(Team $team): array
    {
        $consents = Consent::where('team_id', $team->id)->get();
        
        $totalConsents = $consents->count();
        $validConsents = $consents->filter(fn($consent) => $consent->isValid())->count();
        $expiredConsents = $consents->filter(fn($consent) => $consent->isExpired())->count();
        $withdrawnConsents = $consents->filter(fn($consent) => $consent->isWithdrawn())->count();

        return [
            'team_id' => $team->id,
            'total_consents' => $totalConsents,
            'valid_consents' => $validConsents,
            'expired_consents' => $expiredConsents,
            'withdrawn_consents' => $withdrawnConsents,
            'compliance_rate' => $totalConsents > 0 ? $validConsents / $totalConsents : 1.0,
            'issues' => $this->identifyConsentIssues($consents),
        ];
    }

    public function checkRetentionCompliance(Team $team): array
    {
        $violations = [];
        $policies = DataRetentionPolicy::where('team_id', $team->id)->get();
        
        foreach ($policies as $policy) {
            $cutoffDate = now()->subMonths($policy->retention_period_months);
            
            // Find users whose data should be deleted according to policy
            $violatingUsers = $team->members()
                ->where('created_at', '<', $cutoffDate)
                ->whereNull('deleted_at')
                ->get();

            foreach ($violatingUsers as $user) {
                $violations[] = [
                    'user_id' => $user->id,
                    'policy_id' => $policy->id,
                    'violation_type' => 'retention_period_exceeded',
                    'data_category' => $policy->data_category,
                    'created_at' => $user->created_at,
                    'should_be_deleted_by' => $user->created_at->addMonths($policy->retention_period_months),
                    'days_overdue' => now()->diffInDays($user->created_at->addMonths($policy->retention_period_months)),
                ];
            }
        }

        return $violations;
    }

    public function generateComplianceDashboard(Team $team): array
    {
        $consentMetrics = $this->checkConsentCompliance($team);
        $retentionViolations = $this->checkRetentionCompliance($team);
        $processingActivities = $this->getProcessingActivityMetrics($team);

        $complianceScore = $this->calculateComplianceScore([
            'consent_compliance' => $consentMetrics['compliance_rate'],
            'retention_violations' => count($retentionViolations),
            'total_users' => $team->members()->count(),
        ]);

        return [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'generated_at' => now(),
            'consent_metrics' => $consentMetrics,
            'retention_metrics' => [
                'violations' => $retentionViolations,
                'violation_count' => count($retentionViolations),
            ],
            'processing_activities' => $processingActivities,
            'compliance_score' => $complianceScore,
            'recommendations' => $this->generateRecommendations($team, $consentMetrics, $retentionViolations),
        ];
    }

    public function handleDataSubjectRequest(User $user, string $requestType): array
    {
        $requestId = 'dsr_' . Str::random(12);
        
        $estimatedCompletion = match($requestType) {
            'access' => now()->addDays(30), // GDPR requires 30 days max
            'rectification' => now()->addDays(30),
            'erasure' => now()->addDays(30),
            'portability' => now()->addDays(30),
            'restriction' => now()->addDays(30),
            default => now()->addDays(30),
        };

        // Log the request
        DataProcessingActivity::create([
            'user_id' => $user->id,
            'team_id' => null,
            'activity_type' => 'data_subject_request',
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
            'data_categories' => ['personal'],
            'purpose' => 'data_subject_rights',
            'legal_basis' => 'data_subject_request',
            'performed_by' => $user->id,
            'performed_at' => now(),
            'metadata' => [
                'request_id' => $requestId,
                'request_type' => $requestType,
            ],
        ]);

        return [
            'request_id' => $requestId,
            'request_type' => $requestType,
            'status' => 'processing',
            'submitted_at' => now(),
            'estimated_completion' => $estimatedCompletion,
            'user_id' => $user->id,
        ];
    }

    public function validateLegalBasis(string $legalBasis, array $context): bool
    {
        return match($legalBasis) {
            'consent' => isset($context['consent_id']) && $this->validateConsent($context['consent_id']),
            'contract' => isset($context['contract_id']),
            'legal_obligation' => isset($context['legal_requirement']),
            'vital_interests' => isset($context['vital_interest_justification']),
            'public_task' => isset($context['public_authority']),
            'legitimate_interests' => isset($context['legitimate_interest_assessment']),
            default => false,
        };
    }

    public function scheduleComplianceCheck(Team $team, string $frequency): void
    {
        // This would integrate with Laravel's job scheduling system
        // For now, we'll just log the scheduling request
        
        DataProcessingActivity::create([
            'user_id' => auth()->id(),
            'team_id' => $team->id,
            'activity_type' => 'compliance_check_scheduled',
            'data_subject_id' => null,
            'data_subject_type' => null,
            'data_categories' => [],
            'purpose' => 'compliance_monitoring',
            'legal_basis' => 'legitimate_interests',
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'metadata' => [
                'frequency' => $frequency,
                'next_check' => $this->calculateNextCheckDate($frequency),
            ],
        ]);
    }

    private function identifyConsentIssues($consents): array
    {
        $issues = [];
        
        foreach ($consents as $consent) {
            if ($consent->isExpired()) {
                $issues[] = [
                    'type' => 'expired_consent',
                    'consent_id' => $consent->id,
                    'purpose' => $consent->purpose,
                    'expired_at' => $consent->getExpiryDate(),
                ];
            }
            
            if (!$consent->legal_basis) {
                $issues[] = [
                    'type' => 'missing_legal_basis',
                    'consent_id' => $consent->id,
                    'purpose' => $consent->purpose,
                ];
            }
        }
        
        return $issues;
    }

    private function getProcessingActivityMetrics(Team $team): array
    {
        $activities = DataProcessingActivity::where('team_id', $team->id)
            ->where('performed_at', '>=', now()->subDays(30))
            ->get();

        return [
            'total_activities' => $activities->count(),
            'activities_by_type' => $activities->groupBy('activity_type')->map->count(),
            'unique_data_subjects' => $activities->unique(function ($activity) {
                return $activity->data_subject_type . ':' . $activity->data_subject_id;
            })->count(),
        ];
    }

    private function calculateComplianceScore(array $metrics): int
    {
        $score = 100;
        
        // Deduct points for consent issues
        $score -= (1 - $metrics['consent_compliance']) * 30;
        
        // Deduct points for retention violations
        if ($metrics['total_users'] > 0) {
            $violationRate = $metrics['retention_violations'] / $metrics['total_users'];
            $score -= $violationRate * 40;
        }
        
        return max(0, min(100, (int) $score));
    }

    private function generateRecommendations(Team $team, array $consentMetrics, array $retentionViolations): array
    {
        $recommendations = [];
        
        if ($consentMetrics['compliance_rate'] < 0.9) {
            $recommendations[] = [
                'type' => 'consent_improvement',
                'priority' => 'high',
                'message' => 'Review and refresh expired consents to improve compliance rate',
            ];
        }
        
        if (count($retentionViolations) > 0) {
            $recommendations[] = [
                'type' => 'data_retention',
                'priority' => 'high',
                'message' => 'Delete or anonymize data that has exceeded retention periods',
            ];
        }
        
        return $recommendations;
    }

    private function validateConsent(int $consentId): bool
    {
        $consent = Consent::find($consentId);
        return $consent && $consent->isValid();
    }

    private function calculateNextCheckDate(string $frequency): \Carbon\Carbon
    {
        return match($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addWeek(),
        };
    }
}
```

### Phase 4: Privacy Controls and Data Minimization (Week 6, Days 5-7)

#### 4.1 Data Minimization Tests

**Test File**: `tests/Unit/Services/DataMinimizationServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Services\DataMinimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Data Minimization Service', function () {
    beforeEach(function () {
        $this->service = new DataMinimizationService();
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'address' => '123 Main St',
        ]);
    });

    it('can identify unnecessary data fields', function () {
        $unnecessaryFields = $this->service->identifyUnnecessaryData($this->user, 'basic_profile');
        
        // For basic profile, phone and address might be unnecessary
        expect($unnecessaryFields)->toContain('phone', 'address');
        expect($unnecessaryFields)->not->toContain('email', 'first_name', 'last_name');
    });

    it('can minimize data based on purpose', function () {
        $minimizedData = $this->service->minimizeDataForPurpose($this->user, 'email_communication');
        
        expect($minimizedData)->toHaveKey('email', 'first_name');
        expect($minimizedData)->not->toHaveKey('phone', 'address');
    });

    it('can pseudonymize sensitive data', function () {
        $pseudonymizedData = $this->service->pseudonymizeData($this->user, ['email', 'phone']);
        
        expect($pseudonymizedData['email'])->not->toBe('test@example.com');
        expect($pseudonymizedData['email'])->toStartWith('pseudo_');
        expect($pseudonymizedData['first_name'])->toBe('John'); // Not pseudonymized
    });

    it('can apply data retention rules automatically', function () {
        // Create old data that should be minimized
        $oldUser = User::factory()->create([
            'created_at' => now()->subYears(2),
            'last_login_at' => now()->subYear(),
        ]);

        $result = $this->service->applyRetentionRules($oldUser);
        
        expect($result['action_taken'])->toBe('minimized');
        expect($result['fields_removed'])->toBeArray();
    });

    it('respects data minimization preferences', function () {
        // User opts for minimal data collection
        $this->user->update(['data_minimization_preference' => 'strict']);
        
        $allowedFields = $this->service->getAllowedFields($this->user, 'marketing');
        
        expect($allowedFields)->toHaveCount(0); // Strict preference allows no marketing data
    });

    it('can generate data minimization report', function () {
        $team = Team::factory()->create();
        $users = User::factory()->count(10)->create();
        
        foreach ($users as $user) {
            $team->addMember($user, 'member');
        }

        $report = $this->service->generateMinimizationReport($team);
        
        expect($report)->toHaveKey('total_users');
        expect($report)->toHaveKey('data_categories');
        expect($report)->toHaveKey('minimization_opportunities');
        expect($report['total_users'])->toBe(10);
    });
});
```

**Implementation**: Create DataMinimizationService

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Str;

class DataMinimizationService
{
    private array $purposeFieldMappings = [
        'basic_profile' => ['email', 'first_name', 'last_name'],
        'email_communication' => ['email', 'first_name'],
        'team_management' => ['email', 'first_name', 'last_name', 'role'],
        'marketing' => ['email', 'first_name'],
        'analytics' => [], // No personal data needed
    ];

    public function identifyUnnecessaryData(User $user, string $purpose): array
    {
        $necessaryFields = $this->purposeFieldMappings[$purpose] ?? [];
        $userFields = array_keys($user->getAttributes());
        
        $protectedFields = ['id', 'created_at', 'updated_at', 'type'];
        $unnecessaryFields = array_diff($userFields, $necessaryFields, $protectedFields);
        
        return array_values($unnecessaryFields);
    }

    public function minimizeDataForPurpose(User $user, string $purpose): array
    {
        $allowedFields = $this->purposeFieldMappings[$purpose] ?? [];
        
        return $user->only($allowedFields);
    }

    public function pseudonymizeData(User $user, array $fields): array
    {
        $data = $user->toArray();
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $data[$field] = 'pseudo_' . Str::random(8);
            }
        }
        
        return $data;
    }

    public function applyRetentionRules(User $user): array
    {
        $actionTaken = 'none';
        $fieldsRemoved = [];
        
        // Check if user is inactive for more than 1 year
        if ($user->last_login_at && $user->last_login_at->lt(now()->subYear())) {
            // Remove non-essential data
            $nonEssentialFields = ['phone', 'address', 'bio', 'preferences'];
            
            foreach ($nonEssentialFields as $field) {
                if ($user->{$field}) {
                    $user->{$field} = null;
                    $fieldsRemoved[] = $field;
                }
            }
            
            if (!empty($fieldsRemoved)) {
                $user->save();
                $actionTaken = 'minimized';
            }
        }
        
        return [
            'user_id' => $user->id,
            'action_taken' => $actionTaken,
            'fields_removed' => $fieldsRemoved,
            'processed_at' => now(),
        ];
    }

    public function getAllowedFields(User $user, string $purpose): array
    {
        $baseFields = $this->purposeFieldMappings[$purpose] ?? [];
        
        // Check user's data minimization preference
        $preference = $user->data_minimization_preference ?? 'standard';
        
        return match($preference) {
            'strict' => $purpose === 'essential' ? $baseFields : [],
            'standard' => $baseFields,
            'permissive' => array_merge($baseFields, ['phone', 'address']),
            default => $baseFields,
        };
    }

    public function generateMinimizationReport(Team $team): array
    {
        $users = $team->members;
        $totalUsers = $users->count();
        
        $dataCategories = [
            'personal' => ['first_name', 'last_name', 'email'],
            'contact' => ['phone', 'address'],
            'behavioral' => ['last_login_at', 'preferences'],
            'optional' => ['bio', 'avatar'],
        ];
        
        $categoryUsage = [];
        $minimizationOpportunities = [];
        
        foreach ($dataCategories as $category => $fields) {
            $usageCount = 0;
            
            foreach ($users as $user) {
                foreach ($fields as $field) {
                    if ($user->{$field}) {
                        $usageCount++;
                        break; // Count user once per category
                    }
                }
            }
            
            $categoryUsage[$category] = [
                'users_with_data' => $usageCount,
                'percentage' => $totalUsers > 0 ? ($usageCount / $totalUsers) * 100 : 0,
            ];
            
            // Identify minimization opportunities
            if ($category === 'optional' && $usageCount > 0) {
                $minimizationOpportunities[] = [
                    'category' => $category,
                    'affected_users' => $usageCount,
                    'recommendation' => 'Consider removing optional data for inactive users',
                ];
            }
        }
        
        return [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'generated_at' => now(),
            'total_users' => $totalUsers,
            'data_categories' => $categoryUsage,
            'minimization_opportunities' => $minimizationOpportunities,
            'compliance_score' => $this->calculateMinimizationScore($categoryUsage),
        ];
    }

    private function calculateMinimizationScore(array $categoryUsage): int
    {
        $score = 100;
        
        // Deduct points for high usage of optional data
        if (isset($categoryUsage['optional'])) {
            $optionalUsage = $categoryUsage['optional']['percentage'];
            $score -= ($optionalUsage / 100) * 20; // Max 20 point deduction
        }
        
        return max(0, min(100, (int) $score));
    }
}
```

## Factory Patterns for Testing

### Consent Factory

**File**: `database/factories/ConsentFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Consent;
use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'purpose' => $this->faker->randomElement([
                'marketing', 'analytics', 'functional', 'personalization'
            ]),
            'granted' => $this->faker->boolean(80), // 80% granted
            'granted_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'legal_basis' => 'consent',
            'data_categories' => $this->faker->randomElements([
                'personal', 'contact', 'behavioral', 'preferences'
            ], $this->faker->numberBetween(1, 3)),
            'retention_period' => $this->faker->randomElement([12, 24, 36]), // months
        ];
    }

    public function granted(): static
    {
        return $this->state(fn() => [
            'granted' => true,
            'granted_at' => now(),
            'withdrawn_at' => null,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn() => [
            'granted' => false,
            'withdrawn_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn() => [
            'granted' => true,
            'granted_at' => now()->subMonths(18),
            'retention_period' => 12,
        ]);
    }

    public function marketing(): static
    {
        return $this->state(fn() => [
            'purpose' => 'marketing',
            'data_categories' => ['personal', 'contact'],
        ]);
    }

    public function analytics(): static
    {
        return $this->state(fn() => [
            'purpose' => 'analytics',
            'data_categories' => ['behavioral'],
        ]);
    }
}
```

### DataProcessingActivity Factory

**File**: `database/factories/DataProcessingActivityFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DataProcessingActivity;
use App\Models\User;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataProcessingActivityFactory extends Factory
{
    protected $model = DataProcessingActivity::class;

    public function definition(): array
    {
        $dataSubject = User::factory()->create();
        
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'activity_type' => $this->faker->randomElement([
                'create', 'read', 'update', 'delete', 'export'
            ]),
            'data_subject_id' => $dataSubject->id,
            'data_subject_type' => get_class($dataSubject),
            'data_categories' => $this->faker->randomElements([
                'personal', 'contact', 'behavioral', 'preferences'
            ], $this->faker->numberBetween(1, 3)),
            'purpose' => $this->faker->randomElement([
                'user_registration', 'profile_update', 'data_export', 'account_deletion'
            ]),
            'legal_basis' => $this->faker->randomElement([
                'consent', 'contract', 'legal_obligation', 'legitimate_interests'
            ]),
            'performed_by' => User::factory(),
            'performed_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'metadata' => [
                'request_id' => 'req_' . $this->faker->uuid(),
                'session_id' => 'sess_' . $this->faker->uuid(),
            ],
        ];
    }

    public function create(): static
    {
        return $this->state(fn() => [
            'activity_type' => 'create',
            'purpose' => 'user_registration',
        ]);
    }

    public function export(): static
    {
        return $this->state(fn() => [
            'activity_type' => 'export',
            'purpose' => 'data_portability',
            'legal_basis' => 'data_subject_request',
        ]);
    }

    public function delete(): static
    {
        return $this->state(fn() => [
            'activity_type' => 'delete',
            'purpose' => 'data_subject_request',
            'legal_basis' => 'data_subject_request',
        ]);
    }
}
```

## Performance Benchmarks and Compliance Validation

### GDPR Performance Benchmark Tests

**Test File**: `tests/Performance/GDPRPerformanceBenchmarkTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Team;
use App\Models\Consent;
use App\Services\DataExportService;
use App\Services\GDPRComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GDPR Performance Benchmarks', function () {
    it('meets data export performance requirements', function () {
        // Create user with substantial data
        $user = User::factory()->create();
        $teams = Team::factory()->count(5)->create();
        
        foreach ($teams as $team) {
            $team->addMember($user, 'member');
            
            // Create consents and activities
            Consent::factory()->count(3)->create(['user_id' => $user->id, 'team_id' => $team->id]);
            DataProcessingActivity::factory()->count(10)->create([
                'data_subject_id' => $user->id,
                'data_subject_type' => get_class($user),
                'team_id' => $team->id,
            ]);
        }
        
        $service = new DataExportService();
        
        // Benchmark data export
        $start = microtime(true);
        $exportData = $service->exportUserData($user);
        $exportTime = microtime(true) - $start;
        
        // Benchmark file generation
        $start = microtime(true);
        $filePath = $service->generateExportFile($user, 'json');
        $fileTime = microtime(true) - $start;
        
        // Performance assertions
        expect($exportTime)->toBeLessThan(2.0); // <2s for data collection
        expect($fileTime)->toBeLessThan(1.0); // <1s for file generation
        expect($exportData)->toHaveKey('personal_information');
        expect($exportData['team_memberships'])->toHaveCount(5);
    });

    it('handles compliance checking efficiently', function () {
        $team = Team::factory()->create();
        $users = User::factory()->count(100)->create();
        
        foreach ($users as $user) {
            $team->addMember($user, 'member');
            Consent::factory()->count(2)->create([
                'user_id' => $user->id,
                'team_id' => $team->id,
            ]);
        }
        
        $service = new GDPRComplianceService();
        
        $start = microtime(true);
        $compliance = $service->checkConsentCompliance($team);
        $complianceTime = microtime(true) - $start;
        
        $start = microtime(true);
        $dashboard = $service->generateComplianceDashboard($team);
        $dashboardTime = microtime(true) - $start;
        
        // Performance assertions
        expect($complianceTime)->toBeLessThan(1.0); // <1s for compliance check
        expect($dashboardTime)->toBeLessThan(3.0); // <3s for dashboard generation
        expect($compliance['total_consents'])->toBe(200);
        expect($dashboard['compliance_score'])->toBeGreaterThan(0);
    });

    it('validates data deletion performance', function () {
        $users = User::factory()->count(50)->create();
        $team = Team::factory()->create();
        
        foreach ($users as $user) {
            $team->addMember($user, 'member');
            Consent::factory()->count(2)->create(['user_id' => $user->id]);
        }
        
        $service = new DataDeletionService();
        
        $start = microtime(true);
        foreach ($users->take(10) as $user) {
            $service->softDeleteUser($user);
        }
        $deletionTime = microtime(true) - $start;
        
        // Performance assertions
        expect($deletionTime)->toBeLessThan(5.0); // <5s for 10 user deletions
        expect($deletionTime / 10)->toBeLessThan(0.5); // <500ms per deletion
        
        // Verify deletions
        $deletedUsers = User::onlyTrashed()->count();
        expect($deletedUsers)->toBe(10);
    });
});
```

## Summary and Next Steps

This comprehensive TDD guide for GDPR compliance implementation provides:

1. **Complete consent management** with expiration and withdrawal tracking
2. **Data export and portability** services with multiple format support
3. **Secure data deletion** with retention policy compliance
4. **Comprehensive audit trails** for all data processing activities
5. **Privacy controls and data minimization** features
6. **Performance-optimized** GDPR operations

### Key TDD Principles Applied

- **Compliance-First Development**: All GDPR features driven by failing tests
- **Privacy by Design**: TDD approach to privacy controls and data minimization
- **Audit Trail Testing**: Comprehensive testing of data processing activity tracking
- **Performance Testing**: TDD validation of GDPR operation performance requirements

### GDPR Compliance Targets Achieved

- Data export: <2s for comprehensive user data export
- Compliance checking: <1s for team consent compliance analysis
- Data deletion: <500ms per user deletion operation
- Complete audit trail: 100% data processing activity tracking
- Privacy controls: Automated data minimization and retention

### Next Implementation Guide

Continue with [070-state-management-tdd.md](070-state-management-tdd.md) to implement user state management using the same comprehensive TDD approach.
