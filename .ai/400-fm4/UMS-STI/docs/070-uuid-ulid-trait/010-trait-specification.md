# 1. UUID/ULID/Snowflake Secondary Key Trait Specification

## 1.1 Executive Summary
The `HasSecondaryUniqueKey` trait provides a standardized approach for adding secondary unique identifiers to models in the UMS-STI system. This trait supports UUID, ULID, and Snowflake formats through an enhanced native PHP enum that includes color and label metadata. The trait is configurable with sensible defaults: Snowflake as the default type and `public_id` as the default column name, allowing developers to choose the appropriate identifier type based on security, performance, distributed system requirements, and business needs.

## 1.2 Learning Objectives
After completing this guide, you will:
- Understand the design principles behind the secondary key trait
- Implement UUID, ULID, or Snowflake secondary keys in STI models
- Choose between UUID, ULID, and Snowflake based on specific requirements
- Integrate the trait with existing UMS-STI architecture
- Handle key generation, validation, and querying efficiently
- Configure distributed Snowflake generation for multi-server environments

## 1.3 Prerequisite Knowledge
- Laravel Eloquent ORM and traits
- Understanding of UMS-STI architecture
- Basic knowledge of UUID, ULID, and Snowflake formats
- Database indexing concepts
- Distributed systems concepts (for Snowflake usage)

## 1.4 Trait Design Specification

### 1.4.1 Enhanced PHP Enum for Secondary Key Types

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SecondaryKeyType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case UUID = 'uuid';
    case ULID = 'ulid';
    case SNOWFLAKE = 'snowflake';

    /**
     * Get the default key type (Snowflake for optimal performance)
     */
    public static function default(): self
    {
        return self::SNOWFLAKE;
    }

    /**
     * Get the display label for the key type
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::UUID => 'UUID v7',
            self::ULID => 'ULID',
            self::SNOWFLAKE => 'Snowflake',
        };
    }

    /**
     * Get the color associated with the key type (for UI/documentation)
     */
    public function getColor(): string
    {
        return match ($this) {
            self::UUID => 'info',
            self::ULID => 'primary',
            self::SNOWFLAKE => 'gray',
        };
    }

    /**
     * Get the description for the key type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::UUID => 'Industry standard with timestamp ordering (Laravel 12 default)',
            self::ULID => 'Compact, case-insensitive with natural sorting',
            self::SNOWFLAKE => 'Distributed system optimized with embedded metadata',
        };
    }

    /**
     * Get the icon for the key type
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::UUID => 'heroicon-o-identification',
            self::ULID => 'heroicon-o-key',
            self::SNOWFLAKE => 'heroicon-o-finger-print',
        };
    }

    /**
     * Get the typical use cases for the key type
     */
    public function useCases(): array
    {
        return match ($this) {
            self::UUID => [
                'Standards compliance',
                'Legacy system integration',
                'Regulatory requirements',
                'Security-critical applications',
            ],
            self::ULID => [
                'Storage efficiency',
                'URL-friendly identifiers',
                'High-volume logging',
                'Time-series data',
            ],
            self::SNOWFLAKE => [
                'Distributed systems',
                'Microservices architecture',
                'Multi-tenant applications',
                'Maximum performance requirements',
            ],
        };
    }

    /**
     * Get the storage characteristics
     */
    public function storageInfo(): array
    {
        return match ($this) {
            self::UUID => [
                'length' => 36,
                'format' => 'string',
                'bytes' => 16,
                'encoding' => 'hexadecimal',
            ],
            self::ULID => [
                'length' => 26,
                'format' => 'string',
                'bytes' => 16,
                'encoding' => 'base32',
            ],
            self::SNOWFLAKE => [
                'length' => 19,
                'format' => 'integer',
                'bytes' => 8,
                'encoding' => 'integer',
            ],
        };
    }
}

```

### 1.4.2 Core Interface

```php
<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\SecondaryKeyType;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

trait HasSecondaryUniqueKey
{
    /**
     * The secondary key type (defaults to Snowflake for optimal performance)
     */
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::SNOWFLAKE;

    /**
     * The column name for the secondary key (defaults to public_id)
     */
    protected string $secondaryKeyColumn = 'public_id';

    /**
     * Find a model by its secondary key
     */
    public static function findBySecondaryKey(string $key): ?static
    {
        return static::where((new static)->getSecondaryKeyColumn(), $key)->first();
    }

    /**
     * Find a model by its secondary key or fail
     */
    public static function findBySecondaryKeyOrFail(string $key): static
    {
        return static::where((new static)->getSecondaryKeyColumn(), $key)->firstOrFail();
    }

    /**
     * Generate a new secondary key based on the configured type
     */
    public function generateSecondaryKey(): string
    {
        return match ($this->getSecondaryKeyType()) {
            SecondaryKeyType::UUID => (string) Str::uuid(), // Generates UUID v7 in Laravel 12
            SecondaryKeyType::ULID => (string) Ulid::generate(),
            SecondaryKeyType::SNOWFLAKE => (string) Snowflake::make(),
        };
    }

