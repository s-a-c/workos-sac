---
owner: "[COMPLIANCE_OFFICER]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft"
version: "1.0.0"
target_audience: "Junior developers with 6 months-2 years experience"
compliance_framework: "GDPR"
retention_period: "2 years"
---

# Data Retention Policy
## [PROJECT_NAME]

**Estimated Reading Time:** 20 minutes

## Executive Summary

This Data Retention Policy defines how [PROJECT_NAME] collects, stores, processes, and deletes personal data in compliance with GDPR and other applicable data protection regulations. The policy establishes a 2-year retention period with automated cleanup procedures.

### Policy Objectives
- **GDPR Compliance**: Ensure full compliance with EU data protection regulations
- **Data Minimization**: Collect and retain only necessary data for specified purposes
- **Automated Management**: Implement automated data lifecycle management
- **User Rights**: Support all data subject rights under GDPR
- **Audit Trail**: Maintain comprehensive audit trails for compliance verification

### Key Principles
- **Purpose Limitation**: Data used only for documented business purposes
- **Storage Limitation**: Data retained only as long as necessary
- **Data Minimization**: Collect minimum data required for functionality
- **Accuracy**: Maintain accurate and up-to-date data
- **Security**: Implement appropriate technical and organizational measures

## Legal Framework

### GDPR Requirements
- **Article 5(1)(e)**: Storage limitation principle
- **Article 17**: Right to erasure ("right to be forgotten")
- **Article 25**: Data protection by design and by default
- **Article 30**: Records of processing activities
- **Article 32**: Security of processing

### Lawful Basis for Processing
- **Consent (Article 6(1)(a))**: User has given clear consent
- **Contract (Article 6(1)(b))**: Processing necessary for contract performance
- **Legal Obligation (Article 6(1)(c))**: Required by law
- **Legitimate Interest (Article 6(1)(f))**: Necessary for legitimate business interests

## Data Categories and Retention Periods

### Personal Data Categories

#### User Account Data
**Data Types**: Name, email address, password hash, account preferences
**Retention Period**: 2 years from last login
**Legal Basis**: Contract performance, legitimate interest
**Deletion Trigger**: Account deletion + 2 years OR 2 years of inactivity

```php
// Laravel implementation
class UserRetentionPolicy
{
    public function shouldRetain(User $user): bool
    {
        $lastActivity = $user->last_login_at ?? $user->updated_at;
        return $lastActivity->gt(now()->subYears(2));
    }
    
    public function getRetentionEndDate(User $user): Carbon
    {
        $lastActivity = $user->last_login_at ?? $user->updated_at;
        return $lastActivity->addYears(2);
    }
}
```

#### Authentication Data
**Data Types**: Login attempts, session data, password reset tokens
**Retention Period**: 90 days
**Legal Basis**: Security and fraud prevention
**Deletion Trigger**: Automated cleanup after 90 days

#### Activity Logs
**Data Types**: User actions, system events, audit trail
**Retention Period**: 2 years
**Legal Basis**: Legitimate interest, legal obligation
**Deletion Trigger**: Automated cleanup after 2 years

#### Consent Records
**Data Types**: Consent timestamps, IP addresses, consent types
**Retention Period**: 3 years (regulatory requirement)
**Legal Basis**: Legal obligation
**Deletion Trigger**: Automated cleanup after 3 years

### Technical Data Categories

#### System Logs
**Data Types**: Error logs, performance logs, security logs
**Retention Period**: 1 year
**Purpose**: System maintenance and security monitoring
**Deletion Trigger**: Log rotation and automated cleanup

#### Backup Data
**Data Types**: Database backups, file backups
**Retention Period**: 30 days for daily backups, 1 year for monthly backups
**Purpose**: Disaster recovery and business continuity
**Deletion Trigger**: Automated backup rotation

## Data Lifecycle Management

### Data Collection Phase

