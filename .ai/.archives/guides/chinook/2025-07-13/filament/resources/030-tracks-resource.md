# 3. Tracks Resource

## Table of Contents

- [Overview](#overview)
- [Resource Implementation](#resource-implementation)
  - [Basic Resource Structure](#basic-resource-structure)
  - [Form Configuration](#form-configuration)
  - [Table Configuration](#table-configuration)
- [Complex Relationships](#complex-relationships)
  - [Album Relationship](#album-relationship)
  - [Media Type Relationship](#media-type-relationship)
  - [Invoice Lines Relationship](#invoice-lines-relationship)
- [Advanced Features](#advanced-features)
  - [Audio File Management](#audio-file-management)
  - [Pricing and Sales](#pricing-and-sales)
  - [Playlist Integration](#playlist-integration)
- [Business Logic](#business-logic)
  - [Track Validation](#track-validation)
  - [Duration Handling](#duration-handling)
  - [Sales Analytics](#sales-analytics)
- [Performance Optimization](#performance-optimization)
- [Authorization](#authorization)
- [Testing](#testing)
- [Navigation](#navigation)

## Overview

The Tracks Resource provides comprehensive management of individual music tracks within the Chinook admin panel. It features complex relationship management, audio file handling, pricing controls, and sales analytics integration.

### Key Features

- **Complete CRUD Operations**: Create, read, update, delete tracks with validation
- **Album Integration**: Seamless integration with album management
- **Media Type Support**: Support for various audio formats and media types
- **Pricing Management**: Dynamic pricing with promotional capabilities
- **Sales Analytics**: Integration with invoice data for sales tracking
- **Audio File Handling**: Upload and management of audio files
- **Playlist Integration**: Track assignment to playlists

## Resource Implementation

### Basic Resource Structure

```php
<?php

declare(strict_types=1);

namespace App\Filament\ChinookAdmin\Resources;

use App\Models\ChinookTrack;
use App\Models\Album;
use App\Models\MediaType;
use App\Models\Playlist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChinookTrackResource extends Resource
{
    protected static ?string $model = ChinookTrack::class;

    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static ?string $navigationGroup = 'Music Management';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Tracks';

    protected static ?string $modelLabel = 'Track';

    protected static ?string $pluralModelLabel = 'Tracks';

    protected static ?string $recordTitleAttribute = 'name';

    // Global search configuration
    protected static ?array $searchableColumns = [
        'name', 
        'composer', 
        'album.title', 
        'album.artist.name'
    ];

    // Permission-based navigation visibility
    protected static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view-tracks') ?? false;
    }

    // Dynamic badge count
    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->can('view-tracks')) {
            return static::getModel()::count();
        }

        return null;
    }

    // Badge color based on count
    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::count();

        return match (true) {
            $count > 5000 => 'success',
            $count > 2000 => 'warning',
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

        // Regular users see only active tracks
        $query->where('is_active', true);

        // Apply additional filters based on user role
        if (!$user?->can('view-inactive-tracks')) {
            $query->where('is_active', true);
        }

        return $query;
    }

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

                Forms\Components\Section::make('Track Details')
                    ->schema([
                        Forms\Components\TextInput::make('track_number')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(999)
                            ->default(function () {
                                // Auto-increment track number based on album
                                $albumId = request()->get('album_id');
                                if ($albumId) {
                                    return ChinookTrack::where('album_id', $albumId)->max('track_number') + 1;
                                }
                                return 1;
                            }),

                        Forms\Components\TextInput::make('disc_number')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(99)
                            ->helperText('Disc number for multi-disc albums'),

                        Forms\Components\Select::make('media_type_id')
                            ->label('Media Type')
                            ->relationship('mediaType', 'name')
                            ->required()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\TextInput::make('milliseconds')
                            ->label('Duration (milliseconds)')
                            ->numeric()
                            ->required()
                            ->minValue(1000)
                            ->helperText('Track duration in milliseconds (minimum 1 second)')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('convertFromMinutes')
                                    ->icon('heroicon-m-clock')
                                    ->form([
                                        Forms\Components\TextInput::make('minutes')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0),
                                        Forms\Components\TextInput::make('seconds')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->maxValue(59),
                                    ])
                                    ->action(function (array $data, Forms\Set $set) {
                                        $totalMs = ($data['minutes'] * 60 + $data['seconds']) * 1000;
                                        $set('milliseconds', $totalMs);
                                    })
                            ),

                        Forms\Components\TextInput::make('bytes')
                            ->label('File Size (bytes)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Audio file size in bytes'),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Price')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('$')
                            ->default(0.99)
                            ->helperText('Track price in USD'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Audio File')
                    ->schema([
                        Forms\Components\FileUpload::make('audio_file_url')
                            ->label('Audio File')
                            ->acceptedFileTypes([
                                'audio/mpeg',
                                'audio/mp4',
                                'audio/wav',
                                'audio/flac',
                                'audio/ogg',
                            ])
                            ->maxSize(50 * 1024) // 50MB
                            ->directory('tracks')
                            ->visibility('private')
                            ->downloadable()
                            ->previewable(false),

                        Forms\Components\TextInput::make('preview_url')
                            ->label('Preview URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL for 30-second preview'),

                        Forms\Components\Textarea::make('lyrics')
                            ->maxLength(65535)
                            ->rows(6)
                            ->helperText('Track lyrics (optional)'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Track Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_explicit')
                            ->label('Explicit Content')
                            ->helperText('Check if this track contains explicit content'),

                        Forms\Components\Toggle::make('is_single')
                            ->label('Released as Single')
                            ->helperText('Check if this track was released as a single'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive tracks are hidden from public view')
                            ->visible(fn () => auth()->user()?->can('manage-track-status')),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Categories & Playlists')
                    ->schema([
                        Forms\Components\Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(50),

                        Forms\Components\Select::make('playlists')
                            ->relationship('playlists', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->optionsLimit(50),
                    ])
                    ->columns(2),
            ]);
    }

### Form Configuration

The form configuration provides comprehensive track management with the following key features:

- **Track Information Section**: Basic track details including album selection, name, slug, and composer
- **Track Details Section**: Technical information like track number, disc number, media type, duration, file size, and pricing
- **Audio File Section**: File upload capabilities with format validation, preview URL, and lyrics
- **Track Status Section**: Status flags for explicit content, single release, and active status
- **Categories & Playlists Section**: Multi-select relationships for categorization and playlist assignment

### Table Configuration

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('track_number')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Track $record): string => 
                        $record->composer ? "Composed by {$record->composer}" : ''
                    ),

                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Track $record): string => 
                        $record->album?->artist?->name ?? 'Unknown Artist'
                    ),

                Tables\Columns\TextColumn::make('mediaType.name')
                    ->label('Format')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MPEG audio file' => 'success',
                        'AAC audio file' => 'info',
                        'Protected AAC audio file' => 'warning',
                        default => 'gray',
                    }),

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
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sales_count')
                    ->label('Sales')
                    ->getStateUsing(function (Track $record): int {
                        return $record->invoiceLines()->count();
                    })
                    ->sortable()
                    ->alignCenter()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_explicit')
                    ->label('Explicit')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_single')
                    ->label('Single')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

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

                Tables\Filters\Filter::make('duration')
                    ->form([
                        Forms\Components\TextInput::make('min_duration')
                            ->label('Minimum Duration (minutes)')
                            ->numeric(),
                        Forms\Components\TextInput::make('max_duration')
                            ->label('Maximum Duration (minutes)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_duration'],
                                fn (Builder $query, $duration): Builder => 
                                    $query->where('milliseconds', '>=', $duration * 60000),
                            )
                            ->when(
                                $data['max_duration'],
                                fn (Builder $query, $duration): Builder => 
                                    $query->where('milliseconds', '<=', $duration * 60000),
                            );
                    }),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('min_price')
                            ->label('Minimum Price')
                            ->numeric()
                            ->step(0.01),
                        Forms\Components\TextInput::make('max_price')
                            ->label('Maximum Price')
                            ->numeric()
                            ->step(0.01),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_price'],
                                fn (Builder $query, $price): Builder => 
                                    $query->where('unit_price', '>=', $price),
                            )
                            ->when(
                                $data['max_price'],
                                fn (Builder $query, $price): Builder => 
                                    $query->where('unit_price', '<=', $price),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('is_explicit')
                    ->label('Explicit Content'),

                Tables\Filters\TernaryFilter::make('is_single')
                    ->label('Released as Single'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn () => auth()->user()?->can('view-trashed-tracks')),
            ])
            ->actions([
                Tables\Actions\Action::make('play')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->url(fn (Track $record): string => $record->preview_url ?? '#')
                    ->openUrlInNewTab()
                    ->visible(fn (Track $record): bool => !empty($record->preview_url)),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('delete-tracks')),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn () => auth()->user()?->can('restore-tracks')),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn () => auth()->user()?->can('force-delete-tracks')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('updatePrice')
                        ->label('Update Price')
                        ->icon('heroicon-o-currency-dollar')
                        ->form([
                            Forms\Components\TextInput::make('new_price')
                                ->label('New Price')
                                ->numeric()
                                ->step(0.01)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(function (Track $track) use ($data) {
                                $track->update(['unit_price' => $data['new_price']]);
                            });
                        })
                        ->visible(fn () => auth()->user()?->can('edit-tracks')),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('delete-tracks')),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('restore-tracks')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->can('force-delete-tracks')),
                ]),
            ])
            ->defaultSort('album_id')
            ->defaultSort('track_number');
    }

## Complex Relationships

The Tracks Resource manages several complex relationships that are essential for the music library functionality.

### Album Relationship

Each track belongs to an album, creating a many-to-one relationship that enables:

- **Album-based organization**: Tracks are grouped by their parent album
- **Artist inheritance**: Tracks inherit artist information through the album relationship
- **Release date context**: Track release dates are typically tied to album release dates
- **Batch operations**: Bulk operations can be performed on all tracks within an album

### Media Type Relationship

Tracks are associated with media types to support various audio formats:

- **Format specification**: Defines the audio format (MP3, FLAC, WAV, etc.)
- **Quality indicators**: Different media types can represent different quality levels
- **Pricing tiers**: Media types can have different pricing structures
- **Compatibility**: Ensures proper playback support across different devices

### Invoice Lines Relationship

The relationship with invoice lines enables sales tracking and analytics:

- **Sales history**: Track all purchases of individual tracks
- **Revenue analytics**: Calculate total revenue per track
- **Popular tracks**: Identify best-selling tracks based on invoice data
- **Customer preferences**: Analyze customer purchasing patterns

## Advanced Features

The Tracks Resource includes several advanced features for comprehensive track management.

### Audio File Management

Comprehensive audio file handling capabilities:

- **Multiple format support**: MP3, FLAC, WAV, OGG, and MP4 audio formats
- **File size validation**: Maximum 50MB file size limit
- **Private storage**: Secure file storage with controlled access
- **Preview generation**: Automatic 30-second preview creation
- **Download protection**: Secure download links with authentication

### Pricing and Sales

Dynamic pricing and sales management features:

- **Flexible pricing**: Individual track pricing with promotional capabilities
- **Bulk price updates**: Mass price changes across multiple tracks
- **Sales analytics**: Integration with invoice data for revenue tracking
- **Price history**: Track pricing changes over time
- **Promotional pricing**: Support for temporary price reductions

### Playlist Integration

Seamless integration with playlist functionality:

- **Multi-playlist assignment**: Tracks can belong to multiple playlists
- **Playlist management**: Easy addition and removal from playlists
- **Playlist analytics**: Track popularity across different playlists
- **Curated collections**: Support for editorial and user-generated playlists

## Business Logic

The Tracks Resource implements comprehensive business logic for track management.

### Track Validation

Robust validation ensures data integrity:

- **Required fields**: Name, album, media type, and track number validation
- **Unique constraints**: Slug uniqueness within the system
- **Format validation**: Audio file format and size validation
- **Duration validation**: Minimum track duration requirements
- **Price validation**: Reasonable price range enforcement

### Duration Handling

Sophisticated duration management:

- **Millisecond precision**: Accurate duration tracking in milliseconds
- **Conversion utilities**: Easy conversion between minutes/seconds and milliseconds
- **Duration display**: Human-readable duration formatting
- **Duration-based filtering**: Search and filter tracks by duration ranges
- **Total album duration**: Automatic calculation of album total duration

### Sales Analytics

Comprehensive sales tracking and analytics:

- **Revenue tracking**: Individual track revenue calculation
- **Sales trends**: Historical sales data analysis
- **Popular tracks**: Best-selling track identification
- **Customer insights**: Purchase pattern analysis
- **Performance metrics**: Track performance across different time periods

## Performance Optimization

The Tracks Resource is optimized for performance with large music libraries.

## Authorization

Comprehensive authorization controls ensure secure access to track management features.

## Testing

The Tracks Resource includes comprehensive testing coverage for all functionality.

    public static function getRelations(): array
    {
        return [
            InvoiceLinesRelationManager::class,
            PlaylistsRelationManager::class,
            CategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTracks::route('/'),
            'create' => Pages\CreateTrack::route('/create'),
            'view' => Pages\ViewTrack::route('/{record}'),
            'edit' => Pages\EditTrack::route('/{record}/edit'),
        ];
    }

    // Authorization methods
    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view-tracks') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create-tracks') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
```

---

## Navigation

**← Previous:** [Albums Resource](020-albums-resource.md)

**Next →** [Categories Resource](040-categories-resource.md)
