# ChinookEmployees Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Role Management](#role-management)
- [Performance Tracking](#performance-tracking)
- [HR Integration](#hr-integration)
- [Security and Privacy](#security-and-privacy)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Employees resource in Filament 4 for the Chinook application.
The Employees resource manages staff information, roles, performance tracking, and HR integration for the music platform
administration.

**🚀 Key Features:**

- **Employee Management**: Comprehensive staff profile handling
- **Role-Based Access**: Integration with spatie/laravel-permission
- **Performance Tracking**: Employee metrics and KPIs
- **HR Integration**: Payroll and benefits management
- **WCAG 2.1 AA Compliance**: Accessible employee interface

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/EmployeeResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Human Resources';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Personal Information')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->required()
                        ->maxLength(20)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get, ?string $state) => 
                            $set('full_name', trim($state . ' ' . ($get('last_name') ?? '')))
                        ),

                    Forms\Components\TextInput::make('last_name')
                        ->required()
                        ->maxLength(20)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get, ?string $state) => 
                            $set('full_name', trim(($get('first_name') ?? '') . ' ' . $state))
                        ),

                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(60)
                        ->unique(Employee::class, 'email', ignoreRecord: true)
                        ->suffixIcon('heroicon-m-envelope'),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(24)
                        ->suffixIcon('heroicon-m-phone'),

                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Date of Birth')
                        ->maxDate(now()->subYears(16))
                        ->displayFormat('M j, Y'),

                    Forms\Components\FileUpload::make('avatar')
                        ->image()
                        ->directory('employee-avatars')
                        ->visibility('private')
                        ->imageEditor()
                        ->circleCropper(),
                ])->columns(2),

            Forms\Components\Section::make('Employment Details')
                ->schema([
                    Forms\Components\TextInput::make('employee_id')
                        ->label('Employee ID')
                        ->required()
                        ->unique(Employee::class, 'employee_id', ignoreRecord: true)
                        ->default(fn () => 'EMP-' . str_pad(Employee::max('id') + 1, 4, '0', STR_PAD_LEFT)),

                    Forms\Components\TextInput::make('title')
                        ->label('Job Title')
                        ->required()
                        ->maxLength(30),

                    Forms\Components\Select::make('department')
                        ->options([
                            'administration' => 'Administration',
                            'sales' => 'Sales',
                            'marketing' => 'Marketing',
                            'customer_service' => 'Customer Service',
                            'it' => 'Information Technology',
                            'finance' => 'Finance',
                            'hr' => 'Human Resources',
                            'content' => 'Content Management',
                        ])
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('reports_to')
                        ->label('Reports To')
                        ->relationship('manager', 'full_name')
                        ->searchable()
                        ->preload(),

                    Forms\Components\DatePicker::make('hire_date')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),

                    Forms\Components\Select::make('employment_type')
                        ->options([
                            'full_time' => 'Full Time',
                            'part_time' => 'Part Time',
                            'contract' => 'Contract',
                            'intern' => 'Intern',
                            'consultant' => 'Consultant',
                        ])
                        ->default('full_time')
                        ->required(),
                ])->columns(2),

            Forms\Components\Section::make('Address Information')
                ->schema([
                    Forms\Components\TextInput::make('address')
                        ->maxLength(70)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('city')
                        ->maxLength(40),

                    Forms\Components\TextInput::make('state')
                        ->maxLength(40),

                    Forms\Components\TextInput::make('country')
                        ->maxLength(40)
                        ->default('USA'),

                    Forms\Components\TextInput::make('postal_code')
                        ->maxLength(10),
                ])->columns(2),

            Forms\Components\Section::make('Compensation & Benefits')
                ->schema([
                    Forms\Components\TextInput::make('salary')
                        ->numeric()
                        ->prefix('$')
                        ->helperText('Annual salary'),

                    Forms\Components\Select::make('pay_frequency')
                        ->options([
                            'weekly' => 'Weekly',
                            'bi_weekly' => 'Bi-Weekly',
                            'monthly' => 'Monthly',
                            'annual' => 'Annual',
                        ])
                        ->default('bi_weekly'),

                    Forms\Components\DatePicker::make('last_review_date')
                        ->label('Last Performance Review'),

                    Forms\Components\DatePicker::make('next_review_date')
                        ->label('Next Performance Review')
                        ->after('last_review_date'),

                    Forms\Components\CheckboxList::make('benefits')
                        ->options([
                            'health_insurance' => 'Health Insurance',
                            'dental_insurance' => 'Dental Insurance',
                            'vision_insurance' => 'Vision Insurance',
                            'retirement_401k' => '401(k) Retirement Plan',
                            'paid_time_off' => 'Paid Time Off',
                            'sick_leave' => 'Sick Leave',
                            'life_insurance' => 'Life Insurance',
                            'disability_insurance' => 'Disability Insurance',
                        ])
                        ->columns(2)
                        ->gridDirection('row'),
                ])->columns(2),

            Forms\Components\Section::make('System Access')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('System User Account')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\TextInput::make('email')->email()->required(),
                            Forms\Components\TextInput::make('password')->password()->required(),
                        ]),

                    Forms\Components\CheckboxList::make('roles')
                        ->relationship('roles', 'name')
                        ->options(function () {
                            return \Spatie\Permission\Models\Role::pluck('name', 'name');
                        })
                        ->columns(2)
                        ->helperText('Select roles for this employee'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Employee')
                        ->default(true)
                        ->helperText('Inactive employees cannot access the system'),

                    Forms\Components\DatePicker::make('termination_date')
                        ->label('Termination Date')
                        ->visible(fn (Forms\Get $get) => !$get('is_active')),
                ])->columns(2),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Internal notes about this employee'),
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

                Tables\Columns\TextColumn::make('employee_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('title')
                    ->label('Job Title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'administration' => 'gray',
                        'sales' => 'success',
                        'marketing' => 'info',
                        'customer_service' => 'warning',
                        'it' => 'primary',
                        'finance' => 'danger',
                        'hr' => 'secondary',
                        'content' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('manager.full_name')
                    ->label('Manager')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('employment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'full_time' => 'success',
                        'part_time' => 'warning',
                        'contract' => 'info',
                        'intern' => 'gray',
                        'consultant' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('hire_date')
                    ->label('Hire Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('years_of_service')
                    ->label('Years')
                    ->getStateUsing(fn (Employee $record): string => 
                        $record->hire_date->diffInYears(now()) . 'y'
                    )
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->options([
                        'administration' => 'Administration',
                        'sales' => 'Sales',
                        'marketing' => 'Marketing',
                        'customer_service' => 'Customer Service',
                        'it' => 'Information Technology',
                        'finance' => 'Finance',
                        'hr' => 'Human Resources',
                        'content' => 'Content Management',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('employment_type')
                    ->label('Employment Type')
                    ->options([
                        'full_time' => 'Full Time',
                        'part_time' => 'Part Time',
                        'contract' => 'Contract',
                        'intern' => 'Intern',
                        'consultant' => 'Consultant',
                    ])
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Employment Status')
                    ->trueLabel('Active employees')
                    ->falseLabel('Inactive employees')
                    ->native(false),

                Tables\Filters\SelectFilter::make('manager')
                    ->relationship('manager', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('hire_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('hired_from')
                            ->label('Hired From'),
                        Forms\Components\DatePicker::make('hired_until')
                            ->label('Hired Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['hired_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('hire_date', '>=', $date),
                            )
                            ->when(
                                $data['hired_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('hire_date', '<=', $date),
                            );
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('performance_review')
                    ->label('Performance Review')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->form([
                        Forms\Components\Select::make('rating')
                            ->options([
                                'exceeds' => 'Exceeds Expectations',
                                'meets' => 'Meets Expectations',
                                'below' => 'Below Expectations',
                                'unsatisfactory' => 'Unsatisfactory',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('comments')
                            ->required()
                            ->rows(4),
                        Forms\Components\DatePicker::make('review_date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (Employee $record, array $data) {
                        $record->performanceReviews()->create([
                            'reviewer_id' => auth()->id(),
                            'rating' => $data['rating'],
                            'comments' => $data['comments'],
                            'review_date' => $data['review_date'],
                        ]);

                        $record->update([
                            'last_review_date' => $data['review_date'],
                            'next_review_date' => now()->addYear(),
                        ]);

                        Notification::make()
                            ->title('Performance review completed')
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

                    Tables\Actions\BulkAction::make('assign_role')
                        ->label('Assign Role')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->options(\Spatie\Permission\Models\Role::pluck('name', 'name'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $record->user?->assignRole($data['role']);
                            }

                            Notification::make()
                                ->title("Role assigned to {$records->count()} employees")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('export_employees')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return response()->download(
                                app(EmployeeExportService::class)->export($records)
                            );
                        }),
                ]),
            ])
            ->defaultSort('hire_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PerformanceReviewsRelationManager::class,
            RelationManagers\TimeOffRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['manager', 'user']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }
}
```

## Form Components

### Advanced Employee Management Components

```php
<?php
// Custom form components for employee management

class EmployeeFormComponents
{
    public static function organizationChart(): Forms\Components\Component
    {
        return Forms\Components\Select::make('reports_to')
            ->label('Reports To')
            ->relationship('manager', 'full_name')
            ->getOptionLabelFromRecordUsing(fn ($record): string =>
                "{$record->full_name} ({$record->title})"
            )
            ->searchable(['first_name', 'last_name', 'title'])
            ->preload()
            ->helperText('Select the direct manager for this employee');
    }

    public static function compensationCalculator(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('base_salary')
                ->numeric()
                ->prefix('$')
                ->live(),

            Forms\Components\TextInput::make('bonus_percentage')
                ->numeric()
                ->suffix('%')
                ->default(0)
                ->live(),

            Forms\Components\Placeholder::make('total_compensation')
                ->label('Total Annual Compensation')
                ->content(function (Forms\Get $get): string {
                    $base = $get('base_salary') ?? 0;
                    $bonus = $get('bonus_percentage') ?? 0;
                    $total = $base * (1 + ($bonus / 100));
                    return '$' . number_format($total, 2);
                }),
        ])->columns(3);
    }
}
```

## Table Configuration

### Employee Analytics Dashboard

```php
<?php
// Enhanced employee table with HR analytics

class EmployeeAnalyticsTable
{
    public static function getHRColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\ImageColumn::make('avatar')
                    ->circular()
                    ->size(50),

                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('full_name')
                        ->weight(FontWeight::Bold),

                    Tables\Columns\TextColumn::make('title')
                        ->color('gray'),

                    Tables\Columns\Layout\Grid::make(3)
                        ->schema([
                            Tables\Columns\TextColumn::make('department')
                                ->badge(),

                            Tables\Columns\TextColumn::make('employment_type')
                                ->badge(),

                            Tables\Columns\TextColumn::make('years_of_service')
                                ->getStateUsing(fn ($record) =>
                                    $record->hire_date->diffInYears(now()) . ' years'
                                ),
                        ]),
                ]),
            ])->space(3),
        ];
    }
}
```

## Role Management

### Employee Role Service

```php
<?php
// app/Services/EmployeeRoleService.php

