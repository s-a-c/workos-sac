# 1. Chinook Testing Suite Documentation

## Table of Contents

- [1. Overview](#1-overview)
- [2. Documentation Structure](#2-documentation-structure)
  - [2.1 Core Testing Documentation](#21-core-testing-documentation)
  - [2.2 Specialized Testing Areas](#22-specialized-testing-areas)
- [3. Testing Philosophy](#3-testing-philosophy)
- [4. Quick Start](#4-quick-start)
  - [4.1 Prerequisites](#41-prerequisites)
  - [4.2 Test Structure](#42-test-structure)
- [5. Testing Standards](#5-testing-standards)
- [6. Related Documentation](#6-related-documentation)
- [7. Navigation](#7-navigation)

## 1. Overview

This directory contains comprehensive testing documentation for the Chinook database implementation using Pest PHP framework with Laravel 12 modern patterns and aliziodev/laravel-taxonomy system.

## 2. Documentation Structure

### 2.1 Core Testing Documentation

1. **[Test Architecture Overview](010-test-architecture-overview.md)** - Comprehensive test architecture with Mermaid diagrams
2. **[Unit Testing Guide](020-unit-testing-guide.md)** - Model, trait, service, and enum testing strategies
3. **[Feature Testing Guide](030-feature-testing-guide.md)** - API, web routes, and Filament admin panel testing
4. **[Integration Testing Guide](040-integration-testing-guide.md)** - Database relationships and end-to-end workflows
5. **[Test Data Management](050-test-data-management.md)** - Factories, seeders, and database state management

### 2.2 Specialized Testing Areas

1. **[RBAC Testing Guide](060-rbac-testing-guide.md)** - spatie/laravel-permission testing with hierarchical roles
2. **[Trait Testing Guide](070-trait-testing-guide.md)** - HasSecondaryUniqueKey, HasSlug, HasTaxonomies testing
3. **[Hierarchical Data Testing](080-hierarchical-data-testing.md)** - Closure table + adjacency list hybrid testing
4. **[Performance Testing Guide](090-performance-testing-guide.md)** - SQLite WAL optimization and load testing
5. **[Direct Taxonomy Mapping Testing](100-genre-preservation-testing.md)** - ✅ **Comprehensive testing strategy** for single taxonomy system with direct mapping from chinook.sql data

## 3. Testing Philosophy

The Chinook testing suite follows modern Laravel 12 and Pest PHP patterns with emphasis on:

- **Comprehensive Coverage**: 95%+ test coverage target with unit, feature, and integration tests
- **Single Taxonomy System**: Complete testing of direct mapping from chinook.sql to taxonomy system using aliziodev/laravel-taxonomy exclusively
- **Performance Focus**: SQLite WAL journal mode optimizations with performance benchmarking
- **RBAC Integration**: 100% coverage of permission and role testing with spatie/laravel-permission
- **Trait Testing**: Complete coverage of all custom traits (HasTaxonomies, HasSlug, etc.)
- **Data Integrity**: Full validation of direct genre-to-taxonomy mapping without enhancement
- **Modern Laravel 12**: Comprehensive testing of casts() method and current framework patterns
- **Quality Assurance**: Automated testing with CI/CD integration and quality gates

## 4. Quick Start

### 4.1 Prerequisites

```bash
# Install Pest and required plugins
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
composer require --dev pestphp/pest-plugin-livewire
composer require --dev pestphp/pest-plugin-faker
composer require --dev pestphp/pest-plugin-type-coverage

# Initialize Pest
./vendor/bin/pest --init
```

### 4.2 Test Structure

```text
tests/
├── Feature/
│   ├── Api/
│   ├── Web/
│   ├── Filament/
│   └── Livewire/
├── Unit/
│   ├── Models/
│   ├── Traits/
│   ├── Services/
│   └── Enums/
├── Integration/
│   ├── Database/
│   ├── Hierarchical/
│   └── Performance/
└── Pest.php
```

## 5. Testing Standards

### 5.1 Laravel 12 Modern Patterns

- Use `casts()` method over `$casts` property
- Modern factory definitions with state methods
- Current Laravel 12 syntax for all framework features

### 5.2 Pest PHP Framework

- Describe/it blocks for test organization
- Expectation-based assertions
- Helper functions for common operations
- Parallel testing for performance
- Comprehensive coverage reporting with 95%+ target

### 5.3 WCAG 2.1 AA Compliance

- High-contrast color palette for diagrams
- Screen reader compatible documentation
- Accessible navigation structure
- Comprehensive alt text for visual elements

## 6. Related Documentation

- **[Chinook Models Guide](../010-chinook-models-guide.md)** - Model implementations and relationships
- **[Chinook Migrations Guide](../020-chinook-migrations-guide.md)** - Database schema and taxonomy tables
- **[Chinook Factories Guide](../030-chinook-factories-guide.md)** - Test data generation patterns
- **[Chinook Seeders Guide](../040-chinook-seeders-guide.md)** - Database seeding with taxonomy relationships
- **[aliziodev/laravel-taxonomy Guide](../packages/110-aliziodev-laravel-taxonomy-guide.md)** - Primary taxonomy package documentation

## 7. Navigation

**Previous ←** [Chinook Index](../000-chinook-index.md)
**Next →** [Test Architecture Overview](010-test-architecture-overview.md)

---

**Source Attribution:** Refactored from: .ai/guides/chinook/testing/000-testing-index.md on 2025-07-11

*This testing documentation provides comprehensive coverage of the Chinook application testing strategy using Pest PHP framework with Laravel 12 modern patterns and aliziodev/laravel-taxonomy integration.*
