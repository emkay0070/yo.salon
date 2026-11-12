# Targeted Verification Report

**Date**: October 9, 2026  
**Scope**: Final verification before implementation  
**Type**: Narrow, targeted checks on specific behaviors

---

## 1. Customer Booking Flow: Instant Confirmation vs Manual Approval vs Deposit Checkout

### Decision Logic

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 320-329

```php
// Determine deposit requirement and payment status
$requiresDeposit = $paymentInstruction->customerAction === 'pay_now';
$paymentStatus = $requiresDeposit ? 'pending' : 'not_required';

// Determine initial status based on approval policy (payment takes precedence)
if ($requiresDeposit) {
    $bookingStatus = 'pending_payment';
} else {
    $bookingStatus = $this->determineInitialBookingStatus($providerId, $locationId, $serviceId, false);
}
```

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 1027-1066

```php
private function determineInitialBookingStatus(
    string $providerId,
    ?string $locationId,
    string $serviceId,
    bool $isInternalBooking = false
): string {
    // Internal bookings bypass approval (admin/staff created)
    if ($isInternalBooking) {
        return 'confirmed';
    }

    try {
        $approvalPolicy = $this->configService->getPolicy(
            $serviceId,
            $providerId,
            $locationId,
            'approval_policy'
        );

        $requireApproval = $approvalPolicy['require_approval'] ?? false;
        $approvalType = $approvalPolicy['approval_type'] ?? 'instant';

        // If approval is not required, or type is instant, confirm immediately
        if (!$requireApproval || $approvalType === 'instant') {
            return 'confirmed';
        }

        // Manual approval: start as pending
        if ($approvalType === 'manual') {
            return 'pending';
        }

        // Consultation: not implemented, treat as pending for now
        if ($approvalType === 'consultation') {
            return 'pending';
        }

        // Default to confirmed for unknown types
        return 'confirmed';
    } catch (\Exception $e) {
        // If config lookup fails, default to confirmed to avoid blocking bookings
        \Log::warning('Failed to determine approval policy, defaulting to confirmed', [
            'error' => $e->getMessage(),
        ]);
        return 'confirmed';
    }
}
```

**File**: `backend/app/Services/BookingOrchestrator.php`  
**Lines**: 212-214

```php
if (empty($validatedData['internal_booking'])) {
    $validatedData['slot_locked_until'] = CarbonImmutable::now()->addMinutes(10);
    $validatedData['status'] = 'reserved';
}
```

### Verified Decision Hierarchy

**For external bookings (customer-facing)**:

1. **BookingOrchestrator override**: Sets status to `reserved` for all external bookings (line 214)
2. **BookingService payment check**: 
   - If deposit required (`pay_now`): status = `pending_payment`
   - If no deposit: status from `determineInitialBookingStatus`
3. **Approval policy check** (only if no deposit):
   - `instant` or no approval required: `confirmed`
   - `manual`: `pending`
   - `consultation`: `pending` (not implemented)

**For internal bookings (staff walk-ins)**:

1. **BookingOrchestrator**: Sets status to `confirmed` (line 216)
2. **BookingService**: Internal bookings bypass approval (line 1034-1036)

### Gap Identified

**BookingOrchestrator's `reserved` status is overridden by BookingService's logic**:
- External bookings start as `reserved` in BookingOrchestrator
- BookingService then determines final status based on payment/approval
- The `reserved` status from BookingOrchestrator is effectively ignored for payment/approval flows

**Actual behavior**:
- Deposit required: `pending_payment`
- No deposit + instant approval: `confirmed`
- No deposit + manual approval: `pending`
- The `reserved` status is only used if BookingOrchestrator's status is not overridden

---

## 2. Scheduled Expiry Command in Deployed Environment

### Command Existence

**File**: `backend/app/Console/Commands/ExpirePendingPayments.php`  
**Lines**: 16, 28-32