namespace App\Services;

use App\Models\Employee;
use Spatie\Permission\Models\Role;

class EmployeeRoleService
{
    /**
     * Assign role to employee
     */
    public function assignRole(Employee $employee, string $roleName): bool
    {
        if (!$employee->user) {
            throw new \Exception('Employee must have a user account to assign roles');
        }

        $role = Role::findByName($roleName);
        $employee->user->assignRole($role);

        // Log role assignment
        activity()
            ->performedOn($employee)
            ->causedBy(auth()->user())
            ->withProperties(['role' => $roleName])
            ->log('Role assigned');

        return true;
    }

    /**
     * Get role hierarchy for employee
     */
    public function getRoleHierarchy(Employee $employee): array
    {
        $hierarchy = [
            'super-admin' => 10,
            'admin' => 9,
            'manager' => 8,
            'supervisor' => 7,
            'senior-employee' => 6,
            'employee' => 5,
            'intern' => 4,
            'contractor' => 3,
            'guest' => 2,
        ];

        $userRoles = $employee->user?->getRoleNames() ?? collect();
        $maxLevel = 0;

        foreach ($userRoles as $role) {
            $level = $hierarchy[$role] ?? 0;
            $maxLevel = max($maxLevel, $level);
        }

        return [
            'level' => $maxLevel,
            'roles' => $userRoles,
            'can_manage' => $userRoles->intersect(['super-admin', 'admin', 'manager'])->isNotEmpty(),
        ];
    }
}
```

## Performance Tracking

### Performance Management System

```php
<?php
// app/Services/PerformanceTrackingService.php

