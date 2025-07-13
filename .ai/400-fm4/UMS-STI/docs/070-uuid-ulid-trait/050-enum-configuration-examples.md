# Enhanced PHP Enum Configuration Examples

## Executive Summary
This document provides comprehensive examples of how to use the enhanced `SecondaryKeyType` PHP enum with the `HasSecondaryUniqueKey` trait. It demonstrates configuration patterns, metadata access, dynamic type selection, and integration with various application scenarios.

## Learning Objectives
After reviewing these examples, you will:
- Configure secondary key types using the enhanced PHP enum
- Access and utilize enum metadata (colors, labels, descriptions)
- Implement dynamic type selection based on application requirements
- Integrate enum-based configuration with existing Laravel applications
- Create custom configuration patterns for different environments

## Prerequisite Knowledge
- PHP 8.1+ enum syntax and features
- Laravel Eloquent model configuration
- UMS-STI architecture basics
- Secondary key trait fundamentals

## Basic Enum Configuration

### 1. Simple Model Configuration

```php
<?php

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasSecondaryUniqueKey;

    // Default configuration - uses Snowflake for optimal performance
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::SNOWFLAKE;
    protected string $secondaryKeyColumn = 'public_id';

    protected $fillable = ['name', 'description', 'price'];
}

// Usage
$product = Product::create([
    'name' => 'Widget',
    'description' => 'A useful widget',
    'price' => 19.99
]);

echo "Product ID: {$product->public_id}\n";
echo "Key Type: {$product->getSecondaryKeyType()->label()}\n";
echo "Storage: {$product->getSecondaryKeyType()->storageInfo()['bytes']} bytes\n";
```

### 2. Override Default Configuration

```php
<?php

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Model;

class LegacyUser extends Model
{
    use HasSecondaryUniqueKey;

    // Override default to use UUID for legacy compatibility
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::UUID;
    protected string $secondaryKeyColumn = 'external_uuid';

    protected $fillable = ['name', 'email'];

    public function getKeyTypeDisplay(): string
    {
        $type = $this->getSecondaryKeyType();
        return "{$type->label()} - {$type->description()}";
    }
}
```

## Advanced Configuration Patterns

### 3. Environment-Based Configuration

```php
<?php

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    use HasSecondaryUniqueKey;

    protected $fillable = ['name', 'expires_at'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Configure based on environment
        $this->setSecondaryKeyType($this->determineKeyType());
    }

    private function determineKeyType(): SecondaryKeyType
    {
        return match (config('app.env')) {
            'production' => config('app.distributed_mode') 
                ? SecondaryKeyType::SNOWFLAKE 
                : SecondaryKeyType::UUID,
            'staging' => SecondaryKeyType::ULID,
            'local' => SecondaryKeyType::UUID,
            default => SecondaryKeyType::default(),
        };
    }

    public function getEnvironmentInfo(): array
    {
        $keyType = $this->getSecondaryKeyType();
        return [
            'environment' => config('app.env'),
            'key_type' => $keyType->value,
            'label' => $keyType->label(),
            'color' => $keyType->color(),
            'use_cases' => $keyType->useCases(),
        ];
    }
}
```

### 4. Feature Flag Configuration

```php
<?php

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasSecondaryUniqueKey;

    protected $fillable = ['title', 'message', 'user_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Configure based on feature flags
        $this->setSecondaryKeyType($this->getFeatureBasedKeyType());
    }

    private function getFeatureBasedKeyType(): SecondaryKeyType
    {
        // Check feature flags
        if (config('features.distributed_notifications')) {
            return SecondaryKeyType::SNOWFLAKE;
        }

        if (config('features.high_volume_notifications')) {
            return SecondaryKeyType::ULID;
        }

        return SecondaryKeyType::UUID;
    }

    public function getFeatureConfiguration(): array
    {
        $keyType = $this->getSecondaryKeyType();
        
        return [
            'distributed_mode' => config('features.distributed_notifications'),
            'high_volume_mode' => config('features.high_volume_notifications'),
            'selected_type' => $keyType->value,
            'type_benefits' => $keyType->useCases(),
            'storage_efficiency' => $keyType->storageInfo(),
        ];
    }
}
```

