# 2. Entity Relationship Diagrams for UMS-STI

## 2.1. Executive Summary

This document provides comprehensive Entity Relationship Diagrams (ERDs) for the User Management System with Single Table Inheritance (UMS-STI) using Mermaid syntax. These diagrams illustrate the database schema, entity relationships, and data structures that support the event-sourced, CQRS-based system with consistent state management across User and Team entities.

## 2.2. Learning Objectives

After reviewing this document, readers will understand:

- **2.2.1.** Complete database schema and entity relationships
- **2.2.2.** Single Table Inheritance implementation for user types
- **2.2.3.** Team hierarchy structure using closure tables
- **2.2.4.** Permission and role assignment relationships
- **2.2.5.** Event store and projection table structures
- **2.2.6.** Consistent state management across entities

## 2.3. Prerequisite Knowledge

Before reviewing these diagrams, ensure familiarity with:

- **2.3.1.** Database design principles and normalization
- **2.3.2.** Single Table Inheritance patterns
- **2.3.3.** Closure table hierarchy implementation
- **2.3.4.** Event-sourcing data structures
- **2.3.5.** CQRS read model design

## 2.4. Core Entity Relationships

### 2.4.1. Complete System ERD

```mermaid
erDiagram
    %% Core User Entities
    USERS {
        string id PK
        string email UK
        string name
        string user_type "Standard|Admin|Guest|SystemUser"
        string state "pending|active|inactive|suspended|archived"
        json profile_data
        timestamp created_at
        timestamp updated_at
        timestamp activated_at
        timestamp deactivated_at
        string activated_by FK
        string deactivated_by FK
        timestamp last_login_at
        string password_hash
        timestamp email_verified_at
        string remember_token
    }

    USER_PROFILES {
        string id PK
        string user_id FK
        string avatar_url
        text bio
        json preferences
        json settings
        json contact_info
        timestamp created_at
        timestamp updated_at
    }

    ADMIN_PROFILES {
        string id PK
        string user_id FK
        json admin_permissions
        json admin_settings
        timestamp last_admin_action
        string admin_level "super|standard|limited"
        json access_restrictions
        timestamp created_at
        timestamp updated_at
    }

    GUEST_PROFILES {
        string id PK
        string user_id FK
        string session_id
        json tracking_data
        timestamp expires_at
        string conversion_source
        json interaction_history
        timestamp created_at
        timestamp updated_at
    }

    %% Team Entities
    TEAMS {
        string id PK
        string name UK
        string parent_id FK
        string state "active|inactive|archived|suspended"
        json settings
        text description
        integer member_count
        string created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp archived_at
        string archived_by FK
    }

    TEAM_HIERARCHY {
        string ancestor_id FK
        string descendant_id FK
        integer depth
        string descendant_name
        timestamp created_at
    }

    TEAM_MEMBERS {
        string id PK
        string team_id FK
        string user_id FK
        string role "member|leader|admin"
        string state "active|inactive|suspended"
        timestamp joined_at
        timestamp left_at
        string added_by FK
        string removed_by FK
        json member_settings
        timestamp created_at
        timestamp updated_at
    }

    %% Permission Entities
    PERMISSIONS {
        string id PK
        string name UK
        string category
        text description
        json metadata
        boolean is_system_permission
        timestamp created_at
        timestamp updated_at
    }

    ROLES {
        string id PK
        string name UK
        text description
        json metadata
        boolean is_system_role
        timestamp created_at
        timestamp updated_at
    }

    USER_PERMISSIONS {
        string id PK
        string user_id FK
        string permission_id FK
        string context_type "global|team|resource"
        string context_id FK
        string granted_by FK
        timestamp granted_at
        timestamp expires_at
        string state "active|inactive|expired"
        timestamp created_at
        timestamp updated_at
    }

    USER_ROLES {
        string id PK
        string user_id FK
        string role_id FK
        string context_type "global|team|resource"
        string context_id FK
        string assigned_by FK
        timestamp assigned_at
        timestamp expires_at
        string state "active|inactive|expired"
        timestamp created_at
        timestamp updated_at
    }

    ROLE_PERMISSIONS {
        string id PK
        string role_id FK
        string permission_id FK
        timestamp created_at
    }

    %% Relationships
    USERS ||--o| USER_PROFILES : "has"
    USERS ||--o| ADMIN_PROFILES : "has (if admin)"
    USERS ||--o| GUEST_PROFILES : "has (if guest)"
    USERS ||--o{ TEAM_MEMBERS : "belongs to teams"
    USERS ||--o{ USER_PERMISSIONS : "has permissions"
    USERS ||--o{ USER_ROLES : "has roles"
    USERS ||--o{ TEAMS : "created by"

    TEAMS ||--o{ TEAMS : "parent-child"
    TEAMS ||--o{ TEAM_HIERARCHY : "hierarchy"
    TEAMS ||--o{ TEAM_MEMBERS : "has members"

    PERMISSIONS ||--o{ USER_PERMISSIONS : "granted to users"
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : "assigned to roles"

    ROLES ||--o{ USER_ROLES : "assigned to users"
    ROLES ||--o{ ROLE_PERMISSIONS : "has permissions"
```

