# 1. Filament Models Documentation Index

> **Refactored from:** `.ai/guides/chinook/filament/models/000-models-index.md` on 2025-07-11  
> **Focus:** Single taxonomy system using aliziodev/laravel-taxonomy package exclusively

## 1.1. Documentation Structure

### 1.1.1. Core Model Standards
1. **Model Architecture** - Laravel 12 modern patterns and conventions *(Documentation pending)*
2. **Required Traits** - HasTaxonomies, HasSecondaryUniqueKey, HasSlug, HasMedia *(Documentation pending)*
3. **Casting Patterns** - Modern casts() method usage instead of $casts property *(Documentation pending)*
4. **Relationship Patterns** - Eloquent relationships and optimization *(Documentation pending)*

### 1.1.2. Specialized Implementations
1. **Hierarchical Models** - Taxonomy hierarchy with closure table architecture *(Documentation pending)*
2. **Polymorphic Models** - HasTaxonomies trait and polymorphic relationships *(Documentation pending)*
3. **User Stamps** - Audit trail implementation with wildside/userstamps *(Documentation pending)*
4. **Soft Deletes** - Safe deletion patterns and restoration *(Documentation pending)*

### 1.1.3. Business Logic
1. **Model Factories** - Laravel 12 factory patterns for testing *(Documentation pending)*
2. **[Taxonomy Integration](090-taxonomy-integration.md)** - **Single taxonomy system implementation**
3. **Model Observers** - Event handling and business logic *(Documentation pending)*
4. **Model Policies** - Authorization and access control *(Documentation pending)*

## 1.2. Standard Model Implementation

### 1.2.1. Base Model Structure

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Wildside\Userstamps\Userstamps;

class ExampleModel extends Model implements HasMedia
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTaxonomies;  // Single taxonomy system
    use SoftDeletes;
    use Userstamps;
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     */
    protected $table = 'example_models';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'slug',
        'public_id',
        'is_active',
        'sort_order',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the secondary unique key for this model.
     */
    public function getSecondaryUniqueKey(): string
    {
        return 'public_id';
    }

    /**
     * Get the slug source attribute.
     */
    public function getSlugSourceAttribute(): string
    {
        return 'public_id';
    }

    /**
     * Get the slug key for this model.
     */
    public function getSlugKey(): string
    {
        return 'slug';
    }

    // Taxonomy methods are automatically available via HasTaxonomies trait:
    // $model->taxonomies
    // $model->attachTaxonomy($taxonomy, $term)
    // $model->detachTaxonomy($taxonomy, $term)
    // $model->syncTaxonomies($taxonomies)
}
```

### 1.2.2. Taxonomy Integration

**Using aliziodev/laravel-taxonomy Package:**

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm;

class ChinookTrack extends Model
{
    use HasTaxonomies;

    /**
     * Get genres for this track.
     */
    public function getGenres()
    {
        return $this->taxonomies()
            ->whereHas('taxonomy', function ($query) {
                $query->where('slug', 'music-genres');
            })
            ->get();
    }

    /**
     * Assign a genre to this track.
     */
    public function assignGenre(string $genreName): void
    {
        $genreTaxonomy = Taxonomy::where('slug', 'music-genres')->first();
        $genreTerm = TaxonomyTerm::where('name', $genreName)
            ->where('taxonomy_id', $genreTaxonomy->id)
            ->first();

        if ($genreTerm) {
            $this->attachTaxonomy($genreTaxonomy, $genreTerm);
        }
    }

    /**
     * Get all taxonomy types for this track.
     */
    public function getTaxonomyTypes(): array
    {
        return $this->taxonomies()
            ->with('taxonomy')
            ->get()
            ->groupBy('taxonomy.slug')
            ->keys()
            ->toArray();
    }

    /**
     * Scope to filter by taxonomy.
     */
    public function scopeWithTaxonomy($query, string $taxonomySlug, string $termName = null)
    {
        return $query->whereHas('taxonomies', function ($q) use ($taxonomySlug, $termName) {
            $q->whereHas('taxonomy', function ($taxonomyQuery) use ($taxonomySlug) {
                $taxonomyQuery->where('slug', $taxonomySlug);
            });
            
            if ($termName) {
                $q->whereHas('term', function ($termQuery) use ($termName) {
                    $termQuery->where('name', $termName);
                });
            }
        });
    }

    /**
     * Scope to filter by multiple taxonomies (AND logic).
     */
    public function scopeWithAllTaxonomies($query, array $taxonomyTerms)
    {
        foreach ($taxonomyTerms as $taxonomySlug => $termName) {
            $query->withTaxonomy($taxonomySlug, $termName);
        }
        return $query;
    }

    /**
     * Scope to filter by any of the given taxonomies (OR logic).
     */
    public function scopeWithAnyTaxonomy($query, array $taxonomyTerms)
    {
        return $query->whereHas('taxonomies', function ($q) use ($taxonomyTerms) {
            $q->where(function ($subQuery) use ($taxonomyTerms) {
                foreach ($taxonomyTerms as $taxonomySlug => $termName) {
                    $subQuery->orWhere(function ($termQuery) use ($taxonomySlug, $termName) {
                        $termQuery->whereHas('taxonomy', function ($taxonomyQuery) use ($taxonomySlug) {
                            $taxonomyQuery->where('slug', $taxonomySlug);
                        })->whereHas('term', function ($termSubQuery) use ($termName) {
                            $termSubQuery->where('name', $termName);
                        });
                    });
                }
            });
        });
    }
}
```

