# 1. Event-Sourcing Architecture for UMS-STI

## 1.1 Executive Summary

This document outlines the comprehensive event-sourcing architecture for the User Management System with Single Table Inheritance (UMS-STI). Event-sourcing serves as a foundational pattern that captures all changes to application state as a sequence of immutable events, providing complete auditability, system recovery capabilities, and rich analytics data. The architecture leverages `spatie/laravel-event-sourcing` with Snowflake IDs for distributed uniqueness, optimized for SQLite database performance while maintaining GDPR compliance through sophisticated event anonymization strategies.

**Key Benefits**: Complete audit trails, point-in-time state reconstruction, enhanced debugging capabilities, and robust analytics foundation for business intelligence.

## 1.2 Learning Objectives

After completing this document, readers will understand:

- **Event Store Design**: How to structure event storage with Snowflake IDs and SQLite optimization
- **Event Types Catalog**: Complete taxonomy of domain events for users and teams
- **Event Versioning**: Strategies for evolving event schemas without breaking existing data
- **Snapshot Mechanisms**: When and how to create aggregate snapshots for performance
- **Event Replay**: Techniques for rebuilding application state from event history
- **GDPR Compliance**: Event anonymization and retention strategies for regulatory compliance
- **SQLite Optimization**: Performance tuning for event storage and retrieval

## 1.3 Prerequisite Knowledge

Before implementing event-sourcing architecture, ensure familiarity with:

- **Laravel Framework**: Eloquent ORM, migrations, service providers, and event system
- **Domain-Driven Design**: Aggregates, entities, value objects, and domain events
- **Event-Sourcing Concepts**: Event stores, projections, snapshots, and eventual consistency
- **SQLite Database**: Performance characteristics, WAL mode, and optimization techniques
- **UMS-STI Architecture**: Single Table Inheritance patterns and team hierarchy design
- **GDPR Compliance**: Data retention, anonymization, and audit requirements

## Architectural Overview

### Event-Sourcing Foundation

Event-sourcing captures all changes to application state as a sequence of immutable events stored in an event store. Instead of storing current state, we store the events that led to that state, enabling:

```php
// Traditional State Storage
User::create(['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active']);

// Event-Sourced Approach
UserRegistered::dispatch(['user_id' => $userId, 'name' => 'John Doe', 'email' => 'john@example.com']);
UserActivated::dispatch(['user_id' => $userId, 'activated_at' => now()]);
```

