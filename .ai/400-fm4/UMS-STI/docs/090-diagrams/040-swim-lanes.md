# 4. Swim Lane Diagrams for UMS-STI

## 4.1. Executive Summary

This document provides comprehensive swim lane diagrams for the User Management System with Single Table Inheritance (UMS-STI) using Mermaid syntax. These diagrams illustrate responsibility mapping, cross-functional processes, and actor interactions within the event-sourced, CQRS-based system. Swim lane diagrams help visualize who does what and when in complex business processes involving multiple actors and systems.

## 4.2. Learning Objectives

After reviewing this document, readers will understand:

- **4.2.1.** Actor responsibilities and interactions in user management processes
- **4.2.2.** Cross-functional workflows between different system components
- **4.2.3.** Responsibility boundaries between users, admins, and system processes
- **4.2.4.** Event-sourcing and CQRS component interactions
- **4.2.5.** External system integration points and responsibilities
- **4.2.6.** Error handling and escalation paths across different actors

## 4.3. Prerequisite Knowledge

Before reviewing these diagrams, ensure familiarity with:

- **4.3.1.** Swim lane diagram notation and concepts
- **4.3.2.** UMS-STI system architecture and components
- **4.3.3.** Event-sourcing and CQRS patterns
- **4.3.4.** Role-based access control concepts
- **4.3.5.** Business process modeling fundamentals

## 4.4. User Registration and Activation Swim Lanes

### 4.4.1. User Self-Registration Process

```mermaid
flowchart TD
    subgraph "User"
        A1[Visit Registration Page]
        A2[Fill Registration Form]
        A3[Submit Registration]
        A4[Check Email]
        A5[Click Activation Link]
        A6[Access Dashboard]
    end

    subgraph "Web Application"
        B1[Display Registration Form]
        B2[Validate Form Data]
        B3[Process Registration Request]
        B4[Display Success Message]
        B5[Validate Activation Token]
        B6[Redirect to Dashboard]
    end

    subgraph "Command Bus"
        C1[Receive RegisterUserCommand]
        C2[Route to Handler]
        C3[Receive ActivateUserCommand]
        C4[Route to Activation Handler]
    end

    subgraph "Event Store"
        D1[Store UserRegistrationInitiated]
        D2[Store UserActivated]
    end

    subgraph "Projectors"
        E1[Update User Projection]
        E2[Update User State to Active]
    end

    subgraph "Email Service"
        F1[Send Welcome Email]
        F2[Send Activation Email]
        F3[Send Activation Confirmation]
    end

    subgraph "Audit System"
        G1[Log Registration Event]
        G2[Log Activation Event]
    end

    A1 --> B1
    A2 --> B2
    A3 --> B3
    B2 --> C1
    C1 --> C2
    C2 --> D1
    D1 --> E1
    E1 --> F1
    F1 --> F2
    B3 --> B4
    B4 --> A4
    A4 --> A5
    A5 --> B5
    B5 --> C3
    C3 --> C4
    C4 --> D2
    D2 --> E2
    E2 --> F3
    B5 --> B6
    B6 --> A6

    D1 --> G1
    D2 --> G2

    classDef userActor fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef webApp fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef commandBus fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef eventStore fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff
    classDef projector fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
    classDef emailService fill:#00796b,stroke:#004d40,stroke-width:2px,color:#ffffff
    classDef auditSystem fill:#fbc02d,stroke:#f57f17,stroke-width:2px,color:#000000

    class A1,A2,A3,A4,A5,A6 userActor
    class B1,B2,B3,B4,B5,B6 webApp
    class C1,C2,C3,C4 commandBus
    class D1,D2 eventStore
    class E1,E2 projector
    class F1,F2,F3 emailService
    class G1,G2 auditSystem
```

### 4.4.2. Admin-Managed User Registration

