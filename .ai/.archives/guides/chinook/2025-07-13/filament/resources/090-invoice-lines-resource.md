# Invoice Lines Resource Guide

## Table of Contents

- [Overview](#overview)
- [Resource Configuration](#resource-configuration)
- [Form Components](#form-components)
- [Table Configuration](#table-configuration)
- [Line Item Management](#line-item-management)
- [Pricing and Discounts](#pricing-and-discounts)
- [Inventory Integration](#inventory-integration)
- [Analytics and Reporting](#analytics-and-reporting)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers the comprehensive implementation of the Invoice Lines resource in Filament 4 for the Chinook
application. The Invoice Lines resource manages individual line items within invoices, including track purchases,
pricing, discounts, and detailed transaction analytics.

**🚀 Key Features:**

- **Line Item Management**: Detailed invoice line item handling
- **Dynamic Pricing**: Real-time price calculations and discounts
- **Track Integration**: Seamless music track purchasing
- **Analytics**: Detailed sales analytics and reporting
- **WCAG 2.1 AA Compliance**: Accessible line item interface

## Resource Configuration

### Basic Resource Setup

```php
<?php
// app/Filament/Resources/InvoiceLineResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceLineResource\Pages;
use App\Models\InvoiceLine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceLineResource extends Resource
{
    protected static ?string $model = InvoiceLine::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Line Item Information')
                ->schema([
                    Forms\Components\Select::make('invoice_id')
                        ->relationship('invoice', 'invoice_number')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            Forms\Components\Select::make('customer_id')
                                ->relationship('customer', 'full_name')
                                ->required(),
                            Forms\Components\DateTimePicker::make('invoice_date')
                                ->default(now())
                                ->required(),
                        ]),

                    Forms\Components\Select::make('track_id')
                        ->relationship('track', 'name')
                        ->searchable(['name', 'album.title', 'album.artist.name'])
                        ->getOptionLabelFromRecordUsing(fn ($record): string => 
                            "{$record->name} - {$record->album->artist->name}"
                        )
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, ?int $state) {
                            if ($state) {
                                $track = \App\Models\Track::find($state);
                                $set('unit_price', $track->price);
                                $set('description', $track->name);
                            }
                        }),

                    Forms\Components\TextInput::make('description')
                        ->maxLength(255)
                        ->helperText('Auto-filled from track name, can be customized'),
                ])->columns(2),

            Forms\Components\Section::make('Pricing Details')
                ->schema([
                    Forms\Components\TextInput::make('quantity')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?int $state) {
                            $unitPrice = $get('unit_price') ?? 0;
                            $discount = $get('discount_amount') ?? 0;
                            $subtotal = ($unitPrice * ($state ?? 1)) - $discount;
                            $set('total', max(0, $subtotal));
                        }),

                    Forms\Components\TextInput::make('unit_price')
                        ->numeric()
                        ->prefix('$')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?float $state) {
                            $quantity = $get('quantity') ?? 1;
                            $discount = $get('discount_amount') ?? 0;
                            $subtotal = (($state ?? 0) * $quantity) - $discount;
                            $set('total', max(0, $subtotal));
                        }),

                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Discount Amount')
                        ->numeric()
                        ->prefix('$')
                        ->default(0)
                        ->minValue(0)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?float $state) {
                            $quantity = $get('quantity') ?? 1;
                            $unitPrice = $get('unit_price') ?? 0;
                            $subtotal = ($unitPrice * $quantity) - ($state ?? 0);
                            $set('total', max(0, $subtotal));
                        }),

                    Forms\Components\TextInput::make('discount_percentage')
                        ->label('Discount %')
                        ->numeric()
                        ->suffix('%')
                        ->minValue(0)
                        ->maxValue(100)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?float $state) {
                            if ($state) {
                                $quantity = $get('quantity') ?? 1;
                                $unitPrice = $get('unit_price') ?? 0;
                                $discountAmount = ($unitPrice * $quantity) * (($state ?? 0) / 100);
                                $set('discount_amount', $discountAmount);
                                $set('total', ($unitPrice * $quantity) - $discountAmount);
                            }
                        }),

                    Forms\Components\TextInput::make('total')
                        ->numeric()
                        ->prefix('$')
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Calculated automatically'),
                ])->columns(3),

            Forms\Components\Section::make('Additional Information')
                ->schema([
                    Forms\Components\Select::make('license_type')
                        ->label('License Type')
                        ->options([
                            'standard' => 'Standard License',
                            'extended' => 'Extended License',
                            'commercial' => 'Commercial License',
                            'sync' => 'Sync License',
                        ])
                        ->default('standard'),

                    Forms\Components\Toggle::make('is_gift')
                        ->label('Gift Purchase')
                        ->helperText('Is this a gift for another user?'),

                    Forms\Components\TextInput::make('gift_recipient_email')
                        ->label('Gift Recipient Email')
                        ->email()
                        ->visible(fn (Forms\Get $get) => $get('is_gift')),

                    Forms\Components\Textarea::make('notes')
                        ->maxLength(500)
                        ->rows(2)
                        ->helperText('Internal notes for this line item'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->url(fn (InvoiceLine $record): string => 
                        route('filament.admin.resources.invoices.view', $record->invoice)
                    ),

                Tables\Columns\TextColumn::make('track.name')
                    ->label('Track')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (InvoiceLine $record): ?string {
                        return $record->track ? 
                            "{$record->track->name} by {$record->track->album->artist->name}" : 
                            null;
                    }),

                Tables\Columns\TextColumn::make('track.album.artist.name')
                    ->label('Artist')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->alignment(Alignment::Center),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('USD')
                    ->sortable()
                    ->color('warning')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('success'),

                Tables\Columns\TextColumn::make('license_type')
                    ->label('License')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'standard' => 'gray',
                        'extended' => 'info',
                        'commercial' => 'warning',
                        'sync' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_gift')
                    ->label('Gift')
                    ->boolean()
                    ->trueIcon('heroicon-o-gift')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success'),

                Tables\Columns\TextColumn::make('invoice.customer.full_name')
                    ->label('Customer')
                    ->searchable(['invoice.customer.first_name', 'invoice.customer.last_name'])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('invoice')
                    ->relationship('invoice', 'invoice_number')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('track')
                    ->relationship('track', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('license_type')
                    ->options([
                        'standard' => 'Standard License',
                        'extended' => 'Extended License',
                        'commercial' => 'Commercial License',
                        'sync' => 'Sync License',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('min_price')
                                    ->label('Min Price')
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('max_price')
                                    ->label('Max Price')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_price'],
                                fn (Builder $query, $minPrice): Builder => 
                                    $query->where('total', '>=', $minPrice),
                            )
                            ->when(
                                $data['max_price'],
                                fn (Builder $query, $maxPrice): Builder => 
                                    $query->where('total', '<=', $maxPrice),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('is_gift')
                    ->label('Gift Purchases')
                    ->trueLabel('Gift purchases only')
                    ->falseLabel('Regular purchases only')
                    ->native(false),

                Tables\Filters\Filter::make('has_discount')
                    ->label('Discounted Items')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('discount_amount', '>', 0)
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('apply_discount')
                    ->label('Apply Discount')
                    ->icon('heroicon-o-tag')
                    ->form([
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed Amount',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->required()
                            ->suffix(fn (Forms\Get $get) => 
                                $get('discount_type') === 'percentage' ? '%' : '$'
                            ),

                        Forms\Components\Textarea::make('discount_reason')
                            ->label('Reason for Discount')
                            ->maxLength(255),
                    ])
                    ->action(function (InvoiceLine $record, array $data) {
                        $discountAmount = $data['discount_type'] === 'percentage'
                            ? ($record->unit_price * $record->quantity) * ($data['discount_value'] / 100)
                            : $data['discount_value'];

                        $record->update([
                            'discount_amount' => $discountAmount,
                            'total' => ($record->unit_price * $record->quantity) - $discountAmount,
                            'notes' => ($record->notes ?? '') . "\nDiscount applied: {$data['discount_reason']}",
                        ]);

                        Notification::make()
                            ->title('Discount applied successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('bulk_discount')
                        ->label('Apply Bulk Discount')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Forms\Components\Select::make('discount_type')
                                ->options([
                                    'percentage' => 'Percentage',
                                    'fixed' => 'Fixed Amount per Item',
                                ])
                                ->required(),

                            Forms\Components\TextInput::make('discount_value')
                                ->numeric()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $record) {
                                $discountAmount = $data['discount_type'] === 'percentage'
                                    ? ($record->unit_price * $record->quantity) * ($data['discount_value'] / 100)
                                    : $data['discount_value'];

                                $record->update([
                                    'discount_amount' => $discountAmount,
                                    'total' => ($record->unit_price * $record->quantity) - $discountAmount,
                                ]);
                            }

                            Notification::make()
                                ->title("Discount applied to {$records->count()} items")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('export_lines')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            return response()->download(
                                app(InvoiceLineExportService::class)->export($records)
                            );
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceLines::route('/'),
            'create' => Pages\CreateInvoiceLine::route('/create'),
            'view' => Pages\ViewInvoiceLine::route('/{record}'),
            'edit' => Pages\EditInvoiceLine::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['invoice.customer', 'track.album.artist']);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereHas('invoice', function ($query) {
            $query->where('status', 'paid')
                  ->whereDate('paid_at', today());
        })->count();
    }
}
```

## Form Components

### Advanced Line Item Components

```php
<?php
// Custom form components for invoice line management

class InvoiceLineFormComponents
{
    public static function trackSelector(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\Select::make('track_id')
                ->relationship('track', 'name')
                ->searchable(['name', 'album.title', 'album.artist.name'])
                ->getOptionLabelFromRecordUsing(fn ($record): string =>
                    "{$record->name} - {$record->album->artist->name} ({$record->album->title})"
                )
                ->getSearchResultsUsing(function (string $search): array {
                    return Track::where('name', 'like', "%{$search}%")
                        ->orWhereHas('album', function ($query) use ($search) {
                            $query->where('title', 'like', "%{$search}%")
                                  ->orWhereHas('artist', function ($artistQuery) use ($search) {
                                      $artistQuery->where('name', 'like', "%{$search}%");
                                  });
                        })
                        ->with(['album.artist'])
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($track) => [
                            $track->id => "{$track->name} - {$track->album->artist->name}"
                        ])
                        ->toArray();
                })
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?int $state) {
                    if ($state) {
                        $track = Track::with(['album.artist'])->find($state);
                        $set('unit_price', $track->price);
                        $set('description', $track->name);
                        $set('license_type', 'standard');
                    }
                }),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('preview_track')
                    ->label('Preview')
                    ->icon('heroicon-o-play')
                    ->action(function (Forms\Get $get) {
                        $trackId = $get('track_id');
                        if ($trackId) {
                            // Implement track preview logic
                            Notification::make()
                                ->title('Track preview opened')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn (Forms\Get $get) => $get('track_id')),
            ]),
        ])->columns(2);
    }

    public static function pricingCalculator(): Forms\Components\Component
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('quantity')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->live(),

            Forms\Components\TextInput::make('unit_price')
                ->numeric()
                ->prefix('$')
                ->live(),

            Forms\Components\Select::make('discount_type')
                ->options([
                    'none' => 'No Discount',
                    'percentage' => 'Percentage',
                    'fixed' => 'Fixed Amount',
                ])
                ->default('none')
                ->live(),

            Forms\Components\TextInput::make('discount_value')
                ->numeric()
                ->visible(fn (Forms\Get $get) => $get('discount_type') !== 'none')
                ->suffix(fn (Forms\Get $get) =>
                    $get('discount_type') === 'percentage' ? '%' : '$'
                )
                ->live(),

            Forms\Components\Placeholder::make('calculated_total')
                ->label('Total')
                ->content(function (Forms\Get $get): string {
                    $quantity = $get('quantity') ?? 1;
                    $unitPrice = $get('unit_price') ?? 0;
                    $discountType = $get('discount_type') ?? 'none';
                    $discountValue = $get('discount_value') ?? 0;

                    $subtotal = $quantity * $unitPrice;

                    $discount = match($discountType) {
                        'percentage' => $subtotal * ($discountValue / 100),
                        'fixed' => $discountValue,
                        default => 0,
                    };

                    $total = max(0, $subtotal - $discount);

                    return '$' . number_format($total, 2);
                }),
        ])->columns(3);
    }
}
```

## Table Configuration

### Advanced Analytics Columns

```php
<?php
// Enhanced table with sales analytics

class InvoiceLineAnalyticsTable
{
    public static function getSalesColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\TextColumn::make('track.name')
                    ->weight(FontWeight::Bold)
                    ->limit(40),

                Tables\Columns\Layout\Grid::make(3)
                    ->schema([
                        Tables\Columns\TextColumn::make('track.album.artist.name')
                            ->color('gray')
                            ->size(TextColumnSize::Small),

                        Tables\Columns\TextColumn::make('invoice.invoice_number')
                            ->color('gray')
                            ->size(TextColumnSize::Small),

                        Tables\Columns\TextColumn::make('license_type')
                            ->badge(),
                    ]),

                Tables\Columns\Layout\Grid::make(4)
                    ->schema([
                        Tables\Columns\TextColumn::make('quantity')
                            ->label('Qty')
                            ->badge()
                            ->color('info'),

                        Tables\Columns\TextColumn::make('unit_price')
                            ->money('USD'),

                        Tables\Columns\TextColumn::make('discount_amount')
                            ->money('USD')
                            ->color('warning')
                            ->placeholder('—'),

                        Tables\Columns\TextColumn::make('total')
                            ->money('USD')
                            ->weight(FontWeight::Bold)
                            ->color('success'),
                    ]),
            ])->space(2),
        ];
    }
}
```

## Line Item Management

### Line Item Service

```php
<?php
// app/Services/InvoiceLineService.php

namespace App\Services;

use App\Models\{InvoiceLine, Track, Invoice};

class InvoiceLineService
{
    /**
     * Add track to invoice
     */
    public function addTrackToInvoice(Invoice $invoice, Track $track, array $options = []): InvoiceLine
    {
        $quantity = $options['quantity'] ?? 1;
        $unitPrice = $options['unit_price'] ?? $track->price;
        $licenseType = $options['license_type'] ?? 'standard';

        // Apply license type pricing
        $unitPrice = $this->applyLicensePricing($unitPrice, $licenseType);

        $invoiceLine = $invoice->invoiceLines()->create([
            'track_id' => $track->id,
            'description' => $track->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'license_type' => $licenseType,
            'total' => $unitPrice * $quantity,
        ]);

        // Update invoice totals
        $this->updateInvoiceTotals($invoice);

        return $invoiceLine;
    }

    /**
     * Apply bulk discount to invoice lines
     */
    public function applyBulkDiscount(Collection $invoiceLines, float $discountPercentage): void
    {
        foreach ($invoiceLines as $line) {
            $discountAmount = ($line->unit_price * $line->quantity) * ($discountPercentage / 100);

            $line->update([
                'discount_amount' => $discountAmount,
                'total' => ($line->unit_price * $line->quantity) - $discountAmount,
            ]);
        }

        // Update invoice totals for affected invoices
        $invoices = $invoiceLines->pluck('invoice')->unique();
        foreach ($invoices as $invoice) {
            $this->updateInvoiceTotals($invoice);
        }
    }

    /**
     * Apply license type pricing
     */
    private function applyLicensePricing(float $basePrice, string $licenseType): float
    {
        return match($licenseType) {
            'extended' => $basePrice * 1.5,
            'commercial' => $basePrice * 2.0,
            'sync' => $basePrice * 3.0,
            default => $basePrice,
        };
    }

    /**
     * Update invoice totals
     */
    private function updateInvoiceTotals(Invoice $invoice): void
    {
        $subtotal = $invoice->invoiceLines()->sum('total');
        $taxAmount = $subtotal * ($invoice->tax_rate / 100);
        $total = $subtotal + $taxAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }
}
```

## Pricing and Discounts

### Dynamic Pricing System

```php
<?php
// app/Services/PricingService.php

namespace App\Services;

use App\Models\{Track, Customer, InvoiceLine};

class PricingService
{
    /**
     * Calculate dynamic pricing for track
     */
    public function calculatePrice(Track $track, Customer $customer, array $options = []): array
    {
        $basePrice = $track->price;
        $licenseType = $options['license_type'] ?? 'standard';
        $quantity = $options['quantity'] ?? 1;

        // Apply license multiplier
        $licenseMultiplier = $this->getLicenseMultiplier($licenseType);
        $adjustedPrice = $basePrice * $licenseMultiplier;

        // Apply customer tier discount
        $customerDiscount = $this->getCustomerDiscount($customer);

        // Apply volume discount
        $volumeDiscount = $this->getVolumeDiscount($quantity);

        // Apply promotional discount
        $promoDiscount = $this->getPromotionalDiscount($track, $customer);

        $totalDiscount = max($customerDiscount, $volumeDiscount, $promoDiscount);
        $finalPrice = $adjustedPrice * (1 - $totalDiscount);

        return [
            'base_price' => $basePrice,
            'license_multiplier' => $licenseMultiplier,
            'adjusted_price' => $adjustedPrice,
            'customer_discount' => $customerDiscount,
            'volume_discount' => $volumeDiscount,
            'promotional_discount' => $promoDiscount,
            'total_discount' => $totalDiscount,
            'final_price' => $finalPrice,
            'savings' => $adjustedPrice - $finalPrice,
        ];
    }

    private function getLicenseMultiplier(string $licenseType): float
    {
        return match($licenseType) {
            'extended' => 1.5,
            'commercial' => 2.0,
            'sync' => 3.0,
            default => 1.0,
        };
    }

    private function getCustomerDiscount(Customer $customer): float
    {
        return match($customer->subscription_tier) {
            'premium' => 0.10, // 10% discount
            'family' => 0.15,  // 15% discount
            'student' => 0.20, // 20% discount
            default => 0.0,
        };
    }

    private function getVolumeDiscount(int $quantity): float
    {
        return match(true) {
            $quantity >= 50 => 0.20, // 20% for 50+
            $quantity >= 20 => 0.15, // 15% for 20+
            $quantity >= 10 => 0.10, // 10% for 10+
            $quantity >= 5 => 0.05,  // 5% for 5+
            default => 0.0,
        };
    }

    private function getPromotionalDiscount(Track $track, Customer $customer): float
    {
        // Check for active promotions
        $activePromotions = Promotion::active()
            ->where(function ($query) use ($track, $customer) {
                $query->whereHas('tracks', fn ($q) => $q->where('track_id', $track->id))
                      ->orWhereHas('customers', fn ($q) => $q->where('customer_id', $customer->id))
                      ->orWhere('applies_to_all', true);
            })
            ->get();

        return $activePromotions->max('discount_percentage') / 100 ?? 0.0;
    }
}
```

## Inventory Integration

### Stock Management

```php
<?php
// app/Services/InventoryService.php

namespace App\Services;

use App\Models\{Track, InvoiceLine};

class InventoryService
{
    /**
     * Check track availability
     */
    public function checkAvailability(Track $track, int $quantity = 1): array
    {
        return [
            'available' => $track->is_available,
            'in_stock' => $track->stock_quantity >= $quantity,
            'stock_level' => $track->stock_quantity,
            'max_quantity' => min($track->stock_quantity, 100), // Max 100 per purchase
            'estimated_restock' => $track->estimated_restock_date,
        ];
    }

    /**
     * Reserve track inventory
     */
    public function reserveInventory(Track $track, int $quantity): bool
    {
        if ($track->stock_quantity >= $quantity) {
            $track->decrement('stock_quantity', $quantity);
            $track->increment('reserved_quantity', $quantity);

            // Log inventory movement
            $track->inventoryMovements()->create([
                'type' => 'reservation',
                'quantity' => $quantity,
                'remaining_stock' => $track->stock_quantity,
                'notes' => 'Reserved for purchase',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Release reserved inventory
     */
    public function releaseReservation(Track $track, int $quantity): void
    {
        $track->increment('stock_quantity', $quantity);
        $track->decrement('reserved_quantity', $quantity);

        $track->inventoryMovements()->create([
            'type' => 'release',
            'quantity' => $quantity,
            'remaining_stock' => $track->stock_quantity,
            'notes' => 'Reservation released',
        ]);
    }
}
```

## Analytics and Reporting

### Sales Analytics

```php
<?php
// app/Services/SalesAnalyticsService.php

namespace App\Services;

use App\Models\InvoiceLine;
use Illuminate\Support\Collection;

class SalesAnalyticsService
{
    /**
     * Get top selling tracks
     */
    public function getTopSellingTracks(int $limit = 10, string $period = '30 days'): Collection
    {
        return InvoiceLine::whereHas('invoice', function ($query) use ($period) {
                $query->where('status', 'paid')
                      ->where('paid_at', '>=', now()->sub($period));
            })
            ->with(['track.album.artist'])
            ->selectRaw('track_id, SUM(quantity) as total_sold, SUM(total) as total_revenue')
            ->groupBy('track_id')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get revenue by license type
     */
    public function getRevenueByLicenseType(string $period = '30 days'): Collection
    {
        return InvoiceLine::whereHas('invoice', function ($query) use ($period) {
                $query->where('status', 'paid')
                      ->where('paid_at', '>=', now()->sub($period));
            })
            ->selectRaw('license_type, SUM(total) as revenue, COUNT(*) as sales_count')
            ->groupBy('license_type')
            ->orderBy('revenue', 'desc')
            ->get();
    }

    /**
     * Get average order value trends
     */
    public function getAverageOrderValueTrends(int $days = 30): Collection
    {
        return InvoiceLine::whereHas('invoice', function ($query) use ($days) {
                $query->where('status', 'paid')
                      ->where('paid_at', '>=', now()->subDays($days));
            })
            ->selectRaw('DATE(created_at) as date, AVG(total) as avg_value, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
```

## Testing

### Invoice Line Testing

```php
<?php
// tests/Feature/Filament/InvoiceLineResourceTest.php

use App\Filament\Resources\InvoiceLineResource;
use App\Models\{InvoiceLine, Invoice, Track, User};
use Tests\TestCase;

class InvoiceLineResourceTest extends TestCase
{
    public function test_can_render_invoice_line_index_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(InvoiceLineResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_can_create_invoice_line(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create-invoice-lines');
        $invoice = Invoice::factory()->create();
        $track = Track::factory()->create(['price' => 1.99]);
        $this->actingAs($user);

        $lineData = [
            'invoice_id' => $invoice->id,
            'track_id' => $track->id,
            'quantity' => 2,
            'unit_price' => 1.99,
            'total' => 3.98,
        ];

        $response = $this->post(InvoiceLineResource::getUrl('create'), $lineData);

        $this->assertDatabaseHas('invoice_lines', $lineData);
    }

    public function test_pricing_calculations_are_correct(): void
    {
        $track = Track::factory()->create(['price' => 10.00]);
        $line = InvoiceLine::factory()->create([
            'track_id' => $track->id,
            'quantity' => 3,
            'unit_price' => 10.00,
            'discount_amount' => 5.00,
        ]);

        expect($line->total)->toBe(25.00); // (10 * 3) - 5
    }
}
```

## Best Practices

### Invoice Line Guidelines

1. **Accurate Pricing**: Ensure pricing calculations are precise and auditable
2. **Inventory Management**: Track stock levels and reservations properly
3. **License Compliance**: Implement proper licensing for different use cases
4. **Discount Tracking**: Maintain clear audit trails for all discounts
5. **Performance**: Optimize queries for large transaction volumes
6. **Data Integrity**: Validate all calculations and maintain consistency

### Performance Optimization

```php
<?php
// Optimized invoice line queries

class InvoiceLineResource extends Resource
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'invoice:id,invoice_number,customer_id',
                'invoice.customer:id,first_name,last_name',
                'track:id,name,album_id,price',
                'track.album:id,title,artist_id',
                'track.album.artist:id,name'
            ])
            ->when(
                auth()->user()->cannot('view-all-invoice-lines'),
                fn (Builder $query) => $query->whereHas('invoice', function ($invoiceQuery) {
                    $invoiceQuery->where('created_by', auth()->id());
                })
            );
    }
}
```

## Navigation

**← Previous:** [Invoices Resource Guide](080-invoices-resource.md)
**Next →** [Employees Resource Guide](100-employees-resource.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components Guide](120-form-components.md) - Advanced form component patterns
- [Table Features Guide](130-table-features.md) - Advanced table configuration and features

---

*This guide provides comprehensive Filament 4 resource implementation for invoice line management in the Chinook
application. Each pattern includes pricing calculations, inventory management, and analytics features for robust
transaction handling.*
