---
owner: "[FRONTEND_LEAD]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft"
version: "1.0.0"
target_audience: "Junior developers with 6 months-2 years experience"
framework_version: "Laravel 12.x"
filament_version: "FilamentPHP v4"
---

# FilamentPHP v4 Integration Guide
## [PROJECT_NAME]

**Estimated Reading Time:** 25 minutes

## Overview

This guide provides comprehensive implementation details for integrating FilamentPHP v4 with Laravel 12.x for [PROJECT_NAME]. It covers panel configuration, resource development, permission integration, and performance optimization for admin interfaces.

### FilamentPHP v4 Key Features
- **Modern UI**: Built on Tailwind CSS with responsive design
- **Form Builder**: Powerful form creation with validation
- **Table Builder**: Advanced data tables with filtering and sorting
- **Panel System**: Multi-panel architecture for different user types
- **Plugin Architecture**: Extensible through custom plugins
- **Laravel Integration**: Native Laravel integration with minimal configuration

## Panel Configuration

### Admin Panel Provider Setup

```php
<?php
// app/Providers/Filament/AdminPanelProvider.php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Resources\UserResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // Custom pages
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                StatsOverview::class,
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                'web',
                'auth',
                'verified',
            ])
            ->authMiddleware([
                'auth',
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->favicon(asset('images/favicon.png'))
            ->brandName('[PROJECT_NAME] Admin')
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2rem')
            ->navigationGroups([
                'User Management',
                'Content Management',
                'System Administration',
                'Reports & Analytics',
            ]);
    }
}
```

### Multi-Panel Configuration

```php
<?php
// app/Providers/Filament/UserPanelProvider.php

class UserPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('dashboard')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/User/Resources'), for: 'App\\Filament\\User\\Resources')
            ->discoverPages(in: app_path('Filament/User/Pages'), for: 'App\\Filament\\User\\Pages')
            ->discoverWidgets(in: app_path('Filament/User/Widgets'), for: 'App\\Filament\\User\\Widgets')
            ->middleware([
                'web',
                'auth',
                'verified',
            ])
            ->authMiddleware([
                'auth',
            ])
            ->brandName('[PROJECT_NAME] Dashboard');
    }
}
```

## Resource Development

### User Resource with Permission Integration

```php
<?php
// app/Filament/Resources/UserResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Enums\UserStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    // Permission-based access control
    public static function canViewAny(): bool
    {
        return auth()->user()->hasPermissionTo('view-users');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->hasPermissionTo('create-users');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasPermissionTo('edit-users');
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasPermissionTo('delete-users');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->displayFormat('Y-m-d H:i:s'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Account Settings')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(UserStatus::class)
                            ->required()
                            ->default(UserStatus::Active),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Roles & Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->columns(2)
                            ->searchable(),
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->columns(3)
                            ->searchable(),
                    ])
                    ->visible(fn () => auth()->user()->hasPermissionTo('assign-roles')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->separator(',')
                    ->limitList(3),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(UserStatus::class),
                Tables\Filters\Filter::make('verified')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at')),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Define relations here
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
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

### Custom Resource Pages

```php
<?php
// app/Filament/Resources/UserResource/Pages/ListUsers.php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export')
                ->label('Export Users')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    return $this->exportUsers();
                })
                ->visible(fn () => auth()->user()->hasPermissionTo('export-users')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Users'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'inactive')),
            'unverified' => Tab::make('Unverified')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('email_verified_at')),
        ];
    }

    protected function exportUsers()
    {
        // Implementation for user export
        return response()->streamDownload(function () {
            $users = $this->getFilteredTableQuery()->get();
            echo $users->toCsv();
        }, 'users-export.csv');
    }
}
```

## Widget Development

### Statistics Overview Widget

```php
<?php
// app/Filament/Widgets/StatsOverview.php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
            
            Stat::make('Active Users', User::where('status', 'active')->count())
                ->description('Currently active users')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary'),
            
            Stat::make('New Users This Month', User::whereMonth('created_at', now()->month)->count())
                ->description('Users registered this month')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning'),
            
            Stat::make('Verified Users', User::whereNotNull('email_verified_at')->count())
                ->description('Email verified users')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