```mermaid
flowchart TD
    subgraph "Admin User"
        A1[Access Admin Panel]
        A2[Navigate to User Management]
        A3[Click Create User]
        A4[Fill User Details]
        A5[Submit User Creation]
        A6[Review Created User]
    end

    subgraph "Filament Admin Panel"
        B1[Authenticate Admin]
        B2[Display User Management]
        B3[Show Create User Form]
        B4[Validate User Data]
        B5[Process User Creation]
        B6[Display Success Message]
        B7[Refresh User List]
    end

    subgraph "Authorization Service"
        C1[Check Admin Permissions]
        C2[Validate Create User Permission]
    end

    subgraph "Command Bus"
        D1[Receive CreateUserCommand]
        D2[Route to Handler]
        D3[Execute Business Logic]
    end

    subgraph "Event Store"
        E1[Store AdminUserCreated]
        E2[Store UserActivated]
    end

    subgraph "Projectors"
        F1[Update User Projection]
        F2[Update Admin Activity Log]
    end

    subgraph "Email Service"
        G1[Send Welcome Email to User]
        G2[Send Credentials Email]
    end

    subgraph "Target User"
        H1[Receive Welcome Email]
        H2[Receive Credentials]
        H3[First Login]
    end

    A1 --> B1
    B1 --> C1
    C1 --> B2
    A2 --> B2
    A3 --> B3
    A4 --> B4
    B4 --> C2
    C2 --> D1
    A5 --> B5
    B5 --> D1
    D1 --> D2
    D2 --> D3
    D3 --> E1
    E1 --> E2
    E2 --> F1
    F1 --> F2
    F1 --> G1
    G1 --> G2
    B5 --> B6
    B6 --> B7
    A6 --> B7

    G1 --> H1
    G2 --> H2
    H2 --> H3

    classDef adminActor fill:#ffecb3
    classDef filamentPanel fill:#e8f5e8
    classDef authService fill:#e1f5fe
    classDef commandBus fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef projector fill:#f3e5f5
    classDef emailService fill:#e0f2f1
    classDef targetUser fill:#e3f2fd

    class A1,A2,A3,A4,A5,A6 adminActor
    class B1,B2,B3,B4,B5,B6,B7 filamentPanel
    class C1,C2 authService
    class D1,D2,D3 commandBus
    class E1,E2 eventStore
    class F1,F2 projector
    class G1,G2 emailService
    class H1,H2,H3 targetUser
```

## 4.5. Team Management Swim Lanes

### 4.5.1. Team Creation and Member Addition

```mermaid
flowchart TD
    subgraph "Team Leader"
        A1[Request Team Creation]
        A2[Fill Team Details]
        A3[Submit Team Request]
        A4[Add Team Members]
        A5[Assign Member Roles]
        A6[Finalize Team Setup]
    end

    subgraph "Web Application"
        B1[Display Team Creation Form]
        B2[Validate Team Data]
        B3[Process Team Creation]
        B4[Show Member Addition Form]
        B5[Process Member Addition]
        B6[Display Team Dashboard]
    end

    subgraph "Authorization Service"
        C1[Check Team Creation Permission]
        C2[Validate Member Addition Rights]
    end

    subgraph "Command Bus"
        D1[Process CreateTeamCommand]
        D2[Process AddTeamMemberCommand]
        D3[Process AssignRoleCommand]
    end

    subgraph "Event Store"
        E1[Store TeamCreated Event]
        E2[Store TeamMemberAdded Event]
        E3[Store MemberRoleAssigned Event]
    end

    subgraph "Team Projectors"
        F1[Update Team Projection]
        F2[Update Team Hierarchy]
        F3[Update Member Projections]
    end

    subgraph "Notification Service"
        G1[Send Team Creation Notification]
        G2[Send Member Addition Notification]
        G3[Send Role Assignment Notification]
    end

    subgraph "Team Members"
        H1[Receive Team Invitation]
        H2[Accept/Decline Invitation]
        H3[Access Team Resources]
    end

    subgraph "System Admin"
        I1[Monitor Team Creation]
        I2[Review Team Structure]
        I3[Approve/Reject if Required]
    end

    A1 --> B1
    A2 --> B2
    B2 --> C1
    C1 --> D1
    A3 --> B3
    B3 --> D1
    D1 --> E1
    E1 --> F1
    F1 --> F2
    F2 --> G1

    A4 --> B4
    B4 --> C2
    C2 --> D2
    A5 --> B5
    B5 --> D2
    D2 --> E2
    E2 --> D3
    D3 --> E3
    E3 --> F3
    F3 --> G2
    G2 --> G3

    G2 --> H1
    H1 --> H2
    H2 --> H3

    A6 --> B6

    E1 --> I1
    F2 --> I2
    I2 --> I3

    classDef teamLeader fill:#ffecb3
    classDef webApp fill:#e8f5e8
    classDef authService fill:#e1f5fe
    classDef commandBus fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef projector fill:#f3e5f5
    classDef notificationService fill:#e0f2f1
    classDef teamMembers fill:#e3f2fd
    classDef systemAdmin fill:#ffebee

    class A1,A2,A3,A4,A5,A6 teamLeader
    class B1,B2,B3,B4,B5,B6 webApp
    class C1,C2 authService
    class D1,D2,D3 commandBus
    class E1,E2,E3 eventStore
    class F1,F2,F3 projector
    class G1,G2,G3 notificationService
    class H1,H2,H3 teamMembers
    class I1,I2,I3 systemAdmin
```

