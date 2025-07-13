# UMS-STI Implementation Tasks

## Project Overview
This task list implements the User Management System with Single Table Inheritance (UMS-STI) based on the approved PRD, decision log, and test specifications. The implementation follows the priority recommendations with SQLite optimization, closure table team hierarchy, hybrid STI user models, permission isolation, and GDPR compliance.

## Relevant Files

### PHP/Laravel Project Structure
- `config/database.php` - SQLite configuration with WAL mode and performance optimization
- `database/migrations/001_create_users_table.php` - Base users table with STI support
- `database/migrations/002_create_user_profiles_table.php` - Polymorphic user-specific data
- `database/migrations/003_create_teams_table.php` - Teams table with hierarchy support
- `database/migrations/004_create_team_closure_table.php` - Closure table for efficient hierarchy queries
- `database/migrations/005_create_team_user_table.php` - Team membership pivot table
- `database/migrations/006_create_permissions_tables.php` - Spatie permission tables
- `database/migrations/007_create_audit_logs_table.php` - GDPR-compliant audit logging
- `database/migrations/008_create_gdpr_requests_table.php` - GDPR request tracking

### Models and STI Implementation
- `app/Models/User.php` - Abstract base User model with STI support
- `app/Models/StandardUser.php` - Standard user implementation
- `app/Models/Admin.php` - Admin user with elevated permissions
- `app/Models/Guest.php` - Guest user with limited access
- `app/Models/SystemUser.php` - System user with bypass capabilities
- `app/Models/UserProfile.php` - Polymorphic user profile data
- `app/Models/Team.php` - Abstract base Team model
- `app/Models/Organization.php` - Organization team type
- `app/Models/Department.php` - Department team type
- `app/Models/Project.php` - Project team type
- `app/Models/Squad.php` - Squad team type
- `app/Models/TeamClosure.php` - Closure table model for hierarchy

### Services and Business Logic
- `app/Services/UserService.php` - User management business logic
- `app/Services/TeamService.php` - Team hierarchy and membership management
- `app/Services/PermissionService.php` - Permission validation and caching
- `app/Services/GdprService.php` - GDPR compliance operations
- `app/Services/AuditService.php` - Audit logging and anonymization

### Controllers and API
- `app/Http/Controllers/Api/UserController.php` - User management API endpoints
- `app/Http/Controllers/Api/TeamController.php` - Team management API endpoints
- `app/Http/Controllers/Api/PermissionController.php` - Permission management API
- `app/Http/Controllers/Api/GdprController.php` - GDPR compliance API

### FilamentPHP Admin Interface
- `app/Filament/Resources/UserResource.php` - User management interface
- `app/Filament/Resources/TeamResource.php` - Team hierarchy management
- `app/Filament/Resources/PermissionResource.php` - Permission assignment interface
- `app/Filament/Resources/AuditLogResource.php` - Audit trail viewer

### Enums and Value Objects
- `app/Enums/UserType.php` - User type enumeration
- `app/Enums/UserState.php` - User state enumeration
- `app/Enums/TeamType.php` - Team type enumeration
- `app/Enums/TeamStatus.php` - Team status enumeration

### Policies and Middleware
- `app/Policies/UserPolicy.php` - User access control policies
- `app/Policies/TeamPolicy.php` - Team access control policies
- `app/Http/Middleware/PermissionCacheMiddleware.php` - Permission caching middleware

### Database Factories and Seeders
- `database/factories/UserFactory.php` - User model factory
- `database/factories/TeamFactory.php` - Team model factory
- `database/seeders/UserSeeder.php` - User data seeding
- `database/seeders/TeamSeeder.php` - Team hierarchy seeding
- `database/seeders/PermissionSeeder.php` - Permission and role seeding

