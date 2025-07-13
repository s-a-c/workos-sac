# 3. Business Process Flows for UMS-STI

## 3.1. Executive Summary

This document provides comprehensive business process flow diagrams for the User Management System with Single Table Inheritance (UMS-STI) using Mermaid syntax. These diagrams illustrate the key business workflows, user journeys, and system processes that define how users, teams, and permissions interact within the event-sourced, CQRS-based system.

## 3.2. Learning Objectives

After reviewing this document, readers will understand:

- **3.2.1.** Complete user lifecycle and registration processes
- **3.2.2.** Team creation and management workflows
- **3.2.3.** Permission and role assignment processes
- **3.2.4.** Authentication and authorization flows
- **3.2.5.** GDPR compliance and data management processes
- **3.2.6.** Event-sourcing and CQRS workflow patterns

## 3.3. Prerequisite Knowledge

Before reviewing these diagrams, ensure familiarity with:

- **3.3.1.** Business process modeling concepts
- **3.3.2.** User experience design principles
- **3.3.3.** Event-sourcing and CQRS patterns
- **3.3.4.** Authentication and authorization concepts
- **3.3.5.** GDPR compliance requirements

## 3.4. User Management Processes

### 3.4.1. User Registration Process

```mermaid
flowchart TD
    A[User Visits Registration Page] --> B{User Type Selection}

    B -->|Standard User| C[Fill Registration Form]
    B -->|Admin User| D[Admin Registration Form]
    B -->|Guest User| E[Guest Session Creation]

    C --> F[Submit Registration Data]
    D --> G[Submit Admin Registration]
    E --> H[Create Guest Profile]

    F --> I{Validation Check}
    G --> J{Admin Validation Check}
    H --> K[Guest Session Active]

    I -->|Valid| L[Dispatch UserRegistrationInitiated]
    I -->|Invalid| M[Show Validation Errors]

    J -->|Valid| N[Dispatch AdminRegistrationInitiated]
    J -->|Invalid| O[Show Admin Validation Errors]

    M --> C
    O --> D

    L --> P[Create User Projection]
    N --> Q[Create Admin User Projection]

    P --> R[Send Welcome Email]
    Q --> S[Send Admin Welcome Email]

    R --> T[Send Activation Email]
    S --> U[Admin Approval Required]

    T --> V[User State: Pending]
    U --> W[Admin State: Pending Approval]

    V --> X{User Clicks Activation Link}
    W --> Y{Admin Approves}

    X -->|Yes| Z[Dispatch UserActivated]
    X -->|No| AA[Activation Link Expires]

    Y -->|Approved| BB[Dispatch AdminUserActivated]
    Y -->|Rejected| CC[Dispatch AdminUserRejected]

    Z --> DD[User State: Active]
    BB --> EE[Admin State: Active]
    CC --> FF[Admin State: Rejected]

    AA --> GG[Send New Activation Email]
    GG --> X

    DD --> HH[User Can Login]
    EE --> II[Admin Can Access Admin Panel]
    FF --> JJ[Registration Process Ends]

    K --> LL[Guest Can Browse]

    classDef startEnd fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    classDef process fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    classDef decision fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
    classDef event fill:#7b1fa2,stroke:#4a148c,stroke-width:2px,color:#ffffff

    class A,HH,II,JJ,LL startEnd
    class C,D,F,G,H,P,Q,R,S,T,U process
    class B,I,J,X,Y decision
    class L,N,Z,BB,CC event
```

### 3.4.2. User Authentication Process

```mermaid
flowchart TD
    A[User Attempts Login] --> B[Enter Credentials]

    B --> C{Authentication Method}

    C -->|Password| D[Validate Email/Password]
    C -->|SSO| E[Redirect to SSO Provider]
    C -->|API Token| F[Validate API Token]

    D --> G{Credentials Valid?}
    E --> H{SSO Authentication}
    F --> I{Token Valid?}

    G -->|Yes| J[Check User State]
    G -->|No| K[Dispatch FailedLoginAttempt]

    H -->|Success| L[Process SSO Response]
    H -->|Failed| M[SSO Authentication Failed]

    I -->|Yes| N[Check API User State]
    I -->|No| O[Invalid Token Response]

    K --> P[Increment Failed Attempts]
    M --> Q[Show SSO Error]
    O --> R[API Authentication Failed]

    P --> S{Max Attempts Reached?}

    S -->|Yes| T[Dispatch UserAccountLocked]
    S -->|No| U[Show Login Error]

    T --> V[Account Locked State]
    U --> B
    Q --> B
    R --> W[API Error Response]

    J --> X{User State Check}
    L --> Y[Create/Update User from SSO]
    N --> Z{API User State Check}

    X -->|Active| AA[Dispatch UserLoggedIn]
    X -->|Inactive| BB[Account Inactive Error]
    X -->|Suspended| CC[Account Suspended Error]
    X -->|Locked| DD[Account Locked Error]

    Y --> EE[Dispatch UserLoggedIn]

    Z -->|Active| FF[Dispatch APIUserAuthenticated]
    Z -->|Inactive| GG[API User Inactive Error]

    AA --> HH[Create User Session]
    EE --> II[Create SSO User Session]
    FF --> JJ[Create API Session]

    HH --> KK[Update Last Login]
    II --> LL[Update Last SSO Login]
    JJ --> MM[Update Last API Access]

    KK --> NN[Redirect to Dashboard]
    LL --> OO[Redirect to Dashboard]
    MM --> PP[API Access Granted]

    BB --> B
    CC --> B
    DD --> B
    GG --> W

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,NN,OO,PP,W startEnd
    class B,D,E,F,L,Y,HH,II,JJ,KK,LL,MM process
    class C,G,H,I,S,X,Z decision
    class K,T,AA,EE,FF event
    class P,V,BB,CC,DD,GG,Q,R,U error
```

