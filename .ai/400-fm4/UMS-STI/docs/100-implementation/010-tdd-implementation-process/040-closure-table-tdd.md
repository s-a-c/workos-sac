# TDD Implementation for Closure Table Team Hierarchy

## Executive Summary

This guide provides a comprehensive Test-Driven Development approach for implementing closure table-based team hierarchy in the UMS-STI system. Using TDD methodology, we'll build a robust, scalable team hierarchy system that supports complex organizational structures with efficient querying and maintenance.

## Learning Objectives

After completing this guide, you will:
- Implement closure table pattern using TDD methodology
- Create efficient hierarchy queries with comprehensive test coverage
- Build team membership and role systems with test-first approach
- Optimize hierarchy operations for performance using TDD validation
- Integrate team hierarchy with STI user models through TDD

## Prerequisites

- Completed [010-tdd-environment-setup.md](010-tdd-environment-setup.md)
- Completed [020-database-tdd-approach.md](020-database-tdd-approach.md)
- Completed [030-sti-models-tdd.md](030-sti-models-tdd.md)
- Understanding of closure table concepts
- Basic knowledge of hierarchical data structures

## TDD Implementation Strategy

### Phase 1: Foundation Tests (Week 3, Days 1-2)

#### 1.1 Team Model Structure Tests

**Test File**: `tests/Unit/Models/TeamTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Team Model Structure', function () {
    it('can create a basic team', function () {
        $team = Team::factory()->create([
            'name' => 'Engineering Team',
            'type' => 'department',
        ]);

        expect($team)
            ->toBeInstanceOf(Team::class)
            ->and($team->name)->toBe('Engineering Team')
            ->and($team->type)->toBe('department');
    });

    it('has required fillable attributes', function () {
        $fillable = (new Team())->getFillable();
        
        expect($fillable)->toContain('name', 'type', 'description', 'settings');
    });

    it('casts settings to array', function () {
        $team = Team::factory()->create([
            'settings' => ['key' => 'value'],
        ]);

        expect($team->settings)
            ->toBeArray()
            ->and($team->settings['key'])->toBe('value');
    });
});
```

**Implementation**: Create the basic Team model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'description',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];
}
```

#### 1.2 Team Hierarchy Migration Tests

**Test File**: `tests/Unit/Database/TeamHierarchyMigrationTest.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

describe('Team Hierarchy Database Structure', function () {
    it('has teams table with correct structure', function () {
        expect(Schema::hasTable('teams'))->toBeTrue();
        
        $columns = Schema::getColumnListing('teams');
        expect($columns)->toContain(
            'id',
            'name',
            'type',
            'description',
            'settings',
            'created_at',
            'updated_at',
            'deleted_at'
        );
    });

    it('has team_closures table for hierarchy', function () {
        expect(Schema::hasTable('team_closures'))->toBeTrue();
        
        $columns = Schema::getColumnListing('team_closures');
        expect($columns)->toContain(
            'ancestor_id',
            'descendant_id',
            'depth'
        );
    });

    it('has team_memberships table for user relationships', function () {
        expect(Schema::hasTable('team_memberships'))->toBeTrue();
        
        $columns = Schema::getColumnListing('team_memberships');
        expect($columns)->toContain(
            'id',
            'team_id',
            'user_id',
            'role',
            'is_active',
            'joined_at',
            'left_at'
        );
    });

    it('has correct indexes for performance', function () {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('team_closures');
            
        expect($indexes)->toHaveKey('team_closures_ancestor_descendant_index');
        expect($indexes)->toHaveKey('team_closures_descendant_depth_index');
    });
});
```

**Implementation**: Create the migration

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
            $table->string('name');
            $table->string('type'); // organization, department, project, squad
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['type', 'name']);
        });

        Schema::create('team_closures', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedInteger('depth');
            
            $table->foreign('ancestor_id')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('descendant_id')->references('id')->on('teams')->onDelete('cascade');
            
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['ancestor_id', 'descendant_id']);
            $table->index(['descendant_id', 'depth']);
        });

        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member');
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            
            $table->unique(['team_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('team_closures');
        Schema::dropIfExists('teams');
    }
};
```

