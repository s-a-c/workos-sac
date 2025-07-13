# ChinookInvoices Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Payment Processing](#payment-processing)
- [Invoice Management](#invoice-management)
- [Financial Analytics](#financial-analytics)
- [Tax and Compliance](#tax-and-compliance)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Invoices resource in Filament 4 for the Chinook application.
The Invoices resource manages billing, payment processing, financial reporting, and compliance for the music platform.

**🚀 Key Features:**

- **Invoice Management**: Complete billing lifecycle management
- **Payment Processing**: Multiple payment method support
- **Financial Analytics**: Revenue tracking and reporting
- **Tax Compliance**: Automated tax calculation and reporting
- **WCAG 2.1 AA Compliance**: Accessible financial interface

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/InvoiceResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Invoice Information')
                ->schema([
                    Forms\Components\TextInput::make('invoice_number')
                        ->required()
                        ->unique(Invoice::class, 'invoice_number', ignoreRecord: true)
                        ->default(fn () => 'INV-' . str_pad(Invoice::max('id') + 1, 6, '0', STR_PAD_LEFT))
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\Select::make('customer_id')
                        ->relationship('customer', 'full_name')
                        ->searchable(['first_name', 'last_name', 'email'])
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('first_name')->required(),
                            Forms\Components\TextInput::make('last_name')->required(),
                            Forms\Components\TextInput::make('email')->email()->required(),
                        ]),

                    Forms\Components\DateTimePicker::make('invoice_date')
                        ->required()
                        ->default(now())
                        ->displayFormat('M j, Y g:i A'),

                    Forms\Components\DateTimePicker::make('due_date')
                        ->required()
                        ->default(now()->addDays(30))
                        ->displayFormat('M j, Y g:i A')
                        ->after('invoice_date'),
                ])->columns(2),

            Forms\Components\Section::make('Billing Address')
                ->schema([
                    Forms\Components\TextInput::make('billing_address')
                        ->maxLength(70)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('billing_city')
                        ->maxLength(40),

                    Forms\Components\TextInput::make('billing_state')
                        ->maxLength(40),

                    Forms\Components\TextInput::make('billing_country')
                        ->maxLength(40)
                        ->default('USA'),

                    Forms\Components\TextInput::make('billing_postal_code')
                        ->maxLength(10),
                ])->columns(2),

            Forms\Components\Section::make('Financial Details')
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('tax_rate')
                        ->numeric()
                        ->suffix('%')
                        ->default(8.25)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?float $state) {
                            $subtotal = $get('subtotal') ?? 0;
                            $taxAmount = $subtotal * ($state / 100);
                            $set('tax_amount', $taxAmount);
                            $set('total', $subtotal + $taxAmount);
                        }),

                    Forms\Components\TextInput::make('tax_amount')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('total')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\Select::make('currency')
                        ->options([
                            'USD' => 'US Dollar',
                            'EUR' => 'Euro',
                            'GBP' => 'British Pound',
                            'CAD' => 'Canadian Dollar',
                        ])
                        ->default('USD')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'sent' => 'Sent',
                            'paid' => 'Paid',
                            'overdue' => 'Overdue',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ])
                        ->default('draft')
                        ->required()
                        ->live(),
                ])->columns(3),

            Forms\Components\Section::make('Payment Information')
                ->schema([
                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'credit_card' => 'Credit Card',
                            'paypal' => 'PayPal',
                            'bank_transfer' => 'Bank Transfer',
                            'apple_pay' => 'Apple Pay',
                            'google_pay' => 'Google Pay',
                        ])
                        ->visible(fn (Forms\Get $get) => in_array($get('status'), ['paid', 'refunded'])),

                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference')
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get) => in_array($get('status'), ['paid', 'refunded'])),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Payment Date')
                        ->visible(fn (Forms\Get $get) => $get('status') === 'paid'),

                    Forms\Components\DateTimePicker::make('refunded_at')
                        ->label('Refund Date')
                        ->visible(fn (Forms\Get $get) => $get('status') === 'refunded'),
                ])->columns(2),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->maxLength(1000)
                        ->rows(3)
                        ->helperText('Internal notes for this invoice'),

                    Forms\Components\Textarea::make('customer_notes')
                        ->label('Customer Notes')
                        ->maxLength(500)
                        ->rows(2)
                        ->helperText('Notes visible to the customer'),
                ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable(['customer.first_name', 'customer.last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('invoice_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn (Invoice $record): string => 
                        $record->due_date->isPast() && $record->status !== 'paid' ? 'danger' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        'refunded' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('invoiceLines')
                    ->sortable(),

                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Days Overdue')
                    ->getStateUsing(function (Invoice $record): ?int {
                        if ($record->status === 'paid' || $record->due_date->isFuture()) {
                            return null;
                        }
                        return $record->due_date->diffInDays(now());
                    })
                    ->color('danger')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('customer')
                    ->relationship('customer', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_amount')
                                    ->label('Min Amount')
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('max_amount')
                                    ->label('Max Amount')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_amount'],
                                fn (Builder $query, $minAmount): Builder => 
                                    $query->where('total', '>=', $minAmount),
                            )
                            ->when(
                                $data['max_amount'],
                                fn (Builder $query, $maxAmount): Builder => 
                                    $query->where('total', '<=', $maxAmount),
                            );
                    }),

                Tables\Filters\Filter::make('overdue')
                    ->label('Overdue Invoices')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('due_date', '<', now())
                              ->whereNotIn('status', ['paid', 'cancelled', 'refunded'])
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereMonth('invoice_date', now()->month)
                              ->whereYear('invoice_date', now()->year)
                    )
                    ->toggle(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('send_invoice')
                    ->label('Send Invoice')
                    ->icon('heroicon-o-envelope')
                    ->action(function (Invoice $record) {
                        app(InvoiceService::class)->sendInvoice($record);
                        $record->update(['status' => 'sent']);
                        
                        Notification::make()
                            ->title('Invoice sent successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record) => $record->status === 'draft'),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->form([
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'credit_card' => 'Credit Card',
                                'paypal' => 'PayPal',
                                'bank_transfer' => 'Bank Transfer',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference'),
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (Invoice $record, array $data) {
                        $record->update([
                            'status' => 'paid',
                            'payment_method' => $data['payment_method'],
                            'payment_reference' => $data['payment_reference'],
                            'paid_at' => $data['paid_at'],
                        ]);

                        Notification::make()
                            ->title('Invoice marked as paid')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Invoice $record) => in_array($record->status, ['sent', 'overdue'])),

                Tables\Actions\Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Invoice $record) {
                        return response()->download(
                            app(InvoicePdfService::class)->generate($record)
                        );
                    }),

                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),

                    Tables\Actions\BulkAction::make('send_invoices')
                        ->label('Send Selected')
                        ->icon('heroicon-o-envelope')
                        ->action(function (Collection $records) {
                            $sent = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'draft') {
                                    app(InvoiceService::class)->sendInvoice($record);
                                    $record->update(['status' => 'sent']);
                                    $sent++;
                                }
                            }

                            Notification::make()
                                ->title("{$sent} invoices sent successfully")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('export_invoices')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return response()->download(
                                app(InvoiceExportService::class)->export($records)
                            );
                        }),
                ]),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoiceLinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->withCount(['invoiceLines'])
            ->with(['customer']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'overdue')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdueCount = static::getModel()::where('status', 'overdue')->count();
        return $overdueCount > 0 ? 'danger' : 'primary';
    }
}
```

## Form Components

### Advanced Invoice Form Features

```php
<?php
// Custom form components for invoice management

