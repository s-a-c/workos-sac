# 6. Data Objects

## 6.1. Data Transfer Objects (DTOs) with Spatie Laravel Data

### 6.1.1. Base User Data Object

```php
<?php

namespace App\Data;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class UserData extends Data
{
    public function __construct(
        public ?int $id,
        
        #[Required, StringType]
        public string $name,
        
        #[Required, Email]
        public string $email,
        
        public ?Carbon $email_verified_at,
        
        #[Required]
        public UserRole $role,
        
        #[Required]
        public UserStatus $status,
        
        public string $ulid,
        public string $slug,
        public bool $is_active,
        public ?Carbon $last_login_at,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        
        #[DataCollectionOf(UserPermissionData::class)]
        public ?DataCollection $permissions = null,
        
        public ?UserProfileData $profile = null,
    ) {}

    /**
     * Create from Eloquent model.
     */
    public static function fromModel(\App\Models\User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            email_verified_at: $user->email_verified_at,
            role: $user->role,
            status: $user->status,
            ulid: $user->ulid,
            slug: $user->slug,
            is_active: $user->is_active,
            last_login_at: $user->last_login_at,
            created_at: $user->created_at,
            updated_at: $user->updated_at,
            permissions: UserPermissionData::collection($user->getPermissions()),
            profile: $user->profile_data ? UserProfileData::from($user->profile_data) : null,
        );
    }

    /**
     * Transform to array for API responses.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->ulid, // Use ULID for external API
            'name' => $this->name,
            'email' => $this->email,
            'role' => [
                'value' => $this->role->value,
                'label' => $this->role->getLabel(),
                'level' => $this->role->getLevel(),
            ],
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->getLabel(),
                'can_login' => $this->status->canLogin(),
            ],
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified_at !== null,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'permissions' => $this->permissions?->toArray(),
            'profile' => $this->profile?->toArray(),
        ];
    }
}
```