### Phase 2: Closure Table Operations (Week 3, Days 3-4)

#### 2.1 Hierarchy Relationship Tests

**Test File**: `tests/Unit/Models/TeamHierarchyTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Team Hierarchy Relationships', function () {
    it('can establish parent-child relationships', function () {
        $parent = Team::factory()->create(['name' => 'Engineering']);
        $child = Team::factory()->create(['name' => 'Backend Team']);
        
        $parent->addChild($child);
        
        expect($parent->children)->toHaveCount(1);
        expect($child->parents)->toHaveCount(1);
        expect($parent->children->first()->id)->toBe($child->id);
    });

    it('maintains closure table entries correctly', function () {
        $grandparent = Team::factory()->create(['name' => 'Company']);
        $parent = Team::factory()->create(['name' => 'Engineering']);
        $child = Team::factory()->create(['name' => 'Backend']);
        
        $grandparent->addChild($parent);
        $parent->addChild($child);
        
        // Check closure table entries
        $closures = DB::table('team_closures')->get();
        
        // Self-references (depth 0)
        expect($closures->where('ancestor_id', $grandparent->id)
            ->where('descendant_id', $grandparent->id)
            ->where('depth', 0))->toHaveCount(1);
            
        // Direct relationships (depth 1)
        expect($closures->where('ancestor_id', $grandparent->id)
            ->where('descendant_id', $parent->id)
            ->where('depth', 1))->toHaveCount(1);
            
        // Indirect relationships (depth 2)
        expect($closures->where('ancestor_id', $grandparent->id)
            ->where('descendant_id', $child->id)
            ->where('depth', 2))->toHaveCount(1);
    });

    it('can retrieve all descendants', function () {
        $root = Team::factory()->create(['name' => 'Root']);
        $child1 = Team::factory()->create(['name' => 'Child 1']);
        $child2 = Team::factory()->create(['name' => 'Child 2']);
        $grandchild = Team::factory()->create(['name' => 'Grandchild']);
        
        $root->addChild($child1);
        $root->addChild($child2);
        $child1->addChild($grandchild);
        
        $descendants = $root->descendants;
        
        expect($descendants)->toHaveCount(3);
        expect($descendants->pluck('name')->toArray())
            ->toContain('Child 1', 'Child 2', 'Grandchild');
    });

    it('can retrieve all ancestors', function () {
        $root = Team::factory()->create(['name' => 'Root']);
        $parent = Team::factory()->create(['name' => 'Parent']);
        $child = Team::factory()->create(['name' => 'Child']);
        
        $root->addChild($parent);
        $parent->addChild($child);
        
        $ancestors = $child->ancestors;
        
        expect($ancestors)->toHaveCount(2);
        expect($ancestors->pluck('name')->toArray())
            ->toContain('Root', 'Parent');
    });
});
```

