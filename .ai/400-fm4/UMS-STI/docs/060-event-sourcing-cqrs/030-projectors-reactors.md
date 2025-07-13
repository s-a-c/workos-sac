# Projectors and Reactors for UMS-STI

## Executive Summary

This document provides comprehensive guidance for implementing projectors and reactors within the event-sourced User Management System with Single Table Inheritance (UMS-STI). Projectors handle the transformation of domain events into optimized read models, while reactors manage side effects and cross-cutting concerns such as notifications, audit logging, and analytics. The implementation leverages `spatie/laravel-event-sourcing` to create a robust, scalable system that maintains eventual consistency between the write and read sides while enabling rich business intelligence and compliance capabilities.

**Key Benefits**: Optimized read models for query performance, decoupled side effect handling, comprehensive audit trails, real-time notifications, and rich analytics data collection for business intelligence.

## Learning Objectives

After completing this document, readers will understand:

- **Projector Design Patterns**: How to transform events into optimized read models
- **Reactor Implementation**: Managing side effects and cross-cutting concerns
- **Event Handling Strategies**: Synchronous vs. asynchronous event processing
- **Read Model Optimization**: Designing projections for specific query patterns
- **Side Effect Management**: Implementing notifications, auditing, and analytics
- **Error Handling**: Resilient event processing and failure recovery
- **Performance Optimization**: Efficient projection updates and reactor processing

## Prerequisite Knowledge

Before implementing projectors and reactors, ensure familiarity with:

- **Event-Sourcing Architecture**: Understanding of event stores, aggregates, and domain events
- **CQRS Implementation**: Command and query separation patterns
- **Laravel Framework**: Eloquent ORM, queues, notifications, and event system
- **Database Design**: Indexing strategies and query optimization
- **UMS-STI Domain**: User types, team hierarchies, and permission systems
- **Spatie Event Sourcing**: Package-specific projector and reactor patterns

## Architectural Overview

### Projectors and Reactors Foundation

Projectors and reactors form the bridge between the event store and the application's read side and side effects:

```
┌─────────────────────────────────────────────────────────────┐
│                Event Processing Architecture                 │
├─────────────────────────────────────────────────────────────┤
│  Event Store → Event Stream → Projectors → Read Models     │
│                     ↓                                       │
│  Event Store → Event Stream → Reactors → Side Effects      │
│                                                             │
│  Side Effects: Notifications, Audit Logs, Analytics        │
└─────────────────────────────────────────────────────────────┘
```

### Projectors (Read Model Updates)

Projectors listen to domain events and update read models:
- **User Projectors**: Maintain user profile and permission projections
- **Team Projectors**: Handle team hierarchy and membership projections
- **Analytics Projectors**: Create aggregated data for reporting

### Reactors (Side Effects)

Reactors handle cross-cutting concerns triggered by events:
- **Notification Reactors**: Send emails, SMS, and push notifications
- **Audit Reactors**: Log security and compliance events
- **Analytics Reactors**: Track user behavior and system metrics
- **Integration Reactors**: Sync with external systems

## Core Concepts Deep Dive

### Projector Design Patterns

Projectors transform events into read models optimized for specific query patterns:

```php
// Base Projector Class
abstract class BaseProjector extends Projector
{
    protected array $handlesEvents = [];
    
    public function onStartingEventReplay(): void
    {
        // Clear existing projections before replay
        $this->clearProjections();
    }
    
    public function onFinishedEventReplay(): void
    {
        // Rebuild indexes and optimize after replay
        $this->optimizeProjections();
    }
    
    abstract protected function clearProjections(): void;
    abstract protected function optimizeProjections(): void;
    
    protected function updateProjection(string $table, array $data, array $conditions): void
    {
        DB::table($table)->updateOrInsert($conditions, $data);
    }
    
    protected function deleteProjection(string $table, array $conditions): void
    {
        DB::table($table)->where($conditions)->delete();
    }
}
```

### User Domain Projectors

**User Profile Projector**:
```php
class UserProfileProjector extends BaseProjector
{
    protected array $handlesEvents = [
        UserRegistrationInitiated::class => 'onUserRegistrationInitiated',
        UserRegistrationCompleted::class => 'onUserRegistrationCompleted',
        UserActivated::class => 'onUserActivated',
        UserDeactivated::class => 'onUserDeactivated',
        UserProfileUpdated::class => 'onUserProfileUpdated',
        UserEmailChanged::class => 'onUserEmailChanged',
        UserPasswordChanged::class => 'onUserPasswordChanged',
    ];
    
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        $this->updateProjection('user_projections', [
            'id' => $event->user_id,
            'email' => $event->email,
            'user_type' => $event->user_type,
            'state' => 'pending',
            'name' => $event->registration_data['name'] ?? '',
            'profile_data' => json_encode($event->registration_data),
            'created_at' => $event->initiated_at,
            'updated_at' => $event->initiated_at,
            'last_login_at' => null,
        ], ['id' => $event->user_id]);
    }
    
    public function onUserRegistrationCompleted(UserRegistrationCompleted $event): void
    {
        $currentProfile = DB::table('user_projections')
            ->where('id', $event->user_id)
            ->value('profile_data');
        
        $profileData = json_decode($currentProfile, true) ?? [];
        $profileData = array_merge($profileData, $event->profile_data);
        
        $this->updateProjection('user_projections', [
            'profile_data' => json_encode($profileData),
            'state' => 'registered',
            'updated_at' => $event->completed_at,
        ], ['id' => $event->user_id]);
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        $this->updateProjection('user_projections', [
            'state' => 'active',
            'activated_at' => $event->activated_at,
            'activated_by' => $event->activated_by,
            'updated_at' => $event->activated_at,
        ], ['id' => $event->user_id]);
    }
    
    public function onUserDeactivated(UserDeactivated $event): void
    {
        $this->updateProjection('user_projections', [
            'state' => 'inactive',
            'deactivated_at' => $event->deactivated_at,
            'deactivated_by' => $event->deactivated_by,
            'deactivation_reason' => $event->reason,
            'updated_at' => $event->deactivated_at,
        ], ['id' => $event->user_id]);
    }
    
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        $updates = ['updated_at' => $event->updated_at];
        
        // Update direct fields
        foreach ($event->new_values as $field => $value) {
            if (in_array($field, ['name', 'email'])) {
                $updates[$field] = $value;
            }
        }
        
        // Update profile_data JSON for other fields
        if (count($event->new_values) > count($updates) - 1) {
            $currentProfile = DB::table('user_projections')
                ->where('id', $event->user_id)
                ->value('profile_data');
            
            $profileData = json_decode($currentProfile, true) ?? [];
            
            foreach ($event->new_values as $field => $value) {
                if (!in_array($field, ['name', 'email'])) {
                    $profileData[$field] = $value;
                }
            }
            
            $updates['profile_data'] = json_encode($profileData);
        }
        
        $this->updateProjection('user_projections', $updates, ['id' => $event->user_id]);
    }
    
    public function onUserEmailChanged(UserEmailChanged $event): void
    {
        $this->updateProjection('user_projections', [
            'email' => $event->new_email,
            'email_verified_at' => $event->verification_required ? null : now(),
            'updated_at' => $event->changed_at,
        ], ['id' => $event->user_id]);
    }
    
    public function onUserPasswordChanged(UserPasswordChanged $event): void
    {
        $this->updateProjection('user_projections', [
            'password_changed_at' => $event->changed_at,
            'password_strength_score' => $event->password_strength_score,
            'updated_at' => $event->changed_at,
        ], ['id' => $event->user_id]);
    }
    
    protected function clearProjections(): void
    {
        DB::table('user_projections')->truncate();
    }
    
    protected function optimizeProjections(): void
    {
        DB::statement('ANALYZE user_projections');
    }
}
```

