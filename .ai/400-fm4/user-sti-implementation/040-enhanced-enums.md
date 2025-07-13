# 4. Enhanced Enums

## 4.1. PHP 8.1+ Enum Implementation

This section covers the implementation of enhanced PHP enums with FilamentPHP v4 integration helpers and utility methods for user roles, permissions, and statuses.

## 4.2. User Role Enum

### 4.2.1. Base Role Enum

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor, HasIcon
{
    case Guest = 'guest';
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    /**
     * Get the label for FilamentPHP v4.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Guest => 'Guest User',
            self::User => 'Standard User',
            self::Moderator => 'Moderator',
            self::Admin => 'Administrator',
            self::SuperAdmin => 'Super Administrator',
        };
    }

    /**
     * Get the color for FilamentPHP v4.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Guest => 'gray',
            self::User => 'primary',
            self::Moderator => 'warning',
            self::Admin => 'success',
            self::SuperAdmin => 'danger',
        };
    }

    /**
     * Get the icon for FilamentPHP v4.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Guest => 'heroicon-o-user',
            self::User => 'heroicon-o-user-circle',
            self::Moderator => 'heroicon-o-shield-check',
            self::Admin => 'heroicon-o-key',
            self::SuperAdmin => 'heroicon-o-star',
        };
    }

    /**
     * Get role hierarchy level.
     */
    public function getLevel(): int
    {
        return match ($this) {
            self::Guest => 0,
            self::User => 1,
            self::Moderator => 2,
            self::Admin => 3,
            self::SuperAdmin => 4,
        };
    }

    /**
     * Check if role has higher or equal level than another.
     */
    public function hasLevelOrHigher(self $role): bool
    {
        return $this->getLevel() >= $role->getLevel();
    }

    /**
     * Get default permissions for role.
     */
    public function getDefaultPermissions(): array
    {
        return match ($this) {
            self::Guest => [
                UserPermission::ViewPublicContent,
            ],
            self::User => [
                UserPermission::ViewPublicContent,
                UserPermission::ViewProfile,
                UserPermission::EditProfile,
                UserPermission::ChangePassword,
            ],
            self::Moderator => [
                UserPermission::ViewPublicContent,
                UserPermission::ViewProfile,
                UserPermission::EditProfile,
                UserPermission::ChangePassword,
                UserPermission::ModerateContent,
                UserPermission::ViewReports,
            ],
            self::Admin => [
                UserPermission::ViewPublicContent,
                UserPermission::ViewProfile,
                UserPermission::EditProfile,
                UserPermission::ChangePassword,
                UserPermission::ModerateContent,
                UserPermission::ViewReports,
                UserPermission::ManageUsers,
                UserPermission::ViewAnalytics,
                UserPermission::ManageSettings,
            ],
            self::SuperAdmin => UserPermission::cases(),
        };
    }

    /**
     * Get all roles as options for forms.
     */
    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $role) => [$role->value => $role->getLabel()])
            ->toArray();
    }

    /**
     * Get roles for specific context.
     */
    public static function getAssignableRoles(UserRole $currentUserRole): array
    {
        return collect(self::cases())
            ->filter(fn(self $role) => $role->getLevel() <= $currentUserRole->getLevel())
            ->values()
            ->toArray();
    }
}
```

## 4.3. User Permission Enum

### 4.3.1. Permission Enum Implementation

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserPermission: string implements HasLabel
{
    // Basic permissions
    case ViewPublicContent = 'view_public_content';
    case ViewProfile = 'view_profile';
    case EditProfile = 'edit_profile';
    case ChangePassword = 'change_password';

    // Content permissions
    case CreateContent = 'create_content';
    case EditContent = 'edit_content';
    case DeleteContent = 'delete_content';
    case ModerateContent = 'moderate_content';

    // User management permissions
    case ViewUsers = 'view_users';
    case ManageUsers = 'manage_users';
    case BanUsers = 'ban_users';
    case DeleteUsers = 'delete_users';

    // System permissions
    case ViewAnalytics = 'view_analytics';
    case ManageSettings = 'manage_settings';
    case ViewReports = 'view_reports';
    case ManageSystem = 'manage_system';

    // API permissions
    case AccessApi = 'access_api';
    case ManageApiTokens = 'manage_api_tokens';

    /**
     * Get the label for FilamentPHP v4.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ViewPublicContent => 'View Public Content',
            self::ViewProfile => 'View Profile',
            self::EditProfile => 'Edit Profile',
            self::ChangePassword => 'Change Password',
            self::CreateContent => 'Create Content',
            self::EditContent => 'Edit Content',
            self::DeleteContent => 'Delete Content',
            self::ModerateContent => 'Moderate Content',
            self::ViewUsers => 'View Users',
            self::ManageUsers => 'Manage Users',
            self::BanUsers => 'Ban Users',
            self::DeleteUsers => 'Delete Users',
            self::ViewAnalytics => 'View Analytics',
            self::ManageSettings => 'Manage Settings',
            self::ViewReports => 'View Reports',
            self::ManageSystem => 'Manage System',
            self::AccessApi => 'Access API',
            self::ManageApiTokens => 'Manage API Tokens',
        };
    }

    /**
     * Get permission category.
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::ViewPublicContent,
            self::ViewProfile,
            self::EditProfile,
            self::ChangePassword => 'Basic',
            
            self::CreateContent,
            self::EditContent,
            self::DeleteContent,
            self::ModerateContent => 'Content',
            
            self::ViewUsers,
            self::ManageUsers,
            self::BanUsers,
            self::DeleteUsers => 'User Management',
            
            self::ViewAnalytics,
            self::ManageSettings,
            self::ViewReports,
            self::ManageSystem => 'System',
            
            self::AccessApi,
            self::ManageApiTokens => 'API',
        };
    }

    /**
     * Get permissions grouped by category.
     */
    public static function getGroupedOptions(): array
    {
        return collect(self::cases())
            ->groupBy(fn(self $permission) => $permission->getCategory())
            ->map(fn($permissions) => $permissions->mapWithKeys(
                fn(self $permission) => [$permission->value => $permission->getLabel()]
            ))
            ->toArray();
    }

    /**
     * Check if permission is dangerous (requires extra confirmation).
     */
    public function isDangerous(): bool
    {
        return in_array($this, [
            self::BanUsers,
            self::DeleteUsers,
            self::DeleteContent,
            self::ManageSystem,
        ]);
    }
}
```

