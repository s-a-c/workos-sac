# 6. Finite State Machine Diagrams for UMS-STI

## 6.1. Executive Summary

This document provides comprehensive Finite State Machine (FSM) diagrams for the User Management System with Single Table Inheritance (UMS-STI) using Mermaid syntax. These diagrams illustrate the state transitions, lifecycle management, and business rules that govern entity states within the event-sourced, CQRS-based system with consistent state management across User and Team entities.

## 6.2. Learning Objectives

After reviewing this document, readers will understand:

- **6.2.1.** User lifecycle state transitions and business rules
- **6.2.2.** Team state management and hierarchy implications
- **6.2.3.** Permission and role assignment state flows
- **6.2.4.** Event-driven state transition triggers
- **6.2.5.** Cross-entity state consistency requirements
- **6.2.6.** Error handling and recovery state patterns

## 6.3. Prerequisite Knowledge

Before reviewing these diagrams, ensure familiarity with:

- **6.3.1.** Finite state machine concepts and notation
- **6.3.2.** Event-sourcing state management patterns
- **6.3.3.** CQRS command and event handling
- **6.3.4.** Business rule validation and constraints
- **6.3.5.** Entity lifecycle management principles

## 6.4. User Entity State Machines

### 6.4.1. Core User Lifecycle FSM

```mermaid
stateDiagram-v2
    [*] --> Pending : UserRegistered
    
    Pending --> Active : UserActivated
    Pending --> Inactive : UserDeactivated
    Pending --> Suspended : UserSuspended
    Pending --> Archived : UserArchived
    
    Active --> Inactive : UserDeactivated
    Active --> Suspended : UserSuspended
    Active --> Archived : UserArchived
    
    Inactive --> Active : UserActivated
    Inactive --> Suspended : UserSuspended
    Inactive --> Archived : UserArchived
    
    Suspended --> Active : UserUnsuspended
    Suspended --> Inactive : UserDeactivated
    Suspended --> Archived : UserArchived
    
    Archived --> [*] : UserPurged
    
    note right of Pending
        Initial state after registration
        Requires activation to become active
        Can be deactivated or suspended
    end note
    
    note right of Active
        Fully functional user state
        Can perform all authorized actions
        Can transition to any other state
    end note
    
    note right of Inactive
        User exists but cannot login
        Profile data preserved
        Can be reactivated
    end note
    
    note right of Suspended
        Temporary restriction state
        Login blocked, data preserved
        Requires unsuspension or escalation
    end note
    
    note right of Archived
        Long-term storage state
        Limited data access
        Final state before purging
    end note
```

### 6.4.2. User Type-Specific State Machines

#### 6.4.2.1. Standard User FSM

```mermaid
stateDiagram-v2
    [*] --> PendingVerification : StandardUserRegistered
    
    PendingVerification --> Active : EmailVerified
    PendingVerification --> Expired : VerificationExpired
    
    Active --> Inactive : UserDeactivated
    Active --> Suspended : PolicyViolation
    Active --> Archived : UserRequested
    
    Inactive --> Active : UserReactivated
    Inactive --> Archived : LongTermInactive
    
    Suspended --> Active : SuspensionLifted
    Suspended --> Archived : PermanentSuspension
    
    Expired --> PendingVerification : ResendVerification
    Expired --> Archived : CleanupExpired
    
    Archived --> [*] : DataRetentionExpired
    
    state Active {
        [*] --> LoggedOut
        LoggedOut --> LoggedIn : LoginSuccessful
        LoggedIn --> LoggedOut : LogoutInitiated
        LoggedIn --> LoggedOut : SessionExpired
        
        state LoggedIn {
            [*] --> Idle
            Idle --> Active_Session : UserActivity
            Active_Session --> Idle : InactivityTimeout
        }
    }
```

#### 6.4.2.2. Admin User FSM

