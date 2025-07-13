# Users Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Authentication Management](#authentication-management)
- [Role and Permission Management](#role-and-permission-management)
- [User Activity Tracking](#user-activity-tracking)
- [Security Features](#security-features)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Users resource in Filament 4 for the Chinook application. The
Users resource manages system user accounts, authentication, roles, permissions, and security features for the music
platform.

**🚀 Key Features:**

- **User Account Management**: Comprehensive user profile handling
- **Authentication Control**: Login, password, and session management
- **Role-Based Access Control**: Integration with spatie/laravel-permission
- **Security Monitoring**: Login tracking and security alerts
- **WCAG 2.1 AA Compliance**: Accessible user management interface

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/UserResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('User Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->suffixIcon('heroicon-m-envelope'),

                    Forms\Components\DateTimePicker::make('email_verified_at')
                        ->label('Email Verified At')
                        ->displayFormat('M j, Y g:i A')
                        ->helperText('Leave empty if email is not verified'),

                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->minLength(8)
                        ->maxLength(255)
                        ->helperText('Minimum 8 characters required'),

                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->same('password')
                        ->dehydrated(false)
                        ->required(fn (string $context): bool => $context === 'create'),
                ])->columns(2),

            Forms\Components\Section::make('Profile Information')
                ->schema([
                    Forms\Components\FileUpload::make('avatar')
                        ->image()
                        ->directory('user-avatars')
                        ->visibility('public')
                        ->imageEditor()
                        ->circleCropper(),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(24),

                    Forms\Components\DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->maxDate(now()->subYears(13))
                        ->displayFormat('M j, Y'),

                    Forms\Components\Select::make('timezone')
                        ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz]))
                        ->searchable()
                        ->default('UTC'),

                    Forms\Components\Select::make('language')
                        ->options([
                            'en' => 'English',
                            'es' => 'Spanish',
                            'fr' => 'French',
                            'de' => 'German',
                            'it' => 'Italian',
                            'pt' => 'Portuguese',
                        ])
                        ->default('en')
                        ->searchable(),

                    Forms\Components\Select::make('theme')
                        ->options([
                            'light' => 'Light',
                            'dark' => 'Dark',
                            'auto' => 'Auto (System)',
                        ])
                        ->default('auto'),
                ])->columns(2),

            Forms\Components\Section::make('Roles & Permissions')
                ->schema([
                    Forms\Components\CheckboxList::make('roles')
                        ->relationship('roles', 'name')
                        ->options(\Spatie\Permission\Models\Role::pluck('name', 'name'))
                        ->columns(2)
                        ->helperText('Select roles for this user'),

                    Forms\Components\CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->options(\Spatie\Permission\Models\Permission::pluck('name', 'name'))
                        ->columns(3)
                        ->helperText('Additional permissions beyond role permissions'),
                ])->columns(1),

            Forms\Components\Section::make('Account Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Account')
                        ->default(true)
                        ->helperText('Inactive users cannot log in'),

                    Forms\Components\Toggle::make('email_notifications')
                        ->label('Email Notifications')
                        ->default(true)
                        ->helperText('Receive email notifications'),

                    Forms\Components\Toggle::make('marketing_consent')
                        ->label('Marketing Consent')
                        ->default(false)
                        ->helperText('Consent to receive marketing communications'),

                    Forms\Components\Toggle::make('two_factor_enabled')
                        ->label('Two-Factor Authentication')
                        ->default(false)
                        ->helperText('Enable 2FA for enhanced security'),

                    Forms\Components\DateTimePicker::make('last_login_at')
                        ->label('Last Login')
                        ->disabled()
                        ->displayFormat('M j, Y g:i A'),

                    Forms\Components\TextInput::make('login_count')
                        ->label('Login Count')
                        ->numeric()
                        ->disabled()
                        ->default(0),
                ])->columns(3),

            Forms\Components\Section::make('Additional Information')
                ->schema([
                    Forms\Components\Textarea::make('bio')
                        ->maxLength(500)
                        ->rows(3)
                        ->helperText('User biography or description'),

                    Forms\Components\KeyValue::make('preferences')
                        ->label('User Preferences')
                        ->keyLabel('Setting')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Preference'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Admin Notes')
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Internal notes about this user'),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->getStateUsing(fn ($record) => !is_null($record->email_verified_at))
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super-admin' => 'danger',
                        'admin' => 'warning',
                        'manager' => 'info',
                        'editor' => 'success',
                        'user' => 'gray',
                        default => 'gray',
                    })
                    ->separator(','),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('login_count')
                    ->label('Logins')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->trueLabel('Active accounts')
                    ->falseLabel('Inactive accounts')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email Verification')
                    ->trueLabel('Verified emails')
                    ->falseLabel('Unverified emails')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    )
                    ->native(false),

                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('two_factor_enabled')
                    ->label('Two-Factor Authentication')
                    ->trueLabel('2FA enabled')
                    ->falseLabel('2FA disabled')
                    ->native(false),

                Tables\Filters\Filter::make('recent_login')
                    ->label('Recent Login')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('last_login_at', '>=', now()->subDays(30))
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('inactive_users')
                    ->label('Inactive Users')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('last_login_at', '<', now()->subDays(90))
                              ->orWhereNull('last_login_at')
                    )
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-user-circle')
                    ->action(function (User $record) {
                        if (auth()->user()->can('impersonate', $record)) {
                            session(['impersonating' => $record->id]);
                            return redirect()->route('dashboard');
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => auth()->user()->can('impersonate', $record)),

                Tables\Actions\Action::make('send_verification')
                    ->label('Send Verification')
                    ->icon('heroicon-o-envelope')
                    ->action(function (User $record) {
                        $record->sendEmailVerificationNotification();
                        
                        Notification::make()
                            ->title('Verification email sent')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => is_null($record->email_verified_at)),

                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->same('password')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'password' => Hash::make($data['password']),
                        ]);

                        Notification::make()
                            ->title('Password reset successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),

                    Tables\Actions\BulkAction::make('verify_emails')
                        ->label('Verify Emails')
                        ->icon('heroicon-o-check-badge')
                        ->action(function (Collection $records) {
                            $records->each(function ($record) {
                                $record->update(['email_verified_at' => now()]);
                            });

                            Notification::make()
                                ->title("{$records->count()} emails verified")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('assign_role')
                        ->label('Assign Role')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->options(\Spatie\Permission\Models\Role::pluck('name', 'name'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->assignRole($data['role']);
                            });

                            Notification::make()
                                ->title("Role assigned to {$records->count()} users")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('export_users')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return response()->download(
                                app(UserExportService::class)->export($records)
                            );
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LoginHistoryRelationManager::class,
            RelationManagers\ActivityLogRelationManager::class,
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
            ])
            ->with(['roles']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('created_at', '>=', now()->subDays(7))->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $newUsers = static::getModel()::where('created_at', '>=', now()->subDays(7))->count();
        return $newUsers > 5 ? 'success' : 'primary';
    }
}
```

## Form Components

### Advanced User Management Components

```php
<?php
// Custom form components for user management

class UserFormComponents
{
    public static function passwordStrengthIndicator(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('password')
                ->password()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    $strength = self::calculatePasswordStrength($state);
                    $set('password_strength', $strength);
                }),

            Forms\Components\Placeholder::make('password_strength')
                ->label('Password Strength')
                ->content(function (Forms\Get $get): string {
                    $password = $get('password');
                    if (!$password) return '';

                    $strength = self::calculatePasswordStrength($password);
                    $color = match($strength['level']) {
                        'weak' => 'text-red-600',
                        'medium' => 'text-yellow-600',
                        'strong' => 'text-green-600',
                        default => 'text-gray-600',
                    };

                    return "<span class='{$color}'>{$strength['text']}</span>";
                })
                ->extraAttributes(['class' => 'password-strength']),
        ]);
    }

    private static function calculatePasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];

        if (strlen($password) >= 8) $score++;
        else $feedback[] = 'At least 8 characters';

        if (preg_match('/[A-Z]/', $password)) $score++;
        else $feedback[] = 'Uppercase letter';

        if (preg_match('/[a-z]/', $password)) $score++;
        else $feedback[] = 'Lowercase letter';

        if (preg_match('/[0-9]/', $password)) $score++;
        else $feedback[] = 'Number';

        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++;
        else $feedback[] = 'Special character';

        return match($score) {
            0, 1, 2 => ['level' => 'weak', 'text' => 'Weak - Missing: ' . implode(', ', $feedback)],
            3, 4 => ['level' => 'medium', 'text' => 'Medium - Consider: ' . implode(', ', $feedback)],
            5 => ['level' => 'strong', 'text' => 'Strong password'],
            default => ['level' => 'weak', 'text' => 'Invalid'],
        };
    }

    public static function rolePermissionMatrix(): Forms\Components\Component
    {
        return Forms\Components\Tabs::make('Permissions')
            ->tabs([
                Forms\Components\Tabs\Tab::make('Roles')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->options(\Spatie\Permission\Models\Role::pluck('name', 'name'))
                            ->descriptions(\Spatie\Permission\Models\Role::pluck('description', 'name'))
                            ->columns(2),
                    ]),

                Forms\Components\Tabs\Tab::make('Direct Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship('permissions', 'name')
                            ->options(\Spatie\Permission\Models\Permission::pluck('name', 'name'))
                            ->columns(3),
                    ]),
            ]);
    }
}
```

## Table Configuration

### Advanced User Analytics

```php
<?php
// Enhanced user table with analytics

