# Booking Lifecycle Deep Dive Audit Report

**Date**: October 9, 2026  
**Scope**: Unresolved Behaviors from Previous Audit - Real Call Path Tracing  
**Type**: Read-Only Investigation  
**Focus**: Reserved lifecycle, hold duration, conflict protection, payment expiry, rescheduling atomicity, rejection mechanisms, and manual approval

---

## Executive Summary

This report traces the actual call paths for unresolved behaviors identified in the previous audit. The investigation focused on real-world execution flows rather than repeating previous verification work.

**Key Findings**:
- **Reserved lifecycle**: Payment does NOT transition booking from `reserved` to `confirmed` - missing critical transition
- **Hold duration**: Hardcoded to 10 minutes in BookingOrchestrator (not configurable)
- **Conflict protection**: Exact time match only - no interval overlap detection with buffers
- **Payment expiry**: Only expires PaymentRequest, does NOT transition booking status
- **Rescheduling**: NOT atomic - original booking cancelled before new booking created
- **Rejection mechanism**: Use `CANCELLED` status with `PROVIDER_UNAVAILABLE` reason code
- **Manual approval**: Auto-approval via scheduled command, no manual UI for pending bookings

**Critical Missing Behaviors**:
1. Payment confirmation does not transition `reserved` → `confirmed`
2. Payment expiry does not transition `reserved` → `cancelled`
3. No manual approval interface for pending bookings

---

## 1. Reserved Booking Lifecycle Trace

### 1.1 Customer Submission Path

**Entry Point**: `BookingController::store` or `BookingOrchestratorController::reserve`

**File**: `backend/app/Http/Controllers/Api/V1/BookingController.php`  
**Lines**: 222-223

```php
$result = app(\App\Services\BookingOrchestrator::class)->reserveAndBook($validated);
$booking = $result['booking'];
```

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 193-229

```php
return DB::transaction(function () use ($validatedData, $providerId, $locationId, $serviceId, $date, $time) {
    // 4. Allocate resources if service requires them
    $resourceAllocations = collect();
    try {
        $resourceAllocations = $this->resourceAllocator->allocateResources(
            serviceId: $serviceId,
            locationId: $locationId,
            date: CarbonImmutable::parse($date),
            startTime: $time,
            endTime: $this->calculateEndTime($time, $serviceId),
        );
    } catch (\Exception $e) {
        throw new \Exception('Required resources are not available: ' . $e->getMessage());
    }

    // 5. Create Reservation (Locking the slot)
    // For external bookings, set to 'reserved' and lock for 10 mins for payment.
    // For internal walk-ins, bypass 'reserved' and just confirm them.
    if (empty($validatedData['internal_booking'])) {
        $validatedData['slot_locked_until'] = CarbonImmutable::now()->addMinutes(10);
        $validatedData['status'] = 'reserved';
    } else {
        $validatedData['status'] = 'confirmed';
    }
    
    $validatedData['resource_allocations'] = $resourceAllocations;

    // 6. Delegate to BookingService for customer resolution and creation
    return $this->bookingService->createBookingWithAccount($validatedData);
});
```

**Verified Behavior**:
- External bookings: status = `reserved`, slot_locked_until = now + 10 minutes
- Internal walk-ins: status = `confirmed` (bypasses reservation)
- Transaction-wrapped for atomicity

### 1.2 Payment Initiation Path

**Entry Point**: `BookingPaymentController::initializeBookingPayment`

**File**: `backend/app/Http/Controllers/Api/V1/BookingPaymentController.php`  
**Lines**: 28-45

```php
public function initializeBookingPayment(Request $request): JsonResponse
{
    $validated = $request->validate([
        'booking_id' => 'required|exists:bookings,id',
        'payment_method_id' => 'required|exists:payment_methods,id',
        'email' => 'required|email',
        'customer_name' => 'required|string|max:255',
        'customer_phone' => 'nullable|string|max:20',
    ]);

    try {
        $result = $this->bookingPaymentService->initializeBookingPayment(
            $validated['booking_id'],
            $validated['payment_method_id'],
            $validated['email'],
            $validated['customer_name'],
            $validated['customer_phone'] ?? null
        );

        return response()->json([
            'success' => true,
            'payment_link' => $result['payment_link'],
            'reference' => $result['reference'],
            'provider_reference' => $result['provider_reference'],
            'access_code' => $result['access_code'],
            'payment_request_id' => $result['payment_request_id'],
        ], 201);
    } catch (\Exception $e) {
        Log::error('Booking payment initialization failed', [
            'error' => $e->getMessage(),
            'booking_id' => $validated['booking_id'],
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to initialize payment: ' . $e->getMessage(),
        ], 500);
    }
}
```