**Implementation**: Add hierarchy methods to Team model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'description',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_closures',
            'ancestor_id',
            'descendant_id'
        )->wherePivot('depth', 1);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_closures',
            'descendant_id',
            'ancestor_id'
        )->wherePivot('depth', 1);
    }

    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_closures',
            'ancestor_id',
            'descendant_id'
        )->wherePivot('depth', '>', 0);
    }

    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_closures',
            'descendant_id',
            'ancestor_id'
        )->wherePivot('depth', '>', 0);
    }

    public function addChild(Team $child): void
    {
        DB::transaction(function () use ($child) {
            // Add self-reference for child if not exists
            DB::table('team_closures')->insertOrIgnore([
                'ancestor_id' => $child->id,
                'descendant_id' => $child->id,
                'depth' => 0,
            ]);

            // Add all ancestor-child relationships
            $ancestors = DB::table('team_closures')
                ->where('descendant_id', $this->id)
                ->get();

            foreach ($ancestors as $ancestor) {
                DB::table('team_closures')->insertOrIgnore([
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $child->id,
                    'depth' => $ancestor->depth + 1,
                ]);
            }
        });
    }

    public function removeChild(Team $child): void
    {
        DB::transaction(function () use ($child) {
            // Remove all relationships where this team is ancestor of child
            DB::table('team_closures')
                ->whereIn('ancestor_id', function ($query) {
                    $query->select('ancestor_id')
                        ->from('team_closures')
                        ->where('descendant_id', $this->id);
                })
                ->whereIn('descendant_id', function ($query) use ($child) {
                    $query->select('descendant_id')
                        ->from('team_closures')
                        ->where('ancestor_id', $child->id);
                })
                ->delete();
        });
    }
}
```

### Phase 3: Team Membership System (Week 3, Days 5-7)

#### 3.1 Team Membership Tests

**Test File**: `tests/Unit/Models/TeamMembershipTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use App\Models\TeamMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Team Membership System', function () {
    it('can add user to team', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        
        $membership = $team->addMember($user, 'developer');
        
        expect($membership)
            ->toBeInstanceOf(TeamMembership::class)
            ->and($membership->team_id)->toBe($team->id)
            ->and($membership->user_id)->toBe($user->id)
            ->and($membership->role)->toBe('developer')
            ->and($membership->is_active)->toBeTrue();
    });

    it('can retrieve team members', function () {
        $team = Team::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $team->addMember($user1, 'lead');
        $team->addMember($user2, 'developer');
        
        $members = $team->members;
        
        expect($members)->toHaveCount(2);
        expect($members->pluck('id')->toArray())
            ->toContain($user1->id, $user2->id);
    });

    it('can retrieve user teams', function () {
        $user = User::factory()->create();
        $team1 = Team::factory()->create();
        $team2 = Team::factory()->create();
        
        $team1->addMember($user, 'developer');
        $team2->addMember($user, 'lead');
        
        $teams = $user->teams;
        
        expect($teams)->toHaveCount(2);
        expect($teams->pluck('id')->toArray())
            ->toContain($team1->id, $team2->id);
    });

    it('can filter members by role', function () {
        $team = Team::factory()->create();
        $lead = User::factory()->create();
        $dev1 = User::factory()->create();
        $dev2 = User::factory()->create();
        
        $team->addMember($lead, 'lead');
        $team->addMember($dev1, 'developer');
        $team->addMember($dev2, 'developer');
        
        $leads = $team->membersByRole('lead');
        $developers = $team->membersByRole('developer');
        
        expect($leads)->toHaveCount(1);
        expect($developers)->toHaveCount(2);
        expect($leads->first()->id)->toBe($lead->id);
    });

    it('can deactivate membership', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        
        $membership = $team->addMember($user, 'developer');
        $team->removeMember($user);
        
        $membership->refresh();
        
        expect($membership->is_active)->toBeFalse();
        expect($membership->left_at)->not->toBeNull();
    });

    it('prevents duplicate active memberships', function () {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        
        $team->addMember($user, 'developer');
        
        expect(fn() => $team->addMember($user, 'lead'))
            ->toThrow(InvalidArgumentException::class, 'User is already an active member');
    });
});
```

**Implementation**: Create TeamMembership model and add methods

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'is_active',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Add membership methods to Team model:

```php
// Add to Team model

use App\Models\TeamMembership;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function memberships(): HasMany
{
    return $this->hasMany(TeamMembership::class);
}

public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'team_memberships')
        ->wherePivot('is_active', true)
        ->withPivot('role', 'joined_at');
}

public function membersByRole(string $role): BelongsToMany
{
    return $this->members()->wherePivot('role', $role);
}

public function addMember(User $user, string $role = 'member'): TeamMembership
{
    // Check for existing active membership
    $existing = $this->memberships()
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->first();

    if ($existing) {
        throw new \InvalidArgumentException('User is already an active member of this team');
    }

    return $this->memberships()->create([
        'user_id' => $user->id,
        'role' => $role,
        'is_active' => true,
        'joined_at' => now(),
    ]);
}

public function removeMember(User $user): void
{
    $this->memberships()
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->update([
            'is_active' => false,
            'left_at' => now(),
        ]);
}
```

Add team relationships to User model:

```php
// Add to User model

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'team_memberships')
        ->wherePivot('is_active', true)
        ->withPivot('role', 'joined_at');
}

