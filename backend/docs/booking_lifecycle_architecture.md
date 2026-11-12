# Booking Lifecycle Architecture

**Date**: October 6, 2026
**Status**: Design Document (Not Implemented)

## Overview

This architecture implements a **booking lifecycle engine** with three distinct layers:

1. **booking.status** — authoritative lifecycle state (where the booking actually is)
2. **booking.operational_state** — persisted current operational state (what the system currently knows)
3. **booking_activities** — immutable history (the story of what happened)

The scheduler persists time-based transitions to the database, so the UI renders truth rather than inventing it. Yesterday's bookings remain queryable and resolvable.

## Design Principles

1. **Three-layer separation of concerns**
   - `status`: Business lifecycle (pending, confirmed, arrived, completed, cancelled, no_show, rescheduled)
   - `operational_state`: Time-derived operational state (upcoming, due_soon, grace_period, needs_resolution, resolved)
   - `booking_activities`: Immutable event history with actor tracking

2. **Database is source of truth**
   - Scheduler persists operational state transitions (not just UI calculation)
   - All state changes create BookingActivity records
   - Yesterday's bookings don't disappear — they remain queryable

3. **Honest history**
   - Rescheduled bookings create new records with `rescheduled_from_booking_id`
   - Original booking marked as rescheduled, not mutated
   - Resolution tracking with follow-up capability

4. **Every transition produces an event**
   - BookingActivity records who, what, when, why
   - Structured reason codes for analytics and disputes
   - Actor tracking (CUSTOMER, SPECIALIST, STAFF, PROVIDER, SYSTEM)

---

## Layer 1: Booking Status (Authoritative Lifecycle)

### Status Values — Where the booking actually is

```
pending              // Initial state, awaiting approval/payment
pending_payment      // Awaiting deposit/payment
confirmed            // Appointment confirmed, awaiting arrival
arrived              // Customer physically arrived
waiting              // Customer in waiting queue
in_progress          // Service being delivered
completed            // Service completed successfully
cancelled            // Booking cancelled
no_show              // Customer did not attend
rescheduled          // Booking was rescheduled (original record)
```

### State Machine

```
                    ┌──────────────┐
                    │    pending    │
                    └──────┬───────┘
                           │
              payment / approval
                           │
              ┌────────────┴────────────┐
              │                         │
         pending_payment            confirmed
              │                         │
         payment_confirmed           │
              │                         │
              └────────────┬────────────┘
                           │
                    ┌──────▼───────┐
                    │   confirmed   │
                    └──────┬───────┘
                           │
                     customer arrives
                           │
                    ┌──────▼───────┐
                    │    arrived   │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
           waiting   in_progress  cancelled
              │            │             │
              │         completed   no_show
              │                         │
              └────────────┬────────────┘
                           │
                    rescheduled (creates new booking)
```

**Key principle:** `grace_period_started`, `missed`, `needs_resolution` are **NOT** booking statuses. They are operational states.

---

## Layer 2: Operational State (Persisted, Time-Driven)

### Operational State Values — What the system currently knows

```
upcoming            // Appointment is in the future
due_soon            // Appointment is within 30 minutes
grace_period        // Appointment time has passed, grace period active
needs_resolution    // Grace period expired, outcome unknown
resolved            // Booking has been resolved (completed, cancelled, no_show)
```

### State Transitions (Persisted by Scheduler)

The scheduler **writes these transitions to the database**, not just calculates them.

```
confirmed
    ↓ (at scheduled_at)
grace_period
    ↓ (at grace_period_ends_at)
needs_resolution
    ↓ (when resolved)
resolved
```

### Example Timeline

**At 14:00 (scheduled_at):**
```php
booking.status = confirmed
booking.operational_state = grace_period
booking.grace_period_started_at = 2026-10-06 14:00:00
booking.grace_period_ends_at = 2026-10-06 14:15:00

// BookingActivity created:
type: GRACE_PERIOD_STARTED
actor: SYSTEM
```

**At 14:15 (grace_period_ends_at):**
```php
booking.status = confirmed
booking.operational_state = needs_resolution

// BookingActivity created:
type: GRACE_PERIOD_EXPIRED
actor: SYSTEM
```

**At 14:17 (receptionist resolves):**
```php
booking.status = no_show
booking.operational_state = resolved

// BookingActivity created:
type: BOOKING_MARKED_MISSED
reason: CUSTOMER_DID_NOT_ARRIVE
actor: STAFF
actor_id: user_123
```

### UI Grouping (Renders from DB)

