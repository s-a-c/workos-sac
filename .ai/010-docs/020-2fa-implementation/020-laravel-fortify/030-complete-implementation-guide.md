# 3.0 Laravel Fortify 2FA Implementation - Complete Implementation Guide

**Document Version**: 2.0  
**Last Updated**: 2025-07-01  
**Target Audience**: Junior Developers  
**Estimated Reading Time**: 28 minutes

## 3.1 Executive Summary

This document provides comprehensive implementation instructions for Laravel Fortify as the unified authentication system, including package installation, service provider configuration, user model transformation, and Filament integration. All commands and code examples are tested for Laravel 12.19.3 with PHP 8.4, Filament 4.0.0-beta11, and existing Google2FA Laravel package integration.

### 3.1.1 Implementation Roadmap

| Step | Component | Estimated Time | Dependencies |
|------|-----------|----------------|--------------|
| **3.2** | Package Installation | 15 minutes | Composer access |
| **3.3** | Service Provider Setup | 20 minutes | Packages installed |
| **3.4** | User Model Transformation | 15 minutes | Service providers |
| **3.5** | Filament Integration | 25 minutes | Model updated |
| **3.6** | Configuration & Routes | 20 minutes | Integration complete |

## 3.2 Package Installation and Configuration

### 3.2.1 Pre-Installation Assessment

**Current Environment Status:**
- ✅ Laravel Framework 12.19.3
- ✅ PHP 8.4.x
- ✅ Filament 4.0.0-beta11 (admin panel)
- ✅ Livewire/Flux 2.2.1 (UI components)
- ✅ Livewire/Volt 1.7.1 (functional components)
- ✅ pragmarx/google2fa-laravel 2.3.0 (existing 2FA library)

**Integration Notes:**
⚠️ **Important**: This implementation will integrate with the existing Google2FA Laravel package rather than conflict with it. Fortify uses the same underlying Google2FA library, ensuring seamless compatibility.

### 3.2.2 Laravel Fortify Installation

**Step 1: Install Core Packages**

```bash
# Navigate to project root
cd /Users/s-a-c/Herd/lfsl

# Install Laravel Fortify (latest stable)
composer require laravel/fortify "^1.27"

# Install Laravel Sanctum using Laravel 12.x official method
php artisan install:api
```

**Step 2: Install Fortify Resources**

```bash
# Install Fortify resources (publishes config and creates actions and service provider)
php artisan fortify:install

# Note: Sanctum configuration is automatically published by install:api
# Verify configuration files created
ls -la config/fortify.php config/sanctum.php
```

**Step 3: Update Environment Configuration**

Add to `.env` file:

```bash

# --- Fortify Configuration ---
FORTIFY_GUARD=web
FORTIFY_PASSWORDS=users
FORTIFY_USERNAME=email
FORTIFY_HOME=/dashboard

# --- Fortify Two-Factor Authentication Settings ---
TWO_FACTOR_AUTH_ENABLED=true
TWO_FACTOR_RECOVERY_CODES=8
TWO_FACTOR_CONFIRM_PASSWORD_TIMEOUT=10800

# --- Application 2FA Settings ---
APP_2FA_ISSUER="${APP_NAME}"
APP_2FA_DIGITS=6
APP_2FA_PERIOD=30
APP_2FA_ALGORITHM=sha512

# --- Sanctum Configuration ---
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,127.0.0.1:8000,::1

```

### 3.2.3 Fortify Configuration Setup

**Update config/fortify.php** (compatible with Filament 4.0.0-beta11):

```php
<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when a user
    | is resetting their password. This configured value should match one
    | of your password brokers setup in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | This value defines which model attribute should be considered as your
    | application's "username" field. Typically, this might be the email
    | address of the users but you are free to change this value here.
    |
    | Out of the box, Fortify expects forgot password and reset password
    | requests to have a field named 'email'. If the application uses
    | another name for the field you may define it below as needed.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | This value defines whether usernames should be lowercased before saving
    | them in the database, as some database system string fields are case
    | sensitive. You may disable this for your application if necessary.
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication or password reset when the operations are successful
    | and the user is authenticated. You are free to change this value.
    |
    */

    'home' => '/dashboard',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    |
    | Here you may specify which prefix Fortify will assign to all the routes
    | that it registers with the application. If necessary, you may change
    | subdomain under which all of the Fortify routes will be available.
    |
    */

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Fortify will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | By default, Fortify will throttle logins to five requests per minute for
    | every email and IP address combination. However, if you would like to
    | specify a custom rate limiter to call then you may specify it here.
    |
    */

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify if the routes returning views should be disabled as
    | you may not need them when building your own application. This may be
    | especially true if you're writing a custom single-page application.
    |
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of the Fortify features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    */

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            // 'window' => 0,
            'window' => env('TWO_FACTOR_CONFIRM_PASSWORD_TIMEOUT', 10800),
        ]),
    ],
];
```

