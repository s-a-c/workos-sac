# ChinookPlaylists Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Relationship Management](#relationship-management)
- [Actions and Bulk Actions](#actions-and-bulk-actions)
- [Permissions and Policies](#permissions-and-policies)
- [Advanced Features](#advanced-features)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Playlists resource in Filament 4 for the Chinook application.
The Playlists resource manages user-created music collections with collaborative features, track management, and
advanced filtering capabilities.

**🚀 Key Features:**

- **Collaborative Playlists**: Multi-user playlist management
- **Track Management**: Drag-and-drop track ordering
- **Advanced Filtering**: Genre, mood, and duration filters
- **Bulk Operations**: Efficient playlist management
- **WCAG 2.1 AA Compliance**: Accessible playlist interface

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/PlaylistResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\PlaylistResource\Pages;
use App\Filament\Resources\PlaylistResource\RelationManagers;
use App\Models\Playlist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlaylistResource extends Resource
{
    protected static ?string $model = Playlist::class;
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Music Management';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Playlist Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) => 
                            $context === 'create' ? $set('slug', Str::slug($state)) : null
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(Playlist::class, 'slug', ignoreRecord: true)
                        ->rules(['alpha_dash']),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(1000)
                        ->rows(3),

                    Forms\Components\FileUpload::make('cover_image')
                        ->image()
                        ->directory('playlist-covers')
                        ->visibility('public')
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            '1:1',
                        ]),
                ])->columns(2),

            Forms\Components\Section::make('Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_public')
                        ->label('Public Playlist')
                        ->helperText('Public playlists can be discovered by other users'),

                    Forms\Components\Toggle::make('is_collaborative')
                        ->label('Collaborative')
                        ->helperText('Allow other users to add tracks to this playlist'),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured')
                        ->helperText('Feature this playlist on the homepage')
                        ->visible(fn () => auth()->user()->can('feature-playlists')),

                    Forms\Components\Select::make('mood')
                        ->options([
                            'energetic' => 'Energetic',
                            'chill' => 'Chill',
                            'romantic' => 'Romantic',
                            'workout' => 'Workout',
                            'focus' => 'Focus',
                            'party' => 'Party',
                        ])
                        ->searchable(),
                ])->columns(2),

            Forms\Components\Section::make('Collaborators')
                ->schema([
                    Forms\Components\Repeater::make('collaborators')
                        ->relationship()
                        ->schema([
                            Forms\Components\Select::make('user_id')
                                ->label('User')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Forms\Components\Select::make('permission_level')
                                ->options([
                                    'view' => 'View Only',
                                    'add' => 'Add Tracks',
                                    'edit' => 'Edit Playlist',
                                    'admin' => 'Full Access',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => 
                            User::find($state['user_id'])?->name ?? null
                        ),
                ])
                ->visible(fn (Forms\Get $get) => $get('is_collaborative')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Cover')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tracks_count')
                    ->label('Tracks')
                    ->counts('tracks')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_duration')
                    ->label('Duration')
                    ->getStateUsing(function (Playlist $record): string {
                        $totalMs = $record->tracks()->sum('duration_ms');
                        $minutes = intval($totalMs / 60000);
                        $seconds = intval(($totalMs % 60000) / 1000);
                        return sprintf('%d:%02d', $minutes, $seconds);
                    }),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-lock-closed'),

                Tables\Columns\IconColumn::make('is_collaborative')
                    ->label('Collaborative')
                    ->boolean()
                    ->trueIcon('heroicon-o-users')
                    ->falseIcon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Visibility')
                    ->trueLabel('Public only')
                    ->falseLabel('Private only')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_collaborative')
                    ->label('Collaboration')
                    ->trueLabel('Collaborative only')
                    ->falseLabel('Solo only')
                    ->native(false),

                Tables\Filters\SelectFilter::make('mood')
                    ->options([
                        'energetic' => 'Energetic',
                        'chill' => 'Chill',
                        'romantic' => 'Romantic',
                        'workout' => 'Workout',
                        'focus' => 'Focus',
                        'party' => 'Party',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('has_tracks')
                    ->label('Has Tracks')
                    ->query(fn (Builder $query): Builder => $query->has('tracks')),

                Tables\Filters\TrashedFilter::make(),
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
                    
                    Tables\Actions\BulkAction::make('make_public')
                        ->label('Make Public')
                        ->icon('heroicon-o-globe-alt')
                        ->action(function (Collection $records) {
                            $records->each->update(['is_public' => true]);
                        })
                        ->requiresConfirmation()
                        ->color('success'),

                    Tables\Actions\BulkAction::make('make_private')
                        ->label('Make Private')
                        ->icon('heroicon-o-lock-closed')
                        ->action(function (Collection $records) {
                            $records->each->update(['is_public' => false]);
                        })
                        ->requiresConfirmation()
                        ->color('warning'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TracksRelationManager::class,
            RelationManagers\CollaboratorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaylists::route('/'),
            'create' => Pages\CreatePlaylist::route('/create'),
            'view' => Pages\ViewPlaylist::route('/{record}'),
            'edit' => Pages\EditPlaylist::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
```

## Form Components

### Advanced Form Features

```php
<?php
// Custom form components for playlist management

class PlaylistFormComponents
{
    public static function trackSelector(): Forms\Components\Component
    {
        return Forms\Components\Select::make('track_ids')
            ->label('Add Tracks')
            ->multiple()
            ->relationship('tracks', 'name')
            ->getOptionLabelFromRecordUsing(fn (Track $record): string => 
                "{$record->name} - {$record->album->artist->name}"
            )
            ->searchable(['name', 'album.title', 'album.artist.name'])
            ->preload()
            ->optionsLimit(50)
            ->helperText('Search by track name, album, or artist');
    }

    public static function moodSelector(): Forms\Components\Component
    {
        return Forms\Components\CheckboxList::make('moods')
            ->label('Playlist Moods')
            ->options([
                'energetic' => 'Energetic',
                'chill' => 'Chill',
                'romantic' => 'Romantic',
                'workout' => 'Workout',
                'focus' => 'Focus',
                'party' => 'Party',
                'nostalgic' => 'Nostalgic',
                'uplifting' => 'Uplifting',
            ])
            ->columns(2)
            ->gridDirection('row');
    }

    public static function privacySettings(): Forms\Components\Component
    {
        return Forms\Components\Fieldset::make('Privacy Settings')
            ->schema([
                Forms\Components\Radio::make('visibility')
                    ->options([
                        'public' => 'Public - Anyone can find and listen',
                        'unlisted' => 'Unlisted - Only people with the link',
                        'private' => 'Private - Only you can access',
                    ])
                    ->default('public')
                    ->inline()
                    ->required(),

                Forms\Components\Toggle::make('allow_downloads')
                    ->label('Allow Downloads')
                    ->helperText('Let users download this playlist'),

                Forms\Components\Toggle::make('allow_embedding')
                    ->label('Allow Embedding')
                    ->helperText('Allow this playlist to be embedded on other websites'),
            ]);
    }
}
```

## Table Configuration

### Advanced Table Features

```php
<?php
// Enhanced table configuration

class PlaylistTableConfiguration
{
    public static function getAdvancedColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->height(60)
                    ->width(60),
                    
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('name')
                        ->weight(FontWeight::Bold)
                        ->searchable(),
                        
                    Tables\Columns\TextColumn::make('description')
                        ->limit(50)
                        ->color('gray'),
                        
                    Tables\Columns\Layout\Grid::make(3)
                        ->schema([
                            Tables\Columns\TextColumn::make('tracks_count')
                                ->label('Tracks')
                                ->counts('tracks')
                                ->badge(),
                                
                            Tables\Columns\TextColumn::make('creator.name')
                                ->label('By')
                                ->prefix('by '),
                                
                            Tables\Columns\TextColumn::make('created_at')
                                ->label('Created')
                                ->since(),
                        ]),
                ]),
            ])->space(3),
        ];
    }

    public static function getAdvancedFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('creator')
                ->relationship('creator', 'name')
                ->searchable()
                ->preload(),

            Tables\Filters\Filter::make('track_count')
                ->form([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('min_tracks')
                                ->label('Min Tracks')
                                ->numeric(),
                            Forms\Components\TextInput::make('max_tracks')
                                ->label('Max Tracks')
                                ->numeric(),
                        ]),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['min_tracks'],
                            fn (Builder $query, $minTracks): Builder => 
                                $query->has('tracks', '>=', $minTracks),
                        )
                        ->when(
                            $data['max_tracks'],
                            fn (Builder $query, $maxTracks): Builder => 
                                $query->has('tracks', '<=', $maxTracks),
                        );
                }),

            Tables\Filters\Filter::make('duration')
                ->form([
                    Forms\Components\Select::make('duration_range')
                        ->options([
                            'short' => 'Short (< 30 min)',
                            'medium' => 'Medium (30-60 min)',
                            'long' => 'Long (> 60 min)',
                        ]),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['duration_range'],
                        function (Builder $query, $range): Builder {
                            return match($range) {
                                'short' => $query->whereHas('tracks', function ($q) {
                                    $q->havingRaw('SUM(duration_ms) < ?', [1800000]); // 30 min
                                }),
                                'medium' => $query->whereHas('tracks', function ($q) {
                                    $q->havingRaw('SUM(duration_ms) BETWEEN ? AND ?', [1800000, 3600000]);
                                }),
                                'long' => $query->whereHas('tracks', function ($q) {
                                    $q->havingRaw('SUM(duration_ms) > ?', [3600000]); // 60 min
                                }),
                                default => $query,
                            };
                        }
                    );
                }),
        ];
    }
}
```

## Relationship Management

### Tracks Relation Manager

```php
<?php
// app/Filament/Resources/PlaylistResource/RelationManagers/TracksRelationManager.php