```
UPCOMING
──────────────
10:30   Promey
11:00   Sarah
11:30   John

DUE_SOON
──────────────
1:55    Mary
        5 min until appointment

GRACE_PERIOD
──────────────
2:00    Sarah
        4 min overdue
        [Arrived] [No-show]

NEEDS_RESOLUTION
──────────────
Yesterday

09:30   James
        Missed
        Reason not recorded
        [Resolve]

14:00   Brian
        Grace period expired
        [Resolve]

RESOLVED
──────────────
09:00   Alice
        Completed
```

---

## Layer 3: Resolution Tracking (Structured Outcomes)

### Booking Resolutions Table

A separate table to track resolution outcomes and follow-up actions.

```php
// Migration: create_booking_resolutions_table.php
Schema::create('booking_resolutions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('booking_id');
    $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');

    // Resolution outcome
    $table->string('outcome'); // NO_SHOW, CANCELLED, RESCHEDULED, COMPLETED_LATE, CUSTOMER_ARRIVED
    $table->string('reason_code'); // CUSTOMER_DID_NOT_ARRIVE, CUSTOMER_CANCELLED, PROVIDER_UNAVAILABLE, etc.
    $table->text('notes')->nullable();

    // Actor tracking
    $table->string('actor_type'); // CUSTOMER, SPECIALIST, STAFF, PROVIDER, SYSTEM
    $table->uuid('actor_id')->nullable();

    // Timing
    $table->timestamp('resolved_at');

    // Follow-up tracking
    $table->boolean('follow_up_required')->default(false);
    $table->timestamp('follow_up_at')->nullable();
    $table->string('follow_up_status')->nullable(); // PENDING, SENT, RESPONDED, COMPLETED
    $table->text('follow_up_notes')->nullable();

    $table->timestamps();
    $table->index('booking_id');
    $table->index('resolved_at');
});
```

### Resolution Outcomes

```
NO_SHOW              // Customer did not attend
CANCELLED            // Booking was cancelled
RESCHEDULED          // Customer rescheduled to new time
COMPLETED_LATE       // Customer arrived late, service completed
CUSTOMER_ARRIVED     // Customer arrived after grace period
```

### Resolution Reason Codes

```
CUSTOMER_DID_NOT_ARRIVE
CUSTOMER_CANCELLED
CUSTOMER_REQUESTED_RESCHEDULE
CUSTOMER_COULD_NOT_BE_REACHED
PROVIDER_UNAVAILABLE
SPECIALIST_UNAVAILABLE
LOCATION_CLOSED
TECHNICAL_ISSUE
OTHER
```

---

## Database Schema Changes

### 1. Add Operational State and Grace Period to `bookings` Table

```php
// Migration: add_operational_state_to_bookings_table.php
Schema::table('bookings', function (Blueprint $table) {
    // Operational state (persisted, not calculated)
    $table->string('operational_state')->default('upcoming')->after('status');

    // Grace period tracking
    $table->timestamp('grace_period_started_at')->nullable()->after('operational_state');
    $table->timestamp('grace_period_ends_at')->nullable()->after('grace_period_started_at');

    // Rescheduling tracking (honest history)
    $table->uuid('rescheduled_from_booking_id')->nullable()->after('grace_period_ends_at');
    $table->foreign('rescheduled_from_booking_id')->references('id')->on('bookings')->nullOnDelete();
});
```

### 2. Add Notification Policy to Config

```php
// Migration: add_notification_policy_to_provider_booking_config.php
Schema::table('provider_booking_config', function (Blueprint $table) {
    $table->json('notification_policy')->nullable()->after('booking_policy');
});
```

---

## Layer 4: Activity History (Immutable Story)

### BookingActivity `type` Values

**Booking Lifecycle:**
- `BOOKING_CREATED`
- `BOOKING_CONFIRMED`
- `BOOKING_ARRIVED`
- `SERVICE_STARTED`
- `SERVICE_COMPLETED`
- `BOOKING_COMPLETED`
- `BOOKING_CANCELLED`
- `BOOKING_RESCHEDULED`

**Operational State Transitions:**
- `OPERATIONAL_STATE_CHANGED` // upcoming → due_soon → grace_period → needs_resolution → resolved
- `GRACE_PERIOD_STARTED`
- `GRACE_PERIOD_EXPIRED`

**Resolution Actions:**
- `BOOKING_MARKED_MISSED`
- `BOOKING_RESOLVED` // with outcome and reason
- `FOLLOW_UP_INITIATED`
- `FOLLOW_UP_SENT`
- `CUSTOMER_RESPONDED`
- `RESCHEDULE_REQUESTED`

**System Actions:**
- `REMINDER_SENT`
- `AUTO_APPROVED`
- `PAYMENT_EXPIRED`

