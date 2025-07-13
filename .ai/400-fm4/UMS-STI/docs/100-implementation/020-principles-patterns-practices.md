# 10.2 UMS-STI Principles, Patterns, and Practices

## 10.2.1 Executive Summary

This document comprehensively describes the architectural principles, design patterns, and development practices that form the foundation of the UMS-STI (User Management System with Single Table Inheritance) implementation. These guidelines ensure consistency, maintainability, security, and scalability across the entire system.

## 10.2.2 Core Architectural Principles

### 10.2.2.1 Single Responsibility Principle (SRP)

**Definition:** Each class, method, and module should have one reason to change and one primary responsibility.

**UMS-STI Application:**
```php
// ❌ Violates SRP - User class handling too many responsibilities
class User extends Model
{
    public function authenticate($password) { /* auth logic */ }
    public function sendEmail($message) { /* email logic */ }
    public function calculatePermissions() { /* permission logic */ }
    public function exportGDPRData() { /* GDPR logic */ }
}

// ✅ Follows SRP - Separated responsibilities
class User extends Model
{
    // Only user data and basic relationships
}

class UserAuthenticationService
{
    public function authenticate(User $user, string $password): bool { /* auth logic */ }
}

class UserNotificationService
{
    public function sendEmail(User $user, string $message): void { /* email logic */ }
}

class UserPermissionService
{
    public function calculatePermissions(User $user, ?Team $team = null): Collection { /* permission logic */ }
}

class GDPRDataExportService
{
    public function exportUserData(User $user): array { /* GDPR logic */ }
}
```

### 10.2.2.2 Open/Closed Principle (OCP)

**Definition:** Software entities should be open for extension but closed for modification.

**UMS-STI Application:**
```php
// Base user behavior that's closed for modification
abstract class User extends Model
{
    abstract public function getSpecificCapabilities(): array;

    public function getAllCapabilities(): array
    {
        return array_merge(
            $this->getBaseCapabilities(),
            $this->getSpecificCapabilities()
        );
    }

    private function getBaseCapabilities(): array
    {
        return ['login', 'profile_update', 'password_change'];
    }
}

// Open for extension - new user types
class Employee extends User
{
    public function getSpecificCapabilities(): array
    {
        return ['timesheet_entry', 'leave_request'];
    }
}

class Manager extends User
{
    public function getSpecificCapabilities(): array
    {
        return ['team_management', 'approval_workflow', 'reporting'];
    }
}

class SystemUser extends User
{
    public function getSpecificCapabilities(): array
    {
        return ['system_automation', 'bulk_operations', 'audit_bypass'];
    }
}
```

### 10.2.2.3 Liskov Substitution Principle (LSP)

**Definition:** Objects of a superclass should be replaceable with objects of its subclasses without breaking functionality.

**UMS-STI Application:**
```php
// Base contract that all user types must honor
interface UserInterface
{
    public function canAccessTeam(Team $team): bool;
    public function getDisplayName(): string;
    public function isActive(): bool;
}

// All user types implement the same interface
class Employee extends User implements UserInterface
{
    public function canAccessTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    public function getDisplayName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->trashed();
    }
}

class SystemUser extends User implements UserInterface
{
    public function canAccessTeam(Team $team): bool
    {
        // SystemUser can access all teams
        return true;
    }

    public function getDisplayName(): string
    {
        return "System: {$this->name}";
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

// Client code works with any user type
function processUserAccess(UserInterface $user, Team $team): bool
{
    if (!$user->isActive()) {
        return false;
    }

    return $user->canAccessTeam($team);
}
```

### 10.2.2.4 Interface Segregation Principle (ISP)

**Definition:** Clients should not be forced to depend on interfaces they don't use.