**File**: `backend/app/Services/Payments/BookingPaymentService.php`  
**Lines**: 43-173

```php
public function initializeBookingPayment(
    string $bookingId,
    string $paymentMethodId,
    string $email,
    string $customerName,
    ?string $customerPhone = null
): array {
    return DB::transaction(function () use ($bookingId, $paymentMethodId, $email, $customerName, $customerPhone) {
        $booking = Booking::findOrFail($bookingId);
        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
        
        // ... payment initialization logic ...
        
        // Create payment request
        $paymentRequest = PaymentRequest::create([
            'booking_id' => $bookingId,
            'customer_id' => $booking->customer_id,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            // ...
        ]);

        // Update booking payment status
        Booking::where('id', $bookingId)
            ->update(['payment_status' => 'pending']);

        return [
            'payment_link' => $paymentLink,
            'reference' => $reference,
            // ...
        ];
    });
}
```

**Verified Behavior**:
- PaymentRequest created with status = `pending`
- Booking payment_status = `pending`
- Booking status remains `reserved` (NOT transitioned)

### 1.3 Successful Payment Path

**Entry Point**: `BookingPaymentController::verifyBookingPayment` or Webhook

**File**: `backend/app/Services/Payments/BookingPaymentService.php`  
**Lines**: 181-278

```php
public function verifyBookingPayment(string $reference): array
{
    return DB::transaction(function () use ($reference) {
        $paymentRequest = PaymentRequest::where('reference', $reference)->firstOrFail();
        
        // ... verification logic with payment provider ...
        
        // Create transaction
        $transaction = Transaction::create([
            'provider_id' => $paymentRequest->provider_id,
            'booking_id' => $paymentRequest->booking_id,
            // ...
            'status' => 'completed',
            // ...
        ]);

        // Update payment request
        $paymentRequest->update([
            'status' => 'paid',
            'completed_at' => now(),
        ]);

        // Update booking payment status
        if ($paymentRequest->booking_id) {
            Booking::where('id', $paymentRequest->booking_id)
                ->update(['payment_status' => 'paid']);
        }

        // Dispatch PaymentConfirmed event
        $booking = Booking::find($paymentRequest->booking_id);
        if ($booking) {
            Event::dispatch(new PaymentConfirmed(
                $booking,
                $transaction,
                $booking->customer_id
            ));
        }

        return [
            'success' => true,
            'status' => 'completed',
            'transaction' => $transaction,
            'payment_request' => $paymentRequest->fresh(),
            'verification_data' => $verificationResult,
        ];
    });
}
```

**Event Listeners**:

**File**: `backend/app/Listeners/AddPaymentActivity.php`  
**Lines**: 10-26

```php
public function handle(PaymentConfirmed $event)
{
    $paymentMethod = $event->transaction->paymentMethod?->name ?? 'MTN MoMo';

    BookingActivity::create([
        'booking_id' => $event->booking->id,
        'type' => 'payment_confirmed',
        'title' => 'Payment Confirmed',
        'description' => "Payment of {$event->amount} UGX received via {$paymentMethod}",
        'data' => [
            'amount' => $event->amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $event->transaction->id,
        ],
        'actor_type' => 'system',
    ]);
}
```

**File**: `backend/app/Listeners/CreatePaymentNotification.php`  
**Lines**: 10-46

```php
public function handle(PaymentConfirmed $event)
{
    $paymentMethod = $event->transaction->paymentMethod?->name ?? 'MTN MoMo';

    // Create notification for provider
    Notification::create([
        'provider_id' => $event->providerId,
        'type' => 'payment_confirmed',
        'category' => 'payment',
        'priority' => 'high',
        'icon' => 'wallet',
        'title' => 'Payment Received',
        'message' => "Payment of {$event->amount} UGX received via {$paymentMethod}",
        'action_url' => "/dashboard/bookings/{$event->booking->id}",
        'data' => [
            'booking_id' => $event->booking->id,
            'amount' => $event->amount,
            'payment_method' => $paymentMethod,
        ],
    ]);

    // Create notification for customer
    Notification::create([
        'customer_id' => $event->customerId,
        'type' => 'payment_confirmed',
        'category' => 'payment',
        'priority' => 'high',
        'icon' => 'wallet',
        'title' => 'Payment Confirmed',
        'message' => "Your payment of {$event->amount} UGX has been confirmed",
        'action_url' => "/portal/bookings/{$event->booking->id}",
        'data' => [
            'booking_id' => $event->booking->id,
            'amount' => $event->amount,
            'payment_method' => $paymentMethod,
        ],
    ]);
}
```