### Test Files
- `tests/Unit/Models/UserTest.php` - User model unit tests
- `tests/Unit/Models/TeamTest.php` - Team model unit tests
- `tests/Unit/Services/PermissionServiceTest.php` - Permission service tests
- `tests/Unit/Services/GdprServiceTest.php` - GDPR compliance tests
- `tests/Feature/Api/UserControllerTest.php` - User API feature tests
- `tests/Feature/Api/TeamControllerTest.php` - Team API feature tests
- `tests/Feature/Auth/AuthenticationTest.php` - Authentication flow tests
- `tests/Feature/Permissions/PermissionIsolationTest.php` - Permission isolation tests
- `tests/Feature/Teams/HierarchyTest.php` - Team hierarchy tests
- `tests/Feature/Gdpr/ComplianceTest.php` - GDPR compliance tests
- `tests/Performance/PermissionCacheTest.php` - Permission caching performance tests

### Configuration and Infrastructure
- `config/permission.php` - Spatie permission configuration
- `config/activitylog.php` - Activity logging configuration
- `config/backup.php` - Backup configuration for GDPR
- `config/cache.php` - Redis caching configuration
- `docker-compose.yml` - Development environment with Redis

### Notes

#### For PHP/Laravel Projects:
- Use `php artisan test` or `./vendor/bin/pest` to run the test suite
- Use `./vendor/bin/pest --coverage` to generate coverage reports (target: 95%)
- Use `php artisan migrate:fresh --seed` to reset database with test data
- Use `php artisan queue:work` to process background jobs (GDPR operations)
- Use `php artisan backup:run` to test backup functionality
- SQLite database file will be created at `database/database.sqlite`
- WAL mode files (`database.sqlite-wal`, `database.sqlite-shm`) are normal

## Tasks

- [ ] 1.0 Database Foundation and SQLite Optimization
  - [ ] 1.1 Configure SQLite with WAL Mode and Performance Optimization
    - [ ] 1.1.1 Update `config/database.php` with SQLite WAL configuration
    - [ ] 1.1.2 Set performance pragmas (cache_size, mmap_size, synchronous)
    - [ ] 1.1.3 Enable foreign key constraints and optimize settings
    - [ ] 1.1.4 Create database connection test to validate WAL mode
  - [ ] 1.2 Install and Configure Required Laravel Packages
    - [ ] 1.2.1 Install `tightenco/parental` for STI support
    - [ ] 1.2.2 Install `spatie/laravel-permission` for role management
    - [ ] 1.2.3 Install `spatie/laravel-model-states` for state management
    - [ ] 1.2.4 Install `spatie/laravel-activitylog` for audit logging
    - [ ] 1.2.5 Install `wildside/userstamps` for user stamp tracking
    - [ ] 1.2.6 Install `symfony/uid` for ULID support
    - [ ] 1.2.7 Install `spatie/laravel-sluggable` for URL-friendly slugs
  - [ ] 1.3 Create Base Database Migrations
    - [ ] 1.3.1 Create users table migration with STI support
    - [ ] 1.3.2 Create user_profiles table for polymorphic user data
    - [ ] 1.3.3 Create teams table with hierarchy support
    - [ ] 1.3.4 Create team_closure table for efficient hierarchy queries
    - [ ] 1.3.5 Create team_user pivot table for membership
    - [ ] 1.3.6 Run Spatie permission migrations
    - [ ] 1.3.7 Create audit_logs table for GDPR compliance
    - [ ] 1.3.8 Create gdpr_requests table for compliance tracking
  - [ ] 1.4 Set Up Database Indexing Strategy
    - [ ] 1.4.1 Add indexes for STI queries (type, state columns)
    - [ ] 1.4.2 Add indexes for team hierarchy queries
    - [ ] 1.4.3 Add indexes for permission lookups
    - [ ] 1.4.4 Add indexes for audit log queries
    - [ ] 1.4.5 Validate index performance with EXPLAIN QUERY PLAN

