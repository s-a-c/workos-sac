# CQRS Implementation for UMS-STI

## Executive Summary

This document provides a comprehensive guide to implementing Command Query Responsibility Segregation (CQRS) patterns within the event-sourced User Management System with Single Table Inheritance (UMS-STI). CQRS separates read and write operations into distinct models, enabling optimized performance, scalability, and maintainability. The implementation leverages Laravel's architecture with `spatie/laravel-event-sourcing`, creating separate command and query sides that communicate through domain events, while maintaining eventual consistency between write and read models.

**Key Benefits**: Optimized read/write performance, independent scaling of command and query sides, simplified complex business logic, and enhanced system maintainability through clear separation of concerns.

## Learning Objectives

After completing this document, readers will understand:

- **CQRS Fundamentals**: Core principles of command and query separation
- **Command Side Design**: Aggregate-based write models and command handlers
- **Query Side Design**: Projection-based read models and query handlers
- **Consistency Models**: Trade-offs between eventual and strong consistency
- **Handler Patterns**: Implementation of command and query handlers
- **Data Flow**: How commands, events, and queries interact in the system
- **Performance Optimization**: Strategies for optimizing both command and query operations

## Prerequisite Knowledge

Before implementing CQRS patterns, ensure familiarity with:

- **Event-Sourcing Architecture**: Understanding of event stores, aggregates, and domain events
- **Domain-Driven Design**: Aggregates, entities, value objects, and bounded contexts
- **Laravel Framework**: Service providers, dependency injection, and queue systems
- **Database Design**: Understanding of read/write optimization and indexing strategies
- **UMS-STI Domain**: User types, team hierarchies, and permission systems
- **Eventual Consistency**: Understanding of asynchronous data synchronization

## Architectural Overview

### CQRS Foundation

CQRS separates the responsibility for handling commands (writes) from queries (reads), allowing each side to be optimized for its specific purpose:

```
┌─────────────────────────────────────────────────────────────┐
│                    CQRS Architecture                        │
├─────────────────────────────────────────────────────────────┤
│  Commands → Command Handlers → Aggregates → Events         │
│                                    ↓                        │
│  Events → Event Store → Projectors → Read Models           │
│                                    ↓                        │
│  Queries → Query Handlers → Read Models → Responses        │
└─────────────────────────────────────────────────────────────┘
```

### Command Side (Write Model)

The command side handles all write operations through:
- **Commands**: Intent to change system state
- **Command Handlers**: Business logic execution
- **Aggregates**: Domain model enforcement
- **Events**: State change notifications

### Query Side (Read Model)

The query side handles all read operations through:
- **Queries**: Intent to retrieve data
- **Query Handlers**: Data retrieval logic
- **Projections**: Optimized read models
- **Views**: Formatted data responses

## Core Concepts Deep Dive

### Command Design Patterns

Commands represent the intent to change system state and encapsulate all necessary data for the operation:

```php
// Base Command Interface
interface Command
{
    public function getAggregateId(): string;
    public function validate(): array;
    public function getMetadata(): array;
}

// Abstract Base Command
abstract class BaseCommand implements Command
{
    protected array $metadata = [];
    
    public function __construct(array $metadata = [])
    {
        $this->metadata = array_merge([
            'command_id' => Str::uuid(),
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'ip_address' => request()->ip(),
        ], $metadata);
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    abstract public function validate(): array;
}
```

### User Domain Commands

**User Registration Commands**:
```php
class RegisterUserCommand extends BaseCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly UserType $userType,
        public readonly array $profileData,
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->userId;
    }
    
    public function validate(): array
    {
        return [
            'email' => ['required', 'email', 'unique:users,email'],
            'user_type' => ['required', 'in:Standard,Admin,Guest,SystemUser'],
            'profile_data.name' => ['required', 'string', 'max:255'],
        ];
    }
}

class ActivateUserCommand extends BaseCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly string $activatedBy,
        public readonly string $activationMethod = 'admin',
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->userId;
    }
    
    public function validate(): array
    {
        return [
            'activation_method' => ['required', 'in:email,admin,auto'],
        ];
    }
}

class UpdateUserProfileCommand extends BaseCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly array $profileUpdates,
        public readonly string $updatedBy,
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->userId;
    }
    
    public function validate(): array
    {
        return [
            'profile_updates' => ['required', 'array'],
            'profile_updates.name' => ['sometimes', 'string', 'max:255'],
            'profile_updates.bio' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
```

**User Permission Commands**:
```php
class GrantUserPermissionCommand extends BaseCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly string $permission,
        public readonly string $grantedBy,
        public readonly array $context = [],
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->userId;
    }
    
    public function validate(): array
    {
        return [
            'permission' => ['required', 'string', 'exists:permissions,name'],
            'context.team_id' => ['sometimes', 'string', 'exists:teams,id'],
        ];
    }
}

class RevokeUserPermissionCommand extends BaseCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly string $permission,
        public readonly string $revokedBy,
        public readonly string $reason,
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->userId;
    }
    
    public function validate(): array
    {
        return [
            'permission' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
```

### Team Domain Commands

**Team Management Commands**:
```php
class CreateTeamCommand extends BaseCommand
{
    public function __construct(
        public readonly string $teamId,
        public readonly string $name,
        public readonly ?string $parentTeamId,
        public readonly string $createdBy,
        public readonly array $settings = [],
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->teamId;
    }
    
    public function validate(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'parent_team_id' => ['nullable', 'string', 'exists:teams,id'],
            'settings' => ['array'],
        ];
    }
}

class AddTeamMemberCommand extends BaseCommand
{
    public function __construct(
        public readonly string $teamId,
        public readonly string $userId,
        public readonly TeamRole $role,
        public readonly string $addedBy,
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->teamId;
    }
    
    public function validate(): array
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,id'],
            'role' => ['required', 'in:member,leader,admin'],
        ];
    }
}

class ChangeTeamHierarchyCommand extends BaseCommand
{
    public function __construct(
        public readonly string $teamId,
        public readonly ?string $newParentId,
        public readonly string $changedBy,
        array $metadata = []
    ) {
        parent::__construct($metadata);
    }
    
    public function getAggregateId(): string
    {
        return $this->teamId;
    }
    
    public function validate(): array
    {
        return [
            'new_parent_id' => ['nullable', 'string', 'exists:teams,id'],
        ];
    }
}
```

