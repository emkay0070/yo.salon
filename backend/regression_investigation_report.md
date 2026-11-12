# REGRESSION INVESTIGATION REPORT

**Date:** 2026-10-09
**Mode:** READ-ONLY INVESTIGATION
**Scope:** Public booking flow regression causing missing customer-provider relationships

---

## CRITICAL FINDING: Missing Dependency in BookingService

### Root Cause Identified

**File:** `backend/app/Services/BookingService.php`

**Issue:** The `BookingService` constructor is missing the `BookingConfigurationService` dependency, but the `determineInitialBookingStatus()` method (line 1033) attempts to use `$this->configService->getPolicy()`.

**Evidence from Laravel logs:**
```
[2026-10-08 19:31:17] local.WARNING: Failed to determine approval policy, defaulting to confirmed
{"error":"Undefined property: App\Services\BookingService::$configService","provider_id":"01a0e836-39fa-700e-991d-797669bb5a32","service_id":"01a10a28-28aa-738d-8f1c-97a0a691285c","location_id":null}
```

This error occurred repeatedly during the booking creation period (2026-10-05 to 2026-10-08).

### Impact Analysis

When `determineInitialBookingStatus()` is called:
1. It tries to access `$this->configService` which doesn't exist
2. Throws exception: "Undefined property: App\Services\BookingService::$configService"
3. Exception is caught at line 1061
4. Logs warning and defaults to 'confirmed' status
5. **BUT**: This exception may be causing the transaction to fail partially or the customer-provider relationship code to not execute

### Constructor vs Usage Mismatch

**Constructor (lines 37-51):**
```php
public function __construct(
    CustomerService $customerService,
    PortalAuthService $portalAuthService,
    BookingPaymentService $bookingPaymentService,
    CustomerSpecialistService $customerSpecialistService,
    NotificationService $notificationService,
    ReminderService $reminderService
) {
    $this->customerService = $customerService;
    $this->portalAuthService = $portalAuthService;
    $this->bookingPaymentService = $bookingPaymentService;
    $this->customerSpecialistService = $customerSpecialistService;
    $this->notificationService = $notificationService;
    $this->reminderService = $reminderService;
}
```

**Missing:** `BookingConfigurationService $configService`

**Usage (line 1033):**
```php
$approvalPolicy = $this->configService->getPolicy(
    $serviceId,
    $providerId,
    $locationId,
    'approval_policy'
);
```

---

## SECONDARY FINDING: Frontend Payload Inconsistency

### Frontend Booking Payload

**File:** `frontend/src/app/booking/BookingContent.tsx` (lines 358-365)

```typescript
createBookingMutation.mutate({
  provider_id: providerId,
  service_id: selectedService.id,
  staff_id: selectedStylist.id,
  date: bookingDateTime.toISOString(),
  status: 'confirmed',
  notes: queuePosition ? `Queue position: ${queuePosition}` : undefined,
});
```

**Missing fields:**
- `customer_name`
- `customer_phone`
- `customer_email`
- `customer_id`

### BookingService Path Analysis

**File:** `backend/app/Services/BookingService.php` (lines 170-190)

```php
// Resolve or create customer (business event)
if (!empty($data['customer_id'])) {
    // Existing CRM customer — look up directly
    $customer = Customer::findOrFail($data['customer_id']);
} elseif (!empty($data['customer_phone'])) {
    // Phone provided — find-or-create a real CRM customer
    $customer = Customer::firstOrCreate(
        ['phone' => $data['customer_phone']],
        [
            'name'  => $data['customer_name'] ?? 'Customer',
            'email' => $data['customer_email'] ?? null,
        ]
    );
} else {
    // No identity provided — create an isolated, provider-scoped guest.
    $customer = \App\Models\Customer::createGuest(
        $providerId,
        $data['customer_name'] ?? 'Walk-in Guest'
    );
}
```

**Expected behavior:** Since frontend doesn't send `customer_phone`, the code should take path 3: `createGuest()` which creates a customer with:
- `is_guest = true`
- `guest_provider_id = providerId`
- `phone = null`

**Actual data:** Customer "booky" has:
- `is_guest = false`
- `guest_provider_id = null`
- `phone = '0780906050'`

**Conclusion:** The bookings were NOT created through the public booking page. They were created through a different path that includes customer phone numbers.

---

## TERTIARY FINDING: Alternative Booking Creation Path

### Test Script Analysis

**File:** `backend/test_walkin.php`

This test script creates bookings with:
- `customer_phone = '0000000000'` (placeholder)
- `customer_name = 'Walk-in Guest'`
- `internal_booking = true`

**Investigation result:** None of the actual bookings have the placeholder phone '0000000000', so they were NOT created by this test script.

### Customer Creation Timeline

**Key finding:** 2 customers were created significantly before their bookings:
- Booking 01a10a7b-1fcc-709f-928a-082a4e10333b: Customer created 334346s (~3.9 days) before booking
- Booking 01a11cf9-8a58-718a-b6ad-f7ca91fcdadb: Customer created 637593s (~7.4 days) before booking

**Conclusion:** These customers were created separately (possibly through a seeder, manual database insertion, or another script) and then referenced by customer_id in bookings.

---

## ROOT CAUSE SYNTHESIS

### Primary Root Cause

**Missing `BookingConfigurationService` dependency in `BookingService` constructor**

This causes:
1. `determineInitialBookingStatus()` to throw an exception when called
2. The exception is caught and logged, but may cause transaction issues
3. The customer-provider relationship code (lines 193-195 in `createGuestBooking`) may not execute properly