```

### Chart Widget

```php
<?php
// app/Filament/Widgets/UserRegistrationChart.php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class UserRegistrationChart extends ChartWidget
{
    protected static ?string $heading = 'User Registrations';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Trend::model(User::class)
            ->between(
                start: now()->subMonths(12),
                end: now(),
            )
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'User Registrations',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

## Custom Pages

### Dashboard Page

```php
<?php
// app/Filament/Pages/Dashboard.php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.dashboard';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\UserRegistrationChart::class,
            \App\Filament\Widgets\RecentActivity::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
        ];
    }
}
```

### Settings Page

```php
<?php
// app/Filament/Pages/Settings.php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.settings';
    protected static ?string $navigationGroup = 'System Administration';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'app_name' => config('app.name'),
            'app_url' => config('app.url'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Application Settings')
                    ->schema([
                        Forms\Components\TextInput::make('app_name')
                            ->label('Application Name')
                            ->required(),
                        Forms\Components\TextInput::make('app_url')
                            ->label('Application URL')
                            ->url()
                            ->required(),
                    ]),
                
                Forms\Components\Section::make('Email Settings')
                    ->schema([
                        Forms\Components\TextInput::make('mail_from_address')
                            ->label('From Email Address')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('mail_from_name')
                            ->label('From Name')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save settings to database or config files
        // Implementation depends on your settings storage strategy

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasPermissionTo('manage-settings');
    }
}
```

## Plugin Development

### Custom Plugin Structure

```php
<?php
// app/Filament/Plugins/AuditLogPlugin.php

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use App\Filament\Resources\AuditLogResource;

class AuditLogPlugin implements Plugin
{
    public function getId(): string
    {
        return 'audit-log';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                AuditLogResource::class,
            ])
            ->pages([
                // Custom pages for audit log
            ]);
    }

    public function boot(Panel $panel): void
    {
        // Boot logic here
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
```

## Performance Optimization

### Resource Query Optimization

```php
<?php
// Optimized resource queries

class UserResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'permissions']) // Eager load relationships
            ->withCount(['posts', 'comments']) // Load counts efficiently
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Optimized columns
            ])
            ->defaultPaginationPageOption(25) // Reasonable page size
            ->deferLoading() // Defer loading for better performance
            ->persistFiltersInSession() // Cache filters
            ->persistSortInSession(); // Cache sorting
    }
}
```

### Caching Strategies

```php
<?php
// app/Filament/Widgets/CachedStatsWidget.php

class CachedStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return Cache::remember('admin.stats', 300, function () {
            return [
                Stat::make('Total Users', User::count()),
                Stat::make('Active Users', User::where('status', 'active')->count()),
                // Other stats...
            ];
        });
    }
}
```

## Security Implementation

### Role-Based Navigation

```php
<?php
// app/Filament/Resources/UserResource.php

class UserResource extends Resource
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasPermissionTo('view-users');
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()->hasRole('super-admin') 
            ? 'System Administration' 
            : 'User Management';
    }
}
```

### Secure File Uploads

```php
<?php
// Secure file upload configuration

Forms\Components\FileUpload::make('avatar')
    ->image()
    ->disk('private') // Use private disk
    ->directory('avatars')
    ->maxSize(2048) // 2MB max
    ->acceptedFileTypes(['image/jpeg', 'image/png'])
    ->imageResizeMode('cover')
    ->imageCropAspectRatio('1:1')
    ->imageResizeTargetWidth('300')
    ->imageResizeTargetHeight('300')
    ->visibility('private');
```

## Testing FilamentPHP Components

### Resource Testing

```php
<?php
// tests/Feature/Filament/UserResourceTest.php

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_view_user_list()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('view-users');

        $this->actingAs($admin)
            ->get(UserResource::getUrl('index'))
            ->assertOk();
    }

    /** @test */
    public function admin_can_create_user()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('create-users');

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'status' => 'active',
        ];

        $this->actingAs($admin)
            ->post(UserResource::getUrl('create'), $userData)
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }
}
```

---

**FilamentPHP Integration Guide Version**: 1.0.0  
**FilamentPHP Version**: v4  
**Laravel Version**: 12.x  
**Created**: [YYYY-MM-DD]  
**Last Updated**: [YYYY-MM-DD]  
**Next Review**: [YYYY-MM-DD]  
**Integration Owner**: [FRONTEND_LEAD]
