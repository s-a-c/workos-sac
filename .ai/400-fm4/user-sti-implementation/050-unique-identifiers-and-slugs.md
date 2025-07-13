# 5. Unique Identifiers and Slugs

## 5.1. ULID Implementation

### 5.1.1. Why ULID Over UUID?

**ULID Advantages:**
- **Lexicographically sortable**: Natural ordering by creation time
- **URL-safe**: No special characters, shorter than UUID
- **Performance**: Better database indexing and clustering
- **Timestamp embedded**: Contains creation timestamp
- **Case-insensitive**: Easier to work with in URLs

### 5.1.2. ULID Integration in User Model

```php
<?php

namespace App\Models;

use Symfony\Component\Uid\Ulid;
use Illuminate\Database\Eloquent\Model;

class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ulid',
        'name',
        'email',
        // ... other fields
    ];

    /**
     * Boot method to generate ULID.
     */
    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function (User $user) {
            if (empty($user->ulid)) {
                $user->ulid = (string) Ulid::generate();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Scope to find by ULID.
     */
    public function scopeByUlid($query, string $ulid)
    {
        return $query->where('ulid', $ulid);
    }

    /**
     * Get ULID timestamp.
     */
    public function getUlidTimestamp(): \DateTimeImmutable
    {
        return Ulid::fromString($this->ulid)->getDateTime();
    }

    /**
     * Check if ULID is valid.
     */
    public static function isValidUlid(string $ulid): bool
    {
        try {
            Ulid::fromString($ulid);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Generate a new ULID.
     */
    public static function generateUlid(): string
    {
        return (string) Ulid::generate();
    }
}
```

### 5.1.3. ULID Database Schema

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add ULID column
            $table->string('ulid', 26)->unique()->after('id');
            
            // Index for performance
            $table->index('ulid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
```

### 5.1.4. ULID Validation and Casting

```php
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Symfony\Component\Uid\Ulid;

class ValidUlid implements Rule
{
    public function passes($attribute, $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            Ulid::fromString($value);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function message(): string
    {
        return 'The :attribute must be a valid ULID.';
    }
}
```

## 5.2. Slug Implementation

### 5.2.1. Slug Configuration

```php
<?php

namespace App\Models;

use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class User extends Authenticatable
{
    use HasSlug;

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name', 'email'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(50)
            ->usingSeparator('-')
            ->usingLanguage('en')
            ->preventOverwrite();
    }

    /**
     * Custom slug generation logic.
     */
    public function generateSlug(): string
    {
        $baseSlug = $this->name 
            ? str($this->name)->slug() 
            : str($this->email)->before('@')->slug();

        // Ensure uniqueness
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Scope to find by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Get the URL using slug.
     */
    public function getUrlAttribute(): string
    {
        return route('users.show', $this->slug);
    }
}
```

### 5.2.2. Advanced Slug Configuration for Different User Types

```php
<?php

namespace App\Models;

class StandardUser extends User
{
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(50)
            ->usingSeparator('-')
            ->extraScope(fn ($builder) => $builder->where('type', 'standard_user'));
    }
}

class Admin extends User
{
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(50)
            ->usingSeparator('-')
            ->usingPrefix('admin')
            ->extraScope(fn ($builder) => $builder->where('type', 'admin'));
    }
}

