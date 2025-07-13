---
owner: "[COMPLIANCE_OFFICER]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft"
version: "1.0.0"
target_audience: "Junior developers with 6 months-2 years experience"
compliance_framework: "GDPR"
data_retention_period: "2 years"
---

# GDPR Compliance Implementation Guide
## [PROJECT_NAME]

**Estimated Reading Time:** 30 minutes

## Executive Summary

This guide provides comprehensive implementation details for GDPR (General Data Protection Regulation) compliance in [PROJECT_NAME]. It covers data protection by design, user rights implementation, consent management, and technical safeguards using Laravel 12.x and FilamentPHP v4.

### GDPR Compliance Checklist
- [ ] **Lawful Basis**: Documented lawful basis for data processing
- [ ] **Data Subject Rights**: All 8 rights implemented and tested
- [ ] **Consent Management**: Granular consent tracking and withdrawal
- [ ] **Data Retention**: 2-year retention policy with automated cleanup
- [ ] **Privacy by Design**: Built-in privacy protections
- [ ] **Data Protection Impact Assessment**: Completed and approved
- [ ] **Breach Notification**: 72-hour notification procedures
- [ ] **Data Transfer**: Cross-border transfer safeguards

## Legal Framework Overview

### GDPR Principles
1. **Lawfulness, Fairness, Transparency**: Clear communication about data processing
2. **Purpose Limitation**: Data used only for specified purposes
3. **Data Minimization**: Collect only necessary data
4. **Accuracy**: Keep data accurate and up-to-date
5. **Storage Limitation**: Retain data only as long as necessary
6. **Integrity and Confidentiality**: Secure data processing
7. **Accountability**: Demonstrate compliance

### Lawful Basis for Processing
- **Consent**: User has given clear consent (Article 6(1)(a))
- **Contract**: Processing necessary for contract performance (Article 6(1)(b))
- **Legal Obligation**: Required by law (Article 6(1)(c))
- **Legitimate Interest**: Necessary for legitimate interests (Article 6(1)(f))

## Data Subject Rights Implementation

### Right of Access (Article 15)

#### Implementation Overview
Users can request a complete copy of their personal data in a structured, commonly used format.

#### Laravel Implementation
```php
<?php
// app/Services/GdprService.php

class GdprService
{
    public function generateDataExport(User $user): array
    {
        return [
            'personal_data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString(),
            ],
            'profile_data' => $user->profile?->toArray(),
            'activity_log' => $user->activities()->get()->toArray(),
            'consent_records' => $user->consents()->get()->toArray(),
            'generated_at' => now()->toISOString(),
            'retention_period' => '2 years from last activity',
        ];
    }
}
```

#### FilamentPHP Admin Interface
```php
<?php
// app/Filament/Resources/UserResource/Pages/DataExport.php

class DataExport extends Page
{
    protected static string $resource = UserResource::class;
    protected static string $view = 'filament.resources.user-resource.pages.data-export';

    public function generateExport()
    {
        $user = $this->record;
        $gdprService = app(GdprService::class);
        
        $exportData = $gdprService->generateDataExport($user);
        
        // Log the export request
        activity()
            ->performedOn($user)
            ->withProperties(['export_type' => 'gdpr_data_export'])
            ->log('GDPR data export generated');
        
        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT);
        }, "user-data-export-{$user->id}.json");
    }
}
```

### Right to Rectification (Article 16)

#### Implementation Overview
Users can request correction of inaccurate personal data.

#### Laravel Implementation
```php
<?php
// app/Http/Controllers/GdprController.php

class GdprController extends Controller
{
    public function requestRectification(RectificationRequest $request)
    {
        $user = auth()->user();
        
        // Create rectification request
        $rectificationRequest = GdprRectificationRequest::create([
            'user_id' => $user->id,
            'field' => $request->field,
            'current_value' => $user->{$request->field},
            'requested_value' => $request->new_value,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);
        
        // Notify compliance team
        Notification::route('mail', config('gdpr.compliance_email'))
            ->notify(new RectificationRequestNotification($rectificationRequest));
        
        return response()->json([
            'message' => 'Rectification request submitted successfully',
            'request_id' => $rectificationRequest->id,
            'estimated_completion' => now()->addDays(30)->toDateString(),
        ]);
    }
}
```