#### Data Minimization Implementation
```php
<?php
// app/Http/Requests/UserRegistrationRequest.php

class UserRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Only collect essential data
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
            
            // Optional data with explicit consent
            'marketing_consent' => ['boolean'],
            'analytics_consent' => ['boolean'],
        ];
    }
    
    protected function prepareForValidation(): void
    {
        // Set default consent values
        $this->merge([
            'marketing_consent' => $this->boolean('marketing_consent'),
            'analytics_consent' => $this->boolean('analytics_consent'),
        ]);
    }
}
```

#### Consent Tracking
```php
<?php
// app/Models/UserConsent.php

class UserConsent extends Model
{
    use HasFactory, Userstamps;

    protected $fillable = [
        'user_id',
        'consent_type',
        'purpose',
        'given_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
        'legal_basis',
        'retention_period',
    ];

    protected $casts = [
        'given_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'consent_type' => ConsentType::class,
    ];

    public function getRetentionEndDate(): Carbon
    {
        return $this->given_at->addYears($this->retention_period ?? 3);
    }

    public function isExpired(): bool
    {
        return now()->gt($this->getRetentionEndDate());
    }
}
```

### Data Processing Phase

#### Purpose Limitation Enforcement
```php
<?php
// app/Services/DataProcessingService.php

class DataProcessingService
{
    public function processUserData(User $user, string $purpose): bool
    {
        // Verify user has given consent for this purpose
        $consent = $user->consents()
            ->where('purpose', $purpose)
            ->where('given_at', '!=', null)
            ->whereNull('withdrawn_at')
            ->first();

        if (!$consent) {
            throw new UnauthorizedDataProcessingException(
                "No valid consent for purpose: {$purpose}"
            );
        }

        // Log the processing activity
        activity()
            ->performedOn($user)
            ->withProperties([
                'purpose' => $purpose,
                'legal_basis' => $consent->legal_basis,
                'consent_id' => $consent->id,
            ])
            ->log('data_processing');

        return true;
    }
}
```

### Data Retention Phase

#### Automated Retention Management
```php
<?php
// app/Console/Commands/DataRetentionCleanup.php

class DataRetentionCleanup extends Command
{
    protected $signature = 'data:retention-cleanup {--dry-run : Show what would be deleted}';
    protected $description = 'Clean up data according to retention policies';

    public function handle(): int
    {
        $this->info('Starting data retention cleanup...');
        
        $stats = [
            'users_anonymized' => $this->cleanupUsers(),
            'logs_deleted' => $this->cleanupLogs(),
            'consents_expired' => $this->cleanupConsents(),
            'backups_rotated' => $this->rotateBackups(),
        ];
        
        $this->table(
            ['Category', 'Records Processed'],
            collect($stats)->map(fn($count, $category) => [$category, $count])
        );
        
        return Command::SUCCESS;
    }
    
    private function cleanupUsers(): int
    {
        $retentionDate = now()->subYears(2);
        
        $usersToAnonymize = User::onlyTrashed()
            ->where('deleted_at', '<', $retentionDate)
            ->whereNull('anonymized_at');
        
        if ($this->option('dry-run')) {
            return $usersToAnonymize->count();
        }
        
        $count = 0;
        $usersToAnonymize->chunk(100, function ($users) use (&$count) {
            foreach ($users as $user) {
                $this->anonymizeUser($user);
                $count++;
            }
        });
        
        return $count;
    }
    
    private function anonymizeUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Anonymize personal data
            $user->update([
                'name' => 'Anonymized User',
                'email' => 'anonymized_' . $user->id . '@deleted.local',
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(32)),
                'remember_token' => null,
                'anonymized_at' => now(),
            ]);
            
            // Remove related personal data
            $user->profile?->delete();
            $user->preferences?->delete();
            
            // Keep audit trail but anonymize
            $user->activities()->update([
                'properties' => json_encode(['anonymized' => true]),
            ]);
        });
    }
}
```

### Data Deletion Phase