**User Permission Projector**:
```php
class UserPermissionProjector extends BaseProjector
{
    protected array $handlesEvents = [
        UserPermissionGranted::class => 'onUserPermissionGranted',
        UserPermissionRevoked::class => 'onUserPermissionRevoked',
        UserRoleAssigned::class => 'onUserRoleAssigned',
        UserRoleRevoked::class => 'onUserRoleRevoked',
    ];
    
    public function onUserPermissionGranted(UserPermissionGranted $event): void
    {
        $this->updateProjection('user_permission_projections', [
            'user_id' => $event->user_id,
            'permission' => $event->permission,
            'context' => json_encode($event->context),
            'granted_at' => $event->granted_at,
            'granted_by' => $event->granted_by,
            'is_active' => true,
        ], [
            'user_id' => $event->user_id,
            'permission' => $event->permission,
            'context' => json_encode($event->context),
        ]);
    }
    
    public function onUserPermissionRevoked(UserPermissionRevoked $event): void
    {
        DB::table('user_permission_projections')
            ->where('user_id', $event->user_id)
            ->where('permission', $event->permission)
            ->update([
                'is_active' => false,
                'revoked_at' => $event->revoked_at,
                'revoked_by' => $event->revoked_by,
                'revocation_reason' => $event->reason,
            ]);
    }
    
    public function onUserRoleAssigned(UserRoleAssigned $event): void
    {
        $this->updateProjection('user_role_projections', [
            'user_id' => $event->user_id,
            'role' => $event->role,
            'context' => json_encode($event->context),
            'assigned_at' => $event->assigned_at,
            'assigned_by' => $event->assigned_by,
            'is_active' => true,
        ], [
            'user_id' => $event->user_id,
            'role' => $event->role,
            'context' => json_encode($event->context),
        ]);
    }
    
    public function onUserRoleRevoked(UserRoleRevoked $event): void
    {
        DB::table('user_role_projections')
            ->where('user_id', $event->user_id)
            ->where('role', $event->role)
            ->update([
                'is_active' => false,
                'revoked_at' => $event->revoked_at,
                'revoked_by' => $event->revoked_by,
                'revocation_reason' => $event->reason,
            ]);
    }
    
    protected function clearProjections(): void
    {
        DB::table('user_permission_projections')->truncate();
        DB::table('user_role_projections')->truncate();
    }
    
    protected function optimizeProjections(): void
    {
        DB::statement('ANALYZE user_permission_projections');
        DB::statement('ANALYZE user_role_projections');
    }
}
```

### Team Domain Projectors

**Team Hierarchy Projector**:
```php
class TeamHierarchyProjector extends BaseProjector
{
    protected array $handlesEvents = [
        TeamCreated::class => 'onTeamCreated',
        TeamUpdated::class => 'onTeamUpdated',
        TeamArchived::class => 'onTeamArchived',
        TeamHierarchyChanged::class => 'onTeamHierarchyChanged',
    ];
    
    public function onTeamCreated(TeamCreated $event): void
    {
        // Update team projection
        $this->updateProjection('team_projections', [
            'id' => $event->team_id,
            'name' => $event->name,
            'parent_id' => $event->parent_team_id,
            'state' => 'active',
            'settings' => json_encode($event->team_settings),
            'member_count' => 0,
            'created_at' => $event->created_at,
            'updated_at' => $event->created_at,
            'created_by' => $event->created_by,
        ], ['id' => $event->team_id]);
        
        // Update closure table for hierarchy
        $this->updateTeamHierarchy($event->team_id, $event->parent_team_id);
    }
    
    public function onTeamUpdated(TeamUpdated $event): void
    {
        $updates = ['updated_at' => $event->updated_at];
        
        foreach ($event->new_values as $field => $value) {
            if ($field === 'name') {
                $updates['name'] = $value;
                
                // Update denormalized name in hierarchy projection
                DB::table('team_hierarchy_projections')
                    ->where('descendant_id', $event->team_id)
                    ->update(['descendant_name' => $value]);
            } elseif ($field === 'settings') {
                $updates['settings'] = json_encode($value);
            }
        }
        
        $this->updateProjection('team_projections', $updates, ['id' => $event->team_id]);
    }
    
    public function onTeamArchived(TeamArchived $event): void
    {
        $this->updateProjection('team_projections', [
            'state' => 'archived',
            'archived_at' => $event->archived_at,
            'archived_by' => $event->archived_by,
            'archive_reason' => $event->reason,
            'updated_at' => $event->archived_at,
        ], ['id' => $event->team_id]);
    }
    
    public function onTeamHierarchyChanged(TeamHierarchyChanged $event): void
    {
        // Remove old hierarchy relationships
        DB::table('team_hierarchy_projections')
            ->where('descendant_id', $event->team_id)
            ->delete();
        
        // Add new hierarchy relationships
        $this->updateTeamHierarchy($event->team_id, $event->new_parent_id);
        
        // Update parent_id in team projection
        $this->updateProjection('team_projections', [
            'parent_id' => $event->new_parent_id,
            'updated_at' => $event->changed_at,
        ], ['id' => $event->team_id]);
    }
    
    private function updateTeamHierarchy(string $teamId, ?string $parentId): void
    {
        $teamName = DB::table('team_projections')
            ->where('id', $teamId)
            ->value('name');
        
        // Self-reference (depth 0)
        DB::table('team_hierarchy_projections')->insert([
            'ancestor_id' => $teamId,
            'descendant_id' => $teamId,
            'depth' => 0,
            'descendant_name' => $teamName,
        ]);
        
        if ($parentId) {
            // Get all ancestors of the parent
            $ancestors = DB::table('team_hierarchy_projections')
                ->where('descendant_id', $parentId)
                ->get();
            
            foreach ($ancestors as $ancestor) {
                DB::table('team_hierarchy_projections')->insert([
                    'ancestor_id' => $ancestor->ancestor_id,
                    'descendant_id' => $teamId,
                    'depth' => $ancestor->depth + 1,
                    'descendant_name' => $teamName,
                ]);
            }
        }
    }
    
    protected function clearProjections(): void
    {
        DB::table('team_projections')->truncate();
        DB::table('team_hierarchy_projections')->truncate();
    }
    
    protected function optimizeProjections(): void
    {
        DB::statement('ANALYZE team_projections');
        DB::statement('ANALYZE team_hierarchy_projections');
    }
}
```

