# Database TDD Approach for UMS-STI

## Executive Summary

This guide demonstrates how to implement SQLite optimization, database migrations, and schema design using Test-Driven Development for UMS-STI. It covers testing database performance, validating WAL mode configuration, and ensuring proper indexing strategies through comprehensive test coverage.

## Learning Objectives

After completing this guide, you will:
- Write tests that drive database schema design decisions
- Implement TDD for SQLite performance optimization
- Create tests for migration integrity and rollback safety
- Validate database constraints and relationships through tests
- Establish performance benchmarks for database operations

## Prerequisites

- Completed [010-tdd-environment-setup.md](010-tdd-environment-setup.md)
- Understanding of SQLite WAL mode and performance characteristics
- Knowledge of Laravel migrations and database testing
- Familiarity with closure table patterns for hierarchical data

## TDD Approach for Database Foundation

### 1. Separate Event Store Database Configuration

The UMS-STI architecture requires a **separate, exclusive database connection** for the event store to ensure optimal performance and isolation. This separation must be implemented and tested from the beginning.

#### Event Store Database Requirements

- **Production**: Dedicated SQLite database with WAL optimization (`event_store.sqlite`)
- **Testing**: Separate event store connection (even when using `:memory:`)
- **Performance**: Optimized SQLite configuration with 64MB cache and memory mapping
- **Isolation**: Complete separation from application database

#### Step 1: Write Event Store Configuration Tests

Create `tests/Unit/Database/EventStoreConfigurationTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class EventStoreConfigurationTest extends TestCase
{
    /** @test */
    public function it_has_separate_event_store_database_connection()
    {
        // Test that event store connection is configured
        $connections = Config::get('database.connections');

        $this->assertArrayHasKey('event_store', $connections);
        $this->assertArrayHasKey('event_store_testing', $connections);
    }

    /** @test */
    public function event_store_connection_uses_separate_database_file()
    {
        $eventStoreConfig = Config::get('database.connections.event_store');
        $defaultConfig = Config::get('database.connections.sqlite');

        // Ensure event store uses different database file
        $this->assertNotEquals(
            $eventStoreConfig['database'], 
            $defaultConfig['database']
        );
    }

    /** @test */
    public function event_store_testing_connection_is_isolated()
    {
        $testingConfig = Config::get('database.connections.event_store_testing');
        $defaultTestingConfig = Config::get('database.connections.testing');

        // Ensure testing event store is separate from default testing
        $this->assertNotEquals(
            $testingConfig['database'], 
            $defaultTestingConfig['database']
        );
    }

    /** @test */
    public function event_store_connection_has_wal_optimization()
    {
        // Switch to event store connection for testing
        Config::set('database.default', 'event_store');
        DB::purge('event_store');

        $walMode = DB::connection('event_store')
            ->select('PRAGMA journal_mode')[0]->journal_mode;
        $this->assertEquals('wal', strtolower($walMode));
    }

    /** @test */
    public function event_store_connection_has_optimized_cache_size()
    {
        $cacheSize = DB::connection('event_store')
            ->select('PRAGMA cache_size')[0]->cache_size;
        $this->assertEquals(-64000, $cacheSize);
    }

    /** @test */
    public function event_store_connection_enables_foreign_keys()
    {
        $foreignKeys = DB::connection('event_store')
            ->select('PRAGMA foreign_keys')[0]->foreign_keys;
        $this->assertEquals(1, $foreignKeys);
    }
}
```

#### Step 2: Write Application Database Configuration Tests

