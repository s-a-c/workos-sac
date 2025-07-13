# 9. FilamentPHP v4 Integration

## 9.1. FilamentPHP v4 Key Changes

**Important Changes from v3 to v4:**
- `reactive()` is replaced with `live()` for real-time form updates
- `BadgeColumn` is replaced with `TextColumn::badge()` method
- Enhanced section collapsing with `collapsed()` method
- Improved color and icon handling with callback functions
- Better performance with optimized rendering

## 9.2. STI-Aware Resource Configuration

### 9.2.1. Base User Resource

```php
<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('role')
                            ->options(UserRole::getFilamentOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, callable $set) =>
                                static::updateFormBasedOnRole($state, $set)
                            ),
                        
                        Forms\Components\Select::make('status')
                            ->options(UserStatus::getFilamentOptions())
                            ->required(),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Profile Information')
                    ->schema([
                        Forms\Components\TextInput::make('profile_data.first_name')
                            ->label('First Name'),
                        
                        Forms\Components\TextInput::make('profile_data.last_name')
                            ->label('Last Name'),
                        
                        Forms\Components\TextInput::make('profile_data.phone')
                            ->label('Phone Number')
                            ->tel(),
                        
                        Forms\Components\Textarea::make('profile_data.bio')
                            ->label('Biography')
                            ->rows(3),
                        
                        Forms\Components\Select::make('profile_data.timezone')
                            ->label('Timezone')
                            ->options(collect(timezone_identifiers_list())
                                ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                ->toArray())
                            ->searchable(),
                    ])
                    ->columns(2),

                // Admin-specific fields
                Forms\Components\Section::make('Admin Settings')
                    ->schema([
                        Forms\Components\TextInput::make('admin_level')
                            ->label('Admin Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->default(1),
                        
                        Forms\Components\TextInput::make('department')
                            ->label('Department'),
                        
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permissions')
                            ->options([
                                'manage_users' => 'Manage Users',
                                'view_analytics' => 'View Analytics',
                                'manage_settings' => 'Manage Settings',
                                'manage_system' => 'Manage System',
                            ])
                            ->columns(2),
                    ])
                    ->visible(fn (Forms\Get $get) =>
                        in_array($get('role'), [UserRole::Admin->value, UserRole::SuperAdmin->value])
                    )
                    ->collapsed(),

                // Guest-specific fields
                Forms\Components\Section::make('Guest Settings')
                    ->schema([
                        Forms\Components\TextInput::make('session_id')
                            ->label('Session ID')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At'),
                        
                        Forms\Components\Textarea::make('conversion_data')
                            ->label('Conversion Data')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->dehydrateStateUsing(fn ($state) => json_decode($state, true)),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('role') === UserRole::Guest->value)
                    ->collapsed(),
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
                
                Tables\Columns\TextColumn::make('role')
                    ->formatStateUsing(fn (UserRole $state) => $state->getLabel())
                    ->badge()
                    ->color(fn (UserRole $state) => $state->getColor())
                    ->icon(fn (UserRole $state) => $state->getIcon()),
                
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (UserStatus $state) => $state->getLabel())
                    ->badge()
                    ->color(fn (UserStatus $state) => $state->getColor())
                    ->icon(fn (UserStatus $state) => $state->getIcon()),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                
                Tables\Columns\TextColumn::make('type')
                    ->label('User Type')
                    ->formatStateUsing(fn (string $state) => str($state)->title()->replace('_', ' '))
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(UserRole::getFilamentOptions()),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options(UserStatus::getFilamentOptions()),
                
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'standard_user' => 'Standard User',
                        'admin' => 'Admin',
                        'guest' => 'Guest',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                
                Tables\Filters\Filter::make('verified')
                    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at'))
                    ->label('Email Verified'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('impersonate')
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->visible(fn (User $record) => 
                        auth()->user()->hasRole(UserRole::SuperAdmin) && 
                        $record->role !== UserRole::SuperAdmin
                    )
                    ->action(fn (User $record) => 
                        redirect()->route('impersonate', $record->ulid)
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => 
                            $records->each->update(['is_active' => true])
                        ),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => 
                            $records->each->update(['is_active' => false])
                        ),
                ]),
            ]);
    }

    protected static function updateFormBasedOnRole(string $role, callable $set): void
    {
        // Update form fields based on selected role
        match ($role) {
            UserRole::Admin->value, UserRole::SuperAdmin->value => $set('type', 'admin'),
            UserRole::Guest->value => $set('type', 'guest'),
            default => $set('type', 'standard_user'),
        };
    }

    public static function getRelations(): array
    {
        return [
            // Add relations here if needed
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
            ->withoutGlobalScopes();
    }
}
```

## 9.3. Specialized Resources for User Types

