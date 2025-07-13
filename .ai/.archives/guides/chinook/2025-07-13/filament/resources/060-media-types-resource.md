# Media Types Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Advanced Features](#advanced-features)
- [File Type Management](#file-type-management)
- [Validation and Security](#validation-and-security)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Media Types resource in Filament 4 for the Chinook
application. The Media Types resource manages different audio and video formats, file specifications, and encoding
standards for the music platform.

**🚀 Key Features:**

- **Format Management**: Support for multiple audio/video formats
- **Encoding Standards**: Quality and compression settings
- **File Validation**: Comprehensive file type validation
- **Metadata Extraction**: Automatic media file analysis
- **WCAG 2.1 AA Compliance**: Accessible media type management

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/MediaTypeResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaTypeResource\Pages;
use App\Models\MediaType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaTypeResource extends Resource
{
    protected static ?string $model = MediaType::class;
    protected static ?string $navigationIcon = 'heroicon-o-musical-note';
    protected static ?string $navigationGroup = 'System Configuration';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Media Type Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(MediaType::class, 'name', ignoreRecord: true)
                        ->helperText('Display name for this media type'),

                    Forms\Components\TextInput::make('mime_type')
                        ->label('MIME Type')
                        ->required()
                        ->maxLength(255)
                        ->unique(MediaType::class, 'mime_type', ignoreRecord: true)
                        ->placeholder('audio/mpeg, video/mp4, etc.')
                        ->helperText('Standard MIME type identifier'),

                    Forms\Components\TextInput::make('file_extension')
                        ->label('File Extension')
                        ->required()
                        ->maxLength(10)
                        ->prefix('.')
                        ->unique(MediaType::class, 'file_extension', ignoreRecord: true)
                        ->rules(['regex:/^[a-zA-Z0-9]+$/'])
                        ->helperText('File extension without the dot'),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(500)
                        ->rows(3)
                        ->helperText('Brief description of this media type'),
                ])->columns(2),

            Forms\Components\Section::make('Technical Specifications')
                ->schema([
                    Forms\Components\Select::make('category')
                        ->options([
                            'audio' => 'Audio',
                            'video' => 'Video',
                            'image' => 'Image',
                            'document' => 'Document',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => 
                            $set('supports_streaming', in_array($state, ['audio', 'video']))
                        ),

                    Forms\Components\Select::make('quality_level')
                        ->options([
                            'low' => 'Low Quality (64-128 kbps)',
                            'standard' => 'Standard Quality (128-256 kbps)',
                            'high' => 'High Quality (256-320 kbps)',
                            'lossless' => 'Lossless (FLAC, ALAC)',
                        ])
                        ->default('standard')
                        ->visible(fn (Forms\Get $get) => $get('category') === 'audio'),

                    Forms\Components\TextInput::make('max_file_size_mb')
                        ->label('Max File Size (MB)')
                        ->numeric()
                        ->default(100)
                        ->minValue(1)
                        ->maxValue(1000)
                        ->suffix('MB'),

                    Forms\Components\TextInput::make('max_duration_seconds')
                        ->label('Max Duration (seconds)')
                        ->numeric()
                        ->default(3600)
                        ->minValue(1)
                        ->suffix('seconds')
                        ->visible(fn (Forms\Get $get) => in_array($get('category'), ['audio', 'video'])),
                ])->columns(2),

            Forms\Components\Section::make('Features & Capabilities')
                ->schema([
                    Forms\Components\Toggle::make('supports_streaming')
                        ->label('Supports Streaming')
                        ->helperText('Can this format be streamed in real-time?'),

                    Forms\Components\Toggle::make('supports_download')
                        ->label('Supports Download')
                        ->default(true)
                        ->helperText('Can users download files in this format?'),

                    Forms\Components\Toggle::make('supports_metadata')
                        ->label('Supports Metadata')
                        ->default(true)
                        ->helperText('Does this format support embedded metadata?'),

                    Forms\Components\Toggle::make('requires_transcoding')
                        ->label('Requires Transcoding')
                        ->helperText('Should files be transcoded for web delivery?'),

                    Forms\Components\Toggle::make('is_web_compatible')
                        ->label('Web Compatible')
                        ->default(true)
                        ->helperText('Can be played directly in web browsers'),

                    Forms\Components\Toggle::make('is_mobile_compatible')
                        ->label('Mobile Compatible')
                        ->default(true)
                        ->helperText('Supported on mobile devices'),
                ])->columns(3),

            Forms\Components\Section::make('Encoding Settings')
                ->schema([
                    Forms\Components\KeyValue::make('encoding_parameters')
                        ->label('Encoding Parameters')
                        ->keyLabel('Parameter')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Parameter')
                        ->helperText('Technical encoding parameters for this format'),

                    Forms\Components\Repeater::make('supported_codecs')
                        ->label('Supported Codecs')
                        ->schema([
                            Forms\Components\TextInput::make('codec_name')
                                ->label('Codec Name')
                                ->required(),
                            Forms\Components\TextInput::make('codec_description')
                                ->label('Description'),
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['codec_name'] ?? null),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('MIME Type')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('MIME type copied')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('file_extension')
                    ->label('Extension')
                    ->formatStateUsing(fn (string $state): string => ".{$state}")
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'audio' => 'success',
                        'video' => 'info',
                        'image' => 'warning',
                        'document' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('quality_level')
                    ->label('Quality')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'lossless' => 'success',
                        'high' => 'info',
                        'standard' => 'warning',
                        'low' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'lossless' => 'Lossless',
                        'high' => 'High',
                        'standard' => 'Standard',
                        'low' => 'Low',
                        default => 'N/A',
                    }),

                Tables\Columns\TextColumn::make('max_file_size_mb')
                    ->label('Max Size')
                    ->suffix(' MB')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('supports_streaming')
                    ->label('Streaming')
                    ->boolean()
                    ->trueIcon('heroicon-o-play')
                    ->falseIcon('heroicon-o-x-mark'),

                Tables\Columns\IconColumn::make('is_web_compatible')
                    ->label('Web')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-x-mark'),

                Tables\Columns\TextColumn::make('tracks_count')
                    ->label('Usage')
                    ->counts('tracks')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'audio' => 'Audio',
                        'video' => 'Video',
                        'image' => 'Image',
                        'document' => 'Document',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('quality_level')
                    ->label('Quality Level')
                    ->options([
                        'low' => 'Low Quality',
                        'standard' => 'Standard Quality',
                        'high' => 'High Quality',
                        'lossless' => 'Lossless',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('supports_streaming')
                    ->label('Streaming Support')
                    ->trueLabel('Supports streaming')
                    ->falseLabel('No streaming support')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_web_compatible')
                    ->label('Web Compatibility')
                    ->trueLabel('Web compatible')
                    ->falseLabel('Not web compatible')
                    ->native(false),

                Tables\Filters\Filter::make('file_size_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_size')
                                    ->label('Min Size (MB)')
                                    ->numeric(),
                                Forms\Components\TextInput::make('max_size')
                                    ->label('Max Size (MB)')
                                    ->numeric(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_size'],
                                fn (Builder $query, $minSize): Builder => 
                                    $query->where('max_file_size_mb', '>=', $minSize),
                            )
                            ->when(
                                $data['max_size'],
                                fn (Builder $query, $maxSize): Builder => 
                                    $query->where('max_file_size_mb', '<=', $maxSize),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (MediaType $record) {
                        if ($record->tracks()->exists()) {
                            throw new \Exception('Cannot delete media type that is in use by tracks.');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records) {
                            $inUse = $records->filter(fn (MediaType $record) => $record->tracks()->exists());
                            if ($inUse->isNotEmpty()) {
                                throw new \Exception('Cannot delete media types that are in use by tracks.');
                            }
                        }),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaTypes::route('/'),
            'create' => Pages\CreateMediaType::route('/create'),
            'view' => Pages\ViewMediaType::route('/{record}'),
            'edit' => Pages\EditMediaType::route('/{record}/edit'),
        ];
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
// Custom form components for media type management

class MediaTypeFormComponents
{
    public static function codecSelector(): Forms\Components\Component
    {
        return Forms\Components\CheckboxList::make('supported_codecs')
            ->label('Supported Codecs')
            ->options([
                'mp3' => 'MP3 (MPEG-1 Audio Layer III)',
                'aac' => 'AAC (Advanced Audio Coding)',
                'flac' => 'FLAC (Free Lossless Audio Codec)',
                'ogg' => 'OGG Vorbis',
                'wav' => 'WAV (Waveform Audio File Format)',
                'h264' => 'H.264 (AVC)',
                'h265' => 'H.265 (HEVC)',
                'vp9' => 'VP9',
                'av1' => 'AV1',
            ])
            ->columns(2)
            ->gridDirection('row')
            ->helperText('Select all codecs supported by this media type');
    }

    public static function qualityPresets(): Forms\Components\Component
    {
        return Forms\Components\Select::make('quality_preset')
            ->label('Quality Preset')
            ->options([
                'web_low' => 'Web Low (64 kbps)',
                'web_standard' => 'Web Standard (128 kbps)',
                'web_high' => 'Web High (256 kbps)',
                'download_standard' => 'Download Standard (320 kbps)',
                'download_lossless' => 'Download Lossless (FLAC)',
            ])
            ->live()
            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                $presets = [
                    'web_low' => ['bitrate' => 64, 'sample_rate' => 44100],
                    'web_standard' => ['bitrate' => 128, 'sample_rate' => 44100],
                    'web_high' => ['bitrate' => 256, 'sample_rate' => 44100],
                    'download_standard' => ['bitrate' => 320, 'sample_rate' => 44100],
                    'download_lossless' => ['bitrate' => null, 'sample_rate' => 96000],
                ];

                if (isset($presets[$state])) {
                    $set('default_bitrate', $presets[$state]['bitrate']);
                    $set('default_sample_rate', $presets[$state]['sample_rate']);
                }
            });
    }
}
```

## Table Configuration

### Advanced Table Features

```php
<?php
// Enhanced table configuration for media types

class MediaTypeTableConfiguration
{
    public static function getAdvancedColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->searchable(),
                    
                Tables\Columns\Layout\Grid::make(3)
                    ->schema([
                        Tables\Columns\TextColumn::make('mime_type')
                            ->color('gray')
                            ->size(TextColumnSize::Small),
                            
                        Tables\Columns\TextColumn::make('file_extension')
                            ->formatStateUsing(fn (string $state): string => ".{$state}")
                            ->badge()
                            ->color('gray'),
                            
                        Tables\Columns\TextColumn::make('category')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'audio' => 'success',
                                'video' => 'info',
                                default => 'gray',
                            }),
                    ]),
                    
                Tables\Columns\Layout\Grid::make(4)
                    ->schema([
                        Tables\Columns\IconColumn::make('supports_streaming')
                            ->label('Stream')
                            ->boolean(),
                            
                        Tables\Columns\IconColumn::make('supports_download')
                            ->label('Download')
                            ->boolean(),
                            
                        Tables\Columns\IconColumn::make('is_web_compatible')
                            ->label('Web')
                            ->boolean(),
                            
                        Tables\Columns\TextColumn::make('tracks_count')
                            ->label('Usage')
                            ->counts('tracks')
                            ->badge(),
                    ]),
            ])->space(2),
        ];
    }
}
```

## Advanced Features

### File Type Validation

```php
<?php
// app/Services/MediaTypeValidationService.php