namespace App\Services;

use App\Models\{Employee, PerformanceReview};

class PerformanceTrackingService
{
    /**
     * Calculate employee performance score
     */
    public function calculatePerformanceScore(Employee $employee): array
    {
        $reviews = $employee->performanceReviews()
            ->where('review_date', '>=', now()->subYear())
            ->get();

        if ($reviews->isEmpty()) {
            return [
                'score' => null,
                'rating' => 'No reviews',
                'trend' => 'neutral',
            ];
        }

        $scoreMap = [
            'exceeds' => 4,
            'meets' => 3,
            'below' => 2,
            'unsatisfactory' => 1,
        ];

        $scores = $reviews->map(fn ($review) => $scoreMap[$review->rating] ?? 0);
        $averageScore = $scores->average();

        $rating = match(true) {
            $averageScore >= 3.5 => 'Exceeds Expectations',
            $averageScore >= 2.5 => 'Meets Expectations',
            $averageScore >= 1.5 => 'Below Expectations',
            default => 'Unsatisfactory',
        };

        // Calculate trend
        $recentScore = $scores->last();
        $previousScore = $scores->slice(-2, 1)->first();

        $trend = match(true) {
            $recentScore > $previousScore => 'improving',
            $recentScore < $previousScore => 'declining',
            default => 'stable',
        };

        return [
            'score' => round($averageScore, 2),
            'rating' => $rating,
            'trend' => $trend,
            'review_count' => $reviews->count(),
        ];
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport(string $department = null): array
    {
        $query = Employee::where('is_active', true);

        if ($department) {
            $query->where('department', $department);
        }

        $employees = $query->with('performanceReviews')->get();

        $departmentStats = [];
        foreach ($employees as $employee) {
            $dept = $employee->department;
            if (!isset($departmentStats[$dept])) {
                $departmentStats[$dept] = [
                    'employee_count' => 0,
                    'total_score' => 0,
                    'reviewed_count' => 0,
                ];
            }

            $departmentStats[$dept]['employee_count']++;

            $performance = $this->calculatePerformanceScore($employee);
            if ($performance['score']) {
                $departmentStats[$dept]['total_score'] += $performance['score'];
                $departmentStats[$dept]['reviewed_count']++;
            }
        }

        // Calculate averages
        foreach ($departmentStats as $dept => &$stats) {
            $stats['average_score'] = $stats['reviewed_count'] > 0
                ? round($stats['total_score'] / $stats['reviewed_count'], 2)
                : null;
        }

        return $departmentStats;
    }
}
```

## HR Integration

### HR Management Service

```php
<?php
// app/Services/HRManagementService.php

namespace App\Services;

use App\Models\{Employee, TimeOffRequest};

class HRManagementService
{
    /**
     * Calculate employee benefits eligibility
     */
    public function calculateBenefitsEligibility(Employee $employee): array
    {
        $yearsOfService = $employee->hire_date->diffInYears(now());
        $isFullTime = $employee->employment_type === 'full_time';

        return [
            'health_insurance' => $isFullTime,
            'dental_insurance' => $isFullTime && $yearsOfService >= 0.25, // 3 months
            'vision_insurance' => $isFullTime && $yearsOfService >= 0.25,
            'retirement_401k' => $isFullTime && $yearsOfService >= 1,
            'paid_time_off' => $this->calculatePTOEligibility($employee),
            'sick_leave' => $isFullTime,
            'life_insurance' => $isFullTime && $yearsOfService >= 1,
            'disability_insurance' => $isFullTime && $yearsOfService >= 1,
        ];
    }

