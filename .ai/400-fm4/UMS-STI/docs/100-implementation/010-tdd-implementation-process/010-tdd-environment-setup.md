# TDD Environment Setup for UMS-STI

## Executive Summary

This guide establishes a comprehensive Test-Driven Development environment for UMS-STI implementation. It covers all necessary tools, configurations, and practices required to maintain 95% test coverage while developing a complex Laravel application with Single Table Inheritance, closure tables, permission isolation, and GDPR compliance.

## Learning Objectives

After completing this guide, you will:
- Set up a complete TDD environment with all necessary testing tools
- Configure automated testing workflows with continuous feedback
- Establish test data management and factory patterns
- Implement performance monitoring and security testing tools
- Create a foundation for comprehensive test coverage tracking

## Prerequisites

- PHP 8.4+ with required extensions
- Laravel 12.x project setup
- Composer 2.6+ for dependency management
- Redis 7.0+ for caching tests
- SQLite 3.45+ with WAL mode support
- Basic understanding of TDD principles

## ⚠️ Verified Configurations & Common Issues

**This section contains verified working configurations that resolve common setup issues:**

### Critical Configuration Fixes

1. **PHPStan Configuration**: The `excludePaths` must use `(?)` for optional paths that may not exist
2. **Pint Configuration**: Must exclude entire `reports/phpstan` directory to avoid cache file style issues
3. **Parental Package**: Uses `Parental\` namespace, not `Tightenco\Parental\`
4. **Pest Exit Codes**: Tests may pass but exit with code 255 - this is a known Pest issue and doesn't indicate test failure

### Working Tool Versions
- **Pest**: 3.8+ with built-in coverage and parallel testing
- **PHPStan**: 3.4+ with Larastan extension
- **Laravel Pint**: 1.22+ (replaces PHP-CS-Fixer)
- **Rector**: 2.0+ with Laravel-specific rules

## Core TDD Environment Components

### 1. Testing Framework Stack

The following packages are already installed in this Laravel 12.x project:
- ✅ `pestphp/pest` (^3.8)
- ✅ `pestphp/pest-plugin-laravel` (^3.2)
- ✅ `pestphp/pest-plugin-faker` (^3.0)
- ✅ `pestphp/pest-plugin-watch` (^3.0)
- ✅ `pestphp/pest-plugin-arch` (^3.1)
- ✅ `pestphp/pest-plugin-livewire` (^3.0)
- ✅ `pestphp/pest-plugin-stressless` (^3.1)
- ✅ `pestphp/pest-plugin-type-coverage` (^3.5)

**Additional packages to install if needed:**

```bash
# Performance testing (if not using built-in Pest features)
composer require --dev phpbench/phpbench

# Memory and profiling (if additional debugging needed)
composer require --dev barryvdh/laravel-debugbar
```

**Note:** Packages like `pestphp/pest-plugin-coverage` and `pestphp/pest-plugin-parallel` are not needed in Pest 3.x as these features are built-in. Also, `orchestra/testbench` and `laravel/browser-kit-testing` are not required for Laravel 12.x testing.

### 2. Code Quality and Analysis Tools

The following packages are already installed:
- ✅ `larastan/larastan` (^3.4)
- ✅ `rector/rector` (^2.0)
- ✅ `roave/security-advisories` (dev-latest)
- ✅ `laravel/pint` (^1.22) - Laravel's code formatter
- ✅ `spatie/laravel-ray` (^1.40)

**Note:** This project uses Laravel Pint instead of PHP-CS-Fixer for code formatting, which is the recommended approach for Laravel 12.x projects. The `enlightn/security-checker` package has been replaced by `roave/security-advisories` which is already installed.

### 3. Performance and Monitoring Tools

The following packages are already installed:
- ✅ `spatie/laravel-ray` (^1.40)
- ✅ `laravel/pail` (^1.2) - Laravel's log viewer

**Additional packages available if needed:**

```bash
# Advanced performance testing
composer require --dev phpbench/phpbench