### 2.4.2. User Domain ERD

```mermaid
erDiagram
    USERS {
        string id PK
        string email UK
        string name
        string user_type "Standard|Admin|Guest|SystemUser"
        string state "pending|active|inactive|suspended|archived"
        json profile_data
        timestamp created_at
        timestamp updated_at
        timestamp activated_at
        timestamp deactivated_at
        string activated_by FK
        string deactivated_by FK
        timestamp last_login_at
        string password_hash
        timestamp email_verified_at
        string remember_token
        json notification_preferences
        json privacy_settings
    }

    USER_PROFILES {
        string id PK
        string user_id FK
        string avatar_url
        text bio
        json preferences
        json settings
        json contact_info
        string timezone
        string locale
        json social_links
        timestamp created_at
        timestamp updated_at
    }

    ADMIN_PROFILES {
        string id PK
        string user_id FK
        json admin_permissions
        json admin_settings
        timestamp last_admin_action
        string admin_level "super|standard|limited"
        json access_restrictions
        json audit_settings
        string department
        string supervisor_id FK
        timestamp created_at
        timestamp updated_at
    }

    GUEST_PROFILES {
        string id PK
        string user_id FK
        string session_id UK
        json tracking_data
        timestamp expires_at
        string conversion_source
        json interaction_history
        string ip_address
        string user_agent
        json referrer_data
        timestamp created_at
        timestamp updated_at
    }

    SYSTEM_USER_PROFILES {
        string id PK
        string user_id FK
        string service_name
        json api_credentials
        json rate_limits
        json allowed_operations
        timestamp last_api_call
        integer api_call_count
        json monitoring_settings
        timestamp created_at
        timestamp updated_at
    }

    USER_SESSIONS {
        string id PK
        string user_id FK
        string session_token UK
        string ip_address
        string user_agent
        json session_data
        timestamp created_at
        timestamp last_activity
        timestamp expires_at
        boolean is_active
    }

    USER_LOGIN_HISTORY {
        string id PK
        string user_id FK
        string session_id FK
        string ip_address
        string user_agent
        string login_method "password|token|sso"
        boolean success
        string failure_reason
        timestamp attempted_at
        json metadata
    }

    %% Relationships
    USERS ||--o| USER_PROFILES : "has"
    USERS ||--o| ADMIN_PROFILES : "has (if admin)"
    USERS ||--o| GUEST_PROFILES : "has (if guest)"
    USERS ||--o| SYSTEM_USER_PROFILES : "has (if system)"
    USERS ||--o{ USER_SESSIONS : "has sessions"
    USERS ||--o{ USER_LOGIN_HISTORY : "has login history"

    USER_SESSIONS ||--o{ USER_LOGIN_HISTORY : "login attempts"
    ADMIN_PROFILES }o--|| USERS : "supervised by"
```

### 2.4.3. Team Domain ERD

