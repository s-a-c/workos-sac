# Unit Testing Strategies for UMS-STI

## Executive Summary
Unit testing in UMS-STI focuses on testing individual components in isolation, with special emphasis on STI behavior, permission isolation, closure table operations, and GDPR compliance mechanisms. This guide provides comprehensive strategies for testing complex Laravel applications with advanced architectural patterns.

## Learning Objectives
After completing this guide, you will:
- Design effective unit tests for STI models and behaviors
- Test permission isolation without database dependencies
- Create reliable tests for closure table operations
- Implement GDPR compliance testing strategies
- Use Laravel testing tools effectively for complex scenarios

## Prerequisite Knowledge
- Laravel testing fundamentals (PHPUnit, Pest)
- Database testing with factories and seeders
- Mocking and stubbing concepts
- Understanding of UMS-STI architecture from previous tasks
- Basic knowledge of test-driven development (TDD)

## Architectural Overview

### Testing Strategy for UMS-STI Components

```
Unit Testing Layers:
┌─────────────────────────────────────┐
│ Model Tests                         │
│ ├── STI behavior validation         │
│ ├── State transitions               │
│ ├── Relationship integrity          │
│ └── Business logic methods          │
├─────────────────────────────────────┤
│ Service Tests                       │
│ ├── Permission isolation logic      │
│ ├── GDPR compliance operations      │
│ ├── Team hierarchy management       │
│ └── Data retention policies         │
├─────────────────────────────────────┤
│ Policy Tests                        │
│ ├── Authorization logic             │
│ ├── SystemUser bypass behavior      │
│ ├── Team access validation          │
│ └── Permission scoping              │
├─────────────────────────────────────┤
│ Enum Tests                          │
│ ├── User type behaviors             │
│ ├── State transition rules          │
│ ├── Permission level validation     │
│ └── GDPR classification logic       │
└─────────────────────────────────────┘
```

### Test Isolation Principles

1. **No Database Dependencies**: Unit tests should not require database
2. **Mock External Services**: Isolate components from dependencies
3. **Fast Execution**: Tests should run in milliseconds
4. **Deterministic Results**: Same input always produces same output
5. **Single Responsibility**: Each test validates one specific behavior

## Core Concepts Deep Dive

### 1. STI Model Testing Patterns

```php
// Test STI instantiation
$admin = Admin::factory()->make();
$this->assertInstanceOf(Admin::class, $admin);
$this->assertEquals(UserType::Admin, $admin->getTypeAttribute());

// Test type-specific behavior
$this->assertTrue($admin->canAccessAdminPanel());
$this->assertFalse($guest->canAccessAdminPanel());
```

### 2. Permission Isolation Testing

```php
// Mock team membership without database
$user = Mockery::mock(User::class);
$team = Mockery::mock(Team::class);

$team->shouldReceive('members->where->exists')
    ->with('user_id', $user->id)
    ->andReturn(false);

$this->assertFalse($service->validateExplicitAccess($user, $team));
```

### 3. Closure Table Logic Testing

```php
// Test hierarchy calculations without database
$service = new TeamClosureService();
$mockTeam = Mockery::mock(Team::class);

$mockTeam->shouldReceive('getDepth')->andReturn(3);
$this->assertFalse($service->validateDepthLimit($mockTeam, 2));
$this->assertTrue($service->validateDepthLimit($mockTeam, 4));
```

## Implementation Principles & Patterns

### 1. Test Organization Structure

```
tests/Unit/
├── Models/
│   ├── UserStiTest.php
│   ├── TeamHierarchyTest.php
│   └── GdprRequestTest.php
├── Services/
│   ├── PermissionIsolationServiceTest.php
│   ├── DataRetentionServiceTest.php
│   └── TeamClosureServiceTest.php
├── Policies/
│   ├── TeamPermissionPolicyTest.php
│   └── UserPolicyTest.php
├── Enums/
│   ├── UserTypeTest.php
│   ├── UserStateTest.php
│   └── DataClassificationTest.php
└── Traits/
    ├── HasUlidTest.php
    └── UserstampsTest.php
```

### 2. Mocking Strategy