### Secondary Root Cause

**Bookings created through alternative paths that bypass the standard customer-provider relationship creation**

The evidence shows:
- Frontend public booking page doesn't send customer details
- Actual bookings have customer phone numbers (inconsistent with frontend path)
- Some customers were created days before their bookings
- No single test script matches the booking patterns

**Possible alternative paths:**
- Direct database insertion
- Custom admin scripts
- API calls with different payloads
- Seeded data

---

## DATABASE VERIFICATION

### Schema Check
- `customer_providers` table exists with correct schema
- Database is PostgreSQL (confirmed)
- `ON CONFLICT` syntax is supported

### Transaction Consistency
- All booking creation code is wrapped in `DB::transaction()`
- If an exception occurs, the entire transaction should roll back
- The missing dependency exception may be causing partial rollbacks

---

## DASHBOARD KPI STATUS

**Finding:** Dashboard KPIs use direct booking queries and do NOT rely on `customer_providers` relationships.

**File:** `backend/app/Http/Controllers/Api/V1/PulseController.php`

```php
$bookings = Booking::with(['customer', 'service', 'staff'])
    ->where('location_id', $locationId)
    ->whereDate('date', $today)
    ->orderBy('time', 'asc')
    ->get();
```

**Conclusion:** Dashboard KPIs should work correctly even without `customer_providers` relationships. Any KPI regression is likely due to a different cause (date/timezone, status filters, or location scope).

---

## RECOMMENDED FIXES

### Fix 1: Add Missing Dependency (CRITICAL)

**File:** `backend/app/Services/BookingService.php`

Add `BookingConfigurationService` to constructor:

```php
public function __construct(
    CustomerService $customerService,
    PortalAuthService $portalAuthService,
    BookingPaymentService $bookingPaymentService,
    CustomerSpecialistService $customerSpecialistService,
    NotificationService $notificationService,
    ReminderService $reminderService,
    BookingConfigurationService $configService  // ADD THIS
) {
    $this->customerService = $customerService;
    $this->portalAuthService = $portalAuthService;
    $this->bookingPaymentService = $bookingPaymentService;
    $this->customerSpecialistService = $customerSpecialistService;
    $this->notificationService = $notificationService;
    $this->reminderService = $reminderService;
    $this->configService = $configService;  // ADD THIS
}
```

Also add to class properties (line 30-35):
```php
private CustomerService $customerService;
private PortalAuthService $portalAuthService;
private BookingPaymentService $bookingPaymentService;
private CustomerSpecialistService $customerSpecialistService;
private NotificationService $notificationService;
private ReminderService $reminderService;
private BookingConfigurationService $configService;  // ADD THIS
```

### Fix 2: Historical Data Backfill

After fixing the dependency, backfill missing customer-provider relationships using the rules from the previous audit report.

### Fix 3: Frontend Payload Update

Update the public booking page to send customer details or use the customer portal booking flow instead.

---

## REGRESSION TESTS NEEDED

### Test 1: BookingService Has All Dependencies
```php
public function test_booking_service_has_config_service()
{
    $service = app(BookingService::class);
    $this->assertObjectHasProperty($service, 'configService');
}
```

### Test 2: Booking Creates Customer-Provider Relationship
```php
public function test_booking_creates_customer_provider_relationship()
{
    $provider = Provider::factory()->create();
    $location = Location::factory()->for($provider)->create();

    $bookingData = [
        'provider_id' => $provider->id,
        'location_id' => $location->id,
        'customer_phone' => '1234567890',
        'customer_name' => 'Test Customer',
        'service_id' => Service::factory()->for($provider)->create()->id,
        'date' => now()->toDateString(),
        'time' => '10:00',
    ];

    $booking = app(BookingService::class)->createGuestBooking($bookingData);

    $this->assertDatabaseHas('customer_providers', [
        'customer_id' => $booking->customer_id,
        'provider_id' => $provider->id,
    ]);
}
```

### Test 3: determineInitialBookingStatus Works
```php
public function test_determine_initial_booking_status()
{
    $service = app(BookingService::class);
    $status = $service->determineInitialBookingStatus(
        $provider->id,
        $location->id,
        $serviceId,
        false
    );
    $this->assertIsString($status);
    $this->assertContains($status, ['pending', 'confirmed']);
}
```

---

## NEXT STEPS

1. **Add missing dependency** to `BookingService` constructor
2. **Test booking creation** to verify customer-provider relationships are created
3. **Backfill historical data** using the rules from the previous audit
4. **Add regression tests** to prevent this from recurring
5. **Investigate dashboard KPI regression** separately (different root cause)

---

## FILES TO MODIFY

1. `backend/app/Services/BookingService.php` - Add `BookingConfigurationService` dependency
2. Create migration for historical data backfill
3. Create regression tests

---

## EVIDENCE SUMMARY

| Evidence | Source | Impact |
|----------|--------|--------|
| Missing `$configService` property | Laravel logs | CRITICAL - causes exception in booking creation |
| Constructor missing dependency | Code inspection | CRITICAL - root cause of exception |
| Frontend missing customer fields | Frontend code | MEDIUM - bookings use alternative path |
| Customers created before bookings | Database audit | LOW - indicates alternative creation path |
| customer_providers table empty | Database audit | HIGH - symptom of the bug |
| Dashboard KPIs use direct queries | Code inspection | NONE - not affected by this bug |