namespace App\Services;

use App\Models\MediaType;
use Illuminate\Http\UploadedFile;

class MediaTypeValidationService
{
    /**
     * Validate uploaded file against media type
     */
    public function validateFile(UploadedFile $file, MediaType $mediaType): array
    {
        $errors = [];

        // Check MIME type
        if ($file->getMimeType() !== $mediaType->mime_type) {
            $errors[] = "File MIME type {$file->getMimeType()} does not match expected {$mediaType->mime_type}";
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== $mediaType->file_extension) {
            $errors[] = "File extension .{$extension} does not match expected .{$mediaType->file_extension}";
        }

        // Check file size
        $fileSizeMB = $file->getSize() / 1024 / 1024;
        if ($fileSizeMB > $mediaType->max_file_size_mb) {
            $errors[] = "File size {$fileSizeMB}MB exceeds maximum {$mediaType->max_file_size_mb}MB";
        }

        // Check duration for audio/video files
        if (in_array($mediaType->category, ['audio', 'video'])) {
            $duration = $this->getMediaDuration($file);
            if ($duration && $duration > $mediaType->max_duration_seconds) {
                $errors[] = "Media duration {$duration}s exceeds maximum {$mediaType->max_duration_seconds}s";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'metadata' => $this->extractMetadata($file, $mediaType),
        ];
    }

    /**
     * Extract metadata from media file
     */
    private function extractMetadata(UploadedFile $file, MediaType $mediaType): array
    {
        if (!$mediaType->supports_metadata) {
            return [];
        }

        // Use appropriate metadata extraction based on file type
        return match($mediaType->category) {
            'audio' => $this->extractAudioMetadata($file),
            'video' => $this->extractVideoMetadata($file),
            'image' => $this->extractImageMetadata($file),
            default => [],
        };
    }

    private function extractAudioMetadata(UploadedFile $file): array
    {
        // Implementation would use libraries like getID3
        return [
            'title' => null,
            'artist' => null,
            'album' => null,
            'duration' => null,
            'bitrate' => null,
            'sample_rate' => null,
        ];
    }
}
```

## File Type Management

### Dynamic Media Type Creation

```php
<?php
// app/Filament/Resources/MediaTypeResource/Pages/CreateMediaType.php

namespace App\Filament\Resources\MediaTypeResource\Pages;

use App\Filament\Resources\MediaTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaType extends CreateRecord
{
    protected static string $resource = MediaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_common_types')
                ->label('Import Common Types')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $this->importCommonMediaTypes();
                })
                ->requiresConfirmation()
                ->modalHeading('Import Common Media Types')
                ->modalDescription('This will create standard audio and video media types. Existing types will not be duplicated.')
                ->modalSubmitActionLabel('Import'),
        ];
    }

    private function importCommonMediaTypes(): void
    {
        $commonTypes = [
            [
                'name' => 'MP3 Audio',
                'mime_type' => 'audio/mpeg',
                'file_extension' => 'mp3',
                'category' => 'audio',
                'quality_level' => 'standard',
                'supports_streaming' => true,
                'supports_metadata' => true,
                'is_web_compatible' => true,
            ],
            [
                'name' => 'FLAC Audio',
                'mime_type' => 'audio/flac',
                'file_extension' => 'flac',
                'category' => 'audio',
                'quality_level' => 'lossless',
                'supports_streaming' => false,
                'supports_metadata' => true,
                'is_web_compatible' => false,
            ],
            [
                'name' => 'MP4 Video',
                'mime_type' => 'video/mp4',
                'file_extension' => 'mp4',
                'category' => 'video',
                'supports_streaming' => true,
                'supports_metadata' => true,
                'is_web_compatible' => true,
            ],
        ];

        foreach ($commonTypes as $typeData) {
            MediaType::firstOrCreate(
                ['mime_type' => $typeData['mime_type']],
                $typeData
            );
        }

        Notification::make()
            ->title('Common media types imported successfully')
            ->success()
            ->send();
    }
}
```

## Validation and Security

### Security Considerations

```php
<?php
// Security validation for media types