```php
// Service layer mocking
$mockPermissionService = Mockery::mock(PermissionIsolationService::class);
$this->app->instance(PermissionIsolationService::class, $mockPermissionService);

// Model relationship mocking
$user = Mockery::mock(User::class);
$user->shouldReceive('teams')->andReturn(collect([$team1, $team2]));
```

### 3. Test Data Builders

```php
class UserTestBuilder
{
    private array $attributes = [];

    public static function create(): self
    {
        return new self();
    }

    public function asAdmin(): self
    {
        $this->attributes['type'] = 'admin';
        return $this;
    }

    public function withTeams(array $teams): self
    {
        $this->attributes['teams'] = $teams;
        return $this;
    }

    public function build(): User
    {
        return User::factory()->make($this->attributes);
    }
}
```

## Step-by-Step Implementation Guide

### Step 1: Configure Pest for UMS-STI (Laravel 12.x)

Update `tests/Pest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Laravel 12.x global test configuration
uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

// Helper functions for UMS-STI testing
function createUser(string $type = 'standard', array $attributes = []): User
{
    $class = match($type) {
        'admin' => App\Models\Admin::class,
        'guest' => App\Models\Guest::class,
        'system' => App\Models\SystemUser::class,
        default => App\Models\StandardUser::class,
    };

    return $class::factory()->create($attributes);
}

function mockTeamWithMembers(array $memberIds = []): Mockery\MockInterface
{
    $team = Mockery::mock(App\Models\Team::class);
    $members = Mockery::mock();

    foreach ($memberIds as $memberId) {
        $members->shouldReceive('where')
            ->with('user_id', $memberId)
            ->andReturnSelf();
        $members->shouldReceive('exists')
            ->andReturn(true);
    }

    $team->shouldReceive('members')->andReturn($members);
    return $team;
}

// Laravel 12.x enhanced expectations for UMS-STI patterns
expect()->extend('toBeUserType', function (string $expectedType) {
    return $this->toBeInstanceOf(match($expectedType) {
        'admin' => App\Models\Admin::class,
        'guest' => App\Models\Guest::class,
        'system' => App\Models\SystemUser::class,
        default => App\Models\StandardUser::class,
    });
});

expect()->extend('toHaveExplicitAccess', function (App\Models\Team $team) {
    $user = $this->value;
    $service = app(App\Services\PermissionIsolationService::class);
    return expect($service->validateExplicitAccess($user, $team))->toBeTrue();
});

expect()->extend('toHaveRecord', function (array $attributes) {
    $table = $this->value;
    return $this->assertDatabaseHas($table, $attributes);
});

expect()->extend('toBeValidUlid', function () {
    $value = $this->value;
    return expect($value)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

// Laravel 12.x dataset helpers for common test scenarios
dataset('user_types', [
    'standard' => ['standard'],
    'admin' => ['admin'],
    'guest' => ['guest'],
    'system' => ['system'],
]);

dataset('team_roles', [
    'member' => ['member'],
    'leader' => ['leader'],
    'executive' => ['executive'],
    'deputy' => ['deputy'],
]);
```

### Step 2: Create STI Model Unit Tests

Create `tests/Unit/Models/UserStiTest.php`:

