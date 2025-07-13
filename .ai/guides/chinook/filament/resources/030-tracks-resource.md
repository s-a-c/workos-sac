# 3. Tracks Resource

> **Refactored from:** `.ai/guides/chinook/filament/resources/030-tracks-resource.md` on 2025-07-11  
> **Focus:** Single taxonomy system using aliziodev/laravel-taxonomy package exclusively

## 3.1. Table of Contents

- [3.2. Overview](#32-overview)
- [4. Resource Implementation](#4-resource-implementation)
  - [4.1. Basic Resource Structure](#41-basic-resource-structure)
- [5. Taxonomy Integration](#5-taxonomy-integration)
  - [5.1. Genre Management](#51-genre-management)
- [6. Complex Relationships](#6-complex-relationships)
  - [6.1. Album Relationship](#61-album-relationship)
- [7. Advanced Features](#7-advanced-features)
  - [7.1. Audio File Management](#71-audio-file-management)

## 3.2. Overview

The Tracks Resource provides comprehensive management of individual music tracks within the Chinook admin panel. It features **single taxonomy system integration**, complex relationship management, audio file handling, pricing controls, and sales analytics integration.

### 3.2.1. Key Features

- **Complete CRUD Operations**: Create, read, update, delete tracks with validation
- **Single Taxonomy Integration**: Genre, mood, theme, and era classification using aliziodev/laravel-taxonomy
- **Album Integration**: Seamless integration with album management
- **Media Type Support**: Support for various audio formats and media types
- **Pricing Management**: Dynamic pricing with promotional capabilities
- **Sales Analytics**: Integration with invoice data for sales tracking
- **Audio File Handling**: Upload and management of audio files
- **Playlist Integration**: Track assignment to playlists

### 3.2.2. Taxonomy Features

- **Multi-Dimensional Classification**: Support for genres, moods, themes, eras, instruments, languages, occasions
- **Hierarchical Taxonomies**: Tree-structured taxonomies with parent-child relationships
- **Polymorphic Relationships**: Shared taxonomies across Artists, Albums, and Tracks
- **Performance Optimized**: Closure table architecture for efficient queries
- **Bulk Operations**: Mass taxonomy assignment and management

## 4. Resource Implementation

### 4.1. Basic Resource Structure

```php
<?php

declare(strict_types=1);

namespace App\Filament\ChinookAdmin\Resources;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\TaxonomyTerm;
use App\Filament\ChinookAdmin\Resources\ChinookTracksResource\Pages;
use App\Filament\ChinookAdmin\Resources\ChinookTracksResource\RelationManagers;
use App\Models\ChinookTrack;
use App\Models\ChinookAlbum;
use App\Models\ChinookMediaType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChinookTracksResource extends Resource
{
    protected static ?string $model = ChinookTrack::class;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';

    protected static ?string $navigationGroup = 'Music Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Track Information')
                    ->schema([
                        Forms\Components\Select::make('album_id')
                            ->label('Album')
                            ->relationship('album', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => 
                                "{$record->title} - {$record->artist->name}"
                            ),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                if ($context === 'edit') {
                                    return;
                                }
                                $set('slug', str($state)->slug());
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ChinookTrack::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\TextInput::make('composer')
                            ->maxLength(255)
                            ->helperText('Composer or songwriter name'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Taxonomy Classification')
                    ->schema([
                        Forms\Components\Select::make('taxonomies')
                            ->label('Genres')
                            ->relationship('taxonomies', 'name', function (Builder $query) {
                                return $query->whereHas('taxonomy', function ($q) {
                                    $q->where('slug', 'music-genres');
                                });
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\Select::make('taxonomy_id')
                                    ->label('Taxonomy')
                                    ->options(function () {
                                        return Taxonomy::where('slug', 'music-genres')->pluck('name', 'id');
                                    })
                                    ->default(function () {
                                        return Taxonomy::where('slug', 'music-genres')->first()?->id;
                                    })
                                    ->required(),
                                Forms\Components\TextInput::make('name')
                                    ->label('Genre Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->helperText('Select or create music genres for this track'),

                        Forms\Components\Select::make('mood_taxonomies')
                            ->label('Moods')
                            ->relationship('taxonomies', 'name', function (Builder $query) {
                                return $query->whereHas('taxonomy', function ($q) {
                                    $q->where('slug', 'moods');
                                });
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Select moods that describe this track'),

                        Forms\Components\Select::make('theme_taxonomies')
                            ->label('Themes')
                            ->relationship('taxonomies', 'name', function (Builder $query) {
                                return $query->whereHas('taxonomy', function ($q) {
                                    $q->where('slug', 'themes');
                                });
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Select thematic content for this track'),

                        Forms\Components\Select::make('era_taxonomies')
                            ->label('Musical Era')
                            ->relationship('taxonomies', 'name', function (Builder $query) {
                                return $query->whereHas('taxonomy', function ($q) {
                                    $q->where('slug', 'musical-eras');
                                });
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Select the musical era for this track'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Track Details')
                    ->schema([
                        Forms\Components\TextInput::make('track_number')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(999)
                            ->helperText('Track position on the album'),

                        Forms\Components\Select::make('media_type_id')
                            ->label('Media Type')
                            ->relationship('mediaType', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('milliseconds')
                            ->label('Duration (ms)')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->maxValue(3600000)
                            ->helperText('Track duration in milliseconds'),

                        Forms\Components\TextInput::make('bytes')
                            ->label('File Size (bytes)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Audio file size in bytes'),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('$')
                            ->helperText('Price per track purchase'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Whether this track is available for purchase'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
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
                    ->description(fn (ChinookTrack $record): string => 
                        $record->album?->title . ' - ' . $record->album?->artist?->name
                    ),

                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('album.artist.name')
                    ->label('Artist')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('taxonomies.name')
                    ->label('Genres')
                    ->badge()
                    ->separator(',')
                    ->limit(3)
                    ->tooltip(function (ChinookTrack $record): string {
                        $genres = $record->taxonomies()
                            ->whereHas('taxonomy', function ($q) {
                                $q->where('slug', 'music-genres');
                            })
                            ->pluck('name')
                            ->join(', ');
                        return $genres ?: 'No genres assigned';
                    }),

                Tables\Columns\TextColumn::make('track_number')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('duration_formatted')
                    ->label('Duration')
                    ->getStateUsing(fn (ChinookTrack $record): string => 
                        gmdate('i:s', intval($record->milliseconds / 1000))
                    )
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('milliseconds', $direction);
                    }),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('album')
                    ->relationship('album', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('artist')
                    ->relationship('album.artist', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('media_type')
                    ->relationship('mediaType', 'name'),

                Tables\Filters\SelectFilter::make('genre')
                    ->label('Genre')
                    ->options(function () {
                        return TaxonomyTerm::whereHas('taxonomy', function ($q) {
                            $q->where('slug', 'music-genres');
                        })->pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($q) use ($data) {
                            $q->whereHas('taxonomies', function ($taxonomyQuery) use ($data) {
                                $taxonomyQuery->where('taxonomy_term_id', $data['value']);
                            });
                        });
                    }),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->native(false),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->numeric()
                            ->prefix('$'),
                        Forms\Components\TextInput::make('price_to')
                            ->numeric()
                            ->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['price_from'], fn ($q) => $q->where('unit_price', '>=', $data['price_from']))
                            ->when($data['price_to'], fn ($q) => $q->where('unit_price', '<=', $data['price_to']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('assign_genre')
                        ->label('Assign Genre')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('genre_id')
                                ->label('Genre')
                                ->options(function () {
                                    return TaxonomyTerm::whereHas('taxonomy', function ($q) {
                                        $q->where('slug', 'music-genres');
                                    })->pluck('name', 'id');
                                })
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $genreTerm = TaxonomyTerm::find($data['genre_id']);
                            if ($genreTerm) {
                                foreach ($records as $record) {
                                    $record->attachTaxonomy($genreTerm->taxonomy, $genreTerm);
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

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
            ->defaultSort('album_id')
            ->defaultSort('track_number');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TaxonomiesRelationManager::class,
            RelationManagers\PlaylistsRelationManager::class,
            RelationManagers\InvoiceLinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChinookTracks::route('/'),
            'create' => Pages\CreateChinookTrack::route('/create'),
            'view' => Pages\ViewChinookTrack::route('/{record}'),
            'edit' => Pages\EditChinookTrack::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['album.artist', 'taxonomies']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'composer', 'album.title', 'album.artist.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Album' => $record->album?->title,
            'Artist' => $record->album?->artist?->name,
            'Duration' => gmdate('i:s', intval($record->milliseconds / 1000)),
            'Price' => '$' . number_format($record->unit_price, 2),
        ];
    }
}
```

## 5. Taxonomy Integration

### 5.1. Genre Management

**Genre Assignment Form Component:**

```php
Forms\Components\Select::make('taxonomies')
    ->label('Genres')
    ->relationship('taxonomies', 'name', function (Builder $query) {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('slug', 'music-genres');
        });
    })
    ->multiple()
    ->searchable()
    ->preload()
    ->createOptionForm([
        Forms\Components\Select::make('taxonomy_id')
            ->label('Taxonomy')
            ->options(function () {
                return Taxonomy::where('slug', 'music-genres')->pluck('name', 'id');
            })
            ->default(function () {
                return Taxonomy::where('slug', 'music-genres')->first()?->id;
            })
            ->required(),
        Forms\Components\TextInput::make('name')
            ->label('Genre Name')
            ->required()
            ->maxLength(255),
        Forms\Components\TextInput::make('slug')
            ->required()
            ->maxLength(255),
    ])
    ->helperText('Select or create music genres for this track'),
```

**Genre Display in Table:**

```php
Tables\Columns\TextColumn::make('taxonomies.name')
    ->label('Genres')
    ->badge()
    ->separator(',')
    ->limit(3)
    ->tooltip(function (ChinookTrack $record): string {
        $genres = $record->taxonomies()
            ->whereHas('taxonomy', function ($q) {
                $q->where('slug', 'music-genres');
            })
            ->pluck('name')
            ->join(', ');
        return $genres ?: 'No genres assigned';
    }),
```

### 5.2. Multi-Taxonomy Support

**Multiple Taxonomy Types:**

```php
// Moods taxonomy
Forms\Components\Select::make('mood_taxonomies')
    ->label('Moods')
    ->relationship('taxonomies', 'name', function (Builder $query) {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('slug', 'moods');
        });
    })
    ->multiple()
    ->searchable()
    ->preload(),

// Themes taxonomy
Forms\Components\Select::make('theme_taxonomies')
    ->label('Themes')
    ->relationship('taxonomies', 'name', function (Builder $query) {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('slug', 'themes');
        });
    })
    ->multiple()
    ->searchable()
    ->preload(),

// Musical eras taxonomy
Forms\Components\Select::make('era_taxonomies')
    ->label('Musical Era')
    ->relationship('taxonomies', 'name', function (Builder $query) {
        return $query->whereHas('taxonomy', function ($q) {
            $q->where('slug', 'musical-eras');
        });
    })
    ->multiple()
    ->searchable()
    ->preload(),
```

### 5.3. Taxonomy Filtering

**Advanced Taxonomy Filters:**

```php
Tables\Filters\SelectFilter::make('genre')
    ->label('Genre')
    ->options(function () {
        return TaxonomyTerm::whereHas('taxonomy', function ($q) {
            $q->where('slug', 'music-genres');
        })->pluck('name', 'id');
    })
    ->query(function (Builder $query, array $data): Builder {
        return $query->when($data['value'], function ($q) use ($data) {
            $q->whereHas('taxonomies', function ($taxonomyQuery) use ($data) {
                $taxonomyQuery->where('taxonomy_term_id', $data['value']);
            });
        });
    }),

Tables\Filters\SelectFilter::make('mood')
    ->label('Mood')
    ->options(function () {
        return TaxonomyTerm::whereHas('taxonomy', function ($q) {
            $q->where('slug', 'moods');
        })->pluck('name', 'id');
    })
    ->query(function (Builder $query, array $data): Builder {
        return $query->when($data['value'], function ($q) use ($data) {
            $q->whereHas('taxonomies', function ($taxonomyQuery) use ($data) {
                $taxonomyQuery->where('taxonomy_term_id', $data['value']);
            });
        });
    }),
```

## 6. Complex Relationships

### 6.1. Album Relationship

**Album Selection with Artist Context:**

```php
Forms\Components\Select::make('album_id')
    ->label('Album')
    ->relationship('album', 'title')
    ->searchable()
    ->preload()
    ->required()
    ->getOptionLabelFromRecordUsing(fn ($record) =>
        "{$record->title} - {$record->artist->name}"
    )
    ->createOptionForm([
        Forms\Components\Select::make('artist_id')
            ->label('Artist')
            ->relationship('artist', 'name')
            ->required(),
        Forms\Components\TextInput::make('title')
            ->required()
            ->maxLength(255),
        Forms\Components\DatePicker::make('release_date')
            ->required(),
    ]),
```

### 6.2. Media Type Relationship

**Media Type Integration:**

```php
Forms\Components\Select::make('media_type_id')
    ->label('Media Type')
    ->relationship('mediaType', 'name')
    ->required()
    ->searchable()
    ->preload()
    ->createOptionForm([
        Forms\Components\TextInput::make('name')
            ->required()
            ->maxLength(255),
        Forms\Components\TextInput::make('file_extension')
            ->maxLength(10)
            ->helperText('e.g., mp3, flac, wav'),
        Forms\Components\TextInput::make('mime_type')
            ->maxLength(100)
            ->helperText('e.g., audio/mpeg, audio/flac'),
    ]),
```

### 6.3. Invoice Lines Relationship

**Sales Analytics Integration:**

```php
// In the resource's relationship managers
RelationManagers\InvoiceLinesRelationManager::class,

// Custom analytics methods
public function getSalesData(): array
{
    return [
        'total_sales' => $this->invoiceLines()->sum('unit_price'),
        'total_quantity' => $this->invoiceLines()->sum('quantity'),
        'unique_customers' => $this->invoiceLines()
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->distinct('invoices.customer_id')
            ->count(),
    ];
}
```

## 7. Advanced Features

### 7.1. Audio File Management

**File Upload Integration:**

```php
Forms\Components\FileUpload::make('audio_file')
    ->label('Audio File')
    ->acceptedFileTypes(['audio/mpeg', 'audio/flac', 'audio/wav'])
    ->maxSize(50 * 1024) // 50MB
    ->directory('tracks')
    ->visibility('private')
    ->downloadable()
    ->previewable(false)
    ->helperText('Upload audio file (MP3, FLAC, or WAV format, max 50MB)'),
```

### 7.2. Pricing and Sales

**Dynamic Pricing Management:**

```php
Forms\Components\TextInput::make('unit_price')
    ->label('Unit Price')
    ->numeric()
    ->required()
    ->minValue(0)
    ->step(0.01)
    ->prefix('$')
    ->helperText('Price per track purchase')
    ->live(onBlur: true)
    ->afterStateUpdated(function ($state, Forms\Set $set) {
        // Auto-calculate promotional pricing
        if ($state > 1.00) {
            $set('promotional_price', round($state * 0.8, 2));
        }
    }),

Forms\Components\TextInput::make('promotional_price')
    ->label('Promotional Price')
    ->numeric()
    ->minValue(0)
    ->step(0.01)
    ->prefix('$')
    ->helperText('Optional promotional pricing'),
```

### 7.3. Playlist Integration

**Playlist Assignment:**

```php
// In relationship managers
RelationManagers\PlaylistsRelationManager::class,

// Bulk playlist assignment
Tables\Actions\BulkAction::make('add_to_playlist')
    ->label('Add to Playlist')
    ->icon('heroicon-o-queue-list')
    ->form([
        Forms\Components\Select::make('playlist_id')
            ->label('Playlist')
            ->relationship('playlists', 'name')
            ->required(),
    ])
    ->action(function (Collection $records, array $data) {
        $playlist = ChinookPlaylist::find($data['playlist_id']);
        foreach ($records as $record) {
            $playlist->tracks()->attach($record->id);
        }
    }),
```

---

## Navigation

**Previous:** Albums Resource *(Documentation pending)* | **Index:** [Resources Index](000-resources-index.md) | **Next:** [Taxonomy Resource](040-taxonomy-resource.md)

---

**Documentation Standards**: This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns.

[⬆️ Back to Top](#3-tracks-resource)