```mermaid
erDiagram
    TEAMS {
        string id PK
        string name UK
        string parent_id FK
        string state "active|inactive|archived|suspended"
        json settings
        text description
        integer member_count
        string team_type "department|project|working_group|committee"
        string visibility "public|private|restricted"
        string created_by FK
        timestamp created_at
        timestamp updated_at
        timestamp archived_at
        string archived_by FK
        json metadata
    }

    TEAM_HIERARCHY {
        string ancestor_id FK
        string descendant_id FK
        integer depth
        string descendant_name
        string path_string
        timestamp created_at
        timestamp updated_at
    }

    TEAM_MEMBERS {
        string id PK
        string team_id FK
        string user_id FK
        string role "member|leader|admin|observer"
        string state "active|inactive|suspended|pending"
        timestamp joined_at
        timestamp left_at
        string added_by FK
        string removed_by FK
        json member_settings
        json permissions_override
        string invitation_token
        timestamp invitation_expires_at
        timestamp created_at
        timestamp updated_at
    }

    TEAM_MEMBER_HISTORY {
        string id PK
        string team_id FK
        string user_id FK
        string action "added|removed|role_changed|state_changed"
        string old_role
        string new_role
        string old_state
        string new_state
        string performed_by FK
        text reason
        json metadata
        timestamp occurred_at
    }

    TEAM_INVITATIONS {
        string id PK
        string team_id FK
        string invited_user_id FK
        string invited_email
        string role "member|leader|admin|observer"
        string token UK
        string invited_by FK
        timestamp invited_at
        timestamp expires_at
        timestamp accepted_at
        timestamp declined_at
        string state "pending|accepted|declined|expired"
        text message
        json metadata
    }

    TEAM_SETTINGS {
        string id PK
        string team_id FK
        string setting_key
        json setting_value
        string set_by FK
        timestamp set_at
        timestamp updated_at
        boolean is_inherited
        string inherited_from FK
    }

    TEAM_PERMISSIONS {
        string id PK
        string team_id FK
        string permission_id FK
        string granted_to_role
        string granted_by FK
        timestamp granted_at
        timestamp expires_at
        string state "active|inactive|expired"
        json conditions
        timestamp created_at
        timestamp updated_at
    }

    %% Relationships
    TEAMS ||--o{ TEAMS : "parent-child"
    TEAMS ||--o{ TEAM_HIERARCHY : "hierarchy"
    TEAMS ||--o{ TEAM_MEMBERS : "has members"
    TEAMS ||--o{ TEAM_MEMBER_HISTORY : "member history"
    TEAMS ||--o{ TEAM_INVITATIONS : "has invitations"
    TEAMS ||--o{ TEAM_SETTINGS : "has settings"
    TEAMS ||--o{ TEAM_PERMISSIONS : "has permissions"

    TEAM_MEMBERS ||--o{ TEAM_MEMBER_HISTORY : "generates history"
    TEAM_INVITATIONS }o--|| TEAM_MEMBERS : "becomes member"
    TEAM_SETTINGS }o--|| TEAMS : "inherited from"
```

## 2.5. Event Store and Projections ERD

### 2.5.1. Event Store Schema

```mermaid
erDiagram
    STORED_EVENTS {
        string id PK
        string aggregate_root_id
        integer aggregate_version
        string event_class
        json event_data
        json meta_data
        timestamp created_at
        string event_stream
        integer global_sequence
        string causation_id
        string correlation_id
    }

    SNAPSHOTS {
        string id PK
        string aggregate_root_id
        integer aggregate_version
        string aggregate_class
        json state
        timestamp created_at
        integer event_count
        string checksum
    }

    EVENT_METADATA {
        string id PK
        string event_id FK
        string key
        json value
        timestamp created_at
    }

    EVENT_STREAMS {
        string id PK
        string stream_name UK
        string stream_type
        json metadata
        timestamp created_at
        timestamp updated_at
        integer event_count
        integer last_sequence
    }

    %% Relationships
    STORED_EVENTS ||--o{ EVENT_METADATA : "has metadata"
    STORED_EVENTS }o--|| EVENT_STREAMS : "belongs to stream"
    SNAPSHOTS }o--|| STORED_EVENTS : "created from events"
```

### 2.5.2. Read Model Projections

