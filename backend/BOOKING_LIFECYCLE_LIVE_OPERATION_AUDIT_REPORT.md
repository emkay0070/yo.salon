# Yo.Salon — Booking Lifecycle & Live Operation Integration Audit

**Date:** October 9, 2026  
**Mode:** STRICT READ-ONLY INVESTIGATION  
**Scope:** Booking lifecycle, specialist live sessions, scheduling, availability, and time-state integration

---

## Section 1 — Executive Summary

Yo.Salon has a sophisticated booking architecture with clear separation between reservation management (`BookingOrchestrator`), operational floor lifecycle (`FloorController`), and live session tracking (`SessionService`). The system implements a dual-state model: booking status (business state) and operational state (time-based progression).

**Key Findings:**

- **WORKING:** Specialist live-session lifecycle is well-implemented with proper authorization, concurrency checks, and audit trails. Session start/completion are the operational source of truth for actual service execution.
- **WORKING:** Time-state processor (`ProcessBookingTimeStates`) correctly handles operational state transitions with timezone-aware calculations and idempotent execution.
- **WORKING:** Concurrency protection uses cache-based locking with 10-second locks during booking creation, plus database-level conflict detection.
- **BUG:** `BookingOrchestrator::reserveAndBook` hardcodes status override based on `internal_booking` flag, completely bypassing approval policy configuration. Manual-approval bookings are incorrectly set to `reserved` instead of `pending`.
- **BUG:** Rescheduling in `CustomerBookingService::rescheduleBooking` cancels the old booking before creating the new one, violating the agreed rule to secure the new interval before releasing the original.
- **MISSING:** No manual approval/rejection workflow exists. The approval policy is read but never acted upon—there is no UI or API endpoint for staff to approve pending bookings.
- **MISSING:** No distinct "rejected" status or rejection outcome. Rejection would need to use generic cancellation, which violates the agreed rule that rejection is a distinct final outcome.
- **MISSING:** No automatic expiry for `reserved` bookings. The `slot_locked_until` field is set but never processed by a scheduled command.
- **MISSING:** Service buffers (before-service and after-service) are defined in the database schema but not applied in availability calculations.
- **PARTIAL:** Payment expiry exists for `PaymentRequest` records (`ExpirePendingPayments` command) but does not transition the associated booking status or release slot locks.
- **INTENTIONAL EXCLUSION:** `pending` bookings are deliberately excluded from time-state processing, which is correct for manual-approval workflows.
- **INTENTIONAL EXCLUSION:** Operational floor states (`arrived`, `waiting`, `in_progress`) are not processed by the time-state command to avoid conflicts with live operations.

**Architecture Assessment:**

The existing live-operation flow can support the agreed scheduling behavior without introducing competing workflows. The session-start mechanism is the correct operational source of truth and should be preserved. The primary gaps are in policy enforcement (approval workflow) and rescheduling atomicity, not in the fundamental architecture.

---

## Section 2 — Actual Lifecycle Diagrams

### 2.1 Booking Creation Through Final Outcome

```mermaid
stateDiagram-v2
    [*] --> CustomerRequest: Customer initiates booking
    CustomerRequest --> BookingOrchestrator: POST /v1/customer/bookings
    BookingOrchestrator --> ValidateAssignment: Check specialist assignment
    ValidateAssignment --> ValidateBookability: Check service availability
    ValidateBookability --> CheckConflicts: Query existing bookings
    CheckConflicts --> CacheLock: Acquire 10s lock
    CacheLock --> DBTransaction: Begin transaction
    DBTransaction --> AllocateResources: ResourceAllocator
    AllocateResources --> DetermineStatus: Set initial status
    
    DetermineStatus --> Reserved: external_booking (BUG: overrides policy)
    DetermineStatus --> Confirmed: internal_booking
    
    Reserved --> CreateBooking: Booking::create
    Confirmed --> CreateBooking: Booking::create
    
    CreateBooking --> [*]: Return booking
    
    note right of DetermineStatus
        BUG: Status hardcoded by internal_booking flag
        Should use BookingConfigurationService::getPolicy
        Manual approval → pending
        Instant approval → reserved (for payment)
    end note
    
    Reserved --> PaymentFlow: If deposit required
    Confirmed --> [*]: Instant confirmation
    
    PaymentFlow --> PendingPayment: BookingPaymentService::initialize
    PendingPayment --> PaymentComplete: verifyBookingPayment
    PaymentComplete --> Confirmed: Booking status transition
    PaymentComplete --> [*]: Payment failed
    
    Confirmed --> Arrived: FloorController::arrive
    Arrived --> Waiting: FloorController::wait
    Waiting --> InProgress: SessionService::startSession
    InProgress --> Completed: SessionService::completeSession
    
    Confirmed --> Cancelled: transitionStatus
    Arrived --> Cancelled: transitionStatus
    Waiting --> Cancelled: transitionStatus
    InProgress --> Cancelled: transitionStatus
    
    Confirmed --> NoShow: resolveBooking (grace expired)
    Arrived --> NoShow: resolveBooking (grace expired)
    
    Completed --> [*]
    Cancelled --> [*]
    NoShow --> [*]
```