**CRITICAL MISSING BEHAVIOR**:
- Payment confirmation updates `payment_status` to `paid`
- Payment confirmation dispatches `PaymentConfirmed` event
- **Event listeners create activity and notifications**
- **NO listener transitions booking status from `reserved` to `confirmed`**
- Booking remains in `reserved` status indefinitely after successful payment

**Expected Behavior**:
```php
// Missing listener or direct transition in verifyBookingPayment:
if ($booking && $booking->status === 'reserved') {
    app(BookingService::class)->transitionStatus(
        $booking,
        'confirmed',
        'system',
        null,
        'Payment confirmed'
    );
}
```

### 1.4 Payment Expiry Path

**Entry Point**: Scheduled command `ExpirePendingPayments`

**File**: `backend/app/Console/Commands/ExpirePendingPayments.php`  
**Lines**: 28-44

```php
public function handle()
{
    $expiredCount = PaymentRequest::where('status', 'pending')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired', 'completed_at' => now()]);

    if ($expiredCount > 0) {
        Log::info('Expired pending payment requests', [
            'count' => $expiredCount,
        ]);
        $this->info("Expired {$expiredCount} pending payment requests.");
    } else {
        $this->info('No pending payment requests to expire.');
    }

    return Command::SUCCESS;
}
```

**CRITICAL MISSING BEHAVIOR**:
- Command only expires `PaymentRequest` records
- **Does NOT transition associated booking status from `reserved` to `cancelled`**
- **Does NOT release the slot (clear slot_locked_until)**
- **Does NOT create audit activity or notifications**

**Expected Behavior**:
```php
public function handle()
{
    $expiredRequests = PaymentRequest::where('status', 'pending')
        ->where('expires_at', '<', now())
        ->get();

    foreach ($expiredRequests as $paymentRequest) {
        DB::transaction(function () use ($paymentRequest) {
            $paymentRequest->update([
                'status' => 'expired',
                'completed_at' => now(),
            ]);

            // Transition booking to cancelled
            if ($paymentRequest->booking_id) {
                Booking::where('id', $paymentRequest->booking_id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'cancelled']);

                // Create activity
                BookingActivity::create([
                    'booking_id' => $paymentRequest->booking_id,
                    'type' => 'payment_expired',
                    'title' => 'Payment Expired',
                    'description' => 'Payment request expired without completion',
                    'actor_type' => 'system',
                ]);
            }
        });
    }

    $expiredCount = $expiredRequests->count();
    // ...
}
```

---

## 2. Hold Duration and Provider Configuration

### 2.1 Actual Hold Duration

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 212-214

```php
if (empty($validatedData['internal_booking'])) {
    $validatedData['slot_locked_until'] = CarbonImmutable::now()->addMinutes(10);
    $validatedData['status'] = 'reserved';
}
```

**Verified Behavior**:
- Hold duration is **hardcoded to 10 minutes**
- Not configurable via provider/location/service policies
- No configuration field in `BookingConfigurationService` for hold duration

**Configuration Service**:

**File**: `backend/app/Services/BookingConfigurationService.php`  
**Lines**: 70-82

```php
private $defaultBookingPolicy = [
    'allow_double_booking' => false,
    'min_notice_hours' => 2,
    'allow_walk_ins' => false,
    'allow_same_day' => true,
    'buffer_minutes' => 15,
    'max_bookings_per_day' => null,
    'blackout_dates' => [],
];
```

**Missing Configuration**:
- No `reservation_hold_minutes` field in default booking policy
- No provider/location/service override capability for hold duration

### 2.2 In-Flight Payment Handling

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 46-54

```php
// 0. Idempotency Check
if (!empty($validatedData['idempotency_key'])) {
    $existingBooking = Booking::where('idempotency_key', $validatedData['idempotency_key'])->first();
    if ($existingBooking) {
        // Determine if we need to return full payload (customer/portal) or just booking.
        // For simplicity, we just return the existing booking.
        return ['booking' => $existingBooking];
    }
}
```

**Verified Behavior**:
- Idempotency key prevents duplicate bookings
- If payment is in-flight (booking already created), returns existing booking
- No specific handling for payment status checks during idempotency

