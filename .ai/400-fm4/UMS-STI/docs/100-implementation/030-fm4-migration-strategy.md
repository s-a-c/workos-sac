# 10.3 FM4 to UMS-STI Migration Strategy

## 10.3.1 Executive Summary

This document provides a comprehensive strategy for migrating the existing FM4 project to align with the UMS-STI (User Management System with Single Table Inheritance) architecture. The migration follows a phased approach using Test-Driven Development principles to ensure system reliability, data integrity, and minimal downtime.

## 10.3.2 Current State Analysis

### 10.3.2.1 FM4 Project Assessment

**Current Architecture:**
- Laravel 12.x application
- Traditional user management without STI
- Basic team structure without hierarchy
- Limited permission system
- No GDPR compliance features
- Standard Laravel authentication

**Key Challenges:**
- User data scattered across multiple tables
- No team hierarchy or closure table structure
- Permission system lacks team isolation
- Missing GDPR compliance mechanisms
- No comprehensive audit trails
- Performance optimization needed for scale

### 10.3.2.2 UMS-STI Target Architecture

**Target Features:**
- Single Table Inheritance for user types
- Closure table team hierarchy
- Team-isolated permission system
- Full GDPR compliance
- Comprehensive audit trails
- Performance-optimized caching
- Event-driven state management

## 10.3.3 Migration Strategy Overview

### 10.3.3.1 Phase-Based Approach

The migration follows a 10-week phased approach that minimizes risk and ensures continuous operation:

```
Phase 1: Foundation (Weeks 1-2)
├── TDD Environment Setup
├── Database Analysis & Planning
└── STI Model Preparation

Phase 2: Core Migration (Weeks 3-4)
├── User Model STI Conversion
├── Team Hierarchy Implementation
└── Permission System Migration

Phase 3: Compliance & Security (Weeks 5-6)
├── GDPR Compliance Implementation
├── Audit Trail System
└── Security Hardening

Phase 4: Performance & Features (Weeks 7-8)
├── Caching Implementation
├── API Development
└── Admin Interface Migration

Phase 5: Testing & Deployment (Weeks 9-10)
├── Integration Testing
├── Performance Validation
└── Production Deployment
```

## 10.3.4 Detailed Migration Plan

### 10.3.4.1 Phase 1: Foundation Setup (Weeks 1-2)

#### Week 1: Environment and Analysis

**Day 1-2: TDD Environment Setup**
```bash
# Follow 010-tdd-environment-setup.md
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
# Configure testing environment
```

**Day 3-4: Current Data Analysis**
```sql
-- Analyze existing user data structure
SELECT 
    COUNT(*) as total_users,
    COUNT(DISTINCT role) as unique_roles,
    COUNT(DISTINCT team_id) as teams_count
FROM users;

-- Identify data migration requirements
SELECT 
    table_name,
    column_name,
    data_type
FROM information_schema.columns 
WHERE table_schema = 'fm4_database'
AND table_name IN ('users', 'teams', 'permissions');
```

**Day 5: Migration Planning**
- Create data mapping documentation
- Identify breaking changes
- Plan rollback strategies

#### Week 2: Database Preparation

**Day 1-3: STI Migration Preparation**
```php
// Create migration for STI conversion
php artisan make:migration convert_users_to_sti

// Migration content following 030-sti-models-tdd.md
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('type')->default('employee');
        $table->json('type_specific_data')->nullable();
        // Add other STI-required fields
    });
}
```

**Day 4-5: Test Data Preparation**
```php
// Create comprehensive test data
// Following TDD principles from guides
User::factory()->count(100)->create();
Team::factory()->count(20)->create();
// Establish baseline for migration testing
```

### 10.3.4.2 Phase 2: Core Migration (Weeks 3-4)

#### Week 3: User Model STI Conversion

**Day 1-2: STI Implementation**
```php
// Following 030-sti-models-tdd.md
// Implement base User model with STI
class User extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'email', 'first_name', 'last_name', 'type', 'type_specific_data'
    ];

    protected $casts = [
        'type_specific_data' => 'array',
    ];
}

// Create specific user types
class Employee extends User
{
    protected $table = 'users';

    public function newQuery()
    {
        return parent::newQuery()->where('type', 'employee');
    }
}
```

**Day 3-4: Data Migration Script**
```php
// Create migration command
php artisan make:command MigrateUsersToSTI

// Migration logic with comprehensive testing
public function handle()
{
    DB::transaction(function () {
        $users = DB::table('users')->get();

        foreach ($users as $user) {
            $userType = $this->determineUserType($user);
            $typeSpecificData = $this->extractTypeSpecificData($user);

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'type' => $userType,
                    'type_specific_data' => json_encode($typeSpecificData),
                ]);
        }
    });
}
```