### Command Handlers Implementation

Command handlers contain the business logic for processing commands and coordinating with aggregates:

```php
// Base Command Handler
abstract class CommandHandler
{
    protected EventStore $eventStore;
    protected CommandValidator $validator;
    
    public function __construct(EventStore $eventStore, CommandValidator $validator)
    {
        $this->eventStore = $eventStore;
        $this->validator = $validator;
    }
    
    protected function validateCommand(Command $command): void
    {
        $rules = $command->validate();
        $data = $this->extractCommandData($command);
        
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new CommandValidationException($validator->errors());
        }
    }
    
    abstract protected function extractCommandData(Command $command): array;
    abstract public function handle(Command $command): void;
}

// User Command Handlers
class RegisterUserCommandHandler extends CommandHandler
{
    public function handle(RegisterUserCommand $command): void
    {
        $this->validateCommand($command);
        
        // Check if user already exists
        $existingAggregate = $this->tryRetrieveAggregate($command->userId);
        if ($existingAggregate) {
            throw new UserAlreadyExistsException();
        }
        
        // Create new user aggregate
        $userAggregate = UserAggregate::register(
            UserId::fromString($command->userId),
            Email::fromString($command->email),
            $command->userType,
            $command->profileData
        );
        
        $this->eventStore->append($userAggregate);
    }
    
    protected function extractCommandData(Command $command): array
    {
        return [
            'email' => $command->email,
            'user_type' => $command->userType->value,
            'profile_data' => $command->profileData,
        ];
    }
    
    private function tryRetrieveAggregate(string $userId): ?UserAggregate
    {
        try {
            return UserAggregate::retrieve($userId);
        } catch (AggregateNotFoundException $e) {
            return null;
        }
    }
}

class ActivateUserCommandHandler extends CommandHandler
{
    public function handle(ActivateUserCommand $command): void
    {
        $this->validateCommand($command);
        
        $userAggregate = UserAggregate::retrieve($command->userId);
        
        $userAggregate->activate(
            UserId::fromString($command->activatedBy),
            $command->activationMethod
        );
        
        $this->eventStore->append($userAggregate);
    }
    
    protected function extractCommandData(Command $command): array
    {
        return [
            'activation_method' => $command->activationMethod,
        ];
    }
}

class UpdateUserProfileCommandHandler extends CommandHandler
{
    public function handle(UpdateUserProfileCommand $command): void
    {
        $this->validateCommand($command);
        
        $userAggregate = UserAggregate::retrieve($command->userId);
        
        $userAggregate->updateProfile(
            $command->profileUpdates,
            UserId::fromString($command->updatedBy)
        );
        
        $this->eventStore->append($userAggregate);
    }
    
    protected function extractCommandData(Command $command): array
    {
        return [
            'profile_updates' => $command->profileUpdates,
        ];
    }
}

// Team Command Handlers
class CreateTeamCommandHandler extends CommandHandler
{
    public function handle(CreateTeamCommand $command): void
    {
        $this->validateCommand($command);
        
        // Validate parent team exists if specified
        if ($command->parentTeamId) {
            $this->validateParentTeamExists($command->parentTeamId);
        }
        
        $teamAggregate = TeamAggregate::create(
            TeamId::fromString($command->teamId),
            TeamName::fromString($command->name),
            $command->parentTeamId ? TeamId::fromString($command->parentTeamId) : null,
            UserId::fromString($command->createdBy),
            $command->settings
        );
        
        $this->eventStore->append($teamAggregate);
    }
    
    protected function extractCommandData(Command $command): array
    {
        return [
            'name' => $command->name,
            'parent_team_id' => $command->parentTeamId,
            'settings' => $command->settings,
        ];
    }
    
    private function validateParentTeamExists(string $parentTeamId): void
    {
        try {
            TeamAggregate::retrieve($parentTeamId);
        } catch (AggregateNotFoundException $e) {
            throw new ParentTeamNotFoundException();
        }
    }
}

class AddTeamMemberCommandHandler extends CommandHandler
{
    public function handle(AddTeamMemberCommand $command): void
    {
        $this->validateCommand($command);
        
        // Validate user exists
        $this->validateUserExists($command->userId);
        
        $teamAggregate = TeamAggregate::retrieve($command->teamId);
        
        $teamAggregate->addMember(
            UserId::fromString($command->userId),
            $command->role,
            UserId::fromString($command->addedBy)
        );
        
        $this->eventStore->append($teamAggregate);
    }
    
    protected function extractCommandData(Command $command): array
    {
        return [
            'user_id' => $command->userId,
            'role' => $command->role->value,
        ];
    }
    
    private function validateUserExists(string $userId): void
    {
        try {
            UserAggregate::retrieve($userId);
        } catch (AggregateNotFoundException $e) {
            throw new UserNotFoundException();
        }
    }
}
```

### Query Design Patterns

Queries represent the intent to retrieve data and are handled by optimized read models:

```php
// Base Query Interface
interface Query
{
    public function getFilters(): array;
    public function getPagination(): ?PaginationOptions;
    public function getSorting(): ?SortingOptions;
}

// Abstract Base Query
abstract class BaseQuery implements Query
{
    protected array $filters = [];
    protected ?PaginationOptions $pagination = null;
    protected ?SortingOptions $sorting = null;
    
    public function getFilters(): array
    {
        return $this->filters;
    }
    
    public function getPagination(): ?PaginationOptions
    {
        return $this->pagination;
    }
    
    public function getSorting(): ?SortingOptions
    {
        return $this->sorting;
    }
    
    public function withPagination(int $page, int $perPage): static
    {
        $this->pagination = new PaginationOptions($page, $perPage);
        return $this;
    }
    
    public function withSorting(string $field, string $direction = 'asc'): static
    {
        $this->sorting = new SortingOptions($field, $direction);
        return $this;
    }
}
```

### User Domain Queries

```php
class GetUserByIdQuery extends BaseQuery
{
    public function __construct(
        public readonly string $userId,
        public readonly bool $includePermissions = false,
        public readonly bool $includeTeams = false
    ) {
        $this->filters = [
            'user_id' => $userId,
            'include_permissions' => $includePermissions,
            'include_teams' => $includeTeams,
        ];
    }
}

class GetUsersByTypeQuery extends BaseQuery
{
    public function __construct(
        public readonly UserType $userType,
        public readonly ?UserState $state = null,
        public readonly ?string $searchTerm = null
    ) {
        $this->filters = array_filter([
            'user_type' => $userType->value,
            'state' => $state?->value,
            'search_term' => $searchTerm,
        ]);
    }
}

class GetUserPermissionsQuery extends BaseQuery
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $context = null
    ) {
        $this->filters = array_filter([
            'user_id' => $userId,
            'context' => $context,
        ]);
    }
}

class SearchUsersQuery extends BaseQuery
{
    public function __construct(
        public readonly string $searchTerm,
        public readonly ?UserType $userType = null,
        public readonly ?UserState $state = null,
        public readonly array $teamIds = []
    ) {
        $this->filters = array_filter([
            'search_term' => $searchTerm,
            'user_type' => $userType?->value,
            'state' => $state?->value,
            'team_ids' => $teamIds,
        ]);
    }
}
```

### Team Domain Queries

```php
class GetTeamByIdQuery extends BaseQuery
{
    public function __construct(
        public readonly string $teamId,
        public readonly bool $includeMembers = false,
        public readonly bool $includeHierarchy = false
    ) {
        $this->filters = [
            'team_id' => $teamId,
            'include_members' => $includeMembers,
            'include_hierarchy' => $includeHierarchy,
        ];
    }
}

class GetTeamHierarchyQuery extends BaseQuery
{
    public function __construct(
        public readonly ?string $rootTeamId = null,
        public readonly int $maxDepth = 10
    ) {
        $this->filters = array_filter([
            'root_team_id' => $rootTeamId,
            'max_depth' => $maxDepth,
        ]);
    }
}

class GetTeamMembersQuery extends BaseQuery
{
    public function __construct(
        public readonly string $teamId,
        public readonly ?TeamRole $role = null,
        public readonly bool $includeInherited = false
    ) {
        $this->filters = array_filter([
            'team_id' => $teamId,
            'role' => $role?->value,
            'include_inherited' => $includeInherited,
        ]);
    }
}

class GetUserTeamsQuery extends BaseQuery
{
    public function __construct(
        public readonly string $userId,
        public readonly ?TeamRole $role = null,
        public readonly bool $includeHierarchy = false
    ) {
        $this->filters = array_filter([
            'user_id' => $userId,
            'role' => $role?->value,
            'include_hierarchy' => $includeHierarchy,
        ]);
    }
}
```

### Query Handlers Implementation

Query handlers retrieve data from optimized read models:

```php
// Base Query Handler
abstract class QueryHandler
{
    protected Connection $readConnection;
    protected CacheManager $cache;
    
    public function __construct(Connection $readConnection, CacheManager $cache)
    {
        $this->readConnection = $readConnection;
        $this->cache = $cache;
    }
    
    abstract public function handle(Query $query): QueryResult;
    
    protected function getCacheKey(Query $query): string
    {
        return sprintf(
            '%s:%s',
            class_basename($query),
            md5(serialize($query))
        );
    }
    
    protected function applySorting(Builder $queryBuilder, ?SortingOptions $sorting): Builder
    {
        if (!$sorting) {
            return $queryBuilder;
        }
        
        return $queryBuilder->orderBy($sorting->field, $sorting->direction);
    }
    
    protected function applyPagination(Builder $queryBuilder, ?PaginationOptions $pagination): Builder
    {
        if (!$pagination) {
            return $queryBuilder;
        }
        
        return $queryBuilder
            ->offset(($pagination->page - 1) * $pagination->perPage)
            ->limit($pagination->perPage);
    }
}

// User Query Handlers
class GetUserByIdQueryHandler extends QueryHandler
{
    public function handle(GetUserByIdQuery $query): QueryResult
    {
        $cacheKey = $this->getCacheKey($query);
        
        return $this->cache->remember($cacheKey, 300, function () use ($query) {
            $queryBuilder = $this->readConnection->table('user_projections')
                ->where('id', $query->userId);
            
            if ($query->includePermissions) {
                $queryBuilder->with('permissions');
            }
            
            if ($query->includeTeams) {
                $queryBuilder->with('teams');
            }
            
            $user = $queryBuilder->first();
            
            if (!$user) {
                throw new UserNotFoundException();
            }
            
            return new QueryResult($user);
        });
    }
}

class GetUsersByTypeQueryHandler extends QueryHandler
{
    public function handle(GetUsersByTypeQuery $query): QueryResult
    {
        $cacheKey = $this->getCacheKey($query);
        
        return $this->cache->remember($cacheKey, 60, function () use ($query) {
            $queryBuilder = $this->readConnection->table('user_projections')
                ->where('user_type', $query->userType->value);
            
            if ($query->state) {
                $queryBuilder->where('state', $query->state->value);
            }
            
            if ($query->searchTerm) {
                $queryBuilder->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query->searchTerm}%")
                      ->orWhere('email', 'LIKE', "%{$query->searchTerm}%");
                });
            }
            
            $queryBuilder = $this->applySorting($queryBuilder, $query->getSorting());
            $queryBuilder = $this->applyPagination($queryBuilder, $query->getPagination());
            
            $users = $queryBuilder->get();
            $total = $this->getTotalCount($query);
            
            return new QueryResult($users, $total);
        });
    }
    
    private function getTotalCount(GetUsersByTypeQuery $query): int
    {
        $queryBuilder = $this->readConnection->table('user_projections')
            ->where('user_type', $query->userType->value);
        
        if ($query->state) {
            $queryBuilder->where('state', $query->state->value);
        }
        
        if ($query->searchTerm) {
            $queryBuilder->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query->searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$query->searchTerm}%");
            });
        }
        
        return $queryBuilder->count();
    }
}

class GetUserPermissionsQueryHandler extends QueryHandler
{
    public function handle(GetUserPermissionsQuery $query): QueryResult
    {
        $cacheKey = $this->getCacheKey($query);
        
        return $this->cache->remember($cacheKey, 300, function () use ($query) {
            $queryBuilder = $this->readConnection->table('user_permission_projections')
                ->where('user_id', $query->userId);
            
            if ($query->context) {
                $queryBuilder->where('context', $query->context);
            }
            
            $permissions = $queryBuilder->get();
            
            return new QueryResult($permissions);
        });
    }
}

// Team Query Handlers
class GetTeamByIdQueryHandler extends QueryHandler
{
    public function handle(GetTeamByIdQuery $query): QueryResult
    {
        $cacheKey = $this->getCacheKey($query);
        
        return $this->cache->remember($cacheKey, 300, function () use ($query) {
            $queryBuilder = $this->readConnection->table('team_projections')
                ->where('id', $query->teamId);
            
            if ($query->includeMembers) {
                $queryBuilder->with('members');
            }
            
            if ($query->includeHierarchy) {
                $queryBuilder->with('hierarchy');
            }
            
            $team = $queryBuilder->first();
            
            if (!$team) {
                throw new TeamNotFoundException();
            }
            
            return new QueryResult($team);
        });
    }
}

class GetTeamHierarchyQueryHandler extends QueryHandler
{
    public function handle(GetTeamHierarchyQuery $query): QueryResult
    {
        $cacheKey = $this->getCacheKey($query);
        
        return $this->cache->remember($cacheKey, 600, function () use ($query) {
            $queryBuilder = $this->readConnection->table('team_hierarchy_projections');
            
            if ($query->rootTeamId) {
                $queryBuilder->where('ancestor_id', $query->rootTeamId);
            }
            
            $queryBuilder->where('depth', '<=', $query->maxDepth)
                         ->orderBy('depth')
                         ->orderBy('descendant_name');
            
            $hierarchy = $queryBuilder->get();
            
            return new QueryResult($this->buildHierarchyTree($hierarchy));
        });
    }
    
    private function buildHierarchyTree(Collection $hierarchy): array
    {
        $tree = [];
        $lookup = [];
        
        foreach ($hierarchy as $node) {
            $lookup[$node->descendant_id] = $node;
            
            if ($node->depth === 0) {
                $tree[$node->descendant_id] = $node;
                $tree[$node->descendant_id]->children = [];
            } else {
                $parentId = $this->findParentId($node, $hierarchy);
                if (isset($lookup[$parentId])) {
                    $lookup[$parentId]->children[] = $node;
                }
            }
        }
        
        return array_values($tree);
    }
    
    private function findParentId($node, Collection $hierarchy): ?string
    {
        return $hierarchy
            ->where('descendant_id', $node->descendant_id)
            ->where('depth', $node->depth - 1)
            ->first()?->ancestor_id;
    }
}
```

## Implementation Principles & Patterns

### Command Bus Pattern

Implement a command bus for decoupled command handling:

```php
interface CommandBus
{
    public function dispatch(Command $command): void;
    public function registerHandler(string $commandClass, string $handlerClass): void;
}

class SynchronousCommandBus implements CommandBus
{
    private array $handlers = [];
    private Container $container;
    
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    
    public function dispatch(Command $command): void
    {
        $commandClass = get_class($command);
        
        if (!isset($this->handlers[$commandClass])) {
            throw new CommandHandlerNotFoundException($commandClass);
        }
        
        $handlerClass = $this->handlers[$commandClass];
        $handler = $this->container->make($handlerClass);
        
        $handler->handle($command);
    }
    
    public function registerHandler(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }
}

class AsynchronousCommandBus implements CommandBus
{
    private SynchronousCommandBus $syncBus;
    private QueueManager $queue;
    
    public function __construct(SynchronousCommandBus $syncBus, QueueManager $queue)
    {
        $this->syncBus = $syncBus;
        $this->queue = $queue;
    }
    
    public function dispatch(Command $command): void
    {
        if ($this->shouldProcessSynchronously($command)) {
            $this->syncBus->dispatch($command);
        } else {
            $this->queue->push(new ProcessCommandJob($command));
        }
    }
    
    public function registerHandler(string $commandClass, string $handlerClass): void
    {
        $this->syncBus->registerHandler($commandClass, $handlerClass);
    }
    
    private function shouldProcessSynchronously(Command $command): bool
    {
        return $command instanceof SynchronousCommand;
    }
}
```

### Query Bus Pattern

Implement a query bus for consistent query handling:

```php
interface QueryBus
{
    public function ask(Query $query): QueryResult;
    public function registerHandler(string $queryClass, string $handlerClass): void;
}

class QueryBusImplementation implements QueryBus
{
    private array $handlers = [];
    private Container $container;
    
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    
    public function ask(Query $query): QueryResult
    {
        $queryClass = get_class($query);
        
        if (!isset($this->handlers[$queryClass])) {
            throw new QueryHandlerNotFoundException($queryClass);
        }
        
        $handlerClass = $this->handlers[$queryClass];
        $handler = $this->container->make($handlerClass);
        
        return $handler->handle($query);
    }
    
    public function registerHandler(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }
}
```