## Metadata Access Patterns

### 5. UI Integration with Colors and Labels

```php
<?php

namespace App\Http\Controllers;

use App\Enums\SecondaryKeyType;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class KeyTypeController extends Controller
{
    public function getKeyTypeOptions(): JsonResponse
    {
        $options = [];
        
        foreach (SecondaryKeyType::cases() as $keyType) {
            $options[] = [
                'value' => $keyType->value,
                'label' => $keyType->label(),
                'color' => $keyType->color(),
                'description' => $keyType->description(),
                'use_cases' => $keyType->useCases(),
                'storage' => $keyType->storageInfo(),
            ];
        }

        return response()->json([
            'key_types' => $options,
            'default' => SecondaryKeyType::default()->value,
        ]);
    }

    public function getUserKeyTypeStats(): JsonResponse
    {
        $stats = [];
        
        foreach (SecondaryKeyType::cases() as $keyType) {
            $count = User::whereRaw('JSON_EXTRACT(key_type_info, "$.type") = ?', [$keyType->value])->count();
            
            $stats[] = [
                'type' => $keyType->value,
                'label' => $keyType->label(),
                'color' => $keyType->color(),
                'count' => $count,
                'percentage' => $count > 0 ? round(($count / User::count()) * 100, 2) : 0,
            ];
        }

        return response()->json(['stats' => $stats]);
    }
}
```

### 6. Monitoring and Analytics

```php
<?php

namespace App\Services;

use App\Enums\SecondaryKeyType;
use App\Models\User;
use Illuminate\Support\Collection;

class KeyTypeAnalyticsService
{
    public function getKeyTypeDistribution(): array
    {
        $distribution = [];
        
        foreach (SecondaryKeyType::cases() as $keyType) {
            $models = $this->getModelsUsingKeyType($keyType);
            
            $distribution[$keyType->value] = [
                'label' => $keyType->label(),
                'color' => $keyType->color(),
                'count' => $models->count(),
                'models' => $models->pluck('class')->unique()->values(),
                'storage_efficiency' => $keyType->storageInfo()['bytes'],
                'use_cases' => $keyType->useCases(),
            ];
        }

        return $distribution;
    }

    public function getPerformanceMetrics(): array
    {
        $metrics = [];
        
        foreach (SecondaryKeyType::cases() as $keyType) {
            $storageInfo = $keyType->storageInfo();
            
            $metrics[$keyType->value] = [
                'label' => $keyType->label(),
                'color' => $keyType->color(),
                'storage_bytes' => $storageInfo['bytes'],
                'format' => $storageInfo['format'],
                'encoding' => $storageInfo['encoding'],
                'estimated_index_size' => $this->estimateIndexSize($keyType),
                'query_performance' => $this->getQueryPerformanceRating($keyType),
            ];
        }

        return $metrics;
    }

    private function getModelsUsingKeyType(SecondaryKeyType $keyType): Collection
    {
        // This would be implemented based on your specific tracking needs
        // Could use reflection, database queries, or configuration files
        return collect();
    }

    private function estimateIndexSize(SecondaryKeyType $keyType): string
    {
        $storageInfo = $keyType->storageInfo();
        $bytesPerRecord = $storageInfo['bytes'];
        
        // Rough estimation for 1M records
        $totalBytes = $bytesPerRecord * 1000000;
        
        return $this->formatBytes($totalBytes);
    }

    private function getQueryPerformanceRating(SecondaryKeyType $keyType): string
    {
        return match ($keyType) {
            SecondaryKeyType::SNOWFLAKE => 'Excellent (Integer operations)',
            SecondaryKeyType::ULID => 'Very Good (Sequential ordering)',
            SecondaryKeyType::UUID => 'Good (Standard compliance)',
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }
}
```

## Configuration File Examples

### 7. Laravel Configuration File

