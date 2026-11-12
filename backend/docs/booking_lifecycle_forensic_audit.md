# Booking Lifecycle Forensic Audit

**Date**: October 6, 2026
**Purpose**: Audit existing Yo.Salon booking infrastructure before implementing new lifecycle engine

---

## Executive Summary

Yo.Salon has a solid booking foundation with:
- String-based status field (flexible for new states)
- Operational floor tracking (arrived_at, waiting_since, queue_position)
- BookingActivity table for event history
- FloorController for operational lifecycle
- Scheduler with auto-approval polling

**Critical gaps for the proposed lifecycle:**
- No persisted operational state (all time-state logic is UI-calculated)
- No grace period tracking
- No resolution tracking (no_show exists but unstructured)
- Yesterday's bookings disappear from operational views
- No reminder scheduling (BookingReminder exists but not scheduled)
- No notification policy in config

---

## 1. Booking Model/Schema/Status Values

### Model: `app/Models/Booking.php`

**Status field:**
```php
protected $fillable = [
    'status', // string, supports arbitrary values
    // ...
];
```

**Current status values (from state machine):**
- `pending` - Initial state
- `pending_payment` - Awaiting deposit/payment
- `reserved` - Slot locked, awaiting approval/payment
- `confirmed` - Appointment confirmed
- `arrived` - Customer physically arrived
- `waiting` - In waiting queue
- `in_progress` - Service being delivered
- `completed` - Service completed
- `cancelled` - Booking cancelled
- `no_show` - Customer did not attend

**Operational floor tracking (already exists):**
```php
'arrived_at'      => 'datetime', // When client physically arrived
'waiting_since'   => 'datetime', // When placed in waiting queue
'queue_position'  => 'integer',  // Queue position (1 = next up)
```

**Date/time representation:**
```php
'date' => 'date',      // YYYY-MM-DD
'time' => 'string',    // HH:MM format
```
- No timezone field on bookings
- Uses application timezone (likely UTC)
- `whereDate()` used extensively for filtering

**Key finding:** Status field is already flexible (string, not enum). Adding new statuses requires no schema change.

---

## 2. BookingService Lifecycle Transitions

### File: `app/Services/BookingService.php`

**State machine (lines 561-572):**
```php
$allowedTransitions = [
    'pending'         => ['confirmed', 'cancelled'],
    'pending_payment' => ['confirmed', 'cancelled'],
    'reserved'        => ['pending', 'pending_payment', 'confirmed', 'cancelled'],
    'confirmed'       => ['arrived', 'in_progress', 'cancelled', 'no_show'],
    'arrived'         => ['waiting', 'in_progress', 'cancelled', 'no_show'],
    'waiting'         => ['in_progress', 'cancelled', 'no_show'],
    'in_progress'     => ['completed'],
    'completed'       => [],
    'cancelled'       => [],
    'no_show'         => [],
];
```

**Operational timestamp handling (lines 593-597):**
```php
if ($newStatus === 'arrived') {
    $updatePayload['arrived_at'] = now();
} elseif ($newStatus === 'waiting') {
    $updatePayload['waiting_since'] = now();
}
```

**Auto-approval polling (lines 664-722):**
- `processAutoApprovals()` called by scheduler every 15 minutes
- Checks if pending bookings exceed `auto_approve_after_hours` threshold
- Transitions pending → confirmed if eligible

**Key finding:** Grace period transitions are NOT in the state machine. Operational state transitions would need to be separate from status transitions.

---

## 3. BookingActivity Model/Schema + Current Writers

### Migration: `2026_07_31_190064__create_booking_activities_table.php`

**Schema:**
```php
Schema::create('booking_activities', function (Blueprint $table) {
    $table->id();
    $table->uuid('booking_id');
    $table->string('type'); // created, payment_confirmed, confirmed, etc.
    $table->string('title');
    $table->text('description')->nullable();
    $table->json('data')->nullable();
    $table->uuid('actor_id')->nullable();
    $table->string('actor_type')->nullable(); // user, customer, staff, system
    $table->timestamps();
});
```