class InvoiceFormComponents
{
    public static function invoiceCalculator(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\Repeater::make('invoice_lines')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('track_id')
                        ->relationship('track', 'name')
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, ?int $state) {
                            if ($state) {
                                $track = Track::find($state);
                                $set('unit_price', $track->price);
                                $set('quantity', 1);
                            }
                        }),

                    Forms\Components\TextInput::make('quantity')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get, ?int $state) =>
                            $set('total', ($get('unit_price') ?? 0) * ($state ?? 1))
                        ),

                    Forms\Components\TextInput::make('unit_price')
                        ->numeric()
                        ->prefix('$')
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get, ?float $state) =>
                            $set('total', ($state ?? 0) * ($get('quantity') ?? 1))
                        ),

                    Forms\Components\TextInput::make('total')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated(),
                ])
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?array $state) {
                    $subtotal = collect($state)->sum('total');
                    $set('../../subtotal', $subtotal);

                    $taxRate = 8.25; // Get from form or config
                    $taxAmount = $subtotal * ($taxRate / 100);
                    $set('../../tax_amount', $taxAmount);
                    $set('../../total', $subtotal + $taxAmount);
                })
                ->columns(4)
                ->collapsible(),
        ]);
    }

    public static function paymentTerms(): Forms\Components\Component
    {
        return Forms\Components\Select::make('payment_terms')
            ->options([
                'immediate' => 'Due Immediately',
                'net_15' => 'Net 15 Days',
                'net_30' => 'Net 30 Days',
                'net_60' => 'Net 60 Days',
                'net_90' => 'Net 90 Days',
            ])
            ->default('net_30')
            ->live()
            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                $days = match($state) {
                    'immediate' => 0,
                    'net_15' => 15,
                    'net_30' => 30,
                    'net_60' => 60,
                    'net_90' => 90,
                    default => 30,
                };

                $set('due_date', now()->addDays($days));
            });
    }
}
```

## Table Configuration

### Advanced Invoice Analytics

```php
<?php
// Enhanced table with financial analytics