### Read Model Projections

Design optimized read models for query performance:

```php
// User Projection Schema
Schema::create('user_projections', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->string('email')->unique();
    $table->string('name');
    $table->string('user_type');
    $table->string('state');
    $table->json('profile_data');
    $table->timestamp('created_at');
    $table->timestamp('updated_at');
    $table->timestamp('last_login_at')->nullable();
    
    // Optimized indexes for common queries
    $table->index(['user_type', 'state']);
    $table->index(['state', 'created_at']);
    $table->index(['name', 'email']); // For search
});

// User Permission Projection Schema
Schema::create('user_permission_projections', function (Blueprint $table) {
    $table->id();
    $table->string('user_id');
    $table->string('permission');
    $table->string('context')->nullable();
    $table->timestamp('granted_at');
    $table->string('granted_by');
    
    $table->unique(['user_id', 'permission', 'context']);
    $table->index(['user_id', 'context']);
    $table->index('permission');
});

// Team Projection Schema
Schema::create('team_projections', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->string('name');
    $table->string('parent_id')->nullable();
    $table->string('state');
    $table->json('settings');
    $table->integer('member_count')->default(0);
    $table->timestamp('created_at');
    $table->timestamp('updated_at');
    
    $table->index(['parent_id', 'state']);
    $table->index(['state', 'created_at']);
    $table->index('name');
});

// Team Hierarchy Projection Schema (Closure Table)
Schema::create('team_hierarchy_projections', function (Blueprint $table) {
    $table->string('ancestor_id');
    $table->string('descendant_id');
    $table->integer('depth');
    $table->string('descendant_name'); // Denormalized for performance
    
    $table->primary(['ancestor_id', 'descendant_id']);
    $table->index(['descendant_id', 'depth']);
    $table->index(['ancestor_id', 'depth']);
});

// Team Member Projection Schema
Schema::create('team_member_projections', function (Blueprint $table) {
    $table->id();
    $table->string('team_id');
    $table->string('user_id');
    $table->string('role');
    $table->string('user_name'); // Denormalized for performance
    $table->string('user_email'); // Denormalized for performance
    $table->timestamp('joined_at');
    
    $table->unique(['team_id', 'user_id']);
    $table->index(['user_id', 'role']);
    $table->index(['team_id', 'role']);
});
```

### Consistency Models

Implement different consistency strategies based on business requirements:

```php
// Eventual Consistency (Default)
class EventualConsistencyProjector extends Projector
{
    protected array $handlesEvents = [
        UserRegistrationInitiated::class => 'onUserRegistrationInitiated',
        UserActivated::class => 'onUserActivated',
        UserProfileUpdated::class => 'onUserProfileUpdated',
    ];
    
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        // Process asynchronously via queue
        dispatch(new UpdateUserProjectionJob($event));
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        dispatch(new UpdateUserProjectionJob($event));
    }
    
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        dispatch(new UpdateUserProjectionJob($event));
    }
}

// Strong Consistency (When Required)
class StrongConsistencyProjector extends Projector
{
    protected array $handlesEvents = [
        UserPermissionGranted::class => 'onUserPermissionGranted',
        UserPermissionRevoked::class => 'onUserPermissionRevoked',
    ];
    
    public function onUserPermissionGranted(UserPermissionGranted $event): void
    {
        // Process synchronously for immediate consistency
        DB::table('user_permission_projections')->insert([
            'user_id' => $event->user_id,
            'permission' => $event->permission,
            'context' => json_encode($event->context),
            'granted_at' => $event->granted_at,
            'granted_by' => $event->granted_by,
        ]);
        
        // Invalidate relevant caches
        $this->invalidateUserPermissionCache($event->user_id);
    }
    
    public function onUserPermissionRevoked(UserPermissionRevoked $event): void
    {
        DB::table('user_permission_projections')
            ->where('user_id', $event->user_id)
            ->where('permission', $event->permission)
            ->delete();
        
        $this->invalidateUserPermissionCache($event->user_id);
    }
    
    private function invalidateUserPermissionCache(string $userId): void
    {
        Cache::tags(['user_permissions', "user_{$userId}"])->flush();
    }
}
```

## Step-by-Step Implementation Guide

### Phase 1: Command Infrastructure (Week 1)

**Step 1: Create Command Interfaces and Base Classes**
```php
// app/Commands/Command.php
interface Command
{
    public function getAggregateId(): string;
    public function validate(): array;
    public function getMetadata(): array;
}

// app/Commands/BaseCommand.php
abstract class BaseCommand implements Command
{
    // Implementation as shown above
}
```

**Step 2: Implement Command Bus**
```php
// app/Services/CommandBus.php
class CommandBus implements CommandBusInterface
{
    // Implementation as shown above
}

// Register in AppServiceProvider
public function register()
{
    $this->app->singleton(CommandBusInterface::class, CommandBus::class);
}
```

**Step 3: Create Command Handlers**
```php
// app/Handlers/Commands/
// Implement all command handlers as shown above
```

### Phase 2: Query Infrastructure (Week 2)

**Step 1: Create Query Interfaces and Base Classes**
```php
// app/Queries/Query.php
interface Query
{
    public function getFilters(): array;
    public function getPagination(): ?PaginationOptions;
    public function getSorting(): ?SortingOptions;
}

// app/Queries/BaseQuery.php
abstract class BaseQuery implements Query
{
    // Implementation as shown above
}
```

**Step 2: Implement Query Bus**
```php
// app/Services/QueryBus.php
class QueryBus implements QueryBusInterface
{
    // Implementation as shown above
}
```

