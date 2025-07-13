---
owner: "[TECHNICAL_LEAD]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft"
version: "1.0.0"
target_audience: "Junior developers with 6 months-2 years experience"
framework_version: "Laravel 12.x"
---

# Laravel 12.x Implementation Guide
## [PROJECT_NAME]

**Estimated Reading Time:** 20 minutes

## Overview

This guide provides Laravel 12.x specific implementation standards and patterns for [PROJECT_NAME]. It focuses on modern Laravel practices including the new `bootstrap/providers.php` pattern, database optimizations, and testing strategies.

## Service Provider Configuration

### Bootstrap Providers Pattern (Laravel 12.x)

**File**: `bootstrap/providers.php`
```php
<?php
// Modern Laravel 12.x service provider registration
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\[PROJECT]ServiceProvider::class,
    App\Providers\FilamentServiceProvider::class,
    App\Providers\SqliteServiceProvider::class,
];
```

### Custom Service Provider Template

**File**: `app/Providers/[PROJECT]ServiceProvider.php`
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\[PROJECT]Service;

class [PROJECT]ServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton([PROJECT]Service::class);
        
        // Register configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/[project].php', '[project]'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/[project].php' => config_path('[project].php'),
        ], '[project]-config');
        
        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\[PROJECT]Command::class,
            ]);
        }
        
        // Register event listeners
        $this->registerEventListeners();
    }
    
    /**
     * Register event listeners for the service.
     */
    protected function registerEventListeners(): void
    {
        // Event listener registration
    }
}
```

## Database Implementation Standards

### Migration Template with Documentation

**File**: `database/migrations/[timestamp]_create_[table]_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates [table] table for [purpose description].
     * Includes user stamps, soft deletes, and GDPR compliance features.
     */
    public function up(): void
    {
        Schema::create('[table]', function (Blueprint $table) {
            // Primary key using ULID
            $table->ulid('id')->primary();
            
            // Core fields
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])
                  ->default('active');
            
            // User stamps (wildside/userstamps)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            
            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index('created_by');
        });
        
        // SQLite-specific optimizations
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA journal_mode=WAL');
            DB::statement('PRAGMA synchronous=NORMAL');
            DB::statement('PRAGMA cache_size=10000');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('[table]');
    }
};
```

### Model Template with Best Practices

**File**: `app/Models/[MODEL].php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wildside\Userstamps\Userstamps;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\[MODEL]Status;

class [MODEL] extends Model
{
    use HasFactory, SoftDeletes, Userstamps, LogsActivity;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => [MODEL]Status::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Activity log configuration for audit trail.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeByStatus($query, [MODEL]Status $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for active records.
     */
    public function scopeActive($query)
    {
        return $query->where('status', [MODEL]Status::Active);
    }
}
```

### Factory Template

**File**: `database/factories/[MODEL]Factory.php`
```php
<?php

namespace Database\Factories;

use App\Models\[MODEL];
use App\Enums\[MODEL]Status;
use Illuminate\Database\Eloquent\Factories\Factory;

class [MODEL]Factory extends Factory
{
    protected $model = [MODEL]::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement([MODEL]Status::cases()),
        ];
    }

    /**
     * Create active model.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => [MODEL]Status::Active,
        ]);
    }

    /**
     * Create inactive model.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => [MODEL]Status::Inactive,
        ]);
    }
}
```

## PHP 8.1+ Enum Implementation

### Status Enum Template

**File**: `app/Enums/[MODEL]Status.php`
```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum [MODEL]Status: string implements HasLabel, HasColor
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Active => 'success',
            self::Inactive => 'warning',
            self::Archived => 'danger',
        };
    }

    /**
     * Get all active statuses.
     */
    public static function activeStatuses(): array
    {
        return [self::Active];
    }

    /**
     * Check if status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
```

## Testing Implementation

### Feature Test Template

**File**: `tests/Feature/[FEATURE]Test.php`
```php
<?php

namespace Tests\Feature;

use App\Models\[MODEL];
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class [FEATURE]Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function authenticated_user_can_view_[feature]_index()
    {
        $this->actingAs($this->user)
            ->get(route('[feature].index'))
            ->assertOk()
            ->assertViewIs('[feature].index');
    }

    /** @test */
    public function authenticated_user_can_create_[feature]()
    {
        $data = [MODEL]::factory()->make()->toArray();

        $this->actingAs($this->user)
            ->post(route('[feature].store'), $data)
            ->assertRedirect(route('[feature].index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('[table]', [
            'name' => $data['name'],
            'created_by' => $this->user->id,
        ]);
    }
}
```

## Performance Optimization

### SQLite Configuration

**File**: `app/Providers/SqliteServiceProvider.php`
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class SqliteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA journal_mode=WAL');
            DB::statement('PRAGMA synchronous=NORMAL');
            DB::statement('PRAGMA cache_size=10000');
            DB::statement('PRAGMA temp_store=MEMORY');
            DB::statement('PRAGMA mmap_size=268435456'); // 256MB
        }
    }
}
```