#### Secure Data Deletion
```php
<?php
// app/Services/SecureDataDeletionService.php

class SecureDataDeletionService
{
    public function secureDelete(Model $model): bool
    {
        DB::transaction(function () use ($model) {
            // Log deletion for audit trail
            activity()
                ->performedOn($model)
                ->withProperties([
                    'deletion_reason' => 'retention_policy',
                    'original_data_hash' => hash('sha256', serialize($model->toArray())),
                ])
                ->log('secure_deletion');
            
            // Perform secure deletion
            $model->forceDelete();
            
            // Overwrite database pages (SQLite specific)
            if (config('database.default') === 'sqlite') {
                DB::statement('PRAGMA secure_delete = ON');
                DB::statement('VACUUM');
            }
        });
        
        return true;
    }
    
    public function secureFileDelete(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Overwrite file multiple times before deletion
        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'r+');
        
        for ($i = 0; $i < 3; $i++) {
            fseek($handle, 0);
            fwrite($handle, str_repeat(chr(rand(0, 255)), $fileSize));
            fflush($handle);
        }
        
        fclose($handle);
        unlink($filePath);
        
        return true;
    }
}
```

## Data Subject Rights Implementation

### Right of Access
```php
<?php
// app/Services/DataSubjectAccessService.php

class DataSubjectAccessService
{
    public function generateDataExport(User $user): array
    {
        return [
            'export_metadata' => [
                'generated_at' => now()->toISOString(),
                'user_id' => $user->id,
                'retention_policy' => '2 years from last activity',
                'legal_basis' => $this->getLegalBasisForUser($user),
            ],
            'personal_data' => [
                'account_information' => $this->getAccountData($user),
                'profile_information' => $this->getProfileData($user),
                'consent_records' => $this->getConsentData($user),
                'activity_history' => $this->getActivityData($user),
            ],
            'retention_information' => [
                'data_categories' => $this->getDataCategories($user),
                'retention_periods' => $this->getRetentionPeriods($user),
                'deletion_schedule' => $this->getDeletionSchedule($user),
            ],
        ];
    }
    
    private function getRetentionPeriods(User $user): array
    {
        return [
            'account_data' => [
                'period' => '2 years',
                'trigger' => 'last login or account deletion',
                'next_review' => $user->last_login_at?->addYears(2)->toDateString(),
            ],
            'activity_logs' => [
                'period' => '2 years',
                'trigger' => 'activity date',
                'automatic_cleanup' => true,
            ],
            'consent_records' => [
                'period' => '3 years',
                'trigger' => 'consent given date',
                'legal_requirement' => true,
            ],
        ];
    }
}
```

### Right to Erasure
```php
<?php
// app/Services/DataErasureService.php

class DataErasureService
{
    public function processErasureRequest(User $user, string $reason = null): void
    {
        // Validate erasure request
        $this->validateErasureRequest($user, $reason);
        
        DB::transaction(function () use ($user, $reason) {
            // Create erasure record for audit
            $erasureRecord = DataErasureRequest::create([
                'user_id' => $user->id,
                'requested_at' => now(),
                'reason' => $reason,
                'status' => 'processing',
                'legal_basis' => 'article_17_gdpr',
            ]);
            
            // Process erasure
            $this->eraseUserData($user);
            
            // Update erasure record
            $erasureRecord->update([
                'completed_at' => now(),
                'status' => 'completed',
            ]);
        });
    }
    
    private function validateErasureRequest(User $user, ?string $reason): void
    {
        // Check if erasure is legally required to be retained
        $legalHolds = $user->legalHolds()
            ->where('status', 'active')
            ->exists();
        
        if ($legalHolds) {
            throw new ErasureNotPermittedException(
                'Data cannot be erased due to legal hold requirements'
            );
        }
        
        // Check if data is required for contract performance
        $activeContracts = $user->contracts()
            ->where('status', 'active')
            ->exists();
        
        if ($activeContracts && $reason !== 'consent_withdrawn') {
            throw new ErasureNotPermittedException(
                'Data required for active contract performance'
            );
        }
    }
}
```

## Automated Compliance Monitoring

