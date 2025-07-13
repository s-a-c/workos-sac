# 1. Filament Spatie Laravel Media Library Plugin Integration Guide

> **Package Source:** [filament/spatie-laravel-media-library-plugin](https://github.com/filamentphp/spatie-laravel-media-library-plugin)  
> **Official Documentation:** [Filament Media Library Plugin Documentation](https://filamentphp.com/docs/spatie-laravel-media-library-plugin)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook database schema and entity prefixing  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Plugin Registration](#131-plugin-registration)
  - [1.3.2. Form Component Integration](#132-form-component-integration)
- [1.4. Chinook Resource Integration](#14-chinook-resource-integration)
  - [1.4.1. Artist Resource Media Forms](#141-artist-resource-media-forms)
  - [1.4.2. Album Resource Media Forms](#142-album-resource-media-forms)
  - [1.4.3. Track Resource Media Forms](#143-track-resource-media-forms)
- [1.5. Advanced Media Management](#15-advanced-media-management)
- [1.6. Performance & Security](#16-performance--security)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [Filament Media Library Plugin documentation](https://filamentphp.com/docs/spatie-laravel-media-library-plugin) for Laravel 12 and Chinook project requirements, integrating with the existing [awcodes/filament-curator](230-awcodes-filament-curator-guide.md) setup.

**Filament Spatie Laravel Media Library Plugin** provides seamless integration between Filament forms and the Spatie Laravel Media Library package. It offers intuitive form components for file uploads, media management, and display within Filament admin panels.

### 1.2.1. Key Features

- **Native Filament Integration**: Purpose-built form components for Filament
- **Media Collection Support**: Full support for Spatie Media Library collections
- **Drag & Drop Interface**: Intuitive file upload experience
- **Preview Generation**: Automatic image previews and thumbnails
- **Validation Integration**: Built-in file validation and error handling
- **Responsive Design**: Mobile-friendly upload interface

### 1.2.2. Integration with Curator

> **Package Synergy:** Works seamlessly with [Filament Curator](230-awcodes-filament-curator-guide.md) for comprehensive media management

- **Curator**: Provides media browser and bulk management capabilities
- **Media Library Plugin**: Provides form components for individual record media management
- **Combined Workflow**: Upload via forms, manage via Curator browser

## 1.3. Installation & Configuration

### 1.3.1. Plugin Registration

> **Configuration Source:** Based on [official plugin setup](https://filamentphp.com/docs/spatie-laravel-media-library-plugin/installation)  
> **Chinook Enhancement:** Already configured in AdminPanelProvider

The plugin is already registered in the admin panel. Verify configuration:

<augment_code_snippet path="app/Providers/Filament/AdminPanelProvider.php" mode="EXCERPT">
````php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelMediaLibraryPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // ... existing configuration ...
            
            // Spatie Laravel Media Library Plugin
            ->plugin(
                SpatieLaravelMediaLibraryPlugin::make()
                    ->registerNavigation(false) // Use Curator for navigation
            );
    }
}
````
</augment_code_snippet>

### 1.3.2. Form Component Integration

> **Component Usage:** Enhanced form components for Chinook media workflows

**Available Form Components:**

- `SpatieMediaLibraryFileUpload`: Single/multiple file uploads
- `SpatieMediaLibraryImageUpload`: Specialized image upload component
- `SpatieMediaLibraryAudioUpload`: Audio file upload component (custom)

## 1.4. Chinook Resource Integration

### 1.4.1. Artist Resource Media Forms

> **Artist Media Forms:** Comprehensive media management for artist profiles

<augment_code_snippet path="app/Filament/Admin/Resources/ChinookArtistResource.php" mode="EXCERPT">
````php
<?php

namespace App\Filament\Admin\Resources;

use App\Models\ChinookArtist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ChinookArtistResource extends Resource
{
    protected static ?string $model = ChinookArtist::class;
    protected static ?string $navigationIcon = 'heroicon-o-microphone';
    protected static ?string $navigationGroup = 'Music Catalog';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('biography')
                            ->rows(4)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media Management')
                    ->schema([
                        // Profile photo upload
                        SpatieMediaLibraryFileUpload::make('profile_photos')
                            ->label('Profile Photo')
                            ->collection('profile_photos')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                                '16:9',
                            ])
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable(false) // Single file
                            ->columnSpanFull(),

                        // Gallery photos
                        SpatieMediaLibraryFileUpload::make('gallery_photos')
                            ->label('Gallery Photos')
                            ->collection('gallery_photos')
                            ->image()
                            ->imageEditor()
                            ->multiple()
                            ->maxFiles(20)
                            ->maxSize(3072) // 3MB per file
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),

                        // Promotional materials
                        SpatieMediaLibraryFileUpload::make('promotional_materials')
                            ->label('Promotional Materials')
                            ->collection('promotional_materials')
                            ->multiple()
                            ->maxFiles(10)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes([
                                'image/jpeg', 'image/png', 'image/webp',
                                'application/pdf'
                            ])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),

                        // Press kit documents
                        SpatieMediaLibraryFileUpload::make('press_kit')
                            ->label('Press Kit Documents')
                            ->collection('press_kit')
                            ->multiple()
                            ->maxFiles(5)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes(['application/pdf', 'text/plain'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
````
</augment_code_snippet>

### 1.4.2. Album Resource Media Forms

> **Album Media Integration:** Specialized forms for album artwork and promotional content

<augment_code_snippet path="app/Filament/Admin/Resources/ChinookAlbumResource.php" mode="EXCERPT">
````php
<?php

namespace App\Filament\Admin\Resources;

use App\Models\ChinookAlbum;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ChinookAlbumResource extends Resource
{
    protected static ?string $model = ChinookAlbum::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Album Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('artist_id')
                            ->relationship('artist', 'name')
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Album Media')
                    ->schema([
                        // Album cover art
                        SpatieMediaLibraryFileUpload::make('cover_art')
                            ->label('Album Cover Art')
                            ->collection('cover_art')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1']) // Square album covers
                            ->maxSize(5120) // 5MB
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable(false) // Single cover art
                            ->columnSpanFull()
                            ->helperText('Upload high-quality album cover art (minimum 600x600px recommended)'),

                        // Digital booklet
                        SpatieMediaLibraryFileUpload::make('digital_booklet')
                            ->label('Digital Booklet')
                            ->collection('digital_booklet')
                            ->multiple()
                            ->maxFiles(10)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),

                        // Promotional images
                        SpatieMediaLibraryFileUpload::make('promotional_images')
                            ->label('Promotional Images')
                            ->collection('promotional_images')
                            ->image()
                            ->multiple()
                            ->maxFiles(15)
                            ->maxSize(3072) // 3MB per file
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
````
</augment_code_snippet>

### 1.4.3. Track Resource Media Forms

> **Track Media Integration:** Audio file management with metadata preservation

<augment_code_snippet path="app/Filament/Admin/Resources/ChinookTrackResource.php" mode="EXCERPT">
````php
<?php

namespace App\Filament\Admin\Resources;

use App\Models\ChinookTrack;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ChinookTrackResource extends Resource
{
    protected static ?string $model = ChinookTrack::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Track Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('album_id')
                            ->relationship('album', 'title')
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Audio Files')
                    ->schema([
                        // Primary audio file
                        SpatieMediaLibraryFileUpload::make('audio_files')
                            ->label('Primary Audio File')
                            ->collection('audio_files')
                            ->maxSize(51200) // 50MB for high-quality audio
                            ->acceptedFileTypes([
                                'audio/mpeg', 'audio/flac', 'audio/wav', 'audio/ogg'
                            ])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable(false) // Single primary file
                            ->columnSpanFull()
                            ->helperText('Upload high-quality audio file (FLAC, WAV, or high-bitrate MP3)'),

                        // Preview clips
                        SpatieMediaLibraryFileUpload::make('preview_clips')
                            ->label('Preview Clips')
                            ->collection('preview_clips')
                            ->multiple()
                            ->maxFiles(3)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes(['audio/mpeg', 'audio/wav'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),

                        // Waveform images
                        SpatieMediaLibraryFileUpload::make('waveforms')
                            ->label('Waveform Visualization')
                            ->collection('waveforms')
                            ->image()
                            ->maxSize(2048) // 2MB
                            ->acceptedFileTypes(['image/png', 'image/svg+xml'])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Sheet Music & Lyrics')
                    ->schema([
                        // Sheet music and lyric sheets
                        SpatieMediaLibraryFileUpload::make('sheet_music')
                            ->label('Sheet Music & Lyrics')
                            ->collection('sheet_music')
                            ->multiple()
                            ->maxFiles(5)
                            ->maxSize(10240) // 10MB
                            ->acceptedFileTypes([
                                'application/pdf', 'image/jpeg', 'image/png'
                            ])
                            ->downloadable()
                            ->openable()
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Filament Curator Guide](230-awcodes-filament-curator-guide.md) | **Next:** [Filament Shield Guide](240-bezhansalleh-filament-shield-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
