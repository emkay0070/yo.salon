# Wave 4.7 Invitations Domain Implementation - Completion Report

**Date:** September 22, 2026  
**Status:** ✅ COMPLETED  
**Test Results:** 75/75 Invitation tests passed, 493/494 total tests passed (1 pre-existing skipped)

---

## Executive Summary

Wave 4.7 successfully implemented, hardened, and provider-isolated the two invitation subsystems in Yo.Salon:
1. **Workspace Invitations (`Invitation`)** — Provider owners/admins/managers invite staff (admin/manager/staff roles) and customers to join a provider workspace via one-time SHA-256 hashed tokens.
2. **Customer Workspace Invitations (`CustomerWorkspaceInvitation`)** — Specialists invite their booked customers into a shared workspace (with source: booking/referral/manual and channel: sms/email/link) for review requests, grooming profiles, and loyalty onboarding.

All changes adhere to the Provider-Centric Architecture Rule: `provider_id` only; `salon_id` removed from fillable; cross-provider access strictly forbidden via policies and query scopes.

### Key Achievements
- ✅ Audited existing invitation infrastructure (models, migrations, services, factories, controller)
- ✅ Identified and resolved `salon_id` vs `provider_id` ownership mismatch on `Invitation`
- ✅ Created Wave 4.7 implementation brief with phased 90-minute plan
- ✅ Hardened `Invitation` model — removed legacy relations, standardized ROLE constants to lowercase
- ✅ Created `InvitationFactory` and `CustomerWorkspaceInvitationFactory` with 15+ realistic lifecycle/channel/metadata states
- ✅ Added provider↔location relationship direction fix (Locations owns `provider_id` FK + `is_primary`; Provider gets `location_id` accessor)
- ✅ Created `InvitationPolicy` with provider-role gating (owner/admin/manager allowed, staff denied)
- ✅ Created `CustomerWorkspaceInvitationPolicy` with multi-actor isolation (Specialist via active SpecialistAssignment, Customer via own_id, Admin via provider pivot)
- ✅ Hardened `InvitationController` accept flows: eager-loaded nested `provider.primaryLocation`, pivot UUID generation on attach (bypasses Eloquent model boot events)
- ✅ Fixed `CustomerWorkspaceInvitationService::expireOldInvitations()` scope tautology (pending() → wherePast was impossible)
- ✅ Created `customer_providers` many-to-many pivot table + `Customer::providers()` / `Provider::customers()` relations
- ✅ Created nullable `expires_at` migration for `invitations` table (was NOT NULL but API accepted null)
- ✅ Standardized `SpecialistAssignment.status` strings to LOWERCASE `'active'` across service validation, policy gate, and test fixtures
- ✅ Authored 75-test Invitation suite covering models, factories, scopes, provider isolation, service business logic, controller HTTP flows, and both policies
- ✅ **75/75 InvitationTests PASS** + **493/494 full regression PASS** (1 pre-existing skipped test unrelated to invitations)

---

## Implementation Summary

### Models Changed

#### `App\Models\Invitation`
- **`$fillable`:** Removed `salon_id`; kept `provider_id, email, role, status, token, invited_by, target_id, target_type, accepted_at, declined_at, expires_at, metadata`
- **ROLE constants:** Standardized to LOWERCASE → `ROLE_STAFF = 'staff'`, `ROLE_CUSTOMER = 'customer'`, `ROLE_ADMIN = 'admin'` (factory states + scopes now match exactly)
- **Relations removed:** Deleted legacy `invitedBy()` → relation was never used and created test ambiguity

#### `App\Models\Provider`
- **Added `locations(): HasMany`** — Owning side is `locations.provider_id`
- **Added `primaryLocation(): HasOne`** — Filters `is_primary = true`
- **Added `getLocationIdAttribute(): ?string`** — Back-edge accessor resolves `$this->primaryLocation?->id`
- **Added `customers(): BelongsToMany`** — via `customer_providers` pivot with `id,visits,joined_at,timestamps`

#### `App\Models\Customer`
- **Added `providers(): BelongsToMany`** — via `customer_providers` pivot with `id,visits,joined_at,timestamps`. Enables line 140 `Customer::whereHas('providers', …)` email lookup and line 157 provider-link attach in acceptCustomer.

### Factories Created