### Actor Types

- `CUSTOMER`
- `SPECIALIST`
- `STAFF`
- `PROVIDER`
- `SYSTEM`

### Example Activity History

```
2026-10-06 10:00  BOOKING_CREATED           actor: CUSTOMER
2026-10-06 10:01  BOOKING_CONFIRMED         actor: SYSTEM
2026-10-06 13:00  REMINDER_SENT             actor: SYSTEM
2026-10-06 13:30  REMINDER_SENT             actor: SYSTEM
2026-10-06 13:45  REMINDER_SENT             actor: SYSTEM
2026-10-06 14:00  GRACE_PERIOD_STARTED      actor: SYSTEM
2026-10-06 14:15  GRACE_PERIOD_EXPIRED      actor: SYSTEM
2026-10-06 14:17  BOOKING_MARKED_MISSED     actor: STAFF, reason: CUSTOMER_DID_NOT_ARRIVE
2026-10-06 14:17  BOOKING_RESOLVED          actor: STAFF, outcome: NO_SHOW
2026-10-06 14:18  FOLLOW_UP_INITIATED      actor: STAFF
2026-10-06 14:20  FOLLOW_UP_SENT            actor: SYSTEM
```

---

## Notification Ladder

### Default Schedule (Provider-Configurable)

For a 2:00 PM appointment:

| Time Before | Customer Experience |
|-------------|---------------------|
| 60 minutes  | "Your appointment is in 1 hour." |
| 30 minutes  | "Your appointment is coming up in 30 minutes." |
| 15 minutes  | "Your appointment is in 15 minutes. We're looking forward to seeing you." |
| 5 minutes   | "**Your appointment starts in 5 minutes.** Please make your way to the location." |
| 0 minutes   | "Your appointment has started. We're ready for you." Buttons: [I'm on my way] [I need to reschedule] |

### Context-Aware Scheduling

**Short service (20 min):**
- 15m, 5m

**30-minute service:**
- 15m, 5m

**2-hour service:**
- 2h, 1h, 30m, 15m, 5m

### Notification Policy Structure

```php
// provider_booking_config.notification_policy
[
    'enabled' => true,
    'reminder_schedule' => [
        ['minutes_before' => 60, 'enabled' => true],
        ['minutes_before' => 30, 'enabled' => true],
        ['minutes_before' => 15, 'enabled' => true],
        ['minutes_before' => 5, 'enabled' => true],
    ],
    'grace_period_minutes' => 15,
    'channels' => ['email', 'sms'], // future: push, whatsapp
]
```

---

## Scheduled Job: Booking Time State Processor

### New Console Command

```php
// app/Console/Commands/ProcessBookingTimeStates.php
class ProcessBookingTimeStates extends Command
{
    protected $signature = 'bookings:process-time-states';

    public function handle()
    {
        // 1. Update operational_state to due_soon (30 min before)
        Booking::where('status', 'confirmed')
            ->where('operational_state', 'upcoming')
            ->whereRaw("CONCAT(date, ' ', time) <= ?", [now()->addMinutes(30)->toDateTimeString()])
            ->chunk(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $this->transitionOperationalState($booking, 'due_soon');
                }
            });

        // 2. Start grace periods at scheduled_at
        Booking::where('status', 'confirmed')
            ->where('operational_state', '!=', 'grace_period')
            ->whereRaw("CONCAT(date, ' ', time) <= ?", [now()->toDateTimeString()])
            ->whereNull('grace_period_started_at')
            ->chunk(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $this->startGracePeriod($booking);
                }
            });

        // 3. Move to needs_resolution when grace expires
        Booking::where('operational_state', 'grace_period')
            ->where('grace_period_ends_at', '<=', now())
            ->chunk(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $this->transitionOperationalState($booking, 'needs_resolution');
                }
            });

        // 4. Schedule reminder notifications
        $this->scheduleReminderNotifications();
    }

    private function transitionOperationalState(Booking $booking, string $newState): void
    {
        $booking->update(['operational_state' => $newState]);

        BookingActivity::create([
            'booking_id' => $booking->id,
            'type' => 'OPERATIONAL_STATE_CHANGED',
            'title' => "Operational state changed to {$newState}",
            'actor_type' => 'SYSTEM',
            'data' => ['previous_state' => $booking->operational_state, 'new_state' => $newState],
        ]);
    }

    private function startGracePeriod(Booking $booking): void
    {
        $gracePeriodMinutes = $this->getGracePeriodMinutes($booking);
        $graceEndsAt = now()->addMinutes($gracePeriodMinutes);

        $booking->update([
            'operational_state' => 'grace_period',
            'grace_period_started_at' => now(),
            'grace_period_ends_at' => $graceEndsAt,
        ]);

        BookingActivity::create([
            'booking_id' => $booking->id,
            'type' => 'GRACE_PERIOD_STARTED',
            'title' => 'Grace period started',
            'actor_type' => 'SYSTEM',
        ]);

        // Schedule grace expiry notification
        $this->scheduleGraceExpiryNotification($booking, $graceEndsAt);
    }
}
```

### Schedule in `routes/console.php`

```php
// Process booking time states every minute
Schedule::command('bookings:process-time-states')
    ->everyMinute()
    ->name('booking.time-states')
    ->withoutOverlapping();
```

---

## Service Layer Enhancements

### BookingService::transitionStatus() Updates

Add `rescheduled` to the state machine (operational state is handled separately by scheduler):

```php
$allowedTransitions = [
    // ... existing transitions ...
    'confirmed' => ['arrived', 'cancelled', 'no_show'],
    'arrived' => ['waiting', 'in_progress', 'cancelled', 'no_show'],
    'waiting' => ['in_progress', 'cancelled', 'no_show'],
    'in_progress' => ['completed'],
    'completed' => [],
    'cancelled' => [],
    'no_show' => [],
];
```

**Note:** Grace period transitions are NOT in the status state machine. They're handled by the scheduler updating `operational_state`.

### New Resolution Methods

```php
public function resolveBooking(
    Booking $booking,
    string $outcome,
    string $reasonCode,
    ?string $notes = null,
    ?string $actorType = null,
    ?string $actorId = null,
    bool $followUpRequired = false,
    ?Carbon $followUpAt = null
): Booking {
    // Map outcome to booking status
    $statusMap = [
        'NO_SHOW' => 'no_show',
        'CANCELLED' => 'cancelled',
        'RESCHEDULED' => 'rescheduled',
        'COMPLETED_LATE' => 'completed',
        'CUSTOMER_ARRIVED' => 'arrived',
    ];

    $booking->update([
        'status' => $statusMap[$outcome],
        'operational_state' => 'resolved',
    ]);

    // Create resolution record
    $resolution = BookingResolution::create([
        'booking_id' => $booking->id,
        'outcome' => $outcome,
        'reason_code' => $reasonCode,
        'notes' => $notes,
        'actor_type' => $actorType ?? 'STAFF',
        'actor_id' => $actorId,
        'resolved_at' => now(),
        'follow_up_required' => $followUpRequired,
        'follow_up_at' => $followUpAt,
        'follow_up_status' => $followUpRequired ? 'PENDING' : null,
    ]);

    // Create activity record
    BookingActivity::create([
        'booking_id' => $booking->id,
        'type' => 'BOOKING_RESOLVED',
        'title' => "Booking resolved as {$outcome}",
        'description' => $notes,
        'actor_type' => $actorType ?? 'STAFF',
        'actor_id' => $actorId,
        'data' => [
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'resolution_id' => $resolution->id,
        ],
    ]);

    return $booking->fresh();
}

public function rescheduleBooking(
    Booking $originalBooking,
    array $newBookingData,
    ?string $actorType = null,
    ?string $actorId = null
): array {
    DB::transaction(function () use ($originalBooking, $newBookingData, $actorType, $actorId) {
        // Mark original as rescheduled
        $originalBooking->update([
            'status' => 'rescheduled',
            'operational_state' => 'resolved',
        ]);

        // Create resolution for original
        BookingResolution::create([
            'booking_id' => $originalBooking->id,
            'outcome' => 'RESCHEDULED',
            'reason_code' => 'CUSTOMER_REQUESTED_RESCHEDULE',
            'actor_type' => $actorType ?? 'STAFF',
            'actor_id' => $actorId,
            'resolved_at' => now(),
        ]);

        // Create new booking
        $newBookingData['rescheduled_from_booking_id'] = $originalBooking->id;
        $newBooking = $this->createGuestBooking($newBookingData);

        // Link activity
        BookingActivity::create([
            'booking_id' => $originalBooking->id,
            'type' => 'BOOKING_RESCHEDULED',
            'title' => 'Booking rescheduled',
            'actor_type' => $actorType ?? 'STAFF',
            'actor_id' => $actorId,
            'data' => [
                'new_booking_id' => $newBooking->id,
            ],
        ]);

        return ['original' => $originalBooking, 'new' => $newBooking];
    });
}

public function initiateFollowUp(BookingResolution $resolution, Carbon $followUpAt): BookingResolution
{
    $resolution->update([
        'follow_up_required' => true,
        'follow_up_at' => $followUpAt,
        'follow_up_status' => 'PENDING',
    ]);

    BookingActivity::create([
        'booking_id' => $resolution->booking_id,
        'type' => 'FOLLOW_UP_INITIATED',
        'title' => 'Follow-up initiated',
        'actor_type' => 'STAFF',
        'data' => [
            'resolution_id' => $resolution->id,
            'follow_up_at' => $followUpAt,
        ],
    ]);

    return $resolution->fresh();
}
```

---

## UI Implementation Notes

### UI Renders from Database (No Calculation Needed)

Since `operational_state` is persisted by the scheduler, the UI simply queries and renders:

```php
// GET /api/v1/bookings/by-operational-state?state=grace_period&location_id=xxx
public function getByOperationalState(Request $request)
{
    $state = $request->query('state');
    $locationId = $request->query('location_id');

    $bookings = Booking::where('location_id', $locationId)
        ->where('operational_state', $state)
        ->where('date', '>=', now()->subDays(7)->toDateString())
        ->with(['customer', 'service', 'specialist'])
        ->orderBy('date')
        ->orderBy('time')
        ->get();

    return response()->json($bookings);
}
```

### Yesterday's Needs Resolution View

```php
// GET /api/v1/bookings/needs-resolution?location_id=xxx
public function getNeedsResolution(Request $request)
{
    $locationId = $request->query('location_id');

    $bookings = Booking::where('location_id', $locationId)
        ->where('operational_state', 'needs_resolution')
        ->where('date', '<', now()->toDateString()) // Yesterday or earlier
        ->with(['customer', 'service', 'specialist'])
        ->orderBy('date', 'desc')
        ->orderBy('time', 'desc')
        ->get();

    return response()->json($bookings);
}
```

### Overdue Minutes Helper

```php
// app/Helpers/BookingOperationalHelper.php
class BookingOperationalHelper
{
    public static function getOverdueMinutes(Booking $booking): ?int
    {
        if ($booking->operational_state !== 'grace_period' || !$booking->grace_period_started_at) {
            return null;
        }
        return now()->diffInMinutes($booking->grace_period_started_at);
    }

    public static function getGraceRemainingMinutes(Booking $booking): ?int
    {
        if ($booking->operational_state !== 'grace_period' || !$booking->grace_period_ends_at) {
            return null;
        }
        return now()->diffInMinutes($booking->grace_period_ends_at, false);
    }
}
```

---

## Migration Plan

### Phase 1: Database Schema
1. Add `operational_state` to `bookings` table (default: 'upcoming')
2. Add `grace_period_started_at`, `grace_period_ends_at` to `bookings`
3. Add `rescheduled_from_booking_id` to `bookings` (foreign key to self)
4. Create `booking_resolutions` table
5. Add `notification_policy` to `provider_booking_config`

### Phase 2: Service Layer
1. Add `rescheduled` to `BookingService::transitionStatus()` state machine
2. Add `resolveBooking()` method with outcome/reason/follow-up tracking
3. Add `rescheduleBooking()` method with honest history tracking
4. Add `initiateFollowUp()` method
5. Create `BookingResolution` model

### Phase 3: Scheduled Job
1. Create `ProcessBookingTimeStates` command
2. Implement operational state transitions (upcoming → due_soon → grace_period → needs_resolution)
3. Add to Laravel scheduler (every minute)
4. Test with time-travel scenarios

### Phase 4: Notification System
1. Add notification policy structure to config defaults
2. Create reminder scheduling logic in scheduler
3. Integrate with existing NotificationJob system
4. Add grace period expiry notification

### Phase 5: UI Layer
1. Create API endpoints for operational state filtering
2. Update dashboard to group by operational state
3. Add "Needs Resolution" view for yesterday's unresolved bookings
4. Add resolution action buttons with outcome/reason selection
5. Add follow-up workflow UI

---

## Testing Strategy

### Unit Tests
- State transition validation
- Grace period calculation
- Time state calculation edge cases (midnight, timezone)

### Integration Tests
- Scheduled job transitions bookings correctly
- Notifications scheduled at right times
- Resolution workflow end-to-end

### Time-Travel Tests
- Use Carbon::setTestNow() to simulate:
  - Booking crossing scheduled_at
  - Grace period expiry
  - Late arrival after grace expiry

---

## Open Questions

1. **Grace period duration**: Default 15 minutes? Make configurable per provider/location?
2. **Notification channels**: Currently email/SMS. Add push notifications later?
3. **Rescheduling workflow**: When "needs_resolution" → "rescheduled", create new booking or modify existing?
4. **Analytics**: What metrics to track from BookingActivity? (no-show rate by time of day, by specialist, etc.)
