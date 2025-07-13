# 1. Architectural Diagrams for UMS-STI

## 1.1. Executive Summary

This document provides comprehensive architectural diagrams for the User Management System with Single Table Inheritance (UMS-STI) using Mermaid syntax. These diagrams illustrate the system's overall architecture, component relationships, data flow, and integration patterns to support understanding and implementation of the event-sourced, CQRS-based system.

## 1.2. Learning Objectives

After reviewing this document, readers will understand:

- **1.2.1.** System architecture overview and component relationships
- **1.2.2.** Event-sourcing and CQRS architectural patterns
- **1.2.3.** Data flow between system components
- **1.2.4.** Integration patterns with external systems
- **1.2.5.** Deployment architecture and infrastructure components

## 1.3. Prerequisite Knowledge

Before reviewing these diagrams, ensure familiarity with:

- **1.3.1.** Event-sourcing and CQRS concepts
- **1.3.2.** Laravel framework architecture
- **1.3.3.** Single Table Inheritance patterns
- **1.3.4.** Filament admin panel architecture
- **1.3.5.** SQLite database design

## 1.4. System Architecture Overview

### 1.4.1. High-Level System Architecture

```mermaid
graph TB
    subgraph "Presentation Layer"
        UI[Filament Admin UI]
        API[REST API]
        CLI[Artisan Commands]
    end

    subgraph "Application Layer"
        Controllers[Controllers]
        Commands[Command Handlers]
        Queries[Query Handlers]
        Middleware[Middleware]
    end

    subgraph "Domain Layer"
        Aggregates[Aggregates]
        Events[Domain Events]
        ValueObjects[Value Objects]
        Policies[Business Policies]
    end

    subgraph "Infrastructure Layer"
        EventStore[(Event Store)]
        ReadModels[(Read Models)]
        Cache[(Redis Cache)]
        Queue[Queue System]
    end

    subgraph "External Systems"
        Email[Email Service]
        Analytics[Analytics Service]
        Monitoring[Monitoring]
    end

    UI --> Controllers
    API --> Controllers
    CLI --> Commands

    Controllers --> Commands
    Controllers --> Queries
    Controllers --> Middleware

    Commands --> Aggregates
    Queries --> ReadModels

    Aggregates --> Events
    Events --> EventStore
    Events --> Queue

    Queue --> Email
    Queue --> Analytics
    Queue --> Monitoring

    ReadModels --> Cache
    EventStore --> ReadModels

    classDef presentation fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef application fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef domain fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef infrastructure fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef external fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff

    class UI,API,CLI presentation
    class Controllers,Commands,Queries,Middleware application
    class Aggregates,Events,ValueObjects,Policies domain
    class EventStore,ReadModels,Cache,Queue infrastructure
    class Email,Analytics,Monitoring external
```

### 1.4.2. Event-Sourcing Architecture

```mermaid
graph LR
    subgraph "Command Side (Write)"
        CMD[Commands] --> CH[Command Handlers]
        CH --> AGG[Aggregates]
        AGG --> EVT[Domain Events]
        EVT --> ES[(Event Store)]
    end

    subgraph "Query Side (Read)"
        QRY[Queries] --> QH[Query Handlers]
        QH --> RM[(Read Models)]
    end

    subgraph "Event Processing"
        ES --> PROJ[Projectors]
        ES --> REACT[Reactors]
        PROJ --> RM
        REACT --> SE[Side Effects]
    end

    subgraph "Side Effects"
        SE --> EMAIL[Email Notifications]
        SE --> AUDIT[Audit Logs]
        SE --> ANALYTICS[Analytics]
        SE --> GDPR[GDPR Compliance]
    end

    classDef command fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
    classDef query fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef event fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef sideeffect fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff

    class CMD,CH,AGG command
    class QRY,QH,RM query
    class EVT,ES,PROJ,REACT event
    class SE,EMAIL,AUDIT,ANALYTICS,GDPR sideeffect
```

### 1.4.3. CQRS Data Flow