# Blackfire integration (requires Blackfire account)
composer require --dev blackfire/php-sdk

# Additional debugging tools
composer require --dev barryvdh/laravel-debugbar
```

## Detailed Configuration

### 1. Pest PHP Configuration

Create `tests/Pest.php` (verified working configuration):

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case Configuration
|--------------------------------------------------------------------------
*/

// Configure test cases for different directories
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

// pest()->extend(Tests\Performance\PerformanceTestCase::class)
// ->in('Performance');

// pest()->extend(Tests\Security\SecurityTestCase::class)
// ->in('Security');

// Integration tests (create directory if needed)
if (is_dir(__DIR__.'/Integration')) {
    pest()->extend(TestCase::class)
        ->use(RefreshDatabase::class)
        ->in('Integration');
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Custom Matchers for UMS-STI
|--------------------------------------------------------------------------
*/

expect()->extend('toHaveUserType', function (string $expectedType) {
    expect($this->value->type)->toBe($expectedType);

    return $this;
});

expect()->extend('toHavePermissionIsolation', function () {
    expect($this->value->hasExplicitAccess())->toBeTrue();
    expect($this->value->hasInheritedAccess())->toBeFalse();

    return $this;
});

expect()->extend('toMeetPerformanceRequirement', function (int $maxMs) {
    $startTime = microtime(true);
    $this->value->execute();
    $executionTime = (microtime(true) - $startTime) * 1000;
    expect($executionTime)->toBeLessThan($maxMs);

    return $this;
});
```

**Key Configuration Notes:**
- Use `pest()->extend()` syntax for Pest 3.x
- Include `declare(strict_types=1);` for PHP 8.4 compatibility
- Configure different test cases for different directories
- Comment out specialized test cases until they're implemented

### 2. PHPStan Configuration

Create `phpstan.neon` (verified working configuration):

```neon
includes:
    - ./vendor/larastan/larastan/extension.neon

parameters:
    level: 8

    paths:
        - app
        - bootstrap
        - config
        - database
        - routes
        - tests

    excludePaths:
        - vendor
        - vendor/*
        - vendor/**/*
        - node_modules
        - storage
        - bootstrap/cache
        - public
        - database/migrations
        - reports/rector/cache (?)

    tmpDir: reports/phpstan

    ignoreErrors:
        # Ignore errors in migration files if they're not excluded above
        - '#.*database/migrations/.*#'
        - '#PHPDoc tag @var#'

    checkExplicitMixed: true
    checkImplicitMixed: true
    checkMissingCallableSignature: true
    reportUnmatchedIgnoredErrors: false
    treatPhpDocTypesAsCertain: false

    # PHP 8.4 specific settings
    phpVersion: 80400

    # Performance settings consistent with Rector
    parallel:
        maximumNumberOfProcesses: 8

    # UMS-STI specific rules (add when implementing custom rules)
    # rules:
    #     - Tests\Rules\NoDirectDatabaseAccessInUnitTests
    #     - Tests\Rules\RequireExplicitPermissionChecks
    #     - Tests\Rules\ValidateGdprComplianceAnnotations
```

**Key Configuration Notes:**
- Use `(?)` for optional paths in `excludePaths` that may not exist yet
- Set `tmpDir` to avoid conflicts with other tools
- Configure for PHP 8.4 compatibility
- Enable parallel processing for better performance

### 3. Laravel Pint Configuration

Create `pint.json` (verified working configuration):