**Team Membership Projector**:
```php
class TeamMembershipProjector extends BaseProjector
{
    protected array $handlesEvents = [
        TeamMemberAdded::class => 'onTeamMemberAdded',
        TeamMemberRemoved::class => 'onTeamMemberRemoved',
        TeamMemberRoleChanged::class => 'onTeamMemberRoleChanged',
    ];
    
    public function onTeamMemberAdded(TeamMemberAdded $event): void
    {
        // Get user details for denormalization
        $user = DB::table('user_projections')
            ->where('id', $event->user_id)
            ->first();
        
        $this->updateProjection('team_member_projections', [
            'team_id' => $event->team_id,
            'user_id' => $event->user_id,
            'role' => $event->role,
            'user_name' => $user->name ?? '',
            'user_email' => $user->email ?? '',
            'joined_at' => $event->added_at,
            'added_by' => $event->added_by,
            'is_active' => true,
        ], [
            'team_id' => $event->team_id,
            'user_id' => $event->user_id,
        ]);
        
        // Update member count
        $this->updateMemberCount($event->team_id);
    }
    
    public function onTeamMemberRemoved(TeamMemberRemoved $event): void
    {
        DB::table('team_member_projections')
            ->where('team_id', $event->team_id)
            ->where('user_id', $event->user_id)
            ->update([
                'is_active' => false,
                'removed_at' => $event->removed_at,
                'removed_by' => $event->removed_by,
                'removal_reason' => $event->reason,
            ]);
        
        // Update member count
        $this->updateMemberCount($event->team_id);
    }
    
    public function onTeamMemberRoleChanged(TeamMemberRoleChanged $event): void
    {
        $this->updateProjection('team_member_projections', [
            'role' => $event->new_role,
            'previous_role' => $event->old_role,
            'role_changed_at' => $event->changed_at,
            'role_changed_by' => $event->changed_by,
        ], [
            'team_id' => $event->team_id,
            'user_id' => $event->user_id,
        ]);
    }
    
    private function updateMemberCount(string $teamId): void
    {
        $memberCount = DB::table('team_member_projections')
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->count();
        
        DB::table('team_projections')
            ->where('id', $teamId)
            ->update(['member_count' => $memberCount]);
    }
    
    protected function clearProjections(): void
    {
        DB::table('team_member_projections')->truncate();
    }
    
    protected function optimizeProjections(): void
    {
        DB::statement('ANALYZE team_member_projections');
    }
}
```

### Reactor Design Patterns

Reactors handle side effects and cross-cutting concerns:

```php
// Base Reactor Class
abstract class BaseReactor extends Reactor
{
    protected array $handlesEvents = [];
    
    protected function shouldProcess(ShouldBeStored $event): bool
    {
        // Override to add conditional processing logic
        return true;
    }
    
    protected function handleFailure(ShouldBeStored $event, \Throwable $exception): void
    {
        // Log failure and optionally retry
        Log::error('Reactor processing failed', [
            'reactor' => static::class,
            'event' => get_class($event),
            'event_data' => $event->toArray(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        
        // Optionally queue for retry
        if ($this->shouldRetry($exception)) {
            $this->queueForRetry($event);
        }
    }
    
    protected function shouldRetry(\Throwable $exception): bool
    {
        // Retry on temporary failures, not on validation errors
        return !($exception instanceof ValidationException);
    }
    
    protected function queueForRetry(ShouldBeStored $event): void
    {
        dispatch(new RetryReactorJob(static::class, $event))
            ->delay(now()->addMinutes(5));
    }
}
```

### Notification Reactors

**Email Notification Reactor**:
```php
class EmailNotificationReactor extends BaseReactor
{
    protected array $handlesEvents = [
        UserRegistrationInitiated::class => 'onUserRegistrationInitiated',
        UserActivated::class => 'onUserActivated',
        UserPasswordChanged::class => 'onUserPasswordChanged',
        TeamMemberAdded::class => 'onTeamMemberAdded',
        UserPermissionGranted::class => 'onUserPermissionGranted',
    ];
    
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        if (!$this->shouldProcess($event)) {
            return;
        }
        
        try {
            $user = $this->getUserProjection($event->user_id);
            
            if ($user) {
                Mail::to($user->email)->queue(new WelcomeEmail($user));
                
                // Send activation email if required
                if ($event->user_type !== 'Guest') {
                    Mail::to($user->email)->queue(new ActivationEmail($user));
                }
            }
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        if (!$this->shouldProcess($event)) {
            return;
        }
        
        try {
            $user = $this->getUserProjection($event->user_id);
            
            if ($user) {
                Mail::to($user->email)->queue(new AccountActivatedEmail($user));
            }
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    public function onUserPasswordChanged(UserPasswordChanged $event): void
    {
        if (!$this->shouldProcess($event)) {
            return;
        }
        
        try {
            $user = $this->getUserProjection($event->user_id);
            
            if ($user) {
                Mail::to($user->email)->queue(new PasswordChangedEmail($user));
            }
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    public function onTeamMemberAdded(TeamMemberAdded $event): void
    {
        if (!$this->shouldProcess($event)) {
            return;
        }
        
        try {
            $user = $this->getUserProjection($event->user_id);
            $team = $this->getTeamProjection($event->team_id);
            
            if ($user && $team) {
                Mail::to($user->email)->queue(new TeamMembershipEmail($user, $team, $event->role));
            }
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    public function onUserPermissionGranted(UserPermissionGranted $event): void
    {
        if (!$this->shouldProcess($event)) {
            return;
        }
        
        try {
            $user = $this->getUserProjection($event->user_id);
            
            if ($user && $this->isSignificantPermission($event->permission)) {
                Mail::to($user->email)->queue(new PermissionGrantedEmail($user, $event->permission));
            }
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    private function getUserProjection(string $userId): ?object
    {
        return DB::table('user_projections')->where('id', $userId)->first();
    }
    
    private function getTeamProjection(string $teamId): ?object
    {
        return DB::table('team_projections')->where('id', $teamId)->first();
    }
    
    private function isSignificantPermission(string $permission): bool
    {
        $significantPermissions = [
            'admin_access',
            'user_management',
            'team_management',
            'system_configuration',
        ];
        
        return in_array($permission, $significantPermissions);
    }
    
    protected function shouldProcess(ShouldBeStored $event): bool
    {
        // Don't send emails in testing environment
        if (app()->environment('testing')) {
            return false;
        }
        
        // Check if notifications are enabled
        return config('notifications.email.enabled', true);
    }
}
```

