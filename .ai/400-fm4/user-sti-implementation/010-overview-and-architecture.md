# 1. Overview and Architecture

## 1.1. Introduction

This document outlines the high-level architecture for implementing a User model using Single Table Inheritance (STI) pattern. The design emphasizes modern PHP practices, type safety, and maintainable code structure while leveraging Laravel's ecosystem and FilamentPHP v4.

## 1.2. Architectural Decisions

### 1.2.1. Why Single Table Inheritance?

**Benefits:**
- **Performance**: Single table queries are faster than joins across multiple tables
- **Simplicity**: Easier to maintain relationships and constraints
- **Flexibility**: Easy to add new user types without schema changes
- **Laravel Integration**: Works seamlessly with Eloquent ORM

**Trade-offs:**
- **Sparse Columns**: Some columns may be null for certain user types
- **Table Size**: Single table may grow large with many user types
- **Type Safety**: Requires careful implementation to maintain type integrity

### 1.2.2. Package Selection Rationale

```php
// Core STI Implementation
"tightenco/parental": "^1.3"           // Mature, well-tested STI package

// State Management
"spatie/laravel-model-states": "^2.4"  // Robust FSM implementation
"spatie/laravel-model-status": "^1.11" // Status tracking and history

// Modern PHP Features
"symfony/uid": "^6.3"                  // ULID support for unique identifiers
"spatie/laravel-sluggable": "^3.4"     // SEO-friendly URL generation
"spatie/laravel-data": "^3.9"          // Type-safe DTOs and validation
```

## 1.3. User Type Hierarchy

### 1.3.1. Base User Class

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tightenco\Parental\HasParent;

abstract class User extends Authenticatable
{
    use HasParent;
    
    // Base user functionality shared across all types
}
```

### 1.3.2. User Type Definitions

#### **User (Standard Authenticated User)**
- **Purpose**: Regular application users with standard permissions
- **Use Cases**: Customer accounts, member profiles, general application access
- **Characteristics**: Basic authentication, profile management, standard features

#### **Admin (Administrative User)**
- **Purpose**: System administrators with elevated permissions
- **Use Cases**: System management, user administration, configuration access
- **Characteristics**: Enhanced permissions, admin panel access, system controls

#### **Guest (Temporary User Representation)**
- **Purpose**: Temporary users for tracking and conversion
- **Use Cases**: Shopping cart persistence, form pre-filling, analytics tracking
- **Characteristics**: Limited permissions, temporary data storage, conversion tracking

### 1.3.3. Suggested Additional User Types

#### **Moderator**
- **Purpose**: Content moderation and community management
- **Use Cases**: Forum moderation, content review, user support
- **Characteristics**: Moderate permissions, content management tools

#### **ApiUser**
- **Purpose**: API-only access for integrations
- **Use Cases**: Third-party integrations, service-to-service communication
- **Characteristics**: Token-based auth, API rate limiting, restricted UI access

## 1.4. System Architecture

### 1.4.1. Layer Architecture

```
┌─────────────────────────────────────┐
│           Presentation Layer        │
│    (FilamentPHP v4 Admin Panel)     │
├─────────────────────────────────────┤
│          Application Layer          │
│    (Controllers, Form Requests)     │
├─────────────────────────────────────┤
│           Domain Layer              │
│   (Models, States, Enums, DTOs)    │
├─────────────────────────────────────┤
│        Infrastructure Layer        │
│  (Database, External Services)     │
└─────────────────────────────────────┘
```

### 1.4.2. Component Relationships

```php
// Core Model Hierarchy
User (Abstract Base)
├── StandardUser
├── Admin
├── Guest
├── Moderator (Optional)
└── ApiUser (Optional)

// Supporting Components
├── UserStates (FSM)
├── UserStatus (Status Tracking)
├── UserEnums (Roles, Permissions)
├── UserData (DTOs)
└── UserValueObjects
```

## 1.5. Technical Requirements

### 1.5.1. System Requirements

- **PHP**: 8.4+ (for latest enum features and performance improvements)
- **Laravel**: 12.x (latest LTS with modern features)
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Memory**: Minimum 512MB for development
- **Storage**: SSD recommended for optimal performance

### 1.5.2. Development Dependencies

```json
{
    "require": {
        "php": "^8.4",
        "laravel/framework": "^12.0",
        "tightenco/parental": "^1.3",
        "spatie/laravel-model-states": "^2.4",
        "spatie/laravel-model-status": "^1.11",
        "symfony/uid": "^6.3",
        "spatie/laravel-sluggable": "^3.4",
        "spatie/laravel-data": "^3.9",
        "spatie/laravel-permission": "^6.0",
        "filament/filament": "^4.0"
    },
    "require-dev": {
        "pestphp/pest": "^2.0",
        "pestphp/pest-plugin-laravel": "^2.0"
    }
}
```

## 1.6. Design Patterns Used

### 1.6.1. Primary Patterns

- **Single Table Inheritance**: Core pattern for user type management
- **State Pattern**: For user state management and transitions
- **Data Transfer Object**: For API and form data handling
- **Value Object**: For complex data types and validation
- **Factory Pattern**: For user creation and testing

### 1.6.2. Laravel-Specific Patterns

- **Eloquent Model Inheritance**: STI implementation
- **Service Container**: Dependency injection
- **Observer Pattern**: Model events and listeners
- **Repository Pattern**: Data access abstraction (optional)

## 1.7. Security Considerations

### 1.7.1. Authentication & Authorization

- **Multi-Guard Authentication**: Separate guards for different user types
- **Role-Based Access Control**: Enum-based permission system
- **API Authentication**: Sanctum for API users
- **Session Security**: Secure session handling for web users

### 1.7.2. Data Protection

- **Attribute Casting**: Automatic encryption for sensitive data
- **Mass Assignment Protection**: Fillable/guarded properties
- **SQL Injection Prevention**: Eloquent ORM protection
- **XSS Protection**: Input sanitization and output escaping

## 1.8. Performance Considerations

### 1.8.1. Database Optimization

- **Indexing Strategy**: Optimized indexes for STI queries
- **Query Optimization**: Efficient STI-aware queries
- **Caching**: Model and query result caching
- **Connection Pooling**: Database connection optimization

### 1.8.2. Application Performance

- **Lazy Loading**: Efficient relationship loading
- **Eager Loading**: Prevent N+1 query problems
- **Memory Management**: Efficient object instantiation
- **Caching Strategy**: Redis/Memcached integration

---

**Next**: [STI Model Structure](020-sti-model-structure.md) - Detailed implementation of the STI pattern with code examples.