## 4.4. User Status Enum

### 4.4.1. Status Enum Implementation

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel, HasColor, HasIcon
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case Deleted = 'deleted';

    /**
     * Get the label for FilamentPHP v4.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
            self::Banned => 'Banned',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Get the color for FilamentPHP v4.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Suspended => 'danger',
            self::Banned => 'danger',
            self::Deleted => 'gray',
        };
    }

    /**
     * Get the icon for FilamentPHP v4.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Active => 'heroicon-o-check-circle',
            self::Inactive => 'heroicon-o-pause-circle',
            self::Suspended => 'heroicon-o-exclamation-triangle',
            self::Banned => 'heroicon-o-x-circle',
            self::Deleted => 'heroicon-o-trash',
        };
    }

    /**
     * Check if status allows login.
     */
    public function canLogin(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if status is considered active.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::Pending]);
    }

    /**
     * Check if status is terminal (cannot be changed).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Banned, self::Deleted]);
    }

    /**
     * Get allowed transitions from current status.
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Inactive, self::Banned],
            self::Active => [self::Inactive, self::Suspended, self::Banned],
            self::Inactive => [self::Active, self::Banned, self::Deleted],
            self::Suspended => [self::Active, self::Inactive, self::Banned],
            self::Banned => [], // Terminal state
            self::Deleted => [], // Terminal state
        };
    }

    /**
     * Check if can transition to another status.
     */
    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->getAllowedTransitions());
    }
}
```

## 4.5. FilamentPHP Integration Helpers

### 4.5.1. Enum Helper Trait

```php
<?php

namespace App\Traits;

trait HasEnumHelpers
{
    /**
     * Get enum options for FilamentPHP v4 select fields.
     */
    public static function getFilamentOptions(): array
    {
        return collect(static::cases())
            ->mapWithKeys(function ($case) {
                $label = method_exists($case, 'getLabel') 
                    ? $case->getLabel() 
                    : str($case->name)->title();
                    
                return [$case->value => $label];
            })
            ->toArray();
    }

    /**
     * Get enum for FilamentPHP v4 badge component.
     */
    public function getFilamentBadge(): array
    {
        $badge = ['label' => $this->getLabel()];

        if (method_exists($this, 'getColor')) {
            $badge['color'] = $this->getColor();
        }

        if (method_exists($this, 'getIcon')) {
            $badge['icon'] = $this->getIcon();
        }

        return $badge;
    }
}
```

### 4.5.2. Usage in FilamentPHP v4 Resources

```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;

// In form schema
Select::make('role')
    ->options(UserRole::getFilamentOptions())
    ->required(),

Select::make('status')
    ->options(UserStatus::getFilamentOptions())
    ->required(),

// In table columns (FilamentPHP v4 syntax)
TextColumn::make('role')
    ->formatStateUsing(fn (UserRole $state) => $state->getLabel())
    ->badge()
    ->color(fn (UserRole $state) => $state->getColor())
    ->icon(fn (UserRole $state) => $state->getIcon()),

TextColumn::make('status')
    ->formatStateUsing(fn (UserStatus $state) => $state->getLabel())
    ->badge()
    ->color(fn (UserStatus $state) => $state->getColor())
    ->icon(fn (UserStatus $state) => $state->getIcon()),
```

## 4.6. Enum Validation and Casting

### 4.6.1. Custom Validation Rules

```php
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EnumValue implements Rule
{
    public function __construct(private string $enumClass) {}

    public function passes($attribute, $value): bool
    {
        if (!enum_exists($this->enumClass)) {
            return false;
        }

        return in_array($value, array_column($this->enumClass::cases(), 'value'));
    }

    public function message(): string
    {
        return 'The :attribute must be a valid enum value.';
    }
}
```

### 4.6.2. Usage in Form Requests

```php
<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', new EnumValue(UserRole::class)],
            'status' => ['required', new EnumValue(UserStatus::class)],
        ];
    }
}
```

---

**Next**: [Unique Identifiers and Slugs](050-unique-identifiers-and-slugs.md) - ULID and slug implementation details.