### Audit Logging Reactor

**Audit Log Reactor**:
```php
class AuditLogReactor extends BaseReactor
{
    protected array $handlesEvents = [
        UserRegistrationInitiated::class => 'onUserRegistrationInitiated',
        UserActivated::class => 'onUserActivated',
        UserDeactivated::class => 'onUserDeactivated',
        UserPermissionGranted::class => 'onUserPermissionGranted',
        UserPermissionRevoked::class => 'onUserPermissionRevoked',
        TeamCreated::class => 'onTeamCreated',
        TeamMemberAdded::class => 'onTeamMemberAdded',
        TeamMemberRemoved::class => 'onTeamMemberRemoved',
        UserLoggedIn::class => 'onUserLoggedIn',
        FailedLoginAttempt::class => 'onFailedLoginAttempt',
    ];
    
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        $this->createAuditLog([
            'event_type' => 'user_registration_initiated',
            'user_id' => $event->user_id,
            'actor_id' => $event->user_id,
            'resource_type' => 'user',
            'resource_id' => $event->user_id,
            'action' => 'create',
            'details' => [
                'email' => $event->email,
                'user_type' => $event->user_type,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => $event->initiated_at,
        ]);
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        $this->createAuditLog([
            'event_type' => 'user_activated',
            'user_id' => $event->user_id,
            'actor_id' => $event->activated_by,
            'resource_type' => 'user',
            'resource_id' => $event->user_id,
            'action' => 'activate',
            'details' => [
                'activation_method' => $event->activation_method,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => $event->activated_at,
        ]);
    }
    
    public function onUserDeactivated(UserDeactivated $event): void
    {
        $this->createAuditLog([
            'event_type' => 'user_deactivated',
            'user_id' => $event->user_id,
            'actor_id' => $event->deactivated_by,
            'resource_type' => 'user',
            'resource_id' => $event->user_id,
            'action' => 'deactivate',
            'details' => [
                'reason' => $event->reason,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => $event->deactivated_at,
        ]);
    }
    
    public function onUserPermissionGranted(UserPermissionGranted $event): void
    {
        $this->createAuditLog([
            'event_type' => 'permission_granted',
            'user_id' => $event->user_id,
            'actor_id' => $event->granted_by,
            'resource_type' => 'permission',
            'resource_id' => $event->permission,
            'action' => 'grant',
            'details' => [
                'permission' => $event->permission,
                'context' => $event->context,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => $event->granted_at,
        ]);
    }
    
    public function onUserPermissionRevoked(UserPermissionRevoked $event): void
    {
        $this->createAuditLog([
            'event_type' => 'permission_revoked',
            'user_id' => $event->user_id,
            'actor_id' => $event->revoked_by,
            'resource_type' => 'permission',
            'resource_id' => $event->permission,
            'action' => 'revoke',
            'details' => [
                'permission' => $event->permission,
                'reason' => $event->reason,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => $event->revoked_at,
        ]);
    }
    
    public function onUserLoggedIn(UserLoggedIn $event): void
    {
        $this->createAuditLog([
            'event_type' => 'user_login',
            'user_id' => $event->user_id,
            'actor_id' => $event->user_id,
            'resource_type' => 'session',
            'resource_id' => $event->session_id,
            'action' => 'login',
            'details' => [
                'login_method' => $event->login_method,
                'session_id' => $event->session_id,
            ],
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'occurred_at' => $event->logged_in_at,
        ]);
    }
    
    public function onFailedLoginAttempt(FailedLoginAttempt $event): void
    {
        $this->createAuditLog([
            'event_type' => 'failed_login_attempt',
            'user_id' => null,
            'actor_id' => null,
            'resource_type' => 'authentication',
            'resource_id' => $event->email,
            'action' => 'login_attempt',
            'details' => [
                'email' => $event->email,
                'failure_reason' => $event->failure_reason,
            ],
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'occurred_at' => $event->attempted_at,
        ]);
    }
    
    private function createAuditLog(array $data): void
    {
        try {
            DB::table('audit_logs')->insert(array_merge($data, [
                'id' => Str::uuid(),
                'created_at' => now(),
            ]));
        } catch (\Throwable $e) {
            // Audit logging should never fail the main process
            Log::error('Failed to create audit log', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);
        }
    }
    
    protected function shouldProcess(ShouldBeStored $event): bool
    {
        // Always process audit events
        return true;
    }
}
```

### Analytics Reactor