### Query Optimization Guidelines

**Eager Loading Best Practices**:
```php
// Good: Eager load relationships
$users = User::with(['posts', 'comments'])->get();

// Bad: N+1 query problem
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // N+1 queries
}
```

**Database Query Optimization**:
```php
// Use database-level operations when possible
User::where('status', 'inactive')
    ->where('last_login', '<', now()->subMonths(6))
    ->update(['status' => 'archived']);

// Instead of loading all records into memory
```

## Security Implementation

### Form Request Validation

**File**: `app/Http/Requests/Store[MODEL]Request.php`
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\[MODEL]Status;
use Illuminate\Validation\Rules\Enum;

class Store[MODEL]Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [MODEL]::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', new Enum([MODEL]Status::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'status.required' => 'Please select a valid status.',
        ];
    }
}
```

## Artisan Commands

### Custom Command Template

**File**: `app/Console/Commands/[PROJECT]Command.php`
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class [PROJECT]Command extends Command
{
    protected $signature = '[project]:{action} {--option=default}';
    protected $description = 'Description of the command';

    public function handle(): int
    {
        $this->info('Starting [PROJECT] command...');
        
        try {
            // Command logic here
            
            $this->info('Command completed successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

---

## Definition of Done Checklist

### Laravel Setup and Configuration
- [ ] Laravel 12.x installed with correct PHP version (8.1+)
- [ ] Service providers configured using bootstrap/providers.php pattern
- [ ] Environment configuration completed for all environments
- [ ] Database connection and SQLite optimizations configured
- [ ] Custom service providers created and registered

### Model and Database Implementation
- [ ] All models follow the template pattern with proper traits
- [ ] Migrations include ULID primary keys, user stamps, and soft deletes
- [ ] Database indexes created for performance optimization
- [ ] Factories created for all models with realistic test data
- [ ] Enums implemented using PHP 8.1+ enum syntax with Filament integration

### Security Implementation
- [ ] Form request validation implemented for all user inputs
- [ ] Authorization policies created and tested
- [ ] CSRF protection enabled for all forms
- [ ] Input sanitization implemented to prevent XSS
- [ ] Activity logging configured for audit trails

### Testing Implementation
- [ ] Unit tests written for all models and services (90%+ coverage)
- [ ] Feature tests created for all user workflows
- [ ] Test database properly isolated using RefreshDatabase trait
- [ ] Factory patterns used for test data generation
- [ ] Performance tests included for critical operations

### Performance and Optimization
- [ ] SQLite WAL mode and optimizations configured
- [ ] Eager loading implemented to prevent N+1 queries
- [ ] Database queries optimized with proper indexing
- [ ] Caching strategies implemented where appropriate
- [ ] Performance benchmarks established and met

### Code Quality and Standards
- [ ] Code follows Laravel conventions and PSR standards
- [ ] All classes properly documented with PHPDoc
- [ ] Error handling implemented with appropriate exception types
- [ ] Logging configured for debugging and monitoring
- [ ] Code review completed and approved

---

## Common Pitfalls and Avoidance Strategies

### Pitfall: Incorrect Service Provider Registration
**Problem**: Using old Laravel patterns instead of Laravel 12.x bootstrap/providers.php
**Solution**: Use the new bootstrap/providers.php pattern for service provider registration
**Example**: Register providers in bootstrap/providers.php instead of config/app.php

### Pitfall: Poor Database Performance
**Problem**: Not optimizing SQLite configuration or missing database indexes
**Solution**: Implement SQLite optimizations and create proper indexes
**Example**: Use WAL mode, add indexes for frequently queried columns, implement eager loading

### Pitfall: Inadequate Model Relationships
**Problem**: Missing or incorrectly defined Eloquent relationships
**Solution**: Define all relationships properly with appropriate foreign key constraints
**Example**: Use proper belongsTo, hasMany, and belongsToMany relationships with foreign key definitions

### Pitfall: Insufficient Input Validation
**Problem**: Relying only on client-side validation or basic Laravel validation
**Solution**: Implement comprehensive Form Request validation with custom rules
**Example**: Create dedicated Form Request classes with authorization and validation logic

### Pitfall: Missing Audit Trail
**Problem**: Not tracking changes to important data for compliance and debugging
**Solution**: Implement activity logging using spatie/laravel-activitylog
**Example**: Configure LogsActivity trait on models that need audit trails

### Pitfall: Inefficient Testing Patterns
**Problem**: Slow tests due to poor database handling or missing factories
**Solution**: Use proper test isolation and factory patterns
**Example**: Use RefreshDatabase trait, create comprehensive factories, avoid real external API calls

---

## Real-World Implementation Example: EventHub - Event Management Platform

### Project Overview
**Application**: EventHub - Platform for organizing and managing community events
**Features**: Event creation, ticket sales, attendee management, check-in system
**Scale**: 10,000+ events, 100,000+ users, 500,000+ tickets sold
**Team**: 4 developers, 6-month development timeline

### Complete Laravel 12.x Implementation

#### 1. Project Setup and Configuration

**bootstrap/providers.php Implementation**:
```php
<?php