**UMS-STI Application:**
```php
// ❌ Fat interface - forces all users to implement admin methods
interface UserInterface
{
    public function login(): bool;
    public function updateProfile(): bool;
    public function manageTeams(): bool;
    public function accessSystemSettings(): bool;
    public function performBulkOperations(): bool;
}

// ✅ Segregated interfaces
interface AuthenticatableUser
{
    public function login(): bool;
    public function logout(): void;
}

interface ProfileManageableUser
{
    public function updateProfile(array $data): bool;
    public function changePassword(string $newPassword): bool;
}

interface TeamManageableUser
{
    public function manageTeams(): bool;
    public function assignTeamMembers(Team $team, array $users): bool;
}

interface SystemAdministrableUser
{
    public function accessSystemSettings(): bool;
    public function performBulkOperations(): bool;
}

// Users implement only relevant interfaces
class Employee extends User implements AuthenticatableUser, ProfileManageableUser
{
    public function login(): bool { /* implementation */ }
    public function logout(): void { /* implementation */ }
    public function updateProfile(array $data): bool { /* implementation */ }
    public function changePassword(string $newPassword): bool { /* implementation */ }
}

class Manager extends User implements AuthenticatableUser, ProfileManageableUser, TeamManageableUser
{
    // Implements all three interfaces
}

class SystemUser extends User implements SystemAdministrableUser
{
    // Only implements system administration interface
}
```

### 10.2.2.5 Dependency Inversion Principle (DIP)

**Definition:** High-level modules should not depend on low-level modules. Both should depend on abstractions.

**UMS-STI Application:**
```php
// ❌ High-level class depends on concrete implementation
class UserService
{
    private $emailSender;

    public function __construct()
    {
        $this->emailSender = new SMTPEmailSender(); // Concrete dependency
    }

    public function notifyUser(User $user, string $message): void
    {
        $this->emailSender->send($user->email, $message);
    }
}

// ✅ Depends on abstraction
interface EmailSenderInterface
{
    public function send(string $to, string $message): bool;
}

class UserService
{
    private EmailSenderInterface $emailSender;

    public function __construct(EmailSenderInterface $emailSender)
    {
        $this->emailSender = $emailSender;
    }

    public function notifyUser(User $user, string $message): void
    {
        $this->emailSender->send($user->email, $message);
    }
}

// Multiple implementations possible
class SMTPEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $message): bool { /* SMTP implementation */ }
}

class QueuedEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $message): bool { /* Queue implementation */ }
}

class LogEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $message): bool { /* Log for testing */ }
}
```

## 10.2.3 Design Patterns in UMS-STI

### 10.2.3.1 Single Table Inheritance (STI) Pattern

**Purpose:** Store different types of related objects in a single database table while maintaining type-specific behavior.

**Implementation:**
```php
// Base model with STI support
abstract class User extends Model
{
    protected $fillable = ['email', 'first_name', 'last_name', 'type', 'type_specific_data'];

    protected $casts = [
        'type_specific_data' => 'array',
    ];

    // Automatic type resolution
    public function newFromBuilder($attributes = [], $connection = null)
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array) $attributes, true);

        // Resolve to specific type
        if (isset($attributes->type)) {
            $class = $this->getTypeClass($attributes->type);
            if (class_exists($class)) {
                $model = new $class();
                $model->setRawAttributes((array) $attributes, true);
            }
        }

        return $model;
    }

    private function getTypeClass(string $type): string
    {
        return 'App\\Models\\Users\\' . Str::studly($type);
    }
}

// Specific implementations
class Employee extends User
{
    protected $table = 'users';

    public function newQuery()
    {
        return parent::newQuery()->where('type', 'employee');
    }

    public function getEmployeeIdAttribute(): ?string
    {
        return $this->type_specific_data['employee_id'] ?? null;
    }

    public function setEmployeeIdAttribute(string $value): void
    {
        $data = $this->type_specific_data ?? [];
        $data['employee_id'] = $value;
        $this->type_specific_data = $data;
    }
}

class Manager extends User
{
    protected $table = 'users';

    public function newQuery()
    {
        return parent::newQuery()->where('type', 'manager');
    }

    public function managedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_managers');
    }
}
```

### 10.2.3.2 Closure Table Pattern

**Purpose:** Efficiently store and query hierarchical data with fast ancestor/descendant lookups.