### 4.5.2. Team Hierarchy Management

```mermaid
flowchart TD
    subgraph "Organization Admin"
        A1[Review Team Structure]
        A2[Plan Hierarchy Changes]
        A3[Execute Team Moves]
        A4[Validate New Structure]
        A5[Communicate Changes]
    end

    subgraph "Admin Panel"
        B1[Display Team Hierarchy]
        B2[Show Move Team Interface]
        B3[Validate Move Operation]
        B4[Process Hierarchy Change]
        B5[Update Hierarchy Display]
    end

    subgraph "Business Rules Engine"
        C1[Check Hierarchy Constraints]
        C2[Validate Move Permissions]
        C3[Check Circular Dependencies]
        C4[Validate Team Dependencies]
    end

    subgraph "Command Bus"
        D1[Process ChangeTeamHierarchyCommand]
        D2[Execute Hierarchy Logic]
    end

    subgraph "Event Store"
        E1[Store TeamHierarchyChanged]
        E2[Store AffectedTeamsUpdated]
    end

    subgraph "Hierarchy Projectors"
        F1[Update Closure Table]
        F2[Recalculate Team Paths]
        F3[Update Affected Subteams]
        F4[Update Team Projections]
    end

    subgraph "Affected Team Leaders"
        G1[Receive Hierarchy Change Notification]
        G2[Review New Team Structure]
        G3[Update Team Processes]
        G4[Communicate to Team Members]
    end

    subgraph "Team Members"
        H1[Receive Structure Change Notice]
        H2[Understand New Reporting Lines]
        H3[Access Updated Team Resources]
    end

    subgraph "Audit System"
        I1[Log Hierarchy Changes]
        I2[Track Administrative Actions]
        I3[Monitor Compliance]
    end

    A1 --> B1
    A2 --> B2
    A3 --> B3
    B3 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> C4
    C4 --> D1
    B4 --> D1
    D1 --> D2
    D2 --> E1
    E1 --> E2
    E2 --> F1
    F1 --> F2
    F2 --> F3
    F3 --> F4

    A4 --> B5
    A5 --> G1
    F4 --> G1
    G1 --> G2
    G2 --> G3
    G3 --> G4
    G4 --> H1
    H1 --> H2
    H2 --> H3

    E1 --> I1
    D2 --> I2
    F4 --> I3

    classDef orgAdmin fill:#ffecb3
    classDef adminPanel fill:#e8f5e8
    classDef businessRules fill:#e1f5fe
    classDef commandBus fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef projector fill:#f3e5f5
    classDef teamLeaders fill:#fff8e1
    classDef teamMembers fill:#e3f2fd
    classDef auditSystem fill:#ffebee

    class A1,A2,A3,A4,A5 orgAdmin
    class B1,B2,B3,B4,B5 adminPanel
    class C1,C2,C3,C4 businessRules
    class D1,D2 commandBus
    class E1,E2 eventStore
    class F1,F2,F3,F4 projector
    class G1,G2,G3,G4 teamLeaders
    class H1,H2,H3 teamMembers
    class I1,I2,I3 auditSystem
```

## 4.6. Permission Management Swim Lanes

### 4.6.1. Role-Based Permission Assignment