### 1.2.3. Taxonomy Helper Methods

**Bulk Taxonomy Operations:**

```php
/**
 * Assign multiple taxonomies to a model.
 */
public function assignTaxonomies(array $taxonomyData): void
{
    foreach ($taxonomyData as $taxonomySlug => $termNames) {
        $taxonomy = Taxonomy::where('slug', $taxonomySlug)->first();
        
        if (!$taxonomy) {
            continue;
        }

        foreach ((array) $termNames as $termName) {
            $term = TaxonomyTerm::where('name', $termName)
                ->where('taxonomy_id', $taxonomy->id)
                ->first();
                
            if ($term) {
                $this->attachTaxonomy($taxonomy, $term);
            }
        }
    }
}

/**
 * Sync taxonomies for a model.
 */
public function syncTaxonomies(array $taxonomyData): void
{
    // Detach all existing taxonomies
    $this->taxonomies()->detach();
    
    // Assign new taxonomies
    $this->assignTaxonomies($taxonomyData);
}

/**
 * Get taxonomy summary for display.
 */
public function getTaxonomySummary(): array
{
    return $this->taxonomies()
        ->with(['taxonomy', 'term'])
        ->get()
        ->groupBy('taxonomy.name')
        ->map(function ($items) {
            return $items->pluck('term.name')->toArray();
        })
        ->toArray();
}
```

## 1.3. Single Taxonomy Architecture

### 1.3.1. Package Benefits

**aliziodev/laravel-taxonomy Advantages:**

- **Closure Table Pattern**: Efficient hierarchical queries with unlimited depth
- **Polymorphic Relationships**: Attach taxonomies to any model using HasTaxonomies trait
- **Performance Optimized**: Built-in caching and query optimization
- **Laravel 12 Compatible**: Modern syntax and framework integration
- **Single Source of Truth**: Unified categorization system

### 1.3.2. Migration from Legacy Systems

**Genre Preservation Strategy:**

```php
// Legacy ChinookGenre model (for compatibility)
class ChinookGenre extends Model
{
    protected $table = 'chinook_genres';
    
    /**
     * Get the corresponding taxonomy term.
     */
    public function getTaxonomyTerm(): ?TaxonomyTerm
    {
        $genreTaxonomy = Taxonomy::where('slug', 'music-genres')->first();
        
        return TaxonomyTerm::where('name', $this->name)
            ->where('taxonomy_id', $genreTaxonomy->id)
            ->first();
    }
    
    /**
     * Migrate this genre to taxonomy system.
     */
    public function migrateToTaxonomy(): TaxonomyTerm
    {
        $genreTaxonomy = Taxonomy::firstOrCreate([
            'slug' => 'music-genres'
        ], [
            'name' => 'Music Genres',
            'description' => 'Musical genre classifications'
        ]);
        
        return TaxonomyTerm::firstOrCreate([
            'name' => $this->name,
            'taxonomy_id' => $genreTaxonomy->id
        ], [
            'slug' => \Str::slug($this->name),
            'description' => "Genre: {$this->name}"
        ]);
    }
}
```

### 1.3.3. Performance Optimization

**Efficient Taxonomy Queries:**

```php
// Eager load taxonomies with relationships
$tracks = ChinookTrack::with(['taxonomies.taxonomy', 'taxonomies.term'])->get();

// Get models with specific taxonomy counts
$artists = ChinookArtist::withCount([
    'taxonomies as genre_count' => function ($query) {
        $query->whereHas('taxonomy', function ($q) {
            $q->where('slug', 'music-genres');
        });
    }
])->get();

// Cache frequently accessed taxonomies
$musicGenres = Cache::remember('music_genres', 3600, function () {
    return Taxonomy::where('slug', 'music-genres')
        ->with('terms')
        ->first();
});
```