### 3.4.3. User Profile Management Process

```mermaid
flowchart TD
    A[User Accesses Profile] --> B[Load Current Profile Data]

    B --> C[Display Profile Form]

    C --> D{User Action}

    D -->|Update Profile| E[Modify Profile Fields]
    D -->|Change Password| F[Password Change Form]
    D -->|Upload Avatar| G[Avatar Upload Process]
    D -->|Update Preferences| H[Preferences Form]
    D -->|Delete Account| I[Account Deletion Process]

    E --> J[Submit Profile Updates]
    F --> K[Submit Password Change]
    G --> L[Process Avatar Upload]
    H --> M[Submit Preferences]
    I --> N[Confirm Account Deletion]

    J --> O{Profile Validation}
    K --> P{Password Validation}
    L --> Q{Avatar Validation}
    M --> R{Preferences Validation}
    N --> S{Deletion Confirmation}

    O -->|Valid| T[Dispatch UserProfileUpdated]
    O -->|Invalid| U[Show Profile Errors]

    P -->|Valid| V[Dispatch UserPasswordChanged]
    P -->|Invalid| W[Show Password Errors]

    Q -->|Valid| X[Dispatch UserAvatarUpdated]
    Q -->|Invalid| Y[Show Avatar Errors]

    R -->|Valid| Z[Dispatch UserPreferencesUpdated]
    R -->|Invalid| AA[Show Preferences Errors]

    S -->|Confirmed| BB[Dispatch UserDeletionRequested]
    S -->|Cancelled| CC[Return to Profile]

    U --> E
    W --> F
    Y --> G
    AA --> H
    CC --> C

    T --> DD[Update User Projection]
    V --> EE[Update Password Hash]
    X --> FF[Update Avatar URL]
    Z --> GG[Update User Preferences]
    BB --> HH[Start GDPR Deletion Process]

    DD --> II[Send Profile Update Notification]
    EE --> JJ[Send Password Change Notification]
    FF --> KK[Update Profile Display]
    GG --> LL[Apply New Preferences]
    HH --> MM[Schedule Data Deletion]

    II --> NN[Profile Updated Successfully]
    JJ --> OO[Password Changed Successfully]
    KK --> PP[Avatar Updated Successfully]
    LL --> QQ[Preferences Saved Successfully]
    MM --> RR[Deletion Process Initiated]

    NN --> C
    OO --> C
    PP --> C
    QQ --> C

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,NN,OO,PP,QQ,RR startEnd
    class B,C,E,F,G,H,J,K,L,M,DD,EE,FF,GG,HH,II,JJ,KK,LL,MM process
    class D,O,P,Q,R,S decision
    class T,V,X,Z,BB event
    class U,W,Y,AA error
```

## 3.5. Team Management Processes

### 3.5.1. Team Creation Process

