# 3. State Management

## 3.1. Finite State Machine Overview

The User model implements a Finite State Machine (FSM) using `spatie/laravel-model-states` to manage user lifecycle states and `spatie/laravel-model-status` for status tracking and history.

## 3.2. User State Definitions

### 3.2.1. Base User State Class

```php
<?php

namespace App\States\User;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class UserState extends State
{
    /**
     * Configure state transitions.
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ActiveState::class)
            ->allowTransition(PendingState::class, InactiveState::class)
            ->allowTransition(ActiveState::class, InactiveState::class)
            ->allowTransition(ActiveState::class, SuspendedState::class)
            ->allowTransition(InactiveState::class, ActiveState::class)
            ->allowTransition(SuspendedState::class, ActiveState::class)
            ->allowTransition(SuspendedState::class, InactiveState::class)
            ->allowTransition([ActiveState::class, InactiveState::class, SuspendedState::class], BannedState::class)
            ->allowTransition(GuestState::class, PendingState::class);
    }

    /**
     * Get state display name.
     */
    abstract public function getDisplayName(): string;

    /**
     * Get state color for UI.
     */
    abstract public function getColor(): string;

    /**
     * Check if state allows login.
     */
    abstract public function canLogin(): bool;

    /**
     * Get allowed actions for this state.
     */
    abstract public function getAllowedActions(): array;
}
```

### 3.2.2. Specific State Implementations

#### Pending State
```php
<?php

namespace App\States\User;

class PendingState extends UserState
{
    public static string $name = 'pending';

    public function getDisplayName(): string
    {
        return 'Pending Verification';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function canLogin(): bool
    {
        return false;
    }

    public function getAllowedActions(): array
    {
        return [
            'verify_email',
            'resend_verification',
        ];
    }
}
```

#### Active State
```php
<?php

namespace App\States\User;

class ActiveState extends UserState
{
    public static string $name = 'active';

    public function getDisplayName(): string
    {
        return 'Active';
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function canLogin(): bool
    {
        return true;
    }

    public function getAllowedActions(): array
    {
        return [
            'login',
            'update_profile',
            'change_password',
            'access_features',
            'logout',
        ];
    }
}
```

#### Inactive State
```php
<?php

namespace App\States\User;

class InactiveState extends UserState
{
    public static string $name = 'inactive';

    public function getDisplayName(): string
    {
        return 'Inactive';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function canLogin(): bool
    {
        return false;
    }

    public function getAllowedActions(): array
    {
        return [
            'reactivate_account',
        ];
    }
}
```

#### Suspended State
```php
<?php

namespace App\States\User;

class SuspendedState extends UserState
{
    public static string $name = 'suspended';

    public function getDisplayName(): string
    {
        return 'Suspended';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function canLogin(): bool
    {
        return false;
    }

    public function getAllowedActions(): array
    {
        return [
            'appeal_suspension',
            'view_suspension_reason',
        ];
    }
}
```

#### Banned State
```php
<?php

namespace App\States\User;

class BannedState extends UserState
{
    public static string $name = 'banned';

    public function getDisplayName(): string
    {
        return 'Banned';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function canLogin(): bool
    {
        return false;
    }

    public function getAllowedActions(): array
    {
        return [];
    }
}
```

#### Guest State
```php
<?php

namespace App\States\User;

class GuestState extends UserState
{
    public static string $name = 'guest';

    public function getDisplayName(): string
    {
        return 'Guest';
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function canLogin(): bool
    {
        return false;
    }

    public function getAllowedActions(): array
    {
        return [
            'browse_content',
            'register',
            'track_activity',
        ];
    }
}
```

## 3.3. State Transitions

### 3.3.1. Transition Classes

```php
<?php

namespace App\States\User\Transitions;

use App\Models\User;
use App\States\User\ActiveState;
use App\States\User\PendingState;
use Spatie\ModelStates\Transition;

class ActivateUser extends Transition
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): User
    {
        $this->user->state = new ActiveState($this->user);
        $this->user->is_active = true;
        $this->user->email_verified_at = now();
        $this->user->save();

        // Log status change
        $this->user->setStatus('activated', 'User account activated');

        // Fire event
        event(new \App\Events\UserActivated($this->user));

        return $this->user;
    }
}
```

### 3.3.2. Using Transitions

```php
<?php

namespace App\Services;

use App\Models\User;
use App\States\User\ActiveState;
use App\States\User\Transitions\ActivateUser;

class UserStateService
{
    /**
     * Activate a pending user.
     */
    public function activateUser(User $user): User
    {
        if (!$user->state->canTransitionTo(ActiveState::class)) {
            throw new \InvalidArgumentException('Cannot activate user in current state');
        }

        return $user->state->transitionTo(ActivateUser::class);
    }

    /**
     * Suspend an active user.
     */
    public function suspendUser(User $user, string $reason = null): User
    {
        $user->state->transitionTo(SuspendedState::class);
        
        if ($reason) {
            $user->setStatus('suspended', $reason);
        }

        return $user;
    }

    /**
     * Check if user can perform action based on state.
     */
    public function canPerformAction(User $user, string $action): bool
    {
        return in_array($action, $user->state->getAllowedActions());
    }
}
```

## 3.4. Status Tracking

### 3.4.1. Status Implementation

```php
<?php

namespace App\Models;

// Add to User model
class User extends Authenticatable
{
    // ... existing code ...

    /**
     * Set user status with reason.
     */
    public function setUserStatus(string $name, string $reason = null): void
    {
        $this->setStatus($name, $reason);
    }

    /**
     * Get current status.
     */
    public function getCurrentStatus(): ?\Spatie\ModelStatus\Status
    {
        return $this->latestStatus();
    }

    /**
     * Get status history.
     */
    public function getStatusHistory(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->statuses()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Check if user has specific status.
     */
    public function hasStatus(string $name): bool
    {
        return $this->latestStatus()?->name === $name;
    }
}
```

### 3.4.2. Status Usage Examples

```php
<?php

// Set status with reason
$user->setUserStatus('email_verified', 'Email verification completed');

// Check current status
if ($user->hasStatus('email_verified')) {
    // User email is verified
}

// Get status history for audit trail
$statusHistory = $user->getStatusHistory();
foreach ($statusHistory as $status) {
    echo "{$status->name}: {$status->reason} at {$status->created_at}";
}
```

## 3.5. State-Based Middleware

### 3.5.1. State Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\States\User\ActiveState;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->state instanceof ActiveState) {
            return redirect()->route('account.inactive')
                ->with('error', 'Your account is not active.');
        }

        return $next($request);
    }
}
```

### 3.5.2. Route Protection

```php
<?php

// In routes/web.php
Route::middleware(['auth', 'user.active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

## 3.6. State Events and Listeners

### 3.6.1. State Change Events

```php
<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStateChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $fromState,
        public string $toState
    ) {}
}
```

### 3.6.2. Event Listeners

```php
<?php

namespace App\Listeners;

use App\Events\UserStateChanged;
use App\Notifications\UserStateChangedNotification;

class NotifyUserStateChange
{
    public function handle(UserStateChanged $event): void
    {
        $event->user->notify(
            new UserStateChangedNotification($event->fromState, $event->toState)
        );
    }
}
```

---

**Next**: [Enhanced Enums](040-enhanced-enums.md) - PHP 8.1+ enum implementations with FilamentPHP integration.
