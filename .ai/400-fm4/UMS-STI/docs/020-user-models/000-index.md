# 2. User Models - Index

## 2.1 Overview

This directory contains comprehensive documentation for implementing Single Table Inheritance (STI) user models with state management in the UMS-STI system. The documentation covers STI architecture, user type implementations, polymorphic relationships, state management patterns, and factory seeding strategies.

## 2.2 Documentation Files

### 2.2.1 Architecture Foundation
- [010-sti-architecture-explained.md](010-sti-architecture-explained.md) ✅
  - Complete STI theory and implementation
  - User type hierarchy and behaviors
  - Integration with Laravel ecosystem

### 2.2.2 User Type Implementation
- [020-user-type-implementations.md](020-user-type-implementations.md) 🚧
  - Detailed implementation of each user type
  - Type-specific behaviors and methods
  - Business logic patterns

### 2.2.3 Relationship Management
- [030-polymorphic-relationships.md](030-polymorphic-relationships.md) 🚧
  - Hybrid STI + polymorphic approach
  - Extended user data management
  - Relationship optimization

### 2.2.4 State Management
- [040-state-management-patterns.md](040-state-management-patterns.md) 🚧
  - Spatie model states integration
  - State transition rules and automation
  - Event-driven state management

### 2.2.5 Testing and Data
- [050-user-factories-seeding.md](050-user-factories-seeding.md) 🚧
  - Comprehensive factory patterns
  - Realistic test data generation
  - Development environment seeding

## 2.3 Learning Path

For developers implementing STI user models, follow this recommended reading order:

1. **STI Architecture** - Understand the foundation concepts
2. **User Type Implementation** - Learn specific user type patterns
3. **Polymorphic Relationships** - Handle complex data relationships
4. **State Management** - Implement user lifecycle management
5. **Factories and Seeding** - Create comprehensive test data

## 2.4 Prerequisites

- **Laravel 12.x** Eloquent ORM knowledge
- Understanding of Single Table Inheritance concepts
- Familiarity with Laravel model relationships
- Knowledge of Spatie Laravel packages
- Basic understanding of state machines

## 2.5 User Type Hierarchy

The STI implementation supports the following user types:

- **StandardUser** - Regular application users
- **AdminUser** - Administrative users with elevated permissions
- **GuestUser** - Temporary or limited access users
- **SystemUser** - Automated system processes and integrations

## 2.6 Related Documentation

- [Main Documentation](../README.md)
- [Database Foundation](../010-database-foundation/000-index.md)
- [Team Hierarchy](../030-team-hierarchy/000-index.md)
- [Permission System](../040-permission-system/000-index.md)
- [TDD Implementation](../100-implementation/tdd-implementation-process/000-index.md)

## 2.7 Implementation Status

**Overall Progress**: 1/5 guides complete (20%)

**Completed**:
- STI architecture and theory ✅

**In Progress**:
- User type implementations 🚧
- Polymorphic relationships 🚧
- State management patterns 🚧
- Factory and seeding strategies 🚧

## 2.8 Quick Start

```bash
# Navigate to user models documentation
cd .ai/tasks/UMS-STI/docs/020-user-models/

# Start with STI architecture
open 010-sti-architecture-explained.md
```