**Day 5: Validation and Testing**
```php
// Comprehensive validation tests
test('user migration preserves all data', function () {
    // Test data integrity after migration
    $originalCount = User::count();

    Artisan::call('migrate:users-to-sti');

    expect(User::count())->toBe($originalCount);
    expect(Employee::count())->toBeGreaterThan(0);
    expect(Manager::count())->toBeGreaterThan(0);
});
```

#### Week 4: Team Hierarchy Migration

**Day 1-3: Closure Table Implementation**
```php
// Following 040-closure-table-tdd.md
// Create team hierarchy migrations
Schema::create('team_closures', function (Blueprint $table) {
    $table->unsignedBigInteger('ancestor_id');
    $table->unsignedBigInteger('descendant_id');
    $table->unsignedInteger('depth');

    $table->foreign('ancestor_id')->references('id')->on('teams');
    $table->foreign('descendant_id')->references('id')->on('teams');

    $table->primary(['ancestor_id', 'descendant_id']);
});

// Migrate existing team relationships
public function migrateTeamHierarchy()
{
    $teams = Team::all();

    foreach ($teams as $team) {
        // Create self-reference
        DB::table('team_closures')->insert([
            'ancestor_id' => $team->id,
            'descendant_id' => $team->id,
            'depth' => 0,
        ]);

        // Migrate parent-child relationships
        if ($team->parent_id) {
            $this->createHierarchyPath($team->parent_id, $team->id);
        }
    }
}
```

**Day 4-5: Team Membership Migration**
```php
// Migrate team memberships to new structure
Schema::create('team_memberships', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->string('role')->default('member');
    $table->boolean('is_active')->default(true);
    $table->timestamp('joined_at')->useCurrent();
    $table->timestamp('left_at')->nullable();
    $table->timestamps();
});

// Migration script
public function migrateTeamMemberships()
{
    $memberships = DB::table('team_user')->get();

    foreach ($memberships as $membership) {
        TeamMembership::create([
            'team_id' => $membership->team_id,
            'user_id' => $membership->user_id,
            'role' => $membership->role ?? 'member',
            'joined_at' => $membership->created_at ?? now(),
        ]);
    }
}
```

### 10.3.4.3 Phase 3: Compliance & Security (Weeks 5-6)

#### Week 5: Permission System Migration

**Day 1-3: Permission System Overhaul**
```php
// Following 050-permission-system-tdd.md
// Create new permission structure
Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('guard_name')->default('web');
    $table->text('description')->nullable();
    $table->string('category')->nullable();
    $table->boolean('is_system')->default(false);
    $table->timestamps();
});

// Migrate existing permissions
public function migratePermissions()
{
    $oldPermissions = DB::table('old_permissions')->get();

    foreach ($oldPermissions as $oldPerm) {
        Permission::create([
            'name' => $oldPerm->name,
            'description' => $oldPerm->description,
            'category' => $this->categorizePermission($oldPerm->name),
        ]);
    }
}
```

**Day 4-5: Team-Scoped Permissions**
```php
// Implement team isolation
public function migrateUserPermissions()
{
    $userPermissions = DB::table('user_permissions')->get();

    foreach ($userPermissions as $userPerm) {
        $user = User::find($userPerm->user_id);
        $permission = Permission::find($userPerm->permission_id);
        $team = $this->determineUserTeam($user);

        $user->givePermissionTo($permission, $team);
    }
}
```

#### Week 6: GDPR Compliance Implementation

**Day 1-3: Consent Management**
```php
// Following 060-gdpr-compliance-tdd.md
// Create consent tracking
Schema::create('consents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('team_id')->nullable()->constrained();
    $table->string('purpose');
    $table->boolean('granted')->default(false);
    $table->timestamp('granted_at')->nullable();
    $table->timestamp('withdrawn_at')->nullable();
    $table->timestamps();
});

// Create default consents for existing users
public function createDefaultConsents()
{
    $users = User::all();

    foreach ($users as $user) {
        Consent::create([
            'user_id' => $user->id,
            'purpose' => 'functional',
            'granted' => true,
            'granted_at' => $user->created_at,
        ]);
    }
}
```

**Day 4-5: Audit Trail Implementation**
```php
// Implement comprehensive audit trails
Schema::create('data_processing_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('activity_type');
    $table->unsignedBigInteger('data_subject_id');
    $table->string('data_subject_type');
    $table->json('data_categories');
    $table->string('purpose');
    $table->string('legal_basis');
    $table->timestamp('performed_at');
    $table->json('metadata')->nullable();
});

// Retroactive audit trail creation
public function createHistoricalAuditTrail()
{
    // Create audit records for existing data
    $users = User::all();

    foreach ($users as $user) {
        DataProcessingActivity::create([
            'activity_type' => 'create',
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
            'data_categories' => ['personal'],
            'purpose' => 'user_registration',
            'legal_basis' => 'contract',
            'performed_at' => $user->created_at,
        ]);
    }
}
```