### 9.3.1. Admin Resource

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Models\Admin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?string $navigationLabel = 'Administrators';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Admin Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        
                        Forms\Components\TextInput::make('admin_level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->required(),
                        
                        Forms\Components\TextInput::make('department'),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ]),

                Forms\Components\Section::make('Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->options([
                                'manage_users' => 'Manage Users',
                                'view_analytics' => 'View Analytics',
                                'manage_settings' => 'Manage Settings',
                                'manage_system' => 'Manage System',
                                'ban_users' => 'Ban Users',
                                'delete_users' => 'Delete Users',
                            ])
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
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
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('admin_level')
                    ->badge()
                    ->colors([
                        'gray' => 1,
                        'warning' => 2,
                        'primary' => 3,
                        'success' => 4,
                        'danger' => 5,
                    ]),
                
                Tables\Columns\TextColumn::make('department')
                    ->toggleable(),
                
                Tables\Columns\TagsColumn::make('permissions')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                
                Tables\Columns\TextColumn::make('last_login_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('admin_level')
                    ->options([
                        1 => 'Level 1',
                        2 => 'Level 2',
                        3 => 'Level 3',
                        4 => 'Level 4',
                        5 => 'Level 5',
                    ]),
                
                Tables\Filters\SelectFilter::make('department'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'view' => Pages\ViewAdmin::route('/{record}'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
```

## 9.4. Custom Form Components

### 9.4.1. User Type Selector Component

```php
<?php

namespace App\Filament\Components;

use App\Enums\UserRole;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;

class UserTypeSelector extends Component
{
    protected string $view = 'filament.components.user-type-selector';

    public static function make(string $name = 'user_type'): static
    {
        return app(static::class, ['name' => $name]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            Select::make('role')
                ->options(UserRole::getFilamentOptions())
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    // Auto-set type based on role
                    $type = match ($state) {
                        UserRole::Admin->value, UserRole::SuperAdmin->value => 'admin',
                        UserRole::Guest->value => 'guest',
                        default => 'standard_user',
                    };
                    $set('type', $type);
                }),

            Select::make('type')
                ->options([
                    'standard_user' => 'Standard User',
                    'admin' => 'Administrator',
                    'guest' => 'Guest User',
                ])
                ->disabled()
                ->dehydrated(),
        ]);
    }
}
```

## 9.5. Custom Table Columns

### 9.5.1. User State Column

```php
<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\Column;

class UserStateColumn extends Column
{
    protected string $view = 'filament.tables.columns.user-state';

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(function ($state, $record) {
            if (!$state) return null;

            return [
                'name' => $state->getDisplayName(),
                'color' => $state->getColor(),
                'can_login' => $state->canLogin(),
                'actions' => $state->getAllowedActions(),
            ];
        });
    }
}
```

## 9.6. Dashboard Widgets

### 9.6.1. User Statistics Widget

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Admin;
use App\Models\Guest;
use App\Models\StandardUser;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count())
                ->description('All user types')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Standard Users', StandardUser::count())
                ->description('Regular users')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('success'),

            Stat::make('Administrators', Admin::count())
                ->description('Admin users')
                ->descriptionIcon('heroicon-m-key')
                ->color('warning'),

            Stat::make('Guest Users', Guest::count())
                ->description('Temporary users')
                ->descriptionIcon('heroicon-m-user')
                ->color('gray'),

            Stat::make('Active Users', User::where('is_active', true)->count())
                ->description('Currently active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('New This Month', User::whereMonth('created_at', now()->month)->count())
                ->description('Registered this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
        ];
    }
}
```

## 9.7. Team Switcher Component

### 9.7.1. Active Team Switcher for Admin Panel Header

```php
<?php

namespace App\Filament\Components;

use App\Models\Team;
use App\Services\ActiveTeamService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Livewire\Component;

class TeamSwitcher extends Component
{
    public ?string $activeTeamId = null;
    public array $availableTeams = [];

    public function mount(ActiveTeamService $activeTeamService): void
    {
        $user = auth()->user();

        if ($user) {
            $this->activeTeamId = $user->active_team_id;
            $this->availableTeams = $activeTeamService->getAvailableTeams($user)
                ->mapWithKeys(fn (Team $team) => [$team->id => $team->name])
                ->toArray();
        }
    }

    public function switchTeam(ActiveTeamService $activeTeamService): void
    {
        $user = auth()->user();

        if (!$user || !$this->activeTeamId) {
            return;
        }

        $team = Team::find($this->activeTeamId);

        if (!$team) {
            Notification::make()
                ->title('Team not found')
                ->danger()
                ->send();
            return;
        }

        try {
            $activeTeamService->setActiveTeam($user, $team);

            Notification::make()
                ->title("Switched to {$team->name}")
                ->success()
                ->send();

            // Refresh the page to update context
            redirect()->to(request()->url());

        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to switch team')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearActiveTeam(ActiveTeamService $activeTeamService): void
    {
        $user = auth()->user();

        if ($user) {
            $activeTeamService->clearActiveTeam($user);
            $this->activeTeamId = null;

            Notification::make()
                ->title('Cleared active team')
                ->success()
                ->send();

            redirect()->to(request()->url());
        }
    }

    public function render(): string
    {
        return <<<'HTML'
        <div class="flex items-center space-x-2">
            <x-filament::input.wrapper>
                <x-filament::input.select
                    wire:model.live="activeTeamId"
                    wire:change="switchTeam"
                    placeholder="Select Team"
                    class="min-w-48"
                >
                    <option value="">No Team Selected</option>
                    @foreach($availableTeams as $teamId => $teamName)
                        <option value="{{ $teamId }}">{{ $teamName }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>

            @if($activeTeamId)
                <x-filament::icon-button
                    icon="heroicon-o-x-mark"
                    tooltip="Clear active team"
                    wire:click="clearActiveTeam"
                    color="gray"
                />
            @endif
        </div>
        HTML;
    }
}
```

---

**Next**: [Teams and Hierarchical Structure](100-teams-hierarchical-structure.md) - Self-referential polymorphic STI for Teams.