```mermaid
sequenceDiagram
    participant Client
    participant CommandBus
    participant CommandHandler
    participant Aggregate
    participant EventStore
    participant Projector
    participant ReadModel
    participant QueryBus
    participant QueryHandler

    Note over Client,QueryHandler: Command Flow (Write)
    Client->>CommandBus: Dispatch Command
    CommandBus->>CommandHandler: Handle Command
    CommandHandler->>Aggregate: Execute Business Logic
    Aggregate->>EventStore: Store Domain Events
    EventStore->>Projector: Publish Events
    Projector->>ReadModel: Update Projections

    Note over Client,QueryHandler: Query Flow (Read)
    Client->>QueryBus: Execute Query
    QueryBus->>QueryHandler: Handle Query
    QueryHandler->>ReadModel: Fetch Data
    ReadModel-->>QueryHandler: Return Data
    QueryHandler-->>QueryBus: Return Result
    QueryBus-->>Client: Return Response
```

## 1.5. Component Architecture

### 1.5.1. User Management Components

```mermaid
graph TB
    subgraph "User Domain"
        UA[User Aggregate]
        UE[User Events]
        UP[User Projector]
        URM[User Read Models]
    end

    subgraph "Team Domain"
        TA[Team Aggregate]
        TE[Team Events]
        TP[Team Projector]
        TRM[Team Read Models]
    end

    subgraph "Permission Domain"
        PA[Permission Aggregate]
        PE[Permission Events]
        PP[Permission Projector]
        PRM[Permission Read Models]
    end

    subgraph "Shared Infrastructure"
        ES[(Event Store)]
        CACHE[(Cache)]
        QUEUE[Queue System]
    end

    UA --> UE
    UE --> ES
    ES --> UP
    UP --> URM
    URM --> CACHE

    TA --> TE
    TE --> ES
    ES --> TP
    TP --> TRM
    TRM --> CACHE

    PA --> PE
    PE --> ES
    ES --> PP
    PP --> PRM
    PRM --> CACHE

    UE --> QUEUE
    TE --> QUEUE
    PE --> QUEUE

    classDef domain fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef infrastructure fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff

    class UA,UE,UP,URM,TA,TE,TP,TRM,PA,PE,PP,PRM domain
    class ES,CACHE,QUEUE infrastructure
```

### 1.5.2. Filament Integration Architecture

```mermaid
graph TB
    subgraph "Filament Admin Panel"
        FR[Filament Resources]
        FP[Filament Pages]
        FF[Filament Forms]
        FT[Filament Tables]
    end

    subgraph "Application Layer"
        CB[Command Bus]
        QB[Query Bus]
        VAL[Validators]
        AUTH[Authorization]
    end

    subgraph "Domain Layer"
        CMD[Commands]
        QRY[Queries]
        POL[Policies]
    end

    subgraph "Infrastructure"
        ES[(Event Store)]
        RM[(Read Models)]
        CACHE[(Cache)]
    end

    FR --> FF
    FR --> FT
    FP --> FF
    FP --> FT

    FF --> CB
    FT --> QB

    CB --> VAL
    CB --> AUTH
    QB --> AUTH

    VAL --> CMD
    AUTH --> POL

    CB --> CMD
    QB --> QRY

    CMD --> ES
    QRY --> RM
    RM --> CACHE

    classDef filament fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef application fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef domain fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef infrastructure fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff

    class FR,FP,FF,FT filament
    class CB,QB,VAL,AUTH application
    class CMD,QRY,POL domain
    class ES,RM,CACHE infrastructure
```

## 1.6. Data Architecture

### 1.6.1. Event Store and Read Model Architecture

```mermaid
graph TB
    subgraph "Event Store (SQLite)"
        SE[Stored Events]
        SS[Snapshots]
        META[Event Metadata]
    end

    subgraph "Read Models (SQLite)"
        UP[User Projections]
        TP[Team Projections]
        TH[Team Hierarchy]
        TM[Team Members]
        PERM[Permissions]
        AUDIT[Audit Logs]
    end

    subgraph "Cache Layer (Redis)"
        UC[User Cache]
        TC[Team Cache]
        PC[Permission Cache]
        QC[Query Cache]
    end

    subgraph "Analytics Store"
        AE[Analytics Events]
        AM[Analytics Metrics]
        DAU[Daily Active Users]
    end

    SE --> UP
    SE --> TP
    SE --> TH
    SE --> TM
    SE --> PERM
    SE --> AUDIT
    SE --> AE
    SE --> AM

    UP --> UC
    TP --> TC
    PERM --> PC

    SS --> UC
    SS --> TC

    META --> AUDIT

    classDef eventstore fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef readmodel fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef cache fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef analytics fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff

    class SE,SS,META eventstore
    class UP,TP,TH,TM,PERM,AUDIT readmodel
    class UC,TC,PC,QC cache
    class AE,AM,DAU analytics
```

