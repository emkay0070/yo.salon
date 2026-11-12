# Wave 4.7 - Invitations Implementation Brief

## Executive Summary

Wave 4.7 completes the Customer Experience domain by consolidating and securing the invitation infrastructure. The system has **two invitation flows** that must be aligned with the Provider → Location architecture:

1. **Invitation** - Staff/Specialist/Customer workspace invitations (provider → user)
2. **CustomerWorkspaceInvitation** - Specialist-to-customer workspace invitations (specialist → customer)

## Audit Findings

### Existing Infrastructure

#### 1. Invitation (Staff/Specialist/Customer Workspace Invitations)
- **Model**: `App\Models\Invitation`
- **Migration**: `2026_07_31_1900313_create_invitations_table.php`
- **Controller**: `InvitationController`
- **Status**: Partially aligned, needs fixes

**Issues Identified:**
- Model fillable has `salon_id` but migration uses `provider_id` - **MISMATCH**
- Model has `salon()` relationship that should be removed
- Model references `invited_by` in fillable but not in migration
- No factory exists
- No policy exists
- No tests exist

**What's Correct:**
- Migration uses `provider_id` with foreign key to providers table
- Controller uses `provider_id` correctly
- Token security (SHA-256 hashing)
- Role-based invite flows (customer, staff, specialist, manager, receptionist)

#### 2. CustomerWorkspaceInvitation (Specialist → Customer Invitations)
- **Model**: `App\Models\CustomerWorkspaceInvitation`
- **Migration**: `2026_08_17_000000_create_customer_workspace_invitations_table.php`
- **Service**: `CustomerWorkspaceInvitationService`
- **Status**: **FULLY ALIGNED** - no changes needed

**What's Correct:**
- Uses `target_provider_id` and `source_provider_id`
- Service validates specialist assignment to target provider
- Provider-scoped queries via `scopeToProvider`
- Proper lifecycle management (sent, opened, accepted, declined, expired)
- No factory, policy, or tests - need to add

## Implementation Plan

### Phase 1: Fix Invitation Model (Critical)

**File**: `backend/app/Models/Invitation.php`

**Changes:**
1. Remove `salon_id` from `$fillable` array
2. Remove `salon()` relationship method
3. Remove `invited_by` from `$fillable` (not in migration)
4. Keep `provider()` relationship (already correct)
5. Add `HasFactory` trait (already present)

### Phase 2: Create InvitationFactory

**File**: `backend/database/factories/InvitationFactory.php`

**States to Implement:**
- `pending()` - pending invitation with future expiration
- `accepted()` - accepted invitation
- `expired()` - expired invitation
- `staff()` - staff role invitation
- `customer()` - customer role invitation
- `specialist()` - specialist role invitation
- `manager()` - manager role invitation
- `receptionist()` - receptionist role invitation
- `withTarget()` - invitation with target_id
- `withoutExpiration()` - invitation without expiration

### Phase 3: Create CustomerWorkspaceInvitationFactory

**File**: `backend/database/factories/CustomerWorkspaceInvitationFactory.php`

**States to Implement:**
- `sent()` - sent invitation
- `opened()` - opened invitation
- `accepted()` - accepted invitation
- `declined()` - declined invitation
- `expired()` - expired invitation
- `specialistDirect()` - specialist direct invite channel
- `platformSuggestion()` - platform suggestion channel
- `referral()` - referral channel
- `directLink()` - direct link channel
- `withExpiration()` - invitation with expiration
- `withoutExpiration()` - invitation without expiration

### Phase 4: Create InvitationPolicy

**File**: `backend/app/Policies/InvitationPolicy.php`

**Provider Isolation Rules:**
- `view()` - only users with access to the provider
- `create()` - only provider owners/managers
- `update()` - only provider owners/managers
- `delete()` - only provider owners/managers
- `accept()` - public endpoint (no auth required for token validation)

### Phase 5: Create CustomerWorkspaceInvitationPolicy

**File**: `backend/app/Policies/CustomerWorkspaceInvitationPolicy.php`

**Provider Isolation Rules:**
- `view()` - only specialists with assignment to target provider
- `create()` - only specialists with active assignment to target provider
- `update()` - only the inviting specialist
- `delete()` - only the inviting specialist or provider owner
- `accept()` - customer can accept their own invitations

### Phase 6: Create Comprehensive Test Suite

**File**: `backend/tests/Feature/InvitationTest.php`

**Test Coverage:**