```mermaid
stateDiagram-v2
    [*] --> PendingApproval : AdminUserCreated
    
    PendingApproval --> Active : AdminApproved
    PendingApproval --> Rejected : AdminRejected
    
    Active --> Inactive : AdminDeactivated
    Active --> Suspended : SecurityBreach
    Active --> UnderReview : SuspiciousActivity
    
    Inactive --> Active : AdminReactivated
    Inactive --> Archived : AdminRemoved
    
    Suspended --> UnderReview : SecurityReview
    Suspended --> Archived : SecurityCompromised
    
    UnderReview --> Active : ReviewPassed
    UnderReview --> Suspended : ReviewFailed
    UnderReview --> Archived : ReviewTerminated
    
    Rejected --> [*] : CleanupRejected
    Archived --> [*] : AdminDataPurged
    
    state Active {
        [*] --> StandardAccess
        StandardAccess --> ElevatedAccess : PrivilegeEscalation
        ElevatedAccess --> StandardAccess : PrivilegeTimeout
        ElevatedAccess --> StandardAccess : PrivilegeRevoked
        
        state ElevatedAccess {
            [*] --> SystemAdmin
            [*] --> UserAdmin
            [*] --> SecurityAdmin
            
            SystemAdmin --> [*] : SessionEnd
            UserAdmin --> [*] : SessionEnd
            SecurityAdmin --> [*] : SessionEnd
        }
    }
```

#### 6.4.2.3. Guest User FSM

```mermaid
stateDiagram-v2
    [*] --> Anonymous : GuestSessionStarted
    
    Anonymous --> Tracked : UserActivity
    Anonymous --> Expired : SessionTimeout
    
    Tracked --> Converting : ConversionIntent
    Tracked --> Expired : SessionTimeout
    Tracked --> Abandoned : UserLeft
    
    Converting --> Converted : RegistrationCompleted
    Converting --> Abandoned : ConversionAbandoned
    Converting --> Expired : ConversionTimeout
    
    Converted --> [*] : UserAccountCreated
    Abandoned --> [*] : SessionCleanup
    Expired --> [*] : SessionCleanup
    
    state Tracked {
        [*] --> Browsing
        Browsing --> Engaging : ContentInteraction
        Engaging --> Browsing : InteractionEnd
        Engaging --> IntentSignal : HighEngagement
        IntentSignal --> Browsing : IntentDeclined
    }
```

## 6.5. Team Entity State Machines

### 6.5.1. Team Lifecycle FSM

```mermaid
stateDiagram-v2
    [*] --> Draft : TeamCreated
    
    Draft --> Active : TeamActivated
    Draft --> Cancelled : TeamCancelled
    
    Active --> Inactive : TeamDeactivated
    Active --> Suspended : TeamSuspended
    Active --> Archived : TeamArchived
    
    Inactive --> Active : TeamReactivated
    Inactive --> Archived : LongTermInactive
    
    Suspended --> Active : SuspensionLifted
    Suspended --> Archived : PermanentSuspension
    
    Cancelled --> [*] : TeamDeleted
    Archived --> [*] : TeamPurged
    
    state Active {
        [*] --> Operational
        Operational --> Maintenance : MaintenanceMode
        Maintenance --> Operational : MaintenanceComplete
        
        state Operational {
            [*] --> AcceptingMembers
            AcceptingMembers --> MembershipClosed : CloseMemership
            MembershipClosed --> AcceptingMembers : OpenMembership
        }
    }
    
    note right of Draft
        Initial creation state
        Configuration in progress
        Not visible to members
    end note
    
    note right of Active
        Fully operational team
        Members can join/leave
        All features available
    end note
    
    note right of Inactive
        Team exists but not operational
        Members preserved
        Limited functionality
    end note
    
    note right of Suspended
        Temporary restriction
        Investigation or policy violation
        Member access blocked
    end note
```

### 6.5.2. Team Membership FSM