- [ ] 2.0 Hybrid STI User Models with State Management
  - [ ] 2.1 Create Base User Model with STI Support
    - [ ] 2.1.1 Create abstract User model extending Authenticatable
    - [ ] 2.1.2 Add HasParent trait from tightenco/parental
    - [ ] 2.1.3 Configure fillable fields and casts
    - [ ] 2.1.4 Add ULID generation and slug support
    - [ ] 2.1.5 Add user stamps tracking (created_by, updated_by, deleted_by)
  - [ ] 2.2 Implement User Type Classes
    - [ ] 2.2.1 Create StandardUser model with specific behaviors
    - [ ] 2.2.2 Create Admin model with elevated permissions
    - [ ] 2.2.3 Create Guest model with limited access
    - [ ] 2.2.4 Create SystemUser model with bypass capabilities
    - [ ] 2.2.5 Define type-specific attributes and methods
  - [ ] 2.3 Create Polymorphic User Profile System
    - [ ] 2.3.1 Create UserProfile model for extended user data
    - [ ] 2.3.2 Create StandardUserProfile for standard user data
    - [ ] 2.3.3 Create AdminProfile for admin-specific data
    - [ ] 2.3.4 Create GuestProfile for guest session data
    - [ ] 2.3.5 Set up polymorphic relationships
  - [ ] 2.4 Implement User State Management
    - [ ] 2.4.1 Create UserState enum (active, inactive, suspended, pending)
    - [ ] 2.4.2 Configure spatie/laravel-model-states
    - [ ] 2.4.3 Define state transition rules and validations
    - [ ] 2.4.4 Create state change event listeners
    - [ ] 2.4.5 Add automatic state transitions (inactivity timeout)
  - [ ] 2.5 Create User Factories and Seeders
    - [ ] 2.5.1 Create UserFactory with type-specific data
    - [ ] 2.5.2 Create UserProfileFactory for polymorphic data
    - [ ] 2.5.3 Create UserSeeder with sample data for each type
    - [ ] 2.5.4 Create development seed data with realistic scenarios

- [ ] 3.0 Team Hierarchy with Closure Table Implementation
  - [ ] 3.1 Create Base Team Model with STI Support
    - [ ] 3.1.1 Create abstract Team model with HasParent trait
    - [ ] 3.1.2 Add ULID generation and slug support for teams
    - [ ] 3.1.3 Configure team settings and metadata JSON fields
    - [ ] 3.1.4 Add user stamps tracking for team operations
    - [ ] 3.1.5 Define team hierarchy relationships (parent/children)
  - [ ] 3.2 Implement Team Type Classes
    - [ ] 3.2.1 Create Organization model (root level teams)
    - [ ] 3.2.2 Create Department model with organization parent
    - [ ] 3.2.3 Create Project model with timeline and progress tracking
    - [ ] 3.2.4 Create Squad model for task-focused teams
    - [ ] 3.2.5 Define type-specific behaviors and validation rules
  - [ ] 3.3 Implement Closure Table for Hierarchy Queries
    - [ ] 3.3.1 Create TeamClosure model for ancestor-descendant relationships
    - [ ] 3.3.2 Create database triggers for closure table maintenance
    - [ ] 3.3.3 Implement hierarchy query methods (ancestors, descendants)
    - [ ] 3.3.4 Add hierarchy depth calculation and validation
    - [ ] 3.3.5 Create hierarchy navigation helper methods
  - [ ] 3.4 Implement Team Membership System
    - [ ] 3.4.1 Create team-user pivot table with roles and timestamps
    - [ ] 3.4.2 Add team membership methods (addMember, removeMember)
    - [ ] 3.4.3 Implement role-based team access (member, leader, executive, deputy)
    - [ ] 3.4.4 Create active team tracking for users
    - [ ] 3.4.5 Add team switching functionality with session persistence
  - [ ] 3.5 Create Team Factories and Seeders
    - [ ] 3.5.1 Create TeamFactory with type-specific data
    - [ ] 3.5.2 Create TeamSeeder with realistic organizational hierarchy
    - [ ] 3.5.3 Create team membership seed data
    - [ ] 3.5.4 Validate closure table integrity after seeding