    /**
     * Calculate PTO eligibility
     */
    private function calculatePTOEligibility(Employee $employee): array
    {
        $yearsOfService = $employee->hire_date->diffInYears(now());
        $isFullTime = $employee->employment_type === 'full_time';

        if (!$isFullTime) {
            return ['eligible' => false, 'days' => 0];
        }

        $ptoDays = match(true) {
            $yearsOfService >= 10 => 25,
            $yearsOfService >= 5 => 20,
            $yearsOfService >= 2 => 15,
            $yearsOfService >= 1 => 10,
            default => 5,
        };

        return [
            'eligible' => true,
            'days' => $ptoDays,
            'accrual_rate' => round($ptoDays / 12, 2), // Monthly accrual
        ];
    }

    /**
     * Process time off request
     */
    public function processTimeOffRequest(Employee $employee, array $requestData): TimeOffRequest
    {
        $request = $employee->timeOffRequests()->create([
            'type' => $requestData['type'],
            'start_date' => $requestData['start_date'],
            'end_date' => $requestData['end_date'],
            'days_requested' => $requestData['days_requested'],
            'reason' => $requestData['reason'],
            'status' => 'pending',
        ]);

        // Notify manager
        if ($employee->manager) {
            Mail::to($employee->manager->email)
                ->send(new TimeOffRequestNotification($request));
        }

        return $request;
    }
}
```

## Security and Privacy

### Employee Data Security

```php
<?php
// app/Services/EmployeeSecurityService.php

namespace App\Services;

use App\Models\Employee;

class EmployeeSecurityService
{
    /**
     * Anonymize employee data for GDPR compliance
     */
    public function anonymizeEmployeeData(Employee $employee): void
    {
        $employee->update([
            'first_name' => 'Former',
            'last_name' => 'Employee',
            'email' => 'former_employee_' . $employee->id . '@company.com',
            'phone' => null,
            'address' => null,
            'birth_date' => null,
            'notes' => 'Employee data anonymized on ' . now()->toDateString(),
        ]);

        // Anonymize related data
        $employee->performanceReviews()->update([
            'comments' => 'Comments anonymized',
        ]);

        $employee->timeOffRequests()->update([
            'reason' => 'Reason anonymized',
        ]);
    }