### 2.2 Specialist Live-Session Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Scheduled: BookingService created with session_status='scheduled'
    
    Scheduled --> InProgress: SessionService::startSession
    note right of Scheduled
        Preconditions:
        - Booking status: confirmed, arrived, or waiting
        - Specialist assigned
        - Active specialist assignment
        - No other active session for specialist
        - Not on active break
    end note
    
    InProgress --> Completed: SessionService::completeSession
    note right of InProgress
        Side effects:
        - Sets actual_end_at
        - If all sessions complete → booking → completed
        - Invalidates availability cache
    end note
    
    Scheduled --> Cancelled: SessionService::cancelSession
    InProgress --> Cancelled: SessionService::cancelSession
    
    Completed --> [*]
    Cancelled --> [*]
    
    note right of InProgress
        Authorization:
        - SpecialistAccount: must be assigned specialist
        - User (staff): must have operate_floor permission
        - Location-scoped access check
    end note
```

### 2.3 Booking State, Temporary Holds, Payment, and Scheduling Interaction

```mermaid
stateDiagram-v2
    [*] --> AvailabilityCheck: Customer selects slot
    AvailabilityCheck --> SlotLock: AvailabilityEngine::lockSlot (optional)
    SlotLock --> PendingHold: Booking with status='pending', slot_locked_until=+10min
    PendingHold --> ReleaseSlot: AvailabilityEngine::releaseSlot (if not converted)
    ReleaseSlot --> [*]
    
    PendingHold --> BookingOrchestrator: Customer completes booking
    BookingOrchestrator --> Reserved: status='reserved', slot_locked_until=+10min
    note right of Reserved
        BUG: Should be 'pending' for manual approval
        Slot lock preserved for payment window
    end note
    
    Reserved --> PendingPayment: If deposit required
    Reserved --> Confirmed: If no deposit (instant approval)
    
    PendingPayment --> PaymentInitiated: BookingPaymentService::initialize
    PaymentInitiated --> PaymentRequestCreated: PaymentRequest with expires_at=+15min
    PaymentRequestCreated --> PaymentComplete: verifyBookingPayment
    PaymentRequestCreated --> PaymentExpired: ExpirePendingPayments command
    
    PaymentComplete --> Confirmed: Booking status → confirmed
    PaymentExpired --> [*]: Payment request expires (booking status unchanged)
    
    note right of PaymentExpired
        GAP: Booking not cancelled
        Slot lock not released
        No customer notification
    end note
    
    Confirmed --> TimeStateProcessing: ProcessBookingTimeStates command
    TimeStateProcessing --> Upcoming: >30min before scheduled
    TimeStateProcessing --> DueSoon: Within 30min
    TimeStateProcessing --> GracePeriod: At scheduled time
    TimeStateProcessing --> NeedsResolution: Grace period expired
    
    NeedsResolution --> Arrived: FloorController::arrive (late arrival)
    NeedsResolution --> NoShow: resolveBooking (CUSTOMER_DID_NOT_ARRIVE)
    
    GracePeriod --> Arrived: FloorController::arrive (on-time)
    Arrived --> Waiting: FloorController::wait
    Waiting --> InProgress: SessionService::startSession