### Architecture Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Event-Sourcing Architecture              │
├─────────────────────────────────────────────────────────────┤
│  Commands → Aggregates → Events → Event Store              │
│                    ↓                                        │
│  Projectors ← Event Stream ← Event Store                   │
│                    ↓                                        │
│  Read Models ← Projectors                                   │
│                    ↓                                        │
│  Queries → Read Models → Responses                          │
└─────────────────────────────────────────────────────────────┘
```

### Core Principles

1. **Immutable Events**: Events are never modified or deleted, only appended
2. **Event Ordering**: Events maintain strict chronological ordering within aggregates
3. **Aggregate Consistency**: Business rules enforced within aggregate boundaries
4. **Eventual Consistency**: Read models updated asynchronously from events
5. **Event Replay**: Complete system state reconstructable from event history

## Core Concepts Deep Dive

### Event Store Database Architecture

The event store requires a **separate, exclusive database connection** to ensure optimal performance and isolation from the main application database. This separation provides:

- **Performance Isolation**: Event store operations don't interfere with application queries
- **Optimization Focus**: Database can be tuned specifically for event storage patterns
- **Backup Strategy**: Separate backup and recovery procedures for event data
- **Testing Isolation**: Tests use dedicated event store connection even with `:memory:` databases

#### Database Connection Configuration

```php
// config/database.php - Event Store Connection
'event_store' => [
    'driver' => 'sqlite',
    'database' => env('EVENT_STORE_DATABASE', database_path('event_store.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'pragmas' => [
        'journal_mode' => 'WAL',        // Write-Ahead Logging for performance
        'synchronous' => 'NORMAL',      // Balance safety and performance
        'cache_size' => -64000,         // 64MB cache for event queries
        'temp_store' => 'MEMORY',       // Use memory for temporary operations
        'mmap_size' => 268435456,       // 256MB memory mapping
        'foreign_keys' => 'ON',         // Enforce referential integrity
    ],
],
```

### Event Store Schema Design

The event store serves as the single source of truth for all domain events. Our SQLite-optimized schema leverages Snowflake IDs for distributed uniqueness and performance:

```sql
-- Primary event store table
CREATE TABLE stored_events (
    id TEXT PRIMARY KEY,                    -- Snowflake ID for global uniqueness
    aggregate_root_id TEXT NOT NULL,       -- Aggregate identifier
    aggregate_version INTEGER NOT NULL,    -- Version within aggregate
    event_class TEXT NOT NULL,             -- Fully qualified event class name
    event_data TEXT NOT NULL,              -- JSON-encoded event payload
    meta_data TEXT,                        -- Additional metadata (user context, etc.)
    created_at DATETIME NOT NULL,          -- Event timestamp

    -- Performance indexes
    UNIQUE(aggregate_root_id, aggregate_version),
    INDEX idx_stored_events_aggregate (aggregate_root_id),
    INDEX idx_stored_events_class (event_class),
    INDEX idx_stored_events_created (created_at)
);

-- Snapshot storage for performance optimization
CREATE TABLE snapshots (
    id TEXT PRIMARY KEY,                    -- Snowflake ID
    aggregate_root_id TEXT NOT NULL,       -- Aggregate identifier
    aggregate_version INTEGER NOT NULL,    -- Version at snapshot time
    state TEXT NOT NULL,                   -- JSON-encoded aggregate state
    created_at DATETIME NOT NULL,

    UNIQUE(aggregate_root_id, aggregate_version),
    INDEX idx_snapshots_aggregate (aggregate_root_id)
);
```

### Snowflake ID Implementation

Snowflake IDs provide distributed uniqueness with temporal ordering, crucial for event sequencing:

```php
use Glhd\Bits\Snowflake;

class EventIdGenerator
{
    private Snowflake $snowflake;

    public function __construct()
    {
        $this->snowflake = new Snowflake(
            datacenter: config('app.datacenter_id', 1),
            worker: config('app.worker_id', 1)
        );
    }

    public function generate(): string
    {
        return $this->snowflake->id();
    }

    public function extractTimestamp(string $id): Carbon
    {
        return $this->snowflake->parse($id)->timestamp();
    }
}
```

### Event Types Catalog

#### User Domain Events

**User Lifecycle Events**:
```php
// User Registration and Activation
UserRegistrationInitiated::class => [
    'user_id' => 'string',
    'email' => 'string',
    'user_type' => 'string', // Standard, Admin, Guest, SystemUser
    'registration_data' => 'array',
    'initiated_at' => 'datetime'
];

UserRegistrationCompleted::class => [
    'user_id' => 'string',
    'profile_data' => 'array',
    'completed_at' => 'datetime'
];

UserActivated::class => [
    'user_id' => 'string',
    'activated_by' => 'string',
    'activation_method' => 'string', // email, admin, auto
    'activated_at' => 'datetime'
];

UserDeactivated::class => [
    'user_id' => 'string',
    'deactivated_by' => 'string',
    'reason' => 'string',
    'deactivated_at' => 'datetime'
];
```

**User Profile Events**:
```php
UserProfileUpdated::class => [
    'user_id' => 'string',
    'changed_fields' => 'array',
    'previous_values' => 'array',
    'new_values' => 'array',
    'updated_by' => 'string',
    'updated_at' => 'datetime'
];

UserEmailChanged::class => [
    'user_id' => 'string',
    'old_email' => 'string',
    'new_email' => 'string',
    'verification_required' => 'boolean',
    'changed_by' => 'string',
    'changed_at' => 'datetime'
];

UserPasswordChanged::class => [
    'user_id' => 'string',
    'changed_by' => 'string',
    'password_strength_score' => 'integer',
    'changed_at' => 'datetime'
];
```

**User Permission Events**:
```php
UserPermissionGranted::class => [
    'user_id' => 'string',
    'permission' => 'string',
    'granted_by' => 'string',
    'context' => 'array', // team_id, resource_id, etc.
    'granted_at' => 'datetime'
];

UserPermissionRevoked::class => [
    'user_id' => 'string',
    'permission' => 'string',
    'revoked_by' => 'string',
    'reason' => 'string',
    'revoked_at' => 'datetime'
];

UserRoleAssigned::class => [
    'user_id' => 'string',
    'role' => 'string',
    'assigned_by' => 'string',
    'context' => 'array',
    'assigned_at' => 'datetime'
];
```

#### Team Domain Events

**Team Lifecycle Events**:
```php
TeamCreated::class => [
    'team_id' => 'string',
    'name' => 'string',
    'parent_team_id' => 'string|null',
    'created_by' => 'string',
    'team_settings' => 'array',
    'created_at' => 'datetime'
];

TeamUpdated::class => [
    'team_id' => 'string',
    'changed_fields' => 'array',
    'previous_values' => 'array',
    'new_values' => 'array',
    'updated_by' => 'string',
    'updated_at' => 'datetime'
];

TeamArchived::class => [
    'team_id' => 'string',
    'archived_by' => 'string',
    'reason' => 'string',
    'archived_at' => 'datetime'
];
```

**Team Membership Events**:
```php
TeamMemberAdded::class => [
    'team_id' => 'string',
    'user_id' => 'string',
    'role' => 'string',
    'added_by' => 'string',
    'added_at' => 'datetime'
];

TeamMemberRemoved::class => [
    'team_id' => 'string',
    'user_id' => 'string',
    'removed_by' => 'string',
    'reason' => 'string',
    'removed_at' => 'datetime'
];

TeamMemberRoleChanged::class => [
    'team_id' => 'string',
    'user_id' => 'string',
    'old_role' => 'string',
    'new_role' => 'string',
    'changed_by' => 'string',
    'changed_at' => 'datetime'
];
```

**Team Hierarchy Events**:
```php
TeamHierarchyChanged::class => [
    'team_id' => 'string',
    'old_parent_id' => 'string|null',
    'new_parent_id' => 'string|null',
    'changed_by' => 'string',
    'hierarchy_impact' => 'array', // affected descendant teams
    'changed_at' => 'datetime'
];
```

#### System Events

**Authentication Events**:
```php
UserLoggedIn::class => [
    'user_id' => 'string',
    'session_id' => 'string',
    'ip_address' => 'string',
    'user_agent' => 'string',
    'login_method' => 'string', // password, token, sso
    'logged_in_at' => 'datetime'
];

UserLoggedOut::class => [
    'user_id' => 'string',
    'session_id' => 'string',
    'logout_method' => 'string', // manual, timeout, forced
    'logged_out_at' => 'datetime'
];

FailedLoginAttempt::class => [
    'email' => 'string',
    'ip_address' => 'string',
    'user_agent' => 'string',
    'failure_reason' => 'string',
    'attempted_at' => 'datetime'
];
```

**GDPR Compliance Events**:
```php
DataExportRequested::class => [
    'user_id' => 'string',
    'requested_by' => 'string',
    'export_scope' => 'array',
    'requested_at' => 'datetime'
];

DataExportCompleted::class => [
    'user_id' => 'string',
    'export_id' => 'string',
    'file_path' => 'string',
    'completed_at' => 'datetime'
];

DataDeletionRequested::class => [
    'user_id' => 'string',
    'requested_by' => 'string',
    'deletion_scope' => 'array',
    'requested_at' => 'datetime'
];

DataAnonymized::class => [
    'user_id' => 'string',
    'anonymization_token' => 'string',
    'anonymized_events' => 'array',
    'anonymized_at' => 'datetime'
];
```

### Event Versioning Strategy

Event schema evolution requires careful versioning to maintain backward compatibility:

```php
abstract class VersionedEvent
{
    public const VERSION = 1;

    public function getVersion(): int
    {
        return static::VERSION;
    }

    abstract public function upcast(array $eventData, int $fromVersion): array;
}

// Version 1 of UserRegistered event
class UserRegisteredV1 extends VersionedEvent
{
    public const VERSION = 1;

    public function upcast(array $eventData, int $fromVersion): array
    {
        // No upcasting needed for base version
        return $eventData;
    }
}

// Version 2 adds user_type field
class UserRegisteredV2 extends VersionedEvent
{
    public const VERSION = 2;

    public function upcast(array $eventData, int $fromVersion): array
    {
        if ($fromVersion === 1) {
            // Add default user_type for V1 events
            $eventData['user_type'] = 'Standard';
        }

        return $eventData;
    }
}
```

### Event Upcasting Implementation

```php
class EventUpcaster
{
    private array $upcasters = [];

    public function registerUpcaster(string $eventClass, callable $upcaster): void
    {
        $this->upcasters[$eventClass] = $upcaster;
    }

    public function upcast(string $eventClass, array $eventData, int $version): array
    {
        $targetVersion = $eventClass::VERSION;

        while ($version < $targetVersion) {
            $nextVersion = $version + 1;
            $upcasterClass = $eventClass . 'V' . $nextVersion;

            if (class_exists($upcasterClass)) {
                $upcaster = new $upcasterClass();
                $eventData = $upcaster->upcast($eventData, $version);
                $version = $nextVersion;
            } else {
                throw new EventVersioningException(
                    "No upcaster found for {$eventClass} from version {$version} to {$nextVersion}"
                );
            }
        }

        return $eventData;
    }
}
```

## Implementation Principles & Patterns

### Aggregate Design Patterns

**User Aggregate**:
```php
class UserAggregate extends AggregateRoot
{
    private UserId $userId;
    private UserType $userType;
    private UserState $state;
    private UserProfile $profile;
    private array $permissions = [];

    public static function register(
        UserId $userId,
        Email $email,
        UserType $userType,
        array $profileData
    ): self {
        $aggregate = new self();

        $aggregate->recordThat(new UserRegistrationInitiated([
            'user_id' => $userId->toString(),
            'email' => $email->toString(),
            'user_type' => $userType->value,
            'registration_data' => $profileData,
            'initiated_at' => now()
        ]));

        return $aggregate;
    }

    public function activate(UserId $activatedBy): void
    {
        if ($this->state->isActive()) {
            throw new UserAlreadyActiveException();
        }

        $this->recordThat(new UserActivated([
            'user_id' => $this->userId->toString(),
            'activated_by' => $activatedBy->toString(),
            'activation_method' => 'admin',
            'activated_at' => now()
        ]));
    }

    protected function applyUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        $this->userId = UserId::fromString($event->user_id);
        $this->userType = UserType::from($event->user_type);
        $this->state = UserState::PENDING;
        $this->profile = UserProfile::fromArray($event->registration_data);
    }

    protected function applyUserActivated(UserActivated $event): void
    {
        $this->state = UserState::ACTIVE;
    }
}
```

**Team Aggregate**:
```php
class TeamAggregate extends AggregateRoot
{
    private TeamId $teamId;
    private TeamName $name;
    private ?TeamId $parentTeamId;
    private TeamState $state;
    private array $members = [];

