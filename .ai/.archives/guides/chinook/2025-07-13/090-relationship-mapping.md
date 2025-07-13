# Chinook Database Relationship Mapping Guide

## Table of Contents

- [1. Overview](#1-overview)
- [2. Core Entity Relationships](#2-core-entity-relationships)
- [3. Polymorphic Relationships](#3-polymorphic-relationships)
- [4. Hierarchical Relationships](#4-hierarchical-relationships)
- [5. RBAC Relationships](#5-rbac-relationships)
- [6. Implementation Examples](#6-implementation-examples)
- [7. Performance Considerations](#7-performance-considerations)
- [8. Testing Relationships](#8-testing-relationships)

## 1. Overview

This guide provides comprehensive mapping of all relationships in the Chinook database implementation, including
traditional foreign key relationships, polymorphic associations, and hierarchical structures using Laravel 12 modern
patterns.

### 1.1. Relationship Types

**Core Relationship Types:**

- **One-to-Many**: Artist → Albums, Album → Tracks
- **Many-to-Many**: Playlists ↔ Tracks, Users ↔ Roles
- **Polymorphic**: Categories ↔ All Models (Categorizable)
- **Hierarchical**: Categories (Closure Table + Adjacency List)
- **Self-Referencing**: Employee → Manager

### 1.2. Modern Laravel 12 Features

- **Attribute Casting**: Using `cast()` method for type safety
- **Relationship Caching**: Optimized query performance
- **Eager Loading**: Preventing N+1 query problems
- **Relationship Constraints**: Database-level integrity

## 2. Core Entity Relationships

### 2.1. Music Catalog Relationships

```php
// Artist Model
class Artist extends Model
{
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }
    
    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(Track::class, Album::class);
    }
}

// Album Model  
class Album extends Model
{
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
    
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}

// Track Model
class Track extends Model
{
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }
    
    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }
    
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_tracks')
                    ->withPivot(['created_at', 'updated_at']);
    }
}
```

### 2.2. Customer & Sales Relationships

```php
// Customer Model
class Customer extends Model
{
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
    
    public function supportEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'support_rep_id');
    }
}

// Invoice Model
class Invoice extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
    
    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(Track::class, InvoiceLine::class);
    }
}
```

## 3. Polymorphic Relationships

### 3.1. Categorizable System

```php
// Category Model
class Category extends Model
{
    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function artists(): MorphToMany
    {
        return $this->morphedByMany(Artist::class, 'categorizable');
    }
    
    public function albums(): MorphToMany
    {
        return $this->morphedByMany(Album::class, 'categorizable');
    }
    
    public function tracks(): MorphToMany
    {
        return $this->morphedByMany(Track::class, 'categorizable');
    }
}

// Categorizable Trait
trait Categorizable
{
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }
    
    public function categoriesByType(CategoryType $type): MorphToMany
    {
        return $this->categories()->where('type', $type);
    }
}
```

### 3.2. Taggable System

```php
// Using Spatie Tags
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    
    public function syncTagsWithType(array $tags, string $type = null): void
    {
        $this->syncTags($tags, $type);
    }
}
```

## 4. Hierarchical Relationships

### 4.1. Category Hierarchy (Hybrid Approach)

```php
// Category Model - Adjacency List
class Category extends Model
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    
    public function descendants(): HasManyThrough
    {
        return $this->hasManyThrough(
            Category::class,
            CategoryClosure::class,
            'ancestor_id',
            'id',
            'id',
            'descendant_id'
        );
    }
    
    public function ancestors(): HasManyThrough
    {
        return $this->hasManyThrough(
            Category::class,
            CategoryClosure::class,
            'descendant_id',
            'id',
            'id',
            'ancestor_id'
        );
    }
}

// CategoryClosure Model - Closure Table
class CategoryClosure extends Model
{
    protected $table = 'category_closures';
    
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'ancestor_id');
    }
    
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'descendant_id');
    }
}
```

### 4.2. Employee Hierarchy

```php
// Employee Model
class Employee extends Model
{
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reports_to');
    }
    
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'reports_to');
    }
    
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'support_rep_id');
    }
}
```

## 5. RBAC Relationships

### 5.1. User-Role-Permission System

```php
// User Model
class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id')
                    ->where('model_type', static::class);
    }
    
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'model_has_permissions', 'model_id', 'permission_id')
                    ->where('model_type', static::class);
    }
}

// Role Model
class Role extends Model
{
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }
    
    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }
}
```

## 6. Implementation Examples

### 6.1. Eager Loading Strategies

```php
// Optimized queries with relationships
$artists = Artist::with([
    'albums.tracks.mediaType',
    'albums.tracks.categories' => function ($query) {
        $query->where('type', CategoryType::GENRE);
    }
])->get();

// Conditional eager loading
$tracks = Track::with([
    'album.artist',
    'categories' => function ($query) use ($categoryType) {
        if ($categoryType) {
            $query->where('type', $categoryType);
        }
    }
])->paginate(50);
```

### 6.2. Relationship Constraints

```php
// Database constraints
Schema::table('albums', function (Blueprint $table) {
    $table->foreign('artist_id')->references('id')->on('artists')
          ->onDelete('cascade');
});

// Model constraints
class Album extends Model
{
    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function ($album) {
            $album->tracks()->delete();
        });
    }
}
```

## 7. Performance Considerations

### 7.1. Query Optimization

```php
// Use select to limit columns
$artists = Artist::select(['id', 'name'])
    ->with(['albums:id,artist_id,title'])
    ->get();

// Use exists for conditional queries
$artistsWithAlbums = Artist::whereHas('albums')->get();

// Use counts for statistics
$artists = Artist::withCount(['albums', 'tracks'])->get();
```

### 7.2. Caching Strategies

```php
// Relationship caching
class Artist extends Model
{
    public function getPopularAlbumsAttribute()
    {
        return Cache::remember(
            "artist.{$this->id}.popular_albums",
            3600,
            fn() => $this->albums()->orderBy('sales_count', 'desc')->limit(5)->get()
        );
    }
}
```

## 8. Testing Relationships

### 8.1. Relationship Tests

```php
// Test relationship existence
test('artist has many albums', function () {
    $artist = Artist::factory()->create();
    $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);
    
    expect($artist->albums)->toHaveCount(3);
    expect($artist->albums->first())->toBeInstanceOf(Album::class);
});

// Test polymorphic relationships
test('track can have categories', function () {
    $track = Track::factory()->create();
    $category = Category::factory()->create(['type' => CategoryType::GENRE]);
    
    $track->categories()->attach($category);
    
    expect($track->categories)->toHaveCount(1);
    expect($track->categories->first()->type)->toBe(CategoryType::GENRE);
});
```

### 8.2. Performance Testing

```php
// Test N+1 query prevention
test('eager loading prevents n+1 queries', function () {
    Artist::factory()->count(10)->create();
    Album::factory()->count(30)->create();
    
    DB::enableQueryLog();
    
    $artists = Artist::with('albums')->get();
    $artists->each(fn($artist) => $artist->albums->count());
    
    $queryCount = count(DB::getQueryLog());
    expect($queryCount)->toBeLessThanOrEqual(2); // 1 for artists, 1 for albums
});
```

---

**Next Steps:**

- [Database Schema Guide](020-chinook-migrations-guide.md) - Database structure implementation
- [Model Implementation Guide](010-chinook-models-guide.md) - Complete model definitions
- [Performance Testing Guide](testing/090-performance-testing-guide.md) - Query optimization strategies

**Related Documentation:**

- [Filament Resources](filament/resources/000-resources-index.md) - Admin panel relationships
- [Testing Guide](testing/030-feature-testing-guide.md) - Relationship testing patterns