**Implementation:**
```php
// Team model with closure table support
class Team extends Model
{
    use HasClosureTable;

    protected $fillable = ['name', 'type', 'description'];

    // Closure table relationship
    public function closures(): HasMany
    {
        return $this->hasMany(TeamClosure::class, 'descendant_id');
    }

    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_closures',
            'descendant_id',
            'ancestor_id'
        )->withPivot('depth');
    }

    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_closures',
            'ancestor_id',
            'descendant_id'
        )->withPivot('depth');
    }

    // Efficient hierarchy queries
    public function getDescendantsAtDepth(int $depth): Collection
    {
        return $this->descendants()->wherePivot('depth', $depth)->get();
    }

    public function getAncestorsToRoot(): Collection
    {
        return $this->ancestors()
            ->orderByPivot('depth', 'desc')
            ->get();
    }
}

// Closure table model
class TeamClosure extends Model
{
    protected $fillable = ['ancestor_id', 'descendant_id', 'depth'];

    public $timestamps = false;

    // Maintain closure table integrity
    public static function insertPath(int $ancestorId, int $descendantId): void
    {
        // Insert direct relationship
        static::create([
            'ancestor_id' => $ancestorId,
            'descendant_id' => $descendantId,
            'depth' => 1,
        ]);

        // Insert indirect relationships
        $ancestorPaths = static::where('descendant_id', $ancestorId)->get();

        foreach ($ancestorPaths as $path) {
            static::create([
                'ancestor_id' => $path->ancestor_id,
                'descendant_id' => $descendantId,
                'depth' => $path->depth + 1,
            ]);
        }
    }
}
```

### 10.2.3.3 Strategy Pattern

**Purpose:** Define a family of algorithms, encapsulate each one, and make them interchangeable.

**Implementation:**
```php
// Permission checking strategies
interface PermissionCheckStrategy
{
    public function hasPermission(User $user, string $permission, ?Team $team = null): bool;
}

class StandardPermissionStrategy implements PermissionCheckStrategy
{
    public function hasPermission(User $user, string $permission, ?Team $team = null): bool
    {
        if ($team) {
            return $user->hasPermissionTo($permission, $team);
        }

        return $user->hasPermissionTo($permission);
    }
}

class SystemUserPermissionStrategy implements PermissionCheckStrategy
{
    public function hasPermission(User $user, string $permission, ?Team $team = null): bool
    {
        // SystemUser has all permissions
        return true;
    }
}

class GuestPermissionStrategy implements PermissionCheckStrategy
{
    private array $allowedPermissions = ['view-public-content'];

    public function hasPermission(User $user, string $permission, ?Team $team = null): bool
    {
        return in_array($permission, $this->allowedPermissions);
    }
}

// Context class
class PermissionChecker
{
    private PermissionCheckStrategy $strategy;

    public function __construct(PermissionCheckStrategy $strategy)
    {
        $this->strategy = $strategy;
    }

    public function setStrategy(PermissionCheckStrategy $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function check(User $user, string $permission, ?Team $team = null): bool
    {
        return $this->strategy->hasPermission($user, $permission, $team);
    }
}

// Usage
$checker = new PermissionChecker(new StandardPermissionStrategy());

if ($user instanceof SystemUser) {
    $checker->setStrategy(new SystemUserPermissionStrategy());
} elseif ($user instanceof Guest) {
    $checker->setStrategy(new GuestPermissionStrategy());
}

$canAccess = $checker->check($user, 'manage-team', $team);
```

### 10.2.3.4 Observer Pattern

**Purpose:** Define a one-to-many dependency between objects so that when one object changes state, all dependents are notified.

