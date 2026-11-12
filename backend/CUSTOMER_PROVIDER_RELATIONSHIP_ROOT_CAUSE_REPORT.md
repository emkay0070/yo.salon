# Customer-Provider Relationship Root Cause Investigation Report

**Investigation Date:** October 9, 2026
**Mode:** READ-ONLY - No database writes, migrations, or code changes performed

---

## Executive Summary

**Root Cause Identified:** `CustomerBookingService::createBooking()` does not call `CustomerService::addSalonRelationship()` to establish the customer-provider relationship.

**Impact:** Customer portal bookings (authenticated customers booking through the portal) create bookings without establishing the `customer_providers` relationship required for Clients list visibility.

**Secondary Issue:** Method name typo in `BookingService::createBookingWithAccount` line 479 calls non-existent `addProviderRelationship()` instead of `addSalonRelationship()`.

**Historical Data:** 11 customers with bookings lack `customer_providers` relationships - these were likely created via the customer portal before the relationship creation was implemented in other paths.

---

## 1. Booking Creation Entry Points

### 1.1 Entry Point Matrix

| Entry Point | Controller | Service Method | Path Type | addSalonRelationship? |
|------------|-----------|---------------|-----------|----------------------|
| Public/Owner/Walk-in | BookingController::store | BookingOrchestrator::reserveAndBook → BookingService::createBookingWithAccount | Business-side | ✅ Yes (lines 300-303) |
| Website with Account | BookingController::storeWithAccount | BookingOrchestrator::reserveAndBook → BookingService::createBookingWithAccount | Journey 4 | ✅ Yes (lines 300-303) |
| Customer Portal | CustomerBookingController::store | CustomerBookingService::createBooking | Customer-side | ❌ **NO** |
| Slot Locking | AvailabilityEngine::lockSlot | Direct Booking::create | Temporary lock | N/A (not a confirmed booking) |

### 1.2 Detailed Path Traces

#### Path 1: BookingController::store (Public/Owner/Walk-in)
```
BookingController::store (line 115)
  ↓
BookingOrchestrator::reserveAndBook (line 44)
  ↓
BookingService::createBookingWithAccount (line 224)
  ↓
Customer resolved/created (lines 288-298)
  ↓
addSalonRelationship called (lines 300-303) ✅
  ↓
Booking created (line 334)
```

**File:** `backend/app/Http/Controllers/Api/V1/BookingController.php`
**Service:** `backend/app/Services/BookingService.php` lines 300-303
**Transaction:** Yes - inside DB::transaction
**Provider ID Source:** `$validatedData['provider_id']` from request payload

#### Path 2: BookingController::storeWithAccount (Journey 4)
```
BookingController::storeWithAccount (line 534)
  ↓
BookingOrchestrator::reserveAndBook (line 44)
  ↓
BookingService::createBookingWithAccount (line 224)
  ↓
Customer resolved/created (lines 288-298)
  ↓
addSalonRelationship called (lines 300-303) ✅
  ↓
Booking created (line 334)
```

**File:** `backend/app/Http/Controllers/Api/V1/BookingController.php`
**Service:** `backend/app/Services/BookingService.php` lines 300-303
**Transaction:** Yes - inside DB::transaction
**Provider ID Source:** `$validatedData['provider_id']` from request payload

#### Path 3: CustomerBookingController::store (Customer Portal) ⚠️
```
CustomerBookingController::store (line 176)
  ↓
CustomerBookingService::createBooking (line 217)
  ↓
Customer found (line 32) - NOT created
  ↓
Booking created (line 77)
  ↓
❌ NO addSalonRelationship call
```

**File:** `backend/app/Http/Controllers/Api/V1/CustomerBookingController.php`
**Service:** `backend/app/Services/CustomerBookingService.php` line 29
**Transaction:** Yes - inside DB::transaction (line 31)
**Provider ID Source:** Derived from location: `$location->provider_id` (line 38)

**Critical Gap:** This path assumes the customer already exists and does not establish the provider relationship.

#### Path 4: AvailabilityEngine::lockSlot (Temporary Locks)
```
AvailabilityEngine::lockSlot (line 381)
  ↓
Direct Booking::create (line 395)
  ↓
❌ NO customer relationship (temporary lock, not confirmed booking)
```

**File:** `backend/app/Services/AvailabilityEngine.php`
**Purpose:** Temporary slot locking for payment flow
**Not a confirmed booking** - these are converted to confirmed bookings via BookingService later

---

## 2. addSalonRelationship Call Locations

