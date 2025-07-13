# 2. Translatable Models Setup

**Refactored from:** `.ai/guides/chinook/filament/internationalization/010-translatable-models-setup.md` on 2025-07-13  
**Purpose:** Spatie Laravel Translatable integration for Chinook models with taxonomy support  
**Scope:** Multi-language support for artists, albums, tracks, and taxonomies using modern Laravel 12 patterns

## 2.1 Table of Contents

- [2.1 Table of Contents](#21-table-of-contents)
- [2.2 Overview](#22-overview)
- [2.3 Supported Models](#23-supported-models)
- [2.4 Translation Strategy](#24-translation-strategy)
- [2.5 Model Configuration](#25-model-configuration)
- [2.6 Taxonomy Translation Setup](#26-taxonomy-translation-setup)
- [2.7 Database Migrations](#27-database-migrations)
- [2.8 Usage Examples](#28-usage-examples)

## 2.2 Overview

The Chinook application supports comprehensive internationalization for all music-related content and taxonomies, enabling global music catalogs with localized metadata using spatie/laravel-translatable and aliziodev/laravel-taxonomy integration.

## 2.3 Supported Models

### 2.3.1 Core Music Models with Translation Support
- **Chinook Artist**: Name and biography translations with taxonomy localization
- **Chinook Album**: Title and description translations with genre localization
- **Chinook Track**: Name and metadata translations with mood/style localization
- **Taxonomy**: Name and description translations for all taxonomy types

### 2.3.2 Translation Fields by Model
- **Artists**: name, biography, social_links metadata
- **Albums**: title, description, label information
- **Tracks**: name, composer, metadata descriptions
- **Taxonomies**: name, description, display metadata

## 2.4 Translation Strategy

### 2.4.1 Translation Approach with Taxonomy Integration
- **Taxonomy Preservation**: Original taxonomy data maintained alongside translations
- **Hierarchical Translation**: Maintain taxonomy hierarchy across languages
- **Fallback Support**: Automatic fallback to default language for missing translations
- **Performance Optimization**: Efficient querying with eager loading for translations
- **Cache Integration**: Translated content and taxonomy caching for performance

## 2.5 Model Configuration

### 2.5.1 Chinook Artist Model with Translations

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use App\Traits\HasUserStamps;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;

class ChinookArtist extends Model
{
    use SoftDeletes, HasTranslations, HasTaxonomies, HasUserStamps, HasSecondaryUniqueKey, HasSlug;

    protected $table = 'chinook_artists';

    protected $fillable = [
        'name',
        'biography',
        'website',
        'social_links',
        'country',
        'formed_year',
        'is_active',
    ];

    protected array $translatable = [
        'name',
        'biography',
        'social_links->description',
        'social_links->bio_short',
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

    /**
     * Get localized artist name with fallback
     */
    public function getLocalizedName(string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        
        return $this->getTranslation('name', $locale) 
            ?? $this->getTranslation('name', config('app.fallback_locale'))
            ?? $this->name;
    }

    /**
     * Get artist taxonomies with localized names
     */
    public function getLocalizedTaxonomies(string $type = null, string $locale = null): \Illuminate\Support\Collection
    {
        $query = $this->taxonomies();
        
        if ($type) {
            $query->where('type', $type);
        }
        
        return $query->get()->map(function ($taxonomy) use ($locale) {
            $taxonomy->localized_name = $taxonomy->getLocalizedName($locale);
            return $taxonomy;
        });
    }

    /**
     * Relationship: Albums
     */
    public function albums(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChinookAlbum::class, 'artist_id');
    }
}
```

### 2.5.2 Chinook Album Model with Translations

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;
use App\Traits\HasUserStamps;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;

class ChinookAlbum extends Model
{
    use SoftDeletes, HasTranslations, HasTaxonomies, HasUserStamps, HasSecondaryUniqueKey, HasSlug;

    protected $table = 'chinook_albums';

    protected $fillable = [
        'title',
        'artist_id',
        'description',
        'release_date',
        'label',
        'catalog_number',
        'price',
        'cover_art',
        'is_compilation',
    ];

    protected array $translatable = [
        'title',
        'description',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'price' => 'decimal:2',
            'is_compilation' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get localized album title with fallback
     */
    public function getLocalizedTitle(string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        
        return $this->getTranslation('title', $locale) 
            ?? $this->getTranslation('title', config('app.fallback_locale'))
            ?? $this->title;
    }

    /**
     * Relationship: Artist
     */
    public function artist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ChinookArtist::class, 'artist_id');
    }

    /**
     * Relationship: Tracks
     */
    public function tracks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ChinookTrack::class, 'album_id');
    }
}
```

## 2.6 Taxonomy Translation Setup

### 2.6.1 Extended Taxonomy Model with Translations

```php
<?php

namespace App\Models;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy as BaseTaxonomy;
use Spatie\Translatable\HasTranslations;

class Taxonomy extends BaseTaxonomy
{
    use HasTranslations;

    protected array $translatable = [
        'name',
        'description',
        'meta->display_name',
        'meta->short_description',
        'meta->cultural_notes',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get localized taxonomy name with cultural adaptation
     */
    public function getLocalizedName(string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        
        // Try localized name first
        $localizedName = $this->getTranslation('name', $locale);
        if ($localizedName) {
            return $localizedName;
        }
        
        // Try cultural adaptation from meta
        $culturalName = $this->getTranslation('meta.display_name', $locale);
        if ($culturalName) {
            return $culturalName;
        }
        
        // Fallback to default language
        return $this->getTranslation('name', config('app.fallback_locale')) ?? $this->name;
    }

    /**
     * Get localized taxonomy hierarchy path
     */
    public function getLocalizedPath(string $locale = null, string $separator = ' > '): string
    {
        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->getLocalizedName($locale));
            $current = $current->parent;
        }

        return implode($separator, $path);
    }

    /**
     * Get cultural notes for specific locale
     */
    public function getCulturalNotes(string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        return $this->getTranslation('meta.cultural_notes', $locale);
    }
}
```

## 2.7 Database Migrations

### 2.7.1 Translation Support Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Artists table already exists, add translation support
        Schema::table('chinook_artists', function (Blueprint $table) {
            // Convert existing text fields to JSON for translations
            $table->json('name')->change();
            $table->json('biography')->nullable()->change();
            $table->json('social_links')->nullable()->change();
        });

        // Albums table translation support
        Schema::table('chinook_albums', function (Blueprint $table) {
            $table->json('title')->change();
            $table->json('description')->nullable()->change();
            $table->json('label')->nullable()->change();
        });

        // Tracks table translation support
        Schema::table('chinook_tracks', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('composer')->nullable()->change();
        });

        // Taxonomies table translation support
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
            $table->json('meta')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reverse the changes (convert back to string)
        Schema::table('chinook_artists', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('biography')->nullable()->change();
            $table->json('social_links')->nullable()->change();
        });

        Schema::table('chinook_albums', function (Blueprint $table) {
            $table->string('title')->change();
            $table->text('description')->nullable()->change();
            $table->string('label')->nullable()->change();
        });

        Schema::table('chinook_tracks', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('composer')->nullable()->change();
        });

        Schema::table('taxonomies', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
            $table->json('meta')->nullable()->change();
        });
    }
};
```

## 2.8 Usage Examples

### 2.8.1 Creating Multilingual Content

```php
<?php

// Create artist with multiple language support
$artist = ChinookArtist::create([
    'name' => [
        'en' => 'The Beatles',
        'es' => 'Los Beatles',
        'fr' => 'Les Beatles',
        'de' => 'Die Beatles',
    ],
    'biography' => [
        'en' => 'British rock band formed in Liverpool in 1960.',
        'es' => 'Banda de rock británica formada en Liverpool en 1960.',
        'fr' => 'Groupe de rock britannique formé à Liverpool en 1960.',
        'de' => 'Britische Rockband, gegründet 1960 in Liverpool.',
    ],
    'country' => 'GB',
    'formed_year' => 1960,
]);

// Create taxonomy with translations
$rockGenre = Taxonomy::create([
    'type' => 'genre',
    'name' => [
        'en' => 'Rock',
        'es' => 'Rock',
        'fr' => 'Rock',
        'de' => 'Rock',
    ],
    'description' => [
        'en' => 'A genre of popular music that originated in the 1950s.',
        'es' => 'Un género de música popular que se originó en los años 50.',
        'fr' => 'Un genre de musique populaire qui a vu le jour dans les années 1950.',
        'de' => 'Ein Genre der Popmusik, das in den 1950er Jahren entstand.',
    ],
]);

// Attach taxonomy to artist
$artist->taxonomies()->attach($rockGenre);
```

This comprehensive translatable models setup provides the foundation for robust multi-language support with comprehensive taxonomy integration and modern Laravel 12 patterns.

---

## Navigation

**Previous:** [Internationalization Index](000-internationalization-index.md)  
**Next:** [Internationalization Index](000-internationalization-index.md)  
**Up:** [Internationalization Documentation](000-internationalization-index.md)  
**Home:** [Chinook Documentation](../../README.md)

[⬆️ Back to Top](#2-translatable-models-setup)