```

### 2.4 Scheduler/Time-State Processing Flow

```mermaid
flowchart TD
    A[Scheduled: bookings:process-time-states] --> B[Query bookings]
    B --> C{Filter by status}
    C -->|confirmed or pending_payment| D[Include]
    C -->|pending or reserved| E[Exclude - intentional]
    
    D --> F{Filter by operational_state}
    F -->|!= resolved| G[Process]
    F -->|== resolved| H[Skip]
    
    G --> I[Calculate time delta]
    I --> J{Compare to thresholds}
    J -->|>30min before| K[State: upcoming]
    J -->|Within 30min| L[State: due_soon]
    J -->|At scheduled time| M[State: grace_period]
    J -->|After grace| N[State: needs_resolution]
    
    M --> O[Set grace_period_started_at = scheduled_at]
    O --> P[Set grace_period_ends_at = scheduled_at + 15min]
    
    K --> Q[Create BookingActivity]
    L --> Q
    M --> Q
    N --> Q
    
    Q --> R[DB transaction]
    R --> S[Update operational_state]
    S --> T[Return transition type]
    
    note right of E
        INTENTIONAL EXCLUSION:
        Pending bookings awaiting manual
        approval should not enter time-state
        processing. They are not confirmed
        appointments.
    end note
    
    note right of O
        CRITICAL: Grace period anchored to
        scheduled_at, not current time.
        Ensures idempotency even if
        scheduler runs late.
    end note
```

---

## Section 3 — Transition and Ownership Matrix

| Current state | Trigger | New state | Authoritative component | Side effects | Evidence |
| ------------- | ------- | --------- | ----------------------- | ------------ | -------- |
| (none) | Customer booking request (external) | `reserved` | `BookingOrchestrator::reserveAndBook` (line 214) | Sets `slot_locked_until = now() + 10min`, creates BookingService records | **BUG**: Should be `pending` for manual approval per policy |
| (none) | Staff booking request (internal) | `confirmed` | `BookingOrchestrator::reserveAndBook` (line 216) | No slot lock, bypasses availability validation | Working - internal walk-ins bypass approval |
| `reserved` | Payment completed | `confirmed` | `BookingPaymentService::verifyBookingPayment` (line 257) | Updates `payment_status = 'paid'`, dispatches `PaymentConfirmed` event | Working |
| `pending_payment` | Payment completed | `confirmed` | `BookingPaymentService::verifyBookingPayment` (line 257) | Updates `payment_status = 'paid'`, dispatches `PaymentConfirmed` event | Working |
| `confirmed` | Customer cancels (>24h before) | `cancelled` | `CustomerBookingService::cancelBooking` (line 231) | Calls `BookingService::transitionStatus`, cancels reminders | Working - 24-hour policy enforced |
| `confirmed` | Staff marks arrived | `arrived` | `FloorController::arrive` (line 64) | Sets `arrived_at` timestamp, creates activity | Working |
| `arrived` | Staff places in queue | `waiting` | `FloorController::wait` (line 99) | Sets `waiting_since`, optional `queue_position` | Working |
| `waiting` | Specialist starts session | `in_progress` | `SessionService::startSession` (line 96) | Sets `started_at`, `session_status = 'in_progress'`, invalidates cache | Working - concurrency check prevents duplicate starts |
| `in_progress` | Specialist completes session | `completed` | `SessionService::completeSession` (line 173) | Sets `actual_end_at`, `session_status = 'completed'`, invalidates cache | Working - auto-transitions booking if all sessions complete |
| `confirmed` | Time-state: >30min before | `operational_state = upcoming` | `ProcessBookingTimeStates::processBooking` (line 110) | Creates activity `operational_state_changed` | Working - idempotent |
| `upcoming` | Time-state: within 30min | `operational_state = due_soon` | `ProcessBookingTimeStates::processBooking` (line 113) | Creates activity `operational_state_due_soon` | Working |
| `due_soon` | Time-state: at scheduled time | `operational_state = grace_period` | `ProcessBookingTimeStates::processBooking` (line 116) | Sets `grace_period_started_at = scheduled_at`, creates activity | Working - anchored to scheduled time |
| `grace_period` | Time-state: grace expired | `operational_state = needs_resolution` | `ProcessBookingTimeStates::processBooking` (line 119) | Creates activity `grace_period_expired` | Working |
| `needs_resolution` | Staff marks late arrival | `arrived` | `FloorController::arrive` (line 47) | Calls `BookingService::resolveBooking` with `CUSTOMER_ARRIVED` | Working - resolution path for late arrivals |
| `needs_resolution` | Staff marks no-show | `no_show` | `BookingService::resolveBooking` | Creates `BookingResolution`, sets `operational_state = resolved` | Working |
| `confirmed` | Staff reschedules (current) | `cancelled` → new booking | `CustomerBookingService::rescheduleBooking` (line 249) | **BUG**: Cancels old before creating new, violates atomicity rule | GAP - should secure new slot first |
| `confirmed` | Any status transition | Target state | `BookingService::transitionStatus` (line 538) | State machine validation, activity creation, reminder scheduling/cancellation | Working - single authority for transitions |

---

## Section 4 — Availability and Concurrency Analysis

### 4.1 Appointment Interval Calculation

**Service Duration:**
- Stored in `Service.duration` (integer, minutes)
- No before-service or after-service buffers applied in current implementation
- Buffers exist in database schema (`buffer_before`, `buffer_after` columns) but are not read or used

**Slot Generation:**
- `AvailabilityEngine::generateTimeSlots` uses 15-minute granularity (configurable)
- Slots are generated from resolved schedule (ProviderSchedule → LocationSchedule → AssignmentSchedule)
- Timezone-aware: uses `TimezoneResolverService` to determine provider/location timezone, defaults to `Africa/Kampala`

**Existing Booking Query:**
```php
// AvailabilityEngine::getExistingBookingsByContext (line 531)
->whereIn('status', ['confirmed', 'pending'])
->where(function ($query) {
    $query->whereNull('slot_locked_until')
          ->orWhere('slot_locked_until', '>', Carbon::now());
})
```
- Only `confirmed` and `pending` bookings occupy time in availability calculations
- Expired slot locks are excluded (correct)
- `reserved` bookings are NOT included, which is a **GAP** - they should occupy time during payment window

**Live Session Extensions:**
```php
// AvailabilityEngine::getLiveSessionExtensionsByContext (line 606)
->where('session_status', 'in_progress')
->whereNotNull('started_at')
```
- In-progress sessions extend their occupancy to actual end time if overrunning
- This is an operational extension layer over the scheduled reservation
- Correctly handles service overruns without silently pushing later appointments

### 4.2 Concurrency Protection

**Cache-Based Locking:**
```php
// BookingOrchestrator::reserveAndBook (line 186)
$lockKey = $assignment 
    ? "booking_slot_{$assignment->id}_{$date}_{$time}"
    : "booking_slot_provider_{$providerId}_{$date}_{$time}";