```mermaid
flowchart TD
    subgraph "Security Admin"
        A1[Review Permission Request]
        A2[Validate Business Justification]
        A3[Select Appropriate Role]
        A4[Assign Role to User]
        A5[Monitor Permission Usage]
    end

    subgraph "Admin Interface"
        B1[Display Permission Request]
        B2[Show Role Selection Interface]
        B3[Validate Role Assignment]
        B4[Process Role Assignment]
        B5[Display Assignment Confirmation]
    end

    subgraph "Authorization Engine"
        C1[Check Admin Permissions]
        C2[Validate Role Compatibility]
        C3[Check Permission Conflicts]
        C4[Authorize Assignment]
    end

    subgraph "Command Bus"
        D1[Process AssignRoleCommand]
        D2[Execute Permission Logic]
    end

    subgraph "Event Store"
        E1[Store UserRoleAssigned]
        E2[Store PermissionsGranted]
    end

    subgraph "Permission Projectors"
        F1[Update User Role Projection]
        F2[Update Permission Projection]
        F3[Update Access Control Lists]
    end

    subgraph "Target User"
        G1[Receive Permission Notification]
        G2[Test New Permissions]
        G3[Access New Resources]
    end

    subgraph "Compliance Monitor"
        H1[Track Permission Changes]
        H2[Validate Compliance Rules]
        H3[Generate Compliance Reports]
    end

    subgraph "Audit Logger"
        I1[Log Permission Assignment]
        I2[Record Admin Actions]
        I3[Track Access Changes]
    end

    A1 --> B1
    A2 --> B2
    A3 --> B3
    B3 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> C4
    C4 --> D1
    A4 --> B4
    B4 --> D1
    D1 --> D2
    D2 --> E1
    E1 --> E2
    E2 --> F1
    F1 --> F2
    F2 --> F3

    A5 --> B5
    F3 --> G1
    G1 --> G2
    G2 --> G3

    E1 --> H1
    F2 --> H2
    H2 --> H3

    D2 --> I1
    A4 --> I2
    F3 --> I3

    classDef securityAdmin fill:#ffecb3
    classDef adminInterface fill:#e8f5e8
    classDef authEngine fill:#e1f5fe
    classDef commandBus fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef projector fill:#f3e5f5
    classDef targetUser fill:#e3f2fd
    classDef complianceMonitor fill:#fff8e1
    classDef auditLogger fill:#ffebee

    class A1,A2,A3,A4,A5 securityAdmin
    class B1,B2,B3,B4,B5 adminInterface
    class C1,C2,C3,C4 authEngine
    class D1,D2 commandBus
    class E1,E2 eventStore
    class F1,F2,F3 projector
    class G1,G2,G3 targetUser
    class H1,H2,H3 complianceMonitor
    class I1,I2,I3 auditLogger
```

### 4.6.2. Permission Revocation Process

```mermaid
flowchart TD
    subgraph "Security Admin"
        A1[Identify Permission to Revoke]
        A2[Review Revocation Impact]
        A3[Prepare Revocation Notice]
        A4[Execute Permission Revocation]
        A5[Verify Revocation Complete]
    end

    subgraph "Admin Interface"
        B1[Display User Permissions]
        B2[Show Revocation Interface]
        B3[Validate Revocation Request]
        B4[Process Permission Revocation]
        B5[Display Revocation Confirmation]
    end

    subgraph "Impact Analysis Engine"
        C1[Analyze Permission Dependencies]
        C2[Identify Affected Resources]
        C3[Check Dependent Permissions]
        C4[Generate Impact Report]
    end

    subgraph "Command Bus"
        D1[Process RevokePermissionCommand]
        D2[Execute Revocation Logic]
        D3[Handle Dependent Revocations]
    end

    subgraph "Event Store"
        E1[Store PermissionRevoked]
        E2[Store DependentPermissionsRevoked]
    end

    subgraph "Permission Projectors"
        F1[Update Permission Projection]
        F2[Remove Access Control Entries]
        F3[Update User Role Projection]
    end

    subgraph "Affected User"
        G1[Receive Revocation Notice]
        G2[Lose Access to Resources]
        G3[Request Access Restoration]
    end

    subgraph "Resource Owners"
        H1[Receive Access Change Notice]
        H2[Update Resource Permissions]
        H3[Verify Access Restrictions]
    end

    subgraph "Compliance Monitor"
        I1[Track Permission Revocations]
        I2[Validate Security Compliance]
        I3[Update Compliance Status]
    end

    A1 --> B1
    A2 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> C4
    C4 --> B2
    A3 --> B3
    B3 --> D1
    A4 --> B4
    B4 --> D1
    D1 --> D2
    D2 --> D3
    D3 --> E1
    E1 --> E2
    E2 --> F1
    F1 --> F2
    F2 --> F3

    A5 --> B5
    F2 --> G1
    G1 --> G2
    G2 --> G3

    F2 --> H1
    H1 --> H2
    H2 --> H3

    E1 --> I1
    F3 --> I2
    I2 --> I3

    classDef securityAdmin fill:#ffecb3
    classDef adminInterface fill:#e8f5e8
    classDef impactAnalysis fill:#e1f5fe
    classDef commandBus fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef projector fill:#f3e5f5
    classDef affectedUser fill:#e3f2fd
    classDef resourceOwners fill:#fff8e1
    classDef complianceMonitor fill:#ffebee

    class A1,A2,A3,A4,A5 securityAdmin
    class B1,B2,B3,B4,B5 adminInterface
    class C1,C2,C3,C4 impactAnalysis
    class D1,D2,D3 commandBus
    class E1,E2 eventStore
    class F1,F2,F3 projector
    class G1,G2,G3 affectedUser
    class H1,H2,H3 resourceOwners
    class I1,I2,I3 complianceMonitor
```