**Implementation:**
```php
// User events and observers
class UserObserver
{
    public function created(User $user): void
    {
        // Create default consents
        $this->createDefaultConsents($user);

        // Log creation activity
        $this->logDataProcessingActivity($user, 'create');

        // Send welcome notification
        $this->sendWelcomeNotification($user);
    }

    public function updated(User $user): void
    {
        // Log update activity
        $this->logDataProcessingActivity($user, 'update');

        // Clear permission cache
        $this->clearPermissionCache($user);

        // Notify team members if relevant changes
        $this->notifyTeamOfChanges($user);
    }

    public function deleted(User $user): void
    {
        // Log deletion activity
        $this->logDataProcessingActivity($user, 'delete');

        // Remove from teams
        $this->removeFromAllTeams($user);

        // Revoke all permissions
        $this->revokeAllPermissions($user);
    }

    private function createDefaultConsents(User $user): void
    {
        Consent::create([
            'user_id' => $user->id,
            'purpose' => 'functional',
            'granted' => true,
            'granted_at' => now(),
        ]);
    }

    private function logDataProcessingActivity(User $user, string $activity): void
    {
        DataProcessingActivity::create([
            'activity_type' => $activity,
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
            'data_categories' => ['personal'],
            'purpose' => 'user_management',
            'legal_basis' => 'contract',
            'performed_at' => now(),
        ]);
    }
}

// Register observer
User::observe(UserObserver::class);
```

### 10.2.3.5 Factory Pattern

**Purpose:** Create objects without specifying the exact class to create.

**Implementation:**
```php
// User factory for different types
class UserFactory
{
    public static function create(string $type, array $attributes = []): User
    {
        $class = self::getClassForType($type);

        if (!class_exists($class)) {
            throw new InvalidArgumentException("Unknown user type: {$type}");
        }

        $user = new $class();
        $user->fill(array_merge($attributes, ['type' => $type]));

        return $user;
    }

    public static function createFromArray(array $userData): User
    {
        $type = $userData['type'] ?? 'employee';

        return self::create($type, $userData);
    }

    private static function getClassForType(string $type): string
    {
        $typeMap = [
            'employee' => Employee::class,
            'manager' => Manager::class,
            'admin' => Admin::class,
            'system' => SystemUser::class,
            'guest' => Guest::class,
        ];

        return $typeMap[$type] ?? Employee::class;
    }
}

// Team factory with hierarchy support
class TeamFactory
{
    public static function createWithHierarchy(array $teamData, ?Team $parent = null): Team
    {
        $team = Team::create($teamData);

        if ($parent) {
            self::attachToParent($team, $parent);
        }

        return $team;
    }

    public static function createOrganizationalStructure(array $structure): Team
    {
        $rootTeam = Team::create($structure['root']);

        if (isset($structure['children'])) {
            foreach ($structure['children'] as $childData) {
                self::createChildTeam($childData, $rootTeam);
            }
        }

        return $rootTeam;
    }

    private static function createChildTeam(array $teamData, Team $parent): Team
    {
        $team = self::createWithHierarchy($teamData, $parent);

        if (isset($teamData['children'])) {
            foreach ($teamData['children'] as $childData) {
                self::createChildTeam($childData, $team);
            }
        }

        return $team;
    }

    private static function attachToParent(Team $child, Team $parent): void
    {
        // Create closure table entries
        TeamClosure::insertPath($parent->id, $child->id);

        // Create self-reference
        TeamClosure::create([
            'ancestor_id' => $child->id,
            'descendant_id' => $child->id,
            'depth' => 0,
        ]);
    }
}
```

## 10.2.4 Development Practices

### 10.2.4.1 Test-Driven Development (TDD)