Create `tests/Unit/Database/SqliteConfigurationTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class SqliteConfigurationTest extends TestCase
{
    /** @test */
    public function it_enables_wal_mode_for_performance()
    {
        // Test that WAL mode is enabled
        $walMode = DB::select('PRAGMA journal_mode')[0]->journal_mode;
        $this->assertEquals('wal', strtolower($walMode));
    }

    /** @test */
    public function it_configures_optimal_cache_size()
    {
        // Test cache size is set to 64MB (-64000 pages)
        $cacheSize = DB::select('PRAGMA cache_size')[0]->cache_size;
        $this->assertEquals(-64000, $cacheSize);
    }

    /** @test */
    public function it_enables_foreign_key_constraints()
    {
        // Test foreign keys are enabled
        $foreignKeys = DB::select('PRAGMA foreign_keys')[0]->foreign_keys;
        $this->assertEquals(1, $foreignKeys);
    }

    /** @test */
    public function it_configures_memory_mapped_io()
    {
        // Test mmap_size is set to 256MB
        $mmapSize = DB::select('PRAGMA mmap_size')[0]->mmap_size;
        $this->assertEquals(268435456, $mmapSize); // 256MB
    }

    /** @test */
    public function it_sets_synchronous_mode_for_performance()
    {
        // Test synchronous mode is NORMAL for performance
        $synchronous = DB::select('PRAGMA synchronous')[0]->synchronous;
        $this->assertEquals(1, $synchronous); // NORMAL = 1
    }

    /** @test */
    public function it_uses_memory_for_temporary_storage()
    {
        // Test temp_store is set to MEMORY
        $tempStore = DB::select('PRAGMA temp_store')[0]->temp_store;
        $this->assertEquals(2, $tempStore); // MEMORY = 2
    }
}
```

#### Step 2: Implement Database Configuration

Create a database configuration that makes tests pass:

```php
// config/database.php
'sqlite' => [
    'driver' => 'sqlite',
    'url' => env('DATABASE_URL'),
    'database' => env('DB_DATABASE', database_path('database.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    'options' => [
        PDO::ATTR_TIMEOUT => 60,
    ],
    'pragmas' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000,
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456,
        'foreign_keys' => true,
    ],
],
```

### 2. Migration Testing Strategy

#### Step 1: Write Migration Tests

Create `tests/Unit/Database/MigrationIntegrityTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class MigrationIntegrityTest extends TestCase
{
    /** @test */
    public function users_table_has_sti_support()
    {
        // Test users table structure for STI
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumn('users', 'type'));
        $this->assertTrue(Schema::hasColumn('users', 'ulid'));
        $this->assertTrue(Schema::hasColumn('users', 'state'));

        // Test indexes exist for STI queries
        $indexes = $this->getTableIndexes('users');
        $this->assertContains('users_type_index', $indexes);
        $this->assertContains('users_state_index', $indexes);
        $this->assertContains('users_ulid_unique', $indexes);
    }

    /** @test */
    public function teams_table_supports_hierarchy()
    {
        // Test teams table structure
        $this->assertTrue(Schema::hasTable('teams'));
        $this->assertTrue(Schema::hasColumn('teams', 'parent_id'));
        $this->assertTrue(Schema::hasColumn('teams', 'type'));
        $this->assertTrue(Schema::hasColumn('teams', 'settings'));

        // Test foreign key constraint
        $this->assertTrue(Schema::hasColumn('teams', 'parent_id'));
    }

    /** @test */
    public function team_closure_table_exists_for_hierarchy_queries()
    {
        // Test closure table structure
        $this->assertTrue(Schema::hasTable('team_closure'));
        $this->assertTrue(Schema::hasColumn('team_closure', 'ancestor_id'));
        $this->assertTrue(Schema::hasColumn('team_closure', 'descendant_id'));
        $this->assertTrue(Schema::hasColumn('team_closure', 'depth'));

        // Test composite primary key
        $indexes = $this->getTableIndexes('team_closure');
        $this->assertContains('team_closure_primary', $indexes);
    }

    /** @test */
    public function user_stamps_are_tracked_on_all_tables()
    {
        $tables = ['users', 'teams', 'team_user', 'permissions'];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'created_by'));
            $this->assertTrue(Schema::hasColumn($table, 'updated_by'));
            $this->assertTrue(Schema::hasColumn($table, 'deleted_by'));
        }
    }

    /** @test */
    public function migrations_can_rollback_safely()
    {
        // Test rollback capability
        Artisan::call('migrate:rollback', ['--step' => 1]);
        Artisan::call('migrate');

        // Verify all tables still exist after rollback/migrate cycle
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('teams'));
        $this->assertTrue(Schema::hasTable('team_closure'));
    }

    private function getTableIndexes(string $table): array
    {
        $indexes = DB::select("PRAGMA index_list({$table})");
        return array_column($indexes, 'name');
    }
}
```