class UserAnalyticsTable
{
    public static function getUserActivityColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\Layout\Grid::make(2)
                    ->schema([
                        Tables\Columns\ImageColumn::make('avatar')
                            ->circular()
                            ->size(50),

                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('name')
                                ->weight(FontWeight::Bold),

                            Tables\Columns\TextColumn::make('email')
                                ->color('gray')
                                ->icon('heroicon-m-envelope'),
                        ]),
                    ]),

                Tables\Columns\Layout\Grid::make(4)
                    ->schema([
                        Tables\Columns\TextColumn::make('roles.name')
                            ->badge()
                            ->separator(','),

                        Tables\Columns\IconColumn::make('email_verified_at')
                            ->label('Verified')
                            ->boolean()
                            ->getStateUsing(fn ($record) => !is_null($record->email_verified_at)),

                        Tables\Columns\TextColumn::make('last_login_at')
                            ->since()
                            ->placeholder('Never'),

                        Tables\Columns\TextColumn::make('login_count')
                            ->badge()
                            ->color('info'),
                    ]),
            ])->space(2),
        ];
    }
}
```

## Authentication Management

### Authentication Service

```php
<?php
// app/Services/AuthenticationService.php

namespace App\Services;

use App\Models\{User, LoginHistory};
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    /**
     * Track user login
     */
    public function trackLogin(User $user, array $loginData): void
    {
        $user->update([
            'last_login_at' => now(),
            'login_count' => $user->login_count + 1,
        ]);

        // Record login history
        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $loginData['ip_address'] ?? request()->ip(),
            'user_agent' => $loginData['user_agent'] ?? request()->userAgent(),
            'login_at' => now(),
            'location' => $this->getLocationFromIP($loginData['ip_address'] ?? request()->ip()),
        ]);

        // Check for suspicious activity
        $this->checkSuspiciousActivity($user, $loginData);
    }

    /**
     * Check for suspicious login activity
     */
    private function checkSuspiciousActivity(User $user, array $loginData): void
    {
        $recentLogins = $user->loginHistory()
            ->where('login_at', '>=', now()->subHours(24))
            ->get();

        // Check for multiple locations
        $uniqueLocations = $recentLogins->pluck('location')->unique();
        if ($uniqueLocations->count() > 3) {
            $this->flagSuspiciousActivity($user, 'multiple_locations', [
                'locations' => $uniqueLocations->toArray(),
            ]);
        }

        // Check for unusual login times
        $currentHour = now()->hour;
        $usualHours = $user->loginHistory()
            ->where('login_at', '>=', now()->subDays(30))
            ->get()
            ->map(fn ($login) => $login->login_at->hour)
            ->mode();

        if (abs($currentHour - $usualHours) > 6) {
            $this->flagSuspiciousActivity($user, 'unusual_time', [
                'current_hour' => $currentHour,
                'usual_hours' => $usualHours,
            ]);
        }
    }

    /**
     * Flag suspicious activity
     */
    private function flagSuspiciousActivity(User $user, string $type, array $data): void
    {
        // Log security event
        activity()
            ->performedOn($user)
            ->withProperties([
                'type' => $type,
                'data' => $data,
                'ip_address' => request()->ip(),
            ])
            ->log('Suspicious activity detected');

        // Notify security team
        Mail::to(config('security.notification_email'))
            ->send(new SuspiciousActivityAlert($user, $type, $data));
    }

    /**
     * Generate secure password reset token
     */
    public function generatePasswordResetToken(User $user): string
    {
        $token = Str::random(64);

        $user->passwordResets()->create([
            'token' => Hash::make($token),
            'created_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        return $token;
    }
}
```

## Role and Permission Management

### Role Management Service

```php
<?php
// app/Services/RoleManagementService.php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\{Role, Permission};

class RoleManagementService
{
    /**
     * Assign role with validation
     */
    public function assignRole(User $user, string $roleName): bool
    {
        $role = Role::findByName($roleName);

        // Check if user can be assigned this role
        if (!$this->canAssignRole(auth()->user(), $role)) {
            throw new \Exception('Insufficient permissions to assign this role');
        }

        $user->assignRole($role);

        // Log role assignment
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['role' => $roleName])
            ->log('Role assigned');

        return true;
    }

    /**
     * Check if user can assign role
     */
    private function canAssignRole(User $assigner, Role $role): bool
    {
        // Super admins can assign any role
        if ($assigner->hasRole('super-admin')) {
            return true;
        }

        // Admins can assign roles below their level
        if ($assigner->hasRole('admin')) {
            return !in_array($role->name, ['super-admin']);
        }

        // Managers can assign basic roles
        if ($assigner->hasRole('manager')) {
            return in_array($role->name, ['user', 'editor']);
        }

        return false;
    }

    /**
     * Get role hierarchy
     */
    public function getRoleHierarchy(): array
    {
        return [
            'super-admin' => [
                'level' => 10,
                'description' => 'Full system access',
                'can_assign' => ['admin', 'manager', 'editor', 'user'],
            ],
            'admin' => [
                'level' => 9,
                'description' => 'Administrative access',
                'can_assign' => ['manager', 'editor', 'user'],
            ],
            'manager' => [
                'level' => 8,
                'description' => 'Management access',
                'can_assign' => ['editor', 'user'],
            ],
            'editor' => [
                'level' => 6,
                'description' => 'Content management',
                'can_assign' => [],
            ],
            'user' => [
                'level' => 5,
                'description' => 'Basic user access',
                'can_assign' => [],
            ],
        ];
    }

    /**
     * Sync user permissions based on roles
     */
    public function syncUserPermissions(User $user): void
    {
        $rolePermissions = $user->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique();

        $directPermissions = $user->permissions()->pluck('name');

        $allPermissions = $rolePermissions->merge($directPermissions)->unique();

        $user->syncPermissions($allPermissions);
    }
}
```

## User Activity Tracking

### Activity Tracking Service

```php
<?php
// app/Services/UserActivityService.php