### Right to Erasure (Article 17)

#### Implementation Overview
Users can request deletion of their personal data ("right to be forgotten").

#### Laravel Implementation
```php
<?php
// app/Services/DataErasureService.php

class DataErasureService
{
    public function processErasureRequest(User $user, string $reason = null): void
    {
        DB::transaction(function () use ($user, $reason) {
            // Log the erasure request
            activity()
                ->performedOn($user)
                ->withProperties([
                    'reason' => $reason,
                    'erasure_type' => 'user_requested'
                ])
                ->log('GDPR erasure request processed');
            
            // Anonymize instead of hard delete for audit trail
            $this->anonymizeUserData($user);
            
            // Soft delete the user
            $user->delete();
            
            // Update related records
            $this->handleRelatedDataErasure($user);
        });
    }
    
    private function anonymizeUserData(User $user): void
    {
        $user->update([
            'name' => 'Anonymized User',
            'email' => 'anonymized_' . $user->id . '@deleted.local',
            'email_verified_at' => null,
            'password' => Hash::make(Str::random(32)),
            'remember_token' => null,
            'anonymized_at' => now(),
        ]);
    }
}
```

### Right to Data Portability (Article 20)

#### Implementation Overview
Users can receive their data in a structured, machine-readable format and transfer it to another controller.

#### Laravel Implementation
```php
<?php
// app/Services/DataPortabilityService.php

class DataPortabilityService
{
    public function generatePortableData(User $user): array
    {
        return [
            'format' => 'JSON',
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'user_data' => [
                'profile' => $this->getPortableProfile($user),
                'preferences' => $this->getPortablePreferences($user),
                'content' => $this->getPortableContent($user),
            ],
            'metadata' => [
                'total_records' => $this->countUserRecords($user),
                'data_sources' => ['user_profile', 'user_preferences', 'user_content'],
                'retention_info' => '2 years from last activity',
            ]
        ];
    }
    
    private function getPortableProfile(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->toISOString(),
            'preferences' => $user->preferences,
        ];
    }
}
```

## Consent Management System

### Consent Model Implementation

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
        'consent_method',
        'legal_basis',
    ];

    protected $casts = [
        'given_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'consent_type' => ConsentType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->given_at && !$this->withdrawn_at;
    }

    public function withdraw(): void
    {
        $this->update([
            'withdrawn_at' => now(),
            'updated_by' => auth()->id(),
        ]);
    }
}
```

### Consent Types Enum

```php
<?php
// app/Enums/ConsentType.php

enum ConsentType: string
{
    case Essential = 'essential';
    case Analytics = 'analytics';
    case Marketing = 'marketing';
    case Personalization = 'personalization';
    case ThirdParty = 'third_party';

    public function getLabel(): string
    {
        return match($this) {
            self::Essential => 'Essential Cookies',
            self::Analytics => 'Analytics and Performance',
            self::Marketing => 'Marketing and Advertising',
            self::Personalization => 'Personalization',
            self::ThirdParty => 'Third-party Services',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::Essential => 'Required for basic site functionality',
            self::Analytics => 'Help us understand how you use our site',
            self::Marketing => 'Used to show relevant advertisements',
            self::Personalization => 'Customize your experience',
            self::ThirdParty => 'External services integration',
        };
    }

    public function isRequired(): bool
    {
        return $this === self::Essential;
    }
}
```

### Consent Management Controller

```php
<?php
// app/Http/Controllers/ConsentController.php

