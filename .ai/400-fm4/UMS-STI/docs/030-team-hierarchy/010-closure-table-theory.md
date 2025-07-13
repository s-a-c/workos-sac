# Closure Table Theory and Implementation

## Executive Summary
Closure tables are a hierarchical data storage pattern that maintains all ancestor-descendant relationships in a separate table. For UMS-STI's team hierarchy, this approach enables efficient queries for complex organizational structures while supporting unlimited depth and fast ancestor/descendant lookups without recursive queries.

## Learning Objectives
After completing this guide, you will:
- Understand closure table theory and advantages over adjacency lists
- Implement closure tables for team hierarchies in Laravel
- Design efficient queries for ancestor/descendant relationships
- Maintain closure table integrity with database triggers
- Optimize closure table performance for large hierarchies

## Prerequisite Knowledge
- Database normalization principles
- Tree data structures and hierarchical relationships
- SQL JOIN operations and query optimization
- Laravel migrations and Eloquent relationships
- Basic understanding of database triggers

## Architectural Overview

### Why Closure Tables for Team Hierarchy?

Based on **DECISION-002** from our decision log, we chose closure tables over alternatives:

```
Alternative 1: Adjacency List (Simple parent_id)
teams
├── id: 1, parent_id: null     (Organization)
├── id: 2, parent_id: 1        (Department)
├── id: 3, parent_id: 2        (Project)
└── id: 4, parent_id: 3        (Squad)

❌ Problems: Recursive queries needed, poor performance for deep hierarchies

Alternative 2: Nested Sets (Left/Right boundaries)
teams
├── id: 1, left: 1, right: 8   (Organization)
├── id: 2, left: 2, right: 7   (Department)
├── id: 3, left: 3, right: 6   (Project)
└── id: 4, left: 4, right: 5   (Squad)

❌ Problems: Complex updates, tree restructuring is expensive

✅ Our Choice: Closure Table
teams                    team_closure
├── id: 1 (Org)         ├── ancestor: 1, descendant: 1, depth: 0
├── id: 2 (Dept)        ├── ancestor: 1, descendant: 2, depth: 1
├── id: 3 (Proj)        ├── ancestor: 1, descendant: 3, depth: 2
└── id: 4 (Squad)       ├── ancestor: 1, descendant: 4, depth: 3
                        ├── ancestor: 2, descendant: 2, depth: 0
                        ├── ancestor: 2, descendant: 3, depth: 1
                        ├── ancestor: 2, descendant: 4, depth: 2
                        ├── ancestor: 3, descendant: 3, depth: 0
                        ├── ancestor: 3, descendant: 4, depth: 1
                        └── ancestor: 4, descendant: 4, depth: 0
```

### Closure Table Benefits for UMS-STI

1. **Fast Queries**: No recursive CTEs needed for ancestor/descendant lookups
2. **Flexible Depth**: Supports unlimited hierarchy depth with consistent performance
3. **Simple Maintenance**: Clear relationship storage and update patterns
4. **SQLite Optimized**: Works excellently with SQLite's indexing capabilities
5. **Hierarchy Validation**: Easy depth limit enforcement and cycle detection

## Core Concepts Deep Dive

### 1. Closure Table Structure

The closure table stores every ancestor-descendant relationship:

```sql
CREATE TABLE team_closure (
    ancestor_id INTEGER NOT NULL,
    descendant_id INTEGER NOT NULL,
    depth INTEGER NOT NULL,
    PRIMARY KEY (ancestor_id, descendant_id)
);
```

**Key Principles**:
- Every node is its own ancestor/descendant at depth 0
- Direct parent-child relationships have depth 1
- Grandparent-grandchild relationships have depth 2, etc.
- All relationships are explicitly stored

### 2. Relationship Examples

For this hierarchy:
```
Acme Corp (1)
└── Engineering (2)
    └── Backend Team (3)
        └── API Squad (4)
```

The closure table contains:
```sql
-- Self-relationships (depth 0)
(1, 1, 0), (2, 2, 0), (3, 3, 0), (4, 4, 0)

-- Direct relationships (depth 1)
(1, 2, 1), (2, 3, 1), (3, 4, 1)

-- Indirect relationships (depth 2+)
(1, 3, 2), (2, 4, 2), (1, 4, 3)
```

### 3. Query Patterns

**Find all descendants of a team**:
```sql
SELECT t.* FROM teams t
JOIN team_closure tc ON t.id = tc.descendant_id
WHERE tc.ancestor_id = ? AND tc.depth > 0;
```

