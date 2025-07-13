# 12. Database Factories and Seeders

## 12.1. Enhanced Database Migrations

### 12.1.1. Teams Table Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            
            // STI discriminator
            $table->string('type', 50);
            
            // Unique identifiers
            $table->string('ulid', 26)->unique();
            $table->string('slug', 100)->index();
            
            // Basic information
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active');
            $table->boolean('is_active')->default(true);
            
            // Self-referential hierarchy
            $table->foreignId('parent_id')->nullable()->constrained('teams')->onDelete('cascade');
            
            // Project-specific fields (nullable for other types)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->string('priority', 20)->nullable();
            
            // Flexible data storage
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['type', 'is_active']);
            $table->index(['parent_id', 'type']);
            $table->index(['status', 'type']);
            $table->unique(['slug', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

### 12.1.2. Team User Pivot Table Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teamables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->morphs('teamable'); // user_id, user_type
            $table->string('role', 50)->default('member');
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            
            $table->unique(['team_id', 'teamable_id', 'teamable_type']);
            $table->index(['teamable_id', 'teamable_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teamables');
    }
};
```

### 12.1.3. Enhanced Roles and Permissions Tables

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend roles table
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('level')->default(50);
            
            $table->index(['team_id', 'name']);
        });

        // Extend permissions table
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->boolean('is_dangerous')->default(false);
            
            $table->index(['team_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['team_id', 'description', 'is_default', 'level']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn(['team_id', 'description', 'category', 'is_dangerous']);
        });
    }
};
```

## 12.2. Comprehensive Factory Implementations

### 12.2.1. Team Factory

```php
<?php

namespace Database\Factories;

use App\Enums\TeamStatus;
use App\Enums\TeamType;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(TeamType::cases()),
            'status' => TeamStatus::Active,
            'is_active' => true,
            'settings' => [
                'notifications_enabled' => $this->faker->boolean(),
                'public_visibility' => $this->faker->boolean(30),
            ],
            'metadata' => [
                'created_by' => 'system',
                'tags' => $this->faker->words(3),
            ],
        ];
    }

    /**
     * Create organization team.
     */
    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TeamType::Organization,
            'parent_id' => null,
            'name' => $this->faker->company() . ' Organization',
            'settings' => [
                'allow_public_projects' => $this->faker->boolean(),
                'require_approval_for_members' => true,
                'max_departments' => $this->faker->numberBetween(5, 20),
            ],
        ]);
    }

    /**
     * Create department team.
     */
    public function department(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TeamType::Department,
            'name' => $this->faker->randomElement([
                'Engineering', 'Marketing', 'Sales', 'HR', 'Finance', 'Operations'
            ]) . ' Department',
            'settings' => [
                'budget_tracking' => true,
                'time_tracking' => $this->faker->boolean(),
            ],
            'metadata' => [
                'budget' => [
                    'allocated' => $this->faker->numberBetween(100000, 1000000),
                    'spent' => 0,
                    'currency' => 'USD',
                ],
            ],
        ]);
    }

    /**
     * Create project team.
     */
    public function project(): static
    {
        $startDate = $this->faker->dateTimeBetween('-6 months', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+1 year');

        return $this->state(fn (array $attributes) => [
            'type' => TeamType::Project,
            'name' => $this->faker->catchPhrase() . ' Project',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'budget' => $this->faker->numberBetween(10000, 500000),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'metadata' => [
                'progress' => $this->faker->numberBetween(0, 100),
                'milestones' => $this->faker->numberBetween(3, 10),
            ],
        ]);
    }

    /**
     * Create squad team.
     */
    public function squad(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TeamType::Squad,
            'name' => $this->faker->randomElement([
                'Alpha', 'Beta', 'Gamma', 'Delta', 'Phoenix', 'Storm', 'Thunder'
            ]) . ' Squad',
            'settings' => [
                'max_capacity' => $this->faker->numberBetween(4, 12),
                'sprint_length' => $this->faker->numberBetween(1, 4),
            ],
        ]);
    }

    /**
     * Create with parent team.
     */
    public function withParent(Team $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Create inactive team.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status' => TeamStatus::Inactive,
        ]);
    }
}
```

### 12.2.2. Enhanced User Factories

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\StandardUser;
use App\States\User\ActiveState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StandardUserFactory extends Factory
{
    protected $model = StandardUser::class;

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
                'social_links' => [
                    'linkedin' => $this->faker->optional()->url(),
                    'github' => $this->faker->optional()->url(),
                ],
            ],
        ];
    }

    /**
     * Create user with team memberships.
     */
    public function withTeamMemberships(int $count = 2): static
    {
        return $this->afterCreating(function (StandardUser $user) use ($count) {
            $teams = \App\Models\Team::factory()->count($count)->create();
            
            foreach ($teams as $team) {
                $team->addMember($user, $this->faker->randomElement([
                    'member', 'contributor', 'lead'
                ]));
            }
        });
    }

    /**
     * Create user with specific permissions.
     */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(function (StandardUser $user) use ($permissions) {
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }
        });
    }
}
```

### 12.2.3. SystemUser Factory

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\SystemUser;
use App\States\User\ActiveState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class SystemUserFactory extends Factory
{
    protected $model = SystemUser::class;

    public function definition(): array
    {
        return [
            'name' => 'System ' . $this->faker->randomElement(['Administrator', 'Process', 'Service', 'Bot']),
            'email' => 'system+' . $this->faker->uuid() . '@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make(str()->random(32)), // Secure random password
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'state' => ActiveState::class,
            'profile_data' => [
                'system_type' => $this->faker->randomElement(['automated', 'maintenance', 'integration']),
                'created_for' => $this->faker->sentence(),
                'last_used' => now(),
            ],
        ];
    }

    /**
     * Create system user for specific purpose.
     */
    public function forPurpose(string $purpose): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => "System {$purpose}",
            'email' => "system+{$purpose}@example.com",
            'profile_data' => [
                'system_type' => 'automated',
                'created_for' => $purpose,
                'last_used' => now(),
            ],
        ]);
    }

    /**
     * Create maintenance system user.
     */
    public function maintenance(): static
    {
        return $this->forPurpose('maintenance');
    }

    /**
     * Create integration system user.
     */
    public function integration(): static
    {
        return $this->forPurpose('integration');
    }
}
```

