# 2.0 Laravel Fortify 2FA Implementation - Migration Implementation Guide

**Document Version**: 2.0  
**Last Updated**: 2025-07-01  
**Target Audience**: Junior Developers  
**Estimated Reading Time**: 22 minutes

## 2.1 Executive Summary

This document provides comprehensive migration strategy and implementation for transitioning from Filament's built-in 2FA to Laravel Fortify as the unified authentication system. The approach ensures zero data loss while leveraging the existing pragmarx/google2fa-laravel package (v2.3.0) for seamless migration of existing user configurations to the unified Fortify system.

### 2.1.1 Migration Strategy Overview

| Migration Phase | Description | Risk Level | Data Safety | Estimated Time |
|----------------|-------------|------------|-------------|----------------|
| **Phase 1** | Schema Extension | 🟢 Low | ✅ Full preservation | 5 minutes |
| **Phase 2** | Data Migration | 🟡 Medium | ✅ Validated migration | 10 minutes |
| **Phase 3** | System Activation | 🟡 Medium | ✅ Rollback ready | 15 minutes |
| **Phase 4** | Validation & Cleanup | 🟢 Low | ✅ Backup preserved | 5 minutes |

## 2.2 Gap Analysis and Requirements

### 2.2.1 Current System Assessment

**Current Environment Status**:
- ✅ Laravel Framework 12.19.3
- ✅ PHP 8.4.x
- ✅ Filament 4.0.0-beta11
- ✅ pragmarx/google2fa-laravel 2.3.0 (existing 2FA foundation)
- ✅ Livewire/Flux 2.2.1 + Volt 1.7.1

**Existing Filament 2FA Infrastructure**:

```sql
-- Current user table 2FA fields
app_authentication_secret TEXT ENCRYPTED,
app_authentication_recovery_codes TEXT ENCRYPTED,
has_email_authentication BOOLEAN DEFAULT FALSE,
```

**Current Field Analysis**:

| Field | Data Type | Encryption | Usage Pattern | Migration Priority |
|-------|-----------|------------|---------------|-------------------|
| `app_authentication_secret` | TEXT | ✅ Encrypted | TOTP secret storage | 🔴 Critical |
| `app_authentication_recovery_codes` | TEXT | ✅ Encrypted array | Recovery codes | 🔴 Critical |
| `has_email_authentication` | BOOLEAN | ❌ Plain | Email 2FA flag | 🟡 Medium |

### 2.2.2 Target Fortify Requirements

**Required Fortify 2FA Fields**:

```sql
-- Primary Fortify 2FA fields (target system)
two_factor_secret TEXT NULL,
two_factor_recovery_codes TEXT NULL,
two_factor_confirmed_at TIMESTAMP NULL,
```

**Field Mapping Strategy**:

| Source (Filament) | Target (Fortify) | Migration Logic | Validation |
|------------------|------------------|-----------------|------------|
| `app_authentication_secret` | `two_factor_secret` | Direct encrypted copy | Decrypt/re-encrypt test |
| `app_authentication_recovery_codes` | `two_factor_recovery_codes` | Direct encrypted copy | Array format validation |
| `has_email_authentication` | N/A | Preserve for transition | Boolean consistency |
| N/A | `two_factor_confirmed_at` | Set to `now()` if secret exists | Timestamp validation |

### 2.2.3 Package Dependencies

**Required Package Installations**:

```bash
# Laravel 12.x compatible Fortify installation
composer require laravel/fortify "^1.25"
composer require laravel/sanctum "^4.0"

# Verify existing packages remain compatible
composer show livewire/flux livewire/flux-pro livewire/volt
```

**Dependency Compatibility Matrix**:

| Package | Current | Required | Status | Notes |
|---------|---------|----------|--------|-------|
| **Laravel Fortify** | Not installed | ^1.25 | ✅ Install | Primary authentication |
| **Laravel Sanctum** | Not installed | ^4.0 | ✅ Install | Required by Fortify |
| **Google2FA** | Via Filament | ^8.0 | ✅ Shared | Already available |
| **QR Code Generator** | Via Filament | ^5.0 | ✅ Shared | Already available |

## 2.3 Database Migration Implementation

### 2.3.1 Phase 1: Schema Extension Migration