```php
<?php

use App\Enums\UserType;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\SystemUser;

describe('User STI Behavior', function () {
    it('instantiates correct user type classes', function () {
        $standard = StandardUser::factory()->make();
        $admin = Admin::factory()->make();
        $guest = Guest::factory()->make();
        $system = SystemUser::factory()->make();

        expect($standard)->toBeUserType('standard');
        expect($admin)->toBeUserType('admin');
        expect($guest)->toBeUserType('guest');
        expect($system)->toBeUserType('system');
    });

    it('returns correct user type enum', function () {
        $admin = Admin::factory()->make();
        expect($admin->getTypeAttribute())->toBe(UserType::Admin);
    });

    it('validates type-specific behaviors', function () {
        $admin = Admin::factory()->make();
        $standard = StandardUser::factory()->make();
        $guest = Guest::factory()->make();
        $system = SystemUser::factory()->make();

        expect($admin->canAccessAdminPanel())->toBeTrue();
        expect($standard->canAccessAdminPanel())->toBeFalse();
        expect($guest->canAccessAdminPanel())->toBeFalse();
        expect($system->canAccessAdminPanel())->toBeTrue();
    });

    it('validates permission levels', function () {
        $guest = Guest::factory()->make();
        $standard = StandardUser::factory()->make();
        $admin = Admin::factory()->make();
        $system = SystemUser::factory()->make();

        expect($guest->getPermissionLevel())->toBe(1);
        expect($standard->getPermissionLevel())->toBe(2);
        expect($admin->getPermissionLevel())->toBe(3);
        expect($system->getPermissionLevel())->toBe(4);
    });

    it('identifies system users correctly', function () {
        $standard = StandardUser::factory()->make();
        $system = SystemUser::factory()->make();

        expect($standard->isSystemUser())->toBeFalse();
        expect($system->isSystemUser())->toBeTrue();
    });

    it('validates guest conversion logic', function () {
        $guest = Guest::factory()->make([
            'name' => 'Test Guest',
            'email' => 'guest@example.com',
        ]);

        // Mock the conversion process
        $mockStandardUser = Mockery::mock(StandardUser::class);
        $mockStandardUser->shouldReceive('getAttribute')
            ->with('name')
            ->andReturn('Test Guest');
        $mockStandardUser->shouldReceive('getAttribute')
            ->with('email')
            ->andReturn('guest@example.com');

        // Test conversion data preparation
        $conversionData = [
            'name' => $guest->name,
            'email' => $guest->email,
            'password' => $guest->password,
            'state' => $guest->state,
        ];

        expect($conversionData['name'])->toBe('Test Guest');
        expect($conversionData['email'])->toBe('guest@example.com');
    });
});
```

### Step 3: Create Permission Isolation Unit Tests

Create `tests/Unit/Services/PermissionIsolationServiceTest.php`:

```php
<?php

use App\Models\Admin;
use App\Models\StandardUser;
use App\Models\SystemUser;
use App\Models\Team;
use App\Services\PermissionIsolationService;

describe('Permission Isolation Service', function () {
    beforeEach(function () {
        $this->service = new PermissionIsolationService();
    });

    it('validates explicit access requirement', function () {
        $user = Mockery::mock(StandardUser::class);
        $team = Mockery::mock(Team::class);

        // Mock team membership check
        $members = Mockery::mock();
        $members->shouldReceive('where')
            ->with('user_id', $user->id)
            ->andReturnSelf();
        $members->shouldReceive('exists')
            ->andReturn(false);

        $team->shouldReceive('members')->andReturn($members);
        $user->shouldReceive('isSystemUser')->andReturn(false);

        expect($this->service->validateExplicitAccess($user, $team))->toBeFalse();
    });

    it('allows system user bypass', function () {
        $systemUser = Mockery::mock(SystemUser::class);
        $team = Mockery::mock(Team::class);

        $systemUser->shouldReceive('isSystemUser')->andReturn(true);

        expect($this->service->validateExplicitAccess($systemUser, $team))->toBeTrue();
    });

    it('validates team role assignment logic', function () {
        $user = Mockery::mock(StandardUser::class);
        $team = Mockery::mock(Team::class);

        // Mock successful team membership
        $members = Mockery::mock();
        $members->shouldReceive('where')
            ->with('user_id', $user->id)
            ->andReturnSelf();
        $members->shouldReceive('exists')
            ->andReturn(true);

        $team->shouldReceive('members')->andReturn($members);
        $team->shouldReceive('addMember')->with($user);

        $user->shouldReceive('assignRole')->with('admin', $team);
        $user->shouldReceive('isSystemUser')->andReturn(false);

        // Mock activity logging
        activity()->shouldReceive('performedOn')
            ->with($team)
            ->andReturnSelf();

        $result = $this->service->assignTeamRole($user, $team, 'admin');
        expect($result)->toBeTrue();
    });

    it('detects inheritance violations correctly', function () {
        $user = Mockery::mock(StandardUser::class);
        $childTeam = Mockery::mock(Team::class);
        $parentTeam = Mockery::mock(Team::class);

        // Mock team hierarchy
        $ancestors = collect([$parentTeam]);
        $childTeam->shouldReceive('ancestors')->andReturn($ancestors);

        // Mock parent team membership
        $parentMembers = Mockery::mock();
        $parentMembers->shouldReceive('where')
            ->with('user_id', $user->id)
            ->andReturnSelf();
        $parentMembers->shouldReceive('exists')
            ->andReturn(true);

        $parentTeam->shouldReceive('members')->andReturn($parentMembers);
        $parentTeam->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $parentTeam->shouldReceive('getAttribute')->with('name')->andReturn('Parent Team');

        $user->shouldReceive('getRoleNames')
            ->with($parentTeam)
            ->andReturn(collect(['admin']));

        $violations = $this->service->auditInheritanceViolation($user, $childTeam);

        expect($violations)->toHaveCount(1);
        expect($violations[0]['parent_team_name'])->toBe('Parent Team');
        expect($violations[0]['violation_type'])->toBe('potential_inheritance_access');
    });

    it('generates correct user team permissions', function () {
        $user = Mockery::mock(StandardUser::class);
        $team = Mockery::mock(Team::class);

        // Mock explicit access validation
        $members = Mockery::mock();
        $members->shouldReceive('where')
            ->with('user_id', $user->id)
            ->andReturnSelf();
        $members->shouldReceive('exists')
            ->andReturn(true);

        $team->shouldReceive('members')->andReturn($members);
        $team->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $team->shouldReceive('getAttribute')->with('name')->andReturn('Test Team');

        $user->shouldReceive('isSystemUser')->andReturn(false);
        $user->shouldReceive('getRoleNames')
            ->with($team)
            ->andReturn(collect(['admin', 'member']));
        $user->shouldReceive('getPermissionsViaRoles')
            ->with($team)
            ->andReturn(collect([
                (object)['name' => 'manage-users'],
                (object)['name' => 'view-team'],
            ]));

        $permissions = $this->service->getUserTeamPermissions($user, $team);

        expect($permissions)->toHaveKey('roles');
        expect($permissions)->toHaveKey('permissions');
        expect($permissions['roles'])->toBe(['admin', 'member']);
        expect($permissions['permissions'])->toBe(['manage-users', 'view-team']);
        expect($permissions['access_type'])->toBe('explicit');
    });
});
```

