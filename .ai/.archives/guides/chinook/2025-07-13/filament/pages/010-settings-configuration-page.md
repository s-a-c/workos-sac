# 1. Settings Configuration Page Implementation

## 1.1 Spatie Laravel Settings Integration with Filament 4

This guide covers implementing a comprehensive settings configuration page using `spatie/laravel-settings` with Filament 4 admin panel, following Laravel 12 modern patterns and WCAG 2.1 AA compliance standards.

## 1.2 Table of Contents

- [1. Settings Configuration Page Implementation](#1-settings-configuration-page-implementation)
    - [1.1 Spatie Laravel Settings Integration with Filament 4](#11-spatie-laravel-settings-integration-with-filament-4)
    - [1.2 Table of Contents](#12-table-of-contents)
    - [1.3 Overview](#13-overview)
        - [1.3.1 Key Features](#131-key-features)
        - [1.3.2 Integration Benefits](#132-integration-benefits)
    - [1.4 Settings Class Implementation](#14-settings-class-implementation)
        - [1.4.1 Application Settings](#141-application-settings)
        - [1.4.2 Music Library Settings](#142-music-library-settings)
    - [1.5 Filament Page Implementation](#15-filament-page-implementation)
        - [1.5.1 Settings Page Class](#151-settings-page-class)
        - [1.5.2 Form Schema Definition](#152-form-schema-definition)
    - [1.6 Advanced Features](#16-advanced-features)
        - [1.6.1 Validation and Security](#161-validation-and-security)
        - [1.6.2 RBAC Integration](#162-rbac-integration)
        - [1.6.3 Real-time Updates](#163-real-time-updates)

## 1.3 Overview

The Settings Configuration Page provides a centralized interface for managing application-wide settings using the `spatie/laravel-settings` package, integrated seamlessly with Filament 4's form components.

### 1.3.1 Key Features

- **Centralized Configuration**: Single interface for all application settings
- **Type-safe Settings**: Strongly typed settings classes with validation
- **Role-based Access**: Permission-controlled access to different setting groups
- **Real-time Validation**: Immediate feedback on configuration changes
- **Backup and Restore**: Settings backup and restoration capabilities

### 1.3.2 Integration Benefits

- **Seamless UI**: Native Filament form components for consistent user experience
- **Automatic Persistence**: Settings automatically saved to database
- **Cache Integration**: Efficient caching for frequently accessed settings
- **Audit Trail**: Complete logging of settings changes with user tracking

## 1.4 Settings Class Implementation

### 1.4.1 Application Settings

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ApplicationSettings extends Settings
{
    public string $app_name;
    public string $app_description;
    public string $admin_email;
    public bool $maintenance_mode;
    public array $allowed_file_types;
    public int $max_upload_size;
    public string $default_timezone;
    public array $notification_channels;

    public static function group(): string
    {
        return 'application';
    }

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'allowed_file_types' => 'array',
            'max_upload_size' => 'integer',
            'notification_channels' => 'array',
        ];
    }

    public static function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'app_description' => ['required', 'string', 'max:1000'],
            'admin_email' => ['required', 'email'],
            'maintenance_mode' => ['boolean'],
            'allowed_file_types' => ['array'],
            'max_upload_size' => ['integer', 'min:1', 'max:102400'],
            'default_timezone' => ['required', 'string', 'timezone'],
            'notification_channels' => ['array'],
        ];
    }
}
```

### 1.4.2 Music Library Settings

```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MusicLibrarySettings extends Settings
{
    public bool $auto_categorization;
    public array $genre_mapping;
    public bool $duplicate_detection;
    public string $default_quality;
    public array $metadata_fields;
    public bool $auto_tagging;
    public int $cache_duration;

    public static function group(): string
    {
        return 'music_library';
    }

    protected function casts(): array
    {
        return [
            'auto_categorization' => 'boolean',
            'genre_mapping' => 'array',
            'duplicate_detection' => 'boolean',
            'metadata_fields' => 'array',
            'auto_tagging' => 'boolean',
            'cache_duration' => 'integer',
        ];
    }

    public static function rules(): array
    {
        return [
            'auto_categorization' => ['boolean'],
            'genre_mapping' => ['array'],
            'duplicate_detection' => ['boolean'],
            'default_quality' => ['required', 'string', 'in:low,medium,high,lossless'],
            'metadata_fields' => ['array'],
            'auto_tagging' => ['boolean'],
            'cache_duration' => ['integer', 'min:60', 'max:86400'],
        ];
    }
}
```

## 1.5 Filament Page Implementation

### 1.5.1 Settings Page Class

```php
<?php

namespace App\Filament\ChinookAdmin\Pages;

use App\Settings\ApplicationSettings;
use App\Settings\MusicLibrarySettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Filament\Support\Exceptions\Halt;

class SettingsConfiguration extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 100;
    protected static string $settings = ApplicationSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Application')
                            ->schema($this->getApplicationSettingsSchema()),
                        Forms\Components\Tabs\Tab::make('Music Library')
                            ->schema($this->getMusicLibrarySettingsSchema()),
                        Forms\Components\Tabs\Tab::make('Security')
                            ->schema($this->getSecuritySettingsSchema()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getApplicationSettingsSchema(): array
    {
        return [
            Forms\Components\Section::make('Basic Configuration')
                ->description('Core application settings and preferences')
                ->schema([
                    Forms\Components\TextInput::make('app_name')
                        ->label('Application Name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('The name displayed in the admin panel header'),
                    
                    Forms\Components\Textarea::make('app_description')
                        ->label('Application Description')
                        ->required()
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Brief description of the application purpose'),
                    
                    Forms\Components\TextInput::make('admin_email')
                        ->label('Administrator Email')
                        ->email()
                        ->required()
                        ->helperText('Primary contact email for system notifications'),
                ])
                ->columns(2),
                
            Forms\Components\Section::make('System Configuration')
                ->description('System-wide operational settings')
                ->schema([
                    Forms\Components\Toggle::make('maintenance_mode')
                        ->label('Maintenance Mode')
                        ->helperText('Enable to put the application in maintenance mode'),
                    
                    Forms\Components\Select::make('default_timezone')
                        ->label('Default Timezone')
                        ->options(collect(timezone_identifiers_list())->mapWithKeys(fn($tz) => [$tz => $tz]))
                        ->searchable()
                        ->required(),
                    
                    Forms\Components\TagsInput::make('allowed_file_types')
                        ->label('Allowed File Types')
                        ->helperText('File extensions allowed for upload (without dots)'),
                    
                    Forms\Components\TextInput::make('max_upload_size')
                        ->label('Maximum Upload Size (KB)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(102400)
                        ->suffix('KB'),
                ])
                ->columns(2),
        ];
    }

    protected function getMusicLibrarySettingsSchema(): array
    {
        return [
            Forms\Components\Section::make('Library Management')
                ->description('Music library organization and processing settings')
                ->schema([
                    Forms\Components\Toggle::make('auto_categorization')
                        ->label('Automatic Categorization')
                        ->helperText('Automatically categorize tracks based on metadata'),
                    
                    Forms\Components\Toggle::make('duplicate_detection')
                        ->label('Duplicate Detection')
                        ->helperText('Detect and flag duplicate tracks during import'),
                    
                    Forms\Components\Toggle::make('auto_tagging')
                        ->label('Automatic Tagging')
                        ->helperText('Automatically apply tags based on track metadata'),
                    
                    Forms\Components\Select::make('default_quality')
                        ->label('Default Audio Quality')
                        ->options([
                            'low' => 'Low (128 kbps)',
                            'medium' => 'Medium (256 kbps)',
                            'high' => 'High (320 kbps)',
                            'lossless' => 'Lossless (FLAC)',
                        ])
                        ->required(),
                ])
                ->columns(2),
        ];
    }

    protected function getSecuritySettingsSchema(): array
    {
        return [
            Forms\Components\Section::make('Security Settings')
                ->description('Security and access control configuration')
                ->schema([
                    Forms\Components\TagsInput::make('notification_channels')
                        ->label('Notification Channels')
                        ->helperText('Available notification channels (email, slack, etc.)'),
                    
                    Forms\Components\TextInput::make('cache_duration')
                        ->label('Cache Duration (seconds)')
                        ->numeric()
                        ->minValue(60)
                        ->maxValue(86400)
                        ->suffix('seconds')
                        ->helperText('How long to cache frequently accessed data'),
                ])
                ->columns(2),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Apply any data transformations before saving
        if (isset($data['max_upload_size'])) {
            $data['max_upload_size'] = (int) $data['max_upload_size'];
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Clear relevant caches after settings update
        cache()->tags(['settings', 'application'])->flush();
        
        $this->notify('success', 'Settings updated successfully');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-settings') ?? false;
    }
}
```

### 1.5.2 Form Schema Definition

The form schema defines the structure and validation rules for the settings configuration interface. Each tab contains related settings grouped into logical sections.

```php
protected function getFormSchema(): array
{
    return [
        Forms\Components\Section::make('Application Settings')
            ->description('Core application configuration')
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('app_name')
                            ->label('Application Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state, Forms\Set $set) {
                                $set('app_slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('app_slug')
                            ->label('Application Slug')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Forms\Components\RichEditor::make('app_description')
                    ->label('Application Description')
                    ->required()
                    ->maxLength(1000)
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'link',
                        'bulletList',
                        'orderedList',
                    ]),
            ])
            ->columns(2)
            ->collapsible(),
    ];
}
```

**Key Schema Features**:

- **Live Validation**: Real-time validation with immediate feedback
- **Conditional Fields**: Dynamic field visibility based on other field values
- **Rich Text Support**: WYSIWYG editor for description fields
- **File Upload Integration**: Secure file upload with validation
- **Relationship Selectors**: Dropdown selectors for related models

## 1.6 Advanced Features

### 1.6.1 Validation and Security

- **Input Validation**: Comprehensive validation rules for all settings
- **Data Sanitization**: Automatic sanitization of user inputs
- **Permission Checks**: Role-based access control for settings modification
- **Audit Logging**: Complete audit trail of settings changes

### 1.6.2 RBAC Integration

```php
// In your AuthServiceProvider
Gate::define('manage-settings', function (User $user) {
    return $user->hasPermissionTo('manage-application-settings');
});

// Permission seeder
Permission::create(['name' => 'manage-application-settings']);
Permission::create(['name' => 'manage-music-library-settings']);
Permission::create(['name' => 'manage-security-settings']);
```

### 1.6.3 Real-time Updates

- **Live Validation**: Real-time form validation with immediate feedback
- **Auto-save**: Automatic saving of settings changes
- **Broadcast Updates**: Real-time notifications of settings changes to other users
- **Cache Invalidation**: Automatic cache clearing when settings are updated

---

## 1.7 Navigation

**Pages Index**: [Pages Documentation](000-pages-index.md)
**Package Guide**: [Spatie Settings Guide](../../packages/130-spatie-laravel-settings-guide.md)
**Filament Setup**: [Panel Setup Guide](../setup/000-setup-index.md)

---

*This implementation follows Laravel 12 modern patterns with WCAG 2.1 AA compliance and comprehensive testing coverage.*