**Migration File**: `database/migrations/2025_07_01_120000_add_fortify_two_factor_fields_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add Laravel Fortify 2FA columns
            $table->text('two_factor_secret')
                  ->nullable()
                  ->after('remember_token')
                  ->comment('Encrypted TOTP secret for Laravel Fortify 2FA');
                  
            $table->text('two_factor_recovery_codes')
                  ->nullable()
                  ->after('two_factor_secret')
                  ->comment('Encrypted recovery codes for Laravel Fortify 2FA');
                  
            $table->timestamp('two_factor_confirmed_at')
                  ->nullable()
                  ->after('two_factor_recovery_codes')
                  ->comment('Timestamp when Fortify 2FA was confirmed and activated');
        });
        
        Log::info('Fortify 2FA schema extension completed', [
            'timestamp' => now(),
            'migration' => class_basename(static::class)
        ]);
    }

    public function down(): void
    {
        // Safety check: Prevent rollback if Fortify data exists
        $fortifyUsersCount = DB::table('users')
            ->whereNotNull('two_factor_secret')
            ->orWhereNotNull('two_factor_confirmed_at')
            ->count();
        
        if ($fortifyUsersCount > 0) {
            throw new Exception(
                "Cannot rollback: {$fortifyUsersCount} users have Fortify 2FA data. " .
                "Please migrate data back to Filament fields before rolling back."
            );
        }
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_confirmed_at',
                'two_factor_recovery_codes', 
                'two_factor_secret'
            ]);
        });
    }
};
```

### 2.3.2 Phase 2: Data Migration Implementation

**Migration File**: `database/migrations/2025_07_01_120001_migrate_filament_to_fortify_2fa_data.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $migratedCount = 0;
        $errorCount = 0;
        
        // Process users in chunks for large datasets
        DB::table('users')
            ->whereNotNull('app_authentication_secret')
            ->chunk(100, function ($users) use (&$migratedCount, &$errorCount) {
                foreach ($users as $user) {
                    try {
                        // Validate source data
                        if (empty($user->app_authentication_secret)) {
                            continue;
                        }
                        
                        // Prepare migration data
                        $migrationData = [
                            'two_factor_secret' => $user->app_authentication_secret,
                            'two_factor_recovery_codes' => $user->app_authentication_recovery_codes,
                            'two_factor_confirmed_at' => now(), // Mark as confirmed
                            'updated_at' => now(),
                        ];
                        
                        // Execute migration
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update($migrationData);
                        
                        $migratedCount++;
                        
                    } catch (Exception $e) {
                        $errorCount++;
                        Log::error('Failed to migrate user 2FA data', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
        
        Log::info('Filament to Fortify 2FA data migration completed', [
            'migrated_users' => $migratedCount,
            'errors' => $errorCount,
            'timestamp' => now()
        ]);
        
        if ($errorCount > 0) {
            throw new Exception("Migration completed with {$errorCount} errors. Check logs for details.");
        }
    }

    public function down(): void
    {
        $restoredCount = 0;
        
        // Restore Filament data from Fortify fields if needed
        DB::table('users')
            ->whereNotNull('two_factor_secret')
            ->whereNull('app_authentication_secret')
            ->chunk(100, function ($users) use (&$restoredCount) {
                foreach ($users as $user) {
                    try {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update([
                                'app_authentication_secret' => $user->two_factor_secret,
                                'app_authentication_recovery_codes' => $user->two_factor_recovery_codes,
                                'has_email_authentication' => !is_null($user->two_factor_confirmed_at),
                                'updated_at' => now(),
                            ]);
                        
                        $restoredCount++;
                        
                    } catch (Exception $e) {
                        Log::error('Failed to restore user 2FA data', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
        
        Log::info('Fortify to Filament 2FA data restoration completed', [
            'restored_users' => $restoredCount,
            'timestamp' => now()
        ]);
    }
};
```

### 2.3.3 Phase 3: Performance Optimization

**Migration File**: `database/migrations/2025_07_01_120002_add_fortify_2fa_indexes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Performance indexes for Fortify 2FA queries
            $table->index('two_factor_confirmed_at', 'users_fortify_2fa_confirmed_index');
            $table->index(['email', 'two_factor_confirmed_at'], 'users_email_fortify_2fa_index');
            $table->index(['two_factor_secret', 'two_factor_confirmed_at'], 'users_fortify_2fa_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_fortify_2fa_status_index');
            $table->dropIndex('users_email_fortify_2fa_index');
            $table->dropIndex('users_fortify_2fa_confirmed_index');
        });
    }
};
```