### Step 4: Create Closure Table Unit Tests

Create `tests/Unit/Services/TeamClosureServiceTest.php`:

```php
<?php

use App\Models\Team;
use App\Models\TeamClosure;
use App\Services\TeamClosureService;

describe('Team Closure Service', function () {
    beforeEach(function () {
        $this->service = new TeamClosureService();
    });

    it('validates depth limit enforcement', function () {
        $team = Mockery::mock(Team::class);
        $team->shouldReceive('getDepth')->andReturn(5);

        expect($this->service->validateDepthLimit($team, 3))->toBeFalse();
        expect($this->service->validateDepthLimit($team, 5))->toBeTrue();
        expect($this->service->validateDepthLimit($team, 8))->toBeTrue();
    });

    it('detects hierarchy cycles correctly', function () {
        $teamA = Mockery::mock(Team::class);
        $teamB = Mockery::mock(Team::class);

        // Mock A is ancestor of B
        $teamA->shouldReceive('isAncestorOf')
            ->with($teamB)
            ->andReturn(true);

        // Mock B is not ancestor of A
        $teamB->shouldReceive('isAncestorOf')
            ->with($teamA)
            ->andReturn(false);

        expect($this->service->detectCycle($teamA, $teamB))->toBeTrue();
        expect($this->service->detectCycle($teamB, $teamA))->toBeFalse();
    });

    it('calculates hierarchy statistics correctly', function () {
        // Mock Team model static methods
        Team::shouldReceive('count')->andReturn(10);
        TeamClosure::shouldReceive('max')->with('depth')->andReturn(4);

        // Mock query builder for root teams
        $rootQuery = Mockery::mock();
        $rootQuery->shouldReceive('count')->andReturn(2);
        
        Team::shouldReceive('whereDoesntHave')
            ->with('closureAncestors', Mockery::any())
            ->andReturn($rootQuery);

        // Mock query builder for leaf teams
        $leafQuery = Mockery::mock();
        $leafQuery->shouldReceive('count')->andReturn(5);
        
        Team::shouldReceive('whereDoesntHave')
            ->with('closureDescendants', Mockery::any())
            ->andReturn($leafQuery);

        $stats = $this->service->getHierarchyStats();

        expect($stats['total_teams'])->toBe(10);
        expect($stats['max_depth'])->toBe(4);
        expect($stats['root_teams'])->toBe(2);
        expect($stats['leaf_teams'])->toBe(5);
    });

    it('validates anonymous token generation', function () {
        $userId = 123;
        $token = $this->service->generateAnonymousToken($userId);

        expect($token)->toBeString();
        expect(strlen($token))->toBe(64); // SHA256 hash length
        expect($token)->toMatch('/^[a-f0-9]{64}$/'); // Hex format
    });
});
```

