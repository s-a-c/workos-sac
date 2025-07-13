# Form Components Guide

## Table of Contents

- [Overview](#overview)
- [Basic Form Components](#basic-form-components)
- [Advanced Input Components](#advanced-input-components)
- [Custom Form Components](#custom-form-components)
- [Validation Patterns](#validation-patterns)
- [Dynamic Form Behavior](#dynamic-form-behavior)
- [File Upload Components](#file-upload-components)
- [Relationship Components](#relationship-components)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers comprehensive form component implementation in Filament 4 for the Chinook application. It provides
reusable form patterns, custom components, validation strategies, and advanced form behaviors for building robust user
interfaces.

**🚀 Key Features:**

- **Reusable Components**: Standardized form component library
- **Dynamic Behavior**: Live updates and reactive forms
- **Custom Validation**: Advanced validation patterns
- **File Handling**: Comprehensive file upload solutions
- **WCAG 2.1 AA Compliance**: Accessible form components

## Basic Form Components

### Standard Input Components

```php
<?php
// app/Filament/Components/StandardInputs.php

namespace App\Filament\Components;

use Filament\Forms;

class StandardInputs
{
    /**
     * Standard text input with validation
     */
    public static function textInput(string $name, string $label = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label ?? str($name)->title())
            ->maxLength(255)
            ->autocomplete()
            ->extraInputAttributes(['class' => 'standard-input']);
    }

    /**
     * Email input with validation
     */
    public static function emailInput(string $name = 'email'): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label('Email Address')
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->suffixIcon('heroicon-m-envelope')
            ->autocomplete('email')
            ->extraInputAttributes([
                'class' => 'email-input',
                'inputmode' => 'email',
            ]);
    }

    /**
     * Phone input with formatting
     */
    public static function phoneInput(string $name = 'phone'): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label('Phone Number')
            ->tel()
            ->maxLength(24)
            ->mask('(999) 999-9999')
            ->placeholder('(555) 123-4567')
            ->suffixIcon('heroicon-m-phone')
            ->autocomplete('tel')
            ->extraInputAttributes([
                'class' => 'phone-input',
                'inputmode' => 'tel',
            ]);
    }

    /**
     * Currency input
     */
    public static function currencyInput(string $name, string $currency = 'USD'): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label(str($name)->title())
            ->numeric()
            ->prefix('$')
            ->step(0.01)
            ->minValue(0)
            ->extraInputAttributes([
                'class' => 'currency-input',
                'inputmode' => 'decimal',
            ])
            ->formatStateUsing(fn ($state) => number_format($state, 2))
            ->dehydrateStateUsing(fn ($state) => (float) str_replace(',', '', $state));
    }

    /**
     * Password input with strength indicator
     */
    public static function passwordInput(string $name = 'password'): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make($name)
                ->label('Password')
                ->password()
                ->required()
                ->minLength(8)
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    $strength = self::calculatePasswordStrength($state);
                    $set($name . '_strength', $strength);
                })
                ->helperText('Minimum 8 characters with uppercase, lowercase, number, and symbol'),

            Forms\Components\Placeholder::make($name . '_strength')
                ->label('Password Strength')
                ->content(function (Forms\Get $get) use ($name): string {
                    $password = $get($name);
                    if (!$password) return '';
                    
                    $strength = self::calculatePasswordStrength($password);
                    $color = match($strength['level']) {
                        'weak' => 'text-red-600',
                        'medium' => 'text-yellow-600',
                        'strong' => 'text-green-600',
                        default => 'text-gray-600',
                    };
                    
                    return "<div class='password-strength {$color}'>{$strength['text']}</div>";
                }),
        ]);
    }

    private static function calculatePasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];

        if (strlen($password) >= 8) $score++; else $feedback[] = 'At least 8 characters';
        if (preg_match('/[A-Z]/', $password)) $score++; else $feedback[] = 'Uppercase letter';
        if (preg_match('/[a-z]/', $password)) $score++; else $feedback[] = 'Lowercase letter';
        if (preg_match('/[0-9]/', $password)) $score++; else $feedback[] = 'Number';
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score++; else $feedback[] = 'Special character';

        return match($score) {
            0, 1, 2 => ['level' => 'weak', 'text' => 'Weak - Missing: ' . implode(', ', $feedback)],
            3, 4 => ['level' => 'medium', 'text' => 'Medium - Consider: ' . implode(', ', $feedback)],
            5 => ['level' => 'strong', 'text' => 'Strong password'],
            default => ['level' => 'weak', 'text' => 'Invalid'],
        };
    }
}
```

## Advanced Input Components

### Specialized Form Components

```php
<?php
// app/Filament/Components/AdvancedInputs.php

namespace App\Filament\Components;

use Filament\Forms;

class AdvancedInputs
{
    /**
     * Address input with autocomplete
     */
    public static function addressInput(): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('address')
                ->label('Street Address')
                ->maxLength(255)
                ->columnSpanFull()
                ->autocomplete('street-address'),

            Forms\Components\TextInput::make('city')
                ->label('City')
                ->maxLength(100)
                ->autocomplete('address-level2'),

            Forms\Components\Select::make('state')
                ->label('State/Province')
                ->options([
                    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona',
                    'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado',
                    // ... more states
                ])
                ->searchable()
                ->autocomplete('address-level1'),

            Forms\Components\TextInput::make('postal_code')
                ->label('ZIP/Postal Code')
                ->maxLength(20)
                ->autocomplete('postal-code'),

            Forms\Components\Select::make('country')
                ->label('Country')
                ->options([
                    'US' => 'United States',
                    'CA' => 'Canada',
                    'MX' => 'Mexico',
                    // ... more countries
                ])
                ->default('US')
                ->searchable()
                ->autocomplete('country'),
        ])->columns(2);
    }

    /**
     * Date range picker
     */
    public static function dateRangePicker(string $startName, string $endName): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\DatePicker::make($startName)
                ->label('Start Date')
                ->required()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) use ($endName) {
                    if ($state) {
                        $set($endName, null); // Reset end date when start date changes
                    }
                }),

            Forms\Components\DatePicker::make($endName)
                ->label('End Date')
                ->required()
                ->after($startName)
                ->live(),

            Forms\Components\Placeholder::make('duration')
                ->label('Duration')
                ->content(function (Forms\Get $get) use ($startName, $endName): string {
                    $start = $get($startName);
                    $end = $get($endName);
                    
                    if ($start && $end) {
                        $days = \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end));
                        return $days . ' day' . ($days !== 1 ? 's' : '');
                    }
                    
                    return '—';
                }),
        ])->columns(3);
    }

    /**
     * Rich text editor with media
     */
    public static function richTextEditor(string $name): Forms\Components\RichEditor
    {
        return Forms\Components\RichEditor::make($name)
            ->label(str($name)->title())
            ->toolbarButtons([
                'attachFiles',
                'blockquote',
                'bold',
                'bulletList',
                'codeBlock',
                'h2',
                'h3',
                'italic',
                'link',
                'orderedList',
                'redo',
                'strike',
                'underline',
                'undo',
            ])
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory('attachments')
            ->fileAttachmentsVisibility('public')
            ->maxLength(10000);
    }

    /**
     * Tag input with suggestions
     */
    public static function tagInput(string $name, array $suggestions = []): Forms\Components\TagsInput
    {
        return Forms\Components\TagsInput::make($name)
            ->label(str($name)->title())
            ->suggestions($suggestions)
            ->separator(',')
            ->splitKeys(['Tab', ','])
            ->nestedRecursiveRules([
                'min:2',
                'max:50',
                'alpha_dash',
            ]);
    }

    /**
     * Color picker with presets
     */
    public static function colorPicker(string $name): Forms\Components\ColorPicker
    {
        return Forms\Components\ColorPicker::make($name)
            ->label(str($name)->title())
            ->rgba()
            ->extraAttributes(['class' => 'color-picker']);
    }

    /**
     * JSON editor
     */
    public static function jsonEditor(string $name): Forms\Components\Textarea
    {
        return Forms\Components\Textarea::make($name)
            ->label(str($name)->title())
            ->rows(10)
            ->extraInputAttributes([
                'class' => 'json-editor font-mono',
                'data-language' => 'json',
            ])
            ->helperText('Enter valid JSON data')
            ->rules(['json']);
    }
}
```

## Custom Form Components

### Business-Specific Components

```php
<?php
// app/Filament/Components/BusinessComponents.php

namespace App\Filament\Components;

use Filament\Forms;
use App\Models\{Track, Album, Artist};

class BusinessComponents
{
    /**
     * Track selector with preview
     */
    public static function trackSelector(string $name = 'track_id'): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\Select::make($name)
                ->label('Track')
                ->relationship('track', 'name')
                ->searchable(['name', 'album.title', 'album.artist.name'])
                ->getOptionLabelFromRecordUsing(fn ($record): string => 
                    "{$record->name} - {$record->album->artist->name}"
                )
                ->preload()
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?int $state) {
                    if ($state) {
                        $track = Track::with(['album.artist'])->find($state);
                        $set('track_preview', [
                            'name' => $track->name,
                            'artist' => $track->album->artist->name,
                            'album' => $track->album->title,
                            'duration' => $track->milliseconds ? gmdate('i:s', $track->milliseconds / 1000) : null,
                            'price' => $track->unit_price,
                        ]);
                    }
                }),

            Forms\Components\Placeholder::make('track_preview')
                ->label('Track Details')
                ->content(function (Forms\Get $get): string {
                    $preview = $get('track_preview');
                    if (!$preview) return 'Select a track to see details';
                    
                    return "
                        <div class='track-preview'>
                            <strong>{$preview['name']}</strong><br>
                            <span class='text-gray-600'>by {$preview['artist']}</span><br>
                            <span class='text-gray-600'>from {$preview['album']}</span><br>
                            <span class='text-sm'>Duration: {$preview['duration']} | Price: \${$preview['price']}</span>
                        </div>
                    ";
                })
                ->visible(fn (Forms\Get $get) => $get($name)),
        ])->columns(2);
    }

    /**
     * Price calculator
     */
    public static function priceCalculator(): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('base_price')
                ->label('Base Price')
                ->numeric()
                ->prefix('$')
                ->step(0.01)
                ->live(),

            Forms\Components\TextInput::make('quantity')
                ->label('Quantity')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->live(),

            Forms\Components\Select::make('discount_type')
                ->label('Discount Type')
                ->options([
                    'none' => 'No Discount',
                    'percentage' => 'Percentage',
                    'fixed' => 'Fixed Amount',
                ])
                ->default('none')
                ->live(),

            Forms\Components\TextInput::make('discount_value')
                ->label('Discount Value')
                ->numeric()
                ->visible(fn (Forms\Get $get) => $get('discount_type') !== 'none')
                ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percentage' ? '%' : '$')
                ->live(),

            Forms\Components\Placeholder::make('calculated_total')
                ->label('Total Price')
                ->content(function (Forms\Get $get): string {
                    $basePrice = (float) ($get('base_price') ?? 0);
                    $quantity = (int) ($get('quantity') ?? 1);
                    $discountType = $get('discount_type') ?? 'none';
                    $discountValue = (float) ($get('discount_value') ?? 0);

                    $subtotal = $basePrice * $quantity;
                    
                    $discount = match($discountType) {
                        'percentage' => $subtotal * ($discountValue / 100),
                        'fixed' => $discountValue,
                        default => 0,
                    };

                    $total = max(0, $subtotal - $discount);
                    
                    return '<strong>$' . number_format($total, 2) . '</strong>';
                }),
        ])->columns(3);
    }

    /**
     * Media upload with metadata
     */
    public static function mediaUpload(string $name): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\FileUpload::make($name)
                ->label('Media File')
                ->acceptedFileTypes(['audio/*', 'video/*'])
                ->maxSize(100 * 1024) // 100MB
                ->directory('media-uploads')
                ->visibility('private')
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, $state) {
                    if ($state) {
                        // Extract metadata from uploaded file
                        $metadata = self::extractMediaMetadata($state);
                        $set('media_metadata', $metadata);
                    }
                }),

            Forms\Components\Placeholder::make('media_metadata')
                ->label('File Information')
                ->content(function (Forms\Get $get): string {
                    $metadata = $get('media_metadata');
                    if (!$metadata) return 'Upload a file to see metadata';
                    
                    return "
                        <div class='media-metadata'>
                            <strong>Duration:</strong> {$metadata['duration']}<br>
                            <strong>Format:</strong> {$metadata['format']}<br>
                            <strong>Size:</strong> {$metadata['size']}<br>
                            <strong>Bitrate:</strong> {$metadata['bitrate']}
                        </div>
                    ";
                })
                ->visible(fn (Forms\Get $get) => $get($name)),
        ])->columns(2);
    }

    private static function extractMediaMetadata($file): array
    {
        // Simplified metadata extraction
        return [
            'duration' => '3:45',
            'format' => 'MP3',
            'size' => '5.2 MB',
            'bitrate' => '320 kbps',
        ];
    }

    /**
     * Playlist builder
     */
    public static function playlistBuilder(string $name = 'tracks'): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make($name)
            ->label('Playlist Tracks')
            ->schema([
                Forms\Components\Select::make('track_id')
                    ->label('Track')
                    ->relationship('track', 'name')
                    ->searchable(['name', 'album.title', 'album.artist.name'])
                    ->getOptionLabelFromRecordUsing(fn ($record): string => 
                        "{$record->name} - {$record->album->artist->name}"
                    )
                    ->required(),

                Forms\Components\TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->default(fn (Forms\Get $get) => count($get('../../tracks')) + 1)
                    ->minValue(1),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->columns(3)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => 
                $state['track_id'] ? Track::find($state['track_id'])?->name : null
            )
            ->reorderable('position')
            ->addActionLabel('Add Track')
            ->deleteAction(
                fn (Forms\Components\Actions\Action $action) => $action
                    ->requiresConfirmation()
            );
    }
}
```

## Validation Patterns

### Advanced Validation Rules

```php
<?php
// app/Filament/Components/ValidationPatterns.php

namespace App\Filament\Components;

use Filament\Forms;
use Illuminate\Validation\Rule;

class ValidationPatterns
{
    /**
     * Unique email with custom validation
     */
    public static function uniqueEmailInput(string $table, string $column = 'email'): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($column)
            ->label('Email Address')
            ->email()
            ->required()
            ->maxLength(255)
            ->rules([
                'email:rfc,dns',
                Rule::unique($table, $column)->ignore(fn ($record) => $record?->id),
                function ($attribute, $value, $fail) {
                    // Custom validation for disposable emails
                    $disposableDomains = ['tempmail.com', '10minutemail.com'];
                    $domain = substr(strrchr($value, '@'), 1);
                    
                    if (in_array($domain, $disposableDomains)) {
                        $fail('Disposable email addresses are not allowed.');
                    }
                },
            ])
            ->suffixIcon('heroicon-m-envelope');
    }

    /**
     * Conditional required field
     */
    public static function conditionalRequired(string $name, string $condition, $conditionValue): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label(str($name)->title())
            ->required(fn (Forms\Get $get) => $get($condition) === $conditionValue)
            ->rules([
                fn (Forms\Get $get): array => $get($condition) === $conditionValue ? ['required'] : [],
            ])
            ->live();
    }

    /**
     * File upload with custom validation
     */
    public static function validatedFileUpload(string $name, array $allowedTypes, int $maxSizeMB): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($name)
            ->label(str($name)->title())
            ->acceptedFileTypes($allowedTypes)
            ->maxSize($maxSizeMB * 1024)
            ->rules([
                'file',
                'max:' . ($maxSizeMB * 1024),
                function ($attribute, $value, $fail) use ($allowedTypes) {
                    if ($value) {
                        $mimeType = $value->getMimeType();
                        $allowedMimes = [];
                        
                        foreach ($allowedTypes as $type) {
                            if ($type === 'image/*') {
                                $allowedMimes = array_merge($allowedMimes, ['image/jpeg', 'image/png', 'image/gif']);
                            } elseif ($type === 'audio/*') {
                                $allowedMimes = array_merge($allowedMimes, ['audio/mpeg', 'audio/wav', 'audio/flac']);
                            }
                        }
                        
                        if (!in_array($mimeType, $allowedMimes)) {
                            $fail('The file type is not allowed.');
                        }
                    }
                },
            ]);
    }

    /**
     * Cross-field validation
     */
    public static function crossFieldValidation(): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\DatePicker::make('start_date')
                ->label('Start Date')
                ->required()
                ->live(),

            Forms\Components\DatePicker::make('end_date')
                ->label('End Date')
                ->required()
                ->after('start_date')
                ->rules([
                    fn (Forms\Get $get): array => [
                        'after:' . $get('start_date'),
                        function ($attribute, $value, $fail) use ($get) {
                            $startDate = $get('start_date');
                            if ($startDate && $value) {
                                $daysDiff = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($value));
                                if ($daysDiff > 365) {
                                    $fail('The date range cannot exceed 365 days.');
                                }
                            }
                        },
                    ],
                ])
                ->live(),
        ])->columns(2);
    }
}
```

## Dynamic Form Behavior

### Reactive Form Components

```php
<?php
// app/Filament/Components/DynamicBehavior.php

namespace App\Filament\Components;

use Filament\Forms;

class DynamicBehavior
{
    /**
     * Cascading dropdowns
     */
    public static function cascadingSelects(): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\Select::make('country')
                ->label('Country')
                ->options([
                    'US' => 'United States',
                    'CA' => 'Canada',
                    'MX' => 'Mexico',
                ])
                ->live()
                ->afterStateUpdated(function (Forms\Set $set) {
                    $set('state', null);
                    $set('city', null);
                }),

            Forms\Components\Select::make('state')
                ->label('State/Province')
                ->options(function (Forms\Get $get): array {
                    return match($get('country')) {
                        'US' => [
                            'CA' => 'California',
                            'NY' => 'New York',
                            'TX' => 'Texas',
                        ],
                        'CA' => [
                            'ON' => 'Ontario',
                            'BC' => 'British Columbia',
                            'QC' => 'Quebec',
                        ],
                        default => [],
                    };
                })
                ->visible(fn (Forms\Get $get) => filled($get('country')))
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('city', null)),

            Forms\Components\Select::make('city')
                ->label('City')
                ->options(function (Forms\Get $get): array {
                    $country = $get('country');
                    $state = $get('state');
                    
                    if ($country === 'US' && $state === 'CA') {
                        return [
                            'LA' => 'Los Angeles',
                            'SF' => 'San Francisco',
                            'SD' => 'San Diego',
                        ];
                    }
                    
                    return [];
                })
                ->visible(fn (Forms\Get $get) => filled($get('state'))),
        ])->columns(3);
    }

    /**
     * Conditional field groups
     */
    public static function conditionalFields(): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\Select::make('user_type')
                ->label('User Type')
                ->options([
                    'individual' => 'Individual',
                    'business' => 'Business',
                    'organization' => 'Organization',
                ])
                ->required()
                ->live(),

            Forms\Components\Group::make([
                Forms\Components\TextInput::make('first_name')
                    ->label('First Name')
                    ->required(),
                Forms\Components\TextInput::make('last_name')
                    ->label('Last Name')
                    ->required(),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Date of Birth'),
            ])
            ->visible(fn (Forms\Get $get) => $get('user_type') === 'individual')
            ->columns(2),

            Forms\Components\Group::make([
                Forms\Components\TextInput::make('company_name')
                    ->label('Company Name')
                    ->required(),
                Forms\Components\TextInput::make('tax_id')
                    ->label('Tax ID')
                    ->required(),
                Forms\Components\TextInput::make('business_license')
                    ->label('Business License'),
            ])
            ->visible(fn (Forms\Get $get) => $get('user_type') === 'business')
            ->columns(2),

            Forms\Components\Group::make([
                Forms\Components\TextInput::make('organization_name')
                    ->label('Organization Name')
                    ->required(),
                Forms\Components\Select::make('organization_type')
                    ->label('Organization Type')
                    ->options([
                        'nonprofit' => 'Non-Profit',
                        'government' => 'Government',
                        'educational' => 'Educational',
                    ])
                    ->required(),
            ])
            ->visible(fn (Forms\Get $get) => $get('user_type') === 'organization')
            ->columns(2),
        ]);
    }

    /**
     * Real-time calculations
     */
    public static function calculatorFields(): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\TextInput::make('principal')
                ->label('Principal Amount')
                ->numeric()
                ->prefix('$')
                ->live(),

            Forms\Components\TextInput::make('interest_rate')
                ->label('Interest Rate')
                ->numeric()
                ->suffix('%')
                ->step(0.01)
                ->live(),

            Forms\Components\TextInput::make('term_years')
                ->label('Term (Years)')
                ->numeric()
                ->live(),

            Forms\Components\Placeholder::make('monthly_payment')
                ->label('Monthly Payment')
                ->content(function (Forms\Get $get): string {
                    $principal = (float) ($get('principal') ?? 0);
                    $rate = (float) ($get('interest_rate') ?? 0) / 100 / 12;
                    $months = (int) ($get('term_years') ?? 0) * 12;

                    if ($principal && $rate && $months) {
                        $payment = $principal * ($rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
                        return '$' . number_format($payment, 2);
                    }

                    return '$0.00';
                }),

            Forms\Components\Placeholder::make('total_interest')
                ->label('Total Interest')
                ->content(function (Forms\Get $get): string {
                    $principal = (float) ($get('principal') ?? 0);
                    $rate = (float) ($get('interest_rate') ?? 0) / 100 / 12;
                    $months = (int) ($get('term_years') ?? 0) * 12;

                    if ($principal && $rate && $months) {
                        $payment = $principal * ($rate * pow(1 + $rate, $months)) / (pow(1 + $rate, $months) - 1);
                        $totalPaid = $payment * $months;
                        $totalInterest = $totalPaid - $principal;
                        return '$' . number_format($totalInterest, 2);
                    }

                    return '$0.00';
                }),
        ])->columns(3);
    }
}
```

## File Upload Components

### Advanced File Handling

```php
<?php
// app/Filament/Components/FileUploadComponents.php

namespace App\Filament\Components;

use Filament\Forms;

class FileUploadComponents
{
    /**
     * Multi-format media uploader
     */
    public static function mediaUploader(string $name): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\FileUpload::make($name)
                ->label('Media Files')
                ->multiple()
                ->acceptedFileTypes(['audio/*', 'video/*', 'image/*'])
                ->maxFiles(10)
                ->maxSize(100 * 1024) // 100MB
                ->directory('media')
                ->visibility('private')
                ->reorderable()
                ->previewable()
                ->downloadable()
                ->imageEditor()
                ->live(),

            Forms\Components\Placeholder::make('file_info')
                ->label('Upload Guidelines')
                ->content('
                    <div class="text-sm text-gray-600">
                        <strong>Supported formats:</strong> MP3, WAV, FLAC, MP4, AVI, JPG, PNG<br>
                        <strong>Maximum size:</strong> 100MB per file<br>
                        <strong>Maximum files:</strong> 10 files
                    </div>
                '),
        ])->columns(2);
    }

    /**
     * Avatar uploader with cropping
     */
    public static function avatarUploader(string $name = 'avatar'): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($name)
            ->label('Profile Picture')
            ->image()
            ->imageEditor()
            ->imageEditorAspectRatios(['1:1'])
            ->imageResizeMode('cover')
            ->imageCropAspectRatio('1:1')
            ->imageResizeTargetWidth('300')
            ->imageResizeTargetHeight('300')
            ->directory('avatars')
            ->visibility('public')
            ->maxSize(2 * 1024) // 2MB
            ->acceptedFileTypes(['image/jpeg', 'image/png'])
            ->rules(['image', 'max:2048']);
    }
}
```

## Relationship Components

### Advanced Relationship Handling

```php
<?php
// app/Filament/Components/RelationshipComponents.php

namespace App\Filament\Components;

use Filament\Forms;

class RelationshipComponents
{
    /**
     * Enhanced select with creation
     */
    public static function enhancedSelect(string $name, string $relationship, string $titleAttribute): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->label(str($name)->title())
            ->relationship($relationship, $titleAttribute)
            ->searchable()
            ->preload()
            ->createOptionForm([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(500),
            ]);
    }

    /**
     * Multi-select with preview
     */
    public static function multiSelectWithPreview(string $name, string $relationship): Forms\Components\Group
    {
        return Forms\Components\Group::make([
            Forms\Components\CheckboxList::make($name)
                ->label(str($name)->title())
                ->relationship($relationship, 'name')
                ->columns(2)
                ->gridDirection('row')
                ->live(),

            Forms\Components\Placeholder::make($name . '_count')
                ->label('Selected Count')
                ->content(function (Forms\Get $get) use ($name): string {
                    $selected = $get($name) ?? [];
                    $count = count($selected);
                    return $count . ' item' . ($count !== 1 ? 's' : '') . ' selected';
                })
                ->visible(fn (Forms\Get $get) => $get($name)),
        ])->columns(2);
    }
}
```

## Testing

### Form Component Testing

```php
<?php
// tests/Feature/Filament/FormComponentsTest.php

use App\Filament\Components\StandardInputs;
use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    public function test_email_input_validation(): void
    {
        $component = StandardInputs::emailInput();

        expect($component->getName())->toBe('email');
        expect($component->isRequired())->toBeTrue();
        expect($component->getMaxLength())->toBe(255);
    }

    public function test_currency_input_formatting(): void
    {
        $component = StandardInputs::currencyInput('price');

        expect($component->getName())->toBe('price');
        expect($component->getPrefix())->toBe('$');
        expect($component->getStep())->toBe(0.01);
    }

    public function test_phone_input_masking(): void
    {
        $component = StandardInputs::phoneInput();

        expect($component->getName())->toBe('phone');
        expect($component->getMask())->toBe('(999) 999-9999');
    }
}
```

## Best Practices

### Form Component Guidelines

1. **Consistency**: Use standardized components across the application
2. **Accessibility**: Ensure all components meet WCAG 2.1 AA standards
3. **Validation**: Implement comprehensive client and server-side validation
4. **Performance**: Optimize live updates and reactive behavior
5. **User Experience**: Provide clear feedback and helpful error messages
6. **Security**: Validate and sanitize all user inputs

### Performance Optimization

```php
<?php
// Optimized form component patterns

class OptimizedComponents
{
    /**
     * Debounced search select
     */
    public static function debouncedSearchSelect(string $name, string $relationship): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->relationship($relationship, 'name')
            ->searchable()
            ->searchDebounce(500) // 500ms debounce
            ->searchingMessage('Searching...')
            ->noSearchResultsMessage('No results found.')
            ->loadingMessage('Loading...');
    }

    /**
     * Lazy-loaded options
     */
    public static function lazySelect(string $name, string $model): Forms\Components\Select
    {
        return Forms\Components\Select::make($name)
            ->options(function () use ($model) {
                return cache()->remember(
                    "select_options_{$model}",
                    now()->addMinutes(30),
                    fn () => app("App\\Models\\{$model}")::pluck('name', 'id')
                );
            })
            ->searchable();
    }
}
```

## Navigation

**← Previous:** [Users Resource Guide](110-users-resource.md)
**Next →** [Table Features Guide](130-table-features.md)

**Related Guides:**

- [Resource Architecture](000-resources-index.md) - Foundation resource patterns
- [Table Features Guide](130-table-features.md) - Advanced table configuration and features
- [Bulk Operations Guide](140-bulk-operations.md) - Bulk actions and operations

---

*This guide provides comprehensive form component implementation for Filament 4 in the Chinook application. Each pattern
includes validation, accessibility, and performance considerations for robust form handling.*