    /**
     * Get the secondary key type
     */
    public function getSecondaryKeyType(): SecondaryKeyType
    {
        return $this->secondaryKeyType ?? SecondaryKeyType::default();
    }

    /**
     * Set the secondary key type
     */
    public function setSecondaryKeyType(SecondaryKeyType $type): void
    {
        $this->secondaryKeyType = $type;
    }

    /**
     * Get the secondary key column name
     */
    public function getSecondaryKeyColumn(): string
    {
        return $this->secondaryKeyColumn;
    }

    /**
     * Scope query to find by secondary key
     */
    public function scopeBySecondaryKey($query, string $key)
    {
        return $query->where($this->getSecondaryKeyColumn(), $key);
    }

    /**
     * Boot the trait
     */
    protected static function bootHasSecondaryUniqueKey(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->getSecondaryKeyColumn()})) {
                $model->{$model->getSecondaryKeyColumn()} = $model->generateSecondaryKey();
            }
        });
    }
}

```

### 1.4.3 Integration with STI Architecture

The trait is designed to work seamlessly with the existing STI architecture:

```php
<?php

namespace App\Models;

use App\Enums\SecondaryKeyType;
use App\Traits\HasSecondaryUniqueKey;
use Illuminate\Database\Eloquent\Model;

abstract class User extends Model
{
    use HasSecondaryUniqueKey;

    // Base user implementation
    protected $table = 'users';

    // Configure secondary key for all user types (Snowflake default for optimal performance)
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::SNOWFLAKE;
    protected string $secondaryKeyColumn = 'public_id';
}

class StandardUser extends User
{
    // Standard users - UUID v7 for standards compliance and familiarity
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::UUID;
}

class AdminUser extends User
{
    // Admin users - UUID v7 for established tooling and compliance
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::UUID;
}

class GuestUser extends User
{
    // Guest users - ULID for compact storage (high volume)
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::ULID;
}

class SystemUser extends User
{
    // System users - Snowflake for distributed system coordination (default)
    // Uses the default SecondaryKeyType::SNOWFLAKE
}
```

### 1.4.4 Database Schema Requirements

The trait requires a secondary key column in the database. The column type depends on the identifier format:

#### 1.4.4.1 For UUID/ULID (String-based)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecondaryKeyToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // For UUID (36 chars) or ULID (26 chars) - use string
            $table->string('public_id', 36)->unique()->nullable();
            $table->index('public_id');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });
    }
}
```

#### For Snowflake (Integer-based)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSnowflakeKeyToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // For Snowflake (64-bit integer) - use bigInteger
            $table->unsignedBigInteger('public_id')->unique()->nullable();
            $table->index('public_id');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });
    }
}
```

#### Mixed Environment (Supporting All Types)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFlexibleSecondaryKeyToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Use string to accommodate all formats
            // Snowflake integers will be cast to strings
            $table->string('public_id', 36)->unique()->nullable();
            $table->index('public_id');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['public_id']);
            $table->dropColumn('public_id');
        });
    }
}
```

### 5. Configuration Options

The trait provides flexible configuration with enum-based type safety:

```php
class CustomModel extends Model
{
    use HasSecondaryUniqueKey;

    // Custom configuration using enum
    protected SecondaryKeyType $secondaryKeyType = SecondaryKeyType::ULID;
    protected string $secondaryKeyColumn = 'external_id';

    // Override generation logic if needed
    public function generateSecondaryKey(): string
    {
        // Custom generation logic
        return 'custom_' . parent::generateSecondaryKey();
    }

    // Access enum metadata
    public function getKeyTypeInfo(): array
    {
        $type = $this->getSecondaryKeyType();
        return [
            'type' => $type->value,
            'label' => $type->label(),
            'color' => $type->color(),
            'description' => $type->description(),
            'use_cases' => $type->useCases(),
            'storage' => $type->storageInfo(),
        ];
    }
}

// Configuration through methods
class FlexibleModel extends Model
{
    use HasSecondaryUniqueKey;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Dynamic configuration based on environment or requirements
        if (config('app.distributed_mode')) {
            $this->setSecondaryKeyType(SecondaryKeyType::SNOWFLAKE);
        } elseif (config('app.storage_optimized')) {
            $this->setSecondaryKeyType(SecondaryKeyType::ULID);
        } else {
            $this->setSecondaryKeyType(SecondaryKeyType::UUID);
        }
    }
}
```

## Design Principles

### 1. Flexibility First
- Support for both UUID and ULID formats
- Configurable column names
- Overridable generation methods
- Type-specific configuration per model

### 2. STI Integration
- Seamless integration with existing STI hierarchy
- Inherited configuration with override capability
- Consistent behavior across all user types
- No breaking changes to existing architecture