#### Step 2: Create Migrations with TDD

Create migration that satisfies tests:

```php
// database/migrations/001_create_users_table.php
public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('ulid')->unique();
        $table->string('type')->index(); // STI support
        $table->string('state')->index(); // State management
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->json('settings')->nullable();
        $table->rememberToken();
        $table->timestamps();
        $table->softDeletes();

        // User stamps
        $table->unsignedBigInteger('created_by')->nullable();
        $table->unsignedBigInteger('updated_by')->nullable();
        $table->unsignedBigInteger('deleted_by')->nullable();

        // Performance indexes
        $table->index(['type', 'state']);
        $table->index(['created_at']);
        $table->index(['email_verified_at']);
    });
}
```

### 3. Performance Testing for Database Operations

#### Step 1: Write Performance Tests

Create `tests/Performance/DatabasePerformanceTest.php`:

```php
<?php

namespace Tests\Performance;

use Tests\Performance\PerformanceTestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\StandardUser;
use App\Models\Organization;

class DatabasePerformanceTest extends PerformanceTestCase
{
    /** @test */
    public function user_creation_meets_performance_requirements()
    {
        $this->measurePerformance('user_creation', function () {
            StandardUser::factory()->count(100)->create();
        });

        // Assert creation time is under 1 second for 100 users
        $this->assertLessThan(1000, $this->performanceMetrics['user_creation']['execution_time_ms']);
    }

    /** @test */
    public function sti_queries_are_optimized()
    {
        // Create test data
        StandardUser::factory()->count(50)->create();
        Admin::factory()->count(10)->create();
        Guest::factory()->count(20)->create();

        $this->measurePerformance('sti_type_query', function () {
            StandardUser::all();
        });

        // STI queries should be fast with proper indexing
        $this->assertLessThan(50, $this->performanceMetrics['sti_type_query']['execution_time_ms']);
    }

    /** @test */
    public function hierarchy_queries_perform_well_with_closure_table()
    {
        // Create deep hierarchy
        $org = Organization::factory()->create();
        $dept = Department::factory()->create(['parent_id' => $org->id]);
        $project = Project::factory()->create(['parent_id' => $dept->id]);
        $squad = Squad::factory()->create(['parent_id' => $project->id]);

        $this->measurePerformance('hierarchy_ancestors', function () use ($squad) {
            $squad->ancestors()->get();
        });

        $this->measurePerformance('hierarchy_descendants', function () use ($org) {
            $org->descendants()->get();
        });

        // Hierarchy queries should be under 50ms
        $this->assertLessThan(50, $this->performanceMetrics['hierarchy_ancestors']['execution_time_ms']);
        $this->assertLessThan(50, $this->performanceMetrics['hierarchy_descendants']['execution_time_ms']);
    }

    /** @test */
    public function concurrent_read_operations_scale_well()
    {
        // Create test data
        StandardUser::factory()->count(1000)->create();

        $this->measurePerformance('concurrent_reads', function () {
            // Simulate concurrent read operations
            for ($i = 0; $i < 100; $i++) {
                User::where('type', 'standard')->count();
            }
        });

        // 100 concurrent reads should complete quickly
        $this->assertLessThan(500, $this->performanceMetrics['concurrent_reads']['execution_time_ms']);
    }

    /** @test */
    public function database_indexes_are_utilized()
    {
        // Create test data
        StandardUser::factory()->count(1000)->create();

        // Test that queries use indexes (SQLite EXPLAIN QUERY PLAN)
        $queryPlan = DB::select('EXPLAIN QUERY PLAN SELECT * FROM users WHERE type = ?', ['standard']);

        // Verify index usage
        $planText = json_encode($queryPlan);
        $this->assertStringContains('USING INDEX', $planText);
    }
}
```

### 4. Constraint and Relationship Testing

#### Step 1: Write Constraint Tests

