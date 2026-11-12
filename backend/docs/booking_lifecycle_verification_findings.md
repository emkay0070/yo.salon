# Booking Lifecycle Verification Findings

**Date**: October 6, 2026
**Purpose**: Verify 7 critical questions before Phase 1 implementation

---

## 1. Is `transitionStatus()` genuinely safe to become the single status writer?

**Finding: NO - Circular delegation pattern**

`transitionStatus()` delegates to `cancelBooking()` and `completeBooking()` for those states:

```php
// BookingService::transitionStatus() lines 583-588
if ($newStatus === 'completed') {
    return $this->completeBooking($booking);
}
if ($newStatus === 'cancelled') {
    return $this->cancelBooking($booking);
}
```

But those methods do direct `update()`:

```php
// BookingService::cancelBooking() line 512
$booking->update(['status' => 'cancelled']);

// BookingService::completeBooking() line 525
$booking->update(['status' => 'completed']);
```

**Problem**: If we make `transitionStatus()` the single writer, we need to:
1. Move the extra logic (visit count increment, cancellation policy check) into `transitionStatus()`
2. Remove the delegation pattern
3. Ensure all callers use `transitionStatus()` directly

**Recommendation**: Refactor during Phase 1 - move special-case logic into `transitionStatus()` and remove the delegate methods.

---

## 2. What exactly does the existing `BookingActivity` record today?

**Finding: Only 2 activity types, both event-driven**

**Current writers:**

1. **AddBookingActivity listener** (`app/Listeners/AddBookingActivity.php`)
   - Triggered by: `BookingCreated` event
   - Type: `'booking_created'`
   - Actor: `'customer'`
   - Data: customer_name, service_names, date, time

2. **AddPaymentActivity listener** (`app/Listeners/AddPaymentActivity.php`)
   - Triggered by: `PaymentConfirmed` event
   - Type: `'payment_confirmed'`
   - Actor: `'system'`
   - Data: amount, payment_method, transaction_id

**Missing activity logging:**
- No activity for: arrival, waiting, in_progress, completion, cancellation, no_show
- No activity for status transitions via `transitionStatus()`
- No activity for auto-approval
- No structured reason codes (type values are lowercase strings)

**Known issue**: AppServiceProvider notes that listeners execute twice due to both `handle()` and `__invoke()` being registered.

**Recommendation**: Add activity logging to `transitionStatus()` to capture all status changes with actor tracking.

---

## 3. How does `FloorController` currently move `confirmed → arrived → waiting → in_progress → completed`?

**Finding: Correct flow, but missing activity logging**

**FloorController endpoints:**

```php
// arrive() - lines 39-55
public function arrive(Request $request, Booking $booking): JsonResponse
{
    $updated = $this->bookingService->transitionStatus($booking, 'arrived');
    // Sets arrived_at atomically
}

// wait() - lines 63-89
public function wait(Request $request, Booking $booking): JsonResponse
{
    $updated = $this->bookingService->transitionStatus($booking, 'waiting');
    // Optionally sets queue_position
}

// startSession() - lines 96-116
public function startSession(Request $request, Booking $booking, string $bookingServiceId)
{
    $updatedBookingService = $this->sessionService->startSession($bookingService, $actor);
    // SessionService calls transitionStatus(booking, 'in_progress')
}

// completeSession() - lines 123-143
public function completeSession(Request $request, Booking $booking, string $bookingServiceId)
{
    $updatedBookingService = $this->sessionService->completeSession($bookingService, $actor);
    // SessionService calls transitionStatus(booking, 'completed') when all services done
}
```

**SessionService flow:**
```php
// SessionService::startSession() lines 104-106
if ($booking->status !== 'in_progress') {
    app(BookingService::class)->transitionStatus($booking, 'in_progress');
}

// SessionService::completeSession() lines 179-181
if ($allCompleted && $booking->status !== 'completed') {
    app(BookingService::class)->transitionStatus($booking, 'completed');
}
```

**Recommendation**: Flow is correct. Add activity logging to `transitionStatus()` to capture these transitions.

---

## 4. What does `booking.status` currently contain in the real database?

**Finding: Cannot verify production data - only factory defaults**

**Factory default** (`database/factories/BookingFactory.php` line 28):
```php
'status' => 'confirmed',
```

**State machine defines 10 states:**
- pending
- pending_payment
- reserved
- confirmed
- arrived
- waiting
- in_progress
- completed
- cancelled
- no_show

**No database query performed** - we only have factory defaults, not actual production data.

**Recommendation**: If production data verification is critical, run a query:
```sql
SELECT DISTINCT status, COUNT(*) FROM bookings GROUP BY status;
```

---

## 5. Exactly which views are filtering `TODAY` and causing yesterday's bookings to disappear?

**Finding: 4 controllers filter by TODAY**

**FloorController (3 endpoints):**
```php
// queue() line 215
->whereDate('date', now()->toDateString())

// live() line 251
->whereDate('date', now()->toDateString())

// snapshot() line 282
->whereDate('date', now()->toDateString())
```

**DashboardController:**
```php
// liveStats() line 42
$today = Carbon::today()->toDateString();
// Stats calculated for today only
```