```php
protected $signature = 'payments:expire-pending';

public function handle()
{
    $expiredCount = PaymentRequest::where('status', 'pending')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired', 'completed_at' => now()]);
    // ...
}
```

### Schedule Configuration

**File**: `backend/routes/console.php`  
**Lines**: 13-33

```php
// Schedule trending score refresh daily at midnight
Schedule::command('trending:refresh')->daily();

// Process subscription renewals every hour
Schedule::job(new ProcessSubscriptionRenewals)->hourly();

// Sweep eligible pending settlements for automated payouts
Schedule::job(new ProcessScheduledPayouts)->hourly();

// Reconcile payouts stuck in processing state
Schedule::job(new ReconcileProcessingPayouts)->hourly();

// Auto-approve pending bookings that have passed the configured threshold.
// Runs every 15 minutes — auto-approval windows are in hours, not seconds.
Schedule::call(function () {
    app(\App\Services\BookingService::class)->processAutoApprovals();
})->everyFifteenMinutes()->name('booking.auto-approval')->withoutOverlapping();

// Process booking operational time states based on scheduled appointment time.
// Runs every minute for precise time-state tracking.
Schedule::command('bookings:process-time-states')->everyMinute()->name('booking.time-states')->withoutOverlapping();
```

### Critical Finding

**The `payments:expire-pending` command is NOT scheduled**:
- Command exists but has no schedule entry
- Payment expiry does NOT run automatically
- Stale `PaymentRequest` records will accumulate indefinitely

---

## 3. Overlap Protection Under Concurrent Requests with Different Start Times

### Conflict Detection in BookingOrchestrator

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

**Verified**: Exact time match only (`where('time', $time)`). No interval overlap detection. No buffer consideration.

### Availability Engine Overlap Detection

**File**: `backend/app/Services/AvailabilityEngine.php`  
**Lines**: 752-789