---

## 3. Full-Interval Conflict Protection

### 3.1 Conflict Detection in BookingOrchestrator

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 118-139

```php
if (!$allowDoubleBooking) {
    // Check for existing booking at same date/time for this provider/location
    $conflictQuery = \App\Models\Booking::where('provider_id', $providerId)
        ->where('date', $date)
        ->where('time', $time)
        ->whereIn('status', ['confirmed', 'pending', 'reserved']);

    if ($locationId) {
        $conflictQuery->where('location_id', $locationId);
    }

    // If a specialist is assigned, check for conflicts with that specialist
    // Otherwise check for any booking at that time (provider-wide double booking check)
    if ($assignment) {
        $conflictQuery->where('staff_id', $assignment->staff_id);
    }

    $existingBooking = $conflictQuery->first();
    if ($existingBooking) {
        throw new \Exception('That appointment time is no longer available. Please choose another time.');
    }
}
```

**Verified Behavior**:
- **Exact time match only** (`where('time', $time)`)
- No interval overlap detection
- No buffer time consideration in conflict check
- Checks `confirmed`, `pending`, `reserved` statuses

### 3.2 Buffer Configuration

**File**: `backend/app/Services/BookingConfigurationService.php`  
**Lines**: 79

```php
'buffer_minutes' => 15,
```

**File**: `backend/app/Services/AvailabilityEngine.php`  
**Lines**: 752-755

```php
$serviceDuration = $service ? $service->duration : 60;
$bufferBefore    = $service ? ($service->buffer_before ?? 0) : 0;
$bufferAfter     = $service ? ($service->buffer_after ?? 0) : 0;
$totalDuration   = $serviceDuration + $bufferBefore + $bufferAfter;
```

**File**: `backend/app/Services/AvailabilityEngine.php`  
**Lines**: 780-789

```php
$isAvailable = true;
foreach ($existingBookings as $booking) {
    $bookingStart = Carbon::parse($booking['start_time'], $timezone);
    $bookingEnd   = Carbon::parse($booking['end_time'], $timezone);

    if ($slotStart->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
        $isAvailable = false;
        break;
    }
}
```

**Verified Behavior**:
- Buffers ARE used in availability slot generation (totalDuration calculation)
- Buffers ARE used in availability overlap detection
- **Buffers NOT used in BookingOrchestrator conflict detection**
- Conflict detection uses exact time match only

**Gap**: Conflict protection is inconsistent - availability checks use buffers, but booking creation uses exact time match.

### 3.3 Concurrent Request Protection

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 181-190

```php
// 3. Concurrency Locking using Cache (instead of DB row locking the whole assignment)
// For independent specialists without assignment, use provider_id as lock key
$lockKey = $assignment
    ? "booking_slot_{$assignment->id}_{$date}_{$time}"
    : "booking_slot_provider_{$providerId}_{$date}_{$time}";
$lock = Cache::lock($lockKey, 10); // lock for 10 seconds

if (!$lock->get()) {
    throw new \Exception('This slot is currently being booked by someone else. Please try again or select another time.');
}
```

**Verified Behavior**:
- Cache-based locking for concurrent requests
- Lock duration: 10 seconds
- Lock key includes assignment/provider, date, and time
- Lock released in finally block (line 232)

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 231-233

```php
} finally {
    $lock->release();
}
```

**Verified Behavior**: Proper lock release in finally block ensures cleanup even on exceptions.

---

## 4. Payment Expiry Through State Machine

### 4.1 Current Payment Expiry Flow

**File**: `backend/app/Console/Commands/ExpirePendingPayments.php`  
**Lines**: 30-32

```php
$expiredCount = PaymentRequest::where('status', 'pending')
    ->where('expires_at', '<', now())
    ->update(['status' => 'expired', 'completed_at' => now()]);
```

**Verified Behavior**:
- Only updates `PaymentRequest.status` to `expired`
- No booking status transition
- No activity record creation
- No notification dispatch
- No slot release

### 4.2 Booking State Machine

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 545-573