namespace App\Services;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class UserActivityService
{
    /**
     * Get user activity summary
     */
    public function getActivitySummary(User $user, int $days = 30): array
    {
        $activities = Activity::where('causer_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        return [
            'total_activities' => $activities->count(),
            'daily_average' => round($activities->count() / $days, 2),
            'most_active_day' => $activities->groupBy(fn ($activity) => $activity->created_at->format('Y-m-d'))
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first(),
            'activity_types' => $activities->groupBy('log_name')
                ->map->count()
                ->sortDesc(),
            'recent_activities' => $activities->latest()->take(10),
        ];
    }

    /**
     * Track user session
     */
    public function trackSession(User $user): void
    {
        $sessionData = [
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'started_at' => now(),
        ];

        $user->sessions()->create($sessionData);
    }

    /**
     * Get user engagement metrics
     */
    public function getEngagementMetrics(User $user): array
    {
        $loginHistory = $user->loginHistory()
            ->where('login_at', '>=', now()->subDays(30))
            ->get();

        $sessionDurations = $user->sessions()
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', now()->subDays(30))
            ->get()
            ->map(fn ($session) => $session->started_at->diffInMinutes($session->ended_at));

        return [
            'login_frequency' => $loginHistory->count(),
            'average_session_duration' => $sessionDurations->average() ?? 0,
            'total_time_spent' => $sessionDurations->sum(),
            'most_active_hours' => $loginHistory->groupBy(fn ($login) => $login->login_at->hour)
                ->map->count()
                ->sortDesc()
                ->take(3)
                ->keys(),
        ];
    }
}
```

## Security Features

### Security Monitoring Service

```php
<?php
// app/Services/SecurityMonitoringService.php