## 1.4. Model Standards Compliance

### 1.4.1. Required Traits

**Standard Trait Stack:**
- `HasFactory` - Laravel factory support
- `HasSecondaryUniqueKey` - Public ID management
- `HasSlug` - URL-friendly slug generation
- `HasTaxonomies` - **Single taxonomy system integration**
- `SoftDeletes` - Safe deletion patterns
- `Userstamps` - Audit trail tracking
- `InteractsWithMedia` - Media library integration (when needed)

### 1.4.2. Modern Laravel 12 Patterns

**Casting with casts() Method:**
```php
protected function casts(): array
{
    return [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

**Attribute Accessors/Mutators:**
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function name(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst($value),
        set: fn (string $value) => strtolower($value),
    );
}
```

### 1.4.3. Relationship Optimization

**Efficient Relationship Loading:**
```php
// Eager loading with constraints
$albums = ChinookAlbum::with([
    'tracks' => function ($query) {
        $query->where('is_active', true);
    },
    'tracks.taxonomies.taxonomy',
    'tracks.taxonomies.term'
])->get();

// Lazy eager loading
$albums->load('artist.taxonomies');

// Relationship counting
$artists = ChinookArtist::withCount(['albums', 'tracks'])->get();
```

## 1.5. Testing Integration

### 1.5.1. Factory Patterns

**Model Factory with Taxonomy:**
```php
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm;

class ChinookTrackFactory extends Factory
{
    protected $model = ChinookTrack::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'duration' => $this->faker->numberBetween(30, 600),
            'is_active' => true,
        ];
    }

    public function withGenre(string $genreName = null): static
    {
        return $this->afterCreating(function (ChinookTrack $track) use ($genreName) {
            $genreTaxonomy = Taxonomy::firstOrCreate([
                'slug' => 'music-genres'
            ], [
                'name' => 'Music Genres'
            ]);

            $term = TaxonomyTerm::firstOrCreate([
                'name' => $genreName ?? $this->faker->randomElement(['Rock', 'Jazz', 'Classical']),
                'taxonomy_id' => $genreTaxonomy->id
            ]);

            $track->attachTaxonomy($genreTaxonomy, $term);
        });
    }
}
```

### 1.5.2. Testing Patterns

**Taxonomy Testing:**
```php
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm;

test('can assign taxonomy to model', function () {
    $track = ChinookTrack::factory()->create();
    $taxonomy = Taxonomy::factory()->create(['slug' => 'music-genres']);
    $term = TaxonomyTerm::factory()->create(['taxonomy_id' => $taxonomy->id]);

    $track->attachTaxonomy($taxonomy, $term);

    expect($track->taxonomies)->toHaveCount(1);
    expect($track->taxonomies->first()->term->name)->toBe($term->name);
});

test('can filter by taxonomy', function () {
    $rockTaxonomy = Taxonomy::factory()->create(['slug' => 'music-genres']);
    $rockTerm = TaxonomyTerm::factory()->create([
        'name' => 'Rock',
        'taxonomy_id' => $rockTaxonomy->id
    ]);

    $rockTrack = ChinookTrack::factory()->create();
    $jazzTrack = ChinookTrack::factory()->create();

    $rockTrack->attachTaxonomy($rockTaxonomy, $rockTerm);

    $rockTracks = ChinookTrack::withTaxonomy('music-genres', 'Rock')->get();

    expect($rockTracks)->toHaveCount(1);
    expect($rockTracks->first()->id)->toBe($rockTrack->id);
});
```

## 1.6. Documentation Navigation

### 1.6.1. Related Documentation
- **[Taxonomy Resource](../resources/040-taxonomy-resource.md)** - Filament resource for taxonomy management
- **Testing Strategies** - Comprehensive testing approaches *(Documentation pending)*
- **Performance Optimization** - Model performance patterns *(Documentation pending)*

### 1.6.2. External Resources
- **[aliziodev/laravel-taxonomy Documentation](https://github.com/aliziodev/laravel-taxonomy)** - Package documentation
- **[Laravel 12 Eloquent](https://laravel.com/docs/12.x/eloquent)** - Official Laravel documentation

---

## Navigation

**Index:** [Filament Documentation](../000-filament-index.md) | **Next:** [Taxonomy Integration](090-taxonomy-integration.md)

---

**Documentation Standards**: This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns.

[⬆️ Back to Top](#1-filament-models-documentation-index)