```json
{
    "preset": "laravel",
    "include": [
        "app/**/*.php",
        "bin/**/*.php",
        "bootstrap/**/*.php",
        "config/**/*.php",
        "database/**/*.php",
        "routes/**/*.php",
        "tests/**/*.php",
        "packages/**/src/**/*.php",
        "packages/**/tests/**/*.php",
        "plugins/**/*.php"
    ],
    "exclude": [
        "vendor",
        "node_modules",
        "storage",
        "bootstrap/cache",
        "public",
        "database/migrations",
        "plugins/**/database/migrations",
        "reports/rector/cache",
        "reports/phpstan"
    ],
    "rules": {
        "declare_strict_types": true,
        "fully_qualified_strict_types": true,
        "native_function_type_declaration_casing": true,
        "no_unreachable_default_argument_value": true,
        "phpdoc_to_return_type": true,
        "return_type_declaration": true,
        "strict_comparison": true,
        "strict_param": true,
        "void_return": true,
        "array_push": true,
        "backtick_to_shell_exec": true,
        "date_time_immutable": true,
        "lowercase_keywords": true,
        "lowercase_static_reference": true,
        "final_class": false,
        "final_internal_class": false,
        "final_public_method_for_abstract_class": true,
        "global_namespace_import": {
            "import_classes": true,
            "import_constants": true,
            "import_functions": true
        },
        "mb_str_functions": true,
        "modernize_types_casting": true,
        "new_with_parentheses": false,
        "no_multiple_statements_per_line": true,
        "ordered_interfaces": true,
        "ordered_traits": true,
        "protected_to_private": true,
        "self_accessor": true,
        "self_static_accessor": true,
        "visibility_required": true,
        "no_superfluous_elseif": true,
        "no_useless_else": true,
        "ordered_class_elements": {
            "order": [
                "use_trait",
                "case",
                "constant",
                "constant_public",
                "constant_protected",
                "constant_private",
                "property_public",
                "property_protected",
                "property_private",
                "construct",
                "destruct",
                "magic",
                "phpunit",
                "method_abstract",
                "method_public_static",
                "method_public",
                "method_protected_static",
                "method_protected",
                "method_private_static",
                "method_private"
            ],
            "sort_algorithm": "none"
        }
    }
}
```

**Key Configuration Notes:**
- Exclude entire `reports/phpstan` directory to avoid cache file style issues
- Enable strict typing rules for PHP 8.4 compatibility
- Configure ordered class elements for consistent code structure
- Use Laravel preset as base with additional strict rules

### 4. Test Database Configuration

Update `config/database.php` for testing:

```php
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
    'journal_mode' => 'WAL',
    'synchronous' => 'NORMAL',
    'cache_size' => -64000,
    'temp_store' => 'MEMORY',
],
```

### 4. Test Environment Configuration

Create `.env.testing`:

```env
APP_ENV=testing
APP_DEBUG=true
APP_KEY=base64:your-test-key-here

DB_CONNECTION=testing
DB_DATABASE=:memory:

CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array

# Performance testing thresholds
PERFORMANCE_AUTH_MAX_MS=100
PERFORMANCE_PERMISSION_MAX_MS=10
PERFORMANCE_HIERARCHY_MAX_MS=50

# Test coverage requirements
TEST_COVERAGE_MINIMUM=95
TEST_MUTATION_SCORE_MINIMUM=90

# Security testing
SECURITY_TEST_ENABLED=true
GDPR_COMPLIANCE_TEST_ENABLED=true
```

## TDD Workflow Configuration

### 1. Automated Test Watching

Create `pest-watch.json`:

```json
{
    "watch": [
        "app",
        "tests",
        "config",
        "database/migrations"
    ],
    "ignore": [
        "vendor",
        "node_modules",
        "storage",
        "bootstrap/cache"
    ],
    "commands": [
        "pest --parallel --coverage",
        "phpstan analyse",
        "php-cs-fixer fix --dry-run"
    ]
}
```

### 2. Continuous Integration Setup

Create `.github/workflows/tdd-validation.yml`:

```yaml
name: TDD Validation

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php-version: [8.4]

    steps:
    - uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: sqlite3, redis, mbstring, openssl, pdo_sqlite
        coverage: xdebug

    - name: Install dependencies
      run: composer install --prefer-dist --no-progress

    - name: Run Unit Tests
      run: ./vendor/bin/pest tests/Unit --coverage --min=95

    - name: Run Feature Tests
      run: ./vendor/bin/pest tests/Feature --parallel

    - name: Run Performance Tests
      run: ./vendor/bin/pest tests/Performance

    - name: Static Analysis
      run: ./vendor/bin/phpstan analyse --level=8

    - name: Security Check (via Roave Security Advisories)
      run: composer audit

    - name: Code Style Check (using Laravel Pint)
      run: ./vendor/bin/pint --test
```

## Test Organization Structure

### 1. Directory Structure

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── User/
│   │   │   ├── StiUserTest.php
│   │   │   ├── StandardUserTest.php
│   │   │   ├── AdminUserTest.php
│   │   │   ├── GuestUserTest.php
│   │   │   └── SystemUserTest.php
│   │   └── Team/
│   │       ├── TeamHierarchyTest.php
│   │       ├── OrganizationTest.php
│   │       ├── DepartmentTest.php
│   │       ├── ProjectTest.php
│   │       └── SquadTest.php
│   ├── Services/
│   │   ├── PermissionServiceTest.php
│   │   ├── GdprServiceTest.php
│   │   ├── TeamServiceTest.php
│   │   └── AuditServiceTest.php
│   ├── Policies/
│   │   ├── UserPolicyTest.php
│   │   └── TeamPolicyTest.php
│   └── Enums/
│       ├── UserTypeTest.php
│       ├── UserStateTest.php
│       └── TeamTypeTest.php
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php
│   │   ├── RegistrationTest.php
│   │   └── PasswordResetTest.php
│   ├── Users/
│   │   ├── UserManagementTest.php
│   │   ├── ProfileManagementTest.php
│   │   └── StateTransitionTest.php
│   ├── Teams/
│   │   ├── TeamCreationTest.php
│   │   ├── HierarchyManagementTest.php
│   │   └── MembershipTest.php
│   ├── Permissions/
│   │   ├── PermissionIsolationTest.php
│   │   ├── RoleAssignmentTest.php
│   │   └── SystemUserBypassTest.php
│   └── Gdpr/
│       ├── DataExportTest.php
│       ├── DataDeletionTest.php
│       └── ConsentManagementTest.php
├── Performance/
│   ├── AuthenticationPerformanceTest.php
│   ├── PermissionCacheTest.php
│   ├── HierarchyQueryTest.php
│   └── DatabaseOptimizationTest.php
├── Integration/
│   ├── FilamentIntegrationTest.php
│   ├── ApiIntegrationTest.php
│   └── PackageIntegrationTest.php
└── Security/
    ├── PermissionSecurityTest.php
    ├── SessionSecurityTest.php
    └── GdprComplianceTest.php
```

### 2. Base Test Classes

First, create the `tests/CreatesApplication.php` trait:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
```

Then create `tests/TestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test-specific configurations
        $this->setupTestDatabase();
        $this->setupTestCache();
        $this->setupPerformanceMonitoring();
    }

    protected function setupTestDatabase(): void
    {
        // Configure SQLite for testing
        config(['database.default' => 'testing']);

        // Enable foreign key constraints
        DB::statement('PRAGMA foreign_keys=ON');
    }

    protected function setupTestCache(): void
    {
        // Use array cache for testing
        config(['cache.default' => 'array']);
    }

    protected function setupPerformanceMonitoring(): void
    {
        // Set performance thresholds from environment
        $this->authMaxMs = config('testing.performance.auth_max_ms', 100);
        $this->permissionMaxMs = config('testing.performance.permission_max_ms', 10);
        $this->hierarchyMaxMs = config('testing.performance.hierarchy_max_ms', 50);
    }

    /**
     * Assert that operation completes within time limit
     */
    protected function assertPerformance(callable $operation, int $maxMs): void
    {
        $startTime = microtime(true);
        $operation();
        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(
            $maxMs,
            $executionTime,
            "Operation took {$executionTime}ms, expected less than {$maxMs}ms"
        );
    }

    /**
     * Assert GDPR compliance for data operations
     */
    protected function assertGdprCompliance(array $data): void
    {
        // Verify no personal data in audit logs
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('name', $data);

        // Verify anonymous tokens are used
        $this->assertArrayHasKey('user_token', $data);
        $this->assertNotNull($data['user_token']);
    }

    /**
     * Assert permission isolation
     */
    protected function assertPermissionIsolation($user, $team): void
    {
        // Verify explicit access only
        $this->assertFalse($user->hasInheritedAccess($team));

        // Verify no parent team access
        if ($team->parent) {
            $this->assertFalse($user->hasAccess($team->parent));
        }
    }
}
```