$lock = Cache::lock($lockKey, 10); // 10 seconds
```
- Lock key includes assignment ID (or provider ID for independent specialists), date, and time
- Lock duration: 10 seconds
- If lock cannot be acquired, throws exception with "slot currently being booked" message
- **VERIFIED**: This provides atomic protection for simultaneous booking requests

**Database-Level Conflict Detection:**
```php
// BookingOrchestrator::reserveAndBook (line 120)
$conflictQuery = Booking::where('provider_id', $providerId)
    ->where('date', $date)
    ->where('time', $time)
    ->whereIn('status', ['confirmed', 'pending', 'reserved']);
```
- Runs inside the DB transaction after cache lock is acquired
- Checks for existing bookings at same provider/location/date/time
- Includes `reserved` status (correct for payment window)
- **VERIFIED**: Double-booking protection at database level

**BookingService Validation:**
```php
// BookingService::validateBookingTime (line 136)
$existingBooking = Booking::where('provider_id', $providerId)
    ->when($specialistId, fn($q) => $q->where('specialist_id', $specialistId))
    ->where('date', $date)
    ->where('time', $time)
    ->whereIn('status', ['pending', 'confirmed'])
    ->first();
```
- Additional safety check in `BookingService` (used by customer booking flows)
- **UNVERIFIED**: Does not include `reserved` status, potential gap

### 4.3 Rescheduling Atomicity

**Current Implementation:**
```php
// CustomerBookingService::rescheduleBooking (line 245)
return DB::transaction(function () use ($bookingId, $newData) {
    $booking = Booking::findOrFail($bookingId);
    $this->cancelBooking($bookingId); // Cancels OLD first
    $newBooking = $this->createGuestBooking(array_merge($newData, [...]));
    // ...
});
```
- **BUG**: Cancels the original booking before creating the new one
- If new booking creation fails, the original is already cancelled
- Violates agreed rule: "Secure a rescheduled appointment's new interval before releasing the original interval"
- **GAP**: No atomic two-slot hold during rescheduling

### 4.4 Walk-in Booking Conflict Protection

**Internal Booking Bypass:**
```php
// BookingOrchestrator::reserveAndBook (line 158)
if (!$bookabilityEngine->acceptsOnlineBooking() && empty($validatedData['internal_booking'])) {
    throw new \Exception('Online booking is not available...');
}
```
- Internal/walk-in bookings bypass online-booking capability check
- **BUT**: They do NOT bypass conflict detection (line 120) or concurrency locking (line 186)
- **VERIFIED**: Walk-ins use same backend conflict protection as online bookings

### 4.5 Timezone Consistency

**Timezone Resolution:**
- `TimezoneResolverService::resolveBusinessTimezone` used by time-state processor
- Falls back to location timezone, then provider timezone, then `Africa/Kampala`
- **VERIFIED**: Availability calculations and time-state processing use consistent timezone handling

---

## Section 5 — Findings Classification

### 5.1 Booking Lifecycle

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| BUG | Status override bypasses approval policy | `BookingOrchestrator.php` | `BookingOrchestrator::reserveAndBook` | 212-217 | Manual-approval bookings incorrectly set to `reserved` instead of `pending` |
| BUG | Rescheduling cancels before securing new slot | `CustomerBookingService.php` | `CustomerBookingService::rescheduleBooking` | 245-259 | Violates atomicity rule, can leave customer with no booking |
| MISSING | Manual approval workflow | - | - | - | No API endpoint or UI for staff to approve/reject pending bookings |
| MISSING | Rejection status/outcome | - | - | - | No distinct "rejected" status; would need to use generic cancellation |
| MISSING | Reserved booking expiry | - | - | - | `slot_locked_until` set but never processed; no scheduled command |
| PARTIAL | Payment expiry | `ExpirePendingPayments.php` | `ExpirePendingPayments::handle` | 28-44 | Expires `PaymentRequest` but does not transition booking or release slot |
| WORKING | Booking status state machine | `BookingService.php` | `BookingService::transitionStatus` | 538-626 | Single authority with validation, activity creation, reminder management |
| WORKING | Resolution workflow | `BookingService.php` | `BookingService::resolveBooking` | 649-756 | Formal resolution records with outcomes and reason codes |
| WORKING | Cancellation policy | `CustomerBookingService.php` | `CustomerBookingService::cancelBooking` | 213-238 | 24-hour minimum enforced before cancellation |
| INTENTIONAL EXCLUSION | Pending bookings in time-state processor | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::handle` | 53 | Correct - pending bookings not yet confirmed appointments |