## 4.7. GDPR Compliance Swim Lanes

### 4.7.1. Data Export Request Process

```mermaid
flowchart TD
    subgraph "Data Subject"
        A1[Submit Data Export Request]
        A2[Provide Identity Verification]
        A3[Specify Data Scope]
        A4[Receive Export Notification]
        A5[Download Data Export]
    end

    subgraph "Web Portal"
        B1[Display Export Request Form]
        B2[Validate Identity]
        B3[Process Export Request]
        B4[Generate Download Link]
        B5[Provide Export Access]
    end

    subgraph "Identity Verification"
        C1[Verify User Identity]
        C2[Check Request Authenticity]
        C3[Authorize Export Request]
    end

    subgraph "GDPR Processor"
        D1[Queue Export Job]
        D2[Collect User Data]
        D3[Anonymize Sensitive Data]
        D4[Generate Export File]
        D5[Encrypt Export Data]
    end

    subgraph "Data Sources"
        E1[Extract User Profile Data]
        E2[Extract Team Membership Data]
        E3[Extract Permission History]
        E4[Extract Activity Logs]
        E5[Extract Analytics Data]
    end

    subgraph "Compliance Officer"
        F1[Review Export Request]
        F2[Validate Data Scope]
        F3[Approve Export Process]
        F4[Monitor Export Completion]
    end

    subgraph "Audit System"
        G1[Log Export Request]
        G2[Track Data Access]
        G3[Record Export Completion]
        G4[Generate Compliance Report]
    end

    A1 --> B1
    A2 --> B2
    B2 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> F1
    A3 --> B3
    B3 --> F2
    F2 --> F3
    F3 --> D1
    D1 --> D2
    D2 --> E1
    E1 --> E2
    E2 --> E3
    E3 --> E4
    E4 --> E5
    E5 --> D3
    D3 --> D4
    D4 --> D5
    D5 --> B4
    B4 --> A4
    A4 --> B5
    B5 --> A5

    F4 --> G1
    D2 --> G2
    D5 --> G3
    G3 --> G4

    classDef dataSubject fill:#e3f2fd
    classDef webPortal fill:#e8f5e8
    classDef identityVerification fill:#e1f5fe
    classDef gdprProcessor fill:#fff3e0
    classDef dataSources fill:#fce4ec
    classDef complianceOfficer fill:#ffecb3
    classDef auditSystem fill:#ffebee

    class A1,A2,A3,A4,A5 dataSubject
    class B1,B2,B3,B4,B5 webPortal
    class C1,C2,C3 identityVerification
    class D1,D2,D3,D4,D5 gdprProcessor
    class E1,E2,E3,E4,E5 dataSources
    class F1,F2,F3,F4 complianceOfficer
    class G1,G2,G3,G4 auditSystem
```

### 4.7.2. Data Deletion Request Process

```mermaid
flowchart TD
    subgraph "Data Subject"
        A1[Submit Deletion Request]
        A2[Confirm Identity]
        A3[Acknowledge Deletion Impact]
        A4[Confirm Final Deletion]
        A5[Receive Deletion Certificate]
    end

    subgraph "Web Portal"
        B1[Display Deletion Request Form]
        B2[Process Deletion Request]
        B3[Show Impact Warning]
        B4[Start Review Period]
        B5[Execute Deletion Process]
    end

    subgraph "Legal Review"
        C1[Review Deletion Request]
        C2[Check Legal Obligations]
        C3[Validate Retention Requirements]
        C4[Approve/Reject Deletion]
    end

    subgraph "Data Retention Manager"
        D1[Analyze Data Dependencies]
        D2[Identify Retention Requirements]
        D3[Plan Anonymization Strategy]
        D4[Execute Data Anonymization]
        D5[Preserve Required Records]
    end

    subgraph "System Components"
        E1[Remove User Profile Data]
        E2[Anonymize Event Store Data]
        E3[Update Projections]
        E4[Clean Cache Data]
        E5[Update External Systems]
    end

    subgraph "Compliance Officer"
        F1[Monitor Deletion Process]
        F2[Validate Compliance]
        F3[Generate Deletion Certificate]
        F4[Update Compliance Records]
    end

    subgraph "Audit System"
        G1[Log Deletion Request]
        G2[Track Deletion Progress]
        G3[Record Data Anonymization]
        G4[Generate Compliance Report]
    end

    A1 --> B1
    A2 --> B2
    B2 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> C4
    C4 --> D1
    A3 --> B3
    B3 --> B4
    B4 --> D2
    D2 --> D3
    A4 --> B5
    B5 --> D4
    D4 --> E1
    E1 --> E2
    E2 --> E3
    E3 --> E4
    E4 --> E5
    E5 --> D5
    D5 --> F3
    F3 --> A5

    F1 --> G1
    D4 --> G2
    E2 --> G3
    F4 --> G4

    classDef dataSubject fill:#e3f2fd
    classDef webPortal fill:#e8f5e8
    classDef legalReview fill:#e1f5fe
    classDef dataRetentionManager fill:#fff3e0
    classDef systemComponents fill:#fce4ec
    classDef complianceOfficer fill:#ffecb3
    classDef auditSystem fill:#ffebee

    class A1,A2,A3,A4,A5 dataSubject
    class B1,B2,B3,B4,B5 webPortal
    class C1,C2,C3,C4 legalReview
    class D1,D2,D3,D4,D5 dataRetentionManager
    class E1,E2,E3,E4,E5 systemComponents
    class F1,F2,F3,F4 complianceOfficer
    class G1,G2,G3,G4 auditSystem
```