**Find all ancestors of a team**:
```sql
SELECT t.* FROM teams t
JOIN team_closure tc ON t.id = tc.ancestor_id
WHERE tc.descendant_id = ? AND tc.depth > 0;
```

**Find immediate children**:
```sql
SELECT t.* FROM teams t
JOIN team_closure tc ON t.id = tc.descendant_id
WHERE tc.ancestor_id = ? AND tc.depth = 1;
```

## Implementation Principles & Patterns

### 1. Separation of Concerns
- **Teams Table**: Stores team data and direct parent relationship
- **Closure Table**: Stores all hierarchical relationships
- **Model Methods**: Provide convenient access to hierarchy operations

### 2. Data Integrity
- **Database Triggers**: Automatically maintain closure table
- **Validation**: Prevent cycles and enforce depth limits
- **Transactions**: Ensure consistency during hierarchy changes

### 3. Performance Optimization
- **Strategic Indexing**: Optimize for common query patterns
- **Batch Operations**: Efficient bulk hierarchy updates
- **Caching**: Cache frequently accessed hierarchy data

## Step-by-Step Implementation Guide

### Step 1: Create Team Closure Migration

Create `database/migrations/004_create_team_closure_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_closure', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedInteger('depth');

            // Composite primary key (Laravel 12.x optimized)
            $table->primary(['ancestor_id', 'descendant_id'], 'team_closure_primary');

            // Foreign key constraints with proper naming
            $table->foreign('ancestor_id', 'team_closure_ancestor_foreign')
                  ->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('descendant_id', 'team_closure_descendant_foreign')
                  ->references('id')->on('teams')->onDelete('cascade');

            // Strategic indexes for performance (Laravel 12.x patterns)
            $table->index('ancestor_id', 'team_closure_ancestor_index');
            $table->index('descendant_id', 'team_closure_descendant_index');
            $table->index('depth', 'team_closure_depth_index');
            $table->index(['ancestor_id', 'depth'], 'team_closure_ancestor_depth_index');
            $table->index(['descendant_id', 'depth'], 'team_closure_descendant_depth_index');

            // Additional performance indexes for common queries
            $table->index(['depth', 'ancestor_id'], 'team_closure_depth_ancestor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_closure');
    }
};
```

### Step 2: Create TeamClosure Model

Create `app/Models/TeamClosure.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamClosure extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $table = 'team_closure';
    protected $primaryKey = ['ancestor_id', 'descendant_id'];

    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    protected $casts = [
        'ancestor_id' => 'integer',
        'descendant_id' => 'integer',
        'depth' => 'integer',
    ];

    /**
     * Ancestor team relationship.
     */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'ancestor_id');
    }

    /**
     * Descendant team relationship.
     */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'descendant_id');
    }

    /**
     * Scope for direct relationships (depth = 1).
     */
    public function scopeDirect($query)
    {
        return $query->where('depth', 1);
    }

    /**
     * Scope for self-relationships (depth = 0).
     */
    public function scopeSelf($query)
    {
        return $query->where('depth', 0);
    }

    /**
     * Scope for indirect relationships (depth > 1).
     */
    public function scopeIndirect($query)
    {
        return $query->where('depth', '>', 1);
    }

    /**
     * Scope by maximum depth.
     */
    public function scopeMaxDepth($query, int $maxDepth)
    {
        return $query->where('depth', '<=', $maxDepth);
    }
}
```

### Step 3: Add Closure Table Methods to Team Model

Update `app/Models/Team.php` with closure table methods:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

abstract class Team extends Model
{
    // ... existing code ...

    /**
     * Closure table relationships.
     */
    public function closureAncestors(): HasMany
    {
        return $this->hasMany(TeamClosure::class, 'descendant_id');
    }

    public function closureDescendants(): HasMany
    {
        return $this->hasMany(TeamClosure::class, 'ancestor_id');
    }

    /**
     * Get all ancestors (excluding self).
     */
    public function ancestors(): Collection
    {
        return Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.ancestor_id')
            ->where('team_closure.descendant_id', $this->id)
            ->where('team_closure.depth', '>', 0)
            ->orderBy('team_closure.depth', 'desc')
            ->get(['teams.*', 'team_closure.depth']);
    }

    /**
     * Get all descendants (excluding self).
     */
    public function descendants(): Collection
    {
        return Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.descendant_id')
            ->where('team_closure.ancestor_id', $this->id)
            ->where('team_closure.depth', '>', 0)
            ->orderBy('team_closure.depth', 'asc')
            ->get(['teams.*', 'team_closure.depth']);
    }