### 10.3.4.4 Phase 4: Performance & Features (Weeks 7-8)

#### Week 7: Performance Optimization

**Day 1-3: Caching Implementation**
```php
// Implement comprehensive caching
// Following performance optimization guides

// User permission caching
public function getCachedPermissions(?Team $team = null): Collection
{
    $cacheKey = $team 
        ? "user_permissions_{$this->id}_{$team->id}"
        : "user_permissions_{$this->id}_global";

    return Cache::remember(
        $cacheKey,
        now()->addMinutes(30),
        fn() => $this->getAllPermissions($team)
    );
}

// Team hierarchy caching
public function getCachedDescendants(): Collection
{
    return Cache::remember(
        "team_descendants_{$this->id}",
        now()->addHours(24),
        fn() => $this->descendants()->get()
    );
}
```

**Day 4-5: Database Optimization**
```sql
-- Add strategic indexes
CREATE INDEX idx_users_type_active ON users(type, deleted_at);
CREATE INDEX idx_team_closures_ancestor_depth ON team_closures(ancestor_id, depth);
CREATE INDEX idx_permissions_category_name ON permissions(category, name);
CREATE INDEX idx_consents_user_purpose ON consents(user_id, purpose, granted);

-- Optimize queries
EXPLAIN SELECT * FROM users WHERE type = 'employee' AND deleted_at IS NULL;
```

#### Week 8: API and Interface Migration

**Day 1-3: API Endpoint Migration**
```php
// Migrate existing API endpoints to new structure
// Following API development guides

Route::middleware(['auth:api', 'team.permission:view-users'])->group(function () {
    Route::get('/teams/{team}/users', [UserController::class, 'index']);
    Route::post('/teams/{team}/users', [UserController::class, 'store']);
    Route::put('/teams/{team}/users/{user}', [UserController::class, 'update']);
});

// Update API responses for STI
public function index(Team $team)
{
    $users = $team->members()->with('type')->get();

    return UserResource::collection($users);
}
```

**Day 4-5: Admin Interface Updates**
```php
// Update admin interface for new structure
// Following Filament admin guides

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('type')
                ->options([
                    'employee' => 'Employee',
                    'manager' => 'Manager',
                    'admin' => 'Admin',
                ])
                ->reactive(),

            // Dynamic fields based on user type
            Group::make()
                ->schema(fn (Get $get) => match ($get('type')) {
                    'employee' => [
                        TextInput::make('employee_id'),
                        Select::make('department'),
                    ],
                    'manager' => [
                        TextInput::make('manager_level'),
                        MultiSelect::make('managed_teams'),
                    ],
                    default => [],
                }),
        ]);
    }
}
```

### 10.3.4.5 Phase 5: Testing & Deployment (Weeks 9-10)

#### Week 9: Comprehensive Testing

**Day 1-3: Integration Testing**
```php
// Comprehensive integration tests
test('complete user workflow with STI', function () {
    $manager = Manager::factory()->create();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create();

    // Test team management
    $team->addMember($manager, 'manager');
    $team->addMember($employee, 'developer');

    // Test permission system
    $manager->givePermissionTo('manage-team', $team);
    expect($manager->hasPermissionTo('manage-team', $team))->toBeTrue();

    // Test GDPR compliance
    $exportData = app(DataExportService::class)->exportUserData($employee);
    expect($exportData)->toHaveKey('personal_information');

    // Test audit trail
    $activities = DataProcessingActivity::forDataSubject($employee)->get();
    expect($activities)->not->toBeEmpty();
});
```

**Day 4-5: Performance Testing**
```php
// Performance validation tests
test('system handles expected load', function () {
    // Create substantial test data
    $teams = Team::factory()->count(50)->create();
    $users = User::factory()->count(1000)->create();

    // Test hierarchy performance
    $start = microtime(true);
    $descendants = $teams->first()->descendants;
    $hierarchyTime = microtime(true) - $start;

    expect($hierarchyTime)->toBeLessThan(0.1); // <100ms

    // Test permission checking performance
    $start = microtime(true);
    $hasPermission = $users->first()->hasPermissionTo('view-team', $teams->first());
    $permissionTime = microtime(true) - $start;

    expect($permissionTime)->toBeLessThan(0.01); // <10ms
});
```

#### Week 10: Production Deployment