    public static function create(
        TeamId $teamId,
        TeamName $name,
        ?TeamId $parentTeamId,
        UserId $createdBy
    ): self {
        $aggregate = new self();

        $aggregate->recordThat(new TeamCreated([
            'team_id' => $teamId->toString(),
            'name' => $name->toString(),
            'parent_team_id' => $parentTeamId?->toString(),
            'created_by' => $createdBy->toString(),
            'team_settings' => [],
            'created_at' => now()
        ]));

        return $aggregate;
    }

    public function addMember(UserId $userId, TeamRole $role, UserId $addedBy): void
    {
        if ($this->hasMember($userId)) {
            throw new UserAlreadyTeamMemberException();
        }

        $this->recordThat(new TeamMemberAdded([
            'team_id' => $this->teamId->toString(),
            'user_id' => $userId->toString(),
            'role' => $role->value,
            'added_by' => $addedBy->toString(),
            'added_at' => now()
        ]));
    }

    protected function applyTeamCreated(TeamCreated $event): void
    {
        $this->teamId = TeamId::fromString($event->team_id);
        $this->name = TeamName::fromString($event->name);
        $this->parentTeamId = $event->parent_team_id 
            ? TeamId::fromString($event->parent_team_id) 
            : null;
        $this->state = TeamState::ACTIVE;
    }