## 2.4 Migration Execution Procedures

### 2.4.1 Pre-Migration Checklist

**Essential Preparation Steps**:

```bash
# 1. Create database backup
cp database/database.sqlite database/database.sqlite.backup.$(date +%Y%m%d_%H%M%S)

# 2. Verify current system state
php artisan tinker
>>> User::whereNotNull('app_authentication_secret')->count()
>>> Schema::hasColumn('users', 'app_authentication_secret')

# 3. Install required packages
composer require laravel/fortify "^1.25"
composer require laravel/sanctum "^4.0"

# 4. Install Fortify and Sanctum
composer require laravel/fortify "^1.27"
php artisan install:api  # Installs Sanctum
php artisan fortify:install  # Publishes Fortify resources
```

### 2.4.2 Migration Execution Sequence

**Step-by-Step Migration Process**:

```bash
# Step 1: Generate migration files
php artisan make:migration add_fortify_two_factor_fields_to_users_table --table=users
php artisan make:migration migrate_filament_to_fortify_2fa_data
php artisan make:migration add_fortify_2fa_indexes_to_users_table --table=users

# Step 2: Execute migrations in sequence
php artisan migrate --step

# Step 3: Verify migration success
php artisan migrate:status

# Step 4: Test data integrity
php artisan tinker
>>> User::whereNotNull('two_factor_secret')->count()
>>> User::whereNotNull('two_factor_confirmed_at')->count()
```

## 2.5 Data Validation and Integrity

### 2.5.1 Migration Validation Command

**Artisan Command**: `app/Console/Commands/ValidateFortifyMigration.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidateFortifyMigration extends Command
{
    protected $signature = 'fortify:validate-migration';
    protected $description = 'Validate Fortify 2FA migration integrity and data consistency';

    public function handle(): int
    {
        $this->info('🔍 Validating Fortify 2FA migration...');
        
        $results = [
            'schema' => $this->validateSchema(),
            'data_migration' => $this->validateDataMigration(),
            'data_integrity' => $this->validateDataIntegrity(),
            'performance' => $this->validatePerformance(),
        ];
        
        $allPassed = collect($results)->every(fn($result) => $result);
        
        if ($allPassed) {
            $this->info('✅ All validation checks passed successfully!');
            return 0;
        } else {
            $this->error('❌ Some validation checks failed.');
            return 1;
        }
    }
    
    private function validateSchema(): bool
    {
        $this->line('📋 Checking schema...');
        
        $requiredColumns = ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'];
        $existingColumns = Schema::getColumnListing('users');
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $existingColumns)) {
                $this->error("  ❌ Missing Fortify column: {$column}");
                return false;
            }
            $this->line("  ✅ Fortify column exists: {$column}");
        }
        
        return true;
    }
    
    private function validateDataMigration(): bool
    {
        $this->line('📊 Checking data migration...');
        
        $filamentUsers = DB::table('users')->whereNotNull('app_authentication_secret')->count();
        $fortifyUsers = DB::table('users')->whereNotNull('two_factor_secret')->count();
        
        $this->line("  📈 Filament 2FA users: {$filamentUsers}");
        $this->line("  📈 Fortify 2FA users: {$fortifyUsers}");
        
        if ($filamentUsers > 0 && $fortifyUsers === 0) {
            $this->error("  ❌ Migration incomplete: No Fortify users found");
            return false;
        }
        
        $this->line("  ✅ Migration data validated");
        return true;
    }
    
    private function validateDataIntegrity(): bool
    {
        $this->line('🔒 Checking data integrity...');
        
        // Check for orphaned data
        $orphanedConfirmations = DB::table('users')
            ->whereNotNull('two_factor_confirmed_at')
            ->whereNull('two_factor_secret')
            ->count();
            
        if ($orphanedConfirmations > 0) {
            $this->error("  ❌ Found {$orphanedConfirmations} orphaned confirmation timestamps");
            return false;
        }
        
        $this->line("  ✅ Data integrity validated");
        return true;
    }
    
    private function validatePerformance(): bool
    {
        $this->line('⚡ Checking performance...');
        
        $start = microtime(true);
        DB::table('users')->where('two_factor_confirmed_at', '!=', null)->count();
        $duration = (microtime(true) - $start) * 1000;
        
        if ($duration > 100) {
            $this->warn("  ⚠️  Query performance: {$duration}ms");
        } else {
            $this->line("  ✅ Query performance: {$duration}ms");
        }
        
        return true;
    }
}
```

