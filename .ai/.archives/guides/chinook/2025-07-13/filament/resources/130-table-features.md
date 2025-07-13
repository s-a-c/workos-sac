# Table Features Guide

## Table of Contents

- [Overview](#overview)
- [Basic Table Configuration](#basic-table-configuration)
- [Advanced Column Types](#advanced-column-types)
- [Custom Table Actions](#custom-table-actions)
- [Filtering and Search](#filtering-and-search)
- [Sorting and Grouping](#sorting-and-grouping)
- [Bulk Operations](#bulk-operations)
- [Performance Optimization](#performance-optimization)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive table feature implementation in Filament 4 for the Chinook application. It provides
advanced table patterns, custom columns, filtering strategies, and performance optimizations for building robust data
interfaces.

**🚀 Key Features:**

- **Advanced Columns**: Rich column types and formatting
- **Smart Filtering**: Dynamic and contextual filters
- **Bulk Operations**: Efficient mass data operations
- **Performance**: Optimized queries and pagination
- **WCAG 2.1 AA Compliance**: Accessible table interfaces

## Basic Table Configuration

### Standard Table Setup

```php
<?php
// app/Filament/Components/StandardTables.php

namespace App\Filament\Components;

use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class StandardTables
{
    /**
     * Standard table configuration
     */
    public static function baseTable(): Tables\Table
    {
        return Tables\Table::make()
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->extremePaginationLinks()
            ->emptyStateHeading('No records found')
            ->emptyStateDescription('Try adjusting your search or filter criteria.')
            ->emptyStateIcon('heroicon-o-document-magnifying-glass');
    }

    /**
     * Responsive table columns
     */
    public static function responsiveColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable()
                ->weight(FontWeight::Bold)
                ->wrap(),

            Tables\Columns\TextColumn::make('email')
                ->label('Email')
                ->sortable()
                ->searchable()
                ->copyable()
                ->copyMessage('Email copied')
                ->icon('heroicon-m-envelope')
                ->toggleable(),

            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable()
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Updated')
                ->dateTime()
                ->sortable()
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * Standard table actions
     */
    public static function standardActions(): array
    {
        return [
            Tables\Actions\ViewAction::make()
                ->iconButton()
                ->tooltip('View details'),

            Tables\Actions\EditAction::make()
                ->iconButton()
                ->tooltip('Edit record'),

            Tables\Actions\DeleteAction::make()
                ->iconButton()
                ->tooltip('Delete record')
                ->requiresConfirmation(),
        ];
    }

    /**
     * Standard bulk actions
     */
    public static function standardBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()
                    ->requiresConfirmation(),

                Tables\Actions\BulkAction::make('export')
                    ->label('Export Selected')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (Collection $records) {
                        return response()->download(
                            app(ExportService::class)->export($records)
                        );
                    }),
            ]),
        ];
    }
}
```

## Advanced Column Types

### Specialized Column Components

```php
<?php
// app/Filament/Components/AdvancedColumns.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Support\Enums\FontWeight;

class AdvancedColumns
{
    /**
     * Status badge column
     */
    public static function statusBadge(string $name, array $colors = []): Tables\Columns\TextColumn
    {
        $defaultColors = [
            'active' => 'success',
            'inactive' => 'danger',
            'pending' => 'warning',
            'draft' => 'gray',
        ];

        return Tables\Columns\TextColumn::make($name)
            ->label(str($name)->title())
            ->badge()
            ->color(fn (string $state): string => $colors[$state] ?? $defaultColors[$state] ?? 'gray')
            ->formatStateUsing(fn (string $state): string => str($state)->title());
    }

    /**
     * Progress bar column
     */
    public static function progressBar(string $name, int $max = 100): Tables\Columns\ViewColumn
    {
        return Tables\Columns\ViewColumn::make($name)
            ->label(str($name)->title())
            ->view('filament.tables.columns.progress-bar')
            ->viewData(['max' => $max]);
    }

    /**
     * Rating stars column
     */
    public static function ratingStars(string $name, int $maxRating = 5): Tables\Columns\ViewColumn
    {
        return Tables\Columns\ViewColumn::make($name)
            ->label('Rating')
            ->view('filament.tables.columns.rating-stars')
            ->viewData(['maxRating' => $maxRating]);
    }

    /**
     * Currency column with formatting
     */
    public static function currency(string $name, string $currency = 'USD'): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($name)
            ->label(str($name)->title())
            ->money($currency)
            ->sortable()
            ->weight(FontWeight::Bold)
            ->color('success');
    }

    /**
     * File size column
     */
    public static function fileSize(string $name): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($name)
            ->label('File Size')
            ->formatStateUsing(function ($state): string {
                if (!$state) return '—';
                
                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $bytes = max($state, 0);
                $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
                $pow = min($pow, count($units) - 1);
                
                $bytes /= pow(1024, $pow);
                
                return round($bytes, 2) . ' ' . $units[$pow];
            })
            ->sortable();
    }

    /**
     * Duration column
     */
    public static function duration(string $name): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($name)
            ->label('Duration')
            ->formatStateUsing(function ($state): string {
                if (!$state) return '—';
                
                $seconds = $state / 1000; // Convert milliseconds to seconds
                $minutes = floor($seconds / 60);
                $seconds = $seconds % 60;
                
                return sprintf('%d:%02d', $minutes, $seconds);
            })
            ->sortable();
    }

    /**
     * Image thumbnail column
     */
    public static function imageThumbnail(string $name, int $size = 40): Tables\Columns\ImageColumn
    {
        return Tables\Columns\ImageColumn::make($name)
            ->label('Image')
            ->circular()
            ->size($size)
            ->defaultImageUrl(url('/images/placeholder.png'));
    }

    /**
     * Boolean icon column
     */
    public static function booleanIcon(string $name, string $trueIcon = 'heroicon-o-check-circle', string $falseIcon = 'heroicon-o-x-circle'): Tables\Columns\IconColumn
    {
        return Tables\Columns\IconColumn::make($name)
            ->label(str($name)->title())
            ->boolean()
            ->trueIcon($trueIcon)
            ->falseIcon($falseIcon)
            ->trueColor('success')
            ->falseColor('danger');
    }

    /**
     * Tags column
     */
    public static function tags(string $name): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($name)
            ->label('Tags')
            ->badge()
            ->separator(',')
            ->color('info')
            ->wrap();
    }

    /**
     * Relationship count column
     */
    public static function relationshipCount(string $relationship, string $label = null): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($relationship . '_count')
            ->label($label ?? str($relationship)->title() . ' Count')
            ->counts($relationship)
            ->badge()
            ->color('info')
            ->sortable();
    }

    /**
     * Calculated column
     */
    public static function calculated(string $name, callable $calculation, string $label = null): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($name)
            ->label($label ?? str($name)->title())
            ->getStateUsing($calculation)
            ->sortable(false);
    }
}
```

## Custom Table Actions

### Advanced Action Components

```php
<?php
// app/Filament/Components/TableActions.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Forms;
use Filament\Notifications\Notification;

class TableActions
{
    /**
     * Quick edit action
     */
    public static function quickEdit(array $fields): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('quick_edit')
            ->label('Quick Edit')
            ->icon('heroicon-o-pencil-square')
            ->form($fields)
            ->action(function ($record, array $data) {
                $record->update($data);
                
                Notification::make()
                    ->title('Record updated successfully')
                    ->success()
                    ->send();
            })
            ->modalWidth('md');
    }

    /**
     * Duplicate action
     */
    public static function duplicate(array $excludeFields = []): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('duplicate')
            ->label('Duplicate')
            ->icon('heroicon-o-document-duplicate')
            ->action(function ($record) use ($excludeFields) {
                $data = $record->toArray();
                
                // Remove excluded fields
                foreach ($excludeFields as $field) {
                    unset($data[$field]);
                }
                
                // Remove primary key and timestamps
                unset($data['id'], $data['created_at'], $data['updated_at']);
                
                $duplicate = $record::create($data);
                
                Notification::make()
                    ->title('Record duplicated successfully')
                    ->success()
                    ->send();
                
                return redirect()->route('filament.admin.resources.' . str($record::class)->snake()->plural() . '.edit', $duplicate);
            })
            ->requiresConfirmation();
    }

    /**
     * Archive action
     */
    public static function archive(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('archive')
            ->label('Archive')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->action(function ($record) {
                $record->update(['archived_at' => now()]);
                
                Notification::make()
                    ->title('Record archived successfully')
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->visible(fn ($record) => !$record->archived_at);
    }

    /**
     * Restore action
     */
    public static function restore(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('restore')
            ->label('Restore')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('success')
            ->action(function ($record) {
                $record->update(['archived_at' => null]);
                
                Notification::make()
                    ->title('Record restored successfully')
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->visible(fn ($record) => $record->archived_at);
    }

    /**
     * Send notification action
     */
    public static function sendNotification(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('send_notification')
            ->label('Send Notification')
            ->icon('heroicon-o-bell')
            ->form([
                Forms\Components\Select::make('type')
                    ->label('Notification Type')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push Notification',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('subject')
                    ->label('Subject')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->action(function ($record, array $data) {
                // Send notification logic here
                app(NotificationService::class)->send($record, $data);
                
                Notification::make()
                    ->title('Notification sent successfully')
                    ->success()
                    ->send();
            })
            ->modalWidth('md');
    }

    /**
     * Export single record action
     */
    public static function exportRecord(string $format = 'pdf'): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('export')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function ($record) use ($format) {
                return response()->download(
                    app(RecordExportService::class)->export($record, $format)
                );
            });
    }

    /**
     * Change status action
     */
    public static function changeStatus(array $statuses): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('change_status')
            ->label('Change Status')
            ->icon('heroicon-o-arrow-path')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('New Status')
                    ->options($statuses)
                    ->required(),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason for Change')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function ($record, array $data) {
                $record->update([
                    'status' => $data['status'],
                    'status_changed_at' => now(),
                    'status_change_reason' => $data['reason'],
                ]);
                
                Notification::make()
                    ->title('Status updated successfully')
                    ->success()
                    ->send();
            })
            ->modalWidth('md');
    }
}
```

## Filtering and Search

### Advanced Filter Components

```php
<?php
// app/Filament/Components/TableFilters.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;

class TableFilters
{
    /**
     * Date range filter
     */
    public static function dateRange(string $column, string $label = null): Tables\Filters\Filter
    {
        return Tables\Filters\Filter::make($column . '_range')
            ->label($label ?? str($column)->title() . ' Range')
            ->form([
                Forms\Components\DatePicker::make('from')
                    ->label('From Date'),
                Forms\Components\DatePicker::make('until')
                    ->label('Until Date'),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                return $query
                    ->when(
                        $data['from'],
                        fn (Builder $query, $date): Builder => $query->whereDate($column, '>=', $date),
                    )
                    ->when(
                        $data['until'],
                        fn (Builder $query, $date): Builder => $query->whereDate($column, '<=', $date),
                    );
            });
    }

    /**
     * Numeric range filter
     */
    public static function numericRange(string $column, string $label = null, string $prefix = ''): Tables\Filters\Filter
    {
        return Tables\Filters\Filter::make($column . '_range')
            ->label($label ?? str($column)->title() . ' Range')
            ->form([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('min')
                            ->label('Minimum')
                            ->numeric()
                            ->prefix($prefix),
                        Forms\Components\TextInput::make('max')
                            ->label('Maximum')
                            ->numeric()
                            ->prefix($prefix),
                    ]),
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                return $query
                    ->when(
                        $data['min'],
                        fn (Builder $query, $min): Builder => $query->where($column, '>=', $min),
                    )
                    ->when(
                        $data['max'],
                        fn (Builder $query, $max): Builder => $query->where($column, '<=', $max),
                    );
            });
    }

    /**
     * Boolean filter with custom labels
     */
    public static function boolean(string $column, string $trueLabel, string $falseLabel, string $label = null): Tables\Filters\TernaryFilter
    {
        return Tables\Filters\TernaryFilter::make($column)
            ->label($label ?? str($column)->title())
            ->trueLabel($trueLabel)
            ->falseLabel($falseLabel)
            ->native(false);
    }
}
```

## Sorting and Grouping

### Advanced Sorting Configuration

Implement sophisticated sorting capabilities for enhanced data organization:

```php
<?php

namespace App\Filament\ChinookAdmin\Resources;

use Filament\Tables;
use Filament\Tables\Table;

class AdvancedSortingResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('album.title')
                    ->label('Album')
                    ->sortable(['albums.title'])
                    ->searchable(['albums.title']),

                Tables\Columns\TextColumn::make('duration')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => gmdate('i:s', $state / 1000)),

                Tables\Columns\TextColumn::make('price')
                    ->sortable()
                    ->money('USD'),
            ])
            ->defaultSort('name', 'asc')
            ->persistSortInSession();
    }
}
```

### Multi-Column Sorting

Enable complex sorting with multiple columns:

```php
<?php

use Filament\Tables\Enums\FiltersLayout;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Column definitions...
        ])
        ->defaultSort([
            'album.artist.name' => 'asc',
            'album.title' => 'asc',
            'track_number' => 'asc',
        ])
        ->persistSortInSession()
        ->striped();
}
```

### Grouping and Aggregation

Implement data grouping with aggregation functions:

```php
<?php

use Filament\Tables\Grouping\Group;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('album.artist.name')
                ->label('Artist')
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('album.title')
                ->label('Album')
                ->sortable(),

            Tables\Columns\TextColumn::make('tracks_count')
                ->label('Track Count')
                ->counts('tracks'),

            Tables\Columns\TextColumn::make('total_duration')
                ->label('Total Duration')
                ->sum('tracks', 'milliseconds')
                ->formatStateUsing(fn ($state) => gmdate('H:i:s', $state / 1000)),
        ])
        ->groups([
            Group::make('album.artist.name')
                ->label('Artist')
                ->collapsible(),

            Group::make('album.release_year')
                ->label('Release Year')
                ->collapsible(),
        ])
        ->groupedBulkActions([
            // Bulk actions that work with grouped data
        ]);
}
```

## Bulk Operations

### Advanced Bulk Actions

```php
<?php
// app/Filament/Components/BulkOperations.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class BulkOperations
{
    /**
     * Bulk status update
     */
    public static function bulkStatusUpdate(array $statuses): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('bulk_status_update')
            ->label('Update Status')
            ->icon('heroicon-o-arrow-path')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('New Status')
                    ->options($statuses)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $records->each(function ($record) use ($data) {
                    $record->update(['status' => $data['status']]);
                });

                Notification::make()
                    ->title("Status updated for {$records->count()} records")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation();
    }

    /**
     * Bulk export with format selection
     */
    public static function bulkExport(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('bulk_export')
            ->label('Export Selected')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Forms\Components\Select::make('format')
                    ->label('Export Format')
                    ->options([
                        'csv' => 'CSV',
                        'xlsx' => 'Excel',
                        'pdf' => 'PDF',
                    ])
                    ->default('csv')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                return response()->download(
                    app(BulkExportService::class)->export($records, $data['format'])
                );
            });
    }
}
```

## Performance Optimization

### Query Optimization Strategies

```php
<?php
// Optimized table implementation example

class OptimizedTable
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select(['id', 'name', 'email', 'status', 'created_at']) // Select only needed columns
            ->with(['roles:id,name']) // Eager load relationships
            ->withCount(['orders']) // Use withCount for counts
            ->when(
                auth()->user()->cannot('view-all'),
                fn (Builder $query) => $query->where('user_id', auth()->id())
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading() // Defer loading for better performance
            ->persistFiltersInSession() // Persist filters
            ->defaultPaginationPageOption(25) // Reasonable page size
            ->extremePaginationLinks(); // Show extreme pagination links
    }
}
```

## Testing

### Table Feature Testing

```php
<?php
// tests/Feature/Filament/TableFeaturesTest.php

use App\Filament\Components\AdvancedColumns;
use App\Filament\Components\TableActions;
use Tests\TestCase;

class TableFeaturesTest extends TestCase
{
    public function test_status_badge_column(): void
    {
        $column = AdvancedColumns::statusBadge('status');

        expect($column->getName())->toBe('status');
        expect($column->isBadge())->toBeTrue();
    }

    public function test_currency_column_formatting(): void
    {
        $column = AdvancedColumns::currency('price', 'USD');

        expect($column->getName())->toBe('price');
        expect($column->isSortable())->toBeTrue();
    }

    public function test_quick_edit_action(): void
    {
        $action = TableActions::quickEdit([
            Forms\Components\TextInput::make('name')->required(),
        ]);

        expect($action->getName())->toBe('quick_edit');
        expect($action->getLabel())->toBe('Quick Edit');
    }
}
```

## Best Practices

### Table Implementation Guidelines

1. **Performance**: Use eager loading and select only necessary columns
2. **User Experience**: Implement responsive design and clear navigation
3. **Accessibility**: Ensure WCAG 2.1 AA compliance for all table elements
4. **Consistency**: Use standardized column types and action patterns
5. **Scalability**: Implement efficient pagination and filtering
6. **Security**: Validate all user inputs and permissions

## Navigation

**← Previous:** [Form Components Guide](120-form-components.md)
**Next →** [Bulk Operations Guide](140-bulk-operations.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components Guide](120-form-components.md) - Advanced form component patterns
- [Bulk Operations Guide](140-bulk-operations.md) - Bulk actions and operations

---

*This guide provides comprehensive table feature implementation for Filament 4 in the Chinook application. Each pattern
includes performance optimization, accessibility, and user experience considerations for robust data interfaces.*