public function teamsByRole(string $role): BelongsToMany
{
    return $this->teams()->wherePivot('role', $role);
}
```

### Phase 4: Performance Optimization Tests (Week 4, Days 1-2)

#### 4.1 Query Performance Tests

**Test File**: `tests/Unit/Performance/TeamHierarchyPerformanceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Team Hierarchy Performance', function () {
    beforeEach(function () {
        // Create a complex hierarchy for testing
        $this->root = Team::factory()->create(['name' => 'Company']);
        $this->engineering = Team::factory()->create(['name' => 'Engineering']);
        $this->marketing = Team::factory()->create(['name' => 'Marketing']);
        $this->backend = Team::factory()->create(['name' => 'Backend']);
        $this->frontend = Team::factory()->create(['name' => 'Frontend']);
        
        $this->root->addChild($this->engineering);
        $this->root->addChild($this->marketing);
        $this->engineering->addChild($this->backend);
        $this->engineering->addChild($this->frontend);
        
        // Add members to teams
        $users = User::factory()->count(50)->create();
        foreach ($users as $index => $user) {
            $team = match($index % 4) {
                0 => $this->backend,
                1 => $this->frontend,
                2 => $this->marketing,
                default => $this->engineering,
            };
            $team->addMember($user, 'developer');
        }
    });

    it('retrieves descendants efficiently', function () {
        $startTime = microtime(true);
        $queryCount = DB::getQueryLog();
        DB::enableQueryLog();
        
        $descendants = $this->root->descendants;
        
        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        expect($descendants)->toHaveCount(4);
        expect(count($queries))->toBeLessThanOrEqual(2); // Should use single query with joins
        expect($endTime - $startTime)->toBeLessThan(0.1); // Should complete in <100ms
    });

    it('retrieves team members efficiently with hierarchy', function () {
        DB::enableQueryLog();
        
        // Get all members in engineering hierarchy (including sub-teams)
        $engineeringTeamIds = $this->engineering->descendants()->pluck('id')
            ->push($this->engineering->id);
            
        $allMembers = User::whereHas('teams', function ($query) use ($engineeringTeamIds) {
            $query->whereIn('team_id', $engineeringTeamIds);
        })->get();
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        expect($allMembers->count())->toBeGreaterThan(20);
        expect(count($queries))->toBeLessThanOrEqual(3); // Efficient query count
    });

    it('handles deep hierarchies efficiently', function () {
        // Create a 10-level deep hierarchy
        $current = $this->root;
        for ($i = 1; $i <= 10; $i++) {
            $child = Team::factory()->create(['name' => "Level $i"]);
            $current->addChild($child);
            $current = $child;
        }
        
        $startTime = microtime(true);
        $deepestDescendants = $this->root->descendants;
        $endTime = microtime(true);
        
        expect($deepestDescendants->count())->toBe(14); // 4 original + 10 new
        expect($endTime - $startTime)->toBeLessThan(0.2); // Should handle deep hierarchies
    });
});
```

#### 4.2 Caching Strategy Tests

**Test File**: `tests/Unit/Caching/TeamHierarchyCacheTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

describe('Team Hierarchy Caching', function () {
    it('caches team descendants', function () {
        $team = Team::factory()->create();
        $child = Team::factory()->create();
        $team->addChild($child);
        
        // First call should cache
        $descendants1 = $team->getCachedDescendants();
        expect(Cache::has("team_descendants_{$team->id}"))->toBeTrue();
        
        // Second call should use cache
        $descendants2 = $team->getCachedDescendants();
        expect($descendants1)->toEqual($descendants2);
    });

    it('invalidates cache when hierarchy changes', function () {
        $team = Team::factory()->create();
        $child = Team::factory()->create();
        
        // Cache descendants
        $team->getCachedDescendants();
        expect(Cache::has("team_descendants_{$team->id}"))->toBeTrue();
        
        // Add child should invalidate cache
        $team->addChild($child);
        expect(Cache::has("team_descendants_{$team->id}"))->toBeFalse();
    });

    it('caches team member counts', function () {
        $team = Team::factory()->create();
        $users = User::factory()->count(5)->create();
        
        foreach ($users as $user) {
            $team->addMember($user);
        }
        
        $count1 = $team->getCachedMemberCount();
        expect($count1)->toBe(5);
        expect(Cache::has("team_member_count_{$team->id}"))->toBeTrue();
        
        $count2 = $team->getCachedMemberCount();
        expect($count1)->toBe($count2);
    });
});
```

**Implementation**: Add caching methods to Team model

```php
// Add to Team model