namespace App\Filament\Resources\PlaylistResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TracksRelationManager extends RelationManager
{
    protected static string $relationship = 'tracks';
    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('track_id')
                    ->label('Track')
                    ->relationship('track', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => 
                        "{$record->name} - {$record->album->artist->name}"
                    )
                    ->searchable(['name', 'album.title', 'album.artist.name'])
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(fn () => $this->getOwnerRecord()->tracks()->max('position') + 1),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('position')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Track')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('album.artist.name')
                    ->label('Artist')
                    ->searchable(),

                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->searchable(),

                Tables\Columns\TextColumn::make('duration_formatted')
                    ->label('Duration'),

                Tables\Columns\TextColumn::make('pivot.added_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Track'),
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('position')
            ->defaultSort('position');
    }
}
```

## Actions and Bulk Actions

### Custom Actions

```php
<?php
// Custom playlist actions

class PlaylistActions
{
    public static function duplicateAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('duplicate')
            ->label('Duplicate Playlist')
            ->icon('heroicon-o-document-duplicate')
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label('New Playlist Name')
                    ->required()
                    ->default(fn (Playlist $record) => $record->name . ' (Copy)'),
                    
                Forms\Components\Toggle::make('copy_tracks')
                    ->label('Copy Tracks')
                    ->default(true),
                    