```php
// ── Canonical state machine for the full booking lifecycle ────────────────
//
// Reservation layer  (managed by booking system):
//   pending         → confirmed | cancelled
//   pending_payment → confirmed | cancelled
//   reserved        → pending | pending_payment | confirmed | cancelled
//
// Operational floor layer (managed by receptionist / specialist):
//   confirmed  → arrived | cancelled | no_show
//   arrived    → waiting | in_progress | cancelled | no_show
//   waiting    → in_progress | cancelled | no_show
//   in_progress → completed
//
// Terminal states:
//   completed | cancelled | no_show → (no further transitions)
//
$allowedTransitions = [
    'pending'         => ['confirmed', 'cancelled'],
    'pending_payment' => ['confirmed', 'cancelled'],
    'reserved'        => ['pending', 'pending_payment', 'confirmed', 'cancelled'],
    'confirmed'       => ['arrived', 'in_progress', 'cancelled', 'no_show', 'rescheduled'],
    'arrived'         => ['waiting', 'in_progress', 'cancelled', 'no_show'],
    'waiting'         => ['in_progress', 'cancelled', 'no_show'],
    'in_progress'     => ['completed'],
    'completed'       => [],
    'cancelled'       => [],
    'no_show'         => [],
    'rescheduled'     => [],
];
```

**Verified Behavior**:
- State machine allows `reserved` → `cancelled` transition
- Payment expiry should use this transition
- Current implementation bypasses state machine entirely

### 4.3 Audit History (BookingActivity)

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 610-623

```php
// Create activity record for audit trail
\App\Models\BookingActivity::create([
    'booking_id' => $booking->id,
    'type' => 'status_changed',
    'title' => "Status changed from {$previousStatus} to {$newStatus}",
    'description' => $reason ?? "Booking status transitioned from {$previousStatus} to {$newStatus}",
    'data' => [
        'previous_status' => $previousStatus,
        'new_status' => $newStatus,
        'reason' => $reason,
    ],
    'actor_type' => $actorType,
    'actor_id' => $actorId,
]);
```

**Verified Behavior**:
- `transitionStatus` method creates activity records
- Payment expiry bypasses `transitionStatus`, so no activity created
- Missing audit trail for payment expiry events

### 4.4 Notifications

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 605-608

```php
// Cancel reminders when booking is cancelled
if ($newStatus === 'cancelled') {
    $this->reminderService->cancelRemindersForBooking($booking->id);
}
```

**Verified Behavior**:
- `transitionStatus` cancels reminders when booking cancelled
- Payment expiry bypasses `transitionStatus`, so reminders not cancelled
- No customer notification for payment expiry

---

## 5. Rescheduling Atomicity

### 5.1 CustomerBookingService Reschedule Path

**File**: `backend/app/Services/CustomerBookingService.php`  
**Lines**: 243-271

```php
public function rescheduleBooking(string $bookingId, array $newData): Booking
{
    return DB::transaction(function () use ($bookingId, $newData) {
        $booking = Booking::findOrFail($bookingId);

        // Cancel old booking (this cancels old reminders via BookingService.transitionStatus)
        $this->cancelBooking($bookingId);

        // Create new booking with same details but new date/time
        $newBookingData = [
            'customer_id' => $booking->customer_id,
            'location_id' => $booking->location_id,
            'service_id' => $booking->service_id,
            'staff_id' => $booking->staff_id,
            'date' => $newData['date'],
            'time' => $newData['time'],
            'notes' => $newData['notes'] ?? $booking->notes,
        ];

        $newBooking = $this->createBooking($newBookingData);

        // Schedule reminders for new booking (Phase 1E integration)
        // createBooking() already handles this if status is confirmed,
        // but we call handleBookingRescheduled for explicit lifecycle clarity
        app(\App\Services\ReminderService::class)->handleBookingRescheduled($newBooking->id);

        return $newBooking;
    });
}
```

**Verified Behavior**:
- Transaction-wrapped
- **Original booking cancelled FIRST** (line 249)
- New booking created SECOND (line 262)
- **If new booking creation fails, original booking is already cancelled**
- No rollback of cancellation if new booking fails
- Original booking lost if replacement cannot be secured

**Gap**: NOT atomic in the sense of preserving original booking. Transaction ensures DB consistency, but business logic leaves customer without a booking if new booking fails.

### 5.2 BookingService Resolution Reschedule Path

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 814-886