**Analytics Reactor**:
```php
class AnalyticsReactor extends BaseReactor
{
    protected array $handlesEvents = [
        UserRegistrationInitiated::class => 'onUserRegistrationInitiated',
        UserActivated::class => 'onUserActivated',
        UserLoggedIn::class => 'onUserLoggedIn',
        TeamCreated::class => 'onTeamCreated',
        TeamMemberAdded::class => 'onTeamMemberAdded',
    ];
    
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        $this->trackEvent('user_registration_started', [
            'user_id' => $event->user_id,
            'user_type' => $event->user_type,
            'registration_source' => $this->getRegistrationSource(),
            'timestamp' => $event->initiated_at,
        ]);
        
        $this->updateMetric('users.registrations.total', 1);
        $this->updateMetric("users.registrations.by_type.{$event->user_type}", 1);
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        $this->trackEvent('user_activated', [
            'user_id' => $event->user_id,
            'activation_method' => $event->activation_method,
            'activated_by' => $event->activated_by,
            'timestamp' => $event->activated_at,
        ]);
        
        $this->updateMetric('users.activations.total', 1);
        $this->updateMetric("users.activations.by_method.{$event->activation_method}", 1);
        
        // Calculate activation time
        $registrationTime = DB::table('stored_events')
            ->where('aggregate_root_id', $event->user_id)
            ->where('event_class', UserRegistrationInitiated::class)
            ->value('created_at');
        
        if ($registrationTime) {
            $activationTimeMinutes = Carbon::parse($registrationTime)
                ->diffInMinutes(Carbon::parse($event->activated_at));
            
            $this->trackMetric('users.activation_time_minutes', $activationTimeMinutes);
        }
    }
    
    public function onUserLoggedIn(UserLoggedIn $event): void
    {
        $this->trackEvent('user_login', [
            'user_id' => $event->user_id,
            'login_method' => $event->login_method,
            'session_id' => $event->session_id,
            'timestamp' => $event->logged_in_at,
        ]);
        
        $this->updateMetric('users.logins.total', 1);
        $this->updateMetric("users.logins.by_method.{$event->login_method}", 1);
        
        // Track daily active users
        $this->trackDailyActiveUser($event->user_id, $event->logged_in_at);
    }
    
    public function onTeamCreated(TeamCreated $event): void
    {
        $this->trackEvent('team_created', [
            'team_id' => $event->team_id,
            'created_by' => $event->created_by,
            'has_parent' => !empty($event->parent_team_id),
            'timestamp' => $event->created_at,
        ]);
        
        $this->updateMetric('teams.created.total', 1);
        
        if ($event->parent_team_id) {
            $this->updateMetric('teams.created.with_parent', 1);
        } else {
            $this->updateMetric('teams.created.root_level', 1);
        }
    }
    
    public function onTeamMemberAdded(TeamMemberAdded $event): void
    {
        $this->trackEvent('team_member_added', [
            'team_id' => $event->team_id,
            'user_id' => $event->user_id,
            'role' => $event->role,
            'added_by' => $event->added_by,
            'timestamp' => $event->added_at,
        ]);
        
        $this->updateMetric('teams.members.added.total', 1);
        $this->updateMetric("teams.members.added.by_role.{$event->role}", 1);
    }
    
    private function trackEvent(string $eventName, array $properties): void
    {
        try {
            DB::table('analytics_events')->insert([
                'id' => Str::uuid(),
                'event_name' => $eventName,
                'properties' => json_encode($properties),
                'occurred_at' => $properties['timestamp'] ?? now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to track analytics event', [
                'event_name' => $eventName,
                'properties' => $properties,
                'exception' => $e->getMessage(),
            ]);
        }
    }
    
    private function updateMetric(string $metricName, int $increment = 1): void
    {
        try {
            DB::table('analytics_metrics')
                ->updateOrInsert(
                    ['metric_name' => $metricName],
                    [
                        'value' => DB::raw("value + {$increment}"),
                        'updated_at' => now(),
                    ]
                );
        } catch (\Throwable $e) {
            Log::error('Failed to update analytics metric', [
                'metric_name' => $metricName,
                'increment' => $increment,
                'exception' => $e->getMessage(),
            ]);
        }
    }
    
    private function trackMetric(string $metricName, float $value): void
    {
        try {
            DB::table('analytics_metric_values')->insert([
                'id' => Str::uuid(),
                'metric_name' => $metricName,
                'value' => $value,
                'recorded_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to track analytics metric value', [
                'metric_name' => $metricName,
                'value' => $value,
                'exception' => $e->getMessage(),
            ]);
        }
    }
    
    private function trackDailyActiveUser(string $userId, string $timestamp): void
    {
        $date = Carbon::parse($timestamp)->format('Y-m-d');
        
        try {
            DB::table('analytics_daily_active_users')
                ->updateOrInsert(
                    ['user_id' => $userId, 'date' => $date],
                    ['created_at' => now()]
                );
        } catch (\Throwable $e) {
            Log::error('Failed to track daily active user', [
                'user_id' => $userId,
                'date' => $date,
                'exception' => $e->getMessage(),
            ]);
        }
    }
    
    private function getRegistrationSource(): string
    {
        $referer = request()->header('referer');
        $userAgent = request()->userAgent();
        
        if (str_contains($userAgent, 'Mobile')) {
            return 'mobile';
        }
        
        if ($referer && str_contains($referer, 'admin')) {
            return 'admin_panel';
        }
        
        return 'web';
    }
    
    protected function shouldProcess(ShouldBeStored $event): bool
    {
        return config('analytics.enabled', true);
    }
}
```

### GDPR Compliance Reactor

**GDPR Compliance Reactor**:
```php
class GdprComplianceReactor extends BaseReactor
{
    protected array $handlesEvents = [
        DataExportRequested::class => 'onDataExportRequested',
        DataDeletionRequested::class => 'onDataDeletionRequested',
        UserDeactivated::class => 'onUserDeactivated',
    ];
    
    public function onDataExportRequested(DataExportRequested $event): void
    {
        try {
            // Queue data export job
            dispatch(new ExportUserDataJob($event->user_id, $event->export_scope))
                ->onQueue('gdpr');
            
            // Log compliance action
            $this->logComplianceAction('data_export_requested', [
                'user_id' => $event->user_id,
                'requested_by' => $event->requested_by,
                'export_scope' => $event->export_scope,
                'requested_at' => $event->requested_at,
            ]);
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    public function onDataDeletionRequested(DataDeletionRequested $event): void
    {
        try {
            // Queue data deletion job with delay for review period
            dispatch(new DeleteUserDataJob($event->user_id, $event->deletion_scope))
                ->delay(now()->addDays(30)) // 30-day review period
                ->onQueue('gdpr');
            
            // Log compliance action
            $this->logComplianceAction('data_deletion_requested', [
                'user_id' => $event->user_id,
                'requested_by' => $event->requested_by,
                'deletion_scope' => $event->deletion_scope,
                'requested_at' => $event->requested_at,
                'scheduled_deletion' => now()->addDays(30),
            ]);
            
            // Notify administrators
            $this->notifyAdministrators('data_deletion_requested', $event);
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    public function onUserDeactivated(UserDeactivated $event): void
    {
        try {
            // Check if this is a GDPR-related deactivation
            if ($this->isGdprDeactivation($event->reason)) {
                // Start data retention countdown
                dispatch(new StartDataRetentionCountdownJob($event->user_id))
                    ->delay(now()->addDays(1))
                    ->onQueue('gdpr');
                
                $this->logComplianceAction('gdpr_deactivation', [
                    'user_id' => $event->user_id,
                    'deactivated_by' => $event->deactivated_by,
                    'reason' => $event->reason,
                    'deactivated_at' => $event->deactivated_at,
                ]);
            }
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }
    
    private function logComplianceAction(string $action, array $details): void
    {
        DB::table('gdpr_compliance_logs')->insert([
            'id' => Str::uuid(),
            'action' => $action,
            'details' => json_encode($details),
            'created_at' => now(),
        ]);
    }
    
    private function notifyAdministrators(string $action, ShouldBeStored $event): void
    {
        $administrators = DB::table('user_projections')
            ->where('user_type', 'Admin')
            ->where('state', 'active')
            ->get();
        
        foreach ($administrators as $admin) {
            Mail::to($admin->email)->queue(new GdprActionNotification($action, $event));
        }
    }
    
    private function isGdprDeactivation(string $reason): bool
    {
        $gdprReasons = [
            'gdpr_request',
            'data_deletion_request',
            'right_to_be_forgotten',
            'privacy_violation',
        ];
        
        return in_array(strtolower($reason), $gdprReasons);
    }
    
    protected function shouldProcess(ShouldBeStored $event): bool
    {
        return config('gdpr.enabled', true);
    }
}
```

## Implementation Principles & Patterns

### Asynchronous Processing

Implement queue-based processing for non-critical projectors and reactors:

```php
// Asynchronous Projector
class AsynchronousUserProjector extends UserProfileProjector
{
    public function onUserRegistrationInitiated(UserRegistrationInitiated $event): void
    {
        // Queue the projection update
        dispatch(new UpdateUserProjectionJob($event))->onQueue('projections');
    }
    
    public function onUserActivated(UserActivated $event): void
    {
        dispatch(new UpdateUserProjectionJob($event))->onQueue('projections');
    }
    
    // Other event handlers follow the same pattern
}

// Projection Update Job
class UpdateUserProjectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        private ShouldBeStored $event
    ) {}
    
    public function handle(): void
    {
        $projector = new UserProfileProjector();
        $eventClass = get_class($this->event);
        $handlerMethod = $projector->getHandlerMethod($eventClass);
        
        if ($handlerMethod) {
            $projector->$handlerMethod($this->event);
        }
    }
}
```

### Error Handling and Resilience

Implement robust error handling for projectors and reactors:

```php
// Resilient Projector
class ResilientProjector extends BaseProjector
{
    protected int $maxRetries = 3;
    protected int $retryDelay = 60; // seconds
    
    protected function handleEvent(ShouldBeStored $event, string $method): void
    {
        $attempt = 0;
        
        while ($attempt < $this->maxRetries) {
            try {
                $this->$method($event);
                return; // Success, exit retry loop
            } catch (\Throwable $e) {
                $attempt++;
                
                Log::warning('Projector event handling failed', [
                    'projector' => static::class,
                    'event' => get_class($event),
                    'method' => $method,
                    'attempt' => $attempt,
                    'exception' => $e->getMessage(),
                ]);
                
                if ($attempt >= $this->maxRetries) {
                    $this->handleFinalFailure($event, $method, $e);
                    throw $e;
                }
                
                sleep($this->retryDelay * $attempt); // Exponential backoff
            }
        }
    }
    
    protected function handleFinalFailure(ShouldBeStored $event, string $method, \Throwable $e): void
    {
        // Log critical failure
        Log::critical('Projector permanently failed', [
            'projector' => static::class,
            'event' => get_class($event),
            'method' => $method,
            'event_data' => $event->toArray(),
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        // Store failed event for manual processing
        DB::table('failed_projector_events')->insert([
            'id' => Str::uuid(),
            'projector_class' => static::class,
            'event_class' => get_class($event),
            'event_data' => json_encode($event->toArray()),
            'method' => $method,
            'exception' => $e->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
```

### Conditional Processing

Implement conditional processing based on business rules:

```php
// Conditional Reactor
class ConditionalNotificationReactor extends BaseReactor
{
    protected function shouldProcess(ShouldBeStored $event): bool
    {
        // Base conditions
        if (!parent::shouldProcess($event)) {
            return false;
        }
        
        // Event-specific conditions
        return match (get_class($event)) {
            UserRegistrationInitiated::class => $this->shouldProcessUserRegistration($event),
            TeamMemberAdded::class => $this->shouldProcessTeamMemberAdded($event),
            UserPermissionGranted::class => $this->shouldProcessPermissionGranted($event),
            default => true
        };
    }
    
    private function shouldProcessUserRegistration(UserRegistrationInitiated $event): bool
    {
        // Don't send notifications for guest users
        if ($event->user_type === 'Guest') {
            return false;
        }
        
        // Check user preferences (if available)
        $preferences = $this->getUserNotificationPreferences($event->user_id);
        return $preferences['email_notifications'] ?? true;
    }
    
    private function shouldProcessTeamMemberAdded(TeamMemberAdded $event): bool
    {
        // Only notify for significant roles
        $significantRoles = ['leader', 'admin'];
        return in_array($event->role, $significantRoles);
    }
    
    private function shouldProcessPermissionGranted(UserPermissionGranted $event): bool
    {
        // Only notify for high-privilege permissions
        $highPrivilegePermissions = [
            'admin_access',
            'user_management',
            'system_configuration',
        ];
        
        return in_array($event->permission, $highPrivilegePermissions);
    }
    
    private function getUserNotificationPreferences(string $userId): array
    {
        $preferences = DB::table('user_notification_preferences')
            ->where('user_id', $userId)
            ->first();
        
        return $preferences ? json_decode($preferences->preferences, true) : [];
    }
}
```

## Step-by-Step Implementation Guide

### Phase 1: Projector Infrastructure (Week 1)

**Step 1: Create Base Projector Classes**
```php
// app/Projectors/BaseProjector.php
abstract class BaseProjector extends Projector
{
    // Implementation as shown above
}
```

**Step 2: Create Projection Tables**
```bash
php artisan make:migration create_user_projections_table
php artisan make:migration create_user_permission_projections_table
php artisan make:migration create_team_projections_table
php artisan make:migration create_team_hierarchy_projections_table
php artisan make:migration create_team_member_projections_table
```

**Step 3: Implement User Projectors**
```php
// app/Projectors/UserProfileProjector.php
// app/Projectors/UserPermissionProjector.php
// Implementation as shown above
```

### Phase 2: Team Projectors (Week 2)

**Step 1: Implement Team Projectors**
```php
// app/Projectors/TeamHierarchyProjector.php
// app/Projectors/TeamMembershipProjector.php
// Implementation as shown above
```

**Step 2: Test Projector Functionality**
```php
// tests/Unit/Projectors/UserProfileProjectorTest.php
class UserProfileProjectorTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_user_registration_creates_projection()
    {
        $event = new UserRegistrationInitiated(
            user_id: 'user-123',
            email: 'test@example.com',
            user_type: 'Standard',
            registration_data: ['name' => 'Test User'],
            initiated_at: now()
        );
        
        $projector = new UserProfileProjector();
        $projector->onUserRegistrationInitiated($event);
        
        $this->assertDatabaseHas('user_projections', [
            'id' => 'user-123',
            'email' => 'test@example.com',
            'state' => 'pending',
        ]);
    }
}
```

### Phase 3: Reactor Infrastructure (Week 3)

**Step 1: Create Base Reactor Classes**
```php
// app/Reactors/BaseReactor.php
abstract class BaseReactor extends Reactor
{
    // Implementation as shown above
}
```

**Step 2: Create Reactor Tables**
```bash
php artisan make:migration create_audit_logs_table
php artisan make:migration create_analytics_events_table
php artisan make:migration create_analytics_metrics_table
php artisan make:migration create_gdpr_compliance_logs_table
```

**Step 3: Implement Core Reactors**
```php
// app/Reactors/EmailNotificationReactor.php
// app/Reactors/AuditLogReactor.php
// app/Reactors/AnalyticsReactor.php
// Implementation as shown above
```

### Phase 4: Advanced Features (Week 4)

**Step 1: Implement GDPR Compliance**
```php
// app/Reactors/GdprComplianceReactor.php
// app/Jobs/ExportUserDataJob.php
// app/Jobs/DeleteUserDataJob.php
// Implementation as shown above
```