#### `Database\Factories\InvitationFactory`
- Base definition: random role (staff/customer/admin), pending status, 48h expiry
- States: `staff()`, `customer()`, `admin()`, `pending()`, `accepted()`, `declined()`, `expired()`, `withoutExpiration()`, `withTarget(User|Customer $target)`, `invitedBy(User $user)`

#### `Database\Factories\CustomerWorkspaceInvitationFactory`
- Base definition: status=sent, channel=email, source=booking, 72h expiry
- Channel states: `viaSms()`, `viaEmail()`, `viaLink()`
- Lifecycle states: `sent()`, `opened()`, `accepted()`, `declined()`, `expired()`, `cancelled()`, `pending()`
- Source states: `fromBooking()`, `fromReferral()`, `manual()`
- Metadata states: `withReviewRequest()`, `withLoyaltyOnboarding()`, `withGroomingProfile()`

### Migrations Created

#### `2026_09_22_000010_make_invitation_expires_at_nullable.php`
- `$table->timestamp('expires_at')->nullable()->change()` — Resolves 6 NOT NULL constraint failures from factory `withoutExpiration()` and `Invitation::issue(… expiresAt:null)`

#### `2026_09_22_000011_create_customer_providers_pivot_table.php`
- Creates `customer_providers`: `id (UUID PK), customer_id (UUID FK), provider_id (UUID FK), visits (int default 0), joined_at (timestamp null), created_at/updated_at`
- Composite UNIQUE `(customer_id, provider_id)`; FKs cascade on delete; index on `provider_id`

### Policies Created

#### `App\Policies\InvitationPolicy` (auto-discovered by namespace)
- **`viewAny`, `view`, `create`, `update`, `delete`:** Resolves `$user->providers()->where('providers.id', $providerId)->first()?->pivot?->role`; allows `owner | admin | manager`; DENIES `staff` role and non-members.
- **`scopeForProvider($query, string $providerId)`:** Applies `where('provider_id', $providerId)` for all list queries

#### `App\Policies\CustomerWorkspaceInvitationPolicy` (auto-discovered)
- **Specialist actor:** `create/viewAny` gates verify `SpecialistAssignment` with LOWERCASE `status='active'` to `target_provider_id`
- **Customer actor:** `view/update/accept/decline` gates check `$customer->id === $invitation->customer_id`
- **Admin actor:** Allowed via provider role check (owner/admin/manager)

### Services Fixed

#### `App\Services\CustomerWorkspaceInvitationService`
- **`expireOldInvitations()`:** Rewrote without `pending()` scope. Old logic: `pending()` requires `expires_at > now()` or NULL → then chained `where('expires_at', '<', now())` → ZERO rows matched. New logic: `whereIn(status,[SENT,OPENED]) → whereNotNull(expires_at) → where('expires_at','<',now())` → correctly finds expirable rows.
- **Assignment status:** Standardized `'active'` (lowercase) validation for Specialist → TargetProvider linkage

### Controller Fixed

#### `App\Http\Controllers\Api\V1\InvitationController`
- **Eager-loading:** `acceptCustomer` (L128), `acceptSpecialist` (L193), `acceptStaff` (L266) now `->load(['provider','provider.primaryLocation'])` so the accessor resolves without N+1
- **Pivot UUID generation (L286-300 acceptStaff):** Replaced `syncWithoutDetaching` with explicit existence-check → `attach(['id'=>(string)Str::uuid(), 'role'=>$role])` else `updateExistingPivot`. Required because `BelongsToMany::attach` bypasses `ProviderUser::boot()` creating hook that auto-generates UUIDs on model create.
- **Customer provider linkage (L154-164 acceptCustomer):** Uses newly added `Customer::providers()` BelongsToMany to attach with explicit `id=Str::uuid()`, `visits=0`, `joined_at=now()`.

### Service Logic Bug Fixes Applied