class ConsentController extends Controller
{
    public function updateConsent(ConsentRequest $request)
    {
        $user = auth()->user();
        
        DB::transaction(function () use ($request, $user) {
            foreach ($request->consents as $consentType => $given) {
                $consent = UserConsent::firstOrNew([
                    'user_id' => $user->id,
                    'consent_type' => $consentType,
                ]);
                
                if ($given && !$consent->isActive()) {
                    // Grant consent
                    $consent->fill([
                        'given_at' => now(),
                        'withdrawn_at' => null,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'consent_method' => 'web_form',
                        'legal_basis' => 'consent',
                    ])->save();
                } elseif (!$given && $consent->isActive()) {
                    // Withdraw consent
                    $consent->withdraw();
                }
            }
        });
        
        return response()->json(['message' => 'Consent preferences updated']);
    }
}
```

## Data Retention and Cleanup

### Retention Policy Implementation

```php
<?php
// app/Console/Commands/GdprDataCleanup.php

class GdprDataCleanup extends Command
{
    protected $signature = 'gdpr:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up data according to GDPR retention policies';

    public function handle(): int
    {
        $this->info('Starting GDPR data cleanup...');
        
        $retentionPeriod = config('gdpr.retention_period', 2); // years
        $cutoffDate = now()->subYears($retentionPeriod);
        
        // Find users to anonymize
        $usersToAnonymize = User::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->whereNull('anonymized_at')
            ->get();
        
        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Email', 'Deleted At', 'Days Since Deletion'],
                $usersToAnonymize->map(function ($user) {
                    return [
                        $user->id,
                        $user->email,
                        $user->deleted_at->format('Y-m-d'),
                        $user->deleted_at->diffInDays(now()),
                    ];
                })
            );
            
            $this->info("Would anonymize {$usersToAnonymize->count()} users");
            return Command::SUCCESS;
        }
        
        $anonymizedCount = 0;
        foreach ($usersToAnonymize as $user) {
            $this->anonymizeUser($user);
            $anonymizedCount++;
        }
        
        $this->info("Anonymized {$anonymizedCount} users");
        
        // Clean up old activity logs
        $this->cleanupActivityLogs($cutoffDate);
        
        return Command::SUCCESS;
    }
    
    private function anonymizeUser(User $user): void
    {
        $user->update([
            'name' => 'Anonymized User',
            'email' => 'anonymized_' . $user->id . '@deleted.local',
            'anonymized_at' => now(),
        ]);
        
        // Remove personal data from related models
        $user->profile?->update([
            'phone' => null,
            'address' => null,
            'date_of_birth' => null,
        ]);
    }
}
```

### Automated Retention Scheduling

```php
<?php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Run GDPR cleanup daily at 2 AM
    $schedule->command('gdpr:cleanup')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->onOneServer();
    
    // Generate retention reports weekly
    $schedule->command('gdpr:retention-report')
        ->weeklyOn(1, '09:00'); // Monday at 9 AM
}
```

## Privacy by Design Implementation

### Data Minimization

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
    
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered. You can sign in or reset your password.',
        ];
    }
}
```

### Encryption at Rest

```php
<?php
// app/Models/User.php

class User extends Authenticatable
{
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        // Encrypt sensitive fields
        'phone' => 'encrypted',
        'address' => 'encrypted',
        'date_of_birth' => 'encrypted:date',
    ];
    
    // Automatically encrypt sensitive attributes
    protected $encrypted = [
        'phone',
        'address',
        'date_of_birth',
    ];
}
```

## Breach Notification System

### Breach Detection and Notification

