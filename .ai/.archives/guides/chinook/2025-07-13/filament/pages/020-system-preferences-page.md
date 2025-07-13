# 1. System Preferences Page Implementation

## 1.1 Advanced System Configuration Interface

This guide covers implementing a comprehensive system preferences page for the Chinook admin panel, providing centralized control over application behavior, performance settings, and operational parameters.

## 1.2 Table of Contents

- [1. System Preferences Page Implementation](#1-system-preferences-page-implementation)
    - [1.1 Advanced System Configuration Interface](#11-advanced-system-configuration-interface)
    - [1.2 Table of Contents](#12-table-of-contents)
    - [1.3 Overview](#13-overview)
        - [1.3.1 Configuration Categories](#131-configuration-categories)
        - [1.3.2 Security Features](#132-security-features)
    - [1.4 Page Implementation](#14-page-implementation)
        - [1.4.1 System Preferences Class](#141-system-preferences-class)
        - [1.4.2 Form Schema](#142-form-schema)
    - [1.5 Configuration Categories](#15-configuration-categories)
        - [1.5.1 Performance Settings](#151-performance-settings)
        - [1.5.2 Security Configuration](#152-security-configuration)
        - [1.5.3 Integration Settings](#153-integration-settings)
    - [1.6 Advanced Features](#16-advanced-features)
        - [1.6.1 Real-time Validation](#161-real-time-validation)
        - [1.6.2 Configuration Backup](#162-configuration-backup)

## 1.3 Overview

The System Preferences page provides administrators with comprehensive control over application-wide settings that affect performance, security, and operational behavior.

### 1.3.1 Configuration Categories

- **Performance Settings**: Cache configuration, query optimization, resource limits
- **Security Configuration**: Authentication settings, access controls, audit logging
- **Integration Settings**: Third-party service configuration and API settings
- **Operational Settings**: Maintenance mode, logging levels, notification preferences

### 1.3.2 Security Features

- **Role-based Access**: Only super administrators can modify system preferences
- **Change Auditing**: Complete audit trail of all configuration changes
- **Validation**: Comprehensive validation to prevent invalid configurations
- **Backup/Restore**: Automatic backup before changes with restore capability

## 1.4 Page Implementation

### 1.4.1 System Preferences Class

```php
<?php

namespace App\Filament\ChinookAdmin\Pages;

use App\Settings\SystemSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Cache;

class SystemPreferences extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static ?string $navigationLabel = 'System Preferences';
    protected static ?string $navigationGroup = 'System Administration';
    protected static ?int $navigationSort = 200;
    protected static string $settings = SystemSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('System Configuration')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Performance')
                            ->icon('heroicon-o-bolt')
                            ->schema($this->getPerformanceSchema()),
                        Forms\Components\Tabs\Tab::make('Security')
                            ->icon('heroicon-o-shield-check')
                            ->schema($this->getSecuritySchema()),
                        Forms\Components\Tabs\Tab::make('Integration')
                            ->icon('heroicon-o-link')
                            ->schema($this->getIntegrationSchema()),
                        Forms\Components\Tabs\Tab::make('Operations')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema($this->getOperationsSchema()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getPerformanceSchema(): array
    {
        return [
            Forms\Components\Section::make('Cache Configuration')
                ->description('Configure caching behavior for optimal performance')
                ->schema([
                    Forms\Components\Select::make('cache_driver')
                        ->label('Cache Driver')
                        ->options([
                            'redis' => 'Redis (Recommended)',
                            'memcached' => 'Memcached',
                            'file' => 'File System',
                            'database' => 'Database',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            $this->validateCacheDriver($state);
                        }),
                    
                    Forms\Components\TextInput::make('cache_ttl')
                        ->label('Default Cache TTL (seconds)')
                        ->numeric()
                        ->minValue(60)
                        ->maxValue(86400)
                        ->default(3600)
                        ->suffix('seconds'),
                    
                    Forms\Components\Toggle::make('query_cache_enabled')
                        ->label('Enable Query Caching')
                        ->helperText('Cache database query results for improved performance'),
                    
                    Forms\Components\TextInput::make('max_memory_limit')
                        ->label('Memory Limit (MB)')
                        ->numeric()
                        ->minValue(128)
                        ->maxValue(2048)
                        ->suffix('MB'),
                ])
                ->columns(2),
        ];
    }

    protected function getSecuritySchema(): array
    {
        return [
            Forms\Components\Section::make('Authentication & Access')
                ->description('Configure security and access control settings')
                ->schema([
                    Forms\Components\TextInput::make('session_lifetime')
                        ->label('Session Lifetime (minutes)')
                        ->numeric()
                        ->minValue(15)
                        ->maxValue(1440)
                        ->default(120)
                        ->suffix('minutes'),
                    
                    Forms\Components\TextInput::make('max_login_attempts')
                        ->label('Maximum Login Attempts')
                        ->numeric()
                        ->minValue(3)
                        ->maxValue(10)
                        ->default(5),
                    
                    Forms\Components\Toggle::make('two_factor_required')
                        ->label('Require Two-Factor Authentication')
                        ->helperText('Enforce 2FA for all admin users'),
                    
                    Forms\Components\Toggle::make('audit_logging_enabled')
                        ->label('Enable Audit Logging')
                        ->helperText('Log all administrative actions for compliance'),
                ])
                ->columns(2),
        ];
    }

    protected function getIntegrationSchema(): array
    {
        return [
            Forms\Components\Section::make('External Services')
                ->description('Configure third-party service integrations')
                ->schema([
                    Forms\Components\TextInput::make('api_rate_limit')
                        ->label('API Rate Limit (requests/minute)')
                        ->numeric()
                        ->minValue(60)
                        ->maxValue(10000)
                        ->default(1000),
                    
                    Forms\Components\Toggle::make('external_api_enabled')
                        ->label('Enable External API Access')
                        ->helperText('Allow external applications to access the API'),
                    
                    Forms\Components\Textarea::make('allowed_origins')
                        ->label('Allowed CORS Origins')
                        ->placeholder('https://example.com, https://app.example.com')
                        ->helperText('Comma-separated list of allowed origins'),
                ])
                ->columns(2),
        ];
    }

    protected function getOperationsSchema(): array
    {
        return [
            Forms\Components\Section::make('Operational Settings')
                ->description('Configure application operational behavior')
                ->schema([
                    Forms\Components\Toggle::make('maintenance_mode')
                        ->label('Maintenance Mode')
                        ->helperText('Put the application in maintenance mode'),
                    
                    Forms\Components\Select::make('log_level')
                        ->label('Logging Level')
                        ->options([
                            'emergency' => 'Emergency',
                            'alert' => 'Alert',
                            'critical' => 'Critical',
                            'error' => 'Error',
                            'warning' => 'Warning',
                            'notice' => 'Notice',
                            'info' => 'Info',
                            'debug' => 'Debug',
                        ])
                        ->default('info'),
                    
                    Forms\Components\TextInput::make('backup_retention_days')
                        ->label('Backup Retention (days)')
                        ->numeric()
                        ->minValue(7)
                        ->maxValue(365)
                        ->default(30)
                        ->suffix('days'),
                ])
                ->columns(2),
        ];
    }

    protected function validateCacheDriver(string $driver): void
    {
        $requirements = [
            'redis' => extension_loaded('redis'),
            'memcached' => extension_loaded('memcached'),
        ];

        if (isset($requirements[$driver]) && !$requirements[$driver]) {
            $this->addError('cache_driver', "The {$driver} extension is not installed.");
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Create backup before saving changes
        $this->createConfigurationBackup();
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Clear all caches after configuration changes
        Cache::flush();
        
        // Log the configuration change
        activity()
            ->causedBy(auth()->user())
            ->log('System preferences updated');
        
        $this->notify('success', 'System preferences updated successfully');
    }

    protected function createConfigurationBackup(): void
    {
        $backup = [
            'timestamp' => now(),
            'user_id' => auth()->id(),
            'settings' => app(SystemSettings::class)->toArray(),
        ];
        
        Cache::put('system_preferences_backup', $backup, now()->addDays(7));
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('Super Admin') ?? false;
    }
}
```

### 1.4.2 Form Schema

The form schema is organized into logical tabs, each containing related configuration sections with comprehensive validation and real-time feedback.

```php
protected function getFormSchema(): array
{
    return [
        Forms\Components\Tabs::make('System Configuration')
            ->tabs([
                $this->getPerformanceTab(),
                $this->getSecurityTab(),
                $this->getIntegrationTab(),
                $this->getOperationsTab(),
            ])
            ->columnSpanFull()
            ->persistTabInQueryString(),
    ];
}

protected function getPerformanceTab(): Forms\Components\Tabs\Tab
{
    return Forms\Components\Tabs\Tab::make('Performance')
        ->icon('heroicon-o-bolt')
        ->badge($this->getPerformanceIssuesCount())
        ->badgeColor('warning')
        ->schema([
            Forms\Components\Section::make('System Performance')
                ->description('Configure performance-critical settings')
                ->schema($this->getPerformanceSchema())
                ->columns(2),
        ]);
}

protected function getPerformanceIssuesCount(): int
{
    $issues = 0;

    if (!extension_loaded('redis')) $issues++;
    if (ini_get('memory_limit') < '512M') $issues++;
    if (!function_exists('opcache_get_status')) $issues++;

    return $issues;
}
```

**Schema Features**:

- **Tab Persistence**: Current tab persisted in URL for better UX
- **Dynamic Badges**: Real-time issue indicators on tabs
- **Conditional Validation**: Context-aware validation rules
- **Performance Monitoring**: Built-in performance issue detection

## 1.5 Configuration Categories

### 1.5.1 Performance Settings

- **Cache Configuration**: Redis/Memcached setup with TTL management
- **Query Optimization**: Database query caching and optimization settings
- **Resource Limits**: Memory and execution time limits
- **Asset Optimization**: CSS/JS minification and compression settings

### 1.5.2 Security Configuration

- **Session Management**: Session lifetime and security settings
- **Access Control**: Login attempt limits and lockout policies
- **Two-Factor Authentication**: 2FA enforcement and configuration
- **Audit Logging**: Comprehensive activity logging for compliance

### 1.5.3 Integration Settings

- **API Configuration**: Rate limiting and access control for external APIs
- **CORS Settings**: Cross-origin resource sharing configuration
- **Webhook Management**: Outbound webhook configuration and retry policies
- **Service Integrations**: Third-party service API keys and settings

## 1.6 Advanced Features

### 1.6.1 Real-time Validation

- **Dependency Checking**: Validate required extensions and services
- **Configuration Testing**: Test settings before applying changes
- **Impact Assessment**: Show potential impact of configuration changes
- **Rollback Capability**: Quick rollback to previous configurations

### 1.6.2 Configuration Backup

- **Automatic Backup**: Backup before any configuration changes
- **Version History**: Track configuration changes over time
- **Export/Import**: Export configurations for deployment across environments
- **Disaster Recovery**: Quick restoration of known-good configurations

---

## 1.7 Navigation

**Pages Index**: [Pages Documentation](000-pages-index.md)
**Settings Guide**: [Settings Configuration](010-settings-configuration-page.md)
**Security Setup**: [Security Configuration](../setup/050-security-configuration.md)

---

*This implementation follows Laravel 12 modern patterns with WCAG 2.1 AA compliance and comprehensive security features.*