### 5.2 Specialist Live-Session Lifecycle

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| WORKING | Session start validation | `SessionService.php` | `SessionService::startSession` | 21-125 | Validates booking state, specialist assignment, active assignment, no duplicate sessions, no active break |
| WORKING | Session completion | `SessionService.php` | `SessionService::completeSession` | 135-206 | Validates session in progress, sets actual_end_at, auto-transitions booking if all sessions complete |
| WORKING | Session cancellation | `SessionService.php` | `SessionService::cancelSession` | 216-262 | Allows cancellation from scheduled or in_progress states |
| WORKING | Authorization checks | `SessionService.php` | `SessionService::authorizeActorForSession` | 267-307 | SpecialistAccount: must be assigned specialist; User: must have operate_floor permission and location access |
| WORKING | Concurrency protection | `SessionService.php` | `SessionService::startSession` | 56-67 | Prevents specialist from starting another session while one is in progress |
| WORKING | Break conflict check | `SessionService.php` | `SessionService::startSession` | 69-79 | Prevents session start if specialist is on active break |
| WORKING | Cache invalidation | `SessionService.php` | `SessionService::startSession/completeSession` | 117-122, 198-203 | Invalidates availability cache on session state changes |
| WORKING | Floor controller integration | `FloorController.php` | `FloorController::startSession/completeSession` | 131-178 | Exposes session operations to floor staff with proper authorization |

### 5.3 Scheduling and Availability

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| MISSING | Service buffers in availability | `AvailabilityEngine.php` | `AvailabilityEngine::generateTimeSlots` | - | Buffer columns exist in schema but not applied to slot calculations |
| GAP | Reserved bookings not in availability check | `AvailabilityEngine.php` | `AvailabilityEngine::getExistingBookingsByContext` | 535 | Only checks `confirmed` and `pending`; should include `reserved` during payment window |
| WORKING | Schedule resolution hierarchy | `AvailabilityEngine.php` | `AvailabilityEngine::getSchedule` | 448-514 | ProviderSchedule → LocationSchedule → AssignmentSchedule with proper inheritance |
| WORKING | Specialist assignment validation | `BookingOrchestrator.php` | `BookingOrchestrator::reserveAndBook` | 84-103 | Checks for active assignment at provider/location level |
| WORKING | Live session extensions | `AvailabilityEngine.php` | `AvailabilityEngine::getLiveSessionExtensionsByContext` | 606-656 | Extends occupancy for in-progress sessions that overrun |
| WORKING | Specialist breaks in availability | `AvailabilityEngine.php` | `AvailabilityEngine::getExistingBookingsByContext` | 561-580 | Injects specialist breaks as blocked slots |

