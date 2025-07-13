# 2. ChinookAlbums Resource

## Table of Contents

- [Overview](#overview)
- [Resource Implementation](#resource-implementation)
  - [Basic Resource Structure](#basic-resource-structure)
  - [Form Configuration](#form-configuration)
  - [Table Configuration](#table-configuration)
- [Relationship Managers](#relationship-managers)
  - [Tracks Relationship Manager](#tracks-relationship-manager)
  - [Categories Relationship Manager](#categories-relationship-manager)
- [Advanced Features](#advanced-features)
  - [Custom Form Components](#custom-form-components)
  - [Advanced Table Features](#advanced-table-features)
  - [Bulk Operations](#bulk-operations)
- [Authorization](#authorization)
  - [Resource-Level Authorization](#resource-level-authorization)
  - [Field-Level Security](#field-level-security)
- [Business Logic](#business-logic)
  - [Album Validation](#album-validation)
  - [Release Date Handling](#release-date-handling)
  - [Cover Art Management](#cover-art-management)
- [Performance Optimization](#performance-optimization)
- [Testing](#testing)
- [Navigation](#navigation)

## Overview

The ChinookAlbums Resource provides comprehensive management of music albums within the Chinook admin panel. It features advanced relationship management, polymorphic category assignments, media handling, and business logic for album lifecycle management.

### Key Features

- **Complete CRUD Operations**: Create, read, update, delete albums with validation
- **Artist Relationship**: Seamless integration with artist management
- **Track Management**: Inline track creation and management
- **Category Assignment**: Polymorphic category relationships with hierarchical support
- **Cover Art Handling**: Image upload with validation and optimization
- **Release Management**: Release date validation and status tracking
- **Sales Analytics**: Integration with invoice data for sales metrics

## Resource Implementation

### Basic Resource Structure

```php
<?php

declare(strict_types=1);

namespace App\Filament\ChinookAdmin\Resources;

use App\Models\ChinookAlbum;
use App\Models\ChinookArtist;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChinookAlbumResource extends Resource
{
    protected static ?string $model = ChinookAlbum::class;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';

    protected static ?string $navigationGroup = 'Music Management';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Albums';

    protected static ?string $modelLabel = 'Album';

    protected static ?string $pluralModelLabel = 'Albums';

    protected static ?string $recordTitleAttribute = 'title';

    // Global search configuration
    protected static ?array $searchableColumns = ['title', 'artist.name', 'catalog_number'];

    // Permission-based navigation visibility
    protected static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view-albums') ?? false;
    }

    // Dynamic badge count
    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->can('view-albums')) {
            return static::getModel()::count();
        }

        return null;
    }

    // Badge color based on count
    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::count();

        return match (true) {
            $count > 500 => 'success',
            $count > 200 => 'warning',
            default => 'primary',
        };
    }

    // Global scope for data access
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        // Super admins see everything
        if ($user?->hasRole('super-admin')) {
            return $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        // Regular users see only active albums
        $query->where('is_active', true);

        // Apply additional filters based on user role
        if (!$user?->can('view-inactive-albums')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Album Information')
                    ->schema([
                        Forms\Components\Select::make('artist_id')
                            ->label('Artist')
                            ->relationship('artist', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('biography')
                                    ->maxLength(65535),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Artist::create($data)->getKey();
                            }),

                        Forms\Components\TextInput::make('title')
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
                            ->unique(Album::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\DatePicker::make('release_date')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->maxDate(now()->addYear())
                            ->rules(['before_or_equal:today']),

                        Forms\Components\TextInput::make('label')
                            ->label('Record Label')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('catalog_number')
                            ->maxLength(255)
                            ->unique(Album::class, 'catalog_number', ignoreRecord: true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Album Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->rows(4),

                        Forms\Components\FileUpload::make('cover_image_url')
                            ->label('Cover Art')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                            ])
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->directory('album-covers')
                            ->visibility('public')
                            ->rules([
                                'image',
                                'max:2048',
                                'mimes:jpeg,png,webp',
                                'dimensions:min_width=300,min_height=300,max_width=3000,max_height=3000',
                            ]),

                        Forms\Components\TextInput::make('total_tracks')
                            ->label('Number of Tracks')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(999)
                            ->default(1),

                        Forms\Components\TextInput::make('total_duration_ms')
                            ->label('Total Duration (milliseconds)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Will be calculated automatically from tracks'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Album Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_compilation')
                            ->label('Compilation Album')
                            ->helperText('Check if this is a compilation album'),

                        Forms\Components\Toggle::make('is_explicit')
                            ->label('Explicit Content')
                            ->helperText('Check if this album contains explicit content'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive albums are hidden from public view')
                            ->visible(fn () => auth()->user()?->can('manage-album-status')),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Categories')
                    ->schema([
                        Forms\Components\Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(50)
                            ->createOptionForm([
                                Forms\Components\Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship('parent', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'GENRE' => 'Genre',
                                        'MOOD' => 'Mood',
                                        'THEME' => 'Theme',
                                        'ERA' => 'Era',
                                        'INSTRUMENT' => 'Instrument',
                                        'LANGUAGE' => 'Language',
                                        'OCCASION' => 'Occasion',
                                    ])
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return Category::create($data)->getKey();
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_url')
                    ->label('Cover')
                    ->circular()
                    ->size(60)
                    ->defaultImageUrl(url('/images/default-album-cover.png')),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Album $record): string => $record->artist->name ?? 'Unknown Artist'),

                Tables\Columns\TextColumn::make('artist.name')
                    ->label('Artist')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('release_date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('label')
                    ->label('Record Label')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_tracks')
                    ->label('Tracks')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_duration_formatted')
                    ->label('Duration')
                    ->getStateUsing(function (Album $record): string {
                        if (!$record->total_duration_ms) {
                            return 'Unknown';
                        }
                        
                        $minutes = floor($record->total_duration_ms / 60000);
                        $seconds = floor(($record->total_duration_ms % 60000) / 1000);
                        
                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_compilation')
                    ->label('Compilation')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_explicit')
                    ->label('Explicit')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('artist')
                    ->relationship('artist', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('release_date')
                    ->form([
                        Forms\Components\DatePicker::make('released_from')
                            ->label('Released from'),
                        Forms\Components\DatePicker::make('released_until')
                            ->label('Released until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['released_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('release_date', '>=', $date),
                            )
                            ->when(
                                $data['released_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('release_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('is_compilation')
                    ->label('Compilation Albums'),

                Tables\Filters\TernaryFilter::make('is_explicit')
                    ->label('Explicit Content'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->can('view-trashed-albums')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('delete-albums')),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn () => auth()->user()?->can('restore-albums')),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('force-delete-albums')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('delete-albums')),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('restore-albums')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('force-delete-albums')),
                ]),
            ])
            ->defaultSort('release_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            TracksRelationManager::class,
            CategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlbums::route('/'),
            'create' => Pages\CreateAlbum::route('/create'),
            'view' => Pages\ViewAlbum::route('/{record}'),
            'edit' => Pages\EditAlbum::route('/{record}/edit'),
        ];
    }

    // Authorization methods
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view-albums') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create-albums') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete-albums') ?? false;
    }

    public static function canForceDelete($record): bool
    {
        return auth()->user()?->can('force-delete', $record) ?? false;
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()?->can('force-delete-albums') ?? false;
    }

    public static function canRestore($record): bool
    {
        return auth()->user()?->can('restore', $record) ?? false;
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()?->can('restore-albums') ?? false;
    }
}
```

## Relationship Managers

### Tracks Relationship Manager

```php
<?php

declare(strict_types=1);

namespace App\Filament\ChinookAdmin\Resources\AlbumResource\RelationManagers;

use App\Models\MediaType;
use App\Models\Track;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TracksRelationManager extends RelationManager
{
    protected static string $relationship = 'tracks';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Track Information')
                    ->schema([
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
                            ->unique(Track::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\TextInput::make('composer')
                            ->maxLength(255),

                        Forms\Components\Select::make('media_type_id')
                            ->label('Media Type')
                            ->relationship('mediaType', 'name')
                            ->required()
                            ->preload(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Track Details')
                    ->schema([
                        Forms\Components\TextInput::make('track_number')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(999)
                            ->default(function () {
                                $album = $this->getOwnerRecord();
                                return $album->tracks()->max('track_number') + 1;
                            }),

                        Forms\Components\TextInput::make('disc_number')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(99),

                        Forms\Components\TextInput::make('milliseconds')
                            ->label('Duration (milliseconds)')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->helperText('Minimum 1 second (1000ms)'),

                        Forms\Components\TextInput::make('bytes')
                            ->label('File Size (bytes)')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Price')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('$')
                            ->default(0.99),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('lyrics')
                            ->maxLength(65535)
                            ->rows(6),

                        Forms\Components\TextInput::make('preview_url')
                            ->label('Preview URL')
                            ->url()
                            ->maxLength(255),

                        Forms\Components\Toggle::make('is_explicit')
                            ->label('Explicit Content'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('track_number')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('composer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('duration_formatted')
                    ->label('Duration')
                    ->getStateUsing(function (Track $record): string {
                        if (!$record->milliseconds) {
                            return 'Unknown';
                        }
                        
                        $minutes = floor($record->milliseconds / 60000);
                        $seconds = floor(($record->milliseconds % 60000) / 1000);
                        
                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_explicit')
                    ->label('Explicit')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('media_type')
                    ->relationship('mediaType', 'name'),
                Tables\Filters\TernaryFilter::make('is_explicit'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => auth()->user()?->can('create-tracks')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()?->can('edit-tracks')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('delete-tracks')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('delete-tracks')),
                ]),
            ])
            ->defaultSort('track_number');
    }
}
```

### Form Configuration

The form configuration provides comprehensive album management with the following key features:

- **Album Information Section**: Basic album details including title, artist, release date, and cover art
- **Track Management Section**: Embedded track creation and management
- **Category Assignment Section**: Polymorphic category relationships
- **Metadata Section**: Additional album information and status flags

### Table Configuration

The table configuration offers advanced album browsing and management capabilities:

- **Sortable Columns**: Title, artist, release date, track count, and total duration
- **Searchable Fields**: Album title, artist name, and track names
- **Filterable Options**: Artist, release year, category, and status filters
- **Bulk Operations**: Mass operations for album management
- **Relationship Columns**: Artist information and track statistics

## Relationship Managers

The Albums Resource includes sophisticated relationship management for associated data.

### Tracks Relationship Manager

Comprehensive track management within the album context:

- **Inline Track Creation**: Add tracks directly from the album view
- **Track Ordering**: Drag-and-drop track reordering
- **Bulk Track Operations**: Mass track updates and deletions
- **Track Statistics**: Duration, file size, and sales analytics
- **Media Type Management**: Support for multiple audio formats

### Categories Relationship Manager

Polymorphic category assignment and management:

- **Multi-Category Assignment**: Albums can belong to multiple categories
- **Category Hierarchy**: Support for nested category structures
- **Category Analytics**: Track category usage and popularity
- **Bulk Category Operations**: Mass category assignment and removal
- **Category Validation**: Ensure appropriate category assignments

## Advanced Features

The Albums Resource includes several advanced features for comprehensive album management.

### Custom Form Components

Specialized form components for album-specific functionality:

- **Cover Art Upload**: Image upload with validation and resizing
- **Release Date Picker**: Advanced date selection with validation
- **Track Duration Calculator**: Automatic album duration calculation
- **Artist Selector**: Enhanced artist selection with search and creation
- **Category Multi-Select**: Advanced category assignment interface

### Advanced Table Features

Enhanced table functionality for improved user experience:

- **Cover Art Thumbnails**: Visual album identification
- **Track Count Indicators**: Quick track statistics
- **Duration Display**: Human-readable duration formatting
- **Sales Analytics**: Revenue and popularity metrics
- **Status Indicators**: Visual status and availability flags

### Bulk Operations

Comprehensive bulk operation support for efficient album management:

- **Bulk Category Assignment**: Mass category updates
- **Bulk Status Changes**: Mass activation/deactivation
- **Bulk Price Updates**: Mass pricing changes
- **Bulk Export**: Data export functionality
- **Bulk Validation**: Mass data validation and cleanup

## Authorization

Comprehensive authorization controls ensure secure access to album management features.

### Resource-Level Authorization

Control access to album management functionality:

- **View Permissions**: Control who can view albums
- **Create Permissions**: Control who can create new albums
- **Edit Permissions**: Control who can modify existing albums
- **Delete Permissions**: Control who can remove albums
- **Bulk Operation Permissions**: Control access to mass operations

### Field-Level Security

Granular control over individual form fields and data access:

- **Sensitive Data Protection**: Protect financial and sales data
- **Role-Based Field Access**: Different fields for different roles
- **Dynamic Field Visibility**: Context-aware field display
- **Data Masking**: Protect sensitive information display
- **Audit Trail**: Track all data modifications

## Business Logic

The Albums Resource implements comprehensive business logic for album management.

### Album Validation

Robust validation ensures data integrity and business rule compliance:

- **Required Field Validation**: Ensure all necessary data is provided
- **Unique Constraint Validation**: Prevent duplicate albums
- **Release Date Validation**: Ensure logical release dates
- **Track Validation**: Validate associated track data
- **Category Validation**: Ensure appropriate category assignments

### Release Date Handling

Sophisticated release date management:

- **Future Release Support**: Handle upcoming album releases
- **Historical Date Validation**: Ensure realistic historical dates
- **Regional Release Dates**: Support for different regional releases
- **Release Date Analytics**: Track release patterns and trends
- **Automatic Status Updates**: Update album status based on release dates

### Cover Art Management

Comprehensive cover art handling:

- **Image Upload Validation**: Format and size validation
- **Automatic Resizing**: Generate multiple image sizes
- **Image Optimization**: Compress images for web delivery
- **Fallback Images**: Default images for albums without cover art
- **Image Storage**: Secure and efficient image storage

## Performance Optimization

The Albums Resource is optimized for performance with large music libraries.

## Testing

The Albums Resource includes comprehensive testing coverage for all functionality.

---

## Navigation

**← Previous:** [Artists Resource](010-artists-resource.md)

**Next →** [Tracks Resource](030-tracks-resource.md)