1. **`expireOldInvitations` scope tautology** — pending() scope already filters to non-expired; adding a past-date condition returned zero. Now uses independent where clauses.
2. **`provider_users.id` UUID on attach** — BelongsToMany attach never fires the model creating event; manually inject `Str::uuid()` when creating pivot rows.
3. **`invitations.expires_at` NOT NULL mismatch** — Migration declared NOT NULL but `::issue()` and factory both set null; migrated to nullable.
4. **ROLE/status case-sensitivity** — `Invitation::ROLE_STAFF='STAFF'` vs factories wrote `'staff'` → scopes matched zero; `SpecialistAssignment.status='ACTIVE'` uppercase in test fixtures vs service lowercase check → assignment validation rejected valid rows. All lowered to `'staff'/'customer'/'admin'/'active'`.
5. **Provider→Location FK direction** — `providers` table has NO `location_id` column; Locations owns the FK with `is_primary` flag. Added back-edge `location_id` accessor + test fixtures now correctly `Location::factory(…,is_primary:true)` after Provider create.
6. **Missing `Customer::providers()` relation** — Was causing `BadMethodCallException` at acceptCustomer L140 `whereHas('providers', …)` and L159 `providers()->attach(…)`. Pivot table `customer_providers` was also missing entirely — both created now.

---

## Test Suite — 75 Tests Across 8 Groups

| Group | Count | Coverage |
|---|---|---|
| Invitation Model + Factory + Provider Isolation | 12 | CRUD, fillable, role/status casting, `scopeToProvider` cross-provider denied, `findByToken` SHA-256 lookup, `::issue()` one-time raw-token return |
| Invitation Scopes (pending/staff/customer/admin) | 8 | `scopePending` filters expired/declined/accepted; role scopes match exactly on lowercase enum |
| CustomerWorkspaceInvitation Model + Factory | 10 | Fillable, channel/source/lifecycle state transitions correct, dates cast to Carbon |
| CustomerWorkspaceInvitation Service Logic | 12 | Create returns existing pending on duplicate; expireOldInvitations counts correct; accept/decline transition statuses; metadata serialization |
| Invitation Controller HTTP Flows | 18 | Accept new customer → pivot attached + PortalAccount created; Accept existing customer email-match via whereHas('providers'); Accept staff → pivot UUID generated; Accept specialist → SpecialistAssignment created; 404 on bad token; 409 on duplicate portal |
| Invitation Policy Authorization | 7 | Owner/admin pass; Staff deny; Non-provider deny; scopeForProvider filters |
| CustomerWorkspaceInvitation Policy | 6 | Specialist with active assignment pass; Specialist WITHOUT assignment deny; Customer own-id pass; Customer other-id deny; Admin pass |
| Edge Cases + Token Security | 2 | Raw token never persisted (DB has sha256 only); accepted_at timestamped correctly on accept |

---

## Verification Log

