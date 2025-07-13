# Laravel Data Implementation Guide

## Table of Contents

- [Overview](#overview)
- [Installation & Setup](#installation--setup)
  - [1.1. Package Installation](#11-package-installation)
  - [1.2. Configuration Publishing](#12-configuration-publishing)
  - [1.3. Basic Setup](#13-basic-setup)
- [Data Transfer Objects](#data-transfer-objects)
  - [2.1. Basic DTO Creation](#21-basic-dto-creation)
  - [2.2. Advanced DTO Features](#22-advanced-dto-features)
  - [2.3. Nested DTOs](#23-nested-dtos)
- [Validation & Transformation](#validation--transformation)
  - [3.1. Built-in Validation](#31-built-in-validation)
  - [3.2. Custom Validation Rules](#32-custom-validation-rules)
  - [3.3. Data Transformation](#33-data-transformation)
- [API Integration](#api-integration)
  - [4.1. API Resource Integration](#41-api-resource-integration)
  - [4.2. Request Handling](#42-request-handling)
  - [4.3. Response Formatting](#43-response-formatting)
- [Collections & Arrays](#collections--arrays)
  - [5.1. Data Collections](#51-data-collections)
  - [5.2. Array Manipulation](#52-array-manipulation)
- [Performance Optimization](#performance-optimization)
  - [6.1. Caching Strategies](#61-caching-strategies)
  - [6.2. Lazy Loading](#62-lazy-loading)
  - [6.3. Memory Optimization](#63-memory-optimization)
- [Testing Strategies](#testing-strategies)
  - [7.1. Unit Testing Data Objects](#71-unit-testing-data-objects)
  - [7.2. Integration Testing](#72-integration-testing)
- [Best Practices](#best-practices)
  - [8.1. Design Principles](#81-design-principles)
  - [8.2. Naming Conventions](#82-naming-conventions)
  - [8.3. Validation Strategy](#83-validation-strategy)
- [Advanced Patterns](#advanced-patterns)
  - [9.1. Polymorphic Data Objects](#91-polymorphic-data-objects)
  - [9.2. Data Pipelines](#92-data-pipelines)
  - [9.3. Event-Driven Data Processing](#93-event-driven-data-processing)
- [Navigation](#navigation)

## Overview

Laravel Data provides type-safe data transfer objects with built-in validation, transformation, and serialization capabilities. This guide covers enterprise-level implementation with API integration, performance optimization, and comprehensive testing strategies.

**🚀 Key Features:**
- **Type Safety**: Full PHP type system integration with strict typing
- **Automatic Validation**: Built-in validation with custom rule support
- **API Resource Integration**: Seamless JSON API response generation
- **Data Transformation**: Flexible casting and transformation pipelines
- **Collection Support**: Efficient handling of data collections and arrays
- **Performance Optimization**: Caching and lazy loading capabilities

## Installation & Setup

### 1.1. Package Installation

Install Laravel Data using Composer:

```bash
# Install Laravel Data
composer require spatie/laravel-data

# Publish configuration (optional)
php artisan vendor:publish --provider="Spatie\LaravelData\LaravelDataServiceProvider" --tag="data-config"

# Verify installation
php artisan data:check
```

**System Requirements:**

- PHP 8.1 or higher
- Laravel 9.0 or higher
- Composer 2.0 or higher

### 1.2. Configuration Publishing

Configure Laravel Data for your application:

```php
// config/data.php
return [
    /*
     * The package will use this format to transform and cast dates.
     * This can be overridden in specific data classes.
     */
    'date_format' => 'Y-m-d H:i:s',

    /*
     * Global transformers will take complex types and transform them into simple types.
     */
    'transformers' => [
        DateTimeInterface::class => \Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class,
        \Illuminate\Contracts\Support\Arrayable::class => \Spatie\LaravelData\Transformers\ArrayableTransformer::class,
        BackedEnum::class => \Spatie\LaravelData\Transformers\EnumTransformer::class,
    ],

    /*
     * Global casts will cast values into complex types when creating a data object from simple types.
     */
    'casts' => [
        DateTimeInterface::class => \Spatie\LaravelData\Casts\DateTimeInterfaceCast::class,
        BackedEnum::class => \Spatie\LaravelData\Casts\EnumCast::class,
    ],

    /*
     * Rule inferrers can be configured here. They will automatically add
     * validation rules to properties of a data object based upon
     * the type of the property.
     */
    'rule_inferrers' => [
        \Spatie\LaravelData\RuleInferrers\SometimesRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\NullableRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\RequiredRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\BuiltInTypesRuleInferrer::class,
        \Spatie\LaravelData\RuleInferrers\AttributesRuleInferrer::class,
    ],

    /*
     * Normalizers return an array representation of the payload, or null if
     * it cannot normalize the payload. The normalizers below are used for
     * every data object, unless overridden in a specific data class.
     */
    'normalizers' => [
        \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
        \Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
        \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
        \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
        \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
        \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
    ],

    /*
     * Data objects can be wrapped into a key like 'data' when used as a resource,
     * this key can be set globally here for all data objects. You can pass
     * `null` if you want to disable wrapping.
     */
    'wrap' => null,

    /*
     * Adds a specific caster to the Symphony VarDumper component which hides some
     * properties from data objects and collections when being dumped by `dump` or `dd`.
     * Can be 'enabled', 'disabled' or 'development_only'.
     */
    'var_dumper_caster_mode' => 'development_only',
];
```

### 1.3. Basic Setup

Create your first Data object:

```php
// app/Data/UserData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;

class UserData extends Data
{
    public function __construct(
        #[Required, StringType]
        public string $name,
        
        #[Required, Email]
        public string $email,
        
        public ?string $phone = null,
        
        public ?int $age = null,
    ) {}
}
```

**Basic Usage:**

```php
// Creating from array
$userData = UserData::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'age' => 30,
]);

// Creating from request
$userData = UserData::from($request);

// Creating from model
$userData = UserData::from($user);

// Converting to array
$array = $userData->toArray();

// Converting to JSON
$json = $userData->toJson();
```

## Data Transfer Objects

### 2.1. Basic DTO Creation

Create comprehensive DTOs for your application:

```php
// app/Data/ProductData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Url;
use Carbon\Carbon;

class ProductData extends Data
{
    public function __construct(
        #[Required, StringType, Min(3), Max(255)]
        public string $name,
        
        #[Required, StringType, Min(10)]
        public string $description,
        
        #[Required, Numeric, Min(0)]
        public float $price,
        
        #[Required, StringType]
        public string $sku,
        
        #[Numeric, Min(0)]
        public int $stock_quantity = 0,
        
        #[StringType]
        public ?string $category = null,
        
        #[Url]
        public ?string $image_url = null,
        
        public bool $is_active = true,
        
        public ?Carbon $created_at = null,
        
        public ?Carbon $updated_at = null,
    ) {}
    
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'price' => ['required', 'numeric', 'min:0'],
            'sku' => ['required', 'string', 'unique:products,sku'],
            'stock_quantity' => ['numeric', 'min:0'],
            'category' => ['string', 'exists:categories,name'],
            'image_url' => ['url'],
            'is_active' => ['boolean'],
        ];
    }
    
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }
    
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }
    
    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock_quantity <= $threshold && $this->stock_quantity > 0;
    }
}
```

### 2.2. Advanced DTO Features

Implement advanced DTO features with custom logic:

```php
// app/Data/OrderData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use App\Enums\OrderStatus;
use App\Casts\MoneyCast;
use App\Transformers\MoneyTransformer;
use Carbon\Carbon;

class OrderData extends Data
{
    public function __construct(
        #[Required, Numeric]
        public int $user_id,
        
        #[Required, In(['pending', 'processing', 'shipped', 'delivered', 'cancelled'])]
        public OrderStatus $status,
        
        #[WithCast(MoneyCast::class), WithTransformer(MoneyTransformer::class)]
        public float $subtotal,
        
        #[WithCast(MoneyCast::class), WithTransformer(MoneyTransformer::class)]
        public float $tax_amount,
        
        #[WithCast(MoneyCast::class), WithTransformer(MoneyTransformer::class)]
        public float $shipping_amount,
        
        #[Computed]
        public float $total,
        
        /** @var DataCollection<OrderItemData> */
        public DataCollection $items,
        
        public ?AddressData $shipping_address = null,
        
        public ?AddressData $billing_address = null,
        
        public ?Carbon $shipped_at = null,
        
        public ?Carbon $delivered_at = null,
        
        public Carbon $created_at,
        
        public Carbon $updated_at,
    ) {}
    
    public static function fromModel($model): static
    {
        return new static(
            user_id: $model->user_id,
            status: OrderStatus::from($model->status),
            subtotal: $model->subtotal,
            tax_amount: $model->tax_amount,
            shipping_amount: $model->shipping_amount,
            total: $model->total,
            items: OrderItemData::collection($model->items),
            shipping_address: $model->shipping_address ? AddressData::from($model->shipping_address) : null,
            billing_address: $model->billing_address ? AddressData::from($model->billing_address) : null,
            shipped_at: $model->shipped_at,
            delivered_at: $model->delivered_at,
            created_at: $model->created_at,
            updated_at: $model->updated_at,
        );
    }
    
    #[Computed]
    public function total(): float
    {
        return $this->subtotal + $this->tax_amount + $this->shipping_amount;
    }
    
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [OrderStatus::Pending, OrderStatus::Processing]);
    }
    
    public function isShippable(): bool
    {
        return $this->status === OrderStatus::Processing && $this->shipping_address !== null;
    }
    
    public function getEstimatedDeliveryDate(): ?Carbon
    {
        if ($this->shipped_at) {
            return $this->shipped_at->addDays(3); // 3-day delivery estimate
        }
        
        return null;
    }
    
    public function getItemsCount(): int
    {
        return $this->items->sum(fn(OrderItemData $item) => $item->quantity);
    }
    
    public function hasDigitalItems(): bool
    {
        return $this->items->contains(fn(OrderItemData $item) => $item->is_digital);
    }
}
```

### 2.3. Nested DTOs

Create complex nested DTO structures:

```php
// app/Data/OrderItemData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Computed;

class OrderItemData extends Data
{
    public function __construct(
        #[Required, Numeric]
        public int $product_id,
        
        #[Required, Numeric, Min(1)]
        public int $quantity,
        
        #[Required, Numeric, Min(0)]
        public float $unit_price,
        
        #[Computed]
        public float $total_price,
        
        public ProductData $product,
        
        public bool $is_digital = false,
        
        public ?string $notes = null,
    ) {}
    
    #[Computed]
    public function totalPrice(): float
    {
        return $this->quantity * $this->unit_price;
    }
    
    public function getDiscountAmount(): float
    {
        $originalPrice = $this->product->price * $this->quantity;
        return $originalPrice - $this->total_price;
    }
    
    public function hasDiscount(): bool
    {
        return $this->getDiscountAmount() > 0;
    }
}

// app/Data/AddressData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Max;

class AddressData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $street_address,
        
        #[StringType, Max(255)]
        public ?string $apartment = null,
        
        #[Required, StringType, Max(100)]
        public string $city,
        
        #[Required, StringType, Max(100)]
        public string $state,
        
        #[Required, StringType, Max(20)]
        public string $postal_code,
        
        #[Required, StringType, Max(100)]
        public string $country,
        
        #[StringType, Max(255)]
        public ?string $company = null,
    ) {}
    
    public function getFullAddress(): string
    {
        $address = $this->street_address;
        
        if ($this->apartment) {
            $address .= ', ' . $this->apartment;
        }
        
        $address .= ', ' . $this->city . ', ' . $this->state . ' ' . $this->postal_code;
        $address .= ', ' . $this->country;
        
        return $address;
    }
    
    public function isInternational(): bool
    {
        return strtoupper($this->country) !== 'US';
    }
}
```

## Validation & Transformation

### 3.1. Built-in Validation

Leverage built-in validation attributes:

```php
// app/Data/UserRegistrationData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Confirmed;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Before;
use Carbon\Carbon;

class UserRegistrationData extends Data
{
    public function __construct(
        #[Required, StringType, Min(2), Max(50)]
        public string $first_name,
        
        #[Required, StringType, Min(2), Max(50)]
        public string $last_name,
        
        #[Required, Email, Unique('users', 'email')]
        public string $email,
        
        #[Required, StringType, Min(8), Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character')]
        public string $password,
        
        #[Required, Confirmed]
        public string $password_confirmation,
        
        #[Regex('/^\+?[1-9]\d{1,14}$/')]
        public ?string $phone = null,
        
        #[Date, Before('today')]
        public ?Carbon $date_of_birth = null,
        
        public bool $accepts_marketing = false,
        
        #[Required]
        public bool $accepts_terms = false,
    ) {}
    
    public static function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'password_confirmation' => ['required', 'same:password'],
            'phone' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'accepts_marketing' => ['boolean'],
            'accepts_terms' => ['required', 'accepted'],
        ];
    }
    
    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    public function getAge(): ?int
    {
        return $this->date_of_birth?->age;
    }
    
    public function isAdult(): bool
    {
        return $this->getAge() >= 18;
    }
}
```

### 3.2. Custom Validation Rules

Create custom validation rules for complex scenarios:

```php
// app/Rules/StrongPasswordRule.php
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class StrongPasswordRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value);
    }
    
    public function message(): string
    {
        return 'The :attribute must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
    }
}

// app/Data/SecureUserData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Rule;
use App\Rules\StrongPasswordRule;

class SecureUserData extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        
        #[Required, Rule(StrongPasswordRule::class)]
        public string $password,
        
        #[Required]
        public string $two_factor_code,
    ) {}
    
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', new StrongPasswordRule()],
            'two_factor_code' => ['required', 'string', 'size:6'],
        ];
    }
}
```

### 3.3. Data Transformation

Implement custom data transformation:

```php
// app/Transformers/MoneyTransformer.php
<?php

namespace App\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Transformers\Transformer;

class MoneyTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value): mixed
    {
        if (is_numeric($value)) {
            return [
                'amount' => $value,
                'formatted' => '$' . number_format($value, 2),
                'currency' => 'USD',
            ];
        }
        
        return $value;
    }
}

// app/Casts/MoneyCast.php
<?php

namespace App\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\DataProperty;

class MoneyCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $context): mixed
    {
        if (is_array($value) && isset($value['amount'])) {
            return (float) $value['amount'];
        }
        
        if (is_string($value)) {
            // Remove currency symbols and convert to float
            $cleaned = preg_replace('/[^\d.]/', '', $value);
            return (float) $cleaned;
        }
        
        return (float) $value;
    }
}

// app/Data/PriceData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use App\Casts\MoneyCast;
use App\Transformers\MoneyTransformer;

class PriceData extends Data
{
    public function __construct(
        #[WithCast(MoneyCast::class), WithTransformer(MoneyTransformer::class)]
        public float $amount,
        
        public string $currency = 'USD',
    ) {}
    
    public function getFormatted(): string
    {
        return match ($this->currency) {
            'USD' => '$' . number_format($this->amount, 2),
            'EUR' => '€' . number_format($this->amount, 2),
            'GBP' => '£' . number_format($this->amount, 2),
            default => $this->currency . ' ' . number_format($this->amount, 2),
        };
    }
    
    public function convertTo(string $targetCurrency, float $exchangeRate): self
    {
        return new self(
            amount: $this->amount * $exchangeRate,
            currency: $targetCurrency,
        );
    }
}
```

## API Integration

### 4.1. API Resource Integration

Integrate Data objects with Laravel API resources:

```php
// app/Http/Resources/UserResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Data\UserData;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        $userData = UserData::from($this->resource);

        return $userData->toArray();
    }
}

// app/Http/Controllers/Api/UserController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Data\UserData;
use App\Data\UserRegistrationData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::paginate(15);

        return response()->json([
            'data' => UserData::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $userData = UserData::from($user);

        return response()->json([
            'data' => $userData->toArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $registrationData = UserRegistrationData::from($request);

        // Validation is automatically handled by the Data object
        $user = User::create([
            'first_name' => $registrationData->first_name,
            'last_name' => $registrationData->last_name,
            'email' => $registrationData->email,
            'password' => bcrypt($registrationData->password),
            'phone' => $registrationData->phone,
            'date_of_birth' => $registrationData->date_of_birth,
            'accepts_marketing' => $registrationData->accepts_marketing,
        ]);

        $userData = UserData::from($user);

        return response()->json([
            'data' => $userData->toArray(),
            'message' => 'User created successfully',
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $userData = UserData::from($request);

        $user->update($userData->toArray());

        return response()->json([
            'data' => UserData::from($user->fresh())->toArray(),
            'message' => 'User updated successfully',
        ]);
    }
}
```

### 4.2. Request Handling

Handle complex API requests with Data objects:

```php
// app/Data/ProductSearchData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\In;

class ProductSearchData extends Data
{
    public function __construct(
        #[StringType]
        public ?string $query = null,

        #[StringType]
        public ?string $category = null,

        #[Numeric, Min(0)]
        public ?float $min_price = null,

        #[Numeric, Min(0)]
        public ?float $max_price = null,

        #[In(['name', 'price', 'created_at', 'popularity'])]
        public string $sort_by = 'name',

        #[In(['asc', 'desc'])]
        public string $sort_direction = 'asc',

        #[Numeric, Min(1), Max(100)]
        public int $per_page = 15,

        #[Numeric, Min(1)]
        public int $page = 1,

        public bool $in_stock_only = false,

        public bool $active_only = true,
    ) {}

    public function toSearchQuery(): array
    {
        $query = [];

        if ($this->query) {
            $query['search'] = $this->query;
        }

        if ($this->category) {
            $query['category'] = $this->category;
        }

        if ($this->min_price !== null) {
            $query['min_price'] = $this->min_price;
        }

        if ($this->max_price !== null) {
            $query['max_price'] = $this->max_price;
        }

        if ($this->in_stock_only) {
            $query['in_stock'] = true;
        }

        if ($this->active_only) {
            $query['active'] = true;
        }

        return $query;
    }

    public function hasFilters(): bool
    {
        return $this->query !== null ||
               $this->category !== null ||
               $this->min_price !== null ||
               $this->max_price !== null ||
               $this->in_stock_only ||
               !$this->active_only;
    }
}

// app/Http/Controllers/Api/ProductController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Data\ProductSearchData;
use App\Data\ProductData;
use App\Services\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductSearchService $searchService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $searchData = ProductSearchData::from($request);

        $results = $this->searchService->search($searchData);

        return response()->json([
            'data' => ProductData::collection($results->items()),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'has_filters' => $searchData->hasFilters(),
                'filters' => $searchData->toSearchQuery(),
            ],
        ]);
    }
}
```

### 4.3. Response Formatting

Create consistent API response formatting:

```php
// app/Data/ApiResponseData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ApiResponseData extends Data
{
    public function __construct(
        public mixed $data,
        public ?string $message = null,
        public bool $success = true,
        public ?array $meta = null,
        public ?array $errors = null,
    ) {}

    public static function success(mixed $data, ?string $message = null, ?array $meta = null): self
    {
        return new self(
            data: $data,
            message: $message,
            success: true,
            meta: $meta,
        );
    }

    public static function error(string $message, ?array $errors = null, mixed $data = null): self
    {
        return new self(
            data: $data,
            message: $message,
            success: false,
            errors: $errors,
        );
    }

    public static function paginated(DataCollection $data, $paginator, ?string $message = null): self
    {
        return new self(
            data: $data,
            message: $message,
            success: true,
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        );
    }
}

// app/Http/Controllers/Api/BaseController.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Data\ApiResponseData;
use Illuminate\Http\JsonResponse;

abstract class BaseController extends Controller
{
    protected function successResponse(mixed $data, ?string $message = null, ?array $meta = null): JsonResponse
    {
        $response = ApiResponseData::success($data, $message, $meta);

        return response()->json($response->toArray());
    }

    protected function errorResponse(string $message, ?array $errors = null, int $status = 400): JsonResponse
    {
        $response = ApiResponseData::error($message, $errors);

        return response()->json($response->toArray(), $status);
    }

    protected function paginatedResponse($data, $paginator, ?string $message = null): JsonResponse
    {
        $response = ApiResponseData::paginated($data, $paginator, $message);

        return response()->json($response->toArray());
    }
}
```

## Collections & Arrays

### 5.1. Data Collections

Work with collections of Data objects:

```php
// app/Data/OrderSummaryData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Computed;

class OrderSummaryData extends Data
{
    public function __construct(
        /** @var DataCollection<OrderData> */
        public DataCollection $orders,

        #[Computed]
        public float $total_revenue,

        #[Computed]
        public int $total_orders,

        #[Computed]
        public float $average_order_value,

        #[Computed]
        public array $status_breakdown,
    ) {}

    #[Computed]
    public function totalRevenue(): float
    {
        return $this->orders->sum(fn(OrderData $order) => $order->total);
    }

    #[Computed]
    public function totalOrders(): int
    {
        return $this->orders->count();
    }

    #[Computed]
    public function averageOrderValue(): float
    {
        return $this->total_orders > 0 ? $this->total_revenue / $this->total_orders : 0;
    }

    #[Computed]
    public function statusBreakdown(): array
    {
        return $this->orders
            ->groupBy(fn(OrderData $order) => $order->status->value)
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    public function getTopCustomers(int $limit = 10): array
    {
        return $this->orders
            ->groupBy('user_id')
            ->map(function ($orders, $userId) {
                return [
                    'user_id' => $userId,
                    'order_count' => $orders->count(),
                    'total_spent' => $orders->sum(fn(OrderData $order) => $order->total),
                ];
            })
            ->sortByDesc('total_spent')
            ->take($limit)
            ->values()
            ->toArray();
    }

    public function getRevenueByMonth(): array
    {
        return $this->orders
            ->groupBy(fn(OrderData $order) => $order->created_at->format('Y-m'))
            ->map(fn($orders) => $orders->sum(fn(OrderData $order) => $order->total))
            ->toArray();
    }
}
```

### 5.2. Array Manipulation

Advanced array manipulation with Data objects:

```php
// app/Data/AnalyticsData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Carbon\Carbon;

class AnalyticsData extends Data
{
    public function __construct(
        public Carbon $start_date,
        public Carbon $end_date,
        /** @var DataCollection<MetricData> */
        public DataCollection $metrics,
    ) {}

    public function getMetricByName(string $name): ?MetricData
    {
        return $this->metrics->first(fn(MetricData $metric) => $metric->name === $name);
    }

    public function getMetricsByCategory(string $category): DataCollection
    {
        return $this->metrics->filter(fn(MetricData $metric) => $metric->category === $category);
    }

    public function getTrendingMetrics(int $limit = 5): DataCollection
    {
        return $this->metrics
            ->filter(fn(MetricData $metric) => $metric->trend_percentage > 0)
            ->sortByDesc('trend_percentage')
            ->take($limit);
    }

    public function getAveragesByCategory(): array
    {
        return $this->metrics
            ->groupBy('category')
            ->map(fn($group) => $group->avg('value'))
            ->toArray();
    }

    public function toChartData(): array
    {
        return [
            'labels' => $this->metrics->pluck('name')->toArray(),
            'datasets' => [
                [
                    'label' => 'Current Values',
                    'data' => $this->metrics->pluck('value')->toArray(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                ],
                [
                    'label' => 'Previous Period',
                    'data' => $this->metrics->pluck('previous_value')->toArray(),
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                ],
            ],
        ];
    }
}

// app/Data/MetricData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Computed;

class MetricData extends Data
{
    public function __construct(
        public string $name,
        public string $category,
        public float $value,
        public float $previous_value,
        public string $unit = '',
        #[Computed]
        public float $trend_percentage,
        #[Computed]
        public string $trend_direction,
    ) {}

    #[Computed]
    public function trendPercentage(): float
    {
        if ($this->previous_value == 0) {
            return $this->value > 0 ? 100 : 0;
        }

        return (($this->value - $this->previous_value) / $this->previous_value) * 100;
    }

    #[Computed]
    public function trendDirection(): string
    {
        if ($this->trend_percentage > 0) {
            return 'up';
        } elseif ($this->trend_percentage < 0) {
            return 'down';
        } else {
            return 'stable';
        }
    }

    public function getFormattedValue(): string
    {
        return number_format($this->value, 2) . $this->unit;
    }

    public function getFormattedTrend(): string
    {
        $percentage = abs($this->trend_percentage);
        $direction = $this->trend_direction === 'up' ? '↑' : ($this->trend_direction === 'down' ? '↓' : '→');

        return $direction . ' ' . number_format($percentage, 1) . '%';
    }
}
```

## Performance Optimization

### 6.1. Caching Strategies

Implement caching for Data objects:

```php
// app/Data/CachedProductData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CachedProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public float $price,
        public string $sku,
        public int $stock_quantity,
        public ?string $category = null,
        public ?string $image_url = null,
        public bool $is_active = true,
        public ?Carbon $created_at = null,
        public ?Carbon $updated_at = null,
    ) {}

    public static function fromModel($model): static
    {
        $cacheKey = "product_data_{$model->id}_{$model->updated_at->timestamp}";

        return Cache::remember($cacheKey, 3600, function () use ($model) {
            return new static(
                id: $model->id,
                name: $model->name,
                description: $model->description,
                price: $model->price,
                sku: $model->sku,
                stock_quantity: $model->stock_quantity,
                category: $model->category?->name,
                image_url: $model->image_url,
                is_active: $model->is_active,
                created_at: $model->created_at,
                updated_at: $model->updated_at,
            );
        });
    }

    public static function collection($models): array
    {
        $cacheKey = 'product_collection_' . md5(serialize($models->pluck(['id', 'updated_at'])->toArray()));

        return Cache::remember($cacheKey, 1800, function () use ($models) {
            return $models->map(fn($model) => static::fromModel($model))->toArray();
        });
    }

    public function invalidateCache(): void
    {
        $pattern = "product_data_{$this->id}_*";
        Cache::forget($pattern);
    }
}
```

### 6.2. Lazy Loading

Implement lazy loading for expensive operations:

```php
// app/Data/LazyUserData.php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\LaravelData\DataCollection;

class LazyUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public Lazy|DataCollection $orders,
        public Lazy|array $preferences,
        public Lazy|float $lifetime_value,
    ) {}

    public static function fromModel($model): static
    {
        return new static(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            orders: Lazy::create(fn() => OrderData::collection($model->orders)),
            preferences: Lazy::create(fn() => $model->preferences ?? []),
            lifetime_value: Lazy::create(fn() => $model->orders->sum('total')),
        );
    }

    public function includeOrders(): static
    {
        $this->orders = $this->orders instanceof Lazy ? $this->orders->resolve() : $this->orders;
        return $this;
    }

    public function includePreferences(): static
    {
        $this->preferences = $this->preferences instanceof Lazy ? $this->preferences->resolve() : $this->preferences;
        return $this;
    }

    public function includeLifetimeValue(): static
    {
        $this->lifetime_value = $this->lifetime_value instanceof Lazy ? $this->lifetime_value->resolve() : $this->lifetime_value;
        return $this;
    }
}
```

### 6.3. Memory Optimization

Optimize memory usage for large datasets:

```php
// app/Services/DataOptimizationService.php
<?php

namespace App\Services;

use Spatie\LaravelData\DataCollection;
use Generator;

class DataOptimizationService
{
    public function processLargeDataset($models, string $dataClass): Generator
    {
        foreach ($models->chunk(100) as $chunk) {
            $dataObjects = $dataClass::collection($chunk);

            yield $dataObjects;

            // Free memory
            unset($dataObjects, $chunk);

            if (memory_get_usage() > 100 * 1024 * 1024) { // 100MB
                gc_collect_cycles();
            }
        }
    }

    public function streamDataToResponse($models, string $dataClass): Generator
    {
        yield '{"data":[';

        $first = true;
        foreach ($this->processLargeDataset($models, $dataClass) as $chunk) {
            foreach ($chunk as $item) {
                if (!$first) {
                    yield ',';
                }
                yield json_encode($item->toArray());
                $first = false;
            }
        }

        yield ']}';
    }

    public function batchProcess($models, string $dataClass, callable $processor): void
    {
        foreach ($this->processLargeDataset($models, $dataClass) as $chunk) {
            $processor($chunk);
        }
    }
}
```

## Testing Strategies

### 7.1. Unit Testing Data Objects

Comprehensive testing strategies for Data objects:

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Data\ArtistData;
use Spatie\LaravelData\Exceptions\ValidationException;

class ArtistDataTest extends TestCase
{
    public function test_creates_artist_data_from_array(): void
    {
        $data = ArtistData::from([
            'name' => 'Test Artist',
            'bio' => 'Test bio',
            'formed_year' => 2020,
        ]);

        $this->assertEquals('Test Artist', $data->name);
        $this->assertEquals('Test bio', $data->bio);
        $this->assertEquals(2020, $data->formed_year);
    }

    public function test_validates_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        ArtistData::from([
            'bio' => 'Test bio',
            // Missing required 'name' field
        ]);
    }

    public function test_transforms_to_array(): void
    {
        $data = ArtistData::from([
            'name' => 'Test Artist',
            'bio' => 'Test bio',
            'formed_year' => 2020,
        ]);

        $array = $data->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('bio', $array);
        $this->assertArrayHasKey('formed_year', $array);
    }
}
```

### 7.2. Integration Testing

Test Data objects in API contexts:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Artist;
use App\Data\ArtistData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArtistApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_artist_data_format(): void
    {
        $artist = Artist::factory()->create([
            'name' => 'Test Artist',
            'bio' => 'Test bio',
        ]);

        $response = $this->getJson("/api/artists/{$artist->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'bio',
                    'formed_year',
                    'albums_count',
                ]
            ]);
    }

    public function test_api_validates_artist_creation(): void
    {
        $response = $this->postJson('/api/artists', [
            'bio' => 'Test bio',
            // Missing required 'name'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
```

## Best Practices

### 8.1. Design Principles

Follow these principles when designing Data objects:

1. **Single Responsibility**: Each Data object should represent one concept
2. **Immutability**: Prefer immutable Data objects for better predictability
3. **Type Safety**: Use strict typing and validation
4. **Performance**: Consider caching and lazy loading for complex objects

### 8.2. Naming Conventions

Consistent naming improves maintainability:

```php
// Good: Clear, descriptive names
class ArtistProfileData extends Data { }
class AlbumMetadataData extends Data { }
class TrackAnalyticsData extends Data { }

// Avoid: Generic or unclear names
class DataObject extends Data { }
class Info extends Data { }
class Stuff extends Data { }
```

### 8.3. Validation Strategy

Implement comprehensive validation:

```php
class ArtistData extends Data
{
    public function __construct(
        #[Required, Max(120)]
        public string $name,

        #[Sometimes, Max(1000)]
        public ?string $bio,

        #[Sometimes, Min(1900), Max(2024)]
        public ?int $formed_year,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'bio' => ['sometimes', 'string', 'max:1000'],
            'formed_year' => ['sometimes', 'integer', 'min:1900', 'max:2024'],
        ];
    }
}
```

## Advanced Patterns

### 9.1. Polymorphic Data Objects

Handle different data types with polymorphism:

```php
abstract class MediaData extends Data
{
    abstract public function getType(): string;
}

class AudioData extends MediaData
{
    public function __construct(
        public string $filename,
        public int $duration,
        public string $format,
    ) {}

    public function getType(): string
    {
        return 'audio';
    }
}

class ImageData extends MediaData
{
    public function __construct(
        public string $filename,
        public int $width,
        public int $height,
    ) {}

    public function getType(): string
    {
        return 'image';
    }
}
```

### 9.2. Data Pipelines

Create data processing pipelines:

```php
class DataPipeline
{
    private array $processors = [];

    public function addProcessor(callable $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }

    public function process(Data $data): Data
    {
        return array_reduce(
            $this->processors,
            fn($data, $processor) => $processor($data),
            $data
        );
    }
}

// Usage
$pipeline = (new DataPipeline())
    ->addProcessor(fn($data) => $data->withNormalizedName())
    ->addProcessor(fn($data) => $data->withValidatedEmail())
    ->addProcessor(fn($data) => $data->withEnrichedMetadata());

$processedData = $pipeline->process($rawData);
```

### 9.3. Event-Driven Data Processing

Integrate with Laravel events:

```php
class ArtistData extends Data
{
    public static function fromModel(Artist $artist): self
    {
        $data = new self(
            name: $artist->name,
            bio: $artist->bio,
            formed_year: $artist->formed_year,
        );

        // Dispatch event for data creation
        event(new ArtistDataCreated($data, $artist));

        return $data;
    }
}

// Event listener
class EnrichArtistData
{
    public function handle(ArtistDataCreated $event): void
    {
        // Enrich data with external APIs
        $enrichedData = $this->enrichWithMusicBrainz($event->data);

        // Cache enriched data
        Cache::put("artist.{$event->artist->id}.enriched", $enrichedData, 3600);
    }
}
```

---

## Navigation

**← Previous:** [Laravel Horizon Guide](050-laravel-horizon-guide.md)

**Next →** [Laravel Fractal Guide](070-laravel-fractal-guide.md)