```php
private function resolveAsRescheduled(
    Booking $booking,
    string $reasonCode,
    ?string $actorType,
    ?string $actorId,
    array $options
): Booking {
    if (!isset($options['new_booking_data'])) {
        throw ValidationException::withMessages([
            'new_booking_data' => 'new_booking_data is required for RESCHEDULED outcome.',
        ]);
    }

    return DB::transaction(function () use ($booking, $reasonCode, $actorType, $actorId, $options) {
        // Transition original booking to rescheduled
        $this->transitionStatus(
            $booking,
            'rescheduled',
            $actorType,
            $actorId,
            'Booking rescheduled'
        );

        // Set operational state to resolved
        $booking->update(['operational_state' => 'resolved']);

        // Create resolution record for original booking
        $resolution = \App\Models\BookingResolution::create([
            'booking_id' => $booking->id,
            'outcome' => 'RESCHEDULED',
            'reason_code' => $reasonCode,
            'notes' => $options['notes'] ?? null,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'resolved_at' => now(),
            'follow_up_required' => false,
        ]);

        // Create new booking using existing booking creation flow
        $newBookingData = $options['new_booking_data'];
        $newBooking = $this->createGuestBooking(array_merge($newBookingData, [
            'provider_id' => $booking->provider_id,
            'location_id' => $booking->location_id,
            'customer_id' => $booking->customer_id,
            'specialist_id' => $booking->specialist_id,
            'service_id' => $booking->service_id,
            'internal_booking' => true, // Bypass availability validation for rescheduling
        ]));

        // Link new booking to original
        $newBooking->update(['rescheduled_from_booking_id' => $booking->id]);

        // Create activity for original booking
        \App\Models\BookingActivity::create([
            'booking_id' => $booking->id,
            'type' => 'BOOKING_RESCHEDULED',
            'title' => 'Booking rescheduled',
            'description' => "Booking rescheduled to {$newBooking->date} at {$newBooking->time}",
            'data' => [
                'outcome' => 'RESCHEDULED',
                'reason_code' => $reasonCode,
                'resolution_id' => $resolution->id,
                'new_booking_id' => $newBooking->id,
                'new_booking_date' => $newBooking->date,
                'new_booking_time' => $newBooking->time,
            ],
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ]);

        return $booking->fresh(['location', 'customer', 'specialist', 'service', 'resolution']);
    });
}
```

**Verified Behavior**:
- Transaction-wrapped
- **Original booking transitioned to `rescheduled`** (preserved, not cancelled)
- New booking created with `internal_booking = true` (bypasses availability)
- New booking linked to original via `rescheduled_from_booking_id`
- Resolution record created for audit trail
- **If new booking creation fails, transaction rolls back, original booking preserved**

**Better Atomicity**: This path preserves the original booking by using `rescheduled` status instead of `cancelled`. If new booking fails, rollback restores original booking state.

**Gap**: CustomerBookingService path does not use this better pattern. It cancels first, then creates, which is less safe.

---

## 6. Rejection Mechanism

### 6.1 Existing Resolution Outcomes

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 658-662

```php
// Validate outcome
$validOutcomes = ['NO_SHOW', 'CANCELLED', 'RESCHEDULED', 'COMPLETED_LATE', 'CUSTOMER_ARRIVED'];
if (!in_array($outcome, $validOutcomes)) {
    throw ValidationException::withMessages([
        'outcome' => "Invalid outcome. Must be one of: " . implode(', ', $validOutcomes),
    ]);
}
```

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 666-677

```php
// Validate reason code
$validReasonCodes = [
    'CUSTOMER_DID_NOT_ARRIVE',
    'CUSTOMER_CANCELLED',
    'CUSTOMER_REQUESTED_RESCHEDULE',
    'CUSTOMER_COULD_NOT_BE_REACHED',
    'CUSTOMER_ARRIVED_LATE',
    'PROVIDER_UNAVAILABLE',
    'SPECIALIST_UNAVAILABLE',
    'LOCATION_CLOSED',
    'TECHNICAL_ISSUE',
    'OTHER',
];
```

**Verified Behavior**:
- No `REJECTED` outcome exists
- No distinct rejection status exists
- `PROVIDER_UNAVAILABLE` and `SPECIALIST_UNAVAILABLE` reason codes exist
- These can be used with `CANCELLED` outcome to represent provider-initiated rejection

### 6.2 Appropriate Mechanism for Rejection

**Recommended Approach**: Use `CANCELLED` status with provider-side reason codes

**File**: `backend/app/Http/Controllers/Api/V1/BookingController.php`  
**Lines**: 349-350

```php
'outcome'      => 'required|in:NO_SHOW,CANCELLED,RESCHEDULED,COMPLETED_LATE,CUSTOMER_ARRIVED',
'reason_code'  => 'required|in:CUSTOMER_DID_NOT_ARRIVE,CUSTOMER_CANCELLED,CUSTOMER_REQUESTED_RESCHEDULE,CUSTOMER_COULD_NOT_BE_REACHED,CUSTOMER_ARRIVED_LATE,PROVIDER_UNAVAILABLE,SPECIALIST_UNAVAILABLE,LOCATION_CLOSED,TECHNICAL_ISSUE,OTHER',
```

