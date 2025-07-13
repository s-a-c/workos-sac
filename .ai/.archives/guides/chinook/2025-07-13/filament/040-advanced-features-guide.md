# Advanced Features Guide

## Overview

This guide covers enterprise-level advanced features for the Chinook Filament implementation, including RBAC,
hierarchical categories, performance optimization, and API authentication.

## Table of Contents

- [Overview](#overview)
- [Role-Based Access Control (RBAC)](#role-based-access-control-rbac)
- [Hierarchical Category Management](#hierarchical-category-management)
- [Performance Optimization](#performance-optimization)
- [API Authentication](#api-authentication)
- [Advanced Widgets](#advanced-widgets)
- [Custom Actions](#custom-actions)
- [Real-time Features](#real-time-features)
- [Testing Strategies](#testing-strategies)

## Role-Based Access Control (RBAC)

### Hierarchical Role Structure

The Chinook implementation uses a 7-tier hierarchical role system:

```php
// config/permission.php
return [
    'roles' => [
        'super-admin' => [
            'name' => 'Super Administrator',
            'level' => 1,
            'permissions' => ['*'], // All permissions
        ],
        'admin' => [
            'name' => 'Administrator', 
            'level' => 2,
            'permissions' => ['admin.*', 'manage.*'],
        ],
        'manager' => [
            'name' => 'Manager',
            'level' => 3,
            'permissions' => ['view.*', 'edit.*', 'create.*'],
        ],
        'editor' => [
            'name' => 'Editor',
            'level' => 4,
            'permissions' => ['view.*', 'edit.*'],
        ],
        'customer-service' => [
            'name' => 'Customer Service',
            'level' => 5,
            'permissions' => ['view.customers', 'edit.customers', 'view.invoices'],
        ],
        'user' => [
            'name' => 'User',
            'level' => 6,
            'permissions' => ['view.own-data'],
        ],
        'guest' => [
            'name' => 'Guest',
            'level' => 7,
            'permissions' => ['view.public'],
        ],
    ],
];
```

### Permission System

Granular permissions with kebab-case naming:

```php
// Database seeder for permissions
$permissions = [
    // Artist permissions
    'view-artists',
    'create-artists', 
    'edit-artists',
    'delete-artists',
    'manage-artist-categories',
    
    // Album permissions
    'view-albums',
    'create-albums',
    'edit-albums', 
    'delete-albums',
    'manage-album-media',
    
    // Category permissions
    'view-categories',
    'create-categories',
    'edit-categories',
    'delete-categories',
    'manage-category-hierarchy',
    
    // Advanced permissions
    'bulk-operations',
    'export-data',
    'import-data',
    'view-analytics',
    'manage-system-settings',
];
```

### RBAC Implementation in Resources

```php
// app/Filament/Resources/ArtistResource.php
class ArtistResource extends Resource
{
    public static function canViewAny(): bool
    {
        return auth()->user()->can('view-artists');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create-artists');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('edit-artists') 
            && $this->canAccessRecord($record);
    }

    protected function canAccessRecord(Model $record): bool
    {
        // Additional business logic for record-level access
        if (auth()->user()->hasRole('customer-service')) {
            return $record->is_public;
        }
        
        return true;
    }
}
```

## Hierarchical Category Management

### Hybrid Architecture Implementation

The category system uses both closure table and adjacency list for optimal performance:

```php
// app/Models/Category.php
class Category extends Model
{
    use HasClosureTable;
    use HasAdjacencyList;

    // Adjacency list for fast writes
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Closure table for fast reads
    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'descendant_id',
            'ancestor_id'
        )->withPivot('depth');
    }

    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure', 
            'ancestor_id',
            'descendant_id'
        )->withPivot('depth');
    }
}
```

### Category Operations

```php
// Efficient category tree operations
class CategoryService
{
    public function moveCategory(Category $category, ?Category $newParent): void
    {
        DB::transaction(function () use ($category, $newParent) {
            // Update adjacency list
            $category->update(['parent_id' => $newParent?->id]);
            
            // Rebuild closure table for affected subtree
            $this->rebuildClosureTable($category);
        });
    }

    public function getCategoryTree(CategoryType $type): Collection
    {
        return Cache::remember(
            "category_tree_{$type->value}",
            now()->addHour(),
            fn () => Category::where('type', $type)
                ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get()
        );
    }
}
```

## Performance Optimization

### Caching Strategies

```php
// Multi-layer caching implementation
class CacheService
{
    public function getCachedCategories(string $type): Collection
    {
        return Cache::tags(['categories', $type])
            ->remember(
                "categories.{$type}",
                now()->addHours(6),
                fn () => Category::where('type', $type)
                    ->with('children')
                    ->get()
            );
    }

    public function invalidateCategoryCache(Category $category): void
    {
        Cache::tags(['categories', $category->type->value])->flush();
    }
}
```

### Query Optimization

```php
// Optimized queries for large datasets
class OptimizedQueries
{
    public function getArtistsWithCategories(): Builder
    {
        return Artist::select([
                'id', 'public_id', 'name', 'is_active'
            ])
            ->with([
                'categories:id,name,type',
                'albums' => fn ($q) => $q->select('id', 'artist_id', 'title')
                    ->limit(5)
            ])
            ->whereHas('categories', fn ($q) => 
                $q->where('is_active', true)
            );
    }

    public function getBulkCategoryAssignments(array $modelIds, string $modelType): Collection
    {
        return DB::table('categorizables')
            ->join('categories', 'categories.id', '=', 'categorizables.category_id')
            ->whereIn('categorizable_id', $modelIds)
            ->where('categorizable_type', $modelType)
            ->select([
                'categorizable_id',
                'categories.id as category_id',
                'categories.name',
                'categories.type',
                'is_primary'
            ])
            ->get()
            ->groupBy('categorizable_id');
    }
}
```

## API Authentication

### Laravel Sanctum Integration

```php
// config/sanctum.php
return [
    'abilities' => [
        // Role-based abilities
        'admin:read' => 'Read admin data',
        'admin:write' => 'Write admin data',
        'manager:read' => 'Read manager data',
        'manager:write' => 'Write manager data',
        'user:read' => 'Read user data',
        'user:write' => 'Write user data',
        
        // Resource-specific abilities
        'artists:read' => 'Read artists',
        'artists:write' => 'Write artists',
        'albums:read' => 'Read albums',
        'albums:write' => 'Write albums',
    ],
];

// API token generation with role-based scopes
class AuthController extends Controller
{
    public function createToken(Request $request)
    {
        $user = auth()->user();
        $abilities = $this->getAbilitiesForUser($user);
        
        $token = $user->createToken(
            $request->token_name,
            $abilities,
            now()->addDays(30)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }

    private function getAbilitiesForUser(User $user): array
    {
        $abilities = [];
        
        if ($user->hasRole('admin')) {
            $abilities = ['admin:read', 'admin:write'];
        } elseif ($user->hasRole('manager')) {
            $abilities = ['manager:read', 'manager:write'];
        } else {
            $abilities = ['user:read'];
        }

        return array_merge($abilities, $this->getResourceAbilities($user));
    }
}
```

## Advanced Widgets

### Real-time Analytics Widget

```php
// app/Filament/Widgets/SalesAnalyticsWidget.php
class SalesAnalyticsWidget extends ChartWidget
{
    protected static ?string $heading = 'Sales Analytics';
    protected static ?int $sort = 1;
    
    public function canView(): bool
    {
        return auth()->user()->can('view-analytics');
    }

    protected function getData(): array
    {
        $salesData = Cache::remember(
            'sales_analytics_' . now()->format('Y-m-d-H'),
            now()->addMinutes(30),
            fn () => $this->calculateSalesData()
        );

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $salesData['revenue'],
                    'backgroundColor' => '#1976d2', // WCAG compliant color
                    'borderColor' => '#1976d2',
                ],
                [
                    'label' => 'Units Sold',
                    'data' => $salesData['units'],
                    'backgroundColor' => '#388e3c',
                    'borderColor' => '#388e3c',
                ],
            ],
            'labels' => $salesData['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

## Custom Actions

### Bulk Category Assignment Action

```php
// app/Filament/Actions/BulkCategoryAssignmentAction.php
class BulkCategoryAssignmentAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'assign_categories';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Assign Categories')
            ->icon('heroicon-o-tag')
            ->color('primary')
            ->requiresConfirmation()
            ->form([
                Select::make('categories')
                    ->multiple()
                    ->relationship('categories', 'name')
                    ->preload()
                    ->searchable()
                    ->required(),
                Toggle::make('replace_existing')
                    ->label('Replace existing categories')
                    ->default(false),
            ])
            ->action(function (Collection $records, array $data) {
                $this->assignCategoriesToRecords($records, $data);
            });
    }

    private function assignCategoriesToRecords(Collection $records, array $data): void
    {
        DB::transaction(function () use ($records, $data) {
            foreach ($records as $record) {
                if ($data['replace_existing']) {
                    $record->categories()->detach();
                }
                
                $record->categories()->attach($data['categories']);
            }
        });

        Notification::make()
            ->title('Categories assigned successfully')
            ->success()
            ->send();
    }
}
```

## Real-time Features

### Live Updates with Broadcasting

```php
// app/Events/CategoryUpdated.php
class CategoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Category $category,
        public string $action
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('categories'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'category' => $this->category->only(['id', 'name', 'type']),
            'action' => $this->action,
        ];
    }
}

// Frontend integration
class CategoryResource extends Resource
{
    protected function afterSave(): void
    {
        broadcast(new CategoryUpdated($this->record, 'updated'));
    }
}
```

## Testing Strategies

### RBAC Testing

```php
// tests/Feature/RbacTest.php
class RbacTest extends TestCase
{
    /** @test */
    public function admin_can_access_all_resources()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/admin/artists')
            ->assertSuccessful();
            
        $this->actingAs($admin)
            ->get('/admin/categories')
            ->assertSuccessful();
    }

    /** @test */
    public function customer_service_cannot_access_admin_features()
    {
        $user = User::factory()->create();
        $user->assignRole('customer-service');

        $this->actingAs($user)
            ->get('/admin/system-settings')
            ->assertForbidden();
    }
}
```

### Performance Testing

```php
// tests/Performance/CategoryPerformanceTest.php
class CategoryPerformanceTest extends TestCase
{
    /** @test */
    public function category_tree_loads_efficiently()
    {
        // Create deep category hierarchy
        Category::factory()
            ->count(1000)
            ->create();

        $startTime = microtime(true);
        
        $tree = app(CategoryService::class)->getCategoryTree(CategoryType::GENRE);
        
        $executionTime = microtime(true) - $startTime;
        
        $this->assertLessThan(0.5, $executionTime, 'Category tree should load in under 500ms');
        $this->assertNotEmpty($tree);
    }
}
```

---

**Next**: [Testing Guide](testing/000-testing-index.md) | **Back**: [Resources Guide](resources/000-resources-index.md)

---

*This guide provides comprehensive coverage of advanced enterprise features for the Chinook Filament implementation.*
