# Server Configuration Guide

## Overview

This guide provides comprehensive server configuration instructions for deploying the Chinook Filament 4 admin panel in production environments, focusing on performance, security, and reliability.

## Table of Contents

- [Overview](#overview)
- [Server Requirements](#server-requirements)
- [Web Server Configuration](#web-server-configuration)
- [PHP Configuration](#php-configuration)
- [Database Configuration](#database-configuration)
- [SSL/TLS Configuration](#ssltls-configuration)
- [Security Hardening](#security-hardening)
- [Performance Optimization](#performance-optimization)
- [Monitoring Setup](#monitoring-setup)
- [Troubleshooting](#troubleshooting)
- [Navigation](#navigation)

## Server Requirements

### Minimum System Requirements

```bash
# Operating System
Ubuntu 22.04 LTS or CentOS 8+
Debian 11+ or RHEL 8+

# Hardware Requirements
CPU: 2+ cores (4+ recommended)
RAM: 4GB minimum (8GB+ recommended)
Storage: 20GB SSD minimum (50GB+ recommended)
Network: 1Gbps connection

# Software Stack
PHP 8.2+ with required extensions
Nginx 1.20+ or Apache 2.4+
SQLite 3.35+ or MySQL 8.0+
Redis 6.0+ (recommended)
Node.js 18+ (for asset compilation)
```

### PHP Extensions

```bash
# Required PHP Extensions
php8.2-cli
php8.2-fpm
php8.2-mysql
php8.2-sqlite3
php8.2-redis
php8.2-curl
php8.2-gd
php8.2-mbstring
php8.2-xml
php8.2-zip
php8.2-bcmath
php8.2-intl
php8.2-opcache
```

## Web Server Configuration

### Nginx Configuration

```nginx
# /etc/nginx/sites-available/chinook-admin
server {
    listen 80;
    listen [::]:80;
    server_name chinook-admin.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name chinook-admin.example.com;
    
    root /var/www/chinook-admin/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/chinook-admin.crt;
    ssl_certificate_key /etc/ssl/private/chinook-admin.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    # File Upload Limits
    client_max_body_size 100M;
    client_body_timeout 60s;
    client_header_timeout 60s;
    
    # Static Asset Caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    # PHP Processing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_param SERVER_NAME $host;
        fastcgi_param HTTPS on;
    }
    
    # Deny Access to Sensitive Files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(storage|bootstrap/cache) {
        deny all;
    }
    
    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
    location /admin/login {
        limit_req zone=login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### Apache Configuration

```apache
# /etc/apache2/sites-available/chinook-admin.conf
<VirtualHost *:80>
    ServerName chinook-admin.example.com
    Redirect permanent / https://chinook-admin.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName chinook-admin.example.com
    DocumentRoot /var/www/chinook-admin/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/chinook-admin.crt
    SSLCertificateKeyFile /etc/ssl/private/chinook-admin.key
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "no-referrer-when-downgrade"
    
    # Compression
    LoadModule deflate_module modules/mod_deflate.so
    <Location />
        SetOutputFilter DEFLATE
        SetEnvIfNoCase Request_URI \
            \.(?:gif|jpe?g|png)$ no-gzip dont-vary
        SetEnvIfNoCase Request_URI \
            \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
    </Location>
    
    # Directory Configuration
    <Directory /var/www/chinook-admin/public>
        AllowOverride All
        Require all granted
        
        # Laravel Pretty URLs
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </Directory>
    
    # Deny Access to Sensitive Directories
    <DirectoryMatch "/(storage|bootstrap/cache)">
        Require all denied
    </DirectoryMatch>
    
    # File Upload Limits
    LimitRequestBody 104857600  # 100MB
</VirtualHost>
```

## PHP Configuration

### PHP-FPM Configuration

```ini
; /etc/php/8.2/fpm/pool.d/chinook-admin.conf
[chinook-admin]
user = www-data
group = www-data
listen = /var/run/php/php8.2-fpm-chinook.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process Management
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000

; Performance Tuning
request_terminate_timeout = 300
request_slowlog_timeout = 10
slowlog = /var/log/php8.2-fpm-chinook-slow.log

; Security
security.limit_extensions = .php
php_admin_value[disable_functions] = exec,passthru,shell_exec,system
php_admin_flag[allow_url_fopen] = off
php_admin_flag[allow_url_include] = off

; Memory and Execution
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300

; File Uploads
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[max_file_uploads] = 20

; Session Configuration
php_admin_value[session.save_handler] = redis
php_admin_value[session.save_path] = "tcp://127.0.0.1:6379"
php_admin_value[session.gc_maxlifetime] = 7200
```

### PHP.ini Optimization

```ini
; /etc/php/8.2/fpm/php.ini

; OPcache Configuration
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1

; Realpath Cache
realpath_cache_size=4096K
realpath_cache_ttl=600

; Error Reporting (Production)
display_errors=Off
display_startup_errors=Off
log_errors=On
error_log=/var/log/php8.2-fpm-errors.log

; Security
expose_php=Off
allow_url_fopen=Off
allow_url_include=Off
```

## Database Configuration

### SQLite Configuration

```bash
# SQLite Optimization for Production
# /var/www/chinook-admin/.env

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/chinook-admin/database/database.sqlite
DB_FOREIGN_KEYS=true

# SQLite WAL Mode Configuration
SQLITE_JOURNAL_MODE=WAL
SQLITE_SYNCHRONOUS=NORMAL
SQLITE_CACHE_SIZE=10000
SQLITE_TEMP_STORE=MEMORY
```

### MySQL Configuration (Alternative)

```ini
# /etc/mysql/mysql.conf.d/chinook-admin.cnf
[mysqld]
# Performance Tuning
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Connection Limits
max_connections = 200
max_user_connections = 180

# Query Cache
query_cache_type = 1
query_cache_size = 256M
query_cache_limit = 2M

# Security
bind-address = 127.0.0.1
skip-networking = false
local-infile = 0
```

## SSL/TLS Configuration

### Let's Encrypt Setup

```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-nginx

# Obtain SSL Certificate
sudo certbot --nginx -d chinook-admin.example.com

# Auto-renewal Setup
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### SSL Security Configuration

```nginx
# Enhanced SSL Configuration
ssl_session_timeout 1d;
ssl_session_cache shared:SSL:50m;
ssl_session_tickets off;

# HSTS (HTTP Strict Transport Security)
add_header Strict-Transport-Security "max-age=63072000" always;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;
ssl_trusted_certificate /etc/ssl/certs/ca-certificates.crt;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

## Security Hardening

### Firewall Configuration

```bash
# UFW Firewall Setup
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Fail2Ban Configuration
sudo apt install fail2ban
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Custom Jail for Laravel
cat > /etc/fail2ban/jail.d/laravel.conf << EOF
[laravel]
enabled = true
port = http,https
filter = laravel
logpath = /var/www/chinook-admin/storage/logs/laravel.log
maxretry = 3
bantime = 3600
EOF
```

### File Permissions

```bash
# Set Proper Permissions
sudo chown -R www-data:www-data /var/www/chinook-admin
sudo find /var/www/chinook-admin -type f -exec chmod 644 {} \;
sudo find /var/www/chinook-admin -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/chinook-admin/storage
sudo chmod -R 775 /var/www/chinook-admin/bootstrap/cache
```

## Performance Optimization

### System-Level Optimization

```bash
# Kernel Parameters
echo 'net.core.somaxconn = 65535' >> /etc/sysctl.conf
echo 'net.ipv4.tcp_max_syn_backlog = 65535' >> /etc/sysctl.conf
echo 'vm.swappiness = 10' >> /etc/sysctl.conf
sysctl -p

# File Descriptor Limits
echo '* soft nofile 65535' >> /etc/security/limits.conf
echo '* hard nofile 65535' >> /etc/security/limits.conf
```

### Application-Level Optimization

```bash
# Laravel Optimization Commands
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# Asset Optimization
npm run production
```

## Monitoring Setup

### Log Configuration

```bash
# Centralized Logging
sudo mkdir -p /var/log/chinook-admin
sudo chown www-data:www-data /var/log/chinook-admin

# Logrotate Configuration
cat > /etc/logrotate.d/chinook-admin << EOF
/var/log/chinook-admin/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
EOF
```

### Health Check Script

```bash
#!/bin/bash
# /usr/local/bin/chinook-health-check.sh

# Check Application Health
curl -f http://localhost/admin/health || exit 1

# Check Database Connection
php /var/www/chinook-admin/artisan tinker --execute="DB::connection()->getPdo();" || exit 1

# Check Redis Connection
redis-cli ping || exit 1

echo "All health checks passed"
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data /var/www/chinook-admin/storage
   sudo chmod -R 775 /var/www/chinook-admin/storage
   ```

2. **PHP-FPM Socket Errors**
   ```bash
   sudo systemctl restart php8.2-fpm
   sudo systemctl status php8.2-fpm
   ```

3. **SSL Certificate Issues**
   ```bash
   sudo certbot certificates
   sudo certbot renew --dry-run
   ```

### Log Locations

- **Nginx**: `/var/log/nginx/`
- **PHP-FPM**: `/var/log/php8.2-fpm.log`
- **Laravel**: `/var/www/chinook-admin/storage/logs/`
- **System**: `/var/log/syslog`

## Navigation

### Related Documentation

- **[Production Environment](010-production-environment.md)** - Environment setup guide
- **[Security Hardening](030-security-hardening.md)** - Advanced security configuration
- **[Performance Optimization](050-performance-optimization.md)** - Performance tuning guide
- **[Monitoring Setup](090-monitoring-setup.md)** - Comprehensive monitoring configuration

### External Resources

- **[Nginx Documentation](https://nginx.org/en/docs/)** - Official Nginx documentation
- **[PHP-FPM Configuration](https://www.php.net/manual/en/install.fpm.configuration.php)** - PHP-FPM reference
- **[Let's Encrypt](https://letsencrypt.org/)** - Free SSL certificates

---

**Last Updated**: 2025-07-07  
**Version**: 1.0.0  
**Compliance**: Security best practices, Performance optimized