```mermaid
flowchart TD
    A[User Initiates Team Creation] --> B{Permission Check}

    B -->|Authorized| C[Display Team Creation Form]
    B -->|Unauthorized| D[Access Denied]

    C --> E[Fill Team Details]

    E --> F{Team Type Selection}

    F -->|Department| G[Department Team Form]
    F -->|Project| H[Project Team Form]
    F -->|Working Group| I[Working Group Form]
    F -->|Committee| J[Committee Form]

    G --> K[Set Department Settings]
    H --> L[Set Project Settings]
    I --> M[Set Working Group Settings]
    J --> N[Set Committee Settings]

    K --> O[Submit Team Creation]
    L --> O
    M --> O
    N --> O

    O --> P{Validation Check}

    P -->|Valid| Q[Dispatch TeamCreated]
    P -->|Invalid| R[Show Validation Errors]

    R --> E

    Q --> S[Create Team Projection]

    S --> T{Parent Team Specified?}

    T -->|Yes| U[Update Team Hierarchy]
    T -->|No| V[Create Root Team]

    U --> W[Add to Closure Table]
    V --> X[Add Self-Reference to Closure Table]

    W --> Y[Dispatch TeamHierarchyUpdated]
    X --> Y

    Y --> Z[Add Creator as Team Admin]

    Z --> AA[Dispatch TeamMemberAdded]

    AA --> BB[Update Team Member Projection]

    BB --> CC[Send Team Creation Notification]

    CC --> DD{Parent Team Exists?}

    DD -->|Yes| EE[Notify Parent Team Admins]
    DD -->|No| FF[Notify System Admins]

    EE --> GG[Team Created Successfully]
    FF --> GG

    D --> HH[Show Access Denied Message]

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,GG,HH startEnd
    class C,E,G,H,I,J,K,L,M,N,O,S,W,X,BB,CC process
    class B,F,P,T,DD decision
    class Q,Y,AA event
    class D,R error
```

### 3.5.2. Team Member Management Process

```mermaid
flowchart TD
    A[Team Admin Manages Members] --> B{Action Selection}

    B -->|Add Member| C[Add Member Process]
    B -->|Remove Member| D[Remove Member Process]
    B -->|Change Role| E[Change Role Process]
    B -->|Send Invitation| F[Send Invitation Process]

    C --> G[Search for User]
    D --> H[Select Member to Remove]
    E --> I[Select Member for Role Change]
    F --> J[Enter Invitation Details]

    G --> K{User Found?}
    H --> L{Confirm Removal?}
    I --> M[Select New Role]
    J --> N[Send Team Invitation]

    K -->|Yes| O{User Already Member?}
    K -->|No| P[User Not Found Error]

    L -->|Yes| Q[Dispatch TeamMemberRemoved]
    L -->|No| R[Cancellation]

    O -->|No| S[Select Member Role]
    O -->|Yes| T[User Already Member Error]

    M --> U{Role Change Valid?}
    N --> V[Dispatch TeamInvitationSent]

    S --> W[Dispatch TeamMemberAdded]

    U -->|Valid| X[Dispatch TeamMemberRoleChanged]
    U -->|Invalid| Y[Invalid Role Change Error]

    V --> Z[Create Invitation Record]

    W --> AA[Update Team Member Projection]
    Q --> BB[Update Team Member Projection]
    X --> CC[Update Team Member Projection]
    Z --> DD[Send Invitation Email]

    AA --> EE[Send Welcome to Team Email]
    BB --> FF[Send Removal Notification]
    CC --> GG[Send Role Change Notification]
    DD --> HH[Invitation Sent Successfully]

    EE --> II{Auto-assign Permissions?}
    FF --> JJ[Member Removed Successfully]
    GG --> KK[Role Changed Successfully]

    II -->|Yes| LL[Dispatch UserPermissionGranted]
    II -->|No| MM[Manual Permission Assignment]

    LL --> NN[Update Permission Projections]
    MM --> OO[Team Admin Assigns Permissions]

    NN --> PP[Member Added with Permissions]
    OO --> QQ[Member Added - Manual Permissions]

    HH --> RR{Invitation Response}

    RR -->|Accepted| SS[Process Invitation Acceptance]
    RR -->|Declined| TT[Process Invitation Decline]
    RR -->|Expired| UU[Mark Invitation Expired]

    SS --> VV[Dispatch TeamInvitationAccepted]
    TT --> WW[Dispatch TeamInvitationDeclined]
    UU --> XX[Invitation Expired]

    VV --> W
    WW --> YY[Invitation Declined]

    P --> ZZ[Return to Search]
    T --> ZZ
    Y --> I
    R --> AAA[Return to Member List]

    ZZ --> G
    AAA --> A

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,PP,QQ,JJ,KK,HH,YY,XX startEnd
    class G,H,I,J,S,M,N,AA,BB,CC,DD,EE,FF,GG,NN,OO,SS,TT,UU process
    class B,K,L,O,U,II,RR decision
    class W,Q,X,V,LL,VV,WW event
    class P,T,Y,R error
```

### 3.5.3. Team Hierarchy Management Process

