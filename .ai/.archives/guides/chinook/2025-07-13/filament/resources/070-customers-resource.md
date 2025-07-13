# ChinookCustomers Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Customer Analytics](#customer-analytics)
- [Purchase History Management](#purchase-history-management)
- [Communication Tools](#communication-tools)
- [Privacy and Security](#privacy-and-security)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Customers resource in Filament 4 for the Chinook application.
The Customers resource manages customer profiles, purchase history, preferences, and communication for the music
platform.

**🚀 Key Features:**

- **Customer Profile Management**: Comprehensive customer data handling
- **Purchase Analytics**: Detailed purchase history and analytics
- **Communication Tools**: Customer support and messaging
- **Privacy Compliance**: GDPR and data protection features
- **WCAG 2.1 AA Compliance**: Accessible customer management interface

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/CustomerResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Customer Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Personal Information')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->required()
                        ->maxLength(40)
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
                        ->unique(Customer::class, 'email', ignoreRecord: true)
                        ->suffixIcon('heroicon-m-envelope'),

                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(24)
                        ->suffixIcon('heroicon-m-phone'),

                    Forms\Components\DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->maxDate(now()->subYears(13))
                        ->displayFormat('M j, Y'),

                    Forms\Components\Select::make('gender')
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                            'other' => 'Other',
                            'prefer_not_to_say' => 'Prefer not to say',
                        ])
                        ->native(false),
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

            Forms\Components\Section::make('Account Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Account')
                        ->default(true)
                        ->helperText('Inactive accounts cannot make purchases'),

                    Forms\Components\Toggle::make('email_verified')
                        ->label('Email Verified')
                        ->helperText('Has the customer verified their email address?'),

                    Forms\Components\Toggle::make('marketing_consent')
                        ->label('Marketing Consent')
                        ->helperText('Customer has consented to marketing communications'),

                    Forms\Components\Select::make('preferred_language')
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

                    Forms\Components\Select::make('preferred_currency')
                        ->options([
                            'USD' => 'US Dollar',
                            'EUR' => 'Euro',
                            'GBP' => 'British Pound',
                            'CAD' => 'Canadian Dollar',
                            'AUD' => 'Australian Dollar',
                        ])
                        ->default('USD')
                        ->searchable(),

                    Forms\Components\Select::make('subscription_tier')
                        ->options([
                            'free' => 'Free',
                            'premium' => 'Premium',
                            'family' => 'Family',
                            'student' => 'Student',
                        ])
                        ->default('free'),
                ])->columns(3),

            Forms\Components\Section::make('Preferences')
                ->schema([
                    Forms\Components\CheckboxList::make('favorite_genres')
                        ->label('Favorite Genres')
                        ->options([
                            'rock' => 'Rock',
                            'pop' => 'Pop',
                            'jazz' => 'Jazz',
                            'classical' => 'Classical',
                            'electronic' => 'Electronic',
                            'hip_hop' => 'Hip Hop',
                            'country' => 'Country',
                            'blues' => 'Blues',
                        ])
                        ->columns(2)
                        ->gridDirection('row'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Internal Notes')
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Internal notes visible only to staff'),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subscription_tier')
                    ->label('Tier')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'free' => 'gray',
                        'premium' => 'success',
                        'family' => 'info',
                        'student' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->money('USD')
                    ->getStateUsing(fn (Customer $record): float =>
                        $record->invoices()->sum('total')
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoices_count')
                    ->label('Orders')
                    ->counts('invoices')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_purchase_date')
                    ->label('Last Purchase')
                    ->getStateUsing(fn (Customer $record): ?string =>
                        $record->invoices()->latest()->first()?->invoice_date?->format('M j, Y')
                    )
                    ->placeholder('Never'),

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

                Tables\Filters\SelectFilter::make('subscription_tier')
                    ->label('Subscription Tier')
                    ->options([
                        'free' => 'Free',
                        'premium' => 'Premium',
                        'family' => 'Family',
                        'student' => 'Student',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('country')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('total_spent_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_spent')
                                    ->label('Min Spent')
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('max_spent')
                                    ->label('Max Spent')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_spent'],
                                fn (Builder $query, $minSpent): Builder =>
                                    $query->whereHas('invoices', function ($invoiceQuery) use ($minSpent) {
                                        $invoiceQuery->havingRaw('SUM(total) >= ?', [$minSpent]);
                                    }),
                            )
                            ->when(
                                $data['max_spent'],
                                fn (Builder $query, $maxSpent): Builder =>
                                    $query->whereHas('invoices', function ($invoiceQuery) use ($maxSpent) {
                                        $invoiceQuery->havingRaw('SUM(total) <= ?', [$maxSpent]);
                                    }),
                            );
                    }),

                Tables\Filters\Filter::make('recent_customers')
                    ->label('Recent Customers')
                    ->query(fn (Builder $query): Builder =>
                        $query->where('created_at', '>=', now()->subDays(30))
                    )
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('send_email')
                    ->label('Send Email')
                    ->icon('heroicon-o-envelope')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(5),
                    ])
                    ->action(function (Customer $record, array $data) {
                        // Send email logic here
                        Notification::make()
                            ->title('Email sent successfully')
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
                    Tables\Actions\ForceDeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('export_customers')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            // Export logic here
                            return response()->download(
                                app(CustomerExportService::class)->export($records)
                            );
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('send_bulk_email')
                        ->label('Send Bulk Email')
                        ->icon('heroicon-o-envelope')
                        ->form([
                            Forms\Components\TextInput::make('subject')
                                ->required(),
                            Forms\Components\Textarea::make('message')
                                ->required()
                                ->rows(5),
                        ])
                        ->action(function (Collection $records, array $data) {
                            // Bulk email logic here
                            Notification::make()
                                ->title("Email sent to {$records->count()} customers")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\PlaylistsRelationManager::class,
            RelationManagers\SupportTicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->withCount(['invoices'])
            ->with(['invoices' => function ($query) {
                $query->latest()->limit(1);
            }]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('created_at', '>=', now()->subDays(7))->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $newCustomers = static::getModel()::where('created_at', '>=', now()->subDays(7))->count();
        return $newCustomers > 10 ? 'success' : 'primary';
    }
}
```

## Form Components

### Advanced Customer Form Features

```php
<?php
// Custom form components for customer management

class CustomerFormComponents
{
    public static function addressLookup(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('postal_code')
                ->label('Postal Code')
                ->live(onBlur: true)
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    if ($state) {
                        $addressData = app(AddressLookupService::class)->lookup($state);
                        if ($addressData) {
                            $set('city', $addressData['city']);
                            $set('state', $addressData['state']);
                            $set('country', $addressData['country']);
                        }
                    }
                }),

            Forms\Components\TextInput::make('city')
                ->disabled()
                ->dehydrated(),

            Forms\Components\TextInput::make('state')
                ->disabled()
                ->dehydrated(),
        ])->columns(3);
    }

    public static function subscriptionManager(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\Select::make('subscription_tier')
                ->options([
                    'free' => 'Free',
                    'premium' => 'Premium ($9.99/month)',
                    'family' => 'Family ($14.99/month)',
                    'student' => 'Student ($4.99/month)',
                ])
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    $prices = [
                        'free' => 0,
                        'premium' => 9.99,
                        'family' => 14.99,
                        'student' => 4.99,
                    ];
                    $set('monthly_price', $prices[$state] ?? 0);
                }),

            Forms\Components\DatePicker::make('subscription_start_date')
                ->visible(fn (Forms\Get $get) => $get('subscription_tier') !== 'free'),

            Forms\Components\DatePicker::make('subscription_end_date')
                ->visible(fn (Forms\Get $get) => $get('subscription_tier') !== 'free'),
        ])->columns(3);
    }
}
```

## Table Configuration

### Advanced Customer Analytics

```php
<?php
// Enhanced table with customer analytics

