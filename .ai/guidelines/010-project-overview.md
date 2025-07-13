# 1. Project Overview

## 1.1. Introduction

This is a comprehensive, open-source application built on Laravel 12.x and FilamentPHP 3.x. It's designed for Small and Medium Enterprises (SMEs) and large-scale enterprises, offering a modular plugin architecture for managing various business operations.

## 1.2. Core Technologies

- **Laravel 12.x**: Modern PHP framework providing the foundation
- **FilamentPHP 3.x**: Admin panel framework for building the user interface
- **PHP 8.2+**: Taking advantage of modern PHP features
- **MySQL/PostgreSQL**: Database backend options

## 1.3. Project Structure

### 1.3.1. Core Directories

- `/app` - Core application code
- `/plugins` - Modular business logic organized by domain
- `/packages` - Custom packages and third-party integrations
- `/config` - Application configuration
- `/database` - Schema and data migrations
- `/resources` - Frontend assets and views
- `/routes` - URL routing definitions
- `/tests` - Testing infrastructure

### 1.3.2. Plugin Architecture

Plugins are organized under `/plugins/webkul/` with each plugin representing a business domain:

```
plugins/webkul/{module}/
├── composer.json          ← Composer package definition
├── src/
│   ├── {Module}Plugin.php ← FilamentPHP plugin class
│   ├── Models/           ← Domain models
│   ├── Resources/        ← FilamentPHP resources
│   └── Providers/        ← Service providers
├── database/
│   ├── migrations/       ← Database schema
│   └── seeders/         ← Sample data
└── tests/               ← Plugin-specific tests
```

### 1.3.3. Key Plugins

#### 1.3.3.1. Core Plugins

- **Analytics**: Business intelligence and reporting
- **Chatter**: Internal communication
- **Fields**: Custom field definitions
- **Security**: Authentication and authorization
- **Support**: Customer support management
- **Table View**: Enhanced data visualization

#### 1.3.3.2. Business Plugins

- **Accounts**: Financial accounting
- **Contacts**: Customer and contact management
- **Employees**: Human resources
- **Inventories**: Stock management
- **Invoices**: Billing and invoicing
- **Partners**: Partner relationship management
- **Payments**: Payment processing
- **Products**: Product catalog management
- **Projects**: Project management
- **Purchases**: Procurement management
- **Recruitments**: Hiring and recruitment
- **Sales**: Sales management
- **Time-off**: Leave management
- **Timesheets**: Time tracking
- **Website**: Public-facing website management

## 1.4. FilamentPHP Integration

The project uses FilamentPHP extensively for admin interfaces:

- Resources follow FilamentPHP conventions
- FilamentShield for permission management
- FilamentPHP form and table builders
- Dual-panel architecture (Admin/Customer)

## 1.5. Database Architecture

- Laravel migrations for schema changes
- Eloquent ORM for data access
- Proper relationships between entities
- Database seeders for test data

## 1.6. Security Framework

- Laravel security best practices
- FilamentShield for permission management
- Input validation
- Data encryption
- Laravel Sanctum for API authentication

## See Also

### Related Guidelines
- **[Development Standards](030-development-standards.md)** - Code quality and architecture patterns
- **[Security Standards](090-security-standards.md)** - Comprehensive security implementation guide
- **[Testing Standards](050-testing-standards.md)** - Testing requirements for plugins
- **[Documentation Standards](020-documentation-standards.md)** - Plugin documentation requirements

### Quick Decision Guide for New Developers

#### "I need to create a new plugin - where do I start?"
1. **First**: Review this project overview to understand the architecture
2. **Then**: Check [Development Standards](030-development-standards.md) for coding patterns
3. **Next**: Follow [Testing Standards](050-testing-standards.md) for test implementation
4. **Finally**: Document using [Documentation Standards](020-documentation-standards.md)

#### "I need to understand the technology stack"
- **Laravel 12**: See [Development Standards](030-development-standards.md) section 3.4
- **FilamentPHP 3**: Review section 1.4 above and [Development Standards](030-development-standards.md) section 3.2.3
- **Security**: See [Security Standards](090-security-standards.md) for comprehensive security implementation
- **Performance**: See [Performance Standards](100-performance-standards.md) for optimization techniques

#### "I need to work with existing plugins"
- **Plugin Structure**: Review section 1.3.2 above for directory organization
- **Business Plugins**: See section 1.3.3.2 for available business domains
- **Testing**: Use [Testing Guidelines](060-testing/) for plugin-specific testing approaches

---

## Navigation

**Next →** [Documentation Standards](020-documentation-standards.md)