```mermaid
flowchart TD
    A[Admin Manages Team Hierarchy] --> B{Hierarchy Action}

    B -->|Move Team| C[Select Team to Move]
    B -->|Create Subteam| D[Create Subteam Process]
    B -->|Archive Team| E[Archive Team Process]
    B -->|View Hierarchy| F[Display Team Hierarchy]

    C --> G[Select New Parent Team]
    D --> H[Fill Subteam Details]
    E --> I{Confirm Archive?}
    F --> J[Load Hierarchy Data]

    G --> K{Valid Move?}
    H --> L[Submit Subteam Creation]
    I -->|Yes| M[Dispatch TeamArchived]
    I -->|No| N[Cancel Archive]
    J --> O[Display Hierarchy Tree]

    K -->|Valid| P[Dispatch TeamHierarchyChanged]
    K -->|Invalid| Q[Invalid Move Error]

    L --> R{Subteam Validation}

    R -->|Valid| S[Dispatch TeamCreated]
    R -->|Invalid| T[Subteam Validation Error]

    P --> U[Update Closure Table]
    M --> V[Update Team State]
    S --> W[Create Subteam in Hierarchy]

    U --> X[Recalculate Hierarchy Paths]
    V --> Y[Archive Team Members]
    W --> Z[Update Parent Team]

    X --> AA{Affected Subteams?}
    Y --> BB[Notify Affected Members]
    Z --> CC[Update Hierarchy Projections]

    AA -->|Yes| DD[Update Subteam Hierarchies]
    AA -->|No| EE[Hierarchy Update Complete]

    DD --> FF[Dispatch SubteamHierarchyUpdated]

    FF --> GG[Update All Affected Projections]

    GG --> HH[Send Hierarchy Change Notifications]
    BB --> II[Team Archived Successfully]
    CC --> JJ[Subteam Created Successfully]
    EE --> KK[Team Moved Successfully]

    HH --> KK

    O --> LL{Hierarchy Actions Available?}

    LL -->|Yes| MM[Show Action Buttons]
    LL -->|No| NN[Read-only View]

    MM --> B
    NN --> OO[Hierarchy Displayed]

    Q --> C
    T --> H
    N --> A

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,II,JJ,KK,OO startEnd
    class C,G,H,J,L,U,V,W,X,Y,Z,DD,GG,HH,BB,CC,MM process
    class B,I,K,R,AA,LL decision
    class P,M,S,FF event
    class Q,T,N error
```

## 3.6. Permission and Role Management Processes

### 3.6.1. Permission Assignment Process

```mermaid
flowchart TD
    A[Admin Assigns Permissions] --> B{Assignment Type}

    B -->|Direct Permission| C[Select User for Permission]
    B -->|Role Assignment| D[Select User for Role]
    B -->|Bulk Assignment| E[Select Multiple Users]

    C --> F[Choose Permission to Grant]
    D --> G[Choose Role to Assign]
    E --> H[Choose Permissions/Roles]

    F --> I{Context Required?}
    G --> J{Role Context Required?}
    H --> K{Bulk Context Required?}

    I -->|Yes| L[Select Context - Team/Resource]
    I -->|No| M[Global Permission]

    J -->|Yes| N[Select Role Context]
    J -->|No| O[Global Role]

    K -->|Yes| P[Select Bulk Context]
    K -->|No| Q[Global Bulk Assignment]

    L --> R[Set Permission Expiry]
    M --> R
    N --> S[Set Role Expiry]
    O --> S
    P --> T[Set Bulk Expiry]
    Q --> T

    R --> U[Dispatch UserPermissionGranted]
    S --> V[Dispatch UserRoleAssigned]
    T --> W[Dispatch BulkPermissionsAssigned]

    U --> X[Update User Permission Projection]
    V --> Y[Update User Role Projection]
    W --> Z[Update Multiple Projections]

    X --> AA[Check Permission Conflicts]
    Y --> BB[Check Role Conflicts]
    Z --> CC[Check Bulk Conflicts]

    AA --> DD{Conflicts Found?}
    BB --> EE{Role Conflicts Found?}
    CC --> FF{Bulk Conflicts Found?}

    DD -->|Yes| GG[Resolve Permission Conflicts]
    DD -->|No| HH[Permission Granted Successfully]

    EE -->|Yes| II[Resolve Role Conflicts]
    EE -->|No| JJ[Role Assigned Successfully]

    FF -->|Yes| KK[Resolve Bulk Conflicts]
    FF -->|No| LL[Bulk Assignment Successful]

    GG --> MM[Dispatch PermissionConflictResolved]
    II --> NN[Dispatch RoleConflictResolved]
    KK --> OO[Dispatch BulkConflictResolved]

    MM --> PP[Send Conflict Resolution Notification]
    NN --> QQ[Send Role Conflict Notification]
    OO --> RR[Send Bulk Conflict Notification]

    PP --> SS[Permission Assignment Complete]
    QQ --> TT[Role Assignment Complete]
    RR --> UU[Bulk Assignment Complete]

    HH --> VV[Send Permission Grant Notification]
    JJ --> WW[Send Role Assignment Notification]
    LL --> XX[Send Bulk Assignment Notification]

    VV --> SS
    WW --> TT
    XX --> UU

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec

    class A,SS,TT,UU startEnd
    class C,D,E,F,G,H,L,M,N,O,P,Q,R,S,T,X,Y,Z,AA,BB,CC,GG,II,KK,PP,QQ,RR,VV,WW,XX process
    class B,I,J,K,DD,EE,FF decision
    class U,V,W,MM,NN,OO event
```