    /**
     * Get immediate children (depth = 1).
     */
    public function children(): Collection
    {
        return Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.descendant_id')
            ->where('team_closure.ancestor_id', $this->id)
            ->where('team_closure.depth', 1)
            ->get(['teams.*']);
    }

    /**
     * Get immediate parent (depth = 1).
     */
    public function parent(): ?Team
    {
        return Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.ancestor_id')
            ->where('team_closure.descendant_id', $this->id)
            ->where('team_closure.depth', 1)
            ->first(['teams.*']);
    }

    /**
     * Get hierarchy depth (distance from root).
     */
    public function getDepth(): int
    {
        $maxDepth = TeamClosure::where('descendant_id', $this->id)
            ->where('depth', '>', 0)
            ->max('depth');

        return $maxDepth ?? 0;
    }

    /**
     * Check if this team is an ancestor of another team.
     */
    public function isAncestorOf(Team $team): bool
    {
        return TeamClosure::where('ancestor_id', $this->id)
            ->where('descendant_id', $team->id)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * Check if this team is a descendant of another team.
     */
    public function isDescendantOf(Team $team): bool
    {
        return TeamClosure::where('ancestor_id', $team->id)
            ->where('descendant_id', $this->id)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * Get the root team (top-level ancestor).
     */
    public function getRoot(): Team
    {
        $root = Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.ancestor_id')
            ->where('team_closure.descendant_id', $this->id)
            ->orderBy('team_closure.depth', 'desc')
            ->first(['teams.*']);

        return $root ?? $this;
    }

    /**
     * Check if team is a root (has no ancestors).
     */
    public function isRoot(): bool
    {
        return !TeamClosure::where('descendant_id', $this->id)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * Check if team is a leaf (has no descendants).
     */
    public function isLeaf(): bool
    {
        return !TeamClosure::where('ancestor_id', $this->id)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * Get all teams at a specific depth relative to this team.
     */
    public function getTeamsAtDepth(int $depth): Collection
    {
        return Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.descendant_id')
            ->where('team_closure.ancestor_id', $this->id)
            ->where('team_closure.depth', $depth)
            ->get(['teams.*']);
    }

    /**
     * Get hierarchy path from root to this team.
     */
    public function getPath(): Collection
    {
        return Team::query()
            ->join('team_closure', 'teams.id', '=', 'team_closure.ancestor_id')
            ->where('team_closure.descendant_id', $this->id)
            ->orderBy('team_closure.depth', 'desc')
            ->get(['teams.*', 'team_closure.depth']);
    }
}
```

### Step 4: Create Closure Table Service

Create `app/Services/TeamClosureService.php`:

```php
<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamClosure;
use Illuminate\Support\Facades\DB;

class TeamClosureService
{
    /**
     * Insert a new team into the closure table.
     */
    public function insertTeam(Team $team, ?Team $parent = null): void
    {
        DB::transaction(function () use ($team, $parent) {
            // Insert self-relationship
            TeamClosure::create([
                'ancestor_id' => $team->id,
                'descendant_id' => $team->id,
                'depth' => 0,
            ]);

            if ($parent) {
                // Insert direct parent relationship
                TeamClosure::create([
                    'ancestor_id' => $parent->id,
                    'descendant_id' => $team->id,
                    'depth' => 1,
                ]);

                // Insert relationships with all ancestors of parent
                $parentAncestors = TeamClosure::where('descendant_id', $parent->id)
                    ->where('depth', '>', 0)
                    ->get();

                foreach ($parentAncestors as $ancestor) {
                    TeamClosure::create([
                        'ancestor_id' => $ancestor->ancestor_id,
                        'descendant_id' => $team->id,
                        'depth' => $ancestor->depth + 1,
                    ]);
                }
            }
        });
    }

    /**
     * Move a team to a new parent.
     */
    public function moveTeam(Team $team, ?Team $newParent = null): void
    {
        DB::transaction(function () use ($team, $newParent) {
            // Remove existing relationships (except self)
            $this->removeTeamRelationships($team);

            // Re-insert with new parent
            $this->insertTeam($team, $newParent);

            // Update all descendants
            $descendants = $team->descendants();
            foreach ($descendants as $descendant) {
                $this->removeTeamRelationships($descendant);
                $this->insertTeam($descendant, $descendant->parent());
            }
        });
    }

    /**
     * Delete a team and all its relationships.
     */
    public function deleteTeam(Team $team): void
    {
        DB::transaction(function () use ($team) {
            // Remove all closure relationships
            TeamClosure::where('ancestor_id', $team->id)
                ->orWhere('descendant_id', $team->id)
                ->delete();
        });
    }

    /**
     * Validate hierarchy depth limits.
     */
    public function validateDepthLimit(Team $team, int $maxDepth): bool
    {
        $currentDepth = $team->getDepth();
        return $currentDepth <= $maxDepth;
    }

    /**
     * Detect cycles in hierarchy.
     */
    public function detectCycle(Team $team, Team $proposedParent): bool
    {
        return $team->isAncestorOf($proposedParent);
    }

    /**
     * Rebuild entire closure table (for data repair).
     */
    public function rebuildClosureTable(): void
    {
        DB::transaction(function () {
            // Clear existing closure data
            TeamClosure::truncate();

            // Rebuild from teams table
            $teams = Team::all();
            
            foreach ($teams as $team) {
                $parent = $team->parent_id ? Team::find($team->parent_id) : null;
                $this->insertTeam($team, $parent);
            }
        });
    }

    /**
     * Remove team relationships (except self).
     */
    private function removeTeamRelationships(Team $team): void
    {
        TeamClosure::where('descendant_id', $team->id)
            ->where('depth', '>', 0)
            ->delete();
    }

    /**
     * Get hierarchy statistics.
     */
    public function getHierarchyStats(): array
    {
        return [
            'total_teams' => Team::count(),
            'max_depth' => TeamClosure::max('depth'),
            'root_teams' => Team::whereDoesntHave('closureAncestors', function ($query) {
                $query->where('depth', '>', 0);
            })->count(),
            'leaf_teams' => Team::whereDoesntHave('closureDescendants', function ($query) {
                $query->where('depth', '>', 0);
            })->count(),
        ];
    }
}
```

## Testing & Validation

### Unit Test for Closure Table Operations (Laravel 12.x with Pest)

Create `tests/Unit/Services/TeamClosureServiceTest.php`:

```php
<?php

use App\Models\Organization;
use App\Models\Department;
use App\Models\Project;
use App\Models\TeamClosure;
use App\Services\TeamClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TeamClosureService();
});

test('insert team creates self relationship', function () {
    $org = Organization::factory()->create();
    $this->service->insertTeam($org);

    expect('team_closure')->toHaveRecord([
        'ancestor_id' => $org->id,
        'descendant_id' => $org->id,
        'depth' => 0,
    ]);
});

test('insert team with parent creates hierarchy', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create();

    $this->service->insertTeam($org);
    $this->service->insertTeam($dept, $org);

    // Check direct relationship
    expect('team_closure')->toHaveRecord([
        'ancestor_id' => $org->id,
        'descendant_id' => $dept->id,
        'depth' => 1,
    ]);

    // Check self-relationship
    expect('team_closure')->toHaveRecord([
        'ancestor_id' => $dept->id,
        'descendant_id' => $dept->id,
        'depth' => 0,
    ]);
});

test('three level hierarchy creates all relationships', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create();
    $project = Project::factory()->create();

    $this->service->insertTeam($org);
    $this->service->insertTeam($dept, $org);
    $this->service->insertTeam($project, $dept);

    // Check all relationships exist
    expect(TeamClosure::count())->toBe(6); // 3 self + 2 direct + 1 indirect

    // Check indirect relationship
    expect('team_closure')->toHaveRecord([
        'ancestor_id' => $org->id,
        'descendant_id' => $project->id,
        'depth' => 2,
    ]);
});