**Red-Green-Refactor Cycle:**
```php
// 1. RED - Write failing test
test('employee can be created with specific data', function () {
    $employeeData = [
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'employee_id' => 'EMP001',
    ];

    $employee = UserFactory::create('employee', $employeeData);

    expect($employee)->toBeInstanceOf(Employee::class);
    expect($employee->employee_id)->toBe('EMP001');
    expect($employee->type)->toBe('employee');
});

// 2. GREEN - Write minimal code to pass
class Employee extends User
{
    protected $table = 'users';

    public function getEmployeeIdAttribute(): ?string
    {
        return $this->type_specific_data['employee_id'] ?? null;
    }

    public function setEmployeeIdAttribute(string $value): void
    {
        $data = $this->type_specific_data ?? [];
        $data['employee_id'] = $value;
        $this->type_specific_data = $data;
    }
}

// 3. REFACTOR - Improve code structure
trait HasTypeSpecificData
{
    public function getTypeSpecificAttribute(string $key, $default = null)
    {
        return $this->type_specific_data[$key] ?? $default;
    }

    public function setTypeSpecificAttribute(string $key, $value): void
    {
        $data = $this->type_specific_data ?? [];
        $data[$key] = $value;
        $this->type_specific_data = $data;
    }
}

class Employee extends User
{
    use HasTypeSpecificData;

    public function getEmployeeIdAttribute(): ?string
    {
        return $this->getTypeSpecificAttribute('employee_id');
    }

    public function setEmployeeIdAttribute(string $value): void
    {
        $this->setTypeSpecificAttribute('employee_id', $value);
    }
}
```

### 10.2.4.2 GDPR-First Development

**Privacy by Design:**
```php
// Data minimization principle
class UserRegistrationService
{
    public function register(array $userData): User
    {
        // Only collect necessary data
        $minimizedData = $this->minimizeData($userData);

        // Create user with consent tracking
        $user = User::create($minimizedData);

        // Record consent
        $this->recordConsent($user, 'registration');

        // Log processing activity
        $this->logProcessingActivity($user, 'create', 'registration');

        return $user;
    }

    private function minimizeData(array $userData): array
    {
        $allowedFields = ['email', 'first_name', 'last_name', 'type'];

        return array_intersect_key($userData, array_flip($allowedFields));
    }

    private function recordConsent(User $user, string $purpose): void
    {
        Consent::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'granted' => true,
            'granted_at' => now(),
        ]);
    }

    private function logProcessingActivity(User $user, string $activity, string $purpose): void
    {
        DataProcessingActivity::create([
            'activity_type' => $activity,
            'data_subject_id' => $user->id,
            'data_subject_type' => get_class($user),
            'data_categories' => ['personal'],
            'purpose' => $purpose,
            'legal_basis' => 'consent',
            'performed_at' => now(),
        ]);
    }
}
```

### 10.2.4.3 Security-First Development

**Defense in Depth:**
```php
// Multiple layers of security
class SecureUserService
{
    public function updateUser(User $user, array $data, User $actor): User
    {
        // 1. Authentication check
        if (!$actor->isAuthenticated()) {
            throw new UnauthenticatedException();
        }

        // 2. Authorization check
        if (!$this->canUpdateUser($actor, $user)) {
            throw new UnauthorizedException();
        }

        // 3. Input validation
        $validatedData = $this->validateInput($data);

        // 4. Data sanitization
        $sanitizedData = $this->sanitizeData($validatedData);

        // 5. Business rule validation
        $this->validateBusinessRules($user, $sanitizedData);

        // 6. Update with audit trail
        return $this->performSecureUpdate($user, $sanitizedData, $actor);
    }

    private function canUpdateUser(User $actor, User $target): bool
    {
        // Self-update allowed
        if ($actor->id === $target->id) {
            return true;
        }

        // Check admin permissions
        if ($actor->hasPermissionTo('update-users')) {
            return true;
        }

        // Check team management permissions
        $sharedTeams = $actor->teams()->whereIn('teams.id', $target->teams()->pluck('teams.id'))->exists();

        return $sharedTeams && $actor->hasPermissionTo('manage-team-members');
    }

    private function validateInput(array $data): array
    {
        return validator($data, [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email',
        ])->validated();
    }

    private function sanitizeData(array $data): array
    {
        return array_map(function ($value) {
            return is_string($value) ? strip_tags(trim($value)) : $value;
        }, $data);
    }

    private function performSecureUpdate(User $user, array $data, User $actor): User
    {
        DB::transaction(function () use ($user, $data, $actor) {
            $user->update($data);

            // Log the update
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'user_update',
                'target_type' => get_class($user),
                'target_id' => $user->id,
                'changes' => $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return $user->fresh();
    }
}
```