- [ ] 4.0 Permission System with Isolation and Caching
  - [ ] 4.1 Configure Spatie Laravel Permission
    - [ ] 4.1.1 Publish and configure spatie/laravel-permission
    - [ ] 4.1.2 Create permission and role enums
    - [ ] 4.1.3 Define team-scoped permissions (no inheritance)
    - [ ] 4.1.4 Create permission seeder with role definitions
    - [ ] 4.1.5 Configure permission caching with Redis
  - [ ] 4.2 Implement Permission Isolation System
    - [ ] 4.2.1 Create custom permission middleware for team isolation
    - [ ] 4.2.2 Implement explicit team access validation
    - [ ] 4.2.3 Create permission policies for each model
    - [ ] 4.2.4 Add permission validation before all protected actions
    - [ ] 4.2.5 Ensure no automatic permission inheritance through hierarchy
  - [ ] 4.3 Implement SystemUser Bypass Mechanism
    - [ ] 4.3.1 Create SystemUser policy overrides
    - [ ] 4.3.2 Implement bypass logging for audit compliance
    - [ ] 4.3.3 Add SystemUser identification methods
    - [ ] 4.3.4 Create emergency access procedures
    - [ ] 4.3.5 Validate bypass functionality with comprehensive tests
  - [ ] 4.4 Create Permission Caching Layer
    - [ ] 4.4.1 Set up Redis for permission caching
    - [ ] 4.4.2 Implement user permission cache with TTL
    - [ ] 4.4.3 Create cache invalidation on permission changes
    - [ ] 4.4.4 Add cache warming for frequently accessed permissions
    - [ ] 4.4.5 Monitor cache hit rates and performance metrics
  - [ ] 4.5 Create Permission Management Service
    - [ ] 4.5.1 Create PermissionService for business logic
    - [ ] 4.5.2 Implement bulk permission assignment methods
    - [ ] 4.5.3 Add permission validation and conflict detection
    - [ ] 4.5.4 Create permission audit trail functionality
    - [ ] 4.5.5 Add permission reporting and analytics

- [ ] 5.0 GDPR Compliance and Audit System
  - [ ] 5.1 Implement Separate Data Retention Architecture
    - [ ] 5.1.1 Design data classification (personal vs. audit data)
    - [ ] 5.1.2 Create anonymous token system for audit trail continuity
    - [ ] 5.1.3 Implement data anonymization procedures
    - [ ] 5.1.4 Create automated data purging after 2 years
    - [ ] 5.1.5 Set up 7-year audit log retention with anonymization
  - [ ] 5.2 Create GDPR Request Management System
    - [ ] 5.2.1 Create GdprRequest model for tracking compliance requests
    - [ ] 5.2.2 Implement data export functionality (JSON format)
    - [ ] 5.2.3 Create secure data deletion with audit preservation
    - [ ] 5.2.4 Add consent management and tracking
    - [ ] 5.2.5 Implement breach notification system
  - [ ] 5.3 Implement Comprehensive Audit Logging
    - [ ] 5.3.1 Configure spatie/laravel-activitylog for all models
    - [ ] 5.3.2 Add user stamps to all audit entries
    - [ ] 5.3.3 Create audit log anonymization on user deletion
    - [ ] 5.3.4 Implement audit log search and filtering
    - [ ] 5.3.5 Add audit log export functionality
  - [ ] 5.4 Create GDPR Service Layer
    - [ ] 5.4.1 Create GdprService for compliance operations
    - [ ] 5.4.2 Implement user data export with all related information
    - [ ] 5.4.3 Create compliant user deletion process
    - [ ] 5.4.4 Add data portability functionality
    - [ ] 5.4.5 Implement compliance reporting and monitoring
  - [ ] 5.5 Set Up Automated Compliance Monitoring
    - [ ] 5.5.1 Create scheduled jobs for data retention enforcement
    - [ ] 5.5.2 Add compliance metrics and alerting
    - [ ] 5.5.3 Implement data breach detection and notification
    - [ ] 5.5.4 Create compliance dashboard and reporting
    - [ ] 5.5.5 Add automated compliance testing and validation