### 1.6.2. Single Table Inheritance Structure

```mermaid
erDiagram
    USERS {
        string id PK
        string email UK
        string name
        string user_type
        string state
        json profile_data
        timestamp created_at
        timestamp updated_at
        timestamp activated_at
        timestamp deactivated_at
        string activated_by FK
        string deactivated_by FK
    }

    USER_PROFILES {
        string id PK
        string user_id FK
        string avatar_url
        text bio
        json preferences
        json settings
        timestamp created_at
        timestamp updated_at
    }

    ADMIN_PROFILES {
        string id PK
        string user_id FK
        json admin_permissions
        json admin_settings
        timestamp last_admin_action
        timestamp created_at
        timestamp updated_at
    }

    GUEST_PROFILES {
        string id PK
        string user_id FK
        string session_id
        json tracking_data
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    USERS ||--o| USER_PROFILES : "has"
    USERS ||--o| ADMIN_PROFILES : "has (if admin)"
    USERS ||--o| GUEST_PROFILES : "has (if guest)"
```

## 1.7. Integration Architecture

### 1.7.1. External System Integration

```mermaid
graph TB
    subgraph "UMS-STI Core"
        CORE[Core System]
        EVENTS[Event Stream]
        REACTORS[Reactors]
    end

    subgraph "Email Services"
        MAIL[Mail Service]
        SMTP[SMTP Server]
        TEMPLATES[Email Templates]
    end

    subgraph "Analytics Platform"
        ANALYTICS[Analytics Service]
        METRICS[Metrics Collector]
        DASHBOARD[Analytics Dashboard]
    end

    subgraph "Monitoring & Logging"
        LOGS[Log Aggregator]
        METRICS_MON[Metrics Monitor]
        ALERTS[Alert System]
    end

    subgraph "External APIs"
        SSO[SSO Provider]
        WEBHOOK[Webhook Endpoints]
        API_GATEWAY[API Gateway]
    end

    CORE --> EVENTS
    EVENTS --> REACTORS

    REACTORS --> MAIL
    REACTORS --> ANALYTICS
    REACTORS --> LOGS

    MAIL --> SMTP
    MAIL --> TEMPLATES

    ANALYTICS --> METRICS
    ANALYTICS --> DASHBOARD

    LOGS --> METRICS_MON
    METRICS_MON --> ALERTS

    CORE --> SSO
    CORE --> WEBHOOK
    CORE --> API_GATEWAY

    classDef core fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef email fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef analytics fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef monitoring fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef external fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff

    class CORE,EVENTS,REACTORS core
    class MAIL,SMTP,TEMPLATES email
    class ANALYTICS,METRICS,DASHBOARD analytics
    class LOGS,METRICS_MON,ALERTS monitoring
    class SSO,WEBHOOK,API_GATEWAY external
```

### 1.7.2. Queue and Job Processing Architecture

```mermaid
graph TB
    subgraph "Event Processing"
        EVENTS[Domain Events]
        DISPATCHER[Event Dispatcher]
    end

    subgraph "Queue System"
        DEFAULT_Q[Default Queue]
        PROJ_Q[Projections Queue]
        NOTIF_Q[Notifications Queue]
        ANALYTICS_Q[Analytics Queue]
        GDPR_Q[GDPR Queue]
    end

    subgraph "Job Workers"
        DEFAULT_W[Default Worker]
        PROJ_W[Projection Worker]
        NOTIF_W[Notification Worker]
        ANALYTICS_W[Analytics Worker]
        GDPR_W[GDPR Worker]
    end

    subgraph "Job Types"
        PROJ_JOBS[Projection Jobs]
        EMAIL_JOBS[Email Jobs]
        AUDIT_JOBS[Audit Jobs]
        ANALYTICS_JOBS[Analytics Jobs]
        GDPR_JOBS[GDPR Jobs]
    end

    EVENTS --> DISPATCHER

    DISPATCHER --> DEFAULT_Q
    DISPATCHER --> PROJ_Q
    DISPATCHER --> NOTIF_Q
    DISPATCHER --> ANALYTICS_Q
    DISPATCHER --> GDPR_Q

    DEFAULT_Q --> DEFAULT_W
    PROJ_Q --> PROJ_W
    NOTIF_Q --> NOTIF_W
    ANALYTICS_Q --> ANALYTICS_W
    GDPR_Q --> GDPR_W

    PROJ_W --> PROJ_JOBS
    NOTIF_W --> EMAIL_JOBS
    DEFAULT_W --> AUDIT_JOBS
    ANALYTICS_W --> ANALYTICS_JOBS
    GDPR_W --> GDPR_JOBS

    classDef events fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef queue fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef worker fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef jobs fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff

    class EVENTS,DISPATCHER events
    class DEFAULT_Q,PROJ_Q,NOTIF_Q,ANALYTICS_Q,GDPR_Q queue
    class DEFAULT_W,PROJ_W,NOTIF_W,ANALYTICS_W,GDPR_W worker
    class PROJ_JOBS,EMAIL_JOBS,AUDIT_JOBS,ANALYTICS_JOBS,GDPR_JOBS jobs
```

