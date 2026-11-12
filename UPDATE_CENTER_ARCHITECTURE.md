# Update Center Architecture

## Overview

The Update Center provides a controlled, secure mechanism for administrators to review, approve, and apply Yo.Salon application/backend updates. This architecture ensures that no arbitrary backend write access is granted to provider users or normal application administrators.

## Security Principles

1. **No Arbitrary Code Execution**: Provider users and application administrators cannot directly edit backend code or database schemas.
2. **Controlled Authorization**: Updates go through a defined approval workflow with specific permissions.
3. **Audit Trail**: All update actions are logged and traceable.
4. **Rollback Capability**: Failed updates can be safely rolled back.

## Architecture

### Release Model

```
Release Created
    ↓
Release Metadata (version, date, changelog, migration notes)
    ↓
Notification via NotificationCenter
    ↓
Authorized Admin Reviews
    ↓
Approval Decision (Approve/Reject)
    ↓
If Approved → Controlled Update Operation
    ↓
Migration/Deployment Service
    ↓
Health Check
    ↓
Success or Rollback
```

### Release Metadata Structure

```typescript
interface SystemRelease {
  id: string;
  version: string;
  releaseDate: Date;
  type: 'major' | 'minor' | 'patch' | 'hotfix';
  
  // Release Information
  changelog: {
    features: string[];
    bugFixes: string[];
    securityFixes: string[];
  };
  
  // Deployment Information
  requiresDowntime: boolean;
  estimatedDowntimeMinutes?: number;
  requiresUserAction: boolean;
  userActionDescription?: string;
  
  // Database Changes
  databaseChanges: {
    hasMigrations: boolean;
    migrationDescription?: string;
    requiresBackup: boolean;
  };
  
  // Approval Workflow
  status: 'pending' | 'approved' | 'rejected' | 'deployed' | 'rolled_back';
  approvedBy?: string;
  approvedAt?: Date;
  rejectionReason?: string;
  
  // Deployment Status
  deployedAt?: Date;
  deployedBy?: string;
  healthCheckPassed?: boolean;
  rollbackReason?: string;
}
```

### Permission Model

```typescript
// New permissions to be added to the permission system
enum SystemPermission {
  VIEW_SYSTEM_UPDATES = 'view_system_updates',
  APPROVE_SYSTEM_UPDATES = 'approve_system_updates',
  APPLY_SYSTEM_UPDATES = 'apply_system_updates',
  VIEW_UPDATE_HISTORY = 'view_update_history',
}

// Role-based access
const rolePermissions: Record<string, SystemPermission[]> = {
  super_admin: [
    SystemPermission.VIEW_SYSTEM_UPDATES,
    SystemPermission.APPROVE_SYSTEM_UPDATES,
    SystemPermission.APPLY_SYSTEM_UPDATES,
    SystemPermission.VIEW_UPDATE_HISTORY,
  ],
  platform_admin: [
    SystemPermission.VIEW_SYSTEM_UPDATES,
    SystemPermission.APPROVE_SYSTEM_UPDATES,
    SystemPermission.VIEW_UPDATE_HISTORY,
  ],
  provider_admin: [
    SystemPermission.VIEW_SYSTEM_UPDATES,
  ],
};
```

### API Endpoints (Future Implementation)

```
GET  /api/v1/system/releases              - List available releases
GET  /api/v1/system/releases/:id          - Get release details
POST /api/v1/system/releases/:id/approve - Approve a release
POST /api/v1/system/releases/:id/deploy  - Deploy an approved release
POST /api/v1/system/releases/:id/rollback - Rollback a release
GET  /api/v1/system/releases/history       - Get update history
```

### Notification Integration

The existing `NotificationCenter` will be extended to deliver update notifications:

```typescript
interface UpdateNotification {
  type: 'system_update_available';
  title: 'Yo.Salon has a new update available';
  message: string;
  data: {
    releaseId: string;
    version: string;
    requiresAction: boolean;
  };
}
```

### UI Components (Future)

1. **UpdateCenterPage**: Main page for viewing and managing updates
2. **ReleaseCard**: Display release information and approval status
3. **ChangelogViewer**: View detailed changelog
4. **ApprovalDialog**: Confirm approval with risk acknowledgment
5. **DeploymentProgress**: Show deployment status and health checks

## Implementation Phases

### Phase 1: Foundation (Current)
- [x] Architecture documentation
- [ ] Permission model extension
- [ ] Database schema for releases
- [ ] Basic API endpoints structure

### Phase 2: Notification Integration
- [ ] Extend NotificationCenter for update notifications
- [ ] Add notification type for system updates
- [ ] Link notifications to Update Center

### Phase 3: UI Implementation
- [ ] Create UpdateCenter page
- [ ] Implement release list view
- [ ] Implement release detail view
- [ ] Add approval workflow UI

### Phase 4: Deployment Integration
- [ ] Integrate with deployment service
- [ ] Implement health check monitoring
- [ ] Add rollback capability
- [ ] Audit logging

## Important Constraints

1. **No Direct Code Editing**: The Update Center does not provide any interface for editing backend code, database schemas, or configuration files directly.

2. **Pre-defined Releases Only**: Only releases that have been created and validated by the Yo.Salon team can be deployed through this system.

3. **Controlled Deployment**: The actual deployment is performed by a controlled service, not by user-initiated arbitrary commands.

4. **Permission Boundaries**: Provider administrators can only view updates. Only platform/super administrators can approve and deploy.

## Security Considerations

- All update operations require authentication with appropriate permissions
- Approval actions are logged with user, timestamp, and reason
- Deployment operations use a dedicated service account with restricted permissions
- Rollback is automatic if health checks fail
- No user-provided code or scripts are executed during updates

## Future Enhancements

- Staged rollout (canary deployments)
- Scheduled deployments
- Multi-region deployment coordination
- Custom update channels (stable, beta, edge)
- Update rollback to specific version