#### Invitation Model Tests
- Invitation belongs to provider
- Invitation belongs to inviter (if added back to migration)
- Invitation can be issued with token
- Invitation can be found by token
- Invitation pending scope
- Invitation staff scope
- Invitation customer scope
- Invitation is pending check
- Invitation is accepted check
- Invitation is expired check
- Invitation is valid check
- Public join URL generation for different roles

#### Invitation Factory Tests
- Factory pending state
- Factory accepted state
- Factory expired state
- Factory staff role
- Factory customer role
- Factory specialist role
- Factory manager role
- Factory receptionist role
- Factory with target
- Factory without expiration

#### Invitation Controller Tests
- List invitations for provider
- Create invitation as provider owner
- Create invitation as provider manager
- Validate invitation token
- Accept customer invitation (new customer)
- Accept customer invitation (existing customer)
- Accept specialist invitation (new specialist)
- Accept specialist invitation (existing specialist)
- Accept staff invitation (new user)
- Accept staff invitation (existing user)
- Cross-provider invitation rejection
- Invalid token handling
- Expired invitation handling
- Already accepted invitation handling

#### Invitation Policy Tests
- Provider owner can view invitations
- Provider manager can view invitations
- Provider staff cannot view invitations
- Cross-provider access rejected
- Provider owner can create invitations
- Provider manager can create invitations
- Provider staff cannot create invitations

#### CustomerWorkspaceInvitation Model Tests
- Invitation belongs to customer
- Invitation belongs to specialist
- Invitation belongs to target provider
- Invitation belongs to source provider
- Invitation is expired check
- Invitation can be accepted check
- Mark as opened
- Mark as accepted
- Mark as declined
- Mark as expired
- Pending scope
- To provider scope
- From specialist scope

#### CustomerWorkspaceInvitation Factory Tests
- Factory sent state
- Factory opened state
- Factory accepted state
- Factory declined state
- Factory expired state
- Factory specialist direct channel
- Factory platform suggestion channel
- Factory referral channel
- Factory direct link channel
- Factory with expiration
- Factory without expiration

#### CustomerWorkspaceInvitationService Tests
- Create invitation validates specialist assignment
- Create invitation returns existing pending invitation
- Mark as opened
- Accept invitation creates relationship
- Accept invitation idempotent
- Decline invitation
- Get pending invitations for customer
- Get invitations by specialist
- Get invitation statistics
- Expire old invitations
- Cross-provider specialist rejection

#### CustomerWorkspaceInvitationPolicy Tests
- Specialist can view own invitations
- Specialist cannot view other specialist invitations
- Specialist with assignment can create invitation
- Specialist without assignment cannot create invitation
- Cross-provider specialist rejection
- Customer can accept own invitation
- Customer cannot accept other customer invitation

### Phase 7: Register Policies

**File**: `backend/app/Providers/AuthServiceProvider.php`

Add to `$policies` array:
```php
Invitation::class => InvitationPolicy::class,
CustomerWorkspaceInvitation::class => CustomerWorkspaceInvitationPolicy::class,
```

## Success Criteria

- [ ] Invitation model uses only provider_id (no salon_id)
- [ ] Invitation factory with all role and state states
- [ ] CustomerWorkspaceInvitation factory with all channel and state states
- [ ] InvitationPolicy with provider isolation
- [ ] CustomerWorkspaceInvitationPolicy with provider isolation
- [ ] Comprehensive test suite (50+ tests)
- [ ] All focused invitation tests passing
- [ ] Full regression suite passing
- [ ] Wave 4.7 completion report generated

## Risk Assessment

**Low Risk:**
- Model fixes are straightforward (removing salon_id references)
- CustomerWorkspaceInvitation is already aligned

**Medium Risk:**
- InvitationController has complex accept flows for different roles
- Need to ensure token security is maintained

**Mitigation:**
- Comprehensive test coverage for all accept flows
- Verify token hashing is not broken
- Test cross-provider isolation thoroughly

## Dependencies

None - this is a standalone domain completion.

## Timeline Estimate

- Phase 1: Fix model - 5 minutes
- Phase 2: InvitationFactory - 10 minutes
- Phase 3: CustomerWorkspaceInvitationFactory - 10 minutes
- Phase 4: InvitationPolicy - 10 minutes
- Phase 5: CustomerWorkspaceInvitationPolicy - 10 minutes
- Phase 6: Test suite - 30 minutes
- Phase 7: Register policies - 2 minutes
- Testing & validation - 15 minutes

**Total: ~90 minutes**