## 12.3. Comprehensive Database Seeders

### 12.3.1. Master Database Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            OrganizationSeeder::class,
            UserSeeder::class,
            TeamMembershipSeeder::class,
        ]);
    }
}
```

### 12.3.2. Permission Seeder

```php
<?php

namespace Database\Seeders;

use App\Enums\PermissionCategory;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCategory::cases() as $category) {
            foreach ($category->getPermissions() as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ], [
                    'category' => $category->value,
                    'description' => $this->getPermissionDescription($permission),
                    'is_dangerous' => $this->isDangerousPermission($permission),
                ]);
            }
        }
    }

    private function getPermissionDescription(string $permission): string
    {
        return match ($permission) {
            'users.view' => 'View user profiles and information',
            'users.create' => 'Create new user accounts',
            'users.edit' => 'Edit user profiles and settings',
            'users.delete' => 'Delete user accounts',
            'users.impersonate' => 'Impersonate other users',
            'teams.view' => 'View team information',
            'teams.create' => 'Create new teams',
            'teams.edit' => 'Edit team settings and information',
            'teams.delete' => 'Delete teams',
            'teams.manage_members' => 'Add and remove team members',
            'teams.assign_roles' => 'Assign roles to team members',
            default => ucfirst(str_replace(['.', '_'], [' ', ' '], $permission)),
        };
    }

    private function isDangerousPermission(string $permission): bool
    {
        return in_array($permission, [
            'users.delete',
            'users.impersonate',
            'teams.delete',
            'system.settings',
            'system.maintenance',
        ]);
    }
}
```

### 12.3.3. Role Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Services\RoleHierarchyService;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'description' => 'Super Administrator with full system access',
                'level' => RoleHierarchyService::ROLE_LEVELS['super_admin'],
                'permissions' => 'all',
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator with elevated privileges',
                'level' => RoleHierarchyService::ROLE_LEVELS['admin'],
                'permissions' => [
                    'users.view', 'users.create', 'users.edit',
                    'teams.view', 'teams.create', 'teams.edit', 'teams.manage_members',
                    'projects.view', 'projects.create', 'projects.edit',
                    'reports.view', 'reports.create',
                ],
            ],
            [
                'name' => 'manager',
                'description' => 'Manager with team oversight capabilities',
                'level' => RoleHierarchyService::ROLE_LEVELS['manager'],
                'permissions' => [
                    'users.view', 'teams.view', 'teams.manage_members',
                    'projects.view', 'projects.edit', 'reports.view',
                ],
            ],
            [
                'name' => 'member',
                'description' => 'Standard team member',
                'level' => RoleHierarchyService::ROLE_LEVELS['member'],
                'permissions' => [
                    'users.view', 'teams.view', 'projects.view',
                ],
            ],
            [
                'name' => 'viewer',
                'description' => 'Read-only access',
                'level' => RoleHierarchyService::ROLE_LEVELS['viewer'],
                'permissions' => [
                    'teams.view', 'projects.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate([
                'name' => $roleData['name'],
                'guard_name' => 'web',
            ], [
                'description' => $roleData['description'],
                'level' => $roleData['level'],
                'is_default' => in_array($roleData['name'], ['member', 'viewer']),
            ]);

            if ($roleData['permissions'] === 'all') {
                $role->syncPermissions(\App\Models\Permission::all());
            } else {
                $role->syncPermissions($roleData['permissions']);
            }
        }
    }
}
```

### 12.3.4. User Seeder

```php
<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\SystemUser;
use App\States\User\ActiveState;
use App\States\User\GuestState;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create System User (for automated processes)
        SystemUser::create([
            'name' => 'System Administrator',
            'email' => 'system@example.com',
            'password' => Hash::make(str()->random(32)), // Random secure password
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'email_verified_at' => now(),
            'state' => ActiveState::class,
        ]);

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

### 12.3.5. Organization Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Squad;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // Create main organization
        $organization = Organization::factory()->create([
            'name' => 'Acme Corporation',
            'description' => 'A leading technology company',
        ]);

        // Create departments
        $departments = [
            'Engineering' => 'Software development and technical operations',
            'Marketing' => 'Brand management and customer acquisition',
            'Sales' => 'Revenue generation and client relationships',
            'Human Resources' => 'People operations and talent management',
        ];

        foreach ($departments as $name => $description) {
            $department = Department::factory()
                ->withParent($organization)
                ->create([
                    'name' => $name . ' Department',
                    'description' => $description,
                ]);

            // Create projects for each department
            $projects = Project::factory()
                ->count(rand(2, 4))
                ->withParent($department)
                ->create();

            // Create squads for some projects
            foreach ($projects as $project) {
                if (rand(0, 1)) {
                    Squad::factory()
                        ->count(rand(1, 3))
                        ->withParent($project)
                        ->create();
                }
            }
        }
    }
}
```

---

**Next**: [Best Practices and Patterns](130-best-practices-and-patterns.md) - Updated best practices incorporating Teams and Permissions.