class Guest extends User
{
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['ulid'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(30)
            ->usingSeparator('-')
            ->usingPrefix('guest')
            ->extraScope(fn ($builder) => $builder->where('type', 'guest'));
    }
}
```

## 5.3. Database Indexing Strategy

### 5.3.1. Optimized Indexes

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Primary indexes
            $table->index('ulid', 'users_ulid_index');
            $table->index('slug', 'users_slug_index');
            
            // Composite indexes for STI
            $table->index(['type', 'ulid'], 'users_type_ulid_index');
            $table->index(['type', 'slug'], 'users_type_slug_index');
            $table->index(['type', 'is_active'], 'users_type_active_index');
            
            // Performance indexes
            $table->index(['email', 'type'], 'users_email_type_index');
            $table->index(['created_at', 'type'], 'users_created_type_index');
            
            // Unique constraints
            $table->unique(['ulid'], 'users_ulid_unique');
            $table->unique(['slug', 'type'], 'users_slug_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_ulid_index');
            $table->dropIndex('users_slug_index');
            $table->dropIndex('users_type_ulid_index');
            $table->dropIndex('users_type_slug_index');
            $table->dropIndex('users_type_active_index');
            $table->dropIndex('users_email_type_index');
            $table->dropIndex('users_created_type_index');
            $table->dropUnique('users_ulid_unique');
            $table->dropUnique('users_slug_type_unique');
        });
    }
};
```

## 5.4. Route Model Binding

### 5.4.1. Custom Route Model Binding

```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ULID binding
        Route::bind('user_ulid', function (string $value) {
            return User::byUlid($value)->firstOrFail();
        });

        // Slug binding
        Route::bind('user_slug', function (string $value) {
            return User::bySlug($value)->firstOrFail();
        });

        // Type-specific bindings
        Route::bind('admin_slug', function (string $value) {
            return Admin::bySlug($value)->firstOrFail();
        });
    }
}
```

### 5.4.2. Route Definitions

```php
<?php

// In routes/web.php

// Using ULID
Route::get('/users/{user_ulid}', [UserController::class, 'show'])
    ->name('users.show.ulid');

// Using slug
Route::get('/u/{user_slug}', [UserController::class, 'show'])
    ->name('users.show.slug');

// Type-specific routes
Route::get('/admin/{admin_slug}', [AdminController::class, 'show'])
    ->name('admin.show');

// API routes with ULID
Route::apiResource('users', UserController::class)
    ->parameter('user', 'user_ulid');
```

## 5.5. Performance Considerations

### 5.5.1. Query Optimization

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserLookupService
{
    /**
     * Efficient user lookup by ULID.
     */
    public function findByUlid(string $ulid): ?User
    {
        return User::select(['id', 'ulid', 'type', 'name', 'email'])
            ->byUlid($ulid)
            ->first();
    }

    /**
     * Efficient user lookup by slug.
     */
    public function findBySlug(string $slug, ?string $type = null): ?User
    {
        $query = User::select(['id', 'ulid', 'slug', 'type', 'name', 'email'])
            ->bySlug($slug);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->first();
    }

    /**
     * Batch lookup by ULIDs.
     */
    public function findManyByUlids(array $ulids): Collection
    {
        return User::select(['id', 'ulid', 'type', 'name', 'email'])
            ->whereIn('ulid', $ulids)
            ->get()
            ->keyBy('ulid');
    }
}
```

### 5.5.2. Caching Strategy

```php
<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    /**
     * Cache user by ULID.
     */
    public static function findByUlidCached(string $ulid): ?self
    {
        return Cache::remember(
            "user:ulid:{$ulid}",
            now()->addHours(1),
            fn() => static::byUlid($ulid)->first()
        );
    }

    /**
     * Cache user by slug.
     */
    public static function findBySlugCached(string $slug): ?self
    {
        return Cache::remember(
            "user:slug:{$slug}",
            now()->addHours(1),
            fn() => static::bySlug($slug)->first()
        );
    }

    /**
     * Clear user cache on update.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updated(function (User $user) {
            Cache::forget("user:ulid:{$user->ulid}");
            Cache::forget("user:slug:{$user->slug}");
        });

        static::deleted(function (User $user) {
            Cache::forget("user:ulid:{$user->ulid}");
            Cache::forget("user:slug:{$user->slug}");
        });
    }
}
```

---

**Next**: [Data Objects](060-data-objects.md) - DTO and Value Object implementations with spatie/laravel-data.
