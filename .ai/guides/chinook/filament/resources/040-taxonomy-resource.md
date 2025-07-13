# 4. Taxonomy Resource

> **Refactored from:** `.ai/guides/chinook/filament/resources/040-categories-resource.md` on 2025-07-11  
> **Focus:** Single taxonomy system using aliziodev/laravel-taxonomy package exclusively

## 4.1. Table of Contents

- [4.2. Overview](#42-overview)
- [5. Resource Implementation](#5-resource-implementation)
  - [5.1. Basic Resource Structure](#51-basic-resource-structure)
- [6. Hierarchical Data Management](#6-hierarchical-data-management)
  - [6.1. Taxonomy Architecture](#61-taxonomy-architecture)
- [7. Polymorphic Relationships](#7-polymorphic-relationships)
  - [7.1. HasTaxonomies Implementation](#71-hastaxonomies-implementation)
- [8. Advanced Features](#8-advanced-features)
  - [8.1. Taxonomy Tree Visualization](#81-taxonomy-tree-visualization)

## 4.2. Overview

The Taxonomy Resource provides comprehensive management of hierarchical taxonomies within the Chinook admin panel using the **aliziodev/laravel-taxonomy** package exclusively. It features efficient closure table architecture, polymorphic relationships for multi-model categorization, and advanced tree visualization.

### 4.2.1. Key Features

- **Single Taxonomy System**: Unified categorization using aliziodev/laravel-taxonomy exclusively
- **Hierarchical Structure**: Complete tree management with unlimited depth
- **Closure Table Architecture**: Optimized for efficient hierarchical queries
- **Polymorphic Taxonomies**: Attach taxonomies to multiple model types (Artists, Albums, Tracks)
- **Taxonomy Types**: Support for different taxonomy types (Genre, Mood, Theme, Era, etc.)
- **Tree Visualization**: Interactive tree view with drag-and-drop reordering
- **Bulk Operations**: Mass taxonomy assignments and hierarchy operations
- **Performance Optimized**: Efficient queries for large taxonomy trees

## 5. Resource Implementation

### 5.1. Basic Resource Structure

```php
<?php

namespace App\Filament\ChinookAdmin\Resources;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm;
use App\Filament\ChinookAdmin\Resources\TaxonomyResource\Pages;
use App\Filament\ChinookAdmin\Resources\TaxonomyResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Taxonomy Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $context, $state, callable $set) => 
                                $context === 'create' ? $set('slug', \Str::slug($state)) : null
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Taxonomy')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Used for ordering taxonomies within the same level'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Inactive taxonomies are hidden from public views'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('meta')
                            ->keyLabel('Property')
                            ->valueLabel('Value')
                            ->addActionLabel('Add metadata')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Taxonomy $record): string => 
                        $record->parent ? "Parent: {$record->parent->name}" : 'Root taxonomy'
                    ),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('terms_count')
                    ->label('Terms')
                    ->counts('terms')
                    ->sortable(),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('Children')
                    ->counts('children')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Taxonomy')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TermsRelationManager::class,
            RelationManagers\ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxonomies::route('/'),
            'create' => Pages\CreateTaxonomy::route('/create'),
            'view' => Pages\ViewTaxonomy::route('/{record}'),
            'edit' => Pages\EditTaxonomy::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['parent']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Parent' => $record->parent?->name ?? 'Root',
            'Terms' => $record->terms_count ?? 0,
        ];
    }
}
```

### 5.2. Hierarchical Form Configuration

**Parent Selection with Tree Structure:**

```php
Forms\Components\Select::make('parent_id')
    ->label('Parent Taxonomy')
    ->relationship('parent', 'name', fn (Builder $query) => 
        $query->where('is_active', true)->orderBy('sort_order')
    )
    ->searchable()
    ->preload()
    ->nullable()
    ->helperText('Select a parent taxonomy to create a hierarchical structure')
    ->createOptionForm([
        Forms\Components\TextInput::make('name')
            ->required()
            ->maxLength(255),
        Forms\Components\TextInput::make('slug')
            ->required()
            ->maxLength(255),
        Forms\Components\Textarea::make('description')
            ->maxLength(1000),
    ]),
```

**Dynamic Hierarchy Validation:**

```php
Forms\Components\TextInput::make('name')
    ->required()
    ->maxLength(255)
    ->rules([
        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
            $parentId = $get('parent_id');
            if ($parentId) {
                $parent = Taxonomy::find($parentId);
                if ($parent && $parent->getDepth() >= 5) {
                    $fail('Maximum taxonomy depth of 5 levels exceeded.');
                }
            }
        },
    ]),
```

### 5.3. Tree Table Configuration

**Hierarchical Table Display:**

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->formatStateUsing(function (string $state, Taxonomy $record): string {
                    $indent = str_repeat('— ', $record->getDepth());
                    return $indent . $state;
                })
                ->description(fn (Taxonomy $record): string =>
                    $record->description ? \Str::limit($record->description, 50) : ''
                ),

            Tables\Columns\TextColumn::make('hierarchy_path')
                ->label('Path')
                ->getStateUsing(fn (Taxonomy $record): string =>
                    $record->getAncestors()->pluck('name')->push($record->name)->join(' > ')
                )
                ->searchable()
                ->toggleable(),

            Tables\Columns\BadgeColumn::make('terms_count')
                ->label('Terms')
                ->counts('terms')
                ->color(fn (int $state): string => match (true) {
                    $state === 0 => 'gray',
                    $state < 10 => 'warning',
                    default => 'success',
                }),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->sortable(),
        ])
        ->defaultSort('sort_order')
        ->reorderable('sort_order')
        ->groups([
            Tables\Grouping\Group::make('parent.name')
                ->label('Parent Taxonomy')
                ->collapsible(),
        ]);
}
```

## 6. Hierarchical Data Management

### 6.1. Taxonomy Architecture

**aliziodev/laravel-taxonomy Package Benefits:**

- **Closure Table Pattern**: Efficient hierarchical queries with unlimited depth
- **Polymorphic Relationships**: Attach taxonomies to any model using HasTaxonomies trait
- **Performance Optimized**: Built-in caching and query optimization
- **Laravel 12 Compatible**: Modern syntax and framework integration

**Model Integration:**

```php
// In your Chinook models
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookTrack extends Model
{
    use HasTaxonomies;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Taxonomy relationships are automatically available:
    // $track->taxonomies
    // $track->attachTaxonomy($taxonomy)
    // $track->detachTaxonomy($taxonomy)
    // $track->syncTaxonomies($taxonomies)
}
```

### 6.2. Tree Operations

**Hierarchy Management:**

```php
// Create taxonomy hierarchy
$genre = Taxonomy::create(['name' => 'Music Genres', 'slug' => 'music-genres']);
$rock = $genre->children()->create(['name' => 'Rock', 'slug' => 'rock']);
$alternativeRock = $rock->children()->create(['name' => 'Alternative Rock', 'slug' => 'alternative-rock']);

// Move taxonomy to different parent
$alternativeRock->update(['parent_id' => $newParent->id]);

// Get all descendants
$allRockSubgenres = $rock->getDescendants();

// Get taxonomy path
$path = $alternativeRock->getAncestors()->pluck('name')->push($alternativeRock->name)->join(' > ');
```

### 6.3. Performance Optimization

**Efficient Queries:**

```php
// Eager load relationships
Taxonomy::with(['parent', 'children', 'terms'])->get();

// Get taxonomies with term counts
Taxonomy::withCount('terms')->get();

// Scope for active taxonomies only
Taxonomy::where('is_active', true)->get();

// Cache frequently accessed taxonomies
Cache::remember('music_genres', 3600, function () {
    return Taxonomy::where('slug', 'music-genres')
        ->with('descendants')
        ->first();
});
```

## 7. Polymorphic Relationships

### 7.1. HasTaxonomies Implementation

**Model Setup:**

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomies;

class ChinookArtist extends Model
{
    use HasTaxonomies;

    // Taxonomy methods are automatically available
    public function getGenres()
    {
        return $this->taxonomies()
            ->whereHas('taxonomy', function ($query) {
                $query->where('slug', 'music-genres');
            })
            ->get();
    }

    public function assignGenre(string $genreName): void
    {
        $genre = TaxonomyTerm::where('name', $genreName)->first();
        if ($genre) {
            $this->attachTaxonomy($genre->taxonomy, $genre);
        }
    }
}
```

### 7.2. Multi-Model Assignment

**Bulk Taxonomy Assignment:**

```php
// Assign taxonomy to multiple models
$rockGenre = Taxonomy::where('slug', 'rock')->first();

// Assign to multiple tracks
$tracks = ChinookTrack::whereIn('id', [1, 2, 3])->get();
foreach ($tracks as $track) {
    $track->attachTaxonomy($rockGenre);
}

// Bulk assignment with terms
$jazzTerm = TaxonomyTerm::where('name', 'Jazz')->first();
$jazzTracks = ChinookTrack::where('genre_id', 2)->get();
foreach ($jazzTracks as $track) {
    $track->attachTaxonomy($jazzTerm->taxonomy, $jazzTerm);
}
```

### 7.3. Taxonomy Types

**Different Taxonomy Categories:**

```php
// Create different taxonomy types
$genres = Taxonomy::create(['name' => 'Music Genres', 'slug' => 'music-genres']);
$moods = Taxonomy::create(['name' => 'Moods', 'slug' => 'moods']);
$eras = Taxonomy::create(['name' => 'Musical Eras', 'slug' => 'musical-eras']);

// Assign multiple taxonomy types to a track
$track = ChinookTrack::find(1);
$track->attachTaxonomy($genres, $rockTerm);
$track->attachTaxonomy($moods, $energeticTerm);
$track->attachTaxonomy($eras, $modernTerm);
```

## 8. Advanced Features

### 8.1. Taxonomy Tree Visualization

**Interactive Tree Component:**

```php
// Custom Filament component for tree visualization
Forms\Components\ViewField::make('taxonomy_tree')
    ->view('filament.forms.components.taxonomy-tree')
    ->viewData([
        'taxonomies' => Taxonomy::with('children')->whereNull('parent_id')->get(),
        'selected' => $this->record?->taxonomies->pluck('id')->toArray() ?? [],
    ]),
```

### 8.2. Bulk Taxonomy Operations

**Mass Assignment Actions:**

```php
Tables\Actions\BulkAction::make('assign_taxonomy')
    ->label('Assign Taxonomy')
    ->icon('heroicon-o-tag')
    ->form([
        Forms\Components\Select::make('taxonomy_id')
            ->label('Taxonomy')
            ->relationship('taxonomy', 'name')
            ->required(),
        Forms\Components\Select::make('term_id')
            ->label('Term')
            ->options(fn (Get $get) =>
                TaxonomyTerm::where('taxonomy_id', $get('taxonomy_id'))->pluck('name', 'id')
            )
            ->required(),
    ])
    ->action(function (Collection $records, array $data) {
        $taxonomy = Taxonomy::find($data['taxonomy_id']);
        $term = TaxonomyTerm::find($data['term_id']);

        foreach ($records as $record) {
            $record->attachTaxonomy($taxonomy, $term);
        }
    }),
```

### 8.3. Taxonomy Analytics

**Usage Statistics:**

```php
// Get taxonomy usage statistics
public function getTaxonomyStats(): array
{
    return [
        'total_taxonomies' => Taxonomy::count(),
        'active_taxonomies' => Taxonomy::where('is_active', true)->count(),
        'total_terms' => TaxonomyTerm::count(),
        'most_used_taxonomy' => Taxonomy::withCount('terms')
            ->orderBy('terms_count', 'desc')
            ->first(),
        'taxonomy_depth' => Taxonomy::max('depth'),
    ];
}
```

---

## Navigation

**Previous:** [ChinookTracks Resource](030-tracks-resource.md) | **Index:** [Resources Index](000-resources-index.md) | **Next:** ChinookPlaylists Resource *(Documentation pending)*

---

**Documentation Standards**: This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns.

[⬆️ Back to Top](#4-taxonomy-resource)