```php
<?php

// config/secondary_keys.php

use App\Enums\SecondaryKeyType;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Secondary Key Type
    |--------------------------------------------------------------------------
    |
    | This option controls the default secondary key type for models that
    | don't explicitly specify a type. Snowflake is recommended for new
    | applications due to its performance and distributed system benefits.
    |
    */
    'default_type' => env('SECONDARY_KEY_DEFAULT_TYPE', SecondaryKeyType::SNOWFLAKE->value),

    /*
    |--------------------------------------------------------------------------
    | Default Column Name
    |--------------------------------------------------------------------------
    |
    | The default column name for secondary keys across all models.
    |
    */
    'default_column' => env('SECONDARY_KEY_DEFAULT_COLUMN', 'public_id'),

    /*
    |--------------------------------------------------------------------------
    | Model-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Override the default configuration for specific models.
    |
    */
    'models' => [
        'User' => [
            'type' => SecondaryKeyType::UUID->value, // Standards compliance
            'column' => 'public_id',
        ],
        'AdminUser' => [
            'type' => SecondaryKeyType::UUID->value, // Security and compliance
            'column' => 'public_id',
        ],
        'GuestUser' => [
            'type' => SecondaryKeyType::ULID->value, // High volume, storage efficiency
            'column' => 'public_id',
        ],
        'SystemUser' => [
            'type' => SecondaryKeyType::SNOWFLAKE->value, // Distributed systems
            'column' => 'public_id',
        ],
        'ActivityLog' => [
            'type' => SecondaryKeyType::ULID->value, // Time-series data
            'column' => 'log_id',
        ],
        'ApiToken' => [
            'type' => SecondaryKeyType::UUID->value, // Security-critical
            'column' => 'token_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | Override configurations based on the application environment.
    |
    */
    'environments' => [
        'production' => [
            'prefer_snowflake' => env('PRODUCTION_PREFER_SNOWFLAKE', true),
            'distributed_mode' => env('PRODUCTION_DISTRIBUTED_MODE', false),
        ],
        'staging' => [
            'prefer_snowflake' => false,
            'distributed_mode' => false,
        ],
        'local' => [
            'prefer_snowflake' => false,
            'distributed_mode' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Feature flags that influence secondary key type selection.
    |
    */
    'features' => [
        'distributed_notifications' => env('FEATURE_DISTRIBUTED_NOTIFICATIONS', false),
        'high_volume_logging' => env('FEATURE_HIGH_VOLUME_LOGGING', false),
        'legacy_uuid_support' => env('FEATURE_LEGACY_UUID_SUPPORT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Validation rules for each key type.
    |
    */
    'validation' => [
        SecondaryKeyType::UUID->value => [
            'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
        ],
        SecondaryKeyType::ULID->value => [
            'regex:/^[0123456789ABCDEFGHJKMNPQRSTVWXYZ]{26}$/i'
        ],
        SecondaryKeyType::SNOWFLAKE->value => [
            'integer',
            'min:1'
        ],
    ],
];
```

### 8. Environment Configuration

```bash
# .env.example

# Secondary Key Configuration
SECONDARY_KEY_DEFAULT_TYPE=snowflake
SECONDARY_KEY_DEFAULT_COLUMN=public_id

# Production Settings
PRODUCTION_PREFER_SNOWFLAKE=true
PRODUCTION_DISTRIBUTED_MODE=true

# Feature Flags
FEATURE_DISTRIBUTED_NOTIFICATIONS=false
FEATURE_HIGH_VOLUME_LOGGING=false
FEATURE_LEGACY_UUID_SUPPORT=true

# Snowflake Configuration (for glhd/bits)
BITS_DATACENTER_ID=0
BITS_WORKER_ID=0
BITS_EPOCH="2023-01-01"
```

## Testing Examples

### 9. Enum Testing

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\SecondaryKeyType;
use PHPUnit\Framework\TestCase;

