# 1. Translatable Models Setup

## 1.1 Spatie Laravel Translatable Integration for Chinook Models

This guide covers setting up translatable models using `spatie/laravel-translatable` for the Chinook music catalog, enabling multi-language support for artists, albums, tracks, and categories.

## 1.2 Table of Contents

- [1. Translatable Models Setup](#1-translatable-models-setup)
    - [1.1 Spatie Laravel Translatable Integration for Chinook Models](#11-spatie-laravel-translatable-integration-for-chinook-models)
    - [1.2 Table of Contents](#12-table-of-contents)
    - [1.3 Overview](#13-overview)
        - [1.3.1 Supported Models](#131-supported-models)
        - [1.3.2 Translation Strategy](#132-translation-strategy)
    - [1.4 Model Configuration](#14-model-configuration)
        - [1.4.1 Artist Model](#141-artist-model)
        - [1.4.2 Album Model](#142-album-model)
        - [1.4.3 Track Model](#143-track-model)
        - [1.4.4 Category Model](#144-category-model)
    - [1.5 Database Migrations](#15-database-migrations)
        - [1.5.1 Translation Tables](#151-translation-tables)
        - [1.5.2 Index Optimization](#152-index-optimization)
    - [1.6 Usage Examples](#16-usage-examples)
        - [1.6.1 Creating Translations](#161-creating-translations)
        - [1.6.2 Retrieving Translations](#162-retrieving-translations)

## 1.3 Overview

The Chinook application supports comprehensive internationalization for all music-related content, enabling global music catalogs with localized metadata.

### 1.3.1 Supported Models

- **Artist**: Name and biography translations
- **Album**: Title and description translations  
- **Track**: Name and metadata translations
- **Category**: Name and description translations
- **Playlist**: Name and description translations

### 1.3.2 Translation Strategy

- **Genre Preservation**: Original genre data maintained alongside translations
- **Fallback Support**: Automatic fallback to default language
- **Performance Optimization**: Efficient querying with eager loading
- **Cache Integration**: Translated content caching for performance

## 1.4 Model Configuration

### 1.4.1 Artist Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use App\Traits\HasUserStamps;
use App\Traits\HasSecondaryUniqueKey;

class Artist extends Model
{
    use HasTranslations, HasUserStamps, SoftDeletes, HasSecondaryUniqueKey;

    protected $fillable = [
        'name',
        'biography',
        'metadata',
        'is_active',
    ];

    protected array $translatable = [
        'name',
        'biography',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'biography' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale()) 
            ?? $this->getTranslation('name', config('app.fallback_locale'))
            ?? 'Unknown Artist';
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function tracks(): HasManyThrough
    {
        return $this->hasManyThrough(Track::class, Album::class);
    }
}
```

### 1.4.2 Album Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use App\Traits\HasUserStamps;
use App\Traits\HasSecondaryUniqueKey;

class Album extends Model
{
    use HasTranslations, HasUserStamps, SoftDeletes, HasSecondaryUniqueKey;

    protected $fillable = [
        'title',
        'description',
        'artist_id',
        'release_date',
        'metadata',
        'is_active',
    ];

    protected array $translatable = [
        'title',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'release_date' => 'date',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTranslatedTitleAttribute(): string
    {
        return $this->getTranslation('title', app()->getLocale()) 
            ?? $this->getTranslation('title', config('app.fallback_locale'))
            ?? 'Unknown Album';
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
```

### 1.4.3 Track Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use App\Traits\HasUserStamps;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\Categorizable;

class Track extends Model
{
    use HasTranslations, HasUserStamps, SoftDeletes, HasSecondaryUniqueKey, Categorizable;

    protected $fillable = [
        'name',
        'metadata',
        'album_id',
        'genre_id',
        'media_type_id',
        'composer',
        'milliseconds',
        'bytes',
        'unit_price',
        'is_active',
    ];

    protected array $translatable = [
        'name',
        'composer',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'composer' => 'array',
            'metadata' => 'array',
            'milliseconds' => 'integer',
            'bytes' => 'integer',
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale()) 
            ?? $this->getTranslation('name', config('app.fallback_locale'))
            ?? 'Unknown Track';
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function mediaType(): BelongsTo
    {
        return $this->belongsTo(MediaType::class);
    }
}
```

### 1.4.4 Category Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use App\Traits\HasUserStamps;
use App\Traits\HasSecondaryUniqueKey;
use App\Enums\CategoryType;

class Category extends Model
{
    use HasTranslations, HasUserStamps, SoftDeletes, HasSecondaryUniqueKey;

    protected $fillable = [
        'name',
        'description',
        'type',
        'parent_id',
        'sort_order',
        'metadata',
        'is_active',
    ];

    protected array $translatable = [
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'type' => CategoryType::class,
            'sort_order' => 'integer',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale()) 
            ?? $this->getTranslation('name', config('app.fallback_locale'))
            ?? 'Unknown Category';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function tracks(): MorphToMany
    {
        return $this->morphedByMany(Track::class, 'categorizable');
    }
}
```

## 1.5 Database Migrations

### 1.5.1 Translation Tables

The translation data is stored as JSON in the existing model tables, eliminating the need for separate translation tables while maintaining performance.

```php
// Migration example for adding translation support
Schema::table('artists', function (Blueprint $table) {
    $table->json('name')->change();
    $table->json('biography')->nullable()->change();
    $table->index(['name->en', 'name->es', 'name->fr']);
});
```

### 1.5.2 Index Optimization

```php
// Optimized indexes for translation queries
Schema::table('tracks', function (Blueprint $table) {
    $table->index(['name->en']);
    $table->index(['name->es']);
    $table->index(['name->fr']);
    $table->index(['composer->en']);
});
```

## 1.6 Usage Examples

### 1.6.1 Creating Translations

```php
// Creating an artist with multiple translations
$artist = Artist::create([
    'name' => [
        'en' => 'The Beatles',
        'es' => 'Los Beatles',
        'fr' => 'Les Beatles',
    ],
    'biography' => [
        'en' => 'British rock band formed in Liverpool in 1960.',
        'es' => 'Banda de rock británica formada en Liverpool en 1960.',
        'fr' => 'Groupe de rock britannique formé à Liverpool en 1960.',
    ],
]);
```

### 1.6.2 Retrieving Translations

```php
// Get translation for current locale
$artistName = $artist->getTranslation('name', app()->getLocale());

// Get all translations
$allNames = $artist->getTranslations('name');

// Using the helper attribute
$localizedName = $artist->translated_name;
```

---

## 1.7 Navigation

**Internationalization Index**: [Internationalization Documentation](000-internationalization-index.md)
**Package Guide**: [Spatie Translatable Guide](../../packages/150-spatie-laravel-translatable-guide.md)
**Model Architecture**: [Model Standards](../models/010-model-architecture.md)

---

*This implementation follows Laravel 12 modern patterns with WCAG 2.1 AA compliance and comprehensive testing coverage.*