### 3.6.2. Permission Revocation Process

```mermaid
flowchart TD
    A[Admin Revokes Permissions] --> B{Revocation Type}

    B -->|Single Permission| C[Select User Permission]
    B -->|Role Revocation| D[Select User Role]
    B -->|Bulk Revocation| E[Select Multiple Permissions]
    B -->|Expired Cleanup| F[Process Expired Permissions]

    C --> G[Confirm Permission Revocation]
    D --> H[Confirm Role Revocation]
    E --> I[Confirm Bulk Revocation]
    F --> J[Identify Expired Items]

    G --> K{Revocation Confirmed?}
    H --> L{Role Revocation Confirmed?}
    I --> M{Bulk Revocation Confirmed?}
    J --> N[Process Expired Permissions]

    K -->|Yes| O[Enter Revocation Reason]
    K -->|No| P[Cancel Revocation]

    L -->|Yes| Q[Enter Role Revocation Reason]
    L -->|No| R[Cancel Role Revocation]

    M -->|Yes| S[Enter Bulk Revocation Reason]
    M -->|No| T[Cancel Bulk Revocation]

    N --> U[Dispatch PermissionsExpired]

    O --> V[Dispatch UserPermissionRevoked]
    Q --> W[Dispatch UserRoleRevoked]
    S --> X[Dispatch BulkPermissionsRevoked]
    U --> Y[Update Expired Projections]

    V --> Z[Update Permission Projection]
    W --> AA[Update Role Projection]
    X --> BB[Update Multiple Projections]
    Y --> CC[Mark Permissions as Expired]

    Z --> DD[Check Dependent Permissions]
    AA --> EE[Check Dependent Roles]
    BB --> FF[Check Bulk Dependencies]
    CC --> GG[Send Expiry Notifications]

    DD --> HH{Dependencies Found?}
    EE --> II{Role Dependencies Found?}
    FF --> JJ{Bulk Dependencies Found?}

    HH -->|Yes| KK[Handle Permission Dependencies]
    HH -->|No| LL[Permission Revoked Successfully]

    II -->|Yes| MM[Handle Role Dependencies]
    II -->|No| NN[Role Revoked Successfully]

    JJ -->|Yes| OO[Handle Bulk Dependencies]
    JJ -->|No| PP[Bulk Revocation Successful]

    KK --> QQ[Dispatch DependentPermissionsRevoked]
    MM --> RR[Dispatch DependentRolesRevoked]
    OO --> SS[Dispatch BulkDependenciesRevoked]

    QQ --> TT[Update Dependent Projections]
    RR --> UU[Update Dependent Role Projections]
    SS --> VV[Update Bulk Dependent Projections]

    TT --> WW[Send Dependency Revocation Notification]
    UU --> XX[Send Role Dependency Notification]
    VV --> YY[Send Bulk Dependency Notification]

    WW --> ZZ[Permission Revocation Complete]
    XX --> AAA[Role Revocation Complete]
    YY --> BBB[Bulk Revocation Complete]
    GG --> CCC[Expiry Process Complete]

    LL --> DDD[Send Permission Revocation Notification]
    NN --> EEE[Send Role Revocation Notification]
    PP --> FFF[Send Bulk Revocation Notification]

    DDD --> ZZ
    EEE --> AAA
    FFF --> BBB

    P --> A
    R --> A
    T --> A

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,ZZ,AAA,BBB,CCC startEnd
    class C,D,E,F,G,H,I,J,O,Q,S,N,Z,AA,BB,Y,CC,DD,EE,FF,KK,MM,OO,TT,UU,VV,WW,XX,YY,DDD,EEE,FFF,GG process
    class B,K,L,M,HH,II,JJ decision
    class V,W,X,U,QQ,RR,SS event
    class P,R,T error
```

## 3.7. GDPR Compliance Processes

### 3.7.1. Data Export Process

