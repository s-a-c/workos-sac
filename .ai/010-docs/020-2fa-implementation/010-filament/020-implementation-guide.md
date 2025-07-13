# 2.0. 2FA Implementation Guide

## 2.1. Overview

This comprehensive guide provides step-by-step instructions for completing the two-factor authentication (2FA) implementation in your Laravel Filament application. The guide addresses the identified issues and provides a complete working solution.

## 2.2. Implementation Strategy

### 2.2.1. Approach Selection

**Recommended Approach**: Filament 3.2.x Stable Implementation

**Rationale**:
- Filament 4.0-beta11 has incomplete 2FA features
- Filament 3.2.x has mature, tested 2FA implementation
- Better documentation and community support
- Production-ready stability

### 2.2.2. Implementation Phases

| Phase | Description | Duration | Dependencies |
|-------|-------------|----------|--------------|
| **Phase 1** | Package Management | 30 min | Composer access |
| **Phase 2** | Configuration Setup | 45 min | Phase 1 complete |
| **Phase 3** | Code Implementation | 60 min | Phase 2 complete |
| **Phase 4** | Testing & Verification | 30 min | Phase 3 complete |

## 2.3. Phase 1: Package Management

### 2.3.1. Filament Version Downgrade

**Step 1**: Update Composer Dependencies

```bash
# Remove current Filament 4.0 beta
composer remove filament/filament

# Install stable Filament 3.2.x
composer require filament/filament:^3.2

# Install additional 2FA packages if needed
composer require pragmarx/google2fa-laravel
```

**Step 2**: Verify Package Versions

```bash
composer show | grep -E "(filament|google2fa|qr)"
```

**Expected Output**:
```
filament/filament                              3.2.x
pragmarx/google2fa                             8.0.3
pragmarx/google2fa-laravel                     2.1.0
pragmarx/google2fa-qrcode                      3.0.0
```

### 2.3.2. Alternative: Filament 4.0 Stable (Future)

If Filament 4.0 stable is available:

```bash
# Update to stable version
composer require filament/filament:^4.0 --no-dev
composer update
```

## 2.4. Phase 2: Configuration Setup

### 2.4.1. Google2FA Service Configuration

**Step 1**: Publish Google2FA Configuration

```bash
php artisan vendor:publish --provider="PragmaRX\Google2FALaravel\ServiceProvider"
```

**Step 2**: Configure Google2FA Settings

**File**: `config/google2fa.php`

```php
<?php

return [
    /*
     * Enable / disable Google2FA.
     */
    'enabled' => env('GOOGLE2FA_ENABLED', true),

    /*
     * Lifetime in minutes.
     */
    'lifetime' => env('GOOGLE2FA_LIFETIME', 1), // 1 minute

    /*
     * Renew lifetime at every new request.
     */
    'keep_alive' => env('GOOGLE2FA_KEEP_ALIVE', true),

    /*
     * Auth container binding.
     */
    'auth' => 'auth',

    /*
     * 2FA verified session var.
     */
    'session_var' => 'google2fa',

    /*
     * One Time Password request input name.
     */
    'otp_input' => 'one_time_password',

    /*
     * One Time Password Window.
     */
    'window' => 4,

    /*
     * Forbid user to reuse One Time Passwords.
     */
    'forbid_old_passwords' => false,

    /*
     * User's table column for google2fa secret.
     */
    'otp_secret_column' => 'app_authentication_secret',

    /*
     * Recovery codes table column.
     */
    'recovery_codes_column' => 'app_authentication_recovery_codes',

    /*
     * Encrypt secrets.
     */
    'encrypt' => true,

    /*
     * Application name (for QR Code).
     */
    'app_name' => env('APP_NAME', 'LFSL Filament Demo'),

    /*
     * QR Code inline.
     */
    'qr_code_inline' => true,
];
```

### 2.4.2. Environment Configuration

**File**: `.env`

```bash
# 2FA Configuration
GOOGLE2FA_ENABLED=true
GOOGLE2FA_LIFETIME=1
GOOGLE2FA_KEEP_ALIVE=true
```

### 2.4.3. Service Provider Registration

**File**: `config/app.php`

```php
'providers' => [
    // ... other providers
    PragmaRX\Google2FALaravel\ServiceProvider::class,
],
```

## 2.5. Phase 3: Code Implementation

### 2.5.1. Update AdminPanelProvider (Filament 3.2.x)

**File**: `app/Providers/Filament/AdminPanelProvider.php`

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
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
            ->login()
            ->profile()
            ->passwordReset()
            ->emailVerification()
            ->registration()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

### 2.5.2. Create 2FA Profile Page

**File**: `app/Filament/Pages/TwoFactorAuthentication.php`

