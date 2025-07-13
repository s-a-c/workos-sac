# Bulk Operations Guide

## Table of Contents

- [Overview](#overview)
- [Basic Bulk Actions](#basic-bulk-actions)
- [Advanced Bulk Operations](#advanced-bulk-operations)
- [Custom Bulk Actions](#custom-bulk-actions)
- [Conditional Bulk Actions](#conditional-bulk-actions)
- [Performance Optimization](#performance-optimization)
- [Error Handling](#error-handling)
- [Progress Tracking](#progress-tracking)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive bulk operation implementation in Filament 4 for the Chinook application. It provides
patterns for efficient mass data operations, progress tracking, error handling, and performance optimization for
large-scale data management.

**🚀 Key Features:**

- **Mass Operations**: Efficient bulk data processing
- **Progress Tracking**: Real-time operation progress
- **Error Handling**: Robust error management and recovery
- **Performance**: Optimized for large datasets
- **WCAG 2.1 AA Compliance**: Accessible bulk operation interfaces

## Basic Bulk Actions

### Standard Bulk Operations

```php
<?php
// app/Filament/Components/StandardBulkActions.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class StandardBulkActions
{
    /**
     * Standard delete bulk action
     */
    public static function delete(): Tables\Actions\DeleteBulkAction
    {
        return Tables\Actions\DeleteBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading('Delete selected records')
            ->modalDescription('Are you sure you want to delete the selected records? This action cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->successNotificationTitle('Records deleted successfully');
    }

    /**
     * Standard restore bulk action
     */
    public static function restore(): Tables\Actions\RestoreBulkAction
    {
        return Tables\Actions\RestoreBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading('Restore selected records')
            ->modalDescription('Are you sure you want to restore the selected records?')
            ->modalSubmitActionLabel('Restore')
            ->successNotificationTitle('Records restored successfully');
    }

    /**
     * Force delete bulk action
     */
    public static function forceDelete(): Tables\Actions\ForceDeleteBulkAction
    {
        return Tables\Actions\ForceDeleteBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading('Permanently delete selected records')
            ->modalDescription('Are you sure you want to permanently delete the selected records? This action cannot be undone.')
            ->modalSubmitActionLabel('Delete Permanently')
            ->successNotificationTitle('Records permanently deleted');
    }

    /**
     * Export bulk action
     */
    public static function export(array $formats = ['csv', 'xlsx']): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('export')
            ->label('Export Selected')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Forms\Components\Select::make('format')
                    ->label('Export Format')
                    ->options(array_combine($formats, array_map('strtoupper', $formats)))
                    ->default($formats[0])
                    ->required(),

                Forms\Components\CheckboxList::make('columns')
                    ->label('Columns to Export')
                    ->options(self::getExportableColumns())
                    ->default(self::getDefaultExportColumns())
                    ->required()
                    ->columns(2),
            ])
            ->action(function (Collection $records, array $data) {
                return response()->download(
                    app(BulkExportService::class)->export($records, $data['format'], $data['columns'])
                );
            })
            ->modalWidth('lg');
    }

    /**
     * Duplicate bulk action
     */
    public static function duplicate(array $excludeFields = ['id', 'created_at', 'updated_at']): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('duplicate')
            ->label('Duplicate Selected')
            ->icon('heroicon-o-document-duplicate')
            ->action(function (Collection $records) use ($excludeFields) {
                $duplicated = 0;
                
                foreach ($records as $record) {
                    $data = $record->toArray();
                    
                    // Remove excluded fields
                    foreach ($excludeFields as $field) {
                        unset($data[$field]);
                    }
                    
                    $record::create($data);
                    $duplicated++;
                }

                Notification::make()
                    ->title("{$duplicated} records duplicated successfully")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading('Duplicate selected records')
            ->modalDescription('This will create copies of the selected records.')
            ->modalSubmitActionLabel('Duplicate');
    }

    private static function getExportableColumns(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'status' => 'Status',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    private static function getDefaultExportColumns(): array
    {
        return ['name', 'email', 'status', 'created_at'];
    }
}
```

## Advanced Bulk Operations

### Complex Bulk Actions

```php
<?php
// app/Filament/Components/AdvancedBulkActions.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class AdvancedBulkActions
{
    /**
     * Bulk status update with validation
     */
    public static function statusUpdate(array $statuses, array $validTransitions = []): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('bulk_status_update')
            ->label('Update Status')
            ->icon('heroicon-o-arrow-path')
            ->form([
                Forms\Components\Select::make('status')
                    ->label('New Status')
                    ->options($statuses)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) use ($validTransitions) {
                        if ($state && !empty($validTransitions)) {
                            $set('transition_warning', self::getTransitionWarning($state, $validTransitions));
                        }
                    }),

                Forms\Components\Placeholder::make('transition_warning')
                    ->content(function (Forms\Get $get): string {
                        return $get('transition_warning') ?? '';
                    })
                    ->visible(fn (Forms\Get $get) => filled($get('transition_warning'))),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason for Status Change')
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('Optional reason for the status change'),
            ])
            ->action(function (Collection $records, array $data) use ($validTransitions) {
                $updated = 0;
                $skipped = 0;
                $errors = [];

                foreach ($records as $record) {
                    if (self::canTransition($record->status, $data['status'], $validTransitions)) {
                        $record->update([
                            'status' => $data['status'],
                            'status_changed_at' => now(),
                            'status_change_reason' => $data['reason'] ?? null,
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                        $errors[] = "Record {$record->id}: Invalid transition from {$record->status} to {$data['status']}";
                    }
                }

                $message = "{$updated} records updated";
                if ($skipped > 0) {
                    $message .= ", {$skipped} skipped";
                }

                Notification::make()
                    ->title($message)
                    ->success($skipped === 0)
                    ->warning($skipped > 0)
                    ->body($skipped > 0 ? implode("\n", array_slice($errors, 0, 5)) : null)
                    ->send();
            })
            ->requiresConfirmation()
            ->modalWidth('lg');
    }

    /**
     * Bulk assignment with relationship
     */
    public static function assignToUser(string $relationship = 'assignedTo'): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('bulk_assign')
            ->label('Assign to User')
            ->icon('heroicon-o-user-group')
            ->form([
                Forms\Components\Select::make('user_id')
                    ->label('Assign to')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Toggle::make('notify_user')
                    ->label('Notify User')
                    ->default(true)
                    ->helperText('Send notification to the assigned user'),

                Forms\Components\Textarea::make('assignment_note')
                    ->label('Assignment Note')
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('Optional note for the assignment'),
            ])
            ->action(function (Collection $records, array $data) use ($relationship) {
                $assigned = 0;

                foreach ($records as $record) {
                    $record->update([
                        'assigned_to' => $data['user_id'],
                        'assigned_at' => now(),
                        'assignment_note' => $data['assignment_note'] ?? null,
                    ]);

                    if ($data['notify_user']) {
                        // Send notification to assigned user
                        app(NotificationService::class)->notifyAssignment($record, $data['user_id']);
                    }

                    $assigned++;
                }

                Notification::make()
                    ->title("{$assigned} records assigned successfully")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalWidth('lg');
    }

    /**
     * Bulk data transformation
     */
    public static function transform(array $transformations): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('bulk_transform')
            ->label('Transform Data')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->form([
                Forms\Components\Select::make('transformation')
                    ->label('Transformation Type')
                    ->options($transformations)
                    ->required()
                    ->live(),

                Forms\Components\Group::make([
                    Forms\Components\TextInput::make('find')
                        ->label('Find')
                        ->required(),
                    Forms\Components\TextInput::make('replace')
                        ->label('Replace With'),
                ])
                ->visible(fn (Forms\Get $get) => $get('transformation') === 'find_replace')
                ->columns(2),

                Forms\Components\Select::make('case_transformation')
                    ->label('Case Transformation')
                    ->options([
                        'uppercase' => 'UPPERCASE',
                        'lowercase' => 'lowercase',
                        'title' => 'Title Case',
                    ])
                    ->visible(fn (Forms\Get $get) => $get('transformation') === 'case_change'),
            ])
            ->action(function (Collection $records, array $data) {
                $transformed = 0;

                foreach ($records as $record) {
                    $result = self::applyTransformation($record, $data);
                    if ($result) {
                        $transformed++;
                    }
                }

                Notification::make()
                    ->title("{$transformed} records transformed successfully")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalWidth('lg');
    }

    private static function getTransitionWarning(string $newStatus, array $validTransitions): ?string
    {
        // Implementation for transition validation warnings
        return null;
    }

    private static function canTransition(string $currentStatus, string $newStatus, array $validTransitions): bool
    {
        if (empty($validTransitions)) {
            return true;
        }

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    private static function applyTransformation($record, array $data): bool
    {
        // Implementation for data transformation
        return true;
    }
}
```

## Custom Bulk Actions

### Business-Specific Bulk Operations

```php
<?php
// app/Filament/Components/CustomBulkActions.php

namespace App\Filament\Components;

use Filament\Tables;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class CustomBulkActions
{
    /**
     * Bulk email sending
     */
    public static function sendEmail(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('send_email')
            ->label('Send Email')
            ->icon('heroicon-o-envelope')
            ->form([
                Forms\Components\Select::make('template')
                    ->label('Email Template')
                    ->options([
                        'welcome' => 'Welcome Email',
                        'newsletter' => 'Newsletter',
                        'promotion' => 'Promotional Email',
                        'reminder' => 'Reminder Email',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('subject')
                    ->label('Subject')
                    ->required()
                    ->maxLength(255),

                Forms\Components\RichEditor::make('message')
                    ->label('Message')
                    ->required()
                    ->maxLength(5000),

                Forms\Components\Toggle::make('send_immediately')
                    ->label('Send Immediately')
                    ->default(false)
                    ->helperText('If disabled, emails will be queued for later sending'),
            ])
            ->action(function (Collection $records, array $data) {
                $queued = 0;

                foreach ($records as $record) {
                    if ($record->email) {
                        app(EmailService::class)->send($record, $data);
                        $queued++;
                    }
                }

                $message = $data['send_immediately'] 
                    ? "{$queued} emails sent successfully"
                    : "{$queued} emails queued for sending";

                Notification::make()
                    ->title($message)
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalWidth('lg');
    }

    /**
     * Bulk price update for tracks
     */
    public static function updatePrices(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('update_prices')
            ->label('Update Prices')
            ->icon('heroicon-o-currency-dollar')
            ->form([
                Forms\Components\Select::make('update_type')
                    ->label('Update Type')
                    ->options([
                        'fixed' => 'Set Fixed Price',
                        'percentage' => 'Percentage Change',
                        'amount' => 'Add/Subtract Amount',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->numeric()
                    ->required()
                    ->prefix(fn (Forms\Get $get) => match($get('update_type')) {
                        'percentage' => '',
                        default => '$',
                    })
                    ->suffix(fn (Forms\Get $get) => $get('update_type') === 'percentage' ? '%' : ''),

                Forms\Components\DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->default(now())
                    ->required(),

                Forms\Components\Textarea::make('reason')
                    ->label('Reason for Price Change')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (Collection $records, array $data) {
                $updated = 0;

                foreach ($records as $record) {
                    $newPrice = self::calculateNewPrice($record->price, $data);
                    
                    $record->update([
                        'price' => $newPrice,
                        'price_updated_at' => $data['effective_date'],
                        'price_change_reason' => $data['reason'],
                    ]);

                    $updated++;
                }

                Notification::make()
                    ->title("{$updated} prices updated successfully")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalWidth('lg');
    }

    /**
     * Bulk playlist management
     */
    public static function addToPlaylist(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('add_to_playlist')
            ->label('Add to Playlist')
            ->icon('heroicon-o-queue-list')
            ->form([
                Forms\Components\Select::make('playlist_id')
                    ->label('Playlist')
                    ->relationship('playlist', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500),
                    ]),

                Forms\Components\Select::make('position')
                    ->label('Position')
                    ->options([
                        'beginning' => 'Add to Beginning',
                        'end' => 'Add to End',
                        'custom' => 'Custom Position',
                    ])
                    ->default('end')
                    ->live(),

                Forms\Components\TextInput::make('custom_position')
                    ->label('Position Number')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Forms\Get $get) => $get('position') === 'custom'),
            ])
            ->action(function (Collection $records, array $data) {
                $added = 0;

                foreach ($records as $record) {
                    app(PlaylistService::class)->addTrack($data['playlist_id'], $record->id, $data);
                    $added++;
                }

                Notification::make()
                    ->title("{$added} tracks added to playlist")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalWidth('lg');
    }

    private static function calculateNewPrice(float $currentPrice, array $data): float
    {
        return match($data['update_type']) {
            'fixed' => $data['value'],
            'percentage' => $currentPrice * (1 + ($data['value'] / 100)),
            'amount' => $currentPrice + $data['value'],
            default => $currentPrice,
        };
    }
}
```

## Conditional Bulk Actions

### Context-Aware Bulk Operations

```php
<?php
// app/Filament/Components/ConditionalBulkActions.php

namespace App\Filament\Components;

use Filament\Tables;
use Illuminate\Support\Collection;

class ConditionalBulkActions
{
    /**
     * Conditional bulk action based on record state
     */
    public static function conditionalAction(string $condition, Tables\Actions\BulkAction $action): Tables\Actions\BulkAction
    {
        return $action->visible(function (Collection $records) use ($condition) {
            return $records->every(fn ($record) => self::checkCondition($record, $condition));
        });
    }

    /**
     * Role-based bulk actions
     */
    public static function roleBasedActions(): array
    {
        return [
            Tables\Actions\BulkAction::make('admin_only')
                ->label('Admin Action')
                ->visible(fn () => auth()->user()->hasRole('admin'))
                ->action(function (Collection $records) {
                    // Admin-only logic
                }),

            Tables\Actions\BulkAction::make('manager_action')
                ->label('Manager Action')
                ->visible(fn () => auth()->user()->hasAnyRole(['admin', 'manager']))
                ->action(function (Collection $records) {
                    // Manager logic
                }),
        ];
    }

    private static function checkCondition($record, string $condition): bool
    {
        return match($condition) {
            'active' => $record->is_active,
            'published' => $record->status === 'published',
            'editable' => $record->canEdit(),
            default => true,
        };
    }
}
```

## Performance Optimization

### Efficient Bulk Processing

```php
<?php
// app/Services/BulkOperationService.php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BulkOperationService
{
    /**
     * Chunked bulk processing
     */
    public function processInChunks(Collection $records, callable $operation, int $chunkSize = 100): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        $records->chunk($chunkSize)->each(function ($chunk) use ($operation, &$results) {
            DB::transaction(function () use ($chunk, $operation, &$results) {
                foreach ($chunk as $record) {
                    try {
                        $operation($record);
                        $results['success']++;
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = "Record {$record->id}: " . $e->getMessage();
                    }
                }
            });
        });

        return $results;
    }

    /**
     * Batch database operations
     */
    public function batchUpdate(Collection $records, array $updates): int
    {
        $model = $records->first();
        $ids = $records->pluck('id');

        return $model::whereIn('id', $ids)->update($updates);
    }
}
```

## Progress Tracking

### Real-Time Progress Updates

Implement progress tracking for long-running bulk operations to provide user feedback and improve user experience.

```php
<?php

namespace App\Filament\ChinookAdmin\Actions;

use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ProgressTrackingBulkAction extends BulkAction
{
    public static function make(string $name = 'progressTracking'): static
    {
        return parent::make($name)
            ->label('Process with Progress')
            ->icon('heroicon-o-arrow-path')
            ->action(function (Collection $records) {
                $total = $records->count();
                $processed = 0;
                $progressKey = 'bulk_progress_' . auth()->id() . '_' . time();

                // Initialize progress
                Cache::put($progressKey, [
                    'total' => $total,
                    'processed' => 0,
                    'percentage' => 0,
                    'status' => 'processing'
                ], 300); // 5 minutes

                foreach ($records as $record) {
                    // Process individual record
                    static::processRecord($record);

                    $processed++;
                    $percentage = round(($processed / $total) * 100);

                    // Update progress
                    Cache::put($progressKey, [
                        'total' => $total,
                        'processed' => $processed,
                        'percentage' => $percentage,
                        'status' => 'processing'
                    ], 300);

                    // Optional: Add small delay to prevent overwhelming the system
                    if ($processed % 10 === 0) {
                        usleep(100000); // 0.1 second
                    }
                }

                // Mark as complete
                Cache::put($progressKey, [
                    'total' => $total,
                    'processed' => $processed,
                    'percentage' => 100,
                    'status' => 'completed'
                ], 300);

                Notification::make()
                    ->title('Bulk Operation Completed')
                    ->body("Successfully processed {$processed} records")
                    ->success()
                    ->send();
            });
    }

    protected static function processRecord($record): void
    {
        // Implement your record processing logic here
        // This is a placeholder for the actual processing
        $record->update(['processed_at' => now()]);
    }
}
```

### Progress Display Component

Create a Livewire component to display real-time progress updates:

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Cache;

class BulkProgressTracker extends Component
{
    public string $progressKey;
    public array $progress = [];

    public function mount(string $progressKey): void
    {
        $this->progressKey = $progressKey;
        $this->updateProgress();
    }

    public function updateProgress(): void
    {
        $this->progress = Cache::get($this->progressKey, [
            'total' => 0,
            'processed' => 0,
            'percentage' => 0,
            'status' => 'pending'
        ]);
    }

    public function render()
    {
        return view('livewire.bulk-progress-tracker');
    }
}
```

### Progress Tracking Best Practices

1. **Cache Management**: Use appropriate cache expiration times
2. **User Feedback**: Provide clear progress indicators
3. **Error Recovery**: Handle interruptions gracefully
4. **Performance**: Balance update frequency with system performance
5. **Cleanup**: Remove progress data after completion

## Error Handling

### Robust Error Management

```php
<?php
// app/Services/BulkErrorHandler.php

namespace App\Services;

use Filament\Notifications\Notification;

class BulkErrorHandler
{
    /**
     * Handle bulk operation errors
     */
    public function handleErrors(array $results): void
    {
        $total = $results['success'] + $results['failed'];

        if ($results['failed'] === 0) {
            Notification::make()
                ->title("All {$total} operations completed successfully")
                ->success()
                ->send();
        } elseif ($results['success'] === 0) {
            Notification::make()
                ->title("All {$total} operations failed")
                ->danger()
                ->body($this->formatErrors($results['errors']))
                ->send();
        } else {
            Notification::make()
                ->title("Partial success: {$results['success']} succeeded, {$results['failed']} failed")
                ->warning()
                ->body($this->formatErrors($results['errors']))
                ->send();
        }
    }

    private function formatErrors(array $errors): string
    {
        $maxErrors = 5;
        $displayErrors = array_slice($errors, 0, $maxErrors);
        $errorText = implode("\n", $displayErrors);

        if (count($errors) > $maxErrors) {
            $errorText .= "\n... and " . (count($errors) - $maxErrors) . " more errors";
        }

        return $errorText;
    }
}
```

## Testing

### Bulk Operation Testing

```php
<?php
// tests/Feature/Filament/BulkOperationsTest.php

use App\Filament\Components\StandardBulkActions;
use App\Models\User;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    public function test_bulk_delete_action(): void
    {
        $users = User::factory()->count(3)->create();
        $action = StandardBulkActions::delete();

        expect($action->getName())->toBe('delete');
        expect($action->requiresConfirmation())->toBeTrue();
    }

    public function test_bulk_export_action(): void
    {
        $users = User::factory()->count(5)->create();
        $action = StandardBulkActions::export(['csv', 'xlsx']);

        expect($action->getName())->toBe('export');
        expect($action->getLabel())->toBe('Export Selected');
    }

    public function test_bulk_status_update(): void
    {
        $users = User::factory()->count(3)->create(['status' => 'inactive']);

        // Simulate bulk status update
        $users->each(function ($user) {
            $user->update(['status' => 'active']);
        });

        expect($users->every(fn ($user) => $user->fresh()->status === 'active'))->toBeTrue();
    }
}
```

## Best Practices

### Bulk Operation Guidelines

1. **Performance**: Process large datasets in chunks to avoid memory issues
2. **Transactions**: Use database transactions for data consistency
3. **Error Handling**: Implement comprehensive error handling and recovery
4. **User Feedback**: Provide clear progress indicators and result summaries
5. **Security**: Validate permissions for bulk operations
6. **Logging**: Log all bulk operations for audit trails

### Performance Optimization Tips

```php
<?php
// Optimized bulk operation patterns

class OptimizedBulkOperations
{
    /**
     * Memory-efficient processing
     */
    public static function memoryEfficientProcessing(string $model, callable $operation): void
    {
        $model::chunk(100, function ($records) use ($operation) {
            foreach ($records as $record) {
                $operation($record);
            }
        });
    }

    /**
     * Efficient bulk update using raw SQL
     */
    public static function efficientBulkUpdate(Collection $records, array $updates): int
    {
        $ids = $records->pluck('id')->toArray();
        $model = $records->first();

        return $model::whereIn('id', $ids)->update($updates);
    }
}
```

## Navigation

**← Previous:** [Table Features Guide](130-table-features.md)
**Next →** [Resource Architecture](000-resources-index.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Form Components Guide](120-form-components.md) - Advanced form component patterns
- [Relationship Managers Guide](120-relationship-managers.md) - Managing model relationships

---

*This guide provides comprehensive bulk operation implementation for Filament 4 in the Chinook application. Each pattern
includes performance optimization, error handling, and progress tracking for robust mass data operations.*