### Retention Compliance Dashboard
```php
<?php
// app/Filament/Widgets/RetentionComplianceWidget.php

class RetentionComplianceWidget extends Widget
{
    protected static string $view = 'filament.widgets.retention-compliance';
    
    public function getViewData(): array
    {
        return [
            'compliance_score' => $this->calculateComplianceScore(),
            'users_requiring_action' => $this->getUsersRequiringAction(),
            'upcoming_deletions' => $this->getUpcomingDeletions(),
            'retention_statistics' => $this->getRetentionStatistics(),
        ];
    }
    
    private function calculateComplianceScore(): float
    {
        $totalUsers = User::withTrashed()->count();
        $compliantUsers = User::withTrashed()
            ->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->where('last_login_at', '>', now()->subYears(2))
                    ->orWhere('deleted_at', '>', now()->subYears(2))
                    ->orWhereNotNull('anonymized_at');
            })
            ->count();
        
        return $totalUsers > 0 ? ($compliantUsers / $totalUsers) * 100 : 100;
    }
    
    private function getUsersRequiringAction(): Collection
    {
        return User::onlyTrashed()
            ->where('deleted_at', '<', now()->subYears(2))
            ->whereNull('anonymized_at')
            ->limit(10)
            ->get(['id', 'email', 'deleted_at']);
    }
}
```

### Automated Compliance Reports
```php
<?php
// app/Console/Commands/GenerateRetentionReport.php

class GenerateRetentionReport extends Command
{
    protected $signature = 'retention:report {--month= : Generate report for specific month}';
    protected $description = 'Generate monthly data retention compliance report';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        
        $report = [
            'report_period' => $month,
            'compliance_metrics' => $this->getComplianceMetrics(),
            'retention_actions' => $this->getRetentionActions($month),
            'data_subject_requests' => $this->getDataSubjectRequests($month),
            'recommendations' => $this->getRecommendations(),
        ];
        
        // Save report
        $filename = "retention-report-{$month}.json";
        Storage::put("compliance-reports/{$filename}", json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("Retention compliance report generated: {$filename}");
        
        return Command::SUCCESS;
    }
    
    private function getComplianceMetrics(): array
    {
        return [
            'total_users' => User::withTrashed()->count(),
            'active_users' => User::where('last_login_at', '>', now()->subYears(2))->count(),
            'users_anonymized' => User::whereNotNull('anonymized_at')->count(),
            'compliance_percentage' => $this->calculateCompliancePercentage(),
            'data_categories_managed' => $this->getDataCategoriesCount(),
        ];
    }
}
```

## Implementation Schedule

### Phase 1: Foundation (Weeks 1-2)
- [ ] **Policy Documentation**: Complete data retention policy documentation
- [ ] **Legal Review**: Legal team review and approval of retention policies
- [ ] **Data Mapping**: Complete mapping of all data categories and retention periods
- [ ] **Consent Framework**: Implement consent management system

### Phase 2: Automation (Weeks 3-4)
- [ ] **Retention Commands**: Implement automated retention cleanup commands
- [ ] **Monitoring Dashboard**: Create retention compliance monitoring dashboard
- [ ] **Audit Logging**: Implement comprehensive audit logging for all data operations
- [ ] **Testing**: Comprehensive testing of retention and deletion procedures

### Phase 3: Compliance (Weeks 5-6)
- [ ] **Data Subject Rights**: Implement all GDPR data subject rights
- [ ] **Compliance Reporting**: Automated compliance reporting system
- [ ] **Staff Training**: Train staff on data retention procedures
- [ ] **Documentation**: Complete operational documentation

### Phase 4: Monitoring (Weeks 7-8)
- [ ] **Continuous Monitoring**: Implement continuous compliance monitoring
- [ ] **Alerting System**: Set up alerts for compliance violations
- [ ] **Regular Audits**: Schedule regular compliance audits
- [ ] **Process Optimization**: Optimize retention processes based on initial results

---

**Data Retention Policy Version**: 1.0.0  
**Compliance Framework**: GDPR (EU Regulation 2016/679)  
**Retention Period**: 2 years from last activity  
**Created**: [YYYY-MM-DD]  
**Last Updated**: [YYYY-MM-DD]  
**Next Review**: [YYYY-MM-DD]  
**Policy Owner**: [COMPLIANCE_OFFICER]