```php
$serviceDuration = $service ? $service->duration : 60;
$bufferBefore    = $service ? ($service->buffer_before ?? 0) : 0;
$bufferAfter     = $service ? ($service->buffer_after ?? 0) : 0;
$totalDuration   = $serviceDuration + $bufferBefore + $bufferAfter;

// ...

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

**Verified**: Interval overlap detection with buffers included in totalDuration.

### Concurrent Request Protection

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

**Verified**: Cache-based locking with 10-second duration. Lock key includes assignment/provider, date, and time.

### Gap Identified

**Inconsistent overlap protection**:
- Availability checks: interval overlap with buffers
- Booking creation: exact time match only
- **Scenario**: Two bookings at 10:00 and 10:30 with 15-minute buffer
  - Availability: 10:00 booking blocks 09:45-10:45 (with buffer)
  - Booking creation: 10:30 booking allowed (exact time match check only)
  - **Result**: Overlap possible despite availability showing slot as unavailable

---

## 4. Late Payment Handling When Payment Succeeds After Hold Expires

### Payment Verification Logic

**File**: `backend/app/Services/Payments/BookingPaymentService.php`  
**Lines**: 181-209

```php
public function verifyBookingPayment(string $reference): array
{
    $verificationResult = $this->provider->verifyPayment($reference);

    if (!$verificationResult['success']) {
        return [
            'success' => false,
            'status' => $verificationResult['status'],
            'message' => 'Payment verification failed',
        ];
    }

    return DB::transaction(function () use ($verificationResult, $reference) {
        // Find payment request by provider reference
        $paymentRequest = PaymentRequest::where('provider_reference', $reference)
            ->orWhere('metadata->payment_reference', $reference)
            ->first();

        if (!$paymentRequest) {
            throw new \Exception('Payment request not found for this reference');
        }

        if ($paymentRequest->status === 'paid') {
            return [
                'success' => true,
                'status' => 'already_paid',
                'message' => 'Payment already processed',
                'transaction' => $paymentRequest->transaction,
            ];
        }
        // ...
```

**File**: `backend/app/Services/Payments/BookingPaymentService.php`  
**Lines**: 248-268

```php
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
```

### Hold Expiry Fields

**File**: `backend/app/Models/Booking.php`  
**Lines**: 40, 54-55

```php
'slot_locked_until',
// ...
'slot_locked_until' => 'datetime',
'payment_expires_at' => 'datetime',
```

**File**: `backend/app/Services/BookingService.php`  
**Lines**: 354, 359

```php
'payment_expires_at' => $data['slot_locked_until'] ?? null,
// ...
'slot_locked_until' => $data['slot_locked_until'] ?? null,
```

### Gap Identified

**No hold expiry validation in payment verification**:
- `verifyBookingPayment` does NOT check `slot_locked_until` or `payment_expires_at`
- Payment succeeds regardless of whether hold has expired
- No validation that booking is still in `reserved` status
- **Scenario**: Customer pays 15 minutes after booking creation (hold expires at 10 minutes)
  - Payment succeeds
  - Booking payment_status = `paid`
  - Booking status remains `reserved` (no transition to `confirmed`)
  - **Result**: Payment accepted but booking not confirmed, inconsistent state

---

## 5. Authorization Rules for Owner Approval and Rejection

### Booking Resolution Route

**File**: `backend/routes/api.php`  
**Lines**: 549-550

```php
Route::apiResource('bookings', BookingController::class);
Route::post('/bookings/{booking}/resolve', [BookingController::class, 'resolve']);
```

**Route group middleware**: Authenticated via `auth:sanctum` (implicit from route group context)

### Booking Resolution Controller

**File**: `backend/app/Http/Controllers/Api/V1/BookingController.php`  
**Lines**: 346-379

```php
public function resolve(Request $request, Booking $booking): JsonResponse
{
    $validated = $request->validate([
        'outcome'      => 'required|in:NO_SHOW,CANCELLED,RESCHEDULED,COMPLETED_LATE,CUSTOMER_ARRIVED',
        'reason_code'  => 'required|in:CUSTOMER_DID_NOT_ARRIVE,CUSTOMER_CANCELLED,CUSTOMER_REQUESTED_RESCHEDULE,CUSTOMER_COULD_NOT_BE_REACHED,CUSTOMER_ARRIVED_LATE,PROVIDER_UNAVAILABLE,SPECIALIST_UNAVAILABLE,LOCATION_CLOSED,TECHNICAL_ISSUE,OTHER',
        'notes'        => 'nullable|string',
        'follow_up_required' => 'sometimes|boolean',
        'follow_up_at' => 'nullable|date',
        'follow_up_notes' => 'nullable|string',
        'new_booking_data' => 'required_if:outcome,RESCHEDULED|array',
        'new_booking_data.date' => 'required_if:outcome,RESCHEDULED|date',
        'new_booking_data.time' => 'required_if:outcome,RESCHEDULED|string',
        'new_booking_data.customer_name' => 'required_if:outcome,RESCHEDULED|string',
    ]);

    try {
        $actor = $request->user();
        $options = [
            'notes' => $validated['notes'] ?? null,
            'follow_up_required' => $validated['follow_up_required'] ?? false,
            'follow_up_at' => $validated['follow_up_at'] ?? null,
            'follow_up_notes' => $validated['follow_up_notes'] ?? null,
        ];

        if ($validated['outcome'] === 'RESCHEDULED') {
            $options['new_booking_data'] = $validated['new_booking_data'];
        }

        $resolved = $this->bookingService->resolveBooking(
            $booking,
            $validated['outcome'],
            $validated['reason_code'],
            'user',
            $actor?->id,
            $options
        );
```

**Verified**: 
- Accepts `CANCELLED` outcome with `PROVIDER_UNAVAILABLE` or `SPECIALIST_UNAVAILABLE` reason codes
- No explicit authorization check in controller
- Relies on route-level authentication (`auth:sanctum`)
- No provider context validation

### Specialist Portal Status Update

**File**: `backend/app/Http\Controllers/SpecialistPortalController.php`  
**Lines**: 1373-1402

```php
public function updateBookingStatus(Request $request, string $bookingId, \App\Services\BookingService $bookingService): JsonResponse
{
    $account = auth('specialist')->user();
    $specialist = $account->specialist;

    $request->validate([
        'status' => 'required|string|in:confirmed,cancelled,in_progress,completed,no_show',
    ]);

    $booking = Booking::find($bookingId);

    // Authorization: Identity must match
    if ($booking->specialist_id !== $specialist->id) {
        return response()->json(['message' => 'Unauthorized to update this booking'], 403);
    }

    // Authorization: Specialist must have an active assignment at this branch
    $assignment = SpecialistAssignment::where('specialist_id', $specialist->id)
        ->where('provider_id', $booking->provider_id)
        ->where('location_id', $booking->location_id)
        ->where('status', 'ACTIVE')
        ->first();

    if (!$assignment) {
        return response()->json(['message' => 'Unauthorized: No active assignment at this branch'], 403);
    }

    try {
        $booking = $bookingService->transitionStatus($booking, $request->status);
```

**Verified**: 
- Specialist portal has explicit authorization checks
- Validates specialist identity match
- Validates active assignment at branch
- More restrictive than owner/staff routes

### Gap Identified

**No explicit owner authorization for booking resolution**:
- `BookingController::resolve` has no provider context validation
- Any authenticated user can resolve any booking (if they have the booking ID)
- No check that user belongs to the booking's provider
- No role-based authorization (owner vs manager vs staff)

**Specialist portal has proper authorization**:
- Identity must match booking specialist
- Active assignment required at branch
- More secure than owner/staff routes

---

## Summary of Critical Findings

### High Priority (Production Impact)

1. **Payment expiry command not scheduled**
   - Command exists but no schedule entry
   - Stale PaymentRequest records accumulate indefinitely
   - **Fix**: Add schedule entry in `routes/console.php`

2. **No hold expiry validation in payment verification**
   - Payment succeeds regardless of hold expiry
   - Can create inconsistent state (paid but not confirmed)
   - **Fix**: Add `slot_locked_until` check in `verifyBookingPayment`

3. **No explicit owner authorization for booking resolution**
   - Any authenticated user can resolve any booking
   - Missing provider context validation
   - **Fix**: Add provider context middleware or explicit authorization check

### Medium Priority (Operational Gaps)

4. **Inconsistent overlap protection**
   - Availability uses buffers, booking creation uses exact match
   - Can allow overlapping bookings despite availability showing unavailable
   - **Fix**: Use interval overlap detection in BookingOrchestrator

5. **BookingOrchestrator reserved status overridden**
   - `reserved` status set but then overridden by payment/approval logic
   - Confusing decision hierarchy
   - **Fix**: Clarify or remove BookingOrchestrator status override

---

## Recommendations

### Immediate Actions (Required for Production)

1. **Schedule payment expiry command**:
   ```php
   // Add to routes/console.php
   Schedule::command('payments:expire-pending')->everyFiveMinutes()->name('payment.expiry')->withoutOverlapping();
   ```

2. **Add hold expiry validation**:
   ```php
   // In verifyBookingPayment, before processing payment
   if ($booking && $booking->slot_locked_until && $booking->slot_locked_until->isPast()) {
       return [
           'success' => false,
           'status' => 'expired',
           'message' => 'Payment window has expired',
       ];
   }
   ```

3. **Add provider context authorization**:
   ```php
   // In BookingController::resolve
   $providerId = $request->attributes->get('provider_id');
   if ($booking->provider_id !== $providerId) {
       return response()->json(['message' => 'Unauthorized'], 403);
   }
   ```

### Short-Term Improvements

4. **Unify overlap detection**:
   - Use interval overlap detection in BookingOrchestrator
   - Include buffer time in conflict check
   - Ensure consistency with availability display

5. **Clarify status decision hierarchy**:
   - Remove BookingOrchestrator status override
   - Let BookingService handle all status determination
   - Document clear decision flow