### 2.1 BookingService::createGuestBooking
**File:** `backend/app/Services/BookingService.php`
**Line:** 196-198
```php
// Ensure customer has provider relationship (domain integrity)
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $this->customerService->addSalonRelationship($customer, $providerId);
}
```
**Status:** ✅ Present and correct

### 2.2 BookingService::createBookingWithAccount
**File:** `backend/app/Services/BookingService.php`
**Line:** 300-303
```php
// Ensure customer has provider relationship (domain integrity)
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $this->customerService->addSalonRelationship($customer, $providerId);
}
```
**Status:** ✅ Present and correct

### 2.3 BookingService::createBookingWithAccount (Portal Account Variant)
**File:** `backend/app/Services/BookingService.php`
**Line:** 478-480
```php
// Ensure customer has relationship with this business
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $this->customerService->addProviderRelationship($customer, $providerId); // ❌ TYPO
}
```
**Status:** ❌ **Method name typo** - calls `addProviderRelationship` which does not exist. Should be `addSalonRelationship`.

### 2.4 CustomerBookingService::createBooking
**File:** `backend/app/Services/CustomerBookingService.php`
**Line:** 29-115
**Status:** ❌ **Missing** - No addSalonRelationship call anywhere in this method

---

## 3. Paths Bypassing BookingService

### 3.1 Direct Booking::create Calls

| Location | Purpose | Customer Relationship? | Risk |
|----------|---------|------------------------|------|
| AvailabilityEngine::lockSlot (line 395) | Temporary slot lock | N/A (temporary) | None - not a confirmed booking |
| CustomerBookingService::createBooking (line 77) | Customer portal booking | ❌ Missing | **HIGH** - confirmed booking without relationship |
| Console commands (TestSessionAuth, TestSpecialistBreaks) | Test data creation | Varies | None - test-only |

### 3.2 CustomerBookingService Analysis

**File:** `backend/app/Services/CustomerBookingService.php`

**Method:** `createBooking` (lines 29-115)

**Flow:**
1. Customer already exists (line 32) - assumes pre-existing customer
2. Location resolved (line 38)
3. Provider ID derived from location (line 38)
4. Booking created (line 77)
5. Customer visit count incremented (line 100)
6. Customer-specialist relationship tracked (line 104)
7. Reminders scheduled (line 110)

**Missing Step:** No call to `addSalonRelationship` to establish customer-provider link.

**Assumption:** The method assumes the customer already has a relationship with the provider, which is not guaranteed for customer portal bookings.

---

## 4. Test Coverage Mapping

### 4.1 Existing Tests

| Test File | Path Covered | Tests Relationship? | Status |
|------------|--------------|---------------------|--------|
| BookingCustomerProviderRelationshipTest.php | Direct addSalonRelationship call | ✅ Yes | ✅ Passing |
| BookingServicePersistenceTest.php | createGuestBooking, createBookingWithAccount | ❌ No | ⚠️ Incomplete |
| CustomerBookingReminderIntegrationTest.php | CustomerBookingService::createBooking | ❌ No | ⚠️ Incomplete |
| BookingWithAccountEmailTest.php | createBookingWithAccount | ❌ No | ⚠️ Incomplete |

### 4.2 Test Gaps

**Gap 1:** No test verifies `BookingService::createGuestBooking` creates `customer_providers` relationship
**Gap 2:** No test verifies `BookingService::createBookingWithAccount` creates `customer_providers` relationship
**Gap 3:** No test verifies `CustomerBookingService::createBooking` creates `customer_providers` relationship
**Gap 4:** No test covers the typo in line 479 (`addProviderRelationship` vs `addSalonRelationship`)

### 4.3 Test Coverage by Entry Point

| Entry Point | Test Coverage | Relationship Verified? |
|------------|---------------|------------------------|
| BookingController::store | BookingServicePersistenceTest | ❌ No |
| BookingController::storeWithAccount | BookingWithAccountEmailTest | ❌ No |
| CustomerBookingController::store | CustomerBookingReminderIntegrationTest | ❌ No |

---

## 5. Historical Failure Investigation

### 5.1 Git History Analysis

**Repository:** `backend/app/Services/BookingService.php`

**Key Commits:**
- `5587732` (Initial commit): `addSalonRelationship` present in booking flow
- `50f1d04` (Fix booking creation bug): `addSalonRelationship` still present
- Current HEAD: `addSalonRelationship` present in createGuestBooking and createBookingWithAccount