## 1.8. Deployment Architecture

### 1.8.1. Production Deployment Architecture

```mermaid
graph TB
    subgraph "Load Balancer"
        LB[Load Balancer]
    end

    subgraph "Application Servers"
        APP1[App Server 1]
        APP2[App Server 2]
        APP3[App Server 3]
    end

    subgraph "Queue Workers"
        WORKER1[Worker 1]
        WORKER2[Worker 2]
        WORKER3[Worker 3]
    end

    subgraph "Database Layer"
        DB_PRIMARY[(SQLite Primary)]
        DB_BACKUP[(SQLite Backup)]
    end

    subgraph "Cache Layer"
        REDIS_PRIMARY[(Redis Primary)]
        REDIS_REPLICA[(Redis Replica)]
    end

    subgraph "File Storage"
        STORAGE[File Storage]
        BACKUP_STORAGE[Backup Storage]
    end

    subgraph "Monitoring"
        MONITORING[Monitoring Service]
        LOGS[Log Aggregation]
        METRICS[Metrics Collection]
    end

    LB --> APP1
    LB --> APP2
    LB --> APP3

    APP1 --> DB_PRIMARY
    APP2 --> DB_PRIMARY
    APP3 --> DB_PRIMARY

    APP1 --> REDIS_PRIMARY
    APP2 --> REDIS_PRIMARY
    APP3 --> REDIS_PRIMARY

    WORKER1 --> DB_PRIMARY
    WORKER2 --> DB_PRIMARY
    WORKER3 --> DB_PRIMARY

    WORKER1 --> REDIS_PRIMARY
    WORKER2 --> REDIS_PRIMARY
    WORKER3 --> REDIS_PRIMARY

    DB_PRIMARY --> DB_BACKUP
    REDIS_PRIMARY --> REDIS_REPLICA

    APP1 --> STORAGE
    APP2 --> STORAGE
    APP3 --> STORAGE

    STORAGE --> BACKUP_STORAGE

    APP1 --> MONITORING
    APP2 --> MONITORING
    APP3 --> MONITORING
    WORKER1 --> MONITORING
    WORKER2 --> MONITORING
    WORKER3 --> MONITORING

    MONITORING --> LOGS
    MONITORING --> METRICS

    classDef loadbalancer fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef application fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef worker fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef database fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef cache fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
    classDef storage fill:#00796b,stroke:#004d40,stroke-width:2px,color:#ffffff
    classDef monitoring fill:#fbc02d,stroke:#f57f17,stroke-width:2px,color:#000000

    class LB loadbalancer
    class APP1,APP2,APP3 application
    class WORKER1,WORKER2,WORKER3 worker
    class DB_PRIMARY,DB_BACKUP database
    class REDIS_PRIMARY,REDIS_REPLICA cache
    class STORAGE,BACKUP_STORAGE storage
    class MONITORING,LOGS,METRICS monitoring
```

### 1.8.2. Development Environment Architecture