**Current writers:**

1. **AddBookingActivity listener** (`app/Listeners/AddBookingActivity.php`)
   - Triggered by: `BookingCreated` event
   - Type: `'booking_created'`
   - Actor: `'customer'`

2. **AddPaymentActivity listener** (`app/Listeners/AddPaymentActivity.php`)
   - Triggered by: `PaymentConfirmed` event
   - Type: `'payment_confirmed'`
   - Actor: `'system'`

**Missing activity logging:**
- No activity for: arrival, waiting, in_progress, completion, cancellation, no_show
- No structured reason codes (type values are lowercase strings)
- No activity for status transitions via `transitionStatus()`

**Key finding:** BookingActivity table structure is perfect for the proposed system. Need to add activity logging to all status transitions.

---

## 4. Arrival/Check-in Implementation

### File: `app/Http/Controllers/Api/V1/FloorController.php`

**Arrival endpoint (lines 39-55):**
```php
public function arrive(Request $request, Booking $booking): JsonResponse
{
    $updated = $this->bookingService->transitionStatus($booking, 'arrived');
    // Returns arrived_at timestamp
}
```

**Waiting endpoint (lines 63-89):**
```php
public function wait(Request $request, Booking $booking): JsonResponse
{
    $updated = $this->bookingService->transitionStatus($booking, 'waiting');
    // Optionally sets queue_position
}
```

**Key finding:** Arrival is handled via `transitionStatus()` which atomically sets `arrived_at`. This pattern should be preserved.

---

## 5. Completion Implementation

### File: `app/Services/BookingService.php`

**Direct completion (lines 522-528):**
```php
public function completeBooking(Booking $booking): Booking
{
    $booking->update(['status' => 'completed']);
    // Increments customer visit count if registered
}
```

### File: `app/Services/SessionService.php`

**Session completion (lines 143-181):**
```php
public function completeSession(BookingService $bookingService, $actor)
{
    // Completes individual BookingService record
    // When all services complete, transitions booking to 'completed':
    if ($allCompleted && $booking->status !== 'completed') {
        app(BookingService::class)->transitionStatus($booking, 'completed');
    }
}
```

**Key finding:** Completion happens at two levels - individual service sessions and overall booking. Both should create BookingActivity records.

---

## 6. Cancellation Implementation

### File: `app/Services\BookingService.php`

**Provider cancellation (lines 510-514):**
```php
public function cancelBooking(Booking $booking): Booking
{
    $booking->update(['status' => 'cancelled']);
    return $booking->fresh();
}
```

### File: `app/Services\CustomerBookingService.php`

**Customer cancellation (lines 201-220):**
```php
public function cancelBooking(string $bookingId): Booking
{
    // Validates 24-hour cancellation window
    $booking->update(['status' => 'cancelled']);
}
```

**Key finding:** Cancellation does NOT create BookingActivity. Should add activity logging with reason code.

---

## 7. Existing No-Show Implementation

### Status in state machine
- `no_show` is a valid terminal status
- Can transition from: `confirmed`, `arrived`, `waiting`

### Usage in codebase:

**CancellationRulesEngine.php (lines 105-137):**
```php
public function checkNoShowStatus(string $customerId, string $providerId, ?string $locationId = null): array
{
    // Counts no_shows in last 90 days
    $noShowQuery = Booking::where('customer_id', $customerId)
        ->where('status', 'no_show')
        ->where('date', '>=', Carbon::now()->subDays(90));
}
```

**OperationalAnalyticsService.php (lines 64, 84):**
```php
$noShowBookings = $bookings->where('status', 'no_show')->count();
$noShowRate = ($confirmedBookings > 0) ? ($noShowBookings / $confirmedBookings * 100) : 0;
```