```php
<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthentication extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static string $view = 'filament.pages.two-factor-authentication';
    
    protected static ?string $navigationGroup = 'Security';
    
    protected static ?string $title = 'Two-Factor Authentication';
    
    public ?string $confirmationCode = null;
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Two-Factor Authentication')
                    ->description('Add additional security to your account using two-factor authentication.')
                    ->schema([
                        ViewField::make('qr_code')
                            ->view('filament.forms.components.qr-code')
                            ->visible(fn () => !$this->userHas2FA()),
                            
                        TextInput::make('confirmationCode')
                            ->label('Confirmation Code')
                            ->placeholder('Enter the code from your authenticator app')
                            ->visible(fn () => !$this->userHas2FA())
                            ->required(),
                            
                        Actions::make([
                            Action::make('enable')
                                ->label('Enable 2FA')
                                ->action('enable2FA')
                                ->visible(fn () => !$this->userHas2FA())
                                ->color('success'),
                                
                            Action::make('disable')
                                ->label('Disable 2FA')
                                ->action('disable2FA')
                                ->visible(fn () => $this->userHas2FA())
                                ->color('danger')
                                ->requiresConfirmation(),
                        ]),
                    ]),
            ]);
    }
    
    protected function userHas2FA(): bool
    {
        return !empty(Auth::user()->app_authentication_secret);
    }
    
    public function enable2FA(): void
    {
        $user = Auth::user();
        $google2fa = new Google2FA();
        
        // Verify the confirmation code
        $secret = session('2fa_secret');
        if (!$google2fa->verifyKey($secret, $this->confirmationCode)) {
            Notification::make()
                ->title('Invalid confirmation code')
                ->danger()
                ->send();
            return;
        }
        
        // Save the secret
        $user->app_authentication_secret = $secret;
        $user->save();
        
        // Clear session
        session()->forget('2fa_secret');
        
        Notification::make()
            ->title('Two-factor authentication enabled successfully')
            ->success()
            ->send();
            
        $this->redirect(static::getUrl());
    }
    
    public function disable2FA(): void
    {
        $user = Auth::user();
        $user->app_authentication_secret = null;
        $user->app_authentication_recovery_codes = null;
        $user->save();
        
        Notification::make()
            ->title('Two-factor authentication disabled')
            ->success()
            ->send();
            
        $this->redirect(static::getUrl());
    }
}
```

### 2.5.3. Create QR Code View Component

**File**: `resources/views/filament/forms/components/qr-code.blade.php`

```blade
<div class="space-y-4">
    @php
        $user = auth()->user();
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        if (!session('2fa_secret')) {
            $secret = $google2fa->generateSecretKey();
            session(['2fa_secret' => $secret]);
        } else {
            $secret = session('2fa_secret');
        }

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCode = (new \chillerlan\QRCode\QRCode())->render($qrCodeUrl);
    @endphp

    <div class="text-center">
        <div class="inline-block p-4 bg-white rounded-lg">
            {!! $qrCode !!}
        </div>
        <p class="mt-2 text-sm text-gray-600">
            Scan this QR code with your authenticator app
        </p>
        <p class="mt-1 text-xs text-gray-500 font-mono">
            Manual entry key: {{ $secret }}
        </p>
    </div>
</div>
```

### 2.5.4. Create 2FA Page View

**File**: `resources/views/filament/pages/two-factor-authentication.blade.php`

```blade
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
    </x-filament-panels::form>
</x-filament-panels::page>
```

## 2.6. Phase 4: Testing & Verification

### 2.6.1. Clear Application Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### 2.6.2. Test 2FA Setup Process

1. **Access Admin Panel**: Navigate to `/admin`
2. **Login**: Use existing credentials
3. **Navigate to 2FA**: Go to Two-Factor Authentication page
4. **Scan QR Code**: Use Google Authenticator or similar app
5. **Enter Code**: Verify with 6-digit code
6. **Enable 2FA**: Confirm activation

### 2.6.3. Test 2FA Login Process

1. **Logout**: Exit admin panel
2. **Login**: Enter credentials
3. **2FA Challenge**: Should prompt for 6-digit code
4. **Verify**: Enter code from authenticator app
5. **Access Granted**: Should reach dashboard

## 2.7. Troubleshooting Common Issues

### 2.7.1. QR Code Not Displaying

**Symptoms**: Blank space where QR code should appear

**Solutions**:
```bash
# Install missing QR code dependencies
composer require chillerlan/php-qrcode

# Clear views cache
php artisan view:clear
```

### 2.7.2. Invalid Secret Key Errors

**Symptoms**: "Invalid secret key" during setup

**Solutions**:
```bash
# Check Google2FA configuration
php artisan config:show google2fa

# Verify encryption settings
php artisan tinker
>>> encrypt('test')
>>> decrypt(encrypt('test'))
```

### 2.7.3. 2FA Page Not Accessible

**Symptoms**: 404 error when accessing 2FA page

**Solutions**:
```bash
# Clear route cache
php artisan route:clear

# Check if page is registered
php artisan route:list | grep two-factor
```

---

**Navigation Footer**

← [Previous: Analysis Findings](010-analysis-findings.md) | [Next: Verification Checklist →](030-verification-checklist.md)

---

*Document Version: 1.0 | Last Updated: 2025-07-01 | Status: Complete*