class SecondaryKeyTypeTest extends TestCase
{
    /** @test */
    public function it_has_correct_enum_values(): void
    {
        $this->assertEquals('uuid', SecondaryKeyType::UUID->value);
        $this->assertEquals('ulid', SecondaryKeyType::ULID->value);
        $this->assertEquals('snowflake', SecondaryKeyType::SNOWFLAKE->value);
    }

    /** @test */
    public function it_provides_correct_labels(): void
    {
        $this->assertEquals('UUID v7', SecondaryKeyType::UUID->label());
        $this->assertEquals('ULID', SecondaryKeyType::ULID->label());
        $this->assertEquals('Snowflake', SecondaryKeyType::SNOWFLAKE->label());
    }

    /** @test */
    public function it_provides_valid_colors(): void
    {
        foreach (SecondaryKeyType::cases() as $keyType) {
            $color = $keyType->color();
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $color);
        }
    }

    /** @test */
    public function it_provides_storage_information(): void
    {
        $uuidStorage = SecondaryKeyType::UUID->storageInfo();
        $this->assertEquals(36, $uuidStorage['length']);
        $this->assertEquals('string', $uuidStorage['format']);
        $this->assertEquals(16, $uuidStorage['bytes']);

        $snowflakeStorage = SecondaryKeyType::SNOWFLAKE->storageInfo();
        $this->assertEquals(19, $snowflakeStorage['length']);
        $this->assertEquals('integer', $snowflakeStorage['format']);
        $this->assertEquals(8, $snowflakeStorage['bytes']);
    }

    /** @test */
    public function it_has_snowflake_as_default(): void
    {
        $this->assertEquals(SecondaryKeyType::SNOWFLAKE, SecondaryKeyType::default());
    }

    /** @test */
    public function it_provides_use_cases(): void
    {
        foreach (SecondaryKeyType::cases() as $keyType) {
            $useCases = $keyType->useCases();
            $this->assertIsArray($useCases);
            $this->assertNotEmpty($useCases);
        }
    }
}
```

### 10. Integration Testing

```php
<?php

namespace Tests\Feature\Traits;

use App\Enums\SecondaryKeyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasSecondaryUniqueKeyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_default_snowflake_type(): void
    {
        $user = User::factory()->create();
        
        $this->assertEquals(SecondaryKeyType::SNOWFLAKE, $user->getSecondaryKeyType());
        $this->assertNotNull($user->public_id);
        $this->assertIsNumeric($user->public_id);
    }

    /** @test */
    public function it_can_override_key_type(): void
    {
        $user = new User();
        $user->setSecondaryKeyType(SecondaryKeyType::UUID);
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->save();

        $this->assertEquals(SecondaryKeyType::UUID, $user->getSecondaryKeyType());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $user->public_id
        );
    }

    /** @test */
    public function it_provides_key_type_metadata(): void
    {
        $user = User::factory()->create();
        $keyInfo = $user->getKeyTypeInfo();

        $this->assertArrayHasKey('type', $keyInfo);
        $this->assertArrayHasKey('label', $keyInfo);
        $this->assertArrayHasKey('color', $keyInfo);
        $this->assertArrayHasKey('description', $keyInfo);
        $this->assertArrayHasKey('use_cases', $keyInfo);
        $this->assertArrayHasKey('storage', $keyInfo);
    }

    /** @test */
    public function it_can_find_by_secondary_key(): void
    {
        $user = User::factory()->create();
        $found = User::findBySecondaryKey($user->public_id);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }
}
```

## Conclusion

These configuration examples demonstrate the flexibility and power of the enhanced PHP enum approach for secondary key management. The enum provides:

1. **Type Safety**: Compile-time checking of key types
2. **Rich Metadata**: Colors, labels, descriptions, and use cases
3. **Flexible Configuration**: Environment-based and feature flag-driven selection
4. **Easy Integration**: Simple API for accessing enum properties
5. **Comprehensive Testing**: Full test coverage for enum functionality

The enum-based approach makes the secondary key system more maintainable, discoverable, and user-friendly while providing the performance benefits of Snowflake as the default choice.