**Step 3: Create Read Model Migrations**
```bash
php artisan make:migration create_user_projections_table
php artisan make:migration create_team_projections_table
php artisan make:migration create_user_permission_projections_table
php artisan make:migration create_team_member_projections_table
php artisan make:migration create_team_hierarchy_projections_table
```

### Phase 3: Projectors and Read Models (Week 3)

**Step 1: Implement Projectors**
```php
// app/Projectors/UserProjector.php
class UserProjector extends Projector
{
    protected array $handlesEvents = [
        UserRegistrationInitiated::class => 'onUserRegistrationInitiated',
        UserActivated::class => 'onUserActivated',
        UserProfileUpdated::class => 'onUserProfileUpdated',
    ];
    
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        DB::table('user_projections')->insert([
            'id' => $event->user_id,
            'email' => $event->email,
            'name' => $event->registration_data['name'] ?? '',
            'user_type' => $event->user_type,
            'state' => 'pending',
            'profile_data' => json_encode($event->registration_data),
            'created_at' => $event->initiated_at,
            'updated_at' => $event->initiated_at,
        ]);
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        DB::table('user_projections')
            ->where('id', $event->user_id)
            ->update([
                'state' => 'active',
                'updated_at' => $event->activated_at,
            ]);
    }
    
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        $updates = ['updated_at' => $event->updated_at];
        
        foreach ($event->new_values as $field => $value) {
            if (in_array($field, ['name', 'email'])) {
                $updates[$field] = $value;
            }
        }
        
        if (count($event->new_values) > count($updates) - 1) {
            // Update profile_data JSON for other fields
            $currentProfile = DB::table('user_projections')
                ->where('id', $event->user_id)
                ->value('profile_data');
            
            $profileData = json_decode($currentProfile, true) ?? [];
            $profileData = array_merge($profileData, $event->new_values);
            $updates['profile_data'] = json_encode($profileData);
        }
        
        DB::table('user_projections')
            ->where('id', $event->user_id)
            ->update($updates);
    }
}
```

**Step 2: Implement Query Handlers**
```php
// app/Handlers/Queries/
// Implement all query handlers as shown above
```

### Phase 4: Integration and Testing (Week 4)

**Step 1: Register Handlers**
```php
// app/Providers/CqrsServiceProvider.php
class CqrsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $commandBus = $this->app->make(CommandBusInterface::class);
        $queryBus = $this->app->make(QueryBusInterface::class);
        
        // Register command handlers
        $commandBus->registerHandler(RegisterUserCommand::class, RegisterUserCommandHandler::class);
        $commandBus->registerHandler(ActivateUserCommand::class, ActivateUserCommandHandler::class);
        // ... register all command handlers
        
        // Register query handlers
        $queryBus->registerHandler(GetUserByIdQuery::class, GetUserByIdQueryHandler::class);
        $queryBus->registerHandler(GetUsersByTypeQuery::class, GetUsersByTypeQueryHandler::class);
        // ... register all query handlers
    }
}
```

**Step 2: Create API Controllers**
```php
// app/Http/Controllers/Api/UserController.php
class UserController extends Controller
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus
    ) {}
    
    public function store(RegisterUserRequest $request): JsonResponse
    {
        $command = new RegisterUserCommand(
            userId: Str::uuid(),
            email: $request->email,
            userType: UserType::from($request->user_type),
            profileData: $request->profile_data
        );
        
        $this->commandBus->dispatch($command);
        
        return response()->json(['message' => 'User registration initiated'], 202);
    }
    
    public function show(string $id): JsonResponse
    {
        $query = new GetUserByIdQuery(
            userId: $id,
            includePermissions: request()->boolean('include_permissions'),
            includeTeams: request()->boolean('include_teams')
        );
        
        $result = $this->queryBus->ask($query);
        
        return response()->json($result->getData());
    }
    
    public function activate(string $id): JsonResponse
    {
        $command = new ActivateUserCommand(
            userId: $id,
            activatedBy: auth()->id(),
            activationMethod: 'admin'
        );
        
        $this->commandBus->dispatch($command);
        
        return response()->json(['message' => 'User activated'], 200);
    }
}
```

## Testing and Validation

### Command Handler Testing

```php
// tests/Unit/Handlers/Commands/RegisterUserCommandHandlerTest.php
class RegisterUserCommandHandlerTest extends TestCase
{
    use RefreshDatabase;
    
    private RegisterUserCommandHandler $handler;
    private EventStore $eventStore;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->eventStore = $this->createMock(EventStore::class);
        $this->handler = new RegisterUserCommandHandler(
            $this->eventStore,
            app(CommandValidator::class)
        );
    }
    
    public function test_can_register_new_user()
    {
        $command = new RegisterUserCommand(
            userId: 'user-123',
            email: 'test@example.com',
            userType: UserType::STANDARD,
            profileData: ['name' => 'Test User']
        );
        
        $this->eventStore
            ->expects($this->once())
            ->method('append')
            ->with($this->isInstanceOf(UserAggregate::class));
        
        $this->handler->handle($command);
    }
    
    public function test_throws_exception_for_invalid_email()
    {
        $command = new RegisterUserCommand(
            userId: 'user-123',
            email: 'invalid-email',
            userType: UserType::STANDARD,
            profileData: ['name' => 'Test User']
        );
        
        $this->expectException(CommandValidationException::class);
        
        $this->handler->handle($command);
    }
}
```

### Query Handler Testing