class InvoiceAnalyticsTable
{
    public static function getFinancialColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                Tables\Columns\Layout\Grid::make(3)
                    ->schema([
                        Tables\Columns\TextColumn::make('customer.full_name')
                            ->color('gray')
                            ->size(TextColumnSize::Small),

                        Tables\Columns\TextColumn::make('invoice_date')
                            ->date()
                            ->color('gray')
                            ->size(TextColumnSize::Small),

                        Tables\Columns\TextColumn::make('status')
                            ->badge(),
                    ]),

                Tables\Columns\Layout\Grid::make(2)
                    ->schema([
                        Tables\Columns\TextColumn::make('total')
                            ->money('USD')
                            ->weight(FontWeight::Bold),

                        Tables\Columns\TextColumn::make('due_date')
                            ->date()
                            ->color(fn (Invoice $record): string =>
                                $record->due_date->isPast() && $record->status !== 'paid' ? 'danger' : 'gray'
                            ),
                    ]),
            ])->space(2),
        ];
    }
}
```

## Payment Processing

### Payment Integration Service

```php
<?php
// app/Services/PaymentProcessingService.php

namespace App\Services;

use App\Models\Invoice;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentProcessingService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create payment intent for invoice
     */
    public function createPaymentIntent(Invoice $invoice): PaymentIntent
    {
        return PaymentIntent::create([
            'amount' => $invoice->total * 100, // Convert to cents
            'currency' => strtolower($invoice->currency),
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
            ],
            'description' => "Payment for Invoice {$invoice->invoice_number}",
        ]);
    }

    /**
     * Process successful payment
     */
    public function processSuccessfulPayment(Invoice $invoice, array $paymentData): void
    {
        $invoice->update([
            'status' => 'paid',
            'payment_method' => $paymentData['payment_method'] ?? 'credit_card',
            'payment_reference' => $paymentData['payment_intent_id'] ?? null,
            'paid_at' => now(),
        ]);

        // Send payment confirmation email
        Mail::to($invoice->customer->email)->send(new PaymentConfirmationEmail($invoice));

        // Log payment
        activity()
            ->performedOn($invoice)
            ->causedBy(auth()->user())
            ->log('Payment processed successfully');
    }

    /**
     * Process refund
     */
    public function processRefund(Invoice $invoice, float $amount = null): bool
    {
        try {
            $refundAmount = $amount ?? $invoice->total;

            // Process refund through payment gateway
            $refund = \Stripe\Refund::create([
                'payment_intent' => $invoice->payment_reference,
                'amount' => $refundAmount * 100,
            ]);

            $invoice->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $refundAmount,
                'refund_reference' => $refund->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
```

## Invoice Management

### Invoice Generation Service

```php
<?php
// app/Services/InvoiceGenerationService.php

namespace App\Services;

use App\Models\{Invoice, Customer, Track};

class InvoiceGenerationService
{
    /**
     * Generate invoice from cart items
     */
    public function generateFromCart(Customer $customer, array $cartItems): Invoice
    {
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'billing_address' => $customer->address,
            'billing_city' => $customer->city,
            'billing_state' => $customer->state,
            'billing_country' => $customer->country,
            'billing_postal_code' => $customer->postal_code,
            'currency' => 'USD',
            'status' => 'draft',
        ]);

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $track = Track::find($item['track_id']);
            $lineTotal = $track->price * $item['quantity'];

            $invoice->invoiceLines()->create([
                'track_id' => $track->id,
                'quantity' => $item['quantity'],
                'unit_price' => $track->price,
                'total' => $lineTotal,
            ]);

            $subtotal += $lineTotal;
        }

        $taxRate = $this->calculateTaxRate($customer);
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);

        return $invoice;
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $month = now()->format('m');
        $sequence = Invoice::whereYear('created_at', $year)
                          ->whereMonth('created_at', $month)
                          ->count() + 1;

        return "INV-{$year}{$month}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate tax rate based on customer location
     */
    private function calculateTaxRate(Customer $customer): float
    {
        // Simplified tax calculation - in reality, use a tax service
        return match($customer->state) {
            'CA' => 8.25,
            'NY' => 8.00,
            'TX' => 6.25,
            'FL' => 6.00,
            default => 0.00,
        };
    }
}
```

## Financial Analytics

### Revenue Analytics Dashboard

```php
<?php
// app/Filament/Resources/InvoiceResource/Widgets/RevenueOverview.php