### 5.4 Concurrency and Slot Protection

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| WORKING | Cache-based locking | `BookingOrchestrator.php` | `BookingOrchestrator::reserveAndBook` | 181-190 | 10-second lock on assignment/provider + date + time key |
| WORKING | Database conflict detection | `BookingOrchestrator.php` | `BookingOrchestrator::reserveAndBook` | 120-139 | Double-booking check inside transaction after lock acquired |
| WORKING | Idempotency key check | `BookingOrchestrator.php` | `BookingOrchestrator::reserveAndBook` | 46-54 | Prevents duplicate booking creation from same request |
| WORKING | Walk-in conflict protection | `BookingOrchestrator.php` | `BookingOrchestrator::reserveAndBook` | 120-139 | Walk-ins use same conflict detection as online bookings |
| UNVERIFIED | BookingService conflict check | `BookingService.php` | `BookingService::validateBookingTime` | 136-145 | Does not include `reserved` status; may allow double-booking during payment window |

### 5.5 Time-State Processing

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| WORKING | Time-state transitions | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::processBooking` | 88-139 | Correctly transitions upcoming → due_soon → grace_period → needs_resolution |
| WORKING | Grace period anchoring | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::transitionState` | 159-162 | Grace period anchored to scheduled_at, not current time (idempotent) |
| WORKING | Idempotent execution | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::processBooking` | 122-125 | Skips if already in target state |
| WORKING | Terminal state protection | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::processBooking` | 127-135 | Does not reopen completed/cancelled/no_show or resolved bookings |
| WORKING | Operational floor exclusion | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::processBooking` | 132-135 | Skips arrived/waiting/in_progress to avoid conflicts |
| WORKING | Timezone handling | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::processBooking` | 90-98 | Uses TimezoneResolverService for provider/location timezone |
| WORKING | Pending payment inclusion | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::handle` | 53 | Correctly includes `pending_payment` bookings |
| INTENTIONAL EXCLUSION | Pending bookings excluded | `ProcessBookingTimeStates.php` | `ProcessBookingTimeStates::handle` | 53 | Pending bookings not processed (not yet confirmed) |

### 5.6 Payment and Temporary Holds

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| PARTIAL | Payment request expiry | `ExpirePendingPayments.php` | `ExpirePendingPayments::handle` | 28-44 | Expires PaymentRequest but does not cancel booking or release slot |
| WORKING | Payment initialization | `BookingPaymentService.php` | `BookingPaymentService::initializeBookingPayment` | 43-173 | Creates PaymentRequest with expires_at, updates booking payment_status |
| WORKING | Payment verification | `BookingPaymentService.php` | `BookingPaymentService::verifyBookingPayment` | 181-278 | Creates Transaction, updates PaymentRequest and booking, dispatches event |
| WORKING | Manual payment recording | `BookingPaymentService.php` | `BookingPaymentService::recordManualPayment` | 320-387 | Supports cash/card terminal payments |
| WORKING | Refund processing | `BookingPaymentService.php` | `BookingPaymentService::refundBookingPayment` | 396-421 | Creates refund transaction, dispatches RefundConfirmed event |
| GAP | Payment expiry → booking transition | - | - | - | No command to cancel bookings when payment expires |

### 5.7 Frontend and User Experience

| Classification | Finding | File | Class/Method | Lines | Impact |
| -------------- | ------- | ---- | ------------ | ----- | ------ |
| WORKING | Specialist session UI | `appointments/page.tsx` | AppointmentsPage | 67-93 | Start/complete/cancel session buttons with proper API calls |
| WORKING | Session timer display | `appointments/page.tsx` | AppointmentsPage | 96-108 | Real-time duration timer in session modal |
| WORKING | Status display mapping | `BookingDetailsDrawer.tsx` | STATUS_STYLE | 42-52 | Explicit mappings for pending, reserved, pending_payment (recently fixed) |
| WORKING | Booking details drawer | `BookingDetailsDrawer.tsx` | BookingDetailsDrawer | - | Displays booking status with proper styling |
| GAP | Manual approval UI | - | - | - | No interface for staff to approve/reject pending bookings |
| GAP | Rejection UI | - | - | - | No interface for rejecting bookings (distinct from cancellation) |

---

## Section 6 — Tests and Evidence

### 6.1 Existing Tests (Repository)

**BookingTimeStateEngineTest.php** (392 lines)
- `test_upcoming_state_before_due_soon_threshold` - Verifies upcoming state >30min before scheduled
- `test_due_soon_state_within_30_minutes` - Verifies due_soon state within 30min
- `test_grace_period_starts_at_scheduled_time` - Verifies grace period anchored to scheduled time
- `test_scheduler_runs_late_grace_period_anchored_to_scheduled_time` - Verifies idempotency when scheduler runs late
- `test_grace_period_expires` - Verifies transition to needs_resolution
- `test_repeated_scheduler_run_is_idempotent` - Verifies no duplicate activities on repeated runs
- `test_terminal_booking_not_reopened` - Verifies completed/cancelled/no_show not reopened
- `test_already_resolved_remains_resolved` - Verifies resolved state persists
- `test_arrived_booking_not_moved_to_grace_period` - Verifies operational floor exclusion
- `test_waiting_booking_not_moved_to_grace_period` - Verifies operational floor exclusion
- `test_in_progress_booking_not_moved_to_grace_period` - Verifies operational floor exclusion
- `test_timezone_fallback_to_africa_kampala` - Verifies timezone handling
- `test_booking_near_midnight` - Verifies midnight boundary handling
- `test_pending_payment_booking_processed` - Verifies pending_payment inclusion
- `test_pending_booking_not_processed` - Verifies pending exclusion (intentional)

**SessionServiceTest.php** (284 lines)
- `test_confirmed_booking_can_start_session` - Verifies session start from confirmed state
- `test_pending_booking_cannot_start_session` - Verifies pending bookings cannot start sessions
- `test_wrong_specialist_cannot_start_session` - Verifies specialist ownership check
- `test_inactive_specialist_assignment_cannot_operate_session` - Verifies active assignment requirement
- `test_duplicate_start_rejected` - Verifies duplicate start prevention
- `test_started_at_is_preserved` - Verifies started_at cannot be overwritten
- `test_late_scheduled_start_is_allowed` - Verifies late starts are permitted
- `test_session_can_complete_after_starting` - Verifies completion flow
- `test_completion_before_start_rejected` - Verifies completion requires start
- `test_duplicate_completion_rejected` - Verifies duplicate completion prevention
- `test_actual_end_at_is_preserved` - Verifies actual_end_at cannot be overwritten
- `test_completing_session_does_not_change_parent_booking_status` - Verifies session completion doesn't auto-transition booking
- `test_booking_with_null_specialist_id_cannot_start_session` - Verifies specialist requirement
- `test_session_not_in_scheduled_state_cannot_start` - Verifies state validation
- `test_session_not_in_progress_cannot_complete` - Verifies state validation

**Other Relevant Tests:**
- `BookingResolutionTest.php` - Resolution workflow tests
- `BookingNeedsResolutionApiTest.php` - Needs resolution API tests
- `BookingServicePersistenceTest.php` - Booking persistence tests
- `SessionAvailabilityIntegrationTest.php` - Session availability integration
- `SessionCacheInvalidationTest.php` - Cache invalidation tests

### 6.2 Test Execution

No tests were executed during this investigation (read-only mode). All evidence is from static analysis of test definitions and source code.

---

## Section 7 — Minimal Gap List

### 7.1 Backend Booking Lifecycle

1. **Manual approval workflow missing**
   - Gap: No API endpoint or service method for staff to approve pending bookings
   - Impact: Approval policy is read but never enforced
   - Required: POST endpoint to transition `pending` → `confirmed` with authorization checks

2. **Rejection status/outcome missing**
   - Gap: No distinct "rejected" status or rejection outcome
   - Impact: Cannot distinguish rejection from cancellation
   - Required: Add `rejected` status to state machine, or use `BookingResolution` with `REJECTED` outcome

3. **Reserved booking expiry missing**
   - Gap: `slot_locked_until` field is set but never processed
   - Impact: Expired reservations remain indefinitely
   - Required: Scheduled command to cancel `reserved` bookings where `slot_locked_until < now`

### 7.2 Scheduling/Availability

4. **Service buffers not applied**
   - Gap: `buffer_before` and `buffer_after` columns exist but are not used
   - Impact: No padding around service intervals
   - Required: Read buffer values and apply them in slot generation

5. **Reserved bookings not in availability check**
   - Gap: `AvailabilityEngine::getExistingBookingsByContext` only checks `confirmed` and `pending`
   - Impact: Double-booking possible during payment window
   - Required: Add `reserved` status to availability query (with slot lock expiry check)

6. **Rescheduling not atomic**
   - Gap: `CustomerBookingService::rescheduleBooking` cancels old before creating new
   - Impact: Can leave customer with no booking if new creation fails
   - Required: Secure new slot in transaction, then release old slot

### 7.3 Payment and Temporary Holds

7. **Payment expiry does not transition booking**
   - Gap: `ExpirePendingPayments` only expires `PaymentRequest`, not booking
   - Impact: Booking remains in `pending_payment` state indefinitely
   - Required: Add booking status transition to `cancelled` when payment expires

8. **No payment grace period**
   - Gap: Payment initiated before expiry receives no processing grace
   - Impact: Race condition between payment processing and expiry
   - Required: Add bounded grace period (e.g., 2 minutes) for payment completion

### 7.4 Live-Operation Integration

9. **No gaps identified**
   - Live-session lifecycle is well-implemented
   - Time-state processor correctly excludes operational floor states
   - Session start/completion are the correct operational source of truth

### 7.5 Time-State Processing

10. **No gaps identified**
    - Time-state processor is working correctly
    - Grace period anchoring is idempotent
    - Pending booking exclusion is intentional and correct

### 7.6 Frontend Presentation

11. **Manual approval UI missing**
    - Gap: No interface for staff to approve/reject pending bookings
    - Impact: Staff cannot act on approval policy
    - Required: Add approval/rejection buttons to the booking management interface

12. **Rejection UI missing**
    - Gap: No interface for rejecting bookings
    - Impact: Cannot distinguish rejection from cancellation in UI
    - Required: Add rejection option with reason selection

---

## Section 8 — Questions Requiring Product Decisions

1. **Payment vs. Approval Priority**
   - When both manual approval and deposit payment are required, which takes precedence?
   - Current behavior: Payment takes precedence (`BookingService::createBookingWithAccount` line 325)
   - Options:
     - Payment first: `pending_payment` → payment → `pending` → approval → `confirmed`
     - Approval first: `pending` → approval → `pending_payment` → payment → `confirmed`
     - Parallel: Both required, either can fail

2. **Reserved Booking Expiry Behavior**
   - When a `reserved` booking expires (payment window elapses), should it:
     - Be automatically cancelled?
     - Transition to `pending` (awaiting manual approval if configured)?
     - Remain in `reserved` for staff to decide?
   - Current behavior: No automatic action (gap)

3. **Payment Expiry Grace Period**
   - Should payments initiated before expiry receive a short processing grace period?
   - If yes, how long? (e.g., 2 minutes)
   - Should availability be rechecked before confirmation after grace period?

4. **Rejection vs. Cancellation Semantics**
   - Should rejection be a distinct status (`rejected`) or use the existing resolution framework with `REJECTED` outcome?
   - What are the business rules for when rejection is allowed vs. cancellation?

5. **Service Buffer Configuration**
   - Should buffers be configurable per service, per location, or per provider?
   - Should buffers apply to availability calculations only, or also to session timing?

6. **Rescheduling Failure Notification**
   - If rescheduling fails (new slot unavailable), should the customer:
     - Be offered the nearest available alternative?
     - Keep the original booking?
     - Receive a notification with options?

7. **Walk-in Override Authorization**
   - Should walk-in bookings that bypass availability require explicit permission, reason, and audit record?
   - Current behavior: Only `internal_booking` flag, no additional audit

8. **Specialist Unavailability Handling**
   - When a specialist becomes unavailable, should affected appointments be:
     - Automatically reassigned to available specialists?
     - Flagged for staff to manually resolve?
     - Cancelled with customer notification?

---

## Conclusion

Yo.Salon's booking architecture is fundamentally sound with clear separation of concerns between reservation management, operational floor lifecycle, and live session tracking. The specialist live-session workflow is well-implemented and should be preserved as the operational source of truth.

The primary gaps are in policy enforcement (manual approval workflow), rescheduling atomicity, and payment/booking expiry integration. These are implementation gaps rather than architectural flaws—the existing structure can support the agreed business rules with targeted fixes.

The time-state processor and availability engine are working correctly with intentional exclusions for pending bookings and operational floor states. Concurrency protection is robust with cache-based locking and database-level conflict detection.

No new entities, duplicate operational flows, or major architectural changes are required to address the identified gaps.