**Verified Behavior**:
- API accepts `CANCELLED` outcome with `PROVIDER_UNAVAILABLE` or `SPECIALIST_UNAVAILABLE` reason codes
- This effectively represents provider rejection
- No separate `REJECTED` status needed

**Example**:
```php
$bookingService->resolveBooking(
    $booking,
    'CANCELLED',
    'PROVIDER_UNAVAILABLE',
    'staff',
    $staffId,
    ['notes' => 'Provider cannot accommodate this booking']
);
```

**Conclusion**: Existing mechanism is sufficient. Use `CANCELLED` with provider-side reason codes for rejection scenarios.

---

## 7. Manual Approval in Owner Interface

### 7.1 Auto-Approval Mechanism

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 1072-1159

```php
public function processAutoApprovals(string $providerId): array
{
    $approvedCount = 0;
    $skippedCount = 0;

    // Get all pending bookings
    $query = Booking::where('status', 'pending');
    
    if ($providerId) {
        $query->where('provider_id', $providerId);
    }

    $pendingBookings = $query->get();

    foreach ($pendingBookings as $booking) {
        try {
            // Get approval policy for this booking's service
            $approvalPolicy = $this->configService->getPolicy(
                $booking->service_id,
                $booking->provider_id,
                $booking->location_id,
                'approval_policy'
            );

            $autoApproveHours = $approvalPolicy['auto_approve_after_hours'] ?? null;

            // Skip if auto-approval is not configured
            if ($autoApproveHours === null) {
                $skippedCount++;
                continue;
            }

            // Calculate time since booking creation
            $bookingCreatedAt = Carbon::parse($booking->created_at);
            $hoursSinceCreation = Carbon::now()->diffInHours($bookingCreatedAt);

            // Check if booking is eligible for auto-approval
            if ($hoursSinceCreation >= $autoApproveHours) {
                // Transition to confirmed (idempotent - safe to call multiple times)
                $this->transitionStatus(
                    $booking,
                    'confirmed',
                    'system',
                    null,
                    'Auto-approved after ' . $hoursSinceCreation . ' hours pending'
                );
                
                \Log::info('Booking auto-approved', [
                    'booking_id' => $booking->id,
                    'provider_id' => $booking->provider_id,
                    'hours_pending' => $hoursSinceCreation,
                    'threshold_hours' => $autoApproveHours,
                ]);
                
                $approvedCount++;
            } else {
                $skippedCount++;
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to process auto-approvals for booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            $skippedCount++;
        }
    }

    return [
        'approved' => $approvedCount,
        'skipped' => $skippedCount,
    ];
}
```

**File**: `backend/app/Http/Controllers/Api/V1/DashboardController.php`  
**Lines**: 38-39

```php
// Process auto-approvals during polling (idempotent, safe to call frequently)
$autoApprovalResult = $this->bookingService->processAutoApprovals($provider->id);
```

**Verified Behavior**:
- Auto-approval runs on dashboard poll (every 30 seconds)
- Checks `auto_approve_after_hours` configuration
- Transitions `pending` → `confirmed` when threshold reached
- No manual approval trigger

### 7.2 Dashboard Statistics

**File**: `backend/app/Http/Controllers/Api/V1/DashboardController.php`  
**Lines**: 59

```php
'awaiting_approval' => $operationalFacts['today']['awaiting_approval'],
```

**Verified Behavior**:
- Dashboard shows `awaiting_approval` count
- This indicates pending bookings exist
- No UI action to approve them manually

### 7.3 Status Transition API

**File**: `backend/app/Http/Controllers/Api/V1/BookingController.php`  
**Lines**: 276, 318

```php
'status' => 'sometimes|in:pending,confirmed,in_progress,cancelled,completed,no_show,arrived,waiting',

// ...

// Regular status transition for non-needs_resolution bookings
$this->bookingService->transitionStatus($booking, $validated['status']);
```

**Verified Behavior**:
- API allows manual status transition via `BookingController::update`
- Staff can manually transition `pending` → `confirmed`
- **No dedicated "approve" action or UI**
- Staff must manually change status (not explicit approval workflow)

### 7.4 Frontend Dashboard

**File**: `frontend/src/components/dashboard/OwnerDashboard.tsx`  
**Lines**: 97

