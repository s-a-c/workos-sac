# UMS-STI Test Specification Document

## Document Information
- **Project**: User Management System with Single Table Inheritance (UMS-STI)
- **Document Type**: Test Specification
- **Created**: 2025-06-20
- **Related Documents**: `prd-UMS-STI.md`, `decision-log-UMS-STI.md`
- **Testing Framework**: Pest PHP with Laravel Testing

## Test Traceability Matrix

| PRD Requirement | Test Suite | Test Cases | Priority | Status |
|-----------------|------------|------------|----------|--------|
| REQ-001 | User Registration | UR-001 to UR-003 | High | Pending |
| REQ-002 | Authentication | AUTH-001 to AUTH-005 | High | Pending |
| REQ-003 | User Types | UT-001 to UT-004 | High | Pending |
| REQ-017 | Permission Isolation | PERM-001 to PERM-003 | Critical | Pending |
| REQ-022 | Team Hierarchy | HIER-001 to HIER-004 | High | Pending |
| REQ-036 | Password Security | PWD-001 to PWD-005 | High | Pending |
| REQ-037 | Session Security | SESS-001 to SESS-004 | High | Pending |
| REQ-010 | GDPR Compliance | GDPR-001 to GDPR-006 | Critical | Pending |

## Critical Test Scenarios

### Test Suite: Permission Isolation (REQ-022)
**Priority**: Critical - Core security requirement

#### Test Case: PERM-001 - Parent Team Access Denial
```php
test('user with parent team access cannot access child team')
    ->given(function () {
        $organization = Organization::factory()->create();
        $department = Department::factory()->create(['parent_id' => $organization->id]);
        $user = User::factory()->create();
        
        // Grant user access to organization only
        $organization->addMember($user, 'admin');
    })
    ->when(function ($organization, $department, $user) {
        return $department->userHasAccess($user);
    })
    ->then(function ($hasAccess) {
        expect($hasAccess)->toBeFalse();
    })
    ->and('access attempt is logged with user stamps');
```

#### Test Case: PERM-002 - SystemUser Bypass Validation
```php
test('SystemUser bypasses all permission checks')
    ->given(function () {
        $systemUser = SystemUser::factory()->create();
        $restrictedTeam = Department::factory()->create();
    })
    ->when(function ($restrictedTeam, $systemUser) {
        return $restrictedTeam->userHasAccess($systemUser);
    })
    ->then(function ($hasAccess) {
        expect($hasAccess)->toBeTrue();
    })
    ->and('bypass is logged for audit purposes');
```

### Test Suite: Team Hierarchy Enforcement (REQ-018)
**Priority**: High - Business logic validation

#### Test Case: HIER-001 - System-Wide Depth Limit
```php
test('team creation fails when exceeding system hierarchy depth')
    ->given(function () {
        config(['teams.max_hierarchy_depth' => 3]);
        $level3Team = $this->createTeamHierarchy(3); // Creates 3-level deep hierarchy
    })
    ->when(function ($level3Team) {
        return Squad::create([
            'name' => 'Level 4 Squad',
            'parent_id' => $level3Team->id
        ]);
    })
    ->throws(HierarchyDepthExceededException::class)
    ->and('error message includes current depth and maximum allowed')
    ->and('failed attempt is logged with user stamps');
```

### Test Suite: GDPR Compliance (REQ-010)
**Priority**: Critical - Legal compliance

#### Test Case: GDPR-001 - Complete Data Export
```php
test('user data export includes all personal data within 30 days')
    ->given(function () {
        $user = User::factory()->create();
        $user->teams()->attach(Team::factory()->create());
        $user->update(['last_login' => now()]);
    })
    ->when(function ($user) {
        return $user->exportPersonalData();
    })
    ->then(function ($exportData) {
        expect($exportData)
            ->toHaveKey('personal_information')
            ->toHaveKey('team_memberships')
            ->toHaveKey('activity_log')
            ->toHaveKey('user_stamps')
            ->and($exportData['format'])->toBe('json');
    })
    ->and('export request is completed within 30 days')
    ->and('export activity is logged with user stamps');
```

#### Test Case: GDPR-002 - Right to be Forgotten
```php
test('user deletion removes all personal data while preserving audit trail')
    ->given(function () {
        $user = User::factory()->create();
        $teamId = Team::factory()->create()->id;
        $user->teams()->attach($teamId);
        
        // Create audit trail
        activity()->performedOn($user)->log('user_created');
    })
    ->when(function ($user) {
        return $user->deleteWithGDPRCompliance();
    })
    ->then(function ($user, $teamId) {
        // Personal data should be removed
        expect(User::find($user->id))->toBeNull();
        
        // Audit trail should be anonymized but preserved
        $auditLog = Activity::where('subject_id', $user->id)->first();
        expect($auditLog->getExtraProperty('anonymized'))->toBeTrue();
        
        // Team relationships should be cleaned up
        expect(DB::table('team_user')->where('user_id', $user->id)->count())->toBe(0);
    });
```

### Test Suite: Password Security (REQ-036)
**Priority**: High - Security requirement

