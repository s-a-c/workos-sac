# ChinookArtists Resource Documentation

This guide covers the complete implementation of the ChinookArtists resource for the Chinook admin panel, including form components, table features, relationship managers, and advanced functionality.

## Table of Contents

- [Resource Overview](#resource-overview)
- [Model Requirements](#model-requirements)
- [Form Implementation](#form-implementation)
- [Table Configuration](#table-configuration)
- [Relationship Managers](#relationship-managers)
- [Advanced Features](#advanced-features)
- [Authorization](#authorization)
- [Testing](#testing)

## Resource Overview

The ChinookArtists resource manages music artists and bands with comprehensive metadata, relationship management, and advanced search capabilities.

### Key Features
- **Complete ChinookArtist Management**: Name, biography, website, social links
- **Image Management**: ChinookArtist photos with media library integration
- **ChinookAlbum Relationship**: Manage artist's albums with inline editing
- **Category System**: Polymorphic genre and style categorization
- **Geographic Data**: Country of origin and regional information
- **Activity Status**: Track active/inactive artists
- **Advanced Search**: Full-text search across all artist data
- **Bulk Operations**: Mass operations for artist management

## Model Requirements

### Artist Model Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryType;
use App\Traits\Categorizable;
use App\Traits\HasSecondaryUniqueKey;
use App\Traits\HasSlug;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;
use Wildside\Userstamps\Userstamps;

class ChinookArtist extends Model implements HasMedia
{
    use HasFactory;
    use HasSecondaryUniqueKey;
    use HasSlug;
    use HasTags;
    use SoftDeletes;
    use Userstamps;
    use Categorizable;
    use InteractsWithMedia;

    protected $table = 'chinook_artists';

    protected $fillable = [
        'name',
        'biography',
        'website',
        'social_links',
        'country',
        'formed_year',
        'is_active',
        'public_id',
        'slug',
    ];

    /**
     * Get the attributes that should be cast.
     */
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
     * Get the secondary key type for this model.
     */
    public function getSecondaryKeyType(): string
    {
        return 'ulid'; // ULID for chronological ordering
    }

    /**
     * Get the albums for this artist.
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * Get the tracks for this artist through albums.
     */
    public function tracks()
    {
        return $this->hasManyThrough(Track::class, Album::class);
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Register media conversions.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10);

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->quality(90);
    }

    /**
     * Scope to get active artists only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search artists.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('biography', 'like', "%{$search}%")
              ->orWhere('country', 'like', "%{$search}%");
        });
    }

    /**
     * Get the artist's primary photo.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('photos', 'preview');
    }

    /**
     * Get the artist's thumbnail.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('photos', 'thumb');
    }
}
```

## Form Implementation

### Complete Form Schema

```php
<?php

namespace App\Filament\ChinookAdmin\Resources;

use App\Models\Artist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-microphone';
    
    protected static ?string $navigationGroup = 'Music Management';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, Forms\Set $set) {
                                if ($context === 'create') {
                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Artist::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Select::make('country')
                            ->options([
                                'US' => 'United States',
                                'UK' => 'United Kingdom',
                                'CA' => 'Canada',
                                'AU' => 'Australia',
                                'DE' => 'Germany',
                                'FR' => 'France',
                                'IT' => 'Italy',
                                'ES' => 'Spain',
                                'JP' => 'Japan',
                                'BR' => 'Brazil',
                                // Add more countries as needed
                            ])
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('formed_year')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(date('Y'))
                            ->step(1),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Whether the artist is currently active'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detailed Information')
                    ->schema([
                        Forms\Components\RichEditor::make('biography')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                            ]),

                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://example.com'),

                        Forms\Components\Repeater::make('social_links')
                            ->schema([
                                Forms\Components\Select::make('platform')
                                    ->options([
                                        'facebook' => 'Facebook',
                                        'twitter' => 'Twitter',
                                        'instagram' => 'Instagram',
                                        'youtube' => 'YouTube',
                                        'spotify' => 'Spotify',
                                        'apple_music' => 'Apple Music',
                                        'soundcloud' => 'SoundCloud',
                                        'bandcamp' => 'Bandcamp',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('url')
                                    ->url()
                                    ->required()
                                    ->placeholder('https://example.com/artist'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null),
                    ]),

                Forms\Components\Section::make('Media')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->collection('photos')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                                '16:9',
                            ])
                            ->maxSize(5120) // 5MB
                            ->helperText('Upload artist photo (max 5MB)'),

                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(10)
                            ->maxSize(5120)
                            ->helperText('Upload additional photos (max 10 files, 5MB each)'),
                    ]),

                Forms\Components\Section::make('Categorization')
                    ->schema([
                        Forms\Components\Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                            ]),

                        // Polymorphic categories will be handled by the Categorizable trait
                        // This is a placeholder for category selection
                        Forms\Components\Placeholder::make('categories_info')
                            ->content('Categories can be managed through the Categories section'),
                    ]),
            ]);
    }
}
```

## Table Configuration

### Complete Table Implementation

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\SpatieMediaLibraryImageColumn::make('photos')
                ->collection('photos')
                ->conversion('thumb')
                ->height(60)
                ->width(60)
                ->circular(),

            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight(FontWeight::Bold),

            Tables\Columns\TextColumn::make('country')
                ->badge()
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('formed_year')
                ->label('Formed')
                ->sortable()
                ->toggleable(),

            Tables\Columns\TextColumn::make('albums_count')
                ->counts('albums')
                ->label('Albums')
                ->sortable()
                ->badge()
                ->color('success'),

            Tables\Columns\TextColumn::make('tracks_count')
                ->counts('tracks')
                ->label('Tracks')
                ->sortable()
                ->badge()
                ->color('info'),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),

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
            Tables\Filters\TrashedFilter::make(),
            
            Tables\Filters\SelectFilter::make('country')
                ->options([
                    'US' => 'United States',
                    'UK' => 'United Kingdom',
                    'CA' => 'Canada',
                    'AU' => 'Australia',
                    'DE' => 'Germany',
                    'FR' => 'France',
                ])
                ->multiple(),

            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active Status')
                ->boolean()
                ->trueLabel('Active artists only')
                ->falseLabel('Inactive artists only')
                ->native(false),

            Tables\Filters\Filter::make('formed_year')
                ->form([
                    Forms\Components\DatePicker::make('formed_from')
                        ->label('Formed from year'),
                    Forms\Components\DatePicker::make('formed_until')
                        ->label('Formed until year'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['formed_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('formed_year', '>=', $date),
                        )
                        ->when(
                            $data['formed_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('formed_year', '<=', $date),
                        );
                }),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
            Tables\Actions\RestoreAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                
                Tables\Actions\BulkAction::make('activate')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Collection $records) {
                        $records->each->update(['is_active' => true]);
                    })
                    ->requiresConfirmation(),

                Tables\Actions\BulkAction::make('deactivate')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(function (Collection $records) {
                        $records->each->update(['is_active' => false]);
                    })
                    ->requiresConfirmation(),
            ]),
        ])
        ->defaultSort('name')
        ->persistSortInSession()
        ->persistSearchInSession()
        ->persistFiltersInSession();
}
```

## Relationship Managers

### Albums Relationship Manager

```php
<?php

namespace App\Filament\ChinookAdmin\Resources\ArtistResource\RelationManagers;

use App\Models\Album;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AlbumsRelationManager extends RelationManager
{
    protected static string $relationship = 'albums';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Forms\Components\DatePicker::make('release_date')
                    ->label('Release Date'),

                Forms\Components\TextInput::make('label')
                    ->maxLength(255),

                Forms\Components\TextInput::make('catalog_number')
                    ->maxLength(100),

                Forms\Components\Textarea::make('description')
                    ->rows(3),

                Forms\Components\Toggle::make('is_compilation')
                    ->label('Compilation Album'),

                Forms\Components\Toggle::make('is_explicit')
                    ->label('Explicit Content'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('release_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tracks_count')
                    ->counts('tracks')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('total_duration_formatted')
                    ->label('Duration'),

                Tables\Columns\IconColumn::make('is_compilation')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_explicit')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_compilation'),
                Tables\Filters\TernaryFilter::make('is_explicit'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

## Advanced Features

### Global Search Configuration

```php
public static function getGlobalSearchEloquentQuery(): Builder
{
    return parent::getGlobalSearchEloquentQuery()->with(['albums']);
}

public static function getGloballySearchableAttributes(): array
{
    return ['name', 'biography', 'country', 'albums.title'];
}

public static function getGlobalSearchResultDetails(Model $record): array
{
    return [
        'Country' => $record->country,
        'Albums' => $record->albums_count,
        'Active' => $record->is_active ? 'Yes' : 'No',
    ];
}
```

### Custom Page Actions

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListArtists::route('/'),
        'create' => Pages\CreateArtist::route('/create'),
        'view' => Pages\ViewArtist::route('/{record}'),
        'edit' => Pages\EditArtist::route('/{record}/edit'),
        'analytics' => Pages\ArtistAnalytics::route('/{record}/analytics'),
    ];
}
```

## Authorization

### Resource Authorization

```php
public static function canViewAny(): bool
{
    return auth()->user()->can('view-artists');
}

public static function canCreate(): bool
{
    return auth()->user()->can('create-artists');
}

public static function canEdit($record): bool
{
    return auth()->user()->can('edit-artists');
}

public static function canDelete($record): bool
{
    return auth()->user()->can('delete-artists') && 
           $record->albums()->count() === 0; // Prevent deletion if has albums
}

protected static function shouldRegisterNavigation(): bool
{
    return auth()->user()->can('view-artists');
}
```

## Testing

### Feature Tests

```php
<?php

namespace Tests\Feature\ChinookAdmin\Resources;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_artists(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        
        Artist::factory()->count(3)->create();

        $this->actingAs($user)
            ->get('/chinook-admin/artists')
            ->assertStatus(200)
            ->assertSee('Artists');
    }

    public function test_can_create_artist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->actingAs($user)
            ->post('/chinook-admin/artists', [
                'name' => 'Test Artist',
                'country' => 'US',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('artists', [
            'name' => 'Test Artist',
            'country' => 'US',
        ]);
    }
}
```

## Next Steps

1. **Implement Albums Resource** - Create the albums resource with track relationships
2. **Add Category Integration** - Implement polymorphic category relationships
3. **Create Analytics Page** - Build artist analytics and reporting
4. **Implement Media Management** - Add comprehensive media handling
5. **Add Export Functionality** - Implement data export features

## Related Documentation

- **[Albums Resource](020-albums-resource.md)** - Album management implementation
- **[Categories Resource](040-categories-resource.md)** - Category system integration
- **[Relationship Managers](120-relationship-managers.md)** - Advanced relationship patterns