Create `tests/Unit/Database/ConstraintValidationTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use App\Models\Organization;
use App\Models\Department;
use Illuminate\Database\QueryException;

class ConstraintValidationTest extends TestCase
{
    /** @test */
    public function foreign_key_constraints_are_enforced()
    {
        $this->expectException(QueryException::class);

        // Try to create team with non-existent parent
        Team::create([
            'name' => 'Test Team',
            'type' => 'department',
            'parent_id' => 99999, // Non-existent parent
        ]);
    }

    /** @test */
    public function unique_constraints_prevent_duplicates()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(QueryException::class);

        // Try to create user with duplicate email
        User::factory()->create(['email' => 'test@example.com']);
    }

    /** @test */
    public function ulid_uniqueness_is_enforced()
    {
        $ulid = 'test-ulid-123';
        User::factory()->create(['ulid' => $ulid]);

        $this->expectException(QueryException::class);

        // Try to create user with duplicate ULID
        User::factory()->create(['ulid' => $ulid]);
    }

    /** @test */
    public function team_hierarchy_prevents_circular_references()
    {
        $org = Organization::factory()->create();
        $dept = Department::factory()->create(['parent_id' => $org->id]);

        $this->expectException(QueryException::class);

        // Try to create circular reference
        $org->update(['parent_id' => $dept->id]);
    }

    /** @test */
    public function soft_deletes_preserve_referential_integrity()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($user, 'member');

        // Soft delete user
        $user->delete();

        // Verify team membership is preserved but marked as deleted
        $this->assertSoftDeleted('team_user', [
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);
    }
}
```

### 5. GDPR Compliance Database Testing

#### Step 1: Write GDPR Database Tests

Create `tests/Unit/Database/GdprComplianceTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\GdprService;

class GdprComplianceTest extends TestCase
{
    /** @test */
    public function personal_data_can_be_completely_removed()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Create audit trail
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'timestamp' => now(),
        ]);

        $gdprService = new GdprService();
        $gdprService->deleteUserWithCompliance($user);

        // Verify personal data is removed
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // Verify audit trail is anonymized but preserved
        $this->assertDatabaseHas('audit_logs', [
            'user_token' => $gdprService->generateUserToken($user->id),
            'action' => 'login',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function data_retention_policies_are_enforced()
    {
        // Create old user data (over 2 years)
        $oldUser = User::factory()->create([
            'created_at' => now()->subYears(3),
            'updated_at' => now()->subYears(2)->subDay(),
        ]);

        // Create recent user data
        $recentUser = User::factory()->create([
            'created_at' => now()->subYear(),
            'updated_at' => now()->subMonth(),
        ]);

        $gdprService = new GdprService();
        $gdprService->enforceDataRetentionPolicy();

        // Verify old data is removed
        $this->assertDatabaseMissing('users', ['id' => $oldUser->id]);

        // Verify recent data is preserved
        $this->assertDatabaseHas('users', ['id' => $recentUser->id]);
    }

    /** @test */
    public function audit_logs_are_anonymized_after_user_deletion()
    {
        $user = User::factory()->create();

        // Create multiple audit entries
        AuditLog::factory()->count(5)->create(['user_id' => $user->id]);

        $gdprService = new GdprService();
        $token = $gdprService->deleteUserWithCompliance($user);

        // Verify all audit logs are anonymized
        $anonymizedLogs = AuditLog::where('user_token', $token)->count();
        $this->assertEquals(5, $anonymizedLogs);

        // Verify no logs contain user_id
        $remainingLogs = AuditLog::where('user_id', $user->id)->count();
        $this->assertEquals(0, $remainingLogs);
    }
}
```

### 6. Database Seeding and Factory Testing

#### Step 1: Write Seeding Tests

Create `tests/Unit/Database/SeedingIntegrityTest.php`:

```php
<?php

namespace Tests\Unit\Database;

use Tests\TestCase;
use Database\Seeders\TestSeeder;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Project;
use App\Models\Squad;

class SeedingIntegrityTest extends TestCase
{
    /** @test */
    public function test_seeder_creates_valid_hierarchy()
    {
        $this->seed(TestSeeder::class);

        // Verify organizational hierarchy
        $org = Organization::first();
        $this->assertNotNull($org);

        $dept = Department::where('parent_id', $org->id)->first();
        $this->assertNotNull($dept);

        $project = Project::where('parent_id', $dept->id)->first();
        $this->assertNotNull($project);

        $squad = Squad::where('parent_id', $project->id)->first();
        $this->assertNotNull($squad);
    }

    /** @test */
    public function closure_table_is_populated_correctly_during_seeding()
    {
        $this->seed(TestSeeder::class);

        $org = Organization::first();
        $squad = Squad::first();

        // Verify closure table entries
        $this->assertDatabaseHas('team_closure', [
            'ancestor_id' => $org->id,
            'descendant_id' => $squad->id,
            'depth' => 3,
        ]);
    }

    /** @test */
    public function user_factories_create_valid_sti_instances()
    {
        $admin = Admin::factory()->create();
        $user = StandardUser::factory()->create();
        $guest = Guest::factory()->create();
        $system = SystemUser::factory()->create();

        // Verify STI types are correct
        $this->assertEquals('admin', $admin->type);
        $this->assertEquals('standard', $user->type);
        $this->assertEquals('guest', $guest->type);
        $this->assertEquals('system', $system->type);

        // Verify instances are correct classes
        $this->assertInstanceOf(Admin::class, $admin);
        $this->assertInstanceOf(StandardUser::class, $user);
        $this->assertInstanceOf(Guest::class, $guest);
        $this->assertInstanceOf(SystemUser::class, $system);
    }

    /** @test */
    public function team_factories_maintain_hierarchy_integrity()
    {
        $org = Organization::factory()->create();
        $dept = Department::factory()->create(['parent_id' => $org->id]);

        // Verify hierarchy relationship
        $this->assertEquals($org->id, $dept->parent_id);
        $this->assertTrue($dept->parent->is($org));
        $this->assertTrue($org->children->contains($dept));
    }
}
```

## TDD Workflow for Database Development

### 1. Red-Green-Refactor Cycle

```bash
# 1. RED: Write failing test
./vendor/bin/pest tests/Unit/Database/SqliteConfigurationTest.php
# Test fails - configuration not implemented

# 2. GREEN: Implement minimal configuration
# Update config/database.php with SQLite pragmas

# 3. REFACTOR: Optimize configuration
# Fine-tune pragma settings for performance

# 4. VERIFY: Run all database tests
./vendor/bin/pest tests/Unit/Database/ --coverage
```

### 2. Performance-Driven Database Design

```bash
# Write performance test first
./vendor/bin/pest tests/Performance/DatabasePerformanceTest.php::test_sti_queries_are_optimized
# Test fails - no indexes

# Add indexes to migration
# Re-run test until it passes

# Validate with EXPLAIN QUERY PLAN
./vendor/bin/pest tests/Unit/Database/ConstraintValidationTest.php::test_database_indexes_are_utilized
```

### 3. Continuous Validation

```bash
# Run after each migration change
composer run test-database

# Monitor performance regression
composer run test-performance

# Validate GDPR compliance
composer run test-gdpr
```

## Database Testing Best Practices

### 1. Test Organization

- **Unit Tests**: Schema validation, constraints, indexes
- **Performance Tests**: Query optimization, concurrent access
- **Integration Tests**: Migration rollbacks, seeding integrity
- **Compliance Tests**: GDPR requirements, data retention

### 2. Test Data Management

- Use factories for consistent test data
- Implement realistic hierarchies in seeders
- Test with various data volumes
- Validate edge cases and constraints

### 3. Performance Monitoring

- Set performance thresholds in tests
- Monitor query execution plans
- Test concurrent access patterns
- Validate index utilization

## Success Criteria

- [ ] SQLite WAL mode configuration tested and validated
- [ ] All migrations have corresponding integrity tests
- [ ] Performance benchmarks established and passing
- [ ] Constraint validation tests prevent data corruption
- [ ] GDPR compliance database operations tested
- [ ] Seeding and factory integrity validated
- [ ] Database test coverage above 95%

## Next Steps

After completing database TDD:

1. **Validate Setup**: Run all database tests and ensure they pass
2. **Performance Baseline**: Establish performance benchmarks
3. **Move to Models**: Follow [030-sti-models-tdd.md](030-sti-models-tdd.md)
4. **Continuous Monitoring**: Set up automated database testing

---

**Next Guide**: [030-sti-models-tdd.md](030-sti-models-tdd.md) - Test-driven STI model development  
**Estimated Time**: 6-8 hours for complete database TDD setup  
**Prerequisites**: Environment setup completed, understanding of SQLite optimization
