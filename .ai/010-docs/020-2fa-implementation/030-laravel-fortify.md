# 1.0 Laravel Fortify Two-Factor Authentication Implementation Guide

**Document Version**: 1.0
**Last Updated**: 2025-07-02
**Target Audience**: Junior Developers
**Estimated Reading Time**: 45 minutes
**Implementation Time**: 4-6 hours

## Table of Contents

<details><summary>Table of Contents</summary>

- [1.1 Executive Summary](#11-executive-summary)
    - [1.1.1 Implementation Approach](#111-implementation-approach)
    - [1.1.2 Prerequisites Verification](#112-prerequisites-verification)
- [1.2 Architecture Overview](#12-architecture-overview)
    - [1.2.1 Component Architecture](#121-component-architecture)
    - [1.2.2 Security Architecture](#122-security-architecture)
- [1.3 Database Schema Requirements](#13-database-schema-requirements)
    - [1.3.1 Current Schema Status](#131-current-schema-status)
    - [1.3.2 Data Validation](#132-data-validation)
- [1.4 Artisan Command Sequence](#14-artisan-command-sequence)
    - [1.4.1 Component Generation Commands](#141-component-generation-commands)
    - [1.4.2 Route Registration](#142-route-registration)
- [1.5 Component Implementation Guide](#15-component-implementation-guide)
    - [1.5.1 Two-Factor Setup Component](#151-two-factor-setup-component)
    - [1.5.2 Two-Factor Challenge Component](#152-two-factor-challenge-component)
    - [1.5.3 Recovery Codes Management](#153-recovery-codes-management)
- [1.6 Route and Middleware Configuration](#16-route-and-middleware-configuration)
    - [1.6.1 Enhanced Route Protection](#161-enhanced-route-protection)
    - [1.6.2 Fortify Configuration Enhancement](#162-fortify-configuration-enhancement)
- [1.7 Security Considerations](#17-security-considerations)
    - [1.7.1 Best Practices Implementation](#171-best-practices-implementation)
    - [1.7.2 Vulnerability Prevention](#172-vulnerability-prevention)
- [1.8 User Experience Guidelines](#18-user-experience-guidelines)
    - [1.8.1 Accessibility Requirements (WCAG AA)](#181-accessibility-requirements-wcag-aa)
    - [1.8.2 User Journey Design](#182-user-journey-design)
- [1.9 Implementation Details](#19-implementation-details)
    - [1.9.1 Phase 1: Component Creation (🔴 → 🟡)](#191-phase-1-component-creation---)
    - [1.9.2 Phase 2: Component Implementation (🟡 → 🟢)](#192-phase-2-component-implementation---)
    - [1.9.3 Phase 3: Testing Implementation (🟢 → 🔵)](#193-phase-3-testing-implementation---)
    - [1.9.4 Phase 4: Integration and Validation (🔵 → ✅)](#194-phase-4-integration-and-validation---)
- [1.10 Testing Strategy (TDD with Pest Framework)](#110-testing-strategy-tdd-with-pest-framework)
    - [1.10.1 Test Categories](#1101-test-categories)
    - [1.10.2 Database Testing Strategy](#1102-database-testing-strategy)
    - [1.10.3 Test Scenarios](#1103-test-scenarios)
- [1.11 Component Code Examples](#111-component-code-examples)
    - [1.11.1 Two-Factor Authentication Setup Component](#1111-two-factor-authentication-setup-component)
    - [1.11.2 Two-Factor Challenge Component](#1112-two-factor-challenge-component)
    - [1.11.3 Settings Integration](#1113-settings-integration)
- [1.12 Controller Implementation](#112-controller-implementation)
    - [1.12.1 TwoFactorAuthenticationController](#1121-twofactorauthenticationcontroller)
- [1.13 Form Request Validation](#113-form-request-validation)
    - [1.13.1 EnableTwoFactorRequest](#1131-enabletwofactorrequest)
    - [1.13.2 ConfirmTwoFactorRequest](#1132-confirmtwofactorrequest)
- [1.14 Comprehensive Testing Implementation](#114-comprehensive-testing-implementation)
    - [1.14.1 Feature Tests](#1141-feature-tests)
    - [1.14.2 Two-Factor Challenge Tests](#1142-two-factor-challenge-tests)
    - [1.14.3 Component Tests](#1143-component-tests)
- [1.15 Security Implementation & Best Practices](#115-security-implementation--best-practices)
    - [1.15.1 Encryption and Data Protection](#1151-encryption-and-data-protection)
    - [1.15.2 Rate Limiting Configuration](#1152-rate-limiting-configuration)
    - [1.15.3 Audit Logging Integration](#1153-audit-logging-integration)
    - [1.15.4 Session Security](#1154-session-security)
- [1.16 Deployment Procedures](#116-deployment-procedures)
    - [1.16.1 Pre-Deployment Checklist](#1161-pre-deployment-checklist)
    - [1.16.2 Deployment Steps](#1162-deployment-steps)
    - [1.16.3 Post-Deployment Validation](#1163-post-deployment-validation)
- [1.17 Troubleshooting Guide](#117-troubleshooting-guide)
    - [1.17.1 Common Issues and Solutions](#1171-common-issues-and-solutions)
    - [1.17.2 Debug Commands](#1172-debug-commands)
    - [1.17.3 Performance Monitoring](#1173-performance-monitoring)
- [1.18 Maintenance and Updates](#118-maintenance-and-updates)
    - [1.18.1 Regular Maintenance Tasks](#1181-regular-maintenance-tasks)
    - [1.18.2 Future Enhancements](#1182-future-enhancements)
- [1.19 Success Criteria and Validation](#119-success-criteria-and-validation)
    - [1.19.1 Implementation Success Metrics](#1191-implementation-success-metrics)
    - [1.19.2 Final Validation Checklist](#1192-final-validation-checklist)

</details>

## 1.1 Executive Summary

This document provides a comprehensive implementation guide for enhancing the existing Laravel Fortify authentication
system with two-factor authentication (2FA) capabilities. The implementation leverages the already-installed Laravel
Fortify package and existing Livewire/Volt + Flux/Flux-Pro UI components to create a seamless, secure, and accessible
2FA experience.

### 1.1.1 Implementation Approach

**🔵 ENHANCEMENT STRATEGY**: This implementation enhances the existing authentication system rather than replacing it.
Users without 2FA enabled will experience no changes to their current login experience.

**Key Benefits**:

- Seamless integration with existing Laravel Fortify installation
- Modern Livewire/Volt functional components with Flux/Flux-Pro UI
- WCAG AA accessibility compliance
- Comprehensive security with recovery codes and password confirmation
- Test-driven development with Pest framework

### 1.1.2 Prerequisites Verification

**✅ Current System Status**:

- Laravel Framework 12.19.3 ✅
- Laravel Fortify ^1.27 ✅ (Already installed)
- Laravel Sanctum ^4.0 ✅ (Already installed via `php artisan install:api`)
- Livewire/Volt ^1.7.0 ✅
- Flux/Flux-Pro ^2.1 ✅
- Database schema with 2FA columns ✅
- User model with `TwoFactorAuthenticatable` trait ✅

## 1.2 Architecture Overview

### 1.2.1 Component Architecture

```
Authentication Flow Enhancement:
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Login Form    │───▶│  2FA Challenge   │───▶│   Dashboard     │
│ (Existing)      │    │   (New)          │    │  (Existing)     │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                              │
                              ▼
                       ┌──────────────────┐
                       │ Recovery Codes   │
                       │   (Fallback)     │
                       └──────────────────┘

2FA Management Interface:
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Settings Page  │───▶│   2FA Setup      │───▶│ Recovery Codes  │
│  (Enhanced)     │    │    (New)         │    │    (New)        │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### 1.2.2 Security Architecture

**🔒 Security Layers**:

1. **Password Confirmation**: Required before enabling/disabling 2FA
2. **QR Code Validation**: Users must prove they can generate codes before enabling
3. **Recovery Codes**: 8 single-use backup codes for account recovery
4. **Rate Limiting**: Protection against brute force attacks
5. **Session Management**: Secure handling of 2FA state

## 1.3 Database Schema Requirements

### 1.3.1 Current Schema Status

**✅ Already Implemented**: The required 2FA columns are already present in the users table:

```sql
-- Migration: 2025_07_01_163825_add_two_factor_columns_to_users_table.php
ALTER TABLE users
    ADD COLUMN two_factor_secret TEXT NULL AFTER password;
ALTER TABLE users
    ADD COLUMN two_factor_recovery_codes TEXT NULL AFTER two_factor_secret;
ALTER TABLE users
    ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL AFTER two_factor_recovery_codes;
```

**Column Purposes**:

- `two_factor_secret`: Encrypted TOTP secret key
- `two_factor_recovery_codes`: Encrypted JSON array of recovery codes
- `two_factor_confirmed_at`: Timestamp when 2FA was successfully enabled

### 1.3.2 Data Validation

**Verification Commands**:

```bash
# Verify schema exists
php artisan tinker
>>> Schema::hasColumn('users', 'two_factor_secret')
>>> Schema::hasColumn('users', 'two_factor_recovery_codes')
>>> Schema::hasColumn('users', 'two_factor_confirmed_at')
```

## 1.4 Artisan Command Sequence

### 1.4.1 Component Generation Commands

**All commands verified against Laravel 12.x and Volt official documentation**:

> **⚠️ Important**: These commands use the correct Laravel 12.x and Volt syntax. Note the use of
`make:volt --functional` instead of the deprecated `make:livewire --volt` syntax, and the inclusion of `--pest` flags
> for all generation commands to ensure proper test scaffolding.

> **📁 File Naming Convention**: Volt automatically converts PascalCase component names to kebab-case file names:
> - `TwoFactorAuthentication` → `two-factor-authentication.blade.php`
> - `TwoFactorChallenge` → `two-factor-challenge.blade.php`
> - `TwoFactorRecoveryCodes` → `two-factor-recovery-codes.blade.php`

```bash
# 1. Generate 2FA management components (Volt functional components)
php artisan make:volt TwoFactorAuthentication --functional --pest
php artisan make:volt TwoFactorChallenge --functional --pest
php artisan make:volt TwoFactorRecoveryCodes --functional --pest

# 2. Generate controller for 2FA API endpoints
php artisan make:controller TwoFactorAuthenticationController --pest

# 3. Generate middleware for enhanced security
php artisan make:middleware EnsureTwoFactorEnabled --pest

# 4. Generate form requests for validation
php artisan make:request EnableTwoFactorRequest --pest
php artisan make:request DisableTwoFactorRequest --pest
php artisan make:request ConfirmTwoFactorRequest --pest

# 5. Generate test files
php artisan make:test TwoFactorAuthenticationTest --pest
php artisan make:test TwoFactorChallengeTest --pest
php artisan make:test TwoFactorRecoveryTest --pest

# 6. Generate factory modifications (if needed)
php artisan make:factory UserFactory --model=User --pest
```

### 1.4.2 Route Registration

**Routes are automatically registered by Laravel Fortify**:

- `POST /two-factor-authentication` - Enable 2FA
- `DELETE /two-factor-authentication` - Disable 2FA
- `POST /two-factor-authentication/confirm` - Confirm 2FA setup
- `POST /two-factor-authentication/recovery-codes` - Generate new recovery codes
- `POST /two-factor-challenge` - Verify 2FA code during login

## 1.5 Component Implementation Guide

### 1.5.1 Two-Factor Setup Component

**File**: `resources/views/livewire/two-factor-authentication.blade.php` (Volt functional component)

**Core Functionality**:

- QR code generation and display
- Code verification before enabling
- Recovery codes generation and display
- Password confirmation for security actions
- Accessible UI with proper ARIA labels

### 1.5.2 Two-Factor Challenge Component

**File**: `resources/views/livewire/two-factor-challenge.blade.php` (Volt functional component)

**Core Functionality**:

- TOTP code input during login
- Recovery code fallback option
- Rate limiting protection
- Clear error messaging
- Accessibility compliance

### 1.5.3 Recovery Codes Management

**File**: `resources/views/livewire/two-factor-recovery-codes.blade.php` (Volt functional component)

**Core Functionality**:

- Display recovery codes securely
- Regenerate recovery codes
- Download/print functionality
- Usage tracking and warnings

## 1.6 Route and Middleware Configuration

### 1.6.1 Enhanced Route Protection

```php
// routes/web.php - Enhanced with 2FA awareness
Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('settings', 'settings/profile');
    
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/two-factor', 'settings.two-factor')->name('settings.two-factor');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});
```

### 1.6.2 Fortify Configuration Enhancement

**File**: `config/fortify.php` (Already configured)

**Key Settings**:

- Two-factor authentication enabled with confirmation
- Password confirmation timeout: 10800 seconds (3 hours)
- Recovery codes: 8 codes generated

## 1.7 Security Considerations

### 1.7.1 Best Practices Implementation

**🔒 Security Measures**:

1. **Encryption**: All 2FA secrets and recovery codes encrypted at rest
2. **Rate Limiting**: 5 attempts per minute for 2FA verification
3. **Password Confirmation**: Required for all 2FA management actions
4. **Secure Display**: QR codes and recovery codes shown only when needed
5. **Audit Logging**: All 2FA actions logged via Spatie Activity Log

### 1.7.2 Vulnerability Prevention

**Protection Against**:

- **Brute Force**: Rate limiting on 2FA endpoints
- **Session Hijacking**: Proper session regeneration after 2FA
- **Recovery Code Abuse**: Single-use codes with secure storage
- **QR Code Exposure**: Time-limited display with user confirmation

## 1.8 User Experience Guidelines

### 1.8.1 Accessibility Requirements (WCAG AA)

**🌐 Accessibility Features**:

- Proper ARIA labels for all form elements
- Keyboard navigation support
- Screen reader compatibility
- High contrast color schemes
- Clear error messaging
- Alternative text for QR codes

### 1.8.2 User Journey Design

**Setup Flow**:

1. User navigates to Settings → Two-Factor Authentication
2. Password confirmation required
3. QR code displayed with setup instructions
4. User scans QR code with authenticator app
5. User enters verification code to confirm setup
6. Recovery codes generated and displayed
7. 2FA successfully enabled

**Login Flow Enhancement**:

1. User enters email/password (existing flow)
2. If 2FA enabled: redirect to 2FA challenge page
3. User enters TOTP code or recovery code
4. Successful verification: redirect to dashboard
5. Failed verification: error message with retry option

## 1.9 Implementation Details

### 1.9.1 Phase 1: Component Creation (🔴 → 🟡)

**Estimated Time**: 2 hours
**Dependencies**: None

**Tasks**:

- [ ] Generate Volt functional components using official Laravel 12.x commands
- [ ] Create controller for 2FA management endpoints with Pest testing
- [ ] Generate form request classes for validation with Pest testing
- [ ] Create middleware for enhanced security with Pest testing
- [ ] Verify all generated files are properly structured

**Implementation Steps**:

```bash
# Step 1: Generate core Volt functional components
php artisan make:volt --functional --pest Two-Factor-Authentication
php artisan make:volt --functional --pest Two-Factor-Challenge
php artisan make:volt --functional --pest Two-Factor-Recovery-Codes
php artisan make:volt --functional --pest Settings/Two-Factor

# Step 2: Generate supporting classes with Pest testing
php artisan make:controller --pest TwoFactorAuthenticationController
php artisan make:middleware --pest EnsureTwoFactorEnabled
php artisan make:request EnableTwoFactorRequest
php artisan make:request DisableTwoFactorRequest
php artisan make:request ConfirmTwoFactorRequest

# Step 3: Verify file creation (note kebab-case naming for Volt components)
ls -la resources/views/livewire/
# Expected files: two-factor-authentication.blade.php, two-factor-challenge.blade.php, two-factor-recovery-codes.blade.php

ls -la app/Http/Controllers/
ls -la app/Http/Middleware/
ls -la app/Http/Requests/
```

### 1.9.2 Phase 2: Component Implementation (🟡 → 🟢)

**Estimated Time**: 3 hours
**Dependencies**: Phase 1 complete

**Tasks**:

- [ ] Implement TwoFactorAuthentication component with Flux UI
- [ ] Implement TwoFactorChallenge component for login flow
- [ ] Implement TwoFactorRecoveryCodes management
- [ ] Configure controller methods for API endpoints
- [ ] Add proper validation and error handling

### 1.9.3 Phase 3: Testing Implementation (🟢 → 🔵)

**Estimated Time**: 2 hours
**Dependencies**: Phase 2 complete

**Tasks**:

- [ ] Create comprehensive test suite using Pest
- [ ] Test 2FA setup flow end-to-end
- [ ] Test login flow with 2FA enabled
- [ ] Test recovery code functionality
- [ ] Test security edge cases and error conditions

### 1.9.4 Phase 4: Integration and Validation (🔵 → ✅)

**Estimated Time**: 1 hour
**Dependencies**: Phase 3 complete

**Tasks**:

- [ ] Integrate components with existing settings page
- [ ] Update navigation and user interface
- [ ] Perform accessibility audit (WCAG AA)
- [ ] Conduct security review
- [ ] Document final configuration and usage

## 1.10 Testing Strategy (TDD with Pest Framework)

### 1.10.1 Test Categories

**🧪 Test Coverage Requirements**:

- **Feature Tests**: Complete user journeys (setup, login, recovery)
- **Unit Tests**: Individual component methods and validation
- **Integration Tests**: Fortify integration and middleware
- **Security Tests**: Rate limiting, encryption, session handling
- **Accessibility Tests**: WCAG AA compliance verification

### 1.10.2 Database Testing Strategy

**Real Database Interactions** (No Mocking):

```php
// Example test structure
uses(RefreshDatabase::class);

test('user can enable two factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/two-factor-authentication')
        ->assertRedirect();

    expect($user->fresh())
        ->hasEnabledTwoFactorAuthentication()
        ->toBeTrue();
});
```

### 1.10.3 Test Scenarios

**Critical Test Cases**:

1. **Setup Flow**: QR code generation, code verification, recovery codes
2. **Login Flow**: Standard login, 2FA challenge, recovery code usage
3. **Management**: Enable/disable 2FA, regenerate recovery codes
4. **Security**: Rate limiting, password confirmation, encryption
5. **Edge Cases**: Invalid codes, expired sessions, disabled users

## 1.11 Component Code Examples

### 1.11.1 Two-Factor Authentication Setup Component

**File**: `resources/views/livewire/two-factor-authentication.blade.php`
> Generated by: `php artisan make:volt --functional --pest Two-Factor-Authentication`

```php
<?php

declare(strict_types=1);

use function Livewire\Volt\{state, mount, computed};
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

state([
    'showingQrCode' => false,
    'showingConfirmation' => false,
    'showingRecoveryCodes' => false,
    'code' => '',
    'password' => '',
    'isEnabled' => false,
    'confirmingDisable' => false,
]);

mount(function () {
    $this->isEnabled = Auth::user()->hasEnabledTwoFactorAuthentication();
});

$twoFactorQrCodeSvg = computed(function () {
    return Auth::user()->twoFactorQrCodeSvg();
});

$recoveryCodes = computed(function () {
    return Auth::user()->recoveryCodes();
});

$enableTwoFactorAuthentication = function () {
    $this->ensurePasswordIsConfirmed();

    app(EnableTwoFactorAuthentication::class)(Auth::user());

    $this->showingQrCode = true;
    $this->showingConfirmation = true;
    $this->isEnabled = true;
};

$confirmTwoFactorAuthentication = function () {
    $this->validate([
        'code' => 'required|string',
    ]);

    if (! Auth::user()->confirmTwoFactorAuth($this->code)) {
        throw ValidationException::withMessages([
            'code' => ['The provided two factor authentication code was invalid.'],
        ]);
    }

    $this->showingQrCode = false;
    $this->showingConfirmation = false;
    $this->showingRecoveryCodes = true;
    $this->code = '';

    session()->flash('status', 'Two factor authentication has been enabled.');
};

$disableTwoFactorAuthentication = function () {
    $this->ensurePasswordIsConfirmed();

    app(DisableTwoFactorAuthentication::class)(Auth::user());

    $this->isEnabled = false;
    $this->showingQrCode = false;
    $this->showingConfirmation = false;
    $this->showingRecoveryCodes = false;
    $this->confirmingDisable = false;

    session()->flash('status', 'Two factor authentication has been disabled.');
};

$generateNewRecoveryCodes = function () {
    $this->ensurePasswordIsConfirmed();

    app(GenerateNewRecoveryCodes::class)(Auth::user());

    $this->showingRecoveryCodes = true;

    session()->flash('status', 'New recovery codes have been generated.');
};

$ensurePasswordIsConfirmed = function () {
    if (! session('auth.password_confirmed_at') ||
        time() - session('auth.password_confirmed_at') > config('auth.password_timeout', 10800)) {
        $this->redirect(route('password.confirm'));
    }
};

?>

<div class="space-y-6">
    <flux:heading size="lg">Two-Factor Authentication</flux:heading>

    <flux:subheading>
        Add additional security to your account using two-factor authentication.
    </flux:subheading>

    @if ($isEnabled && ! $showingConfirmation)
        <div class="space-y-4">
            <flux:badge color="green" size="sm">
                <flux:icon.check-circle class="size-4" />
                Two-factor authentication is enabled
            </flux:badge>

            <flux:card>
                <flux:card.header>
                    <flux:heading size="sm">Recovery Codes</flux:heading>
                </flux:card.header>

                <flux:card.body>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">
                        Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.
                    </p>

                    <div class="flex gap-2">
                        <flux:button wire:click="$set('showingRecoveryCodes', true)" variant="outline" size="sm">
                            Show Recovery Codes
                        </flux:button>

                        <flux:button wire:click="generateNewRecoveryCodes" variant="outline" size="sm">
                            Regenerate Recovery Codes
                        </flux:button>
                    </div>
                </flux:card.body>
            </flux:card>

            <flux:button wire:click="$set('confirmingDisable', true)" variant="danger" size="sm">
                Disable Two-Factor Authentication
            </flux:button>
        </div>
    @else
        <flux:card>
            <flux:card.header>
                <flux:heading size="sm">Enable Two-Factor Authentication</flux:heading>
            </flux:card.header>

            <flux:card.body class="space-y-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    When two-factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's Google Authenticator application.
                </p>

                @if (! $isEnabled)
                    <flux:button wire:click="enableTwoFactorAuthentication" variant="primary">
                        Enable Two-Factor Authentication
                    </flux:button>
                @endif
            </flux:card.body>
        </flux:card>
    @endif

    <!-- QR Code Display Modal -->
    <flux:modal wire:model="showingQrCode" variant="flyout">
        <flux:modal.header>
            <flux:heading size="lg">Finish enabling two-factor authentication</flux:heading>
        </flux:modal.header>

        <flux:modal.body class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                To finish enabling two-factor authentication, scan the following QR code using your phone's authenticator application or enter the setup key and provide the generated OTP code.
            </p>

            <div class="flex justify-center p-4 bg-white rounded-lg">
                {!! $this->twoFactorQrCodeSvg !!}
            </div>

            <flux:input
                wire:model="code"
                label="Authentication Code"
                placeholder="Enter the 6-digit code from your authenticator app"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                required
                autofocus
            />
        </flux:modal.body>

        <flux:modal.footer>
            <flux:button wire:click="confirmTwoFactorAuthentication" variant="primary">
                Confirm
            </flux:button>

            <flux:button wire:click="$set('showingQrCode', false)" variant="ghost">
                Cancel
            </flux:button>
        </flux:modal.footer>
    </flux:modal>

    <!-- Recovery Codes Display Modal -->
    <flux:modal wire:model="showingRecoveryCodes" variant="flyout">
        <flux:modal.header>
            <flux:heading size="lg">Two-Factor Authentication Recovery Codes</flux:heading>
        </flux:modal.header>

        <flux:modal.body class="space-y-4">
            <flux:badge color="amber" size="sm">
                <flux:icon.exclamation-triangle class="size-4" />
                Store these codes securely
            </flux:badge>

            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                These recovery codes can be used to access your account in the event you lose access to your two-factor authentication device. Store them in a secure password manager.
            </p>

            <div class="grid grid-cols-2 gap-2 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg font-mono text-sm">
                @foreach ($this->recoveryCodes as $code)
                    <div class="p-2 bg-white dark:bg-zinc-700 rounded border">
                        {{ $code }}
                    </div>
                @endforeach
            </div>
        </flux:modal.body>

        <flux:modal.footer>
            <flux:button wire:click="$set('showingRecoveryCodes', false)" variant="primary">
                Done
            </flux:button>
        </flux:modal.footer>
    </flux:modal>

    <!-- Disable Confirmation Modal -->
    <flux:modal wire:model="confirmingDisable" variant="flyout">
        <flux:modal.header>
            <flux:heading size="lg">Disable Two-Factor Authentication</flux:heading>
        </flux:modal.header>

        <flux:modal.body>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Are you sure you want to disable two-factor authentication? This will make your account less secure.
            </p>
        </flux:modal.body>

        <flux:modal.footer>
            <flux:button wire:click="disableTwoFactorAuthentication" variant="danger">
                Disable
            </flux:button>

            <flux:button wire:click="$set('confirmingDisable', false)" variant="ghost">
                Cancel
            </flux:button>
        </flux:modal.footer>
    </flux:modal>
</div>
```

### 1.11.2 Two-Factor Challenge Component

**File**: `resources/views/livewire/two-factor-challenge.blade.php`
> Generated by: `php artisan make:volt --functional --pest Two-Factor-Challenge`

```php
<?php

declare(strict_types=1);

use function Livewire\Volt\{state, mount};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;

state([
    'code' => '',
    'recovery_code' => '',
    'recovery' => false,
]);

$authenticate = function () {
    $this->ensureIsNotRateLimited();

    $code = $this->recovery ? $this->recovery_code : $this->code;

    if (empty($code)) {
        throw ValidationException::withMessages([
            $this->recovery ? 'recovery_code' : 'code' => ['This field is required.'],
        ]);
    }

    $user = session('login.id') ? Auth::getProvider()->retrieveById(session('login.id')) : null;

    if (! $user) {
        throw ValidationException::withMessages([
            'code' => ['Authentication session expired. Please log in again.'],
        ]);
    }

    if ($this->recovery) {
        if (! $user->replaceRecoveryCode($code)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'recovery_code' => ['The provided recovery code was invalid.'],
            ]);
        }
    } else {
        if (! $user->confirmTwoFactorAuth($code)) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }
    }

    Auth::login($user, session('login.remember', false));

    session()->forget(['login.id', 'login.remember']);

    RateLimiter::clear($this->throttleKey());

    $this->redirect(session('url.intended', route('dashboard')));
};

$ensureIsNotRateLimited = function () {
    if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
        return;
    }

    $seconds = RateLimiter::availableIn($this->throttleKey());

    throw ValidationException::withMessages([
        'code' => ["Too many authentication attempts. Please try again in {$seconds} seconds."],
    ]);
};

$throttleKey = function () {
    return 'two-factor-challenge:' . request()->ip();
};

?>

<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
    <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
        <flux:heading size="lg" class="mb-4">Two-Factor Authentication</flux:heading>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            @if ($recovery)
                Please confirm access to your account by entering one of your emergency recovery codes.
            @else
                Please confirm access to your account by entering the authentication code provided by your authenticator application.
            @endif
        </div>

        <form wire:submit="authenticate" class="space-y-6">
            @if ($recovery)
                <flux:input
                    wire:model="recovery_code"
                    label="Recovery Code"
                    type="text"
                    placeholder="Enter recovery code"
                    required
                    autofocus
                    autocomplete="one-time-code"
                />
            @else
                <flux:input
                    wire:model="code"
                    label="Authentication Code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    placeholder="000000"
                    required
                    autofocus
                    autocomplete="one-time-code"
                />
            @endif

            <div class="flex items-center justify-between">
                <flux:button
                    type="button"
                    wire:click="$toggle('recovery')"
                    variant="ghost"
                    size="sm"
                >
                    @if ($recovery)
                        Use authentication code
                    @else
                        Use recovery code
                    @endif
                </flux:button>

                <flux:button type="submit" variant="primary">
                    Verify
                </flux:button>
            </div>
        </form>
    </div>
</div>
```

### 1.11.3 Settings Integration

**File**: `resources/views/livewire/settings/two-factor.blade.php` (Volt functional component)

```php
<?php

declare(strict_types=1);

use function Livewire\Volt\{layout};

layout('components.layouts.app');

?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <livewire:two-factor-authentication />
            </div>
        </div>
    </div>
</div>
```

## 1.12 Controller Implementation

### 1.12.1 TwoFactorAuthenticationController

**File**: `app/Http/Controllers/TwoFactorAuthenticationController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class TwoFactorAuthenticationController extends Controller
{
    /**
     * Enable two-factor authentication for the user.
     */
    public function store(Request $request): Response
    {
        app(EnableTwoFactorAuthentication::class)($request->user());

        return response()->noContent();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirm(Request $request): Response
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        if (! $request->user()->confirmTwoFactorAuth($request->code)) {
            return response()->json([
                'message' => 'The provided two factor authentication code was invalid.',
            ], 422);
        }

        return response()->noContent();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function destroy(Request $request): Response
    {
        app(DisableTwoFactorAuthentication::class)($request->user());

        return response()->noContent();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function recoveryCodes(Request $request): Response
    {
        app(GenerateNewRecoveryCodes::class)($request->user());

        return response()->noContent();
    }

    /**
     * Get the SVG element for the user's two factor authentication QR code.
     */
    public function qrCode(Request $request): Response
    {
        return response($request->user()->twoFactorQrCodeSvg(), 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
```

## 1.13 Form Request Validation

### 1.13.1 EnableTwoFactorRequest

**File**: `app/Http/Requests/EnableTwoFactorRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnableTwoFactorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'password.current_password' => 'The provided password does not match your current password.',
        ];
    }
}
```

### 1.13.2 ConfirmTwoFactorRequest

**File**: `app/Http/Requests/ConfirmTwoFactorRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTwoFactorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null &&
               $this->user()->two_factor_secret !== null &&
               $this->user()->two_factor_confirmed_at === null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Please enter the authentication code.',
            'code.size' => 'The authentication code must be 6 digits.',
            'code.regex' => 'The authentication code must contain only numbers.',
        ];
    }
}
```

## 1.14 Comprehensive Testing Implementation

### 1.14.1 Feature Tests

**File**: `tests/Feature/TwoFactorAuthenticationTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

uses(RefreshDatabase::class);

test('user can enable two factor authentication', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/two-factor-authentication')
        ->assertStatus(204);

    expect($user->fresh())
        ->two_factor_secret->not->toBeNull()
        ->two_factor_recovery_codes->not->toBeNull()
        ->two_factor_confirmed_at->toBeNull();
});

test('user can confirm two factor authentication with valid code', function () {
    $user = User::factory()->create();

    // Enable 2FA first
    app(EnableTwoFactorAuthentication::class)($user);

    // Generate a valid TOTP code
    $code = app('pragmarx.google2fa')->getCurrentOtp(
        decrypt($user->two_factor_secret)
    );

    $this->actingAs($user)
        ->post('/two-factor-authentication/confirm', [
            'code' => $code,
        ])
        ->assertStatus(204);

    expect($user->fresh())
        ->two_factor_confirmed_at->not->toBeNull()
        ->hasEnabledTwoFactorAuthentication()->toBeTrue();
});

test('user cannot confirm two factor authentication with invalid code', function () {
    $user = User::factory()->create();

    app(EnableTwoFactorAuthentication::class)($user);

    $this->actingAs($user)
        ->post('/two-factor-authentication/confirm', [
            'code' => '123456',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    expect($user->fresh())
        ->two_factor_confirmed_at->toBeNull();
});

test('user can disable two factor authentication', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($user)
        ->delete('/two-factor-authentication')
        ->assertStatus(204);

    expect($user->fresh())
        ->two_factor_secret->toBeNull()
        ->two_factor_recovery_codes->toBeNull()
        ->two_factor_confirmed_at->toBeNull()
        ->hasEnabledTwoFactorAuthentication()->toBeFalse();
});

test('user can generate new recovery codes', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $originalCodes = $user->recoveryCodes();

    $this->actingAs($user)
        ->post('/two-factor-authentication/recovery-codes')
        ->assertStatus(204);

    $newCodes = $user->fresh()->recoveryCodes();

    expect($newCodes)
        ->not->toEqual($originalCodes)
        ->toHaveCount(8);
});
```

### 1.14.2 Two-Factor Challenge Tests

**File**: `tests/Feature/TwoFactorChallengeTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

uses(RefreshDatabase::class);

test('user with two factor enabled is redirected to challenge', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/two-factor-challenge');

    expect(Auth::check())->toBeFalse();
    expect(session('login.id'))->toBe($user->id);
});

test('user can complete two factor challenge with valid code', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    // Start login process
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Generate valid TOTP code
    $code = app('pragmarx.google2fa')->getCurrentOtp(
        decrypt($user->two_factor_secret)
    );

    $this->post('/two-factor-challenge', [
        'code' => $code,
    ])->assertRedirect('/dashboard');

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);
});

test('user can complete two factor challenge with recovery code', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $recoveryCodes = $user->recoveryCodes();

    // Start login process
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post('/two-factor-challenge', [
        'recovery_code' => $recoveryCodes[0],
    ])->assertRedirect('/dashboard');

    expect(Auth::check())->toBeTrue();
    expect(Auth::id())->toBe($user->id);

    // Verify recovery code was consumed
    expect($user->fresh()->recoveryCodes())
        ->not->toContain($recoveryCodes[0])
        ->toHaveCount(7);
});

test('user cannot complete challenge with invalid code', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    // Start login process
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post('/two-factor-challenge', [
        'code' => '000000',
    ])->assertSessionHasErrors(['code']);

    expect(Auth::check())->toBeFalse();
});

test('two factor challenge is rate limited', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    // Start login process
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Make 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $this->post('/two-factor-challenge', [
            'code' => '000000',
        ]);
    }

    // 6th attempt should be rate limited
    $this->post('/two-factor-challenge', [
        'code' => '000000',
    ])->assertSessionHasErrors(['code' => 'Too many authentication attempts']);
});
```

### 1.14.3 Component Tests

**File**: `tests/Feature/TwoFactorComponentTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('two factor authentication component renders correctly', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/two-factor')
        ->assertOk()
        ->assertSeeVolt('two-factor-authentication');
});

test('component shows enable button when 2fa is disabled', function () {
    $user = User::factory()->create();

    Volt::test('two-factor-authentication')
        ->actingAs($user)
        ->assertSee('Enable Two-Factor Authentication')
        ->assertDontSee('Two-factor authentication is enabled');
});

test('component shows enabled status when 2fa is active', function () {
    $user = User::factory()->create();

    // Enable and confirm 2FA
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    Volt::test('two-factor-authentication')
        ->actingAs($user)
        ->assertSee('Two-factor authentication is enabled')
        ->assertSee('Recovery Codes')
        ->assertSee('Disable Two-Factor Authentication');
});

test('component can enable two factor authentication', function () {
    $user = User::factory()->create();

    Volt::test('two-factor-authentication')
        ->actingAs($user)
        ->call('enableTwoFactorAuthentication')
        ->assertSet('showingQrCode', true)
        ->assertSet('showingConfirmation', true)
        ->assertSet('isEnabled', true);

    expect($user->fresh())
        ->two_factor_secret->not->toBeNull();
});

test('component can confirm two factor authentication', function () {
    $user = User::factory()->create();

    // Enable 2FA first
    app(EnableTwoFactorAuthentication::class)($user);

    // Generate valid code
    $code = app('pragmarx.google2fa')->getCurrentOtp(
        decrypt($user->two_factor_secret)
    );

    Volt::test('two-factor-authentication')
        ->actingAs($user)
        ->set('code', $code)
        ->call('confirmTwoFactorAuthentication')
        ->assertSet('showingQrCode', false)
        ->assertSet('showingConfirmation', false)
        ->assertSet('showingRecoveryCodes', true)
        ->assertSessionHas('status', 'Two factor authentication has been enabled.');

    expect($user->fresh())
        ->two_factor_confirmed_at->not->toBeNull();
});
```

## 1.15 Security Implementation & Best Practices

### 1.15.1 Encryption and Data Protection

**Secret Storage Security**:

```php
// User model - Fortify handles encryption automatically
protected $hidden = [
    'password',
    'remember_token',
    'two_factor_secret',          // Always encrypted
    'two_factor_recovery_codes',  // Always encrypted
];

// Additional security in User model
public function getTwoFactorSecretAttribute($value)
{
    return $value ? decrypt($value) : null;
}

public function setTwoFactorSecretAttribute($value)
{
    $this->attributes['two_factor_secret'] = $value ? encrypt($value) : null;
}
```

### 1.15.2 Rate Limiting Configuration

**Enhanced Rate Limiting** in `FortifyServiceProvider`:

```php
// app/Providers/FortifyServiceProvider.php
public function boot(): void
{
    // Existing rate limiters...

    // Enhanced 2FA rate limiting
    RateLimiter::for('two-factor', function (Request $request) {
        return [
            Limit::perMinute(5)->by($request->session()->get('login.id')),
            Limit::perHour(20)->by($request->ip()),
        ];
    });

    // Recovery code rate limiting
    RateLimiter::for('two-factor-recovery', function (Request $request) {
        return [
            Limit::perMinute(3)->by($request->session()->get('login.id')),
            Limit::perHour(10)->by($request->ip()),
        ];
    });
}
```

### 1.15.3 Audit Logging Integration

**Activity Logging** for 2FA events:

```php
// In User model - extend existing LogsActivity
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly([
            'name', 'email', 'two_factor_confirmed_at'
        ])
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
            'updated' => $this->wasChanged('two_factor_confirmed_at')
                ? ($this->two_factor_confirmed_at ? '2FA enabled' : '2FA disabled')
                : 'Profile updated',
            default => $eventName,
        });
}
```

### 1.15.4 Session Security

**Enhanced Session Management**:

```php
// In TwoFactorChallenge component
$authenticate = function () {
    // ... existing code ...

    // Regenerate session after successful 2FA
    session()->regenerate();

    // Clear sensitive session data
    session()->forget(['login.id', 'login.remember']);

    // Log successful 2FA authentication
    activity()
        ->causedBy($user)
        ->log('Two-factor authentication successful');

    $this->redirect(session('url.intended', route('dashboard')));
};
```

## 1.16 Deployment Procedures

### 1.16.1 Pre-Deployment Checklist

**🔍 Verification Steps**:

- [ ] All tests passing (`php artisan test`)
- [ ] Database migrations ready (`php artisan migrate:status`)
- [ ] Configuration files updated
- [ ] Rate limiting configured
- [ ] Audit logging enabled
- [ ] Accessibility compliance verified

**Pre-Deployment Commands**:

```bash
# 1. Run full test suite
php artisan test --coverage

# 2. Verify database schema
php artisan migrate:status
php artisan tinker
>>> Schema::hasColumn('users', 'two_factor_secret')

# 3. Clear and cache configuration
php artisan config:clear
php artisan config:cache
php artisan route:cache

# 4. Verify Fortify routes
php artisan route:list | grep two-factor
```

### 1.16.2 Deployment Steps

**Sequential Deployment Process**:

```bash
# Step 1: Backup current state
cp database/database.sqlite database/database.sqlite.backup.$(date +%Y%m%d_%H%M%S)

# Step 2: Deploy code changes
git pull origin main

# Step 3: Install/update dependencies
composer install --no-dev --optimize-autoloader

# Step 4: Run migrations (if any new ones)
php artisan migrate --force

# Step 5: Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Step 6: Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 7: Verify deployment
php artisan route:list | grep two-factor
```

### 1.16.3 Post-Deployment Validation

**Verification Checklist**:

```bash
# Test 2FA routes are accessible
curl -I https://yourapp.com/two-factor-challenge

# Verify database schema
php artisan tinker
>>> User::first()->hasEnabledTwoFactorAuthentication()

# Test component rendering
php artisan test tests/Feature/TwoFactorComponentTest.php

# Verify rate limiting
php artisan tinker
>>> RateLimiter::for('two-factor', function() { return true; })
```

## 1.17 Troubleshooting Guide

### 1.17.1 Common Issues and Solutions

**Issue**: QR Code not displaying

```bash
# Solution: Verify Fortify configuration
php artisan config:clear
php artisan tinker
>>> config('fortify.features')
>>> User::first()->twoFactorQrCodeSvg()
```

**Issue**: Two-factor challenge not triggered

```bash
# Solution: Check user 2FA status
php artisan tinker
>>> $user = User::find(1)
>>> $user->hasEnabledTwoFactorAuthentication()
>>> $user->two_factor_confirmed_at
```

**Issue**: Recovery codes not working

```bash
# Solution: Verify recovery codes format
php artisan tinker
>>> $user = User::first()
>>> $user->recoveryCodes()
>>> count($user->recoveryCodes())
```

### 1.17.2 Debug Commands

**Debugging Tools**:

```bash
# Check Fortify configuration
php artisan config:show fortify

# Verify 2FA routes
php artisan route:list --name=two-factor

# Test rate limiting
php artisan tinker
>>> RateLimiter::hit('two-factor-challenge:127.0.0.1')
>>> RateLimiter::tooManyAttempts('two-factor-challenge:127.0.0.1', 5)

# Check user 2FA data
php artisan tinker
>>> User::whereNotNull('two_factor_secret')->count()
>>> User::whereNotNull('two_factor_confirmed_at')->count()
```

### 1.17.3 Performance Monitoring

**Key Metrics to Monitor**:

- 2FA setup completion rate
- Authentication failure rates
- Recovery code usage frequency
- Rate limiting trigger frequency
- Database query performance for 2FA operations

## 1.18 Maintenance and Updates

### 1.18.1 Regular Maintenance Tasks

**Monthly Tasks**:

- Review 2FA adoption rates
- Analyze authentication failure patterns
- Update recovery code generation if needed
- Review and update security configurations

**Quarterly Tasks**:

- Security audit of 2FA implementation
- Performance optimization review
- Update dependencies (Fortify, Google2FA)
- Review and update documentation

### 1.18.2 Future Enhancements

**Potential Improvements**:

- WebAuthn/FIDO2 support
- SMS backup authentication
- Trusted device management
- Advanced threat detection
- Multi-device 2FA management

## 1.19 Success Criteria and Validation

### 1.19.1 Implementation Success Metrics

**✅ Technical Success Criteria**:

- [ ] All tests passing (95%+ coverage)
- [ ] Zero breaking changes to existing authentication
- [ ] WCAG AA accessibility compliance
- [ ] Sub-200ms response times for 2FA operations
- [ ] Proper error handling and user feedback

**✅ User Experience Success Criteria**:

- [ ] Intuitive 2FA setup process (< 2 minutes)
- [ ] Clear recovery code management
- [ ] Accessible interface for all users
- [ ] Comprehensive help documentation
- [ ] Seamless integration with existing UI

### 1.19.2 Final Validation Checklist

**🔍 Pre-Production Validation**:

- [ ] Complete test suite execution
- [ ] Manual testing of all user flows
- [ ] Accessibility audit completion
- [ ] Security review and penetration testing
- [ ] Performance benchmarking
- [ ] Documentation review and updates
- [ ] Stakeholder approval and sign-off

---

**Navigation Footer**:
← [Previous: Testing Implementation](030-laravel-fortify.md#testing-implementation) | [Next: Index →](.ai/010-docs/020-2fa-implementation/)
