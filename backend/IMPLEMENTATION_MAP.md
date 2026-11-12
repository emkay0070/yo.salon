# Yo.Salon Booking Lifecycle Implementation Map

## Current Booking Creation Paths

### 1. Staff/Admin Bookings
- **Entry Point**: `BookingController::store()` (line 115)
- **Orchestrator**: `BookingOrchestrator::reserveAndBook()` (line 44)
- **Service**: `BookingService::createBookingWithAccount()` (line 274)
- **Status Logic**: 
  - External bookings → `reserved` with 10-minute slot lock (line 213-214)
  - Internal walk-ins → `confirmed` immediately (line 216)
- **Payment**: Handled via `BookingOrchestrator`, payment snapshot created in `BookingService::createBookingWithAccount()`

### 2. Customer Portal Bookings (Journey 4)
- **Entry Point**: `BookingController::storeWithAccount()` (line 534)
- **Orchestrator**: `BookingOrchestrator::reserveAndBook()` (line 44)
- **Service**: `BookingService::createBookingWithAccount()` (line 274)
- **Status Logic**: 
  - Deposit required → `pending_payment` (line 326)
  - No deposit → determined by approval policy (line 328)
- **Payment**: `PaymentRulesEngine::generatePaymentInstructions()` (line 318)

### 3. Customer Portal Bookings (Legacy)
- **Entry Point**: `CustomerBookingController::store()` (line 176)
- **Service**: `CustomerBookingService::createBooking()` (line 29)
- **Status Logic**: `determineInitialBookingStatus()` (line 401)
  - `!require_approval || approval_type === 'instant'` → `confirmed`
  - `approval_type === 'manual'` → `pending`
- **Gap**: Deposit policy NOT integrated - no deposit check before setting status

### 4. Booking Orchestrator Direct
- **Entry Point**: `BookingOrchestratorController::reserve()` (line 22)
- **Orchestrator**: `BookingOrchestrator::reserveAndBook()` (line 44)
- **Same flow as #1**

## Current State Machine

### Canonical State Transitions
- **Method**: `BookingService::transitionStatus()` (line 538)
- **Allowed Transitions** (lines 561-573):
  ```
  pending         → confirmed | cancelled
  pending_payment → confirmed | cancelled
  reserved        → pending | pending_payment | confirmed | cancelled
  confirmed       → arrived | in_progress | cancelled | no_show | rescheduled
  arrived         → waiting | in_progress | cancelled | no_show
  waiting         → in_progress | cancelled | no_show
  in_progress     → completed
  completed       → (terminal)
  cancelled       → (terminal)
  no_show         → (terminal)
  rescheduled     → (terminal)
  ```

### Resolution Workflow
- **Method**: `BookingService::resolveBooking()` (line 628)
- **Endpoint**: `BookingController::resolve()` (line 346)
- **Outcomes**: NO_SHOW, CANCELLED, RESCHEDULED, COMPLETED_LATE, CUSTOMER_ARRIVED

### Auto-Approval
- **Method**: `BookingService::processAutoApprovals()` (line 1088)
- **Scheduled**: Every 15 minutes via `routes/console.php` (line 27-29)
- **Logic**: Approves pending bookings after `auto_approve_after_hours` threshold

## Current Payment Flow

### Payment Initialization
- **Service**: `BookingPaymentService::initializeBookingPayment()` (line 43)
- **Expiry**:
  - Manual payments: 60 minutes (line 116)
  - Digital payments: 15 minutes (line 164)
- **Gap**: Expiry values hardcoded, not provider-configurable

### Payment Verification
- **Service**: `BookingPaymentService::verifyBookingPayment()` (line 181)
- **Idempotency**: Checks if already paid (line 203-210)
- **Gap**: No expiry check - accepts late payments without verifying `expires_at`

### Payment Expiry Command
- **Command**: `ExpirePendingPayments` (line 1-46)
- **Logic**: Updates status to `expired` for pending requests past `expires_at`
- **Gap**: NOT scheduled in `routes/console.php` - command exists but never runs

