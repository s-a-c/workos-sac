# GitHub Copilot Instructions for FM4 Project

## Project Overview
This is a Laravel 12 application with Filament v4 beta admin panel, using Pest for testing and following strict typing conventions.

## Core Technologies
- **Framework:** Laravel 12
- **Admin Panel:** Filament v4 beta
- **Testing:** Pest framework
- **Database:** MySQL/PostgreSQL
- **Frontend:** Livewire, Alpine.js, Tailwind CSS
- **Package Manager:** Composer (PHP), npm/pnpm (Node.js)

## Coding Standards
- Always use strict type declarations: `declare(strict_types=1);`
- Follow PSR-12 coding standards
- Use typed properties and return types throughout
- Implement proper error handling and logging
- Follow Laravel conventions and best practices

## Architecture Patterns
- Repository pattern for data access
- Service layer for business logic
- Form requests for validation
- Eloquent ORM with proper relationships
- Dependency injection via Laravel's service container

## Filament Specific Guidelines
- Use Filament's form components and layouts
- Implement proper resource relationships
- Follow Filament v4 beta conventions
- Use Filament's notification system
- Leverage Filament's theming capabilities

## Testing Requirements
- Write Pest tests for all new functionality
- Use feature tests for user workflows
- Use unit tests for isolated logic
- Mock external dependencies appropriately
- Maintain high test coverage

## Security Practices
- Always validate and sanitize user input
- Use Laravel's built-in CSRF protection
- Implement proper authorization policies
- Follow OWASP security guidelines
- Use secure coding practices for data handling

## Performance Guidelines
- Optimize database queries to avoid N+1 problems
- Use appropriate database indexes
- Implement caching strategies where beneficial
- Use lazy loading and eager loading appropriately
- Profile and optimize slow operations

## File Organization
- Follow Laravel's directory structure
- Group related functionality in appropriate namespaces
- Use clear, descriptive naming for classes and methods
- Keep controllers thin, move logic to services/actions

## Documentation
- Write clear, comprehensive PHPDoc comments
- Document complex business logic
- Follow the documentation standards in `.ai/guidelines/020-documentation-standards.md`

## Additional Resources
For detailed project guidelines, refer to:
- `.ai/guidelines/000-index.md` - Complete guidelines index
- `.ai/guidelines/010-project-overview.md` - Project architecture overview
- `.ai/guidelines/030-development-standards.md` - Detailed coding standards
- `.ai/guidelines/050-testing-standards.md` - Testing requirements and practices

## Code Generation Preferences
When generating code, prioritize:
1. Type safety and strict typing
2. Laravel best practices and conventions
3. Filament admin panel patterns
4. Comprehensive test coverage
5. Clear documentation and comments
6. Security and performance considerations