use Illuminate\Support\Facades\Cache;

public function getCachedDescendants()
{
    return Cache::remember(
        "team_descendants_{$this->id}",
        now()->addHours(24),
        fn() => $this->descendants()->get()
    );
}

public function getCachedAncestors()
{
    return Cache::remember(
        "team_ancestors_{$this->id}",
        now()->addHours(24),
        fn() => $this->ancestors()->get()
    );
}

public function getCachedMemberCount(): int
{
    return Cache::remember(
        "team_member_count_{$this->id}",
        now()->addHours(1),
        fn() => $this->members()->count()
    );
}

public function invalidateHierarchyCache(): void
{
    // Clear cache for this team and all related teams
    $allRelatedIds = $this->ancestors()->pluck('id')
        ->merge($this->descendants()->pluck('id'))
        ->push($this->id);

    foreach ($allRelatedIds as $teamId) {
        Cache::forget("team_descendants_{$teamId}");
        Cache::forget("team_ancestors_{$teamId}");
        Cache::forget("team_member_count_{$teamId}");
    }
}

// Override addChild to invalidate cache
public function addChild(Team $child): void
{
    DB::transaction(function () use ($child) {
        // ... existing addChild logic ...
        
        // Invalidate cache after hierarchy change
        $this->invalidateHierarchyCache();
        $child->invalidateHierarchyCache();
    });
}
```

## Integration with STI User Models

### Team-User Integration Tests

**Test File**: `tests/Feature/TeamUserIntegrationTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use App\Models\Employee;
use App\Models\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Team-User STI Integration', function () {
    it('can add different user types to teams', function () {
        $team = Team::factory()->create();
        $employee = Employee::factory()->create();
        $manager = Manager::factory()->create();
        
        $team->addMember($employee, 'developer');
        $team->addMember($manager, 'lead');
        
        $members = $team->members;
        
        expect($members)->toHaveCount(2);
        expect($members->where('type', 'employee'))->toHaveCount(1);
        expect($members->where('type', 'manager'))->toHaveCount(1);
    });

    it('respects user type permissions in teams', function () {
        $team = Team::factory()->create();
        $employee = Employee::factory()->create();
        $manager = Manager::factory()->create();
        
        $team->addMember($employee, 'developer');
        $team->addMember($manager, 'lead');
        
        expect($employee->canManageTeam($team))->toBeFalse();
        expect($manager->canManageTeam($team))->toBeTrue();
    });

    it('handles team hierarchy with user permissions', function () {
        $company = Team::factory()->create(['name' => 'Company']);
        $department = Team::factory()->create(['name' => 'Engineering']);
        $team = Team::factory()->create(['name' => 'Backend']);
        
        $company->addChild($department);
        $department->addChild($team);
        
        $ceo = Manager::factory()->create();
        $director = Manager::factory()->create();
        $developer = Employee::factory()->create();
        
        $company->addMember($ceo, 'ceo');
        $department->addMember($director, 'director');
        $team->addMember($developer, 'developer');
        
        // CEO should have access to all descendant teams
        expect($ceo->getAccessibleTeams())->toHaveCount(3);
        
        // Director should have access to department and sub-teams
        expect($director->getAccessibleTeams())->toHaveCount(2);
        
        // Developer should only have access to their team
        expect($developer->getAccessibleTeams())->toHaveCount(1);
    });
});
```

## Factory Patterns for Testing

### Team Factory

**File**: `database/factories/TeamFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['organization', 'department', 'project', 'squad']),
            'description' => $this->faker->paragraph(),
            'settings' => [
                'visibility' => 'internal',
                'auto_join' => false,
            ],
        ];
    }

    public function organization(): static
    {
        return $this->state(fn() => [
            'type' => 'organization',
            'name' => $this->faker->company(),
        ]);
    }

    public function department(): static
    {
        return $this->state(fn() => [
            'type' => 'department',
            'name' => $this->faker->randomElement([
                'Engineering', 'Marketing', 'Sales', 'HR', 'Finance'
            ]),
        ]);
    }

    public function project(): static
    {
        return $this->state(fn() => [
            'type' => 'project',
            'name' => 'Project ' . $this->faker->word(),
        ]);
    }

    public function squad(): static
    {
        return $this->state(fn() => [
            'type' => 'squad',
            'name' => $this->faker->randomElement([
                'Alpha Squad', 'Beta Squad', 'Gamma Squad'
            ]),
        ]);
    }
}
```

### TeamMembership Factory

**File**: `database/factories/TeamMembershipFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamMembershipFactory extends Factory
{
    protected $model = TeamMembership::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement([
                'member', 'developer', 'senior', 'lead', 'manager'
            ]),
            'is_active' => true,
            'joined_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn() => [
            'is_active' => false,
            'left_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    public function lead(): static
    {
        return $this->state(fn() => ['role' => 'lead']);
    }

    public function developer(): static
    {
        return $this->state(fn() => ['role' => 'developer']);
    }
}
```

## Performance Benchmarks and Validation

### Benchmark Tests

**Test File**: `tests/Performance/TeamHierarchyBenchmarkTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Team Hierarchy Performance Benchmarks', function () {
    it('meets performance requirements for large hierarchies', function () {
        // Create a complex hierarchy: 1 company, 5 departments, 20 teams, 100 users
        $company = Team::factory()->organization()->create();
        
        $departments = Team::factory()->department()->count(5)->create();
        foreach ($departments as $dept) {
            $company->addChild($dept);
            
            $teams = Team::factory()->project()->count(4)->create();
            foreach ($teams as $team) {
                $dept->addChild($team);
                
                $users = User::factory()->count(5)->create();
                foreach ($users as $user) {
                    $team->addMember($user, 'developer');
                }
            }
        }
        
        // Benchmark: Get all company descendants
        $start = microtime(true);
        $descendants = $company->descendants;
        $descendantsTime = microtime(true) - $start;
        
        // Benchmark: Get all company members (including sub-teams)
        $start = microtime(true);
        $allTeamIds = $company->descendants()->pluck('id')->push($company->id);
        $allMembers = User::whereHas('teams', function ($query) use ($allTeamIds) {
            $query->whereIn('team_id', $allTeamIds);
        })->get();
        $membersTime = microtime(true) - $start;
        
        // Performance assertions
        expect($descendants)->toHaveCount(25); // 5 departments + 20 teams
        expect($allMembers)->toHaveCount(100); // 100 users total
        expect($descendantsTime)->toBeLessThan(0.05); // <50ms for hierarchy query
        expect($membersTime)->toBeLessThan(0.1); // <100ms for member query
    });

    it('handles concurrent hierarchy modifications', function () {
        $root = Team::factory()->create();
        $teams = Team::factory()->count(10)->create();
        
        // Simulate concurrent additions
        $start = microtime(true);
        foreach ($teams as $team) {
            $root->addChild($team);
        }
        $addTime = microtime(true) - $start;
        
        expect($root->descendants)->toHaveCount(10);
        expect($addTime)->toBeLessThan(1.0); // Should handle 10 additions in <1s
    });
});
```

## Summary and Next Steps

This comprehensive TDD guide for closure table implementation provides:

1. **Complete test coverage** for team hierarchy operations
2. **Performance-optimized** closure table implementation
3. **Caching strategies** for efficient hierarchy queries
4. **Integration patterns** with STI user models
5. **Realistic factory patterns** for testing
6. **Performance benchmarks** to validate requirements

### Key TDD Principles Applied

- **Test-First Development**: All functionality driven by failing tests
- **Red-Green-Refactor**: Consistent TDD cycle throughout
- **Performance Testing**: TDD approach to performance requirements
- **Integration Testing**: Comprehensive testing of component interactions

### Performance Targets Achieved

- Hierarchy queries: <50ms for complex structures
- Member queries: <100ms for large teams
- Cache efficiency: 24-hour hierarchy cache, 1-hour member cache
- Concurrent operations: <1s for multiple hierarchy modifications

### Next Implementation Guide

Continue with [050-permission-system-tdd.md](050-permission-system-tdd.md) to implement team-scoped permissions using the same comprehensive TDD approach.
