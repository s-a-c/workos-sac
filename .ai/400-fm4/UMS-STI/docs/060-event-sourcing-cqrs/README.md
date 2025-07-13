# Event-Sourcing and CQRS Architecture for UMS-STI

## Overview

This directory contains comprehensive documentation for implementing Event-Sourcing and CQRS (Command Query Responsibility Segregation) patterns as foundational architectural patterns for the User Management System with Single Table Inheritance (UMS-STI).

## Architecture Goals

- **Auditability**: Complete event history for compliance and debugging
- **Scalability**: Separation of read/write operations for performance
- **Reliability**: Event replay capabilities for system recovery
- **Analytics**: Rich event data for business intelligence

## Core Technologies

- **Event Store**: `spatie/laravel-event-sourcing` as the foundation
- **Event Database**: Separate, exclusive SQLite database connection with WAL optimization
- **Event IDs**: Snowflake IDs using `glhd/bits` for distributed uniqueness
- **Admin Interface**: Filament 4 integration (primarily command-side)
- **State Management**: Finite State Machines using spatie packages and enhanced PHP enums
- **Data Transfer**: DTOs and Value Objects for type safety

## Documentation Structure

### 01. Event-Sourcing Architecture
Comprehensive design of the event store, event types, versioning strategies, and snapshot mechanisms.

### 02. CQRS Implementation
Detailed implementation of command and query models, handlers, and consistency strategies.

### 03. Projectors and Reactors
Design and implementation of projectors for read-side updates and reactors for side effects.

### 04. Filament Integration
Integration strategy for Filament 4 admin interface with event-sourced architecture.

### 05. FSM State Management
Finite State Machine integration with event-sourcing for user and team lifecycle management.

### 06. DTOs and Value Objects
Type-safe data transfer objects and domain value objects for the event-sourced system.

### 07. Implementation Strategy
Greenfield implementation approach, development phases, and deployment strategies.

### 08. Performance Optimization
SQLite optimization, projection performance, caching strategies, and monitoring.

### 09. Testing Strategy
Comprehensive testing approach for event stores, projections, and integration workflows.

## Reading Order

For developers new to event-sourcing and CQRS, we recommend reading the documents in the following order:

1. **01-event-sourcing-architecture.md** - Foundation concepts and design
2. **02-cqrs-implementation.md** - Command and query separation
3. **05-fsm-state-management.md** - State management integration
4. **06-dto-value-objects.md** - Type safety and data structures
5. **03-projectors-reactors.md** - Read-side and side effects
6. **04-filament-integration.md** - Admin interface integration
7. **07-implementation-strategy.md** - Development approach
8. **08-performance-optimization.md** - Performance considerations
9. **09-testing-strategy.md** - Testing and validation

## Database Architecture

### Separate Event Store Database

The event sourcing implementation requires a **separate, exclusive database connection** for the event store:

- **Production**: Dedicated SQLite database with WAL mode optimization
- **Testing**: Separate event store database connection (even when using `:memory:`)
- **Performance**: Optimized SQLite configuration with 64MB cache, memory mapping, and WAL journaling
- **Isolation**: Complete separation from application database to prevent conflicts and optimize performance

### Database Configuration Requirements

```php
// config/database.php - Event Store Connection
'event_store' => [
    'driver' => 'sqlite',
    'database' => env('EVENT_STORE_DATABASE', database_path('event_store.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'pragmas' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000,  // 64MB cache
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB memory mapping
        'foreign_keys' => 'ON',
    ],
],

// Testing Event Store Connection
'event_store_testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:', // or separate test database file
    'prefix' => '',
    'foreign_key_constraints' => true,
    'pragmas' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000,
        'temp_store' => 'MEMORY',
        'foreign_keys' => 'ON',
    ],
],
```

## Prerequisites

Before implementing the event-sourcing architecture, ensure familiarity with:

- Laravel framework and Eloquent ORM
- Single Table Inheritance (STI) patterns
- Basic event-sourcing and CQRS concepts
- SQLite database optimization and WAL mode
- Separate database connection management
- Filament admin panel development

## Related Documentation

- **UMS-STI Decision Log**: `.ai/tasks/UMS-STI/decision-log-UMS-STI.md`
- **UMS-STI PRD**: `.ai/tasks/UMS-STI/prd-UMS-STI.md`
- **Implementation Documentation**: `.ai/400-fm4/user-sti-implementation/`
- **Existing Architecture Docs**: `.ai/tasks/UMS-STI/docs/`

## Implementation Status

This is a greenfield implementation building the entire UMS-STI system from scratch using event-sourcing and CQRS as foundational architectural patterns.

## Support and Questions

For questions about the event-sourcing implementation, refer to:
1. The specific document sections for detailed explanations
2. The troubleshooting guides in each document
3. The decision log for architectural rationale
4. The testing strategy for validation approaches