## 4.8. Event-Sourcing and CQRS Swim Lanes

### 4.8.1. Command Processing Workflow

```mermaid
flowchart TD
    subgraph "Client Application"
        A1[Create Command]
        A2[Send Command to API]
        A3[Receive Response]
        A4[Handle Success/Error]
    end

    subgraph "API Gateway"
        B1[Receive HTTP Request]
        B2[Validate Request Format]
        B3[Route to Command Handler]
        B4[Return HTTP Response]
    end

    subgraph "Command Bus"
        C1[Receive Command]
        C2[Validate Command]
        C3[Route to Handler]
        C4[Execute Command Handler]
    end

    subgraph "Command Handler"
        D1[Load Aggregate]
        D2[Apply Business Logic]
        D3[Generate Events]
        D4[Save Aggregate]
    end

    subgraph "Event Store"
        E1[Validate Event Sequence]
        E2[Store Events]
        E3[Update Aggregate Version]
        E4[Publish Events]
    end

    subgraph "Event Bus"
        F1[Receive Published Events]
        F2[Route to Projectors]
        F3[Route to Reactors]
        F4[Handle Event Processing]
    end

    subgraph "Projectors"
        G1[Update Read Models]
        G2[Maintain Projections]
        G3[Handle Projection Errors]
    end

    subgraph "Reactors"
        H1[Process Side Effects]
        H2[Send Notifications]
        H3[Update External Systems]
    end

    A1 --> A2
    A2 --> B1
    B1 --> B2
    B2 --> B3
    B3 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> C4
    C4 --> D1
    D1 --> D2
    D2 --> D3
    D3 --> D4
    D4 --> E1
    E1 --> E2
    E2 --> E3
    E3 --> E4
    E4 --> F1
    F1 --> F2
    F2 --> F3
    F3 --> F4
    F4 --> G1
    G1 --> G2
    G2 --> G3
    F4 --> H1
    H1 --> H2
    H2 --> H3

    E3 --> B4
    B4 --> A3
    A3 --> A4

    classDef clientApp fill:#e3f2fd
    classDef apiGateway fill:#e8f5e8
    classDef commandBus fill:#e1f5fe
    classDef commandHandler fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef eventBus fill:#f3e5f5
    classDef projectors fill:#e0f2f1
    classDef reactors fill:#fff8e1

    class A1,A2,A3,A4 clientApp
    class B1,B2,B3,B4 apiGateway
    class C1,C2,C3,C4 commandBus
    class D1,D2,D3,D4 commandHandler
    class E1,E2,E3,E4 eventStore
    class F1,F2,F3,F4 eventBus
    class G1,G2,G3 projectors
    class H1,H2,H3 reactors
```

### 4.8.2. Query Processing Workflow