## Current Availability & Concurrency

### Availability Calculation
- **Service**: `AvailabilityEngine::getAvailableSlots()` (line 48)
- **Buffer Handling**: 
  - `buffer_before` and `buffer_after` from Service model (lines 753-755)
  - Added to `totalDuration` in slot generation (line 755)
  - Used in `calculateEndTime()` (line 839)
- **Overlap Check**: Time comparison in `generateTimeSlots()` (lines 781-789)

### Slot Locking
- **Method**: `AvailabilityEngine::lockSlot()` (line 381)
- **Duration**: 10-minute lock (line 389)
- **Implementation**: Creates pending booking with `slot_locked_until`
- **Gap**: Not used by main booking flow - `BookingOrchestrator` uses cache lock instead

### Concurrency Protection
- **Method**: `BookingOrchestrator::reserveAndBook()` (line 181-190)
- **Implementation**: Cache lock for 10 seconds (line 186)
- **Lock Key**: `booking_slot_{$assignment->id}_{$date}_{$time}` or `booking_slot_provider_{$providerId}_{$date}_{$time}`
- **Gap**: Lock is on exact start time only - does NOT protect overlapping intervals with different start times

### Double Booking Check
- **Location**: `BookingOrchestrator::reserveAndBook()` (lines 113-139)
- **Logic**: Checks for existing booking at same date/time
- **Gap**: Checks exact time match only, not interval overlap

## Current Database Schema

### Bookings Table
- **Base Migration**: `2026_07_14_164006_create_bookings_table.php`
- **Key Fields**:
  - `status` - booking status
  - `payment_status` - payment status
  - `payment_strategy` - payment strategy
  - `deposit_required` - boolean
  - `amount_due`, `amount_paid`, `amount_remaining` - payment amounts
  - `payment_expires_at` - payment deadline
  - `payment_snapshot` - payment instruction snapshot
  - `slot_locked_until` - reservation hold expiry
  - `idempotency_key` - idempotency
  - `operational_state` - operational floor state
  - `rescheduled_from_booking_id` - rescheduling relationship
  - `arrived_at`, `waiting_since`, `queue_position` - operational floor tracking
  - `grace_period_started_at`, `grace_period_ends_at` - grace period tracking

### Payment Requests Table
- **Fields**: `status`, `expires_at`, `provider_reference`
- **Statuses**: pending, sent, paid, expired

## Current Authorization

### Provider Context
- **Middleware**: `ResolveProviderContext` (line 18)
- **Logic**: Injects `provider_id` from authenticated user's `currentProvider()` (lines 52-54)
- **Guard**: `BookingController::store()` validates provider_id matches context (lines 153-156)

### Location Context
- **Middleware**: `ResolveLocationContext`
- **Scope**: Provider-wide vs location-specific via `location_scope` attribute

### Gap: No Explicit Approval/Rejection Endpoints
- Status transitions use generic `transitionStatus()` method
- No dedicated owner approval/rejection endpoints with explicit authorization
- No role-based permission checks for approval/rejection actions

## Current Tests

### Booking Tests
- `ConcurrentBookingConflictTest.php` - tests concurrent booking prevention
- `BookingServiceSessionFieldsTest.php` - session field handling
- `BookingRegressionTest.php` - regression tests
- `BookingServicePersistenceTest.php` - persistence tests
- `BookingLocationBoundaryTest.php` - location boundary tests
- `BookingNeedsResolutionApiTest.php` - resolution API tests
- `BookingResolutionTest.php` - resolution workflow tests
- `BookingTimeStateEngineTest.php` - time state engine tests

### Session Tests
- `SessionApiTest.php` - session API tests
- `SessionServiceTest.php` - session service tests
- `SessionAvailabilityIntegrationTest.php` - session availability integration
- `SessionCacheInvalidationTest.php` - cache invalidation tests

### Gap: No Tests For
- Deposit-required booking flow
- Payment expiry processing
- Late payment handling
- Approval/rejection authorization
- Overlapping interval concurrency protection
- Buffer application across all booking paths