## 3.3 Service Provider Implementation

### 3.3.1 Create FortifyServiceProvider

**Step 1: Generate Service Provider**

```bash
# Create Fortify service provider
php artisan make:provider FortifyServiceProvider
```

**Step 2: Implement FortifyServiceProvider**

```php
<?php
// app/Providers/FortifyServiceProvider.php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register any bindings or singletons here if needed
    }

    public function boot(): void
    {
        // Register Fortify actions
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Configure rate limiting
        $this->configureRateLimiting();

        // Register Volt + Flux views (compatible with existing Livewire setup)
        $this->registerVoltFluxViews();

        // Note: Two-factor authentication automatically integrates with
        // existing pragmarx/google2fa-laravel package
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            return Limit::perMinute(5)->by($email.$request->ip());
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    protected function registerVoltFluxViews(): void
    {
        Fortify::loginView(fn () => view('livewire.auth.login'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('livewire.auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
    }
}
```

**Step 3: Register Service Provider**

Edit `bootstrap/providers.php` (Laravel 12.x structure):

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\VoltServiceProvider::class,
    App\Providers\FortifyServiceProvider::class, // Add Fortify provider for unified auth
];
```

**Note**: This integrates Fortify with the existing Filament and Volt service providers without conflicts.

### 3.3.2 Create Fortify Action Classes

**Step 1: Create Actions Directory**

```bash
# Create actions directory structure
mkdir -p app/Actions/Fortify
```

**Step 2: Create Required Action Classes**

**CreateNewUser.php**:

```php
<?php
// app/Actions/Fortify/CreateNewUser.php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
```

**UpdateUserProfileInformation.php**:

```php
<?php
// app/Actions/Fortify/UpdateUserProfileInformation.php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update($user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    protected function updateVerifiedUser($user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
```

## 3.4 User Model Transformation

### 3.4.1 Update User Model for Fortify Integration

**Step 1: Add Fortify Imports and Trait**

```php
<?php
// app/Models/User.php

namespace App\Models;

// Existing imports (preserve all)
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Wildside\Userstamps\Userstamps;

// Add Fortify imports
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements 
    FilamentUser,                    // Keep for Filament panel access
    HasAvatar,                       // Keep for Filament UI
    MustVerifyEmail                  // Keep for email verification
{
    use HasFactory, 
        Notifiable, 
        SoftDeletes, 
        HasSlug, 
        LogsActivity, 
        Userstamps,
        TwoFactorAuthenticatable;    // Add Fortify trait
```

**Step 2: Update Model Properties**

```php
    protected $fillable = [
        'name',
        'email', 
        'password',
        'slug',
        'public_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        
        // Filament 2FA fields (preserve during transition)
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        
        // Fortify 2FA fields (primary system)
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            
            // Filament 2FA casts (preserve during transition)
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'has_email_authentication' => 'boolean',
            
            // Fortify 2FA casts (primary system)
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
```

### 3.4.2 Add Fortify Integration Methods

**Enhanced User Model Methods**:

```php
    /**
     * Determine if the user has enabled two-factor authentication for Fortify.
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return !is_null($this->two_factor_secret) && 
               !is_null($this->two_factor_confirmed_at);
    }

    /**
     * Get the QR code SVG for Fortify 2FA setup.
     */
    public function twoFactorQrCodeSvg(): string
    {
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        
        return $google2fa->getQRCodeInline(
            config('app.name'),
            $this->email,
            decrypt($this->two_factor_secret)
        );
    }

    /**
     * Get the two-factor authentication recovery codes.
     */
    public function recoveryCodes(): array
    {
        return json_decode(decrypt($this->two_factor_recovery_codes), true) ?? [];
    }

    /**
     * Confirm the user's two-factor authentication setup.
     */
    public function confirmTwoFactorAuth(string $code): bool
    {
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        
        if ($google2fa->verifyKey(decrypt($this->two_factor_secret), $code)) {
            $this->forceFill([
                'two_factor_confirmed_at' => now(),
            ])->save();
            
            return true;
        }
        
        return false;
    }

    /**
     * Check if user can access Filament panel with Fortify authentication.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Require Fortify 2FA for admin panel access
        return $this->hasEnabledTwoFactorAuthentication();
    }

    /**
     * Migrate Filament 2FA data to Fortify fields.
     */
    public function migrateToFortify2FA(): bool
    {
        if (is_null($this->app_authentication_secret)) {
            return false; // No Filament 2FA to migrate
        }
        
        if (!is_null($this->two_factor_secret)) {
            return true; // Already migrated
        }
        
        try {
            $this->forceFill([
                'two_factor_secret' => $this->app_authentication_secret,
                'two_factor_recovery_codes' => $this->app_authentication_recovery_codes,
                'two_factor_confirmed_at' => now(),
            ])->save();
            
            return true;
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to migrate user 2FA data', [
                'user_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
```

## 3.5 Filament Integration

### 3.5.1 Update AdminPanelProvider

**Modified AdminPanelProvider Configuration**:

```php
<?php
// app/Providers/Filament/AdminPanelProvider.php

namespace App\Providers\Filament;

use App\Http\Middleware\FortifyAuthenticateForFilament;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            
            // Remove Filament's built-in authentication features
            // ->login()
            // ->registration()
            // ->passwordReset()
            // ->emailVerification()
            // ->multiFactorAuthentication([...])
            
            // Configure for Fortify authentication
            ->authGuard('web')           // Use Fortify's web guard
            ->profile()                  // Keep profile management
            ->strictAuthorization()
            
            ->colors([
                'primary' => Color::Amber,
            ])
            
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FortifyAuthenticateForFilament::class, // Custom Fortify authentication
            ]);
    }
}
```

### 3.5.2 Create Custom Filament Authentication Middleware

**Create Custom Middleware**:

```bash
# Create custom middleware for Filament-Fortify integration
php artisan make:middleware FortifyAuthenticateForFilament
```

**Implement Custom Middleware**:

```php
<?php
// app/Http/Middleware/FortifyAuthenticateForFilament.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class FortifyAuthenticateForFilament
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Fortify web guard
        if (!Auth::guard('web')->check()) {
            return redirect()->route('login')
                ->with('message', 'Please log in to access the admin panel.');
        }
        
        $user = Auth::user();
        
        // Check if user has Fortify 2FA enabled (required for admin access)
        if (!$user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('two-factor.setup')
                ->with('status', 'two-factor-required-for-admin')
                ->with('message', 'Two-factor authentication is required to access the admin panel.');
        }
        
        // Check if user can access the current Filament panel
        $panel = \Filament\Facades\Filament::getCurrentPanel();
        if ($panel && !$user->canAccessPanel($panel)) {
            abort(403, 'You do not have permission to access this admin panel.');
        }
        
        return $next($request);
    }
}
```

## 3.6 Configuration and Routes

### 3.6.1 Authentication Configuration

**Update config/auth.php**:

```php
<?php