```mermaid
erDiagram
    USER_PROJECTIONS {
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
        string activated_by
        string deactivated_by
        timestamp last_login_at
        integer login_count
        json cached_permissions
        json cached_teams
        timestamp projection_updated_at
    }

    TEAM_PROJECTIONS {
        string id PK
        string name UK
        string parent_id
        string state
        json settings
        text description
        integer member_count
        integer depth_level
        string team_type
        string visibility
        string created_by
        timestamp created_at
        timestamp updated_at
        timestamp archived_at
        string archived_by
        json cached_hierarchy
        json cached_members
        timestamp projection_updated_at
    }

    TEAM_HIERARCHY_PROJECTIONS {
        string ancestor_id FK
        string descendant_id FK
        integer depth
        string descendant_name
        string path_string
        json hierarchy_metadata
        timestamp created_at
        timestamp updated_at
    }

    TEAM_MEMBER_PROJECTIONS {
        string id PK
        string team_id FK
        string user_id FK
        string user_name
        string user_email
        string role
        string state
        timestamp joined_at
        timestamp left_at
        string added_by
        string removed_by
        json member_settings
        json cached_permissions
        timestamp projection_updated_at
    }

    USER_PERMISSION_PROJECTIONS {
        string id PK
        string user_id FK
        string permission_name
        string permission_category
        string context_type
        string context_id
        string granted_by
        timestamp granted_at
        timestamp expires_at
        string state
        json metadata
        timestamp projection_updated_at
    }

    USER_ROLE_PROJECTIONS {
        string id PK
        string user_id FK
        string role_name
        string context_type
        string context_id
        string assigned_by
        timestamp assigned_at
        timestamp expires_at
        string state
        json role_permissions
        timestamp projection_updated_at
    }

    %% Relationships
    USER_PROJECTIONS ||--o{ TEAM_MEMBER_PROJECTIONS : "member of teams"
    USER_PROJECTIONS ||--o{ USER_PERMISSION_PROJECTIONS : "has permissions"
    USER_PROJECTIONS ||--o{ USER_ROLE_PROJECTIONS : "has roles"

    TEAM_PROJECTIONS ||--o{ TEAM_HIERARCHY_PROJECTIONS : "hierarchy"
    TEAM_PROJECTIONS ||--o{ TEAM_MEMBER_PROJECTIONS : "has members"

    TEAM_HIERARCHY_PROJECTIONS }o--|| TEAM_PROJECTIONS : "ancestor"
    TEAM_HIERARCHY_PROJECTIONS }o--|| TEAM_PROJECTIONS : "descendant"
```

## 2.6. Audit and Compliance ERD

### 2.6.1. Audit Logging Schema

```mermaid
erDiagram
    AUDIT_LOGS {
        string id PK
        string event_type
        string user_id FK
        string actor_id FK
        string resource_type
        string resource_id
        string action
        json details
        string ip_address
        string user_agent
        timestamp occurred_at
        string session_id
        json before_state
        json after_state
        string correlation_id
    }

    SECURITY_EVENTS {
        string id PK
        string event_type
        string user_id FK
        string ip_address
        string user_agent
        string severity "low|medium|high|critical"
        text description
        json metadata
        timestamp occurred_at
        string session_id
        boolean is_resolved
        string resolved_by FK
        timestamp resolved_at
        text resolution_notes
    }

    GDPR_COMPLIANCE_LOGS {
        string id PK
        string action "data_export|data_deletion|consent_given|consent_withdrawn"
        string user_id FK
        string requested_by FK
        json details
        timestamp requested_at
        timestamp completed_at
        string status "pending|in_progress|completed|failed"
        text failure_reason
        json metadata
    }

    DATA_RETENTION_POLICIES {
        string id PK
        string resource_type
        string policy_name
        integer retention_days
        string action_after_expiry "delete|anonymize|archive"
        json conditions
        boolean is_active
        string created_by FK
        timestamp created_at
        timestamp updated_at
    }

    %% Relationships
    AUDIT_LOGS }o--|| USER_PROJECTIONS : "performed by user"
    AUDIT_LOGS }o--|| USER_PROJECTIONS : "performed by actor"
    SECURITY_EVENTS }o--|| USER_PROJECTIONS : "related to user"
    SECURITY_EVENTS }o--|| USER_PROJECTIONS : "resolved by"
    GDPR_COMPLIANCE_LOGS }o--|| USER_PROJECTIONS : "for user"
    GDPR_COMPLIANCE_LOGS }o--|| USER_PROJECTIONS : "requested by"
```

### 2.6.2. Analytics and Metrics Schema