```mermaid
graph TB
    subgraph "Development Environment"
        DEV_APP[Laravel App]
        DEV_DB[(SQLite Dev)]
        DEV_REDIS[(Redis Dev)]
        DEV_QUEUE[Queue Worker]
    end

    subgraph "Testing Environment"
        TEST_APP[Test Suite]
        TEST_DB[(SQLite Test)]
        TEST_REDIS[(Redis Test)]
        MOCK_SERVICES[Mock Services]
    end

    subgraph "Local Tools"
        FILAMENT[Filament Panel]
        ARTISAN[Artisan CLI]
        TELESCOPE[Laravel Telescope]
        HORIZON[Laravel Horizon]
    end

    subgraph "External Services (Dev)"
        MAILHOG[MailHog]
        LOG_VIEWER[Log Viewer]
        DEBUG_BAR[Debug Bar]
    end

    DEV_APP --> DEV_DB
    DEV_APP --> DEV_REDIS
    DEV_APP --> DEV_QUEUE

    TEST_APP --> TEST_DB
    TEST_APP --> TEST_REDIS
    TEST_APP --> MOCK_SERVICES

    DEV_APP --> FILAMENT
    DEV_APP --> ARTISAN
    DEV_APP --> TELESCOPE
    DEV_APP --> HORIZON

    DEV_APP --> MAILHOG
    DEV_APP --> LOG_VIEWER
    DEV_APP --> DEBUG_BAR

    classDef development fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef testing fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef tools fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef external fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff

    class DEV_APP,DEV_DB,DEV_REDIS,DEV_QUEUE development
    class TEST_APP,TEST_DB,TEST_REDIS,MOCK_SERVICES testing
    class FILAMENT,ARTISAN,TELESCOPE,HORIZON tools
    class MAILHOG,LOG_VIEWER,DEBUG_BAR external
```

## 1.9. Security Architecture

### 1.9.1. Authentication and Authorization Flow

```mermaid
sequenceDiagram
    participant User
    participant Filament
    participant Auth
    participant Policy
    participant Command
    participant Aggregate
    participant Event
    participant Audit

    User->>Filament: Login Request
    Filament->>Auth: Authenticate User
    Auth-->>Filament: Authentication Result

    User->>Filament: Perform Action
    Filament->>Policy: Check Authorization
    Policy-->>Filament: Authorization Result

    alt Authorized
        Filament->>Command: Dispatch Command
        Command->>Aggregate: Execute Business Logic
        Aggregate->>Event: Generate Domain Event
        Event->>Audit: Log Security Event
        Event-->>Filament: Success Response
        Filament-->>User: Action Completed
    else Unauthorized
        Filament->>Audit: Log Unauthorized Attempt
        Filament-->>User: Access Denied
    end
```

### 1.9.2. Data Protection Architecture

```mermaid
graph TB
    subgraph "Data Classification"
        PII[Personal Data]
        AUDIT_DATA[Audit Data]
        SYSTEM_DATA[System Data]
        ANALYTICS_DATA[Analytics Data]
    end

    subgraph "Protection Mechanisms"
        ENCRYPTION[Encryption at Rest]
        HASHING[Data Hashing]
        ANONYMIZATION[Data Anonymization]
        TOKENIZATION[Data Tokenization]
    end

    subgraph "Access Controls"
        RBAC[Role-Based Access]
        POLICIES[Data Policies]
        AUDIT_TRAIL[Audit Trail]
        GDPR_CONTROLS[GDPR Controls]
    end

    subgraph "Compliance"
        RETENTION[Data Retention]
        DELETION[Data Deletion]
        EXPORT[Data Export]
        CONSENT[Consent Management]
    end

    PII --> ENCRYPTION
    PII --> ANONYMIZATION
    AUDIT_DATA --> HASHING
    SYSTEM_DATA --> TOKENIZATION
    ANALYTICS_DATA --> ANONYMIZATION

    ENCRYPTION --> RBAC
    HASHING --> POLICIES
    ANONYMIZATION --> AUDIT_TRAIL
    TOKENIZATION --> GDPR_CONTROLS

    RBAC --> RETENTION
    POLICIES --> DELETION
    AUDIT_TRAIL --> EXPORT
    GDPR_CONTROLS --> CONSENT

    classDef data fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef protection fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef access fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef compliance fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff

    class PII,AUDIT_DATA,SYSTEM_DATA,ANALYTICS_DATA data
    class ENCRYPTION,HASHING,ANONYMIZATION,TOKENIZATION protection
    class RBAC,POLICIES,AUDIT_TRAIL,GDPR_CONTROLS access
    class RETENTION,DELETION,EXPORT,CONSENT compliance
```

## 1.10. Performance Architecture

### 1.10.1. Caching Strategy