```mermaid
flowchart TD
    A[User Requests Data Export] --> B{Request Validation}

    B -->|Valid| C[Dispatch DataExportRequested]
    B -->|Invalid| D[Show Validation Error]

    C --> E[Create Export Job]

    E --> F[Queue Data Export Job]

    F --> G[Start Data Collection]

    G --> H{Data Sources}

    H -->|User Data| I[Collect User Profile Data]
    H -->|Team Data| J[Collect Team Membership Data]
    H -->|Permission Data| K[Collect Permission Data]
    H -->|Activity Data| L[Collect Activity Logs]
    H -->|Analytics Data| M[Collect Analytics Data]

    I --> N[Anonymize Sensitive Fields]
    J --> O[Filter Team Data by Permissions]
    K --> P[Include Permission History]
    L --> Q[Filter Activity by Date Range]
    M --> R[Aggregate Analytics Data]

    N --> S[Format User Data]
    O --> T[Format Team Data]
    P --> U[Format Permission Data]
    Q --> V[Format Activity Data]
    R --> W[Format Analytics Data]

    S --> X[Combine Data Sources]
    T --> X
    U --> X
    V --> X
    W --> X

    X --> Y[Generate Export File]

    Y --> Z{Export Format}

    Z -->|JSON| AA[Create JSON Export]
    Z -->|CSV| BB[Create CSV Export]
    Z -->|PDF| CC[Create PDF Export]

    AA --> DD[Encrypt Export File]
    BB --> DD
    CC --> DD

    DD --> EE[Store Encrypted File]

    EE --> FF[Generate Download Link]

    FF --> GG[Dispatch DataExportCompleted]

    GG --> HH[Send Export Ready Email]

    HH --> II[User Downloads Export]

    II --> JJ{Download Successful?}

    JJ -->|Yes| KK[Log Successful Download]
    JJ -->|No| LL[Log Download Failure]

    KK --> MM[Schedule File Cleanup]
    LL --> NN[Retry Download Available]

    MM --> OO[Export Process Complete]
    NN --> II

    D --> PP[Return to Request Form]

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,OO,PP startEnd
    class E,F,G,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,AA,BB,CC,DD,EE,FF,HH,II,KK,LL,MM process
    class B,H,Z,JJ decision
    class C,GG event
    class D error
```

### 3.7.2. Data Deletion Process

```mermaid
flowchart TD
    A[User Requests Data Deletion] --> B{Deletion Request Validation}

    B -->|Valid| C[Dispatch DataDeletionRequested]
    B -->|Invalid| D[Show Validation Error]

    C --> E[Create Deletion Job]

    E --> F[Start Review Period]

    F --> G[Send Deletion Confirmation Email]

    G --> H[30-Day Review Period]

    H --> I{Review Period Status}

    I -->|User Cancels| J[Dispatch DeletionCancelled]
    I -->|Period Expires| K[Proceed with Deletion]
    I -->|Admin Review Required| L[Admin Review Process]

    J --> M[Cancel Deletion Job]
    L --> N{Admin Decision}

    N -->|Approve| K
    N -->|Reject| O[Dispatch DeletionRejected]

    K --> P[Start Data Anonymization]

    P --> Q{Data Classification}

    Q -->|Personal Data| R[Delete Personal Information]
    Q -->|Audit Data| S[Anonymize Audit Records]
    Q -->|Analytics Data| T[Anonymize Analytics Data]
    Q -->|System Data| U[Preserve System Integrity Data]

    R --> V[Remove User Profile Data]
    S --> W[Replace User IDs with Tokens]
    T --> X[Remove Personal Identifiers]
    U --> Y[Keep Anonymized References]

    V --> Z[Dispatch PersonalDataDeleted]
    W --> AA[Dispatch AuditDataAnonymized]
    X --> BB[Dispatch AnalyticsDataAnonymized]
    Y --> CC[Dispatch SystemDataPreserved]

    Z --> DD[Update User Projections]
    AA --> EE[Update Audit Projections]
    BB --> FF[Update Analytics Projections]
    CC --> GG[Update System References]

    DD --> HH[Mark User as Deleted]
    EE --> II[Preserve Audit Trail]
    FF --> JJ[Maintain Analytics Integrity]
    GG --> KK[Ensure System Functionality]

    HH --> LL[Generate Deletion Certificate]
    II --> LL
    JJ --> LL
    KK --> LL

    LL --> MM[Dispatch DataDeletionCompleted]

    MM --> NN[Send Deletion Confirmation]

    NN --> OO[Log Compliance Action]

    OO --> PP[Deletion Process Complete]

    M --> QQ[Deletion Cancelled]
    O --> RR[Deletion Rejected]
    D --> SS[Return to Request Form]

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,PP,QQ,RR,SS startEnd
    class E,F,G,H,M,P,R,S,T,U,V,W,X,Y,DD,EE,FF,GG,HH,II,JJ,KK,LL,NN,OO process
    class B,I,N,Q decision
    class C,J,O,Z,AA,BB,CC,MM event
    class D error
```