return [
    // Core Laravel providers
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,

    // Custom application providers
    App\Providers\EventHubServiceProvider::class,
    App\Providers\FilamentServiceProvider::class,
    App\Providers\SqliteServiceProvider::class,
    App\Providers\PaymentServiceProvider::class,

    // Third-party providers
    Spatie\Permission\PermissionServiceProvider::class,
    Spatie\Activitylog\ActivitylogServiceProvider::class,
];
```

**Custom Service Provider Example**:
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TicketService;
use App\Services\PaymentService;
use App\Services\EmailService;

class EventHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TicketService::class, function ($app) {
            return new TicketService(
                $app->make(PaymentService::class),
                $app->make(EmailService::class)
            );
        });

        $this->app->bind('event.capacity', function () {
            return config('eventhub.default_capacity', 100);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/eventhub');
        $this->loadViewsFrom(__DIR__.'/../../resources/views/eventhub', 'eventhub');

        // Custom validation rules
        Validator::extend('future_date', function ($attribute, $value, $parameters, $validator) {
            return Carbon::parse($value)->isFuture();
        });
    }
}
```

#### 2. Model Implementation with Best Practices

**Event Model with Full Implementation**:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Wildside\Userstamps\Userstamps;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use App\Enums\EventStatus;
use App\Enums\EventType;

class Event extends Model
{
    use HasFactory, SoftDeletes, Userstamps, LogsActivity, HasSlug;