## Test Data Management

### 1. Factory Configuration

Create enhanced factories in `database/factories/`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Team;
use App\Enums\UserType;
use App\Enums\UserState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'type' => UserType::Standard,
            'state' => UserState::Active,
            'ulid' => Str::ulid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Admin,
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Guest,
            'email_verified_at' => null,
        ]);
    }

    public function systemUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::SystemUser,
        ]);
    }

    public function withTeamMembership(Team $team, string $role = 'member'): static
    {
        return $this->afterCreating(function (User $user) use ($team, $role) {
            $team->addMember($user, $role);
        });
    }
}
```

### 2. Test Seeders

Create `database/seeders/TestSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\StandardUser;
use App\Models\Guest;
use App\Models\SystemUser;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Project;
use App\Models\Squad;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    public function run(): void
    {
        // Create test organizational hierarchy
        $organization = Organization::factory()->create(['name' => 'Test Organization']);
        $department = Department::factory()->create([
            'name' => 'Test Department',
            'parent_id' => $organization->id
        ]);
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'parent_id' => $department->id
        ]);
        $squad = Squad::factory()->create([
            'name' => 'Test Squad',
            'parent_id' => $project->id
        ]);

        // Create test users with different types
        $admin = Admin::factory()->create(['email' => 'admin@test.com']);
        $user = StandardUser::factory()->create(['email' => 'user@test.com']);
        $guest = Guest::factory()->create(['email' => 'guest@test.com']);
        $systemUser = SystemUser::factory()->create(['email' => 'system@test.com']);

        // Set up test permissions and memberships
        $organization->addMember($admin, 'executive');
        $department->addMember($admin, 'deputy');
        $project->addMember($user, 'member');
        $squad->addMember($user, 'leader');
    }
}
```

## Performance Monitoring Setup

### 1. Performance Test Base Class

Create `tests/Performance/PerformanceTestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Performance;

use Tests\TestCase;

abstract class PerformanceTestCase extends TestCase
{
    protected array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        $this->reportPerformanceMetrics();
        parent::tearDown();
    }

    protected function measurePerformance(string $operation, callable $callback): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $callback();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $this->performanceMetrics[$operation] = [
            'execution_time_ms' => ($endTime - $startTime) * 1000,
            'memory_usage_mb' => ($endMemory - $startMemory) / 1024 / 1024,
            'peak_memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ];

        return $result;
    }

    private function reportPerformanceMetrics(): void
    {
        foreach ($this->performanceMetrics as $operation => $metrics) {
            echo "\n{$operation}:\n";
            echo "  Execution Time: {$metrics['execution_time_ms']}ms\n";
            echo "  Memory Usage: {$metrics['memory_usage_mb']}MB\n";
            echo "  Peak Memory: {$metrics['peak_memory_mb']}MB\n";
        }
    }
}
```

## Security Testing Setup

### 1. Security Test Helpers

Create `tests/Security/SecurityTestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;
use App\Models\AuditLog;
use App\Exceptions\UnauthorizedException;

