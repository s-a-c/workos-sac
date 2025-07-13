# 2.3 STI Migration Strategy and Database Schema Design

## 2.3.1 Executive Summary

This document provides comprehensive guidance for designing and implementing database migrations optimized for Single Table Inheritance (STI) in the UMS-STI system. The strategy focuses on SQLite optimization, complex relationship patterns, index optimization, and migration patterns that support the full user and team hierarchy while maintaining performance and data integrity.

## 2.3.2 Learning Objectives

After completing this guide, you will:
- Design STI-optimized database schemas for users and teams
- Implement migration patterns for complex relationships
- Optimize database structure for SQLite performance
- Handle polymorphic relationships within STI architecture
- Establish migration testing and validation strategies

## 2.3.3 Prerequisite Knowledge

- **Laravel 12.x** migration system
- **SQLite 3.45+** features and optimization
- Understanding of Single Table Inheritance concepts
- Database indexing and performance principles
- Laravel Eloquent relationships

## 2.3.4 STI Schema Design Principles

### 2.3.4.1 Core Design Principles

1. **Single Table per Entity Type** - Users and Teams each use one table
2. **Type Discrimination** - Clear type column for inheritance hierarchy
3. **Nullable Columns** - Type-specific columns must be nullable
4. **Index Optimization** - Strategic indexing for type-based queries
5. **Relationship Integrity** - Foreign key constraints where appropriate

### 2.3.4.2 Schema Architecture Overview

```sql
-- Core STI Tables
users (STI: StandardUser, AdminUser, GuestUser, SystemUser)
teams (STI: Organization, Department, Project, Squad)
team_hierarchies (Closure Table)
team_memberships (User-Team relationships)

-- Permission Tables (Spatie)
roles, permissions, model_has_roles, model_has_permissions

-- Event Sourcing Tables (Separate Database)
stored_events, stored_snapshots
```

## 2.3.5 User Table STI Migration

### 2.3.5.1 Primary Users Migration