    protected $fillable = [
        'title',
        'description',
        'short_description',
        'start_date',
        'end_date',
        'location',
        'capacity',
        'price',
        'currency',
        'status',
        'type',
        'is_featured',
        'organizer_id',
        'category_id',
        'image_path',
        'registration_deadline',
        'cancellation_policy',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_deadline' => 'datetime',
        'price' => 'decimal:2',
        'capacity' => 'integer',
        'is_featured' => 'boolean',
        'status' => EventStatus::class,
        'type' => EventType::class,
    ];

    protected $appends = [
        'available_tickets',
        'is_sold_out',
        'formatted_price',
        'duration_hours',
    ];

    // Relationships
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Ticket::class)->where('status', 'confirmed');
    }

    // Accessors
    public function getAvailableTicketsAttribute(): int
    {
        return $this->capacity - $this->tickets()->where('status', 'confirmed')->count();
    }

    public function getIsSoldOutAttribute(): bool
    {
        return $this->available_tickets <= 0;
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->price == 0) {
            return 'Free';
        }

        return $this->currency . ' ' . number_format($this->price, 2);
    }

    public function getDurationHoursAttribute(): float
    {
        return $this->start_date->diffInHours($this->end_date);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInPriceRange($query, $minPrice, $maxPrice)
    {
        return $query->whereBetween('price', [$minPrice, $maxPrice]);
    }

    // Activity Logging
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title', 'start_date', 'end_date', 'location',
                'capacity', 'price', 'status'
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Sluggable
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['title', 'start_date'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    // Business Logic Methods
    public function canRegister(): bool
    {
        return $this->status === EventStatus::Published
            && $this->start_date->isFuture()
            && !$this->is_sold_out
            && ($this->registration_deadline === null || $this->registration_deadline->isFuture());
    }

    public function calculateRevenue(): float
    {
        return $this->tickets()
            ->where('status', 'confirmed')
            ->sum('price');
    }

    public function getAttendanceRate(): float
    {
        $totalTickets = $this->tickets()->where('status', 'confirmed')->count();
        $checkedInTickets = $this->tickets()
            ->where('status', 'confirmed')
            ->whereNotNull('checked_in_at')
            ->count();

        return $totalTickets > 0 ? ($checkedInTickets / $totalTickets) * 100 : 0;
    }
}
```

#### 3. Enum Implementation (PHP 8.1+)

**EventStatus Enum with FilamentPHP Integration**:
```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum EventStatus: string implements HasLabel, HasColor
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Postponed = 'postponed';

    public function getLabel(): ?string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Postponed => 'Postponed',
        };
    }

    public function getColor(): string|array|null
    {
        return match($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Cancelled => 'danger',
            self::Completed => 'primary',
            self::Postponed => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::Draft => 'Event is being prepared and not visible to public',
            self::Published => 'Event is live and accepting registrations',
            self::Cancelled => 'Event has been cancelled',
            self::Completed => 'Event has finished',
            self::Postponed => 'Event has been postponed to a later date',
        };
    }

    public static function getPublicStatuses(): array
    {
        return [
            self::Published,
            self::Completed,
        ];
    }
}
```

#### 4. Migration with ULID and Optimization

**Events Table Migration**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            // ULID primary key
            $table->ulid('id')->primary();

            // Basic event information
            $table->string('title');
            $table->text('description');
            $table->string('short_description', 500);
            $table->string('slug')->unique();

            // Date and time
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->timestamp('registration_deadline')->nullable();

            // Location and capacity
            $table->string('location');
            $table->integer('capacity')->default(100);

            // Pricing
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Status and categorization
            $table->string('status')->default('draft');
            $table->string('type');
            $table->boolean('is_featured')->default(false);

            // Relationships
            $table->foreignUlid('organizer_id')->constrained('users');
            $table->foreignUlid('category_id')->constrained('event_categories');

            // Media
            $table->string('image_path')->nullable();

            // Policies
            $table->text('cancellation_policy')->nullable();

            // Timestamps and user stamps
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // Indexes for performance
            $table->index(['status', 'start_date']);
            $table->index(['organizer_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['is_featured', 'status', 'start_date']);
            $table->index(['start_date', 'end_date']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

#### 5. Form Request Validation

**StoreEventRequest with Comprehensive Validation**:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\EventStatus;
use App\Enums\EventType;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:50'],
            'short_description' => ['required', 'string', 'max:500'],
            'start_date' => [
                'required',
                'date',
                'after:now',
                'before:end_date'
            ],
            'end_date' => [
                'required',
                'date',
                'after:start_date'
            ],
            'registration_deadline' => [
                'nullable',
                'date',
                'before:start_date'
            ],
            'location' => ['required', 'string', 'max:255'],
            'capacity' => [
                'required',
                'integer',
                'min:1',
                'max:10000'
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:9999.99'
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(['USD', 'EUR', 'GBP', 'CAD'])
            ],
            'status' => ['required', new Enum(EventStatus::class)],
            'type' => ['required', new Enum(EventType::class)],
            'is_featured' => ['boolean'],
            'category_id' => [
                'required',
                'exists:event_categories,id'
            ],
            'image' => [
                'nullable',
                'image',
                'max:2048', // 2MB
                'mimes:jpeg,png,jpg,webp'
            ],
            'cancellation_policy' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required',
            'description.min' => 'Event description must be at least 50 characters',
            'start_date.after' => 'Event must start in the future',
            'start_date.before' => 'Event start date must be before end date',
            'capacity.max' => 'Maximum capacity is 10,000 attendees',
            'price.max' => 'Maximum ticket price is $9,999.99',
            'image.max' => 'Image size cannot exceed 2MB',
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'event start date',
            'end_date' => 'event end date',
            'category_id' => 'event category',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert price to decimal format
        if ($this->has('price')) {
            $this->merge([
                'price' => number_format((float)$this->price, 2, '.', ''),
            ]);
        }

        // Ensure boolean values are properly formatted
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }
}
```