class MediaTypeSecurityService
{
    private array $dangerousExtensions = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'php', 'asp'
    ];

    private array $allowedMimeTypes = [
        'audio/mpeg', 'audio/wav', 'audio/flac', 'audio/aac', 'audio/ogg',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ];

    public function validateMediaTypeSecurity(array $data): array
    {
        $errors = [];

        // Check for dangerous file extensions
        if (in_array(strtolower($data['file_extension']), $this->dangerousExtensions)) {
            $errors[] = 'File extension is not allowed for security reasons';
        }

        // Validate MIME type against whitelist
        if (!in_array($data['mime_type'], $this->allowedMimeTypes)) {
            $errors[] = 'MIME type is not in the allowed list';
        }

        // Check for MIME type spoofing attempts
        if ($this->detectMimeTypeSpoofing($data['mime_type'], $data['file_extension'])) {
            $errors[] = 'MIME type does not match file extension';
        }

        return $errors;
    }

    private function detectMimeTypeSpoofing(string $mimeType, string $extension): bool
    {
        $validCombinations = [
            'audio/mpeg' => ['mp3'],
            'audio/wav' => ['wav'],
            'audio/flac' => ['flac'],
            'video/mp4' => ['mp4'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
        ];

        return !in_array($extension, $validCombinations[$mimeType] ?? []);
    }
}
```

## Testing

### Resource Testing

```php
<?php
// tests/Feature/Filament/MediaTypeResourceTest.php