```mermaid
erDiagram
    ANALYTICS_EVENTS {
        string id PK
        string event_name
        string user_id FK
        string session_id
        json properties
        timestamp occurred_at
        string ip_address
        string user_agent
        string referrer
        json context
        string correlation_id
    }

    ANALYTICS_METRICS {
        string id PK
        string metric_name
        string metric_type "counter|gauge|histogram|timer"
        float value
        json tags
        timestamp recorded_at
        string aggregation_period "minute|hour|day|week|month"
        json metadata
    }

    DAILY_ACTIVE_USERS {
        string id PK
        string user_id FK
        date activity_date
        integer session_count
        integer action_count
        timestamp first_activity
        timestamp last_activity
        json activity_summary
    }

    USER_ENGAGEMENT_METRICS {
        string id PK
        string user_id FK
        date metric_date
        integer login_count
        integer page_views
        integer actions_performed
        integer time_spent_minutes
        json feature_usage
        timestamp created_at
    }

    TEAM_ACTIVITY_METRICS {
        string id PK
        string team_id FK
        date metric_date
        integer member_count
        integer active_members
        integer actions_performed
        json activity_breakdown
        timestamp created_at
    }

    %% Relationships
    ANALYTICS_EVENTS }o--|| USER_PROJECTIONS : "performed by"
    DAILY_ACTIVE_USERS }o--|| USER_PROJECTIONS : "user activity"
    USER_ENGAGEMENT_METRICS }o--|| USER_PROJECTIONS : "user metrics"
    TEAM_ACTIVITY_METRICS }o--|| TEAM_PROJECTIONS : "team metrics"
```

## 2.7. State Management Consistency

### 2.7.1. User State Transitions

```mermaid
erDiagram
    USER_STATES {
        string state PK
        string description
        json allowed_transitions
        json required_permissions
        boolean is_active_state
        boolean allows_login
        boolean allows_api_access
        json metadata
    }

    USER_STATE_HISTORY {
        string id PK
        string user_id FK
        string from_state
        string to_state
        string changed_by FK
        text reason
        json metadata
        timestamp changed_at
        string correlation_id
    }


    %% Relationships
    USER_STATE_HISTORY }o--|| USER_PROJECTIONS : "user state changes"
    USER_STATE_HISTORY }o--|| USER_PROJECTIONS : "changed by user"
```

### 2.7.2. Team State Transitions

```mermaid
erDiagram
    TEAM_STATES {
        string state PK
        string description
        json allowed_transitions
        json required_permissions
        boolean is_active_state
        boolean allows_members
        boolean allows_subteams
        json metadata
    }

    TEAM_STATE_HISTORY {
        string id PK
        string team_id FK
        string from_state
        string to_state
        string changed_by FK
        text reason
        json metadata
        timestamp changed_at
        string correlation_id
    }


    %% Relationships
    TEAM_STATE_HISTORY }o--|| TEAM_PROJECTIONS : "team state changes"
    TEAM_STATE_HISTORY }o--|| USER_PROJECTIONS : "changed by user"
```

## 2.8. Permission and Role Management ERD

### 2.8.1. Permission System Schema

```mermaid
erDiagram
    PERMISSIONS {
        string id PK
        string name UK
        string category
        text description
        string scope "global|team|resource"
        json metadata
        boolean is_system_permission
        boolean is_inheritable
        timestamp created_at
        timestamp updated_at
    }

    ROLES {
        string id PK
        string name UK
        text description
        string scope "global|team|resource"
        json metadata
        boolean is_system_role
        boolean is_inheritable
        timestamp created_at
        timestamp updated_at
    }

    ROLE_PERMISSIONS {
        string id PK
        string role_id FK
        string permission_id FK
        string granted_by FK
        timestamp granted_at
        json conditions
        timestamp created_at
    }

    USER_PERMISSION_HISTORY {
        string id PK
        string user_id FK
        string permission_id FK
        string action "granted|revoked|expired"
        string context_type
        string context_id
        string performed_by FK
        text reason
        timestamp occurred_at
        json metadata
    }

    USER_ROLE_HISTORY {
        string id PK
        string user_id FK
        string role_id FK
        string action "assigned|revoked|expired"
        string context_type
        string context_id
        string performed_by FK
        text reason
        timestamp occurred_at
        json metadata
    }

    PERMISSION_INHERITANCE {
        string id PK
        string parent_context_type
        string parent_context_id
        string child_context_type
        string child_context_id
        string permission_id FK
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    %% Relationships
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : "assigned to roles"
    PERMISSIONS ||--o{ USER_PERMISSION_PROJECTIONS : "granted to users"
    PERMISSIONS ||--o{ USER_PERMISSION_HISTORY : "permission history"
    PERMISSIONS ||--o{ PERMISSION_INHERITANCE : "inherited permissions"

    ROLES ||--o{ ROLE_PERMISSIONS : "has permissions"
    ROLES ||--o{ USER_ROLE_PROJECTIONS : "assigned to users"
    ROLES ||--o{ USER_ROLE_HISTORY : "role history"

    USER_PERMISSION_PROJECTIONS ||--o{ USER_PERMISSION_HISTORY : "generates history"
    USER_ROLE_PROJECTIONS ||--o{ USER_ROLE_HISTORY : "generates history"
```