### 10.2.4.4 Performance-First Development

**Optimization Strategies:**
```php
// Caching strategy
class CachedPermissionService
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function getUserPermissions(User $user, ?Team $team = null): Collection
    {
        $cacheKey = $this->getCacheKey($user, $team);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $team) {
            return $this->calculatePermissions($user, $team);
        });
    }

    public function invalidateUserPermissions(User $user): void
    {
        // Clear all permission caches for user
        $pattern = "permissions:user:{$user->id}:*";
        $this->clearCachePattern($pattern);
    }

    private function getCacheKey(User $user, ?Team $team): string
    {
        $teamId = $team ? $team->id : 'global';
        return "permissions:user:{$user->id}:team:{$teamId}";
    }

    private function calculatePermissions(User $user, ?Team $team): Collection
    {
        if ($team) {
            return $user->getPermissionsViaRoles()
                ->merge($user->getDirectPermissions())
                ->where('team_id', $team->id);
        }

        return $user->getAllPermissions();
    }
}

// Query optimization
class OptimizedTeamService
{
    public function getTeamHierarchy(Team $team): Collection
    {
        // Single query to get entire hierarchy
        return Team::with(['descendants' => function ($query) {
            $query->orderBy('team_closures.depth');
        }])
        ->where('id', $team->id)
        ->first()
        ->descendants;
    }

    public function getTeamMembersWithRoles(Team $team): Collection
    {
        // Optimized query with eager loading
        return $team->members()
            ->with(['roles', 'permissions'])
            ->select(['users.*', 'team_memberships.role', 'team_memberships.joined_at'])
            ->get();
    }
}
```

### 5. Documentation-Driven Development

**Living Documentation:**
```php
/**
 * User Management Service
 * 
 * Handles all user-related operations including creation, updates,
 * permission management, and GDPR compliance.
 * 
 * @example
 * ```php
 * $service = new UserManagementService();
 * $user = $service->createUser([
 *     'email' => 'user@example.com',
 *     'type' => 'employee'
 * ]);
 * ```
 */
class UserManagementService
{
    /**
     * Create a new user with proper consent tracking
     * 
     * @param array $userData User data including email, name, and type
     * @param string $legalBasis GDPR legal basis for processing
     * @return User The created user instance
     * 
     * @throws ValidationException When user data is invalid
     * @throws ConsentRequiredException When required consent is missing
     * 
     * @example
     * ```php
     * $user = $service->createUser([
     *     'email' => 'john@example.com',
     *     'first_name' => 'John',
     *     'last_name' => 'Doe',
     *     'type' => 'employee'
     * ], 'contract');
     * ```
     */
    public function createUser(array $userData, string $legalBasis = 'consent'): User
    {
        // Implementation with comprehensive error handling
    }

    /**
     * Export all user data for GDPR compliance
     * 
     * Exports all personal data associated with a user across
     * all systems and teams, formatted for data portability.
     * 
     * @param User $user The user whose data to export
     * @return array Complete user data export
     * 
     * @throws GDPRExportException When export fails
     * 
     * @see https://gdpr.eu/article-20-right-to-data-portability/
     */
    public function exportUserData(User $user): array
    {
        // Implementation with comprehensive data collection
    }
}
```

## 10.2.5 Quality Assurance Practices

### 10.2.5.1 Automated Testing Strategy