    protected function applyTeamMemberAdded(TeamMemberAdded $event): void
    {
        $this->members[$event->user_id] = TeamRole::from($event->role);
    }
}
```

### Event Store Implementation

```php
class SqliteEventStore implements EventStore
{
    private Connection $connection;
    private EventSerializer $serializer;
    private EventIdGenerator $idGenerator;

    public function append(AggregateRoot $aggregate): void
    {
        $uncommittedEvents = $aggregate->getUncommittedEvents();

        if (empty($uncommittedEvents)) {
            return;
        }

        $this->connection->transaction(function () use ($aggregate, $uncommittedEvents) {
            foreach ($uncommittedEvents as $event) {
                $this->storeEvent($aggregate, $event);
            }

            $aggregate->markEventsAsCommitted();
        });
    }

    public function retrieve(string $aggregateId, int $fromVersion = 0): EventStream
    {
        $query = $this->connection->table('stored_events')
            ->where('aggregate_root_id', $aggregateId)
            ->where('aggregate_version', '>', $fromVersion)
            ->orderBy('aggregate_version');

        $events = $query->get()->map(function ($row) {
            return $this->serializer->deserialize(
                $row->event_class,
                json_decode($row->event_data, true),
                json_decode($row->meta_data, true) ?? []
            );
        });

        return new EventStream($events->toArray());
    }