    /**
     * Export employee data for GDPR requests
     */
    public function exportEmployeeData(Employee $employee): array
    {
        return [
            'personal_information' => $employee->only([
                'first_name', 'last_name', 'email', 'phone', 'birth_date'
            ]),
            'employment_details' => $employee->only([
                'employee_id', 'title', 'department', 'hire_date', 'employment_type'
            ]),
            'address' => $employee->only([
                'address', 'city', 'state', 'country', 'postal_code'
            ]),
            'compensation' => $employee->only(['salary', 'pay_frequency']),
            'performance_reviews' => $employee->performanceReviews()->get(),
            'time_off_requests' => $employee->timeOffRequests()->get(),
        ];
    }
}
```

## Testing

### Employee Resource Testing

```php
<?php
// tests/Feature/Filament/EmployeeResourceTest.php

use App\Filament\Resources\EmployeeResource;
use App\Models\{Employee, User};
use Tests\TestCase;

class EmployeeResourceTest extends TestCase
{
    public function test_can_render_employee_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(EmployeeResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_employee(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-employees');
        $this->actingAs($user);

        $employeeData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@company.com',
            'employee_id' => 'EMP-001',
            'title' => 'Software Developer',
            'department' => 'it',
            'hire_date' => now()->toDateString(),
            'employment_type' => 'full_time',
        ];

        $response = $this->post(EmployeeResource::getUrl('create'), $employeeData);

        $this->assertDatabaseHas('employees', $employeeData);
    }

    public function test_employee_performance_calculations(): void
    {
        $employee = Employee::factory()->create();

        // Create performance reviews
        $employee->performanceReviews()->create([
            'rating' => 'exceeds',
            'review_date' => now()->subMonths(6),
            'reviewer_id' => User::factory()->create()->id,
        ]);

        $performance = app(PerformanceTrackingService::class)
            ->calculatePerformanceScore($employee);

        expect($performance['score'])->toBe(4.0);
        expect($performance['rating'])->toBe('Exceeds Expectations');
    }
}
```

## Best Practices

### Employee Management Guidelines

1. **Privacy Protection**: Implement strong data protection for sensitive employee information
2. **Role-Based Access**: Use granular permissions for HR data access
3. **Performance Tracking**: Maintain objective and fair performance evaluation systems
4. **Compliance**: Ensure GDPR and employment law compliance
5. **Security**: Encrypt sensitive data and maintain audit trails
6. **Integration**: Connect with payroll and benefits systems

### Performance Optimization

```php
<?php
// Optimized employee queries

class EmployeeResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['manager:id,first_name,last_name', 'user:id,name'])
            ->withCount(['performanceReviews', 'timeOffRequests'])
            ->when(
                auth()->user()->cannot('view-all-employees'),
                fn (Builder $query) => $query->where('department', auth()->user()->employee?->department)
            );
    }
}
```

## Navigation

**← Previous:** [Invoice Lines Resource Guide](090-invoice-lines-resource.md)
**Next →** [Users Resource Guide](110-users-resource.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components Guide](120-form-components.md) - Advanced form component patterns
- [Table Features Guide](130-table-features.md) - Advanced table configuration and features

---

*This guide provides comprehensive Filament 4 resource implementation for employee management in the Chinook
application. Each pattern includes HR integration, performance tracking, and security considerations for robust
workforce management.*