return [
    'defaults' => [
        'guard' => 'web',           // Single guard for all authentication
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
```

### 3.6.2 Route Configuration

**Update routes/web.php**:

```php
<?php

use App\Http\Controllers\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('welcome');
});

// Dashboard route (protected by Fortify 2FA)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Two-Factor Authentication routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/two-factor/setup', function () {
        return view('livewire.auth.two-factor.setup');
    })->name('two-factor.setup');
    
    Route::get('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
    Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
        ->name('two-factor.enable');
    Route::delete('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
        ->name('two-factor.disable');
    Route::get('/user/two-factor-qr-code', [TwoFactorAuthenticationController::class, 'qrCode'])
        ->name('two-factor.qr-code');
    Route::get('/user/two-factor-recovery-codes', [TwoFactorAuthenticationController::class, 'recoveryCodes'])
        ->name('two-factor.recovery-codes');
    Route::post('/user/two-factor-recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])
        ->name('two-factor.recovery-codes.regenerate');
});

// Note: Fortify routes are automatically registered
// Admin panel routes are handled by Filament with custom middleware
```

### 3.6.3 Create TwoFactorAuthenticationController

**Generate and Implement Controller**:

```bash
# Create controller for 2FA management
php artisan make:controller TwoFactorAuthenticationController
```

```php
<?php
// app/Http/Controllers/TwoFactorAuthenticationController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class TwoFactorAuthenticationController extends Controller
{
    public function show(Request $request)
    {
        return view('livewire.auth.two-factor.setup');
    }

    public function store(Request $request)
    {
        app(EnableTwoFactorAuthentication::class)($request->user());
        return back()->with('status', 'two-factor-authentication-enabled');
    }

    public function destroy(Request $request)
    {
        app(DisableTwoFactorAuthentication::class)($request->user());
        return back()->with('status', 'two-factor-authentication-disabled');
    }

    public function qrCode(Request $request)
    {
        return response($request->user()->twoFactorQrCodeSvg(), 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    public function recoveryCodes(Request $request)
    {
        return response()->json([
            'recovery_codes' => $request->user()->recoveryCodes(),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        app(GenerateNewRecoveryCodes::class)($request->user());
        return response()->json([
            'recovery_codes' => $request->user()->recoveryCodes(),
        ]);
    }
}
```

---

**Navigation Footer**

← [Previous: Migration Implementation Guide](020-migration-implementation-guide.md) | [Next: UI Components & Testing →](040-ui-components-testing.md)

---

**Document Information**
- **File Path**: `.ai/010-docs/020-2fa-implementation/020-laravel-fortify/030-complete-implementation-guide.md`
- **Document ID**: LF-2FA-003-CONSOLIDATED
- **Version**: 2.0
- **Compliance**: WCAG AA, Junior Developer Guidelines