    private function storeEvent(AggregateRoot $aggregate, DomainEvent $event): void
    {
        $this->connection->table('stored_events')->insert([
            'id' => $this->idGenerator->generate(),
            'aggregate_root_id' => $aggregate->getAggregateRootId(),
            'aggregate_version' => $aggregate->getVersion(),
            'event_class' => get_class($event),
            'event_data' => json_encode($this->serializer->serialize($event)),
            'meta_data' => json_encode($event->getMetaData()),
            'created_at' => now()
        ]);
    }
}
```

### Snapshot Strategy

Snapshots optimize performance by storing aggregate state at specific intervals:

```php
class SnapshotStore
{
    private Connection $connection;
    private int $snapshotFrequency;

    public function __construct(Connection $connection, int $snapshotFrequency = 100)
    {
        $this->connection = $connection;
        $this->snapshotFrequency = $snapshotFrequency;
    }

    public function shouldCreateSnapshot(AggregateRoot $aggregate): bool
    {
        return $aggregate->getVersion() % $this->snapshotFrequency === 0;
    }

    public function store(AggregateRoot $aggregate): void
    {
        $this->connection->table('snapshots')->insert([
            'id' => app(EventIdGenerator::class)->generate(),
            'aggregate_root_id' => $aggregate->getAggregateRootId(),
            'aggregate_version' => $aggregate->getVersion(),
            'state' => json_encode($aggregate->getState()),
            'created_at' => now()
        ]);
    }

    public function retrieve(string $aggregateId): ?AggregateSnapshot
    {
        $snapshot = $this->connection->table('snapshots')
            ->where('aggregate_root_id', $aggregateId)
            ->orderByDesc('aggregate_version')
            ->first();

        if (!$snapshot) {
            return null;
        }

        return new AggregateSnapshot(
            $snapshot->aggregate_root_id,
            $snapshot->aggregate_version,
            json_decode($snapshot->state, true)
        );
    }
}
```

## Step-by-Step Implementation Guide

### Phase 1: Event Store Foundation (Week 1)

**Step 1: Install Dependencies**
```bash
composer require spatie/laravel-event-sourcing
composer require glhd/bits
composer require spatie/laravel-data
```

**Step 2: Configure Event Store**
```php
// config/event-sourcing.php
return [
    'stored_event_model' => \App\Models\StoredEvent::class,
    'snapshot_model' => \App\Models\Snapshot::class,
    'event_handlers' => [
        // Projectors
        \App\Projectors\UserProjector::class,
        \App\Projectors\TeamProjector::class,

        // Reactors
        \App\Reactors\EmailNotificationReactor::class,
        \App\Reactors\AuditLogReactor::class,
    ],
];
```

**Step 3: Create Event Store Migration**
```php
// database/migrations/create_event_store_tables.php
public function up()
{
    Schema::create('stored_events', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('aggregate_root_id');
        $table->unsignedInteger('aggregate_version');
        $table->string('event_class');
        $table->json('event_data');
        $table->json('meta_data')->nullable();
        $table->timestamp('created_at');

        $table->unique(['aggregate_root_id', 'aggregate_version']);
        $table->index('aggregate_root_id');
        $table->index('event_class');
        $table->index('created_at');
    });

    Schema::create('snapshots', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('aggregate_root_id');
        $table->unsignedInteger('aggregate_version');
        $table->json('state');
        $table->timestamp('created_at');

        $table->unique(['aggregate_root_id', 'aggregate_version']);
        $table->index('aggregate_root_id');
    });
}
```

### Phase 2: Domain Events (Week 2)

**Step 1: Create Base Event Classes**
```php
// app/Events/DomainEvent.php
abstract class DomainEvent extends \Spatie\EventSourcing\StoredEvents\ShouldBeStored
{
    public array $eventData;
    public array $metaData;

    public function __construct(array $eventData, array $metaData = [])
    {
        $this->eventData = $eventData;
        $this->metaData = array_merge([
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ], $metaData);
    }

    public function toArray(): array
    {
        return $this->eventData;
    }