## 2.9. Integration and External Systems ERD

### 2.9.1. External System Integration

```mermaid
erDiagram
    EXTERNAL_SYSTEMS {
        string id PK
        string name UK
        string type "sso|api|webhook|analytics"
        string endpoint_url
        json configuration
        json credentials
        boolean is_active
        timestamp created_at
        timestamp updated_at
        string created_by FK
    }

    API_TOKENS {
        string id PK
        string user_id FK
        string token_hash UK
        string name
        json scopes
        timestamp expires_at
        timestamp last_used_at
        string ip_address
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    WEBHOOK_ENDPOINTS {
        string id PK
        string name
        string url
        string secret_key
        json event_types
        boolean is_active
        integer retry_count
        timestamp last_success
        timestamp last_failure
        json failure_details
        timestamp created_at
        timestamp updated_at
    }

    WEBHOOK_DELIVERIES {
        string id PK
        string webhook_endpoint_id FK
        string event_id FK
        string event_type
        json payload
        integer attempt_count
        string status "pending|delivered|failed"
        integer response_code
        text response_body
        timestamp delivered_at
        timestamp created_at
    }

    SSO_PROVIDERS {
        string id PK
        string name UK
        string provider_type "oauth2|saml|ldap"
        json configuration
        json attribute_mapping
        boolean is_active
        boolean auto_provision
        timestamp created_at
        timestamp updated_at
    }

    SSO_USER_MAPPINGS {
        string id PK
        string user_id FK
        string sso_provider_id FK
        string external_user_id
        json external_attributes
        timestamp last_sync
        timestamp created_at
        timestamp updated_at
    }

    %% Relationships
    API_TOKENS }o--|| USER_PROJECTIONS : "belongs to user"
    WEBHOOK_DELIVERIES }o--|| WEBHOOK_ENDPOINTS : "delivered to"
    WEBHOOK_DELIVERIES }o--|| STORED_EVENTS : "triggered by event"
    SSO_USER_MAPPINGS }o--|| USER_PROJECTIONS : "mapped user"
    SSO_USER_MAPPINGS }o--|| SSO_PROVIDERS : "from provider"
```

## 2.10. Cross-References

### 2.10.1. Related Diagrams

- **Architectural Diagrams**: See [010-architectural-diagrams.md](010-architectural-diagrams.md) for system architecture overview
- **Business Process Flows**: See [030-business-process-flows.md](030-business-process-flows.md) for workflow diagrams
- **Swim Lanes**: See [040-swim-lanes.md](040-swim-lanes.md) for responsibility mapping
- **Domain Models**: See [050-domain-models.md](050-domain-models.md) for domain-specific diagrams
- **FSM Diagrams**: See [060-fsm-diagrams.md](060-fsm-diagrams.md) for state machine diagrams

### 2.10.2. Related Documentation

- **Database Foundation**: See [../020-database-foundation/010-database-design.md](../020-database-foundation/010-database-design.md)
- **User Models**: See [../030-user-models/010-sti-architecture-explained.md](../030-user-models/010-sti-architecture-explained.md)
- **Team Hierarchy**: See [../040-team-hierarchy/010-closure-table-theory.md](../040-team-hierarchy/010-closure-table-theory.md)
- **Permission System**: See [../050-permission-system/010-permission-design.md](../050-permission-system/010-permission-design.md)
- **Event-Sourcing Architecture**: See [../070-event-sourcing-cqrs/010-event-sourcing-architecture.md](../070-event-sourcing-cqrs/010-event-sourcing-architecture.md)

## 2.11. Database Constraints and Indexes

### 2.11.1. Primary Key and Unique Constraints