```mermaid
stateDiagram-v2
    [*] --> Invited : MemberInvited
    [*] --> Requested : MembershipRequested
    
    Invited --> Active : InvitationAccepted
    Invited --> Declined : InvitationDeclined
    Invited --> Expired : InvitationExpired
    
    Requested --> Active : RequestApproved
    Requested --> Rejected : RequestRejected
    Requested --> Withdrawn : RequestWithdrawn
    
    Active --> Inactive : MemberDeactivated
    Active --> Suspended : MemberSuspended
    Active --> Left : MemberLeft
    Active --> Removed : MemberRemoved
    
    Inactive --> Active : MemberReactivated
    Inactive --> Removed : InactivityCleanup
    
    Suspended --> Active : SuspensionLifted
    Suspended --> Removed : PermanentRemoval
    
    Declined --> [*] : CleanupDeclined
    Expired --> [*] : CleanupExpired
    Rejected --> [*] : CleanupRejected
    Withdrawn --> [*] : CleanupWithdrawn
    Left --> [*] : CleanupLeft
    Removed --> [*] : CleanupRemoved
    
    state Active {
        [*] --> Member
        Member --> Leader : PromotedToLeader
        Leader --> Member : DemotedFromLeader
        Leader --> Admin : PromotedToAdmin
        Admin --> Leader : DemotedToLeader
        Admin --> Member : DemotedToMember
    }
```

## 6.6. Permission and Role State Machines

### 6.6.1. Role Assignment FSM

```mermaid
stateDiagram-v2
    [*] --> Pending : RoleAssigned
    
    Pending --> Active : AssignmentActivated
    Pending --> Cancelled : AssignmentCancelled
    
    Active --> Inactive : RoleDeactivated
    Active --> Suspended : RoleSuspended
    Active --> Revoked : RoleRevoked
    
    Inactive --> Active : RoleReactivated
    Inactive --> Revoked : InactivityRevocation
    
    Suspended --> Active : SuspensionLifted
    Suspended --> Revoked : PermanentRevocation
    
    Cancelled --> [*] : CleanupCancelled
    Revoked --> [*] : CleanupRevoked
    
    state Active {
        [*] --> Standard
        Standard --> Elevated : PrivilegeEscalation
        Elevated --> Standard : PrivilegeTimeout
        
        state Elevated {
            [*] --> TemporaryElevation
            [*] --> PermanentElevation
            
            TemporaryElevation --> [*] : ElevationExpired
            PermanentElevation --> [*] : ElevationRevoked
        }
    }
```

### 6.6.2. Permission Grant FSM

```mermaid
stateDiagram-v2
    [*] --> Requested : PermissionRequested
    [*] --> Granted : DirectGrant
    
    Requested --> Approved : RequestApproved
    Requested --> Denied : RequestDenied
    Requested --> Expired : RequestExpired
    
    Approved --> Active : PermissionActivated
    Granted --> Active : AutoActivated
    
    Active --> Suspended : PermissionSuspended
    Active --> Revoked : PermissionRevoked
    Active --> Expired : PermissionExpired
    
    Suspended --> Active : SuspensionLifted
    Suspended --> Revoked : PermanentRevocation
    
    Denied --> [*] : CleanupDenied
    Expired --> [*] : CleanupExpired
    Revoked --> [*] : CleanupRevoked
    
    state Active {
        [*] --> ReadOnly
        ReadOnly --> ReadWrite : WriteAccessGranted
        ReadWrite --> ReadOnly : WriteAccessRevoked
        ReadWrite --> FullAccess : AdminAccessGranted
        FullAccess --> ReadWrite : AdminAccessRevoked
        FullAccess --> ReadOnly : AccessDowngraded
    }
```

## 6.7. Event-Driven State Transitions

### 6.7.1. Event Processing FSM

```mermaid
stateDiagram-v2
    [*] --> Received : EventReceived
    
    Received --> Validating : ValidationStarted
    
    Validating --> Valid : ValidationPassed
    Validating --> Invalid : ValidationFailed
    
    Valid --> Processing : ProcessingStarted
    Invalid --> Failed : ValidationError
    
    Processing --> Processed : ProcessingCompleted
    Processing --> Failed : ProcessingError
    Processing --> Retrying : RetryableError
    
    Retrying --> Processing : RetryAttempt
    Retrying --> Failed : MaxRetriesExceeded
    
    Processed --> [*] : EventCompleted
    Failed --> [*] : ErrorHandled
    
    state Processing {
        [*] --> StateValidation
        StateValidation --> BusinessRules : StateValid
        BusinessRules --> StateTransition : RulesPassed
        StateTransition --> ProjectionUpdate : TransitionComplete
        ProjectionUpdate --> [*] : UpdateComplete
        
        StateValidation --> [*] : StateInvalid
        BusinessRules --> [*] : RulesFailed
        StateTransition --> [*] : TransitionFailed
        ProjectionUpdate --> [*] : UpdateFailed
    }
```