class CustomerAnalyticsTable
{
    public static function getAnalyticsColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('customer_lifetime_value')
                ->label('CLV')
                ->money('USD')
                ->getStateUsing(function (Customer $record): float {
                    return $record->calculateLifetimeValue();
                })
                ->sortable()
                ->description('Customer Lifetime Value'),

            Tables\Columns\TextColumn::make('avg_order_value')
                ->label('AOV')
                ->money('USD')
                ->getStateUsing(function (Customer $record): float {
                    return $record->invoices()->avg('total') ?? 0;
                })
                ->description('Average Order Value'),

            Tables\Columns\TextColumn::make('purchase_frequency')
                ->label('Frequency')
                ->getStateUsing(function (Customer $record): string {
                    $daysSinceFirst = $record->invoices()->min('invoice_date')?->diffInDays(now()) ?? 1;
                    $orderCount = $record->invoices()->count();
                    $frequency = $orderCount > 0 ? $daysSinceFirst / $orderCount : 0;
                    return number_format($frequency, 1) . ' days';
                })
                ->description('Days between purchases'),

            Tables\Columns\TextColumn::make('last_activity')
                ->label('Last Activity')
                ->getStateUsing(function (Customer $record): string {
                    $lastLogin = $record->user?->last_login_at;
                    $lastPurchase = $record->invoices()->latest()->first()?->invoice_date;

                    $latest = collect([$lastLogin, $lastPurchase])->filter()->max();
                    return $latest ? $latest->diffForHumans() : 'Never';
                })
                ->sortable(),
        ];
    }
}
```

## Customer Analytics

### Customer Insights Dashboard

```php
<?php
// app/Filament/Resources/CustomerResource/Pages/ViewCustomer.php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Customer Overview')
                    ->schema([
                        Infolists\Components\Split::make([
                            Infolists\Components\Grid::make(2)
                                ->schema([
                                    Infolists\Components\TextEntry::make('full_name')
                                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                        ->weight(FontWeight::Bold),

                                    Infolists\Components\TextEntry::make('email')
                                        ->copyable()
                                        ->icon('heroicon-m-envelope'),

                                    Infolists\Components\TextEntry::make('phone')
                                        ->icon('heroicon-m-phone'),

                                    Infolists\Components\TextEntry::make('subscription_tier')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'premium' => 'success',
                                            'family' => 'info',
                                            'student' => 'warning',
                                            default => 'gray',
                                        }),
                                ]),
                        ])->from('lg'),
                    ]),

                Infolists\Components\Section::make('Purchase Analytics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_spent')
                                    ->label('Total Spent')
                                    ->money('USD')
                                    ->getStateUsing(fn ($record) => $record->invoices()->sum('total')),

                                Infolists\Components\TextEntry::make('order_count')
                                    ->label('Total Orders')
                                    ->getStateUsing(fn ($record) => $record->invoices()->count()),

                                Infolists\Components\TextEntry::make('avg_order_value')
                                    ->label('Avg Order Value')
                                    ->money('USD')
                                    ->getStateUsing(fn ($record) => $record->invoices()->avg('total') ?? 0),

                                Infolists\Components\TextEntry::make('last_purchase')
                                    ->label('Last Purchase')
                                    ->getStateUsing(fn ($record) =>
                                        $record->invoices()->latest()->first()?->invoice_date?->diffForHumans() ?? 'Never'
                                    ),
                            ]),
                    ]),
            ]);
    }
}
```

## Purchase History Management

### Invoice Relation Manager

```php
<?php
// app/Filament/Resources/CustomerResource/RelationManagers/InvoicesRelationManager.php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';
    protected static ?string $recordTitleAttribute = 'invoice_number';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('invoiceLines'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->action(function ($record) {
                        // Refund logic here
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'paid'),
            ])
            ->defaultSort('invoice_date', 'desc');
    }
}
```

## Communication Tools

### Customer Communication Features

```php
<?php
// Customer communication tools