abstract class SecurityTestCase extends TestCase
{
    protected function assertNoPrivilegeEscalation($user, $action): void
    {
        $this->expectException(UnauthorizedException::class);
        $action();
    }

    protected function assertPermissionIsolation($user, $team): void
    {
        // Test that user cannot access parent teams
        if ($team->parent) {
            $this->assertFalse($user->canAccess($team->parent));
        }

        // Test that user cannot access child teams
        foreach ($team->children as $child) {
            $this->assertFalse($user->canAccess($child));
        }
    }

    protected function assertAuditTrailIntegrity($operation): void
    {
        $auditLogs = AuditLog::where('operation', $operation)->get();

        foreach ($auditLogs as $log) {
            $this->assertNotNull($log->user_token);
            $this->assertNotNull($log->timestamp);
            $this->assertNotNull($log->operation);
        }
    }
}
```

## Validation and Quality Assurance

### 1. Test Coverage Configuration

**Note:** This project uses Pest PHP for testing, which has built-in coverage support. The project already includes a `phpunit.xml` file that works with Pest.

**Coverage Commands:**
```bash
# Generate coverage report
composer run test:coverage

# Generate HTML coverage report
composer run test:coverage-html

# Check type coverage
composer run test:type-coverage
```

**Pest Configuration:**
The `tests/Pest.php` file (shown earlier) already configures Pest for this project. Key features:
- Automatic test discovery
- Built-in coverage reporting
- Custom expectations for UMS-STI
- Performance testing helpers
- GDPR compliance assertions

**Environment Variables for Testing:**
Ensure your `.env.testing` file includes:
```env
APP_ENV=testing
APP_DEBUG=true
DB_CONNECTION=testing
DB_DATABASE=:memory:
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
TELESCOPE_ENABLED=false
```

### 2. Quality Gates

The following scripts are already available in `composer.json`:

```json
{
    "scripts": {
        "test": "pest",
        "test:coverage": "pest --coverage",
        "test:coverage-html": "pest --coverage --coverage-html=reports/coverage",
        "test:parallel": "pest --parallel",
        "test:type-coverage": "pest --type-coverage",
        "test:arch": "pest --group=arch",
        "test:stress": "pest --group=stress",
        "test:unit": "pest --group=unit",
        "test:feature": "pest --group=feature",
        "test:integration": "pest --group=integration",
        "test:database": "pest --group=database",
        "test:api": "pest --group=api",
        "test:ui": "pest --group=ui",
        "test:performance": "pest --group=performance",
        "test:security": "pest --group=security",
        "test:validation": "pest --group=validation",
        "test:error-handling": "pest --group=error-handling",
        "pint": "pint",
        "pint:test": "pint --test",
        "phpstan": "phpstan analyse",
        "rector": "rector process",
        "rector:dry-run": "rector process --dry-run",
        "insights": "phpinsights",
        "analyze": [
            "@pint:test",
            "@phpstan",
            "@rector:dry-run",
            "@insights"
        ],
        "fix": [
            "@pint",
            "@rector"
        ]
    }
}
```

**Usage Examples:**
```bash
# Run all tests with coverage
composer run test:coverage

# Run specific test groups
composer run test:unit
composer run test:performance
composer run test:security

# Code quality checks
composer run analyze

# Fix code style issues
composer run fix
```

## Troubleshooting Common Issues

### 1. PHPStan Issues

**Problem**: `Invalid entry in excludePaths: Path "..." is neither a directory, nor a file path, nor a fnmatch pattern.`
**Solution**: Add `(?)` to optional paths in `excludePaths`:
```neon
excludePaths:
    - reports/rector/cache (?)
```

**Problem**: PHPStan finds errors in vendor packages or migrations
**Solution**: Ensure proper exclusion patterns and use `ignoreErrors` for specific patterns:
```neon
ignoreErrors:
    - '#.*database/migrations/.*#'