```php
// tests/Unit/Handlers/Queries/GetUserByIdQueryHandlerTest.php
class GetUserByIdQueryHandlerTest extends TestCase
{
    use RefreshDatabase;
    
    private GetUserByIdQueryHandler $handler;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->handler = new GetUserByIdQueryHandler(
            DB::connection(),
            app(CacheManager::class)
        );
    }
    
    public function test_can_get_user_by_id()
    {
        // Arrange
        DB::table('user_projections')->insert([
            'id' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'user_type' => 'Standard',
            'state' => 'active',
            'profile_data' => json_encode(['bio' => 'Test bio']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $query = new GetUserByIdQuery('user-123');
        
        // Act
        $result = $this->handler->handle($query);
        
        // Assert
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertEquals('user-123', $result->getData()->id);
        $this->assertEquals('test@example.com', $result->getData()->email);
    }
    
    public function test_throws_exception_for_non_existent_user()
    {
        $query = new GetUserByIdQuery('non-existent');
        
        $this->expectException(UserNotFoundException::class);
        
        $this->handler->handle($query);
    }
}
```

### Integration Testing

```php
// tests/Feature/CqrsIntegrationTest.php
class CqrsIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_user_registration_flow()
    {
        // Arrange
        $commandBus = app(CommandBusInterface::class);
        $queryBus = app(QueryBusInterface::class);
        
        $userId = Str::uuid();
        
        // Act - Register user
        $registerCommand = new RegisterUserCommand(
            userId: $userId,
            email: 'test@example.com',
            userType: UserType::STANDARD,
            profileData: ['name' => 'Test User']
        );
        
        $commandBus->dispatch($registerCommand);
        
        // Process projections
        $this->artisan('event-sourcing:replay');
        
        // Query user
        $getUserQuery = new GetUserByIdQuery($userId);
        $result = $queryBus->ask($getUserQuery);
        
        // Assert
        $this->assertEquals($userId, $result->getData()->id);
        $this->assertEquals('pending', $result->getData()->state);
        
        // Act - Activate user
        $activateCommand = new ActivateUserCommand(
            userId: $userId,
            activatedBy: 'admin-123'
        );
        
        $commandBus->dispatch($activateCommand);
        
        // Process projections
        $this->artisan('event-sourcing:replay');
        
        // Query user again
        $result = $queryBus->ask($getUserQuery);
        
        // Assert
        $this->assertEquals('active', $result->getData()->state);
    }
}
```

## Performance Considerations

### Command Performance Optimization

```php
// Batch Command Processing
class BatchCommandProcessor
{
    private array $commandBatch = [];
    private int $batchSize;
    
    public function __construct(int $batchSize = 50)
    {
        $this->batchSize = $batchSize;
    }
    
    public function addCommand(Command $command): void
    {
        $this->commandBatch[] = $command;
        
        if (count($this->commandBatch) >= $this->batchSize) {
            $this->processBatch();
        }
    }
    
    public function processBatch(): void
    {
        if (empty($this->commandBatch)) {
            return;
        }
        
        DB::transaction(function () {
            foreach ($this->commandBatch as $command) {
                app(CommandBusInterface::class)->dispatch($command);
            }
        });
        
        $this->commandBatch = [];
    }
}
```

### Query Performance Optimization

```php
// Query Result Caching
class CachedQueryHandler extends QueryHandler
{
    protected int $defaultCacheTtl = 300; // 5 minutes
    
    public function handle(Query $query): QueryResult
    {
        $cacheKey = $this->getCacheKey($query);
        $cacheTtl = $this->getCacheTtl($query);
        
        return $this->cache->remember($cacheKey, $cacheTtl, function () use ($query) {
            return $this->executeQuery($query);
        });
    }
    
    protected function getCacheTtl(Query $query): int
    {
        // Different cache TTLs based on query type
        return match (get_class($query)) {
            GetUserByIdQuery::class => 600, // 10 minutes
            GetTeamHierarchyQuery::class => 1800, // 30 minutes
            SearchUsersQuery::class => 60, // 1 minute
            default => $this->defaultCacheTtl
        };
    }
    
    abstract protected function executeQuery(Query $query): QueryResult;
}

// Read Replica Configuration
class ReadReplicaQueryHandler extends QueryHandler
{
    protected Connection $readConnection;
    
    public function __construct()
    {
        $this->readConnection = DB::connection('read_replica');
    }
    
    // Use read replica for all query operations
}
```

### Projection Performance

```php
// Optimized Projection Updates
class OptimizedUserProjector extends Projector
{
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        // Use upsert for better performance
        DB::table('user_projections')
            ->upsert([
                'id' => $event->user_id,
                'name' => $event->new_values['name'] ?? DB::raw('name'),
                'email' => $event->new_values['email'] ?? DB::raw('email'),
                'profile_data' => json_encode(
                    array_merge(
                        json_decode(
                            DB::table('user_projections')
                                ->where('id', $event->user_id)
                                ->value('profile_data') ?? '{}',
                            true
                        ),
                        $event->new_values
                    )
                ),
                'updated_at' => $event->updated_at,
            ], ['id'], ['name', 'email', 'profile_data', 'updated_at']);
        
        // Invalidate specific cache keys
        $this->invalidateUserCache($event->user_id);
    }
    
    private function invalidateUserCache(string $userId): void
    {
        Cache::tags(['user', "user_{$userId}"])->flush();
    }
}
```

## Security Considerations

### Command Authorization

```php
// Command Authorization Middleware
class AuthorizeCommandMiddleware
{
    public function handle(Command $command, Closure $next)
    {
        $user = auth()->user();
        
        if (!$this->canExecuteCommand($user, $command)) {
            throw new UnauthorizedCommandException();
        }
        
        return $next($command);
    }
    
    private function canExecuteCommand(?User $user, Command $command): bool
    {
        return match (get_class($command)) {
            RegisterUserCommand::class => $this->canRegisterUser($user, $command),
            ActivateUserCommand::class => $this->canActivateUser($user, $command),
            CreateTeamCommand::class => $this->canCreateTeam($user, $command),
            default => false
        };
    }
    
    private function canRegisterUser(?User $user, RegisterUserCommand $command): bool
    {
        // Public registration allowed for Standard users
        if ($command->userType === UserType::STANDARD) {
            return true;
        }
        
        // Admin registration requires admin privileges
        return $user && $user->hasPermission('create_admin_users');
    }
    
    private function canActivateUser(?User $user, ActivateUserCommand $command): bool
    {
        return $user && (
            $user->hasPermission('activate_users') ||
            $user->id === $command->userId // Users can activate themselves
        );
    }
}
```