test('hierarchy queries work correctly', function () {
    $org = Organization::factory()->create(['name' => 'Acme Corp']);
    $dept = Department::factory()->create(['name' => 'Engineering']);
    $project = Project::factory()->create(['name' => 'Backend API']);

    $this->service->insertTeam($org);
    $this->service->insertTeam($dept, $org);
    $this->service->insertTeam($project, $dept);

    // Test ancestors
    $ancestors = $project->ancestors();
    expect($ancestors)->toHaveCount(2);
    expect($ancestors->first()->name)->toBe('Engineering');
    expect($ancestors->last()->name)->toBe('Acme Corp');

    // Test descendants
    $descendants = $org->descendants();
    expect($descendants)->toHaveCount(2);

    // Test depth
    expect($org->getDepth())->toBe(0);
    expect($dept->getDepth())->toBe(1);
    expect($project->getDepth())->toBe(2);
});

test('cycle detection works correctly', function () {
    $org = Organization::factory()->create();
    $dept = Department::factory()->create();

    $this->service->insertTeam($org);
    $this->service->insertTeam($dept, $org);

    // Attempting to make org a child of dept should detect cycle
    expect($this->service->detectCycle($org, $dept))->toBeTrue();
    expect($this->service->detectCycle($dept, $org))->toBeFalse();
});

