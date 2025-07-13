# Casting Patterns Guide

## Table of Contents

- [Overview](#overview)
- [Laravel 12 Modern Casting](#laravel-12-modern-casting)
- [Basic Casting Patterns](#basic-casting-patterns)
- [Advanced Casting Techniques](#advanced-casting-techniques)
- [Custom Cast Classes](#custom-cast-classes)
- [JSON Casting](#json-casting)
- [Date and Time Casting](#date-and-time-casting)
- [Enum Casting](#enum-casting)
- [Performance Considerations](#performance-considerations)
- [Testing Cast Implementations](#testing-cast-implementations)
- [Best Practices](#best-practices)
- [Navigation](#navigation)

## Overview

This guide covers modern casting patterns for Laravel 12 models in the Chinook application. Casting provides a clean way to transform model attributes when retrieving or storing data, ensuring type safety and consistent data handling throughout the application.

**🚀 Key Features:**
- **Laravel 12 Modern Syntax**: Using the new `casts()` method instead of `$casts` property
- **Type Safety**: Ensuring proper data types throughout the application
- **Custom Casts**: Creating reusable casting logic for complex data types
- **Performance Optimization**: Efficient casting strategies for large datasets
- **WCAG 2.1 AA Compliance**: Accessible data presentation patterns

## Laravel 12 Modern Casting

### Using the casts() Method

Laravel 12 introduces the modern `casts()` method to replace the traditional `$casts` property:

```php
<?php
// app/Models/Artist.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Casts\FullNameCast;
use App\Enums\ArtistStatus;

class Artist extends Model
{
    use HasFactory, SoftDeletes, HasTags, HasSecondaryUniqueKey, HasSlug;

    /**
     * Modern Laravel 12 casting using casts() method
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'bio' => 'string',
            'formed_date' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'social_links' => 'collection',
            'status' => ArtistStatus::class,
            'full_name' => FullNameCast::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Computed attribute using Attribute class
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['name'] ?? 'Unknown Artist'
        );
    }
}
```

## Basic Casting Patterns

### Primitive Type Casting

```php
<?php
// app/Models/Track.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected function casts(): array
    {
        return [
            // Numeric casting
            'id' => 'integer',
            'album_id' => 'integer',
            'duration_ms' => 'integer',
            'file_size' => 'integer',
            'price' => 'decimal:2',
            'rating' => 'float',
            
            // String casting
            'name' => 'string',
            'composer' => 'string',
            'file_path' => 'string',
            
            // Boolean casting
            'is_explicit' => 'boolean',
            'is_featured' => 'boolean',
            'is_downloadable' => 'boolean',
            
            // Date casting
            'release_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
```

### Collection and Array Casting

```php
<?php
// app/Models/Album.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Album extends Model
{
    protected function casts(): array
    {
        return [
            // Array casting for JSON fields
            'metadata' => 'array',
            'credits' => 'array',
            'track_listing' => 'array',
            
            // Collection casting for complex data
            'featured_tracks' => 'collection',
            'bonus_content' => 'collection',
            
            // Encrypted casting for sensitive data
            'licensing_info' => 'encrypted:array',
        ];
    }

    /**
     * Accessor for formatted track listing
     */
    protected function formattedTrackListing(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $tracks = $attributes['track_listing'] ?? [];
                return collect($tracks)->map(function ($track, $index) {
                    return [
                        'position' => $index + 1,
                        'title' => $track['title'] ?? 'Unknown',
                        'duration' => $track['duration'] ?? '0:00',
                    ];
                });
            }
        );
    }
}
```

## Advanced Casting Techniques

### Custom Cast Classes

```php
<?php
// app/Casts/DurationCast.php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class DurationCast implements CastsAttributes
{
    /**
     * Cast the given value to a formatted duration string
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (is_null($value)) {
            return '0:00';
        }

        $seconds = intval($value / 1000); // Convert milliseconds to seconds
        $minutes = intval($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Prepare the given value for storage
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if (is_string($value) && preg_match('/^(\d+):(\d{2})$/', $value, $matches)) {
            $minutes = intval($matches[1]);
            $seconds = intval($matches[2]);
            return ($minutes * 60 + $seconds) * 1000; // Convert to milliseconds
        }

        return intval($value);
    }
}
```

### Money and Currency Casting

```php
<?php
// app/Casts/MoneyCast.php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    public function __construct(
        protected string $currency = 'USD'
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [
            'amount' => $value ? number_format($value / 100, 2) : '0.00',
            'currency' => $this->currency,
            'formatted' => $value ? '$' . number_format($value / 100, 2) : '$0.00',
        ];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        if (is_array($value)) {
            return intval(($value['amount'] ?? 0) * 100);
        }

        return intval($value * 100);
    }
}
```

## JSON Casting

### Complex JSON Structures

```php
<?php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Casts\AddressCast;

class Customer extends Model
{
    protected function casts(): array
    {
        return [
            // JSON casting with validation
            'preferences' => 'array',
            'billing_address' => AddressCast::class,
            'shipping_address' => AddressCast::class,

            // Encrypted JSON for sensitive data
            'payment_methods' => 'encrypted:array',
        ];
    }
}

// app/Casts/AddressCast.php
class AddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $address = json_decode($value, true) ?? [];
        
        return [
            'street' => $address['street'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'postal_code' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'US',
            'formatted' => $this->formatAddress($address),
        ];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode([
            'street' => $value['street'] ?? '',
            'city' => $value['city'] ?? '',
            'state' => $value['state'] ?? '',
            'postal_code' => $value['postal_code'] ?? '',
            'country' => $value['country'] ?? 'US',
        ]);
    }

    private function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['street'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['postal_code'] ?? '',
        ]);

        return implode(', ', $parts);
    }
}
```

## Date and Time Casting

### Advanced Date Handling

```php
<?php
// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Invoice extends Model
{
    protected function cast(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Computed attribute for days until due
     */
    protected function daysUntilDue(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!$attributes['due_date']) {
                    return null;
                }

                $dueDate = Carbon::parse($attributes['due_date']);
                $now = Carbon::now();

                return $dueDate->diffInDays($now, false);
            }
        );
    }

    /**
     * Computed attribute for payment status
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if ($attributes['paid_at']) {
                    return 'paid';
                }

                $dueDate = Carbon::parse($attributes['due_date']);
                return $dueDate->isPast() ? 'overdue' : 'pending';
            }
        );
    }
}
```

## Enum Casting

### Modern Enum Integration

```php
<?php
// app/Enums/MediaType.php

namespace App\Enums;

enum MediaType: string
{
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case PODCAST = 'podcast';
    case AUDIOBOOK = 'audiobook';

    public function label(): string
    {
        return match($this) {
            self::AUDIO => 'Audio Track',
            self::VIDEO => 'Music Video',
            self::PODCAST => 'Podcast Episode',
            self::AUDIOBOOK => 'Audiobook Chapter',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::AUDIO => 'musical-note',
            self::VIDEO => 'video-camera',
            self::PODCAST => 'microphone',
            self::AUDIOBOOK => 'book-open',
        ];
    }
}

// app/Models/MediaType.php
class MediaType extends Model
{
    protected function casts(): array
    {
        return [
            'type' => \App\Enums\MediaType::class,
            'is_active' => 'boolean',
        ];
    }
}
```

## Performance Considerations

### Optimizing Cast Performance

```php
<?php
// app/Models/Track.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    /**
     * Optimize casting for large datasets
     */
    protected function casts(): array
    {
        return [
            // Use appropriate precision for decimals
            'price' => 'decimal:2', // Not 'decimal:10'

            // Cache expensive computations
            'duration_formatted' => 'string', // Pre-computed in database

            // Use integers for better performance
            'duration_ms' => 'integer',
            'file_size_bytes' => 'integer',
        ];
    }

    /**
     * Lazy-loaded computed attributes
     */
    protected function expensiveComputation(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // Only compute when accessed
                return cache()->remember(
                    "track.{$attributes['id']}.expensive_computation",
                    3600,
                    fn () => $this->performExpensiveCalculation($attributes)
                );
            }
        )->shouldCache();
    }
}
```

## Testing Cast Implementations

### Comprehensive Cast Testing

```php
<?php
// tests/Unit/Casts/DurationCastTest.php

use App\Casts\DurationCast;
use App\Models\Track;
use Tests\TestCase;

class DurationCastTest extends TestCase
{
    public function test_duration_cast_formats_milliseconds_correctly(): void
    {
        $track = Track::factory()->create([
            'duration_ms' => 180000, // 3 minutes
        ]);

        expect($track->duration_formatted)->toBe('3:00');
    }

    public function test_duration_cast_handles_seconds_correctly(): void
    {
        $track = Track::factory()->create([
            'duration_ms' => 195000, // 3 minutes 15 seconds
        ]);

        expect($track->duration_formatted)->toBe('3:15');
    }

    public function test_duration_cast_stores_formatted_time_as_milliseconds(): void
    {
        $track = new Track();
        $track->duration_formatted = '4:30';
        $track->save();

        expect($track->duration_ms)->toBe(270000);
    }
}
```

## Best Practices

### Casting Guidelines

1. **Use Modern Syntax**: Always use the `casts()` method in Laravel 12
2. **Type Safety**: Ensure proper type casting for all attributes
3. **Performance**: Consider the performance impact of complex casts
4. **Validation**: Validate data before casting in custom cast classes
5. **Testing**: Write comprehensive tests for custom cast implementations
6. **Documentation**: Document complex casting logic clearly

### Security Considerations

```php
<?php
// Secure casting patterns

class SecureModel extends Model
{
    protected function cast(): array
    {
        return [
            // Use encrypted casting for sensitive data
            'ssn' => 'encrypted',
            'credit_card' => 'encrypted',
            
            // Validate and sanitize user input
            'user_input' => 'string', // Always validate in mutators
        ];
    }

    /**
     * Secure mutator with validation
     */
    protected function userInput(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                // Sanitize and validate input
                $sanitized = strip_tags($value);
                $validated = substr($sanitized, 0, 255);
                
                return $validated;
            }
        );
    }
}
```

## Navigation

**← Previous:** [Required Traits Guide](020-required-traits.md)
**Next →** [Relationship Patterns Guide](040-relationship-patterns.md)

**Related Guides:**
- [Model Architecture Guide](010-model-architecture.md) - Foundation model patterns
- [Validation Rules Guide](040-validation-rules.md) - Data validation strategies
- [Hierarchical Models Guide](050-hierarchical-models.md) - Tree structure patterns

---

*This guide provides comprehensive casting patterns for Laravel 12 models in the Chinook application. Each pattern includes practical examples, performance considerations, and testing strategies to ensure robust data handling throughout the application.*