### Query Authorization

```php
// Query Authorization
class AuthorizeQueryMiddleware
{
    public function handle(Query $query, Closure $next)
    {
        $user = auth()->user();
        
        if (!$this->canExecuteQuery($user, $query)) {
            throw new UnauthorizedQueryException();
        }
        
        return $next($query);
    }
    
    private function canExecuteQuery(?User $user, Query $query): bool
    {
        return match (get_class($query)) {
            GetUserByIdQuery::class => $this->canViewUser($user, $query),
            GetUserPermissionsQuery::class => $this->canViewUserPermissions($user, $query),
            GetTeamMembersQuery::class => $this->canViewTeamMembers($user, $query),
            default => false
        };
    }
    
    private function canViewUser(?User $user, GetUserByIdQuery $query): bool
    {
        if (!$user) {
            return false;
        }
        
        // Users can view their own profile
        if ($user->id === $query->userId) {
            return true;
        }
        
        // Admins can view all users
        return $user->hasPermission('view_users');
    }
}
```

### Data Sanitization

```php
// Command Data Sanitization
class SanitizeCommandMiddleware
{
    public function handle(Command $command, Closure $next)
    {
        $this->sanitizeCommand($command);
        
        return $next($command);
    }
    
    private function sanitizeCommand(Command $command): void
    {
        if ($command instanceof RegisterUserCommand) {
            $command->profileData = $this->sanitizeProfileData($command->profileData);
        }
        
        if ($command instanceof UpdateUserProfileCommand) {
            $command->profileUpdates = $this->sanitizeProfileData($command->profileUpdates);
        }
    }
    
    private function sanitizeProfileData(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return strip_tags(trim($value));
            }
            return $value;
        }, $data);
    }
}
```

## Troubleshooting Guide

### Common Issues and Solutions

**Issue: Command Handler Not Found**
```php
// Problem: CommandHandlerNotFoundException thrown
// Solution: Ensure handler is registered in service provider

// Check registration in CqrsServiceProvider
public function boot()
{
    $commandBus = $this->app->make(CommandBusInterface::class);
    
    // Ensure this line exists
    $commandBus->registerHandler(YourCommand::class, YourCommandHandler::class);
}
```

**Issue: Query Performance Problems**
```php
// Problem: Slow query execution
// Solution: Add appropriate indexes and caching

// Add indexes to projection tables
Schema::table('user_projections', function (Blueprint $table) {
    $table->index(['user_type', 'state', 'created_at']);
    $table->index(['name', 'email']); // For search queries
});

// Implement query-specific caching
class OptimizedGetUsersByTypeQueryHandler extends QueryHandler
{
    protected function getCacheTtl(Query $query): int
    {
        // Cache search results for shorter time
        return $query instanceof SearchUsersQuery ? 60 : 300;
    }
}
```

**Issue: Projection Lag**
```php
// Problem: Read models are not updated immediately
// Solution: Implement projection monitoring and alerts

class ProjectionLagMonitor
{
    public function checkProjectionLag(): array
    {
        $lastEventTime = DB::table('stored_events')
            ->max('created_at');
        
        $lastProjectionTime = DB::table('user_projections')
            ->max('updated_at');
        
        $lagSeconds = Carbon::parse($lastEventTime)
            ->diffInSeconds(Carbon::parse($lastProjectionTime));
        
        if ($lagSeconds > 300) { // 5 minutes
            // Alert administrators
            $this->sendLagAlert($lagSeconds);
        }
        
        return [
            'last_event' => $lastEventTime,
            'last_projection' => $lastProjectionTime,
            'lag_seconds' => $lagSeconds,
        ];
    }
}
```

**Issue: Memory Usage in Batch Processing**
```php
// Problem: High memory usage during batch command processing
// Solution: Implement memory-efficient batch processing

class MemoryEfficientBatchProcessor
{
    public function processBatch(array $commands): void
    {
        $chunks = array_chunk($commands, 100);
        
        foreach ($chunks as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $command) {
                    app(CommandBusInterface::class)->dispatch($command);
                }
            });
            
            // Force garbage collection after each chunk
            gc_collect_cycles();
            
            // Optional: Add small delay to prevent overwhelming the system
            usleep(10000); // 10ms
        }
    }
}
```

## References and Further Reading

### CQRS Fundamentals
- [CQRS Pattern - Martin Fowler](https://martinfowler.com/bliki/CQRS.html)
- [CQRS Journey - Microsoft](https://docs.microsoft.com/en-us/previous-versions/msp-n-p/jj554200(v=pandp.10))
- [Command and Query Responsibility Segregation - Greg Young](https://cqrs.files.wordpress.com/2010/11/cqrs_documents.pdf)

### Laravel Implementation
- [Laravel Service Container](https://laravel.com/docs/container)
- [Laravel Queues](https://laravel.com/docs/queues)
- [Laravel Database Optimization](https://laravel.com/docs/database)

### Performance and Scaling
- [Database Read Replicas](https://dev.mysql.com/doc/refman/8.0/en/replication.html)
- [Redis Caching Strategies](https://redis.io/docs/manual/patterns/)
- [SQLite Performance Tuning](https://www.sqlite.org/optoverview.html)

### Security Best Practices
- [Laravel Authorization](https://laravel.com/docs/authorization)
- [Input Validation and Sanitization](https://laravel.com/docs/validation)
- [OWASP Security Guidelines](https://owasp.org/www-project-top-ten/)
