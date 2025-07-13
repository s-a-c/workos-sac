# 4.0. 2FA Troubleshooting Guide

## 4.1. Overview

This comprehensive troubleshooting guide addresses common issues encountered during 2FA implementation and provides step-by-step solutions for resolving them.

## 4.2. Common Issues & Solutions

### 4.2.1. 🔴 2FA Registration Screens Not Appearing

**Symptoms**:
- No 2FA option in admin panel
- 2FA page returns 404 error
- QR code not displaying

**Root Causes**:
- Filament beta version instability
- Missing route registration
- Incorrect panel configuration

**Solutions**:

**Solution 1: Verify Filament Version**
```bash
# Check current Filament version
composer show filament/filament

# If using beta version, consider downgrading
composer require filament/filament:^3.2
```

**Solution 2: Clear All Caches**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear
```

**Solution 3: Verify Panel Configuration**
```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        ->profile() // Ensure profile is enabled
        // ... other configuration
}
```

### 4.2.2. 🟡 QR Code Generation Failures

**Symptoms**:
- Blank space where QR code should appear
- "Class not found" errors
- QR code displays as broken image

**Root Causes**:
- Missing QR code package
- Incorrect view configuration
- PHP GD extension issues

**Solutions**:

**Solution 1: Install QR Code Package**
```bash
composer require chillerlan/php-qrcode
composer require pragmarx/google2fa-qrcode
```

**Solution 2: Verify PHP Extensions**
```bash
php -m | grep -i gd
php -m | grep -i imagick

# If missing, install GD extension
# Ubuntu/Debian: sudo apt-get install php8.4-gd
# macOS: brew install php@8.4 --with-gd
```

**Solution 3: Test QR Code Generation**
```php
// Test in tinker
php artisan tinker
>>> $qr = new \chillerlan\QRCode\QRCode();
>>> $qr->render('test');
```

### 4.2.3. 🟡 Invalid Authentication Codes

**Symptoms**:
- Valid codes rejected as invalid
- "Invalid OTP" error messages
- Codes work intermittently

**Root Causes**:
- Server time synchronization issues
- Incorrect secret key handling
- Code window configuration problems

**Solutions**:

**Solution 1: Check Server Time**
```bash
# Verify server time is accurate
date
timedatectl status

# Sync time if needed
sudo ntpdate -s time.nist.gov
```

**Solution 2: Adjust Code Window**
```php
// config/google2fa.php
'window' => 4, // Increase window for clock skew tolerance
```

**Solution 3: Verify Secret Key**
```php
// Test secret key in tinker
php artisan tinker
>>> $user = User::first();
>>> $secret = decrypt($user->app_authentication_secret);
>>> $google2fa = new \PragmaRX\Google2FA\Google2FA();
>>> $google2fa->getCurrentOtp($secret);
```

### 4.2.4. 🟡 Database Encryption Issues

**Symptoms**:
- "Unable to decrypt" errors
- Secrets not saving properly
- Database constraint violations

**Root Causes**:
- Missing APP_KEY
- Incorrect column types
- Encryption configuration issues

**Solutions**:

**Solution 1: Verify Application Key**
```bash
# Check if APP_KEY exists
grep APP_KEY .env

# Generate new key if missing
php artisan key:generate
```

**Solution 2: Check Database Schema**
```sql
-- Verify column types
DESCRIBE users;

-- app_authentication_secret should be TEXT or LONGTEXT
-- app_authentication_recovery_codes should be TEXT or LONGTEXT
```

**Solution 3: Test Encryption**
```php
php artisan tinker
>>> encrypt('test_secret');
>>> decrypt(encrypt('test_secret'));
```

## 4.3. Advanced Troubleshooting

### 4.3.1. Debug Mode Diagnostics

**Enable Debug Mode**:
```bash
# .env
APP_DEBUG=true
LOG_LEVEL=debug
```

**Check Logs**:
```bash
tail -f storage/logs/laravel.log
```

### 4.3.2. Database Debugging

**Check 2FA Data**:
```sql
-- View encrypted secrets (should not be readable)
SELECT id, email, app_authentication_secret, has_email_authentication 
FROM users 
WHERE app_authentication_secret IS NOT NULL;