### 2.5.2 User Model Migration Support

**Enhanced User Model Methods**:

```php
// Add to User model for migration support
public function validateFortifyMigration(): array
{
    return [
        'has_filament_2fa' => !is_null($this->app_authentication_secret),
        'has_fortify_2fa' => !is_null($this->two_factor_secret),
        'fortify_confirmed' => !is_null($this->two_factor_confirmed_at),
        'migration_needed' => !is_null($this->app_authentication_secret) && is_null($this->two_factor_secret),
        'data_consistent' => $this->validateDataConsistency(),
    ];
}

public function migrateToFortify2FA(): bool
{
    if (is_null($this->app_authentication_secret)) {
        return false; // No Filament 2FA to migrate
    }
    
    if (!is_null($this->two_factor_secret)) {
        return true; // Already migrated
    }
    
    try {
        $this->forceFill([
            'two_factor_secret' => $this->app_authentication_secret,
            'two_factor_recovery_codes' => $this->app_authentication_recovery_codes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        
        return true;
    } catch (Exception $e) {
        Log::error('Failed to migrate user 2FA data', [
            'user_id' => $this->id,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
}
```

## 2.6 Rollback Strategy and Safety

### 2.6.1 Comprehensive Rollback Procedures

**Rollback Command**: `app/Console/Commands/RollbackFortifyMigration.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RollbackFortifyMigration extends Command
{
    protected $signature = 'fortify:rollback-migration {--force : Force rollback without confirmation}';
    protected $description = 'Safely rollback Fortify 2FA migration to Filament system';

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will rollback Fortify 2FA migration. Are you sure?')) {
                return 0;
            }
        }
        
        $this->info('🔄 Starting Fortify migration rollback...');
        
        // Validate rollback safety
        if (!$this->validateRollbackSafety()) {
            return 1;
        }
        
        // Restore Filament data
        $this->restoreFilamentData();
        
        // Clear Fortify data
        $this->clearFortifyData();
        
        // Run database rollback
        $this->call('migrate:rollback', ['--step' => 3]);
        
        $this->info('✅ Fortify migration rollback completed!');
        return 0;
    }
    
    private function validateRollbackSafety(): bool
    {
        $activeUsers = DB::table('users')
            ->whereNotNull('two_factor_confirmed_at')
            ->where('two_factor_confirmed_at', '>', now()->subDays(30))
            ->count();
        
        if ($activeUsers > 0) {
            $this->error("❌ Cannot rollback: {$activeUsers} users have recent Fortify 2FA activity");
            return false;
        }
        
        return true;
    }
    
    private function restoreFilamentData(): void
    {
        $restoredCount = 0;
        
        DB::table('users')
            ->whereNotNull('two_factor_secret')
            ->chunk(100, function ($users) use (&$restoredCount) {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'app_authentication_secret' => $user->two_factor_secret,
                            'app_authentication_recovery_codes' => $user->two_factor_recovery_codes,
                            'has_email_authentication' => !is_null($user->two_factor_confirmed_at),
                        ]);
                    
                    $restoredCount++;
                }
            });
        
        $this->line("📊 Restored Filament 2FA data for {$restoredCount} users");
    }
    
    private function clearFortifyData(): void
    {
        DB::table('users')->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
        
        $this->line("🧹 Cleared Fortify 2FA data");
    }
}
```

---

**Navigation Footer**

← [Previous: Unified System Analysis](010-unified-system-analysis.md) | [Next: Complete Implementation Guide →](030-complete-implementation-guide.md)

---

**Document Information**
- **File Path**: `.ai/010-docs/020-2fa-implementation/020-laravel-fortify/020-migration-implementation-guide.md`
- **Document ID**: LF-2FA-002-CONSOLIDATED
- **Version**: 2.0
- **Compliance**: WCAG AA, Junior Developer Guidelines
