# Relationship Managers Guide

## Overview

This guide covers the implementation of Filament relationship managers for the Chinook music database, providing comprehensive management of complex relationships between artists, albums, tracks, categories, and other entities.

## Table of Contents

- [Overview](#overview)
- [Basic Relationship Managers](#basic-relationship-managers)
- [Artist Relationship Managers](#artist-relationship-managers)
- [Album Relationship Managers](#album-relationship-managers)
- [Category Relationship Managers](#category-relationship-managers)
- [Advanced Relationship Patterns](#advanced-relationship-patterns)
- [Performance Optimization](#performance-optimization)
- [Authorization and Security](#authorization-and-security)
- [Testing Relationship Managers](#testing-relationship-managers)
- [Best Practices](#best-practices)

## Basic Relationship Managers

### Creating Relationship Managers

```php
<?php

namespace App\Filament\Resources\ArtistResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Album;

class AlbumsRelationManager extends RelationManager
{
    protected static string $relationship = 'albums';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $label = 'Album';
    protected static ?string $pluralLabel = 'Albums';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(160)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $context, $state, callable $set) {
                        if ($context === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(160)
                    ->unique(Album::class, 'slug', ignoreRecord: true)
                    ->rules(['alpha_dash']),

                Forms\Components\DatePicker::make('release_date')
                    ->native(false)
                    ->displayFormat('Y-m-d')
                    ->closeOnDateSelection(),

                Forms\Components\Textarea::make('description')
                    ->maxLength(1000)
                    ->rows(3),

                Forms\Components\FileUpload::make('cover_image')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '1:1',
                    ])
                    ->directory('album-covers')
                    ->visibility('public'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->square()
                    ->size(60),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('release_date')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tracks_count')
                    ->counts('tracks')
                    ->label('Tracks')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_duration')
                    ->getStateUsing(function (Album $record): string {
                        $totalMs = $record->tracks->sum('milliseconds');
                        $minutes = floor($totalMs / 60000);
                        $seconds = floor(($totalMs % 60000) / 1000);
                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->label('Duration'),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_cover')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('cover_image'))
                    ->label('Has Cover Image'),

                Tables\Filters\Filter::make('recent')
                    ->query(fn (Builder $query): Builder => $query->where('release_date', '>=', now()->subYear()))
                    ->label('Released This Year'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['artist_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

## Artist Relationship Managers

### Categories Relationship Manager

```php
<?php

namespace App\Filament\Resources\ArtistResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Category;
use App\Enums\CategoryType;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->options(function () {
                        return Category::query()
                            ->whereIn('type', [
                                CategoryType::GENRE,
                                CategoryType::MOOD,
                                CategoryType::ERA,
                            ])
                            ->orderBy('type')
                            ->orderBy('name')
                            ->get()
                            ->groupBy('type')
                            ->map(function ($categories, $type) {
                                return $categories->pluck('name', 'id');
                            });
                    })
                    ->searchable()
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Assignment Notes')
                    ->maxLength(500)
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (CategoryType $state): string => match ($state) {
                        CategoryType::GENRE => 'primary',
                        CategoryType::MOOD => 'success',
                        CategoryType::ERA => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('pivot.notes')
                    ->label('Notes')
                    ->limit(50)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('artists_count')
                    ->counts('artists')
                    ->label('Total Artists')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(CategoryType::class),

                Tables\Filters\Filter::make('has_parent')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('parent_id'))
                    ->label('Subcategories Only'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereIn('type', [
                        CategoryType::GENRE,
                        CategoryType::MOOD,
                        CategoryType::ERA,
                    ]))
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500),
                    ]),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
```

## Album Relationship Managers

### Tracks Relationship Manager

```php
<?php

namespace App\Filament\Resources\AlbumResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Track;
use App\Models\MediaType;

class TracksRelationManager extends RelationManager
{
    protected static string $relationship = 'tracks';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(200),

                        Forms\Components\TextInput::make('track_number')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(function () {
                                return $this->ownerRecord->tracks()->max('track_number') + 1;
                            }),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('milliseconds')
                            ->label('Duration (ms)')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->suffix('ms')
                            ->helperText('Duration in milliseconds'),

                        Forms\Components\TextInput::make('bytes')
                            ->label('File Size')
                            ->numeric()
                            ->suffix('bytes')
                            ->helperText('File size in bytes'),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Price')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('$')
                            ->default(0.99),
                    ]),

                Forms\Components\Select::make('media_type_id')
                    ->label('Media Type')
                    ->options(MediaType::pluck('name', 'id'))
                    ->required()
                    ->default(1), // Default to MPEG audio

                Forms\Components\FileUpload::make('audio_file')
                    ->label('Audio File')
                    ->acceptedFileTypes(['audio/mpeg', 'audio/wav', 'audio/flac'])
                    ->directory('tracks')
                    ->visibility('private')
                    ->maxSize(50 * 1024), // 50MB max
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('track_number')
            ->columns([
                Tables\Columns\TextColumn::make('track_number')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('duration')
                    ->getStateUsing(function (Track $record): string {
                        $minutes = floor($record->milliseconds / 60000);
                        $seconds = floor(($record->milliseconds % 60000) / 1000);
                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->sortable(['milliseconds']),

                Tables\Columns\TextColumn::make('mediaType.name')
                    ->label('Format')
                    ->badge(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_size')
                    ->getStateUsing(function (Track $record): string {
                        if (!$record->bytes) return '—';
                        return Number::fileSize($record->bytes);
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('media_type_id')
                    ->label('Media Type')
                    ->options(MediaType::pluck('name', 'id')),

                Tables\Filters\Filter::make('long_tracks')
                    ->query(fn (Builder $query): Builder => $query->where('milliseconds', '>', 300000))
                    ->label('Long Tracks (>5 min)'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['album_id'] = $this->ownerRecord->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('track_number');
    }
}
```

## Category Relationship Managers

### Categorizable Items Manager

```php
<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Artist;
use App\Models\Album;
use App\Models\Track;

class CategorizableItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'categorizables';
    protected static ?string $label = 'Categorized Item';
    protected static ?string $pluralLabel = 'Categorized Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('categorizable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Artist::class => 'primary',
                        Album::class => 'success',
                        Track::class => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('categorizable.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(function ($record) {
                        $model = $record->categorizable;
                        if ($model instanceof Artist) {
                            return ArtistResource::getUrl('edit', ['record' => $model]);
                        } elseif ($model instanceof Album) {
                            return AlbumResource::getUrl('edit', ['record' => $model]);
                        } elseif ($model instanceof Track) {
                            return TrackResource::getUrl('edit', ['record' => $model]);
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('categorizable.artist.name')
                    ->label('Artist')
                    ->visible(fn () => $this->ownerRecord->type !== CategoryType::ARTIST_SPECIFIC),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Assigned')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorizable_type')
                    ->label('Item Type')
                    ->options([
                        Artist::class => 'Artists',
                        Album::class => 'Albums',
                        Track::class => 'Tracks',
                    ]),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
```

## Advanced Relationship Patterns

### Polymorphic Relationship Manager

```php
<?php

namespace App\Filament\Resources\CommentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';
    protected static ?string $recordTitleAttribute = 'content';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\RichEditor::make('content')
                    ->required()
                    ->maxLength(1000)
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'link',
                        'bulletList',
                        'orderedList',
                    ]),

                Forms\Components\Toggle::make('is_approved')
                    ->label('Approved')
                    ->default(false)
                    ->visible(fn () => auth()->user()->can('moderate comments')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable(),

                Tables\Columns\TextColumn::make('content')
                    ->limit(50)
                    ->html(),

                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

## Performance Optimization

### Eager Loading in Relationship Managers

```php
public function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(function (Builder $query) {
            return $query->with([
                'artist',
                'mediaType',
                'categories',
            ]);
        })
        // ... rest of table configuration
}
```

### Pagination and Limits

```php
public function table(Table $table): Table
{
    return $table
        ->defaultPaginationPageOption(25)
        ->paginationPageOptions([10, 25, 50, 100])
        ->deferLoading()
        // ... rest of configuration
}
```

## Authorization and Security

### Relationship Manager Authorization

```php
public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
{
    return auth()->user()->can('view', $ownerRecord);
}

public function canCreate(): bool
{
    return auth()->user()->can('create', $this->getRelatedModel());
}

public function canEdit(Model $record): bool
{
    return auth()->user()->can('update', $record);
}

public function canDelete(Model $record): bool
{
    return auth()->user()->can('delete', $record);
}
```

## Testing Relationship Managers

### Basic Relationship Manager Tests

```php
<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;
use App\Models\Artist;
use App\Models\Album;
use App\Models\User;
use Livewire\Livewire;

class AlbumsRelationManagerTest extends TestCase
{
    public function test_can_list_artist_albums(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();
        $albums = Album::factory()->count(3)->create(['artist_id' => $artist->id]);

        $this->actingAs($user);

        Livewire::test(AlbumsRelationManager::class, [
            'ownerRecord' => $artist,
            'pageClass' => EditArtist::class,
        ])
            ->assertCanSeeTableRecords($albums);
    }

    public function test_can_create_album_for_artist(): void
    {
        $user = User::factory()->create();
        $artist = Artist::factory()->create();

        $this->actingAs($user);

        Livewire::test(AlbumsRelationManager::class, [
            'ownerRecord' => $artist,
            'pageClass' => EditArtist::class,
        ])
            ->callTableAction('create', data: [
                'title' => 'New Album',
                'release_date' => '2024-01-01',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('albums', [
            'title' => 'New Album',
            'artist_id' => $artist->id,
        ]);
    }
}
```

## Best Practices

### Relationship Manager Guidelines

1. **Performance First**
   - Use eager loading for related data
   - Implement proper pagination
   - Add appropriate database indexes

2. **User Experience**
   - Provide clear column labels
   - Use appropriate filters
   - Implement bulk actions where useful

3. **Security**
   - Implement proper authorization
   - Validate all form inputs
   - Use policies for complex permissions

4. **Maintainability**
   - Keep relationship managers focused
   - Use consistent naming conventions
   - Document complex business logic

---

## Navigation

**← Previous:** [Users Resource](110-users-resource.md)

**Next →** [Form Components](120-form-components.md)