```mermaid
flowchart TD
    subgraph "Client Application"
        A1[Create Query]
        A2[Send Query Request]
        A3[Receive Query Response]
        A4[Process Response Data]
    end

    subgraph "API Gateway"
        B1[Receive Query Request]
        B2[Validate Query Format]
        B3[Route to Query Handler]
        B4[Return Query Response]
    end

    subgraph "Query Bus"
        C1[Receive Query]
        C2[Validate Query Parameters]
        C3[Route to Handler]
        C4[Execute Query Handler]
    end

    subgraph "Query Handler"
        D1[Check Cache]
        D2[Query Read Model]
        D3[Format Response]
        D4[Update Cache]
    end

    subgraph "Read Model Database"
        E1[Execute Query]
        E2[Return Results]
        E3[Apply Filters]
        E4[Handle Pagination]
    end

    subgraph "Cache Layer"
        F1[Check Cache Hit]
        F2[Store Query Result]
        F3[Invalidate Stale Data]
        F4[Manage Cache TTL]
    end

    subgraph "Projectors"
        G1[Update Read Models]
        G2[Maintain Data Consistency]
        G3[Handle Projection Lag]
    end

    subgraph "Monitoring"
        H1[Track Query Performance]
        H2[Monitor Cache Hit Rates]
        H3[Alert on Slow Queries]
    end

    A1 --> A2
    A2 --> B1
    B1 --> B2
    B2 --> B3
    B3 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> C4
    C4 --> D1
    D1 --> F1
    F1 --> D2
    D2 --> E1
    E1 --> E2
    E2 --> E3
    E3 --> E4
    E4 --> D3
    D3 --> D4
    D4 --> F2
    F2 --> B4
    B4 --> A3
    A3 --> A4

    G1 --> G2
    G2 --> G3
    G3 --> F3
    F3 --> F4

    D2 --> H1
    F1 --> H2
    E1 --> H3

    classDef clientApp fill:#e3f2fd
    classDef apiGateway fill:#e8f5e8
    classDef queryBus fill:#e1f5fe
    classDef queryHandler fill:#fff3e0
    classDef readModelDb fill:#fce4ec
    classDef cacheLayer fill:#f3e5f5
    classDef projectors fill:#e0f2f1
    classDef monitoring fill:#fff8e1

    class A1,A2,A3,A4 clientApp
    class B1,B2,B3,B4 apiGateway
    class C1,C2,C3,C4 queryBus
    class D1,D2,D3,D4 queryHandler
    class E1,E2,E3,E4 readModelDb
    class F1,F2,F3,F4 cacheLayer
    class G1,G2,G3 projectors
    class H1,H2,H3 monitoring
```

## 4.9. Error Handling and Recovery Swim Lanes

### 4.9.1. System Error Recovery Process

```mermaid
flowchart TD
    subgraph "System Monitor"
        A1[Detect System Error]
        A2[Classify Error Severity]
        A3[Trigger Recovery Process]
        A4[Monitor Recovery Progress]
        A5[Validate System Health]
    end

    subgraph "Alert System"
        B1[Generate Error Alert]
        B2[Notify Operations Team]
        B3[Escalate if Critical]
        B4[Update Alert Status]
    end

    subgraph "Operations Team"
        C1[Receive Error Alert]
        C2[Assess Error Impact]
        C3[Initiate Recovery Actions]
        C4[Coordinate with Development]
        C5[Validate Recovery]
    end

    subgraph "Recovery Engine"
        D1[Execute Recovery Scripts]
        D2[Restart Failed Services]
        D3[Replay Failed Events]
        D4[Rebuild Projections]
        D5[Verify Data Integrity]
    end

    subgraph "Event Store"
        E1[Identify Failed Events]
        E2[Validate Event Integrity]
        E3[Replay Event Stream]
        E4[Update Event Status]
    end

    subgraph "Projectors"
        F1[Reset Projection State]
        F2[Rebuild from Events]
        F3[Validate Projections]
        F4[Resume Normal Operation]
    end

    subgraph "Development Team"
        G1[Analyze Root Cause]
        G2[Develop Fix]
        G3[Deploy Hotfix]
        G4[Monitor Fix Effectiveness]
    end

    subgraph "Users"
        H1[Experience Service Disruption]
        H2[Receive Status Updates]
        H3[Resume Normal Usage]
    end

    A1 --> A2
    A2 --> A3
    A3 --> B1
    B1 --> B2
    B2 --> C1
    C1 --> C2
    C2 --> C3
    C3 --> D1
    D1 --> D2
    D2 --> D3
    D3 --> E1
    E1 --> E2
    E2 --> E3
    E3 --> D4
    D4 --> F1
    F1 --> F2
    F2 --> F3
    F3 --> F4

    A4 --> A5
    C4 --> G1
    G1 --> G2
    G2 --> G3
    G3 --> G4

    B3 --> H1
    B4 --> H2
    F4 --> H3

    D5 --> B4
    E4 --> A4

    classDef systemMonitor fill:#e3f2fd
    classDef alertSystem fill:#e8f5e8
    classDef operationsTeam fill:#e1f5fe
    classDef recoveryEngine fill:#fff3e0
    classDef eventStore fill:#fce4ec
    classDef projectors fill:#f3e5f5
    classDef developmentTeam fill:#e0f2f1
    classDef users fill:#fff8e1

    class A1,A2,A3,A4,A5 systemMonitor
    class B1,B2,B3,B4 alertSystem
    class C1,C2,C3,C4,C5 operationsTeam
    class D1,D2,D3,D4,D5 recoveryEngine
    class E1,E2,E3,E4 eventStore
    class F1,F2,F3,F4 projectors
    class G1,G2,G3,G4 developmentTeam
    class H1,H2,H3 users
```