```php
<?php
// database/migrations/2024_01_01_000001_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Primary Keys and Identifiers
            $table->id();
            $table->string('public_id', 36)->unique(); // UUID/ULID/Snowflake
            
            // STI Discrimination
            $table->string('type', 50)->index(); // StandardUser, AdminUser, etc.
            
            // Core User Fields (All Types)
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // State Management
            $table->string('state', 50)->default('pending')->index();
            $table->json('state_history')->nullable();
            
            // Common Optional Fields
            $table->string('phone')->nullable();
            $table->string('timezone', 50)->default('UTC');
            $table->string('locale', 10)->default('en');
            $table->json('preferences')->nullable();
            
            // Type-Specific Fields (Nullable for STI)
            
            // StandardUser specific
            $table->string('department')->nullable();
            $table->string('job_title')->nullable();
            $table->date('hire_date')->nullable();
            
            // AdminUser specific
            $table->json('admin_permissions')->nullable();
            $table->timestamp('last_admin_action')->nullable();
            $table->boolean('super_admin')->default(false);
            
            // GuestUser specific
            $table->timestamp('guest_expires_at')->nullable();
            $table->string('guest_token', 100)->nullable()->unique();
            $table->json('guest_restrictions')->nullable();
            
            // SystemUser specific
            $table->string('system_role')->nullable();
            $table->json('system_config')->nullable();
            $table->boolean('automated')->default(false);
            
            // Audit and Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->integer('login_count')->default(0);
            
            // GDPR and Compliance
            $table->boolean('gdpr_consent')->default(false);
            $table->timestamp('gdpr_consent_at')->nullable();
            $table->timestamp('data_retention_until')->nullable();
            $table->string('anonymization_token', 100)->nullable()->unique();
            
            // Standard Laravel Fields
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for Performance
            $table->index(['type', 'state']);
            $table->index(['type', 'created_at']);
            $table->index(['email', 'type']);
            $table->index('state');
            $table->index('last_login_at');
            $table->index('data_retention_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### 2.3.5.2 User Profile Extensions Migration

```php
<?php
// database/migrations/2024_01_01_000002_create_user_profiles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Extended Profile Information
            $table->string('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->json('social_links')->nullable();
            $table->json('skills')->nullable();
            $table->json('certifications')->nullable();
            
            // Contact Information
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();
            
            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
```

## 2.3.6 Team Table STI Migration

### 2.3.6.1 Primary Teams Migration

```php
<?php
// database/migrations/2024_01_01_000003_create_teams_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            // Primary Keys and Identifiers
            $table->id();
            $table->string('public_id', 36)->unique(); // UUID/ULID/Snowflake
            
            // STI Discrimination
            $table->string('type', 50)->index(); // Organization, Department, etc.
            
            // Core Team Fields (All Types)
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // State Management
            $table->string('state', 50)->default('active')->index();
            $table->json('state_history')->nullable();
            
            // Common Fields
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->json('settings')->nullable();
            
            // Type-Specific Fields (Nullable for STI)
            
            // Organization specific
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->json('legal_addresses')->nullable();
            
            // Department specific
            $table->string('department_code', 20)->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('cost_center', 50)->nullable();
            $table->foreignId('head_user_id')->nullable()->constrained('users');
            
            // Project specific
            $table->date('project_start_date')->nullable();
            $table->date('project_end_date')->nullable();
            $table->string('project_status', 50)->nullable();
            $table->decimal('project_budget', 15, 2)->nullable();
            $table->foreignId('project_manager_id')->nullable()->constrained('users');
            
            // Squad specific
            $table->string('squad_methodology', 50)->nullable(); // agile, scrum, etc.
            $table->integer('sprint_length')->nullable();
            $table->json('squad_tools')->nullable();
            $table->foreignId('scrum_master_id')->nullable()->constrained('users');
            
            // Hierarchy and Relationships
            $table->foreignId('parent_team_id')->nullable()->constrained('teams');
            $table->integer('hierarchy_level')->default(0)->index();
            $table->string('hierarchy_path', 500)->nullable()->index();
            
            // Audit and Tracking
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for Performance
            $table->index(['type', 'state']);
            $table->index(['type', 'hierarchy_level']);
            $table->index(['parent_team_id', 'type']);
            $table->index('hierarchy_path');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

### 2.3.6.2 Team Hierarchies Closure Table Migration

```php
<?php
// database/migrations/2024_01_01_000004_create_team_hierarchies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_hierarchies', function (Blueprint $table) {
            $table->id();
            
            // Closure Table Fields
            $table->foreignId('ancestor_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('descendant_id')->constrained('teams')->onDelete('cascade');
            $table->integer('depth')->default(0);
            
            // Additional Metadata
            $table->string('path_type', 50)->nullable(); // direct, inherited, etc.
            $table->json('path_metadata')->nullable();
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicates
            $table->unique(['ancestor_id', 'descendant_id']);
            
            // Performance indexes
            $table->index(['ancestor_id', 'depth']);
            $table->index(['descendant_id', 'depth']);
            $table->index('depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_hierarchies');
    }
};
```

## 2.3.7 Relationship Tables

### 2.3.7.1 Team Memberships Migration

```php
<?php
// database/migrations/2024_01_01_000005_create_team_memberships_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            
            // Core Relationship
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            
            // Membership Details
            $table->string('role', 100); // member, lead, admin, etc.
            $table->string('status', 50)->default('active'); // active, inactive, pending
            
            // Dates and Duration
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_primary')->default(false); // Primary team for user
            
            // Permissions and Access
            $table->json('permissions')->nullable();
            $table->json('restrictions')->nullable();
            
            // Audit
            $table->foreignId('added_by')->nullable()->constrained('users');
            $table->foreignId('removed_by')->nullable()->constrained('users');
            
            $table->timestamps();
            
            // Constraints and Indexes
            $table->unique(['user_id', 'team_id']); // One membership per user per team
            $table->index(['user_id', 'status']);
            $table->index(['team_id', 'role']);
            $table->index(['user_id', 'is_primary']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
    }
};
```

## 2.3.8 Migration Testing Strategy

### 2.3.8.1 Migration Test Suite

```php
<?php
// tests/Feature/MigrationTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates users table with correct structure', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    
    // Check STI columns
    expect(Schema::hasColumn('users', 'type'))->toBeTrue();
    expect(Schema::hasColumn('users', 'public_id'))->toBeTrue();
    
    // Check type-specific nullable columns
    expect(Schema::hasColumn('users', 'department'))->toBeTrue();
    expect(Schema::hasColumn('users', 'admin_permissions'))->toBeTrue();
    expect(Schema::hasColumn('users', 'guest_expires_at'))->toBeTrue();
    expect(Schema::hasColumn('users', 'system_role'))->toBeTrue();
});

it('creates teams table with correct structure', function () {
    expect(Schema::hasTable('teams'))->toBeTrue();
    
    // Check STI columns
    expect(Schema::hasColumn('teams', 'type'))->toBeTrue();
    expect(Schema::hasColumn('teams', 'public_id'))->toBeTrue();
    
    // Check hierarchy columns
    expect(Schema::hasColumn('teams', 'parent_team_id'))->toBeTrue();
    expect(Schema::hasColumn('teams', 'hierarchy_level'))->toBeTrue();
    expect(Schema::hasColumn('teams', 'hierarchy_path'))->toBeTrue();
});

it('creates closure table for team hierarchies', function () {
    expect(Schema::hasTable('team_hierarchies'))->toBeTrue();
    
    expect(Schema::hasColumn('team_hierarchies', 'ancestor_id'))->toBeTrue();
    expect(Schema::hasColumn('team_hierarchies', 'descendant_id'))->toBeTrue();
    expect(Schema::hasColumn('team_hierarchies', 'depth'))->toBeTrue();
});
```

### 2.3.8.2 Data Integrity Tests

```php
<?php
// tests/Feature/DatabaseIntegrityTest.php

use App\Models\Team\Organization;
use App\Models\Team\Department;
use App\Models\User\StandardUser;

it('maintains referential integrity for team hierarchies', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create(['parent_team_id' => $org->id]);
    
    // Test cascade deletion
    $org->delete();
    
    expect(Department::find($dept->id))->toBeNull();
});

it('enforces unique constraints correctly', function () {
    $user = StandardUser::factory()->create(['email' => 'test@example.com']);
    
    expect(fn() => StandardUser::factory()->create(['email' => 'test@example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

## 2.3.9 Performance Optimization

### 2.3.9.1 Index Strategy

```sql
-- Critical indexes for STI queries
CREATE INDEX idx_users_type_state ON users(type, state);
CREATE INDEX idx_users_type_created ON users(type, created_at);
CREATE INDEX idx_teams_type_hierarchy ON teams(type, hierarchy_level);

-- Closure table optimization
CREATE INDEX idx_hierarchies_ancestor_depth ON team_hierarchies(ancestor_id, depth);
CREATE INDEX idx_hierarchies_descendant_depth ON team_hierarchies(descendant_id, depth);

-- Membership queries
CREATE INDEX idx_memberships_user_status ON team_memberships(user_id, status);
CREATE INDEX idx_memberships_team_role ON team_memberships(team_id, role);
```

### 2.3.9.2 SQLite Optimization

```php
// config/database.php - SQLite optimization for migrations
'sqlite' => [
    'driver' => 'sqlite',
    'database' => database_path('database.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'pragmas' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000, // 64MB
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB
    ],
],
```

## 2.3.10 Migration Rollback Strategy

### 2.3.10.1 Safe Rollback Procedures

```php
// Migration rollback with data preservation
public function down(): void
{
    // Create backup table before dropping
    DB::statement('CREATE TABLE users_backup AS SELECT * FROM users');
    
    Schema::dropIfExists('users');
    
    // Log rollback for audit
    Log::info('Users table rolled back', [
        'backup_table' => 'users_backup',
        'timestamp' => now(),
    ]);
}
```

## 2.3.11 Next Steps

This migration strategy provides the foundation for:

1. **Indexing Performance** - Strategic indexing for STI and closure tables
2. **Database Testing** - Validation of schema integrity
3. **User Models** - STI implementation with optimized schema
4. **Team Hierarchy** - Closure table implementation

## 2.3.12 References

- [Laravel 12.x Migrations](https://laravel.com/docs/12.x/migrations)
- [SQLite Optimization Guide](https://www.sqlite.org/optoverview.html)
- [Single Table Inheritance Patterns](https://martinfowler.com/eaaCatalog/singleTableInheritance.html)
- [Closure Table Pattern](https://www.slideshare.net/billkarwin/models-for-hierarchical-data)