namespace App\Filament\Resources\InvoiceResource\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $thisMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total');

        $lastMonth = Invoice::where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('total');

        $monthlyGrowth = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : 0;

        $overdueAmount = Invoice::where('status', 'overdue')->sum('total');
        $overdueCount = Invoice::where('status', 'overdue')->count();

        return [
            Stat::make('Monthly Revenue', '$' . number_format($thisMonth, 2))
                ->description($monthlyGrowth >= 0 ?
                    number_format($monthlyGrowth, 1) . '% increase' :
                    number_format(abs($monthlyGrowth), 1) . '% decrease'
                )
                ->descriptionIcon($monthlyGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Outstanding Invoices', $overdueCount)
                ->description('$' . number_format($overdueAmount, 2) . ' overdue')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'warning' : 'success'),

            Stat::make('Average Order Value', '$' . number_format(
                Invoice::where('status', 'paid')
                    ->whereMonth('paid_at', now()->month)
                    ->avg('total') ?? 0, 2
            ))
                ->description('This month')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }
}
```

## Tax and Compliance

### Tax Calculation Service

```php
<?php
// app/Services/TaxCalculationService.php

namespace App\Services;

use App\Models\{Invoice, Customer};

class TaxCalculationService
{
    /**
     * Calculate tax for invoice
     */
    public function calculateTax(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $taxRate = $this->getTaxRate($customer);
        $taxAmount = $invoice->subtotal * ($taxRate / 100);

        return [
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_jurisdiction' => $this->getTaxJurisdiction($customer),
        ];
    }

    /**
     * Get tax rate based on customer location
     */
    private function getTaxRate(Customer $customer): float
    {
        // Integration with tax service like TaxJar or Avalara
        return app(TaxServiceProvider::class)->getTaxRate([
            'country' => $customer->country,
            'state' => $customer->state,
            'city' => $customer->city,
            'postal_code' => $customer->postal_code,
        ]);
    }

    /**
     * Generate tax report
     */
    public function generateTaxReport(string $period): array
    {
        $invoices = Invoice::where('status', 'paid')
            ->whereBetween('paid_at', $this->getPeriodDates($period))
            ->get();

        return [
            'period' => $period,
            'total_revenue' => $invoices->sum('subtotal'),
            'total_tax_collected' => $invoices->sum('tax_amount'),
            'tax_by_jurisdiction' => $invoices->groupBy('tax_jurisdiction')
                ->map(fn ($group) => $group->sum('tax_amount')),
        ];
    }
}
```

## Testing

### Invoice Resource Testing

```php
<?php
// tests/Feature/Filament/InvoiceResourceTest.php

use App\Filament\Resources\InvoiceResource;
use App\Models\{Invoice, Customer, User};
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    public function test_can_render_invoice_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(InvoiceResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_invoice(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-invoices');
        $customer = Customer::factory()->create();
        $this->actingAs($user);

        $invoiceData = [
            'customer_id' => $customer->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100.00,
            'tax_rate' => 8.25,
            'tax_amount' => 8.25,
            'total' => 108.25,
        ];

        $response = $this->post(InvoiceResource::getUrl('create'), $invoiceData);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'total' => 108.25,
        ]);
    }

    public function test_invoice_calculations_are_correct(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 100.00,
            'tax_rate' => 8.25,
        ]);

        expect($invoice->tax_amount)->toBe(8.25);
        expect($invoice->total)->toBe(108.25);
    }
}
```

## Best Practices

### Invoice Management Guidelines

1. **Automated Numbering**: Use sequential invoice numbering
2. **Tax Compliance**: Integrate with professional tax services
3. **Payment Security**: Use secure payment processors
4. **Audit Trail**: Maintain complete financial audit trails
5. **Backup Systems**: Regular backup of financial data
6. **Compliance**: Follow accounting standards and regulations

### Performance Optimization

```php
<?php
// Optimized invoice queries

class InvoiceResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['invoiceLines'])
            ->with(['customer:id,first_name,last_name,email'])
            ->when(
                auth()->user()->cannot('view-all-invoices'),
                fn (Builder $query) => $query->where('created_by', auth()->id())
            );
    }
}
```

## Navigation

**← Previous:** [Customers Resource Guide](070-customers-resource.md)
**Next →** [Invoice Lines Resource Guide](090-invoice-lines-resource.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components](120-form-components.md) - Advanced form patterns
- [Table Features Guide](130-table-features.md) - Advanced table configuration and features

---

*This guide provides comprehensive Filament 4 resource implementation for invoice management in the Chinook application.
Each pattern includes payment processing, tax compliance, and financial analytics for robust billing functionality.*