```typescript
const waitingNow = todayBookings.filter(b => b.status === 'pending' || b.status === 'in-progress').length;
```

**Verified Behavior**:
- Frontend shows pending bookings in "waiting" count
- No approve button or action
- No dedicated approval UI component

**Gap**: No manual approval interface exists. Auto-approval is the only mechanism. Staff can manually change status via booking update, but this is not an explicit approval workflow.

---

## 8. Summary of Critical Missing Behaviors

### 8.1 High Priority (Production Impact)

1. **Payment confirmation does not transition `reserved` → `confirmed`**
   - **File**: `backend/app/Services/Payments/BookingPaymentService.php`
   - **Line**: 254-268
   - **Impact**: Bookings remain in `reserved` status indefinitely after successful payment
   - **Fix**: Add listener or direct transition in `verifyBookingPayment` to call `transitionStatus($booking, 'confirmed', ...)`

2. **Payment expiry does not transition `reserved` → `cancelled`**
   - **File**: `backend/app/Console/Commands/ExpirePendingPayments.php`
   - **Line**: 30-32
   - **Impact**: Stale `reserved` bookings accumulate, slots not released
   - **Fix**: Add booking status transition, activity creation, and notification dispatch

### 8.2 Medium Priority (Operational Gaps)

3. **Hold duration not configurable**
   - **File**: `backend/app/Services/BookingOrchestrator.php`
   - **Line**: 213
   - **Impact**: All providers use 10-minute hold, no flexibility
   - **Fix**: Add `reservation_hold_minutes` to booking policy configuration

4. **Conflict detection inconsistent with availability**
   - **File**: `backend/app/Services/BookingOrchestrator.php`
   - **Line**: 120-123
   - **Impact**: Exact time match only, no buffer consideration in booking creation
   - **Fix**: Use interval overlap detection with buffers in conflict check

5. **Rescheduling not atomic in CustomerBookingService**
   - **File**: `backend/app/Services/CustomerBookingService.php`
   - **Line**: 249
   - **Impact**: Original booking cancelled before new booking created, customer loses booking if creation fails
   - **Fix**: Use `rescheduled` status pattern from BookingService instead of `cancelled`

### 8.3 Low Priority (UX Improvements)

6. **No manual approval interface**
   - **Impact**: Staff cannot manually approve pending bookings, must rely on auto-approval or manual status change
   - **Fix**: Add approve button in dashboard with explicit approval action

---

## 9. Recommendations

### 9.1 Immediate Actions (Required for Production)

1. **Add payment confirmation transition**:
   - Create listener for `PaymentConfirmed` event
   - Check if booking status is `reserved`
   - Transition to `confirmed` via `BookingService::transitionStatus`

2. **Add payment expiry transition**:
   - Update `ExpirePendingPayments` command
   - Transition booking status from `reserved` to `cancelled`
   - Create activity record
   - Dispatch notification to customer

### 9.2 Short-Term Improvements

3. **Make hold duration configurable**
   - Add `reservation_hold_minutes` to default booking policy
   - Update BookingOrchestrator to read from configuration
   - Add provider/location/service overrides

4. **Unify conflict detection with availability**
   - Use interval overlap detection in BookingOrchestrator
   - Include buffer time in conflict check
   - Ensure consistency between availability display and booking creation

5. **Improve rescheduling atomicity**
   - Update CustomerBookingService to use `rescheduled` status
   - Preserve original booking until new booking confirmed
   - Link bookings via `rescheduled_from_booking_id`

### 9.3 Long-Term Enhancements

6. **Add manual approval interface**
   - Create approve button in dashboard
   - Add dedicated API endpoint for approval
   - Include approval reason tracking
   - Send notification on approval

---

## 10. Conclusion

The investigation revealed several critical missing behaviors in the booking lifecycle:

1. **Payment confirmation does not complete the reservation lifecycle** - bookings stay in `reserved` status after successful payment
2. **Payment expiry does not clean up stale reservations** - expired bookings remain in system
3. **Hold duration is hardcoded** - no provider configuration flexibility
4. **Conflict detection is inconsistent** - availability uses buffers, booking creation does not
5. **Rescheduling is not fully atomic** - customer can lose booking if replacement fails
6. **No manual approval interface** - staff cannot explicitly approve pending bookings

The existing rejection mechanism (using `CANCELLED` with provider-side reason codes) is sufficient and does not require changes.

**Note**: The test fix and payment-expiry fix identified in the previous report are recommendations only and have not been applied or tested.