```php
<?php
// app/Services/BreachNotificationService.php

class BreachNotificationService
{
    public function reportBreach(array $breachData): void
    {
        $breach = DataBreach::create([
            'incident_id' => Str::uuid(),
            'detected_at' => now(),
            'breach_type' => $breachData['type'],
            'affected_records' => $breachData['affected_records'],
            'description' => $breachData['description'],
            'severity' => $this->calculateSeverity($breachData),
            'status' => 'detected',
        ]);
        
        // Immediate notification to compliance team
        $this->notifyComplianceTeam($breach);
        
        // Schedule 72-hour notification if required
        if ($this->requiresRegulatoryNotification($breach)) {
            $this->scheduleRegulatoryNotification($breach);
        }
        
        // Notify affected users if required
        if ($this->requiresUserNotification($breach)) {
            $this->scheduleUserNotification($breach);
        }
    }
    
    private function calculateSeverity(array $breachData): string
    {
        $score = 0;
        
        // Scoring based on data sensitivity
        if (in_array('personal_data', $breachData['data_types'])) $score += 3;
        if (in_array('financial_data', $breachData['data_types'])) $score += 5;
        if (in_array('health_data', $breachData['data_types'])) $score += 5;
        
        // Scoring based on number of affected records
        if ($breachData['affected_records'] > 1000) $score += 3;
        if ($breachData['affected_records'] > 10000) $score += 5;
        
        return match(true) {
            $score >= 8 => 'critical',
            $score >= 5 => 'high',
            $score >= 3 => 'medium',
            default => 'low',
        };
    }
}
```

## Compliance Monitoring and Reporting

### GDPR Compliance Dashboard

```php
<?php
// app/Filament/Widgets/GdprComplianceWidget.php

class GdprComplianceWidget extends Widget
{
    protected static string $view = 'filament.widgets.gdpr-compliance';
    
    public function getViewData(): array
    {
        return [
            'active_consents' => UserConsent::where('given_at', '!=', null)
                ->whereNull('withdrawn_at')
                ->count(),
            'pending_erasure_requests' => GdprErasureRequest::where('status', 'pending')->count(),
            'data_exports_this_month' => GdprDataExport::whereMonth('created_at', now()->month)->count(),
            'retention_compliance' => $this->calculateRetentionCompliance(),
            'recent_breaches' => DataBreach::latest()->limit(5)->get(),
        ];
    }
    
    private function calculateRetentionCompliance(): float
    {
        $totalUsers = User::withTrashed()->count();
        $compliantUsers = User::withTrashed()
            ->where(function ($query) {
                $query->whereNull('deleted_at')
                    ->orWhere('deleted_at', '>', now()->subYears(2))
                    ->orWhereNotNull('anonymized_at');
            })
            ->count();
        
        return $totalUsers > 0 ? ($compliantUsers / $totalUsers) * 100 : 100;
    }
}
```

### Automated Compliance Reports

```php
<?php
// app/Console/Commands/GenerateGdprReport.php

class GenerateGdprReport extends Command
{
    protected $signature = 'gdpr:report {--month= : Generate report for specific month}';
    protected $description = 'Generate monthly GDPR compliance report';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $report = [
            'period' => $month,
            'data_subject_requests' => [
                'access_requests' => GdprDataExport::whereBetween('created_at', [$startDate, $endDate])->count(),
                'rectification_requests' => GdprRectificationRequest::whereBetween('created_at', [$startDate, $endDate])->count(),
                'erasure_requests' => GdprErasureRequest::whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
            'consent_metrics' => [
                'new_consents' => UserConsent::whereBetween('given_at', [$startDate, $endDate])->count(),
                'withdrawn_consents' => UserConsent::whereBetween('withdrawn_at', [$startDate, $endDate])->count(),
            ],
            'data_breaches' => DataBreach::whereBetween('detected_at', [$startDate, $endDate])->count(),
            'retention_compliance' => $this->calculateRetentionCompliance(),
        ];
        
        // Save report
        Storage::put("gdpr-reports/gdpr-report-{$month}.json", json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("GDPR compliance report generated for {$month}");
        
        return Command::SUCCESS;
    }
}
```

---

**GDPR Compliance Guide Version**: 1.0.0  
**Compliance Framework**: GDPR (EU Regulation 2016/679)  
**Data Retention Period**: 2 years  
**Created**: [YYYY-MM-DD]  
**Last Updated**: [YYYY-MM-DD]  
**Next Review**: [YYYY-MM-DD]  
**Compliance Owner**: [COMPLIANCE_OFFICER]