**BookingController:**
```php
// index() lines 63-65
if ($date === 'today') {
    $query->whereDate('date', now()->toDateString());
}
```

**SpecialistCalendarService:**
```php
// getWeekCalendar() lines 127-135
// Shows bookings for a week range, NOT filtered by today
// But excludes cancelled/no_show: ->whereNotIn('status', ['cancelled', 'no_show'])
```

**The problem**: A booking from yesterday with status='confirmed' (never resolved) will:
- Be invisible in FloorController (all 3 endpoints)
- Be invisible in DashboardController stats
- Be invisible in BookingController when date=today
- Be visible in SpecialistCalendarService (if within week range)

**Recommendation**: Add new endpoint for "Needs Attention" view that queries yesterday's unresolved bookings.

---

## 6. How is `date + time` interpreted with the provider/location timezone?

**Finding: Timezone resolved from context, booking.time stored as HH:MM string**

**Timezone sources:**
```php
// BookingService lines 213, 348
$timezone = $location?->timezone ?? $provider?->timezone ?? 'Africa/Kampala';

// AvailabilityEngine line 286
$timezone = $ctx->timezone ?? $location?->timezone ?? $provider->timezone ?? 'Africa/Kampala';

// CustomerAvailabilityService line 38
$timezone = $location?->timezone ?? $provider->timezone ?? 'Africa/Kampala';
```

**Booking storage:**
```php
// Booking model
'date' => 'date',      // YYYY-MM-DD
'time' => 'string',    // HH:MM format (no timezone)
```

**Timezone parsing example (CustomerBookingService line 209):**
```php
$bookingDateTime = Carbon::parse($booking->date . ' ' . $booking->time);
// Uses application timezone (likely UTC), not provider timezone
```

**Test confirms this pattern** (`tests/Feature/BookingTimezoneDisplayTest.php`):
```php
// Book at 14:00 in Africa/Kampala timezone
// Verify time is stored as HH:MM string (no timezone)
$this->assertEquals('14:00', $booking->time);
```

**Grace period calculation issue:**
```php
// WRONG (current pattern in some places):
$graceEndsAt = now()->addMinutes($gracePeriodMinutes);

// CORRECT (what we need):
$scheduledAt = Carbon::parse("{$booking->date} {$booking->time}", $provider->timezone);
$graceEndsAt = $scheduledAt->addMinutes($gracePeriodMinutes);
```

**Recommendation**: When implementing grace period logic, always parse `date + time` in the provider's timezone before calculating offsets.

---

## 7. Are the four direct status updates truly violations, or are some legitimate special cases?

**Finding: Not violations - they're the actual implementation that transitionStatus delegates to**

**Direct update locations:**

1. **BookingService::cancelBooking()** (line 512)
   - Called BY `transitionStatus()` (circular delegation)
   - Extra logic: none currently
   - **Status**: Should be inlined into `transitionStatus()`

2. **BookingService::completeBooking()** (line 525)
   - Called BY `transitionStatus()` (circular delegation)
   - Extra logic: increments customer visit count
   - **Status**: Move visit count logic into `transitionStatus()`, then inline

3. **CustomerBookingService::cancelBooking()** (line 217)
   - Separate service for customer portal
   - Extra logic: validates 24-hour cancellation window
   - **Status**: Legitimate special case - should call `transitionStatus()` after validation

4. **SpecialistPortalController** (line 1112)
   - Bulk no-show marking in a loop
   - Extra logic: error handling per booking
   - **Status**: Legitimate special case - should call `transitionStatus()` in loop

**The real issue**: The circular delegation in `transitionStatus()` makes it impossible to be the single writer without refactoring.

**Recommendation**: 
- Refactor `transitionStatus()` to handle all special cases inline
- Remove `cancelBooking()` and `completeBooking()` as separate methods
- Update `CustomerBookingService::cancelBooking()` to call `transitionStatus()`
- Update `SpecialistPortalController` to call `transitionStatus()` in loop

---

## Summary

| Question | Finding | Action Needed |
|----------|---------|---------------|
| 1. transitionStatus() as single writer? | NO - circular delegation | Refactor during Phase 1 |
| 2. What BookingActivity records? | Only 2 types (created, payment) | Add logging to transitionStatus() |
| 3. FloorController flow? | Correct, missing activity | Add activity logging |
| 4. Actual database status values? | Unknown (only factory defaults) | Optional: query production |
| 5. Views filtering TODAY? | 4 controllers (Floor, Dashboard, Booking, SpecialistCalendar) | Add "Needs Attention" endpoint |
| 6. Date+time timezone handling? | Resolved from context, stored as HH:MM | Parse in provider timezone for grace periods |
| 7. Direct updates violations? | Not violations - circular delegation pattern | Refactor delegation, update callers |

**Critical path for Phase 1:**
1. Refactor `transitionStatus()` to be the single writer
2. Add activity logging to `transitionStatus()`
3. Add `operational_state` column and grace period columns
4. Create scheduler command for time-based state transitions
5. Add "Needs Attention" endpoint for yesterday's unresolved bookings