**Step 2: Add Error Handling and Monitoring**
```php
// app/Console/Commands/MonitorProjections.php
class MonitorProjections extends Command
{
    protected $signature = 'projections:monitor';
    
    public function handle()
    {
        $this->checkProjectionLag();
        $this->checkFailedEvents();
        $this->validateProjectionIntegrity();
    }
    
    private function checkProjectionLag(): void
    {
        $lastEventTime = DB::table('stored_events')->max('created_at');
        $lastProjectionTime = DB::table('user_projections')->max('updated_at');
        
        $lagMinutes = Carbon::parse($lastEventTime)
            ->diffInMinutes(Carbon::parse($lastProjectionTime));
        
        if ($lagMinutes > 10) {
            $this->error("Projection lag detected: {$lagMinutes} minutes");
        }
    }
}
```

## Testing and Validation

### Projector Testing

```php
// tests/Unit/Projectors/TeamHierarchyProjectorTest.php
class TeamHierarchyProjectorTest extends TestCase
{
    use RefreshDatabase;
    
    private TeamHierarchyProjector $projector;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->projector = new TeamHierarchyProjector();
    }
    
    public function test_team_creation_updates_hierarchy()
    {
        $event = new TeamCreated(
            team_id: 'team-123',
            name: 'Development Team',
            parent_team_id: null,
            created_by: 'user-123',
            team_settings: [],
            created_at: now()
        );
        
        $this->projector->onTeamCreated($event);
        
        // Check team projection
        $this->assertDatabaseHas('team_projections', [
            'id' => 'team-123',
            'name' => 'Development Team',
            'parent_id' => null,
        ]);
        
        // Check hierarchy projection (self-reference)
        $this->assertDatabaseHas('team_hierarchy_projections', [
            'ancestor_id' => 'team-123',
            'descendant_id' => 'team-123',
            'depth' => 0,
        ]);
    }
    
    public function test_team_hierarchy_change_updates_closure_table()
    {
        // Create parent team
        $this->createTeamProjection('parent-123', 'Parent Team');
        $this->createHierarchyProjection('parent-123', 'parent-123', 0);
        
        // Create child team
        $this->createTeamProjection('child-123', 'Child Team');
        $this->createHierarchyProjection('child-123', 'child-123', 0);
        
        $event = new TeamHierarchyChanged(
            team_id: 'child-123',
            old_parent_id: null,
            new_parent_id: 'parent-123',
            changed_by: 'user-123',
            hierarchy_impact: [],
            changed_at: now()
        );
        
        $this->projector->onTeamHierarchyChanged($event);
        
        // Check new hierarchy relationship
        $this->assertDatabaseHas('team_hierarchy_projections', [
            'ancestor_id' => 'parent-123',
            'descendant_id' => 'child-123',
            'depth' => 1,
        ]);
    }
    
    private function createTeamProjection(string $id, string $name): void
    {
        DB::table('team_projections')->insert([
            'id' => $id,
            'name' => $name,
            'parent_id' => null,
            'state' => 'active',
            'settings' => '{}',
            'member_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    private function createHierarchyProjection(string $ancestorId, string $descendantId, int $depth): void
    {
        DB::table('team_hierarchy_projections')->insert([
            'ancestor_id' => $ancestorId,
            'descendant_id' => $descendantId,
            'depth' => $depth,
            'descendant_name' => "Team {$descendantId}",
        ]);
    }
}
```

### Reactor Testing

```php
// tests/Unit/Reactors/EmailNotificationReactorTest.php
class EmailNotificationReactorTest extends TestCase
{
    use RefreshDatabase;
    
    private EmailNotificationReactor $reactor;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->reactor = new EmailNotificationReactor();
        Mail::fake();
    }
    
    public function test_user_registration_sends_welcome_email()
    {
        // Create user projection
        DB::table('user_projections')->insert([
            'id' => 'user-123',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'user_type' => 'Standard',
            'state' => 'pending',
            'profile_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $event = new UserRegistrationInitiated(
            user_id: 'user-123',
            email: 'test@example.com',
            user_type: 'Standard',
            registration_data: ['name' => 'Test User'],
            initiated_at: now()
        );
        
        $this->reactor->onUserRegistrationInitiated($event);
        
        Mail::assertQueued(WelcomeEmail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
        
        Mail::assertQueued(ActivationEmail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }
    
    public function test_guest_registration_does_not_send_activation_email()
    {
        DB::table('user_projections')->insert([
            'id' => 'user-123',
            'email' => 'guest@example.com',
            'name' => 'Guest User',
            'user_type' => 'Guest',
            'state' => 'pending',
            'profile_data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $event = new UserRegistrationInitiated(
            user_id: 'user-123',
            email: 'guest@example.com',
            user_type: 'Guest',
            registration_data: ['name' => 'Guest User'],
            initiated_at: now()
        );
        
        $this->reactor->onUserRegistrationInitiated($event);
        
        Mail::assertQueued(WelcomeEmail::class);
        Mail::assertNotQueued(ActivationEmail::class);
    }
}
```

### Integration Testing

```php
// tests/Feature/ProjectorReactorIntegrationTest.php
class ProjectorReactorIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_user_lifecycle_updates_projections_and_triggers_reactions()
    {
        Mail::fake();
        
        // Dispatch user registration event
        $event = new UserRegistrationInitiated(
            user_id: 'user-123',
            email: 'test@example.com',
            user_type: 'Standard',
            registration_data: ['name' => 'Test User'],
            initiated_at: now()
        );
        
        event($event);
        
        // Check projection was updated
        $this->assertDatabaseHas('user_projections', [
            'id' => 'user-123',
            'email' => 'test@example.com',
            'state' => 'pending',
        ]);
        
        // Check audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user_registration_initiated',
            'user_id' => 'user-123',
        ]);
        
        // Check emails were queued
        Mail::assertQueued(WelcomeEmail::class);
        Mail::assertQueued(ActivationEmail::class);
        
        // Dispatch activation event
        $activationEvent = new UserActivated(
            user_id: 'user-123',
            activated_by: 'admin-123',
            activation_method: 'admin',
            activated_at: now()
        );
        
        event($activationEvent);
        
        // Check projection was updated
        $this->assertDatabaseHas('user_projections', [
            'id' => 'user-123',
            'state' => 'active',
        ]);
        
        // Check activation email was queued
        Mail::assertQueued(AccountActivatedEmail::class);
    }
}
```

## Performance Considerations

### Projection Optimization