## 4.10. Cross-References

### 4.10.1. Related Diagrams

- **Architectural Diagrams**: See [010-architectural-diagrams.md](010-architectural-diagrams.md) for system architecture overview
- **ERD Diagrams**: See [020-erd-diagrams.md](020-erd-diagrams.md) for detailed entity relationships
- **Business Process Flows**: See [030-business-process-flows.md](030-business-process-flows.md) for detailed process workflows
- **Domain Models**: See [050-domain-models.md](050-domain-models.md) for domain-specific diagrams
- **FSM Diagrams**: See [060-fsm-diagrams.md](060-fsm-diagrams.md) for state machine diagrams

### 4.10.2. Related Documentation

- **User Models**: See [../030-user-models/010-sti-architecture-explained.md](../030-user-models/010-sti-architecture-explained.md)
- **Team Hierarchy**: See [../040-team-hierarchy/010-closure-table-theory.md](../040-team-hierarchy/010-closure-table-theory.md)
- **Permission System**: See [../050-permission-system/010-permission-design.md](../050-permission-system/010-permission-design.md)
- **GDPR Compliance**: See [../060-gdpr-compliance/010-gdpr-implementation.md](../060-gdpr-compliance/010-gdpr-implementation.md)
- **Event-Sourcing Architecture**: See [../070-event-sourcing-cqrs/010-event-sourcing-architecture.md](../070-event-sourcing-cqrs/010-event-sourcing-architecture.md)

## 4.11. Swim Lane Design Principles

### 4.11.1. Actor Responsibility Mapping

- **Clear Boundaries**: Each swim lane represents a distinct actor or system component
- **Responsibility Isolation**: Actions are clearly assigned to appropriate actors
- **Communication Flows**: Interactions between actors are explicitly shown
- **Error Handling**: Error paths and escalation procedures are documented

### 4.11.2. Process Optimization Guidelines

- **Parallel Processing**: Identify opportunities for concurrent execution
- **Bottleneck Identification**: Highlight potential performance constraints
- **Automation Opportunities**: Mark manual processes that could be automated
- **Monitoring Points**: Identify key metrics and monitoring requirements

### 4.11.3. Security and Compliance Considerations

- **Authorization Checkpoints**: Verify permissions at appropriate stages
- **Audit Trail Requirements**: Ensure all actions are properly logged
- **Data Protection**: Implement appropriate data handling procedures
- **Compliance Validation**: Verify regulatory requirement adherence

## 4.12. References and Further Reading

### 4.12.1. Swim Lane Modeling

- [Business Process Modeling Notation (BPMN)](https://www.bpmn.org/)
- [Swim Lane Diagram Best Practices](https://www.lucidchart.com/pages/swim-lane-diagram)
- [Cross-Functional Process Mapping](https://www.isixsigma.com/tools-templates/flowchart/cross-functional-process-map/)

### 4.12.2. Responsibility Assignment

- [RACI Matrix](https://www.projectmanagement.com/articles/328368/RACI-Charts--What-are-They-and-How-to-Use-Them)
- [Responsibility Assignment Matrix](https://www.pmi.org/learning/library/responsibility-assignment-matrix-ram-6382)
- [Organizational Design Principles](https://www.mckinsey.com/business-functions/organization/our-insights/the-organization-blog)

### 4.12.3. Event-Driven Architecture

- [Event-Driven Architecture Patterns](https://microservices.io/patterns/data/event-driven-architecture.html)
- [CQRS and Event Sourcing](https://martinfowler.com/bliki/CQRS.html)
- [Distributed Systems Design](https://www.amazon.com/Designing-Data-Intensive-Applications-Reliable-Maintainable/dp/1449373321)

### 4.12.4. Error Handling and Recovery

- [Resilience Patterns](https://docs.microsoft.com/en-us/azure/architecture/patterns/category/resiliency)
- [Circuit Breaker Pattern](https://martinfowler.com/bliki/CircuitBreaker.html)
- [Bulkhead Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/bulkhead)