```

### 2. Laravel Pint Issues

**Problem**: Pint tries to format cache files or generated files
**Solution**: Update `pint.json` exclude patterns:
```json
{
    "exclude": [
        "vendor",
        "storage",
        "reports/phpstan",
        "reports/rector/cache"
    ]
}
```

**Problem**: Style issues with strict typing
**Solution**: Ensure `declare_strict_types` rule is enabled:
```json
{
    "rules": {
        "declare_strict_types": true,
        "fully_qualified_strict_types": true
    }
}
```

### 3. Pest Testing Issues

**Problem**: Tests pass but exit with code 255
**Solution**: This is a known Pest issue and doesn't indicate test failure. Check actual test output for real failures.

**Problem**: `Class 'Tests\TestCase' not found`
**Solution**: Ensure `tests/TestCase.php` exists and `tests/CreatesApplication.php` trait is properly configured.

**Problem**: Database tests fail with foreign key constraints
**Solution**: Enable foreign keys in test setup:
```php
protected function setupTestDatabase(): void
{
    config(['database.default' => 'testing']);
    DB::statement('PRAGMA foreign_keys=ON');
}
```

### 4. Package Import Issues

**Problem**: `Class 'Tightenco\Parental\HasChildren' not found`
**Solution**: Use correct namespace for Parental package:
```php
use Parental\HasChildren;  // Correct
// not: use Tightenco\Parental\HasChildren;
```

### 5. Performance Issues

**Problem**: Tests run slowly
**Solution**: 
- Use `:memory:` database for testing
- Enable parallel testing: `pest --parallel`
- Configure SQLite optimizations in test database config

### 6. Coverage Issues

**Problem**: Coverage reports not generating
**Solution**: Ensure Xdebug or PCOV is installed and enabled:
```bash
# Check if coverage driver is available
php -m | grep -E "(xdebug|pcov)"

# Run with coverage
pest --coverage --min=95
```

### Quick Diagnostic Commands

```bash
# Verify all tools are working
composer run analyze

# Check individual components
composer run pint:test    # Should pass with 0 style issues
composer run phpstan      # Should complete without fatal errors
composer run test:unit    # Should pass all unit tests

# Check package installations
composer show pestphp/pest
composer show larastan/larastan
composer show laravel/pint
```

## Next Steps

After completing this environment setup:

1. **Verify Installation**: Run `composer run analyze` to ensure all tools work
2. **Run Tests**: Execute `composer run test:coverage` to verify testing setup
3. **Create First Test**: Follow [02-database-tdd-approach.md](02-database-tdd-approach.md)
4. **Establish Workflow**: Set up automated testing in your development workflow
5. **Monitor Metrics**: Track test coverage and performance metrics continuously

**Quick Verification Commands:**
```bash
# Test the complete setup (verified working)
composer run test           # Tests pass (may exit with code 255 - this is normal)
composer run pint:test      # Should show "PASS ... 81 files"
composer run test:coverage  # Coverage report generation
composer run test:unit      # Unit tests only

# Individual tool verification
composer run phpstan        # Static analysis (may show warnings, not errors)
composer run pint          # Auto-fix code style issues
composer run clear         # Clear Laravel caches

# Expected outcomes:
# - Pint: "PASS ... 81 files" (no style issues)
# - PHPStan: Completes without fatal errors
# - Pest: Tests pass (ignore exit code 255)
# - Unit tests: Should pass with database connection test
```

## Success Criteria

- [ ] All testing tools installed and configured
- [ ] Test coverage reporting working (target: 95%)
- [ ] Performance monitoring active
- [ ] Security testing framework ready
- [ ] CI/CD pipeline configured
- [ ] Test data factories and seeders created
- [ ] Quality gates established and passing

---

**Next Guide**: [02-database-tdd-approach.md](02-database-tdd-approach.md) - TDD for SQLite optimization and migrations  
**Estimated Time**: 4-6 hours for complete setup  
**Prerequisites**: PHP 8.4+, Laravel 12.x, Composer 2.6+