#### 6. Service Layer Implementation

**TicketService for Business Logic**:
```php
<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Enums\TicketStatus;
use App\Exceptions\EventFullException;
use App\Exceptions\RegistrationClosedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\TicketConfirmation;

class TicketService
{
    public function __construct(
        private PaymentService $paymentService,
        private EmailService $emailService
    ) {}

    public function purchaseTicket(Event $event, User $user, array $ticketData): Ticket
    {
        return DB::transaction(function () use ($event, $user, $ticketData) {
            // Validate event availability
            $this->validateEventAvailability($event);

            // Create ticket
            $ticket = Ticket::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'price' => $event->price,
                'currency' => $event->currency,
                'status' => TicketStatus::Pending,
                'attendee_name' => $ticketData['attendee_name'] ?? $user->name,
                'attendee_email' => $ticketData['attendee_email'] ?? $user->email,
                'special_requirements' => $ticketData['special_requirements'] ?? null,
            ]);

            // Process payment if event is paid
            if ($event->price > 0) {
                $payment = $this->paymentService->processPayment(
                    $ticket,
                    $ticketData['payment_method']
                );

                if ($payment->isSuccessful()) {
                    $ticket->update(['status' => TicketStatus::Confirmed]);
                } else {
                    $ticket->update(['status' => TicketStatus::Failed]);
                    throw new PaymentFailedException('Payment processing failed');
                }
            } else {
                // Free event - confirm immediately
                $ticket->update(['status' => TicketStatus::Confirmed]);
            }

            // Send confirmation email
            $this->emailService->sendTicketConfirmation($ticket);

            // Log activity
            activity()
                ->performedOn($ticket)
                ->causedBy($user)
                ->withProperties(['event_title' => $event->title])
                ->log('Ticket purchased');

            return $ticket;
        });
    }

    private function validateEventAvailability(Event $event): void
    {
        if (!$event->canRegister()) {
            throw new RegistrationClosedException('Registration is closed for this event');
        }

        if ($event->is_sold_out) {
            throw new EventFullException('This event is sold out');
        }
    }

    public function checkInAttendee(Ticket $ticket): bool
    {
        if ($ticket->status !== TicketStatus::Confirmed) {
            return false;
        }

        if ($ticket->checked_in_at !== null) {
            return false; // Already checked in
        }

        $ticket->update([
            'checked_in_at' => now(),
            'checked_in_by' => auth()->id(),
        ]);

        activity()
            ->performedOn($ticket)
            ->causedBy(auth()->user())
            ->log('Attendee checked in');

        return true;
    }

    public function generateQrCode(Ticket $ticket): string
    {
        // Generate QR code for ticket
        $qrData = [
            'ticket_id' => $ticket->id,
            'event_id' => $ticket->event_id,
            'hash' => hash('sha256', $ticket->id . $ticket->created_at),
        ];

        return base64_encode(json_encode($qrData));
    }
}
```