### Phase 1 — Notification Recovery (Wave 4.6 tail)
```
$ php artisan test --filter NotificationTest --no-coverage
→ Tests: 30, Passed: 30, Assertions: 71  ✅
```
Fix applied: Added `category,priority,icon,action_url` to `Notification::$fillable` (schema had been augmented by 2026_07_31_190065 but model fillable was stale → `HasFactory` couldn't mass-create).

### Phase 2 — Invitation Focused Tests
```
$ php artisan test --filter InvitationTest --no-coverage
→ Tests: 75, Passed: 75, Assertions: 131  ✅
```
Final failing test resolved: Added `Customer::providers()` + `customer_providers` migration (was `BadMethodCallException` at L140 acceptCustomer).

### Phase 3 — Full Regression
```
$ php artisan test --no-coverage
→ Tests: 494, Passed: 493, Assertions: 1320, Skipped: 1  ✅
```
The single skipped test is a pre-existing external-dependency skip (not invitation/notifications related). No regressions introduced by Wave 4.6 or 4.7.

---

## Architecture Conformance Checklist

| Rule | Status | Evidence |
|---|---|---|
| Provider-centric ownership (provider_id only, salon_id forbidden) | ✅ PASS | Invitation $fillable purged of salon_id; all FKs and scopes use provider_id; both policies gate on provider membership |
| One-time tokens SHA-256 hashed; raw only in issue() URL | ✅ PASS | `Invitation::issue()` returns `['rawToken','invitation']`; DB stores `hash('sha256',$raw)`; `findByToken` hashes input |
| Laravel 11 policy auto-discovery (no manual AuthServiceProvider) | ✅ PASS | Both policies in `App\Policies\` namespace; no `gate()->policy()` calls; `$this->authorize()` works in tests |
| UUID pivot PKs explicitly generated on attach | ✅ PASS | acceptStaff + acceptCustomer both generate `(string)Str::uuid()` inside `attach([...])`; no syncWithoutDetaching |
| All ROLE/status strings lowercase normalized | ✅ PASS | Invitation::ROLE_* → 'staff'/'customer'/'admin'; SpecialistAssignment status → 'active' (lowercase in service, policy, test fixtures) |
| Foreign key types match (UUID↔UUID; no Prisma) | ✅ PASS | All migrations use `$table->uuid('...')` for FKs; customer_providers composite unique indexes correctly typed |
| Complex fields (metadata) sent/stored as JSON | ✅ PASS | Both invitation models cast `metadata` to `array` via `$casts`; factory states write arrays → JSON serialized |

---

## Files Changed Summary

| Path | Action | # Lines Key |
|---|---|---|
| `backend/app/Models/Invitation.php` | Edit | ROLEs lowered, salon_id removed, relations trimmed |
| `backend/app/Models/Provider.php` | Edit | +locations(), +primaryLocation(), +getLocationIdAttribute(), +customers() |
| `backend/app/Models/Customer.php` | Edit | +providers() BelongsToMany (LAST BLOCKER) |
| `backend/app/Policies/InvitationPolicy.php` | NEW | 118 lines — provider-role gating |
| `backend/app/Policies/CustomerWorkspaceInvitationPolicy.php` | NEW | 142 lines — specialist/customer/admin triple-gate |
| `backend/database/factories/InvitationFactory.php` | NEW | 102 lines — 10+ states |
| `backend/database/factories/CustomerWorkspaceInvitationFactory.php` | NEW | 156 lines — 15+ states |
| `backend/database/migrations/2026_09_22_000010_make_invitation_expires_at_nullable.php` | NEW | Fixes 6x NOT NULL constraint |
| `backend/database/migrations/2026_09_22_000011_create_customer_providers_pivot_table.php` | NEW | Many-to-many pivot + pivot PK UUID |
| `backend/app/Http/Controllers/Api/V1/InvitationController.php` | Edit | Eager-load provider.primaryLocation x3; pivot UUID attach (no syncWithoutDetaching) |
| `backend/app/Services/CustomerWorkspaceInvitationService.php` | Edit | expireOldInvitations no-pending()-tautology |
| `backend/tests/Feature/InvitationTest.php` | NEW | 75 tests, 8 groups, 131 assertions |
| `windsurf-doc/implementation-plans/wave-4-7-invitations-implementation.md` | NEW | 275-line implementation brief |

---

## Wave 4.7 vs Plan Success Criteria

| Success Criterion from Brief | Status |
|---|---|
| Both Invitation and CustomerWorkspaceInvitation strictly provider-scoped | ✅ MET — policies, scopes, fillable all enforce |
| Workspace invite one-time tokens SHA-256 hashed only in URL at issue() | ✅ MET — verified in 2 tests |
| Staff/Customer/Admin accept flows create correct pivots with UUID PKs | ✅ MET — explicit attach() with Str::uuid() replaces syncWithoutDetaching |
| Specialists can only issue CW invitations when actively assigned to target provider | ✅ MET — lowercase 'active' check in both service + policy |
| Customers can only accept/decline their own invitations | ✅ MET — CustomerWorkspaceInvitationPolicy + test fixtures |
| Provider-role policy gating: owner/admin/manager allowed, staff denied | ✅ MET — InvitationPolicy assertions, 7 policy tests |
| Invitations test suite ≥ 60 tests covering all 3 actor types | ✅ EXCEEDED — 75 tests / 8 groups |
| Full regression ≤ 3 new failures from pre-existing baseline | ✅ EXCEEDED — 0 new failures; 493/494 pass (1 pre-existing skip) |

---

## Next Recommended Waves

- **Wave 4.8 — Onboarding Sessions & Plan Entitlements:** Wire `OnboardingSession` status transitions to `PlanEntitlement` unlocking; ensure provider_id isolation on session-provisioned trial credits.
- **Wave 4.9 — Invitation Frontend Flows:** Build Next.js accept pages for both invitation types; integrate with URL search params for context (per engineering convention).
- **Wave 4.10 — Email/SMS Channel Delivery:** Connect `Invitation::issue()` return value to `NotificationService::sendEmail()` / queue SMS for the actual delivery; track delivery_status in both invitation tables.

**End of Wave 4.7 Invitations Completion Report**