### 6.1.2. User Profile Data Object

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class UserProfileData extends Data
{
    public function __construct(
        #[StringType]
        public ?string $first_name = null,
        
        #[StringType]
        public ?string $last_name = null,
        
        #[StringType]
        public ?string $phone = null,
        
        #[StringType]
        public ?string $bio = null,
        
        #[StringType]
        public ?string $avatar_url = null,
        
        #[StringType]
        public ?string $timezone = null,
        
        #[StringType]
        public ?string $locale = null,
        
        public ?UserAddressData $address = null,
        public ?UserPreferencesData $preferences = null,
        public ?UserSocialLinksData $social_links = null,
    ) {}

    /**
     * Get full name.
     */
    public function getFullName(): ?string
    {
        if (!$this->first_name && !$this->last_name) {
            return null;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get initials.
     */
    public function getInitials(): string
    {
        $initials = '';
        
        if ($this->first_name) {
            $initials .= strtoupper(substr($this->first_name, 0, 1));
        }
        
        if ($this->last_name) {
            $initials .= strtoupper(substr($this->last_name, 0, 1));
        }
        
        return $initials ?: '??';
    }
}
```

### 6.1.3. Specialized User Data Objects

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class AdminData extends UserData
{
    public function __construct(
        // Inherit all UserData properties
        ...$userProperties,
        
        #[IntegerType]
        public int $admin_level = 1,
        
        #[StringType]
        public ?string $department = null,
        
        #[DataCollectionOf(AdminPermissionData::class)]
        public ?DataCollection $admin_permissions = null,
    ) {
        parent::__construct(...$userProperties);
    }

    /**
     * Create from Admin model.
     */
    public static function fromModel(\App\Models\Admin $admin): self
    {
        $userData = parent::fromModel($admin);
        
        return new self(
            ...$userData->toArray(),
            admin_level: $admin->admin_level,
            department: $admin->department,
            admin_permissions: AdminPermissionData::collection($admin->admin_permissions ?? []),
        );
    }

    /**
     * Get admin-specific API data.
     */
    public function toAdminApiArray(): array
    {
        return array_merge($this->toApiArray(), [
            'admin_level' => $this->admin_level,
            'department' => $this->department,
            'admin_permissions' => $this->admin_permissions?->toArray(),
        ]);
    }
}

class GuestData extends UserData
{
    public function __construct(
        // Inherit all UserData properties
        ...$userProperties,
        
        #[StringType]
        public ?string $session_id = null,
        
        public ?\Carbon\Carbon $expires_at = null,
        public ?array $conversion_data = null,
        public ?array $tracking_data = null,
    ) {
        parent::__construct(...$userProperties);
    }

    /**
     * Create from Guest model.
     */
    public static function fromModel(\App\Models\Guest $guest): self
    {
        $userData = parent::fromModel($guest);
        
        return new self(
            ...$userData->toArray(),
            session_id: $guest->session_id,
            expires_at: $guest->expires_at,
            conversion_data: $guest->conversion_data,
            tracking_data: $guest->tracking_data,
        );
    }

    /**
     * Check if guest is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
```

## 6.2. Value Objects

### 6.2.1. User Address Value Object

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class UserAddressData extends Data
{
    public function __construct(
        #[StringType]
        public ?string $street = null,
        
        #[StringType]
        public ?string $city = null,
        
        #[StringType]
        public ?string $state = null,
        
        #[StringType]
        public ?string $postal_code = null,
        
        #[StringType]
        public ?string $country = null,
    ) {}

    /**
     * Get formatted address.
     */
    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->street,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if address is complete.
     */
    public function isComplete(): bool
    {
        return !empty($this->street) 
            && !empty($this->city) 
            && !empty($this->country);
    }
}
```

### 6.2.2. User Preferences Value Object

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class UserPreferencesData extends Data
{
    public function __construct(
        #[StringType]
        public string $theme = 'light',
        
        #[StringType]
        public string $language = 'en',
        
        #[BooleanType]
        public bool $email_notifications = true,
        
        #[BooleanType]
        public bool $push_notifications = true,
        
        #[BooleanType]
        public bool $marketing_emails = false,
        
        #[BooleanType]
        public bool $two_factor_enabled = false,
        
        public array $notification_settings = [],
        public array $privacy_settings = [],
    ) {}

    /**
     * Get notification preference.
     */
    public function getNotificationPreference(string $type): bool
    {
        return $this->notification_settings[$type] ?? true;
    }

    /**
     * Set notification preference.
     */
    public function setNotificationPreference(string $type, bool $enabled): self
    {
        $settings = $this->notification_settings;
        $settings[$type] = $enabled;
        
        return new self(
            ...$this->toArray(),
            notification_settings: $settings
        );
    }
}
```

## 6.3. Form Request Integration

### 6.3.1. Data-Aware Form Requests

```php
<?php

namespace App\Http\Requests;

use App\Data\UserData;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\WithData;

class CreateUserRequest extends FormRequest
{
    use WithData;

    protected string $dataClass = UserData::class;

    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'enum:' . UserRole::class],
        ];
    }

    /**
     * Get validated data as DTO.
     */
    public function getData(): UserData
    {
        return UserData::from($this->validated());
    }
}
```

### 6.3.2. API Resource Integration

```php
<?php

namespace App\Http\Resources;

use App\Data\UserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $userData = UserData::fromModel($this->resource);
        
        return $userData->toApiArray();
    }
}

class UserCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($user) {
                return UserData::fromModel($user)->toApiArray();
            }),
            'meta' => [
                'total' => $this->collection->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
            ],
        ];
    }
}
```

## 6.4. Data Transformation and Validation

### 6.4.1. Custom Data Casts

```php
<?php

namespace App\Data\Casts;

use App\Enums\UserRole;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\DataProperty;

class UserRoleCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $context): UserRole
    {
        if ($value instanceof UserRole) {
            return $value;
        }

        if (is_string($value)) {
            return UserRole::from($value);
        }

        throw new \InvalidArgumentException('Cannot cast value to UserRole');
    }
}
```

### 6.4.2. Data Validation Rules

```php
<?php

namespace App\Data\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class UniqueUserEmail implements Rule
{
    public function __construct(private ?int $excludeId = null) {}

    public function passes($attribute, $value): bool
    {
        $query = User::where('email', $value);
        
        if ($this->excludeId) {
            $query->where('id', '!=', $this->excludeId);
        }
        
        return !$query->exists();
    }

    public function message(): string
    {
        return 'The email address is already taken.';
    }
}
```

---

**Next**: [Database Migrations](070-database-migrations.md) - Complete database schema design and migrations.