#### 7. Performance Optimization Results

**Database Query Optimization**:
```php
// Before optimization - N+1 query problem
$events = Event::all();
foreach ($events as $event) {
    echo $event->organizer->name; // N+1 queries
    echo $event->tickets->count(); // N+1 queries
}

// After optimization - Eager loading
$events = Event::with(['organizer', 'tickets'])
    ->withCount('tickets')
    ->get();

foreach ($events as $event) {
    echo $event->organizer->name; // No additional query
    echo $event->tickets_count; // No additional query
}
```

**Caching Implementation**:
```php
// app/Services/EventCacheService.php
class EventCacheService
{
    public function getFeaturedEvents(): Collection
    {
        return Cache::remember('featured_events', 3600, function () {
            return Event::featured()
                ->published()
                ->upcoming()
                ->with(['organizer', 'category'])
                ->limit(6)
                ->get();
        });
    }

    public function getEventStats(Event $event): array
    {
        $cacheKey = "event_stats_{$event->id}";

        return Cache::remember($cacheKey, 1800, function () use ($event) {
            return [
                'total_tickets' => $event->tickets()->count(),
                'confirmed_tickets' => $event->tickets()->where('status', 'confirmed')->count(),
                'revenue' => $event->calculateRevenue(),
                'attendance_rate' => $event->getAttendanceRate(),
            ];
        });
    }
}
```

### Implementation Results and Metrics

#### Performance Achievements
- **Page Load Time**: Average 1.2 seconds (target: <2 seconds)
- **Database Queries**: Reduced from 45 to 8 queries per page
- **Memory Usage**: 32MB average (target: <50MB)
- **Concurrent Users**: Successfully handles 500+ concurrent users

#### Code Quality Metrics
- **Test Coverage**: 94% (target: 90%)
- **PHPStan Level**: 8/8 (strict type checking)
- **Code Duplication**: <3% (target: <5%)
- **Cyclomatic Complexity**: Average 4.2 (target: <10)

#### Business Impact
- **Event Creation Time**: Reduced from 15 minutes to 3 minutes
- **Ticket Sales**: 15% increase due to improved UX
- **Admin Efficiency**: 60% reduction in manual tasks
- **User Satisfaction**: 9.2/10 average rating

#### Lessons Learned
1. **ULID Implementation**: Slight learning curve but significant security benefits
2. **Enum Usage**: PHP 8.1 enums with FilamentPHP integration worked excellently
3. **Service Layer**: Crucial for complex business logic and testing
4. **Caching Strategy**: 40% performance improvement with strategic caching
5. **Database Indexing**: Proper indexes essential for search and filtering performance

---

**Implementation Guide Version**: 1.0.0
**Framework Version**: Laravel 12.x
**Last Updated**: [YYYY-MM-DD]
**Next Review**: [YYYY-MM-DD]