class CustomerCommunicationService
{
    public function sendWelcomeEmail(Customer $customer): void
    {
        Mail::to($customer->email)->send(new WelcomeEmail($customer));
    }

    public function sendSubscriptionReminder(Customer $customer): void
    {
        if ($customer->subscription_tier !== 'free') {
            Mail::to($customer->email)->send(new SubscriptionReminderEmail($customer));
        }
    }

    public function sendPersonalizedRecommendations(Customer $customer): void
    {
        $recommendations = app(RecommendationService::class)->getForCustomer($customer);
        Mail::to($customer->email)->send(new RecommendationsEmail($customer, $recommendations));
    }
}
```

## Privacy and Security

### GDPR Compliance Features

```php
<?php
// GDPR and privacy compliance

class CustomerPrivacyService
{
    public function exportCustomerData(Customer $customer): array
    {
        return [
            'personal_information' => $customer->only([
                'first_name', 'last_name', 'email', 'phone', 'date_of_birth'
            ]),
            'address' => $customer->only([
                'address', 'city', 'state', 'country', 'postal_code'
            ]),
            'preferences' => $customer->only([
                'favorite_genres', 'preferred_language', 'marketing_consent'
            ]),
            'purchase_history' => $customer->invoices()->with('invoiceLines.track')->get(),
            'playlists' => $customer->playlists()->get(),
        ];
    }