namespace App\Services;

use App\Models\User;

class SecurityMonitoringService
{
    /**
     * Monitor failed login attempts
     */
    public function monitorFailedLogins(string $email, string $ipAddress): void
    {
        $key = "failed_logins:{$email}:{$ipAddress}";
        $attempts = cache()->increment($key);

        if ($attempts === 1) {
            cache()->put($key, 1, now()->addMinutes(15));
        }

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['locked_until' => now()->addMinutes(30)]);

                // Notify user of account lock
                Mail::to($user->email)->send(new AccountLockedNotification($user));
            }
        }
    }

    /**
     * Check for account takeover indicators
     */
    public function checkAccountTakeover(User $user): array
    {
        $indicators = [];

        // Check for password changes
        $recentPasswordChange = $user->passwordChanges()
            ->where('changed_at', '>=', now()->subHours(24))
            ->exists();

        if ($recentPasswordChange) {
            $indicators[] = 'recent_password_change';
        }

        // Check for email changes
        $recentEmailChange = $user->emailChanges()
            ->where('changed_at', '>=', now()->subHours(24))
            ->exists();

        if ($recentEmailChange) {
            $indicators[] = 'recent_email_change';
        }

        // Check for unusual login patterns
        $unusualLogins = $user->loginHistory()
            ->where('login_at', '>=', now()->subHours(24))
            ->whereNotIn('ip_address', $user->trustedIpAddresses())
            ->count();

        if ($unusualLogins > 0) {
            $indicators[] = 'unusual_login_locations';
        }

        return [
            'risk_level' => $this->calculateRiskLevel($indicators),
            'indicators' => $indicators,
            'recommendations' => $this->getSecurityRecommendations($indicators),
        ];
    }

    private function calculateRiskLevel(array $indicators): string
    {
        $count = count($indicators);

        return match(true) {
            $count >= 3 => 'high',
            $count >= 2 => 'medium',
            $count >= 1 => 'low',
            default => 'none',
        };
    }
}
```

## Testing

### User Resource Testing

```php
<?php
// tests/Feature/Filament/UserResourceTest.php