-- Check for null values
SELECT COUNT(*) as users_with_2fa 
FROM users 
WHERE app_authentication_secret IS NOT NULL;
```

### 4.3.3. Session Debugging

**Check Session Configuration**:
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'file'),
'lifetime' => env('SESSION_LIFETIME', 120),
'encrypt' => false, // Should be false for 2FA sessions
```

**Test Session Storage**:
```bash
# Check session files
ls -la storage/framework/sessions/

# Or check database sessions
SELECT * FROM sessions WHERE user_id IS NOT NULL;
```

## 4.4. Performance Issues

### 4.4.1. Slow QR Code Generation

**Symptoms**:
- QR code takes >5 seconds to load
- Page timeouts during 2FA setup

**Solutions**:
```php
// Optimize QR code generation
$qrCode = new \chillerlan\QRCode\QRCode([
    'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
    'eccLevel' => \chillerlan\QRCode\QRCode::ECC_L, // Lower error correction
    'scale' => 4, // Smaller scale
]);
```

### 4.4.2. Database Performance

**Optimize Queries**:
```php
// Add indexes for 2FA columns
Schema::table('users', function (Blueprint $table) {
    $table->index('app_authentication_secret');
});
```

## 4.5. Security Concerns

### 4.5.1. Secret Key Exposure

**Check for Exposed Secrets**:
```bash
# Search for unencrypted secrets in logs
grep -r "google2fa" storage/logs/
grep -r "secret" storage/logs/
```

**Secure Secret Handling**:
```php
// Always encrypt secrets
$user->app_authentication_secret = encrypt($secret);

// Never log secrets
Log::info('2FA enabled for user', ['user_id' => $user->id]); // Good
Log::info('2FA secret: ' . $secret); // BAD - Never do this
```

### 4.5.2. Recovery Code Security

**Verify Recovery Code Encryption**:
```php
php artisan tinker
>>> $user = User::first();
>>> $codes = $user->app_authentication_recovery_codes;
>>> // Should be array of hashed codes, not plain text
```

## 4.6. Integration Issues

### 4.6.1. Filament Panel Conflicts

**Multiple Panel Setup**:
```php
// Ensure 2FA is configured for correct panel
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin') // Verify correct panel ID
        ->path('admin')
        // ... 2FA configuration
}
```

### 4.6.2. Middleware Conflicts

**Check Middleware Order**:
```php
// Ensure 2FA middleware is in correct position
->authMiddleware([
    Authenticate::class,
    // 2FA middleware should come after authentication
])
```

## 4.7. Emergency Procedures

### 4.7.1. Disable 2FA for User

**Via Database**:
```sql
-- Disable 2FA for specific user
UPDATE users 
SET app_authentication_secret = NULL, 
    app_authentication_recovery_codes = NULL,
    has_email_authentication = 0
WHERE email = 'user@example.com';
```

**Via Artisan Command**:
```php
// Create custom command
php artisan make:command Disable2FA

// In command handle method:
$user = User::where('email', $this->argument('email'))->first();
$user->app_authentication_secret = null;
$user->app_authentication_recovery_codes = null;
$user->has_email_authentication = false;
$user->save();
```

### 4.7.2. Reset All 2FA

**Emergency Reset**:
```sql
-- CAUTION: This disables 2FA for ALL users
UPDATE users 
SET app_authentication_secret = NULL, 
    app_authentication_recovery_codes = NULL,
    has_email_authentication = 0;
```

## 4.8. Getting Help

### 4.8.1. Information to Collect

Before seeking help, collect:
- Laravel version: `php artisan --version`
- Filament version: `composer show filament/filament`
- PHP version: `php --version`
- Error logs: `tail -50 storage/logs/laravel.log`
- Database schema: `DESCRIBE users;`

### 4.8.2. Support Resources

- **Filament Documentation**: https://filamentphp.com/docs
- **Google2FA Package**: https://github.com/antonioribeiro/google2fa
- **Laravel Documentation**: https://laravel.com/docs
- **Community Forums**: Filament Discord, Laravel Forums

---

**Navigation Footer**

← [Previous: Verification Checklist](030-verification-checklist.md) | [Next: Project Summary →](../README.md)

---

*Document Version: 1.0 | Last Updated: 2025-07-01 | Status: Complete*