### Step 5: Create GDPR Compliance Unit Tests

Create `tests/Unit/Services/DataRetentionServiceTest.php`:

```php
<?php

use App\Enums\DataClassification;
use App\Models\DataRetentionRecord;
use App\Models\User;
use App\Services\DataRetentionService;
use Carbon\Carbon;

describe('Data Retention Service', function () {
    beforeEach(function () {
        $this->service = new DataRetentionService();
    });

    it('calculates correct retention periods', function () {
        $personalData = DataClassification::PersonalData;
        $auditData = DataClassification::AuditData;

        expect($personalData->getRetentionYears())->toBe(2);
        expect($auditData->getRetentionYears())->toBe(7);
    });

    it('identifies deletable data types correctly', function () {
        expect(DataClassification::PersonalData->isDeletableOnRequest())->toBeTrue();
        expect(DataClassification::AuditData->isDeletableOnRequest())->toBeFalse();
        expect(DataClassification::SystemData->isDeletableOnRequest())->toBeFalse();
    });

    it('generates consistent anonymous tokens', function () {
        $userId = 123;
        
        // Mock config and time for consistency
        config(['app.key' => 'test-key']);
        Carbon::setTestNow('2024-01-01 12:00:00');

        $token1 = $this->service->generateAnonymousToken($userId);
        $token2 = $this->service->generateAnonymousToken($userId);

        expect($token1)->toBe($token2); // Should be same with same inputs
        expect(strlen($token1))->toBe(64);
    });

    it('validates legal basis for data types', function () {
        expect(DataClassification::PersonalData->getLegalBasis())
            ->toBe('Consent (Article 6(1)(a))');
        
        expect(DataClassification::AuditData->getLegalBasis())
            ->toBe('Legitimate interest (Article 6(1)(f))');
        
        expect(DataClassification::ComplianceData->getLegalBasis())
            ->toBe('Legal obligation (Article 6(1)(c))');
    });

    it('calculates retention expiration correctly', function () {
        Carbon::setTestNow('2024-01-01');

        $personalExpiry = now()->addYears(DataClassification::PersonalData->getRetentionYears());
        $auditExpiry = now()->addYears(DataClassification::AuditData->getRetentionYears());

        expect($personalExpiry->year)->toBe(2026); // 2024 + 2
        expect($auditExpiry->year)->toBe(2031);    // 2024 + 7
    });

    it('validates affected tables mapping', function () {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(123);

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('getAffectedTables');
        $method->setAccessible(true);

        $affectedTables = $method->invoke($this->service, $user);

        expect($affectedTables)->toBeArray();
        expect($affectedTables)->toContain('activity_log');
        expect($affectedTables)->toHaveKey('users');
        expect($affectedTables['users'])->toContain('created_by');
        expect($affectedTables['users'])->toContain('updated_by');
    });
});
```

## Testing & Validation

### Running Unit Tests (Laravel 12.x)

```bash
# Run all unit tests with Laravel 12.x optimizations
./vendor/bin/pest tests/Unit

# Run specific test suite with parallel execution
./vendor/bin/pest tests/Unit/Models --parallel
./vendor/bin/pest tests/Unit/Services --parallel

# Run with coverage (Laravel 12.x enhanced reporting)
./vendor/bin/pest --coverage --min=95 tests/Unit

# Run specific test file with detailed output
./vendor/bin/pest tests/Unit/Models/UserStiTest.php --verbose

# Run with profiling for performance analysis
./vendor/bin/pest tests/Unit --profile

# Run with dataset testing
./vendor/bin/pest tests/Unit --filter="user_types"

# Laravel 12.x specific: Run with memory optimization
./vendor/bin/pest tests/Unit --memory-limit=512M
```