**Test Pyramid Implementation:**
```php
// Unit Tests (70%)
test('employee can calculate overtime hours', function () {
    $employee = Employee::factory()->create();
    $timesheet = Timesheet::factory()->create([
        'user_id' => $employee->id,
        'hours_worked' => 45,
        'standard_hours' => 40,
    ]);

    expect($employee->calculateOvertimeHours($timesheet))->toBe(5.0);
});

// Integration Tests (20%)
test('user permission system integrates with team hierarchy', function () {
    $organization = Team::factory()->create(['type' => 'organization']);
    $department = Team::factory()->create(['type' => 'department']);
    $project = Team::factory()->create(['type' => 'project']);

    // Create hierarchy
    TeamClosure::insertPath($organization->id, $department->id);
    TeamClosure::insertPath($department->id, $project->id);

    $manager = Manager::factory()->create();
    $manager->givePermissionTo('manage-team', $department);

    // Manager should have access to child teams
    expect($manager->hasPermissionTo('manage-team', $project))->toBeTrue();
});

// End-to-End Tests (10%)
test('complete user lifecycle with GDPR compliance', function () {
    // User registration
    $userData = [
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'type' => 'employee',
    ];

    $user = app(UserManagementService::class)->createUser($userData);

    // Verify consent was recorded
    expect($user->consents)->not->toBeEmpty();

    // User joins team
    $team = Team::factory()->create();
    $team->addMember($user, 'developer');

    // User requests data export
    $exportData = app(GDPRService::class)->exportUserData($user);
    expect($exportData)->toHaveKey('personal_information');
    expect($exportData)->toHaveKey('team_memberships');

    // User requests deletion
    app(GDPRService::class)->deleteUserData($user);

    // Verify user is soft deleted and audit trail exists
    expect($user->fresh()->trashed())->toBeTrue();
    expect(DataProcessingActivity::forDataSubject($user)->count())->toBeGreaterThan(0);
});
```

### 10.2.5.2 Code Quality Standards

**Static Analysis Configuration:**
```php
// phpstan.neon
parameters:
    level: 8
    paths:
        - app
        - tests
    excludePaths:
        - app/Console/Kernel.php
        - app/Http/Kernel.php
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
    ignoreErrors:
        - '#Call to an undefined method Illuminate\\Database\\Eloquent\\Builder#'

// rector.php
use Rector\Config\RectorConfig;
use Rector\Laravel\Set\LaravelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/app',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->sets([
        LaravelSetList::LARAVEL_100,
    ]);
};
```

### 10.2.5.3 Security Standards

**Security Checklist:**
```php
// Security validation service
class SecurityValidator
{
    public function validateUserInput(array $input): array
    {
        // 1. Input validation
        $validated = $this->validateStructure($input);

        // 2. XSS prevention
        $sanitized = $this->sanitizeInput($validated);

        // 3. SQL injection prevention (handled by Eloquent)
        // 4. CSRF protection (handled by middleware)

        return $sanitized;
    }

    public function validatePermissionAccess(User $user, string $permission, ?Team $team = null): bool
    {
        // 1. Authentication check
        if (!$user->isAuthenticated()) {
            return false;
        }

        // 2. Permission check with team isolation
        if ($team && !$user->hasPermissionTo($permission, $team)) {
            return false;
        }

        // 3. Rate limiting check
        if ($this->isRateLimited($user, $permission)) {
            return false;
        }

        return true;
    }

    private function isRateLimited(User $user, string $permission): bool
    {
        $key = "permission_check:{$user->id}:{$permission}";
        $attempts = Cache::get($key, 0);

        if ($attempts > 100) { // 100 permission checks per minute
            return true;
        }

        Cache::put($key, $attempts + 1, 60);
        return false;
    }
}
```

## Conclusion

These principles, patterns, and practices form the foundation of a robust, maintainable, and scalable UMS-STI implementation. By following these guidelines, developers can ensure:

1. **Code Quality:** High maintainability and readability
2. **Security:** Defense-in-depth approach to system security
3. **Performance:** Optimized for scale and efficiency
4. **Compliance:** GDPR-first approach to data protection
5. **Testing:** Comprehensive test coverage and quality assurance

### Key Takeaways

1. **SOLID Principles:** Foundation for maintainable object-oriented design
2. **Design Patterns:** Proven solutions for common architectural challenges
3. **TDD Approach:** Quality-first development methodology
4. **Security Focus:** Security considerations in every development decision
5. **Performance Optimization:** Proactive approach to system performance
6. **Documentation:** Living documentation that evolves with the code

These practices ensure that the UMS-STI system remains robust, secure, and maintainable throughout its lifecycle while meeting all business and regulatory requirements.