**SpecialistPortalController.php (lines 1112-1113):**
```php
// Bulk no-show marking
$booking->update(['status' => 'no_show']);
```

**Key finding:** `no_show` exists but has no structured resolution tracking. No reason codes, no actor tracking, no follow-up capability.

---

## 8. Existing Scheduler/Commands/Jobs

### File: `routes/console.php`

**Current scheduled tasks:**
```php
// Daily
Schedule::command('trending:refresh')->daily();

// Hourly
Schedule::job(new ProcessSubscriptionRenewals)->hourly();
Schedule::job(new ProcessScheduledPayouts)->hourly();
Schedule::job(new ReconcileProcessingPayouts)->hourly();

// Every 15 minutes
Schedule::call(function () {
    app(BookingService::class)->processAutoApprovals();
})->everyFifteenMinutes()->name('booking.auto-approval')->withoutOverlapping();
```

### Commands exist but NOT scheduled:

**ProcessNotifications** (`app/Console/Commands/ProcessNotifications.php`)
- Processes pending NotificationJob records
- Not scheduled in console.php

**ExpirePendingPayments** (`app/Console/Commands/ExpirePendingPayments.php`)
- Expires payment requests past expiry time
- Not scheduled in console.php

**Key finding:** No time-based booking state processor exists. Scheduler infrastructure is ready but no command for operational state transitions.

---

## 9. Notification/Reminder Infrastructure

### NotificationJob model (`app/Models/NotificationJob.php`)

**Schema:**
```php
- type (email, sms)
- recipient
- subject
- message
- data
- status (pending, sent, failed, queued)
- attempts
- scheduled_at
- sent_at
- error_message
- related_type (booking, etc.)
- related_id
```

**ProcessNotifications command:**
- Processes pending NotificationJob records
- Calls NotificationService to send

### BookingReminder notification (`app/Notifications/BookingReminder.php`)

**Exists but NOT scheduled:**
- Email-only reminder
- Contains booking details (service, date, time, stylist)
- Has reschedule button
- No scheduling logic exists to trigger it

### Notification policy

**NOT present in config:**
- `ProviderBookingConfig` has cancellation_policy, booking_policy
- No notification_policy field
- No reminder schedule configuration

**Key finding:** Notification infrastructure exists (NotificationJob, ProcessNotifications, BookingReminder) but has no scheduling logic or policy configuration.

---

## 10. Calendar/Dashboard Time-State Logic

### FloorController operational views

**snapshot() (lines 276-306):**
```php
$confirmed  = (clone $baseQuery())->where('status', 'confirmed')->orderBy('time')->get();
$arrived    = (clone $baseQuery())->where('status', 'arrived')->orderBy('arrived_at')->get();
$waiting    = (clone $baseQuery())->where('status', 'waiting')->orderBy('waiting_since')->get();
$inProgress = (clone $baseQuery())->where('status', 'in_progress')->get();
```
- Groups by booking.status
- Filters by TODAY ONLY: `whereDate('date', now()->toDateString())`

**queue() (lines 208-236):**
```php
->whereIn('status', ['arrived', 'waiting'])
->whereDate('date', now()->toDateString())
```

**live() (lines 244-268):**
```php
->where('status', 'in_progress')
->whereDate('date', now()->toDateString())
```

### SpecialistCalendarService

**Week calendar (lines 127-135):**
```php
$bookingQuery = Booking::where('specialist_id', $specialist->id)
    ->whereBetween('date', [$weekStart, $weekEnd])
    ->whereNotIn('status', ['cancelled', 'no_show']) // Excludes terminal states
```

### DashboardController

**liveStats() (lines 31-72):**
```php
// Calls processAutoApprovals during polling
$autoApprovalResult = $this->bookingService->processAutoApprovals($provider->id);

// Stats for TODAY only
$today = Carbon::today()->toDateString();
```