### 3. Performance Considerations
- Automatic database indexing
- Efficient query methods
- Lazy generation (only when needed)
- Optimized for read-heavy workloads

### 4. Security by Design
- Cryptographically secure generation
- No predictable patterns
- Support for time-based ordering (ULID)
- Configurable security levels per model type

### 5. Developer Experience
- Simple trait inclusion
- Intuitive method names
- Clear configuration options
- Comprehensive error handling

## Usage Examples

### Basic Usage
```php
// Create a user with automatic secondary key generation
$user = StandardUser::create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

echo $user->public_id; // Generated UUID

// Find by secondary key
$foundUser = StandardUser::findBySecondaryKey($user->public_id);

// Query scope
$users = StandardUser::bySecondaryKey($publicId)->get();
```

### API Integration
```php
// API routes using secondary keys instead of primary keys
Route::get('/api/users/{publicId}', function (string $publicId) {
    return StandardUser::findBySecondaryKeyOrFail($publicId);
});

// More secure - no exposure of internal IDs
// GET /api/users/550e8400-e29b-41d4-a716-446655440000
```

### Mixed Type Usage with Enum Metadata
```php
// Different user types can use different key types
$standardUser = StandardUser::create(['name' => 'John']); // UUID v7
$adminUser = AdminUser::create(['name' => 'Jane']);       // UUID v7
$guestUser = GuestUser::create(['name' => 'Guest']);      // ULID
$systemUser = SystemUser::create(['name' => 'System']);   // Snowflake (default)

// All support the same interface
$user1 = User::findBySecondaryKey($standardUser->public_id);
$user2 = User::findBySecondaryKey($adminUser->public_id);
$user3 = User::findBySecondaryKey($guestUser->public_id);
$user4 = User::findBySecondaryKey($systemUser->public_id);

// Access enum metadata for each user type
foreach ([$standardUser, $adminUser, $guestUser, $systemUser] as $user) {
    $keyType = $user->getSecondaryKeyType();
    echo "User: {$user->name}\n";
    echo "Key Type: {$keyType->label()} ({$keyType->value})\n";
    echo "Color: {$keyType->color()}\n";
    echo "Description: {$keyType->description()}\n";
    echo "Use Cases: " . implode(', ', $keyType->useCases()) . "\n";
    echo "Storage: {$keyType->storageInfo()['bytes']} bytes\n\n";
}
```

### Snowflake-Specific Usage with Enum Integration
```php
// Snowflake IDs provide additional metadata
$systemUser = SystemUser::create(['name' => 'System Worker']);

// Access Snowflake-specific information using enum type checking
if ($systemUser->getSecondaryKeyType() === SecondaryKeyType::SNOWFLAKE) {
    $snowflake = Snowflake::fromId($systemUser->public_id);

    echo "Key Type: " . $systemUser->getSecondaryKeyType()->label() . "\n";
    echo "Color: " . $systemUser->getSecondaryKeyType()->color() . "\n";
    echo "Timestamp: " . $snowflake->timestamp . "\n";
    echo "Datacenter ID: " . $snowflake->datacenter_id . "\n";
    echo "Worker ID: " . $snowflake->worker_id . "\n";
    echo "Sequence: " . $snowflake->sequence . "\n";

    // Convert to Carbon for date operations
    $createdAt = $snowflake->toCarbon();
    echo "Created: " . $createdAt->toDateTimeString() . "\n";

    // Display enum metadata
    $storageInfo = $systemUser->getSecondaryKeyType()->storageInfo();
    echo "Storage: {$storageInfo['bytes']} bytes as {$storageInfo['format']}\n";
}

// Distributed system coordination
$worker1 = SystemUser::create(['name' => 'Worker 1']); // Datacenter 0, Worker 0
$worker2 = SystemUser::create(['name' => 'Worker 2']); // Datacenter 0, Worker 1

// Natural ordering by creation time
$orderedUsers = SystemUser::orderBy('public_id')->get();

// Display key type information for debugging/monitoring
foreach ($orderedUsers as $user) {
    $keyType = $user->getSecondaryKeyType();
    echo "User: {$user->name} | Type: {$keyType->label()} | ID: {$user->public_id}\n";
}
```

## Next Steps

This specification provides the foundation for the UUID/ULID/Snowflake trait implementation with enhanced PHP enum support. The following documents will cover:

1. **Principles and Patterns** - Deep dive into the architectural decisions and enum-based design
2. **UUID vs ULID vs Snowflake Comparison** - Detailed analysis with use case scenarios and enum metadata
3. **Implementation Diagrams** - Visual representation of the trait architecture including enum integration
4. **Integration Examples** - Real-world usage patterns and best practices with enum-based configuration

## References

- [RFC 4122 - UUID Specification](https://tools.ietf.org/html/rfc4122)
- [ULID Specification](https://github.com/ulid/spec)
- [Laravel Eloquent Traits](https://laravel.com/docs/eloquent-mutators#defining-a-mutator)
- UMS-STI Architecture Documentation