    public function getMetaData(): array
    {
        return $this->metaData;
    }
}
```

**Step 2: Implement User Events**
```php
// app/Events/User/UserRegistrationInitiated.php
class UserRegistrationInitiated extends DomainEvent
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $userType,
        public array $registrationData
    ) {
        parent::__construct([
            'user_id' => $userId,
            'email' => $email,
            'user_type' => $userType,
            'registration_data' => $registrationData,
            'initiated_at' => now()
        ]);
    }
}
```

### Phase 3: Aggregates Implementation (Week 3)

**Step 1: Create Base Aggregate**
```php
// app/Aggregates/AggregateRoot.php
abstract class AggregateRoot extends \Spatie\EventSourcing\AggregateRoots\AggregateRoot
{
    protected function getStoredEventClass(): string
    {
        return StoredEvent::class;
    }

    protected function getSnapshotClass(): string
    {
        return Snapshot::class;
    }

    public function getState(): array
    {
        return [
            'aggregate_id' => $this->aggregateRootUuid(),
            'version' => $this->aggregateVersion(),
            'state' => $this->toArray(),
        ];
    }

    abstract public function toArray(): array;
}
```

**Step 2: Implement User Aggregate**
```php
// app/Aggregates/UserAggregate.php
class UserAggregate extends AggregateRoot
{
    // Implementation as shown in patterns section
}
```

### Phase 4: Event Replay and Recovery (Week 4)

**Step 1: Implement Event Replay**
```php
// app/Console/Commands/ReplayEvents.php
class ReplayEvents extends Command
{
    protected $signature = 'events:replay {--from-date=} {--aggregate-id=}';

    public function handle()
    {
        $fromDate = $this->option('from-date') ? Carbon::parse($this->option('from-date')) : null;
        $aggregateId = $this->option('aggregate-id');

        $query = StoredEvent::query();

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($aggregateId) {
            $query->where('aggregate_root_id', $aggregateId);
        }

        $events = $query->orderBy('created_at')->get();

        foreach ($events as $event) {
            $this->replayEvent($event);
        }
    }

    private function replayEvent(StoredEvent $storedEvent): void
    {
        $eventClass = $storedEvent->event_class;
        $eventData = $storedEvent->event_data;

        $event = new $eventClass(...array_values($eventData));

        // Dispatch to projectors and reactors
        event($event);
    }
}
```

## Testing and Validation

### Event Store Testing

```php
// tests/Unit/EventStore/EventStoreTest.php
class EventStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_and_retrieve_events()
    {
        $userId = UserId::generate();
        $aggregate = UserAggregate::register(
            $userId,
            Email::fromString('test@example.com'),
            UserType::STANDARD,
            ['name' => 'Test User']
        );

        app(EventStore::class)->append($aggregate);

        $retrievedAggregate = UserAggregate::retrieve($userId->toString());

        $this->assertEquals($userId->toString(), $retrievedAggregate->getAggregateRootId());
        $this->assertEquals(1, $retrievedAggregate->getVersion());
    }

    public function test_event_ordering_is_maintained()
    {
        $userId = UserId::generate();
        $aggregate = UserAggregate::register(
            $userId,
            Email::fromString('test@example.com'),
            UserType::STANDARD,
            ['name' => 'Test User']
        );

        $aggregate->activate(UserId::generate());
        $aggregate->deactivate(UserId::generate(), 'Test reason');

        app(EventStore::class)->append($aggregate);

        $events = app(EventStore::class)->retrieve($userId->toString());

        $this->assertCount(3, $events);
        $this->assertInstanceOf(UserRegistrationInitiated::class, $events[0]);
        $this->assertInstanceOf(UserActivated::class, $events[1]);
        $this->assertInstanceOf(UserDeactivated::class, $events[2]);
    }
}
```

### Event Versioning Testing

```php
// tests/Unit/EventVersioning/EventUpcastingTest.php
class EventUpcastingTest extends TestCase
{
    public function test_can_upcast_v1_user_registered_to_v2()
    {
        $v1EventData = [
            'user_id' => 'user_123',
            'email' => 'test@example.com',
            'registration_data' => ['name' => 'Test User']
        ];

        $upcaster = new EventUpcaster();
        $v2EventData = $upcaster->upcast(UserRegisteredV2::class, $v1EventData, 1);

        $this->assertEquals('Standard', $v2EventData['user_type']);
        $this->assertEquals($v1EventData['user_id'], $v2EventData['user_id']);
    }
}
```

### Snapshot Testing

```php
// tests/Unit/Snapshots/SnapshotTest.php
class SnapshotTest extends TestCase
{
    public function test_can_create_and_restore_from_snapshot()
    {
        $userId = UserId::generate();
        $aggregate = UserAggregate::register(
            $userId,
            Email::fromString('test@example.com'),
            UserType::STANDARD,
            ['name' => 'Test User']
        );

        // Generate 100 events to trigger snapshot
        for ($i = 0; $i < 99; $i++) {
            $aggregate->updateProfile(['last_activity' => now()]);
        }

        app(EventStore::class)->append($aggregate);

        // Verify snapshot was created
        $snapshot = app(SnapshotStore::class)->retrieve($userId->toString());
        $this->assertNotNull($snapshot);
        $this->assertEquals(100, $snapshot->getVersion());

        // Verify can restore from snapshot
        $restoredAggregate = UserAggregate::retrieve($userId->toString());
        $this->assertEquals(100, $restoredAggregate->getVersion());
    }
}
```

## Performance Considerations

### SQLite Optimization for Event Store

**WAL Mode Configuration**:
```php
// config/database.php
'sqlite' => [
    'driver' => 'sqlite',
    'database' => database_path('database.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => true,
    'pragma' => [
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'cache_size' => -64000, // 64MB
        'temp_store' => 'MEMORY',
        'mmap_size' => 268435456, // 256MB
    ],
],
```

**Event Store Indexing Strategy**:
```sql
-- Primary indexes for event retrieval
CREATE INDEX idx_stored_events_aggregate_version 
ON stored_events(aggregate_root_id, aggregate_version);

-- Index for event replay by date
CREATE INDEX idx_stored_events_created_class 
ON stored_events(created_at, event_class);

-- Index for event type queries
CREATE INDEX idx_stored_events_class_created 
ON stored_events(event_class, created_at);

-- Partial index for recent events (hot data)
CREATE INDEX idx_stored_events_recent 
ON stored_events(aggregate_root_id, created_at) 
WHERE created_at > datetime('now', '-30 days');
```

### Event Batching for Performance

```php
class BatchEventProcessor
{
    private array $eventBatch = [];
    private int $batchSize;

    public function __construct(int $batchSize = 100)
    {
        $this->batchSize = $batchSize;
    }

    public function addEvent(DomainEvent $event): void
    {
        $this->eventBatch[] = $event;

        if (count($this->eventBatch) >= $this->batchSize) {
            $this->processBatch();
        }
    }

    public function processBatch(): void
    {
        if (empty($this->eventBatch)) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->eventBatch as $event) {
                // Process event
                $this->processEvent($event);
            }
        });

        $this->eventBatch = [];
    }
}
```

## Security Considerations

### Event Data Encryption

```php
class EncryptedEventSerializer implements EventSerializer
{
    private Encrypter $encrypter;
    private array $encryptedFields = ['email', 'name', 'personal_data'];