```sql
-- User Domain Constraints
ALTER TABLE users ADD CONSTRAINT uk_users_email UNIQUE (email);
ALTER TABLE user_sessions ADD CONSTRAINT uk_user_sessions_token UNIQUE (session_token);
ALTER TABLE guest_profiles ADD CONSTRAINT uk_guest_profiles_session UNIQUE (session_id);

-- Team Domain Constraints
ALTER TABLE teams ADD CONSTRAINT uk_teams_name UNIQUE (name);
ALTER TABLE team_hierarchy ADD CONSTRAINT pk_team_hierarchy PRIMARY KEY (ancestor_id, descendant_id);
ALTER TABLE team_invitations ADD CONSTRAINT uk_team_invitations_token UNIQUE (token);

-- Permission Domain Constraints
ALTER TABLE permissions ADD CONSTRAINT uk_permissions_name UNIQUE (name);
ALTER TABLE roles ADD CONSTRAINT uk_roles_name UNIQUE (name);

-- Event Store Constraints
ALTER TABLE stored_events ADD CONSTRAINT uk_stored_events_aggregate_version UNIQUE (aggregate_root_id, aggregate_version);
ALTER TABLE event_streams ADD CONSTRAINT uk_event_streams_name UNIQUE (stream_name);
```

### 2.11.2. Performance Indexes

```sql
-- User Domain Indexes
CREATE INDEX idx_users_type_state ON users(user_type, state);
CREATE INDEX idx_users_state_created ON users(state, created_at);
CREATE INDEX idx_users_last_login ON users(last_login_at);
CREATE INDEX idx_user_sessions_user_active ON user_sessions(user_id, is_active);

-- Team Domain Indexes
CREATE INDEX idx_teams_parent_state ON teams(parent_id, state);
CREATE INDEX idx_teams_state_created ON teams(state, created_at);
CREATE INDEX idx_team_hierarchy_descendant_depth ON team_hierarchy(descendant_id, depth);
CREATE INDEX idx_team_members_team_state ON team_members(team_id, state);
CREATE INDEX idx_team_members_user_state ON team_members(user_id, state);

-- Permission Domain Indexes
CREATE INDEX idx_user_permissions_user_context ON user_permissions(user_id, context_type, context_id);
CREATE INDEX idx_user_permissions_permission_state ON user_permissions(permission_id, state);
CREATE INDEX idx_user_roles_user_context ON user_roles(user_id, context_type, context_id);

-- Event Store Indexes
CREATE INDEX idx_stored_events_aggregate ON stored_events(aggregate_root_id);
CREATE INDEX idx_stored_events_class_created ON stored_events(event_class, created_at);
CREATE INDEX idx_stored_events_stream ON stored_events(event_stream);
CREATE INDEX idx_stored_events_correlation ON stored_events(correlation_id);

-- Projection Indexes
CREATE INDEX idx_user_projections_type_state ON user_projections(user_type, state);
CREATE INDEX idx_team_projections_parent_state ON team_projections(parent_id, state);
CREATE INDEX idx_team_member_projections_team_role ON team_member_projections(team_id, role);

-- Audit and Analytics Indexes
CREATE INDEX idx_audit_logs_user_occurred ON audit_logs(user_id, occurred_at);
CREATE INDEX idx_audit_logs_resource_action ON audit_logs(resource_type, action);
CREATE INDEX idx_analytics_events_user_occurred ON analytics_events(user_id, occurred_at);
CREATE INDEX idx_daily_active_users_date ON daily_active_users(activity_date);
```

## 2.12. References and Further Reading

### 2.12.1. Database Design

- [Database Design Fundamentals](https://www.lucidchart.com/pages/database-diagram/database-design)
- [Entity Relationship Modeling](https://www.smartdraw.com/entity-relationship-diagram/)
- [Normalization and Denormalization](https://www.geeksforgeeks.org/normal-forms-in-dbms/)

### 2.12.2. Single Table Inheritance

- [Single Table Inheritance in Laravel](https://laravel.com/docs/eloquent-relationships#polymorphic-relationships)
- [STI Design Patterns](https://martinfowler.com/eaaCatalog/singleTableInheritance.html)
- [Parental Package Documentation](https://github.com/tighten/parental)

### 2.12.3. Closure Tables

- [Closure Table Pattern](https://www.slideshare.net/billkarwin/models-for-hierarchical-data)
- [Hierarchical Data in MySQL](https://mikehillyer.com/articles/managing-hierarchical-data-in-mysql/)
- [Tree Structures in SQL](https://www.postgresql.org/docs/current/ltree.html)

### 2.12.4. Event Sourcing Data Models

- [Event Store Schema Design](https://eventstore.com/blog/event-store-schema-design)
- [Projection Design Patterns](https://eventstore.com/blog/projections-1-theory)
- [CQRS Read Model Design](https://docs.microsoft.com/en-us/azure/architecture/patterns/cqrs)