    public function anonymizeCustomer(Customer $customer): void
    {
        $customer->update([
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => 'deleted_' . $customer->id . '@example.com',
            'phone' => null,
            'address' => null,
            'date_of_birth' => null,
            'notes' => 'Customer data anonymized on ' . now()->toDateString(),
        ]);
    }
}
```

## Testing

### Customer Resource Testing

```php
<?php
// tests/Feature/Filament/CustomerResourceTest.php

use App\Filament\Resources\CustomerResource;
use App\Models\{Customer, User};
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    public function test_can_render_customer_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(CustomerResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_customer(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-customers');
        $this->actingAs($user);

        $customerData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
        ];

        $response = $this->post(CustomerResource::getUrl('create'), $customerData);

        $this->assertDatabaseHas('customers', $customerData);
    }

    public function test_customer_analytics_calculations(): void
    {
        $customer = Customer::factory()->create();
        Invoice::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
        ]);

        expect($customer->invoices()->sum('total'))->toBe(300.00);
        expect($customer->invoices()->avg('total'))->toBe(100.00);
        expect($customer->invoices()->count())->toBe(3);
    }
}
```

## Best Practices

### Customer Management Guidelines

1. **Privacy First**: Always respect customer privacy and GDPR compliance
2. **Data Security**: Encrypt sensitive customer information
3. **Communication**: Provide clear opt-in/opt-out for marketing
4. **Analytics**: Track meaningful customer metrics for business insights
5. **Support**: Implement efficient customer support workflows
6. **Performance**: Optimize queries for large customer datasets

### Performance Optimization

```php
<?php
// Optimized customer queries

class CustomerResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['invoices', 'playlists'])
            ->with(['invoices' => function ($query) {
                $query->latest()->limit(1)->select(['id', 'customer_id', 'invoice_date', 'total']);
            }])
            ->when(
                auth()->user()->cannot('view-all-customers'),
                fn (Builder $query) => $query->where('created_by', auth()->id())
            );
    }
}
```

## Navigation

**← Previous:** [Media Types Resource Guide](060-media-types-resource.md)
**Next →** [Invoices Resource Guide](080-invoices-resource.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components Guide](120-form-components.md) - Advanced form component patterns
- [Table Features Guide](130-table-features.md) - Advanced table configuration and features

---

*This guide provides comprehensive Filament 4 resource implementation for customer management in the Chinook
application. Each pattern includes privacy compliance, analytics features, and security considerations for robust
customer relationship management.*