test('hierarchy statistics are accurate', function () {
    $org = Organization::factory()->create();
    $dept1 = Department::factory()->create();
    $dept2 = Department::factory()->create();
    $project = Project::factory()->create();

    $this->service->insertTeam($org);
    $this->service->insertTeam($dept1, $org);
    $this->service->insertTeam($dept2, $org);
    $this->service->insertTeam($project, $dept1);

    $stats = $this->service->getHierarchyStats();

    expect($stats['total_teams'])->toBe(4);
    expect($stats['max_depth'])->toBe(2);
    expect($stats['root_teams'])->toBe(1);
    expect($stats['leaf_teams'])->toBe(2); // dept2 and project
});
```

## Common Pitfalls & Troubleshooting

### Issue 1: Closure Table Out of Sync
**Problem**: Manual team updates bypass closure table maintenance
**Solution**: Always use service methods or implement database triggers

### Issue 2: Performance Issues with Large Hierarchies
**Problem**: Slow queries on deep or wide hierarchies
**Solution**: Ensure proper indexing and consider query optimization

### Issue 3: Memory Issues During Rebuild
**Problem**: Rebuilding large closure tables consumes too much memory
**Solution**: Process in batches and use chunked queries

## Integration Points

### Connection to Other UMS-STI Components
- **Permission System (Task 4.0)**: Hierarchy-aware permission inheritance (explicitly disabled)
- **User Models (Task 2.0)**: Users belong to teams through polymorphic relationships
- **FilamentPHP Interface (Task 6.0)**: Hierarchy visualization and management
- **API Layer (Task 7.0)**: Hierarchy navigation endpoints

## Further Reading & Resources

### Closure Table Theory
- [Closure Table Pattern](https://www.slideshare.net/billkarwin/models-for-hierarchical-data)
- [SQL Antipatterns: Avoiding the Pitfalls of Database Programming](https://pragprog.com/titles/bksqla/sql-antipatterns/)

### Performance Optimization
- [SQLite Query Optimization](https://www.sqlite.org/optoverview.html)
- [Database Indexing Strategies](https://use-the-index-luke.com/)

## References and Citations

### Primary Sources
- [Laravel 12.x Database Migrations](https://laravel.com/docs/12.x/migrations)
- [Laravel 12.x Eloquent Relationships](https://laravel.com/docs/12.x/eloquent-relationships)
- [SQLite Foreign Key Support](https://www.sqlite.org/foreignkeys.html)
- [Database Indexing Best Practices](https://use-the-index-luke.com/)

### Secondary Sources
- [Closure Table Pattern by Bill Karwin](https://www.slideshare.net/billkarwin/models-for-hierarchical-data)
- [SQL Antipatterns: Avoiding Database Pitfalls](https://pragprog.com/titles/bksqla/sql-antipatterns/)
- [Hierarchical Data in MySQL](https://mikehillyer.com/articles/managing-hierarchical-data-in-mysql/)
- [Tree Structures in Relational Databases](https://vadimtropashko.wordpress.com/2008/08/09/one-more-nested-intervals-vs-adjacency-list-comparison/)

### Related UMS-STI Documentation
- [Team STI Implementation](02-team-sti-implementation.md) - Next implementation step
- [Hierarchy Query Optimization](03-hierarchy-query-optimization.md) - Performance tuning
- [Membership Role System](04-membership-role-system.md) - Team access patterns
- [SQLite WAL Optimization](../01-database-foundation/01-sqlite-wal-optimization.md) - Database foundation
- [Permission Isolation Design](../04-permission-system/02-permission-isolation-design.md) - Security integration
- [Unit Testing Strategies](../08-testing-suite/01-unit-testing-strategies.md) - Testing patterns
- [PRD Requirements](../../prd-UMS-STI.md) - Team hierarchy specifications (REQ-015, REQ-018)
- [Decision Log](../../decision-log-UMS-STI.md) - Closure table decision rationale (DECISION-002)

### Laravel 12.x Compatibility Notes
- Enhanced migration patterns with explicit constraint naming
- Improved indexing strategies for complex queries
- Updated testing utilities with Pest PHP integration
- Optimized foreign key constraint handling
- Enhanced query builder performance for hierarchical data

---

**Next Steps**: Proceed to [Team STI Implementation](02-team-sti-implementation.md) to implement the team type hierarchy with closure table integration.
