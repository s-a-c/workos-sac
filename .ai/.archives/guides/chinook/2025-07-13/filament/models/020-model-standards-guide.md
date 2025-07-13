# Model Standards Guide

## Overview

This guide establishes comprehensive model standards for the Chinook Laravel implementation, focusing on
enterprise-grade patterns, trait integration, and modern Laravel 12 syntax.

## Table of Contents

- [Overview](#overview)
- [Model Architecture](#model-architecture)
- [Required Traits](#required-traits)
- [Laravel 12 Modern Patterns](#laravel-12-modern-patterns)
- [Polymorphic Relationships](#polymorphic-relationships)
- [Performance Optimization](#performance-optimization)
- [Testing Standards](#testing-standards)
- [Best Practices](#best-practices)

## Model Architecture

### Enterprise Model Foundation

All Chinook models follow a standardized architecture with comprehensive trait integration:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Tags\HasTags;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;
use App\Traits\Categorizable;
use App\Traits\UserStamps;

class Artist extends Model
{
    use SoftDeletes;
    use HasTags;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use Categorizable;
    use UserStamps;

    protected $fillable = [
        'name',
        'biography',
        'website',
        'social_links',
        'country',
        'formed_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'formed_year' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
```

## Required Traits

### Core Trait Integration

Every Chinook model must implement these essential traits:

1. **SoftDeletes**: Data preservation with audit trails
2. **HasTags**: Flexible tagging system via Spatie
3. **HasSecondaryUniqueKey**: Public-facing identifiers (ULID/UUID/Snowflake)
4. **HasSlug**: URL-friendly identifiers
5. **HasTaxonomies**: Single taxonomy system via aliziodev/laravel-taxonomy
6. **UserStamps**: Audit trail tracking (created_by, updated_by)

### Trait Implementation Standards

```php
// Secondary unique key configuration
protected function getSecondaryKeyType(): string
{
    return match (static::class) {
        ChinookArtist::class => 'ulid',
        ChinookAlbum::class => 'ulid',
        ChinookTrack::class => 'snowflake',
        ChinookCategory::class => 'uuid',
        default => 'uuid',
    };
}

// Slug configuration
protected function getSlugSource(): string
{
    return 'public_id';
}

// Category type configuration
protected function getCategoryTypes(): array
{
    return [
        CategoryType::GENRE,
        CategoryType::MOOD,
        CategoryType::THEME,
        CategoryType::ERA,
    ];
}
```

## Laravel 12 Modern Patterns

### Casts Method Implementation

All models use the modern `casts()` method instead of the `$casts` property:

```php
protected function casts(): array
{
    return [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'release_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

### Attribute Accessors and Mutators

Modern attribute handling using Laravel 12 patterns:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function name(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucwords($value),
        set: fn (string $value) => strtolower($value),
    );
}

protected function formattedPrice(): Attribute
{
    return Attribute::make(
        get: fn () => '$' . number_format($this->unit_price, 2),
    );
}
```

## Polymorphic Relationships

### Category Relationships

All models support polymorphic category relationships:

```php
public function categories(): MorphToMany
{
    return $this->morphToMany(
        Category::class,
        'categorizable',
        'categorizables'
    )->withPivot(['metadata', 'sort_order', 'is_primary'])
      ->withTimestamps();
}

public function primaryCategory(): BelongsTo
{
    return $this->categories()
        ->wherePivot('is_primary', true)
        ->first();
}
```

### Relationship Optimization

```php
// Eager loading with constraints
public function scopeWithCategories($query, array $types = [])
{
    return $query->with(['categories' => function ($query) use ($types) {
        if (!empty($types)) {
            $query->whereIn('type', $types);
        }
        $query->orderBy('sort_order');
    }]);
}
```

## Performance Optimization

### Query Optimization

```php
// Efficient category filtering
public function scopeInCategory($query, $categoryId)
{
    return $query->whereHas('categories', function ($query) use ($categoryId) {
        $query->where('categories.id', $categoryId);
    });
}

// Bulk operations
public function scopeActivateAll($query)
{
    return $query->update(['is_active' => true]);
}
```

### Caching Strategies

```php
public function getCachedCategoriesAttribute()
{
    return Cache::remember(
        "model.{$this->id}.categories",
        now()->addHour(),
        fn () => $this->categories()->get()
    );
}
```

## Testing Standards

### Model Testing Requirements

```php
use Tests\TestCase;
use App\Models\Artist;

class ArtistTest extends TestCase
{
    /** @test */
    public function it_has_required_traits()
    {
        $artist = new Artist();
        
        $this->assertArrayHasKey('deleted_at', $artist->getCasts());
        $this->assertTrue(method_exists($artist, 'tags'));
        $this->assertTrue(method_exists($artist, 'categories'));
        $this->assertNotNull($artist->public_id);
        $this->assertNotNull($artist->slug);
    }

    /** @test */
    public function it_generates_proper_secondary_keys()
    {
        $artist = Artist::factory()->create();
        
        $this->assertMatchesRegularExpression(
            '/^[0-9A-HJKMNP-TV-Z]{26}$/',
            $artist->public_id
        );
        $this->assertNotEmpty($artist->slug);
    }
}
```

## Best Practices

### Model Organization

1. **Consistent Trait Order**: Always use the same trait order across models
2. **Fillable Security**: Explicitly define fillable attributes
3. **Cast Definitions**: Use modern cast() method for all type casting
4. **Relationship Methods**: Group relationships by type (belongsTo, hasMany, etc.)
5. **Scope Methods**: Prefix with 'scope' and use descriptive names

### Security Considerations

```php
protected $fillable = [
    'name',
    'description',
    // Never include: id, public_id, created_by, updated_by
];

protected $hidden = [
    'id', // Hide internal IDs from API responses
];

protected $appends = [
    'public_id', // Always include public identifiers
];
```

### Documentation Standards

Every model should include comprehensive PHPDoc:

```php
/**
 * Artist Model
 * 
 * Represents musical artists and bands with comprehensive
 * categorization, tagging, and audit trail support.
 * 
 * @property string $public_id ULID public identifier
 * @property string $slug URL-friendly identifier
 * @property string $name Artist or band name
 * @property array $social_links Social media links
 * @property bool $is_active Active status
 * 
 * @method static Builder inCategory(int $categoryId)
 * @method static Builder withCategories(array $types = [])
 */
class ChinookArtist extends Model
{
    // Implementation
}
```

---

**Next**: [Required Traits Guide](020-required-traits.md) | **Back**: [Models Index](000-models-index.md)

---

*This guide ensures consistent, enterprise-grade model implementation across the entire Chinook application.*