```mermaid
graph TB
    subgraph "Application Layer"
        APP[Application]
        QUERIES[Query Handlers]
    end

    subgraph "Cache Layers"
        L1[L1: Application Cache]
        L2[L2: Redis Cache]
        L3[L3: Database Cache]
    end

    subgraph "Cache Types"
        USER_CACHE[User Cache]
        TEAM_CACHE[Team Cache]
        PERM_CACHE[Permission Cache]
        QUERY_CACHE[Query Result Cache]
    end

    subgraph "Cache Invalidation"
        EVENT_INVALIDATION[Event-Based Invalidation]
        TTL_INVALIDATION[TTL-Based Invalidation]
        MANUAL_INVALIDATION[Manual Invalidation]
    end

    subgraph "Data Sources"
        READ_MODELS[(Read Models)]
        EVENT_STORE[(Event Store)]
    end

    APP --> L1
    QUERIES --> L1

    L1 --> L2
    L2 --> L3
    L3 --> READ_MODELS
    L3 --> EVENT_STORE

    L2 --> USER_CACHE
    L2 --> TEAM_CACHE
    L2 --> PERM_CACHE
    L2 --> QUERY_CACHE

    USER_CACHE --> EVENT_INVALIDATION
    TEAM_CACHE --> EVENT_INVALIDATION
    PERM_CACHE --> EVENT_INVALIDATION
    QUERY_CACHE --> TTL_INVALIDATION

    EVENT_INVALIDATION --> MANUAL_INVALIDATION

    classDef application fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef cache fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef cachetype fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef invalidation fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef datasource fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff

    class APP,QUERIES application
    class L1,L2,L3 cache
    class USER_CACHE,TEAM_CACHE,PERM_CACHE,QUERY_CACHE cachetype
    class EVENT_INVALIDATION,TTL_INVALIDATION,MANUAL_INVALIDATION invalidation
    class READ_MODELS,EVENT_STORE datasource
```

## 1.11. Cross-References

### 1.11.1. Related Diagrams

- **ERD Diagrams**: See [020-erd-diagrams.md](020-erd-diagrams.md) for detailed entity relationships
- **Business Process Flows**: See [030-business-process-flows.md](030-business-process-flows.md) for workflow diagrams
- **Swim Lanes**: See [040-swim-lanes.md](040-swim-lanes.md) for responsibility mapping
- **Domain Models**: See [050-domain-models.md](050-domain-models.md) for domain-specific diagrams
- **FSM Diagrams**: See [060-fsm-diagrams.md](060-fsm-diagrams.md) for state machine diagrams

### 1.11.2. Related Documentation

- **Event-Sourcing Architecture**: See [../070-event-sourcing-cqrs/010-event-sourcing-architecture.md](../070-event-sourcing-cqrs/010-event-sourcing-architecture.md)
- **CQRS Implementation**: See [../070-event-sourcing-cqrs/020-cqrs-implementation.md](../070-event-sourcing-cqrs/020-cqrs-implementation.md)
- **Database Foundation**: See [../020-database-foundation/010-database-design.md](../020-database-foundation/010-database-design.md)
- **User Models**: See [../030-user-models/010-sti-architecture-explained.md](../030-user-models/010-sti-architecture-explained.md)
- **Team Hierarchy**: See [../040-team-hierarchy/010-closure-table-theory.md](../040-team-hierarchy/010-closure-table-theory.md)

## 1.12. References and Further Reading

### 1.12.1. Architecture Patterns

- [Clean Architecture - Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Hexagonal Architecture - Alistair Cockburn](https://alistair.cockburn.us/hexagonal-architecture/)
- [Event-Driven Architecture Patterns](https://microservices.io/patterns/data/event-driven-architecture.html)

### 1.12.2. Event-Sourcing and CQRS

- [Event Sourcing Pattern - Martin Fowler](https://martinfowler.com/eaaDev/EventSourcing.html)
- [CQRS Pattern - Martin Fowler](https://martinfowler.com/bliki/CQRS.html)
- [Event Sourcing and CQRS - Greg Young](https://cqrs.files.wordpress.com/2010/11/cqrs_documents.pdf)

### 1.12.3. Laravel Architecture

- [Laravel Architecture Concepts](https://laravel.com/docs/architecture-concepts)
- [Laravel Service Container](https://laravel.com/docs/container)
- [Laravel Event System](https://laravel.com/docs/events)
