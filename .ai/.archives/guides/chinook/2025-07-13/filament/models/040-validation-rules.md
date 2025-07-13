# Filament Validation Rules Guide

## Overview

This guide covers comprehensive form validation and business rules implementation in Filament resources for the Chinook database, including model-level validation, custom rules, and business logic enforcement.

## Table of Contents

- [Overview](#overview)
- [Model Validation](#model-validation)
- [Form Validation](#form-validation)
- [Custom Validation Rules](#custom-validation-rules)
- [Business Logic Validation](#business-logic-validation)
- [Relationship Validation](#relationship-validation)
- [File Upload Validation](#file-upload-validation)
- [Real-time Validation](#real-time-validation)
- [Error Handling](#error-handling)
- [Best Practices](#best-practices)

## Model Validation

### Base Model Rules

```php
// In Artist model
public static function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:120', 'unique:artists,name'],
        'public_id' => ['required', 'string', 'unique:artists,public_id'],
        'slug' => ['required', 'string', 'unique:artists,slug'],
        'bio' => ['nullable', 'string', 'max:1000'],
        'website' => ['nullable', 'url', 'max:255'],
        'formed_year' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
    ];
}

// Update rules for existing records
public static function updateRules(int $id): array
{
    return [
        'name' => ['required', 'string', 'max:120', "unique:artists,name,{$id}"],
        'public_id' => ['required', 'string', "unique:artists,public_id,{$id}"],
        'slug' => ['required', 'string', "unique:artists,slug,{$id}"],
        // ... other rules
    ];
}
```

### Track Model Validation

```php
// In Track model
public static function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:200'],
        'album_id' => ['required', 'exists:albums,id'],
        'media_type_id' => ['required', 'exists:media_types,id'],
        'genre_id' => ['nullable', 'exists:genres,id'],
        'composer' => ['nullable', 'string', 'max:220'],
        'milliseconds' => ['required', 'integer', 'min:1'],
        'bytes' => ['nullable', 'integer', 'min:0'],
        'unit_price' => ['required', 'decimal:2', 'min:0', 'max:999.99'],
        'track_number' => ['nullable', 'integer', 'min:1'],
    ];
}
```

## Form Validation

### Resource Form Validation

```php
// In ArtistResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')
            ->required()
            ->maxLength(120)
            ->unique(ignoreRecord: true)
            ->live(onBlur: true)
            ->afterStateUpdated(function (string $context, $state, callable $set) {
                if ($context === 'create') {
                    $set('slug', Str::slug($state));
                }
            }),
            
        TextInput::make('slug')
            ->required()
            ->maxLength(120)
            ->unique(ignoreRecord: true)
            ->rules(['alpha_dash'])
            ->helperText('URL-friendly identifier'),
            
        Textarea::make('bio')
            ->maxLength(1000)
            ->rows(4)
            ->columnSpanFull(),
            
        TextInput::make('website')
            ->url()
            ->maxLength(255)
            ->prefix('https://')
            ->placeholder('example.com'),
            
        TextInput::make('formed_year')
            ->numeric()
            ->minValue(1900)
            ->maxValue(date('Y'))
            ->placeholder('YYYY'),
    ]);
}
```

### Album Form Validation

```php
// In AlbumResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('title')
            ->required()
            ->maxLength(160)
            ->live(onBlur: true),
            
        Select::make('artist_id')
            ->relationship('artist', 'name')
            ->required()
            ->searchable()
            ->preload()
            ->createOptionForm([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120),
            ]),
            
        DatePicker::make('release_date')
            ->maxDate(now())
            ->displayFormat('Y-m-d'),
            
        TextInput::make('unit_price')
            ->numeric()
            ->minValue(0)
            ->maxValue(999.99)
            ->step(0.01)
            ->prefix('$'),
    ]);
}
```

## Custom Validation Rules

### Business Rule Validation

```php
// Custom validation rule for track duration
class ValidTrackDuration implements Rule
{
    public function passes($attribute, $value): bool
    {
        // Track must be between 10 seconds and 30 minutes
        return $value >= 10000 && $value <= 1800000;
    }
    
    public function message(): string
    {
        return 'Track duration must be between 10 seconds and 30 minutes.';
    }
}

// Usage in form
TextInput::make('milliseconds')
    ->required()
    ->numeric()
    ->rules([new ValidTrackDuration()])
    ->helperText('Duration in milliseconds (10s - 30min)'),
```

### Category Hierarchy Validation

```php
// Prevent circular references in category hierarchy
class NoCategoryCircularReference implements Rule
{
    private int $categoryId;
    
    public function __construct(int $categoryId)
    {
        $this->categoryId = $categoryId;
    }
    
    public function passes($attribute, $value): bool
    {
        if (!$value) return true;
        
        // Check if parent would create circular reference
        $parent = Category::find($value);
        while ($parent) {
            if ($parent->id === $this->categoryId) {
                return false;
            }
            $parent = $parent->parent;
        }
        
        return true;
    }
    
    public function message(): string
    {
        return 'Cannot set parent category that would create a circular reference.';
    }
}
```

### Price Validation

```php
// Ensure album price is reasonable compared to track prices
class ReasonableAlbumPrice implements Rule
{
    private int $albumId;
    
    public function __construct(int $albumId)
    {
        $this->albumId = $albumId;
    }
    
    public function passes($attribute, $value): bool
    {
        $album = Album::with('tracks')->find($this->albumId);
        if (!$album || $album->tracks->isEmpty()) {
            return true;
        }
        
        $totalTrackPrice = $album->tracks->sum('unit_price');
        $maxReasonablePrice = $totalTrackPrice * 0.9; // 10% discount max
        
        return $value <= $maxReasonablePrice;
    }
    
    public function message(): string
    {
        return 'Album price should not exceed 90% of individual track prices.';
    }
}
```

## Business Logic Validation

### Employee Hierarchy Validation

```php
// In EmployeeResource
Select::make('reports_to')
    ->relationship('supervisor', 'full_name')
    ->searchable()
    ->rules([
        function () {
            return function (string $attribute, $value, Closure $fail) {
                if (!$value) return;
                
                $employee = Employee::find(request()->route('record'));
                if (!$employee) return;
                
                // Prevent self-reporting
                if ($value == $employee->id) {
                    $fail('Employee cannot report to themselves.');
                    return;
                }
                
                // Prevent circular reporting
                $supervisor = Employee::find($value);
                while ($supervisor && $supervisor->reports_to) {
                    if ($supervisor->reports_to == $employee->id) {
                        $fail('This would create a circular reporting structure.');
                        return;
                    }
                    $supervisor = $supervisor->supervisor;
                }
            };
        },
    ]),
```

### Invoice Validation

```php
// In InvoiceResource
public static function form(Form $form): Form
{
    return $form->schema([
        Select::make('customer_id')
            ->relationship('customer', 'full_name')
            ->required()
            ->searchable(),
            
        DatePicker::make('invoice_date')
            ->required()
            ->maxDate(now())
            ->default(now()),
            
        Repeater::make('invoiceLines')
            ->relationship()
            ->schema([
                Select::make('track_id')
                    ->relationship('track', 'name')
                    ->required()
                    ->searchable(),
                    
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $quantity = $get('quantity') ?? 1;
                        $set('total', $state * $quantity);
                    }),
                    
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $unitPrice = $get('unit_price') ?? 0;
                        $set('total', $state * $unitPrice);
                    }),
                    
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->disabled()
                    ->dehydrated(),
            ])
            ->minItems(1)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => 
                isset($state['track_id']) ? Track::find($state['track_id'])?->name : null
            ),
    ]);
}
```

## Relationship Validation

### Polymorphic Category Validation

```php
// Ensure categories match the model type
Select::make('categories')
    ->relationship('categories', 'name')
    ->multiple()
    ->searchable()
    ->rules([
        function () {
            return function (string $attribute, $value, Closure $fail) {
                if (!is_array($value)) return;
                
                $modelType = request()->route('resource');
                $allowedTypes = match($modelType) {
                    'artists' => [CategoryType::GENRE, CategoryType::ERA],
                    'albums' => [CategoryType::GENRE, CategoryType::MOOD, CategoryType::ERA],
                    'tracks' => [CategoryType::GENRE, CategoryType::MOOD, CategoryType::INSTRUMENT],
                    default => []
                };
                
                $categories = Category::whereIn('id', $value)->get();
                foreach ($categories as $category) {
                    if (!in_array($category->type, $allowedTypes)) {
                        $fail("Category '{$category->name}' is not valid for this model type.");
                        return;
                    }
                }
            };
        },
    ]),
```

## File Upload Validation

### Media File Validation

```php
// In TrackResource for audio files
SpatieMediaLibraryFileUpload::make('audio_file')
    ->collection('audio')
    ->acceptedFileTypes(['audio/mpeg', 'audio/wav', 'audio/flac'])
    ->maxSize(50 * 1024) // 50MB
    ->rules([
        function () {
            return function (string $attribute, $value, Closure $fail) {
                if (!$value) return;
                
                // Validate audio duration
                $duration = $this->getAudioDuration($value);
                if ($duration < 10 || $duration > 1800) {
                    $fail('Audio file must be between 10 seconds and 30 minutes.');
                }
            };
        },
    ]),

// Album cover validation
SpatieMediaLibraryFileUpload::make('cover_art')
    ->collection('covers')
    ->image()
    ->imageEditor()
    ->imageResizeMode('cover')
    ->imageCropAspectRatio('1:1')
    ->imageResizeTargetWidth('500')
    ->imageResizeTargetHeight('500')
    ->maxSize(5 * 1024), // 5MB
```

## Real-time Validation

### Live Validation

```php
TextInput::make('email')
    ->email()
    ->required()
    ->unique(ignoreRecord: true)
    ->live(onBlur: true)
    ->afterStateUpdated(function (string $context, $state, callable $set, callable $get) {
        // Real-time email validation
        if ($state && !filter_var($state, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        
        // Check if email exists in external system
        if ($this->emailExistsInExternalSystem($state)) {
            $set('external_user_id', $this->getExternalUserId($state));
        }
    }),
```

## Error Handling

### Custom Error Messages

```php
// In Resource class
protected function getFormActions(): array
{
    return [
        Action::make('save')
            ->action(function (array $data) {
                try {
                    $this->record->update($data);
                    Notification::make()
                        ->title('Saved successfully')
                        ->success()
                        ->send();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Validation Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title('Error')
                        ->body('An unexpected error occurred.')
                        ->danger()
                        ->send();
                }
            }),
    ];
}
```

## Best Practices

### Validation Strategy

- Implement validation at multiple levels (client, form, model, database)
- Use appropriate validation rules for each field type
- Provide clear, helpful error messages
- Implement real-time validation for better UX

### Performance

- Use database constraints for data integrity
- Cache validation results where appropriate
- Optimize complex validation queries
- Use form state management efficiently

### Security

- Validate all user inputs
- Sanitize data before validation
- Use proper authorization checks
- Implement rate limiting for validation-heavy operations

### User Experience

- Provide immediate feedback with live validation
- Use helper text to guide users
- Group related validation errors
- Implement progressive disclosure for complex forms

---

## Navigation

**← Previous:** [Relationship Handling](030-relationship-handling.md)

**Next →** [Hierarchical Models](050-hierarchical-models.md)