```php
// Optimized Batch Projection Updates
class BatchProjectorUpdater
{
    private array $updateBatch = [];
    private int $batchSize;
    
    public function __construct(int $batchSize = 100)
    {
        $this->batchSize = $batchSize;
    }
    
    public function addUpdate(string $table, array $data, array $conditions): void
    {
        $this->updateBatch[] = [
            'table' => $table,
            'data' => $data,
            'conditions' => $conditions,
        ];
        
        if (count($this->updateBatch) >= $this->batchSize) {
            $this->processBatch();
        }
    }
    
    public function processBatch(): void
    {
        if (empty($this->updateBatch)) {
            return;
        }
        
        DB::transaction(function () {
            foreach ($this->updateBatch as $update) {
                DB::table($update['table'])
                    ->updateOrInsert($update['conditions'], $update['data']);
            }
        });
        
        $this->updateBatch = [];
    }
    
    public function __destruct()
    {
        $this->processBatch();
    }
}
```

### Reactor Performance

```php
// Asynchronous Reactor Processing
class AsyncReactorProcessor
{
    public function processEvent(ShouldBeStored $event, array $reactors): void
    {
        foreach ($reactors as $reactorClass) {
            if ($this->shouldProcessAsync($reactorClass)) {
                dispatch(new ProcessReactorJob($reactorClass, $event))
                    ->onQueue($this->getQueueForReactor($reactorClass));
            } else {
                $reactor = app($reactorClass);
                $reactor->handle($event);
            }
        }
    }
    
    private function shouldProcessAsync(string $reactorClass): bool
    {
        $asyncReactors = [
            EmailNotificationReactor::class,
            AnalyticsReactor::class,
        ];
        
        return in_array($reactorClass, $asyncReactors);
    }
    
    private function getQueueForReactor(string $reactorClass): string
    {
        return match ($reactorClass) {
            EmailNotificationReactor::class => 'notifications',
            AnalyticsReactor::class => 'analytics',
            GdprComplianceReactor::class => 'gdpr',
            default => 'default'
        };
    }
}
```

## Security Considerations

### Projection Data Security

```php
// Secure Projection Updates
class SecureProjector extends BaseProjector
{
    protected function updateProjection(string $table, array $data, array $conditions): void
    {
        // Sanitize data before updating
        $sanitizedData = $this->sanitizeData($data);
        
        // Validate conditions to prevent injection
        $validatedConditions = $this->validateConditions($conditions);
        
        parent::updateProjection($table, $sanitizedData, $validatedConditions);
    }
    
    private function sanitizeData(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return strip_tags($value);
            }
            return $value;
        }, $data);
    }
    
    private function validateConditions(array $conditions): array
    {
        foreach ($conditions as $key => $value) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("Invalid condition key: {$key}");
            }
        }
        
        return $conditions;
    }
}
```

### Reactor Security

```php
// Secure Reactor Processing
class SecureReactor extends BaseReactor
{
    protected function validateEventData(ShouldBeStored $event): void
    {
        $eventData = $event->toArray();
        
        // Validate required fields
        $this->validateRequiredFields($eventData);
        
        // Sanitize string fields
        $this->sanitizeStringFields($eventData);
        
        // Validate data types
        $this->validateDataTypes($eventData);
    }
    
    private function validateRequiredFields(array $data): void
    {
        $requiredFields = $this->getRequiredFields();
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Required field missing: {$field}");
            }
        }
    }
    
    abstract protected function getRequiredFields(): array;
}
```

## Troubleshooting Guide

### Common Issues and Solutions

**Issue: Projection Lag**
```php
// Problem: Projections are not updating in real-time
// Solution: Monitor and optimize projection performance

class ProjectionLagMonitor
{
    public function checkLag(): array
    {
        $lastEventTime = DB::table('stored_events')->max('created_at');
        $projectionTimes = [
            'users' => DB::table('user_projections')->max('updated_at'),
            'teams' => DB::table('team_projections')->max('updated_at'),
        ];
        
        $lags = [];
        foreach ($projectionTimes as $projection => $time) {
            $lagSeconds = Carbon::parse($lastEventTime)
                ->diffInSeconds(Carbon::parse($time));
            $lags[$projection] = $lagSeconds;
        }
        
        return $lags;
    }
}
```

**Issue: Failed Reactor Processing**
```php
// Problem: Reactors failing silently
// Solution: Implement comprehensive error handling

class FailedReactorHandler
{
    public function handleFailedReactor(string $reactorClass, ShouldBeStored $event, \Throwable $exception): void
    {
        // Log the failure
        Log::error('Reactor processing failed', [
            'reactor' => $reactorClass,
            'event' => get_class($event),
            'exception' => $exception->getMessage(),
        ]);
        
        // Store for retry
        DB::table('failed_reactor_events')->insert([
            'id' => Str::uuid(),
            'reactor_class' => $reactorClass,
            'event_class' => get_class($event),
            'event_data' => json_encode($event->toArray()),
            'exception' => $exception->getMessage(),
            'failed_at' => now(),
            'retry_count' => 0,
        ]);
        
        // Notify administrators for critical reactors
        if ($this->isCriticalReactor($reactorClass)) {
            $this->notifyAdministrators($reactorClass, $event, $exception);
        }
    }
}
```

**Issue: Memory Usage During Event Replay**
```php
// Problem: High memory usage during projection rebuilds
// Solution: Use chunked processing

class MemoryEfficientProjectorReplay
{
    public function replayEvents(string $projectorClass, int $chunkSize = 1000): void
    {
        $projector = app($projectorClass);
        $projector->onStartingEventReplay();
        
        StoredEvent::orderBy('created_at')
            ->chunk($chunkSize, function ($events) use ($projector) {
                foreach ($events as $storedEvent) {
                    $event = $storedEvent->toEvent();
                    $projector->handle($event);
                }
                
                // Force garbage collection
                gc_collect_cycles();
            });
        
        $projector->onFinishedEventReplay();
    }
}
```

## References and Further Reading

### Event-Sourcing and CQRS
- [Event Sourcing Pattern - Martin Fowler](https://martinfowler.com/eaaDev/EventSourcing.html)
- [CQRS Journey - Microsoft](https://docs.microsoft.com/en-us/previous-versions/msp-n-p/jj554200(v=pandp.10))
- [Projections in Event Sourcing](https://eventstore.com/blog/projections-1-theory)

### Laravel Event Sourcing
- [Spatie Laravel Event Sourcing](https://spatie.be/docs/laravel-event-sourcing)
- [Laravel Queues Documentation](https://laravel.com/docs/queues)
- [Laravel Notifications](https://laravel.com/docs/notifications)

### Performance and Monitoring
- [Database Performance Optimization](https://use-the-index-luke.com/)
- [Laravel Performance Best Practices](https://laravel.com/docs/optimization)
- [Monitoring Event-Sourced Systems](https://www.eventstore.com/blog/monitoring-event-store)

### Security and Compliance
- [GDPR Compliance Guide](https://gdpr.eu/compliance/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Data Protection in Event-Sourced Systems](https://blog.eventstore.com/gdpr-and-event-sourcing)