- [ ] 6.0 FilamentPHP Admin Interface
  - [ ] 6.1 Set Up FilamentPHP v4 with Custom Configuration
    - [ ] 6.1.1 Install and configure FilamentPHP v4
    - [ ] 6.1.2 Create admin panel with custom branding
    - [ ] 6.1.3 Configure role-based access to admin panels
    - [ ] 6.1.4 Set up custom navigation and dashboard
    - [ ] 6.1.5 Configure FilamentPHP with STI model support
  - [ ] 6.2 Create User Management Interface
    - [ ] 6.2.1 Create UserResource with STI-aware forms
    - [ ] 6.2.2 Implement user type-specific form fields
    - [ ] 6.2.3 Add bulk user operations (invite, activate, suspend)
    - [ ] 6.2.4 Create user state management interface
    - [ ] 6.2.5 Add user impersonation functionality for admins
  - [ ] 6.3 Create Team Management Interface
    - [ ] 6.3.1 Create TeamResource with hierarchy visualization
    - [ ] 6.3.2 Implement drag-and-drop team reorganization
    - [ ] 6.3.3 Add team membership management interface
    - [ ] 6.3.4 Create team settings and configuration forms
    - [ ] 6.3.5 Add team analytics and reporting dashboard
  - [ ] 6.4 Create Permission Management Interface
    - [ ] 6.4.1 Create PermissionResource with matrix view
    - [ ] 6.4.2 Implement bulk permission assignment interface
    - [ ] 6.4.3 Add role template management
    - [ ] 6.4.4 Create permission inheritance visualization
    - [ ] 6.4.5 Add permission audit trail viewer
  - [ ] 6.5 Create GDPR Compliance Interface
    - [ ] 6.5.1 Create GdprRequestResource for compliance management
    - [ ] 6.5.2 Add one-click data export functionality
    - [ ] 6.5.3 Implement secure data deletion interface
    - [ ] 6.5.4 Create consent management dashboard
    - [ ] 6.5.5 Add compliance reporting and monitoring interface

- [ ] 7.0 API Layer with Authentication
  - [ ] 7.1 Set Up Laravel Sanctum Authentication
    - [ ] 7.1.1 Install and configure Laravel Sanctum
    - [ ] 7.1.2 Create API token management system
    - [ ] 7.1.3 Implement rate limiting (100 req/min users, 1000 req/min SystemUser)
    - [ ] 7.1.4 Add API authentication middleware
    - [ ] 7.1.5 Create API token scopes and permissions
  - [ ] 7.2 Create User Management API Endpoints
    - [ ] 7.2.1 Create UserController with CRUD operations
    - [ ] 7.2.2 Implement user registration and invitation endpoints
    - [ ] 7.2.3 Add user state management endpoints
    - [ ] 7.2.4 Create user profile management endpoints
    - [ ] 7.2.5 Add user search and filtering endpoints
  - [ ] 7.3 Create Team Management API Endpoints
    - [ ] 7.3.1 Create TeamController with hierarchy operations
    - [ ] 7.3.2 Implement team membership management endpoints
    - [ ] 7.3.3 Add team settings and configuration endpoints
    - [ ] 7.3.4 Create team hierarchy navigation endpoints
    - [ ] 7.3.5 Add team analytics and reporting endpoints
  - [ ] 7.4 Create Permission Management API Endpoints
    - [ ] 7.4.1 Create PermissionController for role/permission management
    - [ ] 7.4.2 Implement permission assignment endpoints
    - [ ] 7.4.3 Add permission validation endpoints
    - [ ] 7.4.4 Create permission audit endpoints
    - [ ] 7.4.5 Add bulk permission operation endpoints
  - [ ] 7.5 Create API Documentation and Validation
    - [ ] 7.5.1 Generate OpenAPI/Swagger documentation
    - [ ] 7.5.2 Add comprehensive API request validation
    - [ ] 7.5.3 Implement API error handling and responses
    - [ ] 7.5.4 Create API versioning strategy
    - [ ] 7.5.5 Add API monitoring and analytics