### Test Performance Benchmarks

```php
// Add to test configuration
it('completes user STI tests within performance threshold', function () {
    $startTime = microtime(true);
    
    // Run STI behavior tests
    $users = collect([
        StandardUser::factory()->make(),
        Admin::factory()->make(),
        Guest::factory()->make(),
        SystemUser::factory()->make(),
    ]);
    
    $users->each(fn($user) => $user->getTypeAttribute());
    
    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    
    expect($executionTime)->toBeLessThan(10); // Should complete in under 10ms
});
```

## Common Pitfalls & Troubleshooting

### Issue 1: Mockery Memory Leaks
**Problem**: Tests become slow due to Mockery objects not being cleaned up
**Solution**: Add `Mockery::close()` in tearDown methods

### Issue 2: STI Factory Conflicts
**Problem**: Factory creates wrong user type in tests
**Solution**: Use specific type factories and validate type attribute

### Issue 3: Static Method Mocking Issues
**Problem**: Cannot mock static Eloquent methods properly
**Solution**: Use dependency injection and interface abstractions

## Integration Points

### Connection to Other Testing Strategies
- **Feature Tests (Task 8.2)**: Unit tests validate individual components
- **Performance Tests (Task 8.3)**: Unit tests ensure algorithmic efficiency
- **Integration Tests (Task 8.4)**: Unit tests verify component contracts

## Further Reading & Resources

### Testing Resources
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Pest PHP Documentation](https://pestphp.com/)
- [Mockery Documentation](http://docs.mockery.io/)

### Testing Patterns
- [Test-Driven Development](https://martinfowler.com/bliki/TestDrivenDevelopment.html)
- [Unit Testing Best Practices](https://github.com/goldbergyoni/javascript-testing-best-practices)

## References and Citations

### Primary Sources
- [Laravel 12.x Testing](https://laravel.com/docs/12.x/testing)
- [Pest PHP v3 Documentation](https://pestphp.com/docs/installation)
- [Laravel 12.x Mocking](https://laravel.com/docs/12.x/mocking)
- [PHPUnit 11 Documentation](https://phpunit.de/documentation.html)

### Secondary Sources
- [Mockery Documentation](http://docs.mockery.io/en/latest/)
- [Test-Driven Development by Kent Beck](https://www.amazon.com/Test-Driven-Development-Kent-Beck/dp/0321146530)
- [Laravel Testing Best Practices](https://github.com/alexeymezenin/laravel-best-practices#testing)
- [Unit Testing Principles](https://martinfowler.com/bliki/UnitTest.html)

### Related UMS-STI Documentation
- [Feature Testing Workflows](02-feature-testing-workflows.md) - Next implementation step
- [Performance Benchmarking](03-performance-benchmarking.md) - Performance testing strategies
- [Integration Testing](04-integration-testing.md) - Component integration testing
- [CI/CD Quality Assurance](05-ci-cd-quality-assurance.md) - Automated testing pipeline
- [STI Architecture](../02-user-models/01-sti-architecture-explained.md) - STI testing context
- [Permission Isolation](../04-permission-system/02-permission-isolation-design.md) - Permission testing patterns
- [GDPR Compliance](../05-gdpr-compliance/01-data-retention-architecture.md) - Compliance testing
- [PRD Requirements](../../prd-UMS-STI.md) - Testing specifications
- [Test Specification](../../test-specification-UMS-STI.md) - Detailed test cases

### Laravel 12.x Compatibility Notes
- Enhanced Pest PHP integration with improved dataset support
- Parallel testing capabilities for faster test execution
- Improved memory management for large test suites
- Enhanced mocking patterns with better type safety
- Updated factory patterns for STI models
- Improved coverage reporting with detailed metrics

---

**Next Steps**: Proceed to [Feature Testing Workflows](02-feature-testing-workflows.md) to implement end-to-end testing for complete user workflows and system integration.