## Critical Gaps Identified

### Phase 1 Gaps (Booking Policy & Interval Safety)
1. **Deposit Policy Integration**: `CustomerBookingService::createBooking()` does not check deposit policy
2. **Duration/Buffer Inconsistency**: `BookingOrchestrator::calculateEndTime()` does NOT include buffers (line 236-242)
3. **Concurrency Protection**: Cache lock on exact time only - no protection for overlapping intervals with different start times
4. **Double Booking Check**: Exact time match only - does not check interval overlap

### Phase 2 Gaps (Payment, Holds, Expiry)
1. **Expiry Command Not Scheduled**: `ExpirePendingPayments` command exists but not in scheduler
2. **Hardcoded Expiry Values**: 15/60 minute expiry not provider-configurable
3. **Late Payment Handling**: `verifyBookingPayment()` accepts payments without checking expiry
4. **No Bounded Grace**: No mechanism to identify in-flight payments for grace period
5. **Payment Success After Slot Taken**: No handling for payment success when slot unavailable

### Phase 3 Gaps (Approval, Rejection, Rescheduling)
1. **No Explicit Approval/Rejection Endpoints**: Only generic status transition
2. **No Authorization Rules**: No role-based permission checks for approval/rejection
3. **Rescheduling Safety**: `CustomerBookingService::rescheduleBooking()` cancels old before securing new (line 249)
4. **Rejection vs Expiry**: No distinction in current implementation

### Phase 4 Gaps (Testing)
1. **Missing Test Coverage**: Deposit flow, expiry, late payment, approval authorization, interval overlap
2. **No Concurrency Tests**: Real database concurrency verification
3. **Session Completion**: Need to verify multi-service session completion behavior

## Files Requiring Changes

### Phase 1
1. `app/Services/CustomerBookingService.php` - integrate deposit policy
2. `app/Services/BookingOrchestrator.php` - fix buffer calculation, interval overlap protection
3. `app/Services/AvailabilityEngine.php` - enhance overlap detection
4. `app/Services/BookingService.php` - ensure buffer consistency

### Phase 2
1. `routes/console.php` - schedule expiry command
2. `app/Console/Commands/ExpirePendingPayments.php` - enhance with bounded grace
3. `app/Services/Payments/BookingPaymentService.php` - add expiry check, late payment handling
4. `app/Services/BookingConfigurationService.php` - provider-configurable holds
5. `app/Services/BookingService.php` - payment success with availability recheck

### Phase 3
1. `app/Http/Controllers/Api/V1/BookingController.php` - add approve/reject endpoints
2. `app/Services/BookingService.php` - explicit approval/rejection methods
3. `app/Services/CustomerBookingService.php` - safe rescheduling (secure new first)
4. `app/Http/Middleware/` - authorization middleware for approval/rejection

### Phase 4
1. New test files for deposit flow, expiry, late payment, approval authorization
2. Enhance `ConcurrentBookingConflictTest.php` for interval overlap
3. Verify session completion tests

## Database Migrations Required

### Phase 2
1. Add `reservation_hold_minutes` to provider/location/service booking config tables
2. Add `payment_grace_started_at` to bookings table for in-flight payment tracking
3. Add `rejection_reason` to bookings table (or use existing resolution mechanism)

## Deployment Requirements

### Scheduler Configuration
- Verify Laravel scheduler is configured in production cron
- Add `payments:expire-pending` to schedule
- Verify `booking.auto-approval` and `booking.time-states` are running

### Worker Configuration
- Ensure queue workers are running for payment processing
- Verify webhook processing is configured

## Next Steps

**Phase 1 Priority**: Fix buffer calculation and interval overlap protection before any payment work.

**Critical Decision**: Whether to use database constraints (exclusion constraints on time ranges) or application-level locking for interval overlap protection. PostgreSQL supports exclusion constraints via `btree_gist` extension.

**Session Completion**: Need to inspect `BookingServiceSessionFieldsTest.php` and session completion logic to understand multi-service booking behavior before making changes.