#### Test Case: PWD-001 - Password Complexity Validation
```php
test('password must meet complexity requirements')
    ->given([
        'weak_password' => 'password123',
        'no_uppercase' => 'password123!',
        'no_lowercase' => 'PASSWORD123!',
        'no_numbers' => 'Password!',
        'no_symbols' => 'Password123',
        'too_short' => 'Pass123!',
        'valid_password' => 'SecurePassword123!'
    ])
    ->when(function ($passwords) {
        $results = [];
        foreach ($passwords as $type => $password) {
            $results[$type] = Password::validate($password);
        }
        return $results;
    })
    ->then(function ($results) {
        expect($results['weak_password'])->toBeFalse();
        expect($results['no_uppercase'])->toBeFalse();
        expect($results['no_lowercase'])->toBeFalse();
        expect($results['no_numbers'])->toBeFalse();
        expect($results['no_symbols'])->toBeFalse();
        expect($results['too_short'])->toBeFalse();
        expect($results['valid_password'])->toBeTrue();
    });
```

### Test Suite: Session Security (REQ-037)
**Priority**: High - Security requirement

#### Test Case: SESS-001 - Concurrent Session Limit
```php
test('user cannot have more than 3 concurrent sessions')
    ->given(function () {
        $user = User::factory()->create();
        
        // Create 3 active sessions
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user)->post('/login');
        }
    })
    ->when(function ($user) {
        // Attempt 4th session
        return $this->actingAs($user)->post('/login');
    })
    ->then(function ($response) {
        $response->assertStatus(429); // Too Many Requests
    })
    ->and('oldest session is invalidated')
    ->and('session limit exceeded event is logged');
```

## Performance Test Specifications

### Test Suite: Performance Requirements
**Priority**: High - Non-functional requirements

#### Test Case: PERF-001 - Authentication Response Time
```php
test('authentication completes within 100ms under load')
    ->given(function () {
        $users = User::factory()->count(100)->create();
    })
    ->when(function ($users) {
        $startTime = microtime(true);
        
        foreach ($users as $user) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password'
            ]);
        }
        
        return (microtime(true) - $startTime) / count($users) * 1000; // Average in ms
    })
    ->then(function ($averageTime) {
        expect($averageTime)->toBeLessThan(100);
    });
```

#### Test Case: PERF-002 - Permission Check Performance
```php
test('permission checks complete within 10ms with caching')
    ->given(function () {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($user, 'member');
    })
    ->when(function ($user, $team) {
        $startTime = microtime(true);
        
        // Perform 100 permission checks
        for ($i = 0; $i < 100; $i++) {
            $team->userHasAccess($user);
        }
        
        return (microtime(true) - $startTime) / 100 * 1000; // Average in ms
    })
    ->then(function ($averageTime) {
        expect($averageTime)->toBeLessThan(10);
    });
```

## Test Data Requirements

### User Test Data
```php
// Standard test users for different scenarios
$standardUser = User::factory()->create(['type' => 'standard']);
$adminUser = Admin::factory()->create();
$guestUser = Guest::factory()->create();
$systemUser = SystemUser::factory()->create();

// Users in different states
$activeUser = User::factory()->active()->create();
$inactiveUser = User::factory()->inactive()->create();
$suspendedUser = User::factory()->suspended()->create();
$pendingUser = User::factory()->pending()->create();
```

### Team Test Data
```php
// Complete organizational hierarchy
$organization = Organization::factory()->create();
$department = Department::factory()->create(['parent_id' => $organization->id]);
$project = Project::factory()->create(['parent_id' => $department->id]);
$squad = Squad::factory()->create(['parent_id' => $project->id]);

// Teams with custom settings
$teamWithCustomDepth = Team::factory()->create([
    'settings' => ['max_hierarchy_depth' => 5]
]);
$teamWithSelfRegistration = Team::factory()->create([
    'settings' => ['allow_self_registration' => true]
]);
```

## Automated Test Implementation Plan

### Phase 1: Core Functionality (Week 1-2)
- User registration and authentication tests
- Basic team management tests
- Permission isolation validation tests
- User stamps tracking tests

### Phase 2: Security and Compliance (Week 3-4)
- Password security tests
- Session security tests
- GDPR compliance tests
- Audit trail validation tests

### Phase 3: Performance and Integration (Week 5-6)
- Performance benchmark tests
- FilamentPHP integration tests
- API endpoint tests
- End-to-end workflow tests

### Phase 4: Edge Cases and Error Handling (Week 7-8)
- Error handling validation
- Edge case scenarios
- Stress testing
- Security penetration testing

## Test Environment Requirements

### Development Environment
- PHP 8.4+ with Xdebug for coverage
- Laravel 12.x test environment
- SQLite for fast test execution
- Redis for caching tests

### CI/CD Environment
- Automated test execution on PR
- Code coverage reporting (minimum 90%)
- Performance regression detection
- Security vulnerability scanning

### Staging Environment
- Production-like data volumes
- Load testing capabilities
- GDPR compliance validation
- Integration testing with external services

---

**Document Status**: Draft - Requires development team review
**Next Steps**: Implement test cases in priority order
**Coverage Target**: 95% code coverage, 100% requirement coverage