                Forms\Components\Toggle::make('copy_collaborators')
                    ->label('Copy Collaborators')
                    ->default(false),
            ])
            ->action(function (Playlist $record, array $data) {
                $newPlaylist = $record->replicate();
                $newPlaylist->name = $data['name'];
                $newPlaylist->slug = Str::slug($data['name']);
                $newPlaylist->save();

                if ($data['copy_tracks']) {
                    $trackData = $record->tracks()->get()->map(function ($track) {
                        return [
                            'track_id' => $track->id,
                            'position' => $track->pivot->position,
                            'added_at' => now(),
                            'added_by' => auth()->id(),
                        ];
                    });
                    
                    $newPlaylist->tracks()->attach($trackData);
                }

                if ($data['copy_collaborators']) {
                    $collaboratorData = $record->collaborators()->get()->map(function ($collaborator) {
                        return [
                            'user_id' => $collaborator->id,
                            'permission_level' => $collaborator->pivot->permission_level,
                        ];
                    });
                    
                    $newPlaylist->collaborators()->attach($collaboratorData);
                }

                Notification::make()
                    ->title('Playlist duplicated successfully')
                    ->success()
                    ->send();
            })
            ->requiresConfirmation();
    }

    public static function exportAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('export')
            ->label('Export Playlist')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Forms\Components\Select::make('format')
                    ->options([
                        'm3u' => 'M3U Playlist',
                        'csv' => 'CSV File',
                        'json' => 'JSON Data',
                    ])
                    ->default('m3u')
                    ->required(),
            ])
            ->action(function (Playlist $record, array $data) {
                $exporter = new PlaylistExporter($record);
                $filename = $exporter->export($data['format']);
                
                return response()->download($filename);
            });
    }
}
```

## Permissions and Policies

### Resource Permissions

```php
<?php
// Playlist resource permissions