use App\Filament\Resources\MediaTypeResource;
use App\Models\{MediaType, User};
use Tests\TestCase;

class MediaTypeResourceTest extends TestCase
{
    public function test_can_render_media_type_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(MediaTypeResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_media_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-media-types');
        $this->actingAs($user);

        $mediaTypeData = [
            'name' => 'Test Audio Format',
            'mime_type' => 'audio/test',
            'file_extension' => 'test',
            'category' => 'audio',
            'max_file_size_mb' => 50,
        ];

        $response = $this->post(MediaTypeResource::getUrl('create'), $mediaTypeData);
        
        $this->assertDatabaseHas('media_types', $mediaTypeData);
    }

    public function test_cannot_delete_media_type_in_use(): void
    {
        $user = User::factory()->create();
        $mediaType = MediaType::factory()->create();
        Track::factory()->create(['media_type_id' => $mediaType->id]);
        
        $this->actingAs($user);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete media type that is in use');

        $response = $this->delete(MediaTypeResource::getUrl('edit', ['record' => $mediaType]));
    }
}
```

## Best Practices

### Media Type Guidelines

1. **Security First**: Validate all file types and MIME types
2. **Performance**: Consider encoding and streaming requirements
3. **Compatibility**: Test across different devices and browsers
4. **Standards**: Follow industry standards for media formats
5. **Metadata**: Preserve and utilize embedded metadata
6. **Quality**: Implement appropriate quality levels for different use cases

### Performance Optimization

```php
<?php
// Optimized media type queries

class MediaTypeResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['tracks'])
            ->when(
                request()->has('category'),
                fn (Builder $query) => $query->where('category', request('category'))
            );
    }
}
```

## Navigation

**← Previous:** [Playlists Resource Guide](050-playlists-resource.md)
**Next →** [Customers Resource Guide](070-customers-resource.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components](120-form-components.md) - Advanced form patterns
- [Table Features Guide](130-table-features.md) - Advanced table configuration and features

---

*This guide provides comprehensive Filament 4 resource implementation for media type management in the Chinook
application. Each pattern includes security validation, performance optimization, and accessibility considerations for
robust media format handling.*
