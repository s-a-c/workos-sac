# Security Hardening Guide

## Overview

This guide covers comprehensive security hardening for the Chinook Filament admin panel in production environments, including server security, application security, and monitoring strategies.

## Table of Contents

- [Overview](#overview)
- [Server Security](#server-security)
- [Application Security](#application-security)
- [Database Security](#database-security)
- [File System Security](#file-system-security)
- [Network Security](#network-security)
- [Authentication Security](#authentication-security)
- [Session Security](#session-security)
- [CSRF Protection](#csrf-protection)
- [Input Validation](#input-validation)
- [Security Headers](#security-headers)
- [Monitoring and Logging](#monitoring-and-logging)
- [Security Auditing](#security-auditing)
- [Best Practices](#best-practices)

## Server Security

### Operating System Hardening

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install security updates automatically
sudo apt install unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades

# Configure firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable

# Disable root login
sudo sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sudo systemctl restart ssh

# Configure fail2ban
sudo apt install fail2ban
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local
```

### PHP Security Configuration

```ini
; php.ini security settings
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; File upload security
file_uploads = On
upload_max_filesize = 10M
max_file_uploads = 20
upload_tmp_dir = /tmp

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.cookie_samesite = "Strict"

; Memory and execution limits
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
```

## Application Security

### Environment Configuration

```bash
# .env production settings
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-32-character-secret-key

# Database security
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chinook_prod
DB_USERNAME=chinook_user
DB_PASSWORD=strong-random-password

# Session security
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# Cache security
CACHE_DRIVER=redis
REDIS_PASSWORD=strong-redis-password

# Mail security
MAIL_ENCRYPTION=tls
```

### Laravel Security Configuration

```php
<?php

// config/app.php
return [
    'debug' => false,
    'env' => 'production',
    
    // Security headers
    'security_headers' => [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ],
];

// config/session.php
return [
    'lifetime' => 120,
    'expire_on_close' => true,
    'encrypt' => true,
    'http_only' => true,
    'same_site' => 'strict',
    'secure' => true,
];
```

### Filament Security Configuration

```php
<?php

// config/filament.php
return [
    'auth' => [
        'guard' => 'web',
        'pages' => [
            'login' => \App\Filament\Pages\Auth\Login::class,
        ],
    ],
    
    'middleware' => [
        'auth' => [
            \App\Http\Middleware\Authenticate::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\RateLimitMiddleware::class,
        ],
        'base' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ],
    
    'pages' => [
        'namespace' => 'App\\Filament\\Pages',
        'path' => app_path('Filament/Pages'),
    ],
];
```

## Database Security

### Database User Permissions

```sql
-- Create dedicated database user
CREATE USER 'chinook_user'@'localhost' IDENTIFIED BY 'strong-random-password';

-- Grant minimal required permissions
GRANT SELECT, INSERT, UPDATE, DELETE ON chinook_prod.* TO 'chinook_user'@'localhost';

-- Remove dangerous permissions
REVOKE FILE, PROCESS, SUPER ON *.* FROM 'chinook_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;
```

### Database Configuration

```ini
# my.cnf security settings
[mysqld]
# Disable remote root login
bind-address = 127.0.0.1

# Enable SSL
ssl-ca = /etc/mysql/ssl/ca-cert.pem
ssl-cert = /etc/mysql/ssl/server-cert.pem
ssl-key = /etc/mysql/ssl/server-key.pem

# Security settings
local-infile = 0
skip-show-database
safe-user-create = 1

# Logging
log-error = /var/log/mysql/error.log
general-log = 1
general-log-file = /var/log/mysql/general.log
```

## File System Security

### File Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/chinook
sudo chown -R root:root /var/www/chinook/vendor

# Set secure permissions
find /var/www/chinook -type f -exec chmod 644 {} \;
find /var/www/chinook -type d -exec chmod 755 {} \;

# Secure sensitive directories
chmod 600 /var/www/chinook/.env
chmod -R 600 /var/www/chinook/storage/logs
chmod -R 755 /var/www/chinook/storage/app/public
chmod -R 755 /var/www/chinook/bootstrap/cache
```

### Storage Security

```php
<?php

// config/filesystems.php
return [
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],
        
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],
        
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],
    ],
];
```

## Network Security

### Web Server Configuration

```nginx
# nginx security configuration
server {
    listen 443 ssl http2;
    server_name admin.chinook.com;
    
    # SSL configuration
    ssl_certificate /etc/ssl/certs/chinook.crt;
    ssl_certificate_key /etc/ssl/private/chinook.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" always;
    
    # Hide server information
    server_tokens off;
    
    # Rate limiting
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location /admin/login {
        limit_req zone=login burst=3 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(vendor|storage|bootstrap|config|database|resources|routes|tests)/ {
        deny all;
    }
}
```

## Authentication Security

### Multi-Factor Authentication

```php
<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getTwoFactorFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getTwoFactorFormComponent(): TextInput
    {
        return TextInput::make('two_factor_code')
            ->label('Two-Factor Code')
            ->numeric()
            ->length(6)
            ->required()
            ->autocomplete('one-time-code');
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $credentials = parent::getCredentialsFromFormData($data);
        
        // Verify 2FA code
        if (!$this->verifyTwoFactorCode($data['email'], $data['two_factor_code'])) {
            throw ValidationException::withMessages([
                'two_factor_code' => 'Invalid two-factor authentication code.',
            ]);
        }
        
        return $credentials;
    }

    private function verifyTwoFactorCode(string $email, string $code): bool
    {
        // Implement 2FA verification logic
        return app('two-factor')->verify($email, $code);
    }
}
```

## Session Security

### Session Configuration

```php
<?php
// config/session.php

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'expire_on_close' => true,
    'encrypt' => true,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'strict',
    'partitioned' => false,
];
```

### Session Security Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SecureSession
{
    public function handle(Request $request, Closure $next)
    {
        // Regenerate session ID on login
        if ($request->user() && !Session::has('user_authenticated')) {
            Session::regenerate();
            Session::put('user_authenticated', true);
        }

        // Check for session hijacking
        if (Session::has('last_ip') && Session::get('last_ip') !== $request->ip()) {
            Session::flush();
            return redirect()->route('login')->with('error', 'Session security violation detected.');
        }

        Session::put('last_ip', $request->ip());
        Session::put('last_activity', now());

        return $next($request);
    }
}
```

## CSRF Protection

### CSRF Configuration

```php
<?php
// config/csrf.php

return [
    'enabled' => true,
    'token_lifetime' => 3600, // 1 hour
    'regenerate_on_login' => true,
    'exclude_routes' => [
        'api/*',
        'webhooks/*',
    ],
];
```

### Enhanced CSRF Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    protected $except = [
        'api/*',
        'webhooks/*',
    ];

    public function handle($request, Closure $next)
    {
        // Additional CSRF validation for admin panel
        if ($request->is('admin/*') && $request->isMethod('post')) {
            $this->validateAdminCsrf($request);
        }

        return parent::handle($request, $next);
    }

    protected function validateAdminCsrf(Request $request)
    {
        $token = $request->header('X-CSRF-TOKEN') ?: $request->input('_token');

        if (!hash_equals(session()->token(), $token)) {
            abort(419, 'CSRF token mismatch');
        }
    }
}
```

## Input Validation

### Request Validation Rules

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SecureFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'password' => [
                'required',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
            'phone' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
        ];
    }

    protected function prepareForValidation()
    {
        // Sanitize input data
        $this->merge([
            'email' => strtolower(trim($this->email)),
            'name' => strip_tags(trim($this->name)),
            'phone' => preg_replace('/[^+\d]/', '', $this->phone),
        ]);
    }
}
```

### Input Sanitization Service

```php
<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class InputSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,b,strong,i,em,u,a[href],ul,ol,li');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);

        $this->purifier = new HTMLPurifier($config);
    }

    public function sanitizeHtml(string $input): string
    {
        return $this->purifier->purify($input);
    }

    public function sanitizeString(string $input): string
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public function sanitizeArray(array $data): array
    {
        return array_map(function ($value) {
            return is_string($value) ? $this->sanitizeString($value) : $value;
        }, $data);
    }
}
```

## Security Headers

### Security Headers Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' data:; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
```

## Monitoring and Logging

### Security Event Logging

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class SecurityLogger
{
    public function logSecurityEvent(string $event, array $context = []): void
    {
        Log::channel('security')->info($event, array_merge([
            'timestamp' => now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
        ], $context));
    }

    public function logFailedLogin(string $email, string $ip): void
    {
        $this->logSecurityEvent('failed_login_attempt', [
            'email' => $email,
            'ip_address' => $ip,
            'severity' => 'warning',
        ]);
    }

    public function logSuspiciousActivity(string $activity, array $details = []): void
    {
        $this->logSecurityEvent('suspicious_activity', [
            'activity' => $activity,
            'details' => $details,
            'severity' => 'critical',
        ]);
    }

    public function logPrivilegeEscalation(int $userId, string $action): void
    {
        $this->logSecurityEvent('privilege_escalation', [
            'user_id' => $userId,
            'action' => $action,
            'severity' => 'critical',
        ]);
    }
}
```

### Real-time Security Monitoring

```php
<?php

namespace App\Http\Middleware;

use App\Services\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecurityMonitoring
{
    private SecurityLogger $logger;

    public function __construct(SecurityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function handle(Request $request, Closure $next)
    {
        $this->detectBruteForce($request);
        $this->detectSqlInjection($request);
        $this->detectXssAttempts($request);

        return $next($request);
    }

    private function detectBruteForce(Request $request): void
    {
        $key = 'login_attempts:' . $request->ip();
        $attempts = Cache::get($key, 0);

        if ($attempts > 5) {
            $this->logger->logSuspiciousActivity('brute_force_detected', [
                'ip' => $request->ip(),
                'attempts' => $attempts,
            ]);
        }
    }

    private function detectSqlInjection(Request $request): void
    {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b.*\bwhere\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\binsert\b.*\binto\b)/i',
        ];

        foreach ($request->all() as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logger->logSuspiciousActivity('sql_injection_attempt', [
                            'field' => $key,
                            'value' => $value,
                            'pattern' => $pattern,
                        ]);
                    }
                }
            }
        }
    }

    private function detectXssAttempts(Request $request): void
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
        ];

        foreach ($request->all() as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logger->logSuspiciousActivity('xss_attempt', [
                            'field' => $key,
                            'value' => $value,
                            'pattern' => $pattern,
                        ]);
                    }
                }
            }
        }
    }
}
```

## Security Auditing

### Automated Security Audits

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SecurityAudit extends Command
{
    protected $signature = 'security:audit {--report}';
    protected $description = 'Perform comprehensive security audit';

    public function handle(): int
    {
        $this->info('Starting security audit...');

        $results = [
            'file_permissions' => $this->auditFilePermissions(),
            'database_security' => $this->auditDatabaseSecurity(),
            'user_accounts' => $this->auditUserAccounts(),
            'configuration' => $this->auditConfiguration(),
            'dependencies' => $this->auditDependencies(),
        ];

        if ($this->option('report')) {
            $this->generateReport($results);
        }

        $this->displayResults($results);

        return Command::SUCCESS;
    }

    private function auditFilePermissions(): array
    {
        $issues = [];

        // Check storage permissions
        if (!is_writable(storage_path())) {
            $issues[] = 'Storage directory not writable';
        }

        // Check .env file permissions
        if (file_exists(base_path('.env')) && substr(sprintf('%o', fileperms(base_path('.env'))), -4) !== '0600') {
            $issues[] = '.env file has incorrect permissions';
        }

        return [
            'status' => empty($issues) ? 'PASS' : 'FAIL',
            'issues' => $issues,
        ];
    }

    private function auditDatabaseSecurity(): array
    {
        $issues = [];

        // Check for default passwords
        $defaultUsers = DB::table('users')
            ->where('email', 'admin@example.com')
            ->orWhere('password', bcrypt('password'))
            ->count();

        if ($defaultUsers > 0) {
            $issues[] = 'Default user accounts detected';
        }

        // Check for SQL injection vulnerabilities
        $suspiciousQueries = DB::getQueryLog();
        foreach ($suspiciousQueries as $query) {
            if (preg_match('/union|drop|insert|delete/i', $query['query'])) {
                $issues[] = 'Potentially unsafe query detected';
            }
        }

        return [
            'status' => empty($issues) ? 'PASS' : 'FAIL',
            'issues' => $issues,
        ];
    }

    private function auditUserAccounts(): array
    {
        $issues = [];

        // Check for inactive admin accounts
        $inactiveAdmins = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'Super Admin')
            ->where('users.last_login_at', '<', now()->subDays(90))
            ->count();

        if ($inactiveAdmins > 0) {
            $issues[] = 'Inactive admin accounts detected';
        }

        return [
            'status' => empty($issues) ? 'PASS' : 'FAIL',
            'issues' => $issues,
        ];
    }

    private function auditConfiguration(): array
    {
        $issues = [];

        // Check debug mode
        if (config('app.debug')) {
            $issues[] = 'Debug mode enabled in production';
        }

        // Check HTTPS enforcement
        if (!config('app.force_https')) {
            $issues[] = 'HTTPS not enforced';
        }

        return [
            'status' => empty($issues) ? 'PASS' : 'FAIL',
            'issues' => $issues,
        ];
    }

    private function auditDependencies(): array
    {
        $issues = [];

        // Check for known vulnerabilities (simplified)
        $composerLock = json_decode(file_get_contents(base_path('composer.lock')), true);

        foreach ($composerLock['packages'] as $package) {
            // This would integrate with vulnerability databases
            if (isset($package['version']) && $this->hasKnownVulnerabilities($package['name'], $package['version'])) {
                $issues[] = "Vulnerable package: {$package['name']} {$package['version']}";
            }
        }

        return [
            'status' => empty($issues) ? 'PASS' : 'FAIL',
            'issues' => $issues,
        ];
    }

    private function hasKnownVulnerabilities(string $package, string $version): bool
    {
        // Integrate with security advisory databases
        return false;
    }

    private function generateReport(array $results): void
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'results' => $results,
            'summary' => [
                'total_checks' => count($results),
                'passed' => count(array_filter($results, fn($r) => $r['status'] === 'PASS')),
                'failed' => count(array_filter($results, fn($r) => $r['status'] === 'FAIL')),
            ],
        ];

        Storage::put('security-audits/' . now()->format('Y-m-d-H-i-s') . '.json', json_encode($report, JSON_PRETTY_PRINT));
    }

    private function displayResults(array $results): void
    {
        foreach ($results as $category => $result) {
            $status = $result['status'] === 'PASS' ? '<info>PASS</info>' : '<error>FAIL</error>';
            $this->line("$category: $status");

            if (!empty($result['issues'])) {
                foreach ($result['issues'] as $issue) {
                    $this->line("  - $issue");
                }
            }
        }
    }
}
```

## Best Practices

### Security Checklist

1. **Server Security**
   - [ ] Operating system updated and hardened
   - [ ] Firewall configured and enabled
   - [ ] SSH access secured with key-based authentication
   - [ ] Fail2ban installed and configured

2. **Application Security**
   - [ ] Debug mode disabled in production
   - [ ] Strong APP_KEY generated and secured
   - [ ] Environment variables properly configured
   - [ ] Security headers implemented

3. **Database Security**
   - [ ] Dedicated database user with minimal permissions
   - [ ] Strong database passwords
   - [ ] Database access restricted to localhost
   - [ ] Regular security updates applied

4. **File System Security**
   - [ ] Proper file permissions set
   - [ ] Sensitive files protected
   - [ ] Storage directories secured
   - [ ] Regular backups configured

5. **Network Security**
   - [ ] HTTPS enforced with strong SSL configuration
   - [ ] Rate limiting implemented
   - [ ] Security headers configured
   - [ ] Server information hidden

### Regular Security Maintenance

1. **Weekly Tasks**
   - Review security logs
   - Check for failed login attempts
   - Monitor system resources

2. **Monthly Tasks**
   - Update system packages
   - Review user access permissions
   - Audit security configurations

3. **Quarterly Tasks**
   - Conduct security penetration testing
   - Review and update security policies
   - Audit third-party dependencies

---

## Navigation

**← Previous:** [Server Configuration](020-server-configuration.md)

**Next →** [SSL Configuration](040-ssl-configuration.md)