use App\Filament\Resources\UserResource;
use App\Models\User;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    public function test_can_render_user_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(UserResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_user(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('create-users');
        $this->actingAs($admin);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->post(UserResource::getUrl('create'), $userData);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_user_role_assignment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($user->hasRole('admin'))->toBeTrue();
        expect($user->can('manage-users'))->toBeTrue();
    }
}
```

## Best Practices

### User Management Guidelines

1. **Security First**: Implement strong authentication and authorization
2. **Privacy Protection**: Secure handling of personal user data
3. **Role Management**: Use hierarchical role structures
4. **Activity Monitoring**: Track user activities for security
5. **Performance**: Optimize queries for large user datasets
6. **Compliance**: Follow data protection regulations

### Performance Optimization

```php
<?php
// Optimized user queries

class UserResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles:id,name'])
            ->withCount(['loginHistory'])
            ->when(
                auth()->user()->cannot('view-all-users'),
                fn (Builder $query) => $query->where('id', auth()->id())
            );
    }
}
```

## Navigation

**← Previous:** [Employees Resource Guide](100-employees-resource.md)
**Next →** [Form Components Guide](120-form-components.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components Guide](120-form-components.md) - Advanced form component patterns
- [Relationship Managers Guide](120-relationship-managers.md) - Managing model relationships

---

*This guide provides comprehensive Filament 4 resource implementation for user management in the Chinook application.
Each pattern includes authentication, security monitoring, and role management for robust user administration.*