- [ ] 8.0 Testing Suite and Performance Validation
  - [ ] 8.1 Create Unit Tests for Core Models
    - [ ] 8.1.1 Create UserTest with STI behavior validation
    - [ ] 8.1.2 Create TeamTest with hierarchy functionality
    - [ ] 8.1.3 Create PermissionTest with isolation validation
    - [ ] 8.1.4 Create GdprServiceTest with compliance validation
    - [ ] 8.1.5 Create AuditServiceTest with anonymization testing
  - [ ] 8.2 Create Feature Tests for User Workflows
    - [ ] 8.2.1 Create AuthenticationTest with all user types
    - [ ] 8.2.2 Create UserRegistrationTest with multiple methods
    - [ ] 8.2.3 Create TeamMembershipTest with role assignments
    - [ ] 8.2.4 Create PermissionIsolationTest with security validation
    - [ ] 8.2.5 Create GdprComplianceTest with full workflow testing
  - [ ] 8.3 Create Performance Tests
    - [ ] 8.3.1 Create PermissionCacheTest with <10ms validation
    - [ ] 8.3.2 Create AuthenticationPerformanceTest with <100ms validation
    - [ ] 8.3.3 Create TeamHierarchyPerformanceTest with closure table queries
    - [ ] 8.3.4 Create DatabasePerformanceTest with SQLite WAL validation
    - [ ] 8.3.5 Create ConcurrentUserTest with 1000+ user simulation
  - [ ] 8.4 Create Integration Tests
    - [ ] 8.4.1 Create FilamentIntegrationTest for admin interface
    - [ ] 8.4.2 Create ApiIntegrationTest for all endpoints
    - [ ] 8.4.3 Create PackageIntegrationTest for Spatie packages
    - [ ] 8.4.4 Create CacheIntegrationTest for Redis functionality
    - [ ] 8.4.5 Create BackupIntegrationTest for GDPR compliance
  - [ ] 8.5 Set Up Continuous Integration and Quality Assurance
    - [ ] 8.5.1 Configure GitHub Actions for automated testing
    - [ ] 8.5.2 Set up code coverage reporting (target: 95%)
    - [ ] 8.5.3 Add static analysis with PHPStan/Psalm
    - [ ] 8.5.4 Configure automated security scanning
    - [ ] 8.5.5 Set up performance regression testing

---

**Implementation Notes for Junior Developers:**

### Getting Started
1. **Environment Setup**: Ensure PHP 8.4+, Composer, and Redis are installed
2. **Laravel Installation**: Start with fresh Laravel 12.x installation
3. **Package Installation**: Follow task 1.2 to install all required packages
4. **Database Setup**: SQLite will be created automatically with proper configuration

### Key Concepts to Understand
- **Single Table Inheritance (STI)**: All user types share one table but have different behaviors
- **Closure Table**: Efficient way to store and query hierarchical data (teams)
- **Permission Isolation**: Teams don't inherit permissions from parent teams (security feature)
- **GDPR Compliance**: Separate retention for personal data (2 years) vs audit data (7 years)

### Testing Strategy
- **Test-Driven Development**: Write tests before implementing features
- **Coverage Target**: Aim for 95% code coverage
- **Performance Testing**: Validate all response time requirements
- **Security Testing**: Ensure permission isolation works correctly

### Common Gotchas
- **SQLite WAL Mode**: Creates additional files (.wal, .shm) - this is normal
- **STI Queries**: Always filter by 'type' column for performance
- **Permission Caching**: Remember to invalidate cache when permissions change
- **GDPR Anonymization**: Use secure tokens, never reversible hashes

### Debugging Tips
- Use `php artisan telescope:install` for request debugging
- Use `EXPLAIN QUERY PLAN` to optimize SQLite queries
- Monitor Redis cache hit rates for permission performance
- Use Laravel Pulse for application performance monitoring

**Estimated Timeline**: 8-12 weeks following task order
**Priority**: Complete tasks 1-5 before moving to 6-8 (foundation first)
**Testing**: Run tests after each major task completion