## 3.8. Event-Sourcing and CQRS Processes

### 3.8.1. Command Processing Flow

```mermaid
flowchart TD
    A[Client Sends Command] --> B[Command Bus Receives Command]

    B --> C{Command Validation}

    C -->|Valid| D[Route to Command Handler]
    C -->|Invalid| E[Return Validation Error]

    D --> F[Load Aggregate from Event Store]

    F --> G{Aggregate Exists?}

    G -->|Yes| H[Load Aggregate State]
    G -->|No| I[Create New Aggregate]

    H --> J[Apply Business Logic]
    I --> J

    J --> K{Business Rules Valid?}

    K -->|Valid| L[Generate Domain Events]
    K -->|Invalid| M[Return Business Rule Error]

    L --> N[Append Events to Event Store]

    N --> O{Concurrency Check}

    O -->|Success| P[Commit Events]
    O -->|Conflict| Q[Handle Concurrency Conflict]

    P --> R[Publish Events to Event Bus]

    R --> S[Update Aggregate Version]

    S --> T[Return Success Response]

    Q --> U{Retry Strategy}

    U -->|Retry| V[Reload Aggregate]
    U -->|Abort| W[Return Concurrency Error]

    V --> H

    E --> X[Log Validation Error]
    M --> Y[Log Business Rule Error]
    W --> Z[Log Concurrency Error]

    X --> AA[Return Error Response]
    Y --> AA
    Z --> AA

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef event fill:#fce4ec
    classDef error fill:#ffebee

    class A,T,AA startEnd
    class B,D,F,H,I,J,L,N,P,R,S,V,X,Y,Z process
    class C,G,K,O,U decision
    class L,R event
    class E,M,Q,W error
```

### 3.8.2. Query Processing Flow

```mermaid
flowchart TD
    A[Client Sends Query] --> B[Query Bus Receives Query]

    B --> C{Query Authorization}

    C -->|Authorized| D[Route to Query Handler]
    C -->|Unauthorized| E[Return Authorization Error]

    D --> F{Cache Check}

    F -->|Cache Hit| G[Return Cached Result]
    F -->|Cache Miss| H[Query Read Model]

    H --> I{Read Model Available?}

    I -->|Yes| J[Execute Query]
    I -->|No| K[Fallback to Event Store]

    J --> L{Query Successful?}
    K --> M[Replay Events to Build State]

    L -->|Yes| N[Format Query Result]
    L -->|No| O[Handle Query Error]

    M --> P[Build Temporary Projection]

    P --> Q[Execute Query on Temp Projection]

    Q --> N

    N --> R{Caching Enabled?}

    R -->|Yes| S[Store Result in Cache]
    R -->|No| T[Return Result Directly]

    S --> U[Set Cache TTL]

    U --> V[Return Cached Result]

    E --> W[Log Authorization Error]
    O --> X[Log Query Error]

    W --> Y[Return Error Response]
    X --> Y

    G --> Z[Update Cache Statistics]
    V --> Z
    T --> Z

    Z --> AA[Return Query Response]

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef error fill:#ffebee

    class A,AA,Y startEnd
    class B,D,H,J,M,P,Q,N,S,U,W,X,Z process
    class C,F,I,L,R decision
    class E,O error
```

### 3.8.3. Event Processing Flow

```mermaid
flowchart TD
    A[Event Published to Event Bus] --> B[Event Dispatcher Receives Event]

    B --> C{Event Routing}

    C -->|Projectors| D[Route to Projectors]
    C -->|Reactors| E[Route to Reactors]
    C -->|External Systems| F[Route to External Handlers]

    D --> G[Update Read Models]
    E --> H[Process Side Effects]
    F --> I[Send to External Systems]

    G --> J{Projection Success?}
    H --> K{Reactor Success?}
    I --> L{External Success?}

    J -->|Success| M[Update Projection Timestamp]
    J -->|Failure| N[Handle Projection Error]

    K -->|Success| O[Log Reactor Success]
    K -->|Failure| P[Handle Reactor Error]

    L -->|Success| Q[Log External Success]
    L -->|Failure| R[Handle External Error]

    N --> S{Retry Projection?}
    P --> T{Retry Reactor?}
    R --> U{Retry External?}

    S -->|Yes| V[Queue Projection Retry]
    S -->|No| W[Mark Projection Failed]

    T -->|Yes| X[Queue Reactor Retry]
    T -->|No| Y[Mark Reactor Failed]

    U -->|Yes| Z[Queue External Retry]
    U -->|No| AA[Mark External Failed]

    V --> BB[Schedule Retry Job]
    X --> BB
    Z --> BB

    BB --> CC[Retry Processing]

    CC --> D

    M --> DD[Update Read Model Cache]
    O --> EE[Complete Reactor Processing]
    Q --> FF[Complete External Processing]

    DD --> GG[Invalidate Related Caches]

    GG --> HH[Event Processing Complete]
    EE --> HH
    FF --> HH

    W --> II[Log Projection Failure]
    Y --> JJ[Log Reactor Failure]
    AA --> KK[Log External Failure]

    II --> LL[Alert System Administrators]
    JJ --> LL
    KK --> LL

    LL --> MM[Event Processing Failed]

    classDef startEnd fill:#e1f5fe
    classDef process fill:#e8f5e8
    classDef decision fill:#fff3e0
    classDef error fill:#ffebee

    class A,HH,MM startEnd
    class B,G,H,I,M,O,Q,V,X,Z,BB,CC,DD,EE,FF,GG,II,JJ,KK,LL process
    class C,J,K,L,S,T,U decision
    class N,P,R,W,Y,AA error
```