    public function serialize(DomainEvent $event): array
    {
        $data = $event->toArray();

        foreach ($this->encryptedFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->encrypter->encrypt($data[$field]);
            }
        }

        return $data;
    }

    public function deserialize(string $eventClass, array $eventData): DomainEvent
    {
        foreach ($this->encryptedFields as $field) {
            if (isset($eventData[$field])) {
                $eventData[$field] = $this->encrypter->decrypt($eventData[$field]);
            }
        }

        return new $eventClass(...array_values($eventData));
    }
}
```

### GDPR Event Anonymization

```php
class GdprEventAnonymizer
{
    public function anonymizeUserEvents(string $userId): string
    {
        $anonymizationToken = $this->generateAnonymizationToken($userId);

        DB::transaction(function () use ($userId, $anonymizationToken) {
            // Anonymize stored events
            $this->anonymizeStoredEvents($userId, $anonymizationToken);

            // Anonymize snapshots
            $this->anonymizeSnapshots($userId, $anonymizationToken);

            // Record anonymization event
            $this->recordAnonymizationEvent($userId, $anonymizationToken);
        });

        return $anonymizationToken;
    }

    private function anonymizeStoredEvents(string $userId, string $token): void
    {
        $events = StoredEvent::where('aggregate_root_id', $userId)->get();

        foreach ($events as $event) {
            $eventData = $event->event_data;
            $eventData = $this->replacePersonalData($eventData, $token);

            $event->update([
                'aggregate_root_id' => $token,
                'event_data' => $eventData
            ]);
        }
    }

    private function replacePersonalData(array $data, string $token): array
    {
        $personalFields = ['email', 'name', 'phone', 'address'];

        foreach ($personalFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[ANONYMIZED]';
            }
        }

        if (isset($data['user_id'])) {
            $data['user_id'] = $token;
        }

        return $data;
    }
}
```

## Troubleshooting Guide

### Common Issues and Solutions

**Issue: Event Ordering Problems**
```php
// Problem: Events appear out of order
// Solution: Ensure proper aggregate version handling