**Key finding:** All operational views filter by TODAY. No operational state concept (upcoming, due_soon, grace_period, needs_resolution). All grouping is by booking.status.

---

## 11. Booking Date/Time/Timezone Representation

### Booking model
```php
'date' => 'date',      // Carbon date cast
'time' => 'string',    // HH:MM format
```

### Usage patterns
```php
// Combining date + time for comparison
CONCAT(date, ' ', time) <= now()

// Date filtering
whereDate('date', now()->toDateString())
whereDate('date', '>=', now()->subDays(90))

// Time comparison
where('time', '>=', $todayTime)
```

### Timezone
- No timezone field on bookings
- Uses application timezone (likely UTC)
- All Carbon operations use app timezone

**Key finding:** Date/time representation is simple (date + string time). Grace period calculation would need to parse `date + time` into a Carbon instance.

---

## 12. Existing Polling Mechanism

### DashboardController polling

**liveStats() endpoint:**
```php
// Called by frontend every 30 seconds
// Cache: 30 seconds
Cache::put($cacheKey, $stats, 30);
```

**During each poll:**
```php
// Calls processAutoApprovals
$autoApprovalResult = $this->bookingService->processAutoApprovals($provider->id);
```

**Key finding:** Only auto-approval is polled. No polling for time-based state transitions. The proposed scheduler would be a better approach than polling.

---

## 13. What Happens to Yesterday's Bookings

### FloorController views
```php
// All filter by TODAY ONLY
->whereDate('date', now()->toDateString())
```

### DashboardController
```php
// Stats for TODAY only
$today = Carbon::today()->toDateString();
```

### SpecialistCalendarService
```php
// Shows bookings for a week range
->whereBetween('date', [$weekStart, $weekEnd])
```
- Excludes cancelled/no_show
- But does show past bookings within the week

### The problem
- Yesterday's bookings exist in database
- They are NOT visible in operational floor views (FloorController)
- They are NOT visible in dashboard stats
- They ARE visible in specialist calendar (if within week range)
- If a booking from yesterday was never resolved, it disappears from operational views

**Example scenario:**
- Yesterday 14:00: Booking confirmed
- Yesterday 14:15: Grace expires (not tracked)
- Yesterday 14:20: Staff didn't mark no-show
- Today: Booking exists in DB with status='confirmed'
- Today: Booking is NOT visible in any operational view
- Today: No way to know this needs resolution

**Key finding:** This is exactly the problem the proposed lifecycle solves. Yesterday's unresolved bookings need to be surfaced in a "Needs Attention" view.

---

## 14. Every Place That Directly Updates Booking.Status

### Direct update() calls

**BookingService::cancelBooking()**
```php
$booking->update(['status' => 'cancelled']);
```

**BookingService::completeBooking()**
```php
$booking->update(['status' => 'completed']);
```

**CustomerBookingService::cancelBooking()**
```php
$booking->update(['status' => 'cancelled']);
```

**SpecialistPortalController (bulk no-show)**
```php
$booking->update(['status' => 'no_show']);
```

**BookingPaymentService**
```php
$booking->update(['payment_status' => 'pending']);
```

**MTNMomoService**
```php
$booking->update(['payment_status' => 'paid']);
```

### Via transitionStatus() (central method)

**BookingService::transitionStatus()**
- Called by FloorController::arrive()
- Called by FloorController::wait()
- Called by SessionService::startSession()
- Called by SessionService::completeSession()
- Called by BookingService::processAutoApprovals()

### Test files
- Direct `update(['status' => ...])` in tests

**Key finding:** Status updates happen via two paths:
1. Direct `update()` (should be refactored to use transitionStatus)
2. `transitionStatus()` (central method, should be the only path)

---

## Recommendations for Implementation

### 1. Extend existing infrastructure (don't create parallel system)

