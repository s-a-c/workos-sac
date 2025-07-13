# SSL Configuration Guide

## Overview

This guide covers comprehensive SSL/TLS configuration for the Chinook Filament admin panel, including certificate management, security best practices, and automated renewal strategies.

## Table of Contents

- [Overview](#overview)
- [SSL Certificate Types](#ssl-certificate-types)
- [Let's Encrypt Setup](#lets-encrypt-setup)
- [Commercial Certificate Setup](#commercial-certificate-setup)
- [Nginx SSL Configuration](#nginx-ssl-configuration)
- [Apache SSL Configuration](#apache-ssl-configuration)
- [SSL Security Best Practices](#ssl-security-best-practices)
- [Certificate Management](#certificate-management)
- [Automated Renewal](#automated-renewal)
- [SSL Testing and Validation](#ssl-testing-and-validation)
- [Troubleshooting](#troubleshooting)
- [Monitoring and Alerts](#monitoring-and-alerts)

## SSL Certificate Types

### Certificate Options

1. **Let's Encrypt (Free)**
   - Domain validated certificates
   - 90-day validity period
   - Automated renewal available
   - Perfect for development and production

2. **Commercial Certificates**
   - Extended validation (EV) certificates
   - Organization validated (OV) certificates
   - Longer validity periods (1-2 years)
   - Warranty and support included

3. **Wildcard Certificates**
   - Covers all subdomains
   - Single certificate for multiple services
   - Cost-effective for multiple subdomains

## Let's Encrypt Setup

### Install Certbot

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install certbot python3-certbot-nginx

# CentOS/RHEL
sudo yum install certbot python3-certbot-nginx

# Or using snap
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot
```

### Obtain SSL Certificate

```bash
# For Nginx
sudo certbot --nginx -d admin.chinook.com -d api.chinook.com

# For Apache
sudo certbot --apache -d admin.chinook.com -d api.chinook.com

# Manual certificate generation
sudo certbot certonly --standalone -d admin.chinook.com

# Wildcard certificate
sudo certbot certonly --manual --preferred-challenges=dns -d *.chinook.com
```

### Verify Certificate Installation

```bash
# Check certificate details
sudo certbot certificates

# Test certificate renewal
sudo certbot renew --dry-run

# Check certificate expiration
openssl x509 -in /etc/letsencrypt/live/admin.chinook.com/cert.pem -text -noout | grep "Not After"
```

## Commercial Certificate Setup

### Generate Certificate Signing Request (CSR)

```bash
# Generate private key
openssl genrsa -out chinook.key 2048

# Generate CSR
openssl req -new -key chinook.key -out chinook.csr

# Verify CSR
openssl req -text -noout -verify -in chinook.csr
```

### Install Commercial Certificate

```bash
# Create certificate directory
sudo mkdir -p /etc/ssl/certs/chinook
sudo mkdir -p /etc/ssl/private/chinook

# Install certificate files
sudo cp chinook.crt /etc/ssl/certs/chinook/
sudo cp chinook.key /etc/ssl/private/chinook/
sudo cp intermediate.crt /etc/ssl/certs/chinook/

# Set proper permissions
sudo chmod 644 /etc/ssl/certs/chinook/*
sudo chmod 600 /etc/ssl/private/chinook/*
sudo chown root:root /etc/ssl/certs/chinook/*
sudo chown root:root /etc/ssl/private/chinook/*
```

## Nginx SSL Configuration

### Complete Nginx Configuration

```nginx
# /etc/nginx/sites-available/chinook-admin
server {
    listen 80;
    server_name admin.chinook.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admin.chinook.com;
    
    root /var/www/chinook/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/admin.chinook.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/admin.chinook.com/privkey.pem;
    
    # SSL Security Settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_session_tickets off;
    
    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    ssl_trusted_certificate /etc/letsencrypt/live/admin.chinook.com/chain.pem;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Content Security Policy
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';" always;
    
    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=admin:10m rate=10r/m;
    limit_req zone=admin burst=20 nodelay;
    
    # PHP Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
    
    # Static Files
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
        access_log off;
    }
    
    # Security
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~ /(vendor|storage|bootstrap|config|database|resources|routes|tests)/ {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Laravel Routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Error Pages
    error_page 404 /404.html;
    error_page 500 502 503 504 /50x.html;
    
    # Logging
    access_log /var/log/nginx/chinook-admin.access.log;
    error_log /var/log/nginx/chinook-admin.error.log;
}
```

### SSL Performance Optimization

```nginx
# /etc/nginx/nginx.conf
http {
    # SSL Session Cache
    ssl_session_cache shared:SSL:50m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    
    # SSL Buffer Size
    ssl_buffer_size 8k;
    
    # OCSP Settings
    ssl_stapling on;
    ssl_stapling_verify on;
    
    # DNS Resolver
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
}
```

## Apache SSL Configuration

### Apache Virtual Host Configuration

```apache
# /etc/apache2/sites-available/chinook-admin-ssl.conf
<VirtualHost *:443>
    ServerName admin.chinook.com
    DocumentRoot /var/www/chinook/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/admin.chinook.com/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/admin.chinook.com/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/admin.chinook.com/chain.pem
    
    # SSL Security
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder off
    SSLSessionTickets off
    
    # OCSP Stapling
    SSLUseStapling on
    SSLStaplingResponderTimeout 5
    SSLStaplingReturnResponderErrors off
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.2-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Laravel Configuration
    <Directory /var/www/chinook/public>
        AllowOverride All
        Require all granted
        
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    # Security
    <DirectoryMatch "/(vendor|storage|bootstrap|config|database|resources|routes|tests)/">
        Require all denied
    </DirectoryMatch>
    
    # Logging
    CustomLog /var/log/apache2/chinook-admin.access.log combined
    ErrorLog /var/log/apache2/chinook-admin.error.log
</VirtualHost>

# HTTP to HTTPS Redirect
<VirtualHost *:80>
    ServerName admin.chinook.com
    Redirect permanent / https://admin.chinook.com/
</VirtualHost>
```

## SSL Security Best Practices

### Security Configuration Checklist

1. **Protocol Security**
   - Disable SSLv3, TLSv1.0, TLSv1.1
   - Enable only TLSv1.2 and TLSv1.3
   - Use strong cipher suites

2. **Certificate Security**
   - Use 2048-bit or higher RSA keys
   - Consider ECDSA certificates for better performance
   - Implement certificate pinning for critical applications

3. **HSTS Implementation**
   - Enable HTTP Strict Transport Security
   - Include subdomains in HSTS policy
   - Consider HSTS preloading

4. **OCSP Stapling**
   - Enable OCSP stapling for better performance
   - Configure proper OCSP responder timeouts

## Certificate Management

### Certificate Monitoring Script

```bash
#!/bin/bash
# /usr/local/bin/ssl-monitor.sh

DOMAIN="admin.chinook.com"
CERT_FILE="/etc/letsencrypt/live/$DOMAIN/cert.pem"
ALERT_DAYS=30

# Check certificate expiration
EXPIRY_DATE=$(openssl x509 -in "$CERT_FILE" -noout -enddate | cut -d= -f2)
EXPIRY_EPOCH=$(date -d "$EXPIRY_DATE" +%s)
CURRENT_EPOCH=$(date +%s)
DAYS_UNTIL_EXPIRY=$(( (EXPIRY_EPOCH - CURRENT_EPOCH) / 86400 ))

if [ $DAYS_UNTIL_EXPIRY -le $ALERT_DAYS ]; then
    echo "WARNING: SSL certificate for $DOMAIN expires in $DAYS_UNTIL_EXPIRY days"
    # Send alert (email, Slack, etc.)
    curl -X POST -H 'Content-type: application/json' \
        --data "{\"text\":\"SSL certificate for $DOMAIN expires in $DAYS_UNTIL_EXPIRY days\"}" \
        "$SLACK_WEBHOOK_URL"
fi
```

## Automated Renewal

### Certbot Renewal Configuration

```bash
# Add to crontab
sudo crontab -e

# Renew certificates twice daily
0 12 * * * /usr/bin/certbot renew --quiet
0 0 * * * /usr/bin/certbot renew --quiet

# Reload web server after renewal
0 2 * * * /usr/bin/certbot renew --quiet --deploy-hook "systemctl reload nginx"
```

### Renewal Hook Script

```bash
#!/bin/bash
# /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh

# Reload web server
systemctl reload nginx

# Restart other services if needed
systemctl restart php8.2-fpm

# Log renewal
echo "$(date): SSL certificates renewed and services reloaded" >> /var/log/ssl-renewal.log

# Send notification
curl -X POST -H 'Content-type: application/json' \
    --data '{"text":"SSL certificates renewed successfully"}' \
    "$SLACK_WEBHOOK_URL"
```

## SSL Testing and Validation

### Testing Commands

```bash
# Test SSL configuration
openssl s_client -connect admin.chinook.com:443 -servername admin.chinook.com

# Check certificate chain
openssl s_client -connect admin.chinook.com:443 -showcerts

# Test specific TLS version
openssl s_client -connect admin.chinook.com:443 -tls1_2

# Check OCSP stapling
openssl s_client -connect admin.chinook.com:443 -status

# Verify certificate
openssl verify -CAfile /etc/ssl/certs/ca-certificates.crt /etc/letsencrypt/live/admin.chinook.com/cert.pem
```

### Online SSL Testing Tools

1. **SSL Labs SSL Test**
   - https://www.ssllabs.com/ssltest/
   - Comprehensive SSL configuration analysis
   - Security grade rating

2. **SSL Checker**
   - https://www.sslshopper.com/ssl-checker.html
   - Certificate chain validation
   - Expiration date checking

## Troubleshooting

### Common SSL Issues

1. **Certificate Chain Issues**
   ```bash
   # Check certificate chain
   openssl s_client -connect admin.chinook.com:443 -showcerts
   
   # Verify intermediate certificates
   openssl verify -CAfile ca-bundle.crt certificate.crt
   ```

2. **Mixed Content Warnings**
   ```php
   // Force HTTPS in Laravel
   // AppServiceProvider.php
   public function boot()
   {
       if (config('app.env') === 'production') {
           URL::forceScheme('https');
       }
   }
   ```

3. **Certificate Renewal Failures**
   ```bash
   # Check renewal logs
   sudo tail -f /var/log/letsencrypt/letsencrypt.log
   
   # Manual renewal with verbose output
   sudo certbot renew --dry-run --verbose
   ```

## Monitoring and Alerts

### SSL Monitoring Service

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MonitorSSLCertificates extends Command
{
    protected $signature = 'ssl:monitor';
    protected $description = 'Monitor SSL certificate expiration';

    public function handle()
    {
        $domains = ['admin.chinook.com', 'api.chinook.com'];
        
        foreach ($domains as $domain) {
            $this->checkCertificate($domain);
        }
    }

    private function checkCertificate(string $domain): void
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $socket = stream_socket_client(
            "ssl://{$domain}:443",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket) {
            $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
            $certData = openssl_x509_parse($cert);
            
            $expiryDate = $certData['validTo_time_t'];
            $daysUntilExpiry = ($expiryDate - time()) / 86400;
            
            if ($daysUntilExpiry <= 30) {
                $this->alert("SSL certificate for {$domain} expires in " . round($daysUntilExpiry) . " days");
            }
            
            fclose($socket);
        }
    }
}
```

---

## Navigation

**← Previous:** [Security Hardening](030-security-hardening.md)

**Next →** [Performance Optimization](050-performance-optimization.md)