### 6.7.2. Saga State Machine

```mermaid
stateDiagram-v2
    [*] --> Started : SagaInitiated
    
    Started --> Step1 : FirstStepStarted
    
    Step1 --> Step2 : Step1Completed
    Step1 --> Compensating : Step1Failed
    
    Step2 --> Step3 : Step2Completed
    Step2 --> Compensating : Step2Failed
    
    Step3 --> Completed : Step3Completed
    Step3 --> Compensating : Step3Failed
    
    Compensating --> CompensatingStep2 : Step3Compensated
    CompensatingStep2 --> CompensatingStep1 : Step2Compensated
    CompensatingStep1 --> Failed : Step1Compensated
    
    Completed --> [*] : SagaSucceeded
    Failed --> [*] : SagaFailed
    
    state Compensating {
        [*] --> UndoStep3
        UndoStep3 --> [*] : Step3Undone
    }
    
    state CompensatingStep2 {
        [*] --> UndoStep2
        UndoStep2 --> [*] : Step2Undone
    }
    
    state CompensatingStep1 {
        [*] --> UndoStep1
        UndoStep1 --> [*] : Step1Undone
    }
```

## 6.8. Cross-Entity State Consistency

### 6.8.1. User-Team Consistency FSM

```mermaid
stateDiagram-v2
    [*] --> Consistent : InitialState
    
    Consistent --> UserChanged : UserStateChanged
    Consistent --> TeamChanged : TeamStateChanged
    Consistent --> BothChanged : SimultaneousChange
    
    UserChanged --> Reconciling : ReconciliationStarted
    TeamChanged --> Reconciling : ReconciliationStarted
    BothChanged --> Reconciling : ReconciliationStarted
    
    Reconciling --> Consistent : ReconciliationSucceeded
    Reconciling --> Inconsistent : ReconciliationFailed
    
    Inconsistent --> Reconciling : RetryReconciliation
    Inconsistent --> ManualIntervention : AutoReconciliationFailed
    
    ManualIntervention --> Consistent : ManualResolution
    ManualIntervention --> Inconsistent : ResolutionFailed
    
    state Reconciling {
        [*] --> ValidatingStates
        ValidatingStates --> ApplyingRules : StatesValid
        ApplyingRules --> UpdatingProjections : RulesApplied
        UpdatingProjections --> [*] : ProjectionsUpdated
        
        ValidatingStates --> [*] : ValidationFailed
        ApplyingRules --> [*] : RulesFailed
        UpdatingProjections --> [*] : UpdateFailed
    }
```

## 6.9. Error Handling and Recovery

### 6.9.1. Error Recovery FSM

```mermaid
stateDiagram-v2
    [*] --> Normal : SystemStarted
    
    Normal --> ErrorDetected : ErrorOccurred
    
    ErrorDetected --> Analyzing : AnalysisStarted
    
    Analyzing --> Recoverable : RecoverableError
    Analyzing --> NonRecoverable : FatalError
    
    Recoverable --> Recovering : RecoveryStarted
    NonRecoverable --> Failed : SystemFailed
    
    Recovering --> Normal : RecoverySucceeded
    Recovering --> Failed : RecoveryFailed
    Recovering --> Analyzing : RetryAnalysis
    
    Failed --> Manual : ManualInterventionRequired
    Manual --> Normal : ManualRecovery
    Manual --> [*] : SystemShutdown
    
    state Analyzing {
        [*] --> ErrorClassification
        ErrorClassification --> ImpactAssessment : ErrorClassified
        ImpactAssessment --> RecoveryPlanning : ImpactAssessed
        RecoveryPlanning --> [*] : PlanCreated
    }
    
    state Recovering {
        [*] --> StateRollback
        StateRollback --> DataConsistency : RollbackComplete
        DataConsistency --> ServiceRestart : ConsistencyRestored
        ServiceRestart --> [*] : ServicesRestarted
    }
```