**Day 1-2: Pre-deployment Validation**
```bash
# Run comprehensive test suite
./vendor/bin/pest --coverage --min=95

# Performance benchmarking
php artisan benchmark:run --iterations=100

# Security scanning
php artisan security:scan

# GDPR compliance check
php artisan gdpr:validate
```

**Day 3-4: Staged Deployment**
```bash
# Staging deployment
php artisan migrate --env=staging
php artisan migrate:sti --env=staging
php artisan cache:clear

# Validation on staging
php artisan test:integration --env=staging
php artisan benchmark:validate --env=staging

# Production deployment (with rollback plan)
php artisan down --message="Upgrading to UMS-STI"
php artisan migrate --env=production
php artisan migrate:sti --env=production
php artisan cache:clear
php artisan up
```

**Day 5: Post-deployment Monitoring**
```php
// Monitoring and validation
// Set up alerts for:
// - Performance degradation
// - Permission system errors
// - GDPR compliance issues
// - Data integrity problems

// Create monitoring dashboard
class UMSSTIMigrationMonitor
{
    public function validateMigration(): array
    {
        return [
            'user_count_match' => $this->validateUserCounts(),
            'permission_integrity' => $this->validatePermissions(),
            'team_hierarchy_valid' => $this->validateTeamHierarchy(),
            'gdpr_compliance' => $this->validateGDPRCompliance(),
            'performance_targets' => $this->validatePerformance(),
        ];
    }
}
```

## Risk Mitigation Strategies

### Data Backup and Recovery

**Pre-Migration Backup:**
```bash
# Complete database backup
mysqldump fm4_database > fm4_pre_migration_backup.sql

# File system backup
tar -czf fm4_files_backup.tar.gz /path/to/fm4

# Test restoration process
mysql fm4_test < fm4_pre_migration_backup.sql
```

**Rollback Plan:**
```php
// Automated rollback capability
class MigrationRollback
{
    public function rollbackToPreSTI(): void
    {
        DB::transaction(function () {
            // Restore original user structure
            $this->restoreOriginalUsers();

            // Restore team structure
            $this->restoreOriginalTeams();

            // Restore permissions
            $this->restoreOriginalPermissions();

            // Clear new tables
            $this->cleanupSTITables();
        });
    }
}
```

### Gradual Migration Approach

**Feature Flags:**
```php
// Use feature flags for gradual rollout
if (Feature::active('sti-user-model')) {
    return new STIUserService();
} else {
    return new LegacyUserService();
}

// Gradual team migration
if (Feature::active('closure-table-hierarchy')) {
    return $team->getCachedDescendants();
} else {
    return $team->children;
}
```

### Monitoring and Alerting

**Real-time Monitoring:**
```php
// Performance monitoring
class PerformanceMonitor
{
    public function checkCriticalPaths(): void
    {
        $this->monitorUserAuthentication();
        $this->monitorPermissionChecks();
        $this->monitorTeamHierarchyQueries();
        $this->monitorGDPROperations();
    }
}

// Alert thresholds
$alerts = [
    'permission_check_time' => 10, // ms
    'hierarchy_query_time' => 50, // ms
    'user_export_time' => 2000, // ms
    'error_rate' => 0.01, // 1%
];
```

## Success Metrics

### Technical Metrics

**Performance Targets:**
- User authentication: <100ms (95th percentile)
- Permission checks: <10ms with caching
- Team hierarchy queries: <50ms for complex hierarchies
- GDPR data export: <30 seconds for complete user data

**Quality Metrics:**
- Test coverage: >95%
- Code quality: PHPStan level 8
- Security scan: Zero critical vulnerabilities
- GDPR compliance: 100% requirement coverage

### Business Metrics

**User Experience:**
- Zero downtime during migration
- No data loss or corruption
- Improved admin interface usability
- Enhanced security and compliance

**Operational Benefits:**
- Reduced permission management complexity
- Automated GDPR compliance
- Improved audit capabilities
- Better scalability for team growth

## Conclusion

This migration strategy provides a comprehensive, risk-mitigated approach to transforming FM4 into a UMS-STI compliant system. The phased approach ensures minimal disruption while delivering significant improvements in security, compliance, and scalability.

### Key Success Factors

1. **Comprehensive Testing:** Every phase includes extensive testing
2. **Gradual Migration:** Feature flags enable safe, gradual rollout
3. **Performance Focus:** Continuous performance monitoring and optimization
4. **Risk Mitigation:** Multiple backup and rollback strategies
5. **Documentation:** Complete documentation of all changes and procedures

### Next Steps

1. Review and approve migration strategy
2. Set up development and staging environments
3. Begin Phase 1 implementation
4. Establish monitoring and alerting systems
5. Train team on new UMS-STI architecture

The migration to UMS-STI will position FM4 as a modern, compliant, and scalable user management system ready for future growth and regulatory requirements.