**Finding:** The relationship creation logic has been present in BookingService since initial commit. The historical 11 missing relationships were likely created via CustomerBookingService::createBooking (customer portal) which never had the relationship creation logic.

### 5.2 Method Name Typo Timeline

**Current Code (line 479):**
```php
$this->customerService->addProviderRelationship($customer, $providerId);
```

**Actual Method Name (CustomerService.php line 104):**
```php
public function addSalonRelationship(Customer $customer, string $providerId): Customer
```

**Impact:** This typo would cause a runtime error if the code path is executed. However, this path may not be currently used or the typo was introduced recently and not yet executed in production.

**Status:** Requires immediate fix to prevent runtime errors.

---

## 6. Idempotency and Provider Ownership Verification

### 6.1 addSalonRelationship Idempotency

**File:** `backend/app/Services/CustomerService.php` lines 104-121

**Implementation:**
```php
public function addSalonRelationship(Customer $customer, string $providerId): Customer
{
    $exists = DB::table('customer_providers')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $providerId)
        ->exists();

    if (!$exists) {
        DB::statement(
            'INSERT INTO customer_providers (id, customer_id, provider_id, visits, joined_at, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?, ?)
             ON CONFLICT (customer_id, provider_id) DO NOTHING',
            [(string) Str::orderedUuid(), $customer->id, $providerId, now(), now(), now()]
        );
    }

    return $customer->fresh();
}
```

**Idempotency Analysis:**
- ✅ Existence check before insert
- ✅ PostgreSQL `ON CONFLICT DO NOTHING` clause
- ✅ Unique constraint on `(customer_id, provider_id)`
- ✅ Safe to call multiple times
- ✅ No duplicates possible

**Status:** ✅ Idempotent and safe

### 6.2 Provider Ownership Logic

**BookingService::createGuestBooking (line 196):**
```php
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $this->customerService->addSalonRelationship($customer, $providerId);
}
```
**Provider ID Source:** `$providerId` from `$data['provider_id']` (request payload)
**Validation:** Controller validates `provider_id` exists and matches authenticated user's provider (BookingController line 154)
**Status:** ✅ Correct - uses explicit provider_id from request

**BookingService::createBookingWithAccount (line 300):**
```php
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $this->customerService->addSalonRelationship($customer, $providerId);
}
```
**Provider ID Source:** `$providerId` from `$validatedData['provider_id']` (request payload)
**Validation:** Controller validates `provider_id` exists and matches authenticated user's provider (BookingController line 154)
**Status:** ✅ Correct - uses explicit provider_id from request

**CustomerBookingService::createBooking (MISSING):**
**Provider ID Source:** Derived from location: `$location->provider_id` (line 38)
**Risk:** If location ownership validation fails or is bypassed, wrong provider relationship could be created
**Status:** ⚠️ **Not applicable** - method doesn't call addSalonRelationship at all

---

## 7. Confirmed Root Cause

### 7.1 Primary Root Cause

**CustomerBookingService::createBooking does not establish customer-provider relationship**

**Evidence:**
- Method creates bookings (line 77) without calling `addSalonRelationship`
- Customer portal bookings use this path exclusively
- Historical 11 customers with bookings but no relationships likely created via this path
- No test coverage verifies relationship creation in this path

**Impact:**
- Customer portal bookings create bookings without `customer_providers` relationships
- These customers do not appear in the owner's Clients list
- The issue affects both historical data and new bookings via customer portal

### 7.2 Secondary Root Cause

**Method name typo in BookingService::createBookingWithAccount (line 479)**

**Evidence:**
- Line 479 calls `addProviderRelationship` which does not exist
- Actual method name is `addSalonRelationship`
- This would cause a runtime error if executed

**Impact:**
- Runtime error if this code path is executed
- May prevent relationship creation in portal account bookings

---

## 8. Missing Test Coverage

### 8.1 Critical Missing Tests