**Keep:**
- Booking.status string field (add `rescheduled`)
- BookingActivity table (add more activity logging)
- transitionStatus() as central method
- Operational floor tracking (arrived_at, waiting_since)
- Scheduler infrastructure

**Add:**
- `operational_state` column to bookings
- Grace period columns (grace_period_started_at, grace_period_ends_at)
- `rescheduled_from_booking_id` for honest history
- BookingResolution table for structured outcomes
- Notification policy to provider config

### 2. Separate operational state from status

**Operational state transitions (scheduler):**
- upcoming → due_soon → grace_period → needs_resolution → resolved
- Persisted by scheduler, not calculated by UI
- Creates BookingActivity records

**Status transitions (human actions):**
- confirmed → arrived → waiting → in_progress → completed
- confirmed → cancelled / no_show
- Handled by transitionStatus()

### 3. Fix grace period calculation

**Wrong:**
```php
$graceEndsAt = now()->addMinutes($gracePeriodMinutes);
```

**Correct:**
```php
$scheduledAt = Carbon::parse("{$booking->date} {$booking->time}");
$graceEndsAt = $scheduledAt->addMinutes($gracePeriodMinutes);
```

### 4. Make scheduler idempotent

**Pattern:**
```php
Booking::where('status', 'confirmed')
    ->where('operational_state', 'upcoming')
    ->where('operational_state', '!=', 'grace_period') // Already processed
    ->whereRaw("CONCAT(date, ' ', time) <= ?", [now()->toDateTimeString()])
    ->chunk(100, function ($bookings) {
        // Process
    });
```

### 5. Allow late arrival from needs_resolution

**Add transition:**
```php
'needs_resolution' => ['arrived'], // Customer can still walk in
```

### 6. Separate notification processor from time-state processor

**Two separate commands:**
- `bookings:process-time-states` (operational state transitions)
- `bookings:schedule-reminders` (notification scheduling)

### 7. Add "Needs Attention" view for yesterday

**New endpoint:**
```php
GET /bookings/needs-resolution?location_id=xxx
```

**Query:**
```php
Booking::where('operational_state', 'needs_resolution')
    ->where('date', '<', now()->toDateString())
    ->with(['customer', 'service'])
    ->get();
```

### 8. Refactor direct status updates to use transitionStatus()

**Replace:**
```php
$booking->update(['status' => 'cancelled']);
```

**With:**
```php
$this->bookingService->transitionStatus($booking, 'cancelled');
```

---

## Migration Strategy

### Phase 1: Database (no data migration needed)
1. Add `operational_state` column (default: 'upcoming')
2. Add grace period columns
3. Add `rescheduled_from_booking_id`
4. Create `booking_resolutions` table
5. Add `notification_policy` to config

### Phase 2: Service layer
1. Add `rescheduled` to state machine
2. Add `resolveBooking()` method
3. Add `rescheduleBooking()` method
4. Refactor direct status updates to use transitionStatus()
5. Add activity logging to all status transitions

### Phase 3: Scheduler
1. Create `ProcessBookingTimeStates` command
2. Add to console.php (every minute)
3. Create `ScheduleReminderNotifications` command
4. Add to console.php

### Phase 4: UI
1. Add "Needs Attention" endpoint
2. Update FloorController to use operational_state
3. Add resolution action UI
4. Add follow-up workflow UI

---

## Conclusion

Yo.Salon's existing infrastructure is well-aligned with the proposed lifecycle architecture. The main gaps are:

1. **No persisted operational state** - all time-state logic is UI-calculated
2. **No grace period tracking** - needs columns + scheduler
3. **No resolution tracking** - needs BookingResolution table
4. **Yesterday's bookings disappear** - needs "Needs Attention" view
5. **No reminder scheduling** - needs scheduler + policy config

The implementation should extend existing patterns rather than create a parallel system. The transitionStatus() method, BookingActivity table, and scheduler infrastructure are solid foundations to build upon.