## 3.9. Cross-References

### 3.9.1. Related Diagrams

- **Architectural Diagrams**: See [010-architectural-diagrams.md](010-architectural-diagrams.md) for system architecture overview
- **ERD Diagrams**: See [020-erd-diagrams.md](020-erd-diagrams.md) for detailed entity relationships
- **Swim Lanes**: See [040-swim-lanes.md](040-swim-lanes.md) for responsibility mapping
- **Domain Models**: See [050-domain-models.md](050-domain-models.md) for domain-specific diagrams
- **FSM Diagrams**: See [060-fsm-diagrams.md](060-fsm-diagrams.md) for state machine diagrams

### 3.9.2. Related Documentation

- **User Models**: See [../030-user-models/010-sti-architecture-explained.md](../030-user-models/010-sti-architecture-explained.md)
- **Team Hierarchy**: See [../040-team-hierarchy/010-closure-table-theory.md](../040-team-hierarchy/010-closure-table-theory.md)
- **Permission System**: See [../050-permission-system/010-permission-design.md](../050-permission-system/010-permission-design.md)
- **GDPR Compliance**: See [../060-gdpr-compliance/010-gdpr-implementation.md](../060-gdpr-compliance/010-gdpr-implementation.md)
- **Event-Sourcing Architecture**: See [../070-event-sourcing-cqrs/010-event-sourcing-architecture.md](../070-event-sourcing-cqrs/010-event-sourcing-architecture.md)

## 3.10. Process Optimization Guidelines

### 3.10.1. Performance Considerations

- **Asynchronous Processing**: Use queues for non-critical operations
- **Batch Operations**: Group similar operations for efficiency
- **Caching Strategy**: Cache frequently accessed data
- **Database Optimization**: Use appropriate indexes and query optimization

### 3.10.2. Error Handling Best Practices

- **Graceful Degradation**: Provide fallback mechanisms
- **Retry Logic**: Implement exponential backoff for transient failures
- **Circuit Breakers**: Prevent cascade failures
- **Monitoring**: Track process success rates and performance metrics

### 3.10.3. Security Considerations

- **Input Validation**: Validate all user inputs
- **Authorization Checks**: Verify permissions at each step
- **Audit Logging**: Log all significant actions
- **Data Protection**: Encrypt sensitive data in transit and at rest

## 3.11. References and Further Reading

### 3.11.1. Business Process Modeling

- [Business Process Model and Notation (BPMN)](https://www.bpmn.org/)
- [Workflow Patterns](http://www.workflowpatterns.com/)
- [Process Mining Techniques](https://www.processmining.org/)

### 3.11.2. User Experience Design

- [User Journey Mapping](https://www.nngroup.com/articles/journey-mapping-101/)
- [Service Design Thinking](https://www.interaction-design.org/literature/topics/service-design)
- [Customer Experience Management](https://www.salesforce.com/resources/articles/customer-experience/)

### 3.11.3. Event-Driven Architecture

- [Event-Driven Architecture Patterns](https://microservices.io/patterns/data/event-driven-architecture.html)
- [Saga Pattern](https://microservices.io/patterns/data/saga.html)
- [Event Sourcing Patterns](https://martinfowler.com/eaaDev/EventSourcing.html)

### 3.11.4. GDPR Compliance

- [GDPR Implementation Guide](https://gdpr.eu/implementation/)
- [Data Protection Impact Assessment](https://gdpr.eu/data-protection-impact-assessment-template/)
- [Privacy by Design Principles](https://www.ipc.on.ca/wp-content/uploads/resources/7foundationalprinciples.pdf)