class UserAggregate extends AggregateRoot
{
    protected function recordThat(DomainEvent $event): void
    {
        // Ensure version is incremented before recording
        $this->aggregateVersion++;
        parent::recordThat($event);
    }
}
```

**Issue: Snapshot Corruption**
```php
// Problem: Snapshots contain invalid state
// Solution: Validate snapshot integrity

class SnapshotValidator
{
    public function validate(AggregateSnapshot $snapshot): bool
    {
        $state = $snapshot->getState();

        // Validate required fields
        if (!isset($state['aggregate_id']) || !isset($state['version'])) {
            return false;
        }

        // Validate state consistency
        return $this->validateStateConsistency($state);
    }
}
```

**Issue: Event Replay Performance**
```php
// Problem: Event replay is too slow
// Solution: Use chunked processing

class OptimizedEventReplay
{
    public function replayEvents(string $aggregateId, int $chunkSize = 1000): void
    {
        StoredEvent::where('aggregate_root_id', $aggregateId)
            ->orderBy('aggregate_version')
            ->chunk($chunkSize, function ($events) {
                foreach ($events as $event) {
                    $this->processEvent($event);
                }
            });
    }
}
```

**Issue: Memory Usage During Replay**
```php
// Problem: Memory exhaustion during large event replays
// Solution: Use generators and memory management

class MemoryEfficientReplay
{
    public function replayEventsGenerator(string $aggregateId): \Generator
    {
        $offset = 0;
        $limit = 100;

        do {
            $events = StoredEvent::where('aggregate_root_id', $aggregateId)
                ->orderBy('aggregate_version')
                ->offset($offset)
                ->limit($limit)
                ->get();

            foreach ($events as $event) {
                yield $event;
            }

            $offset += $limit;

            // Force garbage collection
            if ($offset % 1000 === 0) {
                gc_collect_cycles();
            }

        } while ($events->count() === $limit);
    }
}
```

## References and Further Reading

### Essential Resources

**Event-Sourcing Fundamentals**:
- [Event Sourcing Pattern - Martin Fowler](https://martinfowler.com/eaaDev/EventSourcing.html)
- [CQRS Journey - Microsoft](https://docs.microsoft.com/en-us/previous-versions/msp-n-p/jj554200(v=pandp.10))
- [Event Sourcing and CQRS - Greg Young](https://www.eventstore.com/blog/what-is-event-sourcing)

**Laravel Event-Sourcing**:
- [Spatie Laravel Event Sourcing Documentation](https://spatie.be/docs/laravel-event-sourcing)
- [Event Sourcing in Laravel - Freek Van der Herten](https://freek.dev/1562-getting-started-with-event-sourcing-in-laravel)

**SQLite Optimization**:
- [SQLite Performance Tuning](https://www.sqlite.org/optoverview.html)
- [SQLite WAL Mode](https://www.sqlite.org/wal.html)
- [SQLite Query Planner](https://www.sqlite.org/queryplanner.html)

**GDPR and Event-Sourcing**:
- [GDPR and Event Sourcing](https://blog.eventstore.com/gdpr-and-event-sourcing-3c3b1b0b8b0f)
- [Event Sourcing and GDPR Compliance](https://www.eventstore.com/blog/event-sourcing-and-gdpr)

**Domain-Driven Design**:
- [Domain-Driven Design - Eric Evans](https://domainlanguage.com/ddd/)
- [Implementing Domain-Driven Design - Vaughn Vernon](https://vaughnvernon.co/?page_id=168)

### Code Examples and Patterns

**Event-Sourcing Patterns Repository**:
- [Event Sourcing Examples](https://github.com/EventStore/EventStore.Samples)
- [CQRS and Event Sourcing Patterns](https://github.com/gregoryyoung/m-r)

**Laravel Implementation Examples**:
- [Laravel Event Sourcing Examples](https://github.com/spatie/laravel-event-sourcing/tree/main/docs)
- [Event Sourcing in Practice](https://github.com/buttercup-php/event-sourcing)

### Performance and Monitoring

**SQLite Performance**:
- [SQLite Benchmarking](https://www.sqlite.org/speed.html)
- [SQLite Memory Usage](https://www.sqlite.org/malloc.html)

**Event Store Monitoring**:
- [Event Store Monitoring Best Practices](https://www.eventstore.com/blog/monitoring-event-store)
- [Performance Metrics for Event-Sourced Systems](https://blog.eventstore.com/performance-metrics-for-event-sourced-systems)
