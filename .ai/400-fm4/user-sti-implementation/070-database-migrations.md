# 7. Database Migrations

## 7.1. Primary Users Table Migration

### 7.1.1. Main Users Table Structure

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // Primary key
            $table->id();
            
            // STI discriminator column
            $table->string('type', 50)->default('standard_user');
            
            // Unique identifiers
            $table->string('ulid', 26)->unique();
            $table->string('slug', 100)->index();
            
            // Basic user information
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // Role and status
            $table->string('role', 50)->default('user');
            $table->string('status', 50)->default('pending');
            $table->boolean('is_active')->default(false);
            
            // Authentication
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();

            // Active team tracking
            $table->foreignId('active_team_id')->nullable()->constrained('teams')->onDelete('set null');
            
            // Type-specific columns (nullable for different user types)
            // Admin-specific
            $table->integer('admin_level')->nullable();
            $table->string('department', 100)->nullable();
            $table->json('permissions')->nullable();
            
            // Guest-specific
            $table->string('session_id', 100)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('conversion_data')->nullable();
            $table->json('tracking_data')->nullable();
            
            // Profile data (JSON for flexibility)
            $table->json('profile_data')->nullable();
            
            // State management
            $table->string('state', 50)->default('pending');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['type', 'is_active']);
            $table->index(['type', 'status']);
            $table->index(['type', 'role']);
            $table->index(['email', 'type']);
            $table->index(['created_at', 'type']);
            $table->index('state');
            $table->index('active_team_id');
            
            // Unique constraints
            $table->unique(['slug', 'type']);
            $table->unique(['email', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

## 7.2. Model States Table Migration

### 7.2.1. States Table for FSM

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('model_states', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('field');
            $table->string('state');
            $table->timestamps();
            
            $table->index(['model_type', 'model_id', 'field']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_states');
    }
};
```

## 7.3. Model Statuses Table Migration

### 7.3.1. Statuses Table for Status Tracking

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('reason')->nullable();
            $table->morphs('model');
            $table->timestamps();
            
            $table->index(['model_type', 'model_id']);
            $table->index(['name', 'model_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
```

## 7.4. Additional Supporting Tables

### 7.4.1. User Sessions Table (for Guest tracking)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id', 100)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('last_activity');
            $table->timestamps();
            
            $table->index(['user_id', 'last_activity']);
            $table->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
```

### 7.4.2. User Activity Log Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('action', 100);
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
```

## 7.5. Database Seeders

### 7.5.1. User Types Seeder

```php
<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\States\User\ActiveState;
use App\States\User\GuestState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        Admin::create([
            'name' => 'Super Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            'admin_level' => 5,
            'department' => 'System Administration',
            'is_active' => true,
            'email_verified_at' => now(),
            'state' => ActiveState::class,
            'permissions' => [
                'manage_system',
                'manage_users',
                'view_analytics',
                'manage_settings',
            ],
        ]);

        // Create Regular Admin
        Admin::create([
            'name' => 'John Admin',
            'email' => 'john.admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'admin_level' => 3,
            'department' => 'Operations',
            'is_active' => true,
            'email_verified_at' => now(),
            'state' => ActiveState::class,
            'permissions' => [
                'manage_users',
                'view_analytics',
            ],
        ]);

        // Create Standard Users
        StandardUser::factory(50)->create();

        // Create Guest Users
        Guest::factory(20)->create([
            'state' => GuestState::class,
        ]);
    }
}
```

### 7.5.2. User Factory

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\StandardUser;
use App\States\User\ActiveState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StandardUserFactory extends Factory
{
    protected $model = StandardUser::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::User,
            'is_active' => true,
            'state' => ActiveState::class,
            'profile_data' => [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'phone' => $this->faker->phoneNumber(),
                'bio' => $this->faker->sentence(),
                'timezone' => $this->faker->timezone(),
                'locale' => 'en',
                'preferences' => [
                    'theme' => $this->faker->randomElement(['light', 'dark']),
                    'email_notifications' => $this->faker->boolean(),
                    'push_notifications' => $this->faker->boolean(),
                ],
            ],
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

## 7.6. Active Team Tracking Migration

### 7.6.1. Add Active Team Field to Users Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add active team tracking
            $table->foreignId('active_team_id')
                ->nullable()
                ->after('last_login_at')
                ->constrained('teams')
                ->onDelete('set null');

            // Add index for performance
            $table->index('active_team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_team_id']);
            $table->dropIndex(['active_team_id']);
            $table->dropColumn('active_team_id');
        });
    }
};
```

## 7.7. Database Optimization

### 7.7.1. Additional Indexes Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Composite indexes for common queries
            $table->index(['type', 'is_active', 'created_at'], 'users_type_active_created_idx');
            $table->index(['role', 'is_active'], 'users_role_active_idx');
            $table->index(['state', 'type'], 'users_state_type_idx');
            
            // Full-text search index for name and email
            $table->fullText(['name', 'email'], 'users_name_email_fulltext');
            
            // Partial indexes for active users only (PostgreSQL)
            if (config('database.default') === 'pgsql') {
                DB::statement('CREATE INDEX CONCURRENTLY users_active_email_idx ON users (email) WHERE is_active = true');
                DB::statement('CREATE INDEX CONCURRENTLY users_active_slug_idx ON users (slug) WHERE is_active = true');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_type_active_created_idx');
            $table->dropIndex('users_role_active_idx');
            $table->dropIndex('users_state_type_idx');
            $table->dropFullText('users_name_email_fulltext');
        });
        
        if (config('database.default') === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_active_email_idx');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_active_slug_idx');
        }
    }
};
```

### 7.7.2. Database Views for Performance

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create view for active users
        DB::statement('
            CREATE VIEW active_users AS
            SELECT 
                id, ulid, slug, name, email, role, type, 
                created_at, updated_at, last_login_at
            FROM users 
            WHERE is_active = true 
            AND deleted_at IS NULL
        ');

        // Create view for user statistics
        DB::statement('
            CREATE VIEW user_statistics AS
            SELECT 
                type,
                role,
                COUNT(*) as total_count,
                COUNT(CASE WHEN is_active = true THEN 1 END) as active_count,
                COUNT(CASE WHEN email_verified_at IS NOT NULL THEN 1 END) as verified_count,
                MAX(created_at) as latest_registration,
                MAX(last_login_at) as latest_login
            FROM users 
            WHERE deleted_at IS NULL
            GROUP BY type, role
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS active_users');
        DB::statement('DROP VIEW IF EXISTS user_statistics');
    }
};
```

---

**Next**: [Testing Strategy](080-testing-strategy.md) - Comprehensive testing approach for STI implementation.