## 6.10. State Transition Rules and Constraints

### 6.10.1. Business Rule Validation

The following business rules govern state transitions:

#### 6.10.1.1. User State Rules
- **Pending → Active**: Requires email verification or admin approval
- **Active → Suspended**: Requires valid reason and authorized actor
- **Suspended → Active**: Requires suspension review and approval
- **Any State → Archived**: Preserves audit trail and relationships
- **Archived → Purged**: Follows data retention policies

#### 6.10.1.2. Team State Rules
- **Draft → Active**: Requires minimum configuration and owner assignment
- **Active → Inactive**: Preserves member relationships and data
- **Inactive → Active**: Validates team configuration and owner status
- **Any State → Archived**: Handles member notifications and cleanup
- **Parent Team Archived**: Child teams must be archived or reassigned

#### 6.10.1.3. Cross-Entity Rules
- **User Archived**: All team memberships become inactive
- **Team Archived**: All member relationships become inactive
- **Admin User Suspended**: Elevated permissions immediately revoked
- **Team Owner Removed**: New owner must be assigned or team archived

### 6.10.2. State Transition Events

Each state transition generates corresponding domain events:

```mermaid
graph LR
    A[State Change Command] --> B[Business Rule Validation]
    B --> C[State Transition]
    C --> D[Domain Event Generated]
    D --> E[Event Store Persistence]
    E --> F[Projection Updates]
    F --> G[Notification Dispatch]
    
    B --> H[Validation Failed]
    H --> I[Error Event Generated]
    
    C --> J[Transition Failed]
    J --> K[Rollback Event Generated]
```

## 6.11. Monitoring and Observability

### 6.11.1. State Transition Metrics

Key metrics to monitor for state machine health:

- **Transition Success Rate**: Percentage of successful state transitions
- **Transition Latency**: Time taken for state changes to complete
- **Error Rate**: Frequency of failed transitions by error type
- **Consistency Lag**: Time between related entity state synchronization
- **Recovery Time**: Duration of error recovery processes

### 6.11.2. State Machine Health Checks

```mermaid
stateDiagram-v2
    [*] --> Healthy : SystemStart
    
    Healthy --> Degraded : PerformanceIssue
    Healthy --> Unhealthy : CriticalError
    
    Degraded --> Healthy : IssueResolved
    Degraded --> Unhealthy : IssueEscalated
    
    Unhealthy --> Degraded : PartialRecovery
    Unhealthy --> Failed : SystemFailure
    
    Failed --> [*] : SystemShutdown
    
    state Healthy {
        [*] --> MonitoringActive
        MonitoringActive --> AlertsConfigured : AlertingEnabled
        AlertsConfigured --> MetricsCollecting : MetricsEnabled
        MetricsCollecting --> [*] : HealthCheckPassed
    }
```

## 6.12. Implementation Guidelines

### 6.12.1. State Machine Implementation

- Use Laravel's state machine packages (e.g., `spatie/laravel-model-states`)
- Implement state guards for business rule validation
- Create state transition listeners for event generation
- Use database transactions for atomic state changes
- Implement compensation patterns for complex workflows

### 6.12.2. Testing Strategy

- Unit tests for individual state transitions
- Integration tests for cross-entity consistency
- Property-based testing for state invariants
- Chaos engineering for error recovery validation
- Performance testing for high-volume state changes

## 6.13. Cross-References

This document relates to other UMS-STI documentation:

- **[1. Architectural Diagrams](010-architectural-diagrams.md)**: System architecture context
- **[2. ERD Diagrams](020-erd-diagrams.md)**: Database schema and relationships
- **[3. Business Process Flows](030-business-process-flows.md)**: Process workflows
- **[4. Swim Lane Diagrams](040-swim-lanes.md)**: Actor responsibilities
- **[5. Domain Models](050-domain-models.md)**: Domain entity definitions

---

*This document is part of the UMS-STI Event-Sourcing and CQRS Architecture documentation suite. For questions or clarifications, refer to the main documentation index or contact the development team.*