1. **CustomerBookingService::createBooking relationship test**
   - Should verify `customer_providers` row exists after booking
   - Should verify relationship uses correct provider_id
   - Should verify idempotency (calling twice doesn't create duplicates)

2. **BookingService::createGuestBooking relationship test**
   - Should verify `customer_providers` row exists after booking
   - Should verify relationship uses correct provider_id from request

3. **BookingService::createBookingWithAccount relationship test**
   - Should verify `customer_providers` row exists after booking
   - Should verify relationship uses correct provider_id from request
   - Should catch the method name typo (addProviderRelationship vs addSalonRelationship)

### 8.2 Recommended Test Additions

```php
// In BookingServicePersistenceTest.php
public function test_create_guest_booking_creates_customer_provider_relationship()
{
    $provider = Provider::factory()->create();
    $location = Location::factory()->create(['provider_id' => $provider->id]);
    $service = Service::factory()->create(['provider_id' => $provider->id]);

    $bookingService = app(BookingServiceService::class);

    $booking = $bookingService->createGuestBooking([
        'provider_id' => $provider->id,
        'location_id' => $location->id,
        'customer_name' => 'Test Customer',
        'customer_phone' => '+1234567890',
        'service_id' => $service->id,
        'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
        'time' => '10:00:00',
    ]);

    // Verify customer_providers relationship exists
    $hasProviderRelationship = DB::table('customer_providers')
        ->where('customer_id', $booking->customer_id)
        ->where('provider_id', $provider->id)
        ->exists();

    $this->assertTrue($hasProviderRelationship);
}

// In CustomerBookingReminderIntegrationTest.php
public function test_customer_booking_creates_customer_provider_relationship()
{
    $provider = Provider::factory()->create();
    $location = Location::factory()->create(['provider_id' => $provider->id]);
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['provider_id' => $provider->id]);

    $bookingData = [
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'service_id' => $service->id,
        'date' => now()->addDay()->toDateString(),
        'time' => '10:00',
    ];

    $booking = $this->customerBookingService->createBooking($bookingData);

    // Verify customer_providers relationship exists
    $hasProviderRelationship = DB::table('customer_providers')
        ->where('customer_id', $customer->id)
        ->where('provider_id', $provider->id)
        ->exists();

    $this->assertTrue($hasProviderRelationship);
}
```

---

## 9. Proposed Minimal Fix

### 9.1 Fix CustomerBookingService::createBooking

**File:** `backend/app/Services/CustomerBookingService.php`
**Location:** After line 98 (after booking creation, before visit increment)

**Add:**
```php
// Ensure customer has provider relationship (domain integrity)
$providerId = $location->provider_id;
if (!$customer->providers()->where('provider_id', $providerId)->exists()) {
    $customerService = app(\App\Services\CustomerService::class);
    $customerService->addSalonRelationship($customer, $providerId);
}
```

**Rationale:**
- Matches the pattern used in BookingService
- Uses provider_id derived from location (already validated for ownership)
- Inside existing DB transaction
- Idempotent (safe to call multiple times)

### 9.2 Fix Method Name Typo

**File:** `backend/app/Services/BookingService.php`
**Location:** Line 479

**Change:**
```php
// Before:
$this->customerService->addProviderRelationship($customer, $providerId);

// After:
$this->customerService->addSalonRelationship($customer, $providerId);
```

**Rationale:**
- Matches actual method name in CustomerService
- Prevents runtime error
- Consistent with other calls in the same file

### 9.3 Add Missing Tests

Add the test cases outlined in Section 8.2 to:
- `BookingServicePersistenceTest.php`
- `CustomerBookingReminderIntegrationTest.php`

---

## 10. Historical Data Repair

### 10.1 Backfill Strategy

**Target:** 11 customers with bookings but no `customer_providers` relationships

**Method:** Use `CustomerService::addSalonRelationship()` for each verified customer

**Safety:**
- Idempotent (safe to rerun)
- Unique constraint prevents duplicates
- ON CONFLICT clause handles edge cases
- Already verified in `HISTORICAL_CUSTOMER_PROVIDER_RECONCILIATION_REPORT.md`

**Customer IDs (from reconciliation report):**
1. `01a0f68d-6999-7219-96d5-1d7c1c7d33e9`
2. `01a0f6f8-a466-7229-9571-bd5c2a8b82fe`
3. `01a10a86-3d78-7151-beac-0acb3b87ba3c`
4. `01a10aeb-4edf-73c9-a14e-a022d4972699`
5. `01a11221-d69e-7016-9db2-a459edb5e8e5`
6. `01a113b5-adca-70b3-b21b-6e0ad248cd8c`
7. `01a11cfb-64ca-73a0-b5c3-d499e44137d5`
8. `01a11cfd-bba2-704f-aaa7-9bff13b826fa`
9. `01a11cff-a309-70ff-9307-488e6ea0a8ad`
10. `01a11d23-aaec-72df-9730-ab7f2755190f`
11. `01a11d41-1fd2-7102-8963-ade969357ea5`

**Provider ID:** `01a0e836-39fa-700e-991d-797669bb5a32`

### 10.2 Backfill Execution (After Approval)

```php
$customerService = app(\App\Services\CustomerService::class);
$providerId = '01a0e836-39fa-700e-991d-797669bb5a32';

$customerIds = [
    '01a0f68d-6999-7219-96d5-1d7c1c7d33e9',
    '01a0f6f8-a466-7229-9571-bd5c2a8b82fe',
    // ... (all 11 customer IDs)
];

foreach ($customerIds as $customerId) {
    $customer = \App\Models\Customer::find($customerId);
    if ($customer) {
        $customerService->addSalonRelationship($customer, $providerId);
    }
}
```

---

## 11. Verification Steps

### 11.1 After Code Fix

1. **Run existing tests:**
   ```bash
   php artisan test --filter=BookingCustomerProviderRelationshipTest
   php artisan test --filter=BookingServicePersistenceTest
   php artisan test --filter=CustomerBookingReminderIntegrationTest
   ```

2. **Run new tests:** (after adding them)
   ```bash
   php artisan test --filter=test_create_guest_booking_creates_customer_provider_relationship
   php artisan test --filter=test_customer_booking_creates_customer_provider_relationship
   ```

3. **Test customer portal booking flow:**
   - Create booking via customer portal
   - Verify `customer_providers` row exists
   - Verify customer appears in owner's Clients list

### 11.2 After Backfill

1. **Verify database state:**
   ```sql
   SELECT COUNT(*) FROM customer_providers WHERE provider_id = '01a0e836-39fa-700e-991d-797669bb5a32';
   -- Should be 13 (2 existing + 11 backfilled)
   ```

2. **Verify API endpoint:**
   - Call `/api/v1/customers` for the provider
   - Confirm all 13 customers appear
   - Verify no customers from other providers appear

3. **Verify application behavior:**
   - Log in as owner
   - Navigate to Clients list
   - Confirm historical customers are visible
   - Verify no cross-provider leakage

---

## 12. Conclusion

### 12.1 Root Cause Summary

**Primary:** `CustomerBookingService::createBooking` does not call `addSalonRelationship` to establish the customer-provider relationship. This affects customer portal bookings exclusively.

**Secondary:** Method name typo in `BookingService::createBookingWithAccount` line 479 calls non-existent `addProviderRelationship` instead of `addSalonRelationship`.

**Historical:** 11 customers with bookings lack relationships because they were created via the customer portal before the relationship creation was implemented in other paths.

### 12.2 Impact Assessment

**Current State:**
- Public/Owner/Walk-in bookings: ✅ Protected (relationship created)
- Website with account bookings: ✅ Protected (relationship created, but has typo)
- Customer portal bookings: ❌ **Not protected** (relationship not created)

**Historical Data:**
- 11 customers require backfill
- 2 customers already have relationships
- All 13 should appear in Clients list after backfill

### 12.3 Recommended Actions

**Priority 1 (Immediate):**
1. Fix method name typo in BookingService line 479
2. Add `addSalonRelationship` call to CustomerBookingService::createBooking
3. Add missing test coverage for all booking paths

**Priority 2 (After Code Fix):**
1. Execute backfill for 11 historical customers
2. Verify database state and API endpoint
3. Perform manual smoke test in application

**Do NOT:**
- Weaken customer queries to compensate for missing relationships
- Modify customer records or booking data
- Deploy to production without staging verification
- Run backfill without approval

---

## 13. Appendix: File References

### Controllers
- `backend/app/Http/Controllers/Api/V1/BookingController.php` (lines 115-257, 534-574)
- `backend/app/Http/Controllers/Api/V1/CustomerBookingController.php` (lines 176-240)

### Services
- `backend/app/Services/BookingService.php` (lines 196-198, 300-303, 478-480)
- `backend/app/Services/CustomerBookingService.php` (lines 29-115)
- `backend/app/Services/CustomerService.php` (lines 104-121)
- `backend/app/Services/BookingOrchestrator.php` (lines 44-234)
- `backend/app/Services/AvailabilityEngine.php` (lines 381-419)

### Tests
- `backend/tests/Unit/BookingCustomerProviderRelationshipTest.php`
- `backend/tests/Unit/BookingServicePersistenceTest.php`
- `backend/tests/Feature/CustomerBookingReminderIntegrationTest.php`
- `backend/tests/Feature/BookingWithAccountEmailTest.php`

### Reports
- `backend/HISTORICAL_CUSTOMER_PROVIDER_RECONCILIATION_REPORT.md`
- `backend/CUSTOMER_PROVIDER_VERIFICATION_REPORT.md`
