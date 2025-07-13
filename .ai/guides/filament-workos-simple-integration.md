# Simple FilamentPHP + WorkOS Integration Guide

**A minimal guide for making FilamentPHP work with existing Laravel WorkOS authentication**

---

## Overview

This guide shows how to configure FilamentPHP to work with an existing Laravel WorkOS implementation. **No custom services, middleware, or complex integrations needed** - just simple configuration changes to make Filament use your existing WorkOS authentication.

## Prerequisites

You should already have:
- ✅ Laravel application with WorkOS package installed (`laravel/workos`)
- ✅ WorkOS authentication routes working (`routes/auth.php`)
- ✅ User model with `workos_id` field
- ✅ WorkOS environment variables configured

**Source**: [Laravel WorkOS Package](https://github.com/laravel/workos)

---

## The Problem

By default, FilamentPHP tries to handle authentication independently. With WorkOS, you want Filament to:
1. Redirect unauthenticated users to WorkOS SSO
2. Use the existing Laravel authentication session
3. Validate WorkOS sessions properly

---

## The Solution (2 Simple Steps)

### Step 1: Configure Filament Panel Provider

Update your `app/Providers/Filament/AdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Remove custom login page - use default Filament auth
            ->middleware([
                // Standard Laravel middleware
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                AuthenticateSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,

                // FilamentPHP middleware
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,

                // WorkOS session validation
                ValidateSessionWithWorkOS::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

**Key Changes**:
- ✅ Added `ValidateSessionWithWorkOS::class` middleware
- ✅ Removed custom login page configuration
- ✅ Uses standard Filament authentication flow

### Step 2: Remove Custom Login Page (Optional)

If you have a custom login page at `app/Filament/Pages/Auth/Login.php`, you can either:

**Option A: Delete it** (recommended for simplicity)
```bash
rm app/Filament/Pages/Auth/Login.php
```

**Option B: Fix the redirect** (if you want to keep it)
```php
<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function mount(): void
    {
        // Redirect to Laravel's login route (which goes to WorkOS)
        return redirect()->route('login');
    }
}
```

---

## How It Works

1. **User visits `/admin`** → Filament checks authentication
2. **User not authenticated** → Filament redirects to `/login` 
3. **Laravel login route** → WorkOS handles SSO authentication
4. **WorkOS callback** → User authenticated and redirected to existing `/dashboard` route
5. **User can then navigate to `/admin`** → Already authenticated, Filament grants access
6. **Subsequent requests** → `ValidateSessionWithWorkOS` middleware validates WorkOS session

## Why No Route Changes Needed

**You don't need to modify `routes/auth.php`** because:

- ✅ Your existing `/dashboard` route should remain unchanged
- ✅ WorkOS authentication works independently of Filament
- ✅ Once authenticated via WorkOS, users can access `/admin` directly
- ✅ The `ValidateSessionWithWorkOS` middleware ensures session validity

**The beauty of this approach**: Your existing application flow remains intact, and Filament simply leverages the existing WorkOS authentication session.

---

## That's It! 

No custom services, no complex user synchronization, no custom guards needed. FilamentPHP now works with your existing WorkOS authentication.

## Testing

1. **Logout** from your application
2. **Visit** `/admin`
3. **Should redirect** to WorkOS SSO
4. **After authentication** should land on your existing `/dashboard` page
5. **Navigate to** `/admin` - should work without re-authentication
6. **Subsequent visits** to `/admin` should work directly

---

## Troubleshooting

### Issue: Redirect Loop
**Cause**: Custom login page redirecting incorrectly
**Solution**: Remove custom login page or fix redirect to `route('login')`

### Issue: Session Not Valid
**Cause**: Missing `ValidateSessionWithWorkOS` middleware
**Solution**: Ensure middleware is added to panel configuration

### Issue: 403 Access Denied
**Cause**: User doesn't have required permissions
**Solution**: Check if user needs specific roles/permissions for Filament access

---

## Sources

- [Laravel WorkOS Package](https://github.com/laravel/workos)
- [FilamentPHP Panel Configuration](https://filamentphp.com/docs/3.x/panels/configuration)
- [FilamentPHP Authentication](https://filamentphp.com/docs/3.x/panels/users)

---

**Last Updated**: January 2025  
**Compatibility**: Laravel 12.x, FilamentPHP 3.x, WorkOS 4.x