class PlaylistResource extends Resource
{
    public static function canViewAny(): bool
    {
        return auth()->user()->can('viewAny', Playlist::class);
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', Playlist::class);
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view', $record);
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete', $record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('deleteAny', Playlist::class);
    }
}
```

## Advanced Features

### Custom Page Components

```php
<?php
// app/Filament/Resources/PlaylistResource/Pages/ViewPlaylist.php

namespace App\Filament\Resources\PlaylistResource\Pages;

use App\Filament\Resources\PlaylistResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewPlaylist extends ViewRecord
{
    protected static string $resource = PlaylistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            PlaylistActions::duplicateAction(),
            PlaylistActions::exportAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Playlist Details')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('name')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight(FontWeight::Bold),
                                        
                                    Infolists\Components\TextEntry::make('creator.name')
                                        ->label('Created by'),
                                        
                                    Infolists\Components\TextEntry::make('description')
                                        ->columnSpanFull(),
                                ]),
                                
                            Infolists\Components\ImageEntry::make('cover_image')
                                ->height(200)
                                ->width(200),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('tracks_count')
                                    ->label('Total Tracks')
                                    ->badge(),
                                    
                                Infolists\Components\TextEntry::make('total_duration')
                                    ->label('Total Duration')
                                    ->getStateUsing(function ($record) {
                                        $totalMs = $record->tracks()->sum('duration_ms');
                                        return gmdate('H:i:s', $totalMs / 1000);
                                    }),
                                    
                                Infolists\Components\TextEntry::make('collaborators_count')
                                    ->label('Collaborators')
                                    ->counts('collaborators'),
                                    
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created')
                                    ->since(),
                            ]),
                    ]),
            ]);
    }
}
```

## Testing

### Resource Testing

```php
<?php
// tests/Feature/Filament/PlaylistResourceTest.php

use App\Filament\Resources\PlaylistResource;
use App\Models\{Playlist, User};
use Tests\TestCase;

class PlaylistResourceTest extends TestCase
{
    public function test_can_render_playlist_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(PlaylistResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_playlist(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-playlists');
        $this->actingAs($user);

        $playlistData = [
            'name' => 'Test Playlist',
            'description' => 'A test playlist',
            'is_public' => true,
        ];

        $response = $this->post(PlaylistResource::getUrl('create'), $playlistData);
        
        $this->assertDatabaseHas('playlists', $playlistData);
    }

    public function test_can_edit_own_playlist(): void
    {
        $user = User::factory()->create();
        $playlist = Playlist::factory()->create(['created_by' => $user->id]);
        $this->actingAs($user);

        $response = $this->get(PlaylistResource::getUrl('edit', ['record' => $playlist]));
        $response->assertSuccessful();
    }
}
```

## Best Practices

### Playlist Resource Guidelines

1. **User Experience**: Prioritize intuitive playlist management
2. **Performance**: Optimize queries for large playlists
3. **Collaboration**: Implement clear permission levels
4. **Accessibility**: Ensure WCAG 2.1 AA compliance
5. **Security**: Validate user permissions thoroughly
6. **Testing**: Write comprehensive feature tests

### Performance Optimization

```php
<?php
// Optimized queries for playlist management

class PlaylistResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['creator', 'tracks'])
            ->withCount(['tracks', 'collaborators'])
            ->when(
                auth()->user()->cannot('view-all-playlists'),
                fn (Builder $query) => $query->where(function ($q) {
                    $q->where('is_public', true)
                      ->orWhere('created_by', auth()->id())
                      ->orWhereHas('collaborators', function ($collaboratorQuery) {
                          $collaboratorQuery->where('user_id', auth()->id());
                      });
                })
            );
    }
}
```

## Navigation

**← Previous:** [Categories Resource Guide](040-categories-resource.md)
**Next →** [Media Types Resource Guide](060-media-types-resource.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Relationship Managers](120-relationship-managers.md) - Managing model relationships
- [Form Components](120-form-components.md) - Advanced form patterns

---

*This guide provides comprehensive Filament 4 resource implementation for playlist management in the Chinook
application. Each pattern includes accessibility compliance, performance optimization, and security considerations for
robust playlist functionality.*